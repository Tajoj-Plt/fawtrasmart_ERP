@extends('onlinestore::master')

@section('title', 'إدارة المنتجات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.products.create'))
                <a href="{{ route('store_products.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة منتج
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إدارة المنتجات',
            'icon' => 'package',
            'sub' => 'منتجات المتجر الإلكتروني وحالتها وأسعارها ومخزونها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المنتجات' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-package"></i></span>
                <span class="stk-metric__label">إجمالي المنتجات</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">منتجات نشطة</span>
                <span class="stk-metric__value">{{ $stats['active'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-globe"></i></span>
                <span class="stk-metric__label">متاح أونلاين</span>
                <span class="stk-metric__value">{{ $stats['available_online'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-star"></i></span>
                <span class="stk-metric__label">منتجات مميزة</span>
                <span class="stk-metric__value">{{ $stats['featured'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_products.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="اسم المنتج، الباركود…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="category_id">التصنيف</label>
                                <select name="category_id" id="category_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="brand_id">العلامة التجارية</label>
                                <select name="brand_id" id="brand_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>
                                            {{ $brand->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="active" @selected(request('status') == 'active')>نشط</option>
                                    <option value="unactive" @selected(request('status') == 'unactive')>غير نشط</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="available_online">متاح أونلاين</label>
                                <select name="available_online" id="available_online" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('available_online') == '1')>نعم</option>
                                    <option value="0" @selected(request('available_online') == '0')>لا</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="featured">منتج مميز</label>
                                <select name="featured" id="featured" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('featured') == '1')>نعم</option>
                                    <option value="0" @selected(request('featured') == '0')>لا</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_products.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="button" id="printProductsBtn" class="btn btn-outline-secondary">
                            <i class="feather icon-printer"></i> طباعة
                        </button>
                        @if (hasPermission('online_store.products.export'))
                            <button type="button" id="exportProductsBtn" class="btn btn-outline-success">
                                <i class="feather icon-file-text"></i> تصدير Excel
                            </button>
                        @endif
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card" id="reportCard">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-package"></i> قائمة المنتجات</h6>
                <span class="badge badge-light-primary">{{ $products->total() }} منتج</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="productsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>المنتج</th>
                            <th class="text-center">التصنيف</th>
                            <th class="text-center">السعر</th>
                            <th class="text-center">الكمية</th>
                            <th class="text-center">أونلاين</th>
                            <th class="text-center">مميز</th>
                            <th class="text-center">الحالة</th>
                            @if (hasPermission(['online_store.products.show', 'online_store.products.edit', 'online_store.products.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $images = json_decode($product->images, true);
                                $firstImage = is_array($images) && count($images) > 0 ? $images[0] : null;
                                $quantity = $product->totalQuantity();
                            @endphp
                            <tr>
                                <td class="text-center stk-num">{{ $product->id }}</td>
                                <td>
                                    <div class="stk-media">
                                        @if ($firstImage)
                                            <span class="stk-media__thumb"><img src="{{ asset($firstImage) }}"
                                                    alt="{{ $product->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                        @endif
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">
                                                {{ $product->name }}
                                                @if ($product->variants_count > 0)
                                                    <span class="stk-tag" title="له متغيرات">{{ $product->variants_count }} متغير</span>
                                                @endif
                                            </span>
                                            @if ($product->barcode)
                                                <span class="stk-media__sub">{{ $product->barcode }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if ($product->category)
                                        <span class="badge badge-light-primary">{{ $product->category->name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($product->hasDiscount())
                                        <strong class="text-success stk-num">{{ number_format($product->getFinalPrice(), 2) }}</strong> {{ \App\Support\CurrencySettings::symbol() }}
                                        <br>
                                        <small class="text-muted stk-num"><del>{{ number_format($product->sale_price, 2) }}</del></small>
                                        <br>
                                        <small class="text-danger stk-num">-{{ $product->discount }}{{ $product->getDiscountTypeText() }}</small>
                                    @else
                                        <strong class="stk-num">{{ number_format($product->sale_price, 2) }}</strong> {{ \App\Support\CurrencySettings::symbol() }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($quantity <= 0)
                                        <span class="badge badge-light-danger">نفذ</span>
                                    @elseif ($product->low_stock_alert && $quantity <= $product->low_stock_thershold)
                                        <span class="badge badge-light-warning stk-num">{{ $quantity }}</span>
                                    @else
                                        <span class="badge badge-light-success stk-num">{{ $quantity }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.products.toggle_status'))
                                        <div class="custom-control custom-switch d-inline-block">
                                            <input type="checkbox" class="custom-control-input toggle-online"
                                                id="online_{{ $product->id }}" data-id="{{ $product->id }}"
                                                {{ $product->available_online ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="online_{{ $product->id }}"></label>
                                        </div>
                                    @else
                                        <span class="stk-state {{ $product->available_online ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $product->available_online ? 'نعم' : 'لا' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.products.toggle_status'))
                                        <div class="custom-control custom-switch d-inline-block">
                                            <input type="checkbox" class="custom-control-input toggle-featured"
                                                id="featured_{{ $product->id }}" data-id="{{ $product->id }}"
                                                {{ $product->featured_product ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="featured_{{ $product->id }}"></label>
                                        </div>
                                    @else
                                        <i class="feather icon-star {{ $product->featured_product ? 'text-warning' : 'text-muted' }}"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="stk-state {{ $product->status->value == 'active' ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $product->status->value == 'active' ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </td>
                                @if (hasPermission(['online_store.products.show', 'online_store.products.edit', 'online_store.products.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" data-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-right">
                                                @if (hasPermission('online_store.products.show'))
                                                    <a class="dropdown-item" href="{{ route('store_products.show', $product) }}">
                                                        <i class="feather icon-eye"></i> عرض
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.products.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_products.edit', $product) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.products.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_products.destroy', $product) }}" method="POST" class="delete-form">
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
                                        <h6 class="stk-empty__title">لا توجد منتجات</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول منتج لمتجرك.</p>
                                        @if (hasPermission('online_store.products.create'))
                                            <a href="{{ route('store_products.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة منتج جديد
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $products, 'label' => 'نتيجة'])
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Initialize select2
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    // Toggle Online Status
    $('.toggle-online').change(function() {
        var productId = $(this).data('id');
        $.ajax({
            url: '/{{ app()->getLocale() }}/online_store/products/' + productId + '/toggle-online',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الحالة');
            }
        });
    });

    // Toggle Featured Status
    $('.toggle-featured').change(function() {
        var productId = $(this).data('id');
        $.ajax({
            url: '/{{ app()->getLocale() }}/online_store/products/' + productId + '/toggle-featured',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الحالة');
            }
        });
    });

    // Delete Product
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'تأكيد الحذف',
            text: 'هل أنت متأكد من حذف المنتج؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});

// =====================================
// طباعة وتصدير مخصّصان لقائمة المنتجات — نفس نمط
// Account\GeneralAccountsController (public/js/print-table.js +
// public/js/excel-export-helper.js). كانت "تصدير Excel" هنا تفتح
// StoreProductController::export() وهي دالةٌ فارغة ("ميزة قيد التطوير").
// =====================================
function buildProductsExportRows() {
    return [
        @foreach ($products as $product)
        @php
            $quantity = $product->totalQuantity();
        @endphp
        {
            name: @json($product->name),
            barcode: @json($product->barcode ?? '-'),
            category: @json($product->category->name ?? '-'),
            price: @json(number_format($product->hasDiscount() ? $product->getFinalPrice() : $product->sale_price, 2)),
            quantity: {{ (float) $quantity }},
            online: {{ $product->available_online ? 'true' : 'false' }},
            featured: {{ $product->featured_product ? 'true' : 'false' }},
            status: @json($product->status->value == 'active' ? 'نشط' : 'غير نشط'),
        },
        @endforeach
    ];
}

function printProductsList() {
    const rows = buildProductsExportRows();

    printTable({
        tableSelector: '#productsTable tbody tr',
        title: 'تقرير المنتجات',
        infoCards: [
            { label: 'إجمالي المنتجات', value: '{{ $stats['total'] ?? 0 }}' },
            { label: 'نشطة', value: '{{ $stats['active'] ?? 0 }}', class: 'text-success' },
            { label: 'متاح أونلاين', value: '{{ $stats['available_online'] ?? 0 }}' },
            { label: 'مميزة', value: '{{ $stats['featured'] ?? 0 }}' }
        ],
        filterInfo: {
            @if(request('search')) 'بحث': @json(request('search')), @endif
            @if(request('category_id')) 'التصنيف': @json(optional($categories->firstWhere('id', request('category_id')))->name), @endif
            @if(request('brand_id')) 'العلامة التجارية': @json(optional($brands->firstWhere('id', request('brand_id')))->name), @endif
            @if(request('status')) 'الحالة': @json(request('status') == 'active' ? 'نشط' : 'غير نشط'), @endif
        },
        tableHeaders: ['#', 'المنتج', 'الباركود', 'التصنيف', 'السعر', 'الكمية', 'أونلاين', 'مميز', 'الحالة'],
        columnWidths: ['4%', '22%', '12%', '12%', '10%', '9%', '9%', '8%', '14%'],
        skipColumns: [8]
    });
}

function exportProductsListExcel() {
    const rows = buildProductsExportRows();

    const config = {
        fileName: 'تقرير_المنتجات',
        mainTitle: 'تقرير المنتجات',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
        },

        statisticsCards: [
            { title: 'إجمالي المنتجات', value: '{{ $stats['total'] ?? 0 }}', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'نشطة', value: '{{ $stats['active'] ?? 0 }}', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'متاح أونلاين', value: '{{ $stats['available_online'] ?? 0 }}', titleColor: 'gray', valueColor: 'gray', backgroundColor: 'grayLight', borderColor: 'gray' },
            { title: 'مميزة', value: '{{ $stats['featured'] ?? 0 }}', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' }
        ],

        tableTitle: 'قائمة المنتجات',
        tableHeaders: ['#', 'المنتج', 'الباركود', 'التصنيف', 'السعر (' + window.AppCurrency.symbol + ')', 'الكمية', 'أونلاين', 'مميز', 'الحالة'],
        columnWidths: [5, 28, 14, 16, 14, 10, 10, 8, 12],

        tableData: rows.map((row, index) => ({
            values: [
                index + 1, row.name, row.barcode, row.category, row.price, row.quantity,
                row.online ? 'نعم' : 'لا', row.featured ? 'نعم' : 'لا', row.status
            ],
            cellStyles: [
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

        buttonSelector: '#exportProductsBtn',
        successMessage: 'تم تصدير تقرير المنتجات بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}

$('#printProductsBtn').on('click', printProductsList);
$('#exportProductsBtn').on('click', exportProductsListExcel);
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
@endsection
