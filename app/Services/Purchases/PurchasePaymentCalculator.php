<?php

namespace App\Services\Purchases;

use App\Models\PurchaseInvoice;

/**
 * حساب المدفوع والمتبقّي على مستند شراء.
 *
 * **ثلاث علل في `PurchaseInvoice::calculateRemainingAmount()` القديمة:**
 *
 * 1. **تُطبَّق على كل الأنواع.** أمر الشراء ليس مديونية على المورّد ولا
 *    يُدفع، ومع ذلك كانت تحسب له متبقّياً وتضع عليه `payment_status`.
 *
 * 2. **تجمع الملغى مع المسدَّد.** `payments()->sum('amount')` يجمع كل صفوف
 *    `payments_process` بلا نظر إلى `payment_status` — والقيمة `5` موصوفة
 *    في `PurchasePaymentController` نفسه بأنها «حالة ملغية». فدفعةٌ أُلغيت
 *    تبقى محسوبة كأنها سُدِّدت.
 *
 * 3. **ازدواج محتمل.** كانت تجمع مبالغ `payments_process` مع تخصيصات
 *    `journal_entry_invoices`. اليوم لا خطر فعلي: الجدول لا يحوي إلا صفوف
 *    `invoice_type='sale'` (٤٨٠٧ صفاً، وصفر للشراء) — يكتبها جانب المبيعات
 *    وحده. لكن أول تخصيص شراء يُكتب سيجعل الدفعة الواحدة محسوبةً مرّتين.
 *    لذلك تُجمَع التخصيصات هنا بعد **استبعاد ما يقابله صفُّ دفع مسجَّل**.
 */
class PurchasePaymentCalculator
{
    /**
     * حالات الدفع في `payments_process` التي لا تُحتسب سداداً.
     *
     * `5` = ملغية (كما توثّقها `PurchasePaymentController`).
     * `3` = مسودة.
     */
    public const VOID_PAYMENT_STATUSES = [3, 5];

    /**
     * مجموع ما سُدِّد فعلاً على المستند.
     */
    public static function paidAmount(PurchaseInvoice $document): float
    {
        if (! $document->isPayable()) {
            return 0.0;
        }

        $fromPayments = (float) $document->payments()
            ->whereNotIn('payment_status', self::VOID_PAYMENT_STATUSES)
            ->sum('amount');

        /* ما سُوِّي من محفظة المورّد سدادٌ فعليّ بلا سطر في `payments_process`:
           يُكتب على الفاتورة نفسها (`wallet_paid_amount`) لا كسندِ صرف. وكانت
           الحاسبة تتجاهله، فتقرأ فاتورةٌ سُدِّدت كلُّها من المحفظة «غير مدفوعة»
           بمتبقٍّ كاملٍ في التقارير والشاشات وهي «مدفوعة» في عمودها. */
        $fromWallet = (float) ($document->wallet_paid_amount ?? 0);

        return round($fromPayments + $fromWallet + self::unmatchedAllocations($document), 2);
    }

    /**
     * تخصيصات القيود التي لا يقابلها صفُّ دفع — فلا تُحتسب مرّتين.
     *
     * التخصيص المحاسبي قد يمثّل الدفعة نفسها المسجَّلة في
     * `payments_process`. حين يتساوى المبلغان يُعدّان عمليةً واحدة،
     * ويُحتسب صفُّ الدفع وحده.
     */
    protected static function unmatchedAllocations(PurchaseInvoice $document): float
    {
        $allocations = $document->journalEntries()
            ->pluck('journal_entry_invoices.allocation_amount')
            ->map(fn ($value) => round((float) $value, 2))
            ->filter(fn ($value) => $value > 0)
            ->values();

        if ($allocations->isEmpty()) {
            return 0.0;
        }

        $payments = $document->payments()
            ->whereNotIn('payment_status', self::VOID_PAYMENT_STATUSES)
            ->pluck('amount')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();

        $total = 0.0;

        foreach ($allocations as $allocation) {
            $match = array_search($allocation, $payments, true);

            if ($match !== false) {
                // نُظيره مسجَّل دفعةً — يُستهلك فلا يُطابَق مرّتين
                unset($payments[$match]);

                continue;
            }

            $total += $allocation;
        }

        return $total;
    }

    /**
     * المتبقّي على المستند — صفرٌ لما لا يُدفع، ولا ينزل تحت الصفر.
     */
    public static function remainingAmount(PurchaseInvoice $document): float
    {
        if (! $document->isPayable()) {
            return 0.0;
        }

        $total = round((float) $document->grand_total, 2);

        return round(max(0, $total - self::paidAmount($document)), 2);
    }

    /**
     * حالة الدفع المشتقّة: unpaid | partially_paid | paid.
     */
    public static function paymentStatus(PurchaseInvoice $document): ?string
    {
        if (! $document->isPayable()) {
            return null;
        }

        $total = round((float) $document->grand_total, 2);
        $remaining = self::remainingAmount($document);

        if ($remaining <= 0) {
            return 'paid';
        }

        // المتبقّي مساوٍ للإجمالي (أو أكبر، لو كان الإجمالي صفراً) = لم يُدفع شيء
        return $remaining >= $total ? 'unpaid' : 'partially_paid';
    }
}
