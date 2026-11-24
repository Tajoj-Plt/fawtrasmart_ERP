<?php

namespace Modules\RentalManagement\Models;

use App\Models\Account;
use App\Models\JournalEntry;

/**
 * تسوية تأمين مستأجر عند إنهاء عقده: المحتجَز، والمخصوم (للمالك أو
 * للمنشأة)، والمردود نقداً — بقيدٍ ورقمٍ مستقلّين.
 */
class DepositSettlement extends RealEstateModel
{
    public const DEDUCT_TO = [
        'owner' => ['label' => 'المالك'],
        'office' => ['label' => 'المنشأة'],
    ];

    protected $table = 're_deposit_settlements';

    protected $casts = [
        'settled_at' => 'date',
        'held_amount' => 'float',
        'deducted_amount' => 'float',
        'refunded_amount' => 'float',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id')->withTrashed();
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function treasuryAccount()
    {
        return $this->belongsTo(Account::class, 'treasury_account_id');
    }

    public function getDeductToLabelAttribute(): string
    {
        return self::DEDUCT_TO[$this->deduct_to]['label'] ?? '—';
    }
}
