<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Store\Brand;
use App\Models\Store\OnlineStoreSetting;
use App\Models\Store\ProductOption;
use App\Models\Store\ProductOptionValue;
use App\Models\Store\ProductReview;
use App\Models\Store\RecentlyViewedProduct;
use App\Models\Store\StoreBanner;
use App\Services\OnlineStoreSettingsService;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;
use Modules\OnlineStore\Http\Controllers\Api\Traits\ResolvesStoreSession;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;

class StoreHomeController extends Controller
{
    use FormatsImages;
    use ResolvesStoreDefaults;
    use ResolvesStoreSession;

    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     */
    protected function client(): ?Client
    {
        return Auth::guard('client')->user();
    }

    /**
     * الصفحة الرئيسية للمتجر
     * تحتوي على المنتجات المميزة، الأصناف، البراندات، البانرات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // جلب الإعدادات من الـ Service
            $homeSettings = OnlineStoreSettingsService::getHomePageSettings();

            // استخراج الإعدادات
            $currencySymbol = $homeSettings['currency_symbol'];
            $currencyPosition = $homeSettings['currency_position'];
            $maxCategories = $homeSettings['max_categories'];
            $maxBrands = $homeSettings['max_brands'];
            $maxFeaturedProducts = $homeSettings['max_featured_products'];
            $maxNewArrivals = $homeSettings['max_new_arrivals'];
            $maxTopRatedProducts = $homeSettings['max_top_rated_products'];
            $maxBestSelling = $homeSettings['max_best_selling_products'];
            $maxRecentlyViewed = $homeSettings['max_recently_viewed'];

            // إعدادات عرض الأصناف
            $showCategoryStock = $homeSettings['show_category_stock'];
            $showSubCategories = $homeSettings['show_sub_categories'];
            $showCategoryDescription = $homeSettings['show_category_description'];

            // نطاقات الأسعار من الإعدادات (مطلوب للفلترة)
            $priceRangesConfig = OnlineStoreSettingsService::getPriceRanges();

            // المنتجات المميزة
            $featuredProducts = Product::where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->where('featured_product', 1)
                ->with(['category:id,name', 'brand:id,name,logo'])
                ->select([
                    'id', 'name', 'description', 'sale_price', 'purchase_price',
                    'images', 'category_id', 'brand_id', 'discount', 'discount_type',
                    'featured_product', 'available_online', 'track_inventory',
                ])
                ->limit($maxFeaturedProducts)
                ->get()
                ->map(function ($product) use ($currencySymbol, $currencyPosition) {
                    return $this->formatProduct($product, $currencySymbol, $currencyPosition);
                });

            // أحدث المنتجات
            $newArrivals = Product::where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->with(['category:id,name', 'brand:id,name,logo'])
                ->select([
                    'id', 'name', 'description', 'sale_price', 'purchase_price',
                    'images', 'category_id', 'brand_id', 'discount', 'discount_type',
                    'featured_product', 'available_online', 'track_inventory', 'created_at',
                ])
                ->orderBy('created_at', 'desc')
                ->limit($maxNewArrivals)
                ->get()
                ->map(function ($product) use ($currencySymbol, $currencyPosition) {
                    return $this->formatProduct($product, $currencySymbol, $currencyPosition);
                });

            // أفضل المنتجات تقييماً
            $topRatedProducts = $this->getTopRatedProducts($maxTopRatedProducts);

            // المنتجات الأكثر مبيعاً
            $bestSellingProducts = $this->getBestSellingProducts($maxBestSelling);

            // جميع المنتجات مع Pagination والبحث والفلترة
            $perPage = min((int) $request->get('per_page', 12), 50);
            $allProductsQuery = Product::where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->with(['category:id,name', 'brand:id,name,logo']);

            // البحث بالاسم
            if ($request->filled('search')) {
                $search = $request->search;
                $allProductsQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // فلترة حسب الصنف
            if ($request->filled('category_id')) {
                $categoryId = $request->category_id;
                $categoryIds = Category::where('id', $categoryId)
                    ->orWhere('main_category', $categoryId)
                    ->pluck('id')
                    ->toArray();
                $allProductsQuery->whereIn('category_id', $categoryIds);
            }

            // فلترة حسب البراند
            if ($request->filled('brand_id')) {
                $allProductsQuery->where('brand_id', $request->brand_id);
            }

            // فلترة حسب عدة براندات
            if ($request->filled('brand_ids')) {
                $brandIds = is_array($request->brand_ids)
                    ? $request->brand_ids
                    : explode(',', $request->brand_ids);
                $allProductsQuery->whereIn('brand_id', $brandIds);
            }

            // فلترة حسب نطاق السعر
            if ($request->filled('min_price')) {
                $allProductsQuery->where('sale_price', '>=', (float) $request->min_price);
            }
            if ($request->filled('max_price')) {
                $allProductsQuery->where('sale_price', '<=', (float) $request->max_price);
            }

            // فلترة حسب نطاق السعر المحدد مسبقاً
            if ($request->filled('price_range')) {
                $priceRangeKey = $request->price_range;
                // البحث في نطاقات الأسعار المحددة
                foreach ($priceRangesConfig as $range) {
                    if ($range['key'] === $priceRangeKey) {
                        if ($range['min'] !== null && $range['max'] !== null) {
                            $allProductsQuery->whereBetween('sale_price', [$range['min'], $range['max']]);
                        } elseif ($range['min'] !== null) {
                            $allProductsQuery->where('sale_price', '>=', $range['min']);
                        } elseif ($range['max'] !== null) {
                            $allProductsQuery->where('sale_price', '<=', $range['max']);
                        }
                        break;
                    }
                }
            }

            // فلترة حسب التقييم (النجوم)
            if ($request->filled('rating')) {
                $rating = (int) $request->rating;
                if ($rating >= 1 && $rating <= 5) {
                    $productIds = ProductReview::where('status', 'approved')
                        ->select('product_id')
                        ->groupBy('product_id')
                        ->havingRaw('ROUND(AVG(rating)) = ?', [$rating])
                        ->pluck('product_id');
                    $allProductsQuery->whereIn('id', $productIds);
                } elseif ($rating === 0) {
                    // بدون تقييم
                    $ratedProductIds = ProductReview::where('status', 'approved')
                        ->distinct()
                        ->pluck('product_id');
                    $allProductsQuery->whereNotIn('id', $ratedProductIds);
                }
            }

            // فلترة حسب الحد الأدنى للتقييم (مثلاً 4 نجوم وأكثر)
            if ($request->filled('min_rating')) {
                $minRating = (float) $request->min_rating;
                $productIds = ProductReview::where('status', 'approved')
                    ->select('product_id')
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$minRating])
                    ->pluck('product_id');
                $allProductsQuery->whereIn('id', $productIds);
            }

            // فلترة حسب المنتجات المميزة
            if ($request->filled('featured') && $request->featured) {
                $allProductsQuery->where('featured_product', 1);
            }

            // فلترة حسب خيارات المتغيّرات (لون/مقاس…): options[اللون][]=أزرق
            $this->applyOptionFilters($allProductsQuery, $request->input('options', []));

            // الترتيب
            $sortBy = $request->get('sort_by', 'created_at');
            switch ($sortBy) {
                case 'price_low':
                    $allProductsQuery->orderBy('sale_price', 'asc');
                    break;
                case 'price_high':
                    $allProductsQuery->orderBy('sale_price', 'desc');
                    break;
                case 'name':
                    $allProductsQuery->orderBy('name', 'asc');
                    break;
                case 'oldest':
                    $allProductsQuery->orderBy('created_at', 'asc');
                    break;
                case 'featured':
                    $allProductsQuery->orderBy('featured_product', 'desc')->orderBy('created_at', 'desc');
                    break;
                case 'newest':
                default:
                    $allProductsQuery->orderBy('created_at', 'desc');
                    break;
            }

            $allProductsPaginated = $allProductsQuery->paginate($perPage);
            $allProducts = [
                'products' => $allProductsPaginated->getCollection()->map(function ($product) use ($currencySymbol, $currencyPosition) {
                    return $this->formatProduct($product, $currencySymbol, $currencyPosition);
                }),
                'pagination' => [
                    'current_page' => $allProductsPaginated->currentPage(),
                    'last_page' => $allProductsPaginated->lastPage(),
                    'per_page' => $allProductsPaginated->perPage(),
                    'total' => $allProductsPaginated->total(),
                    'from' => $allProductsPaginated->firstItem(),
                    'to' => $allProductsPaginated->lastItem(),
                    'has_more_pages' => $allProductsPaginated->hasMorePages(),
                ],
                'applied_filters' => [
                    'search' => $request->get('search'),
                    'category_id' => $request->get('category_id'),
                    'brand_id' => $request->get('brand_id'),
                    'brand_ids' => $request->get('brand_ids'),
                    'min_price' => $request->get('min_price'),
                    'max_price' => $request->get('max_price'),
                    'price_range' => $request->get('price_range'),
                    'rating' => $request->get('rating'),
                    'min_rating' => $request->get('min_rating'),
                    'featured' => $request->get('featured'),
                    'options' => $request->input('options', []),
                    'sort_by' => $sortBy,
                ],
            ];

            // أفضل الأصناف (مرتبة حسب عدد المنتجات)
            $categoriesQuery = Category::where(function ($q) {
                $q->whereNull('main_category')->orWhere('main_category', 0);
            })
                ->withCount(['products' => function ($q) {
                    $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                }])
                ->orderBy('products_count', 'desc')
                ->limit($maxCategories);

            // تحميل الأصناف الفرعية إذا كان الإعداد مفعلاً
            if ($showSubCategories) {
                $categoriesQuery->with(['children' => function ($q) {
                    $q->withCount(['products' => function ($q2) {
                        $q2->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                    }]);
                }]);
            }

            $categories = $categoriesQuery->get()
                ->map(function ($category) use ($showCategoryStock, $showSubCategories, $showCategoryDescription) {
                    $categoryData = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'image' => $category->attachments ?? null,
                        'products_count' => $category->products_count ?? 0,
                    ];

                    // إضافة الوصف إذا كان الإعداد مفعلاً
                    if ($showCategoryDescription) {
                        $categoryData['description'] = $category->discretion ?? '';
                    }

                    // إضافة المخزون الكلي إذا كان الإعداد مفعلاً
                    if ($showCategoryStock) {
                        $totalStock = Product::where('category_id', $category->id)
                            ->where('available_online', 1)
                            ->where('type', ProductType::PRODUCTS->value)
                            ->where('status', 'active')
                            ->with('productDetails')
                            ->get()
                            ->sum(fn ($product) => $this->getStoreStockQuantity($product));
                        $categoryData['total_stock'] = $totalStock;
                    }

                    // إضافة الأصناف الفرعية إذا كان الإعداد مفعلاً
                    if ($showSubCategories && $category->children) {
                        $categoryData['sub_categories'] = $category->children->map(function ($subCategory) use ($showCategoryStock, $showCategoryDescription) {
                            $subData = [
                                'id' => $subCategory->id,
                                'name' => $subCategory->name,
                                'image' => $subCategory->attachments ?? null,
                                'products_count' => $subCategory->products_count ?? 0,
                            ];

                            if ($showCategoryDescription) {
                                $subData['description'] = $subCategory->discretion ?? '';
                            }

                            if ($showCategoryStock) {
                                $subTotalStock = Product::where('category_id', $subCategory->id)
                                    ->where('available_online', 1)
                                    ->where('type', ProductType::PRODUCTS->value)
                                    ->where('status', 'active')
                                    ->with('productDetails')
                                    ->get()
                                    ->sum(fn ($product) => $this->getStoreStockQuantity($product));
                                $subData['total_stock'] = $subTotalStock;
                            }

                            return $subData;
                        })->values();
                    }

                    return $categoryData;
                })->values();

            // أفضل البراندات (مرتبة حسب عدد المنتجات)
            $brands = Brand::where('is_active', 1)
                ->withCount(['products' => function ($q) {
                    $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                }])
                ->orderBy('products_count', 'desc')
                ->limit($maxBrands)
                ->get()
                ->map(function ($brand) {
                    return [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'logo' => $this->formatImageUrl($brand->logo),
                        'is_featured' => (bool) $brand->is_featured,
                        'products_count' => $brand->products_count ?? 0,
                    ];
                });

            // البانرات النشطة - الصفحة الرئيسية
            $banners = StoreBanner::where('is_active', 1)
                ->where(function ($q) {
                    $q->whereNull('start_date')
                        ->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                })
                ->orderBy('sort_order', 'asc')
                ->orderBy('created_at', 'desc')
                ->select(['id', 'title', 'description', 'image', 'mobile_image', 'link', 'link_type', 'link_id', 'position', 'sort_order'])
                ->get()
                ->map(function ($banner) {
                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'description' => $banner->description,
                        'image' => $this->formatImageUrl($banner->image),
                        'mobile_image' => $this->formatImageUrl($banner->mobile_image ?? $banner->image),
                        'link' => $banner->link,
                        'link_type' => $banner->link_type?->value ?? null,
                        'link_id' => $banner->link_id,
                        'position' => $banner->position?->value ?? null,
                        'sort_order' => $banner->sort_order,
                    ];
                });

            // البانرات حسب الموقع
            $homeSliders = $banners->filter(function ($banner) {
                return $banner['position'] === 'home_slider';
            })->values();

            $topBanners = $banners->filter(function ($banner) {
                return $banner['position'] === 'home_top';
            })->values();

            $sideBanners = $banners->filter(function ($banner) {
                return $banner['position'] === 'sidebar';
            })->values();

            $homeBanners = $banners->filter(function ($banner) {
                return $banner['position'] === 'home_banner';
            })->values();

            $footerBanners = $banners->filter(function ($banner) {
                return $banner['position'] === 'footer';
            })->values();

            $popupBanners = $banners->filter(function ($banner) {
                return $banner['position'] === 'popup';
            })->values();

            $categoryBanners = $banners->filter(function ($banner) {
                return $banner['position'] === 'category';
            })->values();

            // نطاق الأسعار الفعلي
            $priceStats = Product::where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->selectRaw('MIN(sale_price) as min_price, MAX(sale_price) as max_price')
                ->first();

            // نطاقات الأسعار من الإعدادات
            $priceRangesConfig = OnlineStoreSetting::get('price_ranges', null);
            if ($priceRangesConfig && is_string($priceRangesConfig)) {
                $priceRangesConfig = json_decode($priceRangesConfig, true);
            }
            if (empty($priceRangesConfig)) {
                $priceRangesConfig = $this->getDefaultPriceRanges();
            }
            $priceRanges = $this->buildPriceRanges($priceRangesConfig, $currencySymbol, $currencyPosition);

            // التقييمات مع عدد المنتجات لكل نجمة
            $ratings = $this->getRatingsWithCounts();

            // إحصائيات المتجر
            $storeStats = [
                'total_products' => Product::where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active')->count(),
                'total_categories' => Category::whereHas('products', function ($q) {
                    $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                })->count(),
                'total_brands' => Brand::where('is_active', 1)->whereHas('products', function ($q) {
                    $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                })->count(),
            ];

            // بيانات المتجر الأساسية
            $storeInfo = OnlineStoreSettingsService::getStoreInfo();

            // روابط التواصل الاجتماعي
            $socialLinks = OnlineStoreSettingsService::getSocialSettings();

            // إعدادات SEO
            $seo = OnlineStoreSettingsService::getSeoSettings();

            // المنتجات المشاهدة مؤخراً
            $client = $this->client();
            $clientId = $client?->id;
            $sessionId = $this->storeSessionId($request);

            $recentlyViewedProducts = [];
            $recentlyViewedItems = RecentlyViewedProduct::getRecentlyViewed($clientId, $sessionId, $maxRecentlyViewed);

            foreach ($recentlyViewedItems as $item) {
                if ($item->product) {
                    $recentlyViewedProducts[] = $this->formatProduct($item->product, $currencySymbol, $currencyPosition);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    // بيانات المتجر
                    'store_info' => $storeInfo,
                    'social_links' => $socialLinks,
                    'seo' => $seo,
                    // البانرات
                    'banners' => [
                        'home_sliders' => $homeSliders,
                        'top_banners' => $topBanners,
                        'side_banners' => $sideBanners,
                        'home_banners' => $homeBanners,
                        'footer_banners' => $footerBanners,
                        'popup_banners' => $popupBanners,
                        'category_banners' => $categoryBanners,
                        'all' => $banners,
                    ],
                    // المنتجات
                    'featured_products' => $featuredProducts,
                    'new_arrivals' => $newArrivals,
                    'top_rated_products' => $topRatedProducts,
                    'best_selling_products' => $bestSellingProducts,
                    'recently_viewed_products' => $recentlyViewedProducts,
                    'all_products' => $allProducts,
                    // الفلاتر
                    'categories' => $categories,
                    'brands' => $brands,
                    'option_filters' => $this->buildOptionFilters(),
                    'ratings' => $ratings,
                    'price_range' => [
                        'min' => (float) ($priceStats->min_price ?? 0),
                        'max' => (float) ($priceStats->max_price ?? 0),
                    ],
                    'price_ranges' => $priceRanges,
                    // العملة والإحصائيات
                    'currency' => [
                        'symbol' => $currencySymbol,
                        'position' => $currencyPosition,
                    ],
                    'store_stats' => $storeStats,
                    // إعدادات كاملة من الـ Service
                    'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
                    'sort_options' => [
                        ['key' => 'newest', 'label' => 'الأحدث'],
                        ['key' => 'oldest', 'label' => 'الأقدم'],
                        ['key' => 'price_low', 'label' => 'السعر: من الأقل للأعلى'],
                        ['key' => 'price_high', 'label' => 'السعر: من الأعلى للأقل'],
                        ['key' => 'name', 'label' => 'الاسم'],
                        ['key' => 'featured', 'label' => 'المميزة أولاً'],
                        ['key' => 'rating', 'label' => 'التقييم: من الأعلى للأقل'],
                        ['key' => 'best_selling', 'label' => 'الأكثر مبيعاً'],
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'index');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * جلب المنتجات مع الفلترة والبحث والـ Pagination
     */
    public function products(Request $request): JsonResponse
    {
        try {
            $query = Product::where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->with(['category:id,name', 'brand:id,name,logo']);

            // البحث بالاسم
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // فلترة حسب الصنف
            if ($request->filled('category_id')) {
                $categoryId = $request->category_id;
                // جلب الأصناف الفرعية أيضاً
                $categoryIds = Category::where('id', $categoryId)
                    ->orWhere('main_category', $categoryId)
                    ->pluck('id')
                    ->toArray();
                $query->whereIn('category_id', $categoryIds);
            }

            // فلترة حسب البراند (يمكن تحديد أكثر من براند)
            if ($request->filled('brand_ids')) {
                $brandIds = is_array($request->brand_ids)
                    ? $request->brand_ids
                    : explode(',', $request->brand_ids);
                $query->whereIn('brand_id', $brandIds);
            }

            // فلترة حسب براند واحد
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // فلترة حسب نطاق السعر
            if ($request->filled('min_price')) {
                $query->where('sale_price', '>=', (float) $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('sale_price', '<=', (float) $request->max_price);
            }

            // فلترة حسب نطاقات السعر المحددة مسبقاً (من الإعدادات)
            if ($request->filled('price_range') && $request->price_range !== 'all') {
                $priceRangeKey = $request->price_range;
                $priceRangesConfig = OnlineStoreSettingsService::getPriceRanges();

                foreach ($priceRangesConfig as $range) {
                    if ($range['key'] === $priceRangeKey) {
                        if ($range['min'] !== null && $range['max'] !== null) {
                            $query->whereBetween('sale_price', [$range['min'], $range['max']]);
                        } elseif ($range['min'] !== null) {
                            $query->where('sale_price', '>=', $range['min']);
                        } elseif ($range['max'] !== null) {
                            $query->where('sale_price', '<=', $range['max']);
                        }
                        break;
                    }
                }
            }

            // فلترة حسب المنتجات المميزة
            if ($request->filled('featured') && $request->featured) {
                $query->where('featured_product', 1);
            }

            // فلترة حسب التقييم (نجوم محددة وليس "وأكثر")
            if ($request->filled('rating')) {
                $rating = (int) $request->rating;
                if ($rating >= 1 && $rating <= 5) {
                    $productIds = ProductReview::where('status', 'approved')
                        ->select('product_id')
                        ->groupBy('product_id')
                        ->havingRaw('ROUND(AVG(rating)) = ?', [$rating])
                        ->pluck('product_id');
                    $query->whereIn('id', $productIds);
                } elseif ($rating === 0) {
                    // بدون تقييم
                    $ratedProductIds = ProductReview::where('status', 'approved')
                        ->distinct()
                        ->pluck('product_id');
                    $query->whereNotIn('id', $ratedProductIds);
                }
            }

            // فلترة حسب الحد الأدنى للتقييم (4 نجوم وأكثر)
            if ($request->filled('min_rating')) {
                $minRating = (float) $request->min_rating;
                $productIds = ProductReview::where('status', 'approved')
                    ->select('product_id')
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= ?', [$minRating])
                    ->pluck('product_id');
                $query->whereIn('id', $productIds);
            }

            // فلترة حسب خيارات المتغيّرات (لون/مقاس…): options[اللون][]=أزرق
            $this->applyOptionFilters($query, $request->input('options', []));

            // فلترة حسب توفر المخزون — في المستودع الافتراضي (الإعدادات) وحده
            if ($request->filled('in_stock') && $request->in_stock) {
                $defaultShId = $this->defaultStorehouseId();
                $query->where(function ($q) use ($defaultShId) {
                    $q->where('track_inventory', 0)
                        ->orWhereHas('productDetails', function ($pd) use ($defaultShId) {
                            $pd->where('quantity', '>', 0);
                            if ($defaultShId) {
                                $pd->where('store_house_id', $defaultShId);
                            }
                        });
                });
            }

            // الترتيب
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            switch ($sortBy) {
                case 'price_low':
                    $query->orderBy('sale_price', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('sale_price', 'desc');
                    break;
                case 'name':
                    $query->orderBy('name', $sortOrder);
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'featured':
                    $query->orderBy('featured_product', 'desc')
                        ->orderBy('created_at', 'desc');
                    break;
                case 'rating':
                    // ترتيب حسب متوسط التقييم
                    $query->leftJoin('product_reviews', function ($join) {
                        $join->on('products.id', '=', 'product_reviews.product_id')
                            ->where('product_reviews.status', '=', 'approved');
                    })
                        ->select('products.*')
                        ->groupBy('products.id')
                        ->orderByRaw('(AVG(product_reviews.rating) IS NULL) ASC, AVG(product_reviews.rating) DESC');
                    break;
                case 'best_selling':
                    // ترتيب حسب الأكثر مبيعاً
                    $query->leftJoin('invoice_items', 'products.id', '=', 'invoice_items.product_id')
                        ->select('products.*')
                        ->groupBy('products.id')
                        ->orderByRaw('(SUM(invoice_items.quantity) IS NULL) ASC, SUM(invoice_items.quantity) DESC');
                    break;
                default:
                    // قائمةٌ بيضاء — عمودٌ مجهول/مشوَّه كان يُسقط الاستعلام
                    $query->orderBy(in_array($sortBy, ['name', 'sale_price', 'created_at', 'discount'], true) ? $sortBy : 'created_at', $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            $perPage = min((int) $request->get('per_page', 12), 50);
            $products = $query->paginate($perPage);

            // تنسيق المنتجات
            $formattedProducts = $products->getCollection()->map(function ($product) {
                return $this->formatProduct($product);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $formattedProducts,
                    'pagination' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                        'from' => $products->firstItem(),
                        'to' => $products->lastItem(),
                        'has_more_pages' => $products->hasMorePages(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'products');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المنتجات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * جلب بيانات الفلاتر (الأصناف، البراندات، نطاق الأسعار، التقييمات)
     * جميع القيم ديناميكية وقابلة للتغيير من الإعدادات
     */
    public function filters(Request $request): JsonResponse
    {
        try {
            // جلب الإعدادات من الـ Service
            $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
            $filterSettings = OnlineStoreSettingsService::getFilterSettings();

            $currencySymbol = $currencySettings['currency_symbol'];
            $currencyPosition = $currencySettings['currency_position'];
            $maxCategories = $filterSettings['max_categories'];
            $maxBrands = $filterSettings['max_brands'];

            // نطاقات الأسعار من الـ Service
            $priceRangesConfig = OnlineStoreSettingsService::getPriceRanges();

            // أفضل 10 أصناف مع عدد المنتجات (مرتبة حسب عدد المنتجات)
            $categories = Category::where(function ($q) {
                $q->whereNull('main_category')->orWhere('main_category', 0);
            })
                ->withCount(['products' => function ($q) {
                    $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                }])
                ->orderBy('products_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'products_count' => $category->products_count ?? 0,
                    ];
                })->values();

            // أفضل 10 براندات مع عدد المنتجات (مرتبة حسب عدد المنتجات)
            $brands = Brand::where('is_active', 1)
                ->withCount(['products' => function ($q) {
                    $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                }])
                ->orderBy('products_count', 'desc')
                ->limit($maxBrands)
                ->get()
                ->filter(function ($brand) {
                    return $brand->products_count > 0;
                })
                ->map(function ($brand) {
                    return [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'logo' => $this->formatImageUrl($brand->logo),
                        'products_count' => $brand->products_count ?? 0,
                    ];
                })->values();

            // خيارات المتغيّرات المتاحة (اللون/المقاس…) كفلاتر وجهية
            $optionFilters = $this->buildOptionFilters();

            // نطاق الأسعار الفعلي من المنتجات
            $priceStats = Product::where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->selectRaw('MIN(sale_price) as min_price, MAX(sale_price) as max_price')
                ->first();

            // بناء نطاقات الأسعار مع عدد المنتجات
            $priceRanges = $this->buildPriceRanges($priceRangesConfig, $currencySymbol, $currencyPosition);

            // التقييمات - عدد المنتجات لكل تقييم (5 نجوم، 4 نجوم، 3 نجوم، 2 نجمة، 1 نجمة)
            $ratings = $this->getRatingsWithCounts();

            // أفضل 5 منتجات تقييماً
            $topRatedProducts = $this->getTopRatedProducts(5);

            // جلب بيانات المتجر والإعدادات
            $storeInfo = OnlineStoreSettingsService::getStoreInfo();
            $socialLinks = OnlineStoreSettingsService::getSocialSettings();
            $seo = OnlineStoreSettingsService::getSeoSettings();

            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'brands' => $brands,
                    'option_filters' => $optionFilters,
                    'price_range' => [
                        'min' => (float) ($priceStats->min_price ?? 0),
                        'max' => (float) ($priceStats->max_price ?? 0),
                    ],
                    'price_ranges' => $priceRanges,
                    'ratings' => $ratings,
                    'top_rated_products' => $topRatedProducts,
                    'currency' => [
                        'symbol' => $currencySymbol,
                        'position' => $currencyPosition,
                    ],
                    'sort_options' => [
                        ['key' => 'newest', 'label' => 'الأحدث'],
                        ['key' => 'oldest', 'label' => 'الأقدم'],
                        ['key' => 'price_low', 'label' => 'السعر: من الأقل للأعلى'],
                        ['key' => 'price_high', 'label' => 'السعر: من الأعلى للأقل'],
                        ['key' => 'name', 'label' => 'الاسم'],
                        ['key' => 'featured', 'label' => 'المميزة أولاً'],
                        ['key' => 'rating', 'label' => 'التقييم: من الأعلى للأقل'],
                        ['key' => 'best_selling', 'label' => 'الأكثر مبيعاً'],
                    ],
                    'store_info' => $storeInfo,
                    'social_links' => $socialLinks,
                    'seo' => $seo,
                    'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'filters');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الفلاتر',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * الحصول على نطاقات الأسعار الافتراضية بناءً على البيانات الفعلية
     */
    private function getDefaultPriceRanges(): array
    {
        $priceStats = Product::where('available_online', 1)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', 'active')
            ->selectRaw('MIN(sale_price) as min_price, MAX(sale_price) as max_price')
            ->first();

        $minPrice = (float) ($priceStats->min_price ?? 0);
        $maxPrice = (float) ($priceStats->max_price ?? 1000);

        // حساب النطاقات بناءً على الأسعار الفعلية
        $range = $maxPrice - $minPrice;
        $step = $range / 4;

        return [
            ['key' => 'all', 'min' => null, 'max' => null],
            ['key' => 'range_1', 'min' => $minPrice, 'max' => round($minPrice + $step)],
            ['key' => 'range_2', 'min' => round($minPrice + $step), 'max' => round($minPrice + ($step * 2))],
            ['key' => 'range_3', 'min' => round($minPrice + ($step * 2)), 'max' => round($minPrice + ($step * 3))],
            ['key' => 'range_4', 'min' => round($minPrice + ($step * 3)), 'max' => null],
        ];
    }

    /**
     * بناء نطاقات الأسعار مع عدد المنتجات
     */
    private function buildPriceRanges(array $config, string $currencySymbol, string $currencyPosition): array
    {
        $ranges = [];
        $baseQuery = Product::where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');

        foreach ($config as $range) {
            $query = clone $baseQuery;
            $label = '';

            if ($range['min'] === null && $range['max'] === null) {
                // كل الأسعار
                $label = 'الكل';
            } elseif ($range['min'] === null) {
                // أقل من أو يساوي
                $query->where('sale_price', '<=', $range['max']);
                $label = $this->formatPriceLabel($range['max'], $currencySymbol, $currencyPosition, 'lte');
            } elseif ($range['max'] === null) {
                // أكبر من أو يساوي
                $query->where('sale_price', '>=', $range['min']);
                $label = $this->formatPriceLabel($range['min'], $currencySymbol, $currencyPosition, 'gte');
            } else {
                // بين قيمتين
                $query->whereBetween('sale_price', [$range['min'], $range['max']]);
                $label = $this->formatPriceRangeLabel($range['min'], $range['max'], $currencySymbol, $currencyPosition);
            }

            $ranges[] = [
                'key' => $range['key'],
                'label' => $label,
                'min' => $range['min'],
                'max' => $range['max'],
                'count' => $query->count(),
            ];
        }

        return $ranges;
    }

    /**
     * تنسيق تسمية السعر
     */
    private function formatPriceLabel($price, string $symbol, string $position, string $type): string
    {
        $formattedPrice = number_format($price, 0);
        $priceWithSymbol = $position === 'before' ? "{$symbol}{$formattedPrice}" : "{$formattedPrice} {$symbol}";

        return $type === 'lte' ? "{$priceWithSymbol} أو أقل" : "{$priceWithSymbol} أو أكثر";
    }

    /**
     * تنسيق تسمية نطاق السعر
     */
    private function formatPriceRangeLabel($min, $max, string $symbol, string $position): string
    {
        $minFormatted = number_format($min, 0);
        $maxFormatted = number_format($max, 0);

        if ($position === 'before') {
            return "{$symbol}{$minFormatted} - {$symbol}{$maxFormatted}";
        }

        return "{$minFormatted} - {$maxFormatted} {$symbol}";
    }

    /**
     * الحصول على التقييمات مع عدد المنتجات لكل تقييم
     * Get ratings with product counts
     *
     * يحسب عدد المنتجات لكل مستوى تقييم (5-4-3-2-1 نجمة)
     * بناءً على متوسط التقييمات المعتمدة فقط
     */
    private function getRatingsWithCounts(): array
    {
        $ratings = [];

        // حساب متوسط التقييم لكل منتج وتصنيفه (فقط التقييمات المعتمدة)
        $productRatings = ProductReview::where('status', 'approved')
            ->select('product_id', DB::raw('ROUND(AVG(rating)) as avg_rating'))
            ->groupBy('product_id')
            ->get()
            ->groupBy('avg_rating');

        // عدد المنتجات النشطة المتاحة للبيع أونلاين
        $activeProductIds = Product::where('available_online', 1)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        // حساب عدد المنتجات لكل مستوى تقييم من 5 إلى 1 نجمة
        for ($stars = 5; $stars >= 1; $stars--) {
            $productIds = $productRatings->get($stars, collect())->pluck('product_id')->toArray();
            // فقط المنتجات النشطة والمتاحة
            $count = count(array_intersect($productIds, $activeProductIds));

            $ratings[] = [
                'stars' => $stars,
                'label' => $this->getStarsLabel($stars),
                'count' => $count,
            ];
        }

        // إضافة "بدون تقييم" - المنتجات التي لم تحصل على أي تقييم معتمد
        $ratedProductIds = ProductReview::where('status', 'approved')
            ->distinct()
            ->pluck('product_id')
            ->toArray();

        $unratedCount = Product::where('available_online', 1)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', 'active')
            ->whereNotIn('id', $ratedProductIds)
            ->count();

        $ratings[] = [
            'stars' => 0,
            'label' => 'بدون تقييم',
            'count' => $unratedCount,
        ];

        return $ratings;
    }

    /**
     * الحصول على تسمية النجوم
     */
    private function getStarsLabel(int $stars): string
    {
        $labels = [
            5 => '★★★★★ (5 نجوم)',
            4 => '★★★★☆ (4 نجوم)',
            3 => '★★★☆☆ (3 نجوم)',
            2 => '★★☆☆☆ (2 نجمة)',
            1 => '★☆☆☆☆ (1 نجمة)',
        ];

        return $labels[$stars] ?? '';
    }

    /**
     * الحصول على أفضل المنتجات تقييماً
     * Get top rated products
     *
     * يجلب المنتجات ذات التقييم الأعلى بناءً على متوسط التقييمات المعتمدة
     * وعدد التقييمات لضمان مصداقية التقييم
     */
    private function getTopRatedProducts(int $limit = 5): array
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        // جلب المنتجات مع أعلى تقييم
        // يتم الترتيب حسب متوسط التقييم أولاً، ثم عدد التقييمات لضمان الجودة
        $topProducts = ProductReview::where('status', 'approved')
            ->select(
                'product_id',
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('COUNT(*) as reviews_count')
            )
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) >= 1') // على الأقل تقييم واحد
            ->orderBy('avg_rating', 'desc')
            ->orderBy('reviews_count', 'desc')
            ->limit($limit * 3) // نجلب أكثر للتصفية (قد تكون بعض المنتجات غير نشطة)
            ->get();

        $result = [];
        foreach ($topProducts as $item) {
            // التحقق من أن المنتج نشط ومتاح للبيع أونلاين
            $product = Product::where('id', $item->product_id)
                ->where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->with(['category:id,name', 'brand:id,name,logo'])
                ->first();

            if ($product && count($result) < $limit) {
                // استخدام formatProduct للتنسيق الموحد مع باقي المنتجات
                $formattedProduct = $this->formatProduct($product, $currencySymbol, $currencyPosition);
                $result[] = $formattedProduct;
            }

            // إيقاف الحلقة عند الوصول للحد المطلوب
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * الحصول على المنتجات الأكثر مبيعاً
     * Get best selling products
     *
     * يجلب المنتجات الأكثر مبيعاً بناءً على مجموع الكميات المباعة
     * من جدول invoice_items
     */
    private function getBestSellingProducts(int $limit = 8): array
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        // جلب المنتجات الأكثر مبيعاً من جدول invoice_items
        $bestSelling = DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('products.available_online', 1)
            ->where('products.type', ProductType::PRODUCTS->value)
            ->where('products.status', 'active')
            ->select('products.id', DB::raw('SUM(invoice_items.quantity) as total_sold'))
            ->groupBy('products.id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($bestSelling as $item) {
            $product = Product::where('id', $item->id)
                ->with(['category:id,name', 'brand:id,name,logo'])
                ->first();

            if ($product) {
                // استخدام formatProduct للتنسيق الموحد مع باقي المنتجات
                $formattedProduct = $this->formatProduct($product, $currencySymbol, $currencyPosition);
                // إضافة عدد المبيعات الإجمالية
                $formattedProduct['total_sold'] = (int) $item->total_sold;
                $result[] = $formattedProduct;
            }
        }

        return $result;
    }

    /**
     * البحث في المنتجات (Autocomplete)
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $search = $request->get('q', '');

            if (strlen($search) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'products' => [],
                        'categories' => [],
                        'brands' => [],
                    ],
                ]);
            }

            // البحث في المنتجات
            $products = Product::where('available_online', 1)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', 'active')
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
                ->select(['id', 'name', 'sale_price', 'images'])
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->sale_price,
                        'image' => $product->images,
                    ];
                });

            // البحث في الأصناف
            $categories = Category::where('name', 'like', "%{$search}%")
                ->select(['id', 'name'])
                ->limit(3)
                ->get();

            // البحث في البراندات
            $brands = Brand::where('is_active', 1)
                ->where('name', 'like', "%{$search}%")
                ->select(['id', 'name', 'logo'])
                ->limit(3)
                ->get();

            // جلب بيانات المتجر والإعدادات
            $storeInfo = OnlineStoreSettingsService::getStoreInfo();
            $socialLinks = OnlineStoreSettingsService::getSocialSettings();
            $seo = OnlineStoreSettingsService::getSeoSettings();
            $currencySettings = OnlineStoreSettingsService::getCurrencySettings();

            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $products,
                    'categories' => $categories,
                    'brands' => $brands,
                ],
                'currency' => [
                    'symbol' => $currencySettings['currency_symbol'],
                    'position' => $currencySettings['currency_position'],
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'search');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /** مسارٌ غير موجود تحت /api/store — 404 بصيغة المتجر (يُسجَّل آخر مسارات المجموعة). */
    public function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'المسار المطلوب غير موجود',
        ], 404);
    }

    /**
     * جلب إعدادات المتجر
     */
    public function settings(): JsonResponse
    {
        try {
            // جلب جميع الإعدادات من الـ Service
            $settings = OnlineStoreSettingsService::getAllSettingsForApi();

            // الخريطة: مفتاح Google (إعداد المتجر ثم مفتاح النظام) ومركزها الافتراضي —
            // للصفحة الوحيدة التي تحتاجها (تعبئة العنوان) لا لكل استجابة تحمل `settings`
            $settings['maps'] = OnlineStoreSettingsService::getMapsSettings();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'settings');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإعدادات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * جلب الأصناف للقائمة
     */
    public function menuCategories(): JsonResponse
    {
        try {
            $categories = Category::where(function ($q) {
                $q->whereNull('main_category')->orWhere('main_category', 0);
            })
                ->with(['children' => function ($q) {
                    $q->withCount(['products' => function ($q) {
                        $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                    }]);
                }])
                ->withCount(['products' => function ($q) {
                    $q->where('available_online', 1)->where('type', ProductType::PRODUCTS->value)->where('status', 'active');
                }])
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'image' => null,
                        'products_count' => $category->products_count ?? 0,
                        'children' => $category->children->map(function ($child) {
                            return [
                                'id' => $child->id,
                                'name' => $child->name,
                                'products_count' => $child->products_count ?? 0,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'menuCategories');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الأصناف',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * جلب البانرات/الإعلانات
     * Get banners/advertisements
     */
    public function banners(Request $request): JsonResponse
    {
        try {
            $position = $request->get('position'); // home_slider, home_top, sidebar, etc.

            $query = StoreBanner::where('is_active', 1)
                ->where(function ($q) {
                    $q->whereNull('start_date')
                        ->orWhere('start_date', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                });

            if ($position) {
                $query->where('position', $position);
            }

            $banners = $query->orderBy('sort_order', 'asc')
                ->orderBy('created_at', 'desc')
                ->select(['id', 'title', 'description', 'image', 'mobile_image', 'link', 'link_type', 'link_id', 'position', 'sort_order'])
                ->get()
                ->map(function ($banner) {
                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'description' => $banner->description,
                        'image' => $banner->image,
                        'mobile_image' => $banner->mobile_image ?? $banner->image,
                        'link' => $banner->link,
                        'link_type' => $banner->link_type?->value ?? null,
                        'link_id' => $banner->link_id,
                        'position' => $banner->position?->value ?? null,
                        'sort_order' => $banner->sort_order,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $banners,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'banners');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البانرات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تنسيق بيانات المنتج
     *
     * @param  string|null  $currencySymbol  رمز العملة
     * @param  string|null  $currencyPosition  موقع رمز العملة (before/after)
     */
    private function formatProduct(Product $product, ?string $currencySymbol = null, ?string $currencyPosition = null): array
    {
        // جلب إعدادات العملة إذا لم يتم تمريرها
        if ($currencySymbol === null) {
            $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
            $currencySymbol = $currencySettings['currency_symbol'];
        }
        if ($currencyPosition === null) {
            $currencySettings = $currencySettings ?? OnlineStoreSettingsService::getCurrencySettings();
            $currencyPosition = $currencySettings['currency_position'];
        }

        // جلب إعدادات المخزون من الـ Service (تُرجع boolean مباشرة)
        $appearanceSettings = OnlineStoreSettingsService::getAppearanceSettings();
        $allowNegativeStock = $appearanceSettings['allow_negative_stock'];

        // حساب متوسط التقييم من جدول ProductReview
        $avgRating = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->avg('rating');

        // التأكد من أن التقييم رقم حتى لو كان 0
        $avgRating = $avgRating !== null ? (float) $avgRating : 0.0;

        $reviewsCount = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->count();

        // حساب المخزون من مخازن المتجر كلها — لا المستودع الافتراضي وحده
        try {
            $stock = $this->getStoreStockQuantity($product);
        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreHome', 'formatProduct', $product);
            $stock = 0;
        }

        // تظهر الكمية فقط إذا كان البيع بالسالب غير مفعل
        // إذا allow_negative_stock = false → نعرض الكمية
        // إذا allow_negative_stock = true → لا نعرض الكمية
        $shouldShowQuantity = ! $allowNegativeStock;

        // حساب السعر بعد الخصم باستخدام دوال الموديل
        // discount_type: 1 = percentage (نسبة مئوية), 2 = fixed (مبلغ ثابت)
        $originalPrice = (float) $product->sale_price;
        $hasDiscount = $product->hasDiscount();
        $finalPrice = $product->getFinalPrice();
        $discountAmount = $product->getDiscountAmount();

        // تحديد نوع الخصم (percentage أو fixed)
        $discountType = null;
        $discountValue = 0;
        $discountPercentage = 0;

        if ($hasDiscount) {
            $discountValue = (float) $product->discount;

            if ($product->isPercentageDiscount()) {
                $discountType = 'percentage';
                // نسبة الخصم = قيمة الخصم المدخلة (لأنها نسبة مئوية أصلاً)
                $discountPercentage = $discountValue;
            } else {
                $discountType = 'fixed';
                // نسبة الخصم = (قيمة الخصم / سعر البيع) × 100
                $discountPercentage = $originalPrice > 0 ? round(($discountAmount / $originalPrice) * 100, 1) : 0;
            }
        }

        // تنسيق السعر مع العملة
        $formattedPrice = $this->formatPriceWithCurrency($finalPrice, $currencySymbol, $currencyPosition);
        $formattedOriginalPrice = $hasDiscount ? $this->formatPriceWithCurrency($originalPrice, $currencySymbol, $currencyPosition) : null;

        return [
            // البيانات الأساسية
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description ?? '',

            // الصور
            'image' => $this->getFirstImageUrl($product->images),
            'images' => $this->formatImagesUrls($product->images),

            // الأسعار
            'price' => round($finalPrice, 2),
            'price_formatted' => $formattedPrice,
            'original_price' => $hasDiscount ? $originalPrice : null,
            'original_price_formatted' => $formattedOriginalPrice,

            // بيانات الخصم
            'has_discount' => $hasDiscount,
            'discount_type' => $discountType, // 'percentage' أو 'fixed'
            'discount_value' => $hasDiscount ? $discountValue : null, // قيمة الخصم المدخلة (10% أو 50 ر.س)
            'discount_amount' => $hasDiscount ? round($discountAmount, 2) : null, // قيمة الخصم الفعلية بالريال
            'discount_percentage' => $hasDiscount ? $discountPercentage : null, // نسبة الخصم من سعر البيع

            // العملة
            'currency' => [
                'symbol' => $currencySymbol,
                'position' => $currencyPosition,
            ],

            // التصنيف والبراند
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
            ] : null,

            // المخزون
            'in_stock' => $stock > 0 || ! $product->track_inventory,
            'stock_quantity' => $shouldShowQuantity ? $stock : null,
            'show_stock' => $shouldShowQuantity,

            // التقييمات - تظهر دائماً حتى لو 0
            'rating' => round($avgRating, 1),
            'rating_stars' => $this->getRatingStars($avgRating),
            'reviews_count' => $reviewsCount,
            'has_reviews' => $reviewsCount > 0,

            // حالة المنتج
            'is_featured' => (bool) $product->featured_product,
        ];
    }

    /**
     * تنسيق السعر مع العملة
     */
    private function formatPriceWithCurrency(float $price, string $symbol, string $position): string
    {
        $formattedPrice = number_format($price, 2);

        if ($position === 'before') {
            return $symbol.$formattedPrice;
        }

        return $formattedPrice.' '.$symbol;
    }

    /**
     * الحصول على نجوم التقييم كنص
     */
    private function getRatingStars(float $rating): string
    {
        $fullStars = (int) floor($rating);
        $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
        $emptyStars = 5 - $fullStars - $halfStar;

        return str_repeat('★', $fullStars).($halfStar ? '½' : '').str_repeat('☆', $emptyStars);
    }

    /**
     * خيارات المتغيّرات المتاحة (اللون/المقاس…) كفلاتر وجهية — مُجمَّعة حسب
     * التسمية المعروضة فتندمج options باسم `color` و`اللون` ما دامت تسميتهما
     * العربية واحدة، والقيم مُزالٌ منها التكرار.
     * الشكل: [{ name, label, values: [{ value, color_code }] }]
     */
    private function buildOptionFilters()
    {
        return ProductOption::query()
            ->whereHas('product', function ($q) {
                $q->where('available_online', 1)
                    ->where('type', ProductType::PRODUCTS->value)
                    ->where('status', 'active');
            })
            ->with('values')
            ->get()
            ->groupBy(fn ($option) => trim($option->displayLabel()))
            ->map(function ($group, $label) {
                $seen = [];
                $values = [];
                foreach ($group as $option) {
                    foreach ($option->values as $value) {
                        $valueLabel = trim($value->displayLabel());
                        $key = mb_strtolower($valueLabel);
                        if ($valueLabel === '' || isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $values[] = [
                            'value' => $valueLabel,
                            'color_code' => $value->color_code,
                        ];
                    }
                }

                return [
                    'name' => $label,
                    'label' => $label,
                    'values' => $values,
                ];
            })
            ->filter(fn ($facet) => $facet['name'] !== '' && ! empty($facet['values']))
            ->values();
    }

    /**
     * تطبيق فلترة خيارات المتغيّرات على استعلام المنتجات — الشكل:
     *   options[اللون][]=أزرق&options[المقاس][]=L
     * منطقٌ AND بين أسماء الخيارات، OR بين قيم الخيار الواحد.
     */
    private function applyOptionFilters($query, $optionFilters): void
    {
        if (! is_array($optionFilters)) {
            return;
        }

        foreach ($optionFilters as $optName => $vals) {
            $optName = trim((string) $optName);
            $vals = array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), (array) $vals),
                fn ($v) => $v !== ''
            ));

            if ($optName === '' || empty($vals)) {
                continue;
            }

            $valueIds = ProductOptionValue::whereHas('option', function ($oq) use ($optName) {
                $oq->where('name', $optName)
                    ->orWhere('label_ar', $optName)
                    ->orWhere('label_en', $optName);
            })
                ->where(function ($q) use ($vals) {
                    $q->whereIn('value', $vals)
                        ->orWhereIn('label_ar', $vals)
                        ->orWhereIn('label_en', $vals);
                })
                ->pluck('id');

            if ($valueIds->isEmpty()) {
                $query->whereRaw('1 = 0');

                continue;
            }

            $query->whereHas('variants', function ($vq) use ($valueIds) {
                $vq->where('is_active', 1)
                    ->whereHas('optionValues', fn ($ovq) => $ovq->whereIn('product_option_values.id', $valueIds));
            });
        }
    }
}
