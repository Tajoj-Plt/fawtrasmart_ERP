<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>أمر شراء بسيط - {{ $purchaseOrdersRequests->code }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .order-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
        }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #666;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        
        .signature-box {
            width: 30%;
            text-align: center;
        }
        
        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #333;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">أمر شراء - نموذج بسيط</div>
        <div>الرقم: {{ $purchaseOrdersRequests->code }} | التاريخ: {{ $purchaseOrdersRequests->date }}</div>
    </div>
    
    <div class="order-info">
        <div>
            <div class="info-item">
                <span class="info-label">المورد:</span>
                <span>{{ $purchaseOrdersRequests->supplier->trade_name ?? 'غير محدد' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">منشئ الأمر:</span>
                <span>{{ $purchaseOrdersRequests->creator->name ?? 'غير محدد' }}</span>
            </div>
        </div>
        <div>
            <div class="info-item">
                <span class="info-label">عدد المنتجات:</span>
                <span>{{ $purchaseOrdersRequests->items->count() }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">الإجمالي:</span>
                <span>{{ number_format($purchaseOrdersRequests->grand_total, 2) }} ر.س</span>
            </div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم المنتج</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
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
                    <td colspan="5">لا توجد منتجات في هذا الأمر</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="signature-section">
        <div class="signature-box">
            <div>توقيع المورد</div>
            <div class="signature-line">{{ $purchaseOrdersRequests->supplier->trade_name ?? 'المورد' }}</div>
        </div>
        
        <div class="signature-box">
            <div>توقيع المشتري</div>
            <div class="signature-line">{{ $purchaseOrdersRequests->creator->name ?? 'المشتري' }}</div>
        </div>
    </div>
    
    <div class="footer">
        <p>تم إنشاء هذا الأمر بواسطة نظام فوترة - {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>