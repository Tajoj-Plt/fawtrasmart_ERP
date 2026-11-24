<?php

namespace App\Enums;

enum BatchStatus: string
{
    case ACTIVE = 'active';
    case DEPLETED = 'depleted';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'نشط',
            self::DEPLETED => 'مستنفذ',
            self::EXPIRED => 'منتهي',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::DEPLETED => 'secondary',
            self::EXPIRED => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
