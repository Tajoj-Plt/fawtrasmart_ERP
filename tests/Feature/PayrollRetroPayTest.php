<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Services\PayrollRetroPayService;
use App\Services\PayrollReversalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * تسوية رجعية (§15 TODO 2.7): فروقات زيادة على صرفة معلّقة بنوع بند مستقل.
 */
class PayrollRetroPayTest extends TestCase
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

    public function test_route_uses_retro_permission_and_payroll_plan(): void
    {
        $this->assertTrue(Route::has('payroll-runs.retro.add'));
        $this->assertTrue(Route::has('payroll-runs.retro.remove'));

        $add = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'payroll-runs.retro.add');
        $remove = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'payroll-runs.retro.remove');

        $this->assertContains('permission.role:salaries_payroll_retro', $add->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $add->gatherMiddleware());
        $this->assertContains('permission.role:salaries_payroll_retro', $remove->gatherMiddleware());
        $this->assertContains(
            'salaries_payroll_retro',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_adds_a_retro_item_on_a_pending_run(): void
    {
        $run = $this->pendingRun();

        $item = app(PayrollRetroPayService::class)->add(
            $run,
            'فروقات بدل سكن',
            750,
            'تصحيح يناير',
            '2026-01-01',
            '2026-01-31'
        );

        $this->assertSame(PayrollRunItem::TYPE_RETRO, $item->item_type);
        $this->assertSame('فروقات بدل سكن', $item->name);
        $this->assertEquals(750, (float) $item->amount);
        $this->assertSame('تسوية رجعية', $item->type_label);
        $this->assertStringContainsString('2026-01-01', (string) $item->description);
        $this->assertTrue($run->fresh()->canReceiveRetro());
        $this->assertSame(1, $run->retros()->count());
    }

    public function test_zero_amount_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        app(PayrollRetroPayService::class)->add($this->pendingRun(), 'بند', 0, 'سبب كاف');
    }

    public function test_disbursed_run_cannot_receive_retro(): void
    {
        $run = $this->pendingRun();
        $run->update(['status' => PayrollRun::STATUS_DISBURSED]);

        $this->expectException(\RuntimeException::class);
        app(PayrollRetroPayService::class)->add($run, 'بند', 100, 'بعد الصرف');
    }

    public function test_remove_deletes_only_the_retro_item(): void
    {
        $run = $this->pendingRun();
        $service = app(PayrollRetroPayService::class);
        $item = $service->add($run, 'فروقات', 200, 'تصحيح');
        PayrollRunItem::create([
            'payroll_run_id' => $run->id,
            'item_type' => PayrollRunItem::TYPE_ADDITION,
            'name' => 'بدل',
            'amount' => 50,
        ]);

        $service->remove($run, $item);

        $this->assertSame(0, $run->retros()->count());
        $this->assertSame(1, $run->additions()->count());
    }

    public function test_reversal_keeps_retro_items(): void
    {
        $run = $this->pendingRun();
        $run->update(['status' => PayrollRun::STATUS_DISBURSED, 'disbursed_at' => now()]);
        PayrollRunItem::create([
            'payroll_run_id' => $run->id,
            'item_type' => PayrollRunItem::TYPE_RETRO,
            'name' => 'فروقات',
            'amount' => 300,
            'description' => 'أثر رجعي',
        ]);
        PayrollRunItem::create([
            'payroll_run_id' => $run->id,
            'item_type' => PayrollRunItem::TYPE_ADDITION,
            'name' => 'أساسي',
            'amount' => 1000,
        ]);

        $out = app(PayrollReversalService::class)->reverse($run, 'تصحيح القيد', Auth::id());

        $this->assertSame(PayrollRun::STATUS_PENDING, $out->status);
        $this->assertSame(1, $out->retros()->count());
        $this->assertSame(0, $out->additions()->count());
        $this->assertEquals(300, (float) $out->retros()->first()->amount);
    }

    public function test_disbursement_service_folds_retro_into_earnings(): void
    {
        $source = file_get_contents(app_path('Services/PayrollCalculator.php'));
        $code = preg_replace('#/\*.*?\*/#s', '', $source);
        $code = preg_replace('#//.*$#m', '', (string) $code);

        $this->assertStringContainsString('TYPE_RETRO', (string) $code);
        $this->assertStringContainsString('$totalRetro', (string) $code);
        $this->assertStringContainsString('$basic + $totalAdditions + $totalRetro', (string) $code);
    }

    private function pendingRun(): PayrollRun
    {
        $employee = Employee::create(['first_name' => 'سامي', 'status' => 1]);

        return PayrollRun::create([
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'net_salary' => 0,
            'status' => PayrollRun::STATUS_PENDING,
        ]);
    }
}
