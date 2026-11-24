<?php

namespace App\Services;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Country;
use App\Models\PaymentMethod;
use App\Models\Store\OnlineStoreSetting;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;

/**
 * خدمة إعدادات المتجر الإلكتروني
 * Online Store Settings Service
 *
 * توفر طريقة موحدة ومنظمة لجلب جميع إعدادات المتجر
 * يمكن استخدامها في أي مكان في التطبيق
 *
 * ملاحظة: لا يتم استخدام الكاش - يتم جلب الإعدادات مباشرة من قاعدة البيانات
 */
class OnlineStoreSettingsService
{
    /**
     * جلب جميع الإعدادات مباشرة من قاعدة البيانات
     */
    protected static function loadAllSettings(): array
    {
        $settings = OnlineStoreSetting::all();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = [
                'value' => $setting->value,
                'type' => $setting->type,
            ];
        }

        return $result;
    }

    /**
     * الحصول على قيمة إعداد محدد مع التحويل التلقائي للنوع
     */
    protected static function getValue(string $key, $default = null)
    {
        $settings = self::loadAllSettings();

        if (! isset($settings[$key])) {
            return $default;
        }

        $setting = $settings[$key];

        return self::castValue($setting['value'], $setting['type']);
    }

    /**
     * تحويل القيمة حسب النوع
     */
    protected static function castValue($value, $type)
    {
        if (is_null($value)) {
            return null;
        }

        switch ($type) {
            case 'boolean':
                return $value === '1' || $value === 'true' || $value === true;
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'json':
            case 'array':
                if (is_string($value)) {
                    return json_decode($value, true) ?? [];
                }

                return $value;
            default:
                return $value;
        }
    }

    /**
     * الحصول على قيمة إعداد محدد (واجهة عامة)
     * Get a specific setting value (public interface)
     */
    public static function get(string $key, $default = null)
    {
        return self::getValue($key, $default);
    }

    // ========================================
    // الإعدادات العامة - General Settings
    // ========================================

    /**
     * جلب إعدادات المتجر العامة
     */
    public static function getGeneralSettings(): array
    {
        return [
            'store_name' => self::getValue('store_name', 'المتجر الإلكتروني'),
            'store_name_en' => self::getValue('store_name_en', ''),
            'store_description' => self::getValue('store_description', ''),
            'store_email' => self::getValue('store_email', ''),
            'store_phone' => self::getValue('store_phone', ''),
            'store_address' => self::getValue('store_address', ''),
            'store_logo' => self::getValue('store_logo', ''),
            'store_favicon' => self::getValue('store_favicon', ''),
            'store_timezone' => self::getValue('store_timezone', 'Asia/Riyadh'),
        ];
    }

    // ========================================
    // إعدادات العملة - Currency Settings
    // ========================================

    /**
     * جلب إعدادات العملة — من عملة النظام الافتراضية (شاشة «العملات» في
     * الإعدادات العامة) لا إعدادٍ منفصل خاص بالمتجر.
     *
     * كان للمتجر عملتُه الخاصة (`store_currency`/`currency_symbol`/
     * `currency_position`) بمعزلٍ عن عملة النظام — فيَعرض المتجرُ عملةً
     * والفواتيرُ والتقاريرُ عملةً أخرى لنفس المنشأة. صار المصدر واحداً:
     * \App\Support\CurrencySettings، ويتبعه المتجر تلقائياً متى تغيّر.
     */
    public static function getCurrencySettings(): array
    {
        return [
            'currency' => CurrencySettings::code(),
            'currency_symbol' => CurrencySettings::symbol(),
            'currency_position' => self::resolveCurrencyPosition(),
        ];
    }

    /**
     * جلب رمز العملة
     */
    public static function getCurrencySymbol(): string
    {
        return CurrencySettings::symbol();
    }

    /**
     * جلب موضع العملة
     */
    public static function getCurrencyPosition(): string
    {
        return self::resolveCurrencyPosition();
    }

    /**
     * 'before'/'after' من `positive_format` عملة النظام — نفس منطق
     * CurrencySettings::attachSymbol() لكن كقيمةٍ نصية لواجهات المتجر
     * (تطبيق الجوال) بدل نصٍّ جاهز التنسيق.
     */
    private static function resolveCurrencyPosition(): string
    {
        $format = CurrencySettings::current()?->positive_format ?: 'standard';

        if ($format === 'prefix') {
            return 'before';
        }

        if ($format === 'suffix') {
            return 'after';
        }

        // 'standard': يتبع اتجاه اللغة — بعد الرقم في العربية (RTL)
        return LanguageSettings::isRtl() ? 'after' : 'before';
    }

    // ========================================
    // إعدادات اللغة - Language Settings
    // ========================================

    /**
     * جلب إعدادات اللغة
     */
    public static function getLanguageSettings(): array
    {
        return [
            'language' => self::getValue('language', 'ar'),
            'direction' => self::getValue('direction', 'rtl'),
            'timezone' => self::getValue('store_timezone', 'Asia/Riyadh'),
        ];
    }

    // ========================================
    // إعدادات المظهر - Appearance Settings
    // ========================================

    /**
     * جلب إعدادات المظهر
     */
    public static function getAppearanceSettings(): array
    {
        return [
            'theme_color' => self::getValue('theme_color', '#3B82F6'),
            'secondary_color' => self::getValue('secondary_color', '#10B981'),
            'products_per_page' => (int) self::getValue('products_per_page', 12),
            'show_out_of_stock' => (bool) self::getValue('show_out_of_stock', true),
            'allow_negative_stock' => (bool) self::getValue('allow_negative_stock', false),
            'max_recently_viewed' => (int) self::getValue('max_recently_viewed', 10),
        ];
    }

    // ========================================
    // إعدادات الطلبات - Checkout Settings
    // ========================================

    /**
     * جلب إعدادات الطلبات
     */
    public static function getCheckoutSettings(): array
    {
        return [
            'min_order_amount' => (float) self::getValue('min_order_amount', 0),
            'max_order_amount' => (float) self::getValue('max_order_amount', 10000),
            'enable_guest_checkout' => (bool) self::getValue('enable_guest_checkout', true),
            'tax_rate' => (float) self::getValue('tax_rate', 15),
            'tax_included' => (bool) self::getValue('tax_included', false),
            'require_phone_verification' => (bool) self::getValue('require_phone_verification', true),
            'require_email_verification' => (bool) self::getValue('require_email_verification', false),
            'cart_session_lifetime' => (int) self::getValue('cart_session_lifetime', 1440),
            'enable_coupons' => (bool) self::getValue('enable_coupons', true),
        ];
    }

    // ========================================
    // إعدادات التحقق من الهوية - Verification Settings
    // ========================================

    /**
     * هل يتطلب التحقق من البريد الإلكتروني
     */
    public static function requireEmailVerification(): bool
    {
        return (bool) self::getValue('require_email_verification', false);
    }

    /**
     * هل يتطلب التحقق من رقم الهاتف
     */
    public static function requirePhoneVerification(): bool
    {
        return (bool) self::getValue('require_phone_verification', true);
    }

    /**
     * جلب إعدادات التحقق
     */
    public static function getVerificationSettings(): array
    {
        return [
            'require_email_verification' => self::requireEmailVerification(),
            'require_phone_verification' => self::requirePhoneVerification(),
        ];
    }

    // ========================================
    // إعدادات الشحن - Shipping Settings
    // ========================================

    /**
     * جلب إعدادات الشحن
     */
    public static function getShippingSettings(): array
    {
        return [
            'free_shipping_threshold' => (float) self::getValue('free_shipping_threshold', 200),
            'default_shipping_cost' => (float) self::getValue('default_shipping_cost', 25),
            'default_shipping_method' => self::getValue('default_shipping_method', ''),
            'cod_fee' => (float) self::getValue('cod_fee', 10),
            'express_shipping_fee' => (float) self::getValue('express_shipping_fee', 35),
            'packaging_fee' => (float) self::getValue('packaging_fee', 5),
            'shipping_revenue_percentage' => (float) self::getValue('shipping_revenue_percentage', 0),
        ];
    }

    // ========================================
    // الحسابات المحاسبية - Accounting Accounts
    // ========================================

    /**
     * حساب محاسبي مُعرَّف في إعدادات المتجر — أو الحساب النظامي الافتراضي
     * (`SystemAccountIds`) إن لم يُضبط شيء في الإعدادات بعد.
     *
     * هذا هو نمط الترحيل التدريجي: متجرٌ لم يفتح شاشة «الحسابات المحاسبية»
     * قط يستمر بنفس السلوك القديم تماماً، ومن يخصّص حساباً من شجرة الحسابات
     * يرى أثره فوراً بلا أي تعديل آخر.
     */
    /**
     * حسابات قسم «الحسابات المحاسبية» في إعدادات المتجر، وافتراضيُّ كلٍّ منها
     * — وهو ما تعرضه الشاشة مختاراً حين لم يُحفظ تخصيص.
     *
     * @var array<string, int>
     */
    public const STORE_ACCOUNTS = [
        'sales_account_id' => SystemAccountIds::SALES,
        'sales_returns_account_id' => SystemAccountIds::SALES_RETURNS,
        'allowed_discount_account_id' => SystemAccountIds::ALLOWED_DISCOUNT,
        'cost_of_sales_account_id' => SystemAccountIds::COST_OF_SALES,
        'purchases_account_id' => SystemAccountIds::PURCHASES,
        'cash_client_account_id' => SystemAccountIds::CASH_CLIENT,
        'vat_payable_account_id' => SystemAccountIds::VAT_PAYABLE,
        'vat_paid_account_id' => SystemAccountIds::VAT_PAID,
        'shipping_revenue_account_id' => SystemAccountIds::SHIPPING_REVENUE,
        'shipping_revenue_returns_account_id' => SystemAccountIds::SHIPPING_REVENUE_RETURNS,
        // ضريبة الشحن (مخرجات): افتراضيُّها حسابُ ضريبة المخرجات نفسه — فيجتمع
        // في الإقرار الضريبي ما حُصِّل على المنتجات وعلى الشحن
        'shipping_vat_account_id' => SystemAccountIds::VAT_PAYABLE,
        // مصروف الشحن المجاني: ما تتحمّله المنشأة لشركة الشحن حين يكون الشحن
        // مجانياً للعميل. المعرّفُ هنا مؤشّرٌ فقط — الافتراضيُّ الفعلي يُحلّ
        // بالكود (5606 «شحن مبيعات») في {@see defaultAccountIds()}
        'free_shipping_expense_account_id' => 0,
    ];

    /** كودُ حساب المصروف الافتراضي لشحن المبيعات المجاني (شجرة الحسابات: مصروفات تسويق ومبيعات ← شحن مبيعات). */
    public const FREE_SHIPPING_EXPENSE_CODES = ['5606', '5607'];

    /**
     * افتراضيُّ كل بند في قسم «الحسابات المحاسبية» — ما تعرضه الشاشة مختاراً
     * وما يُرحَّل عليه حين لا تخصيص. مصروفُ الشحن المجاني يُحلّ بالكود لا
     * بالمعرّف (يختلف المعرّف بين قواعد البيانات) وبشرط أن يكون حساب مصروفٍ
     * فعّالاً لا يتفرّع منه غيره.
     *
     * @return array<string, int|null>
     */
    public static function defaultAccountIds(): array
    {
        $defaults = self::STORE_ACCOUNTS;
        $defaults['free_shipping_expense_account_id'] = self::resolveFreeShippingExpenseDefault();

        return $defaults;
    }

    private static function resolveFreeShippingExpenseDefault(): ?int
    {
        foreach (self::FREE_SHIPPING_EXPENSE_CODES as $code) {
            $id = Account::where('code', $code)
                ->where('account_type', 'expenses')
                ->where('is_active', 1)
                ->whereDoesntHave('children')
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * الحساب الذي يرحّل عليه المتجر لهذا البند: ما اختير في إعدادات المتجر،
     * وإلا الافتراضي المعروض في الشاشة نفسها. **لا** يُقرأ ربط «إعدادات
     * الحسابات» العام في النظام: للمتجر قسمه، وما يراه المالك فيه هو ما
     * يُرحَّل عليه — كان الترحيل يذهب إلى ربط النظام والشاشة تعرض حساباً آخر.
     */
    public static function storeAccountId(string $key): ?int
    {
        $saved = self::getAccountId($key, null);

        if ($saved) {
            return $saved;
        }

        // استعلامُ الحساب الافتراضي لمصروف الشحن المجاني لا يُنفَّذ إلا لبنده هو
        if ($key === 'free_shipping_expense_account_id') {
            return self::resolveFreeShippingExpenseDefault();
        }

        return self::STORE_ACCOUNTS[$key] ?? null;
    }

    // ========================================
    // الخرائط - Google Maps
    // ========================================

    /** مركزُ الخريطة الافتراضي لكل دولة (عاصمتها) — يُستعمل حين لا يُضبط إحداثيٌّ للمتجر. */
    private const COUNTRY_CENTERS = [
        'SA' => [24.7136, 46.6753],
        'AE' => [24.4539, 54.3773],
        'KW' => [29.3759, 47.9774],
        'BH' => [26.2285, 50.5860],
        'OM' => [23.5880, 58.3829],
        'QA' => [25.2854, 51.5310],
        'EG' => [30.0444, 31.2357],
        'JO' => [31.9454, 35.9284],
    ];

    /**
     * إعدادات الخريطة للواجهة: مفتاحُ Google من إعدادات المتجر (`google_maps_api_key`)،
     * وإن كان فارغاً فمن إعداد النظام (`services.google_maps.key` ← GOOGLE_MAPS_API_KEY).
     * الدولةُ الافتراضية: إعدادُ المتجر `default_country_code` إن وُجد، وإلا الدولة
     * المعلَّمة افتراضيةً في جدول الدول، وإلا السعودية؛ والمركز الرياض.
     * المفتاحُ مفتاحُ متصفّحٍ عامّ (يُقيَّد بالنطاق في لوحة Google) ولا يُسجَّل في أي سجل.
     */
    public static function getMapsSettings(): array
    {
        $key = trim((string) self::getValue('google_maps_api_key', ''));

        if ($key === '') {
            $key = trim((string) config('services.google_maps.key', ''));
        }

        $code = strtoupper(trim((string) self::getValue('default_country_code', '')));

        if ($code === '') {
            $code = strtoupper((string) Country::where('is_default', 1)->where('status', 1)->value('code'));
        }

        $code = $code !== '' ? $code : 'SA';
        $center = self::COUNTRY_CENTERS[$code] ?? self::COUNTRY_CENTERS['SA'];

        $lat = self::getValue('default_lat', null);
        $lng = self::getValue('default_lng', null);

        return [
            'google_maps_api_key' => $key,
            'default_country_code' => $code,
            'default_lat' => is_numeric($lat) ? (float) $lat : $center[0],
            'default_lng' => is_numeric($lng) ? (float) $lng : $center[1],
        ];
    }

    // ========================================
    // ضريبة الشحن - Shipping VAT
    // ========================================

    /**
     * ضريبةُ الشحن = رسمُ الشحن × نسبة ضريبة المتجر، **مضافةً فوقه** دائماً:
     * رسمُ الشحن لا يتضمّن ضريبةً حتى لو كانت أسعار المنتجات شاملة. شحنٌ
     * مجاني (صفر) ⇒ صفر.
     */
    public static function shippingVatFor(float $shippingFee): float
    {
        if ($shippingFee <= 0) {
            return 0.0;
        }

        return round($shippingFee * max(0, self::getTaxRate()) / 100, 2);
    }

    public static function getAccountId(string $key, ?int $fallback = null): ?int
    {
        $value = self::getValue($key, null);

        return $value ? (int) $value : $fallback;
    }

    // ========================================
    // إعدادات طرق الدفع - Payment Methods Settings
    // ========================================

    /**
     * جلب إعدادات طرق الدفع
     */
    public static function getPaymentMethodsSettings(): array
    {
        return [
            'default_payment_method_id' => (int) self::getValue('default_payment_method_id', 0),
            'enabled_payment_methods' => self::getEnabledPaymentMethods(),
        ];
    }

    /**
     * جلب معرف طريقة الدفع الافتراضية
     */
    public static function getDefaultPaymentMethodId(): ?int
    {
        $value = self::getValue('default_payment_method_id', null);

        return $value ? (int) $value : null;
    }

    /**
     * طرق الدفع التي يقبلها المتجر فعلاً — للسلة ولإتمام الطلب معاً.
     *
     * ما يُفعَّل في «إعدادات المتجر ← طرق الدفع المتاحة» هو المرجع: كانت
     * السلة وإتمام الطلب يقرآن `payment_methods.is_online` وحده، فطريقةٌ
     * فعّلها المالك للمتجر (ككاش «الدفع عند الاستلام») تُرفض بـ«طريقة الدفع
     * غير متاحة»، والإعداد يُحفظ ولا يُقرأ. وإن لم يُضبط الإعداد بقي السلوك
     * السابق (الطرق المعلَّمة «أونلاين»).
     */
    public static function storePaymentMethodsQuery()
    {
        $enabled = self::getEnabledPaymentMethods();
        $query = PaymentMethod::active();

        return $enabled ? $query->whereIn('id', $enabled) : $query->online();
    }

    /**
     * جلب مصفوفة طرق الدفع المفعلة
     */
    public static function getEnabledPaymentMethods(): array
    {
        $value = self::getValue('enabled_payment_methods', '[]');

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_map('intval', $decoded) : [];
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        return [];
    }

    /**
     * التحقق مما إذا كانت طريقة دفع معينة مفعلة
     */
    public static function isPaymentMethodEnabled(int $paymentMethodId): bool
    {
        $enabledMethods = self::getEnabledPaymentMethods();

        return in_array($paymentMethodId, $enabledMethods);
    }

    // ========================================
    // إعدادات SEO
    // ========================================

    /**
     * جلب إعدادات SEO
     */
    public static function getSeoSettings(): array
    {
        return [
            'meta_title' => self::getValue('meta_title', ''),
            'meta_description' => self::getValue('meta_description', ''),
            'meta_keywords' => self::getValue('meta_keywords', ''),
            'google_analytics_id' => self::getValue('google_analytics_id', ''),
            'facebook_pixel_id' => self::getValue('facebook_pixel_id', ''),
        ];
    }

    // ========================================
    // إعدادات الإشعارات - Notification Settings
    // ========================================

    /**
     * جلب إعدادات الإشعارات
     */
    public static function getNotificationSettings(): array
    {
        return [
            'enable_email_notifications' => (bool) self::getValue('enable_email_notifications', true),
            'enable_sms_notifications' => (bool) self::getValue('enable_sms_notifications', true),
            'enable_push_notifications' => (bool) self::getValue('enable_push_notifications', true),
        ];
    }

    // ========================================
    // إعدادات التواصل الاجتماعي - Social Settings
    // ========================================

    /**
     * جلب روابط التواصل الاجتماعي
     */
    public static function getSocialSettings(): array
    {
        return [
            'facebook' => self::getValue('social_facebook', ''),
            'twitter' => self::getValue('social_twitter', ''),
            'instagram' => self::getValue('social_instagram', ''),
            'whatsapp' => self::getValue('social_whatsapp', ''),
            'youtube' => self::getValue('social_youtube', ''),
            'tiktok' => self::getValue('social_tiktok', ''),
            'snapchat' => self::getValue('social_snapchat', ''),
            'linkedin' => self::getValue('social_linkedin', ''),
            'telegram' => self::getValue('social_telegram', ''),
        ];
    }

    // ========================================
    // إعدادات الميزات - Feature Settings
    // ========================================

    /**
     * جلب إعدادات الميزات
     */
    public static function getFeatureSettings(): array
    {
        return [
            'enable_reviews' => (bool) self::getValue('enable_reviews', true),
            'enable_questions' => (bool) self::getValue('enable_questions', true),
            'enable_wishlist' => (bool) self::getValue('enable_wishlist', true),
            'enable_compare' => (bool) self::getValue('enable_compare', true),
            'enable_coupons' => (bool) self::getValue('enable_coupons', true),
            'enable_multi_language' => (bool) self::getValue('enable_multi_language', true),
            'enable_cache' => (bool) self::getValue('enable_cache', true),
        ];
    }

    // ========================================
    // إعدادات الفلاتر والعرض - Filter & Display Settings
    // ========================================

    /**
     * جلب إعدادات الفلاتر
     */
    public static function getFilterSettings(): array
    {
        return [
            'max_categories' => (int) self::getValue('filter_max_categories', 10),
            'max_brands' => (int) self::getValue('filter_max_brands', 10),
            'max_featured_products' => (int) self::getValue('max_featured_products', 8),
            'max_new_arrivals' => (int) self::getValue('max_new_arrivals', 8),
            'max_top_rated_products' => (int) self::getValue('max_top_rated_products', 5),
            'max_best_selling_products' => (int) self::getValue('max_best_selling_products', 8),
            'show_category_stock' => (bool) self::getValue('show_category_stock', true),
            'show_sub_categories' => (bool) self::getValue('show_sub_categories', true),
            'show_category_description' => (bool) self::getValue('show_category_description', true),
        ];
    }

    /**
     * جلب نطاقات الأسعار
     */
    public static function getPriceRanges(): array
    {
        $priceRanges = self::getValue('price_ranges', null);

        if (is_string($priceRanges)) {
            $priceRanges = json_decode($priceRanges, true);
        }

        if (empty($priceRanges)) {
            return [
                ['key' => 'all', 'min' => null, 'max' => null],
                ['key' => 'range_1', 'min' => 0, 'max' => 100],
                ['key' => 'range_2', 'min' => 100, 'max' => 500],
                ['key' => 'range_3', 'min' => 500, 'max' => 1000],
                ['key' => 'range_4', 'min' => 1000, 'max' => null],
            ];
        }

        return $priceRanges;
    }

    // ========================================
    // إعدادات التقييمات - Review Settings
    // ========================================

    /**
     * جلب إعدادات التقييمات
     */
    public static function getReviewSettings(): array
    {
        return [
            'enable_reviews' => (bool) self::getValue('enable_reviews', true),
            'auto_approve_reviews' => (bool) self::getValue('auto_approve_reviews', false),
        ];
    }

    // ========================================
    // إعدادات متقدمة - Advanced Settings
    // ========================================

    /**
     * جلب الإعدادات المتقدمة
     */
    public static function getAdvancedSettings(): array
    {
        return [
            'enable_multi_language' => (bool) self::getValue('enable_multi_language', true),
            'enable_cache' => (bool) self::getValue('enable_cache', true),
            'maintenance_mode' => (bool) self::getValue('maintenance_mode', false),
            'auto_approve_reviews' => (bool) self::getValue('auto_approve_reviews', false),
        ];
    }

    // ========================================
    // دوال مساعدة للحصول على قيم فردية
    // ========================================

    /**
     * الحد الأقصى للأصناف
     */
    public static function getMaxCategories(): int
    {
        return (int) self::getValue('filter_max_categories', 10);
    }

    /**
     * الحد الأقصى للبراندات
     */
    public static function getMaxBrands(): int
    {
        return (int) self::getValue('filter_max_brands', 10);
    }

    /**
     * الحد الأقصى للمنتجات المميزة
     */
    public static function getMaxFeaturedProducts(): int
    {
        return (int) self::getValue('max_featured_products', 8);
    }

    /**
     * الحد الأقصى للمنتجات الجديدة
     */
    public static function getMaxNewArrivals(): int
    {
        return (int) self::getValue('max_new_arrivals', 8);
    }

    /**
     * الحد الأقصى لأفضل المنتجات تقييماً
     */
    public static function getMaxTopRatedProducts(): int
    {
        return (int) self::getValue('max_top_rated_products', 5);
    }

    /**
     * الحد الأقصى للمنتجات الأكثر مبيعاً
     */
    public static function getMaxBestSellingProducts(): int
    {
        return (int) self::getValue('max_best_selling_products', 8);
    }

    /**
     * الحد الأقصى للمنتجات المشاهدة مؤخراً
     */
    public static function getMaxRecentlyViewed(): int
    {
        return (int) self::getValue('max_recently_viewed', 10);
    }

    /**
     * عرض مخزون الأصناف
     */
    public static function showCategoryStock(): bool
    {
        return (bool) self::getValue('show_category_stock', true);
    }

    /**
     * عرض الأصناف الفرعية
     */
    public static function showSubCategories(): bool
    {
        return (bool) self::getValue('show_sub_categories', true);
    }

    /**
     * عرض وصف الأصناف
     */
    public static function showCategoryDescription(): bool
    {
        return (bool) self::getValue('show_category_description', true);
    }

    /**
     * حالة الصيانة
     */
    public static function isMaintenanceMode(): bool
    {
        return (bool) self::getValue('maintenance_mode', false);
    }

    /**
     * نسبة الضريبة
     */
    public static function getTaxRate(): float
    {
        return (float) self::getValue('tax_rate', 15);
    }

    /**
     * هل الأسعار شاملة الضريبة
     */
    public static function isTaxIncluded(): bool
    {
        return (bool) self::getValue('tax_included', false);
    }

    /**
     * الحد الأدنى للطلب
     */
    public static function getMinOrderAmount(): float
    {
        return (float) self::getValue('min_order_amount', 0);
    }

    /**
     * الحد الأقصى للطلب
     */
    public static function getMaxOrderAmount(): float
    {
        return (float) self::getValue('max_order_amount', 10000);
    }

    /**
     * حد الشحن المجاني
     */
    public static function getFreeShippingThreshold(): float
    {
        return (float) self::getValue('free_shipping_threshold', 200);
    }

    /**
     * تكلفة الشحن الافتراضية
     */
    public static function getDefaultShippingCost(): float
    {
        return (float) self::getValue('default_shipping_cost', 25);
    }

    /**
     * عدد المنتجات في الصفحة
     */
    public static function getProductsPerPage(): int
    {
        return (int) self::getValue('products_per_page', 12);
    }

    // ========================================
    // جلب جميع الإعدادات مرة واحدة
    // ========================================

    /**
     * جلب جميع الإعدادات للـ API
     * يُستخدم في endpoint الـ settings
     */
    public static function getAllSettingsForApi(): array
    {
        return [
            // الإعدادات العامة
            'general' => self::getGeneralSettings(),

            // إعدادات العملة
            'currency' => self::getCurrencySettings(),

            // إعدادات اللغة
            'language' => self::getLanguageSettings(),

            // إعدادات المظهر
            'appearance' => self::getAppearanceSettings(),

            // إعدادات الطلبات
            'checkout' => self::getCheckoutSettings(),

            // إعدادات الشحن
            'shipping' => self::getShippingSettings(),

            // إعدادات SEO
            'seo' => self::getSeoSettings(),

            // إعدادات الإشعارات
            'notifications' => self::getNotificationSettings(),

            // روابط التواصل الاجتماعي
            'social' => self::getSocialSettings(),

            // إعدادات الميزات
            'features' => self::getFeatureSettings(),

            // إعدادات الفلاتر
            'filters' => self::getFilterSettings(),

            // نطاقات الأسعار
            'price_ranges' => self::getPriceRanges(),

            // إعدادات التقييمات
            'reviews' => self::getReviewSettings(),

            // إعدادات متقدمة
            'advanced' => self::getAdvancedSettings(),
        ];
    }

    /**
     * جلب الإعدادات الأساسية للصفحة الرئيسية
     * يُستخدم في دالة index للتحسين
     */
    public static function getHomePageSettings(): array
    {
        $currency = self::getCurrencySettings();
        $filters = self::getFilterSettings();
        $appearance = self::getAppearanceSettings();

        return [
            // العملة
            'currency_symbol' => $currency['currency_symbol'],
            'currency_position' => $currency['currency_position'],

            // الفلاتر والعرض
            'max_categories' => $filters['max_categories'],
            'max_brands' => $filters['max_brands'],
            'max_featured_products' => $filters['max_featured_products'],
            'max_new_arrivals' => $filters['max_new_arrivals'],
            'max_top_rated_products' => $filters['max_top_rated_products'],
            'max_best_selling_products' => $filters['max_best_selling_products'],
            'max_recently_viewed' => $appearance['max_recently_viewed'],

            // عرض الأصناف
            'show_category_stock' => $filters['show_category_stock'],
            'show_sub_categories' => $filters['show_sub_categories'],
            'show_category_description' => $filters['show_category_description'],
        ];
    }

    /**
     * جلب بيانات المتجر للعرض
     */
    public static function getStoreInfo(): array
    {
        $general = self::getGeneralSettings();
        $appearance = self::getAppearanceSettings();

        return [
            'name' => $general['store_name'],
            'name_en' => $general['store_name_en'],
            'description' => $general['store_description'],
            'logo' => self::formatImageUrl($general['store_logo']),
            'favicon' => self::formatImageUrl($general['store_favicon']),
            'email' => $general['store_email'],
            'phone' => $general['store_phone'],
            'address' => $general['store_address'],
            'theme_color' => $appearance['theme_color'],
            'secondary_color' => $appearance['secondary_color'],
        ];
    }

    /**
     * تنسيق رابط الصورة بإضافة الدومين الكامل
     */
    protected static function formatImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // إذا كان الرابط يحتوي على http أو https، نرجعه كما هو
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        // إذا كان الرابط يبدأ بـ /، نضيف الدومين
        if (str_starts_with($imagePath, '/')) {
            return url($imagePath);
        }

        // إذا لم يكن يبدأ بـ /، نضيف / ثم الدومين
        return url('/'.$imagePath);
    }
}
