{{--
    تقرير أرصدة الموردين — مرآةُ «أرصدة العملاء»
    (`client::customers.CustomerReport.customer_blances`) قسماً قسماً، والطرفُ
    وحدَه مبدَّل: المورّدُ مكانَ العميل، والدائنُ (له علينا) مكانَ المدين
    (عليه لنا).

    الترتيبُ نفسُه: ترويسةٌ فاتحة، شريطُ حالة الفترة، شبكةُ فلترةٍ تعلوها
    أزرارُ الفترات، سبعةُ مؤشّرات، رسمان (أعلى عشرة أرصدةٍ دائنة + حلقةُ
    توزيع الدائن/المدين/الصفري بجدولها)، ثمّ الجدول أو البطاقات، ثمّ
    مصدرُ الطباعة المخفيّ. التصديرُ يُبنى من `$reportData` والطباعةُ من
    `#printSourceTable`، فلا يقرأ أيٌّ منهما الجدولَ المعروض.

    ما لا نظيرَ له في المورّد سقط لا استُبدل: «الوضع» و«الهدف» و«التصنيف»
    حقولُ عميلٍ لا مورّد. و«الحي» صار «المدينة»، و«مسؤول المبيعات» صار
    «مسؤول المشتريات» (`suppliers.employee_id`).

    المعادلةُ المحاسبية بطرفَيها كما في تقرير العملاء، مقلوبةً لأنّ الحساب
    التزام: (رصيدٌ افتتاحيٌّ + حركاتٌ سابقة) + دائنٌ − مدينٌ = ختامي.
--}}
@extends('purchases::master')

@section('title')
    تقرير أرصدة الموردين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt-client-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
        .rpt-client-link:hover { color: var(--app-primary, #7367f0); border-bottom-style: solid; }
        @media print { .rpt-client-link { border-bottom: 0; } }
    </style>
@endsection

@section('content')
@php
    // «عمر الدين» المطبَّق — عمودُ «الأيام» يصير عمرَ أقدم دين معه
    $debtAgeOn = $totals['debt_age'] ?? null;
    $periodLabel = 'من '.$fromDate->format('d/m/Y').' إلى '.$toDate->format('d/m/Y');
    $currency = \App\Support\CurrencySettings::name();

    /* توزيعُ الأرصدة الختامية: دائنٌ (له علينا) / مدينٌ (عليه لنا) / صفري.
       الحسابُ التزام، فالموجبُ هو ما ندين به للمورّد */
    $blGroups = [
        'credit' => ['label' => 'أرصدة دائنة — لهم علينا', 'color' => '#ef4444', 'count' => 0, 'sum' => 0.0],
        'debit'  => ['label' => 'أرصدة مدينة — عليهم لنا', 'color' => '#10b981', 'count' => 0, 'sum' => 0.0],
        'zero'   => ['label' => 'أرصدة صفرية', 'color' => '#64748b', 'count' => 0, 'sum' => 0.0],
    ];
    $blCreditRows = [];
    foreach ($reportData as $blItem) {
        $blBalance = (float) ($blItem['balance'] ?? 0);
        $blKey = $blBalance > 0.005 ? 'credit' : ($blBalance < -0.005 ? 'debit' : 'zero');
        $blGroups[$blKey]['count']++;
        $blGroups[$blKey]['sum'] += abs($blBalance);
        if ($blKey === 'credit') {
            $blCreditRows[] = ['name' => (string) ($blItem['supplier_name'] ?? 'غير محدد'), 'value' => round($blBalance, 2)];
        }
    }
    $blCount = count($reportData);
    foreach ($blGroups as $blKey => $blGroup) {
        $blGroups[$blKey]['sum'] = round($blGroup['sum'], 2);
        $blGroups[$blKey]['pct'] = $blCount > 0 ? round($blGroup['count'] / $blCount * 100) : 0;
    }
    usort($blCreditRows, fn ($a, $b) => $b['value'] <=> $a['value']);
    $blTopCredit = array_map(fn ($r) => [
        'name' => $r['name'],
        'short' => mb_strlen($r['name']) > 16 ? mb_substr($r['name'], 0, 15).'…' : $r['name'],
        'value' => $r['value'],
    ], array_slice($blCreditRows, 0, 10));
    $blChartGroups = array_values(array_map(fn ($g) => [
        'label' => $g['label'], 'color' => $g['color'], 'count' => $g['count'], 'sum' => $g['sum'], 'pct' => $g['pct'],
    ], $blGroups));

    $canViewSupplier = hasPermission('purchases.suppliers.view') && \Illuminate\Support\Facades\Route::has('SupplierManagement.show');
    $canStatement = hasPermission('purchases.suppliers.view_statement') && \Illuminate\Support\Facades\Route::has('SupplierManagement.statement');

    $guide = [
        'title' => 'ما الذي يعرضه تقرير أرصدة الموردين؟',
        'lead' => 'ما استقرّ على كل مورّد حتى تاريخ القطع، وكيف وصل إليه: رصيدٌ افتتاحيٌّ وحركاتٌ سابقة ثمّ مدينُ المدى ودائنُه.',
        'blocks' => [
            'معادلة الرصيد' => '(رصيدٌ افتتاحيٌّ + حركاتٌ سابقة) + إجمالي الدائن − إجمالي المدين = الرصيد النهائي.',
            'اتجاه الرصيد' => 'دائنٌ = مستحقٌّ للمورّد (له علينا)، ومدينٌ = دفعنا له أكثرَ ممّا علينا (عليه لنا).',
            'الأيام' => 'منذ آخر فاتورةِ شراءٍ حمّلت حسابَ المورّد حتى تاريخ القطع — لا يتجاوز اليوم.',
        ],
        'facts' => ['«رصيد افتتاحي» = قيودُ الرصيد الافتتاحي وحدَها؛ و«حركات سابقة» = فواتيرُ وسنداتٌ قبل المدى', 'الأرقامُ من دفتر المورّد نفسِه فتطابق كشفَ الحساب'],
    ];
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'wallet',
        'headTitle' => 'أرصدة الموردين',
        'headSubtitle' => 'أرصدة الموردين وما علينا لهم — افتتاحيٌّ ومدينٌ ودائنٌ وختاميّ، حتى تاريخ القطع المختار',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'purchases-reports.supplier-balances',
        'headGuide' => $guide,
        'headCrumbLabel' => 'تقارير المشتريات',
        'headCrumbUrl' => route('ReportsPurchases.index'),
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('ReportsPurchases.balanceSuppliers') }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ request('period') ?? '30' }}">

                {{-- ─────────── الفترات السريعة ─────────── --}}
                @include('client::customers.CustomerReport.partials.report-period-tabs', [
                    'tabCurrent' => (string) (request('period') ?: '30'),
                ])

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="from_date">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="from_date" class="form-control" aria-label="من تاريخ" value="{{ request('from_date', $fromDate->format('Y-m-d')) }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="to_date" id="to_date" class="form-control" aria-label="إلى تاريخ" value="{{ request('to_date', $toDate->format('Y-m-d')) }}">
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
                        <select name="supplier_id" id="supplier_id" class="form-control rpt-select2" data-placeholder="كل الموردين — ابحث بالاسم أو الكود">
                            <option value=""></option>
                            @foreach ($suppliers ?? [] as $supplier)
                                <option value="{{ $supplier->id }}" {{ (string) request('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->number_suply }} - {{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- «مسؤول المشتريات» يطابق الموظّفَ المسؤول عن المورّد (`suppliers.employee_id`) —
                         نظيرُ «مسؤول المبيعات» في تقرير العملاء --}}
                    <div class="rpt-field">
                        <label for="responsible_employee">مسؤول المشتريات</label>
                        <select name="responsible_employee" id="responsible_employee" class="form-control rpt-select2" data-placeholder="كل مسؤولي المشتريات">
                            <option value="">كل مسؤولي المشتريات</option>
                            @foreach ($responsibleEmployees ?? [] as $employee)
                                <option value="{{ $employee->id }}" {{ (string) request('responsible_employee') === (string) $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="status">حالة المورد</label>
                        <select name="status" id="status" class="form-control rpt-select2" data-placeholder="كل الحالات">
                            <option value="">كل الحالات</option>
                            @foreach ($supplierStatuses ?? [] as $statusKey => $statusMeta)
                                <option value="{{ $statusKey }}" {{ request('status') === $statusKey ? 'selected' : '' }}>{{ $statusMeta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="city">المدينة</label>
                        <select name="city" id="city" class="form-control rpt-select2">
                            <option value="">جميع المدن</option>
                            @foreach ($cities ?? [] as $city)
                                <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- نوعُ الرصيد: لعرض الأرصدة الدائنة (لصالح الموردين) وحدَها أو المدينين وحدَهم --}}
                    <div class="rpt-field">
                        <label for="balance_side">نوع الرصيد</label>
                        <select name="balance_side" id="balance_side" class="form-control rpt-select2" data-placeholder="كل الأرصدة">
                            <option value="">كل الأرصدة</option>
                            <option value="credit" {{ request('balance_side') === 'credit' ? 'selected' : '' }}>دائن — له علينا</option>
                            <option value="debit" {{ request('balance_side') === 'debit' ? 'selected' : '' }}>مدين — عليه لنا</option>
                            <option value="zero" {{ request('balance_side') === 'zero' ? 'selected' : '' }}>صفري</option>
                        </select>
                    </div>

                    {{-- نوعُ العرض زرّا إرسال: الملخّصُ جدولٌ، والتفاصيلُ بطاقةٌ لكلّ مورّد --}}
                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle"><i class="fas fa-list"></i> الملخص</button>
                            <button type="submit" name="view_type" value="details" class="btn {{ !$isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle"><i class="fas fa-th-list"></i> التفاصيل</button>
                        </div>
                    </div>

                    {{-- عمرُ الدين: ما تجاوز عمرُه عدداً من الأيام — App\Support\DebtAge --}}
                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.debt-age-select', ['debtAgeHint' => 'الموردون الذين لهم مبلغ غير مسدَّد أقدم من الحد'])
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    <div class="form-check">
                        <input type="checkbox" name="show_zero" class="form-check-input" id="show_zero" {{ request('show_zero') ? 'checked' : '' }} value="1">
                        <label class="form-check-label" for="show_zero">إظهار الأرصدة الصفرية</label>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.balanceSuppliers') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    {{-- البطاقاتُ تقرأ المعادلةَ المحاسبية للمدى بطرفَيها، ثمّ الختاميَّ مفصولاً:
         المستحقُّ للموردين وحدَه، والأرصدةُ المدينة وحدَها. --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">إجمالي الموردين</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['total_suppliers'] ?? 0 }}</bdi></div>
                <div class="ag-kpi__sub">دائن {{ number_format($blGroups['credit']['count']) }} · مدين {{ number_format($blGroups['debit']['count']) }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        {{-- ما قبل الفترة بطاقتان: قيودُ الرصيد الافتتاحي وحدَها، ثمّ الفواتيرُ
             والسنداتُ السابقة — كي لا تُقرأ فاتورةٌ قديمةٌ «رصيداً افتتاحياً» --}}
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">رصيد افتتاحي</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['opening_entries'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · قيود الرصيد الافتتاحي</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-flag"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">حركات سابقة</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['prior_movements'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · فواتير وسندات قبل الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-history"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي المدين</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['total_debit'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · حركات الفترة — مدفوعات ومرتجعات</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-arrow-down"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">إجمالي الدائن</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['total_credit'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · حركات الفترة — فواتير شراء</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-arrow-up"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                {{-- المستحقُّ للموردين = أرصدةُ الدائنين وحدَهم — رقمُ «مستحق للموردين» في
                     لوحة المشتريات. والأرصدةُ المدينة بطاقةٌ مستقلّة لا تُطرح منه. --}}
                <div class="ag-kpi__label">مستحق للموردين</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['payable_total'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['payable_suppliers'] ?? 0) }} مورد دائن — لهم علينا</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">أرصدة مدينة على الموردين</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['receivable_total'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['receivable_suppliers'] ?? 0) }} مورد دفعنا له زيادةً أو مقدَّماً — عليهم لنا</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> أعلى 10 أرصدة دائنة</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($blTopCredit) > 0)
                    <div class="ag-canvas"><canvas id="balancesTopBar" aria-label="أعلى الأرصدة الدائنة" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا أرصدة دائنة لعرضها</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع الموردين حسب نوع الرصيد</h4>
            </div>
            <div class="rpt-card__body">
                @if ($blCount > 0)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($blGroups as $blGroup)
                                    <tr class="ag-legend__row" style="--c:{{ $blGroup['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $blGroup['label'] }} <span class="text-muted">({{ number_format($blGroup['count']) }})</span></td>
                                        <td class="ag-legend__amount"><bdi>{{ number_format($blGroup['sum'], 2) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $blGroup['pct'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="ag-donut">
                            <canvas id="balancesDonut" aria-label="توزيع الموردين حسب نوع الرصيد" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ number_format($blCount) }}</strong>
                                <small>مورد</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا توجد أرصدة لعرضها</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول/البطاقات ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $isSummary ? 'ملخص أرصدة الموردين' : 'تفاصيل أرصدة الموردين' }}
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} مورد</span>
            </h4>
            <div class="ag-tools no-print">
                @if($isSummary)
                    @include('client::customers.CustomerReport.partials.report-page-size')
                @endif
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        @if($isSummary)
            <div class="rpt-scroll table-responsive">
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        {{--
                            الأعمدةُ الخمسةُ الأخيرة هي المعادلةُ المحاسبيةُ
                            نفسُها: (رصيدٌ افتتاحيٌّ + حركاتٌ سابقة) + دائنٌ −
                            مدينٌ = ختامي. والأوّلان معاً هما ما قبل الفترة؛
                            فُصلا كي لا تُسمّى فاتورةٌ قديمةٌ «رصيداً افتتاحياً».
                        --}}
                        <tr>
                            <th style="width:3%">#</th>
                            <th style="width:7%">الكود</th>
                            <th style="width:17%">المورد</th>
                            <th style="width:10%">تابع لـ</th>
                            <th style="width:7%">الحالة</th>
                            <th style="width:7%" title="حالة سداد مشتريات الفترة">الوضع</th>
                            <th style="width:8%">المدينة</th>
                            <th style="width:5%">{{ $debtAgeOn ? 'عمر أقدم دين' : 'الأيام' }}</th>
                            <th style="width:8%" title="قيود الرصيد الافتتاحي فقط">رصيد افتتاحي</th>
                            <th style="width:8%" title="فواتير وسندات وقيود مؤرّخة قبل بداية الفترة">حركات سابقة</th>
                            <th style="width:10%">إجمالي المدين</th>
                            <th style="width:10%">إجمالي الدائن</th>
                            <th style="width:11%">الرصيد النهائي</th>
                            <th class="ag-extra">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @php $rowNum = 0; @endphp
                        @forelse($reportData as $item)
                            @php
                                $rowNum++;
                                /* لونُ «الأيام»: حتى 7 أخضر، حتى 30 برتقالي، وما فوقها أحمر */
                                $daysColor = '#10b981';
                                if ($item['days_late'] > 30) { $daysColor = '#ef4444'; }
                                elseif ($item['days_late'] > 7) { $daysColor = '#f97316'; }
                                $statusColor = (string) ($item['status_color'] ?? 'secondary');
                                $statusIsHex = str_starts_with($statusColor, '#');
                            @endphp
                            <tr data-row="1">
                                <td>{{ $rowNum }}</td>
                                <td>{{ $item['supplier_code'] ?? '-' }}</td>
                                <td class="rpt-name">
                                    @if(!empty($item['supplier_id']) && $canViewSupplier)
                                        <a href="{{ route('SupplierManagement.show', $item['supplier_id']) }}" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة المورد" onclick="event.stopPropagation()">{{ $item['supplier_name'] ?: 'غير محدد' }}</a>
                                    @else
                                        {{ $item['supplier_name'] ?: 'غير محدد' }}
                                    @endif
                                </td>
                                {{-- «تابع لـ» يقول الجهةَ ونوعَها --}}
                                <td>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $item['affiliation_name'] ?? '-' }}</span>
                                    @if(!empty($item['affiliation_type']))
                                        <div class="text-muted" style="font-size:11px">{{ $item['affiliation_type'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($statusIsHex)
                                        <span class="rpt-pill" style="background:{{ $statusColor }}; color:#fff">{{ $item['status_name'] ?? '-' }}</span>
                                    @else
                                        <span class="badge badge-{{ $statusColor }}">{{ $item['status_name'] ?? '-' }}</span>
                                    @endif
                                </td>
                                {{-- «الوضع» = حالةُ سداد مشتريات الفترة — نظيرُ «وضع العميل» --}}
                                <td><span class="rpt-pill rpt-pill-{{ $item['payment_status']['class'] ?? 'secondary' }}">{{ $item['payment_status']['text'] ?? '—' }}</span></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $item['city'] ?? '-' }}</span></td>
                                <td>
                                    @if ($debtAgeOn)
                                        <span class="ag-days" style="--c:#ef4444">{{ $item['oldest_debt_days'] }} يوم</span>
                                        <small class="d-block text-muted" title="ما تجاوز {{ $debtAgeOn }} يوماً من المستحق">{{ number_format($item['older_than_amount'] ?? 0, 2) }}</small>
                                    @else
                                        <span class="ag-days" style="--c:{{ $daysColor }}">{{ $item['days_late'] }} يوم</span>
                                    @endif
                                </td>
                                <td class="rpt-num">{{ number_format($item['opening_entries'] ?? 0, 2) }}</td>
                                <td class="rpt-num">{{ number_format($item['prior_movements'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-pos">{{ number_format($item['total_debit'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-neg">{{ number_format($item['total_credit'] ?? 0, 2) }}</td>
                                {{-- الرصيدُ بلا سالب: اتجاهُه في الشارة — «دائن» له علينا، «مدين» عليه لنا --}}
                                <td class="rpt-num {{ ($item['balance'] ?? 0) < -0.005 ? 'rpt-pos' : 'rpt-warnc' }}">
                                    <strong>{{ number_format(abs($item['balance'] ?? 0), 2) }}</strong> {{ $currency }}
                                    <div>
                                        @if (($item['balance'] ?? 0) < -0.005)
                                            <span class="rpt-pill rpt-pill-success" title="دفعنا له أكثرَ ممّا علينا — مبلغٌ لنا عنده، ليس مستحقاً له">مدين — عليه لنا</span>
                                        @elseif (abs($item['balance'] ?? 0) < 0.005)
                                            <span class="rpt-pill rpt-pill-secondary">صفري</span>
                                        @else
                                            <span class="rpt-pill rpt-pill-danger">دائن — له علينا</span>
                                        @endif
                                    </div>
                                    @if(!empty($item['wallet_balance']))
                                        <div class="text-muted" style="font-size:11px" title="رصيد محفظة المورد — أموال المورد نفسه لدينا، منفصل ولا يُحتسب ضمن الرصيد أعلاه">
                                            <i class="fas fa-wallet"></i> محفظة: {{ number_format($item['wallet_balance'], 2) }} {{ $currency }}
                                        </div>
                                    @endif
                                </td>
                                <td class="ag-extra">
                                    @if(!empty($item['supplier_id']) && ($canViewSupplier || $canStatement))
                                        <div class="dropdown">
                                            <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات" title="إجراءات">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if($canViewSupplier)
                                                    <a class="dropdown-item" href="{{ route('SupplierManagement.show', $item['supplier_id']) }}" target="_blank" rel="noopener"><i class="fas fa-user-tie me-2 text-info"></i> ملف المورد</a>
                                                @endif
                                                @if($canStatement)
                                                    <a class="dropdown-item" href="{{ route('SupplierManagement.statement', ['id' => $item['supplier_id'], 'from_date' => $fromDate->format('Y-m-d'), 'to_date' => $toDate->format('Y-m-d')]) }}" target="_blank" rel="noopener"><i class="fas fa-file-invoice me-2 text-primary"></i> كشف الحساب</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($reportData) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="8">الإجمالي العام</td>
                                <td class="rpt-num">{{ number_format($totals['opening_entries'] ?? 0, 2) }}</td>
                                <td class="rpt-num">{{ number_format($totals['prior_movements'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-pos">{{ number_format($totals['total_debit'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-neg">{{ number_format($totals['total_credit'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-warnc" title="الصافي — التفصيل في السطرين التاليين"><strong>{{ number_format($totals['total_balance'] ?? 0, 2) }}</strong> {{ $currency }}</td>
                                <td class="ag-extra"></td>
                            </tr>
                            {{-- الصافي أعلاه مفصَّلاً: المستحقُّ للموردين والأرصدةُ المدينة كلٌّ وحدَه --}}
                            <tr>
                                <td colspan="12" style="text-align:start">مستحق للموردين — {{ number_format($totals['payable_suppliers'] ?? 0) }} مورد دائن</td>
                                <td class="rpt-num rpt-warnc"><strong>{{ number_format($totals['payable_total'] ?? 0, 2) }}</strong> {{ $currency }}</td>
                                <td class="ag-extra"></td>
                            </tr>
                            <tr>
                                <td colspan="12" style="text-align:start">أرصدة مدينة على الموردين — {{ number_format($totals['receivable_suppliers'] ?? 0) }} مورد (عليهم لنا، لا تُطرح من المستحق)</td>
                                <td class="rpt-num rpt-pos"><strong>{{ number_format($totals['receivable_total'] ?? 0, 2) }}</strong> {{ $currency }}</td>
                                <td class="ag-extra"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @include('client::customers.CustomerReport.partials.report-pager', [
                'pagerUnit' => 'مورد',
                'pagerStorage' => 'agSupplierBalancesPageSize',
                'pagerScroll' => '#reportCard',
            ])
        @else
            <div class="rpt-card__body">
                @forelse($reportData as $item)
                    @php
                        $daysColor = '#10b981';
                        if ($item['days_late'] > 30) { $daysColor = '#ef4444'; }
                        elseif ($item['days_late'] > 7) { $daysColor = '#f97316'; }
                        $statusColor = (string) ($item['status_color'] ?? 'secondary');
                        $statusIsHex = str_starts_with($statusColor, '#');
                    @endphp
                    <div class="rpt-block">
                        <header>
                            <h5><i class="fas fa-user-tie"></i>
                                @if(!empty($item['supplier_id']) && $canViewSupplier)
                                    <a href="{{ route('SupplierManagement.show', $item['supplier_id']) }}" target="_blank" rel="noopener" class="rpt-client-link" title="فتح صفحة المورد">{{ $item['supplier_name'] ?: 'غير محدد' }}</a>
                                @else
                                    {{ $item['supplier_name'] ?: 'غير محدد' }}
                                @endif
                                <small class="text-muted" style="font-weight:500">({{ $item['supplier_code'] ?? '-' }})</small></h5>
                            <div>
                                @if($statusIsHex)
                                    <span class="rpt-pill" style="background:{{ $statusColor }}; color:#fff">{{ $item['status_name'] ?? '-' }}</span>
                                @else
                                    <span class="badge badge-{{ $statusColor }}">{{ $item['status_name'] ?? '-' }}</span>
                                @endif
                                @if ($debtAgeOn)
                                    <span class="ag-days" style="--c:#ef4444" title="عمر أقدم دين">{{ $item['oldest_debt_days'] }} يوم · {{ number_format($item['older_than_amount'] ?? 0, 2) }}</span>
                                @else
                                    <span class="ag-days" style="--c:{{ $daysColor }}">{{ $item['days_late'] }} يوم</span>
                                @endif
                            </div>
                        </header>
                        <div class="rpt-mini">
                            <div><small>تابع لـ</small><strong>{{ $item['affiliation_name'] ?? '-' }}</strong> <span class="text-muted">{{ $item['affiliation_type'] ?? '' }}</span></div>
                            <div><small>مسؤول المشتريات</small><strong>{{ $item['employee_name'] ?? '-' }}</strong></div>
                            <div><small>المدينة</small><strong>{{ $item['city'] ?? '-' }}</strong></div>
                            <div><small>الهاتف</small><strong>{{ $item['phone'] ?: '—' }}</strong></div>
                            <div><small>حالة السداد</small><strong><span class="rpt-pill rpt-pill-{{ $item['payment_status']['class'] ?? 'secondary' }}">{{ $item['payment_status']['text'] ?? '—' }}</span></strong></div>
                            {{-- تفصيلُ الذمّة: ممّ تكوَّن الرصيدُ الختامي --}}
                            <div><small>رصيد افتتاحي</small><strong>{{ number_format($item['opening_entries'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>حركات سابقة</small><strong>{{ number_format($item['prior_movements'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>إجمالي الفواتير</small><strong class="rpt-neg">{{ number_format($item['invoices_total'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>المدفوع</small><strong class="rpt-pos">{{ number_format($item['paid_total'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>المرتجعات والإشعارات</small><strong class="rpt-pos">{{ number_format($item['returns_total'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>آخر حركة</small><strong>{{ $item['last_movement'] ?? '—' }}</strong></div>
                            <div>
                                <small>الرصيد</small>
                                <strong class="{{ ($item['balance'] ?? 0) < -0.005 ? 'rpt-pos' : 'rpt-warnc' }}">{{ number_format(abs($item['balance'] ?? 0), 2) }} {{ $currency }}</strong>
                                <span class="rpt-pill {{ ($item['balance'] ?? 0) < -0.005 ? 'rpt-pill-success' : 'rpt-pill-danger' }}">{{ ($item['balance'] ?? 0) < -0.005 ? 'مدين — عليه لنا' : 'دائن — له علينا' }}</span>
                            </div>
                            @if(!empty($item['wallet_balance']))
                                <div><small>محفظة المورد</small><strong class="text-muted">{{ number_format($item['wallet_balance'], 2) }} {{ $currency }}</strong></div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rpt-empty"><i class="fas fa-info-circle"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</div>
                @endforelse

                @if(count($reportData) > 0)
                    <div class="rpt-card">
                        <div class="rpt-card__head"><h4><i class="fas fa-calculator"></i> الإجمالي العام</h4></div>
                        <div class="rpt-mini">
                            <div><small>الموردون</small><strong>{{ $totals['total_suppliers'] ?? 0 }}</strong></div>
                            <div><small>رصيد افتتاحي</small><strong>{{ number_format($totals['opening_entries'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>حركات سابقة</small><strong>{{ number_format($totals['prior_movements'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>إجمالي المدين</small><strong class="rpt-pos">{{ number_format($totals['total_debit'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>إجمالي الدائن</small><strong class="rpt-neg">{{ number_format($totals['total_credit'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>مستحق للموردين ({{ number_format($totals['payable_suppliers'] ?? 0) }} مورد)</small><strong class="rpt-warnc">{{ number_format($totals['payable_total'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>أرصدة مدينة على الموردين ({{ number_format($totals['receivable_suppliers'] ?? 0) }})</small><strong class="rpt-pos">{{ number_format($totals['receivable_total'] ?? 0, 2) }} {{ $currency }}</strong></div>
                            <div><small>الصافي</small><strong>{{ number_format($totals['total_balance'] ?? 0, 2) }} {{ $currency }}</strong></div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{--
        مصدرٌ مخفيّ لصفوف الطباعة — يُبنى من $reportData دوماً بغضّ النظر
        عن نوع العرض، فوضعُ «التفاصيل» (بطاقاتٌ لا جدول) يُطبع كاملاً، ولا
        يدخله عمودُ «إجراءات».
    --}}
    <table class="d-none" id="printSourceTable">
        <tbody>
            @foreach($reportData as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item['supplier_code'] ?? '-' }}</td>
                    <td>{{ $item['supplier_name'] ?: 'غير محدد' }}</td>
                    <td>{{ $item['affiliation_name'] ?? '-' }}{{ !empty($item['affiliation_type']) ? ' ('.$item['affiliation_type'].')' : '' }}</td>
                    <td>{{ $item['status_name'] ?? '-' }}</td>
                    <td>{{ $item['payment_status']['text'] ?? '—' }}</td>
                    <td>{{ $item['city'] ?? '-' }}</td>
                    <td>{{ $item['days_late'] ?? 0 }} يوم</td>
                    <td>{{ number_format($item['opening_entries'] ?? 0, 2) }}</td>
                    <td>{{ number_format($item['prior_movements'] ?? 0, 2) }}</td>
                    <td>{{ number_format($item['total_debit'] ?? 0, 2) }}</td>
                    <td>{{ number_format($item['total_credit'] ?? 0, 2) }}</td>
                    <td>{{ number_format(abs($item['balance'] ?? 0), 2) }} {{ $currency }} ({{ ($item['balance'] ?? 0) < -0.005 ? 'مدين — عليه لنا' : (abs($item['balance'] ?? 0) < 0.005 ? 'صفري' : 'دائن — له علينا') }})</td>
                </tr>
            @endforeach
        </tbody>
    </table>

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
        const BL_GROUPS = @json($blChartGroups);
        const BL_TOP_CREDIT = @json($blTopCredit);

        function formatNumber(n, decimals = 2) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

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

            $('#printBtn').click(function() { printSupplierBalancesReport(); });
            $('#exportExcel').click(function() { exportSupplierBalancesReport(); });
            $('.period-btn').click(function() { changePeriod($(this).data('period')); });

            renderCharts();
        });

        function changePeriod(period) {
            period = String(period);
            $('#periodInput').val(period);
            $('.period-btn').removeClass('is-active');
            $(`.period-btn[data-period="${period}"]`).addClass('is-active');

            if (period !== 'custom') {
                const today = new Date();
                let fromDate = new Date();
                switch(period) {
                    case '1': fromDate = new Date(today); break;
                    case '7': fromDate.setDate(today.getDate() - 7); break;
                    case '30': fromDate.setDate(today.getDate() - 30); break;
                    case '90': fromDate.setDate(today.getDate() - 90); break;
                    case '180': fromDate.setDate(today.getDate() - 180); break;
                    case '365': fromDate.setDate(today.getDate() - 365); break;
                    default: fromDate.setDate(today.getDate() - 30);
                }
                $('#from_date').val(formatDateForInput(fromDate));
                $('#to_date').val(formatDateForInput(today));

                /* أزرارُ المدى السريع تُفرغ منتقيَي السنة والفترة: المتحكّم
                   يقدّمهما على حقلَي التاريخ، فلو بقيت قيمةٌ فيهما لظهر
                   الزرُّ مضغوطاً بينما يقرأ الاستعلامُ الفترةَ المختارة. */
                $('#fiscal_year_id, #accounting_period_id').val('');
                if ($.fn.select2) { $('#fiscal_year_id, #accounting_period_id').trigger('change.select2'); }
                $('#filterForm').submit();
            }
        }

        function renderCharts() {
            if (typeof Chart === 'undefined') return;

            var theme = agChartTheme();

            var barEl = document.getElementById('balancesTopBar');
            if (barEl && BL_TOP_CREDIT.length) {
                var values = BL_TOP_CREDIT.map(function(r) { return r.value; });
                new Chart(barEl, {
                    type: 'bar',
                    data: { labels: BL_TOP_CREDIT.map(function(r) { return r.short; }), datasets: [{ data: values, backgroundColor: '#ef4444', borderRadius: 6, maxBarThickness: 48 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: {
                                title: function(items) { return BL_TOP_CREDIT[items[0].dataIndex].name; },
                                label: function(context) { return ' ' + agMoney(BL_TOP_CREDIT[context.dataIndex].value); }
                            } }
                        },
                        scales: {
                            x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 10 }, maxRotation: 45, autoSkip: false } },
                            y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                 ticks: { color: theme.muted, callback: function(v) { return agWhole(v); } } }
                        }
                    },
                    plugins: [agValueLabels(values)]
                });
            }

            var donutEl = document.getElementById('balancesDonut');
            if (donutEl) {
                new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: BL_GROUPS.map(function(g) { return g.label; }),
                        datasets: [{ data: BL_GROUPS.map(function(g) { return g.count; }), backgroundColor: BL_GROUPS.map(function(g) { return g.color; }), borderColor: theme.surface, borderWidth: 3, hoverOffset: 6 }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: function(context) {
                            var g = BL_GROUPS[context.dataIndex];
                            return ' ' + agWhole(g.count) + ' مورد (' + g.pct + '%) · ' + agMoney(g.sum);
                        } } } }
                    }
                });
            }
        }

        function exportSupplierBalancesReport() {
            var isSummary = {{ $isSummary ? 'true' : 'false' }};
            var totals = {
                total_suppliers: {{ $totals['total_suppliers'] ?? 0 }},
                opening_entries: {{ $totals['opening_entries'] ?? 0 }},
                prior_movements: {{ $totals['prior_movements'] ?? 0 }},
                total_debit: {{ $totals['total_debit'] ?? 0 }},
                total_credit: {{ $totals['total_credit'] ?? 0 }},
                total_balance: {{ $totals['total_balance'] ?? 0 }},
                payable_total: {{ $totals['payable_total'] ?? 0 }},
                payable_suppliers: {{ $totals['payable_suppliers'] ?? 0 }},
                receivable_total: {{ $totals['receivable_total'] ?? 0 }},
                receivable_suppliers: {{ $totals['receivable_suppliers'] ?? 0 }}
            };

            var tableData = [];
            @if(!empty($reportData))
            @foreach($reportData as $item)
            tableData.push({
                values: [
                    '{{ addslashes((string) ($item["supplier_code"] ?? "-")) }}',
                    '{{ addslashes($item["supplier_name"] ?: "غير محدد") }}',
                    '{{ addslashes($item["affiliation_name"] ?? "-") }}',
                    '{{ addslashes($item["affiliation_type"] ?: "-") }}',
                    '{{ addslashes($item["status_name"] ?? "-") }}',
                    '{{ addslashes($item["payment_status"]["text"] ?? "—") }}',
                    '{{ addslashes($item["city"] ?? "-") }}',
                    '{{ $item["days_late"] ?? 0 }} يوم',
                    formatNumber({{ $item['opening_entries'] ?? 0 }}),
                    formatNumber({{ $item['prior_movements'] ?? 0 }}),
                    formatNumber({{ $item['total_debit'] ?? 0 }}),
                    formatNumber({{ $item['total_credit'] ?? 0 }}),
                    formatNumber({{ abs($item['balance'] ?? 0) }}),
                    '{{ ($item["balance"] ?? 0) < -0.005 ? "مدين — عليه لنا" : (abs($item["balance"] ?? 0) < 0.005 ? "صفري" : "دائن — له علينا") }}'
                ],
                cellStyles: [
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'warning' },
                    { alignment: { horizontal: 'center' } }
                ]
            });
            @endforeach
            @endif

            var config = {
                fileName: 'تقرير_أرصدة_الموردين',
                mainTitle: 'تقرير أرصدة الموردين',
                subTitle: 'نظام إدارة المشتريات',
                reportInfo: {
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },
                statisticsCards: [
                    { title: 'إجمالي الموردين', value: totals.total_suppliers, unit: ' مورد', borderColor: 'primary' },
                    { title: 'رصيد افتتاحي', value: formatNumber(totals.opening_entries), unit: ' {{ $currency }}', borderColor: 'info' },
                    { title: 'حركات سابقة', value: formatNumber(totals.prior_movements), unit: ' {{ $currency }}', borderColor: 'primary' },
                    { title: 'إجمالي المدين', value: formatNumber(totals.total_debit), unit: ' {{ $currency }}', borderColor: 'success' },
                    { title: 'إجمالي الدائن', value: formatNumber(totals.total_credit), unit: ' {{ $currency }}', borderColor: 'info' },
                    { title: 'مستحق للموردين (' + totals.payable_suppliers + ' دائن)', value: formatNumber(totals.payable_total), unit: ' {{ $currency }}', borderColor: 'warning' },
                    { title: 'أرصدة مدينة على الموردين (' + totals.receivable_suppliers + ')', value: formatNumber(totals.receivable_total), unit: ' {{ $currency }}', borderColor: 'success' }
                ],
                tableTitle: isSummary ? 'ملخص أرصدة الموردين' : 'تفاصيل أرصدة الموردين',
                tableHeaders: ['الكود', 'المورد', 'تابع لـ', 'النوع', 'الحالة', 'الوضع', 'المدينة', 'الأيام', 'رصيد افتتاحي', 'حركات سابقة', 'إجمالي المدين', 'إجمالي الدائن', 'الرصيد النهائي', 'نوع الحساب'],
                columnWidths: [10, 24, 14, 10, 11, 12, 13, 8, 14, 14, 14, 14, 14, 16],
                tableData: tableData,
                summaryRows: [{
                    values: ['', '', '', '', '', '', '', 'الإجمالي',
                             formatNumber(totals.opening_entries), formatNumber(totals.prior_movements),
                             formatNumber(totals.total_debit),
                             formatNumber(totals.total_credit), formatNumber(totals.total_balance), ''],
                    mergeFrom: 1, mergeTo: 7, background: 'warningLight', color: 'warning',
                    cellStyles: [null, null, null, null, null, null, null, { bold: true },
                                 { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }]
                }, {
                    values: ['', '', '', '', '', '', '', 'مستحق للموردين (' + totals.payable_suppliers + ' مورد دائن)', '', '', '', '', formatNumber(totals.payable_total), ''],
                    mergeFrom: 1, mergeTo: 7, background: 'warningLight', color: 'warning',
                    cellStyles: [null, null, null, null, null, null, null, { bold: true }, null, null, null, null, { bold: true }]
                }, {
                    values: ['', '', '', '', '', '', '', 'أرصدة مدينة على الموردين (' + totals.receivable_suppliers + ') — عليهم لنا', '', '', '', '', formatNumber(totals.receivable_total), ''],
                    mergeFrom: 1, mergeTo: 7, background: 'successLight', color: 'success',
                    cellStyles: [null, null, null, null, null, null, null, { bold: true }, null, null, null, null, { bold: true }]
                }],
                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

        function printSupplierBalancesReport() {
            var totals = {
                total_suppliers: {{ $totals['total_suppliers'] ?? 0 }},
                opening_entries: {{ $totals['opening_entries'] ?? 0 }},
                prior_movements: {{ $totals['prior_movements'] ?? 0 }},
                total_debit: {{ $totals['total_debit'] ?? 0 }},
                total_credit: {{ $totals['total_credit'] ?? 0 }},
                total_balance: {{ $totals['total_balance'] ?? 0 }},
                payable_total: {{ $totals['payable_total'] ?? 0 }},
                payable_suppliers: {{ $totals['payable_suppliers'] ?? 0 }},
                receivable_total: {{ $totals['receivable_total'] ?? 0 }},
                receivable_suppliers: {{ $totals['receivable_suppliers'] ?? 0 }}
            };

            printTable({
                tableSelector: '#printSourceTable tbody tr',
                title: 'تقرير أرصدة الموردين',
                infoCards: [
                    { label: 'عدد الموردين', value: totals.total_suppliers },
                    { label: 'رصيد افتتاحي', value: formatNumber(totals.opening_entries) },
                    { label: 'حركات سابقة', value: formatNumber(totals.prior_movements) },
                    { label: 'إجمالي المدين', value: formatNumber(totals.total_debit) },
                    { label: 'إجمالي الدائن', value: formatNumber(totals.total_credit) },
                    { label: 'مستحق للموردين (' + totals.payable_suppliers + ' دائن)', value: formatNumber(totals.payable_total) },
                    { label: 'أرصدة مدينة على الموردين (' + totals.receivable_suppliers + ')', value: formatNumber(totals.receivable_total) }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', 'الكود', 'المورد', 'تابع لـ', 'الحالة', 'الوضع', 'المدينة', 'الأيام', 'رصيد افتتاحي', 'حركات سابقة', 'إجمالي المدين', 'إجمالي الدائن', 'الرصيد النهائي'],
                columnWidths: ['3%', '6%', '14%', '9%', '7%', '7%', '7%', '5%', '8%', '8%', '9%', '9%', '8%'],
                descriptionColumn: 2,
                windowWidth: 1200,
                windowHeight: 800
            });
        }

    </script>
@endsection
