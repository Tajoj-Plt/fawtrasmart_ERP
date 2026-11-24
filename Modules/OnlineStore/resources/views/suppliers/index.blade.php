@extends('onlinestore::master')

@section('title', 'الموردين')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('purchases.suppliers.create'))
                <a href="{{ route('store_suppliers.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة مورد
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'الموردين',
            'icon' => 'truck',
            'sub' => 'موردو المتجر الإلكتروني وأرصدتهم',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الموردين' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">إجمالي الموردين</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">موردين نشطين</span>
                <span class="stk-metric__value">{{ $stats['active'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">موردين موقوفين</span>
                <span class="stk-metric__value">{{ $stats['inactive'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">أرصدة موردي هذه الصفحة</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($suppliers->sum(fn($s) => $s->account->balance ?? 0)) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_suppliers.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">البحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="ابحث بالاسم أو رقم المورد" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="email">البريد الإلكتروني</label>
                                <input type="text" id="email" name="email" class="form-control"
                                    placeholder="البريد الإلكتروني" value="{{ request('email') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="mobile">رقم الجوال</label>
                                <input type="text" id="mobile" name="mobile" class="form-control"
                                    placeholder="رقم الجوال" value="{{ request('mobile') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('status') === '1')>نشط</option>
                                    <option value="0" @selected(request('status') === '0')>موقوف</option>
                                </select>
                            </div>
                        </div>

                        {{-- فلتر الفرع — خياراته مقصوصة على نطاق المستخدم، ويشمل ما تحت الفرع المختار --}}
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="branch">الفرع</label>
                                <select name="branch" id="branch" class="form-control select2">
                                    <option value="">كل الفروع المتاحة</option>
                                    @foreach ($branches ?? [] as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) request('branch') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_suppliers.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card" id="reportCard">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الموردين</h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="printSuppliersBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-printer"></i> طباعة
                    </button>
                    <button type="button" id="exportSuppliersExcel" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-file-text"></i> تصدير Excel
                    </button>
                    <span class="badge badge-light-primary">{{ $suppliers->total() }} مورد</span>
                </div>
            </div>

            <div class="table-responsive" id="suppliersTableWrap">
                @include('onlinestore::suppliers.partials.suppliers_table', ['suppliers' => $suppliers])
            </div>

            <div id="suppliersPaginationWrap">
                @include('onlinestore::suppliers.partials.pagination', ['suppliers' => $suppliers])
            </div>
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
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    // طباعة قائمة الموردين
    $('#printSuppliersBtn').click(function() {
        printSuppliersReport();
    });

    // تصدير قائمة الموردين إلى Excel
    $('#exportSuppliersExcel').click(function() {
        exportSuppliersReport();
    });

    // تبديل الحالة
    $(document).on('click', '.toggle-status', function() {
        var btn = $(this);
        var supplierId = btn.data('id');
        var supplierName = btn.data('name');
        var currentStatus = btn.data('status');
        var newStatus = currentStatus == 1 ? 0 : 1;

        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: newStatus == 1 ?
                `هل تريد تفعيل المورد "${supplierName}"؟` :
                `هل تريد إيقاف المورد "${supplierName}"؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus == 1 ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: newStatus == 1 ? 'نعم، فعّل' : 'نعم، أوقف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ url('online_store/suppliers') }}/" + supplierId + "/update-status",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        status: newStatus
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.toggleClass('stk-state--on stk-state--off');
                            btn.find('i').toggleClass('icon-check-circle icon-x-circle');
                            btn.data('status', newStatus);
                            btn.contents().filter(function() { return this.nodeType === 3; }).remove();
                            btn.append(newStatus == 1 ? ' نشط' : ' موقوف');
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message || 'حدث خطأ أثناء التحديث');
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ أثناء تحديث الحالة');
                    }
                });
            }
        });
    });

    // تأكيد الحذف
    $(document).on('submit', '.delete-form', function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا المورد نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});

// =====================================
// معلومات الفلاتر المطبَّقة حالياً
// =====================================
var suppliersFilterInfo = {
    @if(request('search'))
    'البحث': @json(request('search')),
    @endif
    @if(request('email'))
    'البريد الإلكتروني': @json(request('email')),
    @endif
    @if(request('mobile'))
    'رقم الجوال': @json(request('mobile')),
    @endif
    @if(request('status') === '1' || request('status') === '0')
    'الحالة': @json(request('status') === '1' ? 'نشط' : 'موقوف'),
    @endif
};

var suppliersInfoCards = [
    { label: 'إجمالي الموردين', value: '{{ $stats['total'] ?? 0 }}' },
    { label: 'موردين نشطين', value: '{{ $stats['active'] ?? 0 }}', class: 'text-success' },
    { label: 'موردين موقوفين', value: '{{ $stats['inactive'] ?? 0 }}', class: 'text-danger' },
    { label: 'إجمالي الأرصدة', value: formatNumber({{ $suppliers->sum(fn($s) => $s->account->balance ?? 0) }}, 0) + ' ' + window.AppCurrency.symbol }
];

// =====================================
// جمع بيانات جدول الموردين من DOM (يتوافق مع صفحة الترقيم الحالية)
// =====================================
function collectSuppliersRows() {
    var rows = [];

    $('#suppliersTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 7) return; // تجاهل صف "لا توجد بيانات"

        rows.push({
            rowNum: $(cells[0]).text().trim(),
            name: $(cells[1]).find('.stk-media__title').text().trim(),
            number: $(cells[2]).text().trim(),
            mobile: $(cells[3]).text().trim(),
            email: $(cells[4]).text().trim(),
            balance: $(cells[5]).find('.badge').text().trim() || $(cells[5]).text().trim(),
            balancePositive: $(cells[5]).find('.badge-light-success').length > 0,
            status: $(cells[6]).find('.stk-state').text().trim() || $(cells[6]).text().trim(),
            assignee: $(cells[7]).text().replace(/\s+/g, ' ').trim()
        });
    });

    return rows;
}

// =====================================
// الطباعة
// =====================================
function printSuppliersReport() {
    var rows = collectSuppliersRows();

    var $hidden = $('#suppliersPrintTable');
    if ($hidden.length === 0) {
        $hidden = $('<table id="suppliersPrintTable" style="display:none"><tbody></tbody></table>').appendTo('body');
    }

    var html = '';
    rows.forEach(function(r) {
        html += '<tr>' +
            '<td>' + $('<div>').text(r.rowNum).html() + '</td>' +
            '<td>' + $('<div>').text(r.name).html() + '</td>' +
            '<td>' + $('<div>').text(r.number).html() + '</td>' +
            '<td>' + $('<div>').text(r.mobile).html() + '</td>' +
            '<td>' + $('<div>').text(r.email).html() + '</td>' +
            '<td>' + $('<div>').text(r.balance + ' ' + window.AppCurrency.symbol).html() + '</td>' +
            '<td>' + $('<div>').text(r.status).html() + '</td>' +
            '<td>' + $('<div>').text(r.assignee).html() + '</td>' +
            '</tr>';
    });
    $hidden.find('tbody').html(html);

    printTable({
        tableSelector: '#suppliersPrintTable tbody tr',
        title: 'قائمة الموردين - المتجر الإلكتروني',
        infoCards: suppliersInfoCards,
        filterInfo: suppliersFilterInfo,
        tableHeaders: ['#', 'اسم المورد', 'رقم المورد', 'رقم الجوال', 'البريد الإلكتروني', 'الرصيد', 'الحالة', 'المسؤول'],
        columnWidths: ['5%', '22%', '12%', '12%', '16%', '10%', '8%', '15%'],
        windowWidth: 1200,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportSuppliersReport() {
    var rows = collectSuppliersRows();

    var tableData = rows.map(function(r) {
        return {
            values: [
                r.rowNum,
                r.name,
                r.number,
                r.mobile,
                r.email,
                r.balance + ' ' + window.AppCurrency.symbol,
                r.status,
                r.assignee
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' }, color: r.balancePositive ? 'success' : 'danger' },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } }
            ]
        };
    });

    var config = {
        fileName: 'قائمة_الموردين',
        mainTitle: 'قائمة الموردين - المتجر الإلكتروني',
        subTitle: 'تقرير مخصَّص',

        reportInfo: Object.keys(suppliersFilterInfo).length > 0 ? Object.assign({
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }, suppliersFilterInfo) : {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },

        statisticsCards: [
            { title: 'إجمالي الموردين', value: '{{ $stats['total'] ?? 0 }}', unit: ' مورد', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'موردين نشطين', value: '{{ $stats['active'] ?? 0 }}', unit: ' مورد', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'موردين موقوفين', value: '{{ $stats['inactive'] ?? 0 }}', unit: ' مورد', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
            { title: 'إجمالي الأرصدة', value: formatNumber({{ $suppliers->sum(fn($s) => $s->account->balance ?? 0) }}, 0), unit: ' ' + window.AppCurrency.symbol, titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' }
        ],

        tableTitle: 'قائمة الموردين',
        tableHeaders: ['#', 'اسم المورد', 'رقم المورد', 'رقم الجوال', 'البريد الإلكتروني', 'الرصيد', 'الحالة', 'المسؤول'],
        columnWidths: [6, 30, 16, 16, 26, 18, 14, 24],

        tableData: tableData,

        buttonSelector: '#exportSuppliersExcel',
        successMessage: 'تم تصدير قائمة الموردين بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير قائمة الموردين'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
