<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عملية استرجاع واحدة.
 *
 * الاسترجاع يكتب فوق قاعدة البيانات كاملة، فأثره مسجَّل مستقلاً: من نفّذه
 * ومن أي عنوان IP ومن أي نسخة، وأي نسخة أمان أُخذت قبله.
 */
class BackupRestore extends Model
{
    use HasTranslatedLabels;

    protected $table = 'backup_restores';

    protected $fillable = [
        'backup_id', 'safety_backup_id', 'status',
        'started_at', 'completed_at', 'duration_ms',
        'statements_executed', 'error_message', 'output',
        'executed_by', 'ip_address',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'statements_executed' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING => ['label' => 'في الانتظار', 'color' => 'secondary'],
        self::STATUS_RUNNING => ['label' => 'قيد التنفيذ', 'color' => 'info'],
        self::STATUS_COMPLETED => ['label' => 'اكتمل', 'color' => 'success'],
        self::STATUS_FAILED => ['label' => 'فشل', 'color' => 'danger'],
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class, 'backup_id');
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(Backup::class, 'safety_backup_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /** الحالات بتسمياتها المترجَمة. */
    public static function statuses(): array
    {
        $out = [];

        foreach (self::STATUSES as $key => $meta) {
            $out[$key] = ['label' => self::tr('backup.restore_statuses.'.$key, $meta['label'])] + $meta;
        }

        return $out;
    }

    public function statusLabel(): string
    {
        return isset(self::STATUSES[$this->status])
            ? self::tr('backup.restore_statuses.'.$this->status, self::STATUSES[$this->status]['label'])
            : $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'secondary';
    }
}
