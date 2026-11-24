<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Online Store Settings Seeder
 * سيدر إعدادات المتجر الإلكتروني
 */
class OnlineStoreSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * إدراج إعدادات المتجر الإلكتروني الأساسية
     */
    public function run(): void
    {
        $settings = [
            // إعدادات عامة - General Settings
            [
                'key' => 'store_name',
                'value' => 'متجر فوترة الرئيسي',
                'type' => 'string',
                'group' => 'general',
                'description' => 'اسم المتجر الإلكتروني'
            ],
            [
                'key' => 'store_name_en',
                'value' => 'Fawtrah Main Store',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Store name in English'
            ],
            [
                'key' => 'store_logo',
                'value' => '/images/store-logo.png',
                'type' => 'string',
                'group' => 'general',
                'description' => 'لوجو المتجر'
            ],
            [
                'key' => 'store_favicon',
                'value' => '/images/favicon.ico',
                'type' => 'string',
                'group' => 'general',
                'description' => 'أيقونة المتجر'
            ],
            [
                'key' => 'store_description',
                'value' => 'متجر إلكتروني شامل يوفر جميع احتياجاتك',
                'type' => 'string',
                'group' => 'general',
                'description' => 'وصف المتجر'
            ],
            [
                'key' => 'store_email',
                'value' => 'info@store.com',
                'type' => 'string',
                'group' => 'general',
                'description' => 'البريد الإلكتروني للمتجر'
            ],
            [
                'key' => 'store_phone',
                'value' => '+966501234567',
                'type' => 'string',
                'group' => 'general',
                'description' => 'رقم هاتف المتجر'
            ],
            [
                'key' => 'store_address',
                'value' => 'الرياض، المملكة العربية السعودية',
                'type' => 'string',
                'group' => 'general',
                'description' => 'عنوان المتجر'
            ],
            [
                'key' => 'store_currency',
                'value' => 'SAR',
                'type' => 'string',
                'group' => 'general',
                'description' => 'عملة المتجر'
            ],
            [
                'key' => 'store_timezone',
                'value' => 'Asia/Riyadh',
                'type' => 'string',
                'group' => 'general',
                'description' => 'المنطقة الزمنية'
            ],

            // إعدادات المظهر - Appearance Settings
            [
                'key' => 'theme_color',
                'value' => '#3B82F6',
                'type' => 'string',
                'group' => 'appearance',
                'description' => 'لون المظهر الأساسي'
            ],
            [
                'key' => 'secondary_color',
                'value' => '#10B981',
                'type' => 'string',
                'group' => 'appearance',
                'description' => 'اللون الثانوي'
            ],
            [
                'key' => 'products_per_page',
                'value' => '12',
                'type' => 'integer',
                'group' => 'appearance',
                'description' => 'عدد المنتجات في الصفحة الواحدة'
            ],
            [
                'key' => 'enable_wishlist',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'appearance',
                'description' => 'تفعيل قائمة المفضلة'
            ],
            [
                'key' => 'enable_compare',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'appearance',
                'description' => 'تفعيل مقارنة المنتجات'
            ],
            [
                'key' => 'enable_reviews',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'appearance',
                'description' => 'تفعيل التقييمات'
            ],
            [
                'key' => 'enable_questions',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'appearance',
                'description' => 'تفعيل الأسئلة والأجوبة على المنتجات'
            ],
            [
                'key' => 'allow_negative_stock',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'appearance',
                'description' => 'السماح بالبيع بالسالب (البيع دون مخزون)'
            ],
            [
                'key' => 'max_recently_viewed',
                'value' => '10',
                'type' => 'integer',
                'group' => 'appearance',
                'description' => 'الحد الأقصى للمنتجات المشاهدة مؤخراً'
            ],

            // إعدادات الدفع - Checkout Settings
            [
                'key' => 'min_order_amount',
                'value' => '50.00',
                'type' => 'decimal',
                'group' => 'checkout',
                'description' => 'الحد الأدنى للطلب'
            ],
            [
                'key' => 'max_order_amount',
                'value' => '10000.00',
                'type' => 'decimal',
                'group' => 'checkout',
                'description' => 'الحد الأقصى للطلب'
            ],
            [
                'key' => 'tax_rate',
                'value' => '15.00',
                'type' => 'decimal',
                'group' => 'checkout',
                'description' => 'نسبة الضريبة'
            ],
            [
                'key' => 'enable_guest_checkout',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'checkout',
                'description' => 'السماح للزوار بالشراء'
            ],
            [
                'key' => 'require_phone_verification',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'checkout',
                'description' => 'مطالبة بتأكيد الهاتف'
            ],
            [
                'key' => 'cart_session_lifetime',
                'value' => '1440',
                'type' => 'integer',
                'group' => 'checkout',
                'description' => 'مدة بقاء السلة بالدقائق'
            ],
            [
                'key' => 'require_email_verification',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'checkout',
                'description' => 'التحقق من البريد الإلكتروني'
            ],
            [
                'key' => 'default_storehouse_id',
                'value' => '',
                'type' => 'integer',
                'group' => 'checkout',
                'description' => 'المخزون الافتراضي للمتجر'
            ],
            [
                'key' => 'default_treasury_id',
                'value' => '',
                'type' => 'integer',
                'group' => 'checkout',
                'description' => 'الخزينة أو البنك الافتراضي للمتجر'
            ],

            // إعدادات طرق الدفع - Payment Methods Settings
            [
                'key' => 'default_payment_method_id',
                'value' => '',
                'type' => 'integer',
                'group' => 'checkout',
                'description' => 'طريقة الدفع الافتراضية في المتجر'
            ],
            [
                'key' => 'enabled_payment_methods',
                'value' => '[]',
                'type' => 'json',
                'group' => 'checkout',
                'description' => 'طرق الدفع المفعلة في المتجر (مصفوفة من المعرفات)'
            ],

            // إعدادات الشحن - Shipping Settings
            [
                'key' => 'free_shipping_threshold',
                'value' => '200.00',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'الحد الأدنى للشحن المجاني'
            ],
            [
                'key' => 'default_shipping_cost',
                'value' => '25.00',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'تكلفة الشحن الافتراضية'
            ],
            [
                'key' => 'cod_fee',
                'value' => '10.00',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'رسوم الدفع عند الاستلام'
            ],
            [
                'key' => 'express_shipping_fee',
                'value' => '35.00',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'رسوم الشحن السريع'
            ],
            [
                'key' => 'packaging_fee',
                'value' => '5.00',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'رسوم التغليف'
            ],
            [
                'key' => 'shipping_revenue_percentage',
                'value' => '0.00',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'نسبة إيرادات الشحن (عمولة الموقع من شركات الشحن %)'
            ],


            // إعدادات السيو - SEO Settings
            [
                'key' => 'meta_title',
                'value' => 'متجر فوترة الرئيسي - تسوق آمن وسريع',
                'type' => 'string',
                'group' => 'seo',
                'description' => 'عنوان الصفحة الرئيسية'
            ],
            [
                'key' => 'meta_description',
                'value' => 'متجر إلكتروني موثوق يوفر أفضل المنتجات بأسعار منافسة مع خدمة توصيل سريعة',
                'type' => 'string',
                'group' => 'seo',
                'description' => 'وصف الصفحة الرئيسية'
            ],
            [
                'key' => 'meta_keywords',
                'value' => 'تسوق، متجر إلكتروني، شراء أونلاين، توصيل سريع',
                'type' => 'string',
                'group' => 'seo',
                'description' => 'الكلمات المفتاحية'
            ],
            [
                'key' => 'google_analytics_id',
                'value' => '',
                'type' => 'string',
                'group' => 'seo',
                'description' => 'معرف Google Analytics'
            ],
            [
                'key' => 'facebook_pixel_id',
                'value' => '',
                'type' => 'string',
                'group' => 'seo',
                'description' => 'معرف Facebook Pixel'
            ],

            // إعدادات الإشعارات - Notifications
            [
                'key' => 'enable_email_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'تفعيل إشعارات البريد الإلكتروني'
            ],
            [
                'key' => 'enable_sms_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'تفعيل إشعارات الرسائل النصية'
            ],
            [
                'key' => 'enable_push_notifications',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'تفعيل الإشعارات المباشرة'
            ],

            // إعدادات التواصل الاجتماعي - Social Media Settings
            [
                'key' => 'social_facebook',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط صفحة فيسبوك'
            ],
            [
                'key' => 'social_twitter',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط حساب تويتر (X)'
            ],
            [
                'key' => 'social_instagram',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط حساب انستقرام'
            ],
            [
                'key' => 'social_whatsapp',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رقم واتساب (مع رمز الدولة)'
            ],
            [
                'key' => 'social_youtube',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط قناة يوتيوب'
            ],
            [
                'key' => 'social_tiktok',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط حساب تيك توك'
            ],
            [
                'key' => 'social_snapchat',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط حساب سناب شات'
            ],
            [
                'key' => 'social_linkedin',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط صفحة لينكد إن'
            ],
            [
                'key' => 'social_telegram',
                'value' => '',
                'type' => 'string',
                'group' => 'social',
                'description' => 'رابط قناة/مجموعة تيليجرام'
            ],

            // إعدادات العملة - Currency Settings
            [
                'key' => 'currency',
                'value' => 'SAR',
                'type' => 'string',
                'group' => 'general',
                'description' => 'رمز العملة'
            ],
            [
                'key' => 'currency_symbol',
                'value' => 'ر.س',
                'type' => 'string',
                'group' => 'general',
                'description' => 'رمز العملة للعرض'
            ],
            [
                'key' => 'currency_position',
                'value' => 'after',
                'type' => 'string',
                'group' => 'general',
                'description' => 'موضع رمز العملة (before/after)'
            ],
            [
                'key' => 'language',
                'value' => 'ar',
                'type' => 'string',
                'group' => 'general',
                'description' => 'اللغة الافتراضية'
            ],
            [
                'key' => 'direction',
                'value' => 'rtl',
                'type' => 'string',
                'group' => 'general',
                'description' => 'اتجاه النص (rtl/ltr)'
            ],
            [
                'key' => 'tax_included',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'checkout',
                'description' => 'الأسعار شاملة الضريبة'
            ],
            [
                'key' => 'default_shipping_method',
                'value' => '',
                'type' => 'string',
                'group' => 'shipping',
                'description' => 'طريقة الشحن الافتراضية'
            ],
            [
                'key' => 'show_out_of_stock',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'appearance',
                'description' => 'عرض المنتجات غير المتوفرة'
            ],
            [
                'key' => 'enable_coupons',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'checkout',
                'description' => 'تفعيل كوبونات الخصم'
            ],

            // إعدادات متقدمة - Advanced Settings
            [
                'key' => 'enable_multi_language',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'تفعيل تعدد اللغات'
            ],
            [
                'key' => 'enable_cache',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'تفعيل التخزين المؤقت'
            ],
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'وضع الصيانة'
            ],
            [
                'key' => 'auto_approve_reviews',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'advanced',
                'description' => 'الموافقة التلقائية على التقييمات'
            ],

            // إعدادات الفلاتر والعرض - Filters & Display Settings
            [
                'key' => 'filter_max_categories',
                'value' => '10',
                'type' => 'integer',
                'group' => 'filters',
                'description' => 'الحد الأقصى للأصناف المعروضة في الفلتر'
            ],
            [
                'key' => 'filter_max_brands',
                'value' => '10',
                'type' => 'integer',
                'group' => 'filters',
                'description' => 'الحد الأقصى للبراندات المعروضة في الفلتر'
            ],
            [
                'key' => 'max_featured_products',
                'value' => '8',
                'type' => 'integer',
                'group' => 'filters',
                'description' => 'الحد الأقصى للمنتجات المميزة'
            ],
            [
                'key' => 'max_new_arrivals',
                'value' => '8',
                'type' => 'integer',
                'group' => 'filters',
                'description' => 'الحد الأقصى للمنتجات الجديدة'
            ],
            [
                'key' => 'max_top_rated_products',
                'value' => '5',
                'type' => 'integer',
                'group' => 'filters',
                'description' => 'الحد الأقصى للمنتجات الأعلى تقييماً'
            ],
            [
                'key' => 'max_best_selling_products',
                'value' => '8',
                'type' => 'integer',
                'group' => 'filters',
                'description' => 'الحد الأقصى للمنتجات الأكثر مبيعاً'
            ],
            [
                'key' => 'show_category_stock',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'filters',
                'description' => 'عرض المخزون الكلي للصنف'
            ],
            [
                'key' => 'show_sub_categories',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'filters',
                'description' => 'عرض الأصناف الفرعية'
            ],
            [
                'key' => 'show_category_description',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'filters',
                'description' => 'عرض وصف الصنف'
            ],
            [
                'key' => 'price_ranges',
                'value' => '[{"key":"all","min":null,"max":null},{"key":"range_1","min":0,"max":100},{"key":"range_2","min":100,"max":500},{"key":"range_3","min":500,"max":1000},{"key":"range_4","min":1000,"max":null}]',
                'type' => 'json',
                'group' => 'filters',
                'description' => 'نطاقات الأسعار للفلترة (JSON)'
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('online_store_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        $this->command->info('تم إدراج إعدادات المتجر الإلكتروني بنجاح');
    }
}