{{--
    تقرير أرباح المبيعات حسب العميل — على تصميم تقارير العملاء المعتمد (`ag-*`):
    ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات
    بترتيب المعادلة (مبيعات − مرتجعات = صافٍ − تكلفة = ربح)، رسمان (الأعلى
    ربحاً + تكلفة الصافي وربحه)، ثمّ الجدولُ بترقيم صفحات.

    ما لم يتغيّر: الفلاترُ وأسماؤها، وزرّا «ملخص/تفاصيل» (`view_type`)،
    وأعمدةُ الجدولين (والترويسةُ المزدوجة في التفاصيل)، والتصديرُ المبنيُّ في
    الخادم، والطباعةُ للصفحة بترويسة المنشأة.
--}}
@extends('sales::master')

@section('title')
    {{ __('sales_reports.profits.title') }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
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
    $qty = fn ($v) => number_format((float) $v, 0);
    $pct = fn ($v) => number_format((float) $v, 2).'%';
    $currency = CurrencySettings::name();

    /* رابطُ العميل بلا include لكلّ صف — كلُّ include يُشغّل مُركِّبات العرض العامّة */
    $clientLink = function ($id, $name, $fallback = '-') {
        $label = e(trim((string) $name) !== '' ? $name : $fallback);

        return $id
            ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">'.$label.'</a>'
            : $label;
    };

    $periodLabel = __('sales_reports.common.period_range', [
        'from' => $fromDate->format('d/m/Y'),
        'to' => $toDate->format('d/m/Y'),
    ]);

    /* الفرعُ من شجرة «تابع لـ» وحدها؛ وبلا عقدةٍ في الطلب فهو فرعُ الهيدر. */
    $selectedBranchName = ($effectiveBranchId ?? null)
        ? optional(\App\Models\Branch::findCached($effectiveBranchId))->name
        : null;

    $selectedClient = request('client_id') ? optional($clients->firstWhere('id', (int) request('client_id')))->trade_name : null;
    $selectedEmployee = request('employee_id') ? optional($users->firstWhere('id', (int) request('employee_id')))->name : null;
    $selectedCategory = request('category_id') ? optional($categories->firstWhere('id', (int) request('category_id')))->name : null;
    $selectedStatus = request('status') === 'no_status'
        ? __('sales_reports.filters.no_status')
        : (request('status') ? optional($statuses->firstWhere('id', (int) request('status')))->name : null);
    $selectedStage = request('registration_stage') === 'no_stage'
        ? __('sales_reports.filters.no_stage')
        : (request('registration_stage') ? optional($registrationStages->firstWhere('id', (int) request('registration_stage')))->name : null);

    $tSales = (float) ($totals['sales_amount'] ?? 0);
    $tReturns = (float) ($totals['returns_amount'] ?? 0);
    $tNet = (float) ($totals['net_sales_amount'] ?? 0);
    $tCost = (float) ($totals['cost_of_sales'] ?? 0);
    $tProfit = (float) ($totals['profit_amount'] ?? 0);

    /* ─────────── الرسمان ─────────── */
    $profitChartSource = $reportData->sortByDesc('profit_amount')->take(10);
    $profitChart = [
        'labels' => $profitChartSource->map(fn ($r) => (string) ($r['name'] ?: __('sales_reports.common.none')))->values()->all(),
        'values' => $profitChartSource->map(fn ($r) => round((float) $r['profit_amount'], 2))->values()->all(),
    ];

    /* الصافي = تكلفتُه + ربحُه — فالحلقةُ تقسم الصافي بينهما حين يكون الربحُ موجباً */
    $splitLegend = [
        ['label' => __('sales_reports.profits.cost_of_sales'), 'value' => $tCost, 'color' => '#f97316'],
        ['label' => __('sales_reports.profits.profit_amount'), 'value' => $tProfit, 'color' => '#10b981'],
    ];
    foreach ($splitLegend as $si => $slice) {
        $splitLegend[$si]['pct'] = $tNet > 0.005 ? round($slice['value'] / $tNet * 100) : null;
    }
    $splitDonut = array_values(array_filter($splitLegend, fn ($s) => $s['value'] > 0.005));
@endphp

<div class="rpt">

    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => __('sales_reports.profits.title').' — '.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view')),
        'periodLabel' => $periodLabel,
        'filters' => [
            __('sales_reports.filters.client') => $selectedClient,
            __('sales_reports.filters.employee') => $selectedEmployee,
            __('sales_reports.filters.branch') => $selectedBranchName,
            __('sales_reports.filters.client_category') => $selectedCategory,
            __('sales_reports.filters.client_status') => $selectedStatus,
            __('sales_reports.filters.client_stage') => $selectedStage,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'coins',
            'headTitle' => __('sales_reports.profits.heading'),
            'headSubtitle' => __('sales_reports.profits.subtitle'),
            'headPeriod' => $periodLabel,
            'headExtra' => __('sales_reports.profits.clients_count').': '.count($reportData),
            'headGuideKey' => 'sales-reports.profits',
            'headCrumbLabel' => __('sales_reports.index.title'),
            'headCrumbUrl' => route('salesReports.index'),
        ])
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('salesReports.profits') }}" id="filterForm">

                @include('sales::sals_report.report_partials.period-tabs')

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="{{ __('sales_reports.filters.from_date') }}"
                                   value="{{ request('date_from', $fromDate->format('Y-m-d')) }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="{{ __('sales_reports.filters.to_date') }}"
                                   value="{{ request('date_to', $toDate->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="rpt-field">
                        <label for="client_id">{{ __('sales_reports.filters.client') }}</label>
                        <select name="client_id" id="client_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.filters.all_clients') }}</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>{{ $client->code }} - {{ $client->trade_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">{{ __('sales_reports.filters.employee') }}</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.filters.all_employees') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('employee_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="category_id">{{ __('sales_reports.filters.client_category') }}</label>
                        <select name="category_id" id="category_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="status">{{ __('sales_reports.filters.client_status') }}</label>
                        <select name="status" id="status" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.filters.all_statuses') }}</option>
                            <option value="no_status" @selected(request('status') == 'no_status')>{{ __('sales_reports.filters.no_status') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected((string) request('status') === (string) $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="registration_stage">{{ __('sales_reports.filters.client_stage') }}</label>
                        <select name="registration_stage" id="registration_stage" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.filters.all_stages') }}</option>
                            <option value="no_stage" @selected(request('registration_stage') == 'no_stage')>{{ __('sales_reports.filters.no_stage') }}</option>
                            @foreach ($registrationStages as $stage)
                                <option value="{{ $stage->id }}" @selected((string) request('registration_stage') === (string) $stage->id)>{{ $stage->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- «تابع لـ» — المُنتقي المشترك، يبدأ من فرع الشريط العلويّ --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => $treeNodes ?? [],
                                'currentNode' => $effectiveBranchId ?? null,
                            ])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="btn-group ag-view-toggle" role="group" aria-label="طريقة العرض">
                        <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-list"></i> {{ __('sales_reports.common.summary') }}
                        </button>
                        <button type="submit" name="view_type" value="details" class="btn {{ ! $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-th-list"></i> {{ __('sales_reports.common.details') }}
                        </button>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('salesReports.profits') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> {{ __('sales_reports.common.reset') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات (بترتيب المعادلة) ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.profits.sales_amount') }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tSales) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.kpi.before_returns') }} · {{ $totals['sales_count'] ?? 0 }} {{ __('sales_reports.columns.operations_count') }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-shopping-cart"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.profits.returns_amount') }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tReturns) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.columns.quantity') }}: {{ $qty($totals['returns_qty'] ?? 0) }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.profits.net_sales_amount') }}</div>
                <div class="ag-kpi__value"><bdi class="{{ $tNet < -0.005 ? 'rpt-neg' : '' }}">{{ $signed($tNet) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.kpi.sales_minus_returns') }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.profits.cost_of_sales') }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tCost) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.profits.cost') }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-box"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.profits.profit_amount') }}</div>
                <div class="ag-kpi__value"><bdi class="{{ $tProfit < -0.005 ? 'rpt-neg' : '' }}">{{ $signed($tProfit) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.profits.profit_to_sales_ratio') }}: {{ $pct($totals['profit_to_sales_ratio'] ?? 0) }} · {{ count($reportData) }} {{ __('sales_reports.filters.client') }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-coins"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> الأعلى ربحاً — {{ __('sales_reports.columns.client') }}</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($profitChart['labels']))
                    <div class="ag-canvas"><canvas id="profitChart" aria-label="الأعلى ربحاً" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> {{ __('sales_reports.profits.net_sales_amount') }}: تكلفةٌ وربح</h4>
            </div>
            <div class="rpt-card__body">
                @if ($tNet > 0.005)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($splitLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $signed($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($splitDonut))
                            <div class="ag-donut">
                                <canvas id="splitDonut" aria-label="تكلفة الصافي وربحه" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $money($tNet) }}</strong>
                                    <small>{{ __('sales_reports.profits.net_sales_amount') }} · {{ $currency }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i>
                {{ $isSummary ? __('sales_reports.profits.summary_heading') : __('sales_reports.profits.details_heading') }}
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} {{ __('sales_reports.filters.client') }}</span>
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
                            <th style="width:40px">{{ __('sales_reports.columns.row') }}</th>
                            <th style="width:22%">{{ __('sales_reports.columns.client') }}</th>
                            <th>{{ __('sales_reports.columns.status') }}</th>
                            <th>{{ __('sales_reports.columns.stage') }}</th>
                            <th>{{ __('sales_reports.columns.operations_count') }}</th>
                            <th>{{ __('sales_reports.profits.sales_amount') }}</th>
                            <th>{{ __('sales_reports.profits.returns') }}</th>
                            <th>{{ __('sales_reports.profits.net_sales_amount') }}</th>
                            <th>{{ __('sales_reports.profits.cost') }}</th>
                            <th>{{ __('sales_reports.profits.profit') }}</th>
                            <th>{{ __('sales_reports.profits.profit_ratio') }}</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $clientId => $data)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">{!! $clientLink($clientId ?: null, $data['name'], __('sales_reports.common.none')) !!}</td>
                                <td>
                                    @if ($data['status'])
                                        <span class="rpt-pill" style="background:{{ $data['status']->color ?? '#999' }}20;color:{{ $data['status']->color ?? '#999' }}">{{ $data['status']->name }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($data['registration_stage'])
                                        <span class="rpt-pill" style="background:{{ $data['registration_stage']->color ?? '#999' }}20;color:{{ $data['registration_stage']->color ?? '#999' }}">{{ $data['registration_stage']->name }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $data['sales_count'] }}</span></td>
                                <td class="rpt-num rpt-pos">{{ $money($data['sales_amount']) }}</td>
                                <td class="rpt-num {{ $data['returns_amount'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $data['returns_amount'] > 0 ? '('.$money($data['returns_amount']).')' : '—' }}
                                </td>
                                <td class="rpt-num {{ $signClass($data['net_sales_amount']) }}">{{ $signed($data['net_sales_amount']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($data['cost_of_sales']) }}</td>
                                <td class="rpt-num {{ $signClass($data['profit_amount']) }}"><strong>{{ $signed($data['profit_amount']) }}</strong></td>
                                <td>
                                    <span class="rpt-pill rpt-pill-{{ $data['profit_to_sales_ratio'] >= 0 ? 'success' : 'danger' }}">
                                        {{ $pct($data['profit_to_sales_ratio']) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="rpt-empty">
                                <i class="fas fa-inbox"></i>
                                {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if (count($reportData) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="4">{{ __('sales_reports.common.grand_total') }}</td>
                                <td>{{ $totals['sales_count'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['sales_amount']) }}</td>
                                <td class="rpt-num rpt-neg">({{ $money($totals['returns_amount']) }})</td>
                                <td class="rpt-num {{ $signClass($totals['net_sales_amount']) }}">{{ $signed($totals['net_sales_amount']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($totals['cost_of_sales']) }}</td>
                                <td class="rpt-num {{ $signClass($totals['profit_amount']) }}">{{ $signed($totals['profit_amount']) }}</td>
                                <td>
                                    <span class="rpt-pill rpt-pill-{{ $totals['profit_to_sales_ratio'] >= 0 ? 'success' : 'danger' }}">
                                        {{ $pct($totals['profit_to_sales_ratio']) }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                {{-- الترويسة المزدوجة محفوظة: كل مجموعةٍ (مبيعات/مردود/صافٍ/ربح/نسب)
                     تجمع عموديها كمّيةً وقيمةً كما كان الجدول القديم --}}
                <table class="rpt-table rpt-table--grouped ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th rowspan="2" style="vertical-align:middle;width:20%">{{ __('sales_reports.columns.client') }}</th>
                            <th rowspan="2" style="vertical-align:middle">{{ __('sales_reports.columns.status') }}</th>
                            <th rowspan="2" style="vertical-align:middle">{{ __('sales_reports.columns.stage') }}</th>
                            <th rowspan="2" style="vertical-align:middle">{{ __('sales_reports.columns.operations_count') }}</th>
                            <th colspan="2">{{ __('sales_reports.profits.sales_amount') }}</th>
                            <th colspan="2">{{ __('sales_reports.profits.returns') }}</th>
                            <th colspan="2">{{ __('sales_reports.profits.net_sales_amount') }}</th>
                            <th rowspan="2" style="vertical-align:middle">{{ __('sales_reports.profits.total_cost') }}</th>
                            <th colspan="2">{{ __('sales_reports.profits.profit') }}</th>
                            <th colspan="2">{{ __('sales_reports.profits.profit_ratio') }}</th>
                        </tr>
                        <tr>
                            <th>{{ __('sales_reports.columns.quantity') }}</th>
                            <th>{{ __('sales_reports.columns.value') }}</th>
                            <th>{{ __('sales_reports.columns.quantity') }}</th>
                            <th>{{ __('sales_reports.columns.value') }}</th>
                            <th>{{ __('sales_reports.columns.quantity') }}</th>
                            <th>{{ __('sales_reports.columns.value') }}</th>
                            <th>{{ __('sales_reports.columns.value') }}</th>
                            <th>{{ __('sales_reports.profits.profit_percentage') }}</th>
                            <th>{{ __('sales_reports.profits.to_sales') }}</th>
                            <th>{{ __('sales_reports.profits.to_cost') }}</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $clientId => $data)
                            <tr data-row="1">
                                <td class="rpt-name">{!! $clientLink($clientId ?: null, $data['name'], __('sales_reports.common.none')) !!}</td>
                                <td>
                                    @if ($data['status'])
                                        <span class="rpt-pill" style="background:{{ $data['status']->color ?? '#999' }}20;color:{{ $data['status']->color ?? '#999' }}">{{ $data['status']->name }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($data['registration_stage'])
                                        <span class="rpt-pill" style="background:{{ $data['registration_stage']->color ?? '#999' }}20;color:{{ $data['registration_stage']->color ?? '#999' }}">{{ $data['registration_stage']->name }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="rpt-num">{{ $data['sales_count'] }}</td>
                                <td class="rpt-num">{{ $qty($data['sales_qty']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($data['sales_amount']) }}</td>
                                <td class="rpt-num">{{ $qty($data['returns_qty']) }}</td>
                                <td class="rpt-num {{ $data['returns_amount'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $money($data['returns_amount']) }}</td>
                                <td class="rpt-num">{{ $qty($data['net_sales_qty']) }}</td>
                                <td class="rpt-num {{ $signClass($data['net_sales_amount']) }}">{{ $signed($data['net_sales_amount']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($data['cost_of_sales']) }}</td>
                                <td class="rpt-num {{ $signClass($data['profit_amount']) }}"><strong>{{ $signed($data['profit_amount']) }}</strong></td>
                                <td class="rpt-num">{{ $pct($data['profit_percentage']) }}</td>
                                <td class="rpt-num">{{ $pct($data['profit_to_sales_ratio']) }}</td>
                                <td class="rpt-num">{{ $pct($data['profit_to_cost_ratio']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="15" class="rpt-empty">
                                <i class="fas fa-inbox"></i>
                                {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if (count($reportData) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="3">{{ __('sales_reports.common.grand_total') }}</td>
                                <td class="rpt-num">{{ $totals['sales_count'] }}</td>
                                <td class="rpt-num">{{ $qty($totals['sales_qty']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['sales_amount']) }}</td>
                                <td class="rpt-num">{{ $qty($totals['returns_qty']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['returns_amount']) }}</td>
                                <td class="rpt-num">{{ $qty($totals['net_sales_qty']) }}</td>
                                <td class="rpt-num {{ $signClass($totals['net_sales_amount']) }}">{{ $signed($totals['net_sales_amount']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($totals['cost_of_sales']) }}</td>
                                <td class="rpt-num {{ $signClass($totals['profit_amount']) }}">{{ $signed($totals['profit_amount']) }}</td>
                                <td class="rpt-num">{{ $pct($totals['profit_percentage']) }}</td>
                                <td class="rpt-num">{{ $pct($totals['profit_to_sales_ratio']) }}</td>
                                <td class="rpt-num">{{ $pct($totals['profit_to_cost_ratio']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'عميل',
            'pagerStorage' => 'agSalesProfitsPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    @include('sales::sals_report.report_partials.scripts', ['cascadeBranches' => $cascadeBranches ?? []])

    <script>
        (function () {
            const PROFIT_CHART = @json($profitChart);
            const SPLIT_DONUT = @json($splitDonut);

            $(function () {
                const bar = document.getElementById('profitChart');
                if (bar && typeof Chart !== 'undefined' && PROFIT_CHART.labels.length) {
                    const theme = agChartTheme();
                    new Chart(bar.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: PROFIT_CHART.labels,
                            datasets: [{
                                data: PROFIT_CHART.values,
                                backgroundColor: PROFIT_CHART.values.map(function (v) { return v < 0 ? '#ef4444' : '#10b981'; }),
                                borderRadius: 6, maxBarThickness: 48
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agMoney(c.parsed.y); } } }
                            },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 0, autoSkip: true,
                                     callback: function (v) { const l = String(this.getLabelForValue(v)); return l.length > 14 ? l.slice(0, 13) + '…' : l; } } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        },
                        plugins: [agValueLabels(PROFIT_CHART.values)]
                    });
                }

                const donut = document.getElementById('splitDonut');
                if (donut && typeof Chart !== 'undefined' && SPLIT_DONUT.length) {
                    const theme = agChartTheme();
                    new Chart(donut, {
                        type: 'doughnut',
                        data: {
                            labels: SPLIT_DONUT.map(function (s) { return s.label; }),
                            datasets: [{
                                data: SPLIT_DONUT.map(function (s) { return s.value; }),
                                backgroundColor: SPLIT_DONUT.map(function (s) { return s.color; }),
                                borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) {
                                    const s = SPLIT_DONUT[c.dataIndex];
                                    return ' ' + agMoney(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)');
                                } } }
                            }
                        }
                    });
                }
            });
        })();
    </script>

    @php
        // ما يُصدَّر هو ما يُرى على الشاشة حرفياً — يُبنى في الخادم لا من الـ DOM
        if ($isSummary) {
            $excelHeaders = [
                __('sales_reports.columns.row'),
                __('sales_reports.columns.client'),
                __('sales_reports.columns.status'),
                __('sales_reports.columns.stage'),
                __('sales_reports.columns.operations_count'),
                __('sales_reports.profits.sales_amount'),
                __('sales_reports.profits.returns'),
                __('sales_reports.profits.net_sales_amount'),
                __('sales_reports.profits.cost'),
                __('sales_reports.profits.profit'),
                __('sales_reports.profits.profit_ratio'),
            ];
            $excelWidths = [6, 30, 14, 14, 14, 18, 16, 18, 16, 16, 14];
            $excelRows = $reportData->values()->map(fn ($r, $i) => [
                'values' => [
                    $i + 1,
                    $r['name'] ?: __('sales_reports.common.none'),
                    $r['status']->name ?? '—',
                    $r['registration_stage']->name ?? '—',
                    $r['sales_count'],
                    $r['sales_amount'],
                    $r['returns_amount'],
                    $r['net_sales_amount'],
                    $r['cost_of_sales'],
                    $r['profit_amount'],
                    $r['profit_to_sales_ratio'].'%',
                ],
                'alignments' => [null, 'right'],
            ])->all();
            $excelSummary = count($reportData) ? [[
                'values' => [
                    '', __('sales_reports.common.grand_total'), '', '', $totals['sales_count'], $totals['sales_amount'],
                    $totals['returns_amount'], $totals['net_sales_amount'], $totals['cost_of_sales'],
                    $totals['profit_amount'], $totals['profit_to_sales_ratio'].'%',
                ],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = [
                __('sales_reports.columns.client'),
                __('sales_reports.columns.status'),
                __('sales_reports.columns.stage'),
                __('sales_reports.columns.operations_count'),
                __('sales_reports.profits.sales_amount').' — '.__('sales_reports.columns.quantity'),
                __('sales_reports.profits.sales_amount').' — '.__('sales_reports.columns.value'),
                __('sales_reports.profits.returns').' — '.__('sales_reports.columns.quantity'),
                __('sales_reports.profits.returns').' — '.__('sales_reports.columns.value'),
                __('sales_reports.profits.net_sales_amount').' — '.__('sales_reports.columns.quantity'),
                __('sales_reports.profits.net_sales_amount').' — '.__('sales_reports.columns.value'),
                __('sales_reports.profits.total_cost'),
                __('sales_reports.profits.profit'),
                __('sales_reports.profits.profit_percentage'),
                __('sales_reports.profits.to_sales'),
                __('sales_reports.profits.to_cost'),
            ];
            $excelWidths = [28, 14, 14, 12, 12, 16, 12, 16, 12, 16, 16, 16, 12, 12, 12];
            $excelRows = $reportData->values()->map(fn ($r) => [
                'values' => [
                    $r['name'] ?: __('sales_reports.common.none'),
                    $r['status']->name ?? '—',
                    $r['registration_stage']->name ?? '—',
                    $r['sales_count'],
                    $r['sales_qty'],
                    $r['sales_amount'],
                    $r['returns_qty'],
                    $r['returns_amount'],
                    $r['net_sales_qty'],
                    $r['net_sales_amount'],
                    $r['cost_of_sales'],
                    $r['profit_amount'],
                    $r['profit_percentage'].'%',
                    $r['profit_to_sales_ratio'].'%',
                    $r['profit_to_cost_ratio'].'%',
                ],
                'alignments' => ['right'],
            ])->all();
            $excelSummary = count($reportData) ? [[
                'values' => [
                    __('sales_reports.common.grand_total'), '', '', $totals['sales_count'],
                    $totals['sales_qty'], $totals['sales_amount'],
                    $totals['returns_qty'], $totals['returns_amount'],
                    $totals['net_sales_qty'], $totals['net_sales_amount'],
                    $totals['cost_of_sales'], $totals['profit_amount'],
                    $totals['profit_percentage'].'%', $totals['profit_to_sales_ratio'].'%',
                    $totals['profit_to_cost_ratio'].'%',
                ],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => __('sales_reports.profits.export_name').($isSummary ? '_'.__('sales_reports.common.summary_view') : '_'.__('sales_reports.common.details')),
        'mainTitle' => __('sales_reports.profits.title').($isSummary ? ' — '.__('sales_reports.common.summary_view') : ' — '.__('sales_reports.common.details_view')),
        'info' => [
            __('sales_reports.common.period') => $periodLabel,
            __('sales_reports.filters.client') => $selectedClient ?: __('sales_reports.common.all'),
            __('sales_reports.filters.employee') => $selectedEmployee ?: __('sales_reports.common.all'),
            __('sales_reports.filters.branch') => $selectedBranchName ?: __('sales_reports.common.all'),
            __('sales_reports.filters.client_category') => $selectedCategory ?: __('sales_reports.common.all'),
            __('sales_reports.filters.client_status') => $selectedStatus ?: __('sales_reports.common.all'),
            __('sales_reports.filters.client_stage') => $selectedStage ?: __('sales_reports.common.all'),
        ],
        'cards' => [
            ['title' => __('sales_reports.profits.sales_amount'), 'value' => $money($totals['sales_amount'] ?? 0), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => __('sales_reports.profits.returns_amount'), 'value' => $money($totals['returns_amount'] ?? 0), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => __('sales_reports.profits.net_sales_amount'), 'value' => $money($totals['net_sales_amount'] ?? 0), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => __('sales_reports.profits.cost_of_sales'), 'value' => $money($totals['cost_of_sales'] ?? 0), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningBg', 'borderColor' => 'warningLight'],
            ['title' => __('sales_reports.profits.profit_amount'), 'value' => $money($totals['profit_amount'] ?? 0), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => __('sales_reports.profits.profit_to_sales_ratio'), 'value' => $pct($totals['profit_to_sales_ratio'] ?? 0), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
        ],
        'headers' => $excelHeaders,
        'widths' => $excelWidths,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
