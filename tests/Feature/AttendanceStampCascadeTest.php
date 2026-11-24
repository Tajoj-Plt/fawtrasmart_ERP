<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendanceGeofence;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use App\Services\Attendance\StampResult;
use Carbon\Carbon;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * التسلسلُ الحاكم لتسجيل الحضور، حلقةً حلقة.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن كلَّ حلقةٍ تردّ ما يخصّها **ولا تتخطّاها التي بعدها**: موظفٌ موقوفٌ
 * يُردّ عند ملفّه فلا يُسأل عن موقعه أصلاً، ومن هو خارج النطاق يُردّ عند
 * الموقع فلا يُكتب له صفُّ يوم. وأنّ كلَّ محاولةٍ — مقبولةً كانت أو
 * مردودة — تترك ختماً في `attendance_punches`.
 *
 * والحالتان اللتان يسهل أن تُنسيا: أن الانصراف يمرّ بالفحص الجغرافي
 * كالحضور (كان لا يمرّ بشيءٍ إطلاقاً)، وأن الإحداثيات تُكتب في صفّ اليوم
 * نفسِه (كانت تُمرَّر إلى مُسجِّل الصور ثم تُهمَل).
 *
 * الجداولُ تُبنى عبر [BuildsAttendanceStampSchema] لأن هجراتِ المشروع
 * تحوي صيغاً خاصّةً بـMySQL لا يفهمها sqlite الذي يفرضه `phpunit.xml`.
 */
class AttendanceStampCascadeTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    /** المقر: خطُّ عرضٍ وطولٍ ونصفُ قطرٍ مئةُ متر. */
    private const HQ_LAT = 24.7135517;

    private const HQ_LNG = 46.6752957;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 120,
            'work_hours_per_day' => 8,
        ]);

        // الأربعاء ٢٠٢٦-٠٩-١٦، الساعة ٨:٠٠ — بداية الوردية الافتراضية
        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ════════════════════════════════════════════════════
    // الحلقة ١ — ملفُّ الموظف
    // ════════════════════════════════════════════════════

    public function test_a_suspended_employee_is_rejected_at_the_employee_ring(): void
    {
        $employee = $this->employee(['status' => EmploymentStatus::SUSPENDED->value]);
        $this->geofence();

        $result = $this->stamp($employee, AttendancePunch::TYPE_IN, self::HQ_LAT, self::HQ_LNG);

        $this->assertFalse($result->accepted);
        $this->assertSame(StampResult::STAGE_EMPLOYEE, $result->stage);
        $this->assertSame('employee_inactive', $result->code);

        // لم يُكتب صفُّ يومٍ: الحلقةُ الرابعة لم تُبلَغ أصلاً
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_a_disabled_badge_is_rejected_but_an_unset_flag_is_not(): void
    {
        $disabled = $this->employee(['barcode_enabled' => false]);
        $result = $this->stamp($disabled, AttendancePunch::TYPE_IN, null, null, AttendancePunch::METHOD_BARCODE);

        $this->assertFalse($result->accepted);
        $this->assertSame('barcode_disabled', $result->code);

        /*
            وغيابُ القيمة ليس إيقافاً. الحالةُ الواقعية أن يُحمَّل الموظفُ
            باستعلامٍ لا يختار العمود — فيُقرأ `null` — و`!null` يصدق،
            فتُوقَف بطاقةُ كلِّ موظفٍ في المنشأة. الإيقافُ قرارٌ صريح.
        */
        $unset = $this->employee();
        $unset->offsetUnset('barcode_enabled');
        $this->assertNull($unset->barcode_enabled);

        $this->assertTrue(
            $this->stamp($unset, AttendancePunch::TYPE_IN, null, null, AttendancePunch::METHOD_BARCODE)->accepted
        );
    }

    public function test_a_barcode_claim_without_a_barcode_is_refused(): void
    {
        $employee = $this->employee();

        /*
            جوهرُ الأمر: `method` كان يصل من العميل حرفاً يُقبل كما هو،
            ولا يُرسَل باركودٌ مع التسجيل إطلاقاً. فكان أيُّ طلبٍ يقول
            «بالباركود» يُسجَّل وسيلةً موثَّقةً وهو لم يُمسَح شيء — والوسيلةُ
            تدخل سجلَّ التدقيق بوصفها دليلاً.
        */
        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: null,
        ));

        $this->assertFalse($result->accepted);
        $this->assertSame('barcode_not_supplied', $result->code);
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_a_barcode_belonging_to_someone_else_is_refused(): void
    {
        $employee = $this->employee();
        $colleague = $this->employee();

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $colleague->barcode,
        ));

        // ولا يُخفَّض صامتاً إلى «يدوي»: التخفيضُ يُمرِّر تسجيلاً لم يأذن به أحد
        $this->assertFalse($result->accepted);
        $this->assertSame('barcode_mismatch', $result->code);
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_a_matching_barcode_is_accepted_and_recorded_as_the_method(): void
    {
        $employee = $this->employee();

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
        ));

        $this->assertTrue($result->accepted, $result->message);

        $punch = AttendancePunch::accepted()->firstOrFail();
        $this->assertSame(AttendancePunch::METHOD_BARCODE, $punch->method);
        $this->assertSame($employee->barcode, $punch->raw_barcode);
    }

    public function test_a_manual_entry_carries_its_written_reason(): void
    {
        $employee = $this->employee();

        // المسارُ اليدوي لا يشترط باركوداً — لكنه يُوسَم بما يبرّره
        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_MANUAL,
            ipAddress: '127.0.0.1',
            entryNote: 'نسي الموظف بطاقته',
        ));

        $this->assertTrue($result->accepted, $result->message);

        $punch = AttendancePunch::accepted()->firstOrFail();
        $this->assertSame(AttendancePunch::METHOD_MANUAL, $punch->method);
        $this->assertSame('نسي الموظف بطاقته', $punch->entry_note);
    }

    public function test_a_field_only_employee_cannot_stamp_on_a_branch_device(): void
    {
        $employee = $this->employee(['attendance_channel' => 'field']);

        $result = $this->stamp($employee, AttendancePunch::TYPE_IN, self::HQ_LAT, self::HQ_LNG);

        $this->assertFalse($result->accepted);
        $this->assertSame('channel_not_allowed', $result->code);
    }

    // ════════════════════════════════════════════════════
    // اشتقاقُ نوع الختم — ما يجعل المسح مسحاً لا إدخالاً يدوياً
    // ════════════════════════════════════════════════════

    public function test_the_next_punch_type_is_derived_from_the_day_record(): void
    {
        $employee = $this->employee();
        $service = app(AttendanceStampService::class);

        /*
            الجهازُ لا يسأل المشغّلَ «أحضورٌ أم انصراف؟» — جوابُه في سجل
            اليوم. وسؤالُه كان يجعل الوسيلةَ قراراً بشرياً بعد أن كانت
            واقعة، ويفتح بابَ تسجيل انصرافٍ مكان حضور.
        */
        $this->assertSame(AttendancePunch::TYPE_IN, $service->nextPunchTypeFor($employee));

        $this->stamp($employee, AttendancePunch::TYPE_IN);
        $this->assertSame(AttendancePunch::TYPE_OUT, $service->nextPunchTypeFor($employee));

        $this->stamp($employee, AttendancePunch::TYPE_OUT);

        // لا ختمَ ثالثاً — والشاشةُ تقول «اليوم مكتمل» بدل أن تُحاول وتُردّ
        $this->assertNull($service->nextPunchTypeFor($employee));
    }

    // ════════════════════════════════════════════════════
    // الحلقة ٣ — الموقع الجغرافي
    // ════════════════════════════════════════════════════

    public function test_a_stamp_from_outside_the_circle_is_rejected(): void
    {
        $employee = $this->employee();
        $this->geofence(['radius_meters' => 100]);

        // نحو ١.٢ كم شمال المقر
        $result = $this->stampField($employee, AttendancePunch::TYPE_IN, self::HQ_LAT + 0.011, self::HQ_LNG);

        $this->assertFalse($result->accepted);
        $this->assertSame(StampResult::STAGE_LOCATION, $result->stage);
        $this->assertSame('outside_geofence', $result->code);
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_a_stamp_from_inside_the_circle_is_accepted_and_stores_the_distance(): void
    {
        $employee = $this->employee();
        $this->geofence(['radius_meters' => 100]);

        // نحو ٥٥ متراً — داخل المئة
        $result = $this->stampField($employee, AttendancePunch::TYPE_IN, self::HQ_LAT + 0.0005, self::HQ_LNG);

        $this->assertTrue($result->accepted, $result->message);

        $day = AttendanceDays::first();
        $this->assertNotNull($day->check_in_time);
        $this->assertLessThanOrEqual(100, $day->check_in_distance_meters);
        $this->assertSame('field', $day->check_in_channel);

        // الإحداثياتُ في صفّ اليوم نفسِه — لا في سياق الصور وحده
        $this->assertEqualsWithDelta(self::HQ_LAT + 0.0005, (float) $day->checkin_latitude, 0.0001);
    }

    public function test_a_field_stamp_with_a_useless_accuracy_reading_is_rejected(): void
    {
        $employee = $this->employee();
        $this->geofence(['radius_meters' => 100, 'max_accuracy_meters' => 50]);

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_FIELD,
            method: AttendancePunch::METHOD_GEO,
            latitude: self::HQ_LAT,
            longitude: self::HQ_LNG,
            accuracyMeters: 2000,
        ));

        // «داخل النطاق ± ٢ كم» لم تُثبت شيئاً — تُردّ بطلب قراءةٍ أدقّ
        $this->assertFalse($result->accepted);
        $this->assertSame('location_inaccurate', $result->code);
    }

    public function test_a_branch_device_on_a_foreign_network_is_rejected(): void
    {
        $employee = $this->employee();
        $geofence = $this->geofence([
            'require_geo' => false,
            'require_network' => true,
            'allowed_ip_ranges' => ['192.168.10.0/24'],
        ]);
        $kiosk = AttendanceKiosk::create(['name' => 'تابلت الاستقبال', 'geofence_id' => $geofence->id]);

        $rejected = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '10.0.0.5',
            kiosk: $kiosk,
            rawBarcode: $employee->barcode,
        ));

        $this->assertFalse($rejected->accepted);
        $this->assertSame('network_not_allowed', $rejected->code);

        // ومن داخل شبكة المقر يمرّ — بلا إحداثياتٍ أصلاً، فالجهازُ ثابت
        $accepted = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '192.168.10.44',
            kiosk: $kiosk,
            rawBarcode: $employee->barcode,
        ));

        $this->assertTrue($accepted->accepted, $accepted->message);
    }

    // ════════════════════════════════════════════════════
    // الحلقة ٤ — سجلُّ اليوم الموحَّد
    // ════════════════════════════════════════════════════

    public function test_the_same_event_cannot_be_stamped_twice_in_one_day(): void
    {
        $employee = $this->employee();

        $this->assertTrue($this->stamp($employee, AttendancePunch::TYPE_IN)->accepted);

        $again = $this->stamp($employee, AttendancePunch::TYPE_IN);

        $this->assertFalse($again->accepted);
        $this->assertSame(StampResult::STAGE_DAY, $again->stage);
        $this->assertSame('already_checked_in', $again->code);

        // صفٌّ واحدٌ لليوم لا صفّان
        $this->assertDatabaseCount('attendance_days', 1);
    }

    public function test_there_is_no_checkout_without_a_checkin(): void
    {
        $employee = $this->employee();

        $result = $this->stamp($employee, AttendancePunch::TYPE_OUT);

        $this->assertFalse($result->accepted);
        $this->assertSame('no_check_in', $result->code);
    }

    public function test_checkout_passes_the_geofence_ring_exactly_as_checkin_does(): void
    {
        $employee = $this->employee();
        $this->geofence(['radius_meters' => 100]);

        $this->assertTrue(
            $this->stampField($employee, AttendancePunch::TYPE_IN, self::HQ_LAT, self::HQ_LNG)->accepted
        );

        Carbon::setTestNow(Carbon::parse('2026-09-16 16:00:00'));

        // من البيت، بعد ١.٢ كم: كان هذا يمرّ بلا أيِّ فحص
        $result = $this->stampField($employee, AttendancePunch::TYPE_OUT, self::HQ_LAT + 0.011, self::HQ_LNG);

        $this->assertFalse($result->accepted);
        $this->assertSame(StampResult::STAGE_LOCATION, $result->stage);
        $this->assertNull(AttendanceDays::first()->check_out_time);
    }

    // ════════════════════════════════════════════════════
    // الحلقة ٦ — سجلُّ التدقيق
    // ════════════════════════════════════════════════════

    public function test_every_attempt_leaves_a_punch_accepted_or_rejected(): void
    {
        $employee = $this->employee();
        $this->geofence(['radius_meters' => 100]);

        $this->stampField($employee, AttendancePunch::TYPE_IN, self::HQ_LAT + 0.011, self::HQ_LNG); // مردود
        $this->stampField($employee, AttendancePunch::TYPE_IN, self::HQ_LAT, self::HQ_LNG);        // مقبول

        $this->assertSame(2, AttendancePunch::count());

        $rejected = AttendancePunch::rejected()->first();
        $this->assertSame(StampResult::STAGE_LOCATION, $rejected->stage);
        $this->assertSame('outside_geofence', $rejected->rejection_code);
        $this->assertGreaterThan(100, $rejected->distance_meters);

        $accepted = AttendancePunch::accepted()->first();
        $this->assertNotNull($accepted->attendance_day_id);
        $this->assertSame('08:00:00', $accepted->expected_check_in);
    }

    public function test_a_punch_can_never_be_edited_or_deleted(): void
    {
        $employee = $this->employee();
        $this->stamp($employee, AttendancePunch::TYPE_IN);

        $punch = AttendancePunch::first();

        // التصحيحُ يمرّ بـAttendanceCorrectionRequest، لا بتعديل الأثر
        $this->expectException(\RuntimeException::class);
        $punch->update(['punch_type' => AttendancePunch::TYPE_OUT]);
    }

    public function test_deleting_a_punch_is_refused(): void
    {
        $employee = $this->employee();
        $this->stamp($employee, AttendancePunch::TYPE_IN);

        $this->expectException(\RuntimeException::class);
        AttendancePunch::first()->delete();
    }

    // ════════════════════════════════════════════════════
    // مساعدات
    // ════════════════════════════════════════════════════

    private function employee(array $overrides = []): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create(array_merge([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "employee{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => "EMP_{$sequence}",
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ], $overrides));
    }

    private function geofence(array $overrides = []): AttendanceGeofence
    {
        return AttendanceGeofence::create(array_merge([
            'name' => 'المقر الرئيسي',
            'latitude' => self::HQ_LAT,
            'longitude' => self::HQ_LNG,
            'radius_meters' => 100,
            'max_accuracy_meters' => 100,
            'require_geo' => true,
            'require_network' => false,
            'is_active' => true,
        ], $overrides));
    }

    private function stamp(
        Employee $employee,
        string $punchType,
        ?float $latitude = null,
        ?float $longitude = null,
        string $method = AttendancePunch::METHOD_BARCODE,
    ): StampResult {
        return app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: $punchType,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: $method,
            latitude: $latitude,
            longitude: $longitude,
            ipAddress: '127.0.0.1',
            // الوسيلةُ تُثبَت: ختمٌ بالباركود يحمل باركودَ صاحبه
            rawBarcode: $method === AttendancePunch::METHOD_BARCODE ? $employee->barcode : null,
        ));
    }

    private function stampField(Employee $employee, string $punchType, float $latitude, float $longitude): StampResult
    {
        return app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: $punchType,
            channel: AttendancePunch::CHANNEL_FIELD,
            method: AttendancePunch::METHOD_GEO,
            latitude: $latitude,
            longitude: $longitude,
            accuracyMeters: 12,
            ipAddress: '127.0.0.1',
        ));
    }
}
