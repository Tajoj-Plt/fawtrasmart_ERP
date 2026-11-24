<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Exports\AttendanceDailyExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDays;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePhotoType;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Attendance\AttendanceStampService;
use App\Services\Attendance\StampContext;
use App\Services\AttendanceProcessorService;
use App\Support\OperationLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Picqer\Barcode\BarcodeGeneratorSVG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BarcodeAttendanceController extends Controller
{
    use ScopesToAccessibleEmployees;

    /** كوكي الاقتران: «معرّف الجهاز|مفتاح الشاشة» — مرّةً عند الاقتران، لا مع كل مسحة. */
    private const KIOSK_COOKIE = 'attendance_kiosk';

    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        /*
            التسجيلُ نيابةً عن الموظفين (الكشك / الباركود) وإدارةُ بطاقاتهم:
            `attendance_registration.kiosk` — وعلى موظفي نطاق المستخدم وحدهم.
        */
        // تشغيلُ شاشة جهاز الفرع: الاقترانُ والمسحُ ورمزُ المكتب المتغيّر
        $this->middleware('permission.role:attendance_registration.kiosk', ['only' => [
            'scanPage', 'pairKiosk', 'unpairKiosk', 'officeCode', 'scan', 'processBarcode', 'checkIn', 'checkOut',
            'searchEmployeeByBarcode', 'quickEmployeeInfo', 'dashboardStats', 'dashboard',
        ]]);

        /*
            بطاقاتُ الموظفين — مجموعتُها (`attendance_cards`). كانت بصلاحية تشغيل
            الشاشة نفسِها، فحسابُ تابلت الاستقبال يُصدر البطاقات ويحذفها.
        */
        $this->middleware('permission.role:attendance_cards.index', ['only' => ['cards', 'employeeBarcodes']]);
        $this->middleware('permission.role:attendance_cards.manage', ['only' => [
            'generateBarcode', 'generateAllBarcodes', 'toggleEmployeeBarcode', 'deleteEmployeeBarcode', 'setEmployeeChannel',
        ]]);
        $this->middleware('permission.role:attendance_cards.print', ['only' => ['printCards', 'printEmployeeBarcode']]);

        // التقاريرُ قراءةٌ لأيام الحضور لا تشغيلٌ للجهاز
        $this->middleware('permission.role:attendance_days.index', ['only' => [
            'dailyReport', 'detailedStats', 'employeeAttendanceReport', 'weeklyUsageStats', 'exportAttendance',
        ]]);

        // صيانةُ النظام وإعداداتُه
        $this->middleware('permission.role:attendance_settings.manage', ['only' => [
            'backupAttendanceData', 'cleanupOldData', 'systemDiagnostics', 'getSystemSettings', 'updateSystemSettings',
        ]]);
    }

    /**
     * شاشةُ جهاز الفرع الثابت.
     *
     * الجهازُ يُقترن مرّةً واحدة برمزٍ يُدخله المشغّل، فيُحفظ الاقترانُ في
     * الجلسة: لا يُسأل عن موقعه في كل نوبة، ولا يُترك تحديدُ الفرع لصدفةِ
     * آخر ما اختاره أحدُهم على الجهاز نفسِه.
     */
    public function scanPage(Request $request)
    {
        $setting = AttendanceSetting::current();

        // أنواعُ الصور المطلوبة لكل مرحلة — الشاشةُ تبني منها واجهةَ الالتقاط
        $photoTypes = $setting->photos_required
            ? AttendancePhotoType::active()->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        $kiosk = $this->resolveKiosk($request);
        $kiosk?->touchHeartbeat();

        return view('hr::barcode.barcode-scan', [
            'setting' => $setting,
            'photoTypes' => $photoTypes,
            'kiosk' => $kiosk,
            'geofence' => $kiosk?->geofence,
            'hasKiosks' => AttendanceKiosk::active()->exists(),
        ]);
    }

    /**
     * اقترانُ هذا المتصفّح بجهاز فرعٍ مُسجَّل — **شاشةٌ واحدة لكل جهاز**.
     *
     * كان الرمزُ وحده يكفي: أيُّ متصفّحٍ يُدخله يصير «جهاز الفرع»، فموظفٌ عرف
     * الرمز أقرن جوالَه ومسح بطاقتَه من حيث شاء وسُجّل حضورُه «من جهاز الفرع».
     * الآن يُصدَر عند الاقتران مفتاحٌ سرّيٌّ يُحفظ في متصفّح هذه الشاشة وحدها
     * (وبصمتُه على الجهاز)، ولا تُقرن شاشةٌ ثانية والأولى قائمة — يفكّها المدير
     * من صفحة الجهاز أو يصدر رمزاً جديداً. والاقترانُ يبقى بعد إغلاق المتصفح.
     */
    public function pairKiosk(Request $request)
    {
        $request->validate(['pairing_code' => 'required|string|max:16']);

        $kiosk = AttendanceKiosk::active()
            ->where('pairing_code', strtoupper(trim($request->pairing_code)))
            ->first();

        if (! $kiosk) {
            return back()->with('error', 'رمزُ الاقتران غير صحيح أو الجهاز موقوف.');
        }

        // هذه الشاشةُ نفسُها مقترنةٌ به أصلاً
        if ($this->holdsKioskToken($request, $kiosk)) {
            return redirect()->route('barcode.scan')->with('success', 'هذه الشاشة مقترنة بجهاز «'.$kiosk->name.'».');
        }

        if ($kiosk->device_token_hash) {
            return back()->with('error', 'جهاز «'.$kiosk->name.'» مقترنٌ بشاشةٍ أخرى'
                .($kiosk->paired_at ? ' منذ '.$kiosk->paired_at->format('Y-m-d H:i') : '')
                .' — لا تُقرن شاشةٌ ثانية. إن تغيّرت شاشة الفرع فليضغط المدير «فك اقتران الشاشة» من صفحة الجهاز في الإعدادات، ثم أدخل الرمز هنا.');
        }

        $token = Str::random(48);
        $kiosk->forceFill([
            'device_token_hash' => hash('sha256', $token),
            'paired_at' => now(),
            'paired_by' => auth()->id(),
            'paired_user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->save();

        $this->rememberKiosk($request, $kiosk, $token);
        $kiosk->touchHeartbeat();

        return redirect()->route('barcode.scan')
            ->with('success', 'تم اقترانُ هذه الشاشة بجهاز «'.$kiosk->name.'». لن يُقبل المسح لهذا الجهاز إلا من هذه الشاشة.');
    }

    /**
     * «رمز المكتب» المتغيّر لشاشة الجهاز — تطلبه الشاشةُ كل بضع ثوانٍ وتعرضه،
     * فيمسحه الموظفُ بجواله. لا يُصدَر إلا للشاشة المقترنة (مفتاحُها جزءٌ من التوقيع).
     */
    public function officeCode(Request $request)
    {
        $kioskOrReject = $this->requirePairedKiosk($request);
        if (! $kioskOrReject instanceof AttendanceKiosk) {
            return $kioskOrReject;
        }

        $code = app(\App\Services\Attendance\OfficeCode::class)->screenCode($kioskOrReject);

        if (! $code) {
            return response()->json(['enabled' => false]);
        }

        $window = \App\Services\Attendance\OfficeCode::WINDOW_SECONDS;

        return response()->json([
            'enabled' => true,
            'svg' => (string) QrCode::format('svg')->size(260)->margin(1)->errorCorrection('M')->generate($code),
            'refresh_in' => $window - (now()->getTimestamp() % $window),
        ]);
    }

    /**
     * فكُّ الاقتران من الشاشة نفسِها — يُحرَّر الجهازُ لشاشةٍ أخرى.
     */
    public function unpairKiosk(Request $request)
    {
        $kiosk = $this->resolveKiosk($request);

        // الشاشةُ المقترنةُ فعلاً تُحرِّر الجهاز؛ غيرُها يمسح كوكيه فقط
        $kiosk?->forceFill(['device_token_hash' => null, 'paired_at' => null, 'paired_by' => null, 'paired_user_agent' => null])->save();

        $this->forgetKiosk($request);

        return redirect()->route('barcode.scan')->with('success', 'تم فكُّ اقتران الشاشة.');
    }

    /**
     * ثبّت الاقتران: معرّفُ الجهاز ومفتاحُ الشاشة في الجلسة وفي كوكي طويل
     * الأمد (مشفّرٌ كبقية كوكيز Laravel) — فلا يُطلب الرمز بعد إغلاق المتصفح.
     */
    private function rememberKiosk(Request $request, AttendanceKiosk $kiosk, string $token): void
    {
        $value = $kiosk->id.'|'.$token;
        $request->session()->put('attendance_kiosk', $value);
        Cookie::queue(cookie(self::KIOSK_COOKIE, $value, 60 * 24 * 365 * 5));
    }

    private function forgetKiosk(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(['attendance_kiosk', 'attendance_kiosk_id']);
        }

        Cookie::queue(Cookie::forget(self::KIOSK_COOKIE));
    }

    /** @return array{0: int, 1: string}|null  [معرّف الجهاز، مفتاح الشاشة] */
    private function kioskCredentials(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = (string) ($request->session()->get('attendance_kiosk') ?: $request->cookie(self::KIOSK_COOKIE));

        if (! str_contains($value, '|')) {
            return null; // اقترانٌ قديمٌ بالمعرّف وحده — لا يُقبل؛ يُدخَل الرمز مرّةً من جديد
        }

        [$id, $token] = explode('|', $value, 2);

        return ((int) $id > 0 && $token !== '') ? [(int) $id, $token] : null;
    }

    private function holdsKioskToken(Request $request, AttendanceKiosk $kiosk): bool
    {
        $credentials = $this->kioskCredentials($request);

        return $credentials
            && $credentials[0] === (int) $kiosk->id
            && $kiosk->device_token_hash
            && hash_equals($kiosk->device_token_hash, hash('sha256', $credentials[1]));
    }

    private function resolveKiosk(Request $request): ?AttendanceKiosk
    {
        // طلبٌ بلا جلسة (استدعاءٌ برمجيّ أو اختبارٌ يبني `Request` يدوياً)
        // لا يحمل اقتراناً — لا استثناءً ولا جهازاً مُفترَضاً.
        $credentials = $this->kioskCredentials($request);

        if (! $credentials) {
            if ($request->hasSession() && ($request->session()->has('attendance_kiosk_id') || $request->cookie(self::KIOSK_COOKIE))) {
                $this->forgetKiosk($request);
            }

            return null;
        }

        $kiosk = AttendanceKiosk::active()->with('geofence')->find($credentials[0]);

        /*
            جهازٌ حُذف أو أُوقف، أو مفتاحُ هذه الشاشة لم يعد مفتاحَه (فكّ المديرُ
            الاقتران أو أصدر رمزاً جديداً أو اقترنت شاشةٌ أخرى): الاقترانُ هنا
            يسقط، ولا يُقبل مسحٌ حتى يُدخَل الرمزُ من جديد.
        */
        if (! $kiosk || ! $this->holdsKioskToken($request, $kiosk)) {
            $this->forgetKiosk($request);

            return null;
        }

        // إن جاء من الكوكي وحدَه (جلسة Laravel تجدّدت) أعد زرعَه في الجلسة
        $value = $kiosk->id.'|'.$credentials[1];
        if ($request->session()->get('attendance_kiosk') !== $value) {
            $request->session()->put('attendance_kiosk', $value);
        }

        return $kiosk;
    }

    /**
     * شاشةُ المسح لا تقبل أختاماً من متصفّحٍ غير مقترن.
     *
     * بلا جهاز معروف لا فرعَ ولا نطاقاً — فالتسجيلُ يُرفَض قبل أيّ ختم،
     * ولا يُترَك ليدخل سجلّ «أجهزة غير معرّفة».
     *
     * @return AttendanceKiosk|\Illuminate\Http\JsonResponse
     */
    private function requirePairedKiosk(Request $request)
    {
        $kiosk = $this->resolveKiosk($request);

        if ($kiosk) {
            return $kiosk;
        }

        return response()->json([
            'success' => false,
            'code' => 'kiosk_required',
            'title' => 'الجهاز غير مقترن',
            'message' => 'أدخِل رمز الاقتران أعلى الشاشة قبل المسح — لا يُقبَل تسجيلٌ من جهاز غير مقترن.',
            'stage' => 'kiosk',
            'stage_label' => 'فحص اقتران الجهاز',
        ], 403);
    }

    /**
     * معالجة الباركود الممسوح — تعريفٌ فقط، لا تسجيل.
     *
     * تُرجِع ما يكفي الشاشةَ لتعرض بطاقةَ الموظف وحالتَه اليوم وورديتَه،
     * فيضغط المشغّلُ «حضور» أو «انصراف». والباركودُ المجهول يُسجَّل ختماً
     * مردوداً: بطاقةٌ لا يعرفها النظام قد تكون بطاقةَ موظفٍ لم تُفعَّل
     * بعد — وتَكرارُها على جهازٍ بعينه واقعةٌ تستحقّ أن تُرى.
     */
    public function processBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string|max:64',
        ]);

        $kioskOrReject = $this->requirePairedKiosk($request);
        if (! $kioskOrReject instanceof AttendanceKiosk) {
            return $kioskOrReject;
        }
        $kiosk = $kioskOrReject;

        $employee = Employee::with('department')
            ->where('barcode', $request->barcode)
            ->first();

        if (! $employee || ! $employee->barcode_enabled) {
            $result = app(AttendanceStampService::class)->rejectUnknownBarcode(
                StampContext::fromRequest($request, AttendancePunch::TYPE_IN, AttendancePunch::CHANNEL_KIOSK, AttendancePunch::METHOD_BARCODE, $kiosk)
            );

            return response()->json([
                'success' => false,
                'message' => $employee ? 'بطاقةُ هذا الموظف موقوفة' : $result->message,
            ], 404);
        }

        if (! $this->canAccessEmployee($employee->id)) {
            return $this->outOfScope();
        }

        // اليومُ الذي سيُسنَد إليه الختمُ التالي — لا تقويمُ اللحظة. وإلّا
        // عرض الجهازُ زرَّ «حضور» لموظفٍ ليليٍّ نوبتُه مفتوحةٌ منذ أمس.
        $today = app(AttendanceStampService::class)
            ->resolveCheckoutDate($employee, Carbon::today());

        $attendance = AttendanceDays::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $day = app(AttendanceProcessorService::class)->describeDay($employee, $today);

        return response()->json([
            'success' => true,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'department' => $employee->department->name ?? 'غير محدد',
                'photo' => $employee->employee_photo,
                // `storage/` كانت هنا — ولا صورةَ موظفٍ فيها؛ الرفعُ إلى
                // `assets/uploads/employee/`. والمسارُ الآن من الموديل وحده.
                'photo_url' => $employee->photo_url,
            ],
            'shift' => [
                'expected_check_in' => $day['expected_check_in']?->format('H:i'),
                'expected_check_out' => $day['expected_check_out']?->format('H:i'),
                'grace_minutes' => (int) ($day['config']['late_grace_minutes'] ?? 0),
                'is_holiday' => (bool) $day['is_holiday'],
                'is_weekly_off' => (bool) $day['is_weekly_off'],
                'on_leave' => $day['leave'] !== null,
            ],
            'attendance_status' => [
                'is_checked_in' => (bool) $attendance?->check_in_time,
                'is_checked_out' => (bool) $attendance?->check_out_time,
                'check_in_time' => $attendance?->check_in_time,
                'check_out_time' => $attendance?->check_out_time,
            ],
            'current_time' => Carbon::now()->format('H:i:s'),
            'current_date' => Carbon::now()->format('Y-m-d'),
            'active_date' => $today->format('Y-m-d'),
        ]);
    }

    /**
     * مسحةٌ واحدة تُنهي العملية — وهذا هو الفرق بين المسح والإدخال اليدوي.
     *
     * كان التدفّقُ: امسح ← تُعرَض البطاقة ← **يضغط المشغّلُ زرّاً** ← يُسجَّل.
     * تلك الضغطةُ هي ما جعل الباركود يشبه اليدوي: النظامُ يسأل «أحضورٌ أم
     * انصراف؟» وجوابُه مكتوبٌ في سجل اليوم، ومن يسأله يفتح بابَ الخطأ —
     * ويجعل الوسيلةَ قراراً بشرياً بعد أن كانت واقعة.
     *
     * فالآن: يُعرَّف الموظفُ من الباركود، ويُشتقّ نوعُ الختم من سجل يومه،
     * ويُختم — كلُّه في نداءٍ واحد. والمشغّلُ يرعى الجهاز ولا يقرّر.
     */
    public function scan(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string|max:64',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'photos' => 'nullable|array|max:10',
            'photos.*.type_id' => 'nullable|integer|exists:attendance_photo_types,id',
            'photos.*.image' => 'nullable|string',
        ]);

        $kioskOrReject = $this->requirePairedKiosk($request);
        if (! $kioskOrReject instanceof AttendanceKiosk) {
            return $kioskOrReject;
        }
        $kiosk = $kioskOrReject;

        $barcode = trim($request->barcode);
        $stamper = app(AttendanceStampService::class);

        $employee = Employee::with('department')->where('barcode', $barcode)->first();

        if (! $employee) {
            // باركودٌ مجهولٌ واقعةٌ تُسجَّل — قد يكون بطاقةً لم تُفعَّل بعد
            $stamper->rejectUnknownBarcode(StampContext::fromRequest(
                $request, AttendancePunch::TYPE_IN, AttendancePunch::CHANNEL_KIOSK, AttendancePunch::METHOD_BARCODE, $kiosk
            ));

            return response()->json([
                'success' => false,
                'code' => 'unknown_barcode',
                'title' => 'بطاقة غير معروفة',
                'message' => 'هذه البطاقة غير مسجّلة في النظام.',
                'stage' => 'employee',
                'stage_label' => 'فحص ملف الموظف',
            ], 404);
        }

        if (! $this->canAccessEmployee($employee->id)) {
            return $this->outOfScope();
        }

        $punchType = $stamper->nextPunchTypeFor($employee);

        if ($punchType === null) {
            $stamper->rejectDayComplete($employee, StampContext::fromRequest(
                $request, AttendancePunch::TYPE_IN, AttendancePunch::CHANNEL_KIOSK, AttendancePunch::METHOD_BARCODE, $kiosk
            ));

            return response()->json([
                'success' => false,
                'code' => 'day_complete',
                'title' => 'اليوم مكتمل بالفعل',
                'message' => 'سُجِّل حضورُك وانصرافُك لهذا اليوم.',
                'stage' => 'day',
                'stage_label' => 'فحص سجل اليوم',
                'employee' => $this->cardEmployee($employee),
            ], 409);
        }

        $context = StampContext::fromRequest(
            $request, $punchType, AttendancePunch::CHANNEL_KIOSK, AttendancePunch::METHOD_BARCODE, $kiosk
        );

        try {
            $result = $stamper->stamp($employee, $context);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'scan');

            return response()->json([
                'success' => false,
                'title' => 'خطأ في النظام',
                'message' => 'تعذّر تسجيل العملية — أعد المحاولة. إن تكرّر الأمر فراجع سجل الأخطاء.',
                'employee' => $this->cardEmployee($employee),
            ], 500);
        }

        if (! $result->accepted) {
            $message = trim((string) ($result->message ?? ''));
            $title = $this->rejectionTitle($result->code, $message);

            return response()->json([
                'success' => false,
                'code' => $result->code,
                'stage' => $result->stage,
                'stage_label' => $this->rejectionStageLabel($result->stage),
                'title' => $title,
                // الرسالة التفصيلية دائماً — حتى لو طابقت العنوان يقرأها المشغّل
                'message' => $message !== '' ? $message : $title,
                'employee' => $this->cardEmployee($employee),
            ], $result->httpStatus);
        }

        $data = $result->data;

        // أعد تحميل سجل اليوم بعد الختم ليعكس اللوحة الجانبية الحالة الحقيقية
        $employee->loadMissing('department');

        return response()->json([
            'success' => true,
            'punch_type' => $punchType,
            'title' => $punchType === AttendancePunch::TYPE_IN ? 'تم تسجيل الحضور' : 'تم تسجيل الانصراف',
            'time' => $punchType === AttendancePunch::TYPE_IN ? $data['check_in_time'] : $data['check_out_time'],
            'status' => $data['status'] ?? null,
            'status_code' => $data['status_code'] ?? null,
            'late_minutes' => $data['late_minutes'] ?? 0,
            'early_leave_minutes' => $data['early_leave_minutes'] ?? 0,
            'overtime_minutes' => $data['overtime_minutes'] ?? 0,
            'expected_check_in' => $data['expected_check_in'] ?? null,
            'expected_check_out' => $data['expected_check_out'] ?? null,
            'check_out_capped' => (bool) ($data['check_out_capped'] ?? false),
            'working_hours' => $data['working_hours'] ?? null,
            'warnings' => $result->warnings,
            'employee' => $this->cardEmployee($employee),
            'location' => $data['location'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'distance_meters' => $data['distance_meters'] ?? null,
        ]);
    }

    /**
     * بطاقةُ الموظف كما تعرضها شاشةُ المسح.
     *
     * تُعاد في بطاقة النتيجة الفورية وفي لوحة المعلومات الجانبية،
     * لذلك تحمل المعرّف وحالة اليوم لا الاسم والصورة فقط.
     */
    private function outOfScope(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'out_of_scope',
            'title' => 'خارج نطاقك',
            'message' => 'هذا الموظف خارج نطاق الموظفين المصرّح لك بتسجيل حضورهم.',
            'stage' => 'employee',
            'stage_label' => 'فحص ملف الموظف',
        ], 403);
    }

    private function cardEmployee(Employee $employee, ?AttendanceDays $attendance = null): array
    {
        if ($attendance === null) {
            $today = app(AttendanceStampService::class)
                ->resolveCheckoutDate($employee, Carbon::today());

            $attendance = AttendanceDays::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $today)
                ->first();
        }

        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'department' => $employee->department->name ?? 'غير محدد',
            // اسمُ الملفّ خامّاً للتوافق، والعنوانُ الجاهزُ من الموديل
            'photo' => $employee->employee_photo,
            'photo_url' => $employee->photo_url,
            'attendance_status' => [
                'is_checked_in' => (bool) $attendance?->check_in_time,
                'is_checked_out' => (bool) $attendance?->check_out_time,
                'check_in_time' => $attendance?->check_in_time,
                'check_out_time' => $attendance?->check_out_time,
            ],
        ];
    }

    /**
     * عنوانٌ قصيرٌ للرفض — يُقرأ من مسافةِ ذراعٍ على شاشة جهازٍ في ممرّ.
     * والتفصيلُ في الرسالة تحته. لا نُرجع «تعذّر التسجيل» إن وُجدت رسالة.
     */
    private function rejectionTitle(?string $code, ?string $message = null): string
    {
        $titles = [
            'employee_inactive' => 'الموظف متوقّف عن العمل',
            'barcode_disabled' => 'البطاقة ملغاة',
            'barcode_mismatch' => 'بطاقة لا تطابق صاحبها',
            'barcode_not_supplied' => 'لم يُمسح باركود',
            'unknown_barcode' => 'بطاقة غير معروفة',
            'before_hire_date' => 'لم يبدأ التعيين بعد',
            'after_last_working_day' => 'انتهت الخدمة',
            'channel_not_allowed' => 'قناة تسجيل غير مسموحة',
            'outside_geofence' => 'خارج نطاق الموقع',
            'location_required' => 'الموقع مطلوب',
            'location_inaccurate' => 'دقّة الموقع غير كافية',
            'network_not_allowed' => 'شبكة غير مسموحة',
            'determinant_rejected' => 'رُفض حسب محدِّد الحضور',
            'already_checked_in' => 'الحضور مسجَّل مسبقاً',
            'already_checked_out' => 'الانصراف مسجَّل مسبقاً',
            'no_check_in' => 'لا انصراف بلا حضور',
            'photos_missing' => 'الصور المطلوبة ناقصة',
            'kiosk_required' => 'الجهاز غير مقترن',
            'on_approved_leave' => 'الموظف في إجازة',
            'weekly_off' => 'اليوم عطلة أسبوعية',
            'public_holiday' => 'اليوم عطلة رسمية',
            'outside_login_window' => 'خارج نافذة تسجيل الحضور',
            'outside_logout_window' => 'خارج نافذة تسجيل الانصراف',
            'route_missing' => 'لا خط سير لهذا اليوم',
            'route_incomplete' => 'خط السير غير مكتمل',
            'day_complete' => 'اليوم مكتمل بالفعل',
        ];

        if ($code && isset($titles[$code])) {
            return $titles[$code];
        }

        $message = trim((string) $message);
        if ($message !== '') {
            // عنوانٌ من الرسالة نفسها — لا غموضَ «تعذّر التسجيل»
            return mb_strlen($message) > 90 ? mb_substr($message, 0, 87).'…' : $message;
        }

        if ($code) {
            return 'رفض التسجيل ('.$code.')';
        }

        return 'تعذّر التسجيل';
    }

    private function rejectionStageLabel(?string $stage): ?string
    {
        return [
            'employee' => 'فحص ملف الموظف',
            'shift' => 'فحص الوردية / النوافذ',
            'location' => 'فحص الموقع الجغرافي',
            'photos' => 'فحص الصور المطلوبة',
            'day' => 'فحص سجل اليوم',
            'leave' => 'فحص الإجازة',
            'kiosk' => 'فحص اقتران الجهاز',
        ][$stage] ?? $stage;
    }

    /**
     * تسجيل حضور الموظف من جهاز الفرع.
     *
     * كلُّ القواعد في `AttendanceStampService` — هذا المتحكّم يبني الظرفَ
     * ويترجم النتيجة. ما كان هنا سابقاً (فحصُ الحالة، والنطاق، والصور،
     * والتكرار، وحسابُ التأخير) صار حلقاتٍ في تسلسلٍ واحد يشترك فيه هذا
     * المدخلُ والمدخلُ الميداني، فلا يُصان أحدُهما دون الآخر.
     */
    public function checkIn(Request $request)
    {
        return $this->stamp($request, AttendancePunch::TYPE_IN);
    }

    /**
     * تسجيل انصراف الموظف من جهاز الفرع.
     *
     * الانصرافُ يمرّ بالتسلسل نفسِه — وهذا وحده إصلاحٌ قائمٌ بذاته:
     * `checkOut` القديم لم يكن يفحص موقعاً ولا حالةَ موظفٍ ولا صورةً، ولا
     * يكتب إحداثياتِ الانصراف. فكان من يُمسَح حضورُه داخل النطاق يستطيع
     * أن يُسجّل انصرافَه من بيته.
     */
    public function checkOut(Request $request)
    {
        return $this->stamp($request, AttendancePunch::TYPE_OUT);
    }

    private function stamp(Request $request, string $punchType)
    {
        $request->validate([
            'barcode' => 'nullable|string|max:64',
            'employee_id' => 'nullable|exists:employees,id',
            'reason' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'photos' => 'nullable|array|max:10',
            'photos.*.type_id' => 'nullable|integer|exists:attendance_photo_types,id',
            'photos.*.image' => 'nullable|string',
        ]);

        /*
            الهويّةُ من المسحة لا من حقلٍ يختاره العميل.

            كان الطلبُ يحمل `employee_id` و`method` منفصلين، فيختار المرسِلُ
            موظفاً ويصف الوسيلةَ كما يشاء. الآن: باركودٌ مُرسَلٌ يُحدِّد
            **مَن** و**بأيّ وسيلة** معاً، فلا ينفصل الاسمُ عن دليله.

            و`method` لم يعد يُقبل من الطلب إطلاقاً — يُشتقّ هنا، ويُعاد
            التحقّقُ منه في `AttendanceStampService`.
        */
        $barcode = trim((string) $request->input('barcode'));

        if ($barcode !== '') {
            $employee = Employee::where('barcode', $barcode)->first();

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'الباركود غير صحيح أو الموظف غير موجود',
                    'stage' => 'employee',
                ], 404);
            }

            // اختلافُ المُرسَل عن صاحب الباركود واقعةٌ تُردّ لا تُسوّى بصمت
            if ($request->filled('employee_id') && (int) $request->employee_id !== $employee->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'الباركود الممسوح يخصّ موظفاً آخر — أعد المسح.',
                    'stage' => 'employee',
                ], 422);
            }

            $method = AttendancePunch::METHOD_BARCODE;
        } else {
            /*
                المسارُ اليدوي: استثناءٌ لا وسيلةُ تسجيل. صلاحيةٌ مستقلّة
                لأن من يشغّل الجهاز ليس بالضرورة من يجوز له تسجيلُ حضورٍ
                بلا بطاقة، وسببٌ مكتوبٌ لأن استثناءً بلا سببٍ لا يُراجَع.
            */
            if (! hasPermission('attendance_records.create')) {
                return response()->json([
                    'success' => false,
                    'message' => 'التسجيل اليدوي يتطلّب صلاحية «تسجيل حضور يدوي استثنائي» — امسح بطاقة الموظف.',
                    'stage' => 'employee',
                ], 403);
            }

            if (! $request->filled('employee_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدِّد الموظف للتسجيل اليدوي.',
                    'stage' => 'employee',
                ], 422);
            }

            if (! $request->filled('reason')) {
                return response()->json([
                    'success' => false,
                    'message' => 'اكتب سببَ التسجيل اليدوي — يُحفظ في سجل الأختام.',
                    'stage' => 'employee',
                ], 422);
            }

            $employee = Employee::findOrFail($request->employee_id);
            $method = AttendancePunch::METHOD_MANUAL;
        }

        if (! $this->canAccessEmployee($employee->id)) {
            return $this->outOfScope();
        }

        $kioskOrReject = $this->requirePairedKiosk($request);
        if (! $kioskOrReject instanceof AttendanceKiosk) {
            return $kioskOrReject;
        }

        $context = StampContext::fromRequest(
            $request,
            $punchType,
            AttendancePunch::CHANNEL_KIOSK,
            $method,
            $kioskOrReject,
        );

        try {
            $result = app(AttendanceStampService::class)->stamp($employee, $context);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.BarcodeAttendance', $punchType === AttendancePunch::TYPE_IN ? 'checkIn' : 'checkOut');

            return response()->json([
                'success' => false,
                'title' => 'خطأ في النظام',
                'message' => 'حدث خطأ أثناء تسجيل '.($punchType === AttendancePunch::TYPE_IN ? 'الحضور' : 'الانصراف'),
            ], 500);
        }

        if (! $result->accepted) {
            $message = trim((string) ($result->message ?? ''));

            return response()->json([
                'success' => false,
                'code' => $result->code,
                'stage' => $result->stage,
                'stage_label' => $this->rejectionStageLabel($result->stage),
                'title' => $this->rejectionTitle($result->code, $message),
                'message' => $message !== '' ? $message : $this->rejectionTitle($result->code),
                'punch_id' => $result->punch?->id,
            ], $result->httpStatus);
        }

        return response()->json($result->toResponsePayload(), 200);
    }

    /**
     * توليد باركود للموظف
     */
    public function generateBarcode($employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        try {
            $employee = Employee::findOrFail($employeeId);

            // توليد باركود فريد إذا لم يكن موجوداً
            if (! $employee->barcode) {
                $barcode = 'EMP_'.$employee->id.'_'.Str::random(8);
                $employee->update(['barcode' => $barcode]);
            }

            return response()->json([
                'success' => true,
                'barcode' => $employee->barcode,
                'employee_name' => $employee->full_name,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'generateBarcode');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في توليد الباركود',
            ], 500);
        }
    }

    /**
     * عرض تقرير الحضور اليومي
     */
    public function dailyReport(Request $request)
    {
        $date = $request->input('date', Carbon::today()->format('Y-m-d'));

        $attendances = $this->scopeToEmployees(AttendanceDays::with('employee.department'))
            ->whereDate('attendance_date', $date)
            ->orderBy('check_in_time')
            ->get();

        $stats = [
            'total_present' => $attendances->where('status', 'present')->count(),
            'total_late' => $attendances->where('status', 'late')->count(),
            'total_absent' => $this->scopeToEmployees(Employee::query(), 'id')->count() - $attendances->count(),
            'barcode_usage' => $attendances->where('scanned_via_barcode', true)->count(),
        ];

        return view('hr::barcode.daily-report', compact('attendances', 'stats', 'date'));
    }

    /**
     * توليد باركود لجميع الموظفين
     */
    public function generateAllBarcodes()
    {
        try {
            $employees = $this->scopeToEmployees(Employee::query(), 'id')->whereNull('barcode')->get();

            foreach ($employees as $employee) {
                $barcode = 'EMP_'.$employee->id.'_'.Str::random(8);
                $employee->update(['barcode' => $barcode]);
            }

            return response()->json([
                'success' => true,
                'message' => "تم توليد باركود لـ {$employees->count()} موظف",
                'generated_count' => $employees->count(),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'generateAllBarcodes');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في توليد الباركود',
            ], 500);
        }
    }

    /**
     * عرض إحصائيات سريعة للداشبورد
     */
    public function dashboardStats(Request $request)
    {
        /*
            نبضةُ الجهاز مع كل استطلاع.

            الشاشةُ تستدعي هذا المسار كلَّ عشرين ثانيةً لتُضيء مؤشّرَ
            الاتصال — وكان لا يُحدِّث `last_seen_at`. فجهازٌ شاشتُه مفتوحةٌ
            وشبكتُه تعمل يُقرأ في «سجل جلسات الحضور» **صامتاً** بعد
            خمسٍ وأربعين دقيقةً بلا مسح، وهو بخير: فرعٌ هادئٌ في منتصف
            الدوام يُنذَر عنه كأنّ جهازَه انطفأ.

            والنبضةُ هنا هي الدليلُ الوحيد على أن الجهاز حيٌّ حين لا يمسح
            أحد — وهي ما يُفرِّق بين «لا أحد يسجّل» و«الجهاز معطوب»، وذاك
            الفرقُ هو سببُ وجود تلك الشاشة.
        */
        $this->resolveKiosk($request)?->touchHeartbeat();

        $today = Carbon::today();

        $stats = [
            'employees_present_today' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $today))
                ->whereNotNull('check_in_time')
                ->whereNull('check_out_time')
                ->count(),
            'total_check_ins_today' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $today))
                ->whereNotNull('check_in_time')
                ->count(),
            'late_arrivals_today' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $today))
                ->where('status', 'late')
                ->count(),
            'barcode_scans_today' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $today))
                ->where('scanned_via_barcode', true)
                ->count(),
            'latest_scans' => $this->scopeToEmployees(AttendanceDays::with('employee'))
                ->whereDate('attendance_date', $today)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * البحث عن موظف بالباركود (لأغراض التطوير والاختبار)
     */
    public function searchEmployeeByBarcode(Request $request)
    {
        $barcode = $request->input('barcode');

        $employee = Employee::where('barcode', $barcode)->first();

        if ($employee && $this->canAccessEmployee($employee->id)) {
            return response()->json([
                'found' => true,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'department' => $employee->department->name ?? 'غير محدد',
                    'barcode' => $employee->barcode,
                ],
            ]);
        }

        return response()->json(['found' => false]);
    }

    /**
     * إدارةُ بطاقات الحضور: إصدارٌ وطباعةٌ وسحبٌ فوريّ.
     *
     * `employeeBarcodes()` التي كانت هنا ميتةٌ من وجهين: لا مسارَ يؤدّي
     * إليها، وترجع `view('attendance.employee-barcode')` — اسمٌ بلا فضاء
     * أسماء (`hr::`) ولا ملفَّ قالبٍ يقابله. فلو وُصِلت لانتهت بـ
     * `View not found`.
     *
     * والسحبُ الفوري هو بيتُ القصيد: موظفٌ استقال أو أُوقف تبقى بطاقتُه
     * صالحةً في جيبه حتى تُوقَف هنا — وحارسُ `barcode_enabled` في
     * `AttendanceStampService` يردّها عندئذٍ.
     */
    /** سقفُ ما يُصيَّر في دفعةٍ واحدة — نحو خمسٍ وعشرين ورقةَ A4. */
    private const MAX_PRINTED_CARDS = 200;

    /**
     * تصفيةُ شاشة البطاقات — مشتركةٌ بين العرض والطباعة.
     *
     * نسختان تتباعدان: يُصفّي المستخدمُ بقسمٍ ثم يضغط «طباعة الكل» فتخرج
     * بطاقاتُ المنشأة كلِّها. فالطباعةُ تطبع **ما يراه** لا ما سواه.
     */
    private function cardsQuery(Request $request): Builder
    {
        $query = $this->scopeToEmployees(Employee::query(), 'id')
            ->with('department:id,name')
            ->orderBy('first_name');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('card_state')) {
            match ($request->card_state) {
                'none' => $query->whereNull('barcode'),
                'active' => $query->whereNotNull('barcode')->where('barcode_enabled', true),
                'suspended' => $query->whereNotNull('barcode')->where('barcode_enabled', false),
                default => null,
            };
        }

        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $term)
                ->orWhere('middle_name', 'like', $term)
                ->orWhere('nickname', 'like', $term)
                ->orWhere('barcode', 'like', $term));
        }

        return $query;
    }

    public function cards(Request $request)
    {
        $employees = $this->cardsQuery($request)->paginate(30)->withQueryString();

        return view('hr::barcode.cards', [
            'employees' => $employees,
            'geofences' => \App\Models\AttendanceGeofence::active()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['department_id', 'card_state', 'q']),
            'stats' => [
                'total' => $this->scopeToEmployees(Employee::query(), 'id')->count(),
                'issued' => $this->scopeToEmployees(Employee::query(), 'id')->whereNotNull('barcode')->count(),
                'suspended' => $this->scopeToEmployees(Employee::query(), 'id')->whereNotNull('barcode')->where('barcode_enabled', false)->count(),
                'none' => $this->scopeToEmployees(Employee::query(), 'id')->whereNull('barcode')->count(),
            ],
        ]);
    }

    /**
     * طباعة باركود موظف واحد
     */
    public function printEmployeeBarcode($employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        try {
            $employee = Employee::findOrFail($employeeId);

            if (! $employee->barcode) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد باركود لهذا الموظف',
                ], 400);
            }

            $employee->loadMissing(['department', 'branch', 'job_title']);

            /*
                الباركودُ ورمزُ QR يُولَّدان في الخادم — لا في المتصفّح.

                كانت البطاقةُ تستدعي ثلاثَ مكتباتٍ من شبكاتِ توزيعٍ خارجية
                وتُصيّرها في `<canvas>`. وفي ذلك عيبان:

                ١) **الرابطُ معطوب**: `qrcode@1.5.3/build/qrcode.min.js` غير
                   موجودٍ في الحزمة — يردّ ٦٤ بايتاً من نصّ خطأ. فرمزُ QR
                   لم يظهر على بطاقةٍ واحدةٍ قط، ويبقى مكانُه فراغاً أبيض
                   في كل نسخةٍ تُطبَع.

                ٢) **وبطاقةٌ تُطبَع لا تنتظر شبكة**: جهازُ الموارد البشرية
                   قد يكون على شبكةٍ مغلقة، فتخرج البطاقةُ بلا باركودٍ
                   أصلاً — وهي بطاقةٌ لا وظيفةَ لها غيره.

                و`picqer` و`simple-qrcode` مثبَّتتان في المشروع ومستعملتان
                في الفواتير والمخزون. وSVG أدقُّ في الطباعة من `canvas`:
                متّجهٌ لا نقطيّ، فلا يتحبّب عند تكبير الطابعة.
            */
            $barcodeSvg = (new BarcodeGeneratorSVG)->getBarcode(
                $employee->barcode,
                BarcodeGeneratorSVG::TYPE_CODE_128,
                2,
                60
            );

            /*
                محتوى QR: الباركود وحدَه لا كائنُ JSON.

                كان يحمل الاسمَ والشركةَ وطابعَ الوقت — فيكبر الرمزُ ويكثر
                تقسيمُه بلا فائدة: القارئُ على الباب يبحث عن معرّفٍ يطابق
                موظفاً، والاسمُ المطبوعُ على البطاقة نفسِها. وبياناتٌ شخصيةٌ
                في رمزٍ يقرؤه أيُّ هاتفٍ زيادةٌ لا تُحتاج.
            */
            $qrSvg = QrCode::format('svg')->size(150)->margin(0)
                ->errorCorrection('M')
                ->generate($employee->barcode);

            /*
                الصورةُ تُتحقَّق على القرص لا في المتصفّح.

                كان القالبُ يكتفي بوجود قيمةٍ في `employee_photo`، والملفُّ
                قد يكون محذوفاً أو مفقوداً بعد نقل — فيخرج `<img>` مكسوراً
                وتُطبَع البطاقةُ بأيقونة «صورة معطوبة» مكانَ الوجه. والفحصُ
                هنا يختار البديلَ قبل أن يُصيَّر شيء، فيصحّ بلا جافاسكربت —
                وهو شرطُ صفحةٍ تُطبَع.
            */
            $photoUrl = $this->employeePhotoUrl($employee);

            $nameSize = $this->cardNameSize($employee->full_name);

            return view('hr::barcode.print-barcode', compact(
                'employee', 'barcodeSvg', 'qrSvg', 'photoUrl', 'nameSize'
            ));

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'printEmployeeBarcode');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في طباعة الباركود',
            ], 500);
        }
    }

    /**
     * طباعةُ دفعةٍ من البطاقات — بنفس تصفية الشاشة.
     *
     * حلّت محلَّ «مولّد الباركود الجماعي»، وكان قالباً يتيماً (٩٥٨ سطراً)
     * لا مسارَ له ولا متحكّم: يكرّر شاشةَ إدارة البطاقات، وعناوينُه
     * الثلاثةُ مكتوبةٌ بيدها على بادئةٍ خاطئة، ورمزُ QR فيه على مكتبةٍ
     * رابطُها معطوب. وما انفرد به فعلاً هو هذا: طباعةُ الكلّ في صفحة.
     *
     * ومن لا بطاقةَ له يُستبعَد: الورقةُ تُقصّ وتُوزَّع، وبطاقةٌ بلا
     * باركودٍ ورقةٌ لا تُمسَح.
     */
    public function printCards(Request $request)
    {
        try {
            $query = $this->cardsQuery($request)->whereNotNull('barcode');

            $total = (clone $query)->count();
            $employees = $query->limit(self::MAX_PRINTED_CARDS)->get();

            $generator = new BarcodeGeneratorSVG;

            $cards = $employees->map(fn (Employee $employee) => [
                'employee' => $employee,
                'barcodeSvg' => $generator->getBarcode(
                    $employee->barcode, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 60
                ),
                'photoUrl' => $this->employeePhotoUrl($employee),
                'nameSize' => $this->cardNameSize($employee->full_name),
            ])->all();

            return view('hr::barcode.print-cards', [
                'cards' => $cards,
                'total' => $total,
                'limit' => self::MAX_PRINTED_CARDS,
                'truncated' => $total > self::MAX_PRINTED_CARDS,
            ]);

        } catch (\Exception $e) {
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'printCards');

            return back()->with('error', 'تعذّرت تهيئة البطاقات للطباعة.');
        }
    }

    /**
     * مقاسُ الاسم على البطاقة — يتبع طولَه.
     *
     * البطاقةُ ارتفاعُها ثابت، والاسمُ العربيُّ الكامل (خمس كلمات) يتجاوز
     * سطرَين بمقاسٍ واحدٍ فيُقصّ في منتصف الحرف.
     */
    private function cardNameSize(string $name): string
    {
        $length = mb_strlen($name);

        return $length <= 22 ? '3.7mm' : ($length <= 34 ? '3.2mm' : '2.8mm');
    }

    /**
     * رابطُ صورة الموظف — أو `null` إن لم يكن لها ملفٌّ فعليّ.
     *
     * **ولماذا موضعان؟** `uploadImage()` يكتب في `public_path()`، لكنّ
     * جذرَ المستودع يقوم مقامَ جذر الموقع في الاستضافة المشتركة
     * (`index.php` في الجذر و`.htaccess` يُحوّل ما لم يجده إلى `public/`) —
     * فصورٌ رُفعت في ذلك الترتيب تستقرّ في `assets/` بالجذر، وصورٌ رُفعت
     * لاحقاً تستقرّ في `public/assets/`. وفي هذا المستودع الموضعان
     * مأهولان فعلاً.
     *
     * و`public/` أوّلاً لأنها المخدومة في الحالين: خادمُ التطوير
     * (`artisan serve`) لا يرى جذرَ المستودع إطلاقاً، فملفٌّ هناك يمرّ
     * فحصَ الوجود ثم يردّ ٤٠٤ عند طلبه.
     *
     * والفحصُ في الخادم لا في المتصفّح: البطاقةُ تُطبَع، و`onerror` لا
     * يُنقذ ورقةً خرجت من الطابعة بأيقونة «صورة معطوبة» مكانَ الوجه.
     */
    private function employeePhotoUrl(Employee $employee): ?string
    {
        // المنطقُ انتقل إلى `Employee::getPhotoUrlAttribute()` ليقرأه الجميع
        // من موضعٍ واحد — الكشكُ والميدانُ والبطاقةُ المطبوعة.
        return $employee->photo_url;
    }

    /**
     * تصدير تقرير الحضور
     */
    public function exportAttendance($date, Request $request)
    {
        $date = $date ?? Carbon::today()->format('Y-m-d');
        $format = $request->query('format', 'excel');

        $attendances = $this->scopeToEmployees(AttendanceDays::with('employee.department'))
            ->whereDate('attendance_date', $date)
            ->orderBy('check_in_time')
            ->get();

        if ($format === 'excel') {
            return $this->exportToExcel($attendances, $date);
        } elseif ($format === 'pdf') {
            return $this->exportToPdf($attendances, $date);
        }

        return response()->json(['error' => 'صيغة غير مدعومة'], 400);
    }

    /**
     * تصدير لإكسل
     */
    /**
     * بناءُ صفوف التقرير — مشتركةٌ بين Excel و PDF.
     */
    private function buildExportRows($attendances): array
    {
        $rows = [];

        foreach ($attendances as $attendance) {
            $workingHours = '';

            if ($attendance->check_in_time && $attendance->check_out_time) {
                $in = Carbon::parse($attendance->attendance_date->toDateString().' '.$attendance->check_in_time);
                $out = Carbon::parse($attendance->attendance_date->toDateString().' '.$attendance->check_out_time);

                if ($out->lt($in)) {
                    $out->addDay();
                }

                $workingHours = round($in->diffInMinutes($out) / 60, 2);
            }

            $rows[] = [
                optional($attendance->employee)->full_name ?? '—',
                optional(optional($attendance->employee)->department)->name ?? '—',
                $attendance->attendance_date instanceof \DateTimeInterface
                    ? $attendance->attendance_date->format('Y-m-d')
                    : (string) $attendance->attendance_date,
                $attendance->check_in_time ?? '',
                $attendance->check_out_time ?? '',
                $workingHours,
                $this->getStatusText($attendance->status),
                $attendance->scanned_via_barcode ? 'باركود' : 'يدوي',
                $attendance->notes ?? '',
            ];
        }

        return $rows;
    }

    /**
     * تصديرٌ إلى Excel — ملفٌ فعلي.
     *
     * كانت هذه الدالّة تبني الصفوف ثم ترميها وتعيد
     * `{"message": "تم التصدير بنجاح - يتطلب تثبيت مكتبة Excel"}` — تُعلن
     * النجاح وتعترف بالنقص في الجملة نفسها. و maatwebsite/excel مثبّتةٌ في
     * المشروع منذ البداية، ويستعملها تصديرُ الموظفين في الوحدة نفسها.
     */
    private function exportToExcel($attendances, $date)
    {
        return Excel::download(
            new AttendanceDailyExport($this->buildExportRows($attendances), $date),
            'attendance-report-'.$date.'.xlsx'
        );
    }

    /**
     * تصديرٌ إلى PDF — ملفٌ فعلي عبر barryvdh/laravel-dompdf المثبّتة.
     */
    private function exportToPdf($attendances, $date)
    {
        $pdf = Pdf::loadView('hr::barcode.daily-report-pdf', [
            'rows' => $this->buildExportRows($attendances),
            'date' => $date,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('attendance-report-'.$date.'.pdf');
    }

    /**
     * تفعيل/تعطيل باركود موظف
     */
    /**
     * طريقةُ تسجيل الموظف: جهازُ الفرع بالبطاقة، أو الجوالُ من الميدان.
     *
     * العمودُ `employees.attendance_channel` يحكم كل تسجيل (`AttendanceStampService`
     * يردّ الختمَ من غير قناته) ولم تكن له شاشةٌ في النظام — كان كلُّ
     * الموظفين على «جهاز الفرع» بلا طريقةٍ لتغييره، فالتسجيلُ من الجوال
     * مغلقٌ على الجميع.
     */
    public function setEmployeeChannel(Request $request, $employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        $data = $request->validate([
            'channel' => 'required|in:branch,field,both',
            // مكانُ التسجيل من الجوال — فارغ = أي مكان مع حفظ الموقع
            'geofence_id' => 'nullable|integer|exists:attendance_geofences,id',
        ]);

        $employee = Employee::findOrFail($employeeId);
        $update = ['attendance_channel' => $data['channel']];
        if ($request->has('geofence_id')) {
            $update['attendance_geofence_id'] = $data['geofence_id'] ?? null;
        }
        $employee->update($update);

        $place = $employee->attendance_geofence_id
            ? '«'.(\App\Models\AttendanceGeofence::find($employee->attendance_geofence_id)?->name).'» فقط'
            : 'أي مكان مع حفظ موقعه';

        return response()->json([
            'success' => true,
            'message' => match ($data['channel']) {
                'field' => 'يسجّل '.$employee->full_name.' من جواله — '.$place.'.',
                'both' => 'يسجّل '.$employee->full_name.' بالبطاقة في الفرع، أو من جواله — '.$place.'.',
                default => 'يسجّل '.$employee->full_name.' بالبطاقة على جهاز الفرع.',
            },
        ]);
    }

    public function toggleEmployeeBarcode(Request $request, $employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        try {
            $employee = Employee::findOrFail($employeeId);
            $enabled = $request->input('enabled', ! $employee->barcode_enabled);

            $employee->update(['barcode_enabled' => $enabled]);

            return response()->json([
                'success' => true,
                'message' => 'تم '.($enabled ? 'تفعيل' : 'تعطيل').' الباركود بنجاح',
                'enabled' => $enabled,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'toggleEmployeeBarcode');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث حالة الباركود',
            ], 500);
        }
    }

    /**
     * حذف باركود موظف
     */
    public function deleteEmployeeBarcode($employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        try {
            $employee = Employee::findOrFail($employeeId);

            // `qr_code` عمودٌ لا وجود له في `employees` — كان يُمرَّر
            // ويُسقَط صامتاً لأنه خارج `$fillable`.
            $employee->update([
                'barcode' => null,
                'barcode_enabled' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الباركود بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'deleteEmployeeBarcode');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حذف الباركود',
            ], 500);
        }
    }

    /**
     * تقرير الحضور للموظف الواحد
     */
    public function employeeAttendanceReport($employeeId, Request $request)
    {
        $this->authorizeEmployeeScope($employeeId);

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $employee = Employee::findOrFail($employeeId);

        $attendances = AttendanceDays::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date', 'desc')
            ->get();

        $stats = [
            'total_days' => $attendances->count(),
            'present_days' => $attendances->where('status', 'present')->count(),
            'late_days' => $attendances->where('status', 'late')->count(),
            'absent_days' => $attendances->where('status', 'absent')->count(),
            'barcode_usage' => $attendances->where('scanned_via_barcode', true)->count(),
            'average_working_hours' => $attendances->where('status', '!=', 'absent')->avg('working_hours') ?? 0,
        ];

        return view('attendance.employee-report', compact('employee', 'attendances', 'stats', 'startDate', 'endDate'));
    }

    /**
     * API للحصول على معلومات الموظف السريعة
     */
    public function quickEmployeeInfo($employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        try {
            $employee = Employee::with(['department', 'todayAttendance'])->findOrFail($employeeId);

            return response()->json([
                'success' => true,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'department' => $employee->department->name ?? 'غير محدد',
                    'photo' => $employee->employee_photo,
                    'barcode' => $employee->barcode,
                    'barcode_enabled' => $employee->barcode_enabled,
                    'today_status' => $employee->today_attendance_status,
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'quickEmployeeInfo');

            return response()->json([
                'success' => false,
                'message' => 'الموظف غير موجود',
            ], 404);
        }
    }

    /**
     * إحصائيات مفصلة للإدارة
     */
    public function detailedStats(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $attendances = $this->scopeToEmployees(AttendanceDays::with('employee.department'))
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        $stats = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1,
            ],
            'attendance' => [
                'total_records' => $attendances->count(),
                'present_count' => $attendances->where('status', 'present')->count(),
                'late_count' => $attendances->where('status', 'late')->count(),
                'absent_count' => $attendances->where('status', 'absent')->count(),
            ],
            'methods' => [
                'barcode_usage' => $attendances->where('scanned_via_barcode', true)->count(),
                'manual_usage' => $attendances->where('scanned_via_barcode', false)->count(),
                'barcode_percentage' => $attendances->count() > 0 ?
                    round(($attendances->where('scanned_via_barcode', true)->count() / $attendances->count()) * 100, 2) : 0,
            ],
            'departments' => $attendances->groupBy('employee.department.name')->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'present' => $group->where('status', 'present')->count(),
                    'late' => $group->where('status', 'late')->count(),
                ];
            }),
            'working_hours' => [
                'average' => round($attendances->where('status', '!=', 'absent')->avg('working_hours') ?? 0, 2),
                'total_minutes' => $attendances->where('status', '!=', 'absent')->sum('working_hours'),
                'max_hours' => $attendances->max('working_hours') ?? 0,
                'min_hours' => $attendances->where('working_hours', '>', 0)->min('working_hours') ?? 0,
            ],
        ];

        return response()->json($stats);
    }

    /**
     * داشبورد الحضور الرئيسي
     */
    public function dashboard()
    {
        $today = Carbon::today();

        // إحصائيات اليوم
        $todayStats = [
            'present' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $today))
                ->where('status', 'present')
                ->count(),
            'late' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $today))
                ->where('status', 'late')
                ->count(),
            'total_employees' => $this->scopeToEmployees(Employee::query(), 'id')->count(),
            'barcode_scans' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $today))
                ->where('scanned_via_barcode', true)
                ->count(),
        ];

        // آخر العمليات
        $recentActivities = $this->scopeToEmployees(AttendanceDays::with('employee'))
            ->whereDate('attendance_date', $today)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        // الموظفين الحاضرين حالياً
        $currentlyPresent = $this->scopeToEmployees(AttendanceDays::with('employee.department'))
            ->whereDate('attendance_date', $today)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->get();

        return view('attendance.dashboard', compact('todayStats', 'recentActivities', 'currentlyPresent'));
    }

    /**
     * تحويل حالة النص
     */
    private function getStatusText($status)
    {
        switch ($status) {
            case 'present': return 'حاضر';
            case 'late': return 'متأخر';
            case 'absent': return 'غائب';
            case 'half_day': return 'نصف يوم';
            default: return 'غير محدد';
        }
    }

    /**
     * التحقق من صحة الباركود
     */
    private function validateBarcode($barcode)
    {
        // التحقق من الصيغة
        if (! preg_match('/^EMP_\d+_[A-Za-z0-9]{8}$/', $barcode)) {
            return false;
        }

        // استخراج معرف الموظف
        $parts = explode('_', $barcode);
        if (count($parts) !== 3) {
            return false;
        }

        $employeeId = $parts[1];

        // التحقق من وجود الموظف
        return Employee::where('id', $employeeId)
            ->where('barcode', $barcode)
            ->exists();
    }

    /**
     * إحصائيات الاستخدام الأسبوعية
     */
    public function weeklyUsageStats()
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $dailyStats = [];

        for ($date = $startOfWeek->copy(); $date <= $endOfWeek; $date->addDay()) {
            $dayAttendances = $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', $date))->get();

            $dailyStats[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->translatedFormat('l'),
                'total_attendances' => $dayAttendances->count(),
                'barcode_scans' => $dayAttendances->where('scanned_via_barcode', true)->count(),
                'manual_entries' => $dayAttendances->where('scanned_via_barcode', false)->count(),
                'late_arrivals' => $dayAttendances->where('status', 'late')->count(),
            ];
        }

        return response()->json([
            'week_period' => [
                'start' => $startOfWeek->format('Y-m-d'),
                'end' => $endOfWeek->format('Y-m-d'),
            ],
            'daily_stats' => $dailyStats,
            'week_totals' => [
                'total_scans' => collect($dailyStats)->sum('barcode_scans'),
                'total_manual' => collect($dailyStats)->sum('manual_entries'),
                'total_late' => collect($dailyStats)->sum('late_arrivals'),
            ],
        ]);
    }

    /**
     * إرسال تقرير يومي بالإيميل
     */
    /*
        حُذفت emailDailyReport.

        كانت تجمع الإحصاءات ثم:

            // هنا يمكنك إرسال الإيميل
            // Mail::to($emails)->send(new AttendanceDaysReport(...));
            return response()->json(['success' => true,
                'message' => 'تم إرسال التقرير بالإيميل بنجاح']);

        سطرُ الإرسال معطَّلٌ والرسالة تُبشِّر بنجاحه — والصنف
        AttendanceDaysReport غير موجودٍ أصلاً في app/Mail.

        ولم يكن لها مسارٌ في routes: غير قابلةٍ للاستدعاء، ولا قالبَ
        يطلبها. إن أُريد إرسالُ التقرير يومياً بالبريد فالبنية جاهزة
        (قالب hr::barcode.daily-report-pdf + MailerConfigurator)، ويُكتب
        عندها Mailable حقيقيّ ومسارٌ وزرّ — لا تُعاد هذه.
    */

    /**
     * النسخ الاحتياطي للبيانات
     */
    public function backupAttendanceData(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now());

        $data = [
            'backup_date' => Carbon::now()->toISOString(),
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'employees' => Employee::select('id', 'first_name', 'middle_name', 'nickname', 'barcode')
                ->get(),
            'attendances' => $this->scopeToEmployees(AttendanceDays::whereBetween('attendance_date', [$startDate, $endDate]))
                ->get(),
            'stats' => $this->calculatePeriodStats($startDate, $endDate),
        ];

        $filename = 'attendance-backup-'.Carbon::now()->format('Y-m-d-H-i-s').'.json';

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * حساب إحصائيات فترة معينة
     */
    private function calculatePeriodStats($startDate, $endDate)
    {
        $attendances = $this->scopeToEmployees(AttendanceDays::whereBetween('attendance_date', [$startDate, $endDate]))->get();

        return [
            'total_records' => $attendances->count(),
            'unique_employees' => $attendances->pluck('employee_id')->unique()->count(),
            'barcode_usage_percentage' => $attendances->count() > 0 ?
                round(($attendances->where('scanned_via_barcode', true)->count() / $attendances->count()) * 100, 2) : 0,
            'average_daily_attendance' => $attendances->groupBy('attendance_date')->avg(function ($day) {
                return $day->count();
            }),
            'peak_attendance_day' => $attendances->groupBy('attendance_date')
                ->sortByDesc(function ($day) {
                    return $day->count();
                })
                ->keys()
                ->first(),
            'departments_stats' => $attendances->groupBy('employee.department_id')
                ->map(function ($group) {
                    return [
                        'total' => $group->count(),
                        'barcode_usage' => $group->where('scanned_via_barcode', true)->count(),
                    ];
                }),
        ];
    }

    /**
     * تشخيص النظام
     */
    public function systemDiagnostics()
    {
        $diagnostics = [
            'database' => [
                'employees_total' => $this->scopeToEmployees(Employee::query(), 'id')->count(),
                'employees_with_barcode' => $this->scopeToEmployees(Employee::query(), 'id')->whereNotNull('barcode')->count(),
                'employees_barcode_enabled' => $this->scopeToEmployees(Employee::query(), 'id')->where('barcode_enabled', true)->count(),
                'today_attendance_records' => $this->scopeToEmployees(AttendanceDays::whereDate('attendance_date', today()))->count(),
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'timezone' => config('app.timezone'),
                'current_time' => Carbon::now()->toISOString(),
            ],
            'camera_support' => [
                'user_agent' => request()->userAgent(),
                'supports_camera' => true, // سيتم التحقق في Frontend
            ],
            'recent_errors' => $this->getRecentErrors(),
        ];

        return response()->json($diagnostics);
    }

    /**
     * الحصول على الأخطاء الحديثة
     */
    private function getRecentErrors()
    {
        // يمكن قراءة آخر الأخطاء من ملف اللوج
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return ['لا توجد أخطاء مسجلة'];
        }

        $lines = file($logPath);
        $recentLines = array_slice($lines, -50); // آخر 50 سطر

        $errors = [];
        foreach ($recentLines as $line) {
            if (strpos($line, 'ERROR') !== false && strpos($line, 'attendance') !== false) {
                $errors[] = trim($line);
            }
        }

        return array_slice($errors, -5); // آخر 5 أخطاء
    }

    /**
     * تنظيف البيانات القديمة
     */
    public function cleanupOldData(Request $request)
    {
        $daysToKeep = $request->input('days', 90); // الاحتفاظ بـ 90 يوم افتراضياً
        $cutoffDate = Carbon::now()->subDays($daysToKeep);

        try {
            $deletedCount = AttendanceDays::where('attendance_date', '<', $cutoffDate)->delete();

            Log::info('تنظيف البيانات القديمة', [
                'cutoff_date' => $cutoffDate,
                'deleted_records' => $deletedCount,
                'performed_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$deletedCount} سجل قديم",
                'deleted_count' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.BarcodeAttendance', 'cleanupOldData');

            return response()->json([
                'success' => false,
                'message' => 'فشل في تنظيف البيانات',
            ], 500);
        }
    }

    /**
     * إعدادات النظام
     */
    public function getSystemSettings()
    {
        return response()->json([
            'default_shift_start' => '08:00',
            'default_shift_end' => '17:00',
            'late_threshold_minutes' => 15,
            'barcode_format' => 'CODE128',
            'auto_logout_hours' => 12,
            'backup_frequency_days' => 7,
            'cleanup_after_days' => 90,
        ]);
    }

    /**
     * تحديث إعدادات النظام
     */
    public function updateSystemSettings(Request $request)
    {
        $request->validate([
            'default_shift_start' => 'required|date_format:H:i',
            'default_shift_end' => 'required|date_format:H:i|after:default_shift_start',
            'late_threshold_minutes' => 'required|integer|min:0|max:60',
            'auto_logout_hours' => 'required|integer|min:1|max:24',
        ]);

        // حفظ الإعدادات في ملف config أو قاعدة البيانات
        // Config::set('attendance.settings', $request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الإعدادات بنجاح',
        ]);
    }
}
