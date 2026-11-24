{{--
    «تقرير التبديلات» ({@see \Modules\Sales\Http\Controllers\Report\ExchangesReportController}).

    تفاصيل: سطرٌ لكلّ بند تبديل — الصنفُ والكميةُ والسببُ ومستودعُ الصرف
    ومستودعُ الاستلام. ملخص: سطرٌ لكلّ مجموعة بحسب «التجميع»، من السطور
    نفسِها، فمجموعُ الملخّص يساوي مجموعَ التفاصيل.

    بتصميم «مبيعات البنود» نفسِه: ترويسةُ `report-head`، وشبكةُ الفلترة
    `ag-filter-grid`، وبطاقاتُ `ag-kpi`، والرسمان، وجدولُ `rpt-table` بترقيمٍ
    في المتصفّح والتصديرُ والطباعةُ للجدول كلِّه.
--}}
@extends('sales::master')

@section('title')
    {{ $pageTitle }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .ag-table td small.d-block { color: var(--rpt-muted); font-size: .7rem; }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-kpi__value small { font-size: .72rem; font-weight: 600; color: var(--rpt-muted); }
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--rpt-brand); border-bottom-style: solid; }
        .ex-reason { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; white-space: nowrap; }
        .ex-emp { min-width: 120px; }
        .ex-reason::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--c, var(--rpt-muted)); flex: 0 0 auto; }
        .ex-code { white-space: nowrap; }
        .ex-stores { min-width: 150px; font-size: .78rem; }
        .ex-stores i { color: var(--rpt-muted); margin: 0 4px; font-size: .7rem; }
        .ex-share { display: flex; align-items: center; gap: 8px; min-width: 120px; }
        .ex-share__bar { flex: 1; height: 6px; border-radius: 999px; background: var(--rpt-sunken); overflow: hidden; }
        .ex-share__bar span { display: block; height: 100%; background: var(--rpt-brand); border-radius: inherit; }
        .ex-share__pct { font-size: .75rem; font-weight: 700; color: var(--rpt-muted); min-width: 42px; text-align: left; }
        @media print { .rpt-client-link { border-bottom: 0; } .ex-share__bar { display: none; } }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;

    $money = fn ($v) => CurrencySettings::number($v);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $canOpenExchange = hasPermission('sales.exchanges.view') && Route::has('product-exchanges.show');
    $clientLink = function ($id, $name) {
        $label = e(trim((string) $name) !== '' ? $name : '-');

        return $id
            ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link">'.$label.'</a>'
            : $label;
    };
    $docLink = function ($id, $code) use ($canOpenExchange) {
        return $canOpenExchange
            ? '<a href="'.e(route('product-exchanges.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link">'.e($code).'</a>'
            : e($code);
    };

    $periodLabel = __('sales_reports.common.period_range', ['from' => $fromDate->format('d/m/Y'), 'to' => $toDate->format('d/m/Y')]);
    $groupLabel = $groupOptions[$groupBy] ?? '';
    $reasonColors = collect($reasonMix)->pluck('color', 'label');

    /* الرسم: قيمة التبديلات يوماً بيوم — أو شهراً بشهر إن طال المدى */
    $byMonth = $fromDate->diffInDays($toDate) > 62;
    $trend = $lines->groupBy(fn ($l) => $byMonth ? $l['month'] : $l['date'])
        ->map(fn ($g) => ['value' => round($g->sum('value'), 2), 'qty' => round($g->sum('quantity'), 2)])
        ->sortKeys();
    $chartData = ['labels' => $trend->keys()->all(), 'values' => $trend->pluck('value')->all(), 'qty' => $trend->pluck('qty')->all()];
    $chartTitle = 'قيمة التبديلات '.($byMonth ? 'شهرياً' : 'يومياً');

    $appliedFilters = array_filter([
        'الصنف' => request('product_id') ? optional($products->firstWhere('id', (int) request('product_id')))->name : null,
        'السبب' => request('reason_id') ? optional($reasons->firstWhere('id', (int) request('reason_id')))->name : null,
        'العميل' => $selectedClient->trade_name ?? null,
        'التجميع' => $isSummary ? $groupLabel : null,
    ]);
@endphp

<div class="rpt">
    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => $pageTitle,
        'periodLabel' => $periodLabel,
        'filters' => $appliedFilters,
    ])

    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'exchange-alt',
            'headTitle' => $pageTitle,
            'headSubtitle' => 'كل بند تبديل بصنفه وكمّيته وسببه ومستودعَي الصرف والاستلام — وملخّصٌ حسب الصنف أو السبب أو العميل أو الموظف.',
            'headPeriod' => $periodLabel,
            'headExtra' => $isSummary ? 'التجميع: '.$groupLabel : null,
            'headGuideKey' => 'sales-reports.exchanges',
            'headGuide' => [
                'title' => 'كيف يُقرأ تقرير التبديلات',
                'lead' => 'التبديل يُخرج صنفاً سليماً إلى العميل ويستلم منه التالف أو الناقص — ليس بيعاً ولا مرتجعاً، فلا يدخل في أرقام المبيعات.',
                'blocks' => [[
                    'القيمة = الكمية × سعر الوحدة كما كُتبت في مستند التبديل، ومجموع بنود المستند = قيمته.',
                    '«تابع لـ» يُطبَّق على عقدة العميل كبقيّة تقارير المبيعات، والموظف = المسؤول عن التبديل أو منشئه.',
                    '«ملخص» يجمع البنود نفسها حسب ما تختاره في «التجميع»، فمجموعه يساوي مجموع التفاصيل.',
                    'التصدير والطباعة يُخرجان الجدول كلّه لا الصفحة المعروضة وحدها.',
                ]],
            ],
            'headCrumbLabel' => __('sales_reports.index.title'),
            'headCrumbUrl' => route('salesReports.index'),
        ])
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ $formAction }}" id="filterForm">

                @include('sales::sals_report.report_partials.period-tabs')

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
                        <label for="code">رقم المستند</label>
                        <input type="text" name="code" id="code" class="form-control" value="{{ request('code') }}" placeholder="EX-…">
                    </div>

                    <div class="rpt-field">
                        <label for="product_id">الصنف</label>
                        <select name="product_id" id="product_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}{{ $product->barcode ? ' — '.$product->barcode : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="category_id">تصنيف الصنف</label>
                        <select name="category_id" id="category_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="reason_id">سبب التبديل</label>
                        <select name="reason_id" id="reason_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason->id }}" @selected((string) request('reason_id') === (string) $reason->id)>{{ $reason->name }}{{ $reason->is_active ? '' : ' (موقوف)' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="storehouse_id">مستودع الصرف (السليم)</label>
                        <select name="storehouse_id" id="storehouse_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($storehouses as $storehouse)
                                <option value="{{ $storehouse->id }}" @selected((string) request('storehouse_id') === (string) $storehouse->id)>{{ $storehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="return_storehouse_id">مستودع الاستلام (التالف)</label>
                        <select name="return_storehouse_id" id="return_storehouse_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($storehouses as $storehouse)
                                <option value="{{ $storehouse->id }}" @selected((string) request('return_storehouse_id') === (string) $storehouse->id)>{{ $storehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">{{ __('sales_reports.filters.employee') }}</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('employee_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('reports.partials.client-select', [
                            'name' => 'client_id',
                            'id' => 'client_id',
                            'url' => route('salesReports.exchanges.searchClients'),
                            'selectedClient' => $selectedClient ?? null,
                            'label' => __('sales_reports.filters.client'),
                            'placeholder' => __('sales_reports.common.all'),
                        ])
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
                        <label for="group_by">{{ __('sales_reports.filters.group_by') }} <small class="text-muted">(في الملخص)</small></label>
                        <select name="group_by" id="group_by" class="form-control rpt-select2">
                            @foreach ($groupOptions as $optKey => $optLabel)
                                <option value="{{ $optKey }}" @selected($groupBy === $optKey)>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $effectiveBranchId ?? null])
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
                        {{-- يحمل العرضَ الحاليّ — كي لا يعيد «عرض التقرير» الملخّصَ إلى التفاصيل --}}
                        <button type="submit" class="btn btn-primary" name="view_type" value="{{ $isSummary ? 'summary' : 'details' }}"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ $formAction }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> {{ __('sales_reports.common.reset') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">عمليات التبديل</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['documents']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ number_format($totals['lines']) }} بند · {{ number_format($totals['clients']) }} عميل</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-exchange-alt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">الكمية المستبدلة</div>
                <div class="ag-kpi__value"><bdi>{{ $qty($totals['quantity']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ number_format($totals['products']) }} صنف{{ $totals['top_product'] ? ' · الأكثر: '.$totals['top_product'] : '' }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-cubes"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">قيمة التبديلات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['value']) }}</bdi></div>
                <div class="ag-kpi__sub">متوسط العملية {{ $money($totals['avg_value']) }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-coins"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div>
                <div class="ag-kpi__label">السبب الأكثر تكراراً</div>
                <div class="ag-kpi__value" style="font-size:1.05rem">{{ $totals['top_reason'] ?: '—' }}</div>
                <div class="ag-kpi__sub">
                    @if ($totals['top_reason'] && $totals['quantity'] > 0)
                        {{ $qty($totals['top_reason_qty']) }} قطعة · {{ round($totals['top_reason_qty'] / $totals['quantity'] * 100) }}% من الكمية
                    @else
                        لا تبديلات في الفترة
                    @endif
                </div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-triangle"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head"><h4><i class="fas fa-chart-bar"></i> {{ $chartTitle }}</h4></div>
            <div class="rpt-card__body">
                @if (count($chartData['labels']))
                    <div class="ag-canvas"><canvas id="trendChart" aria-label="{{ $chartTitle }}" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا توجد تبديلات في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
        <div class="rpt-card">
            <div class="rpt-card__head"><h4><i class="fas fa-chart-pie"></i> أسباب التبديل (بالكمية)</h4></div>
            <div class="rpt-card__body">
                @if (count($reasonMix))
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($reasonMix as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $qty($slice['quantity']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="ag-donut">
                            <canvas id="reasonDonut" aria-label="أسباب التبديل" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ $qty($totals['quantity']) }}</strong>
                                <small>قطعة مستبدلة</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا توجد تبديلات في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-{{ $isSummary ? 'layer-group' : 'list-ul' }}"></i>
                {{ $isSummary ? 'ملخص التبديلات حسب '.$groupLabel : 'بنود التبديل' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? $groups->count().' مجموعة' : $lines->count().' بند' }}</span>
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
                            <th>العمليات</th>
                            <th>البنود</th>
                            <th>الأصناف</th>
                            <th>العملاء</th>
                            <th>الكمية</th>
                            <th>القيمة</th>
                            <th>متوسط العملية</th>
                            <th>النسبة من القيمة</th>
                            @if ($groupBy !== 'reason')<th>السبب الأكثر</th>@endif
                            <th>آخر تبديل</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($groups as $g)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">
                                    @if ($g['exchange_id'])
                                        <strong>{!! $docLink($g['exchange_id'], $g['label']) !!}</strong>
                                    @elseif ($g['client_id'])
                                        {!! $clientLink($g['client_id'], $g['label']) !!}
                                    @elseif ($groupBy === 'reason')
                                        <span class="ex-reason" style="--c:{{ $reasonColors[$g['label']] ?? '' }}">{{ $g['label'] }}</span>
                                    @else
                                        {{ $g['label'] }}
                                    @endif
                                    @if ($g['sub'])<small class="d-block">{{ $g['sub'] }}</small>@endif
                                </td>
                                <td class="rpt-num">{{ number_format($g['documents']) }}</td>
                                <td class="rpt-num">{{ number_format($g['lines']) }}</td>
                                <td class="rpt-num">{{ number_format($g['products']) }}</td>
                                <td class="rpt-num">{{ number_format($g['clients']) }}</td>
                                <td class="rpt-num"><strong>{{ $qty($g['quantity']) }}</strong></td>
                                <td class="rpt-num"><strong>{{ $money($g['value']) }}</strong></td>
                                <td class="rpt-num">{{ $money($g['avg_value']) }}</td>
                                <td>
                                    <div class="ex-share">
                                        <div class="ex-share__bar"><span style="width:{{ min(100, $g['share']) }}%"></span></div>
                                        <span class="ex-share__pct">{{ $g['share'] }}%</span>
                                    </div>
                                </td>
                                @if ($groupBy !== 'reason')
                                    <td>@if ($g['top_reason'])<span class="ex-reason" style="--c:{{ $reasonColors[$g['top_reason']] ?? '' }}">{{ $g['top_reason'] }}</span>@else — @endif</td>
                                @endif
                                <td class="rpt-num">{{ $g['last_date'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $groupBy !== 'reason' ? 12 : 11 }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد تبديلات في {{ $periodLabel }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($groups->count())
                        <tfoot>
                            <tr>
                                <td colspan="2">{{ __('sales_reports.common.grand_total') }}</td>
                                <td class="rpt-num">{{ number_format($totals['documents']) }}</td>
                                <td class="rpt-num">{{ number_format($totals['lines']) }}</td>
                                <td class="rpt-num">{{ number_format($totals['products']) }}</td>
                                <td class="rpt-num">{{ number_format($totals['clients']) }}</td>
                                <td class="rpt-num">{{ $qty($totals['quantity']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($totals['value']) }}</strong></td>
                                <td class="rpt-num">{{ $money($totals['avg_value']) }}</td>
                                <td colspan="{{ $groupBy !== 'reason' ? 3 : 2 }}"></td>
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
                            <th>التصنيف</th>
                            <th>السبب</th>
                            <th>مستودع الصرف ← الاستلام</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>القيمة</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($lines as $l)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-num">{{ $l['date'] }}</td>
                                <td class="ex-code"><strong>{!! $docLink($l['exchange_id'], $l['code']) !!}</strong></td>
                                <td class="rpt-name">
                                    {!! $clientLink($l['client_id'], $l['client_name']) !!}
                                    @if ($l['client_code'])<small class="d-block">{{ $l['client_code'] }}{{ $l['client_branch_name'] ? ' · '.$l['client_branch_name'] : '' }}</small>@endif
                                </td>
                                <td class="ex-emp">
                                    {{ $l['employee_name'] }}
                                    @if ($l['created_by_name'] !== '-' && $l['created_by_name'] !== $l['employee_name'])<small class="d-block">أنشأه: {{ $l['created_by_name'] }}</small>@endif
                                </td>
                                <td class="rpt-name">
                                    {{ $l['product_name'] }}
                                    @if ($l['product_code'])<small class="d-block">{{ $l['product_code'] }}</small>@endif
                                </td>
                                <td>{{ $l['category_name'] }}</td>
                                <td>
                                    <span class="ex-reason" style="--c:{{ $reasonColors[$l['reason_name']] ?? '' }}">{{ $l['reason_name'] }}</span>
                                    @if ($l['reason_notes'])<small class="d-block">{{ \Illuminate\Support\Str::limit($l['reason_notes'], 60) }}</small>@endif
                                </td>
                                <td class="ex-stores">{{ $l['storehouse_name'] }}<small class="d-block"><i class="fas fa-arrow-left"></i>{{ $l['return_storehouse_name'] }}</small></td>
                                <td class="rpt-num"><strong>{{ $qty($l['quantity']) }}</strong></td>
                                <td class="rpt-num">{{ $money($l['unit_price']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($l['value']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد تبديلات في {{ $periodLabel }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($lines->count())
                        <tfoot>
                            <tr>
                                <td colspan="9">{{ __('sales_reports.common.grand_total') }} — {{ number_format($totals['documents']) }} عملية · {{ number_format($totals['clients']) }} عميل</td>
                                <td class="rpt-num">{{ $qty($totals['quantity']) }}</td>
                                <td></td>
                                <td class="rpt-num"><strong>{{ $money($totals['value']) }}</strong></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $isSummary ? 'مجموعة' : 'بند',
            'pagerStorage' => 'agSalesExchangesPageSize',
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
        $(function () {
            if (typeof Chart === 'undefined') return;
            var trend = @json($chartData);
            var mix = @json($reasonMix);

            var bar = document.getElementById('trendChart');
            if (bar && trend.labels.length) {
                var theme = agChartTheme();
                new Chart(bar.getContext('2d'), {
                    type: 'bar',
                    data: { labels: trend.labels, datasets: [{ data: trend.values, backgroundColor: '#8b5cf6', borderRadius: 6, maxBarThickness: 48 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: {
                            label: function (c) { return ' ' + agMoney(c.parsed.y); },
                            afterLabel: function (c) { return ' الكمية: ' + trend.qty[c.dataIndex]; }
                        } } },
                        scales: {
                            x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 45, autoSkip: true } },
                            y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                 ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                        }
                    },
                    plugins: trend.labels.length <= 12 ? [agValueLabels(trend.values)] : []
                });
            }

            var donut = document.getElementById('reasonDonut');
            if (donut && mix.length) {
                var t = agChartTheme();
                new Chart(donut, {
                    type: 'doughnut',
                    data: {
                        labels: mix.map(function (s) { return s.label; }),
                        datasets: [{ data: mix.map(function (s) { return s.quantity; }), backgroundColor: mix.map(function (s) { return s.color; }), borderColor: t.surface, borderWidth: 3, hoverOffset: 6 }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) {
                            var s = mix[c.dataIndex]; return ' ' + s.quantity + ' قطعة' + (s.pct === null ? '' : ' (' + s.pct + '%) — ') + agMoney(s.value);
                        } } } }
                    }
                });
            }
        });
    </script>

    @php
        if ($isSummary) {
            $excelHeaders = array_values(array_filter(['#', $groupLabel, 'العمليات', 'البنود', 'الأصناف', 'العملاء', 'الكمية', 'القيمة', 'متوسط العملية', 'النسبة %', $groupBy !== 'reason' ? 'السبب الأكثر' : null, 'آخر تبديل']));
            $excelRows = $groups->map(fn ($g, $i) => ['values' => array_values(array_filter([
                $i + 1, $g['label'].($g['sub'] ? ' — '.$g['sub'] : ''), $g['documents'], $g['lines'], $g['products'], $g['clients'],
                round($g['quantity'], 2), round($g['value'], 2), round($g['avg_value'], 2), $g['share'],
                $groupBy !== 'reason' ? ($g['top_reason'] ?? '—') : '__skip__', $g['last_date'],
            ], fn ($v) => $v !== '__skip__')), 'alignments' => [null, 'right']])->all();
            $excelSummary = $excelRows ? [[
                'values' => array_values(array_filter(['', 'الإجمالي العام', $totals['documents'], $totals['lines'], $totals['products'], $totals['clients'],
                    round($totals['quantity'], 2), round($totals['value'], 2), round($totals['avg_value'], 2), '', $groupBy !== 'reason' ? '' : '__skip__', ''], fn ($v) => $v !== '__skip__')),
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = ['#', 'التاريخ', 'المستند', 'العميل', 'كود العميل', 'الموظف', 'منشئ المستند', 'فرع المستند', 'كود الصنف', 'الصنف', 'التصنيف', 'السبب', 'ملاحظة السبب', 'مستودع الصرف', 'مستودع الاستلام', 'الكمية', 'سعر الوحدة', 'القيمة'];
            $excelRows = $lines->map(fn ($l, $i) => ['values' => [
                $i + 1, $l['date'], $l['code'], $l['client_name'], (string) ($l['client_code'] ?? ''), $l['employee_name'], $l['created_by_name'], $l['branch_name'],
                (string) ($l['product_code'] ?? ''), $l['product_name'], $l['category_name'], $l['reason_name'], (string) ($l['reason_notes'] ?? ''),
                $l['storehouse_name'], $l['return_storehouse_name'], round($l['quantity'], 2), round($l['unit_price'], 2), round($l['value'], 2),
            ], 'alignments' => [null, null, null, 'right']])->all();
            $excelSummary = $excelRows ? [[
                'values' => ['', 'الإجمالي العام', $totals['documents'].' عملية', '', '', '', '', '', '', '', '', '', '', '', '', round($totals['quantity'], 2), '', round($totals['value'], 2)],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => 'تقرير_التبديلات'.($isSummary ? '_ملخص_'.$groupBy : '_تفاصيل'),
        'mainTitle' => $pageTitle,
        'info' => ['الفترة' => $periodLabel] + $appliedFilters,
        'cards' => [
            ['title' => 'عمليات التبديل', 'value' => number_format($totals['documents']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'الكمية المستبدلة', 'value' => $qty($totals['quantity']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'قيمة التبديلات', 'value' => $money($totals['value']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
        ],
        'headers' => $excelHeaders,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
