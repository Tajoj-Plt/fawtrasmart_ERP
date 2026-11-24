<?php

namespace Tests\Feature;

use App\Constants\SystemAccountIds;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * مصروفُ الراتب يُرحَّل إلى حسابه لا إلى جذر مجموعته.
 *
 * **ما كان**
 *
 * هجرةُ `add_salary_expense_account_to_payroll_settings` تختار القيمة
 * الافتراضية بالبحث في جدول الحسابات:
 *
 *     $default = accounts->find(3079)?->id
 *         ?? accounts->where('parent_id', 3041)->first()?->id
 *         ?? 3041;
 *
 * والهجراتُ تسبق السيدرات، فالجدولُ **فارغٌ** لحظةَ تنفيذها: يسقط الشرطان
 * الأوّلان ويقع الاختيار على `3041` المكتوب نصّاً — «الرواتب والأجور»،
 * جذرُ المجموعة الذي تحته عشرةُ أبناء: الأجورُ والبدلاتُ والحوافزُ
 * والتأميناتُ والتأمينُ الطبي ومكافأةُ نهاية الخدمة والعمولاتُ والخصميات.
 * ويتكرّر ذلك في كلِّ تركيبٍ جديد.
 *
 * فيُخلَط سطرُ الراتب بها جميعاً، ويبقى «رواتب الموظفين» (5301) — موضعُه من
 * الشجرة — بصفر. والأرقامُ تظلّ صحيحةً في الجذر لأنّه يجمع قيودَه المباشرة
 * وأرصدةَ أبنائه، لكنّ التصنيفَ خطأ.
 *
 * وقد ظهر هذا في أوّل صرفةٍ حقيقية على شجرةٍ مزروعة، لا في اختبار.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الهجرةَ تُفرِّغ القيمةَ المعطوبة فيسري الاحتياطُ الموثَّق في الشاشة،
 * وأنّ الترحيلَ يرفض الجذرَ إن أُعيد اختيارُه من القائمة — وأنّ الرفضَ
 * مقصورٌ على الجذر: حسابُ «رواتب الموظفين» نفسُه له ابنٌ في الشجرة
 * المزروعة، فمنعُ كلِّ ذي أبناءٍ يرفض افتراضَ النظام نفسِه.
 */
class PayrollExpenseAccountPlacementTest extends TestCase
{
    private function accountantCode(): string
    {
        $source = (string) file_get_contents(app_path('Services/PayrollAccountant.php'));
        $code = preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#//.*$#m', '', (string) $code);
    }

    /** الجذرُ والحسابُ ليسا واحداً — وهذا أصلُ العطب. */
    public function test_the_group_root_is_not_the_salary_expense_account(): void
    {
        $this->assertNotSame(
            SystemAccountIds::SALARIES_WAGES_PARENT,
            SystemAccountIds::EMPLOYEES_SALARIES_EXPENSE,
            'الجذرُ 3041 غير حساب الرواتب 3079'
        );
    }

    /** الترحيلُ يرفض جذرَ المجموعة ويدلّ على البديل. */
    public function test_posting_to_the_group_root_is_refused(): void
    {
        $code = $this->accountantCode();

        $this->assertMatchesRegularExpression(
            '/\(int\) \$salaryExpenseAccountId === SystemAccountIds::SALARIES_WAGES_PARENT\)\s*\{\s*throw/',
            $code,
            'جذرُ مجموعة الرواتب لا يصلح حساباً للمصروف'
        );

        $source = (string) file_get_contents(app_path('Services/PayrollAccountant.php'));
        $this->assertStringContainsString('رواتب الموظفين', $source, 'الرسالةُ تسمّي البديل');
        $this->assertStringContainsString('حسابات الرواتب', $source, 'وتدلّ على شاشته');
    }

    /**
     * والرفضُ مقصورٌ على الجذر لا على كلِّ ذي أبناء: «رواتب الموظفين» له
     * ابنٌ في الشجرة المزروعة، فقاعدةُ «الطرفي فقط» ترفض الافتراض نفسَه.
     */
    public function test_the_refusal_is_not_a_blanket_leaf_rule(): void
    {
        $code = $this->accountantCode();

        $this->assertStringNotContainsString(
            "Account::where('parent_id', \$salaryExpenseAccountId)->exists()",
            $code,
            'قاعدةُ «الطرفي فقط» ترفض حساب الرواتب نفسَه — لأنّ تحته «راتب» (53011)'
        );
    }

    /** والاحتياطُ حين يُترك الإعدادُ فارغاً هو حسابُ الرواتب. */
    public function test_the_fallback_is_the_salary_account(): void
    {
        $this->assertStringContainsString(
            "PayrollSettings::value('salary_expense_account_id')\n            ?? SystemAccountIds::EMPLOYEES_SALARIES_EXPENSE",
            (string) file_get_contents(app_path('Services/PayrollAccountant.php'))
        );
    }

    /** والهجرةُ تُفرِّغ القيمةَ المعطوبة فيسري ذلك الاحتياط. */
    public function test_the_migration_clears_the_broken_default(): void
    {
        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_expense_account_id')->nullable();
            $t->timestamps();
        });

        DB::table('payroll_settings')->insert([
            ['salary_expense_account_id' => SystemAccountIds::SALARIES_WAGES_PARENT],
        ]);

        $migration = require base_path(
            'database/migrations/2026_09_03_000400_reset_payroll_expense_account_off_the_group_root.php'
        );
        $migration->up();

        $this->assertNull(
            DB::table('payroll_settings')->value('salary_expense_account_id'),
            'الجذرُ يُفرَّغ ليسري الاحتياط'
        );
    }

    /** ولا تمسّ اختياراً صحيحاً. */
    public function test_the_migration_leaves_a_valid_choice_alone(): void
    {
        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_expense_account_id')->nullable();
            $t->timestamps();
        });

        DB::table('payroll_settings')->insert([
            ['salary_expense_account_id' => SystemAccountIds::EMPLOYEES_SALARIES_EXPENSE],
        ]);

        $migration = require base_path(
            'database/migrations/2026_09_03_000400_reset_payroll_expense_account_off_the_group_root.php'
        );
        $migration->up();

        $this->assertSame(
            SystemAccountIds::EMPLOYEES_SALARIES_EXPENSE,
            (int) DB::table('payroll_settings')->value('salary_expense_account_id')
        );
    }
}
