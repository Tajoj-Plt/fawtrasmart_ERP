@extends('onlinestore::master')

@section('title', 'تقرير المبيعات المالي')

@section('css')
<link rel="stylesheet" type="text/css" href="{{ asset('app-assets/vendors/css/pickers/flatpickr/flatpickr.min.css') }}">
@endsection

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تقرير المبيعات المالي',
            'icon' => 'dollar-sign',
            'sub' => 'أرقام المبيعات والإيرادات والضرائب حسب الفترة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التقارير' => route('onlinestore.reports.index'),
                'المبيعات' => null,
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

        <div class="alert alert-info">
            <strong>ملاحظة:</strong> الإيراد يُحتسب لحظة إنشاء الطلب (كما يُقيَّد في الدفاتر) — يشمل الطلبات قيد الانتظار،
            ويُستبعد الملغي كاملاً، ويُطرح ما رُدّ من الطلبات المستردّة. «إجمالي المبالغ» في البطاقة الأولى قبل استبعاد الملغي والمردود.
        </div>

        {{-- المطابقة مع القيود: نفس الطلبات، من دفتر اليومية --}}
        @isset($reconciliation)
            @php $fmt = fn ($v) => \App\Support\CurrencySettings::format($v); @endphp
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0"><i class="feather icon-check-square"></i> صافي المبيعات والمطابقة مع القيود المحاسبية</h4>
                    @if (abs($reconciliation['difference']) < 0.01)
                        <span class="badge badge-light-success">مطابق للقيود</span>
                    @else
                        <span class="badge badge-light-danger">فرق {{ $fmt($reconciliation['difference']) }}</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0" id="reconciliationTable">
                            <thead>
                                <tr><th>البند</th><th class="text-center">من الطلبات</th><th class="text-center">من القيود</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>إجمالي مبالغ الطلبات (قبل الإلغاء والردّ)</td><td class="text-center stk-num">{{ $fmt($reconciliation['gross']) }}</td><td class="text-center text-muted">—</td></tr>
                                <tr><td>(−) طلبات ملغاة ({{ $reconciliation['cancelled_count'] }})</td><td class="text-center stk-num text-danger">{{ $fmt($reconciliation['cancelled']) }}</td><td class="text-center text-muted">—</td></tr>
                                <tr><td>(−) مردودات ومبالغ مستردّة</td><td class="text-center stk-num text-danger">{{ $fmt($reconciliation['refunds']) }}</td><td class="text-center text-muted">—</td></tr>
                                <tr><td>إيراد المبيعات والشحن (صافٍ من الضريبة والمردودات)</td><td class="text-center text-muted">—</td><td class="text-center stk-num">{{ $fmt($reconciliation['revenue']) }}</td></tr>
                                <tr><td>(+) ضريبة القيمة المضافة المحصّلة</td><td class="text-center text-muted">—</td><td class="text-center stk-num">{{ $fmt($reconciliation['vat']) }}</td></tr>
                                <tr><td>(+) مستحق شركات الشحن</td><td class="text-center text-muted">—</td><td class="text-center stk-num">{{ $fmt($reconciliation['shipping_payable']) }}</td></tr>
                                <tr><td>(−) خصم مسموح به (كوبونات)</td><td class="text-center text-muted">—</td><td class="text-center stk-num text-danger">{{ $fmt($reconciliation['discount']) }}</td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th>صافي المبيعات (شامل الضريبة والشحن)</th>
                                    <th class="text-center stk-num">{{ $fmt($reconciliation['report_net']) }}</th>
                                    <th class="text-center stk-num">{{ $fmt($reconciliation['journal_net']) }}</th>
                                </tr>
                                <tr>
                                    <td>منه لم يُحصَّل بعد (دفع عند الاستلام / تحويل)</td>
                                    <td class="text-center stk-num">{{ $fmt($reconciliation['uncollected']) }}</td>
                                    <td class="text-center text-muted">ذمّة العميل النقدي</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endisset

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">إجمالي الطلبات</span>
                <span class="stk-metric__value">{{ number_format($totalAllOrders) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الإيرادات</span>
                <span class="stk-metric__value">{{ number_format($totalAllRevenue, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">إجمالي الضرائب</span>
                <span class="stk-metric__value">{{ number_format($totalSales->tax ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart-2"></i></span>
                <span class="stk-metric__label">نسبة الضرائب</span>
                <span class="stk-metric__value">{{ $totalSales->revenue > 0 ? number_format(($totalSales->tax / $totalSales->revenue) * 100, 2) : '0.00' }}%</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-truck"></i></span>
                <span class="stk-metric__label">الشحن</span>
                <span class="stk-metric__value">{{ number_format($totalSales->shipping ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__label">الخصومات</span>
                <span class="stk-metric__value">{{ number_format($totalSales->discount ?? 0, 2) }}</span>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">الإيرادات (بدون المرتجع)</span>
                <span class="stk-metric__value">{{ number_format($totalSalesWithoutReturned->revenue ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--warning">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">متوسط الشحن</span>
                <span class="stk-metric__value">{{ number_format($additionalStats->avg_shipping ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__label">متوسط الخصم</span>
                <span class="stk-metric__value">{{ number_format($additionalStats->avg_discount ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">متوسط الضريبة</span>
                <span class="stk-metric__value">{{ number_format($additionalStats->avg_tax ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                <span class="stk-metric__label">متوسط الطلب</span>
                <span class="stk-metric__value">{{ number_format($totalSales->avg_order ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">الطلبات المرتجعة</span>
                <span class="stk-metric__value">{{ number_format($totalSalesWithoutReturned->orders - $totalSales->orders) }}</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل المبالغ حسب الحالة</h6>
                        <div class="btn-group">
                            <button type="button" id="printStatusAmounts" class="btn btn-success btn-sm"><i class="fa fa-print"></i> طباعة</button>
                            <button type="button" id="exportStatusAmounts" class="btn btn-info btn-sm"><i class="fa fa-file-excel-o"></i> Excel</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="statusAmountsTable">
                            <thead>
                                <tr>
                                    <th>الحالة</th>
                                    <th>الطلبات</th>
                                    <th>المجموع</th>
                                    <th>المتوسط</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($amountsByStatus as $amount)
                                    <tr>
                                        <td>{{ $amount->status instanceof \App\Enums\OrderStatus ? $amount->status->label() : $amount->status }}</td>
                                        <td class="stk-num">{{ number_format($amount->orders) }}</td>
                                        <td class="stk-num">{{ number_format($amount->total_amount, 2) }}</td>
                                        <td class="stk-num">{{ number_format($amount->avg_amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل الأعداد حسب الحالة</h6>
                        <div class="btn-group">
                            <button type="button" id="printStatusCounts" class="btn btn-success btn-sm"><i class="fa fa-print"></i> طباعة</button>
                            <button type="button" id="exportStatusCounts" class="btn btn-info btn-sm"><i class="fa fa-file-excel-o"></i> Excel</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="statusCountsTable">
                            <thead>
                                <tr>
                                    <th>الحالة</th>
                                    <th>الطلبات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($countsByStatus as $count)
                                    <tr>
                                        <td>{{ $count->status instanceof \App\Enums\OrderStatus ? $count->status->label() : $count->status }}</td>
                                        <td class="stk-num">{{ number_format($count->orders) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">لا توجد بيانات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> أعلى المدن</h6>
                        <div class="btn-group">
                            <button type="button" id="printCitySales" class="btn btn-success btn-sm"><i class="fa fa-print"></i> طباعة</button>
                            <button type="button" id="exportCitySales" class="btn btn-info btn-sm"><i class="fa fa-file-excel-o"></i> Excel</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="citySalesTable">
                            <thead>
                                <tr>
                                    <th>المدينة</th>
                                    <th>الطلبات</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salesByCity as $city)
                                    <tr>
                                        <td>{{ $city->city }}</td>
                                        <td class="stk-num">{{ number_format($city->orders) }}</td>
                                        <td class="stk-num">{{ number_format($city->revenue, 2) }}</td>
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

        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-credit-card"></i> التوزيع حسب طريقة الدفع</h6>
                        <div class="btn-group">
                            <button type="button" id="printPaymentMethod" class="btn btn-success btn-sm"><i class="fa fa-print"></i> طباعة</button>
                            <button type="button" id="exportPaymentMethod" class="btn btn-info btn-sm"><i class="fa fa-file-excel-o"></i> Excel</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="paymentMethodTable">
                            <thead>
                                <tr>
                                    <th>الطريقة</th>
                                    <th>الطلبات</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salesByPaymentMethod as $payment)
                                    <tr>
                                        <td>{{ $payment->payment_method instanceof \BackedEnum ? $payment->payment_method->value : $payment->payment_method }}</td>
                                        <td class="stk-num">{{ number_format($payment->orders) }}</td>
                                        <td class="stk-num">{{ number_format($payment->revenue, 2) }}</td>
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
            <div class="col-lg-6 col-12">
                <div class="card stk-table-card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-truck"></i> التوزيع حسب طريقة الشحن</h6>
                        <div class="btn-group">
                            <button type="button" id="printShippingMethod" class="btn btn-success btn-sm"><i class="fa fa-print"></i> طباعة</button>
                            <button type="button" id="exportShippingMethod" class="btn btn-info btn-sm"><i class="fa fa-file-excel-o"></i> Excel</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="shippingMethodTable">
                            <thead>
                                <tr>
                                    <th>الطريقة</th>
                                    <th>الطلبات</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salesByShippingMethod as $shipping)
                                    <tr>
                                        <td>{{ $shipping->shippingMethod->name ?? 'غير محدد' }}</td>
                                        <td class="stk-num">{{ number_format($shipping->orders) }}</td>
                                        <td class="stk-num">{{ number_format($shipping->revenue, 2) }}</td>
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

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-trending-up"></i> المبيعات اليومية</h6>
                    </div>
                    <div class="card-body">
                        <div id="dailySalesChart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> المبيعات حسب طريقة الدفع</h6>
                    </div>
                    <div class="card-body">
                        <div id="paymentChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-calendar"></i> المبيعات حسب أيام الأسبوع</h6>
                    </div>
                    <div class="card-body">
                        <div id="daysChart" style="height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> المبيعات حسب الساعات</h6>
                    </div>
                    <div class="card-body">
                        <div id="hoursChart" style="height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل المبيعات اليومية</h6>
                <div class="btn-group">
                    <button type="button" id="printDailySales" class="btn btn-success btn-sm"><i class="fa fa-print"></i> طباعة</button>
                    <button type="button" id="exportDailySales" class="btn btn-info btn-sm"><i class="fa fa-file-excel-o"></i> Excel</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dailySalesTable">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>عدد الطلبات</th>
                            <th>المبيعات الصافية</th>
                            <th>الضرائب</th>
                            <th>الشحن</th>
                            <th>الخصومات</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailySales as $day)
                            <tr>
                                <td class="stk-num">{{ \Carbon\Carbon::parse($day->date)->format('Y/m/d') }}</td>
                                <td class="stk-num">{{ number_format($day->orders) }}</td>
                                <td class="stk-num">{{ number_format($day->subtotal, 2) }}</td>
                                <td class="stk-num">{{ number_format($day->tax, 2) }}</td>
                                <td class="stk-num">{{ number_format($day->shipping, 2) }}</td>
                                <td class="stk-num text-danger">{{ number_format($day->discount, 2) }}</td>
                                <td class="stk-num fw-bold text-success">{{ number_format($day->revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-bar-chart-2"></i></span>
                                        <h6 class="stk-empty__title">لا توجد بيانات</h6>
                                        <p class="stk-empty__hint">لا توجد مبيعات في هذه الفترة.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($dailySales->count() > 0)
                        <tfoot class="stk-table-foot" id="dailySalesTableFoot">
                            <tr class="fw-bold">
                                <td>الإجمالي</td>
                                <td class="stk-num">{{ number_format($totalSales->orders ?? 0) }}</td>
                                <td class="stk-num">{{ number_format($totalSales->subtotal ?? 0, 2) }}</td>
                                <td class="stk-num">{{ number_format($totalSales->tax ?? 0, 2) }}</td>
                                <td class="stk-num">{{ number_format($totalSales->shipping ?? 0, 2) }}</td>
                                <td class="stk-num text-danger">{{ number_format($totalSales->discount ?? 0, 2) }}</td>
                                <td class="stk-num text-success">{{ number_format($totalSales->revenue ?? 0, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('app-assets/vendors/js/pickers/flatpickr/l10n/ar.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>

<script>
    function formatNumber(n, decimals = 2) {
        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    $(document).ready(function() {
        // تفاصيل المبالغ حسب الحالة
        $('#printStatusAmounts').click(function() { printStatusAmountsReport(); });
        $('#exportStatusAmounts').click(function() { exportStatusAmountsReport(); });

        // تفاصيل الأعداد حسب الحالة
        $('#printStatusCounts').click(function() { printStatusCountsReport(); });
        $('#exportStatusCounts').click(function() { exportStatusCountsReport(); });

        // أعلى المدن
        $('#printCitySales').click(function() { printCitySalesReport(); });
        $('#exportCitySales').click(function() { exportCitySalesReport(); });

        // التوزيع حسب طريقة الدفع
        $('#printPaymentMethod').click(function() { printPaymentMethodReport(); });
        $('#exportPaymentMethod').click(function() { exportPaymentMethodReport(); });

        // التوزيع حسب طريقة الشحن
        $('#printShippingMethod').click(function() { printShippingMethodReport(); });
        $('#exportShippingMethod').click(function() { exportShippingMethodReport(); });

        // تفاصيل المبيعات اليومية
        $('#printDailySales').click(function() { printDailySalesReport(); });
        $('#exportDailySales').click(function() { exportDailySalesReport(); });
    });

    // =====================================
    // تفاصيل المبالغ حسب الحالة
    // =====================================
    function printStatusAmountsReport() {
        printTable({
            tableSelector: '#statusAmountsTable tbody tr',
            title: 'تقرير تفاصيل المبالغ حسب الحالة',
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['الحالة', 'الطلبات', 'المجموع (' + window.AppCurrency.symbol + ')', 'المتوسط (' + window.AppCurrency.symbol + ')'],
            columnWidths: ['30%', '20%', '25%', '25%'],
            windowWidth: 900,
            windowHeight: 700
        });
    }

    function exportStatusAmountsReport() {
        var tableData = [];
        $('#statusAmountsTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 4) return;
            tableData.push({
                values: [
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim(),
                    $(cells[2]).text().trim(),
                    $(cells[3]).text().trim()
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_تفاصيل_المبالغ_حسب_الحالة',
            mainTitle: 'تقرير تفاصيل المبالغ حسب الحالة',
            subTitle: 'تقرير المبيعات المالي - المتجر الإلكتروني',
            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },
            tableTitle: 'تفاصيل المبالغ حسب الحالة',
            tableHeaders: ['الحالة', 'الطلبات', 'المجموع (' + window.AppCurrency.symbol + ')', 'المتوسط (' + window.AppCurrency.symbol + ')'],
            columnWidths: [25, 15, 20, 20],
            tableData: tableData,
            buttonSelector: '#exportStatusAmounts',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // تفاصيل الأعداد حسب الحالة
    // =====================================
    function printStatusCountsReport() {
        printTable({
            tableSelector: '#statusCountsTable tbody tr',
            title: 'تقرير تفاصيل الأعداد حسب الحالة',
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['الحالة', 'الطلبات'],
            columnWidths: ['60%', '40%'],
            windowWidth: 800,
            windowHeight: 650
        });
    }

    function exportStatusCountsReport() {
        var tableData = [];
        $('#statusCountsTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 2) return;
            tableData.push({
                values: [
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim()
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_تفاصيل_الأعداد_حسب_الحالة',
            mainTitle: 'تقرير تفاصيل الأعداد حسب الحالة',
            subTitle: 'تقرير المبيعات المالي - المتجر الإلكتروني',
            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },
            tableTitle: 'تفاصيل الأعداد حسب الحالة',
            tableHeaders: ['الحالة', 'الطلبات'],
            columnWidths: [35, 20],
            tableData: tableData,
            buttonSelector: '#exportStatusCounts',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // أعلى المدن
    // =====================================
    function printCitySalesReport() {
        printTable({
            tableSelector: '#citySalesTable tbody tr',
            title: 'تقرير أعلى المدن مبيعاً',
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['المدينة', 'الطلبات', 'الإيرادات (' + window.AppCurrency.symbol + ')'],
            columnWidths: ['40%', '25%', '35%'],
            windowWidth: 900,
            windowHeight: 700
        });
    }

    function exportCitySalesReport() {
        var tableData = [];
        $('#citySalesTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 3) return;
            tableData.push({
                values: [
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim(),
                    $(cells[2]).text().trim()
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success' }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_أعلى_المدن_مبيعاً',
            mainTitle: 'تقرير أعلى المدن مبيعاً',
            subTitle: 'تقرير المبيعات المالي - المتجر الإلكتروني',
            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },
            tableTitle: 'أعلى المدن مبيعاً',
            tableHeaders: ['المدينة', 'الطلبات', 'الإيرادات (' + window.AppCurrency.symbol + ')'],
            columnWidths: [30, 15, 20],
            tableData: tableData,
            buttonSelector: '#exportCitySales',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // التوزيع حسب طريقة الدفع
    // =====================================
    function printPaymentMethodReport() {
        printTable({
            tableSelector: '#paymentMethodTable tbody tr',
            title: 'تقرير التوزيع حسب طريقة الدفع',
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['الطريقة', 'الطلبات', 'الإيرادات (' + window.AppCurrency.symbol + ')'],
            columnWidths: ['40%', '25%', '35%'],
            windowWidth: 900,
            windowHeight: 700
        });
    }

    function exportPaymentMethodReport() {
        var tableData = [];
        $('#paymentMethodTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 3) return;
            tableData.push({
                values: [
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim(),
                    $(cells[2]).text().trim()
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success' }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_التوزيع_حسب_طريقة_الدفع',
            mainTitle: 'تقرير التوزيع حسب طريقة الدفع',
            subTitle: 'تقرير المبيعات المالي - المتجر الإلكتروني',
            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },
            tableTitle: 'التوزيع حسب طريقة الدفع',
            tableHeaders: ['الطريقة', 'الطلبات', 'الإيرادات (' + window.AppCurrency.symbol + ')'],
            columnWidths: [30, 15, 20],
            tableData: tableData,
            buttonSelector: '#exportPaymentMethod',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // التوزيع حسب طريقة الشحن
    // =====================================
    function printShippingMethodReport() {
        printTable({
            tableSelector: '#shippingMethodTable tbody tr',
            title: 'تقرير التوزيع حسب طريقة الشحن',
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['الطريقة', 'الطلبات', 'الإيرادات (' + window.AppCurrency.symbol + ')'],
            columnWidths: ['40%', '25%', '35%'],
            windowWidth: 900,
            windowHeight: 700
        });
    }

    function exportShippingMethodReport() {
        var tableData = [];
        $('#shippingMethodTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 3) return;
            tableData.push({
                values: [
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim(),
                    $(cells[2]).text().trim()
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success' }
                ]
            });
        });

        var config = {
            fileName: 'تقرير_التوزيع_حسب_طريقة_الشحن',
            mainTitle: 'تقرير التوزيع حسب طريقة الشحن',
            subTitle: 'تقرير المبيعات المالي - المتجر الإلكتروني',
            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },
            tableTitle: 'التوزيع حسب طريقة الشحن',
            tableHeaders: ['الطريقة', 'الطلبات', 'الإيرادات (' + window.AppCurrency.symbol + ')'],
            columnWidths: [30, 15, 20],
            tableData: tableData,
            buttonSelector: '#exportShippingMethod',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }

    // =====================================
    // تفاصيل المبيعات اليومية
    // =====================================
    function printDailySalesReport() {
        printTable({
            tableSelector: '#dailySalesTable tbody tr',
            tfootSelector: '#dailySalesTableFoot',
            title: 'تقرير المبيعات اليومية',
            infoCards: [
                { label: 'عدد الطلبات', value: formatNumber({{ $totalSales->orders ?? 0 }}, 0) },
                { label: 'المبيعات الصافية', value: formatNumber({{ $totalSales->subtotal ?? 0 }}) + ' ' + window.AppCurrency.symbol },
                { label: 'الضرائب', value: formatNumber({{ $totalSales->tax ?? 0 }}) + ' ' + window.AppCurrency.symbol },
                { label: 'الشحن', value: formatNumber({{ $totalSales->shipping ?? 0 }}) + ' ' + window.AppCurrency.symbol },
                { label: 'الخصومات', value: formatNumber({{ $totalSales->discount ?? 0 }}) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                { label: 'الإجمالي', value: formatNumber({{ $totalSales->revenue ?? 0 }}) + ' ' + window.AppCurrency.symbol, class: 'text-success' }
            ],
            filterInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
            },
            tableHeaders: ['التاريخ', 'عدد الطلبات', 'المبيعات الصافية', 'الضرائب', 'الشحن', 'الخصومات', 'الإجمالي'],
            columnWidths: ['14%', '14%', '16%', '14%', '14%', '14%', '14%'],
            windowWidth: 1400,
            windowHeight: 900
        });
    }

    function exportDailySalesReport() {
        var tableData = [];
        $('#dailySalesTable tbody tr').each(function() {
            var cells = $(this).find('td');
            if (cells.length < 7) return;
            tableData.push({
                values: [
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim(),
                    $(cells[2]).text().trim(),
                    $(cells[3]).text().trim(),
                    $(cells[4]).text().trim(),
                    $(cells[5]).text().trim(),
                    $(cells[6]).text().trim()
                ],
                cellStyles: [
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'danger' },
                    { alignment: { horizontal: 'center' }, color: 'success', bold: true }
                ]
            });
        });

        var totals = {
            orders: {{ $totalSales->orders ?? 0 }},
            subtotal: {{ $totalSales->subtotal ?? 0 }},
            tax: {{ $totalSales->tax ?? 0 }},
            shipping: {{ $totalSales->shipping ?? 0 }},
            discount: {{ $totalSales->discount ?? 0 }},
            revenue: {{ $totalSales->revenue ?? 0 }}
        };

        var config = {
            fileName: 'تقرير_المبيعات_اليومية',
            mainTitle: 'تقرير المبيعات اليومية',
            subTitle: 'تقرير المبيعات المالي - المتجر الإلكتروني',
            reportInfo: {
                'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },
            statisticsCards: [
                {
                    title: 'عدد الطلبات',
                    value: formatNumber(totals.orders, 0),
                    unit: ' طلب',
                    titleColor: 'primary',
                    valueColor: 'primary',
                    backgroundColor: 'grayLight',
                    borderColor: 'primary'
                },
                {
                    title: 'المبيعات الصافية',
                    value: formatNumber(totals.subtotal),
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                },
                {
                    title: 'الضرائب',
                    value: formatNumber(totals.tax),
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'warning',
                    valueColor: 'warning',
                    backgroundColor: 'warningLight',
                    borderColor: 'warning'
                },
                {
                    title: 'الإجمالي',
                    value: formatNumber(totals.revenue),
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                }
            ],
            tableTitle: 'تفاصيل المبيعات اليومية',
            tableHeaders: ['التاريخ', 'عدد الطلبات', 'المبيعات الصافية', 'الضرائب', 'الشحن', 'الخصومات', 'الإجمالي'],
            columnWidths: [14, 14, 16, 14, 14, 14, 14],
            tableData: tableData,
            summaryRows: [
                {
                    label: 'الإجمالي العام',
                    values: [
                        'الإجمالي العام',
                        formatNumber(totals.orders, 0),
                        formatNumber(totals.subtotal),
                        formatNumber(totals.tax),
                        formatNumber(totals.shipping),
                        formatNumber(totals.discount),
                        formatNumber(totals.revenue)
                    ],
                    color: 'primary',
                    cellStyles: [
                        { bold: true },
                        { bold: true },
                        { bold: true },
                        { bold: true },
                        { bold: true },
                        { color: 'danger', bold: true },
                        { color: 'success', bold: true }
                    ]
                }
            ],
            buttonSelector: '#exportDailySales',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        exportToExcelProfessional(config);
    }
</script>
<script>
// التأكد من تحميل المكتبة
function initCharts() {
    if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts library not loaded!');
        return;
    }

    // Flatpickr
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.flatpickr-basic', {
            locale: 'ar',
            dateFormat: 'Y-m-d'
        });
    }

    // المبيعات اليومية
    var dailyData = @json($dailySales->toArray());
    console.log('Daily Sales Data:', dailyData);

    if (dailyData && dailyData.length > 0) {
        var dates = dailyData.map(item => item.date);
        var revenues = dailyData.map(item => parseFloat(item.revenue) || 0);
        var orders = dailyData.map(item => parseInt(item.orders) || 0);

        var dailySalesChart = new ApexCharts(document.querySelector("#dailySalesChart"), {
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
                fontFamily: 'inherit'
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
                        return val ? val.toLocaleString('ar-SA') : '0';
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
            }
        });
        dailySalesChart.render();
    } else {
        document.getElementById("dailySalesChart").innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات لعرضها</div>';
    }

    // طرق الدفع - إصلاح مشكلة Enum
    var paymentLabels = {
        'cash': 'نقدي',
        'card': 'بطاقة',
        'bank_transfer': 'تحويل بنكي',
        'cod': 'الدفع عند الاستلام',
        'online': 'دفع إلكتروني'
    };

    // تحويل البيانات في PHP قبل إرسالها
    var paymentData = @json($salesByPaymentMethod->map(function($item) {
        return [
            'payment_method' => $item->payment_method instanceof \BackedEnum ? $item->payment_method->value : $item->payment_method,
            'orders' => $item->orders,
            'revenue' => $item->revenue
        ];
    })->toArray());

    console.log('Payment Data:', paymentData);

    if (paymentData && paymentData.length > 0) {
        var labels = paymentData.map(function(p) {
            return paymentLabels[p.payment_method] || p.payment_method || 'غير محدد';
        });
        var values = paymentData.map(function(p) {
            return parseFloat(p.revenue) || 0;
        });

        var paymentChart = new ApexCharts(document.querySelector("#paymentChart"), {
            series: values,
            chart: {
                type: 'donut',
                height: 300
            },
            labels: labels,
            colors: ['#28c76f', '#7367f0', '#ff9f43', '#00cfe8', '#fd7e14'],
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
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('ar-SA');
                                }
                            }
                        }
                    }
                }
            }
        });
        paymentChart.render();
    } else {
        document.getElementById("paymentChart").innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات لعرضها</div>';
    }

    // أيام الأسبوع
    var daysData = @json($bestDays->toArray());
    console.log('Days Data:', daysData);

    if (daysData && daysData.length > 0) {
        var dayLabels = daysData.map(function(d) { return d.day_name || ''; });
        var dayRevenues = daysData.map(function(d) { return parseFloat(d.revenue) || 0; });

        var daysChart = new ApexCharts(document.querySelector("#daysChart"), {
            series: [{
                name: 'الإيرادات',
                data: dayRevenues
            }],
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            xaxis: {
                categories: dayLabels
            },
            colors: ['#7367f0'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '50%'
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val ? val.toLocaleString('ar-SA') + ' ' + window.AppCurrency.symbol : '0 ' + window.AppCurrency.symbol;
                    }
                }
            }
        });
        daysChart.render();
    } else {
        document.getElementById("daysChart").innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات لعرضها</div>';
    }

    // الساعات
    var hoursData = @json($bestHours->toArray());
    console.log('Hours Data:', hoursData);

    if (hoursData && hoursData.length > 0) {
        var hourLabels = hoursData.map(function(h) { return h.hour + ':00'; });
        var hourOrders = hoursData.map(function(h) { return parseInt(h.orders) || 0; });

        var hoursChart = new ApexCharts(document.querySelector("#hoursChart"), {
            series: [{
                name: 'الطلبات',
                data: hourOrders
            }],
            chart: {
                type: 'line',
                height: 280,
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            xaxis: {
                categories: hourLabels
            },
            colors: ['#28c76f'],
            stroke: {
                curve: 'smooth',
                width: 3
            },
            markers: {
                size: 4
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val + ' طلب';
                    }
                }
            }
        });
        hoursChart.render();
    } else {
        document.getElementById("hoursChart").innerHTML = '<div class="text-center text-muted p-4">لا توجد بيانات لعرضها</div>';
    }
}

// تشغيل عند جاهزية الصفحة
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts);
} else {
    initCharts();
}
</script>
@endsection
