<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>عرض سعر - ملخص الطلب</title>
    <style>
        body {
            direction: rtl;
            font-family: aealarabiya;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            padding: 0;
            font-size: 18px;
        }

        .summary-box {
            border: 1px solid #000;
            padding: 15px;
            margin: 15px 0;
            background-color: #f9f9f9;
        }

        .summary-item {
            margin: 8px 0;
            font-size: 14px;
        }

        .summary-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }

        .data-table th {
            background-color: #e0e0e0;
        }

        .totals-section {
            margin-top: 20px;
            text-align: left;
        }

        .total-row {
            font-size: 14px;
            margin: 5px 0;
        }

        .total-label {
            display: inline-block;
            width: 150px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>عرض سعر - ملخص الطلب</h1>
        <p>رقم العرض: {{ $purchaseQuotation->code }}</p>
        <p>تاريخ العرض: {{ $purchaseQuotation->date }}</p>
    </div>

    <div class="summary-box">
        <div class="summary-item">
            <span class="summary-label">المورد:</span>
            <span>{{ $purchaseQuotation->supplier->trade_name ?? 'غير محدد' }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">منشئ الطلب:</span>
            <span>{{ optional($purchaseQuotation->creator)->name ?? 'غير محدد' }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">عدد المنتجات:</span>
            <span>{{ $purchaseQuotation->items->count() }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">صالح لمدة:</span>
            <span>{{ $purchaseQuotation->valid_days }} يوم</span>
        </div>
        @if($purchaseQuotation->notes)
        <div class="summary-item">
            <span class="summary-label">الملاحظات:</span>
            <span>{{ $purchaseQuotation->notes }}</span>
        </div>
        @endif
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
    @foreach($purchaseQuotation->items as $item)
        <tr>
            <td>{{ $item->product->name ?? $item->item }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format($item->unit_price, 2) }}</td>
            <td>{{ number_format($item->total, 2) }}</td>
        </tr>
    @endforeach
        </tbody>
    </table>

    <div class="totals-section">
        <div class="total-row">
            <span class="total-label">المجموع الفرعي:</span>
            <span>{{ number_format($purchaseQuotation->subtotal, 2) }}</span>
        </div>
        @if (\Modules\Purchases\Services\PurchaseSettings::enabled('total_discounts') && $purchaseQuotation->total_discount > 0)
        <div class="total-row">
            <span class="total-label">إجمالي الخصم:</span>
            <span>-{{ number_format($purchaseQuotation->total_discount, 2) }}</span>
        </div>
        @endif
        @if($purchaseQuotation->total_tax > 0)
        <div class="total-row">
            <span class="total-label">إجمالي الضريبة:</span>
            <span>{{ number_format($purchaseQuotation->total_tax, 2) }}</span>
        </div>
        @endif
        @if($purchaseQuotation->shipping_cost > 0)
        <div class="total-row">
            <span class="total-label">تكلفة الشحن:</span>
            <span>{{ number_format($purchaseQuotation->shipping_cost, 2) }}</span>
        </div>
        @endif
        <div class="total-row" style="font-weight: bold; font-size: 16px; border-top: 1px solid #000; padding-top: 10px;">
            <span class="total-label">المجموع النهائي:</span>
            <span>{{ number_format($purchaseQuotation->grand_total, 2) }}</span>
        </div>
    </div>
</body>
</html>