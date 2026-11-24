<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة ضريبية  #{{ $invoice->id }}</title>
    <!-- Import Arabic font -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap');

        /* Global Styles */
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f0f0f0;
            padding: 20px;
            direction: rtl;
            font-weight: bold;
        }

        @media print {
            #signature-form {
                display: none !important;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }

        /* Receipt Container */
        .receipt-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }

        .receipt {
            width: 80mm;
            /* Standard thermal receipt width */
            max-width: 100%;
            background-color: white;
            padding: 10px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }

        /* Receipt Header */
        .receipt-header {
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
            margin-bottom: 10px;
            text-align: center;
        }

        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* شعار المنشأة واسمُها — من «معلومات الحساب». مقيَّدان بعرض
           الإيصال (80mm) فلا يدفع شعارٌ كبير بقيّةَ الترويسة. */
        .org-logo {
            max-height: 60px;
            max-width: 60mm;
            margin: 0 auto 5px;
            display: block;
        }

        .org-name {
            font-size: 15px;
            font-weight: bold;
        }

        .receipt-header p {
            font-size: 12px;
            margin-bottom: 2px;
        }

        /* Invoice To Section */
        .invoice-to {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        /* Invoice Details Section */
        .invoice-details {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        /* Invoice Items Table */
        .invoice-items {
            margin-bottom: 10px;
        }

        .table {
            width: 100%;
            margin-bottom: 10px;
            font-size: 12px;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            text-align: center;
            padding: 5px;
            border-bottom: 1px dashed #ddd;
        }

        .table th {
            background-color: #f5f5f5;
            border-bottom: 1px solid #333;
        }

        /* Invoice Summary */
        .invoice-summary {
            border-top: 1px dashed #ccc;
            padding-top: 10px;
            margin-bottom: 15px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        /* QR Code */
        .qr-code {
            margin: 15px 0;
            text-align: center;
        }

        /* Signature */
        .signature {
            margin: 15px auto 0;
            padding-top: 10px;
            border-top: 1px dashed #333;
            width: 90%;
            text-align: center;
        }

        .thank-you {
            font-style: italic;
            margin-top: 5px;
        }

        /* Toastr Styles */
        #toast-container>.toast {
            background-image: none !important;
        }

        #toast-container>.toast:before {
            position: fixed;
            font-family: FontAwesome;
            font-size: 24px;
            line-height: 18px;
            float: right;
            color: #FFF;
            padding-right: 0.5em;
            margin: auto 0.5em auto -1.5em;
        }

        #toast-container>.toast-success:before {
            content: "\f00c";
        }

        #toast-container>.toast-error:before {
            content: "\f00d";
        }

        /* Print Styles */
        @media print {
            body {
                background-color: white;
                padding: 0;
                margin: 0;
                display: block !important;
                width: 80mm !important;
                font-weight: bold !important;
            }

            .receipt {
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0 auto !important;
            }

            .receipt-container {
                min-height: auto;
            }

            .qr-code svg {
                width: 70px !important;
                height: 70px !important;
            }
        }

        /* Responsive Styles */
        @media (max-width: 576px) {
            .receipt {
                width: 100%;
            }
        }
    </style>
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
</head>

<body>
    @php
        $returnedInvoice = \App\Models\Invoice::where('type', 'returned')
            ->where('reference_number', $invoice->id)
            ->first();
    @endphp

    <div class="container">
        <div class="receipt-container">
            <div class="receipt">
                {{-- «مبسّطة» لمن لا رقم ضريبي له — كان العنوان واحداً للجميع --}}
                @include('sales::invoices.partials.thermal-org-header', ['title' => \App\Services\Zatca\ZatcaLabel::invoiceTitle($invoice->client)])

                <!-- Invoice To -->
                <div class="invoice-to">
                    <p class="mb-0">فاتورة الى:
                        {{ $invoice->client->trade_name ?? $invoice->client->first_name . ' ' . $invoice->client->last_name }}
                    </p>
                    <p class="mb-0">{{ $invoice->client->street1 ?? 'غير متوفر' }}</p>
                    <h1 class="mb-0"> {{ $invoice->client->code ?? 'غير متوفر' }}</h1>
                    <p class="mb-0">الرقم الضريبي: {{ $invoice->client->tax_number ?? 'غير متوفر' }}</p>
                     @if ($invoice->client->phone)
                        <p class="mb-0">رقم جوال العميل: {{ $invoice->client->phone }}</p>
                    @endif
                </div>

                <!-- Invoice Details -->
                <div class="invoice-details">
                    <div class="summary-row">
                        <span>رقم الفاتورة:</span>
                        <span>{{ str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div class="summary-row">
                        <span>تاريخ الفاتورة:</span>
                        <span>{{ \App\Services\Zatca\ZatcaLabel::issuedAt($invoice) ?: 'غير متوفر' }}</span>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="invoice-items">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="35%">المنتج</th>
                                <th width="10%">الكمية</th>
                                <th width="15%">السعر</th>
                                <th width="10%">الضريبة</th>
                                <th width="25%">المجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $index => $item)
                                @php
                                    $unit_price = $item->unit_price;
                                    $quantity = $item->quantity;
                                    $subtotal_item = $unit_price * $quantity;
                                    $discount_item = $item->discount ?? 0;
                                    $net_item = $subtotal_item - $discount_item;
                                    // بنسبة البند المضبوطة: كانت 15٪ مكتوبةً هنا،
                                    // فبندٌ معفى أو بنسبةٍ أخرى يُطبَع بضريبةٍ ليست عليه
                                    $tax_item = \App\Services\Zatca\ZatcaLabel::lineTax($item);
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td style="text-align: right;">{{ $item->item }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->unit_price, 2) }}</td>
                                    <td>{{ number_format($tax_item, 2) }}</td>
                                    <td>{{ number_format($item->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Invoice Summary -->
                <div class="invoice-summary">
                    <div class="summary-row">
                        <span>المجموع الفرعي:</span>
                        <span>{{ number_format($invoice->subtotal ?? 0, 2) }} ر.س</span>
                    </div>

                    @if ($invoice->discount_amount > 0)
                        <div class="summary-row">
                            <span>الخصم:</span>
                            <span>{{ number_format($invoice->discount_amount, 2) }} ر.س</span>
                        </div>
                    @endif

                    <div class="summary-row">
                        <span>ضريبة القيمة المضافة (15%):</span>
                        <span>{{ number_format($invoice->tax_total ?? 0, 2) }} ر.س</span>
                    </div>

                    @if ($invoice->shipping_cost > 0)
                        <div class="summary-row">
                            <span>تكلفة الشحن:</span>
                            <span>{{ number_format($invoice->shipping_cost, 2) }} ر.س</span>
                        </div>
                    @endif

                    @if ($invoice->adjustment_value > 0)
                        <div class="summary-row">
                            <span>{{ $invoice->adjustment_label ?? 'تسوية' }}:</span>
                            <span>
                                {{ $invoice->adjustment_type === 'discount' ? '-' : '+' }}
                                {{ number_format($invoice->adjustment_value, 2) }} ر.س
                            </span>
                        </div>
                    @endif

                    <div class="summary-row">
                        <span>الإجمالي شامل الضريبة:</span>
                        <span>{{ number_format($invoice->grand_total, 2) }} ر.س</span>
                    </div>

                    @if ($invoice->advance_payment > 0)
                        <div class="summary-row" id="advance-payment-row">
                            <span>الدفعة المقدمة:</span>
                            <span id="advance-payment-amount">{{ number_format($invoice->advance_payment, 2) }} ر.س</span>
                        </div>
                    @endif

                    @if ($returnedInvoice)
                        <div class="summary-row">
                            <span>مرتجع:</span>
                            <span>{{ number_format($invoice->returned_payment, 2) }} ر.س</span>
                        </div>
                    @endif

                    <div class="summary-row">
                        <span>المبلغ المستحق:</span>
                        <span id="due-value-amount">{{ number_format($invoice->due_value, 2) }} ر.س</span>
                    </div>
                </div>

                <!-- Signature Section -->
                <div class="signature">
                    <!-- مكان عرض التواقيع -->
                    <div class="signature-history" style="margin-top: 20px;">
                        @foreach ($invoice->signatures as $signature)
                            <div class="signature-item">
                                <div><strong>الاسم:</strong> {{ $signature->signer_name }}</div>
                                @if ($signature->signer_role)
                                    <div><strong>الصفة:</strong> {{ $signature->signer_role }}</div>
                                @endif
                                @if ($signature->amount_paid)
                                    <div><strong>المبلغ المدفوع:</strong>
                                        {{ number_format($signature->amount_paid, 2) }} ريال</div>
                                @endif
                                <img src="{{ $signature->signature_data }}"
                                    style="max-width: 100%; height: auto; margin-top: 5px;">
                            </div>
                        @endforeach
                    </div>
                </div>



                <div class="signature">
                    <p>الاسم: ________________</p>
                    <p>التوقيع: _______________</p>
                    <p class="thank-you">شكراً لتعاملكم معنا</p>
                </div>

                <div class="qr-code">
                    {!! $qrCodeSvg !!}
                </div>
            </div>
        </div>
    </div>

    <script>
        toastr.success('تم التحميل بنجاح');
    </script>

    <!-- مكتبات JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /* المعاينة داخل الشاشة تُحمّل هذا القالب في إطارٍ بـ`?embed=1`.
           كانت الطباعة التلقائية لا تسأل عن الوسيط، ففتحُ شاشة العرض
           وحده يفتح حوار الطباعة. و`onbeforeunload` تطبع مرّةً أخرى عند
           مغادرة الصفحة. الطباعة الآن للفتح المباشر وحده. */
        @unless (request()->boolean('embed'))
            window.onload = function () {
                setTimeout(() => {
                    window.print();
                }, 500);
            };
        @endunless
    </script>
</body>

</html>
