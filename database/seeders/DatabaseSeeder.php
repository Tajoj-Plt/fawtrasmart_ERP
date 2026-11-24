<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionTableSeeder::class,
            // بعد الصلاحيات وقبل أي سيدر يمنحها لدورٍ بالاسم. كانت الأدوار
            // تُنشأ داخل UserSeeder، فمن استبعد المستخدمين التجريبيين خسر
            // أدوار النظام معهم
            RolesSeeder::class,
            // صلاحيات المبيعات النمطية ومنحُها — بعد الأدوار لأنها تمنح لها
            SalesPermissionsSeeder::class,

            /* صلاحيات المخزون تُمنح كما تُمنح صلاحيات المبيعات: الكتالوج
               يُنشئ صفوفها و[PermissionTableSeeder] يزرعها، ولم تكن تُمنح
               لأحد — فوحدة المخزون تردّ ٤٠٣ لكل مستخدم في النظام. */
            StockPermissionsSeeder::class,

            // صلاحيات شاشات إدارة المستخدمين/الأدوار/الصلاحيات نفسها
            SettingsUserManagementPermissionsSeeder::class,

            // صلاحيات وحدة المشتريات — نفس نمط StockPermissionsSeeder أعلاه
            PurchasesPermissionsSeeder::class,

            /* صلاحيات إدارة المهام — ونفس النمط كذلك. الوحدة كانت بلا
               حراسةٍ أصلاً، فلمّا حُرست رُدّ الجميع حتى يمنحها هذا السيدر. */
            TaskManagerPermissionsSeeder::class,
            LanguageSeeder::class,
            UserSeeder::class,
            ApplicationSettingsSeeder::class,
            PaymentMethodSeeder::class,
            AccountSeeder::class,
            ClientsTableSeeder::class,
            DefaultClientSeeder::class,
            // العميل النقدي — يقابل حساب «عميل نقدي» في شجرة الحسابات.
            // بعد AccountSeeder ليجد الحساب فيربطه به، لا لينشئ ثانياً.
            CashClientSeeder::class,
            JobRolesTableSeeder::class,
            EmployeesTableSeeder::class,
            // الفرع الرئيسي والمستودع الرئيسي — تنصيبٌ جديد بلا أيّهما يعطّل
            // شاشات العملاء والموظفين والمخزون التي تفترض وجود واحد منهما
            MainBranchSeeder::class,
            MainStoreHouseSeeder::class,
            BranchSettingsSeeder::class,
            ClientPermissionSeeder::class,
            GeneralClientSettingSeeder::class,
            SalesAccountsSeeder::class,
            SerialSettingsSeeder::class,
            SprintSeeder::class,
            EmailTemplateSeeder::class,
            // العطل الرسمية السعودية — قائمةٌ جاهزة تُسند للموظفين من «قوائم العطل»
            SaudiPublicHolidaysSeeder::class,
            // قالبا بريد الحجز والموعد — كانا يُزرعان من زرّ «تحديث النظام»
            // وحدَه، فالتثبيتُ الجديد يُرسل بريدَ الحجز فيفشل بـ«لا قالب»
            \Modules\Client\Database\Seeders\ReservationEmailTemplateSeeder::class,
            SalesDocumentEmailTemplateSeeder::class,
            CountriesSeeder::class,
            CitiesSeeder::class,
            // كانت مكتوبة ولم تُسجَّل هنا، فبقي `system_currencies` فارغاً
            // وشاشة العملات بلا بيانات رغم وجود بذرتها
            SystemCurrenciesSeeder::class,

            // الإعدادات المحاسبية: صلاحيات الشاشتين، ثم ملء جدول ربط
            // الحسابات من ثوابت SystemAccountIds. ترتيبه بعد `AccountSeeder`
            // مقصود — يقرأ أكواد الحسابات، فلا يجد شيئاً إن سبق الشجرة
            AccountingSettingsPermissionsSeeder::class,
            AccountingSettingsSeeder::class,

            // المهام المجدولة والنسخ الاحتياطي
            /* قوالب المستندات. كانت البذرة مكتوبةً ولم تُسجَّل هنا، فيبقى
               `document_templates` فارغاً — وشاشاتُ الطباعة في الفواتير
               والإشعارات الدائنة وعروض الأسعار والسندات والمرتجعات تفتح
               بلا قالبٍ واحد تختار منه. */
            DocumentTemplateSeeder::class,

            // بعد القوالب الجاهزة: يجعل الإيصال الحراري الافتراضي ويُنزل
            // العلَم عن غيره، فلا يبقى افتراضيّان لنوعٍ واحد
            SalesInvoiceThermalTemplateSeeder::class,

            /* مصادر أذون المخزون. كانت البذرة مكتوبةً ولم تُسجَّل هنا،
               فينصَّب النظام بجدولٍ شبه فارغ — و`InvoicesController` يرمي
               «مصدر إذن 'فاتورة مبيعات' غير موجود» عند أوّل حفظ. أي أنّ
               تنصيباً جديداً لا يُصدر فاتورةً واحدة: لا مبيعات ولا مرتجعاً
               ولا إشعاراً دائناً ولا مشتريات. */
            PermissionSourcesSeeder::class,

            SystemMaintenancePermissionsSeeder::class,

            /* صلاحيتا شاشة الاشتراك. كانت البذرة مكتوبةً ولم تُسجَّل هنا،
               فتنصيبٌ جديد لا يُنشئهما إطلاقاً — و`EnsureSubscription`
               يردّ 402 على كل مسار إلّا شاشة الاشتراك، وهي نفسها محروسةٌ
               بـ`settings.subscription.view`. فالنتيجة نظامٌ مقفلٌ على
               نفسه: لا أحد يبلغ الشاشة التي يُربط منها الاشتراك. */
            SubscriptionPermissionsSeeder::class,
            CronJobsSeeder::class,
            NotificationSettingSeeder::class,

            // الرسائل النصية: صفّ الإعدادات أولاً ثم كتالوج القوالب.
            // كان `SmsTemplateSeeder` مكتوباً ولم يُسجَّل هنا، فينصَّب
            // النظام بلا قالب رسالة واحد
            SmsSettingSeeder::class,
            SmsTemplateSeeder::class,

            // Online Store Seeders - سيدرز المتجر الإلكتروني
            BrandsSeeder::class,
            OnlineStoreSettingsSeeder::class,
            StorePagesSeeder::class,
            ShippingMethodsSeeder::class,
            StoreCouponsSeeder::class,
        ]);
    }
}
