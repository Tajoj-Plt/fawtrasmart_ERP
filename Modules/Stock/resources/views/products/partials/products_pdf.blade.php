{{--
    تقرير المنتجات — PDF.

    لا يستعمل متغيّرات CSS ولا خطوطاً خارجية: مولّد الـPDF يقرأ أنماطاً ثابتة
    فحسب، فالقيَم هنا مكتوبة صريحةً لا مشتقّةً من الثيم.

    صُحّحت هنا مقارنتان كانتا تسقطان دائماً: `type == 'product'` و`'service'`
    بينما القيم في القاعدة `products` و`services`، و`status` كائن تعداد لا نصّ.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المنتجات</title>
    <style>
        body {
            font-family: 'aealarabiya', sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #2f3349;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #2f3349;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header h1 { margin: 0; font-size: 17px; font-weight: bold; }
        .header .sub { font-size: 10px; color: #6e6b7b; margin-top: 4px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d8d6de; padding: 6px 5px; }
        th {
            background-color: #f3f2f7;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }
        td { text-align: center; }
        td.name { text-align: right; font-weight: bold; }

        /* تظليل الصفوف الزوجية: يمنع القفز بين الأعمدة في جدولٍ بعشرة أعمدة */
        tr.alt td { background-color: #fafafc; }

        .muted { color: #a5a3ae; }
        .empty { text-align: center; padding: 18px; color: #6e6b7b; }

        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #d8d6de;
            text-align: center;
            font-size: 10px;
            color: #6e6b7b;
        }
    </style>
</head>
<body>
    @php
        $typeLabels = ['products' => 'منتج', 'services' => 'خدمة', 'compiled' => 'تجميعي'];
    @endphp

    <div class="header">
        @include('stock::components.print-org-head')
        <h1>تقرير المنتجات</h1>
        <div class="sub">تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:20%">الاسم</th>
                <th style="width:12%">التصنيف</th>
                <th style="width:12%">الباركود</th>
                <th style="width:10%">الكود</th>
                <th style="width:11%">سعر الشراء</th>
                <th style="width:11%">سعر البيع</th>
                <th style="width:7%">الكمية</th>
                <th style="width:6%">الحالة</th>
                <th style="width:6%">النوع</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td class="name">{{ $product->name }}</td>
                    <td>{{ $product->category->name ?? '—' }}</td>
                    <td>{{ $product->barcode ?: '—' }}</td>
                    <td>{{ $product->product_code ?: '—' }}</td>
                    <td>{{ $product->purchase_price ? number_format($product->purchase_price, 2) : '—' }}</td>
                    <td>{{ $product->sale_price ? number_format($product->sale_price, 2) : '—' }}</td>
                    <td>{{ number_format($product->quantity ?? 0) }}</td>
                    <td>
                        {{ is_object($product->status) ? $product->status->label() : ($product->status ?? '—') }}
                    </td>
                    <td>{{ $typeLabels[$product->type] ?? $product->type }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty">لا توجد منتجات مطابقة</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        إجمالي المنتجات: {{ $products->count() }} — العملة: {{ App\Support\CurrencySettings::code() }}
    </div>
</body>
</html>
