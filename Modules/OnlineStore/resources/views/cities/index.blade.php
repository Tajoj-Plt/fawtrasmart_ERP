@extends('onlinestore::master')

@section('title', 'المدن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.geo.create'))
                <a href="{{ route('cities.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة مدينة
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'المدن',
            'icon' => 'map-pin',
            'sub' => 'مدن الدول المتاحة للشحن والفوترة في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المدن' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-map-pin"></i></span>
                <span class="stk-metric__label">إجمالي المدن</span>
                <span class="stk-metric__value">{{ $cities->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مدن نشطة</span>
                <span class="stk-metric__value">{{ $cities->where('status', true)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">مدن غير نشطة</span>
                <span class="stk-metric__value">{{ $cities->where('status', false)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-globe"></i></span>
                <span class="stk-metric__label">عدد الدول</span>
                <span class="stk-metric__value">{{ $countries->count() ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('cities.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="ابحث باسم المدينة…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="country_id">الدولة</label>
                                <select name="country_id" id="country_id" class="form-control select2">
                                    <option value="">جميع الدول</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}" @selected(request('country_id') == $country->id)>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('status') === '1')>نشطة</option>
                                    <option value="0" @selected(request('status') === '0')>غير نشطة</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('cities.index') }}" class="btn btn-outline-secondary">
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة المدن</h6>
                <span class="badge badge-light-primary">{{ $cities->total() }} مدينة</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="citiesTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>اسم المدينة</th>
                            <th>الدولة</th>
                            <th class="text-center">الحالة</th>
                            @if (hasPermission(['online_store.geo.view', 'online_store.geo.edit', 'online_store.geo.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cities as $city)
                            <tr>
                                <td class="text-center stk-num">{{ $loop->iteration + ($cities->currentPage() - 1) * $cities->perPage() }}</td>
                                <td>
                                    <strong>{{ $city->name }}</strong>
                                </td>
                                <td>
                                    <a href="{{ route('countries.show', $city->country) }}" class="badge badge-light-primary">
                                        <i class="feather icon-globe"></i> {{ $city->country->name }}
                                    </a>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.geo.toggle_status'))
                                        <span class="stk-state {{ $city->status ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $city->id }}" data-url="{{ route('cities.toggle-status', $city) }}">
                                            {{ $city->status ? 'نشطة' : 'متوقفة' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $city->status ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $city->status ? 'نشطة' : 'متوقفة' }}
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
                                                    <a class="dropdown-item" href="{{ route('cities.show', $city) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.geo.edit'))
                                                    <a class="dropdown-item" href="{{ route('cities.edit', $city) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.geo.view'))
                                                    <a class="dropdown-item" href="{{ route('countries.show', $city->country) }}">
                                                        <i class="feather icon-globe"></i> عرض الدولة
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.geo.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('cities.destroy', $city) }}" method="POST" class="delete-form">
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
                                <td colspan="5">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-map-pin"></i></span>
                                        <h6 class="stk-empty__title">لا توجد مدن</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول مدينة لمتجرك.</p>
                                        @if (hasPermission('online_store.geo.create'))
                                            <a href="{{ route('cities.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة مدينة
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $cities, 'label' => 'مدينة'])
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

    $('#printBtn').click(function() {
        printCitiesReport();
    });

    $('#exportExcel').click(function() {
        exportCitiesReport();
    });

    $('.toggle-status').click(function() {
        var btn = $(this);
        var url = btn.data('url');

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    btn.toggleClass('stk-state--on stk-state--off');
                    btn.text(response.status ? 'نشطة' : 'متوقفة');
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
            text: 'سيتم حذف هذه المدينة نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });
});

// =====================================
// بيانات مشتركة للطباعة والتصدير
// =====================================
@php
    $citiesStatusLabel = 'الكل';
    if (request('status') === '1') {
        $citiesStatusLabel = 'نشطة';
    } elseif (request('status') === '0') {
        $citiesStatusLabel = 'غير نشطة';
    }

    $citiesSelectedCountryName = null;
    if (request('country_id')) {
        $citiesSelectedCountryName = optional($countries->firstWhere('id', (int) request('country_id')))->name;
    }
@endphp

function getCitiesFilterInfo() {
    return {
        'البحث': '{{ request('search') ? addslashes(request('search')) : 'الكل' }}',
        'الدولة': '{{ $citiesSelectedCountryName ? addslashes($citiesSelectedCountryName) : 'جميع الدول' }}',
        'الحالة': '{{ $citiesStatusLabel }}'
    };
}

function getCitiesTableData() {
    var rows = [];
    $('#citiesTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 4) return;

        rows.push({
            num: $(cells[0]).text().trim(),
            name: $(cells[1]).text().trim(),
            country: $(cells[2]).text().trim(),
            status: $(cells[3]).text().trim()
        });
    });
    return rows;
}

// =====================================
// الطباعة
// =====================================
function printCitiesReport() {
    printTable({
        tableSelector: '#citiesTable tbody tr',
        title: 'تقرير المدن',
        infoCards: [
            { label: 'إجمالي المدن', value: '{{ $cities->total() ?? 0 }}' },
            { label: 'مدن نشطة', value: '{{ $cities->where('status', true)->count() ?? 0 }}', class: 'text-success' },
            { label: 'مدن غير نشطة', value: '{{ $cities->where('status', false)->count() ?? 0 }}', class: 'text-danger' },
            { label: 'عدد الدول', value: '{{ $countries->count() ?? 0 }}' }
        ],
        filterInfo: getCitiesFilterInfo(),
        tableHeaders: ['#', 'اسم المدينة', 'الدولة', 'الحالة'],
        columnWidths: ['8%', '38%', '32%', '22%'],
        skipColumns: [4],
        windowWidth: 1200,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportCitiesReport() {
    var tableRows = getCitiesTableData();
    var tableData = tableRows.map(function(row) {
        return {
            values: [row.num, row.name, row.country, row.status],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } }
            ]
        };
    });

    var config = {
        fileName: 'تقرير_المدن',
        mainTitle: 'تقرير المدن',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: Object.assign({}, getCitiesFilterInfo(), {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }),

        statisticsCards: [
            {
                title: 'إجمالي المدن',
                value: '{{ $cities->total() ?? 0 }}',
                unit: ' مدينة',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'مدن نشطة',
                value: '{{ $cities->where('status', true)->count() ?? 0 }}',
                unit: ' مدينة',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'مدن غير نشطة',
                value: '{{ $cities->where('status', false)->count() ?? 0 }}',
                unit: ' مدينة',
                titleColor: 'danger',
                valueColor: 'danger',
                backgroundColor: 'dangerLight',
                borderColor: 'danger'
            },
            {
                title: 'عدد الدول',
                value: '{{ $countries->count() ?? 0 }}',
                unit: ' دولة',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            }
        ],

        tableTitle: 'قائمة المدن',
        tableHeaders: ['#', 'اسم المدينة', 'الدولة', 'الحالة'],
        columnWidths: [8, 34, 30, 18],

        tableData: tableData,

        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير تقرير المدن بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
