{{--
    «مبيعات البنود» — التقريرُ التفصيليّ ({@see SalesByItemController::index()}).

    سطرٌ لكلّ بندٍ في فاتورةٍ أو مرتجعٍ أو إشعارٍ دائن، بصنفه وتصنيفه
    ومجموعته ووحدته ومستودعه — فالمجموعةُ والوحدةُ عمودان وفلتران هنا لا
    تقريرٌ مستقلّ. والتجميعُ في «مبيعات الأصناف» من السطور نفسِها.

    المستندُ الذي لا بنود له يظهر سطراً «مستند بلا بنود» بمبلغه، كي يساوي
    الإجماليُّ الدفترَ.
--}}
@extends('sales::master')

@section('title')
    {{ $pageTitle }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .ag-kpi__units { font-size: 1.05rem; line-height: 1.35; }
        .rpt .ag-kpi__units small { font-size: .72rem; font-weight: 600; color: var(--rpt-muted); }
        .rpt .ag-table td small.d-block { color: var(--rpt-muted); font-size: .7rem; }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;

    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number(abs($v));
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $clientLink = function ($id, $name) {
        $label = e(trim((string) $name) !== '' ? $name : '-');

        return $id
            ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link">'.$label.'</a>'
            : $label;
    };

    $reportTitle = $pageTitle;
    $reportAction = $formAction;
    $periodLabel = __('sales_reports.common.period_range', ['from' => $fromDate->format('d/m/Y'), 'to' => $toDate->format('d/m/Y')]);

    /* الرسم: صافي المبيعات يوماً بيوم (أو شهراً بشهر إن طال المدى) */
    $byMonth = $fromDate->diffInDays($toDate) > 62;
    $chartSeries = $lines->groupBy(fn ($l) => $byMonth ? substr($l['date'], 0, 7) : $l['date'])
        ->map(fn ($g) => round($g->sum('amount'), 2))->sortKeys();
    $chartData = ['labels' => $chartSeries->keys()->all(), 'values' => $chartSeries->values()->all()];
    $chartTitle = 'صافي المبيعات '.($byMonth ? 'شهرياً' : 'يومياً');
@endphp

<div class="rpt">
    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => $reportTitle,
        'periodLabel' => $periodLabel,
        'filters' => [],
    ])

    @include('sales::sals_report.salesRport.partials.item-report-common', ['screenKind' => 'lines'])

    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-list-ul"></i> {{ $isSummary ? 'ملخص المستندات' : 'البنود' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? $documents->count().' مستند' : $lines->count().' بند' }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> {{ __('sales_reports.common.excel') }}</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> {{ __('sales_reports.common.print') }}</button>
            </div>
        </div>

        <div class="rpt-scroll">
            @if ($isSummary)
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>{{ __('sales_reports.columns.date') }}</th>
                        <th>المستند</th>
                        <th>{{ __('sales_reports.columns.client') }}</th>
                        <th>{{ __('sales_reports.columns.employee') }}</th>
                        <th>{{ __('sales_reports.columns.branch') }}</th>
                        <th>عدد البنود</th>
                        <th>عدد الأصناف</th>
                        <th>الكمية (أساسية)</th>
                        <th>الخصم</th>
                        <th>{{ __('sales_reports.columns.amount') }}</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse ($documents as $d)
                        <tr data-row="1" class="rpt-row-{{ $d['kind'] }}">
                            <td>{{ $loop->iteration }}</td>
                            <td class="rpt-num">{{ $d['date'] }}</td>
                            <td>
                                <strong>{{ $d['reference_number'] }}</strong>
                                <div><span class="rpt-pill rpt-pill-{{ $d['entry_type_class'] }}">{{ $d['entry_type_label'] }}</span></div>
                            </td>
                            <td class="rpt-name">{!! $clientLink($d['client_id'], $d['client_name']) !!}</td>
                            <td>{{ $d['employee_name'] }}</td>
                            <td>{{ $d['branch_name'] }}</td>
                            <td class="rpt-num">{{ $d['lines'] }}</td>
                            <td class="rpt-num">{{ $d['products'] }}</td>
                            <td class="rpt-num {{ $d['base_quantity'] < 0 ? 'rpt-neg' : '' }}">{{ $qty($d['base_quantity']) }}</td>
                            <td class="rpt-num {{ abs($d['discount']) > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ abs($d['discount']) > 0.005 ? $signed($d['discount']) : '—' }}</td>
                            <td class="rpt-num {{ $d['amount'] < 0 ? 'rpt-neg' : 'rpt-pos' }}"><strong>{{ $signed($d['amount']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد حركة بنود في {{ $periodLabel }}</td></tr>
                    @endforelse
                </tbody>
                @if ($documents->count())
                    <tfoot>
                        <tr>
                            <td colspan="6">{{ __('sales_reports.common.grand_total') }} — {{ $totals['count'] }} فاتورة · {{ $totals['returns_count'] }} مرتجع · {{ $totals['credit_notes_count'] }} إشعار</td>
                            <td class="rpt-num">{{ $documents->sum('lines') }}</td>
                            <td></td>
                            <td class="rpt-num">{{ $qty($totals['net_base_qty']) }}</td>
                            <td class="rpt-num">{{ $signed($totals['discount']) }}</td>
                            <td class="rpt-num"><strong>{{ $signed($totals['net_sales']) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            @else
            <table class="rpt-table ag-table" id="reportTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>{{ __('sales_reports.columns.date') }}</th>
                        <th>المستند</th>
                        <th>{{ __('sales_reports.columns.client') }}</th>
                        <th>{{ __('sales_reports.columns.employee') }}</th>
                        <th>الصنف</th>
                        <th>التصنيف / المجموعة</th>
                        <th>المستودع</th>
                        <th>الكمية</th>
                        <th>الوحدة</th>
                        <th>السعر</th>
                        <th>الخصم</th>
                        <th>{{ __('sales_reports.columns.amount') }}</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse ($lines as $l)
                        <tr data-row="1" class="rpt-row-{{ $l['kind'] }}">
                            <td>{{ $loop->iteration }}</td>
                            <td class="rpt-num">{{ $l['date'] }}</td>
                            <td>
                                <strong>{{ $l['reference_number'] }}</strong>
                                <div><span class="rpt-pill rpt-pill-{{ $l['entry_type_class'] }}">{{ $l['entry_type_label'] }}</span></div>
                            </td>
                            <td class="rpt-name">{!! $clientLink($l['client_id'], $l['client_name']) !!}</td>
                            <td>{{ $l['employee_name'] }}</td>
                            <td class="rpt-name">
                                {{ $l['product_name'] }}
                                @if (($l['is_gift'] ?? false))<span class="rpt-pill rpt-pill-warning">هدية</span>@endif
                                @if ($l['product_code'] !== '-')<small class="d-block">{{ $l['product_code'] }}</small>@endif
                            </td>
                            <td>{{ $l['category_name'] }}<small class="d-block">{{ $l['product_group_name'] }}</small></td>
                            <td>{{ $l['storehouse_name'] }}</td>
                            <td class="rpt-num {{ $l['quantity'] < 0 ? 'rpt-neg' : '' }}"><strong>{{ $qty($l['quantity']) }}</strong></td>
                            <td>{{ $l['unit_name'] }}</td>
                            <td class="rpt-num">{{ $money($l['unit_price']) }}</td>
                            <td class="rpt-num {{ abs($l['discount']) > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ abs($l['discount']) > 0.005 ? $signed($l['discount']) : '—' }}</td>
                            <td class="rpt-num {{ $l['amount'] < 0 ? 'rpt-neg' : 'rpt-pos' }}"><strong>{{ $signed($l['amount']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد حركة بنود في {{ $periodLabel }}</td></tr>
                    @endforelse
                </tbody>
                @if ($lines->count())
                    <tfoot>
                        <tr>
                            <td colspan="11">{{ __('sales_reports.common.grand_total') }} — {{ $totals['count'] }} فاتورة · {{ $totals['returns_count'] }} مرتجع · {{ $totals['credit_notes_count'] }} إشعار</td>
                            <td class="rpt-num">{{ $signed($totals['discount']) }}</td>
                            <td class="rpt-num"><strong>{{ $signed($totals['net_sales']) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $isSummary ? 'مستند' : 'بند',
            'pagerStorage' => 'agSalesItemLinesPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('sales::sals_report.salesRport.partials.item-report-scripts')

    @php
        $excelRows = $lines->map(fn ($l, $i) => [
            'values' => [
                $i + 1, $l['date'], (string) $l['reference_number'], $l['entry_type_label'], $l['client_name'], $l['employee_name'],
                (string) $l['product_code'], $l['product_name'], $l['category_name'], $l['product_group_name'], $l['storehouse_name'],
                round($l['quantity'], 2), $l['unit_name'], round($l['unit_price'], 2), round($l['discount'], 2),
                round($l['amount'], 2),
            ],
            'alignments' => [null, null, null, null, 'right'],
        ])->all();
        $excelSummary = $lines->count() ? [[
            'values' => ['', 'الإجمالي العام', '', '', '', '', '', '', '', '', '', '', '', '', round($totals['discount'], 2), round($totals['net_sales'], 2)],
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [];
    @endphp

    @php
        if ($isSummary) {
            $excelRows = $documents->map(fn ($d, $i) => [
                'values' => [$i + 1, $d['date'], (string) $d['reference_number'], $d['entry_type_label'], $d['client_name'], $d['employee_name'], $d['branch_name'],
                    $d['lines'], $d['products'], round($d['base_quantity'], 2), round($d['discount'], 2), round($d['amount'], 2)],
                'alignments' => [null, null, null, null, 'right'],
            ])->all();
            $excelSummary = $excelRows ? [[
                'values' => ['', 'الإجمالي العام', '', '', '', '', '', $documents->sum('lines'), '', round($totals['net_base_qty'], 2), round($totals['discount'], 2), round($totals['net_sales'], 2)],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
        $excelHeadersLines = $isSummary
            ? ['#', 'التاريخ', 'المستند', 'النوع', 'العميل', 'الموظف', 'الفرع', 'عدد البنود', 'عدد الأصناف', 'الكمية (أساسية)', 'الخصم', 'المبلغ']
            : ['#', 'التاريخ', 'المستند', 'النوع', 'العميل', 'الموظف', 'كود الصنف', 'الصنف', 'التصنيف', 'مجموعة المنتج', 'المستودع', 'الكمية', 'الوحدة', 'السعر', 'الخصم', 'المبلغ'];
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => 'مبيعات_البنود'.($isSummary ? '_ملخص' : '_تفاصيل'),
        'mainTitle' => $reportTitle,
        'info' => ['الفترة' => $periodLabel],
        'cards' => [
            ['title' => 'المبيعات', 'value' => $money($totals['sales_amount']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المرتجعات والإشعارات', 'value' => $money($totals['returns_amount'] + $totals['credit_notes_amount']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'صافي المبيعات', 'value' => $money($totals['net_sales']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
        ],
        'headers' => $excelHeadersLines,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
