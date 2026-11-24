<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * القسيمة لا تُنهى وفيها صرف معلَّق — تبقى نشطة حتى تُصرَف الرواتب المتبقية.
 */
class SalarySlipStaysActiveWithPendingRunsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default(SalarySlip::STATUS_ACTIVE);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('stop_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from')->nullable();
            $t->date('period_to')->nullable();
            $t->date('scheduled_date')->nullable();
            $t->string('status', 20)->default(PayrollRun::STATUS_PENDING);
            $t->timestamps();
        });
    }

    public function test_should_not_expire_while_a_pending_run_remains(): void
    {
        $employee = Employee::create([
            'first_name' => 'صالح',
            'status' => EmploymentStatus::ACTIVE->value,
        ]);

        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31', // انتهت منذ أشهر
        ]);

        PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-01-01',
            'period_to' => '2026-01-31',
            'scheduled_date' => '2026-01-18',
            'status' => PayrollRun::STATUS_PENDING,
        ]);

        $this->assertTrue($slip->hasUndisbursedRuns());
        $this->assertNull($slip->shouldExpire(), 'لا تُنهى وفيها صرف معلَّق');
    }

    public function test_expires_only_after_pending_runs_are_gone(): void
    {
        $employee = Employee::create([
            'first_name' => 'صالح',
            'status' => EmploymentStatus::ACTIVE->value,
        ]);

        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-01-01',
            'period_to' => '2026-01-31',
            'scheduled_date' => '2026-01-18',
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);

        $this->assertFalse($slip->hasUndisbursedRuns());
        $this->assertSame('انتهت فترة القسيمة', $slip->shouldExpire());
    }

    public function test_expired_slip_revives_when_pending_run_exists(): void
    {
        $employee = Employee::create([
            'first_name' => 'صالح',
            'status' => EmploymentStatus::ACTIVE->value,
        ]);

        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_EXPIRED,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'stop_reason' => 'انتهت فترة القسيمة',
        ]);

        PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'scheduled_date' => '2026-09-18',
            'status' => PayrollRun::STATUS_PENDING,
        ]);

        $this->assertTrue($slip->reviveWhileUndisbursed());
        $this->assertSame(SalarySlip::STATUS_ACTIVE, $slip->fresh()->status);
    }
}
