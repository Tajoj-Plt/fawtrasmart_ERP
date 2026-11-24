@extends('sales::master')

@section('title')
    التحصيلات
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'تفاصيل التحصيلات',
        'icon' => 'trending-up',
        'sub' => 'ما حُصّل في الفترة، موزَّعاً حسب ' . $groupByLabel,
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الحسابات' => null,
            'التحصيلات' => null,
        ],
    ])

    <div class="content-body">
        <!-- قسم الفلاتر -->
        <div class="card stk-filters no-print">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> خيارات البحث</h6>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('collections.detail') }}" id="filterForm">
                        <div class="row">
                            <!-- نوع الفلتر الزمني -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>نوع الفترة</label>
                                <select name="filter_type" id="filter_type" class="form-control select2" onchange="updateDateFields()">
                                    <option value="month" {{ $filterType === 'month' ? 'selected' : '' }}>شهر</option>
                                    <option value="day" {{ $filterType === 'day' ? 'selected' : '' }}>يوم</option>
                                    <option value="range" {{ $filterType === 'range' ? 'selected' : '' }}>نطاق تاريخي</option>
                                </select>
                            </div>

                            <!-- الشهر -->
                            <div class="col-md-3 col-sm-6 mb-1" id="monthField">
                                <label>الشهر</label>
                                <input type="month" name="month" id="month" class="form-control" value="{{ $month }}">
                            </div>

                            <!-- اليوم -->
                            <div class="col-md-3 col-sm-6 mb-1" id="dayField" style="display: none;">
                                <label>اليوم</label>
                                <input type="date" name="day" id="day" class="form-control" value="{{ $day }}">
                            </div>

                            <!-- من تاريخ -->
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'col-md-3 col-sm-6 mb-1',
                            ])

                            <div class="col-md-3 col-sm-6 mb-1" id="fromDateField" style="display: none;">
                                <label>من تاريخ</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate }}">
                            </div>

                            <!-- إلى تاريخ -->
                            <div class="col-md-3 col-sm-6 mb-1" id="toDateField" style="display: none;">
                                <label>إلى تاريخ</label>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate }}">
                            </div>

                            <!-- الفرع -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الفرع</label>
                                <select name="branch_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ $effectiveBranchId == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- الموظف -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الموظف</label>
                                <select name="employee_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ $employeeId == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- العميل -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>العميل</label>
                                <select name="client_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ $clientId == $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name ?? $client->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- المجموعة -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>المجموعة</label>
                                <select name="neighborhood_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($regionGroups as $group)
                                        <option value="{{ $group->id }}" {{ $neighborhoodId == $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- تجميع حسب -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>تجميع حسب</label>
                                <select name="group_by" class="form-control">
                                    @foreach($groupByOptions as $key => $label)
                                        <option value="{{ $key }}" {{ $groupBy == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- نوع العرض -->
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

                            <!-- أزرار البحث -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> بحث
                                    </button>
                                    <button type="button" id="resetBtn" class="btn btn-secondary">
                                        <i class="fa fa-refresh"></i> إعادة تعيين
                                    </button>
                                </div>
                            </div>

                            <!-- أزرار التصدير -->
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

        {{--
            كانت الإحصاءات أربع بطاقاتٍ من قالب Vuexy بأصناف `stat-card`
            و`bg-rgba-*` لا وجود لها في أوراق المشروع — فتظهر بيضاء بلا حدّ
            ولا لون. ووحدة المبلغ مكتوبةٌ «(ريال)» في العنوان مهما كانت
            العملة المضبوطة.
        --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي التحصيلات</span>
                <span class="stk-metric__value">@money($statistics['total_collections'] ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">عدد التحصيلات</span>
                <span class="stk-metric__value stk-num">{{ number_format($statistics['collection_count'] ?? 0) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">متوسط التحصيل</span>
                <span class="stk-metric__value">@money($statistics['average_collection'] ?? 0)</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">الرصيد النهائي</span>
                <span class="stk-metric__value">@money($statistics['closing_balance'] ?? 0)</span>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-content">
                <div class="card-body">
                    @if($isSummary)
                        {{-- جدول الملخص --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> ملخص التحصيلات حسب {{ $groupByLabel }}
                            </h6>
                            <div class="text-muted">
                                الفترة: {{ $startDate->format('d/m/Y') }} إلى {{ $endDate->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table report reports_currency no-collapse" id="reportTable">
                                <thead>
                                    <tr class="report-results-head">
                                        <th class="first-column">#</th>
                                        <th class="first-column">{{ $groupByLabel }}</th>
                                        <th class="first-column">عدد التحصيلات</th>
                                        <th class="first-column">إجمالي المبلغ</th>
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
                                            <td>@money($data['amount'])</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">
                                                <div class="stk-empty">
                                                    <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                                    <h6 class="stk-empty__title">لا تحصيلات في هذه الفترة</h6>
                                                    <p class="stk-empty__hint">وسّع المدى الزمني أو غيّر التجميع.</p>
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
                                        <td><strong>@money($totals['amount'])</strong></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @else
                        {{-- جدول التفاصيل --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="stk-table-head__title">
                                <i class="feather icon-list"></i> تفاصيل التحصيلات حسب {{ $groupByLabel }}
                            </h6>
                            <div class="text-muted">
                                الفترة: {{ $startDate->format('d/m/Y') }} إلى {{ $endDate->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">الموظف</th>
                                        <th class="text-center">العميل</th>
                                        <th class="text-center">نوع الحساب</th>
                                        <th class="text-center">الخزينة/البنك</th>
                                        <th class="text-center">الفرع</th>
                                        <th class="text-center">الملاحظات</th>
                                        <th class="text-center">المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $globalRowNum = 0; @endphp
                                    @forelse($reportData as $groupKey => $data)
                                        <!-- رأس المجموعة -->
                                        <tr class="table-secondary">
                                            <td colspan="9">
                                                <strong>
                                                    <i class="fa fa-folder-open"></i>
                                                    {{ $groupByLabel }}: {{ $data['name'] ?: 'غير محدد' }}
                                                    <span class="badge bg-info mr-2">{{ $data['count'] }} تحصيل</span>
                                                </strong>
                                            </td>
                                        </tr>

                                        @foreach($data['items'] as $collection)
                                            @php $globalRowNum++; @endphp
                                            <tr>
                                                <td>{{ $globalRowNum }}</td>
                                                <td>{{ \Carbon\Carbon::parse($collection['date'])->format('d/m/Y') }}</td>
                                                <td>
                                                    <span class="badge bg-light-primary">
                                                        {{ $collection['employee_name'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $collection['client_name'] }}</td>
                                                <td>
                                                    <span class="badge bg-light-{{ $collection['account_type'] === 'خزينة' ? 'success' : 'info' }}">
                                                        {{ $collection['account_type'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $collection['treasury_account'] }}</td>
                                                <td>{{ $collection['branch_name'] }}</td>
                                                <td>{{ Str::limit($collection['note'] ?? '-', 30) }}</td>
                                                <td class="text-success fw-bold">{{ number_format($collection['amount'], 2) }}</td>
                                            </tr>
                                        @endforeach

                                        <!-- صف المجموع للمجموعة -->
                                        <tr class="table-light">
                                            <td colspan="8"><strong>إجمالي {{ $data['name'] ?: 'غير محدد' }}</strong></td>
                                            <td class="text-success"><strong>{{ number_format($data['amount'], 2) }}</strong></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
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
                                        <td colspan="8"><strong>الإجمالي العام</strong></td>
                                        <td class="text-success"><strong>@money($totals['amount'])</strong></td>
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
                    language: {},
                    allowClear: true,
                    width: '100%'
                });
            }

            // إعادة تعيين الفلاتر
            $('#resetBtn').click(function() {
                $('#filterForm')[0].reset();
                $('.select2').val(null).trigger('change');
                $('#filter_type').val('month').trigger('change');
                $('#month').val('{{ now()->format('Y-m') }}');
                window.location.href = '{{ route('collections.detail') }}';
            });

            // طباعة التقرير
            $('#printBtn').click(function() {
                printCollectionsReport();
            });

            // تصدير إلى Excel
            $('#exportExcel').click(function() {
                exportCollectionsReport();
            });

            // تحديث حقول التاريخ
            updateDateFields();
        });

        function updateDateFields() {
            const filterType = $('#filter_type').val();

            $('#monthField').hide();
            $('#dayField').hide();
            $('#fromDateField').hide();
            $('#toDateField').hide();

            if (filterType === 'month') {
                $('#monthField').show();
            } else if (filterType === 'day') {
                $('#dayField').show();
            } else if (filterType === 'range') {
                $('#fromDateField').show();
                $('#toDateField').show();
            }
        }

        // =====================================
        // تصدير Excel - يدعم الملخص والتفاصيل
        // =====================================
        function exportCollectionsReport() {
            var isSummary = {{ $isSummary ? 'true' : 'false' }};

            if (isSummary) {
                exportCollectionsSummary();
            } else {
                exportCollectionsDetails();
            }
        }

        function exportCollectionsSummary() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }}
            };

            // جمع بيانات الجدول الملخص من Blade مباشرة
            var tableData = [];

            @if(!empty($reportData))
            @foreach($reportData as $groupKey => $data)
            tableData.push({
                values: [
                    '{{ $data["name"] ?? $groupKey }}',
                    '{{ $data["count"] ?? 0 }}',
                    formatNumber({{ $data['amount'] ?? 0 }})
                ],
                cellStyles: [
                    { alignment: { horizontal: 'right' } },
                    { alignment: { horizontal: 'center' } },
                    { alignment: { horizontal: 'center' }, color: 'success', bold: true }
                ]
            });
            @endforeach
            @endif

            var config = {
                fileName: 'تقرير_التحصيلات_ملخص',
                mainTitle: 'تقرير التحصيلات حسب {{ $groupByLabel }} - ملخص',
                subTitle: 'نظام المحاسبة المتكامل',

                reportInfo: {
                    'الفترة': 'من {{ $startDate->format("Y-m-d") }} إلى {{ $endDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'إجمالي التحصيلات',
                        value: formatNumber(totals.amount),
                        unit: ' ' + window.AppCurrency.symbol,
                        borderColor: 'success'
                    },
                    {
                        title: 'عدد التحصيلات',
                        value: totals.count,
                        unit: 'عملية',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'ملخص التحصيلات حسب {{ $groupByLabel }}',
                tableHeaders: ['{{ $groupByLabel }}', 'عدد التحصيلات', 'إجمالي المبلغ'],
                columnWidths: [40, 20, 25],

                tableData: tableData,

                summaryRows: [
                    {
                        values: ['الإجمالي العام', totals.count, formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol],
                        cellStyles: [
                            { bold: true },
                            { bold: true },
                            { color: 'success', bold: true }
                        ]
                    }
                ],

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            exportToExcelProfessional(config);
        }

        function exportCollectionsDetails() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }}
            };

            // جمع بيانات الجدول التفصيلي من DOM
            var tableData = [];
            var rowNumber = 1;

            $('#reportTable tbody tr').each(function() {
                var cells = $(this).find('td');
                // تجاهل صفوف المجموعة (التي تحتوي على colspan)
                if (cells.length < 9) return;
                // تجاهل صفوف المجموع
                if ($(this).hasClass('table-light')) return;

                var date = $(cells[1]).text().trim();
                var employee = $(cells[2]).text().trim();
                var client = $(cells[3]).text().trim();
                var accountType = $(cells[4]).text().trim();
                var treasury = $(cells[5]).text().trim();
                var branch = $(cells[6]).text().trim();
                var note = $(cells[7]).text().trim();
                var amount = parseFloat($(cells[8]).text().replace(/,/g, '').trim()) || 0;

                tableData.push({
                    values: [
                        rowNumber++,
                        date,
                        employee,
                        client,
                        accountType,
                        treasury,
                        branch,
                        note,
                        formatNumber(amount)
                    ],
                    cellStyles: [
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'center' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { alignment: { horizontal: 'right' } },
                        { color: 'success', bold: true, alignment: { horizontal: 'center' } }
                    ]
                });
            });

            var config = {
                fileName: 'تقرير_التحصيلات_تفصيلي',
                mainTitle: 'تقرير التحصيلات حسب {{ $groupByLabel }} - تفصيلي',
                subTitle: 'نظام المحاسبة المتكامل',

                reportInfo: {
                    'الفترة': 'من {{ $startDate->format("Y-m-d") }} إلى {{ $endDate->format("Y-m-d") }}',
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'إجمالي التحصيلات',
                        value: formatNumber(totals.amount),
                        unit: ' ' + window.AppCurrency.symbol,
                        borderColor: 'success'
                    },
                    {
                        title: 'عدد التحصيلات',
                        value: totals.count,
                        unit: 'عملية',
                        borderColor: 'primary'
                    }
                ],

                tableTitle: 'تفاصيل التحصيلات حسب {{ $groupByLabel }}',
                tableHeaders: ['#', 'التاريخ', 'الموظف', 'العميل', 'نوع الحساب', 'الخزينة/البنك', 'الفرع', 'الملاحظات', 'المبلغ'],
                columnWidths: [5, 10, 12, 14, 10, 12, 10, 15, 12],

                tableData: tableData,

                summaryRows: [
                    {
                        values: ['', '', '', '', '', '', '', 'الإجمالي العام', formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol],
                        cellStyles: [null, null, null, null, null, null, null, { bold: true }, { color: 'success', bold: true }]
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
        function printCollectionsReport() {
            var isSummary = {{ $isSummary ? 'true' : 'false' }};

            if (isSummary) {
                printCollectionsSummary();
            } else {
                printCollectionsDetails();
            }
        }

        function printCollectionsSummary() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير التحصيلات حسب {{ $groupByLabel }} - ملخص',
                infoCards: [
                    { label: 'إجمالي التحصيلات', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-success' },
                    { label: 'عدد التحصيلات', value: totals.count + ' عملية' }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', '{{ $groupByLabel }}', 'عدد التحصيلات', 'إجمالي المبلغ'],
                columnWidths: ['5%', '45%', '25%', '25%'],
                windowWidth: 1000,
                windowHeight: 700
            });
        }

        function printCollectionsDetails() {
            var totals = {
                count: {{ $totals['count'] ?? 0 }},
                amount: {{ $totals['amount'] ?? 0 }}
            };

            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: '#reportTable tfoot',
                title: 'تقرير التحصيلات حسب {{ $groupByLabel }} - تفصيلي',
                infoCards: [
                    { label: 'إجمالي التحصيلات', value: formatNumber(totals.amount) + ' ' + window.AppCurrency.symbol, class: 'text-success' },
                    { label: 'عدد التحصيلات', value: totals.count + ' عملية' }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $startDate->format("d/m/Y") }} إلى {{ $endDate->format("d/m/Y") }}'
                },
                tableHeaders: ['#', 'التاريخ', 'الموظف', 'العميل', 'نوع الحساب', 'الخزينة/البنك', 'الفرع', 'الملاحظات', 'المبلغ'],
                columnWidths: ['4%', '10%', '12%', '12%', '10%', '12%', '10%', '18%', '12%'],
                descriptionColumn: 8,
                windowWidth: 1400,
                windowHeight: 800
            });
        }
    </script>
@endsection
