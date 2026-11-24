<?php

namespace Modules\Stock\Http\Controllers\Reports;

use App\Constants\permissions\PermissionSources;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\StoreHouse;
use App\Models\WarehousePermitsProducts;
use App\Support\OperationLog;
use Illuminate\Http\Request;

class DamagedStockReportController extends ReportController
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission.role:stock.reports.damaged');
    }

    /**
     * عرض تقرير المخزون التالف والسليم
     */
    public function index(Request $request)
    {
        $warehouseIds = $this->allowedWarehouseIds();
        $warehouses = StoreHouse::when($warehouseIds !== null, fn ($q) => $q->whereIn('id', $warehouseIds ?: [0]))->get();

        $warehouseId = $request->input('warehouse');
        if (! $warehouseId && $warehouses->count() > 0) {
            $warehouseId = $warehouses->first()->id;
        }

        $categories = Category::all();
        // الأصناف المخزنية وحدها — الخدمات والرقميّات لا رصيد لها فلا تظهر في تقرير التالف
        $products = Product::whereIn('type', ProductType::stockedValues())
            ->select('id', 'name', 'serial_number')->get();

        $warehouse = $warehouseId ? StoreHouse::find($warehouseId) : null;
        $report = $warehouseId ? $this->generateReport($request) : null;

        return view('stock::report.stock_report.damaged_stock_report', compact(
            'warehouses',
            'categories',
            'products',
            'warehouse',
            'report'
        ));
    }

    /**
     * جلب بيانات التقرير عبر AJAX
     */
    public function getData(Request $request)
    {
        try {
            $report = $this->generateReport($request);

            return response()->json([
                'success' => true,
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'StockReports', 'getData');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء التقرير
     */
    private function generateReport(Request $request)
    {
        $warehouseId = $request->input('warehouse');
        $productId = $request->input('product');
        $categoryId = $request->input('category');

        if (! $warehouseId) {
            return null;
        }

        $this->authorizeWarehouseScope((int) $warehouseId);

        $warehouse = StoreHouse::find($warehouseId);

        // جلب جميع المنتجات في المستودع مع أرصدتها
        $query = ProductDetails::query()
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('product_details.store_house_id', $warehouseId)
            ->where('product_details.quantity', '>', 0)
            ->select(
                'product_details.product_id',
                'product_details.quantity as total_quantity',
                'products.name as product_name',
                'products.serial_number as product_code',
                'products.purchase_price',
                'categories.name as category_name'
            );

        if ($productId) {
            $query->where('product_details.product_id', $productId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        $productsData = $query->get();

        // حساب الكميات التالفة لكل منتج (من أذونات ارجاع تبديل تالف)
        $damagedQuantities = $this->calculateDamagedQuantities($warehouseId, $productId, $categoryId);

        // بناء التقرير
        $reportItems = [];
        $totalAll = 0;
        $totalDamaged = 0;
        $totalGood = 0;
        $totalDamagedValue = 0;
        $totalGoodValue = 0;

        foreach ($productsData as $product) {
            $damaged = $damagedQuantities[$product->product_id] ?? 0;
            $good = max(0, $product->total_quantity - $damaged);
            $purchasePrice = $product->purchase_price ?? 0;

            $reportItems[] = [
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'category_name' => $product->category_name,
                'total_quantity' => $product->total_quantity,
                'damaged_quantity' => $damaged,
                'good_quantity' => $good,
                'purchase_price' => $purchasePrice,
                'damaged_value' => $damaged * $purchasePrice,
                'good_value' => $good * $purchasePrice,
            ];

            $totalAll += $product->total_quantity;
            $totalDamaged += $damaged;
            $totalGood += $good;
            $totalDamagedValue += $damaged * $purchasePrice;
            $totalGoodValue += $good * $purchasePrice;
        }

        return [
            'warehouse' => $warehouse,
            'items' => $reportItems,
            'summary' => [
                'total_quantity' => $totalAll,
                'total_damaged' => $totalDamaged,
                'total_good' => $totalGood,
                'total_damaged_value' => $totalDamagedValue,
                'total_good_value' => $totalGoodValue,
                'damaged_percentage' => $totalAll > 0 ? round(($totalDamaged / $totalAll) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * حساب الكميات التالفة من أذونات ارجاع تبديل تالف
     * المنطق: التالف يُحسب فقط من بعد آخر مرة وصل فيها المخزون لصفر
     * لأن البضاعة الجديدة التي تدخل بعد أن يصبح المخزون صفر تكون سليمة 100%
     */
    private function calculateDamagedQuantities($warehouseId, $productId = null, $categoryId = null)
    {
        // أولاً: نجلب قائمة المنتجات المطلوبة
        $productsQuery = ProductDetails::query()
            ->join('products', 'product_details.product_id', '=', 'products.id')
            ->where('product_details.store_house_id', $warehouseId)
            ->where('product_details.quantity', '>', 0);

        if ($productId) {
            $productsQuery->where('product_details.product_id', $productId);
        }

        if ($categoryId) {
            $productsQuery->where('products.category_id', $categoryId);
        }

        $productIds = $productsQuery->pluck('product_details.product_id')->toArray();

        $netDamaged = [];

        foreach ($productIds as $prodId) {
            // جلب الرصيد الحالي للمنتج
            $currentQuantity = ProductDetails::where('store_house_id', $warehouseId)
                ->where('product_id', $prodId)
                ->value('quantity') ?? 0;

            // إذا المخزون صفر، لا يوجد تالف
            if ($currentQuantity <= 0) {
                $netDamaged[$prodId] = 0;

                continue;
            }

            // نجد آخر إذن وصل فيه المخزون لصفر
            $lastZeroPermitId = $this->findLastZeroStockPermitId($warehouseId, $prodId);

            // حساب التالف فقط من بعد آخر إذن كان المخزون صفر
            $damagedQuery = WarehousePermitsProducts::query()
                ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->where('warehouse_permits.store_houses_id', $warehouseId)
                ->where('warehouse_permits_products.product_id', $prodId)
                ->whereIn('warehouse_permits.permission_source_id', [
                    PermissionSources::PRODUCT_EXCHANGE_RETURN,
                    PermissionSources::SALES_RETURN,
                    PermissionSources::MANUFACTURING_RETURNS,
                ]);

            // إذا وجدنا إذن كان المخزون صفر، نحسب التالف فقط من بعده
            if ($lastZeroPermitId) {
                //     $damagedQuery->where('warehouse_permits.id', '>', $lastZeroPermitId);
            }

            $damagedQty = $damagedQuery->sum(\DB::raw('ABS(warehouse_permits_products.quantity)'));

            // حساب المُعالج (المُتلف أو المُصنّع) بعد نفس الإذن
            $processedQuery = WarehousePermitsProducts::query()
                ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                ->where('warehouse_permits.store_houses_id', $warehouseId)
                ->where('warehouse_permits_products.product_id', $prodId)
                ->whereIn('warehouse_permits.permission_source_id', [
                    PermissionSources::MANUFACTURING_DAMAGED_MATERIALS ?? 0,
                    PermissionSources::INVENTORY_DAMAGE ?? 0,
                ]);

            if ($lastZeroPermitId) {
                //     $processedQuery->where('warehouse_permits.id', '>', $lastZeroPermitId);
            }

            $processedQty = $processedQuery->sum(\DB::raw('ABS(warehouse_permits_products.quantity)'));

            // التالف الصافي = الداخل - المُعالج (لكن لا يتجاوز المخزون الحالي)
            // التعديل: لا يتم تصفير التالف عند إذن المرتجع فقط، بل يبقى حتى تتم معالجته فعلياً
            $calculatedDamaged = $damagedQty - $processedQty;
            $calculatedDamaged = $calculatedDamaged < 0 ? 0 : $calculatedDamaged;
            // التالف لا يمكن أن يتجاوز المخزون الحالي
            $netDamaged[$prodId] = min($calculatedDamaged, $currentQuantity);
        }

        return $netDamaged;
    }

    /**
     * إيجاد آخر إذن (permit ID) وصل فيه المخزون لصفر لمنتج معين في مستودع معين
     * نستخدم ID الإذن بدلاً من التاريخ لضمان الترتيب الصحيح
     */
    private function findLastZeroStockPermitId($warehouseId, $productId)
    {
        // جلب كل حركات المخزون مرتبة بالـ ID (الترتيب الفعلي للإنشاء)
        $movements = WarehousePermitsProducts::query()
            ->join('warehouse_permits', 'warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
            ->where('warehouse_permits.store_houses_id', $warehouseId)
            ->where('warehouse_permits_products.product_id', $productId)
            ->orderBy('warehouse_permits.id', 'asc')
            ->select('warehouse_permits.id as permit_id', 'warehouse_permits_products.quantity', 'warehouse_permits.permission_source_id')
            ->get();

        if ($movements->isEmpty()) {
            return null;
        }

        // تحديد أنواع الأذونات الواردة (التي تزيد المخزون)
        $incomingTypes = [
            PermissionSources::PURCHASE_INVOICE,           // فاتورة مشتريات
            PermissionSources::PURCHASE_RETURN,            // مرتجع مشتريات
            PermissionSources::SALES_RETURN,               // مرتجع مبيعات
            PermissionSources::INVENTORY_RECEIVED,         // ورقة جرد وارد
            PermissionSources::POS_SHIFT_INBOUND,          // تحويل وارد لنقطة البيع
            PermissionSources::WAREHOUSE_ADD,              // اذن إضافة مخزني
            PermissionSources::WAREHOUSE_OPENING_BALANCE,  // رصيد افتتاحي
            PermissionSources::PRODUCT_EXCHANGE_RETURN,     // ارجاع تبديل تالف
            PermissionSources::MANUFACTURING_RETURNS,      // مرتجع تصنيع
        ];

        // حساب الرصيد التراكمي وتحديد آخر إذن وصل فيه لصفر
        $runningBalance = 0;
        $lastZeroPermitId = null;

        foreach ($movements as $movement) {
            $qty = abs($movement->quantity);

            if (in_array($movement->permission_source_id, $incomingTypes)) {
                $runningBalance += $qty; // وارد
            } else {
                $runningBalance -= $qty; // صادر
            }

            // إذا وصل الرصيد لصفر أو أقل، نسجل ID هذا الإذن
            if ($runningBalance <= 0) {
                $lastZeroPermitId = $movement->permit_id;
                $runningBalance = 0; // لا نسمح برصيد سالب
            }
        }

        return $lastZeroPermitId;
    }
}
