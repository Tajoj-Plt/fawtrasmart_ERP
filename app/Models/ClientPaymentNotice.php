<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إشعارُ سدادٍ أرسله العميل من بوابته.
 *
 * انظر ترحيلَ `client_payment_notices`: الإشعارُ إعلانُ نيّةٍ لا سند —
 * لا يصير مالاً في النظام إلّا حين يعتمده موظّفٌ فيُنشئ سندَ القبض
 * المعتاد من شاشة المبيعات.
 */
class ClientPaymentNotice extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** ألغاه العميلُ نفسُه قبل مراجعته */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * طرقُ السدادِ القديمة — للصفوف التي سبقت ربطَ «طرق الدفع».
     *
     * صارت الطريقةُ مرجعاً إلى `payment_methods` (طلب المستخدم 2026-09-19:
     * «طرق الدفع تُجلب من الإعدادات»)، فما يختاره العميل هو ما يُعبَّأ في
     * سند القبض عند الاعتماد بلا إعادة اختيار. وهذه الخريطةُ تُقرأ ولا
     * تُكتب: صفٌّ قديمٌ بلا `payment_method_id` يُقرأ اسمُه منها.
     *
     * @var array<string, string>
     */
    public const METHODS = [
        'bank_transfer' => 'تحويل بنكي',
        'card_network' => 'شبكة / بطاقة',
        'cash' => 'نقداً',
        'other' => 'أخرى',
    ];

    protected $fillable = [
        'client_id', 'user_id', 'invoice_id', 'amount', 'payment_method_id', 'method', 'paid_at',
        'reference', 'attachment', 'note', 'status', 'reviewed_by', 'reviewed_at',
        'review_note', 'payment_id', 'gateway', 'gateway_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** طريقةُ الدفع من «الإعدادات ← طرق الدفع» — نفسُها التي يقرؤها السند */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function methodLabel(): string
    {
        return $this->paymentMethod?->name
            ?? self::METHODS[$this->method]
            ?? ($this->method ?: '—');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'معتمد',
            self::STATUS_REJECTED => 'مرفوض',
            self::STATUS_CANCELLED => 'ملغى',
            default => 'بانتظار المراجعة',
        };
    }

    /** لونُ الشارة — بنفس مفردات بقيّة الشاشات (bootstrap) */
    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'warning',
        };
    }
}
