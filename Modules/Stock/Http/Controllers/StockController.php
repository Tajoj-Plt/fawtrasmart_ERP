<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\ManufacturOrders;
use App\Models\Product;
use App\Models\ProductDetails;
// Models
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class StockController extends Controller
{
    use ScopesToEmployeeHierarchy;

    /**
     * بوابة الدخول: أي صلاحية واحدة من صلاحيات المخزون (`stock.*`) تكفي
     * لرؤية الداشبورد — ليست صلاحيةً بعينها. نفس مبدأ داشبورد الحسابات
     * (`Modules\Account\Http\Controllers\AccountController`).
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()?->hasAnyPermissionWithPrefix(\App\Constants\permissions\StockPermissions::PREFIX)) {
                abort(403, 'لا تملك أي صلاحية على وحدة المخزون.');
            }

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource with statistics and charts.
     */

    /** ملخّص المخزون المحسوب مرّةً واحدة لكل طلب. */
    private $stockSummary = null;

    public function index()
    {
        /*
            كل أرقام هذه اللوحة تُشتقّ من ملخّصٍ واحد لكل صنف
            (`productStock()`) — لا من دوالّ متفرّقة يحسب كلٌّ منها الشيء
            نفسه بطريقةٍ أخرى.

            كان على اللوحة **تعريفان متناقضان لـ«المخزون المنخفض»**: بطاقة
            العدّاد تقيس مجموع الصنف عبر المستودعات، والجدول أسفلها يقيس كل
            سطر مستودعٍ على حدة. فقرأ المستخدم «0 منتجات منخفضة» فوق جدولٍ
            يسرد منتجاً منخفضاً. وتعريفان لـ«قيمة المخزون»: واحد بسعر البيع
            وآخر بسعر الوحدة المسجَّل في سطر المستودع — وهو فارغ في أكثر
            الأسطر، فكانت القيمتان 220,215 و32,000 لنفس المخزون.
        */
        $stock = $this->productStock();

        $totalProducts = $stock->count();
        $activeProducts = $stock->where('is_active', true)->count();
        $totalQuantity = $stock->sum('stock_qty');

        // قيمة المخزون بالتكلفة — الأساس المحاسبي، لا سعر البيع.
        $inventoryCostValue = $stock->sum('cost_value');
        $inventorySaleValue = $stock->sum('sale_value');
        $expectedProfit = $inventorySaleValue - $inventoryCostValue;

        /*
            «منخفض» و«نافذ» تعريفٌ واحد على مستوى الصنف:
            نافذ = لا رصيد له إطلاقاً؛ منخفض = له رصيد لكنه بلغ حدّ التنبيه.
            وصنفٌ حدُّه صفر لا يُعدّ منخفضاً أبداً — إذ لم يُضبَط له حدّ بعد.
        */
        $lowStockProducts = $stock->where('is_low', true)->count();
        $outOfStockProducts = $stock->where('is_out', true)->count();

        // المنتجات القريبة من الانتهاء (خلال 30 يوم)
        $expiringSoonProducts = Product::where('expiry_date', '!=', null)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->count();

        // قائمة المنتجات القريبة من الانتهاء
        $expiringSoonProductsList = Product::where('expiry_date', '!=', null)
            ->whereDate('expiry_date', '<=', Carbon::now()->addDays(30))
            ->whereDate('expiry_date', '>=', Carbon::now())
            ->with('productDetails')
            ->get();

        // المستودعات — مع رصيدها الفعلي لا عدد أسطرها.
        $storehouses = $this->warehouseStock();
        $totalStorehouses = $storehouses->count();
        $activeStorehouses = $storehouses->where('is_active', true)->count();
        $mainStorehouses = $storehouses->where('is_major', true)->count();

        $totalCategories = Category::count();

        $topSellingProducts = $this->getTopSellingProducts(10);
        $lowStockItems = $this->getLowStockItems(20);
        $categoryDistribution = $this->getCategoryDistribution(8);

        /*
            حركات المخزون الأخيرة.

            **ما كان**

            الجدول يعرض `$movement->product->name` — و`WarehousePermits` هو
            **ترويسة إذن** لا صنفاً واحداً: أصنافُه في بنوده
            (`warehouse_permits_products`). فالعلاقة `product` لا وجود لها
            على الموديل، و`?? 'N/A'` يبتلع الخطأ — فعمود «المنتج» كان
            `N/A` في كل سطر بلا استثناء.

            وعمود «الكمية» كان يطبع `grand_total` — وهو **الإجمالي المالي**
            لا الكمّية. فيُقرأ «39150.00» على أنه عدد حبّات.

            **ما صار**

            البنود تُحمَّل مع الإذن: اسم أول صنف (وكم صنفاً معه)، ومجموع
            الكمّيات الفعلي من البنود. ومصدرُ الإذن يُحمَّل ليُقرأ نوع
            العملية باسمه لا برقمه.
        */
        $recentMovements = WarehousePermits::query()
            ->with([
                'storeHouse:id,name',
                'fromStoreHouse:id,name',
                'toStoreHouse:id,name',
                'permissionSource:id,name',
                'items:id,warehouse_permits_id,product_id,quantity',
                'items.product:id,name',
            ])
            ->when($this->allowedWarehouseIds(), function ($query, $ids) {
                // إذن يخصّ مستودعاً واحداً (إضافة/صرف) أو اثنين (تحويل) — يكفي أن يقع أحدهما ضمن النطاق
                $query->where(function ($q) use ($ids) {
                    $q->whereIn('store_houses_id', $ids ?: [0])
                        ->orWhereIn('from_store_houses_id', $ids ?: [0])
                        ->orWhereIn('to_store_houses_id', $ids ?: [0]);
                });
            })
            ->orderByDesc('permission_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        // البيانات الشهرية (آخر 12 شهر)
        $monthlyData = $this->getMonthlyData();

        return view('stock::dashboard.stock.index', [
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
            'totalQuantity' => $totalQuantity,
            'lowStockProducts' => $lowStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
            'expiringSoonProducts' => $expiringSoonProducts,
            'expiringSoonProductsList' => $expiringSoonProductsList,
            'inventoryCostValue' => $inventoryCostValue,
            'inventorySaleValue' => $inventorySaleValue,
            'expectedProfit' => $expectedProfit,
            'totalStorehouses' => $totalStorehouses,
            'activeStorehouses' => $activeStorehouses,
            'mainStorehouses' => $mainStorehouses,
            'storehouses' => $storehouses,
            'totalCategories' => $totalCategories,
            'topSellingProducts' => $topSellingProducts,
            'lowStockItems' => $lowStockItems,
            'categoryDistribution' => $categoryDistribution,
            'recentMovements' => $recentMovements,
            'months' => $monthlyData['months'],
            'monthlyInventory' => $monthlyData['inventory'],
            'monthlySold' => $monthlyData['sold'],
        ]);
    }

    /**
     * ملخّص المخزون لكل صنف — الأساس الوحيد لأرقام اللوحة كلّها.
     *
     * ضمّةٌ واحدة إلى `product_details`، فلا تضخّم. كانت الدوالّ السابقة
     * تضمّ `invoice_items` و`product_details` معاً في الاستعلام نفسه —
     * وحاصلُ ضمّتين إلى جدولين مستقلّين ضربٌ ديكارتي: صنفٌ له 3 بنود بيع
     * و4 أسطر مستودع يُنتج 12 سطراً، فتُجمع كل كمّية مستودعٍ ثلاث مرّات
     * وكل كمّية بيعٍ أربعاً.
     */
    private function productStock()
    {
        if ($this->stockSummary !== null) {
            return $this->stockSummary;
        }

        // نطاق البيانات: كمّيات مستودعات نطاق المستخدم فقط — الشرط في ON لا WHERE
        // كي يبقى صنفٌ بلا رصيدٍ في مستودعاتٍ مسموحة ظاهراً برصيد صفر، لا مختفياً
        $allowedWarehouseIds = $this->allowedWarehouseIds();

        return $this->stockSummary = Product::query()
            ->leftJoin('product_details', function ($join) use ($allowedWarehouseIds) {
                $join->on('product_details.product_id', '=', 'products.id');
                if ($allowedWarehouseIds !== null) {
                    $join->whereIn('product_details.store_house_id', $allowedWarehouseIds ?: [0]);
                }
            })
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy(
                'products.id', 'products.name', 'products.status',
                'products.category_id', 'products.sale_price',
                'products.purchase_price', 'products.low_stock_thershold',
                'categories.name'
            )
            ->select('products.id', 'products.name', 'products.status', 'products.category_id')
            ->selectRaw('COALESCE(categories.name, ?) as category_name', ['بدون فئة'])
            ->selectRaw('COALESCE(products.sale_price, 0) as sale_price')
            ->selectRaw('COALESCE(products.purchase_price, 0) as purchase_price')
            ->selectRaw('COALESCE(products.low_stock_thershold, 0) as threshold')
            ->selectRaw('COALESCE(SUM(product_details.quantity), 0) as stock_qty')
            ->get()
            ->map(function ($row) {
                $qty = (float) $row->stock_qty;
                $threshold = (float) $row->threshold;

                $row->stock_qty = $qty;
                $row->threshold = $threshold;
                $row->cost_value = $qty * (float) $row->purchase_price;
                $row->sale_value = $qty * (float) $row->sale_price;

                $status = $row->status instanceof \BackedEnum ? $row->status->value : $row->status;
                $row->is_active = $status === 'active';

                $row->is_out = $qty <= 0;
                $row->is_low = $qty > 0 && $threshold > 0 && $qty <= $threshold;

                return $row;
            });
    }

    /**
     * رصيد كل مستودع — كمّيةً وقيمةً وعدد أصناف.
     *
     * كان الجدول يعرض `productDetails()->count()` تحت «إجمالي العناصر» —
     * وهو **عدد أسطر** الجدول لا عدد القطع؛ مستودعٌ فيه 8281 قطعة من صنف
     * واحد كان يُقرأ «1».
     */
    private function warehouseStock()
    {
        $allowedWarehouseIds = $this->allowedWarehouseIds();

        $rowsQuery = ProductDetails::query()
            ->join('products', 'products.id', '=', 'product_details.product_id')
            ->whereNotNull('product_details.store_house_id')
            ->groupBy('product_details.store_house_id')
            ->selectRaw('product_details.store_house_id as wid')
            ->selectRaw('SUM(product_details.quantity) as qty')
            ->selectRaw('COUNT(DISTINCT product_details.product_id) as products')
            ->selectRaw('SUM(product_details.quantity * COALESCE(products.purchase_price, 0)) as cost_value');
        if ($allowedWarehouseIds !== null) {
            $rowsQuery->whereIn('product_details.store_house_id', $allowedWarehouseIds ?: [0]);
        }
        $rows = $rowsQuery->get()->keyBy('wid');

        $warehousesQuery = StoreHouse::orderBy('name');
        if ($allowedWarehouseIds !== null) {
            $warehousesQuery->whereIn('id', $allowedWarehouseIds ?: [0]);
        }

        return $warehousesQuery->get()->map(function ($warehouse) use ($rows) {
            $row = $rows->get($warehouse->id);

            $warehouse->stock_qty = (float) ($row->qty ?? 0);
            $warehouse->product_count = (int) ($row->products ?? 0);
            $warehouse->cost_value = (float) ($row->cost_value ?? 0);
            $warehouse->is_active = $warehouse->status === 'active';
            $warehouse->is_major = (bool) $warehouse->major;

            return $warehouse;
        });
    }

    /**
     * الأصناف الأكثر مبيعاً — من بنود الفواتير وحدها.
     *
     * كان الاستعلام يضمّ `product_details` أيضاً، فيضرب الكمّية المباعة في
     * عدد أسطر مستودعات الصنف.
     */
    private function getTopSellingProducts($limit = 10)
    {
        $sold = InvoiceItem::query()
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as sold_quantity')
            ->havingRaw('SUM(quantity) > 0')
            ->orderByDesc('sold_quantity')
            ->limit($limit)
            ->pluck('sold_quantity', 'product_id');

        if ($sold->isEmpty()) {
            return collect();
        }

        return $this->productStock()
            ->whereIn('id', $sold->keys())
            ->map(function ($row) use ($sold) {
                $row->sold_quantity = (float) $sold[$row->id];

                return $row;
            })
            ->sortByDesc('sold_quantity')
            ->values();
    }

    /**
     * الأصناف المحتاجة إلى إعادة تخزين — بالتعريف نفسه الذي تعدّه البطاقة.
     *
     * كان الجدول يسرد **أسطر `product_details`**، فيظهر سطرٌ بلا مستودع
     * ولا سعر: «N/A ... 0.00 ريال». وكان يقيس كل سطرٍ على حدة بينما تقيس
     * البطاقة مجموع الصنف — فتناقض العدّاد مع الجدول.
     */
    private function getLowStockItems($limit = 20)
    {
        return $this->productStock()
            ->filter(fn ($row) => $row->is_low || $row->is_out)
            ->sortBy('stock_qty')
            ->take($limit)
            ->values();
    }

    /**
     * توزيع الفئات — من الملخّص نفسه، فتتّفق أرقامها مع البطاقات.
     *
     * «نسبة البيع» كانت تُحسب من `sold/(sold+stock)`؛ ولا فواتير في النظام
     * بعد، فكانت صفراً في كل فئة — وهي النسب التي لم تكن تظهر. صارت النسبة
     * حصّةَ الفئة من قيمة المخزون، وهي رقمٌ ذو معنى دائماً.
     */
    private function getCategoryDistribution($limit = 8)
    {
        $stock = $this->productStock();
        $totalValue = (float) $stock->sum('cost_value');

        $sold = InvoiceItem::query()
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as q')
            ->pluck('q', 'product_id');

        return $stock->groupBy('category_id')
            ->map(function ($rows) use ($totalValue, $sold) {
                $costValue = (float) $rows->sum('cost_value');

                return (object) [
                    'id' => $rows->first()->category_id,
                    'name' => $rows->first()->category_name,
                    'product_count' => $rows->count(),
                    'current_stock' => (float) $rows->sum('stock_qty'),
                    'sold_quantity' => (float) $rows->sum(fn ($r) => (float) ($sold[$r->id] ?? 0)),
                    'stock_value' => $costValue,
                    'sale_value' => (float) $rows->sum('sale_value'),
                    'low_count' => $rows->where('is_low', true)->count(),
                    'out_count' => $rows->where('is_out', true)->count(),
                    'value_share' => $totalValue > 0 ? round(($costValue / $totalValue) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('stock_value')
            ->take($limit)
            ->values();
    }

    /**
     * الحصول على البيانات الشهرية (آخر 12 شهر)
     */
    private function getMonthlyData()
    {
        $months = [];
        $monthlyInventory = [];
        $monthlySold = [];

        // نطاق البيانات: مستودعات نطاق المستخدم فقط — نفس أساس اللوحة كلّها
        $allowedWarehouseIds = $this->allowedWarehouseIds();

        // Get data for the last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            // Add month name in Arabic
            $months[] = $date->translatedFormat('F Y');

            // Calculate inventory at the end of each month
            $inventory = ProductDetails::whereHas('product', function ($query) {
                $query->where('status', 'active');
            })
                ->when($allowedWarehouseIds !== null, fn ($q) => $q->whereIn('store_house_id', $allowedWarehouseIds ?: [0]))
                ->whereDate('created_at', '<=', $endOfMonth)
                ->sum('quantity');

            // Calculate sold items for the month
            $sold = InvoiceItem::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->whereHas('product', function ($query) {
                    $query->where('status', 'active');
                })
                ->when($allowedWarehouseIds !== null, fn ($q) => $q->whereIn('store_house_id', $allowedWarehouseIds ?: [0]))
                ->sum('quantity');

            $monthlyInventory[] = $inventory;
            $monthlySold[] = $sold;
        }

        return [
            'months' => $months,
            'inventory' => $monthlyInventory,
            'sold' => $monthlySold,
        ];
    }

    /**
     * Get enhanced monthly data for charts with more details
     */
    private function getEnhancedMonthlyData($startDate, $endDate)
    {
        $months = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $months[] = $current->copy();
            $current->addMonth();
        }

        $data = [
            'labels' => [],
            'incoming_permits' => [],
            'outgoing_permits' => [],
            'transfer_permits' => [],
            'manufacturing' => [],
        ];

        foreach ($months as $month) {
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $data['labels'][] = $month->locale('ar')->translatedFormat('F');

            // أذونات الإدخال
            $data['incoming_permits'][] = WarehousePermits::whereBetween('permission_date', [$monthStart, $monthEnd])
                ->where('permission_type', 'incoming')
                ->sum('grand_total');

            // أذونات الإخراج
            $data['outgoing_permits'][] = WarehousePermits::whereBetween('permission_date', [$monthStart, $monthEnd])
                ->where('permission_type', 'outgoing')
                ->sum('grand_total');

            // أذونات النقل
            $data['transfer_permits'][] = WarehousePermits::whereBetween('permission_date', [$monthStart, $monthEnd])
                ->where('permission_type', 'transfer')
                ->sum('grand_total');

            // أوامر التصنيع
            $data['manufacturing'][] = ManufacturOrders::whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
        }

        return $data;
    }

    /**
     * Get top moving products
     */
    private function getTopMovingProducts($limit = 10)
    {
        return WarehousePermitsProducts::select('product_id', DB::raw('SUM(quantity) as total_quantity'))
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'product_name' => $item->product->name ?? 'غير معروف',
                    'quantity' => $item->total_quantity,
                    'product_id' => $item->product_id,
                ];
            });
    }

    /**
     * Get stock quantity by warehouse
     */
    private function getStockByWarehouse()
    {
        return StoreHouse::select('store_houses.id', 'store_houses.name')
            ->withSum('productDetails as total_quantity', 'quantity')
            ->withSum(['productDetails as total_value' => function ($query) {
                $query->select(DB::raw('SUM(quantity * unit_price)'));
            }], 'quantity')
            ->where('store_houses.status', 'active')
            ->having('total_quantity', '>', 0)
            ->get()
            ->map(function ($warehouse) {
                return [
                    'warehouse_name' => $warehouse->name,
                    'quantity' => $warehouse->total_quantity ?? 0,
                    'value' => $warehouse->total_value ?? 0,
                    'warehouse_id' => $warehouse->id,
                ];
            });
    }

    /**
     * Get expiring or expired products
     */
    private function getExpiringProducts()
    {
        $today = Carbon::today();
        $warningDate = Carbon::today()->addDays(30);

        return Product::whereNotNull('expiry_date')
            ->where(function ($query) use ($today, $warningDate) {
                $query->where('expiry_date', '<=', $warningDate)
                    ->where('expiry_date', '>=', $today);
            })
            ->orWhere('expiry_date', '<', $today)
            ->with('productDetails')
            ->get()
            ->map(function ($product) use ($today) {
                $daysToExpiry = Carbon::parse($product->expiry_date)->diffInDays($today, false);
                // Calculate total quantity without calling conflicting methods
                $totalQuantity = 0;
                foreach ($product->productDetails as $detail) {
                    $totalQuantity += $detail->quantity;
                }

                return [
                    'product_name' => $product->name,
                    'expiry_date' => $product->expiry_date,
                    'days_to_expiry' => $daysToExpiry,
                    'status' => $daysToExpiry < 0 ? 'expired' : 'expiring',
                    'quantity' => $totalQuantity,
                    'product_id' => $product->id,
                ];
            });
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities($limit = 10)
    {
        $activities = collect();

        // أحدث أذونات المستودعات
        $permits = WarehousePermits::with(['storeHouse', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($permit) {
                return [
                    'type' => 'warehouse_permit',
                    'description' => "إذن {$this->getPermitTypeName($permit->permission_type)} - {$permit->number}",
                    'user' => $permit->user->name ?? 'النظام',
                    'date' => $permit->created_at,
                    'icon' => $this->getPermitIcon($permit->permission_type),
                    'color' => $this->getPermitColor($permit->permission_type),
                ];
            });

        // أحدث أوامر التصنيع
        $manufacturing = ManufacturOrders::with(['product', 'employee'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return [
                    'type' => 'manufacturing_order',
                    'description' => "أمر تصنيع - {$order->code}",
                    'user' => $order->employee->name ?? 'النظام',
                    'date' => $order->created_at,
                    'icon' => 'settings',
                    'color' => 'info',
                ];
            });

        // دمج جميع الأنشطة وترتيبها
        return $activities
            ->concat($permits)
            ->concat($manufacturing)
            ->sortByDesc('date')
            ->take($limit)
            ->values();
    }

    /**
     * Get stock distribution by category
     */
    private function getStockByCategory()
    {
        return DB::table('products')
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(product_details.quantity) as total_quantity'),
                DB::raw('SUM(product_details.quantity * product_details.unit_price) as total_value')
            )
            ->where('product_details.quantity', '>', 0)
            ->groupBy('categories.id', 'categories.name')
            ->get()
            ->map(function ($item) {
                return [
                    'category_name' => $item->category_name,
                    'quantity' => $item->total_quantity,
                    'value' => $item->total_value,
                ];
            });
    }

    /**
     * Calculate inventory turnover rate
     */
    private function calculateInventoryTurnover($startDate, $endDate)
    {
        // تكلفة البضاعة المباعة (COGS)
        $cogs = WarehousePermits::whereBetween('permission_date', [$startDate, $endDate])
            ->where('permission_type', 'outgoing')
            ->sum('grand_total');

        // متوسط قيمة المخزون
        $avgInventory = ProductDetails::join('products', 'product_details.product_id', '=', 'products.id')
            ->select(DB::raw('AVG(product_details.quantity * product_details.unit_price) as avg'))
            ->where('product_details.quantity', '>', 0)
            ->value('avg') ?? 1;

        return $avgInventory > 0 ? round($cogs / $avgInventory, 2) : 0;
    }

    /**
     * Helper: Get permit type name in Arabic
     */
    private function getPermitTypeName($type)
    {
        $types = [
            'incoming' => 'إدخال',
            'outgoing' => 'إخراج',
            'transfer' => 'نقل',
            'adjustment' => 'تسوية',
        ];

        return $types[$type] ?? $type;
    }

    /**
     * Helper: Get permit icon
     */
    private function getPermitIcon($type)
    {
        $icons = [
            'incoming' => 'arrow-down-circle',
            'outgoing' => 'arrow-up-circle',
            'transfer' => 'shuffle',
            'adjustment' => 'edit-3',
        ];

        return $icons[$type] ?? 'file-text';
    }

    /**
     * Helper: Get permit color
     */
    private function getPermitColor($type)
    {
        $colors = [
            'incoming' => 'success',
            'outgoing' => 'danger',
            'transfer' => 'info',
            'adjustment' => 'warning',
        ];

        return $colors[$type] ?? 'primary';
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stock::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Implementation for storing new stock items
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('stock::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('stock::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Implementation for updating stock items
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Implementation for deleting stock items
    }

    /**
     * Get stock report
     */
    public function stockReport(Request $request)
    {
        $storeHouseId = $request->get('storehouse_id');
        $categoryId = $request->get('category_id');

        $query = Product::with(['productDetails', 'category']);

        if ($storeHouseId) {
            $query->whereHas('productDetails', function ($q) use ($storeHouseId) {
                $q->where('store_house_id', $storeHouseId);
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->get()->map(function ($product) use ($storeHouseId) {
            // Calculate quantity without calling conflicting methods
            $quantity = 0;
            $totalUnitPrice = 0;
            $count = 0;

            foreach ($product->productDetails as $detail) {
                if (! $storeHouseId || $detail->store_house_id == $storeHouseId) {
                    $quantity += $detail->quantity;
                    $totalUnitPrice += $detail->unit_price;
                    $count++;
                }
            }

            $avgUnitPrice = $count > 0 ? $totalUnitPrice / $count : 0;

            return [
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $avgUnitPrice,
                'total_value' => $quantity * $avgUnitPrice,
                'category' => $product->category->name ?? 'غير مصنف',
            ];
        });

        return response()->json($products);
    }

    /**
     * Get low stock alert
     */
    public function lowStockAlert()
    {
        $lowStockProducts = Product::whereHas('productDetails', function ($query) {
            $query->havingRaw('SUM(quantity) <= products.low_stock_thershold');
        })
            ->with(['productDetails' => function ($query) {
                $query->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                    ->groupBy('product_id');
            }])
            ->get()
            ->map(function ($product) {
                // Calculate current quantity without calling conflicting methods
                $currentQuantity = 0;
                foreach ($product->productDetails as $detail) {
                    $currentQuantity += $detail->quantity;
                }

                return [
                    'product_name' => $product->name,
                    'current_quantity' => $currentQuantity,
                    'threshold' => $product->low_stock_thershold,
                    'status' => 'warning',
                ];
            });

        return response()->json($lowStockProducts);
    }

    // ==================== دوال مساعدة إضافية ====================

    /**
     * Calculate total storehouse capacity
     */
    private function calculateTotalStorehouseCapacity()
    {
        return ProductDetails::sum('quantity');
    }

    /**
     * Calculate storehouse utilization percentage
     */
    private function calculateStorehouseUtilization()
    {
        $totalCapacity = $this->calculateTotalStorehouseCapacity();
        $usedCapacity = ProductDetails::where('quantity', '>', 0)->sum('quantity');

        return $totalCapacity > 0 ? ($usedCapacity / $totalCapacity) * 100 : 0;
    }

    /**
     * Get previous month stock value
     */
    private function getPreviousMonthStockValue()
    {
        $previousMonth = Carbon::now()->subMonth();

        return ProductDetails::join('products', 'product_details.product_id', '=', 'products.id')
            ->select(DB::raw('SUM(product_details.quantity * product_details.unit_price) as total'))
            ->whereMonth('product_details.created_at', $previousMonth->month)
            ->whereYear('product_details.created_at', $previousMonth->year)
            ->where('product_details.quantity', '>', 0)
            ->value('total') ?? 0;
    }

    /**
     * Get top selling products
     */

    /**
     * Get slow moving products
     */
    private function getSlowMovingProducts($limit = 10)
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        return Product::select('products.*')
            ->leftJoin('warehouse_permits_products', 'products.id', '=', 'warehouse_permits_products.product_id')
            ->leftJoin('warehouse_permits', function ($join) use ($threeMonthsAgo) {
                $join->on('warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                    ->where('warehouse_permits.permission_date', '>=', $threeMonthsAgo);
            })
            ->select(
                'products.id',
                'products.name',
                DB::raw('COALESCE(SUM(warehouse_permits_products.quantity), 0) as movement_count')
            )
            ->groupBy('products.id', 'products.name', 'products.category_id', 'products.brand_id', 'products.unit_id', 'products.sale_price', 'products.purchase_price', 'products.min_stock', 'products.max_stock', 'products.low_stock_thershold', 'products.barcode', 'products.description', 'products.image', 'products.status', 'products.created_at', 'products.updated_at')
            ->orderBy('movement_count', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get enhanced stock by warehouse
     */
    private function getEnhancedStockByWarehouse()
    {
        return StoreHouse::select('store_houses.*')
            ->leftJoin('product_details', 'store_houses.id', '=', 'product_details.store_house_id')
            ->select(
                'store_houses.id',
                'store_houses.name',
                DB::raw('COALESCE(SUM(product_details.quantity), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(product_details.quantity * product_details.unit_price), 0) as total_value'),
                DB::raw('COUNT(DISTINCT product_details.product_id) as product_count')
            )
            ->where('store_houses.status', 'active')
            ->groupBy('store_houses.id', 'store_houses.name', 'store_houses.description', 'store_houses.shipping_address', 'store_houses.phone', 'store_houses.email', 'store_houses.major', 'store_houses.status', 'store_houses.created_at', 'store_houses.updated_at')
            ->get()
            ->map(function ($warehouse) {
                return [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'quantity' => $warehouse->total_quantity,
                    'value' => $warehouse->total_value,
                    'product_count' => $warehouse->product_count,
                ];
            });
    }

    /**
     * Get warehouse comparison
     */
    private function getWarehouseComparison()
    {
        $lastMonth = Carbon::now()->subMonth();

        return StoreHouse::select('store_houses.*')
            ->leftJoin('product_details', 'store_houses.id', '=', 'product_details.store_house_id')
            ->select(
                'store_houses.id',
                'store_houses.name',
                DB::raw('COALESCE(SUM(CASE WHEN product_details.created_at >= "'.$lastMonth->toDateString().'" THEN product_details.quantity ELSE 0 END), 0) as current_month'),
                DB::raw('COALESCE(SUM(CASE WHEN product_details.created_at < "'.$lastMonth->toDateString().'" THEN product_details.quantity ELSE 0 END), 0) as previous_month')
            )
            ->where('store_houses.status', 'active')
            ->groupBy('store_houses.id', 'store_houses.name', 'store_houses.description', 'store_houses.shipping_address', 'store_houses.phone', 'store_houses.email', 'store_houses.major', 'store_houses.status', 'store_houses.created_at', 'store_houses.updated_at')
            ->get();
    }

    /**
     * Get expired products
     */
    private function getExpiredProducts()
    {
        $today = Carbon::today();

        return Product::whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->with('productDetails')
            ->get()
            ->map(function ($product) use ($today) {
                $daysExpired = Carbon::parse($product->expiry_date)->diffInDays($today);
                // Calculate total quantity without calling conflicting methods
                $totalQuantity = 0;
                foreach ($product->productDetails as $detail) {
                    $totalQuantity += $detail->quantity;
                }

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'expiry_date' => $product->expiry_date,
                    'days_expired' => $daysExpired,
                    'quantity' => $totalQuantity,
                    'status' => 'expired',
                ];
            });
    }

    /**
     * Get recent warehouse permits
     */
    private function getRecentWarehousePermits($limit = 10)
    {
        return WarehousePermits::with(['storeHouse', 'user', 'warehousePermitsProducts'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($permit) {
                return [
                    'id' => $permit->id,
                    'number' => $permit->number,
                    'type' => $permit->permission_type,
                    'type_name' => $this->getPermitTypeName($permit->permission_type),
                    'storehouse' => $permit->storeHouse->name ?? 'غير محدد',
                    'grand_total' => $permit->grand_total,
                    'items_count' => $permit->warehousePermitsProducts->count(),
                    'date' => $permit->permission_date,
                    'created_by' => $permit->user->name ?? 'النظام',
                    'created_at' => $permit->created_at,
                    'icon' => $this->getPermitIcon($permit->permission_type),
                    'color' => $this->getPermitColor($permit->permission_type),
                ];
            });
    }

    /**
     * Get recent product movements
     */
    private function getRecentProductMovements($limit = 10)
    {
        return ProductDetails::with(['product', 'storeHouse'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'product' => $detail->product->name ?? 'غير محدد',
                    'storehouse' => $detail->storeHouse->name ?? 'غير محدد',
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                    'type' => $detail->type,
                    'type_of_operation' => $detail->type_of_operation,
                    'date' => $detail->date,
                    'created_at' => $detail->created_at,
                ];
            });
    }

    /**
     * Get recent manufacturing orders
     */
    private function getRecentManufacturingOrders($limit = 10)
    {
        return ManufacturOrders::with(['product', 'employee', 'mainWarehouse'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'code' => $order->code,
                    'name' => $order->name,
                    'product' => $order->product->name ?? 'غير محدد',
                    'quantity' => $order->quantity,
                    'actual_quantity' => $order->actual_quantity,
                    'status' => $order->status,
                    'warehouse' => $order->mainWarehouse->name ?? 'غير محدد',
                    'employee' => $order->employee->name ?? 'غير محدد',
                    'from_date' => $order->from_date,
                    'to_date' => $order->to_date,
                    'created_at' => $order->created_at,
                ];
            });
    }

    /**
     * Get recent products
     */
    private function getRecentProducts($limit = 10)
    {
        return Product::with(['category', 'supplier', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name ?? 'غير مصنف',
                    'supplier' => $product->supplier->trade_name ?? 'غير محدد',
                    'sale_price' => $product->sale_price,
                    'purchase_price' => $product->purchase_price,
                    'status' => $product->status,
                    'created_by' => $product->creator->name ?? 'النظام',
                    'created_at' => $product->created_at,
                ];
            });
    }

    /**
     * Get enhanced stock by category
     */
    private function getEnhancedStockByCategory()
    {
        return DB::table('products')
            ->join('product_details', 'products.id', '=', 'product_details.product_id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                DB::raw('COUNT(DISTINCT products.id) as product_count'),
                DB::raw('SUM(product_details.quantity) as total_quantity'),
                DB::raw('SUM(product_details.quantity * product_details.unit_price) as total_value'),
                DB::raw('AVG(product_details.unit_price) as average_price')
            )
            ->where('product_details.quantity', '>', 0)
            ->groupBy('categories.id', 'categories.name')
            ->get();
    }

    /**
     * Get category performance
     */
    private function getCategoryPerformance($startDate, $endDate)
    {
        return DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('warehouse_permits_products', 'products.id', '=', 'warehouse_permits_products.product_id')
            ->leftJoin('warehouse_permits', function ($join) use ($startDate, $endDate) {
                $join->on('warehouse_permits_products.warehouse_permits_id', '=', 'warehouse_permits.id')
                    ->whereBetween('warehouse_permits.permission_date', [$startDate, $endDate]);
            })
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COUNT(DISTINCT warehouse_permits.id) as transactions_count'),
                DB::raw('COALESCE(SUM(warehouse_permits_products.quantity), 0) as total_movement'),
                DB::raw('COALESCE(SUM(warehouse_permits_products.total), 0) as total_value')
            )
            ->groupBy('categories.id', 'categories.name')
            ->get();
    }

    /**
     * Calculate average days to sell
     */
    private function calculateAverageDaysToSell($startDate, $endDate)
    {
        $products = Product::with(['productDetails', 'warehousePermitsProducts' => function ($query) use ($startDate, $endDate) {
            $query->whereHas('warehousePermits', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('permission_date', [$startDate, $endDate])
                    ->where('permission_type', 'outgoing');
            });
        }])->get();

        $totalDays = 0;
        $count = 0;

        foreach ($products as $product) {
            if ($product->warehousePermitsProducts->count() > 0) {
                $firstMovement = $product->productDetails->min('created_at');
                $lastMovement = $product->warehousePermitsProducts->max('created_at');

                if ($firstMovement && $lastMovement) {
                    $days = Carbon::parse($firstMovement)->diffInDays(Carbon::parse($lastMovement));
                    $totalDays += $days;
                    $count++;
                }
            }
        }

        return $count > 0 ? round($totalDays / $count, 2) : 0;
    }

    /**
     * Calculate stockout rate
     */
    private function calculateStockoutRate()
    {
        $totalProducts = Product::where('status', 1)->count();
        $outOfStockProducts = Product::where('status', 1)
            ->whereDoesntHave('productDetails', function ($q) {
                $q->where('quantity', '>', 0);
            })->count();

        return $totalProducts > 0 ? ($outOfStockProducts / $totalProducts) * 100 : 0;
    }

    /**
     * Calculate order fulfillment rate
     */
    private function calculateOrderFulfillmentRate($startDate, $endDate)
    {
        $totalOrders = ManufacturOrders::whereBetween('created_at', [$startDate, $endDate])->count();
        $completedOrders = ManufacturOrders::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->count();

        return $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;
    }

    /**
     * Get weekly analysis
     */
    private function getWeeklyAnalysis()
    {
        $weeks = [];
        $data = [];

        for ($i = 3; $i >= 0; $i--) {
            $weekStart = Carbon::now()->subWeeks($i)->startOfWeek();
            $weekEnd = Carbon::now()->subWeeks($i)->endOfWeek();

            $weeks[] = 'الأسبوع '.($i + 1);

            $permits = WarehousePermits::whereBetween('permission_date', [$weekStart, $weekEnd])
                ->count();

            $data[] = [
                'week' => 'الأسبوع '.(4 - $i),
                'permits' => $permits,
            ];
        }

        return [
            'labels' => $weeks,
            'data' => $data,
        ];
    }

    /**
     * Get daily analysis
     */
    private function getDailyAnalysis($days = 30)
    {
        $dailyData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();

            $permits = WarehousePermits::whereDate('permission_date', $date)
                ->count();

            $dailyData[] = [
                'date' => $date,
                'date_formatted' => Carbon::parse($date)->locale('ar')->translatedFormat('d M'),
                'permits' => $permits,
            ];
        }

        return $dailyData;
    }

    /**
     * Calculate total products value
     */
    private function calculateTotalProductsValue()
    {
        return Product::where('status', 1)->sum(DB::raw('sale_price * COALESCE((SELECT SUM(quantity) FROM product_details WHERE product_details.product_id = products.id), 0)'));
    }

    /**
     * Calculate average product price
     */
    private function calculateAverageProductPrice()
    {
        return Product::where('status', 1)->avg('sale_price') ?? 0;
    }

    /**
     * Get most expensive product
     */
    private function getMostExpensiveProduct()
    {
        return Product::where('status', 1)
            ->orderBy('sale_price', 'desc')
            ->first();
    }

    /**
     * Get least expensive product
     */
    private function getLeastExpensiveProduct()
    {
        return Product::where('status', 1)
            ->where('sale_price', '>', 0)
            ->orderBy('sale_price', 'asc')
            ->first();
    }
}
