<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store\Brand;
use App\Models\Store\OnlineStoreSetting;
use App\Models\Store\ProductOptionValue;
use App\Models\Store\ProductReview;
use App\Models\Store\RecentlyViewedProduct;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;
use Modules\OnlineStore\Http\Controllers\Api\Traits\ResolvesStoreSession;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;

/**
 * متحكم المنتجات للمتجر الإلكتروني
 * Store Products Controller
 *
 * يدير عرض المنتجات والبحث والتصفية
 */
class StoreProductController extends Controller
{
    use FormatsImages;
    use ResolvesStoreDefaults;
    use ResolvesStoreSession;

    /**
     * عرض قائمة المنتجات
     * List products with filters
     */
    public function index(Request $request): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $query = Product::query()
            ->where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE);

        // البحث بالاسم أو الوصف
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // تصفية حسب الفئة
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // تصفية حسب البراند
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // تصفية حسب السعر
        if ($request->filled('min_price')) {
            $query->where('sale_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('sale_price', '<=', $request->max_price);
        }

        // تصفية حسب نطاق السعر المحدد مسبقاً (من الإعدادات)
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

        // تصفية حسب التقييم (نجوم محددة)
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

        // تصفية حسب الحد الأدنى للتقييم
        if ($request->filled('min_rating')) {
            $minRating = (float) $request->min_rating;
            $productIds = ProductReview::where('status', 'approved')
                ->select('product_id')
                ->groupBy('product_id')
                ->havingRaw('AVG(rating) >= ?', [$minRating])
                ->pluck('product_id');
            $query->whereIn('id', $productIds);
        }

        // المنتجات المميزة فقط
        if ($request->boolean('featured')) {
            $query->where('featured_product', true);
        }

        // المنتجات التي عليها خصم
        if ($request->boolean('on_sale')) {
            $query->where('discount', '>', 0);
        }

        // تصفية حسب خيارات المتغيّرات (لون/مقاس…) —
        //   options[اللون][]=أزرق&options[المقاس][]=L
        // AND بين أسماء الخيارات، OR بين قيم الخيار الواحد.
        $optionFilters = $request->input('options', []);
        if (is_array($optionFilters)) {
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

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['name', 'sale_price', 'created_at', 'discount'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // التحميل المسبق للعلاقات
        $query->with(['category:id,name', 'brand:id,name,logo']);

        // التصفح
        $perPage = min($request->get('per_page', 20), 50);
        $products = $query->paginate($perPage);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($product) => $this->formatProductList($product, $currencySymbol, $currencyPosition)),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * عرض تفاصيل منتج
     * Show product details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $product = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->with([
                'category:id,name',
                'brand:id,name,logo,description',
                'productDetails' => function ($query) {
                    $query->where('quantity', '>', 0);
                },
                'options.values',
                'variants.optionValues',
            ])
            ->find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود',
            ], 404);
        }

        // تسجيل المشاهدة
        $clientId = Auth::guard('client')->user()?->id;
        $sessionId = $this->storeSessionId($request);
        RecentlyViewedProduct::addView($product->id, $clientId, $sessionId);

        // الحصول على التقييمات
        $reviews = ProductReview::where('product_id', $product->id)
            ->approved()
            ->with('client:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // الحصول على منتجات مشابهة
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->limit(8)
            ->get()
            ->map(fn ($p) => $this->formatProductList($p, $currencySymbol, $currencyPosition));

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $this->formatProductDetails($product, $currencySymbol, $currencyPosition),
                'options' => $this->formatProductOptions($product),
                'variants' => $this->formatProductVariants($product, $currencySymbol, $currencyPosition),
                'has_variants' => $product->hasVariants(),
                'reviews' => [
                    'items' => $reviews->map(fn ($review) => [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'title' => $review->title,
                        'comment' => $review->comment,
                        'client_name' => $review->client?->first_name ?? 'عميل',
                        'is_verified_purchase' => $review->is_verified_purchase,
                        'helpful_count' => $review->helpful_count,
                        'created_at' => $review->created_at->toDateTimeString(),
                    ]),
                    'average_rating' => $product->average_rating ?? 0,
                    'total_reviews' => $product->reviews_count ?? 0,
                ],
                'related_products' => $relatedProducts,
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * منتجات مشابهة (من فئة المنتج نفسها) — المسار كان مسجَّلاً بلا دالة فيسقط بـ500.
     * Related products
     */
    public function related(Request $request, int $productId): JsonResponse
    {
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $limit = max(1, min((int) $request->query('limit', 8), 20));

        $product = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->find($productId);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود',
            ], 404);
        }

        $products = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->with(['category:id,name', 'brand:id,name,logo'])
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($p) => $this->formatProductList($p, $currencySettings['currency_symbol'], $currencySettings['currency_position'])),
                'currency' => [
                    'symbol' => $currencySettings['currency_symbol'],
                    'position' => $currencySettings['currency_position'],
                ],
            ],
        ]);
    }

    /**
     * المنتجات المميزة
     * Featured products
     */
    public function featured(Request $request): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $limit = min($request->get('limit', 10), 20);

        $products = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->where('featured_product', true)
            ->with(['category:id,name', 'brand:id,name,logo'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($product) => $this->formatProductList($product, $currencySymbol, $currencyPosition)),
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * المنتجات الجديدة
     * New arrivals
     */
    public function newArrivals(Request $request): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $limit = min($request->get('limit', 10), 20);
        $days = $request->get('days', 30);

        $products = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['category:id,name', 'brand:id,name,logo'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($product) => $this->formatProductList($product, $currencySymbol, $currencyPosition)),
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * المنتجات الأكثر مبيعاً
     * Best sellers
     */
    public function bestSellers(Request $request): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $limit = min($request->get('limit', 10), 20);

        $products = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->with(['category:id,name', 'brand:id,name,logo'])
            ->withCount(['invoice_items as total_sold' => function ($query) {
                $query->whereHas('invoice', function ($q) {
                    $q->where('type', 'sales');
                });
            }])
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($product) => $this->formatProductList($product, $currencySymbol, $currencyPosition)),
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * المنتجات التي عليها خصم
     * Products on sale
     */
    public function onSale(Request $request): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $perPage = min($request->get('per_page', 20), 50);

        $products = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->where('discount', '>', 0)
            ->with(['category:id,name', 'brand:id,name,logo'])
            ->orderBy('discount', 'desc')
            ->paginate($perPage);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->map(fn ($product) => $this->formatProductList($product, $currencySymbol, $currencyPosition)),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * منتجات فئة معينة
     * Products by category
     */
    public function byCategory(Request $request, int $categoryId): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $category = Category::find($categoryId);

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'الفئة غير موجودة',
            ], 404);
        }

        $perPage = min($request->get('per_page', 20), 50);

        $products = Product::where('category_id', $categoryId)
            ->where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->with(['brand:id,name,logo'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
                'products' => $products->map(fn ($product) => $this->formatProductList($product, $currencySymbol, $currencyPosition)),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * منتجات براند معين
     * Products by brand
     */
    public function byBrand(Request $request, int $brandId): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $brand = Brand::find($brandId);

        if (! $brand) {
            return response()->json([
                'success' => false,
                'message' => 'البراند غير موجود',
            ], 404);
        }

        $perPage = min($request->get('per_page', 20), 50);

        $products = Product::where('brand_id', $brandId)
            ->where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->with(['category:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'logo' => $this->formatImageUrl($brand->logo),
                    'description' => $brand->description,
                ],
                'products' => $products->map(fn ($product) => $this->formatProductList($product, $currencySymbol, $currencyPosition)),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * البحث التلقائي (Autocomplete)
     */
    public function autocomplete(Request $request): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        $products = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name', 'sale_price', 'images')
            ->limit(10)
            ->get();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->getFinalPrice(),
                    'price_formatted' => $this->formatPriceWithCurrency($product->getFinalPrice(), $currencySymbol, $currencyPosition),
                    'image' => $this->getFirstImageUrl($product->images),
                ]),
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
            ],
        ]);
    }

    /**
     * الحصول على الفلاتر المتاحة
     * Get available filters
     */
    public function filters(Request $request): JsonResponse
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

        // الفئات
        $categories = Category::whereHas('products', function ($query) {
            $query->where('available_online', true)
                ->where('type', ProductType::PRODUCTS->value)
                ->where('status', ProductStatus::ACTIVE);
        })
            ->select('id', 'name')
            ->get();

        // البراندات
        $brands = Brand::active()
            ->whereHas('products', function ($query) {
                $query->where('available_online', true)
                    ->where('type', ProductType::PRODUCTS->value)
                    ->where('status', ProductStatus::ACTIVE);
            })
            ->select('id', 'name', 'logo')
            ->ordered()
            ->get()
            ->map(fn ($brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo' => $this->formatImageUrl($brand->logo),
            ]);

        // نطاق الأسعار
        $priceRange = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->selectRaw('MIN(sale_price) as min_price, MAX(sale_price) as max_price')
            ->first();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'data' => [
                'filters' => [
                    'categories' => $categories,
                    'brands' => $brands,
                    'price_range' => [
                        'min' => $priceRange->min_price ?? 0,
                        'max' => $priceRange->max_price ?? 0,
                    ],
                ],
                'currency' => [
                    'symbol' => $currencySymbol,
                    'position' => $currencyPosition,
                ],
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ],
        ]);
    }

    /**
     * تنسيق بيانات المنتج للقائمة
     */
    private function formatProductList(Product $product, ?string $currencySymbol = null, ?string $currencyPosition = null): array
    {
        // جلب إعدادات العملة إذا لم يتم تمريرها
        if ($currencySymbol === null) {
            $currencySymbol = OnlineStoreSettingsService::getCurrencySymbol();
        }
        if ($currencyPosition === null) {
            $currencyPosition = OnlineStoreSettingsService::getCurrencyPosition();
        }

        // جلب إعدادات المخزون
        $appearanceSettings = OnlineStoreSettingsService::getAppearanceSettings();
        $allowNegativeStock = $appearanceSettings['allow_negative_stock'];
        $shouldShowQuantity = ! $allowNegativeStock;

        $originalPrice = $product->sale_price;
        $finalPrice = $product->getFinalPrice();
        $hasDiscount = $product->hasDiscount();

        // حساب الكمية من مخازن المتجر كلها — لا المستودع الافتراضي وحده
        $totalQuantity = $this->getStoreStockQuantity($product);

        // حساب التقييمات
        $avgRating = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->avg('rating');
        $avgRating = $avgRating !== null ? (float) $avgRating : 0.0;

        $reviewsCount = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->count();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->sku_online ?? $product->id,
            'description' => $product->description ?? '',
            'image' => $this->getFirstImageUrl($product->images),
            'images' => $this->formatImagesUrls($product->images),
            'price' => round($finalPrice, 2),
            'price_formatted' => $this->formatPriceWithCurrency($finalPrice, $currencySymbol, $currencyPosition),
            'original_price' => $hasDiscount ? round($originalPrice, 2) : null,
            'original_price_formatted' => $hasDiscount ? $this->formatPriceWithCurrency($originalPrice, $currencySymbol, $currencyPosition) : null,
            'discount' => $product->discount ?? 0,
            'discount_type' => $product->isPercentageDiscount() ? 'percentage' : 'fixed',
            'discount_amount' => $hasDiscount ? round($product->getDiscountAmount(), 2) : null,
            'has_discount' => $hasDiscount,
            'is_featured' => (bool) $product->featured_product,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
            ] : null,
            'in_stock' => $totalQuantity > 0 || ! $product->track_inventory,
            'stock_quantity' => $shouldShowQuantity ? $totalQuantity : null,
            'show_stock' => $shouldShowQuantity,
            'rating' => round($avgRating, 1),
            'average_rating' => round($avgRating, 1),
            'reviews_count' => $reviewsCount,
            'has_reviews' => $reviewsCount > 0,
            'currency' => [
                'symbol' => $currencySymbol,
                'position' => $currencyPosition,
            ],
        ];
    }

    /**
     * تنسيق بيانات المنتج التفصيلية
     */
    private function formatProductDetails(Product $product, ?string $currencySymbol = null, ?string $currencyPosition = null): array
    {
        // جلب إعدادات العملة إذا لم يتم تمريرها
        if ($currencySymbol === null) {
            $currencySymbol = OnlineStoreSettingsService::getCurrencySymbol();
        }
        if ($currencyPosition === null) {
            $currencyPosition = OnlineStoreSettingsService::getCurrencyPosition();
        }

        // جلب إعدادات المخزون
        $allowNegativeStockValue = OnlineStoreSetting::get('allow_negative_stock', 'false');
        $allowNegativeStock = filter_var($allowNegativeStockValue, FILTER_VALIDATE_BOOLEAN);
        $shouldShowQuantity = ! $allowNegativeStock;

        $originalPrice = $product->sale_price;
        $finalPrice = $product->getFinalPrice();
        $hasDiscount = $product->hasDiscount();

        // حساب الكمية من مخازن المتجر كلها — لا مخزون النظام كله (وإلا ظهر
        // منتجٌ "متوفر" برصيدٍ جالسٍ في مستودع فرعٍ أو عهدة موظف لا صلة له
        // بالمتجر)، ولا المستودع الافتراضي وحده (وإلا اختفى منتجٌ رصيده في
        // مستودعٍ آخر تابعٍ للمتجر فعلاً لكنه ليس الافتراضي تحديداً)
        $totalQuantity = $this->getStoreStockQuantity($product);

        // حساب التقييمات
        $avgRating = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->avg('rating');
        $avgRating = $avgRating !== null ? (float) $avgRating : 0.0;

        $reviewsCount = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->count();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->sku_online ?? $product->id,
            'description' => $product->description,
            'images' => $this->formatImagesUrls($product->images),
            'price' => round($finalPrice, 2),
            'price_formatted' => $this->formatPriceWithCurrency($finalPrice, $currencySymbol, $currencyPosition),
            'original_price' => $hasDiscount ? round($originalPrice, 2) : null,
            'original_price_formatted' => $hasDiscount ? $this->formatPriceWithCurrency($originalPrice, $currencySymbol, $currencyPosition) : null,
            'discount' => $product->discount ?? 0,
            'discount_type' => $product->isPercentageDiscount() ? 'percentage' : 'fixed',
            'discount_amount' => $hasDiscount ? round($product->getDiscountAmount(), 2) : null,
            'has_discount' => $hasDiscount,
            'is_featured' => $product->featured_product,
            'category' => [
                'id' => $product->category?->id,
                'name' => $product->category?->name,
            ],
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'logo' => $this->formatImageUrl($product->brand->logo),
            ] : null,
            'in_stock' => $totalQuantity > 0 || ! $product->track_inventory,
            'stock_quantity' => $shouldShowQuantity ? $totalQuantity : null,
            'show_stock' => $shouldShowQuantity,
            'sku' => $product->sku_online ?? $product->barcode,
            'barcode' => $product->barcode_online ?? $product->barcode,
            'weight' => $product->weight ? (float) $product->weight : null,
            'dimensions' => $this->formatDimensionsForApi($product->dimensions),
            'is_returnable' => $product->is_returnable,
            'return_days' => $product->return_days,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'average_rating' => round($avgRating, 1),
            'reviews_count' => $reviewsCount,
            'currency' => [
                'symbol' => $currencySymbol,
                'position' => $currencyPosition,
            ],
            'created_at' => $product->created_at?->toDateTimeString(),
        ];
    }

    /**
     * تنسيق خيارات المنتج (مثال: اللون، المقاس) وقيمها المتاحة
     * للاستخدام في بناء مُنتقي الخيارات (option picker) بواجهة المتجر
     */
    private function formatProductOptions(Product $product): array
    {
        return $product->options->map(fn ($option) => [
            'id' => $option->id,
            'name' => $option->name,
            'label' => $option->displayLabel(),
            'values' => $option->values->map(fn ($value) => [
                'id' => $value->id,
                'value' => $value->value,
                'label' => $value->displayLabel(),
                'color_code' => $value->color_code,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * تنسيق متغيّرات المنتج (تركيبات الخيارات) — تتضمن `option_value_ids`
     * ليتمكن العميل (الواجهة) من مطابقة تركيبة الخيارات المختارة بالمتغيّر
     * الصحيح دون استعلامٍ إضافي
     */
    private function formatProductVariants(Product $product, ?string $currencySymbol = null, ?string $currencyPosition = null): array
    {
        if ($currencySymbol === null) {
            $currencySymbol = OnlineStoreSettingsService::getCurrencySymbol();
        }
        if ($currencyPosition === null) {
            $currencyPosition = OnlineStoreSettingsService::getCurrencyPosition();
        }

        // المتغيّرات لا تملك مخزوناً مستقلاً — تشترك كلّها في مخزون المنتج
        // الأساسي في مستودعات المتجر. فحقلا stock_quantity و in_stock هنا
        // انعكاسٌ لحالة المنتج، لا لعمود stock_quantity في صفّ المتغيّر
        // (يبقى العمود في قاعدة البيانات لكن لا يُقرأ للتوفّر ولا للخصم).
        $allowNegativeStock = filter_var(OnlineStoreSetting::get('allow_negative_stock', 'false'), FILTER_VALIDATE_BOOLEAN);
        $productQuantity = $this->getStoreStockQuantity($product);
        $productInStock = $productQuantity > 0 || ! $product->track_inventory;

        return $product->variants->map(function ($variant) use ($currencySymbol, $currencyPosition, $productQuantity, $productInStock, $allowNegativeStock) {
            $finalPrice = $variant->finalPrice();

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'image' => $this->formatImageUrl($variant->image_url),
                'variant_options' => $variant->variant_options,
                'label' => $variant->displayLabel(),
                'option_value_ids' => $variant->optionValues->pluck('id')->values()->all(),
                'price' => round($finalPrice, 2),
                'price_formatted' => $this->formatPriceWithCurrency($finalPrice, $currencySymbol, $currencyPosition),
                'compare_at_price' => $variant->compare_at_price !== null ? round((float) $variant->compare_at_price, 2) : null,
                'stock_quantity' => $allowNegativeStock ? null : $productQuantity,
                'in_stock' => $productInStock,
                'is_active' => (bool) $variant->is_active,
            ];
        })->values()->all();
    }

    /**
     * حساب السعر بعد الخصم
     * يستخدم الدوال المعرفة في موديل Product لضمان التوحيد
     * discount_type: 1 = percentage (نسبة مئوية), 2 = fixed (مبلغ ثابت)
     */
    private function calculateDiscountedPrice(Product $product): float
    {
        return $product->getFinalPrice();
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
     * تنسيق الأبعاد للـ API
     *
     * @param  mixed  $dimensions
     */
    private function formatDimensionsForApi($dimensions): ?array
    {
        if (empty($dimensions)) {
            return null;
        }

        // إذا كانت الأبعاد مخزنة كـ array (JSON)
        if (is_array($dimensions) && isset($dimensions['length'], $dimensions['width'], $dimensions['height'])) {
            return [
                'length' => (float) $dimensions['length'],
                'width' => (float) $dimensions['width'],
                'height' => (float) $dimensions['height'],
                'unit' => 'cm',
                'formatted' => $dimensions['length'].'x'.$dimensions['width'].'x'.$dimensions['height'].' cm',
            ];
        }

        return null;
    }
}
