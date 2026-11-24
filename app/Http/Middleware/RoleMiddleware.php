<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, $role)
    {
        $user = auth()->user();

        // ✅ role_id/primaryRole بدلاً من عمود role النصي؛ يدعم بدائل مفصولة بـ| كـ CheckPermissionRole
        if ($user) {
            $roles = str_contains($role, '|') ? explode('|', $role) : [$role];

            foreach ($roles as $candidate) {
                if ($user->hasRoleName($candidate)) {
                    return $next($request);
                }
            }
        }

        return redirect('/login'); // أو صفحة خطأ
    }

}
