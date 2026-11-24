<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Product;
use App\Models\Store\Wishlist;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;

/**
 * متحكم قائمة الأمنيات للمتجر الإلكتروني
 * Store Wishlist Controller
 *
 * يدير قائمة المنتجات المفضلة للعملاء
 */
class StoreWishlistController extends Controller
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
     * عرض قائمة الأمنيات
     * Get wishlist
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
                'wishlist' => [],
                'total' => 0,
            ], 401);
        }

        $wishlistItems = Wishlist::forClient($client->id)
            ->with(['product' => function ($query) {
                $query->select('id', 'name', 'sale_price', 'discount', 'discount_type', 'images', 'available_online', 'type');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // تصفية المنتجات وتنسيقها
        $formattedItems = $wishlistItems->map(function ($item) {
            return $this->formatWishlistItem($item);
        })->filter(function ($item) {
            // يمكن اختيار حذف العناصر التي تم حذف منتجاتها أو إبقائها
            // return $item['product'] !== null; // لحذف العناصر المحذوفة
            return true; // لإبقاء جميع العناصر
        })->values();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();

        return response()->json([
            'success' => true,
            'wishlist' => $formattedItems,
            'total' => $formattedItems->count(),
            'currency' => [
                'symbol' => $currencySettings['currency_symbol'],
                'position' => $currencySettings['currency_position'],
            ],
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * إضافة منتج للمفضلة
     * Add product to wishlist
     */
    public function add(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'bail|required|integer|exists:products,id',
            'notes' => 'nullable|string|max:500',
        ], [
            'product_id.required' => 'المنتج مطلوب',
            'product_id.exists' => 'المنتج غير موجود',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
            ], 401);
        }

        $product = Product::find($request->product_id);

        // التحقق من وجود المنتج
        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود',
            ], 404);
        }

        if (! $product->available_online) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير متاح للإضافة للمفضلة',
            ], 400);
        }

        if ($product->type !== ProductType::PRODUCTS->value) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير متاح للإضافة للمفضلة',
            ], 400);
        }

        // التحقق من وجود المنتج مسبقاً
        if (Wishlist::isInWishlist($client->id, $request->product_id)) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج موجود مسبقاً في المفضلة',
            ], 400);
        }

        $wishlistItem = Wishlist::addToWishlist(
            $client->id,
            $request->product_id,
            $request->notes
        );

        $wishlistItem->load('product');

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة المنتج للمفضلة',
            'item' => $this->formatWishlistItem($wishlistItem),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ], 201);
    }

    /**
     * حذف منتج من المفضلة
     * Remove product from wishlist
     */
    public function remove(Request $request, int $productId): JsonResponse
    {
        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
            ], 401);
        }

        $removed = Wishlist::removeFromWishlist($client->id, $productId);

        if (! $removed) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود في المفضلة',
            ], 404);
        }

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج من المفضلة',
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * التحقق من وجود منتج في المفضلة
     * Check if product is in wishlist
     */
    public function check(Request $request, int $productId): JsonResponse
    {
        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
                'is_in_wishlist' => false,
            ], 401);
        }

        // التحقق من وجود المنتج أولاً
        $product = Product::find($productId);
        if (! $product) {
            return response()->json([
                'success' => true,
                'is_in_wishlist' => false,
            ]);
        }

        $isInWishlist = Wishlist::isInWishlist($client->id, $productId);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'is_in_wishlist' => $isInWishlist,
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * التحقق من عدة منتجات
     * Check multiple products
     */
    public function checkMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'bail|integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
                'wishlist_product_ids' => [],
            ], 401);
        }

        // جلب فقط المنتجات الموجودة (غير المحذوفة)
        $wishlistProductIds = Wishlist::forClient($client->id)
            ->whereIn('product_id', $request->product_ids)
            ->whereHas('product') // فقط العناصر التي لها منتجات موجودة
            ->pluck('product_id')
            ->toArray();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'wishlist_product_ids' => $wishlistProductIds,
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * مسح قائمة الأمنيات
     * Clear wishlist
     */
    public function clear(Request $request): JsonResponse
    {
        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
            ], 401);
        }

        Wishlist::forClient($client->id)->delete();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم مسح قائمة الأمنيات',
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * نقل منتج من المفضلة إلى السلة
     * Move item from wishlist to cart
     */
    public function moveToCart(Request $request, int $productId): JsonResponse
    {
        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
            ], 401);
        }

        $wishlistItem = Wishlist::forProduct($client->id, $productId)->first();

        if (! $wishlistItem) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود في المفضلة',
            ], 404);
        }

        $product = $wishlistItem->product;

        if (! $product || ! $product->available_online || $product->type !== ProductType::PRODUCTS->value) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير متاح للشراء',
            ], 400);
        }

        // التحقق من توفر الكمية في مخازن المتجر كلها (إلا إذا كان البيع بالسالب مسموحاً)
        $allowNegativeStock = OnlineStoreSettingsService::get('allow_negative_stock', false);
        $availableQuantity = 0;

        if (! $allowNegativeStock) {
            $availableQuantity = $this->getStoreStockQuantity($product);
        }

        if (! $allowNegativeStock && $availableQuantity <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير متوفر حالياً',
            ], 400);
        }

        // إضافة للسلة باستخدام CartController
        // ملاحظة: جدول wishlists لا يملك عمود variant_id حالياً (تحقّقنا من الترحيل)،
        // فهذا التمرير يبقى بلا أثر إلى أن يُضاف تتبّع المتغيّر لعناصر المفضلة
        $cartController = new StoreCartController;
        $cartData = [
            'product_id' => $productId,
            'quantity' => 1,
        ];

        if (! empty($wishlistItem->variant_id)) {
            $cartData['variant_id'] = $wishlistItem->variant_id;
        }

        $cartRequest = new Request($cartData);
        $cartRequest->setUserResolver(fn () => $client);

        $response = $cartController->addItem($cartRequest);
        $responseData = json_decode($response->getContent(), true);

        if (! $responseData['success']) {
            return $response;
        }

        // حذف من المفضلة
        $wishlistItem->delete();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم نقل المنتج إلى السلة',
            'cart' => $responseData['cart'],
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * الحصول على عدد عناصر المفضلة
     * Get wishlist count
     */
    public function count(Request $request): JsonResponse
    {
        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => true,
                'count' => 0,
            ]);
        }

        $count = Wishlist::forClient($client->id)->count();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'count' => $count,
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * الحصول على IDs المنتجات في المفضلة
     * Get wishlist product IDs
     */
    public function getProductIds(Request $request): JsonResponse
    {
        $client = $this->client();

        // التحقق من وجود العميل
        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح',
                'product_ids' => [],
            ], 401);
        }

        // جلب IDs المنتجات فقط التي لم يتم حذفها
        $productIds = Wishlist::forClient($client->id)
            ->whereHas('product') // فقط العناصر التي لها منتجات موجودة
            ->pluck('product_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'product_ids' => $productIds,
        ]);
    }

    /**
     * تنسيق بيانات عنصر المفضلة
     */
    private function formatWishlistItem(Wishlist $item): array
    {
        $product = $item->product;

        // إذا كان المنتج محذوف، نرجع بيانات محدودة
        if (! $product) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => null,
                'notes' => $item->notes,
                'added_at' => $item->created_at->toDateTimeString(),
                'product_deleted' => true,
            ];
        }

        // جلب إعدادات المخزون (نفس منطق StoreProductController)
        $appearanceSettings = OnlineStoreSettingsService::getAppearanceSettings();
        $allowNegativeStock = $appearanceSettings['allow_negative_stock'];
        $shouldShowQuantity = ! $allowNegativeStock;

        $originalPrice = $product->sale_price ?? 0;
        $discountedPrice = $this->calculateDiscountedPrice($product);
        $hasDiscount = $product->hasDiscount() ?? false;

        // حساب الكمية من مخازن المتجر كلها — نفس منطق StoreProductController
        $totalQuantity = $this->getStoreStockQuantity($product);

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $this->getFirstImageUrl($product->images),
                'price' => $discountedPrice,
                'original_price' => $hasDiscount ? $originalPrice : $discountedPrice,
                'discount' => $product->discount ?? 0,
                'discount_type' => $product->isPercentageDiscount() ? 'percentage' : 'fixed',
                'discount_amount' => $product->getDiscountAmount(),
                'has_discount' => $hasDiscount,
                'available_online' => $product->available_online,
                'in_stock' => $totalQuantity > 0 || ! $product->track_inventory,
                'stock_quantity' => $shouldShowQuantity ? $totalQuantity : null,
                'show_stock' => $shouldShowQuantity,
            ],
            'notes' => $item->notes,
            'added_at' => $item->created_at->toDateTimeString(),
            'product_deleted' => false,
        ];
    }

    /**
     * حساب السعر بعد الخصم
     * يستخدم الدوال المعرفة في موديل Product
     * discount_type: 1 = percentage, 2 = fixed
     */
    private function calculateDiscountedPrice(?Product $product): float
    {
        if (! $product) {
            return 0;
        }

        return $product->getFinalPrice();
    }
}
