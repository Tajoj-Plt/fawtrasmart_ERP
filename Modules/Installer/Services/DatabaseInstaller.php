<?php

namespace Modules\Installer\Services;

use Database\Seeders\AccountingSettingsPermissionsSeeder;
use Database\Seeders\AccountingSettingsSeeder;
use Database\Seeders\AccountSeeder;
use Database\Seeders\AchievementsSeeder;
use Database\Seeders\ApiKeyPermissionsSeeder;
use Database\Seeders\ApplicationSettingsSeeder;
use Database\Seeders\BranchSettingsSeeder;
use Database\Seeders\BrandsSeeder;
use Database\Seeders\CitiesSeeder;
use Database\Seeders\ClientPermissionSeeder;
use Database\Seeders\ClientsTableSeeder;
use Database\Seeders\CountriesSeeder;
use Database\Seeders\CronJobsSeeder;
use Database\Seeders\CustomerTargetSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\EmployeesTableSeeder;
use Database\Seeders\GeneralClientSettingSeeder;
use Database\Seeders\IntegrationsPermissionsSeeder;
use Database\Seeders\JobRolesTableSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\NoteClassificationSeeder;
use Database\Seeders\NotificationSettingSeeder;
use Database\Seeders\OnlineStoreSettingsSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSourcesSeeder;
use Database\Seeders\PermissionTableSeeder;
use Database\Seeders\PointsExpirySettingsSeeder;
use Database\Seeders\PointsGeneralSettingSeeder;
use Database\Seeders\PointsNotificationTemplatesSeeder;
use Database\Seeders\PointsSettingsSeeder;
use Database\Seeders\PointsTiersSeeder;
use Database\Seeders\ReferralSettingsSeeder;
use Database\Seeders\RewardsSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SalesAccountsSeeder;
use Database\Seeders\SalesPermissionsSeeder;
use Database\Seeders\SerialSettingsSeeder;
use Database\Seeders\ShippingMethodsSeeder;
use Database\Seeders\SmsSettingSeeder;
use Database\Seeders\SmsTemplateSeeder;
use Database\Seeders\SprintSeeder;
use Database\Seeders\StoreBannerSeeder;
use Database\Seeders\StoreCouponsSeeder;
use Database\Seeders\StorePagesSeeder;
use Database\Seeders\SubscriptionPermissionsSeeder;
use Database\Seeders\SystemCurrenciesSeeder;
use Database\Seeders\SystemMaintenancePermissionsSeeder;
use Database\Seeders\TaskManagerGlobalSettingsSeeder;
use Database\Seeders\UnitsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * تشغيل الهجرات والبذور.
 *
 * البذور هنا **ليست** `DatabaseSeeder`. ذاك يخلط الأساسي بالتجريبي: يزرع
 * خمسة مستخدمين بكلمة مرور `123456` وعملاء وموظفين باسم أشخاص حقيقيين.
 * تشغيله على تركيبٍ إنتاجي يترك حسابات دخول مكشوفة وبياناتٍ لا تخصّ صاحب
 * النظام. فالقائمة الأساسية أدناه مقصورة على ما لا يعمل النظام بدونه،
 * والتجريبي خيارٌ صريح في المعالج.
 */
class DatabaseInstaller
{
    /**
     * البذور الأساسية بالترتيب — الصلاحيات أولاً لأن الأدوار تُبنى عليها،
     * ثم المرجعيات (لغات، عملات، دول)، ثم شجرة الحسابات وما يشتقّ منها.
     */
    public const CORE_SEEDERS = [
        // الصلاحيات والأدوار — الترتيب مُلزِم: الصلاحيات تُنشأ، ثم الأدوار
        // تُنشأ وتأخذ الأساس، ثم السيدرات المتخصّصة تمنح لأدوارٍ بالاسم.
        // بلا RolesSeeder هنا كانت الثلاثة التالية تجد `owner` مفقوداً
        // فتتخطّى منح صلاحياتها صامتةً
        PermissionTableSeeder::class,
        RolesSeeder::class,
        SalesPermissionsSeeder::class,
        SystemMaintenancePermissionsSeeder::class,
        ClientPermissionSeeder::class,
        ApiKeyPermissionsSeeder::class,
        SubscriptionPermissionsSeeder::class,
        IntegrationsPermissionsSeeder::class,

        // صلاحيات الفترات المحاسبية — غيابها كان يُسقط شاشات الإعدادات
        // بـ«There is no permission named settings.accounting_periods.view»:
        // قائمة التنقّل تسأل عن صلاحية غير موجودة، وspatie يرمي ولا يُرجع false
        AccountingSettingsPermissionsSeeder::class,

        PermissionSourcesSeeder::class,
        JobRolesTableSeeder::class,

        // المرجعيات
        LanguageSeeder::class,
        SystemCurrenciesSeeder::class,
        CountriesSeeder::class,
        CitiesSeeder::class,
        UnitsSeeder::class,
        NoteClassificationSeeder::class,

        // شجرة الحسابات وما يعتمد عليها
        AccountSeeder::class,
        SalesAccountsSeeder::class,
        PaymentMethodSeeder::class,

        // ربط الحسابات الافتراضية بالشجرة — يقرأ أكوادها فيجب أن يليها
        AccountingSettingsSeeder::class,

        // إعدادات النظام
        ApplicationSettingsSeeder::class,
        SerialSettingsSeeder::class,
        BranchSettingsSeeder::class,
        GeneralClientSettingSeeder::class,
        NotificationSettingSeeder::class,
        TaskManagerGlobalSettingsSeeder::class,

        // بوّابة الرسائل: صفٌّ بقيم آمنة (معطَّلة ووضع اختبار) بدل شاشة فارغة
        SmsSettingSeeder::class,

        // القوالب
        EmailTemplateSeeder::class,
        SmsTemplateSeeder::class,
        DocumentTemplateSeeder::class,

        // المهام المجدولة
        CronJobsSeeder::class,

        // نظام النقاط والمكافآت
        PointsGeneralSettingSeeder::class,
        PointsSettingsSeeder::class,
        PointsExpirySettingsSeeder::class,
        PointsTiersSeeder::class,
        PointsNotificationTemplatesSeeder::class,
        ReferralSettingsSeeder::class,
        AchievementsSeeder::class,
        RewardsSeeder::class,

        // المتجر الإلكتروني
        OnlineStoreSettingsSeeder::class,
        StorePagesSeeder::class,
        ShippingMethodsSeeder::class,
    ];

    /**
     * بيانات تجريبية — لا تُشغَّل إلا بطلبٍ صريح.
     *
     * `UserSeeder` مستبعد نهائياً حتى من هذه القائمة: مستخدموه بكلمة مرور
     * ثابتة معروفة، ومدير النظام يُنشأ من نموذج المعالج بكلمة مرور صاحبه.
     */
    public const DEMO_SEEDERS = [
        ClientsTableSeeder::class,
        EmployeesTableSeeder::class,
        BrandsSeeder::class,
        StoreCouponsSeeder::class,
        StoreBannerSeeder::class,
        SprintSeeder::class,
        CustomerTargetSeeder::class,
    ];

    /**
     * تشغيل الهجرات.
     *
     * @return array{ok:bool, output:string, message:string}
     */
    public function migrate(bool $fresh = false): array
    {
        try {
            $this->relaxLimits();

            Artisan::call($fresh ? 'migrate:fresh' : 'migrate', ['--force' => true]);

            return [
                'ok' => true,
                'output' => Artisan::output(),
                'message' => $fresh ? 'أُعيد بناء الجداول وتشغيل الهجرات.' : 'تم تشغيل الهجرات.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'output' => Artisan::output(),
                'message' => 'فشلت الهجرات: '.$e->getMessage(),
            ];
        }
    }

    /**
     * تشغيل قائمة بذور، صنفاً صنفاً.
     *
     * التشغيل الفردي مقصود: `db:seed` على قائمة يسقط كلّه بسقوط واحد ولا
     * يخبرك أيّها سقط. هنا يُسجَّل لكل بذرة مصيرها، ويكمل ما بعدها — بذرةٌ
     * اختيارية متعثّرة لا يجوز أن تُفشل التثبيت كلّه.
     *
     * @param  array<class-string>  $seeders
     * @return array{ok:bool, results:array, failed:array}
     */
    public function seed(array $seeders): array
    {
        $results = [];
        $failed = [];

        foreach ($seeders as $seeder) {
            $short = class_basename($seeder);

            // بذرة أُعيدت تسميتها أو حُذفت لا تُسقط التثبيت
            if (! class_exists($seeder)) {
                $results[] = ['seeder' => $short, 'ok' => false, 'skipped' => true, 'message' => 'الصنف غير موجود — تُخُطِّي'];

                continue;
            }

            try {
                $this->relaxLimits();

                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);

                $results[] = ['seeder' => $short, 'ok' => true, 'skipped' => false, 'message' => 'تم'];
            } catch (Throwable $e) {
                $results[] = ['seeder' => $short, 'ok' => false, 'skipped' => false, 'message' => $e->getMessage()];
                $failed[] = $short;
            }
        }

        return [
            'ok' => $failed === [],
            'results' => $results,
            'failed' => $failed,
        ];
    }

    /** هل القاعدة مهيّأة فعلاً؟ وجود `users` و`accounts` كافٍ للحكم. */
    public function isMigrated(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('accounts') && Schema::hasTable('migrations');
        } catch (Throwable $e) {
            return false;
        }
    }

    /** هل في القاعدة بياناتٌ يخشى على ضياعها عند migrate:fresh؟ */
    public function hasData(): bool
    {
        try {
            if (! $this->isMigrated()) {
                return false;
            }

            return DB::table('users')->exists() || DB::table('accounts')->exists();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * الهجرات والبذور أطول من مهلة PHP الافتراضية بكثير — 467 هجرة جذرية
     * وحدها. بلا هذا يسقط التثبيت في منتصفه تاركاً قاعدةً نصف مبنية.
     */
    protected function relaxLimits(): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
    }
}
