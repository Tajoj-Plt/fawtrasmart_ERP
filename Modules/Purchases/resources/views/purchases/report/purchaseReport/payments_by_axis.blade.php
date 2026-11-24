@extends('purchases::master')

{{--
    مدفوعات الموردين — حسب المورّد أو حسب الموظف، من ملفٍّ واحد — على تصميم
    تقارير العملاء المعتمد (`ag-*`): ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة
    فلترةٍ واحدة، مؤشّراتٌ بأيقونات، رسمان (الأعلى مدفوعاتٍ + مصادر الصرف)،
    ثمّ جدولٌ واحدٌ بترقيم صفحات.

    محورٌ واحد `$axis` يقلب العنوان والعمود الأوّل والمنسدلة الأولى؛
    والأرقام كلّها من `buildPaymentReport` كما هي — فإجمالي الشاشتين واحدٌ
    لأن مصدرهما واحد.

    ما لم يتغيّر: الفلاترُ وأسماؤها (ومنها «الحد الأدنى للسند»)، والفتراتُ
    من `ReportPeriod` بمفاتيحها، وزرّا «ملخص/كشف تفصيلي» يُرسلان `view_type`،
    وأعمدةُ الجدولين وذيلاهما، والتصديرُ يُبنى في الخادم ممّا يُرى (`partials.excel`)
    والطباعةُ تطبع الصفحة.

    الكشفُ التفصيلي صار جدولاً واحداً: صفُّ عنوانٍ لكلّ مورّد/موظف ثمّ
    سنداتُه ثمّ إجماليُّها — والترقيمُ بالمجموعة.

    @param string $axis  `supplier` أو `employee`
--}}

@section('title')
    {{ ($axis ?? 'supplier') === 'supplier' ? 'تقرير المدفوعات حسب المورد' : 'تقرير المدفوعات حسب الموظف' }}
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt-row-payment td { background: rgba(40, 199, 111, .05); }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;
    use Modules\Purchases\Support\ReportPeriod;

    $isSupplier = ($axis ?? 'supplier') === 'supplier';

    /*
     * كل ما يختلف بين المحورين في مصفوفةٍ واحدة — فلا يتناثر `@if($axis)`
     * في القالب، ومن أضاف محوراً ثالثاً يضيف صفّاً هنا.
     */
    $cfg = $isSupplier ? [
        'route' => 'ReportsPurchases.supplierPayments',
        'icon' => 'money-bill-wave',
        'title' => 'المدفوعات حسب المورد',
        'docTitle' => 'تقرير المدفوعات حسب المورد',
        'subtitle' => 'ما صُرف لكل مورّد خلال الفترة — من سندات الصرف المرحَّلة على حسابات الموردين: لمن صُرف؟',
        'guideKey' => 'purchases-reports.payments-by-supplier',
        'column' => 'المورد',
        'entity' => 'مورد',
        'entities' => 'الموردون',
        'entityIcon' => 'truck',
        'countKey' => 'suppliers_count',
        'otherColumn' => 'الموظفون',
        'otherKey' => 'employees_count',
        'otherItemColumn' => 'الموظف',
        'otherItemKey' => 'employee_name',
        'fileName' => 'المدفوعات_حسب_المورد',
        'idParam' => 'supplier_id',
        'storage' => 'agPurchasePaymentsBySupplierPageSize',
    ] : [
        'route' => 'ReportsPurchases.employeeSupplierPayments',
        'icon' => 'user-tie',
        'title' => 'المدفوعات حسب الموظف',
        'docTitle' => 'تقرير المدفوعات حسب الموظف',
        'subtitle' => 'ما صرفه كل موظّف للموردين خلال الفترة — من سندات الصرف المرحَّلة على حسابات الموردين: من صرف؟',
        'guideKey' => 'purchases-reports.payments-by-employee',
        'column' => 'الموظف',
        'entity' => 'موظف',
        'entities' => 'الموظفون',
        'entityIcon' => 'users',
        'countKey' => 'employees_count',
        'otherColumn' => 'الموردون',
        'otherKey' => 'suppliers_count',
        'otherItemColumn' => 'المورد',
        'otherItemKey' => 'supplier_name',
        'fileName' => 'المدفوعات_حسب_الموظف',
        'idParam' => 'employee_id',
        'storage' => 'agPurchasePaymentsByEmployeePageSize',
    ];

    $q = request()->except(['page']);
    $viewType = request('view_type', 'summary') === 'details' ? 'details' : 'summary';
    $isSummary = $viewType === 'summary';

    $money = fn ($v) => CurrencySettings::number($v);
    $share = fn ($v) => $totals['total'] > 0.009 ? round($v / $totals['total'] * 100, 1) : 0;
    $currency = CurrencySettings::name();
    $periodLabel = $period->labelWithDates();

    $selectedSupplier = request('supplier_id') ? optional($suppliers->firstWhere('id', (int) request('supplier_id')))->trade_name : null;
    $selectedEmployee = request('employee_id') ? optional($employees->firstWhere('id', (int) request('employee_id')))->name : null;
    $selectedBranch = $selectedNodeLabel ?? null;
    $minAmount = request()->filled('min_amount') ? $money((float) request('min_amount')) : null;

    $printFilters = [
        'المورد' => $selectedSupplier,
        'الموظف' => $selectedEmployee,
        'تابع لـ' => $selectedBranch,
        'الحد الأدنى للسند' => $minAmount,
    ];

    // مصادر الصرف — خزينةٌ أو بنك — تُقرأ من الطرف الدائن لكل سند
    $sources = collect($bySource ?? []);
    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];

    /* ─────────── الرسمان ─────────── */
    $topSource = $grouped->sortByDesc('total')->take(10);
    $topData = [
        'labels' => $topSource->map(fn ($r) => (string) $r['name'])->values()->all(),
        'values' => $topSource->map(fn ($r) => round((float) $r['total'], 2))->values()->all(),
    ];
    $sourceLegend = $sources->values()->map(fn ($s, $i) => [
        'label' => (string) $s['label'],
        'value' => round((float) $s['amount'], 2),
        'color' => ['#3b82f6', '#10b981', '#eab308', '#f97316', '#ef4444', '#8b5cf6', '#06b6d4', '#9f1239', '#64748b', '#84cc16'][$i % 10],
        'pct' => $share($s['amount']),
    ])->all();
    $sourceDonut = array_values(array_filter($sourceLegend, fn ($s) => $s['value'] > 0.005));

    $guide = [
        'title' => 'ما الذي يقيسه هذا التقرير؟',
        'lead' => $cfg['subtitle'],
        'blocks' => [
            'المصدر' => 'سنداتُ الصرف المرحَّلة على حسابات الموردين في الفترة — لا جدولُ المدفوعات ولا حالةُ الفاتورة.',
            'مصادر الصرف' => 'الطرفُ الدائن لكلّ سند: خزينةٌ أو بنك؛ مجموعُها هو إجمالي المدفوعات نفسُه.',
            'المستحق للموردين' => 'رصيدُ حسابات الموردين الآن — سياقٌ لرقم المدفوعات لا جزءٌ منه.',
        ],
        'facts' => ['«الحد الأدنى للسند» يُسقط السندات الأصغر منه من الصفوف والإجماليات معاً.', 'تاريخان صريحان يتقدّمان على زرّ الفترة.'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => $cfg['docTitle'].' — '.($isSummary ? 'ملخص' : 'كشف تفصيلي'),
        'periodLabel' => $periodLabel,
        'filters' => $printFilters,
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => $cfg['icon'],
            'headTitle' => $cfg['title'],
            'headSubtitle' => $cfg['subtitle'],
            'headPeriod' => $periodLabel,
            'headExtra' => $isSummary ? 'ملخص' : 'كشف تفصيلي',
            'headGuideKey' => $cfg['guideKey'],
            'headGuide' => $guide,
            'headCrumbLabel' => 'تقارير المشتريات',
            'headCrumbUrl' => route('ReportsPurchases.index'),
        ])
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route($cfg['route']) }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period->key }}">
                <input type="hidden" name="view_type" value="{{ $viewType }}">

                @include('client::customers.CustomerReport.partials.report-period-tabs', [
                    'tabPeriods' => $tabPeriods,
                    'tabCurrent' => $period->key,
                ])

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="من تاريخ"
                                   value="{{ request('date_from', $fromDate->format('Y-m-d')) }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="إلى تاريخ"
                                   value="{{ request('date_to', $toDate->format('Y-m-d')) }}">
                        </div>
                    </div>

                    {{-- منسدلة المحور أوّلاً، ثم المحور الآخر — الفلتران يعملان في الشاشتين --}}
                    @foreach ($isSupplier ? ['supplier', 'employee'] : ['employee', 'supplier'] as $axisField)
                        @if ($axisField === 'supplier')
                            <div class="rpt-field">
                                <label for="supplier_id">المورد</label>
                                <select name="supplier_id" id="supplier_id" class="form-control rpt-select2">
                                    <option value="">جميع الموردين</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->trade_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="rpt-field">
                                <label for="employee_id">الموظف</label>
                                <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                                    <option value="">جميع الموظفين</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @endforeach

                    <div class="rpt-field">
                        <label for="min_amount">الحد الأدنى للسند</label>
                        <input type="number" name="min_amount" id="min_amount" class="form-control" min="0" step="0.01"
                               placeholder="0.00" value="{{ request('min_amount') }}">
                    </div>

                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="btn-group ag-view-toggle" role="group" aria-label="طريقة العرض">
                        <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-list"></i> ملخص
                        </button>
                        <button type="submit" name="view_type" value="details" class="btn {{ ! $isSummary ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-th-list"></i> كشف تفصيلي
                        </button>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route($cfg['route']) }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
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
                <div class="ag-kpi__value"><bdi>{{ $money($totals['total']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ $totals['count'] }} سند صرف في الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">{{ $cfg['entities'] }}</div>
                <div class="ag-kpi__value"><bdi>{{ $totals[$cfg['countKey']] }}</bdi></div>
                <div class="ag-kpi__sub">{{ $cfg['otherColumn'] }}: {{ $totals[$cfg['otherKey']] }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-{{ $cfg['entityIcon'] }}"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">متوسط السند</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['average']) }}</bdi></div>
                <div class="ag-kpi__sub">الإجمالي ÷ عدد السندات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-calculator"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">أكبر سند</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['largest']) }}</bdi></div>
                <div class="ag-kpi__sub">أعلى مبلغ صُرف بسندٍ واحد</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-arrow-up"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المستحق للموردين</div>
                <div class="ag-kpi__value"><bdi class="{{ $outstanding > 0.009 ? 'rpt-neg' : '' }}">{{ $money($outstanding) }}</bdi></div>
                <div class="ag-kpi__sub">رصيد حسابات الموردين الآن — سياقُ رقم المدفوعات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-award"></i> أعلى {{ $cfg['entities'] }} مدفوعاتٍ</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($topData['labels']))
                    <div class="ag-canvas"><canvas id="topChart" aria-label="أعلى {{ $cfg['entities'] }} مدفوعاتٍ" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا توجد سندات صرف للموردين في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> مصادر الصرف</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($sourceLegend))
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($sourceLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($sourceDonut))
                            <div class="ag-donut">
                                <canvas id="sourceDonut" aria-label="مصادر الصرف" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $money($totals['total']) }}</strong>
                                    <small>إجمالي المدفوعات · {{ $currency }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا توجد سندات صرف للموردين في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i>
                {{ $isSummary ? 'المدفوعات حسب '.$cfg['column'] : 'الكشف التفصيلي' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $grouped->count() }} {{ $cfg['entity'] }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll">
            @if ($isSummary)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>{{ $cfg['column'] }}</th>
                            <th>السندات</th>
                            <th>إجمالي المدفوعات</th>
                            <th>النسبة</th>
                            <th>متوسط السند</th>
                            <th>أكبر سند</th>
                            <th>أول سند</th>
                            <th>آخر سند</th>
                            <th>{{ $cfg['otherColumn'] }}</th>
                            <th class="ag-extra" style="width:60px">كشف</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($grouped as $row)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">
                                    {{ $row['name'] }}
                                    @if ($isSupplier && filled($row['code']))
                                        <small>كود: {{ $row['code'] }}</small>
                                    @endif
                                </td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['count'] }}</span></td>
                                <td class="rpt-num rpt-pos"><strong>{{ $money($row['total']) }}</strong></td>
                                <td class="rpt-num">{{ $share($row['total']) }}%</td>
                                <td class="rpt-num">{{ $money($row['average']) }}</td>
                                <td class="rpt-num">{{ $money($row['largest']) }}</td>
                                <td class="rpt-num">{{ $row['first_date'] ? \Carbon\Carbon::parse($row['first_date'])->format('d/m/Y') : '—' }}</td>
                                <td class="rpt-num">{{ $row['last_date'] ? \Carbon\Carbon::parse($row['last_date'])->format('d/m/Y') : '—' }}</td>
                                <td><span class="rpt-pill rpt-pill-primary">{{ $row[$cfg['otherKey']] }}</span></td>
                                <td class="ag-extra">
                                    <a class="ag-actions-btn" title="كشف سندات {{ $cfg['column'] }}" aria-label="كشف سندات {{ $cfg['column'] }}"
                                       href="{{ route($cfg['route'], array_merge($q, ['view_type' => 'details', $cfg['idParam'] => $row['id']])) }}">
                                        <i class="fas fa-th-list"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد سندات صرف للموردين في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($grouped->count())
                        <tfoot>
                            <tr>
                                <td colspan="2">الإجمالي العام</td>
                                <td>{{ $totals['count'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['total']) }}</td>
                                <td class="rpt-num">100%</td>
                                <td class="rpt-num">{{ $money($totals['average']) }}</td>
                                <td class="rpt-num">{{ $money($totals['largest']) }}</td>
                                <td colspan="2"></td>
                                <td>{{ $totals[$cfg['otherKey']] }}</td>
                                <td class="ag-extra"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                {{-- ===================== الكشف التفصيلي: جدولٌ واحد بمجموعات ===================== --}}
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:100px">التاريخ</th>
                            <th>رقم السند</th>
                            <th>النوع</th>
                            <th>{{ $cfg['otherItemColumn'] }}</th>
                            <th>تابع لـ</th>
                            <th>مصدر الصرف</th>
                            <th>المبلغ</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($grouped as $row)
                            @php $rowKey = 'g'.$loop->index; @endphp
                            <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                                <td colspan="8" class="rpt-name">
                                    <i class="fas fa-{{ $isSupplier ? 'truck' : 'user-tie' }}"></i>
                                    <strong>{{ $row['name'] }}</strong>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $row['count'] }} سند</span>
                                    <span class="rpt-pill rpt-pill-primary">{{ $row[$cfg['otherKey']] }} {{ $isSupplier ? 'موظف' : 'مورد' }}</span>
                                    <span class="rpt-pill rpt-pill-success">إجمالي {{ $money($row['total']) }} · {{ $share($row['total']) }}%</span>
                                    <span class="rpt-pill rpt-pill-info">متوسط السند {{ $money($row['average']) }}</span>
                                    <span class="rpt-pill rpt-pill-warning">أكبر سند {{ $money($row['largest']) }}</span>
                                </td>
                            </tr>
                            @foreach ($row['items'] as $i => $item)
                                <tr class="rpt-row-payment" data-parent-row="{{ $rowKey }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td class="rpt-num">{{ $item['date'] }}</td>
                                    <td class="rpt-name">
                                        {{ $item['reference_number'] }}
                                        @if (filled($item['description']))
                                            <small>{{ \Illuminate\Support\Str::limit($item['description'], 60) }}</small>
                                        @endif
                                    </td>
                                    <td><span class="rpt-pill rpt-pill-info">{{ $item['type_name'] }}</span></td>
                                    <td class="rpt-name">{{ $item[$cfg['otherItemKey']] }}</td>
                                    <td>{{ $item['branch_name'] }}</td>
                                    <td>{{ $item['source'] }}</td>
                                    <td class="rpt-num rpt-pos"><strong>{{ $money($item['amount']) }}</strong></td>
                                </tr>
                            @endforeach
                            <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                                <td colspan="7">إجمالي سندات الفترة — {{ $row['name'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($row['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد سندات صرف للموردين في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($grouped->count())
                        <tfoot>
                            <tr>
                                <td colspan="7">الإجمالي العام — {{ $totals['count'] }} سند</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['total']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $cfg['entity'],
            'pagerStorage' => $cfg['storage'],
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')

    <script>
        (function () {
            const TOP = @json($topData);
            const DONUT = @json($sourceDonut);

            $(function () {
                if ($.fn.select2) {
                    $('.rpt-select2').each(function () {
                        if ($(this).data('select2')) { $(this).select2('destroy'); }
                    });
                    $('.rpt-select2').select2({ dir: 'rtl', width: '100%', allowClear: true, language: { noResults: function () { return 'لا توجد نتائج'; } } });
                }

                $('.period-btn').on('click', function () {
                    var key = String($(this).data('period'));
                    if (key === 'custom') { $('#date_from').trigger('focus'); return; }
                    $('#periodInput').val(key);
                    $('#date_from, #date_to').val('');
                    $('#filterForm').trigger('submit');
                });

                $('#printBtn').on('click', function () { window.print(); });

                if (typeof Chart === 'undefined') return;
                const theme = agChartTheme();

                const topEl = document.getElementById('topChart');
                if (topEl && TOP.labels.length) {
                    new Chart(topEl.getContext('2d'), {
                        type: 'bar',
                        data: { labels: TOP.labels, datasets: [{ data: TOP.values, backgroundColor: agPalette.slice(0, TOP.labels.length), borderRadius: 6, maxBarThickness: 48 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agMoney(c.parsed.y); } } } },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 0, autoSkip: false,
                                     callback: function (v) { const l = String(this.getLabelForValue(v)); return l.length > 14 ? l.slice(0, 13) + '…' : l; } } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        },
                        plugins: [agValueLabels(TOP.values)]
                    });
                }

                const donutEl = document.getElementById('sourceDonut');
                if (donutEl && DONUT.length) {
                    new Chart(donutEl, {
                        type: 'doughnut',
                        data: {
                            labels: DONUT.map(function (s) { return s.label; }),
                            datasets: [{ data: DONUT.map(function (s) { return s.value; }), backgroundColor: DONUT.map(function (s) { return s.color; }), borderColor: theme.surface, borderWidth: 3, hoverOffset: 6 }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: { label: function (c) { const s = DONUT[c.dataIndex]; return ' ' + agMoney(s.value) + ' (' + s.pct + '%)'; } } }
                            }
                        }
                    });
                }
            });
        })();
    </script>

    @php
        // ما يُصدَّر هو ما يُرى: صفوف الملخص أو سندات الكشف حسب العرض الحالي — كلُّها لا الصفحةُ المعروضة
        $excelInfo = array_merge(['الفترة' => $periodLabel], collect($printFilters)->map(fn ($v) => $v ?: 'الكل')->all());
        $excelCards = [
            ['title' => 'إجمالي المدفوعات', 'value' => $money($totals['total']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'عدد السندات', 'value' => $totals['count'], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'عدد '.$cfg['entities'], 'value' => $totals[$cfg['countKey']], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'متوسط السند', 'value' => $money($totals['average']), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningLight', 'borderColor' => 'warning'],
            ['title' => 'المستحق للموردين', 'value' => $money($outstanding), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
        ];

        if ($isSummary) {
            $excelHeaders = ['#', $cfg['column'], 'السندات', 'إجمالي المدفوعات', 'النسبة %', 'متوسط السند', 'أكبر سند', 'أول سند', 'آخر سند', $cfg['otherColumn']];
            $excelWidths = [6, 30, 10, 18, 10, 16, 16, 14, 14, 12];
            $excelRows = $grouped->values()->map(fn ($r, $i) => [
                'values' => [
                    $i + 1, $r['name'], $r['count'], $r['total'], $share($r['total']),
                    $r['average'], $r['largest'], $r['first_date'] ?: '', $r['last_date'] ?: '', $r[$cfg['otherKey']],
                ],
                'alignments' => [null, 'right'],
            ])->all();
            $excelSummary = $grouped->count() ? [[
                'values' => ['', 'الإجمالي العام', $totals['count'], $totals['total'], 100, $totals['average'], $totals['largest'], '', '', $totals[$cfg['otherKey']]],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        } else {
            $excelHeaders = ['#', 'التاريخ', 'رقم السند', 'النوع', $cfg['column'], $cfg['otherItemColumn'], 'تابع لـ', 'مصدر الصرف', 'البيان', 'المبلغ'];
            $excelWidths = [6, 12, 16, 14, 28, 24, 18, 20, 32, 16];
            $excelRows = $payments->values()->map(fn ($p, $i) => [
                'values' => [
                    $i + 1, $p['raw_date'], $p['reference_number'], $p['type_name'],
                    $isSupplier ? $p['supplier_name'] : $p['employee_name'],
                    $isSupplier ? $p['employee_name'] : $p['supplier_name'],
                    $p['branch_name'], $p['source'], (string) ($p['description'] ?? ''), $p['amount'],
                ],
                'alignments' => [null, null, null, null, 'right', 'right'],
            ])->all();
            $excelSummary = $payments->count() ? [[
                'values' => ['', 'الإجمالي العام', '', '', '', '', '', '', '', $totals['total']],
                'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
            ]] : [];
        }
    @endphp

    @include('purchases::purchases.report.partials.excel', [
        'fileName' => $cfg['fileName'],
        'mainTitle' => $cfg['docTitle'].' — '.($isSummary ? 'ملخص' : 'كشف تفصيلي'),
        'info' => $excelInfo,
        'cards' => $excelCards,
        'headers' => $excelHeaders,
        'widths' => $excelWidths,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])
@endsection
