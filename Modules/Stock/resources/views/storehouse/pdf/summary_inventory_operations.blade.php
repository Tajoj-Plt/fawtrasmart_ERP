{{--
    ملخّص عمليات المخزون — PDF.

    أنماط ثابتة بلا `var()`: مولّد الـPDF لا يقرأها. ومجموعتا «الوارد»
    و«المنصرف» تُلوَّنان تلويناً خفيفاً وتُفصلان بحدٍّ أثقل، وإلا صار
    اثنا عشر عموداً من الأرقام كتلةً واحدة.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ملخّص عمليات المخزون — {{ $storehouse->name }}</title>
    <style>
        body {
            font-family: 'aealarabiya', Arial, sans-serif;
            font-size: 10px;
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

        .meta { margin-bottom: 10px; font-size: 9px; color: #6e6b7b; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d8d6de; padding: 4px 3px; text-align: center; }
        th { background-color: #f3f2f7; font-weight: bold; font-size: 9px; }
        td.name { text-align: right; font-weight: bold; }

        .group-in  { background-color: #e6f6ec; }
        .group-out { background-color: #fdeaea; }

        /* حدٌّ أثقل يفصل المجموعات بعضها عن بعض */
        .edge { border-inline-end: 2px solid #9a97a5 !important; }

        tr.alt td { background-color: #fafafc; }
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
        <h1>ملخّص عمليات المخزون</h1>
        <h2>{{ $storehouse->name }}</h2>
    </div>

    <div class="meta">تاريخ التقرير: {{ now()->format('Y-m-d H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="edge">الصنف</th>
                <th colspan="5" class="group-in edge">الوارد</th>
                <th colspan="5" class="group-out edge">المنصرف</th>
                <th rowspan="2">صافي الحركة</th>
            </tr>
            <tr>
                <th>فواتير شراء</th>
                <th>مرتجع مبيعات</th>
                <th>تحويل</th>
                <th>يدوي</th>
                <th class="edge">الإجمالي</th>

                <th>فواتير بيع</th>
                <th>مرتجع مشتريات</th>
                <th>تحويل</th>
                <th>يدوي</th>
                <th class="edge">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $net = $product['movement_total'] - $product['sold_quantity'];
                    $signed = fn ($value) => $value ? '-'.$value : 0;
                @endphp

                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td class="name edge">{{ $product['name'] }}</td>

                    <td>0</td>
                    <td>{{ $product['sales_return_quantity'] }}</td>
                    <td>{{ $product['incoming_transfer'] }}</td>
                    <td>{{ $product['incoming_manual'] }}</td>
                    <td class="edge"><strong>{{ $product['incoming_total'] }}</strong></td>

                    <td>{{ $product['sold_quantity'] }}</td>
                    <td>0</td>
                    <td>{{ $signed($product['outgoing_transfer']) }}</td>
                    <td>{{ $signed($product['outgoing_manual']) }}</td>
                    <td class="edge"><strong>{{ $signed($product['outgoing_total']) }}</strong></td>

                    <td><strong>{{ $net }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="empty">لا حركات في هذا المستودع</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        عدد الأصناف: {{ count($products) }}
    </div>
</body>
</html>
