<?php

namespace App\Enums;

/**
 * أنواع مستلمي البريد الإلكتروني في المتجر الإلكتروني
 */
enum RecipientType: string
{
    case Customer = 'customer';     // العميل/الزبون
    case Guest = 'guest';           // زائر غير مسجل
    case Admin = 'admin';           // المدير
    case Support = 'support';       // فريق الدعم
    case External = 'external';     // بريد خارجي
    case Employee = 'employee';     // موظف/مندوب (واجهة تطبيق الجوال)

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match ($this) {
            self::Customer => 'عميل',
            self::Guest => 'زائر',
            self::Admin => 'مدير',
            self::Support => 'فريق الدعم',
            self::External => 'خارجي',
            self::Employee => 'موظف',
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
