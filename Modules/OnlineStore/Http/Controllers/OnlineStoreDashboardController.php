<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Store\Brand;
use App\Models\Store\Cart;
use App\Models\Store\OnlineOrder;
use App\Models\Store\OnlineOrderItem;
use App\Models\Store\ProductReview;
use App\Models\Store\ShippingMethod;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreCoupon;
use App\Models\Store\StoreCouponUsage;
use App\Models\Store\Wishlist;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;
use Modules\OnlineStore\Http\Controllers\Concerns\ScopesStoreOrders;

class OnlineStoreDashboardController extends Controller
{
    use SanitizesFilters;
    use ScopesStoreOrders;

    /** صافي مبيعات الطلب كما في الدفاتر: الملغي صفر، وغيرُه إجماليُّه ناقصاً ما رُدّ منه */
    private const NET = "(CASE WHEN status = 'cancelled' THEN 0 ELSE total_amount - COALESCE(refund_amount, 0) END)";

    public function __construct()
    {
        $this->middleware('permission.role:online_store.dashboard.view');

        // فلاتر الرابط نصوصٌ لا مصفوفات — وإلا سقطت القوالب التي تطبعها
        $this->middleware(function ($request, $next) {
            $this->dropNonScalarFilters($request, ['start_date', 'end_date']);

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        // الفترة الزمنية (افتراضي آخر 30 يوم)
        /*
            حدّا الفترة لحظتان لا نصّان: كان `end_date` نصَّ تاريخٍ بلا وقت
            يُقارَن بـ`created_at`، فيُفهم «منتصف ليل ذلك اليوم» — وطلباتُ
            اليوم نفسه كلُّها خارج اللوحة. وتاريخٌ مشوَّه في الرابط يُهمَل.
        */
        $parseDate = function ($value, Carbon $default): Carbon {
            try {
                return is_string($value) && trim($value) !== '' ? Carbon::parse($value) : $default;
            } catch (\Throwable $e) {
                return $default;
            }
        };
        $startDate = $parseDate($request->query('start_date'), Carbon::now()->subDays(30))->startOfDay();
        $endDate = $parseDate($request->query('end_date'), Carbon::now())->endOfDay();

        // الفرع النشط للمستخدم الحالي — نفس ما تستعمله شاشة الطلبات
        // (OnlineOrderController::index)، كي لا تختلف أرقام لوحة التحكم عن
        // قائمة الطلبات الفعلية المعروضة له
        $branchId = $this->storeBranchNode();

        // =================== إحصائيات الطلبات من online_orders ===================
        $totalOrders = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])->count();
        $pendingOrders = OnlineOrder::forBranch($branchId)->where('status', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $completedOrders = OnlineOrder::forBranch($branchId)->where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $cancelledOrders = OnlineOrder::forBranch($branchId)->where('status', 'cancelled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // إجمالي المبيعات
        // كل غير الملغي، صافياً من المردود — كما يُقيَّد الإيراد لحظةَ إنشاء الطلب.
        // كان يجمع حالاتٍ منها `completed` غير الموجودة أصلاً، فيسقط المسلَّم من الإيراد
        $totalRevenue = OnlineOrder::forBranch($branchId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw(self::NET)) ?? 0;

        // متوسط قيمة الطلب
        $averageOrderValue = $totalOrders > 0
            ? (OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', '!=', 'cancelled')
                ->avg(DB::raw(self::NET)) ?? 0)
            : 0;

        // =================== إحصائيات العملاء من clients ===================
        // إجمالي عملاء المتجر الإلكتروني
        $totalStoreClients = Client::where('is_store_client', 1)->count();

        // العملاء الذين طلبوا في الفترة
        $totalCustomers = OnlineOrder::forBranch($branchId)->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');

        // العملاء الجدد في الفترة
        $newCustomers = Client::where('is_store_client', 1)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $returningCustomers = max(0, $totalCustomers - $newCustomers);

        // =================== إحصائيات المنتجات من products ===================
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $onlineProducts = Product::where('available_online', 1)->count();
        $featuredProducts = Product::where('featured_product', 1)->count();
        $posProducts = Product::where('available_pos', 1)->count();

        // المنتجات ذات المخزون المنخفض
        $lowStockProducts = 0;
        try {
            $trackedProducts = Product::where('track_inventory', 1)
                ->where('low_stock_alert', 1)
                ->where('low_stock_thershold', '>', 0)
                ->get();

            foreach ($trackedProducts as $product) {
                $totalQty = $product->totalQuantity();
                if ($totalQty <= $product->low_stock_thershold) {
                    $lowStockProducts++;
                }
            }
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.OnlineStoreDashboard', 'index');
            $lowStockProducts = 0;
        }

        // أكثر المنتجات مبيعاً
        $topSellingProducts = OnlineOrderItem::select('product_id', 'product_name',
            DB::raw('SUM(quantity) as total_sold'),
            DB::raw('SUM(unit_price * quantity) as total_revenue'))
            ->whereHas('order', function ($q) use ($startDate, $endDate, $branchId) {
                $q->forBranch($branchId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', '!=', 'cancelled');
            })
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        // =================== إحصائيات الأصناف من categories ===================
        $totalCategories = Category::count();
        $mainCategories = Category::where(function ($q) {
            $q->whereNull('main_category')->orWhere('main_category', 0);
        })->count();
        $subCategories = Category::whereNotNull('main_category')
            ->where('main_category', '>', 0)
            ->count();

        // الأصناف التي بها منتجات
        $categoriesWithProducts = 0;
        try {
            $categoriesWithProducts = Category::has('products')->count();
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.OnlineStoreDashboard', 'index');
            $categoriesWithProducts = DB::table('products')
                ->distinct('category_id')
                ->count('category_id');
        }

        // =================== إحصائيات العلامات التجارية من brands ===================
        $totalBrands = Brand::count();
        $activeBrands = Brand::where('is_active', 1)->count();
        $featuredBrands = Brand::where('is_active', 1)->where('is_featured', 1)->count();

        // =================== إحصائيات سلة التسوق من carts ===================
        $activeCartsCount = 0;
        $abandonedCartsCount = 0;
        $avgCartItems = 0;
        try {
            $activeCartsCount = Cart::whereHas('items')->count();
            $abandonedCartsCount = Cart::whereHas('items')
                ->where('updated_at', '<', Carbon::now()->subDays(3))
                ->count();
            $avgCartItems = Cart::whereHas('items')
                ->withCount('items')
                ->get()
                ->avg('items_count') ?? 0;
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.OnlineStoreDashboard', 'index');
            // الجدول قد لا يحتوي على بيانات
        }

        // =================== إحصائيات الكوبونات من store_coupons ===================
        $activeCoupons = StoreCoupon::forBranch($branchId)->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now());
            })
            ->count();

        $totalCouponUsage = StoreCouponUsage::whereHas('coupon', fn ($q) => $q->forBranch($branchId))
            ->whereBetween('created_at', [$startDate, $endDate])->count();
        $totalDiscountGiven = StoreCouponUsage::whereHas('coupon', fn ($q) => $q->forBranch($branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('discount_amount') ?? 0;

        $topCoupons = StoreCouponUsage::select('coupon_id',
            DB::raw('COUNT(*) as usage_count'),
            DB::raw('SUM(discount_amount) as total_discount'))
            ->whereHas('coupon', fn ($q) => $q->forBranch($branchId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('coupon')
            ->groupBy('coupon_id')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get();

        // =================== إحصائيات الشحن من shipping_methods ===================
        $totalShippingMethods = ShippingMethod::forBranch($branchId)->where('is_active', 1)->count();

        $shippingMethodsUsage = OnlineOrder::forBranch($branchId)->select('shipping_method_id', DB::raw('COUNT(*) as orders_count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('shipping_method_id')
            ->with('shippingMethod')
            ->groupBy('shipping_method_id')
            ->orderByDesc('orders_count')
            ->get();

        // =================== إحصائيات التقييمات من product_reviews ===================
        $totalReviews = ProductReview::whereBetween('created_at', [$startDate, $endDate])->count();
        $approvedReviews = ProductReview::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $pendingReviews = ProductReview::where('status', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $averageRating = ProductReview::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('rating') ?? 0;

        // =================== إحصائيات قائمة الأمنيات من wishlists ===================
        $totalWishlistItems = Wishlist::whereBetween('created_at', [$startDate, $endDate])->count();
        $uniqueWishlistUsers = Wishlist::whereBetween('created_at', [$startDate, $endDate])
            ->distinct('client_id')
            ->count('client_id');

        $topWishlistProducts = Wishlist::select('product_id', DB::raw('COUNT(*) as wishlist_count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('product_id')
            ->orderByDesc('wishlist_count')
            ->limit(5)
            ->with('product')
            ->get();

        // =================== إحصائيات البانرات من store_banners ===================
        $activeBanners = StoreBanner::where('is_active', 1)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now());
            })
            ->count();

        // =================== رسم بياني للطلبات (آخر 7 أيام) ===================
        $ordersChartData = [];
        $revenueChartData = [];
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('d M');

            $dayOrders = OnlineOrder::forBranch($branchId)->whereDate('created_at', $date)->count();
            $ordersChartData[] = $dayOrders;

            $dayRevenue = OnlineOrder::forBranch($branchId)->whereDate('created_at', $date)
                ->sum(DB::raw(self::NET)) ?? 0;
            $revenueChartData[] = round($dayRevenue, 2);
        }

        // =================== توزيع حالات الطلبات ===================
        $orderStatusDistribution = OnlineOrder::forBranch($branchId)->select('status', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                $statusValue = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;

                return [$statusValue => $item->count];
            })
            ->toArray();

        // =================== آخر الطلبات ===================
        $recentOrders = OnlineOrder::forBranch($branchId)->with(['client', 'shippingMethod'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // حقلا التاريخ في الصفحة (`type="date"`) يقرآن نصّاً بصيغة Y-m-d
        $startDate = $startDate->toDateString();
        $endDate = $endDate->toDateString();

        return view('onlinestore::dashboard.onlinestore.index', compact(
            // إحصائيات الطلبات
            'totalOrders',
            'pendingOrders',
            'completedOrders',
            'cancelledOrders',
            'totalRevenue',
            'averageOrderValue',

            // إحصائيات العملاء
            'totalStoreClients',
            'totalCustomers',
            'newCustomers',
            'returningCustomers',

            // المنتجات
            'totalProducts',
            'activeProducts',
            'onlineProducts',
            'featuredProducts',
            'posProducts',
            'lowStockProducts',
            'topSellingProducts',

            // الأصناف
            'totalCategories',
            'mainCategories',
            'subCategories',
            'categoriesWithProducts',

            // العلامات التجارية
            'totalBrands',
            'activeBrands',
            'featuredBrands',

            // سلة التسوق
            'activeCartsCount',
            'abandonedCartsCount',
            'avgCartItems',

            // الكوبونات
            'activeCoupons',
            'totalCouponUsage',
            'totalDiscountGiven',
            'topCoupons',

            // الشحن
            'totalShippingMethods',
            'shippingMethodsUsage',

            // التقييمات
            'totalReviews',
            'approvedReviews',
            'pendingReviews',
            'averageRating',

            // قائمة الأمنيات
            'totalWishlistItems',
            'uniqueWishlistUsers',
            'topWishlistProducts',

            // البانرات
            'activeBanners',

            // الرسوم البيانية
            'ordersChartData',
            'revenueChartData',
            'labels',
            'orderStatusDistribution',

            // آخر الطلبات
            'recentOrders',

            // التواريخ
            'startDate',
            'endDate'
        ));
    }
}
