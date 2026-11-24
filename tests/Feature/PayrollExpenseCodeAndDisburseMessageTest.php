<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\SerialSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ترقيم سند صرف الراتب + رسالة منع الصرف الحقيقية.
 */
class PayrollExpenseCodeAndDisburseMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable()->unique();
            $t->decimal('amount', 15, 2)->default(0);
            $t->unsignedBigInteger('accounting_period_id')->nullable();
            $t->string('currency')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->timestamps();
        });

        Schema::create('serial_settings', function (Blueprint $t) {
            $t->id();
            $t->string('section')->unique();
            $t->unsignedBigInteger('current_number')->default(0);
            $t->unsignedInteger('number_of_digits')->default(4);
            $t->string('prefix')->nullable();
            $t->string('suffix')->nullable();
            $t->string('separator')->nullable();
            $t->string('mode')->nullable();
            $t->boolean('reset_yearly')->default(false);
            $t->string('reset_period')->nullable();
            $t->timestamp('last_reset_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

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
            $t->timestamp('held_at')->nullable();
            $t->string('hold_reason')->nullable();
            $t->unsignedBigInteger('payroll_batch_id')->nullable();
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->timestamps();
        });

        SerialSetting::create([
            'section' => 'expense',
            'current_number' => 7,
            'number_of_digits' => 4,
            'prefix' => 'EXP',
            'separator' => '-',
            'is_active' => true,
        ]);
    }

    public function test_generate_code_skips_an_existing_exp_0008(): void
    {
        \DB::table('expenses')->insert([
            'code' => 'EXP-0008',
            'amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // العدّاد عند 7 → التالي الطبيعي 8 لكنه محجوز → يتجاوزه
        $code = Expense::generateCode();

        $this->assertNotSame('EXP-0008', $code);
        $this->assertMatchesRegularExpression('/^EXP-\d+$/', $code);
        $this->assertFalse(\DB::table('expenses')->where('code', $code)->exists());
    }

    public function test_expired_slip_with_pending_run_is_revived_instead_of_blocking(): void
    {
        $employeeId = \DB::table('employees')->insertGetId([
            'first_name' => 'صالح',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slipId = \DB::table('salary_slips')->insertGetId([
            'employee_id' => $employeeId,
            'status' => SalarySlip::STATUS_EXPIRED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $run = PayrollRun::create([
            'salary_slip_id' => $slipId,
            'employee_id' => $employeeId,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'scheduled_date' => '2026-09-18',
            'status' => PayrollRun::STATUS_PENDING,
        ]);

        $reason = $run->disbursementBlockReason();

        $this->assertNotSame('القسيمة منتهية — لا يمكن الصرف', $reason);
        $this->assertSame(SalarySlip::STATUS_ACTIVE, SalarySlip::find($slipId)->status);
    }

    public function test_expired_slip_without_pending_runs_still_blocks(): void
    {
        $employeeId = \DB::table('employees')->insertGetId([
            'first_name' => 'صالح',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slipId = \DB::table('salary_slips')->insertGetId([
            'employee_id' => $employeeId,
            'status' => SalarySlip::STATUS_EXPIRED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $run = PayrollRun::create([
            'salary_slip_id' => $slipId,
            'employee_id' => $employeeId,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'scheduled_date' => '2026-09-18',
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);

        // run مصروف مسبقاً — لا معلَّق → revive لا يحدث؛ نفحص shouldExpire على قسيمة منتهية بلا معلَّق
        $slip = SalarySlip::find($slipId);
        $this->assertFalse($slip->hasUndisbursedRuns());
        $this->assertFalse($slip->reviveWhileUndisbursed());
        unset($run);
    }
}
