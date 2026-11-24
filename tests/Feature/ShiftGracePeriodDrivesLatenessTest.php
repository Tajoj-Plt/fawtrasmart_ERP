<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use App\Models\Employee;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * مهلةُ التأخير المعرَّفة في شاشة الورديات تحكم الحساب فعلاً.
 *
 * **ما كان**
 *
 * شاشةُ «إدارة الورديات» تُدخل `grace_period` لكل يومٍ من أيام الوردية،
 * وتُخزّنه، وتعرضه في صفحة الوردية («فترة السماح: ٣٠ دقيقة») — ولا
 * يقرؤه `AttendanceProcessorService` قط. سلّمُ المهلة كان: إعدادُ الموظف
 * ← إعدادُ المنشأة، وصفُّ الوردية ليس فيه.
 *
 * فمن ضبط مهلةَ ورديةٍ على ثلاثين دقيقة يجد موظفيها «متأخرين» بعد
 * خمسَ عشرة — وهي مهلةُ المنشأة العامّة — بلا ما يدلّه على السبب. وهي
 * الشاشةُ التي وُصفت بأنها «المرجعُ البرمجي المحرّك لصفحة أيام الحضور».
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن السلّم صار: إعدادُ الموظف ← صفُّ الوردية ← إعدادُ المنشأة. وأن
 * الصفرَ مهلةٌ صريحة لا غياب.
 */
class ShiftGracePeriodDrivesLatenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
            $t->tinyInteger('status')->default(1);
            $t->unsignedBigInteger('shift_id')->nullable();
            $t->date('hire_date')->nullable();
            $t->date('termination_date')->nullable();
            $t->date('last_working_day')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('shifts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->string('type')->nullable();
            $t->timestamps();
        });

        Schema::create('shift_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('shift_id');
            $t->string('day');
            $t->boolean('working_day')->default(true);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->time('logout_start_time')->nullable();
            $t->time('logout_end_time')->nullable();
            $t->integer('grace_period')->nullable();
            $t->integer('delay_calculation')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->string('status')->default('present');
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->string('check_in_method')->default('manual');
            $t->string('check_out_method')->default('manual');
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('deduction_id')->nullable();
            $t->integer('late_minutes')->default(0);
            $t->integer('early_leave_minutes')->default(0);
            $t->integer('overtime_minutes')->default(0);
            $t->time('expected_check_in')->nullable();
            $t->time('expected_check_out')->nullable();
            $t->boolean('is_holiday')->default(false);
            $t->boolean('is_weekly_off')->default(false);
            $t->boolean('is_on_leave')->default(false);
            $t->unsignedBigInteger('leave_request_id')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->boolean('auto_generated')->default(false);
            $t->timestamps();
            $t->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('attendance_settings', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('start_month')->nullable();
            $t->integer('start_day')->nullable();
            $t->boolean('auto_deduction_enabled')->default(false);
            $t->decimal('late_deduction_amount', 10, 2)->default(0);
            $t->decimal('absence_deduction_amount', 10, 2)->default(0);
            $t->string('deduction_calculation_type')->default('fixed');
            $t->decimal('work_hours_per_day', 5, 2)->default(8);
            $t->integer('late_grace_minutes')->default(15);
            $t->integer('absent_after_minutes')->default(240);
            $t->integer('early_leave_threshold_minutes')->default(0);
            $t->json('default_weekly_off_days')->nullable();
            $t->timestamps();
        });

        Schema::create('employee_attendance_configs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->integer('late_grace_minutes')->nullable();
            $t->timestamps();
        });

        // الوردياتُ المخصَّصة: تُسأل قبل الوردِيّة الدائمة في كل قياسِ يوم
        Schema::create('custom_shifts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->date('from_date')->nullable();
            $t->date('to_date')->nullable();
            $t->unsignedBigInteger('shift_id')->nullable();
            $t->boolean('is_main')->default(0);
            $t->timestamps();
        });

        Schema::create('custom_shifts_employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('custom_shifts_id');
            $t->unsignedBigInteger('employee_id');
            $t->timestamps();
        });

        Schema::create('leave_permissions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->unsignedTinyInteger('type')->nullable();
            $t->unsignedTinyInteger('leave_type')->nullable();
            $t->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default('pending');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });

        Schema::create('holidays', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('holiday_list_id')->nullable();
            $t->date('holiday_date')->nullable();
            $t->string('named')->nullable();
            $t->string('recurrence_type')->nullable();
            $t->integer('recurrence_day_of_week')->nullable();
            $t->integer('recurrence_day_of_month')->nullable();
            $t->integer('recurrence_month')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_paid')->default(true);
            $t->timestamps();
        });

        DB::table('attendance_settings')->insert([
            'start_month' => 1, 'start_day' => 1,
            'late_grace_minutes' => 15,      // مهلةُ المنشأة العامّة
            'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** وردية 08:00 على الإثنين بمهلةٍ معيَّنة. */
    private function employeeOnShiftWithGrace(?int $grace): Employee
    {
        $shiftId = DB::table('shifts')->insertGetId([
            'name' => 'وردية', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => 'monday', 'working_day' => 1,
            'start_time' => '08:00:00', 'end_time' => '16:00:00',
            'grace_period' => $grace,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => 1,
            'shift_id' => $shiftId,
        ]);
    }

    /** 2026-01-05 هو الإثنين. */
    private function processAt(Employee $employee, string $checkIn): AttendanceDays
    {
        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'check_in_time' => $checkIn,
            'check_out_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        return AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
    }

    /**
     * إعدادُ المنشأة سقفٌ: ورديةٌ بمهلة 30 لا تُرخِّص فوق مهلة المنشأة 15.
     * الحضور 08:25 → متأخّر (كان يُقرأ حاضراً حين كانت الوردية تعلو).
     */
    public function test_company_grace_caps_a_looser_shift_grace(): void
    {
        $employee = $this->employeeOnShiftWithGrace(30);

        $day = $this->processAt($employee, '08:25:00');

        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(25, $day->late_minutes, 'الدقائقُ تُسجَّل خاماً على كل حال');
    }

    /** والورديةُ تشدِّد: منشأة 30 ووردية 10 → الحضور 08:15 متأخّر. */
    public function test_a_stricter_shift_grace_tightens_below_company(): void
    {
        DB::table('attendance_settings')->update(['late_grace_minutes' => 30]);

        $employee = $this->employeeOnShiftWithGrace(10);

        $this->assertSame(
            AttendanceDays::STATUS_LATE,
            $this->processAt($employee, '08:15:00')->status
        );
    }

    /** وبلا مهلةٍ على الوردية تُقرأ مهلةُ المنشأة (١٥): 08:25 تأخير. */
    public function test_without_a_shift_grace_the_company_default_applies(): void
    {
        $employee = $this->employeeOnShiftWithGrace(null);

        $this->assertSame(
            AttendanceDays::STATUS_LATE,
            $this->processAt($employee, '08:25:00')->status
        );
    }

    /** والصفرُ على الوردية يشدِّد إلى لا تسامح — حتى لو منشأة 15. */
    public function test_a_zero_shift_grace_means_no_tolerance_not_a_missing_value(): void
    {
        $employee = $this->employeeOnShiftWithGrace(0);

        $this->assertSame(
            AttendanceDays::STATUS_LATE,
            $this->processAt($employee, '08:05:00')->status,
            'خمسُ دقائق تأخيرٌ حين تكون المهلةُ صفراً — ولا تسقط إلى مهلة المنشأة'
        );
    }

    /** وإعدادُ الموظف يعلو على الاثنين. */
    public function test_the_employee_override_outranks_the_shift(): void
    {
        $employee = $this->employeeOnShiftWithGrace(30);

        DB::table('employee_attendance_configs')->insert([
            'employee_id' => $employee->id,
            'late_grace_minutes' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(
            AttendanceDays::STATUS_LATE,
            $this->processAt($employee->fresh(), '08:10:00')->status
        );
    }
}
