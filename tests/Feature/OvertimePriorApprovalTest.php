<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRule;
use App\Models\SalarySlip;
use App\Services\OvertimePayService;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * الأجرُ الإضافي باعتمادٍ مسبق — فصلُ «ما وقع» عن «ما يُصرف».
 *
 * **ما يقيسه هذا الاختبار**
 *
 * `attendance_days.overtime_minutes` واقعةٌ تُسجَّل دائماً ولا يمسّها هذا
 * المفتاح. المقيسُ هنا هو ما يتحوّل منها إلى **بندِ أجرٍ على القسيمة**:
 * بلا طلبٍ معتمَد لا يُصرف شيء، ومع الاعتماد يُصرف الأقلُّ من المُعتمَد
 * والمُسجَّل.
 *
 * والقاعدةُ التي يسهل أن تنقلب إلى عكسها: **الاعتمادُ سقفٌ لا مِنحة**.
 * من اعتُمدت له أربعُ ساعاتٍ وعمل ساعتين يُصرف له ساعتان — لا أربع. وإلّا
 * صار الطلبُ المعتمَد بابَ صرفٍ لعملٍ لم يقع، وهو أسوأُ من غياب الطبقة
 * كلِّها.
 *
 * والتوافقُ مع القائم شرطٌ أيضاً: المفتاحُ معطَّلٌ افتراضياً، وتعطيلُه
 * يُبقي السلوكَ الحالي حرفياً.
 *
 * ويُقاس هنا كذلك **السقفُ اليومي** (`overtime_rules.max_minutes_per_day`):
 * كان مربوطاً في `breakdown()` منذ كُتِب وبلا اختبارٍ واحد، ثم صار حاملَ
 * حِملٍ حين ضُبط — فهو خطُّ الدفاع الذي يعمل ولو كان المفتاحان معطَّلين.
 */
class OvertimePriorApprovalTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    private const PERIOD_FROM = '2026-09-01';

    private const PERIOD_TO = '2026-09-30';

    private const WORK_DATE = '2026-09-16';

    /** 9000 ÷ 240 ساعة = 37.50 للساعة، والمضاعف 1.5 */
    private const BASIC = 9000.0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        DB::table('payroll_settings')->insert([
            'overtime_enabled' => true,
            'overtime_requires_approval' => false,
            'overtime_monthly_hours' => 240,
        ]);

        OvertimeRule::create([
            'code' => 'weekday',
            'name' => 'يوم عمل',
            'applies_on' => OvertimeRule::APPLIES_WEEKDAY,
            'multiplier' => 1.5,
            'status' => OvertimeRule::STATUS_ACTIVE,
        ]);

        PayrollSettings::forget();
    }

    protected function tearDown(): void
    {
        PayrollSettings::forget();
        parent::tearDown();
    }

    public function test_with_the_switch_off_behaviour_is_unchanged(): void
    {
        $employee = $this->employeeWithOvertime(120);

        // 120 دقيقة × 37.50 ÷ 60 × 1.5 = 112.50
        $this->assertEqualsWithDelta(112.50, $this->breakdown($employee)['amount'], 0.01);
    }

    public function test_with_the_switch_on_nothing_is_paid_without_an_approved_request(): void
    {
        $this->requireApproval();

        $employee = $this->employeeWithOvertime(120);

        $breakdown = $this->breakdown($employee);

        $this->assertSame(0.0, (float) $breakdown['weekday_minutes']);
        $this->assertSame(0.0, $breakdown['amount']);
    }

    public function test_an_approved_request_releases_the_minutes(): void
    {
        $this->requireApproval();

        $employee = $this->employeeWithOvertime(120);
        $this->approvedRequest($employee, minutes: 120);

        $this->assertEqualsWithDelta(112.50, $this->breakdown($employee)['amount'], 0.01);
    }

    public function test_approval_is_a_ceiling_not_a_grant(): void
    {
        $this->requireApproval();

        // اعتُمدت أربعُ ساعات، وعمل ساعتين
        $employee = $this->employeeWithOvertime(120);
        $this->approvedRequest($employee, minutes: 240);

        $breakdown = $this->breakdown($employee);

        // يُصرف ما عُمِل لا ما اعتُمد
        $this->assertSame(120.0, (float) $breakdown['weekday_minutes']);
        $this->assertEqualsWithDelta(112.50, $breakdown['amount'], 0.01);
    }

    public function test_work_beyond_the_approved_ceiling_is_capped(): void
    {
        $this->requireApproval();

        // عمل أربع ساعات، واعتُمدت له ساعتان
        $employee = $this->employeeWithOvertime(240);
        $this->approvedRequest($employee, minutes: 120);

        $breakdown = $this->breakdown($employee);

        $this->assertSame(120.0, (float) $breakdown['weekday_minutes']);
        $this->assertEqualsWithDelta(112.50, $breakdown['amount'], 0.01);
    }

    public function test_a_pending_or_rejected_request_releases_nothing(): void
    {
        $this->requireApproval();

        foreach ([OvertimeRequest::STATUS_PENDING, OvertimeRequest::STATUS_REJECTED, OvertimeRequest::STATUS_CANCELLED] as $status) {
            $employee = $this->employeeWithOvertime(120);

            OvertimeRequest::create([
                'employee_id' => $employee->id,
                'overtime_date' => self::WORK_DATE,
                'requested_minutes' => 120,
                'approved_minutes' => 120,
                'status' => $status,
            ]);

            $this->assertSame(
                0.0,
                $this->breakdown($employee)['amount'],
                "حالة «{$status}» أطلقت دقائق لا يجوز صرفها"
            );
        }
    }

    public function test_an_approval_for_another_day_does_not_release_this_one(): void
    {
        $this->requireApproval();

        $employee = $this->employeeWithOvertime(120);

        // اعتمادٌ لليوم التالي، والدقائقُ مسجَّلةٌ على يوم 16
        $this->approvedRequest($employee, minutes: 120, date: '2026-09-17');

        $this->assertSame(0.0, $this->breakdown($employee)['amount']);
    }

    public function test_an_approval_with_no_explicit_ceiling_falls_back_to_what_was_requested(): void
    {
        $this->requireApproval();

        $employee = $this->employeeWithOvertime(120);

        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'overtime_date' => self::WORK_DATE,
            'requested_minutes' => 90,
            'approved_minutes' => null,   // اعتُمد كما طُلب
            'status' => OvertimeRequest::STATUS_APPROVED,
        ]);

        // فراغُ السقف يعني «كما طُلب» لا «بلا سقف»
        $this->assertSame(90.0, (float) $this->breakdown($employee)['weekday_minutes']);
    }

    public function test_the_recorded_minutes_are_never_touched_by_the_switch(): void
    {
        $this->requireApproval();

        $employee = $this->employeeWithOvertime(240);

        $this->assertSame(0.0, $this->breakdown($employee)['amount']);

        // الواقعةُ باقية: ما يُحجب هو الصرفُ لا التسجيل
        $this->assertSame(240, (int) DB::table('attendance_days')
            ->where('employee_id', $employee->id)
            ->value('overtime_minutes'));
    }

    public function test_the_daily_ceiling_clips_runaway_minutes_even_with_both_switches_off(): void
    {
        // عشرُ ساعاتٍ «إضافي» — انصرافٌ منسيٌّ لا عمل
        $employee = $this->employeeWithOvertime(600);

        OvertimeRule::where('applies_on', OvertimeRule::APPLIES_WEEKDAY)
            ->update(['max_minutes_per_day' => 240]);

        $breakdown = $this->breakdown($employee);

        // السقفُ يعمل بلا اعتمادٍ ولا نافذة — آخرُ حارسٍ قبل القسيمة
        $this->assertSame(240.0, (float) $breakdown['weekday_minutes']);
        $this->assertEqualsWithDelta(225.00, $breakdown['amount'], 0.01, '240 د × 37.50 ÷ 60 × 1.5');
    }

    public function test_the_daily_ceiling_does_not_touch_work_below_it(): void
    {
        $employee = $this->employeeWithOvertime(120);

        OvertimeRule::where('applies_on', OvertimeRule::APPLIES_WEEKDAY)
            ->update(['max_minutes_per_day' => 240]);

        // السقفُ حدٌّ لا قيمةٌ ثابتة
        $this->assertSame(120.0, (float) $this->breakdown($employee)['weekday_minutes']);
    }

    public function test_the_daily_ceiling_and_the_approved_ceiling_both_apply_lowest_wins(): void
    {
        $this->requireApproval();

        // عمل 600 د · السقف اليومي 240 · المعتمَد 180 → يُصرف 180
        $employee = $this->employeeWithOvertime(600);
        $this->approvedRequest($employee, minutes: 180);

        OvertimeRule::where('applies_on', OvertimeRule::APPLIES_WEEKDAY)
            ->update(['max_minutes_per_day' => 240]);

        $this->assertSame(180.0, (float) $this->breakdown($employee)['weekday_minutes']);
    }

    public function test_a_rule_without_a_ceiling_clips_nothing(): void
    {
        // التوافقُ مع القائم: قاعدةٌ بلا سقفٍ تبقى كما كانت
        $employee = $this->employeeWithOvertime(600);

        $this->assertSame(600.0, (float) $this->breakdown($employee)['weekday_minutes']);
    }

    // ════════════════════════════════════════════════════

    private function requireApproval(): void
    {
        DB::table('payroll_settings')->update(['overtime_requires_approval' => true]);
        PayrollSettings::forget();
    }

    private function employeeWithOvertime(int $minutes): Employee
    {
        static $sequence = 0;
        $sequence++;

        $employee = Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "ot{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
        ]);

        DB::table('attendance_days')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => self::WORK_DATE,
            'check_in_time' => '08:00:00',
            'check_out_time' => '16:00:00',
            'status' => 'present',
            'overtime_minutes' => $minutes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SalarySlip::create([
            'employee_id' => $employee->id,
            'basic_amount' => self::BASIC,
            'start_date' => self::PERIOD_FROM,
            'end_date' => self::PERIOD_TO,
            'status' => 'active',
        ]);

        return $employee;
    }

    private function approvedRequest(Employee $employee, int $minutes, string $date = self::WORK_DATE): OvertimeRequest
    {
        return OvertimeRequest::create([
            'employee_id' => $employee->id,
            'overtime_date' => $date,
            'requested_minutes' => $minutes,
            'approved_minutes' => $minutes,
            'status' => OvertimeRequest::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    private function breakdown(Employee $employee): array
    {
        return app(OvertimePayService::class)
            ->breakdown($employee, self::PERIOD_FROM, self::PERIOD_TO, self::BASIC);
    }
}
