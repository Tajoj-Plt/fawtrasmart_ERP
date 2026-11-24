<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollEmployee;
use App\Models\SalarySlip;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * حسم دور المسير (§15 TODO 2.8 / §14.10): مظلة زمنية لا كيان صرف.
 *
 * `payrolls` + `payroll_employee` يغطيان من ومتى. الصرف في `payroll_runs`.
 * اعتماد صرفات جملةً في `payroll_batches`.
 */
class PayrollUmbrellaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->tinyInteger('status')->default(1);
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
            $t->string('status', 20)->default('active');
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
            $t->unsignedBigInteger('payroll_id')->nullable();
            $t->string('status')->default(SalarySlip::STATUS_DRAFT);
            $t->timestamps();
        });
    }

    public function test_the_payroll_model_is_an_umbrella_not_a_pay_event(): void
    {
        $fillable = (new Payroll)->getFillable();

        foreach (['expense_id', 'journal_entry_id', 'net_salary', 'disbursed_at'] as $payField) {
            $this->assertNotContains($payField, $fillable);
        }

        $this->assertContains('name', $fillable);
        $this->assertContains('start_date', $fillable);
        $this->assertContains('end_date', $fillable);

        $source = (string) file_get_contents(app_path('Models/Payroll.php'));
        $this->assertStringContainsString('مظلة زمنية', $source);
        $this->assertStringContainsString('لا عملية صرف', $source);
    }

    public function test_payroll_employee_is_membership_only(): void
    {
        $this->assertSame(['payroll_id', 'employee_id'], (new PayrollEmployee)->getFillable());
    }

    public function test_explicit_membership_covers_the_employee(): void
    {
        $inside = Employee::create(['first_name' => 'داخل']);
        $outside = Employee::create(['first_name' => 'خارج']);
        $payroll = Payroll::create([
            'name' => 'مسير المبيعات',
            'status' => Payroll::STATUS_ACTIVE,
        ]);
        $payroll->employees()->attach($inside->id);

        $this->assertTrue($payroll->coversEmployee($inside->id));
        $this->assertFalse($payroll->coversEmployee($outside->id));
    }

    public function test_the_slip_belongs_to_the_umbrella_not_the_run(): void
    {
        $employee = Employee::create(['first_name' => 'سامي']);
        $payroll = Payroll::create(['name' => 'مظلة', 'status' => Payroll::STATUS_ACTIVE]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'payroll_id' => $payroll->id,
            'status' => SalarySlip::STATUS_DRAFT,
        ]);

        $this->assertSame($payroll->id, $slip->payroll->id);
        $this->assertTrue($payroll->salarySlips->contains($slip));
    }

    public function test_approving_the_umbrella_does_not_post_journals(): void
    {
        $controller = file_get_contents(
            base_path('Modules/HR/Http/Controllers/Salaries/PayrollProcessController.php')
        );
        $start = strpos($controller, 'function approve($id)');
        $end = strpos($controller, 'function deactivate($id)');
        $approve = substr($controller, $start, $end - $start);

        $this->assertStringContainsString("'status' => Payroll::STATUS_ACTIVE", $approve);
        $this->assertStringNotContainsString('disburse', $approve);
        $this->assertStringNotContainsString('JournalEntry', $approve);
        $this->assertStringNotContainsString('Expense::', $approve);
    }

    public function test_disbursement_never_creates_a_payroll_umbrella(): void
    {
        $service = (string) file_get_contents(app_path('Services/PayrollDisbursementService.php'));

        $this->assertStringContainsString('PayrollRun::create', $service);
        $this->assertStringNotContainsString('Payroll::create', $service);
    }

    public function test_the_screens_state_the_umbrella_is_not_a_pay_event(): void
    {
        $index = (string) file_get_contents(
            base_path('Modules/HR/resources/views/salaries/payroll_ process/index.blade.php')
        );
        $show = (string) file_get_contents(
            base_path('Modules/HR/resources/views/salaries/payroll_ process/show.blade.php')
        );

        $this->assertStringContainsString('مظلة زمنية', $index);
        $this->assertStringContainsString('لا سند صرف', $show);
    }
}
