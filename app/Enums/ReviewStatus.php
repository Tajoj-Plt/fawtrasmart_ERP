<?php

namespace App\Enums;

/**
 * حالة تقييم المنتج
 * يحدد حالة مراجعة التقييم من قبل الإدارة
 */
enum ReviewStatus: string
{
    /**
     * قيد الانتظار - التقييم في انتظار مراجعة الإدارة
     */
    case PENDING = 'pending';

    /**
     * معتمد - تم الموافقة على التقييم وهو ظاهر للعملاء
     */
    case APPROVED = 'approved';

    /**
     * مرفوض - تم رفض التقييم ولن يظهر للعملاء
     */
    case REJECTED = 'rejected';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'قيد الانتظار',
            self::APPROVED => 'معتمد',
            self::REJECTED => 'مرفوض',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * الحصول على لون الحالة للعرض
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
