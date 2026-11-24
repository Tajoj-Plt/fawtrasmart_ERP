<?php

namespace Modules\OnlineStore\Http\Controllers\Api;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\OnlineStorePlanFeatures;
use App\Constants\SystemAccountIds;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\RecipientType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PermissionSource;
use App\Models\ProductDetails;
use App\Models\Store\Cart;
use App\Models\Store\ClientAddress;
use App\Models\Store\ClientPaymentCard;
use App\Models\Store\OnlineOrder;
use App\Models\Store\OnlineOrderItem;
use App\Models\Store\OnlineOrderStatusHistory;
use App\Models\Store\ProductVariant;
use App\Models\Store\ShippingMethod;
use App\Models\Store\StoreCoupon;
use App\Models\StoreHouse;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\Treasury;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Fawtra\SubscriptionGate;
use App\Services\OnlineStoreSettingsService;
use App\Services\Zatca\ZatcaQr;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\OnlineStore\Http\Controllers\Api\Traits\FormatsImages;
use Modules\OnlineStore\Http\Controllers\Concerns\PostsStoreOrderAccounting;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;

/**
 * متحكم الطلبات للمتجر الإلكتروني
 * Store Orders Controller
 *
 * يدير إنشاء وعرض وتتبع الطلبات
 */
class StoreOrderController extends Controller
{
    use FormatsImages, PostsStoreOrderAccounting, ResolvesStoreDefaults;

    /**
     * الحصول على العميل المسجل حالياً
     * Get current authenticated client
     */
    protected function client(): ?Client
    {
        return Auth::guard('client')->user();
    }

    /**
     * عرض قائمة طلبات العميل
     * List client orders
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $query = OnlineOrder::where('client_id', $client->id)
            ->with(['items.product:id,name,images', 'shippingMethod:id,name']);

        // تصفية حسب الحالة
        if ($request->filled('status')) {
            $status = OrderStatus::tryFrom($request->status);
            if ($status) {
                $query->byStatus($status);
            }
        }

        // الترتيب
        $query->orderBy('created_at', 'desc');

        $perPage = min($request->get('per_page', 10), 50);
        $orders = $query->paginate($perPage);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();

        return response()->json([
            'success' => true,
            'orders' => $orders->map(fn ($order) => $this->formatOrderList($order)),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
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
    }

    /**
     * عرض تفاصيل طلب
     * Show order details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $order = OnlineOrder::where('client_id', $client->id)
            ->with([
                'items.product:id,name,images,sale_price',
                'shippingMethod',
                'address',
                'coupon:id,code,type,discount_value',
                'statusHistory' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'extraFees',
            ])
            ->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();
        $currencySettings = OnlineStoreSettingsService::getCurrencySettings();

        return response()->json([
            'success' => true,
            'order' => $this->formatOrderDetails($order),
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
     * إنشاء طلب جديد - آمن ومحمي
     * Create new order - Secure and protected
     *
     * ⚠️ ملاحظة أمنية: لا يتم استلام أي قيم مالية من الـ Frontend
     * جميع الحسابات تتم في الـ Backend لمنع التلاعب بالأسعار
     */
    public function store(Request $request): JsonResponse
    {
        // ✅ التحقق من البيانات - فقط IDs بدون قيم مالية
        // ملاحظة: payment_method_id اختياري إذا تم إرسال saved_card_id (سيتم استخراجه من البطاقة)
        $validator = Validator::make($request->all(), [
            'address_id' => 'bail|required|integer|exists:client_addresses,id',
            'shipping_method_id' => 'bail|required|integer|exists:shipping_methods,id',
            'payment_method_id' => 'bail|nullable|integer|exists:payment_methods,id', // اختياري إذا تم اختيار بطاقة محفوظة
            'saved_card_id' => 'bail|nullable|integer|exists:client_payment_cards,id',
            // بيانات البطاقة الجديدة (اختياري)
            'card_number' => 'nullable|string|max:19',
            'card_expiry' => 'nullable|string|max:7',
            'card_cvv' => 'nullable|string|max:4',
            'card_holder' => 'nullable|string|max:100',
            'save_card' => 'nullable|boolean',
            // رقم الجوال اختياري: يؤخذ من الطلب ثم من ملف العميل ثم من العنوان
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9٠-٩\s\-()]{6,20}$/u'],
            // ملاحظات فقط
            'notes' => 'nullable|string|max:1000',
        ], [
            'address_id.required' => 'عنوان التوصيل مطلوب',
            'address_id.exists' => 'العنوان غير موجود',
            'shipping_method_id.required' => 'طريقة الشحن مطلوبة',
            'shipping_method_id.exists' => 'طريقة الشحن غير موجودة',
            'payment_method_id.exists' => 'طريقة الدفع غير موجودة',
            'saved_card_id.exists' => 'البطاقة المحفوظة غير موجودة',
            'phone.regex' => 'رقم الجوال غير صالح',
            'address_id.integer' => 'عنوان التوصيل غير صالح',
            'shipping_method_id.integer' => 'طريقة الشحن غير صالحة',
            'payment_method_id.integer' => 'طريقة الدفع غير صالحة',
            'saved_card_id.integer' => 'البطاقة المحفوظة غير صالحة',
        ], [
            'phone' => 'رقم الجوال',
            'address_id' => 'عنوان التوصيل',
            'shipping_method_id' => 'طريقة الشحن',
            'payment_method_id' => 'طريقة الدفع',
            'saved_card_id' => 'البطاقة المحفوظة',
            'card_number' => 'رقم البطاقة',
            'card_expiry' => 'تاريخ انتهاء البطاقة',
            'card_cvv' => 'رمز التحقق',
            'card_holder' => 'اسم حامل البطاقة',
            'save_card' => 'حفظ البطاقة',
            'notes' => 'الملاحظات',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        // ✅ الخطوة 1: تحديد طريقة الدفع
        // أولوية: البطاقة المحفوظة > طريقة الدفع المختارة
        $savedCard = null;
        $paymentMethod = null;

        // السيناريو 1: تم اختيار بطاقة محفوظة - نستخرج طريقة الدفع منها
        if ($request->filled('saved_card_id')) {
            $savedCard = ClientPaymentCard::where('client_id', $client->id)
                ->where('id', $request->saved_card_id)
                ->with('paymentMethod') // تحميل العلاقة
                ->active()
                ->valid()
                ->first();

            if (! $savedCard) {
                return response()->json([
                    'success' => false,
                    'message' => 'البطاقة المحفوظة غير صالحة أو منتهية الصلاحية',
                ], 400);
            }

            // استخراج طريقة الدفع من البطاقة المحفوظة
            $paymentMethod = $savedCard->paymentMethod;

            // طريقة الدفع المرتبطة بالبطاقة يجب أن يقبلها المتجر الآن (إعداداته) لا أن تكون نشطةً في النظام فحسب
            if (! $paymentMethod || ! OnlineStoreSettingsService::storePaymentMethodsQuery()->whereKey($paymentMethod->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'طريقة الدفع المرتبطة بالبطاقة غير متاحة',
                ], 400);
            }
        }
        // السيناريو 2: تم اختيار طريقة دفع مباشرة
        elseif ($request->filled('payment_method_id')) {
            $paymentMethod = OnlineStoreSettingsService::storePaymentMethodsQuery()->find($request->payment_method_id);

            if (! $paymentMethod) {
                return response()->json([
                    'success' => false,
                    'message' => 'طريقة الدفع غير متاحة',
                ], 400);
            }

            // التحقق من متطلبات البطاقة إذا كانت طريقة الدفع تتطلب بطاقة
            if ($paymentMethod->requires_card) {
                // تم إدخال بيانات بطاقة جديدة
                if ($request->filled('card_number')) {
                    // سيتم حفظ البطاقة لاحقاً بعد نجاح الطلب
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'طريقة الدفع تتطلب بطاقة. الرجاء اختيار بطاقة محفوظة أو إدخال بيانات بطاقة جديدة.',
                    ], 400);
                }
            }
        }
        // السيناريو 3: لا بطاقة ولا طريقة دفع
        else {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء اختيار بطاقة دفع محفوظة أو طريقة دفع',
            ], 400);
        }

        // التحقق من أن طريقة الدفع مفعلة من الإعدادات
        $enabledPaymentMethodIds = OnlineStoreSettingsService::getEnabledPaymentMethods();
        if (! empty($enabledPaymentMethodIds) && ! in_array($paymentMethod->id, $enabledPaymentMethodIds)) {
            return response()->json([
                'success' => false,
                'message' => 'طريقة الدفع غير مفعلة للمتجر',
            ], 400);
        }

        // التحقق من ملكية العنوان
        $address = ClientAddress::where('client_id', $client->id)
            ->active()
            ->find($request->address_id);

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'العنوان غير صالح',
            ], 400);
        }

        // رقم الجوال: من الطلب، وإلا من ملف العميل (المخزَّن عند التسجيل)، وإلا من العنوان —
        // لا يُطلب من العميل مجدداً ما دام محفوظاً عندنا
        $contactPhone = collect([$request->input('phone'), $client->phone, $client->mobile, $address->receiver_phone])
            ->map(fn ($p) => is_string($p) ? trim($p) : '')
            ->first(fn ($p) => preg_match_all('/\d/u', $p) >= 6);

        if (! $contactPhone) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الجوال مطلوب لإتمام الطلب. الرجاء إضافة رقم جوال صحيح إلى ملفك الشخصي أو إلى عنوان التوصيل.',
                'code' => 'PHONE_REQUIRED',
            ], 422);
        }

        // ✅ الخطوة 2: الحصول على السلة وحساب الأسعار من Backend
        $cart = Cart::where('client_id', $client->id)
            ->with(['items.product', 'coupon'])
            ->active()
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'سلة التسوق فارغة',
            ], 400);
        }

        // جلب المخزون الافتراضي من الإعدادات (التابع للمتجر)
        $defaultStorehouse = $this->getDefaultStorehouse();

        if (! $defaultStorehouse) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تحديد المستودع الافتراضي للمتجر. الرجاء ضبطه من إعدادات المتجر.',
            ], 400);
        }

        // التحقق من توفر المنتجات وحساب الأسعار من قاعدة البيانات
        $calculatedItems = [];
        $subtotal = 0;

        foreach ($cart->items as $item) {
            $product = $item->product;

            $productStatus = $product?->status instanceof \BackedEnum ? $product->status->value : (string) $product?->status;

            if (! $product || ! $product->available_online || $product->type !== ProductType::PRODUCTS->value || $productStatus !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => "المنتج {$product?->name} غير متاح للشراء",
                ], 400);
            }

            // ✅ التحقق من متغيّر المنتج (إن وُجد) وإعادة حساب سعره من قاعدة البيانات
            // مباشرةً وقت الدفع، لا الثقة بالسعر المحفوظ في السلة وقت الإضافة —
            // مرجع المتغيّر مُستخرَجٌ من لقطة options (لا عمود variant_id في cart_items)
            $itemOptions = is_array($item->options) ? $item->options : [];
            $variant = null;

            // حراسة إضافية وقت الدفع: لا يصحّ إتمام طلبٍ لمنتجٍ له متغيّرات دون
            // اختيار أحدها — يحمي من بنود سلةٍ قديمة أُضيفت قبل هذا التحقق في addItem
            if ($product->hasVariants() && empty($itemOptions['variant_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => "الرجاء اختيار الخيار المطلوب لمنتج \"{$product->name}\" (مثل اللون أو المقاس) قبل إتمام الطلب",
                ], 422);
            }

            if (! empty($itemOptions['variant_id'])) {
                $variant = ProductVariant::find($itemOptions['variant_id']);

                if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                    return response()->json([
                        'success' => false,
                        'message' => "خيار المنتج {$product->name} لم يعد متاحاً",
                    ], 400);
                }

                // المتغيّر بلا مخزونٍ مستقلّ — التوفّر من مخزون المنتج الأساسي أدناه
                if (! $variant->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => "خيار المنتج {$product->name} غير متاح حالياً",
                    ], 400);
                }
            }

            // ✅ استخدام سعر المتغيّر الفعلي إن وُجد، وإلا السعر المحفوظ في السلة
            $unitPrice = $variant ? $variant->finalPrice() : $item->unit_price;

            // Fallback: إذا لم يكن السعر محفوظاً، نحسبه من سعر المنتج
            if (! $unitPrice || $unitPrice <= 0) {
                $unitPrice = $variant ? $variant->finalPrice() : ($product->getFinalPrice() ?? $product->sale_price ?? $product->purchase_price ?? 0);
            }

            $quantity = (int) $item->quantity;
            // المستودع الافتراضي من الإعدادات وحده — لا `store_house_id` بند السلة
            $storeHouseId = $defaultStorehouse->id;

            // التحقق من إعداد البيع بالسالب
            $allowNegativeStock = OnlineStoreSettingsService::get('allow_negative_stock', false);

            // الكمية المتاحة = مخزون المنتج الأساسي في المستودع الافتراضي —
            // المتغيّرات لا تملك مخزوناً مستقلاً، والخصم يجري على هذا المخزون.
            $availableQuantity = $this->getStoreStockQuantity($product);

            if (! $allowNegativeStock && $availableQuantity < $quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "الكمية المطلوبة من {$product->name} غير متاحة. الكمية المتاحة: {$availableQuantity}",
                    'available_quantity' => $availableQuantity,
                ], 400);
            }

            $itemTotal = $unitPrice * $quantity;
            $subtotal += $itemTotal;

            $calculatedItems[] = [
                'product' => $product,
                'variant' => $variant,
                'product_id' => $product->id,
                'store_house_id' => $storeHouseId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'item_total' => $itemTotal,
                'options' => $itemOptions,
            ];
        }

        // طريقة الشحن وحساب تكلفة الشحن
        $shippingMethod = ShippingMethod::active()->find($request->shipping_method_id);
        if (! $shippingMethod) {
            return response()->json([
                'success' => false,
                'message' => 'طريقة الشحن غير متاحة',
            ], 400);
        }

        // حساب تكلفة الشحن الأساسية من طريقة الشحن
        $baseShippingCost = $shippingMethod->calculateShippingCost($subtotal);

        // جلب نسبة إيرادات الشحن من الإعدادات
        $shippingRevenuePercentage = OnlineStoreSettingsService::get('shipping_revenue_percentage', 0);

        // حساب مبلغ إيرادات الشحن (الربح)
        $shippingRevenueAmount = $baseShippingCost * ($shippingRevenuePercentage / 100);

        // تكلفة الشحن المستحقة لشركة الشحن = تكلفة الطريقة نفسها حتى لو أُعفي العميل منها
        // (حدّ الشحن المجاني/كوبون): الإعفاء يُسقط ما يدفعه العميل لا ما تدفعه المنشأة،
        // وما يزيد عمّا حُمِّل على العميل تتحمّله المنشأة ويُقيَّد مصروفاً عند «تم الشحن»
        $shippingCompanyCost = $shippingMethod->carrierCost();

        // تكلفة الشحن النهائية للعميل = التكلفة الأساسية + نسبة الإيرادات
        $shippingCost = $baseShippingCost + $shippingRevenueAmount;

        // تسجيل تفاصيل حساب الشحن للتتبع
        Log::info('حساب تكلفة الشحن للطلب', [
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_name' => $shippingMethod->name,
            'shipping_method_cost' => $shippingMethod->cost,
            'subtotal' => $subtotal,
            'base_shipping_cost' => $baseShippingCost,
            'shipping_revenue_percentage' => $shippingRevenuePercentage,
            'shipping_revenue_amount' => $shippingRevenueAmount,
            'shipping_company_cost' => $shippingCompanyCost,
            'final_shipping_cost' => $shippingCost,
        ]);

        // حساب خصم الكوبون (من السلة)
        $coupon = $cart->coupon;
        $couponDiscount = 0;

        $checkoutSettings = OnlineStoreSettingsService::getCheckoutSettings();

        /*
            الكوبون يُتحقَّق منه هنا من جديد — لا يُكتفى بأنه «مطبَّق على السلة»:
            قد ينتهي أو يُستنفَد حدُّه بين تطبيقه وإتمام الطلب. كوبونٌ على السلة
            لم يعد صالحاً يُرفَض الطلب معه برسالةٍ واضحة بدل أن يُسقَط بصمت
            فيدفع العميل سعراً غير الذي رآه.
        */
        if ($coupon) {
            if (! ($checkoutSettings['enable_coupons'] ?? true) || ! $coupon->canBeUsedByClient($client->id)) {
                return response()->json([
                    'success' => false,
                    'message' => "كوبون الخصم «{$coupon->code}» لم يعد صالحاً للاستخدام. الرجاء إزالته من السلة والمحاولة مجدداً.",
                    'code' => 'COUPON_INVALID',
                ], 422);
            }

            $productIds = collect($calculatedItems)->pluck('product_id')->toArray();
            $couponDiscount = round($coupon->calculateDiscount($subtotal, $productIds), 2);

            // كوبون «شحن مجاني»: كان يُحسب خصمه صفراً ولا يُلغى رسمُ الشحن في أي مكان
            if ($coupon->type === CouponType::FREE_SHIPPING
                && (! $coupon->min_order_amount || $subtotal >= $coupon->min_order_amount)) {
                $shippingCost = 0;
                $shippingRevenueAmount = 0;
            }
        }

        // ✅ حساب الضريبة والإجمالي في Backend
        $taxRate = max(0, (float) ($checkoutSettings['tax_rate'] ?? 15)) / 100;
        $taxIncluded = (bool) ($checkoutSettings['tax_included'] ?? false);

        $subtotal = round($subtotal, 2);
        // الخصم لا يتجاوز المجموع ولا يقلّ عن صفر مهما كان الكوبون
        $couponDiscount = max(0, min($couponDiscount, $subtotal));
        $shippingCost = round($shippingCost, 2);
        $shippingRevenueAmount = round($shippingRevenueAmount, 2);
        $shippingCompanyCost = round($shippingCompanyCost, 2);
        $amountAfterDiscount = round(max(0, $subtotal - $couponDiscount), 2);

        // ضريبة الشحن فوق رسم الشحن بنسبة ضريبة المتجر (الرسم غير شامل دائماً)؛ الشحن المجاني ⇒ صفر
        $shippingTax = OnlineStoreSettingsService::shippingVatFor($shippingCost);

        /*
            الأسعار الشاملة للضريبة: الضريبةُ **مستخرَجةٌ** من السعر لا صفر.
            كانت تُحسب صفراً متى فُعِّل «السعر شامل الضريبة»، فيُقيَّد كاملُ
            المبلغ إيراداً ولا يصل حسابَ الضريبة شيء — إقرارٌ ضريبي ناقص.
            الإجمالي لا يتغيّر (السعر شامل)؛ الذي يتغيّر توزيعُه في القيد.
        */
        $taxAmount = $taxIncluded
            ? round($amountAfterDiscount - ($amountAfterDiscount / (1 + $taxRate)), 2)
            : round($amountAfterDiscount * $taxRate, 2);
        $grandTotal = round($amountAfterDiscount + ($taxIncluded ? 0 : $taxAmount) + $shippingCost + $shippingTax, 2);

        /*
            الدفع الإلكتروني (بوّابة/بطاقة) يُقبض لحظةَ الطلب في حساب طريقة
            الدفع أو خزينة المتجر الافتراضية. النقد عند الاستلام والتحويل
            البنكي والشيك **ذمّةٌ على العميل** حتى يُسجَّل تحصيلُها من شاشة
            الطلب — فلا تلزمها خزينةٌ الآن، ولا تُقيَّد نقداً لم يُقبض.
        */
        $isPrepaid = $this->paymentMethodIsPrepaid($paymentMethod);
        $treasuryAccount = $isPrepaid ? $this->collectionAccountFor($paymentMethod) : null;

        if ($isPrepaid && ! $treasuryAccount) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تحديد الخزينة/البنك الافتراضي للمتجر. الرجاء ضبطه من إعدادات المتجر.',
            ], 400);
        }

        if (! $paymentMethod->isAmountValid($grandTotal)) {
            return response()->json([
                'success' => false,
                'message' => 'قيمة الطلب خارج الحدود المسموحة لطريقة الدفع المختارة.',
            ], 422);
        }

        // ⚠️ invoices.treasury_id مفتاحٌ خارجي على جدول treasuries، لا accounts —
        // $treasuryAccount->id (معرّف حساب) يفشل بخطأ قيدٍ خارجي صامتٍ إن مُرِّر
        // مباشرةً هنا لو لم تُربَط الخزينة بحسابها (treasuries.account_id).
        // الربط يحدث عادةً تلقائياً عند إنشاء خزينةٍ جديدة (StoreTreasuryController)،
        // لكن نتحمّل غيابه هنا بدل أن يسقط الطلب بكامله بخطأ لا يشرح نفسه.
        $treasuryId = $treasuryAccount
            ? Treasury::where('account_id', $treasuryAccount->id)->value('id')
            : null;

        if ($treasuryAccount && ! $treasuryId) {
            Log::warning('الخزينة الافتراضية للمتجر غير مربوطة بأي صفٍّ في جدول treasuries — سيُحفظ treasury_id فارغاً في الفاتورة', [
                'account_id' => $treasuryAccount->id,
                'account_name' => $treasuryAccount->name,
            ]);
        }

        // الفترة المحاسبية — إتمام الطلب ينشئ فاتورة وقيوداً محاسبية بتاريخ اليوم
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return response()->json([
                'success' => false,
                'message' => $blocked,
            ], 422);
        }

        /*
            حدّ باقة فوترة على الطلبات الشهرية — هنا لا في `middleware()`: هذا
            الكنترولر api/بلا وسيط `plan.limit` مضبوطاً على مجموعته، والمنشئ
            الفعلي الوحيد لطلبات المتجر هو هذه الدالّة (لا شاشة إدارية تُنشئ
            طلباً). انظر [\App\Constants\planFeatures\OnlineStorePlanFeatures].
        */
        $planLimit = app(SubscriptionGate::class)
            ->checkLive(OnlineStorePlanFeatures::LIMIT_ORDERS);

        if (! $planLimit['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $planLimit['reason'],
                'code' => 'PLAN_LIMIT_REACHED',
            ], 402);
        }

        DB::beginTransaction();

        try {
            // قفلُ السلة: نقرتان متزامنتان على «إتمام الطلب» لا تُنشئان طلبين — الثانية
            // تجد السلة فرغت بعد انتظار القفل
            $lockedCart = Cart::whereKey($cart->id)->lockForUpdate()->first();

            if (! $lockedCart || ! $lockedCart->items()->exists()) {
                throw new \DomainException('سلة التسوق فارغة — ربما أُرسل هذا الطلب مسبقاً.');
            }

            /*
                قفلُ الكوبون داخل المعاملة وإعادةُ فحص حدوده: طلبان متزامنان
                بكوبونٍ بقي له استعمالٌ واحد كانا يمرّان معاً (الفحص قبل
                المعاملة بلا قفل)، فيتجاوز الكوبون حدَّه.
            */
            if ($coupon) {
                $coupon = StoreCoupon::whereKey($coupon->id)->lockForUpdate()->first();

                if (! $coupon || ! $coupon->canBeUsedByClient($client->id)) {
                    throw new \DomainException('كوبون الخصم استُنفد حدُّ استخدامه. الرجاء إزالته من السلة والمحاولة مجدداً.');
                }
            }

            // فرعُ الطلب = فرعُ مستودع المتجر الذي تخرج منه البضاعة (وإلا الرئيسي)
            $orderBranchId = $defaultStorehouse->branch_id ?: Branch::mainId();
            $orderCurrency = CurrencySettings::code();

            // ✅ الخطوة 3: إنشاء الفاتورة المرتبطة بالطلب
            $invoiceCode = $this->generateInvoiceCode();

            // تحويل نوع طريقة الدفع إلى الرقم المستخدم في جدول invoices
            // 1: Cash, 2: Bank Transfer, 3: Credit Card, 4: Check, 5: Other
            $invoicePaymentMethod = match ($paymentMethod->type) {
                'cash', 'cod' => 1,          // نقدي أو الدفع عند الاستلام
                'bank_transfer' => 2,         // تحويل بنكي
                'check' => 4,                 // شيك
                'wallet' => 5,                // محفظة
                default => 3,                 // بطاقة ائتمانية (الافتراضي)
            };

            /*
                عميل المتجر الإلكتروني لا يُسجَّل له حسابٌ محاسبي خاص — تُستعمل
                بدلاً منه «حساب العميل النقدي» كما ضُبط في إعدادات المتجر (نفس
                الحساب الذي يُقيَّد عليه فعلياً في createSalesJournalEntries()
                أدناه)، لا حساب العميل الشخصي إن وُجد له واحدٌ من سياقٍ آخر —
                وإلا اختلف حساب الفاتورة المعروض عن حساب القيد الفعلي.
            */
            $cashClientAccountId = OnlineStoreSettingsService::getAccountId('cash_client_account_id', SystemAccountIds::CASH_CLIENT);

            // جلب المدير الأول (المستخدم والموظف الافتراضي للطلبات الأونلاين)
            $systemManager = User::where('role', 'manager')->orderBy('id')->first();
            $systemEmployee = $systemManager?->employee_id
                ? Employee::find($systemManager->employee_id)
                : Employee::orderBy('id')->first();

            $invoice = Invoice::create([
                'client_id' => $client->id,
                'branch_id' => $orderBranchId,
                'currency' => $orderCurrency,
                'code' => $invoiceCode,
                'type' => 'online',
                'invoice_date' => now()->toDateString(),
                'issue_date' => now()->toDateString(),
                'terms' => 0, // شروط الدفع (فوري)
                'notes' => 'طلب من المتجر الإلكتروني',
                // مدفوعةٌ للدفع الإلكتروني وحده؛ غيرُه غير مدفوعة (3) حتى يُسجَّل التحصيل
                'payment_status' => $isPrepaid ? 1 : 3,
                'is_paid' => $isPrepaid,
                'created_by' => $systemManager?->id, // المدير الأول في النظام
                'employee_id' => $systemEmployee?->id, // الموظف الأول أو موظف المدير
                'account_id' => $cashClientAccountId, // حساب العميل النقدي من إعدادات المتجر — لا حساب عميل شخصي
                'discount_amount' => $couponDiscount,
                'discount_type' => 1, // 1: مبلغ ثابت
                'advance_payment' => $isPrepaid ? $grandTotal : 0,
                'payment_type' => 1,
                'shipping_cost' => $shippingCost,
                'shipping_tax' => $shippingTax, // ضريبة الشحن مضافة فوق رسمه (دائماً غير شاملة)
                'tax_type' => $taxIncluded ? 0 : 1, // 0: شامل الضريبة, 1: غير شامل
                'payment_method' => $invoicePaymentMethod,
                'subtotal' => $subtotal,
                // كفواتير المبيعات: tax_total يضمّ ضريبة الشحن، وعمودها المستقل shipping_tax
                'tax_total' => round($taxAmount + $shippingTax, 2),
                'grand_total' => $grandTotal,
                'due_value' => $isPrepaid ? 0 : $grandTotal,
                'treasury_id' => $treasuryId,
            ]);

            // توليد QR Code للفاتورة الإلكترونية (ZATCA)
            $invoice->qrcode = $this->generateTlvContent($invoice->created_at, $invoice->grand_total, $invoice->tax_total);
            $invoice->save();

            // إنشاء بنود الفاتورة
            foreach ($calculatedItems as $calcItem) {
                // اسم البند يعكس المتغيّر المختار (إن وُجد) — الفاتورة إلكترونية
                // نظامية (ZATCA)، فيجب أن يظهر بها بالضبط ما اشتراه العميل
                $variantLabel = $calcItem['options']['variant_label'] ?? null;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $calcItem['product_id'],
                    'store_house_id' => $calcItem['store_house_id'],
                    'item' => $calcItem['product']->name,
                    'variant_label' => $variantLabel,
                    'description' => 'طلب أونلاين',
                    'quantity' => $calcItem['quantity'],
                    'unit_price' => $calcItem['unit_price'],
                    'discount' => $calcItem['discount'],
                    'discount_type' => 1,
                    'tax_1' => $taxRate * 100,
                    'tax_2' => 0,
                    'total' => $calcItem['item_total'],
                ]);
            }

            // إنشاء سجلات الضرائب للفاتورة (TaxInvoice)
            if ($taxAmount > 0) {
                $this->createTaxInvoiceRecords($invoice, $calculatedItems, $taxRate);
            }

            // ✅ الخطوة 4: إنشاء الطلب وربطه بالفاتورة
            // تجميع العنوان الكامل
            $fullAddress = implode(', ', array_filter([
                $address->street,
                $address->district,
                $address->building_number ? 'مبنى '.$address->building_number : null,
                $address->floor ? 'طابق '.$address->floor : null,
                $address->apartment ? 'شقة '.$address->apartment : null,
                $address->address_line,
            ]));

            $order = OnlineOrder::create([
                'order_number' => OnlineOrder::generateOrderNumber(),
                'branch_id' => $orderBranchId,
                'currency' => $orderCurrency,
                'client_id' => $client->id,
                'invoice_id' => $invoice->id,
                'address_id' => $address->id,
                'shipping_method_id' => $shippingMethod->id,
                'coupon_id' => $coupon?->id,
                'status' => OrderStatus::PENDING,
                'payment_method' => $this->mapPaymentMethodFromModel($paymentMethod),
                // ربط فعلي بجدول طرق الدفع — العمود النصي أعلاه يبقى للتوافق مع منطق الحالة القائم
                'payment_method_id' => $paymentMethod?->id,
                // يبدأ غيرَ مدفوع؛ التحصيل الفوري (الدفع الإلكتروني) يعلّمه مدفوعاً مع قيده أدناه
                'payment_status' => PaymentStatus::PENDING,

                // بيانات العنوان (فقط الأعمدة الموجودة في الجدول)
                'shipping_name' => $address->receiver_name ?? ($client->first_name.' '.$client->last_name),
                'shipping_phone' => $request->filled('phone') ? $contactPhone : ($address->receiver_phone ?: $contactPhone),
                'shipping_city' => $address->city,
                'shipping_region' => $address->region,
                'shipping_address' => $fullAddress ?: $address->address_line,
                'shipping_latitude' => $address->latitude,
                'shipping_longitude' => $address->longitude,

                // المبالغ (محسوبة في Backend)
                'subtotal' => $subtotal,
                'discount_amount' => $couponDiscount,
                'tax_amount' => $taxAmount,
                'prices_include_tax' => $taxIncluded,
                'shipping_cost' => $shippingCost,
                'shipping_tax_amount' => $shippingTax,
                'shipping_revenue_percentage' => $shippingRevenuePercentage,
                'shipping_revenue_amount' => $shippingRevenueAmount,
                'shipping_company_cost' => $shippingCompanyCost,
                'is_shipping_paid' => false, // لم يتم صرف المبلغ بعد لشركة الشحن
                'total_amount' => $grandTotal,

                'customer_notes' => $request->notes,
            ]);

            // إنشاء عناصر الطلب
            // ربط حقيقي بإعداد الضريبة (tax_sittings) بجانب النسبة المجمَّدة وقت البيع،
            // بنفس نمط StorePurchaseInvoiceController لبنود المشتريات
            $orderTaxSettingId = $this->resolveTaxSetting($taxRate)?->id;

            foreach ($calculatedItems as $calcItem) {
                // اسم/كود المنتج المحفوظان يعكسان المتغيّر المختار (إن وُجد) — لقطة
                // ثابتة تبقى صحيحة حتى لو حُذف المتغيّر لاحقاً
                $variantLabel = $calcItem['options']['variant_label'] ?? null;

                OnlineOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $calcItem['product_id'],
                    'store_house_id' => $calcItem['store_house_id'],
                    'product_name' => $calcItem['product']->name.($variantLabel ? ' - '.$variantLabel : ''),
                    'product_sku' => $calcItem['variant']?->sku ?? ($calcItem['product']->sku_online ?? $calcItem['product']->barcode),
                    'quantity' => $calcItem['quantity'],
                    'unit_price' => $calcItem['unit_price'],
                    'discount' => $calcItem['discount'],
                    // حصّة البند من ضريبة الطلب (بعد الخصم، ومستخرَجةً إن كان السعر شاملاً)
                    'tax' => $subtotal > 0 ? round($taxAmount * ($calcItem['item_total'] / $subtotal), 2) : 0,
                    'tax_1_id' => $orderTaxSettingId,
                    'total' => $calcItem['item_total'],
                    'options' => $calcItem['options'],
                ]);
            }

            /*
                ✅ الخطوة 5: المخزون أولاً (بقفلٍ وفحصِ رصيدٍ داخل المعاملة) ثم
                القيود — من التقسيم الواحد في PostsStoreOrderAccounting. أيُّ
                خطأ يُلغي الطلب كلَّه: كانت أخطاء القيود تُبتلع (catch + Log)
                فيُنشأ طلبٌ وفاتورةٌ ومخزونٌ مخصوم بلا قيدٍ محاسبي.
            */
            $costTotal = $this->updateInventory($calculatedItems, $invoice, $defaultStorehouse);

            $this->postOrderSaleEntry($order, $invoice);

            $this->postOrderCostEntry(
                $order,
                $invoice,
                $costTotal,
                Account::where('storehouse_id', $defaultStorehouse->id)->first()
                    ?? $this->storeAccount('inventory_account_id', 'inventory', SystemAccountIds::INVENTORY)
            );

            // التحصيل الفوري للدفع الإلكتروني فقط — وغيرُه ذمّةٌ حتى يُحصَّل
            if ($isPrepaid) {
                $this->postOrderCollection($order, $treasuryAccount);
            }

            // تسجيل الحالة الأولى
            OnlineOrderStatusHistory::createRecord(
                $order->id,
                OrderStatus::PENDING->value,
                $isPrepaid ? 'تم إنشاء الطلب وتأكيد الدفع' : 'تم إنشاء الطلب — الدفع عند الاستلام/التحويل (لم يُحصَّل بعد)'
            );

            // استخدام الكوبون
            if ($coupon) {
                $coupon->use($client->id, $invoice->id, $couponDiscount);
            }

            // تحديث استخدام البطاقة المحفوظة
            if ($savedCard) {
                $savedCard->markAsUsed();
            }

            // حفظ البطاقة الجديدة إذا طلب العميل ذلك ولم يكن لديه بطاقة محفوظة
            if (! $savedCard && $request->boolean('save_card') && $request->filled('card_number') && $paymentMethod->requires_card) {
                $this->saveNewCard($client, $paymentMethod, $request);
            }

            // مسح السلة
            $cart->clear();

            // إرسال بريد تأكيد الطلب
            $this->sendOrderConfirmationEmail($order);

            DB::commit();

            Log::info('تم إنشاء طلب جديد من المتجر الإلكتروني', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'invoice_id' => $invoice->id,
                'invoice_code' => $invoice->code,
                'client_id' => $client->id,
                'grand_total' => $grandTotal,
            ]);

            OperationLog::record(
                'created',
                'order',
                $order,
                'تم استلام طلب جديد رقم **'.$order->order_number.'** بقيمة '.number_format($order->total_amount ?? $grandTotal, 2)
            );

            // جلب بيانات المتجر والإعدادات
            $storeInfo = OnlineStoreSettingsService::getStoreInfo();
            $socialLinks = OnlineStoreSettingsService::getSocialSettings();
            $seo = OnlineStoreSettingsService::getSeoSettings();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'order' => $this->formatOrderDetails($order->fresh([
                    'items.product',
                    'shippingMethod',
                    'address',
                    'statusHistory',
                ])),
                'store_info' => $storeInfo,
                'social_links' => $socialLinks,
                'seo' => $seo,
                'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
            ], 201);

        } catch (\DomainException $e) {
            // خطأُ قاعدةٍ (مخزون نفد، كوبون استُنفد): رسالتُه للعميل كما هي
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // ⚠️ لا بدّ من rollBack() قبل OperationLog::failure() لا بعده:
            // التسجيل يكتب عبر Eloquent على نفس اتصال قاعدة البيانات، فإن
            // كتب داخل معاملةٍ لم تُنهَ بعد يُلغى سجلّه هو نفسه حين تُلغى
            // المعاملة بأثرٍ رجعي — فيختفي الخطأ الذي كان يُفترض تسجيله
            // بالضبط. تحقّقتُ من هذا مباشرةً: نفس الاستدعاء يُسجَّل بعد
            // rollBack() ولا يُسجَّل شيئاً قبله.
            DB::rollBack();

            OperationLog::failure($e, 'OnlineStore.StoreOrder', 'store');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الطلب',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * إلغاء طلب
     * Cancel order
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $order = OnlineOrder::where('client_id', $client->id)->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        // التحقق من إمكانية الإلغاء
        $cancellableStatuses = [
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED,
        ];

        if (! in_array($order->status, $cancellableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء الطلب في هذه الحالة',
            ], 400);
        }

        $cancelReason = trim(mb_substr((string) $request->input('reason', ''), 0, 500)) ?: 'تم الإلغاء من قبل العميل';

        // الفترة المحاسبية — الإلغاء يعكس قيود البيع بتاريخ اليوم
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return response()->json(['success' => false, 'message' => $blocked], 422);
        }

        /*
            إلغاءُ العميل يعكس ما رُحِّل: فاتورةُ مرتجع، عكسُ الإيراد والضريبة
            والشحن وتكلفة المبيعات، وعودةُ البضاعة للمستودع، وردُّ ما دُفع.
            كان يغيّر الحالة فقط (ويسقط أصلاً بخطأ SQL في سجلّ الحالات): طلبٌ
            «ملغي» وإيرادُه وضريبتُه ومخزونُه المخصوم باقيةٌ في الدفاتر.
        */
        DB::beginTransaction();

        try {
            $order = OnlineOrder::where('client_id', $client->id)->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, $cancellableStatuses, true)) {
                throw new \DomainException('لا يمكن إلغاء الطلب في هذه الحالة');
            }

            $this->reverseStoreOrder($order, $cancelReason, 'cancel');

            DB::commit();
        } catch (\DomainException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StoreOrder', 'cancel');

            return response()->json([
                'success' => false,
                'message' => 'تعذّر إلغاء الطلب حالياً. الرجاء التواصل مع المتجر.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        // إرسال بريد تأكيد الإلغاء
        $this->sendOrderCancelledEmail($order, $cancelReason);

        OperationLog::record(
            'updated',
            'order',
            $order,
            'تم إلغاء الطلب **'.$order->order_number.'** من قبل العميل — السبب: '.$cancelReason
        );

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الطلب بنجاح',
            'order' => $this->formatOrderList($order->fresh()),
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * تتبع طلب
     * Track order
     */
    public function track(Request $request, string $orderNumber): JsonResponse
    {
        $order = OnlineOrder::where('order_number', $orderNumber)
            ->with([
                'statusHistory' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                },
                'shippingMethod:id,name,estimated_days_min,estimated_days_max',
            ])
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        // التحقق من ملكية الطلب (إذا كان المستخدم مسجلاً)
        $client = $this->client();
        if ($client && (int) $order->client_id !== (int) $client->id) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        /*
            التتبّع العامّ (بلا تسجيل دخول) برقم الطلب وحده، وأرقامُ الطلبات
            متسلسلة يسهل تخمينها: للزائر الحالةُ والتواريخ فقط. الملاحظاتُ
            (أسباب الإلغاء، مبالغ الردّ) والموقعُ لصاحب الطلب المسجَّل وحده.
        */
        $isOwner = $client !== null;

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'tracking' => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $this->getStatusLabel($order->status),
                'created_at' => $order->created_at->toDateTimeString(),
                'estimated_delivery' => $this->calculateEstimatedDelivery($order),
                'shipping_method' => $order->shippingMethod?->name,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
                // المبالغ لصاحب الطلب المسجَّل وحده (الزائر يعرف رقم الطلب فقط)
                'shipping' => $isOwner ? (float) ($order->shipping_cost ?? 0) : null,
                'shipping_tax' => $isOwner ? (float) ($order->shipping_tax_amount ?? 0) : null,
                'history' => $order->statusHistory->map(fn ($history) => [
                    'status' => $history->status,
                    'status_label' => $history->status_name,
                    'notes' => $isOwner ? $history->notes : null,
                    'location' => $isOwner ? $history->location : null,
                    'date' => $history->created_at->toDateTimeString(),
                    'icon' => $history->status_icon,
                    'color' => $history->status_color,
                ]),
            ],
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * إعادة طلب سابق
     * Reorder
     */
    public function reorder(Request $request, int $id): JsonResponse
    {
        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $order = OnlineOrder::where('client_id', $client->id)
            ->with('items.product')
            ->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        // الحصول على أو إنشاء سلة
        $cart = Cart::firstOrCreate(
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

        $addedItems = [];
        $unavailableItems = [];

        foreach ($order->items as $orderItem) {
            $product = $orderItem->product;

            if (! $product || ! $product->available_online || $product->type !== ProductType::PRODUCTS->value
                || ($product->status instanceof \BackedEnum ? $product->status->value : (string) $product->status) !== 'active') {
                $unavailableItems[] = $orderItem->product_name;

                continue;
            }

            // استرجاع متغيّر المنتج (إن وُجد) من لقطة options المحفوظة على بند الطلب،
            // والتحقق من أنه ما زال متاحاً قبل إعادة طلبه
            $orderItemOptions = is_array($orderItem->options) ? $orderItem->options : [];
            $variant = null;
            if (! empty($orderItemOptions['variant_id'])) {
                $variant = ProductVariant::find($orderItemOptions['variant_id']);
                if ($variant && ((int) $variant->product_id !== (int) $product->id || ! $variant->is_active)) {
                    $variant = null;
                }
            }

            // المتغيّر بلا مخزونٍ مستقلّ — التوفّر من مخزون المنتج الأساسي
            // في المستودع الافتراضي (الإعدادات) وحده
            $availableQuantity = $this->getStoreStockQuantity($product);
            $quantity = min($orderItem->quantity, $availableQuantity);

            if ($quantity <= 0) {
                $unavailableItems[] = $orderItem->product_name;

                continue;
            }

            $unitPrice = $variant ? $variant->finalPrice() : $product->sale_price;

            // إضافة للسلة
            $existingItem = $cart->items()->where('product_id', $product->id)->first();

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $quantity,
                ]);
                $existingItem->calculateTotal();
            } else {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'store_house_id' => $orderItem->store_house_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'tax' => 0,
                    'total' => $unitPrice * $quantity,
                    'options' => $variant ? $orderItemOptions : [],
                ]);
            }

            $addedItems[] = $product->name;
        }

        $cart->recalculateTotal();

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'message' => count($addedItems) > 0
                ? 'تمت إضافة المنتجات للسلة'
                : 'لم يتم إضافة أي منتجات',
            'added_items' => $addedItems,
            'unavailable_items' => $unavailableItems,
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * حساب تكلفة الشحن للطلب
     * Calculate shipping cost
     */
    public function calculateShipping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'bail|required|integer|exists:client_addresses,id',
            'shipping_method_id' => 'bail|required|integer|exists:shipping_methods,id',
        ], [
            'address_id.required' => 'عنوان التوصيل مطلوب',
            'address_id.integer' => 'عنوان التوصيل غير صالح',
            'address_id.exists' => 'العنوان غير موجود',
            'shipping_method_id.required' => 'طريقة الشحن مطلوبة',
            'shipping_method_id.integer' => 'طريقة الشحن غير صالحة',
            'shipping_method_id.exists' => 'طريقة الشحن غير موجودة',
        ], [
            'address_id' => 'عنوان التوصيل',
            'shipping_method_id' => 'طريقة الشحن',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الرجاء التحقق من البيانات المدخلة',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $this->client();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً',
            ], 401);
        }

        $address = ClientAddress::where('client_id', $client->id)
            ->active()
            ->find($request->address_id);

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'العنوان غير صالح',
            ], 400);
        }

        $shippingMethod = ShippingMethod::active()->find($request->shipping_method_id);

        if (! $shippingMethod) {
            return response()->json([
                'success' => false,
                'message' => 'طريقة الشحن غير متاحة',
            ], 400);
        }

        $cart = Cart::where('client_id', $client->id)->with('coupon')->active()->first();
        $orderAmount = (float) ($cart?->subtotal ?? 0);

        // حساب تكلفة الشحن - يعتمد على free_shipping_threshold الخاص بطريقة الشحن
        // + نسبة إيراد الشحن، كما تُحسب فعلاً عند إتمام الطلب (store) وفي
        // StoreShippingController::calculate — كان هذا المسار يعرض التكلفة
        // الأساسية وحدها فيرى العميل رسماً أقلّ مما يُحصَّل منه
        $baseShippingCost = (float) $shippingMethod->calculateShippingCost((float) $orderAmount);
        $shippingRevenuePercentage = (float) OnlineStoreSettingsService::get('shipping_revenue_percentage', 0);
        $shippingCost = round($baseShippingCost * (1 + $shippingRevenuePercentage / 100), 2);

        // كوبون «الشحن المجاني» على السلة يُسقط الرسم هنا كما يُسقطه إتمام الطلب
        $coupon = $cart?->coupon;
        if ($coupon && $coupon->type === CouponType::FREE_SHIPPING
            && (! $coupon->min_order_amount || $orderAmount >= $coupon->min_order_amount)) {
            $shippingCost = 0.0;
        }

        // ضريبة الشحن فوق الرسم بنسبة ضريبة المتجر (صفر للشحن المجاني)
        $shippingTax = OnlineStoreSettingsService::shippingVatFor($shippingCost);

        // جلب بيانات المتجر والإعدادات
        $storeInfo = OnlineStoreSettingsService::getStoreInfo();
        $socialLinks = OnlineStoreSettingsService::getSocialSettings();
        $seo = OnlineStoreSettingsService::getSeoSettings();

        return response()->json([
            'success' => true,
            'shipping_cost' => $shippingCost,
            'shipping_tax' => $shippingTax,
            'shipping_total' => round($shippingCost + $shippingTax, 2),
            'is_free' => $shippingCost == 0,
            'estimated_delivery' => $shippingMethod->estimated_delivery_text,
            'store_info' => $storeInfo,
            'social_links' => $socialLinks,
            'seo' => $seo,
            'settings' => OnlineStoreSettingsService::getAllSettingsForApi(),
        ]);
    }

    /**
     * تنسيق الطلب للقائمة
     */
    private function formatOrderList(OnlineOrder $order): array
    {
        // الحصول على تسمية طريقة الدفع
        $paymentMethodLabel = null;
        if ($order->payment_method instanceof PaymentMethod) {
            $paymentMethodLabel = $order->payment_method->label();
        } elseif (is_string($order->payment_method)) {
            // محاولة تحويل النص إلى enum
            $paymentMethod = PaymentMethod::tryFrom($order->payment_method);
            $paymentMethodLabel = $paymentMethod?->label() ?? $order->payment_method;
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $this->getStatusLabel($order->status),
            'status_color' => $this->getStatusColor($order->status),
            'currency' => $order->documentCurrency(),
            'items_count' => $order->items_count,
            'grand_total' => (float) ($order->total_amount ?? $order->grand_total ?? 0),
            'subtotal' => (float) ($order->subtotal ?? 0),
            'tax_amount' => (float) ($order->tax_amount ?? 0),
            'shipping_cost' => (float) ($order->shipping_cost ?? 0),
            'shipping_tax' => (float) ($order->shipping_tax_amount ?? 0),
            'discount_amount' => (float) ($order->discount_amount ?? 0),
            'payment_method' => $paymentMethodLabel ?? 'غير محدد',
            'payment_method_value' => $order->payment_method?->value ?? $order->payment_method,
            'payment_status' => $order->payment_status?->value ?? $order->payment_status,
            'shipping_method' => $order->shippingMethod?->name,
            'created_at' => $order->created_at->toDateTimeString(),
            'first_item_image' => $this->formatImageUrl($order->items->first()?->product_image)
                ?? $this->getFirstImageUrl($order->items->first()?->product?->images),
        ];
    }

    /**
     * تنسيق تفاصيل الطلب
     */
    private function formatOrderDetails(OnlineOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $this->getStatusLabel($order->status),
            'status_color' => $this->getStatusColor($order->status),
            // عملةُ الطلب المخزَّنة عليه — لا عملة النظام الحالية
            'currency' => $order->documentCurrency(),

            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_image' => $this->formatImageUrl($item->product_image) ?? $this->getFirstImageUrl($item->product?->images),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'total' => $item->total,
                'options' => $item->options,
                'variant_id' => is_array($item->options) ? ($item->options['variant_id'] ?? null) : null,
                'variant_label' => is_array($item->options) ? ($item->options['variant_label'] ?? null) : null,
                'variant_sku' => is_array($item->options) ? ($item->options['variant_sku'] ?? null) : null,
                'selected_attributes' => $this->formatSelectedAttributes($item->options),
            ]),

            'address' => $order->address ? [
                'title' => $order->address->title,
                'receiver_name' => $order->shipping_name,
                'receiver_phone' => $order->shipping_phone,
                'full_address' => $order->address->full_address,
                'city' => $order->shipping_city,
                'region' => $order->shipping_region,
            ] : null,

            'shipping_method' => $order->shippingMethod ? [
                'name' => $order->shippingMethod->name,
                'cost' => $order->shipping_cost,
                'tax' => (float) ($order->shipping_tax_amount ?? 0),
                'estimated_delivery' => $order->shippingMethod->estimated_delivery_text,
            ] : null,

            'coupon' => $order->coupon ? [
                'code' => $order->coupon->code,
                'discount' => $order->discount_amount,
            ] : null,

            'payment' => [
                'method' => $order->payment_method instanceof PaymentMethod
                    ? $order->payment_method->label()
                    : (PaymentMethod::tryFrom($order->payment_method)?->label() ?? $order->payment_method ?? 'غير محدد'),
                'method_value' => $order->payment_method?->value ?? $order->payment_method,
                'status' => $order->payment_status?->value ?? $order->payment_status,
            ],

            'totals' => [
                'subtotal' => (float) ($order->subtotal ?? 0),
                'discount' => (float) ($order->discount_amount ?? 0),
                'tax' => (float) ($order->tax_amount ?? 0),
                'tax_included' => (bool) $order->prices_include_tax,
                'refunded' => (float) ($order->refund_amount ?? 0),
                'shipping' => (float) ($order->shipping_cost ?? 0),
                'shipping_tax' => (float) ($order->shipping_tax_amount ?? 0),
                'grand_total' => (float) ($order->total_amount ?? $order->grand_total ?? 0),
            ],

            'tracking' => [
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
            ],

            'notes' => $order->client_notes,

            'status_history' => $order->statusHistory?->map(fn ($history) => [
                'status' => $history->status,
                'status_label' => $history->status_name,
                'notes' => $history->notes,
                'date' => $history->created_at->toDateTimeString(),
            ]),

            'extra_fees' => $order->extraFees?->map(fn ($fee) => [
                'name' => $fee->getNameForLocale(),
                'amount' => $fee->amount,
                'type' => $fee->type?->value,
            ]),

            'created_at' => $order->created_at->toDateTimeString(),
            'updated_at' => $order->updated_at->toDateTimeString(),
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
     * الحصول على اسم الحالة
     */
    private function getStatusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PENDING => 'قيد الانتظار',
            OrderStatus::CONFIRMED => 'تم التأكيد',
            OrderStatus::PROCESSING => 'قيد التجهيز',
            OrderStatus::SHIPPED => 'تم الشحن',
            OrderStatus::OUT_FOR_DELIVERY => 'جاري التوصيل',
            OrderStatus::DELIVERED => 'تم التوصيل',
            OrderStatus::CANCELLED => 'ملغي',
            OrderStatus::RETURNED => 'مرتجع',
            OrderStatus::REFUNDED => 'مسترد',
            default => $status->value,
        };
    }

    /**
     * الحصول على لون الحالة
     */
    private function getStatusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PENDING => 'warning',
            OrderStatus::CONFIRMED => 'info',
            OrderStatus::PROCESSING => 'primary',
            OrderStatus::SHIPPED => 'info',
            OrderStatus::OUT_FOR_DELIVERY => 'primary',
            OrderStatus::DELIVERED => 'success',
            OrderStatus::CANCELLED => 'danger',
            OrderStatus::RETURNED => 'secondary',
            OrderStatus::REFUNDED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * تحويل طريقة الدفع من النص
     */
    private function mapPaymentMethod(string $method): PaymentMethod
    {
        return match ($method) {
            'cash' => PaymentMethod::CASH,
            'card' => PaymentMethod::CARD,
            'bank_transfer' => PaymentMethod::BANK_TRANSFER,
            'wallet' => PaymentMethod::WALLET,
            default => PaymentMethod::CASH,
        };
    }

    /**
     * حفظ بطاقة جديدة للعميل
     */
    private function saveNewCard($client, \App\Models\PaymentMethod $paymentMethod, Request $request): void
    {
        try {
            $cardNumber = preg_replace('/\D/', '', $request->card_number);
            $last4Digits = substr($cardNumber, -4);

            // تحديد نوع البطاقة
            $cardType = 'other';
            if (preg_match('/^4/', $cardNumber)) {
                $cardType = 'visa';
            } elseif (preg_match('/^5[1-5]/', $cardNumber) || preg_match('/^2[2-7]/', $cardNumber)) {
                $cardType = 'mastercard';
            } elseif (preg_match('/^3[47]/', $cardNumber)) {
                $cardType = 'amex';
            } elseif (preg_match('/^(588845|440647|440795|446404|457865|968)/', $cardNumber)) {
                $cardType = 'mada';
            }

            // استخراج شهر وسنة انتهاء الصلاحية
            $expiry = $request->card_expiry;
            $expiryParts = explode('/', $expiry);
            $expiryMonth = isset($expiryParts[0]) ? (int) $expiryParts[0] : null;
            $expiryYear = isset($expiryParts[1]) ? (int) ('20'.$expiryParts[1]) : null;

            // التحقق من عدم وجود بطاقة مكررة
            $existingCard = ClientPaymentCard::where('client_id', $client->id)
                ->where('last_four_digits', $last4Digits)
                ->where('expiry_month', $expiryMonth)
                ->where('expiry_year', $expiryYear)
                ->first();

            if (! $existingCard) {
                $isFirstCard = ClientPaymentCard::where('client_id', $client->id)->count() === 0;

                ClientPaymentCard::create([
                    'client_id' => $client->id,
                    'payment_method_id' => $paymentMethod->id,
                    'card_type' => $cardType,
                    'last_four_digits' => $last4Digits,
                    'card_holder_name' => $request->card_holder,
                    'expiry_month' => $expiryMonth,
                    'expiry_year' => $expiryYear,
                    'is_default' => $isFirstCard,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // تجاهل أخطاء حفظ البطاقة - لا نريد إيقاف الطلب بسببها
            \Log::error('Error saving payment card: '.$e->getMessage());
        }
    }

    /**
     * تحويل طريقة الدفع من الموديل
     */
    private function mapPaymentMethodFromModel(\App\Models\PaymentMethod $paymentMethod): PaymentMethod
    {
        /*
            `payment_methods.type` في الجدول `normal`/`electronic` لا `cash`/`card`:
            كانت الخريطة تسقط دائماً على الافتراضي فيُحفظ كلُّ طلبٍ «نقدي» ولو
            دُفع ببوّابة إلكترونية. التصنيف الآن بدوالّ الموديل نفسها.
        */
        if ($this->paymentMethodIsBankTransfer($paymentMethod)) {
            return PaymentMethod::BANK_TRANSFER;
        }

        if (in_array($paymentMethod->type, ['wallet', 'e_wallet'], true)) {
            return PaymentMethod::WALLET;
        }

        if ($this->paymentMethodIsPrepaid($paymentMethod)) {
            return PaymentMethod::CARD;
        }

        // نقدٌ من متجرٍ إلكتروني = دفعٌ عند الاستلام
        return $paymentMethod->isCash() || in_array($paymentMethod->type, ['cash', 'cod'], true)
            ? PaymentMethod::COD
            : PaymentMethod::CASH;
    }

    /**
     * حساب موعد التوصيل المتوقع
     */
    private function calculateEstimatedDelivery(OnlineOrder $order): ?string
    {
        if (! $order->shippingMethod) {
            return null;
        }

        $minDays = $order->shippingMethod->estimated_days_min ?? 1;
        $maxDays = $order->shippingMethod->estimated_days_max ?? 7;

        $minDate = $order->created_at->addDays($minDays)->format('Y-m-d');
        $maxDate = $order->created_at->addDays($maxDays)->format('Y-m-d');

        return "{$minDate} - {$maxDate}";
    }

    // ==================== دوال البريد الإلكتروني ====================

    /**
     * إرسال بريد تأكيد الطلب
     */
    private function sendOrderConfirmationEmail(OnlineOrder $order): void
    {
        try {
            $order->load(['items.product', 'client', 'shippingMethod']);

            if (! $order->client || ! $order->client->email) {
                return;
            }

            $orderItems = $this->formatOrderItemsForEmail($order);
            $paymentMethodLabel = $this->getPaymentMethodLabel($order->payment_method);

            EmailTemplate::sendTemplatedEmail(
                to: $order->client->email,
                templateCode: 'order.confirmed',
                variables: [
                    'customer_name' => $order->client->first_name.' '.$order->client->last_name,
                    'order_number' => $order->order_number,
                    'order_date' => $order->created_at->format('Y-m-d H:i'),
                    'order_items' => $orderItems,
                    'subtotal' => $order->formatMoney($order->subtotal),
                    // القالب المخزَّن يعرض {{ shipping_cost }} وحده: ضريبة الشحن تُلحَق بسطره حين تكون > 0
                    'shipping_cost' => $order->formatMoney($order->shipping_cost)
                        .((float) $order->shipping_tax_amount > 0 ? ' + ضريبة الشحن '.$order->formatMoney($order->shipping_tax_amount) : ''),
                    'shipping_tax' => $order->formatMoney($order->shipping_tax_amount),
                    'tax' => $order->formatMoney($order->tax_amount),
                    'discount' => $order->formatMoney(-1 * (float) $order->discount_amount),
                    'total' => $order->formatMoney($order->total_amount),
                    'shipping_address' => $this->formatShippingAddress($order),
                    'payment_method' => $paymentMethodLabel,
                    'tracking_url' => url("/track/{$order->order_number}"),
                    'store_name' => config('app.name', 'المتجر الإلكتروني'),
                ],
                recipientType: RecipientType::Customer,
                recipientId: $order->client->id
            );
        } catch (\Exception $e) {
            \Log::error('فشل إرسال بريد تأكيد الطلب: '.$e->getMessage());
        }
    }

    /**
     * إرسال بريد إلغاء الطلب
     */
    private function sendOrderCancelledEmail(OnlineOrder $order, string $reason): void
    {
        try {
            $order->load(['client']);

            if (! $order->client || ! $order->client->email) {
                return;
            }

            // ما رُدّ فعلاً من الخزينة — صفرٌ لطلبٍ لم يكن محصَّلاً
            $refundAmount = $order->payment_status === PaymentStatus::REFUNDED ? (float) $order->refund_amount : 0;
            $refundDays = 5; // يمكن جلبها من الإعدادات

            EmailTemplate::sendTemplatedEmail(
                to: $order->client->email,
                templateCode: 'order.cancelled',
                variables: [
                    'customer_name' => $order->client->first_name.' '.$order->client->last_name,
                    'order_number' => $order->order_number,
                    'cancellation_reason' => $reason,
                    'refund_amount' => $order->formatMoney($refundAmount),
                    'refund_days' => $refundDays,
                    'store_name' => config('app.name', 'المتجر الإلكتروني'),
                ],
                recipientType: RecipientType::Customer,
                recipientId: $order->client->id
            );
        } catch (\Exception $e) {
            \Log::error('فشل إرسال بريد إلغاء الطلب: '.$e->getMessage());
        }
    }

    /**
     * تنسيق عناصر الطلب للبريد الإلكتروني
     */
    private function formatOrderItemsForEmail(OnlineOrder $order): string
    {
        $itemsHtml = '';

        foreach ($order->items as $item) {
            $itemsHtml .= sprintf(
                '<div style="border-bottom:1px solid #ddd;padding:10px 0;">'.
                '<p style="margin:5px 0;"><strong>%s</strong></p>'.
                '<p style="margin:5px 0;color:#666;">الكمية: %d × %s ريال = <strong>%s ريال</strong></p>'.
                '</div>',
                $item->product_name,
                $item->quantity,
                number_format($item->unit_price, 2),
                number_format($item->total, 2)
            );
        }

        return $itemsHtml ?: '<p>لا توجد منتجات</p>';
    }

    /**
     * تنسيق عنوان الشحن للبريد الإلكتروني
     */
    private function formatShippingAddress(OnlineOrder $order): string
    {
        $parts = array_filter([
            $order->shipping_name,
            $order->shipping_phone,
            $order->shipping_address_line,
            $order->shipping_street,
            $order->shipping_district,
            $order->shipping_region,
            $order->shipping_city,
            $order->shipping_country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * الحصول على تسمية طريقة الدفع
     */
    private function getPaymentMethodLabel($method): string
    {
        if ($method instanceof PaymentMethod) {
            return match ($method) {
                PaymentMethod::CASH => 'الدفع عند الاستلام',
                PaymentMethod::CARD => 'بطاقة ائتمان',
                PaymentMethod::BANK_TRANSFER => 'تحويل بنكي',
                PaymentMethod::WALLET => 'محفظة إلكترونية',
                default => $method->value,
            };
        }

        return match ($method) {
            'cash' => 'الدفع عند الاستلام',
            'card' => 'بطاقة ائتمان',
            'bank_transfer' => 'تحويل بنكي',
            'wallet' => 'محفظة إلكترونية',
            default => $method,
        };
    }

    // ==================== دوال الفواتير والقيود اليومية ====================

    /**
     * توليد كود الفاتورة
     */
    private function generateInvoiceCode(): string
    {
        /* من تسلسل فواتير المبيعات نفسه. كان الرقم «آخر رقمٍ + ١» بلا قفل
           وبلا بادئة: `intval('INV-00049')` صفر، فيبدأ المتجر سلسلةً ثانية
           من `00001` داخل الجدول نفسه — وطلبان متزامنان يأخذان الرقم نفسه.
           والهيئة تشترط تسلسلاً واحداً متّصلاً لفواتير المنشأة. */
        return next_document_number('invoice');
    }

    /**
     * توليد محتوى TLV لـ QR Code (ZATCA)
     * Tag-Length-Value format for Saudi e-invoicing
     */
    private function generateTlvContent($timestamp, $totalAmount, $vatAmount): string
    {
        /* البائع المنشأةُ لا المتجر: كان الرمز يحمل «اسم المتجر» ورقماً
           ضريبياً من إعدادات المتجر — وأصفاراً إن لم يُضبَط — فتخرج فاتورة
           المتجر باسمٍ ورقمٍ غير اللذين على فواتير المنشأة نفسها. والفاتورة
           الضريبية يصدرها المكلَّف، والمتجر قناةُ بيعٍ له. */
        return ZatcaQr::phaseOne($timestamp, (float) $totalAmount, (float) $vatAmount);
    }

    /**
     * إنشاء عنصر TLV
     */
    private function getTlv($tag, $value): string
    {
        $value = (string) $value;

        return pack('C', $tag).pack('C', strlen($value)).$value;
    }

    /**
     * إنشاء سجلات الضرائب للفاتورة
     */
    private function createTaxInvoiceRecords(Invoice $invoice, array $calculatedItems, float $taxRate): void
    {
        try {
            $defaultTaxSetting = $this->resolveTaxSetting($taxRate);

            if ($defaultTaxSetting) {
                foreach ($calculatedItems as $item) {
                    $itemTax = $item['item_total'] * $taxRate;

                    if ($itemTax > 0) {
                        TaxInvoice::create([
                            'invoice_id' => $invoice->id,
                            'tax_id' => $defaultTaxSetting->id,
                            'tax_name' => $defaultTaxSetting->name ?? 'ضريبة القيمة المضافة',
                            'tax_rate' => $taxRate * 100,
                            'tax_amount' => $itemTax,
                            'type_invoice' => 'invoice',
                        ]);
                    }
                }

                Log::info('تم إنشاء سجلات الضرائب للفاتورة الأونلاين', [
                    'invoice_id' => $invoice->id,
                    'items_count' => count($calculatedItems),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('تعذر إنشاء سجلات الضرائب للفاتورة الأونلاين', [
                'invoice_id' => $invoice->id,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ]);
            // لا نرمي استثناء لأن هذا ليس حرجاً
        }
    }

    /**
     * إعداد الضريبة (`TaxSitting`) المطابق لنسبة الضريبة المطبّقة على الطلب.
     *
     * كان البحث يستعلم عمودين غير موجودين أصلاً في `tax_sittings`
     * (`is_default`، `rate`) — فيفشل الاستعلام دائماً ويُبتلع الخطأ في
     * catch الخارجي بصمت، فلا تُنشأ سجلات TaxInvoice ولا يُربط أي بند
     * طلب بضريبته الحقيقية. العمود الفعلي في الجدول هو `tax` وحده
     * (`App\Models\TaxSitting::$fillable`).
     */
    private function resolveTaxSetting(float $taxRate): ?TaxSitting
    {
        if ($taxRate <= 0) {
            return null;
        }

        return TaxSitting::where('tax', round($taxRate * 100, 2))->first();
    }

    /**
     * خصمُ كميات الطلب من مستودع المتجر وإنشاء أذونات الصرف — وإرجاعُ تكلفة
     * المبيعات (سعر الشراء × الكمية) ليُرحَّل بها قيدُ التكلفة مرّةً واحدة.
     *
     * الرصيد يُقرأ **بقفل الصفّ داخل المعاملة** ويُفحَص هنا من جديد: الفحص
     * الأول قبل المعاملة بلا قفل، فطلبان متزامنان على آخر قطعة كانا يمرّان
     * معاً ويهبط الرصيد تحت الصفر مع أن «البيع بالسالب» مُعطَّل.
     *
     * @return float تكلفة المبيعات الإجمالية
     */
    private function updateInventory(array $calculatedItems, Invoice $invoice, StoreHouse $defaultStorehouse): float
    {
        $permissionSource = PermissionSource::find(PermissionSources::SALES_INVOICE);
        $allowNegativeStock = (bool) OnlineStoreSettingsService::get('allow_negative_stock', false);
        $createdById = $invoice->created_by ?? 1;
        $costTotal = 0.0;

        foreach ($calculatedItems as $item) {
            $productId = $item['product_id'];
            $storeHouseId = $item['store_house_id'] ?? $defaultStorehouse->id;
            $quantity = (int) $item['quantity'];
            $product = $item['product'];

            $productDetails = ProductDetails::where('product_id', $productId)
                ->where('store_house_id', $storeHouseId)
                ->lockForUpdate()
                ->first();

            if (! $productDetails) {
                $productDetails = ProductDetails::create([
                    'product_id' => $productId,
                    'store_house_id' => $storeHouseId,
                    'quantity' => 0,
                ]);
            }

            $stockBefore = (float) $productDetails->quantity;
            $stockAfter = $stockBefore - $quantity;

            if (! $allowNegativeStock && $stockAfter < 0) {
                throw new \DomainException(
                    "الكمية المطلوبة من {$product->name} لم تعد متاحة. الكمية المتاحة: ".max(0, $stockBefore)
                );
            }

            $productDetails->decrement('quantity', $quantity);

            $costTotal += (float) ($product->purchase_price ?? 0) * $quantity;

            if ($permissionSource) {
                $warehousePermit = WarehousePermits::create([
                    'permission_source_id' => $permissionSource->id,
                    'permission_date' => $invoice->created_at,
                    'number' => $invoice->id,
                    'grand_total' => $item['item_total'],
                    'store_houses_id' => $storeHouseId,
                    'reference_type' => 'Invoice',
                    'reference_id' => $invoice->id,
                    'created_by' => $createdById,
                ]);

                WarehousePermitsProducts::create([
                    'warehouse_permits_id' => $warehousePermit->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $item['unit_price'],
                    'total' => $item['item_total'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                ]);
            }

            if ($product && isset($product->low_stock_alert) && $stockAfter < $product->low_stock_alert) {
                Log::warning('تنبيه انخفاض المخزون', [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'current_quantity' => $stockAfter,
                    'low_stock_alert' => $product->low_stock_alert,
                ]);
            }
        }

        return round($costTotal, 2);
    }
}
