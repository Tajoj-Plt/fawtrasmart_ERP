@extends('stock::master')

@section('title', 'ورقة الجرد')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'ورقة الجرد',
            'icon' => 'clipboard',
            'sub' => 'المنتجات وكمياتها في النظام مقابل الكمية الفعليّة للجرد الميداني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'ورقة الجرد' => null,
            ],
            'actions' => '<button type="button" id="exportExcel" class="btn btn-outline-secondary">'
                . '<i class="feather icon-download"></i> إكسل</button>'
                . '<button type="button" id="printReport" class="btn btn-outline-secondary">'
                . '<i class="feather icon-printer"></i> طباعة</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-filter"></i> بحث وفلترة</h4>
            </div>

            <div class="card-body">
                <form id="inventoryForm" method="GET" action="{{ route('StorHouseReport.inventorySheet') }}" class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="category">التصنيف</label>
                            <select id="category" name="category" class="form-control select2">
                                <option value="">كل التصنيفات</option>
                                @foreach ($categories as $item)
                                    <option value="{{ $item->id }}" {{ request('category') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="warehouse">المستودع</label>
                            <select id="warehouse" name="warehouse" class="form-control select2">
                                <option value="">كل المستودعات</option>
                                @foreach ($warehouses as $item)
                                    <option value="{{ $item->id }}" {{ request('warehouse') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="sort_by">ترتيب حسب</label>
                            <select id="sort_by" name="sort_by" class="form-control">
                                <option value="">افتراضي</option>
                                @foreach ([
                                    'quantity_asc' => 'الكمية تصاعدياً',
                                    'quantity_desc' => 'الكمية تنازلياً',
                                    'name_asc' => 'الاسم أبجدياً',
                                    'name_desc' => 'الاسم عكس أبجدياً',
                                    'code_asc' => 'الكود تصاعدياً',
                                    'code_desc' => 'الكود تنازلياً',
                                ] as $value => $label)
                                    <option value="{{ $value }}" {{ request('sort_by') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="group_by">تجميع حسب</label>
                            <select id="group_by" name="group_by" class="form-control">
                                <option value="">بلا تجميع</option>
                                @foreach ([
                                    'category' => 'التصنيف',
                                    'brand' => 'العلامة التجارية',
                                    'warehouse' => 'المستودع',
                                ] as $value => $label)
                                    <option value="{{ $value }}" {{ request('group_by') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="الاسم أو الكود…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label class="stk-switch">
                            <input type="checkbox" id="hide-zero" name="hide_zero" value="1">
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">إخفاء الرصيد الصفري</span></span>
                        </label>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label class="stk-switch">
                            <input type="checkbox" id="show-totals" name="show_totals" value="1" checked>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">إظهار الإجماليات</span></span>
                        </label>
                    </div>
                </form>
            </div>

            <div class="stk-form-foot">
                <button type="button" class="btn btn-primary" id="filterBtn">
                    <i class="feather icon-search"></i> تصفية
                </button>
                <button type="button" class="btn btn-outline-secondary" id="resetBtn">
                    <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                </button>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num" id="totalProducts">0</span>
                <span class="stk-metric__label">إجمالي المنتجات</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__value stk-num" id="totalQuantity">0</span>
                <span class="stk-metric__label">إجمالي الكمية</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__value stk-num" id="totalCategories">0</span>
                <span class="stk-metric__label">عدد التصنيفات</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                <span class="stk-metric__value stk-num" id="totalWarehouses">0</span>
                <span class="stk-metric__label">عدد المستودعات</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> المخزون حسب المستودع</h4>
            </div>
            <div class="card-body">
                <div style="position:relative; height:340px">
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card stk-table-card" id="reportContainer">
            <div class="stk-print-head">
                <h3 id="printTitle">ورقة الجرد</h3>
                <p>طُبع في {{ now()->format('Y-m-d H:i') }}</p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title" id="reportTitle">
                    <i class="feather icon-clipboard"></i> ورقة الجرد
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="inventoryTable">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>اسم المنتج</th>
                            <th style="width:20%">التصنيف</th>
                            <th class="text-center" style="width:15%">العدد في النظام</th>
                            <th class="text-center" style="width:15%">الكمية الفعليّة</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">جارٍ تحميل البيانات…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('stock::components.report-tools')

    <script>
        $(function () {
            stkReportTools({
                table: '#inventoryTable',
                fileName: 'ورقة_الجرد',
                title: 'ورقة الجرد',
                numericColumns: [0, 3, 4],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 1,
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        /*
            كانت الصفحة تُعيد تحميل jQuery 3.6 من CDN فوق نسخة النظام،
            فتفقد كل الإضافات المسجَّلة عليها (Select2 وغيرها). وكانت رسائل
            الجدول تستعمل colspan=11 في جدولٍ من خمسة أعمدة.
        */
        (function () {
            let inventoryChart;

            const formatNumber = value => (parseFloat(value) || 0)
                .toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

            function message(text, tone) {
                return '<tr><td colspan="5" class="text-center py-4 ' + (tone || 'text-muted') + '">'
                    + escapeHtml(text) + '</td></tr>';
            }

            function productRow(product, index) {
                const quantity = parseFloat(product.quantity_in_system) || 0;
                const tone = quantity > 0 ? 'stk-move--success' : 'stk-move--danger';

                return '<tr>'
                    + '<td class="stk-num text-muted">' + index + '</td>'
                    + '<td><span class="stk-media__title">' + escapeHtml(product.name) + '</span></td>'
                    + '<td>' + escapeHtml(product.category || '—') + '</td>'
                    + '<td class="text-center stk-num"><span class="' + tone + '">' + formatNumber(quantity) + '</span></td>'
                    + '<td class="text-center stk-num">' + formatNumber(product.quantity_actual || 0) + '</td>'
                    + '</tr>';
            }

            function renderTable(products) {
                if (! products || ! products.length) {
                    $('#inventoryTableBody').html(message('لا منتجات مطابقة للفلاتر المحدَّدة'));
                    return;
                }

                let html = '';
                let index = 1;
                let systemTotal = 0;
                let actualTotal = 0;

                products.forEach(entry => {
                    if (entry.group_name && Array.isArray(entry.items)) {
                        html += '<tr class="stk-total-row"><td colspan="5">'
                            + escapeHtml(entry.group_name)
                            + ' <span class="stk-count">' + (entry.total_products || entry.items.length) + '</span>'
                            + '</td></tr>';

                        entry.items.forEach(item => {
                            html += productRow(item, index++);
                            systemTotal += parseFloat(item.quantity_in_system) || 0;
                            actualTotal += parseFloat(item.quantity_actual) || 0;
                        });

                        return;
                    }

                    html += productRow(entry, index++);
                    systemTotal += parseFloat(entry.quantity_in_system) || 0;
                    actualTotal += parseFloat(entry.quantity_actual) || 0;
                });

                if ($('#show-totals').is(':checked')) {
                    html += '<tr class="stk-total-row">'
                        + '<td colspan="3">المجموع الكلي</td>'
                        + '<td class="text-center stk-num">' + formatNumber(systemTotal) + '</td>'
                        + '<td class="text-center stk-num">' + formatNumber(actualTotal) + '</td>'
                        + '</tr>';
                }

                $('#inventoryTableBody').html(html);
            }

            function renderChart(chartData) {
                const canvas = document.getElementById('inventoryChart');
                if (! canvas || typeof Chart === 'undefined') return;

                if (inventoryChart) inventoryChart.destroy();

                inventoryChart = new Chart(canvas, {
                    type: 'bar',
                    data: { labels: chartData.labels || [], datasets: chartData.datasets || [] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true, title: { display: true, text: 'الكمية' } } },
                    },
                });
            }

            function loadData() {
                const params = new URLSearchParams(new FormData(document.getElementById('inventoryForm')));

                $('#inventoryTableBody').html(message('جارٍ تحميل البيانات…'));

                fetch(@json(route('StorHouseReport.inventorySheetAjax')) + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(response => response.json())
                    .then(data => {
                        if (! data || data.success !== true) {
                            $('#inventoryTableBody').html(message(data.message || 'تعذّر تحميل البيانات', 'text-danger'));
                            return;
                        }

                        const totals = data.totals || {};

                        $('#totalProducts').text(formatNumber(totals.total_products || 0));
                        $('#totalQuantity').text(formatNumber(totals.total_quantity || 0));
                        $('#totalCategories').text(formatNumber(totals.total_categories || 0));
                        $('#totalWarehouses').text(formatNumber(totals.total_warehouses || 0));

                        const title = 'ورقة الجرد' + (data.group_by ? ' — تجميع حسب ' + data.group_by : '');
                        $('#reportTitle').html('<i class="feather icon-clipboard"></i> ' + escapeHtml(title));
                        $('#printTitle').text(title);

                        if (data.chart_data) renderChart(data.chart_data);

                        renderTable(data.products || []);
                    })
                    .catch(() => $('#inventoryTableBody').html(message('تعذّر الاتصال بالخادم', 'text-danger')));
            }

            $(function () {
                if ($.fn.select2) {
                    $('#inventoryForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
                }

                $('#filterBtn').on('click', loadData);

                $('#resetBtn').on('click', function () {
                    document.getElementById('inventoryForm').reset();
                    $('#inventoryForm .select2').val(null).trigger('change');
                    loadData();
                });

                let debounce;
                $('#inventoryForm').on('change keyup', 'select, input', function () {
                    clearTimeout(debounce);
                    debounce = setTimeout(loadData, 400);
                });

                loadData();
            });
        })();
    </script>
@endsection
