@extends('stock::master')

@section('title', 'ملخّص عمليات المخزون')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'ملخّص عمليات المخزون',
            'icon' => 'book-open',
            'sub' => 'الوارد والمنصرف مفصَّلين حسب مصدر الحركة لكل مستودع — الحركات المعتمدة وحدها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'ملخّص عمليات المخزون' => null,
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
                <span class="stk-state stk-state--neutral">الحركات المعتمدة فقط</span>
            </div>

            <div class="card-body">
                <form id="filterForm" method="GET" class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="keywords">بحث</label>
                            <input type="search" id="keywords" name="keywords" class="form-control"
                                   placeholder="اسم المنتج…" value="{{ request('keywords') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="storehouse_id">المستودع</label>
                            <select id="storehouse_id" name="storehouse_id" class="form-control select2">
                                <option value="">كل المستودعات</option>
                                @foreach ($storehouses as $item)
                                    <option value="{{ $item->id }}" {{ request('storehouse_id') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="category_id">التصنيف</label>
                            <select id="category_id" name="category_id" class="form-control select2">
                                <option value="">كل التصنيفات</option>
                                @foreach ($categories as $item)
                                    <option value="{{ $item->id }}" {{ request('category_id') == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                                <a href="{{ route('StorHouseReport.summaryInventory') }}" class="btn btn-outline-secondary">
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
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num" id="stat-products-count">{{ $totals['products_count'] ?? 0 }}</span>
                <span class="stk-metric__label">إجمالي الأصناف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__value stk-num text-success" id="stat-incoming">{{ number_format($totals['incoming'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">إجمالي الوارد</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__value stk-num text-danger" id="stat-outgoing">{{ number_format($totals['outgoing'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">إجمالي المنصرف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart-2"></i></span>
                <span class="stk-metric__value stk-num" id="stat-balance">{{ number_format($totals['balance'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">الرصيد المتاح</span>
            </div>
        </div>

        <div id="report-container">
            @forelse ($reportData as $storehouseId => $data)
                <div class="card stk-table-card storehouse-section" data-storehouse-name="{{ $data['storehouse']->name }}">
                    <div class="stk-print-head">
                        <h3>ملخّص عمليات المخزون — {{ $data['storehouse']->name }}</h3>
                        <p>طُبع في {{ now()->format('Y-m-d H:i') }}</p>
                    </div>

                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-home"></i> {{ $data['storehouse']->name }}
                            <span class="stk-count">{{ count($data['products']) }}</span>
                        </h6>
                        <span class="stk-balance stk-num">{{ number_format($data['totals']['balance'], 2) }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="table-{{ $storehouseId }}">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:22%">اسم الصنف</th>
                                    <th colspan="5" class="text-center">الوارد</th>
                                    <th colspan="5" class="text-center">المنصرف</th>
                                    <th rowspan="2" class="text-center" style="width:11%">الرصيد النهائي</th>
                                </tr>
                                <tr>
                                    <th class="text-center">مشتريات</th>
                                    <th class="text-center">مرتجع مبيعات</th>
                                    <th class="text-center">تحويلات</th>
                                    <th class="text-center">إضافة</th>
                                    <th class="text-center">الإجمالي</th>
                                    <th class="text-center">مبيعات</th>
                                    <th class="text-center">مرتجع مشتريات</th>
                                    <th class="text-center">تحويلات</th>
                                    <th class="text-center">صرف</th>
                                    <th class="text-center">الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['products'] as $product)
                                    <tr>
                                        <td><span class="stk-media__title">{{ $product['name'] }}</span></td>
                                        <td class="text-center stk-num">{{ number_format($product['incoming_purchase'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['incoming_return'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['incoming_transfer'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['incoming_manual'], 2) }}</td>
                                        <td class="text-center stk-num">
                                            <span class="stk-move--success">{{ number_format($product['incoming_total'], 2) }}</span>
                                        </td>
                                        <td class="text-center stk-num">{{ number_format($product['outgoing_sales'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['outgoing_return'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['outgoing_transfer'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['outgoing_manual'], 2) }}</td>
                                        <td class="text-center stk-num">
                                            <span class="stk-move--danger">{{ number_format($product['outgoing_total'], 2) }}</span>
                                        </td>
                                        <td class="text-center stk-num"><strong>{{ number_format($product['balance'], 2) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="stk-total-row">
                                    <td>إجمالي المستودع</td>
                                    <td colspan="4"></td>
                                    <td class="text-center stk-num js-foot-incoming">{{ number_format($data['totals']['incoming'], 2) }}</td>
                                    <td colspan="4"></td>
                                    <td class="text-center stk-num js-foot-outgoing">{{ number_format($data['totals']['outgoing'], 2) }}</td>
                                    <td class="text-center stk-num js-foot-balance">{{ number_format($data['totals']['balance'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @empty
                <div class="card">
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-search"></i></div>
                            <h5 class="stk-empty__title">لا نتائج مطابقة</h5>
                            <p class="stk-empty__hint">غيّر كلمات البحث أو الفلاتر</p>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        /* الطباعة عبر المساعد المخصَّص: جدولٌ مهيّأ للورق ببطاقات معلومات */
        function stkPrintReport() {
            const selector = '.storehouse-section table';
            const table = document.querySelector(selector);

            if (! table) { window.print(); return; }

            const headRows = table.querySelectorAll('thead tr');
            const headers = Array.from(headRows[headRows.length - 1].querySelectorAll('th'))
                .map(cell => cell.textContent.trim());

            printTable({
                tableSelector: selector + ' tbody tr',
                tfootSelector: table.querySelector('tfoot') ? selector + ' tfoot' : null,
                title: 'ملخّص عمليات المخزون',
                infoCards: Array.from(document.querySelectorAll('.stk-metric')).map(card => ({
                    label: (card.querySelector('.stk-metric__label') || {}).textContent || '',
                    value: (card.querySelector('.stk-metric__value') || {}).textContent || '',
                })),
                filterInfo: { 'تاريخ الطباعة': new Date().toISOString().slice(0, 10) },
                tableHeaders: headers,
                descriptionColumn: 0,
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
            الطباعة كانت تفتح نافذةً جديدة بأنماطٍ تستعمل var(--app-primary)
            وأمثالها — وهي غير معرَّفة في مستندٍ فارغ، فتخرج الألوان سوداء.
            صارت الطباعة تعتمد أنماط الطباعة المشتركة في القالب.
        */
        $(function () {
            if ($.fn.select2) {
                $('#filterForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            $('#exportExcel').on('click', exportReport);
        });

        async function exportReport() {
            const config = {
                fileName: 'ملخص_عمليات_المخزون',
                mainTitle: 'ملخّص عمليات المخزون — كل المستودعات',
                subTitle: @json(config('app.name')),
                reportInfo: {
                    'التاريخ': new Date().toISOString().slice(0, 10),
                    'المستودع': $('#storehouse_id option:selected').text(),
                    'التصنيف': $('#category_id option:selected').text(),
                },
                statisticsCards: [
                    { title: 'إجمالي الأصناف', value: $('#stat-products-count').text(), unit: ' صنف', titleColor: 'primary', valueColor: 'primary' },
                    { title: 'إجمالي الوارد', value: $('#stat-incoming').text(), unit: '', titleColor: 'success', valueColor: 'success' },
                    { title: 'إجمالي المنصرف', value: $('#stat-outgoing').text(), unit: '', titleColor: 'danger', valueColor: 'danger' },
                    { title: 'الرصيد المتاح', value: $('#stat-balance').text(), unit: '', titleColor: 'info', valueColor: 'info' },
                ],
                tableTitle: 'تفاصيل العمليات حسب المستودع',
                tableHeaders: [
                    'اسم الصنف', 'مشتريات', 'مرتجع مبيعات', 'تحويلات واردة', 'إضافة يدوية', 'إجمالي الوارد',
                    'مبيعات', 'مرتجع مشتريات', 'تحويلات منصرفة', 'صرف يدوي', 'إجمالي المنصرف', 'الرصيد النهائي',
                ],
                columnWidths: [25, 12, 12, 12, 12, 15, 12, 12, 12, 12, 15, 15],
                tableData: [],
                buttonSelector: '#exportExcel',
            };

            const rowStyles = [
                { alignment: 'right', bold: true },
                null, null, null, null, { color: 'success', bold: true },
                null, null, null, null, { color: 'danger', bold: true },
                { color: 'primary', bold: true },
            ];

            $('.storehouse-section').each(function () {
                const name = $(this).data('storehouse-name');

                config.tableData.push({
                    values: [name, '', '', '', '', '', '', '', '', '', '', ''],
                    cellStyles: Array(12).fill({ background: 'primary', color: 'white', bold: true }),
                    mergeFrom: 1, mergeTo: 12,
                });

                $(this).find('tbody tr').each(function () {
                    config.tableData.push({
                        values: $(this).find('td').map((_, cell) => $(cell).text().trim()).get(),
                        cellStyles: rowStyles,
                    });
                });

                /* الإجماليات تُقرأ بأصنافها لا بترتيب الخلايا — الترتيب يتغيّر مع التصميم */
                config.tableData.push({
                    values: [
                        'إجمالي ' + name, '', '', '', '',
                        $(this).find('.js-foot-incoming').text().trim(),
                        '', '', '', '',
                        $(this).find('.js-foot-outgoing').text().trim(),
                        $(this).find('.js-foot-balance').text().trim(),
                    ],
                    background: 'grayBg',
                    cellStyles: rowStyles,
                });
            });

            await exportToExcelProfessional(config);
        }
    </script>
@endsection
