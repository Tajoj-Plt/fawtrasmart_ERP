<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeAttendanceConfig;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * سير العملية كاملة: توظيف → إعدادات حضور للموظف → مسح حضور متأخر → حساب راتب.
 *
 * يتحقق أن خصم التأخير يُنشأ من إعدادات الموظف ويصل إلى صافي الراتب.
 */
class EmployeeHireToPayrollFlowTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    private const BASIC_SALARY = 5000.0;

    private const LATE_PER_MINUTE = 3.0;

    private const LATE_MINUTES = 20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        // إعدادات عامة ضعيفة — يجب أن يغلبها إعداد الموظف
        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 60,
            'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
            'auto_deduction_enabled' => false,
            'late_deduction_amount' => 0.5,
            'deduction_calculation_type' => 'per_minute',
            'early_leave_threshold_minutes' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hire_configure_stamp_then_payroll_applies_employee_late_deduction(): void
    {
        // ── 1) التوظيف ──────────────────────────────────────────────
        $shift = Shift::create(['name' => 'صباحية']);
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            ShiftDay::create([
                'shift_id' => $shift->id,
                'day' => $day,
                'working_day' => true,
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'grace_period' => 0,
            ]);
        }

        $employee = Employee::create([
            'first_name' => 'أحمد',
            'middle_name' => 'كامل',
            'email' => 'hire2pay'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $shift->id,
            'barcode' => 'H2P_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);

        $this->assertSame(EmploymentStatus::ACTIVE->value, (int) $employee->status);
        $this->assertNotNull($employee->hire_date);

        // ── 2) إعدادات حضور خاصة بالموظف ────────────────────────────
        EmployeeAttendanceConfig::create([
            'employee_id' => $employee->id,
            'expected_check_in' => '08:00:00',
            'expected_check_out' => '16:00:00',
            'late_grace_minutes' => 0,
            'auto_deduction_enabled' => true,
            'late_deduction_amount' => self::LATE_PER_MINUTE,
        ]);

        // ── 3) قسيمة راتب ───────────────────────────────────────────
        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'basic_amount' => self::BASIC_SALARY,
            'status' => SalarySlip::STATUS_ACTIVE,
            'start_date' => '2026-09-01',
        ]);

        // ── 4) حضور متأخر 20 دقيقة عن 08:00 ─────────────────────────
        Carbon::setTestNow(Carbon::parse('2026-09-20 08:20:00')); // الأحد

        $stamp = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        $this->assertTrue($stamp->accepted, $stamp->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(self::LATE_MINUTES, (int) $day->late_minutes);
        $this->assertNotNull($day->deduction_id, 'يجب إنشاء خصم تأخير فور الحضور');

        $deduction = Deduction::findOrFail($day->deduction_id);
        $expectedDeduction = self::LATE_MINUTES * self::LATE_PER_MINUTE; // 60

        $this->assertSame('late', $deduction->deduction_type);
        $this->assertSame(Deduction::STATUS_APPROVED, $deduction->status);
        $this->assertEqualsWithDelta($expectedDeduction, (float) $deduction->amount, 0.01);
        $this->assertTrue(
            $employee->unsettledDeductions()->where('id', $deduction->id)->exists(),
            'الخصمية يجب أن تظهر لاستعلام الرواتب'
        );

        // إعداد الموظف غلب العام (العام معطّل وبـ 0.5 فقط)
        $this->assertNotEquals(10.0, (float) $deduction->amount);

        // ── 5) حساب الراتب ──────────────────────────────────────────
        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'scheduled_date' => '2026-09-30',
            'status' => PayrollRun::STATUS_PENDING,
            'basic_amount' => self::BASIC_SALARY,
        ]);

        $calc = app(PayrollCalculator::class)->calculate($run, $slip, $employee);

        $this->assertEqualsWithDelta(self::BASIC_SALARY, $calc->basic, 0.01);
        $this->assertEqualsWithDelta($expectedDeduction, $calc->totalDeductions, 0.01);
        $this->assertEqualsWithDelta(
            self::BASIC_SALARY - $expectedDeduction,
            $calc->netSalary,
            0.01,
            'الصافي = 5000 − (20 × 3) = 4940'
        );

        // الحساب لا يسوّي الخصمية بعد — التسوية عند الصرف الفعلي
        $this->assertEqualsWithDelta(0.0, (float) $deduction->fresh()->settled_amount, 0.01);
    }

    public function test_on_time_hire_flow_pays_full_basic_with_no_late_deduction(): void
    {
        $shift = Shift::create(['name' => 'صباحية']);
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            ShiftDay::create([
                'shift_id' => $shift->id,
                'day' => $day,
                'working_day' => true,
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
            ]);
        }

        $employee = Employee::create([
            'first_name' => 'سارة',
            'email' => 'ontime'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $shift->id,
            'barcode' => 'OT_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);

        EmployeeAttendanceConfig::create([
            'employee_id' => $employee->id,
            'late_grace_minutes' => 15,
            'auto_deduction_enabled' => true,
            'late_deduction_amount' => self::LATE_PER_MINUTE,
        ]);

        $slip = SalarySlip::create([
            'employee_id' => $employee->id,
            'basic_amount' => self::BASIC_SALARY,
            'status' => SalarySlip::STATUS_ACTIVE,
            'start_date' => '2026-09-01',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-20 08:05:00')); // داخل السماح 15

        $stamp = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        $this->assertTrue($stamp->accepted, $stamp->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(AttendanceDays::STATUS_PRESENT, $day->status);
        $this->assertNull($day->deduction_id);
        $this->assertSame(0, Deduction::count());

        $run = PayrollRun::create([
            'salary_slip_id' => $slip->id,
            'employee_id' => $employee->id,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'status' => PayrollRun::STATUS_PENDING,
            'basic_amount' => self::BASIC_SALARY,
        ]);

        $calc = app(PayrollCalculator::class)->calculate($run, $slip, $employee);

        $this->assertEqualsWithDelta(0.0, $calc->totalDeductions, 0.01);
        $this->assertEqualsWithDelta(self::BASIC_SALARY, $calc->netSalary, 0.01);
    }
}
