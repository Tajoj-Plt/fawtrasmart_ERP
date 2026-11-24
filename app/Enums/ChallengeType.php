<?php

namespace App\Enums;

enum ChallengeType: string
{
    case INDIVIDUAL = 'individual';
    case TEAM = 'team';
    case BRANCH = 'branch';
    case DEPARTMENT = 'department';

    public function label(): string
    {
        return match($this) {
            self::INDIVIDUAL => 'فردي',
            self::TEAM => 'فريق',
            self::BRANCH => 'فرع',
            self::DEPARTMENT => 'قسم',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
