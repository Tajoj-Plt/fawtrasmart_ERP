<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use App\Services\AttendanceProcessorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * إصلاحات منطق الحضور: قفل التصحيح، رصيد الإجازة، مسار الحساب.
 */
class AttendanceWorkflowLogicFixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('email')->nullable();
            $t->string('status')->default('1');
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->unsignedInteger('max_days_per_year')->default(21);
            $t->timestamps();
        });

        Schema::create('employee_leave_balances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('leave_type_id');
            $t->unsignedSmallInteger('year');
            $t->integer('initial_balance')->default(0);
            $t->integer('used_balance')->default(0);
            $t->integer('remaining_balance')->default(0);
            $t->integer('carried_forward')->default(0);
            $t->integer('additional_balance')->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->string('status')->default('absent');
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->boolean('manual_override')->default(false);
            $t->boolean('auto_generated')->default(false);
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('leave_type_id')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status')->default('pending');
            $t->unsignedInteger('days')->default(1);
            $t->timestamps();
        });
    }

    public function test_manual_override_blocks_processor_rewrite(): void
    {
        $employee = Employee::create(['first_name' => 'م', 'email' => 'm@t.test', 'status' => '1']);

        $day = AttendanceDays::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => AttendanceDays::STATUS_LATE,
            'check_in_time' => '09:30:00',
            'check_out_time' => '17:00:00',
            'manual_override' => true,
            'late_minutes' => 30,
        ]);

        $result = app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, \Carbon\Carbon::parse('2026-01-05'));

        $day->refresh();

        $this->assertSame('late', $result['status']);
        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame('09:30:00', $day->check_in_time);
        $this->assertTrue((bool) $day->manual_override);
    }

    public function test_leave_balance_deduct_and_restore_update_used_column(): void
    {
        $employee = Employee::create(['first_name' => 'إ', 'email' => 'e@t.test', 'status' => '1']);
        $type = LeaveType::create(['name' => 'سنوية', 'max_days_per_year' => 21]);

        $balance = EmployeeLeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => 2026,
            'initial_balance' => 21,
            'used_balance' => 0,
            'remaining_balance' => 21,
        ]);

        $this->assertTrue($balance->deductDays(3, 'اعتماد'));
        $balance->refresh();
        $this->assertSame(3, (int) $balance->used_balance);
        $this->assertSame(18, (int) $balance->remaining_balance);
        $this->assertFalse($balance->canDeduct(20));

        $balance->addDays(3, 'إلغاء اعتماد');
        $balance->refresh();
        $this->assertSame(0, (int) $balance->used_balance);
        $this->assertSame(21, (int) $balance->remaining_balance);
    }

    public function test_run_calculation_uses_processor_not_sheet_store(): void
    {
        $controller = file_get_contents(base_path(
            'Modules/HR/Http/Controllers/Attendance/AttendanceDaysController.php'
        ));
        $view = file_get_contents(base_path(
            'Modules/HR/resources/views/attendance/attendance_days/Calculation.blade.php'
        ));

        $this->assertStringContainsString('function runCalculation', $controller);
        $this->assertStringContainsString('processEmployeesDateRange', $controller);
        $this->assertStringContainsString('attendanceDays.runCalculation', $view);
        $this->assertStringNotContainsString("route('attendance_sheets.store')", $view);
    }

    public function test_fingerprint_machines_page_left_unimplemented(): void
    {
        $source = file_get_contents(base_path(
            'Modules/HR/Http/Controllers/Attendance/Settings/MachinesController.php'
        ));
        $this->assertStringContainsString('501', $source);
        $this->assertStringContainsString('غير مُفعَّل بعد', $source);
    }
}
