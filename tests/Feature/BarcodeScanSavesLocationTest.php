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
use Carbon\Carbon;
use Tests\Support\BuildsAttendanceStampSchema;
use Tests\TestCase;

/**
 * مسحُ البطاقة يحفظ الإحداثيات في يوم الحضور والختم، ويُعرض في السجلات.
 */
class BarcodeScanSavesLocationTest extends TestCase
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
            'default_shift_start' => '08:00:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-20 08:05:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(): Employee
    {
        return Employee::create([
            'first_name' => 'نورة',
            'email' => 'loc'.uniqid().'@example.test',
            'status' => EmploymentStatus::ACTIVE->value,
            'hire_date' => '2026-01-01',
            'barcode' => 'LOC_'.uniqid(),
            'barcode_enabled' => true,
            'attendance_channel' => 'both',
        ]);
    }

    public function test_scan_persists_client_coordinates_on_day_and_punch(): void
    {
        $employee = $this->employee();
        $kiosk = AttendanceKiosk::query()->first()
            ?? AttendanceKiosk::create([
                'name' => 'جهاز',
                'pairing_code' => 'LOC'.substr(uniqid(), -4),
                'is_active' => true,
            ]);

        $stamp = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            latitude: 24.7135517,
            longitude: 46.6752957,
            accuracyMeters: 12,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
            kiosk: $kiosk,
        ));

        $this->assertTrue($stamp->accepted, $stamp->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(24.7135517, (float) $day->checkin_latitude, 0.0001);
        $this->assertEqualsWithDelta(46.6752957, (float) $day->checkin_longitude, 0.0001);

        $punch = AttendancePunch::where('employee_id', $employee->id)->accepted()->firstOrFail();
        $this->assertEqualsWithDelta(24.7135517, (float) $punch->latitude, 0.0001);
        $this->assertEqualsWithDelta(46.6752957, (float) $punch->longitude, 0.0001);
        $this->assertStringContainsString('24.713552', $punch->locationDisplay());
        $this->assertNotNull($punch->mapsUrl());
    }

    public function test_without_gps_kiosk_geofence_coordinates_are_saved(): void
    {
        $fence = AttendanceGeofence::create([
            'name' => 'مقر الرياض',
            'latitude' => 24.7000000,
            'longitude' => 46.6800000,
            'radius_meters' => 200,
            'is_active' => true,
            'require_geo' => false,
        ]);

        $kiosk = AttendanceKiosk::create([
            'name' => 'جهاز الباب',
            'pairing_code' => 'GEO'.substr(uniqid(), -4),
            'geofence_id' => $fence->id,
            'is_active' => true,
        ]);

        $employee = $this->employee();

        $stamp = app(AttendanceStampService::class)->stamp($employee, new StampContext(
            punchType: AttendancePunch::TYPE_IN,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_BARCODE,
            latitude: null,
            longitude: null,
            ipAddress: '127.0.0.1',
            rawBarcode: $employee->barcode,
            kiosk: $kiosk,
        ));

        $this->assertTrue($stamp->accepted, $stamp->message);

        $day = AttendanceDays::where('employee_id', $employee->id)->firstOrFail();
        $this->assertEqualsWithDelta(24.7, (float) $day->checkin_latitude, 0.0001);
        $this->assertEqualsWithDelta(46.68, (float) $day->checkin_longitude, 0.0001);

        $punch = AttendancePunch::where('employee_id', $employee->id)->accepted()->firstOrFail();
        $this->assertEqualsWithDelta(24.7, (float) $punch->latitude, 0.0001);
        $this->assertStringContainsString('مقر الرياض', $punch->locationDisplay());
    }
}
