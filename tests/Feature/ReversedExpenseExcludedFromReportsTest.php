<?php

namespace Tests\Feature;

use App\Models\Expense;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * راتبٌ عُكس لا يُحصى مصروفاً مرّتين.
 *
 * **ما كان**
 *
 * `PayrollReversalService::annotateExpense()` لا يفعل بسند صرف الراتب
 * المعكوس إلا أن يُصدّر وصفَه بـ`[معكوس]` ويُفرّغ
 * `payroll_runs.expense_id`. وبقاءُ الصفِّ مقصودٌ في نفسه: المستندُ لا
 * يُمحى، والقيدُ العكسيُّ صحَّح الدفتر.
 *
 * لكنّ التقاريرَ لا تقرأ وصفاً. فـ
 * `GeneralAccountsController::expensesByCategory` و`expensesByPeriod`
 * وبطاقاتُ الإحصاء في شاشة المصروفات تستعلم `Expense::query()` بلا أيِّ
 * استثناء. والعكسُ ثمّ إعادةُ الصرف — وهو المسارُ المقصود لتصحيح خطأ —
 * يُخلِّف **صفَّي مصروفٍ لراتبٍ واحد**: فيضاعف التقريرُ بينما الدفترُ صحيح،
 * ويختلف تقريران عن الشهر نفسه.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الوسمَ صار عموداً يُستثنى به المعكوسُ من الجمع، وأنّ السندَ يبقى في
 * الجدول ليُقرأ، وأنّ مواضعَ الجمع كلَّها تلتزم النطاقَ — فنطاقٌ لا يُنادى
 * لا يستثني شيئاً.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class ReversedExpenseExcludedFromReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->text('description')->nullable();
            $t->date('date')->nullable();
            $t->timestamp('reversed_at')->nullable();
            $t->string('reversal_reason', 500)->nullable();
            $t->timestamps();
        });
    }

    private function voucher(string $code, float $amount, bool $reversed): void
    {
        DB::table('expenses')->insert([
            'code' => $code,
            'amount' => $amount,
            'total_amount' => $amount,
            'description' => ($reversed ? '[معكوس] ' : '').'صرف راتب',
            'date' => '2026-09-05',
            'reversed_at' => $reversed ? '2026-09-06 10:00:00' : null,
        ]);
    }

    /**
     * الحالةُ التي كانت تضاعف: راتبٌ صُرف، فعُكس، فأُعيد صرفه.
     * الجدولُ يحمل صفَّين، والمصروفُ الحقيقيُّ واحد.
     */
    public function test_a_reversed_and_reissued_salary_counts_once(): void
    {
        $this->voucher('EXP-0001', 7600, reversed: true);
        $this->voucher('EXP-0002', 7600, reversed: false);

        $this->assertSame(2, Expense::count(), 'المستندان يبقيان في السجلّ');
        $this->assertEqualsWithDelta(
            7600.0,
            (float) Expense::notReversed()->sum('amount'),
            0.001,
            'المصروفُ المحصى راتبٌ واحد لا اثنان'
        );
    }

    /** والسندُ المعكوس يبقى مقروءاً — الوسمُ استثناءٌ من الجمع لا حذف. */
    public function test_the_reversed_voucher_is_still_readable(): void
    {
        $this->voucher('EXP-0001', 7600, reversed: true);

        $expense = Expense::where('code', 'EXP-0001')->first();

        $this->assertNotNull($expense);
        $this->assertTrue($expense->isReversed());
        $this->assertStringContainsString('[معكوس]', (string) $expense->description);
    }

    /** والسندُ العاديُّ لا يمسّه النطاق. */
    public function test_an_ordinary_voucher_is_not_excluded(): void
    {
        $this->voucher('EXP-0003', 1200, reversed: false);

        $this->assertSame(1, Expense::notReversed()->count());
        $this->assertFalse(Expense::where('code', 'EXP-0003')->first()->isReversed());
    }

    /**
     * حارسٌ على الشيفرة: كلُّ موضعٍ يجمع المصروفات ينادي النطاق — تقريرا
     * الفئة والفترة، وبطاقاتُ شاشة المصروفات، وبيانُ الضريبة.
     */
    public function test_every_aggregation_applies_the_scope(): void
    {
        $reports = (string) file_get_contents(
            base_path('Modules/Account/Http/Controllers/GeneralAccountsController.php')
        );
        $screen = (string) file_get_contents(
            base_path('Modules/Account/Http/Controllers/Finance/ExpensesController.php')
        );

        $this->assertSame(
            3,
            substr_count($reports, '->notReversed()'),
            'تقريرا الفئة والفترة وبيانُ الضريبة — ثلاثةُ مواضعَ تجمع المصروفات'
        );

        $this->assertGreaterThanOrEqual(
            6,
            substr_count($screen, 'notReversed()'),
            'بطاقاتُ الإحصاء الثلاث في index() وgetData() معاً'
        );
    }

    /** ولا يُعاد عكسُ سندٍ عُكس: قيدُه الأصليّ موسومٌ فلا يجد الحذفُ ما يعكسه. */
    public function test_a_reversed_voucher_cannot_be_reversed_again(): void
    {
        $screen = (string) file_get_contents(
            base_path('Modules/Account/Http/Controllers/Finance/ExpensesController.php')
        );

        $this->assertStringContainsString(
            "whereNotNull('reversed_at')",
            $screen,
            'الإلغاءُ يجب أن يرفض سنداً معكوساً مسبقاً'
        );
        $this->assertStringContainsString('معكوسٌ مسبقاً', $screen);
    }
}
