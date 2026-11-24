<?php

namespace Modules\Sitting\Http\Controllers\UserManagement;

use App\Constants\permissions\PermissionCatalog;
use App\Http\Controllers\Controller;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * إدارة صلاحياتٍ فردية.
 *
 * أغلب صلاحيات النظام تُزرع من كتالوجات الوحدات (`{Module}Permissions`) لا
 * من هذه الشاشة — هذه الشاشة لإضافة/تعديل/حذف صلاحيةٍ فردية يدوياً حين
 * يلزم ذلك، لا بديلاً عن الكتالوجات.
 */
class PermissionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:settings.permissions.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:settings.permissions.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:settings.permissions.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:settings.permissions.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        // الترتيب بـ`sort_order` فتخرج الصلاحيات مجمَّعةً بأقسامها كما في
        // الكتالوج، لا مبعثرةً أبجدياً بأسمائها الخام
        $query = Permission::withCount('roles')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('keywords')) {
            $keywords = $request->keywords;

            // البحث بالتسمية العربية كما بالاسم الخام — المسؤول يعرف
            // «إضافة مورد» ولا يعرف `inv_manage_suppliers_add`
            $query->where(function ($q) use ($keywords) {
                $q->where('name', 'like', "%{$keywords}%")
                    ->orWhere('label', 'like', "%{$keywords}%");
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        $permissions = $query->paginate(\Modules\Sitting\Support\PageSize::get(30))->withQueryString();

        $modules = collect(PermissionCatalog::modules())
            ->mapWithKeys(fn ($module) => [$module['key'] => $module['label']])
            ->all();

        // إجماليات الترويسة تصف النظام كلّه لا الصفحة المعروضة ولا نتيجة البحث
        $permissionsTotal = Permission::count();
        $rolesTotal = Role::count();
        $unassignedTotal = Permission::doesntHave('roles')->count();

        return view('sitting::permissions.index', compact(
            'permissions',
            'modules',
            'permissionsTotal',
            'rolesTotal',
            'unassignedTotal'
        ));
    }

    public function create()
    {
        return view('sitting::permissions.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:190|unique:permissions,name',
        ], [
            'name.unique' => 'توجد صلاحيةٌ بهذا الاسم مسبقاً.',
        ]);

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        OperationLog::record('created', 'user_management_log', $permission->id, 'تم إنشاء الصلاحية **'.$permission->name.'**');

        return redirect()
            ->route('settings.permissions.index')
            ->with(['success' => 'تم إنشاء الصلاحية بنجاح']);
    }

    public function edit($id)
    {
        $permission = Permission::withCount('roles')->findOrFail($id);

        return view('sitting::permissions.edit', compact('permission'));
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190', Rule::unique('permissions', 'name')->ignore($permission->id)],
        ], [
            'name.unique' => 'توجد صلاحيةٌ بهذا الاسم مسبقاً.',
        ]);

        $permission->name = $data['name'];
        $permission->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        OperationLog::record('updated', 'user_management_log', $permission->id, 'تم تعديل اسم الصلاحية إلى **'.$permission->name.'**');

        return redirect()
            ->route('settings.permissions.index')
            ->with(['success' => 'تم تعديل الصلاحية بنجاح']);
    }

    public function destroy($id)
    {
        $permission = Permission::withCount('roles')->findOrFail($id);

        if ($permission->roles_count > 0) {
            return back()->with(['error' => 'لا يمكن حذف صلاحيةٍ مُسنَدة إلى '.$permission->roles_count.' دور. انزعها من الأدوار أولاً.']);
        }

        $name = $permission->name;
        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        OperationLog::record('deleted', 'user_management_log', $id, 'تم حذف الصلاحية **'.$name.'**');

        return redirect()
            ->route('settings.permissions.index')
            ->with(['success' => 'تم حذف الصلاحية بنجاح']);
    }
}
