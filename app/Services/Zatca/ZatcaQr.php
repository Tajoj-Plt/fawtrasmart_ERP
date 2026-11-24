<?php

namespace App\Services\Zatca;

use App\Models\ElectronicInvoiceSetting;
use App\Models\ZatcaDocument;
use App\Support\BusinessInfo;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * رمز QR للمستند الضريبي — مصدرٌ واحد لكلّ مطبوعة.
 *
 * **لماذا؟** كان الرمز يُبنى في ستّة مواضع، كلٌّ بطريقته:
 *
 *   • فاتورة المبيعات ونقاط البيع: حقول الهيئة الخمسة (TLV) — صحيح.
 *   • القوالب المصمَّمة، وهي ما يُطبَع به افتراضياً: نصٌّ تفصل أجزاءَه `|`.
 *   • مرتجع المبيعات: نصٌّ عربيّ فيه رقم المرتجع واسم العميل.
 *   • الإشعار الدائن: موضعٌ فارغ.
 *   • المتجر الإلكتروني: اسم المتجر لا المنشأة، ورقمٌ ضريبيّ أصفار إن لم يُضبَط.
 *
 * وتطبيق الهيئة لا يقرأ إلا الصيغة الأولى. فمرتجعٌ أو فاتورةٌ طُبعت بقالبٍ
 * مصمَّم كانت تخرج برمزٍ يبدو سليماً ولا يُقرأ.
 *
 * **ما يُعاد:** رمز المرحلة الثانية إن قبلت الهيئة المستند — فيه بصمته
 * وتوقيعه — وإلا رمز المرحلة الأولى مبنيّاً من بيانات المستند. ولا يُقرأ
 * العمود `invoices.qrcode` المخزَّن: حُفظ فيه عبر السنين وقتٌ بغير الصيغة
 * واسمُ منشأةٍ كان مكتوباً في الشيفرة، والمبنيُّ الآن أصحّ منه.
 *
 * Usage:
 *   ZatcaQr::forInvoice($invoice);              // الحمولة base64
 *   ZatcaQr::svg(ZatcaQr::forInvoice($invoice)); // للطباعة
 */
class ZatcaQr
{
    /**
     * رمز المرحلة الأولى: الحقول الخمسة بترميز TLV ثم base64.
     *
     * @param  mixed  $timestamp  وقت إصدار المستند
     */
    public static function phaseOne($timestamp, float $total, float $vat, ?string $sellerName = null, ?string $sellerVat = null): string
    {
        $fields = [
            1 => $sellerName ?? BusinessInfo::name(),
            2 => $sellerVat ?? BusinessInfo::taxNumber(),
            3 => self::timestamp($timestamp),
            // موجبةً دائماً: نوع المستند ما يقول إنه مرتجع، لا إشارةٌ سالبة
            4 => number_format(abs($total), 2, '.', ''),
            5 => number_format(abs($vat), 2, '.', ''),
        ];

        $tlv = '';

        foreach ($fields as $tag => $value) {
            $value = (string) $value;

            // الطول بايتٌ واحد: ما زاد على 255 يُقصّ على حدّ محرفٍ كامل
            if (strlen($value) > 255) {
                $value = mb_strcut($value, 0, 255, 'UTF-8');
            }

            $tlv .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($tlv);
    }

    /** فاتورة مبيعات أو مرتجعها أو فاتورة نقطة بيع أو متجر — كلّها في `invoices`. */
    public static function forInvoice($invoice): string
    {
        if (! $invoice) {
            return '';
        }

        return self::accepted(ZatcaDocument::TYPE_INVOICE, (int) $invoice->id)
            ?? self::phaseOne(
                $invoice->created_at ?? $invoice->invoice_date,
                (float) ($invoice->grand_total ?? 0),
                (float) ($invoice->tax_total ?? 0)
            );
    }

    public static function forCreditNote($note): string
    {
        if (! $note) {
            return '';
        }

        return self::accepted(ZatcaDocument::TYPE_CREDIT_NOTE, (int) $note->id)
            ?? self::phaseOne(
                $note->created_at ?? $note->credit_date,
                (float) ($note->grand_total ?? 0),
                (float) ($note->total_tax ?? 0)
            );
    }

    /**
     * طلب المتجر: رمز فاتورته إن صدرت له فاتورة، وإلا من مبالغ الطلب.
     */
    public static function forOnlineOrder($order): string
    {
        if (! $order) {
            return '';
        }

        if (! empty($order->invoice_id)) {
            $accepted = self::accepted(ZatcaDocument::TYPE_INVOICE, (int) $order->invoice_id);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        return self::phaseOne(
            $order->created_at,
            (float) ($order->total_amount ?? 0),
            // الإجمالي يشمل ضريبة الشحن، فضريبة الرمز تشملها وإلا اختلف الرمز عن الفاتورة
            round((float) ($order->tax_amount ?? 0) + (float) ($order->shipping_tax_amount ?? 0), 2)
        );
    }

    /** الرمز مرسوماً SVG — للمطبوعات التي تضعه في الصفحة مباشرة. */
    public static function svg(string $payload, int $size = 150): string
    {
        if ($payload === '') {
            return '';
        }

        try {
            return (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))
                ->writeString($payload);
        } catch (Throwable $e) {
            // رمزٌ تعذّر رسمه لا يُسقط مطبوعةً كاملة
            return '';
        }
    }

    /**
     * الرمز صورةً في `<img>` — PNG متى أمكن.
     *
     * لمطبوعةٍ تُحوَّل إلى PDF (Dompdf وTCPDF): كلاهما يقرأ PNG ويتعثّر في
     * SVG المكتوب داخل الصفحة. والمتصفّح يقرأ الاثنين.
     */
    public static function image(string $payload, int $size = 150): string
    {
        if ($payload === '') {
            return '';
        }

        $source = self::pngDataUri($payload) ?? self::dataUri($payload, $size);

        return $source === ''
            ? ''
            : '<img src="'.$source.'" width="'.$size.'" height="'.$size.'" alt="QR">';
    }

    protected static function pngDataUri(string $payload): ?string
    {
        try {
            // الوسيطان مقاس الوحدة الواحدة بالبكسل لا مقاس الصورة
            $png = (new \TCPDF2DBarcode($payload, 'QRCODE,M'))->getBarcodePngData(6, 6, [0, 0, 0]);

            return $png ? 'data:image/png;base64,'.base64_encode($png) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** الرمز مسارَ بيانات — للقوالب التي تضعه في `<img src>`. */
    public static function dataUri(string $payload, int $size = 150): string
    {
        $svg = self::svg($payload, $size);

        return $svg === '' ? '' : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * وقت المستند كما تنصّ عليه المواصفة: ISO 8601 بتوقيت UTC وبلا كسور.
     */
    public static function timestamp($value): string
    {
        try {
            return Carbon::parse($value ?? now())->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable $e) {
            return now()->utc()->format('Y-m-d\TH:i:s\Z');
        }
    }

    /** رمز ما قبلته الهيئة في البيئة المفعَّلة، أو `null`. */
    protected static function accepted(string $type, int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        try {
            if (! Schema::hasTable('zatca_documents')) {
                return null;
            }

            $qr = ZatcaDocument::query()
                ->where('document_type', $type)
                ->where('document_id', $id)
                ->whereIn('status', ZatcaDocument::ACCEPTED)
                ->where('environment', ElectronicInvoiceSetting::current()->environment)
                ->orderByDesc('id')
                ->value('qr');

            return filled($qr) ? (string) $qr : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
