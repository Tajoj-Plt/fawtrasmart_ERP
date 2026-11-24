<?php

namespace App\Models;

use App\Enums\PurchaseDocumentType;
use App\Services\Purchases\PurchaseDocumentRules;
use App\Services\Purchases\PurchasePaymentCalculator;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * مستند مشتريات موحّد — جدول واحد لأربعة أنواع يفرّق بينها عمود `type`.
 *
 *     Requested     أمر شراء        ← ليس مستنداً مالياً
 *     invoice       فاتورة شراء     ← المستند المالي الأساسي
 *     Return        مرتجع شراء      ← يعكس أثر فاتورة (reference_id)
 *     City Notice   إشعار مدين      ← تسوية مالية على المورّد
 *
 * **أين المنطق؟** ليس هنا. قواعد كل نوع في `PurchaseDocumentRules`،
 * وحساب الدفع في `PurchasePaymentCalculator`. الموديل يعرض العلاقات
 * والنطاقات وأسئلةً قصيرة تفوّض إلى الاثنين — فإضافة نوع جديد لا تمسّه.
 */
class PurchaseInvoice extends Model
{
    /*
        ═════════ الفرعُ والموظّفُ والفترة ═════════

        ما تختمه فاتورةُ المبيعات لحظةَ إنشائها تختمه هذه: الفرعُ يُجمَّد
        فلا يتبع تبديلَ منشئه فرعَه، والموظّفُ يقول لمن يُنسب المستند،
        والفترةُ تقول أين يقع في الدفتر — والأنواعُ الأربعة سواءٌ في ذلك،
        فالمرتجعُ والإشعارُ المدين قيدان كالفاتورة.

        **والموظّفُ لم يكن له عمودٌ أصلاً**: كان يُعرَف بقفزةٍ إلى `users`
        ثمّ إلى `employees` — وتلك تُعيد كتابةَ التاريخ كلّما نُقل الموظّف
        بين الفروع أو غُيّر ربطُ حسابه.

        **والفترةُ كانت تُستنتَج بالتاريخ** في كلّ شاشة، بينما القيدُ الذي
        وُلد من المستند يحمل `accounting_period_id`. فإن حُرّكت حدودُ
        الفترة اختلف المستندُ عن قيده، ولا يُعرف أيُّهما الصواب.

        `date` هو التاريخُ الحاكم — وهو الافتراضُ في السِّمة فلا يُعاد تعريفه.
    */
    use \App\Models\Concerns\BelongsToAccountingPeriod;
    use \App\Models\Concerns\StampsOwnerContext;
    use BelongsToBranch;

    protected $table = 'purchase_invoices';

    protected $fillable = ['code', 'type', 'reference_id', 'purchase_receiving_id', 'receiving_status', 'attachments', 'supplier_id', 'account_id', 'cost_center_id', 'due_value', 'date', 'due_date', 'terms', 'status', 'payment_status', 'discount_amount', 'discount_percentage', 'discount_type', 'advance_payment', 'advance_payment_type', 'is_paid', 'payment_method', 'reference_number', 'tax_type', 'shipping_cost', 'shipping_tax', 'subtotal', 'total_discount', 'total_tax', 'grand_total', 'notes', 'is_received', 'received_date', 'created_by', 'updated_by', 'branch_id', 'employee_id', 'accounting_period_id', 'currency'];

    /*
     * لا تُضاف `$casts` هنا: الموديل عاش بلا تحويلات، ومئات المواضع تقرأ
     * `date` نصّاً و`grand_total` رقماً خاماً. تحويلها إلى Carbon وdecimal
     * يغيّر مخرجات الطباعة والمقارنات في شيفرة لم تُكتب لتوقّعها — وهذا
     * تغيير سلوك لا علاقة له بإعادة الهيكلة المطلوبة.
     */

    // ==================== النوع ====================

    /** النوع كحالة enum — أو null لقيمة غير معروفة */
    public function documentType(): ?PurchaseDocumentType
    {
        return PurchaseDocumentType::fromValue($this->type);
    }

    public function isPurchaseOrder(): bool
    {
        return $this->documentType() === PurchaseDocumentType::ORDER;
    }

    public function isPurchaseInvoice(): bool
    {
        return $this->documentType() === PurchaseDocumentType::INVOICE;
    }

    public function isPurchaseReturn(): bool
    {
        return $this->documentType() === PurchaseDocumentType::RETURN;
    }

    public function isDebitNote(): bool
    {
        return $this->documentType() === PurchaseDocumentType::DEBIT_NOTE;
    }

    // ==================== القدرات ====================

    /** هل يقبل الدفع؟ أمر الشراء لا يُدفع ولا يُحسب مديونية. */
    public function isPayable(): bool
    {
        return PurchaseDocumentRules::isPayable($this->type);
    }

    public function affectsSupplierBalance(): bool
    {
        return PurchaseDocumentRules::affectsSupplierBalance($this->type);
    }

    public function affectsInventory(): bool
    {
        return PurchaseDocumentRules::affectsInventory($this->type);
    }

    public function requiresJournalEntry(): bool
    {
        return PurchaseDocumentRules::requiresJournalEntry($this->type);
    }

    /** اتجاه أثر المستند على مديونية المورّد: 1 | -1 | 0 */
    public function balanceDirection(): int
    {
        return PurchaseDocumentRules::balanceDirection($this->type);
    }

    // ==================== النطاقات ====================

    public function scopeOfType(Builder $query, PurchaseDocumentType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopePurchaseOrders(Builder $query): Builder
    {
        return $query->where('type', PurchaseDocumentType::ORDER->value);
    }

    public function scopePurchaseInvoices(Builder $query): Builder
    {
        return $query->where('type', PurchaseDocumentType::INVOICE->value);
    }

    public function scopePurchaseReturns(Builder $query): Builder
    {
        return $query->where('type', PurchaseDocumentType::RETURN->value);
    }

    public function scopeDebitNotes(Builder $query): Builder
    {
        return $query->where('type', PurchaseDocumentType::DEBIT_NOTE->value);
    }

    /** المستندات التي تُدفع وحدها — للتقارير وكشوف المديونية */
    public function scopePayable(Builder $query): Builder
    {
        return $query->where('type', PurchaseDocumentType::INVOICE->value);
    }

    // ==================== العلاقات ====================

    /**
     * بنود المستند — العلاقة المعتمدة.
     *
     * كانت ثلاث علاقات لنفس الشيء: `items()` و`invoiceItems()`
     * و`purchaseItems()`. الأخيرة بلا مفتاح أجنبي صريح ولم تُستعمل قطّ
     * فحُذفت؛ و`invoiceItems()` مستعملة في أحد عشر موضعاً فبقيت اسماً
     * بديلاً يفوّض إلى هذه.
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'purchase_invoice_id');
    }

    /** @deprecated اسم بديل لـ`items()` — أُبقي للتوافق مع الشيفرة القائمة */
    public function invoiceItems()
    {
        return $this->items();
    }

    /**
     * مدفوعات المستند — العلاقة المعتمدة.
     *
     * كانت مكرَّرة باسمين متطابقين تماماً: `payments()` و`payments_process()`.
     */
    public function payments()
    {
        return $this->hasMany(PaymentsProcess::class, 'purchases_id');
    }

    /** @deprecated اسم بديل لـ`payments()` — مستعمل في خمسة مواضع */
    public function payments_process()
    {
        return $this->payments();
    }

    /**
     * المستند الأصلي — المرتجع والإشعار المدين يشيران به إلى فاتورتهما.
     */
    public function originalDocument()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'reference_id');
    }

    /** @deprecated اسم بديل لـ`originalDocument()` — مستعمل في أربعة مواضع */
    public function originalInvoice()
    {
        return $this->originalDocument();
    }

    /**
     * المستندات المشتقّة من هذا — مرتجعاته وإشعاراته المدينة.
     */
    public function relatedDocuments()
    {
        return $this->hasMany(PurchaseInvoice::class, 'reference_id');
    }

    /** @deprecated اسم بديل لـ`relatedDocuments()` مقصورٌ على المرتجعات */
    public function returns()
    {
        return $this->relatedDocuments()->where('type', PurchaseDocumentType::RETURN->value);
    }

    /**
     * استلامات هذا الأمر — يُملأ لصفوف أوامر الشراء وحدها.
     */
    public function receivings()
    {
        return $this->hasMany(PurchaseReceiving::class, 'purchase_order_id');
    }

    /**
     * الاستلام الذي وُلدت منه هذه الفاتورة — يُملأ لصفوف الفواتير التي
     * أُنشئت عبر «تحويل استلام إلى فاتورة» وحدها.
     */
    public function receivingOrigin()
    {
        return $this->belongsTo(PurchaseReceiving::class, 'purchase_receiving_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** القيد الرئيسي للمستند */
    public function journalEntry()
    {
        return $this->hasOne(JournalEntry::class, 'purchase_invoice_id');
    }

    /**
     * الإذن المخزني المرتبط — استلام لفاتورة شراء، أو إخراج لمرتجع شراء.
     * `reference_id` وحده لا يميّز نوع المستند المشار إليه عبر الجدول
     * كلّه، فيُقيَّد بنوعَي المرجع اللذين يخصّان `purchase_invoices` تحديداً
     * (لا `inventory_adjustment` أو غيره ممّا قد يتصادف رقم مرجعه).
     *
     * المصدر الوحيد لهذا الربط — بدل استعلامٍ يدويٍّ متكرر في المتحكّم،
     * كان أحد مواضعه غاب فيه فحص الاعتماد فسمح بتعديل فاتورةٍ استُلمت
     * بضاعتها فعلاً.
     */
    public function warehousePermit()
    {
        return $this->hasOne(WarehousePermits::class, 'reference_id')
            ->whereIn('reference_type', ['purchase_invoice', 'purchase_return']);
    }

    /** تخصيصات القيود على هذا المستند (سداد أو تسوية) */
    public function journalEntries()
    {
        return $this->belongsToMany(JournalEntry::class, 'journal_entry_invoices', 'invoice_id', 'entry_id')
            ->wherePivot('invoice_type', \App\Enums\InvoiceType::PURCHASE->value)
            ->withPivot('allocation_amount', 'allocation_percentage', 'invoice_type');
    }

    // ==================== الدفع ====================

    /**
     * ما سُدِّد فعلاً — بلا الملغى وبلا ازدواج.
     */
    public function paidAmount(): float
    {
        return PurchasePaymentCalculator::paidAmount($this);
    }

    /**
     * المتبقّي — صفرٌ لما لا يُدفع (أمر الشراء، المرتجع، الإشعار المدين).
     */
    public function calculateRemainingAmount(): float
    {
        return PurchasePaymentCalculator::remainingAmount($this);
    }

    /**
     * تحديث حالة الدفع — لا يمسّ ما لا يُدفع.
     *
     * كانت تضع `unpaid` و`due_value` على أوامر الشراء فتظهر مديونيةً
     * وهمية على المورّد.
     */
    public function updatePaymentStatus(): void
    {
        if (! $this->isPayable()) {
            return;
        }

        $remaining = $this->calculateRemainingAmount();
        $status = PurchasePaymentCalculator::paymentStatus($this);

        $this->payment_status = $status;
        $this->is_paid = $status === 'paid';
        $this->due_value = $remaining;

        $this->save();
    }
}
