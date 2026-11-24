<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * الوردياتُ الليلية: انصرافٌ في تقويم الغد، وسجلٌّ للأمس.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * ورديةٌ من ٢٢:٠٠ إلى ٠٦:٠٠ تنتهي في اليومِ التالي. وكان ختمُ الانصراف
 * يُطلَب لليوم الجاري دائماً، فيجد صفَّ الغد بلا حضورٍ ويردّ بـ
 * «لا انصرافَ بلا حضور» — ويبقى صفُّ الأمس مفتوحاً أبداً. أي أن **كل
 * موظفٍ ليليّ** كان يحتاج طلبَ تصحيحٍ عن كل نوبةٍ يعملها.
 *
 * والشرطُ الذي يجب ألّا ينقلب: ألّا يُغلق ختمُ اليوم أياماً منسيّةً لا
 * تخصّه. فالإسنادُ إلى الأمس مشروطٌ بثلاثة مجتمعة — اليومُ الجاري لم
 * يبدأ، وصفُّ الأمس مفتوح، وورديةُ الأمس تعبر منتصف الليل فعلاً.
 */
class OvernightShiftCheckoutTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    private const NIGHT_IN = '2026-09-16 22:00:00';   // أربعاء

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 120,
            'work_hours_per_day' => 8,
            'early_leave_threshold_minutes' => 5,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_night_shift_checkout_after_midnight_closes_yesterdays_row(): void
    {
        $employee = $this->nightEmployee();

        $this->stampAt(self::NIGHT_IN, $employee, AttendancePunch::TYPE_IN);
        $result = $this->stampAt('2026-09-17 06:00:00', $employee, AttendancePunch::TYPE_OUT);

        $this->assertTrue($result->accepted, $result->message);

        // صفٌّ واحد — لا صفٌّ جديدٌ ليوم ١٧
        $this->assertSame(1, AttendanceDays::count());

        $day = AttendanceDays::firstOrFail();
        $this->assertSame('2026-09-16', $day->attendance_date->toDateString());
        $this->assertSame('22:00:00', $day->check_in_time);
        $this->assertSame('06:00:00', $day->check_out_time);
    }

    public function test_the_punch_is_filed_under_the_shift_day_not_the_calendar_day(): void
    {
        $employee = $this->nightEmployee();

        $this->stampAt(self::NIGHT_IN, $employee, AttendancePunch::TYPE_IN);
        $this->stampAt('2026-09-17 06:00:00', $employee, AttendancePunch::TYPE_OUT);

        $punch = AttendancePunch::where('punch_type', AttendancePunch::TYPE_OUT)->firstOrFail();

        // الواقعةُ وقعت في ١٧، وتخصّ سجلَّ ١٦ — والختمُ يحمل الاثنين
        $this->assertSame('2026-09-17 06:00:00', $punch->punched_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-16', $punch->attendance_date->toDateString());
    }

    public function test_overtime_on_a_night_shift_is_measured_against_its_own_shift_end(): void
    {
        $employee = $this->nightEmployee();

        $this->stampAt(self::NIGHT_IN, $employee, AttendancePunch::TYPE_IN);
        $this->normalizeAttendanceDates();
        $this->stampAt('2026-09-17 07:30:00', $employee, AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::firstOrFail();

        // نهاية الوردية 06:00 من يوم ١٧ → ساعة ونصف إضافي
        $this->assertSame(90, (int) $day->overtime_minutes);
    }

    public function test_the_checkout_window_still_caps_a_night_shift(): void
    {
        // نافذةٌ تنتهي 07:00 — والختمُ 14:00
        $employee = $this->nightEmployee(logoutEnd: '07:00:00');

        $this->stampAt(self::NIGHT_IN, $employee, AttendancePunch::TYPE_IN);
        $this->normalizeAttendanceDates();
        $result = $this->stampAt('2026-09-17 14:00:00', $employee, AttendancePunch::TYPE_OUT);

        $this->assertTrue($result->accepted);

        $day = AttendanceDays::firstOrFail();
        $this->assertSame('06:00:00', $day->check_out_time);
        $this->assertTrue((bool) $day->check_out_capped);
        $this->assertSame(0, (int) $day->overtime_minutes);
    }

    public function test_a_new_shift_started_today_keeps_yesterday_for_the_correction_cycle(): void
    {
        $employee = $this->nightEmployee();

        // نوبةُ الأمس فُتحت ولم تُغلق
        $this->stampAt(self::NIGHT_IN, $employee, AttendancePunch::TYPE_IN);

        // ثم بدأ نوبةً جديدة مساءَ اليوم التالي
        $this->stampAt('2026-09-17 22:00:00', $employee, AttendancePunch::TYPE_IN);

        $this->assertSame(2, AttendanceDays::count());

        // والانصرافُ الآن يخصّ النوبة الجديدة لا القديمة
        $this->stampAt('2026-09-18 06:00:00', $employee, AttendancePunch::TYPE_OUT);

        $yesterday = AttendanceDays::whereDate('attendance_date', '2026-09-16')->firstOrFail();
        $newer = AttendanceDays::whereDate('attendance_date', '2026-09-17')->firstOrFail();

        $this->assertNull($yesterday->check_out_time, 'أُغلقت نوبةٌ قديمة بختمٍ لا يخصّها');
        $this->assertSame('06:00:00', $newer->check_out_time);
    }

    public function test_a_day_shift_left_open_is_not_closed_by_the_next_mornings_stamp(): void
    {
        // ورديةٌ نهارية 08:00–16:00 نُسي انصرافُها
        $employee = $this->dayEmployee();

        $this->stampAt('2026-09-16 08:00:00', $employee, AttendancePunch::TYPE_IN);

        // ختمُ انصرافٍ صباحَ الغد: لا يخصّ الأمس — تلك حالةُ دورة التصحيح
        $result = $this->stampAt('2026-09-17 07:00:00', $employee, AttendancePunch::TYPE_OUT, expectAccepted: false);

        $this->assertFalse($result->accepted);
        $this->assertSame('no_check_in', $result->code);

        $day = AttendanceDays::whereDate('attendance_date', '2026-09-16')->firstOrFail();
        $this->assertNull($day->check_out_time);
    }

    public function test_a_night_employee_who_never_checked_in_cannot_check_out(): void
    {
        $employee = $this->nightEmployee();

        $result = $this->stampAt('2026-09-17 06:00:00', $employee, AttendancePunch::TYPE_OUT, expectAccepted: false);

        $this->assertFalse($result->accepted);
        $this->assertSame('no_check_in', $result->code);
    }

    // ════════════════════════════════════════════════════

    private function nightEmployee(?string $logoutEnd = null): Employee
    {
        return $this->employee('22:00:00', '06:00:00', $logoutEnd);
    }

    private function dayEmployee(): Employee
    {
        return $this->employee('08:00:00', '16:00:00', null);
    }

    private function employee(string $start, string $end, ?string $logoutEnd): Employee
    {
        static $sequence = 0;
        $sequence++;

        $shift = Shift::create(['name' => "وردية {$sequence}"]);

        foreach (['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
            ShiftDay::create([
                'shift_id' => $shift->id,
                'day' => $day,
                'working_day' => true,
                'start_time' => $start,
                'end_time' => $end,
                'logout_end_time' => $logoutEnd,
            ]);
        }

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "night{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $shift->id,
            'attendance_channel' => 'both',
            'barcode' => 'CARD_'.$sequence.'_'.uniqid(),
        ]);
    }

    private function stampAt(string $moment, Employee $employee, string $punchType, bool $expectAccepted = true)
    {
        Carbon::setTestNow(Carbon::parse($moment));

        // تطبيعُ التاريخ قبل كل ختم — انظر التعليق على الدالة في الـtrait
        $this->normalizeAttendanceDates();

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: $punchType,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        if ($expectAccepted) {
            $this->assertTrue($result->accepted, "رُدّ الختم عند {$moment}: {$result->message}");
        }

        return $result;
    }
}
