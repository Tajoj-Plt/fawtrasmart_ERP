{{--
    تقرير المبيعات حسب الفترة الزمنية — على تصميم تقارير العملاء المعتمد (`ag-*`):
    ترويسةٌ فاتحة، أزرارُ نوع الفترة (يومي/أسبوعي/شهري/سنوي) فوق شبكة فلترةٍ
    واحدة، مؤشّرات، رسمان (الفترات + تركيبُ إجمالي المبيعات)، ثمّ جدولٌ واحدٌ
    بترقيم صفحات.

    ══════════ الأساس ══════════

    المبالغُ على أساس «المبيعات حسب العميل» (حسابا المبيعات ومردوداتها) — قاعدةُ
    `SalesLedger` التي تقرأ بها لوحةُ المبيعات (انظر تعليق
    `SalesReportsController::salesByPeriod`):

        صافي المبيعات = إجمالي فواتير المبيعات − المرتجعات − الإشعارات الدائنة

    كانت الشاشةُ تجمع كلَّ مدينٍ في القيد وتُضيف المرتجعَ والإشعارَ إلى
    «إجمالي المبيعات»، ويعرض الرسمُ الدائريُّ المرتجعَ حصّةً من الإجمالي.
    صار الرسمُ تركيبَ الإجمالي: صافٍ + مرتجعات + إشعارات = الإجمالي.

    التفاصيلُ صفُّ عنوانٍ لكلّ فترة ثمّ مستنداتُها ثمّ إجماليُّها — والمبلغُ
    بإشارته (المرتجعُ والإشعارُ سالبان)، فمجموعُ الصفوف = صافي الفترة، ومجموعُ
    «الرصيد» = الصافي − المحصَّل. الترقيمُ بالفترة فلا تنقسم بين صفحتين.
--}}
@extends('sales::master')

@section('title')
    تقرير المبيعات حسب الفترة الزمنية
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-table tbody tr.ag-grand td { font-weight: 800; border-top: 2px solid var(--rpt-line); }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-tab i { margin-inline-end: 6px; font-size: .8rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .ag-table td small.d-block { color: var(--rpt-muted); font-size: .72rem; margin-top: 2px; }
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--rpt-brand); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;

    $money = fn ($v) => CurrencySettings::number($v);
    $currency = CurrencySettings::name();

    /* رابطُ العميل بلا include لكلّ صف: كلُّ include يُشغّل مُركِّبات العرض
       العامّة (استعلامٌ لكلّ صفّ)، وآلافُ المستندات في «التفاصيل» تصير ثوانيَ. */
    $clientLink = function ($id, $name, $fallback = '-') {
        $label = e(trim((string) $name) !== '' ? $name : $fallback);

        return $id
            ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">'.$label.'</a>'
            : $label;
    };

    $periodLabel = __('sales_reports.common.period_range', ['from' => $fromDate->format('d/m/Y'), 'to' => $toDate->format('d/m/Y')]);
    $periodTypeLabel = $periodTypes[$periodType] ?? $periodType;

    /* أزرارُ نوع الفترة روابطُ: تحمل كلَّ فلترٍ آخر وتُسقط التاريخين، فيحسب
       الخادمُ المدى الافتراضيَّ لنوع الفترة الجديد. */
    $periodIcons = ['daily' => 'calendar-day', 'weekly' => 'calendar-week', 'monthly' => 'calendar-alt', 'yearly' => 'calendar'];
    $periodCarry = collect(request()->query())->except(['period_type', 'date_from', 'date_to', 'page'])->all();
    $periodTabs = [];
    foreach ($periodTypes as $ptKey => $ptLabel) {
        $periodTabs[$ptKey] = [
            'label' => $ptLabel,
            'icon' => $periodIcons[$ptKey] ?? 'calendar',
            'url' => route('salesReports.salesByPeriod', array_merge($periodCarry, ['period_type' => $ptKey])),
        ];
    }

    $selectedBranch = $effectiveBranchId ? optional(\App\Models\Branch::findCached($effectiveBranchId))->name : null;
    $selectedEmployee = request('employee_id') ? optional($employees->firstWhere('id', (int) request('employee_id')))->name : null;
    $selectedClientModel = request('client_id') ? $clients->firstWhere('id', (int) request('client_id')) : null;
    $selectedClient = $selectedClientModel ? ($selectedClientModel->trade_name ?: $selectedClientModel->first_name) : null;
    $selectedCategory = request('category_id') ? optional($categories->firstWhere('id', (int) request('category_id')))->name : null;

    $typeNames = ['SI' => 'فاتورة مبيعات', 'SR' => 'مرتجع مبيعات', 'CN' => 'إشعار دائن'];
    $typePill = ['SI' => 'success', 'SR' => 'danger', 'CN' => 'warning'];

    $tGross = (float) $totals['gross'];
    $tNet = (float) $totals['net'];
    $tDeductions = (float) $totals['returns'] + (float) $totals['credit_notes'];
    $hasOverpaid = $totals['overpaid'] > 0.005;

    /* ─────────── تركيبُ إجمالي المبيعات: صافٍ + مرتجعات + إشعارات ─────────── */
    $pctOfGross = fn ($v) => $tGross > 0 ? round($v / $tGross * 100) : null;
    $grossLegend = [
        ['label' => 'صافي المبيعات', 'value' => round($tNet, 2), 'color' => '#10b981', 'pct' => $pctOfGross($tNet)],
        ['label' => 'المرتجعات', 'value' => round((float) $totals['returns'], 2), 'color' => '#ef4444', 'pct' => $pctOfGross($totals['returns'])],
        ['label' => 'الإشعارات الدائنة', 'value' => round((float) $totals['credit_notes'], 2), 'color' => '#eab308', 'pct' => $pctOfGross($totals['credit_notes'])],
    ];
    $grossDonut = array_values(array_filter($grossLegend, fn ($s) => $s['value'] > 0.005));

    $collectPct = $tNet > 0 ? round($totals['paid'] / $tNet * 100) : null;
    $hasData = count($reportData) > 0;
    $chartTitle = 'صافي المبيعات والتحصيل ('.$periodTypeLabel.')';
    $summaryCols = $hasOverpaid ? 10 : 9;
@endphp

<div class="rpt">

    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => 'تقرير المبيعات حسب الفترة الزمنية — '.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view')),
        'periodLabel' => $periodLabel,
        'filters' => [
            'نوع الفترة' => $periodTypeLabel,
            'تابع لـ' => $selectedBranch,
            'الموظف' => $selectedEmployee,
            'العميل' => $selectedClient,
            'تصنيف العميل' => $selectedCategory,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'calendar-alt',
            'headTitle' => 'المبيعات حسب الفترة الزمنية',
            'headSubtitle' => 'فواتيرُ المبيعات ناقصَ المرتجعات والإشعارات الدائنة على الفترات، وما حُصِّل منها وما بقي.',
            'headPeriod' => $periodLabel,
            'headExtra' => 'نوع الفترة: '.$periodTypeLabel,
            'headGuideKey' => 'sales-reports.sales-by-period',
            'headCrumbLabel' => __('sales_reports.index.title'),
            'headCrumbUrl' => route('salesReports.index'),
        ])
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('salesReports.salesByPeriod') }}" id="filterForm">
                <input type="hidden" name="period_type" id="periodTypeInput" value="{{ $periodType }}">

                <div class="ag-tabs" role="group" aria-label="نوع الفترة">
                    @foreach ($periodTabs as $ptKey => $tab)
                        <a href="{{ $tab['url'] }}" class="ag-tab {{ $periodType == $ptKey ? 'is-active' : '' }}">
                            <i class="fas fa-{{ $tab['icon'] }}"></i>{{ $tab['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="{{ __('sales_reports.filters.from_date') }}"
                                   value="{{ $fromDate->format('Y-m-d') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="{{ __('sales_reports.filters.to_date') }}"
                                   value="{{ $toDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">{{ __('sales_reports.filters.employee') }}</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="client_id">{{ __('sales_reports.filters.client') }}</label>
                        <select name="client_id" id="client_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>{{ $client->trade_name ?: $client->first_name }}</option>
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

                    {{-- «تابع لـ» — عقدةُ العميل في الشجرة، شجرةٌ تتفرّع حقولُها فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $effectiveBranchId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="btn-group ag-view-toggle" role="group" aria-label="نوع العرض">
                        <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-list"></i> {{ __('sales_reports.common.summary') }}
                        </button>
                        <button type="submit" name="view_type" value="details" class="btn {{ ! $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-th-list"></i> {{ __('sales_reports.common.details') }}
                        </button>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('salesReports.salesByPeriod') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> {{ __('sales_reports.common.reset') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">عدد الفواتير</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['count'] }}</bdi></div>
                <div class="ag-kpi__sub">{{ count($reportData) }} فترة · {{ $periodTypeLabel }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">إجمالي المبيعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tGross) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المرتجعات والإشعارات الدائنة</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tDeductions) }}</bdi></div>
                <div class="ag-kpi__sub">مرتجعات {{ $money($totals['returns']) }} ({{ $totals['returns_count'] }}) · إشعارات {{ $money($totals['credit_notes']) }} ({{ $totals['credit_notes_count'] }})</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo-alt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#0ea5e9">
            <div>
                <div class="ag-kpi__label">صافي المبيعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tNet) }}</bdi></div>
                <div class="ag-kpi__sub">الإجمالي − المرتجعات − الإشعارات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي المحصل</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['paid']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $collectPct === null ? $currency : $collectPct.'% من الصافي' }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">إجمالي المتبقي</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['unpaid']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $hasOverpaid ? 'زيادة لصالح العميل '.$money($totals['overpaid']) : $currency }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-circle"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> {{ $chartTitle }}</h4>
            </div>
            <div class="rpt-card__body">
                @if ($hasData)
                    <div class="ag-canvas"><canvas id="salesChart" aria-label="{{ $chartTitle }}" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> تركيب إجمالي المبيعات</h4>
            </div>
            <div class="rpt-card__body">
                @if ($tGross > 0.005)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($grossLegend as $slice)
                                    @continue($slice['value'] == 0 && $slice['label'] !== 'صافي المبيعات')
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($grossDonut))
                            <div class="ag-donut">
                                <canvas id="typeDonut" aria-label="تركيب إجمالي المبيعات" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $money($tGross) }}</strong>
                                    <small>إجمالي المبيعات · {{ $currency }}</small>
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
                بيانات التقرير — {{ $isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view') }}
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} فترة</span>
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
                            <th>الفترة</th>
                            <th>عدد الفواتير</th>
                            <th>إجمالي المبيعات</th>
                            <th>المرتجعات</th>
                            <th>الإشعارات الدائنة</th>
                            <th>صافي المبيعات</th>
                            <th>المبلغ المحصل</th>
                            <th>المبلغ المتبقي</th>
                            @if ($hasOverpaid)
                                <th>زيادة لصالح العميل</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $periodKey => $period)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name"><strong>{{ $period['name'] }}</strong></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $period['count'] }}</span></td>
                                <td class="rpt-num">{{ $money($period['gross']) }}</td>
                                <td class="rpt-num {{ $period['returns'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $money($period['returns']) }}</td>
                                <td class="rpt-num {{ $period['credit_notes'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $money($period['credit_notes']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($period['net']) }}</strong></td>
                                <td class="rpt-num {{ $period['paid'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ $money($period['paid']) }}</td>
                                <td class="rpt-num {{ $period['unpaid'] > 0 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $money($period['unpaid']) }}</td>
                                @if ($hasOverpaid)
                                    <td class="rpt-num {{ $period['overpaid'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ $money($period['overpaid']) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $summaryCols }}" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد بيانات للفترة المحددة
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($hasData)
                        <tfoot>
                            <tr>
                                <td colspan="2">{{ __('sales_reports.common.grand_total') }}</td>
                                <td>{{ $totals['count'] }}</td>
                                <td class="rpt-num">{{ $money($totals['gross']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['returns']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['credit_notes']) }}</td>
                                <td class="rpt-num">{{ $money($totals['net']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['paid']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($totals['unpaid']) }}</td>
                                @if ($hasOverpaid)
                                    <td class="rpt-num rpt-pos">{{ $money($totals['overpaid']) }}</td>
                                @endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">{{ __('sales_reports.columns.row') }}</th>
                            <th>التاريخ / المرجع</th>
                            <th>العميل / الموظف</th>
                            <th>النوع / تابع لـ</th>
                            <th>المبلغ</th>
                            <th>المحصل</th>
                            <th>الرصيد</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $periodKey => $period)
                            @php
                                $rowKey = 'p'.$loop->index;
                                $periodBalance = $period['net'] - $period['paid'];
                            @endphp
                            <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                                <td colspan="7" class="rpt-name">
                                    <i class="fas fa-{{ $periodIcons[$periodType] ?? 'calendar' }}"></i>
                                    <strong>{{ $period['name'] }}</strong>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $period['count'] }} فاتورة</span>
                                    @if ($period['returns_count'])
                                        <span class="rpt-pill rpt-pill-danger">{{ $period['returns_count'] }} مرتجع</span>
                                    @endif
                                    @if ($period['credit_notes_count'])
                                        <span class="rpt-pill rpt-pill-warning">{{ $period['credit_notes_count'] }} إشعار دائن</span>
                                    @endif
                                </td>
                            </tr>
                            @foreach ($period['items'] as $i => $row)
                                <tr data-parent-row="{{ $rowKey }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td class="rpt-num">
                                        {{ $row['date'] }}
                                        <small class="d-block">{{ $row['reference'] }}</small>
                                    </td>
                                    <td class="rpt-name">
                                        {!! $clientLink($row['client_id'], $row['client_name'], 'غير محدد') !!}
                                        <small class="d-block">{{ $row['employee'] }}</small>
                                    </td>
                                    <td>
                                        <span class="rpt-pill rpt-pill-{{ $typePill[$row['type_code']] ?? 'secondary' }}">{{ $typeNames[$row['type_code']] ?? $row['type_code'] }}</span>
                                        <small class="d-block">{{ $row['node'] }}</small>
                                    </td>
                                    <td class="rpt-num {{ $row['amount'] < 0 ? 'rpt-neg' : '' }}"><strong>{{ $money($row['amount']) }}</strong></td>
                                    <td class="rpt-num {{ $row['paid'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ $money($row['paid']) }}</td>
                                    <td class="rpt-num {{ $row['balance'] > 0.005 ? 'rpt-warnc' : ($row['balance'] < -0.005 ? 'rpt-neg' : 'rpt-zero') }}">{{ $money($row['balance']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                                <td colspan="4">
                                    صافي {{ $period['name'] }}
                                    <small class="d-block">إجمالي {{ $money($period['gross']) }} − مرتجعات {{ $money($period['returns']) }} − إشعارات {{ $money($period['credit_notes']) }}</small>
                                </td>
                                <td class="rpt-num">{{ $money($period['net']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($period['paid']) }}</td>
                                <td class="rpt-num rpt-warnc">
                                    {{ $money($periodBalance) }}
                                    @if ($period['overpaid'] > 0.005)
                                        <small class="d-block">زيادة لصالح العميل {{ $money($period['overpaid']) }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد بيانات للفترة المحددة
                            </td></tr>
                        @endforelse

                        @if ($hasData)
                            <tr class="ag-grand">
                                <td colspan="4">
                                    {{ __('sales_reports.common.grand_total') }} — {{ $totals['count'] }} فاتورة
                                    <small class="d-block">إجمالي {{ $money($totals['gross']) }} − مرتجعات {{ $money($totals['returns']) }} − إشعارات {{ $money($totals['credit_notes']) }}</small>
                                </td>
                                <td class="rpt-num">{{ $money($totals['net']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['paid']) }}</td>
                                <td class="rpt-num rpt-warnc">
                                    {{ $money($totals['net'] - $totals['paid']) }}
                                    @if ($hasOverpaid)
                                        <small class="d-block">متبقٍّ {{ $money($totals['unpaid']) }} · زيادة لصالح العميل {{ $money($totals['overpaid']) }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'فترة',
            'pagerStorage' => 'agSalesByPeriodPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    @include('sales::sals_report.report_partials.scripts', ['cascadeBranches' => []])

    <script>
        (function () {
            const PERIOD_CHART = @json($chartData);
            const GROSS_DONUT = @json($grossDonut);

            $(function () {
                const bar = document.getElementById('salesChart');
                if (bar && typeof Chart !== 'undefined' && PERIOD_CHART.labels.length) {
                    const theme = agChartTheme();
                    new Chart(bar.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: PERIOD_CHART.labels,
                            datasets: [
                                { label: 'صافي المبيعات', data: PERIOD_CHART.net, backgroundColor: '#8b5cf6', borderRadius: 5, maxBarThickness: 34 },
                                { label: 'المبلغ المحصل', data: PERIOD_CHART.paid, backgroundColor: '#10b981', borderRadius: 5, maxBarThickness: 34 },
                                { label: 'المبلغ المتبقي', data: PERIOD_CHART.unpaid, backgroundColor: '#f97316', borderRadius: 5, maxBarThickness: 34 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', rtl: true, labels: { color: theme.muted, boxWidth: 12, usePointStyle: true } },
                                tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + agMoney(c.parsed.y); } } }
                            },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, autoSkip: true,
                                     callback: function (v) { const l = String(this.getLabelForValue(v)); return l.length > 18 ? l.slice(0, 17) + '…' : l; } } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        }
                    });
                }

                const donut = document.getElementById('typeDonut');
                if (donut && typeof Chart !== 'undefined' && GROSS_DONUT.length) {
                    const theme = agChartTheme();
                    new Chart(donut, {
                        type: 'doughnut',
                        data: {
                            labels: GROSS_DONUT.map(function (s) { return s.label; }),
                            datasets: [{
                                data: GROSS_DONUT.map(function (s) { return s.value; }),
                                backgroundColor: GROSS_DONUT.map(function (s) { return s.color; }),
                                borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) {
                                    const s = GROSS_DONUT[c.dataIndex];
                                    return ' ' + s.label + ': ' + agMoney(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)');
                                } } }
                            }
                        }
                    });
                }
            });
        })();
    </script>

    @php
        // ما يُصدَّر هو ما يُرى على الشاشة — يُبنى في الخادم لا من الـ DOM
        $r2 = fn ($v) => round((float) $v, 2);

        if ($isSummary) {
            $excelHeaders = ['#', 'الفترة', 'عدد الفواتير', 'إجمالي المبيعات', 'المرتجعات', 'الإشعارات الدائنة', 'صافي المبيعات', 'المبلغ المحصل', 'المبلغ المتبقي'];
            $excelWidths = [6, 34, 12, 18, 16, 16, 18, 18, 18];
            if ($hasOverpaid) {
                $excelHeaders[] = 'زيادة لصالح العميل';
                $excelWidths[] = 16;
            }
            $excelRows = [];
            $n = 0;
            foreach ($reportData as $period) {
                $values = [++$n, $period['name'], $period['count'], $r2($period['gross']), $r2($period['returns']), $r2($period['credit_notes']), $r2($period['net']), $r2($period['paid']), $r2($period['unpaid'])];
                if ($hasOverpaid) {
                    $values[] = $r2($period['overpaid']);
                }
                $excelRows[] = ['values' => $values, 'alignments' => [null, 'right']];
            }
            $grand = ['', 'الإجمالي العام', $totals['count'], $r2($totals['gross']), $r2($totals['returns']), $r2($totals['credit_notes']), $r2($totals['net']), $r2($totals['paid']), $r2($totals['unpaid'])];
            if ($hasOverpaid) {
                $grand[] = $r2($totals['overpaid']);
            }
            $excelSummary = $hasData ? [[
                'values' => $grand,
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = ['#', 'الفترة', 'التاريخ', 'المرجع', 'العميل', 'الموظف', 'النوع', 'تابع لـ', 'المبلغ', 'المحصل', 'الرصيد'];
            $excelWidths = [6, 26, 12, 16, 26, 18, 16, 16, 16, 16, 16];
            $excelRows = [];
            $n = 0;
            foreach ($reportData as $period) {
                foreach ($period['items'] as $row) {
                    $excelRows[] = [
                        'values' => [
                            ++$n,
                            $period['name'],
                            $row['date'],
                            (string) $row['reference'],
                            $row['client_name'] !== '' ? $row['client_name'] : 'غير محدد',
                            $row['employee'],
                            $typeNames[$row['type_code']] ?? $row['type_code'],
                            $row['node'],
                            $r2($row['amount']),
                            $r2($row['paid']),
                            $r2($row['balance']),
                        ],
                        'alignments' => [null, 'right'],
                    ];
                }
            }
            $excelSummary = $excelRows ? [[
                'values' => ['', 'صافي المبيعات', '', '', '', '', '', '', $r2($totals['net']), $r2($totals['paid']), $r2($totals['net'] - $totals['paid'])],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }

        $excelFileName = 'المبيعات_حسب_الفترة_'.$periodTypeLabel.'_'.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details'));
        $excelTitle = 'تقرير المبيعات حسب الفترة الزمنية — '.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view'));
        $excelInfo = [
            'الفترة' => $periodLabel,
            'نوع الفترة' => $periodTypeLabel,
            'تابع لـ' => $selectedBranch ?: 'الكل',
            'الموظف' => $selectedEmployee ?: 'الكل',
            'العميل' => $selectedClient ?: 'الكل',
            'تصنيف العميل' => $selectedCategory ?: 'الكل',
        ];
        $excelCards = [
            ['title' => 'عدد الفواتير', 'value' => $totals['count'], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المبيعات', 'value' => $money($totals['gross']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'المرتجعات والإشعارات', 'value' => $money($tDeductions), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningBg', 'borderColor' => 'warningLight'],
            ['title' => 'صافي المبيعات', 'value' => $money($totals['net']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المحصل', 'value' => $money($totals['paid']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'إجمالي المتبقي', 'value' => $money($totals['unpaid']), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningBg', 'borderColor' => 'warningLight'],
        ];
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => $excelFileName,
        'mainTitle' => $excelTitle,
        'info' => $excelInfo,
        'cards' => $excelCards,
        'headers' => $excelHeaders,
        'widths' => $excelWidths,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
