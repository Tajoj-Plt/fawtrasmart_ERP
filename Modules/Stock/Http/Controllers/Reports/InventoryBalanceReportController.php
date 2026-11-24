<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store\Brand;
use App\Models\StoreHouse;
use App\Support\OperationLog;
use Illuminate\Http\Request;

class InventoryBalanceReportController extends ReportController
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.balance');
    }

    public function index(Request $request)
    {
        // جلب البيانات الأساسية للفلاتر
        $products = Product::all();
        $categories = Category::all();
        $brands = Brand::whereHas('products')->pluck('name', 'id');
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        // بناء الاستعلام الأساسي — الأصناف المخزنية وحدها، لا رصيد للخدمات والرقميّات
        $query = Product::query()
            ->whereIn('type', ProductType::stockedValues())
            ->with(['category', 'product_details.storeHouse', 'warehousePermitsProducts'])
            ->select('products.*');

        // تطبيق الفلاتر
        $this->applyBalanceFilters($query, $request);

        // تطبيق الترتيب
        $this->applyBalanceSorting($query, $request);

        // جلب البيانات
        $products = $query->get();

        // معالجة البيانات للعرض
        $processedProducts = $this->processBalanceData($products, $request);

        // حساب الإجماليات
        $totals = $this->calculateBalanceTotals($processedProducts);

        return view('stock::report.stock_report.inventory_blance',
            compact('categories', 'brands', 'warehouses', 'processedProducts', 'totals'));
    }

    /**
     * جلب بيانات ملخص رصيد المخزون عبر AJAX
     */
    public function inventoryBlanceAjax(Request $request)
    {
        try {
            // بناء الاستعلام الأساسي — الأصناف المخزنية وحدها، لا رصيد للخدمات والرقميّات
            $query = Product::query()
                ->whereIn('type', ProductType::stockedValues())
                ->with(['category', 'product_details.storeHouse'])
                ->select('products.*');

            // تطبيق الفلاتر
            $this->applyBalanceFilters($query, $request);

            // تطبيق الترتيب
            $this->applyBalanceSorting($query, $request);

            // جلب البيانات
            $products = $query->get();

            // معالجة البيانات للعرض
            $processedProducts = $this->processBalanceData($products, $request);

            // حساب الإجماليات
            $totals = $this->calculateBalanceTotals($processedProducts);

            // إعداد بيانات الرسم البياني
            $chartData = $this->prepareBalanceChartData($processedProducts, $request);

            return response()->json([
                'success' => true,
                'products' => $processedProducts,
                'totals' => $totals,
                'chart_data' => $chartData,
                'group_by' => $this->getGroupByLabel($request->group_by),
                'message' => 'تم تحميل البيانات بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'inventoryBlanceAjax');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تطبيق فلاتر ملخص رصيد المخزون
     */
    private function applyBalanceFilters($query, Request $request)
    {
        // فلتر المنتج
        if ($request->filled('product')) {
            $query->where('id', $request->product);
        }

        // فلتر التصنيف
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // فلتر العلامة التجارية
        if ($request->filled('brand')) {
            $query->where('brand_id', $request->brand);
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

        // فلتر الحالة
        if ($request->filled('status')) {
            switch ($request->status) {
                case '1': // متاح
                    $query->whereHas('product_details', function ($q) {
                        $q->where('quantity', '>', 10);
                    });
                    break;
                case '2': // مخزون منخفض
                    $query->whereHas('product_details', function ($q) {
                        $q->where('quantity', '>', 0)->where('quantity', '<=', 10);
                    });
                    break;
                case '3': // مخزون نفد
                    $query->whereHas('product_details', function ($q) {
                        $q->where('quantity', '<=', 0);
                    });
                    break;
                case '4': // غير نشط
                    $query->where('status', 'غير نشط');
                    break;
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

        // إخفاء الرصيد الصفري
        if ($request->boolean('hide_zero_balance')) {
            $query->whereHas('product_details', function ($q) {
                $q->where('quantity', '>', 0);
            });
        }
    }

    /**
     * تطبيق الترتيب لملخص رصيد المخزون
     */
    private function applyBalanceSorting($query, Request $request)
    {
        switch ($request->sort_by) {
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
                    SELECT COALESCE(SUM(quantity), 0) * COALESCE(sale_price, 0)
                    FROM product_details
                    WHERE product_details.product_id = products.id
                ) DESC');
                break;
            case 'value_asc':
                $query->orderByRaw('(
                    SELECT COALESCE(SUM(quantity), 0) * COALESCE(sale_price, 0)
                    FROM product_details
                    WHERE product_details.product_id = products.id
                ) ASC');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
        }
    }

    /**
     * معالجة بيانات ملخص رصيد المخزون للعرض
     */
    private function processBalanceData($products, Request $request)
    {
        $processedData = [];

        foreach ($products as $product) {
            // حساب الكمية الإجمالية
            $totalQuantity = $product->product_details->sum('quantity');

            // تطبيق فلتر إخفاء الرصيد الصفري
            if ($request->boolean('hide_zero_balance') && $totalQuantity <= 0) {
                continue;
            }

            // حساب الأسعار
            $salePrice = $product->sale_price ?? 0;
            $averageCost = $product->averageCost() ?? 0;

            // حساب القيمة الإجمالية - استخدام متوسط السعر
            $totalValue = $totalQuantity * $averageCost;

            // تحديد حالة المنتج
            $status = $this->determineProductStatus($totalQuantity, $product);

            // إعداد بيانات المنتج
            $productData = [
                'id' => $product->id,
                'code' => $product->serial_number ?? $product->id,
                'name' => $product->name,
                'category' => $product->category ? $product->category->name : null,
                'brand' => $product->brand?->name ?? 'غير محدد',
                'warehouse' => $this->getWarehouseNames($product),
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue,
                'status' => $status,
                'sale_price' => $salePrice,
                'average_cost' => $averageCost,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];

            // تطبيق التجميع إذا كان مطلوباً
            if ($request->filled('group_by')) {
                $this->groupBalanceData($processedData, $productData, $request->group_by);
            } else {
                $processedData[] = $productData;
            }
        }

        return $processedData;
    }

    /**
     * تحديد حالة المنتج
     */
    private function determineProductStatus($quantity, $product)
    {
        // التحقق من الحالة المحفوظة في قاعدة البيانات أولاً
        if (isset($product->status)) {
            return $product->status;
        }

        // تحديد الحالة بناءً على الكمية
        if ($quantity > 10) {
            return 'متاح';
        } elseif ($quantity > 0) {
            return 'مخزون منخفض';
        } else {
            return 'مخزون نفد';
        }
    }

    /**
     * تجميع بيانات ملخص رصيد المخزون
     */
    private function groupBalanceData(&$processedData, $productData, $groupBy)
    {
        $groupKey = '';

        switch ($groupBy) {
            case 'category':
                $groupKey = $productData['category'] ?? 'غير محدد';
                break;
            case 'brand':
                $groupKey = $productData['brand'] ?? 'غير محدد';
                break;
            case 'warehouse':
                $groupKey = $productData['warehouse'] ?? 'غير محدد';
                break;
            case 'status':
                $groupKey = $productData['status'];
                break;
            default:
                $groupKey = 'عام';
        }

        if (! isset($processedData[$groupKey])) {
            $processedData[$groupKey] = [
                'group_name' => $groupKey,
                'items' => [],
                'total_products' => 0,
                'total_quantity' => 0,
                'total_value' => 0,
                'available_count' => 0,
                'low_stock_count' => 0,
                'out_of_stock_count' => 0,
                'inactive_count' => 0,
            ];
        }

        $processedData[$groupKey]['items'][] = $productData;
        $processedData[$groupKey]['total_products']++;
        $processedData[$groupKey]['total_quantity'] += $productData['total_quantity'];
        $processedData[$groupKey]['total_value'] += $productData['total_value'];

        // تحديث عدادات الحالة
        switch ($productData['status']) {
            case 'متاح':
                $processedData[$groupKey]['available_count']++;
                break;
            case 'مخزون منخفض':
                $processedData[$groupKey]['low_stock_count']++;
                break;
            case 'مخزون نفد':
                $processedData[$groupKey]['out_of_stock_count']++;
                break;
            case 'غير نشط':
                $processedData[$groupKey]['inactive_count']++;
                break;
        }
    }

    /**
     * حساب إجماليات ملخص رصيد المخزون
     */
    private function calculateBalanceTotals($products)
    {
        $totals = [
            'total_products' => 0,
            'total_quantity' => 0,
            'total_value' => 0,
            'available_count' => 0,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'inactive_count' => 0,
        ];

        foreach ($products as $product) {
            if (isset($product['group_name'])) {
                // للبيانات المجمعة
                $totals['total_products'] += $product['total_products'];
                $totals['total_quantity'] += $product['total_quantity'];
                $totals['total_value'] += $product['total_value'];
                $totals['available_count'] += $product['available_count'];
                $totals['low_stock_count'] += $product['low_stock_count'];
                $totals['out_of_stock_count'] += $product['out_of_stock_count'];
                $totals['inactive_count'] += $product['inactive_count'];
            } else {
                // للبيانات غير المجمعة
                $totals['total_products']++;
                $totals['total_quantity'] += $product['total_quantity'];
                $totals['total_value'] += $product['total_value'];

                switch ($product['status']) {
                    case 'متاح':
                        $totals['available_count']++;
                        break;
                    case 'مخزون منخفض':
                        $totals['low_stock_count']++;
                        break;
                    case 'مخزون نفد':
                        $totals['out_of_stock_count']++;
                        break;
                    case 'غير نشط':
                        $totals['inactive_count']++;
                        break;
                }
            }
        }

        return $totals;
    }

    /**
     * إعداد بيانات الرسم البياني لملخص رصيد المخزون
     */
    private function prepareBalanceChartData($products, Request $request)
    {
        $chartData = [
            'available' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'inactive' => 0,
        ];

        foreach ($products as $product) {
            if (isset($product['group_name'])) {
                // للبيانات المجمعة
                $chartData['available'] += $product['available_count'];
                $chartData['low_stock'] += $product['low_stock_count'];
                $chartData['out_of_stock'] += $product['out_of_stock_count'];
                $chartData['inactive'] += $product['inactive_count'];
            } else {
                // للبيانات غير المجمعة
                switch ($product['status']) {
                    case 'متاح':
                        $chartData['available']++;
                        break;
                    case 'مخزون منخفض':
                        $chartData['low_stock']++;
                        break;
                    case 'مخزون نفد':
                        $chartData['out_of_stock']++;
                        break;
                    case 'غير نشط':
                        $chartData['inactive']++;
                        break;
                }
            }
        }

        return $chartData;
    }

    public function getQuickBalanceReport()
    {
        try {
            $products = Product::with(['product_details'])->get();

            $summary = [
                'total_products' => 0,
                'available' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
                'total_value' => 0,
            ];

            foreach ($products as $product) {
                $quantity = $product->product_details->sum('quantity');
                $value = $quantity * ($product->sale_price ?? 0);

                $summary['total_products']++;
                $summary['total_value'] += $value;

                if ($quantity > 10) {
                    $summary['available']++;
                } elseif ($quantity > 0) {
                    $summary['low_stock']++;
                } else {
                    $summary['out_of_stock']++;
                }
            }

            return response()->json([
                'success' => true,
                'summary' => $summary,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'getQuickBalanceReport');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب التقرير السريع: '.$e->getMessage(),
            ], 500);
        }
    }
}
