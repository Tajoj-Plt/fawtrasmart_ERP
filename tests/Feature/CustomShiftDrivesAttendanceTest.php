<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use App\Models\CustomShift;
use App\Models\Employee;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * الورديةُ المخصَّصة تحكم قياسَ اليوم.
 *
 * **ما كان**
 *
 * لا ذِكرَ لـ`CustomShift` في `app/Services` كلِّه. الحسابُ يقرأ
 * `employees.shift_id` وحدَه — الوردِيّةَ الدائمة — فشاشةُ «ورديات
 * مخصصة» بأكملها تُسنِد الورديّاتِ المتناوبة بمدّة، وتُدار، وتُحذف، ولا
 * أثرَ لها في حسابٍ واحد.
 *
 * والنتيجةُ العملية: من أُسنِدت إليه الورديّةُ الليلية (22:00) أسبوعاً
 * يُقاس على ورديّته النهارية (08:00)، فيُقرأ متأخّراً أربعَ عشرة ساعةً
 * كلَّ ليلة — أو غائباً إن تجاوز عتبةَ الغياب.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن الإسنادَ المخصَّص يعلو على الدائم داخل مدّته، ويعود الدائمُ خارجها.
 * وأن «رئيسي» يحسم التداخل. وأن من لا وردِيّةَ دائمةَ له يُقاس على
 * المخصَّصة لا على بداية دوام المنشأة.
 */
class CustomShiftDrivesAttendanceTest extends TestCase
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
            $t->timestamps();
        });

        Schema::create('shift_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('shift_id');
            $t->string('day');
            $t->boolean('working_day')->default(true);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->integer('grace_period')->nullable();
            $t->timestamps();
        });

        Schema::create('custom_shifts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->date('from_date');
            $t->date('to_date');
            $t->unsignedBigInteger('shift_id');
            $t->tinyInteger('use_rules')->default(1);
            $t->boolean('is_main')->default(0);
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->unsignedBigInteger('shift_rule_id')->nullable();
            $t->timestamps();
        });

        Schema::create('custom_shifts_employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('custom_shifts_id');
            $t->unsignedBigInteger('employee_id');
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
            $t->integer('absent_after_minutes')->default(1000);
            $t->integer('early_leave_threshold_minutes')->default(0);
            $t->string('default_shift_start')->nullable();
            $t->json('default_weekly_off_days')->nullable();
            $t->timestamps();
        });

        Schema::create('employee_attendance_configs', function (Blueprint $t) {
            $t->bigIncrements('id');
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
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 1000,   // لا يُقلب التأخيرُ غياباً في هذا الاختبار
            'work_hours_per_day' => 8,
            'default_shift_start' => '09:00:00',
            'default_weekly_off_days' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** وردِيّةٌ تبدأ في وقتٍ معيَّن على الإثنين. */
    private function shift(string $start, string $end): int
    {
        $id = DB::table('shifts')->insertGetId([
            'name' => 'وردية '.$start, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('shift_days')->insert([
            'shift_id' => $id, 'day' => 'monday', 'working_day' => 1,
            'start_time' => $start, 'end_time' => $end, 'grace_period' => 15,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function employee(?int $shiftId): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => 1,
            'shift_id' => $shiftId,
        ]);
    }

    private function assign(Employee $employee, int $shiftId, string $from, string $to, bool $isMain = false): CustomShift
    {
        $custom = CustomShift::create([
            'name' => 'إسناد', 'from_date' => $from, 'to_date' => $to,
            'shift_id' => $shiftId, 'use_rules' => 2, 'is_main' => $isMain,
        ]);

        $custom->employees()->attach($employee->id);

        return $custom;
    }

    /** 2026-01-05 هو الإثنين. */
    private function expectedCheckIn(Employee $employee, string $date = '2026-01-05'): ?string
    {
        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'status' => 'present',
            'check_in_time' => '22:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse($date));

        return AttendanceDays::where('employee_id', $employee->id)
            ->where('attendance_date', 'like', substr($date, 0, 10).'%')
            ->value('expected_check_in');
    }

    /** الإسنادُ المخصَّص يعلو على الوردِيّة الدائمة داخل مدّته. */
    public function test_a_custom_assignment_outranks_the_permanent_shift(): void
    {
        $dayShift = $this->shift('08:00:00', '16:00:00');
        $nightShift = $this->shift('22:00:00', '06:00:00');

        $employee = $this->employee($dayShift);
        $this->assign($employee, $nightShift, '2026-01-01', '2026-01-31');

        $this->assertSame('22:00:00', $this->expectedCheckIn($employee));
    }

    /** ومن حضر في موعد ورديّته الليلية ليس متأخّراً. */
    public function test_the_night_worker_is_not_marked_late(): void
    {
        $dayShift = $this->shift('08:00:00', '16:00:00');
        $nightShift = $this->shift('22:00:00', '06:00:00');

        $employee = $this->employee($dayShift);
        $this->assign($employee, $nightShift, '2026-01-01', '2026-01-31');

        $this->expectedCheckIn($employee);

        $day = AttendanceDays::where('employee_id', $employee->id)->first();

        $this->assertSame(AttendanceDays::STATUS_PRESENT, $day->status);
        $this->assertSame(0, $day->late_minutes, 'كان يُقرأ متأخّراً أربعَ عشرة ساعة');
    }

    /** وخارج المدّة تعود الوردِيّةُ الدائمة. */
    public function test_outside_the_assignment_window_the_permanent_shift_returns(): void
    {
        $dayShift = $this->shift('08:00:00', '16:00:00');
        $nightShift = $this->shift('22:00:00', '06:00:00');

        $employee = $this->employee($dayShift);
        $this->assign($employee, $nightShift, '2026-01-01', '2026-01-04');

        // 2026-01-05 خارج المدّة
        $this->assertSame('08:00:00', $this->expectedCheckIn($employee));
    }

    /** وإسنادُ زميلٍ آخر لا يمسّ هذا الموظف. */
    public function test_an_assignment_for_someone_else_does_not_apply(): void
    {
        $dayShift = $this->shift('08:00:00', '16:00:00');
        $nightShift = $this->shift('22:00:00', '06:00:00');

        $employee = $this->employee($dayShift);
        $colleague = $this->employee($dayShift);
        $this->assign($colleague, $nightShift, '2026-01-01', '2026-01-31');

        $this->assertSame('08:00:00', $this->expectedCheckIn($employee));
    }

    /** وعند التداخل: «رئيسي» يحسم. */
    public function test_a_main_assignment_wins_over_a_secondary_overlap(): void
    {
        $dayShift = $this->shift('08:00:00', '16:00:00');
        $nightShift = $this->shift('22:00:00', '06:00:00');
        $eveningShift = $this->shift('14:00:00', '22:00:00');

        $employee = $this->employee($dayShift);

        // الفرعيُّ أحدثُ إسناداً، والرئيسيُّ أقدم — و«رئيسي» يعلو
        $this->assign($employee, $nightShift, '2026-01-01', '2026-01-31', isMain: true);
        $this->assign($employee, $eveningShift, '2026-01-01', '2026-01-31', isMain: false);

        $this->assertSame('22:00:00', $this->expectedCheckIn($employee));
    }

    /** ومن لا وردِيّةَ دائمةَ له يُقاس على المخصَّصة لا على دوام المنشأة. */
    public function test_an_employee_without_a_permanent_shift_still_follows_the_assignment(): void
    {
        $nightShift = $this->shift('22:00:00', '06:00:00');

        $employee = $this->employee(null);
        $this->assign($employee, $nightShift, '2026-01-01', '2026-01-31');

        $this->assertSame(
            '22:00:00',
            $this->expectedCheckIn($employee),
            'كان يهبط إلى بداية دوام المنشأة (09:00)'
        );
    }
}
