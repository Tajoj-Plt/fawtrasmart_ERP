<?php

namespace Modules\Purchases\Support;

use App\Models\InvoiceItem;
use App\Services\Purchases\PurchaseTaxCalculator;

/**
 * حساب بند طلب الشراء — مصدر واحد للمتحكّم والشاشات وقوالب الطباعة.
 *
 * **لماذا النسبة لا المبلغ في `tax_1`؟**
 *
 * العمود `invoice_items.tax_1` من نوع `decimal(5,2)`، أي أن أقصى ما يسعه
 * 999.99 — فهو مصمَّم لنسبة مئوية لا لمبلغ. تخزين مبلغ الضريبة فيه يفيض
 * صامتاً عند أول بند تتجاوز ضريبته ألفاً، فتُحفظ قيمة مبتورة.
 *
 * **ولماذا `tax_1_id` إلى جانبها؟**
 *
 * النسبة وحدها لا تكفي: الضريبة في «إعدادات الضريبة» إمّا `excluded`
 * فتُضاف فوق السعر، أو `included` فتُستخرَج منه ولا تزيد الإجمالي. النوع
 * يُقرأ من الإعداد عبر معرّفه، وحساب المبلغ كله في
 * `App\Services\Purchases\PurchaseTaxCalculator` فلا يتكرّر هنا.
 *
 * البنود القديمة بلا معرّف تبقى `excluded` — كما حُسبت يوم حُفظت.
 *
 *     OrderLine::net($item)    // الكمية × السعر − الخصم − الضريبة المتضمَّنة
 *     OrderLine::tax($item)    // مبلغ الضريبة أياً كان نوعها
 *     OrderLine::total($item)  // ما يُدفع فعلاً عن البند
 */
class OrderLine
{
    /**
     * خصمُ بندٍ محفوظ بالصيغة التي يُعاد بها إلى النموذج — **نسبةً** من سطره.
     *
     * ══════════ لماذا نسبة ══════════
     *
     * عمودُ خصم البند أُزيل من نماذج المشتريات بطلب المستخدم (الخصمُ من
     * تبويب «الخصم والتسوية» وحده)، والخصمُ المحفوظ يُحمَل مخفيّاً. ولو
     * حُمل مبلغاً لحُسم كاملاً عن أيّ كميةٍ تُكتب: مرتجعُ قطعةٍ من ثلاث
     * أو إشعارٌ مدينٌ بجزءٍ من البند يحسم عنه خصمَ السطر كلِّه — وكان
     * المستخدمُ يصحّحه بيده في العمود الذي لم يعد موجوداً. والنسبةُ
     * تتناسب مع الكمية بذاتها، وتُعطي المبلغَ نفسَه عند الكمية نفسها.
     *
     * ══════════ والخللُ الذي تسدّه معها ══════════
     *
     * كلُّ متحكّمات المشتريات تحفظ في `discount` **المبلغَ المحسوب**، وتحفظ
     * `discount_type = 2` متى أُدخل نسبةً. وكانت شاشاتُ التعديل والنسخ
     * تُعيده `'percentage'` بالمبلغ نفسِه: خصمُ ١٠٪ على ألفٍ يُحفظ مئةً،
     * ويعود عند فتح التعديل **مئةً بالمئة**.
     *
     * @param  object  $item  بندٌ محفوظ (`quantity`، `unit_price`، `discount` مبلغاً)
     * @return array{discount: float, discount_type: string}
     */
    public static function discountFields($item): array
    {
        $gross = (float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0);
        $amount = (float) ($item->discount ?? 0);

        if ($gross <= 0 || $amount <= 0) {
            return ['discount' => 0.0, 'discount_type' => 'amount'];
        }

        return [
            'discount' => round(min(100, $amount / $gross * 100), 6),
            'discount_type' => 'percentage',
        ];
    }

    /** إجمالي البند قبل الخصم */
    public static function gross(InvoiceItem $item): float
    {
        return (float) $item->unit_price * (float) $item->quantity;
    }

    /** الوعاء الخاضع للضريبة: الإجمالي بعد الخصم — لا ينزل تحت الصفر */
    public static function base(InvoiceItem $item): float
    {
        return max(0.0, self::gross($item) - (float) $item->discount);
    }

    /**
     * الصافي بلا ضريبة.
     *
     * حين تكون الضريبة متضمَّنة يكون الصافي أقلّ من الوعاء بمقدارها،
     * وحين تكون مضافة يتساويان.
     */
    public static function net(InvoiceItem $item): float
    {
        return round(self::base($item) - self::includedTax($item), 2);
    }

    /** مبلغ الضريبة — بالنوع المضبوط لها لا بالإضافة دائماً */
    public static function tax(InvoiceItem $item): float
    {
        return self::compute($item)['tax'];
    }

    /** الجزء المتضمَّن داخل السعر من مبلغ الضريبة */
    public static function includedTax(InvoiceItem $item): float
    {
        $line = self::compute($item);

        return round($line['base'] - $line['net'], 2);
    }

    /** هل ضريبة البند متضمَّنة في السعر؟ */
    public static function isTaxIncluded(InvoiceItem $item): bool
    {
        return PurchaseTaxCalculator::isIncluded($item->tax_1_id)
            || PurchaseTaxCalculator::isIncluded($item->tax_2_id);
    }

    /** النسبة المئوية كما حُفظت */
    public static function taxRate(InvoiceItem $item): float
    {
        return (float) $item->tax_1;
    }

    public static function total(InvoiceItem $item): float
    {
        return self::compute($item)['total'];
    }

    /**
     * الحساب الكامل للبند — تفويض إلى الآلة الحاسبة المشتركة.
     *
     * @return array{gross: float, discount: float, base: float, net: float, tax: float, total: float, taxes: array}
     */
    public static function compute(InvoiceItem $item): array
    {
        return PurchaseTaxCalculator::line(
            (float) $item->quantity,
            (float) $item->unit_price,
            (float) $item->discount,
            [$item->tax_1_id, $item->tax_2_id],
            [(float) $item->tax_1, (float) $item->tax_2],
        );
    }

    /**
     * إجماليات مجموعة بنود.
     *
     * `subtotal` هنا هو مجموع البنود قبل الخصم وقبل استخراج الضريبة
     * المتضمَّنة، و`grand_total` ما يُدفع فعلاً — فلا يزيد بالضريبة
     * المتضمَّنة لأنها أصلاً داخل السعر.
     *
     * @param  iterable<InvoiceItem>  $items
     * @return array{subtotal: float, discount: float, tax: float, grand_total: float}
     */
    public static function totals(iterable $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = self::compute($item);
        }

        return PurchaseTaxCalculator::totals($lines);
    }
}
