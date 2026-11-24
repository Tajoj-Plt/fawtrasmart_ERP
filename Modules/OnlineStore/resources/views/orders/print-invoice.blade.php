<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة #{{ $invoice->code ?? $invoice->id }} - {{ $settings['store_name'] ?? 'المتجر الإلكتروني' }}</title>
    <style>
        /* ============================================
           Print Invoice Styles
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            direction: rtl;
        }

        .invoice-container {
            max-width: 800px;
            margin: 20px auto;
            background: #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        /* Header Section */
        .invoice-header {
            background: linear-gradient(135deg, {{ $settings['theme_color'] ?? '#7367f0' }} 0%, {{ $settings['secondary_color'] ?? '#10B981' }} 100%);
            color: #fff;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .store-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .store-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            background: #fff;
            padding: 10px;
            border-radius: 8px;
        }

        .store-logo-placeholder {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        .store-details h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .store-details p {
            font-size: 13px;
            opacity: 0.9;
        }

        .invoice-title {
            text-align: left;
        }

        .invoice-title h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .invoice-number {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Info Section */
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            padding: 25px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .info-box {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border-right: 3px solid {{ $settings['theme_color'] ?? '#7367f0' }};
        }

        .info-box h4 {
            color: #82868b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .info-box p {
            margin: 3px 0;
            font-size: 13px;
        }

        .info-box strong {
            color: #5e5873;
        }

        /* Order Info Banner */
        .order-info-banner {
            background: linear-gradient(90deg, rgba(115, 103, 240, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }

        .order-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-info-item .label {
            color: #82868b;
            font-size: 12px;
        }

        .order-info-item .value {
            font-weight: 600;
            color: #5e5873;
        }

        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-paid {
            background-color: rgba(40, 199, 111, 0.15);
            color: #28c76f;
        }

        .status-pending {
            background-color: rgba(255, 159, 67, 0.15);
            color: #ff9f43;
        }

        .status-partial {
            background-color: rgba(0, 207, 232, 0.15);
            color: #00cfe8;
        }

        /* Products Table */
        .products-section {
            padding: 20px 30px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #5e5873;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table thead th {
            background: #f8f9fa;
            color: #5e5873;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 15px;
            text-align: right;
            border-bottom: 2px solid #e9ecef;
        }

        .products-table thead th:last-child {
            text-align: left;
        }

        .products-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .products-table tbody tr:last-child td {
            border-bottom: none;
        }

        .products-table tbody tr:hover {
            background-color: rgba(115, 103, 240, 0.02);
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .product-image-placeholder {
            width: 50px;
            height: 50px;
            background: #f1f1f1;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 20px;
        }

        .product-info h5 {
            font-size: 14px;
            font-weight: 600;
            color: #5e5873;
            margin-bottom: 3px;
        }

        .product-info .sku {
            font-size: 12px;
            color: #82868b;
        }

        .product-variants {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 5px;
        }

        .variant-tag {
            background: rgba(115, 103, 240, 0.1);
            color: #7367f0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        .quantity-cell {
            text-align: center;
            font-weight: 600;
            color: #7367f0;
        }

        .price-cell {
            text-align: left;
            font-weight: 600;
            color: #5e5873;
        }

        /* Summary Section */
        .summary-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 25px 30px;
            background: #f8f9fa;
        }

        .notes-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .notes-box h4 {
            font-size: 14px;
            font-weight: 600;
            color: #5e5873;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .notes-box p {
            font-size: 13px;
            color: #6e6b7b;
            line-height: 1.8;
        }

        .totals-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .total-row.discount {
            color: #28c76f;
        }

        .total-row.shipping {
            color: #00cfe8;
        }

        .total-row.tax {
            color: #ff9f43;
        }

        .total-row.grand-total {
            border-top: 2px solid #e9ecef;
            padding-top: 15px;
            margin-top: 10px;
            font-size: 18px;
            font-weight: 700;
            color: {{ $settings['theme_color'] ?? '#7367f0' }};
        }

        .total-row .value {
            font-weight: 600;
        }

        /* Footer Section */
        .invoice-footer {
            padding: 25px 30px;
            background: linear-gradient(135deg, {{ $settings['theme_color'] ?? '#7367f0' }}15 0%, {{ $settings['secondary_color'] ?? '#10B981' }}15 100%);
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .footer-message {
            font-size: 16px;
            font-weight: 600;
            color: {{ $settings['theme_color'] ?? '#7367f0' }};
            margin-bottom: 15px;
        }

        .footer-contact {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #6e6b7b;
        }

        .footer-contact-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .footer-legal {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            font-size: 11px;
            color: #999;
        }

        /* QR Code Section */
        .qr-section {
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e9ecef;
        }

        .qr-code {
            width: 100px;
            height: 100px;
            background: #f1f1f1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
        }

        .signature-area {
            text-align: center;
        }

        .signature-line {
            width: 200px;
            border-bottom: 1px solid #333;
            margin: 40px auto 10px;
        }

        .signature-label {
            font-size: 12px;
            color: #82868b;
        }

        /* Print Styles */
        @media print {
            body {
                background: #fff;
                font-size: 12px;
            }

            .invoice-container {
                max-width: 100%;
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }

            .invoice-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            .products-table {
                font-size: 11px;
            }

            .product-image,
            .product-image-placeholder {
                width: 40px;
                height: 40px;
            }

            @page {
                size: A4;
                margin: 10mm;
            }
        }

        /* Print Button */
        .print-actions {
            position: fixed;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .btn-print {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: {{ $settings['theme_color'] ?? '#7367f0' }};
            color: #fff;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Print Actions -->
    <div class="print-actions no-print">
        <button class="btn-print btn-primary" onclick="window.print()">
            <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            طباعة الفاتورة
        </button>
        <button class="btn-print btn-secondary" onclick="window.close()">
            <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
            </svg>
            إغلاق
        </button>
    </div>

    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="store-info">
                @if(!empty($settings['store_logo']))
                    <img src="{{ asset($settings['store_logo']) }}" alt="{{ $settings['store_name'] ?? 'Logo' }}" class="store-logo">
                @else
                    <div class="store-logo-placeholder">🏪</div>
                @endif
                <div class="store-details">
                    <h1>{{ $settings['store_name'] ?? 'المتجر الإلكتروني' }}</h1>
                    @if(!empty($settings['store_name_en']))
                        <p>{{ $settings['store_name_en'] }}</p>
                    @endif
                    @if(!empty($settings['store_address']))
                        <p>{{ $settings['store_address'] }}</p>
                    @endif
                </div>
            </div>
            <div class="invoice-title">
                <h2>فاتورة ضريبية</h2>
                <span class="invoice-number">رقم: {{ $invoice->code ?? $invoice->id }}</span>
            </div>
        </div>

        <!-- Invoice Info -->
        <div class="invoice-info">
            <div class="info-box">
                <h4>معلومات الفاتورة</h4>
                <p><strong>رقم الفاتورة:</strong> {{ $invoice->code ?? $invoice->id }}</p>
                <p><strong>تاريخ الإصدار:</strong> {{ $invoice->created_at->format('Y-m-d') }}</p>
                <p><strong>تاريخ الاستحقاق:</strong> {{ $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : $invoice->created_at->format('Y-m-d') }}</p>
                @if($order)
                    <p><strong>رقم الطلب:</strong> {{ $order->order_number }}</p>
                @endif
            </div>
            <div class="info-box">
                <h4>معلومات العميل</h4>
                @if($order && $order->client)
                    <p><strong>الاسم:</strong> {{ $order->client->trade_name ?? ($order->client->first_name . ' ' . $order->client->last_name) }}</p>
                    <p><strong>الهاتف:</strong> {{ $order->shipping_phone ?? $order->client->phone }}</p>
                    @if($order->client->email)
                        <p><strong>البريد:</strong> {{ $order->client->email }}</p>
                    @endif
                @elseif($invoice->client)
                    <p><strong>الاسم:</strong> {{ $invoice->client->trade_name ?? ($invoice->client->first_name . ' ' . $invoice->client->last_name) }}</p>
                    <p><strong>الهاتف:</strong> {{ $invoice->client->phone }}</p>
                @else
                    <p>عميل نقدي</p>
                @endif
            </div>
            <div class="info-box">
                <h4>عنوان الشحن</h4>
                @if($order)
                    <p><strong>المستلم:</strong> {{ $order->shipping_name }}</p>
                    <p><strong>المدينة:</strong> {{ $order->shipping_city }}</p>
                    <p><strong>المنطقة:</strong> {{ $order->shipping_region }}</p>
                    <p><strong>العنوان:</strong> {{ $order->shipping_address }}</p>
                @else
                    <p>غير محدد</p>
                @endif
            </div>
        </div>

        <!-- Order Info Banner -->
        @if($order)
        <div class="order-info-banner">
            <div class="order-info-item">
                <span class="label">طريقة الدفع:</span>
                <span class="value">{{ $order->payment_method->label() }}</span>
            </div>
            <div class="order-info-item">
                <span class="label">طريقة الشحن:</span>
                <span class="value">{{ $order->shippingMethod->name ?? 'قياسي' }}</span>
            </div>
            <div class="order-info-item">
                @php
                    $paymentStatusClass = match($invoice->payment_status ?? 3) {
                        1, 'paid' => 'status-paid',
                        2, 'partial' => 'status-partial',
                        default => 'status-pending',
                    };
                    $paymentStatusLabel = match($invoice->payment_status ?? 3) {
                        1, 'paid' => 'مدفوعة',
                        2, 'partial' => 'مدفوعة جزئياً',
                        default => 'غير مدفوعة',
                    };
                @endphp
                <span class="status-badge {{ $paymentStatusClass }}">{{ $paymentStatusLabel }}</span>
            </div>
        </div>
        @endif

        <!-- Products Section -->
        <div class="products-section">
            <h3 class="section-title">المنتجات</h3>
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>المنتج</th>
                        <th style="width: 80px; text-align: center;">الكمية</th>
                        <th style="width: 120px; text-align: left;">السعر</th>
                        <th style="width: 120px; text-align: left;">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @if($order && $order->items->count() > 0)
                        @foreach($order->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <div class="product-cell">
                                    @php
                                        $productImage = null;
                                        if($item->product && $item->product->images) {
                                            $images = json_decode($item->product->images, true);
                                            $productImage = is_array($images) && count($images) > 0 ? $images[0] : null;
                                        }
                                    @endphp
                                    @if($productImage)
                                        <img src="{{ asset($productImage) }}" alt="{{ $item->product_name }}" class="product-image">
                                    @else
                                        <div class="product-image-placeholder">📦</div>
                                    @endif
                                    <div class="product-info">
                                        <h5>{{ $item->product_name }}</h5>
                                        @if($item->product_sku)
                                            <span class="sku">SKU: {{ $item->product_sku }}</span>
                                        @endif
                                        @if(!empty($item->options['variant_label']))
                                            <div class="product-variants">
                                                <span class="variant-tag">{{ $item->options['variant_label'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="quantity-cell">{{ $item->quantity }}</td>
                            <td class="price-cell">{{ number_format($item->unit_price, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</td>
                            <td class="price-cell">{{ number_format($item->total, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</td>
                        </tr>
                        @endforeach
                    @elseif($invoice->items && $invoice->items->count() > 0)
                        @foreach($invoice->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <div class="product-cell">
                                    <div class="product-image-placeholder">📦</div>
                                    <div class="product-info">
                                        <h5>{{ $item->product->name ?? 'منتج' }}</h5>
                                        @if($item->product && $item->product->sku)
                                            <span class="sku">SKU: {{ $item->product->sku }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="quantity-cell">{{ $item->quantity }}</td>
                            <td class="price-cell">{{ number_format($item->unit_price ?? $item->price, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</td>
                            <td class="price-cell">{{ number_format(($item->unit_price ?? $item->price) * $item->quantity, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px;">لا توجد منتجات</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <div class="notes-box">
                <h4>ملاحظات</h4>
                @if($order && $order->customer_notes)
                    <p><strong>ملاحظات العميل:</strong> {{ $order->customer_notes }}</p>
                @endif
                @if($invoice->notes)
                    <p>{{ $invoice->notes }}</p>
                @else
                    <p>شكراً لتعاملكم معنا. نتمنى أن نكون قد حققنا رضاكم.</p>
                @endif
                @if(!empty($settings['store_policy']))
                    <p style="margin-top: 10px; font-size: 11px; color: #999;">{{ $settings['store_policy'] }}</p>
                @endif
            </div>
            <div class="totals-box">
                <div class="total-row">
                    <span>المجموع الفرعي</span>
                    <span class="value">{{ number_format($order ? $order->subtotal : $invoice->subtotal, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</span>
                </div>
                @if(($order ? $order->discount_amount : $invoice->discount_amount) > 0)
                <div class="total-row discount">
                    <span>الخصم</span>
                    <span class="value">- {{ number_format($order ? $order->discount_amount : $invoice->discount_amount, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</span>
                </div>
                @endif
                @if($order && $order->shipping_cost > 0)
                <div class="total-row shipping">
                    <span>الشحن</span>
                    <span class="value">{{ number_format($order->shipping_cost, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</span>
                </div>
                @endif
                @if($order && $order->shipping_tax_amount > 0)
                <div class="total-row tax">
                    <span>ضريبة الشحن</span>
                    <span class="value">{{ number_format($order->shipping_tax_amount, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</span>
                </div>
                @endif
                @if(($order ? $order->tax_amount : $invoice->tax_total) > 0)
                <div class="total-row tax">
                    <span>الضريبة ({{ $settings['tax_rate'] ?? 15 }}%)</span>
                    <span class="value">{{ number_format($order ? $order->tax_amount : $invoice->tax_total, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</span>
                </div>
                @endif
                <div class="total-row grand-total">
                    <span>الإجمالي</span>
                    <span>{{ number_format($order ? $order->total_amount : $invoice->grand_total, 2) }} {{ $settings['currency_symbol'] ?? 'ر.س' }}</span>
                </div>
            </div>
        </div>

        <!-- QR Code & Signature -->
        <div class="qr-section">
            <div class="qr-code">
                <!-- يمكن إضافة كود QR هنا -->
                <span style="font-size: 12px; color: #999;">QR Code</span>
            </div>
            <div class="signature-area">
                <div class="signature-line"></div>
                <span class="signature-label">توقيع المستلم</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <p class="footer-message">شكراً لتسوقكم من {{ $settings['store_name'] ?? 'متجرنا' }}</p>
            <div class="footer-contact">
                @if(!empty($settings['store_phone']))
                <div class="footer-contact-item">
                    <span>📞</span>
                    <span>{{ $settings['store_phone'] }}</span>
                </div>
                @endif
                @if(!empty($settings['store_email']))
                <div class="footer-contact-item">
                    <span>✉️</span>
                    <span>{{ $settings['store_email'] }}</span>
                </div>
                @endif
                @if(!empty($settings['store_website']))
                <div class="footer-contact-item">
                    <span>🌐</span>
                    <span>{{ $settings['store_website'] }}</span>
                </div>
                @endif
            </div>
            <p class="footer-legal">
                هذه الفاتورة صادرة إلكترونياً وهي صالحة بدون ختم أو توقيع
                @if(!empty($settings['vat_number']))
                    | الرقم الضريبي: {{ $settings['vat_number'] }}
                @endif
            </p>
        </div>
    </div>

    <script>
        // Auto print on load if needed
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
