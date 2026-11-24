<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات وحدة المتجر الإلكتروني — المصدر الوحيد لأسمائها.
 *
 * كانت الوحدة بكامل متحكّماتها (25 متحكّماً) بلا أي `middleware('permission:...')`
 * — أي مستخدم مسجّل دخول يصل إلى كل شاشاتها بلا استثناء.
 *
 * **ما لا يُكرَّر هنا**: خمسة متحكّمات في الوحدة تدير فعلياً بيانات/منطقاً
 * مملوكاً لوحدات أخرى، فتُحرَس بكتالوجات تلك الوحدات لا بكتالوجٍ مواز هنا:
 *
 *   - `StorePermitsController`        ← `stock.permits.*` / `stock.permits_approval.*`
 *   - `StorePurchaseInvoiceController`← `purchases.invoices.*`
 *   - `StoreStorehouseController`     ← `stock.warehouses.*`
 *   - `StoreSupplierController`       ← `purchases.suppliers.*`
 *   - `StoreTreasuryController`       ← `account.treasury.*` / `account.treasury_transfers.*`
 *
 * **العمليات المالية الحسّاسة معزولة** بنفس أصل الفصل المستعمل في
 * [StockPermissions]: إلغاء الطلب واستردادُه يعكسان قيوداً محاسبية
 * ويُنشئان فاتورة مرتجع، فهما في `orders_returns` منفصلين عن تصفّح الطلبات
 * وتحديث حالتها. وتعديل رصيد المنتج يدوياً بلا مستندٍ يبرّره في
 * `products_stock` منفصلة، كما فعل `StockPermissions::products_stock`
 * بالضبط.
 */
class OnlineStorePermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة. */
    public const PREFIX = 'online_store.';

    public static function module(): array
    {
        return [
            'key' => 'online_store',
            'label' => 'المتجر الإلكتروني',
            'description' => 'كتالوج المتجر والطلبات والمحتوى والكوبونات والبريد وتقارير المتجر.',
            'icon' => 'shopping-bag',
            'order' => 42,
        ];
    }

    /**
     * @return array<string, array{label: string, description: string, icon?: string, controller?: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            // ═══════════════ لوحة التحكم ═══════════════
            'dashboard' => [
                'label' => 'لوحة تحكم المتجر',
                'description' => 'نظرة عامة على مؤشرات المتجر الإلكتروني.',
                'icon' => 'home',
                'controller' => 'OnlineStoreDashboardController',
                'permissions' => [
                    'online_store.dashboard.view' => 'عرض لوحة تحكم المتجر',
                ],
            ],

            // ═══════════════ الكتالوج ═══════════════
            'brands' => [
                'label' => 'العلامات التجارية',
                'description' => 'العلامات التجارية للمنتجات المعروضة في المتجر.',
                'icon' => 'award',
                'controller' => 'BrandController',
                'permissions' => [
                    'online_store.brands.view' => 'عرض العلامات التجارية',
                    'online_store.brands.create' => 'إنشاء علامة تجارية',
                    'online_store.brands.edit' => 'تعديل علامة تجارية',
                    'online_store.brands.delete' => 'حذف علامة تجارية',
                    'online_store.brands.toggle_status' => 'تفعيل العلامة وتمييزها',
                ],
            ],

            'categories' => [
                'label' => 'أصناف المتجر',
                'description' => 'أصناف المنتجات الرئيسية والفرعية في واجهة المتجر.',
                'icon' => 'folder',
                'controller' => 'StoreCategoryController',
                'permissions' => [
                    'online_store.categories.view' => 'عرض الأصناف',
                    'online_store.categories.create' => 'إنشاء صنف',
                    'online_store.categories.edit' => 'تعديل صنف',
                    'online_store.categories.delete' => 'حذف صنف',
                ],
            ],

            // ═══════════════ المنتجات ═══════════════
            'products' => [
                'label' => 'منتجات المتجر',
                'description' => 'منتجات المتجر الإلكتروني: بياناتها، متغيراتها، إتاحتها أونلاين، وحركتها.',
                'icon' => 'box',
                'controller' => 'StoreProductController',
                'permissions' => [
                    'online_store.products.view' => 'عرض منتجات المتجر',
                    'online_store.products.show' => 'عرض تفاصيل منتج وحركته',
                    'online_store.products.create' => 'إضافة منتج',
                    'online_store.products.edit' => 'تعديل منتج',
                    'online_store.products.delete' => 'حذف منتج',
                    'online_store.products.toggle_status' => 'تفعيل المنتج وإتاحته أونلاين وتمييزه',
                    'online_store.products.export' => 'تصدير المنتجات',
                    'online_store.products.print_barcode' => 'طباعة باركود المنتج',
                ],
            ],

            'products_stock' => [
                'label' => 'تسوية رصيد منتج المتجر (عملية خطرة)',
                'description' => 'تعديل رصيد منتجٍ يدوياً بلا مستند مخزني يبرّره.',
                'icon' => 'alert-triangle',
                'controller' => 'StoreProductController',
                'permissions' => [
                    'online_store.products.manual_stock_adjust' => 'تعديل رصيد منتج يدوياً',
                ],
            ],

            // ═══════════════ الطلبات ═══════════════
            'orders' => [
                'label' => 'طلبات المتجر',
                'description' => 'تصفّح الطلبات ومتابعة حالتها وشحنها وطباعتها.',
                'icon' => 'shopping-cart',
                'controller' => 'OnlineOrderController',
                'permissions' => [
                    'online_store.orders.view' => 'عرض الطلبات',
                    'online_store.orders.show' => 'عرض تفاصيل طلب',
                    'online_store.orders.update_status' => 'تحديث حالة الطلب',
                    'online_store.orders.update_shipping' => 'تحديث معلومات الشحن',
                    'online_store.orders.add_note' => 'إضافة ملاحظة إدارية',
                    'online_store.orders.print' => 'طباعة الطلب وفاتورته وبوليصة الشحن',
                    'online_store.orders.export' => 'تصدير الطلبات',
                    'online_store.orders.statistics' => 'عرض إحصائيات الطلبات',
                ],
            ],

            // الإلغاء والاسترداد يعكسان قيوداً محاسبية وينشئان فاتورة مرتجع —
            // يُفصلان عن تصفّح الطلب وتحديث حالته العادية
            'orders_returns' => [
                'label' => 'إلغاء واسترداد الطلبات (عملية خطرة)',
                'description' => 'إلغاء الطلب أو استرداده — يُنشئ فاتورة مرتجع ويعكس القيود المحاسبية.',
                'icon' => 'rotate-ccw',
                'controller' => 'OnlineOrderController',
                'permissions' => [
                    'online_store.orders.cancel' => 'إلغاء الطلب',
                    'online_store.orders.refund' => 'استرداد الطلب',
                ],
            ],

            // ═══════════════ التقييمات والأسئلة ═══════════════
            'reviews' => [
                'label' => 'تقييمات المنتجات',
                'description' => 'مراجعة تقييمات العملاء واعتمادها والرد عليها.',
                'icon' => 'star',
                'controller' => 'ProductReviewController',
                'permissions' => [
                    'online_store.reviews.view' => 'عرض التقييمات',
                    'online_store.reviews.moderate' => 'اعتماد التقييمات ورفضها',
                    'online_store.reviews.reply' => 'الرد على تقييم',
                    'online_store.reviews.toggle_featured' => 'تمييز تقييم',
                    'online_store.reviews.delete' => 'حذف تقييم',
                ],
            ],

            'questions' => [
                'label' => 'أسئلة المنتجات',
                'description' => 'أسئلة العملاء عن المنتجات والإجابة عليها.',
                'icon' => 'help-circle',
                'controller' => 'ProductQuestionController',
                'permissions' => [
                    'online_store.questions.view' => 'عرض الأسئلة',
                    'online_store.questions.create' => 'إضافة سؤال نيابةً عن عميل أو زائر',
                    'online_store.questions.edit' => 'تعديل سؤال',
                    'online_store.questions.answer' => 'الإجابة على سؤال',
                    'online_store.questions.moderate' => 'رفض سؤال أو إعادة فتحه',
                    'online_store.questions.toggle_public' => 'نشر السؤال للعامة أو إخفاؤه',
                    'online_store.questions.delete' => 'حذف سؤال',
                ],
            ],

            // ═══════════════ المحتوى ═══════════════
            'banners' => [
                'label' => 'بانرات المتجر',
                'description' => 'البانرات والإعلانات المعروضة في واجهة المتجر.',
                'icon' => 'image',
                'controller' => 'StoreBannerController',
                'permissions' => [
                    'online_store.banners.view' => 'عرض البانرات',
                    'online_store.banners.create' => 'إنشاء بانر',
                    'online_store.banners.edit' => 'تعديل بانر',
                    'online_store.banners.delete' => 'حذف بانر',
                    'online_store.banners.toggle_status' => 'تفعيل البانر وتعطيله',
                ],
            ],

            'pages' => [
                'label' => 'صفحات المتجر',
                'description' => 'الصفحات الثابتة والسياسات والأسئلة الشائعة.',
                'icon' => 'file-text',
                'controller' => 'StorePageController',
                'permissions' => [
                    'online_store.pages.view' => 'عرض صفحات المتجر',
                    'online_store.pages.create' => 'إنشاء صفحة',
                    'online_store.pages.edit' => 'تعديل صفحة',
                    'online_store.pages.delete' => 'حذف صفحة',
                    'online_store.pages.toggle_status' => 'تفعيل الصفحة وتعطيلها',
                ],
            ],

            // ═══════════════ التسويق ═══════════════
            'coupons' => [
                'label' => 'كوبونات الخصم',
                'description' => 'كوبونات خصم المتجر وشروط استخدامها.',
                'icon' => 'percent',
                'controller' => 'StoreCouponController',
                'permissions' => [
                    'online_store.coupons.view' => 'عرض الكوبونات',
                    'online_store.coupons.create' => 'إنشاء كوبون',
                    'online_store.coupons.edit' => 'تعديل كوبون',
                    'online_store.coupons.delete' => 'حذف كوبون',
                    'online_store.coupons.toggle_status' => 'تفعيل الكوبون وتعطيله',
                ],
            ],

            // ═══════════════ الشحن والدفع ═══════════════
            'shipping_methods' => [
                'label' => 'طرق الشحن',
                'description' => 'طرق شحن المتجر وتكلفتها ومدة توصيلها.',
                'icon' => 'truck',
                'controller' => 'ShippingMethodController',
                'permissions' => [
                    'online_store.shipping_methods.view' => 'عرض طرق الشحن',
                    'online_store.shipping_methods.create' => 'إنشاء طريقة شحن',
                    'online_store.shipping_methods.edit' => 'تعديل طريقة شحن',
                    'online_store.shipping_methods.delete' => 'حذف طريقة شحن',
                    'online_store.shipping_methods.toggle_status' => 'تفعيل طريقة الشحن وتعطيلها',
                ],
            ],

            // إدارة طرق الدفع (إنشاء/تعديل/حذف) موحّدة في شاشة الإعدادات
            // (Sitting\PaymentMethodsController)؛ هنا عرضٌ مرتبط بسياق المتجر فقط
            'payment_methods' => [
                'label' => 'طرق الدفع (عرض)',
                'description' => 'عرض طرق الدفع المتاحة للمتجر — الإدارة من شاشة الإعدادات.',
                'icon' => 'credit-card',
                'controller' => 'PaymentMethodController',
                'permissions' => [
                    'online_store.payment_methods.view' => 'عرض طرق الدفع',
                ],
            ],

            // ═══════════════ العملاء ═══════════════
            'clients' => [
                'label' => 'عملاء المتجر',
                'description' => 'عملاء المتجر الإلكتروني وعناوينهم.',
                'icon' => 'users',
                'controller' => 'StoreClientController',
                'permissions' => [
                    'online_store.clients.view' => 'عرض عملاء المتجر',
                    'online_store.clients.create' => 'إضافة عميل',
                    'online_store.clients.edit' => 'تعديل عميل وعناوينه',
                    'online_store.clients.delete' => 'حذف عميل',
                    'online_store.clients.export' => 'تصدير عملاء المتجر',
                ],
            ],

            // ═══════════════ البريد الإلكتروني ═══════════════
            'email_templates' => [
                'label' => 'قوالب البريد الإلكتروني',
                'description' => 'قوالب رسائل المتجر التلقائية ومعاينتها واختبار إرسالها.',
                'icon' => 'mail',
                'controller' => 'EmailTemplateController',
                'permissions' => [
                    'online_store.email_templates.view' => 'عرض قوالب البريد',
                    'online_store.email_templates.create' => 'إنشاء قالب بريد',
                    'online_store.email_templates.edit' => 'تعديل قالب بريد',
                    'online_store.email_templates.delete' => 'حذف قالب بريد',
                    'online_store.email_templates.send_test' => 'إرسال بريد تجريبي',
                    'online_store.email_templates.export' => 'تصدير قوالب البريد',
                ],
            ],

            'email_logs' => [
                'label' => 'سجل البريد الإلكتروني',
                'description' => 'سجل رسائل البريد المُرسلة من المتجر وإعادة إرسالها.',
                'icon' => 'inbox',
                'controller' => 'EmailLogController',
                'permissions' => [
                    'online_store.email_logs.view' => 'عرض سجل البريد',
                    'online_store.email_logs.resend' => 'إعادة إرسال بريد فاشل',
                    'online_store.email_logs.delete' => 'حذف سجلات بريد',
                    'online_store.email_logs.cleanup' => 'تنظيف السجلات القديمة',
                    'online_store.email_logs.export' => 'تصدير سجل البريد',
                ],
            ],

            // ═══════════════ الإعدادات ═══════════════
            'settings' => [
                'label' => 'إعدادات المتجر',
                'description' => 'الإعدادات العامة للمتجر الإلكتروني بمجموعاتها كافة.',
                'icon' => 'settings',
                'controller' => 'StoreSettingsController',
                'permissions' => [
                    'online_store.settings.view' => 'عرض إعدادات المتجر',
                    'online_store.settings.manage' => 'تعديل إعدادات المتجر',
                    'online_store.settings.reset' => 'استعادة الإعدادات الافتراضية',
                    'online_store.settings.import_export' => 'استيراد الإعدادات وتصديرها',
                    'online_store.settings.clear_cache' => 'مسح كاش المتجر',
                ],
            ],

            // ═══════════════ الجغرافيا (الشحن) ═══════════════
            'geo' => [
                'label' => 'الدول والمدن',
                'description' => 'قائمة الدول والمدن المستعملة في عناوين شحن المتجر.',
                'icon' => 'map-pin',
                'controller' => 'CountryController + CityController',
                'permissions' => [
                    'online_store.geo.view' => 'عرض الدول والمدن',
                    'online_store.geo.create' => 'إضافة دولة أو مدينة',
                    'online_store.geo.edit' => 'تعديل دولة أو مدينة',
                    'online_store.geo.delete' => 'حذف دولة أو مدينة',
                    'online_store.geo.toggle_status' => 'تفعيل دولة أو مدينة وتعطيلها',
                ],
            ],

            // ═══════════════ التقارير ═══════════════
            'reports' => [
                'label' => 'تقارير المتجر',
                'description' => 'تقارير المبيعات والطلبات والمنتجات والعملاء والكوبونات والشحن والتقييمات.',
                'icon' => 'bar-chart-2',
                'controller' => 'ReportsController',
                'permissions' => [
                    'online_store.reports.view' => 'الدخول إلى تقارير المتجر',
                    'online_store.reports.sales' => 'التقرير المالي للمبيعات',
                    'online_store.reports.orders' => 'تقرير الطلبات',
                    'online_store.reports.products' => 'تقرير المنتجات',
                    'online_store.reports.clients' => 'تقرير العملاء',
                    'online_store.reports.coupons' => 'تقرير الكوبونات',
                    'online_store.reports.shipping' => 'تقرير الشحن',
                    'online_store.reports.reviews' => 'تقرير التقييمات والأسئلة',
                ],
            ],
        ];
    }
}
