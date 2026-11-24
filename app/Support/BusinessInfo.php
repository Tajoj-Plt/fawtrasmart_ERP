<?php

namespace App\Support;

use App\Services\DocumentTemplateDataService;

/**
 * بيانات المنشأة كما ضُبطت في شاشة «معلومات الحساب».
 *
 * **لماذا؟**
 *
 * قوالب الطباعة في وحدة المشتريات كانت تكتب اسم المنشأة وعنوانها ورقم
 * هاتفها نصّاً ثابتاً داخل القالب — فمن غيّرها في الإعدادات لم يتغيّر شيء
 * في مطبوعاته، ومن ثبّت نظامه لمنشأة أخرى طبع باسم منشأةٍ غيره.
 *
 * المنطق موجود أصلاً في `DocumentTemplateDataService::getBusinessData()`
 * (يجمع `business_data` و`clients` و`account_settings` بترتيب أفضلية)،
 * لكنه لم يكن في متناول قالب Blade. هذا غلافٌ رفيع عليه يُحفظ لكل طلب:
 *
 *     BusinessInfo::name()        اسم المنشأة
 *     BusinessInfo::address()     العنوان المركَّب
 *     BusinessInfo::taxNumber()   الرقم الضريبي
 *     BusinessInfo::all()         الخريطة كاملة للقوالب
 */
class BusinessInfo
{
    /** @var array<string, string>|null */
    protected static ?array $cache = null;

    /**
     * الخريطة كاملة: name, logo, phone, email, tax_number,
     * commercial_registration, address, website, currency, stamp, signature.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = app(DocumentTemplateDataService::class)->getBusinessData();
            } catch (\Throwable $e) {
                // قاعدة غير مهيّأة أو جدول ناقص: القوالب تعمل بقيم فارغة
                self::$cache = [];
            }
        }

        return self::$cache;
    }

    /** يُنسى المحفوظ — بعد حفظ «معلومات الحساب» */
    public static function forget(): void
    {
        self::$cache = null;
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::all()[$key] ?? '';

        return $value !== '' ? (string) $value : $default;
    }

    public static function name(): string
    {
        return self::get('name', (string) config('app.name'));
    }

    public static function logo(): string
    {
        return self::get('logo');
    }

    public static function address(): string
    {
        return self::get('address');
    }

    public static function phone(): string
    {
        return self::get('phone');
    }

    public static function email(): string
    {
        return self::get('email');
    }

    public static function taxNumber(): string
    {
        return self::get('tax_number');
    }

    public static function commercialRegistration(): string
    {
        return self::get('commercial_registration');
    }

    public static function website(): string
    {
        return self::get('website');
    }

    public static function stamp(): string
    {
        return self::get('stamp');
    }

    public static function signature(): string
    {
        return self::get('signature');
    }
}
