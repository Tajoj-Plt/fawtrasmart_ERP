<?php

namespace Tests\Feature;

use App\Http\Requests\AttendanceCorrectionRequestRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\AttendanceDaysController;
use Tests\TestCase;

/**
 * شاشة تعديل يوم الحضور القديمة لم تعد تكتب مباشرةً — TODO 10e.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `AttendanceDaysController::update()` كان يكتب على `attendance_days`
 * في خطوةٍ واحدة، بصلاحية تعديل الأيام فقط — بلا فصلٍ
 * بين من يطلب التعديل ومن يعتمده، وبلا أثر تدقيق حقيقي. صار الآن
 * يُقدِّم طلب تصحيح معلَّقاً فقط (نفس منطق شاشة الطلب المخصَّصة، عبر
 * `SubmitsAttendanceCorrectionRequests`)، ومحروساً بصلاحية
 * `attendance_corrections.create` بدل `attendance_days.edit`.
 *
 * `store()` (إنشاء يومٍ جديد لا تصحيح موجود) تُرك كتابةً مباشرة عمداً —
 * خارج نطاق هذا التغيير.
 */
class AttendanceDaysUpdateRoutesThroughCorrectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('email')->unique();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
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

        $this->migration()->up();
    }

    private function migration()
    {
        return require base_path('database/migrations/2026_09_01_090400_create_attendance_correction_requests_table.php');
    }

    private function employee(): Employee
    {
        return Employee::create(['first_name' => 'سالم', 'email' => 'e'.uniqid().'@example.test']);
    }

    private function user(): User
    {
        return User::create(['name' => 'مستخدم', 'email' => 'u'.uniqid().'@example.test', 'password' => 'x']);
    }

    private function controller(): AttendanceDaysController
    {
        return new class extends AttendanceDaysController
        {
            public function __construct() {}
        };
    }

    private function correctionRequestData(array $overrides = []): AttendanceCorrectionRequestRequest
    {
        $request = AttendanceCorrectionRequestRequest::create('/hr/attendanceDays/update/1', 'PUT', array_merge([
            'reason' => 'نسي الباركود',
            'requested_status' => 'present',
            'requested_check_in_time' => '08:05',
            'requested_check_out_time' => '16:00',
        ], $overrides));
        $request->setContainer($this->app);

        $route = new RoutingRoute('PUT', '/attendanceDays/update/{id}', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    public function test_update_does_not_write_to_attendance_days(): void
    {
        $employee = $this->employee();
        $user = $this->user();
        $this->actingAs($user);

        $dayId = DB::table('attendance_days')->insertGetId([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'status' => 'absent', 'absence_type' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = $this->correctionRequestData([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
        ]);

        $response = $this->controller()->update($request, $dayId);

        $this->assertSame(302, $response->getStatusCode());

        $day = DB::table('attendance_days')->where('id', $dayId)->first();
        $this->assertSame('absent', $day->status, 'update() لا يجوز أن يكتب على attendance_days بعد الآن');

        $correction = AttendanceCorrectionRequest::first();
        $this->assertNotNull($correction, 'يجب أن يُنشأ طلب تصحيح معلَّق بدلاً من الكتابة المباشرة');
        $this->assertSame('pending', $correction->status);
        $this->assertSame('present', $correction->requested_status);
        $this->assertSame('absent', $correction->current_status, 'يجب التقاط الحالة الحالية كلقطة للتدقيق');
    }

    public function test_update_404s_for_a_nonexistent_day(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->user());

        $request = $this->correctionRequestData([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->controller()->update($request, 999999);
    }

    public function test_update_respects_the_duplicate_pending_guard(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $this->actingAs($requester);

        $dayId = DB::table('attendance_days')->insertGetId([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
        ]);

        AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'reason' => 'طلب سابق', 'requested_by' => $requester->id,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
        ]);

        $request = $this->correctionRequestData([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller()->update($request, $dayId);
    }

    // ═══════════════ الصلاحيات ═══════════════

    public function test_edit_and_update_are_now_guarded_by_the_correction_request_permission(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(AttendanceDaysController::class))->getFileName()
        );

        $this->assertMatchesRegularExpression(
            "/permission\.role:attendance_corrections\.create', \[\s*'only' => \['edit', 'update'\]/",
            $source,
            'edit/update يجب أن يُحرَسا بصلاحية تقديم طلب التصحيح'
        );
    }

    /** store() يبقى تحريراً مباشراً — لم يُمسّ ضمن هذا التغيير. */
    public function test_store_is_still_guarded_by_the_direct_edit_permission(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(AttendanceDaysController::class))->getFileName()
        );

        $this->assertMatchesRegularExpression(
            "/permission\.role:attendance_days\.edit', \[\s*'only' => \['create', 'delete', 'store'\]/",
            $source
        );
    }
}
