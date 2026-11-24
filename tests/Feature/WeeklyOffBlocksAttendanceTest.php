<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * العطلةُ الأسبوعية من إعدادات الحضور تمنع تسجيل الحضور.
 *
 * مثال: الجمعة مضبوطة في «إعدادات الحضور» → مسح البطاقة يوم الجمعة يُردّ
 * بحجّة أن اليوم إجازة، ولا يُكتب يومُ عمل.
 */
class WeeklyOffBlocksAttendanceTest extends TestCase
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
            'default_weekly_off_days' => ['fri'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "off{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'OFF_'.$sequence.'_'.uniqid(),
            'attendance_channel' => 'both',
        ]);
    }

    private function stamp(Employee $employee, string $type)
    {
        return app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: $type,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));
    }

    /** الجمعة 2026-09-18 عطلة أسبوعية — الحضور يُرفض. */
    public function test_friday_check_in_is_rejected_when_friday_is_weekly_off(): void
    {
        $employee = $this->employee();
        Carbon::setTestNow(Carbon::parse('2026-09-18 08:00:00')); // Friday

        $result = $this->stamp($employee, AttendancePunch::TYPE_IN);

        $this->assertFalse($result->accepted);
        $this->assertSame('weekly_off', $result->code);
        $this->assertStringContainsString('عطلةٌ أسبوعية', $result->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();
        $this->assertTrue(
            $day === null || $day->check_in_time === null,
            'لا يُكتب وقتُ حضورٍ على يوم عطلة أسبوعية'
        );
    }

    /** والمحاولة المردودة تُكتب في سجل الأختام. */
    public function test_the_refused_friday_attempt_is_recorded(): void
    {
        $employee = $this->employee();
        Carbon::setTestNow(Carbon::parse('2026-09-18 08:00:00'));

        $this->stamp($employee, AttendancePunch::TYPE_IN);

        $punch = AttendancePunch::where('employee_id', $employee->id)->first();

        $this->assertNotNull($punch);
        $this->assertSame(AttendancePunch::RESULT_REJECTED, $punch->result);
        $this->assertSame('weekly_off', $punch->rejection_code);
    }

    /** يوم عمل عادي (الخميس) يُقبل رغم أن الجمعة عطلة في الإعدادات. */
    public function test_weekday_check_in_is_still_accepted(): void
    {
        $employee = $this->employee();
        Carbon::setTestNow(Carbon::parse('2026-09-17 08:00:00')); // Thursday

        $result = $this->stamp($employee, AttendancePunch::TYPE_IN);

        $this->assertTrue($result->accepted, $result->message);
    }

    /** بلا أيام عطلة في الإعدادات: الجمعة تُقبل. */
    public function test_friday_is_accepted_when_no_weekly_off_is_configured(): void
    {
        AttendanceSetting::query()->delete();
        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
        ]);

        $employee = $this->employee();
        Carbon::setTestNow(Carbon::parse('2026-09-18 08:00:00'));

        $this->assertTrue($this->stamp($employee, AttendancePunch::TYPE_IN)->accepted);
    }
}
