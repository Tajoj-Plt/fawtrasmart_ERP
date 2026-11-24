<?php

namespace App\Enums;

/**
 * حالة الطلب في المتجر الإلكتروني
 * يحدد المرحلة الحالية للطلب في دورة حياته
 */
enum OrderStatus: string
{
    /**
     * قيد الانتظار - الطلب جديد في انتظار التأكيد
     */
    case PENDING = 'pending';

    /**
     * مؤكد - تم تأكيد الطلب من قبل الإدارة
     */
    case CONFIRMED = 'confirmed';

    /**
     * جاري التجهيز - يتم تجهيز الطلب للشحن
     */
    case PROCESSING = 'processing';

    /**
     * جاهز للشحن - الطلب جاهز وينتظر شركة الشحن
     */
    case READY_FOR_SHIPPING = 'ready_for_shipping';

    /**
     * تم الشحن - تم تسليم الطلب لشركة الشحن
     */
    case SHIPPED = 'shipped';

    /**
     * في الطريق - الطلب في طريقه للعميل
     */
    case OUT_FOR_DELIVERY = 'out_for_delivery';

    /**
     * تم التسليم - تم تسليم الطلب للعميل بنجاح
     */
    case DELIVERED = 'delivered';

    /**
     * ملغي - تم إلغاء الطلب
     */
    case CANCELLED = 'cancelled';

    /**
     * مرتجع - تم إرجاع الطلب
     */
    case RETURNED = 'returned';

    /**
     * مسترد - تم استرداد المبلغ
     */
    case REFUNDED = 'refunded';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'قيد الانتظار',
            self::CONFIRMED => 'مؤكد',
            self::PROCESSING => 'جاري التجهيز',
            self::READY_FOR_SHIPPING => 'جاهز للشحن',
            self::SHIPPED => 'تم الشحن',
            self::OUT_FOR_DELIVERY => 'في الطريق',
            self::DELIVERED => 'تم التسليم',
            self::CANCELLED => 'ملغي',
            self::RETURNED => 'مرتجع',
            self::REFUNDED => 'مسترد',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::PROCESSING => 'Processing',
            self::READY_FOR_SHIPPING => 'Ready for Shipping',
            self::SHIPPED => 'Shipped',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned',
            self::REFUNDED => 'Refunded',
        };
    }

    /**
     * الحصول على لون الحالة للعرض
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::CONFIRMED => 'info',
            self::PROCESSING => 'primary',
            self::READY_FOR_SHIPPING => 'info',
            self::SHIPPED => 'primary',
            self::OUT_FOR_DELIVERY => 'info',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
            self::RETURNED => 'secondary',
            self::REFUNDED => 'dark',
        };
    }

    /**
     * الحصول على أيقونة الحالة
     */
    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'clock',
            self::CONFIRMED => 'check-circle',
            self::PROCESSING => 'cog',
            self::READY_FOR_SHIPPING => 'box',
            self::SHIPPED => 'truck',
            self::OUT_FOR_DELIVERY => 'shipping-fast',
            self::DELIVERED => 'check-double',
            self::CANCELLED => 'times-circle',
            self::RETURNED => 'undo',
            self::REFUNDED => 'money-bill-wave',
        };
    }

    /**
     * التحقق من إمكانية إلغاء الطلب
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
            self::PROCESSING,
        ]);
    }

    /**
     * التحقق من إمكانية الإرجاع
     */
    public function canBeReturned(): bool
    {
        return $this === self::DELIVERED;
    }

    /**
     * الحصول على الحالات التالية المسموحة
     */
    public function allowedNextStatuses(): array
    {
        return match($this) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::READY_FOR_SHIPPING, self::CANCELLED],
            self::READY_FOR_SHIPPING => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::OUT_FOR_DELIVERY, self::DELIVERED, self::RETURNED],
            self::OUT_FOR_DELIVERY => [self::DELIVERED, self::RETURNED],
            self::DELIVERED => [self::RETURNED],
            self::RETURNED => [self::REFUNDED],
            self::CANCELLED => [],
            self::REFUNDED => [],
        };
    }
}
