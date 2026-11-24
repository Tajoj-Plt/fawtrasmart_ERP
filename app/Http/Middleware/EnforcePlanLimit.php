<?php

namespace App\Http\Middleware;

use App\Services\Fawtra\SubscriptionGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لا إنشاء فوق حدّ الباقة — يُسأل قبل أن يصل الطلب إلى المتحكّم.
 *
 * ─────────────────────── لماذا لا يكفي `used` في اللقطة؟ ───────────────────────
 *
 * لأن اللقطة تُجلب كل ربع ساعة والرفعُ إلى فوترة دوريٌّ كذلك. فالمنع المبنيّ
 * على `used` المحفوظ يترك نافذةً مقدارها دورةُ مزامنة كاملة، والإنشاء فيها
 * غير محدود عملياً: ألف منتجٍ فوق سقفٍ من مئة تمرّ كلّها، ثم تأتي المزامنة
 * فتجد الحدّ متجاوَزاً ولا تملك إرجاعه.
 *
 * فهذا الوسيط يسأل `SubscriptionGate::checkLive()` — الحدّ من فوترة
 * والمستهلَك معدودٌ من قاعدة البيانات في هذه اللحظة. انظر
 * `SubscriptionGate::liveLimit()`.
 *
 * ─────────────────────── لماذا على `create` أيضاً لا على `store` وحده؟ ───────────────────────
 *
 * المنع الحقيقي على `store` — وهو وحده ما لا يُلتفّ عليه. أمّا وضعُه على
 * `create` كذلك فلئلّا يملأ المستخدم نموذجاً طويلاً ثم يُرفض عند الحفظ.
 *
 * ─────────────────────── الاستعمال ───────────────────────
 *
 *   `middleware('plan.limit:products')`      — عملية واحدة
 *   `middleware('plan.limit:products,5')`    — كمّية ثابتة
 *
 * والاستيراد الجماعي لا يُحرَس من هنا: كمّيته لا تُعرف إلا بعد قراءة الملف،
 * فيُسأل الحدّ داخل المتحكّم بالعدد الحقيقي.
 */
class EnforcePlanLimit
{
    public function handle(Request $request, Closure $next, string $feature, int|string $quantity = 1): Response
    {
        $result = app(SubscriptionGate::class)->checkLive($feature, max(1, (int) $quantity));

        if ($result['allowed']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $result['reason'],
                'code' => 'PLAN_LIMIT_REACHED',
                'feature' => $feature,
                'remaining' => $result['remaining'],
            ], 402);
        }

        // العودة لا صفحةُ جدار: بلوغ الحدّ حالةٌ يعالجها المستخدم من مكانه
        // (يحذف ما لم يعد مستعمَلاً أو يشتري إضافة)، لا عطلٌ يُخرجه من
        // الشاشة. وصفحةٌ كاملة هنا تُضيّع ما كان يكتبه
        return back()->withInput()->with('error', $result['reason']);
    }
}
