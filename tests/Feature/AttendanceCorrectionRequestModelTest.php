<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * موديل وهجرة طلب تصحيح الحضور — TODO 10a.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `attendance_correction_requests` جدولٌ جديد كلياً — لا كودَ قديماً
 * يُصلَح هنا، بل أساسٌ لتدفّق «طلب → اعتماد → أثر» بدل التعديل المباشر
 * على `attendance_days` (`AttendanceDaysController::update`، الذي كان
 * أيَّ صاحبِ صلاحيةٍ واحدة يُعدِّل به الحضور دون فصلٍ بين من يطلب ومن
 * يعتمد — نمطُها مأخوذٌ من `SalaryAdvanceRequest`).
 *
 * الهجرةُ نفسُها (لا محاكاةٌ لمنطقها) تُحمَّل وتُشغَّل هنا — Schema
 * Blueprint عادي يعمل على sqlite دون حاجةٍ لصياغة MySQL خاصة.
 */
class AttendanceCorrectionRequestModelTest extends TestCase
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

    public function test_the_migration_creates_the_expected_columns(): void
    {
        $expected = [
            'id', 'employee_id', 'attendance_date',
            'requested_status', 'requested_start_shift', 'requested_end_shift',
            'requested_check_in_time', 'requested_check_out_time',
            'requested_absence_type', 'requested_absence_balance', 'requested_notes',
            'current_status', 'current_start_shift', 'current_end_shift',
            'current_check_in_time', 'current_check_out_time',
            'current_absence_type', 'current_absence_balance',
            'reason', 'status', 'requested_by', 'approved_by', 'approved_at',
            'rejection_reason', 'admin_notes', 'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('attendance_correction_requests', $column),
                "العمود {$column} غير موجود"
            );
        }
    }

    public function test_rolling_back_drops_the_table(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_correction_requests'));

        $this->migration()->down();

        $this->assertFalse(Schema::hasTable('attendance_correction_requests'));
    }

    /** الجدول يضمن pending افتراضياً حتى لصفٍّ يُدرَج مباشرةً بلا عمود status. */
    public function test_the_database_default_is_pending(): void
    {
        $employee = $this->employee();
        $user = $this->user();

        \DB::table('attendance_correction_requests')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'reason' => 'نسي تسجيل الحضور بالباركود',
            'requested_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('pending', \DB::table('attendance_correction_requests')->value('status'));
    }

    /** والمتحكم يضبطها صراحةً — نمط SalaryAdvanceRequest نفسه — لا اعتماداً ضمنياً على الافتراضي. */
    public function test_a_request_created_with_the_pending_constant_can_be_decided(): void
    {
        $employee = $this->employee();
        $user = $this->user();

        $request = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'requested_status' => 'present',
            'requested_check_in_time' => '08:00:00',
            'requested_check_out_time' => '16:00:00',
            'reason' => 'نسي تسجيل الحضور بالباركود',
            'requested_by' => $user->id,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
        ]);

        $this->assertSame(AttendanceCorrectionRequest::STATUS_PENDING, $request->status);
        $this->assertTrue($request->canBeDecided());
    }

    public function test_a_decided_request_can_no_longer_be_decided_again(): void
    {
        $employee = $this->employee();
        $user = $this->user();

        $request = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'requested_status' => 'present',
            'reason' => 'تصحيح',
            'requested_by' => $user->id,
            'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
        ]);

        $this->assertFalse($request->canBeDecided());
    }

    public function test_relationships_resolve(): void
    {
        $employee = $this->employee();
        $requester = $this->user();
        $approver = $this->user();

        $request = AttendanceCorrectionRequest::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-01-05',
            'reason' => 'تصحيح',
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
        ]);

        $this->assertTrue($request->employee->is($employee));
        $this->assertTrue($request->requestedBy->is($requester));
        $this->assertTrue($request->approvedBy->is($approver));
    }

    public function test_scopes_filter_by_status(): void
    {
        $employee = $this->employee();
        $user = $this->user();

        $base = ['employee_id' => $employee->id, 'attendance_date' => '2026-01-05', 'reason' => 'x', 'requested_by' => $user->id];

        AttendanceCorrectionRequest::create($base + ['status' => AttendanceCorrectionRequest::STATUS_PENDING]);
        AttendanceCorrectionRequest::create(['employee_id' => $employee->id, 'attendance_date' => '2026-01-06', 'reason' => 'x', 'requested_by' => $user->id, 'status' => AttendanceCorrectionRequest::STATUS_APPROVED]);
        AttendanceCorrectionRequest::create(['employee_id' => $employee->id, 'attendance_date' => '2026-01-07', 'reason' => 'x', 'requested_by' => $user->id, 'status' => AttendanceCorrectionRequest::STATUS_REJECTED]);

        $this->assertSame(1, AttendanceCorrectionRequest::pending()->count());
        $this->assertSame(1, AttendanceCorrectionRequest::approved()->count());
        $this->assertSame(1, AttendanceCorrectionRequest::rejected()->count());
    }
}
