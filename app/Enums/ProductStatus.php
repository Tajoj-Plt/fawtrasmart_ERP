<?php

namespace App\Enums;

enum ProductStatus: string
{
    case ACTIVE = 'active';
    case UNACTIVE = 'unactive';

    /**
     * Get the label for the status
     */
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'نشط',
            self::UNACTIVE => 'غير نشط',
        };
    }

    /**
     * Get the badge class for the status
     */
    public function badgeClass(): string
    {
        return match($this) {
            self::ACTIVE => 'bg-success',
            self::UNACTIVE => 'bg-secondary',
        };
    }

    /**
     * Check if the product is active
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Get all statuses as array
     */
    public static function toArray(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::UNACTIVE->value => self::UNACTIVE->label(),
        ];
    }
}
