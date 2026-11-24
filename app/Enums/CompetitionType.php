<?php

namespace App\Enums;

enum CompetitionType: string
{
    case TARGET = 'target';
    case RANKING = 'ranking';
    case FIRST_N = 'first_n';

    public function label(): string
    {
        return match($this) {
            self::TARGET => 'تحقيق هدف محدد',
            self::RANKING => 'ترتيب',
            self::FIRST_N => 'أول N فائزين',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
