<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\User;
use App\Support\DataScope;

/**
 * نطاقُ الرؤية في شاشات المبيعات — «على أيّ صفوفٍ تسري صلاحيتي؟».
 *
 * ══════════ ما كان ══════════
 *
 * حالتان لا ثالثة في كلّ شاشةٍ من شاشات الوحدة: من يملك `data_scope_all`
 * يرى **كلَّ** المستندات، ومن دونه يرى **ما أنشأه هو** وحدَه
 * (`created_by = auth()->id()`). فالمشرفُ الذي يدير فريقاً — وقد مُنح
 * «نطاق الرؤية: بيانات فريقه» صراحةً في شاشة الأدوار — يفتح فواتيرَ
 * المبيعات فلا يجد إلّا ما كتبه بيده، ولا شيءَ في الشاشة يقول لماذا.
 *
 * والسلَّمُ الخماسيّ ({@see \App\Constants\permissions\DataScopePermissions})
 * محسوبٌ في {@see DataScope} ويقرؤه ستةٌ وعشرون تقريراً ومُنتقيا «مسؤول
 * المبيعات» و«أُضيفت بواسطة» في هذه الوحدة نفسِها — ولا يقرؤه **صفٌّ
 * واحد** من صفوف جداولها. فالمنسدلةُ تعرض الفريقَ والجدولُ يعرض صاحبَه
 * وحدَه.
 *
 * ونظائرُ الوحدة الثلاث (سنداتُ القبض والصرف في الحسابات، أذونُ المخزن،
 * فواتيرُ المشتريات) تُوسّع على المدير بالتسلسل الإداري منذ البداية — ومن
 * هنا جاء السؤال: «يعمل في سندات القبض ولا يعمل في المبيعات».
 *
 * ══════════ ما صار ══════════
 *
 * محورانِ لا محورٌ واحد، والقاعدةُ من {@see DataScope} بحرفها:
 *
 *   • `all` (أو مديرٌ بلا `role_id` — انظر {@see seesAllSalesDocuments()})
 *     ← بلا قيد.
 *   • `region`/`branch` ← محورُ **مكان**: القيدُ على `branch_id` وحدَه،
 *     فكلُّ ما في الفرع مرئيٌّ بصرف النظر عن كاتبه.
 *   • `team`/`own` ← محورُ **أشخاص**: القيدُ على `created_by` أو
 *     `employee_id`، بصرف النظر عن الفرع.
 *
 * ولا يُطبَّق المحوران معاً: مستندٌ في فرعي كتبه غيري يسقط من شاشتي لو
 * جُمعا — والعلّةُ مشروحةٌ في {@see DataScope::isPeopleAxis()}.
 *
 * ══════════ ولماذا `created_by` **أو** `employee_id` ══════════
 *
 * العمودان يقولان شيئين مختلفين: الأوّلُ من كتب المستند، والثاني من
 * يُنسب إليه بيعُه (وعليه تُحسب عمولتُه ومستهدفُه). ومحاسبٌ يُدخل فاتورةً
 * عن مندوب يكتب الأوّلَ باسمه والثاني باسم المندوب — فالقيدُ على أحدهما
 * وحدَه يُخفي المستندَ عن صاحبه الآخر.
 *
 * و`employee_id` فارغٌ في أكثر مستندات هذه المنشأة (٤٦٢ إشعاراً دائناً من
 * ٤٦٣، و٩٢٩٤ سنداً من ٩٤٠٥)، فهو زيادةٌ في الرؤية لا أساسُها — و`created_by`
 * هو القيدُ العمليّ.
 *
 * ══════════ والقائمةُ ليست حارساً ══════════
 *
 * تصفيةُ الجدول تُخفي ولا تمنع: من يعرف رقم مستندٍ يفتحه بكتابة عنوانه.
 * فلكلّ جلبٍ بالمعرّف {@see authorizeSalesDocumentScope()} بعده مباشرةً —
 * وهي القاعدةُ نفسُها المتَّبعة في المشتريات وأذون المخزن.
 *
 * @see DataScope السلَّمُ ومعانيه
 * @see \Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy نظيرُه في سندات القبض
 */
trait ScopesSalesVisibility
{
    /**
     * أيرى المستخدم مستنداتِ المبيعات كلَّها لا ما أنشأه أو نُسب إليه وحده؟
     *
     * `data_scope_all` من دوره — أو مديرٌ بلا دورٍ مربوط: قاعدةُ بياناتٍ
     * مستوردة تصل وأغلبُ مستخدميها بلا `role_id`، و`hasRolePermission()`
     * تُرجع `false` فوراً عندها، فيفتح المديرُ الفواتيرَ والمرتجعاتِ
     * والإشعاراتِ فارغةً. يُقرأ عمودُ `users.role` النصّي حينها كما في
     * المشتريات (`ScopesToEmployeeHierarchy::usesLegacyRoleFallback()`)،
     * ومن له `role_id` يُحكم بدوره كما كان.
     *
     * (كانت في `RespectsAccountingPeriods` — وموضعُها النطاقُ لا الفتراتُ
     * المحاسبية.)
     */
    protected function seesAllSalesDocuments(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRolePermission('data_scope_all') || DataScope::isLegacyManager($user);
    }

    /**
     * الفروعُ التي يراها — أو `null` إن لم يكن نطاقُه مكانياً أصلاً.
     *
     * @return array<int, int>|null
     */
    protected function salesScopeBranchIds(): ?array
    {
        return $this->seesAllSalesDocuments() ? null : DataScope::branchIds();
    }

    /**
     * معرّفاتُ المستخدمين المشمولين (`users.id`) — لعمود `created_by`.
     *
     * @return array<int, int>|null
     */
    protected function salesScopeUserIds(): ?array
    {
        return $this->seesAllSalesDocuments() ? null : DataScope::documentUserIds();
    }

    /**
     * معرّفاتُ الموظفين المشمولين (`employees.id`) — لعمود `employee_id`.
     *
     * @return array<int, int>|null
     */
    protected function salesScopeEmployeeIds(): ?array
    {
        return $this->seesAllSalesDocuments() ? null : DataScope::documentEmployeeIds();
    }

    /** هل على هذا المستخدم قيدُ نطاقٍ أصلاً؟ */
    protected function salesScopeApplies(): bool
    {
        return ! $this->seesAllSalesDocuments()
            && (DataScope::branchIds() !== null || DataScope::documentUserIds() !== null);
    }

    /**
     * تقييدُ استعلامِ مستنداتٍ بنطاق المستخدم.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  string|null  $creatorColumn  عمودُ من أنشأ المستند (`users.id`)
     * @param  string|null  $employeeColumn  عمودُ من يُنسب إليه — أو `null` إن لم يوجد
     * @param  string  $employeeColumnHolds  ماذا يحمل ذاك العمود: `employee` (`employees.id`) أم `user` (`users.id`)
     * @param  string|null  $branchColumn  عمودُ الفرع — أو `null` إن لم يوجد في الجدول
     */
    protected function scopeSalesDocuments(
        $query,
        ?string $creatorColumn = 'created_by',
        ?string $employeeColumn = 'employee_id',
        string $employeeColumnHolds = 'employee',
        ?string $branchColumn = 'branch_id'
    ) {
        if ($this->seesAllSalesDocuments()) {
            return $query;
        }

        /* ── محورُ المكان: الفرعُ وحدَه ──
           ولو لم يكن في الجدول عمودُ فرعٍ سقطنا إلى معرّفات مستخدمي تلك
           الفروع أدناه — لا إلى «بلا قيد». */
        $branchIds = DataScope::branchIds();

        if ($branchIds !== null && $branchColumn) {
            return $query->whereIn($branchColumn, $branchIds ?: [0]);
        }

        $userIds = DataScope::documentUserIds();

        if ($userIds === null) {
            return $query;
        }

        $employeeIds = DataScope::documentEmployeeIds() ?? [];

        return $query->where(function ($scope) use ($creatorColumn, $employeeColumn, $employeeColumnHolds, $userIds, $employeeIds) {
            $matched = false;

            if ($creatorColumn) {
                $scope->whereIn($creatorColumn, $userIds ?: [0]);
                $matched = true;
            }

            if ($employeeColumn) {
                $ids = $employeeColumnHolds === 'user' ? $userIds : $employeeIds;

                $matched
                    ? $scope->orWhereIn($employeeColumn, $ids ?: [0])
                    : $scope->whereIn($employeeColumn, $ids ?: [0]);

                $matched = true;
            }

            // لا هذا ولا ذاك: لا يُترك الاستعلامُ مفتوحاً بالصمت.
            if (! $matched) {
                $scope->whereRaw('1 = 0');
            }
        });
    }

    /**
     * ══════════ فلترُ الفرع النشط، ومتى يجب أن يصمت ══════════
     *
     * شاشاتُ المبيعات كلُّها تقصّ صفوفَها على الفرع المختار في الشريط
     * العلويّ (`current_branch_id()` = `users.branch_id`)، والفرعُ الرئيسيّ
     * مستثنىً لأنه مركزُ المنشأة لا فرعٌ من فروعها.
     *
     * وهذا صوابٌ على **محور المكان** (`all`/`branch`/`region`): من يرى
     * فروعاً يختار أيَّها ينظر إليه.
     *
     * أمّا على **محور الأشخاص** (`team`/`own`) فهو حجبٌ لا تصفية: صفوفُ
     * المستخدم مقيَّدةٌ بشخصه وفريقه أصلاً، وفريقُه قد يعمل في فروعٍ غير
     * فرعه — فلا يزيد قيدُ الفرع فوق ذلك إلّا أن يُخفي عنه عملَ فريقه.
     *
     * وهذا هو العَرَضُ الذي وقع فعلاً: مشرفٌ فرعُه «منطقة الشرقية» ومرؤوساه
     * في منطقتين أخريين — النطاقُ يحسب له ١٢٤٤ فاتورة، ويردّه فلترُ الفرع
     * إلى ٨٢٤، وهي فواتيرُه هو بالضبط. فيقول: «ضبطتُ المدير المباشر ولا
     * أرى إلّا بياناتي» — والنطاقُ يعمل، والفرعُ يحجب.
     *
     * وهي القاعدةُ المكتوبة في {@see DataScope::isPeopleAxis()}: «تطبيقُ
     * الاثنين معاً على استعلامٍ واحد يطرح صفوفاً مشروعة».
     *
     * يتطلّب `isMainBranch()` من `RespectsAccountingPeriods` — والمتحكّمات
     * التي تستعمل هذا التراث تستعمله معه.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applyHeaderBranchScope($query)
    {
        if ($this->salesScopeIsPeopleAxis()) {
            return $query;
        }

        if ($this->isMainBranch(current_branch_id())) {
            return $query;
        }

        return $query->forBranch(current_branch_id());
    }

    /** أنطاقُه محورُ أشخاصٍ (فريقُه أو هو وحدَه) لا محورُ مكان؟ */
    protected function salesScopeIsPeopleAxis(): bool
    {
        return ! $this->seesAllSalesDocuments() && DataScope::isPeopleAxis();
    }

    /**
     * هل يقع هذا المستند داخل نطاق المستخدم؟ — لقرارات العرض في الواجهة.
     *
     * @param  mixed  $createdBy  قيمةُ عمود المنشئ (`users.id`)
     * @param  mixed  $employeeId  قيمةُ عمود المسؤول — أو `null`
     * @param  mixed  $branchId  قيمةُ عمود الفرع — أو `null`
     */
    protected function canAccessSalesDocument(
        $createdBy,
        $employeeId = null,
        $branchId = null,
        string $employeeColumnHolds = 'employee'
    ): bool {
        if ($this->seesAllSalesDocuments()) {
            return true;
        }

        $branchIds = DataScope::branchIds();

        if ($branchIds !== null) {
            return $branchId !== null && in_array((int) $branchId, $branchIds, true);
        }

        $userIds = DataScope::documentUserIds();

        if ($userIds === null) {
            return true;
        }

        if ($createdBy !== null && in_array((int) $createdBy, $userIds, true)) {
            return true;
        }

        if ($employeeId === null) {
            return false;
        }

        $ids = $employeeColumnHolds === 'user' ? $userIds : (DataScope::documentEmployeeIds() ?? []);

        return in_array((int) $employeeId, $ids, true);
    }

    /**
     * يوقف الطلب بـ403 إن كان المستند خارج نطاق المستخدم.
     *
     * تُستدعى بعد كلّ `findOrFail` مباشرةً: التصفيةُ تُخفي، وهذه تمنع.
     */
    protected function authorizeSalesDocumentScope(
        $createdBy,
        $employeeId = null,
        $branchId = null,
        string $employeeColumnHolds = 'employee'
    ): void {
        if (! $this->canAccessSalesDocument($createdBy, $employeeId, $branchId, $employeeColumnHolds)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا المستند.');
        }
    }

    /**
     * مستخدمو النطاق جاهزين لمنسدلة «أُضيفت بواسطة».
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     */
    protected function salesScopeUsers($query = null)
    {
        $query ??= User::query();

        $ids = $this->salesScopeUserIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get();
    }

    /**
     * خياراتُ فلتر «أُضيفت بواسطة» — من الصفوف التي يراها المستخدم نفسِها.
     *
     * كانت كلُّ شاشةٍ تبني القائمةَ بطريقتها: `User::all()` في المرتجعات،
     * وكلُّ الموظفين والمديرين في الإشعارات، ونطاقُ الأشخاص وحدَه في
     * الفواتير. فتظهر أسماءٌ من فرعٍ آخر أو لم تُنشئ مستنداً قطّ، وضغطُها
     * يُخرج جدولاً فارغاً.
     *
     * والقائمةُ هنا تُقرأ من الاستعلام **بعد** نطاق الرؤية وفرع الشريط
     * العلويّ و**قبل** فلاتر البحث: من أنشأ مستنداً يراه المستخدم في هذا
     * الفرع يظهر اسمُه، ولا يظهر غيرُه — مهما كان النطاق فرعاً أو فريقاً.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $baseQuery
     * @param  string  $column  عمودٌ يحمل `users.id`
     */
    protected function creatorFilterOptions($baseQuery, string $column = 'created_by')
    {
        $ids = $this->distinctFilterValues($baseQuery, $column);

        return $ids
            ? User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    /**
     * خياراتُ فلتر «مسؤول المبيعات» — على القاعدة نفسِها.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $baseQuery
     * @param  string  $column  عمودٌ يحمل `employees.id`
     */
    protected function salesPersonFilterOptions($baseQuery, string $column = 'employee_id')
    {
        $ids = $this->distinctFilterValues($baseQuery, $column);

        return $ids
            ? Employee::whereIn('id', $ids)->get()
                ->sortBy(fn ($employee) => mb_strtolower(trim((string) $employee->full_name)))
                ->values()
            : collect();
    }

    /**
     * القيمُ المميَّزة لعمودٍ في استعلامٍ مُنسوخ.
     *
     * `select()` لا `pluck()` وحدها: `withCount()` في شاشة العروض يضع
     * أعمدةً على الاستعلام، و`DISTINCT` عليها كلِّها لا يُميّز شيئاً.
     *
     * @return array<int, int>
     */
    private function distinctFilterValues($baseQuery, string $column): array
    {
        $qualified = str_contains($column, '.')
            ? $column
            : $baseQuery->getModel()->getTable().'.'.$column;

        return (clone $baseQuery)->toBase()
            ->reorder()
            ->select($qualified)
            ->distinct()
            ->whereNotNull($qualified)
            ->pluck($qualified)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }
}
