{{--
    التقرير الموحد للموردين — على تصميم تقارير العملاء المعتمد (`ag-*`):
    ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات،
    رسمان (الأعلى صافياً + حالة السداد)، ثمّ جدولٌ واحدٌ بترقيم صفحات.

    ثلاثة أوجه لبيانات واحدة — `buildReport()` في `SupplierReportController`:
    الملخص (صفٌّ لكل مورّد)، والتفاصيل (كشفٌ لكل مورّد)، والأرصدة (المشتريات
    والمرتجعات والمدفوعات ورصيد آخر المدة). الأرقام هي نفسها التي تقرؤها
    شاشة «المشتريات حسب المورد»، فلا يختلف إجمالٌ هنا عن إجمالٍ هناك.

    التفاصيلُ صارت جدولاً واحداً: صفُّ عنوانٍ لكلّ مورّد ثمّ مستنداتُه ثمّ
    إجماليُّه — والترقيمُ بالمورّد فلا ينقسم كشفُه بين صفحتين. والتصديرُ
    يتبع العرضَ المفتوح كما كان.
--}}
@extends('purchases::master')

@section('title')
    التقرير الموحد للموردين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); text-align: start; }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt-row-purchase   td { background: rgba(115, 103, 240, .04); }
        .rpt-row-return     td { background: rgba(234, 84, 85, .05); }
        .rpt-row-payment    td { background: rgba(40, 199, 111, .05); }
        .rpt-row-debit_note td { background: rgba(255, 159, 67, .06); }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;
    use Modules\Purchases\Support\ReportPeriod;

    $viewType = in_array(request('view_type'), ['summary', 'details', 'balances'], true) ? request('view_type') : 'summary';

    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number($v);
    $signClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-pos' : 'rpt-neg');
    // الرصيد التزام: الموجب دَينٌ علينا فيُلوَّن تحذيراً لا ربحاً
    $balClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-neg' : 'rpt-pos');
    $statusIcon = fn ($code) => match ($code) {
        'paid' => 'check-circle',
        'partial' => 'clock',
        'unpaid' => 'times-circle',
        default => 'info-circle',
    };
    $currency = CurrencySettings::name();

    $selectedSupplier = request('supplier_id') ? optional($suppliers->firstWhere('id', (int) request('supplier_id')))->trade_name : null;
    $selectedEmployee = request('employee_id') ? optional($users->firstWhere('id', (int) request('employee_id')))->name : null;
    $selectedBranch = $selectedNodeLabel ?? null;
    $periodLabel = $period->labelWithDates();

    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];

    $viewTitles = [
        'summary' => 'ملخص المشتريات حسب المورد',
        'details' => 'تفاصيل المشتريات حسب المورد',
        'balances' => 'أرصدة الموردين',
    ];
    $viewIcons = ['summary' => 'list', 'details' => 'th-list', 'balances' => 'money-bill'];
    $viewLabels = ['summary' => 'ملخص', 'details' => 'تفاصيل', 'balances' => 'الأرصدة'];

    $suppliersCount = $reportData->count();
    $kindPill = ['purchase' => 'success', 'return' => 'danger', 'debit_note' => 'warning', 'payment' => 'info', 'other' => 'secondary'];

    /* ─────────── الرسمان ─────────── */
    $topBars = $reportData->sortByDesc('net_amount')->take(10)->values()->map(fn ($r) => [
        'name' => (string) $r['name'],
        'short' => mb_strlen($r['name']) > 16 ? mb_substr($r['name'], 0, 15).'…' : $r['name'],
        'value' => round((float) $r['net_amount'], 2),
    ])->filter(fn ($r) => $r['value'] > 0)->values()->all();

    $tGross = (float) $totals['amount'];
    $tReturns = (float) $totals['returns_amount'];
    $tNet = (float) $totals['net_amount'];
    $tPaid = (float) $totals['paid'];
    $tUnpaid = (float) $totals['unpaid'];
    $payBase = $tPaid + $tUnpaid;
    $payLegend = [
        ['label' => 'المدفوع', 'value' => round($tPaid, 2), 'color' => '#10b981', 'pct' => $payBase > 0 ? round($tPaid / $payBase * 100) : null, 'donut' => true],
        ['label' => 'غير المدفوع', 'value' => round($tUnpaid, 2), 'color' => '#f97316', 'pct' => $payBase > 0 ? round($tUnpaid / $payBase * 100) : null, 'donut' => true],
        ['label' => 'المرتجعات والإشعارات', 'value' => round($tReturns, 2), 'color' => '#ef4444', 'pct' => $tGross > 0 ? round($tReturns / $tGross * 100) : null, 'donut' => false],
    ];
    $payDonut = array_values(array_filter($payLegend, fn ($s) => $s['donut'] && $s['value'] > 0.005));

    $guide = [
        'title' => 'ما الذي يعرضه التقرير الموحد للموردين؟',
        'lead' => 'مشتريات كل مورّد ومرتجعاته وما سُدِّد له وما بقي عليه خلال الفترة — ملخصاً، أو كشفاً تفصيلياً، أو أرصدةً.',
        'blocks' => [
            'الملخص' => 'صفٌّ لكلّ مورّد: عددُ الفواتير والمشتريات والمرتجعات والصافي والمدفوع وغير المدفوع وحالةُ السداد.',
            'التفاصيل' => 'كشفٌ لكلّ مورّد بمستنداته في الفترة: فواتير ومرتجعات وإشعارات وسندات، مع ما سُدِّد من كلّ فاتورة.',
            'الأرصدة' => 'المشتريات والمرتجعات والمدفوعات ورصيدُ آخر المدة التراكمي — موجبٌ يعني مستحقاً للمورّد.',
        ],
        'facts' => ['الأرقامُ من دفتر المورّد نفسِه فتطابق «المشتريات حسب المورد»', 'التصديرُ يتبع العرضَ المفتوح أعمدةً وصفوفاً'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => 'التقرير الموحد للموردين — '.$viewTitles[$viewType],
        'periodLabel' => $periodLabel,
        'filters' => [
            'المورد' => $selectedSupplier,
            'الموظف' => $selectedEmployee,
            'تابع لـ' => $selectedBranch,
        ],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'address-book',
            'headTitle' => 'التقرير الموحد للموردين',
            'headSubtitle' => 'مشتريات كل مورّد ومرتجعاته وما سُدِّد له وما بقي عليه خلال الفترة — ملخصاً، أو كشفاً تفصيلياً، أو أرصدةً.',
            'headPeriod' => $periodLabel,
            'headExtra' => 'العرض: '.$viewLabels[$viewType],
            'headGuideKey' => 'purchases-reports.unified-supplier',
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
            <form method="GET" action="{{ route('ReportsPurchases.unifiedSupplierReport') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period->key }}">
                <input type="hidden" name="view_type" id="viewTypeInput" value="{{ $viewType }}">

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
                                <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->trade_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="employee_id">الموظف</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">جميع الموظفين</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(request('employee_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="btn-group ag-view-toggle" role="group" aria-label="طريقة العرض">
                        @foreach ($viewLabels as $key => $label)
                            <button type="button" class="btn {{ $viewType === $key ? 'btn-primary active' : 'btn-outline-primary' }}" data-view="{{ $key }}">
                                <i class="fas fa-{{ $viewIcons[$key] }}"></i> {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.unifiedSupplierReport') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
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
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['count']) }}</bdi></div>
                <div class="ag-kpi__sub">على {{ number_format($suppliersCount) }} مورد</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-alt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي المشتريات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tGross) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · قبل المرتجعات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المرتجعات</div>
                <div class="ag-kpi__value"><bdi class="{{ $tReturns > 0.005 ? 'rpt-neg' : '' }}">{{ $money($tReturns) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['returns_count']) }} مرتجع</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">صافي المشتريات</div>
                <div class="ag-kpi__value"><bdi class="{{ $signClass($tNet) }}">{{ $signed($tNet) }}</bdi></div>
                <div class="ag-kpi__sub">المشتريات − المرتجعات − الإشعارات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">المدفوع</div>
                <div class="ag-kpi__value"><bdi>{{ $money($tPaid) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['payments_count']) }} سند صرف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-check-circle"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">غير المدفوع</div>
                <div class="ag-kpi__value"><bdi class="{{ $tUnpaid > 0.005 ? 'rpt-warnc' : '' }}">{{ $money($tUnpaid) }}</bdi></div>
                <div class="ag-kpi__sub">من صافي مشتريات الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> أعلى الموردين صافيَ مشتريات</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($topBars))
                    <div class="ag-canvas"><canvas id="topSuppliersChart" aria-label="أعلى الموردين صافي مشتريات" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا مشتريات لعرضها في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> حالة السداد من الصافي</h4>
            </div>
            <div class="rpt-card__body">
                @if ($tGross > 0.005 || $tReturns > 0.005)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($payLegend as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($slice['value']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] === null ? '—' : $slice['pct'].'%' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (count($payDonut))
                            <div class="ag-donut">
                                <canvas id="payDonut" aria-label="حالة السداد من الصافي" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ $signed($tNet) }}</strong>
                                    <small>صافي المشتريات · {{ $currency }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا حركة في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $viewTitles[$viewType] }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($suppliersCount) }} مورد</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            {{-- ===================== الملخص ===================== --}}
            @if ($viewType === 'summary')
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:22%">المورد</th>
                            <th>عدد الفواتير</th>
                            <th>إجمالي المشتريات</th>
                            <th>المرتجعات</th>
                            <th>صافي المشتريات</th>
                            <th>المدفوع</th>
                            <th>غير المدفوع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $row)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">
                                    {{ $row['name'] }}
                                    <small>كود: {{ $row['supplier_code'] }}</small>
                                </td>
                                <td>
                                    <span class="rpt-pill rpt-pill-info">{{ $row['count'] }}</span>
                                    @if ($row['returns_count'] > 0)
                                        <span class="rpt-pill rpt-pill-danger">-{{ $row['returns_count'] }}</span>
                                    @endif
                                </td>
                                <td class="rpt-num rpt-pos">{{ $money($row['amount']) }}</td>
                                <td class="rpt-num {{ $row['returns_amount'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['returns_amount'] > 0 ? '('.$money($row['returns_amount']).')' : '—' }}
                                </td>
                                <td class="rpt-num {{ $signClass($row['net_amount']) }}"><strong>{{ $signed($row['net_amount']) }}</strong></td>
                                <td class="rpt-num {{ $row['paid'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">
                                    {{ $row['paid'] > 0 ? $money($row['paid']) : '—' }}
                                </td>
                                <td class="rpt-num {{ $row['unpaid'] > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $money($row['unpaid']) }}</td>
                                <td>
                                    <span class="rpt-pill rpt-pill-{{ $row['status']['class'] }}">
                                        <i class="fas fa-{{ $statusIcon($row['status']['code']) }}"></i> {{ $row['status']['text'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty">
                                <i class="fas fa-inbox"></i>
                                لا توجد بيانات للعرض في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($suppliersCount)
                        <tfoot>
                            <tr>
                                <td colspan="2">الإجمالي العام</td>
                                <td>
                                    {{ $totals['count'] }}
                                    @if ($totals['returns_count'] > 0)
                                        <span class="rpt-pill rpt-pill-danger">-{{ $totals['returns_count'] }}</span>
                                    @endif
                                </td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['amount']) }}</td>
                                <td class="rpt-num rpt-neg">({{ $money($totals['returns_amount']) }})</td>
                                <td class="rpt-num {{ $signClass($tNet) }}">{{ $signed($tNet) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['paid']) }}</td>
                                <td class="rpt-num {{ $tUnpaid > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $money($totals['unpaid']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

            {{-- ===================== التفاصيل ===================== --}}
            @elseif ($viewType === 'details')
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>رقم المرجع</th>
                            <th style="width:100px">التاريخ</th>
                            <th>الموظف</th>
                            <th>نوع الفاتورة</th>
                            <th>تابع لـ</th>
                            <th>المبلغ</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $row)
                            @php
                                $rowKey = 's'.$loop->index;
                                $items = collect($row['items']);
                                $itemsAmount = $items->sum('amount');
                                $itemsPaid = $items->sum('paid');
                                $itemsUnpaid = $items->sum('unpaid');
                            @endphp
                            <tr class="ag-group-row" data-row="1" data-row-key="{{ $rowKey }}">
                                <td colspan="10" class="rpt-name">
                                    <i class="fas fa-user-tie"></i>
                                    <strong>{{ $row['name'] }}</strong>
                                    <small class="text-muted" style="display:inline; font-weight:500">— كود {{ $row['supplier_code'] }}</small>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $row['count'] }} فاتورة</span>
                                    @if ($row['returns_count'] > 0)
                                        <span class="rpt-pill rpt-pill-danger">{{ $row['returns_count'] }} مرتجع</span>
                                    @endif
                                    <span class="rpt-pill rpt-pill-{{ $row['status']['class'] }}">{{ $row['status']['text'] }}</span>
                                </td>
                            </tr>
                            @forelse ($items as $i => $item)
                                @php $isReturn = $item['kind'] === 'return'; @endphp
                                <tr class="rpt-row-{{ $item['kind'] }}" data-parent-row="{{ $rowKey }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td><strong>{{ $item['reference_number'] }}</strong></td>
                                    <td class="rpt-num">{{ $item['date'] }}</td>
                                    <td>{{ $item['employee_name'] }}</td>
                                    <td><span class="rpt-pill rpt-pill-{{ $kindPill[$item['kind']] ?? 'secondary' }}">{{ $item['reference_type'] }}</span></td>
                                    <td>{{ $item['branch_name'] }}</td>
                                    <td class="rpt-num {{ $isReturn ? 'rpt-neg' : $signClass($item['amount']) }}">
                                        {{ $isReturn ? '('.$money(abs($item['amount'])).')' : $signed($item['amount']) }}
                                    </td>
                                    <td class="rpt-num {{ $item['paid'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ $item['paid'] > 0 ? $money($item['paid']) : '—' }}</td>
                                    <td class="rpt-num {{ $item['unpaid'] > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $item['unpaid'] > 0.005 ? $money($item['unpaid']) : '—' }}</td>
                                    <td><span class="rpt-pill rpt-pill-{{ $item['status']['class'] }}">{{ $item['status']['text'] }}</span></td>
                                </tr>
                            @empty
                                <tr data-parent-row="{{ $rowKey }}"><td colspan="10" class="rpt-empty"><i class="fas fa-inbox"></i> لا حركة لهذا المورد في الفترة</td></tr>
                            @endforelse
                            @if ($items->isNotEmpty())
                                <tr class="ag-subtotal" data-parent-row="{{ $rowKey }}">
                                    <td colspan="6" style="text-align:start">
                                        إجمالي {{ $row['name'] }}
                                        <small class="text-muted">(المشتريات {{ $money($row['amount']) }} − المرتجعات {{ $money($row['returns_amount']) }} = الصافي {{ $signed($row['net_amount']) }})</small>
                                    </td>
                                    <td class="rpt-num {{ $signClass($itemsAmount) }}">{{ $signed($itemsAmount) }}</td>
                                    <td class="rpt-num rpt-pos">{{ $money($itemsPaid) }}</td>
                                    <td class="rpt-num {{ $itemsUnpaid > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $money($itemsUnpaid) }}</td>
                                    <td></td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="10" class="rpt-empty">
                                <i class="fas fa-inbox"></i>
                                لا توجد بيانات للعرض في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($suppliersCount)
                        <tfoot>
                            <tr>
                                <td colspan="6">الإجمالي العام — {{ number_format($totals['count']) }} فاتورة على {{ number_format($suppliersCount) }} مورد</td>
                                <td class="rpt-num {{ $signClass($tNet) }}">{{ $signed($tNet) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['paid']) }}</td>
                                <td class="rpt-num {{ $tUnpaid > 0.005 ? 'rpt-warnc' : 'rpt-zero' }}">{{ $money($totals['unpaid']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

            {{-- ===================== الأرصدة ===================== --}}
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:24%">المورد</th>
                            <th>الكود</th>
                            <th>المشتريات</th>
                            <th>المرتجعات</th>
                            <th>المدفوعات</th>
                            <th>الرصيد</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($reportData as $row)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">{{ $row['name'] }}</td>
                                <td class="rpt-num">{{ $row['supplier_code'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($row['total_purchases']) }}</td>
                                <td class="rpt-num {{ $row['total_returns'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">{{ $money($row['total_returns']) }}</td>
                                <td class="rpt-num {{ $row['paid'] > 0 ? 'rpt-pos' : 'rpt-zero' }}">{{ $money($row['paid']) }}</td>
                                <td class="rpt-num {{ $balClass($row['balance']) }}">
                                    <strong>{{ $signed($row['balance']) }}</strong>
                                    <small class="d-block" style="font-size:.68rem">{{ $row['balance_state']['text'] }}</small>
                                </td>
                                <td><span class="rpt-pill rpt-pill-{{ $row['status']['class'] }}">{{ $row['status']['text'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty">
                                <i class="fas fa-inbox"></i>
                                لا توجد بيانات للعرض في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($suppliersCount)
                        <tfoot>
                            <tr>
                                <td colspan="3">الإجمالي</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['total_purchases']) }}</td>
                                <td class="rpt-num rpt-neg">{{ $money($totals['total_returns']) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['paid']) }}</td>
                                <td class="rpt-num {{ $balClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'مورد',
            'pagerStorage' => 'agUnifiedSupplierPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')

    @php
        /*
         * التصدير يتبع العرض المفتوح: ما يراه القارئ على الشاشة هو ما يخرج
         * في الملف — أعمدةً وصفوفاً وإجمالاً.
         */
        $excelInfo = [
            'الفترة' => $periodLabel,
            'المورد' => $selectedSupplier ?: 'الكل',
            'الموظف' => $selectedEmployee ?: 'الكل',
            'تابع لـ' => $selectedBranch ?: 'الكل',
            'العرض' => $viewTitles[$viewType],
        ];

        $excelCards = [
            ['title' => 'عدد الفواتير', 'value' => $totals['count'], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المشتريات', 'value' => $money($totals['amount']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المرتجعات', 'value' => $money($totals['returns_amount']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
            ['title' => 'صافي المشتريات', 'value' => $money($totals['net_amount']), 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'المدفوع', 'value' => $money($totals['paid']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'غير المدفوع', 'value' => $money($totals['unpaid']), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningLight', 'borderColor' => 'warning'],
        ];

        $summaryStyle = ['background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12];

        if ($viewType === 'summary') {
            $excelHeaders = ['#', 'المورد', 'الكود', 'عدد الفواتير', 'عدد المرتجعات', 'إجمالي المشتريات', 'المرتجعات', 'صافي المشتريات', 'المدفوع', 'غير المدفوع', 'الحالة'];
            $excelWidths = [6, 30, 14, 12, 12, 16, 14, 16, 14, 14, 16];
            $excelRows = $reportData->values()->map(fn ($r, $i) => [
                'values' => [
                    $i + 1, $r['name'], (string) $r['supplier_code'], $r['count'], $r['returns_count'],
                    $r['amount'], $r['returns_amount'], $r['net_amount'], $r['paid'], $r['unpaid'], $r['status']['text'],
                ],
                'alignments' => [null, 'right'],
            ])->all();
            $excelSummary = $suppliersCount ? [array_merge(['values' => [
                '', 'الإجمالي العام', '', $totals['count'], $totals['returns_count'],
                $totals['amount'], $totals['returns_amount'], $totals['net_amount'], $totals['paid'], $totals['unpaid'], '',
            ]], $summaryStyle)] : [];
        } elseif ($viewType === 'details') {
            $excelHeaders = ['#', 'المورد', 'رقم المرجع', 'التاريخ', 'الموظف', 'نوع الفاتورة', 'تابع لـ', 'المبلغ', 'المدفوع', 'المتبقي', 'الحالة'];
            $excelWidths = [6, 26, 16, 12, 18, 16, 18, 14, 14, 14, 16];
            $excelRows = [];
            $n = 0;
            foreach ($reportData as $r) {
                foreach ($r['items'] as $item) {
                    $excelRows[] = [
                        'values' => [
                            ++$n, $r['name'], $item['reference_number'], $item['date'], $item['employee_name'],
                            $item['reference_type'], $item['branch_name'], $item['amount'], $item['paid'], $item['unpaid'], $item['status']['text'],
                        ],
                        'alignments' => [null, 'right'],
                    ];
                }
            }
            $excelSummary = $excelRows ? [array_merge(['values' => [
                '', 'الإجمالي العام', '', '', '', '', '', $totals['net_amount'], $totals['paid'], $totals['unpaid'], '',
            ]], $summaryStyle)] : [];
        } else {
            $excelHeaders = ['#', 'المورد', 'الكود', 'المشتريات', 'المرتجعات', 'المدفوعات', 'الرصيد', 'وصف الرصيد', 'الحالة'];
            $excelWidths = [6, 30, 14, 16, 14, 16, 16, 16, 16];
            $excelRows = $reportData->values()->map(fn ($r, $i) => [
                'values' => [
                    $i + 1, $r['name'], (string) $r['supplier_code'], $r['total_purchases'], $r['total_returns'],
                    $r['paid'], $r['balance'], $r['balance_state']['text'], $r['status']['text'],
                ],
                'alignments' => [null, 'right'],
            ])->all();
            $excelSummary = $suppliersCount ? [array_merge(['values' => [
                '', 'الإجمالي', '', $totals['total_purchases'], $totals['total_returns'], $totals['paid'], $totals['balance'], '', '',
            ]], $summaryStyle)] : [];
        }
    @endphp

    @include('purchases::purchases.report.partials.excel', [
        'fileName' => 'التقرير_الموحد_للموردين',
        'mainTitle' => 'التقرير الموحد للموردين — '.$viewTitles[$viewType],
        'info' => $excelInfo,
        'cards' => $excelCards,
        'headers' => $excelHeaders,
        'widths' => $excelWidths,
        'rows' => $excelRows,
        'summaryRows' => $excelSummary,
    ])

    <script>
        (function () {
            var TOP_BARS = @json($topBars);
            var PAY_DONUT = @json($payDonut);

            function markCustom() {
                $('#periodInput').val('custom');
                $('.period-btn').removeClass('is-active');
                $('.period-btn[data-period="custom"]').addClass('is-active');
            }

            $(function () {
                if ($.fn.select2) {
                    $('.rpt-select2').each(function () { if ($(this).data('select2')) { $(this).select2('destroy'); } });
                    $('.rpt-select2').select2({ dir: 'rtl', width: '100%', language: { noResults: function () { return 'لا توجد نتائج'; } } });
                }

                $('.period-btn[data-period]').on('click', function () {
                    var key = String($(this).data('period'));
                    $('.period-btn').removeClass('is-active');
                    $(this).addClass('is-active');
                    $('#periodInput').val(key);
                    if (key === 'custom') { $('#date_from').trigger('focus'); return; }
                    $('#date_from, #date_to').val('');
                    $('#filterForm').trigger('submit');
                });
                $('#date_from, #date_to').on('change', markCustom);
                $('#filterForm').on('submit', function () {
                    if ($('#periodInput').val() !== 'custom') { $('#date_from, #date_to').val(''); }
                });

                $('.ag-view-toggle [data-view]').on('click', function () {
                    $('#viewTypeInput').val($(this).data('view'));
                    $('#filterForm').trigger('submit');
                });

                $('#printBtn').on('click', function () { window.print(); });

                renderCharts();
            });

            function renderCharts() {
                if (typeof Chart === 'undefined') return;
                var theme = agChartTheme();

                var barEl = document.getElementById('topSuppliersChart');
                if (barEl && TOP_BARS.length) {
                    var values = TOP_BARS.map(function (r) { return r.value; });
                    new Chart(barEl, {
                        type: 'bar',
                        data: { labels: TOP_BARS.map(function (r) { return r.short; }), datasets: [{ data: values, backgroundColor: '#8b5cf6', borderRadius: 6, maxBarThickness: 48 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: { rtl: true, callbacks: {
                                    title: function (items) { return TOP_BARS[items[0].dataIndex].name; },
                                    label: function (c) { return ' ' + agMoney(TOP_BARS[c.dataIndex].value); }
                                } }
                            },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 10 }, maxRotation: 45, autoSkip: false } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } }
                            }
                        },
                        plugins: [agValueLabels(values)]
                    });
                }

                var donutEl = document.getElementById('payDonut');
                if (donutEl && PAY_DONUT.length) {
                    new Chart(donutEl, {
                        type: 'doughnut',
                        data: {
                            labels: PAY_DONUT.map(function (s) { return s.label; }),
                            datasets: [{ data: PAY_DONUT.map(function (s) { return s.value; }), backgroundColor: PAY_DONUT.map(function (s) { return s.color; }), borderColor: theme.surface, borderWidth: 3, hoverOffset: 6 }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) {
                                var s = PAY_DONUT[c.dataIndex];
                                return ' ' + agMoney(s.value) + (s.pct === null ? '' : ' (' + s.pct + '%)');
                            } } } }
                        }
                    });
                }
            }
        })();
    </script>
@endsection
