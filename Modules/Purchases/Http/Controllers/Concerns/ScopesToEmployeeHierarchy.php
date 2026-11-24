<?php

namespace Modules\Purchases\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * نطاق الرؤية في وحدة المشتريات — على قياس ما تعمل به الأذون المخزنية
 * (`Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy`).
 *
 * ══════════════════ القاعدة ══════════════════
 *
 *   • من يملك `data_scope_all` يرى كل شيء — تجاوزٌ صريح، حتى لو كان موظفاً
 *     له مديرٌ في التسلسل (كالمدير العام).
 *   • من ليس موظفاً (`employee_id` فارغ) لا يُقيَّد هنا — يُضبط أمرُه
 *     بالصلاحيات وحدها، فبابُ الشاشة هو `permission:` لا هذا النطاق.
 *   • الموظف يرى **بياناته وبيانات كل من يديرهم** مباشرةً أو عبر السلسلة
 *     كاملة (`Employee::getDescendantIds()`).
 *
 * ══════════════════ لماذا لا `Scopes::applyDataScope`؟ ══════════════════
 *
 * تلك تبني الرؤية على صلاحياتٍ هرمية (`data_scope_team/branch/region`)،
 * ونتيجتها أن الموظف لا يرى مرؤوسيه إلا إن مُنح `data_scope_team` صراحةً —
 * وهي اليوم في دور «manager» وحده. فمشرفٌ حقيقيٌّ له مرؤوسون في التسلسل
 * الإداري يبقى يرى سجلّاته هو فقط، ولا يدلّ شيءٌ في الشاشة على السبب.
 *
 * وهذه تقرأ التسلسل الإداري نفسه: من يدير غيره يرى ما تحته بحكم كونه يديره،
 * بلا صلاحيةٍ إضافية تُمنح يدوياً لكل مشرف. وهو سلوك الأذون المخزنية نفسه،
 * فيتّحد معنى «النطاق» بين الوحدتين بدل أن يختلف من شاشةٍ إلى أخرى.
 *
 * `Scopes` تبقى مستعملةً لحسم الفرع عند الكتابة (`canSelectBranch`،
 * `resolveBranchId`) — ذاك سؤالٌ آخر لا علاقة له بمن يرى ماذا.
 */
trait ScopesToEmployeeHierarchy
{
    protected function scopeUser(): User
    {
        return auth()->user();
    }

    /**
     * أنا + كل من يتبعني إدارياً.
     *
     * `null` تعني «بلا قيد»: إمّا لأني أملك `data_scope_all`، أو لأني لست
     * موظفاً أصلاً. وهي تختلف عن المصفوفة الفارغة التي تعني «لا أحد».
     *
     * @return array<int, int>|null
     */
    protected function accessibleEmployeeIds(): ?array
    {
        $user = $this->scopeUser();

        if ($user->hasRolePermission('data_scope_all')) {
            return null;
        }

        if ($this->usesLegacyRoleFallback()) {
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
        return DataScope::documentEmployeeIds();
    }

    /**
     * حسابات المستخدمين (`users.id`) المرتبطة بأولئك الموظفين — فأعمدة
     * المنشئ في مستندات المشتريات تشير إلى `users` لا إلى `employees`.
     *
     * @return array<int, int>|null
     */
    protected function accessibleUserIds(): ?array
    {
        /* نفسُ السلَّم محسوباً على `users` — ولا يُشتقّ من الموظفين هنا:
           في محور المكان (فرع/منطقة) يُحسب على `users.branch_id` مباشرةً،
           فلا يسقط مستخدمٌ في الفرع لا سجلَّ موظفٍ له. */
        return DataScope::documentUserIds();
    }

    /**
     * مديرٌ بلا دورٍ مربوط — يُعامَل معاملةَ من يملك `data_scope_all`.
     *
     * `hasRolePermission()` تقرأ `role_id` وحده وتُرجع `false` فوراً إن كان
     * فارغاً. وقاعدةُ بياناتٍ مستوردة تصل وأغلبُ مستخدميها بلا `role_id`،
     * فمديرُ المنشأة المرتبطُ بموظفٍ يسقط إلى «ما أنشأتُه أنا» — وهو لم
     * يُدخل الفواتير بنفسه، فتفتح له الفواتيرُ والمرتجعاتُ والإشعاراتُ فارغةً
     * على الفرع الرئيسي، وهو من يُفترض أن يرى كلَّ شيء.
     *
     * يُقرأ عمودُ `users.role` النصّي عندها — الدورُ الموروث الذي يكتبه
     * `assignPrimaryRole()` مع `role_id` — ولمديرٍ أو مسؤولٍ وحدهما. ومن له
     * `role_id` يُحكم بصلاحيات دوره كما كان، فلا يوسّع هذا صلاحيةَ أحدٍ رُبط
     * بدوره فعلاً. والشاشةُ تنبّه إليه (`listScopeNotice()`) ليُربط الحساب.
     */
    protected function usesLegacyRoleFallback(): bool
    {
        $user = $this->scopeUser();

        return ! $user->role_id
            && in_array(strtolower(trim((string) $user->role)), ['manager', 'admin', 'owner'], true);
    }

    /**
     * ما يضيّق قائمةَ المستندات الآن — لسطرٍ يُعرض فوقها.
     *
     * ثلاثةُ مرشِّحاتٍ تعمل دون أن يختارها المستخدم: مدى التاريخ، والفرعُ
     * النشط، ونطاقُ الرؤية. شاشةٌ فارغةٌ بلا هذا السطر تُقرأ عطلاً، ومعه
     * تقول سببَها على أيّ خادمٍ فُتحت.
     *
     * @param  array{start: string, end: string, is_default?: bool}  $range
     * @return array{from: string, to: string, is_default: bool, branch: ?string, people: ?int, role_fallback: bool, role: ?string}
     */
    protected function listScopeNotice(array $range): array
    {
        $branchId = current_branch_id();
        // العقدةُ النشطة لا تُقصّ على محور الأشخاص، فلا تُذكَر في السطر:
        // شرطٌ لا أثر له يُقرأ سبباً لشاشةٍ ناقصة — انظر applyHeaderBranchScope()
        $isMain = $this->scopeIsPeopleAxis()
            || (method_exists($this, 'isMainBranch') ? $this->isMainBranch($branchId) : ! $branchId);
        $userIds = $this->accessibleUserIds();

        return [
            'from' => (string) $range['start'],
            'to' => (string) $range['end'],
            'is_default' => (bool) ($range['is_default'] ?? false),
            // من أين جاء المدى الافتراضي — سنةٌ مالية، أو فترة، أو التاريخ كلُّه
            'source' => (string) ($range['source'] ?? 'fiscal_year'),
            // المسارُ من الأعلى لا الاسمُ وحده — «تابع لـ: منطقة جيزان ← فرع بيش»
            'branch' => $isMain ? null : (\App\Models\Branch::pathLabelFor($branchId) ?? '#'.$branchId),
            'people' => $userIds === null ? null : count($userIds),
            'role_fallback' => $this->usesLegacyRoleFallback(),
            'role' => $this->scopeUser()->role,
        ];
    }

    /**
     * تقييد استعلامٍ بعمود منشئ السجل.
     *
     * `?: [0]` مقصود: مجموعةٌ فارغة تعني «لا أحد»، وتركُ `whereIn` بمصفوفةٍ
     * فارغة يجعلها شرطاً كاذباً في بعض المحرّكات وصامتاً في غيرها — فيُصرَّح
     * بمعرّفٍ لا يوجد بدل الاعتماد على ذلك.
     */
    protected function scopeToCreator(Builder $query, string $column = 'created_by'): Builder
    {
        $ids = $this->accessibleUserIds();

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids ?: [0]);
    }

    /**
     * نطاقُ **الموردين**: من أنشأ المورّدَ أو من نُسب إليه (`suppliers.employee_id`).
     *
     * كانت قوائمُ الموردين في الإنشاء والتعديل وشاشةُ الموردين تُقصّ على
     * `created_by` وحدَه، فالمورّدُ الذي يُعيَّن لموظّفٍ من صفحة العرض لا
     * يجده ذلك الموظّفُ في أيّ قائمة — والتعيينُ وُضع لعكس ذلك.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Supplier>  $query
     */
    protected function scopeSuppliers(Builder $query): Builder
    {
        return $this->scopeToPeople($query, 'created_by', 'employee_id', 'employee');
    }

    /**
     * ══════════ محورُ الأشخاص: المنشئُ **أو** المسؤول ══════════
     *
     * العمودان يقولان شيئين مختلفين: `created_by` من كتب المستند،
     * و`employee_id` من يُنسب إليه شراؤه. ومحاسبٌ يُدخل فاتورةً عن مندوب
     * يكتب الأوّل باسمه والثاني باسم المندوب — فالقيدُ على أحدهما وحدَه
     * يُخفي المستندَ عن صاحبه الآخر. وهذا ما تفعله المبيعات
     * ({@see \Modules\Sales\Http\Controllers\Concerns\ScopesSalesVisibility::scopeSalesDocuments()})
     * وكانت المشتريات تقتصر على `created_by`.
     *
     * ولا يُطبَّق على محور المكان: هناك الفرعُ هو القيد، وقد شُرح في
     * {@see \App\Support\DataScope::isPeopleAxis()} لماذا لا يُجمَع
     * المحوران.
     *
     * @param  string  $employeeColumnHolds  ماذا يحمل عمودُ المسؤول:
     *                                       `employee` (`employees.id`) أم `user` (`users.id`)
     */
    protected function scopeToPeople(
        Builder $query,
        string $creatorColumn = 'created_by',
        string $employeeColumn = 'employee_id',
        string $employeeColumnHolds = 'employee'
    ): Builder {
        $userIds = $this->accessibleUserIds();

        if ($userIds === null) {
            return $query;
        }

        $ids = $employeeColumnHolds === 'user'
            ? $userIds
            : (DataScope::documentEmployeeIds() ?? []);

        return $query->where(function ($scope) use ($creatorColumn, $employeeColumn, $userIds, $ids) {
            $scope->whereIn($creatorColumn, $userIds ?: [0])
                ->orWhereIn($employeeColumn, $ids ?: [0]);
        });
    }

    /**
     * ══════════ فلترُ الفرع النشط، ومتى يجب أن يصمت ══════════
     *
     * شاشاتُ المشتريات تقصّ صفوفَها على العقدة المختارة في الشريط العلويّ،
     * والفرعُ الرئيسيّ مستثنىً لأنه مركزُ المنشأة لا فرعٌ من فروعها.
     *
     * وهذا صوابٌ على **محور المكان** (`all`/`branch`/`region`): من يرى
     * فروعاً يختار أيَّها ينظر إليه.
     *
     * أمّا على **محور الأشخاص** (`team`/`own`) فهو حجبٌ لا تصفية: صفوفُ
     * المستخدم مقيَّدةٌ بشخصه وفريقه أصلاً، وفريقُه قد يعمل في فروعٍ غير
     * فرعه — فلا يزيد قيدُ الفرع فوق ذلك إلّا أن يُخفي عنه عملَ فريقه.
     * فيضبط المشرفُ «المدير المباشر» في سجلّات مرؤوسيه ثمّ يقول: «لا أرى
     * إلّا فواتيري» — والنطاقُ يعمل، والفرعُ يحجب. وهي العلّةُ نفسُها التي
     * أُسقِط شرطُها من شاشات المبيعات.
     *
     * يتطلّب `isMainBranch()` من `RespectsAccountingPeriods`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    protected function applyHeaderBranchScope($query, $request = null)
    {
        // بطلبٍ: العقدةُ المختارة في فلتر الشاشة تغلب — بالقاعدة الواحدة أعلاه
        if ($request) {
            return $this->scopeToBranchNode($query, $request);
        }

        if ($this->scopeIsPeopleAxis()) {
            return $query;
        }

        $active = current_branch_id();

        if (method_exists($this, 'isMainBranch') ? $this->isMainBranch($active) : ! $active) {
            return $query;
        }

        return $query->forBranch($active);
    }

    /**
     * «أُنشئ بواسطة» في فلاتر القوائم — من أنشأ مستنداً يظهر في هذه الشاشة فعلاً.
     *
     * كانت `User::all()`: كلُّ مستخدمي المنشأة، ولو وقف المستخدمُ على منطقةٍ
     * لا يعمل فيها أغلبُهم. فتُشتقّ من المستندات نفسها بعد نطاق الرؤية وفرع
     * الشريط العلويّ، فلا تعرض القائمةُ اسماً لا صفَّ له في الجدول.
     */
    protected function creatorOptions(Builder $documents, string $column = 'created_by')
    {
        $documents = $this->scopeToCreator($documents, $column);

        /* `HeaderPickers` لا `applyHeaderBranchScope()`: الثانيةُ تحتاج
           `isMainBranch()` من `RespectsAccountingPeriods`، وبدونها ترشّح
           على الفرع الرئيسيّ نفسِه. ويصمت على محور الأشخاص كمثيلتها. */
        if (! $this->scopeIsPeopleAxis()) {
            \Modules\Purchases\Support\HeaderPickers::constrain($documents);
        }

        $ids = $documents->whereNotNull($column)->distinct()->pluck($column);

        // وأقسامُ موظّفي المشتريات من الإعدادات — إن ضُبطت
        return \Modules\Purchases\Support\HeaderPickers::constrainUsersToDepartments(User::whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }

    /** أنطاقُه محورُ أشخاصٍ (فريقُه أو هو وحدَه) لا محورُ مكان؟ */
    protected function scopeIsPeopleAxis(): bool
    {
        return $this->accessibleUserIds() !== null && DataScope::isPeopleAxis();
    }

    /**
     * حماية سجلٍّ واحد بعد جلبه — يمنع تجاوز النطاق بتبديل المعرّف في الرابط.
     *
     * تقييدُ القوائم وحده لا يكفي: من يعرف رقم مستندٍ خارج نطاقه يبلغه
     * بكتابته في العنوان، فالقائمة تُخفي ولا تمنع.
     */
    protected function authorizeCreatorScope($createdBy, $employeeId = null, $branchId = null): void
    {
        if (! $this->canAccessCreator($createdBy, $employeeId, $branchId)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا السجل.');
        }
    }

    /**
     * نسخةٌ لا تُوقف الطلب — لمن يحتاج استجابةً غير 403 (JSON أو تحويل برسالة).
     *
     * المحورُ نفسُه الذي تقصّ به القائمةُ صفوفَها: على محور المكان يُقاس
     * فرعُ المستند، وعلى محور الأشخاص منشئُه أو المسؤولُ عنه. وكان القياسُ
     * على `created_by` وحده في الحالتين — فمديرُ فرعٍ يفتح من قائمته
     * مستنداً كتبه غيرُه في فرعه فيُردّ بـ403، والمستندُ في نطاقه.
     *
     * و`employee_id`/`branch_id` اختياريان: من ناداها بحجّةٍ واحدة — وهي
     * أغلبُ المواضع — يُقاس بالمنشئ كما كان، ولا يتوسّع نطاقُ أحد بالسكوت.
     */
    protected function canAccessCreator($createdBy, $employeeId = null, $branchId = null): bool
    {
        $ids = $this->accessibleUserIds();

        if ($ids === null) {
            return true;
        }

        if (! $this->scopeIsPeopleAxis()) {
            $branchIds = DataScope::branchIds();

            if ($branchIds !== null && $branchId !== null) {
                return in_array((int) $branchId, $branchIds, true);
            }
        }

        if (in_array((int) $createdBy, $ids, true)) {
            return true;
        }

        if ($employeeId === null) {
            return false;
        }

        return in_array((int) $employeeId, DataScope::documentEmployeeIds() ?? [], true);
    }

    /**
     * تقييدُ الشاشة بـ«تابع لـ»: العقدةُ المختارة في الشاشة، وإلّا العقدةُ
     * النشطة من الهيدر — والعقدةُ تعني هي وكلَّ ما تحتها (`forBranch`).
     *
     * **أربعُ شاشاتٍ في المشتريات لم تكن تقرأ الفرع إطلاقاً**: أوامر
     * الشراء، وعروض الأسعار، وطلبات عروض الأسعار، وطلبات الشراء. فمن يقف
     * على «منطقة جيزان» يرى أوامرَ المنشأة كلِّها، ومبدّلُ الهيدر لا أثر
     * له في شاشاتها. وهي تحمل `branch_id` على مستنداتها مثل بقيّة
     * المستندات، فما نقصها إلّا القراءة.
     *
     * والفرعُ الرئيسيّ مستثنىً: مركزُ المنشأة يرى مستنداتِها كلَّها، ولا
     * يُبنى له شرطٌ لا أثر له.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function scopeToBranchNode(Builder $query, $request = null): Builder
    {
        /* اختيارُ الشاشة يغلب العقدةَ النشطة: المستخدم طلب عقدةً بعينها —
           إن كانت في نطاقه. المعرّفُ يصل في الطلب، فصاحبُ منطقةٍ كان يقرأ
           منطقةً أخرى بتبديله في العنوان؛ عقدةٌ خارج النطاق تُتجاهَل ويُعاد
           إلى القاعدة أدناه. */
        if ($request && $request->filled('branch')) {
            $node = (int) $request->input('branch');
            $allowed = DataScope::selectableBranchIds();

            if ($allowed === null || in_array($node, $allowed, true)) {
                return $query->forBranch($node);
            }
        }

        /* العقدةُ النشطة تصمت على محور الأشخاص — انظر
           {@see applyHeaderBranchScope()} لتفصيل السبب. والاختيارُ الصريح
           أعلاه يبقى: ذاك طلبُ المستخدم لا قيدٌ يُفرض عليه. */
        if ($this->scopeIsPeopleAxis()) {
            return $query;
        }

        $active = current_branch_id();

        if (! $active || \App\Models\Branch::where('id', $active)->value('is_main')) {
            return $query;
        }

        return $query->forBranch($active);
    }
}
