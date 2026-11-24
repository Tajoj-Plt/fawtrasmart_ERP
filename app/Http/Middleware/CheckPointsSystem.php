<?php

namespace App\Http\Middleware;

use App\Models\Ecosystem\PointsGeneralSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware للتحقق من تفعيل نظام النقاط الرئيسي
 * يمنع الوصول إلى جميع صفحات النقاط إذا كان النظام معطلاً
 * (عدا صفحة الإعدادات العامة والتقارير لتمكين إعادة التفعيل)
 */
class CheckPointsSystem
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = PointsGeneralSetting::instance();

            if (!$settings->system_enabled) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'نظام النقاط معطّل حالياً. يمكنك تفعيله من الإعدادات العامة.',
                    ], 403);
                }

                return response()->view('ecosystem::points.errors.system-disabled', [
                    'settings' => $settings,
                ], 403);
            }
        } catch (\Exception $e) {
            // إذا لم يتم تشغيل الـ migration بعد، تجاوز الفحص
        }

        return $next($request);
    }
}
