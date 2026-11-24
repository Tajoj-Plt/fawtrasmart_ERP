<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مصادقة طلبات الواجهة البرمجية بمفتاح الربط.
 *
 * **لماذا كان الأمر معطوباً؟**
 *
 * الاسم `api.key` كان مسجَّلاً في `bootstrap/app.php` ويشير إلى هذا الصنف
 * — وهو غير موجود. أي مسار يستعمله كان يسقط بـ «Class not found». ولأن
 * الجدول كان ينقصه عمود `secret` فإن إنشاء المفتاح نفسه كان يفشل أيضاً.
 * فالقسم كله كان واجهة بلا خلفية.
 *
 * **كيف يُستعمل؟**
 *
 *     Route::middleware('api.key')->get(...);              // أي مفتاح صالح
 *     Route::middleware('api.key:clients.read')->get(...); // بصلاحية محددة
 *
 * ويرسل العميل الترويستين:
 *
 *     X-Api-Key: fawt_xxxxxxxx
 *     X-Api-Secret: yyyyyyyy
 */
class ApiKeyAuthentication
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $key = $request->header('X-Api-Key') ?: $request->query('api_key');
        $secret = $request->header('X-Api-Secret');

        if (! $key || ! $secret) {
            return $this->deny('مفتاح الربط مفقود. أرسل الترويستين X-Api-Key و X-Api-Secret.', 401);
        }

        $apiKey = ApiKey::where('key', $key)->first();

        // المقارنة تتم دائماً ولو لم يوجد المفتاح: الخروج المبكر يكشف
        // بفارق الزمن أي المفاتيح موجود
        $storedHash = $apiKey?->secret ?: str_repeat('0', 64);

        if (! hash_equals($storedHash, hash('sha256', $secret)) || ! $apiKey) {
            return $this->deny('مفتاح الربط أو الرمز السري غير صحيح.', 401);
        }

        if (! $apiKey->isValid()) {
            return $this->deny(
                $apiKey->expires_at && $apiKey->expires_at->isPast()
                    ? 'انتهت صلاحية هذا المفتاح.'
                    : 'هذا المفتاح معطَّل.',
                403
            );
        }

        if ($ability && ! $apiKey->can($ability)) {
            return $this->deny("هذا المفتاح لا يملك صلاحية «{$ability}».", 403);
        }

        $apiKey->markAsUsed($request->ip());

        // الطلب يعمل بهوية صاحب المفتاح، فتسري عليه قيود بياناته
        $request->attributes->set('api_key', $apiKey);

        if ($apiKey->user) {
            auth()->setUser($apiKey->user);
        }

        return $next($request);
    }

    protected function deny(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
