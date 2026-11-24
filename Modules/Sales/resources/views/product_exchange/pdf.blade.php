<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة عملية التبديل #{{ $exchange->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
            direction: rtl;
            background: white;
            padding: 20px;
            font-size: 14px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #17a2b8;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #17a2b8;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .header .exchange-code {
            font-size: 18px;
            color: #333;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }
        .info-box {
            flex: 1;
            padding: 10px;
        }
        .info-box:not(:last-child) {
            border-left: 1px solid #ddd;
        }
        .info-label {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .info-value {
            font-weight: bold;
            font-size: 14px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: right;
        }
        th {
            background: #17a2b8;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .reason-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        .reason-damaged {
            background: #ffeeba;
            color: #856404;
        }
        .reason-missing_perfume {
            background: #d4edda;
            color: #155724;
        }
        .reason-defective {
            background: #f8d7da;
            color: #721c24;
        }
        .reason-other {
            background: #e2e3e5;
            color: #383d41;
        }
        .total-section {
            text-align: left;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #17a2b8;
            border-top: 2px solid #17a2b8;
            padding-top: 10px;
            margin-top: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 20px;
        }
        .signature-box {
            width: 200px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #17a2b8; color: white; border: none; border-radius: 5px;">
            <i class="fa fa-print"></i> طباعة
        </button>
        <button onclick="window.close()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px; margin-right: 10px;">
            إغلاق
        </button>
    </div>

    <div class="container">
        <div class="header">
            <h1>إشعار تبديل منتجات</h1>
            <div class="exchange-code">رقم العملية: {{ $exchange->code }}</div>
        </div>

        <div class="info-section">
            <div class="info-box">
                <div class="info-label">العميل</div>
                <div class="info-value">
                    {{ $exchange->client ? ($exchange->client->trade_name ?: $exchange->client->first_name . ' ' . $exchange->client->last_name) : 'غير محدد' }}
                </div>
                @if($exchange->client && $exchange->client->code)
                    <small>كود: {{ $exchange->client->code }}</small>
                @endif
            </div>
            <div class="info-box">
                <div class="info-label">تاريخ التبديل</div>
                <div class="info-value">{{ $exchange->exchange_date ? $exchange->exchange_date->format('Y-m-d') : '--' }}</div>
            </div>
            <div class="info-box">
                <div class="info-label">المستودع</div>
                <div class="info-value">{{ $exchange->storehouse ? $exchange->storehouse->name : 'غير محدد' }}</div>
            </div>
            <div class="info-box">
                <div class="info-label">أنشئت بواسطة</div>
                <div class="info-value">{{ $exchange->createdByUser ? $exchange->createdByUser->name : 'غير محدد' }}</div>
            </div>
        </div>

        @if($exchange->notes)
        <div style="margin-bottom: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
            <strong>ملاحظات:</strong> {{ $exchange->notes }}
        </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 35%">المنتج</th>
                    <th style="width: 10%">الكمية</th>
                    <th style="width: 15%">السعر</th>
                    <th style="width: 15%">الإجمالي</th>
                    <th style="width: 20%">السبب</th>
                </tr>
            </thead>
            <tbody>
                @foreach($exchange->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->product ? $item->product->name : 'منتج غير معروف' }}
                        @if($item->product && $item->product->code)
                            <br><small style="color: #666;">كود: {{ $item->product->code }}</small>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: center;">{{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: center;">{{ number_format($item->total, 2) }}</td>
                    <td style="text-align: center;">
                        <span class="reason-badge" style="background-color: #e2e3e5; color: #383d41;">
                            {{ $item->reason_text }}
                        </span>
                        @if($item->reason_notes)
                            <br><small>{{ $item->reason_notes }}</small>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">
                <span>عدد المنتجات:</span>
                <span>{{ $exchange->items->count() }}</span>
            </div>
            <div class="total-row">
                <span>إجمالي الكميات:</span>
                <span>{{ $exchange->items->sum('quantity') }}</span>
            </div>
            <div class="total-row grand-total">
                <span>الإجمالي الكلي:</span>
                <span>{{ number_format($exchange->total_value, 2) }} ر.س</span>
            </div>
        </div>

        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">توقيع المستلم</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">توقيع المسؤول</div>
            </div>
        </div>

        <div class="footer">
            <p>تم الطباعة بتاريخ: {{ now()->format('Y-m-d H:i') }}</p>
        </div>
    </div>
</body>
</html>
