<?php

namespace App\Services\Zatca;

use App\Models\ZatcaDocument;
use Carbon\Carbon;
use DOMDocument;
use DOMNode;
use DOMXPath;
use TCPDF;

/**
 * الفاتورة الإلكترونية كما تُسلَّم للعميل: PDF/A-3 في جوفه ملفّ الهيئة.
 *
 * الهيئة تقبل تسليم الفاتورة الضريبية بإحدى صيغتين: ملفّ XML، أو PDF/A-3
 * يضمّه. والثانية ما يُرسَل فعلاً: يفتحها العميل فيرى فاتورةً، ونظامُه
 * المحاسبي يستخرج منها الملفّ.
 *
 * **كلّ رقمٍ في الصفحة يُقرأ من الملفّ الموقَّع نفسه** لا من جداول المبيعات:
 * الصفحة وجهُ الملفّ المرفق بها، فلو قُرئت من مصدرٍ آخر لأمكن أن يختلفا —
 * وفاتورةٌ تعرض مبلغاً وملفُّها يحمل غيره أسوأ من فاتورةٍ بلا ملفّ.
 *
 * Usage:
 *   $binary = app(ZatcaPdfBuilder::class)->build($document);
 */
class ZatcaPdfBuilder
{
    private const NAMESPACES = [
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    ];

    private const FONT = 'aealarabiya';

    /** محتوى ملفّ PDF. */
    public function build(ZatcaDocument $document): string
    {
        $xml = $document->finalXml();
        $data = $this->read($xml);

        // `3` ⇒ PDF/A-3: الإصدار الوحيد من PDF/A الذي يقبل مرفقاً غير PDF
        $pdf = new class('P', 'mm', 'A4', true, 'UTF-8', false, 3) extends TCPDF
        {
            // المكتبة تكتب في آخر صفحةٍ سطراً دعائياً خفيّاً برابطها؛ لا
            // مكان له في فاتورةٍ تُسلَّم للعميل، ولا مُعدِّل له إلا الوراثة
            // في المُنشئ لا في تعريف الخاصّية: مُنشئ المكتبة يعيدها `true`
            public function __construct(...$arguments)
            {
                parent::__construct(...$arguments);

                $this->tcpdflink = false;
            }
        };

        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor($data['seller']['name']);
        $pdf->SetTitle($this->title($document).' '.$data['number']);
        $pdf->SetMargins(12, 12, 12);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->setRTL(true);
        $pdf->SetFont(self::FONT, '', 10);

        $pdf->writeHTML($this->html($document, $data), true, false, true, false, '');

        $this->identifiers($pdf, $document, $data);
        $this->qr($pdf, (string) $document->qr);

        $pdf->EmbedFileFromString($this->attachmentName($document), $xml);

        return $pdf->Output('', 'S');
    }

    /**
     * ما يُعرَض، مقروءاً من الملفّ.
     *
     * @return array<string, mixed>
     */
    public function read(string $xml): array
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET);

        $xpath = new DOMXPath($dom);

        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $root = $dom->documentElement;
        $text = fn (string $query, ?DOMNode $context = null): string => trim((string) $xpath->evaluate('string('.$query.')', $context ?? $root));
        $number = fn (string $query, ?DOMNode $context = null): float => (float) $text($query, $context);

        $party = function (string $role) use ($text): array {
            $base = 'cac:'.$role.'/cac:Party';

            return [
                'name' => $text($base.'/cac:PartyLegalEntity/cbc:RegistrationName'),
                'vat' => $text($base.'/cac:PartyTaxScheme/cbc:CompanyID'),
                'id' => $text($base.'/cac:PartyIdentification/cbc:ID'),
                'address' => implode('، ', array_filter([
                    trim($text($base.'/cac:PostalAddress/cbc:BuildingNumber').' '.$text($base.'/cac:PostalAddress/cbc:StreetName')),
                    $text($base.'/cac:PostalAddress/cbc:CitySubdivisionName'),
                    $text($base.'/cac:PostalAddress/cbc:CityName'),
                    $text($base.'/cac:PostalAddress/cbc:PostalZone'),
                ])),
            ];
        };

        $lines = [];

        foreach ($xpath->query('cac:InvoiceLine', $root) as $line) {
            $net = $number('cbc:LineExtensionAmount', $line);
            $tax = $number('cac:TaxTotal/cbc:TaxAmount', $line);
            $discount = $number('cac:AllowanceCharge[cbc:ChargeIndicator="false"]/cbc:Amount', $line);
            $quantity = $number('cbc:InvoicedQuantity', $line);

            $lines[] = [
                'name' => $text('cac:Item/cbc:Name', $line),
                'quantity' => $quantity,
                // من الإجمالي لا من خانة السعر: السعر قد يُكتَب لكمّية البند
                // كلّها («الكمّية الأساس») فلا يكون سعرَ الوحدة
                'price' => $quantity > 0 ? ($net + $discount) / $quantity : 0.0,
                'discount' => $discount,
                'net' => $net,
                'rate' => $number('cac:Item/cac:ClassifiedTaxCategory/cbc:Percent', $line),
                'tax' => $tax,
                'total' => $net + $tax,
            ];
        }

        $issued = $text('cbc:IssueDate').' '.rtrim($text('cbc:IssueTime'), 'Z');

        try {
            // الملفّ بتوقيت UTC؛ والفاتورة تُقرأ بتوقيت المنشأة
            $issued = Carbon::parse($issued, 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            // يبقى كما في الملفّ
        }

        return [
            'number' => $text('cbc:ID'),
            'uuid' => $text('cbc:UUID'),
            'issued_at' => $issued,
            'currency' => $text('cbc:DocumentCurrencyCode') ?: 'SAR',
            'reference' => $text('cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'),
            'seller' => $party('AccountingSupplierParty'),
            'buyer' => $party('AccountingCustomerParty'),
            'lines' => $lines,
            'totals' => [
                'lines' => $number('cac:LegalMonetaryTotal/cbc:LineExtensionAmount'),
                'discount' => $number('cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount'),
                'taxable' => $number('cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'),
                'tax' => $number('cac:TaxTotal[cac:TaxSubtotal]/cbc:TaxAmount'),
                'due' => $number('cac:LegalMonetaryTotal/cbc:PayableAmount'),
            ],
        ];
    }

    protected function title(ZatcaDocument $document, string $suffix = ''): string
    {
        $key = $document->kind === 'invoice' ? $document->profile : $document->kind;

        return __('zatca.pdf.title_'.$key.$suffix);
    }

    /** اسم المرفق: رقم المستند بمحارف يقبلها كلّ نظام ملفّات. */
    protected function attachmentName(ZatcaDocument $document): string
    {
        $number = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $document->document_number), '_');

        return ($number !== '' ? $number : 'invoice').'.xml';
    }

    /**
     * المعرّف الفريد وعدّاد الهيئة — يُكتبان من اليسار إلى اليمين.
     *
     * المعرّف مقاطعُ لاتينية بينها شَرطات، وفي سياقٍ عربيّ تُقلَب مقاطعه
     * فيُطبَع معرّفٌ غير الذي في الملفّ. فيُكتَب خارج النصّ العربي.
     *
     * @param  array<string, mixed>  $data
     */
    protected function identifiers(TCPDF $pdf, ZatcaDocument $document, array $data): void
    {
        $pdf->setRTL(false);
        // الخطّ نفسه: خطٌّ ثانٍ يُضمَّن كاملاً في PDF/A فيتضاعف حجم الملفّ أربعاً
        $pdf->SetFont(self::FONT, '', 7);
        $pdf->SetTextColor(90, 98, 110);
        $pdf->Cell(0, 5, 'UUID: '.$data['uuid'].'   |   ICV: '.$document->icv, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont(self::FONT, '', 10);
        $pdf->setRTL(true);
    }

    /**
     * رمز QR مرسوماً بخطوط PDF لا صورةً: يبقى حادّاً عند الطباعة، ولا يُدخِل
     * في الملفّ صورةً بشفافيةٍ يمنعها PDF/A.
     */
    protected function qr(TCPDF $pdf, string $payload): void
    {
        if ($payload === '') {
            return;
        }

        $size = 38;

        if ($pdf->GetY() + $size + 12 > $pdf->getPageHeight() - 14) {
            $pdf->AddPage();
        }

        $y = $pdf->GetY() + 3;

        $pdf->setRTL(false);
        $pdf->write2DBarcode($payload, 'QRCODE,M', 12, $y, $size, $size, ['border' => false, 'padding' => 0]);
        $pdf->setRTL(true);

        $pdf->SetXY(12, $y + 6);
        $pdf->SetFont(self::FONT, '', 8);
        $pdf->SetTextColor(90, 98, 110);
        $pdf->MultiCell(186 - $size - 6, 0, __('zatca.pdf.footer'), 0, 'R', false, 1, 12 + $size + 6, $y + 6);
        $pdf->SetTextColor(0, 0, 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function html(ZatcaDocument $document, array $data): string
    {
        $e = fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $money = fn (float $value): string => number_format($value, 2);
        $quantity = fn (float $value): string => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        $head = 'background-color:#1f3a5f;color:#ffffff;font-weight:bold;';
        $cell = 'border:0.3px solid #c9d0da;';
        $muted = 'color:#5a626e;';

        $partyBlock = function (string $label, array $party) use ($e, $cell, $head, $muted): string {
            if (($party['name'] ?? '') === '' && ($party['vat'] ?? '') === '') {
                return '<td width="49%"></td>';
            }

            $rows = '<tr><td style="'.$head.'" colspan="2"> '.$e($label).'</td></tr>'
                .'<tr><td style="'.$cell.'" colspan="2"><b>'.$e($party['name']).'</b></td></tr>';

            foreach ([
                'vat_number' => $party['vat'],
                'commercial_register' => $party['id'],
                'address' => $party['address'],
            ] as $key => $value) {
                if ($value !== '') {
                    $rows .= '<tr><td style="'.$cell.$muted.'" width="34%">'.$e(__('zatca.pdf.'.$key)).'</td>'
                        .'<td style="'.$cell.'" width="66%">'.$e($value).'</td></tr>';
                }
            }

            return '<td width="49%"><table cellpadding="4" cellspacing="0">'.$rows.'</table></td>';
        };

        $html = '<table cellpadding="2" cellspacing="0"><tr>'
            .'<td width="60%"><span style="font-size:17pt;font-weight:bold;color:#1f3a5f;">'.$e($this->title($document)).'</span><br />'
            .'<span style="font-size:10pt;'.$muted.'">'.$e($this->title($document, '_en')).'</span></td>'
            .'<td width="40%"><table cellpadding="3" cellspacing="0">'
            .'<tr><td style="'.$cell.$muted.'" width="42%">'.$e(__('zatca.pdf.number')).'</td><td style="'.$cell.'" width="58%"><b>'.$e($data['number']).'</b></td></tr>'
            .'<tr><td style="'.$cell.$muted.'">'.$e(__('zatca.pdf.date')).'</td><td style="'.$cell.'">'.$e($data['issued_at']).'</td></tr>'
            .($data['reference'] !== ''
                ? '<tr><td style="'.$cell.$muted.'">'.$e(__('zatca.pdf.reference')).'</td><td style="'.$cell.'">'.$e($data['reference']).'</td></tr>'
                : '')
            .'</table></td></tr></table><br /><br />';

        $html .= '<table cellpadding="0" cellspacing="0"><tr>'
            .$partyBlock(__('zatca.pdf.seller'), $data['seller'])
            .'<td width="2%"></td>'
            .$partyBlock(__('zatca.pdf.buyer'), $data['buyer'])
            .'</tr></table><br /><br />';

        $columns = [
            ['#', 5, 'center'],
            [__('zatca.pdf.item'), 29, 'right'],
            [__('zatca.pdf.quantity'), 9, 'center'],
            [__('zatca.pdf.unit_price'), 11, 'center'],
            [__('zatca.pdf.discount'), 9, 'center'],
            [__('zatca.pdf.taxable'), 12, 'center'],
            [__('zatca.pdf.vat_rate'), 7, 'center'],
            [__('zatca.pdf.vat_amount'), 8, 'center'],
            [__('zatca.pdf.line_total'), 10, 'center'],
        ];

        $html .= '<table cellpadding="4" cellspacing="0"><thead><tr>';

        foreach ($columns as [$label, $width, $align]) {
            $html .= '<th style="'.$head.$cell.'font-size:8.5pt;" width="'.$width.'%" align="'.$align.'">'.$e($label).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data['lines'] as $index => $line) {
            $values = [
                $index + 1,
                $line['name'],
                $quantity($line['quantity']),
                $money($line['price']),
                $money($line['discount']),
                $money($line['net']),
                $quantity($line['rate']).'%',
                $money($line['tax']),
                $money($line['total']),
            ];

            $html .= '<tr nobr="true">';

            foreach ($columns as $position => [$label, $width, $align]) {
                $html .= '<td style="'.$cell.'font-size:9pt;" width="'.$width.'%" align="'.$align.'">'.$e($values[$position]).'</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table><br /><br />';

        $totals = [
            'subtotal' => $data['totals']['lines'],
            'total_discount' => $data['totals']['discount'],
            'total_taxable' => $data['totals']['taxable'],
            'total_vat' => $data['totals']['tax'],
        ];

        $html .= '<table cellpadding="0" cellspacing="0"><tr><td width="50%"><table cellpadding="4" cellspacing="0">';

        foreach ($totals as $key => $value) {
            // الخصم يُعرَض حين يوجد: سطرٌ بصفرٍ يُوهم أنّ في الفاتورة خصماً
            if ($key === 'total_discount' && $value <= 0) {
                continue;
            }

            $html .= '<tr><td style="'.$cell.$muted.'" width="60%">'.$e(__('zatca.pdf.'.$key)).'</td>'
                .'<td style="'.$cell.'" width="40%" align="center">'.$money($value).'</td></tr>';
        }

        $html .= '<tr><td style="'.$head.$cell.'" width="60%">'.$e(__('zatca.pdf.total_due')).' ('.$e($data['currency']).')</td>'
            .'<td style="'.$head.$cell.'" width="40%" align="center">'.$money($data['totals']['due']).'</td></tr>'
            .'</table></td><td width="50%"></td></tr></table>';

        return $html;
    }
}
