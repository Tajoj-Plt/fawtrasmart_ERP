<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\OperationLog;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\TaskManager\Services\ProjectAccess;

class ReportsController extends Controller
{
    public function __construct()
    {
        /*
            كل تقارير الوحدة بصلاحيةٍ واحدة، إلا اثنتين:

            `workspacePerformance` تقيس إنجاز كل عضوٍ على حدة — وهي قراءةٌ
            في أداء الأفراد لا في حال المشروع، فصلاحيتُها مستقلّة.

            و`export` تُخرج البيانات من النظام، والإخراج فعلٌ آخر غير
            العرض: من يقرأ الشاشة ليس بالضرورة من يُسمح له بحمل الملف.
        */
        $this->middleware('permission.role:tasks.reports.view');
        $this->middleware('permission.role:tasks.reports.performance', ['only' => ['workspacePerformance']]);
        $this->middleware('permission.role:tasks.reports.export', ['only' => ['export']]);
    }

    /**
     * Display reports index page
     */
    public function index()
    {
        $quickStats = [
            'tasks' => ProjectAccess::scopeByProject(Task::query())->count(),
            'tasks_completed' => ProjectAccess::scopeByProject(Task::query())->where('status', 'completed')->count(),
            'projects' => ProjectAccess::scopeProjects(Project::query())->count(),
            'projects_active' => ProjectAccess::scopeProjects(Project::query())->where('status', '!=', 'completed')->count(),
            'workspaces' => ProjectAccess::scopeWorkspaces(Workspace::query())->count(),
        ];

        return view('taskmanager::reports.index', compact('quickStats'));
    }

    /**
     * Display task reports dashboard
     */
    public function tasks(Request $request)
    {
        // Get filter parameters
        $projectId = $request->get('project_id');
        $workspaceId = $request->get('workspace_id');
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');
        $priority = $request->get('priority');
        $taskType = $request->get('task_type');

        // Build base queries
        $taskQuery = ProjectAccess::scopeByProject(Task::with(['project', 'project.workspace', 'assignedUsers', 'sprint']));
        $projectQuery = ProjectAccess::scopeProjects(Project::with(['workspace', 'tasks']));
        $workspaceQuery = $this->visibleWorkspaces();

        // Apply filters
        if ($projectId) {
            $taskQuery->where('project_id', $projectId);
            $projectQuery->where('id', $projectId);
        }

        if ($workspaceId) {
            $taskQuery->whereHas('project', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            });
            $projectQuery->where('workspace_id', $workspaceId);
            $workspaceQuery->where('id', $workspaceId);
        }

        if ($userId) {
            $taskQuery->whereHas('assignedUsers', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $taskQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $taskQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            $taskQuery->where('status', $status);
        }

        if ($priority) {
            $taskQuery->where('priority', $priority);
        }

        if ($taskType) {
            $taskQuery->where('type', $taskType);
        }

        // Get filtered data
        $tasks = $taskQuery->get();
        $projects = $projectQuery->get();
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Task Statistics
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $inProgressTasks = $tasks->where('status', 'in_progress')->count();
        $notStartedTasks = $tasks->where('status', 'not_started')->count();
        $overdueTasks = $tasks->where('status', 'overdue')->count();
        $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

        // Project Statistics
        $totalProjects = $projects->count();
        $activeProjects = $projects->filter(function ($project) {
            return $project->tasks->where('status', '!=', 'completed')->count() > 0;
        })->count();
        $projectCompletionRate = $totalProjects > 0 ? ($activeProjects / $totalProjects) * 100 : 0;

        // Workspace Statistics
        $totalWorkspaces = $workspaces->count();
        $activeWorkspaces = $workspaces->filter(function ($workspace) {
            return $workspace->projects->filter(function ($project) {
                return $project->tasks->where('status', '!=', 'completed')->count() > 0;
            })->count() > 0;
        })->count();
        $workspaceCompletionRate = $totalWorkspaces > 0 ? ($activeWorkspaces / $totalWorkspaces) * 100 : 0;

        // Task Types Distribution
        $taskTypes = $tasks->groupBy('type')->map(function ($group, $type) {
            $typeLabels = [
                'user_story' => 'قصة مستخدم',
                'epic' => 'إبيك',
                'component' => 'مكون',
                'bug' => 'خلل',
                'sub_task' => 'مهمة فرعية',
                'task' => 'مهمة',
            ];

            return (object) [
                'type' => $typeLabels[$type] ?? $type,
                'count' => $group->count(),
            ];
        })->values();

        // Task Status Distribution
        $taskStatuses = $tasks->groupBy('status')->map(function ($group, $status) {
            $statusLabels = [
                'not_started' => 'لم تبدأ',
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ];

            return (object) [
                'status' => $statusLabels[$status] ?? $status,
                'count' => $group->count(),
            ];
        })->values();

        // Task Priority Distribution
        $taskPriorities = $tasks->groupBy('priority')->map(function ($group, $priority) {
            $priorityLabels = [
                'low' => 'منخفضة',
                'medium' => 'متوسطة',
                'high' => 'عالية',
            ];

            return (object) [
                'priority' => $priorityLabels[$priority] ?? $priority,
                'count' => $group->count(),
            ];
        })->values();

        // Top Users by completed tasks
        $topUsers = $users->map(function ($user) use ($tasks) {
            $userTasks = $tasks->filter(function ($task) use ($user) {
                return $task->assignedUsers->contains($user);
            });

            $totalTasks = $userTasks->count();
            $completedTasks = $userTasks->where('status', 'completed')->count();
            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            return (object) [
                'id' => $user->id,
                'name' => $user->name,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $completionRate,
            ];
        })->sortByDesc('completion_rate')->take(10);

        // Top Projects by completion rate
        $topProjects = $projects->map(function ($project) {
            $projectTasks = $project->tasks;
            $totalTasks = $projectTasks->count();
            $completedTasks = $projectTasks->where('status', 'completed')->count();
            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            return (object) [
                'id' => $project->id,
                'title' => $project->title,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $completionRate,
            ];
        })->sortByDesc('completion_rate')->take(10);

        // Top Workspaces by completion rate
        $topWorkspaces = $workspaces->map(function ($workspace) {
            $workspaceProjects = $workspace->projects;
            $totalTasks = 0;
            $completedTasks = 0;

            foreach ($workspaceProjects as $project) {
                $projectTasks = $project->tasks;
                $totalTasks += $projectTasks->count();
                $completedTasks += $projectTasks->where('status', 'completed')->count();
            }

            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            return (object) [
                'id' => $workspace->id,
                'title' => $workspace->title,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $completionRate,
            ];
        })->sortByDesc('completion_rate')->take(10);

        // Monthly task trends
        $months = [];
        $monthlyTasks = [];
        $monthlyCompletedTasks = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->translatedFormat('F Y');

            $monthlyTasks[] = $tasks->filter(function ($task) use ($date) {
                return $task->created_at &&
                       $task->created_at->year == $date->year &&
                       $task->created_at->month == $date->month;
            })->count();

            $monthlyCompletedTasks[] = $tasks->filter(function ($task) use ($date) {
                return $task->updated_at &&
                       $task->updated_at->year == $date->year &&
                       $task->updated_at->month == $date->month &&
                       $task->status == 'completed';
            })->count();
        }

        // Prepare data for charts
        $taskTypeLabels = $taskTypes->pluck('type')->toArray();
        $taskTypeCounts = $taskTypes->pluck('count')->toArray();

        $taskStatusLabels = $taskStatuses->pluck('status')->toArray();
        $taskStatusCounts = $taskStatuses->pluck('count')->toArray();

        $taskPriorityLabels = $taskPriorities->pluck('priority')->toArray();
        $taskPriorityCounts = $taskPriorities->pluck('count')->toArray();

        return view('taskmanager::reports.tasks.summary', compact(
            'tasks',
            'projects',
            'workspaces',
            'users',
            'totalTasks',
            'completedTasks',
            'inProgressTasks',
            'notStartedTasks',
            'overdueTasks',
            'completionRate',
            'totalProjects',
            'activeProjects',
            'projectCompletionRate',
            'totalWorkspaces',
            'activeWorkspaces',
            'workspaceCompletionRate',
            'taskTypes',
            'taskStatuses',
            'taskPriorities',
            'topUsers',
            'topProjects',
            'topWorkspaces',
            'months',
            'monthlyTasks',
            'monthlyCompletedTasks',
            'taskTypeLabels',
            'taskTypeCounts',
            'taskStatusLabels',
            'taskStatusCounts',
            'taskPriorityLabels',
            'taskPriorityCounts'
        ));
    }

    /**
     * Display task types report
     */
    public function taskTypes(Request $request)
    {
        // Get filter parameters
        $projectId = $request->get('project_id');
        $workspaceId = $request->get('workspace_id');
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');
        $priority = $request->get('priority');

        // Build base queries
        $taskQuery = ProjectAccess::scopeByProject(Task::with(['project', 'project.workspace', 'assignedUsers']));
        $projectQuery = ProjectAccess::scopeProjects(Project::with(['workspace', 'tasks']));
        $workspaceQuery = $this->visibleWorkspaces();

        // Apply filters
        if ($projectId) {
            $taskQuery->where('project_id', $projectId);
            $projectQuery->where('id', $projectId);
        }

        if ($workspaceId) {
            $taskQuery->whereHas('project', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            });
            $projectQuery->where('workspace_id', $workspaceId);
            $workspaceQuery->where('id', $workspaceId);
        }

        if ($userId) {
            $taskQuery->whereHas('assignedUsers', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $taskQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $taskQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            $taskQuery->where('status', $status);
        }

        if ($priority) {
            $taskQuery->where('priority', $priority);
        }

        // Get filtered data
        $tasks = $taskQuery->get();
        $projects = $projectQuery->get();
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Task Statistics
        $totalTasks = $tasks->count();

        // Task Types Distribution with detailed information
        $taskTypesDetail = $tasks->groupBy('type')->map(function ($group, $type) {
            $typeLabels = [
                'user_story' => 'قصة مستخدم',
                'epic' => 'إبيك',
                'component' => 'مكون',
                'bug' => 'خلل',
                'sub_task' => 'مهمة فرعية',
                'task' => 'مهمة',
            ];

            $inProgress = $group->where('status', 'in_progress')->count();
            $completed = $group->where('status', 'completed')->count();
            $overdue = $group->where('status', 'overdue')->count();
            $count = $group->count();
            $percentage = $count > 0 ? ($count / $group->count()) * 100 : 0;

            return (object) [
                'type' => $type,
                'name' => $typeLabels[$type] ?? $type,
                'count' => $count,
                'percentage' => $percentage,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'overdue' => $overdue,
            ];
        })->values();

        // Sort by count descending
        $taskTypesDetail = $taskTypesDetail->sortByDesc('count')->values();

        $taskTypesCount = $taskTypesDetail->count();
        $mostCommonTypeCount = $taskTypesDetail->first() ? $taskTypesDetail->first()->count : 0;
        $leastCommonTypeCount = $taskTypesDetail->last() ? $taskTypesDetail->last()->count : 0;

        // Prepare data for charts
        $taskTypeLabels = $taskTypesDetail->pluck('name')->toArray();
        $taskTypeCounts = $taskTypesDetail->pluck('count')->toArray();
        $inProgressCounts = $taskTypesDetail->pluck('in_progress')->toArray();
        $completedCounts = $taskTypesDetail->pluck('completed')->toArray();
        $overdueCounts = $taskTypesDetail->pluck('overdue')->toArray();

        return view('taskmanager::reports.tasks.types', compact(
            'tasks',
            'projects',
            'workspaces',
            'users',
            'totalTasks',
            'taskTypesDetail',
            'taskTypesCount',
            'mostCommonTypeCount',
            'leastCommonTypeCount',
            'taskTypeLabels',
            'taskTypeCounts',
            'inProgressCounts',
            'completedCounts',
            'overdueCounts'
        ));
    }

    /**
     * Display task status report
     */
    public function taskStatus(Request $request)
    {
        // Get filter parameters
        $projectId = $request->get('project_id');
        $workspaceId = $request->get('workspace_id');
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $priority = $request->get('priority');
        $taskType = $request->get('task_type');

        // Build base queries
        $taskQuery = ProjectAccess::scopeByProject(Task::with(['project', 'project.workspace', 'assignedUsers']));
        $projectQuery = ProjectAccess::scopeProjects(Project::with(['workspace', 'tasks']));
        $workspaceQuery = $this->visibleWorkspaces();

        // Apply filters
        if ($projectId) {
            $taskQuery->where('project_id', $projectId);
            $projectQuery->where('id', $projectId);
        }

        if ($workspaceId) {
            $taskQuery->whereHas('project', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            });
            $projectQuery->where('workspace_id', $workspaceId);
            $workspaceQuery->where('id', $workspaceId);
        }

        if ($userId) {
            $taskQuery->whereHas('assignedUsers', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $taskQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $taskQuery->where('created_at', '<=', $toDate);
        }

        if ($priority) {
            $taskQuery->where('priority', $priority);
        }

        if ($taskType) {
            $taskQuery->where('type', $taskType);
        }

        // Get filtered data
        $tasks = $taskQuery->get();
        $projects = $projectQuery->get();
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Task Statistics
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $inProgressTasks = $tasks->where('status', 'in_progress')->count();
        $notStartedTasks = $tasks->where('status', 'not_started')->count();
        $overdueTasks = $tasks->where('status', 'overdue')->count();
        $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

        // Task Status Distribution with detailed information
        $taskStatusesDetail = $tasks->groupBy('status')->map(function ($group, $status) {
            $statusLabels = [
                'not_started' => 'لم تبدأ',
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ];

            $highPriority = $group->where('priority', 'high')->count();
            $mediumPriority = $group->where('priority', 'medium')->count();
            $lowPriority = $group->where('priority', 'low')->count();
            $count = $group->count();
            $percentage = $count > 0 ? ($count / $group->count()) * 100 : 0;

            return (object) [
                'status' => $status,
                'name' => $statusLabels[$status] ?? $status,
                'count' => $count,
                'percentage' => $percentage,
                'high_priority' => $highPriority,
                'medium_priority' => $mediumPriority,
                'low_priority' => $lowPriority,
            ];
        })->values();

        // Sort by count descending
        $taskStatusesDetail = $taskStatusesDetail->sortByDesc('count')->values();

        $statusCount = $taskStatusesDetail->count();
        $mostCommonStatusCount = $taskStatusesDetail->first() ? $taskStatusesDetail->first()->count : 0;
        $leastCommonStatusCount = $taskStatusesDetail->last() ? $taskStatusesDetail->last()->count : 0;

        // Prepare data for charts
        $taskStatusLabels = $taskStatusesDetail->pluck('name')->toArray();
        $taskStatusCounts = $taskStatusesDetail->pluck('count')->toArray();
        $highPriorityCounts = $taskStatusesDetail->pluck('high_priority')->toArray();
        $mediumPriorityCounts = $taskStatusesDetail->pluck('medium_priority')->toArray();
        $lowPriorityCounts = $taskStatusesDetail->pluck('low_priority')->toArray();

        return view('taskmanager::reports.tasks.status', compact(
            'tasks',
            'projects',
            'workspaces',
            'users',
            'totalTasks',
            'completedTasks',
            'inProgressTasks',
            'notStartedTasks',
            'overdueTasks',
            'completionRate',
            'taskStatusesDetail',
            'statusCount',
            'mostCommonStatusCount',
            'leastCommonStatusCount',
            'taskStatusLabels',
            'taskStatusCounts',
            'highPriorityCounts',
            'mediumPriorityCounts',
            'lowPriorityCounts'
        ));
    }

    /**
     * Display task priority report
     */
    public function taskPriority(Request $request)
    {
        // Get filter parameters
        $projectId = $request->get('project_id');
        $workspaceId = $request->get('workspace_id');
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');
        $taskType = $request->get('task_type');

        // Build base queries
        $taskQuery = ProjectAccess::scopeByProject(Task::with(['project', 'project.workspace', 'assignedUsers']));
        $projectQuery = ProjectAccess::scopeProjects(Project::with(['workspace', 'tasks']));
        $workspaceQuery = $this->visibleWorkspaces();

        // Apply filters
        if ($projectId) {
            $taskQuery->where('project_id', $projectId);
            $projectQuery->where('id', $projectId);
        }

        if ($workspaceId) {
            $taskQuery->whereHas('project', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            });
            $projectQuery->where('workspace_id', $workspaceId);
            $workspaceQuery->where('id', $workspaceId);
        }

        if ($userId) {
            $taskQuery->whereHas('assignedUsers', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $taskQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $taskQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            $taskQuery->where('status', $status);
        }

        if ($taskType) {
            $taskQuery->where('type', $taskType);
        }

        // Get filtered data
        $tasks = $taskQuery->get();
        $projects = $projectQuery->get();
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Task Statistics
        $totalTasks = $tasks->count();
        $highPriorityTasks = $tasks->where('priority', 'high')->count();
        $mediumPriorityTasks = $tasks->where('priority', 'medium')->count();
        $lowPriorityTasks = $tasks->where('priority', 'low')->count();

        // Task Priority Distribution with detailed information
        $taskPrioritiesDetail = $tasks->groupBy('priority')->map(function ($group, $priority) {
            $priorityLabels = [
                'low' => 'منخفضة',
                'medium' => 'متوسطة',
                'high' => 'عالية',
            ];

            $notStarted = $group->where('status', 'not_started')->count();
            $inProgress = $group->where('status', 'in_progress')->count();
            $completed = $group->where('status', 'completed')->count();
            $overdue = $group->where('status', 'overdue')->count();
            $count = $group->count();
            $percentage = $count > 0 ? ($count / $group->count()) * 100 : 0;

            return (object) [
                'priority' => $priority,
                'name' => $priorityLabels[$priority] ?? $priority,
                'count' => $count,
                'percentage' => $percentage,
                'not_started' => $notStarted,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'overdue' => $overdue,
            ];
        })->values();

        // Sort by priority order (high, medium, low)
        $priorityOrder = ['high', 'medium', 'low'];
        $taskPrioritiesDetail = $taskPrioritiesDetail->sortBy(function ($item) use ($priorityOrder) {
            return array_search($item->priority, $priorityOrder);
        })->values();

        $priorityCount = $taskPrioritiesDetail->count();

        // Prepare data for charts
        $taskPriorityLabels = $taskPrioritiesDetail->pluck('name')->toArray();
        $taskPriorityCounts = $taskPrioritiesDetail->pluck('count')->toArray();
        $notStartedCounts = $taskPrioritiesDetail->pluck('not_started')->toArray();
        $inProgressCounts = $taskPrioritiesDetail->pluck('in_progress')->toArray();
        $completedCounts = $taskPrioritiesDetail->pluck('completed')->toArray();
        $overdueCounts = $taskPrioritiesDetail->pluck('overdue')->toArray();

        return view('taskmanager::reports.tasks.priority', compact(
            'tasks',
            'projects',
            'workspaces',
            'users',
            'totalTasks',
            'highPriorityTasks',
            'mediumPriorityTasks',
            'lowPriorityTasks',
            'taskPrioritiesDetail',
            'priorityCount',
            'taskPriorityLabels',
            'taskPriorityCounts',
            'notStartedCounts',
            'inProgressCounts',
            'completedCounts',
            'overdueCounts'
        ));
    }

    /**
     * Display project reports
     */
    public function projects(Request $request)
    {
        // Get filter parameters
        $workspaceId = $request->get('workspace_id');
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');
        $priority = $request->get('priority');

        // Build base queries
        $projectQuery = ProjectAccess::scopeProjects(Project::with(['workspace', 'tasks']));
        $workspaceQuery = $this->visibleWorkspaces();

        // Apply filters
        if ($workspaceId) {
            $projectQuery->where('workspace_id', $workspaceId);
            $workspaceQuery->where('id', $workspaceId);
        }

        if ($userId) {
            $projectQuery->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $projectQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $projectQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            $projectQuery->where('status', $status);
        }

        if ($priority) {
            $projectQuery->where('priority', $priority);
        }

        // Get filtered data
        $projects = $projectQuery->get();
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Project Statistics
        $totalProjects = $projects->count();
        $completedProjects = $projects->where('status', 'completed')->count();
        $activeProjects = $projects->where('status', 'active')->count();
        $onHoldProjects = $projects->where('status', 'on_hold')->count();
        $cancelledProjects = $projects->where('status', 'cancelled')->count();

        // Calculate overdue projects
        $overdueProjects = $projects->filter(function ($project) {
            return $project->end_date && $project->end_date < now() && $project->status != 'completed';
        })->count();

        // Calculate completion rate
        $completionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;

        // Get all tasks for these projects
        $projectIds = $projects->pluck('id')->toArray();
        $tasks = Task::whereIn('project_id', $projectIds)->get();
        $totalTasks = $tasks->count();

        // Project Status Distribution
        $projectStatuses = $projects->groupBy('status')->map(function ($group, $status) {
            $statusLabels = [
                'active' => 'نشط',
                'completed' => 'مكتمل',
                'on_hold' => 'معلق',
                'cancelled' => 'ملغى',
            ];

            return (object) [
                'status' => $statusLabels[$status] ?? $status,
                'count' => $group->count(),
            ];
        })->values();

        // Project Priority Distribution
        $projectPriorities = $projects->groupBy('priority')->map(function ($group, $priority) {
            $priorityLabels = [
                'low' => 'منخفضة',
                'medium' => 'متوسطة',
                'high' => 'عالية',
            ];

            return (object) [
                'priority' => $priorityLabels[$priority] ?? $priority,
                'count' => $group->count(),
            ];
        })->values();

        // Top Projects by completion rate
        $topProjects = $projects->map(function ($project) {
            $projectTasks = $project->tasks;
            $totalTasks = $projectTasks->count();
            $completedTasks = $projectTasks->where('status', 'completed')->count();
            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            return (object) [
                'id' => $project->id,
                'title' => $project->title,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $completionRate,
            ];
        })->sortByDesc('completion_rate')->take(10);

        // Top Workspaces by projects
        $topWorkspaces = $workspaces->map(function ($workspace) {
            $workspaceProjects = $workspace->projects;
            $totalProjects = $workspaceProjects->count();
            $completedProjects = $workspaceProjects->where('status', 'completed')->count();
            $completionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;

            return (object) [
                'id' => $workspace->id,
                'title' => $workspace->title,
                'total_projects' => $totalProjects,
                'completed_projects' => $completedProjects,
                'completion_rate' => $completionRate,
            ];
        })->sortByDesc('completion_rate')->take(10);

        // Monthly project trends
        $months = [];
        $monthlyProjects = [];
        $monthlyCompletedProjects = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->translatedFormat('F Y');

            $monthlyProjects[] = $projects->filter(function ($project) use ($date) {
                return $project->created_at &&
                       $project->created_at->year == $date->year &&
                       $project->created_at->month == $date->month;
            })->count();

            $monthlyCompletedProjects[] = $projects->filter(function ($project) use ($date) {
                return $project->updated_at &&
                       $project->updated_at->year == $date->year &&
                       $project->updated_at->month == $date->month &&
                       $project->status == 'completed';
            })->count();
        }

        // Workspace distribution
        $workspaceDistribution = $projects->groupBy('workspace_id')->map(function ($group) {
            $workspace = $group->first()->workspace;

            return (object) [
                'workspace' => $workspace ? $workspace->title : 'غير محدد',
                'count' => $group->count(),
            ];
        })->values();

        // Prepare data for charts
        $projectStatusLabels = $projectStatuses->pluck('status')->toArray();
        $projectStatusCounts = $projectStatuses->pluck('count')->toArray();

        $projectPriorityLabels = $projectPriorities->pluck('priority')->toArray();
        $projectPriorityCounts = $projectPriorities->pluck('count')->toArray();

        $workspaceLabels = $workspaceDistribution->pluck('workspace')->toArray();
        $workspaceCounts = $workspaceDistribution->pluck('count')->toArray();

        return view('taskmanager::reports.projects.summary', compact(
            'projects',
            'workspaces',
            'users',
            'totalProjects',
            'completedProjects',
            'activeProjects',
            'onHoldProjects',
            'cancelledProjects',
            'overdueProjects',
            'completionRate',
            'totalTasks',
            'projectStatuses',
            'projectPriorities',
            'topProjects',
            'topWorkspaces',
            'months',
            'monthlyProjects',
            'monthlyCompletedProjects',
            'workspaceLabels',
            'workspaceCounts',
            'projectStatusLabels',
            'projectStatusCounts',
            'projectPriorityLabels',
            'projectPriorityCounts'
        ));
    }

    /**
     * Display project completion report
     */
    public function projectCompletion(Request $request)
    {
        // Get filter parameters
        $workspaceId = $request->get('workspace_id');
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');
        $priority = $request->get('priority');

        // Build base queries
        $projectQuery = ProjectAccess::scopeProjects(Project::with(['workspace', 'tasks']));
        $workspaceQuery = $this->visibleWorkspaces();

        // Apply filters
        if ($workspaceId) {
            $projectQuery->where('workspace_id', $workspaceId);
            $workspaceQuery->where('id', $workspaceId);
        }

        if ($userId) {
            $projectQuery->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $projectQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $projectQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            $projectQuery->where('status', $status);
        }

        if ($priority) {
            $projectQuery->where('priority', $priority);
        }

        // Get filtered data
        $projects = $projectQuery->get();
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Project Statistics
        $totalProjects = $projects->count();
        $completedProjects = $projects->where('status', 'completed')->count();

        // Calculate completion rate
        $completionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;

        // Calculate average completion time
        $completedProjectsWithDates = $projects->filter(function ($project) {
            return $project->status == 'completed' && $project->start_date && $project->actual_end_date;
        });

        $totalCompletionDays = 0;
        foreach ($completedProjectsWithDates as $project) {
            $start = $project->start_date;
            $end = $project->actual_end_date;
            $totalCompletionDays += $start->diffInDays($end);
        }

        $avgCompletionTime = $completedProjectsWithDates->count() > 0 ? $totalCompletionDays / $completedProjectsWithDates->count() : 0;

        // Project details with completion information
        $projectDetails = $projects->map(function ($project) {
            $totalTasks = $project->tasks->count();
            $completedTasks = $project->tasks->where('status', 'completed')->count();
            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            $completionDays = null;
            if ($project->start_date && $project->actual_end_date) {
                $completionDays = $project->start_date->diffInDays($project->actual_end_date);
            }

            return (object) [
                'id' => $project->id,
                'title' => $project->title,
                'workspace' => $project->workspace ? $project->workspace->title : 'غير محدد',
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $completionRate,
                'start_date' => $project->start_date,
                'actual_end_date' => $project->actual_end_date,
                'completion_days' => $completionDays,
            ];
        })->sortByDesc('completion_rate')->values();

        // Monthly completion trends
        $months = [];
        $completionRates = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->translatedFormat('F Y');

            $monthlyProjects = $projects->filter(function ($project) use ($date) {
                return $project->created_at &&
                       $project->created_at->year == $date->year &&
                       $project->created_at->month == $date->month;
            });

            $monthlyCompleted = $monthlyProjects->where('status', 'completed')->count();
            $monthlyCompletionRate = $monthlyProjects->count() > 0 ? ($monthlyCompleted / $monthlyProjects->count()) * 100 : 0;

            $completionRates[] = $monthlyCompletionRate;
        }

        // Workspace performance
        $workspacePerformance = $workspaces->map(function ($workspace) {
            $workspaceProjects = $workspace->projects;
            $totalProjects = $workspaceProjects->count();
            $completedProjects = $workspaceProjects->where('status', 'completed')->count();
            $completionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;

            return (object) [
                'workspace' => $workspace->title,
                'completion_rate' => $completionRate,
            ];
        })->values();

        // Prepare data for charts
        $workspaceLabels = $workspacePerformance->pluck('workspace')->toArray();
        $workspaceCompletionRates = $workspacePerformance->pluck('completion_rate')->toArray();

        return view('taskmanager::reports.projects.completion', compact(
            'projects',
            'workspaces',
            'users',
            'totalProjects',
            'completedProjects',
            'completionRate',
            'avgCompletionTime',
            'projectDetails',
            'months',
            'completionRates',
            'workspaceLabels',
            'workspaceCompletionRates'
        ));
    }

    /**
     * Display project timeline report
     */
    public function projectTimeline(Request $request)
    {
        // Get filter parameters
        $workspaceId = $request->get('workspace_id');
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');
        $priority = $request->get('priority');

        // Build base queries
        $projectQuery = ProjectAccess::scopeProjects(Project::with(['workspace', 'tasks']));
        $workspaceQuery = $this->visibleWorkspaces();

        // Apply filters
        if ($workspaceId) {
            $projectQuery->where('workspace_id', $workspaceId);
            $workspaceQuery->where('id', $workspaceId);
        }

        if ($userId) {
            $projectQuery->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $projectQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $projectQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            $projectQuery->where('status', $status);
        }

        if ($priority) {
            $projectQuery->where('priority', $priority);
        }

        // Get filtered data
        $projects = $projectQuery->get();
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Project Statistics
        $totalProjects = $projects->count();
        $onTimeProjects = $projects->filter(function ($project) {
            return $project->end_date && $project->actual_end_date &&
                   $project->actual_end_date <= $project->end_date;
        })->count();
        $delayedProjects = $projects->filter(function ($project) {
            return $project->end_date && $project->actual_end_date &&
                   $project->actual_end_date > $project->end_date;
        })->count();

        // Timeline statistics
        $timelineStats = $projects->filter(function ($project) {
            return $project->start_date && $project->end_date;
        })->map(function ($project) {
            $plannedDuration = $project->start_date->diffInDays($project->end_date);
            $actualDuration = $project->actual_end_date ?
                              $project->start_date->diffInDays($project->actual_end_date) :
                              ($project->status == 'completed' ? $plannedDuration : 0);
            $delayDays = $actualDuration > $plannedDuration ? $actualDuration - $plannedDuration : 0;

            return (object) [
                'project_id' => $project->id,
                'project_title' => $project->title,
                'planned_duration' => $plannedDuration,
                'actual_duration' => $actualDuration,
                'delay_days' => $delayDays,
            ];
        });

        // Project timeline details
        $projectTimelineDetails = $projects->filter(function ($project) {
            return $project->start_date && $project->end_date;
        })->map(function ($project) {
            $plannedDuration = $project->start_date->diffInDays($project->end_date);
            $actualDuration = $project->actual_end_date ?
                              $project->start_date->diffInDays($project->actual_end_date) :
                              ($project->status == 'completed' ? $plannedDuration : 0);
            $delayDays = $actualDuration > $plannedDuration ? $actualDuration - $plannedDuration : 0;
            $onTime = $delayDays == 0;

            return (object) [
                'id' => $project->id,
                'title' => $project->title,
                'workspace' => $project->workspace ? $project->workspace->title : 'غير محدد',
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'actual_end_date' => $project->actual_end_date,
                'planned_duration' => $plannedDuration,
                'actual_duration' => $actualDuration,
                'delay_days' => $delayDays,
                'on_time' => $onTime,
            ];
        })->sortBy('start_date')->values();

        // Monthly timeline trends
        $months = [];
        $onTimeRates = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->translatedFormat('F Y');

            $monthlyProjects = $projects->filter(function ($project) use ($date) {
                return $project->created_at &&
                       $project->created_at->year == $date->year &&
                       $project->created_at->month == $date->month;
            });

            $monthlyOnTime = $monthlyProjects->filter(function ($project) {
                return $project->end_date && $project->actual_end_date &&
                       $project->actual_end_date <= $project->end_date;
            })->count();

            $onTimeRate = $monthlyProjects->count() > 0 ? ($monthlyOnTime / $monthlyProjects->count()) * 100 : 0;
            $onTimeRates[] = $onTimeRate;
        }

        // Workspace timeline performance
        $workspaceTimelinePerformance = $workspaces->map(function ($workspace) {
            $workspaceProjects = $workspace->projects->filter(function ($project) {
                return $project->start_date && $project->end_date;
            });

            $totalProjects = $workspaceProjects->count();
            $onTimeProjects = $workspaceProjects->filter(function ($project) {
                return $project->end_date && $project->actual_end_date &&
                       $project->actual_end_date <= $project->end_date;
            })->count();

            $onTimeRate = $totalProjects > 0 ? ($onTimeProjects / $totalProjects) * 100 : 0;

            return (object) [
                'workspace' => $workspace->title,
                'on_time_rate' => $onTimeRate,
            ];
        })->values();

        // Prepare data for charts
        $workspaceLabels = $workspaceTimelinePerformance->pluck('workspace')->toArray();
        $workspaceOnTimeRates = $workspaceTimelinePerformance->pluck('on_time_rate')->toArray();

        return view('taskmanager::reports.projects.timeline', compact(
            'projects',
            'workspaces',
            'users',
            'totalProjects',
            'onTimeProjects',
            'delayedProjects',
            'timelineStats',
            'projectTimelineDetails',
            'months',
            'onTimeRates',
            'workspaceLabels',
            'workspaceOnTimeRates'
        ));
    }

    /**
     * Display workspace reports
     */
    public function workspaces(Request $request)
    {
        // Get filter parameters
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');

        // Build base queries
        $workspaceQuery = $this->visibleWorkspaces(['projects.users']);

        // Apply filters
        // ملاحظة: لا يوجد جدول pivot مباشر بين المستخدمين ومساحات العمل (workspace_user)؛
        // عضوية المستخدم تُشتق من مشاريعه (project_user) داخل مشاريع مساحة العمل.
        if ($userId) {
            $workspaceQuery->whereHas('projects.users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $workspaceQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $workspaceQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            // For workspaces, we'll consider active if they have active projects
            if ($status == 'active') {
                $workspaceQuery->whereHas('projects', function ($q) {
                    $q->where('status', 'active');
                });
            } elseif ($status == 'inactive') {
                $workspaceQuery->whereDoesntHave('projects', function ($q) {
                    $q->where('status', 'active');
                });
            }
        }

        // Get filtered data
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Workspace Statistics
        $totalWorkspaces = $workspaces->count();
        $activeWorkspaces = $workspaces->filter(function ($workspace) {
            return $workspace->projects->where('status', 'active')->count() > 0;
        })->count();

        // Get all projects and tasks for these workspaces
        $workspaceIds = $workspaces->pluck('id')->toArray();
        $projects = ProjectAccess::scopeProjects(Project::query())->whereIn('workspace_id', $workspaceIds)->get();
        $totalProjects = $projects->count();

        $projectIds = $projects->pluck('id')->toArray();
        $tasks = Task::whereIn('project_id', $projectIds)->get();
        $totalTasks = $tasks->count();

        // Calculate completion rate
        $completedProjects = $projects->where('status', 'completed')->count();
        $completionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;

        // Total users across all workspaces (مشتقّة من project_user عبر مشاريع هذه المساحات)
        $totalUsers = User::whereHas('projects', function ($q) use ($projectIds) {
            $q->whereIn('projects.id', $projectIds);
        })->count();

        // Workspace Status Distribution
        $workspaceStatuses = $workspaces->map(function ($workspace) {
            $hasActiveProjects = $workspace->projects->where('status', 'active')->count() > 0;

            return (object) [
                'status' => $hasActiveProjects ? 'active' : 'inactive',
                'count' => 1,
            ];
        })->groupBy('status')->map(function ($group) {
            $statusLabels = [
                'active' => 'نشط',
                'inactive' => 'غير نشط',
            ];

            return (object) [
                'status' => $statusLabels[$group->first()->status] ?? $group->first()->status,
                'count' => $group->count(),
            ];
        })->values();

        // Top Workspaces by projects and tasks
        $topWorkspaces = $workspaces->map(function ($workspace) {
            $workspaceProjects = $workspace->projects;
            $totalProjects = $workspaceProjects->count();

            $totalTasks = 0;
            $completedTasks = 0;

            foreach ($workspaceProjects as $project) {
                $projectTasks = $project->tasks;
                $totalTasks += $projectTasks->count();
                $completedTasks += $projectTasks->where('status', 'completed')->count();
            }

            $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            return (object) [
                'id' => $workspace->id,
                'title' => $workspace->title,
                'total_projects' => $totalProjects,
                'total_tasks' => $totalTasks,
                'completion_rate' => $completionRate,
            ];
        })->sortByDesc('total_projects')->take(10);

        // Top Users by workspaces, projects, and tasks
        $topUsers = $users->map(function ($user) use ($workspaces) {
            $userWorkspaces = $workspaces->filter(function ($workspace) use ($user) {
                return $workspace->projects->contains(function ($project) use ($user) {
                    return $project->users->contains($user);
                });
            });

            $totalWorkspaces = $userWorkspaces->count();

            $totalProjects = 0;
            $totalTasks = 0;

            foreach ($userWorkspaces as $workspace) {
                $workspaceProjects = $workspace->projects;
                $totalProjects += $workspaceProjects->count();

                foreach ($workspaceProjects as $project) {
                    $totalTasks += $project->tasks->count();
                }
            }

            return (object) [
                'id' => $user->id,
                'name' => $user->name,
                'total_workspaces' => $totalWorkspaces,
                'total_projects' => $totalProjects,
                'total_tasks' => $totalTasks,
            ];
        })->sortByDesc('total_workspaces')->take(10);

        // Monthly workspace trends
        $months = [];
        $monthlyWorkspaces = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->translatedFormat('F Y');

            $monthlyWorkspaces[] = $workspaces->filter(function ($workspace) use ($date) {
                return $workspace->created_at &&
                       $workspace->created_at->year == $date->year &&
                       $workspace->created_at->month == $date->month;
            })->count();
        }

        // Workspace distribution for projects and tasks
        $workspaceProjectDistribution = $workspaces->map(function ($workspace) {
            return (object) [
                'workspace' => $workspace->title,
                'projects' => $workspace->projects->count(),
            ];
        })->values();

        $workspaceTaskDistribution = $workspaces->map(function ($workspace) {
            $totalTasks = 0;
            foreach ($workspace->projects as $project) {
                $totalTasks += $project->tasks->count();
            }

            return (object) [
                'workspace' => $workspace->title,
                'tasks' => $totalTasks,
            ];
        })->values();

        // Prepare data for charts
        $workspaceStatusLabels = $workspaceStatuses->pluck('status')->toArray();
        $workspaceStatusCounts = $workspaceStatuses->pluck('count')->toArray();

        $workspaceLabels = $workspaceProjectDistribution->pluck('workspace')->toArray();
        $workspaceProjectCounts = $workspaceProjectDistribution->pluck('projects')->toArray();
        $workspaceTaskCounts = $workspaceTaskDistribution->pluck('tasks')->toArray();

        return view('taskmanager::reports.workspaces.summary', compact(
            'workspaces',
            'users',
            'totalWorkspaces',
            'activeWorkspaces',
            'totalProjects',
            'totalTasks',
            'completionRate',
            'totalUsers',
            'workspaceStatuses',
            'topWorkspaces',
            'topUsers',
            'months',
            'monthlyWorkspaces',
            'workspaceLabels',
            'workspaceProjectCounts',
            'workspaceTaskCounts',
            'workspaceStatusLabels',
            'workspaceStatusCounts'
        ));
    }

    /**
     * Display workspace performance report
     */
    public function workspacePerformance(Request $request)
    {
        // Get filter parameters
        $userId = $request->get('user_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');

        // Build base queries
        // ملاحظة: لا يوجد جدول pivot مباشر بين المستخدمين ومساحات العمل (workspace_user)؛
        // عضوية المستخدم تُشتق من مشاريعه (project_user) داخل مشاريع مساحة العمل.
        $workspaceQuery = $this->visibleWorkspaces(['projects.users']);

        // Apply filters
        if ($userId) {
            $workspaceQuery->whereHas('projects.users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        if ($fromDate) {
            $workspaceQuery->where('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $workspaceQuery->where('created_at', '<=', $toDate);
        }

        if ($status) {
            // For workspaces, we'll consider active if they have active projects
            if ($status == 'active') {
                $workspaceQuery->whereHas('projects', function ($q) {
                    $q->where('status', 'active');
                });
            } elseif ($status == 'inactive') {
                $workspaceQuery->whereDoesntHave('projects', function ($q) {
                    $q->where('status', 'active');
                });
            }
        }

        // Get filtered data
        $workspaces = $workspaceQuery->get();
        $users = User::all();

        // Workspace Statistics
        $totalWorkspaces = $workspaces->count();
        $activeWorkspaces = $workspaces->filter(function ($workspace) {
            return $workspace->projects->where('status', 'active')->count() > 0;
        })->count();

        // Workspace details with performance metrics
        $workspaceDetails = $workspaces->map(function ($workspace) {
            $workspaceProjects = $workspace->projects;
            $totalProjects = $workspaceProjects->count();
            $completedProjects = $workspaceProjects->where('status', 'completed')->count();
            $activeProjects = $workspaceProjects->where('status', 'active')->count();
            $overdueProjects = $workspaceProjects->filter(function ($project) {
                return $project->end_date && $project->end_date < now() && $project->status != 'completed';
            })->count();

            $userCount = $workspaceProjects->flatMap(function ($project) {
                return $project->users;
            })->unique('id')->count();

            // Calculate performance score (weighted formula)
            $completionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;
            $onTimeRate = $totalProjects > 0 ? (($totalProjects - $overdueProjects) / $totalProjects) * 100 : 0;
            $activityRate = $totalProjects > 0 ? ($activeProjects / $totalProjects) * 100 : 0;

            // Weighted performance score: 40% completion, 30% on-time, 20% activity, 10% user engagement
            $userEngagementScore = min(100, $userCount * 10); // Simple user engagement metric
            $performanceScore = (0.4 * $completionRate) + (0.3 * $onTimeRate) + (0.2 * $activityRate) + (0.1 * $userEngagementScore);

            return (object) [
                'id' => $workspace->id,
                'title' => $workspace->title,
                'total_projects' => $totalProjects,
                'completed_projects' => $completedProjects,
                'active_projects' => $activeProjects,
                'overdue_projects' => $overdueProjects,
                'user_count' => $userCount,
                'completion_rate' => $completionRate,
                'performance_score' => $performanceScore,
            ];
        })->sortByDesc('performance_score')->values();

        // Best and worst performance scores
        $bestPerformanceScore = $workspaceDetails->first() ? $workspaceDetails->first()->performance_score : 0;
        $worstPerformanceScore = $workspaceDetails->last() ? $workspaceDetails->last()->performance_score : 0;

        // Prepare data for charts
        $workspaceLabels = $workspaceDetails->pluck('title')->toArray();
        $performanceScores = $workspaceDetails->pluck('performance_score')->toArray();

        // Ratios for radar chart
        $completedProjectRatios = $workspaceDetails->map(function ($workspace) {
            return $workspace->total_projects > 0 ? ($workspace->completed_projects / $workspace->total_projects) * 100 : 0;
        })->toArray();

        $activeProjectRatios = $workspaceDetails->map(function ($workspace) {
            return $workspace->total_projects > 0 ? ($workspace->active_projects / $workspace->total_projects) * 100 : 0;
        })->toArray();

        $overdueProjectRatios = $workspaceDetails->map(function ($workspace) {
            return $workspace->total_projects > 0 ? ($workspace->overdue_projects / $workspace->total_projects) * 100 : 0;
        })->toArray();

        return view('taskmanager::reports.workspaces.performance', compact(
            'workspaces',
            'users',
            'totalWorkspaces',
            'activeWorkspaces',
            'bestPerformanceScore',
            'worstPerformanceScore',
            'workspaceDetails',
            'workspaceLabels',
            'performanceScores',
            'completedProjectRatios',
            'activeProjectRatios',
            'overdueProjectRatios'
        ));
    }

    /**
     * تصدير المهامّ بفلاتر التقرير إلى CSV.
     *
     * كان المسار والصلاحية قائمَين والدالّة تعيد جملةً ثابتة. CSV لا xlsx:
     * يفتحه Excel مباشرةً (بعلامة BOM للعربية) بلا مكتبةٍ تُحمَّل لأجله،
     * ويُبثّ صفّاً صفّاً فلا يُحمَّل التقرير كلّه في الذاكرة.
     */
    public function export(Request $request)
    {
        $filters = $request->validate([
            'project_id' => 'nullable|integer',
            'workspace_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(TaskVocabulary::statusIds())],
            'priority' => ['nullable', Rule::in(TaskVocabulary::priorityIds())],
            'task_type' => ['nullable', Rule::in(TaskVocabulary::typeIds())],
        ]);

        $query = ProjectAccess::scopeByProject(Task::with(['project:id,title,workspace_id', 'project.workspace:id,title', 'assignedUsers:id,name', 'sprint:id,name']))
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['workspace_id'] ?? null, fn ($q, $id) => $q->whereHas('project', fn ($p) => $p->where('workspace_id', $id)))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->whereHas('assignedUsers', fn ($u) => $u->where('users.id', $id)))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['priority'] ?? null, fn ($q, $value) => $q->where('priority', $value))
            ->when($filters['task_type'] ?? null, fn ($q, $value) => $q->where('type', $value))
            ->orderBy('id');

        OperationLog::record('exported', 'TaskManager.Reports', null, 'صُدّر تقرير المهامّ إلى CSV');

        // خانةٌ تبدأ بـ = أو + أو - أو @ يقرؤها Excel معادلةً — وعنوان
        // المهمّة يكتبه أيّ عضو
        $safe = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($query, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['#', 'العنوان', 'النوع', 'الحالة', 'الأولوية', 'المشروع', 'مساحة العمل', 'السبرنت', 'المكلَّفون', 'البداية', 'الاستحقاق', 'الإنجاز', 'الساعات المقدّرة']);

            $query->chunk(500, function ($tasks) use ($out, $safe) {
                foreach ($tasks as $task) {
                    fputcsv($out, array_map($safe, [
                        $task->id,
                        $task->title,
                        $task->type_label,
                        $task->status_label,
                        $task->priority_label,
                        $task->project?->title,
                        $task->project?->workspace?->title,
                        $task->sprint?->name,
                        $task->assignedUsers->pluck('name')->implode('، '),
                        $task->start_date,
                        $task->due_date,
                        $task->completed_date,
                        $task->estimated_hours,
                    ]));
                }
            });

            fclose($out);
        }, 'tasks-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * مساحات العمل المرئية، ومعها مشاريعها المرئية وحدها.
     *
     * المشاريع تُحمَّل مقيَّدةً هنا لا عند الحاجة: التقارير تقرأ
     * `$workspace->projects` في حلقاتها، والتحميل المتأخّر يجلبها كلّها —
     * فيقرأ عضوُ مشروعٍ واحد أرقام بقيّة مشاريع المساحة.
     *
     * @param  array<int, string>  $with  علاقاتٌ تحت `projects`
     */
    private function visibleWorkspaces(array $with = [])
    {
        return ProjectAccess::scopeWorkspaces(Workspace::query())->with([
            'projects' => fn ($projects) => ProjectAccess::scopeProjects($projects->getQuery()),
            // التقارير تعدّ مهامّ كل مشروعٍ في حلقاتها — فتُحمَّل معه لا استعلاماً لكل مشروع
            'projects.tasks',
            ...$with,
        ]);
    }
}
