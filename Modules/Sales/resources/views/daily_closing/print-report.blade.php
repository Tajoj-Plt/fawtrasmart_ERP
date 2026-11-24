{{--
    تقريرُ إقفالاتٍ محفوظة على مدى فترة — قد يضمّ موظفين عدّة وأياماً عدّة.

    يبدأ بجدولٍ مجملٍ يجمع الفترة كلّها، ثم تفاصيلُ كلِّ إقفالٍ على حدة.
    الجدول المجمل جديد: كان التقرير يفتح مباشرةً على التفاصيل، فقارئُه
    يقلّب عشرين صفحة ليعرف مجموع الفترة.
--}}
@extends('sales::daily_closing.partials.print-layout', [
    'documentTitle' => __('sales_daily_closing.print.period_title'),
    'periodLabel' => trim(($fromDate ?? '').' — '.($toDate ?? '')) ?: null,
    'filters' => [
        __('sales_daily_closing.fields.employee') => optional($filterEmployee)->name,
    ],
])

@section('report')
    @php
        use App\Support\CurrencySettings;

        $currency = CurrencySettings::symbol();
    @endphp

    @if ($closings->isEmpty())
        <div class="rpt-empty">{{ __('sales_daily_closing.print.no_data') }}</div>
    @else
        {{-- ══════════ الجدول المجمل ══════════ --}}
        <div class="rpt-block">
            <header>
                <h5>{{ __('sales_daily_closing.sections.summary') }}</h5>
                <span class="rpt-pill rpt-pill-secondary">{{ $closings->count() }}</span>
            </header>

            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th style="width: 4%">#</th>
                            <th>{{ __('sales_daily_closing.fields.date') }}</th>
                            @if ($seesAll)<th>{{ __('sales_daily_closing.fields.employee') }}</th>@endif
                            <th>{{ __('sales_daily_closing.fields.sales') }}</th>
                            <th>{{ __('sales_daily_closing.fields.returns') }}</th>
                            <th>{{ __('sales_daily_closing.fields.net_sales') }}</th>
                            <th>{{ __('sales_daily_closing.fields.cash_sales') }}</th>
                            <th>{{ __('sales_daily_closing.fields.receipts') }}</th>
                            <th>{{ __('sales_daily_closing.fields.collections') }}</th>
                            <th>{{ __('sales_daily_closing.fields.expenses') }}</th>
                            <th>{{ __('sales_daily_closing.fields.stock') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($closingsDetails as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="rpt-num">{{ $row['date'] }}</td>
                                @if ($seesAll)
                                    <td class="rpt-name">{{ optional($row['employee'])->name ?? '—' }}</td>
                                @endif
                                <td class="rpt-num rpt-pos">{{ CurrencySettings::number($row['totalSales']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ CurrencySettings::number($row['totalReturns']) }}</td>
                                <td class="rpt-num">{{ CurrencySettings::number($row['netSales']) }}</td>
                                <td class="rpt-num rpt-infoc">{{ CurrencySettings::number($row['totalCashSales']) }}</td>
                                <td class="rpt-num rpt-infoc">{{ CurrencySettings::number($row['totalReceipts']) }}</td>
                                <td class="rpt-num rpt-infoc">{{ CurrencySettings::number($row['totalCollections']) }}</td>
                                <td class="rpt-num rpt-neg">{{ CurrencySettings::number($row['totalExpensesDetail']) }}</td>
                                <td class="rpt-num">{{ number_format($row['systemStock'], 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="{{ $seesAll ? 3 : 2 }}">{{ __('sales_daily_closing.fields.total') }}</td>
                            <td class="rpt-num rpt-pos">{{ CurrencySettings::number(collect($closingsDetails)->sum('totalSales')) }}</td>
                            <td class="rpt-num rpt-warnc">{{ CurrencySettings::number(collect($closingsDetails)->sum('totalReturns')) }}</td>
                            <td class="rpt-num">{{ CurrencySettings::number(collect($closingsDetails)->sum('netSales')) }}</td>
                            <td class="rpt-num rpt-infoc">{{ CurrencySettings::number(collect($closingsDetails)->sum('totalCashSales')) }}</td>
                            <td class="rpt-num rpt-infoc">{{ CurrencySettings::number(collect($closingsDetails)->sum('totalReceipts')) }}</td>
                            <td class="rpt-num rpt-infoc">{{ CurrencySettings::number(collect($closingsDetails)->sum('totalCollections')) }}</td>
                            <td class="rpt-num rpt-neg">{{ CurrencySettings::number(collect($closingsDetails)->sum('totalExpensesDetail')) }}</td>
                            <td class="rpt-num">{{ number_format(collect($closingsDetails)->sum('systemStock'), 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- ══════════ التفاصيل ══════════ --}}
        @foreach ($closingsDetails as $row)
            <div class="pr-employee">
                <div class="pr-employee__head">
                    <h3>{{ optional($row['employee'])->name ?? '—' }}</h3>
                    <span>{{ $row['date'] }}</span>
                </div>

                @include('sales::daily_closing.partials.breakdown', [
                    'data' => $row,
                    'currency' => $currency,
                    'stockDetails' => $row['stockDetails'],
                ])
            </div>
        @endforeach
    @endif
@endsection
