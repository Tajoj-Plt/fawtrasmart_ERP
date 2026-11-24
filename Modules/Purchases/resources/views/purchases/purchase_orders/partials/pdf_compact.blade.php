<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>أمر شراء مضغوط - {{ $purchaseOrdersRequests->code }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.2;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }
        
        .title {
            font-size: 14px;
            font-weight: bold;
        }
        
        .order-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        th, td {
            border: 1px solid #333;
            padding: 4px;
            text-align: center;
        }
        
        th {
            background-color: #e8e8e8;
            font-weight: bold;
            font-size: 9px;
        }
        
        td {
            font-size: 8px;
        }
        
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">أمر شراء - نموذج مضغوط</div>
        <div>الرقم: {{ $purchaseOrdersRequests->code }} | التاريخ: {{ $purchaseOrdersRequests->date }}</div>
    </div>
    
    <div class="order-summary">
        <div>المورد: {{ $purchaseOrdersRequests->supplier->trade_name ?? 'غير محدد' }}</div>
        <div>المنشئ: {{ $purchaseOrdersRequests->creator->name ?? 'غير محدد' }}</div>
        <div>المنتجات: {{ $purchaseOrdersRequests->items->count() }}</div>
        <div>الإجمالي: {{ number_format($purchaseOrdersRequests->grand_total, 2) }} ر.س</div>
    </div>
    
    <table>
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
            @forelse ($purchaseOrdersRequests->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product->name ?? $item->item ?? '--' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->unit_price, 2) }}</td>
                    <td>{{ number_format($item->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">لا توجد منتجات</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>تم إنشاء هذا الأمر بواسطة نظام فوترة - {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>