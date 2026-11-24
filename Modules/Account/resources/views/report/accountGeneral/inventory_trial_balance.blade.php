@extends('account::master')

@section('title')
    ميزان مراجعة المخزون
@stop

@section('content')
@include('account::components.page-head', [
    'title' => 'ميزان مراجعة المخزون',
    'crumbs' => [
        'التقارير' => route('GeneralAccountReports.index'),
        'ميزان مراجعة المخزون' => null,
    ],
])

<div class="content-body">
    <div class="alert alert-info">
        <i class="feather icon-info"></i>
        التقرير يقتصر على شجرة المخزون. يبحث في الحساب الأب: <strong>{{ $diagnostics['parent_id_used'] }}</strong>
        @if(!$diagnostics['parent_id_16_exists'])
            <span class="badge bg-warning">غير موجود في DB</span>
        @endif
        — تم العثور على <strong>{{ $diagnostics['inventory_tree_count'] }}</strong> حساب في شجرة المخزون.
        @if($diagnostics['inventory_tree_count'] === 0)
            <hr>
            <strong class="text-danger">⚠ لا يوجد حسابات تحت الحساب {{ $diagnostics['parent_id_used'] }}.</strong>
            تحقق من شجرة الحسابات وتأكد من رقم حساب الأب الصحيح للمخزون في نظامك،
            ثم صحّحه من شاشة «الحسابات المحاسبية» في الإعدادات (مفتاح <code>inventory</code>).
        @endif
    </div>

    {{-- Filters & toolbar --}}
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <div class="card-title">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div><i class="feather icon-filter"></i> بحث وفلترة</div>
                        <div>
                            <button class="btn btn-outline-success" id="exportExcel">
                                <i class="fa fa-file-excel me-2"></i>تصدير Excel
                            </button>
                            <button class="btn btn-outline-primary" id="printBtn">
                                <i class="fa fa-print me-2"></i>طباعة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form id="reportForm" method="GET" action="{{ route('GeneralAccountReports.inventoryTrialBalance') }}" class="form">
                    <div class="form-body row">
                        {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                        @include('partials.accounting-period-filter', [
                            'fromName' => 'from_date',
                            'toName' => 'to_date',
                            'colClass' => 'form-group col-md-3',
                        ])

                        <div class="form-group col-md-3">
                            <label>من تاريخ</label>
                            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label>إلى تاريخ</label>
                            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label>نوع الفترة</label>
                            @php $dt = $dateType ?? 'custom'; @endphp
                            <select name="date_type" class="form-control">
                                <option value="custom"     {{ $dt=='custom' ? 'selected' : '' }}>مخصص</option>
                                <option value="this_month" {{ $dt=='this_month' ? 'selected' : '' }}>هذا الشهر</option>
                                <option value="last_month" {{ $dt=='last_month' ? 'selected' : '' }}>الشهر الماضي</option>
                                <option value="this_quarter" {{ $dt=='this_quarter' ? 'selected' : '' }}>هذا الربع</option>
                                <option value="this_year"  {{ $dt=='this_year' ? 'selected' : '' }}>هذا العام</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>الفرع</label>
                            <select name="branch" id="branch" class="form-control">
                                <option value="">جميع الفروع</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ ($branchFilter ?? '') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>الحساب (مخزون محدد)</label>
                            <select name="account_id" id="account_id" class="form-control">
                                <option value="">كل حسابات المخزون</option>
                                @foreach($inventoryAccountsList as $acc)
                                    <option value="{{ $acc->id }}" {{ ($accountIdFilter ?? '') == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} - {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>نوع الحركة</label>
                            <select name="movement_type" class="form-control">
                                <option value="all" {{ $movementType=='all' ? 'selected' : '' }}>الكل</option>
                                <option value="in"  {{ $movementType=='in' ? 'selected' : '' }}>داخل (مدين)</option>
                                <option value="out" {{ $movementType=='out' ? 'selected' : '' }}>خارج (دائن)</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>رقم القيد</label>
                            <input type="text" name="reference_number" class="form-control" placeholder="..." value="{{ $referenceFilter ?? '' }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label>بحث في البيان</label>
                            <input type="text" name="search" class="form-control" placeholder="..." value="{{ $searchFilter ?? '' }}">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" id="filterBtn"><i class="fa fa-search"></i> بحث</button>
                        <a href="{{ route('GeneralAccountReports.inventoryTrialBalance') }}" class="btn btn-outline-danger"><i class="fa fa-times"></i> إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Summary stats --}}
    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
            <span class="stk-metric__label">الرصيد الافتتاحي الإجمالي</span>
            <span class="stk-metric__value {{ $totals['opening'] >= 0 ? 'text-primary' : 'text-danger' }}" id="statOpening">{{ number_format($totals['opening'], 2) }}</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
            <span class="stk-metric__label">إجمالي مدين (داخل)</span>
            <span class="stk-metric__value">{{ number_format($totals['debit'], 2) }}</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
            <span class="stk-metric__label">إجمالي دائن (خارج)</span>
            <span class="stk-metric__value">{{ number_format($totals['credit'], 2) }}</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
            <span class="stk-metric__label">الرصيد الختامي الإجمالي</span>
            <span class="stk-metric__value {{ $totals['closing'] >= 0 ? 'text-primary' : 'text-danger' }}" id="statClosing">{{ number_format($totals['closing'], 2) }}</span>
        </div>
    </div>

    {{-- Summary table per account --}}
    <div class="card" id="summaryCard">
        <div class="card-content">
            <div class="card-body">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> ملخص حسابات المخزون</h6>
                @if($summary->isEmpty())
                    <div class="alert alert-warning text-center">
                        <i class="feather icon-alert-circle"></i>
                        لا توجد حسابات مخزون مطابقة لمعايير البحث.
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover" id="summaryTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>الكود</th>
                                <th>اسم الحساب</th>
                                <th class="text-left">الرصيد الافتتاحي</th>
                                <th class="text-left text-success">إجمالي مدين (داخل)</th>
                                <th class="text-left text-danger">إجمالي دائن (خارج)</th>
                                <th class="text-left">الرصيد الختامي</th>
                                <th class="text-center">القيود</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summary as $i => $s)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><span class="badge bg-light text-dark">{{ $s['code'] }}</span></td>
                                    <td><strong>{{ $s['name'] }}</strong></td>
                                    <td class="text-left">{{ number_format($s['opening_balance'], 2) }}</td>
                                    <td class="text-left text-success fw-bold">{{ number_format($s['total_debit'], 2) }}</td>
                                    <td class="text-left text-danger fw-bold">{{ number_format($s['total_credit'], 2) }}</td>
                                    <td class="text-left fw-bold {{ $s['closing_balance'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($s['closing_balance'], 2) }}</td>
                                    <td class="text-center"><span class="badge bg-info">{{ $s['entries_count'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:#f8f9fa; font-weight:bold;">
                                <td colspan="3" class="text-left">الإجمالي</td>
                                <td class="text-left">{{ number_format($totals['opening'], 2) }}</td>
                                <td class="text-left text-success">{{ number_format($totals['debit'], 2) }}</td>
                                <td class="text-left text-danger">{{ number_format($totals['credit'], 2) }}</td>
                                <td class="text-left text-primary">{{ number_format($totals['closing'], 2) }}</td>
                                <td class="text-center">{{ $totals['entries'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Movements (paginated) --}}
    <div class="card" id="movementsCard">
        <div class="card-content">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 class="card-title mb-0"><i class="feather icon-activity"></i> الحركات التفصيلية</h4>
                    <small class="text-muted">إجمالي: {{ $movementsTotal }} حركة • صفحة {{ $page }} / {{ $totalPages }}</small>
                </div>

                @if($movements->isEmpty())
                    <div class="alert alert-info text-center">
                        <i class="feather icon-info"></i>
                        لا توجد حركات مطابقة في الفترة المحددة.
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover" id="movementsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>رقم القيد</th>
                                <th>الحساب</th>
                                <th>الحساب المقابل</th>
                                <th>البيان</th>
                                <th class="text-left">مدين</th>
                                <th class="text-left">دائن</th>
                                <th>الفرع</th>
                            </tr>
                        </thead>
                        <tbody id="movementsTableBody">
                            @foreach($movements as $i => $m)
                                <tr>
                                    <td>{{ ($page - 1) * $perPage + $i + 1 }}</td>
                                    <td>{{ $m['date'] }}</td>
                                    <td>
                                        <a href="{{ route('journal.show', ['id' => $m['entry_id']]) }}" target="_blank" class="badge bg-light text-dark">
                                            {{ $m['reference_number'] ?? '-' }}
                                        </a>
                                    </td>
                                    <td><strong>{{ $m['account_code'] }}</strong> - {{ $m['account_name'] }}</td>
                                    <td><span class="text-info">{{ $m['counter_accounts'] ?: '-' }}</span></td>
                                    <td><small>{{ $m['description'] ?: '-' }}</small></td>
                                    <td class="text-left text-success">{{ $m['debit'] > 0 ? number_format($m['debit'], 2) : '' }}</td>
                                    <td class="text-left text-danger">{{ $m['credit'] > 0 ? number_format($m['credit'], 2) : '' }}</td>
                                    <td>{{ $m['branch'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @include('stock::components.page-links', [
                    'page' => $page,
                    'totalPages' => $totalPages,
                    'total' => $movementsTotal,
                    'unit' => 'حركة',
                ])
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script src="{{ asset('js/print-table.js') }}"></script>

<script>
$(document).ready(function() {
    $('#exportExcel').on('click', function(e) {
        e.preventDefault();
        exportInventoryReport();
    });

    $('#printBtn').on('click', function(e) {
        e.preventDefault();
        printInventoryReport();
    });
});

function fmt(n) { return parseFloat(n||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

// ===== تصدير Excel =====
function exportInventoryReport() {
    const summary = @json($summary);
    const movements = @json($movements);
    const totals = @json($totals);
    const fromDate = '{{ $fromDate }}';
    const toDate   = '{{ $toDate }}';
    const branchName = $('#branch option:selected').text() || 'جميع الفروع';
    const accountName = $('#account_id option:selected').text() || 'كل حسابات المخزون';

    const tableData = [];
    let row = 1;

    // قسم الملخص
    tableData.push({
        values: ['', 'ملخص حسابات المخزون', '', '', '', '', '', ''],
        mergeFrom: 1, mergeTo: 8,
        alignments: ['center'],
        cellStyles: [{ bold: true, color: 'primary' }]
    });

    summary.forEach(s => {
        tableData.push({
            values: [
                row++, s.code, s.name,
                fmt(s.opening_balance), fmt(s.total_debit), fmt(s.total_credit),
                fmt(s.closing_balance), s.entries_count
            ],
            alignments: ['center','center','right','center','center','center','center','center']
        });
    });
    tableData.push({
        values: ['', '', 'الإجمالي', fmt(totals.opening), fmt(totals.debit), fmt(totals.credit), fmt(totals.closing), totals.entries],
        cellStyles: [null, null, { bold: true }, { bold: true }, { bold: true, color: 'success' }, { bold: true, color: 'danger' }, { bold: true, color: 'primary' }, { bold: true }]
    });
    tableData.push({ values: ['','','','','','','',''] });

    // قسم الحركات (الصفحة الحالية فقط)
    tableData.push({
        values: ['', 'الحركات التفصيلية (الصفحة الحالية)', '', '', '', '', '', ''],
        mergeFrom: 1, mergeTo: 8,
        alignments: ['center'],
        cellStyles: [{ bold: true, color: 'primary' }]
    });
    movements.forEach((m, i) => {
        tableData.push({
            values: [
                i + 1, m.date, m.reference_number || '',
                (m.account_code + ' - ' + m.account_name),
                m.counter_accounts || '-',
                m.description || '-',
                m.debit > 0 ? fmt(m.debit) : '',
                m.credit > 0 ? fmt(m.credit) : ''
            ],
            alignments: ['center','center','center','right','right','right','center','center']
        });
    });

    const config = {
        fileName: 'ميزان_مراجعة_المخزون',
        mainTitle: 'ميزان مراجعة المخزون',
        subTitle: 'نظام المحاسبة المتكامل',
        reportInfo: {
            'الفترة': (fromDate || '-') + ' إلى ' + (toDate || '-'),
            'الفرع': branchName,
            'الحساب': accountName,
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },
        statisticsCards: [
            { title: 'الرصيد الافتتاحي', value: fmt(totals.opening), unit: ' ' + window.AppCurrency.symbol, titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayBg', borderColor: 'primary' },
            { title: 'إجمالي مدين', value: fmt(totals.debit), unit: ' ' + window.AppCurrency.symbol, titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'إجمالي دائن', value: fmt(totals.credit), unit: ' ' + window.AppCurrency.symbol, titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
            { title: 'الرصيد الختامي', value: fmt(totals.closing), unit: ' ' + window.AppCurrency.symbol, titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayBg', borderColor: 'primary' }
        ],
        tableTitle: 'تفاصيل الحسابات والحركات',
        tableHeaders: ['#', 'الكود/التاريخ', 'الحساب/المرجع', 'افتتاحي/الحساب', 'مدين/المقابل', 'دائن/البيان', 'ختامي/مدين', 'القيود/دائن'],
        columnWidths: [6, 12, 26, 18, 18, 26, 14, 14],
        tableData: tableData,
        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير ميزان مراجعة المخزون بنجاح!',
        errorMessage: 'حدث خطأ أثناء التصدير'
    };

    exportToExcelProfessional(config);
}

// ===== الطباعة المخصصة =====
function printInventoryReport() {
    const fromDate = '{{ $fromDate }}';
    const toDate   = '{{ $toDate }}';
    const branchName = $('#branch option:selected').text() || 'جميع الفروع';
    const accountName = $('#account_id option:selected').text() || 'كل حسابات المخزون';

    printTable({
        tableSelector: '#movementsTableBody tr',
        title: 'ميزان مراجعة المخزون',
        infoCards: [
            { label: 'الرصيد الافتتاحي', value: $('#statOpening').text() + ' ' + window.AppCurrency.symbol },
            { label: 'إجمالي مدين', value: $('#statDebit').text() + ' ' + window.AppCurrency.symbol, class: 'text-success' },
            { label: 'إجمالي دائن', value: $('#statCredit').text() + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
            { label: 'الرصيد الختامي', value: $('#statClosing').text() + ' ' + window.AppCurrency.symbol, class: 'text-primary' }
        ],
        filterInfo: {
            'الفترة': (fromDate || '-') + ' → ' + (toDate || '-'),
            'الفرع': branchName,
            'الحساب': accountName
        },
        tableHeaders: ['#', 'التاريخ', 'رقم القيد', 'الحساب', 'الحساب المقابل', 'البيان', 'مدين', 'دائن', 'الفرع'],
        columnWidths: ['4%','9%','9%','18%','15%','22%','9%','9%','5%'],
        descriptionColumn: 6,
        windowWidth: 1400, windowHeight: 900
    });
}
</script>

<style>
    @media print {
        .form-actions, #reportForm, .breadcrumb-wrapper, .alert-info,
        .card-title > div:last-child, .pagination {
            display: none !important;
        }
    }
</style>
@endsection
