<?php

namespace App\Support;

use App\Models\TaxSitting;

/**
 * حسابُ الضريبة — موضعٌ واحد للسندات والتقارير والشاشات.
 *
 * **الطريقتان اللتان يعرّفهما جدولُ الضرائب:**
 *
 *   • `excluded` — الضريبةُ تُضاف فوق المبلغ.
 *         مبلغ 1,000 ونسبة 15% ⇒ ضريبة 150، الإجمالي 1,150.
 *
 *   • `included` — المبلغُ المُدخَل **يشمل** الضريبةَ أصلاً، فتُستخرَج منه.
 *         مبلغ 1,150 ونسبة 15% ⇒ ضريبة 150، الأساس 1,000، الإجمالي 1,150.
 *         والقانون: `ضريبة = المبلغ × النسبة ÷ (1 + النسبة)`.
 *
 * **وكان العمودُ `type` معرَّفاً في الجدول ولا يقرؤه شيء.** فمن ضبط ضريبةً
 * «شاملة» حُسبت له كأنها مضافة: تُضاف 15% فوق مبلغٍ يحملها، فيتضخّم
 * الإجمالي بمقدارها مرّتين — ويخرج الإقرارُ بضريبةٍ أكبر ممّا حُصِّل.
 *
 * والنسبةُ تُقبَل بالصيغتَين: كسراً (`0.15`) ومئويةً (`15`) — فبياناتٌ
 * أُدخلت بأيّهما لا تنكسر.
 */
class TaxCalculator
{
    public const EXCLUDED = 'excluded';

    public const INCLUDED = 'included';

    /**
     * تفصيلُ الضريبة لمبلغٍ ونسبةٍ وطريقة.
     *
     * @return array{base: float, tax: float, total: float}
     *                                                      base  المبلغُ قبل الضريبة
     *                                                      tax   مبلغُ الضريبة
     *                                                      total المبلغُ بعد الضريبة
     */
    public static function compute($amount, $rate, ?string $type = self::EXCLUDED): array
    {
        $amount = (float) $amount;
        $fraction = static::fraction($rate);

        if ($amount == 0.0 || $fraction <= 0) {
            return ['base' => round($amount, 2), 'tax' => 0.0, 'total' => round($amount, 2)];
        }

        if ($type === self::INCLUDED) {
            // المبلغُ يحمل الضريبة: تُستخرَج منه ولا تُضاف إليه
            $tax = $amount * $fraction / (1 + $fraction);

            return [
                'base' => round($amount - $tax, 2),
                'tax' => round($tax, 2),
                'total' => round($amount, 2),
            ];
        }

        $tax = $amount * $fraction;

        return [
            'base' => round($amount, 2),
            'tax' => round($tax, 2),
            'total' => round($amount + $tax, 2),
        ];
    }

    /**
     * التفصيلُ من سجلّ ضريبةٍ محفوظ — النسبةُ وطريقتُها منه.
     *
     * @param  int|string|null  $taxId  معرّفُ الضريبة كما يُخزَّن في `tax1`/`tax2`
     * @return array{base: float, tax: float, total: float}
     */
    public static function forTax($amount, $taxId): array
    {
        $tax = static::resolve($taxId);

        return static::compute($amount, $tax?->tax ?? 0, $tax?->type ?? self::EXCLUDED);
    }

    /**
     * تفصيلُ سندٍ كامل بضريبتَيه — الطريقة المصرية.
     *
     * المبلغُ المُدخَل في السند يُفسَّر بحسب طريقة كلّ ضريبة:
     *
     *   • ما كان «مضافاً» (`excluded`) يُحسب فوق الأساس.
     *   • ما كان «شاملاً» (`included`) فالمُدخَل يحمله أصلاً، فيُستخرَج منه.
     *
     * فإن اجتمعت الطريقتان في سندٍ واحد كان الأساسُ هو المُدخَل مقسوماً على
     * (١ + مجموع نسب الضرائب الشاملة)، ثم تُحسب كلُّ ضريبةٍ من ذلك الأساس.
     * وحسابُ كلٍّ على حدة يُخطئ حين تكون الضريبتان شاملتَين معاً، لأن كلاًّ
     * منهما تُقسَم على نسبتها وحدها فيُحتسب الأساسُ مرّتين.
     *
     * **والإجمالي هو ما يُقيَّد على الخزينة**: الأساسُ زائدَ الضرائب كلِّها —
     * لا المبلغُ المُدخَل. وسطرُ الحساب المقابل يحمل الأساسَ لا المُدخَل،
     * وإلا اختلّ توازنُ القيد بمقدار الضريبة الشاملة.
     *
     * @return array{
     *     base: float, tax1: float, tax2: float, tax: float,
     *     total: float, inclusive: bool
     * }
     */
    public static function forDocument($amount, $tax1Id = null, $tax2Id = null): array
    {
        $amount = (float) $amount;

        $one = static::resolve($tax1Id);
        $two = static::resolve($tax2Id);

        $f1 = static::fraction($one?->tax ?? 0);
        $f2 = static::fraction($two?->tax ?? 0);

        $in1 = $f1 > 0 && ($one?->type ?? self::EXCLUDED) === self::INCLUDED;
        $in2 = $f2 > 0 && ($two?->type ?? self::EXCLUDED) === self::INCLUDED;

        // ما يحمله المُدخَل من ضريبةٍ شاملة يُنزَع منه ليظهر الأساس
        $carried = ($in1 ? $f1 : 0.0) + ($in2 ? $f2 : 0.0);
        $base = $carried > 0 ? $amount / (1 + $carried) : $amount;

        $tax1 = round($base * $f1, 2);
        $tax2 = round($base * $f2, 2);
        $base = round($base, 2);

        return [
            'base' => $base,
            'tax1' => $tax1,
            'tax2' => $tax2,
            'tax' => round($tax1 + $tax2, 2),
            'total' => round($base + $tax1 + $tax2, 2),
            'inclusive' => $in1 || $in2,
        ];
    }

    /**
     * هل في السند ضريبةٌ «شاملة»؟
     *
     * يفيد شاشاتِ التعديل: خانةُ المبلغ تعني ما أدخله المستخدم بحسب
     * الطريقة — الوعاءَ في «المضافة» والإجماليَ في «الشاملة». وسندٌ شاملٌ
     * يُعاد فتحُه بوعائه المخزَّن تُستخرَج منه الضريبةُ مرّةً ثانية،
     * فينكمش المبلغُ في كلّ تعديل.
     */
    public static function isInclusive($tax1Id, $tax2Id = null): bool
    {
        foreach ([$tax1Id, $tax2Id] as $id) {
            $tax = static::resolve($id);

            if ($tax && static::fraction($tax->tax) > 0 && $tax->type === self::INCLUDED) {
                return true;
            }
        }

        return false;
    }

    /**
     * المبلغُ كما أدخله المستخدم أوّلاً — لإعادة فتح المستند للتحرير.
     *
     * السطرُ المحفوظ يحمل **الوعاء** بعد نزع الضريبة الشاملة منه. وخانةُ
     * التحرير تعني ما أُدخل، فعرضُ الوعاء فيها يجعل الحفظَ يستخرج الضريبة
     * مرّةً ثانية — فينكمش المبلغ في كلّ تعديل، أو يُردّ القيدُ «غير
     * متوازن» لأنّ الجانب المقابل بقي على إجماليه.
     *
     * فالشاملُ يُفتح بإجماليه، والمضافُ بوعائه.
     */
    public static function grossOf($amount, $taxId): float
    {
        $amount = (float) $amount;
        $tax = static::resolve($taxId);

        if (! $tax || $tax->type !== self::INCLUDED) {
            return round($amount, 2);
        }

        return round($amount * (1 + static::fraction($tax->tax)), 2);
    }

    /**
     * النسبةُ ككسر — تقبل `0.15` و`15` معاً.
     *
     * وحدُّ الفصل عند 1: لا ضريبةَ نسبتُها 100% في الاستعمال العملي، وما
     * دون 1 كسرٌ قطعاً.
     */
    public static function fraction($rate): float
    {
        $value = (float) $rate;

        if ($value <= 0) {
            return 0.0;
        }

        return $value < 1 ? $value : $value / 100;
    }

    /** سجلُّ الضريبة من معرّفه — مع ذاكرةٍ للطلب الواحد. */
    public static function resolve($taxId): ?TaxSitting
    {
        static $cache = [];

        if (empty($taxId)) {
            return null;
        }

        $key = (int) $taxId;

        if (! array_key_exists($key, $cache)) {
            $cache[$key] = TaxSitting::find($key);
        }

        return $cache[$key];
    }

    /**
     * الطريقةُ نصّاً للعرض — «شاملة» أو «مضافة».
     *
     * الفرقُ يمسّ المبلغَ، فحقُّه أن يُقرأ في السند لا أن يُستنتَج.
     */
    public static function typeLabel(?string $type): string
    {
        return $type === self::INCLUDED ? 'شاملة الضريبة' : 'الضريبة مضافة';
    }
}
