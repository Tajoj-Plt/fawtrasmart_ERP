<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Support\DocumentAttachments;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\CustomerRelationship;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Models\Viewing;
use Modules\RentalManagement\Support\RealEstateSettings;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class ListingsController extends RealEstateController
{
    public const LOG = 're_listing_log';

    public function __construct()
    {
        $this->middleware('permission.role:realestate.listings.view', ['only' => ['index', 'requests']]);
        $this->middleware('permission.role:realestate.listings.show', ['only' => ['show']]);
        $this->middleware('permission.role:realestate.listings.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:realestate.listings.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:realestate.listings.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:realestate.listings.publish', ['only' => ['status']]);
        $this->middleware('permission.role:realestate.listings.viewings', ['only' => ['storeViewing', 'updateViewing']]);
    }

    public function index(Request $request)
    {
        // العروض وحدها — طلبات العملاء لها شاشتها (`requests`)
        $query = $this->scoped(Listing::query())->where('listing_kind', 'offer')->with(['unit.building', 'building', 'agent'])->withCount('viewings')->latest('id');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('code', 'like', $term));
        });

        foreach (['listing_type', 'status', 'assigned_agent_id', 'unit_id'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        $query->when($request->filled('building_id'), fn ($q) => $q->where(fn ($w) => $w
            ->where('building_id', $request->building_id)
            ->orWhereHas('unit', fn ($u) => $u->where('building_id', $request->building_id))));

        $listings = $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.listings.partials.table', compact('listings'))->render();
        }

        $scope = $this->scoped(Listing::query())->where('listing_kind', 'offer');
        $offers = clone $scope;

        return view('rentalmanagement::realestate.listings.index', [
            'listings' => $listings,
            'buildings' => $this->buildings(),
            'users' => $this->users(),
            'stats' => [
                'active' => (clone $offers)->where('status', 'active')->count(),
                'rent' => (clone $offers)->where('status', 'active')->where('listing_type', 'rent')->count(),
                'sale' => (clone $offers)->where('status', 'active')->where('listing_type', 'sale')->count(),
                'done' => (clone $scope)->whereIn('status', ['rented', 'purchased'])->count(),
                'inquiries' => (int) (clone $scope)->sum('inquiries_count'),
                'viewings' => Viewing::where('kind', 'viewing')->whereIn('listing_id', (clone $scope)->select('id'))->count(),
                'converted' => (clone $scope)->whereHas('contracts')->count(),
            ],
        ]);
    }

    /**
     * طلبات العملاء: عميلٌ يبحث عن شقة / أرض / محلّ بميزانيةٍ ومواصفات. البيانات
     * هي سجلّات `listing_kind = request` نفسها، بشاشتها وألفاظها هي.
     */
    public function requests(Request $request)
    {
        $query = $this->scoped(Listing::query())->where('listing_kind', 'request')->with(['client', 'agent'])->latest('id');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('code', 'like', $term)
                ->orWhere('contact_name', 'like', $term)->orWhere('contact_phone', 'like', $term)
                ->orWhere('city', 'like', $term)->orWhere('district', 'like', $term)
                ->orWhereHas('client', fn ($c) => $c->where('trade_name', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('code', 'like', $term)));
        });

        foreach (['listing_type', 'property_type', 'assigned_agent_id', 'client_id'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        // «تمت تلبيته» قيمتان مخزَّنتان (إيجار/شراء) بتسميةٍ واحدة
        match ($request->input('status')) {
            null, '' => null,
            'open' => $query->whereIn('status', Listing::REQUEST_OPEN),
            'fulfilled' => $query->whereIn('status', ['rented', 'purchased']),
            default => $query->where('status', $request->input('status')),
        };

        $requests = $query->paginate($this->perPage($request));

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.listings.partials.requests-table', compact('requests'))->render();
        }

        $scope = $this->scoped(Listing::query())->where('listing_kind', 'request');

        return view('rentalmanagement::realestate.listings.requests', [
            'requests' => $requests,
            'users' => $this->users(),
            'stats' => [
                'open' => (clone $scope)->whereIn('status', Listing::REQUEST_OPEN)->count(),
                'follow' => (clone $scope)->where('status', 'paused')->count(),
                'rent' => (clone $scope)->whereIn('status', Listing::REQUEST_OPEN)->where('listing_type', 'rent')->count(),
                'sale' => (clone $scope)->whereIn('status', Listing::REQUEST_OPEN)->where('listing_type', 'sale')->count(),
                'fulfilled' => (clone $scope)->whereIn('status', ['rented', 'purchased'])->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $days = (int) RealEstateSettings::get('listing_default_days', 90);

        if ($request->input('kind') === 'request') {
            return view('rentalmanagement::realestate.listings.request-form', [
                'listing' => new Listing([
                    'listing_kind' => 'request', 'listing_type' => 'rent', 'status' => 'draft', 'rent_period' => 'yearly',
                    'client_id' => $request->input('client_id'), 'expiry_date' => now()->addDays($days),
                ]),
                'code' => peek_document_number('re-listing'),
                'users' => $this->users(),
            ]);
        }

        return view('rentalmanagement::realestate.listings.form', [
            'listing' => new Listing([
                'unit_id' => $request->input('unit_id'), 'listing_kind' => $request->input('kind') === 'request' ? 'request' : 'offer', 'listing_type' => 'rent', 'status' => 'draft',
                'rent_period' => 'yearly', 'asking_price' => 0, 'publish_date' => now(), 'expiry_date' => now()->addDays($days),
            ]),
            'code' => peek_document_number('re-listing'),
            'units' => $this->units(),
            'buildings' => $this->buildings(),
            'users' => $this->users(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $listing = new Listing($data);
        $listing->code = next_document_number('re-listing');
        $listing->images = $this->withUnitImages($request, $listing, $this->storeFiles($request, 'images', 'listings'));
        $listing->status = 'draft';
        $listing->save();

        OperationLog::record('created', self::LOG, $listing, sprintf('أُنشئ %s **%s** — %s', $listing->kind_meta['label'], $listing->code, $listing->title));

        $this->linkRequester($listing);

        $message = $listing->is_request ? 'تم تسجيل الطلب — العروض المطابقة في صفحته' : 'تم إنشاء العرض كمسودة — فعّله حين يكتمل';

        // عرضٌ قائم آخر على الوحدة نفسها: تحذيرٌ لا منع (قد يُعرض للإيجار والبيع معاً)
        if ($duplicate = $this->duplicateOffer($listing)) {
            $message .= sprintf(' — تنبيه: على هذه الوحدة عرضٌ قائم آخر (%s).', $duplicate->code);
        }

        return $this->ok($request, $message, route('realestate.listings.show', $listing->id));
    }

    public function show($id)
    {
        $listing = $this->findScoped(Listing::class, $id, ['unit.building', 'unit.amenities', 'building', 'agent', 'client', 'contracts.tenant']);

        // مشاهدةٌ واحدة لكلّ مستخدمٍ في الجلسة — فلا يضخّم التحديثُ المتكرّر العدّاد
        if (! session()->has('re_listing_viewed.'.$listing->id)) {
            $listing->increment('views_count');
            session()->put('re_listing_viewed.'.$listing->id, true);
        }

        if ($listing->is_request) {
            return view('rentalmanagement::realestate.listings.request-show', [
                'listing' => $listing,
                'matches' => $this->matchingOffers($listing),
                'viewings' => $listing->viewings()->with('client', 'agent')->latest('id')->get(),
                'users' => $this->users(),
            ]);
        }

        return view('rentalmanagement::realestate.listings.show', [
            'listing' => $listing,
            'viewings' => $listing->viewings()->with('client', 'agent')->latest('id')->get(),
            'users' => $this->users(),
        ]);
    }

    public function edit($id)
    {
        $listing = $this->findScoped(Listing::class, $id);

        if ($listing->is_request) {
            return view('rentalmanagement::realestate.listings.request-form', ['listing' => $listing, 'code' => null, 'users' => $this->users()]);
        }

        return view('rentalmanagement::realestate.listings.form', [
            'listing' => $listing,
            'code' => null,
            'units' => $this->units(),
            'buildings' => $this->buildings(),
            'users' => $this->users(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $listing = $this->findScoped(Listing::class, $id);
        // النوع (عرض/طلب) لا يتبدّل بالتعديل: لكلٍّ شاشته وحقوله
        $request->merge(['listing_kind' => $listing->listing_kind]);
        $data = $this->validated($request);

        $listing->fill($data);

        // تغيير النوع قد يُبطل الحالة القائمة: عرضُ بيعٍ لا يبقى «تم الإيجار»
        if (! isset($listing->allowedStatuses()[$listing->status])) {
            throw ValidationException::withMessages(['listing_type' => sprintf(
                'حالة %s الحالية «%s» لا تصحّ لهذا النوع — غيّر الحالة أولاً.', $listing->noun, V::label(V::LISTING_STATUSES, $listing->status))]);
        }

        /*
            `OperationLog::diff()` تقارن القيم نصّياً، و`features` مصفوفة (cast)
            — فتمريرها يرمي «Array to string conversion» ويُسقط الحفظ كلّه.
            الحقول المفردة تُقارَن هناك، والمصفوفة تُقارَن هنا بصيغتها النصّية.
        */
        $changes = OperationLog::diff($listing, array_values(array_diff(array_keys($data), ['features'])));
        $before = implode('، ', (array) $listing->getOriginal('features'));
        $after = implode('، ', (array) $listing->features);

        if ($before !== $after) {
            $changes['old']['features'] = $before;
            $changes['new']['features'] = $after;
        }

        $listing->images = $this->withUnitImages($request, $listing, $this->storeFiles($request, 'images', 'listings', $listing->getOriginal('images')));
        $listing->save();

        OperationLog::record('updated', self::LOG, $listing, sprintf('عُدّل %s **%s**', $listing->kind_meta['label'], $listing->code), $changes);
        $this->linkRequester($listing);

        return $this->ok($request, 'تم تحديث '.$listing->noun, route('realestate.listings.show', $listing->id));
    }

    /**
     * تفعيل/إيقاف/تمّ الإيجار/تمّ الشراء/إغلاق. تفعيلُ عرضٍ لوحدةٍ غير قابلة للتسويق أو مؤجرة يُردّ
     * بتحذيرٍ أولاً، ويمضي إن أكّد المستخدم (`force`).
     */
    public function status(Request $request, $id)
    {
        $listing = $this->findScoped(Listing::class, $id, ['unit']);

        // «تمت تلبيته» للطلب تُخزَّن بقيمة غرضه (إيجار ← rented، شراء ← purchased)
        if ($request->input('status') === 'fulfilled' && $listing->is_request) {
            $request->merge(['status' => $listing->fulfilledStatus()]);
        }

        $data = $request->validate(['status' => 'required|'.V::in(V::LISTING_STATUSES)], [], ['status' => 'الحالة']);

        if (! isset($listing->allowedStatuses()[$data['status']])) {
            $message = match (true) {
                $listing->listing_kind === 'request' => 'الطلب لا يُوسَم «تم الإيجار» أو «تم الشراء» — يوسَم «تمت تلبيته» أو يُغلق.',
                $data['status'] === 'purchased' => 'عرض الإيجار لا يُوسَم «تم الشراء».',
                default => 'عرض البيع لا يُوسَم «تم الإيجار».',
            };

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message, 'errors' => ['status' => [$message]]], 422)
                : back()->with('error', $message);
        }

        if ($data['status'] === 'active' && $listing->listing_kind === 'offer' && $listing->unit && ! $request->boolean('force')) {
            $warning = match (true) {
                ! $listing->unit->is_marketable => 'الوحدة موسومة «غير قابلة للتسويق».',
                $listing->unit->occupancy_status !== 'vacant' => 'الوحدة ليست شاغرة (حالتها: '.$listing->unit->occupancy_meta['label'].').',
                default => null,
            };

            if ($warning) {
                return response()->json(['success' => false, 'confirm' => true, 'message' => $warning.' هل تريد نشر العرض رغم ذلك؟'], 409);
            }
        }

        $duplicate = $data['status'] === 'active' ? $this->duplicateOffer($listing) : null;

        $old = $listing->status;
        $listing->status = $data['status'];

        if ($data['status'] === 'active') {
            $listing->publish_date = $listing->publish_date ?: now();

            if (! $listing->expiry_date || $listing->expiry_date->isPast()) {
                $listing->expiry_date = now()->addDays((int) RealEstateSettings::get('listing_default_days', 90));
            }
        }

        $listing->save();

        OperationLog::record('status_changed', self::LOG, $listing, sprintf(
            'تغيّرت حالة '.$listing->noun.' **%s** من «%s» إلى «%s»%s', $listing->code,
            V::label($listing->is_request ? Listing::REQUEST_STATUSES : V::LISTING_STATUSES, $old), $listing->status_meta['label'],
            $request->boolean('force') ? ' (مع تجاوز تحذير حالة الوحدة)' : ''
        ));

        return $this->ok($request, 'تم تحديث حالة '.$listing->noun
            .($duplicate ? sprintf(' — تنبيه: على هذه الوحدة عرضٌ نشط آخر (%s).', $duplicate->code) : ''));
    }

    public function destroy(Request $request, $id)
    {
        $listing = $this->findScoped(Listing::class, $id);

        if ($listing->contracts()->exists()) {
            $message = 'نتج عن هذا '.$listing->noun.' عقد — أغلقه بدل حذفه.';

            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        $listing->delete();
        OperationLog::record('deleted', self::LOG, $listing->id, sprintf('حُذف %s **%s**', $listing->noun, $listing->code));

        return $this->ok($request, 'تم حذف '.$listing->noun, route($listing->is_request ? 'realestate.listings.requests' : 'realestate.listings.index'));
    }

    public function storeViewing(Request $request, $id)
    {
        $listing = $this->findScoped(Listing::class, $id);

        $data = $request->validate([
            'kind' => 'required|in:inquiry,viewing',
            'client_id' => 'nullable|exists:clients,id',
            'visitor_name' => 'nullable|required_without:client_id|string|max:191',
            'visitor_phone' => 'nullable|string|max:40',
            'agent_id' => 'nullable|exists:users,id',
            // معاينةٌ تُجدوَل لموعدٍ قادم — لا في الماضي
            'scheduled_at' => 'nullable|required_if:kind,viewing|date|after_or_equal:'.now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'notes' => 'nullable|string|max:2000',
        ], [
            'visitor_name.required_without' => 'اختر عميلاً أو اكتب اسم الزائر.',
            'scheduled_at.required_if' => 'حدّد موعد المعاينة.',
            'scheduled_at.after_or_equal' => 'موعد المعاينة لا يكون في الماضي.',
        ], [
            'kind' => 'النوع', 'client_id' => 'العميل', 'visitor_name' => 'اسم الزائر', 'visitor_phone' => 'هاتف الزائر',
            'agent_id' => 'المسوّق', 'scheduled_at' => 'الموعد', 'notes' => 'الملاحظات',
        ]);

        $this->assertClientInScope($data['client_id'] ?? null);
        $this->assertAssignable($data['agent_id'] ?? null, 'agent_id');

        Viewing::create($data + [
            'listing_id' => $listing->id,
            'unit_id' => $listing->unit_id,
            'status' => $data['kind'] === 'inquiry' ? 'done' : 'scheduled',
            'created_by' => auth()->id(),
        ]);

        // كلّ تواصلٍ على العرض استفسارٌ عنه — والمعاينة تُعدّ فوقه من جدول المعاينات
        $listing->increment('inquiries_count');

        // عميلٌ مسجَّل: تُنشأ له علاقة «محتمل» على العرض أو يُحدَّث نشاطه
        if (! empty($data['client_id'])) {
            CustomerRelationship::recordProspect((int) $data['client_id'], $listing);
        }

        OperationLog::record('viewing_logged', self::LOG, $listing, sprintf(
            'سُجّل%s على %s **%s**', $data['kind'] === 'inquiry' ? ' استفسار' : 'ت معاينة', $listing->noun, $listing->code));

        return back()->with('success', $data['kind'] === 'inquiry' ? 'سُجّل الاستفسار' : 'سُجّلت المعاينة');
    }

    public function updateViewing(Request $request, $id, $viewingId)
    {
        $listing = $this->findScoped(Listing::class, $id);
        $viewing = $listing->viewings()->findOrFail($viewingId);

        $data = $request->validate([
            'status' => 'required|'.V::in(V::VIEWING_STATUSES),
            'notes' => 'nullable|string|max:2000',
        ], [], ['status' => 'حالة المعاينة', 'notes' => 'الملاحظات']);

        // نموذجٌ لا يرسل الملاحظات لا يمحوها
        if (! $request->has('notes')) {
            unset($data['notes']);
        }

        $viewing->update($data);
        CustomerRelationship::touchActivity($viewing->client_id);

        return back()->with('success', 'تم تحديث المعاينة');
    }

    /** طلبُ عميلٍ مسجَّل يُنشئ له اهتماماً (محتمل) أو يحدّثه — فتتّصل شاشتا الطلبات والاهتمامات. */
    private function linkRequester(Listing $listing): void
    {
        if ($listing->is_request && $listing->client_id) {
            CustomerRelationship::recordProspect((int) $listing->client_id, $listing);
        }
    }

    /**
     * «عروض مطابقة» لطلب: عروضٌ نشطة داخل النطاق للغرض نفسه، ثم نوع العقار
     * والمدينة والميزانية وعدد الغرف متى حُدّدت في الطلب. استعلامٌ بسيط لا محرّك مطابقة.
     */
    private function matchingOffers(Listing $request)
    {
        return $this->scoped(Listing::query())->where('listing_kind', 'offer')->where('status', 'active')
            ->where('listing_type', $request->listing_type)
            ->when($request->property_type, fn ($q, $type) => $q->where(fn ($w) => $w->where('property_type', $type)
                ->orWhereHas('unit', fn ($u) => $u->where('unit_type', $type))))
            ->when($request->city, fn ($q, $city) => $q->where(fn ($w) => $w->where('city', 'like', '%'.$city.'%')
                ->orWhereHas('building', fn ($b) => $b->where('city', 'like', '%'.$city.'%'))
                ->orWhereHas('unit.building', fn ($b) => $b->where('city', 'like', '%'.$city.'%'))))
            ->when((float) $request->budget_max > 0, fn ($q) => $q->where('asking_price', '<=', (float) $request->budget_max))
            ->when((float) $request->budget_min > 0, fn ($q) => $q->where('asking_price', '>=', (float) $request->budget_min))
            ->when((int) $request->bedrooms > 0, fn ($q) => $q->where(fn ($w) => $w->where('bedrooms', '>=', (int) $request->bedrooms)
                ->orWhereHas('unit', fn ($u) => $u->where('bedrooms', '>=', (int) $request->bedrooms))))
            ->with(['unit.building', 'building', 'agent'])->orderBy('asking_price')->limit(30)->get();
    }

    /** عرضٌ قائم آخر (نشط أو موقوف أو مسودة) على الوحدة نفسها وللغرض نفسه — للتحذير لا للمنع. */
    private function duplicateOffer(Listing $listing): ?Listing
    {
        if ($listing->listing_kind !== 'offer' || ! $listing->unit_id) {
            return null;
        }

        return Listing::query()->where('unit_id', $listing->unit_id)->where('listing_kind', 'offer')
            ->where('listing_type', $listing->listing_type)->whereIn('status', ['active', 'paused'])
            ->where('id', '!=', $listing->id)->first();
    }

    /** «تحويل الوحدة إلى عرض» مع صورها: تُضمّ مسارات صور الوحدة إلى صور العرض بلا تكرار. */
    private function withUnitImages(Request $request, Listing $listing, ?string $images): ?string
    {
        if (! $request->boolean('copy_unit_images') || ! $listing->unit_id) {
            return $images;
        }

        $unit = $this->findScoped(Unit::class, $listing->unit_id);

        return DocumentAttachments::encode(array_values(array_unique(array_merge(
            DocumentAttachments::paths($images), $unit->filePaths('images'),
        ))));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'listing_kind' => 'required|'.V::in(V::LISTING_KINDS),
            'unit_id' => 'nullable|exists:re_units,id',
            'building_id' => 'nullable|exists:re_buildings,id',
            'contact_name' => 'nullable|string|max:191',
            'contact_phone' => 'nullable|string|max:40',
            'client_id' => 'nullable|exists:clients,id',
            'property_type' => 'nullable|'.V::in(V::UNIT_TYPES),
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'area_sqm' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0|max:100',
            'bathrooms' => 'nullable|integer|min:0|max:100',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'listing_type' => 'required|'.V::in(V::LISTING_TYPES),
            // عنوان الطلب يُشتقّ من مواصفاته إن تُرك فارغاً
            'title' => [$request->input('listing_kind') === 'request' ? 'nullable' : 'required', 'string', 'max:191'],
            'asking_price' => 'nullable|numeric|min:0',
            'rent_period' => 'nullable|required_if:listing_type,rent|'.V::in(V::RENT_PERIODS),
            'commission_type' => 'nullable|'.V::in(V::COMMISSION_TYPES),
            'commission_value' => ['nullable', 'numeric', 'min:0', $request->input('commission_type') === 'percent' ? 'max:100' : 'max:999999999'],
            'publish_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:publish_date',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string|max:10000',
            'features' => 'nullable|string|max:2000',
            'copy_unit_images' => 'nullable|boolean',
        ], [
            'budget_max.gte' => 'أعلى الميزانية يجب ألّا يقلّ عن أدناها.',
            'commission_value.max' => $request->input('commission_type') === 'percent' ? 'نسبة العمولة لا تتجاوز 100%.' : 'قيمة العمولة أكبر من المسموح.',
        ], [
            'listing_kind' => 'نوع السجلّ (عرض/طلب)', 'listing_type' => 'الغرض (إيجار/بيع)', 'unit_id' => 'الوحدة', 'building_id' => 'العقار',
            'contact_name' => $request->input('listing_kind') === 'request' ? 'اسم الزائر' : 'اسم صاحب العرض', 'contact_phone' => 'الهاتف',
            'client_id' => $request->input('listing_kind') === 'request' ? 'العميل الطالب' : 'العميل', 'property_type' => $request->input('listing_kind') === 'request' ? 'نوع العقار المطلوب' : 'نوع العقار',
            'city' => 'المدينة', 'district' => 'الحي', 'address' => 'العنوان التفصيلي', 'area_sqm' => 'المساحة', 'bedrooms' => 'غرف النوم', 'bathrooms' => 'دورات المياه',
            'title' => 'العنوان', 'budget_min' => 'أدنى الميزانية', 'budget_max' => 'أعلى الميزانية', 'asking_price' => 'السعر المطلوب',
            'rent_period' => 'دورية الإيجار', 'commission_type' => 'نوع العمولة', 'commission_value' => 'قيمة العمولة',
            'expiry_date' => 'تاريخ الانتهاء', 'publish_date' => 'تاريخ النشر', 'assigned_agent_id' => 'المسوّق', 'description' => 'الوصف', 'features' => 'المميزات',
        ]);

        // ما يُربط به يجب أن يكون داخل النطاق
        $this->assertClientInScope($data['client_id'] ?? null);
        $this->assertAssignable($data['assigned_agent_id'] ?? null, 'assigned_agent_id');

        if (! empty($data['building_id']) && empty($data['unit_id'])) {
            $this->findScoped(Building::class, $data['building_id']);
        }

        $data['asking_price'] = (float) ($data['asking_price'] ?? 0);

        if ($data['listing_kind'] === 'request') {
            // الطلب له صاحب: عميلٌ مسجَّل، أو زائرٌ باسمه وهاتفه
            if (empty($data['client_id']) && (blank($data['contact_name'] ?? null) || blank($data['contact_phone'] ?? null))) {
                throw ValidationException::withMessages(['client_id' => 'حدّد العميل الطالب من ملفّ العملاء، أو اكتب اسم الزائر وهاتفه.']);
            }

            // الطلب لا يُربط بوحدةٍ ولا يحمل عمولة عرض
            $data['unit_id'] = $data['building_id'] = null;

            if (blank($data['title'] ?? null)) {
                $data['title'] = (new Listing($data))->request_summary;
            }
        }

        // العرض يستلزم سعراً؛ الطلب سعره اختياريّ وميزانيّته اختيارية
        if ($data['listing_kind'] === 'offer' && ! $request->filled('asking_price')) {
            throw ValidationException::withMessages(['asking_price' => 'السعر المطلوب مطلوب للعرض.']);
        }

        if (! empty($data['unit_id'])) {
            $data['building_id'] = $this->findScoped(Unit::class, $data['unit_id'])->building_id;
        }

        unset($data['copy_unit_images']);

        // المميزات تُكتب سطراً لكلّ ميزة وتُحفظ قائمة
        $data['features'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|،|,/', (string) ($data['features'] ?? '')))));

        return $data;
    }
}
