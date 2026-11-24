<?php

namespace Modules\Installer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Installer\Services\InstallationState;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحوّل النظام كلّه إلى معالج التثبيت ما لم يكن مثبَّتاً.
 *
 * موضعه المجموعة العامة `web` لا مسارٌ بعينه: قبل التثبيت لا قاعدة بيانات،
 * وأي صفحة تُفتح تنهار باستثناء اتصالٍ خام لا يفهمه المستخدم. تحويلةٌ إلى
 * `/install` أوضح من أثر انهيار.
 *
 * والفحص ملفٌّ مقروء مرة واحدة لكل طلب (InstallationState) — لا استعلام،
 * وإلا لكان الوسيط الذي يعالج «لا قاعدة بيانات» أولَ ما يسقط بغيابها.
 */
class EnsureInstalled
{
    /**
     * مسارات تعمل قبل التثبيت: المعالج نفسه، ونقطة الصحّة التي يستعملها
     * مراقب الخادم — وسقوطها يُقرأ إنذاراً كاذباً.
     */
    protected array $except = [
        'install',
        'install/*',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (InstallationState::isInstalled() || $this->isExcepted($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'النظام غير مثبَّت بعد. افتح /install لإتمام التثبيت.',
            ], 503);
        }

        return redirect('/install');
    }

    protected function isExcepted(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
