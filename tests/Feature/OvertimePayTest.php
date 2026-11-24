<?php

namespace Tests\Feature;

use App\Constants\permissions\HrPermissions;
use App\Models\AttendanceDays;
use App\Models\Employee;
use App\Models\OvertimeRule;
use App\Models\PayrollRun;
use App\Models\SalaryComponentType;
use App\Models\SalaryItem;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\OvertimePayService;
use App\Support\PayrollSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * الأجر الإضافي (§15 TODO 4.2) من `attendance_days.overtime_minutes`.
 *
 * الدقائق تُحسب في الحضور مسبقاً. هنا التحويل إلى بند استحقاق على القسيمة
 * يلتقطه `additionItems()` عند الصرف. معطّل حتى يُفعَّل من الإعدادات.
 */
class OvertimePayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayrollSettings::forget();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('email')->nullable();
            $t->tinyInteger('status')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('salary_slip_id')->nullable();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_from');
            $t->date('period_to');
            $t->decimal('basic_amount', 15, 2)->default(0);
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });

        Schema::create('salary_component_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->unsignedTinyInteger('kind');
            $t->boolean('is_insurable')->default(false);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_system')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedTinyInteger('status')->default(1);
            $t->timestamps();
        });

        Schema::create('salary_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->tinyInteger('type')->nullable()->default(1);
            $t->unsignedBigInteger('component_type_id')->nullable();
            $t->string('origin', 32)->nullable();
            $t->tinyInteger('status')->nullable()->default(1);
            $t->tinyInteger('salary_item_value')->nullable()->default(1);
            $t->decimal('amount', 10)->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('salary_slips_id')->nullable();
            $t->timestamps();
        });

        Schema::create('overtime_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name');
            $t->string('applies_on', 16);
            $t->decimal('multiplier', 6, 2)->default(1.5);
            $t->decimal('hourly_rate', 15, 2)->nullable();
            $t->unsignedInteger('max_minutes_per_day')->nullable();
            $t->boolean('is_system')->default(true);
            $t->unsignedTinyInteger('status')->default(1);
            $t->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->boolean('overtime_enabled')->default(false);
            $t->decimal('overtime_monthly_hours', 8, 2)->default(240);
            $t->decimal('overtime_hourly_rate', 15, 2)->nullable();
            $t->unsignedInteger('overtime_max_minutes_per_period')->nullable();
            $t->unsignedBigInteger('overtime_component_type_id')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->unsignedInteger('overtime_minutes')->default(0);
            $t->boolean('is_holiday')->default(false);
            $t->boolean('is_weekly_off')->default(false);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->timestamps();
        });

        foreach (OvertimeRule::seededRows() as $row) {
            OvertimeRule::create($row);
        }

        $type = SalaryComponentType::create([
            'code' => SalaryComponentType::CODE_OVERTIME,
            'name' => 'أجر إضافي',
            'kind' => SalaryItem::TYPE_ADDITION,
            'is_insurable' => false,
            'is_default' => false,
            'is_system' => true,
            'sort_order' => 40,
            'status' => SalaryComponentType::STATUS_ACTIVE,
        ]);

        DB::table('payroll_settings')->insert([
            'overtime_enabled' => true,
            'overtime_monthly_hours' => 240,
            'overtime_component_type_id' => $type->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PayrollSettings::forget();

        Auth::login(User::create([
            'name' => 'مسؤول',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]));
    }

    public function test_settings_route_uses_the_new_permission_and_salary_items_plan(): void
    {
        $this->assertTrue(Route::has('SalarySittings.overtime'));
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'SalarySittings.overtime');

        $this->assertContains('permission.role:salaries_overtime_manage', $route->gatherMiddleware());
        $this->assertContains('plan:hr_salary_items', $route->gatherMiddleware());
        $this->assertContains(
            'salaries_overtime_manage',
            collect(HrPermissions::groups())->flatMap(fn ($g) => array_keys($g['permissions']))->all()
        );
    }

    public function test_weekday_and_holiday_minutes_use_their_multipliers(): void
    {
        [$employee, $slip, $run] = $this->period(4800);
        $this->minutes($employee, '2026-08-03', 120, false);
        $this->minutes($employee, '2026-08-07', 60, true);

        $breakdown = app(OvertimePayService::class)->breakdown(
            $employee,
            $run->period_from,
            $run->period_to,
            4800
        );

        $this->assertEquals(20, $breakdown['hourly_rate']);
        $this->assertEquals(60, $breakdown['weekday_amount']);
        $this->assertEquals(40, $breakdown['holiday_amount']);
        $this->assertEquals(100, $breakdown['amount']);
    }

    public function test_disabled_setting_does_not_create_a_slip_item(): void
    {
        DB::table('payroll_settings')->update(['overtime_enabled' => false]);
        PayrollSettings::forget();
        [$employee, $slip, $run] = $this->period(4800);
        $this->minutes($employee, '2026-08-03', 120, false);

        $item = app(OvertimePayService::class)->syncForRun($run, $slip, $employee);

        $this->assertNull($item);
        $this->assertSame(0, $slip->additionItems()->count());
    }

    public function test_sync_writes_an_addition_item_that_addition_items_picks_up(): void
    {
        [$employee, $slip, $run] = $this->period(4800);
        $this->minutes($employee, '2026-08-03', 120, false);

        $item = app(OvertimePayService::class)->syncForRun($run, $slip, $employee);

        $this->assertNotNull($item);
        $this->assertSame(SalaryItem::ORIGIN_OVERTIME, $item->origin);
        $this->assertSame(SalaryItem::TYPE_ADDITION, (int) $item->type);
        $this->assertEquals(60, (float) $item->amount);
        $this->assertTrue($slip->additionItems()->where('id', $item->id)->exists());
    }

    public function test_daily_cap_clips_minutes_before_pay(): void
    {
        OvertimeRule::query()->where('applies_on', OvertimeRule::APPLIES_WEEKDAY)
            ->update(['max_minutes_per_day' => 60]);
        [$employee, $slip, $run] = $this->period(4800);
        $this->minutes($employee, '2026-08-03', 180, false);

        $breakdown = app(OvertimePayService::class)->breakdown(
            $employee, $run->period_from, $run->period_to, 4800
        );

        $this->assertEquals(60, $breakdown['weekday_minutes']);
        $this->assertEquals(30, $breakdown['amount']);
    }

    public function test_period_cap_scales_weekday_and_holiday(): void
    {
        DB::table('payroll_settings')->update(['overtime_max_minutes_per_period' => 90]);
        PayrollSettings::forget();
        [$employee, $slip, $run] = $this->period(4800);
        $this->minutes($employee, '2026-08-03', 120, false);
        $this->minutes($employee, '2026-08-07', 60, true);

        $breakdown = app(OvertimePayService::class)->breakdown(
            $employee, $run->period_from, $run->period_to, 4800
        );

        $this->assertEquals(60, $breakdown['weekday_minutes']);
        $this->assertEquals(30, $breakdown['holiday_minutes']);
    }

    public function test_zero_minutes_removes_a_previously_generated_item(): void
    {
        [$employee, $slip, $run] = $this->period(4800);
        $this->minutes($employee, '2026-08-03', 120, false);
        app(OvertimePayService::class)->syncForRun($run, $slip, $employee);
        $this->assertSame(1, $slip->additionItems()->count());

        AttendanceDays::query()->delete();
        $item = app(OvertimePayService::class)->syncForRun($run, $slip, $employee);

        $this->assertNull($item);
        $this->assertSame(0, $slip->additionItems()->count());
    }

    public function test_fixed_hourly_rate_overrides_basic_derivation(): void
    {
        DB::table('payroll_settings')->update(['overtime_hourly_rate' => 10]);
        PayrollSettings::forget();
        [$employee, $slip, $run] = $this->period(4800);
        $this->minutes($employee, '2026-08-03', 60, false);

        $breakdown = app(OvertimePayService::class)->breakdown(
            $employee, $run->period_from, $run->period_to, 4800
        );

        $this->assertEquals(10, $breakdown['hourly_rate']);
        $this->assertEquals(15, $breakdown['amount']);
    }

    /** @return array{0: Employee, 1: SalarySlip, 2: PayrollRun} */
    private function period(float $basic): array
    {
        $employee = Employee::create([
            'first_name' => 'سامي',
            'email' => 's'.uniqid('', true).'@example.test',
        ]);
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'basic_amount' => $basic,
        ]);
        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
            'basic_amount' => $basic,
        ]);

        return [$employee, $slip, $run];
    }

    private function minutes(Employee $employee, string $date, int $minutes, bool $holiday): void
    {
        AttendanceDays::create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'overtime_minutes' => $minutes,
            'is_holiday' => $holiday,
            'is_weekly_off' => false,
        ]);
    }
}
