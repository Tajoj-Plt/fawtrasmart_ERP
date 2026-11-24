<?php

namespace App\Services\Maintenance;

/**
 * سجلّ ما يُسمح بتشغيله من شاشة «تحديث النظام».
 *
 * الشاشة لا تأخذ من المستخدم اسمَ أمرٍ ولا اسمَ سيدر — تعرض هذه القائمة
 * ويختار منها. وأي مفتاح يصل من الواجهة يُطابَق حرفياً على مفاتيح هذا
 * السجلّ قبل أن يقترب من `Artisan::call`. هذا هو حدّ الأمان كلّه، على
 * نمط `CronRegistry` في نظام المهام المجدولة.
 *
 * ما هو مستبعَد عمداً:
 * - `migrate:fresh` و`migrate:refresh` و`migrate:rollback` و`db:wipe` —
 *   تمحو البيانات. الترحيل من هذه الشاشة تقدُّمٌ إلى الأمام فقط.
 * - `db:seed` بلا `--class` — يشغّل `DatabaseSeeder` كاملاً، وفيه بذورُ
 *   بياناتٍ تجريبية (عملاء وموظّفون وسبرنتات) لا تُزرَع في نظام يعمل.
 * - كل سيدر يُدرِج بلا فحصِ وجود ويُكرّر صفوفه عند إعادة التشغيل، إلا ما
 *   وُسِم بـ`guard_table` أدناه.
 *
 * **`guard_table`**: سيدرٌ يُدرِج بـ`insert()` بلا فحص — تشغيله مرّتين
 * يُنتج نسختين من كل صف. يُعرَض في الشاشة، لكن زرَّه مقفلٌ ما دام الجدول
 * غير فارغ. فينفع في تركيبٍ جديد وحده، وهو الموضع الذي يُحتاج فيه.
 *
 * **`additive`**: السيدر **يُضيف الناقص ولا يكتب فوق صفٍّ قائم** — أو لا يكتب
 * فوقه إلا بياناتٍ وصفيةً تخصّ الكتالوج لا اختيارَ المستخدم (وصفُ صلاحية،
 * تصنيفُ قالب). وهذه وحدها ما يشغّله زرّ «التحديث الشامل».
 *
 * وقد فُحص كلُّ سيدرٍ وُسِم به على حدة: `firstOrCreate` أو إنشاءُ الناقص بعد
 * سؤال الجدول، و`givePermissionTo` لا `syncPermissions` (الأخير يسحب صلاحيات
 * الدور الأخرى). وكلُّ ما وُجِد فيه `updateOrCreate` أو `updateOrInsert` أو
 * `upsert` أو `truncate` على صفٍّ يملكه المستخدم تُرِك بلا هذا الوسم — يبقى
 * زرُّه اليدوي ولا يعمل ضمن الشامل. أمثلةُ ما استُبعد لهذا السبب:
 * `ApplicationSettingsSeeder` (يعيد تفعيل تطبيقٍ عطّلتَه)، و`UnitsSeeder`
 * و`BrandsSeeder` و`ShippingMethodsSeeder` (تكتب فوق أسماءٍ وأسعارٍ حرّرتها)،
 * و`AccountingSettingsSeeder` (يعيد ربط الحسابات المحاسبية)،
 * و`OnlineStoreSettingsSeeder` و`StoreCouponsSeeder` و`TaskManagerGlobalSettingsSeeder`.
 *
 * **`caution`**: السيدر يعمل بلا تكرار، لكنه يكتب فوق اختيارٍ للمستخدم
 * (قالبٌ افتراضي، إعدادُ عرضٍ في قالب). يُعرَض التحذير في الشاشة وفي نافذة
 * التأكيد قبل التشغيل.
 */
class SystemUpdateRegistry
{
    /**
     * أقسام العرض — ترتيبها هنا هو ترتيبها في الشاشة.
     *
     * @return array<string, array{label: string, icon: string, hint: string}>
     */
    public static function groups(): array
    {
        return [
            'documents' => [
                'label' => 'القوالب والمطبوعات',
                'icon' => 'file-text',
                'hint' => 'نصوص الفواتير والسندات المطبوعة وقوالب الرسائل.',
            ],
            'permissions' => [
                'label' => 'الصلاحيات والأدوار',
                'icon' => 'shield',
                'hint' => 'إنشاء الصلاحيات الناقصة وإسنادها إلى أدوارها. لا يسحب صلاحيةً من أحد.',
            ],
            'settings' => [
                'label' => 'الإعدادات الافتراضية',
                'icon' => 'sliders',
                'hint' => 'مفاتيح الإعدادات الجديدة التي أضافها التحديث بقيمها المبدئية.',
            ],
            'accounts' => [
                'label' => 'شجرة الحسابات',
                'icon' => 'book',
                'hint' => 'الحسابات القياسية التي أضافها التحديث. تُضاف الناقصةُ وحدها، ولا يُمسّ حسابٌ قائم ولا رصيدُه.',
            ],
            'reference' => [
                'label' => 'البيانات المرجعية',
                'icon' => 'list',
                'hint' => 'الدول والمدن والعملات واللغات ووحدات القياس.',
            ],
            'repair' => [
                'label' => 'إصلاح بيانات',
                'icon' => 'tool',
                'hint' => 'تصحيحُ صفوفٍ كتبتها نسخةٌ أقدم بشكلٍ خاطئ. لا تحذف شيئاً ولا تضيف صفوفاً.',
            ],
            'initial' => [
                'label' => 'بيانات أوّلية — لمرّة واحدة',
                'icon' => 'package',
                'hint' => 'تُزرَع في تركيبٍ جديد فقط. أزرارها تُقفَل تلقائياً متى امتلأ جدولها، لأن إعادة تشغيلها تُكرّر الصفوف لا تحدّثها.',
            ],
        ];
    }

    /**
     * المهام المسموح تشغيلها، بمفاتيحها.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function tasks(): array
    {
        return [
            // ═══════════ القوالب والمطبوعات ═══════════

            'document-templates-refresh' => [
                'label' => 'تحديث نصّ قوالب المستندات',
                'description' => 'يُنزل نصَّ القوالب الجاهزة المزروعة على المخزَّن في قاعدة البيانات. '
                    .'هذا هو الأمر الصحيح بعد أي تعديلٍ على تصميم فاتورة أو سند: لا يمسّ إلا '
                    .'`content` و`styles`، ويحفظ القالبَ الافتراضي وإعدادات العرض التي اختارها المستخدم.',
                'group' => 'documents',
                'command' => 'document-templates:refresh',
                'arguments' => [],
                'recommended' => true,
            ],
            'document-templates-barcode' => [
                'label' => 'تفعيل الباركود في المطبوعات',
                'description' => 'يُعيد مفتاح «إظهار الباركود» إلى قيمته في القوالب المزروعة. '
                    .'يُشغَّل مرّةً واحدة بعد تحديثٍ يُضيف الباركود: كتلة الباركود تصل مع نصّ '
                    .'القالب، لكنّ المفتاح في السجلّات القائمة يبقى مطفأً فلا تظهر. '
                    .'ولا يمسّ إلّا هذا المفتاح.',
                'group' => 'documents',
                'command' => 'document-templates:refresh',
                'arguments' => ['--sync-key' => ['show_barcode']],
            ],
            'document-templates-create-missing' => [
                'label' => 'استيراد القوالب الجديدة',
                'description' => 'يُنشئ القوالبَ المزروعة التي لا صفَّ لها في هذا النظام بعد — قالبٌ '
                    .'أُضيف في تحديثٍ جديد لا يظهر في شاشة «قوالب المستندات» حتى يُنشأ صفُّه. '
                    .'ولا يلمس قالباً موجوداً ولا يجعل الجديدَ افتراضياً: الافتراضيُّ اختيارُك. '
                    .'ولا يمسّ نصَّ قالبٍ قائم — لإنزال النصوص زرُّ «تحديث نصّ قوالب المستندات».',
                'group' => 'documents',
                'command' => 'document-templates:refresh',
                // `--skip-content` لازمة مع وسم `additive`: الأمر بلا هذه الراية
                // يُعيد نصَّ كل قالبٍ جاهزٍ يخالف المزروعة إلى نصّ المصنع، فيضيع
                // ما حرّره المستخدم في شاشة «قوالب المستندات» ضمن «التحديث الشامل»
                'arguments' => ['--create-missing' => true, '--skip-content' => true],
                'additive' => true,
                'recommended' => true,
            ],
            'document-templates-adopt-unified' => [
                'label' => 'اعتماد «التصميم الموحّد» لكل المستندات',
                'description' => 'يُنشئ قالبَي «التصميم الموحّد» (A4 وحراري 80 مم) لكلّ نوع مستند إن لم يُنشآ، '
                    .'ثمّ يجعل «A4 التصميم الموحّد» القالبَ الافتراضيَّ لكلّ نوع — فتُطبع الفواتير والسندات '
                    .'والعروض والأذون والكشوف كلُّها بالهوية نفسها. والقالبُ الحراري يبقى متاحاً في قائمة المطبوعات.',
                'group' => 'documents',
                'command' => 'document-templates:refresh',
                'arguments' => ['--create-missing' => true, '--skip-content' => true, '--adopt-unified' => true],
                'caution' => 'يُلغي القالبَ الافتراضيَّ الذي اخترتَه لكلّ نوع — ويبقى القديمُ في القائمة، '
                    .'وتستطيع إعادته افتراضياً من شاشة «قوالب المستندات».',
            ],
            'document-templates-adopt-hr' => [
                'label' => 'اعتماد «تصميم الموارد البشرية» لمستندات الرواتب والعقود',
                'description' => 'يُنشئ قوالب «تصميم الموارد البشرية» إن لم تُنشأ — عقد العمل، قسيمة الراتب (A4 وحراري)، '
                    .'كشف مسير الرواتب (A4 أفقي)، سند السلفة، سند صرف العمولة — ثمّ يجعلها الافتراضيَّ لهذه الأنواع الخمسة وحدها، '
                    .'فتُطبع بهويةٍ رسمية مستقلّة لا تشبه الفواتير. لا يمسّ أيَّ نوعٍ آخر، ولا نصَّ قالبٍ قائم.',
                'group' => 'documents',
                'command' => 'document-templates:refresh',
                'arguments' => ['--create-missing' => true, '--skip-content' => true, '--adopt-hr' => true],
                'caution' => 'يُلغي القالبَ الافتراضيَّ الجاهز الذي اخترتَه لهذه الأنواع الخمسة — إلّا قالباً صمّمته بنفسك فيبقى. '
                    .'والقديمُ يبقى في القائمة وتستطيع إعادته افتراضياً من شاشة «قوالب المستندات».',
            ],
            'document-templates-unscope-branch' => [
                'label' => 'إظهار القوالب لكل الفروع',
                'description' => 'القوالبُ الجاهزة عامّةٌ لا يملكها فرع، لكنّ زراعتَها من داخل النظام '
                    .'تختمها بفرع من زرعها — فتخلو شاشة «قوالب المستندات» عند كل من سواه، ويعود '
                    .'الطبعُ إلى القالب المدمج. هذا يردّها إلى العموم. ولا يمسّ القوالبَ التي '
                    .'صمّمها المستخدم: قد يكون قصَرها على فرعه عمداً.',
                'group' => 'documents',
                'command' => 'document-templates:refresh',
                // كسابقتها: بلا `--skip-content` يُعاد نصُّ القوالب المحرَّرة
                // إلى المصنع في زرٍّ اسمُه «إظهار القوالب لكل الفروع»
                'arguments' => ['--unscope-branch' => true, '--skip-content' => true],
                'additive' => true,
                'recommended' => true,
            ],
            'sales-document-email-templates' => [
                'label' => 'قوالب بريد مستندات المبيعات',
                'description' => 'ستّةُ قوالبِ رسائل — عرض سعر، أمر بيع، فاتورة، مرتجع، إشعار دائن، سند قبض '
                    .'— يستعملها زرّ «إرسال بالبريد» في شاشة كلّ مستند. بدونها يرفض النظامُ الإرسال '
                    .'ويقول إنّ القالب غير مفعّل، فلا تخرج رسالةٌ باسم المنشأة لم يكتبها أحد. '
                    .'تُزرع الناقصةَ وحدها ولا تُعيد كتابة نصٍّ حرّرتَه.',
                'group' => 'documents',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SalesDocumentEmailTemplateSeeder::class],
                'additive' => true,
                'recommended' => true,
            ],
            'reservations-email-template' => [
                'label' => 'قالبا بريد الحجز والموعد',
                'description' => 'نصُّ الرسالة التي يرسلها زرّ «إرسال بالبريد» في شاشة الحجز — الخدمة والموعد والموظّف، '
                    .'ونصُّ رسالة الموعد المُشارَك مع العميل. '
                    .'مع تأكيد الحجز مرفقاً PDF. بدونه يرفض النظامُ الإرسال ويقول إنّ القالب غير مفعّل. '
                    .'يُزرع إن لم يوجد ولا يُعيد كتابة نصٍّ حرّرتَه.',
                'group' => 'documents',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Modules\Client\Database\Seeders\ReservationEmailTemplateSeeder::class],
                'additive' => true,
                'recommended' => true,
            ],
            'reservations-appointments-sync' => [
                'label' => 'مواعيد الحجوزات السابقة',
                'description' => 'الحجزُ المؤكَّد صار يُكتب موعداً في شاشة «المواعيد». هذا يُنشئ مواعيدَ الحجوزات '
                    .'التي تأكّدت قبل ذلك، ويُحدّث القائمة منها. آمنُ الإعادة: لا يكرّر موعداً. '
                    .'شغّل «الترحيلات» قبله.',
                'group' => 'documents',
                'command' => 'reservations:sync-appointments',
                'arguments' => [],
                'additive' => true,
                'recommended' => true,
            ],
            'document-templates-repair' => [
                'label' => 'إصلاح قوالب المستندات',
                'description' => 'يفحص القوالب ويصلح ما أفسده المحرّر المرئي من رموز الدمج.',
                'group' => 'documents',
                'command' => 'document-templates:repair',
                'arguments' => [],
            ],
            'document-templates-seed' => [
                'label' => 'إعادة زراعة قوالب المستندات كاملةً',
                'description' => 'يُعيد كتابة صفوف القوالب الجاهزة من المزروعة — يُستعمل حين يُضاف قالبٌ '
                    .'جديد لا مجرّد تعديلِ نصّ قالبٍ قائم.',
                'group' => 'documents',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\DocumentTemplateSeeder::class],
                'caution' => 'يعيد `is_default` و`is_active` و`settings` إلى قيم المصنع — يضيع القالبُ '
                    .'الافتراضي الذي اختاره المستخدم لكل نوع مستند. للتعديلات النصّية استعمل '
                    .'«تحديث نصّ قوالب المستندات» أعلاه.',
            ],
            'thermal-template' => [
                'label' => 'قالب الإيصال الحراري 80 مم',
                'description' => 'يزرع أو يحدّث قالب فاتورة المبيعات الحرارية المبني على مطبوعة النظام.',
                'group' => 'documents',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SalesInvoiceThermalTemplateSeeder::class],
                'caution' => 'يجعل هذا القالب الافتراضيَّ لفواتير المبيعات ويُلغي افتراضيةَ غيره.',
            ],
            'sms-templates' => [
                'label' => 'قوالب الرسائل النصّية',
                'description' => 'ينشئ قوالب الرسائل الناقصة ويحدّث وصفَ الموجود منها. نصُّ الرسالة '
                    .'الذي حرّره المستخدم لا يُمَس.',
                'group' => 'documents',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SmsTemplateSeeder::class],
                'additive' => true,
            ],

            // ═══════════ الصلاحيات والأدوار ═══════════

            'permissions-catalog' => [
                'label' => 'مزامنة كتالوج الصلاحيات',
                'description' => 'ينشئ الصلاحيات الناقصة من كتالوجات الأقسام ويكتب تقسيمها وتسمياتها العربية.',
                'group' => 'permissions',
                'command' => 'permissions:sync-catalog',
                'arguments' => [],
                'additive' => true,
                'recommended' => true,
            ],
            'roles' => [
                'label' => 'الأدوار وصلاحياتها',
                'description' => 'ينشئ أدوار النظام الناقصة ويُسند إليها صلاحياتها.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\RolesSeeder::class],
                'additive' => true,
            ],
            'permissions-sales' => [
                'label' => 'صلاحيات المبيعات',
                'description' => 'صلاحيات فواتير المبيعات وعروض الأسعار والمرتجعات ونطاقات رؤيتها.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SalesPermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-purchases' => [
                'label' => 'صلاحيات المشتريات',
                'description' => 'صلاحيات أوامر الشراء وفواتير الموردين ومرتجعاتها.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PurchasesPermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-stock' => [
                'label' => 'صلاحيات المخزون',
                'description' => 'صلاحيات المنتجات والمستودعات وأذون الصرف والاستلام والجرد.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\StockPermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-users' => [
                'label' => 'صلاحيات إدارة المستخدمين',
                'description' => 'صلاحيات شاشات المستخدمين والأدوار والصلاحيات في الإعدادات.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SettingsUserManagementPermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-maintenance' => [
                'label' => 'صلاحيات الصيانة والنسخ الاحتياطي',
                'description' => 'صلاحيات المهام المجدولة والنسخ الاحتياطي وتحديث النظام. '
                    .'الخطيرة منها للمالك وحده.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SystemMaintenancePermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-accounting' => [
                'label' => 'صلاحيات الإعدادات المحاسبية',
                'description' => 'صلاحيات شاشة ربط العمليات بحساباتها من شجرة الحسابات.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\AccountingSettingsPermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-subscription' => [
                'label' => 'صلاحيات الاشتراك',
                'description' => 'صلاحيات شاشة الاشتراك وحدود الباقة.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SubscriptionPermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-integrations' => [
                'label' => 'صلاحيات الربط والاتصال',
                'description' => 'صلاحيات SMTP والرسائل النصّية والإشعارات.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\IntegrationsPermissionsSeeder::class],
                'additive' => true,
            ],
            'permissions-apikeys' => [
                'label' => 'صلاحيات مفاتيح الربط API',
                'description' => 'صلاحيات إنشاء مفاتيح الربط وتعطيلها.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\ApiKeyPermissionsSeeder::class],
                'additive' => true,
            ],
            'permission-sources' => [
                'label' => 'مصادر الصلاحيات',
                'description' => 'يحدّث جدول تصنيف الصلاحيات ومصادرها المستعمَل في شاشة الصلاحيات.',
                'group' => 'permissions',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PermissionSourcesSeeder::class],
                'additive' => true,
            ],

            // ═══════════ الإعدادات الافتراضية ═══════════

            'application-settings' => [
                'label' => 'مفاتيح تفعيل التطبيقات',
                'description' => 'يضيف صفوف التطبيقات الجديدة إلى شاشة «إدارة التطبيقات». '
                    .'التطبيق المعطَّل يدوياً يبقى معطَّلاً.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\ApplicationSettingsSeeder::class],
            ],
            'cron-jobs' => [
                'label' => 'المهام المجدولة',
                'description' => 'يضيف المهام المجدولة الجديدة ويصحّح تعريف القائم منها. '
                    .'المهمّة التي أطفأها المستخدم تبقى مطفأة.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\CronJobsSeeder::class],
                'additive' => true,
            ],
            // ═══════════ شجرة الحسابات ═══════════

            /*
                `AccountSeeder` نفسها لا تُدرَج هنا ولن تُدرَج: تبدأ بـ
                `Account::query()->delete()`، فزرُّها على قاعدةٍ حيّة يمحو شجرة
                المحاسب ويقطع كلّ قيدٍ معلَّقٍ بها. وهذه المهمّة تقرأ قائمتَها
                وتزرع الناقصَ وحده، فهي `additive` بحقّ: لا تكتب في صفٍّ قائم.
            */
            'accounts-missing-preview' => [
                'label' => 'معاينة الحسابات الناقصة',
                'description' => 'يعرض قائمة الحسابات القياسية الغائبة عن شجرتك — بأكوادها وأسمائها — '
                    .'ولا يكتب شيئاً. اضغطه قبل التنفيذ لترى ما سيُضاف.',
                'group' => 'accounts',
                'command' => 'accounts:seed-missing',
                'arguments' => ['--dry-run' => true],
                'readonly' => true,
            ],
            'accounts-missing' => [
                'label' => 'إضافة الحسابات الناقصة',
                'description' => 'يضيف إلى شجرة الحسابات ما استجدّ فيها من حسابات قياسية ولم يصل قاعدتك '
                    .'(يُطابَق بالكود، ويُوضع كلُّ حسابٍ تحت أبيه الصحيح). '
                    .'الحساب الموجود لا يُمسّ: لا اسمُه ولا كودُه ولا رصيدُه. ولا يُحذف شيء.',
                'group' => 'accounts',
                'command' => 'accounts:seed-missing',
                'additive' => true,
                'recommended' => true,
            ],

            'accounting-settings' => [
                'label' => 'الحسابات المحاسبية',
                'description' => 'يضيف مفاتيح ربط العمليات بحساباتها التي أضافها التحديث. '
                    .'الحساب الذي ربطه المستخدم لا يُبدَّل.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\AccountingSettingsSeeder::class],
            ],
            'branch-settings' => [
                'label' => 'إعدادات الفروع',
                'description' => 'مفاتيح إعدادات الفروع الافتراضية.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\BranchSettingsSeeder::class],
                'additive' => true,
            ],
            'serial-settings' => [
                'label' => 'إعدادات الترقيم المتسلسل',
                'description' => 'صيغ أرقام الفواتير والسندات لأنواع المستندات الجديدة.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SerialSettingsSeeder::class],
                'additive' => true,
            ],
            'notification-settings' => [
                'label' => 'إعدادات الإشعارات',
                'description' => 'قنوات الإشعارات ومفاتيحها الافتراضية.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\NotificationSettingSeeder::class],
            ],
            'task-settings' => [
                'label' => 'إعدادات إدارة المهام',
                'description' => 'الإعدادات العامّة لوحدة إدارة المهام.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\TaskManagerGlobalSettingsSeeder::class],
            ],
            'store-settings' => [
                'label' => 'إعدادات المتجر الإلكتروني',
                'description' => 'مفاتيح إعدادات المتجر الافتراضية.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\OnlineStoreSettingsSeeder::class],
            ],
            'points-general' => [
                'label' => 'الإعدادات العامّة للنقاط',
                'description' => 'مفاتيح نظام الولاء والنقاط العامّة.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PointsGeneralSettingSeeder::class],
                'additive' => true,
            ],
            'points-notification-templates' => [
                'label' => 'قوالب إشعارات النقاط',
                'description' => 'نصوص إشعارات كسب النقاط وصرفها وانتهائها.',
                'group' => 'settings',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PointsNotificationTemplatesSeeder::class],
            ],

            // ═══════════ البيانات المرجعية ═══════════

            'saudi-public-holidays' => [
                'label' => 'العطل الرسمية السعودية',
                'description' => 'قائمة «العطل الرسمية - السعودية»: يوم التأسيس واليوم الوطني (تتكرران كل سنة)، '
                    .'وعيدا الفطر والأضحى (أربعة أيام لكلٍّ) حسب تقويم أم القرى للسنوات القادمة. '
                    .'تُضاف العطلة الناقصة وحدها ولا يُعدَّل تاريخٌ عدّلتَه — يوم العيد المعلَن بالرؤية يُصحَّح من «قوائم العطل».',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SaudiPublicHolidaysSeeder::class],
                'additive' => true,
                'recommended' => true,
            ],

            'countries' => [
                'label' => 'الدول',
                'description' => 'قائمة الدول ورموزها.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\CountriesSeeder::class],
            ],
            'cities' => [
                'label' => 'المدن',
                'description' => 'قائمة المدن مربوطةً بدولها.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\CitiesSeeder::class],
            ],
            'currencies' => [
                'label' => 'عملات النظام',
                'description' => 'قائمة العملات ورموزها وكسورها. أسعارُ الصرف التي أدخلها المستخدم لا تُمَس.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SystemCurrenciesSeeder::class],
            ],
            'languages' => [
                'label' => 'لغات النظام',
                'description' => 'يضيف اللغات الناقصة. اللغة المفعَّلة والافتراضية وترتيبُها تبقى كما ضبطها المستخدم.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\LanguageSeeder::class],
            ],
            'units' => [
                'label' => 'وحدات القياس',
                'description' => 'وحدات القياس وقوالبها.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\UnitsSeeder::class],
            ],
            'brands' => [
                'label' => 'العلامات التجارية',
                'description' => 'العلامات التجارية المبدئية للمنتجات.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\BrandsSeeder::class],
            ],
            'note-classifications' => [
                'label' => 'تصنيفات الملاحظات',
                'description' => 'تصنيفات ملاحظات العملاء والزيارات.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\NoteClassificationSeeder::class],
            ],
            'shipping-methods' => [
                'label' => 'طرق الشحن',
                'description' => 'طرق الشحن وحساباتها المحاسبية.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\ShippingMethodsSeeder::class],
            ],
            'store-pages' => [
                'label' => 'صفحات المتجر',
                'description' => 'صفحات «من نحن» و«الشروط» وأخواتها في المتجر الإلكتروني.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\StorePagesSeeder::class],
            ],
            'store-coupons' => [
                'label' => 'كوبونات المتجر',
                'description' => 'الكوبونات التعريفية للمتجر الإلكتروني.',
                'group' => 'reference',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\StoreCouponsSeeder::class],
            ],

            // ═══════════ إصلاح بيانات ═══════════

            /*
                ليس `additive`: كتابةٌ على صفوفٍ قائمة، وإن كانت تصحيحاً — يبقى
                زرّاً يُضغَط عن قصد بعد قراءة وصفه.
            */
            'stock-permit-unit-prices' => [
                'label' => 'تصحيح أسعار الوحدة في الأذون المخزنية',
                'description' => 'كميةُ الإذن المخزني بالوحدة الأساسية دائماً، لكنّ أذوناً كتبتها نسخةٌ '
                    .'أقدم حفظت معها سعرَ الوحدة المشتراة — أربعةُ كراتين بـ١٩١٫٣٠ للكرتون تُكتب '
                    .'«٤٠٠٠ حبة بـ١٩١٫٣٠». فسعرُ الحبّة يظهر في شاشات المخزون ألفَ ضعفِ حقيقته. '
                    .'هذا يعيده إلى الإجمالي ÷ الكمية، والإجمالي في هذه الأسطر صحيح. لا يمسّ سطراً '
                    .'متّسقاً، ولا سطراً إجماليُّه أكبر من الكمية × السعر — فالكميةُ فيه هي المشكوك '
                    .'فيها ويحتاج نظراً بشرياً.',
                'group' => 'repair',
                'command' => 'stock:repair-permit-unit-prices',
                'arguments' => ['--fix' => true],
                'caution' => 'قيودُ تكلفة المبيعات المُرحَّلة قبل التصحيح تبقى بأرقامها — تصحيحُها يكون '
                    .'بإلغاء الفاتورة وإعادتها أو بقيدٍ يدويّ.',
            ],

            // ═══════════ بيانات أوّلية — لمرّة واحدة ═══════════
            // هذه تُدرِج بـ`insert()` بلا فحص وجود: تشغيلها مرّتين يُنتج
            // نسختين من كل صفّ. `guard_table` تُقفل الزرّ متى امتلأ الجدول.

            /*
                بيانات التأسيس — أوّل ما يُشغَّل على تركيبٍ جديد.

                لا `guard_table` له: هو وحده في هذه المجموعة سيدرٌ يسأل عن كل
                عنصرٍ قبل إنشائه، فقفلُ زرّه بجدولٍ واحد يمنع إكمالَ ما نقص
                (مستودعٌ بلا حساب، عميلٌ بلا حساب) بحجّة أن الفروع مزروعة.

                وليس `additive` رغم ذلك: «التحديث الشامل» يُشغَّل على أنظمة
                عاملة، وزرعُ فرعٍ وعميلٍ ومستودعٍ فيها ليس تحديثاً. يبقى زرّاً
                يُضغَط عن قصد.
            */
            'system-foundation' => [
                'label' => 'بيانات التأسيس — فرع وعميل ومستودع',
                'description' => 'يُنزل الحدّ الأدنى الذي لا يعمل النظام بدونه في تركيبٍ جديد: شجرة الحسابات '
                    .'(إن كان جدولها فارغاً)، وربط الحسابات المحاسبية، والفرع الرئيسي، والعميل الافتراضي '
                    .'بحسابه تحت «العملاء»، والمستودع الرئيسي بحسابه تحت «المخزون». '
                    .'يتخطّى كلَّ عنصرٍ موجود، فإعادة تشغيله آمنة.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\SystemFoundationSeeder::class],
                'recommended' => true,
            ],

            /*
                ليس `additive` عمداً: بياناتٌ تجريبية لا تُزرع ضمن «التحديث الشامل»
                في نظامٍ حيّ — يضغطها صاحبُها حين يريد أن يرى الوحدة تعمل.
                والبذرةُ آمنةُ الإعادة: كلُّ عنصرٍ يُفحص، والحجوزاتُ لا تتكرّر.
            */
            'reservations-demo' => [
                'label' => 'بيانات تجريبية للحجوزات',
                'description' => 'خمس خدمات (منتجات خدمية) ونحو ثلاثين حجزاً بكل الحالات على عملائك وموظفيك '
                    .'— موسومة «[بيانات تجريبية]». لا تُرسل إشعارات للعملاء، ولا تُنشئ قبضاً ولا قيوداً، '
                    .'ولا تعدّل ولا تحذف شيئاً قائماً. الدوام من ورديات الموارد البشرية.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Modules\Client\Database\Seeders\ReservationDemoSeeder::class],
            ],

            'payment-methods' => [
                'label' => 'طرق الدفع',
                'description' => 'طرق الدفع النقدية والإلكترونية المبدئية.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PaymentMethodSeeder::class],
                'guard_table' => 'payment_methods',
            ],
            'email-templates' => [
                'label' => 'قوالب البريد الإلكتروني',
                'description' => 'نصوص رسائل البريد المرسَلة تلقائياً.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\EmailTemplateSeeder::class],
                'guard_table' => 'email_templates',
            ],
            'client-permissions' => [
                'label' => 'صلاحيات بوّابة العملاء',
                'description' => 'ما يراه العميل في بوّابته.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\ClientPermissionSeeder::class],
                'guard_table' => 'client_permissions',
            ],
            'client-general-settings' => [
                'label' => 'إعدادات العملاء العامّة',
                'description' => 'حقول ملفّ العميل المعروضة.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\GeneralClientSettingSeeder::class],
                'guard_table' => 'general_client_settings',
            ],
            'client-module-permissions' => [
                'label' => 'صلاحيات تسليم الفواتير وعلاقات العملاء',
                'description' => 'يمنح صلاحيات شاشتَي تسليم الفواتير و CRM للأدوار التي تصل شاشة العملاء أصلاً — الشاشتان كانتا بلا صلاحية، فوضعُها عليهما دون ربط يُخفيهما عن الجميع.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\ClientModulePermissionsSeeder::class],
                'guard_table' => 'permissions',
            ],
            'points-settings' => [
                'label' => 'إعدادات كسب النقاط',
                'description' => 'قواعد احتساب نقاط العملاء والموظّفين.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PointsSettingsSeeder::class],
                'guard_table' => 'points_settings',
            ],
            'points-expiry' => [
                'label' => 'إعدادات انتهاء النقاط',
                'description' => 'مدّة صلاحية النقاط وقواعد إسقاطها.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PointsExpirySettingsSeeder::class],
                'guard_table' => 'points_expiry_settings',
            ],
            'points-tiers' => [
                'label' => 'مستويات الولاء',
                'description' => 'مستويات العملاء ومزاياها.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\PointsTiersSeeder::class],
                'guard_table' => 'points_tiers',
            ],
            'achievements' => [
                'label' => 'الإنجازات',
                'description' => 'إنجازات نظام الولاء وشروطها.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\AchievementsSeeder::class],
                'guard_table' => 'achievements',
            ],
            'rewards' => [
                'label' => 'المكافآت',
                'description' => 'المكافآت القابلة للاستبدال بالنقاط.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\RewardsSeeder::class],
                'guard_table' => 'rewards',
            ],
            'referral-settings' => [
                'label' => 'إعدادات الإحالة',
                'description' => 'قواعد دعوة الأصدقاء ومكافآتها.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\ReferralSettingsSeeder::class],
                'guard_table' => 'referral_settings',
            ],
            'store-banners' => [
                'label' => 'بانرات المتجر',
                'description' => 'بانرات الصفحة الرئيسية للمتجر الإلكتروني.',
                'group' => 'initial',
                'command' => 'db:seed',
                'arguments' => ['--class' => \Database\Seeders\StoreBannerSeeder::class],
                'guard_table' => 'store_banners',
            ],
        ];
    }

    /** المهمّة بمفتاحها، أو `null` لمفتاحٍ غير مسجَّل. */
    public static function find(string $key): ?array
    {
        $task = static::tasks()[$key] ?? null;

        return $task === null ? null : $task + ['key' => $key];
    }

    /** هل المفتاح مسجَّل؟ — الفحص الذي يسبق كل تشغيل. */
    public static function has(string $key): bool
    {
        return isset(static::tasks()[$key]);
    }
}
