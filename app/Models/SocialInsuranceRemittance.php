<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفعةُ سدادٍ للتأمينات: إقفالُ ما حُجز نقداً.
 *
 * الحصّتان في صفٍّ واحد لأنّهما تُسدَّدان إلى الجهة نفسها بحوالةٍ واحدة.
 */
class SocialInsuranceRemittance extends Model
{
    use BelongsToBranch;

    protected $table = 'social_insurance_remittances';

    protected $fillable = [
        'period_month',
        'employee_amount', 'employer_amount', 'total_amount',
        'treasury_id', 'expense_id', 'journal_entry_id', 'branch_id',
        'reference', 'notes',
        'paid_at', 'paid_by',
    ];

    protected $casts = [
        'employee_amount' => 'decimal:2',
        'employer_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
