<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\Log;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\AttendanceCorrectionApprovalController;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Tests\TestCase;

/**
 * الوجهُ المعتمِد لتصحيح الحضور — TODO 10d.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `approve()` هو الموضع الوحيد في تدفّق «طلب → اعتماد → أثر» الذي يكتب
 * فعلياً على `attendance_days` — ويُطبِّق نفس فرع الحالة الذي تطبّقه
 * شاشة `AttendanceDaysController::update` اليدوية (present/absent/غير
 * ذلك)، فلا يبقى حقلٌ من حالةٍ سابقة عالقاً. `reject()` لا يمسّ
 * `attendance_days` إطلاقاً.
 *
 * حارسُ الاعتماد المزدوج (`canBeDecided()` بعد `lockForUpdate()`) يُختبر
 * منطقياً هنا فقط — استدعاءان متتاليان لا فعليّان متزامنان، إذ لا سبيل
 * لمحاكاة تزامن عمليتين حقيقيتين داخل اختبار PHPUnit واحد.
 */
class AttendanceCorrectionApprovalControllerTest extends TestCase
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
            // `deduction_id`: الاعتمادُ يُلغي الخصميةَ التي أبطلها ويفكّ ربطَها
            $t->unsignedBigInteger('deduction_id')->nullable();
            // أضافتها هجرة extend_attendance_days_for_v2، و approve() يُصفّرها
            // عند الاعتماد. غيابُها من هذا المخطّط اليدوي كان يُفشل الإدراج،
            // فيبتلع catch الخطأ ويعيد 302 والطلبُ ما زال pending.
            $t->unsignedSmallInteger('late_minutes')->default(0);
            $t->unsignedSmallInteger('early_leave_minutes')->default(0);
            $t->unsignedSmallInteger('overtime_minutes')->default(0);
            $t->boolean('manual_override')->default(false);
            $t->timestamp('processed_at')->nullable();
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
            $t->unsignedBigInteger('user_id')->nullable();
            $t->text('description')->nullable();
            $t->text('changes')->nullable();
            $t->text('notes')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
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

    private function controller(): AttendanceCorrectionApprovalController
    {
        return new class extends AttendanceCorrectionApprovalController
        {
            public function __construct() {}
        };
    }

    private function pendingRequest(Employee $employee, User $requester, array $overrides = []): AttendanceCorrectionRequest
    {
        return AttendanceCorrectionRequest::create(array_merge([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'requested_status' => 'present',
            'requested_start_shift' => '08:00:00',
            'requested_end_shift' => '16:00:00',
            'requested_check_in_time' => '08:05:00',
            'requested_check_out_time' => '16:00:00',
            'reason' => 'نسي الباركود',
            'requested_by' => $requester->id,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
        ], $overrides));
    }

    /**
     * الطابورُ معلَّقٌ بالافتراض، وما بُتَّ فيه يبقى مبلوغاً.
     *
     * كانت `index()` مثبَّتةً على `pending()`: يعتمد المسؤولُ طلباً
     * فيختفي من شاشته إلى الأبد، ولا سبيلَ لمراجعة قرارٍ سابق.
     */
    public function test_the_queue_defaults_to_pending_and_decided_requests_remain_reachable(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $approver = $this->user();
        $this->actingAs($approver);

        $this->pendingRequest($employee, $requester, ['attendance_date' => '2026-01-05']);
        $decided = $this->pendingRequest($employee, $requester, ['attendance_date' => '2026-01-06']);
        $this->controller()->approve(Request::create('/', 'POST'), $decided->id);

        $default = $this->controller()->index(Request::create('/', 'GET'))->getData();
        $this->assertSame('pending', $default['status']);
        $this->assertCount(1, $default['requests']);
        $this->assertSame(1, $default['counts']['pending']);
        $this->assertSame(1, $default['counts']['approved']);

        $approved = $this->controller()->index(Request::create('/', 'GET', ['status' => 'approved']))->getData();
        $this->assertCount(1, $approved['requests']);
        $this->assertSame($decided->id, $approved['requests'][0]->id);
    }

    /** حالةٌ مجهولةٌ في العنوان تعود إلى الطابور المعلَّق، ولا تُمرَّر إلى `where`. */
    public function test_an_unknown_status_falls_back_to_the_pending_queue(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->user());
        $this->pendingRequest($employee, $this->user());

        $data = $this->controller()->index(Request::create('/', 'GET', ['status' => 'nonsense']))->getData();

        $this->assertSame('pending', $data['status']);
        $this->assertCount(1, $data['requests']);
    }

    /**
     * أثرُ التدقيق يصل إلى الشاشة.
     *
     * المتحكّمُ يكتب في `logs` عند كل قرار، ولم تكن شاشةٌ تقرؤه — فالأثرُ
     * محفوظٌ ولا يراه أحد.
     */
    public function test_the_detail_screen_carries_the_audit_trail(): void
    {
        $employee = $this->employee();
        $approver = $this->user();
        $this->actingAs($approver);

        $correction = $this->pendingRequest($employee, $this->user());
        $this->controller()->approve(Request::create('/', 'POST'), $correction->id);

        $data = $this->controller()->show($correction->id)->getData();

        $this->assertArrayHasKey('auditTrail', $data);
        $this->assertCount(1, $data['auditTrail']);
        $this->assertStringContainsString('اعتماد', $data['auditTrail'][0]->description);
        $this->assertSame((int) $approver->id, (int) $data['auditTrail'][0]->created_by);
    }

    /**
     * نوعُ سجلّ التصحيح محميٌّ من التنظيف.
     *
     * «تنظيف ما مضى عليه ثلاثون يوماً» في شاشة سجل النشاطات كانت تمسحه —
     * فيبقى الخصمُ في الراتب ويذهب مسوّغُه.
     */
    public function test_the_correction_audit_type_is_protected_from_log_cleanup(): void
    {
        $this->assertContains('attendance_correction_request_log', Log::protectedTypes());
    }

    /**
     * الاعتمادُ يُلغي الخصميةَ التي أبطلها.
     *
     * كان اليومُ يُكتب «حاضراً» وتُصفَّر دقائقُه، وتبقى خصميةُ الغياب
     * مربوطةً به بحالة «معتمَدة» — فالموظفُ الذي أُثبت حضورُه يُحسَم من
     * راتبه غيابُ يومٍ حضَره، وهو عينُ ما رُفع الطلبُ لإصلاحه.
     */
    public function test_approving_cancels_the_deduction_the_correction_invalidates(): void
    {
        $employee = $this->employee();
        $approver = $this->user();
        $this->actingAs($approver);

        $deductionId = DB::table('deductions')->insertGetId([
            'employee_id' => $employee->id,
            'deduction_type' => 'absence',
            'amount' => 300,
            'status' => \App\Models\Deduction::STATUS_APPROVED,
            'date' => '2026-01-05',
            'reason' => 'غياب يوم 2026-01-05',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'absent',
            'deduction_id' => $deductionId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $correction = $this->pendingRequest($employee, $this->user());

        $this->controller()->approve(Request::create('/', 'POST'), $correction->id);

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertSame('present', $day->status);
        $this->assertNull($day->deduction_id, 'الربطُ بالخصمية يُفكّ');

        $this->assertSame(
            \App\Models\Deduction::STATUS_CANCELLED,
            DB::table('deductions')->where('id', $deductionId)->value('status'),
            'الخصميةُ تُلغى لا تبقى معتمَدة'
        );

        $this->assertTrue(
            DB::table('deduction_histories')->where('deduction_id', $deductionId)
                ->where('action', 'cancelled')->exists(),
            'ويبقى سردُ الإلغاء في تاريخ الخصمية'
        );
    }

    /** والمسوّاةُ لا تُمسّ: مبلغُها خرج في راتبٍ صُرف، وإلغاؤها يُخفي حسماً وقع. */
    public function test_a_settled_deduction_is_left_alone(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->user());

        $deductionId = DB::table('deductions')->insertGetId([
            'employee_id' => $employee->id,
            'deduction_type' => 'absence',
            'amount' => 300,
            'status' => \App\Models\Deduction::STATUS_SETTLED,
            'date' => '2026-01-05',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'status' => 'absent',
            'deduction_id' => $deductionId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $correction = $this->pendingRequest($employee, $this->user());
        $this->controller()->approve(Request::create('/', 'POST'), $correction->id);

        $this->assertSame(
            \App\Models\Deduction::STATUS_SETTLED,
            DB::table('deductions')->where('id', $deductionId)->value('status')
        );
    }

    public function test_approving_a_present_request_writes_to_attendance_days(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $approver = $this->user();
        $this->actingAs($approver);

        $correction = $this->pendingRequest($employee, $requester);

        $response = $this->controller()->approve(Request::create('/', 'POST'), $correction->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertSame((int) $approver->id, (int) $correction->fresh()->approved_by);
        $this->assertNotNull($correction->fresh()->approved_at);

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertNotNull($day, 'approve() يجب أن ينشئ صفّ attendance_days إن لم يكن موجوداً');
        $this->assertSame('present', $day->status);
        $this->assertSame('08:05:00', $day->check_in_time);
        $this->assertSame('16:00:00', $day->check_out_time);
    }

    public function test_approving_an_absent_request_clears_present_only_fields(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $this->actingAs($this->user());

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'status' => 'present', 'check_in_time' => '08:00:00', 'check_out_time' => '16:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $correction = $this->pendingRequest($employee, $requester, [
            'requested_status' => 'absent',
            'requested_start_shift' => null,
            'requested_end_shift' => null,
            'requested_check_in_time' => null,
            'requested_check_out_time' => null,
            'requested_absence_type' => 1,
            'requested_absence_balance' => 1,
        ]);

        $this->controller()->approve(Request::create('/', 'POST'), $correction->id);

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertSame('absent', $day->status);
        $this->assertSame(1, (int) $day->absence_type);
        $this->assertNull($day->check_in_time, 'حقول الحضور يجب أن تُصفَّر عند التحويل إلى إجازة');
        $this->assertNull($day->check_out_time);
    }

    public function test_approving_updates_an_existing_row_instead_of_duplicating_it(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $this->actingAs($this->user());

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id, 'attendance_date' => '2026-01-05',
            'status' => 'absent', 'absence_type' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $correction = $this->pendingRequest($employee, $requester);

        $this->controller()->approve(Request::create('/', 'POST'), $correction->id);

        $this->assertSame(
            1,
            DB::table('attendance_days')->where('employee_id', $employee->id)->count(),
            'التطبيق يجب أن يحدّث الصفّ الموجود لا يكرّره — يحترم القيد الفريد على (employee_id, attendance_date)'
        );
    }

    public function test_a_decided_request_cannot_be_approved_twice(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $this->actingAs($this->user());

        $correction = $this->pendingRequest($employee, $requester);

        $first = $this->controller()->approve(Request::create('/', 'POST'), $correction->id);
        $this->assertSame(302, $first->getStatusCode());

        // نغيّر الصفّ يدوياً لنتأكد أن الاستدعاء الثاني لا يعيد التطبيق فوقه
        DB::table('attendance_days')->where('employee_id', $employee->id)->update(['status' => 'late']);

        $second = $this->controller()->approve(Request::create('/', 'POST'), $correction->id);
        $this->assertSame(302, $second->getStatusCode());

        $day = DB::table('attendance_days')->where('employee_id', $employee->id)->first();
        $this->assertSame('late', $day->status, 'الاعتماد الثاني على طلبٍ مبتوتٍ فيه لا يجوز أن يُعيد الكتابة');
    }

    public function test_reject_does_not_touch_attendance_days(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $approver = $this->user();
        $this->actingAs($approver);

        $correction = $this->pendingRequest($employee, $requester);

        $request = Request::create('/', 'POST', ['rejection_reason' => 'التاريخ غير صحيح']);
        $this->controller()->reject($request, $correction->id);

        $this->assertSame('rejected', $correction->fresh()->status);
        $this->assertSame('التاريخ غير صحيح', $correction->fresh()->rejection_reason);
        $this->assertSame((int) $approver->id, (int) $correction->fresh()->approved_by);

        $this->assertSame(0, DB::table('attendance_days')->where('employee_id', $employee->id)->count());
    }

    public function test_a_decided_request_cannot_be_rejected_twice(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $this->actingAs($this->user());

        $correction = $this->pendingRequest($employee, $requester, ['status' => AttendanceCorrectionRequest::STATUS_APPROVED]);

        $request = Request::create('/', 'POST', ['rejection_reason' => 'محاولة متأخرة']);
        $this->controller()->reject($request, $correction->id);

        $this->assertSame('approved', $correction->fresh()->status, 'طلبٌ معتمَد بالفعل لا يتحوّل إلى مرفوض');
    }

    // ═══════════════ نطاق الموظفين ═══════════════

    public function test_the_controller_applies_the_employee_scope(): void
    {
        $this->assertContains(
            ScopesToAccessibleEmployees::class,
            array_values(class_uses_recursive(AttendanceCorrectionApprovalController::class))
        );
    }

    public function test_every_action_calls_the_scope(): void
    {
        $source = (string) file_get_contents(
            (new \ReflectionClass(AttendanceCorrectionApprovalController::class))->getFileName()
        );

        $calls = preg_match_all('/\$this->(scopeToEmployees|authorizeEmployeeScope)\(/', $source);

        // index (scopeToEmployees) + show/approve/reject (authorizeEmployeeScope) = 4
        $this->assertGreaterThanOrEqual(4, $calls, "المتحكم يستعمل المرجع ولا يناديه إلا {$calls} مرّة");
    }
}
