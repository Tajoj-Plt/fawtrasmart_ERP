<?php

namespace App\Enums;

/**
 * نوع رابط البانر
 * يحدد نوع الوجهة عند النقر على البانر
 */
enum BannerLinkType: string
{
    /**
     * رابط خارجي - URL مباشر
     */
    case URL = 'url';

    /**
     * رابط منتج - يوجه لصفحة منتج محدد
     */
    case PRODUCT = 'product';

    /**
     * رابط تصنيف - يوجه لصفحة تصنيف محدد
     */
    case CATEGORY = 'category';

    /**
     * رابط علامة تجارية - يوجه لصفحة براند محدد
     */
    case BRAND = 'brand';

    /**
     * رابط صفحة - يوجه لصفحة ثابتة في المتجر
     */
    case PAGE = 'page';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::URL => 'رابط خارجي',
            self::PRODUCT => 'منتج',
            self::CATEGORY => 'تصنيف',
            self::BRAND => 'علامة تجارية',
            self::PAGE => 'صفحة',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::URL => 'External URL',
            self::PRODUCT => 'Product',
            self::CATEGORY => 'Category',
            self::BRAND => 'Brand',
            self::PAGE => 'Page',
        };
    }
}
