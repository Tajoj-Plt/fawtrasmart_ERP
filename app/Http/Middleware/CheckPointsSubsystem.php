<?php

namespace App\Http\Middleware;

use App\Models\Ecosystem\PointsGeneralSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware للتحقق من تفعيل نظام فرعي محدد
 * الاستخدام في الـ Routes: middleware('points.subsystem:tiers')
 */
class CheckPointsSubsystem
{
    /**
     * خريطة الأنظمة الفرعية وأسماء عرضها
     */
    private array $subsystemLabels = [
        'tiers' => 'نظام المستويات',
        'rewards' => 'نظام المكافآت',
        'achievements' => 'نظام الإنجازات',
        'challenges' => 'نظام التحديات',
        'referrals' => 'نظام الإحالات',
        'expiry' => 'نظام انتهاء الصلاحية',
        'notifications' => 'نظام الإشعارات',
    ];

    public function handle(Request $request, Closure $next, string $subsystem): Response
    {
        try {
            if (!PointsGeneralSetting::isSubsystemEnabled($subsystem)) {
                $label = $this->subsystemLabels[$subsystem] ?? $subsystem;

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => "{$label} معطّل حالياً. يمكنك تفعيله من الإعدادات العامة.",
                    ], 403);
                }

                return response()->view('ecosystem::points.errors.subsystem-disabled', [
                    'subsystem' => $subsystem,
                    'subsystemLabel' => $label,
                    'settings' => PointsGeneralSetting::instance(),
                ], 403);
            }
        } catch (\Exception $e) {
            // إذا لم يتم تشغيل الـ migration بعد، تجاوز الفحص
        }

        return $next($request);
    }
}
