<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `AttendanceDays::working_hours` — سالبة دوماً لوردية عادية، مقلوبة لوردية ليلية.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * كانت `getWorkingHoursAttribute()` تُحسب بـ`Carbon::parse($time)` بلا
 * تاريخ (أي على تاريخ اللحظة الحالية ضمناً لا `attendance_date`)، وبلا
 * قيمةٍ مطلقة. فـCarbon 3 لا يُرجع `diffInMinutes()` فرقاً مطلقاً كما في
 * Carbon 2 — فكل وردية عادية (08:00 → 16:00) كانت تُرجع دقائق **سالبة**،
 * ووردية تعبر منتصف الليل (22:00 → 06:00) كانت تُرجع **16 ساعة بدل 8**
 * لأن الانصراف يُقرأ على نفس يوم الحضور لا اليوم التالي.
 *
 * هذا المُسنِد يُستعمل فعلياً في رسالة الانصراف الفورية
 * (`BarcodeAttendanceController::checkOut`) وفي عدّة إحصاءات
 * (`detailedStats`, `employeeAttendanceReport`, تصدير Excel).
 */
class AttendanceDaysWorkingHoursTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->timestamps();
        });
    }

    public function test_a_regular_shift_returns_a_positive_duration(): void
    {
        $day = AttendanceDays::create([
            'employee_id' => 1,
            'attendance_date' => '2026-01-05',
            'check_in_time' => '08:00:00',
            'check_out_time' => '16:00:00',
        ]);

        $this->assertSame(480, $day->working_hours, 'ثماني ساعات = 480 دقيقة، لا -480');
    }

    public function test_an_overnight_shift_computes_the_correct_duration(): void
    {
        $day = AttendanceDays::create([
            'employee_id' => 1,
            'attendance_date' => '2026-01-05',
            'check_in_time' => '22:00:00',
            'check_out_time' => '06:00:00',
        ]);

        $this->assertSame(480, $day->working_hours, 'ثماني ساعات عبر منتصف الليل، لا 16 ساعة مقلوبة');
    }

    public function test_a_short_overnight_shift_is_still_correct(): void
    {
        $day = AttendanceDays::create([
            'employee_id' => 1,
            'attendance_date' => '2026-01-05',
            'check_in_time' => '23:30:00',
            'check_out_time' => '00:15:00',
        ]);

        $this->assertSame(45, $day->working_hours);
    }

    public function test_missing_check_out_returns_zero(): void
    {
        $day = AttendanceDays::create([
            'employee_id' => 1,
            'attendance_date' => '2026-01-05',
            'check_in_time' => '08:00:00',
            'check_out_time' => null,
        ]);

        $this->assertSame(0, $day->working_hours);
    }
}
