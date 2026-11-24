<?php

namespace App\Enums;

enum RedemptionStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'معلق',
            self::APPROVED => 'موافق عليه',
            self::PROCESSING => 'قيد التجهيز',
            self::DELIVERED => 'تم التسليم',
            self::CANCELLED => 'ملغي',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::PROCESSING => 'primary',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
