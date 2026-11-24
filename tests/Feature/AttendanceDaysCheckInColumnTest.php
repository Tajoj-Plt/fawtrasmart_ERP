<?php

namespace Tests\Feature;

use App\Http\Requests\AttendanceDaysRequest;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\AttendanceDaysController;
use Tests\TestCase;

/**
 * توحيدُ عمود الحضور: check_in_time هو ما يُكتب فعلياً الآن، لا login_time.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * الشاشةُ اليدوية (`AttendanceDaysController::store`) كانت تكتب
 * `login_time`/`logout_time` حصراً. لكن معالجَ الكرون (`AttendanceProcessorService`)
 * وشاشةَ الباركود لا يقرآن سوى `check_in_time`/`check_out_time` — فكلُّ يوم
 * حضورٍ أُدخل يدوياً كان `check_in_time` فيه فارغاً، فيعالجه الكرون **غياباً**
 * رغم تسجيله «حاضر» صراحةً من الشاشة نفسِها.
 *
 * `update()` كانت تُختبر هنا أيضاً حتى TODO 10e، حين توقّفت عن الكتابة
 * على `attendance_days` كلياً — صارت تُقدِّم طلب تصحيح بدلاً من ذلك (انظر
 * AttendanceDaysUpdateRoutesThroughCorrectionTest).
 *
 * الجداولُ تُبنى يدوياً كما في بقية اختبارات الحضور لأن هجراتِ المشروع
 * تحوي صيغاً خاصةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class AttendanceDaysCheckInColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
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
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('logs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('type')->nullable();
            $t->unsignedBigInteger('type_id')->nullable();
            $t->string('type_log')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    private function employee(): Employee
    {
        return Employee::create(['first_name' => 'سالم', 'email' => 'e'.uniqid().'@example.test']);
    }

    private function controller(): AttendanceDaysController
    {
        // بلا الباني: حُرّاسه ميدل وير تُختبر على المسار لا هنا
        return new class extends AttendanceDaysController
        {
            public function __construct() {}
        };
    }

    private function request(array $data, ?int $routeId = null): AttendanceDaysRequest
    {
        $request = AttendanceDaysRequest::create('/hr/attendance-days', 'POST', $data);
        $request->setContainer($this->app);

        $route = new RoutingRoute('POST', '/attendance-days', []);
        $route->bind($request);
        if ($routeId !== null) {
            $route->setParameter('id', $routeId);
        }
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    public function test_store_writes_check_in_out_time_not_login_time(): void
    {
        $employee = $this->employee();

        $request = $this->request([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'present',
            'start_shift' => '08:00',
            'end_shift' => '16:00',
            'check_in_time' => '08:05:00',
            'check_out_time' => '16:10:00',
        ]);

        $this->controller()->store($request);

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();

        $this->assertNotNull($day, 'لم يُحفظ يوم الحضور');
        $this->assertSame('08:05:00', $day->check_in_time, 'check_in_time هو ما يجب أن يُكتب — لا login_time');
        $this->assertSame('16:10:00', $day->check_out_time);
        $this->assertNull($day->login_time, 'login_time عمودٌ قديمٌ لا تكتبه الشاشة بعد الآن');
        $this->assertNull($day->logout_time);
    }

    // update() لم يعد يكتب على attendance_days إطلاقاً منذ TODO 10e — صار
    // يُقدِّم طلب تصحيح فقط. تغطيةُ ذلك الآن في
    // AttendanceDaysUpdateRoutesThroughCorrectionTest، وتعيينُ requested_check_in_time
    // الصحيح مُغطًّى عبر SubmitsAttendanceCorrectionRequests في
    // AttendanceCorrectionRequestControllerTest — فلا حاجة لتكرارهما هنا.
}
