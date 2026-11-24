<?php

namespace App\Services\Zatca;

use App\Models\ElectronicInvoiceSetting;
use Saleh7\Zatca\Address;
use Saleh7\Zatca\AllowanceCharge;
use Saleh7\Zatca\GeneratorInvoice;
use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\Invoice;
use Saleh7\Zatca\InvoiceSigner;
use Saleh7\Zatca\Mappers\InvoiceMapper;

/**
 * من بيانات المستند إلى ملفٍّ موقَّع — الحدّ الفاصل بيننا وبين الحزمة.
 *
 * كلّ نداءٍ على `saleh7/php-zatca-xml` لبناء الملفّ وتوقيعه يمرّ من هنا،
 * فترقية الحزمة أو استبدالها تمسّ هذا الملفّ وحده.
 */
class ZatcaXmlFactory
{
    /**
     * بصمة «المستند السابق» لأوّل مستندٍ في السلسلة، كما تعرّفها الهيئة:
     * SHA-256 للصفر، مرمَّزةً base64.
     */
    public const FIRST_PREVIOUS_HASH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    /** ما تملأ به خانةٌ يفرضها مدقّق الحزمة ولا تفرضها الهيئة، ثم يُزال */
    private const PLACEHOLDER = '0000';

    /**
     * @param  array<string, mixed>  $data  ناتج `ZatcaPayloadBuilder`
     * @param  array<int, array<string, mixed>>  $lines  بنوده بتفاصيلها
     * @return array{xml: string, hash: string, qr: string}
     */
    public function sign(array $data, array $lines, string $uuid, int $icv, string $previousHash, Certificate $certificate): array
    {
        $data['uuid'] = $uuid;
        $data['additionalDocuments'] = [
            ['id' => 'ICV', 'uuid' => (string) $icv],
            ['id' => 'PIH', 'attachment' => ['content' => $previousHash]],
        ];

        $withoutBuilding = isset($data['customer']['address'])
            && ($data['customer']['address']['buildingNumber'] ?? '') === '';

        if ($withoutBuilding) {
            $data['customer']['address']['buildingNumber'] = self::PLACEHOLDER;
        }

        $invoice = (new InvoiceMapper)->mapToInvoice($data);

        if ($withoutBuilding) {
            $this->dropBuyerBuildingNumber($invoice, $data['customer']['address']);
        }

        $this->refineLines($invoice, $lines);

        $signed = InvoiceSigner::signInvoice(GeneratorInvoice::invoice($invoice)->getXML(), $certificate);

        return [
            'xml' => $signed->getInvoice(),
            'hash' => $signed->getHash(),
            'qr' => $signed->getQRCode(),
        ];
    }

    /**
     * شهادة التوقيع من المحفوظ في الإعدادات.
     *
     * @param  string  $stage  `production` للإرسال، `compliance` لفحوص التسجيل
     */
    public function certificate(ElectronicInvoiceSetting $settings, string $stage = 'production'): Certificate
    {
        $certificate = $settings->{$stage.'_certificate'};
        $secret = $settings->{$stage.'_secret'};

        if (blank($certificate) || blank($secret) || blank($settings->private_key)) {
            throw new ZatcaException(__('zatca.errors.not_onboarded'));
        }

        return new Certificate(
            (string) $certificate,
            $this->bareKey((string) $settings->private_key),
            (string) $secret
        );
    }

    /** رمز QR من الملفّ الذي أعادته الهيئة معتمَداً — هو الذي يُطبَع. */
    public function qrFrom(string $xml): ?string
    {
        $pattern = '#<cbc:ID>QR</cbc:ID>.*?<cbc:EmbeddedDocumentBinaryObject[^>]*>([^<]+)</cbc:EmbeddedDocumentBinaryObject>#s';

        return preg_match($pattern, $xml, $match) ? trim($match[1]) : null;
    }

    /** المفتاح بلا ترويسته — هكذا تقرؤه الحزمة. */
    private function bareKey(string $pem): string
    {
        return trim((string) preg_replace('/-----(?:BEGIN|END)(?: EC)? PRIVATE KEY-----/', '', $pem));
    }

    /**
     * خصم البند والكمّية الأساس — لا يعرفهما محوّل الحزمة فيُضافان بعده.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function refineLines(Invoice $invoice, array $lines): void
    {
        foreach (array_values($invoice->getInvoiceLines() ?? []) as $index => $line) {
            $meta = $lines[$index] ?? null;

            if ($meta === null) {
                continue;
            }

            if (($meta['discount'] ?? 0) > 0) {
                $line->setAllowanceCharges([
                    (new AllowanceCharge)
                        ->setChargeIndicator(false)
                        ->setAllowanceChargeReason('discount')
                        ->setAmount((float) $meta['discount']),
                ]);
            }

            if (! empty($meta['base_quantity']) && $line->getPrice() !== null) {
                $line->getPrice()->setBaseQuantity((float) $meta['base_quantity']);
            }
        }
    }

    /**
     * مدقّق الحزمة يفرض رقم مبنى المشتري، وسجلّ العميل لا يحمله. فيُمرَّر
     * بديلٌ ليجتاز المدقّق ثم يُبنى العنوان من جديد بدونه — الملفّ الموقَّع
     * لا يحمل رقماً مختلَقاً.
     *
     * @param  array<string, string>  $address
     */
    private function dropBuyerBuildingNumber(Invoice $invoice, array $address): void
    {
        $party = $invoice->getAccountingCustomerParty();

        if ($party === null) {
            return;
        }

        $party->setPostalAddress(
            (new Address)
                ->setStreetName($address['street'])
                ->setCitySubdivisionName($address['subdivision'])
                ->setCityName($address['city'])
                ->setPostalZone($address['postalZone'])
                ->setCountry($address['country'] ?? 'SA')
        );
    }
}
