@extends('onlinestore::master')

@section('title', 'أصناف المنتجات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.categories.create'))
                <a href="{{ route('store_categories.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة صنف
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'أصناف المنتجات',
            'icon' => 'folder',
            'sub' => 'أصناف وفئات المنتجات المعروضة في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الأصناف' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-folder"></i></span>
                <span class="stk-metric__label">إجمالي الأصناف</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-grid"></i></span>
                <span class="stk-metric__label">أصناف رئيسية</span>
                <span class="stk-metric__value">{{ $stats['main_categories'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">أصناف فرعية</span>
                <span class="stk-metric__value">{{ $stats['sub_categories'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">أصناف بها منتجات</span>
                <span class="stk-metric__value">{{ $stats['with_products'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_categories.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="اسم الصنف…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="main_category">الصنف الرئيسي</label>
                                <select name="main_category" id="main_category" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="main" @selected(request('main_category') == 'main')>الأصناف الرئيسية فقط</option>
                                    @foreach ($mainCategories as $mainCat)
                                        <option value="{{ $mainCat->id }}" @selected(request('main_category') == $mainCat->id)>
                                            {{ $mainCat->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_categories.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        <button type="button" id="printCategoriesBtn" class="btn btn-success">
                            <i class="feather icon-printer"></i> طباعة
                        </button>
                        <button type="button" id="exportCategoriesExcel" class="btn btn-info">
                            <i class="feather icon-file-text"></i> Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $categoriesHasActions = hasPermission(['online_store.categories.view', 'online_store.categories.edit', 'online_store.categories.delete']);
        @endphp

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الأصناف</h6>
                <span class="badge badge-light-primary">{{ $categories->total() }} صنف</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="categoriesTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>الصورة</th>
                            <th>اسم الصنف</th>
                            <th class="text-center">الصنف الرئيسي</th>
                            <th class="text-center">عدد المنتجات</th>
                            @if (hasPermission(['online_store.categories.view', 'online_store.categories.edit', 'online_store.categories.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td class="text-center stk-num">{{ $category->id }}</td>
                                <td>
                                    @if ($category->attachments)
                                        <img src="{{ asset($category->attachments) }}" alt="{{ $category->name }}"
                                            class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                            style="width: 50px; height: 50px;">
                                            <i class="feather icon-folder text-muted"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $category->name }}</strong>
                                    @if ($category->discretion)
                                        <small class="text-muted d-block">{{ Str::limit($category->discretion, 50) }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($category->main_category)
                                        @php
                                            $parent = $mainCategories->firstWhere('id', $category->main_category);
                                        @endphp
                                        @if ($parent)
                                            <span class="badge badge-light-info">{{ $parent->name }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    @else
                                        <span class="badge badge-light-primary">صنف رئيسي</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ $category->products_count > 0 ? 'success' : 'secondary' }}">
                                        {{ $category->products_count ?? 0 }}
                                    </span>
                                </td>
                                @if (hasPermission(['online_store.categories.view', 'online_store.categories.edit', 'online_store.categories.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.categories.view'))
                                                    <a class="dropdown-item" href="{{ route('store_categories.show', $category) }}">
                                                        <i class="feather icon-eye"></i> عرض
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.categories.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_categories.edit', $category) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.categories.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_categories.destroy', $category) }}" method="POST" class="delete-form">
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
                                <td colspan="6">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-folder"></i></span>
                                        <h6 class="stk-empty__title">لا توجد أصناف</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول صنف لمتجرك.</p>
                                        @if (hasPermission('online_store.categories.create'))
                                            <a href="{{ route('store_categories.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة صنف
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $categories, 'label' => 'نتيجة'])
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

    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا الصنف نهائياً!',
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

    // طباعة قائمة الأصناف
    $('#printCategoriesBtn').click(function() {
        printCategoriesReport();
    });

    // تصدير قائمة الأصناف إلى Excel
    $('#exportCategoriesExcel').click(function() {
        exportCategoriesReport();
    });
});

function categoriesFilterInfo() {
    var f = {};
    @if(request('search'))
    f['البحث'] = @json(request('search'));
    @endif
    @if(request('main_category'))
    f['الصنف الرئيسي'] = @json(request('main_category') == 'main' ? 'الأصناف الرئيسية فقط' : (optional($mainCategories->firstWhere('id', request('main_category')))->name ?? request('main_category')));
    @endif
    return f;
}

// =====================================
// طباعة تقرير الأصناف
// =====================================
function printCategoriesReport() {
    printTable({
        tableSelector: '#categoriesTable tbody tr',
        title: 'تقرير أصناف المنتجات',
        infoCards: [
            { label: 'إجمالي الأصناف', value: '{{ $stats['total'] ?? 0 }}' },
            { label: 'أصناف رئيسية', value: '{{ $stats['main_categories'] ?? 0 }}' },
            { label: 'أصناف فرعية', value: '{{ $stats['sub_categories'] ?? 0 }}' },
            { label: 'أصناف بها منتجات', value: '{{ $stats['with_products'] ?? 0 }}' }
        ],
        filterInfo: categoriesFilterInfo(),
        tableHeaders: ['#', 'الصورة', 'اسم الصنف', 'الصنف الرئيسي', 'عدد المنتجات', 'الإجراءات'],
        columnWidths: ['8%', '12%', '35%', '20%', '15%', '10%'],
        skipColumns: [1{{ $categoriesHasActions ? ', 5' : '' }}]
    });
}

// =====================================
// تصدير تقرير الأصناف إلى Excel
// =====================================
function exportCategoriesReport() {
    var tableData = [];

    $('#categoriesTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 5) return; // صف الحالة الفارغة

        var name = $(cells[2]).find('strong').first().text().trim() || $(cells[2]).text().trim();

        tableData.push({
            values: [
                $(cells[0]).text().trim(),
                name,
                $(cells[3]).text().trim(),
                $(cells[4]).text().trim()
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    });

    var reportInfo = categoriesFilterInfo();
    reportInfo['تاريخ التصدير'] = new Date().toLocaleDateString('ar-SA');

    var config = {
        fileName: 'تقرير_أصناف_المنتجات',
        mainTitle: 'تقرير أصناف المنتجات',
        subTitle: 'المتجر الإلكتروني',
        reportInfo: reportInfo,
        statisticsCards: [
            { title: 'إجمالي الأصناف', value: '{{ $stats['total'] ?? 0 }}', unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'أصناف رئيسية', value: '{{ $stats['main_categories'] ?? 0 }}', unit: '', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'أصناف فرعية', value: '{{ $stats['sub_categories'] ?? 0 }}', unit: '', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
            { title: 'أصناف بها منتجات', value: '{{ $stats['with_products'] ?? 0 }}', unit: '', titleColor: 'purple', valueColor: 'purple', backgroundColor: 'purpleLight', borderColor: 'purple' }
        ],
        tableTitle: 'قائمة الأصناف',
        tableHeaders: ['#', 'اسم الصنف', 'الصنف الرئيسي', 'عدد المنتجات'],
        columnWidths: [8, 35, 25, 20],
        tableData: tableData,
        buttonSelector: '#exportCategoriesExcel',
        successMessage: 'تم تصدير تقرير الأصناف بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
