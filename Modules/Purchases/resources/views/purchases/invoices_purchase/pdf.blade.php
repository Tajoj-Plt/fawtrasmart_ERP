<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة مشتريات #{{ $purchaseInvoice->code }}</title>
    <!-- Import Arabic font -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap');

        /* Global Styles */
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #ffffff;
            padding: 20px;
            direction: rtl;
            font-weight: 400;
            /* A4 size */
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            color: #333;
        }

        /* Receipt Container */
        .receipt-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }

        .receipt {
            width: 210mm;
            /* A4 width */
            max-width: 100%;
            background-color: white;
            padding: 30px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            margin: 0 auto;
            border-radius: 8px;
        }

        /* Receipt Header */
        .receipt-header {
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
            margin-bottom: 25px;
            text-align: center;
        }

        .receipt-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #007bff;
        }

        .company-info {
            font-size: 16px;
            color: #666;
            margin: 5px 0;
        }

        /* Two-column layout */
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            gap: 20px;
        }

        .info-box {
            flex: 1;
            padding: 15px;
            border-radius: 6px;
            background-color: #f8f9fa;
        }

        .info-box-title {
            font-weight: 700;
            margin-bottom: 10px;
            color: #007bff;
            font-size: 18px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 8px;
        }

        .info-item {
            margin-bottom: 8px;
            display: flex;
        }

        .info-label {
            font-weight: 600;
            min-width: 120px;
            color: #495057;
        }

        .info-value {
            flex: 1;
        }

        /* Invoice Items Table */
        .invoice-items {
            margin: 25px 0;
        }

        .table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 14px;
            border-collapse: collapse;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.03);
            border-radius: 6px;
            overflow: hidden;
        }

        .table th,
        .table td {
            text-align: center;
            padding: 12px 8px;
            border: 1px solid #dee2e6;
        }

        .table th {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .table tbody tr:hover {
            background-color: #e9ecef;
        }

        /* Invoice Summary */
        .invoice-summary {
            margin: 25px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #dee2e6;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 500;
            color: #495057;
        }

        .summary-value {
            font-weight: 600;
        }

        .total-row {
            font-size: 18px;
            font-weight: 700;
            color: #007bff;
        }

        /* Signature */
        .signature-section {
            margin: 40px 0 20px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }

        .signature-box {
            text-align: center;
            flex: 1;
            min-width: 200px;
        }

        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            padding-top: 10px;
        }

        .thank-you {
            text-align: center;
            font-style: italic;
            margin-top: 30px;
            color: #6c757d;
            font-size: 16px;
        }

        /* Print Styles */
        @media print {
            body {
                background-color: white;
                padding: 0;
                margin: 0;
                display: block !important;
                width: 210mm !important;
                height: 297mm !important;
                font-weight: 400 !important;
            }

            .receipt {
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 20px;
                margin: 0 auto !important;
                border-radius: 0;
            }

            .receipt-container {
                min-height: auto;
            }
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .receipt {
                padding: 15px;
            }
            
            .info-section {
                flex-direction: column;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 8px 4px;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="receipt-container">
            <div class="receipt">
                <!-- Receipt Header -->
                <div class="receipt-header">
                    <h1 class="receipt-title">{{ __('purchase_cycle.documents.purchase_invoice') }}</h1>
                    {{-- بيانات المنشأة من «معلومات الحساب» لا نصّاً ثابتاً --}}
                    @include('purchases::partials.print-header')
                </div>

                <!-- Info Section -->
                <div class="info-section">
                    <div class="info-box">
                        <div class="info-box-title">معلومات المورد</div>
                        <div class="info-item">
                            <span class="info-label">اسم المورد:</span>
                            <span class="info-value">{{ optional($purchaseInvoice->supplier)->trade_name ?? 'غير محدد' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">العنوان:</span>
                            <span class="info-value">{{ optional($purchaseInvoice->supplier)->full_address ?? 'غير متوفر' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">كود المورد:</span>
                            <span class="info-value">{{ optional($purchaseInvoice->supplier)->number_suply ?? 'غير متوفر' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الرقم الضريبي:</span>
                            <span class="info-value">{{ optional($purchaseInvoice->supplier)->tax_number ?? 'غير متوفر' }}</span>
                        </div>
                        @if(optional($purchaseInvoice->supplier)->mobile)
                        <div class="info-item">
                            <span class="info-label">رقم الجوال:</span>
                            <span class="info-value">{{ optional($purchaseInvoice->supplier)->mobile }}</span>
                        </div>
                        @endif
                    </div>
                    
                    <div class="info-box">
                        <div class="info-box-title">تفاصيل الفاتورة</div>
                        <div class="info-item">
                            <span class="info-label">رقم الفاتورة:</span>
                            <span class="info-value">{{ $purchaseInvoice->code }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاريخ الإصدار:</span>
                            <span class="info-value">{{ $purchaseInvoice->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">طريقة الدفع:</span>
                            <span class="info-value">
                                @if($purchaseInvoice->payment_method == 1)
                                    نقداً
                                @elseif($purchaseInvoice->payment_method == 2)
                                    تحويل بنكي
                                @elseif($purchaseInvoice->payment_method == 3)
                                    شيك
                                @else
                                    غير محدد
                                @endif
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الحالة:</span>
                            <span class="info-value">
                                @if($purchaseInvoice->payment_status == 'paid')
                                    <span style="color: #28a745; font-weight: 600;">مدفوعة بالكامل</span>
                                @elseif($purchaseInvoice->payment_status == 'partially_paid')
                                    <span style="color: #ffc107; font-weight: 600;">مدفوعة جزئياً</span>
                                @elseif($purchaseInvoice->payment_status == 'unpaid')
                                    <span style="color: #dc3545; font-weight: 600;">غير مدفوعة</span>
                                @else
                                    <span style="color: #6c757d; font-weight: 600;">قيد المراجعة</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="invoice-items">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="38%">المنتج</th>
                                <th width="12%">الكمية</th>
                                <th width="15%">سعر الوحدة</th>
                                <th width="12%">الخصم</th>
                                <th width="18%">المجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($purchaseInvoice->items && $purchaseInvoice->items->count() > 0)
                                @foreach ($purchaseInvoice->items as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td style="text-align: right;">{{ optional($item->product)->name ?? $item->item }}@if($item->unit_name) - {{ $item->unit_name }}@endif</td>
                                    <td>{{ number_format($item->quantity, 0) }}</td>
                                    <td>{{ number_format($item->unit_price, 2) }}</td>
                                    <td>{{ number_format($item->discount, 2) }}</td>
                                    <td>{{ number_format($item->total, 2) }}</td>
                                </tr>
                                @endforeach
                            @else
                            <tr>
                                <td colspan="6">لا توجد منتجات مضافة</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Invoice Summary -->
                <div class="invoice-summary">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="summary-label">المجموع الفرعي:</span>
                            <span class="summary-value">{{ number_format($purchaseInvoice->subtotal, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>

                        @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseInvoice->total_discount > 0)
                        <div class="summary-item">
                            <span class="summary-label">إجمالي الخصم:</span>
                            <span class="summary-value">- {{ number_format($purchaseInvoice->total_discount, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                        @endif

                        @if($purchaseInvoice->total_tax > 0)
                        <div class="summary-item">
                            <span class="summary-label">إجمالي الضريبة:</span>
                            <span class="summary-value">+ {{ number_format($purchaseInvoice->total_tax, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                        @endif

                        @if($purchaseInvoice->shipping_cost > 0)
                        <div class="summary-item">
                            <span class="summary-label">تكلفة الشحن:</span>
                            <span class="summary-value">+ {{ number_format($purchaseInvoice->shipping_cost, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                        @endif

                        @if($purchaseInvoice->advance_payment > 0)
                        <div class="summary-item">
                            <span class="summary-label">الدفعة المقدمة:</span>
                            <span class="summary-value">- {{ number_format($purchaseInvoice->advance_payment, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                        @endif

                        <div class="summary-item total-row">
                            <span class="summary-label">المجموع النهائي:</span>
                            <span class="summary-value">{{ number_format($purchaseInvoice->grand_total, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>

                        @if($purchaseInvoice->due_value > 0)
                        <div class="summary-item">
                            <span class="summary-label">المبلغ المستحق:</span>
                            <span class="summary-value">{{ number_format($purchaseInvoice->due_value, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Signature -->
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line">توقيع المورد</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line">توقيع المستلم</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line">ختم الشركة</div>
                    </div>
                </div>
                
                <p class="thank-you">شكراً لتعاملكم معنا</p>
            </div>
        </div>
    </div>
</body>

</html>