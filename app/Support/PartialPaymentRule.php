<?php

namespace App\Support;

use App\Models\Invoice;

/**
 * «منع السداد الجزئي» على الفاتورة.
 *
 * الخيار يُضبط على الاشتراك الدوري ويُنسخ إلى كل فاتورةٍ يُصدرها
 * (`invoices.disable_partial_payment`). معناه: الفاتورة تُسدَّد **كاملةً
 * دفعةً واحدة** — ما بقي عليها يُدفع كلُّه أو لا يُدفع منه شيء.
 *
 * وكان يُحفظ ولا يقرؤه أحد. يقرؤه الآن كلُّ بابٍ يُسجِّل تحصيلاً على فاتورة:
 *
 *   • دفعة الفاتورة (`SalesPaymentController::store/update`) — تُردّ برسالة.
 *   • الدفع المجمَّع لعدّة فواتير — الفاتورة الممنوعة تُتخطّى إن لم يكفِ
 *     المبلغ لسدادها كاملة.
 *   • سند القبض (`IncomesController::applyPaymentToInvoices`)، والشيك المستلم،
 *     وتوزيع القيد اليومي على الفواتير — مثلُه: لا يُوزَّع على الفاتورة
 *     جزءٌ منها، ويبقى المبلغ رصيداً دائناً للعميل.
 */
class PartialPaymentRule
{
    /** هامش القرش — المبالغ تُقرَّب لخانتين */
    private const TOLERANCE = 0.01;

    /** فاتورة البيع وحدها تحمل الخيار — مسارات التوزيع المشتركة تمرّر فواتير الشراء أيضاً */
    public static function applies($invoice): bool
    {
        return $invoice instanceof Invoice && (bool) ($invoice->disable_partial_payment ?? false);
    }

    /**
     * أيقع المبلغ جزءاً من المتبقّي على فاتورةٍ تمنع التجزئة؟
     *
     * @param  float  $amount  ما يُراد تسجيله على الفاتورة
     * @param  float  $remaining  ما بقي عليها قبل هذا المبلغ
     */
    public static function blocks($invoice, float $amount, float $remaining): bool
    {
        return self::applies($invoice)
            && $remaining > self::TOLERANCE
            && round($amount, 2) < round($remaining, 2) - self::TOLERANCE;
    }

    /** رسالة الردّ — بالمبلغ الواجب سدادُه */
    public static function message(Invoice $invoice, float $remaining): string
    {
        return __('sales_periodic.partial_payment.blocked', [
            'code' => $invoice->code,
            'amount' => CurrencySettings::format(round($remaining, 2)),
        ]);
    }
}
