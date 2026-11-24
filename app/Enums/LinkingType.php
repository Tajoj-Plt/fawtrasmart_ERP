<?php

namespace App\Enums;

enum LinkingType: string
{
    case MULTIPLE = 'multiple';
    case SINGLE = 'single';

    /**
     * Get the Arabic name for the linking type
     */
    public function getName(): string
    {
        return match($this) {
            self::MULTIPLE => 'متعدد',
            self::SINGLE => 'احادي',
        };
    }

    /**
     * Get all linking types with their details
     */
    public static function getAllWithDetails(): array
    {
        $types = [];
        foreach (self::cases() as $case) {
            $types[$case->value] = [
                'code' => $case->value,
                'name' => $case->getName(),
            ];
        }
        return $types;
    }

    /**
     * Get linking type by its string value
     */
    public static function fromValue(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }
        return null;
    }
}