<?php

namespace App\Models;

use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sales\Support\InstallmentSchedule;

/**
 * قسطٌ واحد من اتفاقية تقسيط.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  ما كان معطوباً
 * ══════════════════════════════════════════════════════════════════════
 *
 * **١. `status` عمود `tinyInteger` والمتحكّم يكتب فيه نصّاً.**
 *
 *     InstallmentDetail::create([… 'status' => 'unactive' …]);
 *
 * في MySQL الصارم: `SQLSTATE[22007] Incorrect integer value: 'unactive'`.
 * أي أن **إنشاء أيّ اتفاقية كان يسقط عند أوّل قسط**. صار العمود نصّياً في
 * الهجرة، وقيمُه من `STATUSES` أدناه.
 *
 * **٢. ثلاثة ترميزات متعارضة للحالة نفسها في ثلاث شاشات:**
 *
 *   `installments/show.blade.php`      → أرقام `1` و`2`
 *   `agreement_installments.blade.php` → نصوص `'active'` و`'unactive'`
 *   `installments/index.blade.php`     → يفلتر على `'paid'` و`'unpaid'`
 *
 * ولا واحدةٌ منها توافق العمود. وفي الشاشتين الأوليين اللونُ معكوسٌ على
 * التسمية: `'unactive'` يخرج أخضرَ بنصّ «غير مكتمل»، و`1` يخرج أخضرَ بنصّ
 * «غير مكتمل» كذلك. المصدر الوحيد للحالة الآن هذا الصنف.
 *
 * **٣. `installment_number` و`payment_rate` يُمرَّران إلى `create()`**
 * ولا عمودَ لهما ولا `$fillable` — فرقم القسط كان يضيع، ولا سبيل إلى
 * معرفة «القسط الثالث من عشرة» إلا بعدّ الصفوف.
 *
 * **٤. لا `paid_amount` ولا `paid_at`.** فـ«مدفوع جزئياً» في البند 6 من
 * تقرير الإصلاح لم يكن قابلاً للتمثيل أصلاً: القسط إمّا في حالةٍ أو في
 * أخرى، بلا مبلغٍ يقول كم سُدِّد منه.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  «متأخّر» ليست حالةً مخزَّنة
 * ══════════════════════════════════════════════════════════════════════
 *
 * التأخّر صفةٌ تتغيّر بمرور الوقت لا بفعل أحد: قسطٌ «مستحقّ» اليوم يصير
 * «متأخّراً» غداً بلا أن يلمسه شيء. تخزينُها يعني وظيفةً مجدولة تمرّ على
 * الجدول كلّ ليلة، وأيّ تعطّلٍ فيها يترك الشاشة تكذب. فهي مشتقّةٌ من
 * `due_date` و`paid_amount` وقت القراءة.
 */
class InstallmentDetail extends Model
{
    use HasFactory;

    protected $table = 'installments_details';

    /** لم يُسدَّد منه شيء بعد. */
    public const STATUS_PENDING = 'pending';

    /** سُدِّد بعضه. */
    public const STATUS_PARTIAL = 'partial';

    /** سُدِّد بالكامل. */
    public const STATUS_PAID = 'paid';

    /** أُلغي مع اتفاقيته. */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'installments_id',
        'installment_number',
        'payment_rate',
        'amount',
        'principal_amount',
        'profit_amount',
        'fees_amount',
        'tax_amount',
        'paid_amount',
        'paid_at',
        'due_date',
        'status',
        'note',

        // الغرامة المحمَّلة على هذا القسط — انظر `LateFeeCharger`
        'late_fee_amount',
        'late_fee_paid',
        'late_fee_days',
        'late_fee_posted_at',
        'late_fee_journal_id',

        // تواريخ التنبيه — بها لا يتكرّر الإشعار في اليوم الواحد
        'due_notified_at',
        'overdue_notified_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'fees_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'installment_number' => 'integer',
        'payment_rate' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'date',
        'late_fee_amount' => 'decimal:2',
        'late_fee_paid' => 'decimal:2',
        'late_fee_days' => 'integer',
        'late_fee_posted_at' => 'datetime',
        'due_notified_at' => 'date',
        'overdue_notified_at' => 'date',
    ];

    // ══════════════════ العلاقات ══════════════════

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class, 'installments_id');
    }

    /** اختصارٌ للفاتورة عبر الاتفاقية — يُستعمَل في زرّ السداد. */
    public function invoice()
    {
        return $this->installment?->invoice;
    }

    // ══════════════════ النطاقات ══════════════════

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PARTIAL]);
    }

    /** المتأخّر: فات استحقاقه ولم يكتمل سداده. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->whereDate('due_date', '<', now()->toDateString());
    }

    /** المستحقّ خلال أيامٍ قادمة — لشاشة المتابعة. */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->unpaid()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString());
    }

    // ══════════════════ سمات مشتقّة ══════════════════

    public function getRemainingAmountAttribute(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->paid_amount), 2);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === self::STATUS_PAID || $this->remaining_amount <= 0.009;
    }

    /** فات استحقاقه ولم يكتمل — تُقرأ وقت العرض لا من عمود. */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->is_paid || $this->status === self::STATUS_CANCELLED || ! $this->due_date) {
            return false;
        }

        return $this->due_date->isBefore(now()->startOfDay());
    }

    /**
     * الحالة المعروضة — تضمّ «متأخّر» المشتقّ إلى الحالات المخزَّنة.
     *
     * @return string pending|partial|paid|cancelled|overdue
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->is_paid) {
            return self::STATUS_PAID;
        }

        if ($this->is_overdue) {
            return 'overdue';
        }

        return (float) $this->paid_amount > 0 ? self::STATUS_PARTIAL : self::STATUS_PENDING;
    }

    /** لونُ الحالة في نظام `sl-*` — مصدرٌ واحد بدل ثلاث خرائط متناقضة. */
    public function getStatusToneAttribute(): string
    {
        return match ($this->display_status) {
            self::STATUS_PAID => 'on',
            'overdue' => 'danger',
            self::STATUS_PARTIAL => 'warning',
            self::STATUS_CANCELLED => 'off',
            default => 'neutral',
        };
    }

    /** أيام التأخّر منذ الاستحقاق — للعرض. */
    public function getDaysLateAttribute(): int
    {
        return $this->is_overdue
            ? (int) $this->due_date->diffInDays(now()->startOfDay())
            : 0;
    }

    /**
     * أيام التأخّر **المحتسَبة** — بعد انقضاء مدّة السماح.
     *
     * غيرُ `days_late` أعلاه: ذاك ما مضى منذ الاستحقاق، وهذا ما تُحتسب
     * عليه الغرامة. قسطٌ تأخّر خمسةً وسماحُه سبعة: `days_late = 5`
     * و`chargeable_late_days = 0`.
     */
    public function getChargeableLateDaysAttribute(): int
    {
        return InstallmentSchedule::lateDaysFor(
            $this->due_date,
            (int) ($this->installment?->late_fee_grace_days ?? 0)
        );
    }

    /**
     * الغرامة المستحقّة على هذا القسط اليوم — **تقديرٌ لا تحميل**.
     *
     * ما يُحمَّل ويُقيَّد هو `late_fee_amount`، ويكتبه
     * `App\Services\Installments\LateFeeCharger` وحده. وهذه الدالّة ما
     * سيبلغه ذلك العمود لو شُغِّلت المهمّة الآن — تُقرأ في الشاشة ليُرى
     * أثرُ السياسة قبل وقوعه.
     */
    public function getLateFeeDueAttribute(): float
    {
        $agreement = $this->installment;

        if (! $agreement || $this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }

        return InstallmentSchedule::lateFeeFor(
            (float) $this->remaining_amount,
            $this->due_date,
            $agreement->late_fee_type,
            (float) $agreement->late_fee_value,
            (int) $agreement->late_fee_grace_days,
            $agreement->late_fee_basis ?: InstallmentSchedule::FEE_ONCE,
            $agreement->late_fee_max !== null ? (float) $agreement->late_fee_max : null
        );
    }

    /** ما بقي من الغرامة المحمَّلة بلا سداد. */
    public function getLateFeeRemainingAttribute(): float
    {
        return round(max(0, (float) $this->late_fee_amount - (float) $this->late_fee_paid), 2);
    }

    /**
     * مستحقُّ هذا القسط كلُّه — مبلغُه وغرامتُه المحمَّلة.
     *
     * وهو سقفُ ما يُوزَّع عليه من التحصيل: «توضع الغرامة مع الدفعة التي
     * تأخّر بدفعها» — فالقسطُ المتأخّر يُطالَب بمبلغه وغرامته معاً لا
     * بمبلغه وحده وغرامةٌ معلّقةٌ في مكانٍ آخر.
     */
    public function getTotalDueAttribute(): float
    {
        return round((float) $this->amount + (float) $this->late_fee_amount, 2);
    }

    public function money($amount): string
    {
        $code = $this->installment?->currency_code ?? '';

        return trim(CurrencySettings::number((float) $amount).' '.$code);
    }

    /**
     * تحديث الحالة من المبلغ المسدَّد — نقطةٌ واحدة تُنادى بعد كل سداد.
     */
    public function syncStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        $paid = (float) $this->paid_amount;

        $this->status = match (true) {
            $paid >= (float) $this->amount - 0.009 => self::STATUS_PAID,
            $paid > 0 => self::STATUS_PARTIAL,
            default => self::STATUS_PENDING,
        };

        if ($this->status === self::STATUS_PAID && ! $this->paid_at) {
            $this->paid_at = now()->toDateString();
        }

        if ($this->status !== self::STATUS_PAID) {
            $this->paid_at = null;
        }
    }
}
