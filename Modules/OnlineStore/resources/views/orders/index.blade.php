@extends('onlinestore::master')

@section('title', 'إدارة الطلبات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('online_orders.statistics') }}" class="btn btn-outline-primary">
                <i class="feather icon-bar-chart-2"></i> الإحصائيات
            </a>
            <button type="button" id="printOrdersBtn" class="btn btn-outline-secondary">
                <i class="feather icon-printer"></i> طباعة
            </button>
            @if (hasPermission('online_store.orders.export'))
                <button type="button" id="exportOrdersBtn" class="btn btn-outline-success">
                    <i class="feather icon-download"></i> تصدير Excel
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إدارة الطلبات',
            'icon' => 'shopping-cart',
            'sub' => 'طلبات العملاء في المتجر الإلكتروني ومتابعة حالتها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الطلبات' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @php
            // ألوان bootstrap للحالة (OrderStatus::color()/PaymentStatus::color()) تُطابَق
            // مع نغمات stk-state المتاحة فعلياً في نظام التصميم: on/off/neutral/warn.
            $stkTone = ['success' => 'on', 'danger' => 'off', 'secondary' => 'off', 'dark' => 'off', 'warning' => 'warn', 'info' => 'neutral', 'primary' => 'neutral'];
        @endphp

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">بانتظار التأكيد</span>
                <span class="stk-metric__value">{{ $stats['pending'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">قيد التجهيز</span>
                <span class="stk-metric__value">{{ $stats['processing'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-truck"></i></span>
                <span class="stk-metric__label">تم الشحن</span>
                <span class="stk-metric__value">{{ $stats['shipped'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مكتمل</span>
                <span class="stk-metric__value">{{ $stats['completed'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                <span class="stk-metric__label">طلبات اليوم</span>
                <span class="stk-metric__value">{{ $stats['today_orders'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إيرادات اليوم</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($stats['today_revenue']) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('online_orders.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="رقم الطلب، اسم العميل، الهاتف…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">حالة الطلب</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($orderStatuses as $status)
                                        <option value="{{ $status->value }}" @selected(request('status') == $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="payment_status">حالة الدفع</label>
                                <select name="payment_status" id="payment_status" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($paymentStatuses as $status)
                                        <option value="{{ $status->value }}" @selected(request('payment_status') == $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="payment_method">طريقة الدفع</label>
                                <select name="payment_method" id="payment_method" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($paymentMethods as $method)
                                        <option value="{{ $method->value }}" @selected(request('payment_method') == $method->value)>
                                            {{ $method->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="date_from">من تاريخ</label>
                                <input type="date" id="date_from" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="date_to">إلى تاريخ</label>
                                <input type="date" id="date_to" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="custom-control custom-checkbox mt-1">
                                    <input type="checkbox" class="custom-control-input" id="delayed" name="delayed" value="1" {{ request('delayed') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="delayed">المتأخرة فقط</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('online_orders.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-shopping-cart"></i> قائمة الطلبات</h6>
                <span class="badge badge-light-primary">{{ $orders->total() }} طلب</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="ordersTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th class="text-center">المنتجات</th>
                            <th class="text-center">الإجمالي</th>
                            <th class="text-center">حالة الطلب</th>
                            <th class="text-center">الدفع</th>
                            <th class="text-center">التاريخ</th>
                            <th class="text-center" style="width: 80px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr class="{{ $order->isDelayed() ? 'table-warning' : '' }}">
                                <td class="text-center stk-num">{{ $loop->iteration }}</td>
                                <td>
                                    <a href="{{ route('online_orders.show', $order) }}" class="font-weight-bold text-primary stk-num">
                                        {{ $order->order_number }}
                                    </a>
                                    @if ($order->is_express)
                                        <span class="badge badge-light-danger">سريع</span>
                                    @endif
                                    @if ($order->is_gift)
                                        <span class="badge badge-light-info">هدية</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__body">
                                            @if ($order->client)
                                                <span class="stk-media__title">{{ $order->client->trade_name ?? $order->client->first_name.' '.$order->client->last_name }}</span>
                                            @else
                                                <span class="stk-media__title">{{ $order->shipping_name }}</span>
                                            @endif
                                            <span class="stk-media__sub">{{ $order->shipping_phone }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-secondary">{{ $order->items_count }} منتج</span>
                                </td>
                                <td class="text-center">
                                    <strong class="stk-num">{{ $order->formatMoney($order->total_amount) }}</strong>
                                    @if ($order->refundedSoFar() > 0)
                                        <small class="d-block text-danger">مردود {{ $order->formatMoney($order->refundedSoFar()) }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $stkTone[$order->status->color()] ?? 'neutral' }}">{{ $order->status->label() }}</span>
                                    @if ($order->isDelayed())
                                        <br><small class="text-danger">متأخر {{ $order->days_delayed }} يوم</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $stkTone[$order->payment_status->color()] ?? 'neutral' }}">{{ $order->payment_status->label() }}</span>
                                    <br>
                                    <small class="text-muted">{{ $order->payment_method->label() }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="stk-num">{{ $order->created_at->format('Y-m-d') }}</span>
                                    <small class="text-muted d-block">{{ $order->created_at->format('H:i') }}</small>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-right">
                                            <a class="dropdown-item" href="{{ route('online_orders.show', $order) }}">
                                                <i class="feather icon-eye"></i> عرض
                                            </a>
                                            @if (hasPermission('online_store.orders.print'))
                                                <a class="dropdown-item" href="{{ route('online_orders.print', $order) }}" target="_blank">
                                                    <i class="feather icon-printer"></i> طباعة
                                                </a>
                                            @endif
                                            @if ($order->tracking_number)
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#" data-tracking="{{ $order->tracking_number }}">
                                                    <i class="feather icon-map-pin"></i> تتبع الشحنة
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-shopping-bag"></i></span>
                                        <h6 class="stk-empty__title">لا توجد طلبات</h6>
                                        <p class="stk-empty__hint">لم يصل أي طلب مطابق لهذا البحث بعد.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $orders, 'label' => 'طلب', 'appends' => request()->query()])
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }
});

// =====================================
// طباعة وتصدير مخصّصان لقائمة الطلبات — نفس نمط
// Account\GeneralAccountsController (public/js/print-table.js +
// public/js/excel-export-helper.js). كانت "تصدير" هنا تفتح
// OnlineOrderController::export() وهي دالةٌ فارغة (TODO).
// =====================================
function buildOrdersExportRows() {
    return [
        @foreach ($orders as $order)
        {
            number: @json($order->order_number),
            client: @json($order->client ? ($order->client->trade_name ?? $order->client->first_name.' '.$order->client->last_name) : $order->shipping_name),
            phone: @json($order->shipping_phone),
            items: {{ (int) $order->items_count }},
            total: @json($order->formatMoney($order->total_amount)),
            status: @json($order->status->label()),
            paymentStatus: @json($order->payment_status->label()),
            paymentMethod: @json($order->payment_method->label()),
            date: @json($order->created_at->format('Y-m-d H:i')),
        },
        @endforeach
    ];
}

function printOrdersList() {
    printTable({
        tableSelector: '#ordersTable tbody tr',
        title: 'تقرير طلبات المتجر',
        infoCards: [
            { label: 'بانتظار التأكيد', value: '{{ $stats['pending'] }}' },
            { label: 'قيد التجهيز', value: '{{ $stats['processing'] }}' },
            { label: 'تم الشحن', value: '{{ $stats['shipped'] }}' },
            { label: 'مكتمل', value: '{{ $stats['completed'] }}', class: 'text-success' }
        ],
        filterInfo: {
            @if(request('search')) 'بحث': @json(request('search')), @endif
            @if(request('status')) 'حالة الطلب': @json(optional(collect($orderStatuses)->first(fn($s) => $s->value == request('status')))->label()), @endif
            @if(request('payment_status')) 'حالة الدفع': @json(optional(collect($paymentStatuses)->first(fn($s) => $s->value == request('payment_status')))->label()), @endif
            @if(request('date_from')) 'من تاريخ': @json(request('date_from')), @endif
            @if(request('date_to')) 'إلى تاريخ': @json(request('date_to')), @endif
        },
        tableHeaders: ['#', 'رقم الطلب', 'العميل', 'المنتجات', 'الإجمالي', 'حالة الطلب', 'الدفع', 'التاريخ'],
        columnWidths: ['4%', '12%', '20%', '9%', '11%', '14%', '16%', '14%'],
        skipColumns: [8]
    });
}

function exportOrdersListExcel() {
    const rows = buildOrdersExportRows();

    const config = {
        fileName: 'تقرير_طلبات_المتجر',
        mainTitle: 'تقرير طلبات المتجر',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
        },

        statisticsCards: [
            { title: 'بانتظار التأكيد', value: '{{ $stats['pending'] }}', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
            { title: 'قيد التجهيز', value: '{{ $stats['processing'] }}', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'تم الشحن', value: '{{ $stats['shipped'] }}', titleColor: 'purple', valueColor: 'purple', backgroundColor: 'purpleLight', borderColor: 'purple' },
            { title: 'مكتمل', value: '{{ $stats['completed'] }}', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' }
        ],

        tableTitle: 'قائمة الطلبات',
        tableHeaders: ['#', 'رقم الطلب', 'العميل', 'الهاتف', 'المنتجات', 'الإجمالي', 'حالة الطلب', 'حالة الدفع', 'طريقة الدفع', 'التاريخ'],
        columnWidths: [5, 14, 22, 14, 10, 14, 14, 14, 14, 16],

        tableData: rows.map((row, index) => ({
            values: [
                index + 1, row.number, row.client, row.phone, row.items, row.total,
                row.status, row.paymentStatus, row.paymentMethod, row.date
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' }, color: 'success', bold: true },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        })),

        // صف الإجمالي — صافي الصفحة المعروضة (الملغي خارج المجموع، والمردود مطروح)
        summaryRows: [{
            label: 'الإجمالي',
            values: ['الإجمالي (صافٍ بعد الملغي والمردود)', '', '', '', '', @json(\App\Support\CurrencySettings::format($orders->getCollection()->sum(fn ($o) => $o->status === \App\Enums\OrderStatus::CANCELLED ? 0 : (float) $o->total_amount - $o->refundedSoFar()))), '', '', '', ''],
            mergeFrom: 1,
            mergeTo: 5,
            background: 'successLight',
            color: 'success',
            borderColor: 'success'
        }],

        buttonSelector: '#exportOrdersBtn',
        successMessage: 'تم تصدير تقرير الطلبات بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}

$('#printOrdersBtn').on('click', printOrdersList);
$('#exportOrdersBtn').on('click', exportOrdersListExcel);
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
@endsection
