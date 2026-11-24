<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDays;
use App\Models\AttendanceGeofence;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\GeofenceGuard;
use App\Services\Attendance\OfficeCode;
use App\Services\Attendance\StampContext;
use App\Services\AttendanceProcessorService;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * المدخلُ الثاني: الموظفُ الميداني يسجّل من جواله داخل نطاق المرفق.
 *
 * الفرقُ عن جهاز الفرع فرقُ ظرفٍ لا فرقُ قواعد: `channel = field`
 * و`method = geo`، ثم التسلسلُ نفسُه في `AttendanceStampService`.
 *
 * والفرقُ الجوهريّ الآخر أن **الموظف يسجّل لنفسه**: لا `employee_id` في
 * الطلب إطلاقاً — يُشتقّ من المستخدم المسجَّل دخولُه. حقلٌ كهذا في جسم
 * الطلب يعني أن أيَّ موظفٍ يسجّل حضورَ زميلِه بتبديل رقم.
 */
class FieldAttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:attendance_registration.mobile');
    }

    /**
     * شاشةُ الموظف الميداني.
     */
    public function index(Request $request)
    {
        $employee = $this->currentEmployee($request);

        // اليومُ الذي تعرضه الشاشة هو اليومُ الذي سيُسنَد إليه الختمُ
        // التالي — لا تقويمُ اللحظة. وإلّا رأى الموظفُ الليليُّ زرَّ
        // «تسجيل حضور» ونوبتُه مفتوحةٌ منذ أمس.
        $activeDate = app(AttendanceStampService::class)
            ->resolveCheckoutDate($employee, Carbon::today());

        $day = app(AttendanceProcessorService::class)->describeDay($employee, $activeDate);
        $fence = app(GeofenceGuard::class)->resolve($employee, null, AttendancePunch::CHANNEL_FIELD);

        $attendance = AttendanceDays::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $activeDate)
            ->first();

        /*
            الشاشةُ تتبع طريقةَ تسجيل الموظف: موظفُ الفرع يسجّل ببطاقته على
            جهاز الفرع — فلا يُعرض له زرُّ تسجيلٍ بالموقع يُردّ حتماً
            («غير مسموح بهذه الطريقة»)، بل حالةُ يومه وأين يسجّل.
        */
        $channel = $employee->attendance_channel ?: 'branch';

        // نافذةُ الحضور نفسُها التي يفحصها الختم — تُعرض قبل الضغط لا بعد الردّ
        $window = app(AttendanceStampService::class)->checkInWindow($day, $activeDate);
        $now = Carbon::now();
        $checkInBlock = null;
        if (! $attendance?->check_in_time) {
            if ($attendance?->manual_override) {
                $checkInBlock = 'حضور اليوم ثُبِّت بطلب تصحيح معتمد.';
            } elseif ($day['leave']) {
                $checkInBlock = 'لديك إجازة معتمدة اليوم.';
            } elseif ($day['is_holiday'] || $day['is_weekly_off']) {
                $checkInBlock = 'اليوم عطلة — لا يُسجَّل فيه حضور.';
            } elseif ($window['start'] && $now->lt($window['start'])) {
                $checkInBlock = 'يُفتح تسجيل الحضور الساعة '.$window['start']->format('H:i').'.';
            } elseif ($window['end'] && $now->gt($window['end'])) {
                $checkInBlock = 'انتهى وقت تسجيل الحضور اليوم (حتى '.$window['end']->format('H:i').') — يُحتسب غياباً. إن كان لديك عذر فقدّم طلب تصحيح.';
            }
        }

        return view('hr::attendance.field.index', [
            'channel' => $channel,
            'window' => $window,
            'checkInBlock' => $checkInBlock,
            'branchFence' => $channel === 'field' ? null : app(GeofenceGuard::class)->resolve($employee),
            /*
                مكانُ المكتب بصيغةٍ يقرؤها السكربت — يفحصه زرُّ «امسح رمز
                المكتب» **قبل** أن يفتح الكاميرا، فمن كان خارج الموقع يُقال
                له ذلك ولا تُفتح له كاميرا بلا جدوى.
            */
            'branchFencePayload' => $channel === 'field'
                ? null
                : $this->fencePayload(app(GeofenceGuard::class)->resolve($employee)),
            // أجهزةُ الفرع المربوط بها (إعدادات الحضور ← الموظفون) — هي ما يُقبل عليه مسحُه
            // «رمز المكتب» متاحٌ إن فُعّل على جهازٍ يقبله (أجهزتُه المربوطة، وإلّا أيُّ جهاز)
            'officeScan' => $channel !== 'field' && (
                $employee->attendanceKiosks()->exists()
                    ? $employee->attendanceKiosks()->where('attendance_kiosks.is_active', true)->where('office_qr', '!=', 'off')->exists()
                    : AttendanceKiosk::active()->where('office_qr', '!=', 'off')->exists()
            ),
            'myKiosks' => $channel === 'field' ? collect() : $employee->attendanceKiosks()
                ->where('attendance_kiosks.is_active', true)
                ->with(['geofence:id,name', 'branch:id,name'])
                ->get(['attendance_kiosks.id', 'attendance_kiosks.name', 'attendance_kiosks.geofence_id', 'attendance_kiosks.branch_id']),
            'activeDate' => $activeDate,
            'employee' => $employee,
            'attendance' => $attendance,
            'day' => $day,
            'fence' => $fence,
            // الحمولةُ تُبنى هنا لا في القالب: `@json` لا تقبل مصفوفةً
            // حرفيةً متعددةَ الأسطر، ونفسُ الشكل يُقدَّم من `status()`.
            'fencePayload' => $this->fencePayload($fence),
            'initialMode' => $attendance?->check_out_time
                ? 'done'
                : ($attendance?->check_in_time ? 'out' : 'in'),
            // من أين سُجّلت كلُّ محاولة: الجهازُ أو المكان — لا «جهاز الفرع» مجرّداً
            'recentPunches' => AttendancePunch::with(['kiosk:id,name', 'geofence:id,name'])
                ->where('employee_id', $employee->id)
                ->orderByDesc('punched_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * حالةُ اليوم — تستعلم عنها الشاشةُ دورياً دون إعادة تحميل.
     */
    public function status(Request $request)
    {
        $employee = $this->currentEmployee($request);

        $activeDate = app(AttendanceStampService::class)
            ->resolveCheckoutDate($employee, Carbon::today());

        $attendance = AttendanceDays::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $activeDate)
            ->first();

        $day = app(AttendanceProcessorService::class)->describeDay($employee, $activeDate);
        $fence = app(GeofenceGuard::class)->resolve($employee, null, AttendancePunch::CHANNEL_FIELD);

        return response()->json([
            'success' => true,
            'server_time' => Carbon::now()->format('H:i:s'),
            'date' => Carbon::today()->format('Y-m-d'),
            // يومُ السجل المفتوح — يخالف التقويم في الورديات الليلية
            'active_date' => $activeDate->format('Y-m-d'),
            'is_overnight_shift' => ! $activeDate->isSameDay(Carbon::today()),
            'employee' => [
                'name' => $employee->full_name,
                'department' => $employee->department->name ?? null,
            ],
            'shift' => [
                'expected_check_in' => $day['expected_check_in']?->format('H:i'),
                'expected_check_out' => $day['expected_check_out']?->format('H:i'),
                'grace_minutes' => (int) ($day['config']['late_grace_minutes'] ?? 0),
                'is_holiday' => (bool) $day['is_holiday'],
                'is_weekly_off' => (bool) $day['is_weekly_off'],
                'on_leave' => $day['leave'] !== null,
            ],
            'geofence' => $this->fencePayload($fence),
            'today' => [
                'check_in_time' => $attendance?->check_in_time,
                'check_out_time' => $attendance?->check_out_time,
                'status' => $attendance?->status,
                'late_minutes' => (int) ($attendance?->late_minutes ?? 0),
            ],
        ]);
    }

    /**
     * النطاقُ بالشكل الذي ترسمه الشاشة — وبه تحسب المسافةَ محلياً بنفس
     * أرقام الخادم، فلا يقول المؤشّرُ «داخل النطاق» ثم يردّ الخادمُ الختم.
     *
     * @return array<string, mixed>|null
     */
    private function fencePayload(?AttendanceGeofence $fence): ?array
    {
        if (! $fence) {
            return null;
        }

        return [
            'name' => $fence->name,
            'latitude' => $fence->latitude !== null ? (float) $fence->latitude : null,
            'longitude' => $fence->longitude !== null ? (float) $fence->longitude : null,
            'radius_meters' => $fence->radius_meters,
            'max_accuracy_meters' => $fence->max_accuracy_meters,
            'requires_geo' => $fence->require_geo,
        ];
    }

    public function checkIn(Request $request)
    {
        return $this->stamp($request, AttendancePunch::TYPE_IN);
    }

    public function checkOut(Request $request)
    {
        return $this->stamp($request, AttendancePunch::TYPE_OUT);
    }

    /**
     * «رمز المكتب»: الموظفُ يمسح الرمزَ الظاهر على شاشة الجهاز أو الملصق في
     * المكتب بجواله، فيُسجَّل حضورُه — أو انصرافُه إن كان حاضراً.
     *
     * يُختم كتسجيلٍ على ذلك الجهاز (`CHANNEL_KIOSK`): طريقةُ تسجيل الموظف
     * وأجهزتُه المسموحة ونافذةُ الوقت تُفحص كما تُفحص بطاقتُه على الجهاز.
     * ورمزُ الشاشة يتغيّر كل 30 ثانية فيثبت الحضورَ أمامها — لا يُشترط
     * الموقع (GPS داخل المباني يخطئ). والمطبوعُ ثابت، فلا يُقبل إلا وموقعُ
     * الجوال داخل مكان الجهاز.
     */
    public function officeScan(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:200',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $employee = $this->currentEmployee($request);
        $resolved = app(OfficeCode::class)->resolve((string) $request->input('code'));

        if (! $resolved['ok']) {
            return response()->json(['success' => false, 'code' => 'office_code_invalid', 'stage' => 'kiosk', 'message' => $resolved['message']], 422);
        }

        $kiosk = $resolved['kiosk'];
        $printed = $resolved['mode'] === 'printed';

        if ($printed && ! $kiosk->geofence_id) {
            return response()->json(['success' => false, 'code' => 'office_code_invalid', 'stage' => 'location',
                'message' => 'الرمز المطبوع يحتاج مكاناً محدداً لجهاز «'.$kiosk->name.'» — راجع الموارد البشرية.'], 422);
        }

        if ($printed && ! ($request->filled('latitude') && $request->filled('longitude'))) {
            return response()->json(['success' => false, 'code' => 'location_required', 'stage' => 'location',
                'message' => 'فعّل الموقع في جوالك — الرمز المطبوع لا يُقبل إلا وموقعك داخل المكتب.'], 422);
        }

        // حضورٌ أم انصراف؟ من سجلّ اليوم الذي سيُسنَد إليه الختم (الوردية الليلية تُغلق يومَ أمس)
        $service = app(AttendanceStampService::class);
        $activeDate = $service->resolveCheckoutDate($employee, Carbon::today());
        $row = AttendanceDays::where('employee_id', $employee->id)->whereDate('attendance_date', $activeDate)->first();
        $punchType = ($row?->check_in_time && ! $row?->check_out_time) ? AttendancePunch::TYPE_OUT : AttendancePunch::TYPE_IN;

        $context = new StampContext(
            punchType: $punchType,
            channel: AttendancePunch::CHANNEL_KIOSK,
            method: AttendancePunch::METHOD_OFFICE,
            latitude: $printed ? (float) $request->input('latitude') : null,
            longitude: $printed ? (float) $request->input('longitude') : null,
            accuracyMeters: $printed && $request->filled('accuracy') ? (int) round((float) $request->input('accuracy')) : null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            kiosk: $kiosk,
            userId: Auth::id(),
        );

        try {
            $result = $service->stamp($employee, $context);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.FieldAttendance', 'officeScan');

            return response()->json(['success' => false, 'message' => 'تعذّر التسجيل — حاول مجدداً.'], 500);
        }

        return response()->json(
            $result->toResponsePayload() + ['punch_type' => $punchType, 'kiosk' => $kiosk->name],
            $result->accepted ? 200 : $result->httpStatus
        );
    }

    /**
     * بدءُ الاستراحة أو إنهاؤها — `action` = start | end.
     */
    public function toggleBreak(Request $request)
    {
        $request->validate([
            'action' => 'required|in:start,end',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $employee = $this->currentEmployee($request);

        $context = StampContext::fromRequest(
            $request,
            $request->input('action') === 'start' ? AttendancePunch::TYPE_BREAK_START : AttendancePunch::TYPE_BREAK_END,
            AttendancePunch::CHANNEL_FIELD,
            AttendancePunch::METHOD_GEO,
        );

        try {
            $result = app(AttendanceStampService::class)->toggleBreak($employee, $context);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.FieldAttendance', 'toggleBreak');

            return response()->json(['success' => false, 'message' => 'تعذّر تسجيل الاستراحة'], 500);
        }

        return response()->json($result->toResponsePayload(), $result->accepted ? 200 : $result->httpStatus);
    }

    private function stamp(Request $request, string $punchType)
    {
        /*
            الإحداثياتُ مطلوبةٌ هنا لا اختيارية: مدخلٌ ميدانيٌّ بلا موقعٍ
            هو زرُّ «سجّلني» بلا شرط. وحدُّ `accuracy` يُفحص في النطاق نفسِه.
        */
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'wifi_ssid' => 'nullable|string|max:64',
            'photos' => 'nullable|array|max:10',
            'photos.*.type_id' => 'nullable|integer|exists:attendance_photo_types,id',
            'photos.*.image' => 'nullable|string',
        ]);

        $employee = $this->currentEmployee($request);

        $context = StampContext::fromRequest(
            $request,
            $punchType,
            AttendancePunch::CHANNEL_FIELD,
            AttendancePunch::METHOD_GEO,
        );

        try {
            $result = app(AttendanceStampService::class)->stamp($employee, $context);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.FieldAttendance', $punchType === AttendancePunch::TYPE_IN ? 'checkIn' : 'checkOut');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل '.($punchType === AttendancePunch::TYPE_IN ? 'الحضور' : 'الانصراف'),
            ], 500);
        }

        return response()->json($result->toResponsePayload(), $result->accepted ? 200 : $result->httpStatus);
    }

    /**
     * الموظفُ صاحبُ الجلسة.
     *
     * `users.employee_id` هو الجسرُ الوحيد بين المستخدم وملفّه الوظيفي.
     * مستخدمٌ بلا ملفٍّ (حسابٌ إداريٌّ بحت) لا حضورَ له يُسجَّل، والردُّ
     * صريحٌ لا صفحةٌ فارغة.
     */
    private function currentEmployee(Request $request): Employee
    {
        // `Auth::user()` لا `$request->user()` — انظر التعليق النظير في
        // `TeamLiveStatusController`: الثانيةُ تعتمد على مُحلِّلِ المصادقة.
        $employeeId = Auth::user()?->employee_id;

        $employee = $employeeId
            ? Employee::with('department')->find($employeeId)
            : null;

        if (! $employee) {
            throw new AccessDeniedHttpException('حسابُك غير مرتبطٍ بملفِّ موظف — راجع الموارد البشرية.');
        }

        return $employee;
    }
}
