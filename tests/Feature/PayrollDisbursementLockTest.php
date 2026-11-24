<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Services\PayrollDisbursementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * قفل صفّ الصرف عند التنفيذ (§15 TODO 2.6 / الميزة 1.10).
 *
 * العكس والإيقاف والمسير يقفلون الصفّ سلفاً. الصرف كان يفحص
 * canBeDisbursed خارج المعاملة ثم يكتب السند والقيدين بلا قفل —
 * فنقرة مزدوجة أو كرون موازٍ ينشئان صرفين لنفس الـ run.
 */
class PayrollDisbursementLockTest extends TestCase
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
            $t->string('status')->default(SalarySlip::STATUS_ACTIVE);
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->timestamp('held_at')->nullable();
            $t->unsignedBigInteger('payroll_batch_id')->nullable();
            $t->timestamps();
        });
    }

    public function test_disburse_locks_the_run_before_any_write(): void
    {
        $source = (string) file_get_contents(
            base_path('app/Services/PayrollDisbursementService.php')
        );

        $start = strpos($source, 'function disburse(');
        $end = strpos($source, 'function reverse(');

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $disburse = substr($source, $start, $end - $start);

        $this->assertStringContainsString('lockForUpdate()', $disburse);
        $this->assertStringContainsString('DB::transaction', $disburse);

        $lockAt = strpos($disburse, 'lockForUpdate()');
        $writeAt = strpos($disburse, '$run->update');

        $this->assertNotFalse($lockAt);
        $this->assertNotFalse($writeAt);
        $this->assertLessThan($writeAt, $lockAt, 'القفل يجب أن يسبق أي تحديث على الصرفة');
    }

    public function test_cron_and_controller_disburse_through_the_service(): void
    {
        $cron = (string) file_get_contents(base_path('app/Console/Commands/ProcessPayrollRuns.php'));
        $controller = (string) file_get_contents(
            base_path('Modules/HR/Http/Controllers/Salaries/PayrollRunController.php')
        );

        $this->assertStringContainsString('$service->disburse(', $cron);
        $this->assertStringContainsString('$service->disburse(', $controller);
        $this->assertStringNotContainsString('lockForUpdate', $controller);
    }

    public function test_a_disbursed_run_is_rejected_after_the_lock(): void
    {
        $employee = Employee::create(['first_name' => 'سامي', 'status' => 1]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
        ]);
        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'scheduled_date' => now()->toDateString(),
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن صرف');

        app(PayrollDisbursementService::class)->disburse($run);
    }

    public function test_a_held_run_is_rejected_after_the_lock(): void
    {
        $employee = Employee::create(['first_name' => 'ليلى', 'status' => 1]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
        ]);
        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'scheduled_date' => now()->toDateString(),
            'status' => PayrollRun::STATUS_PENDING,
            'held_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن صرف');

        app(PayrollDisbursementService::class)->disburse($run);
    }
}
