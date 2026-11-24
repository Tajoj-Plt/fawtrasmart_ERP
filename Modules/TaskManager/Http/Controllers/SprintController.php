<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Helpers\TaskSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Support\OperationLog;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\TaskManager\Services\ProjectAccess;
use Modules\TaskManager\Services\TaskNotifier;
use Modules\TaskManager\Services\TaskStatusService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SprintController extends Controller
{
    /** وحدة السجلّ — بها يربط `logs`/`event_logs` أسطرَ هذه الشاشة. */
    private const LOG_MODULE = 'TaskManager.Sprint';

    public function __construct()
    {
        /*
            حارسٌ في المتحكّم فوق حارس المسار.

            دورةُ حياة السبرنت مفصولةٌ عن تعديله: الإقفال يُثبّت قياس
            الأداء (والمهامّ غير المنجزة تبقى فيه كما هي)، وإعادةُ الفتح
            تنقض ذلك — فمن
            يصحّح اسم السبرنت ليس بالضرورة من يقفله.

            و`updateTaskStatus` صلاحيتُها صلاحيةُ المهامّ لا السبرنت:
            الفعل تغييرُ حالة مهمّة، وكونُه من داخل شاشة السبرنت لا يغيّره.
        */
        $this->middleware('permission.role:tasks.sprints.view', [
            'only' => ['index', 'getSprintTasks', 'getUserStoriesForSprint'],
        ]);
        $this->middleware('permission.role:tasks.sprints.show', ['only' => ['show']]);
        $this->middleware('permission.role:tasks.sprints.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:tasks.sprints.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:tasks.sprints.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:tasks.sprints.manage_tasks', ['only' => ['addTask', 'removeTask']]);
        $this->middleware('permission.role:tasks.sprints.change_status', ['only' => ['updateStatus']]);
        $this->middleware('permission.role:tasks.sprints.close', ['only' => ['closeSprint']]);
        $this->middleware('permission.role:tasks.sprints.reopen', ['only' => ['reopenSprint']]);
        $this->middleware('permission.role:tasks.items.update_status', ['only' => ['updateTaskStatus']]);
    }

    /**
     * عرض قائمة السبرنتات
     */
    public function index(Request $request, ?Project $project = null)
    {
        if ($project && $project->isManual()) {
            return redirect()->route('projects.board', $project)
                ->with('error', 'هذا المشروع لا يستخدم السبرنتات — متاحة فقط لمشاريع سكرم');
        }

        if ($project) {
            ProjectAccess::authorizeView($project);
        }

        $filterProjectId = $project?->id ?? $request->project_id;

        // إذا كان الطلب من نوع AJAX، نعيد بيانات JSON
        if ($request->ajax() || $request->wantsJson()) {
            $query = ProjectAccess::scopeByProject(Sprint::with(['project']));

            // تطبيق الفلاتر
            if ($filterProjectId) {
                $query->where('project_id', $filterProjectId);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $sprints = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'sprints' => $sprints,
            ]);
        }

        // إذا كان طلب عادي، نعيد عرض الصفحة
        $query = ProjectAccess::scopeByProject(Sprint::with(['project', 'tasks']));

        // تطبيق الفلاتر
        if ($filterProjectId) {
            $query->where('project_id', $filterProjectId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sprints = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        $projects = ProjectAccess::scopeProjects(Project::query())->get();

        // Get active project ID for settings
        $activeProjectId = auth()->user()->active_project_id;
        $priorities = $activeProjectId ? TaskSettingsHelper::getPriorities($activeProjectId) : [];
        $statuses = $activeProjectId ? TaskSettingsHelper::getStatuses($activeProjectId) : [];
        $taskTypes = $activeProjectId ? TaskSettingsHelper::getTaskTypes($activeProjectId) : [];

        $totalSprintsCount = ProjectAccess::scopeByProject(Sprint::query())->count();
        $activeSprintsCount = ProjectAccess::scopeByProject(Sprint::query())->where('status', 'active')->count();

        return view('taskmanager::sprint.index', compact('sprints', 'projects', 'priorities', 'statuses', 'taskTypes', 'totalSprintsCount', 'activeSprintsCount'));
    }

    /**
     * عرض صفحة إنشاء سبرنت جديد
     */
    public function create()
    {
        $projects = ProjectAccess::scopeProjects(Project::query())->get();

        // Get active project ID for settings
        $activeProjectId = auth()->user()->active_project_id;
        $priorities = $activeProjectId ? TaskSettingsHelper::getPriorities($activeProjectId) : [];
        $statuses = $activeProjectId ? TaskSettingsHelper::getStatuses($activeProjectId) : [];
        $taskTypes = $activeProjectId ? TaskSettingsHelper::getTaskTypes($activeProjectId) : [];

        return view('taskmanager::sprint.create', compact('projects', 'priorities', 'statuses', 'taskTypes'));
    }

    /**
     * حفظ سبرنت جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'goal' => 'nullable|string|max:1000',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:planned,active,completed,closed',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات المدخلة غير صحيحة',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $project = Project::find($request->project_id);
        ProjectAccess::authorizeWrite($project);

        $sprintData = $validator->validated();
        $sprintData['status'] ??= 'planned';

        $message = match (true) {
            (bool) $project?->isManual() => 'لا يمكن إنشاء سبرنت لمشروع من نوع المهام البسيطة',
            default => $this->activeSprintConflict($sprintData['status'], (int) $project->id),
        };

        if ($message) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->with('error', $message)->withInput();
        }

        try {
            $sprint = Sprint::create($sprintData);

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $sprint,
                sprintf(
                    'أُنشئ السبرنت **%s** في المشروع **%s** من **%s** إلى **%s**',
                    $sprint->name,
                    optional($project)->title ?? '—',
                    $sprint->start_date,
                    $sprint->end_date,
                ),
            );

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء السبرنت بنجاح',
                    'sprint' => $sprint,
                ]);
            }

            return redirect()->route('sprints.index')
                ->with('success', 'تم إنشاء السبرنت بنجاح');

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء إنشاء السبرنت',
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء إنشاء السبرنت')
                ->withInput();
        }
    }

    /**
     * عرض تفاصيل السبرنت
     */
    public function show($id)
    {
        $sprint = Sprint::with(['project'])->findOrFail($id);
        ProjectAccess::authorizeSprintView($sprint);

        // جلب المهام المرتبطة بالسبرنت
        $tasks = $sprint->tasks()->with(['creator', 'assignedUsers'])->orderBy('position')->orderBy('id')->get();

        // تنظيم المهام حسب الحالة
        $tasksByStatus = $tasks->groupBy('status');

        // الحالات الممكنة للمهام باستخدام الإعدادات
        $activeProjectId = $sprint->project_id;
        if ($activeProjectId) {
            $statusSettings = TaskSettingsHelper::getStatuses($activeProjectId);
            $statuses = [];
            foreach ($statusSettings as $status) {
                $statuses[$status['id']] = $status['name'];
            }
        } else {
            // الحالات الافتراضية — مطابقة لتعداد حالة المهمة الفعلي في قاعدة البيانات
            $statuses = [
                'not_started' => 'لم تبدأ',
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ];
        }

        return view('taskmanager::sprint.show', compact('sprint', 'tasksByStatus', 'statuses'));
    }

    /**
     * عرض صفحة تعديل السبرنت
     */
    public function edit($id)
    {
        $sprint = Sprint::findOrFail($id);
        ProjectAccess::authorizeSprintWrite($sprint);
        $projects = ProjectAccess::scopeProjects(Project::query())->get();

        // Get active project ID for settings
        $activeProjectId = auth()->user()->active_project_id;
        $priorities = $activeProjectId ? TaskSettingsHelper::getPriorities($activeProjectId) : [];
        $statuses = $activeProjectId ? TaskSettingsHelper::getStatuses($activeProjectId) : [];
        $taskTypes = $activeProjectId ? TaskSettingsHelper::getTaskTypes($activeProjectId) : [];

        return view('taskmanager::sprint.edit', compact('sprint', 'projects', 'priorities', 'statuses', 'taskTypes'));
    }

    /**
     * تحديث السبرنت
     */
    public function update(Request $request, $id)
    {
        $sprint = Sprint::findOrFail($id);
        ProjectAccess::authorizeSprintWrite($sprint);

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'goal' => 'nullable|string|max:1000',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:planned,active,completed,closed',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات المدخلة غير صحيحة',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $movesProject = (int) $data['project_id'] !== (int) $sprint->project_id;

        if ($movesProject) {
            ProjectAccess::authorizeWrite((int) $data['project_id']);
        }

        // حقل الحالة الغائب يُبقي الحالة — لا يكتب `null` في عمودٍ إلزامي
        if (empty($data['status'])) {
            unset($data['status']);
        }

        $message = match (true) {
            // مهامّه من المشروع القديم — ونقلُه وحده يتركها في سبرنتِ مشروعٍ آخر
            $movesProject && $sprint->tasks()->exists() => 'لا يُنقل سبرنتٌ فيه مهامّ إلى مشروعٍ آخر — أخرج مهامّه أولاً',
            (bool) Project::find($data['project_id'])?->isManual() => 'لا يمكن ربط سبرنت بمشروع من نوع المهام البسيطة',
            default => $this->activeSprintConflict($data['status'] ?? $sprint->status, (int) $data['project_id'], $sprint->id),
        };

        if ($message) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->with('error', $message)->withInput();
        }

        try {
            $sprint->update($data);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $sprint,
                sprintf('عُدّل السبرنت **%s**', $sprint->name),
                OperationLog::diff($sprint, ['name', 'goal', 'start_date', 'end_date', 'status', 'project_id']),
            );

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث السبرنت بنجاح',
                    'sprint' => $sprint,
                ]);
            }

            return redirect()->route('sprints.show', $sprint->id)
                ->with('success', 'تم تحديث السبرنت بنجاح');

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'update');

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث السبرنت',
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء تحديث السبرنت')
                ->withInput();
        }
    }

    /**
     * حذف السبرنت
     */
    public function destroy($id)
    {
        $sprint = Sprint::findOrFail($id);
        ProjectAccess::authorizeSprintWrite($sprint);

        try {
            // العدد يُقاس قبل فكّ الارتباط: بعده يصير صفراً دائماً، وهو
            // أهمّ ما في السطر — كم مهمّةً فقدت سبرنتها بهذا الحذف
            $name = $sprint->name;
            $sprintId = (int) $sprint->id;
            $detachedTasks = $sprint->tasks()->count();

            DB::transaction(function () use ($sprint) {
                // تحديث المهام المرتبطة لإزالة المرجع إلى السبرنت
                $sprint->tasks()->update(['sprint_id' => null]);

                // حذف السبرنت
                $sprint->delete();
            });

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $sprintId,
                sprintf('حُذف السبرنت **%s** وفُكّ ارتباط **%d** مهمّة به', $name, $detachedTasks),
            );

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف السبرنت بنجاح',
                ]);
            }

            return redirect()->route('sprints.index')
                ->with('success', 'تم حذف السبرنت بنجاح');

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'destroy');

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف السبرنت',
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حذف السبرنت');
        }
    }

    /**
     * إضافة مهمة إلى السبرنت
     */
    public function addTask(Request $request, $sprintId)
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|exists:tasks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'المهمة المحددة غير موجودة',
            ], 422);
        }

        try {
            $sprint = Sprint::findOrFail($sprintId);
            $task = Task::findOrFail($request->task_id);
            ProjectAccess::authorizeSprintWrite($sprint);

            // التحقق من أن المهمة تنتمي إلى نفس المشروع
            if ($task->project_id != $sprint->project_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'المهمة يجب أن تنتمي إلى نفس المشروع الخاص بالسبرنت',
                ], 422);
            }

            if ($sprint->project && $sprint->project->isManual()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تعيين سبرنت لمهام مشروع من نوع المهام البسيطة',
                ], 422);
            }

            // ربط المهمة بالسبرنت
            $task->update(['sprint_id' => $sprint->id]);

            OperationLog::record(
                'task_added',
                self::LOG_MODULE,
                $sprint,
                sprintf('ضُمّت المهمّة **%s** إلى السبرنت **%s**', $task->title, $sprint->name),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المهمة إلى السبرنت بنجاح',
                'task' => $task->load(['creator', 'assignedUsers', 'sprint']),
            ]);

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'addTask');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة المهمة إلى السبرنت',
            ], 500);
        }
    }

    /**
     * إزالة مهمة من السبرنت
     */
    public function removeTask($sprintId, $taskId)
    {
        try {
            $sprint = Sprint::findOrFail($sprintId);
            $task = Task::findOrFail($taskId);
            ProjectAccess::authorizeSprintWrite($sprint);

            // التحقق من أن المهمة تنتمي إلى هذا السبرنت
            if ($task->sprint_id != $sprint->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'المهمة غير مخصصة لهذا السبرنت',
                ], 422);
            }

            // إزالة ربط المهمة بالسبرنت
            $task->update(['sprint_id' => null]);

            OperationLog::record(
                'task_removed',
                self::LOG_MODULE,
                $sprint,
                sprintf('أُخرجت المهمّة **%s** من السبرنت **%s**', $task->title, $sprint->name),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة المهمة من السبرنت بنجاح',
            ]);

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'removeTask');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إزالة المهمة من السبرنت',
            ], 500);
        }
    }

    /**
     * جلب مهام السبرنت
     */
    public function getSprintTasks($sprintId)
    {
        try {
            $sprint = Sprint::findOrFail($sprintId);
            ProjectAccess::authorizeSprintView($sprint);
            $tasks = $sprint->tasks()->with(['creator:id,name', 'assignedUsers:id,name', 'project:id,title'])->get();

            return response()->json([
                'success' => true,
                'tasks' => $tasks,
            ]);

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'getSprintTasks');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب مهام السبرنت',
            ], 500);
        }
    }

    /**
     * تحديث حالة السبرنت
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:planned,active,completed,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الحالة المحددة غير صحيحة',
            ], 422);
        }

        try {
            $sprint = Sprint::findOrFail($id);
            ProjectAccess::authorizeSprintWrite($sprint);

            // الإقفال وإعادة الفتح لهما صلاحيتان مستقلّتان — وهذا المسار كان
            // بابهما الخلفي: `change_status` وحدها تُقفل وتفتح
            $needs = match (true) {
                $request->status === 'closed' && $sprint->status !== 'closed' => 'tasks.sprints.close',
                $request->status !== 'closed' && $sprint->status === 'closed' => 'tasks.sprints.reopen',
                default => null,
            };

            if ($needs && ! auth()->user()?->hasRolePermission($needs)) {
                return response()->json([
                    'success' => false,
                    'message' => 'إقفال السبرنت وإعادة فتحه يحتاجان صلاحيتهما الخاصّة',
                ], 403);
            }

            if ($conflict = $this->activeSprintConflict($request->status, (int) $sprint->project_id, $sprint->id)) {
                return response()->json(['success' => false, 'message' => $conflict], 422);
            }

            $previousStatus = $sprint->status;
            $sprint->update(['status' => $request->status]);

            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $sprint,
                sprintf(
                    'تغيّرت حالة السبرنت **%s** من **%s** إلى **%s**',
                    $sprint->name,
                    $previousStatus,
                    $request->status,
                ),
                ['old' => ['status' => $previousStatus], 'new' => ['status' => $request->status]],
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة السبرنت بنجاح',
                'sprint' => $sprint,
            ]);

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'updateStatus');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة السبرنت',
            ], 500);
        }
    }

    /**
     * إغلاق السبرنت
     */
    public function closeSprint(Request $request, $id)
    {
        try {
            $sprint = Sprint::findOrFail($id);
            ProjectAccess::authorizeSprintWrite($sprint);

            // التحقق من أن السبرنت ليس مغلق بالفعل
            if ($sprint->status === 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'السبرنت مغلق بالفعل',
                ], 422);
            }

            // تحديث حالة السبرنت إلى "closed"
            $previousStatus = $sprint->status;
            $sprint->update(['status' => 'closed']);

            OperationLog::record(
                'closed',
                self::LOG_MODULE,
                $sprint,
                sprintf(
                    'أُقفل السبرنت **%s** وفيه **%d** مهمّة غير مكتملة',
                    $sprint->name,
                    $sprint->tasks()->where('status', '!=', 'completed')->count(),
                ),
                ['old' => ['status' => $previousStatus], 'new' => ['status' => 'closed']],
            );

            // نحتفظ بالمهام المرتبطة بالسبرنت - لا نقوم بإزالة الارتباط
            // يمكن إضافة منطق إضافي هنا مثل إرسال إشعارات أو تحديث المهام

            return response()->json([
                'success' => true,
                'message' => 'تم إغلاق السبرنت بنجاح',
                'sprint' => $sprint,
            ]);

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'closeSprint');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إغلاق السبرنت',
            ], 500);
        }
    }

    /**
     * إعادة فتح السبرنت
     */
    public function reopenSprint(Request $request, $id)
    {
        try {
            $sprint = Sprint::findOrFail($id);
            ProjectAccess::authorizeSprintWrite($sprint);

            // التحقق من أن السبرنت مغلق
            if ($sprint->status !== 'closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'السبرنت ليس مغلق لتعيد فتحه',
                ], 422);
            }

            // تحديث حالة السبرنت إلى "active" أو "planned" حسب التاريخ
            $now = now();
            $newStatus = 'planned';

            // إذا بدأ السبرنت بالفعل، نجعله نشطًا
            if ($sprint->start_date && $sprint->start_date <= $now) {
                $newStatus = 'active';
            }

            // إذا انتهى السبرنت، نجعله مكتملًا
            if ($sprint->end_date && $sprint->end_date->endOfDay() < $now) {
                $newStatus = 'completed';
            }

            // سبرنتٌ آخر صار نشطاً أثناء إقفال هذا — يعود مخطَّطاً لا نشطاً ثانياً
            if ($newStatus === 'active' && $this->activeSprintConflict('active', (int) $sprint->project_id, $sprint->id)) {
                $newStatus = 'planned';
            }

            $sprint->update(['status' => $newStatus]);

            OperationLog::record(
                'reopened',
                self::LOG_MODULE,
                $sprint,
                sprintf('أُعيد فتح السبرنت **%s** بحالة **%s**', $sprint->name, $newStatus),
                ['old' => ['status' => 'closed'], 'new' => ['status' => $newStatus]],
            );

            // يمكن إضافة منطق إضافي هنا مثل إرسال إشعارات أو تحديث المهام

            return response()->json([
                'success' => true,
                'message' => 'تم إعادة فتح السبرنت بنجاح',
                'sprint' => $sprint,
            ]);

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'reopenSprint');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إعادة فتح السبرنت',
            ], 500);
        }
    }

    /**
     * تحديث حالة مهمة السبرنت
     */
    /**
     * تحديث حالة مهمة السبرنت مع تحديث الأب إذا لزم الأمر
     */
    public function updateTaskStatus(Request $request, $sprintId, $taskId)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(TaskVocabulary::statusIds())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'الحالة المحددة غير صحيحة',
            ], 422);
        }

        try {
            $sprint = Sprint::findOrFail($sprintId);
            $task = Task::findOrFail($taskId);
            ProjectAccess::authorizeSprintWrite($sprint);

            // التحقق من أن المهمة تنتمي إلى هذا السبرنت
            if ($task->sprint_id != $sprint->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'المهمة غير مخصصة لهذا السبرنت',
                ], 422);
            }

            // مسار الحالة الواحد: يسجّل، ويضبط تاريخ الإنجاز، ويرفع إلى الأب
            $changed = DB::transaction(fn () => app(TaskStatusService::class)->change($task, $request->status));

            if ($changed) {
                app(TaskNotifier::class)->statusChanged($task);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة المهمة بنجاح',
                'task' => $task,
            ]);

        } catch (HttpExceptionInterface|ModelNotFoundException $e) {
            // 403 و404 ليستا عطلاً — تمرّان كما هما
            throw $e;
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'updateTaskStatus');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة المهمة',
            ], 500);
        }
    }

    /**
     * قصص المستخدم في السبرنت — لقائمة الاختيار في لوحته.
     *
     * المسار `sprints.get-user-stories` كان يشير إلى هذه الدالّة وهي غير
     * موجودة، فكل طلبٍ إليه خطأ 500.
     */
    public function getUserStoriesForSprint($sprintId)
    {
        $sprint = Sprint::findOrFail($sprintId);
        ProjectAccess::authorizeSprintView($sprint);

        $stories = $sprint->tasks()
            ->where('type', 'user_story')
            ->with(['parent:id,title', 'assignedUsers:id,name'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'tasks' => $stories,
        ]);
    }

    /**
     * سبرنتٌ نشطٌ واحد لكل مشروع — يعيد رسالة التعارض أو `null`.
     *
     * اللوحة تعرض «السبرنت الجاري» واحداً ([Sprint::currentFor()])، فنشطٌ
     * ثانٍ لا يظهر عليها ومهامّه تغيب عن الفريق.
     */
    private function activeSprintConflict(?string $status, int $projectId, ?int $exceptId = null): ?string
    {
        if ($status !== 'active') {
            return null;
        }

        $other = Sprint::where('project_id', $projectId)
            ->where('status', 'active')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->value('name');

        return $other ? "في المشروع سبرنتٌ نشطٌ آخر («{$other}») — أقفله أو أكمله أولاً" : null;
    }
}
