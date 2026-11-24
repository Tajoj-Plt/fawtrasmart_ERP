<?php

namespace Modules\RentalManagement\Models;

use App\Models\Account;
use App\Models\Client;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\ReceivedCheque;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class Payment extends RealEstateModel
{
    /*
        تحصيلُ دفعة عقد: قيدٌ في الدفتر (إيرادُ الإيجار وضريبتُه وتأمينُ
        المستأجر). كان الجدولُ يحمل `accounting_period_id` ولا يملؤه شيء —
        يُختم الآن بفترة تاريخ الدفعة، ويُحرس بإقفالها كبقية سندات القبض.
    */
    use BelongsToAccountingPeriod;

    /**
     * حالات الترحيل. «غير مرحّلة»: سُجّلت والترحيل المحاسبي معطَّل — لا قيد
     * لها ولا حركة في دفتر المالك، ولا تدخل إحصاءات «المرحَّل».
     */
    public const POSTING_STATUSES = V::POSTING_STATUSES + [
        'unposted' => ['label' => 'غير مرحّلة', 'tone' => 'warning'],
        // شيكٌ مستلم لم يُحصَّل: الأقساط موزَّعة، ولا قيد للدفعة ولا حركة للمالك حتى يُحصَّل
        'cheque_pending' => ['label' => 'شيك تحت التحصيل', 'tone' => 'info'],
    ];

    public function receivedCheque()
    {
        return $this->belongsTo(ReceivedCheque::class, 'received_cheque_id');
    }

    /** نوع طريقة الدفع المشتقّ من اسمها في `payment_methods` — للتقارير وشرط المرجع. */
    public const METHOD_KINDS = [
        'cheque' => ['شيك', 'cheque', 'check'],
        'transfer' => ['تحويل', 'حوالة', 'transfer', 'bank', 'بنك', 'إيداع', 'ايداع'],
        'card' => ['بطاقة', 'شبكة', 'مدى', 'card', 'visa', 'master', 'mada', 'أون لاين', 'اون لاين', 'online', 'paypal', 'square', 'tamara', 'tabby', 'stripe'],
        'cash' => ['كاش', 'نقد', 'cash'],
    ];

    protected $table = 're_payments';

    public function accountingDateColumn(): string
    {
        return 'payment_date';
    }

    protected $casts = [
        'payment_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id')->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function treasuryAccount()
    {
        return $this->belongsTo(Account::class, 'treasury_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    /** طريقة الدفع من طرق الدفع المعرَّفة في النظام. */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id')->withTrashed();
    }

    public function getStatusMetaAttribute(): array
    {
        return [
            'label' => V::label(self::POSTING_STATUSES, $this->posting_status),
            'tone' => V::tone(self::POSTING_STATUSES, $this->posting_status),
        ];
    }

    /** اسم الطريقة كما في النظام؛ والدفعات السابقة لربط الطرق تعرض نوعها. */
    public function getMethodLabelAttribute(): string
    {
        return $this->paymentMethod->name ?? V::label(V::PAYMENT_METHODS, $this->payment_method);
    }

    public function isPosted(): bool
    {
        return $this->posting_status === 'posted';
    }

    /** نوع الطريقة (cash / transfer / card / cheque) من اسمها ورمزها. */
    public static function methodKind(?PaymentMethod $method): string
    {
        if (! $method) {
            return 'cash';
        }

        $text = mb_strtolower(trim($method->name.' '.$method->name_en.' '.$method->code));

        foreach (self::METHOD_KINDS as $kind => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($text, mb_strtolower($keyword)) !== false) {
                    return $kind;
                }
            }
        }

        return ($method->type ?? null) === 'electronic' || $method->is_online ? 'card' : 'cash';
    }

    /** الشيك والتحويل لا يُقبلان بلا رقم مرجع. */
    public static function kindNeedsReference(?string $kind): bool
    {
        return in_array($kind, ['transfer', 'cheque'], true);
    }
}
