<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Log as ModelsLog;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Amenity;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class UnitsController extends RealEstateController
{
    public const LOG = 're_unit_log';

    /** أقصى ما يُنشأ من وحدات في طلب «دفعة واحدة». */
    private const BULK_LIMIT = 200;

    private const RESIDENTIAL_SPECS = ['bedrooms', 'bathrooms', 'living_rooms', 'kitchens'];

    private const COMMERCIAL_SPECS = ['frontage_m', 'ceiling_height_m', 'openings_count', 'permitted_activity'];

    public function __construct()
    {
        $this->middleware('permission.role:realestate.units.view', ['only' => ['index']]);
        $this->middleware('permission.role:realestate.units.show', ['only' => ['show']]);
        $this->middleware('permission.role:realestate.units.create', ['only' => ['create', 'store', 'bulkStore']]);
        $this->middleware('permission.role:realestate.units.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:realestate.units.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:realestate.units.change_status', ['only' => ['changeStatus']]);
    }

    public function index(Request $request)
    {
        $query = $this->scoped(Unit::query())->with(['building', 'floor', 'activeContract.tenant'])->latest('id');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('unit_no', 'like', $term)->orWhere('description', 'like', $term)
                ->orWhereHas('building', fn ($b) => $b->where('name_ar', 'like', $term)->orWhere('code', 'like', $term)));
        });

        foreach (['building_id', 'unit_type', 'usage_type', 'occupancy_status', 'furnished_status', 'bedrooms'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        $query->when($request->filled('rent_from'), fn ($q) => $q->where('base_rent', '>=', (float) $request->rent_from));
        $query->when($request->filled('rent_to'), fn ($q) => $q->where('base_rent', '<=', (float) $request->rent_to));

        $units = $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.units.partials.table', compact('units'))->render();
        }

        $counts = $this->scoped(Unit::query())->selectRaw('occupancy_status, COUNT(*) as c')->groupBy('occupancy_status')->pluck('c', 'occupancy_status');

        return view('rentalmanagement::realestate.units.index', [
            'units' => $units,
            'buildings' => $this->buildings(),
            'bulkBuildings' => $this->formBuildings(),
            'counts' => $counts,
            'total' => $counts->sum(),
        ]);
    }

    public function create(Request $request)
    {
        $building = $request->filled('building_id') ? $this->scoped(Building::query())->find($request->input('building_id')) : null;

        // عقارٌ وحيد الوحدة: وحدته وُلدت معه وتُدار من صفحته
        if ($building?->isSingleUnit()) {
            return redirect()->route('realestate.buildings.show', $building->id)
                ->with('error', 'هذا العقار ('.V::label(V::ENTITY_TYPES, $building->entity_type).') وحدتُه واحدة وأُنشئت معه؛ عدّلها من «تعديل العقار».');
        }

        $usage = $building && $building->usage_type !== 'mixed' ? $building->usage_type : 'residential';

        return view('rentalmanagement::realestate.units.form', [
            'unit' => new Unit([
                'building_id' => $building?->id,
                'usage_type' => $usage,
                'unit_type' => $usage === 'commercial' ? 'shop' : 'apartment', 'occupancy_status' => 'vacant', 'furnished_status' => 'unfurnished',
                'unit_condition' => 'good', 'rent_period' => 'yearly', 'is_marketable' => true,
            ]),
            'buildings' => $this->formBuildings(),
            'amenities' => Amenity::where('is_active', true)->orderBy('name')->get(),
            'selectedAmenities' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $unit = new Unit($data);
        $unit->images = $this->storeFiles($request, 'images', 'units');
        $unit->vacant_since = $unit->occupancy_status === 'vacant' ? now()->toDateString() : null;
        $unit->save();
        $unit->amenities()->sync($request->input('amenities', []));

        OperationLog::record('created', self::LOG, $unit, sprintf('أُضيفت الوحدة **%s**', $unit->full_label));

        return $this->ok($request, 'تمت إضافة الوحدة بنجاح', route('realestate.units.show', $unit->id));
    }

    /**
     * «إضافة وحدات دفعة واحدة» لعمارة: من رقم … إلى رقم …، بالطابق والنوع
     * والاستخدام والمساحة والسعر نفسها. كلها أو لا شيء.
     */
    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'building_id' => 'required|exists:re_buildings,id',
            'from_no' => 'required|integer|min:0|max:999999',
            'to_no' => 'required|integer|gte:from_no|max:999999',
            'prefix' => 'nullable|string|max:20',
            'floor_id' => ['nullable', Rule::exists('re_floors', 'id')->where('building_id', $request->input('building_id'))],
            'unit_type' => 'required|'.V::in(V::UNIT_TYPES),
            'usage_type' => 'nullable|'.V::in(V::UNIT_USAGE_TYPES),
            'area_sqm' => 'nullable|numeric|min:0',
            'base_rent' => 'nullable|numeric|min:0',
            'rent_period' => 'nullable|'.V::in(V::RENT_PERIODS),
            'bedrooms' => 'nullable|integer|min:0|max:50',
            'bathrooms' => 'nullable|integer|min:0|max:50',
            'living_rooms' => 'nullable|integer|min:0|max:50',
            'kitchens' => 'nullable|integer|min:0|max:20',
            'furnished_status' => 'nullable|'.V::in(V::FURNISHED),
            'frontage_m' => 'nullable|numeric|min:0|max:100000',
            'ceiling_height_m' => 'nullable|numeric|min:0|max:1000',
            'openings_count' => 'nullable|integer|min:0|max:1000',
            'permitted_activity' => 'nullable|string|max:255',
        ], ['to_no.gte' => '«إلى رقم» يجب ألا يقلّ عن «من رقم».'], self::attributes() + ['from_no' => 'من رقم', 'to_no' => 'إلى رقم', 'prefix' => 'بادئة الرقم']);

        $count = $data['to_no'] - $data['from_no'] + 1;

        if ($count > self::BULK_LIMIT) {
            throw ValidationException::withMessages(['to_no' => sprintf('الحد الأقصى %d وحدة في الدفعة الواحدة (المطلوب %d).', self::BULK_LIMIT, $count)]);
        }

        $building = $this->findScoped(Building::class, $data['building_id']);
        $this->guardSingleUnitBuilding($building, null);

        $usage = $this->resolveUsage($building, null, $data['unit_type'], $data['usage_type'] ?? null);
        $prefix = (string) ($data['prefix'] ?? '');
        $numbers = array_map(fn (int $n) => $prefix.$n, range($data['from_no'], $data['to_no']));

        $taken = $building->units()->whereIn('unit_no', $numbers)->pluck('unit_no');

        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages(['from_no' => sprintf(
                'أرقامٌ مستخدمة في هذا المبنى: %s%s. لم تُنشأ أي وحدة.', $taken->take(10)->implode('، '), $taken->count() > 10 ? '…' : ''
            )]);
        }

        $shared = $this->normalizeSpecs([
            'building_id' => $building->id,
            'floor_id' => $data['floor_id'] ?? null,
            'unit_type' => $data['unit_type'],
            'usage_type' => $usage,
            'area_sqm' => $data['area_sqm'] ?? null,
            'base_rent' => $data['base_rent'] ?? 0,
            'rent_period' => $data['rent_period'] ?? 'yearly',
            'furnished_status' => $data['furnished_status'] ?? 'unfurnished',
            'unit_condition' => 'good',
            'occupancy_status' => 'vacant',
            'vacant_since' => now()->toDateString(),
            'is_marketable' => true,
        ] + array_intersect_key($data, array_flip(array_merge(self::RESIDENTIAL_SPECS, self::COMMERCIAL_SPECS))));

        $created = DB::transaction(function () use ($numbers, $shared, $building) {
            $ids = [];

            foreach ($numbers as $number) {
                Unit::releaseTrashedNumber($building->id, $number);
                $ids[] = Unit::create($shared + ['unit_no' => $number])->id;
            }

            return $ids;
        });

        OperationLog::record('created', BuildingsController::LOG, $building, sprintf(
            'أُضيفت %d وحدة دفعةً واحدة إلى العقار **%s** (من %s إلى %s)', count($created), $building->name_ar, $numbers[0], end($numbers)
        ));

        return $this->ok($request, sprintf('أُضيفت %d وحدة', count($created)), route('realestate.buildings.show', $building->id));
    }

    public function show($id)
    {
        $unit = $this->findScoped(Unit::class, $id, ['building.owner', 'floor', 'amenities', 'activeContract.tenant', 'creator']);

        return view('rentalmanagement::realestate.units.show', [
            'unit' => $unit,
            'contracts' => $unit->contracts()->with('tenant')->latest('start_date')->get(),
            'listings' => $unit->listings()->latest('id')->get(),
            'maintenance' => $unit->maintenanceRequests()->latest('id')->limit(30)->get(),
            'logs' => hasPermission('realestate.logs.view')
                ? ModelsLog::where('type', self::LOG)->where('type_id', $unit->id)->latest()->limit(30)->get()
                : collect(),
        ]);
    }

    public function edit($id)
    {
        $unit = $this->findScoped(Unit::class, $id, ['amenities']);

        return view('rentalmanagement::realestate.units.form', [
            'unit' => $unit,
            'buildings' => $this->formBuildings($unit->building_id),
            'amenities' => Amenity::where('is_active', true)->orderBy('name')->get(),
            'selectedAmenities' => $unit->amenities->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $unit = $this->findScoped(Unit::class, $id);
        $data = $this->validated($request, $unit);

        $changes = OperationLog::diff($unit->fill($data), array_keys($data));
        $unit->images = $this->storeFiles($request, 'images', 'units', $unit->getOriginal('images'));

        if ($unit->isDirty('occupancy_status')) {
            $unit->vacant_since = $unit->occupancy_status === 'vacant' ? now()->toDateString() : null;
        }

        $unit->save();
        $unit->amenities()->sync($request->input('amenities', []));

        OperationLog::record('updated', self::LOG, $unit, sprintf('عُدّلت الوحدة **%s**', $unit->full_label), $changes);

        return $this->ok($request, 'تم تحديث الوحدة', route('realestate.units.show', $unit->id));
    }

    public function changeStatus(Request $request, $id)
    {
        $unit = $this->findScoped(Unit::class, $id);
        $data = $request->validate(['occupancy_status' => 'required|'.V::in(V::OCCUPANCY)], [], ['occupancy_status' => 'حالة الإشغال']);
        $hasContract = $unit->activeContract()->exists();

        $error = match (true) {
            $hasContract && $data['occupancy_status'] !== 'rented' => 'على الوحدة عقدٌ سارٍ — أنهِ العقد أولاً لتغيير حالتها.',
            // «مؤجرة» نتيجةُ عقدٍ سارٍ لا اختيارٌ يدوي؛ «تحت الصيانة» وبقية الحالات تبقى يدوية
            ! $hasContract && $data['occupancy_status'] === 'rented' => self::rentedMessage(),
            default => null,
        };

        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $old = $unit->occupancy_status;
        $unit->markOccupancy($data['occupancy_status']);

        OperationLog::record('status_changed', self::LOG, $unit, sprintf(
            'تغيّرت حالة الوحدة **%s** من «%s» إلى «%s»',
            $unit->full_label, V::label(V::OCCUPANCY, $old), V::label(V::OCCUPANCY, $unit->occupancy_status)
        ));

        return $this->ok($request, 'تم تغيير حالة الوحدة');
    }

    public function destroy(Request $request, $id)
    {
        $unit = $this->findScoped(Unit::class, $id);

        $message = match (true) {
            // 8) الوحدة التلقائية جزءٌ من عقارها: تُحذف بحذفه فقط
            (bool) $unit->is_auto => 'هذه الوحدة أُنشئت تلقائياً مع عقارها الوحيد الوحدة ولا تُحذف وحدها؛ احذف العقار نفسه إن لزم.',
            $unit->contracts()->exists() => 'للوحدة عقودٌ مسجّلة؛ اجعلها «غير متاحة» بدل حذفها.',
            ($listings = $unit->openListings()->count()) > 0 => sprintf('على الوحدة %d عرضاً لم يُغلق؛ أغلق العرض أولاً ثم احذف الوحدة.', $listings),
            ($requests = $unit->openMaintenanceRequests()->count()) > 0 => sprintf('على الوحدة %d طلب صيانة مفتوحاً؛ أكمله أو ألغه أولاً ثم احذف الوحدة.', $requests),
            default => null,
        };

        if ($message) {
            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        $label = $unit->full_label;
        $unit->delete();

        OperationLog::record('deleted', self::LOG, $unit->id, sprintf('حُذفت الوحدة **%s**', $label));

        return $this->ok($request, 'تم حذف الوحدة', route('realestate.units.index'));
    }

    /** بيانات وحدةٍ لنموذج العقد والعرض: السعر الإرشادي والمالك والاستخدام والحالة. */
    public function info($id)
    {
        $unit = $this->findScoped(Unit::class, $id, ['building.owner', 'floor', 'amenities']);
        $building = $unit->building;
        $owner = $building?->owner;

        return response()->json([
            'listing_draft' => $unit->listingDraft(),
            'id' => $unit->id,
            'label' => $unit->full_label,
            'building_id' => $unit->building_id,
            'owner_id' => $building?->owner_id,
            'owner_name' => $owner->display_name ?? $owner->name ?? null,
            // استخدام الوحدة هو المعتمد في نوع العقد والضريبة (لا استخدام المبنى)
            'usage_type' => $unit->usage_type ?: (in_array($building?->usage_type, ['residential', 'commercial'], true) ? $building->usage_type : 'residential'),
            'building_usage_type' => $building?->usage_type,
            'unit_type' => $unit->unit_type,
            'is_auto' => (bool) $unit->is_auto,
            'owner_vat_registered' => $owner && Schema::hasColumn('re_owners', 'is_vat_registered') ? (bool) $owner->is_vat_registered : false,
            'ownership' => $building?->isOwn() ? 'own' : 'managed',
            'base_rent' => (float) $unit->base_rent,
            'rent_period' => $unit->rent_period,
            'annual_rent' => $unit->rent_period === 'monthly' ? (float) $unit->base_rent * 12 : (float) $unit->base_rent,
            'area' => $unit->area_sqm,
            'bedrooms' => $unit->bedrooms,
            'permitted_activity' => $unit->permitted_activity,
            'occupancy' => $unit->occupancy_meta,
            'is_marketable' => $unit->is_marketable,
        ]);
    }

    private function validated(Request $request, ?Unit $unit = null): array
    {
        $data = $request->validate([
            'building_id' => 'required|exists:re_buildings,id',
            'floor_id' => ['nullable', Rule::exists('re_floors', 'id')->where('building_id', $request->input('building_id'))],
            // فريدٌ داخل المبنى لا على مستوى الشركة
            'unit_no' => ['required', 'string', 'max:50', Rule::unique('re_units', 'unit_no')
                ->where('building_id', $request->input('building_id'))->whereNull('deleted_at')->ignore($unit?->id)],
            'unit_type' => 'required|'.V::in(V::UNIT_TYPES),
            'usage_type' => 'nullable|'.V::in(V::UNIT_USAGE_TYPES),
            'area_sqm' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0|max:50',
            'bathrooms' => 'nullable|integer|min:0|max:50',
            'living_rooms' => 'nullable|integer|min:0|max:50',
            'kitchens' => 'nullable|integer|min:0|max:20',
            'parking_count' => 'nullable|integer|min:0|max:100',
            'frontage_m' => 'nullable|numeric|min:0|max:100000',
            'ceiling_height_m' => 'nullable|numeric|min:0|max:1000',
            'openings_count' => 'nullable|integer|min:0|max:1000',
            'permitted_activity' => 'nullable|string|max:255',
            'orientation' => 'nullable|'.V::in(V::ORIENTATIONS),
            'furnished_status' => 'nullable|'.V::in(V::FURNISHED),
            'unit_condition' => 'required|'.V::in(V::UNIT_CONDITIONS),
            'occupancy_status' => 'required|'.V::in(V::OCCUPANCY),
            'availability_from' => 'nullable|date',
            'base_rent' => 'nullable|numeric|min:0',
            'rent_period' => 'required|'.V::in(V::RENT_PERIODS),
            'electricity_meter' => 'nullable|string|max:100',
            'water_meter' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'amenities' => 'nullable|array',
            'amenities.*' => 'exists:re_amenities,id',
        ], ['unit_no.unique' => 'رقم الوحدة مستخدم في هذا المبنى.'], self::attributes());

        $building = $this->findScoped(Building::class, $data['building_id']);
        $this->guardSingleUnitBuilding($building, $unit);

        // الوحدة التلقائية نوعها من نوع عقارها، لا من النموذج
        if ($unit?->is_auto) {
            $data['unit_type'] = $unit->unit_type;
        }

        $data['usage_type'] = $this->resolveUsage($building, $unit, $data['unit_type'], $data['usage_type'] ?? null);

        // حالة الإشغال لوحدةٍ عليها عقدٌ سارٍ يملكها العقد؛ و«مؤجرة» لا تُختار يدوياً بلا عقد
        if ($unit && $unit->activeContract()->exists()) {
            unset($data['occupancy_status']);
        } elseif ($data['occupancy_status'] === 'rented' && $unit?->occupancy_status !== 'rented') {
            throw ValidationException::withMessages(['occupancy_status' => self::rentedMessage()]);
        }

        unset($data['amenities']);
        $data['is_marketable'] = $request->boolean('is_marketable');
        $data['furnished_status'] = $data['furnished_status'] ?? 'unfurnished';

        foreach (['parking_count', 'base_rent'] as $numeric) {
            $data[$numeric] = $data[$numeric] ?? 0;
        }

        // رقمٌ تحمله وحدةٌ محذوفة يُحرَّر لها (الفهرس الفريد يشمل المحذوف) فلا يسقط الحفظ بخطأ 500
        Unit::releaseTrashedNumber($building->id, $data['unit_no'], $unit?->id);

        return $this->normalizeSpecs($data);
    }

    /** 8) عقارٌ وحيد الوحدة لا تُضاف له وحدة ثانية، ووحدته التلقائية لا تُنقل منه. */
    private function guardSingleUnitBuilding(Building $building, ?Unit $unit): void
    {
        if ($building->isSingleUnit() && (! $unit || (int) $unit->building_id !== (int) $building->id)) {
            throw ValidationException::withMessages(['building_id' => sprintf(
                '«%s» عقارٌ وحيد الوحدة (%s) ووحدته أُنشئت معه؛ لا تُضاف له وحدة ثانية.', $building->name, V::label(V::ENTITY_TYPES, $building->entity_type)
            )]);
        }

        if ($unit?->is_auto && (int) $unit->building_id !== (int) $building->id) {
            throw ValidationException::withMessages(['building_id' => 'الوحدة التلقائية تتبع عقارها ولا تُنقل إلى مبنى آخر.']);
        }
    }

    /**
     * استخدام الوحدة (القواعد 1 و2 و7): مبنى سكني أو تجاري يقفله عليه، والمختلط
     * يُختار لكل وحدة وافتراضيه من نوعها. والنوع يجب أن يوافق الاستخدام.
     */
    private function resolveUsage(Building $building, ?Unit $unit, string $unitType, ?string $requested): string
    {
        $hasContract = $unit && $unit->usage_type && $unit->activeContract()->exists();

        if (in_array($building->usage_type, ['residential', 'commercial'], true)) {
            // وحدةٌ قديمة تخالف مبناها وعليها عقدٌ سارٍ تبقى على استخدامها حتى ينتهي العقد
            $usage = $hasContract && $unit->usage_type !== $building->usage_type && (int) $unit->building_id === (int) $building->id
                ? $unit->usage_type
                : $building->usage_type;

            if ($requested && $requested !== $usage) {
                throw ValidationException::withMessages(['usage_type' => sprintf(
                    'المبنى «%s» استخدامه %s، فوحداته كلها %s. اجعل المبنى «مختلط» إن أردت وحدةً %s فيه.',
                    $building->name, V::label(V::USAGE_TYPES, $building->usage_type), V::label(V::USAGE_TYPES, $building->usage_type),
                    V::label(V::UNIT_USAGE_TYPES, $requested)
                )]);
            }
        } else {
            $usage = $requested ?: V::unitUsageFor($unitType) ?: $unit?->usage_type;

            if (! $usage) {
                throw ValidationException::withMessages(['usage_type' => 'اختر استخدام الوحدة: سكني أو تجاري.']);
            }
        }

        // 7) تغيير استخدام وحدة عليها عقد سارٍ
        if ($hasContract && $usage !== $unit->usage_type) {
            throw ValidationException::withMessages(['usage_type' => 'على الوحدة عقدٌ سارٍ؛ لا يتغيّر استخدامها قبل إنهاء العقد.']);
        }

        // 1) نوع الوحدة يوافق استخدامها — الوحدة التلقائية نوعها من عقارها فتُستثنى
        if (! $unit?->is_auto && ! V::unitTypeAllowed($unitType, $usage)) {
            // وحدةٌ قديمة لم يتغيّر نوعها ولا استخدامها لا تُحبس عن بقية التعديلات
            $unchanged = $unit && $unit->unit_type === $unitType && $unit->usage_type === $usage;

            if (! $unchanged) {
                throw ValidationException::withMessages(['unit_type' => sprintf(
                    'نوع الوحدة «%s» لا يكون في وحدة استخدامها %s. الأنواع المتاحة: %s.',
                    V::label(V::UNIT_TYPES, $unitType), V::label(V::UNIT_USAGE_TYPES, $usage),
                    collect(V::unitTypesFor($usage))->except('other')->pluck('label')->implode('، ')
                )]);
            }
        }

        return $usage;
    }

    /** مواصفات تخصّ استخداماً لا تبقى على وحدةٍ من الاستخدام الآخر (أرض بأربع غرف، محل بغرف نوم). */
    private function normalizeSpecs(array $data): array
    {
        $residentialSpecs = $data['usage_type'] === 'residential' && $data['unit_type'] !== 'land';

        foreach (self::RESIDENTIAL_SPECS as $field) {
            $data[$field] = $residentialSpecs ? (int) ($data[$field] ?? 0) : 0;
        }

        if (! $residentialSpecs) {
            $data['furnished_status'] = 'unfurnished';
        }

        if ($data['usage_type'] !== 'commercial') {
            foreach (self::COMMERCIAL_SPECS as $field) {
                $data[$field] = null;
            }
        }

        if ($data['unit_type'] === 'land') {
            $data['floor_id'] = null;
        }

        return $data;
    }

    /**
     * مباني نموذج الوحدة مع طوابقها ونوعها واستخدامها. العقار وحيد الوحدة
     * لا يظهر (وحدته معه) إلا إن كان مبنى الوحدة التي تُعدَّل.
     */
    private function formBuildings(?int $include = null)
    {
        return $this->scoped(Building::query())->with('floors')->orderBy('name_ar')->get()
            ->filter(fn (Building $b) => ! $b->isSingleUnit() || $b->id === $include)->values();
    }

    private static function rentedMessage(): string
    {
        return 'حالة «مؤجرة» تأتي من عقدٍ سارٍ على الوحدة ولا تُختار يدوياً. أنشئ العقد وتتغيّر الحالة تلقائياً.';
    }

    /** أسماء الحقول بالعربية لرسائل التحقق. */
    private static function attributes(): array
    {
        return [
            'building_id' => 'المبنى', 'floor_id' => 'الطابق', 'unit_no' => 'رقم الوحدة', 'unit_type' => 'نوع الوحدة',
            'usage_type' => 'استخدام الوحدة', 'area_sqm' => 'المساحة', 'bedrooms' => 'غرف النوم', 'bathrooms' => 'دورات المياه',
            'living_rooms' => 'الصالات', 'kitchens' => 'المطابخ', 'parking_count' => 'المواقف',
            'frontage_m' => 'الواجهة (م)', 'ceiling_height_m' => 'ارتفاع السقف (م)', 'openings_count' => 'عدد الفتحات',
            'permitted_activity' => 'النشاط المسموح', 'orientation' => 'الاتجاه', 'furnished_status' => 'التأثيث',
            'unit_condition' => 'حالة الوحدة الفنية', 'occupancy_status' => 'حالة الإشغال', 'availability_from' => 'تاريخ الإتاحة',
            'base_rent' => 'السعر الإرشادي', 'rent_period' => 'دورية السعر', 'electricity_meter' => 'رقم عداد الكهرباء',
            'water_meter' => 'رقم عداد المياه', 'description' => 'الوصف', 'notes' => 'الملاحظات', 'amenities' => 'المرافق',
            'images' => 'صور الوحدة',
        ];
    }
}
