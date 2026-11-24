<?php

namespace App\Enums;

enum ExpiryType: string
{
    case FIFO = 'fifo';
    case FIXED_DATE = 'fixed_date';
    case ANNIVERSARY = 'anniversary';
    case NEVER = 'never';

    public function label(): string
    {
        return match($this) {
            self::FIFO => 'الأول يدخل الأول يخرج',
            self::FIXED_DATE => 'تاريخ ثابت',
            self::ANNIVERSARY => 'ذكرى سنوية',
            self::NEVER => 'لا تنتهي',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
