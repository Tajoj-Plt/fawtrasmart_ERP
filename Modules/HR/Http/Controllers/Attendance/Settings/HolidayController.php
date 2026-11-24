<?php

namespace Modules\HR\Http\Controllers\Attendance\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayList;
use App\Models\HolyDayListCustomize;
use App\Models\JopTitle;
use App\Models\Log as ModelsLog;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\BranchTree;

class HolidayController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        // لكلّ فعلٍ صلاحيتُه `holidays.*` — لا صلاحيةَ «إدارة» واحدة تفتح الشاشة كلّها
        $this->middleware('permission.role:holidays.index', ['only' => ['index', 'search']]);
        $this->middleware('permission.role:holidays.show', ['only' => ['show', 'employeesCount', 'employeesList', 'getStatistics']]);
        $this->middleware('permission.role:holidays.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:holidays.edit', ['only' => ['edit', 'update', 'holyday_employees', 'add_holyday_employees', 'removeAssignment', 'addEmployees', 'removeEmployee']]);
        $this->middleware('permission.role:holidays.destroy', ['only' => ['delete']]);
    }

    public function index(Request $request)
    {
        $query = HolidayList::query();

        if ($request->has('keywords') && ! empty($request->keywords)) {
            $query->where('name', 'LIKE', '%'.$request->keywords.'%');
        }

        // إضافة الـ pagination
        $perPage = $request->input('per_page', 10); // افتراضي 10
        $holiday_lists = $query->orderBy('id', 'DESC')->paginate($perPage);

        // الاحتفاظ بمعاملات البحث في الـ pagination
        $holiday_lists->appends($request->query());

        // إذا كان الطلب AJAX، نرجع JSON
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $holiday_lists,
                'html' => view('hr::attendance.settings.holiday.table-content', compact('holiday_lists'))->render(),
            ]);
        }

        return view('hr::attendance.settings.holiday.index', compact('holiday_lists'));
    }

    // Route جديد للبحث بـ AJAX
    public function search(Request $request)
    {
        $query = HolidayList::query();

        if ($request->has('keywords') && ! empty($request->keywords)) {
            $query->where('name', 'LIKE', '%'.$request->keywords.'%');
        }

        // إضافة الـ pagination
        $perPage = $request->input('per_page', 10); // افتراضي 10
        $holiday_lists = $query->orderBy('id', 'DESC')->paginate($perPage);

        // الاحتفاظ بمعاملات البحث في الـ pagination
        $holiday_lists->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => $holiday_lists,
            'html' => view('hr::attendance.settings.holiday.table-content', compact('holiday_lists'))->render(),
        ]);
    }

    public function create()
    {
        return view('hr::attendance.settings.holiday.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'named' => 'required|array|min:1',
            'named.*' => 'required|string|max:255',
            'recurrence_type' => 'required|array',
            'recurrence_type.*' => 'required|in:once,weekly,monthly,yearly',
            'holiday_date' => 'nullable|array',
            'holiday_date.*' => 'nullable|date',
            'recurrence_day_of_week' => 'nullable|array',
            'recurrence_day_of_month' => 'nullable|array',
            'recurrence_yearly_day' => 'nullable|array',
            'recurrence_month' => 'nullable|array',
            'is_paid' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $holidayList = HolidayList::create(['name' => $request->name]);

            foreach ($request->named as $index => $name) {
                $type = $request->recurrence_type[$index];
                Holiday::create([
                    'holiday_list_id' => $holidayList->id,
                    'named' => $name,
                    'recurrence_type' => $type,
                    'holiday_date' => $type === 'once' ? ($request->holiday_date[$index] ?? null) : null,
                    'recurrence_day_of_week' => $type === 'weekly' ? ($request->recurrence_day_of_week[$index] ?? null) : null,
                    /* لليوم السنويّ حقلُه — كان يتشارك اسمَ الشهريّ فتنزاح القيمُ بين الصفوف: يومُ التأسيس بلا يوم، واليومُ الوطني 22 بدل 23 */
                    'recurrence_day_of_month' => match ($type) {
                        'monthly' => $request->recurrence_day_of_month[$index] ?? null,
                        'yearly' => $request->recurrence_yearly_day[$index] ?? $request->recurrence_day_of_month[$index] ?? null,
                        default => null,
                    },
                    'recurrence_month' => $type === 'yearly' ? ($request->recurrence_month[$index] ?? null) : null,
                    'is_active' => true,
                    'is_paid' => isset($request->is_paid[$index]),
                ]);
            }

            ModelsLog::create([
                'type' => 'holiday_list',
                'type_id' => $holidayList->id,
                'type_log' => 'log',
                'description' => 'تم إضافة قائمة عطلات: '.$holidayList->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('holiday_lists.index')
                ->with('success', 'تمت إضافة قائمة العطلات ('.count($request->named).' عطلة)');
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'store');

            DB::rollBack();

            return back()->withInput()->with('error', 'فشل الحفظ: '.$e->getMessage());
        }
    }

    public function edit($id)
    {
        $holiday_list = HolidayList::with('holidays')->findOrFail($id);

        return view('hr::attendance.settings.holiday.edit', compact('holiday_list'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'named' => 'required|array|min:1',
            'named.*' => 'required|string|max:255',
            'recurrence_type' => 'required|array',
            'recurrence_type.*' => 'required|in:once,weekly,monthly,yearly',
            'holiday_date' => 'nullable|array',
            'recurrence_day_of_week' => 'nullable|array',
            'recurrence_day_of_month' => 'nullable|array',
            'recurrence_yearly_day' => 'nullable|array',
            'recurrence_month' => 'nullable|array',
            'is_paid' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $holidayList = HolidayList::findOrFail($id);
            $holidayList->update(['name' => $request->name]);

            // احذف القديم وأنشئ الجديد (أبسط من المُزامنة)
            Holiday::where('holiday_list_id', $id)->delete();

            foreach ($request->named as $index => $name) {
                $type = $request->recurrence_type[$index];
                Holiday::create([
                    'holiday_list_id' => $id,
                    'named' => $name,
                    'recurrence_type' => $type,
                    'holiday_date' => $type === 'once' ? ($request->holiday_date[$index] ?? null) : null,
                    'recurrence_day_of_week' => $type === 'weekly' ? ($request->recurrence_day_of_week[$index] ?? null) : null,
                    /* لليوم السنويّ حقلُه — كان يتشارك اسمَ الشهريّ فتنزاح القيمُ بين الصفوف: يومُ التأسيس بلا يوم، واليومُ الوطني 22 بدل 23 */
                    'recurrence_day_of_month' => match ($type) {
                        'monthly' => $request->recurrence_day_of_month[$index] ?? null,
                        'yearly' => $request->recurrence_yearly_day[$index] ?? $request->recurrence_day_of_month[$index] ?? null,
                        default => null,
                    },
                    'recurrence_month' => $type === 'yearly' ? ($request->recurrence_month[$index] ?? null) : null,
                    'is_active' => true,
                    'is_paid' => isset($request->is_paid[$index]),
                ]);
            }

            ModelsLog::create([
                'type' => 'holiday_list',
                'type_id' => $holidayList->id,
                'type_log' => 'log',
                'description' => 'تم تحديث قائمة عطلات: '.$holidayList->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('holiday_lists.index')->with('success', 'تم تحديث قائمة العطلات');
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'update');

            DB::rollBack();

            return back()->withInput()->with('error', 'فشل التحديث: '.$e->getMessage());
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $list = HolidayList::findOrFail($id);
            Holiday::where('holiday_list_id', $id)->delete();
            HolyDayListCustomize::where('holiday_list_id', $id)->delete();
            $list->delete();

            ModelsLog::create([
                'type' => 'holiday_list', 'type_id' => $id, 'type_log' => 'log',
                'description' => 'تم حذف قائمة عطلات: '.$list->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('holiday_lists.index')->with('success', 'تم حذف القائمة');
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'delete');

            DB::rollBack();

            return back()->with('error', 'فشل الحذف: '.$e->getMessage());
        }
    }

    /**
     * ربطُ قائمة العطل بمن تسري عليه: قسم، أو مسمى وظيفي، أو فرع، أو موظفون بأعينهم.
     *
     * كلُّ ربطٍ صفٌّ مستقل، ويكفي أن ينطبق واحدٌ منها على الموظف (`Holiday::appliesToEmployee`).
     * وقائمةٌ بلا ربطٍ أصلاً تسري على الجميع.
     *
     * كان النموذج يعرض طريقتين لا تجتمعان («قواعد» فرع+قسم+مسمى في صفٍّ
     * واحد يجب أن تنطبق كلُّها، أو «موظفون»)، وكان الموظفون المختارون مع
     * «القواعد» يُحفظون ولا يُقرؤون، وقاعدةٌ فارغة تُحفظ فلا تنطبق على أحد —
     * فتصير القائمة لا تسري على أحد بلا أن يظهر ذلك.
     */
    public function holyday_employees($id)
    {
        $holiday_list = HolidayList::findOrFail($id);

        $employees = $this->scopeToEmployees(Employee::query(), 'id')
            ->select('id', 'first_name', 'middle_name', 'nickname', 'department_id', 'job_title_id', 'branch_id', 'status')
            ->with(['department:id,name', 'job_title:id,name'])
            ->whereIn('status', [1, '1', 'active'])
            ->orderBy('first_name')
            ->get();

        $departments = Department::select('id', 'name')->orderBy('name')->get();
        $job_titles = JopTitle::select('id', 'name')->orderBy('name')->get();
        $branches = Branch::select('id', 'name')->orderBy('name')->get();

        $assignments = HolyDayListCustomize::where('holiday_list_id', $holiday_list->id)
            ->with(['department:id,name', 'jobTitle:id,name', 'branch:id,name', 'employees:id,first_name,middle_name,nickname'])
            ->orderBy('use_rules')
            ->orderBy('id')
            ->get()
            ->map(function (HolyDayListCustomize $row) {
                if ((int) $row->use_rules === 2) {
                    return [
                        'row' => $row,
                        'type' => 'employees',
                        'label' => 'موظفون محددون',
                        'value' => $row->employees->map->full_name->implode('، ') ?: '—',
                        'employees' => $row->employees,
                        'count' => $row->employees->count(),
                    ];
                }

                $parts = array_filter([
                    $row->department ? 'قسم: '.$row->department->name : null,
                    $row->jobTitle ? 'مسمى: '.$row->jobTitle->name : null,
                    $row->branch ? 'فرع: '.$row->branch->name : null,
                ]);

                $count = Employee::whereIn('status', [1, '1', 'active'])
                    ->when($row->department_id, fn ($q) => $q->where('department_id', $row->department_id))
                    ->when($row->job_title_id, fn ($q) => $q->where('job_title_id', $row->job_title_id))
                    ->when($row->branch_id, fn ($q) => $q->where('branch_id', $row->branch_id))
                    ->count();

                return [
                    'row' => $row,
                    'type' => $row->department_id ? 'department' : ($row->job_title_id ? 'job_title' : 'branch'),
                    'label' => $row->department_id ? 'قسم' : ($row->job_title_id ? 'مسمى وظيفي' : ($row->branch_id ? 'فرع' : 'قاعدة فارغة')),
                    'value' => $parts ? implode(' + ', $parts) : 'لا تنطبق على أحد — احذفها',
                    'employees' => null,
                    'count' => $parts ? $count : 0,
                ];
            });

        return view('hr::attendance.settings.holiday.holyday_employees', compact(
            'holiday_list', 'employees', 'departments', 'job_titles', 'branches', 'assignments'
        ));
    }

    public function add_holyday_employees(Request $request, $id)
    {
        $holiday_list = HolidayList::findOrFail($id);

        $data = $request->validate([
            'target' => 'required|in:department,job_title,branch,employees',
            'department_ids' => 'required_if:target,department|array',
            'department_ids.*' => 'integer|exists:departments,id',
            'job_title_ids' => 'required_if:target,job_title|array',
            'job_title_ids.*' => 'integer|exists:jop_titles,id',
            'branch_ids' => 'required_if:target,branch|array',
            'branch_ids.*' => 'integer|exists:branches,id',
            'employee_ids' => 'required_if:target,employees|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ], [
            'department_ids.required_if' => 'اختر قسماً واحداً على الأقل.',
            'job_title_ids.required_if' => 'اختر مسمى وظيفياً واحداً على الأقل.',
            'branch_ids.required_if' => 'اختر فرعاً واحداً على الأقل.',
            'employee_ids.required_if' => 'اختر موظفاً واحداً على الأقل.',
        ]);

        try {
            DB::beginTransaction();

            $added = 0;
            $column = ['department' => 'department_id', 'job_title' => 'job_title_id', 'branch' => 'branch_id'][$data['target']] ?? null;

            if ($column) {
                // صفٌّ لكل قسم/مسمى/فرع — يكفي أن ينطبق أحدها
                foreach (array_unique($data[$data['target'].'_ids']) as $valueId) {
                    $row = HolyDayListCustomize::firstOrCreate([
                        'holiday_list_id' => $holiday_list->id,
                        'use_rules' => 1,
                        'department_id' => $column === 'department_id' ? $valueId : null,
                        'job_title_id' => $column === 'job_title_id' ? $valueId : null,
                        'branch_id' => $column === 'branch_id' ? $valueId : null,
                    ]);
                    $added += $row->wasRecentlyCreated ? 1 : 0;
                }
            } else {
                // صفُّ «موظفون محددون» واحدٌ للقائمة، يُضاف إليه ولا يُستبدل
                $row = HolyDayListCustomize::firstOrCreate([
                    'holiday_list_id' => $holiday_list->id,
                    'use_rules' => 2,
                ]);
                $current = $row->employees()->pluck('employees.id')->all();
                $row->employees()->sync($this->scopedMemberSync(array_merge($current, $data['employee_ids']), $current));
                $added = count(array_diff($data['employee_ids'], $current));
            }

            ModelsLog::create([
                'type' => 'holiday_list',
                'type_id' => $holiday_list->id,
                'type_log' => 'log',
                'description' => 'ربط قائمة العطل «'.$holiday_list->name.'» — '.[
                    'department' => 'أقسام', 'job_title' => 'مسميات وظيفية', 'branch' => 'فروع', 'employees' => 'موظفين',
                ][$data['target']].' ('.$added.' جديد)',
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('holiday_lists.holyday_employees', $holiday_list->id)
                ->with('success', $added > 0 ? 'تمت إضافة الربط.' : 'المختار مربوطٌ بالقائمة مسبقاً.');
        } catch (\Throwable $exception) {
            DB::rollBack();
            OperationLog::failure($exception, 'HR.Holiday', 'add_holyday_employees');

            return redirect()->back()->withInput()->with('error', 'تعذّر حفظ الربط.');
        }
    }

    /**
     * إزالةُ ربط — صفُّ قسمٍ/مسمى/فرع كاملاً، أو موظفٍ واحد من «موظفون محددون».
     */
    public function removeAssignment(Request $request, $id, $assignmentId)
    {
        $row = HolyDayListCustomize::where('holiday_list_id', $id)->findOrFail($assignmentId);

        if ((int) $row->use_rules === 2 && $request->filled('employee_id')) {
            $this->authorizeEmployeeScope((int) $request->employee_id);
            $row->employees()->detach((int) $request->employee_id);
            if ($row->employees()->count() === 0) {
                $row->delete();
            }
        } else {
            $row->employees()->detach();
            $row->delete();
        }

        ModelsLog::create([
            'type' => 'holiday_list',
            'type_id' => (int) $id,
            'type_log' => 'log',
            'description' => 'إزالة ربط من قائمة العطل',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('holiday_lists.holyday_employees', $id)->with('success', 'أُزيل الربط.');
    }

    public function show($id)
    {
        try {
            $holiday_list = HolidayList::with(['holidays'])->findOrFail($id);

            // جلب التخصيصات (الموظفين/القواعد المربوطة)
            $customizations = HolyDayListCustomize::where('holiday_list_id', $id)
                ->with(['employees:id,first_name,middle_name'])
                ->get();

            // حساب عدد الموظفين الفعليين المتأثرين بهذه القائمة
            $affectedEmployees = collect();
            $appliedRules = [];

            foreach ($customizations as $c) {
                if ($c->use_rules == 2 && $c->employees) {
                    // موظفون مباشرون
                    foreach ($c->employees as $emp) {
                        $affectedEmployees->push($emp);
                    }
                } elseif ($c->use_rules == 1) {
                    // قواعد
                    $appliedRules[] = [
                        'branch_id' => $c->branch_id,
                        'department_id' => $c->department_id,
                        'job_title_id' => $c->job_title_id,
                    ];
                    $q = Employee::query();
                    if ($c->branch_id) {
                        $q->where('branch_id', $c->branch_id);
                    }
                    if ($c->department_id) {
                        $q->where('department_id', $c->department_id);
                    }
                    if ($c->job_title_id) {
                        $q->where('job_title_id', $c->job_title_id);
                    }
                    foreach ($q->select('id', 'first_name', 'middle_name')->get() as $emp) {
                        $affectedEmployees->push($emp);
                    }
                }
            }
            $affectedEmployees = $this->onlyListedEmployees($affectedEmployees->unique('id')->values());

            // حساب التواريخ القادمة المتأثرة في الـ 60 يوماً القادمة
            $upcomingDates = [];
            $now = now();
            $end = $now->copy()->addDays(60);
            foreach ($holiday_list->holidays as $h) {
                $d = $now->copy();
                while ($d->lte($end)) {
                    if ($h->appliesToDate($d)) {
                        $upcomingDates[] = [
                            'date' => $d->copy(),
                            'holiday' => $h,
                        ];
                    }
                    $d->addDay();
                }
            }
            // ترتيب وحد أول 20
            usort($upcomingDates, fn ($a, $b) => $a['date']->timestamp <=> $b['date']->timestamp);
            $upcomingDates = array_slice($upcomingDates, 0, 20);

            // جلب سجل النشاطات
            $logs = ModelsLog::where('type', 'holiday_list')
                ->where('type_id', $id)
                ->with(['user.branch'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function ($item) {
                    return $item->created_at->format('Y-m-d');
                });

            return view('hr::attendance.settings.holiday.show', compact(
                'holiday_list', 'logs', 'customizations', 'affectedEmployees', 'upcomingDates'
            ));

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'show');

            return redirect()->route('holiday_lists.index')
                ->with('error', 'قائمة العطل غير موجودة');
        }
    }

    /**
     * جلب عدد الموظفين المخصصين لقائمة العطل
     */
    public function employeesCount($id)
    {
        try {
            $holiday_list = HolidayList::findOrFail($id);

            // البحث عن التخصيصات المرتبطة بهذه القائمة
            $customizations = HolyDayListCustomize::where('holiday_list_id', $id)->get();

            $totalEmployees = 0;

            foreach ($customizations as $customization) {
                if ($customization->use_rules == 2) {
                    // مخصصة للموظفين مباشرة
                    $totalEmployees += $this->scopeAnyQueryToEmployees($customization->employees(), 'employees.id')->count();
                } elseif ($customization->use_rules == 1) {
                    // مخصصة حسب القواعد (فرع، قسم، منصب)
                    $query = Employee::query();

                    if ($customization->branch_id) {
                        $query->where('branch_id', $customization->branch_id);
                    }

                    if ($customization->department_id) {
                        $query->where('department_id', $customization->department_id);
                    }

                    if ($customization->job_title_id) {
                        $query->where('job_title_id', $customization->job_title_id);
                    }

                    $totalEmployees += $this->scopeToEmployees($query, 'id')->count();
                }
            }

            return response()->json([
                'success' => true,
                'count' => $totalEmployees,
                'message' => 'تم جلب العدد بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'employeesCount');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب قائمة الموظفين المخصصين لقائمة العطل
     */
    /**
     * جلب قائمة الموظفين المخصصين لقائمة العطل - محسن
     */
    public function employeesList($id)
    {
        try {
            $holiday_list = HolidayList::findOrFail($id);
            $employees = collect();
            $departments = collect();

            // البحث عن التخصيصات المرتبطة بهذه القائمة
            $customizations = HolyDayListCustomize::where('holiday_list_id', $id)
                ->with(['employees'])
                ->get();

            foreach ($customizations as $customization) {
                if ($customization->use_rules == 2) {
                    // الموظفين المخصصين مباشرة
                    $directEmployees = $this->scopeAnyQueryToEmployees($customization->employees(), 'employees.id')
                        ->with(['department', 'branch', 'job_Title'])
                        ->get();

                    foreach ($directEmployees as $employee) {
                        // تأكد من وجود البيانات وإنشاء structure واضح
                        $employeeData = [
                            'id' => $employee->id,
                            'full_name' => $employee->full_name ?? $employee->full_name ?? 'غير محدد',
                            'employee_id' => $employee->id ?? $employee->id ?? 'غير محدد',
                            'department_id' => $employee->department_id ?? null,
                            'department' => $employee->department ? [
                                'id' => $employee->department->id,
                                'name' => $employee->department->name ?? $employee->department->ar_name ?? 'غير محدد',
                            ] : null,
                            'job_title' => $employee->job_Title ? [
                                'id' => $employee->job_Title->id,
                                'name' => $employee->job_Title->name ?? $employee->job_Title->ar_name ?? 'غير محدد',
                            ] : null,
                            'branch' => $employee->branch ? [
                                'id' => $employee->branch->id,
                                'name' => $employee->branch->name ?? $employee->branch->ar_name ?? 'غير محدد',
                            ] : null,
                            'holidays_count' => $holiday_list->holidays()->count(),
                            'assignment_type' => 'مباشر',
                        ];

                        $employees->push((object) $employeeData);

                        if ($employee->department) {
                            $departments->push($employee->department);
                        }
                    }

                } elseif ($customization->use_rules == 1) {
                    // الموظفين المخصصين حسب القواعد
                    $query = Employee::with(['department', 'branch', 'job_Title']);

                    if ($customization->branch_id) {
                        $query->where('branch_id', $customization->branch_id);
                    }

                    if ($customization->department_id) {
                        $query->where('department_id', $customization->department_id);
                    }

                    if ($customization->job_title_id) {
                        $query->where('job_title_id', $customization->job_title_id);
                    }

                    $ruleBasedEmployees = $this->scopeToEmployees($query, 'id')->get();

                    foreach ($ruleBasedEmployees as $employee) {
                        // تأكد من وجود البيانات وإنشاء structure واضح
                        $employeeData = [
                            'id' => $employee->id,
                            'full_name' => $employee->full_name ?? $employee->full_name ?? 'غير محدد',
                            'employee_id' => $employee->employee_id ?? $employee->emp_id ?? 'غير محدد',
                            'department_id' => $employee->department_id ?? null,
                            'department' => $employee->department ? [
                                'id' => $employee->department->id,
                                'name' => $employee->department->name ?? $employee->department->ar_name ?? 'غير محدد',
                            ] : null,
                            'job_title' => $employee->job_Title ? [
                                'id' => $employee->job_Title->id,
                                'name' => $employee->job_Title->name ?? $employee->job_Title->ar_name ?? 'غير محدد',
                            ] : null,
                            'branch' => $employee->branch ? [
                                'id' => $employee->branch->id,
                                'name' => $employee->branch->name ?? $employee->branch->ar_name ?? 'غير محدد',
                            ] : null,
                            'holidays_count' => $holiday_list->holidays()->count(),
                            'assignment_type' => 'حسب القواعد',
                        ];

                        $employees->push((object) $employeeData);

                        if ($employee->department) {
                            $departments->push($employee->department);
                        }
                    }
                }
            }

            // إزالة التكرارات
            $employees = $employees->unique('id');
            $departments = $departments->unique('id');

            // تحويل البيانات إلى array للتأكد من التوافق
            $employeesArray = $employees->values()->map(function ($employee) {
                return (array) $employee;
            })->toArray();

            $departmentsArray = $departments->values()->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name ?? $dept->ar_name ?? 'غير محدد',
                ];
            })->toArray();

            Log::info('Employee data structure:', [
                'total_employees' => count($employeesArray),
                'sample_employee' => $employeesArray[0] ?? null,
                'departments_count' => count($departmentsArray),
            ]);

            return response()->json([
                'success' => true,
                'employees' => $employeesArray,
                'departments' => $departmentsArray,
                'total_count' => count($employeesArray),
                'debug_info' => [
                    'customizations_count' => $customizations->count(),
                    'holiday_list_id' => $id,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading employees list:', [
                'holiday_list_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إزالة موظف من قائمة العطل
     */
    public function removeEmployee($holidayListId, Request $request)
    {
        $this->authorizeEmployeeScope($request->input('employee_id'));

        try {
            DB::beginTransaction();

            $employeeId = $request->input('employee_id');

            // البحث عن التخصيص الذي يحتوي على هذا الموظف
            $customizations = HolyDayListCustomize::where('holiday_list_id', $holidayListId)
                ->where('use_rules', 2) // فقط المخصصين مباشرة
                ->get();

            $removed = false;
            foreach ($customizations as $customization) {
                if ($customization->employees()->where('employee_id', $employeeId)->exists()) {
                    $customization->employees()->detach($employeeId);
                    $removed = true;
                    break;
                }
            }

            if ($removed) {
                // تسجيل العملية في السجل
                ModelsLog::create([
                    'type' => 'holiday_list',
                    'type_id' => $holidayListId,
                    'type_log' => 'log',
                    'description' => 'تم إزالة موظف من قائمة العطلات',
                    'created_by' => auth()->id(),
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'تم إزالة الموظف من القائمة بنجاح',
                ]);
            } else {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'الموظف غير موجود في هذه القائمة',
                ], 404);
            }

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'removeEmployee');

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إزالة الموظف',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إضافة موظفين جدد لقائمة العطل
     */
    public function addEmployees($holidayListId, Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        foreach ((array) $request->employee_ids as $employeeId) {
            $this->authorizeEmployeeScope($employeeId);
        }

        try {
            DB::beginTransaction();

            $holiday_list = HolidayList::findOrFail($holidayListId);

            // البحث عن التخصيص المباشر أو إنشاء واحد جديد
            $customization = HolyDayListCustomize::firstOrCreate([
                'holiday_list_id' => $holidayListId,
                'use_rules' => 2, // مخصص للموظفين مباشرة
            ]);

            // إضافة الموظفين الجدد
            $newEmployees = array_diff($request->employee_ids, $customization->employees()->pluck('employee_id')->toArray());

            if (! empty($newEmployees)) {
                $customization->employees()->attach($newEmployees);

                // تسجيل العملية في السجل
                ModelsLog::create([
                    'type' => 'holiday_list',
                    'type_id' => $holidayListId,
                    'type_log' => 'log',
                    'description' => 'تم إضافة '.count($newEmployees).' موظف جديد لقائمة العطلات',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الموظفين بنجاح',
                'added_count' => count($newEmployees),
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'addEmployees');

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الموظفين',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب إحصائيات قائمة العطل
     */
    public function getStatistics($id)
    {
        try {
            $holiday_list = HolidayList::findOrFail($id);

            // عدد أيام العطل
            $holidaysCount = $holiday_list->holidays()->count();

            // عدد الموظفين المخصصين
            $employeesCount = $this->getEmployeesCountForList($id);

            // عدد الأقسام المتأثرة
            $departmentsCount = $this->getAffectedDepartmentsCount($id);

            // آخر تحديث
            $lastUpdate = $holiday_list->updated_at;

            return response()->json([
                'success' => true,
                'statistics' => [
                    'holidays_count' => $holidaysCount,
                    'employees_count' => $employeesCount,
                    'departments_count' => $departmentsCount,
                    'last_update' => $lastUpdate->diffForHumans(),
                    'creation_date' => $holiday_list->created_at->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'HR.Holiday', 'getStatistics');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات',
            ], 500);
        }
    }

    /**
     * دالة مساعدة لحساب عدد الموظفين
     */
    private function getEmployeesCountForList($holidayListId)
    {
        $customizations = HolyDayListCustomize::where('holiday_list_id', $holidayListId)->get();
        $totalEmployees = 0;

        foreach ($customizations as $customization) {
            if ($customization->use_rules == 2) {
                $totalEmployees += $this->scopeAnyQueryToEmployees($customization->employees(), 'employees.id')->count();
            } elseif ($customization->use_rules == 1) {
                $query = Employee::query();

                if ($customization->branch_id) {
                    $query->where('branch_id', $customization->branch_id);
                }

                if ($customization->department_id) {
                    $query->where('department_id', $customization->department_id);
                }

                if ($customization->job_title_id) {
                    $query->where('job_title_id', $customization->job_title_id);
                }

                $totalEmployees += $this->scopeToEmployees($query, 'id')->count();
            }
        }

        return $totalEmployees;
    }

    /**
     * دالة مساعدة لحساب عدد الأقسام المتأثرة
     */
    private function getAffectedDepartmentsCount($holidayListId)
    {
        $customizations = HolyDayListCustomize::where('holiday_list_id', $holidayListId)->get();
        $departments = collect();

        foreach ($customizations as $customization) {
            if ($customization->use_rules == 2) {
                $employeeDepartments = $this->scopeAnyQueryToEmployees($customization->employees(), 'employees.id')
                    ->with('department')
                    ->get()
                    ->pluck('department')
                    ->filter();
                $departments = $departments->merge($employeeDepartments);
            } elseif ($customization->use_rules == 1 && $customization->department_id) {
                $department = Department::find($customization->department_id);
                if ($department) {
                    $departments->push($department);
                }
            }
        }

        return $departments->unique('id')->count();
    }

    /**
     * أعضاءُ التخصيص بعد الحفظ: ما اختاره المستخدم **من داخل نطاقه**، ومن كان
     * مرتبطاً خارجَ نطاقه يبقى — المنتقي لا يعرضهم، فغيابُهم ليس طلباً بإزالتهم.
     *
     * @return array<int>
     */
    private function scopedMemberSync(array $submitted, array $current): array
    {
        $allowed = $this->accessibleEmployeeIds();
        $submitted = array_values(array_filter(array_map('intval', $submitted)));

        if ($allowed === null) {
            return $submitted;
        }

        $kept = array_filter(array_map('intval', $current), fn ($id) => ! in_array($id, $allowed, true));

        return array_values(array_unique(array_merge(array_intersect($submitted, $allowed), $kept)));
    }

    /** يحصر مجموعةَ موظفين (نماذج أو كائنات تحمل id) في نطاق القوائم */
    private function onlyListedEmployees($employees)
    {
        $ids = $this->listEmployeeIds();

        return $ids === null
            ? $employees
            : $employees->filter(fn ($e) => in_array((int) $e->id, $ids, true))->values();
    }
}
