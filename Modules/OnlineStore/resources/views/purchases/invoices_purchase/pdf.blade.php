{{--
    فاتورة مشتريات المتجر — نسخة PDF (تُصيَّر عبر TCPDF `writeHTML`).

    الأقسام بترتيب مطبوعة المشتريات: الترويسة ← المورد ← بيانات المستند ←
    البنود ← ملخّص المبالغ ← التوقيعات. البنية جداول وأنماط بسيطة لا
    Flexbox/Grid: محرّك TCPDF لا يفهم تخطيطات CSS الحديثة، والجدول هو ما
    يُترجَم فعلاً إلى صفحة منسّقة — ولهذا لم تُنسخ هنا ورقة `pdf` في وحدة
    المشتريات المبنيّة على flex.

    المورد ببطاقته الموحّدة (نفس حقول النموذج وصفحة الفاتورة)، والتاريخ تاريخ
    الفاتورة (`date`) لا لحظة إدخالها، والمبالغ بعملة الفاتورة المحفوظة معها.
--}}
@php
    $orgName = \App\Support\OrganizationInfo::name();
    $orgAddress = \App\Support\OrganizationInfo::address();
    $supplier = $purchaseInvoice->supplier;
    $isReturn = $purchaseInvoice->isPurchaseReturn();
    $docTitle = $isReturn ? 'مرتجع شراء' : 'فاتورة مشتريات';
    $currency = $purchaseInvoice->currency ?: \App\Support\CurrencySettings::code();
    $invoiceDate = \App\Support\LanguageSettings::formatDate($purchaseInvoice->date ?? $purchaseInvoice->created_at);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>{{ $docTitle }} {{ $purchaseInvoice->code }}</title>
    <style>
        body {
            direction: rtl;
            text-align: right;
            font-family: 'aealarabiya', Arial, sans-serif;
            color: #000;
        }

        .doc-head {
            text-align: center;
            border: 2px solid #000;
            padding: 10px;
            margin-bottom: 16px;
        }

        .doc-head h1 {
            margin: 0 0 4px;
            font-size: 18px;
        }

        .doc-head h2 {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: bold;
        }

        .doc-head p {
            margin: 2px 0;
            font-size: 11px;
            color: #333;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .info-grid td {
            padding: 6px;
            vertical-align: top;
            font-size: 11px;
        }

        .info-label {
            font-weight: bold;
            width: 20%;
            color: #333;
        }

        .info-value {
            border-bottom: 1px dotted #666;
            width: 30%;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
        }

        .amount-box {
            border: 2px solid #000;
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px;
            font-weight: bold;
        }

        .amount-box td {
            padding: 8px 10px;
        }

        .amount-box .amount-box__value {
            text-align: left;
            font-size: 16px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 11px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }

        .data-table th {
            background-color: #f0f0f0;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 36px;
            font-size: 11px;
        }

        .signatures td {
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 6px;
            width: 33%;
        }

        .doc-foot {
            margin-top: 20px;
            padding-top: 6px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 9px;
            color: #555;
        }
    </style>
</head>

<body>
    {{-- الشرطة العادية «-» لا الطويلة في هذا الملف: خطّ TCPDF العربي (aealarabiya)
         بلا محرف الشرطة الطويلة، فتُطبع مربّعاً فارغاً --}}
    <div class="doc-head">
        <h1>{{ $orgName }}</h1>
        <h2>{{ $docTitle }}</h2>
        @if ($orgAddress)<p>{{ $orgAddress }}</p>@endif
        <p>رقم الفاتورة: {{ $purchaseInvoice->code }} - بتاريخ {{ $invoiceDate }}</p>
    </div>

    {{-- بطاقة المورد الموحّدة — جدولٌ بسيط يفهمه TCPDF --}}
    @include('onlinestore::suppliers.partials.supplier-card', ['supplier' => $supplier, 'print' => true, 'title' => 'المورد'])

    <h3 class="section-title">بيانات المستند</h3>
    <table class="info-grid">
        <tr>
            <td class="info-label">رقم الفاتورة:</td>
            <td class="info-value">{{ $purchaseInvoice->code }}</td>
            <td class="info-label">التاريخ:</td>
            <td class="info-value">{{ $invoiceDate }}</td>
        </tr>
        <tr>
            <td class="info-label">طريقة الدفع:</td>
            <td class="info-value">
                @if ($purchaseInvoice->payment_method == 1)
                    نقداً
                @elseif ($purchaseInvoice->payment_method == 2)
                    تحويل بنكي
                @elseif ($purchaseInvoice->payment_method == 3)
                    شيك
                @else
                    غير محدد
                @endif
            </td>
            <td class="info-label">حالة الدفع:</td>
            <td class="info-value">
                @if ($purchaseInvoice->payment_status == 'paid')
                    مدفوعة بالكامل
                @elseif ($purchaseInvoice->payment_status == 'partially_paid')
                    مدفوعة جزئياً
                @elseif ($purchaseInvoice->payment_status == 'unpaid')
                    غير مدفوعة
                @else
                    قيد المراجعة
                @endif
            </td>
        </tr>
    </table>

    <table class="amount-box">
        <tr>
            <td>المجموع الإجمالي (شامل الضريبة)</td>
            <td class="amount-box__value">{{ \App\Support\CurrencySettings::number($purchaseInvoice->grand_total) }} {{ $currency }}</td>
        </tr>
    </table>

    <h3 class="section-title">بنود الفاتورة</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الخصم</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseInvoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align:right">{{ optional($item->product)->name ?? $item->item }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ \App\Support\CurrencySettings::number($item->unit_price) }}</td>
                    <td>{{ $item->discount > 0 ? \App\Support\CurrencySettings::number($item->discount) : '-' }}</td>
                    <td>{{ \App\Support\CurrencySettings::number($item->total) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">لا توجد عناصر</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h3 class="section-title">ملخّص المبالغ</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>البند</th>
                <th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>المجموع الفرعي</td>
                <td>{{ \App\Support\CurrencySettings::number($purchaseInvoice->subtotal ?? 0) }} {{ $currency }}</td>
            </tr>
            @if ($purchaseInvoice->total_discount > 0)
                <tr>
                    <td>إجمالي الخصم</td>
                    <td>- {{ \App\Support\CurrencySettings::number($purchaseInvoice->total_discount) }} {{ $currency }}</td>
                </tr>
            @endif
            @if ($purchaseInvoice->total_tax > 0)
                <tr>
                    <td>إجمالي الضريبة</td>
                    <td>{{ \App\Support\CurrencySettings::number($purchaseInvoice->total_tax) }} {{ $currency }}</td>
                </tr>
            @endif
            @if ($purchaseInvoice->shipping_cost > 0)
                <tr>
                    <td>تكلفة الشحن</td>
                    <td>{{ \App\Support\CurrencySettings::number($purchaseInvoice->shipping_cost) }} {{ $currency }}</td>
                </tr>
            @endif
            @if ($purchaseInvoice->advance_payment > 0)
                <tr>
                    <td>الدفعة المقدمة</td>
                    <td>{{ \App\Support\CurrencySettings::number($purchaseInvoice->advance_payment) }} {{ $currency }}</td>
                </tr>
            @endif
            <tr>
                <td><strong>الإجمالي شامل الضريبة</strong></td>
                <td><strong>{{ \App\Support\CurrencySettings::number($purchaseInvoice->grand_total) }} {{ $currency }}</strong></td>
            </tr>
            <tr>
                <td><strong>المبلغ المستحق</strong></td>
                <td><strong>{{ \App\Support\CurrencySettings::number($purchaseInvoice->due_value) }} {{ $currency }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if ($purchaseInvoice->notes)
        <h3 class="section-title">ملاحظات</h3>
        <p style="font-size: 11px;">{{ $purchaseInvoice->notes }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>توقيع المورد</td>
            <td>توقيع المستلم</td>
            <td>ختم الشركة</td>
        </tr>
    </table>

    <div class="doc-foot">© {{ date('Y') }} {{ $orgName }} - شكراً لتعاملكم معنا</div>
</body>

</html>
