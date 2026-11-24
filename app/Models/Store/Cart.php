<?php

namespace App\Models\Store;

use App\Models\Client;
use App\Services\OnlineStoreSettingsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل سلة التسوق
 * Shopping Cart Model
 *
 * يدير سلة التسوق للعملاء المسجلين والزوار
 * يحتوي على معلومات المنتجات والخصومات والضرائب
 */
class Cart extends Model
{
    use HasFactory;

    protected $table = 'carts';

    protected $fillable = [
        'client_id',
        'session_id',
        'coupon_id',
        'subtotal',
        'discount',
        'tax',
        'shipping_cost',
        'total',
        'notes',
        'expires_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    /**
     * العلاقة مع العميل
     * السلة تنتمي لعميل واحد (أو null للزوار)
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * العلاقة مع كوبون الخصم
     * السلة يمكن أن تحتوي على كوبون خصم واحد
     */
    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'coupon_id');
    }

    /**
     * العلاقة مع عناصر السلة
     * السلة تحتوي على عدة عناصر
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * السلال غير المنتهية الصلاحية
     */
    public function scopeActive($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * السلال المنتهية الصلاحية
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * عدد العناصر في السلة
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * إضافة منتج للسلة
     */
    public function addItem($productId, $quantity = 1, $options = []): CartItem
    {
        $existingItem = $this->items()
            ->where('product_id', $productId)
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $quantity,
            ]);
            $existingItem->calculateTotal();

            return $existingItem;
        }

        $item = $this->items()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'options' => $options,
        ]);

        $item->calculateTotal();

        return $item;
    }

    /**
     * إزالة منتج من السلة
     */
    public function removeItem($productId): bool
    {
        $item = $this->items()->where('product_id', $productId)->first();

        if ($item) {
            $item->delete();
            $this->recalculateTotal();

            return true;
        }

        return false;
    }

    /**
     * تحديث كمية منتج في السلة
     */
    public function updateItemQuantity($productId, $quantity): bool
    {
        $item = $this->items()->where('product_id', $productId)->first();

        if ($item) {
            if ($quantity <= 0) {
                return $this->removeItem($productId);
            }

            $item->update(['quantity' => $quantity]);
            $item->calculateTotal();
            $this->recalculateTotal();

            return true;
        }

        return false;
    }

    /**
     * مسح السلة بالكامل
     */
    public function clear(): void
    {
        $this->items()->delete();
        $this->update([
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'coupon_id' => null,
        ]);
    }

    /**
     * إعادة حساب إجمالي السلة
     */
    public function recalculateTotal(): void
    {
        // تحميل العناصر والكوبون إذا لم تكن محملة
        $this->loadMissing(['items', 'coupon']);

        $subtotal = $this->items->sum('total');
        $discount = $this->calculateDiscount($subtotal);
        $tax = $this->calculateTax($subtotal - $discount);
        $total = $subtotal - $discount + $tax + $this->shipping_cost + $this->shippingTax();

        $this->update([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
        ]);
    }

    /**
     * ضريبة الشحن — رسمُ الشحن × نسبة ضريبة المتجر، مضافةً فوقه (صفر إن لم يُحدَّد شحن).
     * رسمُ الشحن يُختار عند إتمام الطلب، فالسلةُ تحمله صفراً عادةً.
     */
    public function shippingTax(): float
    {
        return OnlineStoreSettingsService::shippingVatFor((float) $this->shipping_cost);
    }

    /**
     * حساب الخصم
     */
    protected function calculateDiscount($subtotal): float
    {
        if (! $this->coupon) {
            return 0;
        }

        // استخدام دالة حساب الخصم من الكوبون
        $productIds = $this->items->pluck('product_id')->toArray();

        return $this->coupon->calculateDiscount($subtotal, $productIds);
    }

    /**
     * حساب الضريبة
     */
    protected function calculateTax($amount): float
    {
        // جلب نسبة الضريبة من الإعدادات
        $checkoutSettings = OnlineStoreSettingsService::getCheckoutSettings();
        $taxRate = floatval($checkoutSettings['tax_rate'] ?? 15) / 100; // تحويل من نسبة مئوية
        $taxIncluded = $checkoutSettings['tax_included'] ?? false;

        // إذا كانت الضريبة مشمولة في السعر، لا نضيفها مرة أخرى
        if ($taxIncluded) {
            return 0;
        }

        return $amount * $taxRate;
    }

    /**
     * التحقق من انتهاء صلاحية السلة
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * تمديد صلاحية السلة
     */
    public function extendExpiry($days = 7): void
    {
        $this->update([
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * التحقق من فراغ السلة
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }
}
