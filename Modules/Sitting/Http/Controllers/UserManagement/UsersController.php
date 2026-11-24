<?php

namespace Modules\Sitting\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Log;
use App\Models\User;
use App\Support\OperationLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Sitting\Services\UserAdministrationGuard;
use Spatie\Permission\Models\Role;

/**
 * إدارة المستخدمين — الدور والفرع علاقتان إلزاميتان لا حقلا نصّ حرّ.
 *
 * `role_id` هو مصدر الحقيقة لدور المستخدم؛ الكتابة إليه (وإلى ما يُشتقّ
 * منه: عمود `role` النصي القديم ودور Spatie الفعلي) تمرّ حصراً عبر
 * `User::assignPrimaryRole()` — لا كتابة يدوية متفرّقة هنا.
 */
class UsersController extends Controller
{
    public function __construct(protected UserAdministrationGuard $guard)
    {
        $this->middleware('permission.role:settings.users.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:settings.users.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:settings.users.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:settings.users.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $query = User::with(['primaryRole', 'branch'])->orderByDesc('id');

        if ($request->filled('keywords')) {
            $keywords = $request->keywords;
            $query->where(function ($q) use ($keywords) {
                $q->where('name', 'like', "%{$keywords}%")
                    ->orWhere('email', 'like', "%{$keywords}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $users = $query->paginate(\Modules\Sitting\Support\PageSize::get(20))->withQueryString();
        $roles = Role::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        return view('sitting::users.index', compact('users', 'roles', 'branches'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        return view('sitting::users.create', compact('roles', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:30',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => 'required|exists:branches,id',
        ], [
            'role_id.required' => 'يرجى اختيار دور المستخدم.',
            'branch_id.required' => 'يرجى اختيار فرع المستخدم.',
        ]);

        $role = Role::findOrFail($data['role_id']);

        // لا يُنشأ مستخدمٌ بدورٍ فوق دور من ينشئه — وإلّا صنع لنفسه حسابَ مالك
        $this->guard->assertCanAssignRole($request->user(), $role);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'branch_id' => $data['branch_id'],
            ]);

            $user->assignPrimaryRole($role);

            OperationLog::record(
                'created',
                'user_management_log',
                $user->id,
                'تم إنشاء المستخدم **'.$user->name.'** بدور **'.$role->name.'**'
            );

            DB::commit();

            return redirect()
                ->route('settings.users.index')
                ->with(['success' => 'تم إنشاء المستخدم بنجاح']);
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'user_management_log', 'store');

            return back()->withInput()->with(['error' => 'حدث خطأ أثناء إنشاء المستخدم: '.$e->getMessage()]);
        }
    }

    public function show($id)
    {
        $user = User::with(['primaryRole', 'branch', 'roles.permissions'])->findOrFail($id);

        // الصلاحيات الفعلية: ما تمنحه أدواره وما مُنح له مباشرةً معاً
        $permissions = $user->getAllPermissions()->sortBy('name')->values();

        $logs = Log::where('type', 'user_management_log')
            ->where('type_id', $user->id)
            ->with('user')
            ->latest()
            ->get()
            ->groupBy(fn ($log) => $log->created_at->format('Y-m-d'));

        return view('sitting::users.show', compact('user', 'permissions', 'logs'));
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();

        return view('sitting::users.edit', compact('user', 'roles', 'branches'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:30',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => 'required|exists:branches,id',
        ], [
            'role_id.required' => 'يرجى اختيار دور المستخدم.',
            'branch_id.required' => 'يرجى اختيار فرع المستخدم.',
        ]);

        $role = Role::findOrFail($data['role_id']);
        $actor = $request->user();

        /*
            ثلاثةُ فحوصٍ قبل أيّ كتابة — انظر [UserAdministrationGuard]:
            من يُعدَّل (لا مستخدمَ فوق المعدِّل: تغييرُ بريده وكلمة مروره
            استيلاءٌ على حسابه)، وما يُسنَد (لا دورَ فوق المعدِّل)، وتغييرُ
            الدور نفسُه (لا لنفسه، ولا لآخر مالك).
        */
        $this->guard->assertCanManageUser($actor, $user);

        if ((int) $user->role_id !== (int) $role->id) {
            $this->guard->assertCanAssignRole($actor, $role);
            $this->guard->assertRoleChangeAllowed($actor, $user, $role);
        }

        DB::beginTransaction();

        try {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->phone = $data['phone'] ?? null;
            $user->branch_id = $data['branch_id'];

            if ($request->filled('password')) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();
            $user->assignPrimaryRole($role);

            OperationLog::record(
                'updated',
                'user_management_log',
                $user->id,
                'تم تعديل المستخدم **'.$user->name.'** — الدور الآن **'.$role->name.'**'
            );

            DB::commit();

            return redirect()
                ->route('settings.users.index')
                ->with(['success' => 'تم تعديل المستخدم بنجاح']);
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'user_management_log', 'update');

            return back()->withInput()->with(['error' => 'حدث خطأ أثناء تعديل المستخدم: '.$e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        try {
            $this->guard->assertCanDeleteUser(auth()->user(), $user);
        } catch (ValidationException $e) {
            return back()->with(['error' => collect($e->errors())->flatten()->first()]);
        }

        $name = $user->name;

        /*
            الحذفُ نهائيّ، ومستخدمٌ له فواتيرُ أو ورديّاتٌ تمنع قاعدةُ البيانات
            حذفَه. كان ذلك يخرج صفحةَ خطأٍ خام؛ صار رسالةً تقول ما العمل.
        */
        try {
            $user->delete();
        } catch (QueryException $e) {
            OperationLog::failure($e, 'user_management_log', 'destroy');

            return back()->with(['error' => 'لا يمكن حذف «'.$name.'»: له مستنداتٌ مسجَّلة باسمه في النظام. غيّر دوره أو كلمة مروره لإيقاف دخوله بدل حذفه.']);
        }

        OperationLog::record('deleted', 'user_management_log', $id, 'تم حذف المستخدم **'.$name.'**');

        return redirect()
            ->route('settings.users.index')
            ->with(['success' => 'تم حذف المستخدم بنجاح']);
    }
}
