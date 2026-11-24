<?php

namespace App\Enums;

/**
 * حالة الدفع للطلب
 * يحدد الوضع الحالي لعملية الدفع
 */
enum PaymentStatus: string
{
    /**
     * قيد الانتظار - لم يتم الدفع بعد
     */
    case PENDING = 'pending';

    /**
     * مدفوع - تم الدفع بالكامل
     */
    case PAID = 'paid';

    /**
     * فشل - فشلت عملية الدفع
     */
    case FAILED = 'failed';

    /**
     * مسترد - تم استرداد المبلغ
     */
    case REFUNDED = 'refunded';

    /**
     * جزئي - تم دفع جزء من المبلغ
     */
    case PARTIAL = 'partial';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'قيد الانتظار',
            self::PAID => 'مدفوع',
            self::FAILED => 'فشل',
            self::REFUNDED => 'مسترد',
            self::PARTIAL => 'دفع جزئي',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
            self::PARTIAL => 'Partial',
        };
    }

    /**
     * الحصول على لون الحالة للعرض
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::FAILED => 'danger',
            self::REFUNDED => 'info',
            self::PARTIAL => 'primary',
        };
    }
}
