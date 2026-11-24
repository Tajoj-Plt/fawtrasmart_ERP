<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربطُ مكافآت الموظفين من «كتالوج المكافآت» بالرواتب وأرصدة الإجازات.
 *
 *   • `salary_items.reward_redemption_id` — سطرُ الاستحقاق المولَّد على قسيمة
 *     الموظف لمكافأةٍ نقدية معتمدة. **فريد**: طلبُ استبدالٍ واحد = سطرٌ واحد،
 *     فلا تُصرف المكافأةُ مرّتين ولو أُعيدت المزامنة.
 *   • `reward_redemptions.payroll_run_id` — الصرفةُ التي دُفعت فيها المكافأة؛
 *     فارغٌ = معتمدةٌ ولم تُصرف بعد.
 *   • `rewards.leave_type_id` — نوعُ الإجازة الذي تُضاف إليه أيامُ مكافأة «إجازة».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_items') && ! Schema::hasColumn('salary_items', 'reward_redemption_id')) {
            Schema::table('salary_items', function (Blueprint $table) {
                $table->unsignedBigInteger('reward_redemption_id')->nullable()->after('origin')
                    ->comment('طلب استبدال المكافأة الذي وُلِّد منه هذا السطر (origin = reward_redemption)');
                $table->unique('reward_redemption_id', 'salary_items_reward_redemption_unique');
            });
        }

        if (Schema::hasTable('reward_redemptions') && ! Schema::hasColumn('reward_redemptions', 'payroll_run_id')) {
            Schema::table('reward_redemptions', function (Blueprint $table) {
                $table->unsignedBigInteger('payroll_run_id')->nullable()->after('treasury_account_id')
                    ->comment('صرفة الراتب التي دُفعت فيها المكافأة النقدية للموظف');
                $table->index('payroll_run_id');
            });
        }

        if (Schema::hasTable('rewards') && ! Schema::hasColumn('rewards', 'leave_type_id')) {
            Schema::table('rewards', function (Blueprint $table) {
                $table->unsignedBigInteger('leave_type_id')->nullable()->after('reward_value')
                    ->comment('نوع الإجازة الذي تُضاف إليه أيام مكافأة «إجازة»');
            });
        }
    }

    public function down(): void
    {
        // الأعمدةُ تُتقاعَد ولا تُحذف — انظر اصطلاح «Retire, do not drop».
    }
};
