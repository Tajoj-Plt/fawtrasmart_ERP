@extends('stock::master')

@section('title', 'حركة كل منتج')

@section('content')
    @php
        $totals = $reportData['totals'];
        $groups = $reportData['data'];
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'حركة كل منتج',
            'icon' => 'box',
            'sub' => 'كشف حركة منفصل لكل منتج مع الرصيد الجاري وسعرَي الشراء والبيع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'حركة كل منتج' => null,
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
            </div>

            <div class="card-body">
                <form id="filterForm" method="GET" class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="product_id">المنتج</label>
                            <select id="product_id" name="product" class="form-control select2">
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
                            <label for="brand">العلامة التجارية</label>
                            <select id="brand" name="brand" class="form-control select2">
                                <option value="">كل العلامات</option>
                                @foreach ($brands as $item)
                                    @php $brandValue = is_string($item) ? $item : ($item->brand ?? ''); @endphp
                                    @continue ($brandValue === '')

                                    <option value="{{ $brandValue }}" {{ request('brand') == $brandValue ? 'selected' : '' }}>
                                        {{ $brandValue }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="period">الفترة</label>
                            <select id="period" name="period" class="form-control">
                                @foreach ([1 => 'اليوم', 7 => 'آخر 7 أيام', 30 => 'آخر 30 يوماً', 90 => 'آخر 3 أشهر', 'custom' => 'مدّة مخصّصة'] as $value => $label)
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

                    <div class="col-md-3 col-sm-6" id="startDateBox" @if (request('period') != 'custom') hidden @endif>
                        <div class="form-group">
                            <label for="start_date">من تاريخ</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="{{ request('start_date') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6" id="endDateBox" @if (request('period') != 'custom') hidden @endif>
                        <div class="form-group">
                            <label for="end_date">إلى تاريخ</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="{{ request('end_date') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                                <a href="{{ route('StorHouseReport.Inventory_mov_det_product') }}" class="btn btn-outline-secondary">
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
                <span class="stk-metric__value stk-num">{{ $totals['total_products'] }}</span>
                <span class="stk-metric__label">عدد الأصناف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__value stk-num text-success">{{ number_format($totals['total_in'], 2) }}</span>
                <span class="stk-metric__label">إجمالي الوارد</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ number_format($totals['total_out'], 2) }}</span>
                <span class="stk-metric__label">إجمالي المنصرف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__value stk-num">{{ number_format($totals['current_balance'], 2) }}</span>
                <span class="stk-metric__label">الصافي الحالي</span>
            </div>
        </div>

        <div id="report-container">
            @forelse ($groups as $group)
                <div class="card stk-table-card product-section" data-product-name="{{ $group['product_name'] }}">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title">
                            <i class="feather icon-box"></i> {{ $group['product_name'] }}
                            @if ($group['product_code'] ?? null)
                                <span class="stk-count">#{{ $group['product_code'] }}</span>
                            @endif
                        </h6>

                        <div class="stk-tags">
                            <span class="stk-state stk-state--neutral">
                                وارد: <span class="stk-num">{{ number_format($group['total_in'], 2) }}</span>
                            </span>
                            <span class="stk-state stk-state--neutral">
                                منصرف: <span class="stk-num">{{ number_format($group['total_out'], 2) }}</span>
                            </span>
                            <span class="stk-balance stk-num">{{ number_format($group['current_balance'], 2) }}</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:14%">التاريخ</th>
                                    <th style="width:24%">الحركة / رقم الإذن</th>
                                    <th style="width:16%">المستودع</th>
                                    <th class="text-center" style="width:9%">وارد</th>
                                    <th class="text-center" style="width:9%">منصرف</th>
                                    <th class="text-center" style="width:10%">الرصيد</th>
                                    <th class="text-center" style="width:9%">سعر الشراء</th>
                                    <th class="text-center" style="width:9%">سعر البيع</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['movements'] as $move)
                                    <tr class="{{ $move['is_balance'] ? 'stk-total-row' : '' }}">
                                        <td class="stk-num">
                                            {{ $move['date'] ? \Illuminate\Support\Carbon::parse($move['date'])->format('Y-m-d H:i') : '—' }}
                                        </td>
                                        <td>{!! $move['type_html'] !!}</td>
                                        <td>{!! $move['warehouse_html'] !!}</td>
                                        <td class="text-center stk-num">
                                            @if ($move['in'] > 0)
                                                <span class="stk-move--success">{{ number_format($move['in'], 2) }}</span>
                                            @else
                                                <span class="stk-move--muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center stk-num">
                                            @if ($move['out'] > 0)
                                                <span class="stk-move--danger">{{ number_format($move['out'], 2) }}</span>
                                            @else
                                                <span class="stk-move--muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center stk-num"><strong>{{ number_format($move['balance'], 2) }}</strong></td>
                                        <td class="text-center stk-num">{{ number_format($move['avg_cost'], 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($move['selling_price'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="card">
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-search"></i></div>
                            <h5 class="stk-empty__title">لا نتائج مطابقة</h5>
                            <p class="stk-empty__hint">وسّع الفترة أو خفّف الفلاتر</p>
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
            const selector = '.product-section table';
            const table = document.querySelector(selector);

            if (! table) { window.print(); return; }

            const headRows = table.querySelectorAll('thead tr');
            const headers = Array.from(headRows[headRows.length - 1].querySelectorAll('th'))
                .map(cell => cell.textContent.trim());

            printTable({
                tableSelector: selector + ' tbody tr',
                tfootSelector: table.querySelector('tfoot') ? selector + ' tfoot' : null,
                title: 'حركة كل منتج',
                infoCards: Array.from(document.querySelectorAll('.stk-metric')).map(card => ({
                    label: (card.querySelector('.stk-metric__label') || {}).textContent || '',
                    value: (card.querySelector('.stk-metric__value') || {}).textContent || '',
                })),
                filterInfo: { 'تاريخ الطباعة': new Date().toISOString().slice(0, 10) },
                tableHeaders: headers,
                descriptionColumn: 1,
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

            /* حقلا التاريخ يظهران مع «مدّة مخصّصة» وحدها */
            $('#period').on('change', function () {
                const custom = this.value === 'custom';
                $('#startDateBox, #endDateBox').prop('hidden', ! custom);
            });

            $('#exportExcel').on('click', exportReport);
        });

        async function exportReport() {
            const config = {
                fileName: 'تقرير_حركة_المنتج_التفصيلي',
                mainTitle: 'تقرير حركة المنتج التفصيلي',
                subTitle: @json(config('app.name')),
                reportInfo: {
                    'التاريخ': new Date().toISOString().slice(0, 10),
                    'الفترة': $('#period option:selected').text(),
                    'المستودع': $('#warehouse option:selected').text(),
                },
                statisticsCards: [
                    { title: 'إجمالي الأصناف', value: @json((string) $totals['total_products']), unit: '', titleColor: 'primary', valueColor: 'primary' },
                    { title: 'إجمالي الوارد', value: @json(number_format($totals['total_in'], 2)), unit: '', titleColor: 'success', valueColor: 'success' },
                    { title: 'إجمالي المنصرف', value: @json(number_format($totals['total_out'], 2)), unit: '', titleColor: 'danger', valueColor: 'danger' },
                    { title: 'الصافي الحالي', value: @json(number_format($totals['current_balance'], 2)), unit: '', titleColor: 'info', valueColor: 'info' },
                ],
                tableTitle: 'تفاصيل الحركات',
                tableHeaders: ['التاريخ', 'نوع الحركة', 'المستودع', 'وارد', 'منصرف', 'الرصيد', 'سعر شراء', 'سعر بيع'],
                columnWidths: [20, 30, 20, 12, 12, 15, 15, 15],
                tableData: [],
            };

            $('.product-section').each(function () {
                config.tableData.push({
                    values: [$(this).data('product-name'), '', '', '', '', '', '', ''],
                    cellStyles: Array(8).fill({ background: 'primary', color: 'white', bold: true }),
                    mergeFrom: 1, mergeTo: 8,
                });

                $(this).find('tbody tr').each(function () {
                    const cells = $(this).find('td');

                    config.tableData.push({
                        values: cells.map((_, cell) => $(cell).text().trim()).get(),
                        cellStyles: [
                            null, null, null,
                            { color: 'success' }, { color: 'danger' },
                            { background: 'grayLight', bold: true },
                            null, null,
                        ],
                    });
                });

                config.tableData.push({ values: Array(8).fill(''), mergeFrom: 1, mergeTo: 8 });
            });

            await exportToExcelProfessional(config);
        }
    </script>
@endsection
