<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\CustomerRelationship;
use Modules\RentalManagement\Models\Listing;
use Modules\RentalManagement\Models\Payment;
use Modules\RentalManagement\Models\Task;
use Modules\RentalManagement\Models\TenantProfile;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Models\Viewing;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * اهتمامات العملاء — من يهتم بماذا: عميلٌ مهتمّ بوحدة أو مبنى أو عرض، ومن
 * يتابعه. عرضٌ عقاريّ فوق العملاء المركزيين: لا يُنشأ هنا عميل.
 *
 * المستأجرون الحاليون (صفوفٌ تُنشئها العقود آلياً) لا يغمرون القائمة: لهم
 * تبويبهم، والتبويب الافتراضي للاهتمامات (المحتملون وما يحتاج متابعة).
 */
class RelationshipsController extends RealEstateController
{
    public const LOG = 're_relationship_log';

    public function __construct()
    {
        $this->middleware('permission.role:realestate.relationships.view', ['only' => ['index']]);
        // ملفّ العميل العقاري هو نفسه «ملفّ المستأجر» — فتكفي صلاحية عرض المستأجر
        $this->middleware('permission.role:realestate.relationships.view|realestate.tenants.show', ['only' => ['client']]);
        $this->middleware('permission.role:realestate.relationships.create', ['only' => ['store']]);
        $this->middleware('permission.role:realestate.relationships.edit', ['only' => ['update']]);
        $this->middleware('permission.role:realestate.relationships.delete', ['only' => ['destroy']]);
    }

    /**
     * صفٌّ واحد لكلّ عميل تُدمَج فيه أدواره وعلاقاته — كان العميل يتكرّر بعدد
     * علاقاته (مستأجرٌ بثلاثة عقود يظهر ثلاث مرّات).
     */
    public function index(Request $request)
    {
        $filtered = fn () => $this->filter($this->scoped(CustomerRelationship::query()), $request);

        $relationships = $filtered()->toBase()
            ->selectRaw('client_id, MAX(id) as last_id, MAX(last_activity_at) as last_activity_at, COUNT(*) as relationships_count')
            ->groupBy('client_id')->orderByDesc('last_id')
            ->paginate($this->perPage($request));

        $groups = $filtered()->whereIn('client_id', collect($relationships->items())->pluck('client_id'))
            ->with(['client', 'unit.building', 'building', 'listing', 'contract', 'assignee'])->latest('id')->get()->groupBy('client_id');

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.relationships.partials.table', compact('relationships', 'groups'))->render();
        }

        $scope = $this->scoped(CustomerRelationship::query());

        return view('rentalmanagement::realestate.relationships.index', [
            'relationships' => $relationships,
            'groups' => $groups,
            'users' => $this->users(),
            'buildings' => $this->buildings(),
            'units' => $this->units(),
            'view' => $this->view($request),
            'stats' => [
                'clients' => $this->interests(clone $scope)->distinct('client_id')->count('client_id'),
                // مستأجرٌ بثلاثة عقود مستأجرٌ واحد
                'tenants' => (clone $scope)->where('roles', 'like', '%"tenant"%')->where('relationship_status', 'active')->distinct('client_id')->count('client_id'),
                'requests' => $this->scoped(Listing::query())->where('listing_kind', 'request')->whereIn('status', ['draft', 'active', 'paused'])->count(),
                'follow_up' => (clone $scope)->where('relationship_status', 'follow_up')->distinct('client_id')->count('client_id'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $relationship = CustomerRelationship::create($data + ['last_activity_at' => now()]);

        OperationLog::record('created', self::LOG, $relationship, sprintf(
            'أُضيف اهتمامٌ للعميل **%s**', $relationship->client->display_name ?? '#'.$relationship->client_id));

        return $this->ok($request, 'تمت إضافة اهتمام العميل');
    }

    public function update(Request $request, $id)
    {
        $relationship = $this->findScoped(CustomerRelationship::class, $id);
        $relationship->update($this->validated($request, $relationship) + ['last_activity_at' => now()]);

        OperationLog::record('updated', self::LOG, $relationship, 'عُدّل اهتمام عميل');

        return $this->ok($request, 'تم تحديث الاهتمام');
    }

    public function destroy(Request $request, $id)
    {
        $relationship = $this->findScoped(CustomerRelationship::class, $id);

        if ($relationship->contract_id) {
            $message = 'هذا الصفّ ناتجٌ عن عقد — يُغلق ولا يُحذف.';

            return $request->expectsJson() ? response()->json(['success' => false, 'message' => $message], 422) : back()->with('error', $message);
        }

        $relationship->delete();
        OperationLog::record('deleted', self::LOG, $relationship->id, 'حُذف اهتمام عميل');

        return $this->ok($request, 'تم حذف الاهتمام');
    }

    /**
     * ملفّ العميل داخل العقارات: علاقاته وعقوده ومدفوعاته ومعايناته وأنشطته.
     * يُفتح لكلّ عميلٍ داخل النطاق — ولو لم يكن له إلّا معاينةٌ أو مهمة.
     */
    public function client($clientId)
    {
        $client = $this->findClient($clientId, ['account']);

        $relationships = $this->scoped(CustomerRelationship::query())->where('client_id', $client->id)
            ->with(['unit.building', 'building', 'listing', 'contract'])->latest('id')->get();

        $contracts = $this->scoped(Contract::query())->where('tenant_client_id', $client->id)->with('unit.building')->latest('start_date')->get();

        return view('rentalmanagement::realestate.relationships.client', [
            'client' => $client,
            'profile' => TenantProfile::query()->where('client_id', $client->id)->first(),
            'relationships' => $relationships,
            'contracts' => $contracts,
            'payments' => hasPermission('realestate.payments.view')
                ? $this->scoped(Payment::query())->where('client_id', $client->id)->with('contract')->latest('payment_date')->limit(30)->get()
                : collect(),
            // المعاينة لا فرع لها ولا نطاق: تُبلَغ من عرضها المقيَّد
            'viewings' => Viewing::where('client_id', $client->id)->whereIn('listing_id', $this->scoped(Listing::query())->select('id'))
                ->with('listing', 'unit.building')->latest('id')->limit(30)->get(),
            'tasks' => $this->scoped(Task::query())->where('client_id', $client->id)->latest('id')->limit(30)->get(),
            'outstanding' => $contracts->where('tenant_client_id', $client->id)->sum(fn ($c) => $c->outstanding()),
        ]);
    }

    /** التبويب: `interests` (الافتراضي) أو `tenants` أو `all`. */
    private function view(Request $request): string
    {
        return in_array($request->input('view'), ['tenants', 'all'], true) ? $request->input('view') : 'interests';
    }

    /** الاهتمامات: كلّ ما ليس صفَّ مستأجرٍ حاليّ — ومعها ما يحتاج متابعة أيّاً كان دوره. */
    private function interests($query)
    {
        return $query->where(fn ($q) => $q->where('roles', 'not like', '%"tenant"%')->orWhere('relationship_status', 'follow_up'));
    }

    private function filter($query, Request $request)
    {
        match ($this->view($request)) {
            'tenants' => $query->where('roles', 'like', '%"tenant"%'),
            'all' => null,
            default => $this->interests($query),
        };

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->whereHas('client', fn ($c) => $c->where(fn ($w) => $w->where('trade_name', 'like', $term)->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('code', 'like', $term)));
        });

        $query->when($request->filled('role'), fn ($q) => $q->where('roles', 'like', '%"'.$request->role.'"%'));

        foreach (['relationship_status', 'assigned_user_id', 'building_id', 'client_id'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        return $query;
    }

    private function validated(Request $request, ?CustomerRelationship $current = null): array
    {
        // «مالك» ليس دوراً يُختار: الملّاك كيانٌ مستقلّ. يبقى لصفٍّ قديم يحمله أصلاً
        $roles = CustomerRelationship::SELECTABLE_ROLES + (in_array('owner', (array) $current?->roles, true)
            ? ['owner' => V::RELATIONSHIP_ROLES['owner']] : []);

        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'roles' => 'required|array|min:1',
            'roles.*' => 'distinct|'.V::in($roles),
            'building_id' => 'nullable|exists:re_buildings,id',
            'unit_id' => 'nullable|exists:re_units,id',
            'listing_id' => 'nullable|exists:re_listings,id',
            'relationship_status' => 'required|'.V::in(V::RELATIONSHIP_STATUSES),
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:3000',
        ], [
            'roles.*.in' => 'نوع الاهتمام المختار غير متاح — الملّاك يُسجَّلون من شاشة «الملّاك».',
        ], [
            'client_id' => 'العميل', 'roles' => 'نوع الاهتمام', 'roles.*' => 'نوع الاهتمام', 'relationship_status' => 'الحالة',
            'building_id' => 'العقار', 'unit_id' => 'الوحدة', 'listing_id' => 'العرض', 'assigned_user_id' => 'المسؤول عن المتابعة', 'notes' => 'الملاحظات',
        ]);

        $data['roles'] = array_values(array_unique($data['roles']));
        sort($data['roles']);

        // ما يُربط به يجب أن يكون داخل النطاق — لا يكفي أنّ المعرّف موجود
        if ((int) $data['client_id'] !== (int) $current?->client_id) {
            $this->assertClientInScope($data['client_id']);
        }

        if ((int) ($data['assigned_user_id'] ?? 0) !== (int) $current?->assigned_user_id) {
            $this->assertAssignable($data['assigned_user_id'] ?? null);
        }

        $unit = ! empty($data['unit_id']) ? $this->findScoped(Unit::class, $data['unit_id']) : null;

        if (! empty($data['building_id'])) {
            $this->findScoped(Building::class, $data['building_id']);
        }

        if ($unit && ! empty($data['building_id']) && (int) $unit->building_id !== (int) $data['building_id']) {
            throw ValidationException::withMessages(['unit_id' => 'الوحدة المختارة لا تتبع العقار المختار.']);
        }

        if ($unit) {
            $data['building_id'] = $unit->building_id;
        }

        $data += ['building_id' => null, 'unit_id' => null];

        // تكرارٌ تامّ: العميل نفسه على الوحدة/العقار نفسه بالأدوار نفسها
        $duplicate = CustomerRelationship::query()->where('client_id', $data['client_id'])
            ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
            ->get(['id', 'roles', 'unit_id', 'building_id'])
            ->first(function (CustomerRelationship $other) use ($data) {
                $roles = array_values(array_unique((array) $other->roles));
                sort($roles);

                return $roles === $data['roles']
                    && (int) $other->unit_id === (int) $data['unit_id']
                    && (int) $other->building_id === (int) $data['building_id'];
            });

        if ($duplicate) {
            throw ValidationException::withMessages(['client_id' => 'لهذا العميل اهتمامٌ مطابق مسجَّل (النوع نفسه على العقار/الوحدة نفسها) — عدّله بدل تكراره.']);
        }

        return $data;
    }
}
