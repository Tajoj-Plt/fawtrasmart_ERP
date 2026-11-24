<?php

namespace App\Enums;

enum ConditionOperator: string
{
    case GREATER_THAN_OR_EQUAL = '>=';
    case GREATER_THAN = '>';
    case EQUAL = '=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';

    public function label(): string
    {
        return match($this) {
            self::GREATER_THAN_OR_EQUAL => 'أكبر من أو يساوي',
            self::GREATER_THAN => 'أكبر من',
            self::EQUAL => 'يساوي',
            self::LESS_THAN => 'أقل من',
            self::LESS_THAN_OR_EQUAL => 'أقل من أو يساوي',
        };
    }

    public function symbol(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
