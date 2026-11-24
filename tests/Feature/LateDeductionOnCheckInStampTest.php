<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * خصمُ التأخير يُنشأ فورَ مسح الحضور — لا ينتظر الانصراف.
 */
class LateDeductionOnCheckInStampTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 0,
            'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
            'auto_deduction_enabled' => true,
            'late_deduction_amount' => 1.5,
            'deduction_calculation_type' => 'per_minute',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_ten_minute_late_check_in_creates_payroll_deduction_immediately(): void
    {
        $shift = Shift::create(['name' => 'صباحية']);
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            ShiftDay::create([
                'shift_id' => $shift->id,
                'day' => $day,
                'working_day' => true,
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'grace_period' => 15, // أوسع من إعداد المنشأة — لا تُرخِّص فوق الصفر
            ]);
        }

        $employee = Employee::create([
            'first_name' => 'سالم',
            'email' => 'lateded'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $shift->id,
            'barcode' => 'LD_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);

        // الأحد 2026-09-20 — تأخير 10 دقائق
        Carbon::setTestNow(Carbon::parse('2026-09-20 08:10:00'));

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        $this->assertTrue($result->accepted, $result->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(10, (int) $day->late_minutes);
        $this->assertNotNull($day->deduction_id, 'يجب إنشاء الخصم فور الحضور بلا انتظار انصراف');

        $deduction = Deduction::findOrFail($day->deduction_id);

        $this->assertSame('late', $deduction->deduction_type);
        $this->assertSame(Deduction::STATUS_APPROVED, $deduction->status);
        $this->assertEqualsWithDelta(15.0, (float) $deduction->amount, 0.01, '10 × 1.5');
        $this->assertTrue($employee->unsettledDeductions()->where('id', $deduction->id)->exists());
    }
}
