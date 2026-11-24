<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Constants\permissions\TaskManagerPermissions;
use App\Helpers\TaskSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Support\OperationLog;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\TaskManager\Services\ProjectAccess;
use Modules\TaskManager\Services\TaskNotifier;
use Modules\TaskManager\Services\TaskStatusService;

class TaskController extends Controller
{
    /** وحدة السجلّ — بها يربط `logs`/`event_logs` أسطرَ هذه الشاشة. */
    private const LOG_MODULE = 'TaskManager.Task';

    public function __construct()
    {
        /*
            الإنشاء والتعديل يخدمان ستة أنواع بصلاحيتين — الهيكل والعمل
            اليومي — فحارسُهما هنا قائمةٌ بديلة، والتمييزُ الفعلي في
            `authorizeTaskType()` وفق `type` الوارد. نفسُ علاج
            [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController]
            حيث يخدم إجراءٌ واحدٌ أربعة أنواع أذون.
        */
        $this->middleware('permission.role:tasks.items.view', [
            'only' => [
                'index', 'getFilteredTasks', 'getTaskManagerData', 'getParentTasks',
                'getComponents', 'getEpics', 'getUserStories', 'getSubTasks',
                'getBugs', 'getTasks',
            ],
        ]);
        $this->middleware('permission.role:tasks.items.show', ['only' => ['show']]);
        $this->middleware('permission.role:tasks.items.create|tasks.items.create_structure', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:tasks.items.edit', ['only' => ['update']]);
        $this->middleware('permission.role:tasks.items.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:tasks.items.update_status', ['only' => ['updateStatus']]);
        $this->middleware('permission.role:tasks.items.reorder', ['only' => ['reorder']]);
        $this->middleware('permission.role:tasks.sprints.manage_tasks', ['only' => ['assignSprintWithInheritance']]);
    }

    /**
     * فحص صلاحية النوع الفعلي — `store()` و`update()` يخدمان ستة أنواع
     * بصلاحيتين منفصلتين، فلا يكفي فحص الميدل وير الموحّد على المسار.
     *
     * نوعٌ غير معروف يُردّ بـ422 لا بـ403: الطلب ليس ممنوعاً بل غيرَ
     * مفهوم، والخلط بينهما يُرسل المستخدم إلى مسؤول الصلاحيات بلا سبب.
     */
    private function authorizeTaskType(?string $type): void
    {
        $permission = TaskManagerPermissions::createPermissionByType()[(string) $type] ?? null;

        if (! $permission) {
            abort(422, 'نوع مهمّةٍ غير معروف.');
        }

        if (! Auth::user()?->hasRolePermission($permission)) {
            abort(403, 'لا تملك صلاحية إنشاء هذا النوع من المهامّ أو تعديله.');
        }
    }

    /**
     * Get filtered tasks
     */
    public function getFilteredTasks(Request $request)
    {
        $query = ProjectAccess::scopeByProject(Task::with(['project', 'parent', 'children', 'assignedUsers', 'sprint']));

        // Filter by type
        if ($request->type) {
            $query->where('type', $request->type);
        }

        // Filter by project
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->user_id) {
            $query->whereHas('assignedUsers', function ($q) use ($request) {
                $q->where('users.id', $request->user_id);
            });
        }

        // Filter by component
        if ($request->component_id) {
            if ($request->type === 'epic') {
                $query->where('parent_task_id', $request->component_id);
            } elseif ($request->type === 'user_story') {
                $query->whereHas('parent', function ($q) use ($request) {
                    $q->where('parent_task_id', $request->component_id);
                });
            } elseif ($request->type === 'bug') {
                $query->where('parent_task_id', $request->component_id);
            }
        }

        // Filter by epic
        if ($request->epic_id) {
            $query->where('parent_task_id', $request->epic_id);
        }

        // Filter by parent task (for subtasks)
        if ($request->parent_task_id) {
            $query->where('parent_task_id', $request->parent_task_id);
        }

        // Filter by priority
        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        // Filter by sprint
        if ($request->sprint_id) {
            $query->where('sprint_id', $request->sprint_id);
        }

        $tasks = $query->paginate(20);

        if ($request->type === 'user_story') {
            $view = 'taskmanager::tasks.partials.stories-table';
        } elseif ($request->type === 'epic') {
            $view = 'taskmanager::tasks.partials.epics-table';
        } elseif ($request->type === 'component') {
            $view = 'taskmanager::tasks.partials.components-table';
        } elseif ($request->type === 'sub_task') {
            $view = 'taskmanager::tasks.partials.subtasks-table';
        } else {
            $view = 'taskmanager::tasks.partials.bugs-table';
        }

        $view = view($view, [
            $request->type === 'component' ? 'components' :
            ($request->type === 'epic' ? 'epics' :
            ($request->type === 'user_story' ? 'stories' :
            ($request->type === 'sub_task' ? 'subTasks' : 'bugs'))) => $tasks,
        ])->render();

        return response()->json([
            'html' => $view,
            'currentPage' => $tasks->currentPage(),
            'lastPage' => $tasks->lastPage(),
        ]);
    }

    /**
     * Display the specified task
     */
    public function show(Task $taskId)
    {
        ProjectAccess::authorizeTaskView($taskId);

        $taskId->load(['project', 'parent', 'assignedUsers', 'sprint']);

        return response()->json([
            'task' => $taskId,
        ]);
    }

    /**
     * عرض قائمة المهام
     */
    public function create(?Project $project = null)
    {
        if ($project) {
            ProjectAccess::authorizeWrite($project);
        }

        $projects = ProjectAccess::scopeProjects(Project::query())->get();
        $users = User::all();
        $sprints = ProjectAccess::scopeByProject(Sprint::query())->get();
        $managementType = $project?->management_type ?? 'scrum';

        // جلب المكونات مع مشاريعها
        $components = $this->visibleTasks('component')->with('project')
            ->get(['id', 'title', 'project_id']);

        // جلب الإبيك مع المكونات المرتبطة بها
        $epics = $this->visibleTasks('epic')->with(['parent', 'parent.project'])
            ->get(['id', 'title', 'parent_task_id']);

        // جلب قصص المستخدم مع الإبيك المرتبطة بها
        $userStories = $this->visibleTasks('user_story')->with(['parent', 'parent.parent'])
            ->get(['id', 'title', 'parent_task_id', 'estimated_hours']);

        // المهام الأب المتاحة للمهام الفرعية في المشاريع اليدوية
        $manualParentTasks = $this->visibleTasks('task', $project)->get(['id', 'title', 'project_id']);

        return view('taskmanager::tasks.modals.create',
            compact('projects', 'users', 'sprints', 'components', 'epics', 'userStories', 'managementType', 'manualParentTasks'));
    }

    public function index(Request $request, ?Project $project = null)
    {
        if ($project && $project->isManual()) {
            return redirect()->route('projects.board', $project)
                ->with('error', 'هذا المشروع لا يستخدم قائمة الانتظار (Backlog) — متاحة فقط لمشاريع سكرم');
        }

        if ($project) {
            ProjectAccess::authorizeView($project);
        }

        $projects = ProjectAccess::scopeProjects(Project::query())->get();
        $users = User::all();
        $sprints = ProjectAccess::scopeByProject(Sprint::query())->get();

        // قوائم الفلاتر — كانت تُحمَّل بأبنائها ومكلَّفيها كاملةً لتملأ
        // قائمة اختيار
        $components = $this->visibleTasks('component', $project)->with('project')->get();
        $epics = $this->visibleTasks('epic', $project)->with('parent')->get();

        // Load initial paginated data for all tabs
        $initialComponents = $this->visibleTasks('component', $project)
            ->with(['project', 'children', 'assignedUsers'])
            ->paginate(20);

        $initialEpics = $this->visibleTasks('epic', $project)
            ->with(['parent', 'children', 'assignedUsers'])
            ->paginate(20);

        $initialStories = $this->visibleTasks('user_story', $project)
            ->with(['parent', 'parent.parent', 'children', 'assignedUsers'])
            ->paginate(20);

        $initialSubTasks = $this->visibleTasks('sub_task', $project)
            ->with(['parent', 'assignedUsers'])
            ->paginate(20);

        $initialBugs = $this->visibleTasks('bug', $project)
            ->with(['parent', 'assignedUsers'])
            ->paginate(20);

        return view('taskmanager::tasks.index', compact(
            'projects',
            'users',
            'sprints',
            'components', // For component selection in filters
            'epics', // For epic selection in filters
            'initialComponents', // For initial table data
            'initialEpics',
            'initialStories',
            'initialSubTasks',
            'initialBugs'
        ));
    }

    /**
     * Get filtered tasks for task manager view
     */
    public function getTaskManagerData(Request $request)
    {
        $resolvedProjectId = $request->project_id ?: auth()->user()->active_project_id;
        $resolvedProject = $resolvedProjectId ? Project::find($resolvedProjectId) : null;
        $isManual = $resolvedProject && $resolvedProject->isManual();

        if ($resolvedProject) {
            ProjectAccess::authorizeView($resolvedProject);
        }

        // السبرنت الجاري للمشروع المحدّد — لا يُستخدم إطلاقاً للمشاريع اليدوية
        $currentSprint = $isManual ? null : Sprint::currentFor($resolvedProjectId ? (int) $resolvedProjectId : null);

        // جلب المهام حسب السبرنت الحالي
        $query = ProjectAccess::scopeByProject(Task::with(['project', 'parent', 'assignedUsers', 'sprint']));

        // تطبيق الفلاتر
        if ($resolvedProjectId) {
            $query->where('project_id', $resolvedProjectId);
        }

        $resolvedType = $request->type ?: ($isManual ? 'task' : 'user_story');

        if (! $isManual) {
            if ($request->sprint_id) {
                $query->where('sprint_id', $request->sprint_id);
            } elseif ($currentSprint && in_array($resolvedType, ['user_story', 'sub_task'], true)) {
                // إذا لم يتم تحديد سبرنت، نستخدم السبرنت الحالي — فقط للأنواع التي تنتمي فعلياً لسبرنت
                // (المكوّن/الإبيك/الخطأ البرمجي لا تُربط بسبرنت، فتقييدها هنا يُخفيها دائماً)
                $query->where('sprint_id', $currentSprint->id);
            }
        }

        $query->where('type', $resolvedType);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->user_id) {
            $query->whereHas('assignedUsers', function ($q) use ($request) {
                $q->where('users.id', $request->user_id);
            });
        }

        $tasks = $query->orderBy('position')->orderBy('id')->get();

        // تنظيم المهام حسب الحالة
        $tasksByStatus = $tasks->groupBy('status');

        // الحالات بأسمائها من إعدادات المشروع — نفس ما تعرضه اللوحة عند
        // التحميل الأول، وإلّا تبدّلت عناوين الأعمدة عند أول فلترة
        $statuses = array_column(TaskSettingsHelper::getStatuses($resolvedProjectId), 'name', 'id');

        // إرجاع العرض الجزئي للوحة المهام (نفس الجزء المستخدم في اللوحة الرئيسية ولوحات السبرنت)
        $html = view('taskmanager::tasks.partials.kanban_board', compact('tasksByStatus', 'statuses'))->render();

        return response()->json([
            'success' => true,
            'html' => $html,
            'currentSprint' => $currentSprint,
            'statuses' => $statuses,
        ]);
    }

    /**
     * إضافة مهمة جديدة
     */
    public function store(Request $request)
    {
        // المهمة الأب (إن وُجدت) تُحلَّل مرة واحدة، وتُستخدم لتحديد المشروع الحاكم
        // (سكرم أو يدوي) وكذلك لتوريث project_id لاحقاً.
        $parentTask = $request->filled('parent_task_id') ? Task::find($request->parent_task_id) : null;

        $project = null;
        if ($request->filled('project_id')) {
            $project = Project::find($request->project_id);
        } elseif ($parentTask) {
            $project = $this->resolveProjectForTask($parentTask);
        }

        $isManual = $project && $project->isManual();

        $validationRules = [
            'type' => $isManual ? 'required|in:task,sub_task' : 'required|in:component,epic,user_story,sub_task,bug',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sprint_id' => 'nullable|exists:sprints,id',
            'priority' => ['required', Rule::in(TaskVocabulary::priorityIds())],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'integer|exists:users,id',
        ];

        // Add specific validations based on task type
        switch ($request->type) {
            case 'component':
            case 'task':
                $validationRules['project_id'] = 'required|exists:projects,id';
                break;
            case 'epic':
            case 'sub_task':
            case 'bug':
                $validationRules['parent_task_id'] = 'required|exists:tasks,id';
                break;
            case 'user_story':
                $validationRules['parent_task_id'] = 'required|exists:tasks,id';
                $validationRules['estimated_hours'] = 'required|numeric|min:0';
                break;
        }

        $validator = Validator::make($request->all(), $validationRules, $this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // بعد التحقّق لا قبله: النوع غير الصالح يُردّ برسالة تحقّقٍ مفهومة
        // لا بـ403 توحي بنقص صلاحية
        $this->authorizeTaskType($request->type);

        // صلاحية النوع تقول «يُنشئ مهامّ»، وهذه تقول «في هذا المشروع»
        if ($project) {
            ProjectAccess::authorizeWrite($project);
        } elseif ($parentTask) {
            ProjectAccess::authorizeTaskWrite($parentTask);
        }

        // التحقق من صحة العلاقة بين نوع المهمة والمهمة الأب
        if ($parentTask && ! $this->validateTaskHierarchy($request->type, $parentTask->type)) {
            return response()->json([
                'errors' => ['parent_task_id' => ['نوع المهمة الأب غير متوافق مع نوع المهمة المطلوب']],
            ], 422);
        }

        // المشاريع اليدوية لا تستخدم السبرنتات إطلاقاً — نتجاهل أي قيمة واردة
        $sprintId = $isManual ? null : $request->sprint_id;

        if ($error = $this->sprintMismatch($sprintId, $project?->id)) {
            return response()->json(['errors' => ['sprint_id' => [$error]]], 422);
        }

        try {
            $startDate = $request->start_date ?: now()->toDateString();

            $data = [
                'type' => $request->type,
                'title' => $request->title,
                'description' => $request->description,
                'sprint_id' => $sprintId,
                'status' => TaskVocabulary::STATUS_NOT_STARTED,
                'priority' => $request->priority,
                'created_by' => Auth::id(),
                // العمودان إلزاميان في الجدول — فإن لم يُدخلهما المستخدم
                // بدأت المهمّة اليوم وامتدّت أسبوعاً
                'start_date' => $startDate,
                'due_date' => $request->due_date ?: Carbon::parse($startDate)->addWeek()->toDateString(),
                'project_id' => $project?->id,
            ];

            if (in_array($request->type, ['epic', 'sub_task', 'bug', 'user_story'], true)) {
                $data['parent_task_id'] = $request->parent_task_id;
            }

            if ($request->type === 'user_story') {
                $data['estimated_hours'] = $request->estimated_hours;
            }

            $task = DB::transaction(function () use ($data, $request) {
                $task = Task::create($data);

                // Update parent epic's estimated hours if this is a user story
                if ($task->type === 'user_story' && $task->parent) {
                    $this->updateEpicEstimatedHours($task->parent);
                }

                if ($request->filled('assigned_users')) {
                    $task->assignedUsers()->sync($this->assignmentPivot($request->assigned_users));
                }

                return $task;
            });

            app(TaskNotifier::class)->assigned($task, (array) $request->input('assigned_users', []));

            // Load the task with its relationships
            $task->load(['parent', 'project', 'assignedUsers']);

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $task,
                sprintf(
                    'أُنشئت **%s** بعنوان **%s** في المشروع **%s** بأولوية **%s**',
                    $task->type,
                    $task->title,
                    optional($task->project)->title ?? '—',
                    $task->priority,
                ),
            );

            $html = '';

            // فشلُ رسم الصفّ لا يُسقط إنشاءً نجح
            try {
                $html = view('taskmanager::tasks.partials.task-row', compact('task'))->render();
            } catch (\Throwable $e) {
                Log::error('Error rendering task row: '.$e->getMessage());
            }

            return response()->json([
                'message' => 'تم إنشاء المهمة بنجاح',
                'task' => $task,
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            // التفاصيل (الرسالة والملف والسطر) إلى السجلّ لا إلى المتصفّح
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return response()->json(['message' => 'حدث خطأ أثناء إنشاء المهمة'], 500);
        }
    }

    public function update(Request $request, Task $taskId)
    {
        ProjectAccess::authorizeTaskWrite($taskId);

        $project = $this->resolveProjectForTask($taskId);

        $validationRules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sprint_id' => 'nullable|exists:sprints,id',
            'priority' => ['required', Rule::in(TaskVocabulary::priorityIds())],
            'status' => ['required', Rule::in(TaskVocabulary::statusIds())],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'integer|exists:users,id',
        ];

        // Add specific validations based on task type
        switch ($taskId->type) {
            case 'component':
            case 'task':
                $validationRules['project_id'] = 'required|exists:projects,id';
                break;
            case 'epic':
            case 'sub_task':
            case 'bug':
                $validationRules['parent_task_id'] = 'required|exists:tasks,id';
                break;
            case 'user_story':
                $validationRules['parent_task_id'] = 'required|exists:tasks,id';
                $validationRules['estimated_hours'] = 'required|numeric|min:0';
                break;
        }

        $validator = Validator::make($request->all(), $validationRules, $this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /*
            النوع هنا يُقرأ من المهمّة نفسها لا من الطلب — فالتعديل لا
            يغيّره — لكنّ الفحص لازمٌ رغم ذلك: من يملك `tasks.items.edit`
            وحدها لا يُفترض أن يعيد رسم هيكل المشروع بتعديل إبيكٍ أو
            قصّة، وهي نفس القسمة التي يقوم عليها الإنشاء.
        */
        $this->authorizeTaskType($taskId->type);

        $ownsProject = in_array($taskId->type, ['component', 'task'], true);

        // الأب الجديد — إن تغيّر. المقارنة بالأعداد: الوارد نصٌّ والمخزَّن
        // عدد، و`!==` بينهما صحيحةٌ دائماً
        $parentTask = null;
        $previousParentId = $taskId->parent_task_id ? (int) $taskId->parent_task_id : null;

        if (! $ownsProject && (int) $request->parent_task_id !== $previousParentId) {
            $parentTask = Task::find($request->parent_task_id);

            if (! $parentTask || ! $this->validateTaskHierarchy($taskId->type, $parentTask->type)) {
                return response()->json([
                    'errors' => ['parent_task_id' => ['نوع المهمة الأب غير متوافق مع نوع المهمة المطلوب']],
                ], 422);
            }

            // النقل إلى أبٍ في مشروعٍ آخر كتابةٌ في ذلك المشروع أيضاً
            ProjectAccess::authorizeTaskWrite($parentTask);
        }

        if ($ownsProject && (int) $request->project_id !== (int) $taskId->project_id) {
            ProjectAccess::authorizeWrite((int) $request->project_id);
        }

        $newProjectId = $ownsProject
            ? (int) $request->project_id
            : (($parentTask ? ProjectAccess::projectIdOfTask($parentTask) : null) ?? $project?->id);

        $newProject = $newProjectId ? Project::find($newProjectId) : null;

        // المشاريع اليدوية لا تستخدم السبرنتات إطلاقاً — نتجاهل أي قيمة واردة
        $sprintId = $newProject && $newProject->isManual() ? null : $request->sprint_id;

        if ($error = $this->sprintMismatch($sprintId, $newProjectId)) {
            return response()->json(['errors' => ['sprint_id' => [$error]]], 422);
        }

        try {
            $assignees = array_map('intval', (array) $request->input('assigned_users', []));
            $previousAssignees = $taskId->assignedUsers()->pluck('users.id')->all();
            $previousStatus = $taskId->status;
            $changes = [];

            DB::transaction(function () use ($request, $taskId, $ownsProject, $newProjectId, $sprintId, $previousParentId, $assignees, &$changes) {
                $data = [
                    'title' => $request->title,
                    'description' => $request->description,
                    'sprint_id' => $sprintId,
                    'priority' => $request->priority,
                    // العمودان إلزاميان — حقلٌ فارغ يُبقي ما كان
                    'start_date' => $request->start_date ?: $taskId->start_date,
                    'due_date' => $request->due_date ?: $taskId->due_date,
                    'project_id' => $newProjectId,
                ];

                if (! $ownsProject) {
                    $data['parent_task_id'] = $request->parent_task_id;
                }

                if ($taskId->type === 'user_story') {
                    $data['estimated_hours'] = $request->estimated_hours;
                }

                $projectChanged = (int) $taskId->project_id !== (int) $newProjectId;

                $taskId->update($data);

                // فرقُ القيم يُلتقط عقب `update()` مباشرةً: `getOriginal()` ما
                // زال يحمل ما كان، وأي حفظٍ لاحق في هذه الدالّة يمحوه
                $changes = OperationLog::diff($taskId, [
                    'title', 'description', 'sprint_id', 'priority',
                    'start_date', 'due_date', 'project_id', 'parent_task_id',
                    'estimated_hours',
                ]);

                // الحالة من مسارها الواحد: يسجّلها، ويضبط تاريخ الإنجاز،
                // ويرفعها إلى الأب
                app(TaskStatusService::class)->change($taskId, $request->status);

                // من انتقل إلى مشروعٍ آخر أخذ أحفاده معه — وإلّا بقوا في
                // تقارير المشروع القديم وسبرنتاته
                if ($projectChanged) {
                    $this->moveDescendants($taskId, $newProjectId);
                }

                // ساعات الإبيك مجموعُ قصصه: الجديد يزيد، والقديم ينقص
                if ($taskId->type === 'user_story') {
                    foreach (array_unique(array_filter([$previousParentId, (int) $taskId->parent_task_id])) as $epicId) {
                        if ($epic = Task::find($epicId)) {
                            $this->updateEpicEstimatedHours($epic);
                        }
                    }
                }

                // نموذج التعديل يحمل قائمة المكلَّفين دائماً، والقائمة
                // الفارغة لا تُرسَل أصلاً — فغيابُها معناه «لا أحد»
                $taskId->assignedUsers()->sync($this->assignmentPivot($assignees));
            });

            $notifier = app(TaskNotifier::class);
            $notifier->assigned($taskId, array_diff($assignees, $previousAssignees));

            if ($previousStatus !== $taskId->status) {
                $notifier->statusChanged($taskId);
            }

            // Load the task with its relationships for the response
            $updatedTask = $taskId->load(['parent', 'project', 'assignedUsers', 'sprint']);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $taskId,
                sprintf('عُدّلت المهمّة **%s**', $taskId->title),
                $changes,
            );

            // Return a minimal response with only the necessary data for UI updates
            return response()->json([
                'message' => 'تم تحديث المهمة بنجاح',
                'task' => [
                    'id' => $updatedTask->id,
                    'title' => $updatedTask->title,
                    'type' => $updatedTask->type,
                    'status' => $updatedTask->status,
                    'priority' => $updatedTask->priority,
                    'start_date' => $updatedTask->start_date,
                    'due_date' => $updatedTask->due_date,
                    'estimated_hours' => $updatedTask->estimated_hours,
                    'parent' => $updatedTask->parent ? [
                        'id' => $updatedTask->parent->id,
                        'title' => $updatedTask->parent->title,
                    ] : null,
                    'project' => $updatedTask->project ? [
                        'id' => $updatedTask->project->id,
                        'name' => $updatedTask->project->title,
                    ] : null,
                    'sprint' => $updatedTask->sprint ? [
                        'id' => $updatedTask->sprint->id,
                        'name' => $updatedTask->sprint->name,
                    ] : null,
                ],
            ]);
        } catch (\Throwable $e) {
            // التفاصيل (الرسالة والملف والسطر) إلى السجلّ لا إلى المتصفّح
            OperationLog::failure($e, self::LOG_MODULE, 'update', $taskId);

            return response()->json(['message' => 'حدث خطأ أثناء تحديث المهمة'], 500);
        }
    }

    /**
     * حذف مهمة
     */
    public function destroy(Task $taskId)
    {
        ProjectAccess::authorizeTaskWrite($taskId);

        if ($taskId->hasSubTasks()) {
            return response()->json([
                'message' => 'لا يمكن حذف المهمة لأنها تحتوي على مهام فرعية',
            ], 422);
        }

        try {
            // قبل الحذف — بعده لا عنوانَ يُكتب ولا معرّفَ يُفتح منه
            $title = $taskId->title;
            $type = $taskId->type;
            $deletedId = (int) $taskId->id;
            $parent = $taskId->type === 'user_story' ? $taskId->parent : null;

            DB::transaction(function () use ($taskId, $parent) {
                // لا مفاتيح أجنبية على هذه الجداول — فما لا يُحذف هنا يبقى
                // يتيماً يشير إلى مهمّةٍ لا وجود لها
                $taskId->assignedUsers()->detach();
                $taskId->comments()->delete();
                $taskId->delete();

                if ($parent) {
                    $this->updateEpicEstimatedHours($parent);
                }
            });

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $deletedId,
                sprintf('حُذفت **%s** بعنوان **%s**', $type, $title),
            );
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $taskId);

            return response()->json(['message' => 'حدث خطأ أثناء حذف المهمة'], 500);
        }

        return response()->json([
            'message' => 'تم حذف المهمة بنجاح',
        ]);
    }

    /**
     * تحديث حالة مهمة (يُستخدم من لوحة السحب والإفلات) — لا يعتمد على سبرنت،
     * يعمل بنفس الشكل للمشاريع اليدوية وللسكرم.
     */
    public function updateStatus(Request $request, Task $taskId)
    {
        ProjectAccess::authorizeTaskWrite($taskId);

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
            $changed = DB::transaction(fn () => app(TaskStatusService::class)->change($taskId, $request->status));

            if ($changed) {
                app(TaskNotifier::class)->statusChanged($taskId);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة المهمة بنجاح',
                'task' => $taskId,
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'updateStatus', $taskId);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة المهمة',
            ], 500);
        }
    }

    /**
     * إعادة ترتيب المهام داخل عمود حالة واحد على اللوحة — يُستخدم بعد كل إفلات
     * (سواء داخل نفس العمود أو من عمود آخر) لحفظ الترتيب الجديد ككل دفعة واحدة.
     *
     * الإفلات في عمودٍ آخر تغييرُ حالة: فيمرّ بمسار الحالة نفسه (صلاحيتها
     * وسجلّها وتاريخ الإنجاز)، ولا يكفيه `tasks.items.reorder` وحدها. وكان
     * يكتب `status` لكل معرّفٍ يصله مباشرةً في الجدول — أيّاً كان مشروعه.
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(TaskVocabulary::statusIds())],
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer|distinct|exists:tasks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الترتيب غير صحيحة',
            ], 422);
        }

        $tasks = Task::whereIn('id', $request->task_ids)->get();

        foreach ($tasks as $task) {
            ProjectAccess::authorizeTaskWrite($task);
        }

        $moved = $tasks->filter(fn (Task $task) => $task->status !== $request->status);

        if ($moved->isNotEmpty() && ! Auth::user()?->hasRolePermission('tasks.items.update_status')) {
            return response()->json([
                'success' => false,
                'message' => 'لا تملك صلاحية تغيير حالة المهامّ.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($request, $moved) {
                $statusService = app(TaskStatusService::class);

                foreach ($moved as $task) {
                    $statusService->change($task, $request->status);
                }

                foreach ($request->task_ids as $index => $taskId) {
                    Task::whereKey($taskId)->update(['position' => $index]);
                }
            });

            foreach ($moved as $task) {
                app(TaskNotifier::class)->statusChanged($task);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ الترتيب',
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'reorder');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الترتيب',
            ], 500);
        }
    }

    /**
     * الحصول على المهام الأب المتاحة
     */
    public function getParentTasks($type)
    {
        $parentType = $this->getParentType($type);

        if (! $parentType) {
            return response()->json([]);
        }

        $tasks = $this->visibleTasks($parentType)
            ->select('id', 'title')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Get all components for dropdown
     */
    public function getComponents()
    {
        $components = $this->visibleTasks('component')
            ->with('project')
            ->select('id', 'title', 'project_id')
            ->get();

        return response()->json($components);
    }

    /**
     * Get epics by component for dropdown
     */
    public function getEpics($componentId = null)
    {
        $query = $this->visibleTasks('epic')
            ->with('parent')
            ->select('id', 'title', 'parent_task_id');

        if ($componentId) {
            $query->where('parent_task_id', $componentId);
        }

        $epics = $query->get();

        return response()->json($epics);
    }

    /**
     * Get user stories by epic for dropdown
     */
    public function getUserStories($epicId = null)
    {
        $query = $this->visibleTasks('user_story')
            ->with('parent')
            ->select('id', 'title', 'parent_task_id');

        if ($epicId) {
            $query->where('parent_task_id', $epicId);
        }

        $userStories = $query->get();

        return response()->json($userStories);
    }

    /**
     * Get subtasks by user story for dropdown
     */
    public function getSubTasks($userStoryId = null)
    {
        $query = $this->visibleTasks('sub_task')
            ->with('parent')
            ->select('id', 'title', 'parent_task_id');

        if ($userStoryId) {
            $query->where('parent_task_id', $userStoryId);
        }

        $subTasks = $query->get();

        return response()->json($subTasks);
    }

    /**
     * Get bugs by component for dropdown
     */
    public function getBugs($componentId = null)
    {
        $query = $this->visibleTasks('bug')
            ->with('parent')
            ->select('id', 'title', 'parent_task_id');

        if ($componentId) {
            $query->where('parent_task_id', $componentId);
        }

        $bugs = $query->get();

        return response()->json($bugs);
    }

    /**
     * جلب المهام العادية (type=task) لقائمة اختيار المهمة الأب في المشاريع اليدوية
     */
    public function getTasks($projectId = null)
    {
        $query = $this->visibleTasks('task')
            ->with('project')
            ->select('id', 'title', 'project_id');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return response()->json($query->get());
    }

    /**
     * التحقق من صحة العلاقة بين نوع المهمة والمهمة الأب
     */
    private function validateTaskHierarchy($taskType, $parentType)
    {
        $validHierarchy = [
            'epic' => ['component'],
            'user_story' => ['epic'],
            // sub_task قد تتبع قصة مستخدم (سكرم) أو مهمة عادية (إدارة يدوية)
            'sub_task' => ['user_story', 'task'],
            'bug' => ['component'],
        ];

        return isset($validHierarchy[$taskType]) && in_array($parentType, $validHierarchy[$taskType]);
    }

    /**
     * تحديد المشروع الحاكم لمهمة موجودة، مع الصعود في سلسلة الآباء إذا لم
     * يكن project_id مضبوطاً مباشرة عليها (بيانات قديمة قبل إصلاح التوريث).
     */
    private function resolveProjectForTask(Task $task): ?Project
    {
        if ($task->project_id) {
            return $task->project;
        }

        $ancestor = $task->parent;
        while ($ancestor) {
            if ($ancestor->project_id) {
                return $ancestor->project;
            }
            $ancestor = $ancestor->parent;
        }

        return null;
    }

    /**
     * الحصول على نوع المهمة الأب المناسب
     */
    private function getParentType($taskType)
    {
        $parentTypes = [
            'epic' => 'component',
            'user_story' => 'epic',
            'sub_task' => 'user_story',
            'bug' => 'component',
        ];

        return $parentTypes[$taskType] ?? null;
    }

    /**
     * Update Epic's estimated hours based on its user stories
     */
    private function updateEpicEstimatedHours($epic)
    {
        $totalEstimatedHours = Task::where('parent_task_id', $epic->id)
            ->where('type', 'user_story')
            ->sum('estimated_hours');

        $epic->update(['estimated_hours' => $totalEstimatedHours]);
    }

    /**
     * Assign sprint to task and all its children recursively
     */
    public function assignSprintWithInheritance(Request $request, $taskId)
    {
        $validator = Validator::make($request->all(), [
            'sprint_id' => 'required|exists:sprints,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'البيانات المدخلة غير صحيحة',
            ], 422);
        }

        $task = Task::findOrFail($taskId);
        $sprint = Sprint::findOrFail($request->sprint_id);

        ProjectAccess::authorizeTaskWrite($task);

        // Check if the task and sprint belong to the same project
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

        try {
            // في معاملةٍ واحدة: فشلٌ في منتصف الشجرة كان يترك نصفها في
            // السبرنت ونصفها خارجه
            DB::transaction(function () use ($task, $sprint) {
                Task::whereIn('id', [$task->id, ...$this->descendantIds($task)])
                    ->update(['sprint_id' => $sprint->id]);
            });

            OperationLog::record(
                'task_added',
                self::LOG_MODULE,
                $task,
                sprintf('ضُمّت **%s** وما تحتها إلى السبرنت **%s**', $task->title, $sprint->name),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تعيين السبرنت للمهمة وجميع المهام الفرعية الخاصة بها بنجاح',
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'assignSprintWithInheritance');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تعيين السبرنت',
            ], 500);
        }
    }

    /* ==================== الداخل ==================== */

    /** مهامّ نوعٍ واحد ممّا يراه المستخدم — وداخل مشروعٍ بعينه إن حُدّد. */
    private function visibleTasks(string $type, ?Project $project = null)
    {
        return ProjectAccess::scopeByProject(Task::query())
            ->where('type', $type)
            ->when($project, fn ($query) => $query->where('project_id', $project->id));
    }

    /**
     * معرّفات كل ما تحت المهمّة — مستوىً مستوىً باستعلامٍ لكل مستوى، لا
     * استعلامٍ لكل مهمّة.
     *
     * @return array<int, int>
     */
    private function descendantIds(Task $task): array
    {
        $all = [];
        $level = [$task->id];

        for ($depth = 0; $depth < 6 && $level !== []; $depth++) {
            $level = Task::whereIn('parent_task_id', $level)->pluck('id')->all();
            $level = array_values(array_diff($level, $all, [$task->id]));
            $all = [...$all, ...$level];
        }

        return $all;
    }

    /** نقل الأحفاد مع أبيهم إلى مشروعه الجديد — وخارج سبرنتات القديم. */
    private function moveDescendants(Task $task, ?int $projectId): void
    {
        $ids = $this->descendantIds($task);

        if ($ids !== []) {
            Task::whereIn('id', $ids)->update(['project_id' => $projectId, 'sprint_id' => null]);
        }
    }

    /** سبرنتٌ من مشروعٍ آخر؟ يعيد رسالة الخطأ، أو `null` إن صحّ. */
    private function sprintMismatch($sprintId, $projectId): ?string
    {
        if (! $sprintId) {
            return null;
        }

        $sprintProjectId = Sprint::whereKey($sprintId)->value('project_id');

        return (int) $sprintProjectId === (int) $projectId
            ? null
            : 'السبرنت المحدّد ليس من مشروع هذه المهمّة';
    }

    /**
     * صفوف `task_user` بمن أسند ومتى.
     *
     * @param  array<int, int|string>  $userIds
     * @return array<int, array<string, mixed>>
     */
    private function assignmentPivot(array $userIds): array
    {
        $pivot = [];

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            $pivot[$userId] = ['assigned_by' => Auth::id()];
        }

        return $pivot;
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        return [
            'type.required' => 'الرجاء اختيار نوع المهمة',
            'type.in' => 'نوع المهمة غير صالح',
            'title.required' => 'عنوان المهمة مطلوب',
            'title.max' => 'عنوان المهمة يجب أن لا يتجاوز 255 حرف',
            'priority.required' => 'الرجاء اختيار الأولوية',
            'priority.in' => 'الأولوية غير صالحة',
            'status.required' => 'الرجاء اختيار الحالة',
            'status.in' => 'الحالة غير صالحة',
            'project_id.required' => 'الرجاء اختيار المشروع',
            'project_id.exists' => 'المشروع المحدد غير موجود',
            'parent_task_id.required' => 'الرجاء اختيار المهمة الأب',
            'parent_task_id.exists' => 'المهمة الأب المحددة غير موجودة',
            'estimated_hours.required' => 'الرجاء تحديد الوقت المقدر',
            'estimated_hours.numeric' => 'الوقت المقدر يجب أن يكون رقماً',
            'estimated_hours.min' => 'الوقت المقدر يجب أن يكون أكبر من صفر',
            'due_date.after_or_equal' => 'تاريخ الاستحقاق لا يسبق تاريخ البداية',
            'assigned_users.*.exists' => 'أحد المكلَّفين غير موجود',
        ];
    }
}
