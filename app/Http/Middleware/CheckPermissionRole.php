<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ميدل وير التحقّق من الصلاحية — مباشرةً عبر جداول النظام الجديد، لا Spatie.
 *
 * السلسلة عبر `User::hasRolePermission()`: `users.role_id` → دورٌ واحد
 * (`App\Models\Role`) → صلاحياته عبر `role_has_permissions`
 * (`App\Models\Role::permissions()`) → `App\Models\Permission`. لا
 * `model_has_roles` ولا `model_has_permissions` — كل صلاحيةٍ مصدرُها دور
 * المستخدم وحده، لا منحٌ مباشرٌ له خارج دوره، ولا تعدّد أدوار.
 *
 * الاستعمال: `middleware('permission.role:settings.users.view')`. لا دور
 * متجاوز إطلاقاً — من يحتاج الوصول الكامل يُمنح دورُه كل الصلاحيات صراحةً
 * في `role_has_permissions` (كما يفعل `RolesSeeder` مع `owner`/`manager`/
 * `admin`)، لا عبر استثناءٍ في هذا الميدل وير.
 *
 * **قائمة صلاحيات بديلة**: `permission.role:a|b|c` — يكفي أن تكون واحدةٌ
 * منها ضمن صلاحيات دور المستخدم. مطلوبةٌ لشاشاتٍ ورثت هذا النمط من النظام
 * القديم (كالمنتجات: عرض الكل، أو عرض ما أضافه هو، أو عرض ما في مستودعه).
 *
 * مستخدمٌ بلا دورٍ (`role_id` فارغ) يُرفض دائماً — لا صلاحية بلا دور.
 */
class CheckPermissionRole
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.');
        }

        if (! $user->hasRolePermission($permission)) {
            abort(403, 'ليست لديك صلاحية الوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}
