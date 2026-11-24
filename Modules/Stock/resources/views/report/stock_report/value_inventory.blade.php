@extends('stock::master')

@section('title', 'تقييم المخزون')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تقييم المخزون',
            'icon' => 'dollar-sign',
            'sub' => 'قيمة التكلفة وقيمة البيع والربح المتوقَّع لكل مستودع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'تقييم المخزون' => null,
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
                <h4 class="card-title"><i class="feather icon-filter"></i> خيارات التقييم</h4>
                <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
            </div>

            <div class="card-body">
                <form id="filterForm" method="GET" class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label for="search">اسم المنتج</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="ابحث باسم المنتج…" value="{{ request('search') }}">
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

                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> تقييم
                                </button>
                                <a href="{{ route('StorHouseReport.valueInventory') }}" class="btn btn-outline-secondary">
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
                <span class="stk-metric__label">عدد الأصناف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num" id="stat-cost">{{ number_format($totals['total_cost_value'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">قيمة التكلفة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__value stk-num" id="stat-sale">{{ number_format($totals['total_sale_value'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">قيمة البيع</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-award"></i></span>
                <span class="stk-metric__value stk-num text-success" id="stat-profit">{{ number_format($totals['total_profit'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">الربح المتوقَّع</span>
            </div>
        </div>

        <div id="report-container">
            @forelse ($reportData as $storehouseId => $data)
                <div class="card stk-table-card storehouse-section" data-storehouse-name="{{ $data['storehouse']->name }}">
                    <div class="stk-print-head">
                        <h3>تقييم المخزون — {{ $data['storehouse']->name }}</h3>
                        <p>طُبع في {{ now()->format('Y-m-d H:i') }}</p>
                    </div>

                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-home"></i> {{ $data['storehouse']->name }}
                            <span class="stk-count">{{ count($data['products']) }}</span>
                        </h6>
                        <span class="stk-balance stk-num">@money($data['totals']['cost_value'])</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="table-{{ $storehouseId }}">
                            <thead>
                                <tr>
                                    <th style="width:26%">اسم الصنف</th>
                                    <th class="text-center" style="width:11%">الكمية</th>
                                    <th class="text-center" style="width:12%">تكلفة الوحدة</th>
                                    <th class="text-center" style="width:12%">سعر البيع</th>
                                    <th class="text-center" style="width:13%">إجمالي التكلفة</th>
                                    <th class="text-center" style="width:13%">إجمالي البيع</th>
                                    <th class="text-center" style="width:13%">الربح المتوقَّع</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data['products'] as $product)
                                    <tr>
                                        <td><span class="stk-media__title">{{ $product['name'] }}</span></td>
                                        <td class="text-center stk-num">{{ number_format($product['current_quantity'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['unit_cost'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['unit_sale'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['total_cost'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($product['total_sale'], 2) }}</td>
                                        <td class="text-center stk-num">
                                            <span class="stk-move--success">{{ number_format($product['profit_value'], 2) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="stk-total-row">
                                    <td>إجمالي المستودع</td>
                                    <td class="text-center stk-num js-foot-quantity">{{ number_format($data['totals']['quantity'], 2) }}</td>
                                    <td colspan="2"></td>
                                    <td class="text-center stk-num js-foot-cost">{{ number_format($data['totals']['cost_value'], 2) }}</td>
                                    <td class="text-center stk-num js-foot-sale">{{ number_format($data['totals']['sale_value'], 2) }}</td>
                                    <td class="text-center stk-num js-foot-profit">{{ number_format($data['totals']['profit'], 2) }}</td>
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
                            <h5 class="stk-empty__title">لا نتائج تقييم مطابقة</h5>
                            <p class="stk-empty__hint">غيّر الفلاتر لعرض تقييم المخزون</p>
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
                title: 'تقييم المخزون',
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
        $(function () {
            if ($.fn.select2) {
                $('#filterForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            $('#exportExcel').on('click', exportReport);
        });

        async function exportReport() {
            const config = {
                fileName: 'تقييم_المخزون',
                mainTitle: 'تقرير تقييم المخزون',
                subTitle: @json(config('app.name')),
                reportInfo: {
                    'التاريخ': new Date().toISOString().slice(0, 10),
                    'المستودع': $('#warehouse option:selected').text(),
                    'التصنيف': $('#category option:selected').text(),
                },
                statisticsCards: [
                    { title: 'عدد الأصناف', value: $('#stat-products-count').text(), unit: ' صنف', titleColor: 'primary', valueColor: 'primary' },
                    { title: 'قيمة التكلفة', value: $('#stat-cost').text(), unit: '', titleColor: 'success', valueColor: 'success' },
                    { title: 'قيمة البيع', value: $('#stat-sale').text(), unit: '', titleColor: 'info', valueColor: 'info' },
                    { title: 'الربح المتوقَّع', value: $('#stat-profit').text(), unit: '', titleColor: 'purple', valueColor: 'purple' },
                ],
                tableTitle: 'تقييم الأصناف حسب المستودع',
                tableHeaders: ['الصنف', 'الكمية', 'تكلفة الوحدة', 'سعر البيع', 'إجمالي التكلفة', 'إجمالي البيع', 'الربح'],
                columnWidths: [30, 12, 15, 15, 18, 18, 18],
                tableData: [],
                buttonSelector: '#exportExcel',
            };

            const rowStyles = [
                { alignment: 'right', bold: true },
                null, null, null,
                { color: 'success', bold: true },
                { color: 'info', bold: true },
                { color: 'primary', bold: true },
            ];

            $('.storehouse-section').each(function () {
                const name = $(this).data('storehouse-name');

                config.tableData.push({
                    values: [name, '', '', '', '', '', ''],
                    cellStyles: Array(7).fill({ background: 'primary', color: 'white', bold: true }),
                    mergeFrom: 1, mergeTo: 7,
                });

                $(this).find('tbody tr').each(function () {
                    config.tableData.push({
                        values: $(this).find('td').map((_, cell) => $(cell).text().trim()).get(),
                        cellStyles: rowStyles,
                    });
                });

                config.tableData.push({
                    values: [
                        'إجمالي ' + name,
                        $(this).find('.js-foot-quantity').text().trim(),
                        '', '',
                        $(this).find('.js-foot-cost').text().trim(),
                        $(this).find('.js-foot-sale').text().trim(),
                        $(this).find('.js-foot-profit').text().trim(),
                    ],
                    background: 'grayBg',
                    cellStyles: rowStyles,
                });
            });

            await exportToExcelProfessional(config);
        }
    </script>
@endsection
