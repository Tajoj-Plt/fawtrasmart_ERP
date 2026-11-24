<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Models\TaxSitting;
use App\Support\TaxCalculator;

/**
 * ضريبة نقاط البيع — من جدول الضرائب، بطريقتها المضبوطة.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 *     $taxRateDecimal = $taxRate / 100;
 *     $taxAmountPerUnit = $priceExcludingTax * $taxRateDecimal;
 *     $priceIncludingTax = $priceExcludingTax + $taxAmountPerUnit;
 *
 * الصيغةُ نفسها مكتوبةً بيدها في **سبعة مواضع**: في `store()` مرّتين، وفي
 * بناء البنود، وفي سجلّ الضرائب، وفي عرض تفاصيل الفاتورة، وفي تفاصيل
 * المرتجع، وفي معالجته. وفيها عيبان:
 *
 * • *طريقةٌ واحدة مفترضة*: الحسابُ كلُّه يفترض `excluded` — الضريبةَ فوق
 *   السعر. وعمودُ `tax_sittings.type` يعرّف `included` أيضاً، **ولا يقرؤه
 *   شيءٌ هنا**. فمن ضبط ضريبةً شاملة في الإعدادات حُسبت له مضافةً: تُضاف
 *   ١٥٪ فوق سعرٍ يحملها أصلاً، فيدفع العميلُ الضريبةَ مرّتين ويخرج الإقرارُ
 *   بأكثر ممّا حُصِّل.
 *
 * • *احتياطٌ مخمَّن*: `?: 0.15` مكتوبةً في أربعة مواضع كنسبةٍ افتراضية حين
 *   لا تُعرف — رقمٌ صحيحٌ في السعودية اليوم وخطأٌ في أي بلدٍ آخر وفي أي
 *   تغييرٍ تشريعي. والنسبةُ تُقرأ من الجدول أو لا تُحسب ضريبة.
 *
 * ─────────────────────── ما صار ───────────────────────
 *
 * مصدرٌ واحد: `App\Support\TaxCalculator` — نفسُه الذي تستعمله السندات
 * والتقارير. يقرأ النسبةَ والطريقةَ من سجلّ الضريبة، ويحسب الوعاءَ
 * والضريبةَ والإجمالي معاً.
 *
 * ─────────────────────── ونسبةُ المرتجع من الفاتورة الأصلية ───────────────────────
 *
 * المرتجعُ لا يُعيد حسابَ الضريبة بنسبةِ اليوم: يُشتقّها من الفاتورة التي
 * يرجع إليها (`tax_total / subtotal`). فتغييرُ النسبة تشريعياً لا يجعل
 * مرتجعَ فاتورةٍ قديمة يردّ ضريبةً غير التي حُصِّلت — وهو ما كان يحدث لولا
 * أن الشيفرة اشتقّتها من الفاتورة صدفةً في موضعين وأخطأت في ثالث.
 */
trait CalculatesPosTax
{
    /**
     * سجلّ الضريبة المعتمد للفاتورة.
     *
     * الترتيب: ما اختاره الكاشير ← أول ضريبةٍ فعّالة في الجدول ← لا شيء.
     *
     * @param  int|string|null  $taxId  ما أرسلته الشاشة
     */
    protected function resolveTax($taxId = null): ?TaxSitting
    {
        if ($taxId) {
            $tax = TaxSitting::find($taxId);

            if ($tax) {
                return $tax;
            }
        }

        try {
            return TaxSitting::where('tax', '>', 0)->orderBy('id')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** نسبةُ الضريبة مئويةً — صفرٌ إن لم تُضبَط ضريبةٌ أصلاً، لا 15 مخمَّنة. */
    protected function taxRateOf(?TaxSitting $tax): float
    {
        return (float) ($tax?->tax ?? 0);
    }

    /** طريقةُ الضريبة — مضافة أم شاملة. */
    protected function taxTypeOf(?TaxSitting $tax): string
    {
        return $tax?->type ?: TaxCalculator::EXCLUDED;
    }

    /**
     * تفصيلُ سطرٍ واحد: الوعاء والضريبة والإجمالي.
     *
     * يُستدعى مرّةً لكل بند، ويُبنى منه البندُ وسجلُّ الضريبة والقيدُ معاً —
     * فلا تُحسب الضريبةُ ثلاث مرّاتٍ بثلاث صيغٍ قد تختلف في التقريب.
     *
     * @param  float  $unitPrice  السعر كما أدخله الكاشير
     * @param  float  $quantity  الكمية بالوحدة المختارة
     * @return array{base: float, tax: float, total: float, unit_base: float, unit_tax: float, unit_total: float}
     */
    protected function taxLine(float $unitPrice, float $quantity, ?TaxSitting $tax): array
    {
        $unit = TaxCalculator::compute($unitPrice, $this->taxRateOf($tax), $this->taxTypeOf($tax));

        $line = TaxCalculator::compute($unitPrice * $quantity, $this->taxRateOf($tax), $this->taxTypeOf($tax));

        return [
            'base' => $line['base'],
            'tax' => $line['tax'],
            'total' => $line['total'],
            'unit_base' => $unit['base'],
            'unit_tax' => $unit['tax'],
            'unit_total' => $unit['total'],
        ];
    }

    /**
     * تفصيلُ سلّةٍ كاملة — الوعاء والضريبة والإجمالي قبل الخصم.
     *
     * الجمعُ على مستوى السطر لا على مستوى الإجمالي: جمعُ الأسعار ثم حسابُ
     * الضريبة مرّةً واحدة يُنتج فرقَ قروشٍ عن مجموع ضرائب السطور، والإيصالُ
     * يعرض السطورَ فيراه العميلُ لا يجمع.
     *
     * @param  array<int, array{unit_price: mixed, quantity: mixed}>  $lines
     * @return array{base: float, tax: float, total: float}
     */
    protected function taxTotals(array $lines, ?TaxSitting $tax): array
    {
        $base = 0.0;
        $taxAmount = 0.0;

        foreach ($lines as $line) {
            $computed = $this->taxLine(
                (float) ($line['unit_price'] ?? 0),
                (float) ($line['quantity'] ?? 0),
                $tax
            );

            $base += $computed['base'];
            $taxAmount += $computed['tax'];
        }

        return [
            'base' => round($base, 2),
            'tax' => round($taxAmount, 2),
            'total' => round($base + $taxAmount, 2),
        ];
    }

    /**
     * نسبةُ ضريبة فاتورةٍ محفوظة — مشتقّةً منها هي.
     *
     * تُستعمل في المرتجع وفي عرض التفاصيل: الفاتورةُ تحمل وعاءها وضريبتها،
     * فالنسبةُ خارجُ قسمتهما. وصفرٌ حين لا وعاء — لا `0.15` مخمَّنة.
     *
     * @return float كسرٌ لا نسبةٌ مئوية (0.15 لا 15)
     */
    protected function invoiceTaxFraction($invoice): float
    {
        $subtotal = (float) ($invoice->subtotal ?? 0);
        $taxTotal = (float) ($invoice->tax_total ?? 0);

        if ($subtotal <= 0) {
            return 0.0;
        }

        return $taxTotal / $subtotal;
    }

    /** الضرائب المتاحة للاختيار في الشاشة. */
    protected function availableTaxes()
    {
        try {
            return TaxSitting::orderBy('tax')->get(['id', 'name', 'tax', 'type']);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
