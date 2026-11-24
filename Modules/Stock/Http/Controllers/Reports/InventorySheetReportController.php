<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Store\Brand;
use App\Models\StoreHouse;
use App\Support\AccountingPeriodFilter;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;

class InventorySheetReportController extends ReportController
{
    /*
        الفترة المحاسبية: النطاقُ الافتراضي للتقرير هو السنةُ المالية الجارية
        لا مدًى مكتوبٌ في الشيفرة.
    */
    use RespectsAccountingPeriods;

    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.inventory_sheet');
    }

    public function indexReport(Request $request)
    {
        return view('stock::report.index');
    }

    public function index()
    {
        $categories = Category::all();
        $brands = Brand::whereHas('products')->pluck('name', 'id');
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        return view('stock::report.stock_report.inventory_sheet', compact('categories', 'brands', 'warehouses'));
    }

    public function inventorySheetAjax(Request $request)
    {
        try {
            Log::info('========== بدء تحميل بيانات الجرد من InventoryItem ==========');
            Log::info('Parameters: '.json_encode($request->all()));

            // ✅ ابدأ من InventoryItem بدلاً من Product
            $query = InventoryItem::query()
                ->with(['product.category', 'product.productDetails.storeHouse', 'adjustment'])
                ->select('inventory_items.*');

            // نطاق البيانات: ورقة الجرد التي أنشأتها (أو أنشأها من أديرهم)، ومستودعها ضمن نطاقي
            $period = $this->stockPeriod($request);

            $query->whereHas('adjustment', function ($q) use ($period) {
                $this->scopeToCreator($q, 'created_by');
                $this->scopeToWarehouse($q, 'stock_id');

                /*
                    بنودُ الجرد تتبع ورقتَها، والورقةُ مختومةٌ بفترتها — فالحصرُ
                    عبر العلاقة. وكان الكشفُ يجمع أوراقَ الجرد كلَّها، فتُقارَن
                    تسويةُ هذه السنة بتسويةٍ من سنةٍ مقفلة.
                */
                AccountingPeriodFilter::applyRaw($q, $period, 'inventory_adjustments.accounting_period_id');
            });

            $countBefore = $query->count();
            Log::info('عدد عناصر الجرد قبل الفلاتر: '.$countBefore);

            $this->applyInventoryFilters($query, $request);

            $inventoryItems = $query->get();
            Log::info('عدد عناصر الجرد بعد الفلاتر: '.count($inventoryItems));

            // ✅ جمع جميع المستودعات الفريدة
            $allWarehouses = $this->collectAllWarehouses($inventoryItems);
            Log::info('المستودعات المكتشفة: '.json_encode(array_keys($allWarehouses)));

            $processedProducts = $this->processInventoryData($inventoryItems, $request);
            Log::info('عدد المنتجات المعالجة: '.count($processedProducts));

            $totals = $this->calculateInventoryTotals($processedProducts);
            $chartData = $this->prepareChartData($processedProducts, $request);

            $productsArray = array_values($processedProducts);
            Log::info('عدد المجموعات النهائي: '.count($productsArray));

            $response = [
                'success' => true,
                'products' => $productsArray,
                'totals' => $totals,
                'chart_data' => $chartData,
                'warehouses' => $allWarehouses,  // ✅ إرسال قائمة المستودعات
                'group_by' => $this->getGroupByLabel($request->group_by),
                'message' => 'تم تحميل البيانات بنجاح',
            ];

            Log::info('========== نجح تحميل البيانات ==========');

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('========== خطأ في تحميل البيانات ==========');
            Log::error('الخطأ: '.$e->getMessage());
            Log::error('الملف: '.$e->getFile().' السطر: '.$e->getLine());
            Log::error('التفاصيل: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    private function collectAllWarehouses($inventoryItems)
    {
        $warehouses = [];
        foreach ($inventoryItems as $inventoryItem) {
            if (! $inventoryItem->product || ! $inventoryItem->product->productDetails) {
                continue;
            }
            foreach ($inventoryItem->product->productDetails as $detail) {
                if ($detail->storeHouse && ! isset($warehouses[$detail->storeHouse->name])) {
                    $warehouses[$detail->storeHouse->name] = $detail->storeHouse->id;
                }
            }
        }

        return $warehouses;
    }

    private function applyInventoryFilters($query, Request $request)
    {
        if ($request->filled('category')) {
            $query->whereHas('product.category', function ($q) use ($request) {
                $q->where('id', $request->category);
            });
        }

        if ($request->filled('brand')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('brand', $request->brand);
            });
        }

        if ($request->filled('warehouse')) {
            // فلتر حسب المستودع من productDetails
            $query->whereHas('product', function ($q) use ($request) {
                $q->whereHas('productDetails', function ($subQ) use ($request) {
                    $subQ->where('store_house_id', $request->warehouse);
                });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where(function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        if ($request->boolean('hide_zero')) {
            $query->where('quantity_in_system', '>', 0);
        }
    }

    private function processInventoryData($inventoryItems, Request $request)
    {
        $processedData = [];

        foreach ($inventoryItems as $inventoryItem) {
            // ✅ الحصول على المنتج والعلاقات المرتبطة
            if (! $inventoryItem->product) {
                continue; // تخطي إذا لم يكن هناك منتج مرتبط
            }

            $product = $inventoryItem->product;

            // ✅ الكميات من InventoryItem مباشرة - بلا تعديل
            $quantitySystem = $inventoryItem->quantity_in_system ?? 0;
            $quantityActual = $inventoryItem->quantity_in_stock ?? 0;
            $quantityAfter = $inventoryItem->quantity_difference ?? 0;

            // ✅ سعر الشراء = متوسط التكلفة من دالة averageCost()
            $unitPrice = $product->averageCost() ?? $product->purchase_price ?? 0;

            // ✅ حالة الجرد من InventoryAdjustment
            $adjustment = $inventoryItem->adjustment;
            $adjustmentStatus = $adjustment ? ($adjustment->status ?? 'غير محدد') : 'غير محدد';
            $isCompleted = ($adjustmentStatus === 'completed' || $adjustmentStatus === 'مكتمل');

            // ✅ جلب الكميات لكل مستودع بشكل منفصل
            $warehouseQuantities = [];
            $warehouseNames = [];
            if ($product->productDetails && count($product->productDetails) > 0) {
                foreach ($product->productDetails as $detail) {
                    if ($detail->storeHouse) {
                        $warehouseName = $detail->storeHouse->name;
                        $warehouseNames[] = $warehouseName;
                        $warehouseQuantities[$warehouseName] = $detail->quantity ?? 0;
                    }
                }
            }

            $stockValue = $quantitySystem * $unitPrice;
            $lowStockThreshold = $product->low_stock_thershold ?? 10;

            $productData = [
                'id' => $product->id,
                'code' => $product->code ?? $product->id,
                'name' => $product->name,
                'category' => $product->category ? $product->category->name : 'غير محدد',
                'brand' => $product->brand ?? 'غير محدد',
                'supplier' => $product->supplier ? $product->supplier->name : 'غير محدد',
                'warehouse' => implode(', ', array_unique($warehouseNames)) ?: 'غير محدد',
                'warehouse_quantities' => $warehouseQuantities,  // ✅ كميات منفصلة لكل مستودع
                'quantity_in_system' => $quantitySystem, // ✅ من InventoryItem مباشرة
                'quantity_actual' => $quantityActual, // ✅ من InventoryItem مباشرة
                'quantity_after' => $quantityAfter, // ✅ الكمية بعد من InventoryItem
                'unit_price' => $unitPrice, // ✅ متوسط التكلفة من averageCost()
                'stock_value' => $stockValue,
                'sale_price' => $product->sale_price ?? 0,
                'profit_margin' => $product->profit_margin ?? 0,
                'notes' => $inventoryItem->note ?? $product->Internal_notes ?? '',
                'status' => $product->status ?? 'نشط',
                'adjustment_status' => $adjustmentStatus, // ✅ حالة الجرد
                'is_completed' => $isCompleted, // ✅ هل مكتمل؟
                'low_stock_threshold' => $lowStockThreshold,
                'is_low_stock' => $quantitySystem < $lowStockThreshold,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'inventory_item_id' => $inventoryItem->id,
                'adjustment_id' => $inventoryItem->adjustment_id,
            ];

            if ($request->filled('group_by')) {
                $this->groupProductData($processedData, $productData, $request->group_by);
            } else {
                // بدون تجميع - عرض السجلات كما هي
                $processedData[$inventoryItem->id] = $productData;
            }
        }

        return $processedData;
    }

    private function groupProductData(&$processedData, $productData, $groupBy)
    {
        $groupKey = '';

        switch ($groupBy) {
            case 'category':
                $groupKey = $productData['category'] ?? 'غير محدد';
                break;
            case 'brand':
                $groupKey = $productData['brand'] ?? 'غير محدد';
                break;
            default:
                $groupKey = 'عام';
        }

        if (! isset($processedData[$groupKey])) {
            $processedData[$groupKey] = [
                'group_name' => $groupKey,
                'items' => [],
                'total_quantity' => 0,
                'total_actual' => 0,
                'total_products' => 0,
                'total_stock_value' => 0,
                'low_stock_count' => 0,
            ];
        }

        $processedData[$groupKey]['items'][] = $productData;
        $processedData[$groupKey]['total_quantity'] += $productData['quantity_in_system'];
        $processedData[$groupKey]['total_actual'] += $productData['quantity_actual'];
        $processedData[$groupKey]['total_products']++;
        $processedData[$groupKey]['total_stock_value'] += $productData['stock_value'];
        if ($productData['is_low_stock']) {
            $processedData[$groupKey]['low_stock_count']++;
        }
    }

    private function calculateInventoryTotals($products)
    {
        $totals = [
            'total_products' => 0,
            'total_quantity' => 0,
            'total_actual' => 0,
            'total_categories' => 0,
            'total_stock_value' => 0,
        ];

        $categories = [];
        $countedProducts = [];

        foreach ($products as $product) {
            if (isset($product['group_name'])) {
                // grouped data
                $totals['total_quantity'] += $product['total_quantity'];
                $totals['total_actual'] += $product['total_actual'];
                $totals['total_stock_value'] += $product['total_stock_value'];

                foreach ($product['items'] as $item) {
                    $productId = $item['id'];
                    if (! isset($countedProducts[$productId])) {
                        $countedProducts[$productId] = true;
                        $totals['total_products']++;
                    }

                    if ($item['category']) {
                        $categories[$item['category']] = true;
                    }
                }
            } else {
                // single product
                $productId = $product['id'];
                if (! isset($countedProducts[$productId])) {
                    $countedProducts[$productId] = true;
                    $totals['total_products']++;
                }
                $totals['total_quantity'] += $product['quantity_in_system'];
                $totals['total_actual'] += $product['quantity_actual'];
                $totals['total_stock_value'] += $product['stock_value'];

                if ($product['category']) {
                    $categories[$product['category']] = true;
                }
            }
        }

        $totals['total_categories'] = count($categories);

        return $totals;
    }

    private function prepareChartData($products, Request $request)
    {
        // استخراج جميع المستودعات الفريدة من كل منتج
        $allWarehouses = [];
        $productWarehouses = []; // منتج => مستودع => كمية

        foreach ($products as $product) {
            if (isset($product['group_name'])) {
                continue; // تخطي البيانات المجمعة للحصول على البيانات الخام فقط
            }

            // استخراج المستودعات من warehouse_quantities
            if (isset($product['warehouse_quantities']) && is_array($product['warehouse_quantities'])) {
                foreach ($product['warehouse_quantities'] as $warehouseName => $quantity) {
                    if (! in_array($warehouseName, $allWarehouses)) {
                        $allWarehouses[] = $warehouseName;
                    }

                    if (! isset($productWarehouses[$product['name']])) {
                        $productWarehouses[$product['name']] = [];
                    }
                    $productWarehouses[$product['name']][$warehouseName] = $quantity;
                }
            }
        }

        // الآن بنية البيانات:
        // المستودعات في labels
        // كل منتج يصير dataset
        $chartData = [
            'labels' => $allWarehouses,  // أسماء المستودعات
            'datasets' => [],  // كل منتج يصير dataset
        ];

        // الألوان المختلفة للمنتجات
        $colors = [
            'rgba(66, 179, 245, 0.8)',
            'rgba(75, 192, 75, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(201, 203, 207, 0.8)',
            'rgba(255, 205, 86, 0.8)',
        ];

        $colorIndex = 0;
        $limit = 10;
        $count = 0;

        // إنشاء dataset لكل منتج
        foreach ($productWarehouses as $productName => $warehouseQties) {
            if ($count >= $limit) {
                break;
            }

            $quantities = [];
            foreach ($allWarehouses as $warehouse) {
                $quantities[] = $warehouseQties[$warehouse] ?? 0;
            }

            $color = $colors[$colorIndex % count($colors)];
            $borderColor = str_replace('0.8', '1', $color);

            $chartData['datasets'][] = [
                'label' => $productName,
                'data' => $quantities,
                'backgroundColor' => $color,
                'borderColor' => $borderColor,
                'borderWidth' => 2,
                'borderRadius' => 5,
                'hoverBackgroundColor' => str_replace('0.8', '1', $color),
            ];

            $colorIndex++;
            $count++;
        }

        return $chartData;
    }

    public function updateActualQuantity(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'actual_quantity' => 'required|numeric|min:0',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الكمية الفعلية بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'updateActualQuantity');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث الكمية: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateNotes(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $product = Product::findOrFail($request->product_id);
            $product->Internal_notes = $request->notes;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الملاحظات بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'updateNotes');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث الملاحظات: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function getGroupByLabel($groupBy)
    {
        $labels = [
            'category' => 'التصنيف',
            'brand' => 'العلامة التجارية',
            'warehouse' => 'المستودع',
        ];

        return $labels[$groupBy] ?? null;
    }
}
