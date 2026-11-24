<?php

namespace App\Enums;

/**
 * نوع الرسوم الإضافية
 * يحدد طريقة حساب الرسوم الإضافية على الطلب
 */
enum ExtraFeeType: string
{
    /**
     * مبلغ ثابت - رسوم بمبلغ محدد
     */
    case FIXED = 'fixed';

    /**
     * نسبة مئوية - رسوم بنسبة من إجمالي الطلب
     */
    case PERCENTAGE = 'percentage';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::FIXED => 'مبلغ ثابت',
            self::PERCENTAGE => 'نسبة مئوية',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::FIXED => 'Fixed Amount',
            self::PERCENTAGE => 'Percentage',
        };
    }

    /**
     * الحصول على رمز النوع
     */
    public function symbol(): string
    {
        return match($this) {
            self::FIXED => 'ر.س',
            self::PERCENTAGE => '%',
        };
    }
}
