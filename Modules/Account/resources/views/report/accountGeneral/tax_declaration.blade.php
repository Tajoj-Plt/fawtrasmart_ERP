@extends('account::master')

@section('title')
    تقرير الاقرار الضريبي
@stop

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير الاقرار الضريبي',
        'crumbs' => [
            'الرئيسية' => null,
            'تقرير الاقرار الضريبي' => null,
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
                    @php
                        $f = $appliedFilters ?? [];
                        $defaultFrom = $f['from_date'] ?? now()->subDays(30)->format('Y-m-d');
                        $defaultTo   = $f['to_date']   ?? now()->format('Y-m-d');
                    @endphp
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.taxDeclaration') }}">
                        <div class="form-body row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'form-group col-md-3',
                            ])

                            <div class="form-group col-md-3">
                                <label>من تاريخ</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $defaultFrom }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label>إلى تاريخ</label>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $defaultTo }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label>نوع الفترة</label>
                                <select name="date_type" id="date_type" class="form-control">
                                    @php $dt = $f['date_type'] ?? 'custom'; @endphp
                                    <option value="custom"        {{ $dt == 'custom' ? 'selected' : '' }}>مخصص</option>
                                    <option value="today"         {{ $dt == 'today' ? 'selected' : '' }}>اليوم</option>
                                    <option value="yesterday"     {{ $dt == 'yesterday' ? 'selected' : '' }}>أمس</option>
                                    <option value="this_week"     {{ $dt == 'this_week' ? 'selected' : '' }}>هذا الأسبوع</option>
                                    <option value="last_week"     {{ $dt == 'last_week' ? 'selected' : '' }}>الأسبوع الماضي</option>
                                    <option value="this_month"    {{ $dt == 'this_month' ? 'selected' : '' }}>هذا الشهر</option>
                                    <option value="last_month"    {{ $dt == 'last_month' ? 'selected' : '' }}>الشهر الماضي</option>
                                    <option value="this_quarter"  {{ $dt == 'this_quarter' ? 'selected' : '' }}>هذا الربع</option>
                                    <option value="last_quarter"  {{ $dt == 'last_quarter' ? 'selected' : '' }}>الربع الماضي</option>
                                    <option value="this_year"     {{ $dt == 'this_year' ? 'selected' : '' }}>هذا العام</option>
                                    <option value="last_year"     {{ $dt == 'last_year' ? 'selected' : '' }}>العام الماضي</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label>الفرع</label>
                                <select name="branch" id="branch" class="form-control">
                                    <option value="">جميع الفروع</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ ($f['branch'] ?? '') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Hidden Div للبحث المتقدم -->
                        <div class="collapse {{ !empty($f['reference_number']) || !empty($f['search']) || (!empty($f['currency']) && $f['currency'] != \App\Support\CurrencySettings::code()) ? 'show' : '' }}" id="advancedSearchForm">
                            <div class="form-body row">
                                <div class="form-group col-md-3">
                                    @include('account::components.currency-select', [
                                        'name' => 'currency',
                                        'label' => 'العملة',
                                        'selected' => $f['currency'] ?? \App\Support\CurrencySettings::code(),
                                    ])
                                </div>
                                <div class="form-group col-md-3">
                                    <label>رقم القيد</label>
                                    <input type="text" name="reference_number" id="reference_number" class="form-control" placeholder="رقم القيد..." value="{{ $f['reference_number'] ?? '' }}">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>بحث (اسم العميل/المورد، الوصف، رقم القيد)</label>
                                    <input type="text" name="search" id="search" class="form-control" placeholder="اكتب اسم العميل/المورد أو وصف القيد..." value="{{ $f['search'] ?? '' }}">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">
                                <i class="fa fa-search"></i> بحث
                            </button>
                            <a class="btn btn-outline-secondary ml-2 mr-2" data-bs-toggle="collapse" data-bs-target="#advancedSearchForm">
                                <i class="fa fa-sliders-h"></i> بحث متقدم
                            </a>
                            <button type="button" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">
                                <i class="fa fa-times"></i> إلغاء الفلترة
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
                <span class="stk-metric__label">إجمالي المبيعات (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxData) ? number_format($taxData['totals']['total_sales'], 2) : '0.00' }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">إجمالي المشتريات (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxData) ? number_format($taxData['totals']['total_purchases'], 2) : '0.00' }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__label">ضريبة المخرجات (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxData) ? number_format($taxData['totals']['total_tax_output'], 2) : '0.00' }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__label">ضريبة المدخلات (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxData) ? number_format($taxData['totals']['total_tax_input'], 2) : '0.00' }}</span>
            </div>
        </div>
    <br>
        <!-- Additional Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calculator"></i></span>
                <span class="stk-metric__label">صافي الضريبة المستحقة (ريال)</span>
                <span class="stk-metric__value">{{ isset($taxData) ? number_format($taxData['totals']['net_tax_due'], 2) : '0.00' }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">إجمالي عدد الفواتير</span>
                <span class="stk-metric__value">{{ isset($taxData) ? $taxData['totals']['total_invoices'] : '0' }}</span>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="stk-table-head__title">التوزيع الضريبي للمبيعات والمشتريات</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع ضريبة المخرجات (المبيعات)</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="salesTaxChart"></canvas>
                                    <div id="salesChartNoData" class="text-center py-5" style="display: none;">
                                        <i class="feather icon-pie-chart text-muted" style="font-size: 48px;"></i>
                                        <p class="text-muted mt-2">لا توجد بيانات ضريبية للمبيعات</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع ضريبة المدخلات (المشتريات)</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="purchasesTaxChart"></canvas>
                                    <div id="purchasesChartNoData" class="text-center py-5" style="display: none;">
                                        <i class="feather icon-pie-chart text-muted" style="font-size: 48px;"></i>
                                        <p class="text-muted mt-2">لا توجد بيانات ضريبية للمشتريات</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- شرح محتوى الرسوم البيانية -->
                        <div class="row mt-3">
                            <div class="col-lg-6">
                                <div class="alert alert-light border">
                                    <small class="text-muted">
                                        <strong>ضريبة المخرجات:</strong><br>
                                        • <span class="text-success">المبيعات:</span> ضريبة المبيعات العادية<br>
                                        • <span class="text-info">أخرى:</span> إشعارات المبيعات المدينة + مردودات المبيعات
                                    </small>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="alert alert-light border">
                                    <small class="text-muted">
                                        <strong>ضريبة المدخلات:</strong><br>
                                        • <span class="text-danger">المشتريات:</span> ضريبة المشتريات العادية<br>
                                        • <span class="text-warning">أخرى:</span> المصروفات + مردودات المشتريات + إشعارات دائنة
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card" id="reportContainer">
            <div class="card-content">
                <div class="card-body">
                    <h6 class="stk-table-head__title">ملخص الاقرار الضريبي</h6>
                    <div class="table-responsive">
                        <div class="row">
                            <!-- Sales Tax Table -->
                            <div class="col-md-6">
                                <h6 class="p-3 mb-0">
                                    <i class="fa fa-arrow-up me-2 text-success"></i>
                                    فواتير المبيعات
                                </h6>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>نوع الضريبة</th>
                                            <th>المبيعات</th>
                                            <th>الضريبة</th>
                                            <th>الإجمالي</th>
                                        </tr>
                                    </thead>
                                    <tbody id="salesTaxTableBody">
                                        @if(isset($taxData) && !empty($taxData['sales_tax_declaration']) && count($taxData['sales_tax_declaration']) > 0)
                                            @foreach($taxData['sales_tax_declaration'] as $item)
                                                <tr>
                                                    <td><strong>{{ $item['tax_name'] ?? 'غير محدد' }}</strong></td>
                                                    <td class="text-success fw-bold">{{ number_format($item['base_amount'] ?? 0, 2) }}</td>
                                                    <td class="text-primary fw-bold">{{ number_format($item['tax_amount'] ?? 0, 2) }}</td>
                                                    <td class="text-dark fw-bold">{{ number_format($item['total_amount'] ?? 0, 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td><strong>الإجمالي</strong></td>
                                                <td>{{ number_format(array_sum(array_column($taxData['sales_tax_declaration']->toArray(), 'base_amount')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($taxData['sales_tax_declaration']->toArray(), 'tax_amount')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($taxData['sales_tax_declaration']->toArray(), 'total_amount')), 2) }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="4" class="text-center py-5">
                                                    <div class="bg-royal-linear alert text-center" role="alert">
                                                        <i class="fa fa-info-circle me-2"></i>
                                                        <p class="mb-0">لا توجد فواتير مبيعات في الفترة المحددة</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <!-- Purchases Tax Table -->
                            <div class="col-md-6">
                                <h6 class="p-3 mb-0">
                                    <i class="fa fa-arrow-down me-2 text-danger"></i>
                                    فواتير المشتريات
                                </h6>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>نوع الضريبة</th>
                                            <th>المشتريات</th>
                                            <th>الضريبة</th>
                                            <th>الإجمالي</th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchasesTaxTableBody">
                                        @if(isset($taxData) && !empty($taxData['purchases_tax_declaration']) && count($taxData['purchases_tax_declaration']) > 0)
                                            @foreach($taxData['purchases_tax_declaration'] as $item)
                                                <tr>
                                                    <td><strong>{{ $item['tax_name'] ?? 'غير محدد' }}</strong></td>
                                                    <td class="text-warning fw-bold">{{ number_format($item['base_amount'] ?? 0, 2) }}</td>
                                                    <td class="text-primary fw-bold">{{ number_format($item['tax_amount'] ?? 0, 2) }}</td>
                                                    <td class="text-dark fw-bold">{{ number_format($item['total_amount'] ?? 0, 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td><strong>الإجمالي</strong></td>
                                                <td>{{ number_format(array_sum(array_column($taxData['purchases_tax_declaration']->toArray(), 'base_amount')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($taxData['purchases_tax_declaration']->toArray(), 'tax_amount')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($taxData['purchases_tax_declaration']->toArray(), 'total_amount')), 2) }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="4" class="text-center py-5">
                                                    <div class="bg-royal-linear alert text-center" role="alert">
                                                        <i class="fa fa-info-circle me-2"></i>
                                                        <p class="mb-0">لا توجد فواتير مشتريات في الفترة المحددة</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Other Tax Table - قسم أخرى -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="p-3 mb-0">
                                    <i class="fa fa-list-alt me-2 text-info"></i>
                                    أخرى (مصروفات، مردودات، إشعارات)
                                </h6>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>البند</th>
                                            <th>المبلغ الأساسي</th>
                                            <th>الضريبة</th>
                                            <th>الإجمالي</th>
                                            <th>النوع</th>
                                        </tr>
                                    </thead>
                                    <tbody id="otherTaxTableBody">
                                        @if(isset($taxData) && !empty($taxData['other_tax_declaration']) && count($taxData['other_tax_declaration']) > 0)
                                            @foreach($taxData['other_tax_declaration'] as $item)
                                                <tr>
                                                    <td><strong>{{ $item['tax_name'] ?? 'غير محدد' }}</strong></td>
                                                    <td class="fw-bold {{ $item['base_amount'] >= 0 ? 'text-info' : 'text-danger' }}">{{ number_format($item['base_amount'] ?? 0, 2) }}</td>
                                                    <td class="fw-bold {{ $item['tax_amount'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($item['tax_amount'] ?? 0, 2) }}</td>
                                                    <td class="text-dark fw-bold">{{ number_format($item['total_amount'] ?? 0, 2) }}</td>
                                                    <td>
                                                        @if(($item['type'] ?? '') == 'output')
                                                            <span class="badge bg-success">ضريبة مخرجات</span>
                                                        @else
                                                            <span class="badge bg-warning">ضريبة مدخلات</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td><strong>الإجمالي</strong></td>
                                                <td>{{ number_format(array_sum(array_column($taxData['other_tax_declaration']->toArray(), 'base_amount')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($taxData['other_tax_declaration']->toArray(), 'tax_amount')), 2) }}</td>
                                                <td>{{ number_format(array_sum(array_column($taxData['other_tax_declaration']->toArray(), 'total_amount')), 2) }}</td>
                                                <td></td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td colspan="5" class="text-center py-5">
                                                    <div class="bg-royal-linear alert text-center" role="alert">
                                                        <i class="fa fa-info-circle me-2"></i>
                                                        <p class="mb-0">لا توجد عمليات أخرى في الفترة المحددة</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tax Summary Section -->
                        <div class="p-4 mt-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                            <h5 class="text-center mb-4">
                                <i class="fa fa-file-contract me-2"></i>
                                ملخص الاقرار الضريبي النهائي
                            </h5>
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">ضريبة المخرجات</h6>
                                        <h4 class="text-success mb-0" id="summaryTaxOutput">
                                            {{ isset($taxData) ? number_format($taxData['totals']['total_tax_output'], 2) : '0.00' }}
                                        </h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">ضريبة المدخلات</h6>
                                        <h4 class="text-warning mb-0" id="summaryTaxInput">
                                            {{ isset($taxData) ? number_format($taxData['totals']['total_tax_input'], 2) : '0.00' }}
                                        </h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">صافي الضريبة</h6>
                                        <h4 class="text-primary mb-0" id="summaryNetTax">
                                            {{ isset($taxData) ? number_format($taxData['totals']['net_tax_due'], 2) : '0.00' }}
                                        </h4>
                                        <small class="text-muted">ريال سعودي</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 bg-white shadow-sm">
                                        <h6 class="text-muted mb-1">الحالة</h6>
                                        <h5 class="mb-0" id="summaryStatus">
                                            @if(isset($taxData))
                                                @if($taxData['totals']['net_tax_due'] > 0)
                                                    <span class="badge bg-danger">مستحقة للدفع</span>
                                                @elseif($taxData['totals']['net_tax_due'] < 0)
                                                    <span class="badge bg-success">مبلغ مسترد</span>
                                                @else
                                                    <span class="badge bg-info">متوازن</span>
                                                @endif
                                            @else
                                                <span class="badge bg-info">مؤقت</span>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        let salesTaxChart = null;
        let purchasesTaxChart = null;

        // Wait for DOM and libraries to load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if jQuery is loaded
            if (typeof jQuery === 'undefined') {
                console.error('jQuery is not loaded');
                return;
            }

            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }

            // Initialize charts with server-side data
            @if(isset($taxData) && isset($taxData['sales_tax_data']) && isset($taxData['purchases_tax_data']))
                try {
                    var salesData = @json($taxData['sales_tax_data']);
                    var purchasesData = @json($taxData['purchases_tax_data']);
                    updateCharts(salesData, purchasesData);
                } catch (e) {
                    console.error('Error initializing charts:', e);
                }
            @else
                // Initialize with empty data
                updateCharts({labels: [], amounts: []}, {labels: [], amounts: []});
            @endif

            // Filter button
            $(document).on('click', '#filterBtn', function(e) {
                e.preventDefault();
                $('#reportForm').submit();
            });

            // Reset button
            $(document).on('click', '#resetBtn', function(e) {
                e.preventDefault();
                window.location.href = '{{ route("GeneralAccountReports.taxDeclaration") }}';
            });

            // Date type change - عند اختيار فترة جاهزة نعطل حقول التاريخ ونرسل للسيرفر
            $(document).on('change', '#date_type', function() {
                var t = $(this).val();
                var disabled = (t !== 'custom');
                $('#from_date').prop('disabled', disabled);
                $('#to_date').prop('disabled', disabled);
                if (disabled) {
                    setDateRange(t);
                }
            });

            // تطبيق الحالة عند تحميل الصفحة
            (function() {
                var t = $('#date_type').val();
                if (t && t !== 'custom') {
                    $('#from_date').prop('disabled', true);
                    $('#to_date').prop('disabled', true);
                }
            })();

            // Export to Excel
            $(document).on('click', '#exportExcel', function(e) {
                e.preventDefault();
                exportTaxDeclarationReport();
            });

            // Print
            $(document).on('click', '#printBtn', function(e) {
                e.preventDefault();
                printTaxDeclaration();
            });
        });

        // Update charts
        function updateCharts(salesData, purchasesData) {
            try {
                // التحقق من صحة البيانات
                if (!salesData || !salesData.amounts) {
                    salesData = {labels: [], amounts: []};
                }
                if (!purchasesData || !purchasesData.amounts) {
                    purchasesData = {labels: [], amounts: []};
                }

                // التحقق من وجود بيانات للمبيعات
                const salesAmounts = Array.isArray(salesData.amounts) ? salesData.amounts : [];
                const purchasesAmounts = Array.isArray(purchasesData.amounts) ? purchasesData.amounts : [];
                
                const salesTotal = salesAmounts.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
                const purchasesTotal = purchasesAmounts.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
                
                // Sales Tax Chart
                const salesCanvas = document.getElementById('salesTaxChart');
                const salesNoData = document.getElementById('salesChartNoData');
                
                if (!salesCanvas) {
                    console.error('Sales chart canvas not found');
                    return;
                }

                const salesCtx = salesCanvas.getContext('2d');
                
                if (salesTaxChart) {
                    salesTaxChart.destroy();
                    salesTaxChart = null;
                }

                if (salesTotal > 0 && salesData.labels && salesData.labels.length > 0) {
                    salesCanvas.style.display = 'block';
                    if (salesNoData) salesNoData.style.display = 'none';
                    
                    salesTaxChart = new Chart(salesCtx, {
                        type: 'doughnut',
                        data: {
                            labels: salesData.labels,
                            datasets: [{
                                data: salesAmounts,
                                backgroundColor: [
                                    'rgba(76, 175, 80, 0.8)',
                                    'rgba(33, 150, 243, 0.8)',
                                    'rgba(255, 193, 7, 0.8)',
                                    'rgba(156, 39, 176, 0.8)',
                                    'rgba(255, 87, 34, 0.8)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            var label = context.label || '';
                                            var value = context.parsed || 0;
                                            return label + ': ' + parseFloat(value).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ' + window.AppCurrency.symbol;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    salesCanvas.style.display = 'none';
                    if (salesNoData) salesNoData.style.display = 'block';
                }

                // Purchases Tax Chart
                const purchasesCanvas = document.getElementById('purchasesTaxChart');
                const purchasesNoData = document.getElementById('purchasesChartNoData');
                
                if (!purchasesCanvas) {
                    console.error('Purchases chart canvas not found');
                    return;
                }

                const purchasesCtx = purchasesCanvas.getContext('2d');
                
                if (purchasesTaxChart) {
                    purchasesTaxChart.destroy();
                    purchasesTaxChart = null;
                }

                if (purchasesTotal > 0 && purchasesData.labels && purchasesData.labels.length > 0) {
                    purchasesCanvas.style.display = 'block';
                    if (purchasesNoData) purchasesNoData.style.display = 'none';
                    
                    purchasesTaxChart = new Chart(purchasesCtx, {
                        type: 'doughnut',
                        data: {
                            labels: purchasesData.labels,
                            datasets: [{
                                data: purchasesAmounts,
                                backgroundColor: [
                                    'rgba(244, 67, 54, 0.8)',
                                    'rgba(255, 152, 0, 0.8)',
                                    'rgba(96, 125, 139, 0.8)',
                                    'rgba(121, 85, 72, 0.8)',
                                    'rgba(158, 158, 158, 0.8)'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            var label = context.label || '';
                                            var value = context.parsed || 0;
                                            return label + ': ' + parseFloat(value).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ' + window.AppCurrency.symbol;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    purchasesCanvas.style.display = 'none';
                    if (purchasesNoData) purchasesNoData.style.display = 'block';
                }
            } catch (error) {
                console.error('Error updating charts:', error);
            }
        }

        // Set date range based on type
        function setDateRange(type) {
            var today = new Date();
            var fromDate, toDate;

            switch(type) {
                case 'today':
                    fromDate = new Date();
                    toDate = new Date();
                    break;
                case 'yesterday':
                    fromDate = new Date();
                    fromDate.setDate(fromDate.getDate() - 1);
                    toDate = new Date(fromDate);
                    break;
                case 'this_week':
                    fromDate = new Date();
                    fromDate.setDate(fromDate.getDate() - fromDate.getDay());
                    toDate = new Date();
                    break;
                case 'last_week':
                    fromDate = new Date();
                    fromDate.setDate(fromDate.getDate() - fromDate.getDay() - 7);
                    toDate = new Date(fromDate);
                    toDate.setDate(toDate.getDate() + 6);
                    break;
                case 'this_month':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    toDate = new Date();
                    break;
                case 'last_month':
                    fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                case 'this_quarter':
                    fromDate = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1);
                    toDate = new Date();
                    break;
                case 'last_quarter':
                    fromDate = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3 - 3, 1);
                    toDate = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 0);
                    break;
                case 'this_year':
                    fromDate = new Date(today.getFullYear(), 0, 1);
                    toDate = new Date();
                    break;
                case 'last_year':
                    fromDate = new Date(today.getFullYear() - 1, 0, 1);
                    toDate = new Date(today.getFullYear() - 1, 11, 31);
                    break;
                default:
                    return;
            }

            document.getElementById('from_date').value = formatDateForInput(fromDate);
            document.getElementById('to_date').value = formatDateForInput(toDate);
        }

        // Format date for input field
        function formatDateForInput(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
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
        function exportTaxDeclarationReport() {
            // جمع الإحصائيات
            var totalSales = parseFloat($('#totalSales').text().replace(/,/g, '').trim()) || 0;
            var totalPurchases = parseFloat($('#totalPurchases').text().replace(/,/g, '').trim()) || 0;
            var totalTaxOutput = parseFloat($('#totalTaxOutput').text().replace(/,/g, '').trim()) || 0;
            var totalTaxInput = parseFloat($('#totalTaxInput').text().replace(/,/g, '').trim()) || 0;
            var netTaxDue = parseFloat($('#netTaxDue').text().replace(/,/g, '').trim()) || 0;
            var totalInvoices = parseInt($('#totalInvoices').text().replace(/,/g, '').trim()) || 0;

            var fromDate = $('#from_date').val() || '';
            var toDate = $('#to_date').val() || '';
            var branchName = $('#branch option:selected').text() || 'جميع الفروع';
            var taxType = $('#tax_type option:selected').text() || 'جميع أنواع الضرائب';

            // جمع بيانات جدول المبيعات
            var salesTableData = [];
            $('#salesTaxTableBody tr').each(function() {
                var cells = $(this).find('td');
                if (cells.length >= 4 && !$(this).find('.alert').length) {
                    salesTableData.push({
                        values: [
                            $(cells[0]).text().trim(),
                            $(cells[1]).text().trim(),
                            $(cells[2]).text().trim(),
                            $(cells[3]).text().trim()
                        ],
                        cellStyles: [
                            { bold: true },
                            { color: 'success' },
                            { color: 'primary' },
                            { bold: true }
                        ]
                    });
                }
            });

            // جمع بيانات جدول المشتريات
            var purchasesTableData = [];
            $('#purchasesTaxTableBody tr').each(function() {
                var cells = $(this).find('td');
                if (cells.length >= 4 && !$(this).find('.alert').length) {
                    purchasesTableData.push({
                        values: [
                            $(cells[0]).text().trim(),
                            $(cells[1]).text().trim(),
                            $(cells[2]).text().trim(),
                            $(cells[3]).text().trim()
                        ],
                        cellStyles: [
                            { bold: true },
                            { color: 'warning' },
                            { color: 'primary' },
                            { bold: true }
                        ]
                    });
                }
            });

            // جمع بيانات جدول أخرى
            var otherTableData = [];
            $('#otherTaxTableBody tr').each(function() {
                var cells = $(this).find('td');
                if (cells.length >= 5 && !$(this).find('.alert').length) {
                    otherTableData.push({
                        values: [
                            $(cells[0]).text().trim(),
                            $(cells[1]).text().trim(),
                            $(cells[2]).text().trim(),
                            $(cells[3]).text().trim(),
                            $(cells[4]).text().trim()
                        ],
                        cellStyles: [
                            { bold: true },
                            null,
                            { color: 'primary' },
                            { bold: true },
                            null
                        ]
                    });
                }
            });

            // دمج البيانات في جدول واحد
            var tableData = [];
            
            // إضافة عنوان المبيعات
            tableData.push({
                values: ['فواتير المبيعات', '', '', '', ''],
                cellStyles: [{ bold: true, color: 'success' }],
                mergeFrom: 1,
                mergeTo: 5
            });
            
            // إضافة بيانات المبيعات
            salesTableData.forEach(function(row) {
                tableData.push({
                    values: [row.values[0], row.values[1], row.values[2], row.values[3], ''],
                    cellStyles: row.cellStyles
                });
            });

            // إضافة فاصل
            tableData.push({ values: ['', '', '', '', ''] });

            // إضافة عنوان المشتريات
            tableData.push({
                values: ['فواتير المشتريات', '', '', '', ''],
                cellStyles: [{ bold: true, color: 'danger' }],
                mergeFrom: 1,
                mergeTo: 5
            });

            // إضافة بيانات المشتريات
            purchasesTableData.forEach(function(row) {
                tableData.push({
                    values: [row.values[0], row.values[1], row.values[2], row.values[3], ''],
                    cellStyles: row.cellStyles
                });
            });

            // إضافة فاصل
            tableData.push({ values: ['', '', '', '', ''] });

            // إضافة عنوان أخرى
            tableData.push({
                values: ['أخرى (مصروفات، مردودات، إشعارات)', '', '', '', ''],
                cellStyles: [{ bold: true, color: 'primary' }],
                mergeFrom: 1,
                mergeTo: 5
            });

            // إضافة بيانات أخرى
            otherTableData.forEach(function(row) {
                tableData.push({
                    values: row.values,
                    cellStyles: row.cellStyles
                });
            });

            // إعداد التقرير
            var config = {
                fileName: 'تقرير_الاقرار_الضريبي',
                mainTitle: 'تقرير الاقرار الضريبي',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'الفترة': 'من ' + fromDate + ' إلى ' + toDate,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                    'الفرع': branchName,
                    'نوع الضريبة': taxType
                },

                statisticsCards: [
                    {
                        title: 'إجمالي المبيعات',
                        value: formatNumber(totalSales),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'إجمالي المشتريات',
                        value: formatNumber(totalPurchases),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'ضريبة المخرجات',
                        value: formatNumber(totalTaxOutput),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayLight',
                        borderColor: 'primary'
                    },
                    {
                        title: 'ضريبة المدخلات',
                        value: formatNumber(totalTaxInput),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'warning',
                        valueColor: 'warning',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    }
                ],

                tableTitle: 'ملخص الاقرار الضريبي',
                tableHeaders: ['البند', 'المبلغ الأساسي', 'الضريبة', 'الإجمالي', 'النوع'],
                columnWidths: [30, 18, 18, 18, 16],
                
                tableData: tableData,

                summaryRows: [
                    {
                        label: 'صافي الضريبة المستحقة',
                        values: ['صافي الضريبة المستحقة', '', '', formatNumber(netTaxDue) + ' ' + window.AppCurrency.symbol, netTaxDue > 0 ? 'مستحقة للدفع' : (netTaxDue < 0 ? 'مبلغ مسترد' : 'متوازن')],
                        color: netTaxDue > 0 ? 'danger' : 'success',
                        cellStyles: [{ bold: true }, null, null, { color: netTaxDue > 0 ? 'danger' : 'success', bold: true }, { bold: true }]
                    },
                    {
                        label: 'إجمالي عدد الفواتير',
                        values: ['إجمالي عدد الفواتير', '', '', totalInvoices.toString(), ''],
                        color: 'primary',
                        cellStyles: [{ bold: true }, null, null, { bold: true }, null]
                    }
                ],

                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'ملخص الاقرار الضريبي',
                    tables: [
                        {
                            title: 'إحصائيات المبيعات والمشتريات',
                            data: [
                                { label: 'إجمالي المبيعات', value: formatNumber(totalSales), unit: window.AppCurrency.symbol },
                                { label: 'إجمالي المشتريات', value: formatNumber(totalPurchases), unit: window.AppCurrency.symbol },
                                { label: 'ضريبة المخرجات', value: formatNumber(totalTaxOutput), unit: window.AppCurrency.symbol },
                                { label: 'ضريبة المدخلات', value: formatNumber(totalTaxInput), unit: window.AppCurrency.symbol }
                            ]
                        },
                        {
                            title: 'صافي الضريبة',
                            data: [
                                { label: 'صافي الضريبة المستحقة', value: formatNumber(netTaxDue), unit: window.AppCurrency.symbol, highlight: true },
                                { label: 'إجمالي عدد الفواتير', value: totalInvoices.toString(), unit: 'فاتورة' }
                            ]
                        }
                    ]
                },

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير تقرير الاقرار الضريبي بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            // استدعاء دالة التصدير
            exportToExcelProfessional(config);
        }

        // =====================================
        // طباعة التقرير
        // =====================================
        function printTaxDeclaration() {
            // جمع بيانات الجداول
            var salesContent = '';
            var purchasesContent = '';
            var otherContent = '';

            // جمع بيانات المبيعات
            $('#salesTaxTableBody tr').each(function() {
                salesContent += $(this).prop('outerHTML');
            });

            // جمع بيانات المشتريات
            $('#purchasesTaxTableBody tr').each(function() {
                purchasesContent += $(this).prop('outerHTML');
            });

            // جمع بيانات أخرى
            $('#otherTaxTableBody tr').each(function() {
                otherContent += $(this).prop('outerHTML');
            });

            // فتح نافذة الطباعة
            var printWindow = window.open('', '', 'height=800,width=1200');
            
            var printContent = '<!DOCTYPE html>' +
                '<html dir="rtl" lang="ar">' +
                '<head>' +
                '<meta charset="UTF-8">' +
                '<title>تقرير الاقرار الضريبي</title>' +
                '<style>' +
                '* { margin: 0; padding: 0; box-sizing: border-box; }' +
                'body { font-family: Arial, Tahoma, sans-serif; padding: 25px; background-color: white; color: #333; line-height: 1.6; }' +
                '.header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }' +
                '.header h1 { font-size: 28px; color: #333; margin-bottom: 8px; font-weight: bold; }' +
                '.header p { color: #666; font-size: 13px; margin: 3px 0; }' +
                '.info-section { margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }' +
                '.info-row { display: flex; justify-content: space-between; flex-wrap: wrap; }' +
                '.info-item { flex: 1; text-align: center; min-width: 150px; margin: 5px; }' +
                '.info-item label { display: block; font-size: 12px; color: #666; margin-bottom: 4px; }' +
                '.info-item value { display: block; font-size: 16px; font-weight: bold; color: #333; }' +
                '.section-title { background: #333; color: white; padding: 10px 15px; margin: 20px 0 10px 0; font-weight: bold; }' +
                '.table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #333; }' +
                '.table thead th { background-color: #333; color: white; padding: 12px 8px; text-align: center; font-weight: bold; border: 1px solid #333; font-size: 13px; }' +
                '.table tbody td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; font-size: 12px; }' +
                '.table tbody tr:nth-child(even) { background-color: #f8f9fa; }' +
                '.text-success { color: #28a745 !important; font-weight: bold; }' +
                '.text-danger { color: #dc3545 !important; font-weight: bold; }' +
                '.text-primary { color: #007bff !important; font-weight: bold; }' +
                '.text-warning { color: #ffc107 !important; font-weight: bold; }' +
                '.fw-bold { font-weight: bold; }' +
                '.summary-box { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; margin-top: 20px; border-radius: 8px; }' +
                '.summary-row { display: flex; justify-content: space-around; text-align: center; flex-wrap: wrap; }' +
                '.summary-item { padding: 15px; background: white; border-radius: 8px; margin: 5px; min-width: 150px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }' +
                '.summary-item h6 { font-size: 12px; color: #666; margin-bottom: 5px; }' +
                '.summary-item h4 { font-size: 18px; margin: 0; }' +
                '.badge { padding: 5px 10px; border-radius: 4px; font-size: 12px; }' +
                '.bg-danger { background-color: #dc3545; color: white; }' +
                '.bg-success { background-color: #28a745; color: white; }' +
                '.bg-info { background-color: #17a2b8; color: white; }' +
                '.bg-warning { background-color: #ffc107; color: #333; }' +
                '.footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #333; text-align: center; color: #666; font-size: 11px; }' +
                '.alert { padding: 15px; text-align: center; background: #f8f9fa; border-radius: 4px; }' +
                '@media print { body { margin: 0; padding: 15px; } .header { page-break-after: avoid; } tr { page-break-inside: avoid; } }' +
                '</style>' +
                '</head>' +
                '<body>' +
                '<div class="header">' +
                '<h1>تقرير الاقرار الضريبي</h1>' +
                '<p>الفترة: من ' + ($('#from_date').val() || '') + ' إلى ' + ($('#to_date').val() || '') + '</p>' +
                '<p>تاريخ الطباعة: ' + new Date().toLocaleDateString('ar-SA') + ' - ' + new Date().toLocaleTimeString('ar-SA') + '</p>' +
                '</div>' +
                '<div class="info-section">' +
                '<div class="info-row">' +
                '<div class="info-item"><label>إجمالي المبيعات</label><value class="text-success">' + $('#totalSales').text() + ' {{ \App\Support\CurrencySettings::symbol() }}</value></div>' +
                '<div class="info-item"><label>إجمالي المشتريات</label><value class="text-danger">' + $('#totalPurchases').text() + ' {{ \App\Support\CurrencySettings::symbol() }}</value></div>' +
                '<div class="info-item"><label>ضريبة المخرجات</label><value class="text-primary">' + $('#totalTaxOutput').text() + ' {{ \App\Support\CurrencySettings::symbol() }}</value></div>' +
                '<div class="info-item"><label>ضريبة المدخلات</label><value class="text-warning">' + $('#totalTaxInput').text() + ' {{ \App\Support\CurrencySettings::symbol() }}</value></div>' +
                '</div>' +
                '</div>' +
                '<div class="section-title"><i class="fa fa-arrow-up"></i> فواتير المبيعات</div>' +
                '<table class="table">' +
                '<thead><tr><th>نوع الضريبة</th><th>المبيعات</th><th>الضريبة</th><th>الإجمالي</th></tr></thead>' +
                '<tbody>' + salesContent + '</tbody>' +
                '</table>' +
                '<div class="section-title"><i class="fa fa-arrow-down"></i> فواتير المشتريات</div>' +
                '<table class="table">' +
                '<thead><tr><th>نوع الضريبة</th><th>المشتريات</th><th>الضريبة</th><th>الإجمالي</th></tr></thead>' +
                '<tbody>' + purchasesContent + '</tbody>' +
                '</table>' +
                '<div class="section-title"><i class="fa fa-list-alt"></i> أخرى (مصروفات، مردودات، إشعارات)</div>' +
                '<table class="table">' +
                '<thead><tr><th>البند</th><th>المبلغ الأساسي</th><th>الضريبة</th><th>الإجمالي</th><th>النوع</th></tr></thead>' +
                '<tbody>' + otherContent + '</tbody>' +
                '</table>' +
                '<div class="summary-box">' +
                '<h5 style="text-align: center; margin-bottom: 15px;">ملخص الاقرار الضريبي النهائي</h5>' +
                '<div class="summary-row">' +
                '<div class="summary-item"><h6>ضريبة المخرجات</h6><h4 class="text-success">' + $('#summaryTaxOutput').text() + '</h4></div>' +
                '<div class="summary-item"><h6>ضريبة المدخلات</h6><h4 class="text-warning">' + $('#summaryTaxInput').text() + '</h4></div>' +
                '<div class="summary-item"><h6>صافي الضريبة</h6><h4 class="text-primary">' + $('#summaryNetTax').text() + '</h4></div>' +
                '<div class="summary-item"><h6>الحالة</h6>' + $('#summaryStatus').html() + '</div>' +
                '</div>' +
                '</div>' +
                '<div class="footer">' +
                '<p>تم إنشاء هذا التقرير بواسطة النظام المحاسبي - جميع الحقوق محفوظة ' + new Date().getFullYear() + '</p>' +
                '</div>' +
                '</body>' +
                '</html>';
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            setTimeout(function() {
                printWindow.focus();
                printWindow.print();
            }, 500);
        }
    </script>
@endsection
