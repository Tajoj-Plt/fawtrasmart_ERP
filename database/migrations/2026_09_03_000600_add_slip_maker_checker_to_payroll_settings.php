<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اشتراطُ فصل المهام على قسيمة الراتب.
 *
 * أن يعتمد الأجرَ مَن حدّده هو أوّلُ ما يسأل عنه المدقّق في دورةٍ تُخرج
 * نقداً. ولم يكن النظام يستطيع منعَه أصلاً: `salary_slips` بلا عمود
 * `created_by` — انظر الهجرة `add_created_by_to_salary_slips_table`.
 *
 * **والافتراضُ إطفاءٌ، بخلاف اشتراط اعتماد الصرف.** والفرقُ أنّ منشأةً
 * بمسؤولٍ واحد لا تستطيع أن تُرضي هذا الشرط بحال: من ينشئ القسيمة هو من
 * يعتمدها ولا ثالثَ لهما. فتفعيلُه افتراضاً يوقف عملاً مشروعاً لا يمنع
 * خطأً — وضابطٌ يوقف الصحيح يُطفأ فيسقط معه ما كان يحرسه.
 *
 * أمّا اعتمادُ الصرف فيمكن لمسؤولٍ واحد أن يستوفيه: يُنشئ المسير ويعتمده،
 * فيبقى للدفعة تصريحٌ مؤرَّخٌ منسوبٌ إليه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_settings')) {
            return;
        }

        if (! Schema::hasColumn('payroll_settings', 'require_slip_maker_checker')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_settings', 'require_slip_maker_checker')) {
                    $table->boolean('require_slip_maker_checker')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_settings') && Schema::hasColumn('payroll_settings', 'require_slip_maker_checker')) {
            Schema::table('payroll_settings', function (Blueprint $table) {
                $table->dropColumn('require_slip_maker_checker');
            });
        }
    }
};
