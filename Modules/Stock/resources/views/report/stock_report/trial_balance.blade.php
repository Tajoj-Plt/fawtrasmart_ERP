@extends('stock::master')

@section('title', 'ميزان المراجعة المخزني')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'ميزان المراجعة المخزني',
            'icon' => 'check-square',
            'sub' => 'الرصيد الابتدائي والوارد والمنصرف والرصيد النهائي كمّيةً وقيمة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'ميزان المراجعة' => null,
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
                <form id="trialBalanceForm" method="GET" action="{{ route('StorHouseReport.trialBalance') }}" class="row">
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
                            <label for="date_type">الفترة الزمنية</label>
                            <select id="date_type" name="date_type" class="form-control">
                                @foreach ([
                                    'custom' => 'مخصّصة',
                                    'today' => 'اليوم',
                                    'yesterday' => 'أمس',
                                    'this_week' => 'هذا الأسبوع',
                                    'last_week' => 'الأسبوع الماضي',
                                    'this_month' => 'هذا الشهر',
                                    'last_month' => 'الشهر الماضي',
                                    'this_year' => 'هذا العام',
                                    'last_year' => 'العام الماضي',
                                ] as $value => $label)
                                    <option value="{{ $value }}" {{ $value === 'this_month' ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
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
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="end_date">إلى تاريخ</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="sort_by">ترتيب حسب</label>
                            <select id="sort_by" name="sort_by" class="form-control">
                                <option value="">افتراضي</option>
                                @foreach ([
                                    'name_asc' => 'الاسم أبجدياً',
                                    'name_desc' => 'الاسم عكس أبجدياً',
                                    'quantity_desc' => 'الكمية النهائية تنازلياً',
                                    'quantity_asc' => 'الكمية النهائية تصاعدياً',
                                    'value_desc' => 'القيمة النهائية تنازلياً',
                                    'value_asc' => 'القيمة النهائية تصاعدياً',
                                    'movement_desc' => 'حجم الحركة تنازلياً',
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
                                    'category' => 'التصنيف',
                                    'warehouse' => 'المستودع',
                                    'movement_type' => 'نوع الحركة',
                                ] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="search">بحث</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="الاسم أو الكود…">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label class="stk-switch">
                            <input type="checkbox" id="hide-zero-balance" name="hide_zero_balance" value="1">
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">إخفاء الأرصدة الصفرية</span></span>
                        </label>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label class="stk-switch">
                            <input type="checkbox" id="show-movements-only" name="show_movements_only" value="1">
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">المنتجات ذات الحركة فقط</span></span>
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
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__value stk-num" id="totalQuantity">0</span>
                <span class="stk-metric__label">الكمية النهائية</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num" id="totalValue">0</span>
                <span class="stk-metric__label">القيمة النهائية</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__value stk-num" id="totalMovements">0</span>
                <span class="stk-metric__label">إجمالي الحركات</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> ميزان المخزون</h4>
            </div>
            <div class="card-body">
                <div style="position:relative; height:340px">
                    <canvas id="trialBalanceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card stk-table-card" id="reportContainer">
            <div class="stk-print-head">
                <h3 id="printTitle">ميزان المراجعة المخزني</h3>
                <p>طُبع في {{ now()->format('Y-m-d H:i') }}</p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title" id="reportTitle">
                    <i class="feather icon-check-square"></i> ميزان المراجعة المخزني
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead>
                        <tr>
                            <th rowspan="2">المنتج</th>
                            <th rowspan="2" style="width:8%">الكود</th>
                            <th rowspan="2" style="width:10%">التصنيف</th>
                            <th rowspan="2" style="width:10%">المستودع</th>
                            <th colspan="2" class="text-center">الرصيد الابتدائي</th>
                            <th colspan="2" class="text-center">الوارد</th>
                            <th colspan="2" class="text-center">المنصرف</th>
                            <th colspan="2" class="text-center">الرصيد النهائي</th>
                        </tr>
                        <tr>
                            <th class="text-center">كمية</th>
                            <th class="text-center">قيمة</th>
                            <th class="text-center">كمية</th>
                            <th class="text-center">قيمة</th>
                            <th class="text-center">كمية</th>
                            <th class="text-center">قيمة</th>
                            <th class="text-center">كمية</th>
                            <th class="text-center">قيمة</th>
                        </tr>
                    </thead>
                    <tbody id="trialBalanceTableBody">
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">جارٍ تحميل البيانات…</td>
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
                fileName: 'ميزان_المراجعة_المخزني',
                title: 'ميزان المراجعة المخزني',
                numericColumns: [4, 5, 6, 7, 8, 9, 10, 11],
                exportButton: '#exportExcel',
                printButton: '#printReport',
                descriptionColumn: 0,
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        /*
            ثلاثة أعطال أُصلحت هنا:
            • jQuery 3.6 كانت تُحمَّل من CDN فوق نسخة النظام فتُفقد إضافاتها.
            • حقلا التاريخ كانا يُعطَّلان (disabled) مع الفترات الجاهزة —
              والحقل المعطَّل لا يُرسَل مع النموذج، فتصل الفترة فارغةً للخادم.
              صارا للقراءة فقط (readonly) فيُرسلان.
            • «تحريك» الأرقام كان يزيد واحداً في كل نبضة حتى يساوي الهدف؛
              مع قيمةٍ عشرية لا يتساويان أبداً فيبقى المؤقّت دائراً، ومع فرقٍ
              صفري يصير الفاصل Infinity فلا يظهر الرقم أصلاً.
        */
        (function () {
            let trialBalanceChart;

            const formatNumber = value => (parseFloat(value) || 0)
                .toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

            const formatCurrency = value => (window.AppCurrency && window.AppCurrency.format)
                ? window.AppCurrency.format(parseFloat(value) || 0)
                : (parseFloat(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

            function applyDateType() {
                const type = $('#date_type').val();
                const today = new Date();
                const iso = date => date.toISOString().slice(0, 10);

                $('#start_date, #end_date').prop('readonly', type !== 'custom');

                if (type === 'custom') return;

                let from = new Date(today.getFullYear(), today.getMonth(), 1);
                let to = today;

                switch (type) {
                    case 'today':
                        from = today;
                        break;
                    case 'yesterday':
                        from = to = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                        break;
                    case 'this_week':
                        from = new Date(today.getFullYear(), today.getMonth(), today.getDate() - today.getDay());
                        break;
                    case 'last_week':
                        to = new Date(today.getFullYear(), today.getMonth(), today.getDate() - today.getDay() - 1);
                        from = new Date(to.getFullYear(), to.getMonth(), to.getDate() - 6);
                        break;
                    case 'last_month':
                        from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        to = new Date(today.getFullYear(), today.getMonth(), 0);
                        break;
                    case 'this_year':
                        from = new Date(today.getFullYear(), 0, 1);
                        break;
                    case 'last_year':
                        from = new Date(today.getFullYear() - 1, 0, 1);
                        to = new Date(today.getFullYear() - 1, 11, 31);
                        break;
                }

                $('#start_date').val(iso(from));
                $('#end_date').val(iso(to));
            }

            function renderChart(chartData) {
                const canvas = document.getElementById('trialBalanceChart');
                if (! canvas || typeof Chart === 'undefined' || ! chartData) return;

                if (trialBalanceChart) trialBalanceChart.destroy();

                trialBalanceChart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [
                            { label: 'الرصيد الابتدائي', data: chartData.initial_values || [], backgroundColor: 'rgba(40,167,69,.7)' },
                            { label: 'الوارد', data: chartData.incoming_values || [], backgroundColor: 'rgba(0,123,255,.7)' },
                            { label: 'المنصرف', data: chartData.outgoing_values || [], backgroundColor: 'rgba(220,53,69,.7)' },
                            { label: 'الرصيد النهائي', data: chartData.final_values || [], backgroundColor: 'rgba(108,117,125,.7)' },
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

            function renderTable(products) {
                if (! products || ! products.length) {
                    $('#trialBalanceTableBody').html(
                        '<tr><td colspan="12" class="text-center text-muted py-4">لا منتجات مطابقة للفلاتر المحدَّدة</td></tr>'
                    );
                    return;
                }

                const totals = {
                    initialQuantity: 0, initialAmount: 0,
                    incomingQuantity: 0, incomingAmount: 0,
                    outgoingQuantity: 0, outgoingAmount: 0,
                    netQuantity: 0, netAmount: 0,
                };

                let html = '';

                products.forEach(product => {
                    const value = key => parseFloat(product[key]) || 0;

                    totals.initialQuantity += value('initial_quantity');
                    totals.initialAmount += value('initial_amount');
                    totals.incomingQuantity += value('incoming_quantity');
                    totals.incomingAmount += value('incoming_amount');
                    totals.outgoingQuantity += value('outgoing_quantity');
                    totals.outgoingAmount += value('outgoing_amount');
                    totals.netQuantity += value('net_quantity');
                    totals.netAmount += value('net_amount');

                    html += '<tr>'
                        + '<td><span class="stk-media__title">' + escapeHtml(product.name) + '</span></td>'
                        + '<td class="stk-num">' + escapeHtml(product.code || product.id) + '</td>'
                        + '<td>' + escapeHtml(product.category || '—') + '</td>'
                        + '<td>' + escapeHtml(product.warehouse || '—') + '</td>'
                        + '<td class="text-center stk-num">' + formatNumber(value('initial_quantity')) + '</td>'
                        + '<td class="text-center stk-num">' + formatCurrency(value('initial_amount')) + '</td>'
                        + '<td class="text-center stk-num"><span class="stk-move--success">' + formatNumber(value('incoming_quantity')) + '</span></td>'
                        + '<td class="text-center stk-num">' + formatCurrency(value('incoming_amount')) + '</td>'
                        + '<td class="text-center stk-num"><span class="stk-move--danger">' + formatNumber(value('outgoing_quantity')) + '</span></td>'
                        + '<td class="text-center stk-num">' + formatCurrency(value('outgoing_amount')) + '</td>'
                        + '<td class="text-center stk-num"><strong>' + formatNumber(value('net_quantity')) + '</strong></td>'
                        + '<td class="text-center stk-num"><strong>' + formatCurrency(value('net_amount')) + '</strong></td>'
                        + '</tr>';
                });

                html += '<tr class="stk-total-row">'
                    + '<td colspan="4">المجموع الكلي</td>'
                    + '<td class="text-center stk-num">' + formatNumber(totals.initialQuantity) + '</td>'
                    + '<td class="text-center stk-num">' + formatCurrency(totals.initialAmount) + '</td>'
                    + '<td class="text-center stk-num">' + formatNumber(totals.incomingQuantity) + '</td>'
                    + '<td class="text-center stk-num">' + formatCurrency(totals.incomingAmount) + '</td>'
                    + '<td class="text-center stk-num">' + formatNumber(totals.outgoingQuantity) + '</td>'
                    + '<td class="text-center stk-num">' + formatCurrency(totals.outgoingAmount) + '</td>'
                    + '<td class="text-center stk-num">' + formatNumber(totals.netQuantity) + '</td>'
                    + '<td class="text-center stk-num">' + formatCurrency(totals.netAmount) + '</td>'
                    + '</tr>';

                $('#trialBalanceTableBody').html(html);
            }

            function loadData() {
                $.ajax({
                    url: @json(route('StorHouseReport.trialBalanceAjax')),
                    method: 'GET',
                    data: $('#trialBalanceForm').serialize(),
                })
                    .done(function (data) {
                        const totals = data.totals || {};

                        $('#totalProducts').text(formatNumber(totals.total_products || 0));
                        $('#totalQuantity').text(formatNumber(totals.total_quantity || 0));
                        $('#totalValue').text(formatCurrency(totals.total_value || 0));
                        $('#totalMovements').text(formatNumber(totals.total_movements || 0));

                        const title = 'ميزان المراجعة المخزني'
                            + (data.group_by ? ' — تجميع حسب ' + data.group_by : '');

                        $('#reportTitle').html('<i class="feather icon-check-square"></i> ' + escapeHtml(title));
                        $('#printTitle').text(title);

                        renderChart(data.chart_data);
                        renderTable(data.products);
                    })
                    .fail(function () {
                        $('#trialBalanceTableBody').html(
                            '<tr><td colspan="12" class="text-center text-danger py-4">تعذّر تحميل البيانات</td></tr>'
                        );
                    });
            }

            $(function () {
                if ($.fn.select2) {
                    $('#trialBalanceForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%', allowClear: true });
                }

                $('#date_type').on('change', function () {
                    applyDateType();
                    loadData();
                });

                $('#filterBtn').on('click', loadData);

                $('#resetBtn').on('click', function () {
                    document.getElementById('trialBalanceForm').reset();
                    $('#trialBalanceForm .select2').val(null).trigger('change');
                    applyDateType();
                    loadData();
                });

                let debounce;
                $('#trialBalanceForm').on('change keyup', 'select, input', function () {
                    clearTimeout(debounce);
                    debounce = setTimeout(loadData, 400);
                });

                applyDateType();
                loadData();
            });
        })();
    </script>
@endsection
