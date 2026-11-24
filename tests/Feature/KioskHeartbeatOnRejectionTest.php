<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Attendance\BarcodeAttendanceController;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * نبضةُ جهاز الفرع تُسجَّل ولو رُدّت المسحة.
 *
 * **ما كان**
 *
 * `touchHeartbeat()` تقع في آخر المسار المقبول وحدَه — بعد أن تكون كلُّ
 * حلقات الردّ قد عادت مبكراً. وجهازٌ تُردّ عليه كلُّ مسحة (نطاقٌ مضبوطٌ
 * على إحداثياتٍ خاطئة، أو شبكةٌ غيرُ مصرَّحٍ بها، أو بطاقاتٌ لم تُفعَّل)
 * **حيٌّ ومعطوب** — وهي الحالُ التي تُفتح شاشةُ «سجل جلسات الحضور»
 * لأجلها بالذات.
 *
 * فكان أشدُّ الأجهزة حاجةً إلى الانتباه هو أقلَّها إظهاراً لعلامات الحياة.
 */
class KioskHeartbeatOnRejectionTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        AttendanceSetting::create([
            'start_month' => 1, 'start_day' => 1,
            'late_grace_minutes' => 15, 'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kiosk(): AttendanceKiosk
    {
        return AttendanceKiosk::create([
            'name' => 'جهاز الفرع',
            'code' => 'K-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function employee(): Employee
    {
        static $sequence = 0;
        $sequence++;

        return Employee::create([
            'first_name' => 'موظف',
            'middle_name' => (string) $sequence,
            'email' => "kiosk{$sequence}@example.test",
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'KCARD_'.$sequence.'_'.uniqid(),
            'attendance_channel' => 'both',
        ]);
    }

    /** مسحةٌ مقبولة تُحدِّث النبضة. */
    public function test_an_accepted_stamp_updates_the_heartbeat(): void
    {
        $kiosk = $this->kiosk();
        $employee = $this->employee();

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
            kiosk: $kiosk,
        ));

        $this->assertTrue($result->accepted, $result->message);
        $this->assertNotNull($kiosk->fresh()->last_seen_at);
    }

    /**
     * ومسحةٌ مردودة تُحدِّثها كذلك.
     *
     * بطاقةٌ لا تخصّ صاحبها تُردّ في الحلقة الأولى — قبل أيّ شيء — وكانت
     * النبضةُ بعدها بمراحل.
     */
    public function test_a_rejected_stamp_still_proves_the_device_is_alive(): void
    {
        $kiosk = $this->kiosk();
        $employee = $this->employee();
        $stranger = $this->employee();

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));

        $result = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $stranger->barcode,   // بطاقةُ غيرِه
            kiosk: $kiosk,
        ));

        $this->assertFalse($result->accepted);
        $this->assertNotNull(
            $kiosk->fresh()->last_seen_at,
            'الجهازُ الذي يردّ كلَّ مسحةٍ حيٌّ ومعطوب — وهي الحالُ التي تُفتح الشاشةُ لأجلها'
        );
    }

    /**
     * واستطلاعُ الحالة الدوريّ نبضةٌ كذلك.
     *
     * الشاشةُ تستدعي `barcode.stats` كلَّ عشرين ثانيةً لتُضيء مؤشّرَ
     * الاتصال، وكان المسارُ لا يُحدِّث `last_seen_at`. فجهازٌ شاشتُه
     * مفتوحةٌ وشبكتُه تعمل يُقرأ «صامتاً» في سجل جلسات الحضور بعد
     * خمسٍ وأربعين دقيقةً بلا مسح — وهو بخير.
     *
     * والاستدعاءُ للمتحكّم مباشرةً: المسارُ خلف حراسة الوحدة وباقتها،
     * والمقيسُ هنا سلوكُ الدالّة لا سلسلةُ الوسائط.
     */
    public function test_the_liveness_poll_refreshes_the_heartbeat(): void
    {
        $kiosk = $this->kiosk();

        // جهازٌ نبضَ آخرَ مرّةٍ قبل ساعتين — «صامت» بمقياس الشاشة
        $kiosk->forceFill(['last_seen_at' => now()->subHours(2)])->saveQuietly();
        $stale = $kiosk->fresh()->last_seen_at;

        $this->pollStats($kiosk->id);

        $this->assertTrue(
            $kiosk->fresh()->last_seen_at->gt($stale),
            'الاستطلاعُ الدوريّ يجب أن يُثبت أن الجهاز حيّ'
        );
    }

    /** وبلا اقتران لا نبضةَ تُنسَب إلى جهاز. */
    public function test_an_unpaired_poll_touches_nothing(): void
    {
        $kiosk = $this->kiosk();
        $kiosk->forceFill(['last_seen_at' => now()->subHours(2)])->saveQuietly();
        $stale = $kiosk->fresh()->last_seen_at;

        $this->pollStats(null);

        $this->assertEquals($stale, $kiosk->fresh()->last_seen_at);
    }

    /** استدعاءُ `dashboardStats` بجلسةٍ تحمل اقتران الجهاز (أو بلا اقتران). */
    private function pollStats(?int $kioskId): void
    {
        $request = Request::create('/hr/barcode/stats', 'GET');

        $session = app('session.store');

        if ($kioskId !== null) {
            \App\Models\AttendanceKiosk::whereKey($kioskId)->update(['device_token_hash' => hash('sha256', 'test-device-token')]);
            $session->put('attendance_kiosk', $kioskId.'|test-device-token');
        }

        $request->setLaravelSession($session);

        (new class extends BarcodeAttendanceController
        {
            public function __construct() {}
        })->dashboardStats($request);
    }

    /** والختمُ المردود يحمل معرّف الجهاز، فيُعدّ في نشاطه. */
    public function test_the_rejected_punch_carries_the_kiosk_id(): void
    {
        $kiosk = $this->kiosk();
        $employee = $this->employee();
        $stranger = $this->employee();

        Carbon::setTestNow(Carbon::parse('2026-09-16 08:00:00'));

        app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            ipAddress: '127.0.0.1',
            rawBarcode: $stranger->barcode,
            kiosk: $kiosk,
        ));

        $punch = AttendancePunch::where('result', AttendancePunch::RESULT_REJECTED)->first();

        $this->assertNotNull($punch);
        $this->assertSame($kiosk->id, $punch->kiosk_id);
    }
}
