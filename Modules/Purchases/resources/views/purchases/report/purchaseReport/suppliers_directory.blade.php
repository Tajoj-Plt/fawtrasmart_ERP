{{--
    دليل الموردين — على تصميم تقارير العملاء المعتمد (`ag-*`): ترويسةٌ
    فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ بأيقونات، رسمان
    (الأعلى شراءً + توزيع الأرصدة)، ثمّ جدولٌ واحدٌ بترقيم صفحات.

    ما لم يتغيّر: الفلاترُ وأسماؤها (والفتراتُ مفاتيحُ `ReportPeriod`
    نفسُها يحسب الخادمُ مداها)، وزرّا «ملخص/كشف تفصيلي» يُرسلان `view_type`،
    وروابطُ الفرز في رؤوس الأعمدة، والتصديرُ يُبنى في الخادم من صفوف التقرير
    (`partials.excel`) والطباعةُ تطبع الصفحة بترويسة المنشأة.

    الكشفُ التفصيلي صار جدولاً واحداً: صفُّ عنوانٍ لكلّ مورّد ثمّ رصيدُ ما
    قبل الفترة ثمّ حركاتُه ثمّ إجماليُّه — والترقيمُ بالمورّد فلا ينقسم
    كشفُه بين صفحتين.
--}}
@extends('purchases::master')

@section('title')
    دليل الموردين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-table tbody tr.ag-group-row td { background: var(--rpt-brand-soft); text-align: start; }
        .rpt .ag-table tbody tr.ag-group-row .rpt-pill { margin-inline-start: 6px; }
        .rpt .ag-table tbody tr.ag-opening td { font-weight: 600; background: var(--rpt-sunken, transparent); }
        .rpt .ag-table tbody tr.ag-subtotal td { font-weight: 800; background: var(--rpt-sunken, transparent); }
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .ag-group-mini { display: inline-flex; flex-wrap: wrap; gap: 4px 14px; margin-inline-start: 10px; font-size: .74rem; font-weight: 500; color: var(--rpt-muted); }
        .rpt .ag-group-mini b { font-weight: 800; direction: ltr; unicode-bidi: embed; }
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

    $q = request()->except(['page']);
    $sortBy = request('sort_by', 'purchases');
    $sortDir = request('sort_dir', 'desc');
    $viewType = request('view_type', 'summary') === 'details' ? 'details' : 'summary';

    $money = fn ($v) => CurrencySettings::number($v);
    $signed = fn ($v) => ($v < -0.005 ? '−' : '').CurrencySettings::number($v);
    $signClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-pos' : 'rpt-neg');
    // الرصيد التزام: الموجب دَينٌ علينا فيُلوَّن تحذيراً لا ربحاً
    $balClass = fn ($v) => abs($v) < 0.005 ? 'rpt-zero' : ($v > 0 ? 'rpt-neg' : 'rpt-pos');
    $currency = CurrencySettings::name();

    $selectedSupplier = request('supplier_id') ? optional($suppliers->firstWhere('id', (int) request('supplier_id')))->trade_name : null;
    $selectedBranch = $selectedNodeLabel ?? null;
    $periodLabel = $period->labelWithDates();

    $link = function ($col) use ($q, $sortBy, $sortDir) {
        $dir = ($sortBy === $col && $sortDir === 'desc') ? 'asc' : 'desc';
        return route('ReportsPurchases.SuppliersDirectory', array_merge($q, ['sort_by' => $col, 'sort_dir' => $dir]));
    };
    $caret = fn ($col) => $sortBy === $col ? ' <i class="fas fa-sort-'.($sortDir === 'asc' ? 'up' : 'down').'"></i>' : '';

    /* أزرارُ الفترات: مفاتيحُ `ReportPeriod` نفسُها التي يقرؤها المتحكّم */
    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];

    /* ─────────── الرسمان ─────────── */
    $topBars = $reportData->sortByDesc('purchases')->take(10)->values()->map(fn ($r) => [
        'name' => (string) $r['supplier_name'],
        'short' => mb_strlen($r['supplier_name']) > 16 ? mb_substr($r['supplier_name'], 0, 15).'…' : $r['supplier_name'],
        'value' => round((float) $r['purchases'], 2),
    ])->filter(fn ($r) => $r['value'] > 0)->values()->all();

    $balGroups = [
        'creditor' => ['label' => 'دائن — له علينا', 'color' => '#ef4444', 'count' => 0, 'sum' => 0.0],
        'debtor' => ['label' => 'مدين — عليه لنا', 'color' => '#10b981', 'count' => 0, 'sum' => 0.0],
        'zero' => ['label' => 'متزن', 'color' => '#64748b', 'count' => 0, 'sum' => 0.0],
    ];
    foreach ($reportData as $blRow) {
        $blKey = $blRow['balance'] > 0.009 ? 'creditor' : ($blRow['balance'] < -0.009 ? 'debtor' : 'zero');
        $balGroups[$blKey]['count']++;
        $balGroups[$blKey]['sum'] += abs((float) $blRow['balance']);
    }
    $suppliersCount = $reportData->count();
    foreach ($balGroups as $blKey => $blGroup) {
        $balGroups[$blKey]['sum'] = round($blGroup['sum'], 2);
        $balGroups[$blKey]['pct'] = $suppliersCount > 0 ? round($blGroup['count'] / $suppliersCount * 100) : 0;
    }
    $creditors = $balGroups['creditor']['count'];
    $debtors = $balGroups['debtor']['count'];
    $chartGroups = array_values($balGroups);

    $kindPill = ['purchase' => 'success', 'return' => 'danger', 'debit_note' => 'warning', 'payment' => 'info', 'other' => 'secondary'];

    $guide = [
        'title' => 'ما الذي يعرضه دليل الموردين؟',
        'lead' => 'بطاقةُ تعريفٍ لكلّ مورّد: من هو، وكيف يُتواصَل معه، وماذا جرى بيننا وبينه في الفترة، وأين استقرّ رصيده — من قيود اليومية المرحَّلة على حساباتهم.',
        'blocks' => [
            'المشتريات والمرتجعات' => 'فواتيرُ الشراء المرحَّلة في الفترة، وما رُدّ منها أو خُصم بإشعارٍ مدين.',
            'الرصيد' => 'رصيدُ آخر المدة تراكميٌّ: موجبٌ يعني مستحقاً للمورّد (له علينا)، وسالبٌ يعني أنّ له عندنا مدفوعاتٍ زائدة (عليه لنا).',
            'الحالة' => 'مقارنةُ المدفوع بصافي مشتريات الفترة: مسدّدٌ بالكامل، أو جزئياً، أو غيرُ مسدّد.',
        ],
        'facts' => ['الأرقامُ من دفتر المورّد نفسِه فتطابق كشفَ الحساب رقماً برقم', 'الفتراتُ الجاهزة حدودٌ تقويمية يحسبها الخادم'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => 'دليل الموردين — '.($viewType === 'summary' ? 'ملخص' : 'كشف تفصيلي'),
        'periodLabel' => $periodLabel,
        'filters' => ['المورد' => $selectedSupplier, 'تابع لـ' => $selectedBranch],
    ])

    {{-- ===================== الترويسة ===================== --}}
    <div class="no-print">
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'address-book',
            'headTitle' => 'دليل الموردين',
            'headSubtitle' => 'بيانات كل مورّد وحركته ورصيده — من قيود اليومية المرحَّلة على حساباتهم.',
            'headPeriod' => $periodLabel,
            'headGuideKey' => 'purchases-reports.suppliers-directory',
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
            <form method="GET" action="{{ route('ReportsPurchases.SuppliersDirectory') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ $period->key }}">
                <input type="hidden" name="view_type" id="viewTypeInput" value="{{ $viewType }}">
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
                                <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->trade_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="payment_status">الحالة</label>
                        <select name="payment_status" id="payment_status" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            <option value="paid" @selected(request('payment_status') === 'paid')>مسدد</option>
                            <option value="partial" @selected(request('payment_status') === 'partial')>جزئي</option>
                            <option value="unpaid" @selected(request('payment_status') === 'unpaid')>غير مسدد</option>
                            <option value="idle" @selected(request('payment_status') === 'idle')>لا حركة</option>
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
                    <div class="ag-checks">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="show_zero" name="show_zero" value="1" @checked(request()->boolean('show_zero'))>
                            <label class="form-check-label" for="show_zero">إظهار الموردين بلا حركة</label>
                        </div>
                    </div>
                    <div class="btn-group ag-view-toggle" role="group" aria-label="طريقة العرض">
                        <button type="button" class="btn {{ $viewType === 'summary' ? 'btn-primary active' : 'btn-outline-primary' }}" data-view="summary"><i class="fas fa-list"></i> ملخص</button>
                        <button type="button" class="btn {{ $viewType === 'details' ? 'btn-primary active' : 'btn-outline-primary' }}" data-view="details"><i class="fas fa-th-list"></i> كشف تفصيلي</button>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.SuppliersDirectory') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">الموردون</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['suppliers_count']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ number_format($totals['entries_count']) }} حركة في الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي المشتريات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['purchases']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['purchases_count']) }} فاتورة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">المرتجعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['returns']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['returns_count']) }} مرتجع</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-undo"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">صافي المشتريات</div>
                <div class="ag-kpi__value"><bdi class="{{ $signClass($totals['net_purchases']) }}">{{ $signed($totals['net_purchases']) }}</bdi></div>
                <div class="ag-kpi__sub">المشتريات − المرتجعات − الإشعارات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">المدفوعات</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['payments']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['payments_count']) }} سند صرف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">الرصيد الكلي</div>
                <div class="ag-kpi__value"><bdi class="{{ $balClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ number_format($creditors) }} دائن · {{ number_format($debtors) }} مدين</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> أعلى الموردين شراءً</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($topBars))
                    <div class="ag-canvas"><canvas id="topSuppliersChart" aria-label="أعلى الموردين شراءً" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا مشتريات لعرضها في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع الموردين حسب نوع الرصيد</h4>
            </div>
            <div class="rpt-card__body">
                @if ($suppliersCount > 0)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($balGroups as $blGroup)
                                    <tr class="ag-legend__row" style="--c:{{ $blGroup['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $blGroup['label'] }} <span class="text-muted">({{ number_format($blGroup['count']) }})</span></td>
                                        <td class="ag-legend__amount"><bdi>{{ $money($blGroup['sum']) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $blGroup['pct'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="ag-donut">
                            <canvas id="balanceDonut" aria-label="توزيع الموردين حسب نوع الرصيد" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ number_format($suppliersCount) }}</strong>
                                <small>مورد</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا موردين لعرضهم في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-{{ $viewType === 'summary' ? 'address-book' : 'th-list' }}"></i>
                {{ $viewType === 'summary' ? 'دليل الموردين' : 'الكشف التفصيلي للموردين' }}
                <span class="rpt-pill rpt-pill-secondary">{{ number_format($suppliersCount) }} مورد</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($viewType === 'summary')
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:22%"><a href="{{ $link('supplier_name') }}">المورد{!! $caret('supplier_name') !!}</a></th>
                            <th>الهاتف</th>
                            <th>المدينة</th>
                            <th>تابع لـ</th>
                            <th><a href="{{ $link('purchases_count') }}">الفواتير{!! $caret('purchases_count') !!}</a></th>
                            <th><a href="{{ $link('purchases') }}">المشتريات{!! $caret('purchases') !!}</a></th>
                            <th><a href="{{ $link('returns') }}">المرتجعات{!! $caret('returns') !!}</a></th>
                            <th><a href="{{ $link('payments') }}">المدفوعات{!! $caret('payments') !!}</a></th>
                            <th><a href="{{ $link('balance') }}">الرصيد{!! $caret('balance') !!}</a></th>
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
                                    <small>كود: {{ $row['supplier_code'] }}</small>
                                </td>
                                <td class="rpt-num">{{ $row['phone'] ?: '—' }}</td>
                                <td>{{ $row['city'] ?: '—' }}</td>
                                <td>{{ $row['branch_name'] }}</td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $row['purchases_count'] }}</span></td>
                                <td class="rpt-num rpt-pos">{{ $money($row['purchases']) }}</td>
                                <td class="rpt-num {{ $row['returns'] > 0 ? 'rpt-neg' : 'rpt-zero' }}">
                                    {{ $row['returns'] > 0 ? '('.$money($row['returns']).')' : '—' }}
                                </td>
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
                                       href="{{ route('ReportsPurchases.SuppliersDirectory', array_merge($q, ['view_type' => 'details', 'supplier_id' => $row['supplier_id']])) }}">
                                        <i class="fas fa-th-list"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rpt-empty">
                                <i class="fas fa-inbox"></i>
                                لا توجد حركة موردين في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($suppliersCount)
                        <tfoot>
                            <tr>
                                <td colspan="5">الإجمالي العام</td>
                                <td>{{ $totals['purchases_count'] }}</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['purchases']) }}</td>
                                <td class="rpt-num rpt-neg">({{ $money($totals['returns']) }})</td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['payments']) }}</td>
                                <td class="rpt-num {{ $balClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</td>
                                <td></td>
                                <td class="ag-extra"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                {{-- الكشفُ التفصيلي: جدولٌ واحد، عنوانٌ لكلّ مورّد ثمّ رصيدُ ما قبل الفترة ثمّ حركاتُه ثمّ إجماليُّه --}}
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:100px">التاريخ</th>
                            <th>رقم المستند</th>
                            <th>البيان</th>
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
                                    <i class="fas fa-user-tie"></i>
                                    <strong>{{ $row['supplier_name'] }}</strong>
                                    <small class="text-muted" style="display:inline; font-weight:500">
                                        — كود {{ $row['supplier_code'] }}
                                        @if ($row['phone']) · {{ $row['phone'] }} @endif
                                        @if ($row['city']) · {{ $row['city'] }} @endif
                                    </small>
                                    <span class="rpt-pill rpt-pill-{{ $row['status']['class'] }}">{{ $row['status']['text'] }}</span>
                                    <span class="rpt-pill rpt-pill-{{ $row['balance_state']['class'] }}">{{ $row['balance_state']['text'] }}: {{ $money($row['balance']) }}</span>
                                    <span class="ag-group-mini">
                                        <span>مشتريات ({{ $row['purchases_count'] }}) <b class="rpt-pos">{{ $money($row['purchases']) }}</b></span>
                                        <span>مرتجعات ({{ $row['returns_count'] }}) <b class="rpt-neg">{{ $money($row['returns']) }}</b></span>
                                        <span>إشعارات ({{ $row['debit_notes_count'] }}) <b class="rpt-neg">{{ $money($row['debit_notes']) }}</b></span>
                                        <span>مدفوعات ({{ $row['payments_count'] }}) <b class="rpt-pos">{{ $money($row['payments']) }}</b></span>
                                    </span>
                                </td>
                            </tr>
                            <tr class="ag-opening" data-parent-row="{{ $rowKey }}">
                                <td>—</td>
                                <td class="rpt-num">{{ $fromDate->format('d/m/Y') }}</td>
                                <td colspan="4" class="rpt-name">رصيد ما قبل الفترة</td>
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
                                <td colspan="6" style="text-align:start">إجمالي حركة الفترة — {{ $row['supplier_name'] }}</td>
                                <td class="rpt-num rpt-neg">{{ $money(collect($row['items'])->sum('debit')) }}</td>
                                <td class="rpt-num rpt-pos">{{ $money(collect($row['items'])->sum('credit')) }}</td>
                                <td class="rpt-num {{ $balClass($row['balance']) }}">{{ $signed($row['balance']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty">
                                <i class="fas fa-inbox"></i>
                                لا توجد حركة موردين في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($suppliersCount)
                        <tfoot>
                            <tr>
                                <td colspan="6">الإجمالي العام — {{ number_format($suppliersCount) }} مورد</td>
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
            'pagerStorage' => 'agSuppliersDirectoryPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')

    @include('purchases::purchases.report.partials.excel', [
        'fileName' => 'دليل_الموردين',
        'mainTitle' => 'دليل الموردين',
        'info' => [
            'الفترة' => $periodLabel,
            'المورد' => $selectedSupplier ?: 'الكل',
            'تابع لـ' => $selectedBranch ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'عدد الموردين', 'value' => $totals['suppliers_count'], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'إجمالي المشتريات', 'value' => $money($totals['purchases']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'المدفوعات', 'value' => $money($totals['payments']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'الرصيد الكلي', 'value' => $money($totals['balance']), 'valueColor' => 'danger', 'titleColor' => 'gray', 'backgroundColor' => 'dangerBg', 'borderColor' => 'dangerLight'],
        ],
        'headers' => ['#', 'المورد', 'الكود', 'الهاتف', 'المدينة', 'تابع لـ', 'الفواتير', 'المشتريات', 'المرتجعات', 'المدفوعات', 'الرصيد', 'الحالة'],
        'widths' => [6, 30, 14, 18, 16, 18, 10, 16, 14, 16, 16, 16],
        'rows' => $reportData->values()->map(fn ($r, $i) => [
            'values' => [
                $i + 1, $r['supplier_name'], (string) $r['supplier_code'],
                (string) ($r['phone'] ?: '—'), (string) ($r['city'] ?: '—'), $r['branch_name'],
                $r['purchases_count'], $r['purchases'], $r['returns'],
                $r['payments'], $r['balance'], $r['status']['text'],
            ],
            'alignments' => [null, 'right'],
        ])->all(),
        'summaryRows' => $suppliersCount ? [[
            'values' => [
                '', 'الإجمالي العام', '', '', '', '', $totals['purchases_count'],
                $totals['purchases'], $totals['returns'], $totals['payments'], $totals['balance'], '',
            ],
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [],
    ])

    <script>
        (function () {
            var TOP_BARS = @json($topBars);
            var BAL_GROUPS = @json($chartGroups);

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

                /* الفتراتُ الجاهزة مفاتيحُ `ReportPeriod` يحسب مداها الخادم: يُفرَّغ
                   التاريخان لأن الخادم يقدّمهما على `period`. و«مخصص» تُنزِل
                   المؤشّر على أوّل حقلِ تاريخ. */
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
                        data: { labels: TOP_BARS.map(function (r) { return r.short; }), datasets: [{ data: values, backgroundColor: theme.brand, borderRadius: 6, maxBarThickness: 48 }] },
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

                var donutEl = document.getElementById('balanceDonut');
                if (donutEl) {
                    new Chart(donutEl, {
                        type: 'doughnut',
                        data: {
                            labels: BAL_GROUPS.map(function (g) { return g.label; }),
                            datasets: [{ data: BAL_GROUPS.map(function (g) { return g.count; }), backgroundColor: BAL_GROUPS.map(function (g) { return g.color; }), borderColor: theme.surface, borderWidth: 3, hoverOffset: 6 }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '66%',
                            plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function (c) {
                                var g = BAL_GROUPS[c.dataIndex];
                                return ' ' + agWhole(g.count) + ' مورد (' + g.pct + '%) · ' + agMoney(g.sum);
                            } } } }
                        }
                    });
                }
            }
        })();
    </script>
@endsection
