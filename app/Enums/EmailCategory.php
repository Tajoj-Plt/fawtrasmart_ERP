<?php

namespace App\Enums;

/**
 * تصنيفات قوالب البريد الإلكتروني للمتجر الإلكتروني
 */
enum EmailCategory: string
{
    case Auth = 'auth';                     // المصادقة والتحقق
    case Welcome = 'welcome';               // الترحيب
    case Orders = 'orders';                 // الطلبات
    case Shipping = 'shipping';             // الشحن والتوصيل
    case Payments = 'payments';             // المدفوعات والفواتير
    case Reviews = 'reviews';               // التقييمات والمراجعات
    case Wishlist = 'wishlist';             // قائمة الأمنيات
    case Cart = 'cart';                     // السلة المتروكة
    case Products = 'products';             // المنتجات
    case Coupons = 'coupons';               // الكوبونات والعروض
    case Loyalty = 'loyalty';               // نقاط الولاء
    case Support = 'support';               // الدعم الفني
    case Account = 'account';               // إدارة الحساب
    case Notifications = 'notifications';   // الإشعارات العامة
    case Marketing = 'marketing';           // التسويق
    case Subscriptions = 'subscription';    // الاشتراكات

    /**
     * الحصول على التسمية العربية للتصنيف
     */
    public function label(): string
    {
        return match($this) {
            self::Auth => 'المصادقة والتحقق',
            self::Welcome => 'الترحيب',
            self::Orders => 'الطلبات',
            self::Shipping => 'الشحن والتوصيل',
            self::Payments => 'المدفوعات والفواتير',
            self::Reviews => 'التقييمات والمراجعات',
            self::Wishlist => 'قائمة الأمنيات',
            self::Cart => 'السلة المتروكة',
            self::Products => 'المنتجات',
            self::Coupons => 'الكوبونات والعروض',
            self::Loyalty => 'نقاط الولاء',
            self::Support => 'الدعم الفني',
            self::Account => 'إدارة الحساب',
            self::Notifications => 'الإشعارات العامة',
            self::Marketing => 'التسويق',
            self::Subscriptions => 'الاشتراكات',
        };
    }

    /**
     * الحصول على الأيقونة
     */
    public function icon(): string
    {
        return match($this) {
            self::Auth => 'fa-shield-alt',
            self::Welcome => 'fa-hand-wave',
            self::Orders => 'fa-shopping-bag',
            self::Shipping => 'fa-truck',
            self::Payments => 'fa-credit-card',
            self::Reviews => 'fa-star',
            self::Wishlist => 'fa-heart',
            self::Cart => 'fa-shopping-cart',
            self::Products => 'fa-box',
            self::Coupons => 'fa-tags',
            self::Loyalty => 'fa-gift',
            self::Support => 'fa-headset',
            self::Account => 'fa-user-cog',
            self::Notifications => 'fa-bell',
            self::Marketing => 'fa-bullhorn',
            self::Subscriptions => 'fa-sync-alt',
        };
    }

    /**
     * الحصول على جميع القيم
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
