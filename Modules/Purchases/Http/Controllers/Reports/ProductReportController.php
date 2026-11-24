<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Enums\PurchaseDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Purchases\Support\ProductPurchaseLedger;
use Modules\Purchases\Support\ReportPeriod;

/**
 * تقرير المشتريات حسب الصنف — ماذا اشترت المنشأة من كل منتج وبكم.
 *
 * الأرقام من `ProductPurchaseLedger`: بنودُ مستنداتٍ لها قيدٌ مرحَّل، لا
 * فواتيرُ مسوَّدة ولا أوامرُ شراء. ويُعرض إلى جانبها ما رُحِّل فعلاً إلى
 * حساب المشتريات وحساب ضريبة المدخلات، فيرى المحاسب الربط بالدفتر.
 *
 * @see ProductPurchaseLedger القاعدة المحاسبية وعلّة استخراجها من هنا
 */
class ProductReportController extends Controller
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
        $this->middleware('permission:purchases.reports.by_product', ['only' => ['index', 'ajaxData']]);
        $this->middleware('permission:purchases.reports.export', ['only' => ['exportExcel']]);
    }

    public function index(Request $request)
    {
        $data = $this->buildReport($request);

        return view('purchases::purchases.report.purchaseReport.by_Product', array_merge([
            // لا عمود `code` في `products` — الرقم التسلسلي هو المعرِّف المعروض
            'products' => Product::orderBy('name')->get(['id', 'name', 'serial_number']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            // فلاترُ المورِّد والمستودع تتبع فرعَ الشريط العلويّ
            'suppliers' => \Modules\Purchases\Support\HeaderPickers::suppliers(Supplier::orderBy('trade_name'))->get(['id', 'trade_name']),
            'storehouses' => \Modules\Purchases\Support\HeaderPickers::storeHouses(StoreHouse::orderBy('name'))->get(['id', 'name']),
            'branches' => $this->reportBranchOptions(['id', 'name']),
            'users' => $this->reportUserOptions(['id', 'name']),
        ], $this->reportTreeContext($request), $data));
    }

    public function ajaxData(Request $request)
    {
        try {
            $data = $this->buildReport($request);

            return response()->json([
                'success' => true,
                'data' => $data['reportData']->values(),
                'grouped_products' => $data['reportData']->values(),   // اسمٌ قديم يقرأه سكربت الشاشة
                'totals' => $data['totals'],
                'control' => $data['control'],
                'period' => $data['period']->key,
                'period_label' => $data['period']->labelWithDates(),
                'from_date' => $data['fromDate']->format('d/m/Y'),
                'to_date' => $data['toDate']->format('d/m/Y'),
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.ProductReport', 'ajaxData');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** تصدير الملخّص — CSV بترميز UTF‑8 مع BOM ليفتحه إكسل عربياً سليماً */
    public function exportExcel(Request $request)
    {
        $data = $this->buildReport($request);

        $columns = [
            'الكود', 'الصنف', 'التصنيف', 'المستندات', 'الكمية المشتراة', 'كمية المرتجع',
            'صافي الكمية', 'إجمالي المشتريات', 'المرتجعات', 'إشعارات مدينة',
            'صافي التكلفة', 'الخصم', 'متوسط سعر الوحدة', 'الموردون',
        ];

        $filename = 'purchases-by-product-'.$data['fromDate']->format('Ymd').'-'.$data['toDate']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data, $columns) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);

            foreach ($data['reportData'] as $row) {
                fputcsv($handle, [
                    $row['code'], $row['name'], $row['category'], $row['documents'],
                    $row['purchases_qty'], $row['returns_qty'], $row['quantity'],
                    number_format($row['purchases'], 2, '.', ''),
                    number_format($row['returns'], 2, '.', ''),
                    number_format($row['debit_notes'], 2, '.', ''),
                    number_format($row['net_amount'], 2, '.', ''),
                    number_format($row['discount'], 2, '.', ''),
                    number_format($row['avg_price'], 2, '.', ''),
                    $row['suppliers_count'],
                ]);
            }

            $t = $data['totals'];
            fputcsv($handle, [
                '', 'الإجمالي العام', '', $t['documents'],
                $t['purchases_qty'], $t['returns_qty'], $t['quantity'],
                number_format($t['purchases'], 2, '.', ''),
                number_format($t['returns'], 2, '.', ''),
                number_format($t['debit_notes'], 2, '.', ''),
                number_format($t['net_amount'], 2, '.', ''),
                number_format($t['discount'], 2, '.', ''),
                '', '',
            ]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =================================================================================
    // البناء
    // =================================================================================

    protected function buildReport(Request $request): array
    {
        $period = ReportPeriod::fromRequest($request);

        $ledger = ProductPurchaseLedger::fromFilters([
            'product' => $request->input('product'),
            'category' => $request->input('category'),
            'storehouse' => $request->input('storehouse'),
            'supplier' => $request->input('supplier'),
            'branch_id' => $this->reportBranchIds($request),
            'employee_id' => $request->input('added_by') ?: $request->input('employee_id'),
            'payment_status' => $request->input('status'),
            'receiving_status' => $request->input('receiving_status'),
            'invoice_type' => $request->input('invoice_type'),
        ])->visibleTo($this->visibleCreatorIds());

        $reportData = $ledger->productTotals($period);
        $wantsDetails = $request->input('view_type', 'summary') === 'details';

        if ($wantsDetails) {
            $lines = $ledger->lines($period)->groupBy('product_id');

            $reportData = $reportData->map(function ($row) use ($lines) {
                $row['items'] = $this->buildLines($lines->get($row['product_id'], collect()));

                return $row;
            });
        }

        $reportData = $this->sortRows($reportData, $request->input('sort_by', 'net_amount'), $request->input('sort_dir', 'desc'));

        $totals = $this->sumRows($reportData);
        // الفاتورةُ ذاتُ الأصناف المتعدّدة مستندٌ واحد لا مستندٌ لكلّ صنف
        $totals['documents'] = $totals['invoice_count'] = $ledger->documentCount($period);

        return [
            'reportData' => $reportData,
            'totals' => $totals,
            'control' => $ledger->controlTotals($period),
            'period' => $period,
            'fromDate' => $period->from,
            'toDate' => $period->to,
            'isSummary' => ! $wantsDetails,
            'currencySymbol' => CurrencySettings::symbol(),
        ];
    }

    /**
     * بنود صنفٍ واحد مرتّبةً — كل سطر بمستنده ومورّده ومخزنه.
     *
     * الكمية والمبلغ يُعرضان بإشارتهما: المرتجع سالبٌ لأنه يُنقص المخزون
     * والتكلفة معاً.
     */
    protected function buildLines(Collection $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $type = PurchaseDocumentType::fromValue($line->document_type);
            $isReturn = $type === PurchaseDocumentType::RETURN;
            $isDebitNote = $type === PurchaseDocumentType::DEBIT_NOTE;
            $sign = $isReturn ? -1 : 1;

            $rows[] = [
                'line_id' => (int) $line->line_id,
                'document_id' => (int) $line->document_id,
                'document_code' => $line->document_code ?: '—',
                'document_type' => $line->document_type,
                'type_name' => $type?->getName() ?? 'مستند',
                'kind' => $isReturn ? 'return' : ($isDebitNote ? 'debit_note' : 'purchase'),
                'date' => Carbon::parse($line->date)->format('d/m/Y'),
                'raw_date' => Carbon::parse($line->date)->format('Y-m-d'),
                'supplier_name' => $line->supplier_name ?: '—',
                'storehouse_name' => $line->storehouse_name ?: '—',
                'unit_name' => $line->unit_name ?: null,
                // الإشعار المدين تسويةُ قيمة بلا حركة صنف، فكمّيته صفر مهما كُتب في البند
                'quantity' => $isDebitNote ? 0 : round($sign * (float) $line->quantity, 2),
                'unit_price' => round((float) $line->unit_price, 2),
                'discount' => round((float) $line->discount_value, 2),
                'amount' => round($sign * (float) $line->total, 2),
                'receiving_status' => $line->receiving_status,
                'payment_status' => $line->payment_status,
                'is_return' => $isReturn,
            ];
        }

        return $rows;
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    protected function sumRows(Collection $rows): array
    {
        $totals = [
            'products_count' => $rows->count(),
            'documents' => 0,
            'lines' => 0,
            'purchases_qty' => 0.0,
            'returns_qty' => 0.0,
            'quantity' => 0.0,
            'purchases' => 0.0,
            'returns' => 0.0,
            'debit_notes' => 0.0,
            'net_amount' => 0.0,
            'gross' => 0.0,
            'discount' => 0.0,
        ];

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                if ($key !== 'products_count' && isset($row[$key])) {
                    $totals[$key] += $row[$key];
                }
            }
        }

        $totals = array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals);

        // مرادفات قديمة يقرأها القالب والسكربت
        $totals['total_quantity'] = $totals['quantity'];
        $totals['total_purchases'] = $totals['purchases'];
        $totals['total_returns'] = $totals['returns'];
        $totals['total_amount'] = $totals['net_amount'];
        $totals['total_discount'] = $totals['discount'];
        $totals['invoice_count'] = $totals['documents'];

        return $totals;
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    protected function sortRows(Collection $rows, ?string $sortBy, ?string $direction): Collection
    {
        $allowed = ['name', 'code', 'category', 'quantity', 'net_amount', 'purchases', 'returns', 'discount', 'avg_price', 'documents'];
        $key = in_array($sortBy, $allowed, true) ? $sortBy : 'net_amount';

        $sorted = in_array($key, ['name', 'code', 'category'], true)
            ? $rows->sortBy($key, SORT_NATURAL | SORT_FLAG_CASE)
            : $rows->sortBy($key);

        return ($direction === 'asc' ? $sorted : $sorted->reverse())->values();
    }
}
