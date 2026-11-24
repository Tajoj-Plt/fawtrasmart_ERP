<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * سياق طلبات تطبيق الجوال (`/api/mobile/*`).
 *
 * **لماذا هذا الوسيط؟** متحكّماتُ الويب تُعيد `redirect()->back()` برسائل الجلسة إلا حين
 * يكون الطلب JSON. والتطبيق يستدعيها نفسها (فتبقى كلُّ القواعد المحاسبية — الترقيم والقيود
 * والأرصدة وحدود الباقة — في مكانٍ واحد)، فيُهيِّئ هذا الوسيط لها ما تتوقّعه من الويب
 * ويُترجم ما تُعيده إلى JSON:
 *
 *  - رأسا JSON و`X-Requested-With` كي تسلك فروعها `wantsJson()` و`ajax()`.
 *  - لغة الرسائل من `Accept-Language`.
 *  - جلسةٌ **في الذاكرة** لمن يستدعي `back()->with()` (تُفقد بانتهاء الطلب؛ لا كوكيز ولا CSRF).
 *  - أيُّ تحويلٍ (redirect) يصير `{success, message, errors}` بحسب ما وُسمت به الجلسة.
 *  - ردٌّ JSON فيه `success:false` بحالة 200 (بعض فروعها) يصير 422 كي لا يُقرأ نجاحاً.
 */
class MobileApiContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $lang = strtolower(substr((string) $request->header('Accept-Language', 'ar'), 0, 2));
        app()->setLocale(in_array($lang, ['ar', 'en'], true) ? $lang : 'ar');

        // جلسة في الذاكرة: لا تُحفظ ولا تُرسَل كوكي
        config(['session.driver' => 'array']);
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);

        return $this->normalize($request, $next($request));
    }

    protected function normalize(Request $request, Response $response): Response
    {
        if ($response instanceof RedirectResponse) {
            $session = $request->hasSession() ? $request->session() : null;

            $errors = $session?->get('errors');
            $bag = $errors instanceof ViewErrorBag ? $errors->getBag('default')->toArray() : [];

            if ($bag !== []) {
                $first = collect($bag)->flatten()->first();

                return new JsonResponse(['success' => false, 'message' => $first, 'errors' => $bag], 422);
            }

            if ($message = $session?->get('error')) {
                return new JsonResponse(['success' => false, 'message' => $message], 422);
            }

            return new JsonResponse([
                'success' => true,
                'message' => $session?->get('success') ?? $session?->get('message') ?? 'OK',
            ]);
        }

        if ($response instanceof JsonResponse && $response->getStatusCode() < 400) {
            $data = $response->getData(true);
            if (is_array($data) && array_key_exists('success', $data) && $data['success'] === false) {
                $response->setStatusCode(422);
            }
        }

        return $response;
    }
}
