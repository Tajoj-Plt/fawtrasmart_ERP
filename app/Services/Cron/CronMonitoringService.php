<?php

namespace App\Services\Cron;

use App\Models\Backup;
use App\Models\CronJob;
use App\Models\CronJobExecution;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * مراقبة صحّة المجدول وإحصاءات المهام.
 *
 * السؤال الذي يجيب عنه هذا الصنف: هل `schedule:run` يعمل على الخادم أصلاً؟
 * نظام مهام بلا هذا الفحص يبدو سليماً تماماً وهو متوقّف — الصفحة خضراء
 * والمهام «نشطة» ولا شيء يُنفَّذ.
 */
class CronMonitoringService
{
    /** بعد هذه المدّة بلا نبض يُعتبر المجدول متوقّفاً. */
    public const HEARTBEAT_FAILED_MINUTES = 15;

    /** وبعد هذه يُعرَض تحذير قبل أن يُعلَن التوقّف. */
    public const HEARTBEAT_WARNING_MINUTES = 5;

    /**
     * تسجيل نبضة — يُستدعى من `cron:run-due` كل دقيقة سواء استُحقّت مهمة أم لا.
     *
     * يُكتَب في ملف لا في الذاكرة المؤقتة: `cache:clear` جزء من روتين النشر
     * العادي، ومحوُه للنبض يجعل النظام يبلّغ عن عطل لا وجود له.
     */
    public function recordHeartbeat(): void
    {
        try {
            $path = $this->heartbeatPath();

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, json_encode([
                'at' => now()->toIso8601String(),
                'host' => gethostname() ?: null,
                'env' => app()->environment(),
                // من أطلق الدورة: Cron الاستضافة، أو زيارةُ ويب، أو رابطُ النبض
                // — `SchedulerTick` يقرؤه ليتنحّى ما دام Cron حقيقيّ ينبض
                'source' => app()->bound('scheduler.tick_source')
                    ? (string) app('scheduler.tick_source')
                    : SchedulerTick::SOURCE_CRON,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // تعذّر الكتابة (صلاحيات storage) لا يبرّر إسقاط دورة المجدول،
            // لكنه سبب مباشر لظهور «المجدول متوقّف» زوراً — فيُسجَّل
            Log::warning('تعذّر تسجيل نبض المجدول', ['error' => $e->getMessage()]);
        }
    }

    /** آخر نبضة مسجَّلة، أو null إن لم تُسجَّل أي نبضة بعد. */
    public function lastHeartbeat(): ?Carbon
    {
        try {
            $path = $this->heartbeatPath();

            if (is_file($path)) {
                $data = json_decode((string) file_get_contents($path), true);

                if (! empty($data['at'])) {
                    return Carbon::parse($data['at']);
                }
            }
        } catch (\Throwable) {
            // ملف تالف يُعامَل كغياب نبض، ويتكفّل الرجوع أدناه بالباقي
        }

        // الرجوع إلى آخر تشغيلة فعلية: أضعف دقّة لكنه دليل قاطع على أن
        // المجدول كان يعمل في ذلك الوقت
        $lastExecution = CronJobExecution::max('started_at');

        return $lastExecution ? Carbon::parse($lastExecution) : null;
    }

    /** مصدرُ آخر نبضة: `cron` أو `web` أو `ping` (نبضةٌ قديمة بلا مصدر = cron). */
    public function lastHeartbeatSource(): string
    {
        try {
            $path = $this->heartbeatPath();

            if (is_file($path)) {
                $data = json_decode((string) file_get_contents($path), true);

                if (! empty($data['at'])) {
                    return (string) ($data['source'] ?? SchedulerTick::SOURCE_CRON);
                }
            }
        } catch (\Throwable) {
        }

        return SchedulerTick::SOURCE_CRON;
    }

    /**
     * حالة المجدول.
     *
     * @return array{status:string,label:string,color:string,last_heartbeat:?Carbon,minutes_ago:?int,message:string}
     */
    public function schedulerHealth(): array
    {
        $last = $this->lastHeartbeat();

        if ($last === null) {
            return [
                'status' => 'unknown',
                'label' => 'غير معروفة',
                'color' => 'secondary',
                'last_heartbeat' => null,
                'minutes_ago' => null,
                'message' => SchedulerTick::enabled()
                    ? 'لم تُسجَّل أي نبضة بعد. المجدول الداخلي مفعّل، فستُسجَّل أول نبضة مع أول زيارة للنظام.'
                    : 'لم تُسجَّل أي نبضة بعد. أضِف مهمة Cron على الخادم تنفّذ '
                        .'«php artisan schedule:run» كل دقيقة.',
            ];
        }

        $minutes = (int) $last->diffInMinutes(now());

        if ($minutes >= self::HEARTBEAT_FAILED_MINUTES) {
            return [
                'status' => 'failed',
                'label' => 'متوقّف',
                'color' => 'danger',
                'last_heartbeat' => $last,
                'minutes_ago' => $minutes,
                'message' => 'انقطع نبض المجدول منذ '.$minutes.' دقيقة. لن تُنفَّذ أي مهمة مجدولة '
                    .'ولا نسخة احتياطية تلقائية حتى يعود.',
            ];
        }

        if ($minutes >= self::HEARTBEAT_WARNING_MINUTES) {
            return [
                'status' => 'warning',
                'label' => 'تحذير',
                'color' => 'warning',
                'last_heartbeat' => $last,
                'minutes_ago' => $minutes,
                'message' => 'آخر نبضة منذ '.$minutes.' دقيقة — أطول من المتوقّع.',
            ];
        }

        return [
            'status' => 'healthy',
            'label' => 'سليم',
            'color' => 'success',
            'last_heartbeat' => $last,
            'minutes_ago' => $minutes,
            'message' => match ($this->lastHeartbeatSource()) {
                SchedulerTick::SOURCE_WEB => 'المجدول يعمل عبر زيارات النظام (المجدول الداخلي) — لا Cron على الخادم.',
                SchedulerTick::SOURCE_PING => 'المجدول يعمل عبر رابط النبض الخارجي — لا Cron على الخادم.',
                default => 'المجدول يعمل بشكل طبيعي.',
            },
        ];
    }

    /**
     * إحصاءات لوحة المهام.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $statusCounts = CronJob::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $executionCounts = CronJobExecution::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lastExecution = CronJobExecution::with('job')->latest('started_at')->first();

        $nextJob = CronJob::active()
            ->whereNotNull('next_run_at')
            ->orderBy('next_run_at')
            ->first();

        return [
            'total_jobs' => (int) $statusCounts->sum(),
            'active_jobs' => (int) ($statusCounts[CronJob::STATUS_ACTIVE] ?? 0),
            'paused_jobs' => (int) ($statusCounts[CronJob::STATUS_PAUSED] ?? 0),
            'disabled_jobs' => (int) ($statusCounts[CronJob::STATUS_DISABLED] ?? 0),

            'successful_executions' => (int) ($executionCounts[CronJobExecution::STATUS_SUCCESS] ?? 0),
            'failed_executions' => (int) ($executionCounts[CronJobExecution::STATUS_FAILED] ?? 0),
            'skipped_executions' => (int) ($executionCounts[CronJobExecution::STATUS_SKIPPED] ?? 0),
            'running_jobs' => (int) ($executionCounts[CronJobExecution::STATUS_RUNNING] ?? 0),

            'executions_today' => CronJobExecution::whereDate('started_at', today())->count(),
            'failures_today' => CronJobExecution::failed()->whereDate('started_at', today())->count(),

            'last_execution' => $lastExecution,
            'next_job' => $nextJob,
            'next_execution_at' => $nextJob?->next_run_at,

            // المهام التي تفشل باستمرار — هي ما يحتاج تدخّلاً فعلياً
            'stuck_jobs' => CronJob::where('consecutive_failures', '>=', CronJobRunner::STUCK_THRESHOLD)
                ->orderByDesc('consecutive_failures')
                ->get(),

            'scheduler' => $this->schedulerHealth(),
        ];
    }

    /**
     * إحصاءات لوحة النسخ الاحتياطي.
     *
     * @return array<string, mixed>
     */
    public function backupDashboard(): array
    {
        $counts = Backup::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lastSuccessful = Backup::available()->latest('completed_at')->first();

        $backupJob = CronJob::where('handler', 'backup:scheduled-run')->first();

        return [
            'total_backups' => (int) $counts->sum(),
            'successful_backups' => (int) ($counts[Backup::STATUS_COMPLETED] ?? 0),
            'failed_backups' => (int) ($counts[Backup::STATUS_FAILED] ?? 0),
            'running_backups' => (int) ($counts[Backup::STATUS_RUNNING] ?? 0)
                + (int) ($counts[Backup::STATUS_PENDING] ?? 0),

            // حجم الملفات الموجودة فعلاً فقط — النسخ المحذوفة لا تشغل مساحة
            'storage_used_bytes' => (int) Backup::available()->sum('size_bytes'),

            'last_backup' => $lastSuccessful,
            'last_backup_at' => $lastSuccessful?->completed_at,
            'next_backup_at' => $backupJob?->isActive() ? $backupJob->next_run_at : null,
            'backup_job' => $backupJob,
        ];
    }

    private function heartbeatPath(): string
    {
        return storage_path('app/system/cron-heartbeat.json');
    }
}
