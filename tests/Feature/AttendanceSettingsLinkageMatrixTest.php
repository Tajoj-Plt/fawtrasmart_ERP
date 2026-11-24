<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendanceGeofence;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePhotoType;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\CustomShift;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeAttendanceConfig;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * مصفوفةُ ربط إعدادات الحضور بمسار المسح.
 *
 * كلُّ اختبار هنا يضبط إعداداً من شاشةٍ معروفة ثم يمسح بطاقةً ويتأكد
 * أن الأثر يصل إلى الحالة / الخصم / الرفض. الشاشات الميتة (قواعد الحضور
 * القديمة، الإعدادات الأساسية للاعتماد، أجهزة البصمة) موثَّقة في التعليق
 * الأخير وليست ضمن هذا المسار.
 */
class AttendanceSettingsLinkageMatrixTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    /** الأحد — يوم عمل في الورديات أدناه */
    private const WORK_DAY = '2026-09-20';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        if (! \Illuminate\Support\Facades\Schema::hasColumn('holidays', 'is_paid')) {
            \Illuminate\Support\Facades\Schema::table('holidays', function ($t) {
                $t->boolean('is_paid')->default(true);
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('holidays', 'holiday_list_id')) {
            \Illuminate\Support\Facades\Schema::table('holidays', function ($t) {
                $t->unsignedBigInteger('holiday_list_id')->nullable();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('employee_attendance_configs', 'photos_required')) {
            \Illuminate\Support\Facades\Schema::table('employee_attendance_configs', function ($t) {
                $t->boolean('photos_required')->nullable();
            });
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('employee_attendance_configs', 'auto_deduction_enabled')) {
            \Illuminate\Support\Facades\Schema::table('employee_attendance_configs', function ($t) {
                $t->boolean('auto_deduction_enabled')->nullable();
                $t->decimal('late_deduction_amount', 10, 2)->nullable();
            });
        }

        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 0,
            'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_shift_start' => '08:00:00',
            'default_weekly_off_days' => [],
            'auto_deduction_enabled' => true,
            'late_deduction_amount' => 2.0,
            'absence_deduction_amount' => 50.0,
            'deduction_calculation_type' => 'per_minute',
            'photos_required' => false,
            'photos_count_checkin' => 0,
            'photos_count_checkout' => 0,
            'checkout_window_minutes' => null,
            'early_leave_threshold_minutes' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function shift(
        string $start = '08:00:00',
        string $end = '16:00:00',
        ?int $grace = 0,
        ?string $logoutEnd = null,
        ?string $loginStart = null,
        ?string $loginEnd = null,
        ?string $logoutStart = null,
    ): int {
        $id = DB::table('shifts')->insertGetId([
            'name' => 'وردية '.$start,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            DB::table('shift_days')->insert([
                'shift_id' => $id,
                'day' => $day,
                'working_day' => 1,
                'start_time' => $start,
                'end_time' => $end,
                'grace_period' => $grace,
                'login_start_time' => $loginStart,
                'login_end_time' => $loginEnd,
                'logout_start_time' => $logoutStart,
                'logout_end_time' => $logoutEnd,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function employee(?int $shiftId = null): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $n,
            'email' => "matrix{$n}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => $shiftId ?? $this->shift(),
            'barcode' => 'MX_'.$n.'_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);
    }

    private function stampIn(Employee $employee, string $at, array $extra = []): \App\Services\Attendance\StampResult
    {
        Carbon::setTestNow(Carbon::parse($at));

        return app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            latitude: $extra['latitude'] ?? null,
            longitude: $extra['longitude'] ?? null,
            accuracyMeters: isset($extra['accuracy']) ? (int) $extra['accuracy'] : null,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
            kiosk: $extra['kiosk'] ?? null,
            photos: $extra['photos'] ?? [],
        ));
    }

    private function stampOut(Employee $employee, string $at, array $extra = []): \App\Services\Attendance\StampResult
    {
        Carbon::setTestNow(Carbon::parse($at));

        return app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_OUT,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
            kiosk: $extra['kiosk'] ?? null,
            photos: $extra['photos'] ?? [],
        ));
    }

    // ─── إعدادات الحضور V2 ───────────────────────────────────

    /** مدة السماح 0 + خصم لكل دقيقة → تأخير 10 د يُخصم فوراً */
    public function test_v2_grace_zero_and_per_minute_deduction_apply_on_scan(): void
    {
        $employee = $this->employee();
        $result = $this->stampIn($employee, self::WORK_DAY.' 08:10:00');

        $this->assertTrue($result->accepted, $result->message);
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(10, (int) $day->late_minutes);
        $this->assertNotNull($day->deduction_id);
        $this->assertEqualsWithDelta(20.0, (float) Deduction::find($day->deduction_id)->amount, 0.01);
    }

    /** مدة السماح 15 → تأخير 10 لا يُخصم */
    public function test_v2_grace_blocks_deduction_within_allowance(): void
    {
        AttendanceSetting::query()->update(['late_grace_minutes' => 15]);
        $employee = $this->employee($this->shift(grace: 15));

        $this->stampIn($employee, self::WORK_DAY.' 08:10:00');
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(AttendanceDays::STATUS_PRESENT, $day->status);
        $this->assertNull($day->deduction_id);
    }

    /** العطلة الأسبوعية من V2 ترفض المسح */
    public function test_v2_weekly_off_rejects_check_in(): void
    {
        AttendanceSetting::query()->update(['default_weekly_off_days' => ['sun']]);
        $employee = $this->employee();

        $result = $this->stampIn($employee, self::WORK_DAY.' 08:00:00');

        $this->assertFalse($result->accepted);
        $this->assertSame('weekly_off', $result->code);
    }

    /** بداية الدوام الافتراضية بلا وردية */
    public function test_v2_default_shift_start_drives_lateness_without_shift(): void
    {
        AttendanceSetting::query()->update(['default_shift_start' => '09:00:00']);
        $employee = Employee::create([
            'first_name' => 'بلا',
            'email' => 'noshift'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'shift_id' => null,
            'barcode' => 'NS_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);

        $this->stampIn($employee, self::WORK_DAY.' 09:20:00');
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('09:00:00', $day->expected_check_in);
        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(20, (int) $day->late_minutes);
    }

    /** طلب الصور من V2 يرفض الحضور بلا صورة */
    public function test_v2_photos_required_rejects_check_in_without_photo(): void
    {
        AttendanceSetting::query()->update([
            'photos_required' => true,
            'photos_count_checkin' => 1,
        ]);

        AttendancePhotoType::create([
            'name' => 'وجه',
            'phase' => 'checkin',
            'is_active' => true,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $employee = $this->employee();
        $result = $this->stampIn($employee, self::WORK_DAY.' 08:00:00');

        $this->assertFalse($result->accepted);
        $this->assertSame('photos_missing', $result->code);
    }

    /** نافذة الانصراف العامة من V2 تقصّ الوقت */
    public function test_v2_checkout_window_caps_late_checkout(): void
    {
        AttendanceSetting::query()->update(['checkout_window_minutes' => 30]);
        // بلا logout_end_time على الوردية — النافذة العامة تعمل
        $employee = $this->employee($this->shift('08:00:00', '16:00:00', 0, null));

        $this->stampIn($employee, self::WORK_DAY.' 08:00:00');
        $out = $this->stampOut($employee, self::WORK_DAY.' 20:00:00');

        $this->assertTrue($out->accepted, $out->message);
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
        $this->assertTrue((bool) $day->check_out_capped);
        $this->assertSame('16:00:00', $day->check_out_time);
    }

    // ─── إعداد موظف فردي ─────────────────────────────────────

    public function test_employee_config_grace_override_applies(): void
    {
        $employee = $this->employee($this->shift(grace: 0));
        EmployeeAttendanceConfig::create([
            'employee_id' => $employee->id,
            'late_grace_minutes' => 20,
        ]);

        $this->stampIn($employee->fresh(), self::WORK_DAY.' 08:15:00');
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(AttendanceDays::STATUS_PRESENT, $day->status);
        $this->assertNull($day->deduction_id);
    }

    public function test_employee_photos_required_overrides_global_off(): void
    {
        AttendanceSetting::query()->update(['photos_required' => false]);

        $employee = $this->employee();
        EmployeeAttendanceConfig::create([
            'employee_id' => $employee->id,
            'photos_required' => true,
        ]);

        AttendancePhotoType::create([
            'name' => 'هوية',
            'phase' => 'both',
            'is_active' => true,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $result = $this->stampIn($employee->fresh(), self::WORK_DAY.' 08:00:00');

        $this->assertFalse($result->accepted);
        $this->assertSame('photos_missing', $result->code);
    }

    // ─── الورديات والورديات المخصّصة ─────────────────────────

    public function test_shift_times_drive_expected_check_in_on_scan(): void
    {
        $employee = $this->employee($this->shift('10:00:00', '18:00:00'));

        $this->stampIn($employee, self::WORK_DAY.' 10:30:00');
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('10:00:00', $day->expected_check_in);
        $this->assertSame(30, (int) $day->late_minutes);
    }

    public function test_custom_shift_assignment_outranks_permanent_on_scan(): void
    {
        $permanent = $this->shift('10:00:00', '18:00:00');
        $assigned = $this->shift('06:00:00', '14:00:00');
        $employee = $this->employee($permanent);

        $custom = CustomShift::create([
            'name' => 'مبكرة',
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
            'shift_id' => $assigned,
            'use_rules' => 2,
            'is_main' => true,
        ]);
        $custom->employees()->attach($employee->id);

        $this->stampIn($employee, self::WORK_DAY.' 06:45:00');
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('06:00:00', $day->expected_check_in);
        $this->assertSame(45, (int) $day->late_minutes);
    }

    public function test_shift_logout_end_window_caps_checkout(): void
    {
        $employee = $this->employee($this->shift('08:00:00', '16:00:00', 0, '17:00:00'));

        $this->stampIn($employee, self::WORK_DAY.' 08:00:00');
        $this->stampOut($employee, self::WORK_DAY.' 22:00:00');
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertTrue((bool) $day->check_out_capped);
        $this->assertSame('16:00:00', $day->check_out_time);
    }

    // ─── العطل الرسمية ───────────────────────────────────────

    public function test_public_holiday_rejects_check_in(): void
    {
        Holiday::create([
            'named' => 'عطلة اختبار',
            'holiday_date' => self::WORK_DAY,
            'recurrence_type' => Holiday::RECURRENCE_ONCE,
            'is_active' => true,
            'is_paid' => true,
        ]);

        $employee = $this->employee();
        $result = $this->stampIn($employee, self::WORK_DAY.' 08:00:00');

        $this->assertFalse($result->accepted);
        $this->assertSame('public_holiday', $result->code);
    }

    // ─── النطاقات والأجهزة ───────────────────────────────────

    public function test_geofence_rejects_stamp_outside_radius(): void
    {
        $fence = AttendanceGeofence::create([
            'name' => 'المقر',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'radius_meters' => 100,
            'max_accuracy_meters' => 100,
            'require_geo' => true,
            'require_network' => false,
            'is_active' => true,
        ]);

        $kiosk = AttendanceKiosk::create([
            'name' => 'بوابة',
            'geofence_id' => $fence->id,
            'is_active' => true,
        ]);

        $employee = $this->employee();
        $result = $this->stampIn($employee, self::WORK_DAY.' 08:00:00', [
            'kiosk' => $kiosk,
            'latitude' => 25.0,
            'longitude' => 47.0,
            'accuracy' => 10,
        ]);

        $this->assertFalse($result->accepted);
        $this->assertSame('outside_geofence', $result->code);
    }

    public function test_geofence_accepts_stamp_inside_radius(): void
    {
        $fence = AttendanceGeofence::create([
            'name' => 'المقر',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'radius_meters' => 500,
            'max_accuracy_meters' => 100,
            'require_geo' => true,
            'require_network' => false,
            'is_active' => true,
        ]);

        $kiosk = AttendanceKiosk::create([
            'name' => 'بوابة',
            'geofence_id' => $fence->id,
            'is_active' => true,
        ]);

        $employee = $this->employee();
        $result = $this->stampIn($employee, self::WORK_DAY.' 08:00:00', [
            'kiosk' => $kiosk,
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'accuracy' => 10,
        ]);

        $this->assertTrue($result->accepted, $result->message);
    }

    // ─── عتبة الغياب من V2 ───────────────────────────────────

    public function test_absent_after_threshold_marks_absent_and_deducts(): void
    {
        AttendanceSetting::query()->update([
            'absent_after_minutes' => 60,
            'late_deduction_amount' => 2,
            'absence_deduction_amount' => 40,
            'deduction_calculation_type' => 'fixed',
        ]);

        $employee = $this->employee();
        $this->stampIn($employee, self::WORK_DAY.' 09:30:00'); // 90 دقيقة
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(AttendanceDays::STATUS_ABSENT, $day->status);
        $this->assertNotNull($day->deduction_id);
        $this->assertSame('absence', Deduction::find($day->deduction_id)->deduction_type);
        $this->assertEqualsWithDelta(40.0, (float) Deduction::find($day->deduction_id)->amount, 0.01);
    }

    public function test_holiday_employee_scope_blocks_only_assigned_staff(): void
    {
        $listId = DB::table('holiday_lists')->insertGetId([
            'name' => 'عطلة نطاق',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Holiday::create([
            'holiday_list_id' => $listId,
            'named' => 'يوم خاص',
            'recurrence_type' => Holiday::RECURRENCE_ONCE,
            'holiday_date' => self::WORK_DAY,
            'is_active' => true,
            'is_paid' => true,
        ]);

        $customizationId = DB::table('holy_day_list_customizes')->insertGetId([
            'use_rules' => 2,
            'holiday_list_id' => $listId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assigned = $this->employee();
        $other = $this->employee();

        DB::table('holy_day_list_customize_employees')->insert([
            'holyday_customizes_id' => $customizationId,
            'employee_id' => $assigned->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blocked = $this->stampIn($assigned, self::WORK_DAY.' 08:00:00');
        $this->assertFalse($blocked->accepted);
        $this->assertSame('public_holiday', $blocked->code);

        $allowed = $this->stampIn($other, self::WORK_DAY.' 08:00:00');
        $this->assertTrue($allowed->accepted);
    }

    public function test_login_window_rejects_check_in_before_window(): void
    {
        $shiftId = $this->shift(
            loginStart: '07:30:00',
            loginEnd: '09:00:00',
        );
        $employee = $this->employee($shiftId);

        $tooEarly = $this->stampIn($employee, self::WORK_DAY.' 07:00:00');
        $this->assertFalse($tooEarly->accepted);
        $this->assertSame('outside_login_window', $tooEarly->code);

        $ok = $this->stampIn($employee, self::WORK_DAY.' 08:00:00');
        $this->assertTrue($ok->accepted);
    }

    public function test_attendance_rule_overrides_grace_minutes(): void
    {
        AttendanceSetting::query()->update(['late_grace_minutes' => 30]);

        $shiftId = $this->shift(grace: 30);
        DB::table('attendance_rules')->insert([
            'name' => 'سماحية صفر',
            'status' => 'active',
            'shift_id' => $shiftId,
            'condition' => 'late_grace_minutes=0',
            'formula' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employee = $this->employee($shiftId);
        $this->stampIn($employee, self::WORK_DAY.' 08:10:00');
        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(AttendanceDays::STATUS_LATE, $day->status);
        $this->assertSame(10, (int) $day->late_minutes);
    }

    public function test_allow_second_shift_opens_new_check_in_after_complete_day(): void
    {
        AttendanceSetting::query()->update(['allow_second_shift' => true]);

        $employee = $this->employee();
        $this->assertTrue($this->stampIn($employee, self::WORK_DAY.' 08:00:00')->accepted);
        $this->assertTrue($this->stampOut($employee, self::WORK_DAY.' 16:00:00')->accepted);

        $next = app(AttendanceStampService::class)->nextPunchTypeFor($employee);
        $this->assertSame(AttendancePunch::TYPE_IN, $next);

        $second = $this->stampIn($employee, self::WORK_DAY.' 17:00:00');
        $this->assertTrue($second->accepted);

        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('17:00:00', $day->check_in_time);
        $this->assertNull($day->check_out_time);
        $this->assertStringContainsString('الوردية الأولى', (string) $day->notes);
    }

    /*
     * خارج مسار المسح عمداً: أجهزة البصمة، قوالب الطباعة، بداية السنة المالية
     * (رصيد الإجازات). طريقة الانصراف «خط السير» مربوطة عند وجود زيارات.
     */
}
