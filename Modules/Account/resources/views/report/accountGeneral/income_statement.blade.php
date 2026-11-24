@extends('account::master')

@section('title')
    تقرير قائمة الدخل
@stop

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير قائمة الدخل',
        'crumbs' => [
            'الرئيسية' => null,
            'تقرير قائمة الدخل' => null,
        ],
    ])

    <div class="content-body">
        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="card-title">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>بحث وفلترة</div>
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.incomeStatement') }}">
                        <div class="form-body row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'form-group col-md-3',
                                'period' => $reportPeriod ?? [],
                            ])

                            <div class="form-group col-md-3">
                                <label for="">من تاريخ</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate ?? now()->startOfYear()->format('Y-m-d') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">إلى تاريخ</label>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate ?? now()->format('Y-m-d') }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">الفرع</label>
                                <select name="branch" id="branch" class="form-control">
                                    <option value="">جميع الفروع</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">مركز التكلفة</label>
                                <select name="cost_center" id="cost_center" class="form-control">
                                    <option value="">جميع مراكز التكلفة</option>
                                    @foreach ($costCenters as $costCenter)
                                        <option value="{{ $costCenter->id }}" {{ request('cost_center') == $costCenter->id ? 'selected' : '' }}>{{ $costCenter->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Hidden Div للبحث المتقدم -->
                        <div class="collapse" id="advancedSearchForm">
                            <div class="form-body row">
                                <div class="form-group col-md-4">
                                    {{--
                                        **السنة المالية من التقويم لا من التقويم الميلادي.**

                                        كانت القائمةُ سنواتٍ ميلادية مكتوبةً في القالب
                                        (2016…2026) لا صلةَ لها بالسنوات المعرَّفة ولا
                                        بفتراتها — ومنشأةٌ سنتُها تبدأ في يوليو تُقرأ
                                        قائمةُ دخلها مقطوعةً عند يناير.

                                        وصارت تُرسل `period_id=year:<id>` — أي المفتاحَ
                                        نفسَه الذي يفهمه فلتر الفترة، فلا تتنازع قائمتان
                                        على معنًى واحد.
                                    --}}
                                    <label for="fiscal_year_shortcut">السنة المالية</label>
                                    <select id="fiscal_year_shortcut" class="form-control" data-fiscal-shortcut>
                                        <option value="">— اختر سنةً لتطبيقها —</option>
                                        @foreach (\App\Support\AccountingPeriodOptions::years() as $shortcutYear)
                                            <option value="year:{{ $shortcutYear->id }}"
                                                @selected(request('period_id') === 'year:' . $shortcutYear->id)>
                                                {{ $shortcutYear->name }}{{ $shortcutYear->status !== 'open' ? ' (مقفلة)' : '' }}
                                            </option>
                                        @endforeach
                                        <option value="all" @selected(request('period_id') === 'all')>جميع السنوات</option>
                                    </select>
                                    <small class="stk-help">تضبط «الفترة المحاسبية» أعلاه.</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="">عرض الحسابات</label>
                                    <select name="account" id="account" class="form-control">
                                        <option value="">عرض جميع الحسابات</option>
                                        <option value="1">عرض الحسابات التي عليها معاملات</option>
                                        <option value="2">إخفاء الحسابات الصفرية</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="">المستويات</label>
                                    <select name="level" id="level" class="form-control">
                                        <option value="">المستويات الافتراضية</option>
                                        <option value="1">مستوى 1</option>
                                        <option value="2">مستوى 2</option>
                                        <option value="3">مستوى 3</option>
                                        <option value="4">مستوى 4</option>
                                        <option value="5">مستوى 5</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">بحث</button>
                            <a class="btn btn-outline-secondary ml-2 mr-2" data-bs-toggle="collapse" data-bs-target="#advancedSearchForm">
                                <i class="bi bi-sliders"></i> بحث متقدم
                            </a>
                            <a href="{{ route('GeneralAccountReports.incomeStatement') }}" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">الغاء الفلترة</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">إجمالي الإيرادات</span>
                <span class="stk-metric__value">{{ number_format($totals['total_revenues'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">تكلفة المبيعات</span>
                <span class="stk-metric__value">{{ number_format($totals['total_cost_of_sales'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">مجمل الربح</span>
                <span class="stk-metric__value">{{ number_format($totals['gross_profit'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">إجمالي المصروفات</span>
                <span class="stk-metric__value">{{ number_format($totals['total_expenses'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-bar-chart-2"></i></span>
                <span class="stk-metric__label">صافي الدخل</span>
                <span class="stk-metric__value">{{ number_format($totals['net_income'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">هامش الربح الصافي</span>
                <span class="stk-metric__value">{{ number_format($totals['profit_margin'] ?? 0, 2) }}%</span>
            </div>
        </div>
        <br>

        <!-- Chart Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="stk-table-head__title">مقارنة الإيرادات والمصروفات</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="incomeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card" id="reportContainer">
            <div class="card-content">
                <div class="card-body">
                    <h6 class="stk-table-head__title">ملخص قائمة الدخل</h6>
                    <div class="table-responsive">
                        <div class="row">
                            <!-- Revenues Table -->
                            <div class="col-md-6">
                                <h6 class="p-3 mb-0">
                                    <i class="fa fa-arrow-up me-2 text-success"></i>
                                    الإيرادات
                                </h6>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>اسم الحساب</th>
                                            <th>الكود</th>
                                            <th class="text-end">المبلغ (ريال)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($revenues) && count($revenues) > 0)
                                            @php $totalRevenuesAmount = 0; @endphp
                                            @foreach($revenues as $revenue)
                                                @php $totalRevenuesAmount += $revenue['balance']; @endphp
                                                <tr>
                                                    <td>{{ $revenue['name'] }}</td>
                                                    <td><strong>{{ $revenue['code'] }}</strong></td>
                                                    <td class="text-success fw-bold text-end">{{ number_format($revenue['balance'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td colspan="2"><strong>إجمالي الإيرادات</strong></td>
                                                <td class="text-end">{{ number_format($totalRevenuesAmount, 2) }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="3" class="text-center py-5">
                                                    <div class="bg-royal-linear alert text-center" role="alert">
                                                        <i class="fa fa-info-circle me-2"></i>
                                                        <p class="mb-0">لا توجد بيانات إيرادات</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <!-- Cost of Sales Table -->
                            <div class="col-md-6">
                                <h6 class="p-3 mb-0">
                                    <i class="fa fa-shopping-cart me-2 text-warning"></i>
                                    تكلفة المبيعات
                                </h6>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>اسم الحساب</th>
                                            <th>الكود</th>
                                            <th class="text-end">المبلغ (ريال)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($costOfSales) && count($costOfSales) > 0)
                                            @php $totalCostAmount = 0; @endphp
                                            @foreach($costOfSales as $cost)
                                                @php $totalCostAmount += $cost['balance']; @endphp
                                                <tr>
                                                    <td>{{ $cost['name'] }}</td>
                                                    <td><strong>{{ $cost['code'] }}</strong></td>
                                                    <td class="text-warning fw-bold text-end">{{ number_format($cost['balance'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td colspan="2"><strong>إجمالي تكلفة المبيعات</strong></td>
                                                <td class="text-end">{{ number_format($totalCostAmount, 2) }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="3" class="text-center py-5">
                                                    <div class="bg-royal-linear alert text-center" role="alert">
                                                        <i class="fa fa-info-circle me-2"></i>
                                                        <p class="mb-0">لا توجد بيانات تكلفة مبيعات</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Gross Profit Section -->
                        <div class="alert alert-info mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-0"><i class="fa fa-chart-line me-2"></i>مجمل الربح</h5>
                                    <small>الإيرادات - تكلفة المبيعات</small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <h4 class="mb-0 text-info">{{ number_format($totals['gross_profit'] ?? 0, 2) }} ريال</h4>
                                    <small>هامش مجمل الربح: {{ number_format($totals['gross_profit_margin'] ?? 0, 2) }}%</small>
                                </div>
                            </div>
                        </div>

                        <!-- Expenses Table -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6 class="p-3 mb-0">
                                    <i class="fa fa-arrow-down me-2 text-danger"></i>
                                    المصروفات التشغيلية
                                </h6>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th width="50%">اسم الحساب</th>
                                            <th width="20%">الكود</th>
                                            <th width="30%" class="text-end">المبلغ (ريال)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($expenses) && count($expenses) > 0)
                                            @php $totalExpensesAmount = 0; @endphp
                                            @foreach($expenses as $expense)
                                                @php $totalExpensesAmount += $expense['balance']; @endphp
                                                <tr>
                                                    <td>{{ $expense['name'] }}</td>
                                                    <td><strong>{{ $expense['code'] }}</strong></td>
                                                    <td class="text-danger fw-bold text-end">{{ number_format($expense['balance'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td colspan="2"><strong>إجمالي المصروفات</strong></td>
                                                <td class="text-end">{{ number_format($totalExpensesAmount, 2) }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="3" class="text-center py-5">
                                                    <div class="bg-royal-linear alert text-center" role="alert">
                                                        <i class="fa fa-info-circle me-2"></i>
                                                        <p class="mb-0">لا توجد بيانات مصروفات</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Income Statement Summary Section -->
                        <div class="p-4 mt-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                            <h5 class="text-center mb-4">
                                <i class="fa fa-file-contract me-2"></i>
                                ملخص قائمة الدخل النهائي
                            </h5>
                            <div class="row text-center">
                                <div class="col-md-2">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">الإيرادات</h6>
                                        <h4 class="text-success mb-0">{{ number_format($totals['total_revenues'] ?? 0, 2) }}</h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">تكلفة المبيعات</h6>
                                        <h4 class="text-warning mb-0">{{ number_format($totals['total_cost_of_sales'] ?? 0, 2) }}</h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">مجمل الربح</h6>
                                        <h4 class="text-info mb-0">{{ number_format($totals['gross_profit'] ?? 0, 2) }}</h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">المصروفات</h6>
                                        <h4 class="text-danger mb-0">{{ number_format($totals['total_expenses'] ?? 0, 2) }}</h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">صافي الدخل</h6>
                                        <h4 class="text-primary mb-0">{{ number_format($totals['net_income'] ?? 0, 2) }}</h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">الحالة</h6>
                                        <h5 class="mb-0" id="summaryStatus">
                                            @php
                                                $netIncome = $totals['net_income'] ?? 0;
                                            @endphp
                                            @if($netIncome > 0)
                                                <span class="badge bg-success">ربح</span>
                                            @elseif($netIncome < 0)
                                                <span class="badge bg-danger">خسارة</span>
                                            @else
                                                <span class="badge bg-info">متوازن</span>
                                            @endif
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
    let incomeChart;

    $(document).ready(function() {
        // Initialize chart with backend data
        updateChart();

        // Export to Excel
        $('#exportExcel').click(function(e) {
            e.preventDefault();
            exportIncomeStatement();
        });

        // Print
        $('#printBtn').click(function(e) {
            e.preventDefault();
            printIncomeStatement();
        });
    });

    // Update chart using backend data
    function updateChart() {
        const ctx = document.getElementById('incomeChart');
        if (!ctx) return;
        
        const context = ctx.getContext('2d');
        if (incomeChart) {
            incomeChart.destroy();
        }

        @php
            $chartLabels = $chartData['labels'] ?? ['الإيرادات', 'المصروفات', 'صافي الدخل'];
            $chartAmounts = $chartData['amounts'] ?? [0, 0, 0];
            $chartColors = $chartData['colors'] ?? ['#198754', '#dc3545', '#6f42c1'];
        @endphp

        const labels = {!! json_encode($chartLabels) !!};
        const amounts = {!! json_encode($chartAmounts) !!};
        const colors = {!! json_encode($chartColors) !!};

        incomeChart = new Chart(context, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'المبلغ (ريال)',
                    data: amounts,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatNumber(context.parsed.y) + ' ' + window.AppCurrency.symbol;
                            }
                        }
                    }
                },
                scales: {
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

    // تنسيق الأرقام
    function formatNumber(number, decimals = 2) {
        return parseFloat(number || 0).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    // =====================================
    // تصدير Excel باستخدام الملف المساعد
    // =====================================
    function exportIncomeStatement() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        
        // البيانات من الصفحة
        const totals = {
            total_revenues: {{ $totals['total_revenues'] ?? 0 }},
            total_cost_of_sales: {{ $totals['total_cost_of_sales'] ?? 0 }},
            gross_profit: {{ $totals['gross_profit'] ?? 0 }},
            total_expenses: {{ $totals['total_expenses'] ?? 0 }},
            net_income: {{ $totals['net_income'] ?? 0 }},
            profit_margin: {{ $totals['profit_margin'] ?? 0 }}
        };

        // جمع بيانات الإيرادات
        const revenuesData = @json($revenues ?? []);
        // جمع بيانات تكلفة المبيعات
        const costOfSalesData = @json($costOfSales ?? []);
        // جمع بيانات المصروفات
        const expensesData = @json($expenses ?? []);

        // بناء بيانات الجدول
        const tableData = [];
        
        // قسم الإيرادات
        tableData.push({
            values: ['', 'أولاً: الإيرادات', ''],
            mergeFrom: 1,
            mergeTo: 3,
            alignments: ['center', 'center', 'center'],
            cellStyles: [{ bold: true, color: 'primary' }, { bold: true, color: 'primary' }, { bold: true, color: 'primary' }]
        });
        
        revenuesData.forEach((item, index) => {
            tableData.push({
                values: [index + 1, item.name || item.account_name, formatNumber(Math.abs(item.balance || item.total))],
                alignments: ['center', 'right', 'center'],
                cellStyles: [null, null, { color: 'success' }]
            });
        });
        
        tableData.push({
            values: ['', 'إجمالي الإيرادات', formatNumber(totals.total_revenues)],
            alignments: ['center', 'right', 'center'],
            cellStyles: [null, { bold: true }, { bold: true, color: 'success' }]
        });
        
        // فراغ
        tableData.push({ values: ['', '', ''], alignments: ['center', 'center', 'center'] });
        
        // قسم تكلفة المبيعات
        tableData.push({
            values: ['', 'ثانياً: تكلفة المبيعات', ''],
            mergeFrom: 1,
            mergeTo: 3,
            alignments: ['center', 'center', 'center'],
            cellStyles: [{ bold: true, color: 'warning' }, { bold: true, color: 'warning' }, { bold: true, color: 'warning' }]
        });
        
        costOfSalesData.forEach((item, index) => {
            tableData.push({
                values: [index + 1, item.name || item.account_name, formatNumber(Math.abs(item.balance || item.total))],
                alignments: ['center', 'right', 'center'],
                cellStyles: [null, null, { color: 'warning' }]
            });
        });
        
        tableData.push({
            values: ['', 'إجمالي تكلفة المبيعات', formatNumber(totals.total_cost_of_sales)],
            alignments: ['center', 'right', 'center'],
            cellStyles: [null, { bold: true }, { bold: true, color: 'warning' }]
        });
        
        // مجمل الربح
        tableData.push({
            values: ['', 'مجمل الربح', formatNumber(totals.gross_profit)],
            alignments: ['center', 'right', 'center'],
            cellStyles: [null, { bold: true }, { bold: true, color: 'primary' }]
        });
        
        // فراغ
        tableData.push({ values: ['', '', ''], alignments: ['center', 'center', 'center'] });
        
        // قسم المصروفات
        tableData.push({
            values: ['', 'ثالثاً: المصروفات', ''],
            mergeFrom: 1,
            mergeTo: 3,
            alignments: ['center', 'center', 'center'],
            cellStyles: [{ bold: true, color: 'danger' }, { bold: true, color: 'danger' }, { bold: true, color: 'danger' }]
        });
        
        expensesData.forEach((item, index) => {
            tableData.push({
                values: [index + 1, item.name || item.account_name, formatNumber(Math.abs(item.balance || item.total))],
                alignments: ['center', 'right', 'center'],
                cellStyles: [null, null, { color: 'danger' }]
            });
        });
        
        tableData.push({
            values: ['', 'إجمالي المصروفات', formatNumber(totals.total_expenses)],
            alignments: ['center', 'right', 'center'],
            cellStyles: [null, { bold: true }, { bold: true, color: 'danger' }]
        });

        // إعداد التقرير
        const config = {
            fileName: 'قائمة_الدخل',
            mainTitle: 'قائمة الدخل',
            subTitle: 'نظام المحاسبة المتكامل',
            
            reportInfo: {
                'الفترة': `من ${fromDate} إلى ${toDate}`,
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                'إجمالي الإيرادات': formatNumber(totals.total_revenues) + ' ' + window.AppCurrency.symbol,
                'تكلفة المبيعات': formatNumber(totals.total_cost_of_sales) + ' ' + window.AppCurrency.symbol,
                'مجمل الربح': formatNumber(totals.gross_profit) + ' ' + window.AppCurrency.symbol,
                'إجمالي المصروفات': formatNumber(totals.total_expenses) + ' ' + window.AppCurrency.symbol,
                'صافي الدخل': formatNumber(totals.net_income) + ' ' + window.AppCurrency.symbol,
                'هامش الربح': formatNumber(totals.profit_margin, 2) + '%'
            },

            tableTitle: 'تفاصيل قائمة الدخل',
            tableHeaders: ['#', 'البيان', 'المبلغ ({{ \App\Support\CurrencySettings::symbol() }})'],
            columnWidths: [8, 52, 20],
            
            tableData: tableData,

            summaryRows: [
                {
                    values: ['', 'صافي الدخل', formatNumber(totals.net_income)],
                    backgroundColor: 'grayBg',
                    borderColor: 'primary',
                    bold: true,
                    alignments: ['center', 'right', 'center'],
                    cellStyles: [null, { bold: true }, { bold: true, color: totals.net_income >= 0 ? 'success' : 'danger' }]
                },
                {
                    values: ['', 'هامش الربح الصافي', formatNumber(totals.profit_margin, 2) + '%'],
                    backgroundColor: 'grayBg',
                    borderColor: 'primary',
                    alignments: ['center', 'right', 'center'],
                    cellStyles: [null, null, { color: 'primary', bold: true }]
                }
            ],

            summarySheet: {
                sheetName: 'ملخص قائمة الدخل',
                title: 'الملخص المالي',
                tables: [
                    {
                        title: 'البيان',
                        data: [
                            { label: 'إجمالي الإيرادات', value: formatNumber(totals.total_revenues), unit: window.AppCurrency.symbol },
                            { label: 'تكلفة المبيعات', value: formatNumber(totals.total_cost_of_sales), unit: window.AppCurrency.symbol },
                            { label: 'مجمل الربح', value: formatNumber(totals.gross_profit), unit: window.AppCurrency.symbol },
                            { label: 'إجمالي المصروفات', value: formatNumber(totals.total_expenses), unit: window.AppCurrency.symbol },
                            { label: 'صافي الدخل', value: formatNumber(totals.net_income), unit: window.AppCurrency.symbol },
                            { label: 'هامش الربح الصافي', value: formatNumber(totals.profit_margin, 2), unit: '%' }
                        ]
                    }
                ]
            },

            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير قائمة الدخل بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        // استدعاء دالة التصدير
        exportToExcelProfessional(config);
    }

    // =====================================
    // الطباعة
    // =====================================
    function printIncomeStatement() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        
        // البيانات من الصفحة
        const revenuesData = @json($revenues ?? []);
        const costOfSalesData = @json($costOfSales ?? []);
        const expensesData = @json($expenses ?? []);
        const totals = {
            total_revenues: {{ $totals['total_revenues'] ?? 0 }},
            total_cost_of_sales: {{ $totals['total_cost_of_sales'] ?? 0 }},
            gross_profit: {{ $totals['gross_profit'] ?? 0 }},
            total_expenses: {{ $totals['total_expenses'] ?? 0 }},
            net_income: {{ $totals['net_income'] ?? 0 }},
            profit_margin: {{ $totals['profit_margin'] ?? 0 }}
        };
        
        // بناء صفوف الإيرادات
        let revenuesRows = '';
        let totalRevenuesAmount = 0;
        revenuesData.forEach(item => {
            const balance = Math.abs(item.balance || item.total || 0);
            totalRevenuesAmount += balance;
            revenuesRows += `
                <tr>
                    <td>${item.name || item.account_name || ''}</td>
                    <td><strong>${item.code || ''}</strong></td>
                    <td class="text-success fw-bold">${formatNumber(balance)}</td>
                </tr>
            `;
        });
        if (revenuesData.length > 0) {
            revenuesRows += `
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td colspan="2"><strong>إجمالي الإيرادات</strong></td>
                    <td class="text-success fw-bold">${formatNumber(totalRevenuesAmount)}</td>
                </tr>
            `;
        } else {
            revenuesRows = '<tr><td colspan="3" style="text-align: center; padding: 20px; color: #666;">لا توجد بيانات إيرادات</td></tr>';
        }
        
        // بناء صفوف تكلفة المبيعات
        let costOfSalesRows = '';
        let totalCostAmount = 0;
        costOfSalesData.forEach(item => {
            const balance = Math.abs(item.balance || item.total || 0);
            totalCostAmount += balance;
            costOfSalesRows += `
                <tr>
                    <td>${item.name || item.account_name || ''}</td>
                    <td><strong>${item.code || ''}</strong></td>
                    <td class="text-warning fw-bold">${formatNumber(balance)}</td>
                </tr>
            `;
        });
        if (costOfSalesData.length > 0) {
            costOfSalesRows += `
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td colspan="2"><strong>إجمالي تكلفة المبيعات</strong></td>
                    <td class="text-warning fw-bold">${formatNumber(totalCostAmount)}</td>
                </tr>
            `;
        } else {
            costOfSalesRows = '<tr><td colspan="3" style="text-align: center; padding: 20px; color: #666;">لا توجد بيانات تكلفة مبيعات</td></tr>';
        }
        
        // بناء صفوف المصروفات
        let expensesRows = '';
        let totalExpensesAmount = 0;
        expensesData.forEach(item => {
            const balance = Math.abs(item.balance || item.total || 0);
            totalExpensesAmount += balance;
            expensesRows += `
                <tr>
                    <td>${item.name || item.account_name || ''}</td>
                    <td><strong>${item.code || ''}</strong></td>
                    <td class="text-danger fw-bold">${formatNumber(balance)}</td>
                </tr>
            `;
        });
        if (expensesData.length > 0) {
            expensesRows += `
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td colspan="2"><strong>إجمالي المصروفات</strong></td>
                    <td class="text-danger fw-bold">${formatNumber(totalExpensesAmount)}</td>
                </tr>
            `;
        } else {
            expensesRows = '<tr><td colspan="3" style="text-align: center; padding: 20px; color: #666;">لا توجد بيانات مصروفات</td></tr>';
        }
        
        // فتح نافذة جديدة للطباعة
        const printWindow = window.open('', '', 'height=900,width=1200');
        
        // بناء محتوى HTML للطباعة
        const printContent = `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="UTF-8">
                <title>قائمة الدخل</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Arial', 'Tahoma', sans-serif; padding: 25px; background-color: white; color: #333; line-height: 1.6; }
                    .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #333; padding-bottom: 15px; }
                    .header h1 { font-size: 28px; color: #333; margin-bottom: 8px; font-weight: bold; }
                    .header p { color: #666; font-size: 13px; margin: 3px 0; }
                    .info-section { margin-bottom: 25px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }
                    .info-row { display: flex; justify-content: space-around; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
                    .info-item { flex: 1; min-width: 150px; text-align: center; padding: 10px; background: white; border-radius: 4px; }
                    .info-item label { display: block; font-size: 11px; color: #666; margin-bottom: 4px; }
                    .info-item value { display: block; font-size: 15px; font-weight: bold; color: #333; }
                    .section-title { font-size: 18px; font-weight: bold; color: #333; margin: 25px 0 15px 0; padding: 10px 15px; background-color: #f0f0f0; border-right: 4px solid #333; }
                    .section-title.revenues { border-right-color: #28a745; background-color: #e8f5e9; }
                    .section-title.cost { border-right-color: #ffc107; background-color: #fff9e6; }
                    .section-title.expenses { border-right-color: #dc3545; background-color: #fce4e4; }
                    .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #333; }
                    .table thead th { background-color: #333; color: white; padding: 12px 8px; text-align: center; font-weight: bold; border: 1px solid #333; font-size: 13px; }
                    .table tbody td, .table tfoot td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; font-size: 12px; }
                    .table tbody tr:nth-child(even) { background-color: #f8f9fa; }
                    .table tfoot tr { font-weight: bold; background-color: #e9ecef; }
                    .text-success { color: #28a745 !important; font-weight: bold; }
                    .text-danger { color: #dc3545 !important; font-weight: bold; }
                    .text-warning { color: #ffc107 !important; font-weight: bold; }
                    .text-primary { color: #007bff !important; font-weight: bold; }
                    .fw-bold { font-weight: bold !important; }
                    .summary-box { margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #333; border-radius: 8px; page-break-inside: avoid; }
                    .summary-title { text-align: center; font-size: 20px; font-weight: bold; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #333; }
                    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
                    .summary-card { background-color: white; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6; text-align: center; }
                    .summary-card h6 { font-size: 11px; color: #666; margin-bottom: 8px; }
                    .summary-card h4 { font-size: 20px; font-weight: bold; margin: 0; }
                    .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #333; text-align: center; color: #666; font-size: 11px; }
                    @media print {
                        body { margin: 0; padding: 15px; }
                        .header, .section-title { page-break-after: avoid; }
                        tr { page-break-inside: avoid; }
                        thead { display: table-header-group; }
                        .summary-box { page-break-before: auto; page-break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>قائمة الدخل</h1>
                    <p>نظام المحاسبة المتكامل</p>
                    <p>الفترة: من ${fromDate} إلى ${toDate}</p>
                    <p>تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} - ${new Date().toLocaleTimeString('ar-SA')}</p>
                </div>
                
                <div class="info-section">
                    <div class="info-row">
                        <div class="info-item"><label>إجمالي الإيرادات</label><value class="text-success">${$('#totalRevenues').text()}</value></div>
                        <div class="info-item"><label>تكلفة المبيعات</label><value class="text-warning">${$('#totalCostOfSales').text()}</value></div>
                        <div class="info-item"><label>مجمل الربح</label><value class="text-primary">${$('#grossProfit').text()}</value></div>
                        <div class="info-item"><label>إجمالي المصروفات</label><value class="text-danger">${$('#totalExpenses').text()}</value></div>
                        <div class="info-item"><label>صافي الدخل</label><value class="text-primary">${$('#netIncome').text()}</value></div>
                        <div class="info-item"><label>هامش الربح</label><value>${$('#profitMargin').text()}</value></div>
                    </div>
                </div>
                
                <h3 class="section-title revenues">📈 الإيرادات</h3>
                <table class="table">
                    <thead><tr><th>اسم الحساب</th><th>الكود</th><th>المبلغ ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
                    <tbody>${revenuesRows}</tbody>
                </table>
                
                <h3 class="section-title cost">📦 تكلفة المبيعات</h3>
                <table class="table">
                    <thead><tr><th>اسم الحساب</th><th>الكود</th><th>المبلغ ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
                    <tbody>${costOfSalesRows}</tbody>
                </table>
                
                <div class="alert alert-info" style="padding: 15px; background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin: 20px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 16px; font-weight: bold; color: #0c5460;">مجمل الربح:</span>
                        <span class="text-primary" style="font-size: 18px; font-weight: bold;">${$('#grossProfit').text()}</span>
                    </div>
                </div>
                
                <h3 class="section-title expenses">📉 المصروفات</h3>
                <table class="table">
                    <thead><tr><th>اسم الحساب</th><th>الكود</th><th>المبلغ ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
                    <tbody>${expensesRows}</tbody>
                </table>
                
                <div class="summary-box">
                    <div class="summary-title">📊 الملخص المالي النهائي</div>
                    <div class="summary-grid">
                        <div class="summary-card"><h6>إجمالي الإيرادات</h6><h4 class="text-success">${$('#totalRevenues').text()}</h4></div>
                        <div class="summary-card"><h6>إجمالي المصروفات</h6><h4 class="text-danger">${$('#totalExpenses').text()}</h4></div>
                        <div class="summary-card"><h6>صافي الدخل</h6><h4 class="text-primary">${$('#netIncome').text()}</h4></div>
                        <div class="summary-card"><h6>تكلفة المبيعات</h6><h4 class="text-warning">${$('#totalCostOfSales').text()}</h4></div>
                        <div class="summary-card"><h6>مجمل الربح</h6><h4 class="text-primary">${$('#grossProfit').text()}</h4></div>
                        <div class="summary-card"><h6>هامش الربح الصافي</h6><h4>${$('#profitMargin').text()}</h4></div>
                    </div>
                </div>
                
                <div class="footer">
                    <p>تم إنشاء هذا التقرير بواسطة النظام المحاسبي - جميع الحقوق محفوظة ${new Date().getFullYear()}</p>
                </div>
            </body>
            </html>
        `;
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // انتظر تحميل الصفحة ثم اطبع تلقائياً
        setTimeout(function() {
            printWindow.focus();
            printWindow.print();
        }, 500);
    }
</script>
@endsection

@push('scripts')
    <script>
        /*
            اختصارُ السنة يكتب في قائمة الفترة نفسِها ثم يُرسل النموذج.

            قائمتان تُرسلان معنًى واحداً تتنازعان: تُختار سنةٌ هنا وفترةٌ هناك،
            فلا يُعرف أيُّهما طُبِّق. فهذه تضبط تلك ولا تُرسل حقلاً خاصّاً بها.
        */
        document.querySelectorAll('[data-fiscal-shortcut]').forEach(function (shortcut) {
            shortcut.addEventListener('change', function () {
                var form = shortcut.closest('form');
                if (! form) return;

                var picker = form.querySelector('[name="period_id"]');
                if (! picker || ! shortcut.value) return;

                picker.value = shortcut.value;

                // الفترةُ تَجُبّ المدى المكتوب، فيُفرَّغ لئلّا يتنازعا
                ['from_date', 'to_date'].forEach(function (name) {
                    var field = form.querySelector('[name="' + name + '"]');
                    if (field) field.value = '';
                });

                form.submit();
            });
        });
    </script>
@endpush
