@extends('onlinestore::master')

@section('title', 'البانرات والإعلانات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.banners.create'))
                <a href="{{ route('store_banners.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة بانر
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'البانرات والإعلانات',
            'icon' => 'image',
            'sub' => 'بانرات وإعلانات المتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'البانرات' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-image"></i></span>
                <span class="stk-metric__label">إجمالي البانرات</span>
                <span class="stk-metric__value">{{ $stats['total'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">بانرات نشطة</span>
                <span class="stk-metric__value">{{ $stats['active'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-eye"></i></span>
                <span class="stk-metric__label">سارية حالياً</span>
                <span class="stk-metric__value">{{ $stats['valid'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">منتهية الصلاحية</span>
                <span class="stk-metric__value">{{ $stats['expired'] }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_banners.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="العنوان أو الوصف…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="position">الموقع</label>
                                <select name="position" id="position" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($positions as $position)
                                        <option value="{{ $position->value }}" @selected(request('position') == $position->value)>
                                            {{ $position->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="link_type">نوع الرابط</label>
                                <select name="link_type" id="link_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($linkTypes as $type)
                                        <option value="{{ $type->value }}" @selected(request('link_type') == $type->value)>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="active" @selected(request('status') == 'active')>نشط</option>
                                    <option value="inactive" @selected(request('status') == 'inactive')>غير نشط</option>
                                    <option value="expired" @selected(request('status') == 'expired')>منتهي</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_banners.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        <button type="button" id="printBannersBtn" class="btn btn-success">
                            <i class="feather icon-printer"></i> طباعة
                        </button>
                        <button type="button" id="exportBannersExcel" class="btn btn-info">
                            <i class="feather icon-file-text"></i> Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $bannersHasHandle = hasPermission('online_store.banners.edit');
            $bannersHasActions = hasPermission(['online_store.banners.view', 'online_store.banners.edit', 'online_store.banners.delete', 'online_store.banners.create']);
            $bannersPositionLabel = optional(collect($positions)->first(fn($p) => $p->value == request('position')))->name ?? request('position');
            $bannersLinkTypeLabel = optional(collect($linkTypes)->first(fn($t) => $t->value == request('link_type')))->name ?? request('link_type');
            $bannersStatusLabel = match(request('status')) {
                'active' => 'نشط',
                'inactive' => 'غير نشط',
                'expired' => 'منتهي',
                default => request('status'),
            };
        @endphp

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة البانرات</h6>
                <span class="badge badge-light-primary">{{ $banners->total() }} بانر</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="bannersTable">
                    <thead>
                        <tr>
                            @if (hasPermission('online_store.banners.edit'))
                                <th class="text-center" style="width: 40px;"><i class="feather icon-move"></i></th>
                            @endif
                            <th>البانر</th>
                            <th class="text-center">الموقع</th>
                            <th class="text-center">نوع الرابط</th>
                            <th class="text-center">الصلاحية</th>
                            <th class="text-center">الحالة</th>
                            @if (hasPermission(['online_store.banners.view', 'online_store.banners.edit', 'online_store.banners.delete', 'online_store.banners.create']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="sortable-banners">
                        @forelse ($banners as $banner)
                            <tr data-id="{{ $banner->id }}">
                                @if (hasPermission('online_store.banners.edit'))
                                    <td class="text-center">
                                        <span class="sort-handle" style="cursor:move">
                                            <i class="feather icon-menu"></i>
                                        </span>
                                    </td>
                                @endif
                                <td>
                                    <div class="stk-media">
                                        @if ($banner->image)
                                            <span class="stk-media__thumb"><img src="{{ asset($banner->image) }}"
                                                    alt="{{ $banner->title }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="feather icon-image"></i></span>
                                        @endif
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $banner->title }}</span>
                                            @if ($banner->description)
                                                <span class="stk-media__sub">{{ Str::limit($banner->description, 40) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-secondary">{{ $banner->position->name ?? '-' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-info">{{ $banner->link_type->name ?? '-' }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($banner->isExpired())
                                        <span class="text-danger"><i class="feather icon-x-circle"></i> منتهي</span>
                                    @elseif (!$banner->isStarted())
                                        <span class="text-warning"><i class="feather icon-clock"></i> لم يبدأ</span>
                                    @else
                                        <span class="text-success"><i class="feather icon-check-circle"></i> ساري</span>
                                    @endif
                                    @if ($banner->days_remaining !== null)
                                        <small class="text-muted d-block">{{ $banner->days_remaining }} يوم متبقي</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.banners.toggle_status'))
                                        <span class="stk-state {{ $banner->is_active ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-url="{{ route('store_banners.toggle-status', $banner) }}">
                                            {{ $banner->is_active ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $banner->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $banner->is_active ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @endif
                                </td>
                                @if (hasPermission(['online_store.banners.view', 'online_store.banners.edit', 'online_store.banners.delete', 'online_store.banners.create']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.banners.view'))
                                                    <a class="dropdown-item" href="{{ route('store_banners.show', $banner) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.banners.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_banners.edit', $banner) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.banners.create'))
                                                    <form action="{{ route('store_banners.duplicate', $banner) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="feather icon-copy"></i> نسخ
                                                        </button>
                                                    </form>
                                                @endif
                                                @if (hasPermission('online_store.banners.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_banners.destroy', $banner) }}" method="POST" class="delete-form">
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
                                        <span class="stk-empty__icon"><i class="feather icon-image"></i></span>
                                        <h6 class="stk-empty__title">لا توجد بانرات</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول بانر للمتجر.</p>
                                        @if (hasPermission('online_store.banners.create'))
                                            <a href="{{ route('store_banners.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة بانر
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $banners, 'label' => 'نتيجة'])
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    // تبديل الحالة
    $('.toggle-status').click(function() {
        var btn = $(this);
        $.ajax({
            url: btn.data('url'),
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    btn.toggleClass('stk-state--on stk-state--off');
                    btn.text(response.is_active ? 'نشط' : 'متوقف');
                    toastr.success(response.message);
                }
            }
        });
    });

    // ترتيب البانرات بالسحب والإفلات
    var sortableEl = document.getElementById('sortable-banners');
    if (sortableEl && $('.sort-handle').length) {
        var sortable = new Sortable(sortableEl, {
            handle: '.sort-handle',
            animation: 150,
            onEnd: function(evt) {
                var orders = [];
                $('#sortable-banners tr').each(function(index) {
                    orders.push({
                        id: $(this).data('id'),
                        sort_order: index
                    });
                });

                $.ajax({
                    url: '{{ route("store_banners.update-order") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        orders: orders
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                        }
                    }
                });
            }
        });
    }

    // حذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف البانر نهائياً!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#7367f0',
            cancelButtonColor: '#ea5455',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });

    // طباعة قائمة البانرات
    $('#printBannersBtn').click(function() {
        printBannersReport();
    });

    // تصدير قائمة البانرات إلى Excel
    $('#exportBannersExcel').click(function() {
        exportBannersReport();
    });
});

function bannersFilterInfo() {
    var f = {};
    @if(request('search'))
    f['البحث'] = @json(request('search'));
    @endif
    @if(request('position'))
    f['الموقع'] = @json($bannersPositionLabel);
    @endif
    @if(request('link_type'))
    f['نوع الرابط'] = @json($bannersLinkTypeLabel);
    @endif
    @if(request('status'))
    f['الحالة'] = @json($bannersStatusLabel);
    @endif
    return f;
}

// =====================================
// طباعة تقرير البانرات
// =====================================
function printBannersReport() {
    var bannersHasHandle = {{ $bannersHasHandle ? 'true' : 'false' }};
    var bannersHasActions = {{ $bannersHasActions ? 'true' : 'false' }};

    var headers = ['البانر', 'الموقع', 'نوع الرابط', 'الصلاحية', 'الحالة'];
    var widths = ['28%', '15%', '15%', '17%', '15%'];
    var skip = [];

    if (bannersHasHandle) {
        headers.unshift('ترتيب');
        widths.unshift('5%');
        skip.push(0);
    }
    if (bannersHasActions) {
        headers.push('الإجراءات');
        widths.push('10%');
        skip.push(headers.length - 1);
    }

    printTable({
        tableSelector: '#bannersTable tbody tr',
        title: 'تقرير البانرات والإعلانات',
        infoCards: [
            { label: 'إجمالي البانرات', value: '{{ $stats['total'] }}' },
            { label: 'بانرات نشطة', value: '{{ $stats['active'] }}', class: 'text-success' },
            { label: 'سارية حالياً', value: '{{ $stats['valid'] }}' },
            { label: 'منتهية الصلاحية', value: '{{ $stats['expired'] }}', class: 'text-danger' }
        ],
        filterInfo: bannersFilterInfo(),
        tableHeaders: headers,
        columnWidths: widths,
        skipColumns: skip
    });
}

// =====================================
// تصدير تقرير البانرات إلى Excel
// =====================================
function exportBannersReport() {
    var bannersHasHandle = {{ $bannersHasHandle ? 'true' : 'false' }};
    var mediaIdx = bannersHasHandle ? 1 : 0;
    var positionIdx = mediaIdx + 1;
    var linkTypeIdx = positionIdx + 1;
    var validityIdx = linkTypeIdx + 1;
    var statusIdx = validityIdx + 1;

    var tableData = [];

    $('#bannersTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length <= statusIdx) return; // صف الحالة الفارغة

        var title = $(cells[mediaIdx]).find('.stk-media__title').text().trim();
        var sub = $(cells[mediaIdx]).find('.stk-media__sub').text().trim();
        var bannerLabel = sub ? (title + ' - ' + sub) : title;

        tableData.push({
            values: [
                bannerLabel,
                $(cells[positionIdx]).text().trim(),
                $(cells[linkTypeIdx]).text().trim(),
                $(cells[validityIdx]).text().replace(/\s+/g, ' ').trim(),
                $(cells[statusIdx]).text().trim()
            ],
            cellStyles: [
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    });

    var reportInfo = bannersFilterInfo();
    reportInfo['تاريخ التصدير'] = new Date().toLocaleDateString('ar-SA');

    var config = {
        fileName: 'تقرير_البانرات_والإعلانات',
        mainTitle: 'تقرير البانرات والإعلانات',
        subTitle: 'المتجر الإلكتروني',
        reportInfo: reportInfo,
        statisticsCards: [
            { title: 'إجمالي البانرات', value: '{{ $stats['total'] }}', unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'بانرات نشطة', value: '{{ $stats['active'] }}', unit: '', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'سارية حالياً', value: '{{ $stats['valid'] }}', unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'منتهية الصلاحية', value: '{{ $stats['expired'] }}', unit: '', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' }
        ],
        tableTitle: 'قائمة البانرات',
        tableHeaders: ['البانر', 'الموقع', 'نوع الرابط', 'الصلاحية', 'الحالة'],
        columnWidths: [30, 15, 15, 20, 15],
        tableData: tableData,
        buttonSelector: '#exportBannersExcel',
        successMessage: 'تم تصدير تقرير البانرات بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
