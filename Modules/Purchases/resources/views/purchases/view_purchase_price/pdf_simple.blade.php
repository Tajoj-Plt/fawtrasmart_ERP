<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>عرض سعر - نموذج بسيط</title>
    <style>
        body {
            direction: rtl;
            font-family: aealarabiya;
            padding: 15px;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            padding: 0;
            font-size: 16px;
        }

        .info-grid {
            width: 100%;
            margin: 15px 0;
        }

        .info-grid td {
            padding: 4px;
        }

        .info-label {
            font-weight: bold;
            width: 100px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 11px;
        }

        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }

        .data-table th {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>عرض سعر - نموذج بسيط</h1>
        <p>رقم العرض: {{ $purchaseQuotation->code }} | التاريخ: {{ $purchaseQuotation->date }}</p>
    </div>

    <table class="info-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">المورد:</td>
            <td>{{ $purchaseQuotation->supplier->trade_name ?? 'غير محدد' }}</td>
            <td class="info-label">صالح لمدة:</td>
            <td>{{ $purchaseQuotation->valid_days }} يوم</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
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
            <td>{{ number_format($item->total, 2) }}</td>
        </tr>
    @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: left; font-weight: bold;">المجموع النهائي:</td>
                <td>{{ number_format($purchaseQuotation->grand_total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>