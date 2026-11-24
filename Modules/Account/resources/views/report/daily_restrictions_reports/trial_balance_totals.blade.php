@extends('account::master')

@section('title')
    ميزان مراجعة أرصدة
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@endsection

@section('content')
    @include('account::components.page-head', [
        'title' => 'ميزان مراجعة أرصدة',
        'crumbs' => [
            'الرئيسية' => null,
            'التقارير' => route('GeneralAccountReports.index'),
            'ميزان مراجعة أرصدة' => null,
        ],
    ])

    <div class="content-body">
        <!-- Filter Card -->
        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="card-title">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <i class="feather icon-filter"></i> بحث وفلترة
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-success" id="exportExcel">
                                    <i class="fa fa-file-excel me-2"></i>تصدير إكسل
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="printBtn">
                                    <i class="fa fa-print me-2"></i>طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="trialBalanceForm" method="GET" action="{{ route('GeneralAccountReports.trialBalance') }}">
                        <div class="form-body row">
                            <!-- نوع الحساب -->
                            <div class="form-group col-md-3">
                                <label for="account_type">نوع الحساب</label>
                                <select name="account_type" id="account_type" class="form-control select2">
                                    <option value="">جميع الحسابات</option>
                                    <option value="1" {{ request('account_type') == '1' ? 'selected' : '' }}>أصول</option>
                                    <option value="2" {{ request('account_type') == '2' ? 'selected' : '' }}>خصوم</option>
                                    <option value="3" {{ request('account_type') == '3' ? 'selected' : '' }}>حقوق ملكية</option>
                                    <option value="4" {{ request('account_type') == '4' ? 'selected' : '' }}>إيرادات</option>
                                    <option value="5" {{ request('account_type') == '5' ? 'selected' : '' }}>مصروفات</option>
                                </select>
                            </div>

                            <!-- الفرع -->
                            <div class="form-group col-md-3">
                                <label for="branch_id">الفرع</label>
                                <select name="branch_id" id="branch_id" class="form-control select2">
                                    <option value="">جميع الفروع</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- المستخدم -->
                            <div class="form-group col-md-3">
                                <label for="user_id">المستخدم</label>
                                <select name="user_id" id="user_id" class="form-control select2">
                                    <option value="">جميع المستخدمين</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- مركز التكلفة -->
                            <div class="form-group col-md-3">
                                <label for="cost_center_id">مركز التكلفة</label>
                                <select name="cost_center_id" id="cost_center_id" class="form-control select2">
                                    <option value="">جميع مراكز التكلفة</option>
                                    @foreach($costCenters as $costCenter)
                                        <option value="{{ $costCenter->id }}" {{ request('cost_center_id') == $costCenter->id ? 'selected' : '' }}>{{ $costCenter->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- الفترة الزمنية -->
                            <div class="form-group col-md-3">
                                <label for="date_type">الفترة الزمنية</label>
                                <select name="date_type" id="date_type" class="form-control">
                                    <option value="custom" {{ request('date_type') == 'custom' ? 'selected' : '' }}>مخصص</option>
                                    <option value="today" {{ request('date_type') == 'today' ? 'selected' : '' }}>اليوم</option>
                                    <option value="yesterday" {{ request('date_type') == 'yesterday' ? 'selected' : '' }}>أمس</option>
                                    <option value="this_week" {{ request('date_type') == 'this_week' ? 'selected' : '' }}>هذا الأسبوع</option>
                                    <option value="last_week" {{ request('date_type') == 'last_week' ? 'selected' : '' }}>الأسبوع الماضي</option>
                                    <option value="this_month" {{ request('date_type') == 'this_month' ? 'selected' : '' }}>هذا الشهر</option>
                                    <option value="last_month" {{ request('date_type') == 'last_month' ? 'selected' : '' }}>الشهر الماضي</option>
                                    <option value="this_year" {{ request('date_type', 'this_year') == 'this_year' ? 'selected' : '' }}>هذا العام</option>
                                    <option value="last_year" {{ request('date_type') == 'last_year' ? 'selected' : '' }}>العام الماضي</option>
                                </select>
                            </div>

                            <!-- من تاريخ -->
                            {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى أدناه --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'start_date',
                                'toName' => 'end_date',
                                'colClass' => 'form-group col-md-3',
                            ])

                            <div class="form-group col-md-3" id="custom_dates">
                                <label for="start_date">من تاريخ</label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                    value="{{ request('start_date', $startDate ?? now()->startOfYear()->format('Y-m-d')) }}">
                            </div>

                            <!-- إلى تاريخ -->
                            <div class="form-group col-md-3" id="custom_to_date">
                                <label for="end_date">إلى تاريخ</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                    value="{{ request('end_date', $endDate ?? now()->format('Y-m-d')) }}">
                            </div>

                            <!-- مستوى الحساب -->
                            <div class="form-group col-md-3">
                                <label for="account_level">مستوى الحساب</label>
                                <select name="account_level" id="account_level" class="form-control">
                                    <option value="">جميع المستويات</option>
                                    <option value="1" {{ request('account_level') == '1' ? 'selected' : '' }}>المستوى الأول</option>
                                    <option value="2" {{ request('account_level') == '2' ? 'selected' : '' }}>المستوى الثاني</option>
                                    <option value="3" {{ request('account_level') == '3' ? 'selected' : '' }}>المستوى الثالث</option>
                                    <option value="4" {{ request('account_level') == '4' ? 'selected' : '' }}>المستوى الرابع</option>
                                </select>
                            </div>

                            <!-- خيارات -->
                            <div class="form-group col-md-3 d-flex align-items-end">
                                <div class="custom-control custom-checkbox mr-2">
                                    <input type="checkbox" id="hide_zero" name="hide_zero" class="custom-control-input" value="1" {{ request('hide_zero') ? 'checked' : '' }}>
                                    <label for="hide_zero" class="custom-control-label">إخفاء الأرصدة الصفرية</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light">
                                <i class="fa fa-search"></i> بحث
                            </button>
                            <a href="{{ route('GeneralAccountReports.trialBalance') }}" class="btn btn-outline-danger waves-effect waves-light">
                                <i class="fa fa-redo"></i> إعادة تعيين
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-up"></i></span>
                <span class="stk-metric__label">إجمالي المدين (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_debit'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-arrow-down"></i></span>
                <span class="stk-metric__label">إجمالي الدائن (ريال)</span>
                <span class="stk-metric__value">{{ number_format($totals['total_credit'] ?? 0, 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__label">الفرق (ريال)</span>
                <span class="stk-metric__value">{{ number_format(($totals['total_debit'] ?? 0) - ($totals['total_credit'] ?? 0), 2) }}</span>
            </div>

            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__label">عدد الحسابات</span>
                <span class="stk-metric__value">{{ $totals['accounts_count'] ?? 0 }}</span>
            </div>
        </div>
        <br>

        <!-- Results Table Card -->
        <div class="card" id="resultsCard">
            <div class="card-content">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> نتائج ميزان المراجعة</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-success mr-1" id="expandAll">
                                <i class="fa fa-expand"></i> فتح الكل
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning mr-2" id="collapseAll">
                                <i class="fa fa-compress"></i> طي الكل
                            </button>
                            <span class="text-muted">{{ count($accountsData ?? []) }} سجل</span>
                        </div>
                    </div>

                    <!-- Results Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm" id="trialBalanceTable">
                            <thead class="thead-gradient">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle" style="width: 20%">اسم الحساب</th>
                                    <th rowspan="2" class="text-center align-middle" style="width: 8%">الكود</th>
                                    <th colspan="2" class="text-center">الأرصدة</th>
                                </tr>
                                <tr>
                                    <th class="text-center">مدين</th>
                                    <th class="text-center">دائن</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($accountsData ?? [] as $account)
                                    @php
                                        $isParent = $account['has_children'];
                                        $level = $account['level'] ?? 0;
                                        $indent = $level * 25;
                                        
                                        // تحديد لون الخلفية والأيقونة حسب المستوى
                                        $levelColors = [
                                            0 => ['bg' => '#e8f4fd', 'border' => '#3498db', 'icon' => 'fa-folder'],
                                            1 => ['bg' => '#f0f9eb', 'border' => '#27ae60', 'icon' => 'fa-folder-open'],
                                            2 => ['bg' => '#fef9e7', 'border' => '#f39c12', 'icon' => 'fa-folder-open'],
                                            3 => ['bg' => '#fdf2f8', 'border' => '#e91e63', 'icon' => 'fa-folder-open'],
                                        ];
                                        $colorSet = $levelColors[$level] ?? ['bg' => '#fff', 'border' => '#95a5a6', 'icon' => 'fa-folder-open'];
                                        
                                        // إخفاء العناصر الفرعية افتراضياً
                                        $displayStyle = $level > 0 ? 'display: none;' : '';
                                    @endphp
                                    <tr class="account-row level-{{ $level }} {{ $isParent ? 'parent-row' : 'child-row' }}" 
                                        data-id="{{ $account['id'] }}" 
                                        data-bs-parent="{{ $account['parent_id'] ?? '' }}" 
                                        data-level="{{ $level }}" 
                                        data-expanded="false" 
                                        data-has-children="{{ $isParent ? 'true' : 'false' }}"
                                        style="{{ $displayStyle }}">
                                        <td class="account-name-cell">
                                            <div class="tree-node" style="padding-right: {{ $indent }}px;">
                                                @if($level > 0)
                                                    <span class="tree-line" style="width: {{ $indent - 10 }}px;"></span>
                                                @endif
                                                @if($isParent)
                                                    <span class="expand-btn" data-id="{{ $account['id'] }}">
                                                        <i class="fa fa-plus-square expand-icon"></i>
                                                    </span>
                                                    <span class="account-icon parent-icon" style="color: {{ $colorSet['border'] }};">
                                                        <i class="fa {{ $colorSet['icon'] }}"></i>
                                                    </span>
                                                @else
                                                    <span class="leaf-spacer"></span>
                                                    <span class="account-icon leaf-icon">
                                                        <i class="fa fa-file-invoice-dollar"></i>
                                                    </span>
                                                @endif
                                                <span class="account-name {{ $isParent ? 'parent-name' : 'leaf-name' }}">{{ $account['name'] ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">{{ $account['code'] ?? '-' }}</span>
                                        </td>
                                        <td class="text-left">{{ number_format($account['net_debit'] ?? 0, 2) }}</td>
                                        <td class="text-left">{{ number_format($account['net_credit'] ?? 0, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <i class="feather icon-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">لا توجد بيانات لعرضها</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($accountsData ?? []) > 0)
                            <tfoot>
                                <tr class="tfoot-gradient font-weight-bold">
                                    <td colspan="2" class="text-center">الإجمالي</td>
                                    <td class="text-left">{{ number_format($totals['net_debit'] ?? 0, 2) }}</td>
                                    <td class="text-left">{{ number_format($totals['net_credit'] ?? 0, 2) }}</td>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>

<script>
    $(document).ready(function() {
        // Initialize Select2 for dropdowns
        if ($.fn.select2) {
            $('.select2').select2({
                placeholder: 'اختر...',
                allowClear: true,
                dir: 'rtl'
            });
        }

        // Date Type Change Handler
        $('#date_type').on('change', function() {
            const value = $(this).val();
            if (value === 'custom') {
                $('#custom_dates, #custom_to_date').show();
            } else {
                $('#custom_dates, #custom_to_date').hide();
            }
        });

        // Initial check for date type
        if ($('#date_type').val() !== 'custom') {
            $('#custom_dates, #custom_to_date').hide();
        }

        // Export to Excel
        $('#exportExcel').on('click', function() {
            exportTrialBalanceTotalsReport();
        });

        // Print
        $('#printBtn').on('click', function() {
            printTrialBalanceTotals();
        });

        // ===== تحسين الأداء: بناء خرائط مرة واحدة =====
        const childrenMap = {};
        const rowsMap = {};
        
        document.querySelectorAll('tr.account-row').forEach(row => {
            const id = row.dataset.id;
            const parentId = row.dataset.parent;
            rowsMap[id] = row;
            if (parentId) {
                if (!childrenMap[parentId]) childrenMap[parentId] = [];
                childrenMap[parentId].push(id);
            }
        });

        // دالة إخفاء سريعة مع تأثيرات
        function fastHide(parentId) {
            const kids = childrenMap[parentId];
            if (!kids) return;
            for (let i = 0; i < kids.length; i++) {
                const row = rowsMap[kids[i]];
                row.style.opacity = '0';
                row.style.transform = 'translateX(-10px)';
                setTimeout(() => {
                    row.style.display = 'none';
                    row.style.opacity = '';
                    row.style.transform = '';
                }, 150);
                row.dataset.expanded = 'false';
                const expandBtn = row.querySelector('.expand-btn');
                if (expandBtn) {
                    expandBtn.classList.remove('expanded');
                    const icon = expandBtn.querySelector('.expand-icon');
                    if (icon) icon.className = 'fa fa-plus-square expand-icon';
                }
                fastHide(kids[i]);
            }
        }

        // دالة إظهار مع تأثيرات
        function fastShow(parentId) {
            const kids = childrenMap[parentId];
            if (!kids) return;
            kids.forEach((kidId, index) => {
                const row = rowsMap[kidId];
                row.style.display = '';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-10px)';
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, index * 30);
            });
        }

        // Toggle expand/collapse
        $(document).on('click', '.expand-btn', function(e) {
            e.stopPropagation();
            const parentId = this.dataset.id;
            const row = this.closest('tr');
            const expandBtn = this;
            const icon = this.querySelector('.expand-icon');
            
            if (row.dataset.expanded === 'true') {
                fastHide(parentId);
                row.dataset.expanded = 'false';
                expandBtn.classList.remove('expanded');
                if (icon) icon.className = 'fa fa-plus-square expand-icon';
            } else {
                fastShow(parentId);
                row.dataset.expanded = 'true';
                expandBtn.classList.add('expanded');
                if (icon) icon.className = 'fa fa-minus-square expand-icon';
            }
        });

        // Expand All - محسن مع تأثيرات
        $('#expandAll').on('click', function() {
            let delay = 0;
            for (const id in rowsMap) {
                const row = rowsMap[id];
                if (row.style.display === 'none') {
                    setTimeout(() => {
                        row.style.display = '';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-10px)';
                        setTimeout(() => {
                            row.style.opacity = '1';
                            row.style.transform = 'translateX(0)';
                        }, 50);
                    }, delay);
                    delay += 15;
                }
                row.dataset.expanded = 'true';
                const expandBtn = row.querySelector('.expand-btn');
                if (expandBtn) {
                    expandBtn.classList.add('expanded');
                    const icon = expandBtn.querySelector('.expand-icon');
                    if (icon) icon.className = 'fa fa-minus-square expand-icon';
                }
            }
        });

        // Collapse All - محسن
        $('#collapseAll').on('click', function() {
            for (const id in rowsMap) {
                const row = rowsMap[id];
                if (row.dataset.level !== '0') {
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.style.display = 'none';
                        row.style.opacity = '';
                    }, 150);
                }
                row.dataset.expanded = 'false';
                const expandBtn = row.querySelector('.expand-btn');
                if (expandBtn) {
                    expandBtn.classList.remove('expanded');
                    const icon = expandBtn.querySelector('.expand-icon');
                    if (icon) icon.className = 'fa fa-plus-square expand-icon';
                }
            }
        });

        // إضافة تأثير hover على الصفوف
        $(document).on('mouseenter', '.account-row', function() {
            $(this).css('box-shadow', '0 2px 8px rgba(0,0,0,0.08)');
        }).on('mouseleave', '.account-row', function() {
            $(this).css('box-shadow', 'none');
        });
    });

    // =====================================
    // تصدير Excel باستخدام الملف المساعد
    // =====================================
    function exportTrialBalanceTotalsReport() {
        // جمع الإحصائيات من PHP
        var totals = {
            total_debit: {{ $totals['total_debit'] ?? 0 }},
            total_credit: {{ $totals['total_credit'] ?? 0 }},
            accounts_count: {{ $totals['accounts_count'] ?? 0 }}
        };

        var startDate = '{{ $startDate ?? now()->startOfYear()->format("Y-m-d") }}';
        var endDate = '{{ $endDate ?? now()->format("Y-m-d") }}';
        var branchName = $('#branch_id option:selected').text() || 'جميع الفروع';
        var accountType = $('#account_type option:selected').text() || 'جميع الحسابات';

        // جمع بيانات الجدول
        var tableData = [];
        
        $('#trialBalanceTable tbody tr.account-row').each(function() {
            var cells = $(this).find('td');
            if (cells.length >= 4) {
                var level = parseInt($(this).data('level')) || 0;
                var indent = '  '.repeat(level);
                var accountName = $(cells[0]).find('.account-name').text().trim();
                
                tableData.push({
                    values: [
                        indent + accountName,
                        $(cells[1]).find('.badge').text().trim() || $(cells[1]).text().trim(),
                        $(cells[2]).text().trim(),
                        $(cells[3]).text().trim()
                    ],
                    cellStyles: [
                        level === 0 ? { bold: true } : null,
                        null,
                        { color: 'success' },
                        { color: 'danger' }
                    ]
                });
            }
        });

        // إعداد التقرير
        var config = {
            fileName: 'ميزان_مراجعة_أرصدة',
            mainTitle: 'ميزان مراجعة أرصدة',
            subTitle: 'نظام المحاسبة المتكامل',
            
            reportInfo: {
                'الفترة': 'من ' + startDate + ' إلى ' + endDate,
                'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                'الفرع': branchName,
                'نوع الحساب': accountType
            },

            statisticsCards: [
                {
                    title: 'إجمالي المدين',
                    value: formatNumber(totals.total_debit),
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'success',
                    valueColor: 'success',
                    backgroundColor: 'successLight',
                    borderColor: 'success'
                },
                {
                    title: 'إجمالي الدائن',
                    value: formatNumber(totals.total_credit),
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'danger',
                    valueColor: 'danger',
                    backgroundColor: 'dangerLight',
                    borderColor: 'danger'
                },
                {
                    title: 'الفرق',
                    value: formatNumber(totals.total_debit - totals.total_credit),
                    unit: ' ' + window.AppCurrency.symbol,
                    titleColor: 'primary',
                    valueColor: (totals.total_debit - totals.total_credit) >= 0 ? 'success' : 'danger',
                    backgroundColor: 'grayLight',
                    borderColor: 'primary'
                },
                {
                    title: 'عدد الحسابات',
                    value: totals.accounts_count.toString(),
                    unit: ' حساب',
                    titleColor: 'warning',
                    valueColor: 'warning',
                    backgroundColor: 'warningLight',
                    borderColor: 'warning'
                }
            ],

            tableTitle: 'تفاصيل ميزان المراجعة',
            tableHeaders: ['اسم الحساب', 'الكود', 'رصيد مدين', 'رصيد دائن'],
            columnWidths: [40, 15, 20, 20],
            
            tableData: tableData,

            summaryRows: [
                {
                    label: 'الإجمالي',
                    values: [
                        'الإجمالي',
                        '',
                        formatNumber(totals.total_debit),
                        formatNumber(totals.total_credit)
                    ],
                    color: 'primary',
                    cellStyles: [
                        { bold: true },
                        null,
                        { color: 'success', bold: true },
                        { color: 'danger', bold: true }
                    ]
                }
            ],

            summarySheet: {
                sheetName: 'الملخص',
                title: 'ملخص ميزان المراجعة',
                tables: [
                    {
                        title: 'إحصائيات الأرصدة',
                        data: [
                            { label: 'إجمالي المدين', value: formatNumber(totals.total_debit), unit: window.AppCurrency.symbol },
                            { label: 'إجمالي الدائن', value: formatNumber(totals.total_credit), unit: window.AppCurrency.symbol },
                            { label: 'الفرق', value: formatNumber(totals.total_debit - totals.total_credit), unit: window.AppCurrency.symbol, highlight: true },
                            { label: 'عدد الحسابات', value: totals.accounts_count.toString(), unit: 'حساب' }
                        ]
                    }
                ]
            },

            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير ميزان المراجعة بنجاح!',
            errorMessage: 'حدث خطأ أثناء تصدير التقرير'
        };

        // استدعاء دالة التصدير
        exportToExcelProfessional(config);
    }

    // =====================================
    // طباعة التقرير
    // =====================================
    function printTrialBalanceTotals() {
        // إظهار جميع الصفوف قبل الطباعة
        var allRows = document.querySelectorAll('#trialBalanceTable tbody tr.account-row');
        var rowsContent = '';
        
        allRows.forEach(function(row) {
            var cells = row.querySelectorAll('td');
            var level = parseInt(row.dataset.level) || 0;
            var indent = level * 15;
            var accountName = row.querySelector('.account-name');
            var isParent = row.dataset.hasChildren === 'true';
            
            rowsContent += '<tr class="' + (isParent ? 'parent-row' : '') + '">';
            
            // اسم الحساب مع المسافة البادئة
            rowsContent += '<td style="padding-right: ' + (indent + 8) + 'px; text-align: right;">';
            if (isParent) {
                rowsContent += '<strong>' + (accountName ? accountName.textContent : '') + '</strong>';
            } else {
                rowsContent += (accountName ? accountName.textContent : '');
            }
            rowsContent += '</td>';
            
            // باقي الخلايا
            for (var i = 1; i < cells.length; i++) {
                var badge = cells[i].querySelector('.badge');
                var cellText = badge ? badge.textContent : cells[i].textContent;
                rowsContent += '<td class="text-center">' + cellText.trim() + '</td>';
            }
            
            rowsContent += '</tr>';
        });

        // جمع بيانات tfoot
        var tfootContent = '';
        var tfoot = document.querySelector('#trialBalanceTable tfoot');
        if (tfoot) {
            tfootContent = tfoot.innerHTML;
        }

        // فتح نافذة الطباعة
        var printWindow = window.open('', '', 'height=800,width=1200');
        
        var printContent = '<!DOCTYPE html>' +
            '<html dir="rtl" lang="ar">' +
            '<head>' +
            '<meta charset="UTF-8">' +
            '<title>ميزان مراجعة أرصدة</title>' +
            '<style>' +
            '* { margin: 0; padding: 0; box-sizing: border-box; }' +
            'body { font-family: Arial, Tahoma, sans-serif; padding: 20px; background-color: white; color: #333; line-height: 1.5; font-size: 12px; }' +
            '.header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }' +
            '.header h1 { font-size: 24px; color: #333; margin-bottom: 5px; font-weight: bold; }' +
            '.header p { color: #666; font-size: 12px; margin: 2px 0; }' +
            '.info-section { margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }' +
            '.info-row { display: flex; justify-content: space-between; flex-wrap: wrap; }' +
            '.info-item { flex: 1; text-align: center; min-width: 120px; margin: 3px; }' +
            '.info-item label { display: block; font-size: 11px; color: #666; margin-bottom: 2px; }' +
            '.info-item value { display: block; font-size: 16px; font-weight: bold; color: #333; }' +
            '.text-success { color: #28a745 !important; }' +
            '.text-danger { color: #dc3545 !important; }' +
            '.table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #333; font-size: 11px; }' +
            '.table thead th { background-color: #333; color: white; padding: 8px 6px; text-align: center; font-weight: bold; border: 1px solid #333; font-size: 12px; }' +
            '.table tbody td { padding: 6px; border: 1px solid #ddd; text-align: center; }' +
            '.table tbody tr:nth-child(even) { background-color: #f8f9fa; }' +
            '.table tbody tr.parent-row { background-color: #e8f4fd; }' +
            '.table tbody tr.parent-row td { font-weight: bold; }' +
            '.table tfoot tr { font-weight: bold; font-size: 12px; background-color: #e9ecef; }' +
            '.table tfoot td { padding: 10px 6px; border: 1px solid #333; }' +
            '.footer { margin-top: 20px; padding-top: 10px; border-top: 2px solid #333; text-align: center; color: #666; font-size: 10px; }' +
            '@media print { body { margin: 0; padding: 10px; } .header { page-break-after: avoid; } tr { page-break-inside: avoid; } thead { display: table-header-group; } tfoot { display: table-footer-group; } }' +
            '</style>' +
            '</head>' +
            '<body>' +
            '<div class="header">' +
            '<h1>ميزان مراجعة أرصدة</h1>' +
            '<p>الفترة: من {{ $startDate ?? now()->startOfYear()->format("Y-m-d") }} إلى {{ $endDate ?? now()->format("Y-m-d") }}</p>' +
            '<p>تاريخ الطباعة: ' + new Date().toLocaleDateString('ar-SA') + ' - ' + new Date().toLocaleTimeString('ar-SA') + '</p>' +
            '</div>' +
            '<div class="info-section">' +
            '<div class="info-row">' +
            '<div class="info-item"><label>إجمالي المدين</label><value class="text-success">@money($totals["total_debit"] ?? 0)</value></div>' +
            '<div class="info-item"><label>إجمالي الدائن</label><value class="text-danger">@money($totals["total_credit"] ?? 0)</value></div>' +
            '<div class="info-item"><label>الفرق</label><value>@money(($totals["total_debit"] ?? 0) - ($totals["total_credit"] ?? 0))</value></div>' +
            '<div class="info-item"><label>عدد الحسابات</label><value>{{ $totals["accounts_count"] ?? 0 }} حساب</value></div>' +
            '</div>' +
            '</div>' +
            '<table class="table">' +
            '<thead>' +
            '<tr>' +
            '<th style="width: 50%">اسم الحساب</th>' +
            '<th style="width: 15%">الكود</th>' +
            '<th style="width: 17.5%">رصيد مدين</th>' +
            '<th style="width: 17.5%">رصيد دائن</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody>' + rowsContent + '</tbody>' +
            (tfootContent ? '<tfoot>' + tfootContent + '</tfoot>' : '') +
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
</script>
@endsection
