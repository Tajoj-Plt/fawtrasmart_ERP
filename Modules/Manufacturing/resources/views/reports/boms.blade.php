@extends('manufacturing::reports.layout', [
    'reportTitle' => 'تقرير قوائم المواد',
    'reportIcon' => 'layers',
    'reportSub' => 'قوائم مواد الإنتاج وعدد بنودها وتكلفة خامها',
])

@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp

@section('metrics')
    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon text-success"><i class="feather icon-layers"></i></span>
            <span class="stk-metric__value stk-num text-success">{{ number_format($rows->total()) }}</span>
            <span class="stk-metric__label">قوائم مواد</span>
        </div>
        {{--
            كان هنا «تكلفة الخام»: مجموعُ خامِ وصفاتِ الصفحة المعروضة. وهو
            مجموعُ تعريفاتٍ لا حركات — وصفةُ ألف زجاجةٍ إلى وصفة عشرة
            لترات — وبعملاتٍ قد تختلف، وعلى صفحةٍ واحدة من النتيجة. فحُذف،
            وبقي ما يُعَدّ فعلاً.
        --}}
        <div class="stk-metric">
            <span class="stk-metric__icon text-info"><i class="feather icon-check-circle"></i></span>
            <span class="stk-metric__value stk-num text-info">{{ number_format($activeCount) }}</span>
            <span class="stk-metric__label">نشطة</span>
        </div>
    </div>
@endsection

@section('table')
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th style="width:12%">الكود</th>
                <th>القائمة</th>
                <th style="width:18%">الصنف المنتَج</th>
                <th class="text-end" style="width:10%">الكمّية</th>
                <th class="text-center" style="width:10%">بنودها</th>
                <th class="text-end" style="width:13%">تكلفة الخام</th>
                <th class="text-end" style="width:13%">تكلفة الوصفة</th>
                <th class="text-center" style="width:10%">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="stk-num">{{ $row->code ?: '—' }}</td>
                    <td>{{ $row->name ?: '—' }}</td>
                    <td>{{ $row->product_name ?: '—' }}</td>
                    <td class="text-end stk-num">{{ $num($row->quantity) }}</td>
                    <td class="text-center stk-num">
                        {{ $row->items_count > 0 ? number_format($row->items_count) : '—' }}
                    </td>
                    <td class="text-end stk-num">{{ $money($row->raw_cost) }}</td>
                    <td class="text-end stk-num">{{ $money($row->last_total_cost) }} {{ $row->currency_code }}</td>
                    <td class="text-center">
                        <span class="badge bg-{{ $row->status ? 'success' : 'secondary' }}">
                            {{ $row->status ? 'نشطة' : 'موقوفة' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        لا قوائم موادّ معرَّفة بعد.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
