<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Purchases\Support\ReceivingLedger;
use Modules\Purchases\Support\ReportPeriod;

/**
 * تقرير المشتريات حسب البند — المشترى والمستلم وما لم يصل بعد.
 *
 * يجيب سؤالاً لا تجيبه بقيّة تقارير المشتريات: **ما الذي دفعنا ثمنه ولم
 * يدخل المستودع؟** وبقيّتها تقيس ما اشتُري؛ وهذا يقيس الفجوة بين الشراء
 * والاستلام.
 *
 * الكمية المستلمة تُقرأ من الإذن المخزني المعتمد لا من
 * `purchase_invoices.receiving_status` — انظر `ReceivingLedger` لعلّة ذلك.
 *
 * @see ReceivingLedger مصدر الأرقام وقاعدة احتساب الاستلام
 */
class ReceivingReportController extends Controller
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
        $this->middleware('permission:purchases.reports.receiving', ['only' => ['index', 'ajaxData']]);
        $this->middleware('permission:purchases.reports.export', ['only' => ['exportExcel']]);
    }

    public function index(Request $request)
    {
        $data = $this->buildReport($request);

        return view('purchases::purchases.report.purchaseReport.receiving_report', array_merge([
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
                'data' => $data['viewType'] === 'lines' ? $data['lines'] : $data['byProduct'],
                'totals' => $data['totals'],
                'period' => $data['period']->key,
                'period_label' => $data['period']->labelWithDates(),
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.ReceivingReport', 'ajaxData');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** تصدير — CSV بترميز UTF‑8 مع BOM ليفتحه إكسل عربياً سليماً */
    public function exportExcel(Request $request)
    {
        $data = $this->buildReport($request);

        $filename = 'purchase-receiving-'.$data['fromDate']->format('Ymd').'-'.$data['toDate']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'التاريخ', 'الفاتورة', 'المورد', 'الفرع', 'الكود', 'الصنف', 'التصنيف',
                'المشترى', 'المستلم', 'غير المستلم', 'سعر الوحدة', 'قيمة غير المستلم', 'الحالة',
            ]);

            foreach ($data['lines'] as $row) {
                fputcsv($handle, [
                    $row['raw_date'], $row['invoice_code'], $row['supplier_name'], $row['branch_name'],
                    $row['product_code'], $row['product_name'], $row['category_name'],
                    $row['purchased'], $row['received'], $row['pending'],
                    number_format($row['unit_price'], 2, '.', ''),
                    number_format($row['pending_value'], 2, '.', ''),
                    $row['state']['text'],
                ]);
            }

            $t = $data['totals'];
            fputcsv($handle, [
                'الإجمالي', '', '', '', '', '', '',
                $t['purchased'], $t['received'], $t['pending'], '',
                number_format($t['pending_value'], 2, '.', ''), '',
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
        $viewType = $request->input('view_type', 'products');
        $storeHouseId = $this->intOrNull($request->input('storehouse'));

        $ledger = ReceivingLedger::fromFilters([
            'product' => $request->input('product'),
            'category' => $request->input('category'),
            'supplier' => $request->input('supplier'),
            'branch_id' => $this->reportBranchIds($request),
            'employee_id' => $request->input('employee_id'),
            'storehouse' => $storeHouseId,
            'receipt_state' => $request->input('receipt_state'),
        ])->visibleTo($this->visibleCreatorIds());

        $lines = $ledger->lines($period);
        $byProduct = ReceivingLedger::byProduct($lines);
        $stock = ReceivingLedger::warehouseStock($storeHouseId);

        // رصيد المستودع إلى جانب المستلم — أي انحرافٍ بينهما يظهر بدل أن يُخفى
        $byProduct = $byProduct->map(function ($row) use ($stock) {
            $onHand = $stock[$row['product_id']] ?? 0.0;
            $row['on_hand'] = round($onHand, 2);
            $row['stock_gap'] = round($onHand - $row['received'], 2);

            return $row;
        });

        return [
            'lines' => $lines,
            'byProduct' => $byProduct,
            'totals' => $this->summarise($lines, $byProduct),
            'viewType' => $viewType,
            'period' => $period,
            'fromDate' => $period->from,
            'toDate' => $period->to,
            'currencySymbol' => CurrencySettings::symbol(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  Collection<int, array<string, mixed>>  $byProduct
     */
    protected function summarise(Collection $lines, Collection $byProduct): array
    {
        $purchased = round($lines->sum('purchased'), 2);
        $received = round($lines->sum('received'), 2);
        $pending = round($lines->sum('pending'), 2);

        $states = $lines->groupBy(fn ($l) => $l['state']['code']);

        return [
            'lines_count' => $lines->count(),
            'products_count' => $byProduct->count(),
            'invoices_count' => $lines->pluck('invoice_id')->unique()->count(),
            'purchased' => $purchased,
            'received' => $received,
            'pending' => $pending,
            // نسبة الاستلام: أوضح رقم واحد يلخّص انضباط التوريد
            'rate' => $purchased > 0 ? round($received / $purchased * 100, 1) : 0.0,
            'amount' => round($lines->sum('amount'), 2),
            'received_value' => round($lines->sum('received_value'), 2),
            'pending_value' => round($lines->sum('pending_value'), 2),
            'full_count' => $states->get('received', collect())->count(),
            'partial_count' => $states->get('partial', collect())->count(),
            'none_count' => $states->get('pending', collect())->count(),
            'over_received' => round($lines->sum('over_received'), 2),
            // أصنافٌ رصيدها في المستودع يخالف ما تقوله أذون الاستلام
            'stock_mismatch' => $byProduct->filter(fn ($r) => abs($r['stock_gap'] ?? 0) > 0.5)->count(),
        ];
    }

    protected function intOrNull($value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
