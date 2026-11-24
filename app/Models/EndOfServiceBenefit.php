<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مكافأة نهاية الخدمة لموظف أُنهيت خدمتُه.
 *
 * المسار: مسودة (حساب) → معتمدة (قيد المخصص) → مدفوعة (قيد التسوية).
 */
class EndOfServiceBenefit extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    /**
     * سُوِّيت إلى ذمّة الموظف: قيدُ المخصص أُقفل إلى المستحقات، ولم يخرج نقد.
     *
     * كانت هذه هي الحالةُ الموسومةَ `paid` — والمكافأةُ حينها التزامٌ قائمٌ
     * لا مبلغٌ مدفوع.
     */
    public const STATUS_SETTLED = 'settled';

    /** صُرفت نقداً من خزينة: سندُ صرفٍ وقيدٌ أنقص الرصيد. */
    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'status',
        'employment_status',
        'hire_date',
        'last_working_day',
        'years_of_service',
        'monthly_wage',
        'full_amount',
        'fraction',
        'payable_amount',
        'component_type_id',
        'notes',
        'accrual_journal_entry_id',
        'settlement_journal_entry_id',
        'approved_at',
        'approved_by',
        'settled_at',
        'settled_by',
        'treasury_id',
        'expense_id',
        'payment_journal_entry_id',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'last_working_day' => 'date',
        'years_of_service' => 'decimal:4',
        'monthly_wage' => 'decimal:2',
        'full_amount' => 'decimal:2',
        'fraction' => 'decimal:4',
        'payable_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'settled_at' => 'datetime',
        'paid_at' => 'datetime',
        'employment_status' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(SalaryComponentType::class, 'component_type_id');
    }

    public function accrualJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'accrual_journal_entry_id');
    }

    public function settlementJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'settlement_journal_entry_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** التسويةُ إلى الذمّة تسبق الصرف، فلا تُصرف مكافأةٌ لم تُسوَّ. */
    public function canBeSettled(): bool
    {
        return $this->isApproved();
    }

    /** ولا تُصرف نقداً إلا ما سُوِّي إلى ذمّة الموظف. */
    public function canBeDisbursed(): bool
    {
        return $this->isSettled();
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function paymentJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'مسودة',
            self::STATUS_APPROVED => 'معتمدة',
            self::STATUS_SETTLED => 'مستحقة للموظف',
            self::STATUS_PAID => 'مدفوعة',
            self::STATUS_CANCELLED => 'ملغاة',
        ];
    }

    /** نبرةُ `stk-state--*` للحالة */
    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'on',
            self::STATUS_SETTLED, self::STATUS_APPROVED => 'neutral',
            self::STATUS_DRAFT => 'warn',
            default => 'off',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'مسودة',
            self::STATUS_APPROVED => 'معتمدة',
            self::STATUS_SETTLED => 'مستحقة للموظف',
            self::STATUS_PAID => 'مدفوعة',
            self::STATUS_CANCELLED => 'ملغاة',
            default => $this->status,
        };
    }

    public function reasonLabel(): string
    {
        return EmploymentStatus::fromValue($this->employment_status)->label();
    }
}
