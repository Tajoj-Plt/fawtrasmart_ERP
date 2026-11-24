<?php

namespace App\Http\Middleware;

use App\Services\Cron\SchedulerTick;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يُشغّل دورةَ المجدول الداخلي بعد إرسال الاستجابة.
 *
 * `terminate()` يُستدعى بعد أن تُرسَل الصفحةُ إلى المتصفّح (وبعد
 * `fastcgi_finish_request` حيث يتوفّر)، فالزائرُ لا ينتظر الدورةَ إلا على
 * خادمٍ لا يدعم ذلك. الفحصُ الأوّل هنا رخيص — مفتاحٌ واحد في الذاكرة
 * المؤقتة — فلا يُثقل الطلبات التي لا دورةَ فيها.
 *
 * @see \App\Services\Cron\SchedulerTick
 */
class TickSchedulerFromRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // رابطُ النبض يُشغّل الدورةَ بنفسه، والمثبِّت يعمل بلا قاعدة
        if ($request->is('scheduler/tick/*') || $request->is('install*')) {
            return;
        }

        SchedulerTick::run(SchedulerTick::SOURCE_WEB);
    }
}
