{{--
    تفاصيل إقفالٍ محفوظ — موظفٌ واحد ويومٌ واحد.

    كان أربعمئة وخمسين سطراً يبني ستّة جداول بيده؛ صار يمرّر بيانات
    `DailyClosingLedger::breakdown()` إلى قالب `partials.breakdown`
    المشترك، فما يُطبع من هنا هو ما يُطبع من التقرير الجماعي حرفياً.
--}}
@extends('sales::daily_closing.partials.print-layout', [
    'documentTitle' => __('sales_daily_closing.print.details_title'),
    'periodLabel' => $date,
    'filters' => [
        __('sales_daily_closing.fields.employee') => optional($employee)->name,
    ],
])

@section('report')
    @php
        use App\Support\CurrencySettings;

        $currency = CurrencySettings::symbol();
    @endphp

    <div class="pr-employee">
        <div class="pr-employee__head">
            <h3>{{ optional($employee)->name ?? '—' }}</h3>
            <span>
                {{ $date }}
                @if ($closing->notes)
                    · {{ $closing->notes }}
                @endif
            </span>
        </div>

        @include('sales::daily_closing.partials.breakdown', [
            'data' => $data,
            'currency' => $currency,
            'stockDetails' => $stockDetails,
        ])
    </div>
@endsection
