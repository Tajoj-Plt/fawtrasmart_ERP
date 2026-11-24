<?php

namespace Modules\HR\Http\Controllers\OrganizationalStructure;

use App\Http\Controllers\Controller;
use App\Models\TypesJobs;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\AppliesOrgSettings;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\OrgStructureSupport;

class ManagingJobTypesController extends Controller
{
    use AppliesOrgSettings, OrgStructureSupport;

    private const LOG_TYPE = 'TypesJobs_log';

    /** ما يشير إلى النوع بـ`ON DELETE CASCADE` — راجع `OrgStructureSupport`. */
    private const USAGE_REFS = [
        'الموظفون' => ['employees', 'job_type_id'],
    ];

    public function __construct()
    {
        /*
            لكلّ فعلٍ في هذه الشاشة صلاحيتُه، ولا تشترك مع بقيّة شاشات
            الهيكل التنظيمي في شيء: `job_types.*` تخصّها وحدها.
        */
        $this->middleware('permission.role:job_types.index', ['only' => ['index']]);
        $this->middleware('permission.role:job_types.show', ['only' => ['show']]);
        $this->middleware('permission.role:job_types.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:job_types.edit', ['only' => ['edit', 'update', 'updateStatus']]);
        $this->middleware('permission.role:job_types.destroy', ['only' => ['delete']]);
    }

    public function index()
    {
        $query = TypesJobs::query()
            ->select('types_jobs.*')
            ->addSelect(['employees_count' => $this->scopedEmployeeCountSubquery('job_type_id', 'types_jobs.id')]);

        // البحث بالاسم
        if (request()->filled('name')) {
            $query->where('name', 'like', '%'.request('name').'%');
        }

        // التصفية بالحالة
        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        $types = $query->orderBy('id', 'desc')->paginate((int) request('per_page', 10))->withQueryString();
        $stats = $this->listStats(TypesJobs::class, 'job_type_id');

        return view('hr::organizational_structure.ManagingJobTypes.index', compact('types', 'stats'));
    }

    private function rules(?int $id = null): array
    {
        return [
            // فرادةُ الاسم وإلزاميّةُ الوصف من «إعدادات الموظفين والهيكل
            // التنظيمي» — و`$id` يتجاوز السجلَّ نفسَه عند التعديل
            'name' => array_merge(
                ['required', 'string', 'max:255'],
                $this->orgUniqueName('types_jobs', 'org_job_type_rules', 'unique_name', $id)
            ),
            'status' => 'required|in:0,1',
            'description' => [$this->orgRequired('org_job_type_rules', 'require_description'), 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'اسم نوع الوظيفة مطلوب.',
            'status.required' => 'الحالة مطلوبة.',
            'status.in' => 'الحالة يجب أن تكون نشط أو غير نشط.',
            'description.max' => 'الوصف لا يزيد على 255 حرفاً.',
            'name.unique' => 'يوجد سجل بهذا الاسم في «أنواع الوظائف» — فرادةُ الأسماء مشغَّلة في إعدادات الموظفين.',
            'description.required' => 'وصف نوع الوظيفة مطلوب — حسب إعدادات الموظفين.',
        ];
    }

    public function create()
    {
        return view('hr::organizational_structure.ManagingJobTypes.create');
    }

    public function store(Request $request)
    {
        $request->validate($this->rules(), $this->messages());

        $type = new TypesJobs;
        $type->name = $request->name;
        $type->description = $request->description;
        $type->status = $request->status;
        $type->save();

        $this->logActivity(self::LOG_TYPE, $type->id, 'تم إضافة نوع وظيفي **'.$type->name.'**');

        return redirect()->route('ManagingJobTypes.index')->with(['success' => 'تمت إضافة نوع الوظيفة بنجاح']);
    }

    public function show($id)
    {
        $type = TypesJobs::findOrFail($id);
        $logs = $this->activityLogs(self::LOG_TYPE, (int) $id);
        $members = $this->scopedMembers('job_type_id', (int) $id);
        $usage = $this->orgUsage(self::USAGE_REFS, (int) $id);

        return view('hr::organizational_structure.ManagingJobTypes.show', compact('type', 'logs', 'members', 'usage'));
    }

    public function edit($id)
    {
        $type = TypesJobs::findOrFail($id);

        return view('hr::organizational_structure.ManagingJobTypes.edit', compact('type'));
    }

    public function update(Request $request, $id)
    {
        $type = TypesJobs::findOrFail($id);

        $request->validate($this->rules((int) $id), $this->messages());

        // كان الاسمُ القديم يُقرأ **بعد** إسناد الجديد، فيُسجَّل «من X إلى X»
        $oldName = $type->name;

        $type->name = $request->name;
        $type->description = $request->description;
        $type->status = $request->status;
        $type->save();

        $this->logActivity(self::LOG_TYPE, $type->id, $oldName === $request->name
            ? 'تم تعديل بيانات النوع الوظيفي **'.$type->name.'**'
            : 'تم تغيير النوع الوظيفي من **'.$oldName.'** إلى **'.$request->name.'**');

        return redirect()->route('ManagingJobTypes.index')->with(['success' => 'تم تعديل نوع الوظيفة بنجاح']);
    }

    public function delete($id)
    {
        $type = TypesJobs::findOrFail($id);

        $usage = $this->orgUsage(self::USAGE_REFS, (int) $type->id);
        if ($usage !== []) {
            return redirect()->back()->with(['error' => $this->usageMessage('نوع الوظيفة', $usage)]);
        }

        $this->logActivity(self::LOG_TYPE, (int) $id, 'تم حذف النوع الوظيفي **'.$type->name.'**');

        $type->delete();

        return redirect()->route('ManagingJobTypes.index')->with(['success' => 'تم حذف نوع الوظيفة بنجاح']);
    }

    public function updateStatus($id)
    {
        $type = TypesJobs::find($id);

        if (! $type) {
            return redirect()->route('ManagingJobTypes.index')->with(['error' => 'نوع الوظيفة غير موجود!']);
        }

        $type->update(['status' => $type->status ? 0 : 1]);

        $statusText = $type->status ? 'تم تعطيل النوع الوظيفي' : 'تم تفعيل النوع الوظيفي';

        $this->logActivity(self::LOG_TYPE, (int) $id, $statusText.' **'.$type->name.'**');

        return redirect()->route('ManagingJobTypes.show', $id)->with(['success' => 'تم تحديث حالة نوع الوظيفة بنجاح!']);
    }
}
