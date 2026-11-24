@extends('account::master')

@section('title')
    دليل الحسابات
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'دليل الحسابات',
        'crumbs' => [
            'الرئيسية' => null,
            'دليل الحسابات' => null,
        ],
    ])

    <div class="content-body">
        <!-- قسم الفلاتر -->
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
                                <button class="btn btn-outline-info" id="exportCSV">
                                    <i class="fa fa-file-csv me-2"></i>تصدير CSV
                                </button>
                                <button class="btn btn-outline-primary" id="printBtn">
                                    <i class="fa fa-print me-2"></i>طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="reportForm" method="GET" action="{{ route('GeneralAccountReports.ChartOfAccounts') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-3">
                                <label for="parent_account"><i class="fa fa-sitemap text-primary"></i> الحساب الأب</label>
                                <select name="parent_account" id="parent_account" class="form-control select2">
                                    <option value="all">كل الحسابات</option>
                                    @foreach ($parentAccounts ?? [] as $parentAccount)
                                        <option value="{{ $parentAccount->id }}" {{ request('parent_account') == $parentAccount->id ? 'selected' : '' }}>
                                            {{ $parentAccount->code }} - {{ $parentAccount->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="account_level">مستوى الحساب</label>
                                <select name="account_level" id="account_level" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="main" {{ request('account_level') == 'main' ? 'selected' : '' }}>حساب رئيسي</option>
                                    <option value="sub" {{ request('account_level') == 'sub' ? 'selected' : '' }}>حساب فرعي</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="account_type">طبيعة الحساب</label>
                                <select name="account_type" id="account_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="debit" {{ request('account_type') == 'debit' ? 'selected' : '' }}>مدين</option>
                                    <option value="credit" {{ request('account_type') == 'credit' ? 'selected' : '' }}>دائن</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="account_tree_type"><i class="fa fa-tree text-success"></i> نوع الشجرة</label>
                                <select name="account_tree_type" id="account_tree_type" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="assets" {{ request('account_tree_type') == 'assets' ? 'selected' : '' }}>الأصول</option>
                                    <option value="liabilities" {{ request('account_tree_type') == 'liabilities' ? 'selected' : '' }}>الخصوم</option>
                                    <option value="equity" {{ request('account_tree_type') == 'equity' ? 'selected' : '' }}>حقوق الملكية</option>
                                    <option value="revenues" {{ request('account_tree_type') == 'revenues' ? 'selected' : '' }}>الإيرادات</option>
                                    <option value="expenses" {{ request('account_tree_type') == 'expenses' ? 'selected' : '' }}>المصروفات</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="cost_center">مركز التكلفة</label>
                                <select name="cost_center" id="cost_center" class="form-control select2">
                                    <option value="all">كل مراكز التكلفة</option>
                                    @foreach ($costCenters ?? [] as $costCenter)
                                        <option value="{{ $costCenter->id }}" {{ request('cost_center') == $costCenter->id ? 'selected' : '' }}>
                                            {{ $costCenter->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-body row mt-2">
                            <div class="form-group col-md-3">
                                <label for="account_category">تصنيف الحساب</label>
                                <select name="account_category" id="account_category" class="form-control select2">
                                    <option value="">الكل</option>
                                    <option value="customer" {{ request('account_category') == 'customer' ? 'selected' : '' }}>حسابات العملاء</option>
                                    <option value="supplier" {{ request('account_category') == 'supplier' ? 'selected' : '' }}>حسابات الموردين</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="search_code">بحث بالكود</label>
                                <input type="text" name="search_code" id="search_code" class="form-control" 
                                       placeholder="أدخل كود الحساب..." value="{{ request('search_code') }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="search_name">بحث بالاسم</label>
                                <input type="text" name="search_name" id="search_name" class="form-control" 
                                       placeholder="أدخل اسم الحساب..." value="{{ request('search_name') }}">
                            </div>
                        </div>

                        <!-- البحث المتقدم -->
                        <div class="collapse" id="advancedSearchForm">
                            <div class="form-body row mt-2">
                                <div class="form-group col-md-3">
                                    <label for="branch">الفرع</label>
                                    <select name="branch" id="branch" class="form-control select2">
                                        <option value="all">كل الفروع</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                                @include('partials.accounting-period-filter', [
                                    'fromName' => 'date_from',
                                    'toName' => 'date_to',
                                    'colClass' => 'form-group col-md-3',
                                ])

                                <div class="form-group col-md-3">
                                    <label for="date_from"><i class="fa fa-calendar text-success"></i> من تاريخ الإنشاء</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control" 
                                           value="{{ request('date_from') }}">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="date_to"><i class="fa fa-calendar text-danger"></i> إلى تاريخ الإنشاء</label>
                                    <input type="date" name="date_to" id="date_to" class="form-control" 
                                           value="{{ request('date_to') }}">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="order_by">ترتيب حسب</label>
                                    <select name="order_by" id="order_by" class="form-control select2">
                                        <option value="asc" {{ request('order_by', 'asc') == 'asc' ? 'selected' : '' }}>الكود تصاعدي</option>
                                        <option value="desc" {{ request('order_by') == 'desc' ? 'selected' : '' }}>الكود تنازلي</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">
                                <i class="fa fa-search"></i> بحث
                            </button>
                            <a class="btn btn-outline-secondary ml-2 mr-2" data-bs-toggle="collapse" data-bs-target="#advancedSearchForm">
                                <i class="fa fa-sliders"></i> بحث متقدم
                            </a>
                            <button type="button" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">
                                <i class="fa fa-refresh"></i> إلغاء الفلترة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        @php
            $allAccountsCollection = $allAccounts ?? $accounts;
            $totalAccounts = $allAccountsCollection->count();
            $mainAccounts = $allAccountsCollection->whereNull('parent_id')->count();
            $subAccounts = $allAccountsCollection->whereNotNull('parent_id')->count();
            $debitAccounts = $allAccountsCollection->where('balance_type', 'debit')->count();
            $creditAccounts = $allAccountsCollection->where('balance_type', 'credit')->count();
            $accountsWithCostCenter = $allAccountsCollection->whereNotNull('cost_center_id')->count();
            $accountsWithBranch = $allAccountsCollection->whereNotNull('branch_id')->count();
        @endphp
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                <span class="stk-metric__label">إجمالي الحسابات</span>
                <span class="stk-metric__value">{{ $totalAccounts }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-folder"></i></span>
                <span class="stk-metric__label">الحسابات الرئيسية</span>
                <span class="stk-metric__value">{{ $mainAccounts }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                <span class="stk-metric__label">الحسابات الفرعية</span>
                <span class="stk-metric__value">{{ $subAccounts }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-git-branch"></i></span>
                <span class="stk-metric__label">مدين / دائن</span>
                <span class="stk-metric__value"><span class="text-success">{{ $debitAccounts }}</span> / <span class="text-danger">{{ $creditAccounts }}</span></span>
            </div>
        </div>

        <!-- الرسم البياني -->
        @if($accounts->count() > 0)
        <div class="card mt-2">
            <div class="card-header">
                <h6 class="stk-table-head__title">توزيع الحسابات - الرسم البياني</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="accountTypeChart" height="250"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="accountLevelChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> شجرة الحسابات</h6>
                        <div class="text-muted">
                            <i class="fa fa-database"></i> إجمالي: {{ $totalAccounts }} حساب
                            @if($accountsWithCostCenter > 0)
                                | <i class="fa fa-bullseye text-warning"></i> {{ $accountsWithCostCenter }} مع مركز تكلفة
                            @endif
                            @if($accountsWithBranch > 0)
                                | <i class="fa fa-building text-info"></i> {{ $accountsWithBranch }} مع فرع
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table report reports_currency no-collapse" id="accountsTable">
                            <thead>
                                <tr class="report-results-head">
                                    <th class="first-column" style="width: 50px;">#</th>
                                    <th class="first-column" style="width: 120px;">كود الحساب</th>
                                    <th class="first-column">اسم الحساب</th>
                                    <th class="first-column" style="width: 100px;">نوع الحساب</th>
                                    <th class="first-column" style="width: 100px;">المستوى</th>
                                    <th class="first-column" style="width: 150px;">مركز التكلفة</th>
                                    <th class="first-column" style="width: 150px;">الفرع</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($accounts as $index => $account)
                                    @php
                                        $level = $account->tree_level ?? 0;
                                        $isMain = is_null($account->parent_id);
                                        $hasChildren = $account->has_children ?? false;
                                        $rowClass = $isMain ? 'main-account' : ($hasChildren ? 'parent-account' : 'sub-account');
                                    @endphp
                                    <tr class="{{ $rowClass }}" data-level="{{ $level }}" data-id="{{ $account->id }}" data-bs-parent="{{ $account->parent_id ?? '' }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <span class="account-code">{{ $account->code }}</span>
                                        </td>
                                        <td class="account-level-{{ min($level, 6) }}">
                                            @if($hasChildren)
                                                <i class="fa fa-minus-square-o toggle-children tree-icon" data-account-id="{{ $account->id }}"></i>
                                            @else
                                                <span class="tree-line">└</span>
                                            @endif
                                            
                                            @if($isMain)
                                                <i class="fa fa-folder text-primary"></i>
                                            @elseif($hasChildren)
                                                <i class="fa fa-folder-open text-warning"></i>
                                            @else
                                                <i class="fa fa-file-text-o text-muted"></i>
                                            @endif
                                            {{ $account->name }}
                                        </td>
                                        <td>
                                            @if ($account->balance_type == 'debit')
                                                <span class="badge-debit">
                                                    <i class="fa fa-arrow-up"></i> مدين
                                                </span>
                                            @else
                                                <span class="badge-credit">
                                                    <i class="fa fa-arrow-down"></i> دائن
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($isMain)
                                                <span class="badge bg-primary">رئيسي</span>
                                            @elseif($hasChildren)
                                                <span class="badge bg-warning">فرعي (أب)</span>
                                            @else
                                                <span class="badge bg-secondary">فرعي</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($account->costCenter)
                                                <span class="text-warning">
                                                    <i class="fa fa-bullseye"></i> {{ $account->costCenter->name }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($account->branch)
                                                <span class="text-info">
                                                    <i class="fa fa-building"></i> {{ $account->branch->name }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fa fa-inbox fa-3x mb-3"></i>
                                                <p>لا توجد حسابات متاحة</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($accounts->count() > 0)
                            <tfoot>
                                <tr class="section-net">
                                    <td class="first-column" colspan="2">الإجمالي</td>
                                    <td>{{ $totalAccounts }} حساب</td>
                                    <td>
                                        <span class="text-success">{{ $debitAccounts }} مدين</span> | 
                                        <span class="text-danger">{{ $creditAccounts }} دائن</span>
                                    </td>
                                    <td>
                                        <span class="text-primary">{{ $mainAccounts }} رئيسي</span> | 
                                        <span class="text-secondary">{{ $subAccounts }} فرعي</span>
                                    </td>
                                    <td>{{ $accountsWithCostCenter }} حساب</td>
                                    <td>{{ $accountsWithBranch }} حساب</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
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
                $('#reportForm').submit();
            });

            // طباعة التقرير
            $('#printBtn').click(function() {
                printChartOfAccounts();
            });

            // تصدير إلى Excel
            $('#exportExcel').click(function() {
                exportChartOfAccountsReport();
            });

            // تصدير إلى CSV
            $('#exportCSV').click(function() {
                exportToCSV();
            });

            // Toggle children visibility
            $(document).on('click', '.toggle-children', function() {
                const accountId = $(this).data('account-id');
                const $icon = $(this);
                
                if ($icon.hasClass('fa-minus-square-o')) {
                    // Collapse
                    $icon.removeClass('fa-minus-square-o').addClass('fa-plus-square-o');
                    hideChildren(accountId);
                } else {
                    // Expand
                    $icon.removeClass('fa-plus-square-o').addClass('fa-minus-square-o');
                    showChildren(accountId);
                }
            });

            // الرسم البياني
            @if($accounts->count() > 0)
            initCharts();
            @endif
        });

        function hideChildren(parentId) {
            $('tr[data-bs-parent="' + parentId + '"]').each(function() {
                $(this).hide();
                const childId = $(this).data('id');
                hideChildren(childId);
            });
        }

        function showChildren(parentId) {
            $('tr[data-bs-parent="' + parentId + '"]').each(function() {
                $(this).show();
                const childId = $(this).data('id');
                const $childIcon = $(this).find('.toggle-children');
                if ($childIcon.hasClass('fa-minus-square-o')) {
                    showChildren(childId);
                }
            });
        }

        // تهيئة الرسوم البيانية
        function initCharts() {
            // رسم بياني لأنواع الحسابات (مدين/دائن)
            const typeCtx = document.getElementById('accountTypeChart');
            if (typeCtx) {
                new Chart(typeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['حسابات مدينة', 'حسابات دائنة'],
                        datasets: [{
                            data: [{{ $debitAccounts }}, {{ $creditAccounts }}],
                            backgroundColor: ['#23b7e5', '#fa8c16'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'توزيع الحسابات حسب النوع'
                            },
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }

            // رسم بياني لمستويات الحسابات
            const levelCtx = document.getElementById('accountLevelChart');
            if (levelCtx) {
                new Chart(levelCtx, {
                    type: 'bar',
                    data: {
                        labels: ['حسابات رئيسية', 'حسابات فرعية'],
                        datasets: [{
                            label: 'عدد الحسابات',
                            data: [{{ $mainAccounts }}, {{ $subAccounts }}],
                            backgroundColor: ['#0abb87', '#6f42c1'],
                            borderColor: ['#0abb87', '#6f42c1'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'توزيع الحسابات حسب المستوى'
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        }

        // =====================================
        // تصدير Excel باستخدام الملف المساعد
        // =====================================
        function exportChartOfAccountsReport() {
            var statistics = {
                total: {{ $totalAccounts }},
                main: {{ $mainAccounts }},
                sub: {{ $subAccounts }},
                debit: {{ $debitAccounts }},
                credit: {{ $creditAccounts }},
                withCostCenter: {{ $accountsWithCostCenter }},
                withBranch: {{ $accountsWithBranch }}
            };

            // جمع بيانات الجدول
            var tableData = [];
            
            $('#accountsTable tbody tr').each(function() {
                var cells = $(this).find('td');
                if (cells.length >= 7) {
                    var level = parseInt($(this).data('level')) || 0;
                    var indent = '  '.repeat(level);
                    var accountName = $(cells[1]).text().trim();
                    var accountType = $(cells[3]).find('.badge').text().trim();
                    
                    tableData.push({
                        values: [
                            $(cells[0]).text().trim(),
                            indent + accountName,
                            $(cells[2]).text().trim(),
                            accountType,
                            $(cells[4]).text().trim(),
                            $(cells[5]).text().trim(),
                            $(cells[6]).text().trim()
                        ],
                        cellStyles: [
                            null,
                            level === 0 ? { bold: true } : null,
                            null,
                            accountType === 'مدين' ? { color: 'success' } : { color: 'danger' },
                            null,
                            null,
                            null
                        ]
                    });
                }
            });

            // إعداد التقرير
            var config = {
                fileName: 'دليل_الحسابات',
                mainTitle: 'دليل الحسابات',
                subTitle: 'نظام المحاسبة المتكامل',
                
                reportInfo: {
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                },

                statisticsCards: [
                    {
                        title: 'إجمالي الحسابات',
                        value: statistics.total.toString(),
                        unit: ' حساب',
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'grayLight',
                        borderColor: 'primary'
                    },
                    {
                        title: 'الحسابات الرئيسية',
                        value: statistics.main.toString(),
                        unit: ' حساب',
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: 'الحسابات الفرعية',
                        value: statistics.sub.toString(),
                        unit: ' حساب',
                        titleColor: 'warning',
                        valueColor: 'warning',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: 'حسابات مدينة',
                        value: statistics.debit.toString(),
                        unit: ' حساب',
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successBg',
                        borderColor: 'success'
                    },
                    {
                        title: 'حسابات دائنة',
                        value: statistics.credit.toString(),
                        unit: ' حساب',
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerBg',
                        borderColor: 'danger'
                    }
                ],

                tableTitle: 'تفاصيل دليل الحسابات',
                tableHeaders: ['#', 'اسم الحساب', 'كود الحساب', 'نوع الحساب', 'مستوى الحساب', 'مركز التكلفة', 'الفرع'],
                columnWidths: [8, 35, 15, 12, 12, 20, 20],
                
                tableData: tableData,

                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'ملخص دليل الحسابات',
                    tables: [
                        {
                            title: 'إحصائيات الحسابات',
                            data: [
                                { label: 'إجمالي الحسابات', value: statistics.total.toString(), unit: 'حساب' },
                                { label: 'الحسابات الرئيسية', value: statistics.main.toString(), unit: 'حساب' },
                                { label: 'الحسابات الفرعية', value: statistics.sub.toString(), unit: 'حساب' },
                                { label: 'حسابات مدينة', value: statistics.debit.toString(), unit: 'حساب' },
                                { label: 'حسابات دائنة', value: statistics.credit.toString(), unit: 'حساب' },
                                { label: 'حسابات مع مركز تكلفة', value: statistics.withCostCenter.toString(), unit: 'حساب' },
                                { label: 'حسابات مع فرع', value: statistics.withBranch.toString(), unit: 'حساب' }
                            ]
                        }
                    ]
                },

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير دليل الحسابات بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            // استدعاء دالة التصدير
            exportToExcelProfessional(config);
        }

        // =====================================
        // طباعة التقرير
        // =====================================
        function printChartOfAccounts() {
            // إظهار جميع الصفوف قبل الطباعة
            var allRows = document.querySelectorAll('#accountsTable tbody tr');
            var rowsContent = '';
            
            allRows.forEach(function(row) {
                var cells = row.querySelectorAll('td');
                var level = parseInt(row.dataset.level) || 0;
                var indent = level * 15;
                var isParent = row.querySelector('.toggle-children') !== null;
                
                rowsContent += '<tr class="' + (isParent ? 'parent-row' : '') + '">';
                
                // رقم الحساب
                rowsContent += '<td class="text-center">' + cells[0].textContent.trim() + '</td>';
                
                // اسم الحساب مع المسافة البادئة
                rowsContent += '<td style="padding-right: ' + (indent + 8) + 'px; text-align: right;">';
                if (isParent) {
                    rowsContent += '<strong>' + cells[1].textContent.trim() + '</strong>';
                } else {
                    rowsContent += cells[1].textContent.trim();
                }
                rowsContent += '</td>';
                
                // باقي الخلايا
                for (var i = 2; i < cells.length; i++) {
                    var badge = cells[i].querySelector('.badge');
                    var cellText = badge ? badge.textContent : cells[i].textContent;
                    rowsContent += '<td class="text-center">' + cellText.trim() + '</td>';
                }
                
                rowsContent += '</tr>';
            });

            // فتح نافذة الطباعة
            var printWindow = window.open('', '', 'height=800,width=1200');
            
            var printContent = '<!DOCTYPE html>' +
                '<html dir="rtl" lang="ar">' +
                '<head>' +
                '<meta charset="UTF-8">' +
                '<title>دليل الحسابات</title>' +
                '<style>' +
                '* { margin: 0; padding: 0; box-sizing: border-box; }' +
                'body { font-family: Arial, Tahoma, sans-serif; padding: 20px; background-color: white; color: #333; line-height: 1.5; font-size: 11px; }' +
                '.header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }' +
                '.header h1 { font-size: 24px; color: #333; margin-bottom: 5px; font-weight: bold; }' +
                '.header p { color: #666; font-size: 11px; margin: 2px 0; }' +
                '.info-section { margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }' +
                '.info-row { display: flex; justify-content: space-between; flex-wrap: wrap; }' +
                '.info-item { flex: 1; text-align: center; min-width: 140px; margin: 3px; }' +
                '.info-item label { display: block; font-size: 10px; color: #666; margin-bottom: 2px; }' +
                '.info-item value { display: block; font-size: 14px; font-weight: bold; color: #333; }' +
                '.table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #333; font-size: 10px; }' +
                '.table thead th { background-color: #333; color: white; padding: 8px 5px; text-align: center; font-weight: bold; border: 1px solid #333; font-size: 11px; }' +
                '.table tbody td { padding: 6px 5px; border: 1px solid #ddd; text-align: center; }' +
                '.table tbody tr:nth-child(even) { background-color: #f8f9fa; }' +
                '.table tbody tr.parent-row { background-color: #e8f4fd; }' +
                '.table tbody tr.parent-row td { font-weight: bold; }' +
                '.badge { background-color: #6c757d; color: white; padding: 2px 6px; border-radius: 3px; font-size: 9px; }' +
                '.footer { margin-top: 20px; padding-top: 10px; border-top: 2px solid #333; text-align: center; color: #666; font-size: 10px; }' +
                '@media print { body { margin: 0; padding: 10px; } .header { page-break-after: avoid; } tr { page-break-inside: avoid; } thead { display: table-header-group; } }' +
                '</style>' +
                '</head>' +
                '<body>' +
                '<div class="header">' +
                '<h1>دليل الحسابات</h1>' +
                '<p>تاريخ الطباعة: ' + new Date().toLocaleDateString('ar-SA') + ' - ' + new Date().toLocaleTimeString('ar-SA') + '</p>' +
                '</div>' +
                '<div class="info-section">' +
                '<div class="info-row">' +
                '<div class="info-item"><label>إجمالي الحسابات</label><value>{{ $totalAccounts }} حساب</value></div>' +
                '<div class="info-item"><label>الحسابات الرئيسية</label><value>{{ $mainAccounts }} حساب</value></div>' +
                '<div class="info-item"><label>الحسابات الفرعية</label><value>{{ $subAccounts }} حساب</value></div>' +
                '</div>' +
                '<div class="info-row">' +
                '<div class="info-item"><label>حسابات مدينة</label><value>{{ $debitAccounts }} حساب</value></div>' +
                '<div class="info-item"><label>حسابات دائنة</label><value>{{ $creditAccounts }} حساب</value></div>' +
                '</div>' +
                '</div>' +
                '<table class="table">' +
                '<thead>' +
                '<tr>' +
                '<th style="width: 8%">#</th>' +
                '<th style="width: 30%">اسم الحساب</th>' +
                '<th style="width: 12%">كود الحساب</th>' +
                '<th style="width: 12%">نوع الحساب</th>' +
                '<th style="width: 12%">مستوى الحساب</th>' +
                '<th style="width: 13%">مركز التكلفة</th>' +
                '<th style="width: 13%">الفرع</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>' + rowsContent + '</tbody>' +
                '</table>' +
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

        // تصدير إلى CSV
        function exportToCSV() {
            const table = document.getElementById("accountsTable");
            const rows = table.querySelectorAll("tr");
            let csv = [];

            for (let i = 0; i < rows.length; i++) {
                const row = [],
                    cols = rows[i].querySelectorAll("td, th");
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + text + '"');
                }
                csv.push(row.join(","));
            }

            const csvContent = "\uFEFF" + csv.join("\n"); // Adding BOM for Arabic support
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            
            const today = new Date();
            const fileName = `دليل_الحسابات_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.csv`;
            
            link.setAttribute("href", url);
            link.setAttribute("download", fileName);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
@endsection
