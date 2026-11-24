<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\DailyAttendanceBoard;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\TeamLiveStatusController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * لوحةُ متابعة الفريق.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن النطاقَ يُشتقّ من **الهيكل التنظيمي** لا من الصلاحية: المديرُ يرى
 * من يرأسهم هو وحدهم. وهذا ما يفرقها عن سجل اليوم الموحَّد — ومديرٌ يفتح
 * جدولاً بمئتي موظفٍ ليجد أربعةً من فريقه لن يفتحه مرّتين.
 *
 * والحالةُ التي يسهل أن تُغفَل: **المستوياتُ الأدنى**. مديرٌ يرأس مديراً
 * يرأس موظفين — فريقُه هم الثلاثة، لا المباشرون وحدهم. ومفتاحٌ يفصل
 * الحالتين لأن السؤالين مختلفان.
 *
 * ولا يُعاد حسابُ الحالات هنا: `DailyAttendanceBoard` مصدرُها الوحيد،
 * فلا تصير «غائب» في شاشةٍ «لم يُسجِّل بعد» في أخرى.
 */
class TeamLiveStatusTest extends TestCase
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

        foreach (['departments', 'branches', 'jop_titles'] as $table) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->bigIncrements('id');
                    $t->string('name')->nullable();
                    $t->timestamps();
                });
            }
        }

        AttendanceSetting::create([
            'start_month' => 1, 'start_day' => 1,
            'late_grace_minutes' => 15, 'absent_after_minutes' => 120,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_board_shows_only_the_managers_own_team(): void
    {
        $manager = $this->employee();
        $mine = $this->employee($manager->id);
        $alsoMine = $this->employee($manager->id);

        // موظفٌ لا يتبع هذا المدير — ولا يجوز أن يظهر
        $stranger = $this->employee();

        $rows = $this->board($manager)['rows'];

        $this->assertEqualsCanonicalizing(
            [$mine->id, $alsoMine->id],
            $rows->pluck('employee.id')->all()
        );
        $this->assertNotContains($stranger->id, $rows->pluck('employee.id')->all());
        $this->assertNotContains($manager->id, $rows->pluck('employee.id')->all());
    }

    public function test_lower_levels_are_included_by_default_and_can_be_excluded(): void
    {
        $manager = $this->employee();
        $lead = $this->employee($manager->id);
        $junior = $this->employee($lead->id);

        // الافتراضُ: الفريقُ كلُّه — المباشرون ومن تحتهم
        $all = $this->board($manager)['rows']->pluck('employee.id')->all();
        $this->assertEqualsCanonicalizing([$lead->id, $junior->id], $all);

        // والمباشرون وحدهم عند طلب ذلك
        $direct = $this->board($manager, ['all_levels' => '0'])['rows']->pluck('employee.id')->all();
        $this->assertSame([$lead->id], $direct);
    }

    public function test_the_summary_separates_late_from_absent_and_pending(): void
    {
        $manager = $this->employee();
        $late = $this->employee($manager->id);
        $onTime = $this->employee($manager->id);
        $this->employee($manager->id);   // لم يحضر

        $this->stampIn($late, '08:45:00');
        $this->stampIn($onTime, '08:05:00');

        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));

        $summary = $this->board($manager)['summary'];

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['present']);
        $this->assertSame(1, $summary['absent']);
        /*
            «متأخر» تعني تجاوزَ السماحية لا دقيقةً بعد الموعد: من حضر 08:05
            بسماحٍ 15 دقيقةً ليس متأخراً. وعدُّه كذلك يُغرق اللوحةَ بأسماءٍ
            لا شأن للمدير بها.
        */
        $this->assertSame(1, $summary['late']);
    }

    public function test_a_manager_with_no_team_gets_an_empty_board_not_an_error(): void
    {
        $manager = $this->employee();

        $data = $this->board($manager);

        $this->assertTrue($data['rows']->isEmpty());
        $this->assertSame(0, $data['summary']['total']);
    }

    public function test_a_user_without_an_employee_file_is_refused(): void
    {
        $this->actingAs(User::create([
            'name' => 'حساب إداري', 'email' => 'admin@example.test', 'employee_id' => null,
        ]));

        $this->expectException(AccessDeniedHttpException::class);

        (new TeamLiveStatusController)->index(Request::create('/', 'GET'));
    }

    public function test_the_live_feed_mirrors_the_board(): void
    {
        $manager = $this->employee();
        $member = $this->employee($manager->id);

        $this->stampIn($member, '08:45:00');
        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));

        $this->actingAs($this->userFor($manager));

        $payload = (new TeamLiveStatusController)
            ->feed(Request::create('/', 'GET'))
            ->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertCount(1, $payload['members']);
        $this->assertSame('08:45', $payload['members'][0]['check_in']);
        $this->assertSame(45, $payload['members'][0]['late_minutes']);
        $this->assertSame(DailyAttendanceBoard::STATE_PRESENT, $payload['members'][0]['state']);
    }

    // ════════════════════════════════════════════════════

    private function employee(?int $managerId = null): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "team{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'CARD_'.$sequence.'_'.uniqid(),
            'attendance_channel' => 'both',
            'direct_manager_id' => $managerId,
        ]);
    }

    private function userFor(Employee $employee): User
    {
        // نداءان للوحة في اختبارٍ واحد يعنيان مستخدمَين بالبريد نفسه
        return User::firstOrCreate(
            ['email' => 'u'.$employee->id.'@example.test'],
            ['name' => $employee->full_name, 'employee_id' => $employee->id],
        );
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

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function board(Employee $manager, array $query = []): array
    {
        $this->actingAs($this->userFor($manager));

        return (new TeamLiveStatusController)
            ->index(Request::create('/', 'GET', $query))
            ->getData();
    }
}
