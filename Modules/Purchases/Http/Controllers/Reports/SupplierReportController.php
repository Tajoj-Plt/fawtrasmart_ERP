<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Enums\PurchaseDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Support\DebtAge;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Purchases\Support\HeaderPickers;
use Modules\Purchases\Support\ReportPeriod;
use Modules\Purchases\Support\SupplierLedger;

/**
 * تقارير المورّدين — المشتريات والأرصدة وأعمار الديون.
 *
 * الأرقام كلّها من `SupplierLedger` لا من استعلامات محلية، فما يظهر هنا
 * يطابق لوحة المشتريات وكشف حساب المورّد رقماً برقم.
 */
class SupplierReportController extends Controller
{
    /* `RespectsAccountingPeriods` لمُنتقيَي السنة المالية والفترة المحاسبية
       في أعمار الديون (كما في تقرير العملاء). الطريقتان تعرّفان
       `excludeClosedRanges` بالجسم نفسِه — تُحسم لصالح نطاق التقارير. */
    use PurchaseReportScope, RespectsAccountingPeriods {
        PurchaseReportScope::excludeClosedRanges insteadof RespectsAccountingPeriods;
    }

    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً رغم أن مجموعة
     * `reports` في كتالوج `PurchasesPermissions` تشمل تقارير المشتريات
     * السبعة كلّها صراحةً.
     */
    public function __construct()
    {
        /* صلاحيةٌ لكلّ تقرير، لا بوابةٌ واحدة للثمانية.

           و`ajax`/`Data` تُحرَس بصلاحية شاشتها لا بالبوابة العامة: هي
           مصدرُ الأرقام نفسِه، وحراسةُ الصفحة وترْكُ مغذّيها مفتوحاً
           لا تحرس شيئاً. */
        $this->middleware('permission:purchases.reports.by_supplier', ['only' => [
            'index', 'bySupplier', 'unifiedSupplierReport', 'ajaxData', 'getReportData',
        ]]);
        $this->middleware('permission:purchases.reports.suppliers_directory', ['only' => [
            'directory', 'directoryAjaxData',
        ]]);
        $this->middleware('permission:purchases.reports.supplier_balances', ['only' => [
            'supplierBalances', 'supplierBalancesAjax',
        ]]);
        $this->middleware('permission:purchases.reports.debt_aging', ['only' => [
            'debtAging', 'debtAgingAjax', 'invoiceDebtAging', 'invoiceDebtAgingAjax', 'searchSuppliers',
        ]]);
        $this->middleware('permission:purchases.reports.export', ['only' => ['exportExcel']]);
    }

    // =================================================================================
    // المشتريات حسب المورّد
    // =================================================================================

    public function index(Request $request)
    {
        $data = $this->buildReport($request);

        return view('purchases::purchases.report.purchaseReport.Purchase_By_Supplier', array_merge([
            'suppliers' => $this->supplierOptions(),
            'branches' => $this->reportBranchOptions(),
            'users' => $this->employeeOptions(),
        ], $this->reportTreeContext($request), $data));
    }

    public function bySupplier(Request $request)
    {
        return $this->index($request);
    }

    public function unifiedSupplierReport(Request $request)
    {
        $data = $this->buildReport($request);

        return view('purchases::purchases.report.purchaseReport.unified_supplier_report', array_merge([
            'suppliers' => $this->supplierOptions(),
            'branches' => $this->reportBranchOptions(),
            'users' => $this->employeeOptions(),
        ], $this->reportTreeContext($request), $data));
    }

    public function ajaxData(Request $request)
    {
        try {
            $data = $this->buildReport($request);

            return response()->json([
                'success' => true,
                'data' => $data['reportData']->values(),
                'totals' => $data['totals'],
                'trend' => $data['trend'],
                'period' => $data['period']->key,
                'period_label' => $data['period']->labelWithDates(),
                'from_date' => $data['fromDate']->format('d/m/Y'),
                'to_date' => $data['toDate']->format('d/m/Y'),
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierReport', 'ajaxData');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getReportData(Request $request)
    {
        return $this->ajaxData($request);
    }

    /**
     * تصدير الملخّص — CSV بترميز UTF‑8 مع BOM ليفتحه إكسل عربياً سليماً.
     *
     * كان المسار `SupplierPurchasesExport` يشير إلى دالة غير موجودة، فكل
     * ضغطة على «تصدير» ترمي خطأ 500.
     */
    public function exportExcel(Request $request)
    {
        $data = $this->buildReport($request);

        $columns = [
            'المورد', 'الكود', 'رصيد أول المدة', 'عدد الفواتير', 'إجمالي المشتريات',
            'المرتجعات', 'إشعارات مدينة', 'صافي المشتريات', 'المدفوعات', 'رصيد آخر المدة', 'الحالة',
        ];

        $filename = 'purchases-by-supplier-'.$data['fromDate']->format('Ymd').'-'.$data['toDate']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data, $columns) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);

            foreach ($data['reportData'] as $row) {
                fputcsv($handle, [
                    $row['supplier_name'],
                    $row['supplier_code'],
                    number_format($row['opening_balance'], 2, '.', ''),
                    $row['purchases_count'],
                    number_format($row['purchases'], 2, '.', ''),
                    number_format($row['returns'], 2, '.', ''),
                    number_format($row['debit_notes'], 2, '.', ''),
                    number_format($row['net_purchases'], 2, '.', ''),
                    number_format($row['payments'], 2, '.', ''),
                    number_format($row['balance'], 2, '.', ''),
                    $row['status']['text'],
                ]);
            }

            $totals = $data['totals'];
            fputcsv($handle, [
                'الإجمالي العام', '',
                number_format($totals['opening_balance'], 2, '.', ''),
                $totals['purchases_count'],
                number_format($totals['purchases'], 2, '.', ''),
                number_format($totals['returns'], 2, '.', ''),
                number_format($totals['debit_notes'], 2, '.', ''),
                number_format($totals['net_purchases'], 2, '.', ''),
                number_format($totals['payments'], 2, '.', ''),
                number_format($totals['balance'], 2, '.', ''),
                '',
            ]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =================================================================================
    // دليل الموردين وأرصدتهم
    // =================================================================================

    public function directory(Request $request)
    {
        $data = $this->buildReport($request);

        return view('purchases::purchases.report.purchaseReport.suppliers_directory', array_merge([
            'branches' => $this->reportBranchOptions(),
            'suppliers' => $this->supplierOptions(),
        ], $this->reportTreeContext($request), $data));
    }

    public function directoryAjaxData(Request $request)
    {
        try {
            $data = $this->buildReport($request);

            $topSuppliers = $data['reportData']
                ->sortByDesc('purchases')
                ->take(10)
                ->values()
                ->map(fn ($item) => ['name' => $item['supplier_name'], 'amount' => round($item['purchases'], 2)]);

            return response()->json([
                'success' => true,
                'data' => $data['reportData']->values(),
                'totals' => $data['totals'],
                'from_date' => $data['fromDate']->format('Y-m-d'),
                'to_date' => $data['toDate']->format('Y-m-d'),
                'chart_data' => [
                    'top_suppliers' => $topSuppliers,
                    'balance_distribution' => [
                        'labels' => ['دائن (لهم)', 'مدين (عليهم)', 'متزن'],
                        'values' => [
                            $data['reportData']->where('balance', '>', 0.009)->count(),
                            $data['reportData']->where('balance', '<', -0.009)->count(),
                            $data['reportData']->filter(fn ($r) => abs($r['balance']) <= 0.009)->count(),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierReport', 'directoryAjaxData');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * أرصدة الموردين — مرآةُ «أرصدة العملاء» (`CustomerBalancesController`)
     * بابَ باب، والطرفُ وحدَه مبدَّل.
     *
     * الشاشةُ تقرأ ما تقرؤه أختُها: `from_date`/`to_date` (و`date_from`/`date_to`
     * لروابطَ محفوظة)، و`fiscal_year_id`/`accounting_period_id`، و`supplier_id`
     * بدل `customer`، و`responsible_employee` بدل `sales_manager`، و`status`،
     * و`city` بدل `neighborhood`، و`balance_side`، و`view_type`، و`tree_node`/
     * `exact_node`، و`show_zero`. وتبقى `employee_id` (منشئُ القيد) و
     * `payment_status` مقبولتين لمن حفظ رابطاً بهما.
     */
    public function supplierBalances(Request $request)
    {
        $data = $this->buildBalances($request);

        return view('purchases::purchases.report.purchaseReport.balance_suppliers', array_merge([
            'suppliers' => $this->supplierOptions(),
            'responsibleEmployees' => $this->responsibleEmployeeOptions(),
            'supplierStatuses' => self::SUPPLIER_STATUSES,
            'cities' => $this->supplierCityOptions(),
        ], $this->reportTreeContext($request), $data));
    }

    /**
     * الشكلُ نفسُه الذي يعيده `customerBalancesAjax`: `data` و`totals` و
     * `chart_data{labels, balances}` والمدى المطبَّق فعلاً.
     */
    public function supplierBalancesAjax(Request $request)
    {
        try {
            $data = $this->buildBalances($request);

            $top = array_slice($data['reportData'], 0, 10);

            return response()->json([
                'success' => true,
                'data' => $data['reportData'],
                'totals' => $data['totals'],
                'chart_data' => [
                    'labels' => array_column($top, 'supplier_name'),
                    'balances' => array_column($top, 'balance'),
                ],
                'from_date' => $data['periodContext']['start'],
                'to_date' => $data['periodContext']['end'],
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Purchases.SupplierReport', 'supplierBalancesAjax');

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحميل البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /** حالةُ المورّد كما تقرؤها شاشةُ الموردين: `1` نشط وما عداه موقوف */
    public const SUPPLIER_STATUSES = [
        'active' => ['label' => 'نشط', 'color' => 'success'],
        'inactive' => ['label' => 'موقوف', 'color' => 'secondary'],
    ];

    /**
     * صفوفُ التقرير وإجماليّاتُه — مصدرٌ واحدٌ للشاشة وللـAJAX معاً.
     *
     * ══════════ المعادلة ══════════
     *
     * حسابُ المورّد التزام، فالسطرُ الواحد يقرأ:
     *
     *     (قيودُ الرصيد الافتتاحي + حركاتٌ سابقة) + دائنُ المدى − مدينُه = الختامي
     *
     * والختاميُّ الموجبُ «دائن — له علينا»، والسالبُ «مدين — عليه لنا»؛ وهي
     * قاعدةُ {@see SupplierLedger} نفسُها فتطابق الأرقامُ كشفَ الحساب. وقيودُ
     * الرصيد الافتتاحي تُقرأ «رصيداً افتتاحياً» أينما وقع تاريخُها حتى تاريخ
     * القطع، فلا تدخل في مدين المدى ودائنه — كما في تقرير العملاء.
     *
     * @return array{reportData: array<int, array<string, mixed>>, totals: array<string, mixed>, fromDate: Carbon, toDate: Carbon, periodContext: array, isSummary: bool}
     */
    protected function buildBalances(Request $request): array
    {
        // الاسمان القديمان `date_from`/`date_to` يبقيان مفهومَين لروابطَ محفوظة
        $startKey = ! $request->filled('from_date') && $request->filled('date_from') ? 'date_from' : 'from_date';
        $endKey = ! $request->filled('to_date') && $request->filled('date_to') ? 'date_to' : 'to_date';

        $periodContext = $this->reportPeriodContext($request, $startKey, $endKey);
        $from = $periodContext['start'];
        $to = $periodContext['end'];
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        /* `$to` يقصّ الحركاتَ؛ و«الأيام» تُقاس إلى اليوم إن كان المدى ينتهي
           في المستقبل — والمدى الافتراضي ينتهي بنهاية السنة المالية. */
        $cut = Carbon::parse($to)->startOfDay();
        if ($cut->greaterThan(Carbon::today())) {
            $cut = Carbon::today();
        }

        $ledger = SupplierLedger::fromFilters(
            $this->intOrNull($request->input('supplier_id')),
            $this->intOrNull($request->input('employee_id')),
            $this->reportBranchIds($request)
        )->visibleTo($this->visibleCreatorIds());

        $accounts = $ledger->accounts();
        $accounts->loadMissing(['supplier.branch', 'supplier.employee']);

        // ما قبل المدى بلا استبعادٍ محاسبي (الرصيدُ حصيلةُ التاريخ كلِّه)، وحركةُ المدى كما في بقيّة التقارير
        $before = $ledger->sideTotals(null, $fromDate->copy()->subDay()->toDateString(), false);
        $range = $ledger->sideTotals($fromDate->toDateString(), $toDate->toDateString(), true);

        // «عمر الدين» — عمرُ أقدم مبلغٍ غير مسدَّد، لا «الأيام» منذ آخر فاتورة
        $debtAge = DebtAge::fromRequest($request);
        $debtAges = $debtAge !== null ? $this->supplierDebtAges($ledger, $toDate, $debtAge) : [];

        // أرصدةُ المحافظ — أموالُ المورّد لدينا، تُعرض ولا تُخلط بالذمّة
        $walletBalances = Account::wallets()
            ->whereIn('supplier_id', $accounts->pluck('supplier_id'))
            ->pluck('balance', 'supplier_id');

        $side = (string) $request->input('balance_side', '');
        $statusFilter = (string) $request->input('status', '');
        $cityFilter = trim((string) $request->input('city', ''));
        $responsible = $this->intOrNull($request->input('responsible_employee'));
        $paymentFilter = (string) $request->input('payment_status', '');
        $showZero = $request->boolean('show_zero');

        $rows = [];

        foreach ($accounts as $account) {
            /* حسابُ المحفظة يحمل `supplier_id` نفسَه ويعود من `accounts()` صفّاً
               ثانياً، بينما الدفترُ يستبعده ويجمع على المورّد — فلولا هذا
               الشرط لظهر المورّدُ مرّتين برصيدٍ واحدٍ يُحسب مرّتين في الإجمالي. */
            if (in_array((string) $account->type_accont, [Account::WALLET_CLIENT, Account::WALLET_SUPPLIER], true)) {
                continue;
            }

            $supplier = $account->supplier;
            $supplierId = (int) $account->supplier_id;

            // ── فلاترُ بطاقة المورّد (نظيرُ فلاتر العميل) ──────────────
            $isActive = (int) $supplier->status === 1;
            if ($statusFilter === 'active' && ! $isActive) {
                continue;
            }
            if ($statusFilter === 'inactive' && $isActive) {
                continue;
            }
            if ($cityFilter !== '' && trim((string) $supplier->city) !== $cityFilter) {
                continue;
            }
            if ($responsible && (int) $supplier->employee_id !== $responsible) {
                continue;
            }

            $prior = $before[$supplierId] ?? null;
            $period = $range[$supplierId] ?? null;
            $figures = $period['figures'] ?? SupplierLedger::emptyTotals();

            $priorMovements = $prior ? round((float) $prior['credit'] - (float) $prior['debit'], 2) : 0.0;
            $openingEntries = round(
                ($prior ? (float) $prior['opening_credit'] - (float) $prior['opening_debit'] : 0.0)
                + ($period ? (float) $period['opening_credit'] - (float) $period['opening_debit'] : 0.0),
                2
            );
            $rangeDebit = $period ? round((float) $period['debit'], 2) : 0.0;
            $rangeCredit = $period ? round((float) $period['credit'], 2) : 0.0;
            $balance = round($openingEntries + $priorMovements + $rangeCredit - $rangeDebit, 2);

            /* «نوع الرصيد»: دائن (له علينا) / مدين (عليه لنا) / صفري */
            if (($side === 'credit' && $balance <= 0.005)
                || ($side === 'debit' && $balance >= -0.005)
                || ($side === 'zero' && abs($balance) >= 0.005)) {
                continue;
            }

            // الصفريُّ يعني ختاميّاً صفراً بلا حركةٍ في المدى
            if (! $showZero && abs($balance) < 0.005 && $rangeDebit < 0.005 && $rangeCredit < 0.005) {
                continue;
            }

            $netPurchases = $figures['purchases'] - $figures['returns'] - $figures['debit_notes'];
            $paymentStatus = $this->paymentStatus($netPurchases, $figures['payments'], $figures);
            if ($paymentFilter !== '' && $paymentStatus['code'] !== $paymentFilter) {
                continue;
            }

            $age = $debtAge !== null ? ($debtAges[$supplierId] ?? null) : null;
            if ($debtAge !== null && (! $age || $age['older'] <= 0.005)) {
                continue;
            }

            $chargeDate = $period['last_charge'] ?? $prior['last_charge'] ?? null;
            $daysLate = $chargeDate ? (int) max(0, Carbon::parse($chargeDate)->startOfDay()->diffInDays($cut, false)) : 0;

            $lastMovement = max((string) ($prior['last_movement'] ?? ''), (string) ($period['last_movement'] ?? '')) ?: null;
            $branch = $supplier->branch;
            $statusKey = $isActive ? 'active' : 'inactive';

            $rows[] = [
                'supplier_id' => $supplierId,
                'account_id' => (int) $account->id,
                'supplier_name' => $supplier->name,
                'supplier_code' => $supplier->number_suply ?? $supplier->id,
                'phone' => $supplier->mobile ?: $supplier->phone,
                'branch_name' => $branch->name ?? 'غير محدد',
                'branch_id' => $supplier->branch_id,
                'employee_name' => $supplier->employee?->full_name ?: 'غير محدد',
                'employee_id' => $supplier->employee_id,
                'city' => $supplier->city ?: 'غير محدد',
                'status_name' => self::SUPPLIER_STATUSES[$statusKey]['label'],
                'status_color' => self::SUPPLIER_STATUSES[$statusKey]['color'],
                // «تابع لـ» — الجهةُ في الشجرة ونوعُها
                'affiliation_name' => $branch->name ?? 'غير محدد',
                'affiliation_type' => $branch ? $branch->displayTypeLabel() : '',

                // ── المعادلةُ المحاسبية للمدى ──────────────────────
                'opening_balance' => round($openingEntries + $priorMovements, 2),
                'opening_entries' => $openingEntries,
                'prior_movements' => $priorMovements,
                'total_debit' => $rangeDebit,
                'total_credit' => $rangeCredit,
                'balance' => $balance,
                'balance_type' => $balance >= 0 ? 'دائن' : 'مدين',

                // ── تفصيلُ الذمّة ──────────────────────────────────
                'invoices_total' => round($figures['purchases'], 2),
                'paid_total' => round($figures['payments'], 2),
                'returns_total' => round($figures['returns'] + $figures['debit_notes'], 2),
                'adjustments_total' => round($figures['adjustments'], 2),
                'payment_status' => $paymentStatus,
                'last_movement' => $lastMovement,
                'last_charge' => $chargeDate,

                // ── معلوماتٌ جانبية ───────────────────────────────
                'wallet_balance' => (float) ($walletBalances[$supplierId] ?? 0),
                'days_late' => $daysLate,
                // مع فلتر «عمر الدين» وحده
                'oldest_debt_days' => $age['oldest'] ?? null,
                'older_than_amount' => $age['older'] ?? null,
            ];
        }

        $rows = $this->sortBalanceRows($rows, $request->input('sort_by'), $request->input('sort_dir'));

        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);
        $balances = array_column($rows, 'balance');

        return [
            'reportData' => $rows,
            'totals' => [
                'total_suppliers' => count($rows),
                'opening_balance' => $sum('opening_balance'),
                'opening_entries' => $sum('opening_entries'),
                'prior_movements' => $sum('prior_movements'),
                'total_debit' => $sum('total_debit'),
                'total_credit' => $sum('total_credit'),
                'total_balance' => $sum('balance'),
                /* المستحقُّ للموردين والأرصدةُ المدينة **منفصلان**: الأوّل أرصدةُ
                   الدائنين (لهم علينا)، والثاني ما دفعناه زيادةً موجباً. */
                'payable_total' => round(array_sum(array_filter($balances, fn ($b) => $b > 0.005)), 2),
                'payable_suppliers' => count(array_filter($balances, fn ($b) => $b > 0.005)),
                'receivable_total' => round(abs(array_sum(array_filter($balances, fn ($b) => $b < -0.005))), 2),
                'receivable_suppliers' => count(array_filter($balances, fn ($b) => $b < -0.005)),
                'invoices_total' => $sum('invoices_total'),
                'paid_total' => $sum('paid_total'),
                'returns_total' => $sum('returns_total'),
                'debt_age' => $debtAge,
                'older_than_amount' => $debtAge !== null ? $sum('older_than_amount') : null,
            ],
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'periodContext' => $periodContext,
            'isSummary' => $request->input('view_type', 'summary') === 'summary',
        ];
    }

    /**
     * عمرُ أقدم مبلغٍ غير مسدَّد لكلّ مورّد، وما تجاوز الحدَّ منه — لفلتر «عمر الدين».
     *
     * بإطفاء {@see buildAging()} نفسِه: الرصيدُ الدائن يُوزَّع على قيود
     * المشتريات من الأحدث إلى الأقدم (ما سُدِّد يُطفئ أقدمَها)، والعمرُ إلى
     * اليوم. وما بقي بلا قيدٍ يقابله (رصيدٌ افتتاحيٌّ أقدمُ من كلّ الحركات)
     * يُعدّ أقدمَ من أيّ حدّ — كما يذهب إلى «+120» في تقرير الأعمار.
     *
     * @return array<int, array{oldest: int, older: float}>
     */
    protected function supplierDebtAges(SupplierLedger $ledger, Carbon $asOf, int $threshold): array
    {
        $today = now()->startOfDay();
        $credits = $ledger->creditsAsOf($asOf)->groupBy('supplier_id');
        $out = [];

        foreach ($ledger->balancesAsOf($asOf) as $supplierId => $balance) {
            if ($balance <= 0.005) {
                continue;
            }

            $remaining = (float) $balance;
            $oldest = 0;
            $older = 0.0;

            foreach ($credits->get($supplierId, collect()) as $movement) {
                if ($remaining <= 0.005) {
                    break;
                }

                $amount = min($remaining, (float) $movement->credit);
                $days = (int) Carbon::parse($movement->date)->startOfDay()->diffInDays($today);
                $remaining -= $amount;
                $oldest = max($oldest, $days);

                if (DebtAge::exceeds($days, $threshold)) {
                    $older += $amount;
                }
            }

            if ($remaining > 0.005) {
                $older += $remaining;
                $oldest = max($oldest, $threshold + 1);
            }

            $out[(int) $supplierId] = ['oldest' => $oldest, 'older' => round($older, 2)];
        }

        return $out;
    }

    /**
     * ترتيبُ الصفوف — الأطولُ تأخيراً أوّلاً كما في تقرير العملاء، ويُقبل
     * `sort_by`/`sort_dir` لمن أراد غيرَه.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function sortBalanceRows(array $rows, ?string $sortBy, ?string $direction): array
    {
        $allowed = ['days_late', 'balance', 'supplier_name', 'opening_entries', 'prior_movements', 'total_debit', 'total_credit'];
        $key = in_array($sortBy, $allowed, true) ? $sortBy : 'days_late';
        $asc = $direction === 'asc';

        usort($rows, function ($a, $b) use ($key, $asc) {
            $cmp = $key === 'supplier_name'
                ? strnatcasecmp((string) $a[$key], (string) $b[$key])
                : $a[$key] <=> $b[$key];

            return $asc ? $cmp : -$cmp;
        });

        return $rows;
    }

    /** «مسؤول المشتريات» — الموظّفُ المسؤول عن المورّد (`suppliers.employee_id`) */
    protected function responsibleEmployeeOptions()
    {
        $query = \Modules\Purchases\Support\HeaderPickers::employees();
        \App\Support\DataScope::constrainEmployee($query, 'id');

        return $query
            ->get(['id', 'first_name', 'middle_name', 'nickname', 'department_id'])
            ->sortBy(fn ($employee) => $employee->full_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** مدنُ الموردين لقائمة الفلتر — نظيرُ «الحي» في تقرير العملاء، على فرع الشريط العلويّ */
    protected function supplierCityOptions()
    {
        return HeaderPickers::suppliers(Supplier::query())
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    // =================================================================================
    // أعمار ديون الموردين
    // =================================================================================

    /**
     * شاشة أعمار الديون — تُبنى في الخادم كبقيّة التقارير.
     *
     * كانت الصفحة تُرسَل فارغةً ثم تُملأ بنداء AJAX، فلا يُطبع منها شيء
     * ولا يُشارَك رابطها محمّلاً بنتيجته. صارت تُرسل بنتيجتها كاملةً،
     * ويبقى `debtAgingAjax` لمن يناديه من خارج الشاشة.
     */
    public function debtAging(Request $request)
    {
        // كما في تقرير العملاء: الطلبُ نفسُه عبر AJAX يعيد JSON
        if ($request->ajax()) {
            return $this->debtAgingAjax($request);
        }

        $aging = $this->buildAging($request);

        /* «الموظف المسؤول» نظيرُ «مسؤول المبيعات» في تقرير العملاء —
           موظّفو المشتريات بحسب أقسام الإعدادات وفرع الشريط العلويّ. */
        $employees = \Modules\Purchases\Support\HeaderPickers::employees(\App\Models\Employee::orderBy('first_name'))->get();

        $selectedSupplier = $request->filled('supplier_id')
            ? \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::select('id', 'number_suply', 'trade_name', 'first_name', 'last_name'))->find($request->input('supplier_id'))
            : null;

        return view('purchases::purchases.report.purchaseReport.supplier_debt_aging', array_merge([
            'branches' => $this->reportBranchOptions(),
            'suppliers' => $this->supplierOptions(),
            'employees' => $employees,
            'selectedSupplier' => $selectedSupplier,
        ], $this->reportTreeContext($request), $aging));
    }

    public function debtAgingAjax(Request $request)
    {
        try {
            $aging = $this->buildAging($request);

            return response()->json([
                'success' => true,
                'data' => $aging['reportData'],
                'debtors' => $aging['debtors'],
                'totals' => $aging['totals'],
                'chart_data' => $aging['chartData'],
                // النطاقُ المطبَّق فعلاً لا ما كتبه المستخدم
                'from_date' => $aging['fromDate']->format('Y-m-d'),
                'to_date' => $aging['toDate']->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.SupplierReport', 'debtAgingAjax');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * قيمُ `aging_filter` (كما يرسلها تقريرُ العملاء) ← عمودُ الشريحة.
     *
     * @var array<string, string>
     */
    public const AGING_FILTERS = [
        'today' => 'today',
        '1-30' => 'days1to30',
        '31-60' => 'days31to60',
        '61-90' => 'days61to90',
        '91-120' => 'days91to120',
        '120+' => 'daysOver120',
    ];

    /**
     * أعمار ديون الموردين حتى تاريخ — صفوفها وإجمالياتها.
     *
     * ══════════ الشكلُ شكلُ تقرير العملاء ══════════
     *
     * الصفوفُ والإجماليات بمفاتيح `CustomerDebtAgingController::reportRows()`
     * نفسِها مع تبديل الطرف (عميل ← مورّد)، فالشاشةُ صورةٌ من شاشة «أعمار
     * ديون الأستاذ العام»: الرصيدُ الموجب هنا **دائن** (له علينا) وهو
     * المديونيةُ التي تُعمَّر، والسالبُ **مدين** (لنا عليه — دفعنا أكثر)
     * فيُفصل في قائمة `debtors` بلا أعمار، كما تُفصل أرصدةُ العملاء الدائنة.
     *
     * المعاملاتُ المقروءة: `from_date`/`to_date` أو `fiscal_year_id` أو
     * `accounting_period_id` (تاريخُ القطع = نهايةُ النطاق)، و`supplier_id`،
     * و`employee_id` (الموظّف المسؤول عن المورّد أو من أضافه بمستخدمه)،
     * وشجرةُ «تابع لـ»، و`aging_filter` (يقصّ العرضَ على شريحة)، و`show_zero`،
     * و`search` (اسم/كود)، و`sort` (`days_late` | `balance` | `supplier_name`)
     * مع `dir`. و`date_to` ومفاتيحُ `period` القديمة تبقى مفهومةً لروابطَ محفوظة.
     *
     * @return array{reportData: array, debtors: array, totals: array, chartData: array, fromDate: Carbon, toDate: Carbon, periodContext: array, buckets: array}
     */
    protected function buildAging(Request $request): array
    {
        /*
         * الفترة تُحدّد تاريخَ التعمير لا نطاقَ القيود.
         *
         * التعمير لقطةٌ تراكمية: رصيد المورّد عند تاريخٍ هو حاصل كل قيوده
         * قبله. فلو قُصر الحساب على قيود الفترة وحدها لسقط دَينٌ نشأ قبلها
         * وما زال قائماً — وهو الجزء الأخطر في التقرير أصلاً. لذلك يُقرأ
         * النطاقُ لتُؤخذ نهايتُه تاريخاً للنظر، ويبقى الرصيد تراكمياً —
         * وبدايتُه لا تقصّ شيئاً (كما في تقرير العملاء).
         *
         * ترتيبُ حسم تاريخ القطع: `date_to` صريح (روابطُ قديمة) ← مفتاحُ فترةٍ
         * قديم (`period=month`…) بلا تاريخ ← نهايةُ نطاق الفترة المحاسبية أو
         * السنة أو حقلَي التاريخ.
         */
        $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $legacyTab = (string) $request->input('period', '');

        if ($request->filled('date_to')) {
            $toDate = Carbon::parse($request->input('date_to'))->endOfDay();
        } elseif ($legacyTab !== '' && array_key_exists($legacyTab, ReportPeriod::TABS) && ! $request->filled('to_date')) {
            $toDate = ReportPeriod::make($legacyTab)->to->copy();
        } else {
            $toDate = Carbon::parse($periodContext['end'])->endOfDay();
        }

        $fromDate = Carbon::parse($periodContext['start'])->startOfDay();

        $ledger = SupplierLedger::fromFilters(
            $this->intOrNull($request->input('supplier_id')),
            null,
            $this->reportBranchIds($request)
        )->visibleTo($this->visibleCreatorIds());

        /*
         * الأرصدة والحركات تُجلبان دفعةً واحدة لكل الموردين.
         *
         * **العلّة التي كانت تُفرغ التقرير:** الرصيد كان يُقرأ بـ
         * `JournalEntryDetail::…->selectRaw('… as balance')->first()->balance`،
         * و`JournalEntryDetail` عليه `getBalanceAttribute()` يعيد
         * `credit - debit`. المُلحِق يتقدّم على العمود المحسوب دائماً،
         * وفي صفّ تجميعيٍّ لا `credit` فيه ولا `debit` يخرج
         * `null - null = 0`. فكل مورّد كان يُقاس رصيده صفراً ويُستبعد،
         * والجدول يظهر فارغاً مهما بلغت الديون. الاستعلام هنا على
         * `DB` لا على الموديل، فلا مُلحِق يعترض القيمة.
         *
         * ومعها زال استعلامان لكل مورّد (وثالثٌ يحمّل قيوده كاملةً)،
         * فصار التقرير استعلامين اثنين مهما كثر الموردون.
         */
        $balances = $ledger->balancesAsOf($toDate);
        $creditsBySupplier = $ledger->creditsAsOf($toDate)->groupBy('supplier_id');

        $suppliersQuery = Supplier::with('employee')->whereIn('id', array_keys($balances));

        /* «الموظف المسؤول» بمعنى «مسؤول المبيعات» في تقرير العملاء: المورّدُ
           المسنَد إلى الموظّف (`suppliers.employee_id`) أو المضافُ بمستخدمه. */
        if ($employeeId = $this->intOrNull($request->input('employee_id'))) {
            $suppliersQuery->where(fn ($q) => $q
                ->where('employee_id', $employeeId)
                ->orWhereIn('created_by', \App\Models\User::where('employee_id', $employeeId)->select('id')));
        }

        $suppliers = $suppliersQuery->get()->keyBy('id');

        // محفظةُ المورّد — أموالُه لدينا، منفصلةٌ عن ذمّته (كما محفظةُ العميل هناك)
        $walletBalances = Account::wallets()
            ->whereIn('supplier_id', $suppliers->keys())
            ->pluck('balance', 'supplier_id');

        $bucketColumn = self::AGING_FILTERS[(string) $request->input('aging_filter')] ?? null;
        $debtAge = DebtAge::fromRequest($request);
        $search = trim((string) $request->input('search', ''));
        $showZero = $request->boolean('show_zero');
        $today = now()->startOfDay();

        $rows = [];
        $debtors = [];

        foreach ($balances as $supplierId => $currentBalance) {
            $supplier = $suppliers->get($supplierId);

            if (! $supplier) {
                continue;
            }

            if (! $showZero && abs($currentBalance) < 0.005) {
                continue;
            }

            if ($search !== '' && mb_stripos($supplier->name.' '.($supplier->number_suply ?? ''), $search) === false) {
                continue;
            }

            $buckets = [
                'today' => 0, 'days1to30' => 0, 'days31to60' => 0,
                'days61to90' => 0, 'days91to120' => 0, 'daysOver120' => 0,
            ];
            // عمرُ أقدم حركةٍ في كلّ شريحة — منه «الأيام» (أقدمُ بندٍ مفتوح)
            $bucketDays = array_fill_keys(array_keys($buckets), 0);
            $itemsCount = 0;
            $keptItems = 0;
            $olderAmount = 0.0;

            if ($currentBalance > 0) {
                // المديونية تُعمَّر من الأحدث إلى الأقدم: ما سُدِّد يُطفئ
                // أقدم الفواتير، فالباقي هو آخر ما نشأ.
                $remaining = $currentBalance;

                foreach ($creditsBySupplier->get($supplierId, collect()) as $movement) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $amount = min($remaining, (float) $movement->credit);
                    $days = Carbon::parse($movement->date)->startOfDay()->diffInDays($today);

                    $bucket = match (true) {
                        $days == 0 => 'today',
                        $days <= 30 => 'days1to30',
                        $days <= 60 => 'days31to60',
                        $days <= 90 => 'days61to90',
                        $days <= 120 => 'days91to120',
                        default => 'daysOver120',
                    };

                    $remaining -= $amount;
                    $itemsCount++;

                    if ($debtAge !== null && DebtAge::exceeds((int) $days, $debtAge)) {
                        $olderAmount += $amount;
                    }

                    /* فلترُ الشريحة يقصّ **العرض** لا التوزيع: الإطفاءُ أعلاه
                       يمضي كما هو، ويُحتسب في الجدول ما وقع في الشريحة وحدَها. */
                    if ($bucketColumn && $bucket !== $bucketColumn) {
                        continue;
                    }

                    $buckets[$bucket] += $amount;
                    $bucketDays[$bucket] = max($bucketDays[$bucket], (int) $days);
                    $keptItems++;
                }

                if ($remaining > 0.005 && (! $bucketColumn || $bucketColumn === 'daysOver120')) {
                    $buckets['daysOver120'] += $remaining;
                    $keptItems++;
                }
            }

            // قُصّ العرضُ ولم يبقَ للمورّد بندٌ فيه ← ليس من نتيجة هذا الفلتر
            if ($bucketColumn && $keptItems === 0) {
                continue;
            }

            /* «عمر الدين»: من له مبلغٌ مفتوحٌ أقدمُ من الحدّ. ما لم يقابله قيدٌ
               (الباقي بعد الإطفاء) أقدمُ من كلّ الحركات فيُعدّ منه. الدائنون
               لنا (رصيدٌ مدين) لا دينَ لهم يُعمَّر. */
            if ($debtAge !== null) {
                $leftover = $currentBalance > 0 ? max(0.0, $remaining ?? 0.0) : 0.0;
                if ($olderAmount + $leftover <= 0.005) {
                    continue;
                }
            }

            // «تابع لـ» — الجهةُ في الشجرة ونوعُها
            $affiliation = $supplier->branch_id ? \App\Models\Branch::findCached($supplier->branch_id) : null;

            $row = array_merge([
                'supplier_id' => (int) $supplierId,
                'supplier_name' => $supplier->name,
                'supplier_code' => $supplier->number_suply ?? $supplier->id,
                'branch_name' => $affiliation?->name ?? 'غير محدد',
                'branch_id' => $supplier->branch_id,
                'employee_name' => $supplier->employee?->full_name ?: 'غير محدد',
                'employee_id' => $supplier->employee_id,
                // «الحالة» = نشط/موقوف (`suppliers.status`) — نظيرُ حالة العميل
                'supplier_status_name' => (int) $supplier->status === 1 ? 'نشط' : 'موقوف',
                'supplier_status_color' => (int) $supplier->status === 1 ? '#28c76f' : '#ea5455',
                'affiliation_name' => $affiliation?->name ?? 'غير محدد',
                'affiliation_type' => $affiliation?->displayTypeLabel() ?? '',
                'balance' => round($currentBalance, 2),
                // دائن = له علينا (المديونية التي تُعمَّر)، مدين = لنا عليه
                'balance_type' => $currentBalance >= 0 ? 'دائن' : 'مدين',
                'open_items_count' => $bucketColumn ? $keptItems : $itemsCount,
                'wallet_balance' => (float) ($walletBalances[$supplierId] ?? 0),
            ], array_map(fn ($v) => round($v, 2), $buckets), [
                // «الأيام» = عمرُ أقدم حركةٍ مفتوحة (بعد الإطفاء)
                'days_late' => (int) max($bucketDays),
            ]);

            /* الأرصدةُ المدينة (لنا على المورّد) **خارج جدول الأعمار**، كما
               تُفصل أرصدةُ العملاء الدائنة هناك: ليست ديناً علينا فلا تُعمَّر
               ولا تُنقص إجماليَّ الجدول. */
            if ($currentBalance < -0.005) {
                $debtors[] = $row;
            } else {
                $rows[] = $row;
            }
        }

        usort($debtors, fn ($a, $b) => $a['balance'] <=> $b['balance']);
        $debitTotal = round(abs(array_sum(array_column($debtors, 'balance'))), 2);

        /* الترتيب: الأقدمُ تأخّراً أوّلاً كما في تقرير العملاء، ويقبل `sort`/`dir` */
        $sortKey = in_array($request->input('sort'), ['days_late', 'balance', 'supplier_name'], true)
            ? $request->input('sort')
            : 'days_late';
        $dir = $request->input('dir') === 'asc' ? 1 : -1;

        usort($rows, function ($a, $b) use ($sortKey, $dir) {
            $cmp = $sortKey === 'supplier_name'
                ? strcmp((string) $a[$sortKey], (string) $b[$sortKey])
                : $a[$sortKey] <=> $b[$sortKey];

            return $cmp * $dir ?: $b['balance'] <=> $a['balance'];
        });

        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);

        $totals = [
            'total_suppliers' => count($rows),
            // الصافي (المديونية − ما لنا عليهم) — لشريط التسوية مع «أرصدة الموردين»
            'total_balance' => round($sum('balance') - $debitTotal, 2),
            // المديونيةُ وحدَها — أرصدةُ صفوف الجدول (ما علينا)
            'debt_balance' => $sum('balance'),
            'debit_suppliers' => count($debtors),
            'today' => $sum('today'),
            'days1to30' => $sum('days1to30'),
            'days31to60' => $sum('days31to60'),
            'days61to90' => $sum('days61to90'),
            'days91to120' => $sum('days91to120'),
            'daysOver120' => $sum('daysOver120'),
        ];

        $totals['aging_total'] = round(
            $totals['today'] + $totals['days1to30'] + $totals['days31to60']
            + $totals['days61to90'] + $totals['days91to120'] + $totals['daysOver120'],
            2
        );

        /* مجموعُ الشرائح − الأرصدةُ المدينة = صافي أرصدة الموردين — يُعلَن
           كي لا يُبحث عن سببِ فرقٍ لا يُرى. */
        $totals['debit_balances'] = $debitTotal;
        $totals['reconciles'] = abs($totals['aging_total'] - $totals['debit_balances'] - $totals['total_balance']) < 0.05;
        // الشرائحُ مقصوصةٌ بشريحة ← لا تُقارَن بالأرصدة الكاملة
        $totals['reconcile_available'] = ! $bucketColumn && $debtAge === null;
        $totals['debt_age'] = $debtAge;

        // مفتاحان قديمان تقرؤهما شاشاتٌ أخرى
        $totals['total'] = $totals['aging_total'];
        $totals['suppliers_count'] = count($rows);

        $top = array_slice($rows, 0, 10);

        return [
            'reportData' => $rows,
            'debtors' => $debtors,
            'totals' => $totals,
            'chartData' => [
                'labels' => array_column($top, 'supplier_name'),
                'balances' => array_column($top, 'balance'),
                'aging_labels' => ['غير مستحق', '1-30 يوم', '31-60 يوم', '61-90 يوم', '91-120 يوم', '+120 يوم'],
                'aging_values' => [
                    $totals['today'],
                    $totals['days1to30'],
                    $totals['days31to60'],
                    $totals['days61to90'],
                    $totals['days91to120'],
                    $totals['daysOver120'],
                ],
            ],
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'periodContext' => $periodContext,
            'buckets' => self::AGING_BUCKETS,
        ];
    }

    /**
     * أعمار ديون **فواتير** الموردين — مستنداً بمستند، لا مجموعاً على
     * المورّد كما في `debtAging()` أعلاه.
     *
     * الفرقُ عن التقرير السابق ليس شكلياً: ذاك يُعمِّر رصيد المورّد
     * التراكميّ عبر حركات دفتره (تسديدٌ يُطفئ أقدم قيدٍ)، وهذا يقرأ كلَّ
     * فاتورةٍ قائمةً بذاتها بتاريخ استحقاقها هي — فيُجيب «أيّ فاتورةٍ
     * تحديداً تجاوزت أجلها؟» لا «كم إجمالاً على هذا المورّد؟». والسؤالان
     * كلاهما مطلوبٌ ولا يُغني أحدُهما عن الآخر.
     */
    public function invoiceDebtAging(Request $request)
    {
        /* الشاشةُ نظيرُ «أعمار ديون الفواتير» في تقارير العملاء قسماً بقسم
           (`client::customers.CustomerReport.debt_reconstruction_inv`) — الطرفُ
           وحدَه تبدّل: مورّدٌ مكان العميل، ومستندُ شراءٍ مكان فاتورة البيع. */
        $periodContext = $this->agingPeriodContext($request);
        $report = $this->buildInvoiceAging($request, $periodContext);

        $selectedSupplier = null;
        if ($request->filled('supplier_id')) {
            $supplier = HeaderPickers::suppliers(Supplier::query())
                ->select('id', 'number_suply', 'trade_name', 'first_name', 'last_name')
                ->find($request->input('supplier_id'));

            // بالشكل الذي يقرؤه مُنتقي `reports.partials.client-select`: code + display_name
            $selectedSupplier = $supplier
                ? (object) ['id' => $supplier->id, 'code' => $supplier->number_suply, 'display_name' => $supplier->name]
                : null;
        }

        return view('purchases::purchases.report.purchaseReport.supplier_invoice_debt_aging', array_merge([
            'selectedSupplier' => $selectedSupplier,
            'purchaseEmployees' => $this->responsibleEmployeeOptions(),
            'cities' => $this->invoiceAgingCityOptions(),
            'reportData' => $report['data'],
            'groupedSuppliers' => $report['grouped_suppliers'],
            'totals' => $report['totals'],
            'reconciliation' => $report['reconciliation'],
            'fromDate' => Carbon::parse($periodContext['start']),
            'toDate' => Carbon::parse($periodContext['end']),
            'chartData' => $report['chart_data'],
            'invoicesCount' => count($report['data']),
            'periodContext' => $periodContext,
        ], $this->reportTreeContext($request)));
    }

    public function invoiceDebtAgingAjax(Request $request)
    {
        try {
            $periodContext = $this->agingPeriodContext($request);
            $report = $this->buildInvoiceAging($request, $periodContext);

            return response()->json([
                'success' => true,
                'data' => $report['data'],
                'grouped_suppliers' => $report['grouped_suppliers'],
                'totals' => $report['totals'],
                'reconciliation' => $report['reconciliation'],
                'chart_data' => $report['chart_data'],
                'count' => count($report['data']),
                // النطاقُ المطبَّق فعلاً لا ما كتبه المستخدم
                'from_date' => $periodContext['start'],
                'to_date' => $periodContext['end'],
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Purchases.SupplierReport', 'invoiceDebtAgingAjax');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * بحثُ الموردين لقائمة Select2 في تقرير أعمار ديون الفواتير — نظيرُ
     * `ClientReport.searchClients` بالشكل نفسِه: `{results: [{id, text, title}], pagination: {more}}`.
     *
     * القائمةُ تتبع فرعَ الشريط العلويّ ({@see HeaderPickers::suppliers()})
     * والفلاترَ الحيّةَ في النموذج: «تابع لـ» وحالةَ المورد — فمن اختار
     * جهةً لا يرى إلّا مورّديها.
     */
    public function searchSuppliers(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $page = max(1, (int) $request->get('page', 1));
        $limit = 20;

        $query = HeaderPickers::suppliers(Supplier::query());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status') === 'active' ? 1 : 0);
        }

        if ($branchIds = $this->reportBranchIds($request)) {
            $query->whereIn('branch_id', $branchIds);
        }

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->where(function ($q) use ($like) {
                $q->where('trade_name', 'LIKE', $like)
                    ->orWhere('first_name', 'LIKE', $like)
                    ->orWhere('last_name', 'LIKE', $like)
                    ->orWhere('number_suply', 'LIKE', $like)
                    ->orWhere('mobile', 'LIKE', $like)
                    ->orWhere('phone', 'LIKE', $like)
                    ->orWhere('email', 'LIKE', $like);
            });
        }

        $total = (clone $query)->count();

        $suppliers = $query
            ->orderBy('trade_name')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get(['id', 'number_suply', 'trade_name', 'first_name', 'last_name', 'mobile', 'phone', 'email']);

        return response()->json([
            'results' => $suppliers->map(fn ($supplier) => [
                'id' => $supplier->id,
                'text' => trim($supplier->number_suply.' - '.$supplier->name),
                'title' => $supplier->mobile ?: ($supplier->phone ?: $supplier->email),
            ])->values(),
            'pagination' => ['more' => $page * $limit < $total],
        ]);
    }

    /**
     * صفوفُ التقرير — نظيرُ `CustomerDebtsController::reportRows()` على فواتير الشراء.
     *
     * ══════════ معنى المدى ══════════
     *
     * `to_date` **تاريخُ القطع**: لا فاتورةَ بعده. و`from_date` يُقيّد متى
     * اختار المستخدمُ مدىً (زرُّ فترة، تاريخان، سنةٌ مالية، فترةٌ محاسبية)؛
     * والتحميلُ الأوّل بلا مدىً (`is_default`) يعرض المديونيةَ كلَّها حتى
     * تاريخ القطع. `include_older` يُعيد الديونَ الأقدمَ مع مدىً مختار،
     * و`restrict_to_period` يُقيّد دائماً (روابطُ محفوظة).
     *
     * ══════════ المبالغ ══════════
     *
     * الإجماليُّ `grand_total`، والمتبقّي `due_value` — وهو رقمُ الوحدة نفسُه
     * الذي تقرؤه شاشةُ الفاتورة وكشفُ المورّد ({@see \App\Services\Purchases\PurchasePaymentCalculator})
     * — والمدفوعُ فرقُهما. والمرتجعُ ما أُرجع على الفاتورة من مستندات
     * `Return` حتى تاريخ القطع، يُعرض إخباراً ولا يُطرح: تسويتُه تجري في
     * الأستاذ لا على `due_value`.
     *
     * ══════════ العمر ══════════
     *
     * من تاريخ الاستحقاق (`due_date`، وإلّا تاريخُ الفاتورة — افتراضُ
     * `SupplierManagementController::invoiceAggregate()`) حتى تاريخ القطع
     * مقصوصاً على اليوم: المدى الافتراضي ينتهي بنهاية السنة المالية، وقياسُ
     * العمر إلى تاريخٍ في المستقبل يُشيخ كلَّ فاتورة.
     *
     * نطاقُ الفرع ومحورُ الأشخاص كما كانا — لا يتغيّران هنا.
     *
     * @param  array<string, mixed>|null  $period  خرجُ agingPeriodContext()
     * @return array{data: array, grouped_suppliers: array, totals: array, reconciliation: array, chart_data: array}
     */
    protected function buildInvoiceAging(Request $request, ?array $period = null): array
    {
        $period ??= $this->agingPeriodContext($request);

        $cutoff = $period['end'];
        $cutoffDay = Carbon::parse($cutoff)->endOfDay();
        $cut = Carbon::parse($cutoff)->startOfDay();
        if ($cut->greaterThan(Carbon::today())) {
            $cut = Carbon::today()->startOfDay();
        }

        $restrict = $request->boolean('restrict_to_period')
            || (! ($period['is_default'] ?? false) && ! $request->boolean('include_older'));

        $paymentStatusFilter = (string) $request->input('payment_status', '');

        $query = PurchaseInvoice::query()
            ->select(['id', 'code', 'type', 'supplier_id', 'branch_id', 'created_by', 'date', 'due_date', 'grand_total', 'due_value', 'payment_status'])
            ->with([
                'supplier:id,number_suply,trade_name,first_name,last_name,status,city,branch_id,employee_id',
                'supplier.branch',
                'supplier.employee:id,first_name,middle_name,nickname',
                'branch',
            ])
            ->where('type', PurchaseDocumentType::INVOICE->value)
            ->whereNotNull('supplier_id')
            ->whereDate('date', '<=', $cutoffDay);

        // «المسدَّدة» تُطلب صراحةً وحدَها — التقريرُ تقريرُ ديون
        if ($paymentStatusFilter === 'paid') {
            $query->whereRaw('COALESCE(due_value, 0) <= 0.009');
        } else {
            $query->whereRaw('COALESCE(due_value, 0) > 0.009');
        }

        if ($restrict) {
            $query->whereDate('date', '>=', $period['start']);
        }

        // نطاقُ الفرع ومحورُ الأشخاص — نفسُ ما يُقيَّد به بقيّةُ التقارير
        if ($branchIds = $this->reportBranchIds($request)) {
            $query->whereIn('branch_id', $branchIds);
        }

        $query = $this->scopeToVisibleCreators($query);

        if ($supplierId = $this->intOrNull($request->input('supplier_id'))) {
            $query->where('supplier_id', $supplierId);
        }

        /* «مسؤول المشتريات» بمعنى «مسؤول المبيعات» في تقرير العملاء: المورّدُ
           المسنَد إلى الموظّف (`suppliers.employee_id`) أو المضافُ بمستخدمه —
           التعريفُ نفسُه في `buildAging()`. */
        if ($employeeId = $this->intOrNull($request->input('employee_id'))) {
            $query->whereHas('supplier', fn ($q) => $q
                ->where('employee_id', $employeeId)
                ->orWhereIn('created_by', \App\Models\User::where('employee_id', $employeeId)->select('id')));
        }

        if ($request->filled('status')) {
            $active = $request->input('status') === 'active' ? 1 : 0;
            $query->whereHas('supplier', fn ($q) => $q->where('status', $active));
        }

        if ($request->filled('city')) {
            $query->whereHas('supplier', fn ($q) => $q->where('city', $request->input('city')));
        }

        $invoices = $query->orderBy('date')->get();

        if ($invoices->isEmpty()) {
            return $this->emptyInvoiceAging($period);
        }

        $returnedByInvoice = $this->purchaseReturnsByOriginal($invoices->pluck('id')->all(), $cutoff);
        $debitNotedByInvoice = $this->purchaseDebitNotesByOriginal($invoices->pluck('id')->all(), $cutoff);

        $rows = [];

        foreach ($invoices as $invoice) {
            $total = round((float) ($invoice->grand_total ?? 0), 2);
            $returned = round((float) ($returnedByInvoice[$invoice->id] ?? 0), 2);
            $debitNoted = round((float) ($debitNotedByInvoice[$invoice->id] ?? 0), 2);

            /* `due_value` لا يتحرّك بمرتجعٍ ولا بإشعارٍ مدين — يبقى على قيمته
               منذ آخر دفعة، فيَعُدّ التقريرُ فاتورةً رُدَّت بالكامل ديناً
               قائماً بكامل قيمتها. `paid` وحده يُقرأ من `due_value` — أثرُ
               الدفعات لا يتغيّر — والمرتجعُ والإشعارُ يُخصمان من المتبقّي
               بعدَه، كما يخصمان من رصيد المورّد في دفتره. */
            $dueValue = round(max(0.0, (float) ($invoice->due_value ?? 0)), 2);
            $paid = round(max(0.0, $total - $dueValue), 2);
            $remaining = round(max(0.0, $dueValue - $returned - $debitNoted), 2);

            // ردّت فاتورةً بالكامل أو سُوِّيت بإشعارٍ حتى لا شيء عليها — لا دَين هنا
            if ($remaining <= 0.009 && $paymentStatusFilter !== 'paid') {
                continue;
            }

            $paymentState = match (true) {
                $remaining <= 0.005 => 'paid',
                $paid > 0.005 => 'partial',
                default => 'unpaid',
            };

            if ($paymentStatusFilter !== '' && $paymentStatusFilter !== $paymentState) {
                continue;
            }

            $supplier = $invoice->supplier;
            $invoiceDate = Carbon::parse($invoice->date)->startOfDay();
            $dueDate = Carbon::parse($invoice->due_date ?: $invoice->date)->startOfDay();
            $creditDays = (int) max(0, $invoiceDate->diffInDays($dueDate, false));

            // التأخّرُ من تاريخ **الاستحقاق** حتى تاريخ **القطع**
            $daysLate = (int) max(0, $dueDate->diffInDays($cut, false));

            $buckets = ['today' => 0.0, 'days1to30' => 0.0, 'days31to60' => 0.0, 'days61to90' => 0.0, 'days91to120' => 0.0, 'daysOver120' => 0.0];
            $buckets[self::invoiceBucketFor($daysLate)] = $remaining;

            $node = $supplier?->branch ?? $invoice->branch;
            $supplierActive = $supplier ? (int) $supplier->status === 1 : null;

            $rows[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->code ?? $invoice->id,
                'invoice_type' => $invoice->type,
                'supplier_id' => $supplier?->id,
                'supplier_code' => $supplier?->number_suply ?? 'غير محدد',
                'supplier_name' => $supplier?->name ?? 'غير محدد',
                /* «تابع لـ» جهةُ المورّد، ويرجع إلى فرع الفاتورة المختوم لمن لا جهةَ له */
                'branch' => $node?->name ?? 'غير محدد',
                'affiliation_type' => $node?->displayTypeLabel() ?? '',
                'status_name' => $supplierActive === null ? 'غير محدد' : ($supplierActive ? 'نشط' : 'موقوف'),
                'status_color' => $supplierActive === null ? '#6c757d' : ($supplierActive ? '#10b981' : '#ef4444'),
                'employee_name' => $supplier?->employee?->full_name ?: 'بلا مسؤول',
                'city' => $supplier?->city ?? '',
                'invoice_date' => $invoiceDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'credit_period' => $creditDays,
                'days_late' => $daysLate,
                'total_amount' => $total,
                'returned_amount' => $returned,
                'debit_noted_amount' => $debitNoted,
                'paid_amount' => $paid,
                'total_due' => $remaining,
                'payment_state' => $paymentState,
                'payment_state_label' => ['paid' => 'مسدَّدة', 'partial' => 'مسدَّدة جزئياً', 'unpaid' => 'غير مسدَّدة'][$paymentState],
                'today' => $buckets['today'],
                'days1to30' => $buckets['days1to30'],
                'days31to60' => $buckets['days31to60'],
                'days61to90' => $buckets['days61to90'],
                'days91to120' => $buckets['days91to120'],
                'daysOver120' => $buckets['daysOver120'],
            ];
        }

        // فلترُ الشرائح — القيمُ المرسَلةُ من الشاشة هي قيمُ تقرير العملاء نفسُها
        if ($request->filled('period_filter') && $request->input('period_filter') !== 'custom') {
            $column = [
                '1' => 'today',
                '7' => 'days1to30',
                '30' => 'days31to60',
                '90' => 'days61to90',
                '180' => 'days91to120',
                '365' => 'daysOver120',
            ][(string) $request->input('period_filter')] ?? null;

            if ($column) {
                $rows = array_values(array_filter($rows, fn ($row) => abs($row[$column]) > 0.005));
            }
        }

        /* «عمر الدين»: الفواتيرُ التي تجاوز عمرُ استحقاقها الحدَّ — `days_late`
           من تاريخ الاستحقاق إلى تاريخ القطع. */
        $debtAge = DebtAge::fromRequest($request);
        if ($debtAge !== null) {
            $rows = array_values(array_filter($rows, fn ($row) => DebtAge::exceeds($row['days_late'], $debtAge)));
        }

        usort($rows, fn ($a, $b) => $b['days_late'] <=> $a['days_late']);

        /* فواتيرُ مفتوحةٌ لمورّدٍ **حسابُه غير دائن** (مدينٌ لنا أو صفر) حتى
           تاريخ القطع: سُدِّد على الحساب لا على الفاتورة، فالفاتورةُ
           «مستحقّة» ورقياً ولا يُستحقّ له فعلياً. تُعلَّم ولا تُحذف. */
        $supplierIds = array_values(array_unique(array_filter(array_column($rows, 'supplier_id'))));
        $ledgerBalanceBySupplier = [];
        if ($supplierIds !== []) {
            try {
                $balances = SupplierLedger::fromFilters(null, null, null)->balancesAsOf($cutoffDay);
                foreach ($supplierIds as $id) {
                    $ledgerBalanceBySupplier[(int) $id] = (float) ($balances[(int) $id] ?? 0.0);
                }
            } catch (\Throwable $e) {
                OperationLog::failure($e, 'Purchases.SupplierReport', 'invoiceDebtAging.balances');
            }
        }
        foreach ($rows as $i => $row) {
            $accountBalance = round((float) ($ledgerBalanceBySupplier[(int) $row['supplier_id']] ?? 0.0), 2);
            $rows[$i]['account_balance'] = $accountBalance;
            $rows[$i]['account_not_credit'] = $accountBalance <= 0.005;
        }

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['supplier_name'].' #'.$row['supplier_id']][] = $row;
        }

        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);

        $totals = [
            'invoices_count' => count($rows),
            'suppliers_count' => count($grouped),
            'total_amount' => $sum('total_amount'),
            'returned_amount' => $sum('returned_amount'),
            'debit_noted_amount' => $sum('debit_noted_amount'),
            'paid_amount' => $sum('paid_amount'),
            'total_due' => $sum('total_due'),
            'today' => $sum('today'),
            'days1to30' => $sum('days1to30'),
            'days31to60' => $sum('days31to60'),
            'days61to90' => $sum('days61to90'),
            'days91to120' => $sum('days91to120'),
            'daysOver120' => $sum('daysOver120'),
        ];

        $notCreditRows = array_filter($rows, fn ($row) => $row['account_not_credit']);
        $totals['debt_age'] = $debtAge;
        $totals['not_credit_invoices'] = count($notCreditRows);
        $totals['not_credit_due'] = round(array_sum(array_column($notCreditRows, 'total_due')), 2);
        $totals['not_credit_suppliers'] = count(array_unique(array_column($notCreditRows, 'supplier_id')));
        $totals['not_credit_debit'] = round(abs(array_sum(array_filter(
            array_map(fn ($id) => $ledgerBalanceBySupplier[(int) $id] ?? 0.0, array_unique(array_column($notCreditRows, 'supplier_id'))),
            fn ($b) => $b < 0
        ))), 2);

        return [
            'data' => $rows,
            'grouped_suppliers' => $grouped,
            'totals' => $totals,
            'reconciliation' => $this->reconcileInvoiceAging($request, $rows, $ledgerBalanceBySupplier, $restrict),
            'chart_data' => [
                'aging_labels' => ['غير مستحق', '1-30 يوم', '31-60 يوم', '61-90 يوم', '91-120 يوم', '+120 يوم'],
                'aging_values' => [
                    $totals['today'], $totals['days1to30'], $totals['days31to60'],
                    $totals['days61to90'], $totals['days91to120'], $totals['daysOver120'],
                ],
            ],
        ];
    }

    /** اسمُ الشريحة الموافقة لعدد أيام التأخّر — نفسُ حدود تقرير العملاء. */
    public static function invoiceBucketFor(int $daysLate): string
    {
        return match (true) {
            $daysLate <= 0 => 'today',
            $daysLate <= 30 => 'days1to30',
            $daysLate <= 60 => 'days31to60',
            $daysLate <= 90 => 'days61to90',
            $daysLate <= 120 => 'days91to120',
            default => 'daysOver120',
        };
    }

    /**
     * تسويةُ مديونية الفواتير مع رصيد الأستاذ — نظيرُ تسوية تقرير العملاء.
     *
     * لا تُحسب حين يُقصر الجدولُ على فواتير الفترة أو يُفلتر بحالة السداد أو
     * شريحة العمر: المقارنةُ حينئذٍ بين جزءٍ وكلّ.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, float>  $balances  رصيدُ الأستاذ لكلّ مورّدٍ في الجدول (موجب = له علينا)
     * @return array<string, mixed>
     */
    private function reconcileInvoiceAging(Request $request, array $rows, array $balances, bool $restrict): array
    {
        if ($restrict) {
            return ['available' => false, 'reason' => 'الجدولُ مقصورٌ على فواتير الفترة — لا يُقارَن بالأستاذ.'];
        }

        if ($request->filled('payment_status') || $request->filled(DebtAge::PARAM)
            || ($request->filled('period_filter') && $request->input('period_filter') !== 'custom')) {
            return ['available' => false, 'reason' => 'الجدولُ مفلترٌ بحالة السداد أو شريحة العمر — لا يُقارَن برصيد الأستاذ الكامل.'];
        }

        if ($rows === []) {
            return ['available' => false, 'reason' => 'لا فواتيرَ مستحقّة في هذا النطاق.'];
        }

        $ledgerTotal = round(array_sum(array_filter($balances, fn ($b) => $b > 0)), 2);
        $invoiceTotal = round(array_sum(array_column($rows, 'total_due')), 2);

        return [
            'available' => true,
            'invoice_debt' => $invoiceTotal,
            'ledger_debt' => $ledgerTotal,
            'difference' => round($invoiceTotal - $ledgerTotal, 2),
            'note' => 'الفرقُ حركاتٌ على حساب المورّد لا تُربط بفاتورةٍ في هذا الجدول، وتعمل في الاتجاهين: مرتجعاتٌ وإشعاراتٌ مدينة وقيودُ يوميةٍ تُخفض الأستاذ، وأرصدةٌ افتتاحيةٌ تزيده ودفعاتُ الرصيد السابق تُخفضه. تقريرُ أرصدة الموردين وكشفُ الحساب يعرضان الرقمَ المحاسبي.',
        ];
    }

    /**
     * مبالغُ مرتجعات الشراء مفهرسةً بمعرّف الفاتورة الأصلية — حتى تاريخ القطع.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<int, float>
     */
    private function purchaseReturnsByOriginal(array $invoiceIds, string $cutoff): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return DB::table('purchase_invoices')
            ->where('type', PurchaseDocumentType::RETURN->value)
            ->whereIn('reference_id', $invoiceIds)
            ->whereDate('date', '<=', $cutoff)
            ->groupBy('reference_id')
            ->selectRaw('reference_id, COALESCE(SUM(grand_total), 0) as returned')
            ->pluck('returned', 'reference_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * إشعاراتُ مدين الفترة لكل فاتورةٍ أصلية — نظيرُ
     * `purchaseReturnsByOriginal()` بنوع المستند وحده.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array<int, float>
     */
    private function purchaseDebitNotesByOriginal(array $invoiceIds, string $cutoff): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return DB::table('purchase_invoices')
            ->where('type', PurchaseDocumentType::DEBIT_NOTE->value)
            ->whereIn('reference_id', $invoiceIds)
            ->whereDate('date', '<=', $cutoff)
            ->groupBy('reference_id')
            ->selectRaw('reference_id, COALESCE(SUM(grand_total), 0) as debit_noted')
            ->pluck('debit_noted', 'reference_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * تقريرٌ فارغٌ بالبنية نفسِها — كي لا يسقط القالبُ على مفتاحٍ ناقص.
     *
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    private function emptyInvoiceAging(array $period): array
    {
        return [
            'data' => [],
            'grouped_suppliers' => [],
            'totals' => [
                'today' => 0, 'days1to30' => 0, 'days31to60' => 0,
                'days61to90' => 0, 'days91to120' => 0, 'daysOver120' => 0,
                'invoices_count' => 0,
                'suppliers_count' => 0,
                'total_amount' => 0,
                'returned_amount' => 0,
                'debit_noted_amount' => 0,
                'paid_amount' => 0,
                'total_due' => 0,
                'not_credit_invoices' => 0,
                'not_credit_due' => 0,
                'not_credit_suppliers' => 0,
                'not_credit_debit' => 0,
            ],
            'reconciliation' => ['available' => false, 'reason' => 'لا فواتيرَ في هذا النطاق.'],
            'chart_data' => [
                'aging_labels' => ['غير مستحق', '1-30 يوم', '31-60 يوم', '61-90 يوم', '91-120 يوم', '+120 يوم'],
                'aging_values' => [0, 0, 0, 0, 0, 0],
            ],
        ];
    }

    /**
     * سياقُ الفترة لتقرير أعمار ديون الفواتير — `reportPeriodContext()` نفسُها
     * (فترةٌ محاسبيةٌ مختارة ← سنةٌ مالية ← تاريخانِ ← السنةُ الجارية)، مع
     * قراءة `date_to` القديم تاريخَ قطعٍ لروابطَ محفوظة.
     *
     * @return array<string, mixed>
     */
    protected function agingPeriodContext(Request $request): array
    {
        // الاسمان القديمان `date_from`/`date_to` يبقيان مفهومَين لروابطَ محفوظة
        $startKey = ! $request->filled('from_date') && $request->filled('date_from') ? 'date_from' : 'from_date';
        $endKey = ! $request->filled('to_date') && $request->filled('date_to') ? 'date_to' : 'to_date';

        return $this->reportPeriodContext($request, $startKey, $endKey);
    }

    /** مدنُ الموردين لفلتر «المدينة» — نظيرُ «الحي» في تقرير العملاء، على فرع الشريط العلويّ */
    protected function invoiceAgingCityOptions()
    {
        return HeaderPickers::suppliers(Supplier::query())
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    /** خانات التعمير — العنوان والمفتاح واللون، مصدراً واحداً للجدول والبطاقات */
    public const AGING_BUCKETS = [
        'today' => ['label' => 'حالي (اليوم)', 'class' => 'success'],
        'days1to30' => ['label' => '١ – ٣٠ يوم', 'class' => 'success'],
        'days31to60' => ['label' => '٣١ – ٦٠ يوم', 'class' => 'info'],
        'days61to90' => ['label' => '٦١ – ٩٠ يوم', 'class' => 'warning'],
        'days91to120' => ['label' => '٩١ – ١٢٠ يوم', 'class' => 'warning'],
        'daysOver120' => ['label' => 'أكثر من ١٢٠', 'class' => 'danger'],
    ];

    // =================================================================================
    // البناء المشترك
    // =================================================================================

    /**
     * صفوف التقرير وإجمالياته للفترة المطلوبة.
     *
     * تُبقى مفاتيح الأسماء القديمة (`amount`، `paid`، `net_amount`…) إلى
     * جانب الجديدة لأن أربع شاشات أخرى تقرأها.
     */
    protected function buildReport(Request $request): array
    {
        $period = ReportPeriod::fromRequest($request);

        $ledger = SupplierLedger::fromFilters(
            $this->intOrNull($request->input('supplier_id')),
            $this->intOrNull($request->input('employee_id')),
            $this->reportBranchIds($request)
        )->visibleTo($this->visibleCreatorIds());

        $accounts = $ledger->accounts();
        $periodTotals = $ledger->periodTotals($period);
        $openings = $ledger->openingBalances($period);
        $wantsDetails = $request->input('view_type', 'summary') === 'details';
        $allMovements = $wantsDetails ? $ledger->movements($period) : collect();
        $movements = $allMovements->groupBy('supplier_id');

        // مدفوعات كل فاتورة — استعلامٌ واحد لكل الفواتير الظاهرة، لا استعلامٌ لكل صف
        $invoicePayments = $this->invoicePayments($allMovements);

        $showEmpty = $request->boolean('show_zero');
        $statusFilter = $request->input('payment_status');

        $reportData = collect();
        $totals = $this->emptyReportTotals();

        foreach ($accounts as $account) {
            $supplierId = (int) $account->supplier_id;
            $supplier = $account->supplier;

            $figures = $periodTotals[$supplierId] ?? SupplierLedger::emptyTotals();
            $opening = (float) ($openings[$supplierId] ?? 0);

            $netPurchases = $figures['purchases'] - $figures['returns'] - $figures['debit_notes'];
            $closing = $opening + $figures['purchases'] - $figures['returns']
                - $figures['debit_notes'] - $figures['payments'] + $figures['adjustments'];

            $hasActivity = $figures['entries_count'] > 0;

            if (! $hasActivity && abs($closing) <= 0.009 && ! $showEmpty) {
                continue;
            }

            $status = $this->paymentStatus($netPurchases, $figures['payments'], $figures);

            if ($statusFilter && $status['code'] !== $statusFilter) {
                continue;
            }

            $row = [
                'supplier_id' => $supplierId,
                'account_id' => $account->id,
                'name' => $supplier->trade_name,
                'supplier_name' => $supplier->trade_name,
                'supplier_code' => $supplier->number_suply ?? $supplier->id,
                'phone' => $supplier->mobile ?: $supplier->phone,
                'city' => $supplier->city,
                'branch_name' => $supplier->branch->name ?? '-',

                'opening_balance' => round($opening, 2),
                'purchases' => round($figures['purchases'], 2),
                'purchases_count' => $figures['purchases_count'],
                'returns' => round($figures['returns'], 2),
                'returns_count' => $figures['returns_count'],
                'debit_notes' => round($figures['debit_notes'], 2),
                'debit_notes_count' => $figures['debit_notes_count'],
                'net_purchases' => round($netPurchases, 2),
                'payments' => round($figures['payments'], 2),
                'payments_count' => $figures['payments_count'],
                'adjustments' => round($figures['adjustments'], 2),
                'balance' => round($closing, 2),
                'balance_state' => $this->balanceState($closing),
                'unpaid' => round(max($netPurchases - $figures['payments'], 0), 2),
                'entries_count' => $figures['entries_count'],
                'status' => $status,

                // أسماء قديمة تقرأها شاشات الدليل والأرصدة والتقرير الموحّد
                'amount' => round($figures['purchases'], 2),
                'total_purchases' => round($figures['purchases'], 2),
                'count' => $figures['purchases_count'],
                'total_returns' => round($figures['returns'], 2),
                'returns_amount' => round($figures['returns'], 2),
                'net_amount' => round($netPurchases, 2),
                'total_payments' => round($figures['payments'], 2),
                'paid' => round($figures['payments'], 2),

                'items' => $wantsDetails
                    ? $this->buildStatement($movements->get($supplierId, collect()), $opening, $invoicePayments)
                    : [],
            ];

            $reportData->push($row);

            foreach (['opening_balance', 'purchases', 'returns', 'debit_notes', 'net_purchases', 'payments', 'adjustments', 'balance', 'unpaid'] as $key) {
                $totals[$key] += $row[$key];
            }
            foreach (['purchases_count', 'returns_count', 'debit_notes_count', 'payments_count', 'entries_count'] as $key) {
                $totals[$key] += $row[$key];
            }
        }

        $reportData = $this->sortRows($reportData, $request->input('sort_by', 'purchases'), $request->input('sort_dir', 'desc'));

        $totals['suppliers_count'] = $reportData->count();
        $totals = array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals);

        // مرادفات الإجماليات للشاشات القديمة
        $totals['amount'] = $totals['purchases'];
        $totals['total_purchases'] = $totals['purchases'];
        $totals['returns_amount'] = $totals['returns'];
        $totals['total_returns'] = $totals['returns'];
        $totals['net_amount'] = $totals['net_purchases'];
        $totals['paid'] = $totals['payments'];
        $totals['total_payments'] = $totals['payments'];
        $totals['count'] = $totals['purchases_count'];

        return [
            'reportData' => $reportData,
            'totals' => $totals,
            'trend' => $ledger->trend($period),
            'period' => $period,
            'fromDate' => $period->from,
            'toDate' => $period->to,
            'isSummary' => ! $wantsDetails,
        ];
    }

    /**
     * ما سُدِّد من كل فاتورة — خريطة `purchase_invoice_id ← المبلغ`.
     *
     * استعلامٌ واحد لكل الفواتير الظاهرة، لا استعلامٌ لكل صف.
     *
     * @param  Collection<int, \stdClass>  $movements
     * @return array<int, float>
     */
    protected function invoicePayments(Collection $movements): array
    {
        $invoiceIds = $movements->pluck('purchase_invoice_id')->filter()->unique()->values();

        if ($invoiceIds->isEmpty()) {
            return [];
        }

        return DB::table('payments_process')
            ->whereIn('purchases_id', $invoiceIds)
            ->groupBy('purchases_id')
            ->selectRaw('purchases_id, COALESCE(SUM(amount), 0) as paid')
            ->pluck('paid', 'purchases_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * كشف حساب المورّد للفترة — برصيد متحرّك يبدأ من رصيد أول المدة.
     *
     * **المفاتيح المكرَّرة ليست زينة.** شاشتا «دليل الموردين» و«أرصدة
     * الموردين» تقرآن `reference_type` و`paid` و`status` لكل سطر، ولم تكن
     * موجودةً هنا — فكل فتحٍ لعرض «التفاصيل» فيهما كان يرمي
     * `Undefined array key "reference_type"` ويُسقط الصفحة كاملةً. هي
     * أسماءٌ بديلة لما يحمله السطر أصلاً، عدا `paid` الذي يأتي من مدفوعات
     * الفاتورة المحمَّلة دفعةً واحدة.
     *
     * @param  array<int, float>  $invoicePayments
     */
    protected function buildStatement(Collection $movements, float $opening, array $invoicePayments = []): array
    {
        $running = $opening;
        $statement = [];

        foreach ($movements as $movement) {
            $debit = (float) $movement->debit;
            $credit = (float) $movement->credit;
            $running += $credit - $debit;

            $kind = SupplierLedger::classify($movement->reference_type);
            $typeName = SupplierLedger::typeName($movement->reference_type);

            $invoiceId = $movement->purchase_invoice_id ? (int) $movement->purchase_invoice_id : null;
            // قيمة الفاتورة هي الدائن على حساب المورّد؛ ما عداها ليس مستنداً يُسدَّد
            $gross = $kind === 'purchase' ? $credit - $debit : 0.0;
            $paid = $invoiceId ? ($invoicePayments[$invoiceId] ?? 0.0) : 0.0;

            $statement[] = [
                'entry_id' => (int) $movement->entry_id,
                'purchase_invoice_id' => $invoiceId,
                'reference_number' => $movement->reference_number ?: '—',
                'date' => Carbon::parse($movement->date)->format('d/m/Y'),
                'raw_date' => Carbon::parse($movement->date)->format('Y-m-d'),
                'type' => (string) $movement->reference_type,
                'type_name' => $typeName,
                // اسمٌ بديل تقرأه شاشتا الدليل والأرصدة في عمود «البيان»
                'reference_type' => $typeName,
                'kind' => $kind,
                'employee_name' => $movement->employee_name ?: '—',
                'branch_name' => $movement->branch_name ?: '—',
                'description' => $movement->description,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'amount' => round($credit - $debit, 2),
                'paid' => round($paid, 2),
                'unpaid' => round(max($gross - $paid, 0), 2),
                'status' => $kind === 'purchase'
                    ? $this->paymentStatus($gross, $paid, ['entries_count' => 1])
                    : ['text' => $typeName, 'class' => $kind === 'return' ? 'danger' : 'info', 'code' => $kind],
                'running_balance' => round($running, 2),
            ];
        }

        return $statement;
    }

    protected function sortRows(Collection $rows, ?string $sortBy, ?string $direction): Collection
    {
        $allowed = ['supplier_name', 'purchases', 'returns', 'net_purchases', 'payments', 'balance', 'purchases_count'];
        $key = in_array($sortBy, $allowed, true) ? $sortBy : 'purchases';

        $sorted = $key === 'supplier_name'
            ? $rows->sortBy($key, SORT_NATURAL | SORT_FLAG_CASE)
            : $rows->sortBy($key);

        return ($direction === 'asc' ? $sorted : $sorted->reverse())->values();
    }

    protected function emptyReportTotals(): array
    {
        return [
            'opening_balance' => 0.0,
            'purchases' => 0.0,
            'purchases_count' => 0,
            'returns' => 0.0,
            'returns_count' => 0,
            'debit_notes' => 0.0,
            'debit_notes_count' => 0,
            'net_purchases' => 0.0,
            'payments' => 0.0,
            'payments_count' => 0,
            'adjustments' => 0.0,
            'balance' => 0.0,
            'unpaid' => 0.0,
            'entries_count' => 0,
            'suppliers_count' => 0,
        ];
    }

    /**
     * حالة السداد بمقارنة المدفوع بصافي مشتريات الفترة.
     *
     * الصافي قد يخرج سالباً حين تفوق المرتجعات والإشعارات مشترياتِ
     * الفترة — وهي حالةٌ قائمة لا «صفر»، فتُسمّى باسمها بدل أن تُعرض
     * «غير مسدد» على مورّد لا شيء عليه.
     */
    protected function paymentStatus(float $net, float $paid, array $figures = []): array
    {
        if (($figures['entries_count'] ?? 1) === 0) {
            return ['text' => 'لا حركة', 'class' => 'secondary', 'code' => 'idle'];
        }

        if ($net <= 0.009) {
            if (($figures['returns'] ?? 0) + ($figures['debit_notes'] ?? 0) > 0.009) {
                return ['text' => 'مرتجع صافٍ', 'class' => 'info', 'code' => 'net_return'];
            }

            return $paid > 0.009
                ? ['text' => 'مدفوعات فقط', 'class' => 'info', 'code' => 'payments_only']
                : ['text' => 'لا حركة', 'class' => 'secondary', 'code' => 'idle'];
        }

        $percentage = ($paid / $net) * 100;

        if ($percentage >= 99.5) {
            return ['text' => 'مسدد بالكامل', 'class' => 'success', 'code' => 'paid'];
        }

        if ($percentage > 0) {
            return ['text' => 'مسدد جزئياً', 'class' => 'warning', 'code' => 'partial'];
        }

        return ['text' => 'غير مسدد', 'class' => 'danger', 'code' => 'unpaid'];
    }

    /** وصف الرصيد: موجب = له علينا */
    protected function balanceState(float $balance): array
    {
        if ($balance > 0.009) {
            return ['text' => 'دائن (له)', 'class' => 'danger'];
        }

        if ($balance < -0.009) {
            return ['text' => 'مدين (عليه)', 'class' => 'info'];
        }

        return ['text' => 'متزن', 'class' => 'success'];
    }

    protected function supplierOptions()
    {
        // فلترُ المورِّد يتبع فرعَ الشريط العلويّ — {@see \Modules\Purchases\Support\HeaderPickers}
        return \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::orderBy('trade_name'))->get();
    }

    protected function employeeOptions()
    {
        return $this->reportUserOptions();
    }

    protected function intOrNull($value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    /** يبقى للتوافق: حسابات الموردين كما يراها التقرير */
    protected function supplierAccounts(): Collection
    {
        return Account::with('supplier')->whereNotNull('supplier_id')->get();
    }
}
