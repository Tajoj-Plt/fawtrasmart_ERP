<?php

namespace Tests\Feature;

use App\Models\PayrollBatch;
use App\Models\PayrollRun;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * لا يخرج نقدٌ بلا تصريحٍ لهذه الدفعة بعينها.
 *
 * **ما كان**
 *
 * الاشتراطُ معلَّقٌ على وجود المسير نفسِه:
 *
 *     if ($this->payroll_batch_id) { ... يجب أن يكون معتمداً ... }
 *
 * فصرفةٌ **بلا** مسيرٍ تمرّ بلا اعتمادٍ للصرف بحال. والصرفاتُ التي يولّدها
 * الكرون تُنشأ بلا مسير، و`auto_disburse_payroll_runs` افتراضُه التفعيل.
 * فالمحصّلة أنّ القسيمةَ تُعتمد مرّةً واحدة، ثمّ يخرج النقدُ من الخزينة كلَّ
 * شهرٍ بلا تصريحٍ لهذه الدفعة وبلا معتمِدٍ مسجَّل — و«مسير الصرفات»، وهو
 * بوابةُ التصريح الوحيدة، اختياريٌّ يسهل ألّا يُستعمل أبداً.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أنّ الاشتراطَ صار قائماً في ذاته: الصرفةُ بلا مسيرٍ ممنوعةٌ كالتي في مسيرٍ
 * لم يُعتمد، والرسالةُ تسمّي الناقصَ لا تقول «لا يمكن» وحدها. وأنّ المفتاحَ
 * في الإعدادات يُرجع السلوكَ السابق لمن أراده صراحةً — وأنّ افتراضَه
 * التفعيل، فالضابطُ المالي يُخفق مُقفَلاً لا مفتوحاً.
 *
 * الجداول تُبنى يدوياً لا بـ`RefreshDatabase`: هجرات المشروع تحوي صيغاً
 * خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class PayrollDisbursementApprovalRequiredTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        PayrollSettings::forget();

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('auto_generate_payroll_runs')->default(true);
            $t->boolean('auto_disburse_payroll_runs')->default(true);
            $t->boolean('require_disbursement_approval')->default(true);
            $t->timestamps();
        });

        Schema::create('payroll_batches', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('status', 20)->default(PayrollBatch::STATUS_PENDING);
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('payroll_batch_id')->nullable();
            $t->date('period_from')->nullable();
            $t->date('period_to')->nullable();
            $t->date('scheduled_date')->nullable();
            $t->string('status', 20)->default(PayrollRun::STATUS_PENDING);
            $t->timestamp('held_at')->nullable();
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        PayrollSettings::forget();

        parent::tearDown();
    }

    private function setting(bool $require): void
    {
        DB::table('payroll_settings')->insert([
            'auto_generate_payroll_runs' => true,
            'auto_disburse_payroll_runs' => true,
            'require_disbursement_approval' => $require,
        ]);

        PayrollSettings::forget();
    }

    private function makeRun(?int $batchId = null): PayrollRun
    {
        return PayrollRun::create([
            'salary_slip_id' => 1,
            'employee_id' => 1,
            'payroll_batch_id' => $batchId,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'scheduled_date' => '2026-09-30',
            'status' => PayrollRun::STATUS_PENDING,
        ]);
    }

    private function batch(string $status): PayrollBatch
    {
        return PayrollBatch::create(['name' => 'مسير سبتمبر', 'status' => $status]);
    }

    /** الافتراضُ اشتراطُ الاعتماد — الضابطُ المالي يُخفق مُقفَلاً. */
    public function test_approval_is_required_by_default(): void
    {
        $this->assertTrue(PayrollRun::requiresDisbursementApproval());
    }

    /** وهذه هي الثغرة: صرفةٌ بلا مسيرٍ كانت تمرّ بلا اعتمادٍ بحال. */
    public function test_a_run_with_no_batch_is_blocked(): void
    {
        $this->setting(true);

        $reason = $this->makeRun()->disbursementApprovalBlockReason();

        $this->assertNotNull($reason, 'صرفةٌ بلا مسير صرف يجب أن تُمنع');
        $this->assertStringContainsString('مسير صرف', $reason, 'الرسالةُ تدلّ على الشاشة المطلوبة');
    }

    /** والصرفةُ في مسيرٍ لم يُعتمد ممنوعةٌ كذلك، وباسم مسيرها. */
    public function test_a_run_in_a_pending_batch_is_blocked(): void
    {
        $this->setting(true);
        $batch = $this->batch(PayrollBatch::STATUS_PENDING);

        $reason = $this->makeRun($batch->id)->disbursementApprovalBlockReason();

        $this->assertNotNull($reason);
        $this->assertStringContainsString('مسير سبتمبر', $reason);
    }

    /** والصرفةُ في مسيرٍ معتمدٍ تمرّ. */
    public function test_a_run_in_an_approved_batch_passes(): void
    {
        $this->setting(true);
        $batch = $this->batch(PayrollBatch::STATUS_APPROVED);

        $this->assertNull($this->makeRun($batch->id)->disbursementApprovalBlockReason());
    }

    /** ومسيرٌ حُذف بعد ربطه لا يُعدّ تصريحاً. */
    public function test_a_run_whose_batch_was_deleted_is_blocked(): void
    {
        $this->setting(true);
        $batch = $this->batch(PayrollBatch::STATUS_APPROVED);
        $run = $this->makeRun($batch->id);
        $batch->delete();

        $reason = $run->fresh()->disbursementApprovalBlockReason();

        $this->assertNotNull($reason);
        $this->assertStringContainsString('محذوف', $reason);
    }

    /** وإطفاءُ المفتاح يُرجع السلوكَ السابق لمن أراده صراحةً. */
    public function test_the_setting_can_restore_the_previous_behaviour(): void
    {
        $this->setting(false);

        $this->assertFalse(PayrollRun::requiresDisbursementApproval());
        $this->assertNull($this->makeRun()->disbursementApprovalBlockReason());
    }

    /** و`canBeDisbursed()` تلتزم الاشتراطَ لا تكتفي بفحص الحالة. */
    public function test_can_be_disbursed_honours_the_requirement(): void
    {
        $this->setting(true);

        $this->assertFalse($this->makeRun()->canBeDisbursed(), 'صرفةٌ بلا اعتمادٍ لا تُصرف');
    }

    /**
     * حارسٌ على الشيفرة: الكرون يتخطّى غيرَ المعتمَد كحالةٍ متوقَّعة لا
     * كعطل — فالانتظارُ لا يجوز أن يملأ سجلَّ الأخطاء كلَّ يوم.
     */
    public function test_the_cron_treats_missing_approval_as_a_wait_not_a_failure(): void
    {
        $source = (string) file_get_contents(app_path('Console/Commands/ProcessPayrollRuns.php'));

        $checkAt = strpos($source, 'disbursementApprovalBlockReason()');
        $tryAt = strpos($source, 'try {', (int) strpos($source, '$dueRuns'));

        $this->assertNotFalse($checkAt, 'الكرون يجب أن يفحص اعتماد الصرف');
        $this->assertNotFalse($tryAt, 'كتلةُ الصرف مفقودة');
        $this->assertLessThan($tryAt, $checkAt, 'الفحصُ قبل كتلة المحاولة لا داخل catch');
        $this->assertStringContainsString('awaiting', $source, 'يُعَدُّ المنتظِرُ ويُبلَّغ عنه');
    }
}
