<?php

namespace App\Http\Middleware;

use App\Models\ClientModuleSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يمنع الوصول إلى قسمٍ فرعيٍّ من موديول العملاء عُطِّل من «إعدادات وحدات
 * العملاء» — الزيارات وخطوط السير والموقع والملاحظات والمواعيد وCRM
 * والديون المشكوك فيها وتحويل الفواتير وأهداف العملاء.
 *
 * الاستعمال: `middleware('client.feature:visits')` — المفتاح المُمرَّر +
 * `_enabled` هو اسم العمود في جدول `client_module_settings`.
 */
class EnsureClientFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $column = $feature.'_enabled';

        if ((bool) (ClientModuleSetting::current()->{$column} ?? true)) {
            return $next($request);
        }

        $message = 'هذه الميزة معطّلة من إعدادات العملاء.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()->route('clients.setting')->with('error', $message);
    }
}
