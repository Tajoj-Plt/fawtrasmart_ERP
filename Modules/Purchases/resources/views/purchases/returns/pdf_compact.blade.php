<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>مرتجع مشتريات - مختصر</title>
    <style>
        body {
            direction: rtl;
            font-family: aealarabiya;
            padding: 10px;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding: 5px;
        }

        .info-table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
            font-size: 11px;
        }

        .info-table td {
            padding: 3px;
            border-bottom: 1px dotted #999;
        }

        .products-table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
            font-size: 11px;
        }

        .products-table th,
        .products-table td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
        }

        .totals-table {
            width: 50%;
            margin: 10px 0;
            border-collapse: collapse;
            font-size: 11px;
            float: left;
        }

        .totals-table td {
            padding: 3px;
            border: 1px solid #000;
        }

        .signatures {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h3>مرتجع مشتريات - مختصر</h3>
        <p>رقم المرتجع: {{ $purchaseInvoiceReturn->code }} | التاريخ: {{ $purchaseInvoiceReturn->date }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>المورد:</strong> {{ optional($purchaseInvoiceReturn->supplier)->trade_name }}</td>
            <td><strong>الإجمالي:</strong> {{ number_format($purchaseInvoiceReturn->grand_total, 2) }}</td>
        </tr>
    </table>

    <h4>المنتجات المرتجعة:</h4>
    <table class="products-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>السعر</th>
                <th>المجموع</th>
            </tr>
        </thead>
        <tbody>
            @if($purchaseInvoiceReturn->invoiceItems && $purchaseInvoiceReturn->invoiceItems->count() > 0)
                @foreach($purchaseInvoiceReturn->invoiceItems as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ optional($item->product)->name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }}</td>
                        <td>{{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5">لا توجد منتجات مرتجعة</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>المجموع الفرعي:</td>
            <td>{{ number_format($purchaseInvoiceReturn->subtotal, 2) }}</td>
        </tr>
        {{-- سطرٌ لكل مبلغٍ له وجود فقط: الصفر لا يُطبع — نفس شرط
             صندوق الإجماليات في الشاشة --}}
        @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseInvoiceReturn->total_discount > 0)
            <tr>
                <td>الخصم:</td>
                <td>{{ number_format($purchaseInvoiceReturn->total_discount, 2) }}</td>
            </tr>
        @endif
        @if ($purchaseInvoiceReturn->shipping_cost > 0)
            <tr>
                <td>الشحن:</td>
                <td>{{ number_format($purchaseInvoiceReturn->shipping_cost, 2) }}</td>
            </tr>
        @endif
        @if ($purchaseInvoiceReturn->total_tax > 0)
            <tr>
                <td>الضريبة:</td>
                <td>{{ number_format($purchaseInvoiceReturn->total_tax, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>الإجمالي:</td>
            <td>{{ number_format($purchaseInvoiceReturn->grand_total, 2) }}</td>
        </tr>
        @if (($purchaseInvoiceReturn->advance_payment ?? 0) > 0)
            <tr>
                <td>الدفعة المقدمة:</td>
                <td>{{ number_format($purchaseInvoiceReturn->advance_payment, 2) }}</td>
            </tr>
            <tr>
                <td>المبلغ المستحق:</td>
                <td>{{ number_format($purchaseInvoiceReturn->due_value ?? 0, 2) }}</td>
            </tr>
        @endif
    </table>

    <div class="signatures">
        <div>
            <p>توقيع المورد</p>
            <div style="margin-top: 30px; border-top: 1px solid #000; width: 100px;"></div>
        </div>
        <div>
            <p>توقيع المستلم</p>
            <div style="margin-top: 30px; border-top: 1px solid #000; width: 100px;"></div>
        </div>
    </div>
</body>

</html>