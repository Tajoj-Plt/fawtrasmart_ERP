<?php

namespace Modules\HR\Http\Controllers\OrganizationalStructure;

use App\Exports\DepartmentsExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JopTitle;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\AppliesOrgSettings;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\OrgStructureSupport;

class DepartmentController extends Controller
{
    use AppliesOrgSettings, OrgStructureSupport;

    /** نوعُ سجلّ النشاط لهذه الشاشة — والحذفُ كان يكتب `struct_log` (نوعَ المسمّيات) فيضيع سطرُه. */
    private const LOG_TYPE = 'department_struct_log';

    /**
     * ما يشير إلى القسم في قاعدة البيانات بـ`ON DELETE CASCADE` — راجع
     * `OrgStructureSupport`. (`department_employee` جدولُ المديرين ويُحذف معه
     * بلا ضرر، و`workspaces` مربوطٌ بـ`SET NULL`.)
     */
    private const USAGE_REFS = [
        'الموظفون' => ['employees', 'department_id'],
        'المسمّيات الوظيفية' => ['jop_titles', 'department_id'],
        'مسيرات الرواتب' => ['payrolls', 'department_id'],
        'كشوف الحضور' => ['attendance_sheets', 'department_id'],
        'الورديات المخصّصة' => ['custom_shifts', 'department_id'],
        'تخصيصات العطل' => ['holy_day_list_customizes', 'department_id'],
        'تخصيصات سياسات الإجازات' => ['leave_policy_customizes', 'department_id'],
    ];

    public function __construct()
    {
        /*
            لكلّ فعلٍ في هذه الشاشة صلاحيتُه، ولا تشترك مع بقيّة شاشات
            الهيكل التنظيمي في شيء: `departments.*` تخصّها وحدها.
        */
        $this->middleware('permission.role:departments.index', ['only' => ['index', 'export', 'export_view']]);
        $this->middleware('permission.role:departments.show', ['only' => ['show']]);
        $this->middleware('permission.role:departments.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:departments.edit', ['only' => ['edit', 'update', 'updateStatus']]);
        $this->middleware('permission.role:departments.destroy', ['only' => ['delete']]);
    }

    public function index()
    {
        $query = Department::query()
            ->select('departments.*')
            ->withCount('employees')
            // موظفو القسم (`employees.department_id`) داخل نطاق الرؤية — لا
            // مديروه في `department_employee` كما كان العمود يعرض تحت «عدد الموظفين».
            ->addSelect(['members_count' => $this->scopedEmployeeCountSubquery('department_id', 'departments.id')]);

        // البحث بالاسم أو الاختصار
        if (request()->filled('name')) {
            $term = '%'.request('name').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('short_name', 'like', $term));
        }

        // التصفية بالحالة
        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        $departments = $query->orderByDesc('id')->paginate((int) request('per_page', 10))->withQueryString();
        $stats = $this->listStats(Department::class, 'department_id');

        return view('hr::organizational_structure.department.index', compact('departments', 'stats'));
    }

    public function create()
    {
        // منتقي الموظفين من نطاق المستخدم وحده
        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name'), 'id')->get();

        return view('hr::organizational_structure.department.create', compact('employees'));
    }

    /**
     * إلزاميّةُ الاختصار والوصف والمدير، وفرادةُ الاسم — كلُّها من «إعدادات
     * الموظفين والهيكل التنظيمي» (تبويب «الهيكل التنظيمي»).
     *
     * `$id` يُمرَّر عند التعديل كي تتجاوزه قاعدةُ الفرادة: القسمُ لا يُشكى
     * من اسمه هو.
     */
    private function rules(?int $id = null): array
    {
        return [
            'name' => array_merge(
                ['required', 'string', 'max:255'],
                $this->orgUniqueName('departments', 'org_department_rules', 'unique_name', $id)
            ),
            'short_name' => [$this->orgRequired('org_department_rules', 'require_short_name'), 'string', 'max:50'],
            'status' => 'required|in:0,1',
            'description' => [$this->orgRequired('org_department_rules', 'require_description'), 'string'],
            // «مديرو القسم» — منتقٍ متعدّد، فالإلزامُ يعني مديراً واحداً على الأقل
            'employee_id' => [$this->orgRequired('org_department_rules', 'require_manager'), 'array', 'min:1'],
            'employee_id.*' => 'exists:employees,id',
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'الرجاء إدخال اسم القسم.',
            'name.unique' => 'يوجد قسم بهذا الاسم — فرادةُ أسماء الأقسام مشغَّلة في إعدادات الموظفين.',
            'status.required' => 'الرجاء تحديد الحالة.',
            'status.in' => 'الحالة يجب أن تكون نشط أو غير نشط.',
            'short_name.required' => 'الاختصار مطلوب — حسب إعدادات الموظفين.',
            'description.required' => 'وصف القسم مطلوب — حسب إعدادات الموظفين.',
            'employee_id.required' => 'يجب اختيار مدير واحد للقسم على الأقل — حسب إعدادات الموظفين.',
            'employee_id.min' => 'يجب اختيار مدير واحد للقسم على الأقل — حسب إعدادات الموظفين.',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules(), $this->messages());

        $department = Department::create([
            'name' => $request->name,
            'short_name' => $request->short_name,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        $this->logActivity(self::LOG_TYPE, $department->id, 'تم إضافة قسم وظيفي **'.$department->name.'**');

        if ($request->has('employee_id')) {
            $department->employees()->sync($this->scopedMemberSync($department, (array) $request->employee_id));
        }

        return redirect()->route('department.index')->with(['success' => 'تمت إضافة القسم بنجاح']);
    }

    public function show($id)
    {
        $department = Department::with('employees.job_title:id,name')->findOrFail($id);
        $logs = $this->activityLogs(self::LOG_TYPE, (int) $id);
        $members = $this->scopedMembers('department_id', (int) $id);
        $usage = $this->orgUsage(self::USAGE_REFS, (int) $id);
        $titles = JopTitle::where('department_id', $id)->orderBy('name')->get(['id', 'name', 'status']);

        return view('hr::organizational_structure.department.show', compact('department', 'logs', 'members', 'usage', 'titles'));
    }

    public function edit($id)
    {
        $department = Department::findOrFail($id);
        // منتقي الموظفين من نطاق المستخدم وحده
        $employees = $this->scopeToEmployees(Employee::select('id', 'first_name', 'middle_name'), 'id')->get();
        $selectedEmployees = $department->employees->pluck('id')->toArray();

        return view('hr::organizational_structure.department.edit', compact('employees', 'department', 'selectedEmployees'));
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        $oldName = $department->name;

        $request->validate($this->rules((int) $department->id), $this->messages());

        $department->update([
            'name' => $request->name,
            'short_name' => $request->short_name,
            'status' => $request->status,
            'description' => $request->description,
        ]);

        $this->logActivity(self::LOG_TYPE, $department->id, $oldName === $request->name
            ? 'تم تعديل بيانات القسم الوظيفي **'.$department->name.'**'
            : 'تم تغيير القسم الوظيفي من **'.$oldName.'** إلى **'.$request->name.'**');

        /*
            المنتقي في نموذج التعديل يُرسَل دائماً — فغيابُ `employee_id` يعني
            أن المستخدم أفرغه، لا أنه لم يلمسه. كان الشرطُ `has('employee_id')`
            يجعل إزالةَ آخرِ مديرٍ مستحيلة: المنتقي الفارغ لا يُرسَل أصلاً.
        */
        $department->employees()->sync($this->scopedMemberSync($department, (array) $request->input('employee_id', [])));

        return redirect()->route('department.index')->with(['success' => 'تم تحديث بيانات القسم بنجاح']);
    }

    public function delete($id)
    {
        $department = Department::findOrFail($id);

        $usage = $this->orgUsage(self::USAGE_REFS, (int) $department->id);
        if ($usage !== []) {
            return redirect()->back()->with(['error' => $this->usageMessage('القسم', $usage)]);
        }

        $this->logActivity(self::LOG_TYPE, $department->id, 'تم حذف القسم الوظيفي **'.$department->name.'**');

        $department->delete();

        return redirect()->route('department.index')->with(['success' => 'تم حذف القسم بنجاح']);
    }

    public function updateStatus($id)
    {
        $department = Department::find($id);

        if (! $department) {
            return redirect()->route('department.index')->with(['error' => 'القسم غير موجود!']);
        }

        $department->update(['status' => $department->status ? 0 : 1]);

        $statusText = $department->status ? 'تم تعطيل القسم الوظيفي' : 'تم تفعيل القسم الوظيفي';

        $this->logActivity(self::LOG_TYPE, (int) $id, $statusText.' **'.$department->name.'**');

        return redirect()->route('department.show', $id)->with(['success' => 'تم تحديث حالة القسم بنجاح!']);
    }

    /**
     * أعضاءُ القسم بعد الحفظ: ما اختاره المستخدم **من داخل نطاقه**، ومن
     * كان عضواً خارجَ نطاقه يبقى كما هو — المنتقي لا يعرضهم، فغيابُهم عن
     * الطلب ليس طلباً بإزالتهم.
     *
     * @return array<int>
     */
    private function scopedMemberSync(Department $department, array $submitted): array
    {
        $allowed = $this->accessibleEmployeeIds();
        $submitted = array_map('intval', array_filter($submitted));

        if ($allowed === null) {
            return $submitted;
        }

        $kept = $department->exists
            ? $department->employees()->pluck('employees.id')->map(fn ($id) => (int) $id)->reject(fn ($id) => in_array($id, $allowed, true))->all()
            : [];

        return array_values(array_unique(array_merge(array_intersect($submitted, $allowed), $kept)));
    }

    public function export_view()
    {
        return view('hr::organizational_structure.department.export');
    }

    public function export(Request $request)
    {

        $fields = $request->input('fields', []);

        if (empty($fields)) {
            return redirect()->back()->with(['error' => 'يرجى تحديد الحقول للتصدير!']);
        }

        return Excel::download(new DepartmentsExport($fields), 'departments.xlsx');
    }
}
