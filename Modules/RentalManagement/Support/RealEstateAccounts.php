<?php

namespace Modules\RentalManagement\Support;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Treasury;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Services\PropertyAccountService;
use Throwable;

/**
 * حسابات العقارات في دليل الحسابات — نقطةٌ واحدة تجدها أو تنشئها.
 *
 * الترتيب لكل حساب: المربوط في الإعدادات ← الحساب المسمّى باسمه من نوعه ←
 * يُنشأ تحت جذر نوعه ويُحفظ في الإعدادات. فلا يتوقّف ترحيلٌ على حسابٍ لم
 * يُنشئه أحد، ولا يتكرّر الحساب بتكرار التشغيل.
 */
class RealEstateAccounts
{
    /**
     * @var array<string, array{name:string, account_type:string, balance_type:string}>
     */
    public const POSTING = [
        'deposit_liability_account_id' => ['name' => 'تأمينات المستأجرين', 'account_type' => 'liabilities', 'balance_type' => 'credit'],
        'rent_revenue_account_id' => ['name' => 'إيرادات الإيجار', 'account_type' => 'revenues', 'balance_type' => 'credit'],
        'management_commission_account_id' => ['name' => 'إيرادات إدارة الأملاك', 'account_type' => 'revenues', 'balance_type' => 'credit'],
        'maintenance_expense_account_id' => ['name' => 'مصروفات صيانة العقارات', 'account_type' => 'expenses', 'balance_type' => 'debit'],
        'deposit_forfeit_account_id' => ['name' => 'إيرادات خصم التأمينات', 'account_type' => 'revenues', 'balance_type' => 'credit'],
        // وسيط: يُقيَّد له الشيك عند استلامه، ويُقفل بقيد الدفعة عند التحصيل أو بقيد الارتجاع
        'cheque_clearing_account_id' => ['name' => 'دفعات عقارية بشيكات تحت التحصيل', 'account_type' => 'liabilities', 'balance_type' => 'credit'],
    ];

    /** يجهّز الحسابات كلها ويربطها في الإعدادات. @return array<string, int> */
    public static function ensureAll(): array
    {
        $ids = ['owner_payables_account_id' => self::ownerPayables()->id];

        foreach (array_keys(self::POSTING) as $key) {
            $ids[$key] = self::ensure($key)->id;
        }

        return $ids;
    }

    /** «مستحقات ملاك العقار»: أبٌ دائن في الخصوم تتفرّع منه الحسابات الجارية للملّاك. */
    public static function ownerPayables(): Account
    {
        $account = app(PropertyAccountService::class)->ownerPayablesAccount();

        if ($account->type !== 'main' || $account->balance_type !== 'credit') {
            $account->forceFill(['type' => 'main', 'balance_type' => 'credit'])->save();
        }

        return $account;
    }

    public static function ensure(string $key): Account
    {
        $definition = self::POSTING[$key] ?? null;

        if (! $definition) {
            throw new \InvalidArgumentException('حساب عقاري غير معرَّف: '.$key);
        }

        $current = Account::find(RealEstateSettings::get($key));

        if ($current && ! ($problem = self::problem($key, $current))) {
            return $current;
        }

        // الحساب المربوط غير صالح للترحيل (كحسابٍ لخزينة/بنك: قُيّد التأمين فيه فبدا القيدُ تحويلاً
        // بين خزينتين) — يُستبدل بالحساب القياسي ويُسجَّل الاستبدال، ولا يبقى الربط الفاسد يُفسد كل قيد
        if ($current) {
            Log::warning('حسابٌ عقاري مربوط غير صالح — استُبدل بالقياسي', ['key' => $key, 'account' => $current->id, 'problem' => $problem]);
            OperationLog::record('repaired', 're_settings_log', null, sprintf(
                'استُبدل الحساب المربوط في «%s» (%s — %s) لأنه غير صالح للترحيل: %s',
                RealEstateSettings::DEFINITIONS[$key]['label'] ?? $key, $current->code, $current->name, $problem));
        }

        $account = Account::where('name', $definition['name'])->where('account_type', $definition['account_type'])
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->whereDoesntHave('children')->orderBy('id')->first();

        if (! $account) {
            $root = Account::whereNull('parent_id')->where('account_type', $definition['account_type'])->orderBy('code')->first();

            $account = Account::create([
                'name' => $definition['name'],
                'code' => app(PropertyAccountService::class)->nextCode($root),
                'parent_id' => $root?->id,
                'type' => 'sub',
                'account_type' => $definition['account_type'],
                'balance_type' => $definition['balance_type'],
                'balance' => 0,
                'is_active' => 1,
            ]);
        }

        RealEstateSettings::put($key, $account->id);

        return $account;
    }

    public static function id(string $key): int
    {
        return (int) self::ensure($key)->id;
    }

    /** أنواع الحسابات بالعربية — للرسائل ولوحة الفحص. */
    public const TYPE_LABELS = ['assets' => 'الأصول', 'liabilities' => 'الخصوم', 'revenues' => 'الإيرادات', 'expenses' => 'المصروفات', 'equity' => 'حقوق الملكية'];

    /** أين يُستعمل كل حساب — يظهر في لوحة الفحص ليعرف المحاسب مصيرَ كل مبلغ. */
    public const USAGE = [
        'owner_payables_account_id' => 'الحساب الأب لحسابات الملّاك الجارية: ما يخصّ كل مالك من إيجار محصَّل (دائن) وما يُحوَّل له أو يُحمَّل عليه (مدين).',
        'deposit_liability_account_id' => 'تأمين المستأجر: يُقيَّد دائناً عند قبضه (من الخزينة)، ويُقفل مديناً عند ردّه أو خصمه. التزامٌ على المنشأة للمستأجر لا خزينة.',
        'rent_revenue_account_id' => 'إيجار المباني المملوكة للمنشأة (دائن). إيجار المباني المُدارة لا يمرّ به بل يذهب لحساب المالك.',
        'management_commission_account_id' => 'عمولة إدارة الأملاك المخصومة من نصيب المالك (دائن).',
        'maintenance_expense_account_id' => 'الصيانة التي تتحمّلها المنشأة (مدين).',
        'deposit_forfeit_account_id' => 'ما يُخصم من تأمين المستأجر لصالح المنشأة عند التسوية (دائن).',
        'cheque_clearing_account_id' => 'حساب وسيط لدفعات الشيكات حتى تُحصَّل من «الشيكات المستلمة».',
    ];

    /**
     * سببُ عدم صلاحية الحساب للترحيل على هذا البند، أو null إن صلح.
     *
     * كان المربوط يُقبل كما هو دون فحص: ربطٌ قديم أو يدويّ بحسابِ بنك جعل تأمين
     * المستأجر يُقيَّد دائناً على البنك، فظهر القيد «من خزينة إلى خزينة» كأنه تحويل.
     */
    public static function problem(string $key, ?Account $account): ?string
    {
        $isOwnerParent = $key === 'owner_payables_account_id';
        $expected = $isOwnerParent ? 'liabilities' : (self::POSTING[$key]['account_type'] ?? null);

        if ($expected === null) {
            return null;
        }

        if (! $account) {
            return 'الحساب المربوط غير موجود في دليل الحسابات.';
        }

        if ($account->account_type !== $expected) {
            return sprintf('نوعه «%s» والمطلوب «%s».', self::TYPE_LABELS[$account->account_type] ?? $account->account_type, self::TYPE_LABELS[$expected] ?? $expected);
        }

        if (self::isTreasury($account->id)) {
            return 'حسابٌ لخزينة أو بنك — لا يصلح لهذا البند.';
        }

        if (! $isOwnerParent && $account->children()->exists()) {
            return 'حسابٌ رئيسي له حسابات تحته — لا يُرحَّل عليه مباشرةً.';
        }

        if (array_key_exists('is_active', $account->getAttributes()) && ! (int) $account->is_active) {
            return 'الحساب غير مفعّل.';
        }

        return null;
    }

    /**
     * فحصُ كل حسابات الترحيل: ما المربوط، وأين موقعه في الشجرة، ورصيده، وهل يصلح.
     * لا يُنشئ ولا يُعدّل شيئاً.
     *
     * @return array<int, array{key:string, label:string, usage:string, linked:bool, id:?int, code:?string, name:?string, type:?string, path:string, balance:float, problem:?string, status:string}>
     */
    public static function health(): array
    {
        $rows = [];

        foreach (['owner_payables_account_id' => RealEstateSettings::DEFINITIONS['owner_payables_account_id']['label']] + array_map(fn ($k) => RealEstateSettings::DEFINITIONS[$k]['label'] ?? $k, array_combine(array_keys(self::POSTING), array_keys(self::POSTING))) as $key => $label) {
            $id = RealEstateSettings::get($key);
            $account = $id ? Account::find($id) : null;
            $problem = $account ? self::problem($key, $account) : null;

            $path = [];
            for ($node = $account, $guard = 0; $node && $guard < 8; $guard++) {
                $path[] = $node->name;
                $node = $node->parent_id ? Account::find($node->parent_id, ['id', 'name', 'parent_id']) : null;
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'usage' => self::USAGE[$key] ?? '',
                'linked' => (bool) $account,
                'id' => $account?->id,
                'code' => $account?->code,
                'name' => $account?->name,
                'type' => $account ? (self::TYPE_LABELS[$account->account_type] ?? $account->account_type) : null,
                'path' => implode(' ← ', array_reverse($path)),
                'balance' => $account ? round((float) $account->balance, 2) : 0.0,
                'problem' => $id && ! $account ? 'الحساب المربوط محذوف من الدليل.' : $problem,
                'status' => ! $id ? 'unlinked' : ($account && ! $problem ? 'ok' : 'bad'),
            ];
        }

        return $rows;
    }

    /** حساب ضريبة المخرجات للمنشأة — حساب النظام لا حسابٌ خاص بالوحدة. */
    public static function vatPayableId(): int
    {
        $id = AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE);

        if (! $id || ! Account::whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                'accounting' => 'تعذّر الترحيل: لم يُربط حساب «ضريبة القيمة المضافة المستحقة» في الإعدادات ← الحسابات المحاسبية.',
            ]);
        }

        return (int) $id;
    }

    /* ─────────────────────────── الخزينة ─────────────────────────── */

    /** الحساب خزينةٌ أو بنك؟ أي له صفٌّ في `treasuries`. */
    public static function isTreasury($accountId): bool
    {
        return $accountId && Treasury::where('account_id', (int) $accountId)->exists();
    }

    /**
     * سببُ رفض الحساب طرفاً نقدياً، أو null. كان أيّ `accounts.id` يُقبل،
     * فقُيِّد قبضٌ على حساب إيرادات. الآن: خزينةٌ أو بنك، ومسموحٌ لي بها.
     *
     * @param  string  $action  `deposit` أو `withdraw`
     */
    public static function treasuryBlockReason($accountId, string $action): ?string
    {
        if (! $accountId) {
            return 'اختر الخزينة أو الحساب البنكي.';
        }

        if (! self::isTreasury($accountId)) {
            return 'الحساب المختار ليس خزينةً ولا حساباً بنكياً — اختر من قائمة الخزائن والبنوك.';
        }

        return $action === 'deposit'
            ? TreasuryAccessGuard::depositBlockReason((int) $accountId)
            : TreasuryAccessGuard::withdrawBlockReason((int) $accountId);
    }

    public static function assertTreasury($accountId, string $action, string $field = 'treasury_account_id'): int
    {
        if ($reason = self::treasuryBlockReason($accountId, $action)) {
            throw ValidationException::withMessages([$field => $reason]);
        }

        return (int) $accountId;
    }

    /* ─────────────────────────── الملكية ─────────────────────────── */

    public static function buildingOf(Contract $contract): ?Building
    {
        return $contract->unit?->building ?? $contract->building;
    }

    /**
     * مالك العقد **إن كان المبنى مُداراً لحسابه**؛ و null إن كان المبنى
     * مملوكاً للمنشأة أو بلا مالك — فيُقيَّد إيجاره إيراداً لا التزاماً.
     */
    public static function managedOwner(Contract $contract): ?Owner
    {
        $owner = $contract->effectiveOwner();

        return $owner && self::isManaged(self::buildingOf($contract)) ? $owner : null;
    }

    public static function isManaged(?Building $building): bool
    {
        if (! $building) {
            return true;
        }

        // بلا قيمة (سجلّ سابق للعمود): المبنى الذي لا مالك له مملوكٌ للمنشأة
        return ($building->getAttribute('ownership') ?: ($building->owner_id ? 'managed' : 'own')) !== 'own';
    }

    /* ─────────────────────────── مساعدات ─────────────────────────── */

    /** رقم مستندٍ من تسلسل النظام، وبديلٌ زمنيّ إن لم يُسجَّل القسم بعد. */
    public static function nextNumber(string $section, string $prefix): string
    {
        try {
            return next_document_number($section);
        } catch (Throwable $e) {
            Log::warning('تسلسل مستند عقاري غير مسجَّل', ['section' => $section, 'error' => $e->getMessage()]);

            return $prefix.'-'.now()->format('ymdHis').random_int(10, 99);
        }
    }

    /** سببُ منع القيد في هذا التاريخ (فترة مقفلة) أو null. */
    public static function periodBlock($date): ?string
    {
        try {
            return app(AccountingPeriodService::class)->blockReason($date ?: now());
        } catch (Throwable $e) {
            Log::warning('فحص الفترة المحاسبية أخفق في العقارات', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public static function assertPeriodOpen($date, string $field = 'date'): void
    {
        if ($block = self::periodBlock($date)) {
            throw ValidationException::withMessages([$field => $block]);
        }
    }

    public static function refreshBalances(array $accountIds): void
    {
        foreach (array_unique(array_filter($accountIds)) as $id) {
            Account::updateBalancesHierarchically($id);
        }
    }
}
