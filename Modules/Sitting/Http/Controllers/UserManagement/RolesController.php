<?php

namespace Modules\Sitting\Http\Controllers\UserManagement;

use App\Constants\permissions\PermissionCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OperationLog;
use App\Support\PermissionLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Sitting\Services\UserAdministrationGuard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:settings.roles.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:settings.roles.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:settings.roles.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:settings.roles.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $query = Role::withCount(['permissions', 'users'])->orderBy('name');

        if ($request->filled('keywords')) {
            $query->where('name', 'like', '%'.$request->keywords.'%');
        }

        $roles = $query->paginate(\Modules\Sitting\Support\PageSize::get(20))->withQueryString();

        // إجماليات الترويسة تصف النظام كلّه لا الصفحة المعروضة ولا نتيجة البحث
        $permissionsTotal = Permission::count();
        $usersWithoutRole = User::doesntHave('roles')->count();

        return view('sitting::roles.index', compact('roles', 'permissionsTotal', 'usersWithoutRole'));
    }

    public function create()
    {
        $permissionTree = $this->permissionTree();

        return view('sitting::roles.create', compact('permissionTree'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:190|unique:roles,name',
            'unified_sidebar' => 'nullable|boolean',
            'mobile_only' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ], [
            'name.unique' => 'يوجد دورٌ بهذا الاسم مسبقاً.',
        ]);

        // لا يُنشأ دورٌ بصلاحياتٍ لا يملكها منشئُه — انظر [UserAdministrationGuard]
        app(UserAdministrationGuard::class)->assertRoleWriteAllowed(
            $request->user(), null, $data['name'], $data['permissions'] ?? [], (bool) ($data['mobile_only'] ?? false)
        );

        DB::beginTransaction();

        try {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

            /*
                إسنادٌ صريح لا عبر `create()`: هذا المتحكّم يستعمل نموذج
                Spatie لا [App\Models\Role]، و`$fillable` فيه لا يعرف
                العمود — فيسقط صامتاً لو مُرِّر في المصفوفة.
            */
            $role->unified_sidebar = (bool) ($data['unified_sidebar'] ?? false);
            // نموذج Spatie لا يُدرج العمودَ في $fillable، فالإسنادُ صريحٌ كنظيره
            $role->mobile_only = (bool) ($data['mobile_only'] ?? false);
            $role->save();

            $role->syncPermissions($data['permissions'] ?? []);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            OperationLog::record(
                'created',
                'user_management_log',
                $role->id,
                'تم إنشاء الدور **'.$role->name.'** بـ'.count($data['permissions'] ?? []).' صلاحية'
                    .($role->unified_sidebar ? ' — بقائمة جانبية موحّدة' : '')
            );

            DB::commit();

            return redirect()
                ->route('settings.roles.index')
                ->with(['success' => 'تم إنشاء الدور بنجاح']);
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'user_management_log', 'store');

            return back()->withInput()->with(['error' => 'حدث خطأ أثناء إنشاء الدور: '.$e->getMessage()]);
        }
    }

    public function show($id)
    {
        $role = Role::with(['permissions', 'users.branch'])->findOrFail($id);

        return view('sitting::roles.show', compact('role'));
    }

    public function edit($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $permissionTree = $this->permissionTree();
        $assigned = $role->permissions->pluck('name')->all();

        return view('sitting::roles.edit', compact('role', 'permissionTree', 'assigned'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190', Rule::unique('roles', 'name')->ignore($role->id)],
            'unified_sidebar' => 'nullable|boolean',
            'mobile_only' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ], [
            'name.unique' => 'يوجد دورٌ بهذا الاسم مسبقاً.',
        ]);

        /*
            قبل أيّ كتابة: لا يُمنح ما لا يملكه المعدِّل، ولا يُمسّ دورٌ فوقه،
            ولا يغيّر أحدٌ صلاحياتِ دوره هو، وأدوارُ النظام لا تُعاد تسميتُها،
            ودورُ المالك لا يُقفل على الجوال ولا يُجرَّد من إدارة المستخدمين.
        */
        app(UserAdministrationGuard::class)->assertRoleWriteAllowed(
            $request->user(), $role, $data['name'], $data['permissions'] ?? [], (bool) ($data['mobile_only'] ?? false)
        );

        DB::beginTransaction();

        try {
            $oldName = $role->name;
            $role->name = $data['name'];
            $role->unified_sidebar = (bool) ($data['unified_sidebar'] ?? false);
            // نموذج Spatie لا يُدرج العمودَ في $fillable، فالإسنادُ صريحٌ كنظيره
            $role->mobile_only = (bool) ($data['mobile_only'] ?? false);
            $role->save();
            $role->syncPermissions($data['permissions'] ?? []);

            /*
                فحص الصلاحيات (`hasRole`/`hasPermissionTo`) يقرأ جداول Spatie
                عبر `role_id` — لا يتأثّر بإعادة التسمية. لكن عمود `role`
                النصي على `users` (مرآةٌ لاسم الدور، يقرؤه عشرات الاستعلامات
                القديمة `where('role', 'employee')` مباشرةً من القاعدة) كان
                يبقى بالاسم القديم صامتاً حتى يُعدَّل كل مستخدمٍ يدوياً.
            */
            if ($oldName !== $role->name) {
                User::where('role_id', $role->id)->update(['role' => $role->name]);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            OperationLog::record(
                'updated',
                'user_management_log',
                $role->id,
                'تم تعديل الدور **'.$role->name.'** — الآن '.count($data['permissions'] ?? []).' صلاحية'
                    .', والقائمة الجانبية '.($role->unified_sidebar ? 'موحّدة' : 'حسب الوحدة')
            );

            DB::commit();

            return redirect()
                ->route('settings.roles.index')
                ->with(['success' => 'تم تعديل الدور بنجاح']);
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'user_management_log', 'update');

            return back()->withInput()->with(['error' => 'حدث خطأ أثناء تعديل الدور: '.$e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $role = Role::withCount('users')->findOrFail($id);

        try {
            app(UserAdministrationGuard::class)->assertCanDeleteRole(auth()->user(), $role);
        } catch (ValidationException $e) {
            return back()->with(['error' => collect($e->errors())->flatten()->first()]);
        }

        if ($role->users_count > 0) {
            return back()->with(['error' => 'لا يمكن حذف دورٍ مُسنَدٍ إلى '.$role->users_count.' مستخدم. انقلهم إلى دورٍ آخر أولاً.']);
        }

        $name = $role->name;
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        OperationLog::record('deleted', 'user_management_log', $id, 'تم حذف الدور **'.$name.'**');

        return redirect()
            ->route('settings.roles.index')
            ->with(['success' => 'تم حذف الدور بنجاح']);
    }

    /**
     * شجرة الصلاحيات: **قسم ← مجموعة ← صلاحية** — كما تعرضها شاشة بناء الدور.
     *
     * التقسيم يُقرأ من أعمدة `module` و`group_key` في جدول `permissions`،
     * وهي أعمدةٌ يكتبها `permissions:sync-catalog` من
     * [App\Constants\permissions\PermissionCatalog]. قبلها كان التجميع يُشتقّ من بادئة
     * الاسم، والبادئة تكذب: `inv_manage_suppliers_add` مشترياتٌ لا مخزون،
     * و`Delete_Client` عملاءُ لا «Delete». الاسم أثرُ تاريخٍ لا تصنيف.
     *
     * صفٌّ بلا `module` — صلاحيةٌ أُنشئت يدوياً ولم تُصنَّف بعد — يسقط في
     * قسم «غير مصنَّفة» في آخر الشاشة بدل أن يختفي.
     *
     * @return array<int, array{key:string, label:string, description:string, icon:string, groups:array}>
     */
    private function permissionTree(): array
    {
        $permissions = Permission::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'module', 'module_label', 'group_key', 'group_label', 'label']);

        $tree = [];

        foreach ($permissions as $permission) {
            $moduleKey = $permission->module ?: PermissionLabels::UNCLASSIFIED;
            $groupKey = $permission->group_key ?: 'other';

            if (! isset($tree[$moduleKey])) {
                $definition = PermissionCatalog::module($moduleKey);

                $tree[$moduleKey] = [
                    'key' => $moduleKey,
                    'label' => $permission->module_label ?: PermissionLabels::groupLabel($moduleKey),
                    'description' => $definition['description'] ?? PermissionLabels::groupDescription($moduleKey),
                    'icon' => $definition['icon'] ?? PermissionLabels::groupIcon($moduleKey),
                    'count' => 0,
                    'groups' => [],
                ];
            }

            if (! isset($tree[$moduleKey]['groups'][$groupKey])) {
                $moduleDefinition = PermissionCatalog::module($moduleKey) ?? [];
                $definition = $moduleDefinition['groups'][$groupKey] ?? [];
                $sectionKey = $definition['section'] ?? null;
                $sectionLabel = $sectionKey ? ($moduleDefinition['sections'][$sectionKey] ?? null) : null;

                // وحدةٌ مقسَّمة وفيها مجموعةٌ ليست في كتالوجها (صفوفٌ قديمة): تحت «أخرى»
                // لا تحت عنوان القسم الذي سبقها
                if (! $sectionKey && ! empty($moduleDefinition['sections'])) {
                    $sectionKey = '_other';
                    $sectionLabel = 'صلاحيات أخرى';
                }

                $tree[$moduleKey]['groups'][$groupKey] = [
                    'key' => $moduleKey.'--'.$groupKey,
                    'label' => $permission->group_label ?: 'صلاحيات أخرى',
                    'description' => $definition['description'] ?? '',
                    'icon' => $definition['icon'] ?? 'shield',
                    // عنوانُ القسم الفرعي (الحضور، الرواتب…) — يُطبع فوق أوّل مجموعةٍ منه
                    'section' => $sectionKey,
                    'section_label' => $sectionLabel,
                    'permissions' => [],
                ];
            }

            $tree[$moduleKey]['count']++;
            $tree[$moduleKey]['groups'][$groupKey]['permissions'][] = [
                'name' => $permission->name,
                'label' => $permission->label ?: PermissionLabels::describe($permission->name),
            ];
        }

        /*
            ترتيبُ المجموعات بترتيب الكتالوج — لا بترتيب صفوف الجدول.

            `sort_order` يُرتّب الصلاحياتِ صفّاً صفّاً، فتتداخل المجموعاتُ
            (أيامُ الحضور بين الأقسام والمسمّيات…) ويتكرّر عنوانُ القسم
            الفرعي كلّما عاد إليه الدور. فالمجموعاتُ تُرتَّب بقسمها (بترتيب
            `sections()`) ثمّ بموضعها في `groups()`، وما ليس في الكتالوج
            (صفوفٌ قديمة لم تُرحَّل) يأتي آخراً. وصلاحياتُ المجموعة الواحدة
            بترتيب تعريفها في الكتالوج أيضاً.
        */
        foreach ($tree as $moduleKey => $module) {
            $definition = PermissionCatalog::module($moduleKey) ?? [];
            $catalogGroups = array_keys($definition['groups'] ?? []);
            $sectionOrder = array_flip(array_keys($definition['sections'] ?? []));

            if ($catalogGroups === []) {
                continue;
            }

            $groupOrder = array_flip($catalogGroups);
            $groups = $module['groups'];

            uksort($groups, function ($a, $b) use ($groups, $groupOrder, $sectionOrder) {
                $rank = fn ($key) => [
                    isset($groupOrder[$key]) ? 0 : 1,
                    $sectionOrder[$groups[$key]['section'] ?? ''] ?? PHP_INT_MAX,
                    $groupOrder[$key] ?? PHP_INT_MAX,
                    $key,
                ];

                return $rank($a) <=> $rank($b);
            });

            foreach ($groups as $groupKey => $group) {
                $permissionOrder = array_flip(array_keys($definition['groups'][$groupKey]['permissions'] ?? []));
                if ($permissionOrder !== []) {
                    usort($group['permissions'], fn ($x, $y) => [$permissionOrder[$x['name']] ?? PHP_INT_MAX, $x['name']]
                        <=> [$permissionOrder[$y['name']] ?? PHP_INT_MAX, $y['name']]);
                    $groups[$groupKey] = $group;
                }
            }

            $tree[$moduleKey]['groups'] = $groups;
        }

        // «غير مصنَّفة» آخر الشاشة دائماً — ما بعدها لا شيء
        if (isset($tree[PermissionLabels::UNCLASSIFIED])) {
            $unclassified = $tree[PermissionLabels::UNCLASSIFIED];
            unset($tree[PermissionLabels::UNCLASSIFIED]);
            $tree[PermissionLabels::UNCLASSIFIED] = $unclassified;
        }

        return array_values($tree);
    }
}
