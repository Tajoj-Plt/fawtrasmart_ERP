<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendanceSetting;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * الحلقةُ السابعة: ما يُسلّمه الحضورُ إلى مسير الرواتب.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * الوصلةُ بين الوحدتين شيئان لا ثالث لهما: صفُّ `Deduction` معتمدٌ غيرُ
 * مُسوّى يقرؤه `PayrollCalculator` عبر `Employee::unsettledDeductions()`،
 * و`attendance_days.overtime_minutes` الذي يقرؤه `OvertimePayService`
 * ليكتب بند استحقاقٍ على القسيمة.
 *
 * فما يُقاس هنا هو **إنتاجُ** هذين لا استهلاكُهما: أن يوماً متأخراً يُخرِج
 * خصميةً بالقيمة الصحيحة وبالحالة التي تلتقطها الصرفة، وأن انصرافاً بعد
 * نهاية الوردية يُخرِج دقائق إضافي. واستهلاكُهما مُغطّىً في اختبارات
 * الرواتب القائمة، والختمُ نفسُه في [AttendanceStampCascadeTest].
 *
 * وصفوفُ اليوم تُكتب بـ`DB::table()` لا بالموديل — كما في نظائرها في
 * هذا المستودع. صبُّ `date` في إلوكوِنت يكتب `Y-m-d H:i:s` على sqlite
 * بينما عمودُ MySQL من نوع `DATE` يُطبّع القيمة، فلا يجد
 * `processEmployeeDay` صفَّه بمطابقةٍ حرفية ويحسب اليوم غياباً. أثرٌ
 * بيئيٌّ لا يقع على MySQL — والكتابةُ الخام تتفاداه.
 *
 * والحالةُ التي يسهل أن تُنسى: **ألّا يُخصَم مرّتين**. إعادةُ معالجة اليوم
 * — وهي تقع فعلاً: مرّةً عند الانصراف ومرّةً في الكرون الليلي — يجب أن
 * تُبقي خصميةً واحدة.
 */
class AttendancePayrollLinkageTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    /** الأربعاء — يومُ عملٍ في جدول الوردية أدناه. */
    private const WORK_DATE = '2026-09-16';

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
            'auto_deduction_enabled' => true,
            'late_deduction_amount' => 2.0,
            'deduction_calculation_type' => 'per_minute',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_late_stamp_produces_an_approved_unsettled_deduction(): void
    {
        $employee = $this->employee();

        // الوردية 08:00، والحضور 08:45 → تأخير 45 دقيقة (السماح 15 لا يُطرح)
        $day = $this->workDay($employee, '08:45:00', '16:00:00');

        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(45, (int) $day->late_minutes);
        $this->assertNotNull($day->deduction_id, 'اليوم لم يُربَط بخصمية');

        $deduction = Deduction::find($day->deduction_id);

        $this->assertSame('late', $deduction->deduction_type);
        $this->assertEqualsWithDelta(90.00, (float) $deduction->amount, 0.01, '45 دقيقة × 2.00');

        /*
            الحالةُ هي ما يلتقطه `Deduction::scopeUnsettled()` — وعليها
            وحدها يتوقّف وصولُ الخصمية إلى الصرفة. خصميةٌ بحالة `pending`
            تُنشأ وتُربَط باليوم ولا تُخصَم من راتبٍ أبداً.
        */
        $this->assertSame(Deduction::STATUS_APPROVED, $deduction->status);
        $this->assertEqualsWithDelta(90.00, $deduction->remaining_amount, 0.01);

        // وهذا هو الاستعلامُ بعينه الذي يبني به `PayrollCalculator` صرفته
        $this->assertTrue(
            $employee->unsettledDeductions()->where('id', $deduction->id)->exists(),
            'الخصمية لا يلتقطها استعلام الصرفة'
        );
    }

    public function test_reprocessing_the_same_day_does_not_deduct_twice(): void
    {
        $employee = $this->employee();

        $this->workDay($employee, '08:45:00', '16:00:00');

        $this->assertSame(1, Deduction::count());

        // الكرونُ الليلي يُعيد معالجة اليوم نفسه بعد ساعات
        Carbon::setTestNow(Carbon::parse('2026-09-17 00:05:00'));
        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse(self::WORK_DATE));

        $this->assertSame(1, Deduction::count(), 'خُصم مرّتين عن يومٍ واحد');
    }

    public function test_an_on_time_day_produces_no_deduction(): void
    {
        $employee = $this->employee();

        // 08:10 داخل السماح (15 دقيقة)
        $day = $this->workDay($employee, '08:10:00', '16:00:00');

        $this->assertSame(AttendanceDays::STATUS_PRESENT, $day->status);
        $this->assertNull($day->deduction_id);
        $this->assertSame(0, Deduction::count());
    }

    public function test_a_late_checkout_produces_the_overtime_minutes_payroll_reads(): void
    {
        $employee = $this->employee();

        $day = $this->workDay($employee, '08:00:00', '18:00:00');

        // 16:00 → 18:00 = ساعتان. `OvertimePayService::overtimeDays()` يبحث
        // عن `overtime_minutes > 0` بالضبط.
        $this->assertSame(120, (int) $day->overtime_minutes);
        $this->assertSame(0, (int) $day->early_leave_minutes);
        $this->assertSame(0, (int) $day->late_minutes);
    }

    public function test_an_early_checkout_is_recorded_beyond_its_threshold_only(): void
    {
        $employee = $this->employee();

        // 14:30 مقابل نهاية وردية 16:00 → 90 دقيقة، فوق عتبة الـ5
        $day = $this->workDay($employee, '08:00:00', '14:30:00');

        $this->assertSame(90, (int) $day->early_leave_minutes);
        $this->assertSame(0, (int) $day->overtime_minutes);

        // ودقيقتان قبل الموعد ليستا انصرافاً مبكراً — دون العتبة
        $other = $this->employee();
        $otherDay = $this->workDay($other, '08:00:00', '15:58:00');
        $this->assertSame(0, (int) $otherDay->early_leave_minutes);
    }

    // ════════════════════════════════════════════════════

    private function employee(): Employee
    {
        static $sequence = 0;
        $sequence++;

        $shift = Shift::create(['name' => "وردية {$sequence}"]);

        foreach (['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            ShiftDay::create([
                'shift_id' => $shift->id,
                'day' => $day,
                'working_day' => true,
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
            ]);
        }

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "payroll{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $shift->id,
            'attendance_channel' => 'both',
        ]);
    }

    /**
     * يومٌ كامل بحضورٍ وانصراف، ثم معالجتُه — وهو بالضبط ما يفعله
     * `AttendanceStampService` عند الانصراف وما يفعله الكرون ليلاً.
     */
    private function workDay(Employee $employee, string $checkIn, string $checkOut): AttendanceDays
    {
        $date = self::WORK_DATE;

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'check_in_method' => 'barcode',
            'check_in_channel' => 'kiosk',
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Carbon::setTestNow(Carbon::parse($date.' '.$checkOut));

        app(AttendanceProcessorService::class)->processEmployeeDay($employee, Carbon::parse($date));

        return AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
    }
}
