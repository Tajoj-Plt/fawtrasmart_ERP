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
 * نافذةُ الانصراف: تقصّ ولا تكافئ.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * موظفٌ غادر الرابعة عصراً ومرَّ على الجهاز الحادية عشرة ليلاً كان
 * يُحتسب له سبعُ ساعاتٍ إضافية لم يعملها — لأن `shift_days.logout_end_time`
 * عمودٌ تحفظه شاشةُ الورديات ولا يقرؤه منطقٌ واحد.
 *
 * والقاعدةُ التي يقيسها ما يلي: الختمُ **يُقبل دائماً** فاليومُ يُغلق ولا
 * يُترك مفتوحاً، ولكنه يُكتب على نهاية الوردية فلا يُنتج استحقاقاً.
 * والحدُّ الفاصل هو النافذة لا نهايةُ الوردية: من انصرف داخل النافذة
 * يأخذ إضافيَّه كاملاً.
 *
 * والحالةُ التي يسهل أن تنقلب: ألّا يقصّ القصُّ **دون** ما عُمِل. انصرافٌ
 * مبكرٌ لا تمسّه النافذة أبداً.
 *
 * وللنافذة مصدران: صفُّ الوردية، ونافذةٌ عامّةٌ بالدقائق في إعدادات
 * الحضور. والثانيةُ ليست تحسيناً: منشأةٌ بلا جدول ورديات — وهي حالةٌ
 * واقعية — لا صفَّ ورديةٍ فيها إطلاقاً، فكانت الطبقةُ نائمةً فيها تماماً.
 */
class CheckoutWindowCappingTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    private const WORK_DATE = '2026-09-16'; // أربعاء

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

    public function test_a_checkout_beyond_the_window_is_capped_to_the_shift_end(): void
    {
        // وردية 08:00–16:00، ونافذةُ انصرافٍ تنتهي 18:00
        $employee = $this->employee(logoutEnd: '18:00:00');

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $result = $this->stamp($employee, '23:30:00', AttendancePunch::TYPE_OUT);

        // الختمُ يُقبل: اليومُ يُغلق ولا يُترك معلّقاً
        $this->assertTrue($result->accepted);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        $this->assertSame('16:00:00', $day->check_out_time, 'لم يُقصّ الانصراف إلى نهاية الوردية');
        $this->assertTrue((bool) $day->check_out_capped);
        $this->assertSame(0, (int) $day->overtime_minutes, 'احتُسب إضافيٌّ لم يقع');
    }

    public function test_the_raw_punch_keeps_both_times(): void
    {
        $employee = $this->employee(logoutEnd: '18:00:00');

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '23:30:00', AttendancePunch::TYPE_OUT);

        $punch = AttendancePunch::where('employee_id', $employee->id)
            ->where('punch_type', AttendancePunch::TYPE_OUT)
            ->firstOrFail();

        // ما قرأته الساعةُ فعلاً — لا يُمَسّ
        $this->assertSame('23:30:00', $punch->punched_at->format('H:i:s'));
        // وما كُتب في سجل اليوم
        $this->assertSame('16:00:00', $punch->applied_time);
        $this->assertSame('capped_to_shift_end', $punch->adjustment_code);
    }

    public function test_the_employee_is_told_that_the_stamp_was_capped(): void
    {
        $employee = $this->employee(logoutEnd: '18:00:00');

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $result = $this->stamp($employee, '23:30:00', AttendancePunch::TYPE_OUT);

        // قصٌّ صامتٌ أسوأُ من ردّ: يُكتشف الفارقُ في القسيمة بعد شهر
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('نافذة الوردية', implode(' ', $result->warnings));
        $this->assertStringContainsString('طلب تصحيح', implode(' ', $result->warnings));
    }

    public function test_overtime_inside_the_window_is_paid_in_full(): void
    {
        $employee = $this->employee(logoutEnd: '18:00:00');

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '17:45:00', AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        $this->assertSame('17:45:00', $day->check_out_time);
        $this->assertFalse((bool) $day->check_out_capped);
        $this->assertSame(105, (int) $day->overtime_minutes, 'قُصَّ إضافيٌّ داخل النافذة');
    }

    public function test_the_window_boundary_itself_is_inside(): void
    {
        $employee = $this->employee(logoutEnd: '18:00:00');

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '18:00:00', AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        $this->assertFalse((bool) $day->check_out_capped, 'طرفُ النافذة يجب أن يكون داخلها');
        $this->assertSame(120, (int) $day->overtime_minutes);
    }

    public function test_a_shift_without_a_window_is_never_capped(): void
    {
        // النافذةُ هي الإذن، وغيابُها ليس منعاً — ولا تتغيّر الورديات القائمة
        $employee = $this->employee(logoutEnd: null);

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '23:30:00', AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        $this->assertSame('23:30:00', $day->check_out_time);
        $this->assertFalse((bool) $day->check_out_capped);
        $this->assertSame(450, (int) $day->overtime_minutes);
    }

    public function test_an_early_checkout_is_never_pushed_up_to_the_shift_end(): void
    {
        $employee = $this->employee(logoutEnd: '18:00:00');

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '14:30:00', AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        // القصُّ يُسقط إضافياً غيرَ مُثبَت، ولا يمنح وقتاً لم يُعمَل
        $this->assertSame('14:30:00', $day->check_out_time);
        $this->assertFalse((bool) $day->check_out_capped);
        $this->assertSame(90, (int) $day->early_leave_minutes);
    }

    public function test_a_global_window_caps_when_no_shift_row_carries_one(): void
    {
        // منشأةٌ بلا جدول ورديات: الموظفُ بلا `shift_id`، وموعدُ الانصراف
        // يُشتقّ من `work_hours_per_day` (08:00 + 8 = 16:00).
        $employee = $this->shiftlessEmployee();
        AttendanceSetting::query()->update(['checkout_window_minutes' => 120]);

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $result = $this->stamp($employee, '23:30:00', AttendancePunch::TYPE_OUT);

        $this->assertTrue($result->accepted);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();
        $this->assertSame('16:00:00', $day->check_out_time);
        $this->assertTrue((bool) $day->check_out_capped);
        $this->assertSame(0, (int) $day->overtime_minutes);
    }

    public function test_a_global_window_leaves_work_inside_it_alone(): void
    {
        $employee = $this->shiftlessEmployee();
        AttendanceSetting::query()->update(['checkout_window_minutes' => 120]);

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '17:30:00', AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();
        $this->assertSame('17:30:00', $day->check_out_time);
        $this->assertFalse((bool) $day->check_out_capped);
        $this->assertSame(90, (int) $day->overtime_minutes);
    }

    public function test_an_empty_global_window_imposes_no_limit(): void
    {
        // التوافقُ مع القائم: الفراغُ لا قيد
        $employee = $this->shiftlessEmployee();

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '23:30:00', AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();
        $this->assertSame('23:30:00', $day->check_out_time);
        $this->assertFalse((bool) $day->check_out_capped);
    }

    public function test_the_shift_window_wins_over_the_global_one(): void
    {
        // الأسبقيّةُ للأخصّ: نافذةُ الوردية 20:00 تتجاوز العامّة (120 د → 18:00)
        $employee = $this->employee(logoutEnd: '20:00:00');
        AttendanceSetting::query()->update(['checkout_window_minutes' => 120]);

        $this->stamp($employee, '08:00:00', AttendancePunch::TYPE_IN);
        $this->stamp($employee, '19:30:00', AttendancePunch::TYPE_OUT);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();
        $this->assertFalse((bool) $day->check_out_capped, 'غلبت النافذةُ العامّة نافذةَ الوردية');
        $this->assertSame(210, (int) $day->overtime_minutes);
    }

    // ════════════════════════════════════════════════════

    /** موظفٌ بلا ورديةٍ مُسنَدة — حالُ منشأةٍ لم تُنشئ جدول ورديات. */
    private function shiftlessEmployee(): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create([
            'first_name' => 'بلا وردية',
            'middle_name' => (string) $sequence,
            'email' => "shiftless{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'attendance_channel' => 'both',
            'barcode' => 'CARD_S'.$sequence.'_'.uniqid(),
        ]);
    }

    private function employee(
        ?string $logoutEnd,
        string $start = '08:00:00',
        string $end = '16:00:00',
    ): Employee {
        static $sequence = 0;
        $sequence++;

        $shift = Shift::create(['name' => "وردية {$sequence}"]);

        foreach (['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
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
            'email' => "window{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $shift->id,
            'attendance_channel' => 'both',
            'barcode' => 'CARD_'.$sequence.'_'.uniqid(),
        ]);
    }

    private function context(string $punchType, Employee $employee): StampContext
    {
        return new StampContext(
            punchType: $punchType,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            // الوسيلةُ تُثبَت: ختمٌ بالباركود يحمل باركودَ صاحبه
            rawBarcode: $employee->barcode,
        );
    }

    private function stamp(Employee $employee, string $time, string $punchType)
    {
        Carbon::setTestNow(Carbon::parse(self::WORK_DATE.' '.$time));

        // صفُّ اليوم كتبه ختمُ الحضور بالموديل — يُطبَّع تاريخُه قبل أن
        // يبحث عنه المعالجُ حرفياً عند الانصراف. انظر التعليق على الدالة.
        if ($punchType === AttendancePunch::TYPE_OUT) {
            $this->normalizeAttendanceDates();
        }

        return app(AttendanceStampService::class)->stamp($employee, $this->context($punchType, $employee));
    }
}
