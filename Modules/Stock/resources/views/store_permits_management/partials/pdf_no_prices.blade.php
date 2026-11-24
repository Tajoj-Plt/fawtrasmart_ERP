{{--
    إذن مخزني — PDF بلا أسعار (نسخة المستودع والمستلم).

    نفس بنية النسخة المسعَّرة، بلا أعمدة السعر والقيمة. وحُذفت منها أيضاً
    صفوف المنتجات المُختلَقة التي كانت تُطبع حين لا بنود للإذن.
--}}
@php
    $items = $permit->items ?? collect();

    $types = [1 => 'إذن إضافة', 2 => 'إذن صرف', 3 => 'تحويل يدوي'];
    $itemTypes = [1 => 'إضافة', 2 => 'صرف', 3 => 'تحويل'];

    $states = [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليه',
        'apporod' => 'موافق عليه',
        'rejected' => 'مرفوض',
        'rejec' => 'مرفوض',
        'processing' => 'قيد المعالجة',
    ];

    $type = $permit->permission_type ?? 1;
    $notes = $permit->notes ?? $permit->description ?? null;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إذن مخزني {{ $permit->number ?? $permit->code }}</title>
    <style>
        body {
            font-family: 'aealarabiya', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #2f3349;
        }

        .header {
            text-align: center;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2f3349;
        }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 3px 0 0; font-size: 10px; color: #6e6b7b; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d8d6de; padding: 5px 6px; }

        .info-table td { width: 25%; }
        .info-table td.label { background-color: #f3f2f7; font-weight: bold; width: 15%; }

        .products-table th {
            background-color: #f3f2f7;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
        }
        .products-table td { text-align: center; }
        .products-table td.name { text-align: right; }
        .products-table tr.alt td { background-color: #fafafc; }
        .products-table tfoot td { background-color: #f3f2f7; font-weight: bold; }

        .empty { padding: 16px; text-align: center; color: #6e6b7b; }

        .notes {
            border: 1px solid #d8d6de;
            padding: 8px;
            margin-bottom: 14px;
            background-color: #fafafc;
        }

        .signatures { width: 100%; margin-top: 26px; }
        .signatures td {
            border: 0;
            text-align: center;
            width: 33.33%;
            font-size: 10px;
        }
        .signature-line {
            margin: 34px auto 0;
            border-top: 1px solid #2f3349;
            width: 150px;
        }
    </style>
</head>
<body>
    <div class="header">
        @include('stock::components.print-org-head')
        <h1>إذن مخزني — {{ $types[$type] ?? 'غير محدد' }}</h1>
        <p>
            رقم الإذن: {{ $permit->number ?? $permit->code ?? '—' }}
            — الحالة: {{ $states[$permit->status] ?? $permit->status ?? '—' }}
        </p>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">{{ $type == 2 ? 'العميل' : 'المورّد' }}</td>
            <td>
                {{ $type == 2
                    ? (optional($permit->client)->trade_name ?? '—')
                    : (optional($permit->supplier)->trade_name ?? '—') }}
            </td>
            <td class="label">تاريخ الإذن</td>
            <td>
                {{ $permit->permission_date
                    ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d')
                    : optional($permit->created_at)->format('Y-m-d') }}
            </td>
        </tr>
        <tr>
            <td class="label">الفرع</td>
            <td>{{ optional($permit->branch)->name ?? '—' }}</td>
            <td class="label">المستودع</td>
            <td>{{ optional($permit->storeHouse)->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">مصدر الإذن</td>
            <td>{{ optional($permit->permissionSource)->name ?? '—' }}</td>
            <td class="label">الرقم المرجعي</td>
            <td>{{ $permit->reference_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">أُنشئ بواسطة</td>
            <td>{{ optional($permit->createdBy)->name ?? optional($permit->user)->name ?? '—' }}</td>
            <td class="label">عدد الأصناف</td>
            <td>{{ $items->count() }}</td>
        </tr>
    </table>

    <table class="products-table">
        <thead>
            <tr>
                <th style="width:6%">#</th>
                <th style="width:44%">المنتج</th>
                <th style="width:18%">الوحدة</th>
                <th style="width:18%">الكمية</th>
                <th style="width:14%">العملية</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $loop->iteration }}</td>
                    <td class="name">{{ optional($item->product)->name ?? '—' }}</td>
                    <td>{{ optional($item->unit)->name ?? '—' }}</td>
                    <td>{{ number_format($item->quantity ?? 0, 2) }}</td>
                    <td>{{ $itemTypes[$type] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">لا بنود في هذا الإذن</td>
                </tr>
            @endforelse
        </tbody>

        @if ($items->count())
            <tfoot>
                <tr>
                    <td colspan="3">إجمالي الكمية</td>
                    <td colspan="2">{{ number_format($items->sum('quantity'), 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($notes)
        <div class="notes"><strong>ملاحظات:</strong> {{ $notes }}</div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                توقيع مدير المستودع
                <div class="signature-line"></div>
            </td>
            <td>
                توقيع المستلم
                <div class="signature-line"></div>
            </td>
            <td>
                ختم المنشأة
                <div class="signature-line"></div>
            </td>
        </tr>
    </table>
</body>
</html>
