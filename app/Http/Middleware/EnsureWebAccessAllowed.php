<?php

namespace App\Http\Middleware;

use App\Support\RoleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * دورٌ محصورٌ في التطبيق لا يتصفّح الموقع.
 *
 * رفضُ الدخول في شاشة تسجيل الدخول وحده لا يكفي: من كان داخلاً قبل أن
 * يُشعَل المفتاح تبقى جلستُه قائمةً حتى تنتهي، ومن نسخ رابطاً يفتحه. فهذا
 * الحارسُ يُغلق الجلسةَ عند **أوّل طلبٍ** بعد إشعال المفتاح.
 *
 * **على مجموعة `web` وحدها** — فواجهةُ التطبيق (`api`) هي القناةُ المقصودة
 * بالإبقاء، وحجبُها يعني حجبَ الدور عن النظام كلِّه لا عن الموقع.
 *
 * ويخرج فوراً حين لا جلسةَ أو حين لا يكون الدورُ محصوراً — وهو الغالب في
 * كلّ طلب: قراءةٌ واحدةٌ من `roles` محفوظةٌ لعمر الطلب في {@see RoleAccess}.
 *
 * ومسارُ تسجيل الخروج مستثنى: من حُجب يُسجَّل خروجُه هنا أصلاً، ولو حُجب
 * المسارُ نفسُه لدار الطلبُ على نفسه.
 */
class EnsureWebAccessAllowed
{
    /** مساراتٌ تعمل قبل الحجب أو بعده — لا تُحجب كي لا يدور الطلب على نفسه. */
    private const ALLOWED = [
        'logout',
        'login',
        '*/logout',
        '*/login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || $request->is(self::ALLOWED) || ! RoleAccess::mobileOnly()) {
            return $next($request);
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // طلبُ AJAX لا يُحوَّل: يُردّ بـ403 ليعرف الطرفُ الآخر أنّ الجلسة أُغلقت
        if ($request->expectsJson()) {
            return response()->json(['message' => RoleAccess::MOBILE_ONLY_NOTICE], 403);
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => RoleAccess::MOBILE_ONLY_NOTICE]);
    }
}
