<?php

namespace Modules\Client\Reservations\Services;

use App\Support\TaxCalculator;
use Modules\Client\Reservations\Models\ReservationService;
use Modules\Client\Reservations\Models\ReservationSetting;

/**
 * تسعيرُ الحجز — موضعٌ واحد يحسب، والشاشةُ تعرض ما حسبه.
 *
 * ═══════ لماذا لا تُحسب في المتحكّم ولا في الجافاسكربت؟ ═══════
 *
 * لأنّها تُحسب في أربعة مواضع: نموذجُ الإنشاء يعرض الإجمالي حيّاً، والحفظُ
 * يخزّنه، والفاتورةُ المُنشأة من الحجز تعيد بناءَه، وتقريرُ الإيرادات
 * يجمعه. وأربعةُ حسّاباتٍ تفترق بعد أوّل تعديلٍ على قاعدة الخصم.
 *
 * والجافاسكربت خصوصاً لا يُؤتمَن على الرقم المحفوظ: من عدّل الطلبَ قبل
 * إرساله خزّن ما شاء. فالجافاسكربت **يعرض تقديراً**، وهذا الصنف يقرّر.
 *
 * ═══════ ترتيب العمليات ═══════
 *
 *     الأساس   = سعر الوحدة
 *     الخصم    = نسبةً من الأساس أو مبلغاً مقطوعاً
 *     الوعاء   = الأساس − الخصم
 *     الضريبة  = من الوعاء، بطريقة الضريبة (مضافة/شاملة)
 *     الإجمالي = بحسب الطريقة
 *
 * **الضريبةُ بعد الخصم لا قبله.** حسابُها على الأساس ثمّ خصمُ الخصم منها
 * يُنتج ضريبةً على مبلغٍ لم يُحصَّل — وهو إقرارٌ ضريبيّ أعلى من الواقع.
 *
 * والطريقةُ («مضافة» أو «شاملة») لا تُخمَّن هنا: تُقرأ من سجلّ الضريبة
 * عبر `TaxCalculator::forTax()` الذي يحترم عمود `type` — وهو العمود الذي
 * كان معرَّفاً ولا يقرؤه شيء قبل توحيد الحساب.
 */
class ReservationPricingService
{
    /**
     * تفصيلُ مبالغ حجز.
     *
     * @param  string  $discountType  `percentage` أو `amount` — مفردةُ النظام نفسُها
     * @return array{
     *     unit_price: float, subtotal: float, discount_amount: float,
     *     tax_amount: float, total: float
     * }
     */
    public function quote(
        float $unitPrice,
        float $discountValue = 0,
        string $discountType = 'amount',
        $taxId = null
    ): array {
        $unitPrice = round(max(0, $unitPrice), 2);

        $discount = $this->discountAmount($unitPrice, $discountValue, $discountType);

        $taxable = round($unitPrice - $discount, 2);

        $breakdown = TaxCalculator::forTax($taxable, $taxId);

        return [
            'unit_price' => $unitPrice,
            /*
                `subtotal` هو الوعاءُ الخاضع للضريبة — أي بعد الخصم.
                وتسميتُه بالأساس قبل الخصم كانت ستجعل
                `subtotal + tax = total` غيرَ صحيحةٍ في كل حجزٍ بخصم.
            */
            'subtotal' => $breakdown['base'],
            'discount_amount' => $discount,
            'tax_amount' => $breakdown['tax'],
            'total' => $breakdown['total'],
        ];
    }

    /**
     * تفصيلُ مبالغ حجزٍ انطلاقاً من خدمة — السعرُ والضريبةُ منها.
     *
     * @param  bool  $taxChosen  هل **اختار** المستخدمُ الضريبةَ صراحةً — ولو اختار «بلا ضريبة»؟
     * @return array{unit_price: float, subtotal: float, discount_amount: float, tax_amount: float, total: float, tax_id: int|null}
     */
    public function quoteForService(
        ?ReservationService $service,
        ?float $unitPriceOverride = null,
        float $discountValue = 0,
        string $discountType = 'amount',
        $taxIdOverride = null,
        bool $taxChosen = false
    ): array {
        $settings = ReservationSetting::current();

        $price = $unitPriceOverride !== null
            ? $unitPriceOverride
            : (float) ($service?->effectivePrice() ?? 0);

        /*
            ترتيبُ الضريبة: ما اختاره المستخدم في النموذج، وإلّا ضريبةُ
            الخدمة (أو منتجها)، وإلّا الضريبةُ الافتراضية من الإعدادات.

            **والاختيارُ الصريح يُحترَم ولو كان فارغاً.** كان `?:` يقرأ
            «بلا ضريبة» كأنّه «لم يختر» فيعود إلى ضريبة الخدمة — ولا سبيلَ
            من النموذج إلى حجزٍ بلا ضريبة. `$taxChosen` تفرّق بين «أرسل
            فارغاً» و«لم يُرسل».

            ولا نسبةَ مكتوبةً في الشيفرة في أيّ من الثلاثة — «15%» لا تظهر
            في هذا الملفّ ولا في غيره.
        */
        $taxId = ($taxChosen || $taxIdOverride)
            ? $taxIdOverride
            : ($service?->effectiveTaxId() ?: $settings->default_tax_id);

        return $this->quote($price, $discountValue, $discountType, $taxId ?: null) + ['tax_id' => $taxId ? (int) $taxId : null];
    }

    /**
     * الخصمُ محسوباً بالمبلغ دائماً.
     *
     * ويُقصّ عند الأساس: خصمٌ يتجاوز السعر يُنتج وعاءً سالباً فضريبةً سالبة
     * فإجماليَّ حجزٍ يدين به المنشأةُ للعميل.
     */
    public function discountAmount(float $base, float $value, string $type): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        $amount = $type === 'percentage'
            ? $base * (min($value, 100) / 100)
            : $value;

        return round(min($amount, $base), 2);
    }

    /**
     * الدفعةُ المقدَّمة المطلوبة لإجماليٍّ ما — حسب إعدادات العربون.
     *
     * صفرٌ حين لا يُطلب عربون.
     */
    public function requiredDeposit(float $total): float
    {
        $settings = ReservationSetting::current();

        if (! $settings->require_deposit || $total <= 0) {
            return 0.0;
        }

        $value = (float) $settings->deposit_value;

        $amount = $settings->deposit_type === 'percentage'
            ? $total * (min($value, 100) / 100)
            : $value;

        return round(min($amount, $total), 2);
    }

    /**
     * رسمُ الإلغاء لحجزٍ بإجماليٍّ ما — حسب إعدادات الإلغاء.
     */
    public function cancellationFee(float $total): float
    {
        $settings = ReservationSetting::current();

        $type = (string) $settings->cancellation_fee_type;

        if ($type === 'none' || $total <= 0) {
            return 0.0;
        }

        $value = (float) $settings->cancellation_fee_value;

        $amount = $type === 'percentage'
            ? $total * (min($value, 100) / 100)
            : $value;

        return round(min($amount, $total), 2);
    }
}
