<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\StoreHouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * من يملك العمل على هذا المستودع؟
 *
 * **ما كان**
 *
 * شاشة المستودع تسأل ثلاثة أسئلة — من يعرضه، ومن يُنشئ منه فاتورة، ومن
 * يعدّل مخزونه — وتحفظ لكلٍّ منها جمهوراً: الكل، أو موظّفاً بعينه، أو
 * دوراً (من نظام الأدوار والصلاحيات الحالي `roles`، لا `job_roles`
 * القديم)، أو فرعاً. تُكتب الأعمدة الستّة في قاعدة البيانات
 * (`*_permissions` ونظائرها `value_of_*`) و**لا يقرؤها أحد**: لا شاشةُ
 * فاتورةٍ ولا حفظُها. فالضبط موجود في الواجهة وأثرُه معدوم — وهو أسوأ من
 * غيابه، لأنّ صاحب النظام يظنّ المستودع محروساً وهو مفتوح.
 *
 * **ما صار**
 *
 * الحكم هنا وحده، ويُطبَّق في موضعين: تصفيةُ قائمة المستودعات في الشاشة
 * (`scopeCreateInvoices`) كي لا يُعرض ما لا يُسمح به، وفحصُ المستودع
 * المُرسَل عند الحفظ (`canCreateInvoices`) — فلا يمرّ من عدّل قيمة القائمة
 * في المتصفّح.
 *
 * **من يُستثنى؟** المستخدم بلا سجلّ موظّف — المدير وصاحب النظام. الحراسة
 * هنا تنظّم عمل الموظّفين على المستودعات، لا تحجب من يديرها. وهو نفس ما
 * كانت تفعله شاشة الفاتورة أصلاً حين تعطي `StoreHouse::all()` لمن لا
 * `employee_id` له.
 */
class StorehouseAccess
{
    /** الجمهور: الكل. */
    public const AUDIENCE_ALL = 0;

    /** الجمهور: موظّف بعينه — القيمة `employees.id`. */
    public const AUDIENCE_EMPLOYEE = 1;

    /** الجمهور: دور — القيمة `roles.id` (نظام الأدوار والصلاحيات الحالي، لا `job_roles`). */
    public const AUDIENCE_JOB_ROLE = 2;

    /** الجمهور: فرع — القيمة `branches.id`. */
    public const AUDIENCE_BRANCH = 3;

    /** أعمدة كل صلاحية: النوع والقيمة. */
    private const SCOPES = [
        'view' => ['view_permissions', 'value_of_view_permissions'],
        'create_invoices' => ['crate_invoices_permissions', 'value_of_crate_invoices_permissions'],
        'edit_stock' => ['edit_stock_permissions', 'value_of_edit_stock_permissions'],
    ];

    /** هل يجوز لهذا المستخدم إنشاء فاتورة من هذا المستودع؟ */
    public static function canCreateInvoices($storehouse, ?User $user = null): bool
    {
        return self::allows('create_invoices', $storehouse, $user);
    }

    /** هل يجوز له عرض المستودع؟ */
    public static function canView($storehouse, ?User $user = null): bool
    {
        return self::allows('view', $storehouse, $user);
    }

    /** هل يجوز له تعديل مخزونه؟ */
    public static function canEditStock($storehouse, ?User $user = null): bool
    {
        return self::allows('edit_stock', $storehouse, $user);
    }

    /**
     * الحكم العام لأي صلاحية.
     *
     * المستودع المجهول يُمنع: قائمةٌ أُرسل فيها معرّفٌ لا وجود له ليست
     * حالةً تُتساهَل فيها.
     */
    public static function allows(string $scope, $storehouse, ?User $user = null): bool
    {
        [$typeColumn, $valueColumn] = self::SCOPES[$scope] ?? self::SCOPES['create_invoices'];

        $storehouse = $storehouse instanceof StoreHouse
            ? $storehouse
            : StoreHouse::find($storehouse);

        if (! $storehouse) {
            return false;
        }

        $user = $user ?: auth()->user();

        if (! $user) {
            return false;
        }

        // من لا سجلّ موظّف له يدير النظام لا يعمل فيه — لا يُحجب
        if (! $user->employee_id) {
            return true;
        }

        /*
            وحاملُ `data_scope_all` كذلك — بقرار صاحب النظام: التجاوزُ
            الصريح لكل البيانات يعلو على ضبط المستودع نفسه، في الصلاحيات
            الثلاث معاً (العرض، وإنشاء الفاتورة، وتعديل المخزون) — فتسري
            على فواتير المبيعات والمشتريات والتحويلات وكل من يسأل هنا.
        */
        if (self::hasAllDataScope($user)) {
            return true;
        }

        $audience = (int) ($storehouse->{$typeColumn} ?? self::AUDIENCE_ALL);
        $value = $storehouse->{$valueColumn};

        // «الكل»، أو جمهورٌ بلا قيمة محدَّدة — لا حجب
        if ($audience === self::AUDIENCE_ALL || $value === null || $value === '') {
            return true;
        }

        return match ($audience) {
            // «العرض» لموظفٍ محدَّد يمتدّ إلى من يشرف عليه (قرار صاحب النظام): مستودعُ
            // المندوب يظهر لمشرفه. الفاتورةُ وتعديلُ المخزون يبقيان للموظف نفسه.
            self::AUDIENCE_EMPLOYEE => (int) $user->employee_id === (int) $value
                || ($scope === 'view' && in_array((int) $value, self::teamEmployeeIds($user), true)),
            self::AUDIENCE_JOB_ROLE => (int) ($user->role_id ?? 0) === (int) $value,
            self::AUDIENCE_BRANCH => in_array((int) $value, self::branchesOf($user), true),
            // نوعٌ غير معروف: يُمنع لا يُتساهَل — عمودٌ تالف لا يفتح مستودعاً
            default => false,
        };
    }

    /** هل يحمل دورُ المستخدم صلاحية «كل البيانات»؟ — بلا رمي على دورٍ ناقص. */
    /** @var array<int, array<int, int>> */
    private static array $teamMemo = [];

    /** موظفو فريق المستخدم (من يشرف عليهم مباشرةً أو بالتسلسل) — بذاكرة للطلب الواحد. */
    private static function teamEmployeeIds(User $user): array
    {
        return self::$teamMemo[(int) $user->employee_id] ??= array_map(
            'intval',
            Employee::find($user->employee_id)?->getDescendantIds() ?? []
        );
    }

    private static function hasAllDataScope(User $user): bool
    {
        try {
            return $user->hasRolePermission('data_scope_all');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * فروع المستخدم: فرعه، وفرعُ سجلّه الوظيفي، والفرع النشط في الجلسة.
     *
     * ثلاثتها لأنّ الفرع في هذا النظام يُقرأ من ثلاثة مواضع، ومن يعمل في
     * فرعٍ يُبدّله من الترويسة لا يصحّ أن يفقد مستودعه بذلك.
     *
     * @return array<int, int>
     */
    private static function branchesOf(User $user): array
    {
        $branches = [
            $user->branch_id ?? null,
            optional($user->employee)->branch_id ?? null,
        ];

        if (function_exists('current_branch_id')) {
            $branches[] = current_branch_id();
        }

        return array_values(array_unique(array_map('intval', array_filter($branches))));
    }

    /**
     * تصفية استعلام المستودعات على ما يجوز للمستخدم إنشاء فاتورة منه.
     *
     * تُطبَّق في قاعدة البيانات لا بعد الجلب: القائمة تُعرَض في شاشةٍ قد
     * تحمل مئات المستودعات، وتصفيتُها في PHP تجلبها كلّها أوّلاً.
     */
    public static function scopeCreateInvoices(Builder $query, ?User $user = null): Builder
    {
        return self::scopeFor('create_invoices', $query, $user);
    }

    /** تصفية استعلام المستودعات على أي صلاحية. */
    public static function scopeFor(string $scope, Builder $query, ?User $user = null): Builder
    {
        [$typeColumn, $valueColumn] = self::SCOPES[$scope] ?? self::SCOPES['create_invoices'];

        $user = $user ?: auth()->user();

        // غير الموظف وحامل data_scope_all بلا قيد — كما في allows()
        if (! $user || ! $user->employee_id || self::hasAllDataScope($user)) {
            return $query;
        }

        $employeeId = (int) $user->employee_id;
        $roleId = (int) ($user->role_id ?? 0);
        $branchIds = self::branchesOf($user);
        // «العرض» يمتدّ إلى مستودعات من يشرف عليهم — كما في allows()
        $employeeIds = $scope === 'view'
            ? array_values(array_unique(array_merge([$employeeId], self::teamEmployeeIds($user))))
            : [$employeeId];

        return $query->where(function (Builder $q) use ($typeColumn, $valueColumn, $employeeIds, $roleId, $branchIds) {
            // «الكل» أو بلا ضبط
            $q->whereNull($typeColumn)
                ->orWhere($typeColumn, self::AUDIENCE_ALL)
                ->orWhereNull($valueColumn)
                ->orWhere($valueColumn, '')
                ->orWhere(fn (Builder $s) => $s->where($typeColumn, self::AUDIENCE_EMPLOYEE)->whereIn($valueColumn, $employeeIds))
                ->orWhere(fn (Builder $s) => $s->where($typeColumn, self::AUDIENCE_JOB_ROLE)->where($valueColumn, $roleId))
                ->orWhere(fn (Builder $s) => $s->where($typeColumn, self::AUDIENCE_BRANCH)->whereIn($valueColumn, $branchIds ?: [0]));
        });
    }

    /** رسالة المنع — تسمّي المستودع فلا يبقى المستخدم يخمّن. */
    public static function message($storehouse): string
    {
        $name = $storehouse instanceof StoreHouse
            ? $storehouse->name
            : StoreHouse::whereKey($storehouse)->value('name');

        return sprintf(
            'لا تملك صلاحية إنشاء فاتورة من مستودع «%s».',
            $name ?: 'المحدَّد'
        );
    }
}
