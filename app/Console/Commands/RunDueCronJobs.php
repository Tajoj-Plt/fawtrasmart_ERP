<?php

namespace App\Console\Commands;

use App\Models\CronJob;
use App\Models\CronJobExecution;
use App\Services\Cron\CronJobRunner;
use App\Services\Cron\CronMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * نقطة الدخول الوحيدة لكل المهام المجدولة.
 *
 * `app/Console/Kernel.php` صار يسجّل هذا الأمر وحده كل دقيقة، وهو يقرأ
 * `cron_jobs` من قاعدة البيانات ويشغّل ما استُحقّ. المكسب أن تعديل وقت أي
 * مهمة أو إيقافها صار حفظ صفّ، لا تعديل كود ونشره.
 *
 * الأمر يسجّل نبضة في كل تشغيلة حتى لو لم تستحقّ أي مهمة — وهي الدليل
 * الوحيد على أن `schedule:run` يعمل أصلاً على الخادم.
 */
class RunDueCronJobs extends Command
{
    protected $signature = 'cron:run-due
                            {--job= : تشغيل مهمة بعينها بمفتاحها بغضّ النظر عن استحقاقها}
                            {--dry-run : عرض المهام المستحقّة دون تنفيذها}';

    protected $description = 'تشغيل المهام المجدولة المستحقّة المعرَّفة في قاعدة البيانات';

    public function handle(CronJobRunner $runner, CronMonitoringService $monitor): int
    {
        // النبضة أولاً: لو تعثّرت المهام بعدها فالمجدول نفسه يبقى مثبتاً
        // أنه يعمل، وهذا ما يفرّق بين «مهمة فاشلة» و«لا شيء يعمل»
        $monitor->recordHeartbeat();

        $dryRun = (bool) $this->option('dry-run');

        $jobs = $this->resolveJobs();

        if ($jobs->isEmpty()) {
            return self::SUCCESS;
        }

        $ran = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            if ($dryRun) {
                $this->line('مستحقّة: ['.$job->key.'] '.$job->name.' — '.$job->cron_expression);
                $ran++;

                continue;
            }

            try {
                $execution = $runner->run($job, 'schedule');

                if ($execution->status === CronJobExecution::STATUS_FAILED) {
                    $failed++;
                    $this->error('فشلت: '.$job->name.' — '.$execution->error_message);
                } else {
                    $ran++;
                    $this->line('نُفِّذت: '.$job->name.' ('.$execution->statusLabel().')');
                }
            } catch (\Throwable $e) {
                // مهمة تنهار خارج المُشغّل يجب ألا توقف بقية المهام: دورة
                // المجدول واحدة لكل الدقيقة، وسقوطها يعطّل النظام كلّه
                $failed++;

                Log::error('انهيار غير متوقّع أثناء تشغيل مهمة مجدولة', [
                    'cron_job_id' => $job->id,
                    'key' => $job->key,
                    'error' => $e->getMessage(),
                ]);

                $this->error('انهارت: '.$job->name.' — '.$e->getMessage());
            }
        }

        if ($ran || $failed) {
            $this->info("انتهت الدورة: {$ran} نُفِّذت، {$failed} فشلت.");
        }

        // شفرة الخروج تعكس نتيجة الدورة لا نتيجة المهام: فشل مهمة مسجَّل
        // في جدولها، وإفشال الدورة كلّها بسببه يشوّش مراقبة المجدول
        return self::SUCCESS;
    }

    /**
     * المهام التي ستُشغَّل في هذه الدورة.
     *
     * @return Collection<int, CronJob>
     */
    protected function resolveJobs()
    {
        $key = $this->option('job');

        if ($key) {
            $job = CronJob::where('key', $key)->first();

            if (! $job) {
                $this->error('لا توجد مهمة بالمفتاح: '.$key);

                return collect();
            }

            return collect([$job]);
        }

        $now = now();

        /*
            `isPending` لا `isDue`: الثانيةُ تصدُق في دقيقة الموعد وحدها،
            ودورةُ المجدول تأتي من زيارةٍ فتفوتها. انظر
            `CronJob::isPending()` و`config/cron.php`.
        */
        return CronJob::active()->get()->filter(fn (CronJob $job) => $job->isPending($now))->values();
    }
}
