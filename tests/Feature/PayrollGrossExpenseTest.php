<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * مصروفُ الرواتب يُقيَّد بإجماليه، والخصميةُ تُقيَّد مرّةً واحدة — عند اعتمادها.
 *
 * **التطوّر**
 *
 *   1. قديماً: المصروفُ يُدان بالصافي بعد الخصميات، والخصميةُ لا قيدَ لها.
 *   2. ثمّ (2026-09-22): المصروفُ بالإجمالي، والخصمياتُ سطرٌ دائنٌ في قيد
 *      الاستحقاق على حسابٍ واحد `payroll_deductions` — يُرفض الصرفُ كلُّه إن
 *      لم يُربط، ولو ضُبط لكلِّ نوعٍ حسابُه.
 *   3. الآن: الخصميةُ تُقيَّد **عند اعتمادها** على حساب نوعها
 *      (`DeductionAccountingService`)، وقيدُ الراتب لا يُقيّدها ثانيةً:
 *
 *          الاعتماد:   مدين ذمّة الموظف / دائن حساب النوع          (الخصمية)
 *          الاستحقاق:  مدين مصروف الرواتب / دائن ذمّة الموظف       (الإجمالي)
 *          الصرف:      مدين ذمّة الموظف (الإجمالي − الخصميات)
 *                          دائن الخزينة (الصافي) + السلف + التأمينات
 *
 *      فتنقفل الذمّةُ ولا يتكرّر مبلغُ الخصمية.
 *
 * اختبارٌ على المصدر — مجموعةُ الاختبارات لا تعمل على هذا المستودع؛ والتحقّقُ
 * الفعليّ على قاعدة MySQL في سكربتات الجلسة.
 */
class PayrollGrossExpenseTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(app_path('Services/PayrollAccountant.php'));
    }

    /** الشيفرةُ بلا تعليقات — فلا يُحتسب نصُّ شرحٍ على أنّه تنفيذ. */
    private function code(): string
    {
        $code = preg_replace('#/\*.*?\*/#s', '', $this->source());

        return (string) preg_replace('#//.*$#m', '', (string) $code);
    }

    /** جسدُ قيد الاستحقاق حتى مطلع قيد الصرف. */
    private function accrualBlock(): string
    {
        $code = $this->code();
        $from = strpos($code, "'reference_number' => 'SAL-ACC-'");
        $to = strpos($code, "'reference_number' => 'SAL-PAY-'");

        $this->assertNotFalse($from, 'قيد الاستحقاق مفقود');
        $this->assertNotFalse($to, 'قيد الصرف مفقود');

        return substr($code, $from, $to - $from);
    }

    /** المصروفُ يُدان بالإجمالي، والذمّةُ تُقيَّد دائنةً بالإجمالي. */
    public function test_the_accrual_is_gross_on_both_sides(): void
    {
        $block = $this->accrualBlock();

        $this->assertStringContainsString("'debit' => \$calc->totalEarnings", $block);
        $this->assertStringContainsString("'credit' => \$calc->totalEarnings", $block);
    }

    /** ولا سطرَ خصمياتٍ في قيد الاستحقاق — قُيِّدت عند اعتمادها. */
    public function test_the_accrual_has_no_deductions_line(): void
    {
        $block = $this->accrualBlock();

        $this->assertStringNotContainsString('$deductionsAccountId', $block);
        $this->assertStringNotContainsString('$deductionsCredit', $block);
    }

    /** وقيدُ الصرف يُدين الذمّةَ بالإجمالي ناقصاً الخصميات المقتطعة. */
    public function test_the_payment_debits_payable_gross_minus_deductions(): void
    {
        $this->assertStringContainsString(
            '$payableDebitOnPayment = round($calc->totalEarnings - $calc->otherDeductions, 2);',
            $this->code()
        );
    }

    /** وما لم يُقيَّد بعد من الخصميات المقتطعة يُقيَّد قبل قيد الراتب. */
    public function test_unposted_deductions_are_posted_before_the_payroll_journal(): void
    {
        $code = $this->code();

        $posting = strpos($code, '->ensurePosted(');
        $accrual = strpos($code, "'reference_number' => 'SAL-ACC-'");

        $this->assertNotFalse($posting);
        $this->assertLessThan($accrual, $posting);
    }

    /** وكفايةُ الخزينة تُسأل من الحارس المشترك قبل أيِّ قيد. */
    public function test_treasury_balance_is_guarded_before_any_journal(): void
    {
        $code = $this->code();

        $guard = strpos($code, 'TreasuryBalanceGuard::lockAndAssert(');
        $firstJournal = strpos($code, 'JournalEntry::create(');

        $this->assertNotFalse($guard);
        $this->assertLessThan($firstJournal, $guard);
    }

    /** والمفتاحُ العام معروضٌ في شاشة الحسابات المحاسبية ليربطه المحاسب. */
    public function test_the_key_is_offered_in_the_accounting_settings_screen(): void
    {
        $seeder = (string) file_get_contents(base_path('database/seeders/AccountingSettingsSeeder.php'));

        $this->assertStringContainsString("'payroll_deductions' =>", $seeder);
        $this->assertStringContainsString('خصميات الموظفين', $seeder);
        $this->assertStringContainsString('GROUP_PAYROLL', $seeder);
    }
}
