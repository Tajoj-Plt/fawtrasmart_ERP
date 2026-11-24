<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>أمر شراء مفصل - {{ $purchaseOrdersRequests->code }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-info {
            text-align: right;
            margin-bottom: 15px;
        }
        
        .order-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .info-section {
            width: 48%;
        }
        
        .info-section h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }
        
        .info-item {
            margin-bottom: 4px;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        th, td {
            border: 1px solid #333;
            padding: 6px;
            text-align: center;
        }
        
        th {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            width: 30%;
            text-align: center;
        }
        
        .signature-line {
            margin-top: 30px;
            border-top: 1px solid #333;
            padding-top: 5px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-left {
            text-align: left;
        }
        
        .mb-5 {
            margin-bottom: 5px;
        }
        
        .mt-15 {
            margin-top: 15px;
        }
        
        .mt-30 {
            margin-top: 30px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .summary-table {
            width: 50%;
            margin-left: auto;
        }
        
        .summary-table td {
            border: 1px solid #333;
            padding: 4px;
        }
        
        .summary-label {
            font-weight: bold;
            text-align: left;
        }
        
        .summary-value {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">أمر شراء - نموذج مفصل</div>
        <div>الرقم: {{ $purchaseOrdersRequests->code }}</div>
        <div>التاريخ: {{ $purchaseOrdersRequests->date }}</div>
    </div>
    
    <div class="order-info">
        <div class="info-section">
            <h3>معلومات الأمر</h3>
            <div class="info-item">
                <span class="info-label">رقم الأمر:</span>
                <span>{{ $purchaseOrdersRequests->code }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">تاريخ الأمر:</span>
                <span>{{ $purchaseOrdersRequests->date }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">منشئ الأمر:</span>
                <span>{{ $purchaseOrdersRequests->creator->name ?? 'غير محدد' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">المورد:</span>
                <span>{{ $purchaseOrdersRequests->supplier->trade_name ?? 'غير محدد' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">الحالة:</span>
                <span>
                    @if ($purchaseOrdersRequests->status == "Under Review")
                        تحت المراجعة
                    @elseif ($purchaseOrdersRequests->status == "approval")
                        تمت الموافقة عليه
                    @elseif ($purchaseOrdersRequests->status == "disagree")
                        مرفوض
                    @endif
                </span>
            </div>
        </div>
        
        <div class="info-section">
            <h3>معلومات إضافية</h3>
            <div class="info-item">
                <span class="info-label">عدد المنتجات:</span>
                <span>{{ $purchaseOrdersRequests->items->count() }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">الإجمالي:</span>
                <span>{{ number_format($purchaseOrdersRequests->grand_total, 2) }} ر.س</span>
            </div>
            <div class="info-item">
                <span class="info-label">الملاحظات:</span>
                <span>{{ $purchaseOrdersRequests->notes ?? 'لا توجد ملاحظات' }}</span>
            </div>
        </div>
    </div>
    
    <h3>المنتجات</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>اسم المنتج</th>
                <th>الوصف</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الخصم</th>
                <th>الضريبة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseOrdersRequests->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product->name ?? $item->item ?? '--' }}</td>
                    <td>{{ $item->description ?? '--' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->unit_price, 2) }}</td>
                    <td>{{ number_format($item->discount, 2) }}</td>
                    <td>{{ number_format($item->tax, 2) }}</td>
                    <td>{{ number_format($item->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">لا توجد منتجات في هذا الأمر</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ملخص الأمر -->
    <table class="summary-table">
        <tr>
            <td class="summary-label">عدد المنتجات:</td>
            <td class="summary-value">{{ $purchaseOrdersRequests->items->count() }}</td>
        </tr>
        <tr>
            <td class="summary-label">الكمية الإجمالية:</td>
            <td class="summary-value">{{ $purchaseOrdersRequests->items->sum('quantity') }}</td>
        </tr>
        <tr>
            <td class="summary-label">الإجمالي قبل الضريبة:</td>
            <td class="summary-value">{{ number_format($purchaseOrdersRequests->items->sum('total') - $purchaseOrdersRequests->items->sum('tax'), 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">إجمالي الضريبة:</td>
            <td class="summary-value">{{ number_format($purchaseOrdersRequests->items->sum('tax'), 2) }}</td>
        </tr>
        <tr>
            <td class="summary-label">الإجمالي النهائي:</td>
            <td class="summary-value">{{ number_format($purchaseOrdersRequests->grand_total, 2) }} ر.س</td>
        </tr>
    </table>
    
    <div class="signature-section">
        <div class="signature-box">
            <div>التوقيع</div>
            <div class="signature-line">المنشئ</div>
        </div>
        
        <div class="signature-box">
            <div>التوقيع</div>
            <div class="signature-line">المورد</div>
        </div>
        
        <div class="signature-box">
            <div>التوقيع</div>
            <div class="signature-line">المستلم</div>
        </div>
    </div>
    
    <div class="footer">
        <p>تم إنشاء هذا الأمر بواسطة نظام فوترة - {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>