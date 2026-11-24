<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات نظام المبيعات — المصدر الوحيد لأسمائها.
 *
 * **لماذا كتالوج مستقل؟**
 *
 * وحدة المبيعات اليوم بلا صلاحيات إطلاقاً: مسحُ `Modules/Sales` كلّها لا
 * يجد ولا `permission:` واحدة ولا `hasPermission()`. والحماية الموجودة سبعة
 * أنماط متفرقة (فحص دور مباشر، أعمدة `job_roles`، `role:manager` على مسار،
 * `abort(403)` داخل الدوال) — لا نظاماً واحداً.
 *
 * وهذا الملف لا يعتمد `JobRole::$job_roles`: تلك القائمة أعمدةٌ في جدول
 * وأسماؤها بنمط `sales_add_invoices`، ونظامها في طريقه إلى الاستبدال. النمط
 * هنا نقطي `sales.invoices.create` — وهو ما اعتمدته السيدرات الأحدث في
 * المشروع (`settings.backup.manage`, `settings.subscription.view`)، ولا
 * يتصادم مع الأسماء القديمة فيمكن للنظامين التعايش أثناء الانتقال.
 *
 * **محورا الصلاحية منفصلان عمداً** (وهو ما يوصي به تقرير الإصلاح، بند 13.4):
 * - **العمليات** (`sales.invoices.create`) تحدّد *ماذا يفعل المستخدم*.
 * - **النطاق** (`data_scope_branch`) يحدّد *على أي بيانات*.
 *
 * خلطهما هو سبب انفجار الأسماء في النظام القديم: `sales_add_invoices` مقابل
 * `sales_add_own_invoices`، و`sales_view_all_quotes` مقابل
 * `sales_view_own_quotes` — أي ضربُ كل عملية في كل نطاق. بفصلهما تكفي عمليةٌ
 * واحدة ونطاقٌ واحد.
 *
 * **المستبعَد** وفق «قرارات الحذف» في تقرير الإصلاح: أهداف المبيعات
 * والعمولة (`SalesTargetController`, `WeeklyCommissionController`)، والفاتورة
 * الإلكترونية. أما «أهداف التحصيلات» و«إعدادات الأهداف» فتبقى — ينصّ
 * التقرير على بقائها صراحةً.
 */
class SalesPermissions extends BasePermissionCatalog
{
    public static function module(): array
    {
        return [
            'key' => 'sales',
            'label' => 'المبيعات',
            'description' => 'دورة البيع كاملةً: العروض والفواتير والمرتجعات والدفعات والأقساط وأوامر التوريد وتقاريرها.',
            'icon' => 'shopping-cart',
            'order' => 20,
        ];
    }

    /**
     * صلاحيات العمليات مجموعةً بالشاشة.
     *
     * المفتاح اسم الصلاحية، والقيمة وصفها العربي المعروض في شاشة الأدوار.
     *
     * @return array<string, array{label: string, controller: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            'invoices' => [
                'label' => 'فواتير المبيعات',
                'description' => 'فواتير المبيعات: إنشاؤها وتعديلها وطباعتها وإرسالها وعرض ربحها.',
                'icon' => 'file-text',
                'controller' => 'InvoicesController',
                'permissions' => [
                    'sales.invoices.view' => 'عرض الفواتير',
                    'sales.invoices.create' => 'إنشاء فاتورة',
                    'sales.invoices.edit' => 'تعديل فاتورة',
                    'sales.invoices.delete' => 'حذف فاتورة',
                    'sales.invoices.print' => 'طباعة فاتورة',
                    'sales.invoices.print_tax' => 'طباعة الفاتورة الضريبية',
                    'sales.invoices.export_pdf' => 'تصدير الفاتورة PDF',
                    'sales.invoices.send' => 'إرسال الفاتورة للعميل',
                    'sales.invoices.labels' => 'طباعة الملصقات وبوليصة الشحن',
                    'sales.invoices.notes' => 'إدارة ملاحظات الفاتورة',
                    'sales.invoices.mark_paid' => 'تعليم الفاتورة مدفوعة',
                    'sales.invoices.view_assigned_clients' => 'عرض فواتير العملاء المُسنَدين إليه',
                    'sales.invoices.bypass_location' => 'إنشاء فاتورة من أي موقع (بلا تحقّق من موقع العميل)',
                    'sales.invoices.edit_date' => 'تعديل تاريخ المستند (فاتورة أو إشعار دائن أو مرتجع)',
                    'sales.invoices.invoice_all_products' => 'فوترة جميع المنتجات',
                    'sales.invoices.view_profit' => 'عرض ربح الفاتورة',
                    'sales.invoices.change_seller' => 'تغيير مندوب البيع',
                ],
            ],

            'quotes' => [
                'label' => 'عروض الأسعار',
                'description' => 'عروض الأسعار للعملاء وتحويلها إلى فواتير.',
                'icon' => 'file',
                'controller' => 'QuoteController',
                'permissions' => [
                    'sales.quotes.view' => 'عرض عروض الأسعار',
                    'sales.quotes.create' => 'إنشاء عرض سعر',
                    'sales.quotes.edit' => 'تعديل عرض سعر',
                    'sales.quotes.delete' => 'حذف عرض سعر',
                    'sales.quotes.print' => 'طباعة وتصدير عرض السعر',
                    'sales.quotes.send_link' => 'إرسال عرض السعر بالبريد',
                    'sales.quotes.convert' => 'تحويل عرض السعر إلى فاتورة',
                ],
            ],

            /* أوامر البيع — مستندٌ مستقلّ بصلاحياته، وإن شارك عروضَ
               الأسعار جدولَها وشاشاتِها: من يُصدر عرضاً ليس بالضرورة من
               يعتمد أمراً ملزماً، ومن يحوّل الأمر إلى فاتورة يمسّ المخزون
               والقيود. */
            'sales_orders' => [
                'label' => 'أوامر البيع',
                'description' => 'أوامر البيع المعتمدة وتحويلها إلى فواتير مبيعات.',
                'icon' => 'clipboard',
                'controller' => 'SalesOrderController',
                'permissions' => [
                    'sales.sales_orders.view' => 'عرض أوامر البيع',
                    'sales.sales_orders.create' => 'إنشاء أمر بيع',
                    'sales.sales_orders.edit' => 'تعديل أمر بيع',
                    'sales.sales_orders.delete' => 'حذف أمر بيع',
                    'sales.sales_orders.print' => 'طباعة وتصدير أمر البيع',
                    'sales.sales_orders.send_link' => 'إرسال أمر البيع بالبريد',
                    'sales.sales_orders.convert' => 'تحويل أمر البيع إلى فاتورة',
                ],
            ],

            'return_invoices' => [
                'label' => 'فواتير المرتجع',
                'description' => 'فواتير المرتجع وأثرها في المخزون وحساب العميل.',
                'icon' => 'rotate-ccw',
                'controller' => 'ReturnInvoiceController',
                'permissions' => [
                    'sales.returns.view' => 'عرض المرتجعات',
                    'sales.returns.create' => 'إنشاء مرتجع',
                    'sales.returns.edit' => 'تعديل مرتجع',
                    'sales.returns.delete' => 'حذف مرتجع',
                    'sales.returns.print' => 'طباعة المرتجع',
                    'sales.returns.send' => 'إرسال المرتجع بالبريد',
                    'sales.returns.bypass_location' => 'إنشاء مرتجع من أي موقع (بلا تحقّق من موقع العميل)',
                ],
            ],

            'credit_notifications' => [
                'label' => 'الإشعارات الدائنة',
                'description' => 'الإشعارات الدائنة الصادرة للعملاء.',
                'icon' => 'file-minus',
                'controller' => 'CreditNotificationController',
                'permissions' => [
                    'sales.credit_notes.view' => 'عرض الإشعارات الدائنة',
                    'sales.credit_notes.create' => 'إنشاء إشعار دائن',
                    'sales.credit_notes.edit' => 'تعديل إشعار دائن',
                    'sales.credit_notes.delete' => 'حذف إشعار دائن',
                    'sales.credit_notes.print' => 'طباعة إشعار دائن',
                    'sales.credit_notes.send' => 'إرسال إشعار دائن',
                    'sales.credit_notes.cancel' => 'إلغاء إشعار دائن',
                    'sales.credit_notes.bypass_location' => 'إنشاء إشعار دائن من أي موقع (بلا تحقّق من موقع العميل)',
                ],
            ],

            'payments' => [
                'label' => 'سندات القبض',
                'description' => 'سندات القبض من العملاء وتخصيصها على الفواتير.',
                'icon' => 'dollar-sign',
                'controller' => 'SalesPaymentController',
                'permissions' => [
                    'sales.payments.view' => 'عرض دفعات العملاء',
                    'sales.payments.create' => 'تسجيل دفعة عميل',
                    'sales.payments.edit' => 'تعديل دفعة عميل',
                    'sales.payments.delete' => 'حذف دفعة عميل',
                    'sales.payments.print' => 'طباعة إيصال دفعة العميل',
                    'sales.payments.cancel' => 'إلغاء دفعة عميل',
                    'sales.payments.bulk' => 'التحصيل الجماعي لعدة فواتير',
                    'sales.payments.view_assigned_clients' => 'عرض مدفوعات العملاء المُسنَدين إليه',
                ],
            ],

            'periodic_invoices' => [
                'label' => 'الفواتير الدورية',
                'description' => 'الفواتير الدورية المتكرّرة وجدولتها.',
                'icon' => 'repeat',
                'controller' => 'PeriodicInvoicesController',
                'permissions' => [
                    'sales.periodic_invoices.view' => 'عرض الفواتير الدورية',
                    'sales.periodic_invoices.create' => 'إنشاء فاتورة دورية',
                    'sales.periodic_invoices.edit' => 'تعديل فاتورة دورية',
                    'sales.periodic_invoices.delete' => 'حذف فاتورة دورية',
                    'sales.periodic_invoices.bulk_delete' => 'الحذف الجماعي للفواتير الدورية',
                ],
            ],

            'revolving_invoices' => [
                'label' => 'الفواتير المتجددة',
                'description' => 'الفواتير المتجدّدة.',
                'icon' => 'refresh-cw',
                'controller' => 'RevolvingInvoicesController',
                'permissions' => [
                    'sales.revolving_invoices.view' => 'عرض الفواتير المتجددة',
                    'sales.revolving_invoices.create' => 'إنشاء فاتورة متجددة',
                ],
            ],

            'installments' => [
                'label' => 'الأقساط',
                'description' => 'تقسيط الفواتير وتحصيل الأقساط.',
                'icon' => 'calendar',
                'controller' => 'InstallmentsController',
                'permissions' => [
                    'sales.installments.view' => 'عرض الأقساط',
                    'sales.installments.create' => 'إنشاء قسط',
                    'sales.installments.edit_amount' => 'تعديل مبلغ القسط',
                    'sales.installments.agreement' => 'إدارة اتفاقية التقسيط',
                ],
            ],

            /*
             * الإقفال اليومي — صار مربوطاً بحراسة `middleware` فعلية على
             * المسارات وفي بانية المتحكّم معاً، بنفس نمط بقيّة شاشات
             * المبيعات. وكان الربط مؤجَّلاً لأن الصفوف نفسها لم تكن في جدول
             * `permissions` أصلاً، فلا دور يملكها: الحراسة يومها كانت تُقفل
             * الشاشة على الجميع بمن فيهم المالك. أُنشئت الصفوف بـ
             * `permissions:sync-catalog` ومُنحت للأدوار قبل تركيب الحراسة.
             *
             * `bulk` منفصلة عن `create` عن قصد: الإقفال الجماعي يكتب إقفال
             * يومٍ لكلّ موظّف دفعةً واحدة — فعلٌ إداريّ لا يصحّ أن يملكه كلُّ
             * من يقفل يومَه هو. والشاشة نفسها تسمّيه «للإدارة»
             * (`sales_daily_closing.actions.bulk_close_hint`) ولها رسالة
             * رفضٍ جاهزة له (`denied.bulk`) لم يكن يصدرها شيء.
             *
             * **صلاحيةٌ لكلّ زرّ في الشاشة** — كانت خمسٌ تحرس ثمانية أزرار،
             * فزرّا «إقفال تلقائي» و«مراجعة ومطابقة النظام» يتبعان «الإقفال
             * اليدوي»، و«طباعة PDF» يتبع طباعةَ السطر، و«تقرير جماعي» يتبع
             * «الإقفال الجماعي»: لا سبيل لمنح المندوب المراجعةَ دون الإقفال
             * التلقائي الأعمى، ولا تقريرَ الإدارة الجماعي دون أن يُقفل هو
             * للجميع. والنظائر الجديدة مُنحت لمن يملك أصلها
             * (`2026_09_11_160000_grant_daily_closing_button_permissions`)
             * فلا يختفي زرٌّ عن أحدٍ كان يراه.
             */
            'daily_closing' => [
                'label' => 'الإقفال اليومي',
                'description' => 'مطابقة يوم الموظف بما سجّله النظام من فواتير ودفعات ومرتجعات.',
                'icon' => 'check-square',
                'controller' => 'DailyClosingController',
                'permissions' => [
                    'sales.daily_closing.view' => 'عرض الإقفال اليومي',
                    'sales.daily_closing.create' => 'الإقفال اليدوي (إدخال الأرقام ومطابقتها)',
                    'sales.daily_closing.auto_close' => 'الإقفال التلقائي بأرقام النظام',
                    'sales.daily_closing.review' => 'مراجعة ومطابقة بيانات النظام ثم الإقفال',
                    'sales.daily_closing.delete' => 'حذف إقفال يومي',
                    'sales.daily_closing.print' => 'طباعة تفاصيل إقفالٍ واحد',
                    'sales.daily_closing.print_report' => 'طباعة تقرير الإقفالات PDF',
                    'sales.daily_closing.bulk' => 'الإقفال الجماعي لكل الموظفين',
                    'sales.daily_closing.bulk_report' => 'التقرير الجماعي لكل الموظفين (بلا تخزين)',
                    // صلاحية نطاقٍ لا فعل — كصلاحية «عرض فواتير العملاء
                    // المُسنَدين» في مجموعة الفواتير. كان النطاق يُقرأ من
                    // `hasRole('manager')`: اسمُ دورٍ واحدٍ حرفياً، فالمالك
                    // ومدير المبيعات لا يريان إلّا إقفالاتهما هما.
                    'sales.daily_closing.view_all' => 'عرض إقفالات كل الموظفين',
                ],
            ],

            'product_exchange' => [
                'label' => 'تبديل المنتجات',
                'description' => 'تبديل المنتجات للعملاء وما يقابله من مرتجع.',
                'icon' => 'shuffle',
                'controller' => 'ProductExchangeController',
                'permissions' => [
                    'sales.exchanges.view' => 'عرض عمليات التبديل',
                    'sales.exchanges.create' => 'إنشاء عملية تبديل',
                    // التعديل لم يكن في السجلّ لأنّ الشاشة نفسها لم تكن
                    // موجودة — أُضيف مع `edit`/`update`
                    'sales.exchanges.edit' => 'تعديل عملية تبديل',
                    'sales.exchanges.delete' => 'حذف عملية تبديل',
                    'sales.exchanges.print' => 'طباعة عملية التبديل',
                    'sales.exchanges.dashboard' => 'لوحة متابعة التبديل',
                    'sales.exchanges.manage_limits' => 'إدارة حدود التبديل للموظفين والعملاء',
                    'sales.exchange_reasons.manage' => 'إدارة أسباب التبديل',
                ],
            ],

            'offers' => [
                'label' => 'العروض والهدايا',
                'description' => 'العروض الترويجية والهدايا وشروط استحقاقها.',
                'icon' => 'gift',
                'controller' => 'OffersController / GiftOfferController',
                'permissions' => [
                    'sales.offers.view' => 'عرض العروض',
                    'sales.offers.create' => 'إنشاء عرض',
                    'sales.offers.edit' => 'تعديل عرض',
                    'sales.offers.delete' => 'حذف عرض',
                    'sales.offers.change_status' => 'تفعيل وتعطيل العروض',
                    'sales.gift_offers.view' => 'عرض عروض الهدايا',
                    'sales.gift_offers.create' => 'إنشاء عرض هدية',
                    'sales.gift_offers.edit' => 'تعديل عرض هدية',
                    'sales.gift_offers.delete' => 'حذف عرض هدية',
                ],
            ],

            /*
             * `sales.collections_targets.view`/`.report` تُستعملان أيضاً في
             * شريط الموظف الجانبي (`resources/views/layouts/sidebar.blade.php`)
             * لبندَي «أهدافي الحالية» و«تقرير التحصيل اليومي» —
             * `App\Http\Controllers\EmployeeTargetController` (النسخة
             * الخاصة بالموظف من الشاشة نفسها، مقابل `CollectionsTargetController`
             * الإداري)، وهو أيضاً بلا `middleware('permission:...')` على
             * مساراته حتى الآن؛ كان بندا الشريط بلا أي فحصٍ قبل هذا الربط.
             */
            'collections_targets' => [
                'label' => 'أهداف التحصيلات',
                'description' => 'أهداف التحصيل وتسويتها.',
                'icon' => 'target',
                'controller' => 'CollectionsTargetController / TargetSettingsController / EmployeeTargetController',
                'permissions' => [
                    'sales.collections_targets.view' => 'عرض أهداف التحصيلات',
                    'sales.collections_targets.create' => 'إنشاء هدف تحصيل',
                    'sales.collections_targets.edit' => 'تعديل هدف تحصيل',
                    'sales.collections_targets.delete' => 'حذف هدف تحصيل',
                    'sales.collections_targets.report' => 'تقرير أهداف التحصيلات',
                    'sales.collections_targets.settle' => 'تسوية هدف التحصيل',
                    'sales.target_settings.view' => 'عرض إعدادات الأهداف',
                    'sales.target_settings.update' => 'تعديل إعدادات الأهداف',
                ],
            ],

            /*
             * العمولات الأسبوعية — كان `WeeklyCommissionController` المتحكّمَ
             * الوحيد في الوحدة بلا حراسةٍ إطلاقاً: لا في بانيته ولا على
             * مساراته الأربعة. ودالّتاه `payout` و`payoutAll` **تصرفان مالاً
             * من الخزينة** وتكتبان قيداً، فكان أيُّ مستخدمٍ مسجَّلٍ يبلغهما.
             *
             * والشاشة مقصورةٌ على عمولات الضاغط نفسِه (`$user->employee_id`)
             * فلا يصرف أحدٌ لغيره — لكنّ ذلك قيدُ نطاقٍ لا حراسة: مستخدمٌ لا
             * شأن له بالمبيعات أصلاً كان يفتحها ويصرف ما وجد.
             *
             * والصرفُ منفصلٌ عن العرض عمداً: منشأةٌ تريد أن يرى المندوبُ ما
             * استحقّ ولا يصرفه بنفسه تمنح الأولى وتمنع الثانية.
             */
            'weekly_commissions' => [
                'label' => 'العمولات الأسبوعية',
                'description' => 'عمولةُ الموظف من أهداف المبيعات، يصرفها من خزينته مباشرة.',
                'icon' => 'award',
                'controller' => 'WeeklyCommissionController',
                'permissions' => [
                    'sales.commissions.view' => 'عرض عمولاتي الأسبوعية',
                    'sales.commissions.payout' => 'صرف العمولة من الخزينة',
                ],
            ],

            /*
             * محفظة العملاء — قائمة أرصدة العملاء الحالية مع أداة تسوية
             * سريعة تكتب قيداً محاسبياً، بدل فتح ملفّ كل عميل على حدة من
             * وحدة العملاء لتعديل رصيده الافتتاحي. تُعيد استخدام حقل
             * الرصيد الافتتاحي نفسه (`accounts.opening_balance`) والقيد
             * المرجعي `OB-{account_id}`، لكنّ حسابها المقابل مستقلٌّ
             * (`customer_settlement_account`) كي تُميَّز قيود التسوية عن
             * القيود الافتتاحية الحقيقية في التقارير.
             */
            'settings' => [
                'label' => 'إعدادات المبيعات',
                'description' => 'إعدادات المبيعات وقوالبها وضوابطها.',
                'icon' => 'settings',
                'controller' => 'SittingInvoiceController / ShippingOptionsController / OrderSourceController',
                'permissions' => [
                    'sales.settings.view' => 'عرض إعدادات المبيعات',
                    'sales.settings.update' => 'تعديل إعدادات المبيعات',
                    'sales.settings.invoice_designs' => 'إدارة تصاميم الفواتير',
                    'sales.settings.test_print' => 'الطباعة التجريبية',
                    'sales.shipping_options.view' => 'عرض خيارات الشحن',
                    'sales.shipping_options.manage' => 'إدارة خيارات الشحن',
                    'sales.order_sources.view' => 'عرض مصادر الطلب',
                    'sales.order_sources.manage' => 'إدارة مصادر الطلب',
                ],
            ],

            'notifications' => [
                'label' => 'إشعارات المبيعات',
                'description' => 'إشعارات المبيعات وتنبيهاتها.',
                'icon' => 'bell',
                'controller' => 'NotificationsController',
                'permissions' => [
                    'sales.notifications.view' => 'عرض إشعارات المبيعات',
                    'sales.notifications.send' => 'إرسال إشعار',
                    'sales.notifications.reply' => 'الرد على إشعارات المبيعات',
                ],
            ],

            'dashboard' => [
                'label' => 'لوحة المبيعات',
                'description' => 'لوحة المبيعات ومؤشراتها.',
                'icon' => 'pie-chart',
                'controller' => 'DashboardController',
                'permissions' => [
                    'sales.dashboard.view' => 'عرض لوحة المبيعات',
                    'sales.dashboard.financial_analysis' => 'عرض التحليل المالي',
                ],
            ],

            /*
             * التقارير — عشرة ملفات في `Report/` زائد `SalesReportsController`،
             * وكلها اليوم خلف `auth` وحدها بلا أي صلاحية (بند 8.1 في تقرير
             * التحسين: «ثغرة تسريب بيانات»). فمن يعرف الرابط يقرأ أرباح
             * المنشأة كاملةً.
             *
             * `sales.reports.view` بوابةٌ عامة، وما تحتها تقريرٌ تقرير — كي
             * يمكن منح محصِّلٍ تقريرَ تحصيلاته دون تقرير الأرباح.
             */
            'reports' => [
                'label' => 'تقارير المبيعات',
                'description' => 'تقارير المبيعات — لكلٍّ صلاحيته كي يُمنح المحصِّل تقريرَه دون تقرير الأرباح.',
                'icon' => 'bar-chart-2',
                'controller' => 'Report/* + SalesReportsController',
                'permissions' => [
                    'sales.reports.view' => 'الدخول إلى تقارير المبيعات (بوابة القائمة)',
                    'sales.reports.by_employee' => 'تقرير المبيعات حسب الموظف',
                    'sales.reports.by_customer' => 'تقرير المبيعات حسب العميل',
                    'sales.reports.by_product' => 'تقرير المبيعات حسب المنتج',
                    'sales.reports.by_product_group' => 'تقرير المبيعات حسب مجموعة المنتج',
                    'sales.reports.by_item' => 'تقرير المبيعات حسب الصنف',
                    'sales.reports.profits' => 'تقرير أرباح المبيعات',
                    'sales.reports.collections' => 'تقرير التحصيلات',
                    'sales.reports.exchanges' => 'تقرير التبديلات',
                    'sales.reports.commissions' => 'تقرير العمولات (المبيعات والتحصيلات)',
                    'sales.reports.payments_by_customer' => 'تقرير المدفوعات حسب العميل',
                    'sales.reports.payments_by_employee' => 'تقرير المدفوعات حسب الموظف',
                    'sales.reports.payment_methods' => 'تقرير طرق الدفع',
                    'sales.reports.unified_customer' => 'تقرير العميل الموحّد',
                    'sales.reports.periods' => 'تقارير الفترات (مبيعات ومدفوعات)',
                    'sales.reports.tax' => 'التقرير الضريبي للمبيعات',
                    'sales.reports.export' => 'تصدير التقارير',
                ],
            ],

            /*
             * أهداف المندوبين — آخرُ ما بقي من النظام القديم في هذا الكتالوج.
             *
             * كانت `sales_targets_add`/`sales_targets_settle` بنمط `job_roles`
             * ومجموعتُهما موسومةً «(النظام القديم)»، وكذلك بوابةُ التقارير
             * `sales_view_reports`. صارت الثلاثة بالنمط النقطي
             * ([الترحيل 2026_09_12_220000])، فلم يبقَ في المبيعات اسمٌ مسطَّح.
             *
             * لاحظ الفرق عن `collections_targets` أعلاه: تلك أهدافُ تحصيلٍ
             * على الدفعات، وهذه أهدافُ بيعٍ على الفواتير — شاشتان ومتحكّمان.
             */
            'targets' => [
                'label' => 'أهداف المبيعات',
                'description' => 'أهداف المندوبين وتسويتها.',
                'icon' => 'target',
                'controller' => 'Sales/SalesTargetController',
                'permissions' => [
                    'sales.targets.manage' => 'إدارة أهداف المبيعات (إضافة وتعديل وحذف)',
                    'sales.targets.settle' => 'تسوية أهداف المبيعات',
                ],
            ],

        ];
    }

    /**
     * أسماء صلاحيات النطاق.
     *
     * النطاقات عابرةٌ للوحدات لا خاصةً بالمبيعات، فانتقل تعريفها إلى
     * [DataScopePermissions] لتظهر قسماً مستقلاً في شاشة الأدوار. هذه
     * الدالة وأختها تُبقيان النداءات القائمة عاملةً بالإحالة إليه.
     */
    public static function scopes(): array
    {
        return DataScopePermissions::all();
    }

    /** العمليات والنطاقات معاً — ما يجب أن يوجد في جدول `permissions`. */
    public static function allWithScopes(): array
    {
        return array_merge(self::all(), self::scopes());
    }
}
