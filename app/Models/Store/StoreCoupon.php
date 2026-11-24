<?php

namespace App\Models\Store;

use App\Enums\CouponType;
use App\Models\Concerns\DefaultsToMainBranch;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل كوبونات المتجر
 * Store Coupon Model
 *
 * يدير كوبونات الخصم المختلفة في المتجر الإلكتروني
 * يحتوي على معلومات الخصم والشروط وحدود الاستخدام
 */
class StoreCoupon extends Model
{
    use BelongsToBranch, DefaultsToMainBranch, HasFactory;

    protected $table = 'store_coupons';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'applicable_products',
        'applicable_categories',
        'excluded_products',
        'applicable_clients',
        'is_first_order_only',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'used_count' => 'integer',
        'applicable_products' => 'array',
        'applicable_categories' => 'array',
        'excluded_products' => 'array',
        'applicable_clients' => 'array',
        'is_first_order_only' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * العلاقة مع استخدامات الكوبون
     */
    public function usages()
    {
        return $this->hasMany(StoreCouponUsage::class, 'coupon_id');
    }

    /**
     * العلاقة مع الطلبات التي استخدمت هذا الكوبون
     */
    public function orders()
    {
        return $this->hasMany(OnlineOrder::class, 'coupon_id');
    }

    /**
     * الكوبونات النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * الكوبونات السارية (ضمن فترة الصلاحية)
     */
    public function scopeValid($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            });
    }

    /**
     * الكوبونات المنتهية الصلاحية
     */
    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now());
    }

    /**
     * فلترة حسب نوع الكوبون
     */
    public function scopeByType($query, CouponType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * التحقق من صلاحية الكوبون
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * التحقق من إمكانية استخدام الكوبون لعميل محدد
     */
    public function canBeUsedByClient($clientId): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        // التحقق من قائمة العملاء المخصصة
        if (! empty($this->applicable_clients) && ! in_array($clientId, $this->applicable_clients)) {
            return false;
        }

        // التحقق من حد الاستخدام لكل مستخدم
        if ($this->usage_limit_per_user) {
            $userUsageCount = $this->usages()->where('client_id', $clientId)->count();
            if ($userUsageCount >= $this->usage_limit_per_user) {
                return false;
            }
        }

        // التحقق من كون الكوبون للطلب الأول فقط
        if ($this->is_first_order_only) {
            $hasOrders = OnlineOrder::where('client_id', $clientId)
                ->where('status', '!=', 'cancelled')
                ->exists();
            if ($hasOrders) {
                return false;
            }
        }

        return true;
    }

    /**
     * حساب مبلغ الخصم للطلب
     */
    public function calculateDiscount(float $orderAmount, array $productIds = []): float
    {
        // التحقق من الحد الأدنى للطلب
        if ($this->min_order_amount && $orderAmount < $this->min_order_amount) {
            return 0;
        }

        // التحقق من المنتجات المؤهلة
        if (! empty($this->applicable_products)) {
            $hasApplicableProduct = ! empty(array_intersect($productIds, $this->applicable_products));
            if (! $hasApplicableProduct) {
                return 0;
            }
        }

        // التحقق من المنتجات المستثناة
        if (! empty($this->excluded_products)) {
            $hasExcludedProduct = ! empty(array_intersect($productIds, $this->excluded_products));
            if ($hasExcludedProduct) {
                return 0;
            }
        }

        $discount = 0;

        switch ($this->type) {
            case CouponType::PERCENTAGE:
                $discount = $orderAmount * ($this->value / 100);
                break;

            case CouponType::FIXED:
                $discount = $this->value;
                break;

            case CouponType::FREE_SHIPPING:
                // يتم التعامل مع هذا في منطق الشحن
                $discount = 0;
                break;
        }

        // تطبيق الحد الأقصى للخصم
        if ($this->max_discount && $discount > $this->max_discount) {
            $discount = $this->max_discount;
        }

        // التأكد من عدم تجاوز مبلغ الطلب
        $discount = min($discount, $orderAmount);

        return $discount;
    }

    /**
     * استخدام الكوبون
     */
    public function use($clientId, $invoiceId = null, $discountAmount = 0): StoreCouponUsage
    {
        $this->increment('used_count');

        return $this->usages()->create([
            'client_id' => $clientId,
            'invoice_id' => $invoiceId,
            'discount_amount' => $discountAmount,
        ]);
    }

    /**
     * التحقق من انتهاء صلاحية الكوبون
     */
    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * التحقق من بداية صلاحية الكوبون
     */
    public function isStarted(): bool
    {
        return ! $this->start_date || $this->start_date->isPast();
    }

    /**
     * الحصول على نسبة الاستخدام
     */
    public function getUsagePercentageAttribute(): float
    {
        if (! $this->usage_limit) {
            return 0;
        }

        return ($this->used_count / $this->usage_limit) * 100;
    }

    /**
     * الحصول على عدد الاستخدامات المتبقية
     */
    public function getRemainingUsagesAttribute(): ?int
    {
        if (! $this->usage_limit) {
            return null;
        }

        return max(0, $this->usage_limit - $this->used_count);
    }

    /**
     * الحصول على وصف نوع الخصم
     */
    public function getDiscountDescriptionAttribute(): string
    {
        switch ($this->type) {
            case CouponType::PERCENTAGE:
                return "{$this->value}% خصم";
            case CouponType::FIXED:
                return "{$this->value} خصم ثابت";
            case CouponType::FREE_SHIPPING:
                return 'شحن مجاني';
            default:
                return '';
        }
    }
}
