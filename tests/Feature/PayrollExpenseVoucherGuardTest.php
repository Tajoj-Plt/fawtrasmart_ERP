<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Account\Http\Controllers\Finance\ExpensesController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * سندُ صرف الراتب لا يُعدَّل ولا يُحذف من شاشة المصروفات.
 *
 * **ما كان**
 *
 * `PayrollAccountant` ينشئ صفَّ `expenses` لكلِّ راتب يُصرف، ويكتب قيدَ
 * الصرف بـ`reference_type = EXPENSES` و`reference_id = expenses.id`. وهذان
 * بالضبط ما يطابق عليه `ExpensesController::findExpenseJournalEntry()`.
 *
 * فكان `expenses.delete` — وهو `cancel()` نفسها — يرحّل قيداً عكسيّاً
 * **لقيد الراتب** ثمّ يحذف الصفَّ حذفاً صلباً (`Expense` بلا `SoftDeletes`).
 * ولا سطرَ في وحدة الحسابات يعرف `payroll_runs`، ولا فلترَ يستثني سندات
 * الرواتب من الشاشة. فتبقى الصرفةُ `disbursed` تشير بـ`expense_id` إلى صفٍّ
 * محذوف، وتبقى السلفُ والخصمياتُ مسوّاةً كأنّ الراتب صُرف.
 *
 * وأثرُه لا يقف عند الاختلال: `PayrollReversalService::reverseJournals()`
 * يتخطّى كلَّ قيدٍ `is_reversed`، فمن عكَس الصرفةَ بعد ذلك من شاشتها لا يجد
 * قيداً يعكسه — وتعود إلى `pending` بلا فكِّ تسوياتها، قابلةً للصرف ثانيةً.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الحارسَ يمنع سندَ صرفةٍ قائمة، ويسمّي رقمَها، ولا يعترض سنداً عاديّاً،
 * ولا يُقفل السندَ إلى الأبد بعد العكس (إذ يُصفَّر `expense_id` هناك).
 * ثمّ — على منوال `PayrollDisbursementPostingTest` — يقيس أنّ `cancel()`
 * و`update()` تسألان الحارسَ فعلاً: منطقٌ صحيحٌ لا يُنادى لا يمنع شيئاً.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class PayrollExpenseVoucherGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->text('description')->nullable();
            $t->date('date')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('expense_id')->nullable();
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });
    }

    /** السببُ الذي يعيده الحارس لسندٍ ما — أو `null`. */
    private function blockReason(int $expenseId): ?string
    {
        $method = new ReflectionMethod(ExpensesController::class, 'payrollExpenseBlockReason');
        $method->setAccessible(true);

        return $method->invoke(
            (new \ReflectionClass(ExpensesController::class))->newInstanceWithoutConstructor(),
            $expenseId
        );
    }

    private function seedExpense(int $id, string $code): void
    {
        DB::table('expenses')->insert([
            'id' => $id,
            'code' => $code,
            'amount' => 5000,
            'description' => 'صرف راتب',
            'date' => '2026-09-01',
        ]);
    }

    /** سندُ صرفةٍ قائمة ممنوع، والرسالةُ تسمّي الصرفة وتدلّ على مخرجها. */
    public function test_a_voucher_owned_by_a_payroll_run_is_blocked(): void
    {
        $this->seedExpense(1, 'EXP-0001');
        DB::table('payroll_runs')->insert([
            'id' => 42,
            'employee_id' => 7,
            'expense_id' => 1,
            'status' => 'disbursed',
        ]);

        $reason = $this->blockReason(1);

        $this->assertNotNull($reason, 'سندُ راتبٍ مصروف يجب أن يُمنع من شاشة المصروفات');
        $this->assertStringContainsString('42', $reason, 'الرسالةُ يجب أن تسمّي رقم الصرفة');
        $this->assertStringContainsString('عكس الصرف', $reason, 'الرسالةُ يجب أن تدلّ على المخرج الصحيح');
    }

    /** وسندُ المصروفات العاديّ لا يُعترض — الحارسُ لا يوسّع منعَه. */
    public function test_an_ordinary_voucher_is_not_blocked(): void
    {
        $this->seedExpense(2, 'EXP-0002');

        $this->assertNull($this->blockReason(2));
    }

    /**
     * بعد العكس يُصفَّر `expense_id` في `PayrollReversalService`، فالسندُ
     * الموسوم `[معكوس]` يعود إلى التصرّف العاديّ ولا يُقفَل إلى الأبد.
     */
    public function test_a_voucher_released_by_reversal_is_no_longer_blocked(): void
    {
        $this->seedExpense(3, 'EXP-0003');
        DB::table('payroll_runs')->insert([
            'id' => 43,
            'employee_id' => 7,
            'expense_id' => null,
            'status' => 'pending',
        ]);

        $this->assertNull($this->blockReason(3));
    }

    /**
     * حارسٌ على الشيفرة نفسها: المنعُ يُنادى من المخرجَين اللذين يمسّان
     * السند — الإلغاء/الحذف والتعديل.
     */
    public function test_both_mutating_paths_consult_the_guard(): void
    {
        $source = (string) file_get_contents(
            base_path('Modules/Account/Http/Controllers/Finance/ExpensesController.php')
        );

        // جسدُ كلِّ دالّة حتى مطلع التي تليها
        $cancel = $this->methodBody($source, 'cancel');
        $update = $this->methodBody($source, 'update');

        $this->assertStringContainsString(
            'payrollExpenseBlockReason',
            $cancel,
            'cancel() — ومنها delete() — يجب أن تسأل الحارس قبل ترحيل القيد العكسي والحذف'
        );

        $this->assertStringContainsString(
            'payrollExpenseBlockReason',
            $update,
            'update() يجب أن تسأل الحارس: تعديلُ المبلغ يفصل السند عن payroll_runs.net_salary'
        );
    }

    /** جسدُ دالّةٍ عامّة من نصّ الملف، حتى مطلع الدالّة التي تليها. */
    private function methodBody(string $source, string $name): string
    {
        $start = strpos($source, 'public function '.$name.'(');
        $this->assertNotFalse($start, "لم تُوجد الدالّة {$name}() في المتحكّم");

        $next = strpos($source, "\n    public function ", $start + 1);

        return $next === false
            ? substr($source, $start)
            : substr($source, $start, $next - $start);
    }
}
