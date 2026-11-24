<?php

namespace Modules\HR\Http\Controllers\OrganizationalStructure;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JopTitle;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\AppliesOrgSettings;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\OrgStructureSupport;

class JobTitleController extends Controller
{
    use AppliesOrgSettings, OrgStructureSupport;

    private const LOG_TYPE = 'struct_log';

    /** ما يشير إلى المسمّى بـ`ON DELETE CASCADE` — راجع `OrgStructureSupport`. */
    private const USAGE_REFS = [
        'الموظفون' => ['employees', 'job_title_id'],
        'العقود' => ['contracts', 'job_title_id'],
        'مسيرات الرواتب' => ['payrolls', 'jop_title_id'],
        'كشوف الحضور' => ['attendance_sheets', 'job_title_id'],
        'الورديات المخصّصة' => ['custom_shifts', 'job_title_id'],
        'تخصيصات العطل' => ['holy_day_list_customizes', 'job_title_id'],
        'تخصيصات سياسات الإجازات' => ['leave_policy_customizes', 'job_title_id'],
    ];

    public function __construct()
    {
        /*
            لكلّ فعلٍ في هذه الشاشة صلاحيتُه، ولا تشترك مع بقيّة شاشات
            الهيكل التنظيمي في شيء: `job_titles.*` تخصّها وحدها.
        */
        $this->middleware('permission.role:job_titles.index', ['only' => ['index']]);
        $this->middleware('permission.role:job_titles.show', ['only' => ['show']]);
        $this->middleware('permission.role:job_titles.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:job_titles.edit', ['only' => ['edit', 'update', 'updateStatus']]);
        $this->middleware('permission.role:job_titles.destroy', ['only' => ['delete']]);
    }

    public function index()
    {
        $query = JopTitle::query()
            ->select('jop_titles.*')
            ->with('department:id,name')
            ->addSelect(['employees_count' => $this->scopedEmployeeCountSubquery('job_title_id', 'jop_titles.id')]);

        // البحث بالاسم
        if (request()->filled('name')) {
            $query->where('name', 'like', '%'.request('name').'%');
        }

        // التصفية بالحالة
        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        // التصفية بالقسم
        if (request()->filled('department_id')) {
            $query->where('department_id', request('department_id'));
        }

        $titles = $query->orderBy('id', 'desc')->paginate((int) request('per_page', 10))->withQueryString();
        $departments = Department::select('id', 'name')->orderBy('name')->get();
        $stats = $this->listStats(JopTitle::class, 'job_title_id');

        return view('hr::organizational_structure.JobTitle.index', compact('titles', 'departments', 'stats'));
    }

    /**
     * قواعدُ المسمّى الوظيفي — واحدةٌ للإضافة والتعديل.
     *
     * `department_id` كان بلا قاعدةٍ إطلاقاً بينما `jop_titles.department_id`
     * عمودٌ `NOT NULL` بلا قيمةٍ افتراضية. فحفظُ مسمّى بلا قسمٍ كان يمرّ
     * التحقّقَ ويصل إلى قاعدة البيانات، فيرتدّ
     * `SQLSTATE[23000] … Column 'department_id' cannot be null` خاماً في
     * وجه المستخدم — رسالةُ محرّكٍ لا رسالةُ نموذج.
     */
    private function titleRules(?int $id = null, mixed $departmentId = null): array
    {
        return [
            /* الفرادةُ داخل القسم لا في الجدول كلِّه: «محاسب» في المالية وفي
               المشتريات مسمّيان مختلفان، و«محاسب» مرّتين في المالية خطأُ إدخال.
               وإن كان القسمُ فارغاً (ولم يكن إلزاميّاً) تُقاس الفرادةُ على
               القسم الفارغ نفسه — وهو الصحيح: مسمّيان بلا قسمٍ وبنفس الاسم
               تكرارٌ كذلك. */
            'name' => array_merge(
                ['required', 'string', 'max:255'],
                $this->orgUniqueName('jop_titles', 'org_job_title_rules', 'unique_name_per_department', $id, [
                    'department_id' => $departmentId ?: null,
                ])
            ),
            'status' => 'required|in:0,1',
            /* القسمُ كان `required` مثبَّتاً، وبقي الافتراضُ مشغَّلاً — لكنّه
               صار مفتاحاً: منشأةٌ تضع مسمّياتها قبل أقسامها تستطيع إطفاءه. */
            'department_id' => [$this->orgRequired('org_job_title_rules', 'require_department'), 'exists:departments,id'],
            'description' => [$this->orgRequired('org_job_title_rules', 'require_description'), 'string', 'max:255'],
        ];
    }

    private function titleMessages(): array
    {
        return [
            'name.required' => 'اسم المسمّى الوظيفي مطلوب.',
            'status.required' => 'الحالة مطلوبة.',
            'status.in' => 'الحالة يجب أن تكون نشط أو غير نشط.',
            'name.unique' => 'يوجد مسمّى وظيفي بهذا الاسم في القسم نفسه — فرادةُ المسمّيات مشغَّلة في إعدادات الموظفين.',
            'department_id.required' => 'القسم مطلوب — لا يُحفظ مسمّى وظيفي بلا قسم.',
            'description.required' => 'وصف المسمّى الوظيفي مطلوب — حسب إعدادات الموظفين.',
            'department_id.exists' => 'القسم المحدَّد غير موجود.',
            'description.max' => 'الوصف لا يزيد على 255 حرفاً.',
        ];
    }

    public function create()
    {
        $departments = Department::select('id', 'name', 'status')->orderBy('name')->get();

        return view('hr::organizational_structure.JobTitle.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $request->validate($this->titleRules(null, $request->input('department_id')), $this->titleMessages());

        $title = new JopTitle;
        $title->name = $request->name;
        $title->description = $request->description;
        $title->status = $request->status;
        $title->department_id = $request->department_id;
        $title->save();

        $this->logActivity(self::LOG_TYPE, $title->id, 'تم إضافة مسمى وظيفي **'.$title->name.'**');

        return redirect()->route('JobTitles.index')->with(['success' => 'تمت إضافة المسمى الوظيفي بنجاح']);
    }

    public function show($id)
    {
        $title = JopTitle::with('department:id,name')->findOrFail($id);
        $logs = $this->activityLogs(self::LOG_TYPE, (int) $id);
        $members = $this->scopedMembers('job_title_id', (int) $id);
        $usage = $this->orgUsage(self::USAGE_REFS, (int) $id);

        return view('hr::organizational_structure.JobTitle.show', compact('title', 'logs', 'members', 'usage'));
    }

    public function edit($id)
    {
        $departments = Department::select('id', 'name', 'status')->orderBy('name')->get();
        $title = JopTitle::findOrFail($id);

        return view('hr::organizational_structure.JobTitle.edit', compact('title', 'departments'));
    }

    public function update(Request $request, $id)
    {
        $title = JopTitle::findOrFail($id);

        $request->validate($this->titleRules((int) $title->id, $request->input('department_id')), $this->titleMessages());

        $oldName = $title->name;
        $title->name = $request->name;
        $title->description = $request->description;
        $title->status = $request->status;
        $title->department_id = $request->department_id;
        $title->save();

        $this->logActivity(self::LOG_TYPE, $title->id, $oldName === $request->name
            ? 'تم تعديل بيانات المسمى الوظيفي **'.$title->name.'**'
            : 'تم تغيير المسمى الوظيفي من **'.$oldName.'** إلى **'.$request->name.'**');

        return redirect()->route('JobTitles.index')->with(['success' => 'تم تعديل المسمى الوظيفي بنجاح']);
    }

    public function delete($id)
    {
        $title = JopTitle::findOrFail($id);

        $usage = $this->orgUsage(self::USAGE_REFS, (int) $title->id);
        if ($usage !== []) {
            return redirect()->back()->with(['error' => $this->usageMessage('المسمى الوظيفي', $usage)]);
        }

        $this->logActivity(self::LOG_TYPE, $title->id, 'تم حذف المسمى الوظيفي **'.$title->name.'**');
        $title->delete();

        return redirect()->route('JobTitles.index')->with(['success' => 'تم حذف المسمى الوظيفي بنجاح']);
    }

    public function updateStatus($id)
    {
        $title = JopTitle::find($id);

        if (! $title) {
            return redirect()->route('JobTitles.index')->with(['error' => 'المسمى الوظيفي غير موجود!']);
        }

        $title->update(['status' => $title->status ? 0 : 1]);

        $statusText = $title->status ? 'تم تعطيل المسمى الوظيفي' : 'تم تفعيل المسمى الوظيفي';

        $this->logActivity(self::LOG_TYPE, (int) $id, $statusText.' **'.$title->name.'**');

        return redirect()->route('JobTitles.show', $id)->with(['success' => 'تم تحديث حالة المسمى الوظيفي بنجاح!']);
    }
}
