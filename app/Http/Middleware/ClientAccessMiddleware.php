<?php

namespace App\Http\Middleware;

use App\Helpers\ClientSettingsHelper;
use App\Support\ClientPortalPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Client\Http\Controllers\ClientPortalPreviewController;
use Symfony\Component\HttpFoundation\Response;

/**
 * حصرُ حساب العميل في بوابته.
 *
 * ── ما كان معطّلاً ──
 *
 * كان الحارس يفحص `hasRoleName('client')` وقائمةَ سماحٍ من ستّة أسماء
 * مكتوبةً في الكود. وثلاثةُ أشياء كانت تُبطله بالكامل:
 *
 *   ١. دورٌ اسمه `client` **غير موجود** في جدول الأدوار إطلاقاً، وحسابات
 *      العملاء الخمسة والعشرون كلُّها بلا `role_id` — فالشرط `false` دوماً
 *      والحارس لا يمنع أحداً قط.
 *   ٢. قائمة السماح مستقلّةٌ عن شاشة «صلاحيات العميل»، فتُطفأ ميزةٌ في
 *      الشاشة ويبقى راوتها مسموحاً هنا.
 *   ٣. راوتات البوابة معرَّفةٌ مرّتين، والنسخة الفائزة (في موديول العملاء)
 *      بلا هذا الحارس أصلاً.
 *
 * ── ما صار ──
 *
 * **الدور يُختار من الإعدادات** (`client_portal_settings.portal_role_id`)
 * لا يُثبَّت اسمُه هنا؛ ومعه شرطُ `client_id` — راجع
 * `ClientSettingsHelper::isClientPortalUser()`.
 *
 * **قائمة السماح تُشتقّ من الشاشة** (`ClientPortalPermissions::allowedRoutes()`)
 * لا تُكتب هنا: أطفئ «مشاهدة فواتيره» فيُغلق راوتها في اللحظة نفسها.
 *
 * ── حالتان تُنهيان الجلسة بدل إعادة التوجيه ──
 *
 * إن أُقفلت البوابة كلُّها (المفتاح الرئيسي مطفأ) أو لم يبقَ للعميل راوتٌ
 * واحدٌ مسموح، فإعادةُ توجيهه إلى صفحةٍ ممنوعةٍ هي الأخرى حلقةٌ لا تنتهي.
 * يُسجَّل خروجه برسالةٍ صريحة — وهذا هو الفرق بين «مقفلٌ» و«معطوب».
 */
class ClientAccessMiddleware
{
    /**
     * مساراتٌ تمرّ دوماً مهما كان صاحبُ الجلسة — على قياس `$except` في
     * `EnsureSubscription` و`CheckSystemUpdates`.
     *
     * ليست تساهلاً بل شرطُ ألّا يصير الحصرُ حلقةً مغلقة: هذا الحارس في
     * المجموعة العامّة، وبعده حارسا الاشتراك والتحديث. فلو حوّل أحدُهما
     * العميلَ إلى شاشة الاشتراك أو التحديث ثم رددناه نحن إلى البوابة،
     * تقاذفاه إلى الأبد. والخروجُ خاصّةً يجب أن يبقى مفتوحاً دائماً —
     * حسابٌ لا يستطيع صاحبُه الخروج منه حسابٌ محبوس.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'login',
        'logout',
        'register',
        'password/*',
        'forgot-password',
        'reset-password/*',
        'subscription',
        'subscription/*',
        'subscription-required',
        'system-update',
        'system-update/*',
        'up',
        'lang/*',
        'locale/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        /* جلسةُ «معاينة كعميل» تُحصَر كحساب عميلٍ مهما كان دورُ الحساب
           المُنتحَل — وإلّا صارت المعاينةُ تعرض النظام كلَّه (إن كان حساب
           العميل بلا الدور المختار) فلا تجيب سؤالها الوحيد: «ماذا يرى
           عميلي؟». ولو كان الحساب مُسنَداً بالخطأ إلى دورٍ إداري، لم تصر
           المعاينةُ باباً خلفياً. */
        $inPreview = $request->session()->has(ClientPortalPreviewController::SESSION_KEY)
            && $user?->client_id !== null;

        // ليس حساب عميل (أو لم يُختَر دور العميل بعد) — لا شأن لهذا الحارس به
        if (! $inPreview && ! ClientSettingsHelper::isClientPortalUser($user)) {
            return $next($request);
        }

        // طريقُ العودة من المعاينة قبل كل شيء: حصرٌ يبتلع مخرجَه حبسٌ
        if ($request->routeIs('clients.portal.stop')) {
            return $next($request);
        }

        if ($this->isExcepted($request)) {
            return $next($request);
        }

        if (! ClientPortalPermissions::portalEnabled()) {
            return $this->deny($request, 'بوابة العملاء مغلقة حالياً. راجع إدارة النظام.');
        }

        $allowed = ClientPortalPermissions::allowedRoutes();

        if ($allowed === []) {
            return $this->deny($request, 'لا توجد صلاحيات مفعّلة لحسابك في البوابة. راجع إدارة النظام.');
        }

        $current = Route::currentRouteName();

        if (in_array($current, $allowed, true)) {
            return $next($request);
        }

        $landing = ClientPortalPermissions::landingRoute();

        // العميل واقفٌ على صفحة هبوطه أصلاً ومع ذلك مُنع — لا تُعِده إليها
        if ($landing === null || $landing === $current) {
            return $this->deny($request, 'لا توجد صفحةٌ متاحةٌ لحسابك في البوابة. راجع إدارة النظام.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرّح لك بالدخول إلى هذه الصفحة.',
            ], 403);
        }

        return redirect()->route($landing)
            ->with('error', 'غير مصرّح لك بالدخول إلى هذه الصفحة.');
    }

    /**
     * أهذا المسار من المستثناة؟ يُقارَن بعد نزع بادئة اللغة، إذ كل مسارات
     * `web` هنا مسبوقةٌ بها (`/ar/logout`) فمقارنةٌ بلا نزعٍ لا تطابق شيئاً.
     */
    private function isExcepted(Request $request): bool
    {
        $path = ltrim($request->path(), '/');
        $segments = explode('/', $path);
        $locales = array_keys(config('laravellocalization.supportedLocales') ?? []);

        if (isset($segments[0]) && in_array($segments[0], $locales, true)) {
            array_shift($segments);
            $path = implode('/', $segments);
        }

        foreach ($this->except as $pattern) {
            if ($path === $pattern || fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * إنهاء الجلسة برسالة — للحالات التي لا وجهةَ صالحةَ فيها، فإعادةُ
     * التوجيه فيها حلقةٌ مغلقة لا حمايةٌ.
     */
    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', $message);
    }
}
