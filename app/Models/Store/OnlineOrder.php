<?php

namespace App\Models\Store;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Concerns\DefaultsToMainBranch;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Invoice;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل الطلبات الإلكترونية
 * Online Order Model
 *
 * يدير الطلبات المقدمة من المتجر الإلكتروني
 * يحتوي على معلومات شاملة عن الطلب والشحن والدفع
 */
class OnlineOrder extends Model
{
    /*
        العملة تُختم على الطلب وقت إنشائه (`currency`) ولا تتغيّر بعده — تغييرُ
        عملة النظام لاحقاً لا يمسّ طلباً محفوظاً. العرض من `formatMoney()`.
    */
    use BelongsToBranch, DefaultsToMainBranch, HasDocumentCurrency, HasFactory;

    protected $table = 'online_orders';

    protected $fillable = [
        'order_number',
        'branch_id',
        'client_id',
        'invoice_id',
        'address_id',
        'shipping_method_id',
        'coupon_id',
        'status',
        'payment_method',
        'payment_method_id',
        'payment_status',
        'paid_at',
        'payment_account_id',
        'currency',
        'prices_include_tax',
        'stock_returned_at',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_cost',
        'shipping_tax_amount',
        'express_shipping_fee',
        'remote_area_fee',
        'cod_fee',
        'packaging_fee',
        'gift_wrap_fee',
        'insurance_fee',
        'handling_fee',
        'service_fee',
        'custom_fee',
        'custom_fee_description',
        'total_extra_fees',
        'total_amount',
        'shipping_revenue_percentage',
        'shipping_revenue_amount',
        'shipping_company_cost',
        'is_shipping_paid',
        'shipping_paid_at',
        'tracking_number',
        'shipping_company',
        'shipping_company_phone',
        'driver_name',
        'driver_phone',
        'estimated_delivery_date',
        'shipped_at',
        'delivered_at',
        'shipping_name',
        'shipping_phone',
        'shipping_city',
        'shipping_region',
        'shipping_address',
        'shipping_latitude',
        'shipping_longitude',
        'is_gift',
        'gift_message',
        'is_express',
        'requires_signature',
        'leave_at_door',
        'customer_notes',
        'admin_notes',
        'delivery_notes',
        'cancel_reason',
        'cancel_notes',
        'refund_reason',
        'refund_amount',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_method' => PaymentMethod::class,
        'payment_status' => PaymentStatus::class,
        'paid_at' => 'datetime',
        'prices_include_tax' => 'boolean',
        'stock_returned_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_tax_amount' => 'decimal:2',
        'shipping_revenue_percentage' => 'decimal:2',
        'shipping_revenue_amount' => 'decimal:2',
        'shipping_company_cost' => 'decimal:2',
        'is_shipping_paid' => 'boolean',
        'shipping_paid_at' => 'datetime',
        'express_shipping_fee' => 'decimal:2',
        'remote_area_fee' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'packaging_fee' => 'decimal:2',
        'gift_wrap_fee' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'handling_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'custom_fee' => 'decimal:2',
        'total_extra_fees' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'estimated_delivery_date' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'shipping_latitude' => 'decimal:8',
        'shipping_longitude' => 'decimal:8',
        'is_gift' => 'boolean',
        'is_express' => 'boolean',
        'requires_signature' => 'boolean',
        'leave_at_door' => 'boolean',
    ];

    /**
     * العلاقة مع العميل
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع الفاتورة
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * العلاقة مع عنوان الشحن
     */
    public function address()
    {
        return $this->belongsTo(ClientAddress::class, 'address_id');
    }

    /**
     * العلاقة مع طريقة الشحن
     */
    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * العلاقة مع طريقة الدفع (السجل الفعلي في جدول طرق الدفع)
     */
    public function paymentMethod()
    {
        return $this->belongsTo(\App\Models\PaymentMethod::class, 'payment_method_id');
    }

    /**
     * العلاقة مع كوبون الخصم
     */
    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'coupon_id');
    }

    /**
     * العلاقة مع عناصر الطلب
     */
    public function items()
    {
        return $this->hasMany(OnlineOrderItem::class, 'order_id');
    }

    /**
     * العلاقة مع سجل تتبع الحالات
     */
    public function statusHistory()
    {
        return $this->hasMany(OnlineOrderStatusHistory::class, 'order_id');
    }

    /**
     * العلاقة مع الرسوم الإضافية
     */
    public function extraFees()
    {
        return $this->hasMany(OnlineOrderExtraFee::class, 'order_id');
    }

    /**
     * فلترة الطلبات حسب الحالة
     */
    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * الطلبات المكتملة
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatus::DELIVERED);
    }

    /**
     * الطلبات الملغاة
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', OrderStatus::CANCELLED);
    }

    /**
     * الطلبات المعلقة
     */
    public function scopePending($query)
    {
        return $query->where('status', OrderStatus::PENDING);
    }

    /**
     * تحديث حالة الطلب مع تسجيل التاريخ
     */
    public function updateStatus(OrderStatus $newStatus, $notes = null): bool
    {
        /*
            كان يكتب في سجلّ الحالات أعمدةً لا وجود لها في الجدول
            (`old_status`, `new_status`, `changed_by`) فيسقط كلُّ من ناداه بخطأ
            SQL — ومنهم إلغاءُ العميل لطلبه من المتجر. الأعمدة الفعلية:
            `status`, `notes`, `location`, `updated_by`.
        */
        if ($this->update(['status' => $newStatus])) {
            OnlineOrderStatusHistory::createRecord($this->id, $newStatus->value, $notes);

            return true;
        }

        return false;
    }

    /**
     * المبلغ المردود/المخصوم من الطلب حتى الآن (تراكمي).
     */
    public function refundedSoFar(): float
    {
        return round((float) ($this->refund_amount ?? 0), 2);
    }

    /**
     * هل تُردّ رسومُ الشحن مع المرتجع؟ لا — متى شُحن الطلب فالخدمة تمّت
     * (سواءٌ سُدِّدت شركةُ الشحن آلياً أو بقي مستحقُّها دائناً لها).
     */
    public function shippingIsRefundable(): bool
    {
        return ! $this->is_shipping_paid && ! $this->shipped_at;
    }

    /**
     * أساس الردّ: إجمالي الطلب، بلا رسوم الشحن إن نُفِّذ الشحن فعلاً.
     */
    public function refundBase(): float
    {
        // ضريبةُ الشحن تتبع الشحنَ: تبقى مع رسمه متى شُحن الطلب
        return round((float) $this->total_amount - ($this->shippingIsRefundable() ? 0 : (float) $this->shipping_cost + (float) $this->shipping_tax_amount), 2);
    }

    /**
     * أقصى ما يمكن ردّه الآن: أساس الردّ ناقصاً ما رُدّ من قبل.
     */
    public function refundableAmount(): float
    {
        return max(0, round($this->refundBase() - $this->refundedSoFar(), 2));
    }

    /**
     * المستحق تحصيلُه من العميل لطلبٍ لم يُدفع: الإجمالي ناقصاً ما خُصم منه.
     */
    public function collectibleAmount(): float
    {
        if ($this->isPaid() || in_array($this->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            return 0.0;
        }

        return max(0, round((float) $this->total_amount - $this->refundedSoFar(), 2));
    }

    /**
     * إنشاء رقم طلب فريد من إعدادات «الترقيم المتسلسل» — القسم online-order
     */
    public static function generateOrderNumber(): string
    {
        return next_document_number('online-order');
    }

    /**
     * حساب إجمالي الرسوم الإضافية
     */
    public function calculateTotalExtraFees(): float
    {
        return $this->express_shipping_fee +
               $this->remote_area_fee +
               $this->cod_fee +
               $this->packaging_fee +
               $this->gift_wrap_fee +
               $this->insurance_fee +
               $this->handling_fee +
               $this->service_fee +
               $this->custom_fee;
    }

    /**
     * إعادة حساب المجموع الإجمالي
     */
    public function recalculateTotal(): void
    {
        $totalExtraFees = $this->calculateTotalExtraFees();
        $totalAmount = $this->subtotal - $this->discount_amount + $this->tax_amount + $this->shipping_cost + $this->shipping_tax_amount + $totalExtraFees;

        $this->update([
            'total_extra_fees' => $totalExtraFees,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * التحقق من حالة الطلب
     */
    public function isCompleted(): bool
    {
        return $this->status === OrderStatus::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::CANCELLED;
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::PENDING;
    }

    public function isShipped(): bool
    {
        return $this->status === OrderStatus::SHIPPED;
    }

    /**
     * التحقق من حالة الدفع
     */
    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID;
    }

    /**
     * الحصول على وقت التسليم المتوقع
     */
    public function getEstimatedDeliveryTextAttribute(): string
    {
        if ($this->estimated_delivery_date) {
            return $this->estimated_delivery_date->format('d/m/Y');
        }

        return 'غير محدد';
    }

    /**
     * الحصول على عدد أيام التأخير
     */
    public function getDaysDelayedAttribute(): int
    {
        if (! $this->estimated_delivery_date || $this->isCompleted()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->estimated_delivery_date, false));
    }

    /**
     * التحقق من كون الطلب متأخر
     */
    public function isDelayed(): bool
    {
        return $this->days_delayed > 0;
    }

    /**
     * Accessor لـ grand_total - alias لـ total_amount للتوافق
     */
    public function getGrandTotalAttribute(): float
    {
        return (float) $this->total_amount;
    }

    /**
     * Accessor لـ items_count
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Accessor لـ items_quantity
     */
    public function getItemsQuantityAttribute(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    /**
     * Accessor لـ client_notes - alias لـ customer_notes للتوافق
     */
    public function getClientNotesAttribute(): ?string
    {
        return $this->customer_notes;
    }
}
