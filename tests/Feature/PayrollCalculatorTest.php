<?php

namespace Tests\Feature;

use App\Models\Deduction;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Services\PayrollAccountant;
use App\Services\PayrollCalculator;
use App\Services\PayrollDisbursementService;
use App\Services\PayrollSettlement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * تقسيم خدمة الصرف (§15 TODO 2.7 / §3.6): حساب بلا سند.
 *
 * كانت `PayrollDisbursementService` كتلةً واحدة: لا سبيل لحساب الصافي
 * دون إنشاء سند وقيدين وإشعار. الحساب في `PayrollCalculator`.
 */
class PayrollCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->unsignedBigInteger('employee_advance_account_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default(SalarySlip::STATUS_ACTIVE);
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
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
            $t->string('status', 32)->default('approved');
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

    public function test_the_three_services_exist_and_the_facade_wires_them(): void
    {
        $this->assertTrue(class_exists(PayrollCalculator::class));
        $this->assertTrue(class_exists(PayrollAccountant::class));
        $this->assertTrue(class_exists(PayrollSettlement::class));

        $facade = app(PayrollDisbursementService::class);
        $ref = new \ReflectionClass($facade);

        $this->assertTrue($ref->hasProperty('calculator'));
        $this->assertTrue($ref->hasProperty('accountant'));
        $this->assertTrue($ref->hasProperty('settlement'));
    }

    public function test_net_pay_is_computed_without_posting_an_expense_or_journal(): void
    {
        $employee = Employee::create(['first_name' => 'سامي', 'status' => 1]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
            'basic_amount' => 5000,
        ]);
        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'scheduled_date' => now()->toDateString(),
            'status' => PayrollRun::STATUS_PENDING,
            'basic_amount' => 5000,
        ]);

        $calc = app(PayrollCalculator::class)->calculate($run, $slip, $employee);

        $this->assertEquals(5000.0, $calc->basic);
        $this->assertEquals(5000.0, $calc->totalEarnings);
        $this->assertEquals(5000.0, $calc->netSalary);
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_a_deduction_reduces_net_without_settling_it(): void
    {
        $employee = Employee::create(['first_name' => 'ليلى', 'status' => 1]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
            'basic_amount' => 4000,
        ]);
        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'status' => PayrollRun::STATUS_PENDING,
        ]);

        Deduction::create([
            'employee_id' => $employee->id,
            'amount' => 400,
            'settled_amount' => 0,
            'status' => Deduction::STATUS_APPROVED,
            'deduction_type' => 'other',
            'date' => now()->toDateString(),
            'reason' => 'جزاء',
        ]);

        $calc = app(PayrollCalculator::class)->calculate($run, $slip, $employee);

        $this->assertEquals(3600.0, $calc->netSalary);
        $this->assertEquals(400.0, $calc->totalDeductions);
        $this->assertEquals(0.0, (float) Deduction::query()->value('settled_amount'));
    }
}
