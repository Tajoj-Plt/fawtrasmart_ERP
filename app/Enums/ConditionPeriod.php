<?php

namespace App\Enums;

enum ConditionPeriod: string
{
    case ALL_TIME = 'all_time';
    case YEARLY = 'yearly';
    case MONTHLY = 'monthly';
    case WEEKLY = 'weekly';
    case DAILY = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::ALL_TIME => 'طوال الوقت',
            self::YEARLY => 'سنوياً',
            self::MONTHLY => 'شهرياً',
            self::WEEKLY => 'أسبوعياً',
            self::DAILY => 'يومياً',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
