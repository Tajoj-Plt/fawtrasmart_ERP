<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة مرتجع #{{ $return_invoice->id }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap');

        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f0f0f0;
            padding: 20px;
            direction: rtl;
            font-weight: bold;
        }

        .receipt-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }

        .receipt {
            width: 80mm;
            max-width: 100%;
            background-color: white;
            padding: 10px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }

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

        .invoice-to {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        .invoice-details {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        .return-reason {
            margin-bottom: 10px;
            padding: 8px;
            background-color: #fff3cd;
            border-radius: 4px;
            border-bottom: 1px dashed #ccc;
        }

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

        .qr-code {
            margin: 15px 0;
            text-align: center;
        }

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

            .qr-code img {
                width: 70px !important;
                height: 70px !important;
            }
        }

        @media (max-width: 576px) {
            .receipt {
                width: 100%;
            }
        }
    </style>

</head>
<body>
    <div class="container">
        <div class="receipt-container">
            <div class="receipt">
                <!-- Receipt Header -->
                <div class="receipt-header">
                    <h1 class="receipt-title">فاتورة مرتجع</h1>
                    <p class="mb-0">{{ \App\Support\OrganizationInfo::name() }}</p>
                    @if (\App\Support\OrganizationInfo::taxNumber() !== '')
                        <p class="mb-0">الرقم الضريبي: {{ \App\Support\OrganizationInfo::taxNumber() }}</p>
                    @endif
                    <p class="mb-0">{{ \App\Support\OrganizationInfo::address() }}</p>
                    <p>رقم المسؤول: {{ \App\Support\OrganizationInfo::phone() }}</p>
                </div>

                <!-- Invoice To -->
                <div class="invoice-to">
                    <p class="mb-0">العميل: {{ $return_invoice->client->trade_name ?? $return_invoice->client->first_name . ' ' . $return_invoice->client->last_name }}</p>
                    <p class="mb-0">{{ $return_invoice->client->street1 ?? 'غير متوفر' }}</p>
                    <h1 class="mb-0">{{ $return_invoice->client->code ?? 'غير متوفر' }}</h1>
                    <p class="mb-0">الرقم الضريبي: {{ $return_invoice->client->tax_number ?? 'غير متوفر' }}</p>
                    @if($return_invoice->client->mobile)
                        <p class="mb-0">الهاتف: {{ $return_invoice->client->mobile }}</p>
                    @endif
                </div>

                <!-- Invoice Details -->
                <div class="invoice-details">
                    <div class="summary-row">
                        <span>رقم المرتجع:</span>
                        <span>{{ str_pad($return_invoice->id, 5, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div class="summary-row">
                        <span>التاريخ:</span>
                        <span>{{ \App\Services\Zatca\ZatcaLabel::issuedAt($return_invoice, 'Y/m/d H:i') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>الفاتورة الأصلية:</span>
                        <span>#{{ str_pad($return_invoice->reference_number ?? 0, 5, '0', STR_PAD_LEFT) }}</span>
                    </div>
                </div>

                <!-- Return Reason -->
                <div class="return-reason">
                    <strong>سبب المرتجع:</strong> {{ $return_invoice->return_reason ?? 'لم يتم تحديد سبب' }}
                </div>

                <!-- Invoice Items -->
                <div class="invoice-items">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="35%">الصنف</th>
                                <th width="12%">الكمية</th>
                                <th width="13%">السعر</th>
                                <th width="15%">الخصم</th>
                                <th width="20%">المجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($return_invoice->items as $item)
                            @php
                                // جلب اسم الوحدة من الحقل المحفوظ
                                $displayName = $item->item;
                                if ($item->unit_name && !str_contains($displayName, '(')) {
                                    $displayName = $item->item . ' (' . $item->unit_name . ')';
                                }
                                
                                $itemSubtotal = $item->quantity * $item->unit_price;
                                $itemAfterDiscount = $itemSubtotal - $item->discount;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td style="text-align: right;">{{ $displayName }}</td>
                                <td>{{ number_format($item->quantity, 0) }}</td>
                                <td>{{ number_format($item->unit_price, 2) }}</td>
                                <td>{{ number_format($item->discount, 2) }}</td>
                                <td>{{ number_format($itemAfterDiscount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Invoice Summary -->
                <div class="invoice-summary">
                    {{-- كانت هذه اللوحة تُعيد حساب المجاميع من البنود وحدها
                         (تجمع `item->discount` و`tax_1`/`tax_2`)، فتُسقط
                         بصمت خصم المستند الإضافي (`discount_amount`) وتكلفة
                         الشحن (`shipping_cost`) كلَّما وُجدا — فيخرج المرتجع
                         بلا سطر شحنٍ إطلاقاً ومجموعٍ لا يطابق ما حُفظ عند
                         الإنشاء. صارت تعرض القيم المحفوظة نفسها كما تفعل
                         شاشة عرض المرتجع وقالب الإشعار الدائن. --}}
                    <div class="summary-row">
                        <span>المجموع الفرعي:</span>
                        <span>{{ number_format($return_invoice->subtotal ?? 0, 2) }} ر.س</span>
                    </div>
                    @if(($return_invoice->discount_amount ?? 0) > 0)
                    <div class="summary-row">
                        <span>الخصم:</span>
                        <span style="color: #dc3545;">-{{ number_format($return_invoice->discount_amount, 2) }} ر.س</span>
                    </div>
                    @endif
                    @if(($return_invoice->shipping_cost ?? 0) > 0)
                    <div class="summary-row">
                        <span>الشحن:</span>
                        <span>+{{ number_format($return_invoice->shipping_cost, 2) }} ر.س</span>
                    </div>
                    @endif
                    @if(($return_invoice->tax_total ?? 0) > 0)
                    <div class="summary-row">
                        <span>الضريبة:</span>
                        <span>+{{ number_format($return_invoice->tax_total, 2) }} ر.س</span>
                    </div>
                    @endif
                    <div class="summary-row" style="border-top: 2px solid #333; padding-top: 8px; margin-top: 8px;">
                        <span><strong>إجمالي المرتجع:</strong></span>
                        <span><strong>{{ number_format($return_invoice->grand_total ?? 0, 2) }} ر.س</strong></span>
                    </div>
                </div>

                <!-- QR Code -->
                <div class="qr-code">
                    {{-- رمز الهيئة مرسوماً في الخادم. كان يُرسَم في المتصفّح بمكتبةٍ
                         من شبكةٍ خارجية، ومحتواه نصٌّ عربيّ فيه رقم المرتجع واسم
                         العميل — لا يقرؤه تطبيق الهيئة، ولا يظهر بلا إنترنت --}}
                    {!! \App\Services\Zatca\ZatcaQr::image(\App\Services\Zatca\ZatcaQr::forInvoice($return_invoice)) !!}
                </div>

                <!-- Signature -->
                <div class="signature">
                    <p>توقيع العميل: _______________</p>
                    <p>ختم المحل: _______________</p>
                    <p class="thank-you">شكراً لتعاملكم معنا</p>
                </div>
            </div>
        </div>
    </div>

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