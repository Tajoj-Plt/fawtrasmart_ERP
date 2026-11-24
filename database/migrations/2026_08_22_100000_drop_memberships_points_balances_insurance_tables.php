<?php

use App\Support\SchemaRetire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط جداول الأقسام المحذوفة: العضويات، النقاط والأرصدة، نقاط الولاء، وكلاء التأمين.
 *
 * لا يمسّ هذا الملف جداول «إيكو بوينت» (points_*, tier_upgrades_log, rewards,
 * achievements, challenges …) فهي تخصّ وحدة Ecosystem وتبقى كما هي.
 *
 * الترتيب مهمّ: الجداول التابعة تُسقَط قبل الجداول التي تشير إليها.
 */
return new class extends Migration
{
    /** الجداول المُسقَطة مرتَّبة من التابع إلى المتبوع. */
    private array $tables = [
        // النقاط والأرصدة
        'balance_consumptions',
        'balance_charges',
        'balance_type_package',
        'balance_types',
        // نقاط الولاء (المستقلّة عن إيكو بوينت)
        'client_loyalty_rule',
        'loyalty_rules',
        'loyalty_sittings',
        // العضويات والاشتراكات في الباقات
        'subscriptions',
        'memberships',
        'memberships_setthings',
        'packages',
        // وكلاء التأمين
        'insurance_agent_categories',
        'insurance_agents',
    ];

    /** أعمدة الصلاحيات المُزالة من جدول job_roles. */
    private array $permissionColumns = [
        'customer_loyalty_points_managing_customer_bases',
        'customer_loyalty_points_redeem_loyalty_points',
        'management_of_insurance_agents',
        'points_credits_packages_manage',
        'points_credits_credit_recharge_manage',
        'points_credits_credit_usage_manage',
        'points_credits_credit_settings_manage',
        'membership_management',
        'membership_setting_management',
        'membership_subscription_management',
        'membership_view_subscriptions',
        'clients_points_balances_report',
        'insurance_agents_view',
        'insurance_agents_create',
    ];

    /** مفاتيح التطبيقات المُزالة من جدول application_settings. */
    private array $applicationKeys = [
        'insurance',
        'customer_loyalty_points',
        'points_balances',
        'membership',
    ];

    public function up(): void
    {
        /* تقاعدٌ لا حذف: الفارغُ يُسقَط، وما فيه صفوفٌ يُعاد تسميته إلى
           `retired_*` فتبقى بياناتُه. كان هنا `dropIfExists` صريحاً — وهو
           على قاعدة عميلٍ يعمل بها منذ سنة محوٌ لآلاف الصفوف، ويوقف شاشةَ
           التحديث عند هذا الملفّ فلا ينزل ما بعده. */
        Schema::disableForeignKeyConstraints();

        SchemaRetire::tables($this->tables);

        Schema::enableForeignKeyConstraints();

        /* أعمدةُ الصلاحيات في `job_roles` تُترك حيث هي.
           كان هنا `dropColumn($existing)`. وعمودٌ لم يعد يُقرأ لا يكلّف
           شيئاً، بينما حذفُه يمحو ما فيه ولا يُستعاد. والكودُ الذي كان
           يقرؤها زال، وهو المقصود. */

        // إزالة صفوف الصلاحيات من Spatie
        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')
                ->whereIn('name', $this->permissionColumns)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                foreach (['role_has_permissions', 'model_has_permissions'] as $pivot) {
                    if (Schema::hasTable($pivot)) {
                        DB::table($pivot)->whereIn('permission_id', $ids)->delete();
                    }
                }

                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }

        // إزالة مفاتيح التطبيقات المعطَّلة
        if (Schema::hasTable('application_settings')) {
            DB::table('application_settings')->whereIn('key', $this->applicationKeys)->delete();
        }
    }

    /**
     * لا تراجُع: الجداول والبيانات محذوفة نهائياً بقرار من التقرير،
     * وإعادة إنشاء هياكل فارغة لن تُعيد البيانات.
     */
    public function down(): void
    {
        // متعمَّد: لا شيء.
    }
};
