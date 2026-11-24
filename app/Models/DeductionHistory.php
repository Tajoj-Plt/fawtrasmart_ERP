<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل عمليات الخصمية (audit trail).
 */
class DeductionHistory extends Model
{
    use HasFactory;

    protected $table = 'deduction_histories';

    public const ACTION_CREATED            = 'created';
    public const ACTION_UPDATED            = 'updated';
    public const ACTION_APPROVED           = 'approved';
    public const ACTION_REJECTED           = 'rejected';
    public const ACTION_CANCELLED          = 'cancelled';
    public const ACTION_SETTLED            = 'settled';
    public const ACTION_PARTIALLY_SETTLED  = 'partially_settled';

    public static function actionLabels(): array
    {
        return [
            self::ACTION_CREATED            => 'إنشاء',
            self::ACTION_UPDATED            => 'تعديل',
            self::ACTION_APPROVED           => 'اعتماد',
            self::ACTION_REJECTED           => 'رفض',
            self::ACTION_CANCELLED          => 'إلغاء',
            self::ACTION_SETTLED            => 'تسوية',
            self::ACTION_PARTIALLY_SETTLED  => 'تسوية جزئية',
        ];
    }

    protected $fillable = [
        'deduction_id',
        'action',
        'user_id',
        'description',
        'changes',
        'notes',
        'amount',
    ];

    protected $casts = [
        'changes' => 'array',
        'amount'  => 'decimal:2',
    ];

    public function deduction(): BelongsTo
    {
        return $this->belongsTo(Deduction::class, 'deduction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActionLabelAttribute(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }
}
