<?php

namespace App\Enums;

enum UserType: string
{
    case EMPLOYEE = 'employee';
    case CLIENT = 'client';

    public function label(): string
    {
        return match($this) {
            self::EMPLOYEE => 'موظف',
            self::CLIENT => 'عميل',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
