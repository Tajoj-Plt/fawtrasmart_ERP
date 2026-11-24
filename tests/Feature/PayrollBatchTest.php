<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\PayrollBatchService;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * اعتماد صرفات جملةً (§15 TODO 2.8): مسير payroll_runs لا قسائم.
 */
class PayrollBatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('payroll_id')->nullable();
            $t->string('status')->default(SalarySlip::STATUS_ACTIVE);
            $t->timestamps();
        });

        Schema::create('payroll_batches', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('payroll_id')->nullable();
            $t->date('period_from')->nullable();
            $t->date('period_to')->nullable();
            $t->string('status', 20)->default('pending');
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('payroll_batch_id')->nullable();
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->timestamp('held_at')->nullable();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->timestamps();
        });

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    public function test_routes_use_approve_permission_and_payroll_plan(): void
    {
        $this->assertTrue(Route::has('hr.payroll-batches.store'));
        $this->assertTrue(Route::has('hr.payroll-batches.approve'));
        $this->assertTrue(Route::has('PayrollProcess.approveRuns'));

        $store = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'hr.payroll-batches.store');
        $approve = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'hr.payroll-batches.approve');
        $fromProcess = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'PayrollProcess.approveRuns');

        $this->assertContains('permission.role:salaries_payroll_approve', $store->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $store->gatherMiddleware());
        $this->assertContains('permission.role:salaries_payroll_approve', $approve->gatherMiddleware());
        $this->assertContains('permission.role:salaries_payroll_approve', $fromProcess->gatherMiddleware());
        $this->assertContains(
            'salaries_payroll_approve',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    /**
     * **قرارٌ انعكس عن قصد.**
     *
     * كان هذا الاختبار يُثبت أنّ «الصرف غير المجمّع لا يتغيّر» — أي أنّ
     * المسير أداةُ اعتمادٍ بالجملة اختياريةٌ لا بوابةُ تصريحٍ لازمة. وهو ما
     * كان يجعل الصرفَ يخرج نقداً بلا معتمِدٍ مسجَّلٍ لهذه الدفعة: صرفاتُ
     * الكرون تُنشأ بلا مسير، و`auto_disburse_payroll_runs` افتراضُه التفعيل.
     *
     * فصار الاعتمادُ مشترطاً في ذاته. والسلوكُ السابق يبقى قابلاً للتعبير
     * بمفتاحٍ في «إعدادات الرواتب» — انظر الاختبار الذي يليه.
     */
    public function test_an_unbatched_run_is_no_longer_disbursable(): void
    {
        $run = $this->dueRun();

        $this->assertFalse($run->canBeDisbursed(), 'صرفةٌ بلا مسيرٍ معتمدٍ لا تُصرف');
        $this->assertStringContainsString(
            'مسير صرف',
            (string) $run->disbursementApprovalBlockReason(),
            'الرسالةُ تدلّ على الشاشة المطلوبة'
        );
    }

    /** وإطفاءُ الاشتراط من الإعدادات يُرجع السلوكَ السابق لمن أراده صراحةً. */
    public function test_the_setting_restores_unbatched_disbursement(): void
    {
        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('require_disbursement_approval')->default(false);
            $t->timestamps();
        });
        DB::table('payroll_settings')->insert(['require_disbursement_approval' => false]);
        PayrollSettings::forget();

        $run = $this->dueRun();

        $this->assertTrue($run->canBeDisbursed());

        PayrollSettings::forget();
    }

    public function test_batched_unapproved_run_cannot_be_disbursed(): void
    {
        $run = $this->dueRun();
        $batch = app(PayrollBatchService::class)->create([$run->id], 'أغسطس');

        $this->assertTrue($batch->isPending());
        $this->assertFalse($run->fresh()->canBeDisbursed());
        $this->assertSame($batch->id, $run->fresh()->payroll_batch_id);
    }

    public function test_approving_the_batch_unlocks_disbursement(): void
    {
        $run = $this->dueRun();
        $service = app(PayrollBatchService::class);
        $batch = $service->create([$run->id], 'أغسطس');
        $service->approve($batch, Auth::id());

        $this->assertTrue($batch->fresh()->isApproved());
        $this->assertTrue($run->fresh()->canBeDisbursed());
        $this->assertNotNull($batch->fresh()->approved_at);
    }

    public function test_create_and_approve_is_one_step(): void
    {
        $run = $this->dueRun();
        $batch = app(PayrollBatchService::class)->createAndApprove([$run->id], 'دفعة');

        $this->assertTrue($batch->isApproved());
        $this->assertTrue($run->fresh()->canBeDisbursed());
    }

    public function test_empty_selection_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        app(PayrollBatchService::class)->create([]);
    }

    public function test_disbursed_run_cannot_join_a_batch(): void
    {
        $run = $this->dueRun();
        $run->update(['status' => PayrollRun::STATUS_DISBURSED]);

        $this->expectException(\RuntimeException::class);
        app(PayrollBatchService::class)->create([$run->id]);
    }

    public function test_run_cannot_join_a_second_batch(): void
    {
        $run = $this->dueRun();
        app(PayrollBatchService::class)->create([$run->id], 'أول');

        $this->expectException(\RuntimeException::class);
        app(PayrollBatchService::class)->create([$run->id], 'ثانٍ');
    }

    private function dueRun(): PayrollRun
    {
        $employee = Employee::create(['first_name' => 'سامي', 'status' => 1]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
        ]);

        return PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => now()->toDateString(),
            'net_salary' => 4000,
            'status' => PayrollRun::STATUS_PENDING,
        ]);
    }
}
