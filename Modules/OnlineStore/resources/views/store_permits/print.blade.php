<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>إذن مخزني #{{ $permit->number }}</title>
    <style>
        body {
            font-family: 'Tajawal', 'Cairo', sans-serif;
            color: #333;
            padding: 20px;
        }

        .doc {
            width: 210mm;
            max-width: 100%;
            margin: 0 auto;
        }

        .doc-header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .doc-title {
            font-size: 24px;
            font-weight: 700;
            color: #007bff;
            margin: 0 0 6px;
        }

        .doc-number {
            font-size: 14px;
            color: #666;
        }

        .info-grid {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .info-box {
            flex: 1;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 12px 16px;
        }

        .info-box dt {
            font-size: 12px;
            color: #888;
        }

        .info-box dd {
            margin: 0 0 8px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #dee2e6;
            padding: 8px 10px;
            text-align: center;
            font-size: 13px;
        }

        th {
            background: #f1f3f5;
        }

        .totals {
            width: 260px;
            margin-inline-start: auto;
        }

        .totals td {
            border: none;
            padding: 4px 10px;
        }

        .totals .grand {
            font-weight: 700;
            font-size: 16px;
            border-top: 2px solid #007bff;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }

        .signature-line {
            width: 200px;
            border-top: 1px solid #999;
            padding-top: 6px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="doc">
        <div class="doc-header">
            @php
                $printType = (int) $permit->permission_type;
                $printCurrency = $permit->currency ?: \App\Support\CurrencySettings::code();
            @endphp
            <h1 class="doc-title">{{ [1 => 'إذن إضافة مخزني', 2 => 'إذن صرف مخزني', 3 => 'إذن تحويل مخزني', 4 => 'إذن رصيد افتتاحي'][$printType] ?? 'إذن مخزني' }}</h1>
            <div class="doc-number">رقم الإذن: {{ $permit->number }}</div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <dl>
                    {{-- التحويل يُظهر طرفَيه، والفرعُ فرعُ المخزن — والتاريخُ نصٌّ في القاعدة لا كائن تاريخ --}}
                    @if ($printType === 3)
                        <dt>من مستودع</dt>
                        <dd>{{ $permit->fromStoreHouse->name ?? '-' }}{{ $permit->fromStoreHouse?->branch ? ' — '.$permit->fromStoreHouse->branch->name : '' }}</dd>
                        <dt>إلى مستودع</dt>
                        <dd>{{ $permit->toStoreHouse->name ?? '-' }}{{ $permit->toStoreHouse?->branch ? ' — '.$permit->toStoreHouse->branch->name : '' }}</dd>
                    @else
                        <dt>المستودع</dt>
                        <dd>{{ $permit->storeHouse->name ?? '-' }}{{ $permit->storeHouse?->branch ? ' — '.$permit->storeHouse->branch->name : '' }}</dd>
                    @endif
                    <dt>تاريخ الإذن</dt>
                    <dd>{{ $permit->permission_date ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d') : '-' }}</dd>
                </dl>
            </div>
            <div class="info-box">
                <dl>
                    <dt>الحالة</dt>
                    <dd>{{ $permit->status === 'apporod' ? 'معتمد' : ($permit->status === 'pending' ? 'معلَّق' : 'مرفوض') }}</dd>
                    <dt>ملاحظات</dt>
                    <dd>{{ $permit->details ?: '-' }}</dd>
                </dl>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة ({{ $printCurrency }})</th>
                    <th>الإجمالي ({{ $printCurrency }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($permit->items as $item)
                    <tr>
                        <td>{{ $item->product->name ?? '-' }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                        <td>{{ number_format((float) ($item->unit_price ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($item->total ?? ($item->quantity * ($item->unit_price ?? 0))), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">لا توجد بنود</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr class="grand">
                <td>الإجمالي</td>
                <td>{{ number_format((float) ($permit->grand_total ?: $permit->items->sum('total')), 2) }} {{ $printCurrency }}</td>
            </tr>
        </table>

        <div class="signatures">
            <div class="signature-line">توقيع المستلم</div>
            <div class="signature-line">توقيع المسؤول</div>
        </div>
    </div>
</body>

</html>
