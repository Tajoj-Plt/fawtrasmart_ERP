<?php

namespace App\Constants;

/**
 * فهرسُ البنوك ورموزُها في الآيبان.
 *
 * اسمُ البنك ورقمُ الحساب كانا حقلَي نصٍّ حرَّين: يُكتب فيهما أيُّ شيء،
 * فيُسجَّل «الراجحي» و«الرّاجحي» و«al rajhi» بنوكاً ثلاثة، ويُقبل رقمُ
 * حسابٍ ناقصُ خانةٍ فلا يُكتشف إلا حين يرتدّ الحوالة.
 *
 * والآيبان ليس رقماً حرّاً: بنيتُه معرَّفة في ISO 13616 — رمزُ الدولة، ثم
 * خانتا تحقّق، ثم **رمزُ البنك** ثم رقمُ الحساب. فرمزُ البنك في الآيبان
 * يجب أن يطابق البنكَ المختار من القائمة، وإلا فأحدُهما خطأ.
 *
 * **الرموزُ من هيئة النقد**: تُراجَع عند اندماجِ بنكٍ أو دخولِ آخر. وهذا
 * الفهرسُ هو الموضعُ الوحيد الذي يُعدَّل فيه — الشاشاتُ والتحقّقُ تقرأ منه.
 */
class Banks
{
    /**
     * بنوكُ المملكة العربية السعودية.
     *
     * المفتاحُ رمزُ البنك في الآيبان (الخانتان الثالثة والرابعة بعد `SA`
     * وخانتَي التحقّق).
     *
     * @var array<string, string>
     */
    public const SA = [
        '10' => 'البنك الأهلي السعودي',
        '15' => 'بنك البلاد',
        '20' => 'مصرف الراجحي',
        '30' => 'البنك السعودي الفرنسي',
        '40' => 'البنك السعودي البريطاني (ساب)',
        '45' => 'البنك السعودي للاستثمار',
        '50' => 'مصرف الإنماء',
        '55' => 'بنك الرياض',
        '60' => 'بنك الجزيرة',
        '65' => 'البنك العربي الوطني',
        '76' => 'بنك الإمارات دبي الوطني',
        '80' => 'بنك الخليج الدولي',
        '95' => 'بنك الصين الصناعي والتجاري',
    ];

    /**
     * بنيةُ الآيبان لكل دولة: الطولُ الكلّي، وموضعُ رمز البنك وطولُه.
     *
     * الطولُ يفترق بين الدول (السعودية ٢٤ والإمارات ٢٣ والكويت ٣٠)، فلا
     * يصحّ فحصٌ واحدٌ لها جميعاً.
     *
     * @var array<string, array{length: int, bank_offset: int, bank_length: int}>
     */
    public const IBAN_SPEC = [
        'SA' => ['length' => 24, 'bank_offset' => 4, 'bank_length' => 2],
        'AE' => ['length' => 23, 'bank_offset' => 4, 'bank_length' => 3],
        'KW' => ['length' => 30, 'bank_offset' => 4, 'bank_length' => 4],
        'BH' => ['length' => 22, 'bank_offset' => 4, 'bank_length' => 4],
        'QA' => ['length' => 29, 'bank_offset' => 4, 'bank_length' => 4],
        'OM' => ['length' => 23, 'bank_offset' => 4, 'bank_length' => 3],
        'JO' => ['length' => 30, 'bank_offset' => 4, 'bank_length' => 4],
        'EG' => ['length' => 29, 'bank_offset' => 4, 'bank_length' => 4],
    ];

    /** الدولةُ الافتراضية — تُقرأ منها القائمةُ حين لا تحديد. */
    public const DEFAULT_COUNTRY = 'SA';

    /**
     * قائمةُ البنوك للعرض في قائمةٍ منسدلة.
     *
     * @return array<string, string>  رمزُ البنك => اسمُه
     */
    public static function forCountry(string $country = self::DEFAULT_COUNTRY): array
    {
        $country = strtoupper($country);

        return defined(static::class.'::'.$country)
            ? constant(static::class.'::'.$country)
            : [];
    }

    /** اسمُ البنك من رمزه — أو `null` إن لم يكن في الفهرس. */
    public static function name(?string $code, string $country = self::DEFAULT_COUNTRY): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return static::forCountry($country)[$code] ?? null;
    }

    /** بنيةُ الآيبان لدولةٍ ما — أو `null` إن لم تكن معرَّفة. */
    public static function ibanSpec(string $country): ?array
    {
        return static::IBAN_SPEC[strtoupper($country)] ?? null;
    }

    /**
     * رمزُ البنك المستخرَج من الآيبان — أو `null` إن تعذّر.
     *
     * يُستعمل للتحقّق من أن الآيبان يخصّ البنكَ المختار فعلاً.
     */
    public static function bankCodeFromIban(?string $iban): ?string
    {
        $iban = static::normalize($iban);

        if (strlen($iban) < 8) {
            return null;
        }

        $spec = static::ibanSpec(substr($iban, 0, 2));

        if (! $spec) {
            return null;
        }

        return substr($iban, $spec['bank_offset'], $spec['bank_length']);
    }

    /**
     * الآيبان بلا فراغاتٍ ولا شرطات، بحروفٍ كبيرة.
     *
     * ويُنظَّف أيضاً ما يلتصق بالآيبان المنسوخ من تطبيق البنك أو ملف PDF ولا
     * يُرى بالعين: علاماتُ الاتجاه (RLM/LRM وأخواتها)، والفراغُ غيرُ القاطع،
     * والنقاط — ويُحوَّل ما كُتب بالأرقام العربية (٠١٢…) أو الفارسية (۰۱۲…)
     * إلى أرقامٍ لاتينية. كان آيبانٌ صحيحٌ منسوخٌ يُرفض «صيغةً غير صحيحة».
     */
    public static function normalize(?string $iban): string
    {
        $iban = (string) $iban;
        $iban = strtr($iban, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $clean = preg_replace('/[\s\x{00A0}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\-\.]+/u', '', $iban);

        return strtoupper($clean ?? $iban);
    }

    /**
     * الآيبان مجزّأً كل أربع خانات — صيغةُ العرض المعتادة.
     *
     * `SA0380000000608010167519` تُقرأ خطأً بالعين؛ وتجزئتُها تجعل مقارنتَها
     * بورقةِ البنك ممكنة.
     */
    public static function formatIban(?string $iban): string
    {
        $clean = static::normalize($iban);

        return $clean === '' ? '' : trim(chunk_split($clean, 4, ' '));
    }
}
