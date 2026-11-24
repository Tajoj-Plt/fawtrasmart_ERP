<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceSheetsRequest;
use App\Models\AttendanceDays;
use App\Models\AttendanceSheets;
use App\Models\Branch;
use App\Models\Deduction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JopTitle;
use App\Models\Log as ModelsLog;
use App\Models\Shift;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ResolvesEmployeesByRules;
use Modules\HR\Http\Controllers\Concerns\ScopesEmployeeAssignments;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\BranchTree;

class AttendanceSheetsController extends Controller
{
    use ResolvesEmployeesByRules;

    /*
        نطاقُ الموظفين — كان هذا المتحكّم وحدَه بين شاشات الحضور بلا نطاق.

        لا صلاحياتِ «هو» ولا «الكل»: `attendance_books.index` تفتح الشاشة،
        ونطاقُ الرؤية (`data_scope_*`) وحده يحدّد دفاترَ مَن تظهر وما فيها —
        فصاحبُ `data_scope_own` يرى دفاترَه هو، ومديرُ الفرع دفاترَ فرعه.

        والنطاقُ هنا يعمل على مستويين: الدفترُ يظهر إن كان فيه موظفٌ
        واحدٌ على الأقلّ داخل نطاق المستخدم، وما يُعرض **داخله** — الصفوفُ
        والملخّصُ وإحصائياتُ الموظفين — يقتصر على من يراهم. فالدفترُ
        وعاءٌ مشترك، والنطاقُ يقع على محتواه لا على وجوده.
    */
    use ScopesToAccessibleEmployees;
    use ScopesEmployeeAssignments;

    public function __construct()
    {
        /*
            دفاتر الحضور: عرض / إنشاء / تعديل / حذف مستقلان.
            نطاق الرؤية يصفّي محتوى الدفتر لا وجوده وحده.
        */
        $this->middleware('permission.role:attendance_books.index', ['only' => ['index', 'getAttendanceSheetsAjax']]);
        $this->middleware('permission.role:attendance_books.show', ['only' => ['show', 'filterAttendanceDays']]);
        $this->middleware('permission.role:attendance_books.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:attendance_books.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:attendance_books.destroy', ['only' => ['delete']]);
    }

    /**
     * دفترٌ لا يخصّ المستخدمَ فيه أحد: لا يُفتح ولا يُعدَّل ولا يُحذف بتغيير
     * الرقم في العنوان.
     */
    private function authorizeSheet(AttendanceSheets $sheet): void
    {
        if ($this->accessibleEmployeeIds() === null) {
            return;
        }

        $inScope = $sheet->employees()->pluck('employees.id')
            ->contains(fn ($id) => $this->canAccessEmployee($id));

        if (! $inScope) {
            abort(403, 'هذا الدفتر خارج نطاق الموظفين المصرّح لك بالاطّلاع عليهم.');
        }
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->getAttendanceSheetsAjax($request);
        }
        $departments = Department::select('id', 'name')->get();
        $job_titles = JopTitle::select('id', 'name')->get();
        $branches = Branch::select('id', 'name')->get();

        $attendanceSheets = $this->getFilteredAttendanceSheets($request);

        return view('hr::attendance.attendance_sheets.index', compact('attendanceSheets', 'branches', 'departments', 'job_titles'));
    }

    public function getAttendanceSheetsAjax(Request $request)
    {
        try {
            $attendanceSheets = $this->getFilteredAttendanceSheets($request);

            $html = view('hr::attendance.attendance_sheets.table_rows', compact('attendanceSheets'))->render();

            return response()->json([
                'success' => true,
                'html' => $html,
                'count' => $attendanceSheets->count(),
                'message' => null,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.AttendanceSheets', 'getAttendanceSheetsAjax');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحميل البيانات: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    private function getFilteredAttendanceSheets(Request $request)
    {
        $query = AttendanceSheets::query();

        // لا يظهر الدفترُ إلا إن كان فيه موظفٌ داخل نطاق المستخدم
        $query->whereHas('employees', fn ($q) => $this->scopeToEmployees($q, 'employees.id'));

        // Filter by employee name or code
        if ($request->filled('keywords')) {
            $keywords = $request->input('keywords');
            $query->whereHas('employees', function ($q) use ($keywords) {
                /*
                    `employees` هنا مربوطةٌ بعلاقة belongsToMany عبر
                    `attendance_sheets_employees`، ولهذه الأخيرة عمودُها الخاص
                    `id` (bigIncrements) — فـ`id` غير مؤهَّلٍ يصير غامضاً بين
                    الجدولين ويُسقط الاستعلامَ بخطإ SQL 1052 (تحقّقتُ منه حيّاً).
                    التأهيلُ بـ`employees.` يحسم الغموض، والبحث بـ`employee_code`
                    بدل `id` الخام يوافق ما تعرضه الواجهة (#EMP-000001) وما
                    تتّبعه شاشاتٌ أخرى (مثل قوائم الموظفين وطلبات الإجازة).
                */
                $q->where('employees.first_name', 'LIKE', "%{$keywords}%")
                    ->orWhere('employees.employee_code', 'LIKE', "%{$keywords}%");
            });
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('from_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('to_date', '<=', $request->input('to_date'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by department
        if ($request->filled('department')) {
            $department = $request->input('department');
            $query->whereHas('employees', function ($q) use ($department) {
                $q->where('department_id', $department);
            });
        }

        // Filter by branch
        if ($request->filled('branch')) {
            $branch = $request->input('branch');
            $query->whereHas('employees', function ($q) use ($branch) {
                BranchTree::apply($q, $branch, 'employees.branch_id');
            });
        }

        // Filter by job title
        if ($request->filled('job_title')) {
            $jobTitle = $request->input('job_title');
            $query->whereHas('employees', function ($q) use ($jobTitle) {
                $q->where('job_title_id', $jobTitle);
            });
        }

        return $query->orderBy('id', 'DESC')->paginate(15);
    }

    public function create()
    {
        // المنتقي من نطاق المستخدم وفرع الشريط
        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name'), 'id')->get();
        $branches = Branch::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();
        $job_titles = JopTitle::select('id', 'name')->get();
        $shifts = Shift::select('id', 'name')->get();

        return view('hr::attendance.attendance_sheets.create', compact('employees', 'branches', 'departments', 'job_titles', 'shifts'));
    }

    public function show($id)
    {
        $attendanceSheet = AttendanceSheets::with([
            'employees',
            'employees.branch',
            'employees.department',
            'employees.job_title',
            'employees.shift',
            'attendanceDays', // أضف هذه
        ])->findOrFail($id);

        /*
            موظفو الدفتر الذين يحقّ لهذا المستخدم رؤيتُهم — لا موظفوه كلُّهم.

            وعليهم يُبنى كلُّ ما في الشاشة: الصفوفُ والملخّصُ والإحصائيات.
            وإلّا قرأ صاحبُ نطاق `data_scope_own` نسبةَ غيابِ زملائه وما خُصم
            منهم في بطاقاتِ الملخّص، وإن لم يظهروا له صفّاً صفّاً.
        */
        $scopedEmployees = $attendanceSheet->employees->filter(
            fn ($employee) => $this->canAccessEmployee($employee->id)
        )->values();

        // دفترٌ لا يخصّ هذا المستخدمَ فيه أحد: لا يُفتح بتغيير الرقم في العنوان
        if ($scopedEmployees->isEmpty()) {
            abort(403, 'هذا الدفتر خارج نطاق الموظفين المصرّح لك بالاطّلاع عليهم.');
        }

        $scopedEmployeeIds = $scopedEmployees->pluck('id');

        // جلب أيام الحضور مع الفلاتر
        $query = AttendanceDays::whereIn('employee_id', $scopedEmployeeIds)
            ->whereBetween('attendance_date', [$attendanceSheet->from_date, $attendanceSheet->to_date]);

        if (request('employee_filter')) {
            $query->where('employee_id', request('employee_filter'));
        }

        if (request('status_filter')) {
            $query->where('status', request('status_filter'));
        }

        $attendanceDays = $query->with('employee')->orderBy('attendance_date', 'desc')->paginate(50);

        /*
            الملخّصُ يُحسَب على الفترة كاملة، لا على صفحةٍ من الجدول.

            كان القالبُ يبني «إجمالي الأيام» و«نسبة الحضور» و«إحصائيات
            الحضور» لكل موظف من `$attendanceDays` نفسِها — وهي مُرقَّمةٌ
            بخمسين صفّاً ومُصفّاةٌ بمرشِّحَي الموظف والحالة. فدفترُ شهرٍ
            لعشرين موظفاً (نحو ستّمئة صفّ) كان يقول «إجمالي الأيام: 50»،
            وتُحسَب نسبةُ الحضور على أوّل خمسين صفّاً وقعت في الصفحة. ومن
            صفّى بـ«غائب» قرأ نسبةَ حضورٍ صفراً للجميع.

            وهذه الشاشةُ تقريرٌ تجميعيٌّ يُراجَع قبل الإغلاق ويُبنى عليه
            حسمُ الراتب — فالرقمُ فيها إمّا صحيحٌ أو لا معنى له.
        */
        $summaryDays = AttendanceDays::whereIn('employee_id', $scopedEmployeeIds)
            ->whereBetween('attendance_date', [$attendanceSheet->from_date, $attendanceSheet->to_date])
            ->get([
                'id', 'employee_id', 'attendance_date', 'status',
                'check_in_time', 'check_out_time',
                'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'deduction_id',
            ]);

        $summary = $this->summariseDays($summaryDays);
        $employeeStats = $this->summarisePerEmployee($scopedEmployees, $summaryDays);

        // جلب السجلات
        $logs = ModelsLog::where('type', 'attendance_sheets_log')
            ->where('type_id', $id)
            ->whereHas('attendanceSheet') // استخدام العلاقة الصحيحة
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('hr::attendance.attendance_sheets.show', compact('attendanceSheet', 'attendanceDays', 'logs', 'summary', 'employeeStats', 'scopedEmployees'));
    }

    /**
     * ملخّصُ الفترة كلِّها.
     *
     * `working_hours` وصّافٌ في الموديل لا عمودٌ في الجدول، فلا تجمعه SQL —
     * والجمعُ هنا على صفوفٍ محدودةٍ بفترة الدفتر وموظفيه.
     *
     * @param  Collection<int, AttendanceDays>  $days
     * @return array<string, int|float>
     */
    private function summariseDays($days): array
    {
        return [
            'total' => $days->count(),
            'present' => $days->where('status', AttendanceDays::STATUS_PRESENT)->count(),
            'absent' => $days->where('status', AttendanceDays::STATUS_ABSENT)->count(),
            'late' => $days->where('status', AttendanceDays::STATUS_LATE)->count(),
            'leave' => $days->where('status', AttendanceDays::STATUS_LEAVE)->count(),
            'holiday' => $days->where('status', AttendanceDays::STATUS_HOLIDAY)->count(),
            'worked_minutes' => (int) $days->sum(fn ($day) => $day->working_hours),
            // «إجمالي دقائق الخصميات» — ما يُحسَم عليه قبل الإغلاق
            'late_minutes' => (int) $days->sum('late_minutes'),
            'early_leave_minutes' => (int) $days->sum('early_leave_minutes'),
            'overtime_minutes' => (int) $days->sum('overtime_minutes'),
            'deducted_days' => $days->whereNotNull('deduction_id')->count(),
            'deducted_amount' => (float) Deduction::whereIn('id', $days->pluck('deduction_id')->filter()->all())
                ->sum('amount'),
        ];
    }

    /**
     * صفٌّ لكل موظفٍ في الدفتر — بمن لا يومَ له في الفترة.
     *
     * `$employees` لا `$sheet`: القائمةُ تصل مُقيَّدةً بنطاق المستخدم من
     * `show()`، فلا يُبنى صفُّ إحصاءٍ لمن لا يراه.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, AttendanceDays>  $days
     * @return array<int, array<string, mixed>>
     */
    private function summarisePerEmployee($employees, $days): array
    {
        $byEmployee = $days->groupBy('employee_id');

        return $employees->map(function (Employee $employee) use ($byEmployee) {
            $own = $byEmployee->get($employee->id, collect());
            $total = $own->count();
            $present = $own->where('status', AttendanceDays::STATUS_PRESENT)->count();
            $late = $own->where('status', AttendanceDays::STATUS_LATE)->count();
            $absent = $own->where('status', AttendanceDays::STATUS_ABSENT)->count();

            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'leave' => $own->where('status', AttendanceDays::STATUS_LEAVE)->count(),
                'late' => $late,
                'late_minutes' => (int) $own->sum('late_minutes'),
                'early_leave_minutes' => (int) $own->sum('early_leave_minutes'),
                'worked_minutes' => (int) $own->sum(fn ($day) => $day->working_hours),
                // الحاضرُ متأخراً حاضرٌ: نسبةُ الالتزام تقابل الغياب لا التأخير
                'attendance_rate' => $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0.0,
                'absence_rate' => $total > 0 ? round(($absent / $total) * 100, 1) : 0.0,
            ];
        })->all();
    }

    public function store(AttendanceSheetsRequest $request)
    {
        // قبل المعاملة: 403 لا يُبتلَع في catch أدناه
        $scopedEmployeeIds = $this->scopedAssignment($request);

        try {
            DB::beginTransaction();

            $successCount = 0;
            $failedEmployees = [];
            $createdAttendanceIds = [];
            $totalDaysCreated = 0;

            // في وضع القواعد يُجلب الموظفون منها ويُستبعد المحددون، وفي وضع
            // الموظفين يُؤخذ من اختير صراحةً.
            $employeeIds = $scopedEmployeeIds;

            if (empty($employeeIds)) {
                // الخروجُ من داخل المعاملة يترك واحدةً مفتوحة إن لم تُلغَ.
                DB::rollBack();

                return redirect()
                    ->back()
                    ->withInput()
                    ->with(['error' => $request->input('use_rules') === 'rules'
                        ? 'لا يوجد موظفون مطابقون للقواعد المختارة بعد الاستبعاد'
                        : 'يرجى اختيار موظف واحد على الأقل']);
            }

            // إنشاء مصفوفة التواريخ مرة واحدة
            $dateRange = $this->generateDateRange($request['from_date'], $request['to_date']);

            // الحالة الافتراضية
            $defaultStatus = $request['default_status'] ?? 'absent';
            $autoFillShifts = $request['auto_fill_shifts'] ?? false;

            foreach ($employeeIds as $employeeId) {
                // التحقق من وجود دفتر حضور للموظف في نفس الفترة
                $existingAttendance = AttendanceSheets::whereHas('employees', function ($query) use ($employeeId) {
                    $query->where('employee_id', $employeeId);
                })
                    ->where(function ($query) use ($request) {
                        $query
                            ->whereBetween('from_date', [$request['from_date'], $request['to_date']])
                            ->orWhereBetween('to_date', [$request['from_date'], $request['to_date']])
                            ->orWhere(function ($subQuery) use ($request) {
                                $subQuery->where('from_date', '<=', $request['from_date'])->where('to_date', '>=', $request['to_date']);
                            });
                    })
                    ->first();

                if ($existingAttendance) {
                    // الحصول على اسم الموظف للعرض في رسالة الخطأ
                    $employee = Employee::find($employeeId);
                    $failedEmployees[] = $employee ? $employee->full_name : "موظف غير معروف (ID: $employeeId)";

                    continue;
                }

                // الحصول على معلومات الموظف والوردية
                $employee = Employee::with('shift')->find($employeeId);
                $shiftStartTime = null;
                $shiftEndTime = null;

                if ($autoFillShifts && $employee && $employee->shift) {
                    $shiftStartTime = $employee->shift->start_time;
                    $shiftEndTime = $employee->shift->end_time;
                } elseif ($autoFillShifts && $request['shifts_id']) {
                    $shift = Shift::find($request['shifts_id']);
                    if ($shift) {
                        $shiftStartTime = $shift->start_time;
                        $shiftEndTime = $shift->end_time;
                    }
                }

                // إنشاء دفتر حضور جديد لكل موظف
                if ($request['use_rules'] === 'employees') {
                    $attendance = AttendanceSheets::create([
                        'from_date' => $request['from_date'],
                        'to_date' => $request['to_date'],
                        'use_rules' => 2,
                    ]);
                } elseif ($request['use_rules'] === 'rules') {
                    $attendance = AttendanceSheets::create([
                        'from_date' => $request['from_date'],
                        'to_date' => $request['to_date'],
                        'branch_id' => $request['branch_id'],
                        'department_id' => $request['department_id'],
                        'job_title_id' => $request['job_title_id'],
                        'shift_id' => $request['shifts_id'],
                        'use_rules' => 1,
                    ]);
                }

                // ربط الموظف بدفتر الحضور
                $attendance->employees()->attach($employeeId);

                // إنشاء سجلات يومية باستخدام Bulk Insert
                $attendanceDaysData = [];
                $dayCount = 0;

                foreach ($dateRange as $date) {
                    // التحقق من عدم وجود سجل لنفس الموظف ونفس التاريخ
                    $existingDay = AttendanceDays::where('employee_id', $employeeId)->where('attendance_date', $date)->exists();

                    if (! $existingDay) {
                        $dayData = [
                            'attendance_sheets_id' => $attendance->id, // إضافة معرف دفتر الحضور
                            'employee_id' => $employeeId,
                            'attendance_date' => $date,
                            'status' => $defaultStatus,
                            'start_shift' => $shiftStartTime,
                            'end_shift' => $shiftEndTime,
                            'login_time' => null,
                            'logout_time' => null,
                            'absence_type' => $defaultStatus === 'absent' ? 1 : null,
                            'absence_balance' => null,
                            'notes' => 'تم إنشاؤه تلقائياً من دفتر الحضور',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $attendanceDaysData[] = $dayData;
                        $dayCount++;
                    }
                }

                if (! empty($attendanceDaysData)) {
                    AttendanceDays::insert($attendanceDaysData);
                    $totalDaysCreated += $dayCount;
                }

                // بعد الإدراج: شغّل المعالج على الأيام ≤ اليوم حتى لا تبقى غياباً وهمياً
                // إن وُجد ختم أو إجازة/عطلة. المستقبل يبقى بلا صف أو كما أُدرج دون معالجة.
                $employeeModel = Employee::find($employeeId);
                if ($employeeModel) {
                    $processor = app(\App\Services\AttendanceProcessorService::class);
                    foreach ($dateRange as $date) {
                        $carbonDate = \Carbon\Carbon::parse($date)->startOfDay();
                        if ($carbonDate->gt(now()->startOfDay())) {
                            continue;
                        }
                        try {
                            $processor->processEmployeeDay($employeeModel, $carbonDate);
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('معالجة يوم بعد إنشاء دفتر فشلت', [
                                'employee_id' => $employeeId,
                                'date' => $date,
                                'err' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // إضافة السجل
                ModelsLog::create(attributes: [
                    'type' => 'attendance_sheets_log',
                    'type_id' => $attendance->id,
                    'type_log' => 'log',
                    'description' => "تم اضافة دفتر حضور للموظف: {$employee->full_name} (ID: {$employeeId}) مع {$dayCount} يوم حضور",
                    'created_by' => auth()->id(),
                ]);

                $successCount++;
                $createdAttendanceIds[] = $attendance->id;
            }

            DB::commit();

            // تحديد نوع الرسالة بناءً على النتائج
            if ($successCount > 0 && empty($failedEmployees)) {
                // جميع الموظفين تم إضافتهم بنجاح
                return redirect()
                    ->route('attendance_sheets.index')
                    ->with(['success' => "تم إنشاء {$successCount} دفتر حضور بنجاح مع {$totalDaysCreated} سجل يومي!"]);
            } elseif ($successCount > 0 && ! empty($failedEmployees)) {
                // بعض الموظفين تم إضافتهم والبعض فشل
                $failedList = implode('، ', $failedEmployees);

                return redirect()
                    ->route('attendance_sheets.index')
                    ->with([
                        'warning' => "تم إنشاء {$successCount} دفتر حضور بنجاح مع {$totalDaysCreated} سجل يومي. لكن لم يتم إضافة دفاتر للموظفين التاليين لأن لديهم دفاتر حضور في نفس الفترة: {$failedList}",
                    ]);
            } else {
                // جميع الموظفين فشلوا
                $failedList = implode('، ', $failedEmployees);

                return redirect()
                    ->back()
                    ->withInput()
                    ->with([
                        'error' => "لا يمكن إنشاء دفاتر حضور للموظفين المحددين لأن لديهم دفاتر حضور في نفس الفترة: {$failedList}",
                    ]);
            }
        } catch (\Exception $exception) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($exception, 'HR.AttendanceSheets', 'store');

            DB::rollBack();

            return redirect()
                ->back()
                ->withInput()
                ->with(['error' => 'حدث خطأ ما يرجى المحاولة لاحقاً. تفاصيل الخطأ: '.$exception->getMessage()]);
        }
    }

    /**
     * إنشاء مصفوفة من التواريخ بين تاريخين
     */
    private function generateDateRange($fromDate, $toDate)
    {
        $dates = [];
        $startDate = new \DateTime($fromDate);
        $endDate = new \DateTime($toDate);

        while ($startDate <= $endDate) {
            $dates[] = $startDate->format('Y-m-d');
            $startDate->modify('+1 day');
        }

        return $dates;
    }

    /**
     * التحقق من التداخل في التواريخ (يمكن استخدامها لتحسين الأداء)
     */
    private function getExistingAttendanceDates($employeeId, $fromDate, $toDate)
    {
        return AttendanceDays::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->pluck('attendance_date')
            ->toArray();
    }

    public function edit($id)
    {
        $attendanceSheet = AttendanceSheets::findOrFail($id);
        $this->authorizeSheet($attendanceSheet);

        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name'), 'id')->get();
        $branches = Branch::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();
        $job_titles = JopTitle::select('id', 'name')->get();
        $shifts = Shift::select('id', 'name')->get();

        // المرتبطون المعروضون من النطاق وحده؛ ومن سواهم يبقى مرتبطاً عند الحفظ
        $attachedEmployeeIds = $this->onlyListable($attendanceSheet->employees->pluck('id')->map(fn ($id) => (int) $id)->all());

        // قائمةُ الاستبعاد لا تُحفظ، فتُستنتج: مطابقو قواعد الدفتر ناقصَ من
        // ارتبط به فعلاً.
        $excludedEmployeeIds = $attendanceSheet->use_rules == 1
            ? $this->excludedEmployeeIdsFromRules([
                'branch_id' => $attendanceSheet->branch_id,
                'department_id' => $attendanceSheet->department_id,
                'job_title_id' => $attendanceSheet->job_title_id,
                'shift_id' => $attendanceSheet->shift_id,
            ], $attachedEmployeeIds)
            : [];
        $excludedEmployeeIds = $this->onlyListable($excludedEmployeeIds);

        return view('hr::attendance.attendance_sheets.edit', compact('attendanceSheet', 'employees', 'branches', 'departments', 'job_titles', 'shifts', 'attachedEmployeeIds', 'excludedEmployeeIds'));
    }

    public function update(AttendanceSheetsRequest $request, $id)
    {
        $sheet = AttendanceSheets::findOrFail($id);
        $this->authorizeSheet($sheet);
        $scopedEmployeeIds = $this->scopedAssignment($request, $sheet->employees()->pluck('employees.id')->all());

        try {
            DB::beginTransaction();

            $attendance = AttendanceSheets::findOrFail($id);

            // الحصول على الموظفين المرتبطين حالياً
            $currentEmployeeIds = $attendance->employees()->pluck('employee_id')->toArray();
            $newEmployeeIds = $scopedEmployeeIds;

            if (empty($newEmployeeIds)) {
                DB::rollBack();

                return redirect()
                    ->back()
                    ->withInput()
                    ->with(['error' => $request->input('use_rules') === 'rules'
                        ? 'لا يوجد موظفون مطابقون للقواعد المختارة بعد الاستبعاد'
                        : 'يرجى اختيار موظف واحد على الأقل']);
            }

            // تحديث بيانات دفتر الحضور
            if ($request['use_rules'] === 'employees') {
                $attendance->update([
                    'from_date' => $request['from_date'],
                    'to_date' => $request['to_date'],
                    'use_rules' => 2,
                    'branch_id' => null,
                    'department_id' => null,
                    'job_title_id' => null,
                    'shift_id' => null,
                ]);
            } elseif ($request['use_rules'] === 'rules') {
                $attendance->update([
                    'from_date' => $request['from_date'],
                    'to_date' => $request['to_date'],
                    'branch_id' => $request['branch_id'],
                    'department_id' => $request['department_id'],
                    'job_title_id' => $request['job_title_id'],
                    'shift_id' => $request['shifts_id'],
                    'use_rules' => 1,
                ]);
            }

            // حذف أيام الحضور للموظفين المحذوفين
            $removedEmployeeIds = array_diff($currentEmployeeIds, $newEmployeeIds);
            if (! empty($removedEmployeeIds)) {
                AttendanceDays::where('attendance_sheets_id', $attendance->id)
                    ->whereIn('employee_id', $removedEmployeeIds)
                    ->delete();
            }

            // إنشاء مصفوفة التواريخ الجديدة
            $dateRange = $this->generateDateRange($request['from_date'], $request['to_date']);

            // الحالة الافتراضية
            $defaultStatus = $request['default_status'] ?? 'absent';
            $autoFillShifts = $request['auto_fill_shifts'] ?? false;

            $totalDaysCreated = 0;

            // معالجة الموظفين الجدد والموظفين الحاليين
            foreach ($newEmployeeIds as $employeeId) {
                // الحصول على معلومات الموظف والوردية
                $employee = Employee::with('shift')->find($employeeId);
                $shiftStartTime = null;
                $shiftEndTime = null;

                if ($autoFillShifts && $employee && $employee->shift) {
                    $shiftStartTime = $employee->shift->start_time;
                    $shiftEndTime = $employee->shift->end_time;
                } elseif ($autoFillShifts && $request['shifts_id']) {
                    $shift = Shift::find($request['shifts_id']);
                    if ($shift) {
                        $shiftStartTime = $shift->start_time;
                        $shiftEndTime = $shift->end_time;
                    }
                }

                // حذف أيام الحضور الحالية للموظف خارج النطاق الجديد
                AttendanceDays::where('attendance_sheets_id', $attendance->id)
                    ->where('employee_id', $employeeId)
                    ->where(function ($query) use ($request) {
                        $query->where('attendance_date', '<', $request['from_date'])
                            ->orWhere('attendance_date', '>', $request['to_date']);
                    })
                    ->delete();

                // إنشاء سجلات يومية للتواريخ الجديدة
                $attendanceDaysData = [];
                $dayCount = 0;

                foreach ($dateRange as $date) {
                    // التحقق من عدم وجود سجل لنفس الموظف ونفس التاريخ في هذا الدفتر
                    $existingDay = AttendanceDays::where('attendance_sheets_id', $attendance->id)
                        ->where('employee_id', $employeeId)
                        ->where('attendance_date', $date)
                        ->exists();

                    if (! $existingDay) {
                        $dayData = [
                            'attendance_sheets_id' => $attendance->id,
                            'employee_id' => $employeeId,
                            'attendance_date' => $date,
                            'status' => $defaultStatus,
                            'start_shift' => $shiftStartTime,
                            'end_shift' => $shiftEndTime,
                            'login_time' => null,
                            'logout_time' => null,
                            'absence_type' => $defaultStatus === 'absent' ? 1 : null,
                            'absence_balance' => null,
                            'notes' => 'تم إنشاؤه تلقائياً من دفتر الحضور',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $attendanceDaysData[] = $dayData;
                        $dayCount++;
                    }
                }

                // إدراج السجلات الجديدة دفعة واحدة
                if (! empty($attendanceDaysData)) {
                    AttendanceDays::insert($attendanceDaysData);
                    $totalDaysCreated += $dayCount;
                }
            }

            // تحديث ربط الموظفين
            $attendance->employees()->sync($newEmployeeIds);

            // إضافة السجل
            ModelsLog::create([
                'type' => 'attendance_sheets_log',
                'type_id' => $attendance->id,
                'type_log' => 'log',
                'description' => "تم تعديل دفتر حضور مع {$totalDaysCreated} سجل يومي جديد",
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('attendance_sheets.index')
                ->with(['success' => "تم تعديل دفتر الحضور بنجاح مع {$totalDaysCreated} سجل يومي!"]);

        } catch (\Exception $exception) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($exception, 'HR.AttendanceSheets', 'update');

            DB::rollBack();

            return redirect()
                ->back()
                ->withInput()
                ->with(['error' => 'حدث خطأ ما يرجى المحاولة لاحقاً. تفاصيل الخطأ: '.$exception->getMessage()]);
        }
    }

    public function delete($id)
    {
        $attendance = AttendanceSheets::findOrFail($id);
        $this->authorizeSheet($attendance);
        ModelsLog::create([
            'type' => 'attendance_sheets_log',
            'type_id' => $attendance->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => 'تم  حذف دفتر حضور',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);
        if ($attendance->status == 1) {
            return redirect()
                ->route('attendance_sheets.index')
                ->with(['error' => 'لا يمكن حذف دفتر الحضور الموافق عليه !!']);
        }
        $attendance->employees()->detach();
        $attendance->delete();

        return redirect()
            ->route('attendance_sheets.index')
            ->with(['error' => 'تم حذف دفتر الحضور بنجاج !!']);
    }
}
