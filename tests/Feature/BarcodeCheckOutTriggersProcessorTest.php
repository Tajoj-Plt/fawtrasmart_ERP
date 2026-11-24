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
 * الانصرافُ يُشغِّل معالج اليوم فوراً — لا انتظار الكرون الليلي.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `AttendanceProcessorService` كان يعمل حصراً عبر أمر `attendance:process`
 * المجدوَل 23:55. فموظفٌ ينصرف ظهراً وتُصرف قسيمةُ راتبه بعد ظهر اليوم
 * نفسِه (تسويةٌ فورية أو دفعةٌ يدوية) كانت قسيمتُه تخرج بلا دقائق إضافي
 * ولا خصمية تأخير محسوبة — فارغةً حتى منتصف الليل.
 *
 * الآن `checkOut()` يستدعي `processEmployeeDay()` مباشرةً بعد تثبيت وقت
 * الانصراف — خارج معاملة الانصراف نفسِها، حتى لا يُسقط فشلُ المعالجة
 * تسجيلَ الانصراف.
 *
 * الجداولُ تُبنى يدوياً كما في بقية اختبارات الحضور لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class BarcodeCheckOutTriggersProcessorTest extends TestCase
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

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default('pending');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
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

    public function test_checkout_computes_overtime_immediately_without_the_nightly_cron(): void
    {
        $shiftId = DB::table('shifts')->insertGetId(['name' => 'وردية', 'created_at' => now(), 'updated_at' => now()]);

        $today = Carbon::today();
        $dayName = strtolower($today->format('l'));

        DB::table('shift_days')->insert([
            'shift_id' => $shiftId, 'day' => $dayName, 'working_day' => 1,
            'start_time' => '08:00:00', 'end_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $employee = Employee::create([
            'first_name' => 'سالم', 'email' => 'e'.uniqid().'@example.test',
            'status' => 1, 'shift_id' => $shiftId,
            'barcode' => 'CARD_'.uniqid(),
        ]);

        // موظفٌ حضر الساعة 08:00 وبقي حتى 17:00 — ساعة إضافية.
        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $today->toDateString(),
            'check_in_time' => '08:00:00',
            'check_in_method' => 'barcode',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = $this->makePairedKioskRequest('/', 'POST', [
            'employee_id' => $employee->id,
            'barcode' => $employee->barcode,
        ]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        Carbon::setTestNow($today->copy()->setTime(17, 0, 0));
        $response = $this->controller()->checkOut($request);
        Carbon::setTestNow();

        $body = json_decode($response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        // بلا استدعاء attendance:process يدوياً — هذه القيم يجب أن تكون
        // محسوبة فعلاً فور استجابة checkOut، لا أصفاراً بانتظار الكرون.
        $this->assertNotNull($day->processed_at, 'اليوم لم يُعالَج فور الانصراف');
        $this->assertSame(60, $day->overtime_minutes);
        $this->assertSame(0, $day->early_leave_minutes);
    }

    /** وفشل المعالجة لا يُسقط تسجيل الانصراف نفسه. */
    public function test_checkout_still_succeeds_even_if_processing_cannot_resolve_a_shift(): void
    {
        $today = Carbon::today();

        // لا shift_id على الإطلاق — المعالج يعتمد حينها على الافتراضي، لا يفشل.
        $employee = Employee::create([
            'first_name' => 'سالم', 'email' => 'e'.uniqid().'@example.test',
            'status' => 1, 'shift_id' => null,
            'barcode' => 'CARD_'.uniqid(),
        ]);

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $today->toDateString(),
            'check_in_time' => '08:00:00',
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = $this->makePairedKioskRequest('/', 'POST', [
            'employee_id' => $employee->id,
            'barcode' => $employee->barcode,
        ]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $response = $this->controller()->checkOut($request);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['success']);
        $this->assertNotNull(
            DB::table('attendance_days')->where('employee_id', $employee->id)->value('check_out_time'),
            'الانصراف نفسه يجب أن يُسجَّل حتى لو تعثّرت المعالجة'
        );
    }
}
