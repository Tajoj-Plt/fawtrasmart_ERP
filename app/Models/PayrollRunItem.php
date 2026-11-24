<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند داخل عملية صرف الراتب — استحقاق / خصم / سلفة / تسوية رجعية.
 *
 * source_table + source_id يربطان البند بالسجل الأصلي (للتتبع):
 *   - salary_items          → بند راتب من العقد
 *   - deductions            → خصمية
 *   - salary_advance_requests → سلفة من النظام B
 *   - installment_payments  → قسط من النظام A
 *   - (null, type=retro)    → فروقات زيادة بأثر رجعي على الصرفة المعلّقة
 */
class PayrollRunItem extends Model
{
    protected $table = 'payroll_run_items';

    public const TYPE_ADDITION = 'addition';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_ADVANCE = 'advance';

    /** مساهمة المنشأة في التأمينات — لا تُنقص صافي الراتب. */
    public const TYPE_EMPLOYER = 'employer';

    /** فروقات زيادة بأثر رجعي — تُضاف إلى الصافي عند الصرف. */
    public const TYPE_RETRO = 'retro';

    protected $fillable = [
        'payroll_run_id', 'item_type', 'source_table', 'source_id',
        'name', 'amount', 'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->item_type) {
            self::TYPE_ADDITION => 'استحقاق',
            self::TYPE_DEDUCTION => 'خصم',
            self::TYPE_ADVANCE => 'تسديد سلفة',
            self::TYPE_EMPLOYER => 'مساهمة منشأة',
            self::TYPE_RETRO => 'تسوية رجعية',
            default => $this->item_type,
        };
    }
}
