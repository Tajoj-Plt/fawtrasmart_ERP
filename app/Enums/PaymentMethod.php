<?php

namespace App\Enums;

/**
 * طريقة الدفع في المتجر الإلكتروني
 * يحدد الوسيلة المستخدمة لدفع قيمة الطلب
 */
enum PaymentMethod: string
{
    /**
     * نقدي - الدفع نقداً
     */
    case CASH = 'cash';

    /**
     * بطاقة - الدفع بالبطاقة الائتمانية أو مدى
     */
    case CARD = 'card';

    /**
     * محفظة - الدفع من رصيد المحفظة الإلكترونية
     */
    case WALLET = 'wallet';

    /**
     * تحويل بنكي - الدفع عبر التحويل البنكي
     */
    case BANK_TRANSFER = 'bank_transfer';

    /**
     * الدفع عند الاستلام - COD
     */
    case COD = 'cod';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::CASH => 'نقدي',
            self::CARD => 'بطاقة',
            self::WALLET => 'محفظة',
            self::BANK_TRANSFER => 'تحويل بنكي',
            self::COD => 'الدفع عند الاستلام',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::CASH => 'Cash',
            self::CARD => 'Card',
            self::WALLET => 'Wallet',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::COD => 'Cash on Delivery',
        };
    }

    /**
     * الحصول على أيقونة طريقة الدفع
     */
    public function icon(): string
    {
        return match($this) {
            self::CASH => 'money-bill',
            self::CARD => 'credit-card',
            self::WALLET => 'wallet',
            self::BANK_TRANSFER => 'university',
            self::COD => 'hand-holding-usd',
        };
    }

    /**
     * التحقق من أن الطريقة تتطلب دفع فوري
     */
    public function requiresImmediatePayment(): bool
    {
        return in_array($this, [
            self::CARD,
            self::WALLET,
        ]);
    }
}
