<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\BarcodeAttendanceController;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * حراسةُ `checkIn` من تسجيل حضورٍ لموظفٍ لا يحقّ له.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `BarcodeAttendanceController::checkIn` كان يُنشئ سجلَّ حضورٍ لأيّ
 * `employee_id` موجود — بلا فحص `isActive()` ولا تاريخي التعيين/الإنهاء.
 * فموظفٌ موقوفٌ أو مستقيلٌ أو لم يحن تاريخُ تعيينه بعد كان بمقدوره
 * تسجيل حضورٍ يدخل مسارَ الراتب لاحقاً.
 *
 * الجداولُ تُبنى يدوياً كما في [EmploymentStatusTest] لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class BarcodeAttendanceCheckInGuardTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->tinyInteger('status')->default(EmploymentStatus::ACTIVE->value);
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
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->timestamps();
        });

        // determineCheckInStatus() (منذ إصلاح عتبة 08:00 الثابتة) يستدعي
        // AttendanceProcessorService::resolveConfig()، الذي يحتاج هذين
        // الجدولين ولو كان الموظف بلا وردية مُعيَّنة إطلاقاً.
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

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'barcode' => 'CARD_'.uniqid(),
        ], $attributes));
    }

    private function controller(): BarcodeAttendanceController
    {
        // بلا الباني: حُرّاسه ميدل وير تُختبر على المسار لا هنا
        return new class extends BarcodeAttendanceController
        {
            public function __construct() {}
        };
    }

    private function checkIn(int $employeeId): array
    {
        /*
            الوسيلةُ تُثبَت: الطلبُ يحمل الباركودَ لا اسمَ الوسيلة. وكان
            يكفيه أن يقول «method=barcode» مع رقمِ موظف، فيُسجَّل مسحاً
            موثَّقاً بلا أن يُمسَح شيء.
        */
        $barcode = Employee::withTrashed()->find($employeeId)?->barcode;

        $request = $this->makePairedKioskRequest('/', 'POST', [
            'employee_id' => $employeeId,
            'barcode' => $barcode,
        ]);

        $response = $this->controller()->checkIn($request);

        return [$response->getStatusCode(), json_decode($response->getContent(), true)];
    }

    public function test_an_active_employee_can_check_in(): void
    {
        $employee = $this->employee();

        [$status, $body] = $this->checkIn($employee->id);

        $this->assertSame(200, $status);
        $this->assertTrue($body['success']);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $employee->id]);
    }

    private function assertCannotCheckIn(int $status): void
    {
        $employee = $this->employee(['status' => $status]);

        [$httpStatus, $body] = $this->checkIn($employee->id);

        $this->assertSame(403, $httpStatus);
        $this->assertFalse($body['success']);
        $this->assertDatabaseMissing('attendance_days', ['employee_id' => $employee->id]);
    }

    public function test_a_suspended_employee_cannot_check_in(): void
    {
        $this->assertCannotCheckIn(EmploymentStatus::SUSPENDED->value);
    }

    public function test_a_resigned_employee_cannot_check_in(): void
    {
        $this->assertCannotCheckIn(EmploymentStatus::RESIGNED->value);
    }

    public function test_a_terminated_employee_cannot_check_in(): void
    {
        $this->assertCannotCheckIn(EmploymentStatus::TERMINATED->value);
    }

    public function test_a_deactivated_employee_cannot_check_in(): void
    {
        $this->assertCannotCheckIn(EmploymentStatus::INACTIVE->value);
    }

    /** تحت التجربة عملٌ يُصرف له — يسجّل حضوره كالنشط تماماً. */
    public function test_an_employee_on_probation_can_check_in(): void
    {
        $employee = $this->employee(['status' => EmploymentStatus::PROBATION->value]);

        [$status, $body] = $this->checkIn($employee->id);

        $this->assertSame(200, $status);
        $this->assertTrue($body['success']);
    }

    public function test_check_in_before_the_hire_date_is_rejected(): void
    {
        $employee = $this->employee(['hire_date' => Carbon::tomorrow()]);

        [$status, $body] = $this->checkIn($employee->id);

        $this->assertSame(403, $status);
        $this->assertFalse($body['success']);
        $this->assertDatabaseMissing('attendance_days', ['employee_id' => $employee->id]);
    }

    public function test_check_in_on_the_hire_date_itself_is_allowed(): void
    {
        $employee = $this->employee(['hire_date' => Carbon::today()]);

        [$status, $body] = $this->checkIn($employee->id);

        $this->assertSame(200, $status);
        $this->assertTrue($body['success']);
    }

    public function test_check_in_after_the_last_working_day_is_rejected(): void
    {
        $employee = $this->employee([
            'status' => EmploymentStatus::RESIGNED->value,
            'termination_date' => Carbon::yesterday()->subDay(),
            'last_working_day' => Carbon::yesterday(),
        ]);

        [$status, $body] = $this->checkIn($employee->id);

        $this->assertSame(403, $status);
        $this->assertFalse($body['success']);
    }

    /** آخرُ يوم عملٍ غيابُه يُرَدّ إلى تاريخ الإنهاء — نفس القاعدة في [EmploymentStatusTest]. */
    public function test_falls_back_to_termination_date_when_last_working_day_is_absent(): void
    {
        $employee = $this->employee([
            'status' => EmploymentStatus::RESIGNED->value,
            'termination_date' => Carbon::yesterday(),
            'last_working_day' => null,
        ]);

        [$status] = $this->checkIn($employee->id);

        $this->assertSame(403, $status);
    }
}
