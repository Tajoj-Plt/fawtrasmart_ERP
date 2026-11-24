<?php

namespace App\Services\Zatca;

use App\Models\Client;
use App\Models\CreditNotification;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Neighborhood;
use App\Services\Tax\DocumentTaxCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * يحوّل مستند المبيعات إلى الصيغة التي تبني منها الحزمة ملفّ الهيئة (UBL).
 *
 * **أرقام الملفّ تُحسب هنا بقاعدة الهيئة لا تُنقَل من المستند.** المستند
 * يقرّب ضريبة كلّ بند ثم يجمع، والهيئة تجمع وعاء كلّ فئةٍ ضريبية ثم تضرب
 * وتقرّب مرّة واحدة — فيختلفان بهللة أو اثنتين في المستند كثير البنود. ثم
 * يُقارَن الناتج بإجمالي المستند: فرقٌ يتجاوز `TOLERANCE` يعني أنّ في
 * المستند ما لا يعرفه هذا المحوّل (تسويةٌ أو رسمٌ)، فيُرفَض الإرسال بدل أن
 * يبلغ الهيئةَ مبلغٌ غير الذي على الفاتورة المطبوعة.
 *
 * ترتيب الحساب هو ترتيب مستندات المبيعات نفسه — انظر
 * `DocumentTaxCalculator::allocateDocumentDiscount()`: خصم البند يُنقص
 * صافيه، والخصم العامّ يُوزَّع على البنود فيُنقص وعاء كلّ فئة، والشحن
 * إضافةٌ خاضعة تأتي بعد الخصم.
 *
 * Usage:
 *   $payload = app(ZatcaPayloadBuilder::class)->forInvoice($invoice, $settings);
 *   $payload['profile'];  // standard | simplified
 *   $payload['data'];     // بلا uuid ولا عدّاد ولا بصمة — تضيفها خدمة الإرسال
 */
class ZatcaPayloadBuilder
{
    /** أقصى فرقٍ مقبول بين إجمالي المستند وما حُسب للهيئة */
    public const TOLERANCE = 0.10;

    /** رقم ضريبي سعودي: خمس عشرة خانة تبدأ وتنتهي بثلاثة */
    public const VAT_PATTERN = '/^3\d{13}3$/';

    /** رقما الاختبار في نماذج عُدّة الهيئة — لمستندات فحص الامتثال وحدها */
    private const SAMPLE_BUYER_VAT = '399999999800003';

    private const SAMPLE_SELLER_VAT = '399999999900003';

    /**
     * @return array{profile: string, kind: string, number: string, data: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    public function forInvoice(Invoice $invoice, ElectronicInvoiceSetting $settings): array
    {
        if ($invoice->isVoided()) {
            throw new ZatcaException(__('zatca.errors.voided'));
        }

        $isReturn = $invoice->type === 'returned';
        $reference = null;

        if ($isReturn) {
            // الرابط بالأصل `reference_id` في الجديد و`reference_number` في
            // القديم — وكلاهما معرّف الفاتورة لا رقمها
            $originalId = $invoice->reference_id ?: $invoice->reference_number;
            $reference = $originalId ? Invoice::query()->whereKey($originalId)->value('code') : null;

            if (blank($reference)) {
                throw new ZatcaException(__('zatca.errors.return_without_original'));
            }
        }

        return $this->build(
            settings: $settings,
            number: (string) ($invoice->code ?: $invoice->id),
            kind: $isReturn ? 'credit' : 'invoice',
            issuedAt: $this->issuedAt($invoice->invoice_date, $invoice->created_at),
            client: $invoice->client,
            items: $invoice->items()->get(),
            discountTotal: (float) $invoice->discount_amount,
            shippingCost: (float) $invoice->shipping_cost,
            shippingTax: (float) $invoice->shipping_tax,
            grandTotal: (float) $invoice->grand_total,
            paid: (int) $invoice->payment_status === 1,
            reference: $reference,
            reason: $isReturn ? ($invoice->notes ?: __('zatca.reasons.return')) : null,
        );
    }

    /**
     * @return array{profile: string, kind: string, number: string, data: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    public function forCreditNote(CreditNotification $note, ElectronicInvoiceSetting $settings): array
    {
        // 0 مسودّة و5 ملغى — لا يبلغان الهيئة
        if (in_array((int) $note->status, [0, 5], true)) {
            throw new ZatcaException(__('zatca.errors.credit_note_inactive'));
        }

        $reference = $note->invoice_id ? Invoice::query()->whereKey($note->invoice_id)->value('code') : null;

        if (blank($reference)) {
            throw new ZatcaException(__('zatca.errors.credit_note_without_invoice'));
        }

        return $this->build(
            settings: $settings,
            number: (string) ($note->credit_number ?: $note->id),
            kind: 'credit',
            issuedAt: $this->issuedAt($note->credit_date, $note->created_at),
            client: $note->client,
            items: $note->items()->get(),
            discountTotal: max((float) $note->total_discount, (float) $note->discount_amount),
            shippingCost: (float) $note->shipping_cost,
            shippingTax: (float) $note->shipping_tax,
            grandTotal: (float) $note->grand_total,
            paid: false,
            reference: $reference,
            reason: $note->notes ?: __('zatca.reasons.credit_note'),
        );
    }

    /**
     * مستندٌ نموذجيّ لفحوص الامتثال — ببيانات المنشأة الفعلية وبندٍ واحد.
     *
     * الهيئة تفحص أنّ النظام يُخرج كلّ نوعٍ صحيحاً قبل أن تمنح شهادة
     * الإنتاج، فالعميل والبند هنا بيانات فحصٍ لا مستندٌ من الدفاتر.
     *
     * @return array<string, mixed>
     */
    public function sample(ElectronicInvoiceSetting $settings, string $profile, string $kind, int $sequence): array
    {
        $data = [
            'id' => 'CHECK-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'issueDate' => now()->toIso8601String(),
            'issueTime' => now()->toIso8601String(),
            'currencyCode' => 'SAR',
            'taxCurrencyCode' => 'SAR',
            'invoiceType' => ['invoice' => $profile, 'type' => $kind],
            'supplier' => $this->supplier($settings),
            'paymentMeans' => ['code' => '10'],
            'taxTotal' => [
                'taxAmount' => 15.00,
                'subTotals' => [[
                    'taxableAmount' => 100.00,
                    'taxAmount' => 15.00,
                    'taxCategory' => ['id' => 'S', 'percent' => 15, 'taxScheme' => ['id' => 'VAT']],
                ]],
            ],
            'legalMonetaryTotal' => [
                'lineExtensionAmount' => 100.00,
                'taxExclusiveAmount' => 100.00,
                'taxInclusiveAmount' => 115.00,
                'prepaidAmount' => 0,
                'payableAmount' => 115.00,
                'allowanceTotalAmount' => 0,
            ],
            'invoiceLines' => [[
                'id' => 1,
                'unitCode' => 'PCE',
                'quantity' => 1,
                'lineExtensionAmount' => 100.00,
                'item' => [
                    'name' => 'Compliance check item',
                    'classifiedTaxCategory' => [['id' => 'S', 'percent' => 15, 'taxScheme' => ['id' => 'VAT']]],
                ],
                'price' => ['amount' => 100.00, 'unitCode' => 'PCE'],
                'taxTotal' => ['taxAmount' => 15.00, 'roundingAmount' => 115.00],
            ]],
        ];

        if ($kind !== 'invoice') {
            $data['billingReferences'] = [['id' => 'CHECK-001']];
            $data['paymentMeans']['note'] = 'Compliance check';
        }

        if ($profile === 'standard') {
            /* مشتري الفحص: رقمُ الاختبار المنشور في عُدّة الهيئة (SDK)، لا
               رقمُ عميلٍ من الدفاتر — والهيئة ترفض مشترياً رقمُه رقمُ
               البائع. والعنوان عنوان المنشأة: مكتملٌ بلا اختلاق. */
            $supplier = $data['supplier'];

            $data['customer'] = [
                'registrationName' => 'Compliance check buyer',
                'taxId' => $supplier['taxId'] === self::SAMPLE_BUYER_VAT ? self::SAMPLE_SELLER_VAT : self::SAMPLE_BUYER_VAT,
                'address' => $supplier['address'],
            ];
            $data['delivery'] = ['actualDeliveryDate' => now()->toDateString()];
        }

        return $data;
    }

    /**
     * بيانات البائع — وما ينقص منها يُسمّى باسمه.
     *
     * @return array<string, mixed>
     */
    public function supplier(ElectronicInvoiceSetting $settings): array
    {
        $taxNumber = $this->digits($settings->fromEstablishment('tax_number'));
        $postal = $this->digits($settings->fromEstablishment('postal_code'));
        $building = $this->digits($settings->building_number);

        $problems = [];

        if (! preg_match(self::VAT_PATTERN, $taxNumber)) {
            $problems[] = __('zatca.errors.seller_tax_number');
        }

        if (strlen($postal) !== 5) {
            $problems[] = __('zatca.errors.seller_postal_code');
        }

        if (strlen($building) !== 4) {
            $problems[] = __('zatca.errors.seller_building_number');
        }

        foreach ([
            'trade_name' => $settings->fromEstablishment('trade_name'),
            'commercial_register' => $settings->fromEstablishment('commercial_registration'),
            'street' => $settings->fromEstablishment('street1'),
            'city' => $settings->fromEstablishment('city'),
            'district' => $settings->district,
        ] as $key => $value) {
            if (blank($value)) {
                $problems[] = __('zatca.errors.seller_missing', ['field' => __('sales_settings.electronic.fields.'.$key)]);
            }
        }

        if ($problems !== []) {
            throw ZatcaException::withDetails(__('zatca.errors.seller_incomplete'), $problems);
        }

        return [
            'registrationName' => (string) $settings->fromEstablishment('trade_name'),
            'taxId' => $taxNumber,
            'identificationId' => (string) $settings->fromEstablishment('commercial_registration'),
            'identificationType' => 'CRN',
            'address' => [
                'street' => (string) $settings->fromEstablishment('street1'),
                'buildingNumber' => $building,
                'subdivision' => (string) $settings->district,
                'city' => (string) $settings->fromEstablishment('city'),
                'postalZone' => $postal,
                'country' => 'SA',
            ],
        ];
    }

    /**
     * بائع المستند المرسَل — ورقمُه في المنصّة التجريبية رقمُ شهادتها.
     *
     * منصّة المطوّرين لا تُصدر شهادة إنتاجٍ للمنشأة: تُعيد شهادةً واحدة ثابتة
     * برقم الهيئة الاختباري أيّاً كان طالبها، ثم ترفض كلّ مستندٍ رقمُ بائعه
     * غير رقم الشهادة (`certificate-permissions`). فالرقم الحقيقي لا يمرّ
     * فيها أبداً، ومستنداتها بلا أثرٍ نظامي — فيُكتَب رقم الشهادة.
     *
     * في المحاكاة والبوابة الفعلية الشهادة شهادة المنشأة ورقمها رقمها، فلا
     * يُمسّ شيء: هناك اختلاف الرقمين خطأ يجب أن يظهر لا أن يُستَر.
     *
     * @return array<string, mixed>
     */
    protected function sender(ElectronicInvoiceSetting $settings): array
    {
        $supplier = $this->supplier($settings);

        if ($settings->environment !== ElectronicInvoiceSetting::ENV_SANDBOX) {
            return $supplier;
        }

        $certified = $this->certificateVat((string) $settings->production_certificate);

        if ($certified !== null) {
            $supplier['taxId'] = $certified;
        }

        return $supplier;
    }

    /** الرقم الضريبي المكتوب في الشهادة (حقل UID)، أو `null` إن تعذّرت قراءتها. */
    protected function certificateVat(string $certificate): ?string
    {
        if ($certificate === '') {
            return null;
        }

        try {
            $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(trim($certificate), 64, "\n").'-----END CERTIFICATE-----';
            $parsed = openssl_x509_parse($pem);
        } catch (\Throwable $e) {
            return null;
        }

        $alternative = (string) ($parsed['extensions']['subjectAltName'] ?? '');

        return preg_match('/UID\s*=\s*(3\d{13}3)\b/', $alternative, $match) ? $match[1] : null;
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @return array{profile: string, kind: string, number: string, data: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    protected function build(
        ElectronicInvoiceSetting $settings,
        string $number,
        string $kind,
        Carbon $issuedAt,
        ?Client $client,
        Collection $items,
        float $discountTotal,
        float $shippingCost,
        float $shippingTax,
        float $grandTotal,
        bool $paid,
        ?string $reference,
        ?string $reason,
    ): array {
        if ($items->isEmpty()) {
            throw new ZatcaException(__('zatca.errors.no_lines'));
        }

        $profile = $this->profileFor($client, $settings);
        $lines = $this->lines($items, $discountTotal, $shippingCost, $shippingTax, $settings);
        $categories = $this->categories($lines);

        $lineTotal = round(array_sum(array_column($lines, 'net')), 2);
        $allowanceTotal = round(array_sum(array_column($lines, 'share')), 2);
        $taxTotal = round(array_sum(array_column($categories, 'tax')), 2);
        $taxExclusive = round($lineTotal - $allowanceTotal, 2);
        $taxInclusive = round($taxExclusive + $taxTotal, 2);

        if (abs($taxInclusive - round($grandTotal, 2)) > self::TOLERANCE) {
            throw new ZatcaException(__('zatca.errors.total_mismatch', [
                'document' => number_format($grandTotal, 2),
                'computed' => number_format($taxInclusive, 2),
            ]));
        }

        $data = [
            'id' => $number,
            'issueDate' => $issuedAt->toIso8601String(),
            'issueTime' => $issuedAt->toIso8601String(),
            'currencyCode' => 'SAR',
            'taxCurrencyCode' => 'SAR',
            'invoiceType' => ['invoice' => $profile, 'type' => $kind],
            'supplier' => $this->sender($settings),
            'paymentMeans' => ['code' => $paid ? '10' : '30'],
            'taxTotal' => [
                'taxAmount' => $taxTotal,
                'subTotals' => array_map(fn (array $category) => [
                    'taxableAmount' => $category['taxable'],
                    'taxAmount' => $category['tax'],
                    'taxCategory' => $this->taxCategory($category),
                ], array_values($categories)),
            ],
            'legalMonetaryTotal' => [
                'lineExtensionAmount' => $lineTotal,
                'taxExclusiveAmount' => $taxExclusive,
                'taxInclusiveAmount' => $taxInclusive,
                'prepaidAmount' => 0,
                'payableAmount' => $taxInclusive,
                'allowanceTotalAmount' => $allowanceTotal,
            ],
            'invoiceLines' => array_map(fn (array $line) => [
                'id' => $line['id'],
                'unitCode' => 'PCE',
                'quantity' => $line['quantity'],
                'lineExtensionAmount' => $line['net'],
                'item' => [
                    'name' => $line['name'],
                    'classifiedTaxCategory' => [$this->taxCategory($line)],
                ],
                'price' => ['amount' => $line['price'], 'unitCode' => 'PCE'],
                'taxTotal' => [
                    'taxAmount' => $line['tax'],
                    'roundingAmount' => round($line['net'] + $line['tax'], 2),
                ],
            ], $lines),
        ];

        // الخصم العامّ: خصمٌ على مستوى المستند لكلّ فئةٍ ضريبية بحصّتها
        $allowances = [];

        foreach ($categories as $category) {
            if ($category['share'] > 0) {
                $allowances[] = [
                    'isCharge' => false,
                    'reason' => 'discount',
                    'amount' => $category['share'],
                    'taxCategories' => [$this->taxCategory($category)],
                ];
            }
        }

        if ($allowances !== []) {
            $data['allowanceCharges'] = $allowances;
        }

        if ($kind !== 'invoice') {
            $data['billingReferences'] = [['id' => (string) $reference]];
            // سبب الإشعار مطلوب نظاماً، وموضعه ملاحظة وسيلة الدفع
            $data['paymentMeans']['note'] = mb_substr(trim((string) $reason), 0, 1000);
        }

        if ($profile === 'standard') {
            $data['customer'] = $this->customer($client);
            $data['delivery'] = ['actualDeliveryDate' => $issuedAt->toDateString()];
        }

        return [
            'profile' => $profile,
            'kind' => $kind,
            'number' => $number,
            'data' => $data,
            'lines' => $lines,
        ];
    }

    /**
     * ضريبية أم مبسّطة؟ العميل ذو الرقم الضريبي الصحيح منشأةٌ (B2B).
     */
    protected function profileFor(?Client $client, ElectronicInvoiceSetting $settings): string
    {
        $isBusiness = $client !== null
            && preg_match(self::VAT_PATTERN, $this->digits($client->tax_number)) === 1;

        $profile = $isBusiness ? 'standard' : 'simplified';

        if ($settings->issues($profile)) {
            return $profile;
        }

        // المنشأة مسجَّلة لنوعٍ واحد: عميلٌ برقم ضريبي يأخذ المبسّطة إن كانت
        // هي المسجَّلة، أمّا فردٌ بلا رقم فلا تصدر له ضريبية
        if ($profile === 'standard' && $settings->issues('simplified')) {
            return 'simplified';
        }

        throw new ZatcaException(__('zatca.errors.profile_not_registered'));
    }

    /**
     * بيانات المشتري في الفاتورة الضريبية.
     *
     * لا يُختلَق ما ليس في سجلّ العميل: الناقص يُسمّى ويُرفَض الإرسال حتى
     * يُستكمَل، فعنوانٌ مُلفَّق في ملفٍّ موقَّع أسوأ من تأخير الإرسال.
     *
     * @return array<string, mixed>
     */
    protected function customer(Client $client): array
    {
        $street = $client->street1 ?: $client->street2;
        $postal = $this->digits($client->postal_code);

        $problems = [];

        if (blank($street)) {
            $problems[] = __('zatca.errors.buyer_street');
        }

        if (blank($client->city)) {
            $problems[] = __('zatca.errors.buyer_city');
        }

        if (strlen($postal) !== 5) {
            $problems[] = __('zatca.errors.buyer_postal_code');
        }

        if ($problems !== []) {
            throw ZatcaException::withDetails(
                __('zatca.errors.buyer_incomplete', ['client' => $client->display_name]),
                $problems
            );
        }

        // رقم المبنى من خانته، وإلا ممّا كُتب في أوّل الشارع — ولا يُخترَع
        $stored = $this->digits($client->building_number ?? '');
        $building = strlen($stored) === 4 ? $stored : $this->buildingFrom((string) $client->street1);

        if ($building !== '' && $building !== $stored) {
            // وإلا كُتب الرقم مرّتين: في خانته وفي أوّل اسم الشارع
            $street = trim((string) preg_replace('/^\s*[\d٠-٩]{4}\b[\s،,-]*/u', '', (string) $street)) ?: $street;
        }

        $address = [
            'street' => (string) $street,
            // رقم المبنى ليس في سجلّ العميل — يُستخرَج إن كُتب في أوّل
            // الشارع، وإلا يُترك فارغاً ولا يُخترَع
            'buildingNumber' => $building,
            // الحيّ الذي يُكتَب في شاشة العميل يُحفَظ في `neighborhoods`
            'subdivision' => (string) ($this->district($client) ?: $client->street2 ?: $client->region ?: $client->city),
            'city' => (string) $client->city,
            'postalZone' => $postal,
            'country' => 'SA',
        ];

        $customer = [
            'registrationName' => (string) $client->display_name,
            'taxId' => $this->digits($client->tax_number),
            'address' => $address,
        ];

        if (filled($client->commercial_registration)) {
            $customer['identificationId'] = (string) $client->commercial_registration;
            $customer['identificationType'] = 'CRN';
        }

        return $customer;
    }

    /**
     * بنود المستند بأرقام الهيئة: صافٍ بلا ضريبة، وحصّةٌ من الخصم العامّ.
     *
     * @param  Collection<int, InvoiceItem>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function lines(Collection $items, float $discountTotal, float $shippingCost, float $shippingTax, ElectronicInvoiceSetting $settings): array
    {
        $lines = [];
        $lineDiscounts = 0.0;

        foreach ($items->values() as $index => $item) {
            if ((float) $item->tax_1 > 0 && (float) $item->tax_2 > 0) {
                throw new ZatcaException(__('zatca.errors.two_taxes', ['item' => $item->item]));
            }

            $useSecond = (float) $item->tax_1 <= 0 && (float) $item->tax_2 > 0;
            $taxId = $useSecond ? $item->tax_2_id : $item->tax_1_id;
            $setting = DocumentTaxCalculator::find($taxId);

            $rate = $setting ? (float) $setting->tax : (float) ($useSecond ? $item->tax_2 : $item->tax_1);
            $included = $setting ? $setting->type === 'included' : false;

            $quantity = (float) $item->quantity;
            $gross = round($quantity * (float) $item->unit_price, 2);
            $discount = min(max(0.0, (float) $item->discount), $gross);

            $lineDiscounts += $discount;

            $lines[] = [
                'id' => $index + 1,
                'name' => trim((string) ($item->item ?: optional($item->product)->name ?: __('zatca.item'))),
                'quantity' => $quantity,
                'rate' => $rate,
                'included' => $included,
                // المبلغ كما أُدخل — قد يحمل ضريبةً متضمَّنة تُستخرَج أدناه
                'entered_gross' => $gross,
                'entered_discount' => $discount,
                'entered_base' => round($gross - $discount, 2),
            ];
        }

        // `discount_amount` في المستند مجموعُ خصوم البنود والخصم العامّ
        // والكوبون؛ فالعامّ ما زاد على خصوم البنود
        $documentDiscount = max(0.0, round($discountTotal - $lineDiscounts, 2));

        $shares = DocumentTaxCalculator::allocateDocumentDiscount(
            array_column($lines, 'entered_base'),
            $documentDiscount
        );

        foreach ($lines as $index => $line) {
            $divisor = $line['included'] ? 1 + $line['rate'] / 100 : 1;

            $lines[$index] += $this->amounts(
                gross: $line['entered_gross'] / $divisor,
                discount: $line['entered_discount'] / $divisor,
                share: ($shares[$index] ?? 0) / $divisor,
                quantity: $line['quantity'],
                rate: $line['rate'],
            );
        }

        if ($shippingCost > 0) {
            $shippingRate = round($shippingTax / $shippingCost * 100, 2);

            $lines[] = [
                'id' => count($lines) + 1,
                'name' => __('zatca.shipping'),
                'quantity' => 1.0,
                'rate' => $shippingRate,
                'included' => false,
            ] + $this->amounts($shippingCost, 0, 0, 1, $shippingRate);
        }

        return $this->classify($lines, $settings);
    }

    /**
     * @return array{gross: float, discount: float, net: float, share: float, price: float, base_quantity: float|null, tax: float}
     */
    protected function amounts(float $gross, float $discount, float $share, float $quantity, float $rate): array
    {
        $gross = round($gross, 2);
        $discount = round($discount, 2);
        $net = round($gross - $discount, 2);

        $price = $quantity > 0 ? round($gross / $quantity, 2) : 0.0;

        /* السعر يُكتب بخانتين. فإن لم يُعِد ضربُه في الكمّية إجماليَّ البند
           (سعرٌ متضمّنٌ ضريبةً استُخرجت منه مثلاً) كُتب إجماليُّ البند سعراً
           لكمّيته كلّها — وهو ما تسمّيه الصيغة «الكمّية الأساس» — فيستقيم
           الحساب بلا هللةٍ ضائعة. */
        $exact = abs(round($price * $quantity, 2) - $gross) < 0.005;

        return [
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'share' => round(min($share, $net), 2),
            'price' => $exact ? $price : $gross,
            'base_quantity' => $exact ? null : $quantity,
            'tax' => round($net * $rate / 100, 2),
        ];
    }

    /**
     * الفئة الضريبية لكلّ بند.
     *
     * بندٌ بنسبةٍ أكبر من صفر «خاضع» (S). وبندٌ بلا ضريبة لا يُصنَّف تخميناً:
     * صفريٌّ أم معفى أم خارج النطاق قرارٌ ضريبي يضبطه صاحب المنشأة في
     * الإعدادات بسببه النظامي. والهدية (مبلغها صفر) تتبع فئة المستند
     * الخاضعة فلا أثر لها في الضريبة.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function classify(array $lines, ElectronicInvoiceSetting $settings): array
    {
        $standardRate = null;

        foreach ($lines as $line) {
            if ($line['rate'] > 0) {
                $standardRate = $line['rate'];
                break;
            }
        }

        foreach ($lines as $index => $line) {
            if ($line['rate'] > 0) {
                $lines[$index]['category'] = 'S';

                continue;
            }

            if ($line['net'] <= 0) {
                $lines[$index]['category'] = 'S';
                $lines[$index]['rate'] = $standardRate ?? 15.0;

                continue;
            }

            if (! in_array($settings->zero_tax_category, ElectronicInvoiceSetting::ZERO_TAX_CATEGORIES, true)
                || blank($settings->zero_tax_reason_code)
                || blank($settings->zero_tax_reason)) {
                throw new ZatcaException(__('zatca.errors.zero_tax_unclassified', ['item' => $line['name']]));
            }

            $lines[$index]['category'] = $settings->zero_tax_category;
            $lines[$index]['reason_code'] = $settings->zero_tax_reason_code;
            $lines[$index]['reason'] = $settings->zero_tax_reason;
        }

        return $lines;
    }

    /**
     * وعاء كلّ فئةٍ وضريبتها — الضريبة من الوعاء المجموع لا من جمع البنود.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, array<string, mixed>>
     */
    protected function categories(array $lines): array
    {
        $categories = [];

        foreach ($lines as $line) {
            $key = $line['category'].'|'.$line['rate'];

            $categories[$key] ??= [
                'category' => $line['category'],
                'rate' => $line['rate'],
                'reason_code' => $line['reason_code'] ?? null,
                'reason' => $line['reason'] ?? null,
                'net' => 0.0,
                'share' => 0.0,
            ];

            $categories[$key]['net'] += $line['net'];
            $categories[$key]['share'] += $line['share'];
        }

        foreach ($categories as $key => $category) {
            $share = round($category['share'], 2);
            $taxable = round($category['net'] - $share, 2);

            $categories[$key]['share'] = $share;
            $categories[$key]['taxable'] = $taxable;
            $categories[$key]['tax'] = round($taxable * $category['rate'] / 100, 2);
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $source  بندٌ أو فئة
     * @return array<string, mixed>
     */
    protected function taxCategory(array $source): array
    {
        $category = [
            'id' => $source['category'],
            'percent' => $source['rate'],
            'taxScheme' => ['id' => 'VAT'],
        ];

        if (! empty($source['reason_code'])) {
            $category['reasonCode'] = $source['reason_code'];
            $category['reason'] = $source['reason'];
        }

        return $category;
    }

    /** تاريخ المستند بوقت إنشائه: التاريخ ما كُتب عليه، والوقت لحظة حفظه. */
    protected function issuedAt($date, $createdAt): Carbon
    {
        $created = $createdAt ? Carbon::parse($createdAt) : now();
        $day = $date ? Carbon::parse($date) : $created;

        $issued = $day->copy()->setTimeFrom($created);

        // الهيئة ترفض تاريخاً في المستقبل
        return $issued->isFuture() ? now() : $issued;
    }

    /** الحيّ كما أُدخل في شاشة العميل، أو فراغ. */
    protected function district(Client $client): string
    {
        if (! $client->exists) {
            return '';
        }

        try {
            return trim((string) Neighborhood::where('client_id', $client->id)->orderByDesc('id')->value('name'));
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function buildingFrom(string $street): string
    {
        return preg_match('/^\s*(\d{4})\b/u', $this->latinDigits($street), $match) ? $match[1] : '';
    }

    protected function digits($value): string
    {
        return preg_replace('/\D+/', '', $this->latinDigits((string) $value)) ?? '';
    }

    protected function latinDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
