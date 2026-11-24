{{--
    فاتورة مشتريات المتجر — نسخة الطابعة الحرارية (80 مم).

    التخطيط نفسه الذي تطبع به وحدة المشتريات فاتورة الشراء
    (`purchases::purchases.invoices_purchase.thermal`) — الترويسة من «معلومات
    الحساب» عبر `purchases::partials.print-header`، ثم المورد، فبيانات
    المستند، فالبنود، فالإجماليات، فالتوقيع — فلا تخرج فاتورة المتجر بورقةٍ
    تخالف ورقة المشتريات.

    ما يخصّ المتجر:
      • المورد بحقول البطاقة الموحّدة نفسها وبترتيبها (الاسم · رقم المورد ·
        الرقم الضريبي · السجل التجاري · الهاتف/الجوال · العنوان) — الرصيد لا
        يُطبع: الورقة تذهب إلى المورد وتُحفظ، والرصيد يتغيّر بعدها.
      • التاريخ تاريخُ الفاتورة (`date`) لا لحظة إدخالها (`created_at`):
        فاتورةٌ أُدخلت اليوم بتاريخ الأمس تُطبع بتاريخ الأمس.
      • المبالغ بعملة الفاتورة المحفوظة معها، لا برمز العملة الافتراضية الحالي.

    بطاقة المورد هنا مكتوبةٌ سطوراً لا جدولاً: ورقة 80 مم لا تسع جدول البطاقة
    بأعمدته الأربعة — لكن الحقول هي نفسها وبالترتيب نفسه.
--}}
@php
    $supplier = $purchaseInvoice->supplier;
    $isReturn = $purchaseInvoice->isPurchaseReturn();
    $docTitle = $isReturn ? 'مرتجع شراء' : __('purchase_cycle.documents.purchase_invoice');
    $currency = $purchaseInvoice->currency ?: \App\Support\CurrencySettings::code();
    $invoiceDate = \App\Support\LanguageSettings::formatDate($purchaseInvoice->date ?? $purchaseInvoice->created_at);
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $docTitle }} #{{ $purchaseInvoice->code }}</title>
    <style>
        /* أنماط الصفحة الرئيسية - جميع النصوص عريضة */
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
            background: white;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-size: 14px !important; /* زيادة حجم الخط العام */
            font-weight: bold !important;
        }

        .invoice-main-container {
            width: 80mm;
            padding: 8px; /* زيادة الحشوة قليلاً */
            margin: 0 auto;
            text-align: center;
        }

        /* أنماط الرأس */
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #333;
        }

        .header h1 {
            margin: 0;
            padding: 0;
            font-size: 20px; /* زيادة حجم العنوان الرئيسي */
            font-weight: bold;
        }

        .header h2 {
            margin: 5px 0;
            font-size: 16px; /* زيادة حجم النص الثانوي */
        }

        .header p, .header h4 {
            margin: 4px 0;
            font-size: 14px; /* زيادة حجم النص */
        }

        /* أنماط معلومات المورد */
        .supplier-info {
            margin: 10px 0;
            text-align: right;
            padding-bottom: 8px;
            border-bottom: 1px dashed #333;
        }

        .supplier-info h3 {
            margin: 8px 0;
            font-size: 16px;
            font-weight: bold;
        }

        .supplier-info p {
            margin: 5px 0;
            font-size: 14px;
        }

        .invoice-meta {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #ccc;
            font-size: 14px;
        }

        /* أنماط جدول العناصر */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px auto;
            font-size: 14px; /* زيادة حجم خط الجدول */
        }

        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            padding: 8px 5px; /* زيادة الحشوة */
            border-bottom: 1px solid #333;
            text-align: center;
        }

        .items-table td {
            padding: 8px 5px;
            border-bottom: 1px dashed #ddd;
            text-align: center;
            font-weight: bold;
        }

        /* أنماط قسم المجموع */
        .total-section {
            margin: 15px auto 0;
            padding-top: 8px;
            border-top: 1px dashed #333;
            width: 100%;
            font-size: 14px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 6px 0;
            padding: 4px 0;
        }

        .total-row:last-child {
            border-top: 1px dashed #333;
            padding-top: 8px;
        }

        /* أنماط التوقيع والباركود */
        .signature {
            margin: 15px auto 0;
            padding-top: 10px;
            border-top: 1px dashed #333;
            width: 90%;
            text-align: center;
            font-size: 14px;
        }

        .barcode {
            text-align: center;
            margin: 10px auto;
            padding: 8px 0;
        }

        .thank-you {
            font-style: italic;
            margin-top: 5px;
            font-size: 14px;
        }

        /* أنماط الطباعة */
        @media print {
            body {
                display: block !important;
                width: 80mm !important;
                font-size: 14px !important;
                background: white !important;
                font-weight: bold !important;
            }

            .invoice-main-container {
                box-shadow: none !important;
                margin: 0 auto !important;
                padding: 5px !important;
            }

            .barcode svg {
                width: 70px !important;
                height: 70px !important;
            }
        }
    </style>
</head>

<body>
    <div class="invoice-main-container">
        <div class="invoice-content">
            <!-- رأس الفاتورة -->
            <div class="header">
                <h1>{{ $docTitle }}</h1>
                {{-- الشعار يُخفى في العرض الحراري الضيّق --}}
                @include('purchases::partials.print-header', ['showLogo' => false])
            </div>

            <!-- معلومات المورد -->
            <div class="supplier-info">
                @php
                    $supplierPhones = array_values(array_filter([optional($supplier)->mobile, optional($supplier)->phone]));
                    $supplierAddress = trim((string) optional($supplier)->full_address);
                @endphp
                <h3>المورد: {{ optional($supplier)->name ?? 'غير محدد' }}</h3>
                <p>رقم المورد: {{ optional($supplier)->number_suply ?: '—' }}</p>
                <p>الرقم الضريبي: {{ optional($supplier)->tax_number ?: '—' }}</p>
                <p>السجل التجاري: {{ optional($supplier)->commercial_registration ?: '—' }}</p>
                <p>الهاتف / الجوال: {{ $supplierPhones ? implode(' / ', $supplierPhones) : '—' }}</p>
                <p>العنوان: {{ $supplierAddress !== '' ? $supplierAddress : '—' }}</p>

                <div class="invoice-meta">
                    <p>رقم الفاتورة: {{ $purchaseInvoice->code }}</p>
                    <p>تاريخ الفاتورة: {{ $invoiceDate }}</p>
                </div>
            </div>

            <!-- جدول العناصر -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="40%">المنتج</th>
                        <th width="15%">الكمية</th>
                        <th width="20%">السعر</th>
                        @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseInvoice->total_discount > 0)
                        <th width="10%">الخصم</th>
                        @endif
                        <th width="25%">المجموع</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseInvoice->items as $item)
                    <tr>
                        <td style="text-align: right;">{{ optional($item->product)->name ?? $item->item }}@if($item->unit_name) - {{ $item->unit_name }}@endif</td>
                        <td>{{ number_format($item->quantity, 0) }}</td>
                        <td>{{ \App\Support\CurrencySettings::number($item->unit_price) }}</td>
                        @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseInvoice->total_discount > 0)
                        <td>{{ \App\Support\CurrencySettings::number($item->discount) }}</td>
                        @endif
                        <td>{{ \App\Support\CurrencySettings::number($item->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- قسم المجموع -->
            <div class="total-section">
                <div class="total-row">
                    <span>المجموع الكلي:</span>
                    <span>{{ \App\Support\CurrencySettings::number($purchaseInvoice->grand_total) }} {{ $currency }}</span>
                </div>

                @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseInvoice->total_discount > 0)
                <div class="total-row">
                    <span>الخصم:</span>
                    <span>{{ \App\Support\CurrencySettings::number($purchaseInvoice->total_discount) }} {{ $currency }}</span>
                </div>
                @endif

                @if($purchaseInvoice->shipping_cost > 0)
                <div class="total-row">
                    <span>تكلفة الشحن:</span>
                    <span>{{ \App\Support\CurrencySettings::number($purchaseInvoice->shipping_cost) }} {{ $currency }}</span>
                </div>
                @endif

                @if($purchaseInvoice->advance_payment > 0)
                <div class="total-row">
                    <span>الدفعة المقدمة:</span>
                    <span>{{ \App\Support\CurrencySettings::number($purchaseInvoice->advance_payment) }} {{ $currency }}</span>
                </div>
                @endif

                <div class="total-row">
                    <span>المبلغ المستحق:</span>
                    <span>{{ \App\Support\CurrencySettings::number($purchaseInvoice->due_value) }} {{ $currency }}</span>
                </div>
            </div>

            <!-- الباركود والتوقيع -->
            <div class="signature">
                <p>الاسم: ________________</p>
                <p>التوقيع: _______________</p>
                <p class="thank-you">شكراً لتعاملكم معنا</p>
            </div>
        </div>
    </div>

    <script>
        // طباعة تلقائية عند تحميل الصفحة
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>