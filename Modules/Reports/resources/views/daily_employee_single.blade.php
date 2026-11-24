<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>التقرير اليومي - {{ $user->name }}</title>
    <style>
        @font-face {
            font-family: 'DejaVu Sans';
            font-style: normal;
            font-weight: normal;
            src: url('{{ storage_path('fonts/dejavu-sans/DejaVuSans.ttf') }}') format('truetype');
        }

        * { margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            font-size: 8pt;
            margin: 0;
            padding: 0;
            line-height: 1.5;
            color: #1a1a2e;
            background: #ffffff;
        }

        /* ════════════════════════════════════════════
           الهيدر — شريط أنيق بتدرج كحلي
           ════════════════════════════════════════════ */
        .report-header {
            background-color: #1a1a2e;
            color: #ffffff;
            padding: 7mm 6mm 5mm 6mm;
            position: relative;
        }
        .report-header::after {
            content: '';
            display: block;
            height: 3pt;
            background-color: #e2b04a;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }
        .header-row {
            width: 100%;
            border-collapse: collapse;
            direction: rtl;
        }
        .header-row td {
            vertical-align: middle;
            padding: 0;
        }
        .header-title {
            font-size: 18pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #e2b04a;
        }
        .header-sub {
            font-size: 8pt;
            color: #a0a0c0;
            margin-top: 1mm;
        }
        .header-date-box {
            text-align: left;
            background-color: rgba(226, 176, 74, 0.15);
            border: 1pt solid #e2b04a;
            padding: 2.5mm 4mm;
            font-size: 10pt;
            font-weight: bold;
            color: #e2b04a;
            direction: ltr;
        }

        /* ════════════════════════════════════════════
           شريط معلومات الموظف
           ════════════════════════════════════════════ */
        .emp-info {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5pt solid #e8e8ef;
        }
        .emp-info td {
            padding: 2.5mm 4mm;
            font-size: 8.5pt;
        }
        .emp-info .label {
            background-color: #f0f0f8;
            font-weight: bold;
            color: #1a1a2e;
            width: 15%;
            border-left: 1pt solid #e8e8ef;
        }
        .emp-info .value {
            width: 35%;
            color: #444466;
        }

        /* ════════════════════════════════════════════
           بطاقات الملخص — أسلوب عصري بألوان جانبية
           ════════════════════════════════════════════ */
        .summary-wrapper {
            padding: 4mm 4mm 2mm 4mm;
        }
        .summary-cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2.5mm;
            direction: rtl;
        }
        .s-card {
            width: 25%;
            padding: 3mm;
            text-align: center;
            vertical-align: middle;
            border: 1pt solid #e8e8ef;
            background-color: #fafafe;
        }
        .s-card-label {
            font-size: 6.5pt;
            color: #8888aa;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 1mm;
        }
        .s-card-amount {
            font-size: 12pt;
            font-weight: bold;
            direction: ltr;
        }
        .clr-sales { border-right: 4pt solid #3b82f6; }
        .clr-sales .s-card-amount { color: #3b82f6; }
        .clr-returns { border-right: 4pt solid #f97316; }
        .clr-returns .s-card-amount { color: #f97316; }
        .clr-collect { border-right: 4pt solid #10b981; }
        .clr-collect .s-card-amount { color: #10b981; }
        .clr-expense { border-right: 4pt solid #ef4444; }
        .clr-expense .s-card-amount { color: #ef4444; }

        /* ════════════════════════════════════════════
           شريط الصافي
           ════════════════════════════════════════════ */
        .net-strip {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 3mm 0;
            direction: rtl;
        }
        .net-strip td {
            width: 50%;
            text-align: center;
            padding: 2.5mm 3mm;
            font-size: 8.5pt;
            font-weight: bold;
            background-color: #1a1a2e;
            color: #a0a0c0;
            border: 0.5pt solid #2a2a4e;
        }
        .net-strip .net-amount {
            font-size: 10pt;
            direction: ltr;
            margin-right: 2mm;
        }
        .positive { color: #34d399 !important; }
        .negative { color: #f87171 !important; }

        /* ════════════════════════════════════════════
           رؤوس الأقسام
           ════════════════════════════════════════════ */
        .sec-title {
            padding: 2.5mm 5mm;
            margin: 4mm 0 2mm 0;
            font-size: 10pt;
            font-weight: bold;
            color: #ffffff;
            position: relative;
        }
        .sec-title .count-badge {
            float: left;
            font-size: 7.5pt;
            font-weight: normal;
            background-color: rgba(255,255,255,0.2);
            padding: 0.5pt 6pt;
        }
        .sec-sales    { background-color: #3b82f6; }
        .sec-returns  { background-color: #f97316; }
        .sec-collect  { background-color: #10b981; }
        .sec-expense  { background-color: #ef4444; }
        .sec-stock    { background-color: #8b5cf6; }
        .sec-inv      { background-color: #1a1a2e; }

        /* رأس فرعي */
        .sub-title {
            font-size: 8pt;
            font-weight: bold;
            padding: 1.5mm 4mm;
            margin-bottom: 1.5mm;
        }
        .sub-title-green {
            background-color: #ecfdf5;
            color: #059669;
            border-right: 3pt solid #10b981;
        }
        .sub-title-teal {
            background-color: #f0fdfa;
            color: #0d9488;
            border-right: 3pt solid #14b8a6;
        }

        /* ════════════════════════════════════════════
           الجداول
           ════════════════════════════════════════════ */
        table.tbl {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 7.5pt;
            direction: rtl;
        }
        table.tbl thead th {
            background-color: #f8f8fc;
            color: #1a1a2e;
            padding: 2mm;
            text-align: right;
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            border-bottom: 2pt solid #1a1a2e;
        }
        table.tbl tbody td {
            padding: 2mm;
            border-bottom: 0.5pt solid #efefef;
            text-align: right;
            vertical-align: middle;
        }
        table.tbl tbody tr:nth-child(even) td {
            background-color: #fafaff;
        }
        table.tbl tbody tr:hover td {
            background-color: #f0f0ff;
        }
        table.tbl .sum-row td {
            background-color: #1a1a2e !important;
            color: #e2b04a;
            font-weight: bold;
            font-size: 8pt;
            border-top: 2pt solid #e2b04a;
            border-bottom: none;
            padding: 2.5mm 2mm;
        }

        .cur {
            text-align: left;
            direction: ltr;
            font-family: 'DejaVu Sans', sans-serif;
        }
        .cen { text-align: center; }

        /* ════════════════════════════════════════════
           البادجات
           ════════════════════════════════════════════ */
        .tag {
            display: inline-block;
            padding: 1pt 5pt;
            font-size: 6.5pt;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.2px;
        }
        .tag-green  { background-color: #10b981; }
        .tag-orange { background-color: #f97316; }
        .tag-red    { background-color: #ef4444; }
        .tag-blue   { background-color: #3b82f6; }
        .tag-purple { background-color: #8b5cf6; }
        .tag-gray   { background-color: #6b7280; }

        /* ════════════════════════════════════════════
           كتل تفاصيل الأذونات
           ════════════════════════════════════════════ */
        .permit-card {
            margin-bottom: 3mm;
            border: 1pt solid #e5e7eb;
            page-break-inside: avoid;
        }
        .permit-card-head {
            padding: 2mm 3mm;
            background-color: #faf5ff;
            border-bottom: 1.5pt solid #8b5cf6;
        }
        .permit-card-head .pc-title {
            font-size: 8pt;
            font-weight: bold;
            color: #7c3aed;
        }
        .permit-card-head .pc-meta {
            font-size: 6.5pt;
            color: #6b7280;
            margin-top: 0.5mm;
        }
        table.prod-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        table.prod-tbl th {
            background-color: #7c3aed;
            color: #ffffff;
            padding: 1.5mm 2mm;
            text-align: right;
            font-size: 6.5pt;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        table.prod-tbl td {
            padding: 1.5mm 2mm;
            border-bottom: 0.5pt solid #efefef;
            text-align: right;
        }
        table.prod-tbl tr:nth-child(even) td {
            background-color: #faf5ff;
        }

        /* مؤشر المخزون */
        .stk {
            display: inline-block;
            padding: 0.5pt 3pt;
            font-size: 5.5pt;
            font-weight: bold;
            color: #ffffff;
            margin-right: 1mm;
        }
        .stk-ok    { background-color: #10b981; }
        .stk-low   { background-color: #f97316; }
        .stk-empty { background-color: #ef4444; }

        /* ════════════════════════════════════════════
           قسم رصيد المستودع الحالي
           ════════════════════════════════════════════ */
        .inv-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            direction: rtl;
        }
        .inv-grid thead th {
            background-color: #1a1a2e;
            color: #e2b04a;
            padding: 2mm;
            text-align: right;
            font-size: 7pt;
            text-transform: uppercase;
        }
        .inv-grid tbody td {
            padding: 2mm;
            border-bottom: 0.5pt solid #efefef;
        }
        .inv-grid tbody tr:nth-child(even) td {
            background-color: #fafaff;
        }

        /* ════════════════════════════════════════════
           لا توجد بيانات
           ════════════════════════════════════════════ */
        .empty-state {
            text-align: center;
            color: #9ca3af;
            padding: 5mm;
            border: 1pt dashed #d1d5db;
            background-color: #fafafa;
            margin-bottom: 3mm;
            font-size: 8pt;
        }

        /* ════════════════════════════════════════════
           الفوتر
           ════════════════════════════════════════════ */
        .report-footer {
            text-align: center;
            padding: 3mm 0;
            margin-top: 5mm;
            border-top: 2pt solid #1a1a2e;
            font-size: 7pt;
            color: #9ca3af;
        }
        .report-footer strong {
            color: #e2b04a;
        }

        /* عرض الأعمدة */
        .w5  { width: 5%;  }
        .w8  { width: 8%;  }
        .w10 { width: 10%; }
        .w12 { width: 12%; }
        .w15 { width: 15%; }
        .w18 { width: 18%; }
        .w20 { width: 20%; }
        .w22 { width: 22%; }
        .w25 { width: 25%; }
        .w30 { width: 30%; }
        .w35 { width: 35%; }
        .w60 { width: 60%; }
    </style>
</head>
<body>

{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║               الهيدر الرئيسي                           ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
<div class="report-header">
    <table class="header-row">
        <tr>
            <td style="width:65%;">
                <div class="header-title">التقرير اليومي للموظف</div>
                <div class="header-sub">{{ $user->name }}  —  {{ $user->branch->name ?? '' }}</div>
            </td>
            <td style="width:35%;">
                <div class="header-date-box">{{ $date }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║              بطاقات الملخص المحاسبي                    ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
<div class="summary-wrapper">
    <table class="summary-cards">
        <tr>
            <td class="s-card clr-sales">
                <div class="s-card-label">المبيعات</div>
                <div class="s-card-amount">{{ number_format($accountingSales, 2) }}</div>
            </td>
            <td class="s-card clr-returns">
                <div class="s-card-label">المرتجعات</div>
                <div class="s-card-amount">{{ number_format($accountingReturns, 2) }}</div>
            </td>
            <td class="s-card clr-collect">
                <div class="s-card-label">التحصيلات</div>
                <div class="s-card-amount">{{ number_format($accountingCollections, 2) }}</div>
            </td>
            <td class="s-card clr-expense">
                <div class="s-card-label">المصروفات</div>
                <div class="s-card-amount">{{ number_format($accountingExpenses, 2) }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- شريط الصافي --}}
<table class="net-strip">
    <tr>
        <td>
            صافي المبيعات:
            <span class="net-amount {{ $netAccountingSales >= 0 ? 'positive' : 'negative' }}">
                {{ number_format($netAccountingSales, 2) }} ر.س
            </span>
        </td>
        <td>
            صافي التحصيل:
            <span class="net-amount {{ $netAccountingCollection >= 0 ? 'positive' : 'negative' }}">
                {{ number_format($netAccountingCollection, 2) }} ر.س
            </span>
        </td>
    </tr>
</table>


{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║             ① فواتير المبيعات                          ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
<div class="sec-title sec-sales">
    فواتير المبيعات
    <span class="count-badge">{{ $salesInvoices->count() }} فاتورة</span>
</div>

@if($salesInvoices->count() > 0)
<table class="tbl">
    <thead>
        <tr>
            <th class="w5 cen">#</th>
            <th class="w12 cen">رقم الفاتورة</th>
            <th class="w25">العميل</th>
            <th class="w15 cur">المبلغ</th>
            <th class="w10 cen">الحالة</th>
            <th class="w8 cen">الوقت</th>
            <th class="w25">الأصناف</th>
        </tr>
    </thead>
    <tbody>
        @foreach($salesInvoices as $i => $inv)
        <tr>
            <td class="cen">{{ $i + 1 }}</td>
            <td class="cen">{{ $inv->invoice_number ?? $inv->id }}</td>
            <td>{{ $inv->client->trade_name ?? ($inv->client->name ?? '—') }}</td>
            <td class="cur">{{ number_format($inv->grand_total, 2) }}</td>
            <td class="cen">
                @if($inv->payment_status == 1)
                    <span class="tag tag-green">مدفوعة</span>
                @elseif($inv->payment_status == 2)
                    <span class="tag tag-orange">جزئي</span>
                @else
                    <span class="tag tag-red">آجل</span>
                @endif
            </td>
            <td class="cen">{{ $inv->created_at ? $inv->created_at->format('H:i') : '—' }}</td>
            <td style="font-size:6.5pt;">
                @if($inv->items && $inv->items->count())
                    @foreach($inv->items->take(3) as $item)
                        {{ $item->product->name ?? '—' }} ({{ $item->quantity }}){{ !$loop->last ? '، ' : '' }}
                    @endforeach
                    @if($inv->items->count() > 3)
                        <span style="color:#9ca3af;">+{{ $inv->items->count() - 3 }}</span>
                    @endif
                @else
                    —
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="sum-row">
            <td colspan="3" style="text-align:right;">إجمالي المبيعات</td>
            <td class="cur">{{ number_format($totalSalesAmount, 2) }} ر.س</td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
@else
<div class="empty-state">لا توجد فواتير مبيعات لهذا اليوم</div>
@endif


{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║             ② فواتير المرتجعات                         ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
@if($returnInvoices->count() > 0)
<div class="sec-title sec-returns">
    فواتير المرتجعات
    <span class="count-badge">{{ $returnInvoices->count() }} فاتورة</span>
</div>
<table class="tbl">
    <thead>
        <tr>
            <th class="w5 cen">#</th>
            <th class="w12 cen">رقم الفاتورة</th>
            <th class="w15 cen">رقم الأصلية</th>
            <th class="w30">العميل</th>
            <th class="w15 cur">المبلغ</th>
            <th class="w10 cen">الوقت</th>
        </tr>
    </thead>
    <tbody>
        @foreach($returnInvoices as $i => $inv)
        <tr>
            <td class="cen">{{ $i + 1 }}</td>
            <td class="cen">{{ $inv->invoice_number ?? $inv->id }}</td>
            <td class="cen">{{ $inv->reference_number ?? '—' }}</td>
            <td>{{ $inv->client->trade_name ?? ($inv->client->name ?? '—') }}</td>
            <td class="cur" style="color:#ef4444;">{{ number_format($inv->grand_total, 2) }}</td>
            <td class="cen">{{ $inv->created_at ? $inv->created_at->format('H:i') : '—' }}</td>
        </tr>
        @endforeach
        <tr class="sum-row">
            <td colspan="4" style="text-align:right;">إجمالي المرتجعات</td>
            <td class="cur">{{ number_format($totalReturnsAmount, 2) }} ر.س</td>
            <td></td>
        </tr>
    </tbody>
</table>
@endif


{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║             ③ التحصيلات                                ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
<div class="sec-title sec-collect">
    التحصيلات
    <span class="count-badge">{{ $payments->count() + $receipts->count() }} عملية</span>
</div>

{{-- المدفوعات على الفواتير --}}
@if($payments->count() > 0)
<div class="sub-title sub-title-green">
    المدفوعات على الفواتير ({{ $payments->count() }})
</div>
<table class="tbl">
    <thead>
        <tr>
            <th class="w5 cen">#</th>
            <th class="w10 cen">رقم الفاتورة</th>
            <th class="w18">العميل</th>
            <th class="w15">الخزنة / الحساب</th>
            <th class="w12 cur">المبلغ</th>
            <th class="w10 cen">طريقة الدفع</th>
            <th class="w18">ملاحظات</th>
            <th class="w8 cen">الوقت</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payments as $i => $pay)
        <tr>
            <td class="cen">{{ $i + 1 }}</td>
            <td class="cen">{{ $pay->invoice->invoice_number ?? ($pay->invoice_id ?? '—') }}</td>
            <td>
                @if($pay->invoice && $pay->invoice->client)
                    {{ $pay->invoice->client->trade_name ?? $pay->invoice->client->name }}
                @elseif($pay->client)
                    {{ $pay->client->trade_name ?? $pay->client->name }}
                @else
                    —
                @endif
            </td>
            <td>
                @if($pay->treasury)
                    {{ $pay->treasury->name ?? '—' }}
                @elseif($pay->account)
                    {{ $pay->account->name ?? '—' }}
                @else
                    —
                @endif
            </td>
            <td class="cur">{{ number_format($pay->amount, 2) }}</td>
            <td class="cen">
                @if($pay->payment_method == 'cash' || $pay->payment_method == 1)
                    <span class="tag tag-green">نقداً</span>
                @elseif($pay->payment_method == 'bank' || $pay->payment_method == 2)
                    <span class="tag tag-blue">بنكي</span>
                @else
                    <span class="tag tag-gray">{{ $pay->payment_method ?? '—' }}</span>
                @endif
            </td>
            <td style="font-size:6.5pt;">{{ $pay->notes ?? '—' }}</td>
            <td class="cen">{{ $pay->payment_date ? \Carbon\Carbon::parse($pay->payment_date)->format('H:i') : '—' }}</td>
        </tr>
        @endforeach
        <tr class="sum-row">
            <td colspan="4" style="text-align:right;">إجمالي المدفوعات</td>
            <td class="cur">{{ number_format($totalPaymentsAmount, 2) }} ر.س</td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
@endif

{{-- سندات القبض --}}
@if($receipts->count() > 0)
<div class="sub-title sub-title-teal">
    سندات القبض ({{ $receipts->count() }})
</div>
<table class="tbl">
    <thead>
        <tr>
            <th class="w5 cen">#</th>
            <th class="w10 cen">رقم السند</th>
            <th class="w18">العميل</th>
            <th class="w12">الحساب</th>
            <th class="w12">الخزنة</th>
            <th class="w12 cur">المبلغ</th>
            <th class="w20">البيان</th>
            <th class="w8 cen">الوقت</th>
        </tr>
    </thead>
    <tbody>
        @foreach($receipts as $i => $rec)
        <tr>
            <td class="cen">{{ $i + 1 }}</td>
            <td class="cen">{{ $rec->code ?? $rec->id }}</td>
            <td>{{ $rec->client->trade_name ?? ($rec->client->name ?? '—') }}</td>
            <td>{{ $rec->account->name ?? '—' }}</td>
            <td>{{ $rec->treasury->name ?? '—' }}</td>
            <td class="cur">{{ number_format($rec->amount, 2) }}</td>
            <td style="font-size:6.5pt;">{{ $rec->description ?? '—' }}</td>
            <td class="cen">{{ $rec->created_at ? $rec->created_at->format('H:i') : '—' }}</td>
        </tr>
        @endforeach
        <tr class="sum-row">
            <td colspan="5" style="text-align:right;">إجمالي سندات القبض</td>
            <td class="cur">{{ number_format($totalReceiptsAmount, 2) }} ر.س</td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>
@endif

@if($payments->count() == 0 && $receipts->count() == 0)
<div class="empty-state">لا توجد تحصيلات لهذا اليوم</div>
@endif

{{-- إجمالي التحصيلات --}}
@if($payments->count() > 0 || $receipts->count() > 0)
<table class="tbl" style="margin-top:1mm;">
    <tr class="sum-row">
        <td style="text-align:right; width:70%;">إجمالي التحصيلات الكلي</td>
        <td class="cur" style="width:30%; font-size:9pt;">{{ number_format($totalCollections, 2) }} ر.س</td>
    </tr>
</table>
@endif


{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║             ④ المصروفات                                ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
<div class="sec-title sec-expense">
    المصروفات
    <span class="count-badge">{{ $expenses->count() }} سند</span>
</div>

@if($expenses->count() > 0)
<table class="tbl">
    <thead>
        <tr>
            <th class="w5 cen">#</th>
            <th class="w10 cen">رقم السند</th>
            <th class="w15">التصنيف</th>
            <th class="w22">الوصف / البيان</th>
            <th class="w12 cur">المبلغ</th>
            <th class="w12">الحساب</th>
            <th class="w12">الخزنة</th>
            <th class="w8 cen">الوقت</th>
        </tr>
    </thead>
    <tbody>
        @foreach($expenses as $i => $exp)
        <tr>
            <td class="cen">{{ $i + 1 }}</td>
            <td class="cen">{{ $exp->code ?? $exp->id }}</td>
            <td>{{ $exp->expenses_category->name ?? '—' }}</td>
            <td style="font-size:7pt;">{{ $exp->description ?? '—' }}</td>
            <td class="cur" style="color:#ef4444;">{{ number_format($exp->amount, 2) }}</td>
            <td>{{ $exp->account->name ?? '—' }}</td>
            <td>{{ $exp->treasury->name ?? '—' }}</td>
            <td class="cen">{{ $exp->created_at ? $exp->created_at->format('H:i') : '—' }}</td>
        </tr>
        @endforeach
        <tr class="sum-row">
            <td colspan="4" style="text-align:right;">إجمالي المصروفات</td>
            <td class="cur">{{ number_format($totalExpensesAmount, 2) }} ر.س</td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
@else
<div class="empty-state">لا توجد مصروفات لهذا اليوم</div>
@endif


{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║             ⑤ حركات المخزون والأذونات                  ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
<div class="sec-title sec-stock">
    حركات المخزون والأذونات
    <span class="count-badge">{{ $warehouseMovements->count() }} إذن</span>
</div>

@if($warehouseMovements->count() > 0)

{{-- ملخص الأنواع --}}
<table class="tbl" style="margin-bottom:2mm;">
    <tr>
        <td style="text-align:center; width:33%; background:#ecfdf5; padding:2mm;">
            <span style="color:#059669; font-weight:bold; font-size:8pt;">▲ وارد: {{ $warehouseIncoming }}</span>
        </td>
        <td style="text-align:center; width:34%; background:#fff7ed; padding:2mm;">
            <span style="color:#ea580c; font-weight:bold; font-size:8pt;">▼ صادر: {{ $warehouseOutgoing }}</span>
        </td>
        <td style="text-align:center; width:33%; background:#f5f3ff; padding:2mm;">
            <span style="color:#7c3aed; font-weight:bold; font-size:8pt;">⇄ تحويل: {{ $warehouseTransfers }}</span>
        </td>
    </tr>
</table>

{{-- جدول الأذونات مع تفاصيل المنتجات --}}
@php $rowNum = 0; @endphp
<table class="tbl">
    <thead>
        <tr>
            <th class="w5 cen">#</th>
            <th class="w8 cen">رقم الإذن</th>
            <th class="w8 cen">النوع</th>
            <th class="w12">المستودع</th>
            <th class="w18">المنتج</th>
            <th class="w8 cen">الكمية</th>
            <th class="w10 cen">رصيد قبل</th>
            <th class="w10 cen">رصيد بعد</th>
            <th class="w10 cen">الرصيد الحالي</th>
            <th class="w8 cen">الوقت</th>
        </tr>
    </thead>
    <tbody>
        @foreach($warehouseMovements as $mv)
            @foreach($mv['products'] as $prod)
            @php $rowNum++; @endphp
            <tr>
                <td class="cen">{{ $rowNum }}</td>
                <td class="cen">{{ $mv['number'] ?? $mv['id'] }}</td>
                <td class="cen">
                    @if($mv['type'] == 'وارد')
                        <span class="tag tag-green">{{ $mv['type'] }}</span>
                    @elseif($mv['type'] == 'صادر')
                        <span class="tag tag-orange">{{ $mv['type'] }}</span>
                    @else
                        <span class="tag tag-purple">{{ $mv['type'] }}</span>
                    @endif
                </td>
                <td style="font-size:6.5pt;">{{ $mv['warehouse'] }}</td>
                <td>{{ $prod['name'] }}</td>
                <td class="cen">{{ number_format($prod['quantity'], 0) }}</td>
                <td class="cen">{{ number_format($prod['stock_before'], 0) }}</td>
                <td class="cen">{{ number_format($prod['stock_after'], 0) }}</td>
                <td class="cen">
                    <strong>{{ number_format($prod['current_stock'], 0) }}</strong>
                    @if($prod['current_stock'] <= 0)
                        <span class="stk stk-empty">نفد</span>
                    @elseif($prod['current_stock'] <= 5)
                        <span class="stk stk-low">منخفض</span>
                    @else
                        <span class="stk stk-ok">متوفر</span>
                    @endif
                </td>
                <td class="cen">{{ $mv['time'] ?? '—' }}</td>
            </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

@else
<div class="empty-state">لا توجد حركات مخزون لهذا اليوم</div>
@endif


{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║             ⑥ رصيد المستودع الحالي                     ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
@if(isset($employeeWarehouse) && $employeeWarehouse && isset($warehouseInventory) && $warehouseInventory->count() > 0)
<div class="sec-title sec-inv">
    رصيد المستودع الحالي: {{ $employeeWarehouse->name }}
    <span class="count-badge">{{ $warehouseInventory->count() }} صنف</span>
</div>
<table class="inv-grid">
    <thead>
        <tr>
            <th class="w10 cen">#</th>
            <th class="w60">المنتج</th>
            <th class="w30 cen">الكمية المتبقية</th>
        </tr>
    </thead>
    <tbody>
        @foreach($warehouseInventory as $idx => $inv)
        <tr>
            <td class="cen">{{ $idx + 1 }}</td>
            <td>{{ $inv->product->name ?? '—' }}</td>
            <td class="cen">
                <strong>{{ number_format($inv->total_quantity, 0) }}</strong>
                @if($inv->total_quantity <= 0)
                    <span class="stk stk-empty">نفد</span>
                @elseif($inv->total_quantity <= 5)
                    <span class="stk stk-low">منخفض</span>
                @else
                    <span class="stk stk-ok">متوفر</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif


{{-- ╔══════════════════════════════════════════════════════════╗ --}}
{{-- ║                   الفوتر                               ║ --}}
{{-- ╚══════════════════════════════════════════════════════════╝ --}}
<div class="report-footer">
    تم إنشاء التقرير آلياً — <strong>{{ date('Y-m-d H:i') }}</strong> — نظام فوترة سمارت
</div>

</body>
</html>
