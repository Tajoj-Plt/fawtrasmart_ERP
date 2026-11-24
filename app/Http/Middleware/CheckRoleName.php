<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ميدل وير التحقّق من **اسم الدور الأساسي** — من `users.role_id`، لا Spatie.
 *
 * كان الاسم المستعار `role` يشير إلى `Spatie\Permission\Middleware\
 * RoleMiddleware`، وهي تسأل `hasAnyRole()` فتقرأ `model_has_roles`. وهذا
 * الجدولُ ليس مصدرَ الحقيقة في النظام: `users.role_id` هو، وعليه يقوم
 * `hasRolePermission()` و`CheckPermissionRole` وخطّافُ `Gate::before` في
 * `AppServiceProvider` — أي كلُّ فحص صلاحيةٍ في النظام إلا هذا.
 *
 * فحين ينحرف العمودان (وقد انحرفا فعلاً: حسابٌ `role_id` يقول «manager»
 * و`model_has_roles` تقول «owner») يصير المستخدمُ مديراً في كل شاشةٍ
 * ومحروماً في المسارات المحروسة بـ`role:manager` وحدها — وهي حفنةُ
 * مساراتٍ في التقارير وتبريرات الزيارات، فيبدو المنعُ عشوائياً بلا سبب.
 *
 * الاستعمال كما هو: `middleware('role:manager')` أو `role:admin|manager`.
 * مستخدمٌ بلا `role_id` يُرفض دائماً — لا دورَ له.
 */
class CheckRoleName
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.');
        }

        $candidates = array_filter(array_map('trim', explode('|', $roles)));

        foreach ($candidates as $name) {
            if ($user->hasRoleName($name)) {
                return $next($request);
            }
        }

        abort(403, 'ليست لديك صلاحية الوصول إلى هذه الصفحة.');
    }
}
