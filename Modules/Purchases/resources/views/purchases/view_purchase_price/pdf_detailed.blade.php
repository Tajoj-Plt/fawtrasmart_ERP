<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>عرض سعر - نموذج مفصل</title>
    <style>
        body {
            direction: rtl;
            font-family: aealarabiya;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border: 2px solid #000;
            padding: 15px;
            background-color: #f9f9f9;
        }

        .header h1 {
            margin: 0;
            padding: 0;
            font-size: 22px;
            color: #333;
        }

        .header p {
            margin: 5px 0;
            font-size: 14px;
        }

        .info-grid {
            width: 100%;
            margin: 20px 0;
            border: 1px solid #000;
        }

        .info-grid td {
            padding: 8px;
        }

        .info-label {
            font-weight: bold;
            width: 150px;
            background-color: #f0f0f0;
        }

        .info-value {
            border-bottom: 1px dotted #666;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 10px;
            text-align: center;
        }

        .data-table th {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .section-title {
            margin: 25px 0 15px;
            border-bottom: 3px solid #333;
            padding-bottom: 8px;
            font-size: 18px;
            color: #333;
        }

        .totals-table {
            width: 50%;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px;
            border: 1px solid #000;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>عرض سعر - نموذج مفصل</h1>
        <p>{{ date('d/m/Y') }}</p>
        <p>{{ \App\Support\OrganizationInfo::name() }}</p>
        <p>رقم العرض: {{ $purchaseQuotation->code }}</p>
        @if($purchaseQuotation->status == "Under Review")
            <p>الحالة: تحت المراجعة</p>
        @elseif($purchaseQuotation->status == "approval")
            <p>الحالة: معتمد</p>
        @elseif($purchaseQuotation->status == "disagree")
            <p>الحالة: مرفوض</p>
        @endif
    </div>

    <table class="info-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">المورد:</td>
            <td class="info-value">{{ $purchaseQuotation->supplier->trade_name ?? 'غير محدد' }}</td>
            <td class="info-label">رقم العرض:</td>
            <td class="info-value">{{ $purchaseQuotation->code }}</td>
        </tr>
        <tr>
            <td class="info-label">تاريخ العرض:</td>
            <td class="info-value">{{ $purchaseQuotation->date }}</td>
            <td class="info-label">صالح لمدة:</td>
            <td class="info-value">{{ $purchaseQuotation->valid_days }} يوم</td>
        </tr>
        <tr>
            <td class="info-label">الحساب:</td>
            <td class="info-value">{{ optional($purchaseQuotation->account)->name ?? 'غير محدد' }}</td>
            <td class="info-label">منشئ العرض:</td>
            <td class="info-value">{{ optional($purchaseQuotation->creator)->name ?? 'غير محدد' }}</td>
        </tr>
        <tr>
            <td class="info-label">الملاحظات:</td>
            <td class="info-value" colspan="3">{{ $purchaseQuotation->notes ?? 'لا توجد ملاحظات' }}</td>
        </tr>
    </table>

    <h3 class="section-title">تفاصيل المنتجات:</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الخصم</th>
                <th>الضريبة 1</th>
                <th>الضريبة 2</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
       <tbody>
    @foreach($purchaseQuotation->items as $item)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $item->product->name ?? $item->item }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format($item->unit_price, 2) }}</td>
            <td>{{ number_format($item->discount, 2) }}</td>
            <td>{{ number_format($item->tax_1, 2) }}%</td>
            <td>{{ number_format($item->tax_2, 2) }}%</td>
            <td>{{ number_format($item->total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td style="text-align: left; font-weight: bold; width: 70%;">المجموع الفرعي:</td>
            <td>{{ number_format($purchaseQuotation->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td style="text-align: left; font-weight: bold;">إجمالي الخصم:</td>
            <td>{{ number_format($purchaseQuotation->total_discount, 2) }}</td>
        </tr>
        <tr>
            <td style="text-align: left; font-weight: bold;">إجمالي الضريبة:</td>
            <td>{{ number_format($purchaseQuotation->total_tax, 2) }}</td>
        </tr>
        <tr>
            <td style="text-align: left; font-weight: bold;">تكلفة الشحن:</td>
            <td>{{ number_format($purchaseQuotation->shipping_cost, 2) }}</td>
        </tr>
        <tr>
            <td style="text-align: left; font-weight: bold;">المجموع النهائي:</td>
            <td>{{ number_format($purchaseQuotation->grand_total, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>شكراً لاختياركم {{ \App\Support\OrganizationInfo::name() }}</p>
        <p>للاستفسارات: {{ \App\Support\OrganizationInfo::email() }} | هاتف: {{ \App\Support\OrganizationInfo::phone() }}</p>
    </div>
</body>
</html>