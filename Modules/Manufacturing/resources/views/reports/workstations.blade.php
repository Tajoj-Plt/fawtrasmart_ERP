@extends('manufacturing::reports.layout', [
    'reportTitle' => 'تقرير حِمل محطّات العمل',
    'reportIcon' => 'cpu',
    'reportSub' => 'كم أمراً مرّ بكل محطّة في المدى، وكم ساعةً شغّلت، وبكم — بلا الأوامر الملغاة',
    'showStatus' => false,
    'showProduct' => false,
])

@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num = fn ($v) => number_format((float) $v, 2);

    /*
        المحطّات كلّها تُعرَض ولو لم يمرّ بها أمرٌ في المدى — لأنّ «محطّةٌ
        بلا حِمل» جوابٌ على السؤال لا غيابٌ عنه. فالحِملُ يُحسب في استعلامٍ
        فرعي (ضمن المدى، بلا الملغي) ثمّ تُوصَل به المحطّاتُ `leftJoin`.

        والمجاميعُ من `$totals` — على النتيجة كلِّها لا على الصفحة المعروضة.
    */

    // الساعات تُجمع ما دامت وحدةُ المحطّات المحمَّلة واحدة («ساعة»)
    $sameUnit = $totals->count() > 0 && $totals->max('units') <= 1;
@endphp

@section('metrics')
    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon text-primary"><i class="feather icon-cpu"></i></span>
            <span class="stk-metric__value stk-num text-primary">{{ number_format($rows->total()) }}</span>
            <span class="stk-metric__label">محطّات</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon text-{{ $loadedStations > 0 ? 'success' : 'muted' }}">
                <i class="feather icon-activity"></i>
            </span>
            <span class="stk-metric__value stk-num text-{{ $loadedStations > 0 ? 'success' : 'muted' }}">
                {{ number_format($loadedStations) }}
            </span>
            <span class="stk-metric__label">محمَّلة في المدى</span>
        </div>

        @foreach ($totals as $t)
            @if ($sameUnit)
                <div class="stk-metric">
                    <span class="stk-metric__icon text-info"><i class="feather icon-clock"></i></span>
                    <span class="stk-metric__value stk-num text-info">{{ $num($t->operating_time) }}</span>
                    <span class="stk-metric__label">ساعات التشغيل</span>
                </div>
            @endif
            <div class="stk-metric">
                <span class="stk-metric__icon text-danger"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ $money($t->load_cost) }}</span>
                <span class="stk-metric__label">تكلفة الحِمل — {{ $t->currency_code }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon text-success"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__value stk-num text-success">{{ $money($t->done_cost) }}</span>
                <span class="stk-metric__label">منها على أوامر مكتملة</span>
            </div>
        @endforeach
    </div>
@endsection

@section('table')
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th style="width:11%">الكود</th>
                <th>المحطّة</th>
                <th class="text-center" style="width:8%">الوحدة</th>
                <th class="text-end" style="width:10%">سعر الوحدة</th>
                <th class="text-end" style="width:9%">منه أجور</th>
                <th class="text-center" style="width:8%">أوامر</th>
                <th class="text-end" style="width:10%">ساعات تشغيل</th>
                <th class="text-end" style="width:14%">تكلفة الحِمل</th>
                <th class="text-end" style="width:13%">منها مكتملة</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr @class(['text-muted' => $row->orders_count == 0])>
                    <td class="stk-num">{{ $row->code ?: '—' }}</td>
                    <td>{{ $row->name ?: '—' }}</td>
                    <td class="text-center">{{ $row->unit ?: '—' }}</td>
                    <td class="text-end stk-num">{{ $money($row->total_cost) }}</td>
                    <td class="text-end stk-num">{{ $money($row->cost_wages) }}</td>
                    <td class="text-center stk-num">
                        {{ $row->orders_count > 0 ? number_format($row->orders_count) : '—' }}
                    </td>
                    <td class="text-end stk-num">{{ $row->orders_count > 0 ? $num($row->operating_time) : '—' }}</td>
                    <td class="text-end stk-num fw-bold">
                        {{ $row->orders_count > 0 ? $money($row->load_cost) . ' ' . $row->currency_code : '—' }}
                    </td>
                    <td class="text-end stk-num">{{ $row->orders_count > 0 ? $money($row->done_cost) : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        لا محطّات عملٍ معرَّفة بعد.
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- لا إجماليَّ بلا حِمل؛ ولكل عملةٍ صفُّها --}}
        @if ($totals->isNotEmpty())
            <tfoot>
                @foreach ($totals as $t)
                    <tr>
                        <td colspan="6" class="text-end fw-bold">الإجمالي</td>
                        <td class="text-end stk-num fw-bold">{{ $sameUnit ? $num($t->operating_time) : '' }}</td>
                        <td class="text-end stk-num fw-bold">{{ $money($t->load_cost) }} {{ $t->currency_code }}</td>
                        <td class="text-end stk-num fw-bold">{{ $money($t->done_cost) }}</td>
                    </tr>
                @endforeach
            </tfoot>
        @endif
    </table>
@endsection
