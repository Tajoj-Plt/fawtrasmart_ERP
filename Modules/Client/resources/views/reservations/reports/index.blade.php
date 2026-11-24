{{--
    تقاريرُ الحجوزات — صفحةٌ واحدة على تصميم «أعمار ديون الفواتير»:
    ترويسةٌ فاتحة، شريطُ فلترةٍ واحد (الفتراتُ السريعة فوق شبكةٍ من أربعة
    حقولٍ في السطر و«تابع لـ» على سطرٍ كامل)، خمسةُ مؤشّرات، رسمان (أعمدةٌ
    زمنية + حلقةُ الحالات بجدول توزيع)، ثمّ **جدولُ عرضٍ واحد** يتبدّل
    محورُه بأزرار «تجميع حسب» (الحالة، الملغاة، الموظّف، الخدمة…) —
    طلبُ المستخدم (2026-09-21): لا جداول متراصة.

    مصدرُ المحاور `ReservationReportsController::definitions()` وحدَه، وأعمدةُ
    الجدول `columns()` نفسُها التي يقرؤها التصدير — فلا يُكتب شيءٌ هنا مرّتين.

    يُعاد استعمالُ أجزاء تقارير العملاء كما هي (`report-theme` و`report-head`
    و`report-period-tabs` و`report-pager` و`report-charts`)، ومعاملاتُها مسبوقةٌ
    عمداً كي لا يلتقط الـinclude متغيّرَ الشاشة بالاسم نفسِه.

    ترقيمُ الصفحات يُخفي الصفوفَ بالصنف `ag-hidden` لا بخاصية العرض، فتُطبع
    الجداولُ كلُّها لا الصفحةُ المعروضة.

    القالبُ الأمّ `client::reservations.master` لا يُعرّف قسمَ `css`، فورقةُ
    المظهر تُحقَن في متن المحتوى.
--}}
@extends('client::reservations.master')

@section('title', __('reservations.reports.page.title'))

@section('content')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        /* خمسةُ مؤشّرات في سطرٍ واحد على الشاشات العريضة */
        .rpt .ag-kpis--five { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        @media (max-width: 1399.98px) { .rpt .ag-kpis--five { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .rpt .ag-kpis--five { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); } }

        /* شريطُ المراسي: قفزةٌ إلى أيّ تقرير في الصفحة */
        .rpt .ag-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 16px; }
        .rpt .ag-nav__label { font-size: .8rem; font-weight: 700; color: var(--rpt-muted); margin-inline-end: 4px; }
        .rpt .ag-nav a.ag-chip { text-decoration: none; }
        .rpt .ag-nav a.ag-chip:hover, .rpt .ag-nav a.ag-chip.is-active { background: var(--rpt-brand); color: var(--rpt-on-brand); }

        /* قسمُ التقرير: مرساةٌ تُبرَز لحظةَ الوصول إليها من رابط */
        .rpt .ag-section { scroll-margin-top: 90px; margin-top: 16px; }
        .rpt .ag-section.is-target { box-shadow: 0 0 0 2px var(--rpt-brand); }
        .rpt .ag-section__desc { margin: 0; padding: 10px 18px 0; font-size: .8rem; color: var(--rpt-muted); }
        .rpt .ag-section .rpt-card__head { flex-wrap: wrap; gap: 8px; }
        .rpt .ag-table thead th.rpt-num, .rpt .ag-table td.rpt-num { white-space: nowrap; }
        /* زرّان مباشران جنباً إلى جنب — لا قائمة منسدلة تنقصّها الحاوية */
        .rpt .ag-table td.ag-extra { width: 1%; white-space: nowrap; }
        .rpt .ag-table td.ag-extra .ag-actions-btn + .ag-actions-btn { margin-inline-start: 6px; }
        .rpt .ag-charts { margin-bottom: 0; }
        @media print { .rpt .ag-nav { display: none !important; } }
    </style>

    @php
        $currency = \App\Support\CurrencySettings::name();
        $periodLabel = __('reservations.reports.page.period_label', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y')]);
        $canList = hasPermission('reservations.view');
        $canExport = hasPermission('reservation_reports.export');
        $currentStatus = (string) (((array) request('status', []))[0] ?? '');

        // الفلاترُ الحيّةُ تنتقل مع كلّ رابطٍ إلى القائمة فلا يفترق الرقمُ عمّا تحته
        $carry = request()->only(['tree_node', 'exact_node', 'employee_id', 'product_id', 'client_id', 'status']);
        $exportQuery = request()->query();

        $format = fn ($value, string $type) => match ($type) {
            'money' => number_format((float) $value, 2),
            'percent' => $value.'%',
            default => number_format((int) $value),
        };

        /* رابطُ صفّ التقرير إلى قائمة الحجوزات بفلتره: يومٌ، شهرٌ، حالةٌ، موظّف… */
        $rowLink = function (array $definition, array $row) use ($carry, $from, $to, $canList) {
            if (! $canList) {
                return null;
            }

            $q = array_merge($carry, ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

            if (! empty($definition['status'])) {
                $q['status'] = $definition['status'];
            }

            switch ($definition['group']) {
                case 'date':
                    $q['date_from'] = $q['date_to'] = (string) $row['key'];
                    break;
                case 'month':
                    $q['date_from'] = $row['key'].'-01';
                    $q['date_to'] = \Illuminate\Support\Carbon::parse($row['key'].'-01')->endOfMonth()->toDateString();
                    break;
                case 'status':
                    $q['status'] = [$row['key']];
                    break;
                case 'branch_id':
                    $q['tree_node'] = $row['key'];
                    $q['exact_node'] = 1;
                    break;
                default:
                    $q[$definition['group']] = $row['key'];
            }

            return route('Reservations.index', $q);
        };

        $guide = [
            'title' => __('reservations.guide.reports_title'),
            'lead' => __('reservations.guide.reports_lead'),
            'blocks' => __('reservations.guide.reports_blocks'),
            'facts' => [],
        ];

        $timelineTitle = $timeline['mode'] === 'day'
            ? __('reservations.reports.page.charts.timeline_day')
            : __('reservations.reports.page.charts.timeline_month');
    @endphp

    <div class="rpt">
        {{-- ===================== الترويسة ===================== --}}
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'calendar-check',
            'headTitle' => __('reservations.reports.page.title'),
            'headSubtitle' => __('reservations.reports.page.subtitle'),
            'headPeriod' => $periodLabel,
            'headExtra' => $definition['title'],
            'headGuideKey' => 'reservations-reports.hub',
            'headGuide' => $guide,
            'headCrumbLabel' => __('reservations.menu.reservations'),
            'headCrumbUrl' => route('Reservations.index'),
        ])

        @include('client::reservations.partials.alerts')

        {{-- ===================== الفلترة ===================== --}}
        <div class="rpt-card rpt-filters no-print">
            <div class="rpt-card__body">
                <form method="GET" id="filterForm" action="{{ route('Reservations.reports.index') }}">
                    <input type="hidden" name="period" id="periodInput" value="{{ $period }}">

                    @include('client::customers.CustomerReport.partials.report-period-tabs', [
                        'tabPeriods' => $periods,
                        'tabCurrent' => $period,
                    ])

                    <div class="ag-filter-grid">
                        <div class="rpt-field">
                            <label for="date_from">{{ __('reservations.reports.page.filters.range') }}</label>
                            <div class="ag-range">
                                <input type="date" name="date_from" id="date_from" class="form-control" aria-label="{{ __('reservations.attributes.date_from') }}" value="{{ $from->format('Y-m-d') }}">
                                <span class="ag-range__sep">{{ __('reservations.reports.page.filters.to') }}</span>
                                <input type="date" name="date_to" id="date_to" class="form-control" aria-label="{{ __('reservations.attributes.date_to') }}" value="{{ $to->format('Y-m-d') }}">
                            </div>
                        </div>

                        <div class="rpt-field">
                            <label for="status">{{ __('reservations.columns.status') }}</label>
                            <select name="status[]" id="status" class="form-control">
                                <option value="">{{ __('reservations.reports.page.filters.all_statuses') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($currentStatus === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rpt-field">
                            <label for="product_id">{{ __('reservations.columns.service') }}</label>
                            <select name="product_id" id="product_id" class="form-control">
                                <option value="">{{ __('reservations.reports.page.filters.all_services') }}</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected(request('product_id') == $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rpt-field">
                            <label for="employee_id">{{ __('reservations.columns.employee') }}</label>
                            <select name="employee_id" id="employee_id" class="form-control">
                                <option value="">{{ __('reservations.reports.page.filters.all_employees') }}</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rpt-field">
                            {{-- `#rsv-client` يستثنيه تزيينُ القالب الأمّ فلا يُهيَّأ select2 مرّتين --}}
                            @include('reports.partials.client-select', [
                                'name' => 'client_id',
                                'id' => 'rsv-client',
                                'url' => route('Reservations.clients'),
                                'selectedClient' => $selectedClient ?? null,
                                'placeholder' => __('reservations.reports.page.filters.client_placeholder'),
                                'label' => __('reservations.columns.client'),
                            ])
                        </div>

                        {{-- «مفوتَر» من الفاتورة القائمة — الفلترُ نفسُه في `applyFilters()` --}}
                        <div class="rpt-field">
                            <label for="invoiced">{{ __('reservations.reports.page.filters.invoiced') }}</label>
                            <select name="invoiced" id="invoiced" class="form-control">
                                <option value="">{{ __('reservations.ui.all') }}</option>
                                <option value="1" @selected(request('invoiced') === '1')>{{ __('reservations.reports.page.filters.invoiced_yes') }}</option>
                                <option value="0" @selected(request('invoiced') === '0')>{{ __('reservations.reports.page.filters.invoiced_no') }}</option>
                            </select>
                        </div>

                        {{-- التجميعُ على حسب — داخل شبكة الفلاتر ليكون بجانب بقيّة الحقول --}}
                        <div class="rpt-field">
                            <label for="reportAxis"><i class="fas fa-layer-group"></i> {{ __('reservations.reports.page.nav') }}</label>
                            <select id="reportAxis" name="report" class="form-control" data-no-select2>
                                @foreach ($definitions as $key => $def)
                                    <option value="{{ $key }}" @selected($key === $report)>{{ $def['title'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                        <div class="ag-span-all">
                            <div class="row">
                                @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                            </div>
                        </div>
                    </div>

                    <div class="ag-filter-foot">
                        <span class="ag-hint"><i class="fas fa-info-circle"></i> {{ __('reservations.reports.page.filters.note') }}</span>
                        <div class="ag-quick__actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> {{ __('reservations.reports.page.filters.apply') }}</button>
                            <a href="{{ route('Reservations.reports.index') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> {{ __('reservations.reports.page.filters.reset') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===================== المؤشّرات ===================== --}}
        <div class="ag-kpis ag-kpis--five">
            <div class="ag-kpi" style="--c:#3b82f6">
                <div>
                    <div class="ag-kpi__label">{{ __('reservations.reports.page.kpis.count') }}</div>
                    <div class="ag-kpi__value"><bdi id="kpiCount">{{ number_format($kpis['count']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ __('reservations.reports.page.kpis.count_sub', ['total' => number_format($kpis['total'], 2), 'currency' => $currency]) }}</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-calendar-check"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#10b981">
                <div>
                    <div class="ag-kpi__label">{{ __('reservations.reports.page.kpis.completed') }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['completed']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ __('reservations.reports.page.kpis.completed_sub', ['confirmed' => number_format($kpis['confirmed']), 'active' => number_format($kpis['active'])]) }}</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-check-double"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#ef4444">
                <div>
                    <div class="ag-kpi__label">{{ __('reservations.reports.page.kpis.lost') }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['lost']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ __('reservations.reports.page.kpis.lost_sub', ['cancelled' => number_format($kpis['cancelled']), 'no_show' => number_format($kpis['no_show'])]) }}</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-user-times"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#f97316">
                <div>
                    <div class="ag-kpi__label">{{ __('reservations.reports.page.kpis.moves') }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['moves']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ __('reservations.reports.page.kpis.moves_sub', ['moved' => number_format($kpis['moved'])]) }}</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-redo"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#8b5cf6">
                <div>
                    <div class="ag-kpi__label">{{ __('reservations.reports.page.kpis.invoiced') }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['invoiced']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ __('reservations.reports.page.kpis.invoiced_sub', ['amount' => number_format($kpis['invoice_amount'], 2), 'currency' => $currency]) }}</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-file-invoice-dollar"></i></span>
            </div>
        </div>

        {{-- ===================== الرسمان ===================== --}}
        <div class="ag-charts">
            <div class="rpt-card">
                <div class="rpt-card__head">
                    <h4><i class="fas fa-chart-bar"></i> {{ $timelineTitle }}</h4>
                </div>
                <div class="rpt-card__body">
                    @if ($kpis['count'] > 0)
                        <div class="ag-canvas"><canvas id="rsvTimeline" aria-label="{{ $timelineTitle }}" role="img"></canvas></div>
                    @else
                        <div class="rpt-empty"><i class="fas fa-chart-bar"></i> {{ __('reservations.reports.page.charts.no_data') }}</div>
                    @endif
                </div>
            </div>

            <div class="rpt-card">
                <div class="rpt-card__head">
                    <h4><i class="fas fa-chart-pie"></i> {{ __('reservations.reports.page.charts.distribution') }}</h4>
                    <span class="ag-hint no-print">{{ __('reservations.reports.page.charts.distribution_hint') }}</span>
                </div>
                <div class="rpt-card__body">
                    @if ($kpis['count'] > 0)
                        <div class="ag-dist">
                            <table class="ag-legend">
                                <tbody>
                                    @foreach ($statusSlices as $slice)
                                        <tr class="ag-legend__row {{ $currentStatus === $slice['key'] ? 'is-active' : '' }}"
                                            style="--c:{{ $slice['color'] }}" data-status="{{ $slice['key'] }}" tabindex="0"
                                            title="{{ $currentStatus === $slice['key'] ? __('reservations.reports.page.charts.clear_status') : __('reservations.reports.page.charts.only_status') }}">
                                            <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                            <td class="ag-legend__amount"><bdi>{{ number_format($slice['value']) }}</bdi></td>
                                            <td class="ag-legend__pct">{{ $slice['pct'] }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="ag-donut">
                                <canvas id="rsvDonut" aria-label="{{ __('reservations.reports.page.charts.distribution') }}" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ number_format($kpis['count']) }}</strong>
                                    <small>{{ __('reservations.reports.page.charts.reservation_unit') }}</small>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="rpt-empty"><i class="fas fa-chart-pie"></i> {{ __('reservations.reports.page.charts.no_data') }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===================== الجدول الواحد بمحاور التجميع ===================== --}}
        @php
            $unit = __('reservations.reports.page.units.'.$definition['unit']);
            /* روابطُ الأزرار تحمل فلاتر الصفحة نفسَها ومحورُ التجميع وحدَه يتغيّر */
            $reportLink = fn (string $key) => route('Reservations.reports.index', array_merge(request()->except('report'), ['report' => $key]));
        @endphp

        <div class="rpt-card ag-section" id="rpt-table" data-title="{{ $definition['title'] }}">
            <div class="rpt-card__head ag-table-head">
                <h4>
                    <i class="fas fa-{{ $definition['icon'] }}"></i> {{ $definition['title'] }}
                    <span class="rpt-pill rpt-pill-secondary">{{ number_format(count($rows)) }} {{ $unit }}</span>
                </h4>
                <div class="ag-tools no-print">
                    {{-- اختيارُ عدد الصفوف — مربوطٌ بشريط الترقيم بالبادئة نفسِها؛ `data-no-select2` يُبقيه قائمةً خامّة --}}
                    <label class="ag-page-size no-print" for="rptPageSize">
                        <span>{{ __('reservations.reports.page.tools.rows') }}</span>
                        <select id="rptPageSize" class="form-control" data-no-select2>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="0">{{ __('reservations.ui.all') }}</option>
                        </select>
                    </label>
                    @if ($canExport)
                        <a class="btn btn-sm ag-btn-soft" href="{{ route('Reservations.reports.export', array_merge([$report], $exportQuery)) }}">
                            <i class="fas fa-file-excel"></i> {{ __('reservations.reports.page.tools.export') }}
                        </a>
                    @endif
                    <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> {{ __('reservations.reports.page.tools.print') }}</button>
                </div>
            </div>

            <p class="ag-section__desc">{{ $definition['description'] }}</p>

            <div class="rpt-scroll table-responsive">
                <table class="rpt-table ag-table" id="tbl-report">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('reservations.columns.label') }}</th>
                            @foreach ($columns as $column)
                                <th class="rpt-num">{{ $column['label'] }}</th>
                            @endforeach
                            <th class="ag-extra">{{ __('reservations.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-report">
                        @forelse ($rows as $row)
                            @php $link = $rowLink($definition, $row); @endphp
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">
                                    @if ($definition['group'] === 'status')
                                        <span class="rpt-pill" style="background-color: {{ \Modules\Client\Reservations\Enums\ReservationStatus::from_value($row['key'])->color() }}; color: #fff;">{{ $row['label'] }}</span>
                                    @elseif ($definition['group'] === 'client_id')
                                        <a href="{{ route('clients.show', $row['key']) }}" target="_blank" rel="noopener" class="ag-link"><strong>{{ $row['label'] }}</strong></a>
                                    @else
                                        <strong>{{ $row['label'] }}</strong>
                                    @endif
                                </td>
                                @foreach ($columns as $column)
                                    @php $value = $row['values'][$column['key']] ?? 0; @endphp
                                    <td class="rpt-num {{ in_array($column['key'], ['cancelled', 'no_show'], true) && $value > 0 ? 'rpt-neg' : '' }} {{ in_array($column['key'], ['completed', 'invoice_amount'], true) && $value > 0 ? 'rpt-pos' : '' }}">
                                        {{ $format($value, $column['type']) }}
                                    </td>
                                @endforeach
                                {{-- أزرارٌ مباشرة لا قائمةٌ منسدلة: القائمة كانت تنقصّها
                                     حاويةُ التمرير (`.rpt-scroll{overflow-x:auto}`) فتظهر
                                     مبتورةً — والفعلان لا يحتاجان طيّاً أصلاً --}}
                                <td class="ag-extra">
                                    @if ($link)
                                        <a class="ag-actions-btn" href="{{ $link }}" target="_blank" rel="noopener" title="{{ __('reservations.reports.page.actions.view_reservations') }}" aria-label="{{ __('reservations.reports.page.actions.view_reservations') }}"><i class="fas fa-list"></i></a>
                                    @endif
                                    @if ($definition['group'] === 'client_id')
                                        <a class="ag-actions-btn" href="{{ route('clients.show', $row['key']) }}" target="_blank" rel="noopener" title="{{ __('reservations.reports.page.actions.client_file') }}" aria-label="{{ __('reservations.reports.page.actions.client_file') }}"><i class="fas fa-user"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) + 3 }}" class="rpt-empty"><i class="fas fa-inbox"></i> {{ __('reservations.reports.page.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($rows))
                        <tfoot id="tfoot-report">
                            <tr>
                                <td colspan="2">{{ __('reservations.columns.total_row') }}</td>
                                @foreach ($columns as $column)
                                    <td class="rpt-num">{{ array_key_exists($column['key'], $totals) ? $format($totals[$column['key']], $column['type']) : '' }}</td>
                                @endforeach
                                <td class="ag-extra"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @include('client::customers.CustomerReport.partials.report-pager', [
                'pagerId' => 'rpt',
                'pagerRows' => '#tbody-report tr[data-row]',
                'pagerUnit' => $unit,
                'pagerStorage' => 'rsvReports_PageSize',
                'pagerScroll' => '#rpt-table',
            ])
        </div>

        {{-- ذيلُ الطباعة بلا عمود «إجراءات» — يُملأ لحظةَ الطباعة --}}
        <table hidden><tfoot id="rsvPrintFoot"></tfoot></table>

        @include('sales::sals_report.report_partials.print-footer')
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    @include('client::customers.CustomerReport.partials.report-charts')

    <script>
        const RSV_TIMELINE = @json($timeline);
        const RSV_STATUS = @json($statusSlices);
        const RSV_CURRENT_REPORT = @json($report);
        const RSV_REPORT_LINKS = @json(collect($definitions)->mapWithKeys(fn ($d, $k) => [$k => $reportLink($k)]));
        const RSV_I18N = {
            nothingToPrint: @json(__('reservations.reports.page.tools.nothing_to_print')),
            reportDate: @json(__('reservations.reports.page.report_date')),
            period: @json(__('reservations.reports.page.period')),
            periodLabel: @json($periodLabel),
            unit: @json(__('reservations.reports.page.charts.reservation_unit'))
        };

        $(document).ready(function () {
            /* الفتراتُ السريعة: الخادمُ يحسب مداها (الحجوزاتُ تمتدّ إلى المستقبل)،
               والمتصفّحُ يرسل اسمَها فقط. «مخصص» يترك الحقلين للمستخدم. */
            $('.period-btn').on('click', function () {
                var period = String($(this).data('period'));
                $('#periodInput').val(period);

                if (period === 'custom') {
                    $('.period-btn').removeClass('is-active');
                    $(this).addClass('is-active');
                    $('#date_from').trigger('focus');
                    return;
                }

                $('#filterForm').trigger('submit');
            });

            /* تعديلُ التاريخ يدوياً يُلغي الفترةَ السريعة وإلّا دهسها الخادم */
            $('#date_from, #date_to').on('change', function () {
                $('#periodInput').val('custom');
                $('.period-btn').removeClass('is-active').filter('[data-period="custom"]').addClass('is-active');
            });

            /* صفُّ جدول التوزيع يقصر الصفحةَ كلَّها على حالته — ويُلغيها بنقرةٍ ثانية */
            $(document).on('click', '.ag-legend__row[data-status]', function () {
                applyStatusFilter(String($(this).data('status')));
            });
            $(document).on('keydown', '.ag-legend__row[data-status]', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyStatusFilter(String($(this).data('status'))); }
            });

            /* طباعةُ الجدول — رؤوسُه بلا عمود «إجراءات»، وذيلُه منسوخٌ بلا خليّته */
            $('#printBtn').on('click', printReport);

            /* قائمةُ «تجميع حسب»: الاختيارُ يفتح محورَه حاملاً فلاتر الصفحة */
            $('#reportAxis').on('change', function () {
                var key = String($(this).val());
                if (RSV_REPORT_LINKS[key] && key !== RSV_CURRENT_REPORT) {
                    window.location = RSV_REPORT_LINKS[key];
                }
            });

            /* رابطٌ قديم بمرساة `#rpt-daily` — يُفتح محورُه بدل التجاهل */
            var hashKey = (location.hash || '').replace(/^#/, '').replace(/^rpt-/, '');
            if (hashKey && RSV_REPORT_LINKS[hashKey] && hashKey !== RSV_CURRENT_REPORT) {
                window.location.replace(RSV_REPORT_LINKS[hashKey]);
            }

            renderCharts();
        });

        function applyStatusFilter(status) {
            var $status = $('#status');
            $status.val($status.val() === status ? '' : status);
            $('#periodInput').val($('#periodInput').val() || 'custom');
            $('#filterForm').trigger('submit');
        }

        function printReport() {
            var section = document.getElementById('rpt-table');
            if (!section || !section.querySelector('#tbody-report tr[data-row]')) {
                agWarn(RSV_I18N.nothingToPrint);
                return;
            }

            var headers = $('#tbl-report thead th').not('.ag-extra').map(function () { return $(this).text().trim(); }).get();
            var foot = document.getElementById('tfoot-report');
            var printFoot = document.getElementById('rsvPrintFoot');
            printFoot.innerHTML = '';

            if (foot) {
                var row = foot.rows[0].cloneNode(true);
                row.querySelectorAll('.ag-extra').forEach(function (cell) { cell.remove(); });
                printFoot.appendChild(row);
            }

            var filterInfo = {};
            filterInfo[RSV_I18N.reportDate] = new Date().toISOString().slice(0, 10);
            filterInfo[RSV_I18N.period] = RSV_I18N.periodLabel;

            printTable({
                tableSelector: '#tbody-report tr[data-row]',
                tfootSelector: foot ? '#rsvPrintFoot' : null,
                title: section.getAttribute('data-title'),
                filterInfo: filterInfo,
                tableHeaders: headers,
                skipColumns: [headers.length],
                descriptionColumn: 1,
                windowWidth: 1400,
                windowHeight: 900
            });
        }

        function renderCharts() {
            if (typeof Chart === 'undefined') return;

            var theme = agChartTheme();

            var barEl = document.getElementById('rsvTimeline');
            if (barEl) {
                var values = RSV_TIMELINE.values;

                new Chart(barEl, {
                    type: 'bar',
                    data: {
                        labels: RSV_TIMELINE.labels,
                        datasets: [{ data: values, backgroundColor: theme.brand, borderRadius: 6, maxBarThickness: 48 }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agWhole(c.raw) + ' ' + RSV_I18N.unit; } } }
                        },
                        scales: {
                            x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 16 } },
                            y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                 ticks: { color: theme.muted, precision: 0, callback: function (v) { return agWhole(v); } } }
                        }
                    },
                    plugins: [agValueLabels(values)]
                });
            }

            var donutEl = document.getElementById('rsvDonut');
            if (donutEl) {
                var donut = new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: RSV_STATUS.map(function (s) { return s.label; }),
                        datasets: [{
                            data: RSV_STATUS.map(function (s) { return s.value; }),
                            backgroundColor: RSV_STATUS.map(function (s) { return s.color; }),
                            borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: { label: function (c) { var s = RSV_STATUS[c.dataIndex]; return ' ' + agWhole(s.value) + ' (' + s.pct + '%)'; } } }
                        }
                    }
                });

                donut.canvas.style.cursor = 'pointer';
                donut.canvas.addEventListener('click', function (evt) {
                    var hit = donut.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                    if (hit.length) applyStatusFilter(RSV_STATUS[hit[0].index].key);
                });
            }
        }
    </script>
@endsection
