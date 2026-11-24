<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case READ = 'read';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'معلق',
            self::SENT => 'مُرسل',
            self::DELIVERED => 'تم التسليم',
            self::FAILED => 'فشل',
            self::READ => 'مقروء',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::SENT => 'info',
            self::DELIVERED => 'primary',
            self::FAILED => 'danger',
            self::READ => 'success',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
