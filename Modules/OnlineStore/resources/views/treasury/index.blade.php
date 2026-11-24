@extends('onlinestore::master')

@section('title', 'الخزائن والحسابات البنكية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('account.treasury.bank_create'))
                <a href="{{ route('store_treasury.create_bank') }}" class="btn btn-success">
                    <i class="feather icon-credit-card"></i> إضافة حساب بنكي
                </a>
            @endif
            @if (hasPermission('account.treasury.create'))
                <a href="{{ route('store_treasury.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إضافة خزينة
                </a>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'الخزائن والحسابات البنكية',
            'icon' => 'briefcase',
            'sub' => 'خزائن المتجر الإلكتروني وحساباته البنكية وأرصدتها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الخزائن والحسابات البنكية' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <p class="mb-0">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
                <span class="stk-metric__label">إجمالي الحسابات</span>
                <span class="stk-metric__value">{{ $totalCount ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">حسابات نشطة</span>
                <span class="stk-metric__value">{{ $activeCount ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-archive"></i></span>
                <span class="stk-metric__label">خزائن</span>
                <span class="stk-metric__value">{{ $treasuryCount ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-credit-card"></i></span>
                <span class="stk-metric__label">حسابات بنكية</span>
                <span class="stk-metric__value">{{ $bankCount ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الأرصدة</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($totalBalance ?? 0) }}</span>
            </div>
        </div>

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                </div>

                <form method="GET" action="{{ route('store_treasury.index') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">بحث بكلمة مفتاحية</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                    placeholder="الإسم أو الكود…" value="{{ request('keywords') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="category">النوع</label>
                                <select name="category" id="category" class="form-control select2">
                                    <option value="">كل الأنواع</option>
                                    <option value="1" @selected(request('category') === '1')>حساب بنكي</option>
                                    <option value="2" @selected(request('category') === '2')>خزينة</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">كل الحالات</option>
                                    <option value="1" @selected(request('status') === '1')>نشط</option>
                                    <option value="0" @selected(request('status') === '0')>غير نشط</option>
                                </select>
                            </div>
                        </div>

                        {{-- فلتر الفرع — خياراته مقصوصة على نطاق المستخدم، ويشمل ما تحت الفرع المختار --}}
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="branch">الفرع</label>
                                <select name="branch" id="branch" class="form-control select2">
                                    <option value="">كل الفروع المتاحة</option>
                                    @foreach ($branches ?? [] as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) request('branch') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('store_treasury.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card" id="reportCard">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> قائمة الخزائن والحسابات البنكية</h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="printTreasuriesBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="feather icon-printer"></i> طباعة
                    </button>
                    <button type="button" id="exportTreasuriesExcel" class="btn btn-sm btn-outline-success">
                        <i class="feather icon-file-text"></i> تصدير Excel
                    </button>
                    {{-- المسار يشترط «عرض التحويلات» والحفظ يشترط «إنشاء تحويل» — فالزرّ لمن يملكهما معاً --}}
                    @if (hasPermission('account.treasury_transfers.view') && hasPermission('account.treasury_transfers.create'))
                        <a href="{{ route('store_treasury.transfer_create') }}" class="btn btn-sm btn-outline-info">
                            <i class="feather icon-repeat"></i> تحويل بين الخزائن
                        </a>
                    @endif
                    <span class="badge badge-light-primary">{{ $treasuries->total() }} حساب</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0" id="treasuriesTable">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>الاسم</th>
                            <th class="text-center">النوع</th>
                            <th>الوصف</th>
                            <th class="text-center">الرصيد</th>
                            <th class="text-center">الحالة</th>
                            @if (hasPermission(['account.treasury.view', 'account.treasury.edit', 'account.treasury.delete', 'account.treasury.toggle_status', 'account.treasury_transfers.view']))
                                <th class="text-center" style="width: 80px;">الإجراءات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($treasuries as $treasury)
                            @php
                                $row = $treasuryRows[$treasury->id] ?? null;
                                $isBank = (int) $treasury->parent_id === (int) ($bankParentId ?? 0);
                                $rowCurrency = $row?->currency ?: \App\Support\CurrencySettings::code();
                            @endphp
                            <tr>
                                <td class="text-center stk-num">{{ $loop->iteration + ($treasuries->currentPage() - 1) * $treasuries->perPage() }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb">
                                            <i class="feather icon-{{ $isBank ? 'credit-card' : 'archive' }}"></i>
                                        </span>
                                        <span class="stk-media__body">
                                            <span class="stk-media__title">{{ $treasury->name }}</span>
                                            <span class="stk-media__sub">
                                                {{ $branchNames[$treasury->branch_id] ?? 'بلا فرع' }}
                                                @if ((int) ($defaultTreasuryId ?? 0) === (int) $treasury->id)
                                                    · <span class="text-primary">الافتراضية للمتجر</span>
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if (! $isBank)
                                        <span class="badge badge-light-success">خزينة</span>
                                    @else
                                        <span class="badge badge-light-primary">حساب بنكي</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted">{{ Str::limit($row?->description, 30) ?: 'غير محدد' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ ($treasury->balance ?? 0) >= 0 ? 'success' : 'danger' }}">
                                        <span class="js-balance">{{ \App\Support\CurrencySettings::number($treasury->balance ?? 0) }}</span>
                                        <small class="js-currency">{{ $rowCurrency }}</small>
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if (hasPermission('account.treasury.toggle_status'))
                                        <span class="stk-state {{ $treasury->is_active == 1 ? 'stk-state--on' : 'stk-state--off' }} toggle-status cursor-pointer"
                                            data-id="{{ $treasury->id }}" data-name="{{ $treasury->name }}" data-status="{{ $treasury->is_active }}">
                                            {{ $treasury->is_active == 1 ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @else
                                        <span class="stk-state {{ $treasury->is_active == 1 ? 'stk-state--on' : 'stk-state--off' }}">
                                            {{ $treasury->is_active == 1 ? 'نشط' : 'غير نشط' }}
                                        </span>
                                    @endif
                                </td>
                                @if (hasPermission(['account.treasury.view', 'account.treasury.edit', 'account.treasury.delete', 'account.treasury.toggle_status', 'account.treasury_transfers.view']))
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="feather icon-more-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if (hasPermission('account.treasury.view'))
                                                    <a class="dropdown-item" href="{{ route('store_treasury.show', $treasury->id) }}">
                                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                                    </a>
                                                @endif
                                                @if (hasPermission('account.treasury.edit'))
                                                    <a class="dropdown-item" href="{{ route($isBank ? 'store_treasury.edit_bank' : 'store_treasury.edit', $treasury->id) }}">
                                                        <i class="feather icon-edit-2"></i> تعديل
                                                    </a>
                                                @endif
                                                @if (hasPermission('account.treasury.toggle_status'))
                                                    <a class="dropdown-item" href="{{ route('store_treasury.update_status', $treasury->id) }}">
                                                        @if ($treasury->is_active == 1)
                                                            <i class="feather icon-slash"></i> تعطيل
                                                        @else
                                                            <i class="feather icon-check"></i> تفعيل
                                                        @endif
                                                    </a>
                                                @endif
                                                @if (hasPermission('account.treasury_transfers.view') && hasPermission('account.treasury_transfers.create'))
                                                    <a class="dropdown-item" href="{{ route('store_treasury.transfer_create') }}">
                                                        <i class="feather icon-repeat"></i> تحويل
                                                    </a>
                                                @endif
                                                @if (hasPermission('account.treasury.delete'))
                                                    <div class="dropdown-divider"></div>
                                                    <form action="{{ route('store_treasury.destroy', $treasury->id) }}" method="POST" class="delete-form">
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
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لا توجد خزائن أو حسابات بنكية</h6>
                                        <p class="stk-empty__hint">ابدأ بإضافة أول خزينة أو حساب بنكي.</p>
                                        <div>
                                            @if (hasPermission('account.treasury.create'))
                                                <a href="{{ route('store_treasury.create') }}" class="btn btn-primary btn-sm">
                                                    <i class="feather icon-plus"></i> إضافة خزينة
                                                </a>
                                            @endif
                                            @if (hasPermission('account.treasury.bank_create'))
                                                <a href="{{ route('store_treasury.create_bank') }}" class="btn btn-success btn-sm ms-1">
                                                    <i class="feather icon-credit-card"></i> إضافة حساب بنكي
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $treasuries, 'label' => 'حساب'])
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
function formatNumber(n, decimals = 2) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

$(document).ready(function() {
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', allowClear: true, width: '100%' });
    }

    // طباعة قائمة الخزائن والحسابات البنكية
    $('#printTreasuriesBtn').click(function() {
        printTreasuriesReport();
    });

    // تصدير قائمة الخزائن والحسابات البنكية إلى Excel
    $('#exportTreasuriesExcel').click(function() {
        exportTreasuriesReport();
    });

    // تبديل الحالة
    $('.toggle-status').click(function() {
        var btn = $(this);
        var treasuryId = btn.data('id');
        var treasuryName = btn.data('name');
        var currentStatus = btn.data('status');
        var newStatus = currentStatus == 1 ? 0 : 1;

        Swal.fire({
            title: 'تأكيد تغيير الحالة',
            text: newStatus == 1 ?
                `هل تريد تفعيل "${treasuryName}"؟` :
                `هل تريد تعطيل "${treasuryName}"؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus == 1 ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: newStatus == 1 ? 'نعم، فعّل' : 'نعم، عطّل',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ route('store_treasury.update_status', '__ID__') }}".replace('__ID__', treasuryId);
            }
        });
    });

    // تأكيد الحذف
    $('.delete-form').submit(function(e) {
        e.preventDefault();
        var form = this;

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذه الخزينة/الحساب نهائياً!',
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
// معلومات الفلاتر المطبَّقة حالياً
// =====================================
var treasuriesFilterInfo = {
    @if(request('keywords'))
    'بحث بكلمة مفتاحية': @json(request('keywords')),
    @endif
    @if(request('category') === '1' || request('category') === '2')
    'النوع': @json(request('category') === '1' ? 'حساب بنكي' : 'خزينة'),
    @endif
    @if(request('status') === '1' || request('status') === '0')
    'الحالة': @json(request('status') === '1' ? 'نشط' : 'غير نشط'),
    @endif
};

var treasuriesInfoCards = [
    { label: 'إجمالي الحسابات', value: '{{ $totalCount ?? 0 }}' },
    { label: 'حسابات نشطة', value: '{{ $activeCount ?? 0 }}', class: 'text-success' },
    { label: 'خزائن', value: '{{ $treasuryCount ?? 0 }}' },
    { label: 'حسابات بنكية', value: '{{ $bankCount ?? 0 }}' },
    { label: 'إجمالي الأرصدة', value: formatNumber({{ $totalBalance ?? 0 }}) + ' ' + window.AppCurrency.symbol }
];

// =====================================
// جمع بيانات جدول الخزائن من DOM (يتوافق مع صفحة الترقيم الحالية)
// =====================================
function collectTreasuriesRows() {
    var rows = [];

    $('#treasuriesTable tbody tr').each(function() {
        var cells = $(this).find('td');
        if (cells.length < 6) return; // تجاهل صف "لا توجد بيانات"

        rows.push({
            rowNum: $(cells[0]).text().trim(),
            name: $(cells[1]).find('.stk-media__title').text().trim(),
            type: $(cells[2]).text().trim(),
            description: $(cells[3]).text().trim(),
            balance: $(cells[4]).find('.js-balance').text().trim() || $(cells[4]).text().trim(),
            currency: $(cells[4]).find('.js-currency').text().trim() || window.AppCurrency.symbol,
            balancePositive: $(cells[4]).find('.badge-light-success').length > 0,
            status: $(cells[5]).find('.stk-state').text().trim() || $(cells[5]).text().trim()
        });
    });

    return rows;
}

// =====================================
// الطباعة
// =====================================
function printTreasuriesReport() {
    var rows = collectTreasuriesRows();

    var $hidden = $('#treasuriesPrintTable');
    if ($hidden.length === 0) {
        $hidden = $('<table id="treasuriesPrintTable" style="display:none"><tbody></tbody></table>').appendTo('body');
    }

    var html = '';
    rows.forEach(function(r) {
        html += '<tr>' +
            '<td>' + $('<div>').text(r.rowNum).html() + '</td>' +
            '<td>' + $('<div>').text(r.name).html() + '</td>' +
            '<td>' + $('<div>').text(r.type).html() + '</td>' +
            '<td>' + $('<div>').text(r.description).html() + '</td>' +
            '<td>' + $('<div>').text(r.balance + ' ' + r.currency).html() + '</td>' +
            '<td>' + $('<div>').text(r.status).html() + '</td>' +
            '</tr>';
    });
    $hidden.find('tbody').html(html);

    printTable({
        tableSelector: '#treasuriesPrintTable tbody tr',
        title: 'قائمة الخزائن والحسابات البنكية - المتجر الإلكتروني',
        infoCards: treasuriesInfoCards,
        filterInfo: treasuriesFilterInfo,
        tableHeaders: ['#', 'الاسم', 'النوع', 'الوصف', 'الرصيد', 'الحالة'],
        columnWidths: ['6%', '26%', '14%', '24%', '16%', '14%'],
        descriptionColumn: 4,
        windowWidth: 1200,
        windowHeight: 800
    });
}

// =====================================
// تصدير Excel
// =====================================
function exportTreasuriesReport() {
    var rows = collectTreasuriesRows();

    var tableData = rows.map(function(r) {
        return {
            values: [
                r.rowNum,
                r.name,
                r.type,
                r.description,
                r.balance + ' ' + r.currency,
                r.status
            ],
            cellStyles: [
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' } },
                { alignment: { horizontal: 'right' } },
                { alignment: { horizontal: 'center' }, color: r.balancePositive ? 'success' : 'danger' },
                { alignment: { horizontal: 'center' } }
            ]
        };
    });

    var config = {
        fileName: 'قائمة_الخزائن_والحسابات_البنكية',
        mainTitle: 'قائمة الخزائن والحسابات البنكية - المتجر الإلكتروني',
        subTitle: 'تقرير مخصَّص',

        reportInfo: Object.assign({
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        }, treasuriesFilterInfo),

        statisticsCards: [
            { title: 'إجمالي الحسابات', value: '{{ $totalCount ?? 0 }}', unit: ' حساب', titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayLight', borderColor: 'primary' },
            { title: 'حسابات نشطة', value: '{{ $activeCount ?? 0 }}', unit: ' حساب', titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'خزائن', value: '{{ $treasuryCount ?? 0 }}', unit: ' خزينة', titleColor: 'purple', valueColor: 'purple', backgroundColor: 'purpleLight', borderColor: 'purple' },
            { title: 'حسابات بنكية', value: '{{ $bankCount ?? 0 }}', unit: ' حساب', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' },
            { title: 'إجمالي الأرصدة', value: formatNumber({{ $totalBalance ?? 0 }}), unit: ' ' + window.AppCurrency.symbol, titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' }
        ],

        tableTitle: 'قائمة الخزائن والحسابات البنكية',
        tableHeaders: ['#', 'الاسم', 'النوع', 'الوصف', 'الرصيد', 'الحالة'],
        columnWidths: [6, 30, 16, 34, 18, 14],

        tableData: tableData,

        buttonSelector: '#exportTreasuriesExcel',
        successMessage: 'تم تصدير قائمة الخزائن والحسابات البنكية بنجاح!',
        errorMessage: 'حدث خطأ أثناء تصدير القائمة'
    };

    exportToExcelProfessional(config);
}
</script>
@endsection
