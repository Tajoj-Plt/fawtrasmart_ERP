@extends('account::master')

@section('title')
    تقرير الضرائب
@stop

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير الضرائب',
        'crumbs' => [
            'الرئيسية' => null,
            'تقرير الضرائب' => null,
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.taxReport') }}">
                        <div class="form-body row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'form-group col-md-3',
                            ])

                            <div class="form-group col-md-3">
                                <label for="">من تاريخ</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" value="{{ request('from_date', now()->subDays(30)->format('Y-m-d')) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">إلى تاريخ</label>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="{{ request('to_date', now()->format('Y-m-d')) }}">
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
                                <label for="">نوع العرض</label>
                                <select name="view_type" id="view_type" class="form-control">
                                    <option value="summary" {{ request('view_type') == 'summary' ? 'selected' : '' }}>الملخص</option>
                                    <option value="detailed" {{ request('view_type') == 'detailed' ? 'selected' : '' }}>التفاصيل</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">بحث</button>
                            <button type="button" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">الغاء الفلترة</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">ضريبة المبيعات (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxReportData) ? number_format($taxReportData['summary']['sales_tax'], 2) : '0.00' }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">ضريبة المرتجعات (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxReportData) ? number_format($taxReportData['summary']['returns_tax'], 2) : '0.00' }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__label">ضريبة المشتريات (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxReportData) ? number_format($taxReportData['summary']['purchases_tax'], 2) : '0.00' }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calculator"></i></span>
                <span class="stk-metric__label">صافي الضريبة (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxReportData) ? number_format($taxReportData['summary']['net_tax_due'] ?? 0, 2) : '0.00' }}</span>
            </div>
        </div>
        <br>

        <!-- Sales Tax Card -->
        <div class="card" id="salesTaxCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-arrow-up me-2 text-success"></i> ضرائب المبيعات</h6>
                        <div id="salesInfo" class="text-muted">
                            @if(isset($taxReportData))
                                إجمالي: {{ $taxReportData['sales']['count'] }} فاتورة
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم الفاتورة</th>
                                    <th>التاريخ</th>
                                    <th>العميل</th>
                                    <th>الرقم الضريبي</th>
                                    <th>المبلغ قبل الضريبة</th>
                                    <th>الضريبة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="salesTableBody">
                                <!-- Sales data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        <!-- Sales Pagination Controls -->
                        @include('account::report.accountGeneral.partials.tax_pagination', ['type' => 'sales'])
                        <!-- Sales Total Row -->
                        @if(isset($taxReportData) && !empty($taxReportData['sales']['invoices']))
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="4"><strong>الإجمالي</strong></td>
                                    <td>{{ number_format($taxReportData['sales']['total'], 2) }}</td>
                                    <td>{{ number_format($taxReportData['sales']['tax'], 2) }}</td>
                                    <td>{{ number_format($taxReportData['sales']['grand_total'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Returns Tax Card -->
        <div class="card mt-4" id="returnsTaxCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-undo me-2 text-danger"></i> مردودات المبيعات</h6>
                        <div id="returnsInfo" class="text-muted">
                            @if(isset($taxReportData))
                                إجمالي: {{ $taxReportData['returns']['count'] }} قيد
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم القيد</th>
                                    <th>التاريخ</th>
                                    <th>العميل</th>
                                    <th>الرقم الضريبي</th>
                                    <th>المبلغ قبل الضريبة</th>
                                    <th>الضريبة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="returnsTableBody">
                                <!-- Returns data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        @include('account::report.accountGeneral.partials.tax_pagination', ['type' => 'returns'])
                        <!-- Returns Total Row -->
                        @if(isset($taxReportData) && $taxReportData['returns']['count'] > 0)
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #fff3cd; font-weight: bold;">
                                    <td colspan="4"><strong>المجموع</strong></td>
                                    <td class="text-danger">{{ number_format($taxReportData['returns']['total'], 2) }}</td>
                                    <td class="text-danger">{{ number_format($taxReportData['returns']['tax'], 2) }}</td>
                                    <td class="text-danger">{{ number_format($taxReportData['returns']['grand_total'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        @else
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="4"><strong>المجموع</strong></td>
                                    <td>0.00</td>
                                    <td>0.00</td>
                                    <td>0.00</td>
                                </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Debit Notes Card -->
        <div class="card mt-4" id="salesDebitNotesCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-file-invoice me-2 text-info"></i> إشعارات المبيعات المدينة (Sales Debit Note)</h6>
                        <div id="salesDebitNotesInfo" class="text-muted">
                            @if(isset($taxReportData))
                                إجمالي: {{ $taxReportData['sales_debit_notes']['count'] ?? 0 }} قيد
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم القيد</th>
                                    <th>التاريخ</th>
                                    <th>العميل</th>
                                    <th>الرقم الضريبي</th>
                                    <th>المبلغ قبل الضريبة</th>
                                    <th>الضريبة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="salesDebitNotesTableBody">
                                <!-- Sales Debit Notes data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        @include('account::report.accountGeneral.partials.tax_pagination', ['type' => 'salesDebitNotes'])
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="4"><strong>المجموع</strong></td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['sales_debit_notes']['total'] ?? 0, 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['sales_debit_notes']['tax'] ?? 0, 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['sales_debit_notes']['grand_total'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchases Tax Card -->
        <div class="card mt-4" id="purchasesTaxCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-shopping-cart me-2 text-warning"></i> فواتير الشراء</h6>
                        <div id="purchasesInfo" class="text-muted">
                            @if(isset($taxReportData))
                                إجمالي: {{ $taxReportData['purchases']['count'] }} قيد
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم القيد</th>
                                    <th>التاريخ</th>
                                    <th>المورد</th>
                                    <th>الرقم الضريبي</th>
                                    <th>المبلغ قبل الضريبة</th>
                                    <th>الضريبة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="purchasesTableBody">
                                <!-- Purchases data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        @include('account::report.accountGeneral.partials.tax_pagination', ['type' => 'purchases'])
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="4"><strong>المجموع</strong></td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchases']['total'], 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchases']['tax'], 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchases']['grand_total'], 2) : '0.00' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Returns Card -->
        <div class="card mt-4" id="purchaseReturnsCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-undo-alt me-2 text-secondary"></i> مردودات المشتريات</h6>
                        <div id="purchaseReturnsInfo" class="text-muted">
                            @if(isset($taxReportData))
                                إجمالي: {{ $taxReportData['purchase_returns']['count'] ?? 0 }} قيد
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم القيد</th>
                                    <th>التاريخ</th>
                                    <th>المورد</th>
                                    <th>الرقم الضريبي</th>
                                    <th>المبلغ قبل الضريبة</th>
                                    <th>الضريبة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="purchaseReturnsTableBody">
                                <!-- Purchase Returns data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        @include('account::report.accountGeneral.partials.tax_pagination', ['type' => 'purchaseReturns'])
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="4"><strong>المجموع</strong></td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchase_returns']['total'] ?? 0, 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchase_returns']['tax'] ?? 0, 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchase_returns']['grand_total'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchases Credit Notes Card -->
        <div class="card mt-4" id="purchasesCreditNotesCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-file-invoice-dollar me-2 text-purple"></i> إشعارات المشتريات الدائنة (Purchases Credit Note)</h6>
                        <div id="purchasesCreditNotesInfo" class="text-muted">
                            @if(isset($taxReportData))
                                إجمالي: {{ $taxReportData['purchases_credit_notes']['count'] ?? 0 }} قيد
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم القيد</th>
                                    <th>التاريخ</th>
                                    <th>المورد</th>
                                    <th>الرقم الضريبي</th>
                                    <th>المبلغ قبل الضريبة</th>
                                    <th>الضريبة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="purchasesCreditNotesTableBody">
                                <!-- Purchases Credit Notes data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        @include('account::report.accountGeneral.partials.tax_pagination', ['type' => 'purchasesCreditNotes'])
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="4"><strong>المجموع</strong></td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchases_credit_notes']['total'] ?? 0, 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchases_credit_notes']['tax'] ?? 0, 2) : '0.00' }}</td>
                                    <td>{{ isset($taxReportData) ? number_format($taxReportData['purchases_credit_notes']['grand_total'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses Card -->
        <div class="card mt-4" id="expensesCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-receipt me-2 text-danger"></i> المصروفات</h6>
                        <div id="expensesInfo" class="text-muted">
                            @if(isset($taxReportData))
                                إجمالي: {{ $taxReportData['expenses']['count'] ?? 0 }} قيد
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم القيد</th>
                                    <th>التاريخ</th>
                                    <th>المورد/البيان</th>
                                    <th>الرقم الضريبي</th>
                                    <th>المبلغ قبل الضريبة</th>
                                    <th>الضريبة</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="expensesTableBody">
                                <!-- Expenses data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        @include('account::report.accountGeneral.partials.tax_pagination', ['type' => 'expenses'])
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #fff3cd; font-weight: bold;">
                                    <td colspan="4"><strong>المجموع</strong></td>
                                    <td class="text-danger">{{ isset($taxReportData) ? number_format($taxReportData['expenses']['total'] ?? 0, 2) : '0.00' }}</td>
                                    <td class="text-danger">{{ isset($taxReportData) ? number_format($taxReportData['expenses']['tax'] ?? 0, 2) : '0.00' }}</td>
                                    <td class="text-danger">{{ isset($taxReportData) ? number_format($taxReportData['expenses']['grand_total'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tax Summary Section - Compact Design -->
        <div class="card mt-3" id="taxSummaryCard">
            <div class="card-header py-2 bg-light">
                <h5 class="mb-0"><i class="fa fa-calculator me-1"></i> ملخص الضرائب</h5>
            </div>
            <div class="card-body py-3">
                <div class="row">
                    <!-- ضريبة المخرجات (المحصلة) -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="border rounded p-3 h-100" style="border-color: #28a745 !important; border-width: 2px !important;">
                            <h6 class="text-muted mb-2"><i class="feather icon-arrow-up"></i> ضريبة المخرجات</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="py-1">المبيعات</td>
                                    <td class="py-1 text-left" id="summarySalesTax">{{ isset($taxReportData) ? number_format($taxReportData['sales']['tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1">+ إشعارات مدينة</td>
                                    <td class="py-1 text-left" id="summarySalesDebitNotesTax">{{ isset($taxReportData) ? number_format($taxReportData['sales_debit_notes']['tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-danger">- مردودات</td>
                                    <td class="py-1 text-left text-danger" id="summaryReturnsTax">{{ isset($taxReportData) ? number_format($taxReportData['returns']['tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="py-1 font-weight-bold">الإجمالي</td>
                                    <td class="py-1 text-left font-weight-bold text-success" id="summaryTotalOutputTax">{{ isset($taxReportData) ? number_format($taxReportData['summary']['total_output_tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- ضريبة المدخلات (المدفوعة) -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="border rounded p-3 h-100" style="border-color: #ffc107 !important; border-width: 2px !important;">
                            <h6 class="text-muted mb-2"><i class="feather icon-arrow-down"></i> ضريبة المدخلات</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="py-1">المشتريات</td>
                                    <td class="py-1 text-left" id="summaryPurchasesTax">{{ isset($taxReportData) ? number_format($taxReportData['purchases']['tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1">+ المصروفات</td>
                                    <td class="py-1 text-left" id="summaryExpensesTax">{{ isset($taxReportData) ? number_format($taxReportData['expenses']['tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-danger">- مردودات</td>
                                    <td class="py-1 text-left text-danger" id="summaryPurchaseReturnsTax">{{ isset($taxReportData) ? number_format($taxReportData['purchase_returns']['tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-1 text-danger">- إشعارات دائنة</td>
                                    <td class="py-1 text-left text-danger" id="summaryPurchasesCreditNotesTax">{{ isset($taxReportData) ? number_format($taxReportData['purchases_credit_notes']['tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="py-1 font-weight-bold">الإجمالي</td>
                                    <td class="py-1 text-left font-weight-bold text-warning" id="summaryTotalInputTax">{{ isset($taxReportData) ? number_format($taxReportData['summary']['total_input_tax'] ?? 0, 2) : '0.00' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- الصافي المستحق -->
                    <div class="col-lg-4 col-md-12 mb-3">
                        <div class="border rounded p-3 h-100 bg-primary text-white" style="border-width: 2px !important;">
                            <h6 class="text-white-50 mb-2"><i class="feather icon-dollar-sign"></i> صافي الضريبة</h6>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span>ضريبة المخرجات</span>
                                <span id="summaryOutputDisplay">{{ isset($taxReportData) ? number_format($taxReportData['summary']['total_output_tax'] ?? 0, 2) : '0.00' }}</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span>- ضريبة المدخلات</span>
                                <span id="summaryInputDisplay">{{ isset($taxReportData) ? number_format($taxReportData['summary']['total_input_tax'] ?? 0, 2) : '0.00' }}</span>
                            </div>
                            <hr class="my-2 bg-white-50">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="font-weight-bold">المستحق للهيئة</span>
                                <h4 class="mb-0 text-white" id="summaryNetTax">{{ isset($taxReportData) ? number_format($taxReportData['summary']['net_tax_due'] ?? 0, 2) : '0.00' }}</h4>
                            </div>
                            @php
                                $netTax = $taxReportData['summary']['net_tax_due'] ?? 0;
                            @endphp
                            <small class="text-white-50 d-block mt-1">
                                @if($netTax > 0)
                                    <i class="feather icon-alert-circle"></i> مبلغ مستحق للسداد
                                @elseif($netTax < 0)
                                    <i class="feather icon-check-circle"></i> رصيد دائن لصالحك
                                @else
                                    <i class="feather icon-minus-circle"></i> لا يوجد مستحقات
                                @endif
                            </small>
                        </div>
                    </div>
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
        // Pagination configuration
        const ITEMS_PER_PAGE = 25;
        
        // Store all data - based on Journal Entries
        const allData = {
            sales: @json(isset($taxReportData['sales']['invoices']) ? $taxReportData['sales']['invoices'] : []),
            salesDebitNotes: @json(isset($taxReportData['sales_debit_notes']['invoices']) ? $taxReportData['sales_debit_notes']['invoices'] : []),
            returns: @json(isset($taxReportData['returns']['invoices']) ? $taxReportData['returns']['invoices'] : []),
            purchases: @json(isset($taxReportData['purchases']['invoices']) ? $taxReportData['purchases']['invoices'] : []),
            purchaseReturns: @json(isset($taxReportData['purchase_returns']['invoices']) ? $taxReportData['purchase_returns']['invoices'] : []),
            purchasesCreditNotes: @json(isset($taxReportData['purchases_credit_notes']['invoices']) ? $taxReportData['purchases_credit_notes']['invoices'] : []),
            expenses: @json(isset($taxReportData['expenses']['invoices']) ? $taxReportData['expenses']['invoices'] : [])
        };
        
        // Current page tracking
        const currentPage = {
            sales: 1,
            salesDebitNotes: 1,
            returns: 1,
            purchases: 1,
            purchaseReturns: 1,
            purchasesCreditNotes: 1,
            expenses: 1
        };
        
        $(document).ready(function() {
            // Initialize pagination
            initPagination();
            
            // Filter button
            $('#filterBtn').click(function() {
                $('#reportForm').submit();
            });

            // Reset button
            $('#resetBtn').click(function() {
                $('#reportForm')[0].reset();
                $('#from_date').val('{{ now()->subDays(30)->format('Y-m-d') }}');
                $('#to_date').val('{{ now()->format('Y-m-d') }}');
                $('#reportForm').submit();
            });

            // Export to Excel
            $('#exportExcel').on('click', function(e) {
                e.preventDefault();
                exportTaxReport();
            });

            // Print
            $('#printBtn').on('click', function(e) {
                e.preventDefault();
                printTaxReport();
            });
        });
        
        // Initialize pagination for all tables
        function initPagination() {
            const tableTypes = ['sales', 'salesDebitNotes', 'returns', 'purchases', 'purchaseReturns', 'purchasesCreditNotes', 'expenses'];
            tableTypes.forEach(t => renderTablePage(t));
        }
        
        // Render a specific page for a table
        function renderTablePage(tableType) {
            const data = allData[tableType] || [];
            const page = currentPage[tableType];
            const totalPages = Math.max(1, Math.ceil(data.length / ITEMS_PER_PAGE));

            // Calculate start and end indices
            const startIndex = (page - 1) * ITEMS_PER_PAGE;
            const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, data.length);
            const pageData = data.slice(startIndex, endIndex);

            // Map tableType to actual HTML element ID
            const tableIdMap = {
                'sales': 'salesTableBody',
                'salesDebitNotes': 'salesDebitNotesTableBody',
                'returns': 'returnsTableBody',
                'purchases': 'purchasesTableBody',
                'purchaseReturns': 'purchaseReturnsTableBody',
                'purchasesCreditNotes': 'purchasesCreditNotesTableBody',
                'expenses': 'expensesTableBody'
            };

            // Render table rows
            const tableBody = $(`#${tableIdMap[tableType]}`);
            tableBody.empty();

            if (pageData.length === 0) {
                tableBody.append(`
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="bg-royal-linear alert text-center" role="alert">
                                <i class="fa fa-info-circle me-2"></i>
                                <p class="mb-0">لا توجد قيود في الفترة المحددة</p>
                            </div>
                        </td>
                    </tr>
                `);
            } else {
                pageData.forEach(entry => {
                    const row = createTableRow(entry, tableType);
                    tableBody.append(row);
                });
            }

            // Update pagination controls for this table
            updatePaginationControls(tableType, page, totalPages, data.length);
        }
        
        // Create a table row based on journal entry data
        function createTableRow(entry, tableType) {
            let amountClass = 'text-dark fw-bold';
            
            if (tableType === 'sales' || tableType === 'salesDebitNotes') {
                amountClass = 'text-success fw-bold';
            } else if (tableType === 'returns') {
                amountClass = 'text-danger fw-bold';
            } else if (tableType === 'purchases' || tableType === 'purchaseReturns' || tableType === 'purchasesCreditNotes') {
                amountClass = 'text-warning fw-bold';
            } else if (tableType === 'expenses') {
                amountClass = 'text-danger fw-bold';
            }
            
            // Determine entity name column (client for sales, supplier for purchases/expenses)
            let entityName = entry.name || '-';
            let taxNumber = entry.tax_number || '-';
            
            return `
                <tr>
                    <td><strong>${entry.code || '-'}</strong></td>
                    <td>${entry.date || '-'}</td>
                    <td>${entityName}</td>
                    <td>${taxNumber}</td>
                    <td class="${amountClass}">${formatNumber(entry.subtotal)}</td>
                    <td class="text-primary fw-bold">${formatNumber(entry.tax)}</td>
                    <td class="text-dark fw-bold">${formatNumber(entry.grand_total)}</td>
                </tr>
            `;
        }
        
        // Update pagination controls
        function updatePaginationControls(tableType, currentPageNum, totalPages, totalItems) {
            const paginationDiv = $(`#${tableType}Pagination`);
            const pageInfo = $(`#${tableType}PageInfo`);

            // Show/hide pagination controls (use css to override any inline display rules)
            if (totalPages <= 1) {
                paginationDiv.css('display', 'none');
                return;
            } else {
                paginationDiv.css('display', 'flex');
            }

            // Update page info
            const startIndex = totalItems === 0 ? 0 : (currentPageNum - 1) * ITEMS_PER_PAGE + 1;
            const endIndex = Math.min(currentPageNum * ITEMS_PER_PAGE, totalItems);
            pageInfo.text(`عرض ${startIndex} إلى ${endIndex} من ${totalItems} نتيجة`);

            // Update current page number
            $(`#${tableType}CurrentPage`).text(`${currentPageNum} / ${totalPages}`);
            
            // Update button states and links
            // First page
            if (currentPageNum === 1) {
                $(`#${tableType}FirstPageItem`).addClass('disabled');
                $(`#${tableType}PrevPageItem`).addClass('disabled');
            } else {
                $(`#${tableType}FirstPageItem`).removeClass('disabled');
                $(`#${tableType}PrevPageItem`).removeClass('disabled');
            }
            
            // Next and last page
            if (currentPageNum === totalPages) {
                $(`#${tableType}NextPageItem`).addClass('disabled');
                $(`#${tableType}LastPageItem`).addClass('disabled');
            } else {
                $(`#${tableType}NextPageItem`).removeClass('disabled');
                $(`#${tableType}LastPageItem`).removeClass('disabled');
            }
            
            // Bind pagination events
            bindPaginationEvents(tableType, currentPageNum, totalPages);
        }
        
        // Bind pagination events
        function bindPaginationEvents(tableType, currentPageNum, totalPages) {
            // First page
            $(`#${tableType}FirstPage`).off('click').on('click', function(e) {
                e.preventDefault();
                if (currentPageNum > 1) {
                    currentPage[tableType] = 1;
                    renderTablePage(tableType);
                }
            });
            
            // Previous page
            $(`#${tableType}PrevPage`).off('click').on('click', function(e) {
                e.preventDefault();
                if (currentPageNum > 1) {
                    currentPage[tableType]--;
                    renderTablePage(tableType);
                }
            });
            
            // Next page
            $(`#${tableType}NextPage`).off('click').on('click', function(e) {
                e.preventDefault();
                if (currentPageNum < totalPages) {
                    currentPage[tableType]++;
                    renderTablePage(tableType);
                }
            });
            
            // Last page
            $(`#${tableType}LastPage`).off('click').on('click', function(e) {
                e.preventDefault();
                if (currentPageNum < totalPages) {
                    currentPage[tableType] = totalPages;
                    renderTablePage(tableType);
                }
            });
        }

        // Format number
        function formatNumber(number) {
            return parseFloat(number || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // =====================================
        // تصدير Excel باستخدام الملف المساعد
        // =====================================
        function exportTaxReport() {
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();
            const branchName = '{{ request("branch") ? ($branches->find(request("branch"))->name ?? "جميع الفروع") : "جميع الفروع" }}';
            
            // الإحصائيات
            const statistics = {
                sales_tax: {{ $taxReportData['sales']['tax'] ?? 0 }},
                sales_debit_notes_tax: {{ $taxReportData['sales_debit_notes']['tax'] ?? 0 }},
                returns_tax: {{ $taxReportData['returns']['tax'] ?? 0 }},
                purchases_tax: {{ $taxReportData['purchases']['tax'] ?? 0 }},
                purchase_returns_tax: {{ $taxReportData['purchase_returns']['tax'] ?? 0 }},
                purchases_credit_notes_tax: {{ $taxReportData['purchases_credit_notes']['tax'] ?? 0 }},
                expenses_tax: {{ $taxReportData['expenses']['tax'] ?? 0 }},
                total_output_tax: {{ $taxReportData['summary']['total_output_tax'] ?? 0 }},
                total_input_tax: {{ $taxReportData['summary']['total_input_tax'] ?? 0 }},
                net_tax: {{ $taxReportData['summary']['net_tax_due'] ?? 0 }}
            };
            
            // جمع بيانات المبيعات
            const tableData = [];
            let rowNumber = 1;
            
            // قسم المبيعات
            tableData.push({
                values: ['', '📊 ضريبة المبيعات', '', '', ''],
                mergeFrom: 1,
                mergeTo: 5,
                alignments: ['center', 'center', 'center', 'center', 'center'],
                cellStyles: [{ bold: true, color: 'success' }, { bold: true, color: 'success' }, { bold: true, color: 'success' }, { bold: true, color: 'success' }, { bold: true, color: 'success' }]
            });
            
            allData.sales.forEach((entry) => {
                tableData.push({
                    values: [
                        rowNumber++,
                        entry.date || '',
                        entry.code || '',
                        entry.name || '',
                        formatNumber(entry.tax || 0)
                    ],
                    alignments: ['center', 'center', 'center', 'right', 'center'],
                    cellStyles: [null, null, null, null, { color: 'success', bold: true }]
                });
            });
            
            tableData.push({
                values: ['', '', '', 'الإجمالي', formatNumber(statistics.sales_tax)],
                alignments: ['center', 'center', 'center', 'right', 'center'],
                cellStyles: [null, null, null, { bold: true }, { bold: true, color: 'success' }]
            });
            
            tableData.push({ values: ['', '', '', '', ''], alignments: ['center', 'center', 'center', 'center', 'center'] });
            
            // قسم المرتجعات
            tableData.push({
                values: ['', '↩️ ضريبة المرتجعات', '', '', ''],
                mergeFrom: 1,
                mergeTo: 5,
                alignments: ['center', 'center', 'center', 'center', 'center'],
                cellStyles: [{ bold: true, color: 'danger' }, { bold: true, color: 'danger' }, { bold: true, color: 'danger' }, { bold: true, color: 'danger' }, { bold: true, color: 'danger' }]
            });
            
            allData.returns.forEach((entry) => {
                tableData.push({
                    values: [
                        rowNumber++,
                        entry.date || '',
                        entry.code || '',
                        entry.name || '',
                        formatNumber(entry.tax || 0)
                    ],
                    alignments: ['center', 'center', 'center', 'right', 'center'],
                    cellStyles: [null, null, null, null, { color: 'danger', bold: true }]
                });
            });
            
            tableData.push({
                values: ['', '', '', 'الإجمالي', formatNumber(statistics.returns_tax)],
                alignments: ['center', 'center', 'center', 'right', 'center'],
                cellStyles: [null, null, null, { bold: true }, { bold: true, color: 'danger' }]
            });
            
            tableData.push({ values: ['', '', '', '', ''], alignments: ['center', 'center', 'center', 'center', 'center'] });
            
            // قسم المشتريات
            tableData.push({
                values: ['', '🛒 ضريبة المشتريات', '', '', ''],
                mergeFrom: 1,
                mergeTo: 5,
                alignments: ['center', 'center', 'center', 'center', 'center'],
                cellStyles: [{ bold: true, color: 'warning' }, { bold: true, color: 'warning' }, { bold: true, color: 'warning' }, { bold: true, color: 'warning' }, { bold: true, color: 'warning' }]
            });
            
            allData.purchases.forEach((entry) => {
                tableData.push({
                    values: [
                        rowNumber++,
                        entry.date || '',
                        entry.code || '',
                        entry.name || '',
                        formatNumber(entry.tax || 0)
                    ],
                    alignments: ['center', 'center', 'center', 'right', 'center'],
                    cellStyles: [null, null, null, null, { color: 'warning', bold: true }]
                });
            });
            
            tableData.push({
                values: ['', '', '', 'الإجمالي', formatNumber(statistics.purchases_tax)],
                alignments: ['center', 'center', 'center', 'right', 'center'],
                cellStyles: [null, null, null, { bold: true }, { bold: true, color: 'warning' }]
            });
            
            // إعداد التقرير
            const config = {
                fileName: 'تقرير_الضرائب',
                mainTitle: 'تقرير الضرائب',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'الفترة': fromDate && toDate ? `من ${fromDate} إلى ${toDate}` : 'جميع الفترات',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                    'الفرع': branchName
                },

                statisticsCards: [
                    {
                        title: 'ضريبة المبيعات',
                        value: formatNumber(statistics.sales_tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'ضريبة المرتجعات',
                        value: formatNumber(statistics.returns_tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'ضريبة المشتريات',
                        value: formatNumber(statistics.purchases_tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'warning',
                        valueColor: 'warning',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: 'صافي الضريبة',
                        value: formatNumber(statistics.net_tax),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayBg',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'تفاصيل الضرائب',
                tableHeaders: ['#', 'التاريخ', 'رقم المرجع', 'الاسم', 'الضريبة ({{ \App\Support\CurrencySettings::symbol() }})'],
                columnWidths: [8, 15, 18, 39, 20],
                
                tableData: tableData,

                summaryRows: [
                    {
                        values: ['', '', '', 'صافي الضريبة المستحقة', formatNumber(statistics.net_tax)],
                        alignments: ['center', 'center', 'center', 'right', 'center'],
                        cellStyles: [null, null, null, { bold: true }, { bold: true, color: statistics.net_tax >= 0 ? 'primary' : 'danger' }]
                    }
                ],

                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'ملخص الضرائب',
                    tables: [
                        {
                            title: 'الإحصائيات',
                            headerColor: 'primary',
                            data: [
                                { label: 'ضريبة المبيعات', value: formatNumber(statistics.sales_tax), unit: ' ' + window.AppCurrency.symbol, color: 'success' },
                                { label: 'ضريبة المرتجعات', value: formatNumber(statistics.returns_tax), unit: ' ' + window.AppCurrency.symbol, color: 'danger' },
                                { label: 'ضريبة المشتريات', value: formatNumber(statistics.purchases_tax), unit: ' ' + window.AppCurrency.symbol, color: 'warning' },
                                { label: 'صافي الضريبة', value: formatNumber(statistics.net_tax), unit: ' ' + window.AppCurrency.symbol, color: 'primary' }
                            ]
                        }
                    ]
                },

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير تقرير الضرائب بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            // استدعاء دالة التصدير
            exportToExcelProfessional(config);
        }
        
        // =====================================
        // الطباعة
        // =====================================
        function printTaxReport() {
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();
            
            // جمع البيانات من جميع الجداول
            let salesRows = '';
            $('#salesTableBody tr').each(function() {
                if (!$(this).find('.alert').length) {
                    salesRows += $(this)[0].outerHTML;
                }
            });
            
            let returnsRows = '';
            $('#returnsTableBody tr').each(function() {
                if (!$(this).find('.alert').length) {
                    returnsRows += $(this)[0].outerHTML;
                }
            });
            
            let purchasesRows = '';
            $('#purchasesTableBody tr').each(function() {
                if (!$(this).find('.alert').length) {
                    purchasesRows += $(this)[0].outerHTML;
                }
            });
            
            // القيم من البطاقات
            const salesTax = $('#summarySalesTax').text();
            const returnsTax = $('#summaryReturnsTax').text();
            const purchasesTax = $('#summaryPurchasesTax').text();
            const netTax = $('#summaryNetTax').text();
            
            // فتح نافذة جديدة للطباعة
            const printWindow = window.open('', '', 'height=900,width=1200');
            
            const printContent = `
                <!DOCTYPE html>
                <html dir="rtl" lang="ar">
                <head>
                    <meta charset="UTF-8">
                    <title>تقرير الضرائب</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { font-family: 'Arial', 'Tahoma', sans-serif; padding: 25px; background-color: white; color: #333; line-height: 1.6; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #333; padding-bottom: 15px; }
                        .header h1 { font-size: 28px; color: #333; margin-bottom: 8px; font-weight: bold; }
                        .header p { color: #666; font-size: 13px; margin: 3px 0; }
                        .info-section { margin-bottom: 25px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }
                        .info-row { display: flex; justify-content: space-around; gap: 10px; flex-wrap: wrap; }
                        .info-item { flex: 1; min-width: 150px; text-align: center; padding: 10px; background: white; border-radius: 4px; }
                        .info-item label { display: block; font-size: 11px; color: #666; margin-bottom: 4px; }
                        .info-item value { display: block; font-size: 15px; font-weight: bold; color: #333; }
                        .section-title { font-size: 18px; font-weight: bold; color: #333; margin: 25px 0 15px 0; padding: 10px 15px; background-color: #f0f0f0; border-right: 4px solid #333; }
                        .section-title.sales { border-right-color: #28a745; background-color: #e8f5e9; }
                        .section-title.returns { border-right-color: #dc3545; background-color: #fce4e4; }
                        .section-title.purchases { border-right-color: #ffc107; background-color: #fff8e1; }
                        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #333; }
                        .table thead th { background-color: #333; color: white; padding: 12px 8px; text-align: center; font-weight: bold; border: 1px solid #333; font-size: 13px; }
                        .table tbody td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; font-size: 12px; }
                        .table tbody tr:nth-child(even) { background-color: #f8f9fa; }
                        .table tfoot tr { font-weight: bold; background-color: #e9ecef; }
                        .table tfoot td { padding: 12px 8px; border: 1px solid #333; }
                        .text-success { color: #28a745 !important; font-weight: bold; }
                        .text-danger { color: #dc3545 !important; font-weight: bold; }
                        .text-warning { color: #ffc107 !important; font-weight: bold; }
                        .text-primary { color: #007bff !important; font-weight: bold; }
                        .fw-bold { font-weight: bold !important; }
                        .summary-box { margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #333; border-radius: 8px; }
                        .summary-title { text-align: center; font-size: 20px; font-weight: bold; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #333; }
                        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
                        .summary-card { background-color: white; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6; text-align: center; }
                        .summary-card h6 { font-size: 11px; color: #666; margin-bottom: 8px; }
                        .summary-card h4 { font-size: 18px; font-weight: bold; margin: 0; }
                        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #333; text-align: center; color: #666; font-size: 11px; }
                        @media print {
                            body { margin: 0; padding: 15px; }
                            .header, .section-title { page-break-after: avoid; }
                            tr { page-break-inside: avoid; }
                            thead { display: table-header-group; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>تقرير الضرائب</h1>
                        <p>نظام المحاسبة المتكامل</p>
                        ${fromDate && toDate ? `<p>الفترة: من ${fromDate} إلى ${toDate}</p>` : ''}
                        <p>تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} - ${new Date().toLocaleTimeString('ar-SA')}</p>
                    </div>
                    
                    <div class="info-section">
                        <div class="info-row">
                            <div class="info-item"><label>ضريبة المبيعات</label><value class="text-success">${salesTax}</value></div>
                            <div class="info-item"><label>ضريبة المرتجعات</label><value class="text-danger">${returnsTax}</value></div>
                            <div class="info-item"><label>ضريبة المشتريات</label><value class="text-warning">${purchasesTax}</value></div>
                            <div class="info-item"><label>صافي الضريبة</label><value class="text-primary">${netTax}</value></div>
                        </div>
                    </div>
                    
                    <h3 class="section-title sales">📊 ضريبة المبيعات</h3>
                    <table class="table">
                        <thead><tr><th>#</th><th>التاريخ</th><th>رقم المرجع</th><th>العميل</th><th>الضريبة ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
                        <tbody>${salesRows || '<tr><td colspan="5">لا توجد بيانات</td></tr>'}</tbody>
                    </table>
                    
                    <h3 class="section-title returns">↩️ ضريبة المرتجعات</h3>
                    <table class="table">
                        <thead><tr><th>#</th><th>التاريخ</th><th>رقم المرجع</th><th>العميل</th><th>الضريبة ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
                        <tbody>${returnsRows || '<tr><td colspan="5">لا توجد بيانات</td></tr>'}</tbody>
                    </table>
                    
                    <h3 class="section-title purchases">🛒 ضريبة المشتريات</h3>
                    <table class="table">
                        <thead><tr><th>#</th><th>التاريخ</th><th>رقم المرجع</th><th>المورد</th><th>الضريبة ({{ \App\Support\CurrencySettings::symbol() }})</th></tr></thead>
                        <tbody>${purchasesRows || '<tr><td colspan="5">لا توجد بيانات</td></tr>'}</tbody>
                    </table>
                    
                    <div class="summary-box">
                        <div class="summary-title">📊 ملخص الضرائب</div>
                        <div class="summary-grid">
                            <div class="summary-card"><h6>ضريبة المبيعات</h6><h4 class="text-success">${salesTax}</h4></div>
                            <div class="summary-card"><h6>ضريبة المرتجعات</h6><h4 class="text-danger">${returnsTax}</h4></div>
                            <div class="summary-card"><h6>ضريبة المشتريات</h6><h4 class="text-warning">${purchasesTax}</h4></div>
                            <div class="summary-card"><h6>صافي الضريبة</h6><h4 class="text-primary">${netTax}</h4></div>
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
            
            setTimeout(function() {
                printWindow.focus();
                printWindow.print();
            }, 500);
        }
    </script>
@endsection