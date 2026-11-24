<?php

namespace App\Http\Controllers;

use App\Services\Cron\CronMonitoringService;
use App\Services\Cron\SchedulerTick;
use Illuminate\Http\JsonResponse;

/**
 * رابطُ النبض: `GET /scheduler/tick/{token}`.
 *
 * لخدمة مراقبةٍ خارجية (UptimeRobot ونحوها) تستدعيه كل دقيقة، فتقع دوراتُ
 * المجدول في الساعات التي لا يزور فيها أحد. الرمزُ مشتقّ من `APP_KEY`
 * ويُعرض في شاشة «المهام المجدولة». المسارُ خارج مجموعات الوسطاء كلّها:
 * لا جلسة ولا CSRF ولا اشتراك — وإلا حوّلته تلك الحرّاس إلى صفحة دخول.
 */
class SchedulerTickController extends Controller
{
    public function __invoke(string $token, CronMonitoringService $monitor): JsonResponse
    {
        if (! hash_equals(SchedulerTick::token(), $token)) {
            return response()->json(['ok' => false, 'error' => 'invalid token'], 403);
        }

        if (! SchedulerTick::enabled()) {
            return response()->json(['ok' => false, 'error' => 'web tick disabled'], 409);
        }

        $ran = SchedulerTick::run(SchedulerTick::SOURCE_PING);

        return response()->json([
            'ok' => true,
            'ran' => $ran,
            'last_heartbeat' => $monitor->lastHeartbeat()?->toIso8601String(),
            'source' => $monitor->lastHeartbeatSource(),
        ]);
    }
}
