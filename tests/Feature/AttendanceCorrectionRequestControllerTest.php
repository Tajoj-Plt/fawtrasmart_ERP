<?php

namespace Tests\Feature;

use App\Http\Requests\AttendanceCorrectionRequestRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\HR\Http\Controllers\Attendance\AttendanceCorrectionRequestController;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Controllers\Concerns\SubmitsAttendanceCorrectionRequests;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * الوجهُ الطالب لتصحيح الحضور — TODO 10c.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `store()` لا يكتب على `attendance_days` أبداً — يبني طلباً معلَّقاً
 * فقط، مع لقطة `current_*` من الصفّ الحالي (إن وُجد) وقت التقديم. هذا
 * هو الفرقُ الجوهري عن `AttendanceDaysController::update()` القديم: لا
 * أثرَ فوري، والأثرُ الوحيد لاحقاً في خطوة الاعتماد (TODO 10d).
 */
class AttendanceCorrectionRequestControllerTest extends TestCase
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
            $t->unsignedBigInteger('employee_id')->nullable();
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

    private function controller(): AttendanceCorrectionRequestController
    {
        return new class extends AttendanceCorrectionRequestController
        {
            public function __construct() {}
        };
    }

    /**
     * الملخّصُ يصمد بعد الصفحة الأولى.
     *
     * `paginate()` تضع `limit/offset` على الباني نفسِه، وكانت النُّسخُ
     * الأربعُ تُؤخذ بعدها — فـ`count(*)` (صفٌّ واحد) يتخطّاه `offset 20`،
     * وتعود الشاراتُ الأربعُ أصفاراً في كل صفحةٍ بعد الأولى.
     */
    public function test_the_summary_counts_survive_the_second_page(): void
    {
        $employee = $this->employee();
        $user = $this->user();
        $this->actingAs($user);

        for ($i = 1; $i <= 25; $i++) {
            AttendanceCorrectionRequest::create([
                'employee_id' => $employee->id,
                'attendance_date' => '2026-01-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'requested_status' => 'present',
                'reason' => 'سبب',
                'status' => AttendanceCorrectionRequest::STATUS_PENDING,
                'requested_by' => $user->id,
            ]);
        }

        $first = $this->controller()->index(Request::create('/', 'GET'))->getData();

        // المُرقِّم يقرأ الصفحةَ من المُحلِّل العام لا من كائن الطلب المُمرَّر
        Paginator::currentPageResolver(fn () => 2);
        $second = $this->controller()->index(Request::create('/', 'GET', ['page' => 2]))->getData();
        Paginator::currentPageResolver(fn () => 1);

        $this->assertSame(25, $first['stats']['total']);
        $this->assertSame(25, $first['stats']['pending']);

        $this->assertSame(25, $second['stats']['total'], 'الملخّص لا يتغيّر بتغيّر الصفحة');
        $this->assertSame(25, $second['stats']['pending']);
        $this->assertCount(5, $second['requests']);
    }

    /** التصفيةُ بالحالة تُضيّق الجدول ولا تمسّ الملخّص. */
    public function test_filtering_by_status_narrows_the_table_but_not_the_summary(): void
    {
        $employee = $this->employee();
        $user = $this->user();
        $this->actingAs($user);

        foreach ([
            AttendanceCorrectionRequest::STATUS_PENDING,
            AttendanceCorrectionRequest::STATUS_PENDING,
            AttendanceCorrectionRequest::STATUS_APPROVED,
        ] as $i => $status) {
            AttendanceCorrectionRequest::create([
                'employee_id' => $employee->id,
                'attendance_date' => '2026-02-0'.($i + 1),
                'requested_status' => 'present',
                'reason' => 'سبب',
                'status' => $status,
                'requested_by' => $user->id,
            ]);
        }

        $data = $this->controller()->index(Request::create('/', 'GET', ['status' => 'approved']))->getData();

        $this->assertCount(1, $data['requests']);
        $this->assertSame(3, $data['stats']['total']);
        $this->assertSame(2, $data['stats']['pending']);
        $this->assertSame('approved', $data['status']);
    }

    /** حالةٌ مجهولةٌ في العنوان لا تُصفّي شيئاً — ولا تُمرَّر إلى `where` كما هي. */
    public function test_an_unknown_status_in_the_query_is_ignored(): void
    {
        $employee = $this->employee();
        $user = $this->user();
        $this->actingAs($user);

        AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-03-01',
            'requested_status' => 'present',
            'reason' => 'سبب',
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
            'requested_by' => $user->id,
        ]);

        $data = $this->controller()->index(Request::create('/', 'GET', ['status' => 'nonsense']))->getData();

        $this->assertCount(1, $data['requests']);
    }

    /** التسمياتُ عربيّةٌ من مصدرٍ واحد — لا «present» في شاشةٍ عربيّة. */
    public function test_the_model_labels_statuses_in_arabic(): void
    {
        $correction = new AttendanceCorrectionRequest([
            'requested_status' => 'present',
            'current_status' => 'absent',
        ]);
        $correction->status = AttendanceCorrectionRequest::STATUS_PENDING;

        $this->assertSame('حاضر', $correction->requestedStatusLabel());
        $this->assertSame('غائب', $correction->currentStatusLabel());
        $this->assertSame('معلَّق', $correction->statusLabel());
        $this->assertSame('إجازة عرضية', AttendanceCorrectionRequest::absenceTypeLabel(2));
        $this->assertNull(AttendanceCorrectionRequest::absenceTypeLabel(null));
    }

    public function test_store_creates_a_pending_request_without_touching_attendance_days(): void
    {
        $employee = $this->employee();
        $user = $this->user();
        $this->actingAs($user);

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'status' => 'absent', 'absence_type' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = AttendanceCorrectionRequestRequest::create('/hr/attendance-corrections', 'POST', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'reason' => 'الموظف حضر فعلياً ولم يُسجَّل بالباركود',
            'requested_status' => 'present',
            'requested_check_in_time' => '08:00',
            'requested_check_out_time' => '16:00',
        ]);
        $request->setContainer($this->app);

        $response = $this->controller()->store($request);

        $this->assertSame(302, $response->getStatusCode());

        $correction = AttendanceCorrectionRequest::first();
        $this->assertNotNull($correction);
        $this->assertSame('pending', $correction->status);
        $this->assertSame('present', $correction->requested_status);
        $this->assertSame('absent', $correction->current_status, 'يجب أن تُلتقَط الحالة الحالية وقت الطلب');
        $this->assertSame((int) $user->id, (int) $correction->requested_by);

        // الأثر الوحيد المسموح: صفّ الطلب — لا تغيير على attendance_days.
        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertSame('absent', $day->status, 'store() لا يجوز أن يكتب على attendance_days إطلاقاً');
    }

    public function test_store_snapshots_null_current_values_when_no_day_exists_yet(): void
    {
        $employee = $this->employee();
        $user = $this->user();
        $this->actingAs($user);

        $request = AttendanceCorrectionRequestRequest::create('/hr/attendance-corrections', 'POST', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'reason' => 'يوم لم يُسجَّل له شيء إطلاقاً',
            'requested_status' => 'present',
            'requested_check_in_time' => '08:00',
            'requested_check_out_time' => '16:00',
        ]);
        $request->setContainer($this->app);

        $this->controller()->store($request);

        $correction = AttendanceCorrectionRequest::first();
        $this->assertNull($correction->current_status);
    }

    public function test_store_rejects_a_second_pending_request_for_the_same_day(): void
    {
        $employee = $this->employee();
        $user = $this->user();
        $this->actingAs($user);

        AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'reason' => 'أول طلب', 'requested_by' => $user->id,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
        ]);

        $request = AttendanceCorrectionRequestRequest::create('/hr/attendance-corrections', 'POST', [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'reason' => 'طلب ثانٍ لنفس اليوم',
            'requested_status' => 'present',
            'requested_check_in_time' => '08:00',
            'requested_check_out_time' => '16:00',
        ]);
        $request->setContainer($this->app);

        // منذ استخراج المنطق إلى SubmitsAttendanceCorrectionRequests (TODO 10e)،
        // التكرارُ يُرفض بـValidationException — يحوِّله Laravel تلقائياً إلى
        // back()->withErrors() ضمن دورة HTTP الحقيقية؛ هنا نستدعي المتحكم
        // مباشرةً فيصل الاستثناء كما هو، قبل إنشاء أيّ صفٍّ ثانٍ.
        $this->expectException(ValidationException::class);

        $this->controller()->store($request);
    }

    public function test_cancel_is_restricted_to_the_original_requester(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $someoneElse = $this->user();

        $correction = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'reason' => 'x', 'requested_by' => $requester->id,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
        ]);

        $this->actingAs($someoneElse);

        $this->expectException(HttpException::class);
        $this->controller()->cancel($correction->id);
    }

    public function test_cancel_by_the_requester_marks_it_rejected(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $this->actingAs($requester);

        $correction = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'reason' => 'x', 'requested_by' => $requester->id,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
        ]);

        $this->controller()->cancel($correction->id);

        $this->assertSame('rejected', $correction->fresh()->status);
        $this->assertNotNull($correction->fresh()->rejection_reason);
    }

    /** طلبٌ مبتوتٌ فيه أصلاً لا يُلغى ثانيةً — pending() هو الحارس. */
    public function test_a_decided_request_cannot_be_cancelled_again(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $this->actingAs($requester);

        $correction = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'reason' => 'x', 'requested_by' => $requester->id,
            'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
        ]);

        $this->expectException(ModelNotFoundException::class);
        $this->controller()->cancel($correction->id);
    }

    // ═══════════════ نطاق الموظفين ═══════════════

    public function test_the_controller_applies_the_employee_scope(): void
    {
        $this->assertContains(
            ScopesToAccessibleEmployees::class,
            array_values(class_uses_recursive(AttendanceCorrectionRequestController::class)),
            'المتحكم لا يطبّق نطاق الرؤية'
        );
    }

    /**
     * index/store/show تنادي المرجع فعلياً — لا الاكتفاء باستعماله.
     * cancel() مستثناة عمداً: إلغاءٌ ذاتيٌّ لمقدِّم الطلب نفسِه
     * (requested_by === auth()->id())، لا فحصَ نطاق موظفٍ له.
     *
     * منذ TODO 10e: نداءُ store() انتقل إلى
     * SubmitsAttendanceCorrectionRequests (يشترك فيه AttendanceDaysController
     * ::update أيضاً) — فالفحصُ يُجمَع من كِلا الملفّين لا المتحكم وحده.
     */
    public function test_the_read_and_write_paths_call_the_scope(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(AttendanceCorrectionRequestController::class))->getFileName()
        );
        $source .= (string) file_get_contents(
            (new \ReflectionClass(SubmitsAttendanceCorrectionRequests::class))->getFileName()
        );

        $calls = preg_match_all('/\$this->(scopeToEmployees|authorizeEmployeeScope)\(/', $source);

        $this->assertGreaterThanOrEqual(3, $calls, "المتحكم يستعمل المرجع ولا يناديه إلا {$calls} مرّة");
    }
}
