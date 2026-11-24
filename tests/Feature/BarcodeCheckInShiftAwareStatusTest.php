<?php

namespace Tests\Feature;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\BarcodeAttendanceController;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * حالةُ الحضور الفورية عند مسح الباركود تعتمد على الوردية الفعلية —
 * لا عتبة 08:00 ثابتة.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `checkIn()` كان يقارن وقت المسح بـ`'08:00:00'` مكتوبةً حرفياً في
 * الكود، بصرف النظر عن وردية الموظف الفعلية أو يوم الأسبوع — فموظفُ
 * وردية 14:00 يُقرأ "متأخراً" لو مسح باركوده الساعة 08:05 رغم أنه مبكرٌ
 * بست ساعات فعلياً. صار يستخدم
 * `AttendanceProcessorService::determineCheckInStatus()` — نفس منطق
 * `processEmployeeDay` بالضبط (الوردية الفعلية، ودقائق السماح، وعتبة
 * اعتبار التأخير غياباً).
 *
 * الجداولُ تُبنى يدوياً كما في بقية اختبارات الحضور لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class BarcodeCheckInShiftAwareStatusTest extends TestCase
{
    use BuildsAttendanceStampSchema;

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
            $t->boolean('scanned_via_barcode')->default(false);
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->unsignedSmallInteger('early_leave_minutes')->default(0);
            $t->unsignedSmallInteger('overtime_minutes')->default(0);
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
            $t->timestamps();
        });

        Schema::create('employee_attendance_configs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->timestamps();
        });

        /*
            checkIn() صار يمرّ ببوّابة محدِّدات الحضور (AttendanceEntryGuard)
            وبمُسجِّل الصور (AttendancePhotoRecorder) قبل أن يكتب اليوم. كلاهما
            يستعلم عن جداولٍ لم يكن هذا المخطَّط اليدوي يبنيها، فيسقط الطلب
            بـ500 قبل أن يبلغ الحارس المقصود بالاختبار.
        */
        Schema::create('holy_day_list_customizes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedTinyInteger('use_rules')->default(1);
            $t->unsignedBigInteger('holiday_list_id')->nullable();
            $t->unsignedBigInteger('attendance_determinant_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('job_title_id')->nullable();
            $t->timestamps();
        });

        Schema::create('holy_day_list_customize_employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('holyday_customizes_id');
            $t->unsignedBigInteger('employee_id');
            $t->timestamps();
        });

        Schema::create('attendance_determinants', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->tinyInteger('status')->default(0);
            $t->boolean('enable_ip_verification')->default(false);
            $t->tinyInteger('ip_investigation')->nullable();
            $t->text('allowed_ips')->nullable();
            $t->boolean('enable_location_verification')->default(false);
            $t->tinyInteger('location_investigation')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->integer('radius')->nullable();
            $t->tinyInteger('radius_type')->nullable();
            $t->boolean('capture_employee_image')->default(false);
            $t->tinyInteger('image_investigation')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_photo_types', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('code')->nullable();
            $t->string('name')->nullable();
            $t->string('icon')->nullable();
            $t->text('description')->nullable();
            $t->string('phase')->default('both');
            $t->boolean('is_active')->default(true);
            $t->boolean('is_required')->default(false);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('attendance_photos', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('attendance_day_id')->nullable();
            $t->unsignedBigInteger('photo_type_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->string('phase')->nullable();
            $t->string('file_path')->nullable();
            $t->string('original_name')->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->string('mime_type')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('ip_address')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // ما ينقص من جداول الحضور وأعمدتها الجديدة — إكمالاً لا إنشاء
        $this->createAttendanceStampSchema();
    }

    private function controller(): BarcodeAttendanceController
    {
        return new class extends BarcodeAttendanceController
        {
            public function __construct() {}
        };
    }

    private function employeeWithShift(int $shiftId): Employee
    {
        return Employee::create([
            'first_name' => 'سالم', 'email' => 'e'.uniqid().'@example.test',
            'status' => 1, 'shift_id' => $shiftId,
            'barcode' => 'CARD_'.uniqid(),
        ]);
    }

    private function checkIn(int $employeeId): array
    {
        $request = $this->makePairedKioskRequest('/', 'POST', [
            'employee_id' => $employeeId,
            // الباركودُ هو البيّنة — لا اسمُ الوسيلة
            'barcode' => Employee::find($employeeId)?->barcode,
        ]);
        $response = $this->controller()->checkIn($request);

        return [$response->getStatusCode(), json_decode($response->getContent(), true)];
    }

    /**
     * موظفٌ ورديته 14:00 يمسح باركوده 08:05 — مبكرٌ بست ساعات فعلياً.
     * العتبة الثابتة القديمة كانت تقرأ هذا "متأخراً" لأن 08:05 > 08:00.
     */
    public function test_an_early_afternoon_shift_employee_is_not_marked_late_for_a_morning_scan(): void
    {
        $shiftId = DB::table('shifts')->insertGetId(['name' => 'وردية مسائية', 'created_at' => now(), 'updated_at' => now()]);

        $today = Carbon::today();
        $dayName = strtolower($today->format('l'));

        DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '14:00:00', 'end_time' => '22:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        Carbon::setTestNow($today->copy()->setTime(8, 5, 0));
        [$status, $body] = $this->checkIn($employee->id);
        Carbon::setTestNow();

        $this->assertSame(200, $status);
        $this->assertTrue($body['success']);
        $this->assertSame('في الوقت', $body['data']['status'], 'قبل الإصلاح: 08:05 > 08:00 الثابتة → "متأخر" زوراً');

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertSame('present', $day->status);
        $this->assertSame(0, $day->late_minutes);
    }

    public function test_checking_in_within_grace_of_the_real_shift_is_present(): void
    {
        $shiftId = DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);
        $today = Carbon::today();
        $dayName = strtolower($today->format('l'));

        DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // 10 دقائق تأخير — ضمن سماح 15 دقيقة الافتراضي.
        Carbon::setTestNow($today->copy()->setTime(9, 10, 0));
        [, $body] = $this->checkIn($employee->id);
        Carbon::setTestNow();

        $this->assertSame('في الوقت', $body['data']['status']);
    }

    public function test_checking_in_beyond_grace_but_within_absent_threshold_is_late(): void
    {
        $shiftId = DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);
        $today = Carbon::today();
        $dayName = strtolower($today->format('l'));

        DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // 45 دقيقة تأخير — أكثر من سماح 15، أقل من عتبة الغياب 120.
        Carbon::setTestNow($today->copy()->setTime(9, 45, 0));
        [, $body] = $this->checkIn($employee->id);
        Carbon::setTestNow();

        $this->assertSame('متأخر', $body['data']['status']);

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertSame('late', $day->status);
        $this->assertSame(45, $day->late_minutes, 'دقائق التأخير يجب أن تُحفظ فور تسجيل الحضور، لا بانتظار الانصراف');
    }

    public function test_checking_in_far_beyond_the_absent_threshold_counts_as_absent(): void
    {
        $shiftId = DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);
        $today = Carbon::today();
        $dayName = strtolower($today->format('l'));

        DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = $this->employeeWithShift($shiftId);

        // 3 ساعات تأخير — يتجاوز عتبة اعتبار التأخير غياباً (120 دقيقة).
        Carbon::setTestNow($today->copy()->setTime(12, 0, 0));
        [, $body] = $this->checkIn($employee->id);
        Carbon::setTestNow();

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertSame('absent', $day->status);
    }
}
