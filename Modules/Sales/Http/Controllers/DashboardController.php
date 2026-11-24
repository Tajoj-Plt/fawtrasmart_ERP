<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\JournalEntryDetail;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\OperationLog;
use App\Support\ReportPeriod;
use App\Support\ThemeSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Support\SalesDashboardDrillDown;
use Modules\Sales\Support\SalesDashboardMetrics;
use Modules\Sales\Support\SalesLedger;

class DashboardController extends Controller
{
    /**
     * لوحة مؤشّرات المبيعات.
     *
     * المتحكّم لا يحسب رقماً: يحدّد الفرع والفترة، ثم يسأل
     * `SalesDashboardMetrics` — وهي تشتقّ كل رقم من `SalesLedger`، دفترِ
     * أستاذ العملاء. فما تعرضه اللوحة وما يعرضه أي تقرير مبيعات رقمٌ واحد.
     *
     * **ما يُقرأ من الإعدادات لا من الشيفرة:**
     *
     *     العملة    `CurrencySettings`  رمزها ومنازلها وفواصلها
     *     اللغة     `__()` + `LanguageSettings`  الاتجاه وصيغة التاريخ
     *     المظهر    `ThemeSettings::palette()`  ألوان اللوحة ورسومها
     *     الحسابات  `AccountingSettings`  عبر `SalesLedger`
     *     الفترات   `AccountingPeriodService`  حالة الإقفال المحاسبي
     */
    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.dashboard.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.dashboard.view', ['only' => ['index', 'segment', 'reps']]);
        $this->middleware('permission:sales.dashboard.financial_analysis', ['only' => ['financialAnalysis']]);
    }

    public function index(Request $request)
    {
        $branch = $this->resolveDashboardBranch($request);
        $period = ReportPeriod::fromRequest($request, 'year');

        $metrics = SalesDashboardMetrics::forBranch($branch['ids']);

        $totals = $metrics->totals($period);
        $previous = $metrics->totals($metrics->previousPeriod($period));

        return view('sales::dashboard.index', [
            'period' => $period,
            'periodTabs' => $this->periodTabs(),

            'branchOptions' => $branch['options'],
            'branchLineage' => $branch['lineage'],
            'selectedBranchId' => $branch['selected'],
            'isMainBranch' => $branch['isMain'],
            'includeChildren' => $branch['includeChildren'],
            'descendantCount' => $branch['descendants'],
            'hasChildBranches' => $branch['hasChildren'],

            'totals' => $totals,
            'deltas' => $this->dashboardDeltas($totals, $previous),
            'receivables' => $metrics->receivables($period),

            'trend' => $metrics->trend($period),
            'topClients' => $metrics->topClients($period),
            'topDebtors' => $metrics->topDebtors($period),
            'branchBreakdown' => $metrics->branchBreakdown($period, $branch['selected']),
            // الآباءُ والأبناءُ معاً — كلُّ أبٍ بمجموعه شاملاً ما تحته
            'branchTree' => $metrics->branchTree($period, $branch['selected']),
            'salesByEmployee' => $metrics->salesByEmployee($period),
            // الشهرُ الذي تُقاس عليه أهدافُ المندوبين
            'employeeTargetMonth' => $metrics->targetMonth($period),
            'paymentStatusMix' => $metrics->paymentStatusMix($period),
            'topProducts' => $metrics->topProducts($period),
            'recentMovements' => $metrics->recentMovements($period),

            // المظهر من إعدادات النظام — الرسوم تتبع ألوان الثيم لا ألواناً مكتوبة
            'palette' => ThemeSettings::palette(),
            'isDarkTheme' => ThemeSettings::isDark(),

            // حالة الإقفال المحاسبي لطرفَي الفترة
            'periodNotice' => $this->accountingPeriodNotice($period),
        ]);
    }

    /**
     * «أداء المندوبين» — جدولُ اللوحة في شاشةٍ مستقلّة.
     *
     * الصفوفُ صفوفُ `salesByEmployee()` نفسُها وبقواعدها، لكن كلُّ المندوبين
     * لا أعلى ٢٥، مرتَّبين بالأكثر تحصيلاً (أو بما يختاره المستخدم من
     * `REP_SORTS`)، وأفضلُهم في الصدارة. الفرعُ بما تُحسب به اللوحة، والفترةُ
     * الافتراضية الشهرُ الحالي لأن الترتيب شهريٌّ في العادة — والقوائمُ من
     * `segment()` نفسِه، فالسكربتُ يمرّر مفتاحَ الفترة دائماً.
     */
    public function reps(Request $request)
    {
        $branch = $this->resolveDashboardBranch($request);
        $period = ReportPeriod::fromRequest($request, 'month');

        $sort = (string) $request->input('sort', 'collections');
        if (! in_array($sort, SalesDashboardMetrics::REP_SORTS, true)) {
            $sort = 'collections';
        }

        $metrics = SalesDashboardMetrics::forBranch($branch['ids']);
        $reps = $metrics->salesByEmployee($period, null, $sort);

        // مجموعُ الفريق من الصفوف نفسِها — فيطابق تذييلَ الجدول
        $team = [];
        foreach (['invoices', 'sales', 'returns_total', 'net_sales', 'collections', 'refunds', 'refunds_count',
            'cancelled_receipts', 'cancelled_count', 'expenses', 'expense_documents', 'visits', 'planned'] as $key) {
            $team[$key] = $reps->sum($key);
        }

        return view('sales::dashboard.reps', [
            'period' => $period,
            'periodTabs' => $this->periodTabs(),

            'branchOptions' => $branch['options'],
            'branchLineage' => $branch['lineage'],
            'selectedBranchId' => $branch['selected'],
            'isMainBranch' => $branch['isMain'],
            'includeChildren' => $branch['includeChildren'],
            'descendantCount' => $branch['descendants'],
            'hasChildBranches' => $branch['hasChildren'],

            'reps' => $reps,
            'team' => $team,
            'sort' => $sort,
            'sortOptions' => collect(SalesDashboardMetrics::REP_SORTS)
                ->mapWithKeys(fn ($key) => [$key => __('sales_dashboard.reps.sorts.'.$key)])
                ->all(),
            'employeeTargetMonth' => $metrics->targetMonth($period),
            'periodNotice' => $this->accountingPeriodNotice($period),
        ]);
    }

    /**
     * «ماذا وراء هذا الرقم؟» — قائمةُ الرقم المنقور في اللوحة (JSON).
     *
     * الفرعُ والفترةُ بما تُحسب به اللوحة نفسُها (`resolveDashboardBranch()`
     * و`ReportPeriod::fromRequest()`)، فلا تختلف قائمةٌ عن رقمها. وخلايا
     * الشجرة ترسل `node` (وإلا «none» لما لا عقدة لعميله) و`node_scope`
     * (`tree` شاملاً ما تحته، أو `direct` وحده) — ولا تخرج عن نطاق المستخدم.
     */
    public function segment(Request $request)
    {
        $segment = (string) $request->input('segment', '');
        abort_unless(preg_match('/^[a-z_]+(\.[a-z0-9_-]+){0,2}$/', $segment) === 1, 422);

        $branch = $this->resolveDashboardBranch($request);
        $period = ReportPeriod::fromRequest($request, 'year');
        [$branchIds, $onlyClients, $scopeLabel] = $this->segmentScope($request, $branch);

        $result = (new SalesDashboardDrillDown($branchIds, $onlyClients))->resolve($segment, $period);
        abort_if($result === null, 404);

        $result['hint'] = implode(' — ', array_filter([$scopeLabel, $period->labelWithDates(), $result['hint'] ?? null]));

        return response()->json($result + ['count' => count($result['rows'])]);
    }

    /**
     * نطاقُ القائمة: نطاقُ اللوحة، أو عقدةٌ من شجرتها نُقرت خليّتُها.
     *
     * @return array{0: array<int, int>, 1: array<int, int>|null, 2: string|null}
     */
    protected function segmentScope(Request $request, array $branch): array
    {
        $node = (string) $request->input('node', '');

        if ($node === '') {
            return [$branch['ids'], null, null];
        }

        if ($node === 'none') {
            // «غير محدّد» جذرٌ لا يظهر إلا في عرض المنشأة كلّها
            abort_unless($branch['selected'] === null, 403);

            $clientIds = DB::table('clients as c')
                ->whereRaw(SalesLedger::clientNodeExpr('c').' IS NULL')
                ->pluck('c.id')->map(fn ($id) => (int) $id)->all();

            return [[], $clientIds, __('sales_dashboard.common.unassigned')];
        }

        abort_unless(ctype_digit($node), 422);
        $model = Branch::find((int) $node);
        abort_unless($model, 404);

        // لا تخرج القائمةُ عن العقدة المعروضة — ومستخدمُ الفرع محبوسٌ فيها دائماً.
        // بلا عقدةٍ مختارة تعرض اللوحةُ المنشأةَ كلَّها، فكلُّ عقدةٍ فيها مسموحة.
        if ($branch['selected'] !== null) {
            abort_unless((int) $model->id === $branch['selected'] || $model->isUnderBranch($branch['selected']), 403);
        }

        $direct = $request->input('node_scope') === 'direct';

        return [
            $this->dashboardSubtreeIds((int) $model->id, ! $direct),
            null,
            $model->name.($direct ? ' ('.__('sales_dashboard.common.direct_activity').')' : ''),
        ];
    }

    /** مفاتيح الفترة بأسمائها المترجمة */
    protected function periodTabs(): array
    {
        return collect(ReportPeriod::TABS)
            ->mapWithKeys(fn ($label, $key) => [$key => __('sales_dashboard.periods.'.$key)])
            ->all();
    }

    /**
     * الفرع المعروض وموضعه من الشجرة — تنقّلٌ مستوىً بمستوى.
     *
     * **لماذا لا قائمةٌ مسطّحة بكل العقد؟** لأن سرد الأب وأبنائه في قائمةٍ
     * واحدة يخلط مستوياتٍ لا تُقارَن، ويجعل جمع العمود يقرأ المبلغ مرّتين.
     * فالتنقّل هنا نزولٌ في الشجرة:
     *
     *     لا اختيار      ← الجذور (أكبر الآباء)
     *     عقدة مختارة   ← أبناؤها المباشرون، ومسارٌ للعودة صعوداً
     *
     * ويُقبل أي عمق: اختيار ابنٍ يعرض أبناءه، واختيار حفيدٍ يعرض أبناءه —
     * وهكذا حتى الورقة.
     *
     * @return array{ids: array<int, int>, selected: int|null, isMain: bool, options: Collection, lineage: Collection, includeChildren: bool, descendants: int, hasChildren: bool}
     */
    protected function resolveDashboardBranch(Request $request): array
    {
        $includeChildren = $request->boolean('include_children', true);
        $userBranchId = auth()->user()?->branch_id;
        $isMain = (bool) Branch::where('id', $userBranchId)->value('is_main');

        $requested = $request->input('branch_id');
        $selectedId = in_array($requested, [null, '', '0', 'all'], true) ? null : (int) $requested;
        $selected = $selectedId ? Branch::find($selectedId) : null;

        // مستخدم فرعٍ غير رئيسي محبوسٌ في شجرته: يتنقّل داخلها ولا يخرج
        if (! $isMain) {
            $home = $userBranchId ? Branch::find($userBranchId) : null;

            if (! $home) {
                return $this->branchState(null, null, collect(), $includeChildren, false);
            }

            // اختيارٌ خارج شجرته يُرَدّ إلى فرعه
            if (! $selected || ! $selected->isUnderBranch((int) $home->id)) {
                $selected = $home;
            }

            return $this->branchState($selected, $home, $this->childrenOf($selected->id), $includeChildren, false);
        }

        return $this->branchState($selected, null, $this->childrenOf($selected?->id), $includeChildren, true);
    }

    /**
     * أبناء عقدةٍ مباشرةً — أو الجذور حين لا عقدة.
     *
     * **كانت تستبعد عُقد المناطق** بحجّة أنها «تقسيمٌ جغرافي لا فرعٌ
     * محاسبي». والحجّة صحيحةٌ نظراً وخاطئةٌ عملاً: المستنداتُ تُوسَم
     * بالعقدة التي أصدرتها أياً كان نوعُها، فأربعةُ أخماسِ الفواتير في
     * منشأةٍ تعمل بالمناطق تقع على عُقدٍ كانت الشجرةُ تُخفيها — فتعرض
     * اللوحةُ خُمسَ المنشأة وتبدو أرقامُها خاطئة.
     *
     * ولا يُصلحها الضمُّ التنازلي: المناطق جذورٌ بلا أبٍ، فلا فرعَ يرثها.
     *
     * التقسيمُ الجغرافي يُميَّز بالعرض — بأيقونةٍ أو وسمٍ في القائمة — لا
     * بالحجب من الحساب.
     *
     * @return Collection<int, Branch>
     */
    protected function childrenOf(?int $parentId)
    {
        return Branch::where('parent_id', $parentId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'node_type', 'is_territory']);
    }

    /**
     * حالة الفرع كما تقرأها الشاشة.
     *
     * @param  Branch|null  $home  جذرُ صلاحية المستخدم — يقف عنده المسار
     * @return array<string, mixed>
     */
    protected function branchState(?Branch $selected, ?Branch $home, $options, bool $includeChildren, bool $isMain): array
    {
        $ids = $selected
            ? $this->dashboardSubtreeIds((int) $selected->id, $includeChildren)
            : [];

        // المسار من الجذر (أو من فرع المستخدم) إلى العقدة المختارة
        $lineage = collect();

        if ($selected) {
            $lineage = $selected->lineageFromRoot()
                ->when($home, fn ($path) => $path->filter(fn ($node) => $node->isUnderBranch((int) $home->id))->values());
        }

        return [
            'ids' => $ids,
            'selected' => $selected?->id ? (int) $selected->id : null,
            'isMain' => $isMain,
            'options' => $options,
            'lineage' => $lineage,
            'includeChildren' => $includeChildren,
            'descendants' => $selected ? max(0, count($ids) - 1) : 0,
            'hasChildren' => $options->isNotEmpty(),
        ];
    }

    /**
     * الفرع وحده، أو هو وكل أبنائه مهما عمقوا.
     *
     * @return array<int, int>
     */
    protected function dashboardSubtreeIds(int $branchId, bool $includeChildren): array
    {
        if (! $includeChildren) {
            return [$branchId];
        }

        $branch = Branch::find($branchId);

        if (! $branch) {
            return [$branchId];
        }

        return $branch->descendantBranchIds()->push($branchId)
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * تغيّر كل مؤشّر عن الفترة السابقة — نسبةً واتجاهاً.
     *
     * `null` حين لا سابقةَ لها: النمو من صفرٍ ليس «١٠٠٪» ولا صفراً.
     *
     * @return array<string, array{percent: float|null, direction: string}>
     */
    protected function dashboardDeltas(array $totals, array $previous): array
    {
        $deltas = [];

        foreach (['sales', 'net_sales', 'returns', 'returns_total', 'collections', 'sales_count'] as $key) {
            $now = (float) ($totals[$key] ?? 0);
            $then = (float) ($previous[$key] ?? 0);

            if (abs($then) < 0.009) {
                $deltas[$key] = ['percent' => null, 'direction' => $now > 0 ? 'up' : 'flat'];

                continue;
            }

            $percent = (($now - $then) / abs($then)) * 100;

            $deltas[$key] = [
                'percent' => round($percent, 1),
                'direction' => $percent > 0.05 ? 'up' : ($percent < -0.05 ? 'down' : 'flat'),
            ];
        }

        return $deltas;
    }

    /**
     * حالة الإقفال المحاسبي للفترة المعروضة — أو `null` إن لا شيء يُقال.
     *
     * اللوحة تعرض ولا تكتب، فالإقفال لا يمنعها؛ لكن قارئ الأرقام يعنيه
     * أن يعرف أن مدةً مقفلةٌ قد لا تقبل تصحيحاً.
     */
    protected function accountingPeriodNotice(ReportPeriod $period): ?array
    {
        try {
            $service = app(AccountingPeriodService::class);

            if (! DB::table('fiscal_years')->exists()) {
                return ['level' => 'muted', 'message' => __('sales_dashboard.period_notice.not_configured')];
            }

            $reason = $service->blockReason($period->to) ?: $service->blockReason($period->from);

            return $reason
                ? ['level' => 'warning', 'message' => __('sales_dashboard.period_notice.closed', ['reason' => $reason])]
                : null;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.Dashboard', 'accountingPeriodNotice');

            // إعدادات الفترات غير مهيّأة — اللوحة تعرض ولا تعتمد عليها
            return null;
        }
    }

    public function financialAnalysis(Request $request)
    {
        $user = User::find(auth()->user()->id);
        $branch_id = $user->branch_id;

        // الفرع الحالي لتحديد هل هو رئيسي
        $currentBranch = Branch::find($branch_id);
        $isMainBranch = $currentBranch && $currentBranch->is_main == 1;

        // تحديد الفرع المحدد
        $requestedBranchId = $request->get('branch_id');

        // إذا كان الفرع الرئيسي ← نجلب كل الفروع (selected_branch_id = null)
        if ($isMainBranch) {
            $selected_branch_id = ($requestedBranchId && $requestedBranchId !== '0' && $requestedBranchId !== 'all' && $requestedBranchId !== '')
                ? $requestedBranchId
                : null;
        } else {
            // فرع غير رئيسي: دائماً يستخدم فرعه فقط
            $selected_branch_id = $branch_id;
        }

        $selected_branch_ids = $this->expandBranchToDescendants($selected_branch_id);

        // =================== فلترة الفترة الزمنية ===================
        $period = $request->get('period', '30'); // الافتراضي 30 يوم
        $customStartDate = $request->get('start_date');
        $customEndDate = $request->get('end_date');

        $endDate = Carbon::now()->endOfDay();

        switch ($period) {
            case '1':
                $startDate = Carbon::now()->startOfDay();
                break;
            case '7':
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                break;
            case '30':
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                break;
            case '90':
                $startDate = Carbon::now()->subDays(89)->startOfDay();
                break;
            case '180':
                $startDate = Carbon::now()->subDays(179)->startOfDay();
                break;
            case '365':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;
            case 'custom':
                $startDate = $customStartDate ? Carbon::parse($customStartDate)->startOfDay() : Carbon::now()->startOfYear();
                $endDate = $customEndDate ? Carbon::parse($customEndDate)->endOfDay() : Carbon::now()->endOfDay();
                break;
            default:
                $startDate = Carbon::now()->subDays(29)->startOfDay();
        }

        // =================== الإحصائيات الأساسية ===================
        $totalSales = $this->getTotalSales($selected_branch_ids, $isMainBranch, $startDate, $endDate);
        $totalReturns = $this->getTotalSalesReturns($selected_branch_ids, $isMainBranch, $startDate, $endDate);

        // جلب إجمالي المصروفات للفرع المختار والفترة المختارة
        $expenseAccountIds = Account::getAllExpenseAccountIds();
        $totalExpenses = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_details.account_id', $expenseAccountIds)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->when($selected_branch_ids, function ($q) use ($selected_branch_ids) {
                $q->whereIn('journal_entries.branch_id', is_array($selected_branch_ids) ? $selected_branch_ids : [$selected_branch_ids]);
            })
            ->selectRaw('SUM(journal_entry_details.debit) - SUM(journal_entry_details.credit) as total')
            ->value('total') ?? 0;

        // جدول أداء المنتجات
        $productsPerformance = DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->whereIn('invoices.type', ['normal', 'pos'])
            ->when($selected_branch_ids, function ($q) use ($selected_branch_ids) {
                $q->whereIn('clients.branch_id', is_array($selected_branch_ids) ? $selected_branch_ids : [$selected_branch_ids]);
            })
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NULL THEN invoice_items.quantity ELSE 0 END) as total_qty_sold'),
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NULL THEN invoice_items.total ELSE 0 END) as total_gross_sales'),
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NOT NULL THEN invoice_items.quantity ELSE 0 END) as returned_qty'),
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NOT NULL THEN invoice_items.total ELSE 0 END) as returned_value')
            )
            ->groupBy('products.id', 'products.name')
            ->get();

        // جدول تحليل أداء الموظفين
        $employeesPerformance = DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('users', 'invoices.employee_id', '=', 'users.id')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->whereIn('invoices.type', ['normal', 'pos'])
            ->when($selected_branch_ids, function ($q) use ($selected_branch_ids) {
                $q->whereIn('clients.branch_id', is_array($selected_branch_ids) ? $selected_branch_ids : [$selected_branch_ids]);
            })
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NULL THEN invoice_items.quantity ELSE 0 END) as total_qty_sold'),
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NULL THEN invoice_items.total ELSE 0 END) as total_gross_sales'),
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NOT NULL THEN invoice_items.total ELSE 0 END) as returned_value'),
                DB::raw('SUM(CASE WHEN invoices.reference_number IS NULL THEN (invoice_items.quantity * IFNULL(products.purchase_price, 0)) ELSE 0 END) as total_cogs')
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        $branches = [];
        if ($isMainBranch) {
            $branches = Branch::where('is_territory', 0)->orWhereNull('is_territory')->get();
        }

        return view('sales::kpis.financial_analysis', compact(
            'totalSales',
            'totalReturns',
            'totalExpenses',
            'productsPerformance',
            'employeesPerformance',
            'branches',
            'selected_branch_id',
            'isMainBranch'
        ));
    }

    // =================== دوال الجلب من القيود - نفس منطق EmployeePerformanceController ===================

    /**
     * إجمالي مبيعات مجموعة موظفين (محسّنة)
     */
    private function getEmployeesTotalSales($employeeIds, $startDate, $endDate)
    {
        if (empty($employeeIds)) {
            return 0;
        }

        $result = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($employeeIds, $startDate, $endDate) {
            $q->whereBetween('date', [$startDate, $endDate])
                ->whereIn('created_by_employee', $employeeIds)
                ->where('status', 1);
        })
            ->where('account_id', SystemAccountIds::SALES)
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        return ($result->total_credit ?? 0) - ($result->total_debit ?? 0);
    }

    /**
     * إجمالي تحصيلات مجموعة موظفين (محسّنة - تستخدم SQL لاستثناء التحويلات)
     */
    private function getEmployeesTotalCollections($employeeIds, $startDate, $endDate)
    {
        if (empty($employeeIds)) {
            return 0;
        }

        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        if (empty($treasuryAndBankAccounts)) {
            return 0;
        }

        $query = JournalEntryDetail::whereIn('account_id', $treasuryAndBankAccounts)
            ->from('journal_entry_details')
            ->whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function ($q) use ($employeeIds, $startDate, $endDate) {
                $q->whereIn('created_by_employee', $employeeIds)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 1);
            });

        // استثناء التحويلات: القيد الذي يحتوي على دائن (>0) في حسابات الخزينة/البنوك
        $query->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
            $subQuery->select(DB::raw(1))
                ->from('journal_entry_details as jed2')
                ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                ->where('jed2.credit', '>', 0);
        });

        return $query->sum('debit') ?? 0;
    }

    /**
     * إجمالي مردودات مجموعة موظفين (محسّنة)
     */
    private function getEmployeesTotalReturns($employeeIds, $startDate, $endDate)
    {
        if (empty($employeeIds)) {
            return 0;
        }

        $result = JournalEntryDetail::whereHas('journalEntry', function ($q) use ($employeeIds, $startDate, $endDate) {
            $q->whereBetween('date', [$startDate, $endDate])
                ->whereIn('created_by_employee', $employeeIds)
                ->where('status', 1);
        })
            ->where('account_id', SystemAccountIds::SALES_RETURNS)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        return ($result->total_debit ?? 0) - ($result->total_credit ?? 0);
    }

    /**
     * المبيعات الشهرية لمجموعة موظفين (نفس طريقة EmployeePerformanceController)
     */
    private function getMonthlyEmployeeSales($employeeIds)
    {
        $monthlyData = array_fill(0, 12, 0);

        if (empty($employeeIds)) {
            return $monthlyData;
        }

        $results = JournalEntryDetail::select(
            DB::raw('MONTH(journal_entries.date) as month'),
            DB::raw('SUM(journal_entry_details.credit) as total_credit'),
            DB::raw('SUM(journal_entry_details.debit) as total_debit')
        )
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entries.created_by_employee', $employeeIds)
            ->where('journal_entries.status', 1)
            ->whereYear('journal_entries.date', Carbon::now()->year)
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES)
            ->groupBy('month')
            ->get();

        foreach ($results as $result) {
            $monthlyData[$result->month - 1] = ($result->total_credit ?? 0) - ($result->total_debit ?? 0);
        }

        return $monthlyData;
    }

    /**
     * التحصيلات الشهرية لمجموعة موظفين (نفس طريقة EmployeePerformanceController)
     */
    private function getMonthlyEmployeeCollections($employeeIds)
    {
        $monthlyData = array_fill(0, 12, 0);

        if (empty($employeeIds)) {
            return $monthlyData;
        }

        // جلب حسابات الخزينة والبنوك
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        if (empty($treasuryAndBankAccounts)) {
            return $monthlyData;
        }

        $results = JournalEntryDetail::select(
            DB::raw('MONTH(journal_entries.date) as month'),
            DB::raw('SUM(journal_entry_details.debit) as total_debit')
        )
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entries.created_by_employee', $employeeIds)
            ->where('journal_entries.status', 1)
            ->whereYear('journal_entries.date', Carbon::now()->year)
            ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
            ->where('journal_entry_details.debit', '>', 0)
        // استثناء التحويلات: لا يوجد دائن في نفس القيد لحسابات الخزينة/البنوك
            ->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
                $subQuery->select(DB::raw(1))
                    ->from('journal_entry_details as jed2')
                    ->whereColumn('jed2.journal_entry_id', 'journal_entries.id')
                    ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                    ->where('jed2.credit', '>', 0);
            })
            ->groupBy('month')
            ->get();

        foreach ($results as $result) {
            $monthlyData[$result->month - 1] = (float) ($result->total_debit ?? 0);
        }

        return $monthlyData;
    }

    /**
     * المردودات الشهرية لمجموعة موظفين (نفس طريقة EmployeePerformanceController)
     */
    private function getMonthlyEmployeeReturns($employeeIds)
    {
        $monthlyData = array_fill(0, 12, 0);

        if (empty($employeeIds)) {
            return $monthlyData;
        }

        $results = JournalEntryDetail::select(
            DB::raw('MONTH(journal_entries.date) as month'),
            DB::raw('SUM(journal_entry_details.debit) as total_debit'),
            DB::raw('SUM(journal_entry_details.credit) as total_credit')
        )
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entries.created_by_employee', $employeeIds)
            ->where('journal_entries.status', 1)
            ->whereYear('journal_entries.date', Carbon::now()->year)
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES_RETURNS)
            ->groupBy('month')
            ->get();

        foreach ($results as $result) {
            $monthlyData[$result->month - 1] = ($result->total_debit ?? 0) - ($result->total_credit ?? 0);
        }

        return $monthlyData;
    }

    /**
     * تطبيق فلتر الفرع على القيد المحاسبي
     */
    private function applyBranchFilter($query, $branchId, $isMainBranch)
    {
        if (! $isMainBranch && $branchId) {
            $query->where(function ($branchQuery) use ($branchId) {
                $branchQuery->whereHas('client', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                })->orWhereHas('invoice.client', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            });
        }
    }

    /**
     * إجمالي المبيعات — يشمل الفرع + territories الأبناء
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getTotalSales($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES);

        if ($arr) {
            $query->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $arr);
        }

        $result = $query->selectRaw('SUM(journal_entry_details.credit) - SUM(journal_entry_details.debit) as total')->first();

        return $result->total ?? 0;
    }

    /**
     * إجمالي التحصيلات من القيود — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getTotalCollections($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        if (empty($treasuryAndBankAccounts)) {
            return 0;
        }

        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;
        if (! $isMainBranch && ! $arr) {
            $userBranchId = User::find(auth()->user()->id)?->branch_id;
            $arr = $userBranchId ? $this->expandBranchToDescendants($userBranchId) : null;
        }

        return $this->getCollectionsForBranchByClient($arr, $isMainBranch, $startDate, $endDate, $treasuryAndBankAccounts);
    }

    /**
     * جلب التحصيلات — يقبل array من معرفات الفروع (يشمل territories)
     *
     * @param  array<int>|null  $branchIds
     */
    private function getCollectionsForBranchByClient($branchIds, $isMainBranch, $startDate, $endDate, $treasuryAndBankAccounts)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
            ->where('journal_entry_details.debit', '>', 0)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1);

        if ($arr) {
            $query->whereIn('clients.branch_id', $arr);
        } else {
            $query->whereNotNull('journal_entries.client_id');
        }

        // استثناء التحويلات بين الخزائن/البنوك
        $query->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
            $subQuery->select(DB::raw(1))
                ->from('journal_entry_details as jed2')
                ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                ->where('jed2.credit', '>', 0);
        });

        $totalCollected = $query->sum('journal_entry_details.debit') ?? 0;

        // طرح مبالغ إرجاع المبيعات المدفوعة من الخزينة (قيود SR - فواتير المرتجع)
        $returnQuery = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
            ->where('journal_entry_details.credit', '>', 0)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->where('journal_entries.reference_type', ReferenceType::SALES_RETURN->value);

        if ($arr) {
            $returnQuery->whereIn('clients.branch_id', $arr);
        } else {
            $returnQuery->whereNotNull('journal_entries.client_id');
        }

        $totalReturned = $returnQuery->sum('journal_entry_details.credit') ?? 0;

        return max(0, $totalCollected - $totalReturned);
    }

    /**
     * إجمالي مردود المبيعات — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getTotalSalesReturns($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;
        if (! $isMainBranch && ! $arr) {
            $userBranchId = User::find(auth()->user()->id)?->branch_id;
            $arr = $userBranchId ? $this->expandBranchToDescendants($userBranchId) : null;
        }

        return $this->getReturnsForBranchByClient($arr, $isMainBranch, $startDate, $endDate);
    }

    /**
     * جلب المردودات — يقبل array
     *
     * @param  array<int>|null  $branchIds
     */
    private function getReturnsForBranchByClient($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES_RETURNS)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1);

        if ($arr) {
            $query->whereIn('clients.branch_id', $arr);
        }

        $result = $query->selectRaw('SUM(journal_entry_details.debit) - SUM(journal_entry_details.credit) as total')->first();

        return $result->total ?? 0;
    }

    /**
     * المبيعات الشهرية — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getMonthlySales($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 1)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES)
            ->select(
                DB::raw('MONTH(journal_entries.date) as month'),
                DB::raw('SUM(journal_entry_details.credit) - SUM(journal_entry_details.debit) as total')
            );

        if ($arr) {
            $query->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $arr);
        }

        $results = $query->groupBy('month')->get()->keyBy('month');

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[] = isset($results[$m]) ? (float) $results[$m]->total : 0;
        }

        return $monthlyData;
    }

    /**
     * التحصيلات الشهرية — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getMonthlyCollections($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $monthlyData = array_fill(0, 12, 0);

        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        if (empty($treasuryAndBankAccounts)) {
            return $monthlyData;
        }

        return $this->getMonthlyCollectionsForBranchByClient($branchIds, $isMainBranch, $startDate, $endDate, $treasuryAndBankAccounts);
    }

    /**
     * جلب التحصيلات الشهرية — يقبل array
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getMonthlyCollectionsForBranchByClient($branchIds, $isMainBranch, $startDate, $endDate, $treasuryAndBankAccounts)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;
        $monthlyData = array_fill(0, 12, 0);

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
            ->where('journal_entry_details.debit', '>', 0)
            ->where('journal_entries.status', 1)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->select(
                DB::raw('MONTH(journal_entries.date) as month'),
                DB::raw('SUM(journal_entry_details.debit) as total')
            );

        if ($arr) {
            $query->whereIn('clients.branch_id', $arr);
        } else {
            $query->whereNotNull('journal_entries.client_id');
        }

        $query->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
            $subQuery->select(DB::raw(1))
                ->from('journal_entry_details as jed2')
                ->whereColumn('jed2.journal_entry_id', 'journal_entries.id')
                ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                ->where('jed2.credit', '>', 0);
        });

        $results = $query->groupBy('month')->get()->keyBy('month');

        foreach ($results as $month => $result) {
            $monthlyData[$month - 1] = (float) $result->total;
        }

        $returnQuery = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->leftJoin('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
            ->where('journal_entry_details.credit', '>', 0)
            ->where('journal_entries.status', 1)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.reference_type', ReferenceType::SALES_RETURN->value)
            ->select(
                DB::raw('MONTH(journal_entries.date) as month'),
                DB::raw('SUM(journal_entry_details.credit) as total_returned')
            );

        if ($arr) {
            $returnQuery->whereIn('clients.branch_id', $arr);
        } else {
            $returnQuery->whereNotNull('journal_entries.client_id');
        }

        $returnResults = $returnQuery->groupBy('month')->get()->keyBy('month');

        foreach ($returnResults as $month => $result) {
            $idx = $month - 1;
            $monthlyData[$idx] = max(0, $monthlyData[$idx] - (float) $result->total_returned);
        }

        return $monthlyData;
    }

    /**
     * مردود المبيعات الشهري — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getMonthlyReturns($branchIds, $isMainBranch, $startDate, $endDate)
    {
        return $this->getMonthlyReturnsForBranchByClient($branchIds, $isMainBranch, $startDate, $endDate);
    }

    /**
     * جلب المردودات الشهرية — يقبل array
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getMonthlyReturnsForBranchByClient($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;
        $monthlyData = array_fill(0, 12, 0);

        $query = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES_RETURNS)
            ->where('journal_entries.status', 1)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->select(
                DB::raw('MONTH(journal_entries.date) as month'),
                DB::raw('SUM(journal_entry_details.debit) - SUM(journal_entry_details.credit) as total')
            );

        if ($arr) {
            $query->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $arr);
        }

        $results = $query->groupBy('month')->get()->keyBy('month');

        foreach ($results as $month => $result) {
            $monthlyData[$month - 1] = (float) $result->total;
        }

        return $monthlyData;
    }

    /**
     * عدد الفواتير — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getInvoiceCount($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        $query = Invoice::where('type', 'normal')
            ->whereNull('reference_number')
            ->whereBetween('invoices.created_at', [$startDate, $endDate]);

        if ($arr) {
            $query->join('clients', 'invoices.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $arr);
        }

        return $query->count();
    }

    /**
     * عدد الفواتير الشهري — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getMonthlyInvoiceCounts($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        $query = DB::table('invoices')
            ->where('invoices.type', 'normal')
            ->whereNull('invoices.reference_number')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->select(DB::raw('MONTH(invoices.created_at) as month'), DB::raw('COUNT(invoices.id) as total'));

        if ($arr) {
            $query->join('clients', 'invoices.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $arr);
        }

        $results = $query->groupBy('month')->get()->keyBy('month');

        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[] = isset($results[$m]) ? (int) $results[$m]->total : 0;
        }

        return $monthlyData;
    }

    /**
     * إحصائيات الفروع — يقبل array من معرفات الفروع
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getBranchStatistics($branchIds, $isMainBranch, $startDate, $endDate)
    {
        // إذا لم يكن الفرع الرئيسي، نعيد بيانات الفرع المحدد فقط (مع territories)
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;
        if (! $isMainBranch) {
            $primaryId = $arr[0] ?? null;
            $branch = $primaryId ? Branch::find($primaryId) : null;

            return [
                'labels' => [$branch->name ?? 'الفرع الحالي'],
                'sales' => [$this->getTotalSales($arr, false, $startDate, $endDate)],
                'returns' => [$this->getTotalSalesReturns($arr, false, $startDate, $endDate)],
                'payments' => [$this->getTotalCollections($arr, false, $startDate, $endDate)],
            ];
        }

        // --- الفرع الرئيسي: جلب بيانات كل الفروع باستعلامات مجمعة ---

        // 1. جلب كل الفروع
        $branches = Branch::all()->keyBy('id');
        $branchIds = $branches->pluck('id')->toArray();

        // تهيئة المصفوفات بالقيم الصفرية
        $salesData = array_fill_keys($branchIds, 0);
        $returnsData = array_fill_keys($branchIds, 0);
        $collectionsData = array_fill_keys($branchIds, 0);

        // 2. جلب المبيعات مجمعة حسب الفرع
        $salesQueryResult = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES)
            ->whereNotNull('clients.branch_id')
            ->select(
                'clients.branch_id',
                DB::raw('SUM(journal_entry_details.credit) - SUM(journal_entry_details.debit) as total_sales')
            )
            ->groupBy('clients.branch_id')
            ->get();

        foreach ($salesQueryResult as $row) {
            $salesData[$row->branch_id] = (float) $row->total_sales;
        }

        // 3. جلب المردودات مجمعة حسب الفرع
        $returnsQueryResult = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES_RETURNS)
            ->select('clients.branch_id', DB::raw('SUM(journal_entry_details.debit) - SUM(journal_entry_details.credit) as total'))
            ->groupBy('clients.branch_id')
            ->get();

        foreach ($returnsQueryResult as $row) {
            $returnsData[$row->branch_id] = (float) $row->total;
        }

        // 4. جلب التحصيلات مجمعة حسب الفرع
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        if (! empty($treasuryAndBankAccounts)) {
            $collectionsQueryResult = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereBetween('journal_entries.date', [$startDate, $endDate])
                ->where('journal_entries.status', 1)
                ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
                ->where('journal_entry_details.debit', '>', 0)
                // استثناء التحويلات: القيد الذي يحتوي على دائن في حسابات الخزينة/البنوك
                ->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
                    $subQuery->select(DB::raw(1))
                        ->from('journal_entry_details as jed2')
                        ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                        ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                        ->where('jed2.credit', '>', 0);
                })
                ->select('clients.branch_id', DB::raw('SUM(journal_entry_details.debit) as total'))
                ->groupBy('clients.branch_id')
                ->get();

            foreach ($collectionsQueryResult as $row) {
                $collectionsData[$row->branch_id] = (float) $row->total;
            }

            // طرح مبالغ إرجاع المبيعات من التحصيلات (قيود SR) حسب الفرع
            $returnCollectionsResult = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereBetween('journal_entries.date', [$startDate, $endDate])
                ->where('journal_entries.status', 1)
                ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
                ->where('journal_entry_details.credit', '>', 0)
                ->where('journal_entries.reference_type', ReferenceType::SALES_RETURN->value)
                ->select('clients.branch_id', DB::raw('SUM(journal_entry_details.credit) as total_returned'))
                ->groupBy('clients.branch_id')
                ->get();

            foreach ($returnCollectionsResult as $row) {
                if (isset($collectionsData[$row->branch_id])) {
                    $collectionsData[$row->branch_id] = max(0, $collectionsData[$row->branch_id] - (float) $row->total_returned);
                }
            }
        }

        // بناء المصفوفات النهائية
        $labels = [];
        $finalSales = [];
        $finalReturns = [];
        $finalCollections = [];

        foreach ($branches as $id => $branch) {
            $labels[] = $branch->name;
            $finalSales[] = $salesData[$id] ?? 0;
            $finalReturns[] = $returnsData[$id] ?? 0;
            $finalCollections[] = $collectionsData[$id] ?? 0;
        }

        return [
            'labels' => $labels,
            'sales' => $finalSales,
            'returns' => $finalReturns,
            'payments' => $finalCollections,
        ];
    }

    /**
     * إحصائيات المجموعات — territories من جدول branches بعد ترحيل region_groubs
     *
     * @param  int|array<int>|null  $branchIds
     */
    private function getGroupsStatistics($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        // 1. جلب المجموعات (territories): branches بـ is_territory=1
        $groupsQuery = Branch::where('is_territory', 1);
        if ($arr) {
            // نريد فقط territories ضمن الـ scope (الـ ID نفسه أو parent_id ضمن branchIds)
            $groupsQuery->where(function ($q) use ($arr) {
                $q->whereIn('id', $arr)->orWhereIn('parent_id', $arr);
            });
        }
        $allGroups = $groupsQuery->get(['id', 'name'])->keyBy('id');

        if ($allGroups->isEmpty()) {
            return collect();
        }

        $groupIds = $allGroups->pluck('id')->toArray();

        // 2. المبيعات والمردودات حسب المجموعة (territory)
        // العملاء الآن مربوطون بـ branch_id الذي يساوي territory.id بعد الترحيل
        $salesAndReturns = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereIn('clients.branch_id', $groupIds)
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->whereIn('journal_entry_details.account_id', [SystemAccountIds::SALES, SystemAccountIds::SALES_RETURNS])
            ->select(
                'clients.branch_id as group_id',
                DB::raw('SUM(CASE WHEN journal_entry_details.account_id = '.SystemAccountIds::SALES.' THEN journal_entry_details.credit - journal_entry_details.debit ELSE 0 END) as sales'),
                DB::raw('SUM(CASE WHEN journal_entry_details.account_id = '.SystemAccountIds::SALES_RETURNS.' THEN journal_entry_details.debit - journal_entry_details.credit ELSE 0 END) as returns')
            )
            ->groupBy('clients.branch_id')
            ->get()
            ->keyBy('group_id');

        // 3. التحصيلات
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        $collections = collect();
        if (! empty($treasuryAndBankAccounts)) {
            $collections = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $groupIds)
                ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
                ->where('journal_entry_details.debit', '>', 0)
                ->whereBetween('journal_entries.date', [$startDate, $endDate])
                ->where('journal_entries.status', 1)
                ->whereNotExists(function ($subQuery) use ($treasuryAndBankAccounts) {
                    $subQuery->select(DB::raw(1))
                        ->from('journal_entry_details as jed2')
                        ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                        ->whereIn('jed2.account_id', $treasuryAndBankAccounts)
                        ->where('jed2.credit', '>', 0);
                })
                ->select(
                    'clients.branch_id as group_id',
                    DB::raw('SUM(journal_entry_details.debit) as collections')
                )
                ->groupBy('clients.branch_id')
                ->get()
                ->keyBy('group_id');

            $returnCollections = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $groupIds)
                ->whereIn('journal_entry_details.account_id', $treasuryAndBankAccounts)
                ->where('journal_entry_details.credit', '>', 0)
                ->whereBetween('journal_entries.date', [$startDate, $endDate])
                ->where('journal_entries.status', 1)
                ->where('journal_entries.reference_type', ReferenceType::SALES_RETURN->value)
                ->select(
                    'clients.branch_id as group_id',
                    DB::raw('SUM(journal_entry_details.credit) as returned')
                )
                ->groupBy('clients.branch_id')
                ->get()
                ->keyBy('group_id');

            $collections = $collections->map(function ($item) use ($returnCollections) {
                $returned = $returnCollections->get($item->group_id)?->returned ?? 0;
                $item->collections = max(0, $item->collections - $returned);

                return $item;
            });
        }

        // 4. عدد الفواتير
        $invoiceCounts = DB::table('invoices')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereIn('clients.branch_id', $groupIds)
            ->where('invoices.type', 'normal')
            ->whereNull('invoices.reference_number')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->select('clients.branch_id as group_id', DB::raw('COUNT(DISTINCT invoices.id) as count'))
            ->groupBy('clients.branch_id')
            ->get()
            ->keyBy('group_id');

        // بناء المصفوفة النهائية
        $result = collect();
        foreach ($allGroups as $id => $group) {
            $sr = $salesAndReturns->get($id);
            $c = $collections->get($id);
            $ic = $invoiceCounts->get($id);

            $sales = $sr ? (float) $sr->sales : 0;
            $returns = $sr ? (float) $sr->returns : 0;
            $totalCollections = $c ? (float) $c->collections : 0;
            $count = $ic ? (int) $ic->count : 0;

            if ($sales > 0 || $returns > 0 || $totalCollections > 0) {
                $result->push([
                    'region_id' => $id,
                    'region' => $group->name,
                    'sales' => $sales,
                    'returns' => $returns,
                    'payments' => $totalCollections,
                    'receipts' => 0,
                    'collections' => $totalCollections,
                    'invoice_count' => $count,
                ]);
            }
        }

        return $result->sortByDesc('collections')->values();
    }

    /**
     * جلب المدفوعات وسندات القبض مجمعة حسب الموظف — يشمل territories
     *
     * @param  int|array<int>|null  $branchIds
     */
    public function getPaymentsReceiptsByEmployee($branchIds, $isMainBranch, $startDate, $endDate)
    {
        $arr = ! empty($branchIds) ? (is_array($branchIds) ? $branchIds : [$branchIds]) : null;

        // جلب الموظفين (يشمل موظفي territories)
        $employees = User::whereIn('role', ['employee', 'manager'])
            ->when($arr, fn ($q) => $q->whereIn('branch_id', $arr))
            ->get()
            ->keyBy('id');

        if ($employees->isEmpty()) {
            return ['labels' => [], 'payments' => [], 'receipts' => []];
        }

        $employeeIds = $employees->pluck('id')->toArray();

        // 1. المدفوعات مجمعة حسب الموظف
        $paymentsQuery = DB::table('payments_process')
            ->join('invoices', 'payments_process.invoice_id', '=', 'invoices.id')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereBetween('payments_process.created_at', [$startDate, $endDate])
            ->where('invoices.type', 'normal')
            ->whereNull('invoices.reference_number')
            ->select(
                DB::raw('COALESCE(payments_process.employee_id, invoices.created_by) as emp_id'),
                DB::raw('SUM(payments_process.amount) as total')
            );

        if ($arr) {
            $paymentsQuery->whereIn('clients.branch_id', $arr);
        }

        $paymentsData = $paymentsQuery
            ->whereIn(DB::raw('COALESCE(payments_process.employee_id, invoices.created_by)'), $employeeIds)
            ->groupBy('emp_id')
            ->get()
            ->keyBy('emp_id');

        // 2. سندات القبض
        $receiptsQuery = DB::table('receipts')
            ->whereIn('receipts.created_by', $employeeIds)
            ->whereBetween('receipts.created_at', [$startDate, $endDate])
            ->select('receipts.created_by as emp_id', DB::raw('SUM(receipts.amount) as total'));

        if ($arr) {
            $receiptsQuery->join('accounts', 'receipts.account_id', '=', 'accounts.id')
                ->join('clients', 'accounts.client_id', '=', 'clients.id')
                ->whereIn('clients.branch_id', $arr);
        }

        $receiptsData = $receiptsQuery
            ->groupBy('emp_id')
            ->get()
            ->keyBy('emp_id');

        // 3. بناء النتائج
        $chartLabels = [];
        $chartPayments = [];
        $chartReceipts = [];

        foreach ($employees as $id => $employee) {
            $paymentsTotal = isset($paymentsData[$id]) ? (float) $paymentsData[$id]->total : 0;
            $receiptsTotal = isset($receiptsData[$id]) ? (float) $receiptsData[$id]->total : 0;

            if ($paymentsTotal > 0 || $receiptsTotal > 0) {
                $chartLabels[] = $employee->name;
                $chartPayments[] = $paymentsTotal;
                $chartReceipts[] = $receiptsTotal;
            }
        }

        return [
            'labels' => $chartLabels,
            'payments' => $chartPayments,
            'receipts' => $chartReceipts,
        ];
    }

    // ============================================================================
    // Helpers: شجرة الفروع — توسيع الفرع لمعرفاته + جميع الـ territories التابعة
    // ============================================================================

    /**
     * يُرجع معرف الفرع + معرفات كل الأبناء (للفلترة بالبيانات فقط — بدون آباء).
     *
     * @return array<int>|null
     */
    private function expandBranchToDescendants($branchId): ?array
    {
        if (empty($branchId)) {
            return null;
        }

        $branch = Branch::withoutGlobalScopes()->find((int) $branchId);
        if (! $branch) {
            return [(int) $branchId];
        }

        return $branch->descendantBranchIds()
            ->push((int) $branch->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * يُرجع الفرع + أبناءه + آباؤه (مثل CRM) — لعرض الشجرة الهرمية الكاملة.
     * يُستخدم لعرض الشجرة فقط وليس لفلترة البيانات.
     *
     * @return array<int>|null
     */
    private function expandBranchWithAncestors($branchId): ?array
    {
        if (empty($branchId)) {
            return null;
        }

        $branch = Branch::withoutGlobalScopes()->find((int) $branchId);
        if (! $branch) {
            return [(int) $branchId];
        }

        return $branch->descendantBranchIds()
            ->push((int) $branch->id)
            ->merge($branch->ancestors()->pluck('id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * يطبّق فلتر "ضمن مصفوفة معرفات فروع" على query builder.
     */
    private function applyBranchIdsFilter($query, $branchIds, string $col = 'clients.branch_id'): void
    {
        if (empty($branchIds)) {
            return;
        }
        $arr = is_array($branchIds) ? $branchIds : [$branchIds];
        $query->whereIn($col, $arr);
    }

    /**
     * يبني شجرة الأداء: فرع رئيسي → territories الأبناء → أداؤهم (مبيعات/تحصيلات/مردودات/عملاء).
     *
     * @return array<int, array{
     *   id:int, name:string, parent_id:?int, depth:int, is_territory:bool,
     *   sales:float, collections:float, returns:float, clients_count:int,
     *   rolled_sales:float, rolled_collections:float, rolled_returns:float, rolled_clients_count:int,
     *   children: array
     * }>
     */
    private function getBranchHierarchyPerformance(Carbon $startDate, Carbon $endDate, $rootBranchId = null): array
    {
        if ($rootBranchId) {
            // جلب الآباء والأبناء معاً (مثل CRM) لعرض الشجرة الكاملة
            $allowedIds = $this->expandBranchWithAncestors($rootBranchId) ?? [];
            $branches = Branch::withoutGlobalScopes()->whereIn('id', $allowedIds)
                ->orderBy('parent_id')->orderBy('name')->get();
        } else {
            $branches = Branch::withoutGlobalScopes()
                ->orderBy('parent_id')->orderBy('name')->get();
        }

        if ($branches->isEmpty()) {
            return [];
        }

        $branchIds = $branches->pluck('id');

        // عملاء لكل فرع (دفعة واحدة)
        $clientsCountByBranch = DB::table('clients')
            ->select('branch_id', DB::raw('COUNT(*) as c'))
            ->whereIn('branch_id', $branchIds)
            ->groupBy('branch_id')
            ->pluck('c', 'branch_id')
            ->all();

        $treasuryAndBank = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        // المبيعات لكل branch_id
        $salesByBranch = [];
        $rows = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES)
            ->whereIn('clients.branch_id', $branchIds)
            ->groupBy('clients.branch_id')
            ->select('clients.branch_id', DB::raw('SUM(journal_entry_details.credit) - SUM(journal_entry_details.debit) as total'))
            ->get();
        foreach ($rows as $r) {
            $salesByBranch[(int) $r->branch_id] = (float) $r->total;
        }

        // المردودات لكل branch_id
        $returnsByBranch = [];
        $rows = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->where('journal_entries.status', 1)
            ->where('journal_entry_details.account_id', SystemAccountIds::SALES_RETURNS)
            ->whereIn('clients.branch_id', $branchIds)
            ->groupBy('clients.branch_id')
            ->select('clients.branch_id', DB::raw('SUM(journal_entry_details.debit) - SUM(journal_entry_details.credit) as total'))
            ->get();
        foreach ($rows as $r) {
            $returnsByBranch[(int) $r->branch_id] = (float) $r->total;
        }

        // التحصيلات الصافية لكل branch_id
        $collectionsByBranch = [];
        if (! empty($treasuryAndBank)) {
            $rows = DB::table('journal_entry_details')
                ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
                ->join('clients', 'journal_entries.client_id', '=', 'clients.id')
                ->whereIn('journal_entry_details.account_id', $treasuryAndBank)
                ->where('journal_entry_details.debit', '>', 0)
                ->whereBetween('journal_entries.date', [$startDate, $endDate])
                ->where('journal_entries.status', 1)
                ->whereIn('clients.branch_id', $branchIds)
                ->whereNotExists(function ($q) use ($treasuryAndBank) {
                    $q->select(DB::raw(1))
                        ->from('journal_entry_details as jed2')
                        ->whereColumn('jed2.journal_entry_id', 'journal_entry_details.journal_entry_id')
                        ->whereIn('jed2.account_id', $treasuryAndBank)
                        ->where('jed2.credit', '>', 0);
                })
                ->groupBy('clients.branch_id')
                ->select('clients.branch_id', DB::raw('SUM(journal_entry_details.debit) as total'))
                ->get();
            foreach ($rows as $r) {
                $collectionsByBranch[(int) $r->branch_id] = (float) $r->total;
            }
        }

        // بناء الشجرة المتداخلة
        $byId = [];
        foreach ($branches as $b) {
            $byId[(int) $b->id] = [
                'id' => (int) $b->id,
                'name' => $b->name,
                'parent_id' => $b->parent_id ? (int) $b->parent_id : null,
                'is_territory' => (bool) ($b->is_territory ?? false),
                'depth' => 0,
                'sales' => $salesByBranch[(int) $b->id] ?? 0,
                'collections' => $collectionsByBranch[(int) $b->id] ?? 0,
                'returns' => $returnsByBranch[(int) $b->id] ?? 0,
                'clients_count' => (int) ($clientsCountByBranch[(int) $b->id] ?? 0),
                'children' => [],
            ];
        }

        // ربط الأبناء بآبائهم
        $roots = [];
        foreach ($byId as $id => &$node) {
            if ($node['parent_id'] && isset($byId[$node['parent_id']])) {
                $byId[$node['parent_id']]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        // حساب العمق + الإجماليات شاملة الأبناء (rolled-up)
        $rollUp = function (array &$node, int $depth) use (&$rollUp): void {
            $node['depth'] = $depth;
            $rolledSales = $node['sales'];
            $rolledCollections = $node['collections'];
            $rolledReturns = $node['returns'];
            $rolledClients = $node['clients_count'];
            foreach ($node['children'] as &$child) {
                $rollUp($child, $depth + 1);
                $rolledSales += $child['rolled_sales'];
                $rolledCollections += $child['rolled_collections'];
                $rolledReturns += $child['rolled_returns'];
                $rolledClients += $child['rolled_clients_count'];
            }
            unset($child);
            $node['rolled_sales'] = $rolledSales;
            $node['rolled_collections'] = $rolledCollections;
            $node['rolled_returns'] = $rolledReturns;
            $node['rolled_clients_count'] = $rolledClients;
        };

        foreach ($roots as &$root) {
            $rollUp($root, 0);
        }
        unset($root);

        return $roots;
    }
}
