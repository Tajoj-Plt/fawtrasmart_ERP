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
        <!-- Filter Card -->
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.profitAndLoss') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-2">
                                <label>نوع التقرير</label>
                                <select name="report_type" class="form-control" id="reportTypeSelect">
                                    <option value="cash" {{ ($reportType ?? 'cash') == 'cash' ? 'selected' : '' }}>نقدي (Cash)</option>
                                    <option value="accrual" {{ ($reportType ?? 'cash') == 'accrual' ? 'selected' : '' }}>استحقاق (Accrual)</option>
                                </select>
                            </div>
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'form-group col-md-2',
                            ])

                            <div class="form-group col-md-2">
                                <label>من تاريخ</label>
                                <input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? now()->subYear()->format('Y-m-d') }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label>إلى تاريخ</label>
                                <input type="date" name="to_date" class="form-control" value="{{ $toDate ?? now()->format('Y-m-d') }}">
                            </div>
                            <div class="form-group col-md-2">
                                <label>الفرع</label>
                                <select name="branch" class="form-control">
                                    <option value="">جميع الفروع</option>
                                    @foreach ($branches ?? [] as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label>مركز التكلفة</label>
                                <select name="cost_center" class="form-control">
                                    <option value="">الكل</option>
                                    @foreach ($costCenters ?? [] as $costCenter)
                                        <option value="{{ $costCenter->id }}" {{ request('cost_center') == $costCenter->id ? 'selected' : '' }}>{{ $costCenter->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa fa-search me-1"></i> عرض التقرير
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @php
            $netProfit = $totals['net_profit'] ?? 0;
            $monthlyDetailed = $monthlyData['detailed'] ?? [];
            $monthLabels = $monthlyData['labels'] ?? [];
            $isCashReport = ($reportType ?? 'cash') == 'cash';
        @endphp

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">{{ $isCashReport ? 'إجمالي المقبوضات النقدية' : 'إجمالي الإيرادات' }}</span>
                <span class="stk-metric__value">{{ number_format($totals['total_revenues'] ?? 0, 0) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">{{ $isCashReport ? 'إجمالي المدفوعات النقدية' : 'إجمالي المصروفات والتكاليف' }}</span>
                <span class="stk-metric__value">{{ number_format($totals['total_expenses_all'] ?? 0, 0) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__label">
                    @if ($isCashReport)
                        {{ $netProfit >= 0 ? 'صافي التدفق النقدي الموجب' : 'صافي التدفق النقدي السالب' }}
                    @else
                        {{ $netProfit >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}
                    @endif
                </span>
                <span class="stk-metric__value">{{ number_format(abs($netProfit), 0) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">{{ $isCashReport ? 'نسبة الصافي للمقبوضات' : 'هامش الربح الصافي' }}</span>
                <span class="stk-metric__value">{{ number_format($totals['net_profit_margin'] ?? 0, 1) }}%</span>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge {{ $isCashReport ? 'bg-success' : 'bg-info' }} mr-2 p-2">
                            <i class="fa {{ $isCashReport ? 'fa-money-bill-wave' : 'fa-file-invoice' }} mr-1"></i>
                            {{ $isCashReport ? 'تقرير نقدي (Cash Basis)' : 'تقرير استحقاق (Accrual Basis)' }}
                        </span>
                        <span class="mx-2">|</span>
                        <span class="text-muted">الفترة:</span>
                        <strong>{{ $fromDate ?? '' }} - {{ $toDate ?? '' }}</strong>
                        <span class="mx-2">|</span>
                        <span class="text-muted">الفرع:</span>
                        <strong>{{ request('branch') ? ($branches->find(request('branch'))->name ?? 'محدد') : 'جميع الفروع' }}</strong>
                    </div>
                    <small class="text-muted">آخر تحديث: {{ now()->format('Y-m-d H:i') }}</small>
                </div>
            </div>
        </div>

        <!-- Monthly Chart -->
        <div class="card mt-2">
            <div class="card-header">
                <h6 class="stk-table-head__title">الرسم البياني الشهري</h6>
            </div>
            <div class="card-body">
                <div style="height: 320px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge bg-light text-dark-success mr-2">
                        <i class="fa fa-circle mr-1"></i> 
                        {{ $isCashReport ? 'المقبوضات النقدية' : 'الإيرادات' }}
                    </span>
                    <span class="badge bg-light text-dark-danger mr-2">
                        <i class="fa fa-circle mr-1"></i> 
                        {{ $isCashReport ? 'المدفوعات النقدية' : 'المصروفات' }}
                    </span>
                    <span class="badge bg-light text-dark-primary">
                        <i class="fa fa-circle mr-1"></i> 
                        {{ $isCashReport ? 'صافي التدفق' : 'الربح/الخسارة' }}
                    </span>
                </div>
            </div>
        </div>

        @if($isCashReport)
            {{-- ===== تقرير نقدي ===== --}}
            
            <!-- المقبوضات النقدية -->
            <div class="card mt-2" id="receiptsCard">
                <div class="card-header bg-light-success">
                    <h4 class="card-title text-success mb-0">
                        <i class="feather icon-arrow-down-circle mr-1"></i>
                        المقبوضات النقدية - التفصيل الشهري (SAR)
                    </h4>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table" id="receiptsTable">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="min-width: 180px;">البيان</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center text-nowrap">{{ $month['label'] }}</th>
                                    @endforeach
                                    <th class="text-center text-nowrap bg-light"><strong>الإجمالي</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- مبيعات نقدية -->
                                <tr>
                                    <td><i class="fa fa-shopping-cart text-success mr-1"></i> مبيعات نقدية</td>
                                    @php $total1 = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['row1'] ?? 0) > 0 ? number_format($month['row1'], 0) : '-' }}</td>
                                        @php $total1 += $month['row1'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($total1, 0) }}</td>
                                </tr>
                                <!-- تحصيلات من العملاء -->
                                <tr>
                                    <td><i class="fa fa-users text-info mr-1"></i> تحصيلات من العملاء</td>
                                    @php $total2 = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['row2'] ?? 0) > 0 ? number_format($month['row2'], 0) : '-' }}</td>
                                        @php $total2 += $month['row2'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($total2, 0) }}</td>
                                </tr>
                                <!-- مقبوضات نقدية أخرى -->
                                <tr>
                                    <td><i class="fa fa-plus-circle text-primary mr-1"></i> مقبوضات نقدية أخرى</td>
                                    @php $total3 = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['row3'] ?? 0) > 0 ? number_format($month['row3'], 0) : '-' }}</td>
                                        @php $total3 += $month['row3'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($total3, 0) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-success text-white">
                                    <th>إجمالي المقبوضات النقدية</th>
                                    @php $grandTotalRec = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center">{{ number_format($month['total_receipts'] ?? 0, 0) }}</th>
                                        @php $grandTotalRec += $month['total_receipts'] ?? 0; @endphp
                                    @endforeach
                                    <th class="text-center">{{ number_format($grandTotalRec, 0) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- المدفوعات النقدية -->
            <div class="card mt-2" id="expensesCard">
                <div class="card-header bg-light-danger">
                    <h4 class="card-title text-danger mb-0">
                        <i class="feather icon-arrow-up-circle mr-1"></i>
                        المدفوعات النقدية - التفصيل الشهري (SAR)
                    </h4>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table" id="expensesTable">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="min-width: 180px;">البيان</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center text-nowrap">{{ $month['label'] }}</th>
                                    @endforeach
                                    <th class="text-center text-nowrap bg-light"><strong>الإجمالي</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- مردودات مبيعات نقدية -->
                                <tr>
                                    <td><i class="fa fa-undo text-warning mr-1"></i> مردودات مبيعات نقدية</td>
                                    @php $totalExp1 = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['exp1'] ?? 0) > 0 ? number_format($month['exp1'], 0) : '-' }}</td>
                                        @php $totalExp1 += $month['exp1'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalExp1, 0) }}</td>
                                </tr>
                                <!-- مشتريات نقدية -->
                                <tr>
                                    <td><i class="fa fa-truck text-danger mr-1"></i> مشتريات نقدية</td>
                                    @php $totalExp2 = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['exp2'] ?? 0) > 0 ? number_format($month['exp2'], 0) : '-' }}</td>
                                        @php $totalExp2 += $month['exp2'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalExp2, 0) }}</td>
                                </tr>
                                <!-- الرواتب والأجور -->
                                <tr>
                                    <td><i class="fa fa-users text-info mr-1"></i> الرواتب والأجور</td>
                                    @php $totalSalaries = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['salaries_expenses'] ?? 0) > 0 ? number_format($month['salaries_expenses'], 0) : '-' }}</td>
                                        @php $totalSalaries += $month['salaries_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalSalaries, 0) }}</td>
                                </tr>
                                <!-- مصروفات التصنيع -->
                                <tr>
                                    <td><i class="fa fa-industry text-secondary mr-1"></i> مصروفات التصنيع</td>
                                    @php $totalManufacturing = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['manufacturing_expenses'] ?? 0) > 0 ? number_format($month['manufacturing_expenses'], 0) : '-' }}</td>
                                        @php $totalManufacturing += $month['manufacturing_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalManufacturing, 0) }}</td>
                                </tr>
                                <!-- مصروفات التسويق -->
                                <tr>
                                    <td><i class="fa fa-bullhorn text-success mr-1"></i> مصروفات التسويق</td>
                                    @php $totalMarketing = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['marketing_expenses'] ?? 0) > 0 ? number_format($month['marketing_expenses'], 0) : '-' }}</td>
                                        @php $totalMarketing += $month['marketing_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalMarketing, 0) }}</td>
                                </tr>
                                <!-- مصروفات النقل -->
                                <tr>
                                    <td><i class="fa fa-truck-moving text-primary mr-1"></i> مصروفات النقل</td>
                                    @php $totalTransport = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['transport_expenses'] ?? 0) > 0 ? number_format($month['transport_expenses'], 0) : '-' }}</td>
                                        @php $totalTransport += $month['transport_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalTransport, 0) }}</td>
                                </tr>
                                <!-- مصروفات التمويل -->
                                <tr>
                                    <td><i class="fa fa-university text-dark mr-1"></i> مصروفات التمويل</td>
                                    @php $totalFinance = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['finance_expenses'] ?? 0) > 0 ? number_format($month['finance_expenses'], 0) : '-' }}</td>
                                        @php $totalFinance += $month['finance_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalFinance, 0) }}</td>
                                </tr>
                                <!-- مصروفات إدارية -->
                                <tr>
                                    <td><i class="fa fa-building text-info mr-1"></i> مصروفات إدارية</td>
                                    @php $totalAdmin = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['admin_expenses'] ?? 0) > 0 ? number_format($month['admin_expenses'], 0) : '-' }}</td>
                                        @php $totalAdmin += $month['admin_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalAdmin, 0) }}</td>
                                </tr>
                                <!-- مصروفات أخرى -->
                                <tr>
                                    <td><i class="fa fa-ellipsis-h text-muted mr-1"></i> مصروفات أخرى</td>
                                    @php $totalOther = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['other_expenses'] ?? 0) > 0 ? number_format($month['other_expenses'], 0) : '-' }}</td>
                                        @php $totalOther += $month['other_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalOther, 0) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-danger text-white">
                                    <th>إجمالي المدفوعات النقدية</th>
                                    @php $grandTotalExp = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center">{{ number_format($month['total_expenses'] ?? 0, 0) }}</th>
                                        @php $grandTotalExp += $month['total_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <th class="text-center">{{ number_format($grandTotalExp, 0) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- صافي التدفق النقدي -->
            <div class="card mt-2" id="profitCard">
                <div class="card-header {{ $netProfit >= 0 ? 'bg-light-primary' : 'bg-light-warning' }}">
                    <h4 class="card-title {{ $netProfit >= 0 ? 'text-primary' : 'text-warning' }} mb-0">
                        <i class="feather icon-bar-chart-2 mr-1"></i>
                        صافي التدفق النقدي - التفصيل الشهري (SAR)
                    </h4>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table" id="profitTable">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="min-width: 180px;">البيان</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center text-nowrap">{{ $month['label'] }}</th>
                                    @endforeach
                                    <th class="text-center text-nowrap bg-light"><strong>الإجمالي</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="{{ $netProfit >= 0 ? 'bg-primary' : 'bg-warning' }} text-white">
                                    <th>صافي التدفق النقدي</th>
                                    @php $grandTotalProfit = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        @php 
                                            $profit = $month['profit'] ?? 0;
                                            $grandTotalProfit += $profit;
                                        @endphp
                                        <th class="text-center">
                                            {{ number_format($profit, 0) }}
                                        </th>
                                    @endforeach
                                    <th class="text-center">
                                        {{ number_format($grandTotalProfit, 0) }}
                                    </th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ملخص التدفقات النقدية -->
            <div class="card mt-2" id="summaryCard">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="feather icon-file-text mr-1"></i>
                        ملخص التدفقات النقدية
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr class="bg-light-success">
                                        <td width="60%"><strong>إجمالي المقبوضات النقدية</strong></td>
                                        <td class="text-left font-weight-bold text-success">{{ number_format($totals['total_revenues'] ?? 0, 2) }} ريال</td>
                                    </tr>
                                    <tr class="bg-light-danger">
                                        <td><strong>(-) إجمالي المدفوعات النقدية</strong></td>
                                        <td class="text-left font-weight-bold text-danger">{{ number_format($totals['total_expenses_all'] ?? 0, 2) }} ريال</td>
                                    </tr>
                                    <tr class="{{ $netProfit >= 0 ? 'bg-primary' : 'bg-warning' }} text-white">
                                        <td><strong class="h6">= صافي التدفق النقدي</strong></td>
                                        <td class="text-left"><strong class="h5">{{ number_format($netProfit, 2) }} ريال</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="alert {{ $netProfit >= 0 ? 'alert-success' : 'alert-warning' }}">
                                <h5 class="alert-heading">
                                    <i class="fa {{ $netProfit >= 0 ? 'fa-check-circle' : 'fa-exclamation-triangle' }} mr-1"></i>
                                    {{ $netProfit >= 0 ? 'تدفق نقدي إيجابي' : 'تدفق نقدي سلبي' }}
                                </h5>
                                <hr>
                                <p class="mb-0">
                                    @if($netProfit >= 0)
                                        المنشأة حققت صافي تدفق نقدي موجب بقيمة <strong>{{ number_format($netProfit, 2) }}</strong> ريال خلال الفترة المحددة.
                                    @else
                                        المنشأة لديها عجز نقدي بقيمة <strong>{{ number_format(abs($netProfit), 2) }}</strong> ريال خلال الفترة المحددة.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        @else
            {{-- ===== تقرير استحقاق ===== --}}
            
            <!-- الإيرادات -->
            <div class="card mt-2" id="receiptsCard">
                <div class="card-header bg-light-success">
                    <h4 class="card-title text-success mb-0">
                        <i class="feather icon-trending-up mr-1"></i>
                        الإيرادات - التفصيل الشهري (SAR)
                    </h4>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table" id="receiptsTable">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="min-width: 180px;">البيان</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center text-nowrap">{{ $month['label'] }}</th>
                                    @endforeach
                                    <th class="text-center text-nowrap bg-light"><strong>الإجمالي</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- المبيعات -->
                                <tr>
                                    <td><i class="fa fa-shopping-cart text-success mr-1"></i> المبيعات</td>
                                    @php $totalSales = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['sales'] ?? 0) > 0 ? number_format($month['sales'], 0) : '-' }}</td>
                                        @php $totalSales += $month['sales'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalSales, 0) }}</td>
                                </tr>
                                <!-- إيرادات أخرى -->
                                <tr>
                                    <td><i class="fa fa-plus-circle text-info mr-1"></i> إيرادات أخرى</td>
                                    @php $totalOther = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['other_revenue'] ?? 0) > 0 ? number_format($month['other_revenue'], 0) : '-' }}</td>
                                        @php $totalOther += $month['other_revenue'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalOther, 0) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-success text-white">
                                    <th>إجمالي الإيرادات</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center">{{ number_format($month['total_receipts'] ?? 0, 0) }}</th>
                                    @endforeach
                                    <th class="text-center">{{ number_format($totals['total_revenues'] ?? 0, 0) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- التكاليف والمصروفات -->
            <div class="card mt-2" id="expensesCard">
                <div class="card-header bg-light-danger">
                    <h4 class="card-title text-danger mb-0">
                        <i class="feather icon-trending-down mr-1"></i>
                        التكاليف والمصروفات - التفصيل الشهري (SAR)
                    </h4>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table" id="expensesTable">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="min-width: 180px;">البيان</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center text-nowrap">{{ $month['label'] }}</th>
                                    @endforeach
                                    <th class="text-center text-nowrap bg-light"><strong>الإجمالي</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- مردودات المبيعات -->
                                <tr>
                                    <td><i class="fa fa-undo text-warning mr-1"></i> مردودات المبيعات</td>
                                    @php $totalReturns = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['sales_returns'] ?? 0) > 0 ? number_format($month['sales_returns'], 0) : '-' }}</td>
                                        @php $totalReturns += $month['sales_returns'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalReturns, 0) }}</td>
                                </tr>
                                <!-- تكلفة المبيعات -->
                                <tr>
                                    <td><i class="fa fa-box text-danger mr-1"></i> تكلفة المبيعات</td>
                                    @php $totalCost = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['cost_of_sales'] ?? 0) > 0 ? number_format($month['cost_of_sales'], 0) : '-' }}</td>
                                        @php $totalCost += $month['cost_of_sales'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalCost, 0) }}</td>
                                </tr>
                                <!-- الرواتب والأجور -->
                                <tr>
                                    <td><i class="fa fa-users text-primary mr-1"></i> الرواتب والأجور</td>
                                    @php $totalSalaries = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['salaries_expenses'] ?? 0) > 0 ? number_format($month['salaries_expenses'], 0) : '-' }}</td>
                                        @php $totalSalaries += $month['salaries_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalSalaries, 0) }}</td>
                                </tr>
                                <!-- مصروفات الإهلاك -->
                                <tr>
                                    <td><i class="fa fa-chart-line text-info mr-1"></i> مصروفات الإهلاك</td>
                                    @php $totalDepreciation = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['depreciation_expenses'] ?? 0) > 0 ? number_format($month['depreciation_expenses'], 0) : '-' }}</td>
                                        @php $totalDepreciation += $month['depreciation_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalDepreciation, 0) }}</td>
                                </tr>
                                <!-- المصروفات الإدارية والعمومية -->
                                <tr>
                                    <td><i class="fa fa-building text-secondary mr-1"></i> المصروفات الإدارية والعمومية</td>
                                    @php $totalAdmin = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['admin_expenses'] ?? 0) > 0 ? number_format($month['admin_expenses'], 0) : '-' }}</td>
                                        @php $totalAdmin += $month['admin_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalAdmin, 0) }}</td>
                                </tr>
                                <!-- مصروفات التصنيع -->
                                <tr>
                                    <td><i class="fa fa-industry text-dark mr-1"></i> مصروفات التصنيع</td>
                                    @php $totalManufacturing = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['manufacturing_expenses'] ?? 0) > 0 ? number_format($month['manufacturing_expenses'], 0) : '-' }}</td>
                                        @php $totalManufacturing += $month['manufacturing_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalManufacturing, 0) }}</td>
                                </tr>
                                <!-- مصروفات التسويق والمبيعات -->
                                <tr>
                                    <td><i class="fa fa-bullhorn text-success mr-1"></i> مصروفات التسويق والمبيعات</td>
                                    @php $totalMarketing = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['marketing_expenses'] ?? 0) > 0 ? number_format($month['marketing_expenses'], 0) : '-' }}</td>
                                        @php $totalMarketing += $month['marketing_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalMarketing, 0) }}</td>
                                </tr>
                                <!-- مصروفات النقل والمواصلات -->
                                <tr>
                                    <td><i class="fa fa-truck text-warning mr-1"></i> مصروفات النقل والمواصلات</td>
                                    @php $totalTransport = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['transport_expenses'] ?? 0) > 0 ? number_format($month['transport_expenses'], 0) : '-' }}</td>
                                        @php $totalTransport += $month['transport_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalTransport, 0) }}</td>
                                </tr>
                                <!-- مصروفات التمويل -->
                                <tr>
                                    <td><i class="fa fa-money-bill text-danger mr-1"></i> مصروفات التمويل</td>
                                    @php $totalFinance = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['finance_expenses'] ?? 0) > 0 ? number_format($month['finance_expenses'], 0) : '-' }}</td>
                                        @php $totalFinance += $month['finance_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalFinance, 0) }}</td>
                                </tr>
                                <!-- مصروفات أخرى -->
                                <tr>
                                    <td><i class="fa fa-minus-circle text-muted mr-1"></i> مصروفات أخرى</td>
                                    @php $totalOtherExp = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        <td class="text-center">{{ ($month['other_expenses'] ?? 0) > 0 ? number_format($month['other_expenses'], 0) : '-' }}</td>
                                        @php $totalOtherExp += $month['other_expenses'] ?? 0; @endphp
                                    @endforeach
                                    <td class="text-center font-weight-bold bg-light">{{ number_format($totalOtherExp, 0) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-danger text-white">
                                    <th>إجمالي التكاليف والمصروفات</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center">{{ number_format($month['total_expenses'] ?? 0, 0) }}</th>
                                    @endforeach
                                    <th class="text-center">{{ number_format($totals['total_expenses_all'] ?? 0, 0) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- صافي الربح/الخسارة الشهري -->
            <div class="card mt-2" id="profitCard">
                <div class="card-header {{ $netProfit >= 0 ? 'bg-light-primary' : 'bg-light-warning' }}">
                    <h4 class="card-title {{ $netProfit >= 0 ? 'text-primary' : 'text-warning' }} mb-0">
                        <i class="feather icon-bar-chart-2 mr-1"></i>
                        {{ $netProfit >= 0 ? 'الربح' : 'الخسارة' }} - التفصيل الشهري (SAR)
                    </h4>
                </div>
                <div class="card-content">
                    <div class="table-responsive">
                        <table class="table" id="profitTable">
                            <thead>
                                <tr>
                                    <th class="text-nowrap" style="min-width: 180px;">البيان</th>
                                    @foreach($monthlyDetailed as $month)
                                        <th class="text-center text-nowrap">{{ $month['label'] }}</th>
                                    @endforeach
                                    <th class="text-center text-nowrap bg-light"><strong>الإجمالي</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="{{ $netProfit >= 0 ? 'bg-primary' : 'bg-warning' }} text-white">
                                    <th>{{ $netProfit >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}</th>
                                    @php $grandTotalProfit = 0; @endphp
                                    @foreach($monthlyDetailed as $month)
                                        @php $profit = $month['profit'] ?? 0; $grandTotalProfit += $profit; @endphp
                                        <th class="text-center">{{ number_format($profit, 0) }}</th>
                                    @endforeach
                                    <th class="text-center">{{ number_format($grandTotalProfit, 0) }}</th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- قائمة الربح والخسارة المحاسبية -->
            <div class="card mt-2" id="summaryCard">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="feather icon-file-text mr-1"></i>
                        قائمة الربح والخسارة (Income Statement)
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <td width="60%">إجمالي المبيعات</td>
                                        <td class="text-left font-weight-bold">{{ number_format($totals['total_sales'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger">(-) مردودات المبيعات</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_sales_returns'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr class="bg-light">
                                        <td><strong>= صافي المبيعات (Net Sales)</strong></td>
                                        <td class="text-left font-weight-bold">{{ number_format($totals['net_sales'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger">(-) تكلفة المبيعات (Cost of Sales)</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_cost_of_sales'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr class="bg-light-info">
                                        <td><strong>= مجمل الربح (Gross Profit)</strong></td>
                                        <td class="text-left font-weight-bold text-info">{{ number_format($totals['gross_profit'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="bg-secondary text-white"><strong>المصروفات التشغيلية</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger pl-4">(-) الرواتب والأجور</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_salaries_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger pl-4">(-) مصروفات الإهلاك</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_depreciation_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger pl-4">(-) المصروفات الإدارية والعمومية</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_admin_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger pl-4">(-) مصروفات التصنيع</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_manufacturing_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger pl-4">(-) مصروفات التسويق والمبيعات</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_marketing_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger pl-4">(-) مصروفات النقل والمواصلات</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_transport_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger pl-4">(-) مصروفات التمويل</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_finance_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr class="bg-light">
                                        <td><strong>= إجمالي المصروفات التشغيلية</strong></td>
                                        <td class="text-left font-weight-bold text-danger">{{ number_format(
                                            ($totals['total_salaries_expenses'] ?? 0) +
                                            ($totals['total_depreciation_expenses'] ?? 0) +
                                            ($totals['total_admin_expenses'] ?? 0) +
                                            ($totals['total_manufacturing_expenses'] ?? 0) +
                                            ($totals['total_marketing_expenses'] ?? 0) +
                                            ($totals['total_transport_expenses'] ?? 0) +
                                            ($totals['total_finance_expenses'] ?? 0)
                                        , 2) }}</td>
                                    </tr>
                                    <tr class="bg-light-primary">
                                        <td><strong>= الربح التشغيلي (Operating Profit)</strong></td>
                                        <td class="text-left font-weight-bold text-primary">{{ number_format($totals['operating_profit'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-success">(+) إيرادات أخرى</td>
                                        <td class="text-left text-success">{{ number_format($totals['total_other_revenue'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-danger">(-) مصروفات أخرى</td>
                                        <td class="text-left text-danger">{{ number_format($totals['total_other_expenses'] ?? 0, 2) }}</td>
                                    </tr>
                                    <tr class="{{ $netProfit >= 0 ? 'bg-success' : 'bg-danger' }} text-white">
                                        <td><strong class="h6">= {{ $netProfit >= 0 ? 'صافي الربح (Net Profit)' : 'صافي الخسارة (Net Loss)' }}</strong></td>
                                        <td class="text-left"><strong class="h5">{{ number_format(abs($netProfit), 2) }} ريال</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <!-- نسب الربحية -->
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">نسب الربحية (Profitability Ratios)</h6>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small>هامش مجمل الربح</small>
                                            <small><strong>{{ number_format($totals['gross_profit_margin'] ?? 0, 1) }}%</strong></small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: {{ min(abs($totals['gross_profit_margin'] ?? 0), 100) }}%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small>هامش الربح التشغيلي</small>
                                            <small><strong>{{ number_format($totals['operating_profit_margin'] ?? 0, 1) }}%</strong></small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: {{ min(abs($totals['operating_profit_margin'] ?? 0), 100) }}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <small>هامش صافي الربح</small>
                                            <small><strong>{{ number_format($totals['net_profit_margin'] ?? 0, 1) }}%</strong></small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar {{ $netProfit >= 0 ? 'bg-success' : 'bg-danger' }}" style="width: {{ min(abs($totals['net_profit_margin'] ?? 0), 100) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- حالة المنشأة -->
                            <div class="text-center mt-3">
                                @if($netProfit > 0)
                                    <span class="badge bg-success p-2">
                                        <i class="fa fa-check-circle mr-1"></i>
                                        المنشأة تحقق أرباحاً
                                    </span>
                                @elseif($netProfit < 0)
                                    <span class="badge bg-danger p-2">
                                        <i class="fa fa-exclamation-triangle mr-1"></i>
                                        المنشأة تحقق خسائر
                                    </span>
                                @else
                                    <span class="badge bg-info p-2">
                                        <i class="fa fa-balance-scale mr-1"></i>
                                        نقطة التعادل
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- المعادلات المستخدمة -->
            <div class="card mt-2">
                <div class="card-header py-1">
                    <h6 class="mb-0"><i class="fa fa-calculator mr-1"></i> المعادلات المحاسبية المستخدمة</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row small text-muted">
                        <div class="col-md-3">
                            <strong>صافي المبيعات =</strong><br>
                            المبيعات - مردودات المبيعات
                        </div>
                        <div class="col-md-3">
                            <strong>مجمل الربح =</strong><br>
                            صافي المبيعات - تكلفة المبيعات
                        </div>
                        <div class="col-md-3">
                            <strong>الربح التشغيلي =</strong><br>
                            مجمل الربح - المصروفات الإدارية
                        </div>
                        <div class="col-md-3">
                            <strong>صافي الربح =</strong><br>
                            الربح التشغيلي + إيرادات أخرى - مصروفات أخرى
                        </div>
                    </div>
                </div>
            </div>
        @endif
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
    let monthlyChart;
    const isCashReport = {{ $isCashReport ? 'true' : 'false' }};

    $(document).ready(function() {
        initMonthlyChart();
        
        $('#exportExcel').click(function(e) {
            e.preventDefault();
            exportToExcel();
        });
        
        $('#printBtn').click(function(e) {
            e.preventDefault();
            printProfitLoss();
        });
        
        $('#reportTypeSelect').change(function() {
            $('#reportForm').submit();
        });
    });

    function initMonthlyChart() {
        const ctx = document.getElementById('monthlyChart');
        if (!ctx) return;
        
        if (monthlyChart) monthlyChart.destroy();

        @php
            $chartLabels = $monthlyData['labels'] ?? [];
            $chartRevenues = $monthlyData['revenues'] ?? [];
            $chartExpenses = $monthlyData['expenses'] ?? [];
            $chartProfits = $monthlyData['profits'] ?? [];
        @endphp

        monthlyChart = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json($chartLabels),
                datasets: [
                    {
                        label: isCashReport ? 'المقبوضات النقدية' : 'الإيرادات',
                        data: @json($chartRevenues),
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: isCashReport ? 'المدفوعات النقدية' : 'المصروفات',
                        data: @json($chartExpenses),
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: isCashReport ? 'صافي التدفق' : 'الربح/الخسارة',
                        data: @json($chartProfits),
                        type: 'line',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgba(0, 123, 255, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: 0.3,
                        fill: false
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
                        display: false 
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.85)',
                        padding: 12,
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ': ' + formatNumber(ctx.parsed.y) + ' SAR';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(v) { return formatNumber(v); }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    function formatNumber(n) {
        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function exportToExcel() {
        const fromDate = '{{ $fromDate ?? "" }}';
        const toDate = '{{ $toDate ?? "" }}';
        const branchName = '{{ request("branch") ? ($branches->find(request("branch"))->name ?? "جميع الفروع") : "جميع الفروع" }}';
        
        const totals = {
            total_revenues: {{ $totals['total_revenues'] ?? 0 }},
            total_expenses: {{ $totals['total_expenses_all'] ?? 0 }},
            net_profit: {{ $totals['net_profit'] ?? 0 }},
            profit_margin: {{ $totals['net_profit_margin'] ?? 0 }}
        };
        
        // جمع رؤوس الأعمدة (البيان + الأشهر + الإجمالي)
        const headers = ['البيان'];
        const monthlyDetailed = @json($monthlyDetailed ?? []);
        
        monthlyDetailed.forEach(month => {
            headers.push(month.label || '');
        });
        headers.push('الإجمالي');
        
        // جمع بيانات الإيرادات/المقبوضات من الجدول
        const receiptsData = [];
        $('#receiptsTable tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length > 0 && !$(this).hasClass('bg-success')) {
                const rowData = {
                    values: [],
                    alignments: [],
                    cellStyles: []
                };
                
                cells.each(function(index) {
                    rowData.values.push($(this).text().trim());
                    rowData.alignments.push(index === 0 ? 'right' : 'center');
                    rowData.cellStyles.push(index === 0 ? null : { color: 'success' });
                });
                
                if (rowData.values.length > 0) {
                    receiptsData.push(rowData);
                }
            }
        });
        
        // جمع بيانات المصروفات/المدفوعات من الجدول
        const expensesData = [];
        $('#expensesTable tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length > 0 && !$(this).hasClass('bg-danger')) {
                const rowData = {
                    values: [],
                    alignments: [],
                    cellStyles: []
                };
                
                cells.each(function(index) {
                    rowData.values.push($(this).text().trim());
                    rowData.alignments.push(index === 0 ? 'right' : 'center');
                    rowData.cellStyles.push(index === 0 ? null : { color: 'danger' });
                });
                
                if (rowData.values.length > 0) {
                    expensesData.push(rowData);
                }
            }
        });
        
        // بناء بيانات الجدول المدمج
        const tableData = [];
        
        // قسم الإيرادات
        const revenuesHeaderRow = {
            values: Array(headers.length).fill(''),
            alignments: Array(headers.length).fill('center'),
            cellStyles: Array(headers.length).fill({ bold: true, color: 'success' })
        };
        revenuesHeaderRow.values[0] = isCashReport ? '📥 المقبوضات النقدية' : '📈 الإيرادات';
        revenuesHeaderRow.mergeFrom = 1;
        revenuesHeaderRow.mergeTo = headers.length;
        tableData.push(revenuesHeaderRow);
        
        tableData.push(...receiptsData);
        
        // إجمالي الإيرادات من tfoot
        const receiptsTotal = {
            values: [],
            alignments: [],
            cellStyles: []
        };
        $('#receiptsTable tfoot tr').find('th').each(function(index) {
            receiptsTotal.values.push($(this).text().trim());
            receiptsTotal.alignments.push(index === 0 ? 'right' : 'center');
            receiptsTotal.cellStyles.push({ bold: true, color: 'success' });
        });
        if (receiptsTotal.values.length > 0) {
            tableData.push(receiptsTotal);
        }
        
        // فراغ
        tableData.push({ 
            values: Array(headers.length).fill(''), 
            alignments: Array(headers.length).fill('center'),
            cellStyles: Array(headers.length).fill(null)
        });
        
        // قسم المصروفات
        const expensesHeaderRow = {
            values: Array(headers.length).fill(''),
            alignments: Array(headers.length).fill('center'),
            cellStyles: Array(headers.length).fill({ bold: true, color: 'danger' })
        };
        expensesHeaderRow.values[0] = isCashReport ? '📤 المدفوعات النقدية' : '📉 المصروفات';
        expensesHeaderRow.mergeFrom = 1;
        expensesHeaderRow.mergeTo = headers.length;
        tableData.push(expensesHeaderRow);
        
        tableData.push(...expensesData);
        
        // إجمالي المصروفات من tfoot
        const expensesTotal = {
            values: [],
            alignments: [],
            cellStyles: []
        };
        $('#expensesTable tfoot tr').find('th').each(function(index) {
            expensesTotal.values.push($(this).text().trim());
            expensesTotal.alignments.push(index === 0 ? 'right' : 'center');
            expensesTotal.cellStyles.push({ bold: true, color: 'danger' });
        });
        if (expensesTotal.values.length > 0) {
            tableData.push(expensesTotal);
        }
        
        // فراغ
        tableData.push({ 
            values: Array(headers.length).fill(''), 
            alignments: Array(headers.length).fill('center'),
            cellStyles: Array(headers.length).fill(null)
        });
        
        // صافي الربح/التدفق
        const profitRow = {
            values: [],
            alignments: [],
            cellStyles: []
        };
        $('#profitTable tbody tr').find('th').each(function(index) {
            profitRow.values.push($(this).text().trim());
            profitRow.alignments.push(index === 0 ? 'right' : 'center');
            profitRow.cellStyles.push({ bold: true, color: totals.net_profit >= 0 ? 'primary' : 'warning' });
        });
        if (profitRow.values.length > 0) {
            tableData.push(profitRow);
        }
        
        // حساب عرض الأعمدة ديناميكياً
        const numColumns = headers.length;
        const columnWidths = [35]; // عرض عمود البيان
        for (let i = 1; i < numColumns; i++) {
            columnWidths.push(12); // عرض أعمدة الأشهر والإجمالي
        }
        
        // إعداد التقرير
        const config = {
            fileName: isCashReport ? 'تقرير_التدفقات_النقدية' : 'تقرير_الربح_والخسارة',
            mainTitle: isCashReport ? 'التدفقات النقدية التفصيلية' : 'تقرير الربح والخسارة (محاسبي)',
            subTitle: 'نظام المحاسبة المتكامل',
            
            reportInfo: {
                'الفترة': fromDate && toDate ? `من ${fromDate} إلى ${toDate}` : 'جميع الفترات',
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                'الفرع': branchName,
                'نوع التقرير': isCashReport ? 'نقدي (Cash)' : 'استحقاق (Accrual)',
                'إجمالي الإيرادات': formatNumber(totals.total_revenues) + ' ' + window.AppCurrency.symbol,
                'إجمالي المصروفات': formatNumber(totals.total_expenses) + ' ' + window.AppCurrency.symbol,
                'الصافي': formatNumber(totals.net_profit) + ' ' + window.AppCurrency.symbol
            },
            
            tableTitle: 'التفصيل الشهري',
            tableHeaders: headers,
            columnWidths: columnWidths,
            
            tableData: tableData,
            
            summarySheet: {
                sheetName: 'الملخص',
                title: 'الملخص المالي',
                tables: [
                    {
                        title: 'البيان',
                        data: [
                            { label: isCashReport ? 'إجمالي المقبوضات' : 'إجمالي الإيرادات', value: formatNumber(totals.total_revenues), unit: window.AppCurrency.symbol },
                            { label: isCashReport ? 'إجمالي المدفوعات' : 'إجمالي المصروفات', value: formatNumber(totals.total_expenses), unit: window.AppCurrency.symbol },
                            { label: isCashReport ? 'صافي التدفق النقدي' : 'صافي الربح/الخسارة', value: formatNumber(totals.net_profit), unit: window.AppCurrency.symbol },
                            { label: 'هامش الربح', value: formatNumber(totals.profit_margin, 2), unit: '%' }
                        ]
                    }
                ]
            },
            
            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير التقرير بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };
        
        // استدعاء دالة التصدير (بدون statisticsCards لأن عدد الأعمدة كبير)
        exportToExcelProfessional(config);
    }
    
    // =====================================
    // الطباعة
    // =====================================
    function printProfitLoss() {
        const fromDate = '{{ $fromDate ?? "" }}';
        const toDate = '{{ $toDate ?? "" }}';
        const reportTitle = isCashReport ? 'التدفقات النقدية التفصيلية' : 'تقرير الربح والخسارة (محاسبي)';
        
        // جمع رؤوس الجدول
        let tableHeaders = '<tr>';
        $('#receiptsTable thead tr th').each(function() {
            tableHeaders += `<th>${$(this).text()}</th>`;
        });
        tableHeaders += '</tr>';
        
        // جمع بيانات الإيرادات/المقبوضات
        let receiptsRows = '';
        $('#receiptsTable tbody tr').each(function() {
            receiptsRows += $(this)[0].outerHTML;
        });
        
        // جمع إجمالي الإيرادات
        let receiptsTfoot = '';
        $('#receiptsTable tfoot tr').each(function() {
            receiptsTfoot += $(this)[0].outerHTML;
        });
        
        // جمع بيانات المصروفات/المدفوعات
        let expensesRows = '';
        $('#expensesTable tbody tr').each(function() {
            expensesRows += $(this)[0].outerHTML;
        });
        
        // جمع إجمالي المصروفات
        let expensesTfoot = '';
        $('#expensesTable tfoot tr').each(function() {
            expensesTfoot += $(this)[0].outerHTML;
        });
        
        // جمع صافي الربح/التدفق
        let profitRows = '';
        $('#profitTable tbody tr').each(function() {
            profitRows += $(this)[0].outerHTML;
        });
        
        // قراءة القيم من البطاقات
        const totalRevenues = $('#totalRevenues, .stat-card:eq(0) h2').first().text().trim();
        const totalExpenses = $('#totalExpenses, .stat-card:eq(1) h2').first().text().trim();
        const netProfit = $('#netProfit, .stat-card:eq(2) h2').first().text().trim();
        const profitMargin = $('#profitMargin, .stat-card:eq(3) h2').first().text().trim();
        
        // فتح نافذة جديدة للطباعة
        const printWindow = window.open('', '', 'height=900,width=1400');
        
        const printContent = `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="UTF-8">
                <title>${reportTitle}</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Arial', 'Tahoma', sans-serif; padding: 20px; background-color: white; color: #333; line-height: 1.5; font-size: 11px; }
                    .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #333; padding-bottom: 10px; }
                    .header h1 { font-size: 24px; color: #333; margin-bottom: 5px; font-weight: bold; }
                    .header p { color: #666; font-size: 11px; margin: 2px 0; }
                    .info-section { margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 3px; }
                    .info-row { display: flex; justify-content: space-around; gap: 8px; flex-wrap: wrap; }
                    .info-item { flex: 1; min-width: 100px; text-align: center; padding: 8px; background: white; border-radius: 3px; }
                    .info-item label { display: block; font-size: 9px; color: #666; margin-bottom: 3px; }
                    .info-item value { display: block; font-size: 13px; font-weight: bold; color: #333; }
                    .section-title { font-size: 14px; font-weight: bold; color: #333; margin: 15px 0 10px 0; padding: 8px 12px; background-color: #f0f0f0; border-right: 3px solid #333; }
                    .section-title.revenues { border-right-color: #28a745; background-color: #e8f5e9; }
                    .section-title.expenses { border-right-color: #dc3545; background-color: #fce4e4; }
                    .section-title.profit { border-right-color: #007bff; background-color: #e3f2fd; }
                    .table { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #333; font-size: 10px; }
                    .table thead th { background-color: #333; color: white; padding: 8px 4px; text-align: center; font-weight: bold; border: 1px solid #333; font-size: 10px; }
                    .table tbody td { padding: 6px 4px; border: 1px solid #ddd; text-align: center; }
                    .table tbody tr:nth-child(even) { background-color: #f8f9fa; }
                    .table tfoot th { font-weight: bold; background-color: #e9ecef; padding: 8px 4px; border: 1px solid #333; }
                    .bg-success { background-color: #28a745 !important; color: white !important; }
                    .bg-danger { background-color: #dc3545 !important; color: white !important; }
                    .bg-primary { background-color: #007bff !important; color: white !important; }
                    .bg-warning { background-color: #ffc107 !important; color: white !important; }
                    .text-success { color: #28a745 !important; font-weight: bold; }
                    .text-danger { color: #dc3545 !important; font-weight: bold; }
                    .text-primary { color: #007bff !important; font-weight: bold; }
                    .text-warning { color: #ffc107 !important; font-weight: bold; }
                    .text-white { color: white !important; }
                    .font-weight-bold { font-weight: bold !important; }
                    .bg-light { background-color: #f8f9fa !important; }
                    .footer { margin-top: 20px; padding-top: 10px; border-top: 2px solid #333; text-align: center; color: #666; font-size: 9px; }
                    @media print {
                        body { margin: 0; padding: 10px; }
                        .header, .section-title { page-break-after: avoid; }
                        tr { page-break-inside: avoid; }
                        thead { display: table-header-group; }
                        tfoot { display: table-footer-group; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>${reportTitle}</h1>
                    <p>نظام المحاسبة المتكامل</p>
                    ${fromDate && toDate ? `<p>الفترة: من ${fromDate} إلى ${toDate}</p>` : ''}
                    <p>تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} - ${new Date().toLocaleTimeString('ar-SA')}</p>
                </div>
                
                <div class="info-section">
                    <div class="info-row">
                        <div class="info-item"><label>${isCashReport ? 'إجمالي المقبوضات' : 'إجمالي الإيرادات'}</label><value class="text-success">${totalRevenues}</value></div>
                        <div class="info-item"><label>${isCashReport ? 'إجمالي المدفوعات' : 'إجمالي المصروفات'}</label><value class="text-danger">${totalExpenses}</value></div>
                        <div class="info-item"><label>${isCashReport ? 'صافي التدفق' : 'صافي الربح'}</label><value class="text-primary">${netProfit}</value></div>
                        <div class="info-item"><label>هامش الربح</label><value>${profitMargin}</value></div>
                    </div>
                </div>
                
                <h3 class="section-title revenues">📈 ${isCashReport ? 'المقبوضات النقدية - التفصيل الشهري' : 'الإيرادات - التفصيل الشهري'}</h3>
                <table class="table">
                    <thead>${tableHeaders}</thead>
                    <tbody>${receiptsRows}</tbody>
                    <tfoot>${receiptsTfoot}</tfoot>
                </table>
                
                <h3 class="section-title expenses">📉 ${isCashReport ? 'المدفوعات النقدية - التفصيل الشهري' : 'المصروفات - التفصيل الشهري'}</h3>
                <table class="table">
                    <thead>${tableHeaders}</thead>
                    <tbody>${expensesRows}</tbody>
                    <tfoot>${expensesTfoot}</tfoot>
                </table>
                
                <h3 class="section-title profit">📊 ${isCashReport ? 'صافي التدفق النقدي - التفصيل الشهري' : 'صافي الربح/الخسارة - التفصيل الشهري'}</h3>
                <table class="table">
                    <thead>${tableHeaders}</thead>
                    <tbody>${profitRows}</tbody>
                </table>
                
                <div class="footer">
                    <p>تم إنشاء هذا التقرير بواسطة النظام المحاسبي - جميع الحقوق محفوظة ${new Date().getFullYear()}</p>
                </div>
            </body>
            </html>
        `;
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        setTimeout(function() {
            printWindow.focus();
            printWindow.print();
        }, 500);
    }
</script>

<style>
    /* Print Styles */
    @media print {
        .card-header .btn, 
        form, 
        .breadcrumb-wrapper,
        #exportExcel,
        #printBtn { 
            display: none !important; 
        }
        .card { 
            box-shadow: none !important; 
            border: 1px solid #e0e0e0 !important; 
            page-break-inside: avoid; 
        }
        .table { 
            font-size: 11px !important; 
        }
    }
    
    /* Clean Table Styles */
    .table {
        font-size: 13px;
        margin-bottom: 0;
    }
    
    .table th, .table td { 
        vertical-align: middle;
        padding: 0.6rem 0.75rem;
        border-color: #e9ecef;
    }
    
    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
    }
    
    .table tfoot th,
    .table tfoot td {
        font-weight: 600;
        border-top: 2px solid #dee2e6;
    }
    
    /* Card Styles */
    .card {
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        border-radius: 8px;
    }
    
    .card-header {
        background-color: #fff;
        border-bottom: 1px solid #e9ecef;
        padding: 0.75rem 1.25rem;
        border-radius: 8px 8px 0 0;
    }
    
    .card-title {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 0;
    }
    
    /* Stat Card */
    .stat-card {
        border-left: 4px solid #e9ecef;
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    .border-left-success { border-left-color: #28a745 !important; }
    .border-left-danger { border-left-color: #dc3545 !important; }
    .border-left-primary { border-left-color: #007bff !important; }
    .border-left-warning { border-left-color: #ffc107 !important; }
    .border-left-info { border-left-color: #17a2b8 !important; }
    
    .stat-card .card-header h2 {
        font-size: 1.75rem;
    }
    
    .stat-card .card-header p {
        font-size: 12px;
        color: #6c757d;
    }
    
    /* Light Background Classes */
    .bg-light-success { background-color: rgba(40, 167, 69, 0.1) !important; }
    .bg-light-danger { background-color: rgba(220, 53, 69, 0.1) !important; }
    .bg-light-primary { background-color: rgba(0, 123, 255, 0.1) !important; }
    .bg-light-warning { background-color: rgba(255, 193, 7, 0.1) !important; }
    .bg-light-info { background-color: rgba(23, 162, 184, 0.1) !important; }
    
    /* Progress Bar */
    .progress {
        background-color: #e9ecef;
        border-radius: 4px;
    }
    
    /* Responsive Tables */
    .table-responsive {
        border: none;
    }
    
    /* Badge Styles */
    .badge {
        font-weight: 500;
        font-size: 12px;
    }
    
    .bg-light text-dark-success {
        background-color: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }
    
    .bg-light text-dark-danger {
        background-color: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }
    
    .bg-light text-dark-primary {
        background-color: rgba(0, 123, 255, 0.15);
        color: #007bff;
    }
    
    /* Form Styles */
    .form-group label {
        font-size: 12px;
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.25rem;
    }
    
    .form-control {
        font-size: 13px;
        border-radius: 6px;
    }
    
    /* Alert improvements */
    .alert {
        border-radius: 8px;
    }
</style>
@endsection
