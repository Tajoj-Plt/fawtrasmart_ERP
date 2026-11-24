{{--
    تقرير المبيعات حسب الموظف — على تصميم تقارير العملاء المعتمد (`ag-*`):
    ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات،
    رسمان (الأعلى صافياً + حالة التحصيل)، ثمّ جدولٌ واحدٌ بترقيم صفحات.

    ما لم يتغيّر: الفلاترُ وأسماؤها، والفتراتُ الجاهزة روابطُ يحسب الخادمُ
    مداها، وزرّا «ملخص/تفاصيل» يُرسلان `view_type`، والتصديرُ يُبنى في الخادم
    من صفوف التقرير (`report_partials.excel`) والطباعةُ تطبع الصفحة.

    التفاصيلُ صارت جدولاً واحداً: صفُّ عنوانٍ لكلّ مجموعة ثمّ مستنداتُها ثمّ
    إجماليُّها — والترقيمُ بالمجموعة فلا تنقسم بين صفحتين.

    نسخةٌ من `Sales_By_Customer` لا يختلف فيها إلّا مصفوفةُ `$screen`
    والعنوان — عدِّلهما معاً.
--}}
@extends('sales::master')

@section('title')
    {{ __('sales_reports.by_employee.title') }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-table tbody tr.ag-grand td { font-weight: 800; border-top: 2px solid var(--rpt-line); }
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

    $screen = [
        'key' => 'by_employee',
        'route' => 'salesReports.byEmployee',
        'guideKey' => 'sales-reports.by-employee',
        'icon' => 'user-tie',
        'groupFallback' => __('sales_reports.group_by.employee'),
        'storage' => 'agSalesByEmployeePageSize',
    ];

    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number(abs($v));
    $signClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-pos' : 'rpt-neg');
    $currency = CurrencySettings::name();

    /* رابطُ العميل بلا include لكلّ صف: كلُّ include يُشغّل مُركِّبات العرض
       العامّة (استعلامٌ لكلّ صفّ)، وآلافُ الصفوف في «التفاصيل» تصير ثوانيَ. */
    $clientLink = function ($id, $name, $fallback = '-') {
        $label = e(trim((string) $name) !== '' ? $name : $fallback);

        return $id
            ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">'.$label.'</a>'
            : $label;
    };

    $periodLabel = __('sales_reports.common.period_range', ['from' => $fromDate->format('d/m/Y'), 'to' => $toDate->format('d/m/Y')]);

    $selectedClientName = optional($selectedClient ?? null)->trade_name;
    $selectedEmployee = request('employee_id') ? optional($users->firstWhere('id', (int) request('employee_id')))->name : null;
    /* الفرعُ من شجرة «تابع لـ» وحدها؛ وبلا عقدةٍ في الطلب فهو فرعُ الهيدر. */
    $selectedBranchName = ($effectiveBranchId ?? null)
        ? optional(\App\Models\Branch::findCached($effectiveBranchId))->name
        : null;
    $selectedCategory = request('category_id') ? optional($categories->firstWhere('id', (int) request('category_id')))->name : null;
    /* حالةُ الدفع تخصّ الفاتورة، وحالةُ العميل («نشط/موقوف») تخصّ العميلَ نفسَه. */
    $paymentStatusLabels = ['1' => __('sales_reports.status.paid'), '2' => __('sales_reports.status.partial'), '3' => __('sales_reports.status.unpaid')];
    $selectedPaymentStatus = $paymentStatusLabels[request('payment_status')] ?? null;
    $selectedStatus = request('status') === 'no_status'
        ? __('sales_reports.filters.no_status')
        : (request('status') ? optional($statuses->firstWhere('id', (int) request('status')))->name : null);
    $selectedStage = request('registration_stage') === 'no_stage'
        ? __('sales_reports.filters.no_stage')
        : (request('registration_stage') ? optional($registrationStages->firstWhere('id', (int) request('registration_stage')))->name : null);

    $groupLabel = $groupByOptions[$groupBy] ?? $screen['groupFallback'];
    $groupIcon = ['client' => 'user', 'employee' => 'user-tie', 'branch' => 'sitemap', 'day' => 'calendar-day', 'month' => 'calendar-alt'][$groupBy] ?? 'layer-group';
    $isClientGroup = $groupBy === 'client';
    $groupUnit = ['client' => 'عميل', 'employee' => 'موظف', 'branch' => 'فرع', 'day' => 'يوم', 'month' => 'شهر'][$groupBy] ?? 'مجموعة';

    /* ─────────── الرسمان ─────────── */
    $groupIsTime = in_array($groupBy, ['day', 'month'], true);
    $chartTitle = $groupIsTime
        ? 'صافي المبيعات حسب '.$groupLabel
        : 'الأعلى صافياً — '.$groupLabel;
    $chartSource = $groupIsTime
        ? $reportData->sortKeys()->take(-($groupBy === 'day' ? 31 : 24))
        : $reportData->take(10);
    $chartData = [
        'labels' => $chartSource->map(fn ($r) => (string) ($r['name'] ?: __('sales_reports.common.none')))->values()->all(),
        'values' => $chartSource->map(fn ($r) => round((float) ($r['net_amount'] ?? $r['amount']), 2))->values()->all(),
    ];

    $tNet = (float) ($totals['net_amount'] ?? 0);
    $tPaid = (float) ($totals['paid'] ?? 0);
    $tUnpaid = (float) ($totals['unpaid'] ?? 0);
    $tGross = (float) ($totals['amount'] ?? 0);
    $tReturns = (float) ($totals['returns_amount'] ?? 0);
    $collectBase = $tPaid + $tUnpaid;
    $collectLegend = [
        ['label' => __('sales_reports.columns.paid'), 'value' => $tPaid, 'color' => '#10b981', 'pct' => $collectBase > 0 ? round($tPaid / $collectBase * 100) : null, 'donut' => true],
        ['label' => __('sales_reports.columns.unpaid'), 'value' => $tUnpaid, 'color' => '#f97316', 'pct' => $collectBase > 0 ? round($tUnpaid / $collectBase * 100) : null, 'donut' => true],
        ['label' => __('sales_reports.columns.returns_and_notes'), 'value' => $tReturns, 'color' => '#ef4444', 'pct' => $tGross > 0 ? round($tReturns / $tGross * 100) : null, 'donut' => false],
    ];
    $collectDonut = array_values(array_filter($collectLegend, fn ($s) => $s['donut'] && $s['value'] > 0.005));

    $summaryCols = $isClientGroup ? 11 : 9;
@endphp

<div class="rpt">

    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => __('sales_reports.'.$screen['key'].'.title').' — '.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view')),
        'periodLabel' => $periodLabel,
        'filters' => [
            'التجميع' => $groupLabel,
            'العميل' => $selectedClientName,
            'الموظف' => $selectedEmployee,
            'الفرع' => $selectedBranchName,
            'التصنيف' => $selectedCategory,
            'حالة الدفع' => $selectedPaymentStatus,
            __('sales_reports.filters.client_status') => $selectedStatus,
            __('sales_reports.filters.client_stage') => $selectedStage,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => $screen['icon'],
            'headTitle' => __('sales_reports.'.$screen['key'].'.heading'),
            'headSubtitle' => __('sales_reports.'.$screen['key'].'.subtitle'),
            'headPeriod' => $periodLabel,
            'headExtra' => 'التجميع: '.$groupLabel,
            'headGuideKey' => $screen['guideKey'],
            'headCrumbLabel' => __('sales_reports.index.title'),
            'headCrumbUrl' => route('salesReports.index'),
        ])
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route($screen['route']) }}" id="filterForm">

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
                        <label for="employee_id">{{ __('sales_reports.filters.employee') }}</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.filters.all_employees') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('employee_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('reports.partials.client-select', [
                            'name' => 'client_id',
                            'id' => 'client_id',
                            'url' => route($screen['route'].'.searchClients'),
                            'selectedClient' => $selectedClient ?? null,
                            'label' => __('sales_reports.filters.client'),
                            'placeholder' => __('sales_reports.filters.all_clients'),
                        ])
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
                        <label for="payment_status">{{ __('sales_reports.filters.payment_status') }}</label>
                        <select name="payment_status" id="payment_status" class="form-control rpt-select2">
                            <option value="">{{ __('sales_reports.common.all') }}</option>
                            <option value="1" @selected(request('payment_status') == '1')>{{ __('sales_reports.status.paid') }}</option>
                            <option value="3" @selected(request('payment_status') == '3')>{{ __('sales_reports.status.unpaid') }}</option>
                            <option value="2" @selected(request('payment_status') == '2')>{{ __('sales_reports.status.partial') }}</option>
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

                    <div class="rpt-field">
                        <label for="group_by">{{ __('sales_reports.filters.group_by') }}</label>
                        <select name="group_by" id="group_by" class="form-control rpt-select2">
                            @foreach ($groupByOptions as $key => $label)
                                <option value="{{ $key }}" @selected($groupBy == $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
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
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route($screen['route']) }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> {{ __('sales_reports.common.reset') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات (بترتيب المعادلة) ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.columns.sales_total') }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tGross) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['count'] ?? 0 }} {{ __('sales_reports.kpi.sales_invoice_unit') }} · {{ __('sales_reports.kpi.before_returns') }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice-dollar"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.columns.returns_and_notes') }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tReturns) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['returns_count'] ?? 0 }} عملية</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.columns.net_sales') }}</div>
                <div class="ag-kpi__value"><bdi class="{{ $tNet < -0.005 ? 'rpt-neg' : '' }}">{{ $signed($tNet) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.kpi.sales_minus_returns') }} · {{ count($reportData) }} {{ $groupLabel }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.columns.paid') }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tPaid) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.kpi.collected_of_net') }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">{{ __('sales_reports.columns.unpaid') }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tUnpaid) }}</bdi></div>
                <div class="ag-kpi__sub">{{ __('sales_reports.kpi.due_on_clients') }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> {{ $chartTitle }}</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($chartData['labels']))
                    <div class="ag-canvas"><canvas id="groupsChart" aria-label="{{ $chartTitle }}" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> حالة التحصيل من الصافي</h4>
            </div>
            <div class="rpt-card__body">
                @if ($tGross > 0.005 || $tReturns > 0.005)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($collectLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($collectDonut))
                            <div class="ag-donut">
                                <canvas id="collectDonut" aria-label="حالة التحصيل من الصافي" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $signed($tNet) }}</strong>
                                    <small>{{ __('sales_reports.columns.net_sales') }} · {{ $currency }}</small>
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
                {{ $isSummary ? __('sales_reports.'.$screen['key'].'.summary_heading', ['group' => $groupLabel]) : __('sales_reports.common.details_view') }}
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} {{ $groupLabel }}</span>
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
                            <th>{{ $groupLabel }}</th>
                            @if ($isClientGroup)
                                <th>{{ __('sales_reports.filters.client_status') }}</th>
                                <th>{{ __('sales_reports.filters.client_stage') }}</th>
                            @endif
                            <th>{{ __('sales_reports.columns.invoices_count') }}</th>
                            <th>{{ __('sales_reports.columns.sales_total') }}</th>
                            <th>{{ __('sales_reports.columns.returns') }}</th>
                            <th>{{ __('sales_reports.columns.net_sales') }}</th>
                            <th>{{ __('sales_reports.columns.paid') }}</th>
                            <th>{{ __('sales_reports.columns.unpaid') }}</th>
                            <th>{{ __('sales_reports.columns.status') }}</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $groupId => $data)
                            @php $rowNet = $data['net_amount'] ?? $data['amount']; @endphp
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">{!! $clientLink($isClientGroup && is_numeric($groupId) ? $groupId : null, $data['name'], __('sales_reports.common.none')) !!}</td>
                                @if ($isClientGroup)
                                    <td>{{ $data['status_name'] ?? '—' }}</td>
                                    <td>{{ $data['stage_name'] ?? '—' }}</td>
                                @endif
                                <td>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $data['count'] }}</span>
                                    @if (($data['returns_count'] ?? 0) > 0)
                                        <span class="rpt-pill rpt-pill-danger">-{{ $data['returns_count'] }}</span>
                                    @endif
                                </td>
                                <td class="rpt-num rpt-pos">{{ $money($data['amount']) }}</td>
                                <td class="rpt-num {{ ($data['returns_amount'] ?? 0) > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ ($data['returns_amount'] ?? 0) > 0 ? '('.$money($data['returns_amount']).')' : '—' }}
                                </td>
                                <td class="rpt-num {{ $signClass($rowNet) }}"><strong>{{ $signed($rowNet) }}</strong></td>
                                <td class="rpt-num {{ ($data['paid'] ?? 0) > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ ($data['paid'] ?? 0) > 0 ? $money($data['paid']) : '—' }}</td>
                                <td class="rpt-num {{ ($data['unpaid'] ?? 0) > 0 ? 'rpt-warnc' : 'rpt-zero' }}">{{ ($data['unpaid'] ?? 0) > 0 ? $money($data['unpaid']) : '—' }}</td>
                                <td>
                                    @if (isset($data['status']))
                                        <span class="rpt-pill rpt-pill-{{ $data['status']['class'] }}">{{ $data['status']['text'] }}</span>
                                    @else
                                        <span class="rpt-pill rpt-pill-secondary">{{ __('sales_reports.common.none') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $summaryCols }}" class="rpt-empty">
                                <i class="fas fa-inbox"></i> {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if (count($reportData) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="{{ $isClientGroup ? 4 : 2 }}">{{ __('sales_reports.common.grand_total') }}</td>
                                <td>{{ $totals['count'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['amount']) }}</td>
                                <td class="rpt-num rpt-neg">({{ $money($totals['returns_amount'] ?? 0) }})</td>
                                <td class="rpt-num {{ $signClass($tNet) }}">{{ $signed($tNet) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['paid']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($totals['unpaid']) }}</td>
                                <td>—</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">{{ __('sales_reports.columns.row') }}</th>
                            <th>{{ __('sales_reports.columns.reference_number') }}</th>
                            <th>{{ __('sales_reports.columns.date') }}</th>
                            <th>{{ __('sales_reports.columns.client') }}</th>
                            <th>{{ __('sales_reports.filters.client_status') }}</th>
                            <th>{{ __('sales_reports.filters.client_stage') }}</th>
                            <th>{{ __('sales_reports.columns.employee') }}</th>
                            <th>{{ __('sales_reports.columns.document_type') }}</th>
                            <th>{{ __('sales_reports.columns.branch') }}</th>
                            <th>{{ __('sales_reports.columns.amount') }}</th>
                            <th>{{ __('sales_reports.columns.paid') }}</th>
                            <th>{{ __('sales_reports.columns.remaining') }}</th>
                            <th>{{ __('sales_reports.columns.status') }}</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $groupId => $data)
                            @php
                                $rowKey = 'g'.$loop->index;
                                $rowNet = $data['net_amount'] ?? $data['amount'];
                            @endphp
                            <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                                <td colspan="13" class="rpt-name">
                                    <i class="fas fa-{{ $groupIcon }}"></i>
                                    <strong>{!! $clientLink($isClientGroup && is_numeric($groupId) ? $groupId : null, $data['name'], __('sales_reports.common.none')) !!}</strong>
                                    <small class="text-muted">— {{ $groupLabel }}</small>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $data['count'] }} فاتورة</span>
                                    @if (($data['returns_count'] ?? 0) > 0)
                                        <span class="rpt-pill rpt-pill-danger">{{ $data['returns_count'] }} مرتجع</span>
                                    @endif
                                    @if (isset($data['status']))
                                        <span class="rpt-pill rpt-pill-{{ $data['status']['class'] }}">{{ $data['status']['text'] }}</span>
                                    @endif
                                </td>
                            </tr>
                            @foreach (($data['items'] ?? []) as $i => $entry)
                                @php
                                    $isReturn = $entry['is_return'] ?? false;
                                    $isCreditNote = ($entry['is_credit_note'] ?? false)
                                        || (isset($entry['reference_type']) && $entry['reference_type'] == 'اشعار دائن');
                                    $kind = $isCreditNote ? 'credit_note' : ($isReturn ? 'return' : 'sale');
                                @endphp
                                <tr class="rpt-row-{{ $kind }}" data-parent-row="{{ $rowKey }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <strong>{{ $entry['reference_number'] ?? $entry['id'] }}</strong>
                                        @if ($isReturn && ! empty($entry['original_invoice_ref']))
                                            <small class="d-block" style="font-size:.68rem">{{ __('sales_reports.columns.original_invoice') }}: {{ $entry['original_invoice_ref'] }}</small>
                                        @endif
                                    </td>
                                    <td class="rpt-num">{{ $entry['date'] }}</td>
                                    <td class="rpt-name">
                                        {!! $clientLink($entry['client_id'] ?? null, $entry['client_name'] ?? '') !!}
                                        <small class="d-block">{{ __('sales_reports.columns.client_code') }}: {{ $entry['client_code'] ?? '-' }}</small>
                                    </td>
                                    <td>{{ $entry['client_status_name'] ?? '—' }}</td>
                                    <td>{{ $entry['client_stage_name'] ?? '—' }}</td>
                                    <td>{{ $entry['employee_name'] ?? '-' }}</td>
                                    <td>
                                        <span class="rpt-pill rpt-pill-{{ $isCreditNote ? 'info' : ($isReturn ? 'danger' : 'success') }}">{{ $entry['reference_type'] ?? '-' }}</span>
                                    </td>
                                    <td>{{ $entry['branch_name'] ?? '-' }}</td>
                                    <td class="rpt-num {{ $isReturn || $isCreditNote ? 'rpt-neg' : 'rpt-pos' }}">
                                        {{ $isReturn ? '('.$money(abs($entry['amount'] ?? 0)).')' : $money($entry['amount'] ?? 0) }}
                                    </td>
                                    <td class="rpt-num {{ ($entry['paid'] ?? 0) > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ ($entry['paid'] ?? 0) > 0 ? $money($entry['paid']) : '—' }}</td>
                                    <td class="rpt-num {{ (! $isCreditNote && ($entry['unpaid'] ?? 0) > 0) ? 'rpt-warnc' : 'rpt-zero' }}">
                                        {{ $isCreditNote ? $money(0) : (($entry['unpaid'] ?? 0) > 0 ? $money($entry['unpaid']) : '—') }}
                                    </td>
                                    <td>
                                        @if (isset($entry['status']))
                                            <span class="rpt-pill rpt-pill-{{ $entry['status']['class'] }}">{{ $entry['status']['text'] }}</span>
                                        @else
                                            <span class="rpt-pill rpt-pill-secondary">{{ __('sales_reports.common.none') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                                <td colspan="9">إجمالي {{ $data['name'] ?: __('sales_reports.common.none') }}
                                    <small class="text-muted">({{ __('sales_reports.columns.sales_total') }} {{ $money($data['amount']) }} − {{ __('sales_reports.columns.returns') }} {{ $money($data['returns_amount'] ?? 0) }})</small>
                                </td>
                                <td class="rpt-num {{ $signClass($rowNet) }}">{{ $signed($rowNet) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($data['paid']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($data['unpaid']) }}</td>
                                <td></td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="rpt-empty">
                                <i class="fas fa-inbox"></i> {{ __('sales_reports.common.no_movement', ['period' => $periodLabel]) }}
                            </td></tr>
                        @endforelse

                        @if (count($reportData) > 0)
                            <tr class="ag-grand">
                                <td colspan="9">{{ __('sales_reports.common.grand_total') }} — {{ $totals['count'] }} فاتورة</td>
                                <td class="rpt-num {{ $signClass($tNet) }}">{{ $signed($tNet) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['paid']) }}</td>
                                <td class="rpt-num rpt-warnc">{{ $money($totals['unpaid']) }}</td>
                                <td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $groupUnit,
            'pagerStorage' => $screen['storage'],
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
            const GROUPS_CHART = @json($chartData);
            const COLLECT_DONUT = @json($collectDonut);
            const GROUP_IS_TIME = @json($groupIsTime);

            $(function () {
                const bar = document.getElementById('groupsChart');
                if (bar && typeof Chart !== 'undefined' && GROUPS_CHART.labels.length) {
                    const theme = agChartTheme();
                    new Chart(bar.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: GROUPS_CHART.labels,
                            datasets: [{ data: GROUPS_CHART.values, backgroundColor: '#8b5cf6', borderRadius: 6, maxBarThickness: 48 }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agMoney(c.parsed.y); } } }
                            },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: GROUP_IS_TIME ? 45 : 0, autoSkip: true,
                                     callback: function (v) { const l = String(this.getLabelForValue(v)); return l.length > 14 ? l.slice(0, 13) + '…' : l; } } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        },
                        plugins: GROUPS_CHART.labels.length <= 12 ? [agValueLabels(GROUPS_CHART.values)] : []
                    });
                }

                const donut = document.getElementById('collectDonut');
                if (donut && typeof Chart !== 'undefined' && COLLECT_DONUT.length) {
                    const theme = agChartTheme();
                    new Chart(donut, {
                        type: 'doughnut',
                        data: {
                            labels: COLLECT_DONUT.map(function (s) { return s.label; }),
                            datasets: [{
                                data: COLLECT_DONUT.map(function (s) { return s.value; }),
                                backgroundColor: COLLECT_DONUT.map(function (s) { return s.color; }),
                                borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) {
                                    const s = COLLECT_DONUT[c.dataIndex];
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
            $excelHeaders = $isClientGroup
                ? ['#', $groupLabel, 'حالة العميل', 'وضع العميل', 'عدد الفواتير', 'إجمالي المبيعات', 'المرتجعات', 'صافي المبيعات', 'المدفوع', 'غير المدفوع', 'الحالة']
                : ['#', $groupLabel, 'عدد الفواتير', 'إجمالي المبيعات', 'المرتجعات', 'صافي المبيعات', 'المدفوع', 'غير المدفوع', 'الحالة'];
            $excelWidths = $isClientGroup ? [6, 26, 14, 14, 14, 18, 16, 18, 16, 16, 16] : [6, 30, 14, 18, 16, 18, 16, 16, 16];
            $excelRows = $reportData->values()->map(fn ($r, $i) => [
                'values' => $isClientGroup ? [
                    $i + 1,
                    $r['name'] ?: 'غير محدد',
                    $r['status_name'] ?? '—',
                    $r['stage_name'] ?? '—',
                    $r['count'],
                    $r['amount'],
                    $r['returns_amount'] ?? 0,
                    $r['net_amount'] ?? $r['amount'],
                    $r['paid'],
                    $r['unpaid'],
                    $r['status']['text'] ?? 'غير محدد',
                ] : [
                    $i + 1,
                    $r['name'] ?: 'غير محدد',
                    $r['count'],
                    $r['amount'],
                    $r['returns_amount'] ?? 0,
                    $r['net_amount'] ?? $r['amount'],
                    $r['paid'],
                    $r['unpaid'],
                    $r['status']['text'] ?? 'غير محدد',
                ],
                'alignments' => [null, 'right'],
            ])->all();
            $excelSummary = count($reportData) ? [[
                'values' => $isClientGroup ? [
                    '', 'الإجمالي العام', '', '', $totals['count'], $totals['amount'],
                    $totals['returns_amount'] ?? 0, $totals['net_amount'] ?? $totals['amount'],
                    $totals['paid'], $totals['unpaid'], '',
                ] : [
                    '', 'الإجمالي العام', $totals['count'], $totals['amount'],
                    $totals['returns_amount'] ?? 0, $totals['net_amount'] ?? $totals['amount'],
                    $totals['paid'], $totals['unpaid'], '',
                ],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = ['#', $groupLabel, 'رقم المرجع', 'التاريخ', 'العميل', 'حالة العميل', 'وضع العميل', 'الموظف', 'نوع المستند', 'الفرع', 'المبلغ', 'المدفوع', 'المتبقي', 'الحالة'];
            $excelWidths = [6, 24, 14, 12, 24, 14, 14, 18, 14, 14, 16, 16, 16, 14];
            $excelRows = [];
            $n = 0;
            foreach ($reportData as $r) {
                foreach (($r['items'] ?? []) as $entry) {
                    $excelRows[] = [
                        'values' => [
                            ++$n,
                            $r['name'] ?: 'غير محدد',
                            (string) ($entry['reference_number'] ?? $entry['id']),
                            $entry['date'],
                            $entry['client_name'] ?? '-',
                            $entry['client_status_name'] ?? '—',
                            $entry['client_stage_name'] ?? '—',
                            $entry['employee_name'] ?? '-',
                            $entry['reference_type'] ?? '-',
                            $entry['branch_name'] ?? '-',
                            $entry['amount'] ?? 0,
                            $entry['paid'] ?? 0,
                            $entry['unpaid'] ?? 0,
                            $entry['status']['text'] ?? 'غير محدد',
                        ],
                        'alignments' => [null, 'right'],
                    ];
                }
            }
            $excelSummary = $excelRows ? [[
                'values' => [
                    '', 'الإجمالي العام', '', '', '', '', '', '', '', '',
                    $totals['net_amount'] ?? $totals['amount'], $totals['paid'], $totals['unpaid'], '',
                ],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    {{-- «العميل» كان يُمرَّر نموذجاً كاملاً، و«حالة الدفع» كانت تحمل حالةَ العميل --}}
    @include('sales::sals_report.report_partials.excel', [
        'fileName' => __('sales_reports.'.$screen['key'].'.export_name').'_'.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details')),
        'mainTitle' => __('sales_reports.'.$screen['key'].'.title').' — '.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view')),
        'info' => [
            'الفترة' => $periodLabel,
            'التجميع' => $groupLabel,
            'العميل' => $selectedClientName ?: 'الكل',
            'الموظف' => $selectedEmployee ?: 'الكل',
            'الفرع' => $selectedBranchName ?: 'الكل',
            'التصنيف' => $selectedCategory ?: 'الكل',
            'حالة الدفع' => $selectedPaymentStatus ?: 'الكل',
            'حالة العميل' => $selectedStatus ?: 'الكل',
            'وضع العميل' => $selectedStage ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'عدد الفواتير', 'value' => $totals['count'] ?? 0, 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المبيعات', 'value' => $money($totals['amount'] ?? 0), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المرتجعات', 'value' => $money($totals['returns_amount'] ?? 0), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'صافي المبيعات', 'value' => $money($totals['net_amount'] ?? 0), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'المدفوع', 'value' => $money($totals['paid'] ?? 0), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'غير المدفوع', 'value' => $money($totals['unpaid'] ?? 0), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningBg', 'borderColor' => 'warningLight'],
        ],
        'headers' => $excelHeaders,
        'widths' => $excelWidths,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
