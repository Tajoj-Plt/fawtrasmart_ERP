<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case PICKUP = 'pickup';
    case DELIVERY = 'delivery';
    case DIGITAL = 'digital';

    public function label(): string
    {
        return match($this) {
            self::PICKUP => 'استلام من الفرع',
            self::DELIVERY => 'توصيل',
            self::DIGITAL => 'رقمي',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
