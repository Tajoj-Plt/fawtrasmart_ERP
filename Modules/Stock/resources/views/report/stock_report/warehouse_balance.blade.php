@extends('stock::master')

@section('title', 'رصيد المستودع')

@section('content')
    @php
        $totals = $reportData['totals'] ?? [
            'total_warehouses' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'current_balance' => 0,
        ];

        $periods = [1 => 'اليوم', 7 => 'أسبوع', 30 => 'شهر', 90 => '3 أشهر', 365 => 'سنة', 'custom' => 'مخصّصة'];
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'رصيد المستودع',
            'icon' => 'home',
            'sub' => 'كشف حساب المستودعات مالياً مع القيود المحاسبية المرتبطة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'رصيد المستودع' => null,
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
                <h4 class="card-title"><i class="feather icon-filter"></i> خيارات البحث</h4>
                <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
            </div>

            <div class="card-body">
                <form id="filterForm" method="GET" class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="period">الفترة الزمنية</label>
                            <select id="period" name="period" class="form-control">
                                @foreach ($periods as $value => $label)
                                    <option value="{{ $value }}" {{ request('period', 30) == $value ? 'selected' : '' }}>
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

                    <div class="col-md-3 col-sm-6 js-custom-dates" @if (request('period') != 'custom') hidden @endif>
                        <div class="form-group">
                            <label for="start_date">من تاريخ</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="{{ request('start_date', now()->subDays((int) request('period', 30))->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 js-custom-dates" @if (request('period') != 'custom') hidden @endif>
                        <div class="form-group">
                            <label for="end_date">إلى تاريخ</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="{{ request('end_date', now()->format('Y-m-d')) }}">
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
                            <label for="product">المنتج</label>
                            <select id="product" name="product" class="form-control select2">
                                <option value="">كل المنتجات</option>
                                @foreach ($products as $item)
                                    <option value="{{ $item->id }}" {{ request('product') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}@if ($item->serial_number) — {{ $item->serial_number }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="category_id">التصنيف</label>
                            <select id="category_id" name="category" class="form-control select2">
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
                            <label for="search">بحث شامل</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="الوصف أو رقم المرجع…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                                <a href="{{ route('StorHouseReport.warehouseBalance') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-rotate-ccw"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-home"></i></span>
                <span class="stk-metric__value stk-num">{{ $totals['total_warehouses'] ?? 0 }}</span>
                <span class="stk-metric__label">عدد المستودعات</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__value stk-num text-success">{{ number_format($totals['total_in'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">إجمالي المدين</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ number_format($totals['total_out'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">إجمالي الدائن</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__value stk-num">{{ number_format($totals['current_balance'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">الرصيد الحالي</span>
            </div>
        </div>

        <div id="report-container">
            @include('stock::report.stock_report.partials.warehouse_statement_table')
        </div>
    </div>

    <div class="modal fade" id="entryDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">تفاصيل القيد المحاسبي</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body" id="entryDetailsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        /* الطباعة عبر المساعد المخصَّص: جدولٌ مهيّأ للورق ببطاقات معلومات */
        function stkPrintReport() {
            const selector = '.js-warehouse-section table';
            const table = document.querySelector(selector);

            if (! table) { window.print(); return; }

            const headRows = table.querySelectorAll('thead tr');
            const headers = Array.from(headRows[headRows.length - 1].querySelectorAll('th'))
                .map(cell => cell.textContent.trim());

            printTable({
                tableSelector: selector + ' tbody tr',
                tfootSelector: table.querySelector('tfoot') ? selector + ' tfoot' : null,
                title: 'كشف حساب المستودعات',
                infoCards: Array.from(document.querySelectorAll('.stk-metric')).map(card => ({
                    label: (card.querySelector('.stk-metric__label') || {}).textContent || '',
                    value: (card.querySelector('.stk-metric__value') || {}).textContent || '',
                })),
                filterInfo: { 'تاريخ الطباعة': new Date().toISOString().slice(0, 10) },
                tableHeaders: headers,
                descriptionColumn: 2,
                windowWidth: 1400,
                windowHeight: 900,
            });
        }

        $(function () { $(document).on('click', '#printReport', stkPrintReport); });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        /*
            كان تصدير إكسل يمرّ على `.product-section` — وهو صنفٌ لا وجود له
            في هذه الصفحة (جدولها يأتي من جزئيّة المستودعات)، فيخرج الملف
            بلا صفٍّ واحد. صار يمرّ على أقسام المستودعات فعلياً.
            وتاريخ القيد كان يُنسَّق بـ moment وهي غير محمَّلة هنا.
        */
        (function () {
            const entryUrl = id => @json(url('stock/StorHouseReport/warehouse-balance/journal-entry')) + '/' + id + '/details';

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();

            const money = value => (window.AppCurrency && window.AppCurrency.format)
                ? window.AppCurrency.format(parseFloat(value) || 0)
                : (parseFloat(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            function renderEntry(entry) {
                const date = entry.date ? String(entry.date).slice(0, 10) : '—';

                let html = '<dl class="stk-def">'
                    + '<div class="stk-def__row"><dt>التاريخ</dt><dd class="stk-num">' + escapeHtml(date) + '</dd></div>'
                    + '<div class="stk-def__row"><dt>الرقم المرجعي</dt><dd class="stk-num">' + escapeHtml(entry.reference_number || '—') + '</dd></div>'
                    + '<div class="stk-def__row"><dt>الحالة</dt><dd>'
                        + '<span class="stk-state ' + (entry.status ? 'stk-state--on' : 'stk-state--off') + '">'
                        + (entry.status ? 'معتمد' : 'بانتظار الاعتماد') + '</span></dd></div>'
                    + '<div class="stk-def__row"><dt>الوصف</dt><dd>' + escapeHtml(entry.description || '—') + '</dd></div>'
                    + '</dl>'
                    + '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>'
                    + '<th>الحساب</th><th>الوصف</th>'
                    + '<th class="text-center" style="width:18%">مدين</th>'
                    + '<th class="text-center" style="width:18%">دائن</th>'
                    + '</tr></thead><tbody>';

                (entry.details || []).forEach(detail => {
                    html += '<tr>'
                        + '<td>' + escapeHtml(detail.account_name) + ' <span class="stk-media__sub stk-num">' + escapeHtml(detail.account_code) + '</span></td>'
                        + '<td>' + escapeHtml(detail.description || '—') + '</td>'
                        + '<td class="text-center stk-num">' + (detail.debit > 0 ? '<span class="stk-move--success">' + money(detail.debit) + '</span>' : '<span class="stk-move--muted">—</span>') + '</td>'
                        + '<td class="text-center stk-num">' + (detail.credit > 0 ? '<span class="stk-move--danger">' + money(detail.credit) + '</span>' : '<span class="stk-move--muted">—</span>') + '</td>'
                        + '</tr>';
                });

                return html + '</tbody></table></div>';
            }

            $(function () {
                if ($.fn.select2) {
                    $('#filterForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
                }

                $('#period').on('change', function () {
                    $('.js-custom-dates').prop('hidden', this.value !== 'custom');
                });

                $(document).on('click', '.view-entry-btn', function () {
                    const id = $(this).data('entry-id');

                    $('#entryDetailsContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>');
                    $('#entryDetailsModal').modal('show');

                    $.get(entryUrl(id))
                        .done(function (response) {
                            $('#entryDetailsContent').html(
                                response && response.success
                                    ? renderEntry(response.data)
                                    : '<div class="alert alert-danger mb-0">تعذّر جلب بيانات القيد</div>'
                            );
                        })
                        .fail(function () {
                            $('#entryDetailsContent').html('<div class="alert alert-danger mb-0">تعذّر الاتصال بالخادم</div>');
                        });
                });

                $('#exportExcel').on('click', exportReport);
            });

            async function exportReport() {
                const config = {
                    fileName: 'كشف_حساب_المستودعات',
                    mainTitle: 'كشف حساب المستودعات (مالي)',
                    subTitle: @json(config('app.name')),
                    reportInfo: {
                        'التاريخ': new Date().toISOString().slice(0, 10),
                        'الفترة': $('#period option:selected').text(),
                        'المستودع': $('#warehouse option:selected').text(),
                    },
                    statisticsCards: [
                        { title: 'عدد المستودعات', value: @json((string) ($totals['total_warehouses'] ?? 0)), unit: '', titleColor: 'primary', valueColor: 'primary' },
                        { title: 'إجمالي المدين', value: @json(number_format($totals['total_in'] ?? 0, 2)), unit: '', titleColor: 'success', valueColor: 'success' },
                        { title: 'إجمالي الدائن', value: @json(number_format($totals['total_out'] ?? 0, 2)), unit: '', titleColor: 'danger', valueColor: 'danger' },
                        { title: 'الرصيد الحالي', value: @json(number_format($totals['current_balance'] ?? 0, 2)), unit: '', titleColor: 'info', valueColor: 'info' },
                    ],
                    tableTitle: 'تفاصيل الحركات',
                    tableHeaders: ['المستودع', 'التاريخ', 'رقم المرجع', 'الوصف', 'مدين', 'دائن', 'الرصيد الجاري', 'المستخدم'],
                    columnWidths: [25, 20, 20, 30, 15, 15, 15, 20],
                    tableData: [],
                };

                $('.js-warehouse-section').each(function () {
                    const name = $(this).data('warehouse-name');
                    const account = $(this).data('account-name');
                    const label = account ? name + ' (' + account + ')' : name;

                    config.tableData.push({
                        values: [label, '', '', '', '', '', '', ''],
                        cellStyles: Array(8).fill({ background: 'primary', color: 'white', bold: true }),
                        mergeFrom: 1, mergeTo: 8,
                    });

                    $(this).find('tbody tr').each(function () {
                        const cells = $(this).find('td');
                        if (cells.length < 7) return;

                        config.tableData.push({
                            values: [name].concat(cells.map((_, cell) => $(cell).text().trim()).get()),
                            cellStyles: [
                                null, null, null, null,
                                { color: 'success' }, { color: 'danger' },
                                { background: 'grayLight', bold: true }, null,
                            ],
                        });
                    });

                    config.tableData.push({ values: Array(8).fill(''), mergeFrom: 1, mergeTo: 8 });
                });

                await exportToExcelProfessional(config);
            }
        })();
    </script>
@endsection
