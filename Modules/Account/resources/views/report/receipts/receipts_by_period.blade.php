@extends('account::master')

@section('title')
    سندات القبض حسب الفترة الزمنية
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">

@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'سندات القبض حسب الفترة الزمنية',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'التقارير' => route('GeneralAccountReports.index'),
            'سندات القبض حسب الفترة' => null,
        ],
    ])

    <div class="content-body">
        <!-- قسم الفلاتر -->
        <div class="card stk-filters no-print">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> خيارات البحث</h6>
            </div>
            <div>
                <div class="card-body">
                    <form method="GET" action="{{ route('GeneralAccountReports.receiptsByPeriod') }}" id="filterForm">
                        <!-- Hidden field for period type -->
                        <input type="hidden" name="period_type" id="periodTypeInput" value="{{ $periodType }}">
            
                    <div class="row">
                        <div class="col-md-12">
                            <div class="period-tabs">
                                <a href="{{ route('GeneralAccountReports.receiptsByPeriod', ['period_type' => 'daily']) }}" 
                                   class="btn {{ $periodType == 'daily' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-day"></i> يومي
                                </a>
                                <a href="{{ route('GeneralAccountReports.receiptsByPeriod', ['period_type' => 'weekly']) }}" 
                                   class="btn {{ $periodType == 'weekly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-week"></i> أسبوعي
                                </a>
                                <a href="{{ route('GeneralAccountReports.receiptsByPeriod', ['period_type' => 'monthly']) }}" 
                                   class="btn {{ $periodType == 'monthly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-alt"></i> شهري
                                </a>
                                <a href="{{ route('GeneralAccountReports.receiptsByPeriod', ['period_type' => 'yearly']) }}" 
                                   class="btn {{ $periodType == 'yearly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar"></i> سنوي
                                </a>
                            </div>
                        </div>
                    </div>
                        
                        <div class="row mt-2">
                            <!-- Date From -->
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'date_from',
                                'toName' => 'date_to',
                                'colClass' => 'col-md-3 col-sm-6 mb-1',
                                'costName' => 'cost_center',
                            ])

                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>من تاريخ</label>
                                <input type="date" name="date_from" class="form-control" value="{{ $fromDate->format('Y-m-d') }}">
                            </div>

                            <!-- Date To -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>إلى تاريخ</label>
                                <input type="date" name="date_to" class="form-control" value="{{ $toDate->format('Y-m-d') }}">
                            </div>

                            <!-- Treasury Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الخزينة</label>
                                <select name="treasury_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($treasuries as $treasury)
                                        <option value="{{ $treasury->id }}" {{ request('treasury_id') == $treasury->id ? 'selected' : '' }}>
                                            {{ $treasury->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Category Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>تصنيف السند</label>
                                <select name="category_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Branch Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الفرع</label>
                                <select name="branch_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Client Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>العميل</label>
                                <select name="client_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name ?? $client->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Employee Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الموظف</label>
                                <select name="employee_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- View Type -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>نوع العرض</label>
                                <div class="btn-group d-block" role="group">
                                    <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                                        <i class="fa fa-list"></i> الملخص
                                    </button>
                                    <button type="submit" name="view_type" value="details" class="btn {{ !$isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                                        <i class="fa fa-th-list"></i> التفاصيل
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <!-- Search Button -->
                            <div class="col-md-6 col-sm-6 mb-1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-search"></i> بحث
                                </button>
                                <a href="{{ route('GeneralAccountReports.receiptsByPeriod') }}" class="btn btn-secondary">
                                    <i class="fa fa-refresh"></i> إعادة تعيين
                                </a>
                            </div>

                            <!-- Export Buttons -->
                            <div class="col-md-6 col-sm-6 mb-1 text-right">
                                <button type="button" class="btn btn-success" id="printBtn" onclick="printReceiptsByPeriodReport()">
                                    <i class="fa fa-print"></i> طباعة
                                </button>
                                <button type="button" class="btn btn-info" id="exportExcel" onclick="exportToExcel()">
                                    <i class="fa fa-file-excel-o"></i> Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">عدد سندات القبض</span>
                <span class="stk-metric__value">{{ $totals['count'] ?? 0 }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">إجمالي المبلغ (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['amount'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">إجمالي الضريبة (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['tax'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">الإجمالي شامل الضريبة (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_with_tax'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-primary mr-1">{{ $periodTypes[$periodType] }}</span>
                        <span class="text-muted">الفترة: {{ $fromDate->format('d/m/Y') }} إلى {{ $toDate->format('d/m/Y') }}</span>
                    </div>
                    <small class="text-muted">آخر تحديث: {{ now()->format('Y-m-d H:i') }}</small>
                </div>
            </div>
        </div>

        <!-- Chart -->
        @if(count($reportData) > 0)
        <div class="card mt-2">
            <div class="card-header">
                <h6 class="stk-table-head__title"><i class="fa fa-chart-bar"></i> الرسم البياني لسندات القبض ({{ $periodTypes[$periodType] }})</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="receiptsChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge bg-light text-dark-success mr-2">
                        <i class="fa fa-circle"></i> المبلغ
                    </span>
                    <span class="badge bg-light text-dark-warning mr-2">
                        <i class="fa fa-circle"></i> الضريبة
                    </span>
                    <span class="badge bg-light text-dark-primary">
                        <i class="fa fa-circle"></i> الإجمالي
                    </span>
                </div>
            </div>
        </div>
        @endif

        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-header">
                <h6 class="stk-table-head__title"><i class="fa fa-table"></i> {{ $isSummary ? 'ملخص سندات القبض' : 'تفاصيل سندات القبض' }} حسب {{ $periodTypes[$periodType] }}</h6>
            </div>
            <div class="card-content">
                <div class="card-body">
                    @if($isSummary)
                        {{-- جدول الملخص --}}
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">الفترة</th>
                                        <th class="text-center">عدد السندات</th>
                                        <th class="text-center">إجمالي المبلغ</th>
                                        <th class="text-center">إجمالي الضريبة</th>
                                        <th class="text-center">الإجمالي شامل الضريبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rowNum = 0; @endphp
                                    @forelse($reportData as $periodKey => $data)
                                        @php $rowNum++; @endphp
                                        <tr>
                                            <td>{{ $rowNum }}</td>
                                            <td class="font-weight-bold">{{ $data['name'] }}</td>
                                            <td>{{ $data['count'] }}</td>
                                            <td class="text-success">{{ number_format($data['amount'], 2) }}</td>
                                            <td class="text-warning">{{ number_format($data['tax'], 2) }}</td>
                                            <td class="text-primary font-weight-bold">{{ number_format($data['total_with_tax'], 2) }}</td>
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
                                @if(count($reportData) > 0)
                                <tfoot class="table-warning">
                                    <tr>
                                        <td colspan="2" class="font-weight-bold">الإجمالي العام</td>
                                        <td class="font-weight-bold">{{ $totals['count'] }}</td>
                                        <td class="text-success font-weight-bold">@money($totals['amount'])</td>
                                        <td class="text-warning font-weight-bold">@money($totals['tax'])</td>
                                        <td class="text-primary font-weight-bold">@money($totals['total_with_tax'])</td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @else
                        {{-- جدول التفاصيل --}}
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">الكود</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">الخزينة</th>
                                        <th class="text-center">التصنيف</th>
                                        <th class="text-center">العميل</th>
                                        <th class="text-center">الموظف</th>
                                        <th class="text-center">البيان</th>
                                        <th class="text-center">الفرع</th>
                                        <th class="text-center">المبلغ</th>
                                        <th class="text-center">الضريبة</th>
                                        <th class="text-center">الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $globalRowNum = 0; @endphp
                                    @forelse($reportData as $periodKey => $data)
                                        <!-- رأس الفترة -->
                                        <tr class="table-secondary">
                                            <td colspan="12">
                                                <strong>
                                                    <i class="fa fa-calendar"></i>
                                                    {{ $data['name'] }}
                                                    <span class="badge bg-info mr-2">{{ $data['count'] }} سند</span>
                                                    <span class="badge bg-success">@money($data['total_with_tax'])</span>
                                                </strong>
                                            </td>
                                        </tr>
                                        
                                        @foreach($data['items'] as $receipt)
                                            @php $globalRowNum++; @endphp
                                            <tr>
                                                <td>{{ $globalRowNum }}</td>
                                                <td>
                                                    @if($receipt->journal_entry_id)
                                                        <a href="{{ route('journal.show', $receipt->journal_entry_id) }}" class="text-primary">
                                                            {{ $receipt->code ?? $receipt->id }}
                                                        </a>
                                                    @else
                                                        {{ $receipt->code ?? $receipt->id }}
                                                    @endif
                                                </td>
                                                <td>{{ $receipt->date ? \Carbon\Carbon::parse($receipt->date)->format('d/m/Y') : '-' }}</td>
                                                <td>{{ $receipt->treasury->name ?? '-' }}</td>
                                                <td>{{ $receipt->incomes_category->name ?? '-' }}</td>
                                                <td>{{ $receipt->account->client->trade_name ?? $receipt->account->client->first_name ?? '-' }}</td>
                                                <td>{{ $receipt->user->name ?? '-' }}</td>
                                                <td>{{ Str::limit($receipt->description ?? '-', 25) }}</td>
                                                <td>{{ $receipt->branch->name ?? '-' }}</td>
                                                <td class="text-success">{{ number_format($receipt->amount ?? 0, 2) }}</td>
                                                <td class="text-warning">{{ number_format($receipt->tax_amount ?? 0, 2) }}</td>
                                                <td class="text-primary font-weight-bold">{{ number_format(($receipt->amount ?? 0) + ($receipt->tax_amount ?? 0), 2) }}</td>
                                            </tr>
                                        @endforeach
                                        
                                        <!-- صف المجموع للفترة -->
                                        <tr class="table-light">
                                            <td colspan="9" class="text-left"><strong>إجمالي {{ $data['name'] }}</strong></td>
                                            <td class="text-success"><strong>{{ number_format($data['amount'], 2) }}</strong></td>
                                            <td class="text-warning"><strong>{{ number_format($data['tax'], 2) }}</strong></td>
                                            <td class="text-primary"><strong>{{ number_format($data['total_with_tax'], 2) }}</strong></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="12" class="text-center py-5">
                                                <i class="fa fa-info-circle text-muted fa-3x mb-3 d-block"></i>
                                                <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                                                <p class="text-muted">يرجى تحديد معايير البحث والضغط على "بحث" لعرض التقرير</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(count($reportData) > 0)
                                <tfoot class="table-warning">
                                    <tr>
                                        <td colspan="9" class="text-left"><strong>الإجمالي العام</strong></td>
                                        <td class="text-success"><strong>@money($totals['amount'])</strong></td>
                                        <td class="text-warning"><strong>@money($totals['tax'])</strong></td>
                                        <td class="text-primary"><strong>@money($totals['total_with_tax'])</strong></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        let receiptsChart;

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

            // تهيئة الرسم البياني
            initChart();
        });

        function initChart() {
            const ctx = document.getElementById('receiptsChart');
            if (!ctx) return;

            if (receiptsChart) receiptsChart.destroy();

            const chartData = @json($chartData);

            receiptsChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'المبلغ',
                            data: chartData.amounts,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1,
                            order: 2
                        },
                        {
                            label: 'الضريبة',
                            data: chartData.taxes,
                            backgroundColor: 'rgba(255, 193, 7, 0.7)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1,
                            order: 3
                        },
                        {
                            label: 'الإجمالي',
                            data: chartData.totals,
                            type: 'line',
                            fill: false,
                            borderColor: 'rgba(0, 123, 255, 1)',
                            backgroundColor: 'rgba(0, 123, 255, 0.2)',
                            borderWidth: 3,
                            tension: 0.3,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 12 },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatNumber(context.raw) + ' ' + window.AppCurrency.symbol;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11 },
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatNumber(value) + ' ' + window.AppCurrency.symbol;
                                }
                            }
                        }
                    }
                }
            });
        }

        function formatNumber(n, decimals = 2) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

        // =====================================
        // تصدير Excel - يدعم الملخص والتفاصيل
        // =====================================
        function exportToExcel() {
            const isSummary = {{ $isSummary ? 'true' : 'false' }};
            
            if (isSummary) {
                exportReceiptsByPeriodSummary();
            } else {
                exportReceiptsByPeriodDetails();
            }
        }

        function exportReceiptsByPeriodSummary() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            // قراءة البيانات مباشرة من Blade
            var tableData = [];
            var rowNumber = 1;
            @if(!empty($reportData))
            @foreach($reportData as $key => $data)
            tableData.push({
                values: [
                    rowNumber++,
                    '{{ $data["name"] }}',
                    {{ $data['count'] }},
                    formatNumber({{ $data['amount'] }}),
                    formatNumber({{ $data['tax'] }}),
                    formatNumber({{ $data['total_with_tax'] }})
                ],
                cellStyles: [
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }
                ]
            });
            @endforeach
            @endif

            const config = {
                fileName: 'تقرير_سندات_القبض_حسب_الفترة_ملخص',
                mainTitle: 'تقرير سندات القبض حسب الفترة الزمنية - ملخص',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    { title: 'عدد السندات', value: formatNumber(totals.count, 0), unit: ' سند', color: 'success', borderColor: 'success' },
                    { title: 'إجمالي المبلغ', value: formatNumber(totals.amount), unit: ' ' + window.AppCurrency.symbol, color: 'success', borderColor: 'success' },
                    { title: 'إجمالي الضريبة', value: formatNumber(totals.tax), unit: ' ' + window.AppCurrency.symbol, color: 'warning', borderColor: 'warning' },
                    { title: 'الإجمالي شامل الضريبة', value: formatNumber(totals.total_with_tax), unit: ' ' + window.AppCurrency.symbol, color: 'primary', borderColor: 'primary' }
                ],

                tableTitle: 'ملخص سندات القبض حسب {{ $periodTypes[$periodType] }}',
                tableHeaders: ['#', 'الفترة', 'العدد', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: [6, 40, 12, 15, 15, 15],
                tableData: tableData,
                summaryRows: [{
                    label: 'الإجمالي',
                    values: ['الإجمالي', '', totals.count, formatNumber(totals.amount), formatNumber(totals.tax), formatNumber(totals.total_with_tax)],
                    mergeFrom: 1, mergeTo: 2, color: 'primary', alignment: 'center'
                }],
                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!'
            };

            exportToExcelProfessional(config);
        }

        function exportReceiptsByPeriodDetails() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            // قراءة البيانات من DOM مع تخطي صفوف العناوين والمجاميع
            var tableData = [];
            var rowNumber = 1;
            
            $('#reportTable tbody tr').each(function() {
                var cells = $(this).find('td');
                // تخطي صفوف رؤوس المجموعات (colspan)
                if (cells.length < 10) return;
                // تخطي صفوف المجاميع الفرعية
                if ($(this).hasClass('table-light')) return;
                if ($(this).hasClass('table-secondary')) return;

                // 12 عمود: #, الكود, التاريخ, الخزينة, التصنيف, العميل, الموظف, البيان, الفرع, المبلغ, الضريبة, الإجمالي
                var code = $(cells[1]).text().trim();
                var date = $(cells[2]).text().trim();
                var treasury = $(cells[3]).text().trim();
                var category = $(cells[4]).text().trim();
                var client = $(cells[5]).text().trim();
                var employee = $(cells[6]).text().trim();
                var description = $(cells[7]).text().trim();
                var branch = $(cells[8]).text().trim();
                var amount = $(cells[9]).text().replace(/,/g, '').trim();
                var tax = $(cells[10]).text().replace(/,/g, '').trim();
                var totalWithTax = $(cells[11]).text().replace(/,/g, '').trim();

                tableData.push({
                    values: [rowNumber++, code, date, treasury, category, client, employee, description, branch, amount, tax, totalWithTax],
                    cellStyles: [
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } }
                    ]
                });
            });

            const config = {
                fileName: 'تقرير_سندات_القبض_حسب_الفترة_تفصيلي',
                mainTitle: 'تقرير سندات القبض حسب الفترة الزمنية - تفصيلي',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    { title: 'عدد السندات', value: formatNumber(totals.count, 0), unit: ' سند', color: 'success', borderColor: 'success' },
                    { title: 'إجمالي المبلغ', value: formatNumber(totals.amount), unit: ' ' + window.AppCurrency.symbol, color: 'success', borderColor: 'success' },
                    { title: 'إجمالي الضريبة', value: formatNumber(totals.tax), unit: ' ' + window.AppCurrency.symbol, color: 'warning', borderColor: 'warning' },
                    { title: 'الإجمالي شامل الضريبة', value: formatNumber(totals.total_with_tax), unit: ' ' + window.AppCurrency.symbol, color: 'primary', borderColor: 'primary' }
                ],

                tableTitle: 'تفاصيل سندات القبض حسب {{ $periodTypes[$periodType] }}',
                tableHeaders: ['#', 'الكود', 'التاريخ', 'الخزينة', 'التصنيف', 'العميل', 'الموظف', 'البيان', 'الفرع', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: [5, 10, 10, 12, 12, 14, 12, 20, 10, 12, 12, 12],
                tableData: tableData,
                summaryRows: [{
                    label: 'الإجمالي',
                    values: ['', '', '', '', '', '', '', '', formatNumber(totals.amount), formatNumber(totals.tax), formatNumber(totals.total_with_tax)],
                    mergeFrom: 1, mergeTo: 9, color: 'primary', alignment: 'right'
                }],
                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!'
            };

            exportToExcelProfessional(config);
        }

        // =====================================
        // الطباعة
        // =====================================
        function printReceiptsByPeriodReport() {
            const isSummary = {{ $isSummary ? 'true' : 'false' }};
            
            if (isSummary) {
                printReceiptsByPeriodSummary();
            } else {
                printReceiptsByPeriodDetails();
            }
        }

        function printReceiptsByPeriodSummary() {
            const totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير سندات القبض حسب الفترة - ملخص',
                infoCards: [
                    { label: 'عدد السندات', value: totals.count + ' سند' },
                    { label: 'إجمالي المبلغ', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-success' },
                    { label: 'إجمالي الضريبة', value: formatNumber(totals.tax) + ' ' + window.AppCurrency.symbol, class: 'text-warning' },
                    { label: 'الإجمالي شامل', value: formatNumber(totals.total_with_tax) + ' ' + window.AppCurrency.symbol, class: 'text-success' }
                ],
                filterInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}'
                },
                tableHeaders: ['#', 'الفترة', 'العدد', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: ['8%', '32%', '15%', '15%', '15%', '15%'],
                windowWidth: 1000, windowHeight: 800
            });
        }

        function printReceiptsByPeriodDetails() {
            const totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير سندات القبض حسب الفترة - تفصيلي',
                infoCards: [
                    { label: 'عدد السندات', value: totals.count + ' سند' },
                    { label: 'إجمالي المبلغ', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-success' },
                    { label: 'إجمالي الضريبة', value: formatNumber(totals.tax) + ' ' + window.AppCurrency.symbol, class: 'text-warning' },
                    { label: 'الإجمالي شامل', value: formatNumber(totals.total_with_tax) + ' ' + window.AppCurrency.symbol, class: 'text-success' }
                ],
                filterInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}'
                },
                tableHeaders: ['#', 'الكود', 'التاريخ', 'الخزينة', 'التصنيف', 'العميل', 'الموظف', 'البيان', 'الفرع', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: ['5%', '8%', '8%', '10%', '10%', '12%', '10%', '12%', '8%', '8%', '8%', '8%'],
                descriptionColumn: 7,
                windowWidth: 1400, windowHeight: 900
            });
        }

        // تغيير نوع الفترة الزمنية
        function changePeriodType(periodType) {
            // تحديث الحقل المخفي
            document.getElementById('periodTypeInput').value = periodType;
            
            // إفراغ حقول التاريخ ليتم حسابها تلقائياً حسب الفترة الجديدة
            document.querySelector('input[name="date_from"]').value = '';
            document.querySelector('input[name="date_to"]').value = '';
            
            // تحديث مظهر الأزرار
            document.querySelectorAll('.period-btn').forEach(btn => {
                if (btn.getAttribute('data-period') === periodType) {
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
            });
            
            // إرسال النموذج
            document.getElementById('filterForm').submit();
        }
    </script>
@endsection
