# TaskManager → Unified Scrum/Normal Project Management: Phase 1 Discovery + Phase 2 Architecture

Status: **Phase 1 (Discovery) + Phase 2 (Architecture) complete. Phase 3 (Database) complete and verified.** All findings below are evidence-based (file:line citations from direct source reads), not assumptions.

**Phase 3 completion note**: all 14 migrations listed in F were written, then verified against the local sandbox database — forward migration, full rollback (all 14, including the SQLite table-rebuild one), and re-forward, each producing the exact expected schema/data. One real bug was caught and fixed during this verification: the `projects.status` enum-extension migration originally tried to remap old status values via a standalone `UPDATE` before the CHECK constraint was widened (SQLite rejected it); fixed by remapping values inline via SQL `CASE` during the table-rebuild's `INSERT...SELECT`, and by giving the up/down rebuild paths distinct temp-table names (SQLite doesn't rename a table's auto-generated indexes when the table itself is renamed, so reusing one name across both directions collided). The app was smoke-tested afterward (board, projects, workspaces pages) against the migrated schema with no regressions — including confirming `ReportsController`'s existing `status === 'active'` filter, which could never previously match anything under the old `new/in_progress/completed/on_hold` enum, now works correctly for the first time.

---

## A. Current Architecture

- **Modular monolith** (`nwidart/laravel-modules`). `Modules/TaskManager` is an **Http/Views/Services-only module** — it ships **zero migrations of its own** (`Modules/TaskManager/database/migrations/` contains only `.gitkeep`) and only one trivial model (`Modules/TaskManager/Models/TaskSettings.php`, actually shadowed by `app/Models/TaskSettings.php`). Every real model (`Task`, `Project`, `Sprint`, `Workspace`, `Comment`, `User`) lives in `app/Models/`, and every real migration lives in the root `database/migrations/`. The module is a thin controller/view layer over root-level Eloquent models — this is the single most important fact for the redesign: **there is one project/task core already, not two**.
- **Authentication**: standard Laravel `auth` guard/middleware, fully reused, zero TaskManager-specific auth code. Do not touch.
- **Authorization**: the app has a **custom single-role permission system**, not Spatie's native tables (even though `spatie/laravel-permission` is installed alongside it). `User::hasRolePermission()` (`app/Models/User.php:157-168`) loads the user's single `primaryRole` (via `role_id` FK) and checks `role->permissions()->whereIn('name', ...)`. `app/Helpers/PermissionHelper.php` (`hasPermission()`, `checkPermission()`, `canAny()`, `canAll()`) and the Blade directives `@permission`/`@haspermission` (`AppServiceProvider.php:195-206`) all delegate to this same method. Laravel's native `@can`/`Gate::` is wired (`AppServiceProvider.php:44-48`) to call the *same* `hasRolePermission()` — so even "standard" authorization checks route through the custom system, not Spatie. **TaskManager currently defines exactly one permission string for the entire module**: `tasks_view_tasks`, which only gates sidebar-link visibility (`Modules/TaskManager/resources/views/layouts/sidebar.blade.php:51,55`). No controller anywhere in the module calls `hasPermission()`, `@can`, or `Gate::`.
- **Active-project mechanism**: `users.active_project_id` — nullable FK column (migration `2025_11_01_154946_add_active_project_id_to_users_table.php`), **not session-based**. Read as a fallback project scope in `SprintController` (3 places), `TaskController::index` (1 place), `TaskManagerController::index` (1 place). Written in exactly one place: `ProjectController::acceptInvitation()` (`ProjectController.php:626`) when a user accepts a project invite. **There is no dedicated "switch active project" route/action today.** `App\Http\Middleware\EnsureProjectSettings` (registered globally on **every** web request via `app/Http/Kernel.php:40`, plus explicitly again in the module's own route group, `Modules/TaskManager/routes/web.php:19`) reads `active_project_id` to eagerly warm that project's `task_settings` into session/cache on every request.
- **Middleware chain** applied to every TaskManager route: global `web` group (`EnsureModuleEnabled`, `EnsureSubscription`, session/CSRF, etc.) → module wraps itself again in `Route::middleware('web')` (`Modules/TaskManager/Providers/RouteServiceProvider.php:38`) → `['auth', EnsureProjectSettings::class]` (`web.php:19`) → locale/branch group (`localeSessionRedirect`, `localizationRedirect`, `localeViewPath`, `check.branch`). **No project-context-resolution middleware and no permission/role middleware alias** is attached anywhere in the module's route file — route-model-binding is Laravel's default implicit binding only.
- **No Policies anywhere** (`find app Modules/TaskManager -iname "*Policy.php"` finds nothing relevant; `AuthServiceProvider` has no `$policies` array). **No Form Requests anywhere** (`Modules/TaskManager/Http/Requests/` doesn't exist). All validation is inline `Validator::make()`/`$request->validate()` per controller method; all authorization is inline ad-hoc column comparisons (`$model->created_by !== Auth::id()`, `$model->admin_id !== Auth::id()`, one stray `Auth::user()->hasRole('admin')` Spatie call in `CommentController.php:216,249` that is inconsistent with the rest of the app's auth mechanism).
- **Department/Employee** (HR-owned, `Modules/HR` + `app/Models/`): `Employee.department_id` is a direct FK + `belongsTo(Department)` (`Employee.php:188-191`); **separately**, `Department::employees()` is a `belongsToMany` through a distinct `department_employee` pivot table (`Department.php:15-18`). These are two non-reciprocal relationship mechanisms for conceptually the same link — a pre-existing inconsistency in HR, not something TaskManager introduced. `User::employee()` (`User.php:193-196`) bridges `users.employee_id` → `Employee`; `User::isEmployee()` checks `employee_id` is non-null. **TaskManager has zero department-awareness today** — every user/assignee picker in the module is an unscoped `User::all()` (7+ call sites across `ProjectController`, `TaskManagerController`, `ReportsController`, `WorkspaceController`, `TaskController`). Department-scoped queries are an established pattern only inside `Modules/HR`.

---

## B. Existing Task Manager Components

**Controllers** (`Modules/TaskManager/Http/Controllers/`), what each actually does today:

| Controller | Lines | Covers |
|---|---|---|
| `WorkspaceController` | 329 | Workspace CRUD, scoped to `admin_id === Auth::id()` (single-owner, not team-based) |
| `ProjectController` | 710 | Project CRUD, member invite/accept/decline via `project_user` |
| `TaskController` | 1003 | Task CRUD, board/kanban query building, hierarchy dropdown endpoints (components/epics/stories/subtasks/bugs), status update, reorder, sprint-inheritance assignment |
| `SprintController` | 624 | Sprint CRUD, status transitions (planned/active/completed/closed), task attach/detach, per-sprint task status update |
| `CommentController` | 431 | Polymorphic comment CRUD (Task/Project), threaded replies |
| `TaskManagerController` | 409 | Kanban board page + dashboard aggregate stats |
| `ReportsController` | 1543 | 10 report pages (tasks/projects/workspaces breakdowns), **zero input validation anywhere in the file** |
| `TaskSettingsController` | 135 | Per-project status/priority/type/team/sprint config (the one controller with a real service behind it) |
| `ProductBacklogController` | 18 | **Completely empty class body, no methods, no route references it anywhere** — a scaffolded-but-abandoned "Product Backlog" concept |

**Concretely broken things found** (bugs independent of any redesign — worth fixing regardless, and necessary groundwork either way):
1. `TaskController::getParentTasksByProject` — routed (`tasks.parent-tasks-by-project`) but **does not exist** in the class.
2. `SprintController::getUserStoriesForSprint` — routed (`sprints.get-user-stories`) but **does not exist**.
3. `TaskSettingsController::getSprintConfig`/`updateSprintConfig`/`getNotificationConfig`/`updateNotificationConfig` — all 4 routed but **none exist** in the class.
4. `SprintController::store()`/`destroy()` redirect (non-AJAX path) to `route('tasks.sprint-planning')` (`SprintController.php:143,294`) — **this route name is never registered anywhere**; would throw `RouteNotFoundException`.
5. `TaskSettingsController::store()` redirects to `route('taskmanager.settings.index', ...)` (`TaskSettingsController.php:77`) — the actual registered name is `settings.index`. Broken redirect.
6. `Modules\TaskManager\Http\Controllers\Settings\GlobalSettingsController` is imported (`web.php:15`) and referenced in `TaskSettingsServiceProvider.php:17`, but **the file doesn't exist** — dead import, currently harmless only because nothing resolves that binding.
7. `CommentController::getComments()` and `::toggleLike()` — both fully implemented but **no route points to either** — dead code.
8. `Workspace::users()`/`Workspace::hasMember()` (`Workspace.php:37-41,66-69`) reference a `workspace_user` pivot table **that does not exist in any migration** — confirmed by the module's own authors, who left comments in `ReportsController.php:1212,1420` explicitly noting *"no direct pivot table between users and workspaces"* and route around it there. Any code path that actually calls `Workspace::users()`/`hasMember()` will 500 with a SQL "table not found" error. (I found and fixed the two call sites inside `ReportsController` in an earlier session; the dead model methods themselves are still landmines for any future caller.)
9. `Services/Settings/GlobalSettingsService.php`, `Services/Settings/ProjectSettingsService.php`, `Services/Settings/SettingsValidationService.php` (589 lines combined) — a **fully duplicate, entirely unused** namespace of settings services; the real, wired-up one is `Services/GlobalSettingsService.php` at the parent namespace.
10. JS asset drift: `Modules/TaskManager/resources/js/{settings.js, tasks.js}` have **diverged substantially** (540-700+ changed lines) from their same-named `public/modules/taskmanager/js/` counterparts, and neither `resources/js` copy is ever loaded by any view — only `public/modules/taskmanager/js/*` is live. `public/.../app.js` is a 0-byte stub; `settings.js`, `settings-enhanced.js`, `task-updates.js` are all unreferenced by any current view (the settings pages use inline `<script>` instead).
11. `Modules/TaskManager/resources/views/index.blade.php` (5-line scaffold) and `components/layouts/master.blade.php` (unused anonymous component) are nwidart default scaffolding, never referenced — dead views.

**Views** (58 Blade files): the real, live page set is `tasks/task_manger.blade.php` (Kanban board), `tasks/index.blade.php` (tabbed hierarchy browser — this is what's wired to the "Backlog" route today, **not** a true prioritized Scrum backlog), `sprint/{index,create,edit,show}.blade.php`, `project/{index,create,edit,show}.blade.php`, `workspaces/{index,create,edit,show}.blade.php`, `dashboard/task/index.blade.php`, `reports/*` (10 pages), `task-settings/index.blade.php`. All extend `Modules/TaskManager/resources/views/master.blade.php`.

**No view-mode toggle exists today** — Kanban board and tabbed Backlog are two separate pages/routes, not modes of one page. No calendar, no timeline, anywhere in the module currently.

**No dedicated search exists** — only exact-match filter dropdowns (`TaskController::getFilteredTasks`, routed `tasks.filter`) and per-page title `LIKE` filters on the Project/Workspace list pages. No task title/ID search, no cross-entity search.

**Service layer**: exists but narrow — only `TaskSettingsService` (wired) and `GlobalSettingsService` (wired, used by module-level `helpers.php`) are real. Every other controller (Task, Project, Sprint, Workspace, Comment, Reports — i.e. all the actual domain logic) has zero service layer; business logic lives directly in large controllers (Reports 1543 lines, Task 1003, Project 710, Sprint 624).

**Notifications**: infra exists and is solid (`notifications` table + `NotificationSetting` gate + `NotificationDispatcher` for extra channels), but TaskManager fires **exactly one** notification anywhere in the module — on workspace creation (`WorkspaceController.php:173-178`). Nothing fires on task assignment, status change, comment, sprint events, or project invite, despite matching settings-toggle keys already existing in the UI (`task_created`, `task_assigned`, `sprint_review_enabled`, etc. — all decorative, unwired).

**Activity/audit log**: `App\Support\OperationLog` (a solid, already-built dispatcher to `logs`/`event_logs`/`error_logs`) is used in TaskManager for **failure logging only** (14 call sites across Project/Workspace/Task/Sprint controllers, all inside `catch` blocks). `OperationLog::record()` — the **success**-path call that actually writes to the user-visible activity/audit timeline — **is never called anywhere in TaskManager**. Creating a project, task, sprint, comment, or invite writes zero activity-log entries today.

---

## C. Existing Database Structure

All tables below live in root `database/migrations/`, not module-scoped.

| Table | Key columns | Notes |
|---|---|---|
| `workspaces` | `admin_id`, `title`, `description`, `is_primary` | Single-owner (not team) grouping above projects |
| `projects` | `workspace_id`, `title`, `description`, `status` enum(`new,in_progress,completed,on_hold`), `priority` enum(`low,medium,high,urgent`), **`management_type`** enum(`manual,scrum`) default `scrum`, `budget`, `cost`, `progress_percentage`, `created_by`, dates | `management_type` is exactly the spec's `management_method` concept, already built, already the exact binary split requested (just named/valued differently — `manual` ↔ spec's `normal`) |
| `tasks` | `project_id`, `parent_task_id` (self-referential), `sprint_id`, **`type`** enum(`component,epic,user_story,bug,sub_task,task`), `status` enum(`not_started,in_progress,completed,overdue`), `priority` enum(`low,medium,high,urgent`), `budget`, `estimated_hours`, `actual_hours`, dates, `created_by`, `position` | **Epic/UserStory/Component are `Task.type` values, not separate models** — the hierarchy is convention via `parent_task_id`, not DB-enforced. `position` already powers Kanban drag-reorder — directly reusable for backlog ordering. Unused-by-model columns present in schema but absent from `$fillable`: `completion_percentage`, `files` (json), `assigned_to`. |
| `project_user` | `project_id`, `user_id`, `email`, `invite_token`, `status`, `invited_at`, `expires_at`, **`role`** enum(`manager,member,viewer`), `invited_by`, `invite_message`, `joined_at` | Doubles as the invitation mechanism. `role` is validated but **never read back for authorization anywhere** — currently write-only. |
| `task_user` | pivot, `assigned_at`, `assigned_by` | Task assignee many-to-many |
| `comments` | polymorphic (`commentable_type/id`), `parent_id` (self-referential threading), `content`, `is_edited`, `edited_at`, soft-deletes | Reused this session for task/project comments already |
| `sprints` | `project_id`, `name`, `goal` (text), `start_date`, `end_date`, **`status`** enum(`planned,active,completed,closed`) | **No `capacity`, no `created_by`, no story-points field** despite the spec listing both as conceptual sprint fields |
| `task_settings` | `project_id`, `is_global`, `setting_key`, `setting_value` (json), `setting_type` | **Already implements per-project-configurable statuses/priorities/types with global fallback** — this already satisfies the spec's "configurable statuses if architecture permits" requirement, no new table needed |
| `notifications` | `user_id`, `receiver_id`, `type`, `title`, `description`, `read`, scheduling fields | Reusable, currently under-used by TaskManager |
| `logs` / `event_logs` / `error_logs` | via `OperationLog`/`ActivityLogService`/`EventLogService`/`ErrorLogService` | Reusable, currently under-used by TaskManager (failures only) |

**Confirmed NOT to exist anywhere** (checked exhaustively, not inferred): separate `Epic`/`UserStory` models or tables; `task_dependencies`; `task_attachments` (the `tasks.files` json column exists but its only consumer, `Task::addFile()`, is an empty stub); `checklist`/`TaskChecklist`; `labels`/tags; `sprint_items` (sprint↔task is only the direct `tasks.sprint_id` FK); `sprint_daily_updates`; `sprint_reviews`; `sprint_retrospectives`; `workspace_user` (referenced by code, absent from schema — see finding B.8); `department_id`/`workspace_type` on `projects` or any TaskManager table; burndown/velocity/cycle-time calculations anywhere in `ReportsController`.

---

## D. Reusable Components (what gets extended, not duplicated)

- **Auth, session, guards** — reused as-is, zero changes.
- **User/Employee/Department models** — reused as-is. New TaskManager department-scoping queries will read `Employee.department_id` (the simpler, direct-FK mechanism) rather than trying to reconcile HR's two inconsistent relations.
- **`Project` model** — extended, not replaced. `management_type` already *is* the scrum/normal switch (rename display labels only, see Risk N.1). `workspace_id`, `status`, `priority`, `created_by`, `progress_percentage` all reused as-is.
- **`Task` model** — reused entirely for **both** modes: Normal projects use `type ∈ {task, sub_task}`; Scrum projects use `type ∈ {component, epic, user_story, sub_task, bug}`. This is already exactly the spec's "one shared core, not two systems" philosophy — no new Epic/UserStory tables needed, only new *columns* on the existing `tasks` table for Scrum-only fields (`story_points`, `acceptance_criteria`), nullable so Normal tasks are unaffected.
- **`Sprint` model** — reused, extended with `capacity` + `created_by` columns.
- **`project_user`** — reused for membership + base ownership roles. Scrum-specific roles (Product Owner, Scrum Master) added as a new nullable column, not a rewrite of the existing `role` enum (avoids touching the invitation-role validation that already works).
- **`task_settings` / `TaskSettingsHelper`** — reused entirely for per-project configurable statuses/priorities/types (Normal board columns like "Backlog/Testing/Review/Done" from the spec are already achievable today through this existing mechanism).
- **`Comment` model** — reused as-is (already polymorphic, already supports Task and Project).
- **`notifications` + `NotificationSetting` + `OperationLog`/`ActivityLogService`** — reused as-is; the redesign's job is to actually *call* these at the right points, not build new infrastructure.
- **Permission system (`hasRolePermission`/`role_has_permissions`)** — reused and extended with a new permission catalog for TaskManager (today there's exactly one permission string for the whole module).
- **`active_project_id`** — reused as the project-context mechanism; project-switching UI will write to this same column via a new, currently-missing "switch project" action rather than inventing a session-based alternative.
- **`tm-common.css` design system + `page_hero` partial** (built earlier this session) — reused as the visual foundation for all new pages (Backlog, Sprint Planning, Review, Retrospective, Calendar, Timeline).

---

## E. Required Changes (summary — detail in F/H/I)

1. Fix the 11 concrete bugs in B before/alongside the redesign (they block reliable extension regardless of scope — e.g. you cannot safely build Sprint Planning on top of a controller that already redirects to a non-existent route).
2. Resolve 3 naming/design decisions before any migration is written (see "Open Decisions" at the end) — `management_type` vs `management_method`, where `workspace_type`/`department_id` actually belongs (Project vs Workspace), and how Scrum-specific roles map onto the existing `project_user.role` enum.
3. Add the additive schema in F.
4. Introduce policies + Form Requests incrementally, one controller at a time, each covered by tests before moving to the next (this is a structural change touching every controller, so it must be sequenced carefully — see Risks).
5. Introduce a real service layer for the domain logic that currently lives in controllers (Project/Task/Sprint/Backlog/Board/Report), following the existing `TaskSettingsService` precedent rather than inventing a new pattern.
6. Build the genuinely-missing UI: Backlog (real, orderable), Sprint Planning, Sprint Review, Retrospective, Daily Scrum, Calendar, Timeline, List view, dynamic Scrum/Normal navigation, project-switcher.
7. Wire up notifications and activity-log calls at the actual mutation points (task assigned, status changed, sprint started/completed, comment added, member added) — infrastructure already exists, just needs to be invoked.

---

## F. New Database Changes (all additive, all reversible, no drops)

**Decisions confirmed** (see updated N.1/N.7/N.8): reuse `projects.management_type` as-is (display `manual` as "Normal"); `department_id`/`workspace_type` live on **`workspaces`**, and `workspace_user` is now a **real, built** membership pivot — Workspace becomes the actual team container, Project membership (`project_user`) is scoped to be a subset of the project's workspace's members; Sprint Review folds into `sprint_retrospectives` (no separate review table).

1. **`workspaces`**: add `department_id` (nullable FK → `departments`, `onDelete('set null')`), `workspace_type` enum(`department`,`shared`) default `shared`. A `department` workspace's eligible members are restricted to `Employee.department_id` matches (enforced in `WorkspaceService`/`WorkspacePolicy`, not a DB constraint); a `shared` workspace allows any employee/user.
2. **New table `workspace_user`**: `workspace_id`, `user_id`, `role` enum(`owner`,`member`) default `member`, `joined_at`, timestamps, unique(`workspace_id`,`user_id`). Backs the currently-dead `Workspace::users()`/`hasMember()` calls for real. `admin_id` stays on `workspaces` as the creator/primary-owner pointer (unchanged), and workspace creation auto-inserts the creator as `role=owner` here — same pattern `project_user` already uses for project creators.
3. **`projects`**: extend `status` enum to add `planning`, `cancelled`, `archived` (final set: `planning,active,on_hold,completed,cancelled,archived`, with a value-mapping data migration for existing `new`→`planning`, `in_progress`→`active` rows). No `management_type` change — reused as-is.
4. **`sprints`**: add `capacity` (nullable unsigned smallint — story points capacity), `created_by` (nullable FK → `users`).
5. **`tasks`**: add `story_points` (nullable unsigned tinyint, Scrum-only, UI enforces the 1/2/3/5/8/13/21 set), `acceptance_criteria` (nullable text, Scrum user-story-only).
6. **New table `task_dependencies`**: `task_id`, `related_task_id`, `type` enum(`blocks`,`blocked_by`,`depends_on`,`related_to`,`duplicates`), timestamps, unique(`task_id`,`related_task_id`,`type`).
7. **New table `task_attachments`**: `task_id`, `uploaded_by`, `disk`, `path`, `original_name`, `size`, `mime_type`, timestamps. (Supersedes the dead `tasks.files` json column/`addFile()` stub — leave that column alone, just stop relying on it.)
8. **New table `task_checklist_items`**: `task_id`, `title`, `is_done` (boolean), `position`, timestamps.
9. **New tables `labels`** (`project_id` nullable, `name`, `color`) **+ `label_task`** pivot (`label_id`,`task_id`).
10. **New table `sprint_daily_updates`**: `sprint_id`, `user_id`, `date`, `yesterday` (text), `today` (text), `blockers` (text), unique(`sprint_id`,`user_id`,`date`).
11. **New table `sprint_retrospectives`**: `sprint_id` (unique — one per sprint), `went_well` (text), `went_wrong` (text), `improvements` (text), `action_items` (json), `completed_points`/`committed_points`/`completion_percentage` (nullable — a point-in-time snapshot captured when the retrospective is created, satisfying the "review" half without a separate table), `created_by`, timestamps.
12. **`project_user`**: add `scrum_role` nullable enum(`product_owner`,`scrum_master`) — additive column, existing `role` enum (`manager`,`member`,`viewer`) untouched. A `CHECK`/application-level rule (not DB FK, SQLite has no cross-table trigger support here) ensures a user can only be added to `project_user` if they're already in that project's workspace's `workspace_user` — enforced in the new `ProjectService`, mirroring how `TaskController::store()` already validates hierarchy rules in PHP rather than the DB.
13. **Permission catalog seed** (data, not schema): new rows for `workspace.manage_members`, `project.view/edit/delete/archive`, `project.manage_members`, `task.create/assign/change_status/change_priority`, `backlog.manage`, `sprint.manage/start/complete`, `retrospective.manage`, `reports.view`, following the existing `SystemPermissions`/`JobRole::$job_roles` catalog pattern, synced via the existing `permissions:sync` command.

**Not building**: `Epic`/`UserStory` tables (Task.type already covers this), `sprint_items` pivot (direct `tasks.sprint_id` FK already covers it, and backlog ordering reuses the existing `tasks.position` column), a separate `sprint_reviews` table (folded into `sprint_retrospectives`, item 11), a second activity-log table (reuse `logs`).

---

## G. Migration Strategy

- One migration per concern, small, always with a working `down()` — matching this codebase's existing convention (e.g. the granular `add_X_to_Y_table` migrations already in the history for `tasks`/`projects`).
- Every enum extension reuses the **SQLite-safe CHECK-constraint rebuild pattern already established** in this exact codebase (`database/migrations/2026_09_01_130100_add_task_type_to_tasks_type_enum.php` — full-table-rebuild path for SQLite, plain `ALTER ... MODIFY` for MySQL). This is required because SQLite enums are CHECK constraints, not a native type — already solved once in this repo, just needs to be followed again for `projects.status` and the two new enum columns.
- Order: (1) additive columns/tables first, all nullable/defaulted so zero risk to existing rows, (2) a single data-backfill migration/command for `projects.workspace_type` (default `shared` for all existing rows) and any `status` value remapping the naming decision requires, (3) permission-catalog seed + `permissions:sync` run, (4) only then application code that starts reading the new columns.
- Every migration gets a local SQLite-in-memory dry run (matching the pattern already used this session for sandbox verification) before touching the real dev database, exactly as this session's earlier local-sandbox workflow did for the board/report redesign.

---

## H. Backend Architecture

- **New service classes**, following the existing `TaskSettingsService` precedent (not a new pattern): `ProjectService` (creation incl. `management_type`/`workspace_type`/department validation, and immutability enforcement — `management_type` is *already* excluded from `ProjectController::update()`'s allowed fields today, per this session's earlier work, so this is "keep true" not "add new"), `SprintService` (lifecycle + capacity/points aggregation feeding Planning/Review/Burndown), `BacklogService` (ordering/filtering over `tasks.position`), `BoardService` (the status-update/reorder logic already in `TaskController`, extracted so both Normal and Scrum boards call one implementation instead of two), `ReportService` (extracted from the 1543-line `ReportsController`).
- **New Policy classes** — `ProjectPolicy`, `TaskPolicy`, `SprintPolicy`, `WorkspacePolicy` — registered in `AuthServiceProvider`. Internally these call the **existing** `hasRolePermission()`/`project_user.role`/`scrum_role` checks — this organizes today's scattered ad-hoc inline checks into Laravel's standard policy surface, it does not introduce a second authorization system.
- **New Form Request classes** replacing today's inline `Validator::make()` calls — mechanical extraction of validation rules that already exist in controllers, not new validation logic, done one controller at a time.
- Fix the 11 bugs from B as part of this pass (several are directly in the code paths being touched anyway — e.g. `SprintController`'s broken redirect is in the same method being wrapped in a Form Request).

---

## I. Frontend Architecture

- **Dynamic navigation**: `layouts/sidebar.blade.php` (and a new project-scoped sub-nav partial) branches on `$project->management_type` to show the spec's two distinct nav lists (Overview/Board/Tasks/Calendar/Timeline/Reports/Members/Settings for Normal; Overview/Backlog/Sprint/Board/Planning/Calendar/Reports/Retrospective/Members/Settings for Scrum) — additive `@if`, reusing the one existing sidebar file, not a fork.
- **New pages**: Backlog (real, orderable — evolve `tasks/index.blade.php`'s query into a top-level prioritized list using existing `tasks.position`), Sprint Planning (two-pane backlog↔sprint UI showing capacity/committed-points/remaining, warning-not-blocking on overcommit per spec §17), Sprint Review (completion stats computed live from real `tasks`/`sprints` data + move-to-next-sprint/backlog/cancel actions reusing existing `SprintController::addTask/removeTask`), Retrospective (4-field form → `sprint_retrospectives`), Daily Scrum (lightweight per-day entry → `sprint_daily_updates`), Calendar and Timeline (new read-only projections of the same filtered task query already powering the board — reused, not duplicated), List view (a table rendering of the same query).
- **View-mode toggle**: `?view=board|list|calendar|timeline` query param against the *same* backend filter/query infrastructure already in `TaskManagerController`/`TaskController::getTaskManagerData` — satisfies the spec's "share backend query/filter infrastructure" requirement directly.
- All new UI built on the `tm-common.css` design system + `page_hero` partial already established this session, so it's visually consistent with the board/reports/dashboard work already shipped.
- Clean up the JS drift found in B.10 as part of this work: new JS goes only in `public/modules/taskmanager/js/`; flag `Modules/TaskManager/resources/js/*` and the 3 unreferenced `public/` JS files for removal (with your confirmation, since deleting files is something I'll always ask about, not do silently).

---

## J. Permission Strategy

- **One permission system**: extend the existing `role_has_permissions`/`hasRolePermission()` mechanism with the new catalog listed in F.11. No Spatie-native-table usage, no second system.
- **Project-level Scrum roles** (Product Owner, Scrum Master) are **not** a parallel RBAC system — they're the new `project_user.scrum_role` nullable column, consulted only for (a) UI display/default-assignee suggestions and (b) gating Scrum-ceremony actions specifically (only the Scrum Master or project owner can start/close a sprint, manage retrospective) via the new `SprintPolicy`. Org-wide capability gates (can this user create tasks at all, view reports, etc.) stay entirely on the existing org-wide `role_has_permissions` system. This hybrid matches the spec's own permission list (§26), which mixes org-wide-shaped items ("view reports") with clearly per-project items ("start sprint") — reusing the right existing mechanism for each, not inventing a uniform new one.
- Every mutation in every new/touched controller method gets a policy check before executing — closing the gap where, today, most TaskManager mutations have no authorization check beyond "is logged in."

---

## K. Scrum Workflow (mapped onto reused pieces)

```
Product Backlog   = tasks WHERE project_id=X AND type IN (epic,user_story,bug) AND sprint_id IS NULL, ORDER BY position
                     (existing tasks table + existing position column — new query, no new table)
        ↓
Sprint Planning   = new UI; moves selected rows' sprint_id → target sprint
                     (reuses existing SprintController::addTask; new capacity/points readout from Sprint.capacity + SUM(tasks.story_points))
        ↓
Sprint            = existing Sprint model/CRUD, unchanged
        ↓
Sprint Execution  = existing Kanban board, scoped to sprint_id (already works today — see sprint/show.blade.php)
        ↓
Daily Scrum       = new sprint_daily_updates rows, new lightweight page (org-setting-gated per spec §19)
        ↓
Sprint Review     = new page; all numbers computed live from real tasks/sprints data (no fake metrics, per spec §22);
                     optional persisted snapshot in new sprint_reviews-equivalent — folded into sprint_retrospectives
                     unless a separate saved "review" record is explicitly wanted (open question, see below)
        ↓
Retrospective     = new sprint_retrospectives record (what went well/wrong/improve/action items)
        ↓
Next Sprint       = existing SprintController::store() again; incomplete items move via existing
                     addTask/removeTask (next sprint) or sprint_id=null (return to backlog)
```

---

## L. Normal Workflow (mapped onto reused pieces)

```
Project (management_type=manual, displayed as "Normal")
   → Task (type=task) / Subtask (type=sub_task, parent_task_id)
   → Board       = existing Kanban (already works, already status-driven via task_settings-configurable statuses)
   → List        = new: same query, table rendering
   → Calendar     = new: same query, calendar rendering against start_date/due_date
   → Timeline     = new: same query, basic Gantt-style rendering against start_date/due_date
   → Reports      = existing ReportsController (already project-management-agnostic)
```
No Epic/UserStory/Sprint/StoryPoints/ScrumMaster/ProductOwner/Retrospective/Velocity terminology or data ever surfaces for `management_type='manual'` projects — enforced at the view layer (dynamic nav in I) and, for the underlying data, `type` values `epic`/`user_story`/`component`/`bug` are simply never created against a Normal project (validated in the extracted `TaskService`/Form Request, matching `TaskController::store()`'s existing type-conditional validation which already branches on `$project->isManual()`).

---

## M. Testing Strategy

- PHPUnit Feature tests, using the existing sqlite-in-memory `phpunit.xml` config — no new test infra needed.
- Test names lifted close to verbatim from the spec's own §42 scenario list (Normal: create project → create task → assign → move across board → complete → comment → attachment → subtask → dependency → report → unauthorized-user-rejected; Scrum: create project → epic → story → assign → points → backlog → sprint → move-into-sprint → start → tasks → board moves → daily scrum → complete sprint → review → move-incomplete → retrospective → verify velocity/burndown numbers).
- Policy unit tests for every new Policy class (positive + negative cases per role).
- Service unit tests specifically for burndown/velocity/capacity math, asserted against fixed fixture data with known expected numbers — required because the spec explicitly forbids fake/placeholder metrics (§22), so the calculation itself needs direct test coverage, not just "the page loads."
- Regression tests for the 11 existing bugs in B, written as failing tests first (proving the bug), then fixed.
- Run the existing `composer test` suite after every phase to confirm no regression to already-passing tests.

---

## N. Risks

1. **`management_type` (`manual`/`scrum`) vs. spec's `management_method` (`normal`/`scrum`) — naming/value mismatch. CONFIRMED: reuse `management_type` as-is**, display `manual` as "Normal" everywhere in the UI. No new column.
2. **Two permission systems already coexist** (custom `role_has_permissions` + installed-but-mostly-unused Spatie). Adding a third (e.g. a project-scoped RBAC package) would be a real overengineering risk explicitly warned against in the spec (§44). Mitigated by the hybrid strategy in J.
3. **`workspace_user` dead code** (B.8) is a live landmine independent of this project — any future code path calling `Workspace::users()`/`hasMember()` will 500. Should be cleaned up (either implement the pivot for real, or remove the two dead methods) regardless of the rest of this work.
4. **JS asset drift** (B.10): `Modules/TaskManager/resources/js/*` has silently diverged from the live `public/` copies and is loaded by nothing. New frontend work must be careful to edit `public/modules/taskmanager/js/*` only — this session already learned that lesson once (an earlier redesign initially targeted the wrong copy).
5. **6 already-broken routes/methods** (B.1-6) must be fixed as groundwork, not treated as new scope — building Sprint Planning on top of a `SprintController` that redirects to a nonexistent route on the happy path is not viable.
6. **Introducing Policies/Form Requests touches every controller.** This is the highest-blast-radius structural change in the plan. Must be sequenced one controller at a time, each with tests passing, before moving to the next — never a single sweeping rewrite.
7. **Where `department_id`/`workspace_type` belongs. CONFIRMED: Workspace becomes a real team container.** `department_id`/`workspace_type` live on `workspaces`; the `workspace_user` pivot is now built for real (F.2). This is a bigger change than the minimal-diff option — it turns `Workspace` from single-owner/administrative into an actual membership boundary, which means: (a) `WorkspaceController::index()`'s current scoping (`admin_id === Auth::id()`, i.e. "workspaces I created") must become "workspaces I'm a member of" — a real behavior change to who sees what in the workspace list; (b) `WorkspaceController` needs new member-management endpoints (add/remove/list members, mirroring `ProjectController`'s existing invite pattern) that don't exist today; (c) `ProjectController`'s member-invite flow needs a new guard restricting invitees to the project's workspace's existing members (or auto-adding them to the workspace too — a decision for Phase 4); (d) the dead `Workspace::users()`/`hasMember()` methods (B.8) become live, tested code instead of being removed.
8. **Sprint Review vs. Retrospective. CONFIRMED: folded into one `sprint_retrospectives` record**, with a handful of point-in-time snapshot columns (committed/completed points, completion %) added alongside the narrative fields so the "review" numbers are preserved even if underlying task data changes later, without a 10th table.
9. **Scale of this project.** This is a large, multi-week-equivalent scope even with maximal reuse. The spec's own §41 phasing (Discovery → Architecture → Database → Core → Normal → Scrum → UX → Testing) is the right approach and should be followed literally, with a checkpoint before each phase rather than one continuous push — matching the spec's explicit "do not implement everything in one uncontrolled change" instruction (§41 preamble).

---

## O. Implementation Plan (next steps)

This document **is** Phase 1 (Discovery) + Phase 2 (Architecture). All three open decisions (N.1, N.7, N.8) are now **confirmed** — see F/N for the locked-in schema. Concretely, the next steps are:

1. **Phase 3 (Database)**: write and locally-verify (SQLite dry run, per this session's established sandbox workflow) the migrations in F, in the order in G. Nothing destructive; every migration additive and reversible.
2. **Phase 4 (Core Project Management)**: workspace membership UI (`workspace_user` add/remove/list, department-eligibility guard), `scrum_role` on the project invite/member-management UI, the new permission catalog + Policies wired onto existing controllers, a real "switch active project" action (currently missing entirely), fix the 11 bugs from B along the way.
3. **Phase 5 (Normal)**: List/Calendar/Timeline views, extracted `BoardService`, Task attachments/checklist/dependencies/labels UI.
4. **Phase 6 (Scrum)**: real Backlog page, Sprint Planning, Daily Scrum, Sprint Review, Retrospective, story points, burndown/velocity metrics (real-data-only).
5. **Phase 7 (UX)**: dynamic nav, project switcher, dashboards for both modes, drag/drop polish consistent with the board work already shipped.
6. **Phase 8 (Testing)**: the M section, executed incrementally alongside 3-7, not deferred to the end.

At each phase boundary: report modified files, created migrations, database changes actually applied, and tests executed — per the spec's own reporting requirement (§46) — before proceeding to the next phase.
