<?php

namespace App\Enums;

/**
 * نوع الكوبون/قسيمة الخصم
 * يحدد طريقة حساب الخصم
 */
enum CouponType: string
{
    /**
     * نسبة مئوية - خصم بنسبة من إجمالي الطلب
     */
    case PERCENTAGE = 'percentage';

    /**
     * مبلغ ثابت - خصم مبلغ محدد من إجمالي الطلب
     */
    case FIXED = 'fixed';

    /**
     * شحن مجاني - إعفاء من رسوم الشحن
     */
    case FREE_SHIPPING = 'free_shipping';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::PERCENTAGE => 'نسبة مئوية',
            self::FIXED => 'مبلغ ثابت',
            self::FREE_SHIPPING => 'شحن مجاني',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::PERCENTAGE => 'Percentage',
            self::FIXED => 'Fixed Amount',
            self::FREE_SHIPPING => 'Free Shipping',
        };
    }

    /**
     * الحصول على رمز الخصم
     */
    public function symbol(): string
    {
        return match($this) {
            self::PERCENTAGE => '%',
            self::FIXED => 'ر.س',
            self::FREE_SHIPPING => '🚚',
        };
    }
}
