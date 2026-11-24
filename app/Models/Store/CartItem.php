<?php

namespace App\Models\Store;

use App\Models\Product;
use App\Models\StoreHouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل عناصر سلة التسوق
 * Cart Item Model
 *
 * يدير العناصر الفردية في سلة التسوق
 * يحتوي على معلومات المنتج والكمية والسعر
 */
class CartItem extends Model
{
    use HasFactory;

    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'store_house_id',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'total',
        'options',
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
     * العلاقة مع السلة
     * العنصر ينتمي لسلة واحدة
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * العلاقة مع المنتج
     * العنصر يرتبط بمنتج واحد
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * العلاقة مع المستودع
     * العنصر يمكن أن يرتبط بمستودع محدد
     */
    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    /**
     * حساب الإجمالي عند الإنشاء أو التحديث
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($cartItem) {
            $cartItem->calculateTotal();
        });

        static::saved(function ($cartItem) {
            $cartItem->cart->recalculateTotal();
        });

        static::deleted(function ($cartItem) {
            $cartItem->cart->recalculateTotal();
        });
    }

    /**
     * حساب إجمالي العنصر
     */
    public function calculateTotal(): void
    {
        // الحصول على سعر المنتج إذا لم يكن محدد
        if (! $this->unit_price && $this->product) {
            $this->unit_price = $this->product->sale_price;
        }

        $subtotal = $this->quantity * $this->unit_price;
        $this->total = $subtotal - $this->discount + $this->tax;

        // حفظ بدون إطلاق الأحداث لتجنب التكرار
        $this->saveQuietly();
    }

    /**
     * الحصول على إجمالي السعر بدون تطبيق الخصم
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * زيادة الكمية
     */
    public function increaseQuantity($amount = 1): void
    {
        $this->update([
            'quantity' => $this->quantity + $amount,
        ]);
    }

    /**
     * تقليل الكمية
     */
    public function decreaseQuantity($amount = 1): void
    {
        $newQuantity = max(1, $this->quantity - $amount);
        $this->update([
            'quantity' => $newQuantity,
        ]);
    }

    /**
     * التحقق من توفر المنتج
     */
    public function isAvailable(): bool
    {
        if (! $this->product) {
            return false;
        }

        // التحقق من الكمية المتاحة
        if ($this->store_house_id) {
            $availableQuantity = $this->product->quantityInStorehouse($this->store_house_id);
        } else {
            $availableQuantity = $this->product->totalQuantity();
        }

        return $availableQuantity >= $this->quantity;
    }

    /**
     * الحصول على تفاصيل الخيارات المنسقة
     */
    public function getFormattedOptionsAttribute(): string
    {
        if (empty($this->options)) {
            return '';
        }

        $formatted = [];
        foreach ($this->options as $key => $value) {
            $formatted[] = ucfirst($key).': '.$value;
        }

        return implode(', ', $formatted);
    }

    /**
     * حساب وفر المبلغ
     */
    public function getSavingsAttribute(): float
    {
        return $this->discount;
    }

    /**
     * الحصول على السعر النهائي للوحدة (بعد الخصم)
     */
    public function getFinalUnitPriceAttribute(): float
    {
        $discountPerUnit = $this->discount / $this->quantity;

        return $this->unit_price - $discountPerUnit;
    }
}
