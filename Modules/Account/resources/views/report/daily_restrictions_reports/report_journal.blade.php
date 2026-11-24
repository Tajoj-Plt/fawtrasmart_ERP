@extends('account::master')

@section('title')
    تقرير القيود المحاسبية
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
@php
    // القيم الافتراضية للمتغيرات
    $isSummary = $isSummary ?? (request('is_summary') == '1');
    $fromDate = $fromDate ?? now()->startOfYear();
    $toDate = $toDate ?? now();
    $reportData = $reportData ?? [];
    $totals = $totals ?? ['total_debit' => 0, 'total_credit' => 0, 'balance' => 0, 'count' => 0];
    $referenceTypes = $referenceTypes ?? [];
@endphp
    @include('account::components.page-head', [
        'title' => 'تقرير القيود المحاسبية',
        'crumbs' => [
            'الرئيسية' => null,
            'التقارير المحاسبية' => null,
            'تقرير القيود المحاسبية' => null,
        ],
    ])

    <div class="content-body">
        <!-- قسم الفلاتر -->
        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="card-title">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div><i class="fa fa-filter me-2"></i>بحث وفلترة</div>
                            <div>
                                <button class="btn btn-outline-success" id="exportExcel">
                                    <i class="fa fa-file-excel me-2"></i>تصدير إكسل
                                </button>
                                <button class="btn btn-outline-primary" id="printBtn">
                                    <i class="fa fa-print me-2"></i>طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.JournalEntriesByEmployee') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-3">
                                <label for="">نوع المصدر</label>
                                <select name="reference_type" id="reference_type" class="form-control select2">
                                    <option value="">جميع الأنواع</option>
                                    @foreach($referenceTypes ?? [] as $code => $type)
                                        <option value="{{ $code }}" {{ request('reference_type') == $code ? 'selected' : '' }}>
                                            {{ $type['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">الحساب</label>
                                <select name="account_id" id="account_id" class="form-control select2">
                                    <option value="">جميع الحسابات</option>
                                    @foreach($accounts ?? [] as $account)
                                        <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'date_from',
                                'toName' => 'date_to',
                                'colClass' => 'form-group col-md-3',
                                'costName' => 'cost_center',
                            ])

                            <div class="form-group col-md-3">
                                <label for="">من تاريخ</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" 
                                       value="{{ $fromDate->format('Y-m-d') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">إلى تاريخ</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" 
                                       value="{{ $toDate->format('Y-m-d') }}">
                            </div>
                        </div>

                        <!-- البحث المتقدم -->
                        <div class="collapse" id="advancedSearchForm">
                            <div class="form-body row">
                                <div class="form-group col-md-3">
                                    <label for="">الفرع</label>
                                    <select name="branch_id" id="branch_id" class="form-control select2">
                                        <option value="">جميع الفروع</option>
                                        @foreach($branches ?? [] as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="">الموظف</label>
                                    <select name="employee_id" id="employee_id" class="form-control select2">
                                        <option value="">جميع الموظفين</option>
                                        @foreach($employees ?? [] as $employee)
                                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="">العميل</label>
                                    <select name="client_id" id="client_id" class="form-control select2">
                                        <option value="">جميع العملاء</option>
                                        @foreach($clients ?? [] as $client)
                                            <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                                {{ $client->trade_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="is_summary" id="is_summary" value="{{ ($isSummary ?? false) ? '1' : '' }}">

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">
                                <i class="fa fa-search"></i> بحث
                            </button>
                            <a class="btn btn-outline-secondary ml-2 mr-2" data-bs-toggle="collapse" data-bs-target="#advancedSearchForm">
                                <i class="fa fa-sliders"></i> بحث متقدم
                            </a>
                            <button type="button" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">
                                <i class="fa fa-refresh"></i> إلغاء الفلترة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">إجمالي المدين (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_debit'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">إجمالي الدائن (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_credit'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart-2"></i></span>
                <span class="stk-metric__label">الرصيد {{ ($totals['balance'] ?? 0) >= 0 ? '(مدين)' : '(دائن)' }}</span>
                <span class="stk-metric__value">{{ number_format(abs($totals['balance'] ?? 0), 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">عدد القيود</span>
                <span class="stk-metric__value">{{ $totals['count'] ?? 0 }}</span>
            </div>
        </div>

        <!-- View Toggle Buttons -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="btn-group" role="group">
                        <a href="{{ request()->fullUrlWithQuery(['is_summary' => '']) }}" 
                           class="btn {{ !($isSummary ?? false) ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="fa fa-list"></i> التفاصيل
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['is_summary' => '1']) }}" 
                           class="btn {{ ($isSummary ?? false) ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="fa fa-bars"></i> الملخص
                        </a>
                    </div>
                    <div>
                        <span class="text-muted">
                            <i class="fa fa-calendar"></i>
                            {{ $fromDate->format('d/m/Y') }} - {{ $toDate->format('d/m/Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-content">
                <div class="card-body">
                    @if($isSummary ?? false)
                        {{-- جدول الملخص - حسب نوع المصدر --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> ملخص القيود حسب نوع المصدر</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table report reports_currency no-collapse" id="reportTable">
                                <thead>
                                    <tr class="report-results-head">
                                        <th class="first-column">الكود</th>
                                        <th class="first-column">نوع المصدر</th>
                                        <th class="first-column text-center">عدد القيود</th>
                                        <th class="first-column text-center">إجمالي المدين</th>
                                        <th class="first-column text-center">إجمالي الدائن</th>
                                        <th class="first-column text-center">الرصيد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reportData ?? [] as $code => $data)
                                        @php $balance = $data['total_debit'] - $data['total_credit']; @endphp
                                        <tr>
                                            <td>
                                                @php
                                                    $badgeClass = match($code) {
                                                        'JV' => 'bg-secondary',
                                                        'PV' => 'bg-danger',
                                                        'RV' => 'bg-success',
                                                        'PI', 'PR' => 'bg-warning',
                                                        'SI', 'SR' => 'bg-info',
                                                        'EXP' => 'bg-dark',
                                                        'SAL' => 'badge-purple',
                                                        'OPB' => 'badge-teal',
                                                        default => 'bg-secondary'
                                                    };
                                                @endphp
                                                <span class="badge {{ $badgeClass }}">{{ $code }}</span>
                                            </td>
                                            <td>{{ $data['name'] }}</td>
                                            <td class="text-center">{{ $data['entries_count'] }}</td>
                                            <td class="text-debit text-center">{{ number_format($data['total_debit'], 2) }}</td>
                                            <td class="text-credit text-center">{{ number_format($data['total_credit'], 2) }}</td>
                                            <td class="text-center {{ $balance >= 0 ? 'text-debit' : 'text-credit' }}">
                                                {{ number_format(abs($balance), 2) }}
                                                <small>({{ $balance >= 0 ? 'مدين' : 'دائن' }})</small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <div class="stk-empty">
                                                    <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                                    <h6 class="stk-empty__title">لا توجد بيانات للعرض</h6>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($reportData))
                                @php $grandBalance = ($totals['total_debit'] ?? 0) - ($totals['total_credit'] ?? 0); @endphp
                                <tfoot>
                                    <tr class="grand-total-row">
                                        <td colspan="2"><strong>الإجمالي الكلي</strong></td>
                                        <td class="text-center"><strong>{{ $totals['count'] ?? 0 }}</strong></td>
                                        <td class="text-center"><strong>@money($totals['total_debit'] ?? 0)</strong></td>
                                        <td class="text-center"><strong>@money($totals['total_credit'] ?? 0)</strong></td>
                                        <td class="text-center">
                                            <strong>@money(abs($grandBalance))</strong>
                                            <small>({{ $grandBalance >= 0 ? 'مدين' : 'دائن' }})</small>
                                        </td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @else
                        {{-- جدول التفاصيل - مجمع حسب رقم القيد --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل القيود المحاسبية</h6>
                            <div class="text-muted">
                                الفترة: {{ $fromDate->format('d/m/Y') }} إلى {{ $toDate->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">رقم القيد</th>
                                        <th class="text-center">اسم الحساب</th>
                                        <th class="text-center">كود الحساب</th>
                                        <th class="text-center">الوصف</th>
                                        <th class="text-center">المصدر</th>
                                        <th class="text-center">الفرع</th>
                                        <th class="text-center">مدين</th>
                                        <th class="text-center">دائن</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reportData ?? [] as $entryId => $entry)
                                        {{-- صف رقم القيد (Header) --}}
                                        <tr class="sub-head" style="background-color: #e9ecef;">
                                            <td colspan="9">
                                                <div class="py-1 font-weight-bold">
                                                    <i class="fa fa-file-alt mr-2"></i>
                                                    {{ $entry['reference_number'] }}
                                                    <span class="text-muted mr-3">| {{ \Carbon\Carbon::parse($entry['date'])->format('d/m/Y') }}</span>
                                                    @php
                                                        $sourceClass = match($entry['source_type']) {
                                                            'قيد يومية عام' => 'bg-jv',
                                                            'سند صرف' => 'bg-pv',
                                                            'سند قبض' => 'bg-rv',
                                                            'فاتورة مشتريات', 'مردودات مشتريات' => 'bg-pi',
                                                            'فاتورة مبيعات', 'مردودات مبيعات' => 'bg-si',
                                                            'مصروفات' => 'bg-exp',
                                                            'مسير رواتب' => 'bg-sal',
                                                            'قيد افتتاحي' => 'bg-opb',
                                                            default => 'bg-jv'
                                                        };
                                                    @endphp
                                                    @if($entry['source_url'])
                                                        <a href="{{ $entry['source_url'] }}" class="source-badge {{ $sourceClass }}" target="_blank">
                                                            {{ $entry['source_type'] }}
                                                            <i class="fa fa-external-link-alt" style="font-size: 10px; margin-right: 4px;"></i>
                                                        </a>
                                                    @else
                                                        <span class="badge {{ str_replace('bg-', 'badge-', $sourceClass) }}">{{ $entry['source_type'] }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- صفوف التفاصيل (الحسابات المتأثرة) --}}
                                        @foreach($entry['details'] as $detail)
                                            <tr class="entry-detail-row">
                                                <td class="text-center">
                                                    <div class="py-1">{{ \Carbon\Carbon::parse($entry['date'])->format('d/m/Y') }}</div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="py-1">
                                                        <a href="{{ route('journal.show', $entry['journal_entry_id']) }}" class="link-style text-primary">
                                                            {{ $entry['reference_number'] }}
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="py-1">{{ $detail['account_name'] }}</div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="py-1"><code>{{ $detail['account_code'] }}</code></div>
                                                </td>
                                                <td>
                                                    <div class="py-1">{{ Str::limit($detail['description'] ?: $entry['description'], 50) }}</div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="py-1">
                                                        @if($entry['source_url'])
                                                            <a href="{{ $entry['source_url'] }}" class="link-style">{{ $entry['source_type'] }}</a>
                                                        @else
                                                            {{ $entry['source_type'] }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="py-1">{{ $entry['branch_name'] }}</div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="py-1 {{ $detail['debit'] > 0 ? 'text-debit' : '' }}">
                                                        {{ $detail['debit'] > 0 ? number_format($detail['debit'], 2) : '0.00' }}
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="py-1 {{ $detail['credit'] > 0 ? 'text-credit' : '' }}">
                                                        {{ $detail['credit'] > 0 ? number_format($detail['credit'], 2) : '0.00' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <i class="fa fa-info-circle text-muted fa-3x mb-3 d-block"></i>
                                                <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                                                <p class="text-muted">يرجى تحديد الفلاتر والضغط على "بحث" لعرض التقرير</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(!empty($reportData))
                                @php $grandBalance = ($totals['total_debit'] ?? 0) - ($totals['total_credit'] ?? 0); @endphp
                                <tfoot>
                                    <tr class="grand-total-row">
                                        <td colspan="7"><strong><i class="fa fa-calculator me-2"></i>الإجمالي الكلي</strong></td>
                                        <td class="text-center"><strong>@money($totals['total_debit'] ?? 0)</strong></td>
                                        <td class="text-center"><strong>@money($totals['total_credit'] ?? 0)</strong></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>

                        {{-- التفاصيل صفحةً صفحة؛ الإجماليات أعلاه على الفترة والفلاتر كلِّها --}}
                        @if (($journalPage ?? null) && $journalPage->total() > 0)
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                                <div class="text-muted small">
                                    القيود {{ $journalPage->firstItem() }}–{{ $journalPage->lastItem() }} من {{ $journalPage->total() }}
                                    — الإجمالي الكلي على كل قيود الفترة، والتصدير لقيود هذه الصفحة.
                                </div>
                                <form method="GET" class="d-flex align-items-center gap-1">
                                    @foreach (request()->except(['per_page', 'page']) as $name => $value)
                                        @foreach ((array) $value as $item)
                                            <input type="hidden" name="{{ is_array($value) ? $name.'[]' : $name }}" value="{{ $item }}">
                                        @endforeach
                                    @endforeach
                                    <label for="per_page" class="small text-muted mb-0">لكل صفحة</label>
                                    <select id="per_page" name="per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                        @foreach ([50, 100, 200, 500] as $size)
                                            <option value="{{ $size }}" @selected($journalPage->perPage() === $size)>{{ $size }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                            <div class="mt-2">{{ $journalPage->links() }}</div>
                        @endif
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

            // إعادة تعيين الفلاتر
            $('#resetBtn').click(function() {
                $('#reportForm')[0].reset();
                $('.select2').val(null).trigger('change');
                $('#date_from').val('{{ now()->startOfYear()->format('Y-m-d') }}');
                $('#date_to').val('{{ now()->format('Y-m-d') }}');
                $('#reportForm').submit();
            });

            // طباعة التقرير
            $('#printBtn').click(function() {
                printJournalReport();
            });

            // تصدير إلى Excel
            $('#exportExcel').click(function() {
                exportJournalReport();
            });
        });

        // =====================================
        // تصدير Excel (يختار الطريقة المناسبة)
        // =====================================
        function exportJournalReport() {
            var isSummary = {{ ($isSummary ?? false) ? 'true' : 'false' }};
            
            if (isSummary) {
                exportJournalReportSummary();
            } else {
                exportJournalReportDetails();
            }
        }

        // =====================================
        // تصدير Excel - الملخص
        // =====================================
        function exportJournalReportSummary() {
            var totals = {
                total_debit: {{ $totals['total_debit'] ?? 0 }},
                total_credit: {{ $totals['total_credit'] ?? 0 }},
                balance: {{ $totals['balance'] ?? 0 }},
                count: {{ $totals['count'] ?? 0 }}
            };

            var fromDate = '{{ $fromDate->format("Y-m-d") }}';
            var toDate = '{{ $toDate->format("Y-m-d") }}';

            // جمع بيانات الملخص
            var tableData = [];
            
            @if(!empty($reportData))
            @foreach($reportData as $refType => $data)
                tableData.push({
                    values: [
                        '{{ $data["reference_name"] ?? $refType }}',
                        '{{ $data["count"] ?? 0 }}',
                        formatNumber({{ $data['total_debit'] ?? 0 }}),
                        formatNumber({{ $data['total_credit'] ?? 0 }}),
                        formatNumber({{ ($data['total_debit'] ?? 0) - ($data['total_credit'] ?? 0) }})
                    ],
                    cellStyles: [
                        { bold: true },
                        null,
                        { color: 'success' },
                        { color: 'danger' },
                        { color: ({{ ($data['total_debit'] ?? 0) - ($data['total_credit'] ?? 0) }}) >= 0 ? 'success' : 'danger' }
                    ]
                });
            @endforeach
            @endif

            // إعداد التقرير
            var config = {
                fileName: 'تقرير_القيود_المحاسبية_ملخص',
                mainTitle: 'تقرير القيود المحاسبية - الملخص',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'الفترة': 'من ' + fromDate + ' إلى ' + toDate,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'إجمالي المدين',
                        value: formatNumber(totals.total_debit),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'إجمالي الدائن',
                        value: formatNumber(totals.total_credit),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'الرصيد',
                        value: formatNumber(Math.abs(totals.balance)),
                        unit: ' ' + window.AppCurrency.symbol + ' ' + (totals.balance >= 0 ? '(مدين)' : '(دائن)'),
                        titleColor: 'warning',
                        valueColor: totals.balance >= 0 ? 'success' : 'danger',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: 'عدد القيود',
                        value: totals.count.toString(),
                        unit: ' قيد',
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayLight',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'ملخص القيود حسب نوع المصدر',
                tableHeaders: ['نوع المصدر', 'عدد القيود', 'إجمالي المدين', 'إجمالي الدائن', 'الرصيد'],
                columnWidths: [30, 15, 20, 20, 20],
                
                tableData: tableData,

                summaryRows: [
                    {
                        label: 'الإجمالي',
                        values: [
                            'الإجمالي',
                            totals.count.toString(),
                            formatNumber(totals.total_debit),
                            formatNumber(totals.total_credit),
                            formatNumber(Math.abs(totals.balance))
                        ],
                        color: 'primary',
                        cellStyles: [
                            { bold: true },
                            { bold: true },
                            { color: 'success', bold: true },
                            { color: 'danger', bold: true },
                            { bold: true }
                        ]
                    }
                ],

                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'ملخص تقرير القيود المحاسبية',
                    tables: [
                        {
                            title: 'إحصائيات القيود',
                            data: [
                                { label: 'إجمالي المدين', value: formatNumber(totals.total_debit), unit: window.AppCurrency.symbol },
                                { label: 'إجمالي الدائن', value: formatNumber(totals.total_credit), unit: window.AppCurrency.symbol },
                                { label: 'الرصيد', value: formatNumber(Math.abs(totals.balance)), unit: window.AppCurrency.symbol, highlight: true },
                                { label: 'عدد القيود', value: totals.count.toString(), unit: 'قيد' }
                            ]
                        }
                    ]
                },

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير تقرير القيود بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

        // =====================================
        // تصدير Excel - التفاصيل
        // =====================================
        function exportJournalReportDetails() {
            var totals = {
                total_debit: {{ $totals['total_debit'] ?? 0 }},
                total_credit: {{ $totals['total_credit'] ?? 0 }},
                balance: {{ $totals['balance'] ?? 0 }},
                count: {{ $totals['count'] ?? 0 }}
            };

            var fromDate = '{{ $fromDate->format("Y-m-d") }}';
            var toDate = '{{ $toDate->format("Y-m-d") }}';

            // جمع بيانات التفاصيل من الجدول
            var tableData = [];
            
            $('#reportTable tbody tr').each(function() {
                var cells = $(this).find('td');
                if (cells.length >= 7) {
                    var isHeader = $(this).hasClass('table-secondary') || $(this).hasClass('table-warning');
                    
                    tableData.push({
                        values: [
                            $(cells[0]).text().trim(),
                            $(cells[1]).text().trim(),
                            $(cells[2]).text().trim(),
                            $(cells[3]).text().trim(),
                            $(cells[4]).text().trim(),
                            $(cells[5]).text().trim(),
                            $(cells[6]).text().trim()
                        ],
                        cellStyles: isHeader ? [
                            { bold: true },
                            { bold: true },
                            { bold: true },
                            { bold: true },
                            { bold: true, color: 'success' },
                            { bold: true, color: 'danger' },
                            { bold: true }
                        ] : [
                            null,
                            null,
                            null,
                            null,
                            { color: 'success' },
                            { color: 'danger' },
                            null
                        ]
                    });
                }
            });

            var config = {
                fileName: 'تقرير_القيود_المحاسبية_تفاصيل',
                mainTitle: 'تقرير القيود المحاسبية - التفاصيل',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'الفترة': 'من ' + fromDate + ' إلى ' + toDate,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'إجمالي المدين',
                        value: formatNumber(totals.total_debit),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'إجمالي الدائن',
                        value: formatNumber(totals.total_credit),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'الرصيد',
                        value: formatNumber(Math.abs(totals.balance)),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'warning',
                        valueColor: totals.balance >= 0 ? 'success' : 'danger',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: 'عدد القيود',
                        value: totals.count.toString(),
                        unit: ' قيد',
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayLight',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'تفاصيل القيود المحاسبية',
                tableHeaders: ['رقم القيد', 'التاريخ', 'نوع المصدر', 'الوصف', 'مدين', 'دائن', 'المستخدم'],
                columnWidths: [12, 12, 15, 35, 12, 12, 15],
                
                tableData: tableData,

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير تقرير القيود بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

        // =====================================
        // طباعة التقرير (يختار الطريقة المناسبة)
        // =====================================
        function printJournalReport() {
            var isSummary = {{ ($isSummary ?? false) ? 'true' : 'false' }};
            
            if (isSummary) {
                printJournalReportSummary();
            } else {
                printJournalReportDetails();
            }
        }

        // =====================================
        // طباعة التقرير - الملخص
        // =====================================
        function printJournalReportSummary() {
            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير القيود المحاسبية - الملخص',
                infoCards: [
                    { label: 'إجمالي المدين', value: '@money($totals["total_debit"] ?? 0)', class: 'text-success' },
                    { label: 'إجمالي الدائن', value: '@money($totals["total_credit"] ?? 0)', class: 'text-danger' },
                    { label: 'الرصيد', value: '@money(abs($totals["balance"] ?? 0)) {{ ($totals["balance"] ?? 0) >= 0 ? "(مدين)" : "(دائن)" }}' },
                    { label: 'عدد القيود', value: '{{ $totals["count"] ?? 0 }} قيد' }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['نوع المصدر', 'عدد القيود', 'إجمالي المدين', 'إجمالي الدائن', 'الرصيد'],
                columnWidths: ['30%', '15%', '18%', '18%', '19%'],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

        // =====================================
        // طباعة التقرير - التفاصيل
        // =====================================
        function printJournalReportDetails() {
            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: null,
                title: 'تقرير القيود المحاسبية - التفاصيل',
                infoCards: [
                    { label: 'إجمالي المدين', value: '@money($totals["total_debit"] ?? 0)', class: 'text-success' },
                    { label: 'إجمالي الدائن', value: '@money($totals["total_credit"] ?? 0)', class: 'text-danger' },
                    { label: 'الرصيد', value: '@money(abs($totals["balance"] ?? 0))' },
                    { label: 'عدد القيود', value: '{{ $totals["count"] ?? 0 }} قيد' }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['رقم القيد', 'التاريخ', 'نوع المصدر', 'الوصف', 'مدين', 'دائن', 'المستخدم'],
                columnWidths: ['10%', '10%', '12%', '33%', '12%', '12%', '11%'],
                descriptionColumn: 4,
                windowWidth: 1400,
                windowHeight: 900
            });
        }
    </script>
@endsection
