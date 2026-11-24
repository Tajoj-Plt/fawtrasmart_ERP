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
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * الإجازةُ المعتمدة تمنع احتسابَ اليوم يومَ عمل.
 *
 * **ما كان**
 *
 * الإجازةُ تُنتج تنبيهاً نصّياً وحدَه («للموظف إجازةٌ معتمدة تشمل هذا
 * اليوم — راجع الموارد البشرية») بينما يُقبل الختمُ ويُكتب اليومُ يومَ
 * عملٍ عادياً بأوقاته وساعاته. ثم يأتي الكرونُ ليلاً فيُعيد الحالةَ إلى
 * «إجازة» — فيُمحى أثرُ الحضور صامتاً، وتبقى في هذه الأثناء ساعاتٌ
 * محسوبةٌ لمن سأل عن اليوم قبل أن يمرّ الكرون.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن الحضورَ يُردّ في الحلقة الرابعة (سجلّ اليوم)، وأن الردَّ يُكتب في
 * سجل الأختام فتُقرأ المحاولة. وأن **الانصراف** يبقى مسموحاً: من سُجِّل
 * حضورُه قبل اعتماد الإجازة يجب أن يُغلق يومَه، وإلا بقي معلَّقاً بلا
 * انصرافٍ أبداً.
 */
class ApprovedLeaveBlocksAttendanceTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        AttendanceSetting::create([
            'start_month' => 1, 'start_day' => 1,
            'late_grace_minutes' => 15, 'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
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
            'email' => "leave{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'LCARD_'.$sequence.'_'.uniqid(),
            'attendance_channel' => 'both',
        ]);
    }

    private function approveLeave(Employee $employee, string $from, string $to): void
    {
        DB::table('leave_requests')->insert([
            'employee_id' => $employee->id,
            'status' => 'approved',
            'start_date' => $from,
            'end_date' => $to,
            'created_at' => now(), 'updated_at' => now(),
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

    /** بلا إجازة: الحضورُ يُقبل كالمعتاد. */
    public function test_without_a_leave_the_check_in_is_accepted(): void
    {
        $employee = $this->employee();
        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));

        $this->assertTrue($this->stamp($employee, AttendancePunch::TYPE_IN)->accepted);
    }

    /** وبإجازةٍ معتمدة تشمل اليوم: الحضورُ يُردّ ولا يُكتب يومُ عمل. */
    public function test_an_approved_leave_refuses_the_check_in(): void
    {
        $employee = $this->employee();
        $this->approveLeave($employee, '2026-09-14', '2026-09-18');

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));
        $result = $this->stamp($employee, AttendancePunch::TYPE_IN);

        $this->assertFalse($result->accepted);
        $this->assertStringContainsString('إجازةٍ معتمدة', $result->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();
        $this->assertTrue(
            $day === null || $day->check_in_time === null,
            'لا يُكتب وقتُ حضورٍ على يومِ إجازةٍ معتمدة'
        );
    }

    /** والمحاولةُ المردودة تُكتب في سجل الأختام — فتُقرأ في «سجلات الحضور». */
    public function test_the_refused_attempt_is_recorded_as_a_rejected_punch(): void
    {
        $employee = $this->employee();
        $this->approveLeave($employee, '2026-09-16', '2026-09-16');

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));
        $this->stamp($employee, AttendancePunch::TYPE_IN);

        $punch = AttendancePunch::where('employee_id', $employee->id)->first();

        $this->assertNotNull($punch);
        $this->assertSame(AttendancePunch::RESULT_REJECTED, $punch->result);
        $this->assertSame('on_approved_leave', $punch->rejection_code);
    }

    /** وإجازةٌ خارج اليوم لا تمنع شيئاً. */
    public function test_a_leave_on_other_dates_does_not_block(): void
    {
        $employee = $this->employee();
        $this->approveLeave($employee, '2026-09-01', '2026-09-05');

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));

        $this->assertTrue($this->stamp($employee, AttendancePunch::TYPE_IN)->accepted);
    }

    /** وطلبٌ غيرُ معتمد لا يمنع: «معلَّق» ليس «معتمَد». */
    public function test_a_pending_leave_does_not_block(): void
    {
        $employee = $this->employee();

        DB::table('leave_requests')->insert([
            'employee_id' => $employee->id,
            'status' => 'pending',
            'start_date' => '2026-09-16', 'end_date' => '2026-09-16',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));

        $this->assertTrue($this->stamp($employee, AttendancePunch::TYPE_IN)->accepted);
    }

    /**
     * والانصرافُ يبقى مسموحاً.
     *
     * من سُجِّل حضورُه ثم اعتُمدت إجازتُه يجب أن يُغلق يومَه — وإلا بقي
     * معلَّقاً بلا انصرافٍ أبداً.
     */
    public function test_check_out_is_still_allowed_after_the_leave_is_approved(): void
    {
        $employee = $this->employee();

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));
        $this->assertTrue($this->stamp($employee, AttendancePunch::TYPE_IN)->accepted);

        // الإجازةُ تُعتمد بعد أن بدأ يومُه
        $this->approveLeave($employee, '2026-09-16', '2026-09-16');
        $this->normalizeAttendanceDates();

        Carbon::setTestNow(Carbon::parse('2026-09-16 16:00:00'));
        $result = $this->stamp($employee, AttendancePunch::TYPE_OUT);

        $this->assertTrue($result->accepted, $result->message);
    }
}
