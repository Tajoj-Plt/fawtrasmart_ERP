<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Helpers\TaskSettingsHelper;
use App\Http\Controllers\Controller;
use App\Mail\ProjectInviteMail;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\TaskManager\Services\ProjectAccess;

class ProjectController extends Controller
{
    /** وحدة السجلّ — بها يربط `logs`/`event_logs` أسطرَ هذه الشاشة. */
    private const LOG_MODULE = 'TaskManager.Project';

    public function __construct()
    {
        /*
            حارسٌ في المتحكّم فوق حارس المسار — الصلاحية صفةُ الإجراء لا
            صفةُ المسار.

            `acceptInvitation` و`declineInvitation` خارجه عمداً: المدعوّ
            يردّ على دعوةٍ تخصّه برمزٍ في رابطه، وأغلب المدعوّين لا صلاحيات
            وحدةٍ لهم بعد — فاشتراطُها يقفل الدعوة على من أُرسلت له.
        */
        $this->middleware('permission.role:tasks.projects.view', ['only' => ['index']]);
        $this->middleware('permission.role:tasks.projects.show', ['only' => ['show']]);
        $this->middleware('permission.role:tasks.projects.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:tasks.projects.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:tasks.projects.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:tasks.projects.invite', ['only' => ['inviteUser']]);
    }

    public function index(Request $request)
    {
        // جلب مساحات العمل للفلترة
        $workspaces = ProjectAccess::scopeWorkspaces(Workspace::query())->get();

        if ($request->ajax() || $request->is_ajax) {
            // البحث والترشيح
            $query = ProjectAccess::scopeProjects(Project::with(['workspace', 'creator']))
                ->orderByDesc('created_at');

            // تطبيق الفلاتر
            if ($request->filled('title')) {
                $query->where('title', 'like', '%'.$request->title.'%');
            }

            if ($request->filled('workspace_id')) {
                $query->where('workspace_id', $request->workspace_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('from_date')) {
                $query->whereDate('start_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('end_date', '<=', $request->to_date);
            }

            // الترقيم
            $perPage = min(max((int) $request->get('per_page', 10), 1), 100);
            $projects = $query->paginate($perPage);

            if ($request->expectsJson()) {
                $view = view('taskmanager::project.partials.table', compact('projects'))->render();

                return response()->json([
                    'success' => true,
                    'table' => $view,
                    'total' => $projects->total(),
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'from' => $projects->firstItem(),
                    'to' => $projects->lastItem(),
                ]);
            }
        } else {
            // For initial page load, fetch projects with default pagination
            $query = ProjectAccess::scopeProjects(Project::with(['workspace', 'creator']))
                ->orderByDesc('created_at');

            $perPage = min(max((int) $request->get('per_page', 10), 1), 100);
            $projects = $query->paginate($perPage);
        }

        $activeProjectsCount = ProjectAccess::scopeProjects(Project::query())->where('status', '!=', 'completed')->count();
        $totalProjectsCount = ProjectAccess::scopeProjects(Project::query())->count();

        return view('taskmanager::project.index', compact('workspaces', 'projects', 'activeProjectsCount', 'totalProjectsCount'));
    }

    /**
     * عرض تفاصيل مشروع
     */
    public function show(Project $project)
    {
        ProjectAccess::authorizeView($project);

        // تحميل العلاقات
        $project->load(['workspace', 'creator', 'users']);

        // جلب جميع المستخدمين للدعوات
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();

        // Get project settings using our helper
        $activeProjectId = $project->id;
        $priorities = TaskSettingsHelper::getPriorities($activeProjectId);
        $statuses = TaskSettingsHelper::getStatuses($activeProjectId);
        $taskTypes = TaskSettingsHelper::getTaskTypes($activeProjectId);

        return view('taskmanager::project.show', compact('project', 'users', 'priorities', 'statuses', 'taskTypes'));
    }

    public function create()
    {
        $workspaces = ProjectAccess::scopeWorkspaces(Workspace::query())->get();
        $users = User::all();

        return view('taskmanager::project.create', compact('workspaces', 'users'));
    }

    public function store(Request $request)
    {
        try {
            // التحقق من صحة البيانات
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'workspace_id' => 'required|exists:workspaces,id',
                'status' => 'required|in:planning,active,on_hold,completed,cancelled,archived',
                'priority' => 'required|in:low,medium,high,urgent',
                'management_type' => 'required|in:manual,scrum',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'budget' => 'nullable|numeric|min:0',

                'team_members' => 'nullable|array',
                'team_members.*' => 'exists:users,id',
            ], [
                'title.required' => 'اسم المشروع مطلوب',
                'workspace_id.required' => 'مساحة العمل مطلوبة',
                'workspace_id.exists' => 'مساحة العمل المختارة غير موجودة',
                'management_type.required' => 'الرجاء اختيار طريقة إدارة المشروع',
                'management_type.in' => 'طريقة إدارة المشروع غير صالحة',
                'start_date.required' => 'تاريخ البداية مطلوب',
                'end_date.required' => 'تاريخ النهاية مطلوب',
                'end_date.after' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات غير صحيحة',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if (! ProjectAccess::canViewWorkspace(Workspace::findOrFail($request->workspace_id))) {
                return response()->json([
                    'success' => false,
                    'message' => 'لست عضواً في مساحة العمل المختارة',
                ], 403);
            }

            // بدء معاملة قاعدة البيانات
            DB::beginTransaction();

            // إنشاء المشروع
            $project = Project::create([
                'title' => $request->title,
                'description' => $request->description,
                'workspace_id' => $request->workspace_id,
                'status' => $request->status ?? 'planning',
                'priority' => $request->priority ?? 'medium',
                'management_type' => $request->management_type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'budget' => $request->budget ?? 0,

                'progress_percentage' => 0,
                'created_by' => Auth::id(),
            ]);

            // المنشئ مديرُ مشروعه، ثم أعضاء الفريق المختارون
            DB::table('project_user')->insert([
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'role' => 'manager',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncMembers($project, (array) $request->input('team_members', []));

            // تأكيد المعاملة
            DB::commit();

            // تحميل العلاقات للاستجابة
            $project->load('users');

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $project,
                sprintf(
                    'أُنشئ المشروع **%s** في مساحة العمل **%s** بإدارة **%s**',
                    $project->title,
                    optional($project->workspace)->title ?? '—',
                    $project->management_type === 'scrum' ? 'سكرم' : 'يدوية',
                ),
            );

            // إرجاع استجابة نجاح
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء المشروع بنجاح',
                'data' => [
                    'project' => $project,
                    'redirect_url' => route('projects.show', $project->id),
                ],
            ]);

        } catch (\Exception $e) {
            // التراجع عن المعاملة في حالة الخطأ
            DB::rollBack();

            // تسجيل الخطأ
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء المشروع',
            ], 500);
        }
    }

    /**
     * عرض نموذج تعديل مشروع
     */
    public function edit(Project $project)
    {
        ProjectAccess::authorizeManage($project);

        $workspaces = ProjectAccess::scopeWorkspaces(Workspace::query())->get();
        $users = User::all();

        return view('taskmanager::project.edit', compact('project', 'workspaces', 'users'));
    }

    /**
     * تحديث مشروع موجود
     */
    public function update(Request $request, Project $project)
    {
        ProjectAccess::authorizeManage($project);

        try {
            // التحقق من صحة البيانات
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'workspace_id' => 'required|exists:workspaces,id',
                'status' => 'required|in:planning,active,on_hold,completed,cancelled,archived',
                'priority' => 'required|in:low,medium,high,urgent',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'actual_end_date' => 'nullable|date|after_or_equal:start_date',
                'budget' => 'nullable|numeric|min:0',
                'cost' => 'nullable|numeric|min:0',
                'progress_percentage' => 'nullable|integer|min:0|max:100',
                'team_members' => 'nullable|array',
                'team_members.*' => 'exists:users,id',
            ], [
                'title.required' => 'اسم المشروع مطلوب',
                'workspace_id.required' => 'مساحة العمل مطلوبة',
                'workspace_id.exists' => 'مساحة العمل المختارة غير موجودة',
                'start_date.required' => 'تاريخ البداية مطلوب',
                'end_date.required' => 'تاريخ النهاية مطلوب',
                'end_date.after' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية',
                'progress_percentage.max' => 'نسبة الإنجاز لا يمكن أن تتجاوز 100%',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات غير صحيحة',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // بدء معاملة قاعدة البيانات
            DB::beginTransaction();

            // تحديث بيانات المشروع
            $project->update([
                'title' => $request->title,
                'description' => $request->description,
                'workspace_id' => $request->workspace_id,
                'status' => $request->status,
                'priority' => $request->priority,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'actual_end_date' => $request->actual_end_date,
                'budget' => $request->budget ?? 0,
                'cost' => $request->cost ?? 0,
                'progress_percentage' => $request->progress_percentage ?? 0,
            ]);

            // فرقُ القيم يُلتقط عقب `update()` مباشرةً: `getOriginal()`
            // ما زال يحمل ما كان، وأيّ حفظٍ لاحق في هذه الدالّة يمحوه
            $changes = OperationLog::diff($project, [
                'title', 'description', 'workspace_id', 'status', 'priority',
                'start_date', 'end_date', 'actual_end_date', 'budget', 'cost',
                'progress_percentage',
            ]);

            // الحقل الغائب معناه «لا تغيير على الفريق» لا «أزل الجميع»:
            // نموذجٌ لا يعرض قائمة الأعضاء لا يُفترض أن يمحوها
            if ($request->has('team_members') || $request->boolean('team_members_present')) {
                $this->syncMembers($project, (array) $request->input('team_members', []));
            }

            // تأكيد المعاملة
            DB::commit();

            // تحميل العلاقات للاستجابة
            $project->load('users');

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $project,
                sprintf('عُدّل المشروع **%s**', $project->title),
                $changes,
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المشروع بنجاح',
                'data' => [
                    'project' => $project,
                    'redirect_url' => route('projects.show', $project->id),
                ],
            ]);

        } catch (\Exception $e) {
            // التراجع عن المعاملة في حالة الخطأ
            DB::rollBack();

            // تسجيل الخطأ
            OperationLog::failure($e, self::LOG_MODULE, 'update', $project);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث المشروع',
            ], 500);
        }
    }

    /**
     * إرسال دعوة لمستخدم للانضمام إلى مشروع
     */
    public function inviteUser(Request $request, Project $project)
    {
        // لمنشئ المشروع ومديريه — وكانت للمنشئ وحده، فلا تنفع مديراً
        // أُسند إليه المشروع ولا تنفعه صلاحية `tasks.projects.invite`
        ProjectAccess::authorizeManage($project);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:manager,member,viewer',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'البيانات غير صحيحة',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::findOrFail($request->user_id);
            $role = $request->role;
            $message = $request->message;

            /*
                الصفّ يُبحث عنه بالمستخدم لا بالبريد: أعضاء الإنشاء يُدرجون
                بلا بريد، فكان الفحص بالبريد لا يراهم ثم يصطدم الإدراج
                بالقيد الفريد `(project_id, user_id)` — خطأ 500 لكل دعوةٍ
                لعضوٍ قائم ولكل من رفض دعوةً سابقة.
            */
            $existing = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing && $existing->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المستخدم عضوٌ في المشروع بالفعل',
                ], 422);
            }

            if ($existing && $existing->status === 'pending' && $existing->expires_at && now()->lt($existing->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المستخدم مدعو بالفعل إلى المشروع',
                ], 422);
            }

            $inviteToken = Str::random(64);

            $invitation = [
                'email' => $user->email,
                'invite_token' => $inviteToken,
                'role' => $role,
                'status' => 'pending',
                'invited_by' => Auth::id(),
                'invite_message' => $message,
                'invited_at' => now(),
                'expires_at' => now()->addWeek(),
                'updated_at' => now(),
            ];

            // دعوةٌ مرفوضة أو منتهية تُجدَّد في صفّها — القيد الفريد لا
            // يسمح بصفٍّ ثانٍ للمستخدم نفسه
            if ($existing) {
                DB::table('project_user')->where('id', $existing->id)->update($invitation);
            } else {
                DB::table('project_user')->insert($invitation + [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);
            }

            $emailSent = true;

            try {
                Mail::to($user->email)->send(new ProjectInviteMail([
                    'inviter_name' => Auth::user()->name,
                    'project_title' => $project->title,
                    'workspace_title' => $project->workspace->title ?? 'غير محدد',
                    'role' => $this->getRoleName($role),
                    'invite_message' => $message,
                    'accept_url' => route('invitations.accept', $inviteToken),
                ]));
            } catch (\Throwable $e) {
                // الدعوة قائمةٌ في النظام وإن لم يصل بريدها — لكنّ المرسِل
                // يُخبَر، وكان يُقال له «تم الإرسال» والبريد لم يخرج
                $emailSent = false;
                OperationLog::failure($e, self::LOG_MODULE, 'inviteUser:mail', $project);
            }

            // الدعوة تمنح وصولاً إلى كل مهامّ المشروع — فتُسجَّل في سجلّ
            // النشاط كما يُسجَّل تعديل المشروع نفسه
            OperationLog::record(
                'invited',
                self::LOG_MODULE,
                $project,
                sprintf(
                    'دُعي **%s** (%s) إلى المشروع **%s** بدور **%s**',
                    $user->name ?? $user->email,
                    $user->email,
                    $project->title,
                    $this->getRoleName($role),
                ),
            );

            return response()->json([
                'success' => true,
                'message' => $emailSent
                    ? 'تم إرسال الدعوة بنجاح'
                    : 'سُجّلت الدعوة، لكن تعذّر إرسال بريدها — راجع إعدادات البريد أو أبلغ المدعوّ مباشرةً.',
                'data' => [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'project_id' => $project->id,
                    'email_sent' => $emailSent,
                ],
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'inviteUser', $project);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إرسال الدعوة. يرجى المحاولة مرة أخرى لاحقاً.',
            ], 500);
        }
    }

    /**
     * الحصول على اسم الدور باللغة العربية
     */
    private function getRoleName($role)
    {
        $roles = [
            'manager' => 'مدير',
            'member' => 'عضو',
            'viewer' => 'مشاهد',
        ];

        return $roles[$role] ?? $role;
    }

    /**
     * قبول دعوة للانضمام إلى مشروع
     */
    public function acceptInvitation(Request $request, $token)
    {
        try {
            // البحث عن الدعوة باستخدام الرمز
            $invitation = DB::table('project_user')
                ->where('invite_token', $token)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            if (! $invitation) {
                return redirect()->route('dashboard')->with('error', 'الدعوة غير صحيحة أو منتهية الصلاحية');
            }

            // التحقق من أن المستخدم الحالي هو نفسه المستخدم المدعو
            if (Auth::user()->email !== $invitation->email) {
                return redirect()->route('dashboard')->with('error', 'غير مخول لقبول هذه الدعوة');
            }

            // التحقق من أن المستخدم غير منضم بالفعل (باستثناء صفّ الدعوة الحالية نفسها،
            // فهي تحمل user_id المدعو منذ إنشائها، فتُطابِق هذا الاستعلام خطأً)
            $existingMembership = DB::table('project_user')
                ->where('project_id', $invitation->project_id)
                ->where('user_id', Auth::id())
                ->where('status', 'active')
                ->where('id', '!=', $invitation->id)
                ->first();

            if ($existingMembership) {
                // تحديث الدعوة إلى حالة منتهية
                DB::table('project_user')
                    ->where('id', $invitation->id)
                    ->update([
                        'status' => 'expired',
                        'updated_at' => now(),
                    ]);

                return redirect()->route('projects.show', $invitation->project_id)
                    ->with('error', 'أنت منضم بالفعل إلى هذا المشروع');
            }

            // تحديث الدعوة لتصبح عضوية نشطة
            DB::table('project_user')
                ->where('id', $invitation->id)
                ->update([
                    'user_id' => Auth::id(),
                    'status' => 'active',
                    'joined_at' => now(),
                    'updated_at' => now(),
                ]);

            ProjectAccess::flush();

            // Set this project as active for the user
            Auth::user()->update(['active_project_id' => $invitation->project_id]);

            return redirect()->route('projects.show', $invitation->project_id)
                ->with('success', 'تم الانضمام إلى المشروع بنجاح');

        } catch (\Exception $e) {
            Log::error('خطأ في قبول الدعوة: '.$e->getMessage(), [
                'token' => $token,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()->route('dashboard')->with('error', 'حدث خطأ أثناء قبول الدعوة');
        }
    }

    /**
     * رفض دعوة للانضمام إلى مشروع
     */
    public function declineInvitation(Request $request, $token)
    {
        try {
            // البحث عن الدعوة باستخدام الرمز
            $invitation = DB::table('project_user')
                ->where('invite_token', $token)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            if (! $invitation) {
                return redirect()->route('dashboard')->with('error', 'الدعوة غير صحيحة أو منتهية الصلاحية');
            }

            // التحقق من أن المستخدم الحالي هو نفسه المستخدم المدعو
            if (Auth::user()->email !== $invitation->email) {
                return redirect()->route('dashboard')->with('error', 'غير مخول لرفض هذه الدعوة');
            }

            // تحديث الدعوة إلى حالة مرفوضة
            DB::table('project_user')
                ->where('id', $invitation->id)
                ->update([
                    'status' => 'declined',
                    'updated_at' => now(),
                ]);

            return redirect()->route('dashboard')->with('success', 'تم رفض الدعوة بنجاح');

        } catch (\Exception $e) {
            Log::error('خطأ في رفض الدعوة: '.$e->getMessage(), [
                'token' => $token,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()->route('dashboard')->with('error', 'حدث خطأ أثناء رفض الدعوة');
        }
    }

    /**
     * API: حذف المشروع
     */
    public function destroy(Project $project)
    {
        ProjectAccess::authorizeManage($project);

        try {
            // الاسم والمعرّف يُلتقطان قبل الحذف — بعده لا يبقى ما يُكتب
            // في السطر ولا مرجعٌ يُفتح منه
            $title = $project->title;
            $projectId = (int) $project->id;
            $counts = [];

            /*
                لا مفاتيح أجنبية على `tasks.project_id` ولا `sprints.project_id`
                ولا `project_user` — فكان المشروع يُحذف وحده، وتبقى مهامّه
                وسبرنتاته وعضوياته تُعدّ في التقارير باسم مشروعٍ لا وجود له.
            */
            DB::transaction(function () use ($project, &$counts) {
                $taskIds = Task::where('project_id', $project->id)->pluck('id');

                foreach ($taskIds->chunk(500) as $chunk) {
                    DB::table('task_user')->whereIn('task_id', $chunk)->delete();
                    Comment::where('commentable_type', Task::class)->whereIn('commentable_id', $chunk)->delete();
                }

                $counts['tasks'] = Task::where('project_id', $project->id)->delete();
                $counts['sprints'] = Sprint::where('project_id', $project->id)->delete();

                Comment::where('commentable_type', Project::class)->where('commentable_id', $project->id)->delete();
                DB::table('project_user')->where('project_id', $project->id)->delete();
                DB::table('task_settings')->where('project_id', $project->id)->delete();
                User::where('active_project_id', $project->id)->update(['active_project_id' => null]);

                $project->delete();
            });

            ProjectAccess::flush();

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $projectId,
                sprintf(
                    'حُذف المشروع **%s** ومعه **%d** مهمّة و**%d** سبرنت',
                    $title,
                    $counts['tasks'] ?? 0,
                    $counts['sprints'] ?? 0,
                ),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المشروع بنجاح',
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $project);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المشروع',
            ], 500);
        }
    }

    /**
     * مزامنة أعضاء الفريق من نموذج المشروع — بالفرق لا بالمسح والإعادة.
     *
     * كان التعديل يحذف كل صفٍّ دوره `member` ثم يُدرج المختارين: فيضيع من
     * انضمّ بدعوة (بريدُه وتاريخ انضمامه) وتُمحى الدعوات المعلّقة، ويصطدم
     * الإدراج بالقيد الفريد إن كان أحد المختارين مديراً أو مراقباً.
     *
     * النموذج يدير الأعضاء النشطين بدور `member` وحدهم: المديرون
     * والمراقبون والدعوات المعلّقة لا يمسّها.
     *
     * @param  array<int, int|string>  $memberIds
     */
    private function syncMembers(Project $project, array $memberIds): void
    {
        $desired = array_values(array_unique(array_filter(
            array_map('intval', $memberIds),
            fn (int $id) => $id && $id !== (int) $project->created_by,
        )));

        $rows = DB::table('project_user')->where('project_id', $project->id)->get()->keyBy('user_id');

        // من أُزيل من القائمة — العضو النشط بدور `member` فقط
        $removable = $rows
            ->filter(fn ($row) => $row->role === 'member' && $row->status === 'active' && ! in_array((int) $row->user_id, $desired, true))
            ->pluck('id');

        if ($removable->isNotEmpty()) {
            DB::table('project_user')->whereIn('id', $removable)->delete();
        }

        foreach ($desired as $userId) {
            $row = $rows->get($userId);

            if (! $row) {
                DB::table('project_user')->insert([
                    'project_id' => $project->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($row->status !== 'active') {
                // اختياره في النموذج يُغني عن دعوته المعلّقة أو المرفوضة
                DB::table('project_user')->where('id', $row->id)->update([
                    'status' => 'active',
                    'invite_token' => null,
                    'joined_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        ProjectAccess::flush();
    }
}
