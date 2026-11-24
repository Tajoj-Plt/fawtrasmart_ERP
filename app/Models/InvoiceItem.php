<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_unit_id', // معرف وحدة المنتج المستخدمة
        'packege_id',
        'quotation_id',
        'credit_note_id', // إضافة حقل credit_note_id
        'purchase_invoice_id',
        'purchase_receiving_item_id',
        'purchase_quotation_id',
        'store_house_id',
        'purchase_order_id',
        'periodic_invoice_id',
        // إضافة حقل periodic_invoice_id
        'item',
        // لقطة نصية لمتغيّر المنتج المختار وقت البيع (اللون/المقاس/إلخ) — تُملأ
        // فقط لبنود المتجر الإلكتروني ذات المنتجات متعددة المتغيّرات
        'variant_label',
        'quotes_purchase_order_id',
        'purchase_invoice_id_type',
        'description',
        'unit_price',
        'quantity',
        'quantity_in_base_unit', // الكمية بالوحدة الأساسية
        'conversion_factor', // معامل التحويل
        'unit_name', // اسم الوحدة
        'discount',
        'discount_type',
        'tax_1',
        // معرّف الضريبة المضبوطة — منه يُعرف نوعها (متضمَّنة/مضافة)
        'tax_1_id',
        'tax_2',
        'tax_2_id',
        'total',
        'type',
        // تكلفةُ الوحدة الأساسية مجمَّدةً لحظةَ البيع — بها تُحسب الربحيةُ
        // بأثرٍ رجعيّ ثابت، لا بسعر الشراء الحالي الذي يتغيّر
        'unit_cost_at_sale',
    ];

    protected $casts = [
        'tax_1_id' => 'integer',
        'tax_2_id' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_1' => 'decimal:2',
        'tax_2' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'decimal:4',
        'quantity_in_base_unit' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'unit_cost_at_sale' => 'decimal:4',
    ];

    /**
     * لاحقةُ اسم بند الهديّة — يكتبها `InvoicesController::persistInvoice()`
     * و`QuoteController::matchingGiftOfferItems()` حين يضيفان هديّةَ عرض.
     */
    public const GIFT_SUFFIX = '(هدية)';

    /**
     * أهذا بندُ هديّةٍ أضافه عرضُ هدايا، لا بندٌ اختاره المستخدم؟
     *
     * لا عمودَ يميّزه: البندُ يُكتب بسعرٍ صفر واسمٍ ينتهي بـ«(هدية)»، وآلافُ
     * البنود القديمة على هذه الصورة. فالعلامةُ هي الاثنان معاً — سعرٌ صفر
     * وحده قد يكون بيعاً مجانياً اختاره المستخدم.
     *
     * يُستعمل حين تُعبَّأ شاشةٌ من مستندٍ محفوظ (نسخ فاتورة، فاتورة من عرض
     * سعر، تعديل عرض): الحفظ يُعيد حساب الهدايا من العروض السارية، فبندُ
     * الهديّة المنقول يُضاف ثانيةً ويُصرف من المخزون مرّتين.
     *
     * @param  self|array<string, mixed>  $item
     */
    public static function isGiftLine($item): bool
    {
        $price = (float) data_get($item, 'unit_price', 0);
        $name = trim((string) data_get($item, 'item', ''));

        return abs($price) < 0.00001 && str_ends_with($name, self::GIFT_SUFFIX);
    }

    // public function invoice()
    // {
    //     return $this->hasMany(Invoice::class, 'invoice_id');
    // }
    public function invoice()
    {
        // استبدل 'invoice_id' باسم العمود الفعلي إذا كان مختلفاً
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /** سطر الاستلام الذي فوترته هذا السطر — يُملأ لبنود الفواتير المولَّدة من استلام */
    public function receivingItem()
    {
        return $this->belongsTo(PurchaseReceivingItem::class, 'purchase_receiving_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * العلاقة مع وحدة المنتج المستخدمة
     */
    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * اسمُ الوحدة — **العمودُ المخزَّن أوّلاً**.
     *
     * كان المُلحِق يقرأ العلاقة وحدَها ويعيد `''` متى غاب `product_unit_id`
     * — فيحجب `invoice_items.unit_name` المكتوبَ في العمود. وهو يغيب فعلاً:
     * حين تُختار وحدةُ صنفٍ (`category_…`) لا يقابلها صفٌّ في `product_units`
     * يكتب `InvoicesController::store()` الاسمَ والمعاملَ ويترك المعرّف
     * فارغاً. فالبند يُحفَظ بوحدته، ويُطبع بلا وحدة.
     *
     * والعمودُ أحقُّ على كل حال: هو ما كان لحظةَ البيع، ولا يتغيّر بتغيّر
     * وحدات المنتج بعدها. والعلاقةُ تبقى احتياطاً للصفوف الأقدم من العمود.
     */
    public function getUnitNameAttribute(): string
    {
        $stored = $this->attributes['unit_name'] ?? null;

        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        if ($this->productUnit && $this->productUnit->categoryUnit) {
            return $this->productUnit->categoryUnit->unit->name ?? '';
        }

        return '';
    }

    /**
     * معاملُ التحويل — **العمودُ المخزَّن أوّلاً**، للسبب نفسه.
     *
     * وأثرُه هنا أفدحُ من اسمٍ لا يُطبع: المعاملُ يدخل في
     * `quantity_in_base_unit`، ومنها يُخصَم المخزون ويُقيَّد سعرُ التكلفة.
     * فبندٌ بيع بـ٢٠ عبوةً في الخمسين يُحسَب بعشرين حبّة حين يعود المُلحِق
     * بـ١ لغياب المعرّف.
     */
    public function getConversionFactorAttribute(): float
    {
        $stored = (float) ($this->attributes['conversion_factor'] ?? 0);

        if ($stored > 0) {
            return $stored;
        }

        if ($this->product_unit_id && $this->productUnit && $this->productUnit->categoryUnit) {
            $factor = (float) $this->productUnit->categoryUnit->conversion_factor;

            return $factor > 0 ? $factor : 1;
        }

        return 1;
    }

    /**
     * الكميةُ بالوحدة الأساسية — **العمودُ المخزَّن أوّلاً**.
     *
     * كان المُلحِق يحسبها دائماً (`الكمية × المعامل`) ويحجب العمود، فيبقى
     * `invoice_items.quantity_in_base_unit` مكتوباً ولا يُقرأ. وهو ما تُبنى
     * عليه قيودُ التكلفة وخصمُ المخزون، فحسابٌ يخالف المكتوب يعني مستنداً
     * يُقيَّد بغير ما حُفظ به.
     *
     * والحسابُ يبقى للصفوف التي لا عمودَ لها — وهو الآن يقرأ المعاملَ
     * المخزَّن كذلك، فلا يعود إلى ١ لغياب `product_unit_id`.
     */
    public function getQuantityInBaseUnitAttribute(): float
    {
        $stored = (float) ($this->attributes['quantity_in_base_unit'] ?? 0);

        if ($stored > 0) {
            return $stored;
        }

        return (float) $this->quantity * $this->conversion_factor;
    }

    /**
     * التحقق من وجود وحدة للبند
     *
     * `unit_name` لا `product_unit_id`: وحدةُ الصنف المختارة بلا صفٍّ في
     * `product_units` تُحفَظ باسمها ومعاملها بلا معرّف — ووجودُ الوحدة هو
     * ما يُسأل عنه هنا، لا وجودُ صفٍّ يربطها.
     */
    public function hasUnit(): bool
    {
        return $this->unit_name !== '';
    }

    public function quotesPurchase()
    {
        return $this->hasMany(PurchaseQuotationView::class, 'quotes_purchase_order_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quote::class, 'quotation_id');
    }

    // في ملف InvoiceItem.php
    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    public function creditNote()
    {
        return $this->belongsTo(CreditNotification::class, 'credit_note_id');
    }

    // حساب المجموع الفرعي قبل الضرائب
    public function calculateSubtotal()
    {
        return $this->unit_price * $this->quantity - $this->discount;
    }

    // حساب مجموع الضرائب
    public function calculateTaxAmount()
    {
        $subtotal = $this->calculateSubtotal();

        return $subtotal * ($this->tax_1 / 100) + $subtotal * ($this->tax_2 / 100);
    }

    // حساب المجموع الكلي
    public function calculateTotal()
    {
        return $this->calculateSubtotal() + $this->calculateTaxAmount();
    }

    // تحديث المجموع
    public function updateTotal()
    {
        $this->total = $this->calculateTotal();

        return $this;
    }

    // إضافة علاقة مع الدفع (PaymentVoucherDetail)
    public function paymentVoucherDetails()
    {
        return $this->hasMany(PaymentVoucherDetail::class, 'invoice_item_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id'); // Adjust if your foreign key is different
    }

    public function item()
    {
        return $this->belongsTo(Product::class); // Adjust if your foreign key is different
    }

    public function orderPurchase()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); // Adjust if your foreign key is different
    }

    public function purchaseQuotation()
    {
        return $this->belongsTo(PurchaseQuotation::class, 'purchase_quotation_id'); // Adjust if your foreign key is different
    }

    public function permissionSource()
    {
        return $this->belongsTo(PermissionSource::class); // Adjust if your foreign key is different
    }

    /**
     * Scope a query to only include items from a specific storehouse
     */
    public function scopeFromStorehouse($query, $storehouseId)
    {
        return $query->where('store_house_id', $storehouseId);
    }
}
