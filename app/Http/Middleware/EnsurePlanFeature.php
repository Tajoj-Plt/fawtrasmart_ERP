<?php

namespace App\Http\Middleware;

use App\Services\Fawtra\SubscriptionGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لا فتح لشاشةِ ميزةٍ لا تشملها الباقة — طبقةٌ فوق صلاحيات النظام لا بدلاً منها.
 *
 * ─────────────────────── لماذا وسيطٌ مستقلّ؟ ───────────────────────
 *
 * السؤال هنا غير سؤال [CheckPermissionRole] تماماً: ذاك يسأل «هل يملك هذا
 * المستخدم صلاحية النظام؟»، وهذا يسأل «هل اشترت المنشأة هذه الميزة أصلاً؟».
 * وخلطُهما في وسيطٍ واحد يعني أن ترقية الباقة تمرّ بتعديل صلاحيات المستخدمين
 * — وهو ما لا يجوز: صلاحيات النظام مِلك المنشأة تضبطها كما تشاء، وحدود
 * الباقة مِلك فوترة.
 *
 * فالوسيطان يتراكبان: يُرفض من سقط في أيّهما.
 *
 * ─────────────────────── لماذا على المسار لا في القالب؟ ───────────────────────
 *
 * إخفاء بند القائمة يمنع النقر ولا يمنع الطلب. ومن يعرف العنوان — أو يقرؤه
 * من `route()` في صفحةٍ أخرى، أو يستدعي مسار AJAX مباشرةً — يعمل من خلف
 * القائمة المخفيّة. فالقائمة للوضوح، والوسيط للمنع.
 *
 * ─────────────────────── الاستعمال ───────────────────────
 *
 *   `middleware('plan:stock_products')`      — تُشترط هذه الميزة
 *   `middleware('plan:stock_products|stock_categories')` — تكفي واحدة
 *
 * وميزةٌ لا يعرفها كتالوج فوترة **مسموحة** (انظر `SubscriptionGate::allows()`):
 * الكتالوج قد يسبق النظام أو يتأخّر عنه، ومنعُ ما لا يُعرف يُقفل شاشاتٍ لم
 * يقصد أحد إقفالها. ونتيجتُه أن الخطأ الإملائي في اسم المفتاح لا يُنتج
 * عطلاً بل حارساً صامتاً لا يحرس — ولهذا تُكتب الأسماء من
 * [\App\Constants\planFeatures\StockPlanFeatures] لا نصّاً حرّاً.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $features): Response
    {
        $required = array_values(array_filter(explode('|', $features)));

        if ($required === []) {
            return $next($request);
        }

        $gate = app(SubscriptionGate::class);

        if ($gate->allowsAny($required)) {
            return $next($request);
        }

        // الرسالة تسمّي الميزة الناقصة بالاسم الذي يراه المستخدم في شاشة
        // الاشتراك نفسها — لا بمفتاحها البرمجي. «باقتك لا تشمل
        // stock_price_lists» لا تقول لمشترٍ شيئاً
        $labels = array_map(fn ($key) => $gate->permissionLabel($key), $required);

        $message = count($labels) === 1
            ? 'باقتك الحالية في فوترة لا تشمل ميزة «'.$labels[0].'». رقِّ باقتك من لوحة تحكّم فوترة لفتح هذه الشاشة.'
            : 'باقتك الحالية في فوترة لا تشمل أياً من: «'.implode('»، «', $labels).'». رقِّ باقتك من لوحة تحكّم فوترة لفتح هذه الشاشة.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'code' => 'PLAN_FEATURE_UNAVAILABLE',
                'features' => $required,
            ], 402);
        }

        // ‏402 لا 403: المنع هنا سببه ما لم يُشترَ، لا ما لم يُصرَّح به —
        // والتفريق بينهما في السجلّات هو ما يجعل «لماذا لا أصل؟» سؤالاً له
        // جواب واحد لا ثلاثة
        return response()->view('errors.plan-feature', [
            'message' => $message,
            'features' => $labels,
        ], 402);
    }
}
