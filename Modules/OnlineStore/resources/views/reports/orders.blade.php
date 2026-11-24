@extends('onlinestore::master')

@section('title', 'تقرير الطلبات')

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقرير الطلبات',
            'icon' => 'shopping-cart',
            'sub' => 'أعداد الطلبات ومبالغها حسب الحالة والفترة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => route('onlinestore.reports.index'),
                'الطلبات' => null,
            ],
        ])

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
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
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="all" {{ $status == 'all' ? 'selected' : '' }}>كل الحالات</option>
                                    <option value="pending" {{ $status == 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                                    <option value="processing" {{ $status == 'processing' ? 'selected' : '' }}>قيد المعالجة</option>
                                    <option value="shipped" {{ $status == 'shipped' ? 'selected' : '' }}>تم الشحن</option>
                                    <option value="completed" {{ $status == 'completed' ? 'selected' : '' }}>مكتمل</option>
                                    <option value="cancelled" {{ $status == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                                </select>
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
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">إجمالي الطلبات</span>
                <span class="stk-metric__value">{{ number_format($totalOrders) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">الطلبات المكتملة</span>
                <span class="stk-metric__value">{{ number_format($completedOrders) }}</span>
                <small class="text-success">{{ $conversionRate }}% معدل الإكمال</small>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">الطلبات المعلقة</span>
                <span class="stk-metric__value">{{ number_format($pendingOrders) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-check"></i></span>
                <span class="stk-metric__label">الطلبات المؤكدة</span>
                <span class="stk-metric__value">{{ number_format($confirmedOrders) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-settings"></i></span>
                <span class="stk-metric__label">قيد التجهيز</span>
                <span class="stk-metric__value">{{ number_format($processingOrders) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-truck"></i></span>
                <span class="stk-metric__label">الطلبات المشحونة</span>
                <span class="stk-metric__value">{{ number_format($shippedOrders) }}</span>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي مبالغ الطلبات</span>
                <span class="stk-metric__value">{{ number_format($totalOrdersAmount, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مبالغ الطلبات المكتملة</span>
                <span class="stk-metric__value">{{ number_format($completedOrdersAmount, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">مبالغ الطلبات المعلقة</span>
                <span class="stk-metric__value">{{ number_format($pendingOrdersAmount, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">مبالغ الطلبات الملغية</span>
                <span class="stk-metric__value">{{ number_format($cancelledOrdersAmount, 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-rotate-ccw"></i></span>
                <span class="stk-metric__label">مبالغ الطلبات المرتجعة</span>
                <span class="stk-metric__value">{{ number_format($returnedOrdersAmount, 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">مبالغ الطلبات المستردة</span>
                <span class="stk-metric__value">{{ number_format($refundedOrdersAmount, 2) }}</span>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                <span class="stk-metric__label">إجمالي الطلبات (بدون المرتجع والملغي)</span>
                <span class="stk-metric__value">{{ number_format($totalOrdersWithoutCancelledReturned) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي المبالغ (بدون المرتجع والملغي)</span>
                <span class="stk-metric__value">{{ number_format($totalAmountWithoutCancelledReturned, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">إجمالي الضرائب</span>
                <span class="stk-metric__value">{{ number_format($totalTaxAmount, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-truck"></i></span>
                <span class="stk-metric__label">إجمالي الشحن</span>
                <span class="stk-metric__value">{{ number_format($totalShippingAmount, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__label">إجمالي الخصومات</span>
                <span class="stk-metric__value">{{ number_format($totalDiscountAmount, 2) }}</span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفصيل الطلبات حسب الحالة</h6>
                <div>
                    <button type="button" id="printStatusBtn" class="btn btn-success">
                        <i class="fa fa-print"></i> طباعة
                    </button>
                    <button type="button" id="exportStatusExcel" class="btn btn-info">
                        <i class="fa fa-file-excel-o"></i> Excel
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="statusBreakdownTable">
                    <thead>
                        <tr>
                            <th>الحالة</th>
                            <th>عدد الطلبات</th>
                            <th>إجمالي المبالغ</th>
                            <th>متوسط الطلب</th>
                            <th>النسبة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="stk-state stk-state--warn">قيد الانتظار</span></td>
                            <td class="stk-num">{{ number_format($pendingOrders) }}</td>
                            <td class="stk-num">{{ number_format($pendingOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $pendingOrders > 0 ? number_format($pendingOrdersAmount / $pendingOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($pendingOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                        <tr>
                            <td><span class="stk-state stk-state--neutral">مؤكد</span></td>
                            <td class="stk-num">{{ number_format($confirmedOrders) }}</td>
                            <td class="stk-num">{{ number_format($confirmedOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $confirmedOrders > 0 ? number_format($confirmedOrdersAmount / $confirmedOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($confirmedOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                        <tr>
                            <td><span class="stk-state stk-state--neutral">قيد التجهيز</span></td>
                            <td class="stk-num">{{ number_format($processingOrders) }}</td>
                            <td class="stk-num">{{ number_format($processingOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $processingOrders > 0 ? number_format($processingOrdersAmount / $processingOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($processingOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                        <tr>
                            <td><span class="stk-state stk-state--neutral">تم الشحن</span></td>
                            <td class="stk-num">{{ number_format($shippedOrders) }}</td>
                            <td class="stk-num">{{ number_format($shippedOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $shippedOrders > 0 ? number_format($shippedOrdersAmount / $shippedOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($shippedOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                        <tr>
                            <td><span class="stk-state stk-state--on">تم التسليم</span></td>
                            <td class="stk-num">{{ number_format($deliveredOrders) }}</td>
                            <td class="stk-num">{{ number_format($deliveredOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $deliveredOrders > 0 ? number_format($deliveredOrdersAmount / $deliveredOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($deliveredOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                        <tr>
                            <td><span class="stk-state stk-state--off">ملغي</span></td>
                            <td class="stk-num">{{ number_format($cancelledOrders) }}</td>
                            <td class="stk-num">{{ number_format($cancelledOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $cancelledOrders > 0 ? number_format($cancelledOrdersAmount / $cancelledOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($cancelledOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                        <tr>
                            <td><span class="stk-state stk-state--off">مرتجع</span></td>
                            <td class="stk-num">{{ number_format($returnedOrders) }}</td>
                            <td class="stk-num">{{ number_format($returnedOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $returnedOrders > 0 ? number_format($returnedOrdersAmount / $returnedOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($returnedOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                        <tr>
                            <td><span class="stk-state stk-state--off">مسترد</span></td>
                            <td class="stk-num">{{ number_format($refundedOrders) }}</td>
                            <td class="stk-num">{{ number_format($refundedOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ $refundedOrders > 0 ? number_format($refundedOrdersAmount / $refundedOrders, 2) : '0.00' }}</td>
                            <td class="stk-num">{{ $totalOrders > 0 ? number_format(($refundedOrders / $totalOrders) * 100, 1) : '0.0' }}%</td>
                        </tr>
                    </tbody>
                    <tfoot class="stk-table-foot">
                        <tr class="fw-bold">
                            <td>الإجمالي</td>
                            <td class="stk-num">{{ number_format($totalOrders) }}</td>
                            <td class="stk-num">{{ number_format($totalOrdersAmount, 2) }}</td>
                            <td class="stk-num">{{ number_format($avgOrderValue, 2) }}</td>
                            <td class="stk-num">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-bar-chart-2"></i> توزيع الطلبات حسب الحالة</h6>
                    </div>
                    <div class="card-body">
                        <div id="statusChart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-credit-card"></i> طرق الدفع</h6>
                    </div>
                    <div class="card-body">
                        <div id="paymentChart" style="height: 300px;"></div>
                        <div class="stk-def mt-1">
                            @foreach ($ordersByPayment as $payment)
                                <div class="stk-def__row">
                                    <span>{{ $payment->payment_method ?? 'غير محدد' }}</span>
                                    <strong>{{ $payment->count }} طلب</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الطلبات</h6>
                <span class="badge badge-light-primary">{{ $orders->total() }} طلب</span>
                <div>
                    <button type="button" id="printOrdersBtn" class="btn btn-success">
                        <i class="fa fa-print"></i> طباعة
                    </button>
                    <button type="button" id="exportOrdersExcel" class="btn btn-info">
                        <i class="fa fa-file-excel-o"></i> Excel
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="ordersTable">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>المنتجات</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('online_orders.show', $order->id) }}" class="fw-bold">
                                        #{{ $order->order_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $order->client->full_name ?? 'عميل محذوف' }}</span>
                                    @if ($order->client)
                                        <br><small class="text-muted">{{ $order->client->phone }}</small>
                                    @endif
                                </td>
                                <td>{{ $order->items->count() }} منتج</td>
                                <td class="stk-num fw-bold">{{ number_format($order->total_amount, 2) }}</td>
                                <td>
                                    @php
                                        $statusLabels = [
                                            'pending' => 'قيد الانتظار',
                                            'processing' => 'قيد المعالجة',
                                            'shipped' => 'تم الشحن',
                                            'delivered' => 'تم التوصيل',
                                            'completed' => 'مكتمل',
                                            'cancelled' => 'ملغي',
                                        ];
                                        $statusValue = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
                                        $statusOn = in_array($statusValue, ['delivered', 'completed']);
                                        $statusOff = in_array($statusValue, ['cancelled']);
                                    @endphp
                                    <span class="stk-state {{ $statusOn ? 'stk-state--on' : ($statusOff ? 'stk-state--off' : 'stk-state--neutral') }}">
                                        {{ $statusLabels[$statusValue] ?? $statusValue }}
                                    </span>
                                </td>
                                <td class="stk-num">{{ $order->created_at->format('Y/m/d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
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
            <div class="card-body">
                {{ $orders->appends(request()->query())->links() }}
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
    console.log('Orders Report: Document Ready');

    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr-basic', { locale: 'ar', dateFormat: 'Y-m-d' });
    }

    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts not loaded!');
        return;
    }

    // البيانات المحولة من Controller
    var statusData = @json($ordersByStatusChart ?? []);
    var paymentData = @json($ordersByPaymentChart ?? []);

    console.log('Status Data:', statusData);
    console.log('Payment Data:', paymentData);

    var statusLabels = {
        'pending': 'قيد الانتظار',
        'confirmed': 'مؤكد',
        'processing': 'قيد التجهيز',
        'ready_for_shipping': 'جاهز للشحن',
        'shipped': 'تم الشحن',
        'out_for_delivery': 'في الطريق',
        'delivered': 'تم التسليم',
        'completed': 'مكتمل',
        'cancelled': 'ملغي',
        'returned': 'مرتجع',
        'refunded': 'مسترد'
    };

    var paymentLabels = {
        'cash': 'نقدي',
        'card': 'بطاقة',
        'bank_transfer': 'تحويل بنكي',
        'cod': 'الدفع عند الاستلام',
        'online': 'دفع إلكتروني',
        'credit_card': 'بطاقة ائتمان',
        'mada': 'مدى',
        'apple_pay': 'Apple Pay',
        'stc_pay': 'STC Pay'
    };

    // رسم توزيع الحالات
    var statusChartEl = document.querySelector("#statusChart");
    if (statusChartEl && statusData && statusData.length > 0) {
        var categories = statusData.map(function(s) {
            return statusLabels[s.status_value] || s.status_label || s.status_value || 'غير محدد';
        });
        var counts = statusData.map(function(s) { return s.count || 0; });
        var amounts = statusData.map(function(s) { return s.total_amount || 0; });

        new ApexCharts(statusChartEl, {
            series: [
                { name: 'عدد الطلبات', data: counts },
                { name: 'إجمالي المبالغ', data: amounts }
            ],
            chart: { type: 'bar', height: 350, toolbar: { show: false } },
            xaxis: { categories: categories },
            yaxis: [
                { title: { text: 'عدد الطلبات' } },
                { opposite: true, title: { text: 'المبالغ' } }
            ],
            colors: ['#7367f0', '#28c76f'],
            plotOptions: { bar: { borderRadius: 8, columnWidth: '50%' } },
            legend: { position: 'top' },
            tooltip: {
                y: { formatter: function(val) { return val ? val.toLocaleString('ar-SA') : '0'; } }
            }
        }).render();
    } else if (statusChartEl) {
        statusChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }

    // رسم طرق الدفع
    var paymentChartEl = document.querySelector("#paymentChart");
    if (paymentChartEl && paymentData && paymentData.length > 0) {
        var labels = paymentData.map(function(p) {
            return paymentLabels[p.payment_method] || p.payment_method || 'غير محدد';
        });
        var values = paymentData.map(function(p) { return p.count || 0; });

        new ApexCharts(paymentChartEl, {
            series: values,
            chart: { type: 'donut', height: 300 },
            labels: labels,
            colors: ['#28c76f', '#7367f0', '#ff9f43', '#00cfe8', '#ea5455'],
            legend: { position: 'bottom' },
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
                                    return w.globals.seriesTotals.reduce(function(a, b) { return a + b; }, 0) + ' طلب';
                                }
                            }
                        }
                    }
                }
            }
        }).render();
    } else if (paymentChartEl) {
        paymentChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
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

    // نص الخلية بلا مسافات زائدة (خلية العميل تحوي الاسم + الهاتف متداخلين)
    function cellText(cell) {
        return $(cell).text().trim().replace(/\s+/g, ' ');
    }

    @php
        $ordersStatusLabels = [
            'all' => 'كل الحالات',
            'pending' => 'قيد الانتظار',
            'processing' => 'قيد المعالجة',
            'shipped' => 'تم الشحن',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
        ];
        $ordersStatusLabel = $ordersStatusLabels[$status] ?? $status;
        $ordersPeriodText = 'من ' . $startDate->format('d/m/Y') . ' إلى ' . $endDate->format('d/m/Y');
    @endphp

    $(document).ready(function() {
        $('#printStatusBtn').click(printOrdersStatusReport);
        $('#exportStatusExcel').click(exportOrdersStatusReport);
        $('#printOrdersBtn').click(printOrdersListReport);
        $('#exportOrdersExcel').click(exportOrdersListReport);
    });

    // =====================================
    // تفصيل الطلبات حسب الحالة
    // =====================================
    function printOrdersStatusReport() {
        printTable({
            tableSelector: '#statusBreakdownTable tbody tr',
            tfootSelector: '#statusBreakdownTable tfoot',
            title: 'تقرير الطلبات — تفصيل حسب الحالة',
            infoCards: [
                { label: 'إجمالي الطلبات', value: '{{ number_format($totalOrders) }}' },
                { label: 'إجمالي المبالغ', value: '{{ number_format($totalOrdersAmount, 2) }} ' + window.AppCurrency.symbol, class: 'text-success' },
                { label: 'متوسط قيمة الطلب', value: '{{ number_format($avgOrderValue, 2) }} ' + window.AppCurrency.symbol },
                { label: 'معدل الإكمال', value: '{{ $conversionRate }}%' }
            ],
            filterInfo: {
                'الفترة': '{{ $ordersPeriodText }}',
                'الحالة': '{{ $ordersStatusLabel }}'
            },
            tableHeaders: ['الحالة', 'عدد الطلبات', 'إجمالي المبالغ', 'متوسط الطلب', 'النسبة'],
            columnWidths: ['30%', '17%', '20%', '18%', '15%'],
            windowWidth: 1100,
            windowHeight: 800
        });
    }

    function exportOrdersStatusReport() {
        var tableData = [];

        $('#statusBreakdownTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 5) return;

            tableData.push({
                values: [
                    cellText(cells[0]),
                    cellText(cells[1]),
                    formatNumber(cellText(cells[2])) + ' ' + window.AppCurrency.symbol,
                    formatNumber(cellText(cells[3])) + ' ' + window.AppCurrency.symbol,
                    cellText(cells[4])
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' }, bold: true },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success' },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_الطلبات_حسب_الحالة',
            mainTitle: 'تقرير الطلبات — تفصيل حسب الحالة',
            subTitle: 'التقارير - المتجر الإلكتروني',

            reportInfo: {
                'الفترة': '{{ $ordersPeriodText }}',
                'الحالة': '{{ $ordersStatusLabel }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي الطلبات',
                    value: '{{ number_format($totalOrders) }}',
                    titleColor: 'primary',
                    valueColor: 'primary',
                    backgroundColor: 'grayLight',
                    borderColor: 'primary'
                },
                {
                    title: 'إجمالي المبالغ',
                    value: '{{ number_format($totalOrdersAmount, 2) }}',
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                },
                {
                    title: 'متوسط قيمة الطلب',
                    value: '{{ number_format($avgOrderValue, 2) }}',
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'warning',
                    valueColor: 'warning',
                    backgroundColor: 'warningLight',
                    borderColor: 'warning'
                },
                {
                    title: 'معدل الإكمال',
                    value: '{{ $conversionRate }}',
                    unit: ' %',
                    titleColor: 'info',
                    valueColor: 'info',
                    backgroundColor: 'grayLight',
                    borderColor: 'info'
                }
            ],

            tableTitle: 'تفصيل الطلبات حسب الحالة',
            tableHeaders: ['الحالة', 'عدد الطلبات', 'إجمالي المبالغ', 'متوسط الطلب', 'النسبة'],
            columnWidths: [28, 16, 20, 18, 14],

            tableData: tableData,

            summaryRows: [
                {
                    label: 'الإجمالي',
                    values: [
                        'الإجمالي',
                        '{{ number_format($totalOrders) }}',
                        '{{ number_format($totalOrdersAmount, 2) }} ' + window.AppCurrency.symbol,
                        '{{ number_format($avgOrderValue, 2) }} ' + window.AppCurrency.symbol,
                        '100%'
                    ],
                    color: 'primary',
                    cellStyles: [
                        { bold: true },
                        { bold: true },
                        { bold: true, color: 'success' },
                        { bold: true },
                        { bold: true }
                    ]
                }
            ],

            buttonSelector: '#exportStatusExcel',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // قائمة الطلبات
    // =====================================
    function printOrdersListReport() {
        printTable({
            tableSelector: '#ordersTable tbody tr',
            title: 'تقرير الطلبات — القائمة التفصيلية',
            infoCards: [
                { label: 'إجمالي الطلبات', value: '{{ $orders->total() }}' },
                { label: 'الطلبات المكتملة', value: '{{ number_format($completedOrders) }}', class: 'text-success' },
                { label: 'الطلبات المعلقة', value: '{{ number_format($pendingOrders) }}', class: 'text-warning' },
                { label: 'الطلبات الملغية', value: '{{ number_format($cancelledOrders) }}', class: 'text-danger' }
            ],
            filterInfo: {
                'الفترة': '{{ $ordersPeriodText }}',
                'الحالة': '{{ $ordersStatusLabel }}'
            },
            tableHeaders: ['رقم الطلب', 'العميل', 'المنتجات', 'المبلغ', 'الحالة', 'التاريخ'],
            columnWidths: ['14%', '30%', '13%', '15%', '14%', '14%'],
            descriptionColumn: 2,
            windowWidth: 1300,
            windowHeight: 850
        });
    }

    function exportOrdersListReport() {
        var tableData = [];

        $('#ordersTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 6) return;

            tableData.push({
                values: [
                    cellText(cells[0]),
                    cellText(cells[1]),
                    cellText(cells[2]),
                    formatNumber(cellText(cells[3])) + ' ' + window.AppCurrency.symbol,
                    cellText(cells[4]),
                    cellText(cells[5])
                ],
                cellStyles: [
                    { alignment: { horizontal: 'center' }, bold: true },
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success', bold: true },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_الطلبات_التفصيلي',
            mainTitle: 'تقرير الطلبات — القائمة التفصيلية',
            subTitle: 'التقارير - المتجر الإلكتروني',

            reportInfo: {
                'الفترة': '{{ $ordersPeriodText }}',
                'الحالة': '{{ $ordersStatusLabel }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي الطلبات',
                    value: '{{ $orders->total() }}',
                    titleColor: 'primary',
                    valueColor: 'primary',
                    backgroundColor: 'grayLight',
                    borderColor: 'primary'
                },
                {
                    title: 'الطلبات المكتملة',
                    value: '{{ number_format($completedOrders) }}',
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                },
                {
                    title: 'الطلبات المعلقة',
                    value: '{{ number_format($pendingOrders) }}',
                    titleColor: 'warning',
                    valueColor: 'warning',
                    backgroundColor: 'warningLight',
                    borderColor: 'warning'
                },
                {
                    title: 'الطلبات الملغية',
                    value: '{{ number_format($cancelledOrders) }}',
                    titleColor: 'danger',
                    valueColor: 'danger',
                    backgroundColor: 'dangerLight',
                    borderColor: 'danger'
                }
            ],

            tableTitle: 'قائمة الطلبات',
            tableHeaders: ['رقم الطلب', 'العميل', 'المنتجات', 'المبلغ', 'الحالة', 'التاريخ'],
            columnWidths: [14, 30, 12, 16, 14, 16],

            tableData: tableData,

            buttonSelector: '#exportOrdersExcel',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }
</script>
@endsection
