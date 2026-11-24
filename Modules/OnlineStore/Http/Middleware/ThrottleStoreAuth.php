<?php

namespace Modules\OnlineStore\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * حدُّ المحاولات لمسارات تسجيل الدخول والتسجيل والتحقق في واجهة المتجر،
 * بردٍّ بصيغة المتجر (`success` + رسالة عربية + `retry_after`) بدل
 * «Too Many Attempts.» الإنجليزية الخام التي لا تفهمها الواجهة.
 *
 * الاستعمال: `->middleware(ThrottleStoreAuth::class.':10,1')` — عشر محاولات كل دقيقة لكل عنوان ومسار.
 */
class ThrottleStoreAuth
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 10, int $decayMinutes = 1)
    {
        try {
            return app(ThrottleRequests::class)->handle($request, $next, $maxAttempts, $decayMinutes);
        } catch (ThrottleRequestsException $e) {
            $seconds = (int) ($e->getHeaders()['Retry-After'] ?? $decayMinutes * 60);

            return response()->json([
                'success' => false,
                'message' => "محاولات كثيرة خلال وقتٍ قصير — أعد المحاولة بعد {$seconds} ثانية.",
                'code' => 'TOO_MANY_ATTEMPTS',
                'retry_after' => $seconds,
            ], 429, $e->getHeaders());
        }
    }
}
