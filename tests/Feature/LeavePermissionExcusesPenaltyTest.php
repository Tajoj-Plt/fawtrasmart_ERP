<?php

namespace Tests\Feature;

use App\Models\AttendanceDays;
use App\Models\Employee;
use App\Models\LeavePermissions;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * أذونُ الإجازة تُفسِّر فروقاتِ اليوم فعلاً — لا نصّاً.
 *
 * **ما كان**
 *
 * `AttendanceProcessorService` لا يقرأ `leave_permissions` إطلاقاً. يُصدر
 * المسؤولُ إذنَ «وصولٍ متأخر» للموظف، ثم يأتي الكرونُ ليلاً فيحسب التأخير
 * كاملاً، ويكتب حالةَ «متأخر»، **ويُنشئ عليه خصمية**. فالإذنُ ورقةٌ في
 * شاشةٍ لا أثرَ لها في حساب.
 *
 * و`AttendanceStampService` كانت تعرف بالإذن لكنها تكتفي بتنبيهٍ نصّيّ
 * للموظف لحظةَ المسح — كلامٌ لا يمنع خصماً.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن الإذنَ يرفع العقوبة: الحالةُ «حاضر» ولا خصم. وأن الدقائقَ تبقى
 * مكتوبةً كما وقعت — الإذنُ يرفع العقوبةَ لا يُزوّر الواقعة. وأن النوعين
 * لا يُخلطان: إذنُ انصرافٍ مبكر لا يُبرّر تأخيرَ الحضور.
 */
class LeavePermissionExcusesPenaltyTest extends TestCase
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
            $t->boolean('auto_deduction_enabled')->default(true);
            $t->decimal('late_deduction_amount', 10, 2)->default(50);
            $t->decimal('absence_deduction_amount', 10, 2)->default(100);
            $t->string('deduction_calculation_type')->default('fixed');
            $t->decimal('work_hours_per_day', 5, 2)->default(8);
            $t->integer('late_grace_minutes')->default(15);
            $t->integer('absent_after_minutes')->default(240);
            $t->integer('early_leave_threshold_minutes')->default(10);
            $t->json('default_weekly_off_days')->nullable();
            $t->timestamps();
        });

        Schema::create('employee_attendance_configs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
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
            $t->date('submission_date')->nullable();
            $t->text('notes')->nullable();
            $t->string('attachments')->nullable();
            $t->timestamps();
        });

        foreach (['holidays' => function (Blueprint $t) {
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
        }, 'leave_requests' => function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default('pending');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        }, 'shifts' => function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->timestamps();
        }, 'shift_days' => function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('shift_id');
            $t->string('day');
            $t->boolean('working_day')->default(true);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->timestamps();
        }] as $table => $definition) {
            Schema::create($table, $definition);
        }

        Schema::create('deductions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('deduction_type')->nullable();
            $t->decimal('amount', 15, 2)->default(0);
            $t->decimal('settled_amount', 15, 2)->default(0);
            $t->string('status')->nullable();
            $t->date('date')->nullable();
            $t->text('reason')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by_user')->nullable();
            $t->unsignedBigInteger('approved_by_user')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('deduction_histories', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('deduction_id');
            $t->string('action')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->text('description')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });

        DB::table('attendance_settings')->insert([
            'start_month' => 1, 'start_day' => 1,
            'auto_deduction_enabled' => true,
            'late_deduction_amount' => 50,
            'absence_deduction_amount' => 100,
            'deduction_calculation_type' => 'fixed',
            'work_hours_per_day' => 8,
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 240,
            'early_leave_threshold_minutes' => 10,
            'default_weekly_off_days' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => 1,
        ]);
    }

    private function permit(Employee $employee, int $leaveType, string $date): void
    {
        LeavePermissions::create([
            'employee_id' => $employee->id,
            'leave_type' => $leaveType,
            'start_date' => $date,
            'end_date' => $date,
            'type' => 1,
        ]);
    }

    /** يومٌ بتأخيرٍ يتجاوز السماح — بلا إذن: متأخر ومخصوم. */
    public function test_without_a_permit_a_late_day_is_penalised_and_deducted(): void
    {
        $employee = $this->employee();

        // الوردية 08:00 والسماح ١٥ دقيقة
        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'check_in_time' => '09:00:00',
            'check_out_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        $day = AttendanceDays::first();

        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(60, $day->late_minutes);
        $this->assertNotNull($day->deduction_id, 'بلا إذنٍ يُنشأ الخصم');
    }

    /** وبإذن «وصولٍ متأخر»: حاضرٌ بلا خصم — والدقائقُ مكتوبةٌ كما وقعت. */
    public function test_a_late_arrival_permit_lifts_the_status_and_the_deduction(): void
    {
        $employee = $this->employee();
        $this->permit($employee, LeavePermissions::LATE_ARRIVAL, '2026-01-05');

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'check_in_time' => '09:00:00',
            'check_out_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        $day = AttendanceDays::first();

        $this->assertSame(AttendanceDays::STATUS_PRESENT, $day->status, 'الإذنُ يرفع حالةَ «متأخر»');
        $this->assertSame(60, $day->late_minutes, 'والدقائقُ تبقى كما وقعت — الإذنُ يرفع العقوبةَ لا الواقعة');
        $this->assertNull($day->deduction_id, 'ولا خصمَ على تأخيرٍ مأذونٍ فيه');
        $this->assertSame(0, DB::table('deductions')->count());
    }

    /** وإذنُ الانصراف المبكر لا يُبرّر تأخيرَ الحضور — النوعان لا يُخلطان. */
    public function test_an_early_leave_permit_does_not_excuse_a_late_arrival(): void
    {
        $employee = $this->employee();
        $this->permit($employee, LeavePermissions::EARLY_LEAVE, '2026-01-05');

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'check_in_time' => '09:00:00',
            'check_out_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        $day = AttendanceDays::first();

        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertNotNull($day->deduction_id);
    }

    /** وإذنُ «انصرافٍ مبكر» يرفع احتسابَ الدقائق المبكرة. */
    public function test_an_early_leave_permit_clears_the_early_leave_minutes(): void
    {
        $employee = $this->employee();

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'check_in_time' => '08:00:00',
            'check_out_time' => '15:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        $this->assertSame(60, AttendanceDays::first()->early_leave_minutes);

        // نفسُ اليوم بإذنٍ هذه المرّة
        $withPermit = $this->employee();
        $this->permit($withPermit, LeavePermissions::EARLY_LEAVE, '2026-01-05');

        DB::table('attendance_days')->insert([
            'employee_id' => $withPermit->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'check_in_time' => '08:00:00',
            'check_out_time' => '15:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($withPermit, Carbon::parse('2026-01-05'));

        $day = AttendanceDays::where('employee_id', $withPermit->id)->first();

        $this->assertSame(0, $day->early_leave_minutes, 'الإذنُ يرفع احتسابَ الانصراف المبكر');
    }

    /** والإذنُ يوماً آخرَ لا يشمل هذا اليوم. */
    public function test_a_permit_on_another_day_does_not_apply(): void
    {
        $employee = $this->employee();
        $this->permit($employee, LeavePermissions::LATE_ARRIVAL, '2026-01-04');

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'check_in_time' => '09:00:00',
            'check_out_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(AttendanceProcessorService::class)
            ->processEmployeeDay($employee, Carbon::parse('2026-01-05'));

        $this->assertSame(AttendanceDays::STATUS_LATE, AttendanceDays::first()->status);
    }
}
