<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * نسخة احتياطية واحدة.
 *
 * الصفّ يبقى بعد حذف الملف (`file_deleted_at`) — تاريخ ما أُخذ ومتى وحُذف
 * متى جزء من الأثر، وحذفه مع الملف يمحو الدليل على أن النسخة وُجدت أصلاً.
 */
class Backup extends Model
{
    use HasTranslatedLabels;

    protected $table = 'backups';

    protected $fillable = [
        'uuid', 'type', 'table_mode', 'tables', 'trigger', 'disk', 'path', 'file_name', 'size_bytes',
        'is_encrypted', 'status', 'started_at', 'completed_at', 'duration_ms',
        'error_message', 'output', 'is_locked', 'file_deleted_at',
        'created_by', 'cron_job_id',
    ];

    protected $casts = [
        'tables' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'file_deleted_at' => 'datetime',
        'is_locked' => 'boolean',
        'is_encrypted' => 'boolean',
        'size_bytes' => 'integer',
        'duration_ms' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING => ['label' => 'في الانتظار', 'color' => 'secondary', 'icon' => 'clock'],
        self::STATUS_RUNNING => ['label' => 'قيد التنفيذ', 'color' => 'info', 'icon' => 'loader'],
        self::STATUS_COMPLETED => ['label' => 'مكتملة', 'color' => 'success', 'icon' => 'check-circle'],
        self::STATUS_FAILED => ['label' => 'فشلت', 'color' => 'danger', 'icon' => 'x-circle'],
    ];

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_PRE_RESTORE = 'pre_restore';

    /**
     * نسخة الأمان التي تأخذها شاشة «تحديث النظام» قبل تشغيل الترحيلات.
     *
     * الترحيل لا يُتراجَع عنه من الشاشة (لا `rollback` هناك عمداً)، وبعضُ
     * الترحيلات يحذف أعمدةً وجداول. فهذه النسخة هي طريق العودة الوحيد إن
     * تبيّن أن التحديث أخذ معه ما لم يكن يُقصد أخذُه.
     */
    public const TRIGGER_PRE_UPDATE = 'pre_update';

    public const TRIGGERS = [
        self::TRIGGER_MANUAL => ['label' => 'يدوي', 'icon' => 'user'],
        self::TRIGGER_SCHEDULED => ['label' => 'مجدول', 'icon' => 'clock'],
        self::TRIGGER_PRE_RESTORE => ['label' => 'أمان قبل الاسترجاع', 'icon' => 'shield'],
        self::TRIGGER_PRE_UPDATE => ['label' => 'أمان قبل التحديث', 'icon' => 'shield'],
    ];

    protected static function booted(): void
    {
        static::creating(function (self $backup) {
            $backup->uuid ??= (string) Str::uuid();
        });
    }

    // ==================== العلاقات ====================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cronJob(): BelongsTo
    {
        return $this->belongsTo(CronJob::class, 'cron_job_id');
    }

    public function restores(): HasMany
    {
        return $this->hasMany(BackupRestore::class, 'backup_id');
    }

    // ==================== النطاقات ====================

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /** النسخ التي ما زال ملفها على القرص — وحدها القابلة للتنزيل أو الحذف. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED)->whereNull('file_deleted_at');
    }

    /**
     * المرشَّحات للتنظيف التلقائي.
     *
     * نسختا الأمان — قبل الاسترجاع وقبل التحديث — مستثنيتان: كلٌّ منهما طريقُ
     * العودة الوحيد من عمليةٍ لا تُتراجَع، وحذفُها آلياً يلغي معناها.
     */
    public function scopeCleanable(Builder $query): Builder
    {
        return $query->available()
            ->where('is_locked', false)
            ->whereNotIn('trigger', [self::TRIGGER_PRE_RESTORE, self::TRIGGER_PRE_UPDATE]);
    }

    // ==================== الحالة ====================

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->file_deleted_at === null;
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    /** الاسترجاع من نسخة ملفات فقط لا معنى له — لا بيانات فيها. */
    public function isRestorable(): bool
    {
        return $this->isAvailable() && in_array($this->type, ['database', 'both'], true);
    }

    /**
     * هل هذه نسخة جداول مختارة لا نسخة كاملة؟
     *
     * الفرق جوهري عند الاسترجاع: النسخة الجزئية تكتب فوق جداولها وحدها
     * وتترك الباقي كما هو، فتنتج قاعدة نصفها قديم ونصفها جديد. الصفوف
     * القديمة (قبل هذه الميزة) بلا `table_mode` وهي كاملة بحكم التعريف.
     */
    public function isPartial(): bool
    {
        return in_array($this->table_mode, ['include', 'exclude'], true);
    }

    public function tableCount(): int
    {
        return count($this->tables ?? []);
    }

    /**
     * أسماء أقسام البيانات التي تشملها هذه النسخة.
     *
     * الشاشة كانت تسرد أسماء الجداول كما هي — وهي بنية النظام لا معلومة
     * يحتاجها من يقرأ تفاصيل نسخة. الأقسام تقول ما يهمّه فعلاً: «العملاء
     * والمبيعات والمخزون».
     *
     * @return array<int, string>
     */
    public function sectionLabels(): array
    {
        return \App\Services\Backup\DataSectionCatalog::labelsForTables($this->tables);
    }

    /** وصف نطاق البيانات بجملة واحدة. */
    public function tableScopeLabel(): string
    {
        return match ($this->table_mode) {
            'include' => __('settings.backup.scope_include_backup', ['count' => count($this->sectionLabels())]),
            'exclude' => __('settings.backup.scope_exclude_backup', ['count' => count($this->sectionLabels())]),
            default => self::tr('backup.table_modes.all', 'كل البيانات'),
        };
    }

    /**
     * اسم النسخة كما يُعرَض للمستخدم.
     *
     * اسم الملفّ (`backup-2026-08-15-021200.sql.gz`) يقول للمسؤول أقلّ ممّا
     * يقوله لغيره: صيغة الأرشيف ونمط التسمية وأداة النسخ. والتاريخ والنوع
     * هما ما يميّز نسخةً من أخرى فعلاً.
     */
    public function displayName(): string
    {
        $moment = $this->completed_at ?? $this->created_at;

        return __('settings.backup.display_name', [
            'type' => $this->typeLabel(),
            'date' => $moment?->format('Y-m-d H:i') ?? '—',
        ]);
    }

    /** الحالات والمصادر بتسمياتها المترجَمة — للمرشّحات في الشاشات. */
    public static function statuses(): array
    {
        return self::withLabels(self::STATUSES, 'backup.statuses.');
    }

    public static function triggers(): array
    {
        return self::withLabels(self::TRIGGERS, 'backup.triggers.');
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
            ? self::tr('backup.statuses.'.$this->status, self::STATUSES[$this->status]['label'])
            : $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }

    public function typeLabel(): string
    {
        return isset(BackupSetting::TYPES[$this->type])
            ? self::tr('backup.types.'.$this->type, BackupSetting::TYPES[$this->type]['label'])
            : $this->type;
    }

    public function triggerLabel(): string
    {
        return isset(self::TRIGGERS[$this->trigger])
            ? self::tr('backup.triggers.'.$this->trigger, self::TRIGGERS[$this->trigger]['label'])
            : $this->trigger;
    }

    public function humanSize(): string
    {
        return static::formatBytes($this->size_bytes);
    }

    public function humanDuration(): string
    {
        if ($this->duration_ms === null) {
            return '—';
        }

        $seconds = $this->duration_ms / 1000;

        if ($seconds < 60) {
            return __('settings.backup.seconds_short', ['count' => number_format($seconds, 1)]);
        }

        return __('settings.backup.minutes_seconds', [
            'm' => floor($seconds / 60),
            's' => round(fmod($seconds, 60)),
        ]);
    }

    /** هل الملف موجود فعلاً على القرص؟ الصفّ وحده ليس ضماناً. */
    public function fileExists(): bool
    {
        if (! $this->path || $this->file_deleted_at) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->exists($this->path);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '—';
        }

        $units = __('settings.backup.bytes');

        if (! is_array($units)) {
            $units = ['بايت', 'ك.ب', 'م.ب', 'ج.ب', 'ت.ب'];
        }

        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
