<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * طلبُ عملٍ إضافيّ — طلب ← اعتماد ← أثرٌ على القسيمة.
 *
 * على نمط `AttendanceCorrectionRequest`: جدولٌ واحدٌ يحمل الحالة، وصلاحيةُ
 * الاعتماد منفصلةٌ عن صلاحية الطلب، والقفلُ (`lockForUpdate`) قبل فحص
 * `canBeDecided()` يمنع اعتمادين متزامنين.
 */
class OvertimeRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id', 'overtime_date',
        'requested_minutes', 'approved_minutes',
        'status', 'reason',
        'requested_by', 'approved_by', 'approved_at',
        'rejection_reason', 'admin_notes',
    ];

    protected $casts = [
        'overtime_date' => 'date',
        'approved_at' => 'datetime',
        'requested_minutes' => 'integer',
        'approved_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** أينتظر بتّاً؟ — الحارسُ الوحيد ضد الاعتماد المزدوج. */
    public function canBeDecided(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * سقفُ الدقائق المعتمَدة لهذا الطلب.
     *
     * `approved_minutes` قد يكون فارغاً حين يعتمد المديرُ ما طُلب بحرفه،
     * فيُقرأ عندها المطلوبُ نفسُه. وفراغُه لا يعني «بلا سقف» — فطلبٌ بلا
     * سقفٍ يُبطل الطبقةَ كلَّها.
     */
    public function effectiveMinutes(): int
    {
        return (int) ($this->approved_minutes ?? $this->requested_minutes);
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_PENDING => 'قيد الاعتماد',
            self::STATUS_APPROVED => 'معتمَد',
            self::STATUS_REJECTED => 'مرفوض',
            self::STATUS_CANCELLED => 'ملغى',
        ][$this->status] ?? $this->status;
    }
}
