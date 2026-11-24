<?php

namespace Modules\Client\Reservations\Models;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * حركةُ مالٍ واحدة على حجز — فهرسٌ يشير إلى المستند والقيد، لا رصيد.
 *
 * الأنواعُ الأربعة وأثرُها على `reservations.paid_amount`:
 *
 *   payment             +  سندُ قبضٍ وقيد: مدين الخزينة / دائن العرابين
 *   refund              −  قيد: مدين العرابين / دائن الخزينة
 *   cancellation_fee    ·  قيد: مدين العرابين / دائن إيراد رسوم الإلغاء
 *                          (المقبوضُ لا ينقص — لكنّ القابلَ للردّ ينقص)
 *   invoice_settlement  ·  العربونُ يُسوّى على الفاتورة: مدين العرابين /
 *                          دائن حساب العميل، ودفعةٌ في `payments_process`
 *
 * @see \Modules\Client\Reservations\Services\ReservationAccountingService
 */
class ReservationPayment extends Model
{
    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CANCELLATION_FEE = 'cancellation_fee';

    public const TYPE_INVOICE_SETTLEMENT = 'invoice_settlement';

    protected $table = 'reservation_payments';

    protected $fillable = [
        'reservation_id', 'type', 'amount', 'date', 'currency',
        'treasury_account_id', 'payment_method_id',
        'receipt_id', 'journal_entry_id', 'invoice_id', 'payments_process_id',
        'refund_of_id', 'branch_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    // ════════════════════ العلاقات ════════════════════

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'treasury_account_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refundOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'refund_of_id')->where('type', self::TYPE_REFUND);
    }

    // ════════════════════ النطاقات ════════════════════

    public function scopeOfType(Builder $query, string ...$types): Builder
    {
        return $query->whereIn('type', $types);
    }

    // ════════════════════ قراءات ════════════════════

    public function typeLabel(): string
    {
        return __('reservations.payment_types.'.$this->type);
    }

    /** ما بقي من هذه الدفعة قابلاً للردّ — مبلغُها ناقصَ ما رُدّ منها. */
    public function refundableAmount(): float
    {
        if ($this->type !== self::TYPE_PAYMENT) {
            return 0.0;
        }

        $refunded = (float) ($this->refunds_sum_amount ?? $this->refunds()->sum('amount'));

        return round(max(0.0, (float) $this->amount - $refunded), 2);
    }

    /** اتّجاهُ الحركة على الخزينة: +1 داخلٌ، −1 خارج، 0 لا يمسّها. */
    public function cashDirection(): int
    {
        return match ($this->type) {
            self::TYPE_PAYMENT => 1,
            self::TYPE_REFUND => -1,
            default => 0,
        };
    }
}
