<?php

namespace App\Models\Store;

use App\Enums\ShippingMethodType;
use App\Models\Account;
use App\Models\Concerns\DefaultsToMainBranch;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل طرق الشحن
 * Shipping Method Model
 *
 * يدير الطرق المختلفة لشحن المنتجات للعملاء
 * يحتوي على معلومات التكلفة والوقت المتوقع للتوصيل
 */
class ShippingMethod extends Model
{
    use BelongsToBranch, DefaultsToMainBranch, HasFactory;

    protected $table = 'shipping_methods';

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'description',
        'logo',
        'type',
        'cost',
        'min_order_amount',
        'free_shipping_threshold',
        'supports_express',
        'estimated_days_min',
        'estimated_days_max',
        'available_cities',
        'available_regions',
        'is_active',
        'sort_order',
        'account_id',
    ];

    protected $casts = [
        'type' => ShippingMethodType::class,
        'cost' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'supports_express' => 'boolean',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'available_cities' => 'array',
        'available_regions' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * العلاقة مع الحساب المحاسبي
     * طريقة الشحن مرتبطة بحساب محاسبي
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * العلاقة مع الطلبات الإلكترونية
     * طريقة الشحن تستخدم في عدة طلبات
     */
    public function onlineOrders()
    {
        return $this->hasMany(OnlineOrder::class);
    }

    /**
     * طرق الشحن النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * ترتيب طرق الشحن
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * حساب تكلفة الشحن الأساسية بناءً على قيمة الطلب
     * هذه التكلفة الأساسية فقط (ما يُدفع لشركة الشحن)
     * نسبة إيرادات الشحن تُحسب في الكنترولر
     */
    public function calculateShippingCost(float $orderAmount): float
    {
        // إذا كان المبلغ يستحق الشحن المجاني
        if ($this->free_shipping_threshold && $orderAmount >= $this->free_shipping_threshold) {
            return 0;
        }

        // إذا كان نوع الشحن مجاني
        if ($this->type === ShippingMethodType::FREE) {
            return 0;
        }

        // إرجاع التكلفة الأساسية فقط (بدون نسبة الإيرادات)
        // نسبة الإيرادات تُحسب في StoreOrderController
        return $this->cost;
    }

    /**
     * ما تطلبه شركة الشحن فعلاً عن الطلب — تكلفةُ الطريقة نفسها بصرف النظر عن
     * إعفاء العميل منها (حدّ الشحن المجاني أو كوبون): الإعفاءُ يُسقط ما يدفعه
     * العميل لا ما تدفعه المنشأة. طريقةٌ نوعها «مجاني» (استلام من الفرع/توصيل
     * ذاتي) لا شركةَ خلفها فتكلفتُها صفر.
     */
    public function carrierCost(): float
    {
        if ($this->type === ShippingMethodType::FREE) {
            return 0.0;
        }

        return round(max(0.0, (float) $this->cost), 2);
    }

    /**
     * الحصول على نص الوقت المتوقع للتوصيل
     */
    public function getEstimatedDeliveryTextAttribute(): string
    {
        if ($this->estimated_days_min && $this->estimated_days_max) {
            if ($this->estimated_days_min == $this->estimated_days_max) {
                return "{$this->estimated_days_min} يوم";
            }

            return "{$this->estimated_days_min}-{$this->estimated_days_max} أيام";
        } elseif ($this->estimated_days_min) {
            return "من {$this->estimated_days_min} أيام";
        } elseif ($this->estimated_days_max) {
            return "حتى {$this->estimated_days_max} أيام";
        }

        return 'غير محدد';
    }

    /**
     * التحقق من الحد الأدنى للطلب
     */
    public function meetsMinimumOrder(float $orderAmount): bool
    {
        return ! $this->min_order_amount || $orderAmount >= $this->min_order_amount;
    }
}
