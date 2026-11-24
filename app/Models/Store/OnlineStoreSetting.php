<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل إعدادات المتجر الإلكتروني
 * Online Store Settings Model
 *
 * يدير جميع إعدادات المتجر الإلكتروني المختلفة
 * يحتوي على إعدادات عامة، مظهر، دفع، شحن، SEO وغيرها
 */
class OnlineStoreSetting extends Model
{
    use HasFactory;

    protected $table = 'online_store_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * الحصول على جميع الإعدادات (بدون كاش)
     */
    public static function getAllSettings()
    {
        return self::all()->keyBy('key');
    }

    /**
     * فلترة حسب المجموعة
     */
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    /**
     * فلترة حسب النوع
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * الحصول على قيمة إعداد محدد
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return self::castValue($setting->value, $setting->type);
    }

    /**
     * تعيين قيمة إعداد محدد
     */
    public static function set($key, $value, $type = 'string', $group = 'general', $description = null)
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => self::prepareValue($value, $type),
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]
        );
    }

    /**
     * الحصول على جميع إعدادات مجموعة محددة
     */
    public static function getGroup($group)
    {
        $settings = self::byGroup($group)->get();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = self::castValue($setting->value, $setting->type);
        }

        return $result;
    }

    /**
     * الحصول على جميع الإعدادات مرتبة حسب المجموعة
     */
    public static function getAllGrouped()
    {
        $settings = self::all();
        $grouped = [];

        foreach ($settings as $setting) {
            if (! isset($grouped[$setting->group])) {
                $grouped[$setting->group] = [];
            }

            $grouped[$setting->group][$setting->key] = [
                'value' => self::castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'description' => $setting->description,
            ];
        }

        return $grouped;
    }

    /**
     * تحديث عدة إعدادات مرة واحدة
     */
    public static function updateMultiple(array $settings, $group = null)
    {
        foreach ($settings as $key => $data) {
            if (is_array($data)) {
                $value = $data['value'] ?? null;
                $type = $data['type'] ?? 'string';
                $settingGroup = $data['group'] ?? $group ?? 'general';
                $description = $data['description'] ?? null;
            } else {
                $value = $data;
                $type = 'string';
                $settingGroup = $group ?? 'general';
                $description = null;
            }

            self::set($key, $value, $type, $settingGroup, $description);
        }
    }

    /**
     * حذف إعداد محدد
     */
    public static function remove($key)
    {
        return self::where('key', $key)->delete();
    }

    /**
     * التحقق من وجود إعداد
     */
    public static function has($key)
    {
        return self::where('key', $key)->exists();
    }

    /**
     * تحضير القيمة حسب النوع للحفظ
     * Prepare value for storage based on type
     */
    protected static function prepareValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                // معالجة جميع تمثيلات القيم البوليانية
                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }
                if ($value === '1' || $value === 'true' || $value === 1 || $value === 'on') {
                    return '1';
                }

                return '0';

            case 'integer':
                // التحقق من أن القيمة رقمية قبل التحويل
                if (! is_numeric($value) || $value === 'false' || $value === 'true') {
                    return '0';
                }

                return (string) (int) $value;

            case 'decimal':
                // التحقق من أن القيمة رقمية قبل التحويل
                if (! is_numeric($value) || $value === 'false' || $value === 'true') {
                    return '0';
                }

                return (string) (float) $value;

            case 'json':
            case 'array':
                // إذا كانت القيمة JSON string بالفعل، أبقها كما هي
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $value;
                    }
                }

                return json_encode($value);

            case 'email':
                // تصفية البريد الإلكتروني
                $email = filter_var($value, FILTER_SANITIZE_EMAIL);

                return $email ?: '';

            case 'color':
                // التحقق من صيغة اللون
                if (preg_match('/^#[a-fA-F0-9]{6}$/', $value) || preg_match('/^#[a-fA-F0-9]{3}$/', $value)) {
                    return $value;
                }

                return '';

            case 'text':
            case 'string':
            default:
                // 'false' أو 'true' كنص عادي يجب تحويلها لقيمة فارغة إذا لم تكن مقصودة
                if ($value === 'false' || $value === false) {
                    return '';
                }

                return (string) $value;
        }
    }

    /**
     * تحويل القيمة المحفوظة إلى النوع المطلوب
     * Cast stored value to the required type
     */
    public static function castValue($value, $type)
    {
        if (is_null($value)) {
            return null;
        }

        switch ($type) {
            case 'boolean':
                // تصحيح: القيمة true فقط إذا كانت '1' أو 'true' أو true أو 1
                // 'false' أو '0' أو 0 أو false أو '' تُعتبر false
                return $value === '1' || $value === 'true' || $value === true || $value === 1;

            case 'integer':
                // معالجة القيم غير الرقمية بشكل آمن
                if (! is_numeric($value) || $value === 'false' || $value === 'true') {
                    return 0;
                }

                return (int) $value;

            case 'decimal':
                // معالجة القيم غير الرقمية بشكل آمن
                if (! is_numeric($value) || $value === 'false' || $value === 'true') {
                    return 0.0;
                }

                return (float) $value;

            case 'json':
            case 'array':
                // إذا كانت مصفوفة بالفعل، أعدها مباشرة
                if (is_array($value)) {
                    return $value;
                }
                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : [];

            case 'email':
            case 'color':
            case 'text':
            case 'string':
            default:
                // 'false' المحفوظة بالخطأ في حقول النص تُعاد كسلسلة فارغة
                if ($value === 'false' && $type !== 'text') {
                    return '';
                }

                return $value;
        }
    }

    /**
     * الحصول على الإعدادات الافتراضية للمتجر
     */
    public static function getDefaults()
    {
        return [
            // إعدادات عامة
            'store_name' => ['value' => 'متجري الإلكتروني', 'type' => 'string', 'group' => 'general'],
            'store_name_en' => ['value' => 'My Online Store', 'type' => 'string', 'group' => 'general'],
            'store_description' => ['value' => 'وصف المتجر', 'type' => 'text', 'group' => 'general'],
            'store_email' => ['value' => 'info@store.com', 'type' => 'email', 'group' => 'general'],
            'store_phone' => ['value' => '', 'type' => 'string', 'group' => 'general'],
            'store_address' => ['value' => '', 'type' => 'text', 'group' => 'general'],
            'store_logo' => ['value' => '', 'type' => 'string', 'group' => 'general'],
            'store_favicon' => ['value' => '', 'type' => 'string', 'group' => 'general'],
            'store_currency' => ['value' => 'SAR', 'type' => 'string', 'group' => 'general'],
            'store_timezone' => ['value' => 'Asia/Riyadh', 'type' => 'string', 'group' => 'general'],
            'google_maps_api_key' => ['value' => '', 'type' => 'string', 'group' => 'general'],
            'language' => ['value' => 'ar', 'type' => 'string', 'group' => 'general'],
            'direction' => ['value' => 'rtl', 'type' => 'string', 'group' => 'general'],

            // إعدادات العملة
            'currency' => ['value' => 'SAR', 'type' => 'string', 'group' => 'currency'],
            'currency_symbol' => ['value' => 'ر.س', 'type' => 'string', 'group' => 'currency'],
            'currency_position' => ['value' => 'after', 'type' => 'string', 'group' => 'currency'], // before or after

            // إعدادات المظهر
            'theme_color' => ['value' => '#007bff', 'type' => 'color', 'group' => 'appearance'],
            'secondary_color' => ['value' => '#10B981', 'type' => 'color', 'group' => 'appearance'],
            'products_per_page' => ['value' => 12, 'type' => 'integer', 'group' => 'appearance'],
            'show_out_of_stock' => ['value' => true, 'type' => 'boolean', 'group' => 'appearance'],
            'allow_negative_stock' => ['value' => false, 'type' => 'boolean', 'group' => 'appearance'],
            'max_recently_viewed' => ['value' => 10, 'type' => 'integer', 'group' => 'appearance'],
            'enable_wishlist' => ['value' => true, 'type' => 'boolean', 'group' => 'appearance'],
            'enable_compare' => ['value' => true, 'type' => 'boolean', 'group' => 'appearance'],
            'enable_reviews' => ['value' => true, 'type' => 'boolean', 'group' => 'appearance'],

            // إعدادات الفلاتر
            'filter_max_categories' => ['value' => 10, 'type' => 'integer', 'group' => 'filters'],
            'filter_max_brands' => ['value' => 10, 'type' => 'integer', 'group' => 'filters'],
            'max_featured_products' => ['value' => 8, 'type' => 'integer', 'group' => 'filters'],
            'max_new_arrivals' => ['value' => 8, 'type' => 'integer', 'group' => 'filters'],
            'max_top_rated_products' => ['value' => 5, 'type' => 'integer', 'group' => 'filters'],
            'max_best_selling_products' => ['value' => 8, 'type' => 'integer', 'group' => 'filters'],
            'show_category_stock' => ['value' => true, 'type' => 'boolean', 'group' => 'filters'],
            'show_sub_categories' => ['value' => true, 'type' => 'boolean', 'group' => 'filters'],
            'show_category_description' => ['value' => true, 'type' => 'boolean', 'group' => 'filters'],
            'price_ranges' => [
                'value' => json_encode([
                    ['key' => 'all', 'min' => null, 'max' => null],
                    ['key' => 'under_50', 'min' => null, 'max' => 50],
                    ['key' => '50_200', 'min' => 50, 'max' => 200],
                    ['key' => '200_500', 'min' => 200, 'max' => 500],
                    ['key' => '500_1000', 'min' => 500, 'max' => 1000],
                    ['key' => 'over_1000', 'min' => 1000, 'max' => null],
                ]),
                'type' => 'json',
                'group' => 'filters',
            ],

            // إعدادات الطلبات
            'min_order_amount' => ['value' => 0, 'type' => 'decimal', 'group' => 'checkout'],
            'max_order_amount' => ['value' => 10000, 'type' => 'decimal', 'group' => 'checkout'],
            'enable_guest_checkout' => ['value' => true, 'type' => 'boolean', 'group' => 'checkout'],
            'tax_rate' => ['value' => 15, 'type' => 'decimal', 'group' => 'checkout'],
            'tax_included' => ['value' => false, 'type' => 'boolean', 'group' => 'checkout'],
            'require_phone_verification' => ['value' => true, 'type' => 'boolean', 'group' => 'checkout'],
            'require_email_verification' => ['value' => false, 'type' => 'boolean', 'group' => 'checkout'],
            'cart_session_lifetime' => ['value' => 1440, 'type' => 'integer', 'group' => 'checkout'],
            'enable_coupons' => ['value' => true, 'type' => 'boolean', 'group' => 'checkout'],
            'default_storehouse_id' => ['value' => null, 'type' => 'integer', 'group' => 'checkout'],
            'default_treasury_id' => ['value' => null, 'type' => 'integer', 'group' => 'checkout'],

            // الحسابات المحاسبية للمتجر — تُختار من شجرة الحسابات بدل الاعتماد
            // حصراً على ثوابت SystemAccountIds المثبَّتة في الكود. القيمة null
            // تعني: استخدم الحساب النظامي الافتراضي (نفس السلوك القديم).
            'cash_client_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'sales_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'sales_returns_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'allowed_discount_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'cost_of_sales_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'inventory_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'vat_payable_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'vat_paid_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'shipping_revenue_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'shipping_revenue_returns_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'shipping_vat_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],
            'free_shipping_expense_account_id' => ['value' => null, 'type' => 'integer', 'group' => 'accounting'],

            // إعدادات الشحن
            'default_shipping_method' => ['value' => '', 'type' => 'string', 'group' => 'shipping'],
            'cod_fee' => ['value' => 10, 'type' => 'decimal', 'group' => 'shipping'],
            'packaging_fee' => ['value' => 5, 'type' => 'decimal', 'group' => 'shipping'],
            'shipping_revenue_percentage' => ['value' => 0, 'type' => 'decimal', 'group' => 'shipping'],

            // روابط التواصل الاجتماعي
            'social_facebook' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_twitter' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_instagram' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_whatsapp' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_youtube' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_tiktok' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_snapchat' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_linkedin' => ['value' => '', 'type' => 'string', 'group' => 'social'],
            'social_telegram' => ['value' => '', 'type' => 'string', 'group' => 'social'],

            // إعدادات SEO
            'meta_title' => ['value' => 'متجري الإلكتروني', 'type' => 'string', 'group' => 'seo'],
            'meta_description' => ['value' => 'أفضل منتجات بأسعار ممتازة', 'type' => 'text', 'group' => 'seo'],
            'meta_keywords' => ['value' => 'تسوق، منتجات، عروض', 'type' => 'text', 'group' => 'seo'],
            'google_analytics_id' => ['value' => '', 'type' => 'string', 'group' => 'seo'],
            'facebook_pixel_id' => ['value' => '', 'type' => 'string', 'group' => 'seo'],

            // إعدادات الميزات
            'enable_reviews' => ['value' => true, 'type' => 'boolean', 'group' => 'features'],
            'enable_questions' => ['value' => true, 'type' => 'boolean', 'group' => 'features'],
            'enable_wishlist' => ['value' => true, 'type' => 'boolean', 'group' => 'features'],
            'enable_compare' => ['value' => true, 'type' => 'boolean', 'group' => 'features'],
            'enable_coupons' => ['value' => true, 'type' => 'boolean', 'group' => 'features'],
            'enable_multi_language' => ['value' => true, 'type' => 'boolean', 'group' => 'features'],
            'enable_cache' => ['value' => true, 'type' => 'boolean', 'group' => 'features'],

            // إعدادات الإشعارات
            'enable_email_notifications' => ['value' => true, 'type' => 'boolean', 'group' => 'notifications'],
            'enable_sms_notifications' => ['value' => true, 'type' => 'boolean', 'group' => 'notifications'],
            'enable_push_notifications' => ['value' => true, 'type' => 'boolean', 'group' => 'notifications'],

            // إعدادات متقدمة
            'auto_approve_reviews' => ['value' => false, 'type' => 'boolean', 'group' => 'advanced'],

            // حالة المتجر
            'maintenance_mode' => ['value' => false, 'type' => 'boolean', 'group' => 'general'],
        ];
    }

    /**
     * تهيئة الإعدادات الافتراضية
     */
    public static function seedDefaults()
    {
        $defaults = self::getDefaults();

        foreach ($defaults as $key => $data) {
            if (! self::has($key)) {
                self::set($key, $data['value'], $data['type'], $data['group']);
            }
        }
    }

    /**
     * إصلاح البيانات التالفة في قاعدة البيانات
     * Fix corrupted data in the database
     *
     * يعالج الحالات التالية:
     * - قيم 'false' أو 'true' في حقول decimal/integer
     * - قيم 'false' في حقول string (يحولها لسلسلة فارغة)
     * - قيم boolean مخزنة كـ 'true'/'false' بدلاً من '1'/'0'
     */
    public static function fixCorruptedData(): array
    {
        $fixed = [];
        $settings = self::all();

        foreach ($settings as $setting) {
            $originalValue = $setting->value;
            $needsFix = false;
            $newValue = $originalValue;

            switch ($setting->type) {
                case 'boolean':
                    // تحويل 'true'/'false' إلى '1'/'0'
                    if ($originalValue === 'true') {
                        $newValue = '1';
                        $needsFix = true;
                    } elseif ($originalValue === 'false' || $originalValue === '' || is_null($originalValue)) {
                        $newValue = '0';
                        $needsFix = true;
                    }
                    break;

                case 'integer':
                case 'decimal':
                    // إصلاح القيم غير الرقمية
                    if (! is_numeric($originalValue) || $originalValue === 'false' || $originalValue === 'true') {
                        $newValue = '0';
                        $needsFix = true;
                    }
                    break;

                case 'string':
                case 'email':
                case 'color':
                    // تحويل 'false' إلى سلسلة فارغة
                    if ($originalValue === 'false') {
                        $newValue = '';
                        $needsFix = true;
                    }
                    break;

                case 'json':
                case 'array':
                    // التحقق من صحة JSON
                    if (! empty($originalValue)) {
                        $decoded = json_decode($originalValue, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $newValue = '[]';
                            $needsFix = true;
                        }
                    }
                    break;
            }

            if ($needsFix) {
                $setting->value = $newValue;
                $setting->save();
                $fixed[] = [
                    'key' => $setting->key,
                    'type' => $setting->type,
                    'old_value' => $originalValue,
                    'new_value' => $newValue,
                ];
            }
        }

        // مسح الكاش بعد الإصلاح
        self::clearCache();

        return $fixed;
    }

    /**
     * التحقق من سلامة البيانات والإبلاغ عن المشاكل
     * Validate data integrity and report issues
     */
    public static function validateDataIntegrity(): array
    {
        $issues = [];
        $settings = self::all();

        foreach ($settings as $setting) {
            $value = $setting->value;
            $type = $setting->type;
            $key = $setting->key;

            switch ($type) {
                case 'boolean':
                    if (! in_array($value, ['0', '1', 'true', 'false', '', null], true)) {
                        $issues[] = [
                            'key' => $key,
                            'type' => $type,
                            'value' => $value,
                            'issue' => 'Invalid boolean value',
                        ];
                    }
                    break;

                case 'integer':
                case 'decimal':
                    if (! is_numeric($value) && ! empty($value)) {
                        $issues[] = [
                            'key' => $key,
                            'type' => $type,
                            'value' => $value,
                            'issue' => "Non-numeric value in {$type} field",
                        ];
                    }
                    break;

                case 'email':
                    if (! empty($value) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $issues[] = [
                            'key' => $key,
                            'type' => $type,
                            'value' => $value,
                            'issue' => 'Invalid email format',
                        ];
                    }
                    break;

                case 'json':
                case 'array':
                    if (! empty($value)) {
                        json_decode($value, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $issues[] = [
                                'key' => $key,
                                'type' => $type,
                                'value' => $value,
                                'issue' => 'Invalid JSON format',
                            ];
                        }
                    }
                    break;

                case 'color':
                    if (! empty($value) && ! preg_match('/^#[a-fA-F0-9]{3,6}$/', $value)) {
                        $issues[] = [
                            'key' => $key,
                            'type' => $type,
                            'value' => $value,
                            'issue' => 'Invalid color format (expected #RGB or #RRGGBB)',
                        ];
                    }
                    break;
            }
        }

        return $issues;
    }
}
