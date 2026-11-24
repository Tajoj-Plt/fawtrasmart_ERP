<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Services\SmsGateway;
use Illuminate\Console\Command;

/**
 * يرسل الرسائل المؤجَّلة ويعيد محاولة الفاشلة، ثم ينظّف السجل.
 *
 * إعدادا «تأجيل ساعات الهدوء» و«إعادة المحاولة» يكتبان في `sms_logs`
 * صفّاً بموعده وحالته، ولا يرسلان بأنفسهما — إرسالٌ داخل الطلب يعني
 * انتظار المستخدم دقيقةً بين محاولتين. هذا الأمر هو الطرف الثاني منهما:
 * بدونه تبقى الرسالة المؤجَّلة مؤجَّلة إلى الأبد.
 *
 * يُجدوَل كل خمس دقائق في `app/Console/Kernel.php`.
 */
class FlushDeferredSms extends Command
{
    protected $signature = 'sms:flush
                            {--limit=200 : أقصى عدد رسائل تُعالَج في التشغيل الواحد}
                            {--dry-run : يعرض ما سيُرسل دون إرساله}';

    protected $description = 'إرسال الرسائل النصية المؤجَّلة وإعادة محاولة الفاشلة وتنظيف السجل';

    public function handle(SmsGateway $gateway): int
    {
        $settings = SmsSetting::current();

        if (! $settings->isConfigured()) {
            $this->warn('بوابة الرسائل غير مضبوطة أو معطّلة — لا شيء يُرسل.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        // ساعات الهدوء تُفحص هنا أيضاً: الأمر قد يعمل داخلها، وإعادة
        // الإرسال حينئذ تلتفّ على الإعداد الذي أجّل الرسالة أصلاً
        if ($settings->inQuietHours() || ! $settings->isWorkingDay()) {
            $this->info('الوقت الآن ضمن ساعات الهدوء — أُجّلت المعالجة.');

            return self::SUCCESS;
        }

        $due = SmsLog::whereIn('status', ['deferred', 'retry'])
            ->where(function ($query) {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        if ($due->isEmpty()) {
            $this->info('لا توجد رسائل مستحقّة.');
        }

        $sent = 0;
        $failed = 0;
        $abandoned = 0;

        foreach ($due as $log) {
            // المحاولات المستنفدة تُغلق بحالة نهائية بدل أن تُعاد أبداً
            if ($log->status === 'retry' && $log->attempts > $settings->retry_attempts) {
                if (! $dry) {
                    $log->update(['status' => 'failed', 'scheduled_at' => null]);
                }

                $abandoned++;

                continue;
            }

            if ($dry) {
                $this->line("سيُرسل: {$log->recipient} ({$log->status}، محاولة {$log->attempts})");

                continue;
            }

            // الصفّ القديم يُغلق قبل الإرسال كي لا يلتقطه تشغيلٌ متزامن
            $log->update(['status' => 'processing', 'scheduled_at' => null]);

            $result = $gateway->send($log->recipient, $log->message, $log->user_id, $log->template_code);

            // `send` كتب صفّاً جديداً بالنتيجة؛ القديم يحمل الخلاصة
            $log->update([
                'status' => $result['success'] ? 'sent' : ($result['status'] === 'retry' ? 'retry' : 'failed'),
                'attempts' => $log->attempts + 1,
                'scheduled_at' => $result['status'] === 'retry' ? $this->nextAttemptAt($settings, $log->attempts + 1) : null,
                'gateway_response' => json_encode(['flushed' => $result['message']], JSON_UNESCAPED_UNICODE),
            ]);

            $result['success'] ? $sent++ : $failed++;
        }

        if (! $dry && $due->isNotEmpty()) {
            $this->info("المؤجَّلة: أُرسل {$sent}، وفشل {$failed}، واستُنفدت محاولات {$abandoned}.");
        }

        $this->prune($settings, $dry);

        return self::SUCCESS;
    }

    /**
     * موعد المحاولة التالية.
     *
     * التصاعد الأسّي مقصود: بوابة متعطّلة لا تُصلَح بالإلحاح عليها كل
     * دقيقة، والمهلة تتضاعف حتى تُستنفد المحاولات.
     */
    protected function nextAttemptAt(SmsSetting $settings, int $attempt): \Illuminate\Support\Carbon
    {
        $base = max(5, (int) $settings->retry_delay_seconds);

        $delay = $settings->retry_backoff === 'exponential'
            ? $base * (2 ** max(0, $attempt - 1))
            : $base;

        // سقف ساعة: التصاعد الأسّي يبلغ أياماً بعد محاولات قليلة
        return now()->addSeconds(min($delay, 3600));
    }

    /** حذف السجلات التي تجاوزت مدّة الاحتفاظ المضبوطة */
    protected function prune(SmsSetting $settings, bool $dry): void
    {
        $days = (int) $settings->log_retention_days;

        if ($days <= 0) {
            return;
        }

        $query = SmsLog::where('created_at', '<', now()->subDays($days))
            ->whereNotIn('status', ['deferred', 'retry', 'processing']);

        $count = $query->count();

        if ($count === 0) {
            return;
        }

        if ($dry) {
            $this->line("سيُحذف {$count} سجلاً تجاوز {$days} يوماً.");

            return;
        }

        $query->delete();

        $this->info("نُظّف السجل: حُذف {$count} سجلاً تجاوز {$days} يوماً.");
    }
}
