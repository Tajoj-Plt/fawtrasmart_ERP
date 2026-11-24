<?php

namespace App\Http\Middleware;

use App\Constants\planFeatures\ApplicationPlanFeatures;
use App\Services\Fawtra\SubscriptionGate;
use App\Services\Settings\ApplicationRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * منع الوصول إلى وحدة معطَّلة من شاشة التطبيقات.
 *
 * كان التعطيل يُخفي بند القائمة الجانبية ولا شيء غير ذلك: لا وسيط ولا
 * مزوّد خدمة يقرأ `application_settings`. جُرِّب فعلياً — عُطِّلت «نقاط
 * البيع» ثم فُتح `/programs/mobile-pos` فاستجاب 200. أي أن من يعرف
 * الرابط يدخل وحدةً يظنّ صاحب النظام أنه أقفلها.
 *
 * الاستعمال — بادئة الوحدة مأخوذة من المسار تلقائياً:
 *
 *     Route::middleware('module')->group(...);
 *
 * أو بتسمية الوحدة صراحةً حين لا تطابق البادئةُ المسارَ:
 *
 *     Route::middleware('module:pos')->group(...);
 *
 * ─────────────────────── وطبقةُ الباقة معه ───────────────────────
 *
 * الوحدة تُقفل من جهتين: مسؤولُ المنشأة يعطّلها من الشاشة، أو باقةُ فوترة
 * لا تشملها أصلاً. وهما سببان مختلفان لمنعٍ واحد، فيُفرَّق بينهما في
 * الاستجابة: الأول إعادةٌ إلى الرئيسية برسالة «عطّلها مسؤولك» (يفتحها
 * المسؤول بنقرة)، والثاني شاشةُ ‎402‎ «يجب تجديد أو ترقية الباقة» بزرّ
 * الترقية — لأن لا أحد في المنشأة يملك فتحه.
 *
 * والترتيب مقصود: **الباقة أولاً**. وحدةٌ خارج الباقة لا تُفتح مهما قال
 * مفتاحُ الشاشة — بل مفتاحُها في الشاشة نفسها معطَّل مغطّى — فرسالةُ
 * «عطّلها مسؤولك» فيها تُرسل المستخدم إلى بابٍ لا يُفتح.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $module ??= ApplicationRegistry::moduleForPath($request->path());

        // مسار لا يخصّ وحدة معروفة يمرّ كما هو — الوسيط يمنع المعطَّل
        // ولا يحجب ما لا يعرفه
        if ($module === null) {
            return $next($request);
        }

        if (! ApplicationRegistry::isPermitted($module)) {
            return $this->planBlocked($request, $module);
        }

        if (ApplicationRegistry::isActive($module)) {
            return $next($request);
        }

        $label = ApplicationRegistry::labelFor($module);
        $message = __('settings.applications.blocked', ['module' => $label]);

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        // العودة إلى الصفحة السابقة برسالة أوضح من صفحة 403 عارية: المستخدم
        // لم يُمنع لنقص صلاحية بل لأن الوحدة مقفلة، والفرق يهمّه
        return redirect()
            ->route('dashboard')
            ->with('error', $message.' '.__('settings.applications.blocked_hint'));
    }

    /**
     * وحدةٌ خارج الباقة — ‎402‎ لا ‎403‎.
     *
     * الفرق ليس تجميلاً: ‎403‎ تعني «لا صلاحية لك» فيذهب المستخدم إلى
     * مسؤول الصلاحيات، و‎402‎ تعني «لم يُشترَ» فيذهب إلى فوترة. والخلط
     * بينهما يُنتج جولةً كاملة في الاتجاه الخطأ.
     *
     * والشاشة المعروضة هي شاشة [\App\Http\Middleware\EnsurePlanFeature]
     * نفسها — فيها زرّ الترقية ورابط شاشة الاشتراك — كي لا يرى المستخدم
     * شاشتَي «خارج الباقة» مختلفتين في نظامٍ واحد.
     */
    protected function planBlocked(Request $request, string $module): Response
    {
        $label = ApplicationRegistry::labelFor($module);
        $message = __('settings.applications.plan_blocked', ['module' => $label]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'code' => 'PLAN_FEATURE_UNAVAILABLE',
                'features' => ApplicationPlanFeatures::for($module),
            ], 402);
        }

        $gate = app(SubscriptionGate::class);

        return response()->view('errors.plan-feature', [
            'message' => $message,
            // أسماء الميزات كما يقرؤها المشتري في فوترة لا مفاتيحها البرمجية
            'features' => array_map(
                fn ($feature) => $gate->permissionLabel($feature),
                ApplicationPlanFeatures::for($module)
            ),
        ], 402);
    }
}
