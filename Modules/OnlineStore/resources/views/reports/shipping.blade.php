@extends('onlinestore::master')

@section('title', 'تقرير الشحن والتوصيل')

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقرير الشحن والتوصيل',
            'icon' => 'truck',
            'sub' => 'تكاليف الشحن وطرقه والمدن الأكثر طلباً',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => route('onlinestore.reports.index'),
                'الشحن' => null,
            ],
        ])

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> الفترة الزمنية</h6>
                </div>
                <form method="GET">
                    <div class="row">
                        {{-- الفترة المحاسبية + مدًى حرّ — نفس الجزء المستعمل في تقارير الحسابات --}}
                        @include('partials.accounting-period-filter', [
                            'period' => $accountingPeriod ?? [],
                            'withDates' => true,
                            'fromName' => 'start_date',
                            'toName' => 'end_date',
                            'colClass' => 'col-md-4 col-sm-6',
                        ])
                    </div>
                    <div class="stk-filters__foot">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-filter"></i> تصفية
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-truck"></i></span>
                <span class="stk-metric__label">إجمالي تكاليف الشحن</span>
                <span class="stk-metric__value">{{ number_format($totalShippingCost, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إيرادات الشحن (مكتملة)</span>
                <span class="stk-metric__value">{{ number_format($shippingRevenue, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart"></i></span>
                <span class="stk-metric__label">متوسط تكلفة الشحن</span>
                <span class="stk-metric__value">{{ number_format($avgShippingCost, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-gift"></i></span>
                <span class="stk-metric__label">طلبات شحن مجاني</span>
                <span class="stk-metric__value">{{ number_format($freeShippingOrders) }}</span>
                <small class="text-warning">{{ $freeShippingRate }}% من الطلبات</small>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> تكاليف الشحن اليومية</h6>
                    </div>
                    <div class="card-body">
                        <div id="dailyShippingChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> الطلبات حسب طريقة الشحن</h6>
                    </div>
                    <div class="card-body">
                        <div id="shippingMethodChart" style="height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head d-flex justify-content-between align-items-center flex-wrap">
                        <h6 class="stk-table-head__title"><i class="feather icon-truck"></i> تفاصيل طرق الشحن</h6>
                        <div class="stk-table-head__actions no-print">
                            <button type="button" id="printShippingMethodBtn" class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> طباعة
                            </button>
                            <button type="button" id="exportShippingMethodBtn" class="btn btn-info btn-sm">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="shippingMethodReportTable">
                            <thead>
                                <tr>
                                    <th>طريقة الشحن</th>
                                    <th>عدد الطلبات</th>
                                    <th>إجمالي الشحن</th>
                                    <th>المتوسط</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ordersByShippingMethod as $method)
                                    <tr>
                                        <td class="fw-bold">{{ $method->shippingMethod->name ?? 'غير محدد' }}</td>
                                        <td class="stk-num">{{ number_format($method->orders_count) }}</td>
                                        <td class="stk-num">{{ number_format($method->total_shipping, 2) }}</td>
                                        <td class="stk-num">{{ number_format($method->avg_shipping, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="stk-report-empty">
                                        <td colspan="4" class="text-center text-muted">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> الطلبات حسب المدينة</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>المدينة</th>
                                    <th>الطلبات</th>
                                    <th>تكاليف الشحن</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ordersByCity as $city)
                                    <tr>
                                        <td class="fw-bold">{{ $city->city ?? 'غير محدد' }}</td>
                                        <td class="stk-num">{{ number_format($city->orders_count) }}</td>
                                        <td class="stk-num">{{ number_format($city->total_shipping, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-check-circle"></i> طرق الشحن النشطة</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    @forelse($activeShippingMethods as $method)
                        <div class="col-lg-3 col-md-4 col-6 mb-2">
                            <div class="border rounded p-2">
                                <h6 class="fw-bold mb-1">{{ $method->name }}</h6>
                                <p class="mb-0 small text-muted">
                                    التكلفة: <strong>{{ number_format($method->cost, 2) }}</strong> {{ \App\Support\CurrencySettings::symbol() }}
                                </p>
                                @if ($method->free_shipping_threshold)
                                    <p class="mb-0 small text-success">
                                        مجاني فوق {{ number_format($method->free_shipping_threshold) }} {{ \App\Support\CurrencySettings::symbol() }}
                                    </p>
                                @endif
                                <p class="mb-0 small text-muted">
                                    التوصيل: {{ $method->estimated_days_min }}-{{ $method->estimated_days_max }} أيام
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-truck"></i></span>
                                <h6 class="stk-empty__title">لا توجد طرق شحن نشطة</h6>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/l10n/ar.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Shipping Report: Document Ready');

    // Flatpickr
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr-basic', { locale: 'ar', dateFormat: 'Y-m-d' });
    }

    // التحقق من ApexCharts
    if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts not loaded!');
        return;
    }

    // البيانات المحولة من Controller
    var dailyData = @json($dailyShippingChart ?? []);
    var methodsData = @json($shippingMethodsChart ?? []);

    console.log('Daily Shipping Data:', dailyData);
    console.log('Shipping Methods Data:', methodsData);

    // الشحن اليومي
    var dailyShippingChartEl = document.querySelector("#dailyShippingChart");
    if (dailyShippingChartEl && dailyData && dailyData.length > 0) {
        try {
            new ApexCharts(dailyShippingChartEl, {
                series: [{
                    name: 'الطلبات',
                    type: 'column',
                    data: dailyData.map(function(d) { return d.orders_count || 0; })
                }, {
                    name: 'تكلفة الشحن',
                    type: 'line',
                    data: dailyData.map(function(d) { return d.total_shipping || 0; })
                }],
                chart: { height: 300, toolbar: { show: false } },
                xaxis: {
                    categories: dailyData.map(function(d) { return d.date; })
                },
                yaxis: [{
                    title: { text: 'الطلبات' }
                }, {
                    opposite: true,
                    title: { text: 'التكلفة' }
                }],
                colors: ['#7367f0', '#28c76f'],
                stroke: { width: [0, 3] },
                tooltip: {
                    y: {
                        formatter: function(val, { seriesIndex }) {
                            if (seriesIndex === 1) {
                                return val ? val.toLocaleString('ar-SA') + ' ' + window.AppCurrency.symbol : '0 ' + window.AppCurrency.symbol;
                            }
                            return val + ' طلب';
                        }
                    }
                }
            }).render();
            console.log('Daily Shipping Chart rendered successfully');
        } catch (e) {
            console.error('Error rendering dailyShippingChart:', e);
        }
    } else if (dailyShippingChartEl) {
        dailyShippingChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }

    // طرق الشحن
    var shippingMethodChartEl = document.querySelector("#shippingMethodChart");
    if (shippingMethodChartEl && methodsData && methodsData.length > 0) {
        try {
            new ApexCharts(shippingMethodChartEl, {
                series: methodsData.map(function(m) { return m.orders_count || 0; }),
                chart: { type: 'donut', height: 280 },
                labels: methodsData.map(function(m) { return m.name || 'غير محدد'; }),
                colors: ['#7367f0', '#28c76f', '#ff9f43', '#00cfe8', '#ea5455'],
                legend: { position: 'bottom' },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return val + ' طلب';
                        }
                    }
                }
            }).render();
            console.log('Shipping Method Chart rendered successfully');
        } catch (e) {
            console.error('Error rendering shippingMethodChart:', e);
        }
    } else if (shippingMethodChartEl) {
        shippingMethodChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }
});
</script>

<script>
    // =====================================
    // طباعة/تصدير مخصّصان — نفس نمط Account\GeneralAccountsController
    // (public/js/print-table.js + public/js/excel-export-helper.js)
    // =====================================
    function formatNumber(n, decimals = 2) {
        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function cellText(cell) {
        return $(cell).text().trim().replace(/\s+/g, ' ');
    }

    @php
        $shippingPeriodText = 'من ' . ($startDate instanceof \Carbon\Carbon ? $startDate->format('d/m/Y') : $startDate)
            . ' إلى ' . ($endDate instanceof \Carbon\Carbon ? $endDate->format('d/m/Y') : $endDate);
    @endphp

    $(document).ready(function() {
        $('#printShippingMethodBtn').click(printShippingMethodReport);
        $('#exportShippingMethodBtn').click(exportShippingMethodReport);
    });

    function shippingMethodInfoCards() {
        return [
            { label: 'إجمالي تكاليف الشحن', value: '{{ number_format($totalShippingCost, 2) }} ' + window.AppCurrency.symbol },
            { label: 'إيرادات الشحن (مكتملة)', value: '{{ number_format($shippingRevenue, 2) }} ' + window.AppCurrency.symbol, class: 'text-success' },
            { label: 'متوسط تكلفة الشحن', value: '{{ number_format($avgShippingCost, 2) }} ' + window.AppCurrency.symbol },
            { label: 'طلبات شحن مجاني', value: '{{ number_format($freeShippingOrders) }}', class: 'text-warning' }
        ];
    }

    function printShippingMethodReport() {
        printTable({
            tableSelector: '#shippingMethodReportTable tbody tr',
            title: 'تقرير الشحن — تفاصيل طرق الشحن',
            infoCards: shippingMethodInfoCards(),
            filterInfo: {
                'الفترة': '{{ $shippingPeriodText }}'
            },
            tableHeaders: ['طريقة الشحن', 'عدد الطلبات', 'إجمالي الشحن', 'المتوسط'],
            columnWidths: ['34%', '20%', '23%', '23%'],
            windowWidth: 1000,
            windowHeight: 750
        });
    }

    function exportShippingMethodReport() {
        var tableData = [];

        $('#shippingMethodReportTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 4) return;

            tableData.push({
                values: [
                    cellText(cells[0]),
                    cellText(cells[1]),
                    formatNumber(cellText(cells[2])) + ' ' + window.AppCurrency.symbol,
                    formatNumber(cellText(cells[3])) + ' ' + window.AppCurrency.symbol
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' }, bold: true },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success' },
                    { alignment: { horizontal: 'center' } }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_الشحن_طرق_الشحن',
            mainTitle: 'تقرير الشحن — تفاصيل طرق الشحن',
            subTitle: 'التقارير - المتجر الإلكتروني',

            reportInfo: {
                'الفترة': '{{ $shippingPeriodText }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي تكاليف الشحن',
                    value: '{{ number_format($totalShippingCost, 2) }}',
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'primary',
                    valueColor: 'primary',
                    backgroundColor: 'grayLight',
                    borderColor: 'primary'
                },
                {
                    title: 'إيرادات الشحن (مكتملة)',
                    value: '{{ number_format($shippingRevenue, 2) }}',
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                },
                {
                    title: 'متوسط تكلفة الشحن',
                    value: '{{ number_format($avgShippingCost, 2) }}',
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'info',
                    valueColor: 'info',
                    backgroundColor: 'grayLight',
                    borderColor: 'info'
                },
                {
                    title: 'طلبات شحن مجاني',
                    value: '{{ number_format($freeShippingOrders) }}',
                    titleColor: 'warning',
                    valueColor: 'warning',
                    backgroundColor: 'warningLight',
                    borderColor: 'warning'
                }
            ],

            tableTitle: 'تفاصيل طرق الشحن',
            tableHeaders: ['طريقة الشحن', 'عدد الطلبات', 'إجمالي الشحن', 'المتوسط'],
            columnWidths: [34, 18, 22, 22],

            tableData: tableData,

            buttonSelector: '#exportShippingMethodBtn',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }
</script>
@endsection
