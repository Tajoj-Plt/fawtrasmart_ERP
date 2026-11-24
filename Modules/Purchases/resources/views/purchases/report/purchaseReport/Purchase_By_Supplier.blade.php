{{--
    تقرير المشتريات حسب المورد — على تصميم تقارير العملاء المعتمد (`ag-*`):
    ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات،
    رسمان (الاتجاه + الأعلى شراءً)، ثمّ جدولٌ واحدٌ بترقيم صفحات.

    الصفحة كشفُ حسابٍ لا جدولُ أرقام: تبدأ برصيد أول المدة، ثم حركة الفترة
    مصنَّفةً، وتنتهي برصيد آخر المدة — فترتيبُ المؤشّرات يتبع ترتيب المعادلة.

    ما لم يتغيّر: الفلاترُ وأسماؤها وخانةُ «بلا حركة»، والفتراتُ من
    `ReportPeriod` بمفاتيحها، وزرّا «ملخص/كشف تفصيلي» يُرسلان `view_type`،
    وأعمدةُ الجدولين وذيلاهما، والتصديرُ يُبنى في الخادم من صفوف التقرير
    (`partials.excel`) والطباعةُ تطبع الصفحة بترويسة المنشأة.

    الكشفُ التفصيلي صار جدولاً واحداً: صفُّ عنوانٍ لكلّ مورّد ثمّ رصيدُه
    الافتتاحي فحركتُه فإجماليُّها — والترقيمُ بالمورّد.
--}}
@extends('purchases::master')

@section('title')
    تقرير المشتريات حسب المورد
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-table tbody tr.ag-opening td { font-style: italic; }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt-row-purchase   td { background: rgba(115, 103, 240, .04); }
        .rpt-row-payment    td { background: rgba(40, 199, 111, .05); }
        .rpt-row-debit_note td { background: rgba(255, 159, 67, .06); }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;
    use Modules\Purchases\Support\ReportPeriod;

    $q = request()->except(['page']);
    $sortBy = request('sort_by', 'purchases');
    $sortDir = request('sort_dir', 'desc');
    $viewType = request('view_type', 'summary') === 'details' ? 'details' : 'summary';
    $isSummary = $viewType === 'summary';

    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number($v);
    $signClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-pos' : 'rpt-neg');
    // الرصيد التزام: الموجب دَينٌ علينا فيُلوَّن تحذيراً لا ربحاً
    $balClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-neg' : 'rpt-pos');
    $currency = CurrencySettings::name();
    $periodLabel = $period->labelWithDates();

    $selectedSupplier = request('supplier_id') ? optional($suppliers->firstWhere('id', (int) request('supplier_id')))->trade_name : null;
    $selectedEmployee = request('employee_id') ? optional($users->firstWhere('id', (int) request('employee_id')))->name : null;
    $selectedBranch = $selectedNodeLabel ?? null;
    $statusLabels = ['paid' => 'مسدد', 'partial' => 'جزئي', 'unpaid' => 'غير مسدد', 'net_return' => 'مرتجع صافٍ'];
    $selectedStatus = $statusLabels[request('payment_status')] ?? null;

    $link = function ($col) use ($q, $sortBy, $sortDir) {
        $dir = ($sortBy === $col && $sortDir === 'desc') ? 'asc' : 'desc';
        return route('ReportsPurchases.bySupplier', array_merge($q, ['sort_by' => $col, 'sort_dir' => $dir]));
    };
    $caret = fn ($col) => $sortBy === $col ? ' <i class="fas fa-sort-'.($sortDir === 'asc' ? 'up' : 'down').'"></i>' : '';

    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];
    $kindPill = ['purchase' => 'success', 'return' => 'danger', 'debit_note' => 'warning', 'payment' => 'info', 'other' => 'secondary'];

    /* ─────────── الرسمان ─────────── */
    $trendData = [
        'labels' => array_values($trend['labels'] ?? []),
        'purchases' => array_values($trend['purchases'] ?? []),
        'payments' => array_values($trend['payments'] ?? []),
    ];
    $topSource = $reportData->sortByDesc('purchases')->filter(fn ($r) => $r['purchases'] > 0.005)->take(10);
    $topData = [
        'labels' => $topSource->map(fn ($r) => (string) $r['supplier_name'])->values()->all(),
        'values' => $topSource->map(fn ($r) => round((float) $r['purchases'], 2))->values()->all(),
    ];

    $guide = [
        'title' => 'ما الذي يقيسه هذا التقرير؟',
        'lead' => 'حركةُ كلّ مورّد خلال الفترة — مشترياتٍ ومرتجعاتٍ وإشعاراتٍ ومدفوعات — ورصيدُه أوّلَ المدة وآخرَها، من قيود اليومية المرحَّلة على حسابات الموردين.',
        'blocks' => [
            'معادلة الرصيد' => 'رصيد أول المدة + المشتريات − المرتجعات − الإشعارات المدينة − المدفوعات (± التسويات) = رصيد آخر المدة.',
            'اتجاه الرصيد' => 'الرصيدُ التزامٌ: الموجب مستحقٌّ للمورد علينا، والسالب رصيدٌ لنا عنده.',
            'الكشف التفصيلي' => 'حركةُ المورّد قيداً قيداً: مدين (لنا) ودائن (له) ورصيدٌ متحرّك بعد كلّ قيد.',
        ],
        'facts' => ['«إظهار الموردين بلا حركة» يُدرج من له رصيدٌ ولا قيدَ له في الفترة.', 'تاريخان صريحان يتقدّمان على زرّ الفترة.'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => 'تقرير المشتريات حسب المورد — '.($isSummary ? 'ملخص' : 'كشف تفصيلي'),
        'periodLabel' => $periodLabel,
        'filters' => [
            'المورد' => $selectedSupplier,
            'الموظف' => $selectedEmployee,
            'تابع لـ' => $selectedBranch,
            'الحالة' => $selectedStatus,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'truck-loading',
            'headTitle' => 'المشتريات حسب المورد',
            'headSubtitle' => 'حركة كل مورّد خلال الفترة — مشترياتٍ ومرتجعاتٍ وإشعاراتٍ ومدفوعات، ورصيدُه أوّلَ المدة وآخرَها.',
            'headPeriod' => $periodLabel,
            'headExtra' => $isSummary ? 'ملخص' : 'كشف تفصيلي',
            'headGuideKey' => 'purchases-reports.by-supplier',
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
            <form method="GET" action="{{ route('ReportsPurchases.bySupplier') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period->key }}">
                <input type="hidden" name="view_type" value="{{ $viewType }}">
                <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                <input type="hidden" name="sort_dir" value="{{ $sortDir }}">

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

                    <div class="rpt-field">
                        <label for="supplier_id">المورد</label>
                        <select name="supplier_id" id="supplier_id" class="form-control rpt-select2">
                            <option value="">جميع الموردين</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->trade_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">الموظف</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('employee_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="payment_status">الحالة</label>
                        <select name="payment_status" id="payment_status" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($statusLabels as $code => $label)
                                <option value="{{ $code }}" @selected(request('payment_status') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
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
                    <div class="ag-checks">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="show_zero" name="show_zero" value="1" @checked(request()->boolean('show_zero')) onchange="this.form.submit()">
                            <label class="form-check-label" for="show_zero">إظهار الموردين بلا حركة</label>
                        </div>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.bySupplier') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات (بترتيب المعادلة) ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">الموردون</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['suppliers_count'] }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['entries_count'] }} حركة في الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">رصيد أول المدة</div>
                <div class="ag-kpi__value"><bdi class="{{ $balClass($totals['opening_balance']) }}">{{ $signed($totals['opening_balance']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · ما كان مستحقاً للموردين</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي المشتريات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['purchases']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['purchases_count'] }} فاتورة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المرتجعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['returns']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['returns_count'] }} مرتجع</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">إشعارات مدينة</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['debit_notes']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['debit_notes_count'] }} إشعار</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-signature"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">صافي المشتريات</div>
                <div class="ag-kpi__value"><bdi class="{{ $totals['net_purchases'] < -0.005 ? 'rpt-neg' : '' }}">{{ $signed($totals['net_purchases']) }}</bdi></div>
                <div class="ag-kpi__sub">المشتريات − المرتجعات − الإشعارات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#84cc16">
            <div>
                <div class="ag-kpi__label">المدفوعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['payments']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['payments_count'] }} سند صرف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#9f1239">
            <div>
                <div class="ag-kpi__label">رصيد آخر المدة</div>
                <div class="ag-kpi__value"><bdi class="{{ $balClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</bdi></div>
                <div class="ag-kpi__sub">موجب = مستحق للموردين</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
    </div>

    {{-- ===================== معادلة الرصيد ===================== --}}
    <div class="rpt-equation">
        <div class="rpt-equation__item"><small>رصيد أول المدة</small><strong>{{ $signed($totals['opening_balance']) }}</strong></div>
        <div class="rpt-equation__op">+</div>
        <div class="rpt-equation__item"><small>مشتريات</small><strong class="rpt-pos">{{ $money($totals['purchases']) }}</strong></div>
        <div class="rpt-equation__op">−</div>
        <div class="rpt-equation__item"><small>مرتجعات</small><strong class="rpt-neg">{{ $money($totals['returns']) }}</strong></div>
        <div class="rpt-equation__op">−</div>
        <div class="rpt-equation__item"><small>إشعارات مدينة</small><strong class="rpt-neg">{{ $money($totals['debit_notes']) }}</strong></div>
        <div class="rpt-equation__op">−</div>
        <div class="rpt-equation__item"><small>مدفوعات</small><strong class="rpt-neg">{{ $money($totals['payments']) }}</strong></div>
        @if (abs($totals['adjustments']) > 0.005)
            <div class="rpt-equation__op">+</div>
            <div class="rpt-equation__item"><small>تسويات وأرصدة افتتاحية</small><strong>{{ $signed($totals['adjustments']) }}</strong></div>
        @endif
        <div class="rpt-equation__op">=</div>
        <div class="rpt-equation__item"><small>رصيد آخر المدة</small><strong class="{{ $balClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</strong></div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-area"></i> اتجاه المشتريات والمدفوعات</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($trendData['labels']))
                    <div class="ag-canvas"><canvas id="trendChart" aria-label="اتجاه المشتريات والمدفوعات" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-area"></i> لا توجد حركة موردين في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-award"></i> أعلى الموردين شراءً</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($topData['labels']))
                    <div class="ag-canvas"><canvas id="topChart" aria-label="أعلى الموردين شراءً" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا توجد حركة موردين في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i>
                {{ $isSummary ? 'ملخص الموردين' : 'الكشف التفصيلي' }}
                <span class="rpt-pill rpt-pill-secondary">{{ $reportData->count() }} مورد</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <a href="{{ route('SupplierPurchasesExport', $q) }}" class="btn btn-sm ag-btn-soft" title="تصدير CSV خام"><i class="fas fa-file-csv"></i> CSV</a>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll">
            @if ($isSummary)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th><a href="{{ $link('supplier_name') }}">المورد{!! $caret('supplier_name') !!}</a></th>
                            <th>رصيد أول المدة</th>
                            <th><a href="{{ $link('purchases_count') }}">الفواتير{!! $caret('purchases_count') !!}</a></th>
                            <th><a href="{{ $link('purchases') }}">إجمالي المشتريات{!! $caret('purchases') !!}</a></th>
                            <th><a href="{{ $link('returns') }}">المرتجعات{!! $caret('returns') !!}</a></th>
                            <th>إشعارات مدينة</th>
                            <th><a href="{{ $link('net_purchases') }}">صافي المشتريات{!! $caret('net_purchases') !!}</a></th>
                            <th><a href="{{ $link('payments') }}">المدفوعات{!! $caret('payments') !!}</a></th>
                            <th><a href="{{ $link('balance') }}">رصيد آخر المدة{!! $caret('balance') !!}</a></th>
                            <th>الحالة</th>
                            <th class="ag-extra" style="width:60px">كشف</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $row)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">
                                    {{ $row['supplier_name'] }}
                                    <small>كود: {{ $row['supplier_code'] }}@if ($row['phone']) · {{ $row['phone'] }}@endif</small>
                                </td>
                                <td class="rpt-num {{ $balClass($row['opening_balance']) }}">{{ $signed($row['opening_balance']) }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['purchases_count'] }}</span></td>
                                <td class="rpt-num rpt-pos">{{ $money($row['purchases']) }}</td>
                                <td class="rpt-num {{ $row['returns'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['returns'] > 0 ? '('.$money($row['returns']).')' : '—' }}
                                </td>
                                <td class="rpt-num {{ $row['debit_notes'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['debit_notes'] > 0 ? '('.$money($row['debit_notes']).')' : '—' }}
                                </td>
                                <td class="rpt-num {{ $signClass($row['net_purchases']) }}"><strong>{{ $signed($row['net_purchases']) }}</strong></td>
                                <td class="rpt-num {{ $row['payments'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">
                                    {{ $row['payments'] > 0 ? $money($row['payments']) : '—' }}
                                </td>
                                <td class="rpt-num {{ $balClass($row['balance']) }}">
                                    <strong>{{ $signed($row['balance']) }}</strong>
                                    <small class="d-block" style="font-size:.68rem">{{ $row['balance_state']['text'] }}</small>
                                </td>
                                <td><span class="rpt-pill rpt-pill-{{ $row['status']['class'] }}">{{ $row['status']['text'] }}</span></td>
                                <td class="ag-extra">
                                    <a class="ag-actions-btn" title="كشف حساب المورد" aria-label="كشف حساب المورد"
                                       href="{{ route('ReportsPurchases.bySupplier', array_merge($q, ['view_type' => 'details', 'supplier_id' => $row['supplier_id']])) }}">
                                        <i class="fas fa-stream"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد حركة موردين في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($reportData->count())
                        <tfoot>
                            <tr>
                                <td colspan="2">الإجمالي العام</td>
                                <td class="rpt-num">{{ $signed($totals['opening_balance']) }}</td>
                                <td>{{ $totals['purchases_count'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['purchases']) }}</td>
                                <td class="rpt-num rpt-neg">({{ $money($totals['returns']) }})</td>
                                <td class="rpt-num rpt-neg">({{ $money($totals['debit_notes']) }})</td>
                                <td class="rpt-num {{ $signClass($totals['net_purchases']) }}">{{ $signed($totals['net_purchases']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['payments']) }}</td>
                                <td class="rpt-num {{ $balClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</td>
                                <td></td>
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
                            <th>رقم المستند</th>
                            <th>نوع المستند</th>
                            <th>الموظف</th>
                            <th>تابع لـ</th>
                            <th>مدين (لنا)</th>
                            <th>دائن (له)</th>
                            <th>الرصيد المتحرك</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $row)
                            @php $rowKey = 's'.$loop->index; @endphp
                            <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                                <td colspan="9" class="rpt-name">
                                    <i class="fas fa-truck"></i>
                                    <strong>{{ $row['supplier_name'] }}</strong>
                                    <small class="text-muted">— كود {{ $row['supplier_code'] }}</small>
                                    <span class="rpt-pill rpt-pill-{{ $row['status']['class'] }}">{{ $row['status']['text'] }}</span>
                                    <span class="rpt-pill rpt-pill-{{ $row['balance_state']['class'] }}">{{ $row['balance_state']['text'] }}: {{ $money($row['balance']) }}</span>
                                    <span class="rpt-pill rpt-pill-secondary">مشتريات ({{ $row['purchases_count'] }}) {{ $money($row['purchases']) }}</span>
                                    <span class="rpt-pill rpt-pill-danger">مرتجعات ({{ $row['returns_count'] }}) {{ $money($row['returns']) }}</span>
                                    <span class="rpt-pill rpt-pill-warning">إشعارات ({{ $row['debit_notes_count'] }}) {{ $money($row['debit_notes']) }}</span>
                                    <span class="rpt-pill rpt-pill-info">مدفوعات ({{ $row['payments_count'] }}) {{ $money($row['payments']) }}</span>
                                </td>
                            </tr>
                            <tr class="ag-opening" data-parent-row="{{ $rowKey }}">
                                <td>—</td>
                                <td class="rpt-num">{{ $fromDate->format('d/m/Y') }}</td>
                                <td colspan="4" class="rpt-name" style="font-weight:600">رصيد ما قبل الفترة</td>
                                <td class="rpt-zero">—</td>
                                <td class="rpt-zero">—</td>
                                <td class="rpt-num {{ $balClass($row['opening_balance']) }}"><strong>{{ $signed($row['opening_balance']) }}</strong></td>
                            </tr>
                            @foreach ($row['items'] as $i => $item)
                                <tr class="rpt-row-{{ $item['kind'] }}" data-parent-row="{{ $rowKey }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td class="rpt-num">{{ $item['date'] }}</td>
                                    <td><strong>{{ $item['reference_number'] }}</strong></td>
                                    <td><span class="rpt-pill rpt-pill-{{ $kindPill[$item['kind']] ?? 'secondary' }}">{{ $item['type_name'] }}</span></td>
                                    <td>{{ $item['employee_name'] }}</td>
                                    <td>{{ $item['branch_name'] }}</td>
                                    <td class="rpt-num {{ $item['debit'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $item['debit'] > 0 ? $money($item['debit']) : '—' }}</td>
                                    <td class="rpt-num {{ $item['credit'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ $item['credit'] > 0 ? $money($item['credit']) : '—' }}</td>
                                    <td class="rpt-num {{ $balClass($item['running_balance']) }}">{{ $signed($item['running_balance']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                                <td colspan="6">إجمالي حركة الفترة — {{ $row['supplier_name'] }}</td>
                                <td class="rpt-num rpt-neg">{{ $money(collect($row['items'])->sum('debit')) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money(collect($row['items'])->sum('credit')) }}</td>
                                <td class="rpt-num {{ $balClass($row['balance']) }}">{{ $signed($row['balance']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد حركة موردين في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($reportData->count())
                        <tfoot>
                            <tr>
                                <td colspan="6">الإجمالي العام — {{ $totals['entries_count'] }} حركة</td>
                                <td class="rpt-num rpt-neg">{{ $money($reportData->sum(fn ($r) => collect($r['items'])->sum('debit'))) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($reportData->sum(fn ($r) => collect($r['items'])->sum('credit'))) }}</td>
                                <td class="rpt-num {{ $balClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'مورد',
            'pagerStorage' => 'agPurchasesBySupplierPageSize',
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
            const TREND = @json($trendData);
            const TOP = @json($topData);

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

                const trendEl = document.getElementById('trendChart');
                if (trendEl && TREND.labels.length) {
                    new Chart(trendEl.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: TREND.labels,
                            datasets: [
                                { label: 'المشتريات', data: TREND.purchases, borderColor: theme.brand, backgroundColor: theme.brand + '22', fill: true, tension: .35, pointRadius: 3 },
                                { label: 'المدفوعات', data: TREND.payments, borderColor: '#10b981', backgroundColor: '#10b98122', fill: true, tension: .35, pointRadius: 3 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top', rtl: true, labels: { color: theme.muted, boxWidth: 12 } },
                                tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + agMoney(c.parsed.y); } } }
                            },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 45, autoSkip: true } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        }
                    });
                }

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
            });
        })();
    </script>

    {{-- التصديرُ يُبنى في الخادم من صفوف التقرير — كلُّ الصفوف لا الصفحةُ المعروضة --}}
    @include('purchases::purchases.report.partials.excel', [
        'fileName' => 'المشتريات_حسب_المورد',
        'mainTitle' => 'تقرير المشتريات حسب المورد',
        'info' => [
            'الفترة' => $periodLabel,
            'المورد' => $selectedSupplier ?: 'الكل',
            'الموظف' => $selectedEmployee ?: 'الكل',
            'تابع لـ' => $selectedBranch ?: 'الكل',
            'الحالة' => $selectedStatus ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'عدد الموردين', 'value' => $totals['suppliers_count'], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المشتريات', 'value' => $money($totals['purchases']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المرتجعات', 'value' => $money($totals['returns']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'المدفوعات', 'value' => $money($totals['payments']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'رصيد آخر المدة', 'value' => $money($totals['balance']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
        ],
        'headers' => ['#', 'المورد', 'الكود', 'رصيد أول المدة', 'الفواتير', 'المشتريات', 'المرتجعات', 'إشعارات مدينة', 'صافي المشتريات', 'المدفوعات', 'رصيد آخر المدة', 'الحالة'],
        'widths' => [6, 30, 14, 16, 10, 16, 14, 16, 18, 16, 18, 16],
        'rows' => $reportData->values()->map(fn ($r, $i) => [
            'values' => [
                $i + 1, $r['supplier_name'], (string) $r['supplier_code'],
                $r['opening_balance'], $r['purchases_count'], $r['purchases'],
                $r['returns'], $r['debit_notes'], $r['net_purchases'],
                $r['payments'], $r['balance'], $r['status']['text'],
            ],
            'alignments' => [null, 'right'],
        ])->all(),
        'summaryRows' => $reportData->count() ? [[
            'values' => [
                '', 'الإجمالي العام', '', $totals['opening_balance'], $totals['purchases_count'],
                $totals['purchases'], $totals['returns'], $totals['debit_notes'],
                $totals['net_purchases'], $totals['payments'], $totals['balance'], '',
            ],
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [],
    ])
@endsection
