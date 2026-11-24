<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use Modules\Account\Http\Controllers\Finance\ExpensesController;
use Modules\Account\Http\Controllers\Finance\IncomesController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * منعُ تكرار سند الصرف/القبض عند نقرتين على «حفظ».
 *
 * **ما كان**
 *
 * `ExpensesController::store()` و`IncomesController::store()` كانتا بلا أي
 * حمايةٍ من إعادة الإرسال: نقرتان سريعتان — أو ضغطٌ ثانٍ قبل أن يستجيب
 * الزرّ لأولاهما — تصلان كطلبين منفصلين تماماً، فيُنشئ كلٌّ منهما سندَه
 * الخاصّ ويُرحَّل إلى الخزينة والحساب المقابل مرّتين: صرفٌ أو قبضٌ مضاعَف
 * لعمليةٍ واحدة فعلاً، ويختلّ الرصيدان بمقدار السند الزائد بصمت.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * ١) أنّ `AccountingOperations::guardDuplicateSubmission()` — الحارسُ
 *    المشترك بين المتحكّمَين — يمسك قفلاً ذرّياً على بصمة الطلب، يرفض
 *    طلباً ثانياً بنفس البصمة بينما الأول لا يزال ممسكاً بقفله، ولا يعترض
 *    طلباً آخر ببصمةٍ مختلفة فعلاً (مبلغٌ مختلف)، ولا يبقى القفل إلى الأبد
 *    بعد تحريره.
 *
 * ٢) أنّ كلا المتحكّمَين يستدعيان الحارس فعلاً في `store()`، ويحرّران
 *    القفل في كل مسار فشل (`catch`) — منطقٌ غير مستدعًى لا يحمي شيئاً،
 *    وقفلٌ لا يُحرَّر عند الفشل يمنع المندوب من إعادة محاولةٍ مشروعة.
 */
class DuplicateVoucherSubmissionGuardTest extends TestCase
{
    /** كائنٌ يستخدم التِرِيت وحده — بلا بناء متحكّمٍ كامل يحتاج قاعدة بيانات. */
    private function guardHost(): object
    {
        return new class
        {
            use AccountingOperations;
        };
    }

    private function callGuard(object $host, Request $request, string $prefix, array $fields, int $seconds = 15)
    {
        $method = new ReflectionMethod($host, 'guardDuplicateSubmission');
        $method->setAccessible(true);

        return $method->invoke($host, $request, $prefix, $fields, $seconds);
    }

    /** أول طلبٍ يمسك القفل بنجاح ولا يُرفض. */
    public function test_first_submission_acquires_the_lock(): void
    {
        $host = $this->guardHost();
        $request = Request::create('/', 'POST', [
            'amount' => '500', 'date' => '2026-09-12', 'treasury_id' => 1, 'account_id' => 9,
        ]);

        $lock = $this->callGuard($host, $request, 'expense', ['amount', 'date', 'treasury_id', 'account_id']);

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Lock::class, $lock);

        $lock->release();
    }

    /**
     * نقرةٌ ثانية بنفس بيانات السند بالضبط — بينما الأولى لا تزال «قيد
     * المعالجة» (لم تُحرِّر قفلها) — يجب أن تُرفض بخطأ تحقّقٍ لا أن تمرّ
     * فتُنشئ سنداً موازياً.
     */
    public function test_a_duplicate_click_with_identical_fields_is_rejected_while_the_first_lock_is_held(): void
    {
        $host = $this->guardHost();
        $fields = ['amount', 'date', 'treasury_id', 'account_id', 'description'];
        $payload = [
            'amount' => '1250.50', 'date' => '2026-09-12', 'treasury_id' => 3,
            'account_id' => 21, 'description' => 'صرف نثرية مكتب',
        ];

        $first = $this->callGuard($host, Request::create('/', 'POST', $payload), 'expense', $fields);

        try {
            $this->expectException(ValidationException::class);
            $this->callGuard($host, Request::create('/', 'POST', $payload), 'expense', $fields);
        } finally {
            $first->release();
        }
    }

    /** سندٌ آخر فعلاً — بمبلغٍ مختلف من نفس المستخدم في نفس اللحظة — لا يُحجب. */
    public function test_a_genuinely_different_voucher_is_not_blocked_by_an_unrelated_lock(): void
    {
        $host = $this->guardHost();
        $fields = ['amount', 'date', 'treasury_id', 'account_id'];

        $first = $this->callGuard(
            $host,
            Request::create('/', 'POST', ['amount' => '100', 'date' => '2026-09-12', 'treasury_id' => 1, 'account_id' => 9]),
            'expense',
            $fields
        );

        $second = $this->callGuard(
            $host,
            Request::create('/', 'POST', ['amount' => '999', 'date' => '2026-09-12', 'treasury_id' => 1, 'account_id' => 9]),
            'expense',
            $fields
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Lock::class, $second);

        $first->release();
        $second->release();
    }

    /** بعد تحرير القفل — كما يفعل مسار الفشل — تمرّ إعادةُ المحاولة بلا اعتراض. */
    public function test_retry_succeeds_immediately_after_the_lock_is_released(): void
    {
        $host = $this->guardHost();
        $fields = ['amount', 'date'];
        $payload = ['amount' => '50', 'date' => '2026-09-12'];

        $first = $this->callGuard($host, Request::create('/', 'POST', $payload), 'income', $fields);
        $first->release();

        $retry = $this->callGuard($host, Request::create('/', 'POST', $payload), 'income', $fields);

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Lock::class, $retry);

        $retry->release();
    }

    /** بصمتان مختلفتان بادئةً («expense» مقابل «income») لا تتصادمان. */
    public function test_expense_and_income_prefixes_do_not_share_the_same_lock_key(): void
    {
        $host = $this->guardHost();
        $fields = ['amount', 'date'];
        $payload = ['amount' => '75', 'date' => '2026-09-12'];

        $expenseLock = $this->callGuard($host, Request::create('/', 'POST', $payload), 'expense', $fields);
        $incomeLock = $this->callGuard($host, Request::create('/', 'POST', $payload), 'income', $fields);

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Lock::class, $incomeLock);

        $expenseLock->release();
        $incomeLock->release();
    }

    /**
     * فحصٌ على الشيفرة نفسها: `store()` في كلا المتحكّمَين يستدعي الحارس،
     * ويحرّر القفل في كل مسار فشل — لا في مسار النجاح (يبقى حتى تنقضي
     * مهلته عمداً). حارسٌ غير مستدعًى من الشيفرة الفعلية لا يحمي شيئاً
     * مهما صحّ منطقُه في اختبارٍ معزول.
     */
    public function test_both_store_methods_call_the_guard_and_release_it_on_every_failure_path(): void
    {
        $expensesSource = (string) file_get_contents(
            base_path('Modules/Account/Http/Controllers/Finance/ExpensesController.php')
        );
        $incomesSource = (string) file_get_contents(
            base_path('Modules/Account/Http/Controllers/Finance/IncomesController.php')
        );

        $expensesStore = $this->methodBody($expensesSource, 'store');
        $incomesStore = $this->methodBody($incomesSource, 'store');

        foreach (['ExpensesController' => $expensesStore, 'IncomesController' => $incomesStore] as $name => $body) {
            $this->assertStringContainsString(
                'guardDuplicateSubmission',
                $body,
                "{$name}::store() يجب أن يستدعي guardDuplicateSubmission() قبل الحفظ"
            );

            $this->assertSame(
                2,
                substr_count($body, '$duplicateLock->release()'),
                "{$name}::store() يجب أن يحرّر القفل في مساري الفشل الاثنين (ValidationException وباقي الاستثناءات) لا أكثر ولا أقل"
            );
        }
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
