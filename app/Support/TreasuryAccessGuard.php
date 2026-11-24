<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Treasury;
use App\Models\TreasuryEmployee;

/**
 * صلاحيتا الإيداع والسحب على الخزينة والحساب البنكي — نقطةُ القرار الواحدة.
 *
 * المنطقُ كان يسكن `Modules\Account\…\Concerns\GuardsTreasuryAccess` وحده،
 * فلم يكن في متناول من يصرف نقداً من خارج وحدة الحسابات. وصرفُ الرواتب
 * يخرج نقداً من خزينةٍ كما يخرجه سندُ الصرف والتحويلُ والشيك، فلزمه الحارسُ
 * نفسه لا نسخةٌ ثانية منه.
 *
 * فالقرارُ هنا، والتراتُ يبقى غلافاً يرمي `ValidationException` لمتحكّمات
 * وحدة الحسابات — على مثال `App\Support\AccountingPeriodGuard` الذي تسأله
 * وحدةُ الحسابات ووحدةُ الموارد البشرية معاً.
 *
 * والغلافُ الذي يرمي `ValidationException` هو تراتُ
 * `Modules\Account\Http\Controllers\Concerns\GuardsTreasuryAccess` —
 * يُذكر نصّاً لا بـ`@see`: استيرادُ صنفٍ من وحدةٍ داخل `app/` لأجل تعليقٍ
 * وحده يقلب اتجاه التبعية.
 */
class TreasuryAccessGuard
{
    /** أنواع الشرط كما تحفظها شاشة إنشاء الخزينة. */
    private const SCOPE_EVERYONE = 0;

    private const SCOPE_EMPLOYEE = 1;

    private const SCOPE_ROLE = 2;

    private const SCOPE_BRANCH = 3;

    /**
     * سببُ منع الإيداع في خزينة، أو `null` إن كان مسموحاً.
     *
     * @param  int|null  $accountId  حساب الخزينة كما يرد في المستند
     */
    public static function depositBlockReason($accountId): ?string
    {
        return self::blockReason($accountId, 'deposit');
    }

    /** سببُ منع السحب من خزينة، أو `null` إن كان مسموحاً. */
    public static function withdrawBlockReason($accountId): ?string
    {
        return self::blockReason($accountId, 'withdraw');
    }

    /**
     * سبب المنع، أو `null` إن كانت الحركة مسموحة.
     *
     * الخزينة التي لا شرط لها — أو التي لا يقابلها صفٌّ في `treasuries` —
     * مسموحةٌ للجميع. المنعُ يحتاج شرطاً منصوصاً، ولا يُفترض من غياب
     * البيانات: خزائن أُنشئت قبل هذه الميزة لا يجوز أن تُقفل فجأةً.
     *
     * وبغير مستخدمٍ مُصادَق لا منع: مسارُ الكرون يصرف الرواتب بلا جلسة،
     * والشرطُ إنّما يقيّد المستخدمين لا النظام.
     *
     * @param  string  $action  `deposit` أو `withdraw`
     */
    public static function blockReason($accountId, string $action): ?string
    {
        if (! $accountId) {
            return null;
        }

        $treasury = Treasury::where('account_id', $accountId)->first();

        if (! $treasury) {
            return null;
        }

        return self::rowBlockReason($treasury, $action);
    }

    /**
     * الخزائن التي يجوز لهذا المستخدم **السحب** منها — قائمةُ الاختيار في
     * كل شاشةٍ تُخرج نقداً (ردُّ مبلغ مرتجع، إشعارٌ دائن، سندُ صرف).
     *
     * كانت تلك الشاشات تعرض `Treasury::all()`: خزائنُ المنشأة كلُّها لكل
     * موظف، رغم أن شرط السحب مضبوطٌ على الخزينة نفسها (موظفاً أو دوراً أو
     * فرعاً). والقائمةُ والحفظُ يسألان الشرط نفسه الآن، فلا يُعرَض خيارٌ
     * يُرفض عند الحفظ.
     *
     * وبلا `account_id` لا تُعرَض خزينة: الحركةُ تُقيَّد على حسابها، فصفٌّ
     * بلا رابطٍ لا يصلح خياراً — انظر `Treasury::$fillable`.
     *
     * @return \Illuminate\Support\Collection<int, Treasury>
     */
    public static function withdrawable()
    {
        return self::allowed('withdraw');
    }

    /** نظيرتُها للإيداع — الخزائن التي يجوز لهذا المستخدم الإيداع فيها. */
    public static function depositable()
    {
        return self::allowed('deposit');
    }

    /**
     * الخزائن التي يسحب منها **هذا الموظف نفسه** — قائمةُ ردّ المبلغ في
     * مرتجع المبيعات والإشعار الدائن.
     *
     * `withdrawable()` وحدها تعرض كل خزينةٍ شرطُ سحبها «الجميع»، فكان
     * موظفُ المبيعات يرى خزائن زملائه (محمود، ياسر…) إلى جانب خزينته ويصرف
     * منها ردَّ مرتجعه. والقاعدةُ هنا قاعدةُ `ScopesToEmployeeHierarchy` في
     * وحدة الحسابات: الموظفُ يرى خزينتَه المربوطةَ به في `treasury_employees`
     * وحدها، ومن يملك `data_scope_all` أو ليس موظفاً أصلاً يرى القائمة كاملة.
     *
     * @return \Illuminate\Support\Collection<int, Treasury>
     */
    public static function ownWithdrawable()
    {
        $treasuries = self::withdrawable();
        $own = self::ownAccountIds();

        if ($own === null) {
            return $treasuries;
        }

        return $treasuries
            ->filter(fn (Treasury $treasury) => in_array((int) $treasury->account_id, $own, true))
            ->values();
    }

    /**
     * نظيرتا `ownWithdrawable()` للإيداع — قائمةُ «الخزينة المستخدمة» في
     * تسجيل دفعةٍ من عميل وتعديلها.
     *
     * ── محوران لا محور ──
     *
     * `depositable()` تسأل **الصلاحية** وحدها: شرطَ الإيداع المضبوط على
     * الخزينة نفسها (موظّفاً أو دوراً أو فرعاً). وتلك لا تقيّد خزينةً
     * شرطُها «الجميع» — وأكثرُ الخزائن كذلك — فيبقى الموظّفُ يرى خزائن
     * زملائه ويقبض فيها.
     *
     * وهذه تضيف **نطاق الرؤية**: الموظّفُ يودع في خزينته المعيَّنة له في
     * «خزائن الموظفين» وحدَها، ومن يملك `data_scope_all` أو ليس موظّفاً
     * أصلاً يرى القائمة كاملة. وهي قاعدةُ `ScopesToEmployeeHierarchy` نفسُها
     * التي يعمل بها السحبُ في المرتجع والإشعار الدائن، فلا يفترق البابان.
     *
     * @return \Illuminate\Support\Collection<int, Treasury>
     */
    public static function ownDepositable()
    {
        $treasuries = self::depositable();
        $own = self::ownAccountIds();

        if ($own === null) {
            return $treasuries;
        }

        return $treasuries
            ->filter(fn (Treasury $treasury) => in_array((int) $treasury->account_id, $own, true))
            ->values();
    }

    /**
     * نظيرةُ `ownDepositable()` عند الحفظ — فلا يُقبض في خزينةٍ لم تُعرَض.
     */
    public static function ownDepositBlockReason($accountId): ?string
    {
        if ($denied = self::depositBlockReason($accountId)) {
            return $denied;
        }

        $own = self::ownAccountIds();

        if ($own !== null && $accountId && ! in_array((int) $accountId, $own, true)) {
            return 'لا يُسمح لك بالإيداع إلا في الخزينة المعيَّنة لك في «خزائن الموظفين». راجع المسؤول لتعديل التعيين.';
        }

        return null;
    }

    /**
     * نظيرةُ `ownWithdrawable()` عند الحفظ: شرطُ الخزينة أولاً، ثم أنّها
     * خزينةُ الموظف نفسه — فلا يُصرف من خزينةٍ لم تُعرَض عليه.
     */
    public static function ownWithdrawBlockReason($accountId): ?string
    {
        if ($denied = self::withdrawBlockReason($accountId)) {
            return $denied;
        }

        $own = self::ownAccountIds();

        if ($own !== null && $accountId && ! in_array((int) $accountId, $own, true)) {
            return 'لا يُسمح لك بالسحب إلا من الخزينة المعيَّنة لك في «خزائن الموظفين». راجع المسؤول لتعديل التعيين.';
        }

        return null;
    }

    /**
     * حساباتُ خزائن الموظف الحالي — `treasury_employees.treasury_id` معرّفُ
     * **حساب** رغم اسمه. `null` = بلا قيد: لا مستخدم، أو ليس موظفاً، أو
     * يملك `data_scope_all`.
     *
     * @return array<int, int>|null
     */
    private static function ownAccountIds(): ?array
    {
        $user = auth()->user();

        if (! $user || ! $user->employee_id || $user->hasRolePermission('data_scope_all')) {
            return null;
        }

        return TreasuryEmployee::where('employee_id', $user->employee_id)
            ->pluck('treasury_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Treasury>
     */
    private static function allowed(string $action)
    {
        return Treasury::whereNotNull('account_id')
            ->orderBy('name')
            ->get()
            ->filter(fn (Treasury $treasury) => self::rowBlockReason($treasury, $action) === null)
            ->values();
    }

    /**
     * الفحصُ على صفٍّ بعينه — لا بحثاً بـ`account_id` من جديد.
     *
     * القائمةُ تفحص عشرات الصفوف، والبحثُ بالحساب لكلٍّ منها استعلامٌ
     * زائد، وقد يقع على صفٍّ آخر يشارك الحساب نفسه فيفحص شرطاً غير شرط
     * الصفّ المعروض.
     */
    private static function rowBlockReason(Treasury $treasury, string $action): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        // المسؤولون الذين لديهم data_scope_all لا يخضعون لقيود الخزائن
        if ($user->hasRolePermission('data_scope_all')) {
            return null;
        }

        $scope = (int) ($action === 'deposit'
            ? $treasury->deposit_permissions
            : $treasury->withdraw_permissions);

        if ($scope === self::SCOPE_EVERYONE) {
            return null;
        }

        $value = $action === 'deposit'
            ? $treasury->value_of_deposit_permissions
            : $treasury->value_of_withdraw_permissions;

        // شرطٌ بلا قيمة لا يَقفل: ضبطٌ ناقص لا يجوز أن يمنع العمل
        if ($value === null || $value === '') {
            return null;
        }

        if (self::scopeMatches($scope, $value, $user)) {
            return null;
        }

        return self::denialMessage($treasury, $action, $scope, $value);
    }

    /** هل ينطبق شرط الخزينة على هذا المستخدم؟ */
    private static function scopeMatches(int $scope, $value, $user): bool
    {
        return match ($scope) {
            self::SCOPE_EMPLOYEE => (string) $user->employee_id === (string) $value,
            self::SCOPE_BRANCH => (string) $user->branch_id === (string) $value,
            self::SCOPE_ROLE => self::userHasFunctionalRole($user, $value),
            default => true,
        };
    }

    /**
     * الدور الوظيفي: يُطابَق بالمعرّف أو بالاسم.
     *
     * الشاشة تحفظ معرّف الدور، لكنّ بعض الصفوف القديمة تحمل اسمه — فتُقبل
     * الصيغتان بدل أن يُمنع مستخدمٌ لاختلافٍ في صيغة التخزين.
     */
    private static function userHasFunctionalRole($user, $value): bool
    {
        try {
            // الدور الأساسي (role_id ← role_has_permissions) — لا model_has_roles
            // من Spatie، الذي كان يقرأ منه هذا الفحص سابقاً
            if ($user->hasRoleName((string) $value)) {
                return true;
            }

            if (is_numeric($value)) {
                return (int) $user->role_id === (int) $value;
            }
        } catch (\Throwable $e) {
            // تعذّرت قراءة الدور — لا يُمنع المستخدم بسبب عطلٍ في القراءة
            return true;
        }

        return false;
    }

    /** رسالةٌ تسمّي المسموح له، لا «ليس لديك صلاحية» وحدها. */
    private static function denialMessage($treasury, string $action, int $scope, $value): string
    {
        $verb = $action === 'deposit' ? 'الإيداع في' : 'السحب من';
        $kind = (int) $treasury->type === 1 ? 'الحساب البنكي' : 'الخزينة';

        // العائد يختلف بين المفرد والجمع، فيُصاغ مع الجهة لا بعدها
        [$who, $tail] = match ($scope) {
            self::SCOPE_EMPLOYEE => [
                'الموظّف '.self::scopeLabel('employee', $value),
                'وحده يملك ذلك',
            ],
            self::SCOPE_ROLE => [
                'أصحاب الدور الوظيفي «'.self::scopeLabel('role', $value).'»',
                'وحدهم يملكون ذلك',
            ],
            self::SCOPE_BRANCH => [
                'موظّفو فرع «'.self::scopeLabel('branch', $value).'»',
                'وحدهم يملكون ذلك',
            ],
            default => ['المصرَّح لهم', 'وحدهم يملكون ذلك'],
        };

        return sprintf(
            'لا يُسمح لك بـ%s %s «%s»: %s %s. راجع إعدادات %s أو اطلب من المسؤول تعديلها.',
            $verb, $kind, $treasury->name, $who, $tail,
            (int) $treasury->type === 1 ? 'الحساب البنكي' : 'الخزينة'
        );
    }

    /** اسمُ الجهة المسموح لها — ليقرأ المستخدم اسماً لا رقماً. */
    private static function scopeLabel(string $kind, $value): string
    {
        try {
            $name = match ($kind) {
                // `employees` لا عمود `name` فيه — الاسمُ مركَّب، فكانت
                // الرسالة تقول «الموظّف #1» ولا يعرف القارئ من هو
                'employee' => optional(Employee::find($value))->full_name,
                'branch' => optional(Branch::find($value))->name,
                'role' => is_numeric($value)
                    ? optional(Role::find($value))->name
                    : (string) $value,
                default => null,
            };
        } catch (\Throwable $e) {
            $name = null;
        }

        return $name ?: ('#'.$value);
    }
}
