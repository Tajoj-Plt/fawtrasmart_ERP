{{--
    «مبيعات الأصناف» — التقريرُ المجمَّع ({@see SalesByProductController}).

    سطرٌ لكلّ صنفٍ افتراضاً، و«التجميع» يجعله لكلّ تصنيفٍ أو مستودعٍ أو
    عميلٍ أو موظفٍ أو فرعٍ أو يومٍ أو شهر. والتفصيلُ بندًا بندًا في
    «مبيعات البنود» (`Sales_Item_Lines`)، ومن السطور نفسِها.

    المبالغ من القيود — نفسُ أساس «المبيعات حسب العميل».
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
        .rpt .ag-kpi__units small, .rpt .ag-units small { font-size: .72rem; font-weight: 600; color: var(--rpt-muted); }
        .rpt .ag-units div { white-space: nowrap; }
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; }
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
    $signClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-pos' : 'rpt-neg');
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $clientLink = function ($id, $name) {
        $label = e(trim((string) $name) !== '' ? $name : '-');

        return $id && is_numeric($id)
            ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link">'.$label.'</a>'
            : $label;
    };

    $reportTitle = $pageTitle;
    $reportAction = $formAction;
    $periodLabel = __('sales_reports.common.period_range', ['from' => $fromDate->format('d/m/Y'), 'to' => $toDate->format('d/m/Y')]);
    $groupLabel = $groupByOptions[$groupBy] ?? 'الصنف';
    $isItemGroup = $groupBy === 'item';
    $isClientGroup = $groupBy === 'client';
    $groupIsTime = in_array($groupBy, ['day', 'month'], true);
    $groupUnit = [
        'item' => 'صنف', 'category' => 'تصنيف', 'product_group' => 'مجموعة', 'storehouse' => 'مستودع', 'unit' => 'وحدة',
        'day' => 'يوم', 'month' => 'شهر', 'employee' => 'موظف', 'client' => 'عميل', 'branch' => 'فرع',
    ][$groupBy] ?? 'مجموعة';

    $chartSource = $groupIsTime ? $reportData->sortKeys()->take(-($groupBy === 'day' ? 31 : 24)) : $reportData->take(10);
    $chartData = [
        'labels' => $chartSource->map(fn ($r) => (string) $r['name'])->values()->all(),
        'values' => $chartSource->map(fn ($r) => round((float) $r['net'], 2))->values()->all(),
    ];
    $chartTitle = ($groupIsTime ? 'صافي المبيعات حسب ' : 'الأعلى صافياً — ').$groupLabel;

    $unitsHtml = fn (array $byUnit) => collect($byUnit)->map(fn ($v, $u) => '<div>'.e($qty($v)).' <small>'.e($u).'</small></div>')->implode('') ?: '—';
    $cols = 9 + ($isItemGroup ? 2 : 0) + ($isClientGroup ? 2 : 0);
    $lead = 2 + ($isItemGroup ? 2 : 0) + ($isClientGroup ? 2 : 0);
@endphp

<div class="rpt">
    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => $reportTitle,
        'periodLabel' => $periodLabel,
        'filters' => ['التجميع' => $groupLabel],
    ])

    @include('sales::sals_report.salesRport.partials.item-report-common', ['screenKind' => 'products'])

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'المبيعات حسب '.$groupLabel : 'تفاصيل البنود حسب '.$groupLabel }}
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} {{ $groupUnit }}</span>
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
                        <th>{{ $groupLabel }}</th>
                        @if ($isItemGroup)
                            <th>التصنيف</th>
                            <th>{{ __('sales_reports.group_by.product_group') }}</th>
                        @endif
                        @if ($isClientGroup)
                            <th>{{ __('sales_reports.columns.status') }}</th>
                            <th>{{ __('sales_reports.columns.stage') }}</th>
                        @endif
                        <th>المستندات</th>
                        <th>الكمية المباعة</th>
                        <th>الكمية المرتجعة</th>
                        <th>صافي الكمية بوحداتها</th>
                        <th>صافي الكمية (أساسية)</th>
                        <th>المبيعات</th>
                        <th>المرتجعات والإشعارات</th>
                        <th>{{ __('sales_reports.columns.net_sales') }}</th>
                        <th>الخصم</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse ($reportData as $key => $row)
                        <tr data-row="1">
                            <td>{{ $loop->iteration }}</td>
                            <td class="rpt-name">
                                {!! $isClientGroup ? $clientLink($key, $row['name']) : e($row['name']) !!}
                                @if ($isItemGroup && $row['code'] && $row['code'] !== '-')
                                    <small class="d-block">{{ $row['code'] }}</small>
                                @endif
                            </td>
                            @if ($isItemGroup)
                                <td>{{ $row['category_name'] }}</td>
                                <td>{{ $row['product_group_name'] }}</td>
                            @endif
                            @if ($isClientGroup)
                                <td>{{ $row['status_name'] ?? '—' }}</td>
                                <td>{{ $row['stage_name'] ?? '—' }}</td>
                            @endif
                            <td class="rpt-num">{{ $row['documents'] }}</td>
                            <td class="rpt-num">{{ $qty($row['sales_qty']) }}</td>
                            <td class="rpt-num {{ $row['returns_qty'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $row['returns_qty'] > 0 ? $qty($row['returns_qty']) : '—' }}</td>
                            <td class="rpt-num ag-units">{!! $unitsHtml($row['quantities_by_unit']) !!}</td>
                            <td class="rpt-num"><strong>{{ $qty($row['net_base_qty']) }}</strong></td>
                            <td class="rpt-num rpt-pos">{{ $money($row['amount']) }}</td>
                            <td class="rpt-num {{ $row['returns_amount'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $row['returns_amount'] > 0 ? '('.$money($row['returns_amount']).')' : '—' }}</td>
                            <td class="rpt-num {{ $signClass($row['net']) }}"><strong>{{ $signed($row['net']) }}</strong></td>
                            <td class="rpt-num {{ abs($row['discount']) > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ abs($row['discount']) > 0.005 ? $signed($row['discount']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $cols + 2 }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد حركة بنود في {{ $periodLabel }}</td></tr>
                    @endforelse
                </tbody>
                @if (count($reportData))
                    <tfoot>
                        <tr>
                            <td colspan="{{ $lead }}">{{ __('sales_reports.common.grand_total') }}</td>
                            <td class="rpt-num">{{ ($totals['count'] ?? 0) + ($totals['returns_count'] ?? 0) + ($totals['credit_notes_count'] ?? 0) }}</td>
                            <td class="rpt-num">{{ $qty($totals['sales_qty']) }}</td>
                            <td class="rpt-num rpt-neg">{{ $qty($totals['returns_qty']) }}</td>
                            <td class="rpt-num ag-units">{!! $unitsHtml($totals['quantities_by_unit']) !!}</td>
                            <td class="rpt-num">{{ $qty($totals['net_base_qty']) }}</td>
                            <td class="rpt-num rpt-pos">{{ $money($totals['sales_amount']) }}</td>
                            <td class="rpt-num rpt-neg">({{ $money($totals['returns_amount'] + $totals['credit_notes_amount']) }})</td>
                            <td class="rpt-num"><strong>{{ $signed($totals['net_sales']) }}</strong></td>
                            <td class="rpt-num">{{ $signed($totals['discount']) }}</td>
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
                        <th>المستودع</th>
                        <th>الكمية</th>
                        <th>الوحدة</th>
                        <th>السعر</th>
                        <th>الخصم</th>
                        <th>{{ __('sales_reports.columns.amount') }}</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse ($reportData as $key => $row)
                        @php $rowKey = 'g'.$loop->index; @endphp
                        <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                            <td colspan="12" class="rpt-name">
                                <strong>{!! $isClientGroup ? $clientLink($key, $row['name']) : e($row['name']) !!}</strong>
                                <small class="text-muted">— {{ $groupLabel }}</small>
                                <span class="rpt-pill rpt-pill-secondary">{{ $row['lines'] }} بند</span>
                                <span class="rpt-pill rpt-pill-secondary">{{ $row['documents'] }} مستند</span>
                            </td>
                        </tr>
                        @foreach (($row['items'] ?? []) as $l)
                            <tr class="rpt-row-{{ $l['kind'] }}" data-parent-row="{{ $rowKey }}">
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
                                    <small class="d-block">{{ $l['category_name'] }}</small>
                                </td>
                                <td>{{ $l['storehouse_name'] }}</td>
                                <td class="rpt-num {{ $l['quantity'] < 0 ? 'rpt-neg' : '' }}"><strong>{{ $qty($l['quantity']) }}</strong></td>
                                <td>{{ $l['unit_name'] }}</td>
                                <td class="rpt-num">{{ $money($l['unit_price']) }}</td>
                                <td class="rpt-num {{ abs($l['discount']) > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ abs($l['discount']) > 0.005 ? $signed($l['discount']) : '—' }}</td>
                                <td class="rpt-num {{ $l['amount'] < 0 ? 'rpt-neg' : 'rpt-pos' }}"><strong>{{ $signed($l['amount']) }}</strong></td>
                            </tr>
                        @endforeach
                        <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                            <td colspan="7">إجمالي {{ $row['name'] }}
                                <small class="text-muted">(مبيعات {{ $money($row['amount']) }} − مرتجعات وإشعارات {{ $money($row['returns_amount']) }})</small>
                            </td>
                            <td class="rpt-num">{{ $qty($row['net_base_qty']) }}</td>
                            <td>أساسية</td>
                            <td></td>
                            <td class="rpt-num">{{ $signed($row['discount']) }}</td>
                            <td class="rpt-num {{ $signClass($row['net']) }}">{{ $signed($row['net']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد حركة بنود في {{ $periodLabel }}</td></tr>
                    @endforelse
                </tbody>
                @if (count($reportData))
                    <tfoot>
                        <tr>
                            <td colspan="10">{{ __('sales_reports.common.grand_total') }} — {{ $totals['count'] }} فاتورة · {{ $totals['returns_count'] }} مرتجع · {{ $totals['credit_notes_count'] }} إشعار</td>
                            <td class="rpt-num">{{ $signed($totals['discount']) }}</td>
                            <td class="rpt-num"><strong>{{ $signed($totals['net_sales']) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $groupUnit,
            'pagerStorage' => 'agSalesByProductPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    @include('sales::sals_report.salesRport.partials.item-report-scripts')

    @php
        $unitsText = fn (array $byUnit) => collect($byUnit)->map(fn ($v, $u) => $qty($v).' '.$u)->implode(' + ');
        $excelHeaders = array_merge(
            ['#', $groupLabel],
            $isItemGroup ? ['التصنيف', 'مجموعة المنتج'] : [],
            $isClientGroup ? ['الحالة', 'الوضع'] : [],
            ['المستندات', 'الكمية المباعة', 'الكمية المرتجعة', 'صافي الكمية بوحداتها', 'صافي الكمية (أساسية)', 'المبيعات', 'المرتجعات والإشعارات', 'صافي المبيعات', 'الخصم']
        );
        $excelRows = $reportData->values()->map(fn ($r, $i) => [
            'values' => array_merge(
                [$i + 1, (string) $r['name']],
                $isItemGroup ? [$r['category_name'], $r['product_group_name']] : [],
                $isClientGroup ? [$r['status_name'] ?? '—', $r['stage_name'] ?? '—'] : [],
                [$r['documents'], round($r['sales_qty'], 2), round($r['returns_qty'], 2), $unitsText($r['quantities_by_unit']), round($r['net_base_qty'], 2),
                 round($r['amount'], 2), round($r['returns_amount'], 2), round($r['net'], 2), round($r['discount'], 2)]
            ),
            'alignments' => [null, 'right'],
        ])->all();
        $excelSummary = count($reportData) ? [[
            'values' => array_merge(
                ['', 'الإجمالي العام'],
                array_fill(0, $lead - 2, ''),
                ['', round($totals['sales_qty'], 2), round($totals['returns_qty'], 2), $unitsText($totals['quantities_by_unit']), round($totals['net_base_qty'], 2),
                 round($totals['sales_amount'], 2), round($totals['returns_amount'] + $totals['credit_notes_amount'], 2), round($totals['net_sales'], 2), round($totals['discount'], 2)]
            ),
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [];
    @endphp

    @php
        if (! $isSummary) {
            $excelHeaders = ['#', $groupLabel, 'التاريخ', 'المستند', 'النوع', 'العميل', 'الموظف', 'الصنف', 'المستودع', 'الكمية', 'الوحدة', 'السعر', 'الخصم', 'المبلغ'];
            $excelRows = [];
            $n = 0;
            foreach ($reportData as $r) {
                foreach (($r['items'] ?? []) as $l) {
                    $excelRows[] = [
                        'values' => [++$n, (string) $r['name'], $l['date'], (string) $l['reference_number'], $l['entry_type_label'], $l['client_name'], $l['employee_name'],
                            $l['product_name'], $l['storehouse_name'], round($l['quantity'], 2), $l['unit_name'], round($l['unit_price'], 2), round($l['discount'], 2), round($l['amount'], 2)],
                        'alignments' => [null, 'right'],
                    ];
                }
            }
            $excelSummary = $excelRows ? [[
                'values' => ['', 'الإجمالي العام', '', '', '', '', '', '', '', '', '', '', round($totals['discount'], 2), round($totals['net_sales'], 2)],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => 'مبيعات_الأصناف_حسب_'.str_replace(' ', '_', $groupLabel).($isSummary ? '_ملخص' : '_تفاصيل'),
        'mainTitle' => $reportTitle.' — حسب '.$groupLabel,
        'info' => ['الفترة' => $periodLabel, 'التجميع' => $groupLabel],
        'cards' => [
            ['title' => 'المبيعات', 'value' => $money($totals['sales_amount']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المرتجعات والإشعارات', 'value' => $money($totals['returns_amount'] + $totals['credit_notes_amount']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'صافي المبيعات', 'value' => $money($totals['net_sales']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
        ],
        'headers' => $excelHeaders,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
