@extends('onlinestore::master')

@section('title', 'تقرير العملاء')

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
<style>
    .client-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
    }
</style>
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقرير العملاء',
            'icon' => 'users',
            'sub' => 'عملاء المتجر الجدد والعائدون وأكثرهم إنفاقاً',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => route('onlinestore.reports.index'),
                'العملاء' => null,
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
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-user-plus"></i></span>
                <span class="stk-metric__label">عملاء جدد</span>
                <span class="stk-metric__value">{{ number_format($newClients) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">عملاء نشطون</span>
                <span class="stk-metric__value">{{ number_format($activeClients) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-refresh-cw"></i></span>
                <span class="stk-metric__label">معدل الاحتفاظ</span>
                <span class="stk-metric__value">{{ $retentionRate }}%</span>
                <small class="text-muted">{{ $returningCustomers }} عائدون</small>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">متوسط الإنفاق</span>
                <span class="stk-metric__value">{{ number_format($avgSpendingPerClient, 2) }}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> العملاء الجدد يومياً</h6>
                    </div>
                    <div class="card-body">
                        <div id="newClientsChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> الجدد vs المتكررين</h6>
                    </div>
                    <div class="card-body">
                        <div id="clientTypeChart" style="height: 250px;"></div>
                        <div class="mt-1 text-center">
                            <span class="badge badge-light-primary mr-1">جدد: {{ $firstTimeCustomers }}</span>
                            <span class="badge badge-light-success">متكررين: {{ $returningCustomers }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-award"></i> أفضل العملاء</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العميل</th>
                                    <th>الطلبات</th>
                                    <th>متوسط الطلب</th>
                                    <th>إجمالي الإنفاق</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topClients as $index => $item)
                                    <tr>
                                        <td>
                                            @if ($index < 3)
                                                <span class="badge badge-{{ $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'info') }}">{{ $index + 1 }}</span>
                                            @else
                                                {{ $index + 1 }}
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="client-avatar bg-light-primary mr-1">
                                                    {{ mb_substr($item->client->full_name ?? 'ع', 0, 1) }}
                                                </div>
                                                <div>
                                                    <span class="fw-bold">{{ $item->client->full_name ?? 'عميل محذوف' }}</span>
                                                    @if ($item->client)
                                                        <br><small class="text-muted">{{ $item->client->email ?? $item->client->phone }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="stk-num">{{ number_format($item->orders_count) }}</td>
                                        <td class="stk-num">{{ number_format($item->avg_order, 2) }}</td>
                                        <td class="stk-num fw-bold text-success">{{ number_format($item->total_spent, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">لا توجد بيانات</td>
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
                        <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> العملاء حسب المدينة</h6>
                    </div>
                    <div class="card-body">
                        @forelse($clientsByCity as $city)
                            <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                <div>
                                    <span class="fw-bold">{{ $city->city ?? 'غير محدد' }}</span>
                                    <br><small class="text-muted">{{ $city->clients_count }} عميل - {{ $city->orders_count }} طلب</small>
                                </div>
                                <span class="text-success fw-bold">{{ number_format($city->total_revenue, 2) }}</span>
                            </div>
                        @empty
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-map-pin"></i></span>
                                <p class="stk-empty__hint mb-0">لا توجد بيانات</p>
                            </div>
                        @endforelse
                    </div>
                </div>
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

    // البيانات المحولة من Controller - للعملاء
    var newClientsData = @json($newClientsByDayChart ?? []);
    var firstTimeCustomers = {{ $firstTimeCustomers ?? 0 }};
    var returningCustomers = {{ $returningCustomers ?? 0 }};

    console.log('New Clients Data:', newClientsData);
    console.log('First Time:', firstTimeCustomers, 'Returning:', returningCustomers);

    // رسم العملاء الجدد يومياً
    var newClientsChartEl = document.querySelector("#newClientsChart");
    if (newClientsChartEl && newClientsData && newClientsData.length > 0) {
        new ApexCharts(newClientsChartEl, {
            series: [{
                name: 'عملاء جدد',
                data: newClientsData.map(function(d) {
                    return { x: d.date, y: d.count };
                })
            }],
            chart: { type: 'area', height: 300, toolbar: { show: false } },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2 }
            },
            xaxis: { type: 'datetime' },
            colors: ['#28c76f'],
            tooltip: {
                x: { format: 'yyyy-MM-dd' }
            }
        }).render();
    } else if (newClientsChartEl) {
        newClientsChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }

    // رسم الجدد vs المتكررين
    var clientTypeChartEl = document.querySelector("#clientTypeChart");
    if (clientTypeChartEl && (firstTimeCustomers > 0 || returningCustomers > 0)) {
        new ApexCharts(clientTypeChartEl, {
            series: [firstTimeCustomers, returningCustomers],
            chart: { type: 'donut', height: 250 },
            labels: ['عملاء جدد', 'عملاء متكررين'],
            colors: ['#7367f0', '#28c76f'],
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
                                    return w.globals.seriesTotals.reduce(function(a, b) { return a + b; }, 0);
                                }
                            }
                        }
                    }
                }
            }
        }).render();
    } else if (clientTypeChartEl) {
        clientTypeChartEl.innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات</div>';
    }
});
</script>
@endsection
