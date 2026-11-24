@extends('account::master')

@section('title')
    التدفقات النقدية التفصيلية
@stop

@section('content')
@include('account::components.page-head', [
    'title' => 'التدفقات النقدية التفصيلية',
    'crumbs' => [
        'التقارير المحاسبية' => route('GeneralAccountReports.index'),
        'التدفقات النقدية التفصيلية' => null,
    ],
])

<div class="content-body">
    {{-- ===== Filters ===== --}}
    @php $f = $currentFilters ?? []; @endphp
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="card-title mb-0">بحث وفلترة متقدمة</h4>
                <div>
                    <button class="btn btn-sm btn-outline-success" id="exportExcel"><i class="fa fa-file-excel"></i> تصدير Excel</button>
                    <button class="btn btn-sm btn-outline-primary" id="printBtn"><i class="fa fa-print"></i> طباعة</button>
                    <a href="{{ route('GeneralAccountReports.cashFlowStatement', request()->only(['from_date','to_date','branch','currency','counter_account_id'])) }}" class="btn btn-sm btn-outline-info">
                        <i class="feather icon-bar-chart-2"></i> عرض الإجمالية
                    </a>
                </div>
            </div>

            <form id="reportForm" method="GET" action="{{ route('GeneralAccountReports.detailedCashFlow') }}" class="form">
                <div class="form-body row">
                    {{-- التاريخ --}}
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
                        @php $dt = $dateType ?? 'custom'; @endphp
                        <select name="date_type" class="form-control">
                            <option value="custom"       {{ $dt=='custom' ? 'selected' : '' }}>مخصص</option>
                            <option value="today"        {{ $dt=='today' ? 'selected' : '' }}>اليوم</option>
                            <option value="yesterday"    {{ $dt=='yesterday' ? 'selected' : '' }}>أمس</option>
                            <option value="this_week"    {{ $dt=='this_week' ? 'selected' : '' }}>هذا الأسبوع</option>
                            <option value="last_week"    {{ $dt=='last_week' ? 'selected' : '' }}>الأسبوع الماضي</option>
                            <option value="this_month"   {{ $dt=='this_month' ? 'selected' : '' }}>هذا الشهر</option>
                            <option value="last_month"   {{ $dt=='last_month' ? 'selected' : '' }}>الشهر الماضي</option>
                            <option value="this_quarter" {{ $dt=='this_quarter' ? 'selected' : '' }}>هذا الربع</option>
                            <option value="last_quarter" {{ $dt=='last_quarter' ? 'selected' : '' }}>الربع الماضي</option>
                            <option value="this_year"    {{ $dt=='this_year' ? 'selected' : '' }}>هذا العام</option>
                            <option value="last_year"    {{ $dt=='last_year' ? 'selected' : '' }}>العام الماضي</option>
                        </select>
                    </div>

                    {{-- الفرع --}}
                    <div class="form-group col-md-3">
                        <label>الفرع</label>
                        <select name="branch" class="form-control">
                            <option value="">جميع الفروع</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ ($f['branch'] ?? '') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- البنك / الخزينة --}}
                    <div class="form-group col-md-3">
                        <label>بنك / خزينة محددة</label>
                        <select name="counter_account_id" class="form-control">
                            <option value="">الكل</option>
                            @foreach($cashAccountsList as $acc)
                                <option value="{{ $acc->id }}" {{ ($f['counter_account_id'] ?? '') == $acc->id ? 'selected' : '' }}>
                                    {{ $acc->code }} - {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- نوع الحركة --}}
                    <div class="form-group col-md-3">
                        <label>نوع الحركة</label>
                        @php $mt = $f['movement_type'] ?? 'all'; @endphp
                        <select name="movement_type" class="form-control">
                            <option value="all" {{ $mt=='all' ? 'selected' : '' }}>الكل</option>
                            <option value="in"  {{ $mt=='in' ? 'selected' : '' }}>تدفقات داخلة فقط</option>
                            <option value="out" {{ $mt=='out' ? 'selected' : '' }}>تدفقات خارجة فقط</option>
                        </select>
                    </div>

                    {{-- نوع الحساب --}}
                    <div class="form-group col-md-3">
                        <label>نوع الحساب</label>
                        <select name="account_type" class="form-control">
                            <option value="">الكل</option>
                            @foreach($accountTypes as $t)
                                <option value="{{ $t }}" {{ ($f['account_type'] ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- العملة --}}
                    <div class="form-group col-md-3">
                        @include('account::components.currency-select', [
                            'name' => 'currency',
                            'label' => 'العملة',
                            'placeholder' => 'كل العملات',
                            'selected' => $f['currency'] ?? \App\Support\CurrencySettings::code(),
                        ])
                    </div>

                    {{-- بحث في الحساب --}}
                    <div class="form-group col-md-4">
                        <label>بحث في الحساب (اسم/كود)</label>
                        <input type="text" name="account_search" class="form-control" placeholder="اسم أو كود الحساب..." value="{{ $f['account_search'] ?? '' }}">
                    </div>

                    {{-- رقم القيد --}}
                    <div class="form-group col-md-3">
                        <label>رقم القيد</label>
                        <input type="text" name="reference_number" class="form-control" placeholder="..." value="{{ $f['reference_number'] ?? '' }}">
                    </div>

                    {{-- نطاق المبلغ --}}
                    <div class="form-group col-md-3">
                        <label>أدنى مبلغ (لإجمالي حركة الحساب)</label>
                        <input type="number" step="0.01" min="0" name="min_amount" class="form-control" value="{{ $f['min_amount'] ?? '' }}">
                    </div>
                    <div class="form-group col-md-2">
                        <label>أعلى مبلغ</label>
                        <input type="number" step="0.01" min="0" name="max_amount" class="form-control" value="{{ $f['max_amount'] ?? '' }}">
                    </div>

                    {{-- الترتيب --}}
                    <div class="form-group col-md-3">
                        <label>الترتيب حسب</label>
                        @php $sb = $f['sort_by'] ?? 'activity'; @endphp
                        <select name="sort_by" class="form-control">
                            <option value="activity" {{ $sb=='activity' ? 'selected' : '' }}>حجم النشاط</option>
                            <option value="code"     {{ $sb=='code' ? 'selected' : '' }}>كود الحساب</option>
                            <option value="name"     {{ $sb=='name' ? 'selected' : '' }}>اسم الحساب</option>
                            <option value="inflow"   {{ $sb=='inflow' ? 'selected' : '' }}>التدفقات الداخلة</option>
                            <option value="outflow"  {{ $sb=='outflow' ? 'selected' : '' }}>التدفقات الخارجة</option>
                            <option value="closing"  {{ $sb=='closing' ? 'selected' : '' }}>الرصيد الختامي</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>الاتجاه</label>
                        @php $sd = $f['sort_dir'] ?? 'desc'; @endphp
                        <select name="sort_dir" class="form-control">
                            <option value="desc" {{ $sd=='desc' ? 'selected' : '' }}>تنازلي</option>
                            <option value="asc"  {{ $sd=='asc' ? 'selected' : '' }}>تصاعدي</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>عدد الصفوف/صفحة</label>
                        <select name="per_page" class="form-control">
                            @foreach([10,25,50,100,200] as $pp)
                                <option value="{{ $pp }}" {{ ($perPage ?? 25) == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary"><i class="fa fa-search"></i> بحث</button>
                    <a href="{{ route('GeneralAccountReports.detailedCashFlow') }}" class="btn btn-outline-danger"><i class="fa fa-times"></i> إلغاء الفلترة</a>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Summary cards ===== --}}
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
            <span class="stk-metric__label">صافي التدفق</span>
            <span class="stk-metric__value {{ $totals['net_cash_flow'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totals['net_cash_flow'], 2) }}</span>
        </div>
        <div class="stk-metric">
            <span class="stk-metric__icon"><i class="feather icon-alert-circle"></i></span>
            <span class="stk-metric__label">الحسابات / القيود</span>
            <span class="stk-metric__value">{{ $totals['accounts_count'] }} / {{ $totals['entries_count'] }}</span>
        </div>
    </div>

    {{-- ===== Main table ===== --}}
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="card-title mb-0"><i class="feather icon-list"></i> الحسابات التي حدثت لها حركة نقدية</h4>
                <small class="text-muted">الفترة: {{ $fromDate }} → {{ $toDate }}</small>
            </div>

            @if($rows->isEmpty())
                <div class="alert alert-info text-center">
                    <i class="feather icon-info"></i>
                    لا توجد حسابات حدثت لها حركة نقدية مع البنوك أو الخزائن خلال الفترة وبهذه الفلاتر.
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover" id="cashFlowTable">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="width:90px;">الكود</th>
                            <th>اسم الحساب</th>
                            <th>النوع</th>
                            <th class="text-left">الرصيد الافتتاحي</th>
                            <th class="text-left text-success">تدفق داخل</th>
                            <th class="text-left text-danger">تدفق خارج</th>
                            <th class="text-left">صافي التغير</th>
                            <th class="text-left">الرصيد الختامي</th>
                            <th class="text-center" style="width:90px;">القيود</th>
                            <th class="text-center" style="width:110px;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="detailedCashFlowTableBody">
                        @foreach($rows as $i => $r)
                            <tr>
                                <td>{{ ($page - 1) * $perPage + $i + 1 }}</td>
                                <td><span class="badge bg-light text-dark">{{ $r['account_code'] ?? '-' }}</span></td>
                                <td><strong>{{ $r['account_name'] }}</strong></td>
                                <td><small class="text-muted">{{ $r['account_type'] ?? '-' }}</small></td>
                                <td class="text-left">{{ number_format($r['opening_balance'], 2) }}</td>
                                <td class="text-left text-success fw-bold">{{ number_format($r['cash_inflow'], 2) }}</td>
                                <td class="text-left text-danger fw-bold">{{ number_format($r['cash_outflow'], 2) }}</td>
                                <td class="text-left {{ $r['net_change'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($r['net_change'], 2) }}</td>
                                <td class="text-left fw-bold {{ $r['closing_balance'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($r['closing_balance'], 2) }}</td>
                                <td class="text-center"><span class="badge badge-pill bg-info">{{ $r['entries_count'] }}</span></td>
                                <td class="text-center">
                                    <a href="{{ route('GeneralAccountReports.detailedCashFlowAccount', ['accountId' => $r['account_id']]) }}?from_date={{ $fromDate }}&to_date={{ $toDate }}&branch={{ $f['branch'] ?? '' }}"
                                       class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                        <i class="feather icon-eye"></i> تفاصيل
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8f9fa; font-weight:bold;">
                            <td colspan="5" class="text-left">الإجمالي (الكل في الصفحات)</td>
                            <td class="text-left text-success">{{ number_format($totals['total_inflows'], 2) }}</td>
                            <td class="text-left text-danger">{{ number_format($totals['total_outflows'], 2) }}</td>
                            <td class="text-left {{ $totals['net_cash_flow'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($totals['net_cash_flow'], 2) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Pagination --}}
            @include('stock::components.page-links', [
                    'page' => $page,
                    'totalPages' => $totalPages,
                    'total' => $totals['accounts_count'],
                    'unit' => 'حساباً',
                ])
            @endif
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
    $('#exportExcel').on('click', function(e){ e.preventDefault(); exportDetailedCashFlow(); });
    $('#printBtn').on('click', function(e){ e.preventDefault(); printDetailedCashFlow(); });
});
function fmt(n){ return parseFloat(n||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function exportDetailedCashFlow() {
    const rows = @json($rowsAll ?? $rows);
    const totals = @json($totals);
    const fromDate = '{{ $fromDate }}';
    const toDate = '{{ $toDate }}';
    const branchName = $('select[name="branch"] option:selected').text() || 'جميع الفروع';

    const tableData = rows.map((r, i) => ({
        values: [
            i + 1, r.account_code || '-', r.account_name, r.account_type || '-',
            fmt(r.opening_balance), fmt(r.cash_inflow), fmt(r.cash_outflow),
            fmt(r.net_change), fmt(r.closing_balance), r.entries_count
        ],
        alignments: ['center','center','right','center','center','center','center','center','center','center'],
        cellStyles: [null, null, null, null, null,
            { color: 'success', bold: true }, { color: 'danger', bold: true },
            null, { color: 'primary', bold: true }, null]
    }));

    exportToExcelProfessional({
        fileName: 'التدفقات_النقدية_التفصيلية',
        mainTitle: 'التدفقات النقدية التفصيلية',
        subTitle: 'نظام المحاسبة المتكامل',
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
        tableTitle: 'تفاصيل الحسابات',
        tableHeaders: ['#', 'الكود', 'اسم الحساب', 'النوع', 'افتتاحي', 'داخل', 'خارج', 'الصافي', 'ختامي', 'القيود'],
        columnWidths: [6, 12, 28, 14, 14, 14, 14, 14, 14, 10],
        tableData: tableData,
        summaryRows: [{
            values: ['', '', '', 'الإجمالي', '', fmt(totals.total_inflows), fmt(totals.total_outflows), fmt(totals.net_cash_flow), '', ''],
            cellStyles: [null, null, null, { bold: true }, null, { bold: true, color: 'success' }, { bold: true, color: 'danger' }, { bold: true, color: 'primary' }, null, null]
        }],
        buttonSelector: '#exportExcel',
        successMessage: 'تم تصدير التدفقات النقدية التفصيلية بنجاح!',
        errorMessage: 'حدث خطأ أثناء التصدير'
    });
}

function printDetailedCashFlow() {
    const fromDate = '{{ $fromDate }}';
    const toDate = '{{ $toDate }}';
    const branchName = $('select[name="branch"] option:selected').text() || 'جميع الفروع';

    printTable({
        tableSelector: '#detailedCashFlowTableBody tr',
        title: 'التدفقات النقدية التفصيلية',
        infoCards: [
            { label: 'تدفقات داخلة', value: '@money($totals['total_inflows'])', class: 'text-success' },
            { label: 'تدفقات خارجة', value: '@money($totals['total_outflows'])', class: 'text-danger' },
            { label: 'صافي التدفق', value: '@money($totals['net_cash_flow'])', class: 'text-primary' },
            { label: 'الحسابات', value: '{{ $totals['accounts_count'] }}' }
        ],
        filterInfo: {
            'الفترة': (fromDate || '-') + ' → ' + (toDate || '-'),
            'الفرع': branchName
        },
        tableHeaders: ['#', 'الكود', 'اسم الحساب', 'النوع', 'افتتاحي', 'داخل', 'خارج', 'صافي', 'ختامي', 'القيود', ''],
        columnWidths: ['4%','7%','22%','10%','10%','10%','10%','10%','10%','5%','2%'],
        descriptionColumn: 3,
        windowWidth: 1400, windowHeight: 900
    });
}
</script>

<style>
    @media print {
        .form-actions, #reportForm, .breadcrumb-wrapper, .alert,
        .pagination, nav { display: none !important; }
    }
</style>
@endsection
