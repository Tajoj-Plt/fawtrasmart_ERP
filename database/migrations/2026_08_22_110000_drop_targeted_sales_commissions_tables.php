<?php

use App\Support\SchemaRetire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط جداول قسم «المبيعات المستهدفة والعمولات» — قواعد العمولة وعمولات
 * المبيعات وفترات المبيعات.
 *
 * لا يمسّ هذا الملف منظومة **أهداف المبيعات** ولا **أهداف التحصيلات**:
 * `sales_targets` و`collections_targets` وجداول مستويات عمولتهما
 * (`sales_target_commission_tiers`, `collections_target_commission_tiers`)
 * وتسوياتهما تبقى كما هي، وكذلك `target_settings` و`employee_targets`
 * و«العمولات الأسبوعية» في وحدة المبيعات.
 *
 * الترتيب مهمّ: الجداول التابعة تُسقَط قبل الجداول التي تشير إليها.
 */
return new class extends Migration
{
    /** الجداول المُسقَطة مرتَّبة من التابع إلى المتبوع. */
    private array $tables = [
        'commission_sales_period_employee',
        'sales_commissions',
        'commission__products',
        'commission_users',
        'sales_periods',
        'commissions',
    ];

    /** أعمدة الصلاحيات المُزالة من جدول job_roles. */
    private array $permissionColumns = [
        'targeted_sales_commissions_manage_commission_rules',
        'targeted_sales_commissions_view_all_sales_commissions',
        'targeted_sales_commissions_view_own_sales_commissions',
        'targeted_sales_commissions_manage_sales_periods',
    ];

    public function up(): void
    {
        /* تقاعدٌ لا حذف — انظر [\App\Support\SchemaRetire]: الجدول الفارغ
           يُسقَط، وما فيه صفوفٌ يُعاد تسميته إلى `retired_*` فتبقى بياناته
           ولا تقف شاشةُ التحديث عند هذا الملفّ. */
        Schema::disableForeignKeyConstraints();

        SchemaRetire::tables($this->tables);

        Schema::enableForeignKeyConstraints();

        /* أعمدةُ الصلاحيات في `job_roles` تُترك حيث هي: عمودٌ لم يعد يُقرأ
           لا يكلّف شيئاً، وحذفُه يمحو ما فيه بلا رجعة. */

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

        // إزالة مفتاح التطبيق المعطَّل
        if (Schema::hasTable('application_settings')) {
            DB::table('application_settings')->where('key', 'target_sales_commissions')->delete();
        }
    }

    /**
     * لا تراجُع: الجداول والبيانات محذوفة نهائياً بقرارٍ من المستخدم،
     * وإعادة إنشاء هياكل فارغة لن تُعيد البيانات.
     */
    public function down(): void
    {
        // متعمَّد: لا شيء.
    }
};
