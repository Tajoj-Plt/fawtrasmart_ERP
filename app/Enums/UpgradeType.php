<?php

namespace App\Enums;

enum UpgradeType: string
{
    case AUTOMATIC = 'automatic';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match($this) {
            self::AUTOMATIC => 'تلقائي',
            self::MANUAL => 'يدوي',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
