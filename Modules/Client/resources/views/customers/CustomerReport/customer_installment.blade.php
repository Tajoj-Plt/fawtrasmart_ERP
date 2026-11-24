{{--
    تقرير أقساط العملاء — على نمط التصميم المعتمد (أعمار ديون الفواتير):
    ترويسةٌ فاتحة، أزرارُ فتراتٍ سريعة فوق شبكة الفلترة، أربعةُ مؤشّرات،
    رسمُ توزيع الحالات مع جدوله، ثمّ جدولُ الاتفاقيات بترقيم صفحات.

    ما لم يتغيّر:
    • كلُّ حقول الفلترة وأسماؤها وقيمُها — في شبكةٍ واحدة.
    • أعمدةُ الجدول الاثنا عشر وبياناتُها، وتفاصيلُ كلّ اتفاقية في صفٍّ
      مطويٍّ تحتها (`data-parent-row`) يُخفى ويظهر مع صفّها في الترقيم.
    • التصديرُ من بيانات الخادم، والطباعةُ من صفوف الجدول الرئيسية.
--}}
@extends('client::master')

@section('title')
    تقرير أقساط العملاء
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
    <style>
        .rpt .sub-installments-table { font-size: .82rem; }
        .rpt .sub-installments-table thead th { background: var(--rpt-sunken, #f6f5ff); }
        .rpt .cursor-pointer { cursor: pointer; }
        .rpt .ag-table tbody td:nth-child(8) { white-space: nowrap; }
    </style>
@endsection

@section('content')
@php
    $instCurrency = \App\Support\CurrencySettings::name();
    $instFrom = \Illuminate\Support\Carbon::parse(request('date_from') ?: ($periodContext['start'] ?? now()->startOfMonth()));
    $instTo = \Illuminate\Support\Carbon::parse(request('date_to') ?: ($periodContext['end'] ?? now()));
    $instPeriodLabel = 'من '.$instFrom->format('d/m/Y').' إلى '.$instTo->format('d/m/Y');

    /* شرائحُ الرسم من `chart_data` نفسِها التي يرسلها المتحكّم */
    $instSlices = [
        ['key' => 'paid',    'label' => 'مدفوع',              'color' => '#10b981'],
        ['key' => 'pending', 'label' => 'متبقٍّ لم يحلّ بعد', 'color' => '#f97316'],
        ['key' => 'overdue', 'label' => 'متأخر',              'color' => '#ef4444'],
    ];
    $instSliceSum = 0.0;
    foreach ($instSlices as $sIdx => $slice) {
        $instSlices[$sIdx]['value'] = round((float) ($chartData[$slice['key']] ?? 0), 2);
        $instSliceSum += max(0, $instSlices[$sIdx]['value']);
    }
    foreach ($instSlices as $sIdx => $slice) {
        $instSlices[$sIdx]['pct'] = $instSliceSum > 0 ? round(max(0, $slice['value']) / $instSliceSum * 100) : 0;
    }

    $instTotalAmount = (float) ($totals['total_amount'] ?? 0);
    $instOverduePct = $instTotalAmount > 0 ? round((float) ($totals['overdue_amount'] ?? 0) / $instTotalAmount * 100) : 0;
    $instRowsCount = count($reportRows ?? []);
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'calendar-alt',
        'headTitle' => 'تقرير أقساط العملاء',
        'headSubtitle' => 'أقساط فواتير العملاء — المدفوع والمتبقي والمتأخر منها',
        'headPeriod' => $instPeriodLabel,
        'headGuideKey' => 'client-reports.installments',
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ request('period', '30') }}">

                {{-- ─────────── الفترات السريعة ─────────── --}}
                @include('client::customers.CustomerReport.partials.report-period-tabs', [
                    'tabCurrent' => (string) request('period', '30'),
                ])

                <div class="ag-filter-grid">
                    {{-- المدى على تاريخ استحقاق القسط --}}
                    <div class="rpt-field">
                        <label for="date_from">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="date_from" id="date_from" class="form-control" aria-label="من تاريخ" value="{{ request('date_from') ?: \Illuminate\Support\Carbon::parse($periodContext['start'] ?? now()->startOfMonth())->format('Y-m-d') }}">
                            <span class="ag-range__sep">إلى</span>
                            {{-- المدى الذي حُسب به التقرير — لا «أوّل الشهر» الثابت --}}
                            <input type="date" name="date_to" id="date_to" class="form-control" aria-label="إلى تاريخ" value="{{ request('date_to') ?: \Illuminate\Support\Carbon::parse($periodContext['end'] ?? now())->format('Y-m-d') }}">
                        </div>
                    </div>

                    @include('client::customers.CustomerReport.partials.period-selector', [
                        'periodContext' => $periodContext ?? [],
                        'startId' => 'date_from',
                        'endId' => 'date_to',
                        'cols' => '',
                        'yearTitle' => 'السنة المالية',
                        'periodTitle' => 'الفترة المحاسبية',
                    ])

                    <div class="rpt-field">
                        @include('reports.partials.client-select', [
                            'name' => 'customer',
                            'id' => 'customer',
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
                            @foreach ($statuses ?? [] as $clientStatus)
                                <option value="{{ $clientStatus->id }}" @selected((string) request('status') === (string) $clientStatus->id)>{{ $clientStatus->name }}</option>
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
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_client') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="installment_status">حالة القسط</label>
                        <select name="installment_status" id="installment_status" class="form-control rpt-select2">
                            <option value="">جميع الحالات</option>
                            @foreach (['paid' => 'مدفوع', 'pending' => 'قيد الانتظار', 'overdue' => 'متأخر', 'partial' => 'مدفوع جزئياً'] as $stKey => $stLabel)
                                <option value="{{ $stKey }}" @selected(request('installment_status') === $stKey)>{{ $stLabel }}</option>
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
                    <div class="ag-checks">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="show_overdue_only" name="show_overdue_only" value="1" @checked(request()->boolean('show_overdue_only'))>
                            <label class="form-check-label" for="show_overdue_only">المتأخرة فقط</label>
                        </div>
                        {{-- «تجميع بالعميل» أُزيل: المتحكّم يجمّع لكنّ الشاشة لم تعرض التجميعَ قطّ، فكانت خانةً لا تفعل شيئاً --}}
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
                <div class="ag-kpi__label">عدد الاتفاقيات</div>
                <div class="ag-kpi__value"><bdi id="totalInstallments">{{ $totals['total_installments'] ?? 0 }}</bdi></div>
                <div class="ag-kpi__sub">اتفاقية تقسيط غير ملغاة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-calendar-alt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">مبلغ الأقساط</div>
                <div class="ag-kpi__value"><bdi id="totalAmount">{{ number_format($totals['total_amount'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $instCurrency }} · المدفوع منها <bdi>{{ number_format($totals['paid_amount'] ?? 0, 2) }}</bdi> · مقدَّمات مقبوضة <bdi>{{ number_format($totals['down_payment_paid'] ?? 0, 2) }}</bdi></div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-dollar-sign"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">المتبقي من الأقساط</div>
                <div class="ag-kpi__value"><bdi id="pendingAmount">{{ number_format($totals['pending_amount'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $instCurrency }} · منه مستحق في الفترة <bdi>{{ number_format($totals['period_due'] ?? 0, 2) }}</bdi></div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-clock"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#ef4444">
            <div>
                <div class="ag-kpi__label">أقساط متأخرة</div>
                <div class="ag-kpi__value"><bdi id="overdueAmount">{{ number_format($totals['overdue_amount'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $instCurrency }} · فات استحقاقُها ولم تُسدَّد · {{ $instOverduePct }}% من المبلغ</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-exclamation-triangle"></i></span>
        </div>
        {{-- الغراماتُ ذمّةٌ على العميل خارج مبلغ الأقساط — كانت غائبةً عن التقرير كلّه --}}
        <div class="ag-kpi" style="--c:#9f1239">
            <div>
                <div class="ag-kpi__label">غرامات تأخير مستحقة</div>
                <div class="ag-kpi__value"><bdi id="lateFeesAmount">{{ number_format($totals['late_fees_outstanding'] ?? 0, 2) }}</bdi></div>
                <div class="ag-kpi__sub">{{ $instCurrency }} · محمَّلة ولم تُحصَّل — خارج مبلغ الأقساط</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-gavel"></i></span>
        </div>
    </div>

    {{-- ===================== الرسم ===================== --}}
    <div class="ag-charts ag-charts--single">
        <div class="rpt-card" id="chartCard">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-pie"></i> توزيع الحالات</h4>
            </div>
            <div class="rpt-card__body">
                @if ($instSliceSum > 0)
                    <div class="ag-dist">
                        <table class="ag-legend">
                            <tbody>
                                @foreach ($instSlices as $slice)
                                    <tr class="ag-legend__row" style="--c:{{ $slice['color'] }}">
                                        <td><span class="ag-dot"></span>{{ $slice['label'] }}</td>
                                        <td class="ag-legend__amount"><bdi>{{ number_format($slice['value'], 2) }}</bdi></td>
                                        <td class="ag-legend__pct">{{ $slice['pct'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="ag-donut">
                            <canvas id="installmentChart" aria-label="توزيع الحالات" role="img"></canvas>
                            <div class="ag-donut__center">
                                <strong>{{ number_format($instSliceSum, 2) }}</strong>
                                <small>{{ $instCurrency }}</small>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-pie"></i> لا مبالغ أقساط لعرضها</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> تفاصيل الأقساط
                <span class="rpt-pill rpt-pill-secondary">{{ $instRowsCount }} اتفاقية</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="expandAllDetails"><i class="fas fa-plus"></i> توسيع الكل</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="collapseAllDetails"><i class="fas fa-minus"></i> طي الكل</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="installmentsTable">
                <thead>
                    <tr>
                        <th>#</th><th>رقم الفاتورة</th><th>العميل</th><th>الفرع</th><th>الموظف</th>
                        <th>الأقساط</th><th title="مجموع الأقساط — دون الدفعة المقدَّمة">مبلغ الأقساط</th><th title="أقرب قسطٍ لم يُسدَّد">الاستحقاق</th><th>الحالة</th><th>المدفوع</th><th>المتبقي</th><th title="غرامات تأخير محمَّلة ولم تُحصَّل">الغرامات</th><th>التأخير</th>
                    </tr>
                </thead>
                {{--
                    الجدولُ يُبنى في الخادم لا في JavaScript بعد التحميل،
                    وتفاصيلُ كلّ اتفاقية في صفٍّ مطويٍّ تحتها.
                --}}
                <tbody id="reportTableBody">
                    @php
                        $instStatusText = [
                            'paid' => 'مدفوع', 'pending' => 'قيد الانتظار',
                            'overdue' => 'متأخر', 'partial' => 'مدفوع جزئياً',
                        ];
                        $instStatusClass = [
                            'paid' => 'rpt-pill-success', 'pending' => 'rpt-pill-warning',
                            'overdue' => 'rpt-pill-danger', 'partial' => 'rpt-pill-info',
                        ];
                    @endphp
                    @forelse ($reportRows as $i => $inst)
                        @php $collapseId = 'details-'.$inst['id'].'-'.$i; $hasDetails = ! empty($inst['details']); @endphp
                        <tr data-row="1" data-row-key="{{ $inst['id'] }}" @if($hasDetails) class="cursor-pointer" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" @endif>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $inst['invoice_number'] ?? '-' }}</td>
                            <td class="rpt-name">@include('reports.partials.client-link', ['id' => $inst['client_id'] ?? null, 'name' => $inst['client_name'] ?? ''])</td>
                            <td>{{ $inst['branch'] ?? '-' }}</td>
                            <td>{{ $inst['employee'] ?? '-' }}</td>
                            <td>{{ $inst['installment_number'] ?? 1 }}</td>
                            <td class="rpt-num">
                                {{ number_format($inst['amount'] ?? 0, 2) }}
                                @if (($inst['down_payment'] ?? 0) > 0)
                                    <div class="text-muted" style="font-size:11px" title="الدفعة المقدَّمة — تُقبض عند الاتفاقية وليست من الأقساط">مقدَّمة {{ number_format($inst['down_payment'], 2) }} · مقبوض {{ number_format($inst['down_payment_paid'] ?? 0, 2) }}</div>
                                @endif
                            </td>
                            <td class="rpt-num">{{ $inst['due_date'] ?? '-' }}</td>
                            <td>
                                <span class="rpt-pill {{ $instStatusClass[$inst['status']] ?? '' }}">
                                    {{ $instStatusText[$inst['status']] ?? $inst['status'] }}
                                </span>
                            </td>
                            <td class="rpt-num rpt-pos">{{ number_format($inst['paid_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num rpt-warnc">{{ number_format($inst['remaining_amount'] ?? 0, 2) }}</td>
                            <td class="rpt-num {{ ($inst['late_fees_outstanding'] ?? 0) > 0 ? 'rpt-neg' : '' }}">{{ number_format($inst['late_fees_outstanding'] ?? 0, 2) }}</td>
                            <td>{{ ($inst['days_overdue'] ?? 0) > 0 ? $inst['days_overdue'].' يوم' : '-' }}</td>
                        </tr>

                        @if ($hasDetails)
                            <tr class="collapse no-print" id="{{ $collapseId }}" data-parent-row="{{ $inst['id'] }}">
                                <td colspan="13" class="p-0">
                                    <div class="p-2">
                                        <table class="rpt-table sub-installments-table mb-0">
                                            <thead><tr><th>البيان</th><th>المبلغ</th><th>التاريخ</th><th>الحالة</th><th>المدفوع</th><th>المتبقي</th><th>الغرامة</th><th>التأخير</th></tr></thead>
                                            <tbody>
                                                @foreach ($inst['details'] as $d)
                                                    <tr>
                                                        <td>{{ $d['description'] ?? 'قسط فرعي' }}</td>
                                                        <td class="rpt-num">{{ number_format($d['amount'] ?? 0, 2) }}</td>
                                                        <td class="rpt-num">{{ $d['due_date'] ?? '-' }}</td>
                                                        <td>
                                                            <span class="rpt-pill {{ $instStatusClass[$d['status']] ?? '' }}">
                                                                {{ $instStatusText[$d['status']] ?? $d['status'] }}
                                                            </span>
                                                        </td>
                                                        <td class="rpt-num rpt-pos">{{ number_format($d['paid_amount'] ?? 0, 2) }}</td>
                                                        <td class="rpt-num rpt-warnc">{{ number_format($d['remaining_amount'] ?? 0, 2) }}</td>
                                                        <td class="rpt-num">{{ number_format($d['late_fee_amount'] ?? 0, 2) }}</td>
                                                        <td>{{ ($d['days_overdue'] ?? 0) > 0 ? $d['days_overdue'].' يوم' : '-' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="13" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'اتفاقية',
            'pagerStorage' => 'agInstallmentsPageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>

    <script>
        let installmentChart;
        /* بياناتُ التقرير من الخادم مباشرةً — التصديرُ والطباعة يقرآن منها. */
        const currentReportData = @json(['installments' => $reportRows ?? [], 'totals' => $totals ?? []]);
        const instChartData = @json($chartData ?? []);

        $(document).ready(function() {
            $('.rpt-select2').select2({ dir: 'rtl', width: '100%', placeholder: 'اختر...' });

            $('#exportExcel').click(function() { exportCustomerInstallmentsToExcel(); });
            $('#printBtn').click(function() { printCustomerInstallments(); });
            $('.period-btn').click(function() { changePeriod($(this).data('period')); });

            /* التوسيعُ والطيّ على صفوف تفاصيل هذا الجدول وحدَه، لا كلّ `.collapse` في الصفحة */
            $('#expandAllDetails').click(function() { $('#reportTableBody > tr.collapse').addClass('show'); });
            $('#collapseAllDetails').click(function() { $('#reportTableBody > tr.collapse').removeClass('show'); });

            /* الرسمُ آخراً: خطأٌ فيه لا يُعطّل أزرارَ التصدير والطباعة */
            renderChart(instChartData);
        });

        function changePeriod(period) {
            $('#periodInput').val(period);
            $('.period-btn').removeClass('is-active');
            $(`.period-btn[data-period="${period}"]`).addClass('is-active');

            if (period !== 'custom') {
                const today = new Date();
                const startDate = new Date(today);
                startDate.setDate(startDate.getDate() - parseInt(period));
                /* المتحكّمُ يقدّم منتقيَي السنة والفترة على حقلَي التاريخ،
                   فيُفرغان كي يقرأ الاستعلامُ المدى السريعَ المختار. */
                $('#fiscal_year_id, #accounting_period_id').val('');
                if ($.fn.select2) { $('#fiscal_year_id, #accounting_period_id').trigger('change.select2'); }
                $('#date_from').val(startDate.toISOString().split('T')[0]);
                $('#date_to').val(today.toISOString().split('T')[0]);
            }
            $('#filterForm').submit();
        }

        /* الرسمُ من بيانات الخادم نفسِها التي بُني منها الجدول. */
        function renderChart(chartData) {
            const canvas = document.getElementById('installmentChart');
            const values = [chartData.paid || 0, chartData.pending || 0, chartData.overdue || 0];

            if (!canvas || typeof Chart === 'undefined' || !values.some(v => v > 0)) { return; }

            if (installmentChart) installmentChart.destroy();

            const theme = agChartTheme();
            const labels = ['مدفوع', 'متبقٍّ لم يحلّ بعد', 'متأخر'];

            installmentChart = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#10b981', '#f97316', '#ef4444'],
                        borderColor: theme.surface, borderWidth: 3, hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '66%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { rtl: true, callbacks: { label: function(context) { return ' ' + agMoney(context.parsed); } } }
                    }
                }
            });
        }

        function getStatusClass(status) {
            switch (status) {
                case 'paid': return 'status-paid';
                case 'pending': return 'status-pending';
                case 'overdue': return 'status-overdue';
                case 'partial': return 'status-partial';
                default: return 'status-pending';
            }
        }

        function getStatusText(status) {
            switch (status) {
                case 'paid': return 'مدفوع';
                case 'pending': return 'قيد الانتظار';
                case 'overdue': return 'متأخر';
                case 'partial': return 'مدفوع جزئياً';
                default: return 'قيد الانتظار';
            }
        }

        function formatNumber(n) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatDate(d) {
            return d ? new Date(d).toLocaleDateString('en-GB') : '-';
        }

        function resetFilters() {
            $('#filterForm')[0].reset();
            $('.rpt-select2').val(null).trigger('change');
            changePeriod('30');
        }

        function exportCustomerInstallmentsToExcel() {
            if (!currentReportData || !currentReportData.installments) return;

            // exportToExcelProfessional تقرأ tableHeaders/tableData لا columns/rows —
            // كان التصدير يفشل داخل try/catch المساعد نفسه بلا أثر.
            const tableData = currentReportData.installments.map((inst, i) => ({
                // أعمدةُ الجدول نفسُها — كان التصديرُ ينقصه الموظفُ والغراماتُ والتأخير
                values: [
                    i + 1, inst.invoice_number || '-', inst.client_name || '-', inst.branch || '-', inst.employee || '-',
                    inst.installment_number || 1, formatNumber(inst.amount), formatNumber(inst.down_payment || 0), formatDate(inst.due_date),
                    getStatusText(inst.status), formatNumber(inst.paid_amount), formatNumber(inst.remaining_amount),
                    formatNumber(inst.late_fees_outstanding || 0), (inst.days_overdue > 0 ? inst.days_overdue + ' يوم' : '-')
                ]
            }));

            exportToExcelProfessional({
                fileName: 'تقرير_أقساط_العملاء',
                mainTitle: 'تقرير أقساط العملاء',
                subTitle: 'نظام إدارة العملاء',
                reportInfo: { 'تاريخ التقرير': new Date().toLocaleDateString('ar-SA') },
                statisticsCards: [
                    { title: 'مبلغ الأقساط', value: formatNumber(currentReportData.totals.total_amount), borderColor: 'primary' },
                    { title: 'المدفوع', value: formatNumber(currentReportData.totals.paid_amount), borderColor: 'success' },
                    { title: 'المتبقي', value: formatNumber(currentReportData.totals.pending_amount), borderColor: 'warning' },
                    { title: 'أقساط متأخرة', value: formatNumber(currentReportData.totals.overdue_amount), borderColor: 'danger' },
                    { title: 'غرامات تأخير مستحقة', value: formatNumber(currentReportData.totals.late_fees_outstanding || 0), borderColor: 'danger' }
                ],
                tableTitle: 'تفاصيل الأقساط',
                tableHeaders: ['#', 'الفاتورة', 'العميل', 'الفرع', 'الموظف', 'الأقساط', 'مبلغ الأقساط', 'المقدَّمة', 'الاستحقاق', 'الحالة', 'المدفوع', 'المتبقي', 'الغرامات', 'التأخير'],
                columnWidths: [5, 15, 25, 15, 18, 9, 14, 12, 13, 13, 13, 13, 12, 10],
                tableData: tableData,
                buttonSelector: '#exportExcel'
            });
        }

        function printCustomerInstallments() {
            if (!currentReportData || !currentReportData.installments) return;

            // printTable تقرأ tableSelector/tableHeaders من الـ DOM الفعلي — والمحدِّد
            // يستثني صفوف الأقساط الفرعية المطويّة (:not(.collapse)) فلا تختلط
            // بصفوف الجدول الرئيسي ذات الأعمدة الاثني عشر. الترقيمُ يُخفي بالصنف،
            // فتُطبع الصفوفُ كلُّها لا الصفحةُ المعروضة.
            printTable({
                tableSelector: '#reportTableBody > tr:not(.collapse)',
                title: 'تقرير أقساط العملاء',
                infoCards: [
                    { label: 'مبلغ الأقساط', value: formatNumber(currentReportData.totals.total_amount) },
                    { label: 'المدفوع', value: formatNumber(currentReportData.totals.paid_amount) },
                    { label: 'المتبقي', value: formatNumber(currentReportData.totals.pending_amount), class: 'text-danger' },
                    { label: 'غرامات تأخير مستحقة', value: formatNumber(currentReportData.totals.late_fees_outstanding || 0), class: 'text-danger' }
                ],
                tableHeaders: ['#', 'رقم الفاتورة', 'العميل', 'الفرع', 'الموظف', 'الأقساط', 'مبلغ الأقساط', 'الاستحقاق', 'الحالة', 'المدفوع', 'المتبقي', 'الغرامات', 'التأخير'],
                windowWidth: 1200,
                windowHeight: 800
            });
        }
    </script>
@endsection
