<?php

namespace App\Services\Cron;

use App\Models\CronJob;
use App\Models\CronJobExecution;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * منطق إدارة المهام المجدولة: الإنشاء والتعديل والتحكّم في الحالة.
 *
 * كل ما يعدّل صفّ مهمة يمرّ من هنا — فبناء `cron_expression` وتنقية
 * المعاملات وحساب الموعد التالي تحدث في مكان واحد لا في كل مُتحكّم.
 */
class CronJobService
{
    public function __construct(private CronJobRunner $runner) {}

    /**
     * إنشاء مهمة جديدة من مدخلات مُتحقَّق منها.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): CronJob
    {
        $attributes = $this->prepare($data);

        $attributes['key'] = $this->uniqueKey($data['key'] ?? $data['name'] ?? 'task');
        // المهام المنشأة من الواجهة ليست نظامية أبداً — النظامية يزرعها
        // الـ seeder وحده، وهي وحدها الممنوعة من الحذف
        $attributes['is_system'] = false;
        $attributes['created_by'] = $userId;
        $attributes['updated_by'] = $userId;

        $job = CronJob::create($attributes);

        $job->forceFill(['next_run_at' => $job->nextRunDate()])->save();

        return $job;
    }

    /**
     * تعديل مهمة قائمة.
     *
     * `key` و`is_system` لا يُعدَّلان: الأول يربط المهام النظامية بالـ seeder،
     * والثاني هو ما يمنع حذفها.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CronJob $job, array $data, ?int $userId = null): CronJob
    {
        $attributes = $this->prepare($data, $job);
        $attributes['updated_by'] = $userId;

        $job->update($attributes);

        $job->forceFill(['next_run_at' => $job->nextRunDate()])->save();

        return $job->refresh();
    }

    /**
     * تجهيز الحقول المشتركة بين الإنشاء والتعديل.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?CronJob $existing = null): array
    {
        $handler = $data['handler'] ?? $existing?->handler;

        // خطّ الدفاع الأخير قبل قاعدة البيانات: لا يُحفَظ مُعالِج غير مسموح
        if (! CronRegistry::isAllowed($handler)) {
            throw new \InvalidArgumentException('المُعالِج المحدَّد غير مدرَج في قائمة الأوامر المسموحة.');
        }

        // والمخفيّ لا يُنشأ من هنا ولو كان مسموح التنفيذ: مهمّته الوحيدة
        // مزروعة ومحميّة، ونسخةٌ ثانية منها تُضاعف النداءات الخارجية
        if (CronRegistry::isHidden($handler) && $existing?->handler !== $handler) {
            throw new \InvalidArgumentException('هذا المُعالِج يُدار من النظام ولا تُنشأ له مهمّة يدوياً.');
        }

        $scheduleType = $data['schedule_type'] ?? $existing?->schedule_type ?? 'daily';
        $time = $data['schedule_time'] ?? $existing?->schedule_time;
        $day = isset($data['schedule_day']) ? (int) $data['schedule_day'] : $existing?->schedule_day;

        $expression = CronScheduleParser::build(
            $scheduleType,
            $time ? (string) $time : null,
            $day,
            $data['cron_expression'] ?? $existing?->cron_expression
        );

        $meta = CronRegistry::find($handler);

        return [
            'name' => $data['name'] ?? $existing?->name ?? CronRegistry::labelFor($handler),
            'description' => $data['description'] ?? $existing?->description ?? ($meta['description'] ?? null),
            'type' => $data['type'] ?? $meta['type'] ?? 'custom',
            'handler' => $handler,
            'parameters' => CronRegistry::sanitizeParameters($handler, $data['parameters'] ?? null),
            'schedule_type' => $scheduleType,
            'cron_expression' => $expression,
            'schedule_time' => $time,
            'schedule_day' => $day,
            'timezone' => $data['timezone'] ?? $existing?->timezone,
            'status' => $data['status'] ?? $existing?->status ?? CronJob::STATUS_ACTIVE,
            'prevent_overlap' => (bool) ($data['prevent_overlap'] ?? $existing?->prevent_overlap ?? true),
            'timeout' => max(10, min(7200, (int) ($data['timeout'] ?? $existing?->timeout ?? 300))),
            'notify_on_failure' => (bool) ($data['notify_on_failure'] ?? $existing?->notify_on_failure ?? true),
            'notify_on_success' => (bool) ($data['notify_on_success'] ?? $existing?->notify_on_success ?? false),
        ];
    }

    // ==================== التحكّم في الحالة ====================

    public function enable(CronJob $job, ?int $userId = null): CronJob
    {
        return $this->setStatus($job, CronJob::STATUS_ACTIVE, $userId);
    }

    public function disable(CronJob $job, ?int $userId = null): CronJob
    {
        return $this->setStatus($job, CronJob::STATUS_DISABLED, $userId);
    }

    public function pause(CronJob $job, ?int $userId = null): CronJob
    {
        return $this->setStatus($job, CronJob::STATUS_PAUSED, $userId);
    }

    public function resume(CronJob $job, ?int $userId = null): CronJob
    {
        return $this->setStatus($job, CronJob::STATUS_ACTIVE, $userId);
    }

    protected function setStatus(CronJob $job, string $status, ?int $userId): CronJob
    {
        $job->forceFill([
            'status' => $status,
            'updated_by' => $userId,
            // المهمة المعطَّلة أو الموقوفة لا موعد تالٍ لها — إظهار موعد
            // لن يأتي أسوأ من عدم إظهار شيء
            'next_run_at' => $status === CronJob::STATUS_ACTIVE ? $job->nextRunDate() : null,
        ])->save();

        return $job;
    }

    /** تشغيل فوري بطلب من المسؤول. */
    public function runNow(CronJob $job, ?int $userId = null): CronJobExecution
    {
        return $this->runner->run($job, 'manual', $userId);
    }

    /**
     * حذف مهمة.
     *
     * المهام النظامية تُعطَّل ولا تُحذف: صفّها هو ما يربط وظيفة قائمة في
     * النظام بجدولتها، وحذفه يوقف الوظيفة بلا أثر ظاهر.
     */
    public function delete(CronJob $job): void
    {
        if (! $job->isDeletable()) {
            throw new \RuntimeException('لا يمكن حذف مهمة نظامية — يمكن تعطيلها بدلاً من ذلك.');
        }

        DB::transaction(function () use ($job) {
            // القفل قد يكون مأخوذاً لتشغيلة جارية؛ تركه يمنع مهمة جديدة
            // بالمعرّف نفسه من العمل حتى تنتهي مدّته
            Cache::forget($job->lockKey());
            $job->delete();
        });
    }

    /**
     * مفتاح فريد مشتقّ من الاسم.
     */
    protected function uniqueKey(string $source): string
    {
        $base = Str::slug(Str::limit($source, 80, ''), '_') ?: 'task';
        $base = 'custom_'.$base;
        $key = $base;
        $suffix = 1;

        while (CronJob::where('key', $key)->exists()) {
            $key = $base.'_'.(++$suffix);
        }

        return mb_substr($key, 0, 120);
    }
}
