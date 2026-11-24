<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>عرض سعر - نموذج مضغوط</title>
    <style>
        body {
            direction: rtl;
            font-family: aealarabiya;
            padding: 10px;
            font-size: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
        }

        .header h1 {
            margin: 0;
            padding: 0;
            font-size: 14px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
        }

        .data-table th {
            background-color: #e0e0e0;
            font-size: 9px;
        }

        .data-table td {
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>عرض سعر - نموذج مضغوط</h1>
        <p>رقم: {{ $purchaseQuotation->code }} | التاريخ: {{ $purchaseQuotation->date }} | المورد: {{ $purchaseQuotation->supplier->trade_name ?? 'غير محدد' }}</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>السعر</th>
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
                <td colspan="4" style="text-align: left; font-weight: bold;">الإجمالي:</td>
                <td>{{ number_format($purchaseQuotation->grand_total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>