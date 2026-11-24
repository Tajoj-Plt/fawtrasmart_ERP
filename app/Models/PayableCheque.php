<?php

namespace App\Models;

use App\Enums\ChequeStatus;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayableCheque extends Model
{
    /*
        الفترةُ المحاسبية تُختَم لحظةَ الإنشاء من تاريخ المستند، فتثبت معه
        ما بقي — بدل أن تُستنتَج بمطابقة التاريخ في كلّ شاشة.
    */
    use BelongsToAccountingPeriod;

    /**
     * فرعُ المستند: يُختَم عند الإنشاء (`StampsOwnerContext`) ويُصفّى عند
     * القراءة (`BelongsToBranch::scopeForBranch`).
     *
     * كان الجدول بلا عمود فرعٍ إطلاقاً، فتبديلُ الفرع في الشريط العلوي لا
     * يجد ما يُصفّي به. والصفوفُ التي سبقت العمود تبقى ظاهرةً في كل الفروع
     * (`orWhereNull`) فلا يختفي تاريخٌ عند التشغيل.
     */
    use BelongsToBranch, StampsOwnerContext;

    /** التاريخُ الحاكم لفترة هذا المستند. */
    public function accountingDateColumn(): string
    {
        return 'issue_date';
    }

    use HasDocumentCurrency, HasFactory;

    protected $table = 'payable_cheques';

    protected $fillable = [
        'id', 'amount', 'issue_date', 'due_date', 'bank_id', 'cheque_book_id',
        'cheque_number', 'recipient_account_id', 'payee_name', 'description',
        'attachment', 'status', 'journal_entry_id', 'cash_journal_entry_id',
        'cashed_at', 'rejected_at', 'cancelled_at', 'supplier_id', 'created_by',
        'created_at', 'updated_at', 'currency',

        'accounting_period_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'cashed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2',
        'status' => ChequeStatus::class,
    ];

    protected $attributes = [
        'status' => 'issued',
    ];

    // ═══════════════════════════════════════════════════════════════
    // العلاقات (Relationships)
    // ═══════════════════════════════════════════════════════════════

    /**
     * حساب البنك المسحوب عليه (من جدول الحسابات)
     */
    public function bank()
    {
        return $this->belongsTo(Account::class, 'bank_id');
    }

    /**
     * دفتر الشيكات
     */
    public function chequeBook()
    {
        return $this->belongsTo(ChequeBook::class, 'cheque_book_id');
    }

    /**
     * حساب المستفيد (المورد أو الدائن)
     */
    public function recipientAccount()
    {
        return $this->belongsTo(Account::class, 'recipient_account_id');
    }

    /**
     * المورد المستفيد
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * قيد الإصدار
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * قيد الصرف
     */
    public function cashJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'cash_journal_entry_id');
    }

    /**
     * من أصدر الشيك.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ═══════════════════════════════════════════════════════════════
    // دوال مساعدة (Helper Methods)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get status name in Arabic
     */
    public function getStatusNameAttribute(): string
    {
        return $this->status?->getName() ?? 'غير محدد';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeAttribute(): string
    {
        return $this->status?->getBadgeClass() ?? 'badge-secondary';
    }

    /**
     * Check if cheque can be cashed
     */
    public function canCash(): bool
    {
        return $this->status?->canCash() ?? false;
    }

    /**
     * Check if cheque can be rejected
     */
    public function canReject(): bool
    {
        return $this->status === ChequeStatus::ISSUED;
    }

    /**
     * Check if cheque can be cancelled
     */
    public function canCancel(): bool
    {
        return $this->status?->canCancel() ?? false;
    }

    /**
     * Check if cheque is past due date
     */
    public function isPastDue(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }

    /**
     * Get days until due
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    // ═══════════════════════════════════════════════════════════════
    // Scopes
    // ═══════════════════════════════════════════════════════════════

    /**
     * Scope for issued cheques
     */
    public function scopeIssued($query)
    {
        return $query->where('status', ChequeStatus::ISSUED);
    }

    /**
     * Scope for cashed cheques
     */
    public function scopeCashed($query)
    {
        return $query->where('status', ChequeStatus::CASHED);
    }

    /**
     * Scope for rejected cheques
     */
    public function scopeRejected($query)
    {
        return $query->where('status', ChequeStatus::REJECTED);
    }

    /**
     * Scope for cancelled cheques
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', ChequeStatus::CANCELLED);
    }

    /**
     * Scope for due cheques
     */
    public function scopeDue($query)
    {
        return $query->where('due_date', '<=', now());
    }

    /**
     * Scope for cheques due in period
     */
    public function scopeDueBetween($query, $from, $to)
    {
        return $query->whereBetween('due_date', [$from, $to]);
    }
}
