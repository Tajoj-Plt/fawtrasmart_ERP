<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Store\ProductReview;
use App\Models\Store\RecentlyViewedProduct;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;

/**
 * متحكم المنتجات المشاهدة مؤخراً
 * Recently Viewed Controller
 *
 * يدير تتبع المنتجات المشاهدة
 */
class StoreRecentlyViewedController extends Controller
{
    use FormatsImages;
    use ResolvesStoreDefaults;

    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     */
    protected function client(): ?Client
    {
        return Auth::guard('client')->user();
    }

    /**
     * عرض المنتجات المشاهدة مؤخراً
     * Get recently viewed products
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 12), 30);
        $client = $this->client();
        $clientId = $client?->id;
        $sessionId = $request->get('session_id');

        if (! $clientId && ! $sessionId) {
            return response()->json([
                'success' => true,
                'products' => [],
            ]);
        }

        $query = RecentlyViewedProduct::with(['product' => function ($q) {
            $q->active()->where('type', ProductType::PRODUCTS->value)->with(['category', 'brand']);
        }]);

        if ($clientId) {
            $query->where('client_id', $clientId);
        } else {
            $query->where('session_id', $sessionId)
                ->whereNull('client_id');
        }

        $recentItems = $query->orderBy('viewed_at', 'desc')
            ->limit($limit)
            ->get();

        $products = $recentItems
            ->filter(fn ($item) => $item->product
                && $item->product->isActive()
                && $item->product->type === ProductType::PRODUCTS->value)
            ->map(fn ($item) => $this->formatProduct($item->product, $item->viewed_at));

        return response()->json([
            'success' => true,
            'products' => $products->values(),
        ]);
    }

    /**
     * إضافة منتج للمشاهدات
     * Add product to recently viewed
     */
    public function store(Request $request): JsonResponse
    {
        $productId = $request->get('product_id');

        if (! $productId) {
            return response()->json([
                'success' => false,
                'message' => 'معرف المنتج مطلوب',
            ], 400);
        }

        $product = Product::active()->find($productId);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود',
            ], 404);
        }

        $client = $this->client();
        $clientId = $client?->id;
        $sessionId = $request->get('session_id');

        if (! $clientId && ! $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى توفير معرف الجلسة',
            ], 400);
        }

        // تحديث أو إنشاء سجل المشاهدة
        $conditions = ['product_id' => $productId];

        if ($clientId) {
            $conditions['client_id'] = $clientId;
            // حذف أي مشاهدات سابقة بنفس session_id لهذا المنتج
            if ($sessionId) {
                RecentlyViewedProduct::where('product_id', $productId)
                    ->where('session_id', $sessionId)
                    ->whereNull('client_id')
                    ->delete();
            }
        } else {
            $conditions['session_id'] = $sessionId;
            $conditions['client_id'] = null;
        }

        RecentlyViewedProduct::updateOrCreate(
            $conditions,
            [
                'viewed_at' => now(),
            ]
        );

        // تحديث عداد المشاهدات على المنتج
        $product->increment('view_count');

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل المشاهدة',
        ]);
    }

    /**
     * حذف منتج من المشاهدات
     * Remove from recently viewed
     */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $client = $this->client();
        $clientId = $client?->id;
        $sessionId = $request->get('session_id');

        $query = RecentlyViewedProduct::where('product_id', $productId);

        if ($clientId) {
            $query->where('client_id', $clientId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تحديد المستخدم',
            ], 400);
        }

        $query->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج من المشاهدات',
        ]);
    }

    /**
     * مسح كل المشاهدات
     * Clear all recently viewed
     */
    public function clear(Request $request): JsonResponse
    {
        $client = $this->client();
        $clientId = $client?->id;
        $sessionId = $request->get('session_id');

        $query = RecentlyViewedProduct::query();

        if ($clientId) {
            $query->where('client_id', $clientId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تحديد المستخدم',
            ], 400);
        }

        $query->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم مسح كل المشاهدات',
        ]);
    }

    /**
     * المنتجات الأكثر مشاهدة
     * Most viewed products
     */
    public function mostViewed(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 12), 30);
        $period = $request->get('period', 'week'); // day, week, month, all

        $query = Product::active()
            ->where('type', ProductType::PRODUCTS->value)
            ->with(['category', 'brand'])
            ->whereHas('recentlyViewed');

        // تصفية حسب الفترة
        if ($period !== 'all') {
            $date = match ($period) {
                'day' => now()->subDay(),
                'week' => now()->subWeek(),
                'month' => now()->subMonth(),
                default => now()->subWeek(),
            };

            // نحتاج للاعتماد على جدول المشاهدات للفترة
            $productIds = RecentlyViewedProduct::select('product_id')
                ->selectRaw('COUNT(*) as total_views')
                ->where('viewed_at', '>=', $date)
                ->groupBy('product_id')
                ->orderByDesc('total_views')
                ->limit($limit)
                ->pluck('product_id');

            $query->whereIn('id', $productIds);
        } else {
            $query->orderByDesc('view_count')
                ->limit($limit);
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'products' => $products->map(fn ($product) => $this->formatProduct($product)),
        ]);
    }

    /**
     * دمج مشاهدات الجلسة مع العميل
     * Merge session views with client
     */
    public function merge(Request $request): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول',
            ], 401);
        }

        $sessionId = $request->get('session_id');

        if (! $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'معرف الجلسة مطلوب',
            ], 400);
        }

        // الحصول على مشاهدات الجلسة
        $sessionViews = RecentlyViewedProduct::where('session_id', $sessionId)
            ->whereNull('client_id')
            ->get();

        foreach ($sessionViews as $view) {
            // التحقق من وجود مشاهدة سابقة للعميل
            $existingView = RecentlyViewedProduct::where('client_id', $client->id)
                ->where('product_id', $view->product_id)
                ->first();

            if ($existingView) {
                // تحديث المشاهدة الموجودة بأحدث تاريخ
                if ($view->viewed_at > $existingView->viewed_at) {
                    $existingView->update([
                        'viewed_at' => $view->viewed_at,
                    ]);
                }
            } else {
                // نقل المشاهدة للعميل
                $view->update([
                    'client_id' => $client->id,
                    'session_id' => null,
                ]);

                continue;
            }

            // حذف مشاهدة الجلسة
            $view->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم دمج المشاهدات',
        ]);
    }

    /**
     * تنسيق المنتج
     */
    private function formatProduct(Product $product, $viewedAt = null): array
    {
        // جلب إعدادات العملة
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();
        $currencySymbol = $currencySettings['currency_symbol'];
        $currencyPosition = $currencySettings['currency_position'];

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
            'has_variants' => (bool) $product->has_variants,
            'variants_count' => $product->has_variants ? $product->variants()->count() : 0,
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
            'viewed_at' => $viewedAt?->toDateTimeString(),
            'currency' => [
                'symbol' => $currencySymbol,
                'position' => $currencyPosition,
            ],
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
}
