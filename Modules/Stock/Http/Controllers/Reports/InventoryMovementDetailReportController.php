<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Constants\permissions\PermissionSources;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\WarehousePermitsProducts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;

class InventoryMovementDetailReportController extends ReportController
{
    /*
        الفترة المحاسبية: النطاقُ الافتراضي للتقرير هو السنةُ المالية الجارية
        لا مدًى مكتوبٌ في الشيفرة.
    */
    use RespectsAccountingPeriods;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.movement_by_product');
    }

    public function index(Request $request)
    {
        // 1. إعداد الفلاتر — الأصناف المخزنية وحدها، لا رصيد للخدمات والرقميّات
        $products = Product::whereIn('type', ProductType::stockedValues())
            ->select('id', 'name', 'serial_number')->orderBy('name')->get();
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        $brands = Product::all();
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))
            ->select('id', 'name')->orderBy('name')->get();

        // 2. معالجة الفترة الزمنية
        $period = $request->input('period', '30');
        $dateRange = $this->getDateRange($period, $request);
        $startDate = $dateRange['from'];
        $endDate = $dateRange['to'];

        // 3. جلب البيانات المجمعة بحسب المنتج
        $reportData = $this->getProductMovementData($request, $startDate, $endDate);

        return view('stock::report.stock_report.Inventory_mov_det_product', compact(
            'products', 'categories', 'brands', 'warehouses', 'reportData', 'startDate', 'endDate', 'period'
        ));
    }

    /**
     * معالجة نطاق التاريخ
     */
    private function getDateRange($period, Request $request)
    {
        $today = now();

        /*
            الفترة المحاسبية تسبق كل شيء.

            قائمة المدى هنا كانت أعداد أيام — ٧، ٣٠، ٩٠ — لا صلة لها
            بالفترة التي تعمل المنشأة عليها. ووسيط `accounting.period`
            يكون قد حوّل `period_id` إلى تاريخين، فيكفي احترامُهما مهما
            كان المختار في قائمة الأيام.
        */
        /*
            **الفترة المحاسبية تسبق كل شيء — والنطاقُ الافتراضي منها.**

            كان الرجوعُ عند غياب التاريخين إلى «آخر N يوماً» (٧ أو ٣٠ أو ٩٠) —
            مدًى لا صلة له بالفترة التي تعمل المنشأة عليها، فيُقرأ تقريرُ
            الحركة على نافذةٍ لا توافق أيَّ إقفال.

            و`stockReportScope` هي القاعدةُ نفسُها في المالية: مدًى كتبه
            المستخدم يتقدّم، وإلّا فالسنةُ المالية الجارية، وإلّا — إن لم يكن
            ثمّة تقويم — فلا حصر.
        */
        $scope = $this->stockReportScope($request, 'start_date', 'end_date');

        if ($scope['mode'] !== 'all' && $scope['start'] && $scope['end']) {
            return [
                'from' => Carbon::parse($scope['start'])->startOfDay(),
                'to' => Carbon::parse($scope['end'])->endOfDay(),
            ];
        }

        /*
            لا تقويمَ معرَّفاً: يُعرض التاريخُ كلُّه بدل أن يُحصَر بمعيارٍ لا
            وجود له. والحدُّ الأدنى تاريخُ أوّل حركةٍ عملياً.
        */
        if ($scope['mode'] === 'all') {
            return [
                'from' => Carbon::parse('1970-01-01')->startOfDay(),
                'to' => $today->copy()->endOfDay(),
            ];
        }
        if ($period === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            return [
                'from' => Carbon::parse($request->start_date)->startOfDay(),
                'to' => Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        $periodDays = is_numeric($period) ? (int) $period : 30;
        if ($periodDays == 1) {
            return [
                'from' => $today->copy()->startOfDay(),
                'to' => $today->copy()->endOfDay(),
            ];
        }

        return [
            'from' => $today->copy()->subDays($periodDays)->startOfDay(),
            'to' => $today->copy()->endOfDay(),
        ];
    }

    /**
     * جلب بيانات الحركة مجمعة حسب المنتج
     */
    private function getProductMovementData(Request $request, $startDate, $endDate)
    {
        try {
            // 1. بناء الاستعلام الأساسي للحركة
            $query = WarehousePermitsProducts::query()
                ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->join('products', 'warehouse_permits_products.product_id', '=', 'products.id')
                ->leftJoin('store_houses', 'warehouse_permits.store_houses_id', '=', 'store_houses.id')
                ->leftJoin('store_houses as from_stores', 'warehouse_permits.from_store_houses_id', '=', 'from_stores.id')
                ->leftJoin('store_houses as to_stores', 'warehouse_permits.to_store_houses_id', '=', 'to_stores.id')
                ->leftJoin('users', 'warehouse_permits.created_by', '=', 'users.id')
                ->select(
                    'warehouse_permits_products.*',
                    'warehouse_permits.id as permit_id',
                    'warehouse_permits.number as permit_number',
                    'warehouse_permits.permission_date',
                    'warehouse_permits.permission_source_id',
                    'warehouse_permits.store_houses_id',
                    'warehouse_permits.from_store_houses_id',
                    'warehouse_permits.to_store_houses_id',
                    'products.id as product_id',
                    'products.name as product_name',
                    'products.serial_number as product_code',
                    'products.purchase_price as avg_cost',
                    'products.sale_price as selling_price',
                    'store_houses.name as store_name',
                    'from_stores.name as from_store_name',
                    'to_stores.name as to_store_name',
                    'users.name as user_name'
                )
                ->whereNotNull('warehouse_permits_products.quantity')
                ->where('warehouse_permits_products.quantity', '!=', 0)
                ->whereIn('warehouse_permits.status', ['approved', 'apporod']);

            // نطاق البيانات: منشئو الإذن ضمن ما يسمح به نطاق المستخدم
            $this->scopeToCreator($query, 'warehouse_permits.created_by');

            // تطبيق فلاتر البحث الأساسية
            $this->applyFilters($query, $request);

            // تطبيق فلتر التاريخ
            $query->whereBetween('warehouse_permits.permission_date', [$startDate, $endDate]);

            // ترتيب الحركات
            $query->orderBy('warehouse_permits.permission_date', 'asc')
                ->orderBy('warehouse_permits.created_at', 'asc');

            $allMovements = $query->get();

            // 2. تجميع الحركات حسب المنتج
            $groupedMovements = $allMovements->groupBy('product_id');

            // 3. حساب الأرصدة الافتتاحية لكل منتج موجود في النتائج
            $productIds = $groupedMovements->keys()->toArray();
            $openingBalances = $this->calculateOpeningBalances($productIds, $request, $startDate);

            // 4. معالجة كل مجموعة لحساب الرصيد المتتابع
            $finalData = [];

            // لإحصائيات التقرير ككل
            $globalTotals = [
                'total_products' => count($productIds),
                'total_in' => 0,
                'total_out' => 0,
                'current_balance' => 0,
            ];

            foreach ($groupedMovements as $productId => $movements) {
                // الحصول على بيانات المنتج من أول حركة
                $firstItem = $movements->first();
                $productName = $firstItem->product_name;
                $productCode = $firstItem->product_code;

                $startBalance = $openingBalances[$productId] ?? 0;
                $runningBalance = $startBalance;

                $processedRows = [];

                // إضافة صف الرصيد الافتتاحي
                $processedRows[] = [
                    'date' => null,
                    'type_html' => '<span class="badge badge-secondary">رصيد قبل الفترة</span>',
                    'warehouse_html' => '-',
                    'in' => 0,
                    'out' => 0,
                    'balance' => $startBalance,
                    'avg_cost' => 0,
                    'selling_price' => 0,
                    'user' => '-',
                    'is_balance' => true,
                ];

                $prodIn = 0;
                $prodOut = 0;

                foreach ($movements as $m) {
                    $isIncoming = $this->isIncoming($m, $request->warehouse);
                    $qty = abs($m->quantity);

                    if ($isIncoming) {
                        $runningBalance += $qty;
                        $inQty = $qty;
                        $outQty = 0;
                        $prodIn += $qty;
                    } else {
                        $runningBalance -= $qty;
                        $inQty = 0;
                        $outQty = $qty;
                        $prodOut += $qty;
                    }

                    // تجهيز العرض
                    $typeName = PermissionSources::getName($m->permission_source_id);
                    $typeClass = $isIncoming ? 'success' : 'danger';
                    $typeHtml = '<span class="badge badge-'.$typeClass.'">'.$typeName.'</span> <span class="badge badge-light border">#'.$m->permit_number.'</span>';

                    $warehouseHtml = '<span class="badge badge-light">'.($m->store_name ?? 'غير محدد').'</span>';
                    if ($m->permission_source_id == PermissionSources::MANUAL_TRANSFER && ! $request->warehouse) {
                        $warehouseHtml = '<span class="text-danger">'.($m->from_store_name ?? '').'</span> → <span class="text-success">'.($m->to_store_name ?? '').'</span>';
                    }

                    $processedRows[] = [
                        'date' => $m->permission_date,
                        'type_html' => $typeHtml,
                        'warehouse_html' => $warehouseHtml,
                        'in' => $inQty,
                        'out' => $outQty,
                        'balance' => $runningBalance,
                        'avg_cost' => $m->avg_cost ?? 0,
                        'selling_price' => $m->selling_price ?? 0,
                        'user' => $m->user_name,
                        'is_balance' => false,
                    ];
                }

                $globalTotals['total_in'] += $prodIn;
                $globalTotals['total_out'] += $prodOut;

                $finalData[] = [
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'product_code' => $productCode,
                    'movements' => $processedRows,
                    'total_in' => $prodIn,
                    'total_out' => $prodOut,
                    'start_balance' => $startBalance,
                    'current_balance' => $runningBalance,
                ];
            }

            $globalTotals['current_balance'] = $globalTotals['total_in'] - $globalTotals['total_out'];

            return [
                'data' => $finalData,
                'totals' => $globalTotals,
            ];

        } catch (\Exception $e) {
            Log::error('InventoryMovementDetailReport Error: '.$e->getMessage());

            return ['data' => [], 'totals' => ['total_products' => 0, 'total_in' => 0, 'total_out' => 0, 'current_balance' => 0]];
        }
    }

    private function calculateOpeningBalances($productIds, Request $request, $beforeDate)
    {
        // حساب الرصيد قبل التاريخ المحدد لكل المنتجات المطلوبة
        $query = WarehousePermitsProducts::query()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->whereIn('warehouse_permits_products.product_id', $productIds)
            ->where('warehouse_permits.permission_date', '<', $beforeDate)
            ->whereIn('warehouse_permits.status', ['approved', 'apporod'])
            ->whereNotNull('warehouse_permits_products.quantity')
            ->where('warehouse_permits_products.quantity', '!=', 0);

        // نطاق البيانات: الرصيد الافتتاحي من نفس الحركات المسموح رؤيتها
        $this->scopeToCreator($query, 'warehouse_permits.created_by');

        // تطبيق فلاتر المستودع فقط (لأننا نحسب رصيد المنتج المحدد)
        if ($request->filled('warehouse')) {
            $warehouseId = $request->warehouse;
            $query->where(function ($q) use ($warehouseId) {
                $q->where(function ($sub) use ($warehouseId) {
                    $sub->whereNotIn('warehouse_permits.permission_source_id', [PermissionSources::MANUAL_TRANSFER])
                        ->where('warehouse_permits.store_houses_id', $warehouseId);
                })
                    ->orWhere(function ($sub) use ($warehouseId) {
                        $sub->where('warehouse_permits.permission_source_id', PermissionSources::MANUAL_TRANSFER)
                            ->where('warehouse_permits.from_store_houses_id', $warehouseId)
                            ->where('warehouse_permits_products.quantity', '<', 0);
                    })
                    ->orWhere(function ($sub) use ($warehouseId) {
                        $sub->where('warehouse_permits.permission_source_id', PermissionSources::MANUAL_TRANSFER)
                            ->where('warehouse_permits.to_store_houses_id', $warehouseId)
                            ->where('warehouse_permits_products.quantity', '>', 0);
                    });
            });
        }

        $movements = $query->get();
        $balances = [];

        foreach ($movements as $m) {
            if (! isset($balances[$m->product_id])) {
                $balances[$m->product_id] = 0;
            }

            $isIncoming = $this->isIncoming($m, $request->warehouse);
            if ($isIncoming) {
                $balances[$m->product_id] += abs($m->quantity);
            } else {
                $balances[$m->product_id] -= abs($m->quantity);
            }
        }

        return $balances;
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('product')) {
            $query->where('warehouse_permits_products.product_id', $request->product);
        }
        if ($request->filled('category')) {
            $query->where('products.category_id', $request->category);
        }
        if ($request->filled('brand')) {
            $query->where('products.brand', $request->brand);
        }

        if ($request->filled('warehouse')) {
            $warehouseId = $request->warehouse;
            $query->where(function ($q) use ($warehouseId) {
                $q->where('warehouse_permits.store_houses_id', $warehouseId)
                    ->orWhere('warehouse_permits.from_store_houses_id', $warehouseId)
                    ->orWhere('warehouse_permits.to_store_houses_id', $warehouseId);
            });
        }
    }

    private function isIncoming($movement, $warehouseId = null)
    {
        $sourceId = $movement->permission_source_id;
        if ($sourceId == PermissionSources::MANUAL_TRANSFER) {
            if ($warehouseId) {
                return $movement->to_store_houses_id == $warehouseId;
            }

            return $movement->quantity > 0;
        }

        $incomingTypes = [
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
        ];

        return in_array($sourceId, $incomingTypes);
    }

    /**
     * إعداد بيانات الرسم البياني
     */
    private function prepareChartData($movements)
    {
        if (empty($movements)) {
            return null;
        }

        // تجميع حسب المستودع
        $warehouseData = [];
        foreach ($movements as $movement) {
            $warehouseName = $movement['warehouse_name'];
            if (! isset($warehouseData[$warehouseName])) {
                $warehouseData[$warehouseName] = [
                    'incoming' => 0,
                    'outgoing' => 0,
                ];
            }

            if ($movement['direction'] === 'in') {
                $warehouseData[$warehouseName]['incoming'] += abs($movement['quantity']);
            } else {
                $warehouseData[$warehouseName]['outgoing'] += abs($movement['quantity']);
            }
        }

        // ترتيب حسب النشاط وأخذ أعلى 10
        uasort($warehouseData, function ($a, $b) {
            return ($b['incoming'] + $b['outgoing']) - ($a['incoming'] + $a['outgoing']);
        });
        $warehouseData = array_slice($warehouseData, 0, 10, true);

        return [
            'labels' => array_keys($warehouseData),
            'datasets' => [
                [
                    'label' => 'الوارد',
                    'data' => array_column($warehouseData, 'incoming'),
                    'backgroundColor' => 'rgba(40, 167, 69, 0.7)',
                    'borderColor' => 'rgba(40, 167, 69, 1)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'المنصرف',
                    'data' => array_column($warehouseData, 'outgoing'),
                    'backgroundColor' => 'rgba(220, 53, 69, 0.7)',
                    'borderColor' => 'rgba(220, 53, 69, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }

    public function searchProducts(Request $request)
    {
        $term = $request->input('q');
        $page = $request->input('page', 1);
        $perPage = 20;

        $products = Product::whereIn('type', ProductType::stockedValues())
            ->select('id', 'name', 'serial_number')
            ->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('serial_number', 'LIKE', "%{$term}%")
                    ->orWhere('id', 'LIKE', "%{$term}%");
            })
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $results = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'text' => $product->name.($product->serial_number ? " ({$product->serial_number})" : ''),
            ];
        });

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => $products->hasMorePages()],
        ]);
    }
}
