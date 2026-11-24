@extends('onlinestore::master')

@section('title', 'عملاء المتجر الإلكتروني')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.clients.create'))
                <a href="{{ route('store_clients.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة عميل
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'عملاء المتجر الإلكتروني',
            'icon' => 'users',
            'sub' => 'عملاء المتجر الإلكتروني وطلباتهم',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'عملاء المتجر' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">إجمالي العملاء</span>
                <span class="stk-metric__value">{{ $totalClients ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-user-check"></i></span>
                <span class="stk-metric__label">عملاء نشطين</span>
                <span class="stk-metric__value">{{ $activeClients ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي قيمة الطلبات</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($totalOrdersValue ?? 0) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_clients.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-5 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="اسم، بريد، هاتف، كود…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-5 col-sm-6">
                            <div class="form-group">
                                <label for="city">المدينة</label>
                                <select name="city" id="city" class="form-control select2">
                                    <option value="">-- الكل --</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city }}" @selected(request('city') == $city)>{{ $city }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_clients.index') }}" class="btn btn-outline-secondary">
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة العملاء</h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="printClientsBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-printer"></i> طباعة
                    </button>
                    <button type="button" id="exportClientsExcel" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-file-text"></i> تصدير Excel
                    </button>
                    <span class="badge badge-light-primary">{{ $clients->total() }} عميل</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="clientsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>البريد الإلكتروني</th>
                            <th class="text-center">الهاتف</th>
                            <th class="text-center">عدد الطلبات</th>
                            <th class="text-center">تاريخ التسجيل</th>
                            @if (hasPermission(['online_store.clients.view', 'online_store.clients.edit', 'online_store.clients.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clients as $index => $client)
                            <tr>
                                <td class="text-center stk-num">{{ $clients->firstItem() + $index }}</td>
                                <td>
                                    <span class="badge badge-light-primary">{{ $client->code ?? '-' }}</span>
                                </td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb">{{ mb_substr($client->first_name ?? 'ع', 0, 1) }}</span>
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $client->first_name }} {{ $client->last_name }}</span>
                                            @if ($client->trade_name)
                                                <span class="stk-media__sub">{{ $client->trade_name }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $client->email ?? '-' }}</td>
                                <td class="text-center" dir="ltr">{{ $client->mobile ?? $client->phone ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge badge-light-info">{{ $client->online_orders_count ?? 0 }} طلب</span>
                                </td>
                                <td class="text-center stk-num">{{ $client->created_at ? $client->created_at->format('Y-m-d') : '-' }}</td>
                                @if (hasPermission(['online_store.clients.view', 'online_store.clients.edit', 'online_store.clients.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.clients.view'))
                                                    <a class="dropdown-item" href="{{ route('store_clients.show', $client) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.clients.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_clients.edit', $client) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.clients.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_clients.destroy', $client) }}" method="POST" class="delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="feather icon-trash-2"></i> حذف
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-users"></i></span>
                                        <h6 class="stk-empty__title">لا يوجد عملاء</h6>
                                        <p class="stk-empty__hint">لا يوجد عملاء متجر مسجلون حتى الآن.</p>
                                        @if (hasPermission('online_store.clients.create'))
                                            <a href="{{ route('store_clients.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة عميل
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $clients, 'label' => 'عميل', 'appends' => request()->query()])
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
function formatNumber(n, decimals = 2) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({
            dir: 'rtl',
            language: {
                noResults: function() {
                    return "لا توجد نتائج";
                }
            },
            allowClear: true,
            width: '100%'
        });
    }

    // طباعة قائمة العملاء
    $('#printClientsBtn').click(function() {
        printClientsReport();
    });

    // تصدير قائمة العملاء إلى Excel
    $('#exportClientsExcel').click(function() {
        exportClientsReport();
    });

    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا العميل نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

// =====================================
// معلومات الفلاتر المطبَّقة حالياً
// =====================================
var clientsFilterInfo = {
    @if(request('search'))
    'البحث': @json(request('search')),
    @endif
    @if(request('city'))
    'المدينة': @json(request('city')),
    @endif
};

var clientsInfoCards = [
    { label: 'إجمالي العملاء', value: '{{ $totalClients ?? 0 }}' },
    { label: 'عملاء نشطين', value: '{{ $activeClients ?? 0 }}', class: 'text-success' },
    { label: 'إجمالي قيمة الطلبات', value: formatNumber({{ $totalOrdersValue ?? 0 }}) + ' ' + window.AppCurrency.symbol }
];

// =====================================
// جمع بيانات جدول العملاء من DOM (يتوافق مع صفحة الترقيم الحالية)
// =====================================
function collectClientsRows() {
    var rows = [];

    $('#clientsTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 7) return; // تجاهل صف "لا توجد بيانات"

        rows.push({
            rowNum: $(cells[0]).text().trim(),
            code: $(cells[1]).text().trim(),
            name: $(cells[2]).find('.stk-media__title').text().trim(),
            email: $(cells[3]).text().trim(),
            phone: $(cells[4]).text().trim(),
            ordersCount: $(cells[5]).text().trim(),
            registeredAt: $(cells[6]).text().trim()
        });
    });

    return rows;
}

// =====================================
// الطباعة
// =====================================
function printClientsReport() {
    var rows = collectClientsRows();

    var $hidden = $('#clientsPrintTable');
    if ($hidden.length === 0) {
        $hidden = $('<table id="clientsPrintTable" style="display:none"><tbody></tbody></table>').appendTo('body');
    }

    var html = '';
    rows.forEach(function(r) {
        html += '<tr>' +
            '<td>' + $('<div>').text(r.rowNum).html() + '</td>' +
            '<td>' + $('<div>').text(r.code).html() + '</td>' +
            '<td>' + $('<div>').text(r.name).html() + '</td>' +
            '<td>' + $('<div>').text(r.email).html() + '</td>' +
            '<td>' + $('<div>').text(r.phone).html() + '</td>' +
            '<td>' + $('<div>').text(r.ordersCount).html() + '</td>' +
            '<td>' + $('<div>').text(r.registeredAt).html() + '</td>' +
            '</tr>';
    });
    $hidden.find('tbody').html(html);

    printTable({
        tableSelector: '#clientsPrintTable tbody tr',
        title: 'قائمة عملاء المتجر الإلكتروني',
        infoCards: clientsInfoCards,
        filterInfo: clientsFilterInfo,
        tableHeaders: ['#', 'الكود', 'الاسم', 'البريد الإلكتروني', 'الهاتف', 'عدد الطلبات', 'تاريخ التسجيل'],
        columnWidths: ['5%', '10%', '25%', '22%', '14%', '12%', '12%'],
        windowWidth: 1300,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportClientsReport() {
    var rows = collectClientsRows();

    var tableData = rows.map(function(r) {
        return {
            values: [
                r.rowNum,
                r.code,
                r.name,
                r.email,
                r.phone,
                r.ordersCount,
                r.registeredAt
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        };
    });

    var config = {
        fileName: 'قائمة_عملاء_المتجر',
        mainTitle: 'قائمة عملاء المتجر الإلكتروني',
        subTitle: 'تقرير مخصَّص',

        reportInfo: Object.assign({
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }, clientsFilterInfo),

        statisticsCards: [
            { title: 'إجمالي العملاء', value: '{{ $totalClients ?? 0 }}', unit: ' عميل', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'عملاء نشطين', value: '{{ $activeClients ?? 0 }}', unit: ' عميل', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'إجمالي قيمة الطلبات', value: formatNumber({{ $totalOrdersValue ?? 0 }}), unit: ' ' + window.AppCurrency.symbol, titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' }
        ],

        tableTitle: 'قائمة العملاء',
        tableHeaders: ['#', 'الكود', 'الاسم', 'البريد الإلكتروني', 'الهاتف', 'عدد الطلبات', 'تاريخ التسجيل'],
        columnWidths: [6, 12, 28, 26, 16, 14, 14],

        tableData: tableData,

        buttonSelector: '#exportClientsExcel',
        successMessage: 'تم تصدير قائمة العملاء بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير القائمة'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
