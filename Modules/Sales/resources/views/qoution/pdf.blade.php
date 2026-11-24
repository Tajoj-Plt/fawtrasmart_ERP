<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض سعر #{{ $quote->quote_number }}</title>
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

            .qr-code svg {
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
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="receipt-container">
            <div class="receipt">
                {{-- كانت هذه الأسطر الثلاثة اسمَ منشأةٍ بعينها وعنوانها
                     ورقم مسؤولها مكتوبةً في القالب — فكل عرضٍ سعرٍ يطبعه
                     أيُّ عميلٍ لهذا النظام يخرج باسم منشأةٍ أخرى. الآن من
                     «معلومات المنشأة» في الإعدادات. --}}
                <div class="receipt-header">

                    <h1 class="receipt-title">{{ $doc['t']('doc.singular') }}</h1>
                    <p class="mb-0">{{ \App\Support\BusinessInfo::name() }}</p>
                    @if (\App\Support\BusinessInfo::address())
                        <p class="mb-0">{{ \App\Support\BusinessInfo::address() }}</p>
                    @endif
                    @if (\App\Support\BusinessInfo::phone())
                        <p>{{ \App\Support\BusinessInfo::phone() }}</p>
                    @endif

                    <h1 class="receipt-title">عرض سعر</h1>
                    <p class="mb-0">مؤسسة اعمال خاصة للتجارة</p>
                    <p class="mb-0">{{ \App\Support\OrganizationInfo::address() }}</p>
                    <p>رقم المسؤول: {{ \App\Support\OrganizationInfo::phone() }}</p>

                </div>

                <div class="invoice-to">
                    <p class="mb-0">عرض سعر الى:
                        {{ $quote->client->trade_name ?? $quote->client->first_name . ' ' . $quote->client->last_name }}
                    </p>
                    <p class="mb-0">{{ $quote->client->street1 ?? 'غير متوفر' }}</p>
                    <p class="mb-0">الرقم الضريبي: {{ $quote->client->tax_number ?? 'غير متوفر' }}</p>
                    @if ($quote->client->mobile)
                        <p class="mb-0">رقم الجوال: {{ $quote->client->mobile }}</p>
                    @endif
                </div>

                <div class="invoice-details">
                    <div class="summary-row">
                        <span>رقم عرض السعر:</span>
                        {{-- بلا `str_pad`: الطول والبادئة يضبطهما
                             «الترقيم المتسلسل»، وحشوٌ فوقهما يشوّه الرقم --}}
                        <span>{{ $quote->quote_number ?: $quote->id }}</span>
                    </div>
                    <div class="summary-row">
                        <span>تاريخ العرض:</span>
                        <span>{{ \App\Support\LanguageSettings::formatDate($quote->quote_date) }}</span>
                    </div>
                </div>

                <div class="invoice-items">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="40%">البند</th>
                                <th width="15%">الكمية</th>
                                <th width="20%">السعر</th>
                                <th width="20%">المجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quote->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td style="text-align: right;">{{ $item->item }}</td>
                                <td>{{ \App\Support\CurrencySettings::quantity($item->quantity) }}</td>
                                <td>{{ \App\Support\CurrencySettings::number($item->unit_price) }}</td>
                                <td>{{ \App\Support\CurrencySettings::number($item->total) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="invoice-summary">
                    {{-- كان الرمز يُقرأ من `$account_setting->currency` —
                         إعدادُ **حسابٍ شخصي** لا إعدادُ منشأة — ثم تُستبدل
                         به صورةُ ريالٍ ثابتة. فمن ضبط عملة النظام على
                         غيره رأى فاتورته بالريال. المتغيّران صارا ميّتين
                         بعد أن مرّت المبالغ على `CurrencySettings`. --}}

                    @if(($quote->total_discount ?? 0) > 0)
                    <div class="summary-row">
                        <span>الخصم:</span>
                        <span>{{ \App\Support\CurrencySettings::format($quote->total_discount) }}</span>
                    </div>
                    @endif

                    @if(($quote->shipping_cost ?? 0) > 0)
                    <div class="summary-row">
                        <span>تكلفة الشحن:</span>
                        <span>{{ \App\Support\CurrencySettings::format($quote->shipping_cost) }}</span>
                    </div>
                    @endif

                    {{-- ══ الضريبة: سطرٌ لكلّ ضريبةٍ لا لكلّ بند ══

                         كان الجدول `taxs_invoice` يُطبَع صفّاً صفّاً — وفيه
                         **صفٌّ لكل بندٍ لكل ضريبة**. فعرضٌ بثلاثة بنود
                         بالنسبة نفسِها يخرج بثلاثة أسطر:

                             القيمة المضافة (15%): 28.50
                             القيمة المضافة (15%): 9.00
                             القيمة المضافة (15%): 54.00

                         وهي ضريبةٌ واحدة مقسَّمة على بنودها، لا ثلاث ضرائب.
                         والفاتورةُ التي يتحوّل إليها العرض تطبع سطراً واحداً
                         (`tax_total`)، فالمستندان يختلفان في الشكل وهما
                         بالمبلغ نفسِه.

                         والأسوأ أنّ **ضريبة الشحن لم تكن تُطبَع أصلاً**: لا
                         صفَّ لها في `taxs_invoice` (تُحسب على تكلفة الشحن لا
                         على بند)، فتدخل «المجموع الكلي» ولا تظهر في سطر —
                         فلا تستقيم جمعةُ الأسطر مع الإجمالي المطبوع.

                         الآن: تُجمَع أسطر البنود بالاسم والنسبة، ثمّ سطرٌ
                         مستقلّ لضريبة الشحن باسمها كما تُسمّى في القيد. --}}
                    @php
                        $taxLines = $TaxsInvoice
                            ->groupBy(fn ($row) => $row->name.'|'.$row->rate)
                            ->map(fn ($rows) => [
                                'name' => $rows->first()->name,
                                'rate' => $rows->first()->rate,
                                'value' => $rows->sum('value'),
                            ])
                            ->filter(fn ($line) => abs((float) $line['value']) >= 0.005);
                    @endphp

                    @foreach($taxLines as $taxLine)
                        <div class="summary-row">
                            <span>{{ $taxLine['name'] }} ({{ $taxLine['rate'] }}%):</span>
                            <span>{{ \App\Support\CurrencySettings::format($taxLine['value']) }}</span>
                        </div>
                    @endforeach

                    @if(($quote->shipping_tax ?? 0) > 0)
                    <div class="summary-row">
                        <span>ضريبة شحن مبيعات:</span>
                        <span>{{ \App\Support\CurrencySettings::format($quote->shipping_tax) }}</span>
                    </div>
                    @endif

                    <div class="summary-row">
                        <span>المجموع الكلي:</span>
                        <span>{{ \App\Support\CurrencySettings::format($quote->grand_total ?? 0) }}</span>
                    </div>
                </div>

                <div class="qr-code">
                    <canvas id="qrcode"></canvas>
                </div>

                <div class="signature">
                    <p>الاسم: ________________</p>
                    <p>التوقيع: _______________</p>
                    <p class="thank-you">شكراً لتعاملكم معنا</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        /* المحتوى من المتحكّم (`quotePayload()`) لا مبنيّاً هنا: كان
           يُبنى في ثلاثة مواضع بثلاث صيغ، وأحدها يرسل اسم العميل والمبلغ
           إلى `api.qrserver.com` — خدمةٍ خارجية — عند كل تنزيل. */
        const quoteData = @json($qrPayload ?? '');

        QRCode.toCanvas(document.getElementById('qrcode'), quoteData, {
            width: 150,
            margin: 1
        }, function (error) {
            if (error) console.error(error);
            console.log('QR Code generated successfully!');
        });

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
