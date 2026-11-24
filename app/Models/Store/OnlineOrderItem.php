<?php

namespace App\Models\Store;

use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\TaxSitting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل عناصر الطلب الإلكتروني
 * Online Order Item Model
 *
 * يدير العناصر الفردية في كل طلب إلكتروني
 * يحتوي على تفاصيل المنتج والكمية والأسعار وقت الطلب
 */
class OnlineOrderItem extends Model
{
    use HasFactory;

    protected $table = 'online_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'store_house_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'tax_1_id',
        'tax_2_id',
        'total',
        'options',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'options' => 'array',
    ];

    /**
     * العلاقة مع الطلب
     */
    public function order()
    {
        return $this->belongsTo(OnlineOrder::class, 'order_id');
    }

    /**
     * العلاقة مع المنتج
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * العلاقة مع المستودع
     */
    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    /**
     * إعدادا الضريبة المطبَّقان على هذا البند — نفس نمط InvoiceItem
     * (tax_1/tax_1_id و tax_2/tax_2_id): عمود الـFK يحمل إعداد الضريبة
     * الحقيقي، والعمود الرقمي يجمّد نسبته وقت البيع.
     */
    public function taxSetting1()
    {
        return $this->belongsTo(TaxSitting::class, 'tax_1_id');
    }

    public function taxSetting2()
    {
        return $this->belongsTo(TaxSitting::class, 'tax_2_id');
    }

    /**
     * عناصر طلب محدد
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * عناصر منتج محدد
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * حساب الإجمالي عند الحفظ
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($orderItem) {
            $orderItem->calculateTotal();
        });
    }

    /**
     * حساب إجمالي العنصر
     */
    public function calculateTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;
        $this->total = $subtotal - $this->discount + $this->tax;
    }

    /**
     * الحصول على المجموع الفرعي
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * الحصول على الخيارات المنسقة
     */
    public function getFormattedOptionsAttribute(): string
    {
        if (empty($this->options)) {
            return '';
        }

        // التأكد من أن options عبارة عن array
        $options = is_array($this->options) ? $this->options : [];

        if (empty($options)) {
            return '';
        }

        $formatted = [];
        foreach ($options as $key => $value) {
            // تجاهل المفاتيح الفارغة أو التي تحتوي على arrays فارغة
            if (empty($value)) {
                continue;
            }

            // التعامل مع جميع أنواع البيانات
            if (is_array($value)) {
                // تجاهل arrays الفارغة
                if (empty($value)) {
                    continue;
                }
                // إذا كان array، نحوله لنص مفصول بفواصل
                $value = implode(', ', array_map(function ($item) {
                    if (is_scalar($item)) {
                        return (string) $item;
                    }

                    return json_encode($item, JSON_UNESCAPED_UNICODE);
                }, $value));
            } elseif (is_object($value)) {
                // إذا كان object، نحوله لـ JSON
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (! is_scalar($value)) {
                // أي نوع آخر غير scalar
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                // تحويل القيمة إلى string بشكل آمن
                $value = (string) $value;
            }

            // إضافة للنتيجة فقط إذا كانت القيمة ليست فارغة
            if (! empty($value)) {
                $formatted[] = ucfirst((string) $key).': '.$value;
            }
        }

        return implode(', ', $formatted);
    }

    /**
     * الحصول على السعر بعد الخصم
     */
    public function getFinalUnitPriceAttribute(): float
    {
        if ($this->quantity == 0) {
            return 0;
        }

        $discountPerUnit = $this->discount / $this->quantity;

        return $this->unit_price - $discountPerUnit;
    }

    /**
     * الحصول على نسبة الخصم
     */
    public function getDiscountPercentageAttribute(): float
    {
        if ($this->subtotal == 0) {
            return 0;
        }

        return ($this->discount / $this->subtotal) * 100;
    }

    /**
     * الحصول على إجمالي الوفر
     */
    public function getTotalSavingsAttribute(): float
    {
        return $this->discount;
    }

    /**
     * التحقق من توفر المنتج في المخزون
     */
    public function isInStock(): bool
    {
        if (! $this->product) {
            return false;
        }

        if ($this->store_house_id) {
            $availableQuantity = $this->product->quantityInStorehouse($this->store_house_id);
        } else {
            $availableQuantity = $this->product->totalQuantity();
        }

        return $availableQuantity >= $this->quantity;
    }

    /**
     * الحصول على الكمية المتاحة
     */
    public function getAvailableQuantityAttribute(): int
    {
        if (! $this->product) {
            return 0;
        }

        if ($this->store_house_id) {
            return $this->product->quantityInStorehouse($this->store_house_id);
        }

        return $this->product->totalQuantity();
    }

    /**
     * التحقق من وجود نقص في المخزون
     */
    public function hasStockShortage(): bool
    {
        return $this->quantity > $this->available_quantity;
    }

    /**
     * الحصول على كمية النقص
     */
    public function getShortageQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->available_quantity);
    }

    /**
     * تحديث بيانات المنتج من المصدر الأصلي
     */
    public function refreshProductData(): bool
    {
        if (! $this->product) {
            return false;
        }

        return $this->update([
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku ?? $this->product->barcode,
            'unit_price' => $this->product->sale_price,
        ]);
    }

    /**
     * الحصول على معلومات المنتج المحفوظة
     */
    public function getProductInfoAttribute(): array
    {
        return [
            'name' => $this->product_name,
            'sku' => $this->product_sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'options' => $this->options,
            'current_product_exists' => ! is_null($this->product),
        ];
    }
}
