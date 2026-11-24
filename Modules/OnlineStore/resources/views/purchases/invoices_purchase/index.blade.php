{{--
    قائمة فواتير مشتريات المتجر.

    الفلاتر هنا هي ما يقرؤه `StorePurchaseInvoiceController::applyFilters()`
    فعلاً وبأسمائه وقيمه — فلترٌ يظهر ولا يُطبَّق يوهم المستخدم بأن النتيجة
    مفلترة:
      • المورد يُرسَل باسم `employee_search` (هكذا يقرؤه الخادم)؛ كان يُرسَل
        `supplier_id` فلا يُفلتر شيئاً.
      • «مدفوع جزئياً» قيمته `partial`، و«مرتجع» قيمته `Return`.
      • قائمة «عدد الصفوف» حُذفت: الخادم يقسّم على ٣٠ ثابتة ولا يقرأها.
      • فلتر الفرع من `DataScope::branchOptions()` — الفروع التي يحقّ
        للمستخدم اختيارها فقط، والخادم يقصّ القيمة على نطاقه مرةً أخرى.

    الطباعة والتصدير من المتصفّح (printTable + exportToExcelProfessional)
    يقرآن الجدول المعروض، فيتبعان الفلترة الحالية.
--}}
@extends('onlinestore::master')

@section('title', 'فواتير الشراء')

@section('content')
    @php
        // المتحكّم لا يمرّر قائمة فروع — تُقرأ هنا من نطاق المستخدم مباشرة
        $branchOptions = \App\Support\DataScope::branchOptions();
        $listCurrency = \App\Support\CurrencySettings::code();
    @endphp

    <div class="content-body">

        @section('page-actions')
            <button type="button" id="printInvoicesBtn" class="btn btn-outline-secondary">
                <i class="feather icon-printer"></i> طباعة
            </button>
            <button type="button" id="exportInvoicesBtn" class="btn btn-outline-success">
                <i class="feather icon-download"></i> تصدير Excel
            </button>
            @if (hasPermission('purchases.invoices.create'))
                <a href="{{ route('store_purchase_invoices.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة فاتورة شراء
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'فواتير الشراء',
            'icon' => 'file-text',
            'sub' => 'فواتير شراء البضائع من الموردين وحركة المرتجعات',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'فواتير الشراء' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">إجمالي الفواتير</span>
                <span class="stk-metric__value">{{ $purchaseData->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">فواتير مدفوعة</span>
                <span class="stk-metric__value">{{ $purchaseData->where('payment_status', 'paid')->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                <span class="stk-metric__label">فواتير معلقة</span>
                <span class="stk-metric__value">{{ $purchaseData->where('payment_status', 'unpaid')->count() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي المبالغ</span>
                <span class="stk-metric__value">{{ number_format($purchaseData->sum('grand_total'), 0) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleAdvancedSearch" aria-controls="advancedSearch">
                        <i class="feather icon-sliders"></i> بحث متقدم
                    </button>
                </div>

                <form method="GET" action="{{ route('store_purchase_invoices.index') }}" id="search-form">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="supplier_id">المورد</label>
                                <select name="employee_search" class="form-control select2 search-input" id="supplier_id">
                                    <option value="">البحث بواسطة إسم المورد</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('employee_search') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}@if (filled($supplier->number_suply)) — #{{ $supplier->number_suply }}@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="number_invoice">رقم الفاتورة</label>
                                <input type="text" name="number_invoice" class="form-control search-input" id="number_invoice"
                                    placeholder="ابحث برقم الفاتورة" value="{{ request('number_invoice') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="payment_status">حالة الدفع</label>
                                <select name="payment_status" class="form-control search-input" id="payment_status">
                                    <option value="">الكل</option>
                                    <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>مدفوع</option>
                                    <option value="partial" {{ request('payment_status') === 'partial' ? 'selected' : '' }}>مدفوع جزئياً</option>
                                    <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>غير مدفوع</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="receiving_status">حالة الاستلام</label>
                                <select name="receiving_status" class="form-control search-input" id="receiving_status">
                                    <option value="">الكل</option>
                                    <option value="received" {{ request('receiving_status') === 'received' ? 'selected' : '' }}>تم الاستلام</option>
                                    <option value="partially_receiv" {{ request('receiving_status') === 'partially_receiv' ? 'selected' : '' }}>استلام جزئي</option>
                                    <option value="not_received" {{ request('receiving_status') === 'not_received' ? 'selected' : '' }}>لم يتم الاستلام</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ request()->hasAny(['start_date_from', 'start_date_to', 'created_by', 'source', 'branch']) ? 'show' : '' }}" id="advancedSearch">
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date_from">تاريخ (من)</label>
                                    <input type="date" class="form-control search-input" name="start_date_from" id="start_date_from" value="{{ request('start_date_from') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date_to">تاريخ (إلى)</label>
                                    <input type="date" class="form-control search-input" name="start_date_to" id="start_date_to" value="{{ request('start_date_to') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="created_by">أضيفت بواسطة</label>
                                    <select class="form-control select2 search-input" name="created_by" id="created_by">
                                        <option value="">اختر المستخدم</option>
                                        @foreach ($users ?? [] as $user)
                                            <option value="{{ $user->id }}" {{ request('created_by') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="source">المصدر</label>
                                    <select class="form-control search-input" name="source" id="source">
                                        <option value="">إختر المصدر</option>
                                        <option value="invoice" {{ request('source') === 'invoice' ? 'selected' : '' }}>فاتورة</option>
                                        <option value="Return" {{ request('source') === 'Return' ? 'selected' : '' }}>المرتجع</option>
                                    </select>
                                </div>
                            </div>
                            @if ($branchOptions->count() > 1)
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="branch_filter">الفرع</label>
                                        <select class="form-control search-input" name="branch" id="branch_filter">
                                            <option value="">كل الفروع المتاحة</option>
                                            @foreach ($branchOptions as $branchOption)
                                                <option value="{{ $branchOption->id }}" {{ (string) request('branch') === (string) $branchOption->id ? 'selected' : '' }}>{{ $branchOption->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-warning btn-sm" id="clear-filters">
                                    <i class="feather icon-x"></i> مسح الفلاتر
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <div id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></div>
                        <button type="submit" class="btn btn-primary" id="search-btn">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card" id="reportCard">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة فواتير الشراء</h6>
                <div class="d-flex align-items-center gap-2">
                    <div id="pagination-top">
                        @if ($purchaseData->hasPages())
                            <span class="text-muted mx-2">
                                {{ $purchaseData->firstItem() }}-{{ $purchaseData->lastItem() }} من {{ $purchaseData->total() }}
                            </span>
                        @endif
                    </div>
                    <span class="badge badge-light-primary"><span id="invoices-total">{{ $purchaseData->total() }}</span> فاتورة</span>
                </div>
            </div>

            <div id="table-container">
                @include('onlinestore::purchases.invoices_purchase.partials.table', ['purchaseData' => $purchaseData])
            </div>
        </div>

        {{-- الحذف نموذجٌ حقيقيّ لا AJAX: المتحكّم يردّ بتحويلٍ ورسالة (نجاح أو سبب
             الرفض)، وطلب AJAX كان يتبع التحويل فيُعلن «تم الحذف» حتى لو رُفض --}}
        <form id="storeInvoiceDeleteForm" method="POST" action="" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    let searchTimeout;

    // «بحث متقدم»: تبديلٌ يدويّ للصنف — محرّكا Bootstrap 4 و5 محمَّلان معاً
    // وكلٌّ ينتظر سمته، فلا يُعتمد على أيٍّ منهما
    $('#toggleAdvancedSearch').on('click', function() {
        $('#advancedSearch').toggleClass('show');
    });

    // تهيئة Select2
    if ($('#supplier_id').length) {
        $('#supplier_id').select2({
            placeholder: 'اختر المورد',
            allowClear: true,
            dir: 'rtl'
        });
    }

    if ($('#created_by').length) {
        $('#created_by').select2({
            placeholder: 'اختر المستخدم',
            allowClear: true,
            dir: 'rtl'
        });
    }

    // البحث التلقائي عند الكتابة أو التغيير
    $('.search-input').on('input change', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performSearch();
        }, 500);
    });

    // البحث التلقائي لـ Select2
    $('#supplier_id, #created_by').on('select2:select select2:clear', function() {
        performSearch();
    });

    // البحث عند الضغط على زر البحث
    $('#search-form').on('submit', function(e) {
        e.preventDefault();
        performSearch();
    });

    // مسح الفلاتر
    $('#clear-filters').on('click', function() {
        $('#search-form')[0].reset();
        $('#supplier_id, #created_by').val(null).trigger('change');
        performSearch();
    });

    // التعامل مع روابط الـ pagination
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        let url = $(this).attr('href');
        let page = new URL(url).searchParams.get('page');
        performSearch(page);
    });

    // دالة البحث الرئيسية
    function performSearch(page = 1) {
        showLoading();

        let formData = $('#search-form').serialize();
        formData += '&page=' + page;
        formData += '&is_ajax=1';

        $.ajax({
            url: '{{ route('store_purchase_invoices.index') }}',
            method: 'GET',
            data: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            cache: false,
            success: function(response) {
                if (response.success) {
                    $('#table-container').html(response.data);
                    // الخادم يعيد أرقام الصفحة لا HTML — يُبنى منها عدّاد الرأس
                    $('#pagination-top').html(response.last_page > 1 && response.from
                        ? '<span class="text-muted mx-2">' + response.from + '-' + response.to + ' من ' + response.total + '</span>'
                        : '');
                    $('#invoices-total').text(response.total);
                    initializeEvents();
                }
            },
            error: function(xhr, status, error) {
                console.error('خطأ في تحميل البيانات:', error);
                $('#table-container').html(
                    '<div class="alert alert-danger text-center">' +
                    '<p class="mb-0">حدث خطأ في تحميل البيانات. يرجى المحاولة مرة أخرى.</p>' +
                    '</div>'
                );
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    function showLoading() {
        $('#table-container').css('opacity', '0.6');
        $('#loading-spinner').removeClass('d-none');
        $('#search-btn').prop('disabled', true);
    }

    function hideLoading() {
        $('#table-container').css('opacity', '1');
        $('#loading-spinner').addClass('d-none');
        $('#search-btn').prop('disabled', false);
    }

    function initializeEvents() {
        // أحداث الحذف
        $('.delete-invoice').off('click').on('click', function(e) {
            e.preventDefault();
            const invoiceId = $(this).data('id');
            const invoiceCode = $(this).data('code') || '';

            Swal.fire({
                title: 'تأكيد الحذف',
                text: 'سيُحذف المستند ' + invoiceCode + ' مع قيوده المحاسبية وإذنه المخزني. لا يمكن التراجع.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteInvoice(invoiceId);
                }
            });
        });
    }

    function deleteInvoice(invoiceId) {
        const form = document.getElementById('storeInvoiceDeleteForm');
        form.action = `{{ url('online_store/purchase-invoices') }}/${invoiceId}`;
        form.submit();
    }

    initializeEvents();
});

// =====================================
// طباعة وتصدير مخصّصان لقائمة فواتير الشراء — نفس نمط
// Account\GeneralAccountsController (public/js/print-table.js +
// public/js/excel-export-helper.js). بيانات الجدول تُقرأ من DOM مباشرة
// لأن الجدول يُعاد تصييره بالكامل عبر AJAX عند البحث/التنقّل بين الصفحات
// (performSearch أعلاه) — فتبقى الطباعة والتصدير متوافقين مع الفلترة
// الحالية دون ازدواج منطق.
// =====================================
function getInvoicesTableData() {
    const rows = [];
    $('#invoicesTable tbody tr').each(function () {
        const cells = $(this).find('td');
        if (cells.length < 10) return; // صف الحالة الفارغة (colspan)

        // النص يُضغط: الخلايا تحمل أسطراً ومسافات تصييرٍ لا تصلح لخانة Excel
        const text = (cell) => $(cell).text().replace(/\s+/g, ' ').trim();

        rows.push({
            number: text($(cells[1]).find('.stk-media__title')) || text(cells[1]),
            supplier: text(cells[2]),
            branch: text(cells[3]),
            invoiceDate: text(cells[4]),
            total: text(cells[5]),
            remaining: text(cells[6]),
            paymentStatus: text(cells[7]),
            receivingStatus: text(cells[8]),
        });
    });
    return rows;
}

function printInvoicesList() {
    printTable({
        tableSelector: '#invoicesTable tbody tr',
        title: 'تقرير فواتير الشراء',
        infoCards: [
            { label: 'إجمالي الفواتير', value: '{{ $purchaseData->total() ?? 0 }}' },
            { label: 'فواتير مدفوعة', value: '{{ $purchaseData->where('payment_status', 'paid')->count() ?? 0 }}', class: 'text-success' },
            { label: 'فواتير معلقة', value: '{{ $purchaseData->where('payment_status', 'unpaid')->count() ?? 0 }}', class: 'text-danger' },
            { label: 'إجمالي المبالغ', value: '{{ \App\Support\CurrencySettings::number($purchaseData->sum('grand_total')) }} {{ $listCurrency }}' }
        ],
        filterInfo: {
            @if(request('employee_search')) 'المورد': @json(optional($suppliers->firstWhere('id', request('employee_search')))->name), @endif
            @if(request('number_invoice')) 'رقم الفاتورة': @json(request('number_invoice')), @endif
            @if(request('payment_status')) 'حالة الدفع': @json(request('payment_status')), @endif
            @if(request('receiving_status')) 'حالة الاستلام': @json(request('receiving_status')), @endif
            @if(request('start_date_from')) 'من تاريخ': @json(request('start_date_from')), @endif
            @if(request('start_date_to')) 'إلى تاريخ': @json(request('start_date_to')), @endif
        },
        tableHeaders: ['#', 'رقم الفاتورة', 'المورد', 'الفرع', 'تاريخ الفاتورة', 'المبلغ الإجمالي', 'المتبقّي', 'حالة الدفع', 'حالة الاستلام'],
        columnWidths: ['4%', '12%', '17%', '11%', '10%', '13%', '12%', '10%', '11%'],
        skipColumns: [9]
    });
}

function exportInvoicesListExcel() {
    const rows = getInvoicesTableData();

    const config = {
        fileName: 'تقرير_فواتير_الشراء',
        mainTitle: 'تقرير فواتير الشراء',
        subTitle: 'المتجر الإلكتروني',

        reportInfo: {
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
        },

        statisticsCards: [
            { title: 'إجمالي الفواتير', value: '{{ $purchaseData->total() ?? 0 }}', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'فواتير مدفوعة', value: '{{ $purchaseData->where('payment_status', 'paid')->count() ?? 0 }}', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'فواتير معلقة', value: '{{ $purchaseData->where('payment_status', 'unpaid')->count() ?? 0 }}', titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
            { title: 'إجمالي المبالغ', value: formatNumber({{ (float) $purchaseData->sum('grand_total') }}), unit: ' ' + @json($listCurrency), titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' }
        ],

        tableTitle: 'قائمة فواتير الشراء',
        tableHeaders: ['#', 'رقم الفاتورة', 'المورد', 'الفرع', 'تاريخ الفاتورة', 'المبلغ الإجمالي', 'المتبقّي', 'حالة الدفع', 'حالة الاستلام'],
        columnWidths: [5, 16, 24, 16, 14, 18, 18, 14, 16],

        tableData: rows.map((row, index) => ({
            values: [index + 1, row.number, row.supplier, row.branch, row.invoiceDate, row.total, row.remaining, row.paymentStatus, row.receivingStatus],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' }, color: 'primary', bold: true },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'center' } }
            ]
        })),

        buttonSelector: '#exportInvoicesBtn',
        successMessage: 'تم تصدير تقرير فواتير الشراء بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير التقرير'
    };

    exportToExcelProfessional(config);
}

function formatNumber(n, decimals = 2) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

$('#printInvoicesBtn').on('click', printInvoicesList);
$('#exportInvoicesBtn').on('click', exportInvoicesListExcel);
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
@endsection
