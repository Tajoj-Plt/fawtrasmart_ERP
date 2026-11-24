<?php

namespace App\Enums;

/**
 * نوع صفحة المتجر
 * يحدد نوع المحتوى الثابت في المتجر
 */
enum StorePageType: string
{
    /**
     * صفحة عادية - صفحة محتوى عام
     */
    case PAGE = 'page';

    /**
     * أسئلة شائعة - صفحة الأسئلة المتكررة
     */
    case FAQ = 'faq';

    /**
     * سياسة - صفحات السياسات (الخصوصية، الاسترجاع، إلخ)
     */
    case POLICY = 'policy';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::PAGE => 'صفحة عادية',
            self::FAQ => 'أسئلة شائعة',
            self::POLICY => 'سياسة',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::PAGE => 'Page',
            self::FAQ => 'FAQ',
            self::POLICY => 'Policy',
        };
    }
}
