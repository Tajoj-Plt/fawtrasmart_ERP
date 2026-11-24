@extends('onlinestore::master')

@section('title', 'كوبونات الخصم')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.coupons.create'))
                <a href="{{ route('store_coupons.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة كوبون
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'كوبونات الخصم',
            'icon' => 'percent',
            'sub' => 'كوبونات خصم المتجر وشروط استخدامها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'كوبونات الخصم' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-gift"></i></span>
                <span class="stk-metric__label">إجمالي الكوبونات</span>
                <span class="stk-metric__value">{{ $stats['total'] ?? $coupons->total() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">كوبونات نشطة</span>
                <span class="stk-metric__value">{{ $stats['active'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">كوبونات منتهية</span>
                <span class="stk-metric__value">{{ $stats['expired'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">مرات الاستخدام</span>
                <span class="stk-metric__value">{{ $stats['used'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_coupons.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث بالكود</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="كود الكوبون…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="type">نوع الخصم</label>
                                <select name="type" id="type" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="percentage" @selected(request('type') == 'percentage')>نسبة مئوية</option>
                                    <option value="fixed" @selected(request('type') == 'fixed')>مبلغ ثابت</option>
                                    <option value="free_shipping" @selected(request('type') == 'free_shipping')>شحن مجاني</option>
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
                                    <option value="expired" @selected(request('status') == 'expired')>منتهي</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_coupons.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                        <button type="button" id="printCouponsBtn" class="btn btn-success">
                            <i class="feather icon-printer"></i> طباعة
                        </button>
                        <button type="button" id="exportCouponsExcel" class="btn btn-info">
                            <i class="feather icon-file-text"></i> Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $couponsHasActions = hasPermission(['online_store.coupons.view', 'online_store.coupons.edit', 'online_store.coupons.delete']);
            $couponsActiveCount = $stats['active'] ?? 0;
            $couponsExpiredCount = $stats['expired'] ?? 0;
            $couponsUsedTotal = $stats['used'] ?? 0;
            $couponsTypeLabel = match(request('type')) {
                'percentage' => 'نسبة مئوية',
                'fixed' => 'مبلغ ثابت',
                'free_shipping' => 'شحن مجاني',
                default => request('type'),
            };
            $couponsStatusLabel = match(request('status')) {
                'active' => 'نشط',
                'inactive' => 'غير نشط',
                'expired' => 'منتهي',
                default => request('status'),
            };
        @endphp

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الكوبونات</h6>
                <span class="badge badge-light-primary">{{ $coupons->total() }} كوبون</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="couponsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>الكود</th>
                            <th class="text-center">نوع الخصم</th>
                            <th class="text-center">القيمة</th>
                            <th class="text-center">الاستخدام</th>
                            <th class="text-center">الانتهاء</th>
                            <th class="text-center">الحالة</th>
                            @if (hasPermission(['online_store.coupons.view', 'online_store.coupons.edit', 'online_store.coupons.delete']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($coupons as $coupon)
                            @php
                                $isExpired = $coupon->end_date && $coupon->end_date < now();
                                $isLimitReached = $coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit;
                            @endphp
                            <tr>
                                <td class="text-center stk-num">{{ $coupon->id }}</td>
                                <td>
                                    <span class="stk-num">{{ $coupon->code }}</span>
                                    @if ($coupon->description)
                                        <small class="text-muted d-block">{{ Str::limit($coupon->description, 30) }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($coupon->type->value == 'percentage')
                                        <span class="badge badge-light-primary"><i class="feather icon-percent"></i> نسبة</span>
                                    @elseif ($coupon->type->value == 'fixed')
                                        <span class="badge badge-light-success"><i class="feather icon-dollar-sign"></i> ثابت</span>
                                    @else
                                        <span class="badge badge-light-info"><i class="feather icon-truck"></i> شحن</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <strong class="text-success">
                                        @if ($coupon->type->value == 'percentage')
                                            {{ rtrim(rtrim(number_format($coupon->value, 2), '0'), '.') }}%
                                        @elseif ($coupon->type->value == 'fixed')
                                            {{ \App\Support\CurrencySettings::format($coupon->value) }}
                                        @else
                                            —
                                        @endif
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $isLimitReached ? 'badge-light-danger' : 'badge-light-secondary' }}">
                                        {{ $coupon->used_count }}/{{ $coupon->usage_limit ?: '∞' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($coupon->end_date)
                                        @if ($isExpired)
                                            <span class="text-danger"><i class="feather icon-alert-circle"></i> منتهي</span>
                                        @else
                                            <span class="stk-num">{{ $coupon->end_date->format('Y-m-d') }}</span>
                                            <small class="text-muted d-block">{{ $coupon->end_date->diffForHumans() }}</small>
                                        @endif
                                    @else
                                        <span class="badge badge-light-success">دائم</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($isExpired)
                                        <span class="stk-state stk-state--off"><i class="feather icon-x-circle"></i> منتهي</span>
                                    @elseif ($isLimitReached)
                                        <span class="stk-state stk-state--off"><i class="feather icon-alert-triangle"></i> مكتمل</span>
                                    @elseif (hasPermission('online_store.coupons.toggle_status'))
                                        <span class="stk-state {{ $coupon->is_active ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $coupon->id }}" data-url="{{ route('store_coupons.toggle-status', $coupon) }}">
                                            {{ $coupon->is_active ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $coupon->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $coupon->is_active ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @endif
                                </td>
                                @if (hasPermission(['online_store.coupons.view', 'online_store.coupons.edit', 'online_store.coupons.delete']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('online_store.coupons.view'))
                                                    <a class="dropdown-item" href="{{ route('store_coupons.show', $coupon) }}">
                                                        <i class="feather icon-eye"></i> عرض
                                                    </a>
                                                    @if ($coupon->used_count > 0)
                                                        <a class="dropdown-item" href="{{ route('store_coupons.usages', $coupon) }}">
                                                            <i class="feather icon-users"></i> الاستخدامات
                                                        </a>
                                                    @endif
                                                @endif
                                                @if (hasPermission('online_store.coupons.edit'))
                                                    <a class="dropdown-item" href="{{ route('store_coupons.edit', $coupon) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.coupons.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_coupons.destroy', $coupon) }}" method="POST" class="delete-form">
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
                                        <span class="stk-empty__icon"><i class="feather icon-gift"></i></span>
                                        <h6 class="stk-empty__title">لا توجد كوبونات</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول كوبون خصم.</p>
                                        @if (hasPermission('online_store.coupons.create'))
                                            <a href="{{ route('store_coupons.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة كوبون
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $coupons, 'label' => 'نتيجة'])
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
            text: 'سيتم حذف هذا الكوبون نهائياً!',
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

    // طباعة قائمة الكوبونات
    $('#printCouponsBtn').click(function() {
        printCouponsReport();
    });

    // تصدير قائمة الكوبونات إلى Excel
    $('#exportCouponsExcel').click(function() {
        exportCouponsReport();
    });
});

function couponsFilterInfo() {
    var f = {};
    @if(request('search'))
    f['البحث'] = @json(request('search'));
    @endif
    @if(request('type'))
    f['نوع الخصم'] = @json($couponsTypeLabel);
    @endif
    @if(request('status'))
    f['الحالة'] = @json($couponsStatusLabel);
    @endif
    return f;
}

// =====================================
// طباعة تقرير الكوبونات
// =====================================
function printCouponsReport() {
    var couponsHasActions = {{ $couponsHasActions ? 'true' : 'false' }};

    var headers = ['#', 'الكود', 'نوع الخصم', 'القيمة', 'الاستخدام', 'الانتهاء', 'الحالة'];
    var widths = ['5%', '20%', '13%', '12%', '13%', '15%', '12%'];
    var skip = [];

    if (couponsHasActions) {
        headers.push('الإجراءات');
        widths.push('10%');
        skip.push(headers.length - 1);
    }

    printTable({
        tableSelector: '#couponsTable tbody tr',
        title: 'تقرير كوبونات الخصم',
        infoCards: [
            { label: 'إجمالي الكوبونات', value: '{{ $coupons->total() ?? 0 }}' },
            { label: 'كوبونات نشطة', value: '{{ $couponsActiveCount }}', class: 'text-success' },
            { label: 'كوبونات منتهية', value: '{{ $couponsExpiredCount }}', class: 'text-danger' },
            { label: 'مرات الاستخدام', value: '{{ $couponsUsedTotal }}' }
        ],
        filterInfo: couponsFilterInfo(),
        tableHeaders: headers,
        columnWidths: widths,
        skipColumns: skip
    });
}

// =====================================
// تصدير تقرير الكوبونات إلى Excel
// =====================================
function exportCouponsReport() {
    var tableData = [];

    $('#couponsTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 7) return; // صف الحالة الفارغة

        var code = $(cells[1]).find('.stk-num').first().text().trim() || $(cells[1]).text().trim();

        tableData.push({
            values: [
                $(cells[0]).text().trim(),
                code,
                $(cells[2]).text().trim(),
                $(cells[3]).text().trim(),
                $(cells[4]).text().trim(),
                $(cells[5]).text().replace(/\s+/g, ' ').trim(),
                $(cells[6]).text().trim()
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        });
    });

    var reportInfo = couponsFilterInfo();
    reportInfo['تاريخ التصدير'] = new Date().toLocaleDateString('ar-SA');

    var config = {
        fileName: 'تقرير_كوبونات_الخصم',
        mainTitle: 'تقرير كوبونات الخصم',
        subTitle: 'المتجر الإلكتروني',
        reportInfo: reportInfo,
        statisticsCards: [
            { title: 'إجمالي الكوبونات', value: '{{ $coupons->total() ?? 0 }}', unit: '', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'كوبونات نشطة', value: '{{ $couponsActiveCount }}', unit: '', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'كوبونات منتهية', value: '{{ $couponsExpiredCount }}', unit: '', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
            { title: 'مرات الاستخدام', value: '{{ $couponsUsedTotal }}', unit: '', titleColor: 'purple', valueColor: 'purple', backgroundColor: 'purpleLight', borderColor: 'purple' }
        ],
        tableTitle: 'قائمة الكوبونات',
        tableHeaders: ['#', 'الكود', 'نوع الخصم', 'القيمة', 'الاستخدام', 'الانتهاء', 'الحالة'],
        columnWidths: [6, 22, 15, 14, 14, 16, 14],
        tableData: tableData,
        buttonSelector: '#exportCouponsExcel',
        successMessage: 'تم تصدير تقرير الكوبونات بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
