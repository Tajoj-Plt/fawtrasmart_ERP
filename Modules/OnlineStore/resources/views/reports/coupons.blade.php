@extends('onlinestore::master')

@section('title', 'تقرير الكوبونات والخصومات')

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقرير الكوبونات والخصومات',
            'icon' => 'tag',
            'sub' => 'استخدام الكوبونات وحجم الخصومات الممنوحة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => route('onlinestore.reports.index'),
                'الكوبونات' => null,
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
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__label">إجمالي الكوبونات</span>
                <span class="stk-metric__value">{{ number_format($totalCoupons) }}</span>
                <small class="text-success">{{ $activeCoupons }} نشط</small>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">إجمالي الخصومات</span>
                <span class="stk-metric__value">{{ number_format($totalDiscount, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">طلبات بخصم</span>
                <span class="stk-metric__value">{{ number_format($ordersWithDiscount) }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart"></i></span>
                <span class="stk-metric__label">متوسط الخصم</span>
                <span class="stk-metric__value">{{ number_format($avgDiscount, 2) }}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> استخدام الكوبونات يومياً</h6>
                    </div>
                    <div class="card-body">
                        <div id="dailyUsageChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> توزيع الخصومات</h6>
                    </div>
                    <div class="card-body">
                        <div id="discountDistChart" style="height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-award"></i> أكثر الكوبونات استخداماً</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الكوبون</th>
                            <th>النوع</th>
                            <th>القيمة</th>
                            <th>مرات الاستخدام</th>
                            <th>إجمالي الخصم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topCoupons as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <span class="stk-num">{{ $item->coupon->code ?? 'محذوف' }}</span>
                                    @if ($item->coupon)
                                        <br><small class="text-muted">{{ $item->coupon->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->coupon)
                                        @if ($item->coupon->type == 'percentage')
                                            <span class="badge badge-light-info">نسبة مئوية</span>
                                        @else
                                            <span class="badge badge-light-success">مبلغ ثابت</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="stk-num">
                                    @if ($item->coupon)
                                        @if ($item->coupon->type == 'percentage')
                                            {{ $item->coupon->value }}%
                                        @else
                                            {{ number_format($item->coupon->value, 2) }} {{ \App\Support\CurrencySettings::symbol() }}
                                        @endif
                                    @endif
                                </td>
                                <td class="stk-num">
                                    <span class="fw-bold">{{ number_format($item->usage_count) }}</span> مرة
                                </td>
                                <td class="stk-num fw-bold text-danger">{{ number_format($item->total_discount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">لا توجد بيانات</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل استخدام الكوبونات</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>الكوبون</th>
                            <th>الاسم</th>
                            <th>النوع</th>
                            <th>مرات الاستخدام</th>
                            <th>إجمالي الخصم</th>
                            <th>متوسط الخصم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($couponUsages as $usage)
                            <tr>
                                <td><code>{{ $usage->coupon->code ?? 'محذوف' }}</code></td>
                                <td>{{ $usage->coupon->name ?? '-' }}</td>
                                <td>
                                    @if ($usage->coupon && $usage->coupon->type == 'percentage')
                                        {{ $usage->coupon->value }}%
                                    @elseif($usage->coupon)
                                        {{ number_format($usage->coupon->value, 2) }} {{ \App\Support\CurrencySettings::symbol() }}
                                    @endif
                                </td>
                                <td class="stk-num">{{ number_format($usage->usage_count) }}</td>
                                <td class="stk-num text-danger">{{ number_format($usage->total_discount, 2) }}</td>
                                <td class="stk-num">{{ number_format($usage->usage_count > 0 ? $usage->total_discount / $usage->usage_count : 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-tag"></i></span>
                                        <h6 class="stk-empty__title">لا توجد بيانات</h6>
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
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/l10n/ar.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    var dailyData = @json($dailyCouponUsageChart ?? []);
    var topCouponsData = @json($topCouponsChart ?? []);

    console.log('Daily Coupon Data:', dailyData);
    console.log('Top Coupons Data:', topCouponsData);

    // الاستخدام اليومي
    var dailyUsageChartEl = document.querySelector("#dailyUsageChart");
    if (dailyUsageChartEl && dailyData && dailyData.length > 0) {
        new ApexCharts(dailyUsageChartEl, {
            series: [{
                name: 'مرات الاستخدام',
                type: 'column',
                data: dailyData.map(function(d) { return d.usage_count || 0; })
            }, {
                name: 'الخصومات',
                type: 'line',
                data: dailyData.map(function(d) { return d.total_discount || 0; })
            }],
            chart: { height: 300, toolbar: { show: false } },
            xaxis: {
                categories: dailyData.map(function(d) { return d.date; })
            },
            yaxis: [{
                title: { text: 'الاستخدام' }
            }, {
                opposite: true,
                title: { text: 'الخصومات' }
            }],
            colors: ['#7367f0', '#ea5455'],
            stroke: { width: [0, 3] },
            tooltip: {
                y: {
                    formatter: function(val, { seriesIndex }) {
                        if (seriesIndex === 1) {
                            return val ? val.toLocaleString('ar-SA') + ' ' + window.AppCurrency.symbol : '0 ' + window.AppCurrency.symbol;
                        }
                        return val + ' مرة';
                    }
                }
            }
        }).render();
    } else if (dailyUsageChartEl) {
        dailyUsageChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }

    // توزيع الخصومات
    var discountDistChartEl = document.querySelector("#discountDistChart");
    if (discountDistChartEl && topCouponsData && topCouponsData.length > 0) {
        var sliced = topCouponsData.slice(0, 5);
        new ApexCharts(discountDistChartEl, {
            series: sliced.map(function(c) { return c.total_discount || 0; }),
            chart: { type: 'donut', height: 280 },
            labels: sliced.map(function(c) { return c.code || 'محذوف'; }),
            colors: ['#7367f0', '#28c76f', '#ff9f43', '#00cfe8', '#ea5455'],
            legend: { position: 'bottom' },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val ? val.toLocaleString('ar-SA') + ' ' + window.AppCurrency.symbol : '0 ' + window.AppCurrency.symbol;
                    }
                }
            }
        }).render();
    } else if (discountDistChartEl) {
        discountDistChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }
});
</script>
@endsection
