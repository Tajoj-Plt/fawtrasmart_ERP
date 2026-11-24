<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\PayrollReportController;
use Tests\TestCase;

/**
 * كشفُ حساب الموظف ومطابقةُ الرواتب مع المحاسبة (§11.3 / TODO 3.3).
 *
 * المصدرُ المحاسبي `journal_entry_details` على حسابَي المستحقات والسلف،
 * والمطابقة بين عمليات `payroll_runs` المصروفة ومدين حساب مصروف الرواتب.
 */
class PayrollAccountingReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayrollSettings::forget();

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

        Schema::create('accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->string('code')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
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
            $t->unsignedBigInteger('salary_payable_account_id')->nullable();
            $t->unsignedBigInteger('employee_advance_account_id')->nullable();
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
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedBigInteger('accrual_journal_entry_id')->nullable();
            $t->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->date('date')->nullable();
            $t->string('description')->nullable();
            $t->string('reference_number')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedTinyInteger('status')->default(1);
            $t->timestamps();
        });

        Schema::create('journal_entry_details', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('journal_entry_id');
            $t->unsignedBigInteger('account_id');
            $t->string('description')->nullable();
            $t->decimal('debit', 15, 2)->default(0);
            $t->decimal('credit', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('salary_expense_account_id')->nullable();
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

    private function account(string $name): int
    {
        return (int) DB::table('accounts')->insertGetId(['name' => $name, 'code' => 'T'.uniqid()]);
    }

    private function journal(string $date, ?int $employeeId, string $reference, array $lines): int
    {
        $id = (int) DB::table('journal_entries')->insertGetId([
            'date' => $date,
            'description' => $reference,
            'reference_number' => $reference,
            'employee_id' => $employeeId,
            'status' => 1,
        ]);

        foreach ($lines as $line) {
            DB::table('journal_entry_details')->insert([
                'journal_entry_id' => $id,
                'account_id' => $line['account_id'],
                'description' => $line['description'] ?? $reference,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
            ]);
        }

        return $id;
    }

    private function periodRequest(array $extra = []): Request
    {
        return Request::create('/x', 'GET', $extra + [
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]);
    }

    public function test_statement_hides_another_employees_ledger_and_builds_a_running_balance(): void
    {
        $payable = $this->account('مستحقات سالم');
        $advance = $this->account('سلف سالم');
        $hiddenPayable = $this->account('مستحقات مخفي');

        $visible = $this->employee([
            'first_name' => 'ظاهر',
            'salary_payable_account_id' => $payable,
            'employee_advance_account_id' => $advance,
        ]);
        $hidden = $this->employee([
            'first_name' => 'مخفي',
            'salary_payable_account_id' => $hiddenPayable,
        ]);

        $this->journal('2026-07-15', $visible->id, 'OPEN', [
            ['account_id' => $payable, 'credit' => 100],
        ]);
        $this->journal('2026-08-10', $visible->id, 'SAL-ACC-1', [
            ['account_id' => $payable, 'credit' => 3400, 'description' => 'استحقاق راتب'],
        ]);
        $this->journal('2026-08-10', $visible->id, 'SAL-PAY-1', [
            ['account_id' => $payable, 'debit' => 3400, 'description' => 'صرف راتب'],
            ['account_id' => $advance, 'credit' => 100, 'description' => 'تسوية سلفة'],
        ]);
        $this->journal('2026-08-12', $hidden->id, 'SAL-ACC-HIDDEN', [
            ['account_id' => $hiddenPayable, 'credit' => 9000],
        ]);

        PayrollRun::create([
            'employee_id' => $visible->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => '2026-08-31',
            'basic_amount' => 3500,
            'total_additions' => 0,
            'total_deductions' => 100,
            'total_advances_deducted' => 100,
            'net_salary' => 3300,
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);

        $data = $this->controller([$visible->id])
            ->statement($this->periodRequest(['employee_id' => $visible->id]))
            ->getData();

        $this->assertCount(1, $data['summaries']);
        $this->assertSame('ظاهر', $data['summaries']->first()['employee']->first_name);
        $this->assertEquals(100, (float) $data['summaries']->first()['payable']['opening']);
        $this->assertEquals(3400, (float) $data['summaries']->first()['payable']['debit']);
        $this->assertEquals(3400, (float) $data['summaries']->first()['payable']['credit']);
        $this->assertEquals(100, (float) $data['summaries']->first()['payable']['closing']);
        $this->assertEquals(100, (float) $data['summaries']->first()['advance']['credit']);

        $this->assertCount(3, $data['ledger']);
        $this->assertSame('استحقاق', $data['ledger'][0]['nature']);
        $this->assertSame('صرف', $data['ledger'][1]['nature']);
        $this->assertSame('تسوية سلفة', $data['ledger'][2]['nature']);
        $this->assertEquals(100, (float) $data['ledger'][1]['balance']);

        $this->assertEquals(3500, (float) $data['operational']['earnings']);
        $this->assertEquals(100, (float) $data['operational']['deductions']);
        $this->assertEquals(3300, (float) $data['operational']['net']);
    }

    public function test_reconciliation_matches_posted_expense_and_flags_unposted_and_orphan_journals(): void
    {
        $expense = $this->account('رواتب الموظفين');
        $payable = $this->account('مستحقات');
        DB::table('payroll_settings')->insert(['salary_expense_account_id' => $expense]);
        PayrollSettings::forget();

        $employee = $this->employee([
            'first_name' => 'سالم',
            'salary_payable_account_id' => $payable,
        ]);
        $other = $this->employee(['first_name' => 'مخفي']);

        $accrual = $this->journal('2026-08-10', $employee->id, 'SAL-ACC-OK', [
            ['account_id' => $expense, 'debit' => 3400],
            ['account_id' => $payable, 'credit' => 3400],
        ]);
        $payment = $this->journal('2026-08-10', $employee->id, 'SAL-PAY-OK', [
            ['account_id' => $payable, 'debit' => 3400],
        ]);

        PayrollRun::create([
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => '2026-08-31',
            'basic_amount' => 3500,
            'total_additions' => 0,
            'total_deductions' => 100,
            'total_advances_deducted' => 100,
            'net_salary' => 3300,
            'status' => PayrollRun::STATUS_DISBURSED,
            'accrual_journal_entry_id' => $accrual,
            'journal_entry_id' => $payment,
        ]);

        PayrollRun::create([
            'employee_id' => $employee->id,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'scheduled_date' => '2026-07-31',
            'basic_amount' => 2000,
            'net_salary' => 2000,
            'status' => PayrollRun::STATUS_DISBURSED,
        ]);

        $this->journal('2026-08-20', $employee->id, 'ORPHAN', [
            ['account_id' => $expense, 'debit' => 50],
        ]);
        $this->journal('2026-08-21', $other->id, 'HIDDEN-ORPHAN', [
            ['account_id' => $expense, 'debit' => 999],
        ]);

        $data = $this->controller([$employee->id])
            ->reconciliation($this->periodRequest([
                'from_date' => '2026-07-01',
                'to_date' => '2026-08-31',
            ]))
            ->getData();

        $byState = $data['rows']->groupBy('state');
        $this->assertCount(1, $byState['matched']);
        $this->assertEquals(3400, (float) $byState['matched']->first()['payroll_expense']);
        $this->assertEquals(0, (float) $byState['matched']->first()['variance']);
        $this->assertCount(1, $byState['unposted']);

        $this->assertCount(1, $data['orphans']);
        $this->assertEquals(50, (float) $data['orphans']->first()->debit);
        $this->assertEquals(3400, (float) $data['totals']['posted_expense']);
        $this->assertEquals(50, (float) $data['totals']['orphans']);
    }

    public function test_reconciliation_flags_a_posted_amount_mismatch(): void
    {
        $expense = $this->account('رواتب');
        DB::table('payroll_settings')->insert(['salary_expense_account_id' => $expense]);
        PayrollSettings::forget();
        $employee = $this->employee();

        $accrual = $this->journal('2026-08-10', $employee->id, 'SAL-ACC-BAD', [
            ['account_id' => $expense, 'debit' => 1000],
        ]);

        PayrollRun::create([
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => '2026-08-31',
            'basic_amount' => 1500,
            'net_salary' => 1500,
            'status' => PayrollRun::STATUS_DISBURSED,
            'accrual_journal_entry_id' => $accrual,
        ]);

        $row = $this->controller()
            ->reconciliation($this->periodRequest())
            ->getData()['rows']
            ->first();

        $this->assertSame('variance', $row['state']);
        $this->assertEquals(500, (float) $row['variance']);
    }
}
