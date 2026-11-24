<?php

namespace App\Models;

use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Sales\Support\InstallmentSchedule;

/**
 * اتفاقية التقسيط — الخطة التي يُسدَّد بها مبلغ فاتورةٍ على دفعات.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  العطب الذي كان يُسقط القسم كلَّه
 * ══════════════════════════════════════════════════════════════════════
 *
 *     public function invoice()
 *     {
 *         return $this->hasMany(Invoice::class, 'invoice_id');
 *     }
 *
 * اتفاقيةٌ واحدة تخصّ فاتورةً واحدة، والعلاقة `belongsTo` لا `hasMany`.
 * وبهذا التعريف تعني: «الفواتيرُ التي عمودُ `invoice_id` فيها يساوي
 * **معرّف هذه الاتفاقية**» — أي استعلامٌ على عمودٍ لا وجود له في
 * `invoices`، ونتيجتُه `Collection` لا `Invoice`.
 *
 * فكلّ ما بُني عليها كان يقرأ من مجموعةٍ لا من فاتورة:
 *
 *   `$installment->invoice->client`      ← لا يوجد على `Collection`
 *   `$installment->invoice->grand_total` ← لا يوجد
 *   `$installment->invoice->due_value`   ← لا يوجد
 *
 * وأخطرُها في `calculateInstallmentStatus()` التي تُنادى لكلّ صفٍّ في
 * القائمة:
 *
 *     $installment->invoice->installments()->sum('amount')
 *
 * `Collection::installments()` دالّةٌ غير موجودة ⇒
 * **`BadMethodCallException`**. أي أن **شاشة قائمة الأقساط كانت تسقط بـ500
 * بمجرّد وجود اتفاقيةٍ واحدة في النظام**.
 *
 * ولو مرّت، فالبند 2.10 في تقرير التحسين يرصد أن `installments()` علاقةُ
 * **اتفاقيات** لا **دفعات**: الدالّة كانت تجمع مبالغ الاتفاقيات وتعدّها
 * سداداً، فيظهر كلّ قسط «مكتمل» فور إنشاء الاتفاقية.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  أعطابٌ أخرى في هذا الصنف
 * ══════════════════════════════════════════════════════════════════════
 *
 *   • `$fillable` فيه أربعة حقول، و`store()` يمرّر سبعة — فـ`client_id`
 *     و`payment_rate` و`start_date` و`note` تُسقَط بصمت. انظر الهجرة
 *     `2026_08_22_090000_upgrade_installments_for_erp`.
 *
 *   • `payment()` — `belongsTo(PaymentsProcess::class, 'installment_id')`
 *     بمفتاحٍ أجنبيّ على جدول الاتفاقيات لا وجود له. والدفعة تخصّ الفاتورة
 *     لا الاتفاقية، فالعلاقة تُقرأ من هناك.
 *
 *   • `purchase_invoice()` — `belongsTo(PurchaseInvoice::class,)` بفاصلةٍ
 *     زائدة وبلا مفتاح. اتفاقية تقسيطٍ على فاتورة **شراء** لا معنى لها في
 *     وحدة المبيعات؛ الدالّة ميّتة ولا شيء يناديها.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  السداد: من أين يُقرأ؟
 * ══════════════════════════════════════════════════════════════════════
 *
 * الاتفاقية **لا تحفظ مالاً بنفسها**. الدفعة تُسجَّل سندَ قبضٍ عبر
 * `SalesPaymentController` (`paymentsClient.*`) على الفاتورة، وهو مسار
 * المال الوحيد في النظام. والقسط يقرأ ما سُدِّد من هناك.
 *
 * فلا مساران للمال يفترقان، ولا قيدٌ محاسبيّ يُكتب من هذا القسم.
 */
class Installment extends Model
{
    use HasFactory;

    protected $table = 'installments';

    /** قسم الترقيم المتسلسل في `App\Support\DocumentSections`. */
    public const NUMBER_SECTION = 'installment';

    /** حالات الاتفاقية — البند 5 في تقرير الإصلاح. */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DEFAULTED = 'defaulted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_DEFAULTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'code',
        'invoice_id',
        'client_id',
        'branch_id',
        'created_by',
        'updated_by',

        // المال
        'total_amount',
        'down_payment',
        'principal_amount',
        'profit_rate',
        'profit_method',
        'profit_amount',
        'admin_fees',
        'tax_rate',
        'tax_amount',
        'financed_amount',
        'installment_amount',
        'amount',

        // الغرامة
        'late_fee_type',
        'late_fee_value',
        'late_fee_grace_days',
        'late_fee_basis',
        'late_fee_max',

        // ما رُحِّل من التمويل وما تحقّق من ربحه — انظر `InstallmentFinancing`
        'financing_profit_posted',
        'financing_fees_posted',
        'financing_tax_posted',
        'profit_recognized',

        // الضريبة المختارة من «إعدادات الضريبة» — النسبة تُشتقّ منها
        'tax_id',

        // الجدولة
        'installment_number',
        'payment_rate',
        'first_due_date',
        'last_due_date',
        'due_date',

        'currency',
        'payment_method',
        'status',
        'note',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'admin_fees' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'financed_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'profit_rate' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'late_fee_value' => 'decimal:2',
        'late_fee_grace_days' => 'integer',
        'late_fee_max' => 'decimal:2',
        'financing_profit_posted' => 'decimal:2',
        'financing_fees_posted' => 'decimal:2',
        'financing_tax_posted' => 'decimal:2',
        'profit_recognized' => 'decimal:2',
        'tax_id' => 'integer',
        'installment_number' => 'integer',
        'payment_rate' => 'integer',
        'first_due_date' => 'date',
        'last_due_date' => 'date',
        'due_date' => 'date',
    ];

    // ══════════════════ العلاقات ══════════════════

    /** الفاتورة المقسَّطة — `belongsTo` لا `hasMany`. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method');
    }

    /**
     * الضريبة المطبَّقة — صفٌّ من «إعدادات الضريبة».
     *
     * النسبةُ مجمَّدةٌ في `tax_rate` عند الحفظ، وهذه العلاقةُ لاسمها
     * وطريقتها في العرض والطباعة. فتعديلُ نسبة الضريبة في الإعدادات لاحقاً
     * لا يُعيد حساب اتفاقيةٍ وُقّعت ولا يغيّر جدولَ أقساطها.
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(TaxSitting::class, 'tax_id');
    }

    /** أقساط الاتفاقية بترتيب رقمها ثمّ استحقاقها. */
    public function details(): HasMany
    {
        return $this->hasMany(InstallmentDetail::class, 'installments_id')
            ->orderBy('installment_number')
            ->orderBy('due_date');
    }

    // ══════════════════ النطاقات ══════════════════

    public function scopeVisibleTo(Builder $query, $user = null): Builder
    {
        $user = $user ?: auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('sales.installments.view') || $user->hasRole('manager')) {
            return $query;
        }

        if (! $user->can('sales.installments.view')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('created_by', $user->id);
    }

    /** اتفاقيات فرعٍ بعينه — والقديمة التي سبقت عمود الفرع. */
    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        if (! $branchId) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($branchId) {
            $scope->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
    }

    // ══════════════════ السداد ══════════════════

    /**
     * ما سُدِّد فعلاً — مجموع ما سُجِّل على الأقساط.
     *
     * البند 2.10 كان يجمع `Installment.amount` (مبالغ الاتفاقيات) ويعدّه
     * سداداً. المصدر الصحيح `paid_amount` على القسط، وهو ما يُحدَّث من
     * سندات القبض المسجَّلة على الفاتورة.
     */
    public function getPaidAmountAttribute(): float
    {
        return (float) $this->details->sum('paid_amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return round(max(0, (float) $this->financed_amount - $this->paid_amount), 2);
    }

    /**
     * كلُّ ما على العميل من هذه الاتفاقية — الأقساطُ وغراماتُها.
     *
     * `remaining_amount` أعلاه هو **الأقساط وحدها**، وعليه تُبنى نسبةُ
     * الإنجاز وحالةُ الاكتمال: اتفاقيةٌ سُدِّدت أقساطُها كلُّها مكتملةٌ
     * وإن بقيت عليها غرامةٌ لم تُحصَّل — الغرامةُ ذمّةٌ قائمة لا قسطٌ
     * ناقص، وخلطُهما يُبقي الاتفاقية «نشطة» أبداً.
     */
    public function getTotalOutstandingAttribute(): float
    {
        return round($this->remaining_amount + $this->late_fees_outstanding, 2);
    }

    /**
     * ما حُصِّل من الدفعة المقدَّمة.
     *
     * الدفعة المقدَّمة تُدخَل في نموذج الاتفاقية فتخصم من المبلغ الممول،
     * لكنّها **لا تُنشئ سند قبض**: لا مال يُقيَّد بمجرّد كتابة رقمٍ في
     * حقل. و`applyPayments()` تخصمها من مجموع السندات قبل توزيعها على
     * الأقساط — أي أنّها تعدّها مستحقّةً أوّلاً، وهو الترتيب الصحيح.
     *
     * وكان أثر ذلك خفيّاً: من أدخل دفعةً مقدَّمة ولم يُسجّل سندها ظنّ أنّ
     * أوّل سندٍ يسجّله سيُسدّد القسط الأوّل، فيجده «غير مسدَّد» لأنّ السند
     * ذهب إلى الدفعة المقدَّمة. هاتان السمتان تُظهران ذلك في الشاشة بدل
     * أن يُستنتَج.
     */
    public function getDownPaymentPaidAttribute(): float
    {
        $down = (float) $this->down_payment;

        if ($down <= 0) {
            return 0.0;
        }

        // السندات الملغاة (5) لا تُحسب مقبوضاً
        $collected = $this->invoice
            ? (float) $this->invoice->payments()->where('payment_status', '!=', 5)->sum('amount')
            : 0.0;

        return round(min($down, $collected), 2);
    }

    /** ما بقي من الدفعة المقدَّمة بلا تحصيل. */
    public function getDownPaymentRemainingAttribute(): float
    {
        return round(max(0, (float) $this->down_payment - $this->down_payment_paid), 2);
    }

    /** نسبة الإنجاز — لشريط التقدّم في الشاشة. */
    public function getProgressAttribute(): float
    {
        $financed = (float) $this->financed_amount;

        return $financed > 0 ? min(100, round(($this->paid_amount / $financed) * 100, 1)) : 0.0;
    }

    /** أقساطٌ فات استحقاقها ولم تُسدَّد بالكامل. */
    public function getOverdueCountAttribute(): int
    {
        return $this->details->filter(fn (InstallmentDetail $d) => $d->is_overdue)->count();
    }

    /**
     * مجموع الغرامات **المحمَّلة والمقيَّدة** على أقساط هذه الاتفاقية.
     *
     * المصدر عمودُ `late_fee_amount` لا حسابٌ لحظيّ: هذا ما رُحِّل فعلاً
     * إلى «غرامات تأخير الأقساط» وصار ذمّةً على العميل، فلا يجوز أن يختلف
     * عمّا يقوله دفترُ الأستاذ بتغيّر ساعة القراءة.
     */
    public function getLateFeesChargedAttribute(): float
    {
        return round((float) $this->details->sum('late_fee_amount'), 2);
    }

    /** ما سُدِّد من تلك الغرامات. */
    public function getLateFeesPaidAttribute(): float
    {
        return round((float) $this->details->sum('late_fee_paid'), 2);
    }

    /** ما بقي منها بلا تحصيل. */
    public function getLateFeesOutstandingAttribute(): float
    {
        return round(max(0, $this->late_fees_charged - $this->late_fees_paid), 2);
    }

    /**
     * مجموع ما **سيُحمَّل** لو شُغِّلت مهمّةُ الغرامات الآن — تقديرٌ للعرض.
     *
     * يُقرأ في الشاشة ليرى المستخدمُ أثرَ سياسة الغرامة قبل أن تقع، وهو
     * غيرُ `late_fees_charged`: ذاك ما وقع، وهذا ما هو مستحقّ اليوم.
     */
    public function getLateFeesDueAttribute(): float
    {
        return round($this->details->sum(fn (InstallmentDetail $d) => $d->late_fee_due), 2);
    }

    /** هل ضُبطت للاتفاقية سياسةُ غرامةٍ أصلاً؟ */
    public function hasLateFeePolicy(): bool
    {
        return in_array($this->late_fee_type, ['percent', 'fixed'], true)
            && (float) $this->late_fee_value > 0;
    }

    /**
     * الحالة التي تصفّ الاتفاقية الآن.
     *
     * محسوبةٌ لا مخزَّنة: الحالة المخزَّنة تتقادم بمجرّد مرور تاريخ
     * استحقاق، ولا شيء يمرّ على الجدول ليحدّثها. المخزَّن يبقى مرجعاً
     * للإلغاء اليدوي وحده.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return self::STATUS_CANCELLED;
        }

        if ($this->details->isNotEmpty() && $this->remaining_amount <= 0.009) {
            return self::STATUS_COMPLETED;
        }

        return $this->overdue_count > 0 ? self::STATUS_DEFAULTED : self::STATUS_ACTIVE;
    }

    // ══════════════════ سمات العرض ══════════════════

    /** رمز عملة المستند — المخزَّن معه، وإلا عملة النظام. */
    public function getCurrencyCodeAttribute(): string
    {
        $stored = $this->attributes['currency'] ?? null;

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        try {
            return CurrencySettings::code();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** مبلغٌ منسَّقٌ بعملة الاتفاقية — لا برمزٍ مثبَّت في القالب. */
    public function money($amount): string
    {
        return trim(CurrencySettings::number((float) $amount).' '.$this->currency_code);
    }

    /** مفتاح ترجمة الدوريّة — من `InstallmentSchedule::RATES` لا من قائمةٍ في القالب. */
    public function getRateKeyAttribute(): string
    {
        return InstallmentSchedule::rateKey($this->payment_rate);
    }
}
