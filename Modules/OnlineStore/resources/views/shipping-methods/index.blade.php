@extends('onlinestore::master')

@section('title', 'طرق الشحن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.shipping_methods.create'))
                <a href="{{ route('shipping_methods.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة طريقة شحن
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'طرق الشحن',
            'icon' => 'truck',
            'sub' => 'طرق الشحن المتاحة لطلبات المتجر وتكلفة كل طريقة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'طرق الشحن' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-truck"></i></span>
                <span class="stk-metric__label">إجمالي الطرق</span>
                <span class="stk-metric__value">{{ $shippingMethods->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">طرق نشطة</span>
                <span class="stk-metric__value">{{ $stats['active'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-gift"></i></span>
                <span class="stk-metric__label">شحن مجاني</span>
                <span class="stk-metric__value">{{ $stats['free'] ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('shipping_methods.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="الاسم أو الكود…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="type">النوع</label>
                                <select name="type" id="type" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected(request('type') == $type->value)>
                                            {{ $type->label() }}
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
                                    <option value="active" @selected(request('status') == 'active')>نشط</option>
                                    <option value="inactive" @selected(request('status') == 'inactive')>غير نشط</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('shipping_methods.index') }}" class="btn btn-outline-secondary">
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
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة طرق الشحن</h6>
                <span class="badge badge-light-primary">{{ $shippingMethods->total() }} طريقة</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="shippingMethodsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>طريقة الشحن</th>
                            <th class="text-center">النوع</th>
                            <th class="text-center">التكلفة</th>
                            <th class="text-center">حد الشحن المجاني</th>
                            <th class="text-center">شحن سريع</th>
                            <th class="text-center">الوقت المتوقع</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width: 80px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shippingMethods as $method)
                            <tr>
                                <td class="text-center stk-num">{{ $method->id }}</td>
                                <td>
                                    <div class="stk-media">
                                        @if ($method->logo)
                                            <span class="stk-media__thumb"><img src="{{ asset($method->logo) }}"
                                                    alt="{{ $method->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="feather icon-truck"></i></span>
                                        @endif
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $method->name }}</span>
                                            @if ($method->code)
                                                <span class="stk-media__sub">{{ $method->code }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $method->type->value == 'free' ? 'badge-light-success' : 'badge-light-info' }}">
                                        {{ $method->type->label() }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($method->type->value == 'free')
                                        <span class="text-success">مجاني</span>
                                    @else
                                        <strong class="stk-num">{{ \App\Support\CurrencySettings::format($method->cost) }}</strong>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($method->free_shipping_threshold)
                                        <span class="text-info stk-num">{{ \App\Support\CurrencySettings::format($method->free_shipping_threshold) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($method->supports_express ?? true)
                                        <span class="badge badge-light-success"><i class="feather icon-zap"></i> يدعم</span>
                                    @else
                                        <span class="badge badge-light-secondary">لا يدعم</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <small>{{ $method->estimated_delivery_text }}</small>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('online_store.shipping_methods.toggle_status'))
                                        <span class="stk-state {{ $method->is_active ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $method->id }}" data-url="{{ route('shipping_methods.toggle-status', $method) }}">
                                            {{ $method->is_active ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $method->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $method->is_active ? 'نشط' : 'متوقف' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('shipping_methods.show', $method) }}">
                                                <i class="feather icon-eye"></i> عرض
                                            </a>
                                            @if (hasPermission('online_store.shipping_methods.edit'))
                                                <a class="dropdown-item" href="{{ route('shipping_methods.edit', $method) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                            @endif
                                            @if (hasPermission('online_store.shipping_methods.delete'))
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('shipping_methods.destroy', $method) }}" method="POST" class="delete-form">
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-truck"></i></span>
                                        <h6 class="stk-empty__title">لا توجد طرق شحن</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول طريقة شحن لمتجرك.</p>
                                        @if (hasPermission('online_store.shipping_methods.create'))
                                            <a href="{{ route('shipping_methods.create') }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-plus"></i> إضافة طريقة شحن
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $shippingMethods, 'label' => 'نتيجة'])
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
        printShippingMethodsReport();
    });

    $('#exportExcel').click(function() {
        exportShippingMethodsReport();
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
            text: 'سيتم حذف طريقة الشحن!',
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
    $shippingStatusLabel = 'الكل';
    if (request('status') == 'active') {
        $shippingStatusLabel = 'نشط';
    } elseif (request('status') == 'inactive') {
        $shippingStatusLabel = 'غير نشط';
    }

    $shippingSelectedType = collect($types)->first(fn ($t) => $t->value == request('type'));
    $shippingTypeLabel = $shippingSelectedType ? $shippingSelectedType->label() : 'الكل';
@endphp

function getShippingMethodsFilterInfo() {
    return {
        'البحث': '{{ request('search') ? addslashes(request('search')) : 'الكل' }}',
        'النوع': '{{ addslashes($shippingTypeLabel) }}',
        'الحالة': '{{ $shippingStatusLabel }}'
    };
}

function getShippingMethodsTableData() {
    var rows = [];
    $('#shippingMethodsTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 8) return;

        var nameCell = $(cells[1]);
        var title = nameCell.find('.stk-media__title').text().trim();
        var sub = nameCell.find('.stk-media__sub').text().trim();
        var name = sub ? (title + ' - ' + sub) : title;

        rows.push({
            num: $(cells[0]).text().trim(),
            name: name,
            type: $(cells[2]).text().trim(),
            cost: $(cells[3]).text().trim(),
            freeThreshold: $(cells[4]).text().trim(),
            express: $(cells[5]).text().trim(),
            eta: $(cells[6]).text().trim(),
            status: $(cells[7]).text().trim()
        });
    });
    return rows;
}

// =====================================
// الطباعة
// =====================================
function printShippingMethodsReport() {
    printTable({
        tableSelector: '#shippingMethodsTable tbody tr',
        title: 'تقرير طرق الشحن',
        infoCards: [
            { label: 'إجمالي الطرق', value: '{{ $shippingMethods->total() ?? 0 }}' },
            { label: 'طرق نشطة', value: '{{ $stats['active'] ?? 0 }}', class: 'text-success' },
            { label: 'شحن مجاني', value: '{{ $stats['free'] ?? 0 }}' }
        ],
        filterInfo: getShippingMethodsFilterInfo(),
        tableHeaders: ['#', 'طريقة الشحن', 'النوع', 'التكلفة', 'حد الشحن المجاني', 'شحن سريع', 'الوقت المتوقع', 'الحالة'],
        columnWidths: ['5%', '20%', '12%', '11%', '14%', '11%', '14%', '13%'],
        skipColumns: [8],
        windowWidth: 1400,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportShippingMethodsReport() {
    var tableRows = getShippingMethodsTableData();
    var tableData = tableRows.map(function(row) {
        return {
            values: [row.num, row.name, row.type, row.cost, row.freeThreshold, row.express, row.eta, row.status],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        };
    });

    var config = {
        fileName: 'تقرير_طرق_الشحن',
        mainTitle: 'تقرير طرق الشحن',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: Object.assign({}, getShippingMethodsFilterInfo(), {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }),

        statisticsCards: [
            {
                title: 'إجمالي الطرق',
                value: '{{ $shippingMethods->total() ?? 0 }}',
                unit: ' طريقة',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'طرق نشطة',
                value: '{{ $stats['active'] ?? 0 }}',
                unit: ' طريقة',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'شحن مجاني',
                value: '{{ $stats['free'] ?? 0 }}',
                unit: ' طريقة',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            }
        ],

        tableTitle: 'قائمة طرق الشحن',
        tableHeaders: ['#', 'طريقة الشحن', 'النوع', 'التكلفة', 'حد الشحن المجاني', 'شحن سريع', 'الوقت المتوقع', 'الحالة'],
        columnWidths: [6, 26, 14, 12, 16, 12, 16, 12],

        tableData: tableData,

        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير تقرير طرق الشحن بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
