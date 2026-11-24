@extends('account::master')

@section('title')
    الحركات المالية
@stop

@section('content')
    @include('account::components.page-head', [
        'title' => 'الحركات المالية',
        'crumbs' => [
            'الرئيسية' => null,
            'الحركات المالية' => null,
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
                                    <i class="feather icon-file-text"></i> تصدير Excel
                                </button>
                                <button class="btn btn-outline-primary" id="printBtn">
                                    <i class="feather icon-printer"></i> طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.financialTransactions') }}">
                        <div class="form-body row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'form-group col-md-2',
                                'costName' => 'cost_center',
                            ])

                            <div class="form-group col-md-2">
                                <label for="from_date">من تاريخ</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate }}">
                            </div>
                            
                            <div class="form-group col-md-2">
                                <label for="to_date">إلى تاريخ</label>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate }}">
                            </div>
                            
                            <div class="form-group col-md-2">
                                <label for="branch">الفرع</label>
                                <select name="branch" id="branch" class="form-control">
                                    <option value="">جميع الفروع</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group col-md-2">
                                <label for="account">الحساب</label>
                                <select name="account" id="account" class="form-control">
                                    <option value="">جميع الحسابات</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" {{ request('account') == $account->id ? 'selected' : '' }}>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group col-md-2">
                                <label for="reference_type">نوع القيد</label>
                                <select name="reference_type" id="reference_type" class="form-control">
                                    <option value="">جميع الأنواع</option>
                                    @foreach($referenceTypes as $key => $type)
                                        <option value="{{ $type['code'] }}" {{ request('reference_type') == $type['code'] ? 'selected' : '' }}>
                                            {{ $type['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- ثلاثُ حالاتٍ لا اثنتان: خانةُ تأشيرٍ تُتيح طلبَ
                                 الضريبية وحدَها ولا تُتيح طلبَ غيرِها، وهي ما
                                 يُراجَع عند تسوية الإقرار. --}}
                            <div class="form-group col-md-2">
                                <label for="taxable">الضريبة</label>
                                <select name="taxable" id="taxable" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="1" {{ request('taxable') === '1' ? 'selected' : '' }}>
                                        ضريبية فقط
                                    </option>
                                    <option value="0" {{ request('taxable') === '0' ? 'selected' : '' }}>
                                        غير ضريبية
                                    </option>
                                </select>
                            </div>

                            <div class="form-group col-md-2">
                                <label for="client">العميل</label>
                                <select name="client" id="client" class="form-control">
                                    <option value="">جميع العملاء</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ request('client') == $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">بحث</button>
                            <a href="{{ route('GeneralAccountReports.financialTransactions') }}" class="btn btn-outline-danger waves-effect waves-light">إلغاء الفلترة</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up-circle"></i></span>
                <span class="stk-metric__label">رصيد بداية المدة (ريال)</span>
                <span class="stk-metric__value">{{ number_format($statistics['opening_balance'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">إجمالي الإيرادات (ريال)</span>
                <span class="stk-metric__value">{{ number_format($statistics['total_income'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                <span class="stk-metric__label">إجمالي المصروفات (ريال)</span>
                <span class="stk-metric__value">{{ number_format($statistics['total_expenses'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down-circle"></i></span>
                <span class="stk-metric__label">رصيد نهاية المدة (ريال)</span>
                <span class="stk-metric__value">{{ number_format($statistics['closing_balance'] ?? 0, 2) }}</span>
            </div>
        </div>
        <br>

        <!-- Transactions Table Card -->
        <div class="card" id="transactionsCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> تفاصيل الحركات المالية</h6>
                        <div class="text-muted">
                            الفترة: {{ $fromDate }} إلى {{ $toDate }}
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>التاريخ</th>
                                    <th>رقم المرجع</th>
                                    <th>نوع الحركة</th>
                                    <th>الوصف</th>
                                    <th>مدين</th>
                                    <th>دائن</th>
                                    <th>الرصيد</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody">
                                {{-- رصيد بداية المدة --}}
                                <tr class="table-secondary">
                                    <td colspan="7"><strong>رصيد بداية المدة</strong></td>
                                    <td>
                                        <strong class="{{ ($statistics['opening_balance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                            @money(abs($statistics['opening_balance'] ?? 0))
                                        </strong>
                                    </td>
                                </tr>
                                
                                @if($journalEntries->count() > 0)
                                    @php
                                        $runningBalance = $statistics['opening_balance'] ?? 0;
                                        $counter = 1;
                                    @endphp
                                    
                                    @foreach($journalEntries as $entry)
                                        @php
                                            $totalDebit = $entry->details->sum('debit');
                                            $totalCredit = $entry->details->sum('credit');
                                            $amount = $totalDebit - $totalCredit;
                                            $runningBalance += $amount;
                                            $referenceTypeName = $entry->reference_type_name ?? 'عام';
                                        @endphp
                                        <tr>
                                            <td>{{ $counter++ }}</td>
                                            <td>{{ $entry->date?->format('d/m/Y') }}</td>
                                            <td>
                                                <a href="{{ route('journal.show', $entry->id) }}" class="text-primary">
                                                    {{ $entry->reference_number }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark-primary">{{ $referenceTypeName }}</span>
                                            </td>
                                            <td>{{ Str::limit($entry->description ?? '-', 50) }}</td>
                                            <td class="text-success fw-bold">{{ number_format($totalDebit, 2) }}</td>
                                            <td class="text-danger fw-bold">{{ number_format($totalCredit, 2) }}</td>
                                            <td>
                                                <strong class="{{ $runningBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format(abs($runningBalance), 2) }}
                                                </strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="feather icon-inbox" style="font-size: 3rem;"></i>
                                                <h5 class="mt-2">لا توجد حركات مالية</h5>
                                                <p>لم يتم العثور على أي حركات مالية تطابق معايير البحث المحددة.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                            @if($journalEntries->count() > 0)
                            <tfoot>
                                {{-- إجمالي الإيرادات --}}
                                <tr class="table-success">
                                    <td colspan="5"><strong>إجمالي الإيرادات</strong></td>
                                    <td colspan="2" class="text-success"><strong>@money($statistics['total_income'] ?? 0)</strong></td>
                                    <td></td>
                                </tr>
                                {{-- إجمالي المصروفات --}}
                                <tr class="table-danger">
                                    <td colspan="5"><strong>إجمالي المصروفات</strong></td>
                                    <td colspan="2" class="text-danger"><strong>@money($statistics['total_expenses'] ?? 0)</strong></td>
                                    <td></td>
                                </tr>
                                {{-- رصيد نهاية المدة --}}
                                <tr class="table-warning">
                                    <td colspan="7"><strong>رصيد نهاية المدة</strong></td>
                                    <td>
                                        <strong class="{{ ($statistics['closing_balance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}" style="font-size: 1.1rem;">
                                            @money(abs($statistics['closing_balance'] ?? 0))
                                        </strong>
                                    </td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                        
                        @if($journalEntries->hasPages())
                            <div class="d-flex justify-content-between align-items-center mt-3 w-100 px-3">
                                {{-- معلومات الترقيم --}}
                                <div class="stk-table-foot__count">
                                    عرض {{ $journalEntries->firstItem() ?? 0 }} إلى {{ $journalEntries->lastItem() ?? 0 }} من {{ $journalEntries->total() }} نتيجة
                                </div>

                                {{-- أزرار التنقل --}}
                                <nav aria-label="صفحات النتائج">
                                    <ul class="pagination pagination-sm mb-0">
                                        {{-- الصفحة الأولى --}}
                                        @if ($journalEntries->onFirstPage())
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fa fa-angle-double-right"></i></span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $journalEntries->url(1) }}" aria-label="الأول">
                                                    <i class="fa fa-angle-double-right"></i>
                                                </a>
                                            </li>
                                        @endif

                                        {{-- الصفحة السابقة --}}
                                        @if ($journalEntries->onFirstPage())
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fa fa-angle-right"></i></span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $journalEntries->previousPageUrl() }}" aria-label="السابق">
                                                    <i class="fa fa-angle-right"></i>
                                                </a>
                                            </li>
                                        @endif

                                        {{-- رقم الصفحة الحالية --}}
                                        <li class="page-item active">
                                            <span class="page-link">{{ $journalEntries->currentPage() }}</span>
                                        </li>

                                        {{-- الصفحة التالية --}}
                                        @if ($journalEntries->hasMorePages())
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $journalEntries->nextPageUrl() }}" aria-label="التالي">
                                                    <i class="fa fa-angle-left"></i>
                                                </a>
                                            </li>
                                        @else
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fa fa-angle-left"></i></span>
                                            </li>
                                        @endif

                                        {{-- الصفحة الأخيرة --}}
                                        @if ($journalEntries->hasMorePages())
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $journalEntries->url($journalEntries->lastPage()) }}" aria-label="الأخير">
                                                    <i class="fa fa-angle-double-left"></i>
                                                </a>
                                            </li>
                                        @else
                                            <li class="page-item disabled">
                                                <span class="page-link"><i class="fa fa-angle-double-left"></i></span>
                                            </li>
                                        @endif
                                    </ul>
                                </nav>
                            </div>
                        @elseif($journalEntries->count() > 0)
                            <div class="d-flex justify-content-between align-items-center mt-3 w-100 px-3">
                                <div class="stk-table-foot__count">
                                    عرض {{ $journalEntries->count() }} من {{ $journalEntries->total() }} نتيجة
                                </div>
                                <div class="text-muted small">
                                    صفحة واحدة
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="card mt-3" id="summaryCard">
            <div class="card-header py-2">
                <h5 class="mb-0"><i class="feather icon-bar-chart-2 me-1"></i> ملخص الحركات المالية</h5>
            </div>
            <div class="card-body py-3">
                <div class="row">
                    <!-- الإيرادات -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="p-3 border rounded" style="background: rgba(40, 199, 111, 0.1);">
                            <h6 class="text-success mb-2"><i class="feather icon-arrow-up"></i> الإيرادات</h6>
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td>إجمالي الإيرادات</td>
                                    <td class="text-left"><strong class="text-success">{{ number_format($statistics['total_income'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>عدد القيود</td>
                                    <td class="text-left"><strong>{{ $statistics['income_count'] ?? 0 }}</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- المصروفات -->
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="p-3 border rounded" style="background: rgba(234, 84, 85, 0.1);">
                            <h6 class="text-danger mb-2"><i class="feather icon-arrow-down"></i> المصروفات</h6>
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td>إجمالي المصروفات</td>
                                    <td class="text-left"><strong class="text-danger">{{ number_format($statistics['total_expenses'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>عدد القيود</td>
                                    <td class="text-left"><strong>{{ $statistics['expenses_count'] ?? 0 }}</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- الصافي -->
                    <div class="col-lg-4 col-md-12 mb-3">
                        <div class="p-3 border rounded" style="background: rgba(115, 103, 240, 0.1);">
                            <h6 class="text-primary mb-2"><i class="feather icon-target"></i> صافي الحركة</h6>
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td>رصيد البداية</td>
                                    <td class="text-left"><strong>{{ number_format($statistics['opening_balance'] ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td>صافي الحركة</td>
                                    <td class="text-left">
                                        @php
                                            $netMovement = ($statistics['total_income'] ?? 0) - ($statistics['total_expenses'] ?? 0);
                                        @endphp
                                        <strong class="{{ $netMovement >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ number_format($netMovement, 2) }}
                                        </strong>
                                    </td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>رصيد النهاية</strong></td>
                                    <td class="text-left">
                                        <strong class="{{ ($statistics['closing_balance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}" style="font-size: 1.1rem;">
                                            @money($statistics['closing_balance'] ?? 0)
                                        </strong>
                                    </td>
                                </tr>
                            </table>
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
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        $(document).ready(function() {
            $('#filterBtn').click(function() {
                $('#reportForm').submit();
            });

            $('#exportExcel').click(function() {
                exportFinancialTransactionsReport();
            });

            $('#printBtn').click(function() {
                printFinancialTransactions();
            });
        });

        // =====================================
        // تصدير Excel باستخدام الملف المساعد
        // =====================================
        function exportFinancialTransactionsReport() {
            const statistics = {
                opening_balance: {{ $statistics['opening_balance'] ?? 0 }},
                total_income: {{ $statistics['total_income'] ?? 0 }},
                total_expenses: {{ $statistics['total_expenses'] ?? 0 }},
                closing_balance: {{ $statistics['closing_balance'] ?? 0 }},
                income_count: {{ $statistics['income_count'] ?? 0 }},
                expenses_count: {{ $statistics['expenses_count'] ?? 0 }},
                total_entries: {{ $statistics['total_entries'] ?? 0 }}
            };

            const fromDate = '{{ $fromDate }}';
            const toDate = '{{ $toDate }}';
            const branchName = '{{ request("branch") ? ($branches->find(request("branch"))->name ?? "جميع الفروع") : "جميع الفروع" }}';
            const accountName = '{{ request("account") ? ($accounts->find(request("account"))->name ?? "جميع الحسابات") : "جميع الحسابات" }}';

            // جمع بيانات الجدول
            const tableData = [];
            let rowNumber = 1;
            
            $('#transactionsTableBody tr').each(function() {
                if ($(this).hasClass('table-secondary')) return;
                
                const cells = $(this).find('td');
                if (cells.length === 0) return;

                const date = $(cells[1]).text().trim();
                const refNumber = $(cells[2]).text().trim();
                const type = $(cells[3]).text().trim();
                const description = $(cells[4]).text().trim();
                const debit = parseFloat($(cells[5]).text().replace(/,/g, '').trim()) || 0;
                const credit = parseFloat($(cells[6]).text().replace(/,/g, '').trim()) || 0;
                const balance = parseFloat($(cells[7]).text().replace(/,/g, '').replace(window.AppCurrency.symbol, '').trim()) || 0;

                tableData.push({
                    values: [
                        rowNumber++,
                        date,
                        refNumber,
                        type,
                        description,
                        debit > 0 ? formatNumber(debit) : '',
                        credit > 0 ? formatNumber(credit) : '',
                        formatNumber(Math.abs(balance))
                    ],
                    alignments: ['center', 'center', 'center', 'center', 'right', 'center', 'center', 'center'],
                    cellStyles: [
                        null, null, null, null, null,
                        debit > 0 ? { color: 'success', bold: true } : null,
                        credit > 0 ? { color: 'danger', bold: true } : null,
                        { color: balance >= 0 ? 'success' : 'danger', bold: true }
                    ]
                });
            });

            // إعداد التقرير
            const config = {
                fileName: 'تقرير_الحركات_المالية',
                mainTitle: 'تقرير الحركات المالية',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'الفترة': `من ${fromDate} إلى ${toDate}`,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                    'الفرع': branchName,
                    'الحساب': accountName
                },

                statisticsCards: [
                    {
                        title: 'رصيد بداية المدة',
                        value: formatNumber(statistics.opening_balance),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'purple',
                        valueColor: 'purple',
                        backgroundColor: 'purpleLight',
                        borderColor: 'purple'
                    },
                    {
                        title: 'إجمالي الإيرادات',
                        value: formatNumber(statistics.total_income),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'إجمالي المصروفات',
                        value: formatNumber(statistics.total_expenses),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: 'رصيد نهاية المدة',
                        value: formatNumber(statistics.closing_balance),
                        unit: ' ' + window.AppCurrency.symbol,
                        titleColor: 'warning',
                        valueColor: statistics.closing_balance >= 0 ? 'success' : 'danger',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    }
                ],

                tableTitle: 'تفاصيل الحركات المالية',
                tableHeaders: ['#', 'التاريخ', 'رقم المرجع', 'نوع الحركة', 'الوصف', 'مدين', 'دائن', 'الرصيد'],
                columnWidths: [6, 12, 16, 16, 45, 14, 14, 14],
                
                tableData: [
                    // رصيد البداية
                    {
                        values: ['', '', '', '', 'رصيد بداية المدة', '', '', formatNumber(Math.abs(statistics.opening_balance))],
                        mergeFrom: 1,
                        mergeTo: 5,
                        alignments: ['center', 'center', 'center', 'center', 'right', 'center', 'center', 'center'],
                        cellStyles: [null, null, null, null, { bold: true }, null, null, { color: statistics.opening_balance >= 0 ? 'success' : 'danger', bold: true }]
                    },
                    ...tableData
                ],

                summaryRows: [
                    {
                        values: ['', '', '', '', 'إجمالي الإيرادات', formatNumber(statistics.total_income), window.AppCurrency.symbol, ''],
                        mergeFrom: 1,
                        mergeTo: 4,
                        background: 'successBg',
                        color: 'success',
                        borderColor: 'success',
                        cellStyles: [null, null, null, null, { alignment: 'right' }, null, null, null]
                    },
                    {
                        values: ['', '', '', '', 'إجمالي المصروفات', formatNumber(statistics.total_expenses), window.AppCurrency.symbol, ''],
                        mergeFrom: 1,
                        mergeTo: 4,
                        background: 'dangerBg',
                        color: 'danger',
                        borderColor: 'danger',
                        cellStyles: [null, null, null, null, { alignment: 'right' }, null, null, null]
                    },
                    {
                        values: ['', '', '', '', 'صافي الحركة', formatNumber(statistics.total_income - statistics.total_expenses), window.AppCurrency.symbol, ''],
                        mergeFrom: 1,
                        mergeTo: 4,
                        background: 'purpleLight',
                        color: 'purple',
                        borderColor: 'purple',
                        cellStyles: [null, null, null, null, { alignment: 'right' }, { color: (statistics.total_income - statistics.total_expenses) >= 0 ? 'success' : 'danger' }, null, null]
                    },
                    {
                        values: ['', '', '', '', 'رصيد نهاية المدة', formatNumber(statistics.closing_balance), 'ريال سعودي', ''],
                        mergeFrom: 1,
                        mergeTo: 4,
                        background: 'warningLight',
                        color: 'warning',
                        borderColor: 'warning',
                        height: 32,
                        fontSize: 12,
                        cellStyles: [null, null, null, null, { alignment: 'right' }, { color: statistics.closing_balance >= 0 ? 'success' : 'danger', fontSize: 14 }, null, null]
                    }
                ],

                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'الملخص الإحصائي',
                    tables: [
                        {
                            title: 'البيان',
                            headerColor: 'primary',
                            data: [
                                { label: 'رصيد بداية المدة', value: formatNumber(statistics.opening_balance), unit: ' ' + window.AppCurrency.symbol, color: 'purple' },
                                { label: 'إجمالي الإيرادات', value: formatNumber(statistics.total_income), unit: ' ' + window.AppCurrency.symbol, color: 'success' },
                                { label: 'إجمالي المصروفات', value: formatNumber(statistics.total_expenses), unit: ' ' + window.AppCurrency.symbol, color: 'danger' },
                                { label: 'صافي الحركة', value: formatNumber(statistics.total_income - statistics.total_expenses), unit: ' ' + window.AppCurrency.symbol, color: 'purple' },
                                { label: 'رصيد نهاية المدة', value: formatNumber(statistics.closing_balance), unit: ' ' + window.AppCurrency.symbol, color: 'warning' }
                            ]
                        },
                        {
                            title: 'الإحصائيات',
                            headerColor: 'success',
                            data: [
                                { label: 'عدد قيود الإيرادات', value: statistics.income_count, unit: 'قيد', color: 'primary' },
                                { label: 'عدد قيود المصروفات', value: statistics.expenses_count, unit: 'قيد', color: 'primary' },
                                { label: 'إجمالي عدد القيود', value: statistics.total_entries, unit: 'قيد', color: 'primary' }
                            ]
                        }
                    ]
                },

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            // استدعاء دالة التصدير
            exportToExcelProfessional(config);
        }

        function printFinancialTransactions() {
            printTable({
                tableSelector: '#transactionsTableBody tr',
                tfootSelector: '#transactionsCard tfoot',
                title: 'تقرير الحركات المالية',
                infoCards: [
                    { label: 'رصيد بداية المدة', value: '@money($statistics["opening_balance"] ?? 0)' },
                    { label: 'إجمالي الإيرادات', value: '@money($statistics["total_income"] ?? 0)', class: 'text-success' },
                    { label: 'إجمالي المصروفات', value: '@money($statistics["total_expenses"] ?? 0)', class: 'text-danger' },
                    { label: 'رصيد نهاية المدة', value: '@money($statistics["closing_balance"] ?? 0)' }
                ],
                filterInfo: {
                    'الفترة': 'من {{ $fromDate }} إلى {{ $toDate }}',
                    'الفرع': '{{ request("branch") ? ($branches->find(request("branch"))->name ?? "جميع الفروع") : "جميع الفروع" }}',
                    'الحساب': '{{ request("account") ? ($accounts->find(request("account"))->name ?? "جميع الحسابات") : "جميع الحسابات" }}'
                },
                tableHeaders: ['#', 'التاريخ', 'رقم المرجع', 'نوع الحركة', 'الوصف', 'مدين', 'دائن', 'الرصيد'],
                columnWidths: ['3%', '8%', '7%', '10%', '41%', '10%', '10%', '11%'],
                descriptionColumn: 5,
                windowWidth: 1200,
                windowHeight: 800
            });
        }
    </script>
@endsection