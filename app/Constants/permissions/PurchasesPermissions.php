<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات وحدة المشتريات الكاملة.
 *
 * نفس بنية [StockPermissions] حرفاً بحرف: `groups()` مصدرٌ واحد، و`all()`/
 * `describe()` مُشتقّتان منه. تُدمَج في [PermissionTableSeeder] وتُمنح عبر
 * سيدرٍ مستقل [PurchasesPermissionsSeeder].
 *
 * **التعايش مع النظام القديم**: كنترولران فقط من الثمانية عشر ما زالا
 * محروسين اليوم بأسماء صلاحياتٍ قديمة بنمط شرطة سفلية
 * (`purchase_cycle_*_manage*`/`*_approve_reject`، من `JobRole::$job_roles`)
 * — لم يُمَسّا، ولن تُضاف صلاحيات هذا الكتالوج كحراسة `middleware` جديدة
 * عليهما تفادياً لتكديس شرطين معاً (AND) قد يُقفل شاشاتٍ تعمل اليوم:
 * `PurchaseCycleController` (`purchase_cycle_*`)، و
 * `PurchaseOrdersController`
 * (`purchase_cycle_orders_manage`/`purchase_cycle_orders_approve_reject` —
 * رغم أن المجموعة `order_requests` أدناه معرَّفة له بصلاحياتٍ جديدة، فهي
 * غير مفعَّلة في الكود بعد؛ نفس وضع البقية قبل ترحيلها).
 *
 * `PurchaseRequestsController`، `QuotationsController`، `InvoicesPurchaseController`
 * (كان محروساً بـ`inv_manage_purchase_invoice_*`)، و`SupplierManagementController`
 * (كان بلا `middleware` إطلاقاً — فحوصٌ يدوية فقط بـ`inv_manage_suppliers_*`
 * لا وجود لملفّ `SuppliersController` بالمناسبة؛ المجموعة `legacy_suppliers`
 * أدناه صُحِّح مرجعها) — انتقلت جميعها فعلياً إلى صلاحيات هذا الكتالوج
 * (`purchases.orders.*`/`purchases.quotations.*`/`purchases.invoices.*`/
 * `purchases.suppliers.*`) على مستوى الكنترولر والمسار وأزرار الـblade معاً؛
 * استبدالٌ نظيف لا ازدواج فيه، فمن كان يملك الاسم القديم مُنح الاسم الجديد
 * صراحةً في قاعدة البيانات عند كل نقل (لا يكفي `permissions:sync-catalog`
 * وحده: ذاك يُنشئ صفّ الصلاحية فقط، لا يمنحها لدور).
 *
 * **فجوة مكتشَفة وسُدَّت**: عشرة كنترولرات أخرى كانت بلا أي حراسة صلاحيات
 * إطلاقاً — لا قديمة ولا من هذا الكتالوج — رغم أن مجموعاتها معرَّفة هنا منذ
 * البداية: التقارير السبعة كلّها (`purchases.reports.*`)، وCityNotices
 * (`purchases.debit_notices.*`)، وPurchasePayment (`purchases.payments.*`)،
 * وPurchasesDashboard (`purchases.dashboard.view`)، وPurchaseSettings
 * وInvoiceSettings معاً (`purchases.settings.*`)، وReturnsInvoice
 * (`purchases.returns.*`)، وViewPurchasePrice (`purchases.price_quotations.*`).
 * أُضيفت لها حراسة `middleware` في مُنشئ كلّ كنترولر بنفس نمط
 * Orders/Quotations، فمن يستعمل هذه الشاشات اليوم يحتاج أيضاً منحه
 * الصلاحية المقابلة صراحةً بعد `permissions:sync` وإلا فقد الوصول.
 */
class PurchasesPermissions extends BasePermissionCatalog
{
    /**
     * بادئة صلاحيات القسم — لبوّابات الدخول العامّة (بطاقةُ اللوحة وبندُ
     * الهيدر والشريط الجانبي) عبر `User::hasAnyPermissionWithPrefix()`:
     * أيُّ صلاحيةِ مشترياتٍ واحدة تكفي لإظهار المدخل، لا أسماءٌ بعينها.
     *
     * كانت هذه وحدَها من بين الكتالوجات بلا `PREFIX`، فحُرست مداخلُها
     * بتعدادٍ يدويٍّ لثماني صلاحيات من ثمانين — فمن مُنح «إضافة عرض سعر»
     * أو الاستلامَ أو المرتجعاتِ أو المدفوعات لم يرَ للمشتريات أثراً.
     */
    public const PREFIX = 'purchases.';

    public static function module(): array
    {
        return [
            'key' => 'purchases',
            'label' => 'المشتريات',
            'description' => 'دورة الشراء كاملةً: الطلبات وعروض الموردين وأوامر الشراء والفواتير والمرتجعات والمدفوعات.',
            'icon' => 'shopping-bag',
            'order' => 45,
        ];
    }

    /**
     * @return array<string, array{label:string, controller:string, permissions:array<string,string>}>
     */
    public static function groups(): array
    {
        return [
            'invoices' => [
                'label' => 'فواتير المشتريات',
                'description' => 'فواتير المشتريات وأثرها في المخزون وحساب المورد.',
                'icon' => 'file-text',
                'controller' => 'Purchases/InvoicesPurchaseController',
                'permissions' => [
                    'purchases.invoices.view' => 'عرض فواتير المشتريات',
                    'purchases.invoices.create' => 'إضافة فاتورة مشتريات',
                    // النسخُ صلاحيةٌ مستقلّة عن الإضافة بطلب المستخدم
                    // (2026-09-21): كان زرُّ «نسخ» محروساً بـ`create`، فمن
                    // مُنح الإضافةَ نسخ بالضرورة ولا سبيل إلى منعه.
                    'purchases.invoices.duplicate' => 'نسخ فاتورة مشتريات',
                    'purchases.invoices.edit' => 'تعديل فاتورة مشتريات',
                    'purchases.invoices.delete' => 'حذف فاتورة مشتريات',
                    'purchases.invoices.approve' => 'اعتماد فاتورة مشتريات',
                    'purchases.invoices.export' => 'تصدير/طباعة فواتير المشتريات',
                ],
            ],
            // مفتاح المجموعة 'orders' والبادئة purchases.orders.* بقيا كما هما
            // (معرِّفان داخليان) رغم أن التسمية العربية الصحيحة لهذا الكنترولر
            // هي «طلبات الشراء» لا «أوامر الشراء» — أُكِّد هذا مع المستخدم
            // ثلاث مرّات (آخرها بربط صريح لملفّ الكنترولر). لم تُعَد تسمية
            // المفتاح تفادياً لتغيير كل صلاحية مسندة في قاعدة البيانات؛
            // النص العربي وحده هو ما يظهر للمستخدم فصُحِّح.
            'orders' => [
                'label' => 'طلبات الشراء',
                'description' => 'طلبات الشراء المرسلة إلى الموردين.',
                'icon' => 'clipboard',
                'controller' => 'Purchases/PurchaseRequestsController',
                'permissions' => [
                    'purchases.orders.view' => 'عرض طلبات الشراء',
                    'purchases.orders.create' => 'إضافة طلب شراء',
                    'purchases.orders.edit' => 'تعديل طلب شراء',
                    'purchases.orders.delete' => 'حذف طلب شراء',
                    'purchases.orders.approve_reject' => 'اعتماد/رفض طلب شراء',
                    'purchases.orders.export' => 'تصدير/طباعة طلبات الشراء',
                ],
            ],
            // نفس الملاحظة أعلاه بالعكس: 'order_requests'/purchases.order_requests.*
            // مفتاحان داخليان لكنترولر PurchaseOrdersController الذي
            // تسميته العربية الصحيحة «أوامر الشراء».
            'order_requests' => [
                'label' => 'أوامر الشراء',
                // لم يعد التحويل مباشراً إلى فاتورة: أمر الشراء يمرّ أولاً
                // على «استلام المشتريات» (مجموعة receiving أدناه)، ومنه
                // وحده تُنشأ الفاتورة — انظر PurchaseReceivingController.
                'description' => 'أوامر الشراء — تُنشأ من تحويل عرض سعر مورّد، وتتحوّل بعد اعتمادها إلى استلام مشتريات ثم فاتورة.',
                'icon' => 'edit-3',
                'controller' => 'Purchases/PurchaseOrdersController',
                'permissions' => [
                    'purchases.order_requests.view' => 'عرض أوامر الشراء',
                    'purchases.order_requests.create' => 'إضافة أمر شراء',
                    'purchases.order_requests.edit' => 'تعديل أمر شراء',
                    'purchases.order_requests.delete' => 'حذف أمر شراء',
                    'purchases.order_requests.approve_reject' => 'اعتماد/رفض أمر شراء',
                    /* كانت هنا `purchases.order_requests.convert` — «تحويل أمر
                       شراء إلى فاتورة». الفعلُ نفسُه مُعطَّل منذ أن صار الطريق
                       يمرّ على «استلام المشتريات»، فبقيت خانةً في شاشة الأدوار
                       تحرس دالّةً لا تفعل إلا إظهارَ رسالة. حُذفت
                       ([الترحيل 2026_09_12_220000])، والمسارُ باقٍ على
                       `purchases.order_requests.view` فلا يُكسر رابطٌ قديم. */
                    'purchases.order_requests.export' => 'تصدير/طباعة أوامر الشراء',
                ],
            ],
            'receiving' => [
                'label' => 'استلام المشتريات',
                'description' => 'استلام البضاعة الواردة من أوامر الشراء وإدخالها إلى المستودع، تمهيداً لتحويلها إلى فاتورة.',
                'icon' => 'package',
                'controller' => 'Purchases/PurchaseReceivingController',
                'permissions' => [
                    'purchases.receiving.view' => 'عرض استلام المشتريات',
                    'purchases.receiving.create' => 'إضافة استلام مشتريات',
                    'purchases.receiving.edit' => 'تعديل استلام مشتريات',
                    'purchases.receiving.delete' => 'حذف استلام مشتريات',
                    'purchases.receiving.approve' => 'اعتماد استلام مشتريات',
                    'purchases.receiving.convert' => 'تحويل استلام مشتريات إلى فاتورة',
                    'purchases.receiving.export' => 'تصدير/طباعة استلام المشتريات',
                ],
            ],
            'quotations' => [
                'label' => 'طلبات عروض الأسعار',
                'description' => 'طلبات عروض الأسعار المرسلة إلى الموردين.',
                'icon' => 'mail',
                'controller' => 'Purchases/QuotationsController',
                'permissions' => [
                    'purchases.quotations.view' => 'عرض طلبات عروض الأسعار',
                    'purchases.quotations.create' => 'إضافة طلب عرض سعر',
                    'purchases.quotations.duplicate' => 'نسخ طلب عرض سعر',
                    'purchases.quotations.edit' => 'تعديل طلب عرض سعر',
                    'purchases.quotations.delete' => 'حذف طلب عرض سعر',
                    'purchases.quotations.approve_reject' => 'اعتماد/رفض طلب عرض سعر',
                    'purchases.quotations.export' => 'تصدير/طباعة طلبات عروض الأسعار',
                ],
            ],
            'price_quotations' => [
                'label' => 'عروض أسعار الموردين',
                'description' => 'عروض أسعار الموردين والمفاضلة بينها.',
                'icon' => 'file',
                'controller' => 'Purchases/ViewPurchasePriceController',
                'permissions' => [
                    'purchases.price_quotations.view' => 'عرض عروض أسعار الموردين',
                    'purchases.price_quotations.create' => 'إضافة عرض سعر',
                    'purchases.price_quotations.duplicate' => 'نسخ عرض سعر',
                    'purchases.price_quotations.edit' => 'تعديل عرض سعر',
                    'purchases.price_quotations.delete' => 'حذف عرض سعر',
                    'purchases.price_quotations.approve_reject' => 'اعتماد/رفض عرض سعر',
                    'purchases.price_quotations.convert' => 'تحويل عرض سعر إلى أمر شراء',
                    'purchases.price_quotations.export' => 'تصدير/طباعة عروض الأسعار',
                ],
            ],
            'returns' => [
                'label' => 'مرتجعات المشتريات',
                'description' => 'مرتجعات المشتريات.',
                'icon' => 'rotate-ccw',
                'controller' => 'Purchases/ReturnsInvoiceController',
                'permissions' => [
                    'purchases.returns.view' => 'عرض مرتجعات المشتريات',
                    'purchases.returns.create' => 'إضافة مرتجع مشتريات',
                    'purchases.returns.duplicate' => 'نسخ مرتجع مشتريات',
                    'purchases.returns.edit' => 'تعديل مرتجع مشتريات',
                    'purchases.returns.delete' => 'حذف مرتجع مشتريات',
                    'purchases.returns.approve' => 'اعتماد مرتجع مشتريات',
                    'purchases.returns.export' => 'تصدير/طباعة مرتجعات المشتريات',
                ],
            ],
            'debit_notices' => [
                'label' => 'إشعارات مدينة (مشتريات)',
                'description' => 'الإشعارات المدينة على الموردين.',
                'icon' => 'file-minus',
                'controller' => 'Purchases/CityNoticesController',
                'permissions' => [
                    'purchases.debit_notices.view' => 'عرض الإشعارات المدينة',
                    'purchases.debit_notices.create' => 'إضافة إشعار مدين',
                    'purchases.debit_notices.edit' => 'تعديل إشعار مدين',
                    'purchases.debit_notices.delete' => 'حذف إشعار مدين',
                    'purchases.debit_notices.approve' => 'اعتماد إشعار مدين',
                    'purchases.debit_notices.export' => 'تصدير/طباعة الإشعارات المدينة',
                ],
            ],
            'payments' => [
                'label' => 'مدفوعات الموردين',
                'description' => 'مدفوعات الموردين وتخصيصها على فواتيرهم.',
                'icon' => 'dollar-sign',
                'controller' => 'Purchases/PurchasePaymentController',
                'permissions' => [
                    'purchases.payments.view' => 'عرض مدفوعات الموردين',
                    'purchases.payments.create' => 'إضافة دفعة',
                    'purchases.payments.edit' => 'تعديل دفعة',
                    'purchases.payments.delete' => 'حذف دفعة',
                    'purchases.payments.cancel' => 'إلغاء دفعة',
                    'purchases.payments.bulk_create' => 'دفعات مجمَّعة',
                    'purchases.payments.export' => 'تصدير/طباعة المدفوعات',
                ],
            ],
            'suppliers' => [
                'label' => 'الموردون',
                'description' => 'سجل الموردين وبياناتهم وأرصدتهم.',
                'icon' => 'users',
                'controller' => 'Purchases/SupplierManagementController',
                'permissions' => [
                    'purchases.suppliers.view' => 'عرض الموردين',
                    'purchases.suppliers.create' => 'إضافة مورد',
                    'purchases.suppliers.edit' => 'تعديل مورد',
                    'purchases.suppliers.delete' => 'حذف مورد',
                    'purchases.suppliers.view_statement' => 'عرض كشف حساب المورد',
                    'purchases.suppliers.manage_opening_balance' => 'تعديل الرصيد الافتتاحي للمورد',
                    'purchases.suppliers.assign_employee' => 'تعيين مورد لموظف',
                ],
            ],
            /* محافظ الموردين — دفعاتٌ مقدَّمة لدى المورد تُخصَم منها
               فواتير الشراء. حسابٌ تحت الأصول لكل مورد. */
            'supplier_wallet' => [
                'label' => 'محافظ الموردين',
                'description' => 'رصيدنا لدى المورد: دفعةٌ مقدَّمة واستردادٌ وسدادُ فواتير منها.',
                'icon' => 'credit-card',
                'controller' => 'Purchases/SupplierWalletController',
                'permissions' => [
                    'purchases.supplier_wallet.view' => 'عرض محافظ الموردين وكشف حركتها',
                    'purchases.supplier_wallet.deposit' => 'تسجيل دفعة مقدَّمة لمورد',
                    'purchases.supplier_wallet.withdraw' => 'استرداد من محفظة مورد',
                    'purchases.supplier_wallet.pay_invoice' => 'سداد فاتورة شراء من محفظة المورد',
                ],
            ],

            'settings' => [
                'label' => 'إعدادات المشتريات',
                'description' => 'إعدادات دورة الشراء.',
                'icon' => 'settings',
                'controller' => 'Purchases/PurchaseSettingsController + InvoiceSettingsController',
                'permissions' => [
                    'purchases.settings.view' => 'عرض إعدادات المشتريات',
                    'purchases.settings.edit' => 'تعديل إعدادات المشتريات',
                ],
            ],
            'dashboard' => [
                'label' => 'لوحة تحكّم المشتريات',
                'description' => 'لوحة المشتريات ومؤشراتها.',
                'icon' => 'pie-chart',
                'controller' => 'Purchases/PurchasesDashboardController',
                'permissions' => [
                    'purchases.dashboard.view' => 'عرض لوحة تحكّم المشتريات',
                ],
            ],
            /*
             * ═══════════ التقارير — بوابةٌ عامة، ثمّ تقريرٌ تقرير ═══════════
             *
             * كانت `purchases.reports.view` وحدَها تحرس الثمانية جميعاً،
             * فمن فُتح له «المشتريات حسب البند» انفتحت له أرصدةُ الموردين
             * وأعمارُ ديونهم ومدفوعاتُهم معاً — ولا سبيلَ لمنح أمينِ
             * مستودعٍ تقريرَ الاستلام دون أن يقرأ مديونيةَ المنشأة.
             *
             * وبطاقاتُ لوحة التقارير كانت تُعرض كلُّها لكل من يفتح اللوحة،
             * لأنّ الصلاحيةَ التي تحرسها هي الصلاحيةُ التي فتحها.
             *
             * البوابةُ تبقى `purchases.reports.view` — بها يُفتح الفهرسُ
             * وتُعرَض البطاقاتُ المسموحة — وما تحتها تقريرٌ تقرير، على
             * نفس نمط `SalesPermissions` و`ClientsPermissions`.
             */
            'reports' => [
                'label' => 'تقارير المشتريات',
                'description' => 'تقارير المشتريات والموردين — لكلٍّ صلاحيته كي يُمنح أمينُ المستودع تقريرَ الاستلام دون أرصدة الموردين. ونطاقُ ما يراه في كلٍّ منها من «نطاق البيانات».',
                'icon' => 'bar-chart-2',
                'controller' => 'Purchases/Reports/*',
                'permissions' => [
                    'purchases.reports.view' => 'الدخول إلى تقارير المشتريات (فهرس التقارير)',
                    'purchases.reports.by_employee' => 'المشتريات حسب الموظف',
                    'purchases.reports.by_supplier' => 'المشتريات حسب المورد',
                    'purchases.reports.by_product' => 'المشتريات حسب المنتج',
                    'purchases.reports.suppliers_directory' => 'دليل الموردين',
                    'purchases.reports.debt_aging' => 'أعمار ديون الموردين',
                    'purchases.reports.supplier_balances' => 'أرصدة الموردين',
                    'purchases.reports.supplier_wallets' => 'تقرير محافظ الموردين',
                    'purchases.reports.payments' => 'مدفوعات المشتريات',
                    'purchases.reports.receiving' => 'المشتريات حسب البند (المستلم)',
                    'purchases.reports.export' => 'تصدير تقارير المشتريات',
                ],
            ],

        ];
    }
}
