<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ملخص أمر شراء - {{ $purchaseOrdersRequests->code }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-box {
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 4px;
        }
        
        .info-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            text-align: center;
        }
        
        .stat-item {
            padding: 10px;
        }
        
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">ملخص أمر شراء</div>
        <div>الرقم: {{ $purchaseOrdersRequests->code }} | التاريخ: {{ $purchaseOrdersRequests->date }}</div>
    </div>
    
    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">معلومات المورد</div>
            <div>الاسم: {{ $purchaseOrdersRequests->supplier->trade_name ?? 'غير محدد' }}</div>
            <div>منشئ الأمر: {{ $purchaseOrdersRequests->creator->name ?? 'غير محدد' }}</div>
        </div>
        
        <div class="info-box">
            <div class="info-label">معلومات مالية</div>
            <div>عدد المنتجات: {{ $purchaseOrdersRequests->items->count() }}</div>
            <div>الإجمالي: {{ number_format($purchaseOrdersRequests->grand_total, 2) }} ر.س</div>
            <div>الحالة: 
                @if ($purchaseOrdersRequests->status == "Under Review")
                    تحت المراجعة
                @elseif ($purchaseOrdersRequests->status == "approval")
                    تمت الموافقة عليه
                @elseif ($purchaseOrdersRequests->status == "disagree")
                    مرفوض
                @endif
            </div>
        </div>
    </div>
    
    <div class="summary-stats">
        <div class="stat-item">
            <div class="stat-value">{{ $purchaseOrdersRequests->items->count() }}</div>
            <div class="stat-label">عدد المنتجات</div>
        </div>
        
        <div class="stat-item">
            <div class="stat-value">{{ $purchaseOrdersRequests->items->sum('quantity') }}</div>
            <div class="stat-label">الكمية الإجمالية</div>
        </div>
        
        <div class="stat-item">
            <div class="stat-value">{{ number_format($purchaseOrdersRequests->grand_total, 2) }}</div>
            <div class="stat-label">الإجمالي (ر.س)</div>
        </div>
    </div>
    
    <div class="footer">
        <p>تم إنشاء هذا الملخص بواسطة نظام فوترة - {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>