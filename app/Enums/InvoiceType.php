<?php

namespace App\Enums;

enum InvoiceType: string
{
    case SALE = 'sale';
    case PURCHASE = 'purchase';

    /**
     * Get the Arabic name for the invoice type
     */
    public function getName(): string
    {
        return match($this) {
            self::SALE => 'بيع',
            self::PURCHASE => 'شراء',
        };
    }

    /**
     * Get all invoice types with their details
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
     * Get invoice type by its string value
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