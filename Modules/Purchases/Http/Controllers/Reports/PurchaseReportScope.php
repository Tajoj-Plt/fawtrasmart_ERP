<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Models\Branch;
use App\Support\AffiliationTree;
use App\Support\ClosedPeriodRanges;
use App\Support\DataScope;
use Illuminate\Http\Request;

/**
 * نطاقُ تقارير المشتريات — الجهةُ في الشجرة، وما أُقفل محاسبياً.
 *
 * ══════════ لماذا هنا لا في كلّ متحكّم ══════════
 *
 * كانت تقاريرُ المشتريات السبعةُ تقرأ `branch_id` مسطّحاً: قائمةٌ واحدة
 * فيها الفروعُ والمناطقُ والمنافذُ مختلطةً بلا نسب، واختيارُ منطقةٍ
 * يُطابق `purchase_invoices.branch_id = <المنطقة>` وحدَه — فتخرج الشاشةُ
 * فارغةً لأن الفواتير مثبّتةٌ على فروعٍ **تحت** المنطقة لا عليها.
 *
 * وتقاريرُ المبيعات كانت قد تجاوزت هذا إلى مُنتقي «تابع لـ» المتدرّج
 * ({@see \App\Support\AffiliationTree})، فصارت الوحدتان تقولان في الفرع
 * الواحد قولين. هذا الـtrait ينقل المشترياتِ إلى المصدر نفسِه:
 *
 *   • القيمةُ `tree_node` — معرّفُ عقدةٍ في `branches`، بلا بادئة.
 *   • والعقدةُ تُوسَّع إلى ما تحتها، إلّا أن يُطلب `exact_node`.
 *   • ومستخدمٌ خارج الفرع الرئيسي مقصورٌ على فرعه مهما أرسل.
 *
 * ══════════ والإقفالُ السنويّ ══════════
 *
 * `SupplierLedger` و`ProductPurchaseLedger` كانا يستبعدان ما أُقفل، وبقيةُ
 * الدفاتر لا. فشاشةُ مشترياتِ الموظّفين كانت تعرض سنةً أُقفلت وأُصدرت
 * قوائمُها بينما شاشةُ الموردين تُخفيها — رقمان متعارضان عن الشهر نفسه.
 * التعريفُ واحدٌ في {@see ClosedPeriodRanges}، وهو تابعٌ لضابط
 * `enforce_lock`: قبل تفعيله لا أثرَ لأيّ إقفال.
 *
 * والاستبعادُ يقع على **حركة** الفترة لا على الأرصدة: رصيدُ المورّد حصيلةُ
 * التاريخ كلِّه، وحذفُ سنةٍ مقفلةٍ منه يخترع رقماً لا يقابله شيءٌ في دفتر
 * الأستاذ — وهي القاعدةُ التي يشرحها `SupplierLedger::openingBalances()`.
 */
trait PurchaseReportScope
{
    /**
     * العقدةُ المطلوبة — «تابع لـ» في الطلب، أو فرعُ المستخدم.
     *
     * `branch_id` يُقرأ للتوافق مع روابطَ محفوظةٍ ومع الشاشات التي لم
     * تُنقل بعد، وهو مقصورٌ على مستخدم الفرع الرئيسي كما كان.
     *
     * ══════════ والمطلوبُ يُقصَر على شجرة المستخدم ══════════
     *
     * مستخدمٌ خارج الفرع الرئيسي لا يخرج عن فرعه وما تحته مهما أرسل.
     * وبدون هذا القيد يكفي أن يُبدَّل `tree_node` في شريط العنوان ليقرأ
     * موظّفُ فرعٍ أرقامَ منطقةٍ أخرى كاملةً — وقاعدةُ `branch_id` القديمة
     * كانت تمنع ذلك، فنقلُ الشاشات إلى الشجرة كان سيفتحه من حيث لا يُقصد.
     *
     * والعقدةُ المطلوبة تُقبل إن كانت فرعَه أو تحته؛ وإلّا رُدّ إلى فرعه.
     */
    protected function reportNodeId(Request $request): ?int
    {
        $requested = AffiliationTree::requestedNodeId(
            $request,
            ['tree_node', 'cascade_node', 'region_group', 'region_group_id']
        );

        $user = auth()->user();
        $userBranchId = $user?->branch_id ? (int) $user->branch_id : null;
        $userBranch = $userBranchId ? Branch::find($userBranchId) : null;
        $isMainBranch = $userBranch && $userBranch->is_main == 1;

        if (! $isMainBranch && $userBranchId) {
            $allowed = AffiliationTree::descendantIds($userBranchId) ?: [$userBranchId];

            return DataScope::clampNode(
                ($requested && in_array($requested, $allowed, true)) ? $requested : $userBranchId
            );
        }

        if ($requested) {
            return DataScope::clampNode($requested);
        }

        return DataScope::clampNode($isMainBranch
            ? AffiliationTree::requestedNodeId($request, ['branch_id', 'branch'])
            : $userBranchId);
    }

    /**
     * تقييدُ استعلامٍ بنطاق رؤية المستخدم — محورُ الأشخاص.
     *
     * ══════════ ما الذي كان ناقصاً ══════════
     *
     * القيدُ أعلاه على **الفرع** وحدَه، ومبناهُ `branches.is_main` لا
     * الصلاحيات. فمندوبٌ في الفرع الرئيسي — وهو حالُ أكثر المستخدمين هنا —
     * كان يقرأ مشترياتِ زملائه كلِّهم مهما كان نطاقُه، لأنّ `$isMainBranch`
     * تُسقط كلَّ قيدٍ عنه. ومَن نطاقُه `data_scope_own` تقول صلاحيتُه
     * «بياناته هو» ولا شيءَ في الشاشة كان يقرؤها.
     *
     * والقيدُ هنا على محور الأشخاص وحدَه: قيدُ الفرع مطبَّقٌ أصلاً عبر
     * {@see reportBranchIds()}، وجمعُهما يطرح مستنداتٍ مشروعةً من تقرير
     * مديرِ الفرع (مستندُ فرعِه الذي كتبه غيرُ مرؤوسيه).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    protected function scopeToVisibleCreators($query, string $column = 'created_by')
    {
        return DataScope::isPeopleAxis()
            ? $query->whereIn($column, DataScope::userIds() ?? [0])
            : $query;
    }

    /** نظيرُها على عمود **موظّف** (`employees.id`) لا مستخدم. */
    protected function scopeToVisibleEmployees($query, string $column = 'employee_id')
    {
        return DataScope::isPeopleAxis()
            ? $query->whereIn($column, DataScope::employeeIds() ?? [0])
            : $query;
    }

    /**
     * ما تُمرَّر به الدفاترُ عبر `visibleTo()` — أو `null` بلا قيد.
     *
     * الدفاترُ الخمسة تبني أرقامَها بنفسها، فلا يكفي أن يُقيَّد استعلامُ
     * الشاشة: الرقمُ يأتي من الدفتر لا من المتحكّم. و`null` هنا مقصودةٌ
     * على محور المكان، إذ الفروعُ تصل الدفترَ أصلاً عبر `fromFilters()`.
     *
     * @return array<int, int>|null
     */
    protected function visibleCreatorIds(): ?array
    {
        return DataScope::isPeopleAxis() ? (DataScope::userIds() ?? [0]) : null;
    }

    /**
     * قائمةُ «الموظف» في فلاتر تقارير المشتريات، مقصورةً على النطاق.
     *
     * كان `User::whereIn('role', […])->orderBy('name')->get()` مكرَّراً
     * حرفياً في سبعة مواضع من خمسة متحكّمات، غيرَ مقيَّدٍ بشيء. فمن
     * نطاقُه «بياناته هو» يرى في المنسدلة كلَّ موظّفي المنشأة بأسمائهم،
     * ويختار منهم فيخرج بشاشةٍ فارغةٍ لا تفسّر نفسها.
     *
     * والتقييدُ على المحورين معاً هنا — لا على محور الأشخاص وحدَه: هذه
     * قائمةُ **مستخدمين** لا صفوفُ مستندات، ومديرُ الفرع لا شأنَ له
     * بأسماء موظّفي فرعٍ آخر أصلاً.
     */
    /**
     * قائمةُ الفروع في فلاتر تقارير المشتريات، مقصورةً على نطاق المستخدم.
     *
     * كان `Branch::orderBy('name')->get()` مكرَّراً في أحدَ عشرَ موضعاً بلا
     * قيد، فيرى موظّفُ فرعٍ أسماءَ فروع المنشأة كلِّها في المنسدلة. والقيدُ
     * في {@see DataScope::selectableBranchIds()} — قاعدةُ المنسدلات نفسُها
     * التي يتبعها مُنتقي «تابع لـ».
     */
    protected function reportBranchOptions(array $columns = ['*'])
    {
        return DataScope::branchOptions(Branch::query()->orderBy('name'), $columns);
    }

    protected function reportUserOptions(array $columns = ['*'])
    {
        $query = \App\Models\User::whereIn('role', ['admin', 'employee', 'manager']);

        DataScope::constrainCreator($query, 'id');

        // أقسامُ موظّفي المشتريات من الإعدادات — إن ضُبطت
        \Modules\Purchases\Support\HeaderPickers::constrainUsersToDepartments($query);

        /* على فرع الشريط العلويّ: من أنشأ مستندَ شراءٍ أو سداداً في الجهة وما
           يتبعها. `users.branch_id` لا يصلح هنا — هو موضعُ المستخدم في الشريط
           الآن لا مكانُ عمله. */
        if ($headerIds = \Modules\Purchases\Support\HeaderPickers::nodeIds()) {
            $inScope = fn ($table, $column) => \Illuminate\Support\Facades\DB::table($table)
                ->where(fn ($w) => $w->whereIn('branch_id', $headerIds)->orWhereNull('branch_id'))
                ->whereNotNull($column)
                ->distinct()
                ->pluck($column);

            $query->whereIn('id', $inScope('purchase_invoices', 'created_by')
                ->merge($inScope('payments_process', 'employee_id'))
                ->unique()
                ->all());
        }

        return $query->orderBy('name')->get($columns);
    }

    /**
     * الفروعُ المشمولة — العقدةُ وما تحتها، أو `null` لكل الفروع.
     *
     * `null` لا `[]`: الدفاترُ تفهم الفارغَ «كلّ الفروع»، وقائمةٌ فارغةٌ
     * صريحةٌ كانت ستعني الشيءَ نفسَه بغموض.
     *
     * @return array<int, int>|null
     */
    protected function reportBranchIds(Request $request): ?array
    {
        $node = $this->reportNodeId($request);

        if (! $node) {
            return null;
        }

        if ($request->boolean('exact_node')) {
            return [$node];
        }

        $ids = AffiliationTree::descendantIds($node);

        return $ids ?: [$node];
    }

    /**
     * ما تحتاجه الشاشةُ لرسم المُنتقي وشارةِ الفلتر.
     *
     * `treeNodes` مصدرُ المُنتقي المشترك (`reports.partials.affiliation-tree`)،
     * و`selectedNodeLabel` اسمُ العقدة المختارة لترويسة الطباعة وشارات
     * الفلاتر — كانتا تُشتقّان من `$branches` المسطّحة، فلا تعرف الشاشةُ
     * اسمَ منطقةٍ اختيرت لأنها ليست في تلك القائمة أصلاً.
     *
     * @return array{treeNodes: array, selectedNodeId: int|null, selectedNodeLabel: string|null}
     */
    protected function reportTreeContext(Request $request): array
    {
        $node = $this->reportNodeId($request);
        $branch = $node ? Branch::find($node) : null;

        $label = $branch
            ? $branch->name.($request->boolean('exact_node') ? '' : ' (وما يتبعها)')
            : null;

        /* المُنتقي يعرض فرعَ الشريط العلويّ وما يتبعه وحده — من وقف على «منطقة
           الرياض» لا يرى جدّة في القائمة. العقدةُ التي أبوها خارج القائمة
           تصير أعلى مستوى في المُنتقي، فيبدأ من عقدة الشريط نفسها.
           والرئيسيُّ يعني المنشأة كلَّها ({@see \App\Support\HeaderBranchScope}). */
        $treeNodes = \Modules\Purchases\Support\HeaderPickers::treeNodes();

        return [
            'treeNodes' => $treeNodes,
            'selectedNodeId' => $node,
            'selectedNodeLabel' => $label,
        ];
    }

    /** استبعادُ ما أُقفل محاسبياً من استعلام — على تاريخ المستند. */
    protected function excludeClosedRanges($query, string $column): void
    {
        ClosedPeriodRanges::exclude($query, $column);
    }
}
