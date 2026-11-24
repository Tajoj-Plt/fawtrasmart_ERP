@extends('stock::master')

@section('title', 'ملخّص عمليات المخزون — ' . $storehouse->name)

@section('content')
    @php
        $hasFilters = request()->hasAny([
            'keywords', 'category', 'operation_type',
            'from_date', 'to_date', 'min_quantity', 'max_quantity',
        ]);
    @endphp

    <div class="content-body">

        @section('page-actions')
            {{-- التصدير والطباعة عبر الملفّين المساعدَين المعتمدَين في تقارير الحسابات:
                 إكسل بترويسة وبطاقات إحصائية، وطباعة بجدولٍ مهيّأ للورق --}}
            <button type="button" class="btn btn-outline-secondary" id="printReport">
                <i class="feather icon-printer"></i> طباعة
            </button>

            <button type="button" class="btn btn-outline-secondary" id="exportExcel">
                <i class="feather icon-download"></i> تصدير Excel
            </button>

            @permission('stock.reports.export')
            <a href="{{ route('storehouse.summary_inventory_operations.export', [$storehouse->id, 'pdf']) }}"
               class="btn btn-outline-secondary">
                <i class="feather icon-file-text"></i> PDF
            </a>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'ملخّص عمليات المخزون',
            'icon' => 'activity',
            'sub' => $storehouse->name . ' — الوارد والمنصرف لكل صنف',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المستودعات' => route('storehouse.index'),
                $storehouse->name => route('storehouse.show', $storehouse->id),
                'ملخّص العمليات' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters)
                            <span class="stk-count">فلاتر مفعّلة</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                                data-bs-toggle="collapse" data-target="#advancedSearchForm"
                                data-bs-target="#advancedSearchForm" aria-expanded="false">
                            <i class="feather icon-sliders"></i> بحث متقدّم
                        </button>
                    </div>
                </div>

                <form id="searchForm" method="GET">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">البحث</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                       value="{{ request('keywords') }}" placeholder="اسم الصنف…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="category">التصنيف</label>
                                <select id="category" name="category" class="form-control select2">
                                    <option value="">جميع التصنيفات</option>
                                    @foreach ($categories ?? [] as $category)
                                        <option value="{{ $category->id }}"
                                            {{ request('category') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="operation_type">نوع العملية</label>
                                <select id="operation_type" name="operation_type" class="form-control">
                                    <option value="">جميع العمليات</option>
                                    @foreach (['incoming' => 'الوارد', 'outgoing' => 'المنصرف', 'transfer' => 'التحويل'] as $value => $label)
                                        <option value="{{ $value }}" {{ request('operation_type') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse" id="advancedSearchForm">
                        <div class="row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'col-md-3 col-sm-6',
                            ])

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="from_date">من تاريخ</label>
                                    <input type="date" id="from_date" name="from_date" class="form-control"
                                           value="{{ request('from_date') }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="to_date">إلى تاريخ</label>
                                    <input type="date" id="to_date" name="to_date" class="form-control"
                                           value="{{ request('to_date') }}">
                                </div>
                            </div>

                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label>نطاق الكمية</label>
                                    <div class="input-group">
                                        <input type="number" name="min_quantity" class="form-control stk-num"
                                               placeholder="من" value="{{ request('min_quantity') }}">
                                        <input type="number" name="max_quantity" class="form-control stk-num"
                                               placeholder="إلى" value="{{ request('max_quantity') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <button type="button" id="resetSearch" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول ══════════ --}}
        <div class="card stk-table-card">
            <div class="stk-print-head">
                <h3>ملخّص عمليات المخزون — {{ $storehouse->name }}</h3>
                <p>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> الأصناف
                    <span class="stk-count" id="rows-count">{{ count($products) }}</span>
                </h6>
                <div class="spinner-border spinner-border-sm text-primary d-none" id="loading-indicator" role="status"></div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 stk-grouped" id="reportTable">
                    <thead>
                        <tr>
                            <th rowspan="2">الصنف</th>
                            <th colspan="6" class="text-center stk-group stk-group--in">الوارد</th>
                            <th colspan="5" class="text-center stk-group stk-group--out">المنصرف</th>
                            <th rowspan="2" class="text-center">صافي الحركة</th>
                        </tr>
                        <tr>
                            <th class="text-center">فواتير شراء</th>
                            <th class="text-center">مرتجع مبيعات</th>
                            <th class="text-center">تحويل</th>
                            <th class="text-center">يدوي</th>
                            {{-- الرصيد الافتتاحي كان يسقط من التقرير كلّه --}}
                            <th class="text-center">رصيد افتتاحي</th>
                            <th class="text-center stk-group__sum">الإجمالي</th>

                            <th class="text-center">فواتير بيع</th>
                            <th class="text-center">مرتجع مشتريات</th>
                            <th class="text-center">تحويل</th>
                            <th class="text-center">يدوي</th>
                            <th class="text-center stk-group__sum">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody id="inventory-table-body">
                        @forelse ($products as $product)
                            @php
                                $net = $product['movement_total'] - $product['sold_quantity'];
                            @endphp

                            <tr>
                                <td>
                                    <a href="{{ route('products.show', $product['id']) }}" class="stk-media__title">
                                        {{ $product['name'] }}
                                    </a>
                                </td>

                                <td class="text-center stk-num">0</td>
                                <td class="text-center stk-num">{{ $product['sales_return_quantity'] }}</td>
                                <td class="text-center stk-num">{{ $product['incoming_transfer'] }}</td>
                                <td class="text-center stk-num">{{ $product['incoming_manual'] }}</td>
                                <td class="text-center stk-num">{{ $product['incoming_opening'] ?? 0 }}</td>
                                <td class="text-center stk-num stk-group__sum">{{ $product['incoming_total'] }}</td>

                                <td class="text-center stk-num">{{ $product['sold_quantity'] }}</td>
                                <td class="text-center stk-num">0</td>
                                <td class="text-center stk-num">{{ $product['outgoing_transfer'] ? '-' . $product['outgoing_transfer'] : 0 }}</td>
                                <td class="text-center stk-num">{{ $product['outgoing_manual'] ? '-' . $product['outgoing_manual'] : 0 }}</td>
                                <td class="text-center stk-num stk-group__sum">{{ $product['outgoing_total'] ? '-' . $product['outgoing_total'] : 0 }}</td>

                                {{-- كان الصافي رابطاً بلا وجهة (href="")، فيعيد تحميل الصفحة عند الضغط --}}
                                <td class="text-center">
                                    <span class="stk-move stk-move--{{ $net > 0 ? 'success' : ($net < 0 ? 'danger' : 'muted') }}">
                                        <span class="stk-num">{{ $net }}</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13">
                                    <div class="stk-empty">
                                        <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                        <h5 class="stk-empty__title">لا حركات</h5>
                                        <p class="stk-empty__hint">لا شيء يطابق معايير البحث في هذا المستودع</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        const REPORT_HEADERS = [
            'الصنف',
            'فواتير شراء', 'مرتجع مبيعات', 'تحويل وارد', 'يدوي وارد', 'رصيد افتتاحي', 'إجمالي الوارد',
            'فواتير بيع', 'مرتجع مشتريات', 'تحويل منصرف', 'يدوي منصرف', 'إجمالي المنصرف',
            'صافي الحركة',
        ];

        /** صفوف الجدول كما هي معروضة الآن — بعد الفلترة والبحث */
        function collectRows() {
            const rows = [];

            document.querySelectorAll('#reportTable tbody tr').forEach(function (tr) {
                const cells = tr.querySelectorAll('td');
                if (cells.length < REPORT_HEADERS.length) return;   // صف «لا نتائج»

                rows.push({
                    values: Array.from(cells).map(function (cell, index) {
                        const text = cell.textContent.trim();
                        return index === 0 ? text : (parseFloat(text.replace(/,/g, '')) || 0);
                    }),
                    alignments: ['right'].concat(Array(REPORT_HEADERS.length - 1).fill('center')),
                });
            });

            return rows;
        }

        function reportTotals(rows) {
            const sum = index => rows.reduce((total, row) => total + (parseFloat(row.values[index]) || 0), 0);

            return { incoming: sum(6), outgoing: sum(11), net: sum(12), count: rows.length };
        }

        function exportSummaryOperations() {
            const rows = collectRows();
            const totals = reportTotals(rows);

            exportToExcelProfessional({
                fileName: 'ملخص_عمليات_المخزون_' + @json($storehouse->name),
                mainTitle: 'ملخّص عمليات المخزون',
                subTitle: @json($storehouse->name),

                reportInfo: {
                    'المستودع': @json($storehouse->name),
                    'تاريخ التقرير': new Date().toISOString().slice(0, 10),
                    'عدد الأصناف': String(totals.count),
                },

                statisticsCards: [
                    { title: 'عدد الأصناف', value: String(totals.count), unit: ' صنف', color: 'primary', borderColor: 'primary' },
                    { title: 'إجمالي الوارد', value: formatNumber(totals.incoming), unit: '', color: 'success', borderColor: 'success' },
                    { title: 'إجمالي المنصرف', value: formatNumber(Math.abs(totals.outgoing)), unit: '', color: 'danger', borderColor: 'danger' },
                    { title: 'صافي الحركة', value: formatNumber(totals.net), unit: '', color: totals.net < 0 ? 'danger' : 'primary', borderColor: 'primary' },
                ],

                tableTitle: 'حركة الأصناف',
                tableHeaders: REPORT_HEADERS,
                columnWidths: [28, 12, 13, 12, 12, 13, 14, 12, 14, 13, 13, 15, 14],
                tableData: rows,

                summaryRows: [{
                    label: 'الإجمالي',
                    values: ['', '', '', '', '', formatNumber(totals.incoming), '', '', '', '', formatNumber(totals.outgoing), formatNumber(totals.net)],
                    mergeFrom: 1,
                    mergeTo: 1,
                    color: 'primary',
                    alignment: 'right',
                }],

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح',
                errorMessage: 'تعذّر تصدير التقرير',
            });
        }

        function printSummaryOperations() {
            const totals = reportTotals(collectRows());

            printTable({
                tableSelector: '#reportTable tbody tr',
                title: 'ملخّص عمليات المخزون — ' + @json($storehouse->name),
                infoCards: [
                    { label: 'عدد الأصناف', value: totals.count + ' صنف' },
                    { label: 'إجمالي الوارد', value: formatNumber(totals.incoming), class: 'text-success' },
                    { label: 'إجمالي المنصرف', value: formatNumber(Math.abs(totals.outgoing)), class: 'text-danger' },
                    { label: 'صافي الحركة', value: formatNumber(totals.net) },
                ],
                filterInfo: {
                    'المستودع': @json($storehouse->name),
                    'تاريخ الطباعة': new Date().toISOString().slice(0, 10),
                },
                tableHeaders: REPORT_HEADERS,
                columnWidths: ['14%', '7%', '8%', '7%', '7%', '8%', '8%', '7%', '8%', '7%', '7%', '7%', '7%'],
                descriptionColumn: 0,
                windowWidth: 1400,
                windowHeight: 900,
            });
        }
    </script>

    <script>
        $(function () {
            if ($.fn.select2) {
                $('#searchForm .select2').select2({ placeholder: 'الكل', allowClear: true, dir: 'rtl', width: '100%' });
            }

            $('#exportExcel').on('click', exportSummaryOperations);
            $('#printReport').on('click', printSummaryOperations);

            const escapeHtml = value => $('<div>').text(value == null ? '' : value).html();
            const showUrl = @json(route('products.show', ['id' => '_ID_']));

            function render(rows) {
                const tbody = $('#inventory-table-body').empty();
                $('#rows-count').text(rows.length);

                if (! rows.length) {
                    tbody.append(
                        '<tr><td colspan="13"><div class="stk-empty">'
                        + '<div class="stk-empty__icon"><i class="feather icon-activity"></i></div>'
                        + '<h5 class="stk-empty__title">لا نتائج</h5>'
                        + '<p class="stk-empty__hint">لا شيء يطابق معايير البحث</p>'
                        + '</div></td></tr>'
                    );
                    return;
                }

                rows.forEach(function (row) {
                    const net = row.movement_total - row.sold_quantity;
                    const tone = net > 0 ? 'success' : (net < 0 ? 'danger' : 'muted');
                    const signed = value => (value ? '-' + value : 0);

                    tbody.append(
                        '<tr>'
                        + '<td><a class="stk-media__title" href="' + showUrl.replace('_ID_', row.id) + '">'
                        + escapeHtml(row.name) + '</a></td>'
                        + '<td class="text-center stk-num">0</td>'
                        + '<td class="text-center stk-num">' + row.sales_return_quantity + '</td>'
                        + '<td class="text-center stk-num">' + row.incoming_transfer + '</td>'
                        + '<td class="text-center stk-num">' + row.incoming_manual + '</td>'
                        + '<td class="text-center stk-num">' + (row.incoming_opening || 0) + '</td>'
                        + '<td class="text-center stk-num stk-group__sum">' + row.incoming_total + '</td>'
                        + '<td class="text-center stk-num">' + row.sold_quantity + '</td>'
                        + '<td class="text-center stk-num">0</td>'
                        + '<td class="text-center stk-num">' + signed(row.outgoing_transfer) + '</td>'
                        + '<td class="text-center stk-num">' + signed(row.outgoing_manual) + '</td>'
                        + '<td class="text-center stk-num stk-group__sum">' + signed(row.outgoing_total) + '</td>'
                        + '<td class="text-center"><span class="stk-move stk-move--' + tone + '">'
                        + '<span class="stk-num">' + net + '</span></span></td>'
                        + '</tr>'
                    );
                });
            }

            function search() {
                $('#loading-indicator').removeClass('d-none');

                $.get(
                    '{{ route('storehouse.summary_inventory_operations.search', $storehouse->id) }}',
                    $('#searchForm').serialize()
                )
                    .done(function (response) {
                        if (response.success) render(response.data);
                    })
                    .fail(() => alert('تعذّر تحميل البيانات'))
                    .always(() => $('#loading-indicator').addClass('d-none'));
            }

            $('#searchForm').on('submit', function (e) {
                e.preventDefault();
                search();
            });

            $('#searchForm').on('change input', 'input, select', function () {
                clearTimeout(window.summarySearchTimer);
                window.summarySearchTimer = setTimeout(search, 500);
            });

            $('#resetSearch').on('click', function () {
                $('#searchForm')[0].reset();
                if ($.fn.select2) $('#searchForm .select2').val('').trigger('change');
                search();
            });
        });
    </script>
@endsection
