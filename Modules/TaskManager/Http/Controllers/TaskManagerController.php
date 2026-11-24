<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Helpers\TaskSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\TaskManager\Services\ProjectAccess;

class TaskManagerController extends Controller
{
    public function __construct()
    {
        // حارسٌ في المتحكّم فوق حارس المسار — لوحة المهام ولوحة المؤشّرات
        // يخدمان مسارين لكلٍّ منهما (عامّ وداخل مشروع)، ونسيانُ أحدهما
        // يفتح الشاشة كلّها
        $this->middleware('permission.role:tasks.board.view', ['only' => ['index']]);
        $this->middleware('permission.role:tasks.board.dashboard', ['only' => ['dashboard']]);
    }

    /**
     * Display the task manager view with user stories and drag-and-drop functionality
     */
    public function index(Request $request, ?Project $project = null)
    {
        // المشروع الحاكم: المشروع المُمرَّر عبر المسار (الصفحة المخصصة لمشروع) له
        // الأولوية، ثم فلتر project_id، ثم المشروع النشط للمستخدم (الصفحة العامة)
        $resolvedProjectId = $project?->id ?? $request->project_id ?? auth()->user()->active_project_id;
        $resolvedProject = $project ?? ($resolvedProjectId ? Project::find($resolvedProjectId) : null);
        $managementType = $resolvedProject?->management_type ?? 'scrum';
        $isManual = $resolvedProject && $resolvedProject->isManual();

        if ($resolvedProject) {
            ProjectAccess::authorizeView($resolvedProject);
        }

        // السبرنت الجاري للمشروع المحدّد (النشط أولاً). المشاريع اليدوية لا
        // تستخدم السبرنتات إطلاقاً، ومشروعٌ بلا سبرنت يُعرض بلا شريط سبرنت —
        // وكان يُصنع له «سبرنت افتراضي» غير محفوظ، وزرُّ إقفاله بلا معرّف
        $currentSprint = $isManual ? null : Sprint::currentFor($resolvedProjectId ? (int) $resolvedProjectId : null);

        // الشاشة تقرأ مكوّنات كل مشروع من `tasks` — فتُحمَّل معه
        $projects = ProjectAccess::scopeProjects(Project::with('tasks'))->get();
        $users = User::all();
        $sprints = ProjectAccess::scopeByProject(Sprint::query())->get();

        // Get project settings using our helper
        $priorities = $resolvedProjectId ? TaskSettingsHelper::getPriorities($resolvedProjectId) : [];
        $statuses = $resolvedProjectId ? TaskSettingsHelper::getStatuses($resolvedProjectId) : [];
        $taskTypes = $resolvedProjectId ? TaskSettingsHelper::getTaskTypes($resolvedProjectId) : [];

        // جلب المهام حسب السبرنت الحالي
        $query = ProjectAccess::scopeByProject(Task::with(['project', 'parent', 'assignedUsers', 'sprint']));

        // تطبيق الفلاتر
        if ($resolvedProjectId) {
            $query->where('project_id', $resolvedProjectId);
        }

        if (! $isManual) {
            if ($request->sprint_id) {
                $query->where('sprint_id', $request->sprint_id);
            } elseif ($currentSprint) {
                // إذا لم يتم تحديد سبرنت، نستخدم السبرنت الحالي
                $query->where('sprint_id', $currentSprint->id);
            }
        }

        if ($request->type) {
            $query->where('type', $request->type);
        } else {
            // افتراضياً نعرض قصص المستخدم في مشاريع سكرم، والمهام في المشاريع اليدوية
            $query->where('type', $isManual ? 'task' : 'user_story');
        }

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

        // تطبيق الفلترة الهرمية (سكرم فقط — لا وجود لإبيك/مكون في المشاريع اليدوية)
        if (! $isManual) {
            if ($request->epic_id) {
                // إذا تم تحديد إبيك، نجلب قصص المستخدم التابعة له
                $query->where('parent_task_id', $request->epic_id);
                $query->where('type', 'user_story');
            } elseif ($request->component_id) {
                // إذا تم تحديد مكون، نحتاج أولاً إلى جلب الإبيكات التابعة له
                // ثم جلب قصص المستخدم التابعة لتلك الإبيكات
                if ($request->type === 'user_story') {
                    // جلب الإبيكات التابعة للمكون
                    $epics = ProjectAccess::scopeByProject(Task::query())->where('type', 'epic')
                        ->where('parent_task_id', $request->component_id)
                        ->pluck('id');

                    // جلب قصص المستخدم التابعة لتلك الإبيكات
                    $query->whereIn('parent_task_id', $epics);
                } elseif ($request->type === 'epic') {
                    // إذا كان نوع المهمة إبيك، نجلب الإبيكات التابعة للمكون
                    $query->where('parent_task_id', $request->component_id);
                }
            }
        }

        $tasks = $query->orderBy('position')->orderBy('id')->get();

        // تنظيم المهام حسب الحالة باستخدام الإعدادات
        $tasksByStatus = $tasks->groupBy('status');

        // أعمدة اللوحة: الحالات بأسمائها من إعدادات المشروع (أو العامّة)
        $statuses = array_column(TaskSettingsHelper::getStatuses($resolvedProjectId), 'name', 'id');

        // جلب المكونات مع مشاريعها
        $components = ProjectAccess::scopeByProject(Task::with('project'))
            ->where('type', 'component')
            ->get(['id', 'title', 'project_id']);

        // جلب الإبيك مع المكونات المرتبطة بها
        $epics = ProjectAccess::scopeByProject(Task::with(['parent', 'parent.project']))
            ->where('type', 'epic')
            ->get(['id', 'title', 'parent_task_id']);

        // جلب قصص المستخدم مع الإبيك المرتبطة بها
        $userStories = ProjectAccess::scopeByProject(Task::with(['parent', 'parent.parent']))
            ->where('type', 'user_story')
            ->get(['id', 'title', 'parent_task_id', 'estimated_hours']);

        // المهام الأب المتاحة للمهام الفرعية في المشاريع اليدوية
        $manualParentTasksQuery = ProjectAccess::scopeByProject(Task::query())->where('type', 'task');
        if ($resolvedProjectId) {
            $manualParentTasksQuery->where('project_id', $resolvedProjectId);
        }
        $manualParentTasks = $manualParentTasksQuery->get(['id', 'title', 'project_id']);

        return view('taskmanager::tasks.task_manger', compact(
            'currentSprint',
            'projects',
            'users',
            'sprints',
            'tasksByStatus',
            'statuses',
            'priorities',
            'taskTypes',
            'managementType',
            'manualParentTasks',
            'resolvedProjectId',
            'components',
            'epics',
            'userStories'
        ));
    }

    /**
     * Display the task dashboard with comprehensive statistics
     */
    public function dashboard(Request $request, ?Project $project = null)
    {
        if ($project) {
            ProjectAccess::authorizeView($project);
        }

        // قوائم الفلاتر — ممّا يراه المستخدم
        $allProjects = ProjectAccess::scopeProjects(Project::query())->get(['id', 'title', 'workspace_id']);
        $allSprints = ProjectAccess::scopeByProject(Sprint::query())->get(['id', 'name', 'project_id', 'status']);
        $allWorkspaces = ProjectAccess::scopeWorkspaces(Workspace::query())->get(['id', 'title']);

        $filterProjectId = $project?->id ?? $request->project_id;

        // السبرنت الجاري للمشروع المحدّد — وبلا مشروع، الجاري لمشروع المستخدم النشط
        $currentSprint = Sprint::currentFor((int) ($filterProjectId ?: auth()->user()->active_project_id) ?: null);

        if ($currentSprint && ! ProjectAccess::canView($currentSprint->project_id)) {
            $currentSprint = null;
        }

        $taskQuery = ProjectAccess::scopeByProject(Task::query());
        $projectQuery = ProjectAccess::scopeProjects(Project::query());
        $sprintQuery = ProjectAccess::scopeByProject(Sprint::query());

        if ($filterProjectId) {
            $taskQuery->where('project_id', $filterProjectId);
            $projectQuery->where('id', $filterProjectId);
            $sprintQuery->where('project_id', $filterProjectId);
        }

        if ($request->sprint_id) {
            $sprintId = $request->sprint_id === 'current' ? $currentSprint?->id : $request->sprint_id;

            if ($sprintId) {
                $taskQuery->where('sprint_id', $sprintId);
                $sprintQuery->where('id', $sprintId);
            }
        }

        // أعمدة العدّ وحدها — لا الوصف ولا بقيّة الصفّ
        $tasks = $taskQuery->get(['id', 'project_id', 'sprint_id', 'type', 'status', 'title', 'priority', 'due_date', 'completed_date', 'created_at', 'updated_at']);

        // ============== User Story Statistics ==============
        $userStoryTasks = $tasks->where('type', 'user_story');

        $userStoryTotal = $userStoryTasks->count();
        $userStoryCompleted = $userStoryTasks->where('status', 'completed')->count();
        $userStoryInProgress = $userStoryTasks->where('status', 'in_progress')->count();
        $userStoryNotStarted = $userStoryTasks->where('status', 'not_started')->count();
        $userStoryOverdue = $userStoryTasks->where('status', 'overdue')->count();
        $userStoryCompletionRate = $userStoryTotal > 0 ? ($userStoryCompleted / $userStoryTotal) * 100 : 0;

        // Basic task statistics
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $inProgressTasks = $tasks->where('status', 'in_progress')->count();
        $notStartedTasks = $tasks->where('status', 'not_started')->count();
        $overdueTasks = $tasks->where('status', 'overdue')->count();

        // المهامّ مجمّعةً مرّةً واحدة — كانت كل حلقةٍ أدناه تسأل القاعدة عن
        // مهامّ كل مشروعٍ وكل سبرنت على حدة
        $tasksByProject = $tasks->groupBy('project_id');
        $tasksBySprint = $tasks->whereNotNull('sprint_id')->groupBy('sprint_id');
        $isOpen = fn ($group) => $group && $group->where('status', '!=', 'completed')->isNotEmpty();

        // Project statistics (filtered)
        $projects = $projectQuery->get(['id', 'title', 'workspace_id']);
        $totalProjects = $projects->count();
        $activeProjects = $projects->filter(fn ($item) => $isOpen($tasksByProject->get($item->id)))->count();

        /*
            «نسبة الإنجاز» = ما اكتمل ÷ الكل. وكانت تُحسب من المشاريع
            **النشطة**: مساحةٌ كل مشاريعها متعثّرة تُعرض 100% منجزة.
            المنجَز ما له مهامّ وكلّها مكتملة.
        */
        $isDone = fn ($group) => $group && $group->isNotEmpty() && $group->where('status', '!=', 'completed')->isEmpty();
        $doneProjects = $projects->filter(fn ($item) => $isDone($tasksByProject->get($item->id)))->count();
        $projectCompletionRate = $totalProjects > 0 ? ($doneProjects / $totalProjects) * 100 : 0;

        // Sprint statistics (filtered)
        $sprints = $sprintQuery->get(['id', 'name', 'status', 'project_id']);
        $totalSprints = $sprints->count();
        $activeSprints = $sprints->where('status', 'active')->count();
        $completedSprints = $sprints->whereIn('status', ['completed', 'closed'])->count();
        $sprintCompletionRate = $totalSprints > 0 ? ($completedSprints / $totalSprints) * 100 : 0;

        // Workspace statistics — مساحات المشاريع المعروضة
        $projectsByWorkspace = $projects->groupBy('workspace_id');
        $workspaces = $allWorkspaces->whereIn('id', $projectsByWorkspace->keys());
        $totalWorkspaces = $filterProjectId ? $workspaces->count() : $allWorkspaces->count();

        $workspaceTasks = fn ($workspace) => ($projectsByWorkspace->get($workspace->id) ?? collect())
            ->flatMap(fn ($item) => $tasksByProject->get($item->id) ?? collect());

        $activeWorkspaces = $workspaces->filter(fn ($workspace) => $isOpen($workspaceTasks($workspace)))->count();
        $doneWorkspaces = $workspaces->filter(fn ($workspace) => $isDone($workspaceTasks($workspace)))->count();
        $workspaceCompletionRate = $totalWorkspaces > 0 ? ($doneWorkspaces / $totalWorkspaces) * 100 : 0;

        $summarize = function ($group, array $extra) {
            $total = $group ? $group->count() : 0;
            $completed = $group ? $group->where('status', 'completed')->count() : 0;

            return (object) ($extra + [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'completion_rate' => $total > 0 ? ($completed / $total) * 100 : 0,
            ]);
        };

        // Top projects by completion rate (filtered)
        $topProjects = $projects
            ->map(fn ($item) => $summarize($tasksByProject->get($item->id), ['id' => $item->id, 'title' => $item->title]))
            ->sortByDesc('completion_rate')
            ->take(5);

        // Current sprints with completion rates (filtered)
        $currentSprints = $sprints
            ->map(fn ($sprint) => $summarize($tasksBySprint->get($sprint->id), ['id' => $sprint->id, 'name' => $sprint->name]));

        // Sprint completion rates for chart
        $sprintCompletionRates = $currentSprints->map(fn ($sprint) => round($sprint->completion_rate, 2))->values()->all();
        $sprintNames = $currentSprints->pluck('name')->values()->all();

        // Monthly task trends (filtered)
        $months = [];
        $monthlyTasks = [];
        $monthlyCompletedTasks = [];

        // شهر الإنجاز من `completed_date` — و`updated_at` لما اكتمل قبل أن
        // يُملأ العمود. كان `updated_at` وحده، فتصحيحُ عنوان مهمّةٍ مكتملة
        // ينقلها إلى إنجاز هذا الشهر
        $completedOn = fn ($task) => $task->completed_date
            ? Carbon::parse($task->completed_date)
            : $task->updated_at;

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->startOfMonth()->subMonths($i);
            $months[] = $date->translatedFormat('F Y');

            $monthlyTasks[] = $tasks->filter(fn ($task) => $task->created_at && $task->created_at->isSameMonth($date))->count();

            $monthlyCompletedTasks[] = $tasks->filter(function ($task) use ($date, $completedOn) {
                $on = $task->status === 'completed' ? $completedOn($task) : null;

                return $on && $on->isSameMonth($date);
            })->count();
        }

        // Task types distribution (filtered)
        $typeLabels = array_column(TaskVocabulary::types(), 'name', 'id');
        $taskTypes = $tasks->groupBy('type')->map(fn ($group, $type) => (object) [
            'id' => $type,
            'type' => $typeLabels[$type] ?? $type,
            'count' => $group->count(),
        ])->values();

        // Top users by completed tasks (filtered) — من جدول الإسناد مباشرةً:
        // كان يحمّل كل المستخدمين ثم يسأل عن مكلَّفي كل مهمّةٍ لكل مستخدم
        $assignments = $tasks->isEmpty() ? collect() : DB::table('task_user')
            ->join('users', 'users.id', '=', 'task_user.user_id')
            ->whereIn('task_user.task_id', $tasks->pluck('id'))
            ->get(['task_user.task_id', 'users.id', 'users.name']);

        $statusById = $tasks->pluck('status', 'id');

        $topUsers = $assignments->groupBy('id')->map(function ($rows) use ($statusById) {
            $total = $rows->count();
            $completed = $rows->filter(fn ($row) => $statusById->get($row->task_id) === 'completed')->count();

            return (object) [
                'id' => $rows->first()->id,
                'name' => $rows->first()->name,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'completion_rate' => $total > 0 ? ($completed / $total) * 100 : 0,
            ];
        })->sortByDesc('completion_rate')->take(5);

        // Recent tasks (filtered)
        $recentTasks = $tasks->sortByDesc('created_at')->take(10);

        return view('taskmanager::dashboard.task.index', compact(
            'allProjects',
            'allSprints',
            'allWorkspaces',
            'currentSprint',
            'totalTasks',
            'completedTasks',
            'inProgressTasks',
            'notStartedTasks',
            'overdueTasks',
            'totalProjects',
            'activeProjects',
            'projectCompletionRate',
            'totalSprints',
            'activeSprints',
            'completedSprints',
            'sprintCompletionRate',
            'totalWorkspaces',
            'activeWorkspaces',
            'workspaceCompletionRate',
            'topProjects',
            'currentSprints',
            'sprintCompletionRates',
            'sprintNames',
            'months',
            'monthlyTasks',
            'monthlyCompletedTasks',
            'taskTypes',
            'topUsers',
            'recentTasks',
            // User Story Statistics
            'userStoryTotal',
            'userStoryCompleted',
            'userStoryInProgress',
            'userStoryNotStarted',
            'userStoryOverdue',
            'userStoryCompletionRate'
        ));
    }
}
