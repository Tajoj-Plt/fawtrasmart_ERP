{{--
    تقرير أعمار ديون الأستاذ العام — على نمط التصميم المعتمد («أعمار ديون
    الفواتير»): ترويسةٌ فاتحة، شبكةُ فلترة، أربعةُ مؤشّرات، رسمان (أعمدة +
    حلقة بجدول توزيع)، ثمّ جدولُ العملاء بترقيم صفحات.

    ما لم يتغيّر:
    • حقولُ الفلترة وأسماؤها، وخانتا «بلا رصيد» و«الأقدم من بداية الفترة» —
      عدا «تمت الإضافة بواسطة»: صار «مسؤول المبيعات» وحده يغطّي المعنيين
      (العميلُ المسنَد إلى الموظّف أو المضافُ بمستخدمه).
    • أعمدةُ الجدول الخمسة عشر وبياناتُها؛ أُضيف بعدها عمودا «فترة التأخير»
      و«إجراءات» (`ag-extra`) ويُستثنيان من الطباعة. التصديرُ يُبنى من
      `currentReportData` لا من الجدول، فملفُّ Excel كما كان.
    • فلترُ الشرائح (`aging_filter`) — كان بكروت الشرائح الستّ، وصار بالنقر
      على صفّ الشريحة في جدول التوزيع أو على عمودها/قطعتها في الرسمين.
--}}
@extends('client::master')

@section('title')
    تقرير أعمار ديون الأستاذ العام
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

    $clientsCount = $totals['total_clients'] ?? count($reportData);
    $debtorsCount = $ledgerRows->filter(fn ($r) => (float) ($r['balance'] ?? 0) > 0.005)->count();

    $debtGuide = [
        'title' => __('client_debt.guide.title'),
        'lead' => __('client_debt.guide.ledger.lead'),
        'blocks' => [
            __('client_debt.guide.ledger.source_title') => __('client_debt.guide.ledger.source'),
            __('client_debt.guide.age_title') => __('client_debt.guide.age'),
            __('client_debt.guide.credit_title') => __('client_debt.guide.credit'),
            __('client_debt.guide.opening_title') => __('client_debt.guide.opening'),
        ],
        // بلا سطر «المتأخّر = تجاوز N يوماً» — مؤشّرُ المتأخّرين في لوحة العملاء وحدَها
        'facts' => array_slice(__('client_debt.guide.facts'), 1),
    ];

    $canViewClient = canAny(['clients_view_all_clients', 'clients_view_own_clients', 'clients_view_by_employee_groups', 'clients_view_by_daily_itinerary']);
    $canStatement = \Illuminate\Support\Facades\Route::has('ClientReport.customerAccountStatement') && hasPermission('clients.reports.statement');
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'user-clock',
        'headTitle' => 'أعمار ديون الأستاذ العام',
        'headSubtitle' => 'مديونية كل عميلٍ موزَّعةً على شرائح أعمارها — بندًا بندًا بتاريخ استحقاقه، حتى تاريخ القطع المختار',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'client-reports.debt-aging-ledger',
        'headGuide' => $debtGuide,
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" id="filterForm">
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
                            {{-- المدى الذي حُسب به التقرير — كان «أوّل الشهر» ثابتاً فيُضيَّق كلُّ بحثٍ تالٍ --}}
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
                        @include('reports.partials.client-select', [
                            'name' => 'customer',
                            'selectedClient' => $selectedClient ?? null,
                            'label' => 'العميل',
                        ])
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.sales-employee-select', [
                            'salesEmployees' => $salesEmployees ?? collect(),
                            'label' => 'مسؤول المبيعات',
                        ])
                    </div>

                    <div class="rpt-field">
                        <label for="status">حالة العميل</label>
                        <select name="status" id="status" class="form-control rpt-select2" data-placeholder="كل الحالات">
                            <option value="">كل الحالات</option>
                            @foreach($statuses ?? [] as $status)
                                <option value="{{ $status->id }}" {{ request('status') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.stage-select', [
                            'registrationStages' => $registrationStages ?? collect(),
                            'label' => 'وضع العميل',
                        ])
                    </div>

                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.target-select', [
                            'customerTargets' => $customerTargets ?? collect(),
                            'label' => 'هدف العميل',
                        ])
                    </div>

                    <div class="rpt-field">
                        <label for="category_client">تصنيف العميل</label>
                        <select name="category_client" id="category_client" class="form-control rpt-select2">
                            <option value="">جميع التصنيفات</option>
                            @foreach($categories ?? [] as $category)
                                <option value="{{ $category->id }}" {{ request('category_client') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="neighborhood">الحي</label>
                        <select name="neighborhood" id="neighborhood" class="form-control rpt-select2">
                            <option value="">جميع الأحياء</option>
                            @foreach($neighborhoods ?? [] as $nh)
                                <option value="{{ $nh->name }}" data-region-id="{{ $nh->region_id }}" {{ request('neighborhood') == $nh->name ? 'selected' : '' }}>{{ $nh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- عمرُ الدين: ما تجاوز عمرُه عدداً من الأيام — App\Support\DebtAge --}}
                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.debt-age-select', ['debtAgeHint' => 'العملاء الذين لهم مبلغ مفتوح أقدم من الحد'])
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    {{--
                        الأعمارُ صورةُ الدين كلِّه حتى تاريخ القطع («إلى تاريخ») — بدايةُ
                        المدى لا تقصّ شيئاً (CustomerDebtAgingController::reportRows)،
                        فسقطت خانةُ «الأقدم من بداية الفترة».
                    --}}
                    <div class="ag-checks">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="show_zero" name="show_zero" value="1" {{ request('show_zero') ? 'checked' : '' }}>
                            <label class="form-check-label" for="show_zero">إظهار العملاء بلا رصيد</label>
                        </div>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ url()->current() }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                {{-- «إجمالي المديونية» بتعريف لوحة العملاء: أرصدةُ المدينين وحدَهم.
                     الصافي بعد الأرصدة الدائنة في شريط التسوية وتذييل الجدول. --}}
                <div class="ag-kpi__label">إجمالي المديونية</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['aging_total'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($debtorsCount) }} عميل مدين</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
        </div>
        {{-- بطاقتا «المتأخّرين» (تجاوز N يوماً) حُذفتا من التقارير بطلب المستخدم
             (2026-09-16) — تبقيان في لوحة العملاء وحدَها --}}
        {{-- الأرصدةُ الدائنة وحدَها — خارج جدول الأعمار ولا تُطرح من المديونية --}}
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">أرصدة دائنة للعملاء</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['credit_balances'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['credit_clients'] ?? 0) }} عميل — لهم علينا، تفصيلُها أسفل الجدول</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        {{-- الرصيدُ الافتتاحي كما في القيد، وما لم يُسدَّد منه بعدُ — ليُعرف أيُّ
             الأرصدة المرحَّلة ما زال قائماً --}}
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">لم يُسدَّد من الرصيد الافتتاحي</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['opening_remaining'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['opening_open_clients'] ?? 0) }} عميل — من رصيد افتتاحي <bdi>{{ number_format($totals['opening_entries'] ?? 0, 2) }}</bdi></div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-flag"></i></span>
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
                <span class="ag-hint no-print">اضغط على فترة لعرض عملائها فقط</span>
            </div>
            <div class="rpt-card__body">
                <div class="ag-dist">
                    <table class="ag-legend">
                        <tbody>
                            @foreach ($agBuckets as $bucket)
                                <tr class="ag-legend__row {{ $currentAgingFilter === $bucket['filter'] ? 'is-active' : '' }}"
                                    style="--c:{{ $bucket['color'] }}" data-filter="{{ $bucket['filter'] }}" tabindex="0"
                                    title="{{ $currentAgingFilter === $bucket['filter'] ? 'إلغاء فلتر هذه الفترة' : 'عرض عملاء هذه الفترة فقط' }}">
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
        التسويةُ مع تقرير أرصدة العملاء: المديونيةُ (مجموعُ الشرائح) والأرصدةُ
        الدائنة كلٌّ وحدَه، وصافيهما هو «الصافي» هناك. يُعلَن كي لا يُبحث عن
        سببِ فرقٍ لا يُرى — لا لطرح الدائن من المديونية.
    --}}
    @if (!empty($totals['aging_total']) && ($totals['reconcile_available'] ?? true))
        <div class="alert alert-{{ ($totals['reconciles'] ?? false) ? 'success' : 'warning' }}" role="status">
            <i class="fas fa-balance-scale"></i>
            <strong>المديونية (مجموع الشرائح):</strong> <span dir="ltr">{{ number_format($totals['aging_total'], 2) }}</span>
            <span class="mx-2">|</span>
            <strong>أرصدة دائنة للعملاء (منفصلة):</strong> <span dir="ltr">{{ number_format($totals['credit_balances'] ?? 0, 2) }}</span>
            <span class="mx-2">|</span>
            <strong>الصافي في أرصدة العملاء:</strong> <span dir="ltr">{{ number_format($totals['total_balance'] ?? 0, 2) }}</span>
            <span class="rpt-pill {{ ($totals['reconciles'] ?? false) ? 'rpt-pill-success' : 'rpt-pill-warning' }}">
                {{ ($totals['reconciles'] ?? false) ? 'مطابق لتقرير الأرصدة' : 'غير مطابق — راجِع' }}
            </span>
        </div>
    @endif

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> تفاصيل أعمار ديون الأستاذ العام
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} عميل</span>
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
                        <th>#</th><th>كود العميل</th><th>اسم العميل</th><th>تابع لـ</th><th>الحالة</th><th>الهدف</th><th>الأيام</th>
                        <th title="قيود الرصيد الافتتاحي كما في القيد">رصيد افتتاحي</th>
                        <th title="ما بقي من الرصيد الافتتاحي بعد أن سدّدت التحصيلات أقدم الدين أولاً — وهو داخل شرائح الأعمار">لم يُسدَّد منه</th>
                        <th>غير مستحق</th><th>1-30</th><th>31-60</th><th>61-90</th><th>91-120</th><th>+120</th><th>إجمالي المديونية</th><th>نوع الحساب</th>
                        <th class="ag-extra">فترة التأخير</th><th class="ag-extra">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    @forelse($reportData as $item)
                        @php
                            $daysLate = (int) ($item['days_late'] ?? 0);
                            $rowBucket = $bucketsByKey[\Modules\Client\Services\CustomerLedgerService::bucketFor($daysLate)];
                            $statusColor = $item['client_status_color'] ?? '#6c757d';
                            $rowClientId = $item['client_id'] ?? null;
                        @endphp
                        <tr data-row="1">
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item['client_code'] ?? '-' }}</td>
                            <td class="rpt-name">
                                @if(!empty($item['client_id']))
                                    @include('reports.partials.client-link', ['id' => $item['client_id'], 'name' => $item['client_name'] ?? ''])
                                @else
                                    <span class="text-muted">{{ $item['client_name'] ?? '-' }}</span>
                                @endif
                            </td>
                            {{-- «تابع لـ» يقول الجهةَ ونوعَها، لا الفرعَ المحاسبيَّ المشتقّ --}}
                            <td>
                                {{ $item['affiliation_name'] ?? '-' }}
                                @if(!empty($item['affiliation_type']))
                                    <div class="text-muted" style="font-size:11px">{{ $item['affiliation_type'] }}</div>
                                @endif
                            </td>
                            {{-- الحالةُ والوضعُ في عمودٍ واحد: الحالةُ نشط/موقوف، والوضعُ
                                 مرحلةُ التسجيل تحتها — حقلان مستقلّان يُقرآن معاً. --}}
                            <td>
                                <span class="rpt-pill" style="background-color: {{ $statusColor }}; color: white;">{{ $item['client_status_name'] ?? '-' }}</span>
                                @if(!empty($item['stage_name']))
                                    <div class="mt-1">
                                        <span class="rpt-pill" style="background-color: {{ $item['stage_color'] ?? '#6c757d' }}; color: white;">{{ $item['stage_name'] }}</span>
                                    </div>
                                @endif
                            </td>
                            <td><span class="rpt-pill" style="background-color: {{ $item['target_color'] ?? '#6c757d' }}; color: white;">{{ $item['target_name'] ?? '-' }}</span></td>
                            <td><span class="ag-days" style="--c:{{ $rowBucket['color'] }}" title="عمرُ أقدم بندٍ مفتوح">{{ $daysLate }} يوم</span></td>
                            <td class="rpt-num">{{ number_format($item['opening_entries'] ?? 0, 2) }}</td>
                            <td class="rpt-num {{ ($item['opening_remaining'] ?? 0) > 0.005 ? 'rpt-warnc' : '' }}">{{ number_format($item['opening_remaining'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['today'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days1to30'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days31to60'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days61to90'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['days91to120'] ?? 0, 2) }}</td>
                            <td class="rpt-num">{{ number_format($item['daysOver120'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-warnc"><strong>{{ number_format($item['balance'] ?? 0, 2) }}</strong></td>
                            <td>
                                <span class="rpt-pill {{ ($item['balance_type'] ?? 'مدين') === 'مدين' ? 'rpt-pill-danger' : 'rpt-pill-success' }}">{{ $item['balance_type'] ?? 'مدين' }}</span>
                                @if(!empty($item['wallet_balance']))
                                    <div class="text-muted" style="font-size:11px" title="رصيد محفظة العميل — أموال العميل نفسه لدينا، منفصل ولا يُحتسب ضمن الرصيد أعلاه">
                                        <i class="fas fa-wallet"></i> {{ number_format($item['wallet_balance'], 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="ag-extra"><span class="ag-bucket" style="--c:{{ $rowBucket['color'] }}">{{ $rowBucket['label'] }}</span></td>
                            <td class="ag-extra">
                                @if ($rowClientId && ($canViewClient || $canStatement))
                                    <div class="dropdown">
                                        <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات" title="إجراءات">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if ($canViewClient)
                                                <a class="dropdown-item" href="{{ route('clients.show', $rowClientId) }}" target="_blank" rel="noopener"><i class="fas fa-user me-2 text-info"></i> ملف العميل</a>
                                            @endif
                                            @if ($canStatement)
                                                <a class="dropdown-item" href="{{ route('ClientReport.customerAccountStatement', ['client_id' => $rowClientId]) }}" target="_blank" rel="noopener"><i class="fas fa-file-invoice me-2 text-primary"></i> كشف الحساب</a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="19" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                    @endforelse
                </tbody>
                @if(count($reportData) > 0)
                    <tfoot id="reportTableFooter">
                        <tr>
                            <td colspan="7">الإجمالي الكلي</td>
                            <td class="rpt-num">{{ number_format($totals['opening_entries'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-warnc">{{ number_format($totals['opening_remaining'] ?? 0, 2) }}</td>
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
            'pagerUnit' => 'عميل',
            'pagerStorage' => 'agLedgerAgingPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    {{-- ===================== الأرصدة الدائنة — منفصلة ===================== --}}
    @if (!empty($creditors))
        <div class="rpt-card" id="creditorsCard">
            <div class="rpt-card__head">
                <h4>
                    <i class="fas fa-hand-holding-usd"></i> أرصدة دائنة للعملاء
                    <span class="rpt-pill rpt-pill-success">{{ number_format(count($creditors)) }} عميل · {{ number_format($totals['credit_balances'] ?? 0, 2) }} {{ $currency }}</span>
                </h4>
                <span class="ag-hint">دفعوا أكثرَ ممّا عليهم أو مقدَّماً — مبالغُ لهم علينا، ليست ديناً فلا تُعمَّر ولا تُطرح من المديونية</span>
            </div>
            <div class="rpt-scroll table-responsive">
                <table class="rpt-table ag-table">
                    <thead>
                        <tr><th>#</th><th>كود العميل</th><th>اسم العميل</th><th>تابع لـ</th><th>الحالة</th><th>المبلغ لصالح العميل</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($creditors as $creditor)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $creditor['client_code'] ?? '-' }}</td>
                                <td class="rpt-name">@include('reports.partials.client-link', ['id' => $creditor['client_id'] ?? null, 'name' => $creditor['client_name'] ?? ''])</td>
                                <td>{{ $creditor['affiliation_name'] ?? '-' }}</td>
                                <td>{{ $creditor['client_status_name'] ?? '-' }}</td>
                                <td class="rpt-num rpt-pos"><strong>{{ number_format(abs($creditor['balance']), 2) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">إجمالي الأرصدة الدائنة</td>
                            <td class="rpt-num rpt-pos">{{ number_format($totals['credit_balances'] ?? 0, 2) }}</td>
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
            const branchName = $('#branch option:selected').text() || 'جميع الفروع';
            const customerName = $('#customer option:selected').text() || 'جميع العملاء';
            const groupName = $('#region_group option:selected').text() || 'جميع المجموعات';

            const totals = currentReportData.totals || {};
            const data = currentReportData.data || [];

            const tableData = [];
            data.forEach((item, index) => {
                tableData.push({
                    values: [
                        index + 1, item.client_code || '-', item.client_name || '-',
                        (item.affiliation_name || '-') + (item.affiliation_type ? ' (' + item.affiliation_type + ')' : ''),
                        item.client_status_name || '-', item.stage_name || '—', item.target_name || '-',
                        (item.days_late || 0) + ' يوم',
                        formatNumber(item.opening_entries || 0), formatNumber(item.opening_remaining || 0),
                        formatNumber(item.today || 0), formatNumber(item.days1to30 || 0), formatNumber(item.days31to60 || 0),
                        formatNumber(item.days61to90 || 0), formatNumber(item.days91to120 || 0), formatNumber(item.daysOver120 || 0),
                        formatNumber(item.balance || 0), item.balance_type || 'مدين'
                    ],
                    alignments: ['center', 'center', 'right', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'],
                    cellStyles: [
                        null, null, null, null, null, null, null, null,
                        null, { color: 'warning' },
                        { color: 'success' }, { color: 'info' }, { color: 'warning' }, { color: 'danger' }, { color: 'primary' }, { color: 'gray' },
                        { color: 'primary', bold: true }, null
                    ]
                });
            });

            const config = {
                fileName: 'تقرير_أعمار_ديون_الأستاذ_العام',
                mainTitle: 'تقرير أعمار ديون الأستاذ العام',
                subTitle: 'نظام إدارة العملاء',
                reportInfo: { 'الفترة': `من ${fromDate} إلى ${toDate}`, 'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'), 'الفرع': branchName, 'العميل': customerName, 'المجموعة': groupName },
                statisticsCards: [
                    { title: 'غير مستحق', value: formatNumber(totals.today || 0), unit: ' ر.س', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
                    { title: '1-30 يوم', value: formatNumber(totals.days1to30 || 0), unit: ' ر.س', titleColor: 'info', valueColor: 'info', backgroundColor: 'grayLight', borderColor: 'info' },
                    { title: '31-60 يوم', value: formatNumber(totals.days31to60 || 0), unit: ' ر.س', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
                    { title: '61-90 يوم', value: formatNumber(totals.days61to90 || 0), unit: ' ر.س', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
                    { title: '91-120 يوم', value: formatNumber(totals.days91to120 || 0), unit: ' ر.س', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'purpleLight', borderColor: 'primary' },
                    { title: '+120 يوم', value: formatNumber(totals.daysOver120 || 0), unit: ' ر.س', titleColor: 'gray', valueColor: 'gray', backgroundColor: 'grayBg', borderColor: 'gray' },
                    { title: 'رصيد افتتاحي', value: formatNumber(totals.opening_entries || 0), unit: ' ر.س', borderColor: 'info' },
                    { title: 'لم يُسدَّد من الافتتاحي (' + (totals.opening_open_clients || 0) + ')', value: formatNumber(totals.opening_remaining || 0), unit: ' ر.س', borderColor: 'warning' },
                    { title: 'أرصدة دائنة للعملاء (' + (totals.credit_clients || 0) + ') — منفصلة', value: formatNumber(totals.credit_balances || 0), unit: ' ر.س', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' }
                ],
                tableTitle: 'تفاصيل أعمار ديون العملاء',
                tableHeaders: ['#', 'كود العميل', 'اسم العميل', 'تابع لـ', 'الحالة', 'الوضع', 'الهدف', 'الأيام', 'رصيد افتتاحي', 'لم يُسدَّد منه', 'غير مستحق', '1-30', '31-60', '61-90', '91-120', '+120', 'إجمالي المديونية', 'نوع الحساب'],
                columnWidths: [6, 12, 28, 16, 12, 12, 12, 9, 13, 13, 12, 11, 11, 11, 11, 11, 15, 11],
                tableData: tableData,
                summaryRows: [{
                    values: ['', '', '', '', '', '', '', 'الإجمالي الكلي',
                        formatNumber(totals.opening_entries || 0), formatNumber(totals.opening_remaining || 0),
                        formatNumber(totals.today || 0), formatNumber(totals.days1to30 || 0), formatNumber(totals.days31to60 || 0),
                        formatNumber(totals.days61to90 || 0), formatNumber(totals.days91to120 || 0), formatNumber(totals.daysOver120 || 0),
                        formatNumber(totals.debt_balance || 0), ''
                    ],
                    mergeFrom: 1, mergeTo: 8, background: 'successLight', color: 'success',
                    cellStyles: [null, null, null, null, null, null, null, { bold: true, alignment: 'right' }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }]
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
                title: 'تقرير أعمار ديون الأستاذ العام',
                infoCards: [
                    { label: 'غير مستحق', value: formatNumber(currentReportData.totals.today || 0) + ' ر.س' },
                    { label: '1-30 يوم', value: formatNumber(currentReportData.totals.days1to30 || 0) + ' ر.س' },
                    { label: '31-60 يوم', value: formatNumber(currentReportData.totals.days31to60 || 0) + ' ر.س' },
                    { label: '61-90 يوم', value: formatNumber(currentReportData.totals.days61to90 || 0) + ' ر.س' },
                    { label: '91-120 يوم', value: formatNumber(currentReportData.totals.days91to120 || 0) + ' ر.س' },
                    { label: '+120 يوم', value: formatNumber(currentReportData.totals.daysOver120 || 0) + ' ر.س' },
                    { label: 'إجمالي المديونية', value: formatNumber(currentReportData.totals.debt_balance || 0) + ' ر.س', class: 'text-danger fw-bold' },
                    { label: 'رصيد افتتاحي', value: formatNumber(currentReportData.totals.opening_entries || 0) + ' ر.س' },
                    { label: 'لم يُسدَّد من الافتتاحي', value: formatNumber(currentReportData.totals.opening_remaining || 0) + ' ر.س', class: 'text-warning' },
                    { label: 'أرصدة دائنة للعملاء (منفصلة)', value: formatNumber(currentReportData.totals.credit_balances || 0) + ' ر.س', class: 'text-success' }
                ],
                filterInfo: {
                    'الفترة': `من ${$('#from_date').val()} إلى ${$('#to_date').val()}`,
                    'الفرع': $('#branch option:selected').text() || 'جميع الفروع',
                    'العميل': $('#customer option:selected').text() || 'جميع العملاء',
                    'المجموعة': $('#region_group option:selected').text() || 'جميع المجموعات'
                },
                {{-- الطباعةُ تنسخ صفوفَ الجدول نفسِه، فرؤوسُها تتبع أعمدتَه:
                     «الوضع» صار داخل «الحالة» فسقط رأسُه هنا أيضاً. --}}
                tableHeaders: ['#', 'كود العميل', 'اسم العميل', 'تابع لـ', 'الحالة', 'الهدف', 'الأيام', 'رصيد افتتاحي', 'لم يُسدَّد منه', 'غير مستحق', '1-30', '31-60', '61-90', '91-120', '+120', 'إجمالي المديونية', 'نوع الحساب'],
                columnWidths: ['3%', '6%', '12%', '8%', '7%', '6%', '4%', '6%', '6%', '6%', '5%', '5%', '5%', '5%', '5%', '8%', '5%'],
                // «فترة التأخير» و«إجراءات» (ag-extra) لا تُطبعان
                skipColumns: [17, 18],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

    </script>
@endsection
