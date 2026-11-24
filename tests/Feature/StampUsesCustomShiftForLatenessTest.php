<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\CustomShift;
use App\Models\Employee;
use App\Models\EmployeeAttendanceConfig;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * المسح يقيس التأخير على الوردية المخصَّصة لا على إعداد الموظف الثابت.
 */
class StampUsesCustomShiftForLatenessTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_shift_start' => '09:00:00',
            'default_weekly_off_days' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function shift(string $start, string $end): int
    {
        $id = DB::table('shifts')->insertGetId([
            'name' => 'وردية '.$start,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // الأحد 2026-09-20
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            DB::table('shift_days')->insert([
                'shift_id' => $id,
                'day' => $day,
                'working_day' => 1,
                'start_time' => $start,
                'end_time' => $end,
                'grace_period' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function stamp(Employee $employee): \App\Services\Attendance\StampResult
    {
        return app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));
    }

    /**
     * وردية دائمة 10:00 + إعداد موظف 10:00 + إسناد مخصَّص 06:00.
     * حضور 07:00 → متأخّر ساعة عن المخصَّصة (لا «في الوقت» نسبةً لـ 10:00).
     */
    public function test_stamp_lateness_follows_custom_shift_not_employee_config(): void
    {
        $permanent = $this->shift('10:00:00', '18:00:00');
        $assigned = $this->shift('06:00:00', '14:00:00');

        $employee = Employee::create([
            'first_name' => 'سالم',
            'email' => 'customlate'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $permanent,
            'barcode' => 'CL_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);

        EmployeeAttendanceConfig::create([
            'employee_id' => $employee->id,
            'expected_check_in' => '10:00:00',
            'expected_check_out' => '18:00:00',
        ]);

        $custom = CustomShift::create([
            'name' => 'وردية مبكرة مؤقتة',
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
            'shift_id' => $assigned,
            'use_rules' => 2,
            'is_main' => true,
        ]);
        $custom->employees()->attach($employee->id);

        Carbon::setTestNow(Carbon::parse('2026-09-20 07:00:00')); // Sunday

        $result = $this->stamp($employee);

        $this->assertTrue($result->accepted, $result->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        $this->assertSame('06:00:00', $day->expected_check_in);
        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(60, (int) $day->late_minutes);
    }

    /** بلا إسناد مخصَّص: يُقاس على إعداد الموظف / الوردية الدائمة. */
    public function test_without_custom_assignment_employee_config_still_applies(): void
    {
        $permanent = $this->shift('10:00:00', '18:00:00');

        $employee = Employee::create([
            'first_name' => 'سالم',
            'email' => 'cfg'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $permanent,
            'barcode' => 'CFG_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);

        // إعداد موظف بلا صف وردية فعّال لهذا الاختبار — نستخدم الوردية الدائمة
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:45:00'));

        $result = $this->stamp($employee);

        $this->assertTrue($result->accepted, $result->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        $this->assertSame('10:00:00', $day->expected_check_in);
        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(45, (int) $day->late_minutes);
    }
}
