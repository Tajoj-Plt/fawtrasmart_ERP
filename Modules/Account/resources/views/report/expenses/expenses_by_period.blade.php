@extends('account::master')

@section('title')
    المصروفات حسب الفترة الزمنية
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">

@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'المصروفات حسب الفترة الزمنية',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'التقارير' => route('GeneralAccountReports.index'),
            'المصروفات حسب الفترة' => null,
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
                    <form method="GET" action="{{ route('GeneralAccountReports.expensesByPeriod') }}" id="filterForm">
                        <!-- Hidden field for period type -->
                        <input type="hidden" name="period_type" id="periodTypeInput" value="{{ $periodType }}">

                    <div class="row">
                        <div class="col-md-12">
                            <div class="period-tabs">
                                <a href="{{ route('GeneralAccountReports.expensesByPeriod', ['period_type' => 'daily']) }}"
                                   class="btn {{ $periodType == 'daily' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-day"></i> يومي
                                </a>
                                <a href="{{ route('GeneralAccountReports.expensesByPeriod', ['period_type' => 'weekly']) }}"
                                   class="btn {{ $periodType == 'weekly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-week"></i> أسبوعي
                                </a>
                                <a href="{{ route('GeneralAccountReports.expensesByPeriod', ['period_type' => 'monthly']) }}"
                                   class="btn {{ $periodType == 'monthly' ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fa fa-calendar-alt"></i> شهري
                                </a>
                                <a href="{{ route('GeneralAccountReports.expensesByPeriod', ['period_type' => 'yearly']) }}"
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
                                <label>تصنيف المصروف</label>
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

                            <!-- Supplier Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>المورد</label>
                                <select name="supplier_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->trade_name ?? $supplier->first_name }}
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
                                <a href="{{ route('GeneralAccountReports.expensesByPeriod') }}" class="btn btn-secondary">
                                    <i class="fa fa-refresh"></i> إعادة تعيين
                                </a>
                            </div>

                            <!-- Export Buttons -->
                            <div class="col-md-6 col-sm-6 mb-1 text-right">
                                <button type="button" id="printBtn" class="btn btn-success">
                                    <i class="fa fa-print"></i> طباعة
                                </button>
                                <button type="button" id="exportExcel" class="btn btn-info">
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
                <span class="stk-metric__label">عدد المصروفات</span>
                <span class="stk-metric__value">{{ $totals['count'] ?? 0 }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
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
                <h6 class="stk-table-head__title"><i class="fa fa-chart-bar"></i> الرسم البياني للمصروفات ({{ $periodTypes[$periodType] }})</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="expensesChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge bg-light text-dark-danger mr-2">
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
                <h6 class="stk-table-head__title"><i class="fa fa-table"></i> {{ $isSummary ? 'ملخص المصروفات' : 'تفاصيل المصروفات' }} حسب {{ $periodTypes[$periodType] }}</h6>
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
                                        <th class="text-center">عدد المصروفات</th>
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
                                            <td class="text-danger">{{ number_format($data['amount'], 2) }}</td>
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
                                        <td class="text-danger font-weight-bold">@money($totals['amount'])</td>
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
                                        <th class="text-center">المورد</th>
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
                                                    <span class="badge bg-info mr-2">{{ $data['count'] }} مصروف</span>
                                                    <span class="badge bg-danger">@money($data['total_with_tax'])</span>
                                                </strong>
                                            </td>
                                        </tr>

                                        @foreach($data['items'] as $expense)
                                            @php $globalRowNum++; @endphp
                                            <tr>
                                                <td>{{ $globalRowNum }}</td>
                                                <td>
                                                    @if($expense->journal_entry_id)
                                                        <a href="{{ route('journal.show', $expense->journal_entry_id) }}" class="text-primary">
                                                            {{ $expense->code ?? $expense->id }}
                                                        </a>
                                                    @else
                                                        {{ $expense->code ?? $expense->id }}
                                                    @endif
                                                </td>
                                                <td>{{ $expense->date ? \Carbon\Carbon::parse($expense->date)->format('d/m/Y') : '-' }}</td>
                                                <td>{{ $expense->treasury->name ?? '-' }}</td>
                                                <td>{{ $expense->expenses_category->name ?? '-' }}</td>
                                                <td>{{ $expense->Supplier->trade_name ?? $expense->seller ?? '-' }}</td>
                                                <td>{{ $expense->createdBy->name ?? '-' }}</td>
                                                <td>{{ Str::limit($expense->description ?? '-', 25) }}</td>
                                                <td>{{ $expense->branch->name ?? '-' }}</td>
                                                <td class="text-danger">{{ number_format($expense->amount ?? 0, 2) }}</td>
                                                <td class="text-warning">{{ number_format(($expense->tax1_amount ?? 0) + ($expense->tax2_amount ?? 0), 2) }}</td>
                                                <td class="text-primary font-weight-bold">{{ number_format(($expense->amount ?? 0) + ($expense->tax1_amount ?? 0) + ($expense->tax2_amount ?? 0), 2) }}</td>
                                            </tr>
                                        @endforeach

                                        <!-- صف المجموع للفترة -->
                                        <tr class="table-light">
                                            <td colspan="9" class="text-left"><strong>إجمالي {{ $data['name'] }}</strong></td>
                                            <td class="text-danger"><strong>{{ number_format($data['amount'], 2) }}</strong></td>
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
                                        <td class="text-danger"><strong>@money($totals['amount'])</strong></td>
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
        let expensesChart;

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

            // معالجات الأحداث
            $('#printBtn').click(function() {
                printExpensesByPeriodReport();
            });

            $('#exportExcel').click(function() {
                exportToExcel();
            });
        });

        function initChart() {
            const ctx = document.getElementById('expensesChart');
            if (!ctx) return;

            if (expensesChart) expensesChart.destroy();

            const chartData = @json($chartData);

            expensesChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'المبلغ',
                            data: chartData.amounts,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                            borderColor: 'rgba(220, 53, 69, 1)',
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
            var isSummary = {{ $isSummary ? 'true' : 'false' }};
            
            if (isSummary) {
                exportExpensesByPeriodSummary();
            } else {
                exportExpensesByPeriodDetails();
            }
        }

        function exportExpensesByPeriodSummary() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            // جمع بيانات الجدول الملخص من Blade مباشرة
            var tableData = [];
            
            @if(!empty($reportData))
            @foreach($reportData as $periodKey => $data)
            tableData.push({
                values: [
                    '{{ $data["name"] ?? $periodKey }}',
                    '{{ $data["count"] ?? 0 }}',
                    formatNumber({{ $data['amount'] ?? 0 }}),
                    formatNumber({{ $data['tax'] ?? 0 }}),
                    formatNumber({{ $data['total_with_tax'] ?? 0 }})
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' } }
                ]
            });
            @endforeach
            @endif

            var config = {
                fileName: 'تقرير_المصروفات_حسب_الفترة_ملخص',
                mainTitle: 'تقرير المصروفات حسب الفترة الزمنية - ملخص',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'عدد المصروفات',
                        value: formatNumber(totals.count, 0),
                        unit: ' مصروف',
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'إجمالي المبلغ',
                        value: formatNumber(totals.amount),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'إجمالي الضريبة',
                        value: formatNumber(totals.tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'warning',
                        valueColor: 'warning',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: 'الإجمالي شامل الضريبة',
                        value: formatNumber(totals.total_with_tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayLight',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'ملخص المصروفات حسب {{ $periodTypes[$periodType] }}',
                tableHeaders: ['الفترة', 'عدد المصروفات', 'إجمالي المبلغ', 'إجمالي الضريبة', 'الإجمالي شامل الضريبة'],
                columnWidths: [35, 15, 18, 18, 18],
                
                tableData: tableData,

                summaryRows: [
                    {
                        label: 'الإجمالي العام',
                        values: [
                            'الإجمالي العام',
                            totals.count.toString(),
                            formatNumber(totals.amount),
                            formatNumber(totals.tax),
                            formatNumber(totals.total_with_tax)
                        ],
                        color: 'primary',
                        cellStyles: [
                            { bold: true },
                            { bold: true },
                            { color: 'danger', bold: true },
                            { color: 'warning', bold: true },
                            { color: 'primary', bold: true }
                        ]
                    }
                ],

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

        function exportExpensesByPeriodDetails() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            // جمع بيانات الجدول التفصيلي من DOM
            var tableData = [];
            var rowNumber = 1;
            
            $('#reportTable tbody tr').each(function() {
                var cells = $(this).find('td');
                // تجاهل صفوف الفترة (التي تحتوي على colspan)
                if (cells.length < 10) return;
                // تجاهل صفوف المجموع
                if ($(this).hasClass('table-light')) return;
                
                tableData.push({
                    values: [
                        rowNumber++,
                        $(cells[1]).text().trim(),   // الكود
                        $(cells[2]).text().trim(),   // التاريخ
                        $(cells[3]).text().trim(),   // الخزينة
                        $(cells[4]).text().trim(),   // التصنيف
                        $(cells[5]).text().trim(),   // المورد
                        $(cells[6]).text().trim(),   // الموظف
                        $(cells[7]).text().trim(),   // البيان
                        $(cells[8]).text().trim(),   // الفرع
                        $(cells[9]).text().trim(),   // المبلغ
                        $(cells[10]).text().trim(),  // الضريبة
                        $(cells[11]).text().trim()   // الإجمالي
                    ],
                    cellStyles: [
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' }, color: 'danger' },
                        { alignment: { horizontal: 'center' }, color: 'warning' },
                        { alignment: { horizontal: 'center' }, color: 'primary' }
                    ]
                });
            });

            var config = {
                fileName: 'تقرير_المصروفات_حسب_الفترة_تفصيلي',
                mainTitle: 'تقرير المصروفات حسب الفترة الزمنية - تفصيلي',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'عدد المصروفات',
                        value: formatNumber(totals.count, 0),
                        unit: ' مصروف',
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'إجمالي المبلغ',
                        value: formatNumber(totals.amount),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'إجمالي الضريبة',
                        value: formatNumber(totals.tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'warning',
                        valueColor: 'warning',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: 'الإجمالي شامل الضريبة',
                        value: formatNumber(totals.total_with_tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayLight',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'تفاصيل المصروفات حسب {{ $periodTypes[$periodType] }}',
                tableHeaders: ['#', 'الكود', 'التاريخ', 'الخزينة', 'التصنيف', 'المورد', 'الموظف', 'البيان', 'الفرع', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: [5, 8, 10, 10, 12, 12, 10, 20, 10, 10, 10, 10],
                
                tableData: tableData,

                summaryRows: [
                    {
                        label: 'الإجمالي العام',
                        values: [
                            'الإجمالي العام', '', '', '', '', '', '', '', '',
                            formatNumber(totals.amount),
                            formatNumber(totals.tax),
                            formatNumber(totals.total_with_tax)
                        ],
                        color: 'primary',
                        cellStyles: [
                            { bold: true },
                            null, null, null, null, null, null, null, null,
                            { color: 'danger', bold: true },
                            { color: 'warning', bold: true },
                            { color: 'primary', bold: true }
                        ]
                    }
                ],

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

        // =====================================
        // الطباعة - تدعم الملخص والتفاصيل
        // =====================================
        function printExpensesByPeriodReport() {
            var isSummary = {{ $isSummary ? 'true' : 'false' }};
            
            if (isSummary) {
                printExpensesByPeriodSummary();
            } else {
                printExpensesByPeriodDetails();
            }
        }

        function printExpensesByPeriodSummary() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير المصروفات حسب الفترة الزمنية - ملخص',
                infoCards: [
                    { label: 'عدد المصروفات', value: totals.count + ' مصروف' },
                    { label: 'إجمالي المبلغ', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                    { label: 'إجمالي الضريبة', value: formatNumber(totals.tax) + ' ' + window.AppCurrency.symbol, class: 'text-warning' },
                    { label: 'الإجمالي شامل الضريبة', value: formatNumber(totals.total_with_tax) + ' ' + window.AppCurrency.symbol }
                ],
                filterInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', 'الفترة', 'عدد المصروفات', 'إجمالي المبلغ', 'إجمالي الضريبة', 'الإجمالي شامل الضريبة'],
                columnWidths: ['5%', '30%', '15%', '17%', '17%', '16%'],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

        function printExpensesByPeriodDetails() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير المصروفات حسب الفترة الزمنية - تفصيلي',
                infoCards: [
                    { label: 'عدد المصروفات', value: totals.count + ' مصروف' },
                    { label: 'إجمالي المبلغ', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                    { label: 'إجمالي الضريبة', value: formatNumber(totals.tax) + ' ' + window.AppCurrency.symbol, class: 'text-warning' },
                    { label: 'الإجمالي شامل الضريبة', value: formatNumber(totals.total_with_tax) + ' ' + window.AppCurrency.symbol }
                ],
                filterInfo: {
                    'نوع الفترة': '{{ $periodTypes[$periodType] }}',
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', 'الكود', 'التاريخ', 'الخزينة', 'التصنيف', 'المورد', 'الموظف', 'البيان', 'الفرع', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: ['4%', '6%', '8%', '8%', '10%', '10%', '8%', '16%', '8%', '8%', '7%', '7%'],
                descriptionColumn: 8,
                windowWidth: 1600,
                windowHeight: 900
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
