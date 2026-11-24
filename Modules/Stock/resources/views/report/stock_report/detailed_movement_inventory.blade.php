@extends('stock::master')

@section('title', 'الحركة التفصيلية للمخزون')

@section('content')
    @php
        $movements = $reportData['movements'] ?? [];
        $totals = $reportData['totals'] ?? [];

        $periods = [
            1 => 'اليوم',
            7 => 'أسبوع',
            30 => 'شهر',
            90 => '3 أشهر',
            365 => 'سنة',
            'custom' => 'مخصّص',
        ];

        $movementTypes = [
            'purchase' => 'فاتورة شراء',
            'sale' => 'فاتورة بيع',
            'purchase_return' => 'مرتجع شراء',
            'sale_return' => 'مرتجع بيع',
            'transfer' => 'نقل',
            'adjustment' => 'تعديل جرد',
            'manufacturing' => 'تصنيع',
        ];
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'الحركة التفصيلية للمخزون',
            'icon' => 'search',
            'sub' => 'كل حركة مخزنية بتفاصيلها: النوع والمسؤول والأسعار والرصيد قبل وبعد',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'تقارير المخزون' => route('StorHouseReport.index'),
                'الحركة التفصيلية' => null,
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
                <span class="stk-count stk-num">
                    {{ $startDate->format('Y-m-d') }} → {{ $endDate->format('Y-m-d') }}
                </span>
            </div>

            <div class="card-body">
                <form id="filterForm" method="GET" action="{{ route('StorHouseReport.detailedMovementInventory') }}" class="row">
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
                                   value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6 js-custom-dates" @if (request('period') != 'custom') hidden @endif>
                        <div class="form-group">
                            <label for="end_date">إلى تاريخ</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
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

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="type">نوع الحركة</label>
                            <select id="type" name="type" class="form-control select2">
                                <option value="">كل الأنواع</option>
                                @foreach ($movementTypes as $value => $label)
                                    <option value="{{ $value }}" {{ request('type') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="search">بحث شامل</label>
                            <input type="search" id="search" name="search" class="form-control"
                                   placeholder="الاسم أو الكود…" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="d-flex" style="gap:.4rem">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="feather icon-search"></i> بحث
                                </button>
                                <a href="{{ route('StorHouseReport.detailedMovementInventory') }}" class="btn btn-outline-secondary">
                                    <i class="feather icon-rotate-ccw"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="stk-switch">
                            <input type="checkbox" name="show_incoming" value="1" {{ request('show_incoming', '1') == '1' ? 'checked' : '' }}>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">إظهار الوارد</span></span>
                        </label>
                    </div>

                    <div class="col-md-6">
                        <label class="stk-switch">
                            <input type="checkbox" name="show_outgoing" value="1" {{ request('show_outgoing', '1') == '1' ? 'checked' : '' }}>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label"><span class="stk-switch__title">إظهار المنصرف</span></span>
                        </label>
                    </div>
                </form>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__value stk-num">{{ $totals['total_movements'] ?? 0 }}</span>
                <span class="stk-metric__label">إجمالي الحركات</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__value stk-num text-success">{{ number_format($totals['total_incoming'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">إجمالي الوارد</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ number_format($totals['total_outgoing'] ?? 0, 2) }}</span>
                <span class="stk-metric__label">إجمالي المنصرف</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num">{{ $totals['total_products'] ?? 0 }}</span>
                <span class="stk-metric__label">المنتجات المتأثّرة</span>
            </div>
        </div>

        <div class="card stk-table-card" id="reportCard">
            <div class="stk-print-head">
                <h3>الحركة التفصيلية للمخزون</h3>
                <p>
                    من {{ $startDate->format('Y-m-d') }} إلى {{ $endDate->format('Y-m-d') }}
                    — طُبع في {{ now()->format('Y-m-d H:i') }}
                </p>
            </div>

            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-activity"></i> تفاصيل الحركات
                    <span class="stk-count">{{ count($movements) }}</span>
                </h6>
            </div>

            @if (count($movements) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="movementsTable">
                        <thead>
                            <tr>
                                <th style="width:44px">#</th>
                                <th style="width:10%">التاريخ</th>
                                <th style="width:9%">النوع</th>
                                <th style="width:8%">الكود</th>
                                <th>المنتج</th>
                                <th style="width:9%">المسؤول</th>
                                <th class="text-center" style="width:8%">متوسط التكلفة</th>
                                <th class="text-center" style="width:7%">سعر البيع</th>
                                <th class="text-center" style="width:7%">الوارد</th>
                                <th class="text-center" style="width:7%">المنصرف</th>
                                <th class="text-center" style="width:7%">قبل</th>
                                <th class="text-center" style="width:7%">بعد</th>
                                <th style="width:9%">المستودع</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            @foreach ($movements as $item)
                                @php
                                    $isBalanceRow = ($item['direction'] ?? '') === 'balance';
                                    $isIncoming = ($item['direction'] ?? '') === 'in';
                                    $quantity = abs($item['quantity'] ?? 0);
                                @endphp

                                @if ($isBalanceRow)
                                    <tr class="stk-total-row">
                                        <td class="text-muted">—</td>
                                        <td class="text-muted">—</td>
                                        <td>{!! $item['type_display_html'] !!}</td>
                                        <td class="text-muted">—</td>
                                        <td>{{ $item['product_name'] }}</td>
                                        <td class="text-muted">—</td>
                                        <td class="text-center text-muted">—</td>
                                        <td class="text-center text-muted">—</td>
                                        <td class="text-center text-muted">—</td>
                                        <td class="text-center text-muted">—</td>
                                        <td class="text-center text-muted">—</td>
                                        <td class="text-center stk-num">{{ number_format($item['stock_after'] ?? 0, 2) }}</td>
                                        <td class="text-muted">—</td>
                                        <td class="text-muted">—</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="stk-num text-muted">{{ $item['id'] }}</td>
                                        <td class="stk-num">
                                            {{ \Illuminate\Support\Carbon::parse($item['date'])->format('Y-m-d H:i') }}
                                        </td>
                                        <td>{!! $item['type_display_html'] ?? '—' !!}</td>
                                        <td class="stk-num">{{ $item['product_code'] ?? '—' }}</td>
                                        <td><span class="stk-media__title">{{ $item['product_name'] ?? '—' }}</span></td>
                                        <td>{{ $item['user_name'] ?? '—' }}</td>
                                        <td class="text-center stk-num">{{ number_format($item['avg_cost'] ?? 0, 2) }}</td>
                                        <td class="text-center stk-num">{{ number_format($item['selling_price'] ?? 0, 2) }}</td>
                                        <td class="text-center stk-num">
                                            @if ($isIncoming)
                                                <span class="stk-move--success">{{ number_format($quantity, 2) }}</span>
                                            @else
                                                <span class="stk-move--muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center stk-num">
                                            @if (! $isIncoming)
                                                <span class="stk-move--danger">{{ number_format($quantity, 2) }}</span>
                                            @else
                                                <span class="stk-move--muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center stk-num text-muted">{{ number_format($item['stock_before'] ?? 0, 2) }}</td>
                                        <td class="text-center stk-num"><strong>{{ number_format($item['stock_after'] ?? 0, 2) }}</strong></td>
                                        <td>{!! $item['warehouse_display_html'] ?? '—' !!}</td>
                                        <td>{{ $item['notes'] ?? '—' }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                        <h5 class="stk-empty__title">لا حركات للعرض</h5>
                        <p class="stk-empty__hint">غيّر معايير البحث أو وسّع الفترة</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        /* الطباعة عبر المساعد المخصَّص: جدولٌ مهيّأ للورق ببطاقات معلومات */
        function stkPrintReport() {
            const selector = '#movementsTable';
            const table = document.querySelector(selector);

            if (! table) { window.print(); return; }

            const headRows = table.querySelectorAll('thead tr');
            const headers = Array.from(headRows[headRows.length - 1].querySelectorAll('th'))
                .map(cell => cell.textContent.trim());

            printTable({
                tableSelector: selector + ' tbody tr',
                tfootSelector: table.querySelector('tfoot') ? selector + ' tfoot' : null,
                title: 'الحركة التفصيلية للمخزون',
                infoCards: Array.from(document.querySelectorAll('.stk-metric')).map(card => ({
                    label: (card.querySelector('.stk-metric__label') || {}).textContent || '',
                    value: (card.querySelector('.stk-metric__value') || {}).textContent || '',
                })),
                filterInfo: { 'تاريخ الطباعة': new Date().toISOString().slice(0, 10) },
                tableHeaders: headers,
                descriptionColumn: 4,
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
            أُزيلت بطاقة «الرسم البياني»: كانت `display:none` دائماً ولا كود
            يرسم فيها شيئاً — ولا Chart.js محمَّلة في الصفحة أصلاً.
            والطباعة كانت تفتح نافذةً بأنماطٍ تشير إلى متغيّرات CSS غير
            معرَّفة فيها؛ صارت تعتمد أنماط الطباعة المشتركة.
        */
        $(function () {
            if ($.fn.select2) {
                $('#filterForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%', allowClear: true });
            }

            $('#period').on('change', function () {
                $('.js-custom-dates').prop('hidden', this.value !== 'custom');
            });

            $('#exportExcel').on('click', exportReport);
        });

        async function exportReport() {
            const config = {
                fileName: 'تقرير_الحركة_التفصيلية_للمخزون',
                mainTitle: 'تقرير الحركة التفصيلية للمخزون',
                subTitle: @json(config('app.name')),
                reportInfo: {
                    'الفترة': @json($startDate->format('Y-m-d') . ' → ' . $endDate->format('Y-m-d')),
                    'المستودع': $('#warehouse option:selected').text(),
                    'التصنيف': $('#category option:selected').text(),
                    'نوع الحركة': $('#type option:selected').text(),
                },
                statisticsCards: [
                    { title: 'إجمالي الحركات', value: @json((string) ($totals['total_movements'] ?? 0)), unit: '', titleColor: 'primary', valueColor: 'primary' },
                    { title: 'إجمالي الوارد', value: @json(number_format($totals['total_incoming'] ?? 0, 2)), unit: '', titleColor: 'success', valueColor: 'success' },
                    { title: 'إجمالي المنصرف', value: @json(number_format($totals['total_outgoing'] ?? 0, 2)), unit: '', titleColor: 'danger', valueColor: 'danger' },
                    { title: 'المنتجات المتأثّرة', value: @json((string) ($totals['total_products'] ?? 0)), unit: '', titleColor: 'info', valueColor: 'info' },
                ],
                tableTitle: 'تفاصيل الحركات',
                tableHeaders: [
                    '#', 'التاريخ', 'النوع', 'الكود', 'المنتج', 'المسؤول', 'متوسط التكلفة', 'سعر البيع',
                    'الوارد', 'المنصرف', 'الرصيد قبل', 'الرصيد بعد', 'المستودع', 'ملاحظات',
                ],
                columnWidths: [5, 15, 15, 12, 25, 15, 12, 12, 12, 12, 12, 12, 20, 25],
                tableData: [],
                buttonSelector: '#exportExcel',
            };

            $('#movementsTable tbody tr').each(function () {
                const cells = $(this).find('td');
                if (cells.length < 14) return;

                config.tableData.push({
                    values: cells.map((_, cell) => $(cell).text().trim()).get(),
                    cellStyles: [
                        null, null, null, null, { alignment: 'right' }, null, null, null,
                        { color: 'success', bold: true }, { color: 'danger', bold: true },
                        null, { bold: true }, null, null,
                    ],
                });
            });

            await exportToExcelProfessional(config);
        }
    </script>
@endsection
