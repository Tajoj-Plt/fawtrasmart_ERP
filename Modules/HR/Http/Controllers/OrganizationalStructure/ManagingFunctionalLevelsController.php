<?php

namespace Modules\HR\Http\Controllers\OrganizationalStructure;

use App\Http\Controllers\Controller;
use App\Models\FunctionalLevels;
use Illuminate\Http\Request;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\AppliesOrgSettings;
use Modules\HR\Http\Controllers\OrganizationalStructure\Concerns\OrgStructureSupport;

class ManagingFunctionalLevelsController extends Controller
{
    use AppliesOrgSettings, OrgStructureSupport;

    private const LOG_TYPE = 'FunctionalLevels';

    /** ما يشير إلى المستوى بـ`ON DELETE CASCADE` — راجع `OrgStructureSupport`. */
    private const USAGE_REFS = [
        'الموظفون' => ['employees', 'job_level_id'],
        'العقود' => ['contracts', 'job_level_id'],
    ];

    public function __construct()
    {
        /*
            لكلّ فعلٍ في هذه الشاشة صلاحيتُه، ولا تشترك مع بقيّة شاشات
            الهيكل التنظيمي في شيء: `job_levels.*` تخصّها وحدها.
        */
        $this->middleware('permission.role:job_levels.index', ['only' => ['index']]);
        $this->middleware('permission.role:job_levels.show', ['only' => ['show']]);
        $this->middleware('permission.role:job_levels.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:job_levels.edit', ['only' => ['edit', 'update', 'updateStatus']]);
        $this->middleware('permission.role:job_levels.destroy', ['only' => ['delete']]);
    }

    public function index()
    {
        $query = FunctionalLevels::query()
            ->select('functional_levels.*')
            ->addSelect(['employees_count' => $this->scopedEmployeeCountSubquery('job_level_id', 'functional_levels.id')]);

        // البحث بالاسم
        if (request()->filled('name')) {
            $query->where('name', 'like', '%'.request('name').'%');
        }

        // التصفية بالحالة
        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        $functionalLevels = $query->orderBy('id', 'desc')->paginate((int) request('per_page', 10))->withQueryString();
        $stats = $this->listStats(FunctionalLevels::class, 'job_level_id');

        return view('hr::organizational_structure.ManagingFunctionalLevels.index', compact('functionalLevels', 'stats'));
    }

    /**
     * `status` كان بلا قاعدة في هذه الشاشة وحدها، والعمودُ `NOT NULL` —
     * فطلبٌ بلا حالة يرتدّ خطأَ قاعدة بيانات خاماً بدل رسالة نموذج.
     */
    private function rules(?int $id = null): array
    {
        return [
            // فرادةُ الاسم وإلزاميّةُ الوصف من «إعدادات الموظفين والهيكل
            // التنظيمي» — و`$id` يتجاوز السجلَّ نفسَه عند التعديل
            'name' => array_merge(
                ['required', 'string', 'max:255'],
                $this->orgUniqueName('functional_levels', 'org_job_level_rules', 'unique_name', $id)
            ),
            'status' => 'required|in:0,1',
            'description' => [$this->orgRequired('org_job_level_rules', 'require_description'), 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'اسم المستوى الوظيفي مطلوب.',
            'status.required' => 'الحالة مطلوبة.',
            'status.in' => 'الحالة يجب أن تكون نشط أو غير نشط.',
            'description.max' => 'الوصف لا يزيد على 255 حرفاً.',
            'name.unique' => 'يوجد سجل بهذا الاسم في «المستويات الوظيفية» — فرادةُ الأسماء مشغَّلة في إعدادات الموظفين.',
            'description.required' => 'وصف المستوى الوظيفي مطلوب — حسب إعدادات الموظفين.',
        ];
    }

    public function create()
    {
        return view('hr::organizational_structure.ManagingFunctionalLevels.create');
    }

    public function store(Request $request)
    {
        $request->validate($this->rules(), $this->messages());

        $level = FunctionalLevels::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
        ]);

        $this->logActivity(self::LOG_TYPE, $level->id, 'تم إضافة مستوى وظيفي **'.$level->name.'**');

        return redirect()->route('ManagingFunctionalLevels.index')->with('success', 'تمت إضافة المستوى الوظيفي بنجاح');
    }

    public function show($id)
    {
        $level = FunctionalLevels::findOrFail($id);
        $logs = $this->activityLogs(self::LOG_TYPE, (int) $id);
        $members = $this->scopedMembers('job_level_id', (int) $id);
        $usage = $this->orgUsage(self::USAGE_REFS, (int) $id);

        return view('hr::organizational_structure.ManagingFunctionalLevels.show', compact('level', 'logs', 'members', 'usage'));
    }

    public function updateStatus($id)
    {
        $level = FunctionalLevels::find($id);

        if (! $level) {
            return redirect()->route('ManagingFunctionalLevels.index')->with(['error' => 'المستوى الوظيفي غير موجود!']);
        }

        $level->update(['status' => $level->status ? 0 : 1]);

        // 0 = نشط في هذا الجدول (كبقيّة الهيكل): كان النصُّ مقلوباً فيُسجِّل «تفعيل» عند التعطيل
        $statusText = $level->status ? 'تم تعطيل المستوى الوظيفي' : 'تم تفعيل المستوى الوظيفي';

        $this->logActivity(self::LOG_TYPE, (int) $id, $statusText.' **'.$level->name.'**');

        return redirect()->route('ManagingFunctionalLevels.show', $id)->with(['success' => 'تم تحديث حالة المستوى الوظيفي بنجاح!']);
    }

    public function edit($id)
    {
        $functionalLevel = FunctionalLevels::findOrFail($id);

        return view('hr::organizational_structure.ManagingFunctionalLevels.edit', compact('functionalLevel'));
    }

    public function update(Request $request, $id)
    {
        $level = FunctionalLevels::findOrFail($id);

        $request->validate($this->rules((int) $id), $this->messages());

        $oldName = $level->name;

        $level->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
        ]);

        $this->logActivity(self::LOG_TYPE, (int) $id, $oldName === $request->name
            ? 'تم تعديل بيانات المستوى الوظيفي **'.$level->name.'**'
            : 'تم تغيير المستوى الوظيفي من **'.$oldName.'** إلى **'.$request->name.'**');

        return redirect()->route('ManagingFunctionalLevels.index')->with('success', 'تم تعديل المستوى الوظيفي بنجاح');
    }

    public function delete($id)
    {
        $level = FunctionalLevels::findOrFail($id);

        $usage = $this->orgUsage(self::USAGE_REFS, (int) $level->id);
        if ($usage !== []) {
            return redirect()->back()->with(['error' => $this->usageMessage('المستوى الوظيفي', $usage)]);
        }

        $this->logActivity(self::LOG_TYPE, (int) $id, 'تم حذف المستوى الوظيفي **'.$level->name.'**');

        $level->delete();

        return redirect()->route('ManagingFunctionalLevels.index')->with('success', 'تم حذف المستوى الوظيفي بنجاح');
    }
}
