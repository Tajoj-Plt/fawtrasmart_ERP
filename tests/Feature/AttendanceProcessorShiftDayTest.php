<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ربطُ الوردية بيوم الأسبوع الصحيح — لا أوّل صفٍّ عشوائي.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `AttendanceProcessorService::getExpectedCheckIn/Out` كانت تنادي
 * `$shift?->shiftDays?->first()`. `shiftDays` غير معرَّفة على الموديل
 * (العلاقةُ الحقيقية اسمُها `days()`)، فتعود دوماً `null` — فيسقط جدولُ
 * الورديات كلُّه إلى الافتراضي الثابت `08:00` بصمت، أياً كان يوم الأسبوع
 * أو الوردية المخصَّصة للموظف.
 *
 * ويتحقّق هذا الاختبار أيضاً أن الإصلاح يقرأ التاريخ المُعالَج نفسَه —
 * لا `now()` — لأن معرفة «أيّ يوم أسبوع» هي بالضبط ما تحتاجه معالجةُ
 * يومٍ سابق (backfill)؛ وبلا ذلك لا معنى لاختيار صفّ الوردية الصحيح.
 *
 * الجداولُ تُبنى يدوياً كما في بقية اختبارات الحضور لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class AttendanceProcessorShiftDayTest extends TestCase
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
            $t->time('login_start_time')->nullable();
            $t->time('login_end_time')->nullable();
            $t->time('logout_start_time')->nullable();
            $t->time('logout_end_time')->nullable();
            $t->integer('grace_period')->nullable();
            $t->integer('delay_calculation')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('attendance_sheets_id')->nullable();
            $t->date('attendance_date');
            $t->string('status')->default('present');
            $t->time('start_shift')->nullable();
            $t->time('end_shift')->nullable();
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->time('login_time')->nullable();
            $t->time('logout_time')->nullable();
            $t->unsignedTinyInteger('absence_type')->nullable();
            $t->integer('absence_balance')->nullable();
            $t->string('check_in_method')->default('manual');
            $t->string('check_out_method')->default('manual');
            $t->boolean('scanned_via_barcode')->default(false);
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('deduction_id')->nullable();
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->unsignedSmallInteger('early_leave_minutes')->default(0);
            $t->unsignedSmallInteger('overtime_minutes')->default(0);
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
            $t->string('checkout_method')->default('hours');
            $t->integer('late_grace_minutes')->default(15);
            $t->integer('absent_after_minutes')->default(120);
            $t->boolean('photos_required')->default(false);
            $t->integer('photos_count_checkin')->nullable();
            $t->integer('photos_count_checkout')->nullable();
            $t->boolean('location_check_enabled')->default(false);
            $t->boolean('ip_check_enabled')->default(false);
            $t->integer('early_leave_threshold_minutes')->nullable();
            $t->boolean('allow_second_shift')->nullable();
            $t->boolean('allow_backdated_requests')->nullable();
            $t->boolean('direct_manager_approval')->nullable();
            $t->boolean('department_manager_approval')->nullable();
            $t->boolean('employees_approval')->nullable();
            $t->timestamps();
        });

        Schema::create('employee_attendance_configs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
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

        // المعالجُ يسأل عن أذون التأخير/الانصراف المبكر قبل أن يُعاقب
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
    }

    private function employeeWithShift(int $shiftId): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => 1,
            'shift_id' => $shiftId,
        ]);
    }

    public function test_the_expected_time_uses_the_shift_day_matching_the_weekday_being_processed(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);

        // نفسُ الوردية بأوقاتٍ مختلفة تماماً بين الأحد والاثنين — لو
        // اختار المعالجُ «أوّل صفٍّ» بدل صفّ اليوم الفعلي لأخطأ حتماً.
        \DB::table('shift_days')->insert([
            ['shift_id' => $shiftId, 'day' => 'sunday', 'working_day' => 1, 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['shift_id' => $shiftId, 'day' => 'monday', 'working_day' => 1, 'start_time' => '13:00:00', 'end_time' => '21:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // 2026-01-05 هو الإثنين تحديداً.
        $monday = Carbon::parse('2026-01-05');
        $this->assertSame('Monday', $monday->format('l'));

        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $monday->toDateString(),
            'check_in_time' => '13:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = (new AttendanceProcessorService)->processEmployeeDay($employee, $monday);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame('13:00:00', $day->expected_check_in, 'يجب أن يُقرأ صفّ الاثنين لا صفّ الأحد ولا 08:00 الافتراضي');
        $this->assertSame('present', $result['status'], 'الحضور 13:00 يطابق بداية وردية الاثنين تماماً — لا تأخير');
    }

    public function test_a_different_weekday_on_the_same_shift_uses_its_own_time(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);

        \DB::table('shift_days')->insert([
            ['shift_id' => $shiftId, 'day' => 'sunday', 'working_day' => 1, 'start_time' => '06:00:00', 'end_time' => '14:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['shift_id' => $shiftId, 'day' => 'monday', 'working_day' => 1, 'start_time' => '13:00:00', 'end_time' => '21:00:00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // 2026-01-04 هو الأحد.
        $sunday = Carbon::parse('2026-01-04');
        $this->assertSame('Sunday', $sunday->format('l'));

        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $sunday->toDateString(),
            'check_in_time' => '06:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AttendanceProcessorService)->processEmployeeDay($employee, $sunday);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame('06:00:00', $day->expected_check_in);
    }

    /** يومٌ لا وردية فيه ولا موظف مضبوطة له: يبقى الافتراضي 08:00 كما كان. */
    public function test_an_employee_without_a_shift_still_falls_back_to_the_default(): void
    {
        $employee = Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => 1,
            'shift_id' => null,
        ]);

        $date = Carbon::parse('2026-01-05');

        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'check_in_time' => '08:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AttendanceProcessorService)->processEmployeeDay($employee, $date);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame('08:00:00', $day->expected_check_in);
    }

    // ═══════════════ Backfill: التاريخ المُعالَج، لا now() ═══════════════

    /**
     * قبل هذا الإصلاح كان `getExpectedCheckIn` يبني الوقتَ المتوقَّع على
     * `now()->toDateString()` — تاريخ اليوم الحالي، لا اليوم الذي
     * يُعالَج. فمعالجةُ يومٍ من قبل ٤٠٠ يوم كانت تقارن وقتَ حضورٍ من ذلك
     * اليوم بوقتٍ متوقَّع مؤرَّخٍ باليوم الحالي — فرقُ **شهور** لا دقائق،
     * فيسقط شرطُ `checkInTime->gt($expectedIn)` دائماً ولا يُحتسب تأخيرٌ
     * إطلاقاً، أياً كانت ساعةُ الحضور الفعلية.
     */
    public function test_a_backfilled_past_day_computes_lateness_against_its_own_date_not_today(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);

        $pastDate = Carbon::now()->subDays(400)->startOfDay();
        $dayName = strtolower($pastDate->format('l'));

        \DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // حضورٌ متأخرٌ نصفَ ساعة عن بداية وردية ذلك اليوم بالذات — لا اليوم الحالي.
        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $pastDate->toDateString(),
            'check_in_time' => '09:30:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = (new AttendanceProcessorService)->processEmployeeDay($employee, $pastDate);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame('09:00:00', $day->expected_check_in, 'الوقتُ المتوقَّع يجب أن يُبنى على وردية ذلك اليوم لا وردية اليوم الحالي');
        $this->assertSame('late', $result['status'], 'حضورٌ متأخرٌ ٣٠ دقيقة قبل ٤٠٠ يوم يجب أن يُقرأ متأخراً — لا "في الوقت" لمجرد أن التاريخ في الماضي');
        $this->assertEqualsWithDelta(30, $day->late_minutes, 1, 'فرقُ الدقائق يجب أن يُحسب داخل اليوم نفسه لا بين تاريخين مختلفين بأشهر');
    }

    /** ونفسُ اليوم البعيد، بحضورٍ ضمن السماح، يبقى "حاضر" — لا غائبٌ ولا متأخر. */
    public function test_a_backfilled_past_day_within_grace_is_still_present(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);

        $pastDate = Carbon::now()->subDays(400)->startOfDay();
        $dayName = strtolower($pastDate->format('l'));

        \DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $pastDate->toDateString(),
            'check_in_time' => '09:05:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = (new AttendanceProcessorService)->processEmployeeDay($employee, $pastDate);

        $this->assertSame('present', $result['status']);
    }

    // ═══════════════ إشارة diffInMinutes — عطلٌ مستقل عن الـ backfill ═══════════════

    /**
     * اكتُشف أثناء كتابة اختبار الـ backfill أعلاه، وهو منفصلٌ عنه تماماً:
     * `Carbon 3` لا يُرجع `diffInMinutes()` فرقاً مطلقاً بعد الآن — يُرجع
     * فرقاً بإشارة، فيخرج سالباً هنا لأن `checkInTime` بعد `expectedIn`
     * دائماً في حالة التأخير. وبلا `abs()` فإن `سالب <= graceMinutes`
     * يصحّ دوماً، فلا يُصنَّف أيُّ حضورٍ متأخرٍ "متأخراً" مهما بلغ تأخرُه —
     * ولو كان التاريخ المُعالَج هو اليوم نفسه، بلا أيّ backfill.
     *
     * يُختبر هنا بتاريخٍ غير مؤجَّل عمداً، ليبقى هذا العطلُ مغطّىً حتى لو
     * أُصلح خطأ now()/$date يوماً بطريقةٍ أخرى.
     */
    public function test_late_minutes_is_a_positive_count_regardless_of_carbon_diff_direction(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);

        $today = Carbon::now()->startOfDay();
        $dayName = strtolower($today->format('l'));

        \DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $today->toDateString(),
            'check_in_time' => '09:45:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = (new AttendanceProcessorService)->processEmployeeDay($employee, $today);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertGreaterThan(0, $day->late_minutes, 'يجب أن يكون عدد دقائق التأخير موجباً لا سالباً');
        $this->assertSame(45, $day->late_minutes);
        $this->assertSame('late', $result['status']);
    }

    // ═══════════════ وردية تعبر منتصف الليل ═══════════════

    /**
     * وردية 22:00 → 06:00: قبل هذا الإصلاح كان expected_check_out يُبنى
     * على نفس يوم الحضور (06:00 من نفس اليوم) — زمنياً **قبل** 22:00 —
     * فتقارَن أوقاتٌ من يومين مختلفين وكأنهما على نفس السطر الزمني.
     */
    public function test_an_overnight_shift_on_time_reads_as_present(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية ليلية', 'created_at' => now(), 'updated_at' => now()]);

        $date = Carbon::parse('2026-01-05'); // إثنين
        $dayName = strtolower($date->format('l'));

        \DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'check_in_time' => '22:00:00',
            'check_out_time' => '06:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = (new AttendanceProcessorService)->processEmployeeDay($employee, $date);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame('present', $result['status']);
        $this->assertSame(0, $day->late_minutes);
        $this->assertSame(0, $day->overtime_minutes);
        $this->assertSame(0, $day->early_leave_minutes);
    }

    public function test_leaving_early_on_an_overnight_shift_is_measured_correctly(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية ليلية', 'created_at' => now(), 'updated_at' => now()]);

        $date = Carbon::parse('2026-01-05');
        $dayName = strtolower($date->format('l'));

        \DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // انصرف الساعة 05:00 — قبل نهاية الوردية بساعة، لا 17 ساعة كما لو
        // قُورن بـ06:00 من نفس يوم الحضور.
        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'check_in_time' => '22:00:00',
            'check_out_time' => '05:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AttendanceProcessorService)->processEmployeeDay($employee, $date);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame(60, $day->early_leave_minutes);
        $this->assertSame(0, $day->overtime_minutes);
    }

    public function test_staying_late_on_an_overnight_shift_is_measured_as_overtime_not_a_negative_day(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية ليلية', 'created_at' => now(), 'updated_at' => now()]);

        $date = Carbon::parse('2026-01-05');
        $dayName = strtolower($date->format('l'));

        \DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // انصرف الساعة 07:00 — ساعة إضافية بعد نهاية الوردية، لا خطأ سالب.
        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'check_in_time' => '22:00:00',
            'check_out_time' => '07:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AttendanceProcessorService)->processEmployeeDay($employee, $date);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame(60, $day->overtime_minutes);
        $this->assertSame(0, $day->early_leave_minutes);
    }

    /**
     * موظفٌ ترك وردية 22:00 → 06:00 عند 23:00 — قبل منتصف الليل بالذات، لا
     * بعده. هنا يظهر العطلُ الحقيقي: بلا هذا الإصلاح، expected_check_out
     * غير المُرحَّل (06:00 من **نفس** يوم الحضور) يقع زمنياً **قبل** وقت
     * الانصراف الفعلي 23:00 على نفس اليوم، فيُقرأ ذلك "إضافياً" — نحو 17
     * ساعة عمل إضافي — بدل انصرافٍ مبكرٍ بمقدار 7 ساعات فعلياً.
     */
    public function test_leaving_before_midnight_on_an_overnight_shift_is_early_leave_not_massive_overtime(): void
    {
        $shiftId = \DB::table('shifts')->insertGetId(['name' => 'وردية ليلية', 'created_at' => now(), 'updated_at' => now()]);

        $date = Carbon::parse('2026-01-05');
        $dayName = strtolower($date->format('l'));

        \DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        \DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
            'check_in_time' => '22:00:00',
            'check_out_time' => '23:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new AttendanceProcessorService)->processEmployeeDay($employee, $date);

        $day = \DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertSame(420, $day->early_leave_minutes, 'سبع ساعات انصرافاً مبكراً (23:00 إلى 06:00 اليوم التالي)');
        $this->assertSame(0, $day->overtime_minutes, 'لا يجوز أن يُقرأ هذا إضافياً بأي مقدار');
    }
}
