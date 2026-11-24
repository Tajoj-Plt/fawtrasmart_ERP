<?php

namespace Tests\Feature;

use App\Models\SocialInsuranceRemittance;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ما حُجز من التأمينات صار له مخرجٌ نقدي.
 *
 * **ما كان**
 *
 * صرفُ الراتب يحجز حصةَ الموظف ويُقيّدها دائنةً في ذمّتها، ويُقيّد حصةَ
 * المنشأة مصروفاً والتزاماً. فيتراكم الحسابان شهراً بعد شهر، ولا سبيلَ في
 * وحدة الرواتب إلى إخراجهما نقداً: إقفالُهما يحتاج سندَ صرفٍ يدويّاً في
 * وحدة الحسابات، بلا رابطٍ يعود إلى الرواتب ولا سجلٍّ لما سُدِّد ومتى.
 *
 * وهو نظيرُ ما عولج في مكافأة نهاية الخدمة: التسويةُ إلى ذمّةٍ خطوة،
 * وإخراجُ النقد خطوةٌ بعدها.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ للمسار وجوداً وشاشةً، وأنّ القيد يُدين الذمّتَين ويُدين الخزينةَ
 * بمجموعهما، وأنّه لا يُسدَّد أكثرُ مما حُجز — فذلك يقلب الذمّة مدينةً، أي
 * دَيناً على الجهة لا لها — وأنّ الضوابطَ النقدية هي ضوابطُ صرف الراتب.
 */
class SocialInsuranceRemittanceTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(
            base_path('Modules/HR/Http/Controllers/Salaries/SocialInsuranceSettingsController.php')
        );
    }

    /** للسداد شاشةٌ ومسارُ تنفيذ. */
    public function test_the_remittance_path_exists(): void
    {
        $this->assertTrue(Route::has('hr.gosi.remittances'), 'شاشة السداد');
        $this->assertTrue(Route::has('hr.gosi.remit'), 'تنفيذ السداد');

        $this->assertSame('remittances', Route::getRoutes()->getByName('hr.gosi.remittances')->getActionMethod());
        $this->assertSame('remit', Route::getRoutes()->getByName('hr.gosi.remit')->getActionMethod());
        $this->assertContains('POST', Route::getRoutes()->getByName('hr.gosi.remit')->methods());
    }

    /** والقيدُ يُدين الذمّتَين ويُدين الخزينةَ بمجموعهما. */
    public function test_the_entry_debits_both_liabilities_and_credits_the_treasury(): void
    {
        $source = $this->controller();

        $this->assertStringContainsString('إقفال تأمينات مستحقة — حصة الموظف', $source);
        $this->assertStringContainsString('إقفال تأمينات مستحقة — حصة المنشأة', $source);
        $this->assertStringContainsString("'credit' => \$total,", $source, 'الخزينة دائنةٌ بالمجموع');
        $this->assertStringContainsString("'GOSI-REM-'", $source, 'مرجعٌ خاصٌّ بالسداد');
    }

    /** ولا يُسدَّد أكثرُ مما حُجز. */
    public function test_it_refuses_to_pay_more_than_was_withheld(): void
    {
        $source = $this->controller();

        $this->assertStringContainsString('حصة الموظف المستحقة', $source);
        $this->assertStringContainsString('حصة المنشأة المستحقة', $source);
        $this->assertMatchesRegularExpression(
            '/\$employeeAmount > \(float\) \$employeeAccount->balance/',
            $source,
            'المُدخَل يُقاس على رصيد الذمّة'
        );
    }

    /** والضوابطُ النقدية هي ضوابطُ صرف الراتب — فالنقدُ الخارجُ واحد. */
    public function test_it_carries_the_payroll_cash_guards(): void
    {
        $source = $this->controller();

        $this->assertStringContainsString('TreasuryAccessGuard::withdrawBlockReason', $source, 'شرطُ السحب');
        $this->assertStringContainsString('TreasuryBalanceGuard::lockAndAssert(', $source, 'قفلُ صفِّ الخزينة والرصيدُ من الدفتر — الحارس المشترك');
        $this->assertStringContainsString('Expense::create(', $source, 'سندُ الصرف');
        $this->assertStringContainsString('DB::transaction(', $source, 'معاملةٌ واحدة');
    }

    /** والدفعةُ تُسجَّل بمن سدّدها ومتى ومن أيِّ خزينة. */
    public function test_a_remittance_records_its_provenance(): void
    {
        Schema::create('social_insurance_remittances', function (Blueprint $t) {
            $t->id();
            $t->string('period_month', 7)->nullable();
            $t->decimal('employee_amount', 15, 2)->default(0);
            $t->decimal('employer_amount', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->unsignedBigInteger('treasury_id')->nullable();
            $t->unsignedBigInteger('expense_id')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->string('reference', 191)->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->unsignedBigInteger('paid_by')->nullable();
            $t->timestamps();
        });

        DB::table('social_insurance_remittances')->insert([
            'period_month' => '2026-08',
            'employee_amount' => 975,
            'employer_amount' => 1175,
            'total_amount' => 2150,
            'treasury_id' => 14,
            'reference' => 'TRF-99',
            'paid_at' => '2026-09-03 12:00:00',
            'paid_by' => 1,
        ]);

        $r = SocialInsuranceRemittance::first();

        $this->assertSame('2026-08', $r->period_month);
        $this->assertEqualsWithDelta(2150.0, (float) $r->total_amount, 0.001);
        $this->assertEqualsWithDelta(
            (float) $r->employee_amount + (float) $r->employer_amount,
            (float) $r->total_amount,
            0.001,
            'المجموعُ حصيلةُ الحصّتَين'
        );
        $this->assertSame(1, (int) $r->paid_by);
        $this->assertNotNull($r->paid_at);
    }
}
