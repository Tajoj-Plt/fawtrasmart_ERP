<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Services\PayrollCalculator;
use App\Services\PayrollDisbursementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * تكامل إدارة الموظفين والمرتبات: موظف → مظلة → قسيمة → توليد صرف → حساب صافٍ.
 *
 * لا ترحيل هنا: الهدف أن السلسلة تعمل حتى باب الصرف دون سند ولا قيد.
 */
class HrPayrollOperationsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
            هذا الملفُّ يقيس غيرَ بوّابة الاعتماد، فتُطفأ صراحةً ليبقى المقيسُ
            وحدَه في الصورة. واشتراطُ الاعتماد له اختبارُه:
            [PayrollDisbursementApprovalRequiredTest].
        */
        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('require_disbursement_approval')->default(false);
            $t->timestamps();
        });
        DB::table('payroll_settings')->insert(['require_disbursement_approval' => false]);
        PayrollSettings::forget();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('email')->nullable();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            $t->unsignedBigInteger('salary_payable_account_id')->nullable();
            $t->unsignedBigInteger('employee_advance_account_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('jop_title_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->string('status', 20)->default(Payroll::STATUS_ACTIVE);
            $t->timestamps();
        });

        Schema::create('payroll_employee', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('payroll_id');
            $t->unsignedBigInteger('employee_id');
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->unsignedBigInteger('payroll_id')->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->string('status')->default(SalarySlip::STATUS_DRAFT);
            $t->string('frequency', 20)->default(SalarySlip::FREQUENCY_MONTHLY);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->tinyInteger('scheduled_payment_day')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->nullable();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default(PayrollRun::STATUS_PENDING);
            $t->string('trigger_source', 20)->nullable();
            $t->timestamp('held_at')->nullable();
            $t->unsignedBigInteger('held_by')->nullable();
            $t->text('hold_reason')->nullable();
            $t->unsignedBigInteger('payroll_batch_id')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_run_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('payroll_run_id');
            $t->string('item_type', 32);
            $t->string('source_table')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('name')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('salary_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slips_id')->nullable();
            $t->string('name')->nullable();
            $t->tinyInteger('type')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->unsignedBigInteger('account_id')->nullable();
            $t->timestamps();
        });

        Schema::create('deductions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('deduction_type', 40)->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('settled_amount', 15, 2)->default(0);
            $t->string('status', 32)->default(Deduction::STATUS_APPROVED);
            $t->date('date')->nullable();
            $t->text('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('salary_advances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->boolean('pay_from_salary')->nullable();
            $t->integer('paid_installments')->default(0);
            $t->integer('total_installments')->default(0);
            $t->timestamps();
        });

        Schema::create('salary_advance_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->string('status')->nullable();
            $t->boolean('is_settled')->default(false);
            $t->timestamps();
        });

        Schema::create('serial_settings', function (Blueprint $t) {
            $t->id();
            $t->string('section');
            $t->unsignedBigInteger('current_number')->default(0);
            $t->unsignedInteger('number_of_digits')->default(6);
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

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->decimal('amount', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->timestamps();
        });
    }

    public function test_inactive_employee_cannot_generate_runs(): void
    {
        $employee = $this->employee(EmploymentStatus::SUSPENDED->value);
        [$payroll, $slip] = $this->umbrellaAndSlip($employee, 3000);

        $this->assertFalse($slip->canGenerateRuns());
        $this->assertNull(app(PayrollDisbursementService::class)->generateNextRun($slip));
        $this->assertSame(0, PayrollRun::query()->count());
    }

    public function test_inactive_umbrella_blocks_run_generation(): void
    {
        $employee = $this->employee();
        [$payroll, $slip] = $this->umbrellaAndSlip($employee, 3000);
        $payroll->update(['status' => Payroll::STATUS_INACTIVE]);

        $this->assertFalse($slip->fresh()->canGenerateRuns());
        $this->assertNull(app(PayrollDisbursementService::class)->generateNextRun($slip->fresh()));
    }

    public function test_active_employee_under_umbrella_gets_a_pending_run(): void
    {
        $employee = $this->employee();
        [$payroll, $slip] = $this->umbrellaAndSlip($employee, 4500);

        $this->assertTrue($payroll->coversEmployee($employee->id));
        $this->assertTrue($slip->canGenerateRuns());

        $run = app(PayrollDisbursementService::class)->generateNextRun($slip);

        $this->assertNotNull($run);
        $this->assertSame(PayrollRun::STATUS_PENDING, $run->status);
        $this->assertSame($employee->id, $run->employee_id);
        $this->assertSame($slip->id, $run->salary_slip_id);
        $this->assertNotEmpty($run->code);
        $this->assertStringContainsString('PRN', $run->code);
        $this->assertTrue($run->canBeDisbursed());
    }

    public function test_approved_deduction_reduces_net_without_posting(): void
    {
        $employee = $this->employee();
        [, $slip] = $this->umbrellaAndSlip($employee, 4000);
        $run = app(PayrollDisbursementService::class)->generateNextRun($slip);

        Deduction::create([
            'employee_id' => $employee->id,
            'amount' => 250,
            'settled_amount' => 0,
            'status' => Deduction::STATUS_APPROVED,
            'deduction_type' => 'other',
            'date' => now()->toDateString(),
            'reason' => 'جزاء',
        ]);

        $calc = app(PayrollCalculator::class)->calculate($run, $slip, $employee);

        $this->assertEquals(4000.0, $calc->basic);
        $this->assertEquals(250.0, $calc->totalDeductions);
        $this->assertEquals(3750.0, $calc->netSalary);
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, JournalEntry::query()->count());
        $this->assertEquals(0.0, (float) Deduction::query()->value('settled_amount'));
    }

    public function test_holding_the_run_blocks_disbursement_after_generation(): void
    {
        $employee = $this->employee();
        [, $slip] = $this->umbrellaAndSlip($employee, 4000);
        $run = app(PayrollDisbursementService::class)->generateNextRun($slip);

        $run->hold('مراجعة يدوية');

        $this->assertFalse($run->fresh()->canBeDisbursed());
        $this->assertTrue($run->fresh()->isHeld());
    }

    private function employee(int $status = EmploymentStatus::ACTIVE->value): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid('', true).'@example.test',
            'status' => $status,
            'salary_payable_account_id' => 99,
        ]);
    }

    /** @return array{0: Payroll, 1: SalarySlip} */
    private function umbrellaAndSlip(Employee $employee, float $basic): array
    {
        $payroll = Payroll::create([
            'name' => 'مسير تكامل',
            'status' => Payroll::STATUS_ACTIVE,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $payroll->employees()->attach($employee->id);

        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'payroll_id' => $payroll->id,
            'basic_amount' => $basic,
            'status' => SalarySlip::STATUS_ACTIVE,
            'frequency' => SalarySlip::FREQUENCY_MONTHLY,
            'start_date' => now()->startOfMonth()->toDateString(),
            'scheduled_payment_day' => 1,
            'approved_at' => now(),
        ]);

        return [$payroll, $slip];
    }
}
