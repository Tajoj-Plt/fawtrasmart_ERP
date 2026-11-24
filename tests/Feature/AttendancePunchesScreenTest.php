<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\AttendancePunchesController;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * شاشةُ «سجلات الحضور» — الحركاتُ الخام.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن الشاشةَ تُسلّم للقالب ما يحتاجه فعلاً: قائمةَ الموظفين التي تُبنى
 * منها تصفيةُ `employee_id`. كانت الشاشةُ تقبل المُرشِّح في المتحكّم ولا
 * تُرسل القائمةَ إلى القالب — فالمُرشِّحُ موجودٌ في الشيفرة ولا سبيلَ
 * لاستعماله من الواجهة.
 *
 * وأن المردودَ يُعرض مع المقبول: محاولةٌ رُفضت هي الدليلُ الوحيد على أن
 * أحداً حاول، وإخفاؤها يجعل «لم يسجّل» و«سجّل فرُفض» شيئاً واحداً.
 */
class AttendancePunchesScreenTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        Schema::create('users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->timestamps();
        });

        AttendanceSetting::create([
            'start_month' => 1, 'start_day' => 1,
            'late_grace_minutes' => 15, 'absent_after_minutes' => 120,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
        ]);

        $this->actingAs(User::create([
            'name' => 'مشغّل', 'email' => 'punches@example.test', 'employee_id' => null,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_screen_hands_the_template_an_employee_list_for_its_filter(): void
    {
        $this->employee();
        $this->employee();

        $data = $this->screen();

        $this->assertArrayHasKey('employees', $data);
        $this->assertCount(2, $data['employees']);
    }

    public function test_filtering_by_employee_narrows_the_punches(): void
    {
        $mine = $this->employee();
        $theirs = $this->employee();

        $this->stampIn($mine, '08:10:00');
        $this->stampIn($theirs, '08:20:00');

        $all = $this->screen();
        $this->assertCount(2, $all['punches']);

        $one = $this->screen(['employee_id' => $mine->id]);
        $this->assertCount(1, $one['punches']);
        $this->assertSame($mine->id, $one['punches'][0]->employee_id);
        $this->assertSame((string) $mine->id, (string) $one['filters']['employee_id']);
    }

    public function test_a_rejected_attempt_is_listed_beside_the_accepted_one(): void
    {
        $employee = $this->employee();

        $this->stampIn($employee, '08:00:00');

        // ختمُ دخولٍ ثانٍ في اليوم نفسِه — يُردّ ويُكتب مردوداً
        Carbon::setTestNow(Carbon::parse('2026-09-16 09:00:00'));
        app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        $this->assertCount(2, $this->screen()['punches']);
        $this->assertCount(1, $this->screen(['result' => 'rejected'])['punches']);
        $this->assertCount(1, $this->screen(['result' => 'accepted'])['punches']);
    }

    // ════════════════════════════════════════════════════

    private function employee(): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "punch{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'PCARD_'.$sequence.'_'.uniqid(),
            'attendance_channel' => 'both',
        ]);
    }

    private function stampIn(Employee $employee, string $time): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 '.$time));

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        $this->assertTrue($result->accepted, $result->message);
    }

    /** @return array<string, mixed> */
    private function screen(array $query = []): array
    {
        return (new AttendancePunchesController)
            ->index(Request::create('/', 'GET', $query))
            ->getData();
    }
}
