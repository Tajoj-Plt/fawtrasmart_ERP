<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوليصة الشحن - {{ $order->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            direction: rtl;
        }
        .label-container {
            width: 10cm;
            height: 15cm;
            margin: 0 auto;
            padding: 10px;
            border: 2px solid #000;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .header .order-number {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .section {
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ccc;
        }
        .section-title {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .section-content {
            font-size: 14px;
        }
        .section-content strong {
            font-size: 16px;
            display: block;
            margin-bottom: 3px;
        }
        
        .from-section {
            background: #f5f5f5;
        }
        .to-section {
            background: #fff;
            border: 2px solid #000;
        }
        .to-section .section-content strong {
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }
        .info-box {
            flex: 1;
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        .info-box .label {
            font-size: 9px;
            color: #666;
            display: block;
        }
        .info-box .value {
            font-size: 14px;
            font-weight: bold;
        }
        
        .products-summary {
            border: 1px solid #ccc;
            padding: 8px;
            margin-bottom: 10px;
        }
        .products-summary h3 {
            font-size: 11px;
            margin-bottom: 5px;
            color: #666;
        }
        .products-summary .count {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
        }
        
        .barcode-section {
            text-align: center;
            padding: 15px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }
        .barcode {
            font-family: 'Courier New', monospace;
            font-size: 20px;
            letter-spacing: 3px;
            font-weight: bold;
        }
        .barcode-lines {
            margin-top: 5px;
            font-size: 8px;
            color: #666;
        }
        
        .badges {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .badge {
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 3px;
        }
        .badge-cod {
            background: #ff9800;
            color: #fff;
        }
        .badge-express {
            background: #f44336;
            color: #fff;
        }
        .badge-gift {
            background: #9c27b0;
            color: #fff;
        }
        .badge-signature {
            background: #2196f3;
            color: #fff;
        }
        .badge-door {
            background: #4caf50;
            color: #fff;
        }
        
        .amount-box {
            background: #000;
            color: #fff;
            text-align: center;
            padding: 10px;
            font-size: 18px;
            font-weight: bold;
        }
        .amount-box .label {
            font-size: 10px;
            font-weight: normal;
        }
        
        .footer {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .label-container {
                border: none;
            }
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background: #7367f0;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover {
            background: #5e50ee;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ طباعة</button>
    
    <div class="label-container">
        <!-- الرأس -->
        <div class="header">
            <h1>{{ \App\Support\OrganizationInfo::name() }}</h1>
            <div class="order-number">{{ $order->order_number }}</div>
        </div>

        <!-- الشارات -->
        <div class="badges">
            @if($order->payment_method->value === 'cod')
                <span class="badge badge-cod">💵 الدفع عند الاستلام</span>
            @endif
            @if($order->is_express)
                <span class="badge badge-express">⚡ شحن سريع</span>
            @endif
            @if($order->is_gift)
                <span class="badge badge-gift">🎁 هدية</span>
            @endif
            @if($order->requires_signature)
                <span class="badge badge-signature">✍️ يتطلب توقيع</span>
            @endif
            @if($order->leave_at_door)
                <span class="badge badge-door">🚪 ترك عند الباب</span>
            @endif
        </div>

        <!-- من -->
        <div class="section from-section">
            <div class="section-title">من / FROM</div>
            <div class="section-content">
                <strong>{{ \App\Support\OrganizationInfo::name() }}</strong>
                {{ \App\Support\OrganizationInfo::address() ?: 'المملكة العربية السعودية' }}
            </div>
        </div>

        <!-- إلى -->
        <div class="section to-section">
            <div class="section-title">إلى / TO</div>
            <div class="section-content">
                <strong>{{ $order->shipping_name }}</strong>
                {{ $order->shipping_phone }}<br>
                {{ $order->shipping_city }} - {{ $order->shipping_region }}<br>
                {{ $order->shipping_address }}
            </div>
        </div>

        <!-- معلومات سريعة -->
        <div class="info-row">
            <div class="info-box">
                <span class="label">المدينة</span>
                <span class="value">{{ $order->shipping_city }}</span>
            </div>
            <div class="info-box">
                <span class="label">المنطقة</span>
                <span class="value">{{ $order->shipping_region }}</span>
            </div>
            <div class="info-box">
                <span class="label">القطع</span>
                <span class="value">{{ $order->items->sum('quantity') }}</span>
            </div>
        </div>

        <!-- ملخص المنتجات -->
        <div class="products-summary">
            <h3>المنتجات ({{ $order->items->count() }} صنف)</h3>
            <div class="count">{{ $order->items->sum('quantity') }} قطعة</div>
        </div>

        <!-- الباركود -->
        <div class="barcode-section">
            <div class="barcode">{{ $order->order_number }}</div>
            @if($order->tracking_number)
                <div class="barcode-lines">رقم التتبع: {{ $order->tracking_number }}</div>
            @endif
        </div>

        <!-- المبلغ (للدفع عند الاستلام) -->
        @if(in_array($order->payment_method?->value, ['cod', 'cash'], true) && $order->collectibleAmount() > 0)
            <div class="amount-box">
                <span class="label">المبلغ المطلوب تحصيله</span><br>
                {{ $order->formatMoney($order->collectibleAmount()) }}
                @if($order->shipping_tax_amount > 0)
                    <br><small>يشمل ضريبة الشحن {{ $order->formatMoney($order->shipping_tax_amount) }}</small>
                @endif
            </div>
        @endif

        <!-- التذييل -->
        <div class="footer">
            <p>تاريخ الطلب: {{ $order->created_at->format('Y-m-d') }}</p>
            @if($order->estimated_delivery_date)
                <p>التسليم المتوقع: {{ $order->estimated_delivery_date->format('Y-m-d') }}</p>
            @endif
            @if($order->notes)
                <p>ملاحظة: {{ Str::limit($order->notes, 50) }}</p>
            @endif
        </div>
    </div>
</body>
</html>
