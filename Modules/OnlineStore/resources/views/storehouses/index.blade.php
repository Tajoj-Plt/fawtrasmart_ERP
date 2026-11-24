@extends('onlinestore::master')

@section('title', 'المخازن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('stock.warehouses.create'))
                <a href="{{ route('store_storehouses.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة مخزن
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'المخازن',
            'icon' => 'package',
            'sub' => 'مخازن المتجر الإلكتروني وأرصدتها المحاسبية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المخازن' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">إجمالي المخازن</span>
                <span class="stk-metric__value">{{ $stats['total'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مخازن نشطة</span>
                <span class="stk-metric__value">{{ $stats['active'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">مخازن رئيسية</span>
                <span class="stk-metric__value">{{ $stats['major'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الأرصدة</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($stats['balance']) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_storehouses.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">البحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="ابحث باسم المخزن أو عنوانه" value="{{ is_string(request('search')) ? request('search') : '' }}">
                            </div>
                        </div>

                        {{--
                            فلتر الفرع: فروعُ المستخدم وحدَها (نطاقُ بياناته)، والاختيارُ
                            يشمل ما تحت الفرع في الشجرة. فارغاً = كلُّ ما يراه المستخدم
                            ضمن فرع الشريط.
                        --}}
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="branch_id">الفرع</label>
                                <select name="branch_id" id="branch_id" class="form-control select2">
                                    <option value="">كل الفروع المتاحة</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((is_scalar(request('branch_id')) ? (string) request('branch_id') : '') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="type">النوع</label>
                                <select name="type" id="type" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('type') === '1')>رئيسي</option>
                                    <option value="0" @selected(request('type') === '0')>فرعي</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="active" @selected(request('status') === 'active')>نشط</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
                                    <option value="stopped" @selected(request('status') === 'stopped')>متوقف</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_storehouses.index') }}" class="btn btn-outline-secondary">
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة المخازن</h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="printStorehousesBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-printer"></i> طباعة
                    </button>
                    <button type="button" id="exportStorehousesExcel" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-file-text"></i> تصدير Excel
                    </button>
                    <span class="badge badge-light-primary">{{ $storehouses->total() }} مخزن</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="storehousesTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>اسم المخزن</th>
                            <th>الفرع</th>
                            <th class="text-center">النوع</th>
                            <th>العنوان</th>
                            <th class="text-center">الرصيد ({{ \App\Support\CurrencySettings::code() }})</th>
                            <th class="text-center">الحالة</th>
                            <th>المسند إليهم</th>
                            @if (hasPermission(['stock.warehouses.view', 'stock.warehouses.show', 'stock.warehouses.edit', 'stock.warehouses.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($storehouses as $storehouse)
                            <tr>
                                <td class="text-center stk-num">{{ $loop->iteration + ($storehouses->currentPage() - 1) * $storehouses->perPage() }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-package"></i></span>
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">
                                                {{ $storehouse->name }}
                                                @if ($storehouse->major == 1)
                                                    <span class="badge badge-light-warning ms-1">رئيسي</span>
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($storehouse->branch)
                                        <span class="badge badge-light-info">{{ $storehouse->branch->name }}</span>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($storehouse->major == 1)
                                        <span class="badge badge-light-warning">رئيسي</span>
                                    @else
                                        <span class="badge badge-light-secondary">فرعي</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted">{{ Str::limit($storehouse->shipping_address, 25) ?: 'غير محدد' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ ($storehouse->account->balance ?? 0) >= 0 ? 'success' : 'danger' }}">
                                        {{ number_format($storehouse->account->balance ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('stock.warehouses.toggle_status'))
                                        <span class="stk-state {{ $storehouse->status == 'active' ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $storehouse->id }}" data-name="{{ $storehouse->name }}"
                                            data-status="{{ $storehouse->status }}">
                                            {{ $storehouse->status == 'active' ? 'نشط' : ($storehouse->status == 'inactive' ? 'غير نشط' : 'متوقف') }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $storehouse->status == 'active' ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $storehouse->status == 'active' ? 'نشط' : ($storehouse->status == 'inactive' ? 'غير نشط' : 'متوقف') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($assignees[$storehouse->id] ?? [] as $assignee)
                                        <span class="badge badge-light-primary">{{ $assignee['name'] }}</span>
                                    @empty
                                        <span class="text-muted">غير مسند</span>
                                    @endforelse
                                </td>
                                @if (hasPermission(['stock.warehouses.view', 'stock.warehouses.show', 'stock.warehouses.edit', 'stock.warehouses.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown"
                                                data-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-right">
                                                @if (hasPermission('stock.warehouses.show'))
                                                    <a class="dropdown-item" href="{{ route('store_storehouses.show', $storehouse->id) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('stock.warehouses.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_storehouses.edit', $storehouse->id) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('stock.warehouses.view'))
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="{{ route('store_storehouses.summary-inventory-operations', $storehouse->id) }}">
                                                        <i class="feather icon-bar-chart-2"></i> ملخص العمليات
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('store_storehouses.inventory-value', $storehouse->id) }}">
                                                        <i class="feather icon-dollar-sign"></i> قيمة المخزون
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('store_storehouses.inventory-sheet', $storehouse->id) }}">
                                                        <i class="feather icon-file-text"></i> ورقة الجرد
                                                    </a>
                                                @endif
                                                @if ($storehouse->major == 0 && hasPermission('stock.warehouses.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_storehouses.destroy', $storehouse->id) }}" method="POST" class="delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item delete-item text-danger">
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
                                <td colspan="9">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-package"></i></span>
                                        <h6 class="stk-empty__title">لا توجد مخازن لعرضها</h6>
                                        <p class="stk-empty__hint">لا مخازن للمتجر في الفرع المختار أو ضمن نطاق صلاحياتك — جرّب فرعاً آخر من الشريط العلوي أو من فلتر الفرع.</p>
                                        @if (hasPermission('stock.warehouses.create'))
                                            <a href="{{ route('store_storehouses.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة مخزن جديد
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $storehouses, 'label' => 'مخزن'])
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

    // طباعة قائمة المخازن
    $('#printStorehousesBtn').click(function() {
        printStorehousesReport();
    });

    // تصدير قائمة المخازن إلى Excel
    $('#exportStorehousesExcel').click(function() {
        exportStorehousesReport();
    });

    // تبديل الحالة
    $('.toggle-status').click(function() {
        var btn = $(this);
        var storehouseId = btn.data('id');
        var storehouseName = btn.data('name');
        var currentStatus = btn.data('status');
        var newStatus = currentStatus == 'active' ? 'inactive' : 'active';

        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: newStatus == 'active' ?
                `هل تريد تفعيل المخزن "${storehouseName}"؟` :
                `هل تريد إيقاف المخزن "${storehouseName}"؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus == 'active' ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: newStatus == 'active' ? 'نعم، فعّل' : 'نعم، أوقف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ url('online_store/storehouses') }}/" + storehouseId + "/update-status",
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        status: newStatus
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.toggleClass('stk-state--on stk-state--off');
                            btn.data('status', newStatus);
                            btn.contents().filter(function() { return this.nodeType === 3; }).remove();
                            btn.append(newStatus == 'active' ? ' نشط' : ' غير نشط');
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
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا المخزن نهائياً!',
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
var storehousesFilterInfo = {
    @if(is_string(request('search')) && request('search') !== '')
    'البحث': @json(request('search')),
    @endif
    @if(is_numeric(request('branch_id')) && $branches->firstWhere('id', (int) request('branch_id')))
    'الفرع': @json($branches->firstWhere('id', (int) request('branch_id'))->name),
    @endif
    @if(request('type') === '1' || request('type') === '0')
    'النوع': @json(request('type') === '1' ? 'رئيسي' : 'فرعي'),
    @endif
    @if(request('status'))
    'الحالة': @json(request('status') === 'active' ? 'نشط' : (request('status') === 'inactive' ? 'غير نشط' : 'متوقف')),
    @endif
};

var storehousesInfoCards = [
    { label: 'إجمالي المخازن', value: '{{ $stats['total'] }}' },
    { label: 'مخازن نشطة', value: '{{ $stats['active'] }}', class: 'text-success' },
    { label: 'مخازن رئيسية', value: '{{ $stats['major'] }}' },
    { label: 'إجمالي الأرصدة', value: formatNumber({{ (float) $stats['balance'] }}, 2) + ' ' + window.AppCurrency.symbol }
];

// =====================================
// جمع بيانات جدول المخازن من DOM (يتوافق مع صفحة الترقيم الحالية)
// =====================================
function collectStorehousesRows() {
    var rows = [];

    $('#storehousesTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 7) return; // تجاهل صف "لا توجد بيانات"

        // اسم المخزن قد يتضمن شارة "رئيسي" مُدمجة - نستبعدها من نص الاسم
        var $title = $(cells[1]).find('.stk-media__title').clone();
        $title.find('.badge').remove();

        rows.push({
            rowNum: $(cells[0]).text().trim(),
            name: $title.text().trim(),
            branch: $(cells[2]).text().trim(),
            type: $(cells[3]).text().trim(),
            address: $(cells[4]).text().trim(),
            balance: $(cells[5]).find('.badge').text().trim() || $(cells[5]).text().trim(),
            balancePositive: $(cells[5]).find('.badge-light-success').length > 0,
            status: $(cells[6]).find('.stk-state').text().trim() || $(cells[6]).text().trim(),
            assignees: $(cells[7]).text().replace(/\s+/g, ' ').trim()
        });
    });

    return rows;
}

// =====================================
// الطباعة
// =====================================
function printStorehousesReport() {
    var rows = collectStorehousesRows();

    var $hidden = $('#storehousesPrintTable');
    if ($hidden.length === 0) {
        $hidden = $('<table id="storehousesPrintTable" style="display:none"><tbody></tbody></table>').appendTo('body');
    }

    var html = '';
    rows.forEach(function(r) {
        html += '<tr>' +
            '<td>' + $('<div>').text(r.rowNum).html() + '</td>' +
            '<td>' + $('<div>').text(r.name).html() + '</td>' +
            '<td>' + $('<div>').text(r.branch).html() + '</td>' +
            '<td>' + $('<div>').text(r.type).html() + '</td>' +
            '<td>' + $('<div>').text(r.address).html() + '</td>' +
            '<td>' + $('<div>').text(r.balance + ' ' + window.AppCurrency.symbol).html() + '</td>' +
            '<td>' + $('<div>').text(r.status).html() + '</td>' +
            '<td>' + $('<div>').text(r.assignees).html() + '</td>' +
            '</tr>';
    });
    $hidden.find('tbody').html(html);

    printTable({
        tableSelector: '#storehousesPrintTable tbody tr',
        title: 'قائمة المخازن - المتجر الإلكتروني',
        infoCards: storehousesInfoCards,
        filterInfo: storehousesFilterInfo,
        tableHeaders: ['#', 'اسم المخزن', 'الفرع', 'النوع', 'العنوان', 'الرصيد', 'الحالة', 'المسند إليهم'],
        columnWidths: ['4%', '18%', '12%', '8%', '16%', '14%', '10%', '18%'],
        descriptionColumn: 5,
        windowWidth: 1200,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportStorehousesReport() {
    var rows = collectStorehousesRows();

    var tableData = rows.map(function(r) {
        return {
            values: [
                r.rowNum,
                r.name,
                r.branch,
                r.type,
                r.address,
                r.balance + ' ' + window.AppCurrency.symbol,
                r.status,
                r.assignees
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' }, color: r.balancePositive ? 'success' : 'danger' },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } }
            ]
        };
    });

    var config = {
        fileName: 'قائمة_المخازن',
        mainTitle: 'قائمة المخازن - المتجر الإلكتروني',
        subTitle: 'تقرير مخصَّص',

        reportInfo: Object.assign({
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }, storehousesFilterInfo),

        statisticsCards: [
            { title: 'إجمالي المخازن', value: '{{ $stats['total'] }}', unit: ' مخزن', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'مخازن نشطة', value: '{{ $stats['active'] }}', unit: ' مخزن', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'مخازن رئيسية', value: '{{ $stats['major'] }}', unit: ' مخزن', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
            { title: 'إجمالي الأرصدة', value: formatNumber({{ (float) $stats['balance'] }}, 2), unit: ' ' + window.AppCurrency.symbol, titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' }
        ],

        tableTitle: 'قائمة المخازن',
        tableHeaders: ['#', 'اسم المخزن', 'الفرع', 'النوع', 'العنوان', 'الرصيد', 'الحالة', 'المسند إليهم'],
        columnWidths: [6, 30, 22, 14, 34, 18, 14, 30],

        tableData: tableData,

        buttonSelector: '#exportStorehousesExcel',
        successMessage: 'تم تصدير قائمة المخازن بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير القائمة'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
