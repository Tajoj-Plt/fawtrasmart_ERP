@extends('hr::master')

@section('title')
    طلبات الأجازة
@stop

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
        data-bs-target="#leaveBalanceModal" title="رصيد الإجازات">
        <i class="fas fa-calendar-alt"></i>
        <span class="d-none d-md-inline">رصيد الإجازات</span>
    </button>
    @if (hasPermission('leave_requests.create'))
        <a href="{{ route('attendance.leave_requests.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">أضف طلب إجازة</span>
        </a>
    @endif
@endsection

@section('content')
<div class="content-body">
    <style>
        /* غطاءُ التحميل فوق الجدول أثناء طلب البحث */
        .lr-table-wrap { position: relative; min-height: 120px; }
        .loading-overlay { position: absolute; inset: 0; z-index: 5; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--app-surface, #fff) 75%, transparent); }
        .lr-status-bar { padding: .6rem 1.25rem; border-bottom: 1px solid var(--app-border, #eef0f4); }
        .lr-status-bar .nav-link { padding: .3rem .85rem; font-size: .82rem; font-weight: 600; }
    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('stock::components.page-head', [
        'title' => 'طلبات الإجازات',
        'icon' => 'send',
        'sub' => 'إدارة طلبات الموظفين — اعتماد، رفض، متابعة الأرصدة',
        'crumbs' => ['الرئيسية' => url('/'), 'طلبات الإجازات' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'leaves'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-leave-requests.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه طلباتُ الإجازة. والطلبُ لا يُغيّر شيئاً حتى يُعتمَد: عند الاعتماد وحده يُخصَم من رصيد الموظف ويُقرأ في الحضور.',
        'blocks' => [
            'ما يترتّب على الاعتماد' => [
                'يُخصَم عددُ الأيام من رصيد نوع الإجازة للموظف — والرصيدُ محدودٌ وتُرفض الزيادةُ عليه.',
                'ويصير الموظفُ «في إجازة» في تلك الأيام، فلا تُحسَب غياباً ولا يُحسَم عليها من راتبه.',
                'ونوعُ الإجازة هو ما يقرّر أمدفوعةٌ هي أم لا — وهو ما يفرّق بين إجازةٍ بلا أثرٍ في الراتب وأخرى تُنقصه.',
            ],
            'الرفضُ والإلغاء' => [
                'رفضُ الطلب لا يمسّ الرصيد. وإلغاءُ إجازةٍ اعتُمدت يُعيد أيامَها إلى الرصيد.',
            ],
            'ما تراه أنت' => [
                'من يملك «إجازاتي» وحدها يرى طلباتِه فقط؛ ومن يملك الاعتمادَ يرى طلباتِ من يتبعونه.',
            ],
        ],
        'facts' => [
            'الاعتمادُ يخصم من الرصيد ويمنع حسمَ الغياب',
            'نوعُ الإجازة يقرّر أثرَها في الراتب',
        ],
    ])

    {{-- البحث يجري بـAJAX (attendance.leave_requests.search) — الحقول بمعرّفاتها التي يقرؤها السكربت --}}
    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                <span class="stk-count" id="lrFiltersActive" style="display:none">فلاتر مفعّلة</span>
            </div>

            <form id="searchForm" onsubmit="return false;">
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="form-group">
                            <label for="employee_search">الموظف</label>
                            <input type="text" class="form-control" placeholder="ابحث بالاسم أو الكود…"
                                name="employee_search" id="employee_search">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="form-group">
                            <label for="from_date">من تاريخ</label>
                            <input type="date" class="form-control" name="from_date" id="from_date">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="form-group">
                            <label for="to_date">إلى تاريخ</label>
                            <input type="date" class="form-control" name="to_date" id="to_date">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="form-group">
                            <label for="created_date">تاريخ الإنشاء</label>
                            <input type="date" class="form-control" name="created_date" id="created_date">
                        </div>
                    </div>
                </div>

                <!-- البحث المتقدم -->
                <div class="collapse" id="advancedSearchForm">
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="leave_type">نوع الإجازة</label>
                                <select class="form-control" name="leave_type" id="leave_type">
                                    <option value="">كل الأنواع</option>
                                    @foreach ($leaveTypes as $leaveType)
                                        <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label for="department">القسم</label>
                                <select class="form-control" name="department" id="department">
                                    <option value="">كل الأقسام</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                @include('reports.partials.affiliation-tree', [
                                    'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                    'name' => 'branch',
                                    'showExact' => false,
                                    'label' => 'تابع لـ',
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <button type="button" class="btn btn-outline-secondary me-auto" data-bs-toggle="collapse"
                        data-bs-target="#advancedSearchForm" aria-expanded="false">
                        <i class="feather icon-sliders"></i> بحث متقدم
                    </button>
                    <button type="button" id="clearFilters" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </button>
                    <button type="button" id="searchBtn" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول النتائج -->
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-list"></i> قائمة طلبات الإجازة
                <span class="stk-count" id="totalResults">0</span>
            </h6>
            @include('stock::components.per-page', [
                'id' => 'perPageSelect',
                'current' => 10,
            ])
        </div>

        <!-- فلترة سريعة حسب الحالة -->
        <div class="lr-status-bar">
            <ul class="nav nav-pills gap-1" id="statusTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="all-tab" data-status="" type="button">الكل</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="pending-tab" data-status="pending" type="button">تحت المراجعة</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="approved-tab" data-status="approved" type="button">موافق عليه</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="rejected-tab" data-status="rejected" type="button">مرفوض</button>
                </li>
            </ul>
        </div>

        <div class="lr-table-wrap">
            <div id="loadingOverlay" class="loading-overlay" style="display: none;">
                <div class="spinner-border text-muted" role="status" style="width: 2.5rem; height: 2.5rem;">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
            </div>

            <div id="tableContainer">
                <div class="card-body">
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-loader"></i></div>
                        <h5 class="stk-empty__title">جاري تحميل الطلبات…</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- مودال رصيد الإجازات -->
    <div class="modal fade" id="leaveBalanceModal" tabindex="-1" aria-labelledby="leaveBalanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveBalanceModalLabel">
                        رصيد الإجازات
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <!-- البحث عن الموظف -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <label for="employeeSelect" class="form-label fw-bold">
                                <i class="feather icon-user me-2"></i>اختر الموظف
                            </label>
                            <select class="form-select" id="employeeSelect">
                                <option value="">-- اختر موظف --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Loading Spinner -->
                    <div id="loadingSpinner" class="text-center" style="display: none;">
                        <div class="spinner-border text-secondary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <p class="mt-3 text-muted">جاري تحميل بيانات الموظف...</p>
                    </div>

                    <!-- رصيد الإجازات -->
                    <div id="leaveBalanceContent" style="display: none;">
                        <!-- معلومات الموظف -->
                        <div class="mb-4 pb-3 border-bottom">
                            <h5 id="selectedEmployeeName" class="mb-1"></h5>
                            <p class="text-muted mb-0" id="selectedEmployeeCode"></p>
                        </div>

                        <!-- تفاصيل أنواع الإجازات -->
                        <div class="mt-3">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 lr-balance-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">نوع الإجازة</th>
                                            <th class="text-center">الرصيد الكلي</th>
                                            <th class="text-center">المستخدم</th>
                                            <th class="text-center">المتبقي</th>
                                        </tr>
                                    </thead>
                                    <tbody id="leaveTypesBalance">
                                        <!-- سيتم ملؤها ديناميكياً -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- رسالة عدم وجود أرصدة -->
                    <div id="noBalanceMessage" style="display: none;">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-alert-circle"></i></div>
                            <h5 class="stk-empty__title">ليس لديه رصيد إجازات</h5>
                            <p class="stk-empty__hint">هذا الموظف ليس لديه أرصدة إجازات محددة في النظام — يجب على إدارة الموارد البشرية تحديد الأرصدة أولاً</p>
                        </div>
                    </div>

                    <!-- رسالة اختر موظف -->
                    <div id="noEmployeeSelected" class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-user-check"></i></div>
                        <h5 class="stk-empty__title">اختر موظفاً لعرض رصيد الإجازات</h5>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="feather icon-x me-2"></i>إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال تأكيد الحذف -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-alert-triangle me-2"></i>تأكيد الحذف
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>هل أنت متأكد من حذف طلب الإجازة هذا؟</p>
                    <p class="text-muted">لا يمكن التراجع عن هذا الإجراء.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="feather icon-trash-2 me-2"></i>حذف
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush

@section('scripts')
    <script>
        $(document).ready(function() {
            let currentPage = 1;
            let currentFilters = {};
            let deleteRequestId = null;
            let searchXhr = null;

            // إعداد AJAX headers
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // تحميل قائمة الموظفين عند فتح المودال
            $('#leaveBalanceModal').on('show.bs.modal', function() {
                console.log('فتح مودال رصيد الإجازات...');
                loadEmployees();
                resetBalanceModal();
            });

            // عند اختيار موظف في مودال رصيد الإجازات
            $('#employeeSelect').on('change', function() {
                const employeeId = $(this).val();
                console.log('تم اختيار الموظف:', employeeId);

                if (employeeId && employeeId !== '') {
                    loadEmployeeLeaveBalance(employeeId);
                } else {
                    resetBalanceModal();
                }
            });

            // التبويبات - فلترة حسب الحالة
            $('#statusTabs .nav-link').on('click', function(e) {
                e.preventDefault();
                $('#statusTabs .nav-link').removeClass('active');
                $(this).addClass('active');

                const status = $(this).data('status');
                currentFilters.status = status;
                currentPage = 1;
                searchLeaveRequests();
            });

            // البحث
            $('#searchBtn').on('click', function() {
                currentPage = 1;
                searchLeaveRequests();
            });

            // مسح الفلاتر
            $('#clearFilters').on('click', function() {
                $('#searchForm')[0].reset();
                $('#branch').val(''); if (window.__rptInitAffiliationTree_branch) window.__rptInitAffiliationTree_branch();
                currentFilters = {};
                currentPage = 1;
                $('#statusTabs .nav-link').removeClass('active');
                $('#all-tab').addClass('active');
                searchLeaveRequests();
            });

            // «إزالة الفلاتر» في حالة «لا نتائج» داخل الجدول
            $(document).on('click', '.js-lr-reset', function() {
                $('#clearFilters').trigger('click');
            });

            // عدد السجلات في الصفحة (مُنتقي رأس الجدول)
            $(document).off('change', '#perPageSelect').on('change', '#perPageSelect', function() {
                updatePerPageAndSearch($(this).val());
            });

            // البحث التلقائي عند كتابة اسم الموظف
            $('#employee_search').on('keyup', debounce(function() {
                currentPage = 1;
                searchLeaveRequests();
            }, 500));

            // تحديث البحث عند تغيير التواريخ والفلاتر
            $('#from_date, #to_date, #created_date, #leave_type, #department, #branch').on('change', function() {
                currentPage = 1;
                searchLeaveRequests();
            });

            // وظيفة البحث الرئيسية
            function searchLeaveRequests() {
                // جمع البيانات من النموذج
                currentFilters = {
                    employee_search: $('#employee_search').val(),
                    from_date: $('#from_date').val(),
                    to_date: $('#to_date').val(),
                    created_date: $('#created_date').val(),
                    leave_type: $('#leave_type').val(),
                    department: $('#department').val(),
                    branch: $('#branch').val(),
                    status: $('#statusTabs .nav-link.active').data('status') || '',
                    per_page: currentFilters.per_page || 10, // إضافة per_page
                    page: currentPage
                };

                // إظهار مؤشر التحميل
                showTableLoading();

                // طلبٌ واحدٌ في الطريق: الضغطُ على «بحث» كان يُرسل طلبين (زرّ البحث وتغيّرُ مرشِّح الفرع معاً)
                if (searchXhr) { searchXhr.abort(); }

                searchXhr = $.ajax({
                    url: "{{ route('attendance.leave_requests.search') }}",
                    method: 'GET',
                    data: currentFilters,
                    success: function(response) {
                        $('#loadingOverlay').hide();

                        if (response.table) {
                            $('#tableContainer').html(response.table);
                        } else {
                            // في حالة عدم وجود جدول في الاستجابة، عرض رسالة افتراضية
                            $('#tableContainer').html(`
                                <div class="card-body"><div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-inbox"></i></div>
                                    <h5 class="stk-empty__title">لا توجد نتائج</h5>
                                    <p class="stk-empty__hint">لم يتم العثور على طلبات إجازة تطابق معايير البحث</p>
                                </div></div>
                            `);
                        }

                        // شارة «فلاتر مفعّلة» في رأس بطاقة البحث
                        const lrActive = ['employee_search', 'from_date', 'to_date', 'created_date', 'leave_type', 'department', 'branch']
                            .some(function (k) { return currentFilters[k] !== undefined && currentFilters[k] !== null && currentFilters[k] !== ''; });
                        $('#lrFiltersActive').toggle(lrActive);

                        // تحديث عدد النتائج
                        if (response.total !== undefined) {
                            $('#totalResults').text(response.total);
                        } else {
                            $('#totalResults').text('0');
                        }

                        setupPaginationHandlers();
                        setupActionHandlers();
                    },
                    error: function(xhr, status, error) {
                        if (status === 'abort') { return; } // حلّ محلَّه طلبٌ أحدث
                        $('#loadingOverlay').hide();
                        console.error('خطأ في البحث:', xhr.responseText);
                        
                        // في حالة الخطأ، عرض رسالة خطأ في الجدول
                        $('#tableContainer').html(`
                            <div class="card-body"><div class="stk-empty">
                                <div class="stk-empty__icon"><i class="feather icon-alert-triangle text-danger"></i></div>
                                <h5 class="stk-empty__title text-danger">حدث خطأ في تحميل البيانات</h5>
                                <p class="stk-empty__hint">يرجى المحاولة مرة أخرى أو الاتصال بالدعم الفني</p>
                            </div></div>
                        `);
                        
                        showAlert('حدث خطأ أثناء البحث: ' + error, 'danger');
                    }
                });
            }
// تحميل قائمة الموظفين

function loadEmployees() {
    $.ajax({
        url: "{{ route('attendance.employees.list') }}",
        method: 'GET',
        beforeSend: function() {
            console.log('جاري تحميل قائمة الموظفين...');
            $('#employeeSelect').html('<option value="">جاري التحميل...</option>');
        },
        success: function(response) {
            console.log('تم تحميل الموظفين:', response);
            
            // إعادة تعيين المودال أولاً
            resetBalanceModal();
            
            if (response && Array.isArray(response)) {
                const select = $('#employeeSelect');
                select.empty();
                select.append('<option value="">-- اختر موظف --</option>');
                
                response.forEach(function(employee) {
                    select.append(
                        `<option value="${employee.id}">
                            ${employee.full_name} - ${employee.employee_code || employee.id}
                        </option>`
                    );
                });
                
                console.log('تم تحميل ' + response.length + ' موظف');
            } else {
                console.error('بيانات غير صحيحة:', response);
                $('#employeeSelect').html('<option value="">خطأ في تحميل البيانات</option>');
                showAlert('تم استلام بيانات غير صحيحة من الخادم', 'warning');
            }
        },
        error: function(xhr, status, error) {
            console.error('خطأ في تحميل الموظفين:', xhr.responseText);
            $('#employeeSelect').html('<option value="">خطأ في التحميل</option>');
            
            let errorMessage = 'حدث خطأ في تحميل قائمة الموظفين';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMessage = xhr.responseJSON.error;
            }
            
            showAlert(errorMessage, 'danger');
        }
    });
}
// تحميل رصيد إجازات موظف محدد
function loadEmployeeLeaveBalance(employeeId) {
    $('#loadingSpinner').show();
    $('#leaveBalanceContent').hide();
    $('#noEmployeeSelected').hide();
    $('#noBalanceMessage').hide();

    $.ajax({
        url: `/hr/leave_requests/attendance/employees/${employeeId}/leave-balance`,
        method: 'GET',
        dataType: 'json',
        beforeSend: function() {
            console.log('جاري تحميل رصيد الإجازات للموظف:', employeeId);
        },
        success: function(response) {
            console.log('تم تحميل رصيد الإجازات:', response);
            $('#loadingSpinner').hide();

            // التحقق من أن response هو object صحيح
            if (!response || typeof response !== 'object') {
                console.error('البيانات المرجعة ليست object صحيح');
                showAlert('خطأ في صيغة البيانات المرجعة', 'danger');
                resetBalanceModal();
                return;
            }

            if (response.success && response.employee && response.leave_types) {
                // عرض بيانات رصيد الإجازات بنجاح
                displayLeaveBalance(response);
                $('#leaveBalanceContent').show();
            } else if (!response.success && response.has_no_balance) {
                // الموظف ليس لديه أرصدة محددة
                displayNoBalanceMessage(employeeId, response);
                $('#noBalanceMessage').show();
            } else {
                console.error('بيانات غير صحيحة');
                showAlert(response.error || 'حدث خطأ غير معروف', 'danger');
                resetBalanceModal();
            }
        },
        error: function(xhr, status, error) {
            console.error('خطأ في تحميل رصيد الإجازات');
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            
            $('#loadingSpinner').hide();

            let errorMessage = 'حدث خطأ في تحميل رصيد الإجازات';
            
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                
                // إذا كان الخطأ لأن الموظف ليس لديه أرصدة
                if (xhr.status === 404 && errorResponse.has_no_balance) {
                    displayNoBalanceMessage(employeeId, errorResponse);
                    $('#noBalanceMessage').show();
                    return;
                }
                
                if (errorResponse.error) {
                    errorMessage = errorResponse.error;
                }
            } catch(e) {
                // إذا لم تكن الاستجابة JSON صحيحة
            }

            if (xhr.status === 404) {
                errorMessage = 'الموظف المحدد غير موجود';
            } else if (xhr.status === 500) {
                errorMessage = 'خطأ في الخادم، يرجى المحاولة مرة أخرى';
            } else if (xhr.status === 403) {
                errorMessage = 'ليس لديك صلاحية لعرض هذه البيانات';
            }

            showAlert(errorMessage, 'danger');
            resetBalanceModal();
        }
    });
}

// عرض بيانات رصيد الإجازات
function displayLeaveBalance(response) {
    const employee = response.employee;
    
    // تحديث معلومات الموظف
    $('#selectedEmployeeName').text(employee.name || 'غير محدد');
    $('#selectedEmployeeCode').text(`كود الموظف: #${employee.employee_code || 'غير محدد'}`);

    // تحديث الأرصدة العامة
    $('#totalBalance').text(response.total_balance || 0);
    $('#usedBalance').text(response.used_balance || 0);
    $('#remainingBalance').text(response.remaining_balance || 0);

    // تحديث تفاصيل أنواع الإجازات
    const tbody = $('#leaveTypesBalance');
    tbody.empty();

    if (response.leave_types && Array.isArray(response.leave_types) && response.leave_types.length > 0) {
        response.leave_types.forEach(function(type) {
            const percentage = type.total_balance > 0 ?
                Math.round((type.used_balance / type.total_balance) * 100) : 0;

            let progressClass = 'bg-success';
            if (percentage > 70) progressClass = 'bg-warning';
            if (percentage > 90) progressClass = 'bg-danger';

            tbody.append(`
                <tr>
                    <td>
                        <strong>${type.name || 'غير محدد'}</strong>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-primary">${type.total_balance || 0}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-warning">${type.used_balance || 0}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success">${type.remaining_balance || 0}</span>
                    </td>
                   
                </tr>
            `);
        });
    }
}

// عرض رسالة عدم وجود أرصدة
function displayNoBalanceMessage(employeeId, response) {
    const employeeSelect = $('#employeeSelect');
    const selectedOption = employeeSelect.find(`option[value="${employeeId}"]`);
    const employeeName = selectedOption.text() || 'الموظف';

    const messageHtml = `
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="feather icon-slash fa-4x text-danger"></i>
            </div>
            <h4 class="text-danger mb-2">لا توجد أرصدة إجازات</h4>
            <p class="text-muted mb-3">الموظف <strong>${employeeName}</strong> ليس لديه أرصدة إجازات محددة في النظام</p>
            <div class="alert alert-warning" role="alert">
                <i class="feather icon-info me-2"></i>
                <strong>ملاحظة:</strong> يجب على إدارة الموارد البشرية تحديد أرصدة الإجازات لهذا الموظف أولاً
            </div>
            <button type="button" class="btn btn-primary" onclick="resetBalanceModal()">
                <i class="feather icon-arrow-left me-2"></i>اختر موظف آخر
            </button>
        </div>
    `;

    $('#noBalanceMessage').html(messageHtml);
}

// إعادة تعيين حالة المودال
function resetBalanceModal() {
    $('#leaveBalanceContent').hide();
    $('#loadingSpinner').hide();
    $('#noEmployeeSelected').show();
    $('#noBalanceMessage').hide();
    $('#employeeSelect').val('');
}
            // إعادة تعيين حالة المودال
            function resetBalanceModal() {
                $('#leaveBalanceContent').hide();
                $('#loadingSpinner').hide();
                $('#noEmployeeSelected').show();
            }

            // إعداد معالجات الصفحات
            function setupPaginationHandlers() {
                $(document).off('click', '.pagination a').on('click', '.pagination a', function(e) {
                    e.preventDefault();
                    const url = $(this).attr('href');
                    if (url) {
                        const urlParams = new URLSearchParams(url.split('?')[1]);
                        const page = urlParams.get('page');
                        if (page) {
                            currentPage = parseInt(page);
                            searchLeaveRequests();
                        }
                    }
                });
            }

            // إعداد معالجات الإجراءات
            function setupActionHandlers() {
                // حذف طلب الإجازة
                $(document).off('click', '.delete-request').on('click', '.delete-request', function() {
                    deleteRequestId = $(this).data('id');
                    $('#deleteConfirmModal').modal('show');
                });

                // الموافقة على طلب
                $(document).off('click', '.approve-request').on('click', '.approve-request', function() {
                    const requestId = $(this).data('id');
                    updateRequestStatus(requestId, 'approved');
                });

                // رفض طلب
                $(document).off('click', '.reject-request').on('click', '.reject-request', function() {
                    const requestId = $(this).data('id');
                    updateRequestStatus(requestId, 'rejected');
                });
            }

            // تأكيد الحذف
            $('#confirmDeleteBtn').on('click', function() {
                if (deleteRequestId) {
                    deleteRequest(deleteRequestId);
                    $('#deleteConfirmModal').modal('hide');
                }
            });

            // حذف طلب الإجازة
            function deleteRequest(requestId) {
                $.ajax({
                    url: "{{ route('attendance.leave_requests.destroy', ':id') }}".replace(':id', requestId),
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'X-HTTP-Method-Override': 'DELETE'
                    },
                    beforeSend: function() {
                        showTableLoading();
                    },
                    success: function(response) {
                        showAlert('تم حذف الطلب بنجاح', 'success');
                        searchLeaveRequests();
                        deleteRequestId = null;
                    },
                    error: function(xhr, status, error) {
                        console.error('خطأ في حذف الطلب:', xhr.responseText);
                        showAlert('حدث خطأ أثناء حذف الطلب: ' + error, 'danger');
                        $('#loadingOverlay').hide();
                    }
                });
            }

            // تحديث حالة الطلب
            function updateRequestStatus(requestId, status) {
                $.ajax({
                    url: `{{ route('attendance.leave_requests.update_status', '') }}/${requestId}`,
                    method: 'PATCH',
                    data: {
                        status: status
                    },
                    beforeSend: function() {
                        showTableLoading();
                    },
                    success: function(response) {
                        const statusText = status === 'approved' ? 'تمت الموافقة على' : 'تم رفض';
                        showAlert(`${statusText} الطلب بنجاح`, 'success');
                        searchLeaveRequests();
                    },
                    error: function(xhr, status, error) {
                        console.error('خطأ في تحديث حالة الطلب:', xhr.responseText);
                        showAlert('حدث خطأ أثناء تحديث الطلب: ' + error, 'danger');
                        $('#loadingOverlay').hide();
                    }
                });
            }

            // إظهار مؤشر التحميل في الجدول
            function showTableLoading() {
                $('#loadingOverlay').show();
            }

            // وظيفة تأخير التنفيذ
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // إظهار التنبيهات
            function showAlert(message, type = 'info') {
                // إزالة التنبيهات السابقة
                $('.alert').remove();

                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show position-fixed"
                         style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;" role="alert">
                        <strong>${getAlertIcon(type)}</strong> ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;

                $('body').append(alertHtml);

                // إخفاء التنبيه تلقائياً بعد 5 ثوان
                setTimeout(() => {
                    $('.alert').fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
            }

            // الحصول على أيقونة التنبيه
            function getAlertIcon(type) {
                const icons = {
                    'success': '<i class="feather icon-check-circle me-2"></i>',
                    'danger': '<i class="feather icon-alert-circle me-2"></i>',
                    'warning': '<i class="feather icon-alert-triangle me-2"></i>',
                    'info': '<i class="feather icon-info me-2"></i>'
                };
                return icons[type] || icons['info'];
            }

            // دالة تحديث عدد السجلات وإعادة البحث
            function updatePerPageAndSearch(perPage) {
                currentPage = 1; // العودة إلى الصفحة الأولى
                currentFilters.per_page = perPage; // تحديث عدد السجلات في الفلاتر
                searchLeaveRequests(); // إعادة تحميل البيانات
            }

            // جعل دالة updatePerPageAndSearch متاحة عالمياً
            window.updatePerPageAndSearch = updatePerPageAndSearch;
            // سكربتُ صفوف الجدول (partials/table_rows) يُعيد البحث بعد الموافقة/الرفض/الحذف
            window.searchLeaveRequests = searchLeaveRequests;

            // تحميل البيانات عند تحميل الصفحة
            searchLeaveRequests();
            setupActionHandlers();
        });
    </script>
@endsection