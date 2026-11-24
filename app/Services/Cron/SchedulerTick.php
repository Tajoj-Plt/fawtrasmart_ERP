<?php

namespace App\Services\Cron;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * المجدول الداخلي: دورةُ المجدول من داخل النظام لا من Cron الاستضافة.
 *
 * **لماذا؟** النظام يُركَّب على دومينات كثيرة، ولكلّ دومين كان يلزم سطر
 * Cron على استضافته يستدعي `schedule:run` كل دقيقة. السطر يُنسى، أو يشير
 * إلى مجلد قديم بعد نقل النظام، أو تُوقفه الاستضافة — فتبدو كل المهام
 * «نشطة» في شاشة المهام المجدولة ولا شيء يُنفَّذ (وقع هذا على خادم الإنتاج:
 * انقطع النبض في 2026-09-23 20:37 وبقيت تسوية الأهداف معلّقة بلا أثر).
 *
 * **كيف؟** كلُّ طلب ويب، بعد أن تُرسَل استجابتُه إلى المتصفّح، يسأل: أمرّت
 * دقيقةٌ على آخر دورة؟ أينبض Cron حقيقيّ؟ فإن استُحقّت الدورة ولا Cron
 * نُفِّذ `cron:run-due` في العملية نفسها — وهو ما يُنفّذه Cron تماماً،
 * إذ صارت كل المهام في جدول `cron_jobs`. القفلُ يمنع دورتين متزامنتين،
 * والفاصلُ يمنع دورةً كل طلب.
 *
 * **حدوده** — ولهذا يبقى Cron الحقيقيّ أفضل حين يتوفّر:
 *   • لا زيارات = لا دورات: مهمةٌ موعدها 02:10 تُنفَّذ مع أوّل زيارةٍ في
 *     الصباح (المهامُ التي تفحص «بلغ الوقتُ» لا «الدقيقةَ نفسها» — كتسوية
 *     الأهداف — تُدرك ذلك). رابطُ النبض أدناه يحلّ هذا بخدمة مراقبة خارجية.
 *   • على خادمٍ بلا `fastcgi_finish_request` (Apache mod_php) ينتظر
 *     الزائرُ الذي وقعت الدورةُ في طلبه حتى تنتهي — مرّةً في الدقيقة.
 */
class SchedulerTick
{
    public const SOURCE_CRON = 'cron';

    public const SOURCE_WEB = 'web';

    public const SOURCE_PING = 'ping';

    private const LOCK_KEY = 'scheduler:tick:lock';

    private const INTERVAL_KEY = 'scheduler:tick:last';

    public static function enabled(): bool
    {
        return (bool) config('cron.web_tick', true);
    }

    /**
     * أتُستحقّ دورةٌ الآن من هذا المصدر؟
     *
     * لا تُستحقّ حين: الميزة موقوفة، أو لم يمضِ الفاصلُ منذ آخر دورة، أو
     * Cron حقيقيّ ينبض — فالدورةُ من الزيارات بديلٌ لا شريك.
     */
    public static function due(string $source = self::SOURCE_WEB): bool
    {
        if (! self::enabled()) {
            return false;
        }

        try {
            if ($source !== self::SOURCE_PING && self::realCronAlive()) {
                return false;
            }

            return ! Cache::has(self::INTERVAL_KEY);
        } catch (\Throwable) {
            // ذاكرةٌ مؤقتة معطّلة لا تُسقط الطلب — ولا تُشغّل الدورة
            return false;
        }
    }

    /**
     * تنفيذُ دورةٍ إن استُحقّت. تُرجع `true` حين نُفِّذت فعلاً.
     */
    public static function run(string $source = self::SOURCE_WEB): bool
    {
        if (! self::due($source)) {
            return false;
        }

        try {
            $lock = Cache::lock(self::LOCK_KEY, 300);

            if (! $lock->get()) {
                return false;
            }

            try {
                Cache::put(self::INTERVAL_KEY, now()->toIso8601String(), (int) config('cron.web_tick_interval', 60));

                // مصدر النبضة يقرؤه `CronMonitoringService::recordHeartbeat()`
                app()->instance('scheduler.tick_source', $source);

                Artisan::call('cron:run-due');

                return true;
            } finally {
                app()->forgetInstance('scheduler.tick_source');
                $lock->release();
            }
        } catch (\Throwable $e) {
            Log::warning('تعثّرت دورة المجدول الداخلي', ['source' => $source, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** أينبض Cron حقيقيّ (لا دورةٌ من الزيارات) خلال المهلة المضبوطة؟ */
    public static function realCronAlive(): bool
    {
        $monitor = app(CronMonitoringService::class);
        $last = $monitor->lastHeartbeat();

        if ($last === null || $monitor->lastHeartbeatSource() !== self::SOURCE_CRON) {
            return false;
        }

        return $last->diffInMinutes(now()) < (int) config('cron.web_tick_defer_minutes', 3);
    }

    /**
     * رمزُ رابط النبض — مشتقّ من `APP_KEY` فلا يُخزَّن ولا يُخمَّن، ويختلف
     * بين التركيبات.
     */
    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'scheduler-tick', (string) config('app.key')), 0, 40);
    }

    public static function url(): string
    {
        return url('/scheduler/tick/'.self::token());
    }
}
