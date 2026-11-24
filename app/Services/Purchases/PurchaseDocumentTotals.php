<?php

namespace App\Services\Purchases;

use App\Services\Tax\DocumentTaxCalculator;
use Illuminate\Http\Request;

/**
 * إجماليات مستند الشراء — الخصم العامّ والضريبة والشحن في موضعٍ واحد.
 *
 * كانت الحلقةُ نفسُها منسوخةً في ثمانية مواضع: فاتورةُ الشراء (إنشاءً
 * وتعديلاً)، ومرتجعُه، وإشعارُه المدين، وأمرُ الشراء — وكلُّها تحسب
 * الخصمَ والضريبةَ والشحنَ بقواعدَ متفرّقة، فالمستندُ الواحد يخرج بإجمالٍ
 * ويخرج مرتجعُه بإجمالٍ آخر لنفس الأرقام. والتفصيلُ في {@see compute()}.
 *
 * @see \App\Services\Tax\DocumentTaxCalculator الحسّابُ المشترك مع المبيعات
 */
class PurchaseDocumentTotals
{
    /**
     * ══════════ ترتيبُ الحساب — واحدٌ للبيع والشراء ══════════
     *
     *     إجمالي الأصناف − خصومات الأصناف  ⇒ صافي الأصناف
     *     − الخصم العامّ                    ⇒ صافي التوريد
     *     + الشحن (إضافةٌ خاضعة)            ⇒ الوعاء الضريبي
     *     × النسبة                          ⇒ الضريبة
     *
     * ── الثلاثةُ التي كانت تفترق فيها المشتريات عن المبيعات ──
     *
     * **أولاً: نسبةُ الخصم العامّ على الإجمالي لا على الصافي.** كانت
     * `($total_amount * النسبة) / 100` — و`$total_amount` إجماليُّ الأصناف
     * **قبل** خصومها. فخصمُ ١٠٪ على فاتورةٍ بألفٍ وخصمِ بندٍ بمئة يخرج مئةً
     * لا تسعين: أكبرَ ممّا تعرضه الشاشة. و`update()` كان أسوأ: يحسبها قبل
     * طرح الضريبة المتضمَّنة، فتخرج النسبةُ على مبلغٍ يحمل ضريبته.
     *
     * **ثانياً: الخصمُ العامّ لا يُنقص الوعاء الضريبي.** كانت حلقةُ البنود
     * تحسب الضريبة قبل أن يُعرَف الخصمُ العامّ أصلاً، فخصمُ البند يُنقص
     * الوعاء والخصمُ العامّ لا يُنقصه — والخصمُ الواحد يُعطي فاتورتين
     * مختلفتين بحسب أين كُتب. يُوزَّع هنا على البنود بنسبة صافي كلٍّ منها
     * ثمّ تُحسب ضريبةُ كلّ بندٍ على ما بقي له، فالبندُ المعفى يأخذ حصّته
     * ولا يُنتج ضريبة.
     *
     * **ثالثاً: ضريبةُ الشحن كانت صفراً في كلّ حفظ.** كانت مشروطةً بـ
     * `$request->tax_type == 1`، و**نموذجُ المشتريات لا يرسل هذا الحقل
     * إطلاقاً** — فالشرط كاذبٌ دائماً. يختار المستخدم ضريبةً للشحن في
     * تبويبة الشحن (`tax_id`) فتُهمَل، ويُحفَظ الشحنُ بلا ضريبته بينما
     * تضمّها الشاشةُ إلى الإجمالي المعروض. وهي نفسُ العلّة التي أُسقِط
     * شرطُها من مستندات البيع.
     *
     * @param  array<int|string, float>  $lineNets  صافي كلّ بندٍ بعد خصمه الخاصّ
     * @param  array<int|string, array{ids: array<int, mixed>, rates: array<int, float>}>  $lineTaxes
     * @param  array<int, mixed>  $documentTaxIds  ضرائب البنود — تُرَشَّح لضريبة الشحن
     * @return array{
     *     net_items: float, invoice_discount: float, item_tax: float,
     *     included_item_tax: float, tax_rows: array<int, array<string, mixed>>,
     *     shipping_cost: float, shipping_tax: float,
     *     line_costs: array<int|string, float>
     * }
     */
    public static function compute(
        Request $request,
        array $lineNets,
        array $lineTaxes,
        bool $settlementEnabled,
        array $documentTaxIds
    ): array {
        $net_items = round(array_sum($lineNets), 2);

        /* «تبويب التسوية» هو ما يحمل الخصم العامّ والشحن، وإطفاء الإعداد
           يُخفيه من النموذج — فالخادم يتجاهل الحقلين أيضاً ولا يكتفي
           بغيابهما من الطلب: طلبٌ قديم أو مُلفَّق يظلّ يحملهما. */
        $invoice_discount = 0.0;

        if ($settlementEnabled && $request->has('discount_amount') && $request->discount_amount > 0) {
            $invoice_discount = $request->input('discount_type') === 'percentage'
                ? ($net_items * floatval($request->discount_amount)) / 100
                : floatval($request->discount_amount);

            /* لا يتجاوز الخصمُ ما يُخصَم منه — كما تفعل الشاشة. كان الخادم
               يأخذه كما أُدخل: خصمُ ثمانين على فاتورةٍ بخمسين يُحفَظ
               بإجمالٍ سالب، وعليه يُبنى القيد. */
            $invoice_discount = min($invoice_discount, max(0.0, $net_items));
        }

        $shares = DocumentTaxCalculator::allocateDocumentDiscount($lineNets, $invoice_discount);

        $item_tax = 0.0;
        $included_item_tax = 0.0;
        $tax_rows = [];
        $line_costs = [];

        foreach ($lineNets as $key => $net) {
            $taxableBase = max(0.0, (float) $net - ($shares[$key] ?? 0.0));

            $line = DocumentTaxCalculator::line(
                1.0,
                $taxableBase,
                0.0,
                $lineTaxes[$key]['ids'] ?? [],
                $lineTaxes[$key]['rates'] ?? [],
            );

            $item_tax += $line['tax'];
            // الجزء المتضمَّن داخل الأسعار — يُطرح من المجموع فلا يُحسب مرّتين
            $included_item_tax += $line['base'] - $line['net'];

            foreach ($line['taxes'] as $detail) {
                $tax_rows[] = $detail;
            }

            /* ── تكلفةُ البند الداخلةُ المخزون ──
             *
             * `$line['net']` هو الوعاءُ بعد الخصمين ناقصاً الضريبةَ
             * المتضمَّنة — أي **صافي** ما دفعناه في البضاعة نفسها.
             *
             * وكان الإذنُ المخزنيّ يأخذ `invoice_items.total` مكانَه، وهو
             * البندُ **بضريبته المضافة وقبل الخصم العامّ**. والإذنُ هو
             * وعاءُ التكلفة الذي يقرؤه `Product::averageCost()`، ومنه
             * تُقيَّد تكلفةُ المبيعات — فضريبةُ المدخلات، وهي مستردّةٌ
             * ومقيَّدةٌ في «ضريبة المشتريات» أصلاً، كانت تُرسمَل في
             * المخزون معها: بندٌ بألفٍ وخصمِ مئةٍ وضريبةِ ١٥٪ يدخل
             * المخزون بـ١٠٣٫٥٠ للوحدة وتكلفتُه الحقيقية ٨١٫٠٠.
             */
            $line_costs[$key] = $line['net'];
        }

        $shipping_cost = $settlementEnabled ? floatval($request->shipping_cost ?? 0) : 0.0;

        /* ضريبةُ الشحن بالنسبة المضبوطة: المختارةُ في تبويبة الشحن أوّلاً
           (`tax_id`)، وإلّا ضرائبُ البنود. و`filled()` لا `has()`: خيارُ
           «اختر الضريبة» الفارغ يعني «كما البنود» في هذه الشاشة لا «بلا
           ضريبة» — لا نصَّ فيها يعرض الثاني. */
        $shipping_tax = DocumentTaxCalculator::shippingTax(
            $shipping_cost,
            DocumentTaxCalculator::shippingTaxIds(
                $request->filled('tax_id'),
                $request->input('tax_id'),
                $documentTaxIds
            ),
        );

        return [
            'net_items' => $net_items,
            'invoice_discount' => round($invoice_discount, 2),
            'item_tax' => round($item_tax, 2),
            'included_item_tax' => round($included_item_tax, 2),
            'tax_rows' => $tax_rows,
            'shipping_cost' => $shipping_cost,
            'shipping_tax' => $shipping_tax,
            'line_costs' => $line_costs,
        ];
    }
}
