{{--
    تقرير دليل العملاء — على نمط التصميم المعتمد (أعمار ديون الفواتير):
    ترويسةٌ فاتحة، شبكةُ فلترةٍ واحدة، أربعةُ مؤشّرات، رسمٌ واحد، ثمّ الجدول
    بترقيم صفحات في المتصفّح.

    ما لم يتغيّر:
    • كلُّ حقول الفلترة وأسماؤها ومعرّفاتُها — عدا «تمت الإضافة بواسطة»
      الذي حُذف بطلب المستخدم؛ «مسؤول المبيعات» يغطّي المعنيين.
    • أعمدةُ الجدول الثمانية وبياناتُها؛ زرّا «عرض/تعديل» صارا قائمةَ ثلاث نقاط
      في عمود «الإجراءات» نفسِه.
    • التصديرُ يُبنى من بيانات الخادم (`currentReportData`) والطباعةُ تتخطّى
      عمود الإجراءات — فملفُّ Excel والورقةُ المطبوعة كما كانا.

    الرسمُ ثلاثةُ أعدادٍ متداخلة (عميلٌ واحدٌ قد يكون له هاتفٌ وموقع)، لا
    توزيعٌ لكلٍّ واحد — فبطاقةُ أعمدةٍ واحدة بلا حلقة.
--}}
@extends('client::master')

@section('title')
    تقرير دليل العملاء
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@section('content')
@php
    $guideRows = $reportRows ?? [];
    $guideTotals = $totals ?? [];
    $guideReportData = ['data' => $guideRows, 'totals' => $guideTotals];

    $guideFrom = request('from_date') ?? now()->startOfMonth()->format('Y-m-d');
    $guideTo = request('to_date') ?? now()->format('Y-m-d');
    $guidePeriodLabel = 'من '.$guideFrom.' إلى '.$guideTo;

    $guideCustomers = (int) ($guideTotals['total_customers'] ?? count($guideRows));
    $guideContacts = (int) ($guideTotals['total_contacts'] ?? 0);
    $guideLocations = (int) ($guideTotals['total_locations'] ?? 0);
    $guideNotes = (int) ($guideTotals['total_notes'] ?? 0);
    $guidePct = fn ($n) => $guideCustomers > 0 ? round($n / $guideCustomers * 100) : 0;
    $guideChartSum = $guideContacts + $guideLocations + $guideNotes;

    /* صلاحيتا المسارين كما في وسيط ClientController */
    $canViewClient = Route::has('clients.show') && canAny([
        'clients_view_all_clients', 'clients_view_own_clients',
        'clients_view_by_employee_groups', 'clients_view_by_daily_itinerary',
    ]);
    $canEditClient = Route::has('clients.edit') && hasPermission('clients_edit_data');
@endphp

<div class="rpt">
    {{-- ===================== الترويسة ===================== --}}
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'address-book',
        'headTitle' => 'دليل العملاء',
        'headSubtitle' => 'دليل شامل لكل عميل — بياناته وموقعه وجهة التواصل معه',
        'headPeriod' => $guidePeriodLabel,
        'headExtra' => 'آخر تحديث: '.now()->format('Y-m-d H:i'),
        'headGuideKey' => 'client-reports.guide',
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ request('period') ?? '30' }}">

                {{-- ─────────── الفترات السريعة ─────────── --}}
                @include('client::customers.CustomerReport.partials.report-period-tabs')

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="from_date">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="from_date" class="form-control" aria-label="من تاريخ" value="{{ request('from_date') ?? now()->startOfMonth()->format('Y-m-d') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="to_date" id="to_date" class="form-control" aria-label="إلى تاريخ" value="{{ request('to_date') ?? now()->format('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="rpt-field">
                        @include('reports.partials.client-select', [
                            'name' => 'customer',
                            'id' => 'customer',
                            'selectedClient' => $selectedClient ?? null,
                            'label' => 'العميل',
                        ])
                    </div>

                    {{-- يغطّي «تمت الإضافة بواسطة» أيضاً: المُسنَدون إلى الموظف أو من أضافهم بحسابه --}}
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
                                <option value="{{ $clientStatus->id }}" {{ request('status') == $clientStatus->id ? 'selected' : '' }}>{{ $clientStatus->name }}</option>
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
                            @foreach ($categories ?? [] as $category)
                                <option value="{{ $category->id }}" {{ request('category_client') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="location_status">حالة الموقع</label>
                        <select name="location_status" id="location_status" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            <option value="with_location" {{ request('location_status') == 'with_location' ? 'selected' : '' }}>عملاء لديهم موقع</option>
                            <option value="without_location" {{ request('location_status') == 'without_location' ? 'selected' : '' }}>عملاء بدون موقع</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="activity_type">نوع النشاط</label>
                        <select name="activity_type" id="activity_type" class="form-control rpt-select2">
                            <option value="">الكل</option>
                            <option value="contact" {{ request('activity_type') == 'contact' ? 'selected' : '' }}>جهة اتصال</option>
                            <option value="location" {{ request('activity_type') == 'location' ? 'selected' : '' }}>موقع</option>
                            <option value="notes" {{ request('activity_type') == 'notes' ? 'selected' : '' }}>ملاحظات</option>
                        </select>
                    </div>

                    <div class="rpt-field">
                        <label for="sort">الترتيب</label>
                        <select name="sort" id="sort" class="form-control rpt-select2">
                            <option value="name" {{ request('sort') == 'name' ? 'selected' : '' }}>حسب الاسم</option>
                            <option value="code" {{ request('sort') == 'code' ? 'selected' : '' }}>حسب الكود</option>
                            <option value="recent" {{ request('sort') == 'recent' ? 'selected' : '' }}>الأحدث</option>
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
                <div class="ag-kpi__label">إجمالي العملاء</div>
                <div class="ag-kpi__value"><bdi id="totalCustomers">{{ $totals['total_customers'] ?? count($reportRows ?? []) }}</bdi></div>
                <div class="ag-kpi__sub">عميل</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#10b981">
            <div>
                <div class="ag-kpi__label">جهات اتصال</div>
                <div class="ag-kpi__value"><bdi id="totalContacts">{{ $totals['total_contacts'] ?? 0 }}</bdi></div>
                <div class="ag-kpi__sub">عميل له هاتف أو جوّال · {{ $guidePct($guideContacts) }}%</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-phone"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#f97316">
            <div>
                <div class="ag-kpi__label">مواقع جغرافية</div>
                <div class="ag-kpi__value"><bdi id="totalLocations">{{ $totals['total_locations'] ?? 0 }}</bdi></div>
                <div class="ag-kpi__sub">عميل له موقع مسجَّل · {{ $guidePct($guideLocations) }}%</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-map-marker-alt"></i></span>
        </div>
        <div class="ag-kpi" style="--c:#06b6d4">
            <div>
                <div class="ag-kpi__label">ملاحظات</div>
                <div class="ag-kpi__value"><bdi id="totalNotes">{{ $totals['total_notes'] ?? 0 }}</bdi></div>
                <div class="ag-kpi__sub">ملاحظة</div>
            </div>
            <span class="ag-kpi__icon"><i class="fas fa-sticky-note"></i></span>
        </div>
    </div>

    {{-- ===================== الرسم ===================== --}}
    <div class="ag-charts ag-charts--single">
        <div class="rpt-card" id="chartCard">
            <div class="rpt-card__head">
                <h4><i class="fas fa-chart-bar"></i> اكتمال بيانات العملاء</h4>
            </div>
            <div class="rpt-card__body">
                @if ($guideChartSum > 0)
                    <div class="ag-canvas"><canvas id="guideChart" aria-label="عدد العملاء حسب اكتمال بياناتهم" role="img"></canvas></div>
                @else
                    <div class="rpt-empty"><i class="fas fa-chart-bar"></i> لا بيانات لعرضها</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> تفاصيل دليل العملاء
                <span class="rpt-pill rpt-pill-secondary">{{ number_format(count($guideRows)) }} عميل</span>
            </h4>
            <div class="ag-tools no-print">
                @include('client::customers.CustomerReport.partials.report-page-size')
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>
        <div class="rpt-scroll table-responsive">
            <table class="rpt-table ag-table" id="guideTable">
                <thead>
                    <tr>
                        <th>#</th><th>كود العميل</th><th>اسم العميل</th><th>التصنيف</th>
                        <th>الموقع</th><th>جهة الاتصال</th><th>الحالة</th><th>الإجراءات</th>
                    </tr>
                </thead>
                {{-- الجدولُ يُبنى في الخادم لا في JavaScript بعد التحميل --}}
                <tbody id="reportTableBody">
                    @forelse ($reportRows as $i => $item)
                        <tr data-row="1">
                            <td>{{ $i + 1 }}</td>
                            <td><span class="rpt-pill rpt-pill-secondary">{{ $item['code'] ?? '-' }}</span></td>
                            <td class="rpt-name"><strong>@include('reports.partials.client-link', ['id' => $item['id'] ?? null, 'name' => $item['trade_name'] ?: ($item['first_name'] ?? '')])</strong></td>
                            <td>{{ $item['category_name'] ?? '-' }}</td>
                            <td>
                                @if (! empty($item['latitude']) && ! empty($item['longitude']))
                                    <button type="button" class="btn btn-sm btn-info show-map-btn"
                                        data-lat="{{ $item['latitude'] }}" data-lng="{{ $item['longitude'] }}"
                                        data-name="{{ $item['trade_name'] ?: ($item['first_name'] ?? 'العميل') }}"
                                        data-address="{{ ($item['city'] ?? '').' '.($item['address'] ?? '') }}">
                                        <i class="fa fa-map-marker-alt"></i> عرض الموقع
                                    </button>
                                @elseif (! empty($item['city']))
                                    <span class="rpt-pill rpt-pill-secondary">{{ $item['city'] }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $item['phone'] ?: ($item['email'] ?? '-') }}</td>
                            <td>
                                <span class="rpt-pill" style="background-color: {{ $item['status_color'] ?? '#6c757d' }}; color:#fff;">
                                    {{ $item['status_name'] ?? 'غير محدد' }}
                                </span>
                            </td>
                            <td>
                                @if ($canViewClient || $canEditClient)
                                    <div class="dropdown">
                                        <button type="button" class="ag-actions-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات" title="إجراءات">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if ($canViewClient)
                                                <a class="dropdown-item" href="{{ route('clients.show', $item['id']) }}" target="_blank" rel="noopener"><i class="fas fa-eye me-2 text-primary"></i> ملف العميل</a>
                                            @endif
                                            @if ($canEditClient)
                                                <a class="dropdown-item" href="{{ route('clients.edit', $item['id']) }}" target="_blank" rel="noopener"><i class="fas fa-edit me-2 text-warning"></i> تعديل العميل</a>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="rpt-empty"><i class="fas fa-inbox"></i> لا توجد بيانات — حدِّد معايير البحث واضغط «عرض التقرير»</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('client::customers.CustomerReport.partials.report-pager', [
            'pagerUnit' => 'عميل',
            'pagerStorage' => 'agGuidePageSize',
            'pagerScroll' => '#reportCard',
        ])
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>

{{-- Map Modal --}}
<div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="mapModalLabel"><i class="fas fa-map-marker-alt me-2"></i><span id="mapCustomerName">موقع العميل</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="customerMap" style="height: 450px; width: 100%;"></div>
                <div class="p-3 border-top">
                    <div class="row">
                        <div class="col-md-6"><small class="text-muted d-block">خط العرض:</small><strong id="mapLatitude">-</strong></div>
                        <div class="col-md-6"><small class="text-muted d-block">خط الطول:</small><strong id="mapLongitude">-</strong></div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12"><small class="text-muted d-block">العنوان:</small><strong id="mapAddress">-</strong></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="openInGoogleMaps" target="_blank" class="btn btn-success"><i class="fas fa-external-link-alt me-1"></i> فتح في خرائط Google</a>
                <a href="#" id="getDirections" target="_blank" class="btn btn-info"><i class="fas fa-directions me-1"></i> الاتجاهات</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

@include('sales::partials.table-menu')
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('client::customers.CustomerReport.partials.report-charts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        let guideChart;
        /* بياناتُ التقرير من الخادم مباشرةً — يقرأ منها التصديرُ والطباعة
           والرسمُ، بلا طلبٍ ثانٍ بعد التحميل. */
        const currentReportData = @json($guideReportData);

        $(document).ready(function() {
            if ($.fn.select2) {
                $('.rpt-select2').select2({
                    dir: 'rtl',
                    language: { noResults: function() { return 'لم يتم العثور على نتائج'; } },
                    allowClear: true,
                    width: '100%'
                });
            }

            /* شجرةُ «تابع لـ» هي الجزئيةُ المشتركة `reports.partials.affiliation-tree`
               — نفسُها في تقارير العملاء والمبيعات، تُرسل `tree_node` معرّفاً مجرّداً. */

            $('#exportExcel').click(function() { exportCustomerGuideToExcel(); });
            $('#printBtn').click(function() { printCustomerGuide(); });
            $('.period-btn').click(function() { changePeriod($(this).data('period')); });

            updateChart({
                contacts: currentReportData.totals.total_contacts || 0,
                locations: currentReportData.totals.total_locations || 0,
                notes: currentReportData.totals.total_notes || 0
            });

            /* أيُّ تغييرٍ في الفلاتر يُرسل النموذج — إعادةُ تحميلٍ واحدة
               تُحدّث الجدولَ والمؤشّرات والرسمَ معاً. */
            $('#customer, #sales_manager, #status, #registration_stage, #customer_target_id, #category_client, #location_status, #activity_type, #sort').change(function() {
                $('#filterForm').submit();
            });
        });

        function changePeriod(period) {
            $('#periodInput').val(period);
            $('.period-btn').removeClass('is-active');
            $(`.period-btn[data-period="${period}"]`).addClass('is-active');

            if (period !== 'custom') {
                const today = new Date();
                const startDate = new Date(today);
                startDate.setDate(startDate.getDate() - parseInt(period));
                $('#from_date').val(formatDateForInput(startDate));
                $('#to_date').val(formatDateForInput(today));
            }
            $('#filterForm').submit();
        }

        /* كان الرسمُ يُستدعى بثلاثة أعداد ويقرأ `labels`/`customers` غير
           الموجودَين، فيظهر فارغاً. يُبنى الآن من الأعداد الثلاثة نفسِها. */
        function updateChart(chartData) {
            const ctx = document.getElementById('guideChart');
            if (!ctx || typeof Chart === 'undefined') return;

            if (guideChart) { guideChart.destroy(); }

            const theme = agChartTheme();
            const customers = parseFloat(currentReportData.totals.total_customers || 0);
            const items = [
                { label: 'جهات اتصال', value: parseFloat(chartData.contacts || 0), color: '#10b981' },
                { label: 'مواقع جغرافية', value: parseFloat(chartData.locations || 0), color: '#f97316' },
                { label: 'ملاحظات', value: parseFloat(chartData.notes || 0), color: '#06b6d4' }
            ];
            const values = items.map(function(item) { return item.value; });

            guideChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: items.map(function(item) { return item.label; }),
                    datasets: [{
                        label: 'عدد العملاء',
                        data: values,
                        backgroundColor: items.map(function(item) { return item.color; }),
                        borderRadius: 6,
                        maxBarThickness: 72
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    layout: { padding: { top: 22 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: function(context) {
                                    const pct = customers > 0 ? Math.round(context.raw / customers * 100) : 0;
                                    return ' ' + agWhole(context.raw) + ' (' + pct + '% من العملاء)';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { reverse: true, grid: { display: false }, ticks: { color: theme.muted, font: { size: 11 }, maxRotation: 0, autoSkip: false } },
                        y: { beginAtZero: true, position: 'right', grid: { color: theme.line }, border: { display: false },
                             ticks: { color: theme.muted, precision: 0, callback: function(v) { return agWhole(v); } } }
                    }
                },
                plugins: [agValueLabels(values)]
            });
        }

        function formatDateDisplay(dateStr) { return new Date(dateStr).toLocaleDateString('ar-SA'); }
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function resetFilters() {
            $('#customer').val(null).trigger('change');
            $('#tree_node').val('');
            $('#exact_node').prop('checked', false);
            if (window.__rptInitAffiliationTree) { window.__rptInitAffiliationTree(); }
            $('#sales_manager').val(null).trigger('change');
            $('#registration_stage').val(null).trigger('change');
            $('#customer_target_id').val(null).trigger('change');
            $('#status').val(null).trigger('change');
            $('#category_client').val(null).trigger('change');
            $('#location_status').val('').trigger('change');
            $('#activity_type').val('').trigger('change');
            $('#sort').val('name').trigger('change');
            $('#periodInput').val('30');

            $('.period-btn').removeClass('is-active');
            $('.period-btn[data-period="30"]').addClass('is-active');

            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            $('#from_date').val(formatDateForInput(firstDay));
            $('#to_date').val(formatDateForInput(today));

            $('#filterForm').submit();
        }

        function exportCustomerGuideToExcel() {
            if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'لا توجد بيانات لتصديرها' });
                return;
            }

            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();
            // «تابع لـ» بدل قائمة الفرع المحذوفة — مسارُ ما اختير كما يعرضه الحقل
            const branchName = ($('#affiliationTrail').text() || '').replace('تابع لـ: ', '') || 'الكل';

            const tableData = [];
            currentReportData.data.forEach((item, index) => {
                tableData.push({
                    values: [
                        index + 1, item.code || '-', item.trade_name || item.first_name || '-',
                        item.category_name || '-',
                        item.city || (item.latitude ? (item.latitude.toFixed(4) + ', ' + item.longitude.toFixed(4)) : '-'),
                        item.phone || item.email || '-', item.status_name || '-'
                    ],
                    alignments: ['center', 'center', 'right', 'center', 'right', 'center', 'center'],
                    cellStyles: [
                        null, { color: 'primary' }, { bold: true }, null, null, null,
                        { color: item.status_color ? 'custom' : null, customColor: item.status_color }
                    ]
                });
            });

            const config = {
                fileName: 'تقرير_دليل_العملاء',
                mainTitle: 'تقرير دليل العملاء',
                subTitle: 'نظام إدارة العملاء',
                reportInfo: { 'الفترة': `من ${fromDate} إلى ${toDate}`, 'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'), 'الفرع': branchName },
                statisticsCards: [
                    { title: 'إجمالي العملاء', value: currentReportData.totals.total_customers || 0, unit: ' عميل', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'primaryLight', borderColor: 'primary' },
                    { title: 'جهات الاتصال', value: currentReportData.totals.total_contacts || 0, unit: ' جهة', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
                    { title: 'المواقع الجغرافية', value: currentReportData.totals.total_locations || 0, unit: ' موقع', titleColor: 'info', valueColor: 'info', backgroundColor: 'infoLight', borderColor: 'info' },
                    { title: 'الملاحظات', value: currentReportData.totals.total_notes || 0, unit: ' ملاحظة', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' }
                ],
                tableTitle: 'تفاصيل دليل العملاء',
                tableHeaders: ['#', 'الكود', 'العميل', 'التصنيف', 'الموقع', 'الاتصال', 'الحالة'],
                columnWidths: [6, 12, 35, 15, 20, 20, 15],
                tableData: tableData,
                buttonSelector: '#exportExcel'
            };

            exportToExcelProfessional(config);
        }

        function printCustomerGuide() {
            if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
                Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'لا توجد بيانات لطباعتها' });
                return;
            }

            printTable({
                tableSelector: '#reportTableBody tr',
                title: 'تقرير دليل العملاء',
                infoCards: [
                    { label: 'إجمالي العملاء', value: (currentReportData.totals.total_customers || 0) + ' عميل' },
                    { label: 'جهات الاتصال', value: (currentReportData.totals.total_contacts || 0), class: 'text-success' },
                    { label: 'المواقع الجغرافية', value: (currentReportData.totals.total_locations || 0), class: 'text-info' },
                    { label: 'الملاحظات', value: (currentReportData.totals.total_notes || 0), class: 'text-warning' }
                ],
                filterInfo: { 'الفترة': `من ${$('#from_date').val()} إلى ${$('#to_date').val()}`, 'تابع لـ': ($('#affiliationTrail').text() || '').replace('تابع لـ: ', '') || 'الكل' },
                tableHeaders: ['#', 'الكود', 'العميل', 'التصنيف', 'الموقع', 'الاتصال', 'الحالة'],
                columnWidths: ['5%', '10%', '30%', '15%', '15%', '15%', '10%'],
                skipColumns: [7],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; width: 400px; max-width: 90vw;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('body').append(alertHtml);
            setTimeout(() => { $('.alert').fadeOut(() => $('.alert').remove()); }, 4000);
        }

        // ====================== خريطة الموقع ======================
        let customerMap = null;
        let customerMarker = null;

        $(document).on('click', '.show-map-btn', function() {
            const lat = parseFloat($(this).data('lat'));
            const lng = parseFloat($(this).data('lng'));
            const name = $(this).data('name');
            const address = $(this).data('address');

            if (!lat || !lng || isNaN(lat) || isNaN(lng)) {
                showAlert('إحداثيات الموقع غير صالحة', 'warning');
                return;
            }

            $('#mapCustomerName').text('موقع: ' + name);
            $('#mapLatitude').text(lat.toFixed(6));
            $('#mapLongitude').text(lng.toFixed(6));
            $('#mapAddress').text(address || 'غير محدد');

            $('#openInGoogleMaps').attr('href', `https://www.google.com/maps?q=${lat},${lng}`);
            $('#getDirections').attr('href', `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`);

            $('#mapModal').modal('show');

            setTimeout(function() { initOrUpdateMap(lat, lng, name); }, 300);
        });

        function initOrUpdateMap(lat, lng, name) {
            if (customerMap) {
                customerMap.setView([lat, lng], 15);
                if (customerMarker) {
                    customerMarker.setLatLng([lat, lng]);
                    customerMarker.setPopupContent('<strong>' + name + '</strong>');
                }
            } else {
                customerMap = L.map('customerMap').setView([lat, lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(customerMap);
                customerMarker = L.marker([lat, lng]).addTo(customerMap).bindPopup('<strong>' + name + '</strong>').openPopup();
            }
            customerMap.invalidateSize();
        }

        $('#mapModal').on('shown.bs.modal', function() {
            if (customerMap) { customerMap.invalidateSize(); }
        });
    </script>
@endsection
