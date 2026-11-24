@extends('onlinestore::master')

@section('title', 'تقارير المتجر - نظرة عامة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <div class="btn-group" role="group">
                <a href="{{ route('onlinestore.reports.index', ['period' => '7']) }}"
                    class="btn btn-outline-primary {{ $period == '7' ? 'active' : '' }}">7 أيام</a>
                <a href="{{ route('onlinestore.reports.index', ['period' => '30']) }}"
                    class="btn btn-outline-primary {{ $period == '30' ? 'active' : '' }}">30 يوم</a>
                <a href="{{ route('onlinestore.reports.index', ['period' => '90']) }}"
                    class="btn btn-outline-primary {{ $period == '90' ? 'active' : '' }}">90 يوم</a>
            </div>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تقارير المتجر',
            'icon' => 'bar-chart-2',
            'sub' => 'نظرة عامة على أداء المتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'التقارير' => null,
            ],
        ])

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">إجمالي الطلبات</span>
                <span class="stk-metric__value">{{ number_format($totalOrders) }}</span>
                <small class="{{ $ordersGrowth >= 0 ? 'text-success' : 'text-danger' }}">
                    <i class="feather icon-{{ $ordersGrowth >= 0 ? 'trending-up' : 'trending-down' }}"></i>
                    {{ abs($ordersGrowth) }}%
                </small>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الإيرادات</span>
                <span class="stk-metric__value">{{ number_format($totalRevenueWithoutCancelledReturned, 2) }}</span>
                <small class="{{ $revenueGrowth >= 0 ? 'text-success' : 'text-danger' }}">
                    <i class="feather icon-{{ $revenueGrowth >= 0 ? 'trending-up' : 'trending-down' }}"></i>
                    {{ abs($revenueGrowth) }}%
                </small>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي مبالغ الطلبات</span>
                <span class="stk-metric__value">{{ number_format($totalOrderAmounts, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">المنتجات المباعة</span>
                <span class="stk-metric__value">{{ number_format($totalProductsSold) }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">الطلبات المعلقة</span>
                <span class="stk-metric__value">{{ number_format($pendingOrders) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">الطلبات المؤكدة</span>
                <span class="stk-metric__value">{{ number_format($confirmedOrders) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-settings"></i></span>
                <span class="stk-metric__label">جاري التجهيز</span>
                <span class="stk-metric__value">{{ number_format($processingOrders) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-truck"></i></span>
                <span class="stk-metric__label">الطلبات المشحونة</span>
                <span class="stk-metric__value">{{ number_format($shippedOrders) }}</span>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-check-square"></i></span>
                <span class="stk-metric__label">الطلبات المستلمة</span>
                <span class="stk-metric__value">{{ number_format($deliveredOrders) }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">الطلبات الملغية</span>
                <span class="stk-metric__value">{{ number_format($cancelledOrders) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-rotate-ccw"></i></span>
                <span class="stk-metric__label">الطلبات المرتجعة</span>
                <span class="stk-metric__value">{{ number_format($returnedOrders) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">الطلبات المستردة</span>
                <span class="stk-metric__value">{{ number_format($refundedOrders) }}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> المبيعات اليومية</h6>
                        <span class="text-muted small">{{ $startDate->format('Y/m/d') }} - {{ $endDate->format('Y/m/d') }}</span>
                    </div>
                    <div class="card-body">
                        <div id="salesChart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> توزيع حالات الطلبات</h6>
                    </div>
                    <div class="card-body">
                        <div id="statusChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-link"></i> التقارير التفصيلية</h6>
                    </div>
                    <div class="card-body d-flex flex-column gap-1">
                        <a href="{{ route('onlinestore.reports.sales') }}" class="btn btn-outline-success text-start">
                            <i class="feather icon-dollar-sign"></i> تقرير المبيعات المالي
                        </a>
                        <a href="{{ route('onlinestore.reports.orders') }}" class="btn btn-outline-primary text-start">
                            <i class="feather icon-shopping-cart"></i> تقرير الطلبات
                        </a>
                        <a href="{{ route('onlinestore.reports.products') }}" class="btn btn-outline-info text-start">
                            <i class="feather icon-package"></i> تقرير المنتجات
                        </a>
                        <a href="{{ route('onlinestore.reports.clients') }}" class="btn btn-outline-secondary text-start">
                            <i class="feather icon-users"></i> تقرير العملاء
                        </a>
                        <a href="{{ route('onlinestore.reports.coupons') }}" class="btn btn-outline-warning text-start">
                            <i class="feather icon-tag"></i> تقرير الكوبونات
                        </a>
                        <a href="{{ route('onlinestore.reports.shipping') }}" class="btn btn-outline-dark text-start">
                            <i class="feather icon-truck"></i> تقرير الشحن
                        </a>
                        <a href="{{ route('onlinestore.reports.reviews') }}" class="btn btn-outline-danger text-start">
                            <i class="feather icon-star"></i> تقرير التقييمات
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> أفضل المنتجات</h6>
                        <div class="btn-group btn-group-sm no-print" role="group">
                            <button type="button" id="printTopProductsBtn" class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> طباعة
                            </button>
                            <button type="button" id="exportTopProductsExcel" class="btn btn-info btn-sm">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0" id="topProductsTable">
                            <tbody>
                                @forelse($topProducts as $item)
                                    <tr>
                                        <td class="py-1">
                                            @if ($item->product && $item->product->images)
                                                @php $images = is_array($item->product->images) ? $item->product->images : json_decode($item->product->images, true); @endphp
                                                @if (!empty($images))
                                                    <img src="{{ asset('storage/' . $images[0]) }}" alt="" class="rounded" width="40" height="40" style="object-fit: cover;">
                                                @else
                                                    <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                                @endif
                                            @else
                                                <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                            @endif
                                        </td>
                                        <td class="py-1">
                                            <span class="fw-bold">{{ $item->product->name ?? 'منتج محذوف' }}</span>
                                            <br><small class="text-muted">{{ number_format($item->total_qty) }} قطعة</small>
                                        </td>
                                        <td class="py-1 text-end">
                                            <span class="text-success fw-bold">{{ number_format($item->total_revenue, 2) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">
                                            <div class="stk-empty">
                                                <span class="stk-empty__icon"><i class="feather icon-package"></i></span>
                                                <p class="stk-empty__hint mb-0">لا توجد بيانات</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-rotate-ccw"></i> تفاصيل المرتجعات</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>الطلبات المرتجعة</span>
                                <strong>{{ $returnedOrders }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>الطلبات المستردة</span>
                                <strong>{{ $refundedOrders }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>إجمالي المبالغ المستردة</span>
                                <strong class="text-danger">{{ number_format($totalRefundedAmount, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-bar-chart-2"></i> تحليل حالات الطلبات</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>قيد الانتظار</span>
                                <strong class="text-warning">{{ $pendingOrders }} | {{ number_format($pendingRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>مؤكد</span>
                                <strong class="text-info">{{ $confirmedOrders }} | {{ number_format($confirmedRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>جاري التجهيز</span>
                                <strong class="text-primary">{{ $processingOrders }} | {{ number_format($processingRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>جاهز للشحن</span>
                                <strong class="text-info">{{ $readyForShippingOrders }} | {{ number_format($readyForShippingRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>تم الشحن</span>
                                <strong class="text-primary">{{ $shippedOrders }} | {{ number_format($shippedRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>في الطريق</span>
                                <strong class="text-info">{{ $outForDeliveryOrders }} | {{ number_format($outForDeliveryRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>تم التسليم</span>
                                <strong class="text-success">{{ $deliveredOrders }} | {{ number_format($deliveredRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>ملغي</span>
                                <strong class="text-danger">{{ $cancelledOrders }} | {{ number_format($cancelledRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>مرتجع</span>
                                <strong>{{ $returnedOrders }} | {{ number_format($returnedRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>مسترد</span>
                                <strong>{{ $refundedOrders }} | {{ number_format($refundedRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>إجمالي الإيرادات (بدون المسترده والملغيه والمرتجعه)</span>
                                <strong class="text-success">{{ number_format($totalRevenueWithoutCancelledReturned, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-users"></i> أفضل العملاء</h6>
                        <div class="btn-group btn-group-sm no-print" role="group">
                            <button type="button" id="printTopClientsBtn" class="btn btn-success btn-sm">
                                <i class="fa fa-print"></i> طباعة
                            </button>
                            <button type="button" id="exportTopClientsExcel" class="btn btn-info btn-sm">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0" id="topClientsTable">
                            <tbody>
                                @forelse($topClients as $item)
                                    <tr>
                                        <td class="py-1">
                                            <div class="avatar bg-light-primary mr-1">
                                                <div class="avatar-content">{{ mb_substr($item->client->full_name ?? 'ع', 0, 1) }}</div>
                                            </div>
                                        </td>
                                        <td class="py-1">
                                            <span class="fw-bold">{{ $item->client->full_name ?? 'عميل محذوف' }}</span>
                                            <br><small class="text-muted">{{ $item->orders_count }} طلب</small>
                                        </td>
                                        <td class="py-1 text-end">
                                            <span class="text-success fw-bold">{{ number_format($item->total_spent, 2) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3">
                                            <div class="stk-empty">
                                                <span class="stk-empty__icon"><i class="feather icon-users"></i></span>
                                                <p class="stk-empty__hint mb-0">لا توجد بيانات</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
$(document).ready(function() {
    console.log('Reports Index: Document Ready');

    // رسم بياني للمبيعات اليومية
    var salesData = @json($dailySales);
    console.log('Sales Data:', salesData);
    var dates = salesData.map(item => item.date);
    var revenues = salesData.map(item => parseFloat(item.revenue) || 0);
    var orders = salesData.map(item => parseInt(item.orders) || 0);

    if (salesData && salesData.length > 0) {
        var salesChartOptions = {
            series: [{
                name: 'الإيرادات',
                type: 'area',
                data: revenues
            }, {
                name: 'الطلبات',
                type: 'line',
                data: orders
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: { show: false },
                fontFamily: 'inherit',
                rtl: true
            },
            stroke: {
                width: [0, 3],
                curve: 'smooth'
            },
            fill: {
                type: ['gradient', 'solid'],
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                opacityTo: 0.3,
            }
        },
        labels: dates,
        markers: {
            size: 4
        },
        xaxis: {
            type: 'datetime'
        },
        yaxis: [{
            title: { text: 'الإيرادات' },
            labels: {
                formatter: function(val) {
                    return val.toLocaleString('ar-SA');
                }
            }
        }, {
            opposite: true,
            title: { text: 'الطلبات' }
        }],
        colors: ['#7367f0', '#28c76f'],
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: function(val, { seriesIndex }) {
                    if (seriesIndex === 0) {
                        return val ? val.toLocaleString('ar-SA') + ' ' + window.AppCurrency.symbol : '0 ' + window.AppCurrency.symbol;
                    }
                    return val + ' طلب';
                }
            }
        },
        noData: { text: 'لا توجد بيانات' }
    };

    var salesChart = new ApexCharts(document.querySelector("#salesChart"), salesChartOptions);
    salesChart.render();
    } // end if salesData.length

    // رسم بياني دائري لحالات الطلبات
    var statusData = @json($ordersByStatus);
    console.log('Status Data:', statusData);
    var statusLabels = {
        'pending': 'قيد الانتظار',
        'confirmed': 'مؤكد',
        'processing': 'قيد التجهيز',
        'ready_for_shipping': 'جاهز للشحن',
        'shipped': 'تم الشحن',
        'out_for_delivery': 'في الطريق',
        'delivered': 'تم التسليم',
        'cancelled': 'ملغي',
        'returned': 'مرتجع',
        'refunded': 'مسترد'
    };

    var labels = Object.keys(statusData).map(key => statusLabels[key] || key);
    var values = Object.values(statusData);
    console.log('Labels:', labels, 'Values:', values);

    if (values && values.length > 0) {
        var statusChartOptions = {
            series: values,
            chart: {
                type: 'donut',
                height: 300
            },
            labels: labels,
            colors: ['#ff9f43', '#17a2b8', '#00cfe8', '#6f42c1', '#7367f0', '#20c997', '#28c76f', '#ea5455', '#fd7e14', '#6c757d'],
            legend: {
                position: 'bottom'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'الإجمالي',
                                formatter: function(w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: { width: 200 },
                    legend: { position: 'bottom' }
                }
            }],
            noData: { text: 'لا توجد بيانات' }
        };

        var statusChart = new ApexCharts(document.querySelector("#statusChart"), statusChartOptions);
        statusChart.render();
    } // end if values.length
});

// =====================================
// أزرار الطباعة والتصدير المخصصة لكل جدول
// =====================================
$(document).ready(function() {
    // طباعة جدول أفضل المنتجات
    $('#printTopProductsBtn').click(function() {
        printTopProductsReport();
    });

    // تصدير جدول أفضل المنتجات إلى Excel
    $('#exportTopProductsExcel').click(function() {
        exportTopProductsReport();
    });

    // طباعة جدول أفضل العملاء
    $('#printTopClientsBtn').click(function() {
        printTopClientsReport();
    });

    // تصدير جدول أفضل العملاء إلى Excel
    $('#exportTopClientsExcel').click(function() {
        exportTopClientsReport();
    });
});

// =====================================
// أفضل المنتجات - طباعة
// =====================================
function printTopProductsReport() {
    printTable({
        tableSelector: '#topProductsTable tbody tr',
        title: 'تقرير أفضل المنتجات',
        infoCards: [
            { label: 'عدد المنتجات المعروضة', value: '{{ count($topProducts) }}' },
            { label: 'إجمالي المنتجات المباعة', value: '{{ number_format($totalProductsSold) }} قطعة' },
            { label: 'إجمالي الإيرادات', value: '{{ number_format($totalRevenueWithoutCancelledReturned, 2) }} ' + window.AppCurrency.symbol, class: 'text-success' }
        ],
        filterInfo: {
            'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }} ({{ $period }} يوم)'
        },
        tableHeaders: ['صورة', 'المنتج', 'إجمالي المبيعات'],
        columnWidths: ['15%', '55%', '30%'],
        skipColumns: [0],
        windowWidth: 900,
        windowHeight: 700
    });
}

// =====================================
// أفضل المنتجات - تصدير Excel
// =====================================
function exportTopProductsReport() {
    var tableData = [];

    $('#topProductsTable tbody tr').each(function() {
        var cells = $(this).find('td');
        // تجاهل صف "لا توجد بيانات" (خلية واحدة فقط بـ colspan)
        if (cells.length < 3) return;

        tableData.push({
            values: [
                $(cells[1]).text().trim(),
                $(cells[2]).text().trim()
            ],
            cellStyles: [
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' }, color: 'success', bold: true }
            ]
        });
    });

    var config = {
        fileName: 'تقرير_أفضل_المنتجات',
        mainTitle: 'تقرير أفضل المنتجات',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
            'عدد الأيام': '{{ $period }} يوم',
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },

        statisticsCards: [
            {
                title: 'عدد المنتجات المعروضة',
                value: '{{ count($topProducts) }}',
                unit: '',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'إجمالي المنتجات المباعة',
                value: '{{ number_format($totalProductsSold) }}',
                unit: ' قطعة',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'إجمالي الإيرادات',
                value: '{{ number_format($totalRevenueWithoutCancelledReturned, 2) }}',
                unit: ' ' + window.AppCurrency.symbol,
                titleColor: 'danger',
                valueColor: 'danger',
                backgroundColor: 'dangerLight',
                borderColor: 'danger'
            }
        ],

        tableTitle: 'أفضل المنتجات مبيعاً',
        tableHeaders: ['المنتج', 'إجمالي المبيعات'],
        columnWidths: [45, 25],

        tableData: tableData,

        buttonSelector: '#exportTopProductsExcel',
        successMessage: 'تم تصدير التقرير بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}

// =====================================
// أفضل العملاء - طباعة
// =====================================
function printTopClientsReport() {
    printTable({
        tableSelector: '#topClientsTable tbody tr',
        title: 'تقرير أفضل العملاء',
        infoCards: [
            { label: 'عدد العملاء المعروضين', value: '{{ count($topClients) }}' },
            { label: 'إجمالي عدد العملاء', value: '{{ number_format($totalCustomers) }}' },
            { label: 'الفترة', value: 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}' }
        ],
        filterInfo: {
            'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }} ({{ $period }} يوم)'
        },
        tableHeaders: ['صورة', 'العميل', 'إجمالي المشتريات'],
        columnWidths: ['15%', '55%', '30%'],
        skipColumns: [0],
        windowWidth: 900,
        windowHeight: 700
    });
}

// =====================================
// أفضل العملاء - تصدير Excel
// =====================================
function exportTopClientsReport() {
    var tableData = [];

    $('#topClientsTable tbody tr').each(function() {
        var cells = $(this).find('td');
        // تجاهل صف "لا توجد بيانات" (خلية واحدة فقط بـ colspan)
        if (cells.length < 3) return;

        tableData.push({
            values: [
                $(cells[1]).text().trim(),
                $(cells[2]).text().trim()
            ],
            cellStyles: [
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' }, color: 'success', bold: true }
            ]
        });
    });

    var config = {
        fileName: 'تقرير_أفضل_العملاء',
        mainTitle: 'تقرير أفضل العملاء',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
            'عدد الأيام': '{{ $period }} يوم',
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },

        statisticsCards: [
            {
                title: 'عدد العملاء المعروضين',
                value: '{{ count($topClients) }}',
                unit: '',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'إجمالي عدد العملاء',
                value: '{{ number_format($totalCustomers) }}',
                unit: '',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            }
        ],

        tableTitle: 'أفضل العملاء',
        tableHeaders: ['العميل', 'إجمالي المشتريات'],
        columnWidths: [45, 25],

        tableData: tableData,

        buttonSelector: '#exportTopClientsExcel',
        successMessage: 'تم تصدير التقرير بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
