<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Attendance\BarcodeAttendanceController;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * شاشةُ جهاز الفرع ترفض المسح بلا اقتران.
 */
class KioskPairingRequiredTest extends TestCase
{
    use BuildsAttendanceStampSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceStampSchema();

        AttendanceSetting::create([
            'start_month' => 1,
            'start_day' => 1,
            'late_grace_minutes' => 15,
            'absent_after_minutes' => 240,
            'work_hours_per_day' => 8,
            'default_weekly_off_days' => [],
        ]);
    }

    private function controller(): BarcodeAttendanceController
    {
        return new class extends BarcodeAttendanceController
        {
            public function __construct() {}
        };
    }

    private function employee(): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'pair'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'PAIR_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);
    }

    public function test_scan_rejects_unpaired_browser(): void
    {
        $employee = $this->employee();

        $request = Request::create('/', 'POST', ['barcode' => $employee->barcode]);
        $session = app('session.store');
        $request->setLaravelSession($session);

        $response = $this->controller()->scan($request);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('kiosk_required', $body['code']);
        $this->assertFalse($body['success']);
        $this->assertDatabaseMissing('attendance_days', ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('attendance_punches', ['employee_id' => $employee->id]);
    }

    public function test_check_in_rejects_unpaired_browser(): void
    {
        $employee = $this->employee();

        $request = Request::create('/', 'POST', [
            'employee_id' => $employee->id,
            'barcode' => $employee->barcode,
        ]);
        $request->setLaravelSession(app('session.store'));

        $response = $this->controller()->checkIn($request);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('kiosk_required', $body['code']);
        $this->assertDatabaseMissing('attendance_days', ['employee_id' => $employee->id]);
    }

    public function test_paired_scan_is_accepted(): void
    {
        $employee = $this->employee();

        $request = $this->makePairedKioskRequest('/', 'POST', [
            'barcode' => $employee->barcode,
        ]);

        $response = $this->controller()->scan($request);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode(), $body['message'] ?? '');
        $this->assertTrue($body['success']);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $employee->id]);
    }

    public function test_pairing_once_covers_subsequent_scans_in_same_session(): void
    {
        $first = $this->employee();
        $second = $this->employee();

        $request = $this->makePairedKioskRequest('/', 'POST', [
            'barcode' => $first->barcode,
        ]);
        $session = $request->session();

        $firstResponse = $this->controller()->scan($request);
        $this->assertTrue(json_decode($firstResponse->getContent(), true)['success'] ?? false);

        // مسحة ثانية بنفس الجلسة — بلا إعادة رمز الاقتران
        $secondRequest = Request::create('/', 'POST', ['barcode' => $second->barcode]);
        $secondRequest->setLaravelSession($session);

        $secondResponse = $this->controller()->scan($secondRequest);
        $body = json_decode($secondResponse->getContent(), true);

        $this->assertSame(200, $secondResponse->getStatusCode(), $body['message'] ?? '');
        $this->assertTrue($body['success']);
        $this->assertSame(
            $session->get('attendance_kiosk'),
            $request->session()->get('attendance_kiosk')
        );
    }

    public function test_kiosk_cookie_restores_pairing_when_session_key_missing(): void
    {
        $employee = $this->employee();
        $kiosk = \App\Models\AttendanceKiosk::query()->first()
            ?? \App\Models\AttendanceKiosk::create([
                'name' => 'جهاز اختبار',
                'pairing_code' => 'COOKIE'.substr(uniqid(), -4),
                'is_active' => true,
            ]);

        $session = app('session.store');
        // بلا اقترانٍ في الجلسة — الاعتماد على الكوكي فقط («معرّف الجهاز|مفتاح الشاشة»)
        $kiosk->forceFill(['device_token_hash' => hash('sha256', 'cookie-token')])->save();
        $request = Request::create('/', 'POST', ['barcode' => $employee->barcode]);
        $request->setLaravelSession($session);
        $request->cookies->set('attendance_kiosk', $kiosk->id.'|cookie-token');

        $response = $this->controller()->scan($request);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode(), $body['message'] ?? '');
        $this->assertTrue($body['success']);
        $this->assertSame($kiosk->id.'|cookie-token', $session->get('attendance_kiosk'));
    }

    /** جوالُ موظفٍ عرف المعرّفَ ولا يحمل مفتاحَ الشاشة لا يصير جهازَ الفرع */
    public function test_kiosk_id_without_screen_token_is_refused(): void
    {
        $employee = $this->employee();
        $kiosk = \App\Models\AttendanceKiosk::query()->first()
            ?? \App\Models\AttendanceKiosk::create(['name' => 'جهاز اختبار', 'pairing_code' => 'LOCK'.substr(uniqid(), -4), 'is_active' => true]);
        $kiosk->forceFill(['device_token_hash' => hash('sha256', 'real-screen')])->save();

        $request = Request::create('/', 'POST', ['barcode' => $employee->barcode]);
        $request->setLaravelSession(app('session.store'));
        $request->cookies->set('attendance_kiosk', $kiosk->id.'|phone-guess');

        $response = $this->controller()->scan($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('kiosk_required', json_decode($response->getContent(), true)['code'] ?? null);
    }
}
