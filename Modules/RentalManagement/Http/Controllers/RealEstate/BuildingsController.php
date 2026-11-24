<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Log as ModelsLog;
use App\Models\StoreHouse;
use App\Models\Treasury;
use App\Support\DocumentSections;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Complex;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\MaintenanceRequest;
use Modules\RentalManagement\Models\PaymentAllocation;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Services\PropertyAccountService;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class BuildingsController extends RealEstateController
{
    public const LOG = 're_building_log';

    /** المجمع كيانٌ مستقل وله سجلّه — كان يُكتب تحت سجل المباني بمعرّف المجمع. */
    public const COMPLEX_LOG = 're_complex_log';

    public function __construct()
    {
        $this->middleware('permission.role:realestate.buildings.view', ['only' => ['index', 'map', 'complexes']]);
        $this->middleware('permission.role:realestate.buildings.show', ['only' => ['show']]);
        $this->middleware('permission.role:realestate.buildings.create', ['only' => ['create', 'store', 'storeComplex']]);
        $this->middleware('permission.role:realestate.buildings.edit', ['only' => ['edit', 'update', 'storeFloor', 'destroyFloor', 'updateComplex']]);
        $this->middleware('permission.role:realestate.buildings.delete', ['only' => ['destroy', 'destroyComplex']]);
    }

    /** كل العقارات التي حُدّد موقعها على خريطةٍ واحدة. */
    public function map()
    {
        // "name" وصفٌ محسوب (name_ar/name_en) لا عمودٌ حقيقي — orderBy('name')
        // كان يفشل على MySQL (42S22) ويمرّ صامتاً بلا فرزٍ فعليّ على SQLite
        $buildings = $this->scoped(Building::query())->whereNotNull('latitude')->whereNotNull('longitude')
            ->withCount(['units', 'units as vacant_units_count' => fn ($q) => $q->where('occupancy_status', 'vacant')])
            ->orderBy('name_ar')->get();

        $canShow = hasPermission('realestate.buildings.show');

        return view('rentalmanagement::realestate.buildings.map', [
            'points' => $buildings->map(fn (Building $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'lat' => (float) $b->latitude,
                'lng' => (float) $b->longitude,
                'address' => collect([$b->city, $b->district, $b->address])->filter()->implode('، '),
                'units' => $b->units_count,
                'vacant' => $b->vacant_units_count,
                'focus' => (int) request('focus') === $b->id,
                'url' => $canShow ? route('realestate.buildings.show', $b->id) : null,
            ])->values(),
            'missing' => $this->scoped(Building::query())->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->count(),
        ]);
    }

    public function index(Request $request)
    {
        $query = $this->scoped(Building::query())
            ->with(['owner', 'complex'])
            ->withCount([
                'units',
                'units as rented_units_count' => fn ($q) => $q->where('occupancy_status', 'rented'),
                'units as vacant_units_count' => fn ($q) => $q->where('occupancy_status', 'vacant'),
            ])
            ->latest('id');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term)
                ->orWhere('code', 'like', $term)->orWhere('address', 'like', $term));
        });

        foreach (['city', 'entity_type', 'usage_type', 'status', 'owner_id', 'complex_id'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        $buildings = $query->paginate($this->perPage($request));
        $view = $request->input('view', 'cards') === 'table' ? 'table' : 'cards';

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.buildings.partials.'.$view, compact('buildings'))->render();
        }

        $scope = $this->scoped(Building::query());

        return view('rentalmanagement::realestate.buildings.index', [
            'buildings' => $buildings,
            'view' => $view,
            'cities' => (clone $scope)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'complexes' => $this->complexList(),
            'owners' => $this->owners(),
            'stats' => [
                'total' => (clone $scope)->count(),
                'active' => (clone $scope)->where('status', 'active')->count(),
                'maintenance' => (clone $scope)->where('status', 'maintenance')->count(),
                'complexes' => $this->scoped(Complex::query())->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('rentalmanagement::realestate.buildings.form', [
            'building' => new Building([
                'status' => 'active', 'entity_type' => 'building', 'usage_type' => 'residential', 'owner_id' => request('owner_id'),
                'ownership' => 'managed',
            ]),
            'autoUnit' => new Unit(['rent_period' => 'yearly', 'furnished_status' => 'unfurnished']),
            'code' => peek_document_number('re-building'),
            'complexes' => $this->complexList(),
            'owners' => $this->owners((int) request('owner_id') ?: null),
            'storeHouses' => $this->storeHouses(),
            'treasuries' => $this->treasuries(),
            'users' => $this->users(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $unitData, $floors] = $this->validated($request);

        try {
            $building = DB::transaction(function () use ($request, $data, $unitData, $floors) {
                $building = new Building($data);
                $building->code = next_document_number('re-building');
                $building->floors_count = 0;
                $building->images = $this->storeFiles($request, 'images', 'buildings');
                $building->attachments = $this->storeFiles($request, 'attachments', 'buildings');
                $building->save();
                $this->applyWarehouseChoice($request, $building);

                // الطوابق صفوفٌ تطابق العدد دائماً (الأرض بلا طوابق)، والعقار وحيد الوحدة تولد وحدته معه
                $building->syncFloors($floors ?? 0);
                $building->ensureAutoUnit($unitData);

                return $building;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG, 'store');

            throw $e;
        }

        OperationLog::record('created', self::LOG, $building, sprintf('أُضيف العقار **%s** (%s)', $building->name_ar, $building->code));

        $message = $building->isSingleUnit()
            ? 'تمت إضافة العقار ووحدته — يمكنك إنشاء العقد من هذه الصفحة مباشرة'
            : 'تمت إضافة العقار — أضف وحداته الآن من تبويب «الوحدات»';

        return $this->ok($request, $message, route('realestate.buildings.show', $building->id));
    }

    public function show($id)
    {
        $building = $this->findScoped(Building::class, $id, ['owner', 'manager', 'storeHouse', 'complex', 'floors.units', 'creator', 'defaultTreasuryAccount']);

        $units = $building->units()->with('floor', 'activeContract.tenant')->orderBy('unit_no')->get();
        $contracts = $building->contracts()->with('tenant', 'unit')->latest('id')->limit(50)->get();

        $collected = PaymentAllocation::whereHas('payment', fn ($q) => $q->where('posting_status', 'posted'))
            ->whereHas('schedule.contract', fn ($q) => $q->where('building_id', $building->id))->sum('amount');

        $overdue = PaymentSchedule::overdue()
            ->whereHas('contract', fn ($q) => $q->where('building_id', $building->id))
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid),0) as total')->value('total');

        return view('rentalmanagement::realestate.buildings.show', [
            'building' => $building,
            'units' => $units,
            'autoUnit' => $building->isSingleUnit() ? ($units->firstWhere('is_auto', true) ?? $units->first()) : null,
            'treasuryName' => $building->default_treasury_account_id
                ? (Treasury::where('account_id', $building->default_treasury_account_id)->value('name') ?? $building->defaultTreasuryAccount?->name)
                : null,
            'contracts' => $contracts,
            'listings' => $building->listings()->latest('id')->get()
                ->merge(Listing::whereIn('unit_id', $units->pluck('id'))->latest('id')->get())->unique('id'),
            'maintenance' => $building->maintenanceRequests()->latest('id')->limit(50)->get(),
            'finance' => [
                'active_value' => $contracts->whereIn('status', ['active', 'renewing'])->sum('annual_value'),
                'collected' => (float) $collected,
                'overdue' => (float) $overdue,
                'maintenance_cost' => (float) $building->maintenanceRequests()->sum('actual_cost'),
            ],
            'logs' => hasPermission('realestate.logs.view')
                ? ModelsLog::where('type', self::LOG)->where('type_id', $building->id)->latest()->limit(40)->get()
                : collect(),
        ]);
    }

    public function edit($id)
    {
        $building = $this->findScoped(Building::class, $id);
        $building->syncFloors();

        return view('rentalmanagement::realestate.buildings.form', [
            'building' => $building,
            'autoUnit' => ($building->isSingleUnit() ? ($building->autoUnit ?? $building->units()->first()) : null)
                ?? new Unit(['rent_period' => 'yearly', 'furnished_status' => 'unfurnished']),
            'code' => null,
            'complexes' => $this->complexList($building->complex_id),
            'owners' => $this->owners($building->owner_id),
            'storeHouses' => $this->storeHouses(),
            'treasuries' => $this->treasuries(),
            'users' => $this->users(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $building = $this->findScoped(Building::class, $id);
        [$data, $unitData, $floors] = $this->validated($request, $building);

        $wasSingle = $building->isSingleUnit();
        $floorsBefore = (int) $building->floors_count;

        $changes = OperationLog::diff($building->fill($data), array_keys($data));
        $building->images = $this->storeFiles($request, 'images', 'buildings', $building->getOriginal('images'));
        $building->attachments = $this->storeFiles($request, 'attachments', 'buildings', $building->getOriginal('attachments'));

        DB::transaction(function () use ($request, $building, $unitData, $floors, $wasSingle) {
            $building->save();
            $this->applyWarehouseChoice($request, $building);

            // «عدد الطوابق» لا يُكتب رقماً فقط: الصفوف تُنشأ أو تُحذف معه، وإن تعذّر رُفض التعديل كله
            $building->syncFloors($floors);

            if ($building->isSingleUnit()) {
                $building->ensureAutoUnit($unitData);
            } elseif ($wasSingle) {
                // صار عمارةً: وحدته تبقى وحدةً عادية تُدار من شاشة الوحدات
                $building->units()->where('is_auto', true)->update(['is_auto' => false]);
            }
        });

        if ((int) $building->floors_count !== $floorsBefore) {
            $changes['old']['floors_count'] = $floorsBefore;
            $changes['new']['floors_count'] = (int) $building->floors_count;
        }

        OperationLog::record('updated', self::LOG, $building, sprintf('عُدّل العقار **%s**', $building->name_ar), $changes);

        return $this->ok($request, 'تم تحديث بيانات العقار', route('realestate.buildings.show', $building->id));
    }

    public function destroy(Request $request, $id)
    {
        $building = $this->findScoped(Building::class, $id);
        $unitIds = $building->units()->pluck('id');

        // مبنىً عليه عقودٌ تاريخٌ ماليّ — يُعطَّل ولا يُحذف. والعروض والصيانة المفتوحة تُغلق أولاً.
        $blocker = match (true) {
            $building->contracts()->exists() || Unit::whereIn('id', $unitIds)->whereHas('contracts')->exists() => 'على هذا العقار عقودٌ مسجّلة؛ غيّر حالته إلى «غير نشط» بدل حذفه.',
            ($n = Listing::where(fn ($q) => $q->where('building_id', $building->id)->orWhereIn('unit_id', $unitIds))
                ->whereNotIn('status', array_merge(V::LISTING_FINAL_STATUSES, ['expired']))->count()) > 0 => sprintf('على العقار أو وحداته %d عرضاً لم يُغلق؛ أغلق العروض أولاً ثم احذف.', $n),
            ($m = MaintenanceRequest::where(fn ($q) => $q->where('building_id', $building->id)->orWhereIn('unit_id', $unitIds))
                ->whereNotIn('status', ['completed', 'cancelled'])->count()) > 0 => sprintf('على العقار أو وحداته %d طلب صيانة مفتوحاً؛ أكملها أو ألغها أولاً ثم احذف.', $m),
            default => null,
        };

        if ($blocker) {
            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $blocker], 422) : back()->with('error', $blocker);
        }

        DB::transaction(function () use ($building) {
            $building->units()->delete();
            $building->delete();
        });

        OperationLog::record('deleted', self::LOG, $building->id, sprintf('حُذف العقار **%s** (%s)', $building->name_ar, $building->code));

        return $this->ok($request, 'تم حذف العقار', route('realestate.buildings.index'));
    }

    public function storeFloor(Request $request, $id)
    {
        $building = $this->findScoped(Building::class, $id);
        $data = $request->validate(
            ['number' => 'required|integer|min:-5|max:200', 'name' => 'nullable|string|max:100'],
            [], ['number' => 'رقم الطابق', 'name' => 'اسم الطابق']
        );

        $error = match (true) {
            ! $building->hasFloors() => 'الأرض لا طوابق لها.',
            $building->floors()->where('number', $data['number'])->exists() => 'رقم الطابق موجود في هذا المبنى.',
            default => null,
        };

        if ($error) {
            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $error], 422) : back()->with('error', $error);
        }

        $floor = $building->floors()->create($data);
        $building->syncFloors();

        OperationLog::record('updated', self::LOG, $building, sprintf('أُضيف «%s» إلى العقار **%s**', $floor->label, $building->name_ar));

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'أُضيف الطابق', 'id' => $floor->id, 'label' => $floor->label, 'number' => $floor->number])
            : back()->with('success', 'أُضيف الطابق');
    }

    public function destroyFloor(Request $request, $id, $floorId)
    {
        $building = $this->findScoped(Building::class, $id);
        $floor = $building->floors()->findOrFail($floorId);

        if (($count = $floor->units()->count()) > 0) {
            $message = sprintf('في «%s» %d وحدة مسجّلة — انقلها إلى طابقٍ آخر أولاً.', $floor->label, $count);

            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        $floor->delete();
        $building->syncFloors();

        return $request->expectsJson() ? response()->json(['success' => true, 'message' => 'حُذف الطابق']) : back()->with('success', 'حُذف الطابق');
    }

    // ═══════ المجمعات: حاويةٌ فوق المباني ═══════

    public function complexes()
    {
        return view('rentalmanagement::realestate.buildings.complexes', [
            'complexes' => $this->scoped(Complex::query())->withCount('buildings')->with('creator:id,name')->orderBy('name_ar')->get(),
        ]);
    }

    public function storeComplex(Request $request)
    {
        $data = $this->complexData($request);

        $complex = DB::transaction(fn () => Complex::create($data + ['code' => $this->nextComplexCode()]));

        OperationLog::record('created', self::COMPLEX_LOG, $complex, sprintf('أُضيف المجمع **%s** (%s)', $complex->name_ar, $complex->code));

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'أُضيف المجمع', 'id' => $complex->id, 'name' => $complex->name_ar, 'code' => $complex->code])
            : back()->with('success', 'أُضيف المجمع');
    }

    public function updateComplex(Request $request, $complexId)
    {
        $complex = $this->findScoped(Complex::class, $complexId);

        $changes = OperationLog::diff($complex->fill($this->complexData($request, $complex)), ['name_ar', 'city', 'usage_type']);
        $complex->save();

        OperationLog::record('updated', self::COMPLEX_LOG, $complex, sprintf('عُدّل المجمع **%s**', $complex->name_ar), $changes);

        return $this->ok($request, 'تم تحديث المجمع');
    }

    public function destroyComplex(Request $request, $complexId)
    {
        $complex = $this->findScoped(Complex::class, $complexId);

        // يُحذف الفارغ فقط: حذفُه وفيه مبانٍ كان يفكّ ربطها بصمت
        if (($count = $complex->buildings()->count()) > 0) {
            $message = sprintf('في المجمع %d مبنى؛ انقلها أو افصلها عنه أولاً.', $count);

            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        $complex->delete();

        OperationLog::record('deleted', self::COMPLEX_LOG, $complex->id, sprintf('حُذف المجمع **%s** (%s)', $complex->name_ar, $complex->code));

        return $this->ok($request, 'تم حذف المجمع');
    }

    private function complexData(Request $request, ?Complex $complex = null): array
    {
        $request->merge(['name_ar' => trim((string) $request->input('name_ar'))]);

        return $request->validate([
            'name_ar' => ['required', 'string', 'max:191', Rule::unique('re_complexes', 'name_ar')->whereNull('deleted_at')->ignore($complex?->id)],
            'city' => 'nullable|string|max:100',
            'usage_type' => 'nullable|'.V::in(V::USAGE_TYPES),
        ], ['name_ar.unique' => 'يوجد مجمع بهذا الاسم.'], ['name_ar' => 'اسم المجمع', 'city' => 'المدينة', 'usage_type' => 'الاستخدام']);
    }

    /** رقم المجمع من تسلسله في إعدادات الترقيم؛ وقبل تسجيل المفتاح يبقى المخطط القديم. */
    private function nextComplexCode(): string
    {
        if (class_exists(DocumentSections::class) && DocumentSections::exists('re-complex')) {
            return next_document_number('re-complex');
        }

        $next = (int) Complex::withTrashed()->withoutGlobalScopes()->max('id') + 1;

        do {
            $code = 'CPX-'.str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
        } while (Complex::withTrashed()->withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }

    /** مجمعات نطاقي (كالمباني) — مع المجمع المرتبط حالياً ولو خرج عن النطاق. */
    private function complexList(?int $include = null)
    {
        return $this->scoped(Complex::query())->when($include, fn ($q) => $q->orWhere('id', $include))->orderBy('name_ar')->get(['id', 'name_ar', 'code']);
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: ?int} بيانات المبنى، بيانات وحدته التلقائية، عدد الطوابق المطلوب (null = لا تغيير)
     */
    private function validated(Request $request, ?Building $building = null): array
    {
        $type = (string) $request->input('entity_type');
        $single = V::isSingleUnit($type);
        $isLand = $type === 'land';

        // ملكية المبنى: طلبٌ بلا الحقل (شاشة قديمة) يُشتقّ من وجود المالك
        $ownership = $request->input('ownership') ?: ($request->filled('owner_id') ? 'managed' : 'own');
        $request->merge(['ownership' => $ownership]);

        // الموقع اختياري للأرض، ولمن اختار «تحديد الموقع لاحقاً»
        $locationRule = $isLand || $request->boolean('locate_later') ? 'nullable' : 'required';

        $data = $request->validate([
            'complex_id' => ['nullable', Rule::exists('re_complexes', 'id')->whereNull('deleted_at')],
            'name_ar' => 'required|string|max:191',
            'name_en' => 'nullable|string|max:191',
            'entity_type' => 'required|'.V::in(V::ENTITY_TYPES),
            'usage_type' => 'required|'.V::in($single ? V::UNIT_USAGE_TYPES : V::USAGE_TYPES),
            'ownership' => 'required|'.V::in(V::OWNERSHIP),
            'owner_id' => [$ownership === 'managed' ? 'required' : 'nullable', 'exists:re_owners,id'],
            'manager_user_id' => 'nullable|exists:users,id',
            'warehouse_mode' => 'nullable|in:none,new,existing',
            'store_house_id' => 'nullable|required_if:warehouse_mode,existing|exists:store_houses,id',
            'default_treasury_account_id' => 'nullable|exists:accounts,id',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'latitude' => $locationRule.'|numeric|between:-90,90',
            'longitude' => $locationRule.'|numeric|between:-180,180',
            'floors_count' => 'nullable|integer|min:0|max:200',
            'expected_units_count' => 'nullable|integer|min:0|max:100000',
            'total_area' => 'nullable|numeric|min:0',
            'construction_year' => 'nullable|integer|min:1800|max:'.(date('Y') + 5),
            'construction_year_hijri' => 'nullable|integer|min:1200|max:'.(self::hijriYear() + 5),
            'status' => 'required|'.V::in(V::BUILDING_STATUSES),
            'description' => 'nullable|string|max:5000',
            'plan_no' => 'nullable|string|max:100',
            'plot_no' => 'nullable|string|max:100',
            'deed_no' => 'nullable|string|max:100',
            'deed_date' => 'nullable|date',
            'street_width' => 'nullable|numeric|min:0|max:1000',
            'boundaries' => 'nullable|string|max:2000',
            'building_permit_no' => 'nullable|string|max:100',
            'municipal_license_no' => 'nullable|string|max:100',
            // وحدة العقار وحيد الوحدة تُحرَّر من نموذج العقار نفسه
            'unit' => 'nullable|array',
            'unit.unit_type' => [$type === 'standalone' ? 'required' : 'nullable', Rule::in(V::STANDALONE_UNIT_TYPES)],
            'unit.area_sqm' => 'nullable|numeric|min:0',
            'unit.base_rent' => 'nullable|numeric|min:0',
            'unit.rent_period' => 'nullable|'.V::in(V::RENT_PERIODS),
            'unit.bedrooms' => 'nullable|integer|min:0|max:50',
            'unit.bathrooms' => 'nullable|integer|min:0|max:50',
            'unit.living_rooms' => 'nullable|integer|min:0|max:50',
            'unit.kitchens' => 'nullable|integer|min:0|max:20',
            'unit.furnished_status' => 'nullable|'.V::in(V::FURNISHED),
            'unit.frontage_m' => 'nullable|numeric|min:0|max:100000',
            'unit.ceiling_height_m' => 'nullable|numeric|min:0|max:1000',
            'unit.openings_count' => 'nullable|integer|min:0|max:1000',
            'unit.permitted_activity' => 'nullable|string|max:255',
        ], [
            'owner_id.required' => 'اختر المالك، أو اجعل ملكية المبنى «مملوك للمنشأة».',
            'usage_type.in' => $single ? 'العقار وحيد الوحدة يكون سكنياً أو تجارياً، لا مختلطاً.' : 'قيمة الاستخدام غير صحيحة.',
            'latitude.required' => 'حدّد موقع العقار على الخريطة، أو اختر «تحديد الموقع لاحقاً».',
            'longitude.required' => 'حدّد موقع العقار على الخريطة، أو اختر «تحديد الموقع لاحقاً».',
        ], self::attributes());

        $unitData = $single ? array_filter($data['unit'] ?? [], fn ($v) => $v !== null && $v !== '') : [];
        unset($data['unit']);

        $this->guardTypeAndUsage($building, $data, $single);
        $this->guardDuplicate($request, $building, $data);

        if ($ownership === 'own') {
            $data['owner_id'] = null;
        }

        if (! self::hasOwnershipColumn()) {
            unset($data['ownership']);
        }

        // «لا» تفكّ الربط، و«مستودع جديد» يُنشأ بعد حفظ المبنى (applyWarehouseChoice)
        if ($request->has('warehouse_mode') && $request->input('warehouse_mode') !== 'existing') {
            $data['store_house_id'] = null;
        }

        unset($data['warehouse_mode']);

        if ($isLand) {
            // الأرض: لا طوابق ولا سنة بناء ولا مواصفات داخلية
            $data['construction_year'] = $data['construction_year_hijri'] = null;
            $unitData = array_diff_key($unitData, array_flip(['bedrooms', 'bathrooms', 'living_rooms', 'kitchens', 'furnished_status']))
                + ['bedrooms' => 0, 'bathrooms' => 0, 'living_rooms' => 0, 'kitchens' => 0];
        }

        // أُدخل تقويمٌ واحد؟ يُشتقّ الآخر — فالتقارير تفرز بالميلادي والمستندات تُطبع بالهجري
        if (! empty($data['construction_year']) && empty($data['construction_year_hijri'])) {
            $data['construction_year_hijri'] = (int) round(($data['construction_year'] - 622) * 33 / 32);
        } elseif (! empty($data['construction_year_hijri']) && empty($data['construction_year'])) {
            $data['construction_year'] = (int) round($data['construction_year_hijri'] * 32 / 33 + 622);
        }

        // «عدد الطوابق» يُكتب عبر syncFloors مع صفوفه، لا مباشرةً. غائبٌ عن طلب التعديل = لا تغيير
        $floors = $isLand ? 0 : ($request->has('floors_count') || ! $building ? (int) ($data['floors_count'] ?? 0) : null);
        unset($data['floors_count']);

        if ($single) {
            $data['expected_units_count'] = 1;

            if (! empty($data['total_area']) && empty($unitData['area_sqm']) && ! $building) {
                $unitData['area_sqm'] = $data['total_area'];
            }
        } else {
            $data['expected_units_count'] = (int) ($data['expected_units_count'] ?? 0);
        }

        return [$data, $unitData, $floors];
    }

    /** قواعد المواصفة 6 و7 و9: الاستخدام والنوع لا يتغيّران بما يخالف الوحدات القائمة. */
    private function guardTypeAndUsage(?Building $building, array $data, bool $single): void
    {
        if (! $building) {
            return;
        }

        $units = $building->units()->get(['id', 'unit_no', 'usage_type', 'is_auto']);

        // 9) عمارة فيها أكثر من وحدة لا تصير فيلا أو أرضاً أو منشأة مستقلة
        if ($single && $units->count() > 1) {
            throw ValidationException::withMessages(['entity_type' => sprintf(
                'لا يتحوّل العقار إلى «%s» وفيه %d وحدات؛ هذا النوع وحدتُه واحدة.', V::label(V::ENTITY_TYPES, $data['entity_type']), $units->count()
            )]);
        }

        if ($data['usage_type'] === 'mixed' || $data['usage_type'] === $building->getOriginal('usage_type')) {
            return;
        }

        if ($single) {
            // 7) وحدة العقار تتبع استخدامه، وعقدها الساري يمنع تغييره
            $unit = $units->first();

            if ($unit && $unit->usage_type && $unit->usage_type !== $data['usage_type'] && Unit::find($unit->id)->activeContract()->exists()) {
                throw ValidationException::withMessages(['usage_type' => 'على العقار عقدٌ سارٍ؛ لا يتغيّر استخدامه قبل إنهاء العقد.']);
            }

            return;
        }

        // 6) مختلط ← سكني يُرفض وفيه وحدات تجارية، والعكس
        $conflicting = $units->filter(fn ($unit) => $unit->usage_type && $unit->usage_type !== $data['usage_type']);

        if ($conflicting->isNotEmpty()) {
            throw ValidationException::withMessages(['usage_type' => sprintf(
                'لا يصير استخدام المبنى «%s» وفيه %d وحدة %s (%s). غيّر استخدام هذه الوحدات أولاً أو اجعل المبنى «مختلط».',
                V::label(V::USAGE_TYPES, $data['usage_type']), $conflicting->count(),
                $data['usage_type'] === 'residential' ? 'تجارية' : 'سكنية',
                $conflicting->take(5)->pluck('unit_no')->implode('، ').($conflicting->count() > 5 ? '…' : '')
            )]);
        }
    }

    /** مبنى بالاسم والإحداثيات نفسها: تحذيرٌ يُتجاوز بتأكيدٍ صريح (`confirm_duplicate`). */
    private function guardDuplicate(Request $request, ?Building $building, array $data): void
    {
        if ($request->boolean('confirm_duplicate')) {
            return;
        }

        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;

        $twin = $this->scoped(Building::query())->where('name_ar', $data['name_ar'])
            ->when($building, fn ($q) => $q->where('id', '!=', $building->id))
            ->when($lat !== null && $lng !== null,
                fn ($q) => $q->whereRaw('ROUND(latitude, 5) = ROUND(?, 5) AND ROUND(longitude, 5) = ROUND(?, 5)', [$lat, $lng]),
                fn ($q) => $q->whereNull('latitude'))
            ->first(['id', 'code']);

        if ($twin) {
            throw ValidationException::withMessages(['confirm_duplicate' => sprintf(
                'تنبيه: يوجد عقار بالاسم والموقع نفسيهما (%s). إن كان عقاراً آخر فعلاً فاضغط «حفظ» مرةً ثانية للتأكيد.', $twin->code
            )]);
        }
    }

    private static function hasOwnershipColumn(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('re_buildings', 'ownership');
    }

    /** أسماء الحقول بالعربية لرسائل التحقق. */
    private static function attributes(): array
    {
        return [
            'complex_id' => 'المجمع', 'name_ar' => 'الاسم بالعربية', 'name_en' => 'الاسم بالإنجليزية', 'entity_type' => 'نوع العقار',
            'usage_type' => 'الاستخدام', 'ownership' => 'ملكية المبنى', 'owner_id' => 'المالك', 'manager_user_id' => 'مدير العقار',
            'warehouse_mode' => 'خيار المستودع', 'store_house_id' => 'مستودع المبنى', 'default_treasury_account_id' => 'الخزينة الافتراضية للمبنى',
            'country' => 'الدولة', 'city' => 'المدينة', 'district' => 'الحي', 'address' => 'العنوان',
            'latitude' => 'خط العرض', 'longitude' => 'خط الطول', 'floors_count' => 'عدد الطوابق',
            'expected_units_count' => 'عدد الوحدات المتوقع', 'total_area' => 'المساحة الكلية',
            'construction_year' => 'سنة البناء (ميلادي)', 'construction_year_hijri' => 'سنة البناء (هجري)',
            'status' => 'الحالة', 'description' => 'الوصف', 'plan_no' => 'رقم المخطط', 'plot_no' => 'رقم القطعة',
            'deed_no' => 'رقم الصك', 'deed_date' => 'تاريخ الصك', 'street_width' => 'عرض الشارع', 'boundaries' => 'الحدود والأطوال',
            'building_permit_no' => 'رقم رخصة البناء', 'municipal_license_no' => 'رقم رخصة البلدية',
            'unit.unit_type' => 'نوع المنشأة', 'unit.area_sqm' => 'مساحة الوحدة', 'unit.base_rent' => 'السعر الإرشادي',
            'unit.rent_period' => 'دورية السعر', 'unit.bedrooms' => 'غرف النوم', 'unit.bathrooms' => 'دورات المياه',
            'unit.living_rooms' => 'الصالات', 'unit.kitchens' => 'المطابخ', 'unit.furnished_status' => 'التأثيث',
            'unit.frontage_m' => 'الواجهة (م)', 'unit.ceiling_height_m' => 'ارتفاع السقف (م)',
            'unit.openings_count' => 'عدد الفتحات', 'unit.permitted_activity' => 'النشاط المسموح',
            'images' => 'صور العقار', 'attachments' => 'المستندات',
        ];
    }

    /** «نعم، أنشئ مستودعاً»: مستودعٌ باسم المبنى في قسم المخزون، بحسابه المحاسبي. */
    private function applyWarehouseChoice(Request $request, Building $building): void
    {
        if ($request->input('warehouse_mode') !== 'new') {
            return;
        }

        if (! hasPermission('stock.warehouses.create')) {
            throw ValidationException::withMessages(['warehouse_mode' => 'لا تملك صلاحية إنشاء مستودع في قسم المخزون.']);
        }

        $storeHouse = app(PropertyAccountService::class)->createStoreHouse($building);
        $building->forceFill(['store_house_id' => $storeHouse->id])->saveQuietly();
    }

    /** السنة الهجرية الحالية تقريباً — حدٌّ أعلى للتحقّق لا أكثر. */
    private static function hijriYear(): int
    {
        return (int) round(((int) date('Y') - 622) * 33 / 32);
    }

    private function storeHouses()
    {
        return StoreHouse::query()->where('status', 'active')->select('id', 'name')->orderBy('name')->get();
    }

    /**
     * كل خزائن المنشأة ذات حسابٍ محاسبي — إسنادها للمبنى إعدادٌ إداري لا
     * علاقة له بمن يملك صلاحية الإيداع فيها (بخلاف نموذج تسجيل الدفعة).
     * `value` هو accounts.id كما في treasury_account_id بجدول re_payments.
     */
    private function treasuries()
    {
        return Treasury::query()->whereNotNull('account_id')->orderBy('name')->get()
            ->map(fn (Treasury $t) => (object) ['id' => (int) $t->account_id, 'name' => $t->name]);
    }
}
