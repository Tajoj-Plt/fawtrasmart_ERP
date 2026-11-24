<?php

use App\Constants\planFeatures\TaskManagerPlanFeatures;
use App\Http\Middleware\EnsureProjectSettings;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\TaskManager\Http\Controllers\CommentController;
use Modules\TaskManager\Http\Controllers\ProjectController;
use Modules\TaskManager\Http\Controllers\ReportsController;
use Modules\TaskManager\Http\Controllers\Settings\GlobalSettingsController;
use Modules\TaskManager\Http\Controllers\SprintController;
use Modules\TaskManager\Http\Controllers\TaskController;
use Modules\TaskManager\Http\Controllers\TaskManagerController;
use Modules\TaskManager\Http\Controllers\TaskSettingsController;
use Modules\TaskManager\Http\Controllers\WorkspaceController;

/*
|--------------------------------------------------------------------------
| ثلاث طبقات حراسة لا طبقة — على نسق Modules/Stock/routes/web.php
|--------------------------------------------------------------------------
|
| كانت الوحدة كلّها بلا حارسٍ غير `auth`: أيّ مستخدمٍ مسجَّل يحذف مشروعاً،
| ويقفل سبرنتاً، ويقرأ تقرير أداء الفريق كلّه. والأسئلة الثلاثة مختلفة ولا
| يُغني أحدها عن الآخر:
|
|   `module:tasks`          هل فتحت المنشأة هذه الوحدة من شاشة التطبيقات؟
|   `plan:tasks_*`          هل تشملها باقة فوترة؟
|   `permission.role:tasks.*` هل يملك هذا المستخدم هذا الفعل؟
|
| وموضع الأوّلَين على المجموعة لا على كل مسار: نسيانُ واحدٍ من مئة مسار
| يفتحه كلّه، والمنسيّ عادةً مسار AJAX لا شاشة فلا يُلاحَظ غيابُ حارسه.
|
| **تنبيه على `plan:`**: مفاتيح [TaskManagerPlanFeatures] ليست في كتالوج
| فوترة بعد، و[\App\Services\Fawtra\SubscriptionGate::allows()] تُمرّر
| المجهول. فهذا السطر أنبوبٌ ممدود لا حارسٌ عامل — يبدأ العمل يوم تصل
| المفاتيح من فوترة، ولا يمنع شيئاً اليوم. اقرأ الملف نفسه.
*/

Route::middleware(['auth', EnsureProjectSettings::class])->group(function () {
    Route::group(
        [
            'prefix' => LaravelLocalization::setLocale(),
            'middleware' => [
                'localeSessionRedirect',
                'localizationRedirect',
                'localeViewPath',
                'check.branch',
                'module:tasks',
                'plan:'.implode('|', TaskManagerPlanFeatures::all()),
            ],
        ],
        function () {
            // ═══════════════════════ مساحات العمل ═══════════════════════
            Route::prefix('workspaces')->middleware('plan:'.TaskManagerPlanFeatures::PROJECTS)->group(function () {
                Route::get('/', [WorkspaceController::class, 'index'])->name('workspaces.index')
                    ->middleware('permission.role:tasks.workspaces.view');
                Route::get('/create', [WorkspaceController::class, 'create'])->name('workspaces.create')
                    ->middleware('permission.role:tasks.workspaces.create');
                Route::post('/', [WorkspaceController::class, 'store'])->name('workspaces.store')
                    ->middleware('permission.role:tasks.workspaces.create');
                Route::get('/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show')
                    ->middleware('permission.role:tasks.workspaces.show');
                Route::get('/{workspace}/edit', [WorkspaceController::class, 'edit'])->name('workspaces.edit')
                    ->middleware('permission.role:tasks.workspaces.edit');
                Route::put('/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update')
                    ->middleware('permission.role:tasks.workspaces.edit');
                Route::delete('/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy')
                    ->middleware('permission.role:tasks.workspaces.delete');
            });

            // ═══════════════════════ المشاريع ═══════════════════════
            Route::middleware('plan:'.TaskManagerPlanFeatures::PROJECTS)->group(function () {
                Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index')
                    ->middleware('permission.role:tasks.projects.view');
                Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create')
                    ->middleware('permission.role:tasks.projects.create');
                Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store')
                    ->middleware('permission.role:tasks.projects.create');
                Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show')
                    ->middleware('permission.role:tasks.projects.show');
                Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit')
                    ->middleware('permission.role:tasks.projects.edit');
                Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update')
                    ->middleware('permission.role:tasks.projects.edit');
                Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy')
                    ->middleware('permission.role:tasks.projects.delete');

                Route::post('/projects/{project}/invite', [ProjectController::class, 'inviteUser'])->name('projects.invite')
                    ->middleware('permission.role:tasks.projects.invite');
            });

            /*
                الردّ على الدعوة بلا صلاحية — عمداً.

                المدعوّ يفتح رابطاً في بريده ليقبل أو يرفض دعوةً تخصّه هو،
                والرمز في الرابط هو إثباتُه. واشتراطُ `tasks.projects.*`
                هنا يقفل الباب على كل مدعوٍّ لم تُمنح له صلاحيات الوحدة
                بعد — وهم أغلب المدعوّين، إذ الدعوة سابقةٌ للصلاحية لا
                تابعةٌ لها.
            */
            Route::get('/invitations/{token}/accept', [ProjectController::class, 'acceptInvitation'])->name('invitations.accept');
            Route::get('/invitations/{token}/decline', [ProjectController::class, 'declineInvitation'])->name('invitations.decline');

            // شاشات المشروع الواحد — لوحته وقائمة انتظاره وسبرنتاته
            Route::prefix('projects/{project}')->name('projects.')->group(function () {
                Route::get('/board', [TaskManagerController::class, 'index'])->name('board')
                    ->middleware(['permission.role:tasks.board.view', 'plan:'.TaskManagerPlanFeatures::BOARD]);
                Route::get('/dashboard', [TaskManagerController::class, 'dashboard'])->name('task-dashboard')
                    ->middleware(['permission.role:tasks.board.dashboard', 'plan:'.TaskManagerPlanFeatures::BOARD]);
                Route::get('/backlog', [TaskController::class, 'index'])->name('backlog')
                    ->middleware(['permission.role:tasks.board.backlog', 'plan:'.TaskManagerPlanFeatures::BOARD]);
                Route::get('/sprints', [SprintController::class, 'index'])->name('sprints')
                    ->middleware(['permission.role:tasks.sprints.view', 'plan:'.TaskManagerPlanFeatures::SPRINTS]);
            });

            // ═══════════════════════ اللوحة العامّة ═══════════════════════
            Route::middleware('plan:'.TaskManagerPlanFeatures::BOARD)->group(function () {
                Route::get('/task-manager', [TaskManagerController::class, 'index'])->name('tasks.manager')
                    ->middleware('permission.role:tasks.board.view');
                Route::get('/task-dashboard', [TaskManagerController::class, 'dashboard'])->name('tasks.dashboard')
                    ->middleware('permission.role:tasks.board.dashboard');
            });

            // ═══════════════════════ التقارير ═══════════════════════
            Route::prefix('task-reports')
                ->middleware(['permission.role:tasks.reports.view', 'plan:'.TaskManagerPlanFeatures::REPORTS])
                ->group(function () {
                    Route::get('/', [ReportsController::class, 'index'])->name('tasks.reports');
                    Route::get('/tasks', [ReportsController::class, 'tasks'])->name('tasks.reports.tasks');
                    Route::get('/task-types', [ReportsController::class, 'taskTypes'])->name('tasks.reports.task-types');
                    Route::get('/task-status', [ReportsController::class, 'taskStatus'])->name('tasks.reports.status');
                    Route::get('/task-priority', [ReportsController::class, 'taskPriority'])->name('tasks.reports.priority');
                    Route::get('/projects', [ReportsController::class, 'projects'])->name('tasks.reports.projects');
                    Route::get('/project-completion', [ReportsController::class, 'projectCompletion'])->name('tasks.reports.project-completion');
                    Route::get('/project-timeline', [ReportsController::class, 'projectTimeline'])->name('tasks.reports.project-timeline');
                    Route::get('/workspaces', [ReportsController::class, 'workspaces'])->name('tasks.reports.workspaces');

                    // أداء الأفراد داخل الفريق — صلاحيةٌ مستقلّة عن بقيّة
                    // التقارير: من يقرأ إنجاز المشروع ليس بالضرورة من
                    // يُسمح له بقياس كل موظّفٍ على حدة
                    Route::get('/workspace-performance', [ReportsController::class, 'workspacePerformance'])
                        ->name('tasks.reports.workspace-performance')
                        ->middleware('permission.role:tasks.reports.performance');

                    Route::post('/export', [ReportsController::class, 'export'])->name('tasks.reports.export')
                        ->middleware('permission.role:tasks.reports.export');
                });

            // ═══════════════════════ المهام ═══════════════════════
            Route::prefix('tasks')->middleware('plan:'.TaskManagerPlanFeatures::BOARD)->group(function () {
                Route::get('/', [TaskController::class, 'index'])->name('tasks.index')
                    ->middleware('permission.role:tasks.items.view');

                /*
                    شاشةُ الإنشاء وإجراؤه يخدمان ستة أنواع بصلاحيتين:
                    الهيكل (مكوّن/إبيك/قصّة) والعمل اليومي (مهمة/فرعية/خطأ).
                    فالحارس هنا قائمةٌ بديلة — تكفي إحداهما لفتح الشاشة —
                    والتمييزُ الفعلي داخل `TaskController::store()` وفق
                    `type` الوارد. راجع [\App\Constants\permissions\TaskManagerPermissions::createPermissionByType()].
                */
                Route::get('/create', [TaskController::class, 'create'])->name('tasks.create')
                    ->middleware('permission.role:tasks.items.create|tasks.items.create_structure');
                Route::post('/', [TaskController::class, 'store'])->name('tasks.store')
                    ->middleware('permission.role:tasks.items.create|tasks.items.create_structure');

                // مسارات القراءة المساعِدة — كلّها تغذّي قوائم الاختيار في
                // الشاشات، فحارسها حارسُ العرض
                Route::middleware('permission.role:tasks.items.view')->group(function () {
                    Route::get('/parent-tasks/{type}', [TaskController::class, 'getParentTasks'])->name('tasks.parent-tasks');
                    Route::get('/plain-tasks/{projectId?}', [TaskController::class, 'getTasks'])->name('tasks.plain-tasks');
                    Route::get('/components', [TaskController::class, 'getComponents'])->name('tasks.components');
                    Route::get('/epics/{componentId?}', [TaskController::class, 'getEpics'])->name('tasks.epics');
                    Route::get('/user-stories/{epicId?}', [TaskController::class, 'getUserStories'])->name('tasks.user-stories');
                    Route::get('/subtasks/{userStoryId?}', [TaskController::class, 'getSubTasks'])->name('tasks.subtasks');
                    Route::get('/bugs/{componentId?}', [TaskController::class, 'getBugs'])->name('tasks.bugs');
                    Route::get('/filter', [TaskController::class, 'getFilteredTasks'])->name('tasks.filter');
                    Route::get('/manager-data', [TaskController::class, 'getTaskManagerData'])->name('tasks.manager-data');
                });

                /*
                    تجديد رمز CSRF بلا صلاحية — عمداً.

                    الجلسة التي انتهى رمزُها تحتاج الرمزَ الجديد لتُرسل أي
                    طلب، ومنها طلبُ من لا يملك صلاحية المهام أصلاً. وربطُه
                    بصلاحيةٍ يجعل انتهاءَ الرمز عطلاً صامتاً لا مخرج منه
                    غير إعادة تحميل الصفحة.
                */
                Route::get('/refresh-csrf', function () {
                    return response()->json(['token' => csrf_token()]);
                })->name('tasks.refresh-csrf');

                Route::post('/{taskId}/assign-sprint-with-inheritance', [TaskController::class, 'assignSprintWithInheritance'])
                    ->name('tasks.assign-sprint-with-inheritance')
                    ->middleware(['permission.role:tasks.sprints.manage_tasks', 'plan:'.TaskManagerPlanFeatures::SPRINTS]);

                Route::patch('/{taskId}/status', [TaskController::class, 'updateStatus'])->name('tasks.update-status')
                    ->middleware('permission.role:tasks.items.update_status');
                Route::patch('/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder')
                    ->middleware('permission.role:tasks.items.reorder');

                Route::get('/{taskId}/comments', [CommentController::class, 'getTaskComments'])->name('tasks.comments')
                    ->middleware('permission.role:tasks.comments.view');

                // المسارات العامّة بعد الخاصّة دائماً
                Route::get('/{taskId}', [TaskController::class, 'show'])->name('tasks.show')
                    ->middleware('permission.role:tasks.items.show');
                Route::put('/{taskId}', [TaskController::class, 'update'])->name('tasks.update')
                    ->middleware('permission.role:tasks.items.edit');
                Route::delete('/{taskId}', [TaskController::class, 'destroy'])->name('tasks.destroy')
                    ->middleware('permission.role:tasks.items.delete');
            });

            // ═══════════════════════ التعليقات ═══════════════════════
            /*
                `edit_own|edit_any` قائمةٌ بديلة، والتمييز داخل المتحكّم:
                من يملك `edit_own` وحدها يمرّ من الحارس ثم يُردّ في
                `CommentController::update()` إن كان التعليق لغيره.
            */
            Route::prefix('comments')->middleware('plan:'.TaskManagerPlanFeatures::BOARD)->group(function () {
                Route::post('/', [CommentController::class, 'store'])->name('comments.store')
                    ->middleware('permission.role:tasks.comments.create');
                Route::put('/{id}', [CommentController::class, 'update'])->name('comments.update')
                    ->middleware('permission.role:tasks.comments.edit_own|tasks.comments.edit_any');
                Route::delete('/{id}', [CommentController::class, 'destroy'])->name('comments.destroy')
                    ->middleware('permission.role:tasks.comments.delete_own|tasks.comments.delete_any');
            });

            // ═══════════════════════ السبرنتات ═══════════════════════
            Route::prefix('sprints')->middleware('plan:'.TaskManagerPlanFeatures::SPRINTS)->group(function () {
                Route::get('/', [SprintController::class, 'index'])->name('sprints.index')
                    ->middleware('permission.role:tasks.sprints.view');
                Route::get('/create', [SprintController::class, 'create'])->name('sprints.create')
                    ->middleware('permission.role:tasks.sprints.create');
                Route::post('/', [SprintController::class, 'store'])->name('sprints.store')
                    ->middleware('permission.role:tasks.sprints.create');
                Route::get('/{id}', [SprintController::class, 'show'])->name('sprints.show')
                    ->middleware('permission.role:tasks.sprints.show');
                Route::get('/{id}/edit', [SprintController::class, 'edit'])->name('sprints.edit')
                    ->middleware('permission.role:tasks.sprints.edit');
                Route::put('/{id}', [SprintController::class, 'update'])->name('sprints.update')
                    ->middleware('permission.role:tasks.sprints.edit');
                Route::delete('/{id}', [SprintController::class, 'destroy'])->name('sprints.destroy')
                    ->middleware('permission.role:tasks.sprints.delete');

                Route::post('/{sprintId}/tasks', [SprintController::class, 'addTask'])->name('sprints.add-task')
                    ->middleware('permission.role:tasks.sprints.manage_tasks');
                Route::delete('/{sprintId}/tasks/{taskId}', [SprintController::class, 'removeTask'])->name('sprints.remove-task')
                    ->middleware('permission.role:tasks.sprints.manage_tasks');
                Route::get('/{sprintId}/tasks', [SprintController::class, 'getSprintTasks'])->name('sprints.get-tasks')
                    ->middleware('permission.role:tasks.sprints.view');
                Route::get('/{sprintId}/user-stories', [SprintController::class, 'getUserStoriesForSprint'])->name('sprints.get-user-stories')
                    ->middleware('permission.role:tasks.sprints.view');

                // تغيير حالة مهمّةٍ من داخل السبرنت هو نفسه تغييرُ حالة
                // مهمّة — فصلاحيتُه صلاحيةُ المهام لا صلاحيةُ السبرنت
                Route::patch('/{sprintId}/tasks/{taskId}/status', [SprintController::class, 'updateTaskStatus'])->name('sprints.update-task-status')
                    ->middleware('permission.role:tasks.items.update_status');

                Route::patch('/{id}/status', [SprintController::class, 'updateStatus'])->name('sprints.update-status')
                    ->middleware('permission.role:tasks.sprints.change_status');
                Route::patch('/{id}/close', [SprintController::class, 'closeSprint'])->name('sprints.close')
                    ->middleware('permission.role:tasks.sprints.close');
                Route::patch('/{id}/reopen', [SprintController::class, 'reopenSprint'])->name('sprints.reopen')
                    ->middleware('permission.role:tasks.sprints.reopen');
            });

            // ═══════════════ إعدادات المشروع الواحد ═══════════════
            /*
                الاسم `taskmanager.settings.*` لا `settings.*`: البادئة
                الأخيرة مشغولةٌ في الإعدادات العامّة (`settings.users.*`،
                `settings.roles.*`)، وكان `TaskSettingsController::store()`
                يعيد التوجيه إلى `taskmanager.settings.index` — اسمٌ لا وجود
                له، فينتهي الحفظُ الناجح باستثناء `RouteNotFoundException`.
            */
            Route::prefix('projects/{projectId}/settings')->name('taskmanager.settings.')->group(function () {
                Route::get('/', [TaskSettingsController::class, 'index'])->name('index')
                    ->middleware('permission.role:tasks.settings.view');
                Route::post('/', [TaskSettingsController::class, 'store'])->name('store')
                    ->middleware('permission.role:tasks.settings.edit');

                Route::get('/team-config', [TaskSettingsController::class, 'getTeamConfig'])->name('team-config')
                    ->middleware('permission.role:tasks.settings.view');
                Route::put('/team-config', [TaskSettingsController::class, 'updateTeamConfig'])->name('update-team-config')
                    ->middleware('permission.role:tasks.settings.edit');

                Route::get('/sprint-config', [TaskSettingsController::class, 'getSprintConfig'])->name('sprint-config')
                    ->middleware('permission.role:tasks.settings.view');
                Route::put('/sprint-config', [TaskSettingsController::class, 'updateSprintConfig'])->name('update-sprint-config')
                    ->middleware('permission.role:tasks.settings.edit');

                Route::get('/notifications', [TaskSettingsController::class, 'getNotificationConfig'])->name('notifications')
                    ->middleware('permission.role:tasks.settings.view');
                Route::put('/notifications', [TaskSettingsController::class, 'updateNotificationConfig'])->name('update-notifications')
                    ->middleware('permission.role:tasks.settings.edit');
            });

            // ═══════════════ الإعدادات العامّة للوحدة ═══════════════
            /*
                شاشة الوحدة في لوحة إعدادات النظام (`SittingAccount/dashboard`).
                بندُها مسجَّلٌ في [\App\Services\Settings\SettingsNavigation]،
                فيظهر في اللوحة والشريط الجانبي معاً أو لا يظهر في أيّهما.
            */
            Route::prefix('task-settings')->name('taskmanager.global-settings.')->group(function () {
                Route::get('/', [GlobalSettingsController::class, 'index'])->name('index')
                    ->middleware('permission.role:tasks.settings.global.view');
                Route::post('/statuses', [GlobalSettingsController::class, 'storeStatus'])->name('statuses.store')
                    ->middleware('permission.role:tasks.settings.global.edit');
                Route::post('/types', [GlobalSettingsController::class, 'storeType'])->name('types.store')
                    ->middleware('permission.role:tasks.settings.global.edit');
                Route::post('/priorities', [GlobalSettingsController::class, 'storePriority'])->name('priorities.store')
                    ->middleware('permission.role:tasks.settings.global.edit');
                Route::delete('/{group}/{id}', [GlobalSettingsController::class, 'destroy'])->name('destroy')
                    ->middleware('permission.role:tasks.settings.global.edit');
                Route::put('/notifications', [GlobalSettingsController::class, 'updateNotifications'])->name('notifications.update')
                    ->middleware('permission.role:tasks.settings.global.edit');
            });
        }
    );
});
