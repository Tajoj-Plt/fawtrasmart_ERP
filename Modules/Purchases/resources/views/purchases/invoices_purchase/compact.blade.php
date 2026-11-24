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
            background-color: #f0f0f0;
            padding: 10px;
            direction: rtl;
            font-weight: bold;
            /* A4 size */
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
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
            padding: 10px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }

        /* Receipt Header */
        .receipt-header {
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
            margin-bottom: 10px;
            text-align: center;
        }

        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Supplier Section */
        .invoice-to {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        /* Invoice Details Section */
        .invoice-details {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ccc;
        }

        /* Invoice Items Table */
        .invoice-items {
            margin-bottom: 10px;
        }

        .table {
            width: 100%;
            margin-bottom: 10px;
            font-size: 12px;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            text-align: center;
            padding: 5px;
            border-bottom: 1px dashed #ddd;
        }

        .table th {
            background-color: #f5f5f5;
            border-bottom: 1px solid #333;
        }

        /* Invoice Summary */
        .invoice-summary {
            border-top: 1px dashed #ccc;
            padding-top: 10px;
            margin-bottom: 15px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        /* Signature */
        .signature {
            margin: 15px auto 0;
            padding-top: 10px;
            border-top: 1px dashed #333;
            width: 90%;
            text-align: center;
        }

        .thank-you {
            font-style: italic;
            margin-top: 5px;
        }

        .print-button {
            display: block;
            margin: 15px auto;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }

        .print-button:hover {
            background-color: #0056b3;
        }

        .no-print {
            display: block;
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
                font-weight: bold !important;
            }

            .receipt {
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 10px;
                margin: 0 auto !important;
            }

            .receipt-container {
                min-height: auto;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Responsive Styles */
        @media (max-width: 576px) {
            .receipt {
                width: 100%;
                padding: 5px;
            }
            
            .table {
                font-size: 10px;
            }
            
            .table th,
            .table td {
                padding: 3px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="receipt-container">
            <div class="receipt">
                <button class="print-button no-print" onclick="window.print()">طباعة الفاتورة</button>
                
                <!-- Receipt Header -->
                <div class="receipt-header">
                    <h1 class="receipt-title">{{ __('purchase_cycle.documents.purchase_invoice') }}</h1>
                    @include('purchases::partials.print-header')
                </div>

                <!-- Supplier Section -->
                <div class="invoice-to">
                    <p class="mb-0">فاتورة من: {{ optional($purchaseInvoice->supplier)->trade_name ?? 'غير محدد' }}</p>
                    <p class="mb-0">كود المورد: {{ optional($purchaseInvoice->supplier)->number_suply ?? 'غير متوفر' }}</p>
                    @if(optional($purchaseInvoice->supplier)->mobile)
                    <p class="mb-0">رقم جوال المورد: {{ optional($purchaseInvoice->supplier)->mobile }}</p>
                    @endif
                </div>

                <!-- Invoice Details -->
                <div class="invoice-details">
                    <div class="summary-row">
                        <span>رقم الفاتورة:</span>
                        <span>{{ $purchaseInvoice->code }}</span>
                    </div>
                    <div class="summary-row">
                        <span>تاريخ الفاتورة:</span>
                        <span>{{ $purchaseInvoice->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="summary-row">
                        <span>الحالة:</span>
                        <span>
                            @if($purchaseInvoice->payment_status == 'paid')
                                مدفوعة بالكامل
                            @elseif($purchaseInvoice->payment_status == 'partially_paid')
                                مدفوعة جزئياً
                            @elseif($purchaseInvoice->payment_status == 'unpaid')
                                غير مدفوعة
                            @else
                                قيد المراجعة
                            @endif
                        </span>
                    </div>
                </div>

                <!-- Invoice Items -->
                <div class="invoice-items">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="45%">المنتج</th>
                                <th width="15%">الكمية</th>
                                <th width="15%">السعر</th>
                                <th width="20%">المجموع</th>
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
                                    <td>{{ number_format($item->total, 2) }}</td>
                                </tr>
                                @endforeach
                            @else
                            <tr>
                                <td colspan="5">لا توجد منتجات مضافة</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Invoice Summary -->
                <div class="invoice-summary">
                    <div class="summary-row">
                        <span>المجموع الكلي:</span>
                        <span>{{ number_format($purchaseInvoice->grand_total, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                    </div>

                    @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseInvoice->total_discount > 0)
                    <div class="summary-row">
                        <span>الخصم:</span>
                        <span>{{ number_format($purchaseInvoice->total_discount, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                    </div>
                    @endif

                    @if($purchaseInvoice->shipping_cost > 0)
                    <div class="summary-row">
                        <span>تكلفة الشحن:</span>
                        <span>{{ number_format($purchaseInvoice->shipping_cost, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                    </div>
                    @endif

                    @if($purchaseInvoice->advance_payment > 0)
                    <div class="summary-row">
                        <span>الدفعة المقدمة:</span>
                        <span>{{ number_format($purchaseInvoice->advance_payment, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                    </div>
                    @endif

                    <div class="summary-row">
                        <span><strong>المبلغ المستحق:</strong></span>
                        <span><strong>{{ number_format($purchaseInvoice->due_value, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong></span>
                    </div>
                </div>

                @if ($purchaseInvoice->notes)
                <div class="notes">
                    <strong>ملاحظات:</strong> {{ $purchaseInvoice->notes }}
                </div>
                @endif

                <!-- Signature -->
                <div class="signature">
                    <p>توقيع المورد: ________________</p>
                    <p>توقيع المستلم: _______________</p>
                    <p class="thank-you">شكراً لتعاملكم معنا</p>
                </div>
                
                <button class="print-button no-print" onclick="window.print()" style="margin-top: 15px;">طباعة الفاتورة</button>
            </div>
        </div>
    </div>
</body>

</html>