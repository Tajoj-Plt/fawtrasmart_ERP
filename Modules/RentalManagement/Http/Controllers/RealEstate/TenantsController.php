<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Client;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\CustomerRelationship;
use Modules\RentalManagement\Models\PaymentSchedule;
use Modules\RentalManagement\Models\TenantProfile;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Services\TenantService;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * المستأجرون — عملاء الملفّ المركزي الذين لهم دور «مستأجر» أو عقدٌ هم
 * طرفه المستأجر. ليست قاعدة عملاء ثانية: التسجيل هنا يضيف الدور فقط،
 * وحقل «المستأجر / العميل» في العقد يقرأ من القائمة نفسها.
 */
class TenantsController extends RealEstateController
{
    public function __construct()
    {
        $this->middleware('permission.role:realestate.tenants.view', ['only' => ['index']]);
        // بطاقة المستأجر تُقرأ أيضاً من نموذج العقد (JSON) — فتكفي صلاحية إنشاء العقد
        $this->middleware('permission.role:realestate.tenants.show|realestate.tenants.view|realestate.contracts.create|realestate.contracts.edit', ['only' => ['show']]);
        $this->middleware('permission.role:realestate.tenants.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:realestate.tenants.edit', ['only' => ['edit', 'update']]);
    }

    public function index(Request $request)
    {
        $tenantIds = $this->tenantClientIds();
        $occupying = $this->scoped(Contract::query())->whereIn('status', V::CONTRACT_OCCUPYING);

        // المعرّفات نفسها مشتقّةٌ من علاقاتٍ وعقودٍ داخل النطاق — فالقائمة مقيَّدةٌ به
        $query = Client::query()->whereIn('id', $tenantIds)->orderBy('trade_name');

        $query->when($request->filled('keywords'), function ($q) use ($request) {
            $term = '%'.$request->keywords.'%';
            $q->where(fn ($c) => $c->where('trade_name', 'like', $term)->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('mobile', 'like', $term)->orWhere('code', 'like', $term)
                ->orWhereIn('id', TenantProfile::query()->where('id_number', 'like', $term)->select('client_id')));
        });

        $query->when($request->filled('building_id'), fn ($q) => $q->whereIn('id',
            $this->scoped(Contract::query())->where('building_id', $request->building_id)->pluck('tenant_client_id')));

        $query->when($request->filled('tenant_type'), fn ($q) => $q->whereIn('id',
            TenantProfile::query()->where('tenant_type', $request->tenant_type)->select('client_id')));

        $query->when($request->state === 'with_contract', fn ($q) => $q->whereIn('id', (clone $occupying)->pluck('tenant_client_id')));
        $query->when($request->state === 'without_contract', fn ($q) => $q->whereNotIn('id', (clone $occupying)->pluck('tenant_client_id')));
        $query->when($request->state === 'overdue', fn ($q) => $q->whereIn('id', $this->overdueTenantIds()));

        $tenants = $query->paginate($this->perPage($request));

        $contracts = $this->scoped(Contract::query())->whereIn('tenant_client_id', $tenants->pluck('id'))
            ->with('unit.building')->latest('start_date')->get()->groupBy('tenant_client_id');

        $profiles = TenantProfile::query()->whereIn('client_id', $tenants->pluck('id'))->get()->keyBy('client_id');

        $data = ['tenants' => $tenants, 'contracts' => $contracts, 'profiles' => $profiles, 'canSeeMoney' => hasPermission('realestate.payments.schedules'),
            'canOpenFile' => hasPermission('realestate.tenants.show') || hasPermission('realestate.relationships.view')];

        if ($request->ajax()) {
            return view('rentalmanagement::realestate.tenants.partials.table', $data)->render();
        }

        $withContract = (clone $occupying)->distinct('tenant_client_id')->count('tenant_client_id');

        return view('rentalmanagement::realestate.tenants.index', $data + [
            'buildings' => $this->buildings(),
            'stats' => [
                'total' => count($tenantIds),
                'with_contract' => $withContract,
                'without_contract' => max(0, count($tenantIds) - $withContract),
                'overdue' => count($this->overdueTenantIds()),
            ],
        ]);
    }

    /** صفحة تسجيل المستأجر — صفحةٌ كاملة لا نافذة، وتُفتح من نموذج العقد في تبويبٍ مستقل. */
    public function create()
    {
        return view('rentalmanagement::realestate.tenants.form', [
            'tenant' => null, 'profile' => null, 'users' => $this->users(), 'units' => $this->units(),
        ]);
    }

    public function edit($clientId)
    {
        $client = $this->findClient($clientId);

        return view('rentalmanagement::realestate.tenants.form', [
            'tenant' => $this->payload($client),
            'profile' => TenantProfile::query()->where('client_id', $client->id)->first(),
            'users' => collect(), 'units' => collect(),
        ]);
    }

    /**
     * يسجّل مستأجراً: «جديد» يُنشئ العميل المركزي ببياناته الشخصية، و«موجود»
     * يضيف الدور إلى عميلٍ قائم. في الحالتين تُحفظ الهوية وما يتبعها.
     */
    public function store(Request $request, TenantService $service)
    {
        $new = $request->input('mode', 'new') !== 'existing';
        $company = $request->input('tenant_type') === 'company';

        $data = $request->validate([
            'mode' => 'nullable|in:new,existing',
            'client_id' => [$new ? 'nullable' : 'required', 'exists:clients,id'],
            'trade_name' => [$new ? 'required' : 'nullable', 'string', 'max:191'],
            'mobile' => [$new ? 'required' : 'nullable', 'string', 'max:30', self::PHONE],
            'unit_id' => 'nullable|exists:re_units,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:3000',
        ] + $this->personalRules($company), $this->messages(), $this->attributeNames());

        $unit = ! empty($data['unit_id']) ? $this->findScoped(Unit::class, $data['unit_id']) : null;
        $this->assertAssignable($data['assigned_user_id'] ?? null);

        if (! $new) {
            $this->assertClientInScope($data['client_id']);
        }

        $client = DB::transaction(function () use ($request, $new, $data, $service, $unit) {
            if ($new) {
                $client = $service->createClient($data);
            } else {
                // تسجيل عميلٍ قائم يُكمل بياناته ولا يمحو منها شيئاً
                $client = Client::findOrFail($data['client_id']);
                $service->updateClient($client, collect($data)->except('trade_name')->all());
            }

            $profile = $service->saveProfile($client, $data);
            $this->saveAttachments($request, $profile);

            $relationship = CustomerRelationship::where('client_id', $client->id)->whereNull('contract_id')
                ->where('roles', 'like', '%"tenant"%')->first()
                ?? CustomerRelationship::where('client_id', $client->id)->whereNull('contract_id')->first();

            $fields = array_filter([
                'unit_id' => $unit?->id,
                'building_id' => $unit?->building_id,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($value) => $value !== null) + ['last_activity_at' => now()];

            if ($relationship) {
                $relationship->update($fields + ['roles' => array_values(array_unique(array_merge((array) $relationship->roles, ['tenant'])))]);
            } else {
                $relationship = CustomerRelationship::create($fields + [
                    'client_id' => $client->id, 'roles' => ['tenant'], 'relationship_status' => 'active',
                ]);
            }

            OperationLog::record('created', RelationshipsController::LOG, $relationship, sprintf(
                'سُجّل العميل **%s** مستأجراً', $client->display_name ?? $client->trade_name));

            return $client;
        });

        return $this->saved($request, $client, 'تم تسجيل المستأجر');
    }

    /**
     * ملفّ المستأجر: المتصفّح يُحوَّل إلى ملفّ العميل العقاري، وطلب Ajax
     * (نموذج التعديل وبطاقة العقد) يأخذ بياناته JSON.
     */
    public function show(Request $request, $clientId)
    {
        $client = $this->findClient($clientId);

        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->route('realestate.relationships.client', $client->id);
        }

        return response()->json($this->payload($client));
    }

    public function update(Request $request, TenantService $service, $clientId)
    {
        $client = $this->findClient($clientId);
        $isTenant = in_array($client->id, $this->tenantClientIds(), true);
        $company = $request->input('tenant_type') === 'company';

        $data = $request->validate([
            'trade_name' => 'required|string|max:191',
            'mobile' => ['required', 'string', 'max:30', self::PHONE],
        ] + $this->personalRules($company), $this->messages(), $this->attributeNames());

        DB::transaction(function () use ($request, $client, $data, $service, $isTenant) {
            // التعديل يكتب ما في النموذج كما هو: حقلٌ اختياريّ أُفرغ يُمحى فعلاً
            $service->updateClient($client, $data, true);
            $profile = $service->saveProfile($client, $data);
            $this->saveAttachments($request, $profile);

            // عميلٌ اختير في العقد قبل أن يُسجَّل مستأجراً: استكمال بياناته يسجّله
            if (! $isTenant) {
                CustomerRelationship::create(['client_id' => $client->id, 'roles' => ['tenant'], 'relationship_status' => 'active', 'last_activity_at' => now()]);
            }
        });

        OperationLog::record('updated', RelationshipsController::LOG, $client->id, sprintf('حُدّثت بيانات المستأجر **%s**', $client->trade_name));

        return $this->saved($request, $client->fresh(), 'تم تحديث بيانات المستأجر');
    }

    /** أرقام الهواتف: أرقام ومسافات و«+» في أوّلها — لا حروف. */
    private const PHONE = 'regex:/^\+?[0-9][0-9 ]{5,24}$/';

    private function personalRules(bool $company): array
    {
        return [
            'tenant_type' => 'nullable|'.V::in(TenantProfile::TYPES),
            // الفرد يُعرَّف بهويته، والشركة بسجلّها التجاري
            'id_number' => [$company ? 'nullable' : 'required', 'string', 'max:50'],
            'commercial_registration' => [$company ? 'required' : 'nullable', 'string', 'max:100'],
            'tax_number' => 'nullable|string|max:50',
            'representative_name' => 'nullable|string|max:150',
            'representative_title' => 'nullable|string|max:100',
            'representative_id_number' => 'nullable|string|max:50',
            'representative_phone' => ['nullable', 'string', 'max:30', self::PHONE],
            'phone' => ['nullable', 'string', 'max:30', self::PHONE],
            'email' => 'nullable|email|max:191',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|'.V::in(V::GENDERS),
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'street1' => 'nullable|string|max:255',
            'id_type' => 'nullable|'.V::in(V::ID_TYPES),
            'id_issue_date' => 'nullable|date|before_or_equal:today',
            'id_expiry_date' => 'nullable|date|after:id_issue_date',
            'id_issue_place' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'marital_status' => 'nullable|'.V::in(V::MARITAL_STATUSES),
            'family_members' => 'nullable|integer|min:0|max:100',
            'job_title' => 'nullable|string|max:150',
            'employer' => 'nullable|string|max:150',
            'employer_phone' => ['nullable', 'string', 'max:30', self::PHONE],
            'monthly_income' => 'nullable|numeric|min:0',
            'emergency_name' => 'nullable|string|max:150',
            'emergency_relation' => 'nullable|string|max:50',
            'emergency_phone' => ['nullable', 'string', 'max:30', self::PHONE],
        ];
    }

    private function messages(): array
    {
        return [
            'regex' => ':attribute يقبل الأرقام والمسافات وعلامة + في أوّله فقط.',
            'id_expiry_date.after' => 'تاريخ انتهاء الهوية يجب أن يكون بعد تاريخ إصدارها.',
        ];
    }

    private function attributeNames(): array
    {
        return [
            'mode' => 'طريقة التسجيل', 'tenant_type' => 'نوع المستأجر', 'client_id' => 'العميل', 'unit_id' => 'الوحدة',
            'assigned_user_id' => 'المسؤول عن المتابعة', 'notes' => 'الملاحظات',
            'trade_name' => 'اسم المستأجر', 'mobile' => 'رقم الجوال', 'phone' => 'الهاتف الآخر', 'email' => 'البريد الإلكتروني',
            'birth_date' => 'تاريخ الميلاد', 'gender' => 'الجنس', 'country' => 'الدولة', 'city' => 'المدينة', 'region' => 'الحي / المنطقة', 'street1' => 'العنوان',
            'id_number' => 'رقم الهوية', 'id_type' => 'نوع الهوية', 'id_issue_date' => 'تاريخ إصدار الهوية', 'id_expiry_date' => 'تاريخ انتهاء الهوية',
            'id_issue_place' => 'مكان إصدار الهوية', 'nationality' => 'الجنسية', 'marital_status' => 'الحالة الاجتماعية', 'family_members' => 'عدد أفراد الأسرة',
            'job_title' => 'المهنة', 'employer' => 'جهة العمل', 'employer_phone' => 'هاتف جهة العمل', 'monthly_income' => 'الدخل الشهري',
            'emergency_name' => 'اسم جهة الطوارئ', 'emergency_relation' => 'صلة القرابة', 'emergency_phone' => 'جوال جهة الطوارئ',
            'commercial_registration' => 'السجلّ التجاري', 'tax_number' => 'الرقم الضريبي', 'representative_name' => 'اسم ممثّل الشركة',
            'representative_title' => 'صفة الممثّل', 'representative_id_number' => 'هوية الممثّل', 'representative_phone' => 'جوال الممثّل',
            'attachments' => 'المرفقات', 'attachments.*' => 'المرفق',
        ];
    }

    private function saveAttachments(Request $request, TenantProfile $profile): void
    {
        $attachments = $this->storeFiles($request, 'attachments', 'tenants', $profile->attachments);

        if ($attachments !== $profile->attachments) {
            $profile->attachments = $attachments;
            $profile->save();
        }
    }

    /** بعد الحفظ: Ajax يأخذ بطاقة المستأجر ووجهته، والمتصفّح يُحوَّل إلى ملفّه. */
    private function saved(Request $request, Client $client, string $message)
    {
        $redirect = hasPermission('realestate.tenants.show') || hasPermission('realestate.relationships.view')
            ? route('realestate.relationships.client', $client->id)
            : route('realestate.tenants.index');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'redirect' => $redirect, 'client' => $this->payload($client)]);
        }

        return redirect($redirect)->with('success', $message);
    }

    private function payload(Client $client): array
    {
        $profile = TenantProfile::query()->where('client_id', $client->id)->first();

        return [
            'id' => $client->id,
            'label' => ($client->display_name ?? $client->trade_name).($client->code ? ' — '.$client->code : ''),
            'birth_date' => $client->birth_date ? Carbon::parse($client->birth_date)->toDateString() : null,
            'tenant_type' => $profile->tenant_type ?? 'individual',
        ] + $client->only(['trade_name', 'mobile', 'phone', 'email', 'gender', 'country', 'city', 'region', 'street1', 'tax_number', 'commercial_registration'])
          + ($profile ? [
              'id_issue_date' => $profile->id_issue_date?->toDateString(),
              'id_expiry_date' => $profile->id_expiry_date?->toDateString(),
              'id_type_label' => $profile->id_type_label,
          ] + $profile->only(['id_type', 'id_number', 'id_issue_place', 'nationality', 'marital_status', 'family_members',
              'job_title', 'employer', 'employer_phone', 'monthly_income', 'emergency_name', 'emergency_relation', 'emergency_phone',
              'representative_name', 'representative_title', 'representative_id_number', 'representative_phone']) : []);
    }

    /** @return array<int, int> */
    private function overdueTenantIds(): array
    {
        return $this->scoped(Contract::query())->whereIn('id',
            PaymentSchedule::overdue()->select('contract_id'))
            ->pluck('tenant_client_id')->unique()->values()->all();
    }
}
