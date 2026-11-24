<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoubtfulDebt extends Model
{
    use HasFactory;

    protected $table = 'doubtful_debts';

    protected $fillable = [
        'client_id',
        'account_id',
        'original_balance',
        'provision_amount',
        'provision_percentage',
        'journal_entry_id',
        'status', // pending, collected, written_off
        'collected_amount',
        'collected_date',
        'collected_by',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'original_balance' => 'decimal:2',
        'provision_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'collected_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * العلاقة مع العميل
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع الحساب
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * العلاقة مع قيد اليومية
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * العلاقة مع من قام بالتسجيل
     */
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة مع من قام بالتحصيل
     */
    public function collectedByUser()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * الحصول على الرصيد المتبقي
     */
    public function getRemainingBalance()
    {
        return $this->original_balance - ($this->collected_amount ?? 0);
    }

    /**
     * التحقق من تحصيل كامل المبلغ
     */
    public function isFullyCollected()
    {
        return $this->collected_amount >= $this->original_balance;
    }

    /**
     * نسبة التحصيل
     */
    public function getCollectionPercentage()
    {
        if ($this->original_balance == 0) {
            return 0;
        }
        return round(($this->collected_amount / $this->original_balance) * 100, 2);
    }
}
