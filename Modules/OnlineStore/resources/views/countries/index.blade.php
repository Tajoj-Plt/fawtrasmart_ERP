@extends('onlinestore::master')

@section('title', 'الدول')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.geo.create'))
                <a href="{{ route('countries.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة دولة
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'الدول',
            'icon' => 'globe',
            'sub' => 'الدول المتاحة للشحن والفوترة في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الدول' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-globe"></i></span>
                <span class="stk-metric__label">إجمالي الدول</span>
                <span class="stk-metric__value">{{ $countries->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">دول نشطة</span>
                <span class="stk-metric__value">{{ $countries->where('status', true)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">دول غير نشطة</span>
                <span class="stk-metric__value">{{ $countries->where('status', false)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-map-pin"></i></span>
                <span class="stk-metric__label">إجمالي المدن</span>
                <span class="stk-metric__value">{{ $countries->sum('cities_count') ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('countries.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="ابحث بالاسم، الكود أو كود الهاتف…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('status') === '1')>نشط</option>
                                    <option value="0" @selected(request('status') === '0')>غير نشط</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('countries.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        <button type="button" id="printBtn" class="btn btn-success">
                            <i class="feather icon-printer"></i> طباعة
                        </button>
                        <button type="button" id="exportExcel" class="btn btn-info">
                            <i class="feather icon-file-text"></i> Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الدول</h6>
                <span class="badge badge-light-primary">{{ $countries->total() }} دولة</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="countriesTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>اسم الدولة</th>
                            <th class="text-center">الكود</th>
                            <th class="text-center">كود الهاتف</th>
                            <th class="text-center">عدد المدن</th>
                            <th class="text-center">افتراضية</th>
                            <th class="text-center">الحالة</th>
                            @if (hasPermission(['online_store.geo.view', 'online_store.geo.edit', 'online_store.geo.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($countries as $country)
                            <tr>
                                <td class="text-center stk-num">{{ $loop->iteration + ($countries->currentPage() - 1) * $countries->perPage() }}</td>
                                <td>
                                    <strong>{{ $country->name }}</strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-info">{{ $country->code }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="text-muted stk-num">+{{ $country->phone_code }}</span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('cities.index', ['country_id' => $country->id]) }}"
                                        class="badge badge-light-primary">
                                        {{ $country->cities_count }} مدينة
                                    </a>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.geo.toggle_status'))
                                        <span class="stk-state {{ $country->is_default ? 'stk-state--on' : 'stk-state--off' }} toggle-default cursor-pointer"
                                            data-id="{{ $country->id }}" data-url="{{ route('countries.toggle-default', $country) }}">
                                            {{ $country->is_default ? 'افتراضية' : 'عادية' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $country->is_default ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $country->is_default ? 'افتراضية' : 'عادية' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.geo.toggle_status'))
                                        <span class="stk-state {{ $country->status ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $country->id }}" data-url="{{ route('countries.toggle-status', $country) }}">
                                            {{ $country->status ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $country->status ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $country->status ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @endif
                                </td>
                                @if (hasPermission(['online_store.geo.view', 'online_store.geo.edit', 'online_store.geo.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.geo.view'))
                                                    <a class="dropdown-item" href="{{ route('countries.show', $country) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.geo.edit'))
                                                    <a class="dropdown-item" href="{{ route('countries.edit', $country) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.geo.view'))
                                                    <a class="dropdown-item" href="{{ route('cities.index', ['country_id' => $country->id]) }}">
                                                        <i class="feather icon-map-pin"></i> عرض المدن
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.geo.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('countries.destroy', $country) }}" method="POST" class="delete-form">
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
                                        <span class="stk-empty__icon"><i class="feather icon-globe"></i></span>
                                        <h6 class="stk-empty__title">لا توجد دول</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول دولة لمتجرك.</p>
                                        @if (hasPermission('online_store.geo.create'))
                                            <a href="{{ route('countries.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة دولة
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $countries, 'label' => 'دولة'])
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    $('#printBtn').click(function() {
        printCountriesReport();
    });

    $('#exportExcel').click(function() {
        exportCountriesReport();
    });

    $('.toggle-default').click(function() {
        var el = $(this);

        $.ajax({
            url: el.data('url'),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    $('.toggle-default').removeClass('stk-state--on').addClass('stk-state--off').text('عادية');
                    el.toggleClass('stk-state--off stk-state--on');
                    el.text(response.is_default ? 'افتراضية' : 'عادية');
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message || 'حدث خطأ أثناء التحديث');
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الدولة الافتراضية');
            }
        });
    });

    $('.toggle-status').click(function() {
        var btn = $(this);
        $.ajax({
            url: btn.data('url'),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    btn.toggleClass('stk-state--on stk-state--off');
                    btn.text(response.status ? 'نشط' : 'متوقف');
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الحالة');
            }
        });
    });

    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه الدولة نهائياً!',
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
// بيانات مشتركة للطباعة والتصدير
// =====================================
@php
    $countriesStatusLabel = 'الكل';
    if (request('status') === '1') {
        $countriesStatusLabel = 'نشط';
    } elseif (request('status') === '0') {
        $countriesStatusLabel = 'غير نشط';
    }
@endphp

function getCountriesFilterInfo() {
    return {
        'البحث': '{{ request('search') ? addslashes(request('search')) : 'الكل' }}',
        'الحالة': '{{ $countriesStatusLabel }}'
    };
}

function getCountriesTableData() {
    var rows = [];
    $('#countriesTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 7) return;

        rows.push({
            num: $(cells[0]).text().trim(),
            name: $(cells[1]).text().trim(),
            code: $(cells[2]).text().trim(),
            phoneCode: $(cells[3]).text().trim(),
            citiesCount: $(cells[4]).text().trim(),
            isDefault: $(cells[5]).text().trim(),
            status: $(cells[6]).text().trim()
        });
    });
    return rows;
}

// =====================================
// الطباعة
// =====================================
function printCountriesReport() {
    printTable({
        tableSelector: '#countriesTable tbody tr',
        title: 'تقرير الدول',
        infoCards: [
            { label: 'إجمالي الدول', value: '{{ $countries->total() ?? 0 }}' },
            { label: 'دول نشطة', value: '{{ $countries->where('status', true)->count() ?? 0 }}', class: 'text-success' },
            { label: 'دول غير نشطة', value: '{{ $countries->where('status', false)->count() ?? 0 }}', class: 'text-danger' },
            { label: 'إجمالي المدن', value: '{{ $countries->sum('cities_count') ?? 0 }}' }
        ],
        filterInfo: getCountriesFilterInfo(),
        tableHeaders: ['#', 'اسم الدولة', 'الكود', 'كود الهاتف', 'عدد المدن', 'افتراضية', 'الحالة'],
        columnWidths: ['6%', '25%', '12%', '12%', '14%', '14%', '17%'],
        skipColumns: [7],
        windowWidth: 1200,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportCountriesReport() {
    var tableRows = getCountriesTableData();
    var tableData = tableRows.map(function(row) {
        return {
            values: [row.num, row.name, row.code, row.phoneCode, row.citiesCount, row.isDefault, row.status],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        };
    });

    var config = {
        fileName: 'تقرير_الدول',
        mainTitle: 'تقرير الدول',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: Object.assign({}, getCountriesFilterInfo(), {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }),

        statisticsCards: [
            {
                title: 'إجمالي الدول',
                value: '{{ $countries->total() ?? 0 }}',
                unit: ' دولة',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'دول نشطة',
                value: '{{ $countries->where('status', true)->count() ?? 0 }}',
                unit: ' دولة',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'دول غير نشطة',
                value: '{{ $countries->where('status', false)->count() ?? 0 }}',
                unit: ' دولة',
                titleColor: 'danger',
                valueColor: 'danger',
                backgroundColor: 'dangerLight',
                borderColor: 'danger'
            },
            {
                title: 'إجمالي المدن',
                value: '{{ $countries->sum('cities_count') ?? 0 }}',
                unit: ' مدينة',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            }
        ],

        tableTitle: 'قائمة الدول',
        tableHeaders: ['#', 'اسم الدولة', 'الكود', 'كود الهاتف', 'عدد المدن', 'افتراضية', 'الحالة'],
        columnWidths: [6, 30, 14, 14, 16, 16, 14],

        tableData: tableData,

        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير تقرير الدول بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
