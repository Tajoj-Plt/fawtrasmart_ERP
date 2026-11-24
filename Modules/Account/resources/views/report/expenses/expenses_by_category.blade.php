@extends('account::master')

@section('title')
    المصروفات حسب التصنيف
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection


@section('content')
    @include('account::components.page-head', [
        'title' => 'المصروفات حسب التصنيف',
        'crumbs' => [
            'الرئيسية' => null,
            'المصروفات حسب التصنيف' => null,
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
                    <form method="GET" action="{{ route('GeneralAccountReports.expensesByCategory') }}" id="filterForm">
                        <div class="row">
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

                            <!-- Supplier Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>المورد</label>
                                <select name="supplier_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->trade_name ?? $supplier->first_name . ' ' . $supplier->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

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
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                            </div>

                            <!-- Date To -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>إلى تاريخ</label>
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                            </div>
                        </div>

                        <div class="row">
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

                            <!-- Account Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الحساب</label>
                                <select name="account_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

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
                        </div>

                        <div class="row">
                            <!-- Group By -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>تجميع حسب</label>
                                <select name="group_by" class="form-control">
                                    @foreach($groupByOptions as $key => $label)
                                        <option value="{{ $key }}" {{ $groupBy == $key ? 'selected' : '' }}>{{ $label }}</option>
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

                            <!-- Search Button -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> بحث
                                    </button>
                                    <a href="{{ route('GeneralAccountReports.expensesByCategory') }}" class="btn btn-secondary">
                                        <i class="fa fa-refresh"></i> إعادة تعيين
                                    </a>
                                </div>
                            </div>

                            <!-- Export Buttons -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="button" id="printBtn" class="btn btn-success">
                                        <i class="fa fa-print"></i> طباعة
                                    </button>
                                    <button type="button" id="exportExcel" class="btn btn-info">
                                        <i class="fa fa-file-excel-o"></i> Excel
                                    </button>
                                </div>
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


        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-content">
                <div class="card-body">
                    @if($isSummary)
                        {{-- جدول الملخص --}}
                        <div class="table-responsive">
                            <table class="table report reports_currency no-collapse" id="reportTable">
                                <thead>
                                    <tr class="report-results-head">
                                        <th class="first-column">#</th>
                                        <th class="first-column">{{ $groupByOptions[$groupBy] ?? 'التصنيف' }}</th>
                                        <th class="first-column">عدد المصروفات</th>
                                        <th class="first-column">إجمالي المبلغ</th>
                                        <th class="first-column">إجمالي الضريبة</th>
                                        <th class="first-column">الإجمالي شامل الضريبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rowNum = 0; @endphp
                                    @forelse($reportData as $groupKey => $data)
                                        @php $rowNum++; @endphp
                                        <tr>
                                            <td>{{ $rowNum }}</td>
                                            <td>{{ $data['name'] ?: 'غير محدد' }}</td>
                                            <td>{{ $data['count'] }}</td>
                                            <td>{{ number_format($data['amount'], 2) }}</td>
                                            <td>{{ number_format($data['tax'], 2) }}</td>
                                            <td>{{ number_format($data['total_with_tax'], 2) }}</td>
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
                                <tfoot>
                                    <tr class="section-net">
                                        <td class="first-column" colspan="2">الإجمالي العام</td>
                                        <td>{{ $totals['count'] }}</td>
                                        <td>{{ number_format($totals['amount'], 2) }} <span class="sar_symbol">{{ \App\Support\CurrencySettings::symbol() }}</span></td>
                                        <td>{{ number_format($totals['tax'], 2) }} <span class="sar_symbol">{{ \App\Support\CurrencySettings::symbol() }}</span></td>
                                        <td>{{ number_format($totals['total_with_tax'], 2) }} <span class="sar_symbol">{{ \App\Support\CurrencySettings::symbol() }}</span></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @else
                        {{-- جدول التفاصيل --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل المصروفات</h6>
                            <div class="text-muted">
                                الفترة: {{ $fromDate->format('d/m/Y') }} إلى {{ $toDate->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">الكود</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">الخزينة/البنك</th>
                                        <th class="text-center">التصنيف</th>
                                        <th class="text-center">المورد</th>
                                        <th class="text-center">الحساب</th>
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
                                    @forelse($reportData as $groupKey => $data)
                                        <!-- رأس المجموعة -->
                                        <tr class="table-secondary">
                                            <td colspan="13">
                                                <strong>
                                                    <i class="fa fa-folder-open"></i>
                                                    {{ $groupByOptions[$groupBy] ?? 'التصنيف' }}: {{ $data['name'] ?: 'غير محدد' }}
                                                    <span class="badge bg-info mr-2">{{ $data['count'] }} مصروف</span>
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
                                                <td>{{ $expense->account->name ?? '-' }}</td>
                                                <td>{{ $expense->createdBy->name ?? '-' }}</td>
                                                <td>{{ Str::limit($expense->description ?? '-', 30) }}</td>
                                                <td>{{ $expense->branch->name ?? '-' }}</td>
                                                <td class="text-success fw-bold">{{ number_format($expense->amount ?? 0, 2) }}</td>
                                                <td class="text-warning fw-bold">{{ number_format(($expense->tax1_amount ?? 0) + ($expense->tax2_amount ?? 0), 2) }}</td>
                                                <td class="text-primary fw-bold">{{ number_format(($expense->amount ?? 0) + ($expense->tax1_amount ?? 0) + ($expense->tax2_amount ?? 0), 2) }}</td>
                                            </tr>
                                        @endforeach
                                        
                                        <!-- صف المجموع للمجموعة -->
                                        <tr class="table-light">
                                            <td colspan="10"><strong>إجمالي {{ $data['name'] ?: 'غير محدد' }}</strong></td>
                                            <td class="text-success"><strong>{{ number_format($data['amount'], 2) }}</strong></td>
                                            <td class="text-warning"><strong>{{ number_format($data['tax'], 2) }}</strong></td>
                                            <td class="text-primary"><strong>{{ number_format($data['total_with_tax'], 2) }}</strong></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13" class="text-center py-5">
                                                <i class="fa fa-info-circle text-muted fa-3x mb-3 d-block"></i>
                                                <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                                                <p class="text-muted">يرجى تحديد معايير البحث والضغط على "بحث" لعرض التقرير</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(count($reportData) > 0)
                                <tfoot>
                                    <tr class="table-warning">
                                        <td colspan="10"><strong>الإجمالي العام</strong></td>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        function formatNumber(n, decimals = 2) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

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
                window.location.href = '{{ route('GeneralAccountReports.expensesByCategory') }}';
            });

            // طباعة التقرير
            $('#printBtn').click(function() {
                printExpensesByCategoryReport();
            });

            // تصدير إلى Excel
            $('#exportExcel').click(function() {
                exportExpensesByCategoryReport();
            });
        });

        // =====================================
        // تصدير Excel - يدعم الملخص والتفاصيل
        // =====================================
        function exportExpensesByCategoryReport() {
            var isSummary = {{ $isSummary ? 'true' : 'false' }};
            
            if (isSummary) {
                exportExpensesByCategorySummary();
            } else {
                exportExpensesByCategoryDetails();
            }
        }

        function exportExpensesByCategorySummary() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            // جمع بيانات الجدول الملخص من Blade مباشرة
            var tableData = [];
            
            @if(!empty($reportData))
            @foreach($reportData as $groupKey => $data)
            tableData.push({
                values: [
                    '{{ $data["name"] ?? $groupKey }}',
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
                fileName: 'تقرير_المصروفات_حسب_التصنيف_ملخص',
                mainTitle: 'تقرير المصروفات حسب {{ $groupByOptions[$groupBy] ?? "التصنيف" }} - ملخص',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
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

                tableTitle: 'ملخص المصروفات حسب {{ $groupByOptions[$groupBy] ?? "التصنيف" }}',
                tableHeaders: ['{{ $groupByOptions[$groupBy] ?? "التصنيف" }}', 'عدد المصروفات', 'إجمالي المبلغ', 'إجمالي الضريبة', 'الإجمالي شامل الضريبة'],
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

        function exportExpensesByCategoryDetails() {
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
                // تجاهل صفوف المجموعة (التي تحتوي على colspan)
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
                        $(cells[6]).text().trim(),   // الحساب
                        $(cells[7]).text().trim(),   // الموظف
                        $(cells[8]).text().trim(),   // البيان
                        $(cells[9]).text().trim(),   // الفرع
                        $(cells[10]).text().trim(),  // المبلغ
                        $(cells[11]).text().trim(),  // الضريبة
                        $(cells[12]).text().trim()   // الإجمالي
                    ],
                    cellStyles: [
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' }, color: 'success' },
                        { alignment: { horizontal: 'center' }, color: 'warning' },
                        { alignment: { horizontal: 'center' }, color: 'primary' }
                    ]
                });
            });

            var config = {
                fileName: 'تقرير_المصروفات_حسب_التصنيف_تفصيلي',
                mainTitle: 'تقرير المصروفات حسب {{ $groupByOptions[$groupBy] ?? "التصنيف" }} - تفصيلي',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
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

                tableTitle: 'تفاصيل المصروفات حسب {{ $groupByOptions[$groupBy] ?? "التصنيف" }}',
                tableHeaders: ['#', 'الكود', 'التاريخ', 'الخزينة', 'التصنيف', 'المورد', 'الحساب', 'الموظف', 'البيان', 'الفرع', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: [5, 8, 10, 10, 10, 10, 10, 10, 15, 10, 10, 10, 10],
                
                tableData: tableData,

                summaryRows: [
                    {
                        label: 'الإجمالي العام',
                        values: [
                            'الإجمالي العام', '', '', '', '', '', '', '', '', '',
                            formatNumber(totals.amount),
                            formatNumber(totals.tax),
                            formatNumber(totals.total_with_tax)
                        ],
                        color: 'primary',
                        cellStyles: [
                            { bold: true },
                            null, null, null, null, null, null, null, null, null,
                            { color: 'success', bold: true },
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
        function printExpensesByCategoryReport() {
            var isSummary = {{ $isSummary ? 'true' : 'false' }};
            
            if (isSummary) {
                printExpensesByCategorySummary();
            } else {
                printExpensesByCategoryDetails();
            }
        }

        function printExpensesByCategorySummary() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير المصروفات حسب {{ $groupByOptions[$groupBy] ?? "التصنيف" }} - ملخص',
                infoCards: [
                    { label: 'عدد المصروفات', value: totals.count + ' مصروف' },
                    { label: 'إجمالي المبلغ', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                    { label: 'إجمالي الضريبة', value: formatNumber(totals.tax) + ' ' + window.AppCurrency.symbol, class: 'text-warning' },
                    { label: 'الإجمالي شامل الضريبة', value: formatNumber(totals.total_with_tax) + ' ' + window.AppCurrency.symbol }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', '{{ $groupByOptions[$groupBy] ?? "التصنيف" }}', 'عدد المصروفات', 'إجمالي المبلغ', 'إجمالي الضريبة', 'الإجمالي شامل الضريبة'],
                columnWidths: ['5%', '30%', '15%', '17%', '17%', '16%'],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

        function printExpensesByCategoryDetails() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }},
                tax: {{ $totals['tax'] ?? 0 }},
                total_with_tax: {{ $totals['total_with_tax'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير المصروفات حسب {{ $groupByOptions[$groupBy] ?? "التصنيف" }} - تفصيلي',
                infoCards: [
                    { label: 'عدد المصروفات', value: totals.count + ' مصروف' },
                    { label: 'إجمالي المبلغ', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                    { label: 'إجمالي الضريبة', value: formatNumber(totals.tax) + ' ' + window.AppCurrency.symbol, class: 'text-warning' },
                    { label: 'الإجمالي شامل الضريبة', value: formatNumber(totals.total_with_tax) + ' ' + window.AppCurrency.symbol }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate->format("d/m/Y") }} إلى {{ $toDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', 'الكود', 'التاريخ', 'الخزينة', 'التصنيف', 'المورد', 'الحساب', 'الموظف', 'البيان', 'الفرع', 'المبلغ', 'الضريبة', 'الإجمالي'],
                columnWidths: ['4%', '6%', '8%', '8%', '8%', '8%', '8%', '8%', '14%', '8%', '7%', '7%', '7%'],
                descriptionColumn: 9,
                windowWidth: 1600,
                windowHeight: 900
            });
        }
    </script>
@endsection
