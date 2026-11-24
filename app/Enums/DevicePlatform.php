<?php

namespace App\Enums;

/**
 * نوع منصة الجهاز
 * يحدد نوع الجهاز المستخدم للإشعارات
 */
enum DevicePlatform: string
{
    /**
     * iOS - أجهزة آبل (iPhone, iPad)
     */
    case IOS = 'ios';

    /**
     * Android - أجهزة أندرويد
     */
    case ANDROID = 'android';

    /**
     * Web - متصفح الويب
     */
    case WEB = 'web';

    /**
     * الحصول على التسمية العربية
     */
    public function label(): string
    {
        return match($this) {
            self::IOS => 'آي أو إس',
            self::ANDROID => 'أندرويد',
            self::WEB => 'متصفح ويب',
        };
    }

    /**
     * الحصول على التسمية الإنجليزية
     */
    public function labelEn(): string
    {
        return match($this) {
            self::IOS => 'iOS',
            self::ANDROID => 'Android',
            self::WEB => 'Web',
        };
    }

    /**
     * الحصول على أيقونة المنصة
     */
    public function icon(): string
    {
        return match($this) {
            self::IOS => 'apple',
            self::ANDROID => 'android',
            self::WEB => 'globe',
        };
    }
}
