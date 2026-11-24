<?php

namespace App\Models;

use App\Enums\ChequeStatus;
use App\Enums\ReferenceType;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivedCheque extends Model
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

    protected $table = 'received_cheques';

    protected $fillable = [
        'id', 'amount', 'issue_date', 'due_date', 'cheque_number',
        'recipient_account_id', 'collection_account_id', 'bank_account_id',
        'payee_name', 'endorsement', 'name', 'description', 'attachment',
        'status', 'journal_entry_id', 'collection_journal_entry_id',
        'collected_at', 'rejected_at', 'endorsed_at', 'endorsed_to_account_id',
        'client_id', 'drawer_bank', 'drawer_branch', 'created_by',
        'created_at', 'updated_at', 'currency',

        'accounting_period_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'collected_at' => 'datetime',
        'rejected_at' => 'datetime',
        'endorsed_at' => 'datetime',
        'amount' => 'decimal:2',
        'status' => ChequeStatus::class,
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    // ═══════════════════════════════════════════════════════════════
    // العلاقات (Relationships)
    // ═══════════════════════════════════════════════════════════════

    /**
     * حساب المستفيد (العميل)
     */
    public function recipientAccount()
    {
        return $this->belongsTo(Account::class, 'recipient_account_id');
    }

    /**
     * حساب التحصيل (شيكات برسم التحصيل)
     */
    public function collectionAccount()
    {
        return $this->belongsTo(Account::class, 'collection_account_id');
    }

    /**
     * حساب البنك للإيداع
     */
    public function bankAccount()
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    /**
     * العميل صاحب الشيك
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * قيد الاستلام
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * قيد التحصيل
     */
    public function collectionJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'collection_journal_entry_id');
    }

    /**
     * جميع القيود المرتبطة بالشيك
     * يجلب قيود الاستلام، الإيداع، التحصيل، التظهير، والرفض
     */
    public function allJournalEntries()
    {
        return JournalEntry::where(function ($query) {
            $query->where('description', 'like', "%{$this->cheque_number}%")
                ->whereIn('reference_type', [
                    ReferenceType::RECEIVED_CHEQUE,
                    ReferenceType::RECEIVED_CHEQUE_COLLECT,
                    ReferenceType::RECEIVED_CHEQUE_ENDORSE,
                    ReferenceType::RECEIVED_CHEQUE_REJECT,
                ]);
        })
            ->orWhere('id', $this->journal_entry_id)
            ->orWhere('id', $this->collection_journal_entry_id)
            ->with('details.account')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * حساب المظهر إليه
     */
    public function endorsedToAccount()
    {
        return $this->belongsTo(Account::class, 'endorsed_to_account_id');
    }

    /**
     * من استلم الشيك.
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
     * Check if cheque can be collected
     */
    public function canCollect(): bool
    {
        return $this->status?->canCollect() ?? false;
    }

    /**
     * Check if cheque can be rejected
     */
    public function canReject(): bool
    {
        return $this->status?->canReject() ?? false;
    }

    /**
     * Check if cheque can be endorsed
     */
    public function canEndorse(): bool
    {
        return $this->status?->canEndorse() ?? false;
    }

    /**
     * Check if cheque can be deposited
     */
    public function canDeposit(): bool
    {
        return $this->status?->canDeposit() ?? false;
    }

    /**
     * Check if cheque is past due date
     */
    public function isPastDue(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }

    /**
     * Check if cheque is due today
     */
    public function isDueToday(): bool
    {
        return $this->due_date && $this->due_date->isToday();
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
     * Scope for pending cheques
     */
    public function scopePending($query)
    {
        return $query->where('status', ChequeStatus::PENDING);
    }

    /**
     * Scope for under collection cheques
     */
    public function scopeUnderCollection($query)
    {
        return $query->where('status', ChequeStatus::UNDER_COLLECTION);
    }

    /**
     * Scope for collected cheques
     */
    public function scopeCollected($query)
    {
        return $query->where('status', ChequeStatus::COLLECTED);
    }

    /**
     * Scope for rejected cheques
     */
    public function scopeRejected($query)
    {
        return $query->where('status', ChequeStatus::REJECTED);
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
