{{--
    مدفوعات المشتريات — يومياً وشهرياً — على تصميم تقارير العملاء المعتمد
    (`ag-*`): ترويسةٌ فاتحة، أزرارُ الفترات فوق شبكة فلترةٍ واحدة، مؤشّراتٌ
    بأيقونات، رسمُ إيقاع الصرف، ثمّ جدولٌ واحدٌ بترقيم صفحات.

    التقرير يجيب «متى صُرف؟» — لا «لمن» ولا «من». فالمحور هو الزمن،
    والصفّ فترةٌ لا مورّداً: يوماً بيوم أو شهراً بشهر.

    ما لم يتغيّر: الفلاترُ وأسماؤها وخانةُ «إخفاء الفترات بلا صرف»،
    والفتراتُ من `ReportPeriod` بمفاتيحها، وأزرارُ «يومي/شهري/سنداً سنداً»
    تُرسل `view_type`، وأعمدةُ الجدولين وذيلاهما، والتصديرُ يُبنى في الخادم
    من صفوف التقرير (`partials.excel`) والطباعةُ تطبع الصفحة.
--}}
@extends('purchases::master')

@section('title')
    مدفوعات المشتريات — يومياً وشهرياً
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .ag-view-toggle .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; font-size: .84rem; }
        .rpt .ag-table td.rpt-num { white-space: nowrap; }
        .rpt-row-payment td { background: rgba(40, 199, 111, .05); }
        /* شريط النسبة داخل الصف — يتبع اللون الأساسي المضبوط في «مظهر النظام» */
        .rpt-bar { height: 8px; border-radius: 999px; overflow: hidden; background: var(--rpt-line); margin-bottom: 3px; min-width: 70px; }
        .rpt-bar > span { display: block; height: 100%; border-radius: 999px; background: var(--rpt-ok); min-width: 2px; }
        @media print {
            .rpt-bar { border: 1px solid #999; background: #fff; }
            .rpt-bar > span { background: #666; }
        }
    </style>
@endsection

@section('content')
@php
    use App\Support\CurrencySettings;
    use Modules\Purchases\Support\ReportPeriod;

    $q = request()->except(['page']);
    $isDetails = $viewType === 'details';
    $isMonthly = $unit === 'month';

    $money = fn ($v) => CurrencySettings::number($v);
    $unitLabel = $isMonthly ? 'شهر' : 'يوم';
    $currency = CurrencySettings::name();
    $periodLabel = $period->labelWithDates();

    $selectedSupplier = request('supplier_id') ? optional($suppliers->firstWhere('id', (int) request('supplier_id')))->trade_name : null;
    $selectedEmployee = request('employee_id') ? optional($users->firstWhere('id', (int) request('employee_id')))->name : null;
    $selectedBranch = $selectedNodeLabel ?? null;

    // نسبة الفترة من الإجمالي — شريطٌ داخل الصف يُقرأ أسرع من الرقم وحده
    $share = fn ($v) => $totals['amount'] > 0.009 ? round($v / $totals['amount'] * 100, 1) : 0;
    $peak = max($buckets->max('amount') ?: 0, 0.01);
    $tabPeriods = ReportPeriod::TABS + ['custom' => 'مخصص'];

    /* ─────────── الرسم ─────────── */
    $chartData = [
        'labels' => $buckets->pluck('label')->values()->all(),
        'amounts' => $buckets->pluck('amount')->map(fn ($v) => round((float) $v, 2))->values()->all(),
        'vouchers' => $buckets->pluck('vouchers')->map(fn ($v) => (int) $v)->values()->all(),
    ];

    $guide = [
        'title' => 'ما الذي يقيسه هذا التقرير؟',
        'lead' => 'إيقاعُ الصرف للموردين خلال الفترة — سندات الصرف المرحَّلة على حسابات الموردين مجمَّعةً يوماً بيوم أو شهراً بشهر.',
        'blocks' => [
            'وحدة التجميع' => '«يومي» يجعل الصفَّ يوماً، و«شهري» شهراً، و«سنداً سنداً» يعرض كلَّ سند صرفٍ وحدَه.',
            'الفترات الخالية' => 'تظهر صفراً فلا ينقطع الخطّ ولا يُظنّ يومٌ بلا صرفٍ يوماً غير موجود — وتُخفى بخانة «إخفاء الفترات التي بلا صرف».',
            'المتوسّطات' => 'متوسّط الفترة يُحسب على الفترات التي فيها صرفٌ وحدَها، ومتوسّط السند = الإجمالي ÷ عدد السندات.',
        ],
        'facts' => ['المصدرُ: قيود سندات الصرف المرحَّلة، لا جدول المدفوعات.', 'تاريخان صريحان يتقدّمان على زرّ الفترة.'],
    ];
@endphp

<div class="rpt">

    @include('purchases::purchases.report.partials.print-header', [
        'documentTitle' => 'تقرير مدفوعات المشتريات — '.($isDetails ? 'سنداً سنداً' : ($isMonthly ? 'شهري' : 'يومي')),
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
            'headIcon' => 'calendar-check',
            'headTitle' => 'مدفوعات المشتريات',
            'headSubtitle' => 'إيقاع الصرف للموردين خلال الفترة — سندات الصرف مجمَّعةً '.($isMonthly ? 'شهراً بشهر' : 'يوماً بيوم').' من القيود المرحَّلة.',
            'headPeriod' => $periodLabel,
            'headExtra' => $isDetails ? 'سنداً سنداً' : ($isMonthly ? 'شهري' : 'يومي'),
            'headGuideKey' => 'purchases-reports.payments-timeline',
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
            <form method="GET" action="{{ route('ReportsPurchases.purchasePayments') }}" id="filterForm">
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
                        <label for="employee_id">الموظف الصارف</label>
                        <select name="employee_id" id="employee_id" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('employee_id') === (string) $user->id)>{{ $user->name }}</option>
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
                        <button type="submit" name="view_type" value="daily" class="btn {{ (! $isDetails && ! $isMonthly) ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-calendar-day"></i> يومي
                        </button>
                        <button type="submit" name="view_type" value="monthly" class="btn {{ (! $isDetails && $isMonthly) ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-calendar-alt"></i> شهري
                        </button>
                        <button type="submit" name="view_type" value="details" class="btn {{ $isDetails ? 'btn-primary active' : 'btn-outline-primary' }}">
                            <i class="fas fa-th-list"></i> سنداً سنداً
                        </button>
                    </div>
                    <div class="ag-checks">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="hide_empty" name="hide_empty" value="1" @checked(request()->boolean('hide_empty')) onchange="this.form.submit()">
                            <label class="form-check-label" for="hide_empty">إخفاء الفترات التي بلا صرف</label>
                        </div>
                    </div>
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ route('ReportsPurchases.purchasePayments') }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
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
                <div class="ag-kpi__value"><bdi>{{ $money($totals['amount']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $currency }} · {{ $totals['vouchers'] }} سند صرف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#3b82f6">
            <div>
                <div class="ag-kpi__label">فترات بها صرف</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['active_buckets'] }}</bdi></div>
                <div class="ag-kpi__sub">من {{ $totals['buckets'] }} {{ $unitLabel }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-calendar"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">متوسط الـ{{ $unitLabel }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['average_bucket']) }}</bdi></div>
                <div class="ag-kpi__sub">على الفترات التي بها صرف</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-divide"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#8b5cf6">
            <div>
                <div class="ag-kpi__label">متوسط السند</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['average_voucher']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['vouchers'] }} سند</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-file-invoice-dollar"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">أعلى {{ $unitLabel }}</div>
                <div class="ag-kpi__value"><bdi>{{ $money($totals['peak_amount']) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $totals['peak_label'] }}</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-arrow-up"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#eab308">
            <div>
                <div class="ag-kpi__label">الموردون</div>
                <div class="ag-kpi__value"><bdi>{{ $totals['suppliers'] }}</bdi></div>
                <div class="ag-kpi__sub">صُرف لهم في الفترة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
    </div>

    {{-- ===================== الرسم ===================== --}}
    <div class="ag-charts ag-charts--single no-print">
        <div class="rpt-card">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> إيقاع الصرف {{ $isMonthly ? 'شهرياً' : 'يومياً' }}</h4>
            </div>
            <div class="rpt-card__body">
                @if (count($chartData['labels']))
                    <div class="ag-canvas"><canvas id="timelineChart" aria-label="إيقاع الصرف" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا توجد مدفوعات في {{ $periodLabel }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-{{ $isDetails ? 'th-list' : 'table' }}"></i>
                {{ $isDetails ? 'سندات الصرف' : 'المدفوعات '.($isMonthly ? 'شهراً بشهر' : 'يوماً بيوم') }}
                <span class="rpt-pill rpt-pill-secondary">{{ $isDetails ? $payments->count().' سند' : $buckets->count().' '.$unitLabel }}</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="rptExcelAction"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll">
            @if (! $isDetails)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:22%">{{ $isMonthly ? 'الشهر' : 'اليوم' }}</th>
                            <th>سندات الصرف</th>
                            <th>الموردون</th>
                            <th>الموظفون</th>
                            <th>إجمالي المصروف</th>
                            <th>متوسط السند</th>
                            <th style="width:22%">النسبة من الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($buckets as $row)
                            <tr class="{{ $row['is_empty'] ? '' : 'rpt-row-payment' }}" data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-name">{{ $row['label'] }}</td>
                                <td>
                                    @if ($row['vouchers'])
                                        <span class="rpt-pill rpt-pill-secondary">{{ $row['vouchers'] }}</span>
                                    @else
                                        <span class="rpt-zero">—</span>
                                    @endif
                                </td>
                                <td>{{ $row['suppliers'] ?: '—' }}</td>
                                <td>{{ $row['employees'] ?: '—' }}</td>
                                <td class="rpt-num {{ $row['amount'] > 0.009 ? 'rpt-pos' : 'rpt-zero' }}">
                                    <strong>{{ $row['amount'] > 0.009 ? $money($row['amount']) : '—' }}</strong>
                                </td>
                                <td class="rpt-num rpt-zero">{{ $row['average'] > 0.009 ? $money($row['average']) : '—' }}</td>
                                <td>
                                    {{-- الشريط نسبيٌّ إلى أعلى فترة، فيبرز الفارق بين الفترات --}}
                                    <div class="rpt-bar" title="{{ $share($row['amount']) }}% من الإجمالي">
                                        <span style="width: {{ min(100, round($row['amount'] / $peak * 100)) }}%"></span>
                                    </div>
                                    <small class="rpt-zero">{{ $share($row['amount']) }}%</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد مدفوعات في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($buckets->count())
                        <tfoot>
                            <tr>
                                <td colspan="2">الإجمالي العام</td>
                                <td>{{ $totals['vouchers'] }}</td>
                                <td colspan="2"></td>
                                <td class="rpt-num rpt-pos">{{ $money($totals['amount']) }}</td>
                                <td class="rpt-num">{{ $money($totals['average_voucher']) }}</td>
                                <td>100%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th style="width:100px">التاريخ</th>
                            <th>رقم السند</th>
                            <th>النوع</th>
                            <th>المورد</th>
                            <th>مصدر الصرف</th>
                            <th>الموظف</th>
                            <th>تابع لـ</th>
                            <th>المبلغ</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        @forelse ($payments as $item)
                            <tr class="rpt-row-payment" data-row="1">
                                <td>{{ $loop->iteration }}</td>
                                <td class="rpt-num">{{ $item['date'] }}</td>
                                <td><strong>{{ $item['reference_number'] }}</strong></td>
                                <td><span class="rpt-pill rpt-pill-info">{{ $item['type_name'] }}</span></td>
                                <td class="rpt-name">{{ $item['supplier_name'] }}</td>
                                <td>{{ $item['source'] }}</td>
                                <td>{{ $item['employee_name'] }}</td>
                                <td>{{ $item['branch_name'] }}</td>
                                <td class="rpt-num rpt-pos"><strong>{{ $money($item['amount']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="rpt-empty">
                                <i class="fas fa-inbox"></i> لا توجد سندات صرف في {{ $periodLabel }}
                            </td></tr>
                        @endforelse
                    </tbody>
                    @if ($payments->count())
                        <tfoot>
                            <tr>
                                <td colspan="8">الإجمالي العام — {{ $payments->count() }} سند</td>
                                <td class="rpt-num rpt-pos">{{ $money($payments->sum('amount')) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => $isDetails ? 'سند' : $unitLabel,
            'pagerStorage' => 'agPurchasePaymentsTimelinePageSize',
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
            const TIMELINE = @json($chartData);

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

                const el = document.getElementById('timelineChart');
                if (el && TIMELINE.labels.length) {
                    const many = TIMELINE.labels.length > 12;
                    new Chart(el.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: TIMELINE.labels,
                            datasets: [
                                { type: 'bar', label: 'المصروف', data: TIMELINE.amounts, backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 48, yAxisID: 'y', order: 2 },
                                { type: 'line', label: 'عدد السندات', data: TIMELINE.vouchers, borderColor: theme.brand, backgroundColor: theme.brand, tension: .35, pointRadius: 3, yAxisID: 'y2', order: 1 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: {
                                legend: { display: true, position: 'top', rtl: true, labels: { color: theme.muted, boxWidth: 12 } },
                                tooltip: { rtl: true, callbacks: { label: function (c) {
                                    return ' ' + c.dataset.label + ': ' + (c.dataset.yAxisID === 'y2' ? agWhole(c.parsed.y) : agMoney(c.parsed.y));
                                } } }
                            },
                            scales: {
                                x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: many ? 45 : 0, autoSkip: many } },
                                y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                                     ticks: { color: theme.muted, callback: function (v) { return agWhole(v); } } },
                                y2: { beginAtZero: true, position: 'left', grid: { display: false }, border: { display: false },
                                      ticks: { color: theme.muted, precision: 0 } }
                            }
                        },
                        plugins: many ? [] : [agValueLabels(TIMELINE.amounts)]
                    });
                }
            });
        })();
    </script>

    {{-- التصديرُ يُبنى في الخادم من صفوف التقرير — كلُّ الصفوف لا الصفحةُ المعروضة --}}
    @include('purchases::purchases.report.partials.excel', [
        'fileName' => 'مدفوعات_المشتريات_'.($isMonthly ? 'شهري' : 'يومي'),
        'mainTitle' => 'تقرير مدفوعات المشتريات — '.($isMonthly ? 'شهري' : 'يومي'),
        'info' => [
            'الفترة' => $periodLabel,
            'التجميع' => $isMonthly ? 'شهرياً' : 'يومياً',
            'المورد' => $selectedSupplier ?: 'الكل',
            'الموظف' => $selectedEmployee ?: 'الكل',
            'تابع لـ' => $selectedBranch ?: 'الكل',
        ],
        'cards' => [
            ['title' => 'إجمالي المدفوعات', 'value' => $money($totals['amount']), 'valueColor' => 'success', 'titleColor' => 'gray', 'backgroundColor' => 'successBg', 'borderColor' => 'successLight'],
            ['title' => 'عدد سندات الصرف', 'value' => $totals['vouchers'], 'valueColor' => 'primary', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'متوسط الـ'.$unitLabel, 'value' => $money($totals['average_bucket']), 'valueColor' => 'primaryLight', 'titleColor' => 'gray', 'backgroundColor' => 'grayBg', 'borderColor' => 'border'],
            ['title' => 'أعلى '.$unitLabel, 'value' => $money($totals['peak_amount']), 'valueColor' => 'warning', 'titleColor' => 'gray', 'backgroundColor' => 'warningLight', 'borderColor' => 'warning'],
        ],
        'headers' => [$isMonthly ? 'الشهر' : 'اليوم', 'سندات الصرف', 'الموردون', 'الموظفون', 'إجمالي المصروف', 'متوسط السند', 'النسبة %'],
        'widths' => [26, 14, 12, 12, 18, 16, 12],
        'rows' => $buckets->map(fn ($r) => [
            'values' => [
                $r['label'], $r['vouchers'], $r['suppliers'], $r['employees'],
                $r['amount'], $r['average'], $share($r['amount']),
            ],
            'alignments' => ['right'],
        ])->all(),
        'summaryRows' => $buckets->count() ? [[
            'values' => [
                'الإجمالي العام', $totals['vouchers'], '', '',
                $totals['amount'], $totals['average_voucher'], 100,
            ],
            'background' => 'primary', 'color' => 'white', 'borderColor' => 'primary', 'fontSize' => 12,
        ]] : [],
    ])
@endsection
