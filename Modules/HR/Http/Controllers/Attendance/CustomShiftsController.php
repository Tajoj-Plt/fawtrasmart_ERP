<?php

namespace Modules\HR\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomShiftRequest;
use App\Models\Branch;
use App\Models\CustomShift;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JopTitle;
use App\Models\Log as ModelsLog;
use App\Models\Shift;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HR\Http\Controllers\Concerns\ResolvesEmployeesByRules;
use Modules\HR\Http\Controllers\Concerns\ScopesEmployeeAssignments;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;
use Modules\HR\Support\BranchTree;

class CustomShiftsController extends Controller
{
    use ResolvesEmployeesByRules;
    use ScopesEmployeeAssignments;
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        $this->middleware('permission.role:shifts.index', ['only' => ['index', 'filter', 'getCustomShiftsData']]);
        $this->middleware('permission.role:shifts.show', ['only' => ['show']]);
        $this->middleware('permission.role:shifts.create', ['only' => ['create', 'store', 'duplicate']]);
        // معاينةُ عدد المطابقين تخدم شاشتي الإضافة والتعديل
        $this->middleware('permission.role:shifts.create|shifts.edit', ['only' => ['getEmployeeCountPreview']]);
        $this->middleware('permission.role:shifts.edit', ['only' => ['edit', 'update', 'toggleStatus']]);
        $this->middleware('permission.role:shifts.destroy', ['only' => ['delete']]);
    }


    public function index(Request $request)
    {
        $custom_shifts = CustomShift::with(['shift', 'employees' => $this->scopedEmployeesRelation(), 'employees.branch', 'employees.department', 'employees.job_Title'])
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('hr::attendance.custom_shifts.index', compact('custom_shifts'));
    }

    /** حدٌّ أعلى للصفحة: `per_page=999999` في العنوان يُعيد الجلبَ الكامل من الباب الخلفي. */
    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 10), 5), 100);
    }

    public function filter(Request $request)
    {
        $query = CustomShift::with(['shift', 'employees' => $this->scopedEmployeesRelation(), 'employees.branch', 'employees.department', 'employees.job_Title'])
            ->orderBy('id', 'desc');

        /*
            المجموعةُ حول «أو» — لا شرطان مفكوكان.

            كان البحثُ يُكتب `where(name)->orWhereHas(employees)` في مستوى
            الاستعلام الأعلى، ثم يُضاف شرطا التاريخ بعده. وأسبقيّةُ SQL
            تجعلها: `name LIKE x OR (employees AND from AND to)` — فأيُّ
            وردِيّةٍ يُطابق اسمُها تظهر مهما كان تاريخُها، والمرشِّحان
            يُطبَّقان على فرعٍ واحدٍ من الشرط.
        */
        if ($request->filled('keywords')) {
            $keywords = $request->keywords;

            $query->where(function ($outer) use ($keywords) {
                $outer->where('name', 'like', '%'.$keywords.'%')
                    ->orWhereHas('employees', function ($q) use ($keywords) {
                        // `full_name` مُعرَّفٌ في الموديل كـ accessor (getFullNameAttribute)
                        // لا كعمودٍ حقيقي، فيسقط أيُّ استعلامٍ يطلبه بـ«العمود غير موجود».
                        // البحثُ الفعليّ بالاسم يكون عبر `first_name` — تحقّقتُ منه حيّاً.
                        $q->where('employees.first_name', 'like', '%'.$keywords.'%')
                            ->orWhere('employees.employee_code', 'like', '%'.$keywords.'%');
                    });
            });
        }

        /*
            أعمدةُ الجدول `from_date`/`to_date` — لا `start_date`/`end_date`.

            وهذان لا وجودَ لهما في `custom_shifts` (انظر الهجرة، وانظر
            `store()` نفسَه فهو يكتب `from_date`/`to_date`). فكلُّ تصفيةٍ
            بتاريخٍ كانت تُسقط الاستعلام بـ«لا عمودَ بهذا الاسم» — بلا
            `try/catch` هنا، أي خمسُمئةٍ عاريةٌ في وجه المستخدم.

            و«من تاريخ» تعني الورديّاتِ التي تنتهي بعده، و«إلى تاريخ» التي
            تبدأ قبله — تقاطعُ مدّتَين، لا مقارنةُ طرفٍ بطرفٍ مقابل: مَن
            سأل عن شهرٍ يريد كلَّ وردِيّةٍ تمسّه لا ما بدأ وانتهى داخله.
        */
        if ($request->filled('from_date')) {
            $query->whereDate('to_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('from_date', '<=', $request->to_date);
        }

        $custom_shifts = $query->paginate($this->perPage($request))->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'html' => view('hr::attendance.custom_shifts.partials.table_rows', compact('custom_shifts'))->render(),
                'count' => $custom_shifts->count(),
                'total' => $custom_shifts->total(),
            ]);
        }

        return view('hr::attendance.custom_shifts.index', compact('custom_shifts'));
    }

    public function create()
    {
        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name'), 'id')->get();
        $branches = Branch::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();
        $job_titles = JopTitle::select('id', 'name')->get();
        $shifts = Shift::select('id', 'name')->get();

        return view('hr::attendance.custom_shifts.create',
            compact('employees', 'branches', 'departments', 'job_titles', 'shifts'));
    }

    public function store(CustomShiftRequest $request)
    {
        // قبل المعاملة: 403 لا يُبتلَع في catch أدناه
        $employeeIds = $this->scopedAssignment($request);

        try {
            DB::beginTransaction();

            $customShiftData = [
                'name' => $request['name'],
                'from_date' => $request['from_date'],
                'to_date' => $request['to_date'],
                'shift_id' => $request['shift_id'],
                // `<select>` لا صندوق اختيار: القيمتان "رئيسي" و"فرعي" كلتاهما
                // حاضرتان في الطلب، فـ`has()` تصحّ للاثنتين معاً — والفارقُ في
                // القيمة نفسها لا في وجودها.
                'is_main' => $request->input('is_main') === '1',
            ];

            if ($request['use_rules'] === 'employees') {
                $customShiftData['use_rules'] = 2;
            } elseif ($request['use_rules'] === 'rules') {
                $customShiftData = array_merge($customShiftData, [
                    'branch_id' => $request['branch_id'],
                    'department_id' => $request['department_id'],
                    'job_title_id' => $request['job_title_id'],
                    'shift_rule_id' => $request['shift_rule_id'],
                    'use_rules' => 1,
                ]);
            }

            $custom_shift = CustomShift::create($customShiftData);

            // في وضع القواعد يُجلب الموظفون منها ويُستبعد المحددون، وفي وضع
            // الموظفين يُؤخذ من اختير صراحةً.
            $custom_shift->employees()->sync($employeeIds);

            // Log the action
            ModelsLog::create([
                'type' => 'shift_custom_log',
                'type_id' => $custom_shift->id,
                'type_log' => 'create',
                'description' => 'تم إضافة وردية متخصصة جديدة: '.$custom_shift->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('custom_shifts.index')
                ->with(['success' => 'تم إضافة الوردية المتخصصة بنجاح!']);

        } catch (\Exception $exception) {
            DB::rollBack();

            // Log the error
            Log::error('Error creating custom shift: '.$exception->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with(['error' => 'حدث خطأ أثناء إضافة الوردية. يرجى المحاولة لاحقاً.']);
        }
    }

    public function edit($id)
    {
        $customShift = CustomShift::with(['employees' => $this->scopedEmployeesRelation(), 'shift', 'branch', 'department', 'jobTitle'])
            ->findOrFail($id);

        // تحقق من وجود العلاقة وتهيئة مصفوفة فارغة إذا كانت null
        $selectedEmployeeIds = [];
        if ($customShift->employees && $customShift->employees->isNotEmpty()) {
            $selectedEmployeeIds = $customShift->employees->pluck('id')->toArray();
        }

        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name', 'nickname'), 'id')->get();
        $branches = Branch::select('id', 'name')->get();
        $departments = Department::select('id', 'name')->get();
        $job_titles = JopTitle::select('id', 'name')->get();
        $shifts = Shift::select('id', 'name')->get();

        // قائمةُ الاستبعاد لا تُحفظ، فتُستنتج: مطابقو قواعد الوردية ناقصَ من
        // ارتبط بها فعلاً.
        $excludedEmployeeIds = $customShift->use_rules == 1
            ? $this->excludedEmployeeIdsFromRules([
                'branch_id' => $customShift->branch_id,
                'department_id' => $customShift->department_id,
                'job_title_id' => $customShift->job_title_id,
                'shift_id' => $customShift->shift_rule_id,
            ], $selectedEmployeeIds)
            : [];

        // المستبعَدون المعروضون من النطاق وحده — لا يُستنتج خارجُه مستبعَداً
        $excludedEmployeeIds = $this->onlyListable($excludedEmployeeIds);

        return view('hr::attendance.custom_shifts.edit',
            compact('customShift', 'employees', 'branches', 'departments', 'job_titles', 'shifts', 'selectedEmployeeIds', 'excludedEmployeeIds'));
    }

    public function update(CustomShiftRequest $request, $id)
    {
        $existingIds = CustomShift::findOrFail($id)->employees()->pluck('employees.id')->all();
        $employeeIds = $this->scopedAssignment($request, $existingIds);

        try {
            DB::beginTransaction();

            $custom_shift = CustomShift::findOrFail($id);
            $oldName = $custom_shift->name;

            $customShiftData = [
                'name' => $request['name'],
                'from_date' => $request['from_date'],
                'to_date' => $request['to_date'],
                'shift_id' => $request['shift_id'],
                // `<select>` لا صندوق اختيار: القيمتان "رئيسي" و"فرعي" كلتاهما
                // حاضرتان في الطلب، فـ`has()` تصحّ للاثنتين معاً — والفارقُ في
                // القيمة نفسها لا في وجودها.
                'is_main' => $request->input('is_main') === '1',
            ];

            if ($request['use_rules'] === 'employees') {
                $customShiftData = array_merge($customShiftData, [
                    'use_rules' => 2,
                    'branch_id' => null,
                    'department_id' => null,
                    'job_title_id' => null,
                    'shift_rule_id' => null,
                ]);
            } elseif ($request['use_rules'] === 'rules') {
                $customShiftData = array_merge($customShiftData, [
                    'branch_id' => $request['branch_id'],
                    'department_id' => $request['department_id'],
                    'job_title_id' => $request['job_title_id'],
                    'shift_rule_id' => $request['shift_rule_id'],
                    'use_rules' => 1,
                ]);
            }

            $custom_shift->update($customShiftData);

            $custom_shift->employees()->sync($employeeIds);

            // Log the action
            ModelsLog::create([
                'type' => 'shift_custom_log',
                'type_id' => $custom_shift->id,
                'type_log' => 'update',
                'description' => 'تم تعديل وردية متخصصة: '.$oldName.' → '.$custom_shift->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('custom_shifts.index')
                ->with(['success' => 'تم تعديل الوردية المتخصصة بنجاح!']);

        } catch (\Exception $exception) {
            DB::rollBack();

            // Log the error
            \Log::error('Error updating custom shift: '.$exception->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->with(['error' => 'حدث خطأ أثناء تعديل الوردية. يرجى المحاولة لاحقاً.']);
        }
    }

    public function show($id)
    {
        $custom_shift = CustomShift::with(['employees' => $this->scopedEmployeesRelation()])->findOrFail($id);
        $logs = ModelsLog::where('type', 'shift_custom_log')
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        return view('hr::attendance.custom_shifts.show', compact('custom_shift', 'logs'));
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $custom_shift = CustomShift::findOrFail($id);
            $shiftName = $custom_shift->name;

            // Log the action before deletion
            ModelsLog::create([
                'type' => 'shift_custom_log',
                'type_id' => $custom_shift->id,
                'type_log' => 'delete',
                'description' => 'تم حذف وردية متخصصة: '.$shiftName,
                'created_by' => auth()->id(),
            ]);

            // Detach all employees
            $custom_shift->employees()->detach();

            // Delete the custom shift
            $custom_shift->delete();

            DB::commit();

            // Check if request expects JSON (AJAX)
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف الوردية المتخصصة بنجاح!',
                ]);
            }

            return redirect()
                ->route('custom_shifts.index')
                ->with(['success' => 'تم حذف الوردية المتخصصة بنجاح!']);

        } catch (\Exception $exception) {
            DB::rollBack();

            // Log the error
            \Log::error('Error deleting custom shift: '.$exception->getMessage());

            // Check if request expects JSON (AJAX)
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف الوردية. يرجى المحاولة لاحقاً.',
                ], 500);
            }

            return redirect()
                ->route('custom_shifts.index')
                ->with(['error' => 'حدث خطأ أثناء حذف الوردية. يرجى المحاولة لاحقاً.']);
        }
    }

    /**
     * Get employees count for preview (AJAX endpoint)
     */
    public function getEmployeeCountPreview(Request $request)
    {
        try {
            $employeeQuery = $this->scopeToEmployees(Employee::query(), 'id');

            if ($request->branch_id) {
                BranchTree::apply($employeeQuery, $request->branch_id);
            }

            if ($request->department_id) {
                $employeeQuery->where('department_id', $request->department_id);
            }

            if ($request->job_title_id) {
                $employeeQuery->where('job_title_id', $request->job_title_id);
            }

            if ($request->shift_rule_id) {
                $employeeQuery->where('shift_id', $request->shift_rule_id);
            }

            $excludedIds = $this->normalizeEmployeeIds($request->input('excluded_employee_id'));
            if (! empty($excludedIds)) {
                $employeeQuery->whereNotIn('id', $excludedIds);
            }

            $count = $employeeQuery->count();
            // جدول employees لا يحتوي على last_name — الاسمُ الثالث في nickname
            // (انظر Employee::getFullNameAttribute). كان هذا يرمي 1054 أي 500.
            $employees = $employeeQuery->select('id', 'first_name', 'middle_name', 'nickname', 'employee_code')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'count' => $count,
                'employees' => $employees,
                'message' => $count > 0 ? "سيتم تطبيق الوردية على {$count} موظف" : 'لا يوجد موظفين يطابقون هذه المعايير',
            ]);

        } catch (\Exception $exception) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($exception, 'HR.CustomShifts', 'getEmployeeCountPreview');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
            ], 500);
        }
    }

    /**
     * Duplicate custom shift
     */
    public function duplicate($id)
    {
        try {
            DB::beginTransaction();

            $originalShift = CustomShift::with('employees')->findOrFail($id);

            // Create new shift with duplicated data
            $newShift = $originalShift->replicate();
            $newShift->name = $originalShift->name.' - نسخة';
            $newShift->created_at = now();
            $newShift->updated_at = now();
            $newShift->save();

            // Duplicate employee associations
            $employeeIds = $originalShift->employees->pluck('id')->toArray();
            $newShift->employees()->attach($employeeIds);

            // Log the action
            ModelsLog::create([
                'type' => 'shift_custom_log',
                'type_id' => $newShift->id,
                'type_log' => 'duplicate',
                'description' => 'تم تكرار وردية متخصصة من: '.$originalShift->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('custom_shifts.edit', $newShift->id)
                ->with(['success' => 'تم تكرار الوردية بنجاح! يمكنك تعديل البيانات حسب الحاجة.']);

        } catch (\Exception $exception) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($exception, 'HR.CustomShifts', 'duplicate');

            DB::rollBack();

            return redirect()
                ->route('custom_shifts.index')
                ->with(['error' => 'حدث خطأ أثناء تكرار الوردية.']);
        }
    }

    /**
     * Toggle shift status (activate/deactivate)
     */
    public function toggleStatus($id)
    {
        try {
            $custom_shift = CustomShift::findOrFail($id);
            $custom_shift->is_active = ! $custom_shift->is_active;
            $custom_shift->save();

            $status = $custom_shift->is_active ? 'تفعيل' : 'إلغاء تفعيل';

            // Log the action
            ModelsLog::create([
                'type' => 'shift_custom_log',
                'type_id' => $custom_shift->id,
                'type_log' => 'status_change',
                'description' => 'تم '.$status.' وردية متخصصة: '.$custom_shift->name,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'is_active' => $custom_shift->is_active,
                'message' => 'تم '.$status.' الوردية بنجاح',
            ]);

        } catch (\Exception $exception) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($exception, 'HR.CustomShifts', 'toggleStatus');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير حالة الوردية',
            ], 500);
        }
    }

    /**
     * Get custom shifts data for DataTables (AJAX)
     */
    public function getCustomShiftsData(Request $request)
    {
        $query = CustomShift::with(['shift:id,name', 'branch:id,name', 'department:id,name'])
            ->withCount(['employees' => $this->scopedEmployeesRelation()]);

        // Apply filters
        if ($request->has('is_main') && $request->is_main !== '') {
            $query->where('is_main', $request->is_main);
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('from_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('to_date', '<=', $request->date_to);
        }

        // Search functionality
        if ($request->has('search') && $request->search['value']) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhereHas('shift', function ($subQ) use ($searchValue) {
                        $subQ->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $totalRecords = $query->count();

        // Apply pagination
        if ($request->has('start') && $request->has('length')) {
            $query->skip($request->start)->take($request->length);
        }

        // Apply sorting
        if ($request->has('order')) {
            $orderColumn = $request->columns[$request->order[0]['column']]['data'];
            $orderDirection = $request->order[0]['dir'];

            switch ($orderColumn) {
                case 'name':
                    $query->orderBy('name', $orderDirection);
                    break;
                case 'from_date':
                    $query->orderBy('from_date', $orderDirection);
                    break;
                case 'to_date':
                    $query->orderBy('to_date', $orderDirection);
                    break;
                default:
                    $query->orderBy('id', 'desc');
            }
        } else {
            $query->orderBy('id', 'desc');
        }

        $customShifts = $query->get();

        $data = $customShifts->map(function ($shift) {
            return [
                'id' => $shift->id,
                'name' => $shift->name,
                'shift_name' => $shift->shift->name ?? 'غير محدد',
                'is_main' => $shift->is_main,
                'from_date' => $shift->from_date,
                'to_date' => $shift->to_date,
                'employees_count' => $shift->employees_count,
                'is_active' => $shift->is_active ?? true,
                'created_at' => $shift->created_at->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ]);
    }
}
