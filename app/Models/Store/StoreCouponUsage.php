<?php

namespace App\Models\Store;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * مودل استخدامات كوبونات المتجر
 * Store Coupon Usage Model
 *
 * يدير سجل استخدامات كوبونات الخصم
 * يحتوي على معلومات من استخدم الكوبون ومتى وبأي مبلغ خصم
 */
class StoreCouponUsage extends Model
{
    use HasFactory;

    protected $table = 'store_coupon_usages';

    protected $fillable = [
        'coupon_id',
        'client_id',
        'invoice_id',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    /**
     * العلاقة مع الكوبون
     */
    public function coupon()
    {
        return $this->belongsTo(StoreCoupon::class, 'coupon_id');
    }

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
     * استخدامات كوبون محدد
     */
    public function scopeForCoupon($query, $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    /**
     * استخدامات عميل محدد
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * الحصول على إجمالي الخصم لكوبون محدد
     */
    public static function getTotalDiscountForCoupon($couponId): float
    {
        return self::forCoupon($couponId)->sum('discount_amount');
    }

    /**
     * الحصول على عدد مرات استخدام الكوبون من قبل عميل
     */
    public static function getUsageCountByClient($couponId, $clientId): int
    {
        return self::forCoupon($couponId)
            ->forClient($clientId)
            ->count();
    }

    /**
     * الحصول على إجمالي الخصم لعميل محدد
     */
    public static function getTotalDiscountForClient($clientId): float
    {
        return self::forClient($clientId)->sum('discount_amount');
    }

    /**
     * الحصول على أكثر الكوبونات استخداماً
     */
    public static function getMostUsedCoupons($limit = 10)
    {
        return self::selectRaw('coupon_id, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->groupBy('coupon_id')
            ->orderBy('usage_count', 'desc')
            ->with('coupon')
            ->limit($limit)
            ->get();
    }

    /**
     * الحصول على أكثر العملاء استخداماً للكوبونات
     */
    public static function getTopCouponUsers($limit = 10)
    {
        return self::selectRaw('client_id, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->groupBy('client_id')
            ->orderBy('total_discount', 'desc')
            ->with('client')
            ->limit($limit)
            ->get();
    }
}
