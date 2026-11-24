<?php

namespace App\Models;

use App\Http\Controllers\POS\SalesStartController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    /*
        ═════════ الفترةُ المحاسبية ═════════

        `journal_entries` و`receipts` و`expenses` و`warehouse_permits` كلُّها
        تختم فترتَها منذ إنشائها، والفاتورةُ — وهي أصلُ أكثر تلك القيود —
        كانت لا تختمها. فالقيدُ يُنسَب إلى فترته والفاتورةُ التي وُلد منها
        لا تُنسَب، ولا يمكن حصرُ الفواتير بفترةٍ إلّا بمقارنة تواريخ تختلف
        عن حدود الفترة كلّما عُدِّلت.

        والتاريخُ الحاكم `invoice_date` لا `date`: الفاتورةُ تُنسَب إلى يوم
        إصدارها.

        ═════════ وسياقُ نقطة البيع ═════════

        `pos_branch_id` و`pos_device_id` يخصّان فواتيرَ نقطة البيع وحدها،
        فلا تُختَمان على فاتورةِ مبيعاتٍ مكتبية ولا على مرتجعٍ من وحدةٍ
        أخرى — انظر `shouldStampOwnerContext()` أدناه.
    */
    use \App\Models\Concerns\BelongsToAccountingPeriod;
    use \App\Models\Concerns\StampsOwnerContext;
    use \App\Traits\BelongsToBranch;

    /** الفرعُ العام (`branch_id`) شأنُ الوحدات الأخرى — لا يُختَم من هنا. */
    protected array $ownerContextMap = ['branch_id' => 'pos_branch_id'];

    protected array $ownerContextExcept = ['user_id'];

    public function accountingDateColumn(): string
    {
        return 'invoice_date';
    }

    /** أهذه الفاتورة ملغاة؟ */
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * الفواتيرُ السارية — تستثني الملغاة.
     *
     * كلُّ تقريرٍ وكلُّ مجموعٍ يجب أن يمرّ بهذا النطاق: فاتورةٌ أُلغيت لم
     * تقع، وحسابُها في المبيعات يُضخّمها بمبلغٍ لم يتحرّك.
     */
    public function scopeNotVoided($query)
    {
        return $query->whereNull($query->qualifyColumn('voided_at'));
    }

    /** فاتورةُ نقطة البيع تتبع فرعَ جلستها. */
    protected function ownerBranchFromDocument(): ?int
    {
        return $this->branchOfSession($this->session_id);
    }

    /**
     * أعمدةُ سياق نقطة البيع تُختَم لفواتيرها وحدها.
     *
     * ═════════ ولماذا لا يكفي النوع؟ ═════════
     *
     * `type = 'returned'` **تستعمله وحدةُ المبيعات أيضاً**
     * (`Modules\Sales\...\ReturnInvoiceController`)، فالنوعُ وحده يجعل
     * مرتجعاتِ المبيعات المكتبية تُختَم بفرعِ نقطة بيعٍ لا علاقة لها به —
     * فتظهر في تقارير نقاط البيع أرقامٌ ليست منها.
     *
     * والمميِّزُ الحقيقي هو **الجلسة**: مرتجعُ نقطة البيع يُولَد من جلسةٍ
     * مفتوحة ويحمل `session_id`، ومرتجعُ المبيعات لا يحمله.
     *
     * ولا يمنع هذا ختمَ الفترة: تلك تعمل عبر [BelongsToAccountingPeriod]
     * المستقلّة، فتشمل الفواتيرَ كلَّها كما ينبغي.
     */
    protected function shouldStampOwnerContext(): bool
    {
        if ($this->type === 'pos') {
            return true;
        }

        return $this->type === 'returned' && filled($this->session_id);
    }

    protected $fillable = ['client_id', 'branch_id', 'treasury_id', 'session_id', 'payment', 'invoice_date', 'issue_date', 'payment_terms', 'payment_status', 'currency', 'total', 'grand_total', 'due_value', 'employee_id', 'order_source_id', 'advance_payment', 'remaining_amount', 'is_paid', 'payment_method', 'reference_number', 'notes', 'code', 'discount_amount', 'discount_type', 'shipping_cost', 'shipping_tax', 'tax_type', 'tax_total', 'attachments', 'type', 'subtotal', 'created_by', 'updated_by', 'InvoiceAdjustmentLabel', 'InvoiceAdjustmentValue', 'subscription_id', 'adjustment_label', 'adjustment_value', 'adjustment_type',
        /*
            سياقُ فاتورة نقطة البيع.

            `pos_branch_id` و`pos_device_id` يُختمان لحظةَ البيع بدل أن
            يُستنتج الفرعُ بقفزتين (فاتورة ← جلسة ← جهاز ← فرع) — ونقلُ
            الجهاز بين الفروع لاحقاً كان يُعيد كتابة تاريخ الفواتير القديمة.

            و`accounting_period_id` كان موجوداً في `journal_entries` وغائباً
            هنا: القيدُ يُنسب إلى فترته والفاتورةُ التي وُلد منها لا تُنسب.
        */
        'pos_branch_id', 'pos_device_id', 'accounting_period_id',
        // الإلغاء: `voided_at` فارغاً يعني «سارية»
        'voided_at', 'voided_by', 'void_reason',
        // فاتورة المرتجع (`type = 'returned'`) وحدها تملؤه — رقم الفاتورة
        // الأصلية التي أُرجعت عنها. راجع `relatedDocuments()`/`originalInvoice()` أدناه
        'reference_id',
        /* المستندُ الأصل حين تكون الفاتورة محوَّلةً عن عرض سعرٍ أو أمر بيع.
           كان `convertToInvoice()` يمرّره منذ كُتبت وهو ليس عموداً ولا في
           `$fillable` — فيُسقَط بصمت، ولا تعرف الفاتورةُ عمّا صدرت. */
        'quote_id',
        // الكوبون كما طُبِّق — مجمَّدٌ لا يُعاد حسابُه
        'coupon_id', 'coupon_code', 'coupon_discount',
        /* «منع السداد الجزئي» — يُنسخ من الاشتراك الدوري عند الإصدار، وتقرؤه
           شاشة الدفعات وسند القبض. انظر `App\Support\PartialPaymentRule`. */
        'disable_partial_payment'];

    protected $casts = [
        'voided_at' => 'datetime',
        'invoice_date' => 'date',
        'issue_date' => 'date',
        'is_paid' => 'boolean',
        'disable_partial_payment' => 'boolean',
        'total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
        'tax_total' => 'decimal:2',
    ];

    // العلاقة مع قيد اليومية

    /**
     * مصدر الطلب — من شاشة «مصادر الطلب».
     *
     * يصير إلزامياً متى فُعّل `order_source_mandatory` في تلك الشاشة،
     * ويُفحَص في `InvoicesController::store()` لا في الشاشة وحدها.
     */
    public function orderSource()
    {
        return $this->belongsTo(\App\Models\OrderSource::class, 'order_source_id');
    }

    public function journalEntry()
    {
        return $this->hasOne(JournalEntry::class, 'invoice_id', 'id');
    }

    // العلاقة مع قيود اليومية المرتبطة
    public function journalEntries()
    {
        return $this->belongsToMany(JournalEntry::class, 'journal_entry_invoices', 'invoice_id', 'entry_id')
            ->withPivot('allocation_amount', 'allocation_percentage');
    }

    // العلاقة مع العميل
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * عرضُ السعر أو أمرُ البيع الذي صدرت عنه هذه الفاتورة.
     *
     * العمودُ `quote_id` كان يُمرَّر عند التحويل ولا يُحفَظ — راجع `$fillable`.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    /**
     * المستندات المرتبطة بهذه الفاتورة عبر `reference_id` — عملياً فواتير
     * المرتجع (`type = 'returned'`) المُنشأة ضدّها. نفس نمط
     * `PurchaseInvoice::relatedDocuments()` بالضبط.
     */
    public function relatedDocuments()
    {
        return $this->hasMany(Invoice::class, 'reference_id');
    }

    /** الفاتورة الأصلية — عكس `relatedDocuments()`، تملأها فاتورة المرتجع فقط. */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'reference_id');
    }

    /** الإشعارات الدائنة الصادرة على هذه الفاتورة. */
    public function creditNotifications()
    {
        return $this->hasMany(CreditNotification::class, 'invoice_id');
    }

    /*
        عروضُ الحسم لا تُطبَّق هنا.

        كان في هذا الموضع `applyOffers()` وتابعاها `checkOfferConditions()`
        و`calculateDiscount()` — ثمانون سطراً **بلا مستدعٍ واحد في المشروع**:
        لا شاشةُ الفاتورة تناديها ولا عرضُ السعر ولا المرتجع. فكانت شاشةُ
        «عروض الحسم» تَعِد بحسمٍ لا يقع على مبلغٍ واحد.

        وكانت — لو استُدعيت — تُطبِّق الحسم على `unit_price` لا على مجموع
        البند، وتقرأ الأعمدة المفردة `product_id`/`category_id` وتتجاهل
        الجداول الوسيطة التي تكتبها الشاشة فعلاً، وتكتب الحسمَ على البند
        بعد حفظه فلا يبلغ الإجماليَّ ولا وعاءَ الضريبة ولا القيد.

        المنطق الآن في `App\Services\Sales\OfferDiscountService`، وتصله
        المستنداتُ الثلاثة عبر `AppliesDiscountOffers` **قبل** أن تُحسب
        الإجماليات — فيدخل الحسمُ كلَّ ما يُبنى عليها.

        @see \App\Services\Sales\OfferDiscountService
        @see \Modules\Sales\Http\Controllers\Concerns\AppliesDiscountOffers
    */

    // العلاقة مع الخزينة
    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // العلاقة مع المستخدم الذي أنشأ الفاتورة
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // العلاقة مع المستخدم الذي قام بتحديث الفاتورة
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // العلاقة مع عناصر الفاتورة

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    // العلاقة مع جميع عمليات الدفع
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentsProcess::class)->where('type', 'client payments');
    }

    // العلاقة مع آخر عملية دفع فقط
    public function lastPayment(): HasOne
    {
        return $this->hasOne(PaymentsProcess::class)->where('type', 'client payments')->latest();
    }

    /**
     * المتبقي للتحصيل: الإجمالي − النقد − «مرتجع».
     *
     * `returned_payment` يكتبه مرتجعُ المبيعات والإشعارُ الدائن معاً
     * (`ClientDebtSettlement`)، والشاشاتُ تطرحه من المستحق أصلاً. ويقرأ هذا
     * توزيعُ سند القبض والقيد — وكان يُهمل المرتجع، فتوزيعُ القيد (الذي لا
     * يستبعد الفواتير المرتجعة) يُحصِّل ثمنَ بضاعةٍ رُدّت.
     */
    public function calculateRemainingAmount(): float
    {
        return $this->grand_total - $this->payments()->sum('amount') - (float) ($this->returned_payment ?? 0);
    }

    /**
     * حالةُ العرض حين يُطفئ المرتجعُ ما بقي على الفاتورة — أو `null`.
     *
     * الحالةُ المخزَّنة (`payment_status`) للنقد وحده، فالفاتورةُ التي
     * أرجعها العميل كلَّها تبقى «غير مدفوعة» بلا مستحقٍّ عليها. هذا يسمّيها
     * في الشاشات بما هي عليه، ولا يغيّر المخزَّن الذي يقرؤه المرتجع ليقرّر
     * ردَّ النقد: فاتورةٌ لم يُدفع منها ريال لا يُردّ منها ريال.
     *
     *   • `returned` — لم يُدفع منها شيء، والمرتجعُ أطفأها كلَّها
     *   • `settled`  — دُفع جزءٌ نقداً وأطفأ المرتجعُ الباقي
     *   • `null`     — ما زال عليها مستحق، أو لا مرتجع: تُعرض حالتُها كما هي
     */
    public function returnSettlementState(): ?string
    {
        if ((float) $this->returned_payment <= 0 || (int) $this->payment_status === 1) {
            return null;
        }

        if ((float) $this->due_value - (float) $this->returned_payment >= 0.005) {
            return null;
        }

        return (float) $this->advance_payment > 0 || (int) $this->payment_status === 2 ? 'settled' : 'returned';
    }

    /**
     * سببُ منع مرتجعٍ جديد على هذه الفاتورة — أو `null` إن جاز.
     *
     * للفاتورة مرتجعٌ واحد: مرتجعُ مبيعاتٍ سابق، أو إشعارٌ دائنٌ سوّاها
     * (`ClientDebtSettlement` يكتب في `returned_payment`) — والإشعارُ هنا
     * يُعدّ مرتجعاً. ولولا المنع لرُدّت البضاعةُ نفسُها مرّتين: مرّةً بالإشعار
     * ومرّةً بالمرتجع.
     */
    public function returnBlockReason(): ?string
    {
        // مرتجعٌ لا يُرتجع — كان يُقبل فيُضاف للعميل رصيدٌ وتدخل البضاعةُ المستودعَ مرّةً أخرى
        if ($this->type === 'returned') {
            return __('sales_invoice.return_blocked.not_sale');
        }

        if (static::where('type', 'returned')->where('reference_number', $this->id)->exists()) {
            return __('sales_invoice.return_blocked.has_return');
        }

        /* الإشعارُ الدائن **الصادرُ على هذه الفاتورة** — لا أثرُ أيّ إشعار.
           كان الشرط `returned_payment > 0`: تسويةُ إشعارٍ بلا فاتورة تكتب فيه على
           أقدم فواتير العميل فيُمنع مرتجعٌ حقيقيّ على فاتورةٍ لم يُردّ منها شيء،
           والتسويةُ لا تكتبه على فاتورةٍ مدفوعة فكان يُعمل لها إشعارٌ ثمّ مرتجع. */
        if ($this->hasActiveCreditNote()) {
            return __('sales_invoice.return_blocked.credit_note');
        }

        return null;
    }

    /** إشعارٌ دائنٌ غيرُ ملغى صادرٌ على هذه الفاتورة (`credit_notifications.invoice_id`). */
    public function hasActiveCreditNote(): bool
    {
        return CreditNotification::where('invoice_id', $this->id)
            ->whereNotIn('status', [0, 5])
            ->exists();
    }

    /**
     * سببُ منع إشعارٍ دائنٍ جديد على هذه الفاتورة — أو `null` إن جاز.
     *
     * ══════════════════════════════════════════════════════════════════
     *  لماذا هو توأمُ `returnBlockReason()`
     * ══════════════════════════════════════════════════════════════════
     *
     * المرتجعُ والإشعارُ الدائن طريقان إلى فعلٍ واحد: ردُّ بضاعةِ
     * الفاتورة وتخفيضُ ذمّة العميل بقيمتها. فالمنعُ كان في اتّجاهٍ واحد
     * فقط: `create()` المرتجع يرفض فاتورةً سوّاها إشعارٌ دائن، بينما
     * شاشةُ الإشعار الدائن تقبل فاتورةً لها مرتجعٌ قائم.
     *
     * فمن عمل مرتجعاً ثمّ عاد إلى الفاتورة وجد زرّ «إشعار دائن» حيّاً،
     * فأنشأ إشعاراً على فاتورةٍ رُدَّت أصلاً: بضاعةٌ تدخل المستودع
     * مرّتين، وذمّةٌ تُخفَّض مرّتين، وقيدان لعمليةٍ واحدة.
     *
     * والشرطان هما شرطا التوأم نفسُهما — مرتجعٌ قائم، أو تسويةٌ سابقة
     * كتبت في `returned_payment` (وهي أثرُ إشعارٍ دائنٍ سابق).
     */
    public function creditNoteBlockReason(): ?string
    {
        if ($this->type === 'returned') {
            return __('sales_invoice.credit_note_blocked.not_sale');
        }

        if (static::where('type', 'returned')->where('reference_number', $this->id)->exists()) {
            return __('sales_invoice.credit_note_blocked.has_return');
        }

        // الإشعارُ الصادرُ عليها لا `returned_payment` — انظر `returnBlockReason()`
        if ($this->hasActiveCreditNote()) {
            return __('sales_invoice.credit_note_blocked.has_credit_note');
        }

        return null;
    }

    /**
     * سببُ منع تقسيط هذه الفاتورة — أو `null` إن جاز تقسيطها.
     *
     * ══════════════════════════════════════════════════════════════════
     *  لماذا
     * ══════════════════════════════════════════════════════════════════
     *
     * اتفاقيةُ التقسيط **جدولُ سدادِ دَينٍ قائم**. وفاتورةٌ سُدِّدت بالكامل
     * لا دَينَ فيها يُجدوَل، فتقسيطُها يُنشئ جدولاً لمالٍ قُبض:
     *
     *   • `create()` كان يقترح `total_amount = due_value ?: grand_total`،
     *     و`due_value` على المسدَّدة **صفر** — فيسقط إلى `grand_total`
     *     ويقسّط قيمةَ الفاتورة كاملةً مرّةً ثانية.
     *   • ثمّ لا يجد `applyPayments()` ما يوزّعه (السنداتُ استُهلكت في
     *     الفاتورة نفسِها)، فتظهر الاتفاقيةُ «متعثّرة» وعلى العميل ذمّةٌ
     *     لا وجود لها.
     *   • والدفعةُ المقدَّمة تُقصّ إلى صفرٍ في `InstallmentDownPayment::capToInvoice()`
     *     فلا يُكتب لها سندٌ ولا قيد — بلا أن يُقال للمستخدم لِمَ.
     *
     * ══════════════════════════════════════════════════════════════════
     *  والمصدر
     * ══════════════════════════════════════════════════════════════════
     *
     * المحصَّلُ من سندات القبض لا من `is_paid`: العمودُ حصيلةُ
     * `updatePaymentStatus()` وقد يتقادم إن كُتب في الجدول من خارجها.
     * والسنداتُ الفاشلة (`payment_status = 5`) مستبعَدة — سندٌ فشل ليس
     * مالاً، وهو الاستبعادُ نفسُه الذي يعمل به `SalesPaymentController::store()`.
     *
     * على نسق `returnBlockReason()` أعلاه: يُقرأ في الشاشة لتعطيل الزرّ،
     * وفي `InstallmentsController::create()` لمنع فتحها، وفي
     * `InstallmentRequest` لمنع طلبٍ يُرسَل مباشرةً.
     */
    public function installmentBlockReason(): ?string
    {
        $total = round((float) $this->grand_total, 2);

        if ($total <= 0.009) {
            return __('sales_installments.messages.invoice_has_no_value');
        }

        $collected = (float) $this->payments()
            ->where('payment_status', '!=', 5)
            ->sum('amount');

        if (round($total - $collected, 2) <= 0.009) {
            return __('sales_installments.messages.invoice_already_paid', [
                'code' => $this->code ?: ('#'.$this->id),
            ]);
        }

        return null;
    }

    // دالة لتحديث حالة الدفع
    public function updatePaymentStatus(): void
    {
        // حساب إجمالي المدفوعات
        $totalPaid = $this->payments()->sum('amount');
        $remainingAmount = $this->grand_total - $totalPaid;

        if ($remainingAmount <= 0) {
            $this->payment_status = 1; // مدفوع بالكامل
            $this->is_paid = true;
        } elseif ($remainingAmount < $this->grand_total) {
            $this->payment_status = 2; // مدفوع جزئياً
            $this->is_paid = false;
        } else {
            $this->payment_status = 3; // غير مدفوع
            $this->is_paid = false;
        }

        $this->remaining_amount = max(0, $remainingAmount);
        $this->due_value = max(0, $remainingAmount);
        // تحديث المبلغ المدفوع (advance_payment) ليعكس إجمالي المدفوعات
        $this->advance_payment = $totalPaid;
        $this->save();
    }

    public function payments_process()
    {
        return $this->hasMany(PaymentsProcess::class, 'invoice_id');
    }

    /**
     * Accessor لحساب المبلغ المدفوع من المدفوعات الفعلية
     */
    public function getPaidAmountAttribute()
    {
        // إذا كان هناك paid_amount محفوظ مباشرة، استخدمه
        if ($this->attributes['paid_amount'] ?? null) {
            return $this->attributes['paid_amount'];
        }

        // وإلا، احسب المجموع من جدول المدفوعات
        return $this->payments()->sum('amount') ?? 0;
    }

    public function journalEntriesOld()
    {
        return $this->hasMany(JournalEntry::class, 'reference_id');
    }

    // في نموذج Invoice
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // في نموذج InvoiceItem
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    public function referenceInvoice()
    {
        return $this->belongsTo(Invoice::class, 'reference_number');
    }

    /**
     * العلاقة مع الطلب الإلكتروني
     * Relationship with OnlineOrder
     */
    public function onlineOrder()
    {
        return $this->hasOne(\App\Models\Store\OnlineOrder::class, 'invoice_id');
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    /**
     * Scope للفواتير التي تنتمي لجلسة معينة
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope للفواتير التي تنتمي للجلسة النشطة للمستخدم
     */
    public function scopeForActiveSession($query, $userId)
    {
        return $query->whereHas('posSession', function ($sessionQuery) use ($userId) {
            $sessionQuery->active()->forUser($userId);
        });
    }

    /**
     * Scope لفواتير نقطة البيع فقط
     */
    public function scopePosInvoices($query)
    {
        return $query->where('type', 'pos');
    }

    /**
     * التحقق من انتماء الفاتورة لجلسة نشطة
     */
    public function belongsToActiveSession($userId = null)
    {
        if (! $this->session_id) {
            return false;
        }

        $userId = $userId ?: auth()->id();

        return $this->posSession &&
               $this->posSession->status === 'active' &&
               $this->posSession->user_id === $userId;
    }

    /**
     * الحصول على معلومات الجلسة للفاتورة
     */
    public function getSessionInfoAttribute()
    {
        if (! $this->posSession) {
            return null;
        }

        return [
            'session_number' => $this->posSession->session_number,
            'cashier_name' => $this->posSession->user->name,
            'device_name' => $this->posSession->device->name ?? 'غير محدد',
            'started_at' => $this->posSession->started_at->format('Y-m-d H:i'),
            'status' => $this->posSession->status_text,
        ];
    }

    /**
     * التحقق من إمكانية تعديل الفاتورة
     */
    public function canBeModified()
    {
        // يمكن تعديل الفاتورة فقط إذا كانت في جلسة نشطة
        return $this->type === 'pos' &&
               $this->belongsToActiveSession() &&
               in_array($this->status, ['pending', 'held']);
    }

    /**
     * التحقق من إمكانية حذف الفاتورة
     */
    public function canBeDeleted()
    {
        return $this->type === 'pos' &&
               $this->belongsToActiveSession() &&
               $this->status === 'held';
    }

    /**
     * الحصول على تفاصيل الدفع للفاتورة
     * يدعم: رقم (1, 2) أو نص ("cash") أو JSON ([{"method_id":1,"amount":100}])
     */
    public function getPaymentDetailsAttribute()
    {
        if (! $this->payment_method) {
            return null;
        }

        // تحويل payment_method إلى array موحد
        $paymentMethods = $this->parsePaymentMethod();

        if (empty($paymentMethods)) {
            return null;
        }

        $details = [];
        foreach ($paymentMethods as $payment) {
            $method = PaymentMethod::find($payment['method_id']);
            $details[] = [
                'method_id' => $payment['method_id'],
                'method_name' => $method->name ?? 'غير محدد',
                'amount' => (float) ($payment['amount'] ?? $this->grand_total ?? 0),
                'percentage' => $this->grand_total > 0 ? round((($payment['amount'] ?? $this->grand_total) / $this->grand_total) * 100, 2) : 0,
            ];
        }

        return $details;
    }

    /**
     * تحويل payment_method إلى array موحد
     * يدعم: رقم (1, 2) أو نص ("cash") أو JSON ([{"method_id":1,"amount":100}])
     */
    public function parsePaymentMethod(): array
    {
        $value = $this->payment_method;

        if (empty($value)) {
            // إذا لم يكن هناك طريقة دفع، استخدم الطريقة الافتراضية
            $defaultMethod = PaymentMethod::getDefault();
            if ($defaultMethod) {
                return [
                    ['method_id' => $defaultMethod->id, 'amount' => (float) ($this->grand_total ?? 0)],
                ];
            }

            return [];
        }

        // الحالة 1: إذا كانت القيمة رقم (integer أو string رقمي)
        if (is_numeric($value)) {
            return [
                ['method_id' => (int) $value, 'amount' => (float) ($this->grand_total ?? 0)],
            ];
        }

        // الحالة 2: إذا كانت القيمة JSON array
        if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // إذا كانت object واحد، حولها إلى array
                if (isset($decoded['method_id'])) {
                    return [$decoded];
                }

                return $decoded;
            }
        }

        // الحالة 3: إذا كانت نص (مثل "cash", "card")
        if (is_string($value)) {
            // محاولة البحث عن طريقة الدفع بالاسم أو الكود
            $method = PaymentMethod::where('name', $value)
                ->orWhere('code', $value)
                ->first();
            if ($method) {
                return [
                    ['method_id' => $method->id, 'amount' => (float) ($this->grand_total ?? 0)],
                ];
            }
            // إذا لم يتم العثور، استخدم الطريقة الافتراضية
            $defaultMethod = PaymentMethod::getDefault();

            return [
                ['method_id' => $defaultMethod ? $defaultMethod->id : 1, 'amount' => (float) ($this->grand_total ?? 0)],
            ];
        }

        // الحالة 4: إذا كانت array مباشرة
        if (is_array($value)) {
            if (isset($value['method_id'])) {
                return [$value];
            }

            return $value;
        }

        return [];
    }

    /**
     * حساب إجمالي المدفوعات النقدية
     * يبحث في جدول طرق الدفع للتحقق من نوع الدفع
     */
    public function getCashPaymentsTotal(): float
    {
        $methods = $this->parsePaymentMethod();
        $total = 0;
        foreach ($methods as $payment) {
            $methodId = $payment['method_id'] ?? 0;
            if ($methodId) {
                $paymentMethod = PaymentMethod::find($methodId);
                if ($paymentMethod && $paymentMethod->isCash()) {
                    $total += (float) ($payment['amount'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * حساب إجمالي المدفوعات غير النقدية (الإلكترونية + التحويلات + الشيكات)
     * يبحث في جدول طرق الدفع للتحقق من نوع الدفع
     */
    public function getNonCashPaymentsTotal(): float
    {
        $methods = $this->parsePaymentMethod();
        $total = 0;
        foreach ($methods as $payment) {
            $methodId = $payment['method_id'] ?? 0;
            if ($methodId) {
                $paymentMethod = PaymentMethod::find($methodId);
                // أي طريقة دفع غير نقدية
                if ($paymentMethod && ! $paymentMethod->isCash()) {
                    $total += (float) ($payment['amount'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * حساب إجمالي مدفوعات البطاقات (فيزا/مدى)
     */
    public function getCardPaymentsTotal(): float
    {
        $methods = $this->parsePaymentMethod();
        $total = 0;
        foreach ($methods as $payment) {
            $methodId = $payment['method_id'] ?? 0;
            if ($methodId) {
                $paymentMethod = PaymentMethod::find($methodId);
                if ($paymentMethod && $paymentMethod->isElectronic()) {
                    $total += (float) ($payment['amount'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * حساب إجمالي التحويلات البنكية
     */
    public function getBankTransferPaymentsTotal(): float
    {
        $methods = $this->parsePaymentMethod();
        $total = 0;
        foreach ($methods as $payment) {
            $methodId = $payment['method_id'] ?? 0;
            if ($methodId) {
                $paymentMethod = PaymentMethod::find($methodId);
                if ($paymentMethod && $paymentMethod->isBankTransfer()) {
                    $total += (float) ($payment['amount'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * حساب إجمالي الشيكات
     */
    public function getCheckPaymentsTotal(): float
    {
        $methods = $this->parsePaymentMethod();
        $total = 0;
        foreach ($methods as $payment) {
            $methodId = $payment['method_id'] ?? 0;
            if ($methodId) {
                $paymentMethod = PaymentMethod::find($methodId);
                if ($paymentMethod && $paymentMethod->isCheck()) {
                    $total += (float) ($payment['amount'] ?? 0);
                }
            }
        }

        return $total;
    }

    /**
     * أحداث النموذج
     */
    protected static function booted()
    {
        // عند إنشاء فاتورة جديدة
        static::creating(function ($invoice) {
            // تعيين طريقة الدفع الافتراضية إذا لم يتم تحديدها
            if (empty($invoice->payment_method)) {
                $defaultMethod = PaymentMethod::getDefault();
                $invoice->payment_method = $defaultMethod ? $defaultMethod->id : 1;
            }

            // للفواتير POS، تأكد من وجود جلسة نشطة
            if ($invoice->type === 'pos' && ! $invoice->session_id) {
                $activeSession = PosSession::active()->forUser(auth()->id())->first();
                if ($activeSession) {
                    $invoice->session_id = $activeSession->id;
                }
            }
        });

        // عند حذف فاتورة، قم بتحديث إحصائيات الجلسة
        static::deleted(function ($invoice) {
            if ($invoice->type === 'pos' && $invoice->posSession && $invoice->posSession->status === 'active') {
                app(SalesStartController::class)->updateSessionStatistics($invoice->posSession);
            }

            /*
                فاتورةٌ من حجز («تحويل إلى فاتورة») تُحذف: يُفكّ ربطُ الحجز بها
                فيعود «غيرَ مفوتَر» ويُفوتَر ثانيةً. كان `invoice_id` يبقى معلَّقاً
                على فاتورةٍ غائبة فلا يُلغى الحجزُ ولا يُحذف ولا يُعدَّل سعرُه.
                الشاشةُ ترفض حذفَ الفواتير أصلاً؛ فهذا لما يُحذف من مسارٍ آخر.
            */
            if (Schema::hasTable('reservations')) {
                DB::table('reservations')->where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
            }
        });

        /* لا حركةَ مخزونٍ من أحداث النموذج.
         *
         * كانت هنا ثلاثةُ أحداث (`created`/`updated`/`deleted`) تكتب في
         * `product_details` مباشرةً — بلا إذنٍ مخزني، وبكمية البند لا
         * بالوحدة الأساسية، وبلا فحص رصيد. والخصمُ الحقيقيُّ تكتبه شاشةُ
         * الفاتورة والتحويلُ ونقطةُ البيع بأنفسها.
         *
         * وأسوأُها `updated`: يُعيد كمياتِ البنود من استعلامٍ جديد، ثمّ يخصمها
         * من `$this->items` — والعلاقةُ محمَّلةٌ **فارغةً** منذ `created`
         * (قبل كتابة البنود). فأيُّ حفظٍ للفاتورة نفسِها بعد بنودها — المرفقُ
         * في `InvoicesController::store()`، و`autoSettleInvoice()` في نقطة
         * البيع — يُرجع المباعَ إلى الرصيد كاملاً، فيبقى الصنفُ «متوفّراً»
         * ويُباع وهو غيرُ موجود. (اختُبر في معاملةٍ مُلغاة: ٨٦٥٥ ← بيع ٣
         * ← ٨٦٥٢ ← حفظُ مرفق ← ٨٦٥٥.) */
    }
}
