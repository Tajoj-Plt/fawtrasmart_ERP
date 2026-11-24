<?php

namespace App\Enums;

/**
 * حالات البريد الإلكتروني
 */
enum EmailStatus: string
{
    case Pending = 'pending';       // قيد الانتظار
    case Queued = 'queued';         // في قائمة الانتظار
    case Sent = 'sent';             // تم الإرسال
    case Delivered = 'delivered';   // تم التسليم
    case Failed = 'failed';         // فشل الإرسال
    case Bounced = 'bounced';       // مرتد
    case Opened = 'opened';         // تم الفتح
    case Clicked = 'clicked';       // تم النقر
    case Spam = 'spam';             // بريد مزعج
    case Unsubscribed = 'unsubscribed'; // إلغاء الاشتراك

    /**
     * الحصول على التسمية العربية للحالة
     */
    public function label(): string
    {
        return match($this) {
            self::Pending => 'قيد الانتظار',
            self::Queued => 'في قائمة الانتظار',
            self::Sent => 'تم الإرسال',
            self::Delivered => 'تم التسليم',
            self::Failed => 'فشل الإرسال',
            self::Bounced => 'مرتد',
            self::Opened => 'تم الفتح',
            self::Clicked => 'تم النقر',
            self::Spam => 'بريد مزعج',
            self::Unsubscribed => 'إلغاء الاشتراك',
        };
    }

    /**
     * الحصول على لون البادج
     */
    public function badge(): string
    {
        return match($this) {
            self::Pending => 'warning',
            self::Queued => 'info',
            self::Sent => 'primary',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Bounced => 'secondary',
            self::Opened => 'info',
            self::Clicked => 'success',
            self::Spam => 'dark',
            self::Unsubscribed => 'secondary',
        };
    }

    /**
     * الحصول على جميع القيم
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
