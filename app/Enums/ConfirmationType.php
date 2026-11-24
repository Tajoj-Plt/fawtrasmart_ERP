<?php

namespace App\Enums;

enum ConfirmationType: string
{
    case REGISTRATION = 'registration';
    case FIRST_PURCHASE = 'first_purchase';
    case MIN_PURCHASE = 'min_purchase';
    case DAYS_ACTIVE = 'days_active';

    public function label(): string
    {
        return match($this) {
            self::REGISTRATION => 'عند التسجيل',
            self::FIRST_PURCHASE => 'أول عملية شراء',
            self::MIN_PURCHASE => 'حد أدنى للشراء',
            self::DAYS_ACTIVE => 'عدد أيام النشاط',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
