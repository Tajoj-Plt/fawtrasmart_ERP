<?php

namespace Modules\RentalManagement\Services;

use App\Helpers\ClientSettingsHelper;
use App\Models\Account;
use App\Models\Client;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\TenantProfile;

/**
 * إدخال المستأجر ببياناته الشخصية: الاسم والجوال يُكتبان في ملفّ العميل
 * المركزي (مصدر واحد تقرؤه العقود والفواتير)، والهوية وما يتبعها في
 * `re_tenant_profiles`.
 */
class TenantService
{
    /** أعمدة ملفّ العميل المركزي التي يكتبها نموذج المستأجر. */
    public const CLIENT_FIELDS = ['trade_name', 'mobile', 'phone', 'email', 'birth_date', 'gender', 'country', 'city', 'region', 'street1', 'tax_number', 'commercial_registration'];

    /** أعمدة العميل التي لا تُفرَّغ أبداً (غير قابلة للفراغ في الملفّ المركزي). */
    private const CLIENT_REQUIRED = ['trade_name', 'mobile'];

    /** أعمدة ملفّ المستأجر. */
    public const PROFILE_FIELDS = [
        'tenant_type', 'id_type', 'id_number', 'id_issue_date', 'id_expiry_date', 'id_issue_place', 'nationality', 'marital_status',
        'family_members', 'job_title', 'employer', 'employer_phone', 'monthly_income',
        'emergency_name', 'emergency_relation', 'emergency_phone',
        'representative_name', 'representative_title', 'representative_id_number', 'representative_phone',
    ];

    /** حقولٌ شخصية لا معنى لها لشركة — تُفرَّغ عند حفظ مستأجرٍ شركة. */
    private const PERSONAL_ONLY = ['marital_status', 'family_members', 'job_title', 'employer', 'employer_phone', 'monthly_income'];

    /** عميلٌ مركزي جديد بمقتضيات ملفّ العملاء: الرقم والحالة والوضع وجهة الاتصال والحساب. */
    public function createClient(array $data): Client
    {
        $mobile = trim((string) ($data['mobile'] ?? ''));

        if ($mobile !== '' && ($existing = Client::where('mobile', $mobile)->orWhere('phone', $mobile)->first())) {
            throw ValidationException::withMessages(['mobile' => sprintf(
                'رقم الجوال مسجَّل للعميل «%s» — اختره من «عميل موجود» بدل إنشائه من جديد.', $existing->trade_name)]);
        }

        $client = new Client;
        $client->forceFill($this->clientAttributes($data));
        $client->status_id = ClientSettingsHelper::defaultNewClientStatusId();
        $client->registration_stage_id = ClientSettingsHelper::defaultRegistrationStageId();
        $client->branch_id = current_branch_id();
        $client->code = next_document_number('customer');
        $client->save();

        $client->contacts()->create([
            'first_name' => $client->trade_name, 'phone' => $client->phone, 'mobile' => $client->mobile,
            'email' => $client->email, 'is_primary' => true,
        ]);

        $this->ensureLedgerAccount($client);

        OperationLog::record('created', 'Client.Client', $client, 'إضافة عميل «'.$client->trade_name.'» من قسم المستأجرين',
            ['new' => $client->only(['code', 'trade_name', 'mobile', 'branch_id'])]);

        return $client;
    }

    /**
     * يحدّث أعمدة العميل ممّا أُرسل. الافتراض «إكمالٌ بلا محو»: الفارغ لا يمحو
     * القائم (تسجيل عميلٍ موجود). ومع `$clear` (نموذج التعديل) يُكتب الفارغ
     * `null` فيُمحى الحقل الاختياري فعلاً — عدا الاسم والجوال.
     */
    public function updateClient(Client $client, array $data, bool $clear = false): void
    {
        $attributes = $this->clientAttributes($data);
        $blank = fn ($value) => $value === null || $value === '';

        $attributes = $clear
            ? array_filter(array_map(fn ($value) => $blank($value) ? null : $value, $attributes),
                fn ($value, $key) => $value !== null || ! in_array($key, self::CLIENT_REQUIRED, true), ARRAY_FILTER_USE_BOTH)
            : array_filter($attributes, fn ($value) => ! $blank($value));

        if ($attributes !== []) {
            $client->forceFill($attributes)->save();
        }
    }

    public function saveProfile(Client $client, array $data): TenantProfile
    {
        $number = trim((string) ($data['id_number'] ?? ''));

        if ($number !== '') {
            $holder = TenantProfile::query()->where('id_number', $number)->where('client_id', '!=', $client->id)->with('client')->first();

            if ($holder) {
                throw ValidationException::withMessages(['id_number' => sprintf(
                    'رقم الهوية مسجَّل للمستأجر «%s».', $holder->client->trade_name ?? '#'.$holder->client_id)]);
            }
        }

        $profile = TenantProfile::query()->firstOrNew(['client_id' => $client->id]);
        $profile->fill(array_intersect_key($data, array_flip(self::PROFILE_FIELDS)));
        $profile->id_type = $profile->id_type ?: 'national_id';
        $profile->tenant_type = $profile->tenant_type ?: 'individual';

        if ($profile->tenant_type === 'company') {
            $profile->fill(array_fill_keys(self::PERSONAL_ONLY, null));
        }
        $profile->save();

        return $profile;
    }

    private function clientAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(self::CLIENT_FIELDS));
    }

    /** حساب العميل تحت «العملاء» كما يفعل ملفّ العملاء — يُتخطّى إن لم يُربط الحساب. */
    private function ensureLedgerAccount(Client $client): void
    {
        $parent = AccountingSettings::account('clients_account');

        if (! $parent || Account::where('client_id', $client->id)->exists()) {
            return;
        }

        $account = new Account;
        $account->forceFill([
            'name' => $client->trade_name,
            'client_id' => $client->id,
            'account_type' => $parent->account_type,
            'code' => app(PropertyAccountService::class)->nextCode($parent),
            'balance_type' => 'debit',
            'parent_id' => $parent->id,
            'opening_balance' => 0,
            'opening_balance_type' => $parent->balance_type,
            'is_active' => true,
        ])->save();
    }
}
