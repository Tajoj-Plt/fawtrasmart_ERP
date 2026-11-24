<?php

namespace Modules\HR\Http\Controllers\Attendance\Settings;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceGeofence;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\Attendance\OfficeCode;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * أماكنُ التسجيل وأجهزةُ تسجيل الحضور في الفروع.
 *
 * كانتا شاشةً واحدةً تُضاف فيها وتُعدَّل النطاقاتُ والأجهزة في صناديق
 * منسدلة. صار لكلٍّ منهما قائمةٌ وإضافةٌ وعرضٌ وتعديل كبقيّة شاشات النظام:
 * المكانُ يُرى على الخريطة مع أجهزته وآخر التسجيلات فيه، والجهازُ يُرى
 * برمز ربطه وحالته وآخر ما مُسح عليه.
 */
class GeofencesController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        // «أماكن التسجيل وأجهزة الفروع» — مجموعتُها لا «قواعد الحضور» (انظر HrPermissions)
        $this->middleware('permission.role:attendance_places.index', ['only' => ['index', 'kioskIndex']]);
        $this->middleware('permission.role:attendance_places.show', ['only' => ['show', 'kioskShow', 'printOfficeCode']]);
        $this->middleware('permission.role:attendance_places.create', ['only' => ['create', 'storeGeofence', 'kioskCreate', 'storeKiosk']]);
        $this->middleware('permission.role:attendance_places.edit', ['only' => ['edit', 'updateGeofence', 'kioskEdit', 'updateKiosk', 'rotateKioskCode', 'releaseKioskDevice', 'rotateOfficeCode', 'attachKioskEmployees', 'detachKioskEmployee']]);
        $this->middleware('permission.role:attendance_places.destroy', ['only' => ['destroyGeofence', 'destroyKiosk']]);
    }

    // ════════════════════════ أماكن التسجيل ════════════════════════

    public function index(Request $request)
    {
        $query = AttendanceGeofence::with('branch')->withCount('kiosks');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $geofences = $query->orderBy('name')->paginate((int) $request->input('per_page', 10))->withQueryString();

        return view('hr::attendance.settings.geofences.index', [
            'geofences' => $geofences,
            'stats' => [
                'total' => AttendanceGeofence::count(),
                'active' => AttendanceGeofence::where('is_active', true)->count(),
                'kiosks' => AttendanceKiosk::count(),
                'today' => AttendancePunch::whereNotNull('geofence_id')->whereDate('punched_at', today())->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('hr::attendance.settings.geofences.create');
    }

    public function storeGeofence(Request $request)
    {
        $geofence = AttendanceGeofence::create($this->validateGeofence($request));

        return redirect()->route('attendance.settings.geofences.show', $geofence->id)
            ->with('success', 'تم حفظ المكان «'.$geofence->name.'».');
    }

    public function show($id)
    {
        $geofence = AttendanceGeofence::with(['branch', 'kiosks'])->findOrFail($id);

        $punches = AttendancePunch::with(['employee', 'kiosk'])
            ->where('geofence_id', $geofence->id)
            ->latest('punched_at')
            ->limit(15)
            ->get();

        return view('hr::attendance.settings.geofences.show', [
            'geofence' => $geofence,
            'punches' => $punches,
            'today' => [
                'accepted' => AttendancePunch::where('geofence_id', $geofence->id)->whereDate('punched_at', today())->where('result', AttendancePunch::RESULT_ACCEPTED)->count(),
                'rejected' => AttendancePunch::where('geofence_id', $geofence->id)->whereDate('punched_at', today())->where('result', AttendancePunch::RESULT_REJECTED)->count(),
            ],
        ]);
    }

    public function edit($id)
    {
        return view('hr::attendance.settings.geofences.edit', [
            'geofence' => AttendanceGeofence::findOrFail($id),
        ]);
    }

    public function updateGeofence(Request $request, $id)
    {
        $geofence = AttendanceGeofence::findOrFail($id);
        $geofence->update($this->validateGeofence($request));

        return redirect()->route('attendance.settings.geofences.show', $geofence->id)
            ->with('success', 'تم حفظ التعديلات.');
    }

    public function destroyGeofence($id)
    {
        try {
            $geofence = AttendanceGeofence::findOrFail($id);

            /*
                الأختامُ تُشير إلى المكان بـ`geofence_id`، وهي سجلٌّ تدقيقيٌّ لا
                يُعدَّل ولا يُحذف. فمكانٌ عليه أجهزةٌ أو تسجيلاتٌ يُوقَف لا يُحذف.
            */
            if ($geofence->kiosks()->exists()) {
                return back()->with('error', 'لا يمكن حذف مكانٍ فيه أجهزة — انقل الأجهزة أو أوقف المكان بدل حذفه.');
            }

            if (AttendancePunch::where('geofence_id', $geofence->id)->exists()) {
                return back()->with('error', 'لا يمكن حذف مكانٍ سُجّل فيه حضور — أوقفه من «تعديل» بدل حذفه، فتبقى تسجيلاته السابقة.');
            }

            $geofence->delete();

            return redirect()->route('attendance.settings.geofences.index')->with('success', 'تم حذف المكان.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'HR.Geofences', 'destroyGeofence');

            return back()->with('error', 'تعذّر حذف المكان.');
        }
    }

    // ════════════════════════ أجهزة التسجيل ════════════════════════

    public function kioskIndex(Request $request)
    {
        $query = AttendanceKiosk::with(['branch', 'geofence']);

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->filled('geofence_id')) {
            $query->where('geofence_id', $request->geofence_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $kiosks = $query->orderBy('name')->paginate((int) $request->input('per_page', 10))->withQueryString();

        return view('hr::attendance.settings.kiosks.index', [
            'kiosks' => $kiosks,
            'geofences' => AttendanceGeofence::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => AttendanceKiosk::count(),
                'active' => AttendanceKiosk::where('is_active', true)->count(),
                'linked' => AttendanceKiosk::whereNotNull('last_seen_at')->count(),
                'silent' => AttendanceKiosk::where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subHours(3)))
                    ->count(),
            ],
        ]);
    }

    public function kioskCreate()
    {
        return view('hr::attendance.settings.kiosks.create', [
            'geofences' => AttendanceGeofence::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeKiosk(Request $request)
    {
        $kiosk = AttendanceKiosk::create($this->validateKiosk($request));

        return redirect()->route('attendance.settings.kiosks.show', $kiosk->id)
            ->with('success', 'تم حفظ الجهاز. رمز الربط: '.$kiosk->pairing_code);
    }

    public function kioskShow($id)
    {
        $kiosk = AttendanceKiosk::with(['branch', 'geofence'])->findOrFail($id);

        $punches = AttendancePunch::with('employee')
            ->where('kiosk_id', $kiosk->id)
            ->latest('punched_at')
            ->limit(15)
            ->get();

        /*
            الموظفون المربوطون بالجهاز — لا يُقبل مسحُهم إلا على أجهزتهم.
            والمرشَّحون للإضافة: موظفو البطاقة النشطون في نطاق المستخدم ممن
            لم يُربطوا به بعد.
        */
        $linked = $this->scopeToEmployees($kiosk->employees()->getQuery(), 'employees.id')
            ->with(['department:id,name', 'branch:id,name', 'attendanceKiosks:id,name'])
            ->orderBy('first_name')
            ->get(['employees.id', 'employees.first_name', 'employees.middle_name', 'employees.nickname', 'employees.employee_code', 'employees.department_id', 'employees.branch_id', 'employees.attendance_channel', 'employees.barcode']);

        $candidates = $this->scopeToEmployees(Employee::query(), 'id')
            ->whereIn('status', EmploymentStatus::activeValues())
            ->where(fn ($q) => $q->whereNull('attendance_channel')->orWhereIn('attendance_channel', ['branch', 'both']))
            ->whereNotIn('id', $linked->pluck('id'))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'nickname', 'employee_code']);

        return view('hr::attendance.settings.kiosks.show', [
            'kiosk' => $kiosk,
            'punches' => $punches,
            'linked' => $linked,
            'candidates' => $candidates,
            'today' => [
                'accepted' => AttendancePunch::where('kiosk_id', $kiosk->id)->whereDate('punched_at', today())->where('result', AttendancePunch::RESULT_ACCEPTED)->count(),
                'rejected' => AttendancePunch::where('kiosk_id', $kiosk->id)->whereDate('punched_at', today())->where('result', AttendancePunch::RESULT_REJECTED)->count(),
            ],
        ]);
    }

    public function kioskEdit($id)
    {
        return view('hr::attendance.settings.kiosks.edit', [
            'kiosk' => AttendanceKiosk::findOrFail($id),
            'geofences' => AttendanceGeofence::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateKiosk(Request $request, $id)
    {
        $kiosk = AttendanceKiosk::findOrFail($id);
        $kiosk->update($this->validateKiosk($request));

        return redirect()->route('attendance.settings.kiosks.show', $kiosk->id)
            ->with('success', 'تم حفظ التعديلات.');
    }

    /**
     * رمزُ ربطٍ جديد — جهازٌ فُقِد أو خرج من الفرع. القديمُ يتوقف فوراً.
     */
    /** ربطُ موظفين بالجهاز — يُضافون إلى أجهزتهم الحالية ولا تُستبدل */
    public function attachKioskEmployees(Request $request, $id)
    {
        $kiosk = AttendanceKiosk::findOrFail($id);
        $data = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
        ], ['employee_ids.required' => 'اختر موظفاً واحداً على الأقل.']);

        $ids = array_values(array_unique(array_map('intval', $data['employee_ids'])));
        foreach ($ids as $employeeId) {
            $this->authorizeEmployeeScope($employeeId);
        }

        // الميدانيُّ لا يمسح على جهاز — يُحوَّل إلى «الاثنين» فلا يُربط بجهازٍ لا يُقبل عليه أصلاً
        Employee::whereKey($ids)->where('attendance_channel', 'field')->update(['attendance_channel' => 'both']);
        $kiosk->employees()->syncWithoutDetaching($ids);

        return back()->with('success', 'رُبط '.count($ids).' موظف بهذا الجهاز — لا يُقبل مسحُهم إلا على أجهزتهم المربوطة.');
    }

    /** فكُّ موظفٍ من الجهاز — إن لم يبقَ له جهاز يُقبل على أيّ جهاز */
    public function detachKioskEmployee($id, $employeeId)
    {
        $kiosk = AttendanceKiosk::findOrFail($id);
        $this->authorizeEmployeeScope((int) $employeeId);
        $kiosk->employees()->detach((int) $employeeId);

        $left = DB::table('attendance_kiosk_employee')->where('employee_id', (int) $employeeId)->count();

        return back()->with('success', $left
            ? 'فُكّ الموظف من هذا الجهاز، وبقي مربوطاً بأجهزته الأخرى.'
            : 'فُكّ الموظف من هذا الجهاز — لا جهاز مربوط له الآن، فيُقبل مسحه على أي جهاز.');
    }

    public function rotateKioskCode($id)
    {
        $kiosk = AttendanceKiosk::findOrFail($id);
        $kiosk->update(['pairing_code' => AttendanceKiosk::generatePairingCode()]);
        // رمزٌ جديد = شاشةٌ جديدة: الشاشةُ المقترنةُ حالياً تسقط
        $kiosk->releaseDevice();

        return back()->with('success', 'رمز الربط الجديد: '.$kiosk->pairing_code.' — الرمز القديم والشاشة المقترنة لم يعودا يعملان. أدخل الرمز على شاشة الفرع.');
    }

    /** صفحةُ طباعة «رمز المكتب» الثابت */
    public function printOfficeCode($id)
    {
        $kiosk = AttendanceKiosk::with('geofence')->findOrFail($id);
        $code = app(OfficeCode::class)->printedCode($kiosk);
        abort_unless($code, 404, 'الرمز المطبوع غير مفعّل لهذا الجهاز.');

        return view('hr::attendance.settings.kiosks.office-code-print', [
            'kiosk' => $kiosk,
            'svg' => (string) QrCode::format('svg')->size(420)->margin(1)->errorCorrection('M')->generate($code),
        ]);
    }

    /** رمزٌ مطبوعٌ جديد — يُبطل الملصقَ القديم فوراً */
    public function rotateOfficeCode($id)
    {
        $kiosk = AttendanceKiosk::findOrFail($id);
        $kiosk->increment('office_qr_version');

        return back()->with('success', 'صدر رمز مطبوع جديد — الملصق القديم لم يعد يعمل. اطبع الجديد وعلّقه.');
    }

    /** فكُّ اقتران الشاشة — بلا تغيير الرمز، لنقل الجهاز إلى شاشةٍ أخرى */
    public function releaseKioskDevice($id)
    {
        $kiosk = AttendanceKiosk::findOrFail($id);
        $kiosk->releaseDevice();

        return back()->with('success', 'فُكّ اقتران الشاشة — أدخل رمز الربط على الشاشة الجديدة.');
    }

    public function destroyKiosk($id)
    {
        $kiosk = AttendanceKiosk::findOrFail($id);

        if (AttendancePunch::where('kiosk_id', $kiosk->id)->exists()) {
            return back()->with('error', 'لا يمكن حذف جهازٍ سُجّل عليه حضور — أوقفه من «تعديل» بدل حذفه، فتبقى تسجيلاته السابقة.');
        }

        $kiosk->delete();

        return redirect()->route('attendance.settings.kiosks.index')->with('success', 'تم حذف الجهاز.');
    }

    // ════════════════════════ التحقق ════════════════════════

    private function validateGeofence(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:20|max:50000',
            'max_accuracy_meters' => 'required|integer|min:10|max:5000',
            'allowed_ip_ranges' => 'nullable|string',
            'allowed_wifi_ssids' => 'nullable|string',
            'require_geo' => 'nullable|boolean',
            'require_network' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ], [], [
            'name' => 'اسم المكان',
            'radius_meters' => 'نصف قطر المكان',
            'max_accuracy_meters' => 'أقل دقة مقبولة',
        ]);

        $ranges = $this->splitList($request->input('allowed_ip_ranges'));

        /*
            مدىً بصيغةٍ خاطئة لا يُطابق شيئاً أبداً — فيبدو الإعدادُ مضبوطاً
            ويردّ كلَّ تسجيلٍ من المقر بلا سببٍ ظاهر. يُرفض عند الإدخال.
        */
        foreach ($ranges as $range) {
            if (! $this->isParsableRange($range)) {
                throw ValidationException::withMessages([
                    'allowed_ip_ranges' => "«{$range}» ليس عنوان شبكة صحيحاً.",
                ]);
            }
        }

        $validated['allowed_ip_ranges'] = $ranges ?: null;
        $validated['allowed_wifi_ssids'] = $this->splitList($request->input('allowed_wifi_ssids')) ?: null;
        $validated['require_geo'] = $request->boolean('require_geo');
        $validated['require_network'] = $request->boolean('require_network');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['office_qr'] = $validated['office_qr'] ?? 'off';

        // المطبوعُ يُقبل بموقع الجوال داخل المكان — فلا معنى له بلا مكان
        if (in_array($validated['office_qr'], ['printed', 'both'], true) && empty($validated['geofence_id'])) {
            throw ValidationException::withMessages([
                'office_qr' => 'الرمز المطبوع يحتاج «المكان الموجود فيه» الجهاز — يُقبل فقط وموقع الجوال داخل هذا المكان.',
            ]);
        }

        return $validated;
    }

    private function isParsableRange(string $range): bool
    {
        if (! str_contains($range, '/')) {
            return filter_var($range, FILTER_VALIDATE_IP) !== false;
        }

        [$subnet, $bits] = explode('/', $range, 2);

        $binary = @inet_pton(trim($subnet));

        if ($binary === false || ! ctype_digit(trim($bits))) {
            return false;
        }

        return (int) $bits >= 0 && (int) $bits <= strlen($binary) * 8;
    }

    private function validateKiosk(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'geofence_id' => 'nullable|exists:attendance_geofences,id',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
            'office_qr' => 'nullable|in:'.implode(',', array_keys(OfficeCode::MODES)),
            'require_location' => 'nullable|boolean',
        ], [], [
            'name' => 'اسم الجهاز',
            'geofence_id' => 'المكان',
            'require_location' => 'اشتراط الموقع على الجهاز',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['require_location'] = $request->boolean('require_location');

        /*
            اشتراطُ الموقع بلا مكانٍ مربوطٍ لا معنى له: الحارسُ يقيس المسافةَ
            إلى دائرة المكان، فبلا مكانٍ لا دائرةَ تُقاس — ويُقاس الموظفُ على
            مكان فرعه أو على أيّ مكانٍ عامّ، وهو غيرُ ما قصده من ضبط الجهاز.
        */
        if ($validated['require_location'] && empty($validated['geofence_id'])) {
            throw ValidationException::withMessages([
                'require_location' => 'اشتراطُ الموقع يحتاج «المكان الموجود فيه» الجهاز — اختره أولاً.',
            ]);
        }

        return $validated;
    }

    /** يفصل قائمةً مكتوبةً بسطورٍ أو فواصل إلى مصفوفةٍ نظيفة. */
    private function splitList(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[\r\n,]+/', $raw) ?: []
        ), fn ($value) => $value !== ''));
    }
}
