<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * الورديةُ الافتراضية مُدخَلةٌ لا مكتوبةٌ في الشيفرة.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * موظفٌ بلا وردية مُسنَدة — وهي حالُ منشأةٍ لم تُنشئ جدول ورديات — كان
 * يُقاس عليه ثابتان مدفونان في `AttendanceProcessorService`:
 * `'08:00:00'` بدايةً للدوام، و`['fri']` عطلةً أسبوعية. لا شاشةَ تُغيّرهما
 * لأنهما ليسا إعداداً أصلاً.
 *
 * فمنشأةٌ تبدأ التاسعة كانت تُحتسب على كل موظفيها ساعةُ تأخيرٍ يومياً،
 * وأخرى عطلتُها الجمعة والسبت كان سبتُها يُحتسب غياباً — والخصميةُ
 * التلقائية تُترجم ذلك مالاً.
 *
 * والحالةُ التي يسهل أن تُغفَل: **الفراغُ المقصود**. مصفوفةٌ فارغة قرارٌ
 * صريح («نعمل سبعة أيام») لا قيمةٌ غيرُ مضبوطة، و`?:` كانت ستقلبها إلى
 * الجمعة.
 */
class DefaultShiftIsConfigurableTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    /** الأربعاء */
    private const WEDNESDAY = '2026-09-16';

    private const FRIDAY = '2026-09-18';

    private const SATURDAY = '2026-09-19';

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
        ]);
    }

    public function test_without_configuration_the_old_constants_still_hold(): void
    {
        $employee = $this->shiftlessEmployee();

        $day = $this->describe($employee, self::WEDNESDAY);

        $this->assertSame('08:00', $day['expected_check_in']->format('H:i'));
        $this->assertSame('16:00', $day['expected_check_out']->format('H:i'));
        $this->assertTrue($this->describe($employee, self::FRIDAY)['is_weekly_off']);
        $this->assertFalse($this->describe($employee, self::SATURDAY)['is_weekly_off']);
    }

    public function test_the_default_start_time_is_read_from_settings(): void
    {
        AttendanceSetting::query()->update(['default_shift_start' => '09:00:00']);

        $day = $this->describe($this->shiftlessEmployee(), self::WEDNESDAY);

        $this->assertSame('09:00', $day['expected_check_in']->format('H:i'));
        // والنهايةُ تُشتقّ: 09:00 + 8 ساعات
        $this->assertSame('17:00', $day['expected_check_out']->format('H:i'));
    }

    public function test_the_weekly_off_days_are_read_from_settings(): void
    {
        AttendanceSetting::query()->update(['default_weekly_off_days' => ['fri', 'sat']]);

        $employee = $this->shiftlessEmployee();

        $this->assertTrue($this->describe($employee, self::FRIDAY)['is_weekly_off']);
        $this->assertTrue($this->describe($employee, self::SATURDAY)['is_weekly_off'], 'السبت لم يُقرأ من الإعدادات');
        $this->assertFalse($this->describe($employee, self::WEDNESDAY)['is_weekly_off']);
    }

    public function test_an_explicitly_empty_weekend_means_a_seven_day_operation(): void
    {
        AttendanceSetting::query()->update(['default_weekly_off_days' => []]);

        $employee = $this->shiftlessEmployee();

        // الفراغُ المقصود لا يُقلب إلى الجمعة
        $this->assertFalse($this->describe($employee, self::FRIDAY)['is_weekly_off']);
    }

    public function test_an_assigned_shift_still_overrides_the_default(): void
    {
        AttendanceSetting::query()->update(['default_shift_start' => '09:00:00']);

        $shift = Shift::create(['name' => 'وردية مسائية']);
        ShiftDay::create([
            'shift_id' => $shift->id,
            'day' => 'wednesday',
            'working_day' => true,
            'start_time' => '14:00:00',
            'end_time' => '22:00:00',
        ]);

        $employee = $this->shiftlessEmployee();
        $employee->update(['shift_id' => $shift->id]);

        $day = $this->describe($employee->fresh(), self::WEDNESDAY);

        // الأخصُّ يغلب: صفُّ الوردية لا الافتراضي
        $this->assertSame('14:00', $day['expected_check_in']->format('H:i'));
        $this->assertSame('22:00', $day['expected_check_out']->format('H:i'));
    }

    // ════════════════════════════════════════════════════

    private function shiftlessEmployee(): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "default{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
        ]);
    }

    private function describe(Employee $employee, string $date): array
    {
        return app(AttendanceProcessorService::class)->describeDay($employee, Carbon::parse($date));
    }
}
