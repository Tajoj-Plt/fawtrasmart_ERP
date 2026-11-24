@extends('account::master')

@section('title')
    الأستاذ العام
@stop

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/ledger-style.css') }}">
    @include('account::components.page-head', [
        'title' => 'دفتر الأستاذ العام',
        'icon' => 'book-open',
        'sub' => 'حركة كل حساب وأرصدته خلال الفترة',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الحسابات' => null,
            'دفتر الأستاذ' => null,
        ],
    ])

    <div class="content-body">

            @php 
                $debitTotal = $totals['total_debit'] ?? 0;
                $creditTotal = $totals['total_credit'] ?? 0;
                $balanceTotal = $totals['total_balance'] ?? 0;
            @endphp

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card income-card">
                        <div class="card-header d-flex flex-column align-items-start pb-0">
                            <div class="avatar bg-rgba-success p-50 m-0">
                                <div class="avatar-content">
                                    <i class="feather icon-arrow-up text-success font-medium-5"></i>
                                </div>
                            </div>
                            <h2 class="text-bold-700 mt-1">{{ number_format($debitTotal, 2) }}</h2>
                            <p class="mb-0">إجمالي المدين</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card expense-card">
                        <div class="card-header d-flex flex-column align-items-start pb-0">
                            <div class="avatar bg-rgba-danger p-50 m-0">
                                <div class="avatar-content">
                                    <i class="feather icon-arrow-down text-danger font-medium-5"></i>
                                </div>
                            </div>
                            <h2 class="text-bold-700 mt-1">{{ number_format($creditTotal, 2) }}</h2>
                            <p class="mb-0">إجمالي الدائن</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card treasury-card">
                        <div class="card-header d-flex flex-column align-items-start pb-0">
                            <div class="avatar bg-rgba-primary p-50 m-0">
                                <div class="avatar-content">
                                    <i class="feather icon-dollar-sign text-primary font-medium-5"></i>
                                </div>
                            </div>
                            <h2 class="text-bold-700 mt-1">{{ number_format($balanceTotal, 2) }}</h2>
                            <p class="mb-0">صافي الرصيد</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12">
                    <div class="card stat-card balance-card">
                        <div class="card-header d-flex flex-column align-items-start pb-0">
                            <div class="avatar bg-rgba-warning p-50 m-0">
                                <div class="avatar-content">
                                    <i class="feather icon-activity text-warning font-medium-5"></i>
                                </div>
                            </div>
                            <h2 class="text-bold-700 mt-1">{{ $totals['total_count'] ?? 0 }}</h2>
                            <p class="mb-0">عدد القيود</p>
                        </div>
                    </div>
                </div>
            </div>
            <br>
            
        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="card-title">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h5 class="mb-0">
                                    <i class="feather icon-filter"></i> بحث وفلترة متقدمة
                                </h5>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary" id="toggleAllBtn" title="طي/فتح جميع الحسابات">
                                    <i class="feather icon-minimize-2"></i> طي الكل
                                </button>
                                <button class="btn btn-outline-primary" id="exportExcel" title="تصدير البيانات إلى ملف Excel">
                                    <i class="fa fa-file-excel me-2"></i>تصدير إكسل
                                </button>
                                <button class="btn btn-outline-primary" id="exportPDF" title="تصدير البيانات إلى ملف PDF">
                                    <i class="fa fa-file-pdf me-2"></i>تصدير PDF
                                </button>
                                <button class="btn btn-outline-primary" id="printBtn" title="طباعة التقرير">
                                    <i class="fa fa-print me-2"></i>طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="filterForm" method="GET" action="{{ route('journal.generalLedger') }}">
                        <div class="form-body row">
                            {{--
                                الفترة المحاسبية: اختيارها يملأ حقلَي المدى.

                                كان دفتر الأستاذ الشاشةَ الوحيدة في التقارير
                                المحاسبية بلا هذا الفلتر — يُقرأ فيه المدى
                                بتاريخين يُكتبان بيدٍ، فيختلف عمّا يُقرأ في
                                ميزان المراجعة وقائمة الدخل.
                            --}}
                            @include('partials.accounting-period-filter', [
                                'fromName' => 'fromDate',
                                'toName' => 'toDate',
                                'colClass' => 'form-group col-md-3',
                                'period' => $reportPeriod ?? [],
                            ])

                            <div class="form-group col-md-3">
                                <label for="account_id">
                                    <i class="feather icon-hash"></i> الحساب
                                </label>
                                <select name="account_id" class="form-control ledger-select2">
                                    <option value="">جميع الحسابات</option>
                                    @foreach($accounts_for_filter as $filterAccount)
                                        <option value="{{ $filterAccount->id }}"
                                            {{ (string)$selected_account_id === (string)$filterAccount->id ? 'selected' : '' }}>
                                            {{ $filterAccount->code }} - {{ $filterAccount->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-3">
                                <label for="account_type">
                                    <i class="feather icon-list"></i> نوع الحساب
                                </label>
                                <select name="account_type" class="form-control ledger-select2">
                                    <option value="">جميع الأنواع</option>
                                    @foreach($account_types as $key => $type)
                                        <option value="{{ $key }}" 
                                            {{ $selected_account_type == $key ? 'selected' : '' }}>
                                            {{ $type }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <label for="account_level">
                                    <i class="feather icon-layers"></i> مستوى الحساب
                                </label>
                                <select name="account_level" class="form-control ledger-select2">
                                    <option value="">الكل</option>
                                    <option value="parent" {{ $selected_account_level == 'parent' ? 'selected' : '' }}>حسابات رئيسية فقط</option>
                                    <option value="child" {{ $selected_account_level == 'child' ? 'selected' : '' }}>حسابات فرعية فقط</option>
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <label for="branch_id">
                                    <i class="feather icon-git-branch"></i> الفرع
                                </label>
                                <select name="branch_id" class="form-control ledger-select2">
                                    <option value="">جميع الفروع</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" 
                                            {{ $selected_branch == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <label for="employee_id">
                                    <i class="feather icon-user"></i> الموظف
                                </label>
                                <select name="employee_id" class="form-control ledger-select2">
                                    <option value="">جميع الموظفين</option>
                                    @foreach($employees as $employee)
                                        {{-- حارسٌ ثانٍ: الفلتر يقارن بمعرّف المستخدم، والموظف بلا مستخدم يُتخطّى --}}
                                        @continue(! $employee->user)
                                        <option value="{{ $employee->user->id }}"
                                            {{ $selected_employee == $employee->user->id ? 'selected' : '' }}>
                                            {{ $employee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <label for="cost_center_id">
                                    <i class="feather icon-target"></i> مركز التكلفة
                                </label>
                                <select name="cost_center_id" class="form-control ledger-select2">
                                    <option value="">جميع مراكز التكلفة</option>
                                    @foreach($cost_centers as $costCenter)
                                        <option value="{{ $costCenter->id }}" 
                                            {{ $selected_cost_center == $costCenter->id ? 'selected' : '' }}>
                                            {{ $costCenter->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <label for="fromDate">
                                    <i class="feather icon-calendar"></i> من تاريخ
                                </label>
                                <input type="date" name="fromDate" value="{{ $from_date }}" class="form-control">
                            </div>
                            <div class="form-group col-md-3">
                                <label for="toDate">
                                    <i class="feather icon-calendar"></i> إلى تاريخ
                                </label>
                                <input type="date" name="toDate" value="{{ $to_date }}" class="form-control">
                            </div>
                        </div>

                        <div class="stk-form-foot">
                            <button type="submit" class="btn btn-primary mr-1 waves-effect waves-light">
                                <i class="feather icon-search"></i> عرض
                            </button>
                            <a href="{{ route('journal.generalLedger') }}" class="btn btn-outline-danger waves-effect waves-light">
                                <i class="feather icon-x"></i> الغاء الفلترة
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- معلومات الفترة -->
        @if($from_date || $to_date || $selected_account_type || $selected_branch || $selected_employee || $selected_cost_center || $selected_account_level || $selected_account_id)
            <div class="alert bg-purple-transparent-80 mb-4">
                <strong><i class="feather icon-filter"></i> الفلاتر المطبقة:</strong>
                
                @if($from_date && $to_date)
                    <span class="badge bg-primary mr-1">الفترة: من {{ $from_date }} إلى {{ $to_date }}</span>
                @elseif($from_date)
                    <span class="badge bg-primary mr-1">من تاريخ: {{ $from_date }}</span>
                @elseif($to_date)
                    <span class="badge bg-primary mr-1">حتى تاريخ: {{ $to_date }}</span>
                @endif
                
                @if($selected_account_type)
                    <span class="badge bg-success mr-1">نوع الحساب: {{ $account_types[$selected_account_type] }}</span>
                @endif

                @if($selected_account_id)
                    @php
                        $selectedAccountFilter = $accounts_for_filter->firstWhere('id', $selected_account_id);
                    @endphp
                    <span class="badge bg-primary mr-1">
                        الحساب: {{ $selectedAccountFilter ? ($selectedAccountFilter->code . ' - ' . $selectedAccountFilter->name) : 'غير معروف' }}
                    </span>
                @endif
                
                @if($selected_account_level)
                    <span class="badge bg-info mr-1">
                        مستوى الحساب: {{ $selected_account_level == 'parent' ? 'حسابات رئيسية' : 'حسابات فرعية' }}
                    </span>
                @endif
                
                @if($selected_branch)
                    @php
                        $branchName = $branches->firstWhere('id', $selected_branch)->name ?? '';
                    @endphp
                    <span class="badge bg-warning mr-1">الفرع: {{ $branchName }}</span>
                @endif
                
                @if($selected_employee)
                    @php
                        $selectedEmp = $employees->first(function($emp) use ($selected_employee) {
                            return $emp->user->id == $selected_employee;
                        });
                        $employeeName = $selectedEmp ? $selectedEmp->name : '';
                    @endphp
                    <span class="badge bg-secondary mr-1">الموظف: {{ $employeeName }}</span>
                @endif
                
                @if($selected_cost_center)
                    @php
                        $costCenterName = $cost_centers->firstWhere('id', $selected_cost_center)->name ?? '';
                    @endphp
                    <span class="badge bg-dark mr-1">مركز التكلفة: {{ $costCenterName }}</span>
                @endif
            </div>
        @endif

        @if(count($accountsWithEntries) > 0)
            <!-- عرض كل حساب مع قيوده -->
            <div id="reportContainer">
                @foreach($accountsWithEntries as $accountIndex => $accountData)
                    @php
                        $account = $accountData['account'];
                        $entries = $accountData['entries'];
                        $openingBalance = $accountData['opening_balance'];
                        $runningBalance = $openingBalance;
                    @endphp
                    
                    <div class="account-section mb-4">
                        <!-- عنوان الحساب -->
                        <div class="card">
                            <div class="card-content">
                                <div class="card-body">
                                    <div class="card-title">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                                            <div>
                                                <h4 class="mb-0">
                                                    <i class="feather icon-folder"></i>
                                                    {{ $account->code }} - {{ $account->name }}
                                                    <span class="badge bg-primary ml-2">
                                                        {{ $account->account_type_translation }}
                                                    </span>
                                                    <span class="badge bg-info ml-1">
                                                        {{ $accountData['count'] }} قيد
                                                    </span>
                                                    <span class="badge bg-success ml-1">
                                                        الرصيد: {{ number_format($accountData['final_balance'], 2) }}
                                                    </span>
                                                </h4>
                                            </div>
                                            <div>
                                                <button class="btn btn-outline-secondary btn-sm toggle-account-btn" 
                                                        data-bs-target="account-table-{{ $accountIndex }}" 
                                                        data-expanded="true"
                                                        title="طي/فتح القيود">
                                                    <i class="feather icon-chevron-up"></i> طي
                                                </button>
                                                <button class="btn btn-outline-success btn-sm" onclick="exportAccountToExcel({{ $accountIndex }})" title="تصدير إلى Excel">
                                                    <i class="fa fa-file-excel"></i> إكسل
                                                </button>
                                                <button class="btn btn-outline-info btn-sm" onclick="exportAccountToPDF({{ $accountIndex }})" title="تصدير إلى PDF">
                                                    <i class="fa fa-file-pdf"></i> PDF
                                                </button>
                                                <button class="btn btn-outline-primary btn-sm" onclick="printAccount({{ $accountIndex }})" title="طباعة">
                                                    <i class="fa fa-print"></i> طباعة
                                                </button>
                                                <a class="btn btn-outline-dark btn-sm"
                                                   href="{{ route('account_statement.statement', $account->id) }}?all=1&from_date={{ $from_date }}&to_date={{ $to_date }}"
                                                   target="_blank"
                                                   title="طباعة كشف الحساب">
                                                    <i class="fa fa-file-text-o"></i> كشف الحساب
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body p-0 account-table-container" id="account-table-{{ $accountIndex }}">
                                    <div class="table-responsive" id="account-container-{{ $accountIndex }}">
                                        <table class="table table-bordered table-hover mb-0 ledger-table dataTable" id="DataTable_Account_{{ $accountIndex }}" role="grid">
                                            <thead>
                                                <tr role="row" class="text-center">
                                                    <th class="sorting">التاريخ</th>
                                                    <th class="sorting">رقم القيد</th>
                                                    <th class="sorting">البيان</th>
                                                    <th class="sorting">مدين</th>
                                                    <th class="sorting">دائن</th>
                                                    <th class="sorting">الرصيد</th>
                                                    <th class="sorting">نوع الرصيد</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <tbody>
                                                <!-- صف الرصيد الافتتاحي -->
                                                <tr class="font-weight-bold" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                                    <td colspan="3" class="text-center">
                                                        <i class="feather icon-flag"></i> الرصيد الافتتاحي
                                                    </td>
                                                    <td class="text-center">-</td>
                                                    <td class="text-center">-</td>
                                                    <td class="text-center">
                                                        {{-- المحسوبُ قبل بداية الفترة — وعليه يُبنى الرصيدُ المتتابع
                                                             أدناه. كان يُعرض عمودُ الحساب الثابت، فيُقرأ «0.00» ثم
                                                             يقفز أوّلُ سطرٍ إلى رصيدٍ لا يُفسَّر --}}
                                                        {{ number_format($openingBalance, 2) }}
                                                    </td>
                                                    <td class="text-center">
                                                        @if($runningBalance > 0)
                                                            <span class="badge bg-success">
                                                                {{ $account->balance_type === 'debit' ? 'مدين' : 'دائن' }}
                                                            </span>
                                                        @elseif($runningBalance < 0)
                                                            <span class="badge bg-danger">
                                                                {{ $account->balance_type === 'debit' ? 'دائن' : 'مدين' }}
                                                            </span>
                                                        @else
                                                            <span class="badge bg-secondary">متوازن</span>
                                                        @endif
                                                    </td>
                                                </tr>

                                                <!-- القيود -->
                                                @foreach($entries as $entry)
                                                    @php
                                                        // تحديث الرصيد الجاري
                                                        if ($account->balance_type === 'debit') {
                                                            $runningBalance = $runningBalance + $entry->debit - $entry->credit;
                                                        } else {
                                                            $runningBalance = $runningBalance + $entry->credit - $entry->debit;
                                                        }
                                                    @endphp
                                                    
                                                    <tr>
                                                        <td class="text-center">
                                                            {{ $entry->journalEntry->date->format('Y-m-d') }}
                                                        </td>
                                                        <td class="text-center">
                                                            <a href="{{ route('journal.show', $entry->journalEntry->id) }}" 
                                                               target="_blank" class="text-primary">
                                                                <span class="badge bg-secondary">{{ $entry->journalEntry->reference_number }}</span>
                                                            </a>
                                                        </td>
                                                        <td>{{ $entry->description ?? $entry->journalEntry->description }}</td>
                                                        <td class="text-center text-success">
                                                            {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}
                                                        </td>
                                                        <td class="text-center text-danger">
                                                            {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}
                                                        </td>
                                                        <td class="text-center font-weight-bold">
                                                            {{ number_format($runningBalance, 2) }}
                                                        </td>
                                                        <td class="text-center">
                                                            @if($runningBalance > 0)
                                                                <span class="badge bg-success">
                                                                    {{ $account->balance_type === 'debit' ? 'مدين' : 'دائن' }}
                                                                </span>
                                                            @elseif($runningBalance < 0)
                                                                <span class="badge bg-danger">
                                                                    {{ $account->balance_type === 'debit' ? 'دائن' : 'مدين' }}
                                                                </span>
                                                            @else
                                                                <span class="badge bg-secondary">متوازن</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <!-- صف الإجماليات -->
                                                <tr class="bg-light font-weight-bold">
                                                    <td colspan="3" class="text-center">
                                                        <i class="feather icon-trending-up"></i> الإجماليات
                                                    </td>
                                                    <td class="text-center text-success">
                                                        {{ number_format($accountData['total_debit'], 2) }}
                                                    </td>
                                                    <td class="text-center text-danger">
                                                        {{ number_format($accountData['total_credit'], 2) }}
                                                    </td>
                                                    <td colspan="2" class="text-center">-</td>
                                                </tr>

                                                <!-- صف الرصيد النهائي -->
                                                <tr class="bg-light font-weight-bold">
                                                    <td colspan="3" class="text-center">
                                                        <i class="feather icon-check-circle"></i> الرصيد النهائي
                                                    </td>
                                                    <td colspan="2" class="text-center">-</td>
                                                    <td class="text-center">
                                                        {{ number_format($accountData['final_balance'], 2) }}
                                                    </td>
                                                    <td class="text-center">
                                                        @if($accountData['final_balance'] > 0)
                                                            <span class="badge bg-success">
                                                                {{ $account->balance_type === 'debit' ? 'مدين' : 'دائن' }}
                                                            </span>
                                                        @elseif($accountData['final_balance'] < 0)
                                                            <span class="badge bg-danger">
                                                                {{ $account->balance_type === 'debit' ? 'دائن' : 'مدين' }}
                                                            </span>
                                                        @else
                                                            <span class="badge bg-secondary">متوازن</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if(isset($accountsPagination))
                <div class="mt-2">
                    {{ $accountsPagination->links() }}
                </div>
            @endif
        @else
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-warning text-center" role="alert">
                        <i class="feather icon-alert-circle font-large-2"></i>
                        <h4 class="alert-heading">لا توجد بيانات</h4>
                        <p class="mb-0">لم يتم العثور على أي قيود للفترة والمعايير المحددة</p>
                        <p class="mb-0">يرجى تعديل معايير البحث والمحاولة مرة أخرى</p>
                    </div>
                </div>
            </div>
        @endif

    </div>
<!-- استبدل فقط قسم الـ style في نهاية الملف -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
{{--
    مكتبتا التصدير المعتمدتان في تقارير النظام.

    كانت الشاشة تحمّل `xlsx` و`html2pdf`: الأولى تُصدِّر الجدول خاماً بلا
    ترويسة ولا اسم منشأة ولا تنسيق — مجرّد خلايا؛ والثانية تُصوِّر الصفحة
    صورةً وتحشرها في PDF، فيخرج نصٌّ لا يُبحث فيه ولا يُنسخ منه، ويقتطع
    الجدولَ الطويل حيث انتهت الشاشة.

    `exportToExcelProfessional` و`printTable` هما ما تستعمله بقية التقارير
    (انظر `report/expenses/expenses_by_category`)، وكلاهما يقرأ اسم
    المنشأة وعنوانها من `window.AppOrg`.
--}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>

<script>
    $(document).ready(function() {
        // تفعيل Select2 لكل حقول الاختيار في الفلاتر
        if ($.fn.select2) {
            $('.ledger-select2').select2({
                width: '100%',
                dir: 'rtl'
            });
        }

        // التعامل مع تصدير إكسل
        $('#exportExcel').click(function() {
            exportToExcel();
        });

        // التعامل مع تصدير PDF
        $('#exportPDF').click(function() {
            exportToPDF();
        });

        // التعامل مع الطباعة المحسّنة
        $('#printBtn').click(function() {
            printLedger();
        });

        // التعامل مع زر طي/فتح لكل حساب
        $('.toggle-account-btn').click(function() {
            const targetId = $(this).data('target');
            const target = $('#' + targetId);
            const isExpanded = $(this).data('expanded');
            
            if (isExpanded) {
                // طي
                target.slideUp(300);
                $(this).data('expanded', false);
                $(this).html('<i class="feather icon-chevron-down"></i> فتح');
                $(this).removeClass('btn-outline-secondary').addClass('btn-warning');
            } else {
                // فتح
                target.slideDown(300);
                $(this).data('expanded', true);
                $(this).html('<i class="feather icon-chevron-up"></i> طي');
                $(this).removeClass('btn-warning').addClass('btn-outline-secondary');
            }
        });

        // التعامل مع زر طي/فتح الكل
        let allExpanded = true;
        $('#toggleAllBtn').click(function() {
            if (allExpanded) {
                // طي الكل
                $('.account-table-container').slideUp(300);
                $('.toggle-account-btn').each(function() {
                    $(this).data('expanded', false);
                    $(this).html('<i class="feather icon-chevron-down"></i> فتح');
                    $(this).removeClass('btn-outline-secondary').addClass('btn-warning');
                });
                $(this).html('<i class="feather icon-maximize-2"></i> فتح الكل');
                allExpanded = false;
            } else {
                // فتح الكل
                $('.account-table-container').slideDown(300);
                $('.toggle-account-btn').each(function() {
                    $(this).data('expanded', true);
                    $(this).html('<i class="feather icon-chevron-up"></i> طي');
                    $(this).removeClass('btn-warning').addClass('btn-outline-secondary');
                });
                $(this).html('<i class="feather icon-minimize-2"></i> طي الكل');
                allExpanded = true;
            }
        });
    });

    /*
        ═══════════════ التصدير والطباعة ═══════════════

        كل الدوال هنا تمرّ عبر `exportToExcelProfessional` و`printTable`،
        فتخرج الورقة بترويسة المنشأة وبطاقات الإجماليات وتنسيق الجدول
        نفسه المعتمد في بقية تقارير النظام.
    */

    const LEDGER_HEADERS = ['التاريخ', 'رقم القيد', 'البيان', 'مدين', 'دائن', 'الرصيد', 'نوع الرصيد'];
    const LEDGER_NUMERIC = [3, 4, 5];
    const LEDGER_WIDTHS = [14, 14, 38, 16, 16, 16, 14];

    /** بطاقات الإجماليات المعروضة أعلى الشاشة */
    function ledgerCards() {
        return [
            {
                title: 'إجمالي المدين',
                value: @json(number_format((float) ($totals['total_debit'] ?? 0), 2)),
                titleColor: 'success', valueColor: 'success',
                backgroundColor: 'successLight', borderColor: 'success',
            },
            {
                title: 'إجمالي الدائن',
                value: @json(number_format((float) ($totals['total_credit'] ?? 0), 2)),
                titleColor: 'danger', valueColor: 'danger',
                backgroundColor: 'dangerLight', borderColor: 'danger',
            },
            {
                title: 'صافي الرصيد',
                value: @json(number_format((float) ($totals['total_balance'] ?? 0), 2)),
                titleColor: 'primary', valueColor: 'primary',
                backgroundColor: 'grayLight', borderColor: 'primary',
            },
            {
                title: 'عدد القيود',
                value: @json((string) ($totals['total_count'] ?? 0)),
                titleColor: 'warning', valueColor: 'warning',
                backgroundColor: 'warningLight', borderColor: 'warning',
            },
        ];
    }

    /** الفلاتر المطبَّقة، لتُطبع تحت الترويسة */
    function ledgerInfo() {
        const info = {};
        const from = @json($from_date ?? '');
        const to = @json($to_date ?? '');

        info['الفترة'] = (from || to) ? ((from || 'البداية') + ' إلى ' + (to || 'اليوم')) : 'كل الفترات';

        document.querySelectorAll('#filterForm select').forEach(function (select) {
            if (! select.value) return;

            const group = select.closest('.form-group');
            const label = group ? group.querySelector('label') : null;
            const option = select.options[select.selectedIndex];

            if (label && option) {
                info[label.textContent.trim()] = option.text.trim();
            }
        });

        return info;
    }

    /** صفوف جدولٍ واحد بصيغة المساعد */
    function ledgerRows(table) {
        const rows = [];

        table.querySelectorAll('tbody tr').forEach(function (tr) {
            const cells = tr.querySelectorAll('td');
            if (cells.length < LEDGER_HEADERS.length) return;

            const values = Array.prototype.slice.call(cells, 0, LEDGER_HEADERS.length).map(function (cell, index) {
                const text = cell.textContent.replace(/\s+/g, ' ').trim();

                if (LEDGER_NUMERIC.indexOf(index) === -1) return text;

                const number = parseFloat(text.replace(/[^\d.\-]/g, ''));
                return isNaN(number) ? text : number;
            });

            rows.push({
                values: values,
                alignments: values.map(function (value, index) {
                    return index === 2 ? 'right' : 'center';
                }),
            });
        });

        return rows;
    }

    /** اسم الحساب من بطاقته */
    function ledgerAccountTitle(table) {
        const card = table.closest('.card');
        const heading = card ? card.querySelector('.card-title h4') : null;

        return heading ? heading.textContent.trim() : 'حساب';
    }

    /** تصدير كل الحسابات المعروضة في ورقةٍ واحدة */
    function exportToExcel() {
        const tables = document.querySelectorAll('.ledger-table');

        if (! tables.length) {
            Swal.fire('لا بيانات', 'لا حسابات معروضة للتصدير.', 'warning');
            return;
        }

        const rows = [];

        tables.forEach(function (table) {
            // سطر عنوانٍ يفصل كل حساب عمّا قبله
            rows.push({
                values: [ledgerAccountTitle(table), '', '', '', '', '', ''],
                alignments: ['right', 'center', 'center', 'center', 'center', 'center', 'center'],
            });

            Array.prototype.push.apply(rows, ledgerRows(table));
        });

        exportToExcelProfessional({
            fileName: 'دفتر_الأستاذ_العام',
            mainTitle: 'دفتر الأستاذ العام',
            subTitle: 'حركة الحسابات وأرصدتها خلال الفترة',
            reportInfo: ledgerInfo(),
            statisticsCards: ledgerCards(),
            tableTitle: 'تفاصيل الحركة',
            tableHeaders: LEDGER_HEADERS,
            columnWidths: LEDGER_WIDTHS,
            tableData: rows,
            buttonSelector: '#exportExcel',
            successMessage: 'تم تصدير دفتر الأستاذ بنجاح',
            errorMessage: 'تعذّر تصدير دفتر الأستاذ',
        });
    }

    /** طباعة كل الحسابات المعروضة */
    function printLedger() {
        if (! document.querySelector('.ledger-table')) {
            Swal.fire('لا بيانات', 'لا حسابات معروضة للطباعة.', 'warning');
            return;
        }

        printTable({
            tableSelector: '.ledger-table tbody tr',
            title: 'دفتر الأستاذ العام',
            infoCards: ledgerCards().map(function (card) {
                return { label: card.title, value: card.value };
            }),
            filterInfo: ledgerInfo(),
            tableHeaders: LEDGER_HEADERS,
            columnWidths: ['12%', '12%', '30%', '13%', '13%', '13%', '7%'],
            descriptionColumn: 2,
            windowWidth: 1400,
            windowHeight: 900,
        });
    }

    /** تصدير حسابٍ واحد */
    function exportAccountToExcel(accountIndex) {
        const table = document.querySelector('#DataTable_Account_' + accountIndex);

        if (! table) {
            Swal.fire('لا بيانات', 'لا حركة على هذا الحساب.', 'warning');
            return;
        }

        const title = ledgerAccountTitle(table);

        exportToExcelProfessional({
            fileName: 'أستاذ_' + title,
            mainTitle: 'كشف حساب من دفتر الأستاذ',
            subTitle: title,
            reportInfo: ledgerInfo(),
            statisticsCards: [],
            tableTitle: title,
            tableHeaders: LEDGER_HEADERS,
            columnWidths: LEDGER_WIDTHS,
            tableData: ledgerRows(table),
            successMessage: 'تم تصدير الحساب بنجاح',
            errorMessage: 'تعذّر تصدير الحساب',
        });
    }

    /** طباعة حسابٍ واحد */
    function printAccount(accountIndex) {
        const table = document.querySelector('#DataTable_Account_' + accountIndex);

        if (! table) {
            Swal.fire('لا بيانات', 'لا حركة على هذا الحساب.', 'warning');
            return;
        }

        printTable({
            tableSelector: '#DataTable_Account_' + accountIndex + ' tbody tr',
            title: 'كشف حساب — ' + ledgerAccountTitle(table),
            infoCards: [],
            filterInfo: ledgerInfo(),
            tableHeaders: LEDGER_HEADERS,
            columnWidths: ['12%', '12%', '30%', '13%', '13%', '13%', '7%'],
            descriptionColumn: 2,
            windowWidth: 1400,
            windowHeight: 900,
        });
    }

    /*
        «تصدير PDF» كان يُصوِّر الصفحة بـ`html2pdf` فيخرج نصٌّ لا يُبحث فيه
        ويقتطع الجدولَ الطويل. صار يفتح نافذة الطباعة نفسها — ومن هناك
        «حفظ بصيغة PDF» يعطي ملفاً نصّياً حقيقياً بالترويسة نفسها.
    */
    function exportToPDF() {
        printLedger();
    }

    /** تصدير حسابٍ واحد PDF — عبر نافذة الطباعة للسبب نفسه */
    function exportAccountToPDF(accountIndex) {
        printAccount(accountIndex);
    }
</script>
@endsection