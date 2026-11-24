{{--
    تقرير المدفوعات حسب الموظف — على تصميم تقارير العملاء المعتمد (`ag-*`):
    ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات،
    رسمان (الأعلى مدفوعاتٍ + توزيعها على الخزينة والبنك)، ثمّ جدولٌ واحدٌ
    بترقيم صفحات.

    ما لم يتغيّر: الفلاترُ وأسماؤها، والفتراتُ الجاهزة يحسب الخادمُ مداها،
    وزرّا «ملخص/تفاصيل» يُرسلان `view_type`، وأعمدةُ الجدولين كما كانت.
    التصديرُ صار يُبنى في الخادم من صفوف التقرير (`report_partials.excel`)
    والطباعةُ تطبع الصفحة نفسها بترويستها — لا نافذةً يُكتب فيها HTML.

    التفاصيلُ جدولٌ واحد: صفُّ عنوانٍ لكلّ مجموعة ثمّ دفعاتُها ثمّ إجماليُّها
    — والترقيمُ بالمجموعة فلا تنقسم بين صفحتين.

    الشاشةُ نفسُها لتقرير «حسب العميل»: `Payments_By_Customer` نسخةٌ منها لا
    يختلف فيها إلّا مصفوفةُ `$screen`.
--}}
@extends('sales::master')

@section('title')
    تقرير المدفوعات حسب الموظف
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-table tfoot tr.ag-grand td { font-weight: 800; border-top: 2px solid var(--rpt-line); }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .ag-table td.ag-note { max-width: 240px; color: var(--rpt-muted); }
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;
    use Illuminate\Support\Str;

    $screen = [
        'title' => 'تقرير المدفوعات حسب الموظف',
        'heading' => 'المدفوعات حسب الموظف',
        'subtitle' => 'ما حصّله كلُّ موظفٍ في الخزائن والبنوك خلال الفترة — بعد طرح المرتجعات المصروفة؛ السندات الملغاة مستبعدة مع قيود إلغائها.',
        'route' => 'paymentsReports.byEmployee',
        'guideKey' => 'sales-reports.payments-by-employee',
        'icon' => 'hand-holding-usd',
        'storage' => 'agPaymentsByEmployeePageSize',
        'exportName' => 'تقرير_المدفوعات_حسب_الموظف',
    ];

    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number(abs($v));
    $signClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-pos' : 'rpt-neg');
    $currency = CurrencySettings::name();

    /* رابطُ العميل بلا include لكلّ صف: كلُّ include يُشغّل مُركِّبات العرض
       العامّة (استعلامٌ لكلّ صفّ)، وآلافُ الدفعات في «التفاصيل» تصير ثوانيَ. */
    $clientLink = function ($id, $name, $fallback = '-') {
        $label = e(trim((string) $name) !== '' ? $name : $fallback);

        return $id
            ? '<a href="'.e(route('clients.show', $id)).'" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة العميل" onclick="event.stopPropagation()">'.$label.'</a>'
            : $label;
    };

    $reportRows = collect($reportData);
    $collections = collect($collectionsData ?? []);

    $periodLabel = __('sales_reports.common.period_range', ['from' => $startDate->format('d/m/Y'), 'to' => $endDate->format('d/m/Y')]);

    $selectedClientName = $clientId ? optional($clients->firstWhere('id', (int) $clientId), fn ($c) => $c->trade_name ?? $c->name) : null;
    $selectedEmployee = $employeeId ? optional($employees->firstWhere('id', (int) $employeeId))->name : null;
    /* الفرعُ من شجرة «تابع لـ» وحدها؛ وبلا عقدةٍ في الطلب فهو فرعُ الهيدر. */
    $selectedBranchName = ($effectiveBranchId ?? null)
        ? optional(\App\Models\Branch::findCached($effectiveBranchId))->name
        : null;

    $groupLabel = $groupByOptions[$groupBy] ?? 'التصنيف';
    $groupIcon = [
        'day' => 'calendar-day', 'day_employee' => 'calendar-day', 'month' => 'calendar-alt', 'region_month' => 'calendar-alt',
        'employee' => 'user-tie', 'client' => 'user', 'branch' => 'sitemap', 'region' => 'map-marker-alt',
        'account_type' => 'university', 'treasury' => 'cash-register',
    ][$groupBy] ?? 'layer-group';
    $groupUnit = [
        'day' => 'يوم', 'day_employee' => 'يوم/موظف', 'month' => 'شهر', 'region_month' => 'مجموعة/شهر',
        'employee' => 'موظف', 'client' => 'عميل', 'branch' => 'فرع', 'region' => 'مجموعة',
        'account_type' => 'نوع', 'treasury' => 'خزينة',
    ][$groupBy] ?? 'مجموعة';
    $isClientGroup = $groupBy === 'client';

    /* ─────────── شجرة «تابع لـ» ───────────
       السكربتُ المشترك يسمّي المستوى بنوع العقدة فوقه (`type_label`)، والمتحكّمُ
       لا يرسله؛ يُكمَّل هنا باستعلامٍ واحدٍ للعقد نفسِها. */
    $cascadeNodes = collect($cascadeBranches ?? []);
    $cascadeModels = $cascadeNodes->isEmpty()
        ? collect()
        : \App\Models\Branch::whereIn('id', $cascadeNodes->pluck('id')->all())
            ->get(['id', 'name', 'parent_id', 'is_territory', 'node_type'])->keyBy('id');
    $cascadeForJs = $cascadeNodes
        ->map(fn ($b) => (array) $b + ['type_label' => optional($cascadeModels->get(((array) $b)['id']))->displayTypeLabel() ?? ''])
        ->values()->all();

    /* ─────────── المؤشّرات ─────────── */
    $tNet = (float) ($statistics['total_collections'] ?? 0);
    $tCount = (int) ($statistics['collection_count'] ?? 0);
    $tAverage = (float) ($statistics['average_collection'] ?? 0);
    $tClosing = (float) ($statistics['closing_balance'] ?? 0);
    $inflows = $collections->where('is_return', false)->where('is_cancellation', false);
    $returnRows = $collections->where('is_return', true);
    $tGross = (float) $inflows->sum('amount');
    $tReturns = abs((float) $returnRows->sum('amount'));
    // السندُ الملغى وقيدُ إلغائه مستبعدان معاً في المتحكّم — لا صفوفَ إلغاء هنا
    $tRefundsCount = (int) ($totals['refunds_count'] ?? $returnRows->count());

    /* ─────────── الرسمان ─────────── */
    $groupIsTime = in_array($groupBy, ['day', 'month'], true);
    $chartTitle = $groupIsTime
        ? 'المدفوعات حسب '.$groupLabel
        : 'الأعلى مدفوعاتٍ — '.$groupLabel;
    $chartSource = $groupIsTime
        ? $reportRows->sortKeys()->take(-($groupBy === 'day' ? 31 : 24))
        : $reportRows->take(10);
    $chartData = [
        'labels' => $chartSource->map(fn ($r) => (string) ($r['name'] ?: 'غير محدد'))->values()->all(),
        'values' => $chartSource->map(fn ($r) => round((float) ($r['amount'] ?? 0), 2))->values()->all(),
    ];

    $typeColors = ['خزينة' => '#10b981', 'بنك' => '#3b82f6'];
    $accountLegend = $inflows->groupBy(fn ($c) => $c['account_type'] ?? 'غير محدد')
        ->map(fn ($rows, $type) => [
            'label' => $type,
            'value' => round((float) $rows->sum('amount'), 2),
            'color' => $typeColors[$type] ?? '#64748b',
            'pct' => $tGross > 0.005 ? round($rows->sum('amount') / $tGross * 100) : null,
            'donut' => true,
        ])
        ->sortByDesc('value')->values()->all();
    $accountLegend[] = ['label' => 'مرتجعات مصروفة', 'value' => $tReturns, 'color' => '#ef4444', 'pct' => $tGross > 0.005 ? round($tReturns / $tGross * 100) : null, 'donut' => false];
    $accountDonut = array_values(array_filter($accountLegend, fn ($s) => $s['donut'] && $s['value'] > 0.005));

    $noDataText = 'لا توجد مدفوعات في الفترة '.$periodLabel;
@endphp

<div class="rpt">

    @include('sales::sals_report.report_partials.print-header', [
        'documentTitle' => $screen['title'].' — '.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view')),
        'periodLabel' => $periodLabel,
        'filters' => [
            'التجميع' => $groupLabel,
            'العميل' => $selectedClientName,
            'الموظف' => $selectedEmployee,
            'الفرع' => $selectedBranchName,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => $screen['icon'],
            'headTitle' => $screen['heading'],
            'headSubtitle' => $screen['subtitle'],
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

                {{-- البحث الهرمي بالشجرة — تُبنى قوائمه ديناميكياً في `report_partials.scripts` --}}
                <input type="hidden" name="cascade_node" id="cascade_node" value="{{ request('cascade_node') }}">

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="من تاريخ"
                                   value="{{ $startDate->format('Y-m-d') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="إلى تاريخ"
                                   value="{{ $endDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    {{-- «الفرع» يُختار من الشجرة وحدَها — كانت قائمةٌ مسطّحةٌ ثانيةٌ تنازعها --}}

                    <div class="rpt-field">
                        <label for="employee_id">الموظف</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="client_id">العميل</label>
                        <select name="client_id" id="client_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>{{ $client->trade_name ?? $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="group_by">تجميع حسب</label>
                        <select name="group_by" id="group_by" class="form-control rpt-select2">
                            @foreach ($groupByOptions as $key => $label)
                                <option value="{{ $key }}" @selected($groupBy == $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row" id="cascadeContainer"></div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="btn-group ag-view-toggle" role="group" aria-label="نوع العرض">
                        <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-list"></i> ملخص
                        </button>
                        <button type="submit" name="view_type" value="details" class="btn {{ ! $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-th-list"></i> تفاصيل
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

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي المدفوعات</div>
                <div class="ag-kpi__value"><bdi class="{{ $tNet < -0.005 ? 'rpt-neg' : '' }}">{{ $signed($tNet) }}</bdi></div>
                <div class="ag-kpi__sub">صافٍ بعد المرتجعات المصروفة · {{ $currency }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">عدد سندات التحصيل</div>
                <div class="ag-kpi__value"><bdi>{{ $tCount }}</bdi></div>
                <div class="ag-kpi__sub">{{ $reportRows->count() }} {{ $groupUnit }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-receipt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">متوسط الدفع</div>
                <div class="ag-kpi__value"><bdi>{{ $signed($tAverage) }}</bdi></div>
                <div class="ag-kpi__sub">الصافي ÷ عدد سندات التحصيل</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">الرصيد النهائي</div>
                <div class="ag-kpi__value"><bdi class="{{ $tClosing < -0.005 ? 'rpt-neg' : '' }}">{{ $signed($tClosing) }}</bdi></div>
                <div class="ag-kpi__sub">الافتتاحي {{ $money($statistics['opening_balance'] ?? 0) }} + المدفوعات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المرتجعات المصروفة</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tReturns) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $tRefundsCount }} صرف نقدي · لا تُعدّ في عدد السندات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
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
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> {{ $noDataText }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع المدفوعات على الخزينة والبنك</h4>
            </div>
            <div class="rpt-card__body">
                @if ($collections->isNotEmpty())
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($accountLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $slice['donut'] ? $signed($slice['value']) : ($slice['value'] > 0.005 ? '('.$money($slice['value']).')' : '—') }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($accountDonut))
                            <div class="ag-donut">
                                <canvas id="accountDonut" aria-label="توزيع المدفوعات على الخزينة والبنك" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $signed($tNet) }}</strong>
                                    <small>صافي المدفوعات · {{ $currency }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> {{ $noDataText }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i>
                {{ $isSummary ? 'ملخص المدفوعات حسب '.$groupLabel : 'تفاصيل المدفوعات حسب '.$groupLabel }}
                <span class="rpt-pill rpt-pill-secondary">{{ $reportRows->count() }} {{ $groupUnit }}</span>
                <span class="rpt-pill rpt-pill-primary">سندات التحصيل: {{ $totals['count'] ?? 0 }}</span>
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
                            <th>عدد سندات التحصيل</th>
                            <th>إجمالي المبلغ</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportRows as $groupKey => $data)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">{!! $clientLink($isClientGroup && is_numeric($groupKey) ? $groupKey : null, $data['name'], 'غير محدد') !!}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $data['count'] }}</span>@if (! empty($data['refunds_count'])) <small class="text-muted">+ {{ $data['refunds_count'] }} صرف مرتجع</small>@endif</td>
                                <td class="rpt-num {{ $signClass($data['amount'] ?? 0) }}"><strong>{{ $signed((float) ($data['amount'] ?? 0)) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="rpt-empty">
                                <i class="fas fa-inbox"></i> {{ $noDataText }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($reportRows->isNotEmpty())
                        <tfoot>
                            <tr class="ag-grand">
                                <td colspan="2">الإجمالي العام</td>
                                <td>{{ $totals['count'] }}@if ($tRefundsCount) <small>+ {{ $tRefundsCount }} صرف مرتجع</small>@endif</td>
                                <td class="rpt-num {{ $signClass($totals['amount'] ?? 0) }}">{{ $signed((float) ($totals['amount'] ?? 0)) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>التاريخ</th>
                            <th>الموظف</th>
                            <th>العميل</th>
                            <th>نوع الحساب</th>
                            <th>الخزينة/البنك</th>
                            <th>الفرع</th>
                            <th>الملاحظات</th>
                            <th>المبلغ</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @php $globalRowNum = 0; @endphp
                        @forelse ($reportRows as $groupKey => $data)
                            @php $rowKey = 'g'.$loop->index; @endphp
                            <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                                <td colspan="9" class="rpt-name">
                                    <i class="fas fa-{{ $groupIcon }}"></i>
                                    <strong>{!! $clientLink($isClientGroup && is_numeric($groupKey) ? $groupKey : null, $data['name'], 'غير محدد') !!}</strong>
                                    <small class="text-muted">— {{ $groupLabel }}</small>
                                    <span class="rpt-pill rpt-pill-info">{{ $data['count'] }} سند</span>@if (! empty($data['refunds_count']))<span class="rpt-pill rpt-pill-danger">{{ $data['refunds_count'] }} صرف مرتجع</span>@endif
                                </td>
                            </tr>
                            @foreach (($data['items'] ?? []) as $collection)
                                @php
                                    $globalRowNum++;
                                    $rowAmount = (float) ($collection['amount'] ?? 0);
                                @endphp
                                <tr data-parent-row="{{ $rowKey }}">
                                    <td>{{ $globalRowNum }}</td>
                                    <td class="rpt-num">{{ \Carbon\Carbon::parse($collection['date'])->format('d/m/Y') }}</td>
                                    <td><span class="rpt-pill rpt-pill-primary">{{ $collection['employee_name'] }}</span></td>
                                    <td class="rpt-name">{!! $clientLink($collection['client_id'] ?? null, $collection['client_name'] ?? '') !!}</td>
                                    <td>
                                        <span class="rpt-pill rpt-pill-{{ $collection['account_type'] === 'خزينة' ? 'success' : 'info' }}">{{ $collection['account_type'] }}</span>
                                    </td>
                                    <td>{{ $collection['treasury_account'] }}</td>
                                    <td>{{ $collection['branch_name'] }}</td>
                                    <td class="ag-note" title="{{ $collection['note'] ?? '' }}">{{ Str::limit($collection['note'] ?? '-', 30) }}</td>
                                    <td class="rpt-num {{ $rowAmount < 0 ? 'rpt-neg' : 'rpt-pos' }}"><strong>{{ $signed($rowAmount) }}</strong></td>
                                </tr>
                            @endforeach
                            <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                                <td colspan="8">إجمالي {{ $data['name'] ?: 'غير محدد' }}</td>
                                <td class="rpt-num {{ $signClass($data['amount'] ?? 0) }}">{{ $signed((float) ($data['amount'] ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty">
                                <i class="fas fa-inbox"></i> {{ $noDataText }}
                                <small class="d-block">يرجى تحديد معايير البحث والضغط على «عرض التقرير»</small>
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($reportRows->isNotEmpty())
                        <tfoot>
                            <tr class="ag-grand">
                                <td colspan="8">الإجمالي العام</td>
                                <td class="rpt-num {{ $signClass($totals['amount'] ?? 0) }}">{{ $signed((float) ($totals['amount'] ?? 0)) }}</td>
                            </tr>
                        </tfoot>
                    @endif
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
    @include('sales::sals_report.report_partials.scripts', ['cascadeBranches' => $cascadeForJs])

    <script>
        (function () {
            const GROUPS_CHART = @json($chartData);
            const ACCOUNT_DONUT = @json($accountDonut);
            const GROUP_IS_TIME = @json($groupIsTime);

            $(function () {
                const bar = document.getElementById('groupsChart');
                if (bar && typeof Chart !== 'undefined' && GROUPS_CHART.labels.length) {
                    const theme = agChartTheme();
                    new Chart(bar.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: GROUPS_CHART.labels,
                            datasets: [{ data: GROUPS_CHART.values, backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 48 }]
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

                const donut = document.getElementById('accountDonut');
                if (donut && typeof Chart !== 'undefined' && ACCOUNT_DONUT.length) {
                    const theme = agChartTheme();
                    new Chart(donut, {
                        type: 'doughnut',
                        data: {
                            labels: ACCOUNT_DONUT.map(function (s) { return s.label; }),
                            datasets: [{
                                data: ACCOUNT_DONUT.map(function (s) { return s.value; }),
                                backgroundColor: ACCOUNT_DONUT.map(function (s) { return s.color; }),
                                borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) {
                                    const s = ACCOUNT_DONUT[c.dataIndex];
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
        // ما يُصدَّر هو ما يُرى على الشاشة — يُبنى في الخادم من صفوف التقرير لا من الـ DOM
        if ($isSummary) {
            $excelHeaders = ['#', $groupLabel, 'عدد سندات التحصيل', 'إجمالي المبلغ'];
            $excelWidths = [6, 40, 20, 25];
            $excelRows = $reportRows->values()->map(fn ($r, $i) => [
                'values' => [$i + 1, $r['name'] ?: 'غير محدد', $r['count'] ?? 0, round((float) ($r['amount'] ?? 0), 2)],
                'alignments' => [null, 'right'],
            ])->all();
            $excelSummary = $excelRows ? [[
                'values' => ['', 'الإجمالي العام', $totals['count'] ?? 0, round((float) ($totals['amount'] ?? 0), 2)],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = ['#', 'التاريخ', 'الموظف', 'العميل', 'نوع الحساب', 'الخزينة/البنك', 'الفرع', 'الملاحظات', 'المبلغ'];
            $excelWidths = [6, 12, 16, 20, 12, 16, 14, 24, 14];
            $excelRows = [];
            $n = 0;
            foreach ($reportRows as $r) {
                /* صفُّ عنوان المجموعة: الدمجُ من عمود الاسم (الثاني) — الدمجُ
                   من الأوّل كان يُبقي قيمتَه الفارغة ويُسقط الاسم */
                $excelRows[] = [
                    'values' => ['', ($r['name'] ?: 'غير محدد').' ('.($r['count'] ?? 0).')', '', '', '', '', '', '', ''],
                    'mergeFrom' => 2, 'mergeTo' => 9,
                    'alignments' => [null, 'right'],
                    'cellStyles' => [null, ['bold' => true, 'color' => 'primary', 'background' => 'grayBg']],
                ];
                foreach (($r['items'] ?? []) as $c) {
                    $excelRows[] = [
                        'values' => [
                            ++$n,
                            \Carbon\Carbon::parse($c['date'])->format('d/m/Y'),
                            $c['employee_name'] ?? '',
                            $c['client_name'] ?? '',
                            $c['account_type'] ?? '',
                            $c['treasury_account'] ?? '',
                            $c['branch_name'] ?? '',
                            $c['note'] ?? '',
                            round((float) ($c['amount'] ?? 0), 2),
                        ],
                        'alignments' => [null, null, 'right', 'right', null, 'right', 'right', 'right'],
                    ];
                }
                $excelRows[] = [
                    'values' => ['', 'إجمالي '.($r['name'] ?: 'غير محدد'), '', '', '', '', '', '', round((float) ($r['amount'] ?? 0), 2)],
                    'mergeFrom' => 2, 'mergeTo' => 8,
                    'alignments' => [null, 'right'],
                    'cellStyles' => [null, ['bold' => true], null, null, null, null, null, null, ['bold' => true, 'color' => 'success']],
                ];
            }
            $excelSummary = $excelRows ? [[
                'values' => ['', 'الإجمالي العام', '', '', '', '', '', '', round((float) ($totals['amount'] ?? 0), 2)],
                'mergeFrom' => 2, 'mergeTo' => 8,
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    @include('sales::sals_report.report_partials.excel', [
        'fileName' => $screen['exportName'].'_'.($isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details')),
        'mainTitle' => $screen['title'].' حسب '.$groupLabel,
        'info' => [
            'الفترة' => $periodLabel,
            'التجميع' => $groupLabel,
            'نوع العرض' => $isSummary ? __('sales_reports.common.summary_view') : __('sales_reports.common.details_view'),
            'العميل' => $selectedClientName ?: 'الكل',
            'الموظف' => $selectedEmployee ?: 'الكل',
            'الفرع' => $selectedBranchName ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'إجمالي المدفوعات', 'value' => $money($tNet), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'عدد سندات التحصيل', 'value' => $tCount, 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'متوسط الدفع', 'value' => $money($tAverage), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'المرتجعات المصروفة', 'value' => $money($tReturns), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
        ],
        'headers' => $excelHeaders,
        'widths' => $excelWidths,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
