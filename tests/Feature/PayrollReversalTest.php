<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\AccountingPeriodSetting;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\PayrollDisbursementService;
use App\Services\PayrollReversalService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * عكس راتب مصروف (§15 TODO 2.3): قيد عكسي بلا حذف الأصلي.
 */
class PayrollReversalTest extends TestCase
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

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->timestamp('disbursed_at')->nullable();
            $t->unsignedBigInteger('disbursed_by')->nullable();
            $t->unsignedBigInteger('expense_id')->nullable();
            $t->unsignedBigInteger('journal_entry_id')->nullable();
            $t->unsignedBigInteger('accrual_journal_entry_id')->nullable();
            $t->timestamp('reversed_at')->nullable();
            $t->unsignedBigInteger('reversed_by')->nullable();
            $t->text('reversal_reason')->nullable();
            $t->unsignedBigInteger('reversal_journal_entry_id')->nullable();
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

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->string('reference_number')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->date('date')->nullable();
            $t->text('description')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->boolean('is_reversed')->default(false);
            $t->string('currency', 10)->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('salary_id')->nullable();
            $t->unsignedBigInteger('created_by_employee')->nullable();
            $t->string('reference_type')->nullable();
            $t->string('linking_type')->nullable();
            $t->unsignedBigInteger('accounting_period_id')->nullable();
            $t->timestamps();
        });

        Schema::create('journal_entry_details', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('journal_entry_id');
            $t->unsignedBigInteger('account_id')->nullable();
            $t->text('description')->nullable();
            $t->decimal('debit', 15, 2)->default(0);
            $t->decimal('credit', 15, 2)->default(0);
            $t->boolean('is_debit')->default(true);
            $t->timestamps();
        });

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('code')->nullable();
            $t->decimal('balance', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('deductions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('settled_amount', 15, 2)->default(0);
            $t->string('status', 32)->default('approved');
            $t->timestamp('settled_at')->nullable();
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('deduction_histories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('deduction_id');
            $t->string('action')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->text('description')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
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

    public function test_route_uses_reverse_permission_and_payroll_plan(): void
    {
        $this->assertTrue(Route::has('payroll-runs.reverse'));
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'payroll-runs.reverse');

        $this->assertContains('permission.role:salaries_payroll_reverse', $route->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $route->gatherMiddleware());
        $this->assertContains(
            'salaries_payroll_reverse',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_reverse_posts_opposite_lines_and_marks_the_original(): void
    {
        $run = $this->disbursedRun();
        $original = $this->journal($run, 'SAL-PAY-'.$run->id, 1000, 0);

        $run->update(['journal_entry_id' => $original->id]);

        $reversed = app(PayrollReversalService::class)->reverse($run, 'خطأ في المبلغ', Auth::id());

        $this->assertTrue($original->fresh()->is_reversed);
        $this->assertSame(PayrollRun::STATUS_PENDING, $reversed->status);
        $this->assertNotNull($reversed->reversed_at);
        $this->assertSame('خطأ في المبلغ', $reversed->reversal_reason);
        $this->assertNull($reversed->journal_entry_id);

        $reverseEntry = JournalEntry::query()->where('reference_number', 'REVERSE_SAL-PAY-'.$run->id)->first();
        $this->assertNotNull($reverseEntry);
        $line = JournalEntryDetail::query()->where('journal_entry_id', $reverseEntry->id)->first();
        $this->assertEquals(0, (float) $line->debit);
        $this->assertEquals(1000, (float) $line->credit);
    }

    public function test_pending_run_cannot_be_reversed(): void
    {
        $run = $this->disbursedRun();
        $run->update(['status' => PayrollRun::STATUS_PENDING, 'disbursed_at' => null]);

        $this->expectException(\RuntimeException::class);
        app(PayrollReversalService::class)->reverse($run, 'لا');
    }

    public function test_payroll_run_reverse_delegates_to_the_service(): void
    {
        $run = $this->disbursedRun();
        $this->journal($run, 'SAL-ACC-'.$run->id, 500, 0);
        $run->update(['accrual_journal_entry_id' => JournalEntry::query()->first()->id]);

        $out = $run->reverse('تصحيح', Auth::id());

        $this->assertSame(PayrollRun::STATUS_PENDING, $out->status);
        $this->assertTrue(JournalEntry::query()->where('is_reversed', true)->exists());
    }

    public function test_disbursement_service_reverse_matches_run_reverse(): void
    {
        $run = $this->disbursedRun();
        $out = app(PayrollDisbursementService::class)->reverse($run, 'تصحيح عبر الخدمة', Auth::id());

        $this->assertSame(PayrollRun::STATUS_PENDING, $out->status);
    }

    public function test_settled_deduction_is_reopened(): void
    {
        $run = $this->disbursedRun();
        $deduction = Deduction::create([
            'employee_id' => $run->employee_id,
            'amount' => 200,
            'settled_amount' => 200,
            'status' => Deduction::STATUS_SETTLED,
            'reason' => 'تأخير',
        ]);
        PayrollRunItem::create([
            'payroll_run_id' => $run->id,
            'item_type' => PayrollRunItem::TYPE_DEDUCTION,
            'source_table' => 'deductions',
            'source_id' => $deduction->id,
            'name' => 'خصم',
            'amount' => 200,
        ]);

        app(PayrollReversalService::class)->reverse($run, 'عكس الخصم', Auth::id());

        $deduction->refresh();
        $this->assertEquals(0, (float) $deduction->settled_amount);
        $this->assertSame(Deduction::STATUS_APPROVED, $deduction->status);
        $this->assertSame(0, PayrollRunItem::query()->where('payroll_run_id', $run->id)->count());
    }

    public function test_locked_period_blocks_reversal(): void
    {
        app()->bind(AccountingPeriodService::class, function () {
            return new class extends AccountingPeriodService
            {
                public function __construct() {}

                public function settings(): AccountingPeriodSetting
                {
                    return new AccountingPeriodSetting(
                        ['override_permission' => null] + AccountingPeriodSetting::DEFAULTS
                    );
                }

                public function blockReason(Carbon|string|null $date): ?string
                {
                    return 'الفترة مقفلة.';
                }
            };
        });

        $run = $this->disbursedRun();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('الفترة مقفلة.');
        app(PayrollReversalService::class)->reverse($run, 'لا يمر');
    }

    private function disbursedRun(): PayrollRun
    {
        $employee = Employee::create(['first_name' => 'سامي']);

        return PayrollRun::create([
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'net_salary' => 3000,
            'status' => PayrollRun::STATUS_DISBURSED,
            'disbursed_at' => now(),
        ]);
    }

    private function journal(PayrollRun $run, string $ref, float $debit, float $credit): JournalEntry
    {
        $entry = JournalEntry::create([
            'reference_number' => $ref,
            'reference_id' => $run->id,
            'date' => now()->toDateString(),
            'description' => 'صرف راتب',
            'status' => 1,
            'is_reversed' => false,
        ]);
        JournalEntryDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => 1,
            'description' => 'سطر',
            'debit' => $debit,
            'credit' => $credit,
            'is_debit' => $debit > 0,
        ]);

        return $entry;
    }
}
