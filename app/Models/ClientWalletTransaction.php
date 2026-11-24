<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * حركة محفظة عميل — دفترُ «لماذا تغيّر الرصيد».
 *
 * الرصيد نفسه يعيش في `accounts.balance` كبقيّة الحسابات، ولا يُقرأ من
 * هنا. وهذا الجدول سببُ كل تغيّر: إيداعٌ من خزينة، أو سحبٌ إليها، أو
 * سدادُ فاتورة — مع القيد الذي رافقه، فيُراجَع الرصيد سطراً سطراً.
 *
 * @see \App\Services\Wallets\ClientWalletService الكاتب الوحيد لهذا الجدول
 */
class ClientWalletTransaction extends Model
{
    protected $table = 'client_wallet_transactions';

    protected $fillable = [
        'client_id', 'account_id', 'type', 'amount', 'balance_after',
        'treasury_account_id', 'invoice_id', 'journal_entry_id',
        'reference', 'note', 'branch_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /** الحركات التي تزيد الرصيد — الباقي ينقصه */
    public const CREDIT_TYPES = ['deposit', 'refund'];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function treasury()
    {
        return $this->belongsTo(Account::class, 'treasury_account_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** أزادت هذه الحركة الرصيد؟ */
    public function isCredit(): bool
    {
        return in_array($this->type, self::CREDIT_TYPES, true);
    }

    /** تسمية النوع بالعربية — تُقرأ في كشف الحركة */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'deposit' => 'إيداع',
            'withdraw' => 'سحب',
            'invoice_paid' => 'سداد فاتورة',
            'refund' => 'ردّ سداد',
            default => $this->type,
        };
    }
}
