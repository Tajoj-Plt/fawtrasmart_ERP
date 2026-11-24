<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntryDetail extends Model
{
    use HasFactory;

    protected $table = 'journal_entry_details';
    protected $guarded = [];

    protected $fillable = ['journal_entry_id', 'account_id', 'description', 'debit', 'credit', 'reference', 'currency', 'is_debit', 'cost_center_id', 'tax_id', 'tax_type', 'is_tax_line'];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'is_debit' => 'boolean',
        'is_tax_line' => 'boolean',
    ];

    /** سجلُّ الضريبة المُختار للسطر — منه تُقرأ النسبةُ وطريقتُها. */
    public function taxSetting()
    {
        return $this->belongsTo(TaxSitting::class, 'tax_id');
    }

    // العلاقة مع الحساب
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // العلاقة مع مركز التكلفة
    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    // دالة مساعدة لحساب الرصيد
    // Note: Positive balance means credit > debit (دائن > مدين)
    // Negative balance means debit > credit (مدين > دائن)
    public function getBalanceAttribute()
    {
        return $this->credit - $this->debit;
    }
    
    /**
     * Get the account associated with this journal entry detail
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getAccount()
    {
        return $this->account;
    }
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

public function invoice()
{
    return $this->belongsTo(Invoice::class, 'reference', 'invoice_number');
}

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // When creating a journal entry detail, update the associated account balance
        static::created(function ($journalEntryDetail) {
            // Get the associated account
            $account = $journalEntryDetail->account;
            
            if ($account) {
                // Update the account balance and its parent balances
                Account::updateParentBalances($account->id, true);
            }
        });

        // When updating a journal entry detail, update the associated account balance
        static::updated(function ($journalEntryDetail) {
            // Get the associated account
            $account = $journalEntryDetail->account;
            
            if ($account) {
                // Update the account balance and its parent balances
                Account::updateParentBalances($account->id, true);
            }
        });

        // When deleting a journal entry detail, update the associated account balance
        static::deleted(function ($journalEntryDetail) {
            // Get the associated account
            $account = $journalEntryDetail->account;
            
            if ($account) {
                // Update the account balance and its parent balances
                Account::updateParentBalances($account->id, true);
            }
        });
    }
}