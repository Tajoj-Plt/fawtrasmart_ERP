@extends('account::master')

@section('title')
    التدفقات النقدية الإجمالية
@stop

@section('content')
@include('account::components.page-head', [
    'title' => 'التدفقات النقدية الإجمالية',
    'crumbs' => [
        'التقارير المحاسبية' => route('GeneralAccountReports.index'),
        'التدفقات النقدية الإجمالية' => null,
    ],
])

<div class="content-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="alert alert-info mb-0 flex-grow-1 me-2">
            <i class="feather icon-info"></i>
            هذا التقرير ملخص وتجميع لبيانات
            <a href="{{ route('GeneralAccountReports.detailedCashFlow', request()->only(['from_date','to_date','branch','currency','counter_account_id'])) }}" class="alert-link">
                "التدفقات النقدية التفصيلية"
            </a>
            ولا يتضمن أي حركات غير نقدية.
        </div>
        <div class="ms-2">
            <button class="btn btn-outline-success" id="exportExcel"><i class="fa fa-file-excel"></i> تصدير Excel</button>
            <button class="btn btn-outline-primary" id="printBtn"><i class="fa fa-print"></i> طباعة</button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card">
        <div class="card-body">
            <h6 class="stk-table-head__title">بحث وفلترة</h6>
            <form id="reportForm" method="GET" action="{{ route('GeneralAccountReports.cashFlowStatement') }}" class="form">
                <div class="form-body row">
                    {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                    @include('partials.accounting-period-filter', [
                        'fromName' => 'from_date',
                        'toName' => 'to_date',
                        'colClass' => 'form-group col-md-3',
                    ])

                    <div class="form-group col-md-3">
                        <label>من تاريخ</label>
                        <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>إلى تاريخ</label>
                        <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label>نوع الفترة</label>
                        <select name="date_type" class="form-control">
                            @php $dt = $dateType ?? 'custom'; @endphp
                            <option value="custom"       {{ $dt=='custom' ? 'selected' : '' }}>مخصص</option>
                            <option value="today"        {{ $dt=='today' ? 'selected' : '' }}>اليوم</option>
                            <option value="yesterday"    {{ $dt=='yesterday' ? 'selected' : '' }}>أمس</option>
                            <option value="this_week"    {{ $dt=='this_week' ? 'selected' : '' }}>هذا الأسبوع</option>
                            <option value="this_month"   {{ $dt=='this_month' ? 'selected' : '' }}>هذا الشهر</option>
                            <option value="last_month"   {{ $dt=='last_month' ? 'selected' : '' }}>الشهر الماضي</option>
                            <option value="this_quarter" {{ $dt=='this_quarter' ? 'selected' : '' }}>هذا الربع</option>
                            <option value="last_quarter" {{ $dt=='last_quarter' ? 'selected' : '' }}>الربع الماضي</option>
                            <option value="this_year"    {{ $dt=='this_year' ? 'selected' : '' }}>هذا العام</option>
                            <option value="last_year"    {{ $dt=='last_year' ? 'selected' : '' }}>العام الماضي</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label>الفرع</label>
                        <select name="branch" class="form-control">
                            <option value="">جميع الفروع</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ ($branchFilter ?? '') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label>بنك / خزينة محددة</label>
                        <select name="counter_account_id" class="form-control">
                            <option value="">جميع البنوك والخزائن</option>
                            @foreach($cashAccountsList as $acc)
                                <option value="{{ $acc->id }}" {{ ($currentFilters['counter_account_id'] ?? '') == $acc->id ? 'selected' : '' }}>
                                    {{ $acc->code }} - {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                    <a href="{{ route('GeneralAccountReports.cashFlowStatement') }}" class="btn btn-outline-danger"><i class="fa fa-times"></i> إلغاء الفلترة</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Top summary cards --}}
    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
            <span class="stk-metric__label">إجمالي التدفقات الداخلة</span>
            <span class="stk-metric__value">{{ number_format($totals['total_inflows'], 2) }}</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
            <span class="stk-metric__label">إجمالي التدفقات الخارجة</span>
            <span class="stk-metric__value">{{ number_format($totals['total_outflows'], 2) }}</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
            <span class="stk-metric__label">صافي التدفق النقدي</span>
            <span class="stk-metric__value">@money($totals['net_cash_flow'])</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-alert-circle"></i></span>
            <span class="stk-metric__label">عدد الحسابات / القيود</span>
            <span class="stk-metric__value">{{ $totals['accounts_count'] }} / {{ $totals['entries_count'] }}</span>
        </div>
    </div>

    {{-- Cash position --}}
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <h6 class="stk-table-head__title"><i class="feather icon-credit-card"></i> رصيد البنوك والخزائن</h6>
                <div class="row text-center">
                    <div class="col-md-4">
                        <small class="text-muted">رصيد بداية الفترة</small>
                        <h3 class="{{ $cashOpening >= 0 ? 'text-primary' : 'text-danger' }}">@money($cashOpening)</h3>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">صافي التغير</small>
                        <h3 class="{{ ($cashClosing - $cashOpening) >= 0 ? 'text-success' : 'text-danger' }}">
                            @money($cashClosing - $cashOpening)
                        </h3>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">رصيد نهاية الفترة</small>
                        <h3 class="{{ $cashClosing >= 0 ? 'text-primary' : 'text-danger' }}">@money($cashClosing)</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- By account type --}}
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <h6 class="stk-table-head__title"><i class="feather icon-grid"></i> الإجمالي حسب نوع الحساب</h6>
                @if($byType->isEmpty())
                    <div class="alert alert-info text-center">لا توجد بيانات.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>نوع الحساب</th>
                                <th class="text-center">عدد الحسابات</th>
                                <th class="text-left text-success">تدفقات داخلة</th>
                                <th class="text-left text-danger">تدفقات خارجة</th>
                                <th class="text-left">الصافي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($byType as $row)
                                <tr>
                                    <td><strong>{{ $row['type'] }}</strong></td>
                                    <td class="text-center">{{ $row['count'] }}</td>
                                    <td class="text-left text-success">{{ number_format($row['inflow'], 2) }}</td>
                                    <td class="text-left text-danger">{{ number_format($row['outflow'], 2) }}</td>
                                    <td class="text-left {{ $row['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row['net'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- By parent category --}}
    <div class="card">
        <div class="card-content">
            <div class="card-body">
                <h6 class="stk-table-head__title"><i class="feather icon-folder"></i> الإجمالي حسب التصنيف (الحساب الأب)</h6>
                @if($byCategory->isEmpty())
                    <div class="alert alert-info text-center">لا توجد بيانات.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>التصنيف</th>
                                <th class="text-center">عدد الحسابات</th>
                                <th class="text-left text-success">تدفقات داخلة</th>
                                <th class="text-left text-danger">تدفقات خارجة</th>
                                <th class="text-left">الصافي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($byCategory as $row)
                                <tr>
                                    <td><strong>{{ $row['parent_name'] }}</strong></td>
                                    <td class="text-center">{{ $row['count'] }}</td>
                                    <td class="text-left text-success">{{ number_format($row['inflow'], 2) }}</td>
                                    <td class="text-left text-danger">{{ number_format($row['outflow'], 2) }}</td>
                                    <td class="text-left {{ $row['net'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row['net'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Top moved accounts --}}
    <div class="card" id="topAccountsCard">
        <div class="card-content">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 class="card-title mb-0"><i class="feather icon-bar-chart-2"></i> أكثر الحسابات حركة (Top)</h4>
                    <a href="{{ route('GeneralAccountReports.detailedCashFlow', request()->only(['from_date','to_date','branch','currency','counter_account_id'])) }}" class="btn btn-sm btn-outline-primary">
                        <i class="feather icon-list"></i> عرض كل الحسابات والتفاصيل
                    </a>
                </div>
                @if($topAccounts->isEmpty())
                    <div class="alert alert-info text-center">لا توجد حسابات.</div>
                @else
                <div class="table-responsive">
                    <table class="table">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>الكود</th>
                                <th>الحساب</th>
                                <th class="text-left text-success">داخل</th>
                                <th class="text-left text-danger">خارج</th>
                                <th class="text-left">الصافي</th>
                                <th class="text-center">القيود</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topAccounts as $i => $r)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $r['account_code'] ?? '-' }}</td>
                                    <td><strong>{{ $r['account_name'] }}</strong></td>
                                    <td class="text-left text-success">{{ number_format($r['cash_inflow'], 2) }}</td>
                                    <td class="text-left text-danger">{{ number_format($r['cash_outflow'], 2) }}</td>
                                    <td class="text-left {{ ($r['cash_inflow'] - $r['cash_outflow']) >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($r['cash_inflow'] - $r['cash_outflow'], 2) }}
                                    </td>
                                    <td class="text-center"><span class="badge bg-info">{{ $r['entries_count'] }}</span></td>
                                    <td class="text-center">
                                        <a href="{{ route('GeneralAccountReports.detailedCashFlowAccount', ['accountId' => $r['account_id']]) }}?from_date={{ $fromDate }}&to_date={{ $toDate }}&branch={{ $branchFilter }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather icon-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script src="{{ asset('js/print-table.js') }}"></script>

<script>
$(document).ready(function() {
    $('#exportExcel').on('click', function(e){ e.preventDefault(); exportCashFlowSummary(); });
    $('#printBtn').on('click', function(e){ e.preventDefault(); printCashFlowSummary(); });
});
function fmt(n){ return parseFloat(n||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function exportCashFlowSummary() {
    const totals = @json($totals);
    const byType = @json($byType);
    const byCategory = @json($byCategory);
    const topAccounts = @json($topAccounts);
    const fromDate = '{{ $fromDate }}';
    const toDate = '{{ $toDate }}';
    const branchName = $('select[name="branch"] option:selected').text() || 'جميع الفروع';

    const tableData = [];
    // قسم: حسب نوع الحساب
    tableData.push({
        values: ['', 'تجميع حسب نوع الحساب', '', '', ''],
        mergeFrom: 1, mergeTo: 5,
        cellStyles: [{ bold: true, color: 'primary' }]
    });
    byType.forEach(r => {
        tableData.push({
            values: [r.type || '-', r.count, fmt(r.inflow), fmt(r.outflow), fmt(r.net)],
            cellStyles: [{ bold: true }, null, { color: 'success' }, { color: 'danger' }, { bold: true }]
        });
    });
    tableData.push({ values: ['','','','',''] });

    // قسم: حسب التصنيف
    tableData.push({
        values: ['', 'تجميع حسب التصنيف (الأب)', '', '', ''],
        mergeFrom: 1, mergeTo: 5,
        cellStyles: [{ bold: true, color: 'primary' }]
    });
    byCategory.forEach(r => {
        tableData.push({
            values: [r.parent_name || '-', r.count, fmt(r.inflow), fmt(r.outflow), fmt(r.net)],
            cellStyles: [{ bold: true }, null, { color: 'success' }, { color: 'danger' }, { bold: true }]
        });
    });
    tableData.push({ values: ['','','','',''] });

    // قسم: أكثر الحسابات حركة
    tableData.push({
        values: ['', 'أكثر الحسابات حركة', '', '', ''],
        mergeFrom: 1, mergeTo: 5,
        cellStyles: [{ bold: true, color: 'primary' }]
    });
    topAccounts.forEach((r, i) => {
        tableData.push({
            values: [
                (r.account_code || '') + ' - ' + r.account_name,
                r.entries_count, fmt(r.cash_inflow), fmt(r.cash_outflow),
                fmt(r.cash_inflow - r.cash_outflow)
            ]
        });
    });

    exportToExcelProfessional({
        fileName: 'التدفقات_النقدية_الإجمالية',
        mainTitle: 'التدفقات النقدية الإجمالية',
        subTitle: 'ملخص بيانات التدفقات التفصيلية',
        reportInfo: {
            'الفترة': (fromDate || '-') + ' إلى ' + (toDate || '-'),
            'الفرع': branchName,
            'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
        },
        statisticsCards: [
            { title: 'تدفقات داخلة', value: fmt(totals.total_inflows), unit: ' ' + window.AppCurrency.symbol, titleColor: 'success', valueColor: 'success', backgroundColor: 'successLight', borderColor: 'success' },
            { title: 'تدفقات خارجة', value: fmt(totals.total_outflows), unit: ' ' + window.AppCurrency.symbol, titleColor: 'danger', valueColor: 'danger', backgroundColor: 'dangerLight', borderColor: 'danger' },
            { title: 'صافي التدفق', value: fmt(totals.net_cash_flow), unit: ' ' + window.AppCurrency.symbol, titleColor: 'primary', valueColor: 'primary', backgroundColor: 'grayBg', borderColor: 'primary' },
            { title: 'الحسابات/القيود', value: totals.accounts_count + ' / ' + totals.entries_count, unit: '', titleColor: 'warning', valueColor: 'warning', backgroundColor: 'warningLight', borderColor: 'warning' }
        ],
        tableTitle: 'تجميعات التدفقات النقدية',
        tableHeaders: ['البند', 'العدد', 'داخل', 'خارج', 'الصافي'],
        columnWidths: [40, 12, 16, 16, 16],
        tableData: tableData,
        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير التدفقات النقدية الإجمالية بنجاح!',
        errorMessage: 'حدث خطأ أثناء التصدير'
    });
}

function printCashFlowSummary() {
    const fromDate = '{{ $fromDate }}';
    const toDate = '{{ $toDate }}';
    const branchName = $('select[name="branch"] option:selected').text() || 'جميع الفروع';

    printTable({
        tableSelector: '#topAccountsCard table tbody tr',
        title: 'التدفقات النقدية الإجمالية',
        infoCards: [
            { label: 'تدفقات داخلة', value: '@money($totals['total_inflows'])', class: 'text-success' },
            { label: 'تدفقات خارجة', value: '@money($totals['total_outflows'])', class: 'text-danger' },
            { label: 'صافي التدفق', value: '@money($totals['net_cash_flow'])', class: 'text-primary' },
            { label: 'رصيد ختامي للنقدية', value: '@money($cashClosing)' }
        ],
        filterInfo: {
            'الفترة': (fromDate || '-') + ' → ' + (toDate || '-'),
            'الفرع': branchName
        },
        tableHeaders: ['#', 'الكود', 'الحساب', 'داخل', 'خارج', 'صافي', 'القيود', ''],
        columnWidths: ['4%','8%','30%','12%','12%','12%','10%','12%'],
        descriptionColumn: 3,
        windowWidth: 1400, windowHeight: 900
    });
}
</script>

<style>
    @media print {
        .form-actions, #reportForm, .breadcrumb-wrapper, .alert,
        .pagination, nav, .btn { display: none !important; }
    }
</style>
@endsection
