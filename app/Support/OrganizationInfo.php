<?php

namespace App\Support;

use App\Services\DocumentTemplateDataService;

/**
 * بيانات المنشأة لكل ما يُطبع أو يُصدَّر أو يُنزَّل PDF.
 *
 * **ما كان**
 *
 * كل قالب طباعة يكتب اسم المنشأة وعنوانها في متنه:
 * «مؤسسة أعمال خاصة للتجارة» و«الرياض - المملكة العربية السعودية» مكتوبتين
 * حرفاً بحرف في سند الصرف وسند القبض والنسخ الحرارية و PDF — بينما الاسم
 * الحقيقي مسجَّل في «إعدادات المنشأة». فالعميل يغيّر اسمه في الإعدادات
 * فتخرج سنداته باسم مؤسسةٍ لا يعرفها.
 *
 * **ما صار**
 *
 * مصدرٌ واحد يقرأ من `business_data` ثم `clients` ثم `account_settings`
 * — وهو ترتيب المصادر نفسه الذي يعتمده مُصيّر قوالب المستندات
 * (`DocumentTemplateDataService`)، فلا يفترق المطبوعُ من القالب عن
 * المطبوع من قالب Blade مباشرةً.
 *
 * الاستعمال في قالب طباعة:
 *
 *     @php $org = \App\Support\OrganizationInfo::all(); @endphp
 *     <h1>{{ $org['name'] }}</h1>
 *     <p>{{ $org['address'] }}</p>
 *
 * أو عبر الدوال المختصرة: `OrganizationInfo::name()`.
 *
 * النتيجة محفوظة لكامل الطلب: القالب الواحد قد يسأل عن الاسم والعنوان
 * والهاتف والشعار، وكلّها من استعلامٍ واحد.
 */
class OrganizationInfo
{
    /** الحزمة المحفوظة لهذا الطلب */
    protected static ?array $cache = null;

    /**
     * كل بيانات المنشأة: name, logo, phone, email, tax_number,
     * commercial_registration, address, website, currency, stamp, signature.
     */
    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        try {
            static::$cache = app(DocumentTemplateDataService::class)->getBusinessData();
        } catch (\Throwable $e) {
            // الطباعة لا تسقط لأن جدول بيانات المنشأة لم يُهيَّأ بعد
            static::$cache = static::fallback();
        }

        return static::$cache;
    }

    /** قيمة مفردة مع بديلٍ عند غيابها */
    public static function get(string $key, string $default = ''): string
    {
        $value = static::all()[$key] ?? null;

        return filled($value) ? (string) $value : $default;
    }

    public static function name(): string
    {
        return static::get('name', config('app.name'));
    }

    public static function address(): string
    {
        return static::get('address');
    }

    public static function phone(): string
    {
        return static::get('phone');
    }

    public static function email(): string
    {
        return static::get('email');
    }

    public static function taxNumber(): string
    {
        return static::get('tax_number');
    }

    public static function commercialRegistration(): string
    {
        return static::get('commercial_registration');
    }

    public static function website(): string
    {
        return static::get('website');
    }

    public static function logo(): string
    {
        return static::get('logo');
    }

    public static function stamp(): string
    {
        return static::get('stamp');
    }

    public static function signature(): string
    {
        return static::get('signature');
    }

    /**
     * سطر تعريفي مختصر يجمع ما هو مسجَّل فقط — للتذييل تحت الترويسة.
     *
     * مثال: «س.ت 1010… — الرقم الضريبي 3001… — هاتف 0555…»
     */
    public static function identityLine(): string
    {
        $parts = array_filter([
            static::commercialRegistration() ? 'س.ت ' . static::commercialRegistration() : null,
            static::taxNumber() ? 'الرقم الضريبي ' . static::taxNumber() : null,
            static::phone() ? 'هاتف ' . static::phone() : null,
            static::email() ?: null,
        ]);

        return implode(' — ', $parts);
    }

    /** يُفرَّغ في الاختبارات أو بعد حفظ الإعدادات في الطلب نفسه */
    public static function flush(): void
    {
        static::$cache = null;
    }

    protected static function fallback(): array
    {
        return [
            'name' => config('app.name'),
            'logo' => asset('assets/images/logo/logo.png'),
            'phone' => '',
            'email' => '',
            'tax_number' => '',
            'commercial_registration' => '',
            'address' => '',
            'website' => '',
            'currency' => CurrencySettings::code(),
            'stamp' => '',
            'signature' => '',
        ];
    }
}
