<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequestRequest;
use App\Http\Requests\AttendanceDaysRequest;
use App\Models\AttendanceDays;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JopTitle;
use App\Models\Log as ModelsLog;
use App\Models\Shift;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\Concerns\ResolvesEmployeesByRules;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Http\Controllers\Concerns\SubmitsAttendanceCorrectionRequests;
use Modules\HR\Support\BranchTree;

class AttendanceDaysController extends Controller
{
    use ResolvesEmployeesByRules;
    use ScopesToAccessibleEmployees;
    use SubmitsAttendanceCorrectionRequests;

    public function __construct()
    {
        $this->middleware('permission.role:attendance_days.index', ['only' => ['index', 'filter']]);
        $this->middleware('permission.role:attendance_days.show', ['only' => ['show']]);
        // إدخالُ يومٍ يدوياً أو حذفُه تعديلٌ على أيام الحضور
        $this->middleware('permission.role:attendance_days.edit', ['only' => ['create', 'delete', 'store']]);
        // شاشةُ «تعديل اليوم» لا تكتب — تُقدِّم طلبَ تصحيح
        $this->middleware('permission.role:attendance_corrections.create', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:attendance_days.calculate', ['only' => [
            'calculateWorkHours', 'calculation', 'runCalculation',
        ]]);
    }

    /*
        القائمةُ تُرقَّم ولا تُجلب كاملة.

        كان `->get()` بلا حدٍّ ولا مرشِّحِ تاريخ، وجدولُ الأيام ينمو بعدد
        الموظفين × عدد الأيام — صفٌّ لكلٍّ منهم كلَّ يوم، يولّدها الكرون
        للغياب أيضاً. فمنشأةٌ بمئة موظفٍ تبلغ ستةً وثلاثين ألفَ صفٍّ في
        السنة، تُحمَّل كلُّها في الذاكرة وتُرسَل إلى المتصفّح دفعةً واحدة.

        والقالبُ كان فيه مُرقِّمٌ كاملٌ لا يظهر أبداً: يقرأ `$attendanceDays`
        والمتحكّمُ يُرسل `$attendance_days` — فـ`isset()` كاذبةٌ دائماً،
        ومُنتقي «عدد السجلات» (`per_page`) معروضٌ ولا يفعل شيئاً.
    */
    public function index(Request $request)
    {
        // جلب البيانات ضمن نطاق الموظفين المسموح للمستخدم الحالي برؤيتهم
        $query = AttendanceDays::with('employee.department', 'employee.branch')
            ->orderBy('id', 'DESC');

        $this->scopeToEmployees($query);

        $attendance_days = $query->paginate($this->perPage($request))->withQueryString();

        // جلب الأقسام والفروع للـ select
        $departments = Department::all();
        $branches = Branch::all();

        return view('hr::attendance.attendance_days.index', compact('attendance_days', 'departments', 'branches'));
    }

    /** حدٌّ أعلى للصفحة: `per_page=100000` في العنوان يُعيد الجلبَ الكامل من الباب الخلفي. */
    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 5), 200);
    }

    public function filter(Request $request)
    {
        $query = AttendanceDays::with('employee.department', 'employee.branch');

        // نطاقُ الموظفين: الصلاحيةُ تفتح الشاشة، والنطاقُ يحدّد بياناتِ مَن فيها.
        $this->scopeToEmployees($query);

        // ✅ فلترة بواسطة الموظف (اسم أو كود)
        if ($request->filled('keywords')) {
            $keywords = $request->keywords;
            $query->whereHas('employee', function ($q) use ($keywords) {
                $q->where('first_name', 'LIKE', "%{$keywords}%")
                    ->orWhere('id', 'LIKE', "%{$keywords}%");
            });
        }

        // ✅ فلترة بواسطة التاريخ من
        if ($request->filled('from_date')) {
            $query->where('attendance_date', '>=', $request->from_date);
        }

        // ✅ فلترة بواسطة التاريخ إلى
        if ($request->filled('to_date')) {
            $query->where('attendance_date', '<=', $request->to_date);
        }

        // ✅ فلترة بواسطة الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ فلترة بواسطة القسم (يدعم id أو نص)
        if ($request->filled('department')) {
            $department = $request->department;
            $query->whereHas('employee.department', function ($q) use ($department) {
                $q->where('id', $department) // لو جاي id من select
                    ->orWhere('name', 'LIKE', "%{$department}%"); // لو نص مكتوب يدوي
            });
        }

        // ✅ فلترة بواسطة الفرع (يدعم id أو نص)
        if ($request->filled('branch')) {
            $branch = $request->branch;
            // الجهةُ المختارة وما تحتها
            $query->whereHas('employee', fn ($q) => BranchTree::apply($q, $branch));
        }

        $attendance_days = $query->orderBy('id', 'DESC')
            ->paginate($this->perPage($request))
            ->withQueryString();

        // ✅ إرجاع البيانات كـ JSON للـ AJAX
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $attendance_days->items(),
                'html' => view('hr::attendance.attendance_days.table_rows', compact('attendance_days'))->render(),
                // القالبُ يستبدل `#pagination-section` بهذا — وكان لا يصله شيء
                'pagination' => view('hr::attendance.attendance_days.pagination', compact('attendance_days'))->render(),
            ]);
        }

        // العرضُ نفسه الذي يعرضه index()، وهو يمرّ على $departments و$branches
        // لبناء قائمتَي التصفية. إغفالُهما هنا كان يرمي «Undefined variable
        // $departments» على كل طلبٍ غير-AJAX يصل إلى /attendance-days/filter.
        $departments = Department::all();
        $branches = Branch::all();

        return view('hr::attendance.attendance_days.index', compact('attendance_days', 'departments', 'branches'));
    }

    public function create()
    {
        // المنتقي من نطاق المستخدم وفرع الشريط معاً
        $employees = $this->scopeToEmployees(Employee::query(), 'id')->select('id', 'first_name', 'middle_name')->get();

        return view('hr::attendance.attendance_days.create', compact('employees'));
    }

    /**
     * إضافةُ يومٍ يدوياً = طلبُ تصحيحٍ ينتظر الاعتماد، كالتعديل تماماً.
     *
     * كانت تكتب اليومَ مباشرةً — حاضراً بأيّ وقتٍ ولأيّ تاريخ، حتى للغد —
     * فيتجاوز حاملُ صلاحيةٍ واحدة نافذةَ الحضور والتصحيحَ والاعتمادَ معاً،
     * ويؤثّر على الخصم والراتب فوراً بلا أثر مراجعة.
     */
    public function store(AttendanceDaysRequest $request)
    {
        $correction = AttendanceCorrectionRequestRequest::createFrom($request);
        $correction->merge([
            'requested_status' => $request->status,
            'requested_start_shift' => $request->start_shift,
            'requested_end_shift' => $request->end_shift,
            'requested_check_in_time' => $request->check_in_time,
            'requested_check_out_time' => $request->check_out_time,
            'requested_absence_type' => $request->absence_type,
            'requested_absence_balance' => $request->absence_balance,
            'requested_notes' => $request->notes,
            'reason' => $request->notes ?: 'إدخال يوم حضور يدوياً من شاشة الحضور اليومي',
        ]);

        $created = $this->submitCorrectionRequest($correction, 'تم تقديم طلب إدخال يوم حضور يدوياً');

        return redirect()
            ->route('hr.attendance-corrections.show', $created->id)
            ->with('success', 'أُرسل اليوم كطلب تصحيح — يُعتمد من «اعتماد التصحيحات» ثم يظهر في الحضور اليومي.');
    }

    public function edit($id)
    {
        $employees = $this->scopedEmployeeOptions();
        $attendanceDay = AttendanceDays::findOrFail($id);

        $this->authorizeEmployeeScope($attendanceDay->employee_id);

        return view('hr::attendance.attendance_days.edit', compact('employees', 'attendanceDay'));
    }

    /**
     * لم يعد هذا يكتب على attendance_days مباشرة — يُقدِّم طلب تصحيح
     * ينتظر اعتماداً، بنفس منطق شاشة الطلب المخصَّصة
     * (SubmitsAttendanceCorrectionRequests). كان أيّ حاملِ صلاحيةٍ واحدة
     * (`attendance_days.edit`) يُعدِّل ويؤثّر على حساب الراتب فوراً
     * بلا مراجعة ولا أثر تدقيق.
     */
    public function update(AttendanceCorrectionRequestRequest $request, $id)
    {
        // تأكيدٌ أن الرابط يشير إلى يومٍ موجود فعلاً؛ الطلبُ نفسُه يستهدف
        // (employee_id, attendance_date) المُرسَلين من النموذج — قد يختلفان
        // عن هذا الصفّ إن غيّرهما المستخدم، وهو احتمالٌ كان قائماً أصلاً.
        $day = AttendanceDays::findOrFail($id);
        $this->authorizeEmployeeScope($day->employee_id);

        $correction = $this->submitCorrectionRequest($request, 'تم تقديم طلب تصحيح حضور من شاشة تعديل يوم الحضور');

        return redirect()
            ->route('hr.attendance-corrections.show', $correction->id)
            ->with('success', 'تم تقديم طلب التصحيح بنجاح — بانتظار الاعتماد. لن يُطبَّق التعديل على السجل حتى يُعتمَد.');
    }

    public function show($id)
    {
        // Fetch the selected attendance day
        $attendance_day = AttendanceDays::with(['checkInGeofence:id,name', 'checkOutGeofence:id,name'])
            ->findOrFail($id);

        // السجل يُطلب بمعرّفٍ مباشر؛ التقييد في index() وحده لا يمنع الوصول هنا.
        $this->authorizeEmployeeScope($attendance_day->employee_id);

        // Retrieve activity logs associated with this record.  The original
        // implementation attempted to call a nonexistent relationship
        // (attendance_days_log) which would cause an error.  Instead we load
        // the user on each log entry and group by date.
        $logs = ModelsLog::where('type', 'attendance_days_log')
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        // Build a summary of attendance days for the same employee.  This
        // summary powers the "سجل الحضور الشامل" tab in the view.  It
        // defaults to the current year unless a specific year/month filter
        // is provided via the request.  Using whereYear/whereMonth ensures
        // proper filtering and ordering.
        $year = request('filter_year');
        // If no year provided default to current year
        if (empty($year)) {
            $year = now()->year;
        }
        $month = request('filter_month');
        $attendanceRecordsQuery = AttendanceDays::where('employee_id', $attendance_day->employee_id)
            ->whereYear('attendance_date', $year);
        if (! empty($month)) {
            $attendanceRecordsQuery->whereMonth('attendance_date', $month);
        }
        $attendanceRecords = $attendanceRecordsQuery->orderBy('attendance_date', 'desc')->get();

        /*
            حركاتُ هذا اليوم كما وقعت — من سجل الأختام لا من «الجلسات».

            كانت تُقرأ من `attendance_records`، وهي طبقةٌ وسطى لم تعد
            تُكتب منذ أن صارت `AttendanceStampService` تكتب الختمَ الخام
            وسجلَّ اليوم مباشرةً. فكان القسمُ يقول «لا توجد جلسات مسجلة
            لهذا اليوم» على كل يومٍ مهما امتلأ بالحركات.

            والمردودُ يُعرض مع المقبول: محاولةٌ رُدّت هي ما يُفسِّر يوماً
            يبدو ناقصاً.
        */
        $dayPunches = $attendance_day->punches()
            ->with(['kiosk:id,name', 'geofence:id,name'])
            ->orderBy('punched_at')
            ->get();

        return view('hr::attendance.attendance_days.show', compact('attendance_day', 'logs', 'attendanceRecords', 'dayPunches'));
    }

    public function delete($id)
    {
        $attendance = AttendanceDays::findOrFail($id);
        $this->authorizeEmployeeScope($attendance->employee_id);
        // Log deletion using the correct type to keep consistency with other
        // attendance day actions.  The previous value ("atendes_log") was a
        // misspelling and could break log filtering.
        ModelsLog::create([
            'type' => 'attendance_days_log',
            'type_id' => $attendance->id,
            'type_log' => 'log',
            'description' => 'تم حذف  حضور يومي',
            'created_by' => auth()->id(),
        ]);
        $attendance->delete();

        return redirect()
            ->route('attendanceDays.index')
            ->with(['error' => 'تم حذف ايام الحضور بنجاح']);
    }

    public function calculation()
    {
        $employees = $this->scopedEmployeeOptions([]);
        $branches = Branch::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();
        $job_titles = JopTitle::select('id', 'name')->get();
        $shifts = Shift::select('id', 'name')->get();

        return view('hr::attendance.attendance_days.Calculation', compact('employees', 'branches', 'departments', 'job_titles', 'shifts'));
    }

    /**
     * تشغيلُ معالج الحضور على نطاق تاريخ/موظفين — لا إنشاء دفاتر غياب فارغة.
     */
    public function runCalculation(Request $request, AttendanceProcessorService $processor)
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'use_rules' => 'required|in:rules,employees',
            'employee_id' => 'nullable|array',
            'employee_id.*' => 'integer|exists:employees,id',
            'excluded_employee_id' => 'nullable|array',
            'excluded_employee_id.*' => 'integer|exists:employees,id',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'job_title_id' => 'nullable|integer',
            'shifts_id' => 'nullable|integer',
        ], [
            'from_date.required' => 'يجب تحديد تاريخ البداية',
            'to_date.required' => 'يجب تحديد تاريخ النهاية',
            'to_date.after_or_equal' => 'تاريخ النهاية يجب ألا يسبق البداية',
            'use_rules.required' => 'يجب اختيار معايير الحساب',
        ]);

        $employeeIds = $this->resolveEmployeeIdsFromRequest($request);

        if ($employeeIds === []) {
            return back()
                ->withInput()
                ->with('error', $validated['use_rules'] === 'rules'
                    ? 'لا يوجد موظفون مطابقون للقواعد المختارة بعد الاستبعاد'
                    : 'يرجى اختيار موظف واحد على الأقل');
        }

        // لا يُعالَج من خارج نطاق المستخدم
        $allowed = $this->accessibleEmployeeIds();
        if ($allowed !== null) {
            $employeeIds = array_values(array_intersect($employeeIds, $allowed));
        }

        if ($employeeIds === []) {
            return back()->withInput()->with('error', 'الموظفون المحددون خارج نطاقك.');
        }

        foreach ($employeeIds as $employeeId) {
            $this->authorizeEmployeeScope($employeeId);
        }

        $stats = $processor->processEmployeesDateRange(
            $employeeIds,
            Carbon::parse($validated['from_date']),
            Carbon::parse($validated['to_date']),
        );

        ModelsLog::create([
            'type' => 'attendance_days_log',
            'type_id' => 0,
            'type_log' => 'calculation',
            'description' => sprintf(
                'حساب أيام حضور: %d يوماً معالَجاً (حاضر %d / متأخر %d / غائب %d / إجازة %d / عطلة %d) — خصومات %d — تخطّي مستقبل %d',
                $stats['processed'],
                $stats['present'],
                $stats['late'],
                $stats['absent'],
                $stats['leave'],
                $stats['holiday'],
                $stats['deductions'],
                $stats['skipped_future'],
            ),
            'created_by' => auth()->id(),
        ]);

        $message = sprintf(
            'تم حساب %d يوم حضور. حاضر: %d — متأخر: %d — غائب: %d — إجازة: %d — عطلة: %d — خصومات: %d.',
            $stats['processed'],
            $stats['present'],
            $stats['late'],
            $stats['absent'],
            $stats['leave'],
            $stats['holiday'],
            $stats['deductions'],
        );

        if ($stats['skipped_future'] > 0) {
            $message .= ' (تُجاهلت تواريخ مستقبلية — لا يُحتسب غياب مسبقاً)';
        }

        if ($stats['errors'] !== []) {
            return redirect()
                ->route('attendanceDays.index')
                ->with('warning', $message.' حدثت أخطاء على بعض الصفوف: '.count($stats['errors']));
        }

        return redirect()
            ->route('attendanceDays.index')
            ->with('success', $message);
    }

    /* Helpers -------------------------------------------------------------- */

    public function calculateWorkHours($loginTime, $logoutTime)
    {
        $login = Carbon::parse($loginTime);
        $logout = Carbon::parse($logoutTime);

        $totalDuration = $login->diff($logout);

        $hours = $totalDuration->h;
        $minutes = $totalDuration->i;

        return [
            'hours' => $hours,
            'minutes' => $minutes,
        ];
    }
}
