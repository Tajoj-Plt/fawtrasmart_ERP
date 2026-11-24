<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case PUSH = 'push';
    case SMS = 'sms';
    case EMAIL = 'email';
    case DATABASE = 'database';

    public function label(): string
    {
        return match($this) {
            self::PUSH => 'إشعار فوري',
            self::SMS => 'رسالة نصية',
            self::EMAIL => 'بريد إلكتروني',
            self::DATABASE => 'إشعار داخلي',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
