<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة الطلب #{{ $order->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            background: #fff;
            direction: rtl;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #7367f0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .company-info h1 {
            font-size: 24px;
            color: #7367f0;
            margin-bottom: 5px;
        }
        .company-info p {
            color: #666;
            font-size: 11px;
        }
        .order-info {
            text-align: left;
        }
        .order-info h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }
        .order-info .order-number {
            font-size: 16px;
            font-weight: bold;
            color: #7367f0;
        }
        .order-info .order-date {
            color: #666;
            font-size: 11px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
        }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-processing { background: #e3f2fd; color: #1565c0; }
        .status-shipped { background: #e8f5e9; color: #2e7d32; }
        .status-completed { background: #e8f5e9; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        
        .info-section {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }
        .info-box {
            flex: 1;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
        }
        .info-box h3 {
            font-size: 13px;
            color: #7367f0;
            margin-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
        }
        .info-box p {
            margin-bottom: 5px;
        }
        .info-box .label {
            color: #888;
            font-size: 10px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background: #7367f0;
            color: #fff;
            padding: 10px;
            text-align: right;
            font-size: 11px;
        }
        .items-table th:last-child,
        .items-table td:last-child {
            text-align: left;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .items-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .product-name {
            font-weight: 600;
        }
        .product-sku {
            color: #888;
            font-size: 10px;
        }
        
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .totals-table td:last-child {
            text-align: left;
            font-weight: 500;
        }
        .totals-table .grand-total {
            font-size: 16px;
            font-weight: bold;
            color: #7367f0;
            background: #f5f5f5;
        }
        .totals-table .discount {
            color: #28a745;
        }
        
        .notes-section {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background: #fafafa;
        }
        .notes-section h3 {
            font-size: 13px;
            color: #7367f0;
            margin-bottom: 10px;
        }
        
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #888;
            font-size: 10px;
        }
        .footer p {
            margin-bottom: 5px;
        }
        
        .barcode {
            text-align: center;
            margin: 20px 0;
        }
        .barcode-text {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            letter-spacing: 2px;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .invoice-container {
                padding: 0;
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
    
    <div class="invoice-container">
        <!-- رأس الفاتورة -->
        <div class="invoice-header">
            <div class="company-info">
                <h1>{{ \App\Support\OrganizationInfo::name() }}</h1>
                @if (\App\Support\OrganizationInfo::address())
                    <p>{{ \App\Support\OrganizationInfo::address() }}</p>
                @endif
                @if (\App\Support\OrganizationInfo::identityLine())
                    <p>{{ \App\Support\OrganizationInfo::identityLine() }}</p>
                @endif
            </div>
            <div class="order-info">
                <h2>فاتورة طلب</h2>
                <div class="order-number">{{ $order->order_number }}</div>
                <div class="order-date">{{ $order->created_at->format('Y-m-d H:i') }}</div>
                <span class="status-badge status-{{ $order->status->value }}">
                    {{ $order->status->label() }}
                </span>
            </div>
        </div>

        <!-- معلومات العميل والشحن -->
        <div class="info-section">
            <div class="info-box">
                <h3>معلومات العميل</h3>
                <p><strong>{{ $order->shipping_name }}</strong></p>
                <p>{{ $order->shipping_phone }}</p>
                @if($order->client && $order->client->email)
                    <p>{{ $order->client->email }}</p>
                @endif
            </div>
            <div class="info-box">
                <h3>عنوان الشحن</h3>
                <p><strong>{{ $order->shipping_city }}</strong> - {{ $order->shipping_region }}</p>
                <p>{{ $order->shipping_address }}</p>
            </div>
            <div class="info-box">
                <h3>معلومات الدفع</h3>
                <p><span class="label">طريقة الدفع:</span> {{ $order->payment_method->label() }}</p>
                <p><span class="label">حالة الدفع:</span> {{ $order->payment_status->label() }}</p>
                @if($order->paid_at)
                    <p><span class="label">تاريخ الدفع:</span> {{ $order->paid_at->format('Y-m-d') }}</p>
                @endif
            </div>
        </div>

        <!-- جدول المنتجات -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>المنتج</th>
                    <th style="width: 80px;">الكمية</th>
                    <th style="width: 100px;">السعر</th>
                    <th style="width: 80px;">الخصم</th>
                    <th style="width: 100px;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <div class="product-name">{{ $item->product_name }}</div>
                            @if($item->product_sku)
                                <div class="product-sku">SKU: {{ $item->product_sku }}</div>
                            @endif
                            @if($item->formatted_options)
                                <div class="product-sku">{{ $item->formatted_options }}</div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td>{{ $order->formatMoney($item->unit_price) }}</td>
                        <td class="discount">{{ $item->discount > 0 ? number_format($item->discount, 2) : '-' }}</td>
                        <td><strong>{{ $order->formatMoney($item->total) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- ملخص المبالغ -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>المجموع الفرعي</td>
                    <td>{{ $order->formatMoney($order->subtotal) }}</td>
                </tr>
                @if($order->discount_amount > 0)
                    <tr class="discount">
                        <td>الخصم @if($order->coupon)({{ $order->coupon->code }})@endif</td>
                        <td>- {{ $order->formatMoney($order->discount_amount) }}</td>
                    </tr>
                @endif
                @if($order->tax_amount > 0)
                    <tr>
                        <td>ضريبة القيمة المضافة (15%)</td>
                        <td>{{ $order->formatMoney($order->tax_amount) }}</td>
                    </tr>
                @endif
                <tr>
                    <td>تكلفة الشحن</td>
                    <td>{{ $order->formatMoney($order->shipping_cost) }}</td>
                </tr>
                @if($order->shipping_tax_amount > 0)
                    <tr>
                        <td>ضريبة الشحن</td>
                        <td>{{ $order->formatMoney($order->shipping_tax_amount) }}</td>
                    </tr>
                @endif
                @if($order->total_extra_fees > 0)
                    <tr>
                        <td>رسوم إضافية</td>
                        <td>{{ $order->formatMoney($order->total_extra_fees) }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td>الإجمالي</td>
                    <td>{{ $order->formatMoney($order->total_amount) }}</td>
                </tr>
            </table>
        </div>

        <!-- ملاحظات -->
        @if($order->notes || $order->is_gift)
            <div class="notes-section">
                <h3>ملاحظات</h3>
                @if($order->notes)
                    <p>{{ $order->notes }}</p>
                @endif
                @if($order->is_gift && $order->gift_message)
                    <p><strong>رسالة الهدية:</strong> {{ $order->gift_message }}</p>
                @endif
            </div>
        @endif

        <!-- الباركود -->
        <div class="barcode">
            <div class="barcode-text">{{ $order->order_number }}</div>
        </div>

        <!-- التذييل -->
        <div class="footer">
            <p>شكراً لتسوقكم معنا</p>
            @php
                $footerContact = array_filter([
                    \App\Support\OrganizationInfo::email() ?: null,
                    \App\Support\OrganizationInfo::phone() ? 'هاتف: ' . \App\Support\OrganizationInfo::phone() : null,
                ]);
            @endphp
            @if ($footerContact)
                <p>للاستفسارات: {{ implode(' | ', $footerContact) }}</p>
            @endif
            <p>تم الطباعة بتاريخ: {{ now()->format('Y-m-d H:i') }}</p>
        </div>
    </div>
</body>
</html>
