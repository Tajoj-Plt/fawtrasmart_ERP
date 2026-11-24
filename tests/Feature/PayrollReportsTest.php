<?php

namespace Tests\Feature;

use App\Models\Deduction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Controllers\PayrollReportController;
use Tests\TestCase;

/**
 * تقاريرُ الرواتب (§11.3) على `payroll_runs` — حقيقةُ الصرف.
 *
 * التقريرُ القائم `salariesByDepartment` يقرأ `salary_slips` (اتفاقيةً
 * مستمرة) فيسمّي «رواتب حسب القسم» ما ليس صرفاً. هنا الصرفُ الفعلي.
 *
 * التصديرُ الموحَّد مؤجَّل إلى 3.4.
 */
class PayrollReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('departments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('branches', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->decimal('total_additions', 15, 2)->default(0);
            $t->decimal('total_deductions', 15, 2)->default(0);
            $t->decimal('total_advances_deducted', 15, 2)->default(0);
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->string('trigger_source', 20)->default('manual');
            $t->timestamps();
        });

        Schema::create('payroll_run_items', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('payroll_run_id');
            $t->string('item_type', 20);
            $t->string('name');
            $t->decimal('amount', 15, 2);
            $t->timestamps();
        });

        Schema::create('deductions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('deduction_type', 40)->default('other');
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('settled_amount', 15, 2)->default(0);
            $t->string('status', 40)->default('pending');
            $t->date('date')->nullable();
            $t->timestamps();
        });

        Schema::create('deduction_histories', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('deduction_id');
            $t->string('action')->nullable();
            $t->timestamps();
        });

        Schema::create('salary_advances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('submission_date')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('installment_payments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('salary_advance_id');
            $t->decimal('amount', 15, 2)->default(0);
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('salary_advance_requests', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('settled_amount', 15, 2)->default(0);
            $t->string('status', 40)->default('approved');
            $t->boolean('is_settled')->default(false);
            $t->date('requested_date')->nullable();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    private function controller(?array $onlyIds = null): PayrollReportController
    {
        return new class($onlyIds) extends PayrollReportController
        {
            public function __construct(private ?array $onlyIds) {}

            protected function accessibleEmployeeIds(): ?array
            {
                return $this->onlyIds;
            }
        };
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create($attributes + [
            'first_name' => 'سالم',
            'email' => 'e'.uniqid('', true).'@example.test',
        ]);
    }

    private function runFor(Employee $employee, array $attributes = []): PayrollRun
    {
        return PayrollRun::create($attributes + [
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => '2026-08-31',
            'basic_amount' => 5000,
            'total_additions' => 500,
            'total_deductions' => 200,
            'total_advances_deducted' => 100,
            'net_salary' => 5200,
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);
    }

    private function periodRequest(array $extra = []): Request
    {
        return Request::create('/x', 'GET', $extra + [
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
        ]);
    }

    public function test_every_catalog_report_has_a_named_route(): void
    {
        foreach (PayrollReportController::catalog() as $tile) {
            $this->assertTrue(Route::has($tile['route']), "المسار {$tile['route']} غير مسجَّل");
        }
    }

    public function test_each_action_is_guarded_by_the_catalog_permission(): void
    {
        $actions = [
            'summary' => 'salaries_payroll_view',
            'byPeriod' => 'salaries_payroll_view',
            'byDepartment' => 'hr_reports_view',
            'components' => 'salaries_payroll_view',
            'allowances' => 'salaries_payroll_view',
            'netSalary' => 'salaries_payroll_view',
            'disbursementStatus' => 'salaries_payroll_view',
            'deductions' => 'deductions_view',
            'payslips' => 'hr_payslips_view',
            'advances' => 'salaries_loans_manage',
            'statement' => 'salaries_payroll_view',
            'reconciliation' => 'salaries_payroll_view',
        ];

        foreach (PayrollReportController::catalog() as $tile) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => $r->getName() === $tile['route']);
            $this->assertNotNull($route, $tile['route']);

            $method = explode('@', class_basename($route->getActionName()))[1] ?? '';
            $this->assertSame($actions[$method], $tile['permission'], $method);

            $guards = array_values(array_filter(
                $route->gatherMiddleware(),
                fn ($m) => is_string($m) && str_starts_with($m, 'permission.role:')
            ));
            $this->assertContains('permission.role:'.$tile['permission'], $guards, $tile['route']);
        }
    }

    public function test_the_report_routes_sit_behind_the_reports_plan(): void
    {
        $middleware = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'hr.reports.payroll.summary')
            ->gatherMiddleware();

        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => is_string($m) && str_contains($m, 'hr_reports'))
        );
    }

    public function test_the_controller_applies_the_employee_scope_trait(): void
    {
        $this->assertContains(
            ScopesToAccessibleEmployees::class,
            array_values(class_uses_recursive(PayrollReportController::class))
        );
    }

    public function test_run_queries_go_through_the_scope(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(PayrollReportController::class))->getFileName()
        );

        $this->assertGreaterThanOrEqual(4, preg_match_all('/\$this->scopeToEmployees\(/', $source));
        $this->assertGreaterThanOrEqual(1, preg_match_all('/\$this->scopeAnyQueryToEmployees\(/', $source));
        $this->assertStringNotContainsString('PayrollRun::all()', $source);
    }

    public function test_the_index_lists_the_payroll_catalog(): void
    {
        $index = (string) file_get_contents(module_path('HR', 'resources/views/reports/index.blade.php'));
        $this->assertStringContainsString('PayrollReportController::catalog()', $index);
    }

    public function test_summary_is_limited_to_visible_employees_and_totals_the_net(): void
    {
        $visible = $this->employee(['first_name' => 'ظاهر']);
        $hidden = $this->employee(['first_name' => 'مخفي']);
        $this->runFor($visible, ['net_salary' => 1000]);
        $this->runFor($hidden, ['net_salary' => 9000]);

        $data = $this->controller([$visible->id])->summary($this->periodRequest())->getData();

        $this->assertCount(1, $data['runs']);
        $this->assertEquals(1000, (float) $data['totals']['net']);
        $this->assertSame('ظاهر', $data['runs']->first()->employee->first_name);
    }

    public function test_runs_are_grouped_by_department(): void
    {
        $sales = Department::create(['name' => 'المبيعات']);
        $hr = Department::create(['name' => 'الموارد']);
        $a = $this->employee(['department_id' => $sales->id]);
        $b = $this->employee(['department_id' => $sales->id]);
        $c = $this->employee(['department_id' => $hr->id]);
        $this->runFor($a, ['net_salary' => 100]);
        $this->runFor($b, ['net_salary' => 200]);
        $this->runFor($c, ['net_salary' => 50]);

        $groups = $this->controller()->byDepartment($this->periodRequest())->getData()['groups']->keyBy('name');

        $this->assertSame(2, $groups['المبيعات']['count']);
        $this->assertEquals(300, (float) $groups['المبيعات']['net']);
        $this->assertSame(1, $groups['الموارد']['count']);
    }

    public function test_allowances_group_addition_items_by_name(): void
    {
        $employee = $this->employee();
        $run = $this->runFor($employee);
        PayrollRunItem::create(['payroll_run_id' => $run->id, 'item_type' => 'addition', 'name' => 'بدل سكن', 'amount' => 800]);
        PayrollRunItem::create(['payroll_run_id' => $run->id, 'item_type' => 'addition', 'name' => 'بدل سكن', 'amount' => 200]);
        PayrollRunItem::create(['payroll_run_id' => $run->id, 'item_type' => 'deduction', 'name' => 'غياب', 'amount' => 50]);

        $groups = $this->controller()->allowances($this->periodRequest())->getData()['groups']->keyBy('name');

        $this->assertSame(2, $groups['بدل سكن']['count']);
        $this->assertEquals(1000, (float) $groups['بدل سكن']['amount']);
        $this->assertArrayNotHasKey('غياب', $groups->all());
    }

    public function test_disbursement_status_splits_pending_from_disbursed(): void
    {
        $employee = $this->employee();
        $this->runFor($employee, ['status' => PayrollRun::STATUS_PENDING, 'net_salary' => 10]);
        $this->runFor($employee, [
            'status' => PayrollRun::STATUS_DISBURSED,
            'net_salary' => 20,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
        ]);

        $groups = $this->controller()->disbursementStatus($this->periodRequest())->getData()['groups']->keyBy('status');

        $this->assertSame(1, $groups[PayrollRun::STATUS_PENDING]['count']);
        $this->assertSame(1, $groups[PayrollRun::STATUS_DISBURSED]['count']);
        $this->assertEquals(20, (float) $groups[PayrollRun::STATUS_DISBURSED]['net']);
    }

    public function test_deductions_report_filters_by_type_and_shows_remaining(): void
    {
        $employee = $this->employee();
        Deduction::create([
            'employee_id' => $employee->id,
            'deduction_type' => Deduction::TYPE_LATE,
            'amount' => 100,
            'settled_amount' => 40,
            'status' => Deduction::STATUS_PARTIALLY_SETTLED,
            'date' => '2026-06-01',
        ]);
        Deduction::create([
            'employee_id' => $employee->id,
            'deduction_type' => Deduction::TYPE_ABSENCE,
            'amount' => 200,
            'settled_amount' => 0,
            'status' => Deduction::STATUS_APPROVED,
            'date' => '2026-06-02',
        ]);

        $rows = $this->controller()->deductions(Request::create('/x', 'GET', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
            'deduction_type' => Deduction::TYPE_LATE,
        ]))->getData()['deductions'];

        $this->assertCount(1, $rows);
        $this->assertEquals(60, (float) $rows->first()->remaining_amount);
    }

    public function test_advances_report_computes_remaining_from_both_systems(): void
    {
        $employee = $this->employee();

        $legacy = SalaryAdvance::create([
            'employee_id' => $employee->id,
            'amount' => 1000,
            'submission_date' => '2026-03-01',
        ]);
        $legacy->payments()->create(['amount' => 250]);

        SalaryAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => 400,
            'settled_amount' => 400,
            'is_settled' => true,
            'status' => 'approved',
            'requested_date' => '2026-04-01',
        ]);

        $data = $this->controller()->advances(Request::create('/x', 'GET', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
        ]))->getData();

        $this->assertCount(2, $data['rows']);
        $this->assertEquals(1400, (float) $data['totals']['amount']);
        $this->assertEquals(650, (float) $data['totals']['paid']);
        $this->assertEquals(750, (float) $data['totals']['remaining']);
    }

    public function test_the_statement_requires_the_accounting_permission_as_well(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'hr.reports.payroll.statement');

        $guards = array_values(array_filter(
            $route->gatherMiddleware(),
            fn ($m) => is_string($m) && str_starts_with($m, 'permission.role:')
        ));

        $this->assertContains('permission.role:salaries_payroll_view', $guards);
        $this->assertContains('permission.role:account.statement.view', $guards);
    }

    public function test_reconciliation_and_ledger_are_in_the_catalog(): void
    {
        $routes = collect(PayrollReportController::catalog())->pluck('route');

        $this->assertTrue($routes->contains('hr.reports.payroll.statement'));
        $this->assertTrue($routes->contains('hr.reports.payroll.reconciliation'));
    }
}
