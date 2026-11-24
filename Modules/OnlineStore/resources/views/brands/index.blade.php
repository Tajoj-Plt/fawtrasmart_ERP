@extends('onlinestore::master')

@section('title', 'العلامات التجارية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.brands.create'))
                <a href="{{ route('brands.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة علامة تجارية
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'العلامات التجارية',
            'icon' => 'award',
            'sub' => 'العلامات التجارية للمنتجات المعروضة في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'العلامات التجارية' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-tag"></i></span>
                <span class="stk-metric__label">إجمالي العلامات</span>
                <span class="stk-metric__value">{{ $brands->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">علامات نشطة</span>
                <span class="stk-metric__value">{{ $brands->where('is_active', true)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">علامات مميزة</span>
                <span class="stk-metric__value">{{ $brands->where('is_featured', true)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">إجمالي المنتجات</span>
                <span class="stk-metric__value">{{ $brands->sum('products_count') ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('brands.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="بحث بالاسم أو الوصف…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="active" @selected(request('status') == 'active')>نشط</option>
                                    <option value="inactive" @selected(request('status') == 'inactive')>غير نشط</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="featured">علامة مميزة</label>
                                <select name="featured" id="featured" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="yes" @selected(request('featured') == 'yes')>مميز</option>
                                    <option value="no" @selected(request('featured') == 'no')>غير مميز</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">
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
                <h6 class="stk-table-head__title"><i class="feather icon-layers"></i> قائمة العلامات التجارية</h6>
                <span class="badge badge-light-primary">{{ $brands->total() }} علامة</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="brandsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>العلامة التجارية</th>
                            <th class="text-center">المنتجات</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">مميز</th>
                            <th class="text-center">الترتيب</th>
                            @if (hasPermission(['online_store.brands.edit', 'online_store.brands.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($brands as $brand)
                            <tr>
                                <td class="text-center stk-num">{{ $brand->id }}</td>
                                <td>
                                    <div class="stk-media">
                                        @if ($brand->logo)
                                            <span class="stk-media__thumb"><img src="{{ asset($brand->logo) }}"
                                                    alt="{{ $brand->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                        @endif
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $brand->name }}</span>
                                            <span class="stk-media__sub">{{ $brand->slug }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-info">
                                        <i class="feather icon-box"></i> {{ $brand->products_count ?? 0 }} منتج
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.brands.toggle_status'))
                                        <span class="stk-state {{ $brand->is_active ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $brand->id }}" data-url="{{ route('brands.toggle-status', $brand) }}">
                                            {{ $brand->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $brand->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $brand->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.brands.toggle_status'))
                                        <button type="button" class="btn btn-sm btn-link toggle-featured p-0"
                                            data-id="{{ $brand->id }}" data-url="{{ route('brands.toggle-featured', $brand) }}">
                                            <i class="feather icon-star {{ $brand->is_featured ? 'text-warning' : 'text-muted' }}"></i>
                                        </button>
                                    @else
                                        <i class="feather icon-star {{ $brand->is_featured ? 'text-warning' : 'text-muted' }}"></i>
                                    @endif
                                    {{-- نصٌّ مخفيّ للعرض فقط، لا يظهر على الشاشة: أيقونة النجمة لا تحمل نصاً يُقرأ عند
                                         الطباعة أو التصدير (بلا خط الأيقونات في النافذة الجديدة). --}}
                                    <span class="d-none stk-print-text">{{ $brand->is_featured ? 'مميز' : 'غير مميز' }}</span>
                                </td>
                                <td class="text-center stk-num">{{ $brand->sort_order }}</td>
                                @if (hasPermission(['online_store.brands.view', 'online_store.brands.edit', 'online_store.brands.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.brands.view'))
                                                    <a class="dropdown-item" href="{{ route('brands.show', $brand) }}">
                                                        <i class="feather icon-eye"></i> عرض
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.brands.edit'))
                                                    <a class="dropdown-item" href="{{ route('brands.edit', $brand) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.brands.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('brands.destroy', $brand) }}" method="POST" class="delete-form">
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
                                <td colspan="7">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-award"></i></span>
                                        <h6 class="stk-empty__title">لا توجد علامات تجارية</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول علامة تجارية لمتجرك.</p>
                                        @if (hasPermission('online_store.brands.create'))
                                            <a href="{{ route('brands.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة علامة تجارية
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $brands, 'label' => 'نتيجة'])
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
        printBrandsReport();
    });

    $('#exportExcel').click(function() {
        exportBrandsReport();
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
                    btn.text(response.is_active ? 'نشط' : 'غير نشط');
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الحالة');
            }
        });
    });

    $('.toggle-featured').click(function() {
        var btn = $(this);
        var icon = btn.find('i');

        $.ajax({
            url: btn.data('url'),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    icon.toggleClass('text-warning text-muted');
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث حالة المميز');
            }
        });
    });

    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه العلامة التجارية نهائياً!',
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
    $brandsStatusLabel = 'الكل';
    if (request('status') == 'active') {
        $brandsStatusLabel = 'نشط';
    } elseif (request('status') == 'inactive') {
        $brandsStatusLabel = 'غير نشط';
    }

    $brandsFeaturedLabel = 'الكل';
    if (request('featured') == 'yes') {
        $brandsFeaturedLabel = 'مميز';
    } elseif (request('featured') == 'no') {
        $brandsFeaturedLabel = 'غير مميز';
    }
@endphp

function getBrandsFilterInfo() {
    return {
        'البحث': '{{ request('search') ? addslashes(request('search')) : 'الكل' }}',
        'الحالة': '{{ $brandsStatusLabel }}',
        'مميز': '{{ $brandsFeaturedLabel }}'
    };
}

function getBrandsTableData() {
    var rows = [];
    $('#brandsTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 6) return;

        var nameCell = $(cells[1]);
        var title = nameCell.find('.stk-media__title').text().trim();
        var sub = nameCell.find('.stk-media__sub').text().trim();
        var name = sub ? (title + ' - ' + sub) : title;

        rows.push({
            num: $(cells[0]).text().trim(),
            name: name,
            products: $(cells[2]).text().trim(),
            status: $(cells[3]).text().trim(),
            featured: $(cells[4]).find('.stk-print-text').text().trim(),
            sortOrder: $(cells[5]).text().trim()
        });
    });
    return rows;
}

// =====================================
// الطباعة
// =====================================
function printBrandsReport() {
    printTable({
        tableSelector: '#brandsTable tbody tr',
        title: 'تقرير العلامات التجارية',
        infoCards: [
            { label: 'إجمالي العلامات', value: '{{ $brands->total() ?? 0 }}' },
            { label: 'علامات نشطة', value: '{{ $brands->where('is_active', true)->count() ?? 0 }}', class: 'text-success' },
            { label: 'علامات مميزة', value: '{{ $brands->where('is_featured', true)->count() ?? 0 }}', class: 'text-warning' },
            { label: 'إجمالي المنتجات', value: '{{ $brands->sum('products_count') ?? 0 }}' }
        ],
        filterInfo: getBrandsFilterInfo(),
        tableHeaders: ['#', 'العلامة التجارية', 'المنتجات', 'الحالة', 'مميز', 'الترتيب'],
        columnWidths: ['6%', '34%', '16%', '16%', '14%', '14%'],
        skipColumns: [6],
        windowWidth: 1200,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportBrandsReport() {
    var tableRows = getBrandsTableData();
    var tableData = tableRows.map(function(row) {
        return {
            values: [row.num, row.name, row.products, row.status, row.featured, row.sortOrder],
            cellStyles: [
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
        fileName: 'تقرير_العلامات_التجارية',
        mainTitle: 'تقرير العلامات التجارية',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: Object.assign({}, getBrandsFilterInfo(), {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }),

        statisticsCards: [
            {
                title: 'إجمالي العلامات',
                value: '{{ $brands->total() ?? 0 }}',
                unit: ' علامة',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'علامات نشطة',
                value: '{{ $brands->where('is_active', true)->count() ?? 0 }}',
                unit: ' علامة',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'علامات مميزة',
                value: '{{ $brands->where('is_featured', true)->count() ?? 0 }}',
                unit: ' علامة',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            },
            {
                title: 'إجمالي المنتجات',
                value: '{{ $brands->sum('products_count') ?? 0 }}',
                unit: ' منتج',
                titleColor: 'purple',
                valueColor: 'purple',
                backgroundColor: 'purpleLight',
                borderColor: 'purple'
            }
        ],

        tableTitle: 'قائمة العلامات التجارية',
        tableHeaders: ['#', 'العلامة التجارية', 'المنتجات', 'الحالة', 'مميز', 'الترتيب'],
        columnWidths: [6, 34, 16, 14, 14, 12],

        tableData: tableData,

        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير تقرير العلامات التجارية بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
