<?php

namespace Modules\Integration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة واجهة الجوال: المناديب/الموظفون فقط.
 *
 * الفحص بارتباط الحساب بسجلّ موظف (isEmployee ← employee_id) لا بعمود
 * `role` النصي — انظر تعليق User::isEmployee().
 */
class EnsureEmployeeUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الواجهة مخصّصة لحسابات الموظفين فقط.',
            ], 403);
        }

        return $next($request);
    }
}
