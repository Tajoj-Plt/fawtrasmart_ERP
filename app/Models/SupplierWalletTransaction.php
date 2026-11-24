<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * حركة محفظة مورد — نظير `ClientWalletTransaction` في دورة المشتريات.
 *
 * الرصيد يعيش في `accounts.balance`، وهذا الجدول سببُ كل تغيّر: دفعةٌ
 * مقدَّمة، أو استردادٌ نقدي، أو سدادُ فاتورة شراء منها.
 *
 * @see \App\Services\Wallets\SupplierWalletService الكاتب الوحيد لهذا الجدول
 */
class SupplierWalletTransaction extends Model
{
    protected $table = 'supplier_wallet_transactions';

    protected $fillable = [
        'supplier_id', 'account_id', 'type', 'amount', 'balance_after',
        'treasury_account_id', 'purchase_invoice_id', 'journal_entry_id',
        'reference', 'note', 'branch_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * الحركات التي تزيد الرصيد.
     *
     * والمحفظة هنا **مدينة** بطبعها (أصل)، فالزيادة دفعةٌ نقدية خارجة —
     * عكسُ محفظة العميل حيث الزيادة نقدٌ داخل.
     */
    public const INCREASE_TYPES = ['deposit', 'refund'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function treasury()
    {
        return $this->belongsTo(Account::class, 'treasury_account_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
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
    public function isIncrease(): bool
    {
        return in_array($this->type, self::INCREASE_TYPES, true);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'deposit' => 'دفعة مقدَّمة',
            'withdraw' => 'استرداد',
            'invoice_paid' => 'سداد فاتورة',
            'refund' => 'ردّ سداد',
            default => $this->type,
        };
    }
}
