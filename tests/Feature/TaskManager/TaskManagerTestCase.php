<?php

namespace Tests\Feature\TaskManager;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * أساس اختبارات إدارة المهام: مستخدمون بأدوارٍ حقيقية ومشاريع بعضويّاتها.
 *
 * الصلاحية تُمنح للدور لا تُتجاوز: الاختبارات تقيس حارسَين معاً —
 * `permission.role` (هل يملك الفعل) و`ProjectAccess` (على أيّ مشروع) —
 * وتعطيل الأول يُخفي تعارضهما.
 */
abstract class TaskManagerTestCase extends TestCase
{
    use RefreshDatabase;

    /** كل صلاحيات الوحدة عدا «كل المشاريع». */
    protected const MEMBER_PERMISSIONS = [
        'tasks.workspaces.view', 'tasks.workspaces.show', 'tasks.workspaces.create', 'tasks.workspaces.edit', 'tasks.workspaces.delete',
        'tasks.projects.view', 'tasks.projects.show', 'tasks.projects.create', 'tasks.projects.edit', 'tasks.projects.delete', 'tasks.projects.invite',
        'tasks.board.view', 'tasks.board.dashboard', 'tasks.board.backlog',
        'tasks.items.view', 'tasks.items.show', 'tasks.items.create', 'tasks.items.create_structure', 'tasks.items.edit',
        'tasks.items.delete', 'tasks.items.update_status', 'tasks.items.reorder',
        'tasks.comments.view', 'tasks.comments.create', 'tasks.comments.edit_own', 'tasks.comments.delete_own',
        'tasks.sprints.view', 'tasks.sprints.show', 'tasks.sprints.create', 'tasks.sprints.edit', 'tasks.sprints.delete',
        'tasks.sprints.manage_tasks', 'tasks.sprints.change_status', 'tasks.sprints.close', 'tasks.sprints.reopen',
        'tasks.reports.view', 'tasks.reports.performance', 'tasks.reports.export',
        'tasks.settings.view', 'tasks.settings.edit', 'tasks.settings.global.view', 'tasks.settings.global.edit',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // حرّاس عامّة لا تخصّ ما يُقاس هنا (الصيانة، الاشتراك، تحديث النظام،
        // الفرع، تفعيل التطبيق، الباقة…) — وكلٌّ منها يحوّل الطلب قبل أن
        // يبلغ حارسَي الوحدة: `permission.role` و`ProjectAccess`
        $aliases = app('router')->getMiddleware();

        $this->withoutMiddleware(array_filter([
            \Modules\Installer\Http\Middleware\EnsureInstalled::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\EnsureWebAccessAllowed::class,
            \App\Http\Middleware\CheckIncompleteVisits::class,
            \App\Http\Middleware\EnsureModuleEnabled::class,
            \App\Http\Middleware\ClientAccessMiddleware::class,
            \App\Http\Middleware\ReturnToVisitWorkspace::class,
            \App\Http\Middleware\LockToOpenVisit::class,
            \App\Http\Middleware\TrackSessionActivity::class,
            \App\Http\Middleware\EnsureSubscription::class,
            \App\Http\Middleware\CheckSystemUpdates::class,
            \App\Http\Middleware\TickSchedulerFromRequests::class,
            // المسارات تُسجَّل عند الإقلاع ببادئة اللغة من عنوان الطلب — ولا طلب
            // عند إقلاع الاختبار، فتُسجَّل بلا بادئة؛ وإعادة التوجيه إلى
            // `/ar/…` تقود إلى 404
            $aliases['localeSessionRedirect'] ?? null,
            $aliases['localizationRedirect'] ?? null,
            $aliases['check.branch'] ?? null,
            $aliases['plan'] ?? null,
        ]));
    }

    /** @param  array<int, string>  $permissions */
    protected function userWith(array $permissions, ?string $roleName = null): User
    {
        $role = Role::create(['name' => $roleName ?? 'role-'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh();
    }

    protected function member(): User
    {
        return $this->userWith(self::MEMBER_PERMISSIONS);
    }

    protected function admin(): User
    {
        return $this->userWith([...self::MEMBER_PERMISSIONS, 'tasks.projects.view_all', 'tasks.comments.edit_any', 'tasks.comments.delete_any']);
    }

    protected function project(User $owner, array $attributes = []): Project
    {
        $workspace = Workspace::create(['title' => 'مساحة '.uniqid(), 'admin_id' => $owner->id, 'is_primary' => false]);

        $project = Project::create($attributes + [
            'workspace_id' => $workspace->id,
            'title' => 'مشروع '.uniqid(),
            'status' => 'active',
            'priority' => 'medium',
            'management_type' => 'scrum',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_by' => $owner->id,
        ]);

        $this->join($project, $owner, 'manager');

        return $project;
    }

    protected function join(Project $project, User $user, string $role = 'member', string $status = 'active', array $extra = []): void
    {
        DB::table('project_user')->insert($extra + [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function task(Project $project, array $attributes = []): Task
    {
        return Task::create($attributes + [
            'project_id' => $project->id,
            'type' => 'user_story',
            'title' => 'مهمّة '.uniqid(),
            'status' => 'not_started',
            'priority' => 'medium',
            'start_date' => '2026-01-01',
            'due_date' => '2026-01-08',
            'created_by' => $project->created_by,
        ]);
    }

    protected function sprint(Project $project, array $attributes = []): Sprint
    {
        return Sprint::create($attributes + [
            'project_id' => $project->id,
            'name' => 'سبرنت '.uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-14',
            'status' => 'planned',
        ]);
    }
}
