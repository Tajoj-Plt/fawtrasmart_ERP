<?php

namespace App\Http\Middleware;

use App\Listeners\RecordSessionActivity;
use App\Models\SessionLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحدّث «آخر ظهور» للجلسة الجارية.
 *
 * بدونه لا يُعرف متى انتهت جلسةٌ أُغلق متصفّحها: لا حدث خروج يقع، فيبقى
 * الصفّ مفتوحاً بلا نهاية. ومع هذا التحديث يعرف الأمر `sessions:reconcile`
 * أن آخر ظهور كان قبل ساعتين فيغلقها عند ذلك الوقت لا عند لحظة اكتشافه.
 *
 * **الكلفة:** كتابةٌ واحدة كل دقيقة لا كتابةٌ مع كل طلب. الفاصل محفوظ في
 * الجلسة نفسها، فلا استعلام يسبق القرار — طلبٌ لا يحتاج تحديثاً لا يمسّ
 * قاعدة البيانات أصلاً.
 */
class TrackSessionActivity
{
    /** الفاصل بين تحديثين، بالثواني */
    protected const INTERVAL = 60;

    protected const STAMP = 'session_log_seen_at';

    public function handle(Request $request, Closure $next): Response
    {
        $this->touch($request);

        return $next($request);
    }

    protected function touch(Request $request): void
    {
        try {
            if (! $request->hasSession() || ! $request->user()) {
                return;
            }

            $session = $request->session();
            $logId = $session->get(RecordSessionActivity::KEY);

            if (! $logId) {
                return;
            }

            $last = (int) $session->get(self::STAMP, 0);

            if (time() - $last < self::INTERVAL) {
                return;
            }

            $session->put(self::STAMP, time());

            // `update` على المفتاح الأساسي مباشرةً: لا حاجة لجلب الصفّ
            // لتحديث عمودين، والشرط يمنع إحياء جلسة أُغلقت من شاشة أخرى
            SessionLog::where('id', $logId)
                ->where('status', 'active')
                ->update([
                    'last_seen_at' => now(),
                    'session_id' => $session->getId(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            // تتبّع الجلسة لا يجوز أن يُسقط الطلب
        }
    }
}
