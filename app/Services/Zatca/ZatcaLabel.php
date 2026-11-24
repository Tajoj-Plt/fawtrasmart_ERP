<?php

namespace App\Services\Zatca;

use App\Services\Tax\DocumentTaxCalculator;
use Carbon\Carbon;
use Throwable;

/**
 * ما تشترطه الهيئة مكتوباً على الفاتورة المطبوعة: عنوانها ووقتها وضريبة بندها.
 *
 * **العنوان** ليس اختياراً: فاتورةٌ لمنشأةٍ برقم ضريبي «فاتورة ضريبية»، ولفردٍ
 * بلا رقم «فاتورة ضريبية مبسّطة» — وكانت المطبوعات تكتب عنواناً واحداً ثابتاً
 * أيّاً كان المشتري، وبعضها «فاتورة» بلا وصف.
 *
 * **الوقت**: الهيئة تشترط تاريخ الإصدار ووقته. و`invoices.invoice_date`
 * عمودُ تاريخٍ بلا وقت، فما طُبع منه خرج `00:00` أو بلا وقتٍ أصلاً. الوقت
 * لحظةُ حفظ المستند (`created_at`)، والتاريخ ما كُتب عليه.
 *
 * Usage (Blade):
 *   {{ \App\Services\Zatca\ZatcaLabel::invoiceTitle($invoice->client) }}
 *   {{ \App\Services\Zatca\ZatcaLabel::issuedAt($invoice) }}
 */
class ZatcaLabel
{
    /** أللعميل رقمٌ ضريبي سعودي صحيح؟ به تكون الفاتورة ضريبيةً لا مبسّطة. */
    public static function isBusiness($client): bool
    {
        if (! $client) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', strtr((string) ($client->tax_number ?? ''), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));

        return preg_match(ZatcaPayloadBuilder::VAT_PATTERN, (string) $digits) === 1;
    }

    public static function invoiceTitle($client): string
    {
        return self::isBusiness($client)
            ? __('zatca.pdf.title_standard')
            : __('zatca.pdf.title_simplified');
    }

    /** تاريخ المستند ووقت إصداره. */
    public static function issuedAt($document, string $format = 'Y-m-d H:i'): string
    {
        try {
            $created = $document->created_at ? Carbon::parse($document->created_at) : null;
            $date = $document->invoice_date ?? $document->credit_date ?? null;
            $day = $date ? Carbon::parse($date) : $created;

            if (! $day) {
                return '';
            }

            return ($created ? $day->copy()->setTimeFrom($created) : $day)->format($format);
        } catch (Throwable $e) {
            return '';
        }
    }

    /** ضريبة البند بنسبته المضبوطة — لا `× 0.15` مكتوبةً في القالب. */
    public static function lineTax($item): float
    {
        return (float) DocumentTaxCalculator::line(
            (float) $item->quantity,
            (float) $item->unit_price,
            (float) ($item->discount ?? 0),
            [$item->tax_1_id ?? null, $item->tax_2_id ?? null],
            [$item->tax_1 ?? null, $item->tax_2 ?? null]
        )['tax'];
    }
}
