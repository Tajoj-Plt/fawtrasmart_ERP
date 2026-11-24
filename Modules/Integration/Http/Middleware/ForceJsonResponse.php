<?php

namespace Modules\Integration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * واجهة الجوال تردّ JSON دائماً، ولو نسي الطلب ترويسة
 * `Accept: application/json`.
 *
 * بلاها يقرّر Laravel شكل الخطأ من الترويسة (`expectsJson()`): خطأُ تحقّقٍ
 * في طلبٍ بلا الترويسة كان يُعاد توجيهه 302 إلى الصفحة السابقة — صفحة HTML
 * لا يفهمها التطبيق ولا Postman — بدل 422 بأخطاء الحقول.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
