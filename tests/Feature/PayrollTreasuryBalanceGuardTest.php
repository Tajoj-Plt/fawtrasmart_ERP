<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Services\Finance\InsufficientTreasuryBalanceException;
use App\Services\Finance\TreasuryBalanceGuard;
use Tests\TestCase;

/**
 * كفايةُ رصيد الخزينة: قفلُ صفِّها، والرصيدُ من الدفتر، ولا سالب — في حارسٍ واحد.
 *
 * **ما كان**
 *
 * الفحصُ منسوخٌ في ثلاثة مواضع (صرف الراتب، صرف مكافأة نهاية الخدمة، سداد
 * التأمينات) بثلاث صيغ. وقبلها كان صرفُ الراتب يُقفل صفَّ **الصرفة** وحدها
 * ويقرأ `accounts.balance` بلا قفل، فصرفتان متزامنتان لموظفَين مختلفَين
 * تقرآن الرصيدَ نفسَه وتنتهي الخزينةُ سالبة.
 *
 * **ما صار**
 *
 * `App\Services\Finance\TreasuryBalanceGuard::lockAndAssert()`: داخل معاملةٍ
 * وحدها، يُقفل صفَّ حساب الخزينة، ويحتسب الرصيدَ من القيود، ويرمي
 * `InsufficientTreasuryBalanceException` إن لم يكفِ — ويسأله كلُّ مخرجٍ نقديّ
 * **قبل** أيِّ قيدٍ أو سند.
 *
 * والقياسُ على الشيفرة لا بمعاملتَين متزامنتَين: `phpunit.xml` يفرض sqlite في
 * الذاكرة ولا اتّصالَ ثانٍ يزاحم الأوّل. والتحقّقُ الفعليّ على MySQL في
 * سكربتات الجلسة (خزينةٌ فارغة تُرفض، وممولةٌ تُجاز).
 */
class PayrollTreasuryBalanceGuardTest extends TestCase
{
    private function code(string $relative): string
    {
        $code = (string) file_get_contents(base_path($relative));
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $code);

        return (string) preg_replace('#//.*$#m', '', $code);
    }

    /** الحارسُ يُقفل ثمّ يحتسب من الدفتر ثمّ يقارن. */
    public function test_guard_locks_then_computes_then_compares(): void
    {
        $code = $this->code('app/Services/Finance/TreasuryBalanceGuard.php');

        $lockAt = strpos($code, 'lockForUpdate()');
        $computeAt = strpos($code, 'updateBalanceFromJournalEntries()');
        $throwAt = strpos($code, 'new InsufficientTreasuryBalanceException');

        $this->assertNotFalse($lockAt);
        $this->assertNotFalse($computeAt);
        $this->assertNotFalse($throwAt);
        $this->assertLessThan($computeAt, $lockAt, 'القفلُ قبل الاحتساب');
        $this->assertLessThan($throwAt, $computeAt, 'الاحتسابُ قبل المقارنة');
        $this->assertStringContainsString('DB::transactionLevel()', $code, 'يُرفض خارج معاملة');
    }

    /** وكلُّ مخرجٍ نقديٍّ في الرواتب يسأله قبل أيِّ قيد. */
    public function test_every_payroll_cash_exit_uses_the_guard(): void
    {
        foreach ([
            'app/Services/PayrollAccountant.php',
            'Modules/HR/Http/Controllers/Salaries/EndOfServiceBenefitController.php',
            'Modules/HR/Http/Controllers/Salaries/SocialInsuranceSettingsController.php',
        ] as $file) {
            $this->assertStringContainsString('TreasuryBalanceGuard::lockAndAssert(', $this->code($file), $file);
        }

        $accountant = $this->code('app/Services/PayrollAccountant.php');
        $this->assertLessThan(
            strpos($accountant, 'JournalEntry::create('),
            strpos($accountant, 'TreasuryBalanceGuard::lockAndAssert('),
            'الفحصُ قبل أوّل قيد'
        );
    }

    /** والرسالةُ تسمّي الرصيدَ والمطلوب. */
    public function test_the_exception_names_both_figures(): void
    {
        $treasury = new Account(['name' => 'خزينة']);
        $e = new InsufficientTreasuryBalanceException($treasury, 10.0, 25.5, 'صرف صافي الراتب');

        $this->assertStringContainsString('10.00', $e->getMessage());
        $this->assertStringContainsString('25.50', $e->getMessage());
        $this->assertStringContainsString('لا يكفي', $e->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function test_the_guard_api_exists(): void
    {
        $this->assertTrue(method_exists(TreasuryBalanceGuard::class, 'lockAndAssert'));
        $this->assertTrue(method_exists(TreasuryBalanceGuard::class, 'assertWithdrawable'));
        $this->assertTrue(method_exists(TreasuryBalanceGuard::class, 'withdrawableAccounts'));
    }
}
