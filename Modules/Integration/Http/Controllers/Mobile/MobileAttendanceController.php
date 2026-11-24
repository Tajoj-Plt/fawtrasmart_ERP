<?php

namespace Modules\Integration\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AttendancePunch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Attendance\FieldAttendanceController;

/**
 * «سجّل حضوري» لتطبيق الجوال — **شاشة الويب نفسها** (`FieldAttendanceController`) بلا نسخةٍ ثانية من قواعدها.
 *
 * طريقةُ التسجيل (فرع/ميداني/كلاهما)، ومكانُ التسجيل ونطاقُه، ونافذةُ الحضور، والإجازة والعطلة،
 * والاستراحة والدوام المرن، وكلُّ أسباب الرفض — في `AttendanceStampService` وما حولها. فيُستدعى هنا
 * المتحكّمُ كما تستدعيه الشاشة: `state()` تقرأ ما يمرّره `index()` إلى القالب، والختمُ والاستراحةُ
 * ورمزُ المكتب تُعاد ردودُها كما هي (`success`, `message`, `code`, `stage`, `data`).
 *
 * الموظفُ دائماً صاحبُ الحساب (`users.employee_id`) — لا `employee_id` في أيّ طلب. والصلاحيةُ وميزةُ
 * الباقة ومفتاحُ التطبيق على المسار (`routes/mobile_api.php`) كمسار الويب.
 */
class MobileAttendanceController extends Controller
{
    /** كلُّ ما ترسمه شاشة «سجّل حضوري» — متغيّراتُ `hr::attendance.field.index` نفسُها */
    public function state(Request $request): JsonResponse
    {
        $d = app(FieldAttendanceController::class)->index($request)->getData();

        $employee = $d['employee'];
        $attendance = $d['attendance'];
        $day = $d['day'];
        $config = $day['config'] ?? [];
        $activeDate = $d['activeDate'];
        $time = fn ($value) => $value ? Carbon::parse($value)->format('H:i') : null;

        return response()->json(['success' => true, 'data' => [
            'serverTime' => Carbon::now()->format('H:i:s'),
            'date' => Carbon::today()->format('Y-m-d'),
            // يومُ السجل المفتوح — يخالف التقويم في الورديات الليلية
            'activeDate' => $activeDate->format('Y-m-d'),
            'overnight' => ! $activeDate->isSameDay(Carbon::today()),
            'employee' => ['name' => $employee->full_name, 'department' => $employee->department->name ?? null],
            // branch: يسجّل على جهاز الفرع (لا زرّ موقع) — field: بالموقع — both: الاثنان
            'channel' => $d['channel'],
            'mode' => $d['initialMode'],
            'checkInBlock' => $d['checkInBlock'],
            'window' => [
                'start' => $d['window']['start']?->format('H:i'),
                'end' => $d['window']['end']?->format('H:i'),
            ],
            'shift' => [
                'expectedIn' => $day['expected_check_in']?->format('H:i'),
                'expectedOut' => $day['expected_check_out']?->format('H:i'),
                'graceMinutes' => (int) ($config['late_grace_minutes'] ?? 0),
                'flexible' => ($config['work_mode'] ?? 'shift') === 'flexible',
                'workHours' => isset($config['work_hours_per_day']) ? (float) $config['work_hours_per_day'] : null,
                'isHoliday' => (bool) $day['is_holiday'],
                'holidayName' => $day['holiday_name'] ?? null,
                'isWeeklyOff' => (bool) $day['is_weekly_off'],
                'onLeave' => $day['leave'] !== null,
            ],
            'fence' => $this->fence($d['fencePayload']),
            'branchFence' => $this->fence($d['branchFencePayload']),
            'officeScan' => (bool) $d['officeScan'],
            'kiosks' => collect($d['myKiosks'])->map(fn ($k) => [
                'name' => $k->name,
                'place' => $k->geofence->name ?? null,
                'branch' => $k->branch->name ?? null,
            ])->values(),
            // الصور لا ترسلها شاشة الويب؛ متى اشتُرطت يردّ الخادمُ الختمَ بـ`photos_missing`
            'photosRequired' => (bool) ($config['photos_required'] ?? false),
            'today' => [
                'checkIn' => $time($attendance?->check_in_time),
                'checkOut' => $time($attendance?->check_out_time),
                'status' => $attendance?->status,
                'lateMinutes' => (int) ($attendance?->late_minutes ?? 0),
                'earlyLeaveMinutes' => (int) ($attendance?->early_leave_minutes ?? 0),
                'overtimeMinutes' => (int) ($attendance?->overtime_minutes ?? 0),
                'workingHours' => $attendance?->working_hours,
                'corrected' => (bool) ($attendance?->manual_override ?? false),
                'onBreak' => (bool) $attendance?->break_started_at,
                'breakStartedAt' => $time($attendance?->break_started_at),
                'breakMinutes' => (int) ($attendance?->break_minutes ?? 0),
                'breakAllowedMinutes' => (int) ($config['break_allowed_minutes'] ?? 0),
            ],
            'recentPunches' => collect($d['recentPunches'])->map(fn (AttendancePunch $p) => [
                'id' => (int) $p->id,
                'type' => $p->punch_type,
                'at' => optional($p->punched_at)->toDateTimeString(),
                'accepted' => $p->result === 'accepted',
                'reason' => $p->result === 'accepted' ? null : ($p->rejectionTitle() ?: $p->rejection_reason),
                'place' => $p->kiosk->name ?? $p->geofence->name ?? null,
                'channel' => $p->channel,
            ])->values(),
        ]]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        return app(FieldAttendanceController::class)->checkIn($request);
    }

    public function checkOut(Request $request): JsonResponse
    {
        return app(FieldAttendanceController::class)->checkOut($request);
    }

    /** `action`: start | end */
    public function toggleBreak(Request $request): JsonResponse
    {
        return app(FieldAttendanceController::class)->toggleBreak($request);
    }

    /** «رمز المكتب» الممسوح بالجوال — حضورٌ أو انصرافٌ بحسب حالة اليوم */
    public function officeScan(Request $request): JsonResponse
    {
        return app(FieldAttendanceController::class)->officeScan($request);
    }

    /** @param  array<string, mixed>|null  $payload */
    private function fence(?array $payload): ?array
    {
        if (! $payload) {
            return null;
        }

        return [
            'name' => $payload['name'],
            'latitude' => $payload['latitude'],
            'longitude' => $payload['longitude'],
            'radiusMeters' => $payload['radius_meters'] !== null ? (int) $payload['radius_meters'] : null,
            'maxAccuracyMeters' => $payload['max_accuracy_meters'] !== null ? (int) $payload['max_accuracy_meters'] : null,
            'requiresGeo' => (bool) $payload['requires_geo'],
        ];
    }
}
