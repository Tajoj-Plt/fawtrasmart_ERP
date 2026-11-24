<?php

namespace App\Enums;

/**
 * نوع طريقة الشحن
 * يحدد كيفية حساب تكلفة الشحن
 */
enum ShippingMethodType: string
{
    /**
     * شحن مجاني - بدون رسوم شحن
     */
    case FREE = 'free';

    /**
     * سعر ثابت - رسوم شحن ثابتة بغض النظر عن الكمية أو الوزن
     */
    case FLAT_RATE = 'flat_rate';

    /**
     * حسب العنصر - رسوم لكل عنصر في الطلب
     */
    case PER_ITEM = 'per_item';

    /**
     * حسب الوزن - رسوم تحسب بناءً على وزن الشحنة
     */
    case PER_WEIGHT = 'per_weight';

    /**
     * حسب المسافة - رسوم تحسب بناءً على مسافة التوصيل
     */
    case PER_DISTANCE = 'per_distance';

    /**
     * محسوب - رسوم تحسب ديناميكياً من شركة الشحن
     */
    case CALCULATED = 'calculated';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::FREE => 'شحن مجاني',
            self::FLAT_RATE => 'سعر ثابت',
            self::PER_ITEM => 'حسب العنصر',
            self::PER_WEIGHT => 'حسب الوزن',
            self::PER_DISTANCE => 'حسب المسافة',
            self::CALCULATED => 'محسوب',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::FREE => 'Free Shipping',
            self::FLAT_RATE => 'Flat Rate',
            self::PER_ITEM => 'Per Item',
            self::PER_WEIGHT => 'Per Weight',
            self::PER_DISTANCE => 'Per Distance',
            self::CALCULATED => 'Calculated',
        };
    }
}
