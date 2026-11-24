<?php

namespace Modules\Account\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * نطاق الرؤية لوحدة الحسابات والمالية — نفس مبدأ نظيره في المخزون
 * (`Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy`)
 * بالضبط: ربطٌ فعليّ موظفٍ↔خزينة (`treasury_employees`) + تسلسلٌ إداري
 * (`Employee::direct_manager_id`) — لا فحص دورٍ ثابت ولا عمود `role` النصي.
 *
 * موظفٌ عاديّ يرى بياناته هو فقط (سنداته، قيوده). من يدير غيره (له
 * مرؤوسون في التسلسل الإداري) يرى بياناته وبيانات كل من يديرهم مباشرة أو
 * عبر السلسلة كاملة. مستخدمٌ ليس موظفاً (`employee_id` فارغ) يرى كل شيء
 * بلا قيدٍ هنا — يُضبط أمره بالصلاحيات وحدها.
 *
 * صلاحية `data_scope_all` (من كتالوج `App\Constants\permissions\DataScopePermissions`
 * المشترك بين الوحدات) تجاوزٌ استثنائي صريح: من يملكها يرى كل بيانات
 * الحسابات والمالية بلا قيدٍ إطلاقاً، حتى لو كان موظفاً له مديرٌ في
 * التسلسل الإداري (كالمدير العام).
 */
trait ScopesToEmployeeHierarchy
{
    protected function scopeUser(): User
    {
        return auth()->user();
    }

    /**
     * أنا + كل من يتبعني إدارياً (`Employee::getDescendantIds()`).
     * `null` = بلا قيدٍ هنا — إمّا لأني أملك `data_scope_all` أو لأني لست
     * موظفاً أصلاً.
     */
    protected function accessibleEmployeeIds(): ?array
    {
        $user = $this->scopeUser();

        if ($user->hasRolePermission('data_scope_all')) {
            return null;
        }

        if (! $user->employee_id) {
            return null;
        }

        /* ── السلَّمُ الخماسيّ، لا التسلسلُ الإداريّ وحدَه ──

           كان الحسابُ هنا: «أنا + من يتبعني إدارياً» مهما كان نطاقُ دوري.
           فمديرُ فرعٍ مُنح `data_scope_branch` صراحةً لا يرى إلّا من يتبعه
           في التسلسل — وأكثرُ موظفي فرعه لا يتبعونه فيه — فنطاقٌ أوسعُ
           اسماً أضيقُ أثراً. ومن مُنح `own` صراحةً كان يرى مرؤوسيه رغم
           القرار المكتوب.

           و{@see \App\Support\DataScope::documentEmployeeIds()} تجمع
           الاثنين في سلَّمٍ واحد: `all` بلا قيد، و`region`/`branch` موظفو
           الفروع، و`team` هو ومن يتبعه، و`own` هو وحدَه — ومن **لا نطاقَ
           في دوره إطلاقاً** يُقاس على التسلسل الإداري كما كانت هذه الشاشة
           تفعل دائماً، فلا يتغيّر شيءٌ لمن لم يُضبط له نطاق. */
        $ids = DataScope::documentEmployeeIds();

        if ($ids === null) {
            return null;
        }

        /* والمشرفُ يرى فريقه مهما ضاق نطاقُ دوره (قرار صاحب النظام): السلَّم
           يوسّع (فرع/منطقة) ولا يُضيِّق دون «أنا ومن أشرف عليهم» — مشرفٌ دورُه
           بنطاق `own` كان لا يرى سندات من يشرف عليهم. */
        $team = Employee::find($user->employee_id)?->getDescendantIds() ?? [];

        return array_values(array_unique(array_map('intval', array_merge($ids, [$user->employee_id], $team))));
    }

    /**
     * معرّفات حسابات المستخدمين (`users.id`) المرتبطة بهذه المجموعة من
     * الموظفين. `null` = بلا قيد.
     */
    protected function accessibleUserIds(): ?array
    {
        /* نفسُ السلَّم محسوباً على `users` — ولا يُشتقّ من الموظفين هنا:
           في محور المكان (فرع/منطقة) يُحسب على `users.branch_id` مباشرةً،
           فلا يسقط مستخدمٌ في الفرع لا سجلَّ موظفٍ له. */
        $ids = DataScope::documentUserIds();

        if ($ids === null) {
            return null;
        }

        // والمشرفُ يرى مستندات فريقه مهما ضاق نطاقُ دوره — كما في accessibleEmployeeIds()
        $user = $this->scopeUser();
        $team = $user->employee_id ? (Employee::find($user->employee_id)?->getDescendantIds() ?? []) : [];
        $teamUserIds = $team ? User::whereIn('employee_id', $team)->pluck('id')->all() : [];

        return array_values(array_unique(array_map('intval', array_merge($ids, [$user->id], $teamUserIds))));
    }

    /**
     * معرّفات الخزائن (حسابات من نوع خزينة، `accounts.id`) المرتبطة بهذه
     * المجموعة عبر `treasury_employees` — الربط الفعلي موظفٍ↔خزينة، لا
     * استنتاجٌ من فرعٍ أو دور. `null` = بلا قيد.
     */
    protected function accessibleTreasuryIds(): ?array
    {
        $employeeIds = $this->accessibleEmployeeIds();
        if ($employeeIds === null) {
            return null;
        }

        return TreasuryEmployee::whereIn('employee_id', $employeeIds)
            ->pluck('treasury_id')
            ->unique()
            ->values()
            ->all();
    }

    /** تقييد استعلامٍ بعمود منشئ السجل (افتراضاً `created_by`) وفق هذا النطاق. */
    protected function scopeToCreator(Builder $query, string $column = 'created_by'): Builder
    {
        $ids = $this->accessibleUserIds();
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    /** حماية سجلٍّ واحد بعد جلبه — يمنع تجاوز النطاق بتغيير المعرّف في الطلب. */
    protected function authorizeCreatorScope($createdBy): void
    {
        $ids = $this->accessibleUserIds();
        if ($ids !== null && ! in_array((int) $createdBy, $ids, true)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا السجل.');
        }
    }

    /**
     * نسخةٌ لا تُوقف الطلب من `authorizeCreatorScope` — لقرارات عرضٍ في
     * الواجهة (كإظهار زرّ التعديل)، لا لحراسة فعلٍ.
     */
    protected function canAccessCreator($createdBy): bool
    {
        $ids = $this->accessibleUserIds();

        return $ids === null || in_array((int) $createdBy, $ids, true);
    }

    /** تقييد استعلامٍ بعمود خزينةٍ (افتراضاً `treasury_id`) وفق هذا النطاق. */
    protected function scopeToTreasury(Builder $query, string $column = 'treasury_id'): Builder
    {
        $ids = $this->accessibleTreasuryIds();
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    /** يوقف الطلب بـ403 إن كانت الخزينة خارج نطاق المستخدم — لسياقات القراءة (GET). */
    protected function authorizeTreasuryAccess(?int $treasuryId): void
    {
        if ($treasuryId === null) {
            return;
        }

        $ids = $this->accessibleTreasuryIds();
        if ($ids !== null && ! in_array($treasuryId, $ids, true)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذه الخزينة.');
        }
    }

    /** يرمي خطأ تحقّقٍ إن كانت الخزينة خارج نطاق المستخدم — لسياقات الكتابة (POST/PUT). */
    protected function assertTreasuryAccess(?int $treasuryId, string $field = 'treasury_id'): void
    {
        if ($treasuryId === null) {
            return;
        }

        $ids = $this->accessibleTreasuryIds();
        if ($ids !== null && ! in_array($treasuryId, $ids, true)) {
            throw ValidationException::withMessages([
                $field => ['لا تملك صلاحية الوصول إلى هذه الخزينة.'],
            ]);
        }
    }

    /**
     * عقدةُ الفرع من الشريط العلويّ التي تُرشَّح عليها قوائمُ هذه الوحدة.
     *
     * كانت الشاشاتُ تمرّر `current_branch_id()` إلى `forBranch()` كما هو،
     * فالفرعُ الرئيسيّ يُطبَّق على الجميع بالطريقة نفسِها. الآن يمرّ على
     * {@see HeaderBranchScope::nodeId()}: الرئيسيّ = المنشأةُ كلُّها لمن يحقّ
     * له ذلك وحدَه، ويُردّ غيرُه إلى فرعه. `null` = بلا قيدِ مكان.
     */
    protected function headerBranchNode(): ?int
    {
        return HeaderBranchScope::nodeId();
    }

    /**
     * فرعٌ مطلوبٌ في فلتر الشاشة، مقصوصاً على نطاق المستخدم — من يطلب
     * فرعاً خارج نطاقه يُردّ إلى جذر نطاقه لا إلى «كل الفروع».
     */
    protected function requestedBranchNode($requested): ?int
    {
        if (! $requested || $requested === 'all') {
            return null;
        }

        return DataScope::clampNode((int) $requested);
    }

    /**
     * فروعُ منسدلةِ الفلتر: الجذورُ لمن لا قيدَ عليه، وإلّا ما يحقّ له
     * اختيارُه وحدَه — لا تُعرض له أسماءُ فروع المنشأة كلِّها.
     */
    protected function branchFilterOptions()
    {
        $allowed = DataScope::selectableBranchIds();

        return $allowed === null
            ? Branch::whereNull('parent_id')->orderBy('id')->get()
            : Branch::whereIn('id', $allowed)->orderBy('name')->get();
    }

    /**
     * هل يجوز لهذا المستخدم اختيار الفرع يدوياً عند الإنشاء؟ الموظف مقفلٌ
     * على فرع سجله الوظيفي دائماً؛ من ليس موظفاً يختار بحرية؛ ومن يملك
     * `data_scope_all` يختار بحرية أيضاً حتى لو كان موظفاً.
     */
    protected function canSelectBranch(): bool
    {
        $user = $this->scopeUser();

        return ! $user->isEmployee() || $user->hasRolePermission('data_scope_all');
    }

    /** فرع الموظف الحالي (من سجله الوظيفي) — `null` لمن ليس موظفاً. */
    protected function currentEffectiveBranchId(): ?int
    {
        $employee = $this->scopeUser()->employee;

        return $employee?->branch_id ? (int) $employee->branch_id : null;
    }

    /**
     * يحسم `branch_id` عند الكتابة: يُقفَل على فرع الموظف دائماً لمن كان
     * موظفاً بصرف النظر عمّا أرسله الطلب، ويُترَك لاختيار من ليس موظفاً
     * (أو يملك `data_scope_all`).
     */
    protected function resolveWriteBranchId(?int $requestedBranchId): ?int
    {
        if (! $this->canSelectBranch()) {
            return $this->currentEffectiveBranchId();
        }

        return $requestedBranchId;
    }
}
