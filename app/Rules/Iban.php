<?php

namespace App\Rules;

use App\Constants\Banks;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * تحقُّقٌ من رقم الآيبان (ISO 13616).
 *
 * ثلاثُ طبقاتٍ لا واحدة، وكلُّ واحدةٍ تمسك ما لا تمسكه أختُها:
 *
 *   ١) **الصيغة والطول** — يمسك النقصَ والزيادةَ والحروفَ الدخيلة.
 *   ٢) **خانتا التحقّق (mod-97)** — تمسكان الخطأَ المطبعيّ: رقمٌ مقلوبٌ
 *      أو خانةٌ مبدَّلة تُنتج آيباناً صحيحَ الطول فاسدَ المضمون. وهذا ما
 *      لا يمسكه فحصُ الطول وحدَه، وهو أكثرُ الأخطاء وقوعاً.
 *   ٣) **مطابقةُ البنك** — رمزُ البنك داخل الآيبان يجب أن يكون بنكَ
 *      الحساب المختار. فمن اختار «الراجحي» وألصق آيبانَ حسابه في «الأهلي»
 *      يُردّ، ولولا ذلك لخرجت الحوالةُ إلى بنكٍ غير المقصود.
 */
class Iban implements ValidationRule
{
    /**
     * @param  string|null  $expectedBankCode  رمزُ البنك المختار — يُطابَق برمز الآيبان
     */
    public function __construct(
        private ?string $expectedBankCode = null,
        private string $country = Banks::DEFAULT_COUNTRY,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = Banks::normalize(is_string($value) ? $value : '');

        if ($iban === '') {
            $fail('رقم الآيبان مطلوب.');

            return;
        }

        // ── ١) الصيغة ──
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            $fail('صيغةُ الآيبان غير صحيحة: يبدأ برمز الدولة (حرفان) ثم خانتَي تحقّق ثم رقم الحساب.');

            return;
        }

        $countryCode = substr($iban, 0, 2);
        $spec = Banks::ibanSpec($countryCode);

        if (! $spec) {
            $fail("رمزُ الدولة «{$countryCode}» غير مدعوم في الآيبان.");

            return;
        }

        if (strlen($iban) !== $spec['length']) {
            $fail(sprintf(
                'طولُ الآيبان لدولة %s يجب أن يكون %d خانة — المُدخَل %d.',
                $countryCode,
                $spec['length'],
                strlen($iban)
            ));

            return;
        }

        // ── ٢) خانتا التحقّق ──
        if (! $this->checksumIsValid($iban)) {
            $fail('رقمُ الآيبان غير صحيح: خانتا التحقّق لا تطابقان باقي الرقم. راجع الرقم كما هو في كشف البنك.');

            return;
        }

        // ── ٣) البنك ──
        if ($this->expectedBankCode) {
            $actual = substr($iban, $spec['bank_offset'], $spec['bank_length']);

            if ($actual !== $this->expectedBankCode) {
                $expectedName = Banks::name($this->expectedBankCode, $this->country) ?? $this->expectedBankCode;
                $actualName = Banks::name($actual, $this->country);

                $fail(sprintf(
                    'الآيبان لا يخصّ «%s»%s. رمزُ البنك في الآيبان هو %s — تأكّد من اختيار البنك الصحيح.',
                    $expectedName,
                    $actualName ? " بل «{$actualName}»" : '',
                    $actual
                ));
            }
        }
    }

    /**
     * فحصُ mod-97 كما في ISO 13616.
     *
     * تُنقل الخاناتُ الأربعُ الأولى إلى آخر السلسلة، ثم يُستبدل كلُّ حرفٍ
     * برقمه (A=10 … Z=35)، ثم يُقسَّم الناتجُ على 97 — والباقي يجب أن يكون 1.
     *
     * والقسمةُ على أجزاء لا دفعةً واحدة: الرقمُ الناتج يبلغ نحوَ ثلاثين
     * خانة، وهو أكبر من عددٍ صحيحٍ في PHP على أنظمة 32-بت — فيُقسَّم
     * تدريجياً بما يبقى في المدى الآمن.
     */
    private function checksumIsValid(string $iban): bool
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char)
                ? (string) (ord($char) - 55)   // A=10 … Z=35
                : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder.$chunk) % 97);
        }

        return $remainder === 1;
    }
}
