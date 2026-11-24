<?php

namespace App\Enums;

enum RewardType: string
{
    case DISCOUNT_PERCENTAGE = 'discount_percentage';
    case DISCOUNT_AMOUNT = 'discount_amount';
    case GIFT = 'gift';
    case VOUCHER = 'voucher';
    case CASH = 'cash';
    case LEAVE_DAY = 'leave_day';
    case FREE_SHIPPING = 'free_shipping';
    case PRODUCT = 'product';
    case SERVICE = 'service';

    public function label(): string
    {
        return match($this) {
            self::DISCOUNT_PERCENTAGE => 'خصم نسبة مئوية',
            self::DISCOUNT_AMOUNT => 'خصم مبلغ محدد',
            self::GIFT => 'هدية',
            self::VOUCHER => 'قسيمة شرائية',
            self::CASH => 'مكافأة نقدية',
            self::LEAVE_DAY => 'إجازة',
            self::FREE_SHIPPING => 'شحن مجاني',
            self::PRODUCT => 'منتج',
            self::SERVICE => 'خدمة',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DISCOUNT_PERCENTAGE => '📊',
            self::DISCOUNT_AMOUNT => '💰',
            self::GIFT => '🎁',
            self::VOUCHER => '🎫',
            self::CASH => '💵',
            self::LEAVE_DAY => '🏖️',
            self::FREE_SHIPPING => '🚚',
            self::PRODUCT => '📦',
            self::SERVICE => '🛠️',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
