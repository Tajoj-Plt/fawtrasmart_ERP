{{--
    قيمة المخزون — PDF.

    أنماطٌ ثابتة بلا متغيّرات CSS: مولّد الـPDF لا يقرأ `var()`.
    وحُذف تكرار الإجماليات ثلاث مرّات بحسابها مرّةً واحدة.
--}}
@php
    $totalSale = $products->sum(fn ($row) => optional($row->product)->sale_price * $row->quantity);
    $totalPurchase = $products->sum(fn ($row) => optional($row->product)->purchase_price * $row->quantity);
    $currency = App\Support\CurrencySettings::code();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قيمة المخزون — {{ $storehouse->name }}</title>
    <style>
        body {
            font-family: 'aealarabiya', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #2f3349;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2f3349;
        }
        .header h1 { margin: 0; font-size: 17px; }
        .header h2 { margin: 3px 0 0; font-size: 13px; color: #6e6b7b; font-weight: normal; }

        .meta {
            margin-bottom: 10px;
            font-size: 10px;
            color: #6e6b7b;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d8d6de; padding: 5px; text-align: center; }
        th { background-color: #f3f2f7; font-weight: bold; font-size: 10px; }
        td.name { text-align: right; font-weight: bold; }

        /* تظليل الصفوف الزوجية: ثمانية أعمدة رقمية يصعب تتبّعها بلا خطّ يقود العين */
        tr.alt td { background-color: #fafafc; }

        tfoot td {
            background-color: #f3f2f7;
            font-weight: bold;
        }

        .empty { padding: 16px; color: #6e6b7b; }

        .footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #d8d6de;
            text-align: center;
            font-size: 9px;
            color: #6e6b7b;
        }
    </style>
</head>
<body>
    <div class="header">
        @include('stock::components.print-org-head')
        <h1>قيمة المخزون التقديرية</h1>
        <h2>{{ $storehouse->name }}</h2>
    </div>

    <div class="meta">
        تاريخ التقرير: {{ now()->format('Y-m-d H:i') }} — العملة: {{ $currency }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:12%">الكود</th>
                <th style="width:22%">الصنف</th>
                <th style="width:8%">الكمية</th>
                <th style="width:12%">سعر البيع</th>
                <th style="width:12%">متوسط الشراء</th>
                <th style="width:13%">إجمالي البيع</th>
                <th style="width:16%">الربح المتوقَّع</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $row)
                @php
                    $product = $row->product;
                    $rowPurchase = optional($product)->purchase_price * $row->quantity;
                    $rowSale = optional($product)->sale_price * $row->quantity;
                @endphp

                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ optional($product)->barcode ?: '—' }}</td>
                    <td class="name">{{ optional($product)->name ?? 'منتج محذوف' }}</td>
                    <td>{{ number_format($row->quantity) }}</td>
                    <td>{{ number_format(optional($product)->sale_price, 2) }}</td>
                    <td>{{ number_format(optional($product)->purchase_price, 2) }}</td>
                    <td>{{ number_format($rowSale, 2) }}</td>
                    <td>{{ number_format($rowSale - $rowPurchase, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">لا أصناف في هذا المستودع</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">الإجمالي</td>
                <td>{{ number_format($totalSale, 2) }}</td>
                <td>{{ number_format($totalSale - $totalPurchase, 2) }}</td>
            </tr>
            <tr>
                <td colspan="6">إجمالي التكلفة</td>
                <td colspan="2">{{ number_format($totalPurchase, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        عدد الأصناف: {{ $products->count() }}
    </div>
</body>
</html>
