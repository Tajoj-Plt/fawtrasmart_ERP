@extends('onlinestore::master')

@section('title', 'صفحات المتجر')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.pages.create'))
                <a href="{{ route('store_pages.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة صفحة جديدة
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'صفحات المتجر',
            'icon' => 'file-text',
            'sub' => 'الصفحات الثابتة والسياسات والأسئلة الشائعة في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'صفحات المتجر' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">إجمالي الصفحات</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">صفحات نشطة</span>
                <span class="stk-metric__value">{{ $stats['active'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file"></i></span>
                <span class="stk-metric__label">صفحات عادية</span>
                <span class="stk-metric__value">{{ $stats['pages'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shield"></i></span>
                <span class="stk-metric__label">السياسات</span>
                <span class="stk-metric__value">{{ $stats['policies'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-help-circle"></i></span>
                <span class="stk-metric__label">أسئلة شائعة</span>
                <span class="stk-metric__value">{{ $stats['faqs'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_pages.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="بحث بالعنوان أو المحتوى…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="type">النوع</label>
                                <select name="type" id="type" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="page" @selected(request('type') == 'page')>صفحة عادية</option>
                                    <option value="policy" @selected(request('type') == 'policy')>سياسة</option>
                                    <option value="faq" @selected(request('type') == 'faq')>أسئلة شائعة</option>
                                </select>
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
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_pages.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        <button type="button" id="printPagesBtn" class="btn btn-success">
                            <i class="feather icon-printer"></i> طباعة
                        </button>
                        <button type="button" id="exportPagesExcel" class="btn btn-info">
                            <i class="feather icon-file-text"></i> Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $pagesHasCheckbox = hasPermission(['online_store.pages.toggle_status', 'online_store.pages.delete']);
            $pagesHasActions = hasPermission(['online_store.pages.view', 'online_store.pages.edit', 'online_store.pages.create', 'online_store.pages.delete']);
            $pagesTypeLabel = match(request('type')) {
                'page' => 'صفحة عادية',
                'policy' => 'سياسة',
                'faq' => 'أسئلة شائعة',
                default => request('type'),
            };
            $pagesStatusLabel = request('status') == 'active' ? 'نشط' : 'غير نشط';
        @endphp

        @if (hasPermission(['online_store.pages.toggle_status', 'online_store.pages.delete']))
            <div class="bulk-actions-bar d-none mb-2" id="bulkActionsBar">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="selected-count">0</span> صفحة محددة
                    </div>
                    <div>
                        @if (hasPermission('online_store.pages.toggle_status'))
                            <button type="button" class="btn btn-success btn-sm" id="bulkActivate">
                                <i class="feather icon-check"></i> تفعيل المحدد
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" id="bulkDeactivate">
                                <i class="feather icon-x"></i> إلغاء تفعيل المحدد
                            </button>
                        @endif
                        @if (hasPermission('online_store.pages.delete'))
                            <button type="button" class="btn btn-danger btn-sm" id="bulkDelete">
                                <i class="feather icon-trash-2"></i> حذف المحدد
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الصفحات</h6>
                <span class="badge badge-light-primary">{{ $pages->total() }} صفحة</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="pagesTable">
                    <thead>
                        <tr>
                            @if (hasPermission(['online_store.pages.toggle_status', 'online_store.pages.delete']))
                                <th class="text-center" style="width: 40px;">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                            @endif
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>العنوان</th>
                            <th>النوع</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center">الترتيب</th>
                            <th class="text-center">عدد الكلمات</th>
                            <th class="text-center">التاريخ</th>
                            @if (hasPermission(['online_store.pages.view', 'online_store.pages.edit', 'online_store.pages.create', 'online_store.pages.delete']))
                                <th class="text-center" style="width: 120px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr data-id="{{ $page->id }}">
                                @if (hasPermission(['online_store.pages.toggle_status', 'online_store.pages.delete']))
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input page-checkbox" value="{{ $page->id }}">
                                    </td>
                                @endif
                                <td class="text-center stk-num">{{ $page->id }}</td>
                                <td>
                                    <span class="stk-media__title">{{ $page->title }}</span>
                                    <small class="text-muted d-block">{{ $page->slug }}</small>
                                </td>
                                <td>
                                    @php
                                        $typeClass = match($page->type->value ?? $page->type) {
                                            'page' => 'primary',
                                            'policy' => 'info',
                                            'faq' => 'warning',
                                            default => 'secondary'
                                        };
                                        $typeText = match($page->type->value ?? $page->type) {
                                            'page' => 'صفحة عادية',
                                            'policy' => 'سياسة',
                                            'faq' => 'أسئلة شائعة',
                                            default => 'غير محدد'
                                        };
                                    @endphp
                                    <span class="badge badge-light-{{ $typeClass }}">{{ $typeText }}</span>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.pages.toggle_status'))
                                        <span class="stk-state {{ $page->is_active ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $page->id }}" data-url="{{ route('store_pages.toggle-status', $page) }}">
                                            {{ $page->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $page->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $page->is_active ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center stk-num">{{ $page->sort_order }}</td>
                                <td class="text-center">
                                    <span class="text-muted">{{ $page->word_count ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">{{ $page->created_at->format('Y-m-d') }}</small>
                                </td>
                                @if (hasPermission(['online_store.pages.view', 'online_store.pages.edit', 'online_store.pages.create', 'online_store.pages.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.pages.view'))
                                                    <a class="dropdown-item" href="{{ route('store_pages.show', $page) }}">
                                                        <i class="feather icon-eye"></i> عرض
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('store_pages.preview', $page) }}" target="_blank">
                                                        <i class="feather icon-external-link"></i> معاينة
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.pages.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_pages.edit', $page) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.pages.create'))
                                                    <button class="dropdown-item duplicate-page" data-id="{{ $page->id }}" data-url="{{ route('store_pages.duplicate', $page) }}">
                                                        <i class="feather icon-copy"></i> نسخ
                                                    </button>
                                                @endif
                                                @if (hasPermission('online_store.pages.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_pages.destroy', $page) }}" method="POST" class="delete-form">
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
                                <td colspan="9">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد صفحات</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول صفحة للمتجر.</p>
                                        @if (hasPermission('online_store.pages.create'))
                                            <a href="{{ route('store_pages.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة صفحة
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $pages, 'label' => 'نتيجة'])
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
    // تهيئة Select2
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

    // تحديد الكل
    $('#selectAll').change(function() {
        $('.page-checkbox').prop('checked', $(this).is(':checked'));
        toggleBulkActions();
    });

    $('.page-checkbox').change(function() {
        toggleBulkActions();
    });

    function toggleBulkActions() {
        var checkedCount = $('.page-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#bulkActionsBar').removeClass('d-none');
            $('.selected-count').text(checkedCount);
        } else {
            $('#bulkActionsBar').addClass('d-none');
        }
    }

    function getSelectedIds() {
        return $('.page-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }

    // تفعيل المحدد
    $('#bulkActivate').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        $.ajax({
            url: '{{ route("store_pages.bulk-activate") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ids: ids
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // إلغاء تفعيل المحدد
    $('#bulkDeactivate').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        $.ajax({
            url: '{{ route("store_pages.bulk-deactivate") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ids: ids
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء العملية');
            }
        });
    });

    // حذف المحدد
    $('#bulkDelete').click(function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف الصفحات المحددة نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("store_pages.bulk-delete") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        ids: ids
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        }
                    },
                    error: function() {
                        toastr.error('حدث خطأ أثناء الحذف');
                    }
                });
            }
        });
    });

    // تبديل الحالة
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
                    btn.text(response.is_active ? 'نشط' : 'غير نشط');
                    toastr.success(response.message);
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء تحديث الحالة');
            }
        });
    });

    // نسخ صفحة
    $(document).on('click', '.duplicate-page', function() {
        var url = $(this).data('url');
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                }
            },
            error: function() {
                toastr.error('حدث خطأ أثناء النسخ');
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه الصفحة نهائياً!',
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

    // طباعة قائمة الصفحات
    $('#printPagesBtn').click(function() {
        printPagesReport();
    });

    // تصدير قائمة الصفحات إلى Excel
    $('#exportPagesExcel').click(function() {
        exportPagesReport();
    });
});

function pagesFilterInfo() {
    var f = {};
    @if(request('search'))
    f['البحث'] = @json(request('search'));
    @endif
    @if(request('type'))
    f['النوع'] = @json($pagesTypeLabel);
    @endif
    @if(request('status'))
    f['الحالة'] = @json($pagesStatusLabel);
    @endif
    return f;
}

// =====================================
// طباعة تقرير صفحات المتجر
// =====================================
function printPagesReport() {
    var pagesHasCheckbox = {{ $pagesHasCheckbox ? 'true' : 'false' }};
    var pagesHasActions = {{ $pagesHasActions ? 'true' : 'false' }};

    var headers = ['#', 'العنوان', 'النوع', 'الحالة', 'الترتيب', 'عدد الكلمات', 'التاريخ'];
    var widths = ['5%', '25%', '13%', '12%', '10%', '12%', '13%'];
    var skip = [];

    if (pagesHasCheckbox) {
        headers.unshift('تحديد');
        widths.unshift('5%');
        skip.push(0);
    }
    if (pagesHasActions) {
        headers.push('الإجراءات');
        widths.push('10%');
        skip.push(headers.length - 1);
    }

    printTable({
        tableSelector: '#pagesTable tbody tr',
        title: 'تقرير صفحات المتجر',
        infoCards: [
            { label: 'إجمالي الصفحات', value: '{{ $stats['total'] ?? 0 }}' },
            { label: 'صفحات نشطة', value: '{{ $stats['active'] ?? 0 }}', class: 'text-success' },
            { label: 'صفحات عادية', value: '{{ $stats['pages'] ?? 0 }}' },
            { label: 'السياسات', value: '{{ $stats['policies'] ?? 0 }}' },
            { label: 'أسئلة شائعة', value: '{{ $stats['faqs'] ?? 0 }}' }
        ],
        filterInfo: pagesFilterInfo(),
        tableHeaders: headers,
        columnWidths: widths,
        skipColumns: skip
    });
}

// =====================================
// تصدير تقرير صفحات المتجر إلى Excel
// =====================================
function exportPagesReport() {
    var pagesHasCheckbox = {{ $pagesHasCheckbox ? 'true' : 'false' }};
    var idIdx = pagesHasCheckbox ? 1 : 0;
    var titleIdx = idIdx + 1;
    var typeIdx = titleIdx + 1;
    var statusIdx = typeIdx + 1;
    var orderIdx = statusIdx + 1;
    var wordsIdx = orderIdx + 1;
    var dateIdx = wordsIdx + 1;

    var tableData = [];

    $('#pagesTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length <= dateIdx) return; // صف الحالة الفارغة

        tableData.push({
            values: [
                $(cells[idIdx]).text().trim(),
                $(cells[titleIdx]).find('.stk-media__title').text().trim() || $(cells[titleIdx]).text().trim(),
                $(cells[typeIdx]).text().trim(),
                $(cells[statusIdx]).text().trim(),
                $(cells[orderIdx]).text().trim(),
                $(cells[wordsIdx]).text().trim(),
                $(cells[dateIdx]).text().trim()
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    });

    var reportInfo = pagesFilterInfo();
    reportInfo['تاريخ التصدير'] = new Date().toLocaleDateString('ar-SA');

    var config = {
        fileName: 'تقرير_صفحات_المتجر',
        mainTitle: 'تقرير صفحات المتجر',
        subTitle: 'المتجر الإلكتروني',
        reportInfo: reportInfo,
        statisticsCards: [
            { title: 'إجمالي الصفحات', value: '{{ $stats['total'] ?? 0 }}', unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'صفحات نشطة', value: '{{ $stats['active'] ?? 0 }}', unit: '', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'صفحات عادية', value: '{{ $stats['pages'] ?? 0 }}', unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'السياسات', value: '{{ $stats['policies'] ?? 0 }}', unit: '', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
            { title: 'أسئلة شائعة', value: '{{ $stats['faqs'] ?? 0 }}', unit: '', titleColor: 'purple', valueColor: 'purple', backgroundColor: 'purpleLight', borderColor: 'purple' }
        ],
        tableTitle: 'قائمة الصفحات',
        tableHeaders: ['#', 'العنوان', 'النوع', 'الحالة', 'الترتيب', 'عدد الكلمات', 'التاريخ'],
        columnWidths: [6, 28, 14, 12, 10, 14, 14],
        tableData: tableData,
        buttonSelector: '#exportPagesExcel',
        successMessage: 'تم تصدير تقرير الصفحات بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
