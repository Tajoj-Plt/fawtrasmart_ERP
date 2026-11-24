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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Attendance\UnifiedAttendanceLogController;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * سجلُّ اليوم الموحَّد.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن السجلَّ يعرض **كلَّ** الموظفين لا الحاضرين وحدهم. جدولٌ مبنيٌّ على
 * `attendance_days` وحده — كما القائمةُ القائمة — لا يعرض إلا من له صفٌّ،
 * فلا يُجيب السؤالَ الذي يُفتح لأجله كلَّ صباح: «من ينقصنا؟».
 *
 * والتمييزُ الذي لا يظهر في `attendance_days.status`: **«لم يسجّل بعد»
 * مقابل «غائب»**. الأولى انتظارٌ والثانيةُ حكم، وخلطُهما في الثامنة
 * صباحاً يُنذر بغيابٍ لم يقع بعد.
 *
 * ويُقاس كذلك أن الوسيلةَ والموقعَ يُقرآن من الختم لا من القيم الافتراضية:
 * صفُّ يومٍ يولّده الكرون للغياب يحمل `check_in_method = 'manual'` وهو
 * لم يُسجَّل فيه شيء.
 */
class UnifiedAttendanceLogTest extends TestCase
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

        foreach (['departments', 'branches'] as $table) {
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
            'default_weekly_off_days' => [],   // لا عطلةَ تُلوّن نتائج الاختبار
        ]);

        $this->actingAs(User::create([
            'name' => 'مشغّل', 'email' => 'log@example.test', 'employee_id' => null,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_every_employee_appears_even_without_a_record(): void
    {
        $this->employee();
        $this->employee();
        $this->employee();

        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));

        $data = $this->log();

        $this->assertCount(3, $data['rows']);
        $this->assertSame(3, $data['summary']['total']);
        $this->assertSame(3, $data['summary']['absent']);
    }

    public function test_a_checked_in_employee_shows_channel_location_and_lateness(): void
    {
        $present = $this->employee();
        $this->employee();

        $this->stampIn($present, '08:45:00');
        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));

        $row = $this->log()['rows']->firstWhere('employee.id', $present->id);

        $this->assertSame('present', $row['state']);
        $this->assertSame('08:45:00', $row['check_in']);
        $this->assertSame(45, $row['late_minutes']);
        $this->assertStringContainsString('جهاز الفرع', $row['channel']);
        $this->assertStringContainsString('بطاقة', $row['channel']);
        $this->assertSame('08:00:00', $row['expected_in']);
    }

    public function test_before_the_shift_starts_nobody_is_marked_absent(): void
    {
        $this->employee();
        $this->employee();

        // السابعة صباحاً والوردية تبدأ الثامنة — انتظارٌ لا غياب
        Carbon::setTestNow(Carbon::parse('2026-09-16 07:00:00'));

        $summary = $this->log(Carbon::today()->toDateString())['summary'];

        $this->assertSame(2, $summary['pending']);
        $this->assertSame(0, $summary['absent']);
    }

    public function test_a_day_row_without_any_punch_reports_no_method(): void
    {
        $employee = $this->employee();

        // ما يولّده الكرون للغياب: صفٌّ بأعمدةِ وسيلةٍ افتراضية وبلا ختم
        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-16',
            'status' => 'absent',
            'check_in_method' => 'manual',
            'check_out_method' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));

        $row = $this->log()['rows']->firstWhere('employee.id', $employee->id);

        // «يدوي» هنا كانت ستقول «سُجِّل يدوياً» عن يومٍ لم يُسجَّل فيه شيء
        $this->assertNull($row['channel']);
        $this->assertSame('absent', $row['state']);
    }

    public function test_a_completed_day_reports_worked_hours(): void
    {
        $employee = $this->employee();

        $this->stampIn($employee, '08:00:00');
        $this->normalizeAttendanceDates();
        $this->stampOut($employee, '16:30:00');

        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));

        $row = $this->log()['rows']->firstWhere('employee.id', $employee->id);

        $this->assertSame('completed', $row['state']);
        $this->assertSame(510, $row['working_minutes']); // 8 ساعات ونصف
        $this->assertSame(30, $row['overtime_minutes']);
    }

    /**
     * أثرُ اليوم على الراتب يُقرأ من الصفّ نفسِه.
     *
     * «متأخر ٤٠ دقيقة» و«متأخر ٤٠ دقيقة وخُصم عليها» حالتان مختلفتان عند
     * من يراجع قبل إغلاق الشهر، وكان الصفُّ يعرضهما سواءً — فلا يُعرف
     * المُرحَّلُ للراتب من غيرِه إلا بفتح شاشة الخصميات وحدها.
     */
    public function test_a_day_row_carries_the_deduction_it_generated(): void
    {
        $employee = $this->employee();

        $this->stampIn($employee, '08:00:00');
        Carbon::setTestNow(Carbon::parse('2026-09-16 18:00:00'));

        $this->assertArrayHasKey('deduction', $this->log()['rows']->first());
        $this->assertNull($this->log()['rows']->first()['deduction']);

        $deductionId = DB::table('deductions')->insertGetId([
            'employee_id' => $employee->id,
            'deduction_type' => 'late',
            'amount' => 25.5,
            'status' => 'approved',
            'date' => '2026-09-16',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('attendance_days')
            ->where('employee_id', $employee->id)
            ->update(['deduction_id' => $deductionId]);

        $row = $this->log()['rows']->first();

        $this->assertNotNull($row['deduction']);
        $this->assertSame('25.50', number_format((float) $row['deduction']->amount, 2));
    }

    // ════════════════════════════════════════════════════

    private function employee(): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "log{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'CARD_'.$sequence.'_'.uniqid(),
            'attendance_channel' => 'both',
        ]);
    }

    private function stampIn(Employee $employee, string $time): void
    {
        $this->stamp($employee, $time, AttendancePunch::TYPE_IN);
    }

    private function stampOut(Employee $employee, string $time): void
    {
        $this->stamp($employee, $time, AttendancePunch::TYPE_OUT);
    }

    private function stamp(Employee $employee, string $time, string $type): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 '.$time));

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: $type,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        $this->assertTrue($result->accepted, $result->message);
    }

    /** @return array<string, mixed> */
    private function log(string $date = '2026-09-16'): array
    {
        return (new UnifiedAttendanceLogController)
            ->index(Request::create('/', 'GET', ['date' => $date]))
            ->getData();
    }
}
