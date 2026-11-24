<?php

namespace App\Services\Cron;

use App\Models\CronJob;
use App\Models\CronJobExecution;
use App\Services\SystemAlertService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * تنفيذ مهمة مجدولة واحدة وتسجيل أثرها.
 *
 * ثلاث ضمانات يقوم عليها هذا الصنف:
 * 1. لا يُنفَّذ مُعالِج خارج `CronRegistry` مهما كان مصدر الطلب.
 * 2. صفّ التنفيذ يُكتَب *قبل* البدء، فانهيار العملية يترك أثراً لا فراغاً.
 * 3. القفل يُحرَّر في `finally`، فالاستثناء لا يترك المهمة مقفلة إلى الأبد.
 */
class CronJobRunner
{
    /** عدد الإخفاقات المتتالية التي يُطلَق عندها تنبيه «مهمة متعثّرة». */
    public const STUCK_THRESHOLD = 3;

    /** أقصى ما يُحفَظ من مخرجات التنفيذ — مخرجات ضخمة تفجّر الجدول. */
    private const MAX_OUTPUT_CHARS = 60000;

    /**
     * المهمة الجارية الآن — يقرأها الأمر المُنفَّذ ليعرف من شغّله.
     *
     * `Artisan::call` لا يمرّر سياقاً، والأمر يُنفَّذ عبر توقيعه وحده. لكن
     * `backups.cron_job_id` موجود ليربط النسخة بالجدول الذي أنتجها — وهو
     * ما تعرضه شاشة الجداول («آخر نسخة من هذا الجدول»). بدون هذا المتغيّر
     * يبقى العمود فارغاً أبداً في النسخ المجدولة، فيبدو كل جدول كأنه لم
     * ينتج شيئاً قطّ.
     *
     * ساكن لا حقنٌ في الحاوية: عمر القيمة سطرٌ واحد داخل `finally`، وتسجيلها
     * في الحاوية يجعلها تبدو خدمةً متاحةً في كل مكان وهي ليست كذلك.
     */
    public static ?CronJob $current = null;

    /**
     * تشغيل المهمة.
     *
     * @param  string  $trigger  schedule عند المجدول، manual عند «شغّل الآن»
     */
    public function run(CronJob $job, string $trigger = 'schedule', ?int $userId = null): CronJobExecution
    {
        // القائمة البيضاء تُفحَص عند كل تشغيل لا عند الحفظ فقط: صفّ حُفظ
        // قبل تضييقها يجب أن يتوقّف، لا أن يستمرّ بامتياز قديم
        if (! CronRegistry::isAllowed($job->handler)) {
            return $this->recordRejected(
                $job,
                $trigger,
                $userId,
                'المُعالِج «'.$job->handler.'» غير مدرَج في قائمة الأوامر المسموحة.'
            );
        }

        // مهمّة معلَنة في خطّة الأتمتة ولم يُكتَب أمرها بعد: الرفض الصريح هنا
        // أوضح من فشل «أمر غير موجود» بعد أن يبدو أن شيئاً جرى تنفيذه
        if (! CronRegistry::isAvailable($job->handler)) {
            return $this->recordRejected(
                $job,
                $trigger,
                $userId,
                'المهمة «'.$job->name.'» مخطَّطة ولم تُنفَّذ بعد: الأمر «'.$job->handler.'» غير مكتوب في النظام.'
            );
        }

        $lock = null;

        if ($job->prevent_overlap) {
            $lock = Cache::lock($job->lockKey(), max(60, $job->timeout));

            if (! $lock->get()) {
                return $this->recordSkipped($job, $trigger, $userId);
            }
        }

        $execution = CronJobExecution::create([
            'cron_job_id' => $job->id,
            'task_name' => $job->name,
            'trigger' => $trigger,
            'status' => CronJobExecution::STATUS_RUNNING,
            'started_at' => now(),
            'environment' => app()->environment(),
            'hostname' => $this->hostname(),
            'executed_by' => $userId,
        ]);

        $job->forceFill([
            'last_run_at' => $execution->started_at,
            'last_status' => CronJobExecution::STATUS_RUNNING,
        ])->save();

        $startedAt = microtime(true);

        try {
            // في سياق HTTP («شغّل الآن») المهلة الافتراضية 30 ثانية تقطع
            // أي مهمة جادّة قبل أن تبدأ
            if (! app()->runningInConsole()) {
                @set_time_limit(max(60, $job->timeout));
            }

            [$exitCode, $output] = $this->execute($job);

            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            // الأمر قد ينتهي بشفرة خروج غير صفرية دون أن يرمي استثناءً —
            // وهذا فشل يجب أن يُسجَّل فشلاً
            if ($exitCode !== 0) {
                return $this->finishFailed(
                    $job,
                    $execution,
                    $duration,
                    'انتهى التنفيذ بشفرة خروج '.$exitCode,
                    $output,
                    $exitCode
                );
            }

            return $this->finishSuccess($job, $execution, $duration, $output);
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            Log::error('فشل تنفيذ مهمة مجدولة', [
                'cron_job_id' => $job->id,
                'key' => $job->key,
                'handler' => $job->handler,
                'error' => $e->getMessage(),
            ]);

            return $this->finishFailed(
                $job,
                $execution,
                $duration,
                $e->getMessage(),
                $e->getTraceAsString(),
                1
            );
        } finally {
            $lock?->release();
        }
    }

    /**
     * التنفيذ الفعلي.
     *
     * @return array{0:int,1:string} [شفرة الخروج, المخرجات]
     */
    protected function execute(CronJob $job): array
    {
        if (CronRegistry::isAllowedJob($job->handler)) {
            // `dispatch_sync` ينفّذ فوراً في العملية الحالية — الاتصال
            // بالطابور `sync` في هذا المشروع يفعل الشيء نفسه، والصراحة هنا
            // تجعل السلوك مستقلاً عن إعداد QUEUE_CONNECTION
            dispatch_sync(app($job->handler));

            return [0, 'نُفِّذت المهمة '.class_basename($job->handler).' بنجاح.'];
        }

        $parameters = CronRegistry::sanitizeParameters($job->handler, $job->parameters);

        $buffer = new BufferedOutput;

        // `finally` لا سطرٌ بعد النداء: الأمر قد يرمي، وقيمةٌ عالقة تنسب
        // نسخةً لاحقة إلى جدول لم يشغّلها
        static::$current = $job;

        try {
            $exitCode = Artisan::call($job->handler, $parameters, $buffer);
        } finally {
            static::$current = null;
        }

        return [$exitCode, $buffer->fetch()];
    }

    // ==================== تسجيل النتائج ====================

    protected function finishSuccess(CronJob $job, CronJobExecution $execution, int $duration, string $output): CronJobExecution
    {
        $execution->update([
            'status' => CronJobExecution::STATUS_SUCCESS,
            'finished_at' => now(),
            'duration_ms' => $duration,
            'exit_code' => 0,
            'output' => $this->trim($output),
            'error_message' => null,
        ]);

        $job->forceFill([
            'last_finished_at' => $execution->finished_at,
            'last_status' => CronJobExecution::STATUS_SUCCESS,
            'last_duration_ms' => $duration,
            'last_error' => null,
            'success_count' => $job->success_count + 1,
            'consecutive_failures' => 0,
            'next_run_at' => $job->nextRunDate(),
        ])->save();

        if ($job->notify_on_success) {
            SystemAlertService::send(
                SystemAlertService::CRON_SUCCEEDED,
                'اكتملت المهمة: '.$job->name,
                'انتهى التنفيذ بنجاح خلال '.$execution->humanDuration().'.'
            );
        }

        return $execution;
    }

    protected function finishFailed(
        CronJob $job,
        CronJobExecution $execution,
        int $duration,
        string $error,
        string $output = '',
        int $exitCode = 1
    ): CronJobExecution {
        $execution->update([
            'status' => CronJobExecution::STATUS_FAILED,
            'finished_at' => now(),
            'duration_ms' => $duration,
            'exit_code' => $exitCode,
            'output' => $this->trim($output),
            'error_message' => $this->trim($error, 2000),
        ]);

        $consecutive = $job->consecutive_failures + 1;

        $job->forceFill([
            'last_finished_at' => $execution->finished_at,
            'last_status' => CronJobExecution::STATUS_FAILED,
            'last_duration_ms' => $duration,
            'last_error' => $this->trim($error, 2000),
            'failure_count' => $job->failure_count + 1,
            'consecutive_failures' => $consecutive,
            'next_run_at' => $job->nextRunDate(),
        ])->save();

        if ($job->notify_on_failure) {
            // فشل متكرّر مشكلة أخرى غير فشل عابر، فله تنبيه مستقلّ يمكن
            // إسكات أحدهما دون الآخر
            $isStuck = $consecutive >= self::STUCK_THRESHOLD;

            SystemAlertService::send(
                $isStuck ? SystemAlertService::CRON_STUCK : SystemAlertService::CRON_FAILED,
                $isStuck
                    ? 'مهمة متعثّرة: '.$job->name
                    : 'فشلت المهمة: '.$job->name,
                $isStuck
                    ? 'فشلت المهمة '.$consecutive.' مرّات متتالية. آخر خطأ: '.$this->trim($error, 300)
                    : $this->trim($error, 300)
            );
        }

        return $execution;
    }

    /** تشغيل تخطّاه القفل: تشغيلة سابقة ما زالت جارية. */
    protected function recordSkipped(CronJob $job, string $trigger, ?int $userId): CronJobExecution
    {
        return CronJobExecution::create([
            'cron_job_id' => $job->id,
            'task_name' => $job->name,
            'trigger' => $trigger,
            'status' => CronJobExecution::STATUS_SKIPPED,
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 0,
            'error_message' => 'تشغيلة سابقة لهذه المهمة ما زالت جارية — مُنع التداخل.',
            'environment' => app()->environment(),
            'hostname' => $this->hostname(),
            'executed_by' => $userId,
        ]);
    }

    /** مُعالِج خارج القائمة البيضاء: يُسجَّل فشلاً صريحاً لا صمتاً. */
    protected function recordRejected(CronJob $job, string $trigger, ?int $userId, string $reason): CronJobExecution
    {
        Log::warning('رُفض تشغيل مهمة: مُعالِج غير مسموح', [
            'cron_job_id' => $job->id,
            'handler' => $job->handler,
        ]);

        $execution = CronJobExecution::create([
            'cron_job_id' => $job->id,
            'task_name' => $job->name,
            'trigger' => $trigger,
            'status' => CronJobExecution::STATUS_FAILED,
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 0,
            'exit_code' => 1,
            'error_message' => $reason,
            'environment' => app()->environment(),
            'hostname' => $this->hostname(),
            'executed_by' => $userId,
        ]);

        $job->forceFill([
            'last_run_at' => $execution->started_at,
            'last_finished_at' => $execution->finished_at,
            'last_status' => CronJobExecution::STATUS_FAILED,
            'last_error' => $reason,
            'failure_count' => $job->failure_count + 1,
            'consecutive_failures' => $job->consecutive_failures + 1,
        ])->save();

        return $execution;
    }

    private function trim(?string $value, int $limit = self::MAX_OUTPUT_CHARS): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit)."\n… (اقتُطعت المخرجات)"
            : $value;
    }

    private function hostname(): string
    {
        return mb_substr((string) (gethostname() ?: 'unknown'), 0, 120);
    }
}
