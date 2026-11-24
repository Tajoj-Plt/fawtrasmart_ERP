<?php

namespace Modules\TaskManager\Services;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * من يرى أيّ مشروع، ومن يكتب فيه، ومن يديره — في موضعٍ واحد.
 *
 * صلاحيات `tasks.*` تجيب «هل يملك المستخدم هذا الفعل في النظام»، ولا
 * تجيب «على أيّ مشروع». وكانت الوحدة تكتفي بالأولى: من يملك
 * `tasks.items.view` يقرأ مهامّ كل المشاريع ويعدّلها، وجدول `project_user`
 * ودور `viewer` فيه لا يُفحصان في موضعٍ واحد.
 *
 * القاعدة هنا:
 *  - من يملك [self::VIEW_ALL] يرى كل شيء (المدير ومن فوقه).
 *  - غيرُه يرى ما أنشأه وما هو عضوٌ نشطٌ فيه.
 *  - `viewer` يقرأ ولا يكتب.
 *  - الإدارة (الدعوة، الإعدادات، الحذف) لمنشئ المشروع ومديريه.
 *
 * النتائج تُحفظ على الطلب الجاري لا في خاصّيةٍ ساكنة: الساكنة تعيش بين
 * الاختبارات وبين طلبات العامل الطويل العمر، فتُجيب بعضويةٍ قديمة.
 */
class ProjectAccess
{
    public const VIEW_ALL = 'tasks.projects.view_all';

    private const REQUEST_KEY = 'taskmanager.project_access';

    public static function seesAll(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return self::remember("all.{$user->id}", fn () => $user->hasRolePermission(self::VIEW_ALL));
    }

    /**
     * أدوار المستخدم في مشاريعه: `[project_id => role]`. المنشئ مديرٌ دائماً
     * ولو غاب صفُّه من `project_user` (مشاريع أُنشئت قبل إدراج المنشئ فيه).
     *
     * @return array<int, string>
     */
    public static function roles(User $user): array
    {
        return self::remember("roles.{$user->id}", function () use ($user) {
            $roles = DB::table('project_user')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('project_id')
                ->pluck('role', 'project_id')
                ->map(fn ($role) => (string) $role)
                ->all();

            foreach (Project::where('created_by', $user->id)->pluck('id') as $projectId) {
                $roles[(int) $projectId] = 'manager';
            }

            return $roles;
        });
    }

    /**
     * معرّفات المشاريع المرئية — أو `null` لمن يرى الكل (فلا يُبنى
     * `whereIn` على آلاف المعرّفات بلا داعٍ).
     *
     * @return array<int, int>|null
     */
    public static function projectIds(?User $user): ?array
    {
        if (! $user) {
            return [];
        }

        return self::seesAll($user) ? null : array_keys(self::roles($user));
    }

    /** تقييد استعلامٍ على `projects` نفسها. */
    public static function scopeProjects(Builder $query, ?User $user = null): Builder
    {
        $ids = self::projectIds($user ?? auth()->user());

        return $ids === null ? $query : $query->whereIn($query->getModel()->getTable().'.id', $ids);
    }

    /**
     * تقييد استعلامٍ على جدولٍ يحمل `project_id` (المهام والسبرنتات).
     *
     * المهمّة بلا مشروع (بيانات قبل إصلاح التوريث) يراها منشئها وحده.
     */
    public static function scopeByProject(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $ids = self::projectIds($user);

        if ($ids === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $ownsOrphans = $query->getModel() instanceof Task;

        return $query->where(function (Builder $q) use ($ids, $table, $ownsOrphans, $user) {
            $q->whereIn("{$table}.project_id", $ids);

            if ($ownsOrphans && $user) {
                $q->orWhere(fn (Builder $o) => $o->whereNull("{$table}.project_id")->where("{$table}.created_by", $user->id));
            }
        });
    }

    /** مساحات العمل: مالكُها، وأعضاؤها، ومن له مشروعٌ مرئيٌّ فيها. */
    public static function scopeWorkspaces(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $ids = self::projectIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($ids, $user) {
            $q->where('admin_id', $user?->id)
                ->orWhereHas('users', fn (Builder $u) => $u->where('users.id', $user?->id))
                ->orWhereHas('projects', fn (Builder $p) => $p->whereIn('projects.id', $ids));
        });
    }

    public static function canView(Project|int|null $project, ?User $user = null): bool
    {
        return self::roleIn($project, $user) !== null;
    }

    /** الكتابة لكل عضوٍ إلا المراقب. */
    public static function canWrite(Project|int|null $project, ?User $user = null): bool
    {
        $role = self::roleIn($project, $user);

        return $role !== null && $role !== 'viewer';
    }

    /** الإدارة للمنشئ والمدير ومن يرى الكل. */
    public static function canManage(Project|int|null $project, ?User $user = null): bool
    {
        return self::roleIn($project, $user) === 'manager';
    }

    public static function canViewWorkspace(Workspace $workspace, ?User $user = null): bool
    {
        return self::scopeWorkspaces(Workspace::whereKey($workspace->getKey()), $user)->exists();
    }

    /** تعديل المساحة وحذفها لمالكها ولمن يرى الكل. */
    public static function canManageWorkspace(Workspace $workspace, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user && (self::seesAll($user) || (int) $workspace->admin_id === (int) $user->id);
    }

    public static function authorizeView(Project|int|null $project): void
    {
        abort_unless(self::canView($project), 403, 'لست عضواً في هذا المشروع.');
    }

    public static function authorizeWrite(Project|int|null $project): void
    {
        abort_unless(self::canWrite($project), 403, 'دورك في هذا المشروع للاطّلاع فقط.');
    }

    public static function authorizeManage(Project|int|null $project): void
    {
        abort_unless(self::canManage($project), 403, 'هذا الإجراء لمدير المشروع.');
    }

    /** مشروع المهمّة — بالصعود في سلسلة الآباء إن لم يُضبط عليها مباشرة. */
    public static function projectIdOfTask(Task $task): ?int
    {
        $node = $task;
        $guard = 0;

        while ($node && ! $node->project_id && $guard++ < 10) {
            $node = $node->parent;
        }

        return $node?->project_id ? (int) $node->project_id : null;
    }

    public static function authorizeTaskView(Task $task): void
    {
        abort_unless(self::canTouchTask($task, false), 403, 'لست عضواً في مشروع هذه المهمّة.');
    }

    public static function authorizeTaskWrite(Task $task): void
    {
        abort_unless(self::canTouchTask($task, true), 403, 'لا تملك التعديل على مهامّ هذا المشروع.');
    }

    public static function canTouchTask(Task $task, bool $write, ?User $user = null): bool
    {
        $user ??= auth()->user();
        $projectId = self::projectIdOfTask($task);

        if ($projectId === null) {
            return $user && (self::seesAll($user) || (int) $task->created_by === (int) $user->id);
        }

        return $write ? self::canWrite($projectId, $user) : self::canView($projectId, $user);
    }

    public static function authorizeSprintView(Sprint $sprint): void
    {
        self::authorizeView($sprint->project_id);
    }

    public static function authorizeSprintWrite(Sprint $sprint): void
    {
        self::authorizeWrite($sprint->project_id);
    }

    /** تُستدعى عقب كل كتابةٍ على العضوية داخل الطلب نفسه. */
    public static function flush(): void
    {
        request()->attributes->remove(self::REQUEST_KEY);
    }

    /** دور المستخدم الفعلي في المشروع، أو `null` إن لم يكن له فيه شيء. */
    private static function roleIn(Project|int|null $project, ?User $user): ?string
    {
        $user ??= auth()->user();
        $projectId = $project instanceof Project ? $project->getKey() : $project;

        if (! $user || ! $projectId) {
            return null;
        }

        if (self::seesAll($user)) {
            return 'manager';
        }

        return self::roles($user)[(int) $projectId] ?? null;
    }

    private static function remember(string $key, callable $resolve): mixed
    {
        $cache = request()->attributes->get(self::REQUEST_KEY, []);

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $resolve();
            request()->attributes->set(self::REQUEST_KEY, $cache);
        }

        return $cache[$key];
    }
}
