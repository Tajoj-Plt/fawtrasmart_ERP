@extends('manufacturing::reports.layout', [
    'reportTitle' => 'تقرير الفاقد ونواتج نهاية العمر',
    'reportIcon' => 'trash-2',
    'reportSub' => 'المخرجات الجانبية بصنفها ونوعها وقيمتها — ما دخل المخزون من أوامر مكتملة، وما يُتوقَّع من المفتوحة',
])

@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');

    // `end_life_waste_type` نصٌّ حرّ في القاعدة، فيُعرَض كما هو ولا يُترجَم
    // بخريطةٍ مخترعة.
    $typeLabel = fn ($t) => filled($t) ? $t : 'غير محدَّد';

    $hasOpen = $totals->sum('open_qty') > 0;
@endphp

@section('metrics')
    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon text-warning"><i class="feather icon-trash-2"></i></span>
            <span class="stk-metric__value stk-num text-warning">{{ number_format($rows->total()) }}</span>
            <span class="stk-metric__label">أصناف مخرجة</span>
        </div>

        {{-- الكمية لا تُجمع عبر الأصناف — تظهر لصنفٍ واحد وحده --}}
        @if ($singleProduct)
            <div class="stk-metric">
                <span class="stk-metric__icon text-info"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num text-info">{{ $num($totals->sum('qty')) }}</span>
                <span class="stk-metric__label">الكمّية (من المكتملة)</span>
            </div>
        @endif

        @foreach ($totals as $t)
            <div class="stk-metric">
                <span class="stk-metric__icon text-danger"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ $money($t->value) }}</span>
                <span class="stk-metric__label">القيمة (من المكتملة) — {{ $t->currency_code }}</span>
            </div>
            @if ((float) $t->open_value > 0)
                <div class="stk-metric">
                    <span class="stk-metric__icon text-secondary"><i class="feather icon-edit-3"></i></span>
                    <span class="stk-metric__value stk-num text-secondary">{{ $money($t->open_value) }}</span>
                    <span class="stk-metric__label">متوقَّعة من المفتوحة — {{ $t->currency_code }}</span>
                </div>
            @endif
        @endforeach
    </div>
@endsection

@section('table')
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>الصنف</th>
                <th style="width:14%">النوع</th>
                <th class="text-center" style="width:9%">أوامر</th>
                <th class="text-end" style="width:14%">الكمّية (مكتملة)</th>
                <th class="text-end" style="width:16%">القيمة (مكتملة)</th>
                @if ($hasOpen)
                    <th class="text-end" style="width:14%">متوقَّعة (مفتوحة)</th>
                    <th class="text-end" style="width:14%">قيمتها التقديرية</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->product_name ?: '—' }}</td>
                    <td>
                        <span class="badge bg-secondary">{{ $typeLabel($row->end_life_waste_type) }}</span>
                    </td>
                    <td class="text-center stk-num">{{ number_format($row->orders_count) }}</td>
                    <td class="text-end stk-num">{{ $num($row->qty) }}</td>
                    <td class="text-end stk-num fw-bold">{{ $money($row->value) }} {{ $row->currency_code }}</td>
                    @if ($hasOpen)
                        <td class="text-end stk-num text-muted">{{ (float) $row->open_qty > 0 ? $num($row->open_qty) : '—' }}</td>
                        <td class="text-end stk-num text-muted">{{ (float) $row->open_qty > 0 ? $money($row->open_value) : '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hasOpen ? 7 : 5 }}" class="text-center text-muted py-4">
                        لا نواتج نهاية عمرٍ مسجَّلة على أوامر هذا المدى.
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- القيمةُ بعملتها؛ والكميةُ لصنفٍ واحد وحده --}}
        @if ($rows->total() > 0)
            <tfoot>
                @foreach ($totals as $t)
                    <tr>
                        <td colspan="3" class="text-end fw-bold">الإجمالي</td>
                        <td class="text-end stk-num fw-bold">{{ $singleProduct ? $num($t->qty) : '' }}</td>
                        <td class="text-end stk-num fw-bold">{{ $money($t->value) }} {{ $t->currency_code }}</td>
                        @if ($hasOpen)
                            <td class="text-end stk-num text-muted">{{ $singleProduct ? $num($t->open_qty) : '' }}</td>
                            <td class="text-end stk-num text-muted">{{ $money($t->open_value) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tfoot>
        @endif
    </table>
@endsection
