{{--
    قائمة الدخل — نسخة PDF.

    **ما كان**

    `GeneralAccountsController::exportIncomeStatementPdf` يستدعي هذا القالب
    منذ كُتب، والقالب **غير موجود أصلاً**. فكل نقرةٍ على «تصدير PDF» ترمي
    `View not found`، يلتقطها `catch` العامّ فيعود المستخدم برسالة
    «فشل في تصدير الملف» بلا سببٍ مفهوم — والزرّ معطَّل فعلياً منذ اليوم
    الأول.

    **ما صار**

    ورقة A4 بلغة `asol/pdf` نفسها: ترويسة المنشأة من الإعدادات، ثم
    الإيرادات فتكلفة المبيعات فالمصروفات، ثم صافي الدخل.

    يستقبل من الكنترولر: revenues, cost_of_sales, expenses, totals, date.
--}}
@php
    $orgName = \App\Support\OrganizationInfo::name();
    $orgAddress = \App\Support\OrganizationInfo::address();
    $orgIdentity = \App\Support\OrganizationInfo::identityLine();

    $revenues = collect($revenues ?? []);
    $costOfSales = collect($cost_of_sales ?? []);
    $expenses = collect($expenses ?? []);
    $totals = $totals ?? [];

    $netIncome = $totals['net_income'] ?? 0;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>قائمة الدخل</title>
    <style>
        @page { margin: 12mm; }

        body {
            direction: rtl;
            text-align: right;
            font-family: 'aealarabiya', 'Cairo', sans-serif;
            color: #000;
            margin: 0;
        }

        .doc-head {
            text-align: center;
            border: 2px solid #000;
            padding: 10px;
            margin-bottom: 20px;
        }

        .doc-head h1 { margin: 0 0 4px; font-size: 18px; }
        .doc-head h2 { margin: 0 0 4px; font-size: 15px; }
        .doc-head p  { margin: 2px 0; font-size: 11px; color: #333; }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin: 18px 0 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        table.data th,
        table.data td {
            border: 1px solid #000;
            padding: 6px;
        }

        table.data th { background: #f0f0f0; text-align: center; }
        table.data td.num { text-align: center; white-space: nowrap; }
        table.data tfoot td { font-weight: bold; background: #fafafa; }

        .net {
            margin-top: 20px;
            border: 2px solid #000;
            padding: 10px 12px;
            font-size: 15px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
        }

        .empty { font-size: 12px; color: #555; padding: 6px 0; }
    </style>
</head>

<body>
    <div class="doc-head">
        <h1>{{ $orgName }}</h1>
        <h2>قائمة الدخل</h2>
        @if ($orgAddress)<p>{{ $orgAddress }}</p>@endif
        @if ($orgIdentity)<p>{{ $orgIdentity }}</p>@endif
        <p>صدرت في {{ $date ?? now()->format('Y-m-d H:i') }}</p>
    </div>

    @foreach ([
        ['الإيرادات', $revenues, 'total_revenues'],
        ['تكلفة المبيعات', $costOfSales, 'total_cost_of_sales'],
        ['المصروفات', $expenses, 'total_expenses'],
    ] as [$sectionTitle, $rows, $totalKey])
        <h3 class="section-title">{{ $sectionTitle }}</h3>

        @if ($rows->count())
            <table class="data">
                <thead>
                    <tr>
                        <th style="width:110px">الكود</th>
                        <th>الحساب</th>
                        <th style="width:140px">الرصيد</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="num">{{ $row['code'] ?? '—' }}</td>
                            <td>{{ $row['name'] ?? '—' }}</td>
                            <td class="num">@money($row['balance'] ?? 0)</td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="2">إجمالي {{ $sectionTitle }}</td>
                        <td class="num">@money($totals[$totalKey] ?? 0)</td>
                    </tr>
                </tfoot>
            </table>
        @else
            <p class="empty">لا حركة على حسابات {{ $sectionTitle }} في هذه الفترة.</p>
        @endif
    @endforeach

    <h3 class="section-title">النتيجة</h3>
    <table class="data">
        <tbody>
            <tr>
                <td>مجمل الربح (الإيرادات ناقص تكلفة المبيعات)</td>
                <td class="num" style="width:140px">@money($totals['gross_profit'] ?? 0)</td>
            </tr>
            <tr>
                <td>هامش مجمل الربح</td>
                <td class="num">{{ number_format($totals['gross_profit_margin'] ?? 0, 2) }}%</td>
            </tr>
            <tr>
                <td>هامش صافي الربح</td>
                <td class="num">{{ number_format($totals['profit_margin'] ?? 0, 2) }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="net">
        <span>{{ $netIncome >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}</span>
        <span>@money(abs($netIncome))</span>
    </div>
</body>

</html>
