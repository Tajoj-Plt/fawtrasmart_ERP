{{--
    تقرير مواعيد العملاء — صفحة مستقلة على تصميم «أعمار الديون»: فلاتر
    واحدة، خمسة مؤشرات، رسمان (أعمدة زمنية + حلقة الحالات)، ثم **جدول
    عرضٍ واحد** يتبدّل محورُه بأزرار «تجميع حسب» (الموظف/الحالة/اليوم/
    العميل) — طلب المستخدم (2026-09-20): لا جداول متراصة.

    قرار المستخدم نفسه: تقرير المواعيد منفصل عن تقرير الحجوزات، وكلاهما
    يُفتح من لوحة تقارير العملاء.

    مصدر المحاور `CustomerAppointmentsController::definitions()` وحده،
    وأعمدة الجدول `columns()` نفسها التي يقرؤها التصدير.

    ترقيم الصفحات يخفي الصفوف بالصنف لا بخاصية العرض، فتُطبع كلها.
--}}
@extends('client::master')

@section('title')
    تقرير مواعيد العملاء
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-kpis--five { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        @media (max-width: 1399.98px) { .rpt .ag-kpis--five { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .rpt .ag-kpis--five { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); } }

        .rpt .ag-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 16px; }
        .rpt .ag-nav__label { font-size: .8rem; font-weight: 700; color: var(--rpt-muted); margin-inline-end: 4px; }
        .rpt .ag-nav a.ag-chip { text-decoration: none; }
        .rpt .ag-nav a.ag-chip:hover, .rpt .ag-nav a.ag-chip.is-active { background: var(--rpt-brand); color: var(--rpt-on-brand); }

        .rpt .ag-section { scroll-margin-top: 90px; margin-top: 16px; }
        .rpt .ag-section.is-target { box-shadow: 0 0 0 2px var(--rpt-brand); }
        .rpt .ag-section__desc { margin: 0; padding: 10px 18px 0; font-size: .8rem; color: var(--rpt-muted); }
        .rpt .ag-section .rpt-card__head { flex-wrap: wrap; gap: 8px; }
        .rpt .ag-table thead th.rpt-num, .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt .ag-table td.ag-extra { width: 48px; }
        .rpt .ag-charts { margin-bottom: 0; }
        @media print { .rpt .ag-nav { display: none !important; } }
    </style>
@endsection

@section('content')
    @php
        $periodLabel = 'من '.$from->format('d/m/Y').' إلى '.$to->format('d/m/Y');
        $currentStatus = (string) request('appointment_status', '');
        $exportQuery = request()->query();

        $format = fn ($value, string $type) => $type === 'percent' ? $value.'%' : number_format((int) $value);
    @endphp

    <div class="rpt">
        {{-- ===================== الترويسة ===================== --}}
        @include('client::customers.CustomerReport.partials.report-head', [
            'headIcon' => 'calendar-alt',
            'headTitle' => 'تقرير مواعيد العملاء',
            'headSubtitle' => 'مواعيد العملاء بالحالات والموظفين والأيام — أقسام بفلاتر واحدة',
            'headPeriod' => $periodLabel,
            'headExtra' => 'تجميع حسب: '.$definition['chip'],
            'headGuideKey' => 'client-reports.appointments',
            'headGuide' => [
                'title' => 'ما هذه الشاشة؟',
                'lead' => 'كل تقارير المواعيد في جدول واحد يتبدّل محوره — والفلاتر أعلى الصفحة تسري على الجدول والمؤشرات والرسوم معاً.',
                'blocks' => [['جدول واحد يتبدّل محوره بأزرار «تجميع حسب» فوقه: الموظف، الحالة (الإلغاء وغيرها)، اليوم، العميل.', 'الموعد المشترك بين موظفين يُحسب لكل واحد منهم في «حسب الموظف».']],
                'facts' => [],
            ],
            'headCrumbLabel' => 'تقارير العملاء',
            'headCrumbUrl' => route('ClientReport.index'),
        ])

        {{-- ===================== الفلترة ===================== --}}
        <div class="rpt-card rpt-filters no-print">
            <div class="rpt-card__body">
                <form method="GET" id="filterForm" action="{{ route('ClientReport.customerAppointments') }}">
                    <input type="hidden" name="period" id="periodInput" value="{{ $period }}">
                    {{-- الفلترة تُبقي محور التجميع المختار --}}
                    <input type="hidden" name="group" value="{{ $group }}">

                    @include('client::customers.CustomerReport.partials.report-period-tabs', [
                        'tabPeriods' => $periods,
                        'tabCurrent' => $period,
                    ])

                    <div class="ag-filter-grid">
                        <div class="rpt-field">
                            <label for="date_from">الفترة</label>
                            <div class="ag-range">
                                <input type="date" name="date_from" id="date_from" class="form-control" aria-label="من تاريخ" value="{{ $from->format('Y-m-d') }}">
                                <span class="ag-range__sep">إلى</span>
                                <input type="date" name="date_to" id="date_to" class="form-control" aria-label="إلى تاريخ" value="{{ $to->format('Y-m-d') }}">
                            </div>
                        </div>

                        <div class="rpt-field">
                            <label for="appointment_status">حالة الموعد</label>
                            <select name="appointment_status" id="appointment_status" class="form-control">
                                <option value="">كل الحالات</option>
                                @foreach ($statuses as $value => $status)
                                    <option value="{{ $value }}" @selected($currentStatus === (string) $value)>{{ $status['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rpt-field">
                            <label for="employee_id">موظّف الموعد</label>
                            <select name="employee_id" id="employee_id" class="form-control">
                                <option value="">كل الموظفين</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rpt-field">
                            @include('reports.partials.client-select', [
                                'name' => 'client_id',
                                'selectedClient' => $selectedClient ?? null,
                                'label' => 'العميل',
                            ])
                        </div>
                    </div>

                    <div class="ag-filter-foot">
                        <span class="ag-hint"><i class="fas fa-info-circle"></i> الفلاتر تسري على كل الأقسام والمؤشرات والرسوم معاً.</span>
                        <div class="ag-quick__actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> تطبيق</button>
                            <a href="{{ route('ClientReport.customerAppointments') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===================== المؤشّرات ===================== --}}
        <div class="ag-kpis ag-kpis--five">
            <div class="ag-kpi" style="--c:#3b82f6">
                <div>
                    <div class="ag-kpi__label">إجمالي المواعيد</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['count']) }}</bdi></div>
                    <div class="ag-kpi__sub">منها {{ number_format($kpis['shared']) }} مشارَكة مع العميل</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-calendar-alt"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#10b981">
                <div>
                    <div class="ag-kpi__label">{{ $statuses[\App\Models\Appointment::STATUS_COMPLETED]['label'] }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['completed']) }}</bdi></div>
                    <div class="ag-kpi__sub">نسبة الإنجاز {{ $kpis['completion_rate'] }}%</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-check-double"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#3b82f6">
                <div>
                    <div class="ag-kpi__label">{{ $statuses[\App\Models\Appointment::STATUS_PENDING]['label'] }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['pending']) }}</bdi></div>
                    <div class="ag-kpi__sub">بانتظار وقتها</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-hourglass-half"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#f97316">
                <div>
                    <div class="ag-kpi__label">{{ $statuses[\App\Models\Appointment::STATUS_RESCHEDULED]['label'] }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['rescheduled']) }}</bdi></div>
                    <div class="ag-kpi__sub">نُقلت إلى وقت آخر</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-redo"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#ef4444">
                <div>
                    <div class="ag-kpi__label">{{ $statuses[\App\Models\Appointment::STATUS_IGNORED]['label'] }}</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($kpis['ignored']) }}</bdi></div>
                    <div class="ag-kpi__sub">لم تُنفَّذ</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-user-times"></i></span>
            </div>
        </div>

        {{-- ===================== الرسمان ===================== --}}
        <div class="ag-charts">
            <div class="rpt-card">
                <div class="rpt-card__head">
                    <h4><i class="fas fa-chart-bar"></i> {{ $timeline['mode'] === 'day' ? 'المواعيد يومياً' : 'المواعيد شهرياً' }}</h4>
                </div>
                <div class="rpt-card__body">
                    @if ($kpis['count'] > 0)
                        <div class="ag-canvas"><canvas id="apTimeline" aria-label="المواعيد على المدى" role="img"></canvas></div>
                    @else
                        <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا بيانات في المدى المختار</div>
                    @endif
                </div>
            </div>

            <div class="rpt-card">
                <div class="rpt-card__head">
                    <h4><i class="fas fa-chart-pie"></i> توزيع الحالات</h4>
                    <span class="ag-hint no-print">اضغط حالة لقصر الصفحة عليها</span>
                </div>
                <div class="rpt-card__body">
                    @if ($kpis['count'] > 0)
                        <div class="ag-dist">
                            <table class="ag-legend">
                                <tbody>
                                    @foreach ($statusSlices as $slice)
                                        <tr class="ag-legend__row {{ $currentStatus === (string) $slice['key'] ? 'is-active' : '' }}"
                                            style="--c:{{ $slice['color'] }}" data-status="{{ $slice['key'] }}" tabindex="0">
                                            <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                            <td class="ag-legend__amount"><bdi>{{ number_format($slice['value']) }}</bdi></td>
                                            <td class="ag-legend__pct">{{ $slice['pct'] }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="ag-donut">
                                <canvas id="apDonut" aria-label="توزيع الحالات" role="img"></canvas>
                                <div class="ag-donut__center">
                                    <strong>{{ number_format($kpis['count']) }}</strong>
                                    <small>موعد</small>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا بيانات في المدى المختار</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===================== الجدول الواحد بمحاور التجميع ===================== --}}
        @php
            /* روابط الأزرار تحمل فلاتر الصفحة نفسها ومحورَ التجميع وحده يتغيّر */
            $groupLink = fn (string $key) => route('ClientReport.customerAppointments', array_merge(request()->except('group'), ['group' => $key]));
        @endphp

        <div class="rpt-card ag-section" id="rpt-table" data-title="{{ $definition['title'] }}">
            <div class="rpt-card__head ag-table-head">
                <h4>
                    <i class="fas fa-{{ $definition['icon'] }}"></i> {{ $definition['title'] }}
                    <span class="rpt-pill rpt-pill-secondary">{{ number_format(count($rows)) }} {{ $definition['unit'] }}</span>
                </h4>
                <div class="ag-tools no-print">
                    <label class="ag-page-size no-print" for="rptPageSize">
                        <span>صفوف</span>
                        <select id="rptPageSize" class="form-control" data-no-select2>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="0">الكل</option>
                        </select>
                    </label>
                    <a class="btn btn-sm ag-btn-soft" href="{{ route('ClientReport.customerAppointmentsExport', array_merge([$group], $exportQuery)) }}">
                        <i class="fas fa-file-excel"></i> تصدير
                    </a>
                    <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
                </div>
            </div>

            {{-- محور التجميع — قائمة منسدلة (طلب المستخدم 2026-09-21)، والجدول واحد --}}
            <nav class="ag-nav no-print" style="padding: 10px 18px 0; margin-bottom: 0;" aria-label="تجميع حسب">
                <label class="ag-page-size" for="groupAxis" style="margin: 0;">
                    <span><i class="fas fa-layer-group"></i> تجميع حسب:</span>
                    <select id="groupAxis" class="form-control" data-no-select2 style="width: auto; min-width: 180px;">
                        @foreach ($definitions as $key => $def)
                            <option value="{{ $key }}" @selected($key === $group)>{{ $def['chip'] }}</option>
                        @endforeach
                    </select>
                </label>
            </nav>

            <p class="ag-section__desc">{{ $definition['description'] }}</p>

            <div class="rpt-scroll table-responsive">
                <table class="rpt-table ag-table" id="tbl-report">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>البيان</th>
                            @foreach ($columns as $column)
                                <th class="rpt-num">{{ $column['label'] }}</th>
                            @endforeach
                            <th class="ag-extra">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-report">
                        @forelse ($rows as $row)
                            <tr data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">
                                    @if ($definition['group'] === 'status')
                                        <span class="rpt-pill" style="background-color: {{ $statuses[(int) $row['key']]['color'] ?? '#64748b' }}; color: #fff;">{{ $row['label'] }}</span>
                                    @elseif ($definition['group'] === 'client_id')
                                        <a href="{{ route('clients.show', $row['key']) }}" target="_blank" rel="noopener" class="ag-link"><strong>{{ $row['label'] }}</strong></a>
                                    @else
                                        <strong>{{ $row['label'] }}</strong>
                                    @endif
                                </td>
                                @foreach ($columns as $column)
                                    @php $value = $row['values'][$column['key']] ?? 0; @endphp
                                    <td class="rpt-num {{ $column['key'] === 'ignored' && $value > 0 ? 'rpt-neg' : '' }} {{ $column['key'] === 'completed' && $value > 0 ? 'rpt-pos' : '' }}">
                                        {{ $format($value, $column['type']) }}
                                    </td>
                                @endforeach
                                {{-- زر مباشر لا قائمة منسدلة — القائمة كانت تنقصّها حاوية التمرير --}}
                                <td class="ag-extra">
                                    @if ($definition['group'] === 'client_id')
                                        <a class="ag-actions-btn" href="{{ route('clients.show', $row['key']) }}" target="_blank" rel="noopener" title="ملف العميل" aria-label="ملف العميل"><i class="fas fa-user"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) + 3 }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا مواعيد في المدى المختار</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($rows))
                        <tfoot id="tfoot-report">
                            <tr>
                                <td colspan="2">الإجمالي</td>
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
                'pagerUnit' => $definition['unit'],
                'pagerStorage' => 'apReports_PageSize',
                'pagerScroll' => '#rpt-table',
            ])
        </div>

        {{-- ذيل الطباعة بلا عمود «إجراءات» — يُملأ لحظة الطباعة --}}
        <table hidden><tfoot id="apPrintFoot"></tfoot></table>

        @include('sales::sals_report.report_partials.print-footer')
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    @include('client::customers.CustomerReport.partials.report-charts')

    <script>
        const AP_TIMELINE = @json($timeline);
        const AP_STATUS = @json($statusSlices);
        const AP_PERIOD_LABEL = @json($periodLabel);
        const AP_CURRENT_GROUP = @json($group);
        const AP_GROUP_LINKS = @json(collect($definitions)->mapWithKeys(fn ($d, $k) => [$k => $groupLink($k)]));

        $(document).ready(function () {
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

            $('#date_from, #date_to').on('change', function () {
                $('#periodInput').val('custom');
                $('.period-btn').removeClass('is-active').filter('[data-period="custom"]').addClass('is-active');
            });

            $(document).on('click', '.ag-legend__row[data-status]', function () {
                applyStatusFilter(String($(this).data('status')));
            });
            $(document).on('keydown', '.ag-legend__row[data-status]', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyStatusFilter(String($(this).data('status'))); }
            });

            $('#printBtn').on('click', printReport);

            /* قائمة «تجميع حسب»: الاختيار يفتح محوره حاملاً فلاتر الصفحة */
            $('#groupAxis').on('change', function () {
                var key = String($(this).val());
                if (AP_GROUP_LINKS[key] && key !== AP_CURRENT_GROUP) {
                    window.location = AP_GROUP_LINKS[key];
                }
            });

            renderCharts();
        });

        function applyStatusFilter(status) {
            var $status = $('#appointment_status');
            $status.val($status.val() === status ? '' : status);
            $('#periodInput').val($('#periodInput').val() || 'custom');
            $('#filterForm').trigger('submit');
        }

        function printReport() {
            var section = document.getElementById('rpt-table');
            if (!section || !section.querySelector('#tbody-report tr[data-row]')) {
                agWarn('لا صفوف لطباعتها');
                return;
            }

            var headers = $('#tbl-report thead th').not('.ag-extra').map(function () { return $(this).text().trim(); }).get();
            var foot = document.getElementById('tfoot-report');
            var printFoot = document.getElementById('apPrintFoot');
            printFoot.innerHTML = '';

            if (foot) {
                var row = foot.rows[0].cloneNode(true);
                row.querySelectorAll('.ag-extra').forEach(function (cell) { cell.remove(); });
                printFoot.appendChild(row);
            }

            printTable({
                tableSelector: '#tbody-report tr[data-row]',
                tfootSelector: foot ? '#apPrintFoot' : null,
                title: section.getAttribute('data-title'),
                filterInfo: { 'تاريخ التقرير': new Date().toISOString().slice(0, 10), 'الفترة': AP_PERIOD_LABEL },
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

            var barEl = document.getElementById('apTimeline');
            if (barEl) {
                var values = AP_TIMELINE.values;

                new Chart(barEl, {
                    type: 'bar',
                    data: {
                        labels: AP_TIMELINE.labels,
                        datasets: [{ data: values, backgroundColor: theme.brand, borderRadius: 6, maxBarThickness: 48 }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: { label: function (c) { return ' ' + agWhole(c.raw) + ' موعد'; } } }
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

            var donutEl = document.getElementById('apDonut');
            if (donutEl) {
                var donut = new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: AP_STATUS.map(function (s) { return s.label; }),
                        datasets: [{
                            data: AP_STATUS.map(function (s) { return s.value; }),
                            backgroundColor: AP_STATUS.map(function (s) { return s.color; }),
                            borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '66%',
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true, callbacks: { label: function (c) { var s = AP_STATUS[c.dataIndex]; return ' ' + agWhole(s.value) + ' (' + s.pct + '%)'; } } }
                        }
                    }
                });

                donut.canvas.style.cursor = 'pointer';
                donut.canvas.addEventListener('click', function (evt) {
                    var hit = donut.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                    if (hit.length) applyStatusFilter(String(AP_STATUS[hit[0].index].key));
                });
            }
        }
    </script>
@endsection
