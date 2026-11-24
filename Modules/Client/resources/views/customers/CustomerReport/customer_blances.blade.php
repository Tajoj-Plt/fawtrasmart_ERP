{{--
    تقرير أرصدة العملاء — على نمط التصميم المعتمد («أعمار ديون الفواتير»):
    ترويسةٌ فاتحة، شبكةُ فلترة، خمسةُ مؤشّرات، رسمان (حلقةٌ بجدول توزيع
    المدين/الدائن/الصفري + أعمدةُ أعلى عشرة أرصدةٍ مدينة)، ثمّ الجدول.

    ما لم يتغيّر:
    • كلُّ حقول الفلترة وأسماؤها، عدا «تمت الإضافة بواسطة» الذي دُمج في
      «مسؤول المبيعات» (المتحكّم المشترك يطابق الموظّفَ أو مستخدمَه).
    • أعمدةُ الجدول الثلاثة عشر وبياناتُها؛ أُضيف بعدها عمود «إجراءات»
      (`ag-extra`). التصديرُ يُبنى من `$reportData` والطباعةُ من
      `#printSourceTable`، فلا يقرأ أيٌّ منهما الجدولَ المعروض — ملفُّ Excel
      والورقةُ المطبوعة كما كانا.
    • الرسمان من `$reportData` نفسِه، بلا استعلامٍ جديد.
--}}
@extends('client::master')

@section('title')
    تقرير أرصدة العملاء
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    // «عمر الدين» المطبَّق — عمودُ «الأيام» يصير عمرَ أقدم دين معه
    $debtAgeOn = $totals['debt_age'] ?? null;
    $periodLabel = 'من '.$fromDate->format('d/m/Y').' إلى '.$toDate->format('d/m/Y');
    $currency = \App\Support\CurrencySettings::name();

    /* توزيعُ الأرصدة الختامية: مدينٌ (على العميل) / دائنٌ (للعميل) / صفري.
       الصفريُّ منفصلٌ هنا وإن كان عمودُ «نوع الحساب» يسمّيه «مدين» */
    $blGroups = [
        'debit'  => ['label' => 'أرصدة مدينة', 'color' => '#ef4444', 'count' => 0, 'sum' => 0.0],
        'credit' => ['label' => 'أرصدة دائنة', 'color' => '#10b981', 'count' => 0, 'sum' => 0.0],
        'zero'   => ['label' => 'أرصدة صفرية', 'color' => '#64748b', 'count' => 0, 'sum' => 0.0],
    ];
    $blDebitRows = [];
    foreach ($reportData as $blItem) {
        $blBalance = (float) ($blItem['balance'] ?? 0);
        $blKey = $blBalance > 0.005 ? 'debit' : ($blBalance < -0.005 ? 'credit' : 'zero');
        $blGroups[$blKey]['count']++;
        $blGroups[$blKey]['sum'] += $blBalance;
        if ($blKey === 'debit') {
            $blDebitRows[] = ['name' => (string) ($blItem['client_name'] ?? 'غير محدد'), 'value' => round($blBalance, 2)];
        }
    }
    $blCount = count($reportData);
    foreach ($blGroups as $blKey => $blGroup) {
        $blGroups[$blKey]['sum'] = round($blGroup['sum'], 2);
        $blGroups[$blKey]['pct'] = $blCount > 0 ? round($blGroup['count'] / $blCount * 100) : 0;
    }
    usort($blDebitRows, fn ($a, $b) => $b['value'] <=> $a['value']);
    $blTopDebit = array_map(fn ($r) => [
        'name' => $r['name'],
        'short' => mb_strlen($r['name']) > 16 ? mb_substr($r['name'], 0, 15).'…' : $r['name'],
        'value' => $r['value'],
    ], array_slice($blDebitRows, 0, 10));
    $blChartGroups = array_values(array_map(fn ($g) => [
        'label' => $g['label'], 'color' => $g['color'], 'count' => $g['count'], 'sum' => $g['sum'], 'pct' => $g['pct'],
    ], $blGroups));

    $blClientPermissions = ['clients_view_all_clients', 'clients_view_own_clients', 'clients_view_by_employee_groups', 'clients_view_by_daily_itinerary'];
    $canViewClient = canAny($blClientPermissions) && \Illuminate\Support\Facades\Route::has('clients.show');
    $canStatement = hasPermission('clients.reports.statement') && \Illuminate\Support\Facades\Route::has('ClientReport.customerAccountStatement');
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'wallet',
        'headTitle' => 'أرصدة العملاء',
        'headSubtitle' => 'أرصدة العملاء ومديونيّتهم — افتتاحيٌّ ومدينٌ ودائنٌ وختاميّ، حتى تاريخ القطع المختار',
        'headPeriod' => $periodLabel,
        'headGuideKey' => 'client-reports.balances',
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ route('ClientReport.customerBalance') }}" id="filterForm">
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
                        @include('reports.partials.client-select', [
                            'name' => 'customer',
                            'selectedClient' => $selectedClient ?? null,
                            'label' => 'العميل',
                        ])
                    </div>

                    {{-- «مسؤول المبيعات» يطابق الموظّفَ المسنَد أو من أضاف العميل —
                         حلّ محلّ حقل «تمت الإضافة بواسطة» --}}
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
                            @foreach($neighborhoods ?? [] as $neighborhood)
                                <option value="{{ $neighborhood->name }}" data-region-id="{{ $neighborhood->region_id }}" {{ request('neighborhood') == $neighborhood->name ? 'selected' : '' }}>{{ $neighborhood->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- نوعُ الرصيد: لعرض الأرصدة الدائنة (لصالح العملاء) وحدَها أو المدينين وحدَهم --}}
                    <div class="rpt-field">
                        <label for="balance_side">نوع الرصيد</label>
                        <select name="balance_side" id="balance_side" class="form-control rpt-select2" data-placeholder="كل الأرصدة">
                            <option value="">كل الأرصدة</option>
                            <option value="debit" {{ request('balance_side') === 'debit' ? 'selected' : '' }}>مدين — عليه لنا</option>
                            <option value="credit" {{ request('balance_side') === 'credit' ? 'selected' : '' }}>دائن — لصالح العميل</option>
                            <option value="zero" {{ request('balance_side') === 'zero' ? 'selected' : '' }}>صفري</option>
                        </select>
                    </div>

                    {{-- عمرُ الدين: من له مبلغٌ مفتوحٌ أقدمُ من الحدّ (FIFO بعد مدّة الائتمان) --}}
                    <div class="rpt-field">
                        @include('client::customers.CustomerReport.partials.debt-age-select', ['debtAgeHint' => 'عمر أقدم مبلغ لم يُسدَّد، لا عمر آخر فاتورة'])
                    </div>

                    {{-- نوعُ العرض زرّا إرسال: الملخّصُ جدولٌ، والتفاصيلُ بطاقةٌ لكلّ عميل --}}
                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle"><i class="fas fa-list"></i> الملخص</button>
                            <button type="submit" name="view_type" value="details" class="btn {{ !$isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle"><i class="fas fa-th-list"></i> التفاصيل</button>
                        </div>
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
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
                        <a href="{{ route('ClientReport.customerBalance') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    {{--
        البطاقاتُ تقرأ المعادلةَ المحاسبية للمدى. كانت تقول «إجمالي
        المبيعات» و«إجمالي المدفوعات» وهي في الحقيقة موجبُ الأرصدة
        وسالبُها — عنوانانِ لا يصفان ما تحتهما.
    --}}
    <div class="ag-kpis">
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">إجمالي العملاء</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['total_clients'] ?? 0 }}</bdi></div>
                <div class="ag-kpi__sub">مدين {{ number_format($blGroups['debit']['count']) }} · دائن {{ number_format($blGroups['credit']['count']) }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        {{-- ما قبل الفترة بطاقتان: قيودُ الرصيد الافتتاحي وحدَها، ثمّ الفواتيرُ
             والسنداتُ السابقة — كي لا تُقرأ فاتورةٌ قديمةٌ «رصيداً افتتاحياً» --}}
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">رصيد افتتاحي</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['opening_entries'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['opening_clients'] ?? 0) }} عميل — لم يُسدَّد منه <bdi>{{ number_format($totals['opening_remaining'] ?? 0, 2) }}</bdi> على {{ number_format($totals['opening_open_clients'] ?? 0) }} عميل</div>
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
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">إجمالي المدين</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['total_debit'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · حركات الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-arrow-up"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">إجمالي الدائن</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['total_credit'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · حركات الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-arrow-down"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                {{-- المديونية = أرصدةُ المدينين وحدَهم — رقمُ «إجمالي المديونية» في لوحتَي
                     العملاء والمبيعات. والأرصدةُ الدائنة بطاقةٌ مستقلّة لا تُطرح منها. --}}
                <div class="ag-kpi__label">المديونية</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['debt_total'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['debt_clients'] ?? 0) }} عميل مدين — عليهم لنا</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-balance-scale"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">أرصدة دائنة للعملاء</div>
                <div class="ag-kpi__value"><bdi>{{ number_format($totals['credit_total'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ number_format($totals['credit_clients'] ?? 0) }} عميل دفع زيادةً أو مقدَّماً — لهم علينا</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
    </div>

    {{-- ===================== الرسمان ===================== --}}
    <div class="ag-charts">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> أعلى 10 أرصدة مدينة</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($blTopDebit) > 0)
                    <div class="ag-canvas"><canvas id="balancesTopBar" aria-label="أعلى الأرصدة المدينة" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا أرصدة مدينة لعرضها</div>
                @endif
            </div>
        </div>

        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع العملاء حسب نوع الرصيد</h4>
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
                            <canvas id="balancesDonut" aria-label="توزيع العملاء حسب نوع الرصيد" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ number_format($blCount) }}</strong>
                                <small>عميل</small>
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
                <i class="fas fa-table"></i> {{ $isSummary ? 'ملخص أرصدة العملاء' : 'تفاصيل أرصدة العملاء' }}
                <span class="rpt-pill rpt-pill-secondary">{{ count($reportData) }} عميل</span>
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
                            نفسُها: (رصيدٌ افتتاحيٌّ + حركاتٌ سابقة) + مدينٌ −
                            دائنٌ = ختامي. والأوّلان معاً هما ما قبل الفترة؛
                            فُصلا كي لا تُسمّى فاتورةٌ قديمةٌ «رصيداً افتتاحياً».
                        --}}
                        <tr>
                            <th style="width:3%">#</th>
                            <th style="width:7%">الكود</th>
                            <th style="width:15%">العميل</th>
                            <th style="width:9%">تابع لـ</th>
                            <th style="width:7%">الحالة</th>
                            <th style="width:7%">الوضع</th>
                            <th style="width:7%">الهدف</th>
                            <th style="width:8%">الحي</th>
                            <th style="width:5%">{{ $debtAgeOn ? 'عمر أقدم دين' : 'الأيام' }}</th>
                            <th style="width:8%" title="قيود الرصيد الافتتاحي كما في القيد">رصيد افتتاحي</th>
                            <th style="width:8%" title="ما بقي من الرصيد الافتتاحي بعد أن سدّدت التحصيلات أقدم الدين أولاً">لم يُسدَّد منه</th>
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
                                $statusColor = (string) ($item['client_status_color'] ?? 'secondary');
                                $statusIsHex = str_starts_with($statusColor, '#');
                            @endphp
                            <tr data-row="1">
                                <td>{{ $rowNum }}</td>
                                <td>{{ $item['client_code'] ?? '-' }}</td>
                                <td class="rpt-name">
                                    @include('reports.partials.client-link', ['id' => $item['client_id'] ?? null, 'name' => $item['client_name'] ?? '', 'fallback' => 'غير محدد'])
                                </td>
                                {{-- «تابع لـ» يقول الجهةَ ونوعَها، لا الفرعَ المحاسبيَّ المشتقّ --}}
                                <td>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $item['affiliation_name'] ?? '-' }}</span>
                                    @if(!empty($item['affiliation_type']))
                                        <div class="text-muted" style="font-size:11px">{{ $item['affiliation_type'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($statusIsHex)
                                        <span class="rpt-pill" style="background:{{ $statusColor }}; color:#fff">{{ $item['client_status_name'] ?? '-' }}</span>
                                    @else
                                        <span class="badge badge-{{ $statusColor }}">{{ $item['client_status_name'] ?? '-' }}</span>
                                    @endif
                                </td>
                                <td><span class="rpt-pill" style="background:{{ $item['stage_color'] ?? '#6c757d' }}; color:#fff">{{ $item['stage_name'] ?? '—' }}</span></td>
                                <td><span class="rpt-pill" style="background:{{ $item['target_color'] ?? '#6c757d' }}; color:#fff">{{ $item['target_name'] ?? '-' }}</span></td>
                                <td><span class="rpt-pill rpt-pill-secondary">{{ $item['neighborhood_name'] ?? '-' }}</span></td>
                                <td>
                                    @if ($debtAgeOn)
                                        <span class="ag-days" style="--c:#ef4444">{{ $item['oldest_debt_days'] }} يوم</span>
                                        <small class="d-block text-muted" title="ما تجاوز {{ $debtAgeOn }} يوماً من الدين">{{ number_format($item['older_than_amount'] ?? 0, 2) }}</small>
                                    @else
                                        <span class="ag-days" style="--c:{{ $daysColor }}">{{ $item['days_late'] }} يوم</span>
                                    @endif
                                </td>
                                <td class="rpt-num">{{ number_format($item['opening_entries'] ?? 0, 2) }}</td>
                                <td class="rpt-num {{ ($item['opening_remaining'] ?? 0) > 0.005 ? 'rpt-warnc' : '' }}">{{ number_format($item['opening_remaining'] ?? 0, 2) }}</td>
                                <td class="rpt-num">{{ number_format($item['prior_movements'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-neg">{{ number_format($item['total_debit'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-pos">{{ number_format($item['total_credit'] ?? 0, 2) }}</td>
                                {{-- الرصيدُ بلا سالب: اتجاهُه في الشارة — «مدين» عليه لنا، «دائن» لصالحه --}}
                                <td class="rpt-num {{ ($item['balance'] ?? 0) < -0.005 ? 'rpt-pos' : 'rpt-warnc' }}">
                                    <strong>{{ number_format(abs($item['balance'] ?? 0), 2) }}</strong> ر.س
                                    <div>
                                        @if (($item['balance'] ?? 0) < -0.005)
                                            <span class="rpt-pill rpt-pill-success" title="دفع أكثرَ ممّا عليه — مبلغٌ لصالح العميل، ليس ديناً">دائن — لصالح العميل</span>
                                        @elseif (abs($item['balance'] ?? 0) < 0.005)
                                            <span class="rpt-pill rpt-pill-secondary">صفري</span>
                                        @else
                                            <span class="rpt-pill rpt-pill-danger">مدين — عليه</span>
                                        @endif
                                    </div>
                                    @if(!empty($item['wallet_balance']))
                                        <div class="text-muted" style="font-size:11px" title="رصيد محفظة العميل — أموال العميل نفسه لدينا، منفصل ولا يُحتسب ضمن الرصيد أعلاه">
                                            <i class="fas fa-wallet"></i> محفظة: {{ number_format($item['wallet_balance'], 2) }} ر.س
                                        </div>
                                    @endif
                                </td>
                                <td class="ag-extra">
                                    @if(!empty($item['client_id']) && ($canViewClient || $canStatement))
                                        <div class="dropdown">
                                            <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات" title="إجراءات">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if($canViewClient)
                                                    <a class="dropdown-item" href="{{ route('clients.show', $item['client_id']) }}" target="_blank" rel="noopener"><i class="fas fa-user me-2 text-info"></i> ملف العميل</a>
                                                @endif
                                                @if($canStatement)
                                                    <a class="dropdown-item" href="{{ route('ClientReport.customerAccountStatement', ['client_id' => $item['client_id'], 'date_from' => $fromDate->format('Y-m-d'), 'date_to' => $toDate->format('Y-m-d')]) }}" target="_blank" rel="noopener"><i class="fas fa-file-invoice me-2 text-primary"></i> كشف الحساب</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="16" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                        @endforelse
                    </tbody>
                    @if(count($reportData) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="9">الإجمالي العام</td>
                                <td class="rpt-num">{{ number_format($totals['opening_entries'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-warnc">{{ number_format($totals['opening_remaining'] ?? 0, 2) }}</td>
                                <td class="rpt-num">{{ number_format($totals['prior_movements'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-neg">{{ number_format($totals['total_debit'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-pos">{{ number_format($totals['total_credit'] ?? 0, 2) }}</td>
                                <td class="rpt-num rpt-warnc" title="الصافي — التفصيل في السطرين التاليين"><strong>{{ number_format($totals['total_balance'] ?? 0, 2) }}</strong> ر.س</td>
                                <td class="ag-extra"></td>
                            </tr>
                            {{-- الصافي أعلاه مفصَّلاً: المديونيةُ والأرصدةُ الدائنة كلٌّ وحدَه --}}
                            <tr>
                                <td colspan="14" style="text-align:start">المديونية — {{ number_format($totals['debt_clients'] ?? 0) }} عميل مدين</td>
                                <td class="rpt-num rpt-warnc"><strong>{{ number_format($totals['debt_total'] ?? 0, 2) }}</strong> ر.س</td>
                                <td class="ag-extra"></td>
                            </tr>
                            <tr>
                                <td colspan="14" style="text-align:start">أرصدة دائنة للعملاء — {{ number_format($totals['credit_clients'] ?? 0) }} عميل (لهم علينا، لا تُطرح من المديونية)</td>
                                <td class="rpt-num rpt-pos"><strong>{{ number_format($totals['credit_total'] ?? 0, 2) }}</strong> ر.س</td>
                                <td class="ag-extra"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @include('client::customers.CustomerReport.partials.report-pager', [
                'pagerUnit' => 'عميل',
                'pagerStorage' => 'agBalancesPageSize',
                'pagerScroll' => '#reportCard',
            ])
        @else
            <div class="rpt-card__body">
                @forelse($reportData as $item)
                    @php
                        $daysColor = '#10b981';
                        if ($item['days_late'] > 30) { $daysColor = '#ef4444'; }
                        elseif ($item['days_late'] > 7) { $daysColor = '#f97316'; }
                        $statusColor = (string) ($item['client_status_color'] ?? 'secondary');
                        $statusIsHex = str_starts_with($statusColor, '#');
                    @endphp
                    <div class="rpt-block">
                        <header>
                            <h5><i class="fas fa-user-circle"></i> @include('reports.partials.client-link', ['id' => $item['client_id'] ?? null, 'name' => $item['client_name'] ?? '', 'fallback' => 'غير محدد']) <small class="text-muted" style="font-weight:500">({{ $item['client_code'] ?? '-' }})</small></h5>
                            <div>
                                @if($statusIsHex)
                                    <span class="rpt-pill" style="background:{{ $statusColor }}; color:#fff">{{ $item['client_status_name'] ?? '-' }}</span>
                                @else
                                    <span class="badge badge-{{ $statusColor }}">{{ $item['client_status_name'] ?? '-' }}</span>
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
                            <div><small>الوضع</small><strong>{{ $item['stage_name'] ?? '—' }}</strong></div>
                            <div><small>الهدف</small><strong>{{ $item['target_name'] ?? '-' }}</strong></div>
                            <div><small>الموظف</small><strong>{{ $item['employee_name'] ?? '-' }}</strong></div>
                            <div><small>الحي</small><strong>{{ $item['neighborhood_name'] ?? '-' }}</strong></div>
                            <div><small>التصنيف</small><strong>{{ $item['category_name'] ?? '-' }}</strong></div>
                            <div><small>آخر عهدة</small><strong>{{ $item['last_deposit'] ?? 0 }}</strong></div>
                            {{-- تفصيلُ المديونية: ممّ تكوَّن الرصيدُ الختامي --}}
                            <div><small>رصيد افتتاحي</small><strong>{{ number_format($item['opening_entries'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>لم يُسدَّد منه</small><strong class="{{ ($item['opening_remaining'] ?? 0) > 0.005 ? 'rpt-warnc' : '' }}">{{ number_format($item['opening_remaining'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>حركات سابقة</small><strong>{{ number_format($item['prior_movements'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>إجمالي الفواتير</small><strong class="rpt-neg">{{ number_format($item['invoices_total'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>المدفوع</small><strong class="rpt-pos">{{ number_format($item['paid_total'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>المرتجعات والإشعارات</small><strong class="rpt-pos">{{ number_format($item['returns_total'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>آخر حركة</small><strong>{{ $item['last_movement'] ?? '—' }}</strong></div>
                            <div>
                                <small>الرصيد</small>
                                <strong class="{{ ($item['balance'] ?? 0) < -0.005 ? 'rpt-pos' : 'rpt-warnc' }}">{{ number_format(abs($item['balance'] ?? 0), 2) }} ر.س</strong>
                                <span class="rpt-pill {{ ($item['balance'] ?? 0) < -0.005 ? 'rpt-pill-success' : 'rpt-pill-danger' }}">{{ ($item['balance'] ?? 0) < -0.005 ? 'دائن — لصالح العميل' : 'مدين' }}</span>
                            </div>
                            @if(!empty($item['wallet_balance']))
                                <div><small>محفظة العميل</small><strong class="text-muted">{{ number_format($item['wallet_balance'], 2) }} ر.س</strong></div>
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
                            <div><small>العملاء</small><strong>{{ $totals['total_clients'] ?? 0 }}</strong></div>
                            <div><small>رصيد افتتاحي</small><strong>{{ number_format($totals['opening_entries'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>لم يُسدَّد منه ({{ number_format($totals['opening_open_clients'] ?? 0) }} عميل)</small><strong class="rpt-warnc">{{ number_format($totals['opening_remaining'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>حركات سابقة</small><strong>{{ number_format($totals['prior_movements'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>إجمالي المدين</small><strong class="rpt-neg">{{ number_format($totals['total_debit'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>إجمالي الدائن</small><strong class="rpt-pos">{{ number_format($totals['total_credit'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>المديونية ({{ number_format($totals['debt_clients'] ?? 0) }} عميل)</small><strong class="rpt-warnc">{{ number_format($totals['debt_total'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>أرصدة دائنة للعملاء ({{ number_format($totals['credit_clients'] ?? 0) }})</small><strong class="rpt-pos">{{ number_format($totals['credit_total'] ?? 0, 2) }} ر.س</strong></div>
                            <div><small>الصافي</small><strong>{{ number_format($totals['total_balance'] ?? 0, 2) }} ر.س</strong></div>
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
                    <td>{{ $item['client_code'] ?? '-' }}</td>
                    <td>{{ $item['client_name'] ?? 'غير محدد' }}</td>
                    <td>{{ $item['affiliation_name'] ?? '-' }}{{ !empty($item['affiliation_type']) ? ' ('.$item['affiliation_type'].')' : '' }}</td>
                    <td>{{ $item['client_status_name'] ?? '-' }}</td>
                    <td>{{ $item['stage_name'] ?? '—' }}</td>
                    <td>{{ $item['target_name'] ?? '-' }}</td>
                    <td>{{ $item['neighborhood_name'] ?? '-' }}</td>
                    <td>{{ $item['days_late'] ?? 0 }} يوم</td>
                    <td>{{ number_format($item['opening_entries'] ?? 0, 2) }}</td>
                    <td>{{ number_format($item['opening_remaining'] ?? 0, 2) }}</td>
                    <td>{{ number_format($item['prior_movements'] ?? 0, 2) }}</td>
                    <td>{{ number_format($item['total_debit'] ?? 0, 2) }}</td>
                    <td>{{ number_format($item['total_credit'] ?? 0, 2) }}</td>
                    <td>{{ number_format(abs($item['balance'] ?? 0), 2) }} ر.س ({{ ($item['balance'] ?? 0) < -0.005 ? 'دائن — لصالح العميل' : (abs($item['balance'] ?? 0) < 0.005 ? 'صفري' : 'مدين') }})</td>
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
        const BL_TOP_DEBIT = @json($blTopDebit);

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

            $('#printBtn').click(function() { printCustomerBalancesReport(); });
            $('#exportExcel').click(function() { exportCustomerBalancesReport(); });
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
            if (barEl && BL_TOP_DEBIT.length) {
                var values = BL_TOP_DEBIT.map(function(r) { return r.value; });
                new Chart(barEl, {
                    type: 'bar',
                    data: { labels: BL_TOP_DEBIT.map(function(r) { return r.short; }), datasets: [{ data: values, backgroundColor: '#ef4444', borderRadius: 6, maxBarThickness: 48 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: {
                                title: function(items) { return BL_TOP_DEBIT[items[0].dataIndex].name; },
                                label: function(context) { return ' ' + agMoney(BL_TOP_DEBIT[context.dataIndex].value); }
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
                            return ' ' + agWhole(g.count) + ' عميل (' + g.pct + '%) · ' + agMoney(g.sum);
                        } } } }
                    }
                });
            }
        }

        function exportCustomerBalancesReport() {
            var isSummary = {{ $isSummary ? 'true' : 'false' }};
            var totals = {
                total_clients: {{ $totals['total_clients'] ?? 0 }},
                opening_entries: {{ $totals['opening_entries'] ?? 0 }},
                opening_remaining: {{ $totals['opening_remaining'] ?? 0 }},
                prior_movements: {{ $totals['prior_movements'] ?? 0 }},
                total_debit: {{ $totals['total_debit'] ?? 0 }},
                total_credit: {{ $totals['total_credit'] ?? 0 }},
                total_balance: {{ $totals['total_balance'] ?? 0 }},
                debt_total: {{ $totals['debt_total'] ?? 0 }},
                debt_clients: {{ $totals['debt_clients'] ?? 0 }},
                credit_total: {{ $totals['credit_total'] ?? 0 }},
                credit_clients: {{ $totals['credit_clients'] ?? 0 }}
            };

            var tableData = [];
            @if(!empty($reportData))
            @foreach($reportData as $item)
            tableData.push({
                values: [
                    '{{ $item["client_code"] ?? "-" }}',
                    '{{ addslashes($item["client_name"] ?? "غير محدد") }}',
                    '{{ addslashes($item["affiliation_name"] ?? "-") }}',
                    '{{ addslashes($item["affiliation_type"] ?? "-") }}',
                    '{{ addslashes($item["client_status_name"] ?? "-") }}',
                    '{{ addslashes($item["stage_name"] ?? "—") }}',
                    '{{ addslashes($item["target_name"] ?? "-") }}',
                    '{{ addslashes($item["neighborhood_name"] ?? "-") }}',
                    '{{ $item["days_late"] ?? 0 }} يوم',
                    formatNumber({{ $item['opening_entries'] ?? 0 }}),
                    formatNumber({{ $item['opening_remaining'] ?? 0 }}),
                    formatNumber({{ $item['prior_movements'] ?? 0 }}),
                    formatNumber({{ $item['total_debit'] ?? 0 }}),
                    formatNumber({{ $item['total_credit'] ?? 0 }}),
                    formatNumber({{ abs($item['balance'] ?? 0) }}),
                    '{{ ($item["balance"] ?? 0) < -0.005 ? "دائن — لصالح العميل" : (abs($item["balance"] ?? 0) < 0.005 ? "صفري" : "مدين") }}'
                ],
                cellStyles: [
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } }, { alignment: { horizontal: 'center' } },
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
                fileName: 'تقرير_أرصدة_العملاء',
                mainTitle: 'تقرير أرصدة العملاء',
                subTitle: 'نظام إدارة العملاء',
                reportInfo: {
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },
                statisticsCards: [
                    { title: 'إجمالي العملاء', value: totals.total_clients, unit: ' عميل', borderColor: 'primary' },
                    { title: 'رصيد افتتاحي', value: formatNumber(totals.opening_entries), unit: ' ر.س', borderColor: 'info' },
                    { title: 'لم يُسدَّد من الافتتاحي', value: formatNumber(totals.opening_remaining), unit: ' ر.س', borderColor: 'warning' },
                    { title: 'حركات سابقة', value: formatNumber(totals.prior_movements), unit: ' ر.س', borderColor: 'primary' },
                    { title: 'إجمالي المدين', value: formatNumber(totals.total_debit), unit: ' ر.س', borderColor: 'success' },
                    { title: 'إجمالي الدائن', value: formatNumber(totals.total_credit), unit: ' ر.س', borderColor: 'info' },
                    { title: 'المديونية (' + totals.debt_clients + ' مدين)', value: formatNumber(totals.debt_total), unit: ' ر.س', borderColor: 'warning' },
                    { title: 'أرصدة دائنة للعملاء (' + totals.credit_clients + ')', value: formatNumber(totals.credit_total), unit: ' ر.س', borderColor: 'success' }
                ],
                tableTitle: isSummary ? 'ملخص أرصدة العملاء' : 'تفاصيل أرصدة العملاء',
                tableHeaders: ['الكود', 'العميل', 'تابع لـ', 'النوع', 'الحالة', 'الوضع', 'الهدف', 'الحي', 'الأيام', 'رصيد افتتاحي', 'لم يُسدَّد منه', 'حركات سابقة', 'إجمالي المدين', 'إجمالي الدائن', 'الرصيد النهائي', 'نوع الحساب'],
                columnWidths: [10, 22, 14, 10, 11, 11, 11, 13, 8, 14, 14, 14, 14, 14, 14, 10],
                tableData: tableData,
                summaryRows: [{
                    values: ['', '', '', '', '', '', '', '', 'الإجمالي',
                             formatNumber(totals.opening_entries), formatNumber(totals.opening_remaining), formatNumber(totals.prior_movements),
                             formatNumber(totals.total_debit),
                             formatNumber(totals.total_credit), formatNumber(totals.total_balance), ''],
                    mergeFrom: 1, mergeTo: 8, background: 'warningLight', color: 'warning',
                    cellStyles: [null, null, null, null, null, null, null, null, { bold: true },
                                 { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }, { bold: true }]
                }, {
                    values: ['', '', '', '', '', '', '', '', 'المديونية (' + totals.debt_clients + ' عميل مدين)', '', '', '', '', formatNumber(totals.debt_total), ''],
                    mergeFrom: 1, mergeTo: 8, background: 'warningLight', color: 'warning',
                    cellStyles: [null, null, null, null, null, null, null, null, { bold: true }, null, null, null, null, { bold: true }]
                }, {
                    values: ['', '', '', '', '', '', '', '', 'أرصدة دائنة للعملاء (' + totals.credit_clients + ') — لهم علينا', '', '', '', '', formatNumber(totals.credit_total), ''],
                    mergeFrom: 1, mergeTo: 8, background: 'successLight', color: 'success',
                    cellStyles: [null, null, null, null, null, null, null, null, { bold: true }, null, null, null, null, { bold: true }]
                }],
                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

        function printCustomerBalancesReport() {
            var totals = {
                total_clients: {{ $totals['total_clients'] ?? 0 }},
                opening_entries: {{ $totals['opening_entries'] ?? 0 }},
                opening_remaining: {{ $totals['opening_remaining'] ?? 0 }},
                prior_movements: {{ $totals['prior_movements'] ?? 0 }},
                total_debit: {{ $totals['total_debit'] ?? 0 }},
                total_credit: {{ $totals['total_credit'] ?? 0 }},
                total_balance: {{ $totals['total_balance'] ?? 0 }},
                debt_total: {{ $totals['debt_total'] ?? 0 }},
                debt_clients: {{ $totals['debt_clients'] ?? 0 }},
                credit_total: {{ $totals['credit_total'] ?? 0 }},
                credit_clients: {{ $totals['credit_clients'] ?? 0 }}
            };

            printTable({
                tableSelector: '#printSourceTable tbody tr',
                title: 'تقرير أرصدة العملاء',
                infoCards: [
                    { label: 'عدد العملاء', value: totals.total_clients },
                    { label: 'رصيد افتتاحي', value: formatNumber(totals.opening_entries) },
                    { label: 'لم يُسدَّد من الافتتاحي', value: formatNumber(totals.opening_remaining) },
                    { label: 'حركات سابقة', value: formatNumber(totals.prior_movements) },
                    { label: 'إجمالي المدين', value: formatNumber(totals.total_debit) },
                    { label: 'إجمالي الدائن', value: formatNumber(totals.total_credit) },
                    { label: 'المديونية (' + totals.debt_clients + ' مدين)', value: formatNumber(totals.debt_total) },
                    { label: 'أرصدة دائنة للعملاء (' + totals.credit_clients + ')', value: formatNumber(totals.credit_total) }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', 'الكود', 'العميل', 'تابع لـ', 'الحالة', 'الوضع', 'الهدف', 'الحي', 'الأيام', 'رصيد افتتاحي', 'لم يُسدَّد منه', 'حركات سابقة', 'إجمالي المدين', 'إجمالي الدائن', 'الرصيد النهائي'],
                columnWidths: ['3%', '6%', '12%', '8%', '6%', '6%', '6%', '7%', '5%', '8%', '8%', '8%', '9%', '9%', '9%'],
                descriptionColumn: 2,
                windowWidth: 1200,
                windowHeight: 800
            });
        }

    </script>
@endsection
