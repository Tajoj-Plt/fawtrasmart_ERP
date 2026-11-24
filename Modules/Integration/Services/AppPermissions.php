<?php

namespace Modules\Integration\Services;

use App\Models\Employee;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * صلاحيات المستخدم كما يحتاجها تطبيق الجوال لإظهار الشاشات والأزرار
 * أو إخفائها.
 *
 * **المصدر هو المسارات نفسها لا قائمةٌ مكتوبة باليد:** كل مسارٍ تحت
 * `integration.*` يحمل ميدل وير `permission.role:...`، فتُجمع أسماء
 * الصلاحيات منها. فمسارٌ جديد يُضاف (مالية، مخزون، مبيعات…) تظهر صلاحيته
 * هنا تلقائياً، ولا يمكن أن يَعرض التطبيقُ زرّاً يرفضه الخادم أو العكس.
 *
 * والفحص نفسه `hasRolePermission()` الذي يحرس المسارات — فالقيمة هنا
 * عين ما سيقرّره الخادم عند الطلب.
 */
class AppPermissions
{
    /** @var array<int, string>|null */
    private static ?array $catalog = null;

    /**
     * @return array{permissions: array<string, bool>, data_scope: string, manages_employees: bool}
     */
    public static function forUser(User $user): array
    {
        // عند الدخول لا مستخدمَ مصادَقاً بعد، و`DataScope` يقرأ المستخدم الحالي
        if (Auth::id() !== $user->id) {
            Auth::setUser($user);
            DataScope::flush();
        }

        // نفس مصدر hasRolePermission() — صلاحيات الدور الأساسي — باستعلامٍ واحد لا استعلامٍ لكل صلاحية
        $granted = array_flip(self::rolePermissionNames($user));

        $permissions = [];
        foreach (self::catalog() as $name) {
            $permissions[$name] = isset($granted[$name]);
        }

        return [
            'permissions' => $permissions,
            'data_scope' => DataScope::level(),
            'manages_employees' => $user->employee_id
                ? Employee::where('direct_manager_id', $user->employee_id)->exists()
                : false,
        ];
    }

    /**
     * أسماء كل الصلاحيات التي تحرس مسارات واجهة الجوال.
     *
     * @return array<int, string>
     */
    public static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $names = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            // بالعنوان لا بالاسم: بادئة الاسم (`api.`) يضعها مزوّد المسارات لا هذا الملف
            if (! str_contains($route->uri(), 'integration/v1/')) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission.role:')) {
                    foreach (explode('|', substr($middleware, strlen('permission.role:'))) as $name) {
                        $names[$name] = true;
                    }
                }
            }
        }

        $names = array_keys($names);
        sort($names);

        return self::$catalog = $names;
    }

    /** @return array<int, string> */
    private static function rolePermissionNames(User $user): array
    {
        $role = $user->primaryRole;

        return $role ? $role->permissions()->pluck('name')->all() : [];
    }
}
