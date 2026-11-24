<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\RecipientType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Product;
use App\Models\Store\Cart;
use App\Models\Store\ClientPaymentCard;
use App\Models\Store\ProductVariant;
use App\Models\Store\StoreCoupon;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;
use Modules\OnlineStore\Http\Controllers\Api\Traits\ResolvesStoreSession;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;

/**
 * متحكم سلة التسوق للمتجر الإلكتروني
 * Store Cart Controller
 *
 * يدير عمليات سلة التسوق للعملاء
 */
class StoreCartController extends Controller
{
    use FormatsImages;
    use ResolvesStoreDefaults;
    use ResolvesStoreSession;

    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     * يستخدم طرق متعددة للحصول على العميل:
     * 1. من Auth::guard('client') - للمصادقة العادية
     * 2. من request()->user() - لـ Sanctum token
     * 3. من authenticated_client - من middleware
     * 4. من Bearer token مباشرة
     */
    protected function client(?Request $request = null): ?Client
    {
        // Method 1: Try Auth guard first
        $client = Auth::guard('client')->user();
        if ($client instanceof Client) {
            return $client;
        }

        // Method 2: Try request user (Sanctum auto-detection)
        $request = $request ?? request();
        $user = $request->user();
        if ($user instanceof Client) {
            return $user;
        }

        // Method 3: Check if middleware set the client
        $authenticatedClient = $request->get('authenticated_client');
        if ($authenticatedClient instanceof Client) {
            return $authenticatedClient;
        }

        // Method 4: Try to resolve from Bearer token manually
        $token = $request->bearerToken();
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->tokenable instanceof Client) {
                return $accessToken->tokenable;
            }
        }

        return null;
    }

    /**
     * عرض سلة التسوق
     * Get cart
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        // إعادة حساب الإجماليات لضمان دقة القيم (خاصة عند وجود كوبون)
        if ($cart->items()->count() > 0) {
            $cart->recalculateTotal();
            $cart->refresh();
        }

        // Debug log للتحقق من قيم الخصم
        \Log::info('Cart Data Debug', [
            'cart_id' => $cart->id,
            'coupon_id' => $cart->coupon_id,
            'subtotal' => $cart->subtotal,
            'discount' => $cart->discount,
            'tax' => $cart->tax,
            'total' => $cart->total,
            'has_coupon' => $cart->coupon ? true : false,
            'coupon_value' => $cart->coupon ? $cart->coupon->value : null,
            'coupon_type' => $cart->coupon ? $cart->coupon->type : null,
        ]);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();

        // جلب طرق الدفع المفعلة من الإعدادات
        $enabledPaymentMethodIds = OnlineStoreSettingsService::getEnabledPaymentMethods();
        $defaultPaymentMethodId = OnlineStoreSettingsService::getDefaultPaymentMethodId();

        // جلب طرق الدفع النشطة والمفعلة من الإعدادات
        $paymentMethodsQuery = OnlineStoreSettingsService::storePaymentMethodsQuery()->ordered();

        // إذا كانت هناك طرق دفع محددة في الإعدادات، نفلتر بها
        if (! empty($enabledPaymentMethodIds)) {
            $paymentMethodsQuery->whereIn('id', $enabledPaymentMethodIds);
        }

        $paymentMethods = $paymentMethodsQuery->get()
            ->map(function ($method) use ($defaultPaymentMethodId) {
                return [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->name,
                    'name_en' => $method->name_en,
                    'description' => $method->description,
                    'icon' => $method->icon,
                    'logo' => $method->logo ? url($method->logo) : null,
                    'type' => $method->type,
                    'fee_type' => $method->fee_type,
                    'fee_amount' => (float) $method->fee_amount,
                    'min_amount' => $method->min_amount ? (float) $method->min_amount : null,
                    'max_amount' => $method->max_amount ? (float) $method->max_amount : null,
                    'requires_card' => (bool) $method->requires_card,
                    'requires_bank_details' => (bool) $method->requires_bank_details,
                    'instructions' => $method->instructions,
                    'bank_name' => $method->bank_name,
                    'bank_account_name' => $method->bank_account_name,
                    'bank_account_number' => $method->bank_account_number,
                    'bank_iban' => $method->bank_iban,
                    'is_default' => $method->id == $defaultPaymentMethodId,
                ];
            })
            ->toArray();

        // جلب بطاقات الدفع المحفوظة للعميل (إن كان مسجلاً)
        $savedCards = [];
        $client = $this->client($request);
        if ($client) {
            $savedCards = ClientPaymentCard::where('client_id', $client->id)
                ->active()
                ->valid()
                ->with('paymentMethod:id,name,icon,type')
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->get()
                ->map(fn ($card) => [
                    'id' => $card->id,
                    'card_type' => $card->card_type,
                    'card_type_name' => $card->card_type_name,
                    'card_icon' => $card->card_icon,
                    'card_holder_name' => $card->card_holder_name,
                    'masked_number' => $card->masked_number,
                    'card_last_four' => $card->card_last_four,
                    'expiry_date' => $card->expiry_date,
                    'is_default' => (bool) $card->is_default,
                    'is_expired' => $card->isExpired(),
                    'is_expiring_soon' => $card->isExpiringSoon(),
                    'nickname' => $card->nickname,
                    'payment_method_id' => $card->payment_method_id,
                ])
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'cart' => $this->formatCart($cart),
            'currency' => [
                'symbol' => $currencySettings['currency_symbol'],
                'position' => $currencySettings['currency_position'],
            ],
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'payment_methods' => $paymentMethods,
            'saved_cards' => $savedCards,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * إضافة منتج للسلة
     * Add item to cart
     */
    public function addItem(Request $request): JsonResponse
    {
        // `bail` + `integer` قبل `exists`: مصفوفة/نصٌّ في معرّفٍ كان يُسقط قاعدة exists بـ«Array to string conversion»
        $validator = Validator::make($request->all(), [
            'product_id' => 'bail|required|integer|exists:products,id',
            'variant_id' => 'bail|nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:1000',
            'store_house_id' => 'nullable|integer',
            'options' => 'nullable|array|max:20',
        ], [
            'product_id.required' => 'المنتج مطلوب',
            'product_id.integer' => 'المنتج غير صالح',
            'product_id.exists' => 'المنتج غير موجود',
            'variant_id.integer' => 'خيار المنتج غير صالح',
            'variant_id.exists' => 'خيار المنتج المحدد غير موجود',
            'quantity.required' => 'الكمية مطلوبة',
            'quantity.integer' => 'الكمية يجب أن تكون رقماً صحيحاً',
            'quantity.min' => 'الكمية يجب أن تكون على الأقل 1',
            'quantity.max' => 'الكمية القصوى للمنتج في الطلب الواحد 1000',
            'options.array' => 'خيارات المنتج غير صالحة',
        ], [
            'product_id' => 'المنتج',
            'variant_id' => 'خيار المنتج',
            'quantity' => 'الكمية',
            'store_house_id' => 'المستودع',
            'options' => 'الخيارات',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        // منتجٌ مخفيّ/غير نشط (status) لا يُباع حتى لو كان معلَّماً «متاح أونلاين»
        $product = Product::where('available_online', true)
            ->where('type', ProductType::PRODUCTS->value)
            ->where('status', ProductStatus::ACTIVE)
            ->find($request->product_id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير متاح للشراء أونلاين',
            ], 400);
        }

        // اختيار المتغيّر إلزاميٌّ إن كان للمنتج متغيّرات (لون/مقاس/إلخ) —
        // لا يجوز إضافة منتجٍ ذي متغيّرات للسلة دون تحديد أيٍّ منها.
        if ($product->hasVariants() && ! $request->filled('variant_id')) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء اختيار الخيار المطلوب (مثل اللون أو المقاس) قبل إضافة المنتج للسلة',
            ], 422);
        }

        // التحقق من متغيّر المنتج (اللون/المقاس/إلخ) إن تم تحديده
        $variant = null;
        if ($request->filled('variant_id')) {
            $variant = ProductVariant::find($request->variant_id);

            if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'خيار المنتج المحدد لا ينتمي لهذا المنتج',
                ], 400);
            }

            // المتغيّر لا يملك مخزوناً مستقلاً — التوفّر يُفحص من مخزون المنتج
            // الأساسي في المستودع أدناه، سواءٌ اختير متغيّرٌ أم لا.
            if (! $variant->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا الخيار من المنتج غير متاح حالياً',
                ], 400);
            }
        }

        // المستودع الافتراضي من الإعدادات وحده — لا `store_house_id` المُرسل
        // ولا المستودع الرئيسي (major). كل عرض/فحص مخزونٍ في المتجر يعتمده.
        $defaultStoreHouse = $this->getDefaultStorehouse();
        $storeHouseId = $defaultStoreHouse?->id;

        // التحقق من إعداد البيع بالسالب
        $allowNegativeStock = OnlineStoreSettingsService::get('allow_negative_stock', false);

        // التحقق من توفر الكمية في المستودع الافتراضي (إن لم يُسمح بالبيع بالسالب).
        // المتغيّرات بلا مخزونٍ مستقلّ — الفحص على مخزون المنتج الأساسي دائماً.
        if (! $allowNegativeStock) {
            $availableQuantity = $this->getStoreStockQuantity($product);

            if ($availableQuantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'الكمية المطلوبة غير متاحة في المخزون',
                    'available_quantity' => $availableQuantity,
                    'store_house_id' => $storeHouseId,
                    'store_house_name' => $defaultStoreHouse?->name ?? null,
                ], 400);
            }
        }

        $cart = $this->getOrCreateCart($request);

        // بناء الـ options - ندمج مرجع المتغيّر (variant) ولقطة عرضه داخل options
        // لأن جدول cart_items لا يملك عمود variant_id مخصصاً (تحقّقنا من الترحيل)
        // المفاتيح المحجوزة للمتغيّر لا تُقبل من العميل: تُكتب من المتغيّر نفسه أدناه أو لا تُكتب
        $itemOptions = collect($request->options ?? [])
            ->except(['variant_id', 'variant_label', 'variant_sku', 'variant_options'])
            ->map(fn ($v) => is_scalar($v) ? mb_substr((string) $v, 0, 200) : null)
            ->filter(fn ($v) => $v !== null)
            ->all();
        if ($variant) {
            $itemOptions['variant_id'] = $variant->id;
            $itemOptions['variant_label'] = $variant->displayLabel();
            $itemOptions['variant_sku'] = $variant->sku;
            $itemOptions['variant_options'] = $variant->variant_options;
        }
        $requestedVariantId = $variant?->id;

        // التحقق من وجود المنتج نفسه في السلة
        // ⚠️ جدول cart_items يحمل قيد تفرّد (cart_id, product_id) فلا يمكن أن يوجد
        // سطران لنفس المنتج في نفس السلة حتى لو اختلف المتغيّر المختار.
        $existingItem = $cart->items()
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingItem) {
            $existingVariantId = is_array($existingItem->options) ? ($existingItem->options['variant_id'] ?? null) : null;

            if ($existingVariantId != $requestedVariantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المنتج موجود بالفعل في السلة بخيارٍ مختلف. يرجى حذفه من السلة أولاً ثم إضافته بالخيار الجديد.',
                ], 400);
            }

            $newQuantity = $existingItem->quantity + $request->quantity;

            if (! $allowNegativeStock) {
                // مخزون المنتج الأساسي في المستودع الافتراضي — لا مخزون المتغيّر
                $availableQuantity = $this->getStoreStockQuantity($product);

                if ($availableQuantity < $newQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'الكمية الإجمالية تتجاوز المتاح في المخزون',
                        'available_quantity' => $availableQuantity,
                        'current_in_cart' => $existingItem->quantity,
                        'store_house_id' => $storeHouseId,
                        'store_house_name' => $defaultStoreHouse?->name ?? null,
                    ], 400);
                }
            }

            $existingItem->update(['quantity' => $newQuantity, 'options' => $itemOptions]);
            $existingItem->calculateTotal();
        } else {
            // حساب السعر - سعر المتغيّر إن وُجد، وإلا سعر المنتج الأساسي
            $unitPrice = $variant ? $variant->finalPrice() : $this->calculateItemPrice($product);

            $cart->items()->create([
                'product_id' => $request->product_id,
                'store_house_id' => $storeHouseId, // استخدام المخزون الرئيسي تلقائياً
                'quantity' => $request->quantity,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'tax' => 0,
                'total' => $unitPrice * $request->quantity,
                'options' => $itemOptions,
            ]);
        }

        $cart->recalculateTotal();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة المنتج للسلة',
            'cart' => $this->formatCart($cart->fresh(['items.product'])),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * تحديث كمية منتج في السلة
     * Update item quantity
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0|max:1000',
        ], [
            'quantity.required' => 'الكمية مطلوبة',
            'quantity.integer' => 'الكمية يجب أن تكون رقماً صحيحاً',
            'quantity.min' => 'الكمية غير صالحة',
            'quantity.max' => 'الكمية القصوى للمنتج في الطلب الواحد 1000',
        ], ['quantity' => 'الكمية']);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الكمية غير صالحة',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->find($itemId);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'العنصر غير موجود في السلة',
            ], 404);
        }

        if ($request->quantity == 0) {
            $item->delete();
            $cart->recalculateTotal();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العنصر من السلة',
                'cart' => $this->formatCart($cart->fresh(['items.product'])),
            ]);
        }

        // التحقق من إعداد البيع بالسالب
        $allowNegativeStock = OnlineStoreSettingsService::get('allow_negative_stock', false);

        // التحقق من توفر الكمية (إذا لم يكن البيع بالسالب مسموحاً) —
        // من المستودع الافتراضي في الإعدادات وحده
        if (! $allowNegativeStock) {
            $product = $item->product;

            // منتجٌ حُذف/أُخفي بعد إضافته للسلة: لا تُحدَّث كميتُه
            if (! $product || ! $product->available_online) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المنتج لم يعد متاحاً للشراء — احذفه من السلة',
                ], 400);
            }

            $availableQuantity = $this->getStoreStockQuantity($product);

            if ($availableQuantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'الكمية المطلوبة غير متاحة في المخزون',
                    'available_quantity' => $availableQuantity,
                    'store_house_id' => $this->defaultStorehouseId(),
                ], 400);
            }
        }

        $item->update(['quantity' => $request->quantity]);
        $item->calculateTotal();
        $cart->recalculateTotal();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الكمية',
            'cart' => $this->formatCart($cart->fresh(['items.product'])),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * حذف منتج من السلة
     * Remove item from cart
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $item = $cart->items()->find($itemId);

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'العنصر غير موجود في السلة',
            ], 404);
        }

        $item->delete();
        $cart->recalculateTotal();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العنصر من السلة',
            'cart' => $this->formatCart($cart->fresh(['items.product'])),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * مسح السلة بالكامل
     * Clear cart
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $cart->clear();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم مسح السلة',
            'cart' => $this->formatCart($cart),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * تطبيق كوبون خصم
     * Apply coupon
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
        ], [], ['code' => 'رمز الكوبون']);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'رمز الكوبون مطلوب',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! (OnlineStoreSettingsService::getCheckoutSettings()['enable_coupons'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => 'الكوبونات غير مفعّلة في المتجر',
                'code' => 'COUPON_INVALID',
            ], 422);
        }

        $coupon = StoreCoupon::where('code', trim((string) $request->code))->first();

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'message' => 'رمز الكوبون غير صحيح',
            ], 400);
        }

        if (! $coupon->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'الكوبون غير صالح أو منتهي الصلاحية',
            ], 400);
        }

        $clientId = $this->client($request)?->id;

        if ($clientId && ! empty($coupon->applicable_clients) && ! in_array($clientId, $coupon->applicable_clients)) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الكوبون غير متاح لحسابك',
            ], 400);
        }

        if ($clientId && ! $coupon->canBeUsedByClient($clientId)) {
            return response()->json([
                'success' => false,
                'message' => 'لقد وصلت للحد الأقصى من استخدام هذا الكوبون',
            ], 400);
        }

        $cart = $this->getOrCreateCart($request);

        // التحقق من الحد الأدنى للطلب
        if ($coupon->min_order_amount && $cart->subtotal < $coupon->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'الحد الأدنى للطلب هو '.$coupon->min_order_amount.' لاستخدام هذا الكوبون',
            ], 400);
        }

        $cart->update(['coupon_id' => $coupon->id]);
        $cart->recalculateTotal();

        // إعادة تحميل السلة للحصول على القيم المحدثة
        $cart->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تم تطبيق الكوبون بنجاح',
            'coupon' => [
                'code' => $coupon->code,
                'type' => $coupon->type->value,
                'discount_value' => (float) $coupon->value,
            ],
            'discount_amount' => (float) $cart->discount,
            'cart' => [
                'summary' => [
                    'subtotal' => (float) $cart->subtotal,
                    'discount' => (float) $cart->discount,
                    'coupon_discount' => (float) $cart->discount,
                    'tax' => (float) $cart->tax,
                    'shipping_cost' => (float) $cart->shipping_cost,
                    'shipping_tax' => $cart->shippingTax(),
                    'total' => (float) $cart->total,
                    'items_count' => $cart->items()->count(),
                ],
            ],
        ]);
    }

    /**
     * إزالة كوبون الخصم
     * Remove coupon
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);

        $cart->update(['coupon_id' => null, 'discount' => 0]);
        $cart->recalculateTotal();

        // إعادة تحميل السلة للحصول على القيم المحدثة
        $cart->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تم إزالة الكوبون',
            'cart' => [
                'summary' => [
                    'subtotal' => (float) $cart->subtotal,
                    'discount' => 0,
                    'coupon_discount' => 0,
                    'tax' => (float) $cart->tax,
                    'shipping_cost' => (float) $cart->shipping_cost,
                    'shipping_tax' => $cart->shippingTax(),
                    'total' => (float) $cart->total,
                    'items_count' => $cart->items()->count(),
                ],
            ],
        ]);
    }

    /**
     * دمج سلة الزائر مع سلة العميل
     * Merge guest cart with client cart
     */
    public function mergeCart(Request $request): JsonResponse
    {
        $client = $this->client($request);

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $sessionId = $this->storeSessionId($request);

        // البحث عن سلة الزائر
        $guestCart = Cart::where('session_id', $sessionId)
            ->whereNull('client_id')
            ->active()
            ->with('items')
            ->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'لا توجد سلة زائر للدمج',
                'cart' => $this->formatCart($this->getOrCreateCart($request)),
            ]);
        }

        // الحصول على أو إنشاء سلة العميل
        $clientCart = Cart::firstOrCreate(
            ['client_id' => $client->id],
            [
                'subtotal' => 0,
                'discount' => 0,
                'tax' => 0,
                'shipping_cost' => 0,
                'total' => 0,
                'expires_at' => now()->addDays(30),
            ]
        );

        // دمج العناصر
        foreach ($guestCart->items as $guestItem) {
            $existingItem = $clientCart->items()
                ->where('product_id', $guestItem->product_id)
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $guestItem->quantity,
                ]);
                $existingItem->calculateTotal();
            } else {
                $clientCart->items()->create([
                    'product_id' => $guestItem->product_id,
                    'store_house_id' => $guestItem->store_house_id,
                    'quantity' => $guestItem->quantity,
                    'unit_price' => $guestItem->unit_price,
                    'discount' => $guestItem->discount,
                    'tax' => $guestItem->tax,
                    'total' => $guestItem->total,
                    'options' => $guestItem->options,
                ]);
            }
        }

        // حذف سلة الزائر
        $guestCart->items()->delete();
        $guestCart->delete();

        $clientCart->recalculateTotal();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم دمج السلة بنجاح',
            'cart' => $this->formatCart($clientCart->fresh(['items.product'])),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * التحقق من توفر عناصر السلة
     * Validate cart items availability
     */
    public function validateCart(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request);
        $unavailableItems = [];
        $lowStockItems = [];

        // التحقق من إعداد البيع بالسالب
        $allowNegativeStock = OnlineStoreSettingsService::get('allow_negative_stock', false);

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->available_online || $product->type !== ProductType::PRODUCTS->value
                || $this->productStatusValue($product) !== 'active') {
                $unavailableItems[] = [
                    'item_id' => $item->id,
                    'product_name' => $product?->name ?? 'منتج محذوف',
                    'reason' => 'المنتج غير متاح',
                ];

                continue;
            }

            // تخطي فحص المخزون إذا كان البيع بالسالب مسموحاً
            if (! $allowNegativeStock) {
                $availableQuantity = $this->getStoreStockQuantity($product);

                if ($availableQuantity == 0) {
                    $unavailableItems[] = [
                        'item_id' => $item->id,
                        'product_name' => $product->name,
                        'reason' => 'نفذت الكمية',
                    ];
                } elseif ($availableQuantity < $item->quantity) {
                    $lowStockItems[] = [
                        'item_id' => $item->id,
                        'product_name' => $product->name,
                        'requested_quantity' => $item->quantity,
                        'available_quantity' => $availableQuantity,
                    ];
                }
            }
        }

        $isValid = empty($unavailableItems) && empty($lowStockItems);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'is_valid' => $isValid,
            'unavailable_items' => $unavailableItems,
            'low_stock_items' => $lowStockItems,
            'cart' => $this->formatCart($cart),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /** قيمة حالة المنتج نصّاً (enum أو نصّ). */
    private function productStatusValue(Product $product): string
    {
        return $product->status instanceof \BackedEnum ? (string) $product->status->value : (string) $product->status;
    }

    /**
     * الحصول على أو إنشاء سلة التسوق
     */
    private function getOrCreateCart(Request $request): Cart
    {
        // استخدام client() للحصول على العميل المسجل
        $client = $this->client($request);
        $clientId = $client?->id;
        $sessionId = $this->storeSessionId($request);

        // Log للتحقق من جلب العميل
        \Log::info('Cart: Getting or creating cart', [
            'client_id' => $clientId,
            'client_name' => $client ? ($client->first_name.' '.$client->last_name) : 'Guest',
            'session_id' => $sessionId,
            'has_bearer_token' => $request->bearerToken() ? 'Yes' : 'No',
        ]);

        if ($clientId) {
            $cart = Cart::firstOrCreate(
                ['client_id' => $clientId],
                [
                    'session_id' => $sessionId,
                    'subtotal' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'shipping_cost' => 0,
                    'total' => 0,
                    'expires_at' => now()->addDays(30),
                ]
            );

            // تحديث session_id إذا تغير
            if ($cart->session_id !== $sessionId) {
                $cart->update(['session_id' => $sessionId]);
            }

            return $cart;
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId, 'client_id' => null],
            [
                'subtotal' => 0,
                'discount' => 0,
                'tax' => 0,
                'shipping_cost' => 0,
                'total' => 0,
                'expires_at' => now()->addDays(7),
            ]
        );
    }

    /**
     * حساب سعر المنتج بعد الخصم
     * يستخدم الدوال المعرفة في موديل Product
     * discount_type: 1 = percentage, 2 = fixed
     */
    private function calculateProductPrice(Product $product): float
    {
        return $product->getFinalPrice();
    }

    /**
     * حساب سعر العنصر
     * يستخدم سعر المنتج الأساسي مع الخصم
     */
    private function calculateItemPrice(Product $product): float
    {
        return $product->getFinalPrice();
    }

    /**
     * تنسيق بيانات السلة
     */
    private function formatCart(Cart $cart): array
    {
        // تحميل جميع العلاقات المطلوبة بما في ذلك المنتجات والعميل
        $cart->load([
            'items.product.category',
            'items.product.brand',
            'coupon',
            'client',
        ]);

        // تنسيق بيانات العميل إن وجد
        $clientInfo = null;
        if ($cart->client) {
            $clientInfo = [
                'id' => $cart->client->id,
                'first_name' => $cart->client->first_name,
                'last_name' => $cart->client->last_name,
                'full_name' => $cart->client->first_name.' '.$cart->client->last_name,
                'email' => $cart->client->email,
                'phone' => $cart->client->phone ?? $cart->client->mobile,
            ];
        }

        return [
            'id' => $cart->id,
            'client_id' => $cart->client_id,
            'client' => $clientInfo,
            'session_id' => $cart->session_id,
            'items' => $cart->items->map(fn ($item) => $this->formatCartItem($item)),
            'items_count' => $cart->items->count(),
            'total_items' => $cart->items->sum('quantity'),
            'summary' => [
                'items_count' => $cart->items->count(),
                'total_items' => $cart->items->sum('quantity'),
                'subtotal' => (float) $cart->subtotal,
                'discount' => (float) $cart->discount,
                'coupon_discount' => (float) $cart->discount, // للتوافق مع الواجهة
                'tax' => (float) $cart->tax,
                'shipping_cost' => (float) $cart->shipping_cost,
                'shipping_tax' => $cart->shippingTax(),
                'total' => (float) $cart->total,
            ],
            'subtotal' => (float) $cart->subtotal,
            'discount' => (float) $cart->discount,
            'tax' => (float) $cart->tax,
            'shipping_cost' => (float) $cart->shipping_cost,
            'shipping_tax' => $cart->shippingTax(),
            'total' => (float) $cart->total,
            'coupon' => $cart->coupon ? [
                'code' => $cart->coupon->code,
                'type' => $cart->coupon->type->value ?? $cart->coupon->type,
                'discount_value' => (float) $cart->coupon->value, // استخدام value - الاسم الصحيح للحقل
            ] : null,
            'notes' => $cart->notes,
        ];
    }

    /**
     * تنسيق عنصر السلة مع كامل بيانات المنتج
     */
    private function formatCartItem($item): array
    {
        $product = $item->product;

        if (! $product) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => 'منتج محذوف',
                'product_deleted' => true,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ];
        }

        // جلب إعدادات المخزون
        $appearanceSettings = OnlineStoreSettingsService::getAppearanceSettings();
        $allowNegativeStock = $appearanceSettings['allow_negative_stock'] ?? false;
        $shouldShowQuantity = ! $allowNegativeStock;
        $totalQuantity = $this->getStoreStockQuantity($product);

        // حساب السعر الأصلي والخصم
        $originalPrice = $product->selling_price;
        $hasDiscount = $product->has_discount && $product->discount_value > 0;
        $discountAmount = 0;
        $discountPercentage = 0;

        if ($hasDiscount) {
            if ($product->discount_type == 1) { // نسبة مئوية
                $discountPercentage = $product->discount_value;
                $discountAmount = ($originalPrice * $product->discount_value) / 100;
            } else { // قيمة ثابتة
                $discountAmount = $product->discount_value;
                $discountPercentage = ($discountAmount / $originalPrice) * 100;
            }
        }

        // معالجة الأبعاد
        $dimensions = null;
        if ($product->dimensions && is_array($product->dimensions)) {
            $dimensions = [
                'length' => $product->dimensions['length'] ?? null,
                'width' => $product->dimensions['width'] ?? null,
                'height' => $product->dimensions['height'] ?? null,
                'formatted' => isset($product->dimensions['length'], $product->dimensions['width'], $product->dimensions['height'])
                    ? "{$product->dimensions['length']}x{$product->dimensions['width']}x{$product->dimensions['height']} سم"
                    : null,
            ];
        }

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $product->name,
            'product_name_en' => $product->name_en,
            'product_description' => $product->description,
            'product_description_en' => $product->description_en,
            'sku' => $product->sku,
            'barcode' => $product->barcode,

            // الصور
            'product_image' => $this->getFirstImageUrl($product->images),
            'product_images' => $this->formatImagesArray($product->images),

            // الأسعار
            'original_price' => $originalPrice,
            'unit_price' => $item->unit_price,
            'has_discount' => $hasDiscount,
            'discount_type' => $hasDiscount ? ($product->discount_type == 1 ? 'percentage' : 'fixed') : null,
            'discount_value' => $hasDiscount ? $product->discount_value : 0,
            'discount_amount' => $discountAmount,
            'discount_percentage' => round($discountPercentage, 2),

            // الكميات - إظهار الكمية فقط إذا كان البيع بالسالب غير مسموح
            'quantity' => $item->quantity,
            'available_quantity' => $totalQuantity,
            'in_stock' => $totalQuantity > 0 || ! $product->track_inventory,
            'stock_quantity' => $shouldShowQuantity ? $totalQuantity : null,
            'show_stock' => $shouldShowQuantity,

            // الضريبة والإجمالي
            'discount' => $item->discount,
            'tax' => $item->tax,
            'total' => $item->total,

            // معلومات إضافية
            'weight' => $product->weight ? (float) $product->weight : null,
            'dimensions' => $dimensions,
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,

            // الخيارات الأخرى (ملاحظات، تخصيصات، الخ)
            'options' => $item->options,
            'notes' => $item->notes ?? null,

            // متغيّر المنتج المختار (إن وُجد) — مُستخرَجٌ من لقطة options
            // لأن جدول cart_items لا يملك عمود variant_id مخصصاً
            'variant_id' => is_array($item->options) ? ($item->options['variant_id'] ?? null) : null,
            'variant_label' => is_array($item->options) ? ($item->options['variant_label'] ?? null) : null,
            'variant_sku' => is_array($item->options) ? ($item->options['variant_sku'] ?? null) : null,
            // تفصيل كل خاصية على حدة (اللون: أحمر، المقاس: L) لعرضها كوسومٍ منفصلة
            'selected_attributes' => $this->formatSelectedAttributes($item->options),
        ];
    }

    /**
     * تفكيك لقطة variant_options (مثال: {"اللون":"أحمر","المقاس":"L"}) المحفوظة
     * ضمن options إلى قائمة {attribute_name, value} لعرضها كوسومٍ منفصلة في الواجهة
     */
    private function formatSelectedAttributes($options): array
    {
        $variantOptions = is_array($options) ? ($options['variant_options'] ?? null) : null;

        if (! is_array($variantOptions)) {
            return [];
        }

        return collect($variantOptions)
            ->map(fn ($value, $name) => ['attribute_name' => $name, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * تنسيق مصفوفة الصور
     */
    private function formatImagesArray($images): array
    {
        if (! $images) {
            return [];
        }

        $imagesArray = is_array($images) ? $images : json_decode($images, true);

        if (! is_array($imagesArray)) {
            return [];
        }

        return array_map(function ($image) {
            return [
                'url' => $this->getImageUrl($image),
                'thumbnail' => $this->getImageUrl($image, 'thumbnail'),
            ];
        }, $imagesArray);
    }

    // ==================== دوال البريد الإلكتروني ====================
    // ملاحظة: هذه الدوال تُستخدم من خلال Scheduled Tasks
    // وليس مباشرة من الكنترولر

    /**
     * إرسال تذكير بالسلة المتروكة
     * يُستخدم عبر Scheduled Task بعد 24 ساعة
     *
     * مثال الاستخدام في Console/Kernel.php:
     * $schedule->call(function () {
     *     Cart::where('client_id', '!=', null)
     *         ->whereHas('items')
     *         ->where('updated_at', '<', now()->subHours(24))
     *         ->where('updated_at', '>', now()->subHours(25))
     *         ->with(['client', 'items.product'])
     *         ->chunk(50, function ($carts) {
     *             foreach ($carts as $cart) {
     *                 (new StoreCartController())->sendAbandonedCartEmail($cart);
     *             }
     *         });
     * })->daily();
     */
    public function sendAbandonedCartEmail(Cart $cart): void
    {
        if (! $cart->client || ! $cart->client->email) {
            return;
        }

        try {
            $cartItems = $this->formatCartItemsForEmail($cart);

            EmailTemplate::sendTemplatedEmail(
                to: $cart->client->email,
                templateCode: 'cart.abandoned',
                variables: [
                    'customer_name' => $cart->client->first_name.' '.$cart->client->last_name,
                    'cart_items' => $cartItems,
                    'cart_total' => number_format($cart->total, 2).' ريال',
                    'checkout_url' => url('/checkout'),
                    'store_name' => config('app.name', 'المتجر الإلكتروني'),
                ],
                recipientType: RecipientType::Customer,
                recipientId: $cart->client->id
            );
        } catch (\Exception $e) {
            \Log::error('فشل إرسال بريد السلة المتروكة: '.$e->getMessage());
        }
    }

    /**
     * إرسال تذكير بالسلة المتروكة مع كوبون خصم
     * يُستخدم عبر Scheduled Task بعد 48 ساعة
     *
     * @param  string  $discountCode  كود الخصم
     * @param  float  $discountPercent  نسبة الخصم
     * @param  string  $expiryDate  تاريخ انتهاء الكوبون
     */
    public function sendAbandonedCartWithDiscountEmail(
        Cart $cart,
        string $discountCode,
        float $discountPercent,
        string $expiryDate
    ): void {
        if (! $cart->client || ! $cart->client->email) {
            return;
        }

        try {
            $cartItems = $this->formatCartItemsForEmail($cart);
            $discountedTotal = $cart->total * (1 - $discountPercent / 100);

            EmailTemplate::sendTemplatedEmail(
                to: $cart->client->email,
                templateCode: 'cart.abandoned_discount',
                variables: [
                    'customer_name' => $cart->client->first_name.' '.$cart->client->last_name,
                    'discount_percent' => $discountPercent,
                    'discount_code' => $discountCode,
                    'expiry_date' => $expiryDate,
                    'cart_items' => $cartItems,
                    'cart_total' => number_format($cart->total, 2).' ريال',
                    'discounted_total' => number_format($discountedTotal, 2).' ريال',
                    'checkout_url' => url('/checkout'),
                    'store_name' => config('app.name', 'المتجر الإلكتروني'),
                ],
                recipientType: RecipientType::Customer,
                recipientId: $cart->client->id
            );
        } catch (\Exception $e) {
            \Log::error('فشل إرسال بريد السلة المتروكة مع خصم: '.$e->getMessage());
        }
    }

    /**
     * تنسيق عناصر السلة لعرضها في البريد الإلكتروني
     */
    private function formatCartItemsForEmail(Cart $cart): string
    {
        $itemsHtml = '';

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }

            $itemsHtml .= sprintf(
                '<div style="border-bottom:1px solid #ddd;padding:10px 0;">'.
                '<p style="margin:5px 0;"><strong>%s</strong></p>'.
                '<p style="margin:5px 0;color:#666;">الكمية: %d × %s ريال = <strong>%s ريال</strong></p>'.
                '</div>',
                $item->product->name,
                $item->quantity,
                number_format($item->unit_price, 2),
                number_format($item->total, 2)
            );
        }

        return $itemsHtml ?: '<p>لا توجد منتجات</p>';
    }
}
