<?php

namespace Modules\HR\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Support\AffiliationTree;
use App\Support\HeaderBranchScope;
use App\Support\Scopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * نطاقُ الرؤية في الموارد البشرية — محورُه **الموظف** لا منشئُ السجل.
 *
 * `App\Support\Scopes::applyDataScope()` تقيّد بعمود `created_by`، وذاك
 * صوابٌ في الفواتير: من كتب الفاتورة صاحبُها. أمّا هنا فلا: قسيمةُ الراتب
 * تخصّ الموظفَ الذي صُرفت له، ومن كتبها كاتبُ الرواتب. فتقييدٌ بـ`created_by`
 * يُري كاتبَ الرواتب كلَّ القسائم (لأنه أنشأها جميعاً) ولا يُري الموظفَ
 * قسيمتَه هو. ولهذا يدور هذا المرجعُ على `employee_id`.
 *
 * **السلّم** — من الأوسع إلى الأضيق، وأوّلُ ما يملكه المستخدم يحسم:
 *
 *   • `data_scope_all`         كلُّ الموظفين بلا قيد.
 *   • `data_scope_region`      موظفو فرعِه وفروعِه التابعة.
 *   • `data_scope_branch`      موظفو فرعِه.
 *   • `data_scope_department`  موظفو قسمِه.
 *   • `data_scope_team`        هو ومن يتبعه إدارياً (`direct_manager_id`).
 *   • وإلا                     هو وحده.
 *
 * وجداولُ الموارد البشرية كلُّها تحمل `employee_id` ولا تحمل `branch_id`،
 * فمستوى الفرع يُحَلّ عبر `employees.branch_id` ثم يُقيَّد بمعرّفات الموظفين.
 *
 * **مستخدمٌ ليس موظفاً** يُقيَّد بنطاقه كغيره: `data_scope_all` تفتح له
 * الكلّ، ونطاقُ الفرع/المنطقة يُحلّ من `users.branch_id` — وما سوى ذلك
 * (`data_scope_own` أو لا نطاق) لا يُريه أحداً، إذ لا سجلَّ موظفٍ له.
 * لا صلاحياتِ «هو» بعد اليوم: النطاقُ وحده يحدّد على من تسري الصلاحية.
 */
trait ScopesToAccessibleEmployees
{
    /** @var array<int>|null|false ذاكرةٌ للطلب الواحد؛ false = لم يُحسب بعد */
    private array|null|false $hrAccessibleEmployeeIds = false;

    /** @var array<int>|null|false */
    private array|null|false $hrListEmployeeIds = false;

    protected function hrScopeUser(): ?User
    {
        return Auth::user();
    }

    /**
     * معرّفاتُ الموظفين التي يحقّ للمستخدم الحالي رؤيةُ بياناتهم.
     *
     * `null` تعني «بلا قيد» — لا مصفوفةً فارغة: الفارغةُ تعني «لا أحد»،
     * والخلطُ بينهما يقلب الحارسَ إلى بابٍ مفتوح أو شاشةٍ فارغة.
     *
     * @return array<int>|null
     */
    protected function accessibleEmployeeIds(): ?array
    {
        if ($this->hrAccessibleEmployeeIds !== false) {
            return $this->hrAccessibleEmployeeIds;
        }

        return $this->hrAccessibleEmployeeIds = $this->resolveAccessibleEmployeeIds();
    }

    /** @return array<int>|null */
    private function resolveAccessibleEmployeeIds(): ?array
    {
        $user = $this->hrScopeUser();

        if (! $user) {
            return [];
        }

        if (hasPermission('data_scope_all')) {
            return null;
        }

        $branchIds = $this->hrScopeBranchIds($user);

        if ($branchIds !== null) {
            return Employee::whereIn('branch_id', $branchIds)->pluck('id')->all();
        }

        // ليس موظفاً ولا نطاقَ أوسعَ له: «بياناته هو» لا شيءَ فيها — فلا يرى أحداً.
        if (! $user->employee_id) {
            return [];
        }

        $employee = $user->employee;

        if (! $employee) {
            return [(int) $user->employee_id];
        }

        if (hasPermission('data_scope_department')) {
            $departmentId = $employee->department_id;
            if ($departmentId) {
                return Employee::where('department_id', $departmentId)->pluck('id')->all();
            }

            // نطاقُ قسمٍ بلا قسمٍ محفوظ: يُضيَّق إلى نفسه لا أن يُفتح له كل شيء.
            return [(int) $employee->id];
        }

        if (hasPermission('data_scope_team')) {
            return array_values(array_unique(
                array_merge([(int) $employee->id], $employee->getDescendantIds())
            ));
        }

        return [(int) $employee->id];
    }

    /**
     * الفروعُ التي يشملها نطاقُ المستخدم — أو `null` إن لم يكن نطاقُه فرعياً.
     *
     * @return array<int>|null
     */
    private function hrScopeBranchIds(User $user): ?array
    {
        $isRegion = hasPermission('data_scope_region');

        if (! $isRegion && ! hasPermission('data_scope_branch')) {
            return null;
        }

        $branchId = Scopes::resolveBranchId($user);

        // نطاقٌ فرعيٌّ بلا فرع: يُعامَل كموظفٍ عاديّ بدل أن يُفتح له كلُّ شيء.
        if (! $branchId) {
            return null;
        }

        if (! $isRegion) {
            return [(int) $branchId];
        }

        $descendants = Branch::findCached($branchId)?->descendantBranchIds();

        return $descendants
            ? $descendants->push($branchId)->unique()->values()->all()
            : [(int) $branchId];
    }

    /**
     * تقييدُ استعلامٍ يحمل عمودَ موظف.
     *
     * `[0]` عند المصفوفة الفارغة: `whereIn` بمصفوفةٍ فارغة تُنتج شرطاً
     * كاذباً في بعض المحرّكات وتُهمَل في أخرى — والصريحُ أسلم.
     */
    protected function scopeToEmployees(Builder $query, string $column = 'employee_id'): Builder
    {
        $ids = $this->listEmployeeIds();

        $this->onlyActiveInAttendance($query, $column, $ids === null);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    /**
     * شاشاتُ الحضور والانصراف لا تعرض إلا من هو على رأس العمل.
     *
     * الموظفُ غيرُ النشط والمستقيلُ والموقوفُ ومنتهي الخدمة كان يظهر في
     * قوائم الحضور اليومي وسجلات التسجيل وإعدادات الحضور وكلّ قائمةٍ منسدلة
     * لاختيار موظف — وهو لا يحضر ولا يُحاسَب على حضور. «على رأس العمل» هو
     * تعريفُ الموديل نفسه (`Employee::active()` — نشط وتحت التجربة).
     *
     * يسري على متحكّمات `Modules\HR\Http\Controllers\Attendance` وحدها: الرواتبُ
     * ونهايةُ الخدمة والتقاريرُ تحتاج من غادر. وفتحُ سجلٍّ قديمٍ برابطه يبقى
     * (`authorizeEmployeeScope()` على الصلاحية وحدها).
     *
     * @param  bool  $keepUnassigned  صفوفٌ بلا موظف (مسحُ بطاقةٍ مجهولة) تبقى ظاهرة لمن لا يُقيَّد نطاقُه
     */
    protected function onlyActiveInAttendance($query, string $column, bool $keepUnassigned = false): void
    {
        if (! str_starts_with(static::class, 'Modules\\HR\\Http\\Controllers\\Attendance\\')) {
            return;
        }

        $active = Employee::query()->whereIn('status', \App\Enums\EmploymentStatus::activeValues())->select('id');

        if ($keepUnassigned && $column !== 'id') {
            $query->where(fn ($q) => $q->whereIn($column, $active)->orWhereNull($column));
        } else {
            $query->whereIn($column, $active);
        }
    }

    /**
     * تقييدُ **أيّ** استعلامٍ بنطاق الموظفين — إلوكوِنت كان أو بانيَ
     * استعلامٍ خام.
     *
     * `scopeToEmployees()` أعلاه تشترط `Eloquent\Builder`، وتقاريرُ الوحدة
     * تُبنى بـ`DB::table(...)->join('employees', …)` — فلا تقبلها. وكانت
     * النتيجةُ أن **التقارير تعرض كل موظفي المنشأة** لمن يملك
     * صلاحيةَ التقارير (`hr_reports.*`)، بينما تمنعه شاشةُ القسائم نفسُها من رؤية قسيمةٍ
     * واحدة خارج نطاقه.
     *
     * فالعمودُ يُمرَّر مؤهَّلاً باسم الجدول أو باسمه المستعار
     * (`payment_employees.id`) لأن الاستعلام الواحد قد يضمّ ثلاثةَ وصلاتٍ
     * إلى `employees`.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     * @param  string  $column  العمود مؤهَّلاً، مثل `salary_slips.employee_id`
     */
    protected function scopeAnyQueryToEmployees($query, string $column)
    {
        $ids = $this->listEmployeeIds();

        $this->onlyActiveInAttendance($query, $column, $ids === null);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    /**
     * قائمةُ الموظفين المسموح عرضُها في فلاتر التقارير.
     *
     * كانت كلُّ شاشةٍ تكتب `Employee::with('department')->get()` — فتعرض
     * أسماءَ من لا يراهم المستخدمُ في قائمةٍ منسدلة، ويُصفّي بها تقريراً
     * لا يُظهر شيئاً. والقائمةُ الآن من النطاق نفسِه.
     */
    protected function scopedEmployeeOptions(array $relations = ['department'])
    {
        $query = Employee::query();

        if ($relations !== []) {
            $query->with($relations);
        }

        return $this->scopeToEmployees($query, 'id')->get();
    }

    /**
     * موظفو القوائم: نطاقُ الصلاحية **وفرعُ الشريط العلويّ** معاً.
     *
     * من وقف في الشريط على «منطقة الرياض» لا يرى في قوائم الموارد البشرية
     * وتقاريرها وقوائمها المنسدلة إلّا موظفي الرياض وما تحتها. والفرعُ
     * الرئيسيّ أو «كلّ الفروع» يعنيان المنشأة كلَّها ({@see HeaderBranchScope}).
     *
     * يُطابَق على `employees.branch_id` — الجهةِ التي يتبعها الموظف.
     * ويبقى المستخدمُ نفسُه ظاهراً في كلّ جهة: إجازاتُه وقسائمُه لا تختفي
     * لأنه وقف على فرعٍ آخر.
     *
     * حارسُ السجلّ المفرد ({@see authorizeEmployeeScope()}) يبقى على الصلاحية
     * وحدها: الشريطُ يُرشِّح القوائم، ولا يمنع فتحَ سجلٍّ يحقّ للمستخدم رؤيتُه.
     *
     * @return array<int>|null
     */
    protected function listEmployeeIds(): ?array
    {
        if ($this->hrListEmployeeIds !== false) {
            return $this->hrListEmployeeIds;
        }

        $allowed = $this->accessibleEmployeeIds();
        $node = HeaderBranchScope::nodeId();

        if (! $node) {
            return $this->hrListEmployeeIds = $allowed;
        }

        $header = Employee::whereIn('branch_id', AffiliationTree::descendantIds($node))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($own = (int) ($this->hrScopeUser()?->employee_id ?? 0)) {
            $header[] = $own;
        }

        $ids = $allowed === null ? $header : array_intersect($allowed, $header);

        return $this->hrListEmployeeIds = array_values(array_unique($ids));
    }

    /** هل يقع هذا الموظف داخل نطاق المستخدم؟ */
    protected function canAccessEmployee($employeeId): bool
    {
        $ids = $this->accessibleEmployeeIds();

        if ($ids === null) {
            return true;
        }

        return $employeeId !== null && in_array((int) $employeeId, $ids, true);
    }

    /**
     * حمايةُ سجلٍّ مفرد بعد جلبه.
     *
     * التقييدُ في القائمة وحدَه لا يكفي: `show/{id}` تُطلب بمعرّفٍ مباشر،
     * فمن غيّر الرقمَ في العنوان بلغ قسيمةَ راتبِ غيره.
     */
    protected function authorizeEmployeeScope($employeeId): void
    {
        if (! $this->canAccessEmployee($employeeId)) {
            abort(403, 'هذا السجل خارج نطاق الموظفين المصرّح لك بالاطّلاع عليهم.');
        }
    }
}
