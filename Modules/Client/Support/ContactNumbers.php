<?php

namespace Modules\Client\Support;

/**
 * أرقامُ التواصل كما تُعرض — لا كما حُفظت.
 *
 * أعمدةُ `phone` و`mobile` في العملاء وجهات الاتصال مستوردةٌ في معظمها،
 * وفيها ما لا يصلح للعرض ولا للاتصال: `" ,0503591607"` (فاصلةٌ قبل الرقم)،
 * و`" ,"` و`" "` (خانةٌ «ممتلئة» بلا رقم)، وأرقامٌ بالأرقام الهندية
 * `٥٠٨٠٠٢٩٠٨`، وأحياناً رقمان في خانةٍ واحدة مفصولان بفاصلة. فكانت شاشةُ
 * جهات الاتصال ترسم شارةَ هاتفٍ فارغة وفاصلةً يتيمة، ورابطَ `tel:` لا يعمل.
 *
 * هذا الصنفُ يقرأ الخانة ويُرجع الأرقامَ الصالحة وحدها — ولا يكتب شيئاً في
 * قاعدة البيانات.
 */
final class ContactNumbers
{
    private const EASTERN_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const WESTERN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * الأرقامُ الصالحة في خانةٍ واحدة أو أكثر، بلا تكرار.
     *
     * @return array<int, string>
     */
    public static function from(?string ...$raw): array
    {
        $numbers = [];

        foreach ($raw as $value) {
            $value = str_replace(self::EASTERN_DIGITS, self::WESTERN_DIGITS, (string) $value);

            foreach (preg_split('/[,،;\/|]+/u', $value) as $part) {
                // يُبقى `+` البادئة وحدها — المسافات والشرطات تُحذف
                $number = preg_replace('/(?!^\+)[^\d]/u', '', trim($part));

                if (strlen(ltrim($number, '+')) >= 5 && ! in_array($number, $numbers, true)) {
                    $numbers[] = $number;
                }
            }
        }

        return $numbers;
    }

    /** أوّلُ رقمٍ صالح — أو `null`. */
    public static function first(?string ...$raw): ?string
    {
        return self::from(...$raw)[0] ?? null;
    }

    /** اسمٌ بمسافاتٍ مكرَّرة أو زائدة على طرفيه. */
    public static function name(?string ...$parts): string
    {
        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_map('strval', $parts))));
    }
}
