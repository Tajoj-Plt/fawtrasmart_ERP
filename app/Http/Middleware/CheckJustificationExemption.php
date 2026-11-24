<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckJustificationExemption
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // التحقق من أن المستخدم مسجل الدخول
        if (!Auth::check()) {
            return $next($request);
        }

        // التحقق من ما إذا كان المستخدم معفى من تقديم التبريرات
        $user = Auth::user();
        if (hasPermission('staff_visit_justification_exempt')) {
            return redirect()->route('dashboard')
                ->with('info', '✅ أنت معفى من تقديم تبريرات الزيارات غير المكتملة.');
        }

        return $next($request);
    }
}
