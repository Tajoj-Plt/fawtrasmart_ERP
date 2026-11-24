{{--
    تقرير الأذون المخزنية — PDF.

    صُحّحت هنا الحالة: كانت `@switch` على 0/1/2 بينما العمود يحفظ نصّاً
    (`pending` / `approved` / `rejected`، وبصيغتين مكتوبتين خطأً قديماً)،
    فكانت الخانة تخرج فارغة في كل صفّ.

    أنماط ثابتة بلا `var()` — مولّد الـPDF لا يقرأ متغيّرات CSS.
--}}
@php
    $types = [1 => 'إذن استلام', 2 => 'إذن صرف', 3 => 'إذن تحويل'];

    $states = [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليه',
        'apporod' => 'موافق عليه',
        'rejected' => 'مرفوض',
        'rejec' => 'مرفوض',
        'processing' => 'قيد المعالجة',
    ];

    $currency = App\Support\CurrencySettings::code();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الأذون المخزنية</title>
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
        .header p { margin: 3px 0 0; font-size: 10px; color: #6e6b7b; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d8d6de; padding: 5px; text-align: center; }
        th { background-color: #f3f2f7; font-weight: bold; font-size: 10px; }
        td.name { text-align: right; }

        tr.alt td { background-color: #fafafc; }
        tfoot td { background-color: #f3f2f7; font-weight: bold; }

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
        <h1>تقرير الأذون المخزنية</h1>
        <p>تاريخ التقرير: {{ now()->format('Y-m-d H:i') }} — العملة: {{ $currency }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:12%">رقم الإذن</th>
                <th style="width:12%">النوع</th>
                <th style="width:13%">التاريخ</th>
                <th style="width:20%">المستودع</th>
                <th style="width:15%">الحساب الفرعي</th>
                <th style="width:13%">الإجمالي</th>
                <th style="width:10%">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($permits as $permit)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $permit->number ?: $permit->id }}</td>
                    <td>{{ $types[$permit->permission_type] ?? '—' }}</td>
                    <td>
                        {{ $permit->permission_date
                            ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d')
                            : '—' }}
                    </td>
                    <td class="name">{{ optional($permit->storeHouse)->name ?? '—' }}</td>
                    <td class="name">{{ $permit->sub_account ?: '—' }}</td>
                    <td>{{ number_format($permit->grand_total, 2) }}</td>
                    <td>{{ $states[$permit->status] ?? $permit->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">لا أذون مطابقة</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">الإجمالي</td>
                <td colspan="2">{{ number_format($permits->sum('grand_total'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        عدد الأذون: {{ count($permits) }}
    </div>
</body>
</html>
