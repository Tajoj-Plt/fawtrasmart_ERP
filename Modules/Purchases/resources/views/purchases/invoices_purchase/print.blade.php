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
            padding: 20px;
            direction: rtl;
            font-weight: bold;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }

        /* Receipt Container */
        .receipt-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }

        .receipt {
            width: 80mm;
            /* Standard thermal receipt width */
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

        /* Invoice To Section */
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

        /* Print Styles */
        @media print {
            body {
                background-color: white;
                padding: 0;
                margin: 0;
                display: block !important;
                width: 80mm !important;
                font-weight: bold !important;
            }

            .receipt {
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0 auto !important;
            }

            .receipt-container {
                min-height: auto;
            }
        }

        /* Responsive Styles */
        @media (max-width: 576px) {
            .receipt {
                width: 100%;
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
                    @include('purchases::partials.print-header')
                </div>

                <!-- Invoice To (Supplier Info) -->
                <div class="invoice-to">
                    <p class="mb-0">فاتورة من:
                        {{ optional($purchaseInvoice->supplier)->trade_name ?? 'غير محدد' }}
                    </p>
                    <p class="mb-0">{{ optional($purchaseInvoice->supplier)->full_address ?? 'غير متوفر' }}</p>
                    <h1 class="mb-0">{{ optional($purchaseInvoice->supplier)->number_suply ?? 'غير متوفر' }}</h1>
                    <p class="mb-0">الرقم الضريبي: {{ optional($purchaseInvoice->supplier)->tax_number ?? 'غير متوفر' }}</p>
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
                        <span>طريقة الدفع:</span>
                        <span>
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
                </div>

                <!-- Invoice Items -->
                <div class="invoice-items">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="38%">المنتج</th>
                                <th width="12%">الكمية</th>
                                <th width="15%">السعر</th>
                                <th width="12%">الخصم</th>
                                <th width="18%">المجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($purchaseInvoice->items && $purchaseInvoice->items->count() > 0)
                                @foreach ($purchaseInvoice->items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td style="text-align: right;">{{ optional($item->product)->name ?? $item->item }}@if($item->unit_name) - {{ $item->unit_name }}@endif</td>
                                        <td>{{ number_format($item->quantity, 0) }}</td>
                                        <td>{{ number_format($item->unit_price, 2) }}</td>
                                        <td style="color: #dc3545;">
                                            @if($item->discount > 0)
                                                {{ number_format($item->discount, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ number_format($item->total, 2) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6">لا توجد عناصر</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Invoice Summary -->
                <div class="invoice-summary">
                    <div class="summary-row">
                        <span>المجموع الفرعي:</span>
                        <span>{{ number_format($purchaseInvoice->subtotal ?? 0, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                    </div>

                    @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseInvoice->total_discount > 0)
                        <div class="summary-row" style="color: #dc3545;">
                            <span>الخصم:</span>
                            <span>- {{ number_format($purchaseInvoice->total_discount, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                        <div class="summary-row" style="border-top: 1px solid #ddd; padding-top: 5px; margin-top: 5px;">
                            <span>المجموع بعد الخصم:</span>
                            <span>{{ number_format(($purchaseInvoice->subtotal ?? 0) - ($purchaseInvoice->total_discount ?? 0), 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                    @endif

                    @if ($purchaseInvoice->total_tax > 0)
                        <div class="summary-row">
                            <span>ضريبة القيمة المضافة:</span>
                            <span>{{ number_format($purchaseInvoice->total_tax, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                    @endif

                    <div class="summary-row" style="font-weight: bold; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; font-size: 1.1em;">
                        <span>المجموع الإجمالي:</span>
                        <span>{{ number_format($purchaseInvoice->grand_total, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                    </div>

                    @if ($purchaseInvoice->shipping_cost > 0)
                        <div class="summary-row">
                            <span>تكلفة الشحن:</span>
                            <span>{{ number_format($purchaseInvoice->shipping_cost, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                    @endif

                    @if ($purchaseInvoice->adjustment_amount > 0)
                        <div class="summary-row">
                            <span>{{ $purchaseInvoice->adjustment_label ?? 'تسوية' }}:</span>
                            <span>
                                {{ $purchaseInvoice->adjustment_type === 'discount' ? '-' : '+' }}
                                {{ number_format(abs($purchaseInvoice->adjustment_amount), 2) }} {{ \App\Support\CurrencySettings::symbol() }}
                            </span>
                        </div>
                    @endif

                    @if ($purchaseInvoice->advance_payment > 0)
                        <div class="summary-row">
                            <span>الدفعة المقدمة:</span>
                            <span>{{ number_format($purchaseInvoice->advance_payment, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        </div>
                    @endif

                    <div class="summary-row">
                        <span>المبلغ المستحق:</span>
                        <span>{{ number_format($purchaseInvoice->due_value, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                    </div>
                </div>

                <!-- Signature Section -->
                <div class="signature">
                    <p>الاسم: ________________</p>
                    <p>التوقيع: _______________</p>
                    <p class="thank-you">شكراً لتعاملكم معنا</p>
                </div>

            </div>
        </div>
    </div>

    <script>
        // طباعة تلقائية عند تحميل الصفحة
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };
    </script>
</body>

</html>
