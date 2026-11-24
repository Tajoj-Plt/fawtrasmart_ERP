<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>ملاحظات فاتورة الشراء - {{ $purchaseInvoice->code }}</title>
    <style>
        body {
            font-family: 'aealarabiya', Arial, sans-serif;
            direction: rtl;
            margin: 20px;
            background-color: #f8f9fa;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .header h1 {
            color: #343a40;
            margin: 0 0 10px 0;
        }
        
        .invoice-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .invoice-info p {
            margin: 5px 0;
        }
        
        .notes-container {
            margin-top: 20px;
        }
        
        .note-card {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .note-title {
            font-weight: bold;
            color: #495057;
            font-size: 16px;
        }
        
        .note-date {
            color: #6c757d;
            font-size: 14px;
        }
        
        .note-content {
            color: #212529;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .note-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 13px;
        }
        
        .no-notes {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 30px;
        }
        
        .page-footer {
            margin-top: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ملاحظات فاتورة الشراء</h1>
        <p>فاتورة رقم: {{ $purchaseInvoice->code }}</p>
        <p>المورد: {{ optional($purchaseInvoice->supplier)->trade_name ?? 'غير محدد' }}</p>
        <p>تاريخ التقرير: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    
    <div class="invoice-info">
        <p><strong>معلومات الفاتورة:</strong></p>
        <p>رقم الفاتورة: {{ $purchaseInvoice->code }}</p>
        <p>تاريخ الفاتورة: {{ $purchaseInvoice->created_at->format('Y-m-d H:i:s') }}</p>
        <p>المجموع الكلي: {{ number_format($purchaseInvoice->grand_total, 2) }} ر.س</p>
        <p>الحالة: 
            @if($purchaseInvoice->payment_status == 'paid')
                مدفوعة بالكامل
            @elseif($purchaseInvoice->payment_status == 'partially_paid')
                مدفوعة جزئياً
            @elseif($purchaseInvoice->payment_status == 'unpaid')
                غير مدفوعة
            @else
                قيد المراجعة
            @endif
        </p>
    </div>
    
    <div class="notes-container">
        <h2>قائمة الملاحظات ({{ $notes->count() }})</h2>
        
        @if($notes->count() > 0)
            @foreach($notes as $note)
            <div class="note-card">
                <div class="note-header">
                    <div class="note-title">{{ $note->process ?? 'ملاحظة عامة' }}</div>
                    <div class="note-date">{{ $note->created_at->format('Y-m-d H:i:s') }}</div>
                </div>
                <div class="note-content">
                    {{ $note->description ?? 'لا يوجد وصف' }}
                </div>
                <div class="note-meta">
                    <div>الموظف: {{ optional($note->employee)->name ?? 'غير محدد' }}</div>
                    <div>التاريخ: {{ $note->date ?? 'غير محدد' }}</div>
                    <div>الوقت: {{ $note->time ?? 'غير محدد' }}</div>
                </div>
                @if($note->attachment)
                <div class="note-meta" style="margin-top: 10px;">
                    <div>مرفق: {{ basename($note->attachment) }}</div>
                </div>
                @endif
            </div>
            @endforeach
        @else
        <div class="no-notes">
            <p>لا توجد ملاحظات لهذه الفاتورة</p>
        </div>
        @endif
    </div>
    
    <div class="page-footer">
        <p>تم إنشاء هذا التقرير بواسطة نظام فوترة فوترات في {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>