<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\HRReportsController;
use Tests\TestCase;

/**
 * تقارير HR للفهرس: قسائم + مدفوعات الصرف تقرأ جداول الموظفين/الرواتب الحقيقية.
 */
class HRReportsSalariesAndPaymentsRealDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('role_id')->nullable();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->decimal('total_salary', 15, 2)->nullable();
            $t->decimal('total_deductions', 15, 2)->nullable();
            $t->decimal('net_salary', 15, 2)->nullable();
            $t->date('start_date')->nullable();
            $t->date('slip_date')->nullable();
            $t->string('status')->default(SalarySlip::STATUS_ACTIVE);
            $t->string('currency')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->timestamp('disbursed_at')->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->decimal('total_additions', 15, 2)->default(0);
            $t->decimal('total_deductions', 15, 2)->default(0);
            $t->decimal('total_advances_deducted', 15, 2)->default(0);
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default(PayrollRun::STATUS_PENDING);
            $t->unsignedBigInteger('treasury_id')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.test']);
        Auth::login($user);
    }

    private function controller(): HRReportsController
    {
        return new class extends HRReportsController
        {
            protected function hrAccessibleEmployeeIds(): ?array
            {
                return null; // بلا قيد نطاق
            }
        };
    }

    public function test_salary_slips_report_reads_modern_basic_amount_and_start_date(): void
    {
        $dept = Department::create(['name' => 'المبيعات']);
        $employee = Employee::create([
            'first_name' => 'خالد',
            'email' => 'k@example.test',
            'department_id' => $dept->id,
            'status' => EmploymentStatus::ACTIVE->value,
        ]);

        SalarySlip::create([
            'employee_id' => $employee->id,
            'basic_amount' => 4500,
            'start_date' => '2026-09-01',
            'status' => SalarySlip::STATUS_ACTIVE,
        ]);

        $request = Request::create('/hr/reports/salaries', 'GET', [
            'fetch_data' => 1,
            'from_date' => '2026-09',
            'to_date' => '2026-09',
        ]);

        $view = $this->controller()->salariesByDepartment($request);
        $data = $view->getData();

        $this->assertSame(1, $data['salariesData']->count());
        $this->assertEqualsWithDelta(4500.0, $data['totals']['total_salary'], 0.01);
        $this->assertSame('خالد', $data['salariesData']->first()->employee_name);
        $this->assertSame('المبيعات', $data['salariesData']->first()->department_name);
    }

    public function test_payments_report_reads_disbursed_payroll_runs_not_invoice_payments(): void
    {
        $dept = Department::create(['name' => 'المحاسبة']);
        $employee = Employee::create([
            'first_name' => 'نورة',
            'email' => 'n@example.test',
            'department_id' => $dept->id,
            'status' => EmploymentStatus::ACTIVE->value,
        ]);

        PayrollRun::create([
            'code' => 'PRN-TEST-1',
            'employee_id' => $employee->id,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'disbursed_at' => '2026-09-28 10:00:00',
            'basic_amount' => 5000,
            'total_deductions' => 200,
            'total_advances_deducted' => 100,
            'net_salary' => 4700,
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);

        // صرف معلّق لا يدخل التقرير
        PayrollRun::create([
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'basic_amount' => 5000,
            'net_salary' => 5000,
            'status' => PayrollRun::STATUS_PENDING,
        ]);

        $request = Request::create('/hr/reports/payments', 'GET', [
            'fetch_data' => 1,
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
        ]);

        $view = $this->controller()->paymentsReport($request);
        $data = $view->getData();

        $this->assertSame(1, $data['paymentsData']->count());
        $this->assertEqualsWithDelta(4700.0, $data['totals']['total_amount'], 0.01);
        $this->assertEqualsWithDelta(200.0, $data['totals']['total_deductions'], 0.01);
        $this->assertSame('نورة', $data['paymentsData']->first()->effective_employee_name);
        $this->assertStringContainsString('payroll_runs', file_get_contents(
            (new \ReflectionClass(HRReportsController::class))->getFileName()
        ));
        $this->assertStringNotContainsString('payments_process', file_get_contents(
            (new \ReflectionClass(HRReportsController::class))->getFileName()
        ));
    }
}
