<?php

namespace Modules\Client\Http\Controllers\Concerns;

use App\Helpers\ClientSettingsHelper;
use App\Models\Branch;
use App\Models\CustomerTarget;
use App\Models\Employee;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * فلاترُ تقارير العملاء — تعريفٌ واحدٌ لخمسة تقارير.
 *
 * ══════════ لماذا جُمِعت ══════════
 *
 * كان لكلّ تقريرٍ نسخةٌ من الفلاتر نفسِها، وتختلف النسخُ فيما لا ينبغي أن
 * تختلف فيه:
 *
 *   • فلترُ الفرع في «الأرصدة» يقبل العميلَ إن كان حسابُه أو حيُّه تحت
 *     الفرع، وفي «الأعمار» يقبل `clients.branch_id` وحده، وفي «مديونية
 *     الفواتير» يقبل فرعَ موظّف الفاتورة أيضاً. فالفرعُ الواحد يُخرج ثلاثةَ
 *     مجاميعَ مختلفة في ثلاث شاشات.
 *   • واسمُ حقل العميل نفسِه كان `client_id` في كشف الحساب و`customer`
 *     في البقية، فمن نسخ رابطاً من شاشةٍ إلى أخرى فقد الفلتر بلا إنذار.
 *
 * صار التعريفُ هنا وحدَه، ويُستدعى من الخمسة على قاعدة العميل نفسِها
 * (`whereHas('client', …)`) مهما كان جدولُ التقرير الأصلي — حساباتٍ كان أو
 * فواتير — فيُنتج الفلترُ الواحد نفسَ مجموعة العملاء في كلّ شاشة.
 *
 * ══════════ «هدفُ العميل» ══════════
 *
 * `clients.customer_target_id` ← `customer_targets` (`name`, `color`).
 * وهو موجودٌ في المنشأة على كلّ العملاء بلا استثناء (٣٨٥٢/٣٨٥٢)، ولم يكن
 * يُعرض ولا يُفلتَر به في أيّ تقريرٍ من الستّة قبل هذا التعديل.
 */
trait FiltersCustomerReports
{
    /**
     * تطبيقُ كلّ فلاتر العميل على استعلامِ عملاء.
     *
     * تُستدعى مباشرةً على `Client::query()`، أو داخل
     * `whereHas('client', fn ($q) => $this->applyClientFilters($q, $request))`
     * لأيّ تقريرٍ قاعدتُه جدولٌ آخر — والنتيجةُ واحدةٌ في الحالتين.
     *
     * @param  Builder<\App\Models\Client>  $query
     */
    protected function applyClientFilters(Builder $query, Request $request): void
    {
        // ── العميل ─────────────────────────────────────────────────
        // اسمانِ لحقلٍ واحد عبر الشاشات؛ يُقبلان معاً كي لا يضيع الفلتر
        // عند نقل رابطٍ من شاشةٍ إلى أختها.
        $clientId = $request->input('customer', $request->input('client_id'));

        if ($clientId !== null && $clientId !== '') {
            $query->whereKey($clientId);
        }

        // ── الفرع ووحدات الشجرة ────────────────────────────────────
        $this->applyOrganizationFilter($query, $request);

        // ── حالة العميل ────────────────────────────────────────────
        if ($request->filled('status')) {
            $query->where('status_id', $request->input('status'));
        }

        /* ── وضع العميل (مرحلة التسجيل) ─────────────────────────────

           حقلٌ مستقلٌّ تماماً عن الحالة: الحالة نشط/موقوف
           (`clients.status_id`)، والوضعُ مرحلةُ تسجيله
           (`clients.registration_stage_id`) من نفس جدول `statuses` لكن
           بقسم `registration_stage`. الخلطُ بينهما يجعل فلترَ «موقوف»
           يبتلع «تحت المراجعة».

           و`no_stage` قيمةٌ صريحة لمن لا وضعَ له — والفارغةُ تعني الكلّ. */
        if ($request->filled('registration_stage')) {
            $stage = $request->input('registration_stage');

            $stage === 'no_stage'
                ? $query->whereNull('registration_stage_id')
                : $query->where('registration_stage_id', $stage);
        }

        // ── هدف العميل ─────────────────────────────────────────────
        // `no_target` قيمةٌ صريحة: «بلا هدف» سؤالٌ مشروعٌ لا يمكن التعبيرُ
        // عنه بخانةٍ فارغة، لأنّ الفارغةَ تعني «كلّ الأهداف».
        if ($request->filled('customer_target_id')) {
            $target = $request->input('customer_target_id');

            $target === 'no_target'
                ? $query->whereNull('customer_target_id')
                : $query->where('customer_target_id', $target);
        }

        // ── تصنيف العميل ───────────────────────────────────────────
        $category = $request->input('category_client', $request->input('category'));

        if ($category !== null && $category !== '') {
            $query->where('category_id', $category);
        }

        // ── الحي ───────────────────────────────────────────────────
        if ($request->filled('neighborhood')) {
            $query->whereHas('neighborhood', function ($q) use ($request) {
                $q->where('name', trim((string) $request->input('neighborhood')));
            });
        }

        // ── مسؤول المبيعات ─────────────────────────────
        /* القيمةُ معرّفُ **موظّف** (`employees.id`) — وهو ما يحمله
           `clients.employee_id` نفسُه، فالمطابقةُ مباشرة.

           كانت القائمةُ تُبنى من `users` ثمّ يُترجَم المستخدمُ إلى ملفّه
           الوظيفي هنا، فيسقط من القائمة كلُّ موظّفٍ بلا حساب دخول — وهو
           حالُ أكثر المندوبين — ويظهر فيها كلُّ مستخدمٍ إداريٍّ لا يُسنَد
           إليه عميلٌ أصلاً. صارت من `employees` مقصورةً على الأقسام
           المسموحة في «إعدادات وحدات العملاء» عبر
           `salesEmployeeOptions()`. */
        /* **وصار الحقلُ الوحيدَ للموظف**، ويطابق **بتعريف لوحة العملاء**
           (2026-09-16): العملاءُ الذين الموظفُ من «الموظفين المسؤولين» عنهم
           في جدول الإسناد `client_employee` — نفسُ فلتر «الموظف» في
           `CatagroiyClientController::dashboardScope()`.

           كان يطابق `clients.employee_id` أو من أضاف العميل، فالموظفُ نفسُه
           يعطي في اللوحة 144 مديناً وفي التقرير صفراً — الإسنادُ الفعليُّ
           محفوظٌ في جدول الإسناد لا في ذلك العمود. */
        if ($request->filled('sales_manager')) {
            $query->whereIn(
                'clients.id',
                \Illuminate\Support\Facades\DB::table('client_employee')
                    ->where('employee_id', (int) $request->input('sales_manager'))
                    ->select('client_id')
            );
        }

        // ── من أنشأ العميل — لم يعد في الشاشات؛ يبقى لروابط محفوظة ────
        if ($request->filled('added_by')) {
            $query->where('user_id', $request->input('added_by'));
        }
    }

    /**
     * فلترُ «تابع لـ» — الجهةُ التي يتبع لها العميل في الشجرة.
     *
     * ══════════ لماذا شجرةٌ واحدة لا شجرةٌ وقائمةُ فروع ══════════
     *
     * كان في كلّ تقريرٍ مُنتقيانِ لسؤالٍ واحد: قائمةُ «الفرع» المسطّحة،
     * وشجرةٌ متدرّجةٌ تحتها. وكانا يتنازعان بقاعدةٍ خفيّة (`hasHierarchyFilter`)
     * تُبطل أحدَهما صامتاً، فيرى المستخدمُ فرعاً مختاراً في الشاشة ولا أثرَ له
     * في النتيجة. بقيت الشجرةُ وحدَها.
     *
     * ══════════ ولماذا `Region_groub` لم تكن جدولاً ثانياً أصلاً ══════════
     *
     * `App\Models\Region_groub` و`App\Models\Branch` يقرآن **نفس الجدول**
     * (`branches`). فالشجرةُ القديمة كانت تدمج الجدولَ بنفسه ببادئتَي `b_`
     * و`g_`، ثمّ تحتاج منطقَ إزالةِ تكرارٍ بالاسم والمستوى لتُخفي ما ولّدته
     * هي. صارت عقدةً واحدةً بمعرّفها، ونوعُها من `branches.node_type`
     * (فرع/منطقة/مجموعة/منفذ).
     *
     * ══════════ والمطابقةُ عبر `Client::scopeInTreeNode` ══════════
     *
     * هو الموضعُ الوحيد المعتمَد لهذا السؤال في النظام: يقرأ
     * `clients.tree_node_id` (مضبوطٌ على ٣٨٥٠ من ٣٨٥٢ عميلاً هنا)، ويرجع
     * إلى `branch_id` والحيّ للصفوف التي سبقت العمود. وكانت التقاريرُ تقرأ
     * `branch_id` وحدَه — وهو الفرعُ **المحاسبي** المشتقّ آلياً، فعميلُ
     * منطقةٍ يُنسب إلى الفرع الذي فوقها ويختفي عمّن يفلتر بمنطقته.
     *
     * @param  Builder<\App\Models\Client>  $query
     */
    protected function applyOrganizationFilter(Builder $query, Request $request): void
    {
        /* ══════════ نطاقُ الرؤية أوّلاً، وبلا شرط ══════════

           كان الحارسُ كلُّه معلَّقاً على وجود عقدةٍ في الطلب: لا عقدةَ ⇒
           `return` ⇒ **لا فلترَ إطلاقاً**. فأيُّ مستخدمٍ يفتح تقارير
           العملاء بلا معاملاتٍ في الرابط يقرأ عملاءَ المنشأة كلَّهم —
           وهي الحالةُ الافتراضية، أي أوّلُ ما يراه عند فتح الشاشة.

           والعقدةُ المطلوبة نفسُها كانت تُقبل كما جاءت: `requestedTreeNodeId()`
           تُعيد ما في الطلب بلا مقارنةٍ بفرع المستخدم، فتبديلُ `tree_node`
           في شريط العنوان يفتح أيَّ منطقةٍ في المنشأة.

           فالنطاقُ يُطبَّق هنا في الحالين، والعقدةُ تُقصّ عليه. */
        $this->applyClientVisibilityScope($query);

        $node = $this->reportTreeNodeId($request);

        if (! $node) {
            return;
        }

        /* «شامل ما تحته» هو الافتراض: من اختار فرعاً يقصد ما تحته من
           مناطقَ ومنافذ، وإلّا اختفى عملاؤها. والمفتاحُ يعطي المطابقةَ
           التامّة لمن أرادها. */
        $query->inTreeNode($node, ! $request->has('exact_node') || ! $request->boolean('exact_node'));
    }

    /**
     * قصرُ استعلامِ عملاءَ على ما يحقّ للمستخدم رؤيتُه.
     *
     * ══════════ المحوران ══════════
     *
     *   • `branch`/`region` ⇒ قيدُ **مكان** على تبعيّة العميل، عبر
     *     {@see AffiliationTree::constrainClients()} — وهو نفسُه الذي
     *     يطابق به فهرسُ العملاء، فلا تفترق الشاشتان. والمطابقةُ على
     *     التبعيّة لا على `clients.branch_id` المشتقّ آلياً، وإلّا سقط
     *     عميلُ المنطقة من تقرير منطقته.
     *   • `own`/`team`      ⇒ قيدُ **أشخاص** على `clients.employee_id`،
     *     وهو مندوبُ العميل. ولا يُجمع مع قيد المكان: عميلٌ في فرع المدير
     *     بلا مندوبٍ مُسنَد يسقط لو جُمعا، وهم كثرةٌ في هذه المنشأة.
     *
     * @param  Builder<\App\Models\Client>  $query
     */
    protected function applyClientVisibilityScope(Builder $query): void
    {
        if (DataScope::isUnrestricted()) {
            return;
        }

        if (DataScope::isPeopleAxis()) {
            /* عميلُ المندوب: مندوبُه في `clients.employee_id`، **أو** هو من
               «الموظفين المسؤولين» عنه في جدول الإسناد `client_employee` —
               وهناك يُحفظ الإسنادُ فعلاً، ومنه تعدّ لوحةُ العملاء وفلترُ
               «الموظف» فيها. كان العمودُ وحدَه، فمندوبُ أبها (١٩٦ عميلاً
               مديناً في اللوحة) يفتح تقريرَ الأرصدة وأعمار الديون صفراً. */
            $employeeIds = DataScope::employeeIds() ?? [0];
            $table = $query->getModel()->getTable();

            $query->where(function ($q) use ($employeeIds, $table) {
                $q->whereIn($table.'.employee_id', $employeeIds ?: [0])
                    ->orWhereIn($table.'.id', \Illuminate\Support\Facades\DB::table('client_employee')
                        ->whereIn('employee_id', $employeeIds ?: [0])
                        ->select('client_id'));
            });

            return;
        }

        AffiliationTree::constrainClients($query, DataScope::branchIds() ?? [0]);
    }

    /**
     * العقدةُ النشطة من مبدِّل الفروع في الهيدر — `null` حين لا تقيّد شيئاً.
     *
     * نفسُ قاعدة المشتريات ({@see \Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy::scopeToBranchNode()}):
     * لا فرعَ مختاراً أو الفرعُ الرئيسيّ ⇒ لا قيد، وإلّا العقدةُ وما تحتها.
     * ومقصوصةٌ على نطاق الرؤية كأيّ عقدةٍ مطلوبة.
     */
    protected function headerTreeNodeId(): ?int
    {
        $active = current_branch_id();

        if (! $active || Branch::where('id', $active)->value('is_main')) {
            return null;
        }

        return DataScope::clampNode($active);
    }

    /**
     * العقدةُ التي يُقصر عليها التقرير — المطلوبةُ، وإلّا فرعُ الشريط العلويّ.
     *
     * ══════════ ما كان ══════════
     *
     * كانت العقدةُ المطلوبةُ وحدَها تُقرأ، ففتحُ أيّ تقريرٍ من الثمانية بلا
     * اختيارٍ في «تابع لـ» يعرض عملاءَ المنشأة كلَّها ولو كان الشريطُ على
     * «منطقة الرياض» — تبديلُ الفرع في الشريط لم يكن يغيّر فيها رقماً.
     * وحده تقريرُ الأهداف كان يقرأ الشريط.
     *
     * ══════════ القواعد ══════════
     *
     *   • لا عقدةَ في الطلب ⇒ عقدةُ الشريط ({@see headerTreeNodeId()}).
     *   • عقدةٌ خارج شجرة الشريط ⇒ تُردّ إلى عقدة الشريط: المُنتقي لا يعرضها،
     *     فلا تصل إلّا برابطٍ محفوظٍ من فرعٍ آخر.
     *   • الفرعُ الرئيسيُّ يعني المنشأةَ كلَّها — في الشريط وفي الشجرة.
     *   • ثمّ القصُّ على نطاق الرؤية.
     */
    protected function reportTreeNodeId(Request $request): ?int
    {
        $header = $this->headerTreeNodeId();
        /* عقدةٌ اختارها المستخدمُ في «تابع لـ» تُطبَّق ولو كانت الفرعَ
           الرئيسي — كما في لوحة العملاء. كانت تُلغى فيعرض اختيارُ «الفرع
           الرئيسي» عملاءَ المنشأة كلَّها (1284 مديناً) بينما تعرض اللوحةُ
           عملاءَه وحدَهم (8). «الرئيسيُّ = الكل» يبقى لفرع **الشريط** وحده
           ({@see headerTreeNodeId()}). */
        $requested = $this->requestedTreeNodeId($request);

        if ($requested && $header && ! in_array($requested, AffiliationTree::descendantIds($header), true)) {
            $requested = $header;
        }

        return DataScope::clampNode($requested ?? $header);
    }

    /**
     * معرّفُ العقدة المطلوبة — من الاسم الجديد أو أيٍّ من القديمة.
     *
     * التعريفُ في {@see AffiliationTree::requestedNodeId()} تشترك فيه
     * تقاريرُ العملاء وتقاريرُ المبيعات معاً.
     */
    protected function requestedTreeNodeId(Request $request): ?int
    {
        return AffiliationTree::requestedNodeId($request);
    }

    /**
     * عقدُ «تابع لـ» لمُنتقي التقارير — من المصدر المشترك.
     *
     * كان التعريفُ هنا ونسخةٌ منه في `SalesReportTrait`، فاختلف المُنتقيان
     * في القيمة والتسمية والمطابقة. صار {@see AffiliationTree} وحدَه.
     *
     * @return array<int, array{id:int, name:string, parent_id:?int, node_type:string, type_label:string}>
     */
    protected function affiliationTreeNodes(): array
    {
        $nodes = AffiliationTree::nodes();

        /* المُنتقي يبدأ من فرع الشريط ولا يعرض إلّا ما تحته — نفسُ قاعدة
           تقارير المبيعات. والمُنتقي المشترك يعرض العقدةَ التي لا أبَ لها في
           القائمة مستوىً أوّلَ ويختارها ابتداءً. */
        if ($header = $this->headerTreeNodeId()) {
            $allowed = AffiliationTree::descendantIds($header);
            $nodes = array_values(array_filter(
                $nodes,
                fn ($node) => in_array((int) ($node['id'] ?? 0), $allowed, true)
            ));
        }

        return DataScope::filterNodes($nodes);
    }

    /**
     * مسؤولو المبيعات لقائمة الفلتر — موظّفو الأقسام المسموحة.
     *
     * ══════════ من أين تأتي القائمة ══════════
     *
     * من **الموظفين** (`employees`) لا من المستخدمين، مقصورةً على
     * «أقسام الموظفين المسموح تعيينهم للعملاء» في إعدادات وحدات
     * العملاء ← تبويب الموظفين (`client_employee_settings.assignable_departments`).
     * وهي نفسُ النافذة التي تُبنى منها قائمةُ الإسناد في شاشة العميل
     * (`ClientController::employeesForClientAssignment()`)، فلا تعرض شاشةُ
     * التقرير مندوباً لا يمكن أن يُسنَد إليه عميلٌ أصلاً.
     *
     * والقائمةُ الفارغة في الإعداد تعني **كلّ الأقسام** لا «لا أحد» —
     * منشأةٌ لم تفتح الشاشة بعدُ تبقى قوائمُها كما كانت.
     *
     * وقيمةُ الخيار `employees.id` مباشرةً، وهو ما يخزّنه
     * `clients.employee_id` — لا ترجمةَ ولا جدولَ وسيط.
     */
    protected function salesEmployeeOptions()
    {
        $query = ClientSettingsHelper::scopeEmployeesToClientDepartments(
            Employee::query()
        );

        /* والقائمةُ تتبع نطاقَ الرؤية أيضاً: من لا يرى بياناتِ زميله لا
           يحتاج اسمَه في منسدلةِ «مسؤول المبيعات» — واختيارُه لا يُرجع
           إلّا شاشةً فارغة. */
        DataScope::constrainEmployee($query, 'id');

        return $query
            ->get(['id', 'first_name', 'middle_name', 'nickname', 'department_id'])
            ->sortBy(fn (Employee $employee) => $employee->full_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** مراحلُ تسجيل العميل («وضع العميل») لقائمة الفلتر. */
    protected function registrationStageOptions()
    {
        return \App\Models\Statuses::section('registration_stage')
            ->ordered()
            ->get(['id', 'name', 'color']);
    }

    /**
     * معرّفاتُ العملاء المطابقة للفلاتر.
     *
     * تُستعمل حيث لا تكفي `whereHas` — كتجميع الحسابات قبل قراءة الأستاذ.
     *
     * @return array<int, int>
     */
    protected function filteredClientIds(Request $request): array
    {
        $query = \App\Models\Client::query();
        $this->applyClientFilters($query, $request);

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * تحليلُ قيمة عقدة الشجرة.
     *
     * البادئتان `b_` و`g_` أثرٌ من حين ظُنّ أنّ الفروع والمجموعات جدولان —
     * وهما جدولٌ واحد (`branches`)، فالمعرّفُ وحدَه يكفي. تبقى القراءةُ
     * لأجل روابطَ محفوظة، ويبقى النوعُ المُعاد لمن يستعمله.
     *
     * @return array{0: ?string, 1: ?int}
     */
    protected function parseRegionGroupFilter($value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (str_starts_with((string) $value, 'b_')) {
            return ['branch', (int) substr((string) $value, 2)];
        }

        if (str_starts_with((string) $value, 'g_')) {
            return ['group', (int) substr((string) $value, 2)];
        }

        return ['group', (int) $value];
    }

    /**
     * الفرعُ وكلُّ ما تحته — بعمقٍ غير محدود.
     *
     * @return array<int, int>
     */
    protected function collectBranchAndDescendantIds($branchId): array
    {
        $ids = [(int) $branchId];
        $queue = [(int) $branchId];

        while ($queue !== []) {
            $children = Branch::withoutGlobalScopes()
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->all();

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** أفي فرعٍ رئيسيٍّ هذا المستخدم؟ — بلا فرعٍ يعني عرضاً كاملاً. */
    protected function isUserInMainBranch(): bool
    {
        $userBranchId = auth()->user()->branch_id ?? null;

        if (! $userBranchId) {
            return true;
        }

        $userBranch = Branch::find($userBranchId);

        return ! $userBranch || (bool) $userBranch->is_main;
    }

    /**
     * أهدافُ العملاء لقائمة الفلتر — النشطةُ أولاً بترتيب أولويّتها.
     */
    protected function customerTargetOptions()
    {
        return CustomerTarget::orderByDesc('priority')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'is_active']);
    }
}
