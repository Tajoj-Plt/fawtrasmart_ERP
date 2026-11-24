<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مسير صرفات: مجموعة payroll_runs تُعتمد دفعة واحدة قبل الصرف.
 */
class PayrollBatch extends Model
{
    protected $table = 'payroll_batches';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'name', 'payroll_id', 'period_from', 'period_to',
        'status', 'approved_at', 'approved_by', 'created_by', 'notes',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'approved_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'بانتظار الاعتماد',
            self::STATUS_APPROVED => 'معتمد',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'payroll_batch_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    /**
     * حالُ الصرف داخل المسير — ما لا يقوله عمودُ الحالة وحده.
     *
     * «معتمد» يعني أنّ الصرفَ مصرَّحٌ به، لا أنّه تمّ. فالمسيرُ المعتمد
     * يُقرأ: بانتظار الصرف، أو صرفٌ جزئي، أو مدفوعٌ بالكامل.
     *
     * @return array{key:string,label:string,tone:string,paid:int,open:int,total:int}
     */
    public function disbursementState(): array
    {
        $runs = $this->relationLoaded('runs') ? $this->runs : $this->runs()->get(['id', 'status', 'held_at']);

        $total = $runs->count();
        $paid = $runs->where('status', PayrollRun::STATUS_DISBURSED)->count();
        $open = $runs->where('status', PayrollRun::STATUS_PENDING)->count();

        [$key, $label, $tone] = match (true) {
            $this->isPending() => ['pending', 'بانتظار الاعتماد', 'warn'],
            $total > 0 && $open === 0 && $paid > 0 => ['paid', 'مدفوع بالكامل', 'on'],
            $paid > 0 => ['partial', 'معتمد — صرفٌ جزئي', 'neutral'],
            $total > 0 && $open === 0 => ['closed', 'مقفل بلا صرف', 'off'],
            default => ['ready', 'معتمد — بانتظار الصرف', 'neutral'],
        };

        return compact('key', 'label', 'tone', 'paid', 'open', 'total');
    }
}
