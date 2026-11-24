<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Constants\permissions\PermissionSources;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;

class TrialBalanceReportController extends ReportController
{
    /*
        الفترة المحاسبية: النطاقُ الافتراضي للتقرير هو السنةُ المالية الجارية
        لا مدًى مكتوبٌ في الشيفرة.
    */
    use RespectsAccountingPeriods;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.trial_balance');
    }

    public function index(Request $request)
    {
        // جلب البيانات الأساسية للفلاتر — الأصناف المخزنية وحدها
        $products = Product::whereIn('type', ProductType::stockedValues())->get();
        $categories = Category::all();
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        return view('stock::report.stock_report.trial_balance',
            compact('products', 'categories', 'warehouses'));
    }

    /**
     * جلب بيانات ميزان المراجعة للمخزون عبر AJAX
     */
    public function trialBalanceAjax(Request $request)
    {
        try {
            // بناء الاستعلام الأساسي — الأصناف المخزنية وحدها
            $query = Product::query()
                ->whereIn('type', ProductType::stockedValues())
                ->with(['category', 'product_details.storeHouse'])
                ->select('products.*');

            // تطبيق الفلاتر
            $this->applyTrialBalanceFilters($query, $request);

            // تطبيق الترتيب
            $this->applyTrialBalanceSorting($query, $request);

            // جلب البيانات
            $products = $query->get();

            // معالجة البيانات للعرض
            $processedProducts = $this->processTrialBalanceData($products, $request);

            // حساب الإجماليات
            $totals = $this->calculateTrialBalanceTotals($processedProducts);

            // إعداد بيانات الرسم البياني
            $chartData = $this->prepareTrialBalanceChartData($processedProducts, $request);

            // تحديد التواريخ للعرض
            /*
                التاريخان المعروضان هما المطبَّقان فعلاً لا ما كُتب وحده:
                «غير محدد» فوق تقريرٍ مفلتَر يُقرأ كأنه يعرض التاريخ كلَّه.
            */
            $displayScope = $this->stockReportScope($request, 'start_date', 'end_date');
            $fromDate = $displayScope['start'] ? Carbon::parse($displayScope['start'])->format('d/m/Y') : 'كل الفترات';
            $toDate = $displayScope['end'] ? Carbon::parse($displayScope['end'])->format('d/m/Y') : 'كل الفترات';

            return response()->json([
                'success' => true,
                'products' => $processedProducts,
                'totals' => $totals,
                'chart_data' => $chartData,
                'group_by' => $this->getGroupByLabel($request->group_by),
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'message' => 'تم تحميل البيانات بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'trialBalanceAjax');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تطبيق فلاتر ميزان المراجعة للمخزون
     */
    private function applyTrialBalanceFilters($query, Request $request)
    {
        // فلتر المنتج
        if ($request->filled('product')) {
            $query->where('id', $request->product);
        }

        // فلتر التصنيف
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // فلتر المستودع
        if ($request->filled('warehouse')) {
            $this->authorizeWarehouseScope((int) $request->warehouse);
            $query->whereHas('product_details', function ($q) use ($request) {
                $q->where('store_house_id', $request->warehouse);
            });
        } else {
            // نطاق البيانات: بلا فلتر مستودعٍ صريح، لا يُعرض إلا منتجٌ له رصيدٌ
            // في مستودعٍ ضمن نطاق المستخدم (فرعه/منطقته)
            $warehouseIds = $this->allowedWarehouseIds();
            if ($warehouseIds !== null) {
                $query->whereHas('productDetails', function ($q) use ($warehouseIds) {
                    $q->whereIn('store_house_id', $warehouseIds ?: [0]);
                });
            }
        }

        // البحث النصي
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // إخفاء الأرصدة الصفرية
        if ($request->boolean('hide_zero_balance')) {
            $query->whereHas('product_details', function ($q) {
                $q->where('quantity', '!=', 0);
            });
        }

        /*
            **«بحركة فقط» يُقاس على الفترة المحاسبية.**

            كان الشرطُ يتطلّب تاريخَين مكتوبَين، فمن لم يكتبهما — وهو الغالب —
            مرّ الفلترُ بلا أثر: يُطلب «بحركة فقط» فتُعرض المنتجاتُ كلُّها.
            وصار المدى من الفترة الجارية حين لا يُكتب شيء.
        */
        if ($request->boolean('show_movements_only')) {
            $scope = $this->stockReportScope($request, 'start_date', 'end_date');

            // الحركةُ إذنٌ معتمدٌ بتاريخه — لا بندُ فاتورةٍ بتاريخ إنشائه
            $query->whereExists(function ($q) use ($scope) {
                $q->select(DB::raw(1))
                    ->from('warehouse_permits_products as mp')
                    ->join('warehouse_permits as mw', 'mw.id', '=', 'mp.warehouse_permits_id')
                    ->whereColumn('mp.product_id', 'products.id')
                    ->whereIn('mw.status', ['approved', 'apporod', 'delivered', 'done'])
                    ->when($scope['mode'] !== 'all' && $scope['start'] && $scope['end'], fn ($w) => $w->whereBetween('mw.permission_date', [
                        $scope['start'].' 00:00:00',
                        $scope['end'].' 23:59:59',
                    ]));
            });
        }
    }

    /**
     * تطبيق الترتيب لميزان المراجعة للمخزون
     */
    private function applyTrialBalanceSorting($query, Request $request)
    {
        switch ($request->sort_by) {
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'quantity_desc':
                $query->orderByRaw('(
                    SELECT COALESCE(SUM(quantity), 0)
                    FROM product_details
                    WHERE product_details.product_id = products.id
                ) DESC');
                break;
            case 'quantity_asc':
                $query->orderByRaw('(
                    SELECT COALESCE(SUM(quantity), 0)
                    FROM product_details
                    WHERE product_details.product_id = products.id
                ) ASC');
                break;
            case 'value_desc':
                $query->orderByRaw('(
                    SELECT COALESCE(SUM(quantity * unit_price), 0)
                    FROM product_details
                    WHERE product_details.product_id = products.id
                ) DESC');
                break;
            case 'value_asc':
                $query->orderByRaw('(
                    SELECT COALESCE(SUM(quantity * unit_price), 0)
                    FROM product_details
                    WHERE product_details.product_id = products.id
                ) ASC');
                break;
            case 'movement_desc':
                $query->orderByRaw('(
                    SELECT COALESCE(COUNT(*), 0)
                    FROM invoice_items
                    WHERE invoice_items.product_id = products.id
                ) DESC');
                break;
            default:
                $query->orderBy('name', 'asc');
        }
    }

    /**
     * معالجة بيانات ميزان المراجعة للعرض
     */
    private function processTrialBalanceData($products, Request $request)
    {
        $processedData = [];

        // النطاقُ المطبَّق لا الحقلان الخامّان: الفترةُ المختارة من القائمة
        // وحدها — بلا تاريخين مكتوبين — كانت لا تغيّر أرقامَ الحركة
        $scope = $this->stockReportScope($request, 'start_date', 'end_date');
        $startDate = $scope['mode'] === 'all' ? null : $scope['start'];
        $endDate = $scope['mode'] === 'all' ? null : $scope['end'];

        $warehouseIds = $request->filled('warehouse')
            ? [(int) $request->warehouse]
            : $this->allowedWarehouseIds();

        $movements = $this->permitMovements($products->pluck('id')->all(), $startDate, $endDate, $warehouseIds);

        foreach ($products as $product) {
            $moves = $movements[$product->id] ?? null;

            // الرصيد الابتدائي: صافي الأذون المعتمدة قبل بداية الفترة
            $initialQuantity = (float) ($moves->opening_qty ?? 0);
            $initialAmount = $initialQuantity * ($product->purchase_price ?? 0);

            $incomingData = [
                'quantity' => (float) ($moves->in_qty ?? 0),
                'amount' => (float) ($moves->in_amount ?? 0),
            ];

            $outgoingData = [
                'quantity' => (float) ($moves->out_qty ?? 0),
                'amount' => (float) ($moves->out_amount ?? 0),
            ];

            // حساب الرصيد النهائي
            $netQuantity = $initialQuantity + $incomingData['quantity'] - $outgoingData['quantity'];
            $netAmount = $initialAmount + $incomingData['amount'] - $outgoingData['amount'];

            // تطبيق فلتر إخفاء الأرصدة الصفرية
            if ($request->boolean('hide_zero_balance') && $netQuantity == 0) {
                continue;
            }

            // تطبيق فلتر إظهار المنتجات بحركة فقط
            if ($request->boolean('show_movements_only') &&
                ($incomingData['quantity'] == 0 && $outgoingData['quantity'] == 0)) {
                continue;
            }

            // إعداد بيانات المنتج
            $productData = [
                'id' => $product->id,
                'code' => $product->serial_number ?? $product->id,
                'name' => $product->name,
                'category' => $product->category ? $product->category->name : null,
                'brand' => $product->brand,
                'warehouse' => $this->getWarehouseNames($product),

                // الرصيد الابتدائي
                'initial_quantity' => $initialQuantity,
                'initial_amount' => $initialAmount,

                // حركات الفترة - الوارد
                'incoming_quantity' => $incomingData['quantity'],
                'incoming_amount' => $incomingData['amount'],

                // حركات الفترة - المنصرف
                'outgoing_quantity' => $outgoingData['quantity'],
                'outgoing_amount' => $outgoingData['amount'],

                // الرصيد النهائي
                'net_quantity' => $netQuantity,
                'net_amount' => $netAmount,

                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];

            // تطبيق التجميع إذا كان مطلوباً
            if ($request->filled('group_by')) {
                $this->groupTrialBalanceData($processedData, $productData, $request->group_by);
            } else {
                $processedData[] = $productData;
            }
        }

        return $processedData;
    }

    /**
     * حركةُ الأصناف من **الأذون المخزنية المعتمدة** بتاريخ الإذن.
     *
     * كان الحسابُ من `invoice_items.type` (purchase/sale/…) و
     * `product_details.type_of_operation` (in/out) بتاريخ `created_at` —
     * والعمودان لا يحملان هذه القيم أصلاً (`product`/`packege`، و`null`/`1`)،
     * فالواردُ والمنصرفُ صفرٌ في كلّ فترة والرصيدُ الابتدائيُّ صفر، والتقريرُ
     * لا يتغيّر مهما اختير من فترة. والإذنُ هو سجلُّ حركة المخزون الوحيد،
     * وبه يُقرأ كشفُ حساب المستودع — فيُقرأ الميزانُ بالقاعدة نفسِها:
     * نوعُ الإذن 1 وارد و2 منصرف، والتحويلُ بإشارة الكمية، وغيرُهما بمصدره.
     *
     * بلا حصرِ مستودعات يُستبعد التحويلُ بين المخازن: صافيه صفر، وعدُّه
     * يُضخّم الواردَ والمنصرفَ معاً.
     *
     * @param  array<int>  $productIds
     * @param  array<int>|null  $warehouseIds  null = كلُّ المستودعات
     * @return array<int, object> لكلّ صنف: opening_qty, in_qty, in_amount, out_qty, out_amount
     */
    private function permitMovements(array $productIds, ?string $startDate, ?string $endDate, ?array $warehouseIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $incoming = implode(',', array_map('intval', [
            PermissionSources::SALES_RETURN,
            PermissionSources::SALES_CREDIT_NOTE,
            PermissionSources::WAREHOUSE_ADD,
            PermissionSources::PURCHASE_INVOICE,
            PermissionSources::INVENTORY_RECEIVED,
            PermissionSources::WAREHOUSE_OPENING_BALANCE,
            PermissionSources::MANUFACTURING_MAIN_PRODUCT,
            PermissionSources::MANUFACTURING_RETURNS,
            PermissionSources::POS_SHIFT_INBOUND,
            PermissionSources::PRODUCT_EXCHANGE_RETURN,
        ]));

        /*
            اتجاهُ السطر من منظور المستودعات المحصورة ($warehouseIds):

            - إذنٌ بطرفٍ واحد يتبع طرفَه: «إلى» وارد و«من» منصرف — مرتجعُ
              المشتريات يحمل «من» مستودع بكميةٍ موجبة وهو منصرف.
            - التحويلُ بين مخزنين داخليٌّ لا يغيّر رصيدَ المنشأة: يُعدّ حين
              يُحصر في مستودعات، فالسطرُ السالب يخرج من المصدر والموجبُ يدخل
              الوجهة — وتحويلٌ بلا سطرٍ سالب (سطورُه موجبةٌ وحدها) يُخرج
              كميّتَه من المصدر ويُدخلها الوجهة معاً.

            و`COALESCE` على نوع الإذن: كثيرٌ من الأذون بلا نوع، والمقارنةُ مع
            NULL تُسقط السطرَ من كلّ شرطٍ مركّب في SQL.
        */
        $ids = $warehouseIds === null ? null : implode(',', array_map('intval', $warehouseIds ?: [0]));
        $inScope = fn (string $column) => $ids === null ? '1 = 1' : "$column IN ($ids)";
        $type = 'COALESCE(w.permission_type, 0)';
        $transfer = "((w.from_store_houses_id IS NOT NULL AND w.to_store_houses_id IS NOT NULL) OR $type = 3)";
        $pairless = 'NOT EXISTS (SELECT 1 FROM warehouse_permits_products neg WHERE neg.warehouse_permits_id = w.id AND neg.quantity < 0)';
        $member = $ids === null ? '1 = 1' : "(w.from_store_houses_id IN ($ids) OR w.to_store_houses_id IN ($ids)
            OR (w.from_store_houses_id IS NULL AND w.to_store_houses_id IS NULL AND w.store_houses_id IN ($ids)))";
        $transferDelta = $ids === null ? '0' : "(CASE WHEN p.quantity < 0
                THEN (CASE WHEN {$inScope('w.from_store_houses_id')} THEN p.quantity ELSE 0 END)
                ELSE (CASE WHEN {$inScope('w.to_store_houses_id')} THEN p.quantity ELSE 0 END)
                    - (CASE WHEN {$inScope('w.from_store_houses_id')} AND $pairless THEN p.quantity ELSE 0 END)
            END)";
        $delta = "(CASE
            WHEN $transfer THEN $transferDelta
            WHEN NOT COALESCE($member, 0) THEN 0
            WHEN $type = 1 THEN ABS(p.quantity)
            WHEN $type = 2 THEN -ABS(p.quantity)
            WHEN w.to_store_houses_id IS NOT NULL THEN ABS(p.quantity)
            WHEN w.from_store_houses_id IS NOT NULL THEN -ABS(p.quantity)
            WHEN w.permission_source_id IN ($incoming) THEN ABS(p.quantity)
            ELSE -ABS(p.quantity) END)";
        $price = 'COALESCE(NULLIF(p.unit_price, 0), pr.purchase_price, 0)';
        $start = $startDate ? $startDate.' 00:00:00' : null;
        $end = $endDate ? $endDate.' 23:59:59' : null;
        $inRange = match (true) {
            $start && $end => 'w.permission_date BETWEEN ? AND ?',
            (bool) $start => 'w.permission_date >= ?',
            (bool) $end => 'w.permission_date <= ?',
            default => '1 = 1',
        };
        $rangeBindings = array_values(array_filter([$start, $end]));
        $before = $start ? 'w.permission_date < ?' : '1 = 0';
        $beforeBindings = $start ? [$start] : [];

        $query = DB::table('warehouse_permits_products as p')
            ->join('warehouse_permits as w', 'w.id', '=', 'p.warehouse_permits_id')
            ->join('products as pr', 'pr.id', '=', 'p.product_id')
            ->whereIn('p.product_id', $productIds)
            ->whereIn('w.status', ['approved', 'apporod', 'delivered', 'done'])
            ->where('p.quantity', '!=', 0);

        return $query
            ->groupBy('p.product_id')
            ->selectRaw('p.product_id')
            ->selectRaw("SUM(CASE WHEN $before THEN $delta ELSE 0 END) as opening_qty", $beforeBindings)
            ->selectRaw("SUM(CASE WHEN $inRange AND $delta > 0 THEN $delta ELSE 0 END) as in_qty", $rangeBindings)
            ->selectRaw("SUM(CASE WHEN $inRange AND $delta > 0 THEN $delta * $price ELSE 0 END) as in_amount", $rangeBindings)
            ->selectRaw("SUM(CASE WHEN $inRange AND $delta < 0 THEN -$delta ELSE 0 END) as out_qty", $rangeBindings)
            ->selectRaw("SUM(CASE WHEN $inRange AND $delta < 0 THEN -$delta * $price ELSE 0 END) as out_amount", $rangeBindings)
            ->get()
            ->keyBy('product_id')
            ->all();
    }

    /**
     * تجميع بيانات ميزان المراجعة
     */
    private function groupTrialBalanceData(&$processedData, $productData, $groupBy)
    {
        $groupKey = '';

        switch ($groupBy) {
            case 'category':
                $groupKey = $productData['category'] ?? 'غير محدد';
                break;
            case 'warehouse':
                $groupKey = $productData['warehouse'] ?? 'غير محدد';
                break;
            case 'movement_type':
                // تصنيف حسب نوع الحركة (وارد أكثر، منصرف أكثر، متوازن)
                $incoming = $productData['incoming_quantity'];
                $outgoing = $productData['outgoing_quantity'];

                if ($incoming > $outgoing) {
                    $groupKey = 'منتجات وارد أكثر';
                } elseif ($outgoing > $incoming) {
                    $groupKey = 'منتجات منصرف أكثر';
                } else {
                    $groupKey = 'منتجات متوازنة';
                }
                break;
            default:
                $groupKey = 'عام';
        }

        if (! isset($processedData[$groupKey])) {
            $processedData[$groupKey] = [
                'group_name' => $groupKey,
                'items' => [],
                'total_products' => 0,
                'initial_quantity' => 0,
                'initial_amount' => 0,
                'incoming_quantity' => 0,
                'incoming_amount' => 0,
                'outgoing_quantity' => 0,
                'outgoing_amount' => 0,
                'net_quantity' => 0,
                'net_amount' => 0,
            ];
        }

        $processedData[$groupKey]['items'][] = $productData;
        $processedData[$groupKey]['total_products']++;
        $processedData[$groupKey]['initial_quantity'] += $productData['initial_quantity'];
        $processedData[$groupKey]['initial_amount'] += $productData['initial_amount'];
        $processedData[$groupKey]['incoming_quantity'] += $productData['incoming_quantity'];
        $processedData[$groupKey]['incoming_amount'] += $productData['incoming_amount'];
        $processedData[$groupKey]['outgoing_quantity'] += $productData['outgoing_quantity'];
        $processedData[$groupKey]['outgoing_amount'] += $productData['outgoing_amount'];
        $processedData[$groupKey]['net_quantity'] += $productData['net_quantity'];
        $processedData[$groupKey]['net_amount'] += $productData['net_amount'];
    }

    /**
     * حساب إجماليات ميزان المراجعة
     */
    private function calculateTrialBalanceTotals($products)
    {
        $totals = [
            'total_products' => 0,
            'total_quantity' => 0,
            'total_value' => 0,
            'total_movements' => 0,
            'initial_quantity' => 0,
            'initial_amount' => 0,
            'incoming_quantity' => 0,
            'incoming_amount' => 0,
            'outgoing_quantity' => 0,
            'outgoing_amount' => 0,
            'net_quantity' => 0,
            'net_amount' => 0,
        ];

        foreach ($products as $product) {
            if (isset($product['group_name'])) {
                // للبيانات المجمعة
                $totals['total_products'] += $product['total_products'];
                $totals['initial_quantity'] += $product['initial_quantity'];
                $totals['initial_amount'] += $product['initial_amount'];
                $totals['incoming_quantity'] += $product['incoming_quantity'];
                $totals['incoming_amount'] += $product['incoming_amount'];
                $totals['outgoing_quantity'] += $product['outgoing_quantity'];
                $totals['outgoing_amount'] += $product['outgoing_amount'];
                $totals['net_quantity'] += $product['net_quantity'];
                $totals['net_amount'] += $product['net_amount'];
                $totals['total_movements'] += ($product['incoming_quantity'] + $product['outgoing_quantity']);
            } else {
                // للبيانات غير المجمعة
                $totals['total_products']++;
                $totals['initial_quantity'] += $product['initial_quantity'];
                $totals['initial_amount'] += $product['initial_amount'];
                $totals['incoming_quantity'] += $product['incoming_quantity'];
                $totals['incoming_amount'] += $product['incoming_amount'];
                $totals['outgoing_quantity'] += $product['outgoing_quantity'];
                $totals['outgoing_amount'] += $product['outgoing_amount'];
                $totals['net_quantity'] += $product['net_quantity'];
                $totals['net_amount'] += $product['net_amount'];
                $totals['total_movements'] += ($product['incoming_quantity'] + $product['outgoing_quantity']);
            }
        }

        $totals['total_quantity'] = $totals['net_quantity'];
        $totals['total_value'] = $totals['net_amount'];

        return $totals;
    }

    /**
     * إعداد بيانات الرسم البياني لميزان المراجعة
     */
    private function prepareTrialBalanceChartData($products, Request $request)
    {
        $chartData = [
            'labels' => [],
            'initial_values' => [],
            'incoming_values' => [],
            'outgoing_values' => [],
            'final_values' => [],
        ];

        // تحديد عدد العناصر المراد عرضها في الرسم البياني (أعلى 10)
        $limit = 10;
        $chartProducts = [];

        foreach ($products as $product) {
            if (isset($product['group_name'])) {
                // للبيانات المجمعة
                $chartProducts[] = [
                    'name' => $product['group_name'],
                    'initial_value' => $product['initial_amount'],
                    'incoming_value' => $product['incoming_amount'],
                    'outgoing_value' => $product['outgoing_amount'],
                    'final_value' => $product['net_amount'],
                ];
            } else {
                // للبيانات غير المجمعة
                $chartProducts[] = [
                    'name' => $product['name'],
                    'initial_value' => $product['initial_amount'],
                    'incoming_value' => $product['incoming_amount'],
                    'outgoing_value' => $product['outgoing_amount'],
                    'final_value' => $product['net_amount'],
                ];
            }
        }

        // ترتيب حسب القيمة النهائية (تنازلي) وأخذ أعلى 10
        usort($chartProducts, function ($a, $b) {
            return abs($b['final_value']) <=> abs($a['final_value']);
        });

        $chartProducts = array_slice($chartProducts, 0, $limit);

        foreach ($chartProducts as $item) {
            $chartData['labels'][] = $item['name'];
            $chartData['initial_values'][] = $item['initial_value'];
            $chartData['incoming_values'][] = $item['incoming_value'];
            $chartData['outgoing_values'][] = $item['outgoing_value'];
            $chartData['final_values'][] = $item['final_value'];
        }

        return $chartData;
    }
}
