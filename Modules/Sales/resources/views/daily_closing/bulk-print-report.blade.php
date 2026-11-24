{{--
    تقريرُ يومٍ واحد لكلّ الموظفين — عرضاً وطباعةً بلا تخزين.

    يبدأ بجدولٍ مجملٍ يقارن الموظفين صفّاً صفّاً، ثم تفاصيلُ كلِّ موظفٍ
    في صفحةٍ مستقلّة. من لا حركة له لا يُطبع له شيء — يُستبعد في المتحكّم.
--}}
@extends('sales::daily_closing.partials.print-layout', [
    'documentTitle' => __('sales_daily_closing.print.bulk_title'),
    'periodLabel' => $date,
    'filters' => [],
])

@section('report')
    @php
        use App\Support\CurrencySettings;

        $currency = CurrencySettings::symbol();
        $rows = collect($reportData);
    @endphp

    @if ($rows->isEmpty())
        <div class="rpt-empty">{{ __('sales_daily_closing.print.no_data') }}</div>
    @else
        {{-- ══════════ مقارنة الموظفين ══════════ --}}
        <div class="rpt-block">
            <header>
                <h5>{{ __('sales_daily_closing.sections.summary') }}</h5>
                <span class="rpt-pill rpt-pill-secondary">{{ $rows->count() }}</span>
            </header>

            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th style="width: 4%">#</th>
                            <th>{{ __('sales_daily_closing.fields.employee') }}</th>
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
                        @foreach ($rows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="rpt-name">{{ optional($row['employee'])->name ?? '—' }}</td>
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
                            <td colspan="2">{{ __('sales_daily_closing.fields.total') }}</td>
                            <td class="rpt-num rpt-pos">{{ CurrencySettings::number($rows->sum('totalSales')) }}</td>
                            <td class="rpt-num rpt-warnc">{{ CurrencySettings::number($rows->sum('totalReturns')) }}</td>
                            <td class="rpt-num">{{ CurrencySettings::number($rows->sum('netSales')) }}</td>
                            <td class="rpt-num rpt-infoc">{{ CurrencySettings::number($rows->sum('totalCashSales')) }}</td>
                            <td class="rpt-num rpt-infoc">{{ CurrencySettings::number($rows->sum('totalReceipts')) }}</td>
                            <td class="rpt-num rpt-infoc">{{ CurrencySettings::number($rows->sum('totalCollections')) }}</td>
                            <td class="rpt-num rpt-neg">{{ CurrencySettings::number($rows->sum('totalExpensesDetail')) }}</td>
                            <td class="rpt-num">{{ number_format($rows->sum('systemStock'), 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- ══════════ تفاصيل كلّ موظف ══════════ --}}
        @foreach ($rows as $row)
            <div class="pr-employee">
                <div class="pr-employee__head">
                    <h3>{{ optional($row['employee'])->name ?? '—' }}</h3>
                    <span>{{ $date }}</span>
                </div>

                @include('sales::daily_closing.partials.breakdown', [
                    'data' => $row,
                    'currency' => $currency,
                    'stockDetails' => null,
                ])
            </div>
        @endforeach
    @endif
@endsection
