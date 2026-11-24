@extends('onlinestore::master')

@section('title', 'لوحة تحكم المتجر الإلكتروني')

@section('css')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'لوحة تحكم المتجر الإلكتروني',
            'icon' => 'shopping-cart',
            'sub' => 'نظرة عامة على الطلبات والمبيعات والمنتجات',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'المتجر الإلكتروني' => null,
            ],
        ])

        {{-- «ابدأ من هنا»: خطوات تشغيل المتجر بالترتيب وحالة كلٍّ منها من النظام --}}
        @include('onlinestore::dashboard.onlinestore._start-guide')

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> الفترة الزمنية</h6>
                </div>
                <form method="GET">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="start_date">من تاريخ</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="end_date">إلى تاريخ</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                            </div>
                        </div>
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
                <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                <span class="stk-metric__label">إجمالي الطلبات</span>
                <span class="stk-metric__value">{{ number_format($totalOrders) }}</span>
                <div class="mt-1">
                    <span class="badge badge-light-warning mr-1">قيد الانتظار: {{ $pendingOrders }}</span>
                    <span class="badge badge-light-success">مكتملة: {{ $completedOrders }}</span>
                </div>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي المبيعات</span>
                <span class="stk-metric__value">{{ number_format($totalRevenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                <small class="text-muted">متوسط الطلب: {{ number_format($averageOrderValue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</small>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">عملاء المتجر الإلكتروني</span>
                <span class="stk-metric__value">{{ number_format($totalStoreClients ?? $totalCustomers) }}</span>
                <div class="mt-1">
                    <span class="badge badge-light-success mr-1">جدد: {{ $newCustomers }}</span>
                    <span class="badge badge-light-info">طلبوا في الفترة: {{ $totalCustomers }}</span>
                </div>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">متوسط التقييم</span>
                <span class="stk-metric__value">{{ number_format($averageRating, 1) }} <i class="feather icon-star"></i></span>
                <div class="mt-1">
                    <span class="badge badge-light-primary mr-1">إجمالي: {{ $totalReviews }}</span>
                    <span class="badge badge-light-warning">بانتظار: {{ $pendingReviews }}</span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> الطلبات والمبيعات (آخر 7 أيام)</h6>
                    </div>
                    <div class="card-body">
                        <div id="ordersChart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> توزيع حالات الطلبات</h6>
                    </div>
                    <div class="card-body">
                        <div id="statusPieChart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-package"></i> المنتجات</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>إجمالي المنتجات</span>
                                <strong>{{ $totalProducts ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>المنتجات النشطة</span>
                                <strong class="text-success">{{ $activeProducts ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>متاحة أونلاين</span>
                                <strong class="text-primary">{{ $onlineProducts ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>منتجات مميزة</span>
                                <strong class="text-warning">{{ $featuredProducts ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>متاحة نقطة البيع</span>
                                <strong class="text-info">{{ $posProducts ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>مخزون منخفض</span>
                                <strong class="text-danger">{{ $lowStockProducts ?? 0 }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-folder"></i> الأصناف</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>إجمالي الأصناف</span>
                                <strong>{{ $totalCategories ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>أصناف رئيسية</span>
                                <strong class="text-primary">{{ $mainCategories ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>أصناف فرعية</span>
                                <strong class="text-info">{{ $subCategories ?? 0 }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>أصناف بها منتجات</span>
                                <strong class="text-success">{{ $categoriesWithProducts ?? 0 }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-shopping-cart"></i> سلة التسوق</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>السلات النشطة</span>
                                <strong>{{ $activeCartsCount }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>السلات المهجورة</span>
                                <strong class="text-danger">{{ $abandonedCartsCount }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>متوسط المنتجات/سلة</span>
                                <strong>{{ number_format($avgCartItems, 1) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-tag"></i> الكوبونات</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>الكوبونات النشطة</span>
                                <strong>{{ $activeCoupons }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>عدد الاستخدامات</span>
                                <strong class="text-primary">{{ $totalCouponUsage }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>إجمالي الخصم</span>
                                <strong class="text-danger">{{ number_format($totalDiscountGiven, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> العلامات التجارية</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>إجمالي العلامات</span>
                                <strong>{{ $totalBrands }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>العلامات النشطة</span>
                                <strong class="text-success">{{ $activeBrands }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>العلامات المميزة</span>
                                <strong class="text-warning">{{ $featuredBrands }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-heart"></i> قائمة الأمنيات</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>إجمالي المنتجات</span>
                                <strong>{{ $totalWishlistItems }}</strong>
                            </div>
                            <div class="stk-def__row">
                                <span>المستخدمون</span>
                                <strong class="text-primary">{{ $uniqueWishlistUsers }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-truck"></i> طرق الشحن</h6>
                    </div>
                    <div class="card-body">
                        <div class="stk-def">
                            <div class="stk-def__row">
                                <span>طرق الشحن النشطة</span>
                                <strong class="text-success">{{ $totalShippingMethods }}</strong>
                            </div>
                        </div>
                        <hr>
                        <h6 class="mb-2">استخدام طرق الشحن:</h6>
                        @forelse($shippingMethodsUsage as $method)
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ $method->shippingMethod->name ?? 'غير محدد' }}</span>
                                <span class="badge badge-light-info">{{ $method->orders_count }}</span>
                            </div>
                        @empty
                            <p class="text-muted">لا توجد بيانات</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> أكثر المنتجات مبيعاً</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المنتج</th>
                            <th>الكمية المباعة</th>
                            <th>إجمالي المبيعات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topSellingProducts as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->product_name }}</td>
                                <td><span class="badge badge-light-primary">{{ $item->total_sold }}</span></td>
                                <td><strong>{{ number_format($item->total_revenue, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-package"></i></span>
                                        <h6 class="stk-empty__title">لا توجد بيانات</h6>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> آخر الطلبات</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>طريقة الشحن</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td><strong>#{{ $order->id }}</strong></td>
                                <td>{{ $order->client ? $order->client->display_name : ($order->shipping_name ?: 'غير محدد') }}</td>
                                <td>{{ $order->shippingMethod->name ?? 'غير محدد' }}</td>
                                <td><strong>{{ $order->formatMoney($order->total_amount) }}</strong></td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'processing' => 'info',
                                            'shipped' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                        ];
                                        $statusValue = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
                                        $color = $statusColors[$statusValue] ?? 'secondary';
                                    @endphp
                                    <span class="badge badge-light-{{ $color }}">{{ $statusValue }}</span>
                                </td>
                                <td class="stk-num">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-outline-primary" title="عرض">
                                        <i class="feather icon-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-shopping-cart"></i></span>
                                        <h6 class="stk-empty__title">لا توجد طلبات</h6>
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
<script>
$(document).ready(function() {
    // رسم بياني للطلبات والمبيعات
    var ordersChartOptions = {
        series: [{
            name: 'عدد الطلبات',
            data: @json($ordersChartData)
        }, {
            name: 'المبيعات ({{ \App\Support\CurrencySettings::symbol() }})',
            data: @json($revenueChartData)
        }],
        chart: {
            type: 'line',
            height: 350,
            fontFamily: 'Cairo, Arial, sans-serif',
            toolbar: {
                show: true
            }
        },
        colors: ['#7367f0', '#28c76f'],
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        xaxis: {
            categories: @json($labels)
        },
        yaxis: [{
            title: {
                text: 'عدد الطلبات'
            }
        }, {
            opposite: true,
            title: {
                text: 'المبيعات ({{ \App\Support\CurrencySettings::symbol() }})'
            }
        }],
        legend: {
            position: 'top'
        },
        grid: {
            borderColor: '#f1f1f1'
        }
    };

    var ordersChart = new ApexCharts(document.querySelector("#ordersChart"), ordersChartOptions);
    ordersChart.render();

    // رسم بياني دائري لحالات الطلبات
    var statusData = @json($orderStatusDistribution);
    var statusLabels = Object.keys(statusData);
    var statusValues = Object.values(statusData);

    var statusPieOptions = {
        series: statusValues,
        chart: {
            type: 'donut',
            height: 350,
            fontFamily: 'Cairo, Arial, sans-serif'
        },
        labels: statusLabels,
        colors: ['#ff9f43', '#00cfe8', '#7367f0', '#28c76f', '#ea5455'],
        legend: {
            position: 'bottom'
        },
        dataLabels: {
            enabled: true
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true
                        },
                        value: {
                            show: true
                        },
                        total: {
                            show: true,
                            label: 'الإجمالي',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                            }
                        }
                    }
                }
            }
        }
    };

    var statusPieChart = new ApexCharts(document.querySelector("#statusPieChart"), statusPieOptions);
    statusPieChart.render();
});
</script>
@endsection
