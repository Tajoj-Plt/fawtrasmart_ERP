@extends('onlinestore::master')

@section('title', 'طرق الدفع')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('PaymentMethods.index') }}" class="btn btn-primary">
                <i class="feather icon-settings"></i> إدارة طرق الدفع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'طرق الدفع',
            'icon' => 'credit-card',
            'sub' => 'طرق الدفع المرتبطة بطلبات المتجر — عرضٌ فقط',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'طرق الدفع' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="feather icon-info mr-1"></i>
            هذه الشاشة عرضٌ فقط لطرق الدفع المرتبطة بطلبات المتجر. الإضافة والتعديل والحذف تتم من
            <a href="{{ route('PaymentMethods.index') }}" class="alert-link mx-1">شاشة الإعدادات ← طرق الدفع</a>
            لتفادي تعارض البيانات بين الشاشتين.
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('payment_methods.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="search">بحث</label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="الاسم أو الكود…" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
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

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="active" @selected(request('status') == 'active')>نشط</option>
                                    <option value="inactive" @selected(request('status') == 'inactive')>غير نشط</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="is_online">متاح أونلاين</label>
                                <select name="is_online" id="is_online" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="yes" @selected(request('is_online') == 'yes')>نعم</option>
                                    <option value="no" @selected(request('is_online') == 'no')>لا</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('payment_methods.index') }}" class="btn btn-outline-secondary">
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

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-credit-card"></i></span>
                <span class="stk-metric__label">إجمالي الطرق</span>
                <span class="stk-metric__value">{{ $paymentMethods->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">طرق نشطة</span>
                <span class="stk-metric__value">{{ $paymentMethods->where('is_active', true)->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-globe"></i></span>
                <span class="stk-metric__label">متاحة أونلاين</span>
                <span class="stk-metric__value">{{ $paymentMethods->where('is_online', true)->count() ?? 0 }}</span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة طرق الدفع</h6>
                <span class="badge badge-light-primary">{{ $paymentMethods->total() }} طريقة</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="paymentMethodsTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>طريقة الدفع</th>
                            <th class="text-center">النوع</th>
                            <th class="text-center">الرسوم</th>
                            <th class="text-center">أونلاين</th>
                            <th class="text-center">افتراضي</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width: 80px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentMethods as $method)
                            <tr>
                                <td class="text-center stk-num">{{ $method->id }}</td>
                                <td>
                                    <div class="stk-media">
                                        @if ($method->logo)
                                            <span class="stk-media__thumb"><img src="{{ asset($method->logo) }}"
                                                    alt="{{ $method->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"></span>
                                        @else
                                            <span class="stk-media__thumb"><i class="fa fa-{{ $method->type_icon }}"></i></span>
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
                                    <span class="badge badge-light-info">{{ $method->type_label }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($method->fee_amount > 0)
                                        @if ($method->fee_type == 'percentage')
                                            <span class="stk-num">{{ number_format($method->fee_amount, 2) }}%</span>
                                        @else
                                            <span class="stk-num">{{ number_format($method->fee_amount, 2) }}</span>
                                        @endif
                                    @else
                                        <span class="text-success">مجاني</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($method->is_online)
                                        <span class="badge badge-light-success"><i class="feather icon-globe"></i> نعم</span>
                                    @else
                                        <span class="badge badge-light-secondary">لا</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($method->is_default)
                                        <span class="badge badge-light-primary"><i class="feather icon-star"></i> افتراضي</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="stk-state {{ $method->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $method->is_active ? 'نشط' : 'متوقف' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('payment_methods.show', $method) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-credit-card"></i></span>
                                        <h6 class="stk-empty__title">لا توجد طرق دفع</h6>
                                        <p class="stk-empty__hint">أضِف طرق الدفع من شاشة الإعدادات.</p>
                                        <a href="{{ route('PaymentMethods.index') }}" class="btn btn-primary btn-sm">
                                            <i class="feather icon-settings"></i> إدارة طرق الدفع
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $paymentMethods, 'label' => 'نتيجة'])
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
        printPaymentMethodsReport();
    });

    $('#exportExcel').click(function() {
        exportPaymentMethodsReport();
    });
});

// =====================================
// بيانات مشتركة للطباعة والتصدير
// =====================================
@php
    $paymentStatusLabel = 'الكل';
    if (request('status') == 'active') {
        $paymentStatusLabel = 'نشط';
    } elseif (request('status') == 'inactive') {
        $paymentStatusLabel = 'غير نشط';
    }

    $paymentOnlineLabel = 'الكل';
    if (request('is_online') == 'yes') {
        $paymentOnlineLabel = 'نعم';
    } elseif (request('is_online') == 'no') {
        $paymentOnlineLabel = 'لا';
    }

    $paymentSelectedType = collect($types)->first(fn ($t) => $t->value == request('type'));
    $paymentTypeLabel = $paymentSelectedType ? $paymentSelectedType->label() : 'الكل';
@endphp

function getPaymentMethodsFilterInfo() {
    return {
        'البحث': '{{ request('search') ? addslashes(request('search')) : 'الكل' }}',
        'النوع': '{{ addslashes($paymentTypeLabel) }}',
        'الحالة': '{{ $paymentStatusLabel }}',
        'متاح أونلاين': '{{ $paymentOnlineLabel }}'
    };
}

function getPaymentMethodsTableData() {
    var rows = [];
    $('#paymentMethodsTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 7) return;

        var nameCell = $(cells[1]);
        var title = nameCell.find('.stk-media__title').text().trim();
        var sub = nameCell.find('.stk-media__sub').text().trim();
        var name = sub ? (title + ' - ' + sub) : title;

        rows.push({
            num: $(cells[0]).text().trim(),
            name: name,
            type: $(cells[2]).text().trim(),
            fee: $(cells[3]).text().trim(),
            online: $(cells[4]).text().trim(),
            isDefault: $(cells[5]).text().trim(),
            status: $(cells[6]).text().trim()
        });
    });
    return rows;
}

// =====================================
// الطباعة
// =====================================
function printPaymentMethodsReport() {
    printTable({
        tableSelector: '#paymentMethodsTable tbody tr',
        title: 'تقرير طرق الدفع',
        infoCards: [
            { label: 'إجمالي الطرق', value: '{{ $paymentMethods->total() ?? 0 }}' },
            { label: 'طرق نشطة', value: '{{ $paymentMethods->where('is_active', true)->count() ?? 0 }}', class: 'text-success' },
            { label: 'متاحة أونلاين', value: '{{ $paymentMethods->where('is_online', true)->count() ?? 0 }}' }
        ],
        filterInfo: getPaymentMethodsFilterInfo(),
        tableHeaders: ['#', 'طريقة الدفع', 'النوع', 'الرسوم', 'أونلاين', 'افتراضي', 'الحالة'],
        columnWidths: ['6%', '26%', '14%', '14%', '14%', '14%', '12%'],
        skipColumns: [7],
        windowWidth: 1300,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportPaymentMethodsReport() {
    var tableRows = getPaymentMethodsTableData();
    var tableData = tableRows.map(function(row) {
        return {
            values: [row.num, row.name, row.type, row.fee, row.online, row.isDefault, row.status],
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
        fileName: 'تقرير_طرق_الدفع',
        mainTitle: 'تقرير طرق الدفع',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: Object.assign({}, getPaymentMethodsFilterInfo(), {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }),

        statisticsCards: [
            {
                title: 'إجمالي الطرق',
                value: '{{ $paymentMethods->total() ?? 0 }}',
                unit: ' طريقة',
                titleColor: 'primary',
                valueColor: 'primary',
                backgroundColor: 'grayLight',
                borderColor: 'primary'
            },
            {
                title: 'طرق نشطة',
                value: '{{ $paymentMethods->where('is_active', true)->count() ?? 0 }}',
                unit: ' طريقة',
                titleColor: 'success',
                valueColor: 'success',
                backgroundColor: 'successLight',
                borderColor: 'success'
            },
            {
                title: 'متاحة أونلاين',
                value: '{{ $paymentMethods->where('is_online', true)->count() ?? 0 }}',
                unit: ' طريقة',
                titleColor: 'warning',
                valueColor: 'warning',
                backgroundColor: 'warningLight',
                borderColor: 'warning'
            }
        ],

        tableTitle: 'قائمة طرق الدفع',
        tableHeaders: ['#', 'طريقة الدفع', 'النوع', 'الرسوم', 'أونلاين', 'افتراضي', 'الحالة'],
        columnWidths: [6, 30, 16, 14, 12, 12, 12],

        tableData: tableData,

        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير تقرير طرق الدفع بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
