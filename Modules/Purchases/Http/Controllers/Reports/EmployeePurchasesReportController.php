<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Support\EmployeePurchaseLedger;
use Modules\Purchases\Support\ReportPeriod;
use Modules\Purchases\Support\SupplierLedger;

/**
 * تقرير المشتريات حسب الموظّف — ماذا اشترى كلُّ موظّف وكم سدَّد.
 *
 * الأرقام كلّها من `EmployeePurchaseLedger` لا من استعلامات محلية، وهو
 * يقرأ حركةَ حسابات الموردين نفسها التي يقرأها `SupplierLedger` — فمجموع
 * هذه الشاشة في فترةٍ يطابق مجموع شاشة الموردين فيها رقماً برقم.
 *
 * @see EmployeePurchaseLedger القاعدة المحاسبية وعلّة استخراجها من هنا
 */
class EmployeePurchasesReportController extends Controller
{
    use PurchaseReportScope;

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
        $this->middleware('permission:purchases.reports.by_employee', ['only' => ['index', 'ajaxData']]);
        $this->middleware('permission:purchases.reports.export', ['only' => ['exportExcel']]);
    }

    public function index(Request $request)
    {
        $data = $this->buildReport($request);

        return view('purchases::purchases.report.purchaseReport.Purchase_By_Employee', array_merge([
            'users' => $this->employeeOptions(),
            'branches' => $this->reportBranchOptions(),
            'suppliers' => \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::orderBy('trade_name'))->get(),
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
            OperationLog::failure($e, 'Purchases.EmployeePurchasesReport', 'ajaxData');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * تصدير الملخّص — CSV بترميز UTF‑8 مع BOM ليفتحه إكسل عربياً سليماً.
     *
     * كان زرّ «Excel» في الشاشة بلا مسار، فلا يفعل شيئاً عند الضغط.
     */
    public function exportExcel(Request $request)
    {
        $data = $this->buildReport($request);

        $columns = [
            'الموظف', 'عدد الفواتير', 'إجمالي المشتريات', 'المرتجعات', 'إشعارات مدينة',
            'صافي المشتريات', 'المدفوعات', 'غير المسدد', 'عدد الموردين', 'الحالة',
        ];

        $filename = 'purchases-by-employee-'.$data['fromDate']->format('Ymd').'-'.$data['toDate']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data, $columns) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);

            foreach ($data['reportData'] as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['purchases_count'],
                    number_format($row['purchases'], 2, '.', ''),
                    number_format($row['returns'], 2, '.', ''),
                    number_format($row['debit_notes'], 2, '.', ''),
                    number_format($row['net_purchases'], 2, '.', ''),
                    number_format($row['payments'], 2, '.', ''),
                    number_format($row['unpaid'], 2, '.', ''),
                    $row['suppliers_count'],
                    $row['status']['text'],
                ]);
            }

            $totals = $data['totals'];
            fputcsv($handle, [
                'الإجمالي العام',
                $totals['purchases_count'],
                number_format($totals['purchases'], 2, '.', ''),
                number_format($totals['returns'], 2, '.', ''),
                number_format($totals['debit_notes'], 2, '.', ''),
                number_format($totals['net_purchases'], 2, '.', ''),
                number_format($totals['payments'], 2, '.', ''),
                number_format($totals['unpaid'], 2, '.', ''),
                '', '',
            ]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =================================================================================
    // البناء
    // =================================================================================

    /**
     * صفوف التقرير وإجمالياته للفترة المطلوبة.
     *
     * تُبقى مفاتيح الأسماء القديمة (`amount`، `count`، `net_amount`…) إلى
     * جانب الجديدة، فالطباعة والتصدير في الشاشة يقرآنها.
     */
    protected function buildReport(Request $request): array
    {
        $period = ReportPeriod::fromRequest($request);

        $ledger = EmployeePurchaseLedger::fromFilters(
            $this->intOrNull($request->input('employee_id')),
            $this->intOrNull($request->input('supplier_id')),
            $this->reportBranchIds($request)
        )->visibleTo($this->visibleCreatorIds());

        $periodTotals = $ledger->periodTotals($period);
        $openings = $ledger->openingBalances($period);
        $wantsDetails = $request->input('view_type', 'summary') === 'details';
        $allMovements = $wantsDetails ? $ledger->movements($period) : collect();
        $movements = $allMovements->groupBy('employee_id');

        // مدفوعات كل فاتورة — استعلامٌ واحد لكل الفواتير الظاهرة، لا استعلامٌ لكل صف
        $invoicePayments = $this->invoicePayments($allMovements);

        /* موظّفٌ رُبط بمورّدٍ قبل الفترة ولم يشترِ فيها شيئاً لا يسقط من
           القائمة: له افتتاحيٌّ قائم، كما يبقى المورّدُ نفسُه ظاهراً في
           «حسب المورد» (تلك تكرّر على حسابات الموردين كلِّها لا قيودَ
           الفترة وحدها). فالكونُ هنا اتحادُ مَن تحرّك في الفترة ومَن
           عليه افتتاحيٌّ — وإلا ضاع افتتاحيُّه من مجموع الشاشة بصمت،
           وهو بالضبط ما كان يُفرِّق «غير المسدد» عن «رصيد المورد» لأيّ
           مدىً غير الشامل للتاريخ كلِّه. */
        $employeeIds = collect(array_keys($periodTotals))
            ->merge(collect($openings)->filter(fn ($v) => abs($v) > 0.005)->keys())
            ->unique();

        // أسماء الموظّفين الظاهرين وحدهم — لا سجلّ المستخدمين كلّه
        $names = User::whereIn('id', $employeeIds)->pluck('name', 'id');

        $statusFilter = $request->input('payment_status');
        $reportData = collect();
        $totals = $this->emptyTotals();

        foreach ($employeeIds as $employeeId) {
            $figures = $periodTotals[$employeeId] ?? SupplierLedger::emptyTotals() + ['suppliers_count' => 0];
            $netPurchases = $figures['purchases'] - $figures['returns'] - $figures['debit_notes'];
            $status = $this->paymentStatus($netPurchases, $figures['payments'], $figures);

            if ($statusFilter && $status['code'] !== $statusFilter) {
                continue;
            }

            $opening = (float) ($openings[$employeeId] ?? 0);

            $row = [
                'employee_id' => $employeeId,
                'name' => $names[$employeeId] ?? 'موظّف محذوف #'.$employeeId,

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
                /* الصيغة نفسها التي يبني بها تقرير «حسب المورد» رصيدَ آخر
                   المدة، بالافتتاحي نفسه: كلُّ ما أنشأه هذا الموظّفُ من
                   قيودٍ قبل الفترة — انظر `EmployeePurchaseLedger::
                   openingBalances()`. كانت هنا بلا افتتاحيٍّ ولا تسويات
                   ومحصورةً بالصفر، فتخالف رصيد المورد لأيّ مدىً غير
                   الشامل للتاريخ كلِّه بقدر افتتاحي مَن تعامل معهم الموظّفُ
                   — شهرٌ واحد كان يُظهر فرقاً بعشرات الآلاف. */
                'unpaid' => round($opening + $netPurchases - $figures['payments'] + $figures['adjustments'], 2),
                'entries_count' => $figures['entries_count'],
                'suppliers_count' => $figures['suppliers_count'],
                'avg_invoice' => $figures['purchases_count'] > 0
                    ? round($figures['purchases'] / $figures['purchases_count'], 2)
                    : 0.0,
                'status' => $status,

                // أسماء قديمة تقرأها الطباعة والتصدير في القالب
                'amount' => round($figures['purchases'], 2),
                'count' => $figures['purchases_count'],
                'returns_amount' => round($figures['returns'], 2),
                'net_amount' => round($netPurchases, 2),
                'paid' => round($figures['payments'], 2),

                'items' => $wantsDetails
                    ? $this->buildStatement($movements->get($employeeId, collect()), $invoicePayments)
                    : [],
            ];

            $reportData->push($row);

            foreach (['opening_balance', 'purchases', 'returns', 'debit_notes', 'net_purchases', 'payments', 'adjustments', 'unpaid'] as $key) {
                $totals[$key] += $row[$key];
            }
            foreach (['purchases_count', 'returns_count', 'debit_notes_count', 'payments_count', 'entries_count'] as $key) {
                $totals[$key] += $row[$key];
            }
        }

        $reportData = $this->sortRows($reportData, $request->input('sort_by', 'purchases'), $request->input('sort_dir', 'desc'));

        $totals['employees_count'] = $reportData->count();
        $totals = array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals);

        // مرادفات الإجماليات للطباعة والتصدير
        $totals['amount'] = $totals['purchases'];
        $totals['count'] = $totals['purchases_count'];
        $totals['returns_amount'] = $totals['returns'];
        $totals['net_amount'] = $totals['net_purchases'];
        $totals['paid'] = $totals['payments'];

        return [
            'reportData' => $reportData,
            'totals' => $totals,
            'trend' => $ledger->trend($period),
            'period' => $period,
            'fromDate' => $period->from,
            'toDate' => $period->to,
            'isSummary' => ! $wantsDetails,
            'currencySymbol' => CurrencySettings::symbol(),
        ];
    }

    /**
     * ما سُدِّد من كل فاتورة — خريطة `purchase_invoice_id ← المبلغ`.
     *
     * كانت الشاشة تقرأ `$entry->purchaseInvoice->payments->sum('amount')` داخل
     * حلقة الصفوف، فاستعلامان لكل سطر. هنا استعلامٌ واحد لكل الفواتير الظاهرة.
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
     * حركة الموظّف في الفترة — قيداً قيداً بمورّده ومبلغه.
     *
     * المبلغ يُعرض بإشارته الاقتصادية: الشراء موجب لأنه التزامٌ نشأ،
     * والمرتجع والسداد سالبان لأنهما يُطفئانه.
     *
     * @param  array<int, float>  $invoicePayments
     */
    protected function buildStatement(Collection $movements, array $invoicePayments = []): array
    {
        $statement = [];

        foreach ($movements as $movement) {
            $debit = (float) $movement->debit;
            $credit = (float) $movement->credit;
            $kind = SupplierLedger::classify($movement->reference_type);

            $invoiceId = $movement->purchase_invoice_id ? (int) $movement->purchase_invoice_id : null;
            $gross = $kind === 'purchase' ? $credit - $debit : 0.0;
            $paid = $invoiceId ? ($invoicePayments[$invoiceId] ?? 0.0) : 0.0;

            $statement[] = [
                'entry_id' => (int) $movement->entry_id,
                'purchase_invoice_id' => $movement->purchase_invoice_id ? (int) $movement->purchase_invoice_id : null,
                'reference_number' => $movement->reference_number ?: '—',
                'date' => Carbon::parse($movement->date)->format('d/m/Y'),
                'raw_date' => Carbon::parse($movement->date)->format('Y-m-d'),
                'type' => (string) $movement->reference_type,
                'type_name' => SupplierLedger::typeName($movement->reference_type),
                // اسمٌ بديل تقرأه الشاشة في عمود «نوع الفاتورة»
                'reference_type' => SupplierLedger::typeName($movement->reference_type),
                'kind' => $kind,
                'supplier_name' => $movement->supplier_name ?: '—',
                'branch_name' => $movement->branch_name ?: '—',
                'description' => $movement->description,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'amount' => round($credit - $debit, 2),
                'paid' => round($paid, 2),
                'unpaid' => round(max($gross - $paid, 0), 2),
                'status' => $kind === 'purchase'
                    ? $this->paymentStatus($gross, $paid, ['entries_count' => 1])
                    : ['text' => SupplierLedger::typeName($movement->reference_type), 'class' => $kind === 'return' ? 'danger' : 'info', 'code' => $kind],
                'is_return' => $kind === 'return',
            ];
        }

        return $statement;
    }

    protected function sortRows(Collection $rows, ?string $sortBy, ?string $direction): Collection
    {
        $allowed = ['name', 'purchases', 'returns', 'net_purchases', 'payments', 'unpaid', 'purchases_count'];
        $key = in_array($sortBy, $allowed, true) ? $sortBy : 'purchases';

        $sorted = $key === 'name'
            ? $rows->sortBy($key, SORT_NATURAL | SORT_FLAG_CASE)
            : $rows->sortBy($key);

        return ($direction === 'asc' ? $sorted : $sorted->reverse())->values();
    }

    protected function emptyTotals(): array
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
            'unpaid' => 0.0,
            'entries_count' => 0,
            'employees_count' => 0,
        ];
    }

    /**
     * حالة السداد بمقارنة ما سدَّده الموظّف بصافي ما اشتراه.
     *
     * نفس منطق شاشة الموردين حرفياً، ولنفس السبب: الصافي قد يخرج سالباً
     * حين تفوق المرتجعاتُ مشترياتِ الفترة — وهي حالةٌ قائمة تُسمّى باسمها
     * بدل أن تُعرض «غير مسدد» على موظّف لا شيء عليه.
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

    protected function employeeOptions()
    {
        return $this->reportUserOptions();
    }

    protected function intOrNull($value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
