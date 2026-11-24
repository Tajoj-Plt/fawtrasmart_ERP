@extends('account::master')

@section('title')
    تقرير الميزانية العمومية
@stop

@section('content')
    @include('account::components.page-head', [
        'title' => 'تقرير الميزانية العمومية',
        'crumbs' => [
            'الرئيسية' => null,
            'تقرير الميزانية العمومية' => null,
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.balanceSheet') }}">
                        <div class="form-body row">
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'from_date',
                                'toName' => 'to_date',
                                'colClass' => 'form-group col-md-3',
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

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">بحث</button>
                            <a href="{{ route('GeneralAccountReports.balanceSheet') }}" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">الغاء الفلترة</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__label">إجمالي الأصول (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_assets'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__label">إجمالي الخصوم (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">حقوق الملكية (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_equity'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-balance"></i></span>
                <span class="stk-metric__label">الخصوم + حقوق الملكية</span>
                <span class="stk-metric__value">{{ number_format($totals['total_liabilities_equity'] ?? 0, 2) }}</span>
            </div>
        </div>
        <br>

        <!-- Chart Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="stk-table-head__title">مقارنة الأصول والخصوم وحقوق الملكية</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="balanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assets Card -->
        <div class="card mt-4" id="assetsCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-arrow-up me-2 text-success"></i> الأصول</h6>
                        <div id="assetsInfo" class="text-muted">
                            @if(isset($assets))
                                إجمالي: {{ count($assets) }} حساب
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>اسم الحساب</th>
                                    <th>الحساب الأب</th>
                                    <th>الكود</th>
                                    <th>المبلغ</th>
                                </tr>
                            </thead>
                            <tbody id="assetsTableBody">
                                <!-- Assets data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        <!-- Assets Pagination Controls -->
                        <div id="assetsPagination" class="d-flex justify-content-between align-items-center mt-3 w-100" style="display: none !important;">
                            <!-- Assets Pagination Info -->
                            <div class="stk-table-foot__count" id="assetsPageInfo">
                                عرض 0 إلى 0 من 0 نتيجة
                            </div>

                            <!-- Assets Pagination Navigation -->
                            <nav aria-label="صفحات النتائج">
                                <ul class="pagination pagination-sm mb-0">
                                    <!-- First Page -->
                                    <li class="page-item disabled" id="assetsFirstPageItem">
                                        <span class="page-link" id="assetsFirstPage">
                                            <i class="fa fa-angle-double-right"></i>
                                        </span>
                                    </li>

                                    <!-- Previous Page -->
                                    <li class="page-item disabled" id="assetsPrevPageItem">
                                        <span class="page-link" id="assetsPrevPage">
                                            <i class="fa fa-angle-right"></i>
                                        </span>
                                    </li>

                                    <!-- Current Page Number -->
                                    <li class="page-item active" id="assetsCurrentPageItem">
                                        <span class="page-link" id="assetsCurrentPage">1</span>
                                    </li>

                                    <!-- Next Page -->
                                    <li class="page-item disabled" id="assetsNextPageItem">
                                        <span class="page-link" id="assetsNextPage">
                                            <i class="fa fa-angle-left"></i>
                                        </span>
                                    </li>

                                    <!-- Last Page -->
                                    <li class="page-item disabled" id="assetsLastPageItem">
                                        <span class="page-link" id="assetsLastPage">
                                            <i class="fa fa-angle-double-left"></i>
                                        </span>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <!-- Assets Total Row -->
                        @if(isset($assets) && count($assets) > 0)
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="3"><strong>الإجمالي</strong></td>
                                    <td class="text-success fw-bold">{{ number_format($totals['total_assets'] ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Liabilities Card -->
        <div class="card mt-4" id="liabilitiesCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-arrow-down me-2 text-danger"></i> الخصوم</h6>
                        <div id="liabilitiesInfo" class="text-muted">
                            @if(isset($liabilities))
                                إجمالي: {{ count($liabilities) }} حساب
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>اسم الحساب</th>
                                    <th>الحساب الأب</th>
                                    <th>الكود</th>
                                    <th>المبلغ</th>
                                </tr>
                            </thead>
                            <tbody id="liabilitiesTableBody">
                                <!-- Liabilities data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        <!-- Liabilities Pagination Controls -->
                        <div id="liabilitiesPagination" class="d-flex justify-content-between align-items-center mt-3 w-100" style="display: none !important;">
                            <!-- Liabilities Pagination Info -->
                            <div class="stk-table-foot__count" id="liabilitiesPageInfo">
                                عرض 0 إلى 0 من 0 نتيجة
                            </div>

                            <!-- Liabilities Pagination Navigation -->
                            <nav aria-label="صفحات النتائج">
                                <ul class="pagination pagination-sm mb-0">
                                    <!-- First Page -->
                                    <li class="page-item disabled" id="liabilitiesFirstPageItem">
                                        <span class="page-link" id="liabilitiesFirstPage">
                                            <i class="fa fa-angle-double-right"></i>
                                        </span>
                                    </li>

                                    <!-- Previous Page -->
                                    <li class="page-item disabled" id="liabilitiesPrevPageItem">
                                        <span class="page-link" id="liabilitiesPrevPage">
                                            <i class="fa fa-angle-right"></i>
                                        </span>
                                    </li>

                                    <!-- Current Page Number -->
                                    <li class="page-item active" id="liabilitiesCurrentPageItem">
                                        <span class="page-link" id="liabilitiesCurrentPage">1</span>
                                    </li>

                                    <!-- Next Page -->
                                    <li class="page-item disabled" id="liabilitiesNextPageItem">
                                        <span class="page-link" id="liabilitiesNextPage">
                                            <i class="fa fa-angle-left"></i>
                                        </span>
                                    </li>

                                    <!-- Last Page -->
                                    <li class="page-item disabled" id="liabilitiesLastPageItem">
                                        <span class="page-link" id="liabilitiesLastPage">
                                            <i class="fa fa-angle-double-left"></i>
                                        </span>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <!-- Liabilities Total Row -->
                        @if(isset($liabilities) && count($liabilities) > 0)
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="3"><strong>الإجمالي</strong></td>
                                    <td class="text-danger fw-bold">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Equity Card -->
        <div class="card mt-4" id="equityCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="fa fa-user me-2 text-warning"></i> حقوق الملكية</h6>
                        <div id="equityInfo" class="text-muted">
                            @if(isset($equity))
                                إجمالي: {{ count($equity) }} حساب
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>اسم الحساب</th>
                                    <th>الحساب الأب</th>
                                    <th>الكود</th>
                                    <th>المبلغ</th>
                                </tr>
                            </thead>
                            <tbody id="equityTableBody">
                                <!-- Equity data will be populated by JavaScript -->
                            </tbody>
                        </table>
                        <!-- Equity Pagination Controls -->
                        <div id="equityPagination" class="d-flex justify-content-between align-items-center mt-3 w-100" style="display: none !important;">
                            <!-- Equity Pagination Info -->
                            <div class="stk-table-foot__count" id="equityPageInfo">
                                عرض 0 إلى 0 من 0 نتيجة
                            </div>

                            <!-- Equity Pagination Navigation -->
                            <nav aria-label="صفحات النتائج">
                                <ul class="pagination pagination-sm mb-0">
                                    <!-- First Page -->
                                    <li class="page-item disabled" id="equityFirstPageItem">
                                        <span class="page-link" id="equityFirstPage">
                                            <i class="fa fa-angle-double-right"></i>
                                        </span>
                                    </li>

                                    <!-- Previous Page -->
                                    <li class="page-item disabled" id="equityPrevPageItem">
                                        <span class="page-link" id="equityPrevPage">
                                            <i class="fa fa-angle-right"></i>
                                        </span>
                                    </li>

                                    <!-- Current Page Number -->
                                    <li class="page-item active" id="equityCurrentPageItem">
                                        <span class="page-link" id="equityCurrentPage">1</span>
                                    </li>

                                    <!-- Next Page -->
                                    <li class="page-item disabled" id="equityNextPageItem">
                                        <span class="page-link" id="equityNextPage">
                                            <i class="fa fa-angle-left"></i>
                                        </span>
                                    </li>

                                    <!-- Last Page -->
                                    <li class="page-item disabled" id="equityLastPageItem">
                                        <span class="page-link" id="equityLastPage">
                                            <i class="fa fa-angle-double-left"></i>
                                        </span>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <!-- Equity Total Row -->
                        @if(isset($equity) && count($equity) > 0)
                        <table class="table">
                            <tbody>
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="3"><strong>الإجمالي</strong></td>
                                    <td class="text-warning fw-bold">{{ number_format($totals['total_equity'] ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Sheet Summary Section -->
        <div class="card mt-4" id="balanceSummaryCard">
            <div class="card-content">
                <div class="card-body">
                    <h4 class="card-title text-center mb-4">
                        <i class="fa fa-file-contract me-2"></i>
                        ملخص الميزانية العمومية النهائي
                    </h4>
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 bg-white shadow-sm h-100">
                                <h6 class="text-muted mb-1">إجمالي الأصول</h6>
                                <h4 class="text-success mb-0" id="summaryAssets">{{ number_format($totals['total_assets'] ?? 0, 2) }}</h4>
                                <small class="text-muted">ريال سعودي</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 bg-white shadow-sm h-100">
                                <h6 class="text-muted mb-1">إجمالي الخصوم</h6>
                                <h4 class="text-danger mb-0" id="summaryLiabilities">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</h4>
                                <small class="text-muted">ريال سعودي</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 bg-white shadow-sm h-100">
                                <h6 class="text-muted mb-1">حقوق الملكية</h6>
                                <h4 class="text-primary mb-0" id="summaryEquity">{{ number_format($totals['total_equity'] ?? 0, 2) }}</h4>
                                <small class="text-muted">ريال سعودي</small>
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
    let balanceChart;

    // Pagination configuration
    const ITEMS_PER_PAGE = 25;
    
    // Store all data
    const allData = {
        assets: @json(isset($assets) ? $assets : []),
        liabilities: @json(isset($liabilities) ? $liabilities : []),
        equity: @json(isset($equity) ? $equity : [])
    };
    
    // Current page tracking
    const currentPage = {
        assets: 1,
        liabilities: 1,
        equity: 1
    };

    $(document).ready(function() {
        // Initialize chart with backend data
        updateChart();
        // Initialize pagination
        initPagination();
        
        // Filter button
        $('#filterBtn').click(function() {
            $('#reportForm').submit();
        });

        // Reset button
        $('#resetBtn').click(function() {
            $('#reportForm')[0].reset();
            $('#from_date').val('{{ now()->startOfYear()->format('Y-m-d') }}');
            $('#to_date').val('{{ now()->format('Y-m-d') }}');
            $('#reportForm').submit();
        });

        // Export to Excel
        $('#exportExcel').click(function(e) {
            e.preventDefault();
            exportBalanceSheet();
        });

        // Print
        $('#printBtn').click(function(e) {
            e.preventDefault();
            printBalanceSheet();
        });
    });

    // Initialize pagination for all tables
    function initPagination() {
        // Initialize page info for all tables
        $('#assetsPageInfo').text('عرض 0 إلى 0 من 0 نتيجة');
        $('#liabilitiesPageInfo').text('عرض 0 إلى 0 من 0 نتيجة');
        $('#equityPageInfo').text('عرض 0 إلى 0 من 0 نتيجة');
        
        renderTablePage('assets');
        renderTablePage('liabilities');
        renderTablePage('equity');
    }
    
    // Render a specific page for a table
    function renderTablePage(tableType) {
        const data = allData[tableType];
        const page = currentPage[tableType];
        const totalPages = Math.ceil(data.length / ITEMS_PER_PAGE);
        
        // Calculate start and end indices
        const startIndex = (page - 1) * ITEMS_PER_PAGE;
        const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, data.length);
        const pageData = data.slice(startIndex, endIndex);
        
        // Render table rows
        const tableBody = $(`#${tableType}TableBody`);
        tableBody.empty();
        
        if (pageData.length === 0) {
            tableBody.append(`
                <tr>
                    <td colspan="4" class="text-center py-5">
                        <div class="bg-royal-linear alert text-center" role="alert">
                            <i class="fa fa-info-circle me-2"></i>
                            <p class="mb-0">لا توجد بيانات</p>
                        </div>
                    </td>
                </tr>
            `);
        } else {
            pageData.forEach(account => {
                const row = createTableRow(account, tableType);
                tableBody.append(row);
            });
        }
        
        // Update pagination controls
        updatePaginationControls(tableType, page, totalPages, data.length);
    }
    
    // Create a table row
    function createTableRow(account, tableType) {
        let amountClass = '';
        let badgeClass = '';
        let rowClass = '';
        let bgColor = '';
        
        if (tableType === 'assets') {
            amountClass = 'text-success fw-bold';
            badgeClass = 'bg-success';
        } else if (tableType === 'liabilities') {
            amountClass = 'text-danger fw-bold';
            badgeClass = 'bg-danger';
        } else if (tableType === 'equity') {
            amountClass = 'text-warning fw-bold';
            badgeClass = 'bg-warning';
        }
        
        // تمييز الحسابات الأب بلون مميز
        const isParent = account.is_parent || account.has_children;
        const level = account.level || 0;
        
        // حساب المسافة البادئة بناءً على المستوى
        const indent = level * 20;
        
        if (isParent) {
            // لون خاص للحسابات الأب حسب المستوى
            if (level === 0) {
                bgColor = 'background-color: #e3f2fd; font-weight: bold;'; // أزرق فاتح للمستوى الأول
            } else if (level === 1) {
                bgColor = 'background-color: #f3e5f5; font-weight: bold;'; // بنفسجي فاتح للمستوى الثاني
            } else {
                bgColor = 'background-color: #fff3e0; font-weight: bold;'; // برتقالي فاتح للمستويات الأخرى
            }
        } else {
            // الحسابات الفرعية بدون لون خاص
            bgColor = level > 0 ? 'background-color: #fafafa;' : '';
        }
        
        // أيقونة للتوضيح
        const icon = isParent ? '<i class="fa fa-folder-open text-primary me-1"></i>' : '<i class="fa fa-file-alt text-muted me-1"></i>';
        
        // عرض الحساب الأب إن وجد
        const parentName = account.parent_name ? account.parent_name : '-';
        
        return `
            <tr style="${bgColor}">
                <td style="padding-right: ${indent + 10}px;">
                    ${icon}
                    <strong>${account.name}</strong>
                    ${isParent ? '<span class="badge bg-info ms-2" style="font-size: 10px;">حساب رئيسي</span>' : ''}
                </td>
                <td class="text-muted">${parentName}</td>
                <td class="text-center"><span class="badge ${badgeClass}">${account.code || '-'}</span></td>
                <td class="${amountClass}">${formatNumber(Math.abs(account.balance))}</td>
            </tr>
        `;
    }
    
    // Update pagination controls
    function updatePaginationControls(tableType, currentPageNum, totalPages, totalItems) {
        const paginationDiv = $(`#${tableType}Pagination`);
        const pageInfo = $(`#${tableType}PageInfo`);
        
        // Show/hide pagination controls
        if (totalPages <= 1) {
            paginationDiv.hide();
            return;
        } else {
            paginationDiv.show();
        }
        
        // Update page info
        const startIndex = (currentPageNum - 1) * ITEMS_PER_PAGE + 1;
        const endIndex = Math.min(currentPageNum * ITEMS_PER_PAGE, totalItems);
        pageInfo.text(`عرض ${startIndex} إلى ${endIndex} من ${totalItems} نتيجة`);
        
        // Update current page number
        $(`#${tableType}CurrentPage`).text(currentPageNum);
        
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

    // Update chart using backend data
    function updateChart() {
        const ctx = document.getElementById('balanceChart');
        if (!ctx) return;
        
        const context = ctx.getContext('2d');
        if (balanceChart) {
            balanceChart.destroy();
        }

        @php
            $chartLabels = $chartData['labels'] ?? ['الأصول', 'الخصوم', 'حقوق الملكية'];
            $chartAmounts = $chartData['amounts'] ?? [0, 0, 0];
            $chartColors = $chartData['colors'] ?? ['#198754', '#dc3545', '#6f42c1'];
        @endphp

        const labels = {!! json_encode($chartLabels) !!};
        const amounts = {!! json_encode($chartAmounts) !!};
        const colors = {!! json_encode($chartColors) !!};

        balanceChart = new Chart(context, {
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
    function exportBalanceSheet() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        
        // جمع إحصائيات من الصفحة
        const totalAssets = parseFloat($('#totalAssets').text().replace(/,/g, '').replace(window.AppCurrency.symbol, '').trim()) || 0;
        const totalLiabilities = parseFloat($('#totalLiabilities').text().replace(/,/g, '').replace(window.AppCurrency.symbol, '').trim()) || 0;
        const totalEquity = parseFloat($('#totalEquity').text().replace(/,/g, '').replace(window.AppCurrency.symbol, '').trim()) || 0;
        const totalLiabilitiesEquity = parseFloat($('#totalLiabilitiesEquity').text().replace(/,/g, '').replace(window.AppCurrency.symbol, '').trim()) || 0;
        
        // جمع بيانات الأصول
        const assetsData = [];
        allData.assets.forEach((account, index) => {
            const indent = (account.level || 0) * 2;
            const prefix = '  '.repeat(indent);
            const icon = account.is_parent || account.has_children ? '📁 ' : '📄 ';
            const parentTag = account.is_parent || account.has_children ? ' [حساب رئيسي]' : '';
            
            assetsData.push({
                values: [
                    index + 1,
                    prefix + icon + account.name + parentTag,
                    account.parent_name || '-',
                    account.code || '-',
                    formatNumber(Math.abs(account.balance))
                ],
                alignments: ['center', 'right', 'center', 'center', 'center'],
                cellStyles: [
                    null,
                    { bold: account.is_parent || account.has_children },
                    null,
                    null,
                    { color: 'success', bold: true }
                ]
            });
        });
        
        // جمع بيانات الخصوم
        const liabilitiesData = [];
        allData.liabilities.forEach((account, index) => {
            const indent = (account.level || 0) * 2;
            const prefix = '  '.repeat(indent);
            const icon = account.is_parent || account.has_children ? '📁 ' : '📄 ';
            const parentTag = account.is_parent || account.has_children ? ' [حساب رئيسي]' : '';
            
            liabilitiesData.push({
                values: [
                    index + 1,
                    prefix + icon + account.name + parentTag,
                    account.parent_name || '-',
                    account.code || '-',
                    formatNumber(Math.abs(account.balance))
                ],
                alignments: ['center', 'right', 'center', 'center', 'center'],
                cellStyles: [
                    null,
                    { bold: account.is_parent || account.has_children },
                    null,
                    null,
                    { color: 'danger', bold: true }
                ]
            });
        });
        
        // جمع بيانات حقوق الملكية
        const equityData = [];
        allData.equity.forEach((account, index) => {
            const indent = (account.level || 0) * 2;
            const prefix = '  '.repeat(indent);
            const icon = account.is_parent || account.has_children ? '📁 ' : '📄 ';
            const parentTag = account.is_parent || account.has_children ? ' [حساب رئيسي]' : '';
            
            equityData.push({
                values: [
                    index + 1,
                    prefix + icon + account.name + parentTag,
                    account.parent_name || '-',
                    account.code || '-',
                    formatNumber(Math.abs(account.balance))
                ],
                alignments: ['center', 'right', 'center', 'center', 'center'],
                cellStyles: [
                    null,
                    { bold: account.is_parent || account.has_children },
                    null,
                    null,
                    { color: 'warning', bold: true }
                ]
            });
        });
        
        // إعداد التقرير - ورقة الأصول
        const assetsConfig = {
            fileName: 'الميزانية_العمومية_الأصول',
            mainTitle: 'الميزانية العمومية - الأصول',
            subTitle: 'نظام المحاسبة المتكامل',
            
            reportInfo: {
                'الفترة': `من ${fromDate} إلى ${toDate}`,
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
            },

            statisticsCards: [
                {
                    title: 'إجمالي الأصول',
                    value: formatNumber(totalAssets),
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                },
                {
                    title: 'عدد حسابات الأصول',
                    value: allData.assets.length,
                    unit: ' حساب',
                    titleColor: 'primary',
                    valueColor: 'primary',
                    backgroundColor: 'grayLight',
                    borderColor: 'primary'
                }
            ],

            tableTitle: 'تفاصيل الأصول',
            tableHeaders: ['#', 'اسم الحساب', 'الحساب الأب', 'الكود', 'الرصيد ({{ \App\Support\CurrencySettings::symbol() }})'],
            columnWidths: [6, 35, 25, 15, 15],
            
            tableData: assetsData,

            summaryRows: [
                {
                    values: ['', '', '', 'إجمالي الأصول:', formatNumber(totalAssets) + ' ' + window.AppCurrency.symbol],
                    backgroundColor: 'successBg',
                    borderColor: 'success',
                    bold: true,
                    alignments: ['center', 'center', 'center', 'right', 'center'],
                    cellStyles: [null, null, null, { alignment: 'right', bold: true }, { color: 'success', bold: true }]
                }
            ],

            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير تقرير الأصول بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير تقرير الأصول'
        };

        // استدعاء دالة التصدير للأصول أولاً
        exportToExcelProfessional(assetsConfig).then(() => {
            // بعد نجاح تصدير الأصول، تصدير الخصوم
            setTimeout(() => {
                const liabilitiesConfig = {
                    fileName: 'الميزانية_العمومية_الخصوم',
                    mainTitle: 'الميزانية العمومية - الخصوم',
                    subTitle: 'نظام المحاسبة المتكامل',
                    
                    reportInfo: {
                        'الفترة': `من ${fromDate} إلى ${toDate}`,
                        'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                    },

                    statisticsCards: [
                        {
                            title: 'إجمالي الخصوم',
                            value: formatNumber(totalLiabilities),
                            unit: ' ' + window.AppCurrency.symbol,
                            titleColor: 'danger',
                            valueColor: 'danger',
                            backgroundColor: 'dangerLight',
                            borderColor: 'danger'
                        },
                        {
                            title: 'عدد حسابات الخصوم',
                            value: allData.liabilities.length,
                            unit: ' حساب',
                            titleColor: 'primary',
                            valueColor: 'primary',
                            backgroundColor: 'grayLight',
                            borderColor: 'primary'
                        }
                    ],

                    tableTitle: 'تفاصيل الخصوم',
                    tableHeaders: ['#', 'اسم الحساب', 'الحساب الأب', 'الكود', 'الرصيد ({{ \App\Support\CurrencySettings::symbol() }})'],
                    columnWidths: [6, 35, 25, 15, 15],
                    
                    tableData: liabilitiesData,

                    summaryRows: [
                        {
                            values: ['', '', '', 'إجمالي الخصوم:', formatNumber(totalLiabilities) + ' ' + window.AppCurrency.symbol],
                            backgroundColor: 'dangerBg',
                            borderColor: 'danger',
                            bold: true,
                            alignments: ['center', 'center', 'center', 'right', 'center'],
                            cellStyles: [null, null, null, { alignment: 'right', bold: true }, { color: 'danger', bold: true }]
                        }
                    ],

                    buttonSelector: '#exportExcel',
                    successMessage: 'تم تصدير تقرير الخصوم بنجاح!',
                    errorMessage: 'حدث خطأ أثناء تصدير تقرير الخصوم'
                };
                
                exportToExcelProfessional(liabilitiesConfig).then(() => {
                    // بعد نجاح تصدير الخصوم، تصدير حقوق الملكية
                    setTimeout(() => {
                        const equityConfig = {
                            fileName: 'الميزانية_العمومية_حقوق_الملكية',
                            mainTitle: 'الميزانية العمومية - حقوق الملكية',
                            subTitle: 'نظام المحاسبة المتكامل',
                            
                            reportInfo: {
                                'الفترة': `من ${fromDate} إلى ${toDate}`,
                                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                            },

                            statisticsCards: [
                                {
                                    title: 'إجمالي حقوق الملكية',
                                    value: formatNumber(totalEquity),
                                    unit: ' ' + window.AppCurrency.symbol,
                                    titleColor: 'warning',
                                    valueColor: 'warning',
                                    backgroundColor: 'warningLight',
                                    borderColor: 'warning'
                                },
                                {
                                    title: 'عدد حسابات حقوق الملكية',
                                    value: allData.equity.length,
                                    unit: ' حساب',
                                    titleColor: 'primary',
                                    valueColor: 'primary',
                                    backgroundColor: 'grayLight',
                                    borderColor: 'primary'
                                }
                            ],

                            tableTitle: 'تفاصيل حقوق الملكية',
                            tableHeaders: ['#', 'اسم الحساب', 'الحساب الأب', 'الكود', 'الرصيد ({{ \App\Support\CurrencySettings::symbol() }})'],
                            columnWidths: [6, 35, 25, 15, 15],
                            
                            tableData: equityData,

                            summaryRows: [
                                {
                                    values: ['', '', '', 'إجمالي حقوق الملكية:', formatNumber(totalEquity) + ' ' + window.AppCurrency.symbol],
                                    backgroundColor: 'grayBg',
                                    borderColor: 'warning',
                                    bold: true,
                                    alignments: ['center', 'center', 'center', 'right', 'center'],
                                    cellStyles: [null, null, null, { alignment: 'right', bold: true }, { color: 'warning', bold: true }]
                                }
                            ],

                            summarySheet: {
                                sheetName: 'ملخص الميزانية',
                                title: 'ملخص الميزانية العمومية',
                                tables: [
                                    {
                                        title: 'البيان',
                                        data: [
                                            { label: 'إجمالي الأصول', value: formatNumber(totalAssets), unit: window.AppCurrency.symbol },
                                            { label: 'إجمالي الخصوم', value: formatNumber(totalLiabilities), unit: window.AppCurrency.symbol },
                                            { label: 'حقوق الملكية', value: formatNumber(totalEquity), unit: window.AppCurrency.symbol },
                                            { label: 'الخصوم + حقوق الملكية', value: formatNumber(totalLiabilitiesEquity), unit: window.AppCurrency.symbol }
                                        ]
                                    }
                                ]
                            },

                            buttonSelector: '#exportExcel',
                            successMessage: 'تم تصدير جميع تقارير الميزانية العمومية بنجاح!',
                            errorMessage: 'حدث خطأ أثناء تصدير تقرير حقوق الملكية'
                        };
                        
                        exportToExcelProfessional(equityConfig);
                    }, 500);
                });
            }, 500);
        });
    }
    
    // =====================================
    // الطباعة باستخدام الملف المساعد
    // =====================================
    function printBalanceSheet() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        
        // فتح نافذة جديدة للطباعة
        const printWindow = window.open('', '', 'height=900,width=1200');
        
        // جمع بيانات الأصول
        let assetsRows = '';
        $('#assetsTableBody tr').each(function() {
            assetsRows += $(this)[0].outerHTML;
        });
        
        // جمع بيانات الخصوم
        let liabilitiesRows = '';
        $('#liabilitiesTableBody tr').each(function() {
            liabilitiesRows += $(this)[0].outerHTML;
        });
        
        // جمع بيانات حقوق الملكية
        let equityRows = '';
        $('#equityTableBody tr').each(function() {
            equityRows += $(this)[0].outerHTML;
        });
        
        // بناء محتوى HTML للطباعة
        const printContent = `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="UTF-8">
                <title>الميزانية العمومية</title>
                <style>
                    * { 
                        margin: 0; 
                        padding: 0; 
                        box-sizing: border-box;
                    }
                    body { 
                        font-family: 'Arial', 'Tahoma', sans-serif; 
                        padding: 25px;
                        background-color: white;
                        color: #333;
                        line-height: 1.6;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                        border-bottom: 3px solid #333;
                        padding-bottom: 15px;
                    }
                    .header h1 {
                        font-size: 28px;
                        color: #333;
                        margin-bottom: 8px;
                        font-weight: bold;
                    }
                    .header p {
                        color: #666;
                        font-size: 13px;
                        margin: 3px 0;
                    }
                    .info-section {
                        margin-bottom: 25px;
                        padding: 15px;
                        background-color: #f8f9fa;
                        border: 1px solid #dee2e6;
                        border-radius: 4px;
                    }
                    .info-row {
                        display: flex;
                        justify-content: space-around;
                        margin-bottom: 10px;
                    }
                    .info-item {
                        flex: 1;
                        text-align: center;
                    }
                    .info-item label {
                        display: block;
                        font-size: 12px;
                        color: #666;
                        margin-bottom: 4px;
                    }
                    .info-item value {
                        display: block;
                        font-size: 16px;
                        font-weight: bold;
                        color: #333;
                    }
                    .section-title {
                        font-size: 20px;
                        font-weight: bold;
                        color: #333;
                        margin: 25px 0 15px 0;
                        padding: 10px 15px;
                        background-color: #f0f0f0;
                        border-right: 4px solid #333;
                    }
                    .section-title.assets {
                        border-right-color: #28a745;
                        background-color: #e8f5e9;
                    }
                    .section-title.liabilities {
                        border-right-color: #dc3545;
                        background-color: #fce4e4;
                    }
                    .section-title.equity {
                        border-right-color: #ffc107;
                        background-color: #fff9e6;
                    }
                    .table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                        border: 1px solid #333;
                    }
                    .table thead th {
                        background-color: #333;
                        color: white;
                        padding: 12px 8px;
                        text-align: center;
                        font-weight: bold;
                        border: 1px solid #333;
                        font-size: 13px;
                    }
                    .table tbody td {
                        padding: 10px 8px;
                        border: 1px solid #ddd;
                        text-align: center;
                        font-size: 12px;
                    }
                    .table tbody tr:nth-child(even) {
                        background-color: #f8f9fa;
                    }
                    .text-success {
                        color: #28a745 !important;
                        font-weight: bold;
                    }
                    .text-danger {
                        color: #dc3545 !important;
                        font-weight: bold;
                    }
                    .text-warning {
                        color: #ffc107 !important;
                        font-weight: bold;
                    }
                    .text-primary {
                        color: #007bff !important;
                    }
                    .text-muted {
                        color: #6c757d !important;
                    }
                    .badge {
                        background-color: #6c757d;
                        color: white;
                        padding: 3px 8px;
                        border-radius: 3px;
                        font-size: 11px;
                        font-weight: normal;
                    }
                    .bg-success {
                        background-color: #28a745;
                    }
                    .bg-danger {
                        background-color: #dc3545;
                    }
                    .bg-warning {
                        background-color: #ffc107;
                    }
                    .bg-info {
                        background-color: #17a2b8;
                    }
                    .fw-bold {
                        font-weight: bold !important;
                    }
                    .fa, .feather {
                        margin-left: 5px;
                    }
                    .total-row {
                        background-color: #e9ecef !important;
                        font-weight: bold;
                        font-size: 14px;
                    }
                    .total-row td {
                        border: 1px solid #333 !important;
                        padding: 12px 8px !important;
                    }
                    .summary-section {
                        margin-top: 30px;
                        padding: 20px;
                        background-color: #f8f9fa;
                        border: 2px solid #333;
                        border-radius: 8px;
                        page-break-inside: avoid;
                    }
                    .summary-title {
                        text-align: center;
                        font-size: 22px;
                        font-weight: bold;
                        color: #333;
                        margin-bottom: 20px;
                        padding-bottom: 10px;
                        border-bottom: 2px solid #333;
                    }
                    .summary-grid {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 15px;
                        margin-top: 20px;
                    }
                    .summary-card {
                        background-color: white;
                        padding: 20px;
                        border-radius: 8px;
                        text-align: center;
                        border: 2px solid #dee2e6;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .summary-card h6 {
                        font-size: 12px;
                        color: #666;
                        margin-bottom: 10px;
                        font-weight: normal;
                    }
                    .summary-card h4 {
                        font-size: 24px;
                        font-weight: bold;
                        margin-bottom: 5px;
                    }
                    .summary-card small {
                        font-size: 11px;
                        color: #999;
                    }
                    .footer {
                        margin-top: 40px;
                        padding-top: 15px;
                        border-top: 2px solid #333;
                        text-align: center;
                        color: #666;
                        font-size: 11px;
                    }
                    @media print {
                        body { 
                            margin: 0; 
                            padding: 15px; 
                        }
                        .header { 
                            page-break-after: avoid; 
                        }
                        .section-title {
                            page-break-after: avoid;
                        }
                        tr { 
                            page-break-inside: avoid; 
                        }
                        thead { 
                            display: table-header-group; 
                        }
                        .summary-section {
                            page-break-before: auto;
                            page-break-inside: avoid;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>الميزانية العمومية</h1>
                    <p>نظام المحاسبة المتكامل</p>
                    <p>الفترة: من ${fromDate} إلى ${toDate}</p>
                    <p>تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} - ${new Date().toLocaleTimeString('ar-SA')}</p>
                </div>
                
                <div class="info-section">
                    <div class="info-row">
                        <div class="info-item">
                            <label>إجمالي الأصول</label>
                            <value class="text-success">${$('#totalAssets').text()}</value>
                        </div>
                        <div class="info-item">
                            <label>إجمالي الخصوم</label>
                            <value class="text-danger">${$('#totalLiabilities').text()}</value>
                        </div>
                        <div class="info-item">
                            <label>حقوق الملكية</label>
                            <value class="text-warning">${$('#totalEquity').text()}</value>
                        </div>
                        <div class="info-item">
                            <label>الخصوم + حقوق الملكية</label>
                            <value>${$('#totalLiabilitiesEquity').text()}</value>
                        </div>
                    </div>
                </div>
                
                <!-- جدول الأصول -->
                <h3 class="section-title assets">📈 الأصول (${allData.assets.length} حساب)</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">اسم الحساب</th>
                            <th style="width: 25%;">الحساب الأب</th>
                            <th style="width: 15%;">الكود</th>
                            <th style="width: 20%;">الرصيد ({{ \App\Support\CurrencySettings::symbol() }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${assetsRows}
                    </tbody>
                </table>
                <table class="table">
                    <tbody>
                        <tr class="total-row">
                            <td colspan="3"><strong>الإجمالي</strong></td>
                            <td class="text-success fw-bold">${$('#totalAssets').text()}</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- جدول الخصوم -->
                <h3 class="section-title liabilities">📉 الخصوم (${allData.liabilities.length} حساب)</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">اسم الحساب</th>
                            <th style="width: 25%;">الحساب الأب</th>
                            <th style="width: 15%;">الكود</th>
                            <th style="width: 20%;">الرصيد ({{ \App\Support\CurrencySettings::symbol() }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${liabilitiesRows}
                    </tbody>
                </table>
                <table class="table">
                    <tbody>
                        <tr class="total-row">
                            <td colspan="3"><strong>الإجمالي</strong></td>
                            <td class="text-danger fw-bold">${$('#totalLiabilities').text()}</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- جدول حقوق الملكية -->
                <h3 class="section-title equity">👤 حقوق الملكية (${allData.equity.length} حساب)</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">اسم الحساب</th>
                            <th style="width: 25%;">الحساب الأب</th>
                            <th style="width: 15%;">الكود</th>
                            <th style="width: 20%;">الرصيد ({{ \App\Support\CurrencySettings::symbol() }})</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${equityRows}
                    </tbody>
                </table>
                <table class="table">
                    <tbody>
                        <tr class="total-row">
                            <td colspan="3"><strong>الإجمالي</strong></td>
                            <td class="text-warning fw-bold">${$('#totalEquity').text()}</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- الملخص النهائي -->
                <div class="summary-section">
                    <div class="summary-title">
                        📊 ملخص الميزانية العمومية النهائي
                    </div>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <h6>إجمالي الأصول</h6>
                            <h4 class="text-success">${$('#summaryAssets').text()}</h4>
                            <small>ريال سعودي</small>
                        </div>
                        <div class="summary-card">
                            <h6>إجمالي الخصوم</h6>
                            <h4 class="text-danger">${$('#summaryLiabilities').text()}</h4>
                            <small>ريال سعودي</small>
                        </div>
                        <div class="summary-card">
                            <h6>حقوق الملكية</h6>
                            <h4 class="text-primary">${$('#summaryEquity').text()}</h4>
                            <small>ريال سعودي</small>
                        </div>
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
        
        // انتظر تحميل الصفحة ثم اطبع تلقائيًا
        setTimeout(function() {
            printWindow.focus();
            printWindow.print();
        }, 500);
    }
</script>
@endsection