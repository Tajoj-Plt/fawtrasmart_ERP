<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\User;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * إيقاف راتب معلّق (§15 TODO 2.6): قرار إداري يمنع الصرف دون إلغاء العملية.
 */
class PayrollHoldTest extends TestCase
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
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default(SalarySlip::STATUS_ACTIVE);
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->date('scheduled_date')->nullable();
            $t->decimal('net_salary', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->timestamp('held_at')->nullable();
            $t->unsignedBigInteger('held_by')->nullable();
            $t->text('hold_reason')->nullable();
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

    public function test_route_uses_hold_permission_and_payroll_plan(): void
    {
        $this->assertTrue(Route::has('payroll-runs.hold'));
        $this->assertTrue(Route::has('payroll-runs.release'));

        $hold = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'payroll-runs.hold');
        $release = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'payroll-runs.release');

        $this->assertContains('permission.role:salaries_payroll_hold', $hold->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $hold->gatherMiddleware());
        $this->assertContains('permission.role:salaries_payroll_hold', $release->gatherMiddleware());
        $this->assertContains('plan:hr_payroll', $release->gatherMiddleware());
        $this->assertContains(
            'salaries_payroll_hold',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_hold_blocks_disbursement_and_due_scope(): void
    {
        $run = $this->pendingDueRun();

        $this->assertTrue($run->canBeDisbursed());
        $this->assertTrue($run->canBeHeld());
        $this->assertSame(1, PayrollRun::due()->count());

        $held = $run->hold('نزاع على البدلات', Auth::id());

        $this->assertTrue($held->isHeld());
        $this->assertFalse($held->canBeDisbursed());
        $this->assertFalse($held->canBeHeld());
        $this->assertTrue($held->canBeReleased());
        $this->assertSame(PayrollRun::STATUS_PENDING, $held->status);
        $this->assertSame('نزاع على البدلات', $held->hold_reason);
        $this->assertSame('موقوف', $held->status_label);
        $this->assertSame(0, PayrollRun::due()->count());
        $this->assertSame(1, PayrollRun::held()->count());
    }

    public function test_release_restores_disbursement(): void
    {
        $run = $this->pendingDueRun()->hold('انتظار تحقيق', Auth::id());

        $released = $run->release();

        $this->assertFalse($released->isHeld());
        $this->assertTrue($released->canBeDisbursed());
        $this->assertNull($released->held_at);
        $this->assertNull($released->hold_reason);
        $this->assertSame(1, PayrollRun::due()->count());
    }

    public function test_disbursed_run_cannot_be_held(): void
    {
        $run = $this->pendingDueRun();
        $run->update(['status' => PayrollRun::STATUS_DISBURSED]);

        $this->expectException(\RuntimeException::class);
        $run->hold('بعد الصرف');
    }

    public function test_empty_reason_is_rejected(): void
    {
        $run = $this->pendingDueRun();

        $this->expectException(\RuntimeException::class);
        $run->hold('   ');
    }

    public function test_release_on_unheld_run_fails(): void
    {
        $run = $this->pendingDueRun();

        $this->expectException(\RuntimeException::class);
        $run->release();
    }

    private function pendingDueRun(): PayrollRun
    {
        $employee = Employee::create(['first_name' => 'سامي', 'status' => 1]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'status' => SalarySlip::STATUS_ACTIVE,
        ]);

        return PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'scheduled_date' => now()->toDateString(),
            'net_salary' => 4000,
            'status' => PayrollRun::STATUS_PENDING,
        ]);
    }
}
