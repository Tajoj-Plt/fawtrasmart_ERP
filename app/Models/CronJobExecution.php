<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تشغيلة واحدة لمهمة مجدولة.
 *
 * يُكتَب الصفّ بحالة `running` قبل بدء التنفيذ لا بعده: لو انهارت العملية
 * في منتصفها بقي أثرها معلّقاً ظاهراً بدل أن يختفي بلا خبر.
 */
class CronJobExecution extends Model
{
    use \App\Models\Concerns\HasTranslatedLabels;

    protected $table = 'cron_job_executions';

    protected $fillable = [
        'cron_job_id', 'task_name', 'trigger', 'status',
        'started_at', 'finished_at', 'duration_ms', 'exit_code',
        'output', 'error_message', 'environment', 'hostname', 'executed_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_PENDING => ['label' => 'في الانتظار', 'color' => 'secondary', 'icon' => 'clock'],
        self::STATUS_RUNNING => ['label' => 'قيد التنفيذ', 'color' => 'info', 'icon' => 'loader'],
        self::STATUS_SUCCESS => ['label' => 'نجحت', 'color' => 'success', 'icon' => 'check-circle'],
        self::STATUS_FAILED => ['label' => 'فشلت', 'color' => 'danger', 'icon' => 'x-circle'],
        self::STATUS_SKIPPED => ['label' => 'تُخطّيت', 'color' => 'warning', 'icon' => 'skip-forward'],
    ];

    public const TRIGGERS = [
        'schedule' => ['label' => 'المجدول', 'icon' => 'clock'],
        'manual' => ['label' => 'تشغيل يدوي', 'icon' => 'play'],
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(CronJob::class, 'cron_job_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /** الحالات والمصادر بتسمياتها المترجَمة. */
    public static function statuses(): array
    {
        return self::withLabels(self::STATUSES, 'cron.exec_statuses.');
    }

    public static function triggers(): array
    {
        return self::withLabels(self::TRIGGERS, 'cron.triggers.');
    }

    private static function withLabels(array $list, string $prefix): array
    {
        $out = [];

        foreach ($list as $key => $meta) {
            $out[$key] = ['label' => self::tr($prefix.$key, $meta['label'])] + $meta;
        }

        return $out;
    }

    public function statusLabel(): string
    {
        return isset(self::STATUSES[$this->status])
            ? self::tr('cron.exec_statuses.'.$this->status, self::STATUSES[$this->status]['label'])
            : $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    public function triggerLabel(): string
    {
        return isset(self::TRIGGERS[$this->trigger])
            ? self::tr('cron.triggers.'.$this->trigger, self::TRIGGERS[$this->trigger]['label'])
            : $this->trigger;
    }

    /** المدّة بصيغة مقروءة — الأرقام بالمللي ثانية غير مفيدة في جدول. */
    public function humanDuration(): string
    {
        if ($this->duration_ms === null) {
            return '—';
        }

        if ($this->duration_ms < 1000) {
            return __('settings.cron.ms', ['count' => $this->duration_ms]);
        }

        $seconds = $this->duration_ms / 1000;

        if ($seconds < 60) {
            return __('settings.cron.seconds', ['count' => number_format($seconds, 1)]);
        }

        return __('settings.cron.minutes_seconds', [
            'm' => floor($seconds / 60),
            's' => round(fmod($seconds, 60)),
        ]);
    }
}
