<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdvanceRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'advance_type',
        'installments_count',
        'installment_amount',
        'start_month',
        'paid_installments',
        'remaining_installments',
        'reason',
        'status',
        'requested_date',
        'approved_by',
        'approved_date',
        'treasury_id',
        'rejection_reason',
        'admin_notes',
        'is_settled',
        'salary_slip_id',
        'settled_date',
        'settled_amount',
        'settlement_type',
        'approver_user_id',
        'disbursed_at',
        'disbursed_by',
        'disbursement_journal_id',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'cancellation_journal_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'requested_date' => 'date',
        'approved_date' => 'datetime',
        'settled_date' => 'date',
        'is_settled' => 'boolean',
        'disbursed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /*
     * ══════════════ دورةُ حياة السلفة ══════════════
     *
     * `status` يحمل قرارَ المعتمِد وحده (معلّق / معتمد / مرفوض / ملغى)، ولا
     * يقول هل خرج المال ولا كم سُدِّد. والمرحلةُ الفعليّة تُقرأ من ثلاثة أعمدة
     * معاً — فتُحسب هنا في موضعٍ واحد تقرؤه الشاشات كلُّها:
     *
     *   pending    بانتظار الاعتماد
     *   approved   معتمدة — بانتظار الصرف (لا قيدَ ولا اقتطاعَ من الراتب)
     *   disbursed  مصروفة — في دورة السداد، لم يُسدَّد منها شيء
     *   repaying   قيد السداد — سُدِّد جزءٌ منها
     *   settled    مسدَّدة بالكامل
     *   rejected   مرفوضة
     *   cancelled  ملغاة
     *
     * والتسويةُ (سدادٌ نقديٌّ أو اقتطاعٌ من الراتب) تنقلها بين disbursed →
     * repaying → settled ولا تُلغيها؛ والإلغاءُ عمليةٌ مستقلّة.
     */
    public const STAGES = [
        'pending' => ['بانتظار الاعتماد', 'stk-state--warn'],
        'approved' => ['معتمدة — بانتظار الصرف', 'stk-state--warn'],
        'disbursed' => ['مصروفة — في دورة السداد', 'stk-state--on'],
        'repaying' => ['قيد السداد', 'stk-state--on'],
        'settled' => ['مسدَّدة بالكامل', 'stk-state--neutral'],
        'rejected' => ['مرفوضة', 'stk-state--off'],
        'cancelled' => ['ملغاة', 'stk-state--off'],
    ];

    public function stage(): string
    {
        return match ($this->status) {
            'pending' => 'pending',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            default => match (true) {
                ! $this->disbursed_at => 'approved',
                (bool) $this->is_settled => 'settled',
                $this->repaidAmount() > 0.001 => 'repaying',
                default => 'disbursed',
            },
        };
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage()][0];
    }

    public function stageClass(): string
    {
        return self::STAGES[$this->stage()][1];
    }

    /** ما سُدِّد منها حتى الآن — نقداً أو اقتطاعاً من الراتب. */
    public function repaidAmount(): float
    {
        $settled = (float) ($this->settled_amount ?? 0);

        if ($settled <= 0 && $this->isScheduled() && (int) $this->paid_installments > 0) {
            $settled = (int) $this->paid_installments * (float) $this->installment_amount;
        }

        return round(min($settled, (float) $this->amount), 2);
    }

    /** المتبقّي على الموظف منها. */
    public function remainingAmount(): float
    {
        if ($this->is_settled) {
            return 0.0;
        }

        return round(max(0, (float) $this->amount - $this->repaidAmount()), 2);
    }

    public function isDisbursed(): bool
    {
        return $this->status === 'approved' && $this->disbursed_at !== null;
    }

    /**
     * هل بدأ سدادُها؟ — سدادٌ نقديّ، أو قسطٌ مقتطَع، أو إدراجٌ في مسيرِ رواتب
     * قائم (معلّقٍ أو مصروفٍ لم يُعكَس). وهذا ما يمنع الإلغاء: من سُدِّد
     * منها شيءٌ لا تُلغى، بل تُكمَل تسويتُها.
     */
    public function hasRepaymentActivity(): bool
    {
        if ((float) ($this->settled_amount ?? 0) > 0.001 || (int) ($this->paid_installments ?? 0) > 0 || $this->is_settled) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table('payroll_run_items as i')
            ->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->where('i.source_table', 'salary_advance_requests')
            ->where('i.source_id', $this->id)
            ->whereNull('r.reversed_at')
            ->exists();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * التحقق إذا كانت السلفة مجدولة
     */
    public function isScheduled(): bool
    {
        return $this->advance_type === 'scheduled';
    }

    /**
     * الحصول على الأقساط المتبقية
     */
    public function getRemainingInstallmentsCountAttribute(): int
    {
        if (!$this->isScheduled()) {
            return 0;
        }
        return max(0, ($this->installments_count ?? 0) - ($this->paid_installments ?? 0));
    }

    /**
     * العلاقة مع الموظف
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * العلاقة مع المستخدم الذي وافق على الطلب
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * العلاقة مع الخزينة
     */
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeUnsettled($query)
    {
        return $query->where('status', 'approved')
                     ->where('is_settled', false);
    }

    public function scopeSettled($query)
    {
        return $query->where('is_settled', true);
    }

    /**
     * العلاقة مع قسيمة الراتب
     */
    public function salarySlip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slip_id');
    }

    /**
     * التحقق من إمكانية تعديل الطلب
     */
    public function canBeEdited(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * التحقق من إمكانية الموافقة على الطلب
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }
}
