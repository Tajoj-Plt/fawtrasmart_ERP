<?php

namespace App\Enums;

/**
 * حالة سؤال المنتج
 * يحدد حالة السؤال المطروح على المنتج
 */
enum QuestionStatus: string
{
    /**
     * قيد الانتظار - السؤال في انتظار الإجابة
     */
    case PENDING = 'pending';

    /**
     * تمت الإجابة - تم الرد على السؤال
     */
    case ANSWERED = 'answered';

    /**
     * مرفوض - تم رفض السؤال
     */
    case REJECTED = 'rejected';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'قيد الانتظار',
            self::ANSWERED => 'تمت الإجابة',
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
            self::ANSWERED => 'Answered',
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
            self::ANSWERED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
