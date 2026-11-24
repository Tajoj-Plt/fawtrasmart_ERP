<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\notifications;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\TaskManager\Services\ProjectAccess;

class WorkspaceController extends Controller
{
    /** وحدة السجلّ — بها يربط `logs`/`event_logs` أسطرَ هذه الشاشة. */
    private const LOG_MODULE = 'TaskManager.Workspace';

    public function __construct()
    {
        /*
            حارسٌ في المتحكّم فوق حارس المسار — كما في
            [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
            الصلاحية صفةُ الإجراء لا صفةُ المسار، ومسارٌ جديدٌ يُنسى حارسُه
            لا يفتح الإجراء.
        */
        $this->middleware('permission.role:tasks.workspaces.view', ['only' => ['index']]);
        $this->middleware('permission.role:tasks.workspaces.show', ['only' => ['show']]);
        $this->middleware('permission.role:tasks.workspaces.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:tasks.workspaces.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:tasks.workspaces.delete', ['only' => ['destroy']]);
    }

    /**
     * عرض قائمة مساحات العمل
     */
    public function index(Request $request)
    {
        // جلب المستخدمين المسؤولين لقائمة الفلترة
        $admins = User::all();
        if ($request->ajax() || $request->is_ajax) {
            // البحث والترشيح
            // المالك وأعضاء المساحة ومن له مشروعٌ فيها — وكانت للمالك وحده،
            // فلا يراها عضوٌ في مشروعٍ من مشاريعها
            $query = ProjectAccess::scopeWorkspaces(Workspace::query());

            // تطبيق الفلاتر
            if ($request->filled('title')) {
                $query->where('title', 'like', '%'.$request->title.'%');
            }

            if ($request->filled('admin_id')) {
                $query->where('admin_id', $request->admin_id);
            }

            if ($request->filled('is_primary')) {
                $query->where('is_primary', $request->is_primary);
            }

            if ($request->filled('projects_min')) {
                $query->whereHas('projects', function ($q) use ($request) {
                    $q->groupBy('workspace_id')
                        ->havingRaw('COUNT(*) >= ?', [$request->projects_min]);
                });
            }

            if ($request->filled('projects_max')) {
                $query->whereHas('projects', function ($q) use ($request) {
                    $q->groupBy('workspace_id')
                        ->havingRaw('COUNT(*) <= ?', [$request->projects_max]);
                });
            }

            if ($request->filled('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->where('created_at', '<=', $request->to_date);
            }

            // عدّاد المشاريع — ممّا يراه المستخدم منها لا كلّها
            $query->withCount(['projects' => fn ($projects) => ProjectAccess::scopeProjects($projects)]);

            // الترتيب والترقيم
            $perPage = min(max((int) $request->get('per_page', 10), 1), 100);
            $workspaces = $query->orderBy('is_primary', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($request->expectsJson()) {
                $view = view('taskmanager::workspaces.partials.table', compact('workspaces'))->render();

                return response()->json([
                    'success' => true,
                    'data' => $view,
                    'total' => $workspaces->total(),
                    'current_page' => $workspaces->currentPage(),
                    'last_page' => $workspaces->lastPage(),
                    'from' => $workspaces->firstItem(),
                    'to' => $workspaces->lastItem(),
                ]);
            }
        }

        $totalWorkspacesCount = ProjectAccess::scopeWorkspaces(Workspace::query())->count();
        $totalProjectsCount = ProjectAccess::scopeProjects(Project::query())->count();

        return view('taskmanager::workspaces.index', compact('admins', 'totalWorkspacesCount', 'totalProjectsCount'));
    }

    /**
     * عرض تفاصيل مساحة العمل
     */
    public function show(Workspace $workspace)
    {
        // التحقق من الصلاحيات
        if (! ProjectAccess::canViewWorkspace($workspace)) {
            return redirect()->route('workspaces.index')->with('error', 'غير مخول لعرض هذه المساحة');
        }

        // مشاريع المساحة ممّا يراه المستخدم — العضو في مشروعٍ واحد منها لا
        // يرى بقيّتها
        $workspace->load(['admin', 'projects' => fn ($projects) => ProjectAccess::scopeProjects($projects->getQuery())]);
        // الشاشة تقرأ `projects_count` لتقرّر إظهار زرّ الحذف — ولم يكن يُحمَّل
        $workspace->loadCount('projects');

        return view('taskmanager::workspaces.show', compact('workspace'));
    }

    public function create()
    {
        // جلب جميع المستخدمين باستثناء المستخدم الحالي
        $users = User::select('id', 'name', 'email')
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();

        $currentUser = Auth::user();
        $admins = User::all(); // للمستخدمين المسؤولين في صفحة الإنشاء

        return view('taskmanager::workspaces.create', compact('users', 'currentUser', 'admins'));
    }

    /**
     * عرض صفحة تعديل مساحة العمل
     */
    public function edit(Workspace $workspace)
    {
        // التأكد من أن المستخدم مخول لتعديل هذه المساحة
        if (! ProjectAccess::canManageWorkspace($workspace)) {
            return redirect()->route('workspaces.index')->with('error', 'غير مخول لتعديل هذه المساحة');
        }

        $admins = User::all(); // للمستخدمين المسؤولين في صفحة التعديل

        return view('taskmanager::workspaces.edit', compact('workspace', 'admins'));
    }

    /**
     * حفظ مساحة العمل الجديدة - بدون ربط أعضاء مباشرة
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'is_primary' => 'nullable|in:0,1',
                // إزالة validation للأعضاء
            ]);

            DB::beginTransaction();

            // إنشاء مساحة العمل
            $workspace = Workspace::create([
                'title' => $request->title,
                'description' => $request->description,
                'admin_id' => Auth::id(),
                'is_primary' => $request->is_primary == '1',
            ]);

            // إذا كانت رئيسية، إلغاء باقي المساحات الرئيسية للمستخدم الحالي
            if ($request->is_primary == '1') {
                Workspace::where('admin_id', Auth::id())
                    ->where('id', '!=', $workspace->id)
                    ->update(['is_primary' => false]);
            }

            // المالك عضوٌ في مساحته بدور `owner` — كما تفعل ترحيلة التعبئة
            // للمساحات القديمة
            $workspace->users()->syncWithoutDetaching([Auth::id() => ['role' => 'owner', 'joined_at' => now()]]);

            DB::commit();
            notifications::create([
                'user_id' => Auth::user()->id,
                'type' => 'workspace',
                'title' => $request->title.' أنشأ مساحة العمل',
                'description' => 'تم انشاء مساحة العمل',
            ]);

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $workspace,
                sprintf('أُنشئت مساحة العمل **%s**', $workspace->title),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء مساحة العمل بنجاح. يمكنك إضافة الأعضاء من خلال إنشاء مشاريع.',
                'data' => [
                    'workspace_id' => $workspace->id,
                    'redirect_url' => route('workspaces.show', $workspace->id),
                ],
            ]);

        } catch (ValidationException $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Workspace creation error: '.$e->getMessage(), [
                'admin_id' => Auth::id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء مساحة العمل. يرجى المحاولة مرة أخرى.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * تحديث مساحة العمل - بدون تحديث الأعضاء
     */
    public function update(Request $request, Workspace $workspace)
    {
        // التأكد من أن المستخدم مخول لتحديث هذه المساحة
        if (! ProjectAccess::canManageWorkspace($workspace)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مخول لتعديل هذه المساحة',
            ], 403);
        }

        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'is_primary' => 'nullable|in:0,1',
                // إزالة validation للأعضاء
            ]);

            DB::beginTransaction();

            // تحديث بيانات مساحة العمل
            $workspace->update([
                'title' => $request->title,
                'description' => $request->description,
                'is_primary' => $request->is_primary == '1',
            ]);

            // فرقُ القيم يُلتقط قبل `save()` التالي — بعده تصير القيمة
            // القديمة هي الجديدة ولا يبقى ما يُقارَن
            $changes = OperationLog::diff($workspace, ['title', 'description', 'is_primary']);

            // إذا كانت رئيسية، إلغاء باقي المساحات الرئيسية للمستخدم الحالي
            // «الرئيسية» واحدةٌ لكل مالك — مالكِ المساحة لا من يعدّلها، فالمدير
            // الذي يعدّل مساحةَ غيره كان يُسقط الصفة عن مساحاته هو
            if ($request->is_primary == '1') {
                Workspace::where('admin_id', $workspace->admin_id)
                    ->where('id', '!=', $workspace->id)
                    ->update(['is_primary' => false]);
            }

            DB::commit();

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $workspace,
                sprintf('عُدّلت مساحة العمل **%s**', $workspace->title),
                $changes,
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث مساحة العمل بنجاح',
            ]);

        } catch (ValidationException $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Workspace update error: '.$e->getMessage(), [
                'workspace_id' => $workspace->id,
                'admin_id' => Auth::id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            OperationLog::failure($e, self::LOG_MODULE, 'update', $workspace);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث مساحة العمل. يرجى المحاولة مرة أخرى.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * حذف مساحة العمل
     */
    public function destroy(Workspace $workspace)
    {
        // التحقق من الصلاحيات
        if (! ProjectAccess::canManageWorkspace($workspace)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مخول لحذف هذه المساحة',
            ], 403);
        }

        // التحقق من وجود مشاريع
        if ($workspace->projects()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مساحة العمل لأنها تحتوي على مشاريع',
            ], 422);
        }

        try {
            $title = $workspace->title;
            $workspaceId = (int) $workspace->id;

            // حذف مساحة العمل مباشرة (لا توجد علاقات users لحذفها)
            $workspace->delete();

            // المعرّف يُلتقط قبل الحذف: بعده يصير `getKey()` فارغاً فيُكتب
            // السطر بلا مرجعٍ يُفتح منه
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $workspaceId,
                sprintf('حُذفت مساحة العمل **%s**', $title),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف مساحة العمل بنجاح',
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $workspace);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف مساحة العمل',
            ], 500);
        }
    }

    // باقي الدوال الأخرى...

}
