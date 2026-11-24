{{--
    تقرير أعمار ديون الموردين — صورةٌ من تقرير «أعمار ديون الأستاذ العام»
    في العملاء (client::customers.CustomerReport.debt_aging_general_enhanced)
    قسماً قسماً: ترويسةٌ فاتحة، شريطُ الفترة، شبكةُ فلترة بأزرار الفترات
    ومُنتقيَي السنة والفترة المحاسبية، مؤشّران، رسمان (أعمدة + حلقة بجدول
    توزيع)، شريطُ التسوية، جدولُ الموردين بترقيم صفحات، ثمّ قائمةُ الأرصدة
    المدينة منفصلةً.

    الطرفُ وحده مبدَّل: الرصيدُ الموجب هنا **دائن** (له علينا) وهو المديونيةُ
    التي تُعمَّر، والسالبُ **مدين** (لنا عليه — دفعنا أكثر) فيُفصل تحت
    الجدول كما تُفصل أرصدةُ العملاء الدائنة هناك. والفلاتر التي لا نظيرَ لها
    عند المورّد (الحالة، الوضع، الهدف، التصنيف، الحي) لا تُخترع.

    فلترُ الشرائح (`aging_filter`) يُرسَل إلى الخادم — بالنقر على صفّ الشريحة
    في جدول التوزيع أو على عمودها/قطعتها في الرسمين — فتُقصّ الشرائحُ
    والإجماليات معاً (SupplierReportController::buildAging). والتصديرُ يُبنى
    من `currentReportData` لا من الجدول.
--}}
@extends('purchases::master')

@section('title')
    تقرير أعمار ديون الموردين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        /* «تابع لـ» يأخذ عرضاً يُقرأ به، ومبالغُ الشرائح لا تنكسر */
        .rpt .ag-table tbody td:nth-child(4) { min-width: 130px; }
        .rpt .ag-table tbody td:nth-child(n+8):nth-child(-n+14) { white-space: nowrap; }
    </style>
@endsection

@section('content')
@php
    $currency = \App\Support\CurrencySettings::name();
    $periodLabel = 'من '.$fromDate->format('d/m/Y').' إلى '.$toDate->format('d/m/Y');
    $currentAgingFilter = (string) request('aging_filter', '');
    $ledgerRows = collect($reportData);

    /* الشرائحُ الستّ بقيم `aging_filter` نفسِها التي يقرؤها المتحكّم */
    $agBuckets = [
        ['key' => 'today',       'filter' => 'today',  'label' => 'غير مستحق',        'short' => 'غير مستحق',  'color' => '#10b981'],
        ['key' => 'days1to30',   'filter' => '1-30',   'label' => 'من 1 إلى 30 يوم',   'short' => '1–30 يوم',   'color' => '#3b82f6'],
        ['key' => 'days31to60',  'filter' => '31-60',  'label' => 'من 31 إلى 60 يوم',  'short' => '31–60 يوم',  'color' => '#eab308'],
        ['key' => 'days61to90',  'filter' => '61-90',  'label' => 'من 61 إلى 90 يوم',  'short' => '61–90 يوم',  'color' => '#f97316'],
        ['key' => 'days91to120', 'filter' => '91-120', 'label' => 'من 91 إلى 120 يوم', 'short' => '91–120 يوم', 'color' => '#ef4444'],
        ['key' => 'daysOver120', 'filter' => '120+',   'label' => 'أكثر من 120 يوم',   'short' => '+120 يوم',   'color' => '#9f1239'],
    ];
    $bucketSum = 0.0;
    foreach ($agBuckets as $i => $bucket) {
        $agBuckets[$i]['value'] = (float) ($totals[$bucket['key']] ?? 0);
        $bucketSum += $agBuckets[$i]['value'];
    }
    foreach ($agBuckets as $i => $bucket) {
        $agBuckets[$i]['pct'] = $bucketSum > 0 ? round($bucket['value'] / $bucketSum * 100) : 0;
        $agBuckets[$i]['element_id'] = $bucket['key'] === 'today' ? 'todayAmount' : $bucket['key'];
    }
    $bucketsByKey = collect($agBuckets)->keyBy('key');
    $activeBucket = collect($agBuckets)->firstWhere('filter', $currentAgingFilter);
    $chartBuckets = array_map(fn ($b) => [
        'label' => $b['label'],
        'short' => $b['short'],
        'value' => round($b['value'], 2),
        'pct' => $b['pct'],
        'color' => $b['color'],
        'filter' => $b['filter'],
    ], $agBuckets);
    $bucketTotal = round($bucketSum, 2);

    /* شريحةُ الصفّ من عمر أقدم بندٍ مفتوح — قاعدةُ CustomerLedgerService::bucketFor() نفسُها */
    $bucketForDays = fn (int $d) => match (true) {
        $d <= 0 => 'today',
        $d <= 30 => 'days1to30',
        $d <= 60 => 'days31to60',
        $d <= 90 => 'days61to90',
        $d <= 120 => 'days91to120',
        default => 'daysOver120',
    };

    $suppliersCount = $totals['total_suppliers'] ?? count($reportData);
    $creditorsCount = $ledgerRows->filter(fn ($r) => (float) ($r['balance'] ?? 0) > 0.005)->count();
    $debtors = $debtors ?? [];

    $selectedSupplierName = $selectedSupplier?->name;
    $selectedEmployeeName = request('employee_id') ? optional(($employees ?? collect())->firstWhere('id', (int) request('employee_id')))->full_name : null;
    // الفلاتر المطبَّقة كما تُطبع في ترويسة الطباعة وورقة Excel
    $agFilterLabels = [
        'المورد' => $selectedSupplierName ?: 'جميع الموردين',
        'الموظف المسؤول' => $selectedEmployeeName ?: 'كل الموظفين',
        'تابع لـ' => $selectedNodeLabel ?? 'الكل',
    ];

    $debtGuide = [
        'title' => 'ما الذي يعرضه تقرير أعمار ديون الموردين؟',
        'lead' => 'ما علينا لكل مورّد موزَّعاً على شرائح أعماره — حركةً حركةً بتاريخها، حتى تاريخ القطع المختار.',
        'blocks' => [
            'مصدر الأرقام' => 'دفترُ المورّد نفسُه (قيودُ حسابه في الأستاذ العام): رصيدُه عند تاريخ القطع هو حاصلُ كلّ قيوده قبله، فيطابق تقريرَ أرصدة الموردين وكشفَ حسابه رقماً برقم.',
            'كيف يُحسب العمر' => 'تُطفأ أقدمُ المستحقّات بالمدفوعات أوّلاً (FIFO)، ويُوزَّع الباقي كلُّ حركةٍ بعمرها هي من تاريخها إلى اليوم. و«الأيام» في الجدول عمرُ أقدم حركةٍ ما زالت مفتوحة.',
            'الأرصدة المدينة' => 'مورّدٌ دفعنا له أكثرَ ممّا عليه — أو مقدَّماً — رصيدُه مدين (لنا عليه). ليس ديناً علينا فلا يُعمَّر ولا يُطرح من المديونية، ويُعرض في قائمةٍ مستقلّة أسفل الجدول.',
            'تاريخ القطع' => 'أزرارُ الفترات ومُنتقيا السنة والفترة المحاسبية تملأ حقلَي التاريخ؛ والأعمارُ صورةُ الدين كلِّه حتى «إلى تاريخ» — بدايةُ المدى لا تقصّ شيئاً.',
        ],
        'facts' => [
            'دائن = له علينا (مديونيتنا للمورّد)، مدين = لنا عليه',
            'النقرُ على شريحةٍ في جدول التوزيع أو الرسم يعيد التقرير مقصوراً عليها',
            'الموردون بلا رصيد يُخفَون إلّا بخانة «إظهار»',
        ],
    ];

    $canViewSupplier = hasPermission('purchases.suppliers.view');
    $canStatement = \Illuminate\Support\Facades\Route::has('SupplierManagement.statement') && hasPermission('purchases.suppliers.view_statement');
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'user-clock',
        'headTitle' => 'أعمار ديون الموردين',
        'headSubtitle' => 'مديونيتنا لكل مورّدٍ موزَّعةً على شرائح أعمارها — حركةً حركةً بتاريخها، حتى تاريخ القطع المختار',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'purchases-reports.supplier-debt-aging',
        'headGuide' => $debtGuide,
        'headCrumbLabel' => 'تقارير المشتريات',
        'headCrumbUrl' => route('ReportsPurchases.index'),
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('ReportsPurchases.supplierDebtAging') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ request('period') ?? '30' }}">
                <input type="hidden" name="aging_filter" id="agingFilterInput" value="{{ request('aging_filter') ?? '' }}">

                {{-- ─────────── الفترات السريعة ─────────── --}}
                @include('client::customers.CustomerReport.partials.report-period-tabs')

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="from_date">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="from_date" class="form-control" aria-label="من تاريخ"
                                   value="{{ request('from_date') ?: (isset($fromDate) ? $fromDate->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d')) }}">
                            <span class="ag-range__sep">إلى</span>
                            {{-- المدى الذي حُسب به التقرير — تاريخُ القطع هو طرفُه الأعلى --}}
                            <input type="date" name="to_date" id="to_date" class="form-control" aria-label="إلى تاريخ"
                                   value="{{ request('to_date') ?: (isset($toDate) ? $toDate->format('Y-m-d') : now()->format('Y-m-d')) }}">
                        </div>
                    </div>

                    @include('client::customers.CustomerReport.partials.period-selector', [
                        'periodContext' => $periodContext ?? [],
                        'startId' => 'from_date',
                        'endId' => 'to_date',
                        'cols' => '',
                        'yearTitle' => 'السنة المالية',
                        'periodTitle' => 'الفترة المحاسبية',
                    ])

                    <div class="rpt-field">
                        <label for="supplier_id">المورد</label>
                        <select name="supplier_id" id="supplier_id" class="form-control rpt-select2" data-placeholder="جميع الموردين">
                            <option value="">جميع الموردين</option>
                            @foreach ($suppliers ?? [] as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                            {{-- المختارُ يبقى ولو خرج عن قائمة فرع الشريط --}}
                            @if ($selectedSupplier && ! ($suppliers ?? collect())->contains('id', $selectedSupplier->id))
                                <option value="{{ $selectedSupplier->id }}" selected>{{ $selectedSupplier->name }}</option>
                            @endif
                        </select>
                    </div>

                    {{-- نظيرُ «مسؤول المبيعات»: المورّدُ المسنَد إلى الموظّف أو المضافُ بمستخدمه --}}
                    <div class="rpt-field">
                        <label for="employee_id">الموظف المسؤول</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2" data-placeholder="كل الموظفين">
                            <option value="">كل الموظفين</option>
                            @foreach ($employees ?? [] as $employee)
                                <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="search">بحث بالاسم أو الكود</label>
                        <input type="text" name="search" id="search" class="form-control" value="{{ request('search') }}" placeholder="اسم المورد أو كوده">
                    </div>

                    {{-- عمرُ الدين: ما تجاوز عمرُه عدداً من الأيام — App\Support\DebtAge --}}
                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.debt-age-select', ['debtAgeHint' => 'الموردون الذين لهم مبلغ مفتوح أقدم من الحد'])
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    {{--
                        الأعمارُ صورةُ الدين كلِّه حتى تاريخ القطع («إلى تاريخ») — بدايةُ
                        المدى لا تقصّ شيئاً (SupplierReportController::buildAging).
                    --}}
                    <div class="ag-checks">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="show_zero" name="show_zero" value="1" {{ request('show_zero') ? 'checked' : '' }}>
                            <label class="form-check-label" for="show_zero">إظهار الموردين بلا رصيد</label>
                        </div>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.supplierDebtAging') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                {{-- «إجمالي المديونية» = ما علينا للموردين: أرصدةُ الدائنين وحدَهم.
                     الصافي بعد الأرصدة المدينة في شريط التسوية وتذييل الجدول. --}}
                <div class="ag-kpi__label">إجمالي المديونية</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['aging_total'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($creditorsCount) }} مورد دائن (له علينا)</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
        {{-- الأرصدةُ المدينة وحدَها — خارج جدول الأعمار ولا تُطرح من المديونية --}}
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">أرصدة مدينة للموردين</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['debit_balances'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['debit_suppliers'] ?? 0) }} مورد — لنا عليهم، تفصيلُها أسفل الجدول</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> إجمالي المبالغ المستحقة حسب فترة التأخير</h4>
            </div>
            <div class="rpt-card__body">
                @if ($bucketSum > 0)
                    <div class="ag-canvas"><canvas id="agingBar" aria-label="المبالغ حسب فترة التأخير" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا مبالغ مستحقة لعرضها</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع الديون حسب فترة التأخير</h4>
                <span class="ag-hint no-print">اضغط على فترة لعرض مورديها فقط</span>
            </div>
            <div class="rpt-card__body">
                <div class="ag-dist">
                    <table class="ag-legend">
                        <tbody>
                            @foreach ($agBuckets as $bucket)
                                <tr class="ag-legend__row {{ $currentAgingFilter === $bucket['filter'] ? 'is-active' : '' }}"
                                    style="--c:{{ $bucket['color'] }}" data-filter="{{ $bucket['filter'] }}" tabindex="0"
                                    title="{{ $currentAgingFilter === $bucket['filter'] ? 'إلغاء فلتر هذه الفترة' : 'عرض موردي هذه الفترة فقط' }}">
                                    <td><span class="ag-dot"></span>{{ $bucket['label'] }}</td>
                                    <td class="ag-legend__amount"><bdi id="{{ $bucket['element_id'] }}">{{ number_format($bucket['value'], 2) }}</bdi></td>
                                    <td class="ag-legend__pct">{{ $bucket['pct'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($bucketSum > 0)
                        <div class="ag-donut">
                            <canvas id="agingDonut" aria-label="توزيع الديون حسب فترة التأخير" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ number_format($bucketSum, 2) }}</strong>
                                <small>{{ $currency }}</small>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{--
        التسويةُ مع تقرير أرصدة الموردين: المديونيةُ (مجموعُ الشرائح) والأرصدةُ
        المدينة كلٌّ وحدَه، وصافيهما هو «الصافي» هناك. يُعلَن كي لا يُبحث عن
        سببِ فرقٍ لا يُرى — لا لطرح المدين من المديونية.
    --}}
    @if (!empty($totals['aging_total']) && ($totals['reconcile_available'] ?? true))
        <div class="alert alert-{{ ($totals['reconciles'] ?? false) ? 'success' : 'warning' }}" role="status">
            <i class="fas fa-balance-scale"></i>
            <strong>المديونية (مجموع الشرائح):</strong> <span dir="ltr">{{ number_format($totals['aging_total'], 2) }}</span>
            <span class="mx-2">|</span>
            <strong>أرصدة مدينة للموردين (منفصلة):</strong> <span dir="ltr">{{ number_format($totals['debit_balances'] ?? 0, 2) }}</span>
            <span class="mx-2">|</span>
            <strong>الصافي في أرصدة الموردين:</strong> <span dir="ltr">{{ number_format($totals['total_balance'] ?? 0, 2) }}</span>
            <span class="rpt-pill {{ ($totals['reconciles'] ?? false) ? 'rpt-pill-success' : 'rpt-pill-warning' }}">
                {{ ($totals['reconciles'] ?? false) ? 'مطابق لتقرير الأرصدة' : 'غير مطابق — راجِع' }}
            </span>
        </div>
    @endif

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> تفاصيل أعمار ديون الموردين
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} مورد</span>
            </h4>
            <div class="ag-tools no-print">
                @if ($activeBucket)
                    <button type="button" class="ag-filter-chip" style="--c:{{ $activeBucket['color'] }}" data-filter="{{ $activeBucket['filter'] }}" title="إلغاء فلتر الفترة">
                        {{ $activeBucket['label'] }} <i class="fas fa-times"></i>
                    </button>
                @endif
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="agingTable">
                <thead>
                    <tr>
                        <th>#</th><th>كود المورد</th><th>اسم المورد</th><th>تابع لـ</th><th>الحالة</th><th>الموظف المسؤول</th><th>الأيام</th>
                        <th>غير مستحق</th><th>1-30</th><th>31-60</th><th>61-90</th><th>91-120</th><th>+120</th><th>إجمالي المديونية</th><th>نوع الحساب</th>
                        <th class="ag-extra">فترة التأخير</th><th class="ag-extra">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse($reportData as $item)
                        @php
                            $daysLate = (int) ($item['days_late'] ?? 0);
                            $rowBucket = $bucketsByKey[$bucketForDays($daysLate)];
                            $statusColor = $item['supplier_status_color'] ?? '#6c757d';
                            $rowSupplierId = $item['supplier_id'] ?? null;
                        @endphp
                        <tr data-row="1">
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item['supplier_code'] ?? '-' }}</td>
                            <td class="rpt-name">
                                @if(!empty($item['supplier_id']))
                                    @include('purchases::purchases.report.partials.supplier-link', ['id' => $item['supplier_id'], 'name' => $item['supplier_name'] ?? ''])
                                @else
                                    <span class="text-muted">{{ $item['supplier_name'] ?? '-' }}</span>
                                @endif
                            </td>
                            {{-- «تابع لـ» يقول الجهةَ ونوعَها، لا الفرعَ المحاسبيَّ المشتقّ --}}
                            <td>
                                {{ $item['affiliation_name'] ?? '-' }}
                                @if(!empty($item['affiliation_type']))
                                    <div class="text-muted" style="font-size:11px">{{ $item['affiliation_type'] }}</div>
                                @endif
                            </td>
                            {{-- الحالةُ نشط/موقوف — للمورّد حالةٌ بلا «وضع» (مرحلة تسجيل) --}}
                            <td>
                                <span class="rpt-pill" style="background-color: {{ $statusColor }}; color: white;">{{ $item['supplier_status_name'] ?? '-' }}</span>
                            </td>
                            {{-- موضعُ «الهدف» عند العميل: للمورّد الموظّفُ المسؤول عنه --}}
                            <td><span class="rpt-pill" style="background-color: #6c757d; color: white;">{{ $item['employee_name'] ?? '-' }}</span></td>
                            <td><span class="ag-days" style="--c:{{ $rowBucket['color'] }}" title="عمرُ أقدم بندٍ مفتوح">{{ $daysLate }} يوم</span></td>
                            <td class="rpt-num">{{ number_format($item['today'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days1to30'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days31to60'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days61to90'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days91to120'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['daysOver120'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-warnc"><strong>{{ number_format($item['balance'] ?? 0, 2) }}</strong></td>
                            <td>
                                {{-- دائن = له علينا (المديونية)، مدين = لنا عليه --}}
                                <span class="rpt-pill {{ ($item['balance_type'] ?? 'دائن') === 'دائن' ? 'rpt-pill-danger' : 'rpt-pill-success' }}">{{ $item['balance_type'] ?? 'دائن' }}</span>
                                @if(!empty($item['wallet_balance']))
                                    <div class="text-muted" style="font-size:11px" title="رصيد محفظة المورد — أموال المورد نفسه لدينا، منفصل ولا يُحتسب ضمن الرصيد أعلاه">
                                        <i class="fas fa-wallet"></i> {{ number_format($item['wallet_balance'], 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="ag-extra"><span class="ag-bucket" style="--c:{{ $rowBucket['color'] }}">{{ $rowBucket['label'] }}</span></td>
                            <td class="ag-extra">
                                @if ($rowSupplierId && ($canViewSupplier || $canStatement))
                                    <div class="dropdown">
                                        <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات" title="إجراءات">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if ($canViewSupplier)
                                                <a class="dropdown-item" href="{{ route('SupplierManagement.show', $rowSupplierId) }}" target="_blank" rel="noopener"><i class="fas fa-truck me-2 text-info"></i> ملف المورد</a>
                                            @endif
                                            @if ($canStatement)
                                                <a class="dropdown-item" href="{{ route('SupplierManagement.statement', $rowSupplierId) }}" target="_blank" rel="noopener"><i class="fas fa-file-invoice me-2 text-primary"></i> كشف الحساب</a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="17" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                    @endforelse
                </tbody>
                @if(count($reportData) > 0)
                    <tfoot id="reportTableFooter">
                        <tr>
                            <td colspan="7">الإجمالي الكلي</td>
                            <td class="rpt-num">{{ number_format($totals['today'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['days1to30'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['days31to60'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['days61to90'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['days91to120'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($totals['daysOver120'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-warnc">{{ number_format($totals['debt_balance'] ?? 0, 2) }}</td>
                            <td></td>
                            <td class="ag-extra"></td>
                            <td class="ag-extra"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'مورد',
            'pagerStorage' => 'agSupplierAgingPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    {{-- ===================== الأرصدة المدينة — منفصلة ===================== --}}
    @if (!empty($debtors))
        <div class="rpt-card" id="debtorsCard">
            <div class="rpt-card__head">
                <h4>
                    <i class="fas fa-hand-holding-usd"></i> أرصدة مدينة للموردين
                    <span class="rpt-pill rpt-pill-success">{{ number_format(count($debtors)) }} مورد · {{ number_format($totals['debit_balances'] ?? 0, 2) }} {{ $currency }}</span>
                </h4>
                <span class="ag-hint">دفعنا لهم أكثرَ ممّا عليهم أو مقدَّماً — مبالغُ لنا عليهم، ليست ديناً علينا فلا تُعمَّر ولا تُطرح من المديونية</span>
            </div>
            <div class="rpt-scroll table-responsive">
                <table class="rpt-table ag-table">
                    <thead>
                        <tr><th>#</th><th>كود المورد</th><th>اسم المورد</th><th>تابع لـ</th><th>الحالة</th><th>المبلغ لنا على المورد</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($debtors as $debtor)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $debtor['supplier_code'] ?? '-' }}</td>
                                <td class="rpt-name">@include('purchases::purchases.report.partials.supplier-link', ['id' => $debtor['supplier_id'] ?? null, 'name' => $debtor['supplier_name'] ?? ''])</td>
                                <td>{{ $debtor['affiliation_name'] ?? '-' }}</td>
                                <td>{{ $debtor['supplier_status_name'] ?? '-' }}</td>
                                <td class="rpt-num rpt-pos"><strong>{{ number_format(abs($debtor['balance']), 2) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">إجمالي الأرصدة المدينة</td>
                            <td class="rpt-num rpt-pos">{{ number_format($totals['debit_balances'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    {{-- ذيلُ الطباعة بلا عمودَي «فترة التأخير» و«إجراءات» — يُملأ لحظةَ الطباعة --}}
    <table hidden><tfoot id="agPrintFooter"></tfoot></table>

    @include('sales::sals_report.report_partials.print-footer')
</div>

@include('sales::partials.table-menu')
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        const AG_BUCKETS = @json($chartBuckets);
        const AG_TOTAL = @json($bucketTotal);
        const AG_FILTER_LABELS = @json($agFilterLabels);
        let currentReportData = null;

        $(document).ready(function() {
            if ($.fn.select2) {
                $('.rpt-select2').each(function() {
                    var $el = $(this);
                    if ($el.data('select2')) { $el.select2('destroy'); }
                });
                $('.rpt-select2').select2({
                    dir: 'rtl',
                    language: { noResults: function() { return "لا توجد نتائج"; } },
                    allowClear: true,
                    width: '100%'
                });
            }

            $('.period-btn').click(function() { changePeriod($(this).data('period')); });

            /* فلترُ الشريحة: صفُّ جدول التوزيع، وشارةُ الفلتر فوق الجدول */
            $(document).on('click', '.ag-legend__row[data-filter], .ag-filter-chip[data-filter]', function() {
                applyBucketFilter(String($(this).attr('data-filter')));
            });
            $(document).on('keydown', '.ag-legend__row[data-filter]', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyBucketFilter(String($(this).attr('data-filter'))); }
            });

            $('#exportExcel').click(function() { exportDebtAgingToExcel(); });
            $('#printBtn').click(function() { printDebtAgingReport(); });

            renderCharts();
        });

        const SERVER_CHART_DATA = @json($chartData ?? ['aging_labels' => [], 'aging_values' => []]);
        const SERVER_TOTALS = @json($totals ?? []);
        const SERVER_REPORT_DATA = @json($reportData ?? []);
        currentReportData = { totals: SERVER_TOTALS, data: SERVER_REPORT_DATA, chart_data: SERVER_CHART_DATA };

        function applyBucketFilter(filter) {
            var current = $('#agingFilterInput').val();
            $('#agingFilterInput').val(current === filter ? '' : filter);
            $('#filterForm').submit();
        }

        function changePeriod(period) {
            $('#periodInput').val(period);
            $('#agingFilterInput').val('');

            if (period !== 'custom') {
                const today = new Date();
                const startDate = new Date(today);
                startDate.setDate(startDate.getDate() - parseInt(period));
                /* أزرارُ المدى السريع تُفرغ منتقيَي السنة والفترة: المتحكّم
                   يقدّمهما على حقلَي التاريخ، فلو بقيت قيمةٌ فيهما لظهر
                   الزرُّ مضغوطاً بينما يقرأ الاستعلامُ الفترةَ المختارة. */
                $('#fiscal_year_id, #accounting_period_id').val('');
                if ($.fn.select2) { $('#fiscal_year_id, #accounting_period_id').trigger('change.select2'); }

                $('#from_date').val(formatDateForInput(startDate));
                $('#to_date').val(formatDateForInput(today));
                $('#filterForm').submit();
            }
        }

        function renderCharts() {
            if (typeof Chart === 'undefined' || !(AG_TOTAL > 0)) return;

            var theme = agChartTheme();
            var muted = theme.muted;
            var line = theme.line;
            var surface = theme.surface;

            var labels = AG_BUCKETS.map(function(b) { return b.label; });
            var values = AG_BUCKETS.map(function(b) { return b.value; });
            var colors = AG_BUCKETS.map(function(b) { return b.color; });

            function tooltipLabel(context) {
                var b = AG_BUCKETS[context.dataIndex];
                return ' ' + formatNumber(b.value) + ' (' + b.pct + '%)';
            }

            function bindFilterClick(chart) {
                chart.canvas.style.cursor = 'pointer';
                chart.canvas.addEventListener('click', function(evt) {
                    var hit = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                    if (hit.length) applyBucketFilter(AG_BUCKETS[hit[0].index].filter);
                });
            }

            var barEl = document.getElementById('agingBar');
            if (barEl) {
                var valueLabels = agValueLabels(values);

                bindFilterClick(new Chart(barEl, {
                    type: 'bar',
                    data: { labels: AG_BUCKETS.map(function(b) { return b.short; }), datasets: [{ data: values, backgroundColor: colors, borderRadius: 6, maxBarThickness: 64 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: tooltipLabel } } },
                        scales: {
                            x: { reverse: true, grid: { display: false }, ticks: { color: muted, font: { size: 11 }, maxRotation: 0, autoSkip: false } },
                            y: { beginAtZero: true, position: 'right', grid: { color: line }, border: { display: false },
                                 ticks: { color: muted, callback: function(v) { return agWhole(v); } } }
                        }
                    },
                    plugins: [valueLabels]
                }));
            }

            var donutEl = document.getElementById('agingDonut');
            if (donutEl) {
                bindFilterClick(new Chart(donutEl, {
                    type: 'doughnut',
                    data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderColor: surface, borderWidth: 3, hoverOffset: 6 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: tooltipLabel } } }
                    }
                }));
            }
        }

        function formatNumber(n) { return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function exportDebtAgingToExcel() {
            if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'لا توجد بيانات لتصديرها' });
                return;
            }

            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();

            const totals = currentReportData.totals || {};
            const data = currentReportData.data || [];

            const tableData = [];
            data.forEach((item, index) => {
                tableData.push({
                    values: [
                        index + 1, item.supplier_code || '-', item.supplier_name || '-',
                        (item.affiliation_name || '-') + (item.affiliation_type ? ' (' + item.affiliation_type + ')' : ''),
                        item.supplier_status_name || '-', item.employee_name || '-',
                        (item.days_late || 0) + ' يوم',
                        formatNumber(item.today || 0), formatNumber(item.days1to30 || 0), formatNumber(item.days31to60 || 0),
                        formatNumber(item.days61to90 || 0), formatNumber(item.days91to120 || 0), formatNumber(item.daysOver120 || 0),
                        formatNumber(item.balance || 0), item.balance_type || 'دائن'
                    ],
                    alignments: ['center', 'center', 'right', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'],
                    cellStyles: [
                        null, null, null, null, null, null, null,
                        { color: 'success' }, { color: 'info' }, { color: 'warning' }, { color: 'danger' }, { color: 'primary' }, { color: 'gray' },
                        { color: 'primary', bold: true }, null
                    ]
                });
            });

            const config = {
                fileName: 'تقرير_أعمار_ديون_الموردين',
                mainTitle: 'تقرير أعمار ديون الموردين',
                subTitle: 'نظام إدارة المشتريات',
                reportInfo: Object.assign({ 'الفترة': `من ${fromDate} إلى ${toDate}`, 'تاريخ التصدير': new Date().toLocaleDateString('ar-SA') }, AG_FILTER_LABELS),
                statisticsCards: [
                    { title: 'غير مستحق', value: formatNumber(totals.today || 0), unit: ' ر.س', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
                    { title: '1-30 يوم', value: formatNumber(totals.days1to30 || 0), unit: ' ر.س', titleColor: 'info', valueColor: 'info', backgroundColor: 'grayLight', borderColor: 'info' },
                    { title: '31-60 يوم', value: formatNumber(totals.days31to60 || 0), unit: ' ر.س', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
                    { title: '61-90 يوم', value: formatNumber(totals.days61to90 || 0), unit: ' ر.س', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
                    { title: '91-120 يوم', value: formatNumber(totals.days91to120 || 0), unit: ' ر.س', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'purpleLight', borderColor: 'primary' },
                    { title: '+120 يوم', value: formatNumber(totals.daysOver120 || 0), unit: ' ر.س', titleColor: 'gray', valueColor: 'gray', backgroundColor: 'grayBg', borderColor: 'gray' },
                    { title: 'أرصدة مدينة للموردين (' + (totals.debit_suppliers || 0) + ') — منفصلة', value: formatNumber(totals.debit_balances || 0), unit: ' ر.س', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' }
                ],
                tableTitle: 'تفاصيل أعمار ديون الموردين',
                tableHeaders: ['#', 'كود المورد', 'اسم المورد', 'تابع لـ', 'الحالة', 'الموظف المسؤول', 'الأيام', 'غير مستحق', '1-30', '31-60', '61-90', '91-120', '+120', 'إجمالي المديونية', 'نوع الحساب'],
                columnWidths: [6, 12, 28, 16, 12, 16, 9, 12, 11, 11, 11, 11, 11, 15, 11],
                tableData: tableData,
                summaryRows: [{
                    values: ['', '', '', '', '', '', 'الإجمالي الكلي',
                        formatNumber(totals.today || 0), formatNumber(totals.days1to30 || 0), formatNumber(totals.days31to60 || 0),
                        formatNumber(totals.days61to90 || 0), formatNumber(totals.days91to120 || 0), formatNumber(totals.daysOver120 || 0),
                        formatNumber(totals.debt_balance || 0), ''
                    ],
                    mergeFrom: 1, mergeTo: 7, background: 'successLight', color: 'success',
                    cellStyles: [null, null, null, null, null, null, { bold: true, alignment: 'right' }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, null]
                }],
                buttonSelector: '#exportExcel'
            };

            exportToExcelProfessional(config);
        }

        function printDebtAgingReport() {
            if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'لا توجد بيانات لطباعتها' });
                return;
            }

            {{-- ذيلُ الطباعة يُنسخ بلا خلايا الأعمدة المضافة (ag-extra) --}}
            var foot = document.getElementById('reportTableFooter');
            var printFoot = document.getElementById('agPrintFooter');
            if (foot && printFoot) {
                var footRow = foot.rows[0].cloneNode(true);
                footRow.querySelectorAll('.ag-extra').forEach(function(cell) { cell.remove(); });
                printFoot.innerHTML = '';
                printFoot.appendChild(footRow);
            }

            printTable({
                tableSelector: '#reportTableBody tr',
                tfootSelector: foot ? '#agPrintFooter' : null,
                title: 'تقرير أعمار ديون الموردين',
                infoCards: [
                    { label: 'غير مستحق', value: formatNumber(currentReportData.totals.today || 0) + ' ر.س' },
                    { label: '1-30 يوم', value: formatNumber(currentReportData.totals.days1to30 || 0) + ' ر.س' },
                    { label: '31-60 يوم', value: formatNumber(currentReportData.totals.days31to60 || 0) + ' ر.س' },
                    { label: '61-90 يوم', value: formatNumber(currentReportData.totals.days61to90 || 0) + ' ر.س' },
                    { label: '91-120 يوم', value: formatNumber(currentReportData.totals.days91to120 || 0) + ' ر.س' },
                    { label: '+120 يوم', value: formatNumber(currentReportData.totals.daysOver120 || 0) + ' ر.س' },
                    { label: 'إجمالي المديونية', value: formatNumber(currentReportData.totals.debt_balance || 0) + ' ر.س', class: 'text-danger fw-bold' },
                    { label: 'أرصدة مدينة للموردين (منفصلة)', value: formatNumber(currentReportData.totals.debit_balances || 0) + ' ر.س', class: 'text-success' }
                ],
                filterInfo: Object.assign({ 'الفترة': `من ${$('#from_date').val()} إلى ${$('#to_date').val()}` }, AG_FILTER_LABELS),
                {{-- الطباعةُ تنسخ صفوفَ الجدول نفسِه، فرؤوسُها تتبع أعمدتَه --}}
                tableHeaders: ['#', 'كود المورد', 'اسم المورد', 'تابع لـ', 'الحالة', 'الموظف المسؤول', 'الأيام', 'غير مستحق', '1-30', '31-60', '61-90', '91-120', '+120', 'إجمالي المديونية', 'نوع الحساب'],
                columnWidths: ['3%', '7%', '13%', '9%', '8%', '7%', '5%', '7%', '6%', '6%', '6%', '6%', '6%', '9%', '6%'],
                // «فترة التأخير» و«إجراءات» (ag-extra) لا تُطبعان
                skipColumns: [15, 16],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

    </script>
@endsection
