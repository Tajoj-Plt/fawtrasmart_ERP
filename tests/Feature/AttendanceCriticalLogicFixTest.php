<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use App\Models\AttendanceSetting;
use App\Models\Deduction;
use App\Models\Employee;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * نصف يوم + خصم انصراف مبكر + أيام إجازة عمل فقط.
 */
class AttendanceCriticalLogicFixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
            $t->tinyInteger('status')->default(1);
            $t->unsignedBigInteger('shift_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->string('status')->default('present');
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->unsignedBigInteger('deduction_id')->nullable();
            $t->integer('late_minutes')->default(0);
            $t->integer('early_leave_minutes')->default(0);
            $t->integer('overtime_minutes')->default(0);
            $t->time('expected_check_in')->nullable();
            $t->time('expected_check_out')->nullable();
            $t->boolean('is_holiday')->default(false);
            $t->boolean('is_weekly_off')->default(false);
            $t->boolean('is_on_leave')->default(false);
            $t->boolean('manual_override')->default(false);
            $t->boolean('auto_generated')->default(false);
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('attendance_settings', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->boolean('auto_deduction_enabled')->default(true);
            $t->decimal('late_deduction_amount', 10, 2)->default(50);
            $t->decimal('absence_deduction_amount', 10, 2)->default(100);
            $t->decimal('early_leave_deduction_amount', 10, 2)->default(40);
            $t->string('deduction_calculation_type')->default('fixed');
            $t->decimal('work_hours_per_day', 5, 2)->default(8);
            $t->integer('late_grace_minutes')->default(15);
            $t->integer('absent_after_minutes')->default(240);
            $t->integer('early_leave_threshold_minutes')->default(10);
            $t->json('default_weekly_off_days')->nullable();
            $t->string('default_shift_start')->nullable();
            $t->timestamps();
        });

        Schema::create('employee_attendance_configs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->timestamps();
        });

        Schema::create('custom_shifts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->timestamps();
        });

        Schema::create('custom_shifts_employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('custom_shifts_id');
            $t->unsignedBigInteger('employee_id');
            $t->timestamps();
        });

        Schema::create('leave_permissions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedTinyInteger('leave_type');
            $t->date('start_date');
            $t->date('end_date');
            $t->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default('pending');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });

        Schema::create('holidays', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->boolean('is_active')->default(true);
            $t->date('from_date')->nullable();
            $t->date('to_date')->nullable();
            $t->unsignedBigInteger('holiday_list_id')->nullable();
            $t->timestamps();
        });

        Schema::create('shifts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->integer('grace_period')->nullable();
            $t->timestamps();
        });

        Schema::create('shift_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('shift_id');
            $t->string('day');
            $t->boolean('working_day')->default(1);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->timestamps();
        });

        Schema::create('deductions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('deduction_type', 40)->default('other');
            $t->decimal('amount', 12, 2)->default(0);
            $t->decimal('settled_amount', 12, 2)->default(0);
            $t->string('status')->default('approved');
            $t->date('date')->nullable();
            $t->text('reason')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by_user')->nullable();
            $t->unsignedBigInteger('approved_by_user')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('deduction_histories', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('deduction_id');
            $t->string('action')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->decimal('amount', 12, 2)->nullable();
            $t->text('description')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_rules', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->timestamps();
        });

        DB::table('attendance_settings')->insert([
            'auto_deduction_enabled' => 1,
            'late_deduction_amount' => 50,
            'absence_deduction_amount' => 100,
            'early_leave_deduction_amount' => 40,
            'deduction_calculation_type' => 'fixed',
            'work_hours_per_day' => 8,
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 240,
            'early_leave_threshold_minutes' => 10,
            'default_weekly_off_days' => json_encode(['fri']),
            'default_shift_start' => '09:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function employeeWithDayShift(): Employee
    {
        $shiftId = DB::table('shifts')->insertGetId([
            'name' => 'صباحية',
            'grace_period' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'saturday'] as $day) {
            DB::table('shift_days')->insert([
                'shift_id' => $shiftId,
                'day' => $day,
                'working_day' => 1,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Employee::create([
            'first_name' => 'م',
            'email' => 'half@t.test',
            'status' => 1,
            'shift_id' => $shiftId,
        ]);
    }

    public function test_early_departure_half_of_shift_becomes_half_day_with_half_absence_deduction(): void
    {
        $employee = $this->employeeWithDayShift();
        // اثنين 2026-01-05 — وردية 9–17 (8 ساعات)، انصراف 13:00 = 4 ساعات عمل = نصف يوم
        AttendanceDays::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'check_in_time' => '09:00:00',
            'check_out_time' => '12:50:00',
            'status' => 'present',
        ]);

        $result = app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        $day = AttendanceDays::first();
        $this->assertSame(AttendanceDays::STATUS_HALF_DAY, $result['status']);
        $this->assertSame(AttendanceDays::STATUS_HALF_DAY, $day->status);

        $deduction = Deduction::first();
        $this->assertNotNull($deduction);
        $this->assertSame(Deduction::TYPE_HALF_DAY, $deduction->deduction_type);
        $this->assertEqualsWithDelta(50.0, (float) $deduction->amount, 0.01);
    }

    public function test_mild_early_leave_keeps_present_and_creates_early_leave_deduction(): void
    {
        $employee = $this->employeeWithDayShift();
        // انصراف قبل الموعد بـ 30 دقيقة فقط (أقل من نصف الوردية)
        AttendanceDays::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'check_in_time' => '09:00:00',
            'check_out_time' => '16:30:00',
            'status' => 'present',
        ]);

        $result = app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        $day = AttendanceDays::first();
        $this->assertSame(AttendanceDays::STATUS_PRESENT, $result['status']);
        $this->assertGreaterThan(0, (int) $day->early_leave_minutes);

        $deduction = Deduction::first();
        $this->assertNotNull($deduction);
        $this->assertSame(Deduction::TYPE_EARLY_LEAVE, $deduction->deduction_type);
        $this->assertEqualsWithDelta(40.0, (float) $deduction->amount, 0.01);
    }

    public function test_leave_end_date_skips_weekly_off(): void
    {
        $employee = $this->employeeWithDayShift();
        // خميس 2026-01-01 + 2 يوم عمل مع جمعة عطلة → خميس + سبت = نهاية السبت؟
        // خميس عمل (1)، جمعة عطلة، سبت عمل (2) → نهاية 2026-01-03
        $end = app(AttendanceProcessorService::class)
            ->endDateForWorkingDays($employee, Carbon::parse('2026-01-01'), 2);

        $this->assertSame('2026-01-03', $end->toDateString());
    }
}
