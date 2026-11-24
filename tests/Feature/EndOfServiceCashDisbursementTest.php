<?php

namespace Tests\Feature;

use App\Models\EndOfServiceBenefit;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * مكافأةُ نهاية الخدمة لا تصير «مدفوعة» قبل أن يخرج المال.
 *
 * **ما كان**
 *
 * `EndOfServiceBenefitController::pay()` يرحّل قيداً واحداً — مدين مخصص /
 * دائن مستحقات الموظف — ثمّ يضبط `status = paid` و`paid_at` و`paid_by`.
 * لا خزينةَ في الأمر، ولا سندَ صرف، ولا تغيُّرَ في أيِّ رصيدٍ نقدي.
 *
 * والقيدُ نفسُه صحيحٌ محاسبياً: تسويةُ مخصَّصٍ إلى ذمّة. الخطأُ في الكلمة
 * وفي غياب الخطوة التي بعدها — فالمكافأةُ «مدفوعة» في الشاشة، وحقُّ الموظف
 * المنتهية خدمتُه قائمٌ في الدفتر بلا أيِّ مسارٍ في الوحدة يُخرجه نقداً.
 * وزرُّ الشاشة كان يقول «تسوية إلى مستحقات الموظف» بينما الحالةُ تقول
 * «مدفوعة»: الواجهةُ صادقة والحالةُ ليست.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الحالتَين انفصلتا وأنّ الترتيبَ بينهما محفوظ، وأنّ الصرفَ النقديَّ
 * يلتزم ضوابطَ صرف الراتب نفسَها — فالنقدُ الخارجُ واحد — وأنّ الهجرةَ
 * تنقل ما كان موسوماً `paid` إلى `settled`، إذ هو مسوّىً لا مدفوع.
 */
class EndOfServiceCashDisbursementTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(
            base_path('Modules/HR/Http/Controllers/Salaries/EndOfServiceBenefitController.php')
        );
    }

    /** الحالتان منفصلتان: تسويةٌ إلى ذمّة، ثمّ صرفٌ نقدي. */
    public function test_settlement_and_payment_are_separate_states(): void
    {
        $this->assertSame('settled', EndOfServiceBenefit::STATUS_SETTLED);
        $this->assertSame('paid', EndOfServiceBenefit::STATUS_PAID);

        $settled = new EndOfServiceBenefit(['status' => EndOfServiceBenefit::STATUS_SETTLED]);
        $approved = new EndOfServiceBenefit(['status' => EndOfServiceBenefit::STATUS_APPROVED]);
        $paid = new EndOfServiceBenefit(['status' => EndOfServiceBenefit::STATUS_PAID]);

        $this->assertSame('مستحقة للموظف', $settled->statusLabel());
        $this->assertSame('مدفوعة', $paid->statusLabel());
    }

    /** ولا تُصرف نقداً إلا ما سُوِّي، ولا تُسوَّى إلا ما اعتُمد. */
    public function test_the_order_between_them_is_enforced(): void
    {
        $approved = new EndOfServiceBenefit(['status' => EndOfServiceBenefit::STATUS_APPROVED]);
        $settled = new EndOfServiceBenefit(['status' => EndOfServiceBenefit::STATUS_SETTLED]);
        $paid = new EndOfServiceBenefit(['status' => EndOfServiceBenefit::STATUS_PAID]);

        $this->assertTrue($approved->canBeSettled());
        $this->assertFalse($approved->canBeDisbursed(), 'لا يُصرف ما لم يُسوَّ');

        $this->assertFalse($settled->canBeSettled());
        $this->assertTrue($settled->canBeDisbursed());

        $this->assertFalse($paid->canBeDisbursed(), 'لا يُصرف مرّتين');
    }

    /** ولكلِّ خطوةٍ مسارُها. */
    public function test_each_step_has_its_own_route(): void
    {
        $this->assertTrue(Route::has('hr.eos.settle'), 'التسوية إلى الذمّة');
        $this->assertTrue(Route::has('hr.eos.pay'), 'الصرف النقدي');

        $this->assertSame('settle', Route::getRoutes()->getByName('hr.eos.settle')->getActionMethod());
        $this->assertSame('pay', Route::getRoutes()->getByName('hr.eos.pay')->getActionMethod());
    }

    /** والتسويةُ لا تدّعي دفعاً: تضبط `settled` لا `paid`. */
    public function test_settlement_no_longer_claims_payment(): void
    {
        $source = $this->controller();

        $settleAt = strpos($source, 'public function settle(');
        $this->assertNotFalse($settleAt, 'settle() مفقودة');

        $body = substr($source, $settleAt, (int) (strpos($source, 'public function ', $settleAt + 10) - $settleAt));

        $this->assertStringContainsString('STATUS_SETTLED', $body);
        $this->assertStringNotContainsString('STATUS_PAID', $body, 'التسويةُ لا تضع حالةَ الدفع');
    }

    /**
     * والصرفُ النقديُّ يلتزم ضوابطَ صرف الراتب: شرطُ السحب، وقفلُ صفِّ
     * الخزينة، والرصيدُ محتسبٌ من الدفتر، والقيدُ يُنقص الخزينة، ومعه سندُ
     * صرفٍ يظهر في شاشة المصروفات.
     */
    public function test_the_cash_step_carries_the_payroll_guards(): void
    {
        $source = $this->controller();

        $this->assertStringContainsString(
            'TreasuryAccessGuard::withdrawBlockReason',
            $source,
            'شرطُ السحب من الخزينة'
        );
        // القفلُ والرصيدُ من الدفتر في الحارس المشترك — انظر PayrollTreasuryBalanceGuardTest
        $this->assertStringContainsString(
            'TreasuryBalanceGuard::lockAndAssert(',
            $source,
            'كفايةُ الرصيد تحت قفلٍ ومن الدفتر'
        );
        $this->assertStringContainsString('Expense::create(', $source, 'سندُ الصرف');
        $this->assertStringContainsString("'EOS-P-'", $source, 'قيدُ الصرف بمرجعٍ خاصٍّ به');
    }

    /** والقيدُ يُنقص الخزينة ويُقفل الذمّة — لا العكس. */
    public function test_the_payment_entry_debits_the_payable_and_credits_the_treasury(): void
    {
        $source = $this->controller();

        $payAt = strpos($source, "'EOS-P-'");
        $this->assertNotFalse($payAt);

        $block = substr($source, $payAt, 1800);

        $payableAt = strpos($block, "'account_id' => \$payableAccountId");
        $treasuryAt = strpos($block, "'account_id' => \$locked->id");

        $this->assertNotFalse($payableAt, 'سطرُ الذمّة مفقود');
        $this->assertNotFalse($treasuryAt, 'سطرُ الخزينة مفقود');
        $this->assertLessThan($treasuryAt, $payableAt, 'الذمّةُ مدينةٌ أوّلاً ثمّ الخزينةُ دائنة');
    }

    /** والهجرةُ تنقل ما كان `paid` إلى `settled` — فهو مسوّىً لا مدفوع. */
    public function test_the_migration_reclassifies_the_old_rows(): void
    {
        $migration = (string) file_get_contents(base_path(
            'database/migrations/2026_09_03_000300_add_cash_disbursement_to_end_of_service_benefits_table.php'
        ));

        $this->assertStringContainsString("->where('status', 'paid')", $migration);
        $this->assertStringContainsString("'status' => 'settled'", $migration);
        $this->assertStringContainsString("'paid_at' => null", $migration, 'تاريخُ دفعٍ لم يقع يُمحى');
    }
}
