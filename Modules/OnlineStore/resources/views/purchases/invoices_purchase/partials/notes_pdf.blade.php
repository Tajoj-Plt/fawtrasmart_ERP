{{--
    ملاحظات فاتورة الشراء — تقرير طباعة.

    مُحاذاة لنمط رأس/تذييل `Modules/Account/resources/views/finance/expenses/print_normal.blade.php`
    (doc-head محاط بإطار، شبكة بيانات info-grid، doc-foot)، مع إبقاء قائمة
    الملاحظات كبطاقات كما كانت — المتغيرات $purchaseInvoice و$notes كما
    يمرّرها StorePurchaseInvoiceController::notesPdf().
--}}
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>ملاحظات فاتورة الشراء - {{ $purchaseInvoice->code }}</title>
    <style>
        body {
            font-family: 'Cairo', 'aealarabiya', Arial, sans-serif;
            direction: rtl;
            text-align: right;
            margin: 20px;
            background-color: #f4f5f7;
            color: #000;
        }

        .paper {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            padding: 24px;
            box-sizing: border-box;
        }

        .doc-head {
            text-align: center;
            border: 2px solid #000;
            padding: 12px;
            margin-bottom: 20px;
        }

        .doc-head h1 {
            margin: 0 0 4px;
            font-size: 18px;
        }

        .doc-head p {
            margin: 2px 0;
            font-size: 12px;
            color: #333;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-grid td {
            padding: 8px 6px;
            vertical-align: top;
            font-size: 13px;
        }

        .info-label {
            font-weight: 700;
            width: 20%;
            color: #333;
            white-space: nowrap;
        }

        .info-value {
            border-bottom: 1px dotted #666;
            width: 30%;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 10px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .note-card {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
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

        .doc-foot {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            text-align: center;
            font-size: 11px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="paper">
        <div class="doc-head">
            <h1>ملاحظات فاتورة الشراء</h1>
            <p>فاتورة رقم: {{ $purchaseInvoice->code }}</p>
            <p>المورد: {{ optional($purchaseInvoice->supplier)->trade_name ?? 'غير محدد' }}</p>
            <p>تاريخ التقرير: {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>

        <h3 class="section-title">معلومات الفاتورة</h3>
        <table class="info-grid">
            <tr>
                <td class="info-label">رقم الفاتورة:</td>
                <td class="info-value">{{ $purchaseInvoice->code }}</td>
                <td class="info-label">تاريخ الفاتورة:</td>
                <td class="info-value">{{ $purchaseInvoice->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
            <tr>
                <td class="info-label">المجموع الكلي:</td>
                <td class="info-value">{{ number_format($purchaseInvoice->grand_total, 2) }} ر.س</td>
                <td class="info-label">الحالة:</td>
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

        <h3 class="section-title">قائمة الملاحظات ({{ $notes->count() }})</h3>

        @if ($notes->count() > 0)
            @foreach ($notes as $note)
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
                    @if ($note->attachment)
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

        <div class="doc-foot">تم إنشاء هذا التقرير بواسطة نظام فوترة فوترات في {{ now()->format('Y-m-d H:i:s') }}</div>
    </div>
</body>
</html>
