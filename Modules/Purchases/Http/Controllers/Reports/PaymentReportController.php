<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Purchases\Support\ReportPeriod;
use Modules\Purchases\Support\SupplierPaymentLedger;

/**
 * تقارير مدفوعات المشتريات — حسب المورّد وحسب الموظف.
 *
 * التقريران وجهان لمجموعة واحدة من سندات الصرف: الأول يسأل «لمن صُرف؟»
 * والثاني «من صرف؟». فالبيانات تُجلب مرةً واحدة من `SupplierPaymentLedger`
 * ثم تُجمَّع على المفتاح المطلوب — فلا يختلف إجمالي الشاشتين أبداً.
 */
class PaymentReportController extends Controller
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
        $this->middleware('permission:purchases.reports.payments', ['only' => [
            'purchasePayments', 'purchasePaymentsData', 'supplierPayments', 'supplierPaymentsData',
            'employeeSupplierPayments', 'employeeSupplierPaymentsData', 'paymentPurchases',
        ]]);
        $this->middleware('permission:purchases.reports.export', ['only' => ['exportPayments']]);
    }

    // =================================================================================
    // مدفوعات المشتريات — يومياً وشهرياً
    // =================================================================================

    /**
     * مدفوعات المشتريات موزَّعةً على الزمن.
     *
     * الشاشتان السابقتان تجيبان «لمن صُرف؟» و«من صرف؟»؛ وهذه تجيب
     * «متى صُرف؟» — فيُرى إيقاع الصرف: أيّ الأيام يثقل فيها، وأيّ الشهور،
     * وهل يتبع إيقاعَ الشراء أم يتخلّف عنه.
     *
     * الوحدة تتبع `view_type`: `daily` يوماً بيوم، و`monthly` شهراً بشهر،
     * و`details` سنداً سنداً. والأيام الخالية تظهر صفراً فلا ينقطع الخط
     * ولا يُظنّ يومٌ بلا صرفٍ يوماً غير موجود.
     */
    public function purchasePayments(Request $request)
    {
        $data = $this->buildTimeline($request);

        return view('purchases::purchases.report.purchaseReport.purchase_payments_timeline', array_merge([
            'suppliers' => \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::orderBy('trade_name'))->get(),
            'branches' => $this->reportBranchOptions(),
            'users' => $this->reportUserOptions(),
        ], $this->reportTreeContext($request), $data));
    }

    public function purchasePaymentsData(Request $request)
    {
        try {
            $data = $this->buildTimeline($request);

            return response()->json([
                'success' => true,
                'unit' => $data['unit'],
                'data' => $data['buckets'],
                'totals' => $data['totals'],
                'period' => $data['period']->key,
                'period_label' => $data['period']->labelWithDates(),
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.PaymentReport', 'purchasePaymentsData');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * صفوف الخط الزمني وإجمالياته.
     *
     * @return array<string, mixed>
     */
    protected function buildTimeline(Request $request): array
    {
        $period = ReportPeriod::fromRequest($request);
        $viewType = $request->input('view_type', 'daily');

        /*
         * فترةٌ طويلة معروضةً يوماً بيوم تعطي جدولاً لا يُقرأ، فالشهري هو
         * الافتراضي حين تتجاوز الفترة شهرين — وهو المعيار نفسه الذي تتبعه
         * رسوم بقيّة التقارير (`ReportPeriod::trendUnit`).
         */
        $unit = match ($viewType) {
            'monthly' => 'month',
            'daily' => 'day',
            default => $period->trendUnit(),
        };

        $ledger = SupplierPaymentLedger::fromFilters(
            $this->intOrNull($request->input('supplier_id')),
            $this->intOrNull($request->input('employee_id')),
            $this->reportBranchIds($request)
        )->visibleTo($this->visibleCreatorIds());

        $buckets = $ledger->buckets($period, $unit, ! $request->boolean('hide_empty'));
        $payments = $viewType === 'details' ? $ledger->payments($period) : collect();

        $active = $buckets->where('is_empty', false);
        $amount = round($buckets->sum('amount'), 2);
        $vouchers = $buckets->sum('vouchers');

        $peak = $active->sortByDesc('amount')->first();

        $totals = [
            'amount' => $amount,
            'vouchers' => $vouchers,
            'buckets' => $buckets->count(),
            'active_buckets' => $active->count(),
            'average_voucher' => $vouchers > 0 ? round($amount / $vouchers, 2) : 0.0,
            // المتوسّط على الفترات التي فيها حركة — القسمة على الخالية تخفض الرقم بلا معنى
            'average_bucket' => $active->count() > 0 ? round($amount / $active->count(), 2) : 0.0,
            'peak_label' => $peak['label'] ?? '—',
            'peak_amount' => $peak['amount'] ?? 0.0,
            'suppliers' => $buckets->max('suppliers') ?: 0,
        ];

        return [
            'buckets' => $buckets,
            'payments' => $payments,
            'totals' => $totals,
            'unit' => $unit,
            'viewType' => $viewType,
            'period' => $period,
            'fromDate' => $period->from,
            'toDate' => $period->to,
        ];
    }

    // =================================================================================
    // مدفوعات الموردين
    // =================================================================================

    /*
     * الشاشتان قالبٌ واحد `payments_by_axis` يقلبه `axis`: كانتا توأمين
     * متطابقَين لا يختلفان إلا في العنوان وترتيب عمودَين.
     */
    public function supplierPayments(Request $request)
    {
        return view(
            'purchases::purchases.report.purchaseReport.payments_by_axis',
            ['axis' => 'supplier'] + $this->buildPaymentReport($request, 'supplier_id', 'supplier_name')
        );
    }

    public function supplierPaymentsData(Request $request)
    {
        return $this->paymentJson($request, 'supplier_id', 'supplier_name');
    }

    // =================================================================================
    // مدفوعات الموظفين
    // =================================================================================

    public function employeeSupplierPayments(Request $request)
    {
        return view(
            'purchases::purchases.report.purchaseReport.payments_by_axis',
            ['axis' => 'employee'] + $this->buildPaymentReport($request, 'employee_id', 'employee_name')
        );
    }

    public function employeeSupplierPaymentsData(Request $request)
    {
        return $this->paymentJson($request, 'employee_id', 'employee_name');
    }

    /** تصدير المدفوعات — CSV بترميز يفتحه إكسل عربياً سليماً */
    public function exportPayments(Request $request)
    {
        $groupBy = $request->input('group') === 'employee' ? 'employee' : 'supplier';
        $data = $groupBy === 'employee'
            ? $this->buildPaymentReport($request, 'employee_id', 'employee_name')
            : $this->buildPaymentReport($request, 'supplier_id', 'supplier_name');

        $filename = 'supplier-payments-'.$data['fromDate']->format('Ymd').'-'.$data['toDate']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['التاريخ', 'رقم السند', 'النوع', 'المورد', 'الموظف', 'الفرع', 'مصدر الصرف', 'المبلغ']);

            foreach ($data['payments'] as $payment) {
                fputcsv($handle, [
                    $payment['raw_date'],
                    $payment['reference_number'],
                    $payment['type_name'],
                    $payment['supplier_name'],
                    $payment['employee_name'],
                    $payment['branch_name'],
                    $payment['source'],
                    number_format($payment['amount'], 2, '.', ''),
                ]);
            }

            fputcsv($handle, ['الإجمالي', '', '', '', '', '', '', number_format($data['totals']['total'], 2, '.', '')]);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =================================================================================
    // البناء المشترك
    // =================================================================================

    /**
     * سندات الفترة مجمَّعةً على المفتاح المطلوب، ومعها الإجماليات والرسوم.
     */
    protected function buildPaymentReport(Request $request, string $idKey, string $nameKey): array
    {
        $period = ReportPeriod::fromRequest($request);

        $ledger = SupplierPaymentLedger::fromFilters(
            $this->intOrNull($request->input('supplier_id')),
            $this->intOrNull($request->input('employee_id')),
            $this->reportBranchIds($request)
        )->visibleTo($this->visibleCreatorIds());

        $payments = $ledger->payments($period);

        if ($request->filled('min_amount')) {
            $min = (float) $request->input('min_amount');
            $payments = $payments->filter(fn ($p) => $p['amount'] >= $min)->values();
        }

        $grouped = SupplierPaymentLedger::groupBy($payments, $idKey, $nameKey);

        $totals = [
            'total' => round($payments->sum('amount'), 2),
            'count' => $payments->count(),
            'suppliers_count' => $payments->pluck('supplier_id')->unique()->count(),
            'employees_count' => $payments->pluck('employee_id')->unique()->count(),
            'average' => round($payments->avg('amount') ?: 0, 2),
            'largest' => round($payments->max('amount') ?: 0, 2),
            'groups_count' => $grouped->count(),
        ];

        return [
            'payments' => $payments,
            'grouped' => $grouped,
            'totals' => $totals,
            'trend' => SupplierPaymentLedger::trend($payments, $period),
            'bySource' => $this->summariseBy($payments, 'source'),
            'period' => $period,
            'fromDate' => $period->from,
            'toDate' => $period->to,
            'suppliers' => \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::orderBy('trade_name'))->get(),
            'employees' => $this->reportUserOptions(),
            'branches' => $this->reportBranchOptions(),
            'outstanding' => $this->outstandingBalance($request),
            ...$this->reportTreeContext($request),
        ];
    }

    protected function paymentJson(Request $request, string $idKey, string $nameKey)
    {
        try {
            $data = $this->buildPaymentReport($request, $idKey, $nameKey);

            return response()->json([
                'success' => true,
                'payments' => $data['payments']->values(),
                'grouped' => $data['grouped']->values(),
                'totals' => $data['totals'],
                'trend' => $data['trend'],
                'by_source' => $data['bySource'],
                'period_label' => $data['period']->labelWithDates(),
                'from_date' => $data['fromDate']->format('Y-m-d'),
                'to_date' => $data['toDate']->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.PaymentReport', 'paymentJson');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** توزيع المبالغ على قيمة عمود واحد — مصدر الصرف مثلاً */
    protected function summariseBy(Collection $payments, string $key): array
    {
        return $payments
            ->groupBy(fn ($payment) => $payment[$key] ?: '—')
            ->map(fn ($group) => round(collect($group)->sum('amount'), 2))
            ->sortDesc()
            ->map(fn ($amount, $label) => ['label' => $label, 'amount' => $amount])
            ->values()
            ->all();
    }

    /**
     * ما زال مستحقاً للموردين حتى نهاية الفترة — سياقُ رقم المدفوعات.
     *
     * رقم المدفوعات وحده لا يقول شيئاً: مليونٌ مدفوع يعني الكثير حين
     * يبقى مئة ألف، وقليلاً حين يبقى عشرة ملايين.
     */
    protected function outstandingBalance(Request $request): float
    {
        $query = Account::whereNotNull('supplier_id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        /* على محور الأشخاص: موردو المستخدم وحدهم — من له لديهم مستندٌ يراه.
           كان المجموعُ رصيدَ موردي المنشأة كلِّهم فوق تقريرٍ يسرد سنداته هو. */
        if (\App\Support\DataScope::isPeopleAxis()) {
            $query->whereIn('supplier_id', PurchaseInvoice::whereIn('created_by', \App\Support\DataScope::userIds() ?? [0])
                ->whereNotNull('supplier_id')->select('supplier_id'));
        }

        return round((float) $query->sum('balance'), 2);
    }

    protected function intOrNull($value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    // =================================================================================
    // مدفوعات المشتريات حسب الفاتورة
    // =================================================================================

    /**
     * تقرير مدفوعات المشتريات — الفواتير وما سُدِّد منها.
     */
    public function paymentPurchases(Request $request)
    {
        $validSupplierIds = Account::whereNotNull('supplier_id')
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->pluck('supplier_id')
            ->unique()
            ->toArray();

        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::orderBy('trade_name'))->get();
        $branches = $this->reportBranchOptions();

        $period = ReportPeriod::fromRequest($request);
        $fromDate = $period->from;
        $toDate = $period->to;

        /* الفواتيرُ وحدَها: الجدولُ يحمل أوامرَ الشراء والمرتجعات بالنوع
           نفسِه، وكان أمرُ شراءٍ (٤٬١٠٤٫٢٥) يُعدّ فاتورةً مستحقّةً هنا. */
        $query = PurchaseInvoice::with(['supplier', 'branch', 'payments', 'creator'])
            ->where('type', \App\Enums\PurchaseDocumentType::INVOICE->value)
            ->whereIn('supplier_id', $validSupplierIds)
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()]);

        // محورُ الأشخاص — التقريرُ الوحيد في الوحدة الذي كان يُسقطه
        $this->scopeToVisibleCreators($query);

        if ($branchIds = $this->reportBranchIds($request)) {
            $query->whereIn('branch_id', $branchIds);
        }

        // ما أُقفل محاسبياً لا يُعرض — نفس تعريف بقيّة تقارير المشتريات
        $this->excludeClosedRanges($query, 'purchase_invoices.date');

        $invoices = $query->orderBy('date', $request->input('order_by', 'desc'))->get();
        $groupedInvoices = $invoices->groupBy('supplier_id');

        /* ما رُدَّ من الفاتورة لا يبقى ديناً: فاتورةٌ رُدَّت كلُّها (٥٬٧٥٠) كانت تُعدّ
           «متبقّياً» كاملاً وهي لا تُسدَّد أصلاً. فالمتبقّي = الإجمالي − المرتجع −
           المسدَّد، ولا ينزل عن الصفر — كما في تقرير أعمار الفواتير. */
        $returnedByInvoice = \Illuminate\Support\Facades\DB::table('purchase_invoices')
            ->where('type', \App\Enums\PurchaseDocumentType::RETURN->value)
            ->whereIn('reference_id', $invoices->pluck('id')->all())
            ->groupBy('reference_id')
            ->selectRaw('reference_id, COALESCE(SUM(grand_total), 0) as returned')
            ->pluck('returned', 'reference_id');

        foreach ($invoices as $invoice) {
            $invoice->returned_amount = round((float) ($returnedByInvoice[$invoice->id] ?? 0), 2);
        }

        $remainingOf = fn ($invoice) => max(0.0, (float) $invoice->grand_total - (float) $invoice->returned_amount - $invoice->paidAmount());

        $totalGrandTotal = 0;
        $totalPaid = 0;
        $totalRemaining = 0;
        $totalReturned = 0;

        foreach ($invoices as $invoice) {
            $totalGrandTotal += (float) $invoice->grand_total;
            $totalPaid += $invoice->paidAmount();
            $totalReturned += $invoice->returned_amount;
            $totalRemaining += $remainingOf($invoice);
        }

        $supplierStats = $groupedInvoices->map(function ($supplierInvoices) use ($remainingOf) {
            $paid = $supplierInvoices->sum(fn ($invoice) => $invoice->paidAmount());
            $remaining = $supplierInvoices->sum(fn ($invoice) => $remainingOf($invoice));

            return [
                'name' => $supplierInvoices->first()->supplier->trade_name ?? 'غير معروف',
                'paid' => $paid,
                'remaining' => $remaining,
            ];
        })->sortByDesc('remaining')->take(10);

        return view('purchases::purchases.report.purchaseReport.paymentPurchases', [
            ...$this->reportTreeContext($request),
            'suppliers' => $suppliers,
            'branches' => $branches,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'groupedInvoices' => $groupedInvoices,
            'totalGrandTotal' => $totalGrandTotal,
            'totalPaid' => $totalPaid,
            'totalRemaining' => $totalRemaining,
            'totalReturned' => $totalReturned,
            'totals' => [
                'totalGrandTotal' => $totalGrandTotal,
                'totalPaid' => $totalPaid,
                'totalReturned' => $totalReturned,
                'totalRemaining' => $totalRemaining,
            ],
            'chartData' => [
                'labels' => $supplierStats->pluck('name')->values()->toArray(),
                'paid' => $supplierStats->pluck('paid')->values()->toArray(),
                'remaining' => $supplierStats->pluck('remaining')->values()->toArray(),
            ],
        ]);
    }

    /** نطاق تاريخ مسمّى — يبقى للتوافق مع نداءات قائمة */
    protected function getDateRange(string $dateType): array
    {
        $period = ReportPeriod::make(match ($dateType) {
            'today' => 'today',
            'yesterday' => 'yesterday',
            'this_week' => 'week',
            'this_month' => 'month',
            'this_year' => 'year',
            default => 'month',
        });

        return ['from' => $period->from, 'to' => $period->to];
    }

    /** اسم طريقة الدفع كما تخزَّن في الفواتير */
    public static function paymentMethodName($method): string
    {
        return [
            1 => 'نقدي',
            2 => 'شيك',
            3 => 'تحويل بنكي',
            4 => 'بطاقة ائتمان',
        ][$method] ?? 'غير محدد';
    }
}
