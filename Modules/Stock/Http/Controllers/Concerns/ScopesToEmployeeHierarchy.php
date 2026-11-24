<?php

namespace Modules\Stock\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\StoreHouse;
use App\Models\User;
use App\Support\DataScope;
use App\Support\HeaderBranchScope;
use App\Support\StorehouseAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * نطاق الرؤية طبقتان تتقاطعان:
 *
 *   ١. **سياقُ الفرع** — الفرع المختار في الشريط العلوي (`users.branch_id`،
 *      يكتبه `BranchesController::switchBranch`). كان التبديل بلا أثرٍ
 *      إطلاقاً على المخزون: يختار المستخدم فرعاً فتبقى الشاشات كما هي،
 *      فيقرأ أرصدةَ فرعٍ ويظنّها أرصدة آخر. وهو **سياقٌ لا صلاحية**:
 *      يسري على الجميع، حتى مالكِ `data_scope_all` — من اختار فرعاً فقد
 *      طلب بياناته هو. ومن لا فرع له (`branch_id` فارغ = «كل الفروع»)
 *      لا يُقيَّد، ومن يقف على الفرع الرئيسيّ (`is_main`) كذلك — فهو
 *      يعني المنشأةَ كلَّها بالاصطلاح (انظر `activeBranchIds()`).
 *
 *   ٢. **نطاقُ الموظف** — ربطٌ فعليّ موظفٍ↔مستودع (`default_warehouses`)
 *      + تسلسلٌ إداري (`Employee::direct_manager_id`).
 *
 * والوصلُ بينهما `store_houses.branch_id`: هو العمود الوحيد الذي يربط
 * المخزون بالفروع. فالأذون والأرصدة والجرد تُنسب إلى فرعها عبر مستودعها،
 * أمّا كتالوج الأصناف والتصنيفات فلا عمود فرعٍ فيه — فيبقى مشتركاً كما هو،
 * لأن المطلوب تصفيةُ ما يرتبط بفرعٍ لا اختراعُ ارتباطٍ لما لا يرتبط.
 *
 * ولا نطاقات
 * `data_scope_own/team/branch/region` الهرمية القديمة (`DataScopeService`،
 * المحذوفة). صلاحية `data_scope_all` وحدها بقيت — من كتالوج
 * `App\Constants\permissions\DataScopePermissions` المشترك بين الوحدات (يستعمله موديول
 * المشتريات أيضاً عبر `App\Support\Scopes`، فلا تُحذف من هناك) — وهي هنا
 * تجاوزٌ استثنائي صريح: من يملكها يرى كل بيانات المخزون بلا قيدٍ إطلاقاً،
 * حتى لو كان موظفاً له مديرٌ في التسلسل الإداري (كالمدير العام).
 *
 * موظفٌ عاديّ يرى بياناته هو فقط. من يدير غيره (له مرؤوسون في التسلسل
 * الإداري) يرى بياناته وبيانات كل من يديرهم مباشرة أو عبر السلسلة كاملة.
 * مستخدمٌ ليس موظفاً (`employee_id` فارغ) يرى كل شيء بلا قيدٍ هنا — يُضبط
 * أمره بالصلاحيات وحدها.
 */
trait ScopesToEmployeeHierarchy
{
    /*
        ذاكرةُ الطلب الواحد: `accessibleWarehouseIds()` تُستدعى عشرات المرّات
        في الصفحة الواحدة (كل حارسٍ وكل قائمة)، و`false` تعني «لم يُحسب بعد»
        تمييزاً لها عن `null` التي تعني «بلا قيد» وهي نتيجةٌ مشروعة.
    */
    private array|null|false $activeBranchIdsCache = false;

    private array|null|false $branchWarehouseIdsCache = false;

    protected function scopeUser(): User
    {
        return auth()->user();
    }

    /**
     * أنا + كل من يتبعني إدارياً (`Employee::getDescendantIds()`).
     * `null` = بلا قيدٍ هنا — إمّا لأني أملك `data_scope_all` (تجاوزٌ صريح)
     * أو لأني لست موظفاً أصلاً.
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

        /* ── والمشرفُ يرى فريقه مهما ضاق نطاقُ دوره (قرار صاحب النظام) ──
           السلَّم يوسّع (فرع/منطقة) ولا يُضيِّق دون «أنا ومن أشرف عليهم»:
           مشرفٌ دورُه «مندوب مبيعات» بنطاق `own` كان لا يرى في الكشف
           المخزني وقوائم المخزون إلا مستودعيه، ومستودعاتُ من يشرف عليهم
           غائبة. */
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
     * الفرعُ المختار في الشريط العلوي وتوابعه في الشجرة — `null` = «كل الفروع».
     *
     * التوابعُ معه عمداً: من اختار «منطقة» أو «مجموعة» أراد ما تحتها، ولو
     * اقتصر الأمر على العقدة نفسها لخرج المستخدم بشاشةٍ فارغة كلما اختار
     * عقدةً تنظيمية لا مستودع لها.
     *
     * **والفرعُ الرئيسيّ (`is_main`) يعني المنشأةَ كلَّها، لا شجرتَه.**
     * كان يُعامَل كأيّ عقدة: فمن يقف عليه — وهو الوضعُ الافتراضيّ لأكثر
     * المستخدمين — كان يرى في الإذن المخزني مستودعاً واحداً من اثنين
     * وثلاثين، لأنّ الرئيسيّ لا يتبعه إلّا فرعٌ واحد وتسعةً وعشرين مستودعاً
     * لا فرعَ لها إطلاقاً. والقاعدةُ نفسُها التي تعمل بها المبيعات
     * والمشتريات ({@see HeaderBranchScope::nodeId()}): لا
     * فرعَ مختاراً أو الرئيسيّ ⇒ لا قيدَ فرعٍ على المستودعات.
     */
    protected function activeBranchIds(): ?array
    {
        if ($this->activeBranchIdsCache !== false) {
            return $this->activeBranchIdsCache;
        }

        $branchId = HeaderBranchScope::nodeId($this->scopeUser()->branch_id ? (int) $this->scopeUser()->branch_id : null);

        if (! $branchId) {
            return $this->activeBranchIdsCache = null;
        }

        $branch = Branch::find((int) $branchId);

        if (! $branch) {
            return $this->activeBranchIdsCache = null;
        }

        return $this->activeBranchIdsCache = $branch->descendantBranchIds()
            ->push($branch->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** مستودعاتُ الفرع المختار وتوابعه — `null` = بلا قيد فرع. */
    protected function branchWarehouseIds(): ?array
    {
        $branchIds = $this->activeBranchIds();

        if ($branchIds === null) {
            return null;
        }

        if ($this->branchWarehouseIdsCache !== false) {
            return $this->branchWarehouseIdsCache;
        }

        return $this->branchWarehouseIdsCache = StoreHouse::whereIn('branch_id', $branchIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * المستودعات التي يجوز لهذا المستخدم رؤيتها الآن: تقاطعُ نطاقه الوظيفي
     * مع الفرع المختار **مع صلاحية «عرض المستودع» المضبوطة على المستودع
     * نفسه**. `null` = بلا قيدٍ من الطبقات كلِّها.
     */
    protected function accessibleWarehouseIds(): ?array
    {
        $base = $this->scopeAccessibleWarehouseIds();
        $viewable = $this->storehouseViewableIds();

        if ($viewable === null) {
            return $base;
        }

        if ($base === null) {
            return $viewable;
        }

        return array_values(array_intersect($base, $viewable));
    }

    /** طبقتا النطاق الوظيفي والفرع — قبل صلاحية المستودع نفسه. */
    private function scopeAccessibleWarehouseIds(): ?array
    {
        $branchIds = $this->branchWarehouseIds();
        $employeeIds = $this->accessibleEmployeeIds();

        if ($employeeIds === null) {
            // لا قيدَ وظيفيّ: يبقى قيدُ الفرع وحده (أو لا قيد إطلاقاً)
            return $branchIds;
        }

        $byEmployee = DefaultWarehouses::whereIn('employee_id', $employeeIds)
            ->pluck('storehouse_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($branchIds === null) {
            return $byEmployee;
        }

        // التقاطع: مستودعٌ في نطاقي **وفي** الفرع الذي أنظر إليه الآن
        return array_values(array_intersect($byEmployee, $branchIds));
    }

    private array|null|false $storehouseViewableIdsCache = false;

    /**
     * صلاحية «عرض المستودع» المضبوطة في شاشة المستودع نفسها
     * (`view_permissions`/`value_of_view_permissions`: الكل، موظف بعينه،
     * دور، فرع) — كانت تُحفَظ **ولا يقرؤها أحد**، فمستودعٌ ضُبط عرضُه على
     * موظفٍ بعينه تظهر كميّاتُه للجميع.
     *
     * صارت طبقةً ثالثة فوق النطاق الوظيفي والفرع، في نقطة الاختناق التي
     * تمرّ بها كل شاشات الكميات والأذون والجرد والتقارير. والحكم حكم
     * `StorehouseAccess` نفسه: مستودعٌ بلا ضبطٍ («الكل» أو قيمة فارغة)
     * مفتوحٌ كما كان، وغيرُ الموظف (مدير النظام) لا يُحجب — `null`.
     */
    protected function storehouseViewableIds(): ?array
    {
        if ($this->storehouseViewableIdsCache !== false) {
            return $this->storehouseViewableIdsCache;
        }

        $user = $this->scopeUser();

        /*
            بلا قيدٍ لاثنين — بقرار صاحب النظام:
              • غيرُ الموظف: يدير النظام ولا يعمل فيه.
              • حاملُ `data_scope_all`: التجاوزُ الصريح يعلو حتى على ضبطِ
                «عرض المستودع» في المستودع نفسه — من مُنح «كل البيانات»
                يرى كلَّ المستودعات.
        */
        if (! $user || ! $user->employee_id || $user->hasRolePermission('data_scope_all')) {
            return $this->storehouseViewableIdsCache = null;
        }

        // لا مستودعَ في النظام يحمل ضبطَ عرضٍ مقيِّداً؟ لا داعي لقائمةٍ ولا تقاطُع
        $anyRestricted = StoreHouse::whereNotNull('view_permissions')
            ->where('view_permissions', '!=', StorehouseAccess::AUDIENCE_ALL)
            ->whereNotNull('value_of_view_permissions')
            ->where('value_of_view_permissions', '!=', '')
            ->exists();

        if (! $anyRestricted) {
            return $this->storehouseViewableIdsCache = null;
        }

        return $this->storehouseViewableIdsCache = StorehouseAccess::scopeFor('view', StoreHouse::query(), $user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * تقييدُ استعلامٍ يحمل أكثر من عمود مستودع — كأذون المخزن: عمودُ
     * المستودع، وعمودا «من» و«إلى» في التحويل. يمرّ الصفُّ إن كان أيُّ
     * عمودٍ منها داخل النطاق.
     */
    protected function scopeToWarehouseColumns(Builder $query, array $columns = ['store_houses_id', 'from_store_houses_id', 'to_store_houses_id']): Builder
    {
        $ids = $this->accessibleWarehouseIds();

        if ($ids === null) {
            return $query;
        }

        $ids = $ids ?: [0];

        return $query->where(function ($q) use ($columns, $ids) {
            foreach ($columns as $column) {
                $q->orWhereIn($column, $ids);
            }
        });
    }

    /**
     * استعلامُ المستودعات التي يجوز لهذا المستخدم رؤيتها.
     *
     * **ولماذا دالّةٌ لا سطرٌ يُكرَّر؟**
     *
     * الشرطُ كان يُكتب في كلّ شاشة، وفيه فخٌّ لا يُرى:
     *
     * ```php
     * StoreHouse::when($this->accessibleWarehouseIds(), fn ($q, $ids) => ...)
     * ```
     *
     * و`when()` تختبر **الصدق** لا الوجود. والمصفوفةُ الفارغة كاذبة في PHP —
     * فموظفٌ **لا مستودعَ له** لا يُطبَّق عليه الشرطُ أصلاً، **فيرى المستودعات
     * كلَّها**: عكسُ المقصود بالضبط، وأخطرُ حالاته أنّ من لا يملك شيئاً يرى
     * كلَّ شيء.
     *
     * والفرقُ بين «بلا قيد» و«لا شيء» يُحسم هنا مرّةً واحدة:
     *
     *   • `null`  ⇒ بلا قيد — `data_scope_all` أو ليس موظفاً.
     *   • `[]`    ⇒ لا مستودعَ له، فلا يُعرض شيء.
     *   • غيرهما ⇒ مستودعاته ومستودعات من يديرهم.
     */
    protected function accessibleWarehouses(array $columns = ['id', 'name'])
    {
        $ids = $this->accessibleWarehouseIds();

        $query = StoreHouse::query()->select($columns)->orderBy('name');

        if ($ids === null) {
            return $query;
        }

        // `[0]` لا يوافق معرّفاً حقيقياً، فالنتيجةُ فارغةٌ عن قصد
        return $query->whereIn('id', $ids ?: [0]);
    }

    /**
     * تقييدُ استعلام منتجاتٍ بنطاق رؤية المستخدم.
     *
     * كانت هذه القاعدةُ خاصّةً بمتحكّم المنتجات وحده، فكلُّ شاشةٍ أخرى تعرض
     * منتجاتٍ — قائمةُ الأسعار، وصفحةُ البراند، وعدّادُ الفئة — تعرضها بلا
     * قيد. وهي مراتبُ ثلاث:
     *
     *   ١. **بلا قيد** — `data_scope_all` أو ليس موظفاً.
     *   ٢. **بمستودعاته** — عبر `product_details.store_house_id`، ويشمل
     *      مستودعات من يديرهم.
     *   ٣. **بما أنشأه** — حين لا مستودعَ له أصلاً.
     *
     * @param  Builder  $query  استعلامُ `Product`
     * @param  string  $relation  اسمُ علاقة تفاصيل المخزون على المودل
     */
    protected function scopeToProductVisibility(Builder $query, string $relation = 'productDetails'): Builder
    {
        $warehouseIds = $this->accessibleWarehouseIds();

        if ($warehouseIds === null) {
            return $query;
        }

        if ($warehouseIds !== []) {
            return $query->whereHas($relation, fn ($q) => $q->whereIn('store_house_id', $warehouseIds));
        }

        return $this->scopeToCreator($query);
    }

    /** تقييد استعلامٍ بعمود منشئ السجل (`created_by`) وفق هذا النطاق. */
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
     * نسخةٌ لا تُوقف الطلب من `authorizeCreatorScope` — لمن يحتاج استجابةً
     * مختلفة عن 403 القياسية (JSON، أو إعادة توجيهٍ مع رسالة) عند الرفض.
     */
    protected function canAccessCreator($createdBy): bool
    {
        $ids = $this->accessibleUserIds();

        return $ids === null || in_array((int) $createdBy, $ids, true);
    }

    /** يوقف الطلب بـ403 إن كان المستودع خارج نطاق المستخدم — لسياقات القراءة (GET). */
    protected function authorizeWarehouseAccess(?int $warehouseId): void
    {
        if ($warehouseId === null) {
            return;
        }

        $ids = $this->accessibleWarehouseIds();
        if ($ids !== null && ! in_array($warehouseId, $ids, true)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا المستودع.');
        }
    }

    /** يرمي خطأ تحقّقٍ إن كان المستودع خارج نطاق المستخدم — لسياقات الكتابة (POST/PUT). */
    protected function assertWarehouseAccess(?int $warehouseId, string $field = 'store_houses_id'): void
    {
        if ($warehouseId === null) {
            return;
        }

        $ids = $this->accessibleWarehouseIds();
        if ($ids !== null && ! in_array($warehouseId, $ids, true)) {
            throw ValidationException::withMessages([
                $field => ['لا تملك صلاحية الوصول إلى هذا المستودع.'],
            ]);
        }
    }

    /** تقييد استعلامٍ بعمود مستودعٍ (افتراضاً `store_house_id`) وفق هذا النطاق. */
    protected function scopeToWarehouse(Builder $query, string $column = 'store_house_id'): Builder
    {
        $ids = $this->accessibleWarehouseIds();
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    /**
     * أسماء بديلة توافقاً مع الاستدعاءات القديمة من زمن `AppliesDataScope`
     * (`DataScopeService`) — نفس التوقيع، تُنفَّذ الآن عبر هذا النطاق
     * (`default_warehouses`/التسلسل الإداري) بدل الفرع/نطاقات `data_scope_*`.
     */
    protected function allowedWarehouseIds(): ?array
    {
        return $this->accessibleWarehouseIds();
    }

    protected function authorizeWarehouseScope(?int $warehouseId): void
    {
        $this->authorizeWarehouseAccess($warehouseId);
    }

    protected function assertWarehouseScope(int $warehouseId, string $field = 'store_houses_id'): void
    {
        $this->assertWarehouseAccess($warehouseId, $field);
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
     * موظفاً بصرف النظر عمّا أرسله الطلب، ويُترَك لاختيار من ليس موظفاً.
     */
    protected function resolveWriteBranchId(?int $requestedBranchId): ?int
    {
        if (! $this->canSelectBranch()) {
            return $this->currentEffectiveBranchId();
        }

        return $requestedBranchId;
    }
}
