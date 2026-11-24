<?php

namespace Tests\Feature;

use App\Models\Log as ModelsLog;
use App\Models\SalarySlip;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * أربعُ ثغراتٍ بقيت مفتوحةً بعد جولات الإصلاح السابقة.
 *
 * **و٧ — خصمُ الغياب على أجرٍ غيرِ الذي يُصرف**
 *
 * `AttendanceProcessorService` كان يقرأ `contracts.basic_salary`، والراتبُ
 * يُدفع من `salary_slips.basic_amount`. والعمودان مستقلّان: تُعدَّل
 * القسيمةُ بزيادةٍ ولا يُعدَّل العقد، فيُخصَم يومُ الغياب على الأجر القديم
 * بينما يُصرف الراتبُ على الجديد.
 *
 * **و١١ — سنداتٌ وقيودٌ بصفر**
 *
 * راتبٌ استهلكته الخصمياتُ والسلفُ كلَّه صافيه صفر، ولا نقدَ يخرج. وكان
 * يُنشأ له سندُ صرفٍ بـ`0.00` يحرق رقماً من تسلسل المستندات، وسطرُ خزينةٍ
 * بصفرٍ لا يغيّر رصيداً.
 *
 * **و٩ — القسيمة بلا مُنشئ**
 *
 * `salary_slips` يحمل `approved_by` ولا يحمل `created_by`. فلا يستطيع
 * النظام أن يشترط أن يكون معتمِدُ الأجر غيرَ من حدّده.
 *
 * **و١٠ — أثرُ التدقيق قابلٌ للحذف**
 *
 * شاشةُ سجل النشاطات تحذف أيَّ سطر، و«تنظيفُ ما مضى عليه ثلاثون يوماً»
 * يمسح كلَّ ما قبل شهرٍ بلا استثناء — فيذهب سردُ من صرف راتباً ومن أيِّ
 * خزينة، وهو الأثرُ الوحيد للصرف التلقائي.
 */
class PayrollRemainingGapsTest extends TestCase
{
    // ═══════════ و٧ ═══════════

    /** قاعدةُ خصم الغياب صارت من القسيمة لا من العقد. */
    public function test_the_absence_deduction_measures_against_the_slip(): void
    {
        $source = (string) file_get_contents(app_path('Services/AttendanceProcessorService.php'));
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        $this->assertStringContainsString(
            'dailyDeductionWageBase($day)',
            $code,
            'الأجرُ المقيس عليه يُحلّ في موضعٍ واحد'
        );
        $this->assertStringContainsString(
            'SalarySlip::query()',
            $code,
            'ويُقرأ من القسيمة'
        );
        $this->assertStringNotContainsString(
            "\$basicSalary = (float) (\$contract?->basic_salary ?? 0);\n            \$amount = \$basicSalary / 30;",
            $code,
            'لم يعد يُقرأ من العقد مباشرةً في موضع الحساب'
        );
    }

    /** والعقدُ يبقى احتياطاً فلا يسقط الخصمُ إلى صفر لغياب قسيمة. */
    public function test_the_contract_remains_the_fallback(): void
    {
        $source = (string) file_get_contents(app_path('Services/AttendanceProcessorService.php'));

        $at = strpos($source, 'private function dailyDeductionWageBase');
        $this->assertNotFalse($at);

        $body = substr($source, $at, 1600);

        $this->assertStringContainsString('basic_amount', $body, 'القسيمةُ أوّلاً');
        $this->assertStringContainsString('basic_salary', $body, 'ثمّ العقد');
    }

    // ═══════════ و١١ ═══════════

    /** لا سندَ صرفٍ ولا سطرَ خزينةٍ لصرفةٍ صافيها صفر. */
    public function test_no_zero_value_voucher_or_treasury_line(): void
    {
        $source = (string) file_get_contents(app_path('Services/PayrollAccountant.php'));

        $this->assertStringContainsString(
            '$expense = $calc->netSalary <= 0.009 ? null : Expense::create(',
            $source,
            'السندُ مشروطٌ بخروج نقدٍ فعلي'
        );
        $this->assertStringContainsString(
            'if ($calc->netSalary > 0.009) {',
            $source,
            'وسطرُ الخزينة كذلك'
        );
    }

    /** ومرجعُ قيد الصرف يبقى فارغاً بلا سند — لا يستعير معرّفاً. */
    public function test_the_payment_entry_reference_is_null_without_a_voucher(): void
    {
        $source = (string) file_get_contents(app_path('Services/PayrollAccountant.php'));

        $this->assertStringContainsString("'reference_id' => \$expense?->id,", $source);
        $this->assertStringContainsString(
            "'expense_id' => \$posted['expense']?->id,",
            (string) file_get_contents(app_path('Services/PayrollDisbursementService.php')),
            'والمنادي يتحمّل غيابَ السند'
        );
    }

    // ═══════════ و٩ ═══════════

    /** القسيمةُ تعرف مَن أنشأها، ويُقاس عليه فصلُ المهام. */
    public function test_the_slip_knows_its_creator(): void
    {
        $slip = new SalarySlip(['created_by' => 7]);

        $this->assertTrue($slip->wasApprovedByItsCreator(7), 'المُنشئُ نفسُه');
        $this->assertFalse($slip->wasApprovedByItsCreator(9), 'مستخدمٌ آخر');
        $this->assertFalse($slip->wasApprovedByItsCreator(null), 'مجهولٌ لا يُبنى عليه منع');

        $unknown = new SalarySlip(['created_by' => null]);
        $this->assertFalse($unknown->wasApprovedByItsCreator(7), 'وقسيمةٌ بلا مُنشئٍ كذلك');
    }

    /** والاشتراطُ مفتاحٌ افتراضُه الإطفاء — منشأةٌ بمسؤولٍ واحد لا تستوفيه. */
    public function test_maker_checker_is_opt_in(): void
    {
        $controller = (string) file_get_contents(
            base_path('Modules/HR/Http/Controllers/Salaries/SalarySlipController.php')
        );

        $this->assertStringContainsString("PayrollSettings::value('require_slip_maker_checker') ?? false", $controller);
        $this->assertStringContainsString('wasApprovedByItsCreator(Auth::id())', $controller);
        $this->assertStringContainsString("'created_by' => Auth::id()", $controller, 'ويُسجَّل عند الإنشاء');
    }

    // ═══════════ و١٠ ═══════════

    /** أثرُ الحركات المالية محميٌّ من الحذف. */
    public function test_the_financial_trail_is_protected(): void
    {
        Schema::create('logs', function (Blueprint $t) {
            $t->id();
            $t->string('type')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->string('type_log')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        DB::table('logs')->insert([
            ['type' => 'payroll_run_log', 'type_log' => 'log', 'description' => 'صرف راتب'],
            ['type' => 'client', 'type_log' => 'log', 'description' => 'تعديل عميل'],
        ]);

        $this->assertSame(1, ModelsLog::protectedRecords()->count());
        $this->assertSame(1, ModelsLog::deletable()->count());

        ModelsLog::deletable()->delete();

        $this->assertSame(1, ModelsLog::count(), 'يبقى الأثرُ المالي');
        $this->assertTrue(ModelsLog::first()->isProtected());
    }

    /** والشاشةُ تلتزم ذلك في مساراتها الثلاثة. */
    public function test_all_three_deletion_paths_honour_it(): void
    {
        $controller = (string) file_get_contents(
            base_path('Modules/Sitting/Http/Controllers/ActivityLogController.php')
        );

        foreach (['destroy', 'bulkDelete', 'cleanup'] as $method) {
            $at = strpos($controller, "public function {$method}(");
            $this->assertNotFalse($at, "{$method}() مفقودة");

            $next = strpos($controller, "\n    public function ", $at + 1);
            $body = $next === false ? substr($controller, $at) : substr($controller, $at, $next - $at);

            $this->assertMatchesRegularExpression(
                '/deletable\(\)|isProtected\(\)/',
                $body,
                "{$method}() يجب أن تستثني الأثر المالي"
            );
        }
    }
}
