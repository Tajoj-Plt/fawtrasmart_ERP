<?php

namespace App\Enums;

/**
 * موقع البانر في المتجر
 * يحدد مكان ظهور البانر في واجهة المتجر
 */
enum BannerPosition: string
{
    /**
     * السلايدر الرئيسي - البانر المتحرك في الصفحة الرئيسية
     */
    case HOME_SLIDER = 'home_slider';

    /**
     * بانر الصفحة الرئيسية - بانرات ثابتة في الصفحة الرئيسية
     */
    case HOME_BANNER = 'home_banner';

    /**
     * الشريط الجانبي - بانرات في القائمة الجانبية
     */
    case SIDEBAR = 'sidebar';

    /**
     * صفحة التصنيف - بانرات تظهر في صفحات التصنيفات
     */
    case CATEGORY = 'category';

    /**
     * الفوتر - بانرات في أسفل الصفحة
     */
    case FOOTER = 'footer';

    /**
     * نافذة منبثقة - بانر يظهر كـ popup
     */
    case POPUP = 'popup';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::HOME_SLIDER => 'السلايدر الرئيسي',
            self::HOME_BANNER => 'بانر الصفحة الرئيسية',
            self::SIDEBAR => 'الشريط الجانبي',
            self::CATEGORY => 'صفحة التصنيف',
            self::FOOTER => 'الفوتر',
            self::POPUP => 'نافذة منبثقة',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::HOME_SLIDER => 'Home Slider',
            self::HOME_BANNER => 'Home Banner',
            self::SIDEBAR => 'Sidebar',
            self::CATEGORY => 'Category Page',
            self::FOOTER => 'Footer',
            self::POPUP => 'Popup',
        };
    }
}
