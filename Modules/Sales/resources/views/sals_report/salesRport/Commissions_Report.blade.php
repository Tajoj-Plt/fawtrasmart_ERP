{{--
    «تقرير العمولات» ({@see \Modules\Sales\Http\Controllers\Report\CommissionsReportController}).

    المصروف: سطرٌ لكلّ تسوية عمولة — أهدافُ المبيعات والتحصيلات معاً —
    بإنجازها وقتَ الصرف ونسبتها وسندها وقيدها وخزينتها. الملخّص: بحسب
    الموظف أو الهدف أو النوع أو طريقة الصرف أو الشهر.
    المستحقّ غير المصروف: بطاقةٌ مستقلّة لا تدخل في مجاميع المصروف.

    بتصميم «مبيعات البنود» نفسِه (`report-head` و`ag-kpi` و`rpt-table`).
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
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--rpt-brand); border-bottom-style: solid; }
        .cm-period { white-space: nowrap; font-size: .78rem; }
        .cm-method { white-space: nowrap; }
        .cm-method .cm-links { flex-wrap: nowrap; }
        .cm-links { display: flex; flex-wrap: wrap; gap: 4px 8px; font-size: .72rem; margin-top: 2px; }
        .cm-links a { color: var(--rpt-brand); text-decoration: none; }
        .cm-links a:hover { text-decoration: underline; }
        .cm-pct { display: flex; align-items: center; gap: 6px; min-width: 96px; }
        .cm-pct__bar { flex: 1; height: 6px; border-radius: 999px; background: var(--rpt-sunken); overflow: hidden; }
        .cm-pct__bar span { display: block; height: 100%; border-radius: inherit; background: var(--rpt-ok); }
        .cm-pct__bar span.is-low { background: var(--rpt-warn); }
        .cm-pct__val { font-size: .75rem; font-weight: 700; min-width: 44px; text-align: left; }
        .cm-note { font-size: .8rem; color: var(--rpt-muted); margin: 0; }
        @media print { .rpt-client-link { border-bottom: 0; } .cm-pct__bar, .cm-links { display: none; } }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;

    $money = fn ($v) => CurrencySettings::number($v);
    $pctFmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%';
    $periodLabel = __('sales_reports.common.period_range', ['from' => $fromDate->format('d/m/Y'), 'to' => $toDate->format('d/m/Y')]);
    $groupLabel = $groupOptions[$groupBy] ?? '';
    $periodNames = ['weekly' => 'أسبوعية', 'monthly' => 'شهرية'];

    $can = [
        'voucher' => hasPermission('account.expenses.view') && Route::has('expenses.show'),
        'journal' => hasPermission('account.journal.view') && Route::has('journal.show'),
        'detail' => hasPermission('commissions.show') && Route::has('hr.commissions-report.show'),
        'target' => hasPermission(['sales.targets.manage', 'sales.targets.settle']),
        'employee' => hasPermission('employees.show') && Route::has('employee.show'),
    ];
    $targetUrl = fn ($kind, $id) => $can['target']
        ? route($kind === 'collections' ? 'collections-targets.show' : 'sales-targets.show', $id)
        : null;

    $mix = array_values(array_filter([
        ['label' => 'عمولات المبيعات المصروفة', 'value' => $totals['sales'], 'color' => '#8b5cf6'],
        ['label' => 'عمولات التحصيلات المصروفة', 'value' => $totals['collections'], 'color' => '#10b981'],
        ['label' => 'مستحقة لم تُصرف', 'value' => $totals['due'], 'color' => '#f59e0b'],
        ['label' => 'جارية (دورتها لم تنتهِ)', 'value' => $totals['running'], 'color' => '#94a3b8'],
    ], fn ($s) => $s['value'] > 0.005));
    $mixTotal = array_sum(array_column($mix, 'value'));
    foreach ($mix as $i => $s) {
        $mix[$i]['pct'] = $mixTotal > 0 ? round($s['value'] / $mixTotal * 100) : null;
    }

    $kindNames = ['' => 'المبيعات والتحصيلات', 'sales' => 'أهداف المبيعات', 'collections' => 'أهداف التحصيلات'];
    $appliedFilters = array_filter([
        'نوع الهدف' => $kind !== '' ? $kindNames[$kind] : null,
        'أساس التاريخ' => $dateBasis === 'period' ? 'فترة العمولة' : 'تاريخ الصرف',
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
            'headIcon' => 'percent',
            'headTitle' => $pageTitle,
            'headSubtitle' => 'عمولات أهداف المبيعات والتحصيلات المصروفة — بإنجاز كل موظف ونسبته وسند الصرف وقيده وخزينته — والمستحقّ الذي لم يُصرف بعد.',
            'headPeriod' => $periodLabel,
            'headExtra' => $kindNames[$kind],
            'headGuideKey' => 'sales-reports.commissions',
            'headGuide' => [
                'title' => 'كيف يُقرأ تقرير العمولات',
                'lead' => 'المصروف يُقرأ من تسويات الأهداف نفسها (يدوية أو تلقائية)، فيطابق «العمولات المصروفة» في الموارد البشرية.',
                'blocks' => [[
                    'المحقق والمستهدف ونسبة الإنجاز كما سُجّلت وقت الصرف في قيد العمولة — لا تتغيّر بمرتجعٍ أو تحصيلٍ لاحق.',
                    'نسبة العمولة = العمولة ÷ المحقق، أي نسبة الشريحة التي بلغها الموظف.',
                    '«أساس التاريخ»: تاريخ الصرف (الافتراضي) أو فترة العمولة التي تتقاطع مع المدى المختار.',
                    'المستحقّ غير المصروف يُحسب الآن بقاعدة التسوية نفسها، ولا يدخل في مجموع المصروف.',
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
                        <label for="date_basis">أساس التاريخ</label>
                        <select name="date_basis" id="date_basis" class="form-control rpt-select2">
                            <option value="paid" @selected($dateBasis === 'paid')>تاريخ الصرف</option>
                            <option value="period" @selected($dateBasis === 'period')>فترة العمولة</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="kind">نوع الهدف</label>
                        <select name="kind" id="kind" class="form-control rpt-select2">
                            @foreach ($kindNames as $optKey => $optLabel)
                                <option value="{{ $optKey }}" @selected($kind === $optKey)>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">{{ __('sales_reports.filters.employee') }}</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($employeesList as $emp)
                                <option value="{{ $emp->id }}" @selected((string) request('employee_id') === (string) $emp->id)>{{ trim($emp->full_name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="target">الهدف</label>
                        <select name="target" id="target" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($targetsList->groupBy('kind') as $k => $items)
                                <optgroup label="{{ $kindNames[$k] }}">
                                    @foreach ($items as $t)
                                        <option value="{{ $t['key'] }}" @selected(request('target') === $t['key'])>{{ $t['name'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="commission_period">دورة العمولة</label>
                        <select name="commission_period" id="commission_period" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach ($periodNames as $optKey => $optLabel)
                                <option value="{{ $optKey }}" @selected(request('commission_period') === $optKey)>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="method">طريقة الصرف</label>
                        <select name="method" id="method" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach (['journal', 'direct', 'salary'] as $m)
                                <option value="{{ $m }}" @selected(request('method') === $m)>{{ __('sales_reports.commissions.method.'.$m) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="state">حالة القيد</label>
                        <select name="state" id="state" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            @foreach (['posted', 'pending_approval'] as $st)
                                <option value="{{ $st }}" @selected(request('state') === $st)>{{ __('sales_reports.commissions.state.'.$st) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="with_earned">المحتسبة عن الفترة</label>
                        <select name="with_earned" id="with_earned" class="form-control rpt-select2">
                            <option value="1" @selected(request('with_earned', '1') === '1')>تُعرض</option>
                            <option value="0" @selected(request('with_earned') === '0')>لا تُعرض</option>
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
                        <button type="submit" class="btn btn-primary" name="view_type" value="{{ $isSummary ? 'summary' : 'details' }}"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ $formAction }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> {{ __('sales_reports.common.reset') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">العمولات المصروفة</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['paid']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ number_format($totals['count']) }} تسوية · {{ number_format($totals['employees']) }} موظف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">عمولات أهداف المبيعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['sales']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ number_format($totals['sales_count']) }} تسوية</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">عمولات أهداف التحصيلات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['collections']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ number_format($totals['collections_count']) }} تسوية</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f59e0b">
            <div>
                <div class="ag-kpi__label">مستحقة عن الفترة لم تُصرف</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['due']) }}</bdi></div>
                <div class="ag-kpi__sub">
                    من محتسبة {{ $money($totals['earned']) }}
                    @if ($totals['running'] > 0.005) · جارية {{ $money($totals['running']) }}@endif
                    @if ($totals['pending_approval'] > 0.005) · بانتظار اعتماد {{ $money($totals['pending_approval']) }}@endif
                </div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head"><h4><i class="fas fa-chart-bar"></i> العمولات المصروفة حسب الموظف</h4></div>
            <div class="rpt-card__body">
                @if (count($byEmployeeChart['labels']))
                    <div class="ag-canvas"><canvas id="employeesChart" aria-label="العمولات حسب الموظف" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا عمولات مصروفة في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
        <div class="rpt-card">
            <div class="rpt-card__head"><h4><i class="fas fa-chart-pie"></i> توزيع العمولات</h4></div>
            <div class="rpt-card__body">
                @if (count($mix))
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($mix as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="ag-donut">
                            <canvas id="mixDonut" aria-label="توزيع العمولات" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ $money($mixTotal) }}</strong>
                                <small>{{ CurrencySettings::name() }}</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا عمولات في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== المصروف ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-{{ $isSummary ? 'layer-group' : 'list-ul' }}"></i>
                {{ $isSummary ? 'ملخص العمولات المصروفة حسب '.$groupLabel : 'العمولات المصروفة' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isSummary ? $groups->count().' مجموعة' : $rows->count().' تسوية' }}</span>
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
                            <th>التسويات</th>
                            <th>الموظفون</th>
                            <th>الأهداف</th>
                            <th>المحقق</th>
                            <th>عمولة المبيعات</th>
                            <th>عمولة التحصيلات</th>
                            <th>إجمالي العمولة</th>
                            <th>نسبة العمولة</th>
                            <th>بانتظار اعتماد</th>
                            <th>النسبة من الإجمالي</th>
                            <th>آخر صرف</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($groups as $g)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">{{ $g['label'] }}@if ($g['sub'])<small class="d-block">{{ $g['sub'] }}</small>@endif</td>
                                <td class="rpt-num">{{ number_format($g['count']) }}</td>
                                <td class="rpt-num">{{ number_format($g['employees']) }}</td>
                                <td class="rpt-num">{{ number_format($g['targets']) }}</td>
                                <td class="rpt-num">{{ $g['achieved'] > 0.005 ? $money($g['achieved']) : '—' }}</td>
                                <td class="rpt-num {{ $g['sales'] > 0.005 ? '' : 'rpt-zero' }}">{{ $g['sales'] > 0.005 ? $money($g['sales']) : '—' }}</td>
                                <td class="rpt-num {{ $g['collections'] > 0.005 ? '' : 'rpt-zero' }}">{{ $g['collections'] > 0.005 ? $money($g['collections']) : '—' }}</td>
                                <td class="rpt-num"><strong>{{ $money($g['commission']) }}</strong></td>
                                <td class="rpt-num">{{ $g['rate'] === null ? '—' : $pctFmt($g['rate']) }}</td>
                                <td class="rpt-num {{ $g['pending_approval'] > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $g['pending_approval'] > 0.005 ? $money($g['pending_approval']) : '—' }}</td>
                                <td>
                                    <div class="cm-pct">
                                        <div class="cm-pct__bar"><span style="width:{{ min(100, $g['share']) }}%"></span></div>
                                        <span class="cm-pct__val">{{ $g['share'] }}%</span>
                                    </div>
                                </td>
                                <td class="rpt-num">{{ $g['last_paid'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="rpt-empty"><i class="fas fa-inbox"></i> لا عمولات مصروفة في {{ $periodLabel }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($groups->count())
                        <tfoot>
                            <tr>
                                <td colspan="2">{{ __('sales_reports.common.grand_total') }}</td>
                                <td class="rpt-num">{{ number_format($totals['count']) }}</td>
                                <td class="rpt-num">{{ number_format($totals['employees']) }}</td>
                                <td></td>
                                <td class="rpt-num">{{ $totals['achieved'] > 0.005 ? $money($totals['achieved']) : '—' }}</td>
                                <td class="rpt-num">{{ $money($totals['sales']) }}</td>
                                <td class="rpt-num">{{ $money($totals['collections']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($totals['paid']) }}</strong></td>
                                <td></td>
                                <td class="rpt-num">{{ $money($totals['pending_approval']) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>تاريخ الصرف</th>
                            <th>النوع</th>
                            <th>{{ __('sales_reports.columns.employee') }}</th>
                            <th>الهدف</th>
                            <th>فترة العمولة</th>
                            <th>المحقق</th>
                            <th>المستهدف</th>
                            <th>الإنجاز</th>
                            <th>نسبة العمولة</th>
                            <th>العمولة</th>
                            <th>الصرف</th>
                            <th>الخزينة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($rows as $r)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-num">{{ $r['paid_at'] ?? '—' }}</td>
                                <td><span class="rpt-pill rpt-pill-{{ $r['kind'] === 'collections' ? 'success' : 'primary' }}">{{ $r['kind_label'] }}</span></td>
                                <td class="rpt-name">
                                    @if ($can['employee'] && $r['employee_id'])
                                        <a href="{{ route('employee.show', $r['employee_id']) }}" target="_blank" rel="noopener" class="rpt-client-link">{{ $r['employee_name'] }}</a>
                                    @else
                                        {{ $r['employee_name'] }}
                                    @endif
                                    <small class="d-block">{{ $r['branch_name'] }}</small>
                                </td>
                                <td class="rpt-name">
                                    @if ($url = $targetUrl($r['kind'], $r['target_id']))
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rpt-client-link">{{ $r['target_name'] }}</a>
                                    @else
                                        {{ $r['target_name'] }}
                                    @endif
                                    @if ($r['commission_period'])<small class="d-block">{{ $periodNames[$r['commission_period']] ?? $r['commission_period'] }}</small>@endif
                                </td>
                                <td class="cm-period"><bdi>{{ $r['from'] }}</bdi><small class="d-block">إلى <bdi>{{ $r['to'] }}</bdi></small></td>
                                <td class="rpt-num">{{ $r['achieved'] !== null ? $money($r['achieved']) : '—' }}</td>
                                <td class="rpt-num">{{ $r['target_amount'] ? $money($r['target_amount']) : '—' }}</td>
                                <td>
                                    @if ($r['percentage'] !== null)
                                        <div class="cm-pct">
                                            <div class="cm-pct__bar"><span class="{{ $r['percentage'] < 100 ? 'is-low' : '' }}" style="width:{{ min(100, max(0, $r['percentage'])) }}%"></span></div>
                                            <span class="cm-pct__val">{{ $pctFmt($r['percentage']) }}</span>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="rpt-num">{{ $pctFmt($r['rate']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($r['commission']) }}</strong></td>
                                <td class="cm-method">
                                    {{ $r['method_label'] }}
                                    <div class="cm-links">
                                        @if ($r['voucher_code'])
                                            @if ($can['voucher'] && $r['expense_id'])
                                                <a href="{{ route('expenses.show', $r['expense_id']) }}" target="_blank" rel="noopener">{{ $r['voucher_code'] }}</a>
                                            @else
                                                <span>{{ $r['voucher_code'] }}</span>
                                            @endif
                                        @endif
                                        @if ($r['journal_id'])
                                            @if ($can['journal'])
                                                <a href="{{ route('journal.show', $r['journal_id']) }}" target="_blank" rel="noopener">قيد #{{ $r['journal_id'] }}</a>
                                            @else
                                                <span>قيد #{{ $r['journal_id'] }}</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td class="cm-method">{{ $r['treasury_name'] ?? '—' }}</td>
                                <td class="cm-method">
                                    <span class="rpt-pill rpt-pill-{{ $r['state'] === 'posted' ? 'success' : 'warning' }}">{{ $r['state_label'] }}</span>
                                    <small class="d-block">{{ $r['mode_label'] }} · {{ $r['settled_by'] }}</small>
                                    @if ($can['detail'])
                                        <div class="cm-links"><a href="{{ route('hr.commissions-report.show', ['id' => $r['id'], 'kind' => $r['kind']]) }}" target="_blank" rel="noopener">التفاصيل</a></div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="rpt-empty"><i class="fas fa-inbox"></i> لا عمولات مصروفة في {{ $periodLabel }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->count())
                        <tfoot>
                            <tr>
                                <td colspan="6">{{ __('sales_reports.common.grand_total') }} — {{ number_format($totals['count']) }} تسوية · {{ number_format($totals['employees']) }} موظف</td>
                                <td class="rpt-num">{{ $totals['achieved'] > 0.005 ? $money($totals['achieved']) : '—' }}</td>
                                <td colspan="3"></td>
                                <td class="rpt-num"><strong>{{ $money($totals['paid']) }}</strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $isSummary ? 'مجموعة' : 'تسوية',
            'pagerStorage' => 'agSalesCommissionsPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    {{-- ===================== المحتسَب عن الفترة ===================== --}}
    @if (request('with_earned', '1') === '1')
        <div class="rpt-card" id="earnedCard">
            <div class="rpt-card__head ag-table-head">
                <h4>
                    <i class="fas fa-calculator"></i> العمولات المحتسبة عن الفترة
                    <span class="rpt-pill rpt-pill-secondary">{{ $earned->count() }}</span>
                </h4>
                <p class="cm-note">ما استحقّه كل موظف عن {{ $periodLabel }} بقاعدة التسوية نفسها — دورةً دورة — وما صُرف له منه والباقي.</p>
            </div>

            <div class="rpt-scroll">
                <table class="rpt-table ag-table" id="earnedTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>النوع</th>
                            <th>{{ __('sales_reports.columns.employee') }}</th>
                            <th>الهدف</th>
                            <th>الفترة</th>
                            <th>المحقق</th>
                            <th>المستهدف</th>
                            <th>الإنجاز</th>
                            <th>العمولة المستحقة</th>
                            <th>المصروف</th>
                            <th>الباقي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="earnedTableBody">
                        @forelse ($earned as $e)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td><span class="rpt-pill rpt-pill-{{ $e['kind'] === 'collections' ? 'success' : 'primary' }}">{{ $e['kind_label'] }}</span></td>
                                <td class="rpt-name">{{ $e['employee_name'] }}</td>
                                <td class="rpt-name">
                                    @if ($url = $targetUrl($e['kind'], $e['target_id']))
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rpt-client-link">{{ $e['target_name'] }}</a>
                                    @else
                                        {{ $e['target_name'] }}
                                    @endif
                                </td>
                                <td class="cm-period">
                                    <bdi>{{ $e['from'] }}</bdi><small class="d-block">إلى <bdi>{{ $e['to'] }}</bdi>{{ $e['cycles'] > 1 ? ' · '.$e['cycles'].' دورات' : '' }}</small>
                                </td>
                                <td class="rpt-num">{{ $money($e['achieved']) }}</td>
                                <td class="rpt-num">{{ $money($e['target_amount']) }}</td>
                                <td>
                                    @if ($e['percentage'] !== null)
                                        <div class="cm-pct">
                                            <div class="cm-pct__bar"><span class="{{ $e['percentage'] < 100 ? 'is-low' : '' }}" style="width:{{ min(100, max(0, $e['percentage'])) }}%"></span></div>
                                            <span class="cm-pct__val">{{ $pctFmt($e['percentage']) }}</span>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="rpt-num"><strong>{{ $money($e['commission']) }}</strong></td>
                                <td class="rpt-num {{ $e['paid'] > 0.005 ? '' : 'rpt-zero' }}">{{ $e['paid'] > 0.005 ? $money($e['paid']) : '—' }}</td>
                                <td class="rpt-num {{ $e['remaining'] > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}"><strong>{{ $e['remaining'] > 0.005 ? $money($e['remaining']) : '—' }}</strong></td>
                                <td class="cm-method"><span class="rpt-pill rpt-pill-{{ $e['state_tone'] }}">{{ $e['state_label'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rpt-empty"><i class="fas fa-inbox"></i> لم يبلغ أحدٌ مستوى عمولة في {{ $periodLabel }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($earned->count())
                        <tfoot>
                            <tr>
                                <td colspan="8">{{ __('sales_reports.common.grand_total') }} — مستحقة لم تُصرف {{ $money($totals['due']) }} · جارية {{ $money($totals['running']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($totals['earned']) }}</strong></td>
                                <td class="rpt-num">{{ $money($totals['earned_paid']) }}</td>
                                <td class="rpt-num"><strong>{{ $money($totals['due'] + $totals['running']) }}</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

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
            var emp = @json($byEmployeeChart);
            var mix = @json($mix);

            var bar = document.getElementById('employeesChart');
            if (bar && emp.labels.length) {
                var theme = agChartTheme();
                new Chart(bar.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: emp.labels,
                        datasets: [
                            { label: 'مبيعات', data: emp.sales, backgroundColor: '#8b5cf6', borderRadius: 4, maxBarThickness: 44, stack: 'c' },
                            { label: 'تحصيلات', data: emp.collections, backgroundColor: '#10b981', borderRadius: 4, maxBarThickness: 44, stack: 'c' }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, position: 'bottom', rtl: true, labels: { color: theme.muted, boxWidth: 12 } },
                            tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + agMoney(c.parsed.y); } } }
                        },
                        scales: {
                            x: { reverse: true, stacked: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 45, autoSkip: true,
                                 callback: function (v) { var l = String(this.getLabelForValue(v)); return l.length > 14 ? l.slice(0, 13) + '…' : l; } } },
                            y: { stacked: true, beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                 ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                        }
                    }
                });
            }

            var donut = document.getElementById('mixDonut');
            if (donut && mix.length) {
                var t = agChartTheme();
                new Chart(donut, {
                    type: 'doughnut',
                    data: {
                        labels: mix.map(function (s) { return s.label; }),
                        datasets: [{ data: mix.map(function (s) { return s.value; }), backgroundColor: mix.map(function (s) { return s.color; }), borderColor: t.surface, borderWidth: 3, hoverOffset: 6 }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) {
                            var s = mix[c.dataIndex]; return ' ' + agMoney(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)');
                        } } } }
                    }
                });
            }
        });
    </script>

    @php
        if ($isSummary) {
            $excelHeaders = ['#', $groupLabel, 'التسويات', 'الموظفون', 'الأهداف', 'المحقق', 'عمولة المبيعات', 'عمولة التحصيلات', 'إجمالي العمولة', 'نسبة العمولة %', 'بانتظار اعتماد', 'النسبة من الإجمالي %', 'آخر صرف'];
            $excelRows = $groups->map(fn ($g, $i) => ['values' => [
                $i + 1, $g['label'].($g['sub'] ? ' — '.$g['sub'] : ''), $g['count'], $g['employees'], $g['targets'], round($g['achieved'], 2),
                round($g['sales'], 2), round($g['collections'], 2), round($g['commission'], 2), $g['rate'] ?? '', round($g['pending_approval'], 2), $g['share'], $g['last_paid'] ?? '',
            ], 'alignments' => [null, 'right']])->all();
            $excelSummary = $excelRows ? [[
                'values' => ['', 'الإجمالي العام', $totals['count'], $totals['employees'], '', round($totals['achieved'], 2), round($totals['sales'], 2), round($totals['collections'], 2), round($totals['paid'], 2), '', round($totals['pending_approval'], 2), '', ''],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = ['#', 'تاريخ الصرف', 'النوع', 'الموظف', 'الفرع', 'الهدف', 'دورة العمولة', 'من', 'إلى', 'المحقق', 'المستهدف', 'الإنجاز %', 'نسبة العمولة %', 'العمولة', 'طريقة الصرف', 'سند الصرف', 'رقم القيد', 'الخزينة', 'الحالة', 'التسوية', 'بواسطة'];
            $excelRows = $rows->map(fn ($r, $i) => ['values' => [
                $i + 1, $r['paid_at'] ?? '', $r['kind_label'], $r['employee_name'], $r['branch_name'], $r['target_name'], $periodNames[$r['commission_period']] ?? '',
                $r['from'], $r['to'], $r['achieved'] !== null ? round($r['achieved'], 2) : '', $r['target_amount'] ? round($r['target_amount'], 2) : '',
                $r['percentage'] ?? '', $r['rate'] ?? '', round($r['commission'], 2), $r['method_label'], (string) ($r['voucher_code'] ?? ''),
                $r['journal_id'] ? (string) $r['journal_id'] : '', (string) ($r['treasury_name'] ?? ''), $r['state_label'], $r['mode_label'], $r['settled_by'],
            ], 'alignments' => [null, null, null, 'right']])->all();
            $excelSummary = $excelRows ? [[
                'values' => ['', 'الإجمالي العام', '', $totals['employees'].' موظف', '', '', '', '', '', round($totals['achieved'], 2), '', '', '', round($totals['paid'], 2), '', '', '', '', '', '', ''],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => 'تقرير_العمولات'.($isSummary ? '_ملخص_'.$groupBy : '_تفاصيل'),
        'mainTitle' => $pageTitle,
        'info' => ['الفترة' => $periodLabel] + $appliedFilters,
        'cards' => [
            ['title' => 'العمولات المصروفة', 'value' => $money($totals['paid']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'عمولات المبيعات', 'value' => $money($totals['sales']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'عمولات التحصيلات', 'value' => $money($totals['collections']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'مستحقة لم تُصرف', 'value' => $money($totals['due']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
        ],
        'headers' => $excelHeaders,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
