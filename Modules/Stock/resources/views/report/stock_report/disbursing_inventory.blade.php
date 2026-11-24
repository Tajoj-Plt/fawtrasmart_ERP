@extends('stock::master')

@section('title', 'تقرير صرف المخزون')

@section('content')
    @php
        /*
            تنبيه: هذه الشاشة غير موصولة بأي طريق في `Modules/Stock/routes/web.php`،
            وطريقاها `StorHouseReport.disbursingInventory` و`…Ajax` غير مسجَّلين —
            فلا يمكن الوصول إليها اليوم. أُبقيت وأُعيد تصميمها كما هي، ويُقرأ
            اسما الطريقين بحذر حتى لا تسقط الصفحة إن فُتحت قبل تسجيلهما.
        */
        $formAction = Route::has('StorHouseReport.disbursingInventory')
            ? route('StorHouseReport.disbursingInventory')
            : url()->current();

        $ajaxUrl = Route::has('StorHouseReport.disbursingInventoryAjax')
            ? route('StorHouseReport.disbursingInventoryAjax')
            : null;
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'صرف المخزون',
            'icon' => 'log-out',
            'sub' => 'عمليات الصرف بكمياتها وقيمها عبر الفترة والمستودعات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'صرف المخزون' => null,
            ],
            'actions' => '<button type="button" id="exportExcel" class="btn btn-outline-secondary">'
                . '<i class="feather icon-download"></i> إكسل</button>'
                . '<button type="button" id="printReport" class="btn btn-outline-secondary">'
                . '<i class="feather icon-printer"></i> طباعة</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @unless ($ajaxUrl)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                هذا التقرير غير مسجَّل في ملف الطرق بعد، فلا تُحمَّل بياناته.
            </div>
        @endunless

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-filter"></i> بحث وفلترة</h4>
            </div>

            <div class="card-body">
                <form id="disbursingForm" method="GET" action="{{ $formAction }}" class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="product">المنتج</label>
                            <select id="product" name="product" class="form-control select2">
                                <option value="">كل المنتجات</option>
                                @foreach ($products as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="category">التصنيف</label>
                            <select id="category" name="category" class="form-control select2">
                                <option value="">كل التصنيفات</option>
                                @foreach ($categories as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
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
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="الاسم أو الكود…">
                        </div>
                    </div>

                    {{-- الفترة المحاسبية: اختيارها يملأ تاريخَي التقرير --}}
                    @include('partials.accounting-period-filter', [
                        'fromName' => 'start_date',
                        'toName' => 'end_date',
                        'colClass' => 'col-md-3 col-sm-6',
                    ])

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="start_date">من تاريخ</label>
                            <input type="date" id="start_date" name="start_date" class="form-control">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="end_date">إلى تاريخ</label>
                            <input type="date" id="end_date" name="end_date" class="form-control">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="sort_by">ترتيب حسب</label>
                            <select id="sort_by" name="sort_by" class="form-control">
                                <option value="">افتراضي</option>
                                @foreach ([
                                    'date_desc' => 'التاريخ (الأحدث أولاً)',
                                    'date_asc' => 'التاريخ (الأقدم أولاً)',
                                    'quantity_desc' => 'الكمية تنازلياً',
                                    'quantity_asc' => 'الكمية تصاعدياً',
                                    'value_desc' => 'القيمة تنازلياً',
                                    'value_asc' => 'القيمة تصاعدياً',
                                ] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
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
                                    'product' => 'المنتج',
                                    'category' => 'التصنيف',
                                    'warehouse' => 'المستودع',
                                    'date' => 'التاريخ',
                                ] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label class="stk-switch">
                            <input type="checkbox" id="hide-zero" name="hide_zero" value="1">
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">إخفاء الكميات الصفرية</span></span>
                        </label>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label class="stk-switch">
                            <input type="checkbox" id="show-values" name="show_values" value="1" checked>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">إظهار القيم المالية</span></span>
                        </label>
                    </div>
                </form>
            </div>

            <div class="stk-form-foot">
                <button type="button" class="btn btn-primary" id="filterBtn">
                    <i class="feather icon-search"></i> بحث
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
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__value stk-num text-danger" id="totalDisbursed">0</span>
                <span class="stk-metric__label">الكمية المُصرفة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num" id="totalValue">0</span>
                <span class="stk-metric__label">قيمة المصروف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clipboard"></i></span>
                <span class="stk-metric__value stk-num" id="totalTransactions">0</span>
                <span class="stk-metric__label">عدد العمليات</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-trending-down"></i> منحنى الصرف</h4>
            </div>
            <div class="card-body">
                <div style="position:relative; height:340px">
                    <canvas id="disbursingChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card stk-table-card" id="reportContainer">
            <div class="stk-print-head">
                <h3 id="printTitle">تقرير صرف المخزون</h3>
                <p>طُبع في {{ now()->format('Y-m-d H:i') }}</p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title" id="reportTitle">
                    <i class="feather icon-log-out"></i> عمليات الصرف
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th style="width:10%">التاريخ</th>
                            <th style="width:9%">كود المنتج</th>
                            <th>اسم المنتج</th>
                            <th style="width:12%">التصنيف</th>
                            <th style="width:12%">المستودع</th>
                            <th class="text-center" style="width:9%">الكمية</th>
                            <th class="text-center" style="width:10%">سعر الوحدة</th>
                            <th class="text-center" style="width:11%">القيمة</th>
                            <th style="width:12%">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody id="disbursingTableBody">
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">جارٍ تحميل البيانات…</td>
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
                table: '#reportTable',
                fileName: 'تقرير_صرف_المخزون',
                title: 'تقرير صرف المخزون',
                numericColumns: [0, 6, 7, 8],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 3,
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        /*
            أُزيل إعادة تحميل jQuery 3.6 من CDN فوق نسخة النظام،
            و«تحريك» الأرقام الذي كان يعلَق في مؤقّتٍ لا ينتهي مع القيم العشرية.
        */
        (function () {
            const ajaxUrl = @json($ajaxUrl);

            let disbursingChart;

            const formatNumber = value => (parseFloat(value) || 0)
                .toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

            const formatCurrency = value => (window.AppCurrency && window.AppCurrency.format)
                ? window.AppCurrency.format(parseFloat(value) || 0)
                : (parseFloat(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

            function message(text, tone) {
                return '<tr><td colspan="10" class="text-center py-4 ' + (tone || 'text-muted') + '">'
                    + escapeHtml(text) + '</td></tr>';
            }

            function renderChart(chartData) {
                const canvas = document.getElementById('disbursingChart');
                if (! canvas || typeof Chart === 'undefined' || ! chartData) return;

                if (disbursingChart) disbursingChart.destroy();

                disbursingChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [
                            {
                                label: 'الكمية المُصرفة',
                                data: chartData.quantities || [],
                                backgroundColor: 'rgba(220,53,69,.1)',
                                borderColor: 'rgba(220,53,69,1)',
                                borderWidth: 2, fill: true, tension: .4,
                            },
                            {
                                label: 'القيمة المُصرفة',
                                data: chartData.values || [],
                                backgroundColor: 'rgba(255,193,7,.1)',
                                borderColor: 'rgba(255,193,7,1)',
                                borderWidth: 2, fill: true, tension: .4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true } },
                    },
                });
            }

            function renderTable(rows) {
                if (! rows || ! rows.length) {
                    $('#disbursingTableBody').html(message('لا عمليات صرف مطابقة للفلاتر'));
                    return;
                }

                const showValues = $('#show-values').is(':checked');

                let html = '';
                let totalQuantity = 0;
                let totalValue = 0;

                rows.forEach((item, index) => {
                    const quantity = parseFloat(item.quantity) || 0;
                    const value = parseFloat(item.total_value) || 0;

                    totalQuantity += quantity;
                    totalValue += value;

                    html += '<tr>'
                        + '<td class="stk-num text-muted">' + (index + 1) + '</td>'
                        + '<td class="stk-num">' + escapeHtml(String(item.date || '—').slice(0, 10)) + '</td>'
                        + '<td class="stk-num">' + escapeHtml(item.product_code || '—') + '</td>'
                        + '<td><span class="stk-media__title">' + escapeHtml(item.product_name || '—') + '</span></td>'
                        + '<td>' + escapeHtml(item.category || '—') + '</td>'
                        + '<td>' + escapeHtml(item.warehouse || '—') + '</td>'
                        + '<td class="text-center stk-num"><span class="stk-move--danger">' + formatNumber(quantity) + '</span></td>'
                        + '<td class="text-center stk-num">' + (showValues ? formatCurrency(item.unit_price) : '—') + '</td>'
                        + '<td class="text-center stk-num">' + (showValues ? formatCurrency(value) : '—') + '</td>'
                        + '<td>' + escapeHtml(item.notes || '—') + '</td>'
                        + '</tr>';
                });

                html += '<tr class="stk-total-row">'
                    + '<td colspan="6">المجموع الكلي</td>'
                    + '<td class="text-center stk-num">' + formatNumber(totalQuantity) + '</td>'
                    + '<td></td>'
                    + '<td class="text-center stk-num">' + (showValues ? formatCurrency(totalValue) : '—') + '</td>'
                    + '<td></td>'
                    + '</tr>';

                $('#disbursingTableBody').html(html);
            }

            function loadData() {
                if (! ajaxUrl) {
                    $('#disbursingTableBody').html(message('طريق البيانات غير مسجَّل', 'text-warning'));
                    return;
                }

                $.get(ajaxUrl, $('#disbursingForm').serialize())
                    .done(function (data) {
                        const totals = data.totals || {};

                        $('#totalProducts').text(formatNumber(totals.total_products || 0));
                        $('#totalDisbursed').text(formatNumber(totals.total_disbursed || 0));
                        $('#totalValue').text(formatCurrency(totals.total_value || 0));
                        $('#totalTransactions').text(formatNumber(totals.total_transactions || 0));

                        const title = 'تقرير صرف المخزون'
                            + (data.group_by ? ' — تجميع حسب ' + data.group_by : '');

                        $('#reportTitle').html('<i class="feather icon-log-out"></i> ' + escapeHtml(title));
                        $('#printTitle').text(title);

                        renderChart(data.chart_data);
                        renderTable(data.disbursing);
                    })
                    .fail(function () {
                        $('#disbursingTableBody').html(message('تعذّر تحميل البيانات', 'text-danger'));
                    });
            }

            $(function () {
                if ($.fn.select2) {
                    $('#disbursingForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
                }

                $('#filterBtn').on('click', loadData);

                $('#resetBtn').on('click', function () {
                    document.getElementById('disbursingForm').reset();
                    $('#disbursingForm .select2').val(null).trigger('change');
                    loadData();
                });

                let debounce;
                $('#disbursingForm').on('change keyup', 'select, input', function () {
                    clearTimeout(debounce);
                    debounce = setTimeout(loadData, 400);
                });

                loadData();
            });
        })();
    </script>
@endsection
