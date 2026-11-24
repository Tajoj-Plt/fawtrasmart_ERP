@extends('hr::master')

@section('title')
    سياسة الإجازات - {{ $leave_policy->name }}
@stop

@section('css')
    <style>
        /* بطاقات إحصاء الموظفين تُبنى في السكربت (updateEmployeeStatistics) */
        .stats-card { transition: transform 0.2s ease-in-out; }
        .stats-card:hover { transform: translateY(-2px); }
        /* صورة الموظف المختصرة في جدول الموظفين — يبنيها السكربت */
        .employee-avatar {
            width: 36px;
            height: 36px;
            background: var(--app-primary, #667eea);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 13px;
            color: #fff;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .leave-type-card {
            border: 1px solid var(--app-border, #e3e6f0);
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 14px;
            background: var(--app-surface, #fff);
            height: calc(100% - 14px);
        }
    </style>
@endsection

@php
    $lpActive = $leave_policy->status == 0;
    $lpTypesCount = $leave_policy->leaveType()->count();
@endphp

@section('page-actions')
    @permission('leave_policies.edit')
        <a href="{{ route('leave_policy.edit', $leave_policy->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل</span>
        </a>
    @endpermission
    @if (canAny(['leave_policies.edit', 'leave_policies.destroy']))
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @permission('leave_policies.edit')
                    <li>
                        <a class="dropdown-item" href="{{ route('leave_policy.leave_policy_employees', $leave_policy->id) }}">
                            <i class="fa fa-users me-2 text-success"></i>إدارة الموظفين
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('leave_policy.updateStatus', $leave_policy->id) }}">
                            <i class="fa {{ $lpActive ? 'fa-ban text-warning' : 'fa-check text-success' }} me-2"></i>{{ $lpActive ? 'تعطيل' : 'تفعيل' }}
                        </a>
                    </li>
                @endpermission
                @permission('leave_policies.destroy')
                    @permission('leave_policies.edit')
                        <li><hr class="dropdown-divider"></li>
                    @endpermission
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $leave_policy->id }}">
                            <i class="fa fa-trash me-2"></i>حذف السياسة
                        </a>
                    </li>
                @endpermission
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('hr::components.table-menu')

    @include('stock::components.page-head', [
        'title' => $leave_policy->name,
        'icon' => 'bookmark',
        'crumbs' => ['الرئيسية' => url('/'), 'سياسات الإجازات' => route('leave_policy.index'), $leave_policy->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true">
                        <i class="feather icon-bookmark"></i>
                    </span>
                    <h4 class="mb-25">{{ $leave_policy->name }}</h4>
                    <p class="stk-help mt-0 mb-1">سياسة #{{ $leave_policy->id }} · {{ $lpTypesCount }} نوع إجازة</p>
                    <span class="stk-state stk-state--{{ $lpActive ? 'on' : 'off' }}">{{ $lpActive ? 'نشط' : 'غير نشط' }}</span>
                </div>
                {{-- المعرّفات totalLeaveTypes / totalEmployees / totalDepartments يملؤها السكربت --}}
                <div class="stk-metrics" id="statisticsCards" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">أنواع الإجازات</span>
                        <span class="stk-metric__value" id="totalLeaveTypes">{{ $lpTypesCount }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">إجمالي الموظفين</span>
                        <span class="stk-metric__value" id="totalEmployees">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">الأقسام المتأثرة</span>
                        <span class="stk-metric__value" id="totalDepartments">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4>
                </div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>رقم السياسة</dt>
                            <dd class="stk-num">#{{ $leave_policy->id }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>اسم السياسة</dt>
                            <dd>{{ $leave_policy->name }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الوصف</dt>
                            <dd>{{ $leave_policy->description ?: 'لا يوجد وصف' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الحالة</dt>
                            <dd><span class="stk-state stk-state--{{ $lpActive ? 'on' : 'off' }}">{{ $lpActive ? 'نشط' : 'غير نشط' }}</span></dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>عدد أنواع الإجازات</dt>
                            <dd class="stk-num">{{ $lpTypesCount }} نوع</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>عدد الموظفين المخصصين</dt>
                            <dd>
                                <span id="assignedEmployeesCount">
                                    <span class="spinner-border spinner-border-sm" role="status">
                                        <span class="sr-only">جاري التحميل...</span>
                                    </span>
                                </span>
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الإنشاء</dt>
                            <dd>{{ optional($leave_policy->created_at)->locale('ar')->translatedFormat('l، d F Y - H:i') ?? '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>آخر تحديث</dt>
                            <dd>{{ optional($leave_policy->updated_at)->locale('ar')->translatedFormat('l، d F Y - H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ══════════ أنواع الإجازات والموظفون والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="leave-types-tab" data-toggle="tab" href="#leave-types" role="tab"
                               aria-controls="leave-types" aria-selected="true">
                                <i class="feather icon-list"></i> أنواع الإجازات
                                <span class="stk-count">{{ $lpTypesCount }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="employees-tab" data-toggle="tab" href="#employees" role="tab"
                               aria-controls="employees" aria-selected="false">
                                <i class="feather icon-users"></i> الموظفين المخصصين
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activity-log-tab" data-toggle="tab" href="#activity-log" role="tab"
                               aria-controls="activity-log" aria-selected="false">
                                <i class="feather icon-activity"></i> سجل النشاطات
                                <span class="stk-count">{{ $logs ? $logs->flatten()->count() : 0 }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    {{-- أنواع الإجازات --}}
                    <div class="tab-pane fade show active" id="leave-types" role="tabpanel" aria-labelledby="leave-types-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">أنواع الإجازات المتاحة</h6>
                            <div class="d-flex flex-wrap align-items-center" style="gap: .5rem">
                                <input type="text" id="leave_type_search" class="form-control form-control-sm" style="width: auto"
                                       placeholder="البحث في أنواع الإجازات...">
                                <button id="reset_leave_type_filters" class="btn btn-outline-secondary btn-sm">
                                    <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            @if ($leave_policy->leaveType && $leave_policy->leaveType->count() > 0)
                                <div class="row" id="leaveTypesContainer">
                                    @foreach ($leave_policy->leaveType as $type)
                                        <div class="col-md-6 col-xl-4 leave-type-item" data-name="{{ strtolower($type->name) }}">
                                            <div class="leave-type-card">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <h6 class="mb-0 font-weight-bold">{{ $type->name }}</h6>
                                                    <span class="stk-tag stk-num">#{{ $type->id }}</span>
                                                </div>
                                                <div class="mb-1">
                                                    <small class="stk-help">الوصف:</small>
                                                    <p class="mb-0">{{ $type->description ?: 'لا يوجد وصف' }}</p>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <small class="stk-help">مدة الإجازة:</small>
                                                        <span class="badge badge-info">{{ $type->duration ?: 'غير محدد' }}</span>
                                                    </div>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                            data-bs-toggle="dropdown" data-bs-display="static"
                                                            aria-expanded="false" aria-label="خيارات">
                                                            <i class="fa fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <button type="button" class="dropdown-item" onclick="viewLeaveTypeDetails({{ $type->id }})">
                                                                <i class="fa fa-eye me-2 text-primary"></i>عرض التفاصيل
                                                            </button>
                                                            <button type="button" class="dropdown-item" onclick="editLeaveType({{ $type->id }})">
                                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-list"></i></div>
                                    <h5 class="stk-empty__title">لا توجد أنواع إجازات مرتبطة بهذه السياسة!</h5>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- الموظفين المخصصين — يملؤه السكربت (loadEmployees) --}}
                    <div class="tab-pane fade" id="employees" role="tabpanel" aria-labelledby="employees-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">الموظفين المخصصين لهذه السياسة</h6>
                            <div class="d-flex flex-wrap align-items-center" style="gap: .5rem">
                                <input type="text" id="employee_search" class="form-control form-control-sm" style="width: auto"
                                       placeholder="البحث في الموظفين...">
                                <select id="department_filter" class="form-control form-control-sm" style="width: auto">
                                    <option value="">جميع الأقسام</option>
                                </select>
                                <button id="reset_employee_filters" class="btn btn-outline-secondary btn-sm">
                                    <i class="feather icon-refresh-cw"></i> إعادة تعيين
                                </button>
                            </div>
                        </div>

                        <div class="card-body pb-0" id="employee_statistics_cards">
                            <div class="row" id="employeeStatsRow">
                                {{-- يملؤه السكربت --}}
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th width="25%">الموظف</th>
                                        <th width="15%">القسم</th>
                                        <th width="15%">المسمى الوظيفي</th>
                                        <th width="10%">الفرع</th>
                                        <th width="10%">أنواع الإجازات</th>
                                        <th width="15%">طريقة التخصيص</th>
                                        <th width="10%">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="employees_table">
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="p-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="sr-only">جاري تحميل بيانات الموظفين...</span>
                                                </div>
                                                <p class="mt-2 text-muted">يرجى الانتظار...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- سجل النشاطات --}}
                    <div class="tab-pane fade" id="activity-log" role="tabpanel" aria-labelledby="activity-log-tab">
                        <div class="card-body">
                            @include('hr::organizational_structure.partials._activity', ['logs' => $logs ?? collect()])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- نافذة حذف السياسة --}}
    <div class="modal fade" id="modal_DELETE{{ $leave_policy->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">حذف سياسة الإجازات</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1 text-danger"><i class="feather icon-alert-triangle"></i> <strong>تحذير!</strong></p>
                    <p class="mb-1">سيتم حذف سياسة الإجازات <strong>{{ $leave_policy->name }}</strong> وجميع البيانات المرتبطة بها
                        ({{ $lpTypesCount }} نوع إجازة).</p>
                    <p class="stk-empty__hint mb-0"><strong>هذا الإجراء لا يمكن التراجع عنه!</strong> هل أنت متأكد من أنك تريد الحذف؟</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    @permission('leave_policies.destroy')
                        <form method="POST" action="{{ route('leave_policy.delete', $leave_policy->id) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="feather icon-trash-2"></i> تأكيد الحذف
                            </button>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // تحميل البيانات الأولية
            loadEmployeesCount();
            loadStatistics();

            // التعامل مع فلاتر أنواع الإجازات
            $('#leave_type_search').on('input', function() {
                filterLeaveTypes();
            });

            // إعادة تعيين فلاتر أنواع الإجازات
            $('#reset_leave_type_filters').on('click', function() {
                $('#leave_type_search').val('');
                filterLeaveTypes();
            });

            // التعامل مع فلاتر الموظفين
            $('#employee_search, #department_filter').on('input change', function() {
                filterEmployees();
            });

            // إعادة تعيين فلاتر الموظفين
            $('#reset_employee_filters').on('click', function() {
                $('#employee_search').val('');
                $('#department_filter').val('');
                filterEmployees();
            });

            // تحميل بيانات الموظفين عند النقر على التبويبة
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                var targetTab = $(e.target).attr("href");
                var url = window.location.href;
                var newUrl = url.split('#')[0] + targetTab;
                window.history.replaceState(null, null, newUrl);

                // تحميل بيانات الموظفين عند تفعيل التبويبة
                if (targetTab === '#employees') {
                    loadEmployees();
                }
            });

            // تفعيل التبويبة من URL إذا كانت موجودة
            var hash = window.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
                // إذا كانت تبويبة الموظفين، قم بتحميل البيانات
                if (hash === '#employees') {
                    loadEmployees();
                }
            }
        });

        // تحميل عدد الموظفين المخصصين
        function loadEmployeesCount() {
            $.ajax({
                url: '{{ route("leave_policy.employeesCount", $leave_policy->id) }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#assignedEmployeesCount').html(`<strong>${response.count} موظف</strong>`);
                        $('#totalEmployees').text(response.count);
                    } else {
                        $('#assignedEmployeesCount').html('<strong>0 موظف</strong>');
                        $('#totalEmployees').text('0');
                    }
                },
                error: function() {
                    $('#assignedEmployeesCount').html('<strong class="text-danger">خطأ في التحميل</strong>');
                    $('#totalEmployees').text('خطأ');
                }
            });
        }

        // تحميل الإحصائيات
        function loadStatistics() {
            $.ajax({
                url: '{{ route("leave_policy.getStatistics", $leave_policy->id) }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#totalDepartments').text(response.statistics.departments_count);
                    }
                },
                error: function() {
                    $('#totalDepartments').text('خطأ');
                }
            });
        }

        // فلترة أنواع الإجازات
        function filterLeaveTypes() {
            const searchTerm = $('#leave_type_search').val().toLowerCase();

            $('.leave-type-item').each(function() {
                const leaveTypeName = $(this).data('name');
                const matchesSearch = !searchTerm || leaveTypeName.includes(searchTerm);

                $(this).toggle(matchesSearch);
            });
        }

        // تحميل بيانات الموظفين
        function loadEmployees() {
            // تحقق من أن البيانات لم يتم تحميلها مسبقاً
            if ($('#employees_table').data('loaded')) {
                return;
            }

            console.log('بدء تحميل بيانات الموظفين...');

            $.ajax({
                url: '{{ route("leave_policy.employeesList", $leave_policy->id) }}',
                method: 'GET',
                timeout: 30000, // 30 ثانية
                beforeSend: function() {
                    // إظهار رسالة التحميل
                    $('#employees_table').html(`
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="p-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">جاري تحميل بيانات الموظفين...</span>
                                    </div>
                                    <p class="mt-2 text-muted">جاري جلب البيانات، يرجى الانتظار...</p>
                                </div>
                            </td>
                        </tr>
                    `);
                },
                success: function(response) {
                    console.log('تم استلام الاستجابة:', response);

                    if (response.success) {
                        if (response.employees && response.employees.length > 0) {
                            displayEmployees(response.employees);
                            populateDepartmentFilter(response.departments || []);
                            updateEmployeeStatistics(response.employees);
                        } else {
                            showNoEmployeesMessage();
                        }
                        // وضع علامة على أن البيانات تم تحميلها
                        $('#employees_table').data('loaded', true);
                    } else {
                        showErrorMessage('لم يتم العثور على بيانات الموظفين');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('خطأ في تحميل البيانات:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        error: error,
                        response: xhr.responseText
                    });

                    let errorMessage = 'خطأ في تحميل البيانات';
                    if (xhr.status === 404) {
                        errorMessage = 'الرابط غير موجود';
                    } else if (xhr.status === 500) {
                        errorMessage = 'خطأ في الخادم';
                    } else if (status === 'timeout') {
                        errorMessage = 'انتهت مهلة الطلب';
                    }

                    showErrorMessage(errorMessage);
                }
            });
        }

        // عرض رسالة خطأ
        function showErrorMessage(message) {
            $('#employees_table').html(`
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="p-4">
                            <i class="feather icon-alert-triangle fa-3x text-danger mb-3"></i>
                            <h5 class="text-danger">${message}</h5>
                            <button class="btn btn-primary mt-2" onclick="retryLoadEmployees()">
                                <i class="feather icon-refresh-cw"></i> إعادة المحاولة
                            </button>
                        </div>
                    </td>
                </tr>
            `);
        }

        // إعادة محاولة تحميل الموظفين
        function retryLoadEmployees() {
            $('#employees_table').removeData('loaded');
            loadEmployees();
        }

        // عرض بيانات الموظفين
        function displayEmployees(employees) {
            if (!employees || employees.length === 0) {
                showNoEmployeesMessage();
                return;
            }

            let html = '';

            employees.forEach(function(employee) {
                // طباعة البيانات للتشخيص
                console.log('Employee data:', employee);

                // التأكد من وجود البيانات المطلوبة مع تجربة عدة احتمالات
                const fullName = employee.full_name || employee.name || employee.first_name || employee.ar_name || 'غير محدد';
                const employeeId = employee.employee_id || employee.emp_id || employee.id || 'غير محدد';

                // محاولة الحصول على اسم القسم بطرق مختلفة
                let departmentName = 'غير محدد';
                if (employee.department && employee.department.name) {
                    departmentName = employee.department.name;
                } else if (employee.department && employee.department.ar_name) {
                    departmentName = employee.department.ar_name;
                } else if (employee.department_name) {
                    departmentName = employee.department_name;
                }

                // محاولة الحصول على المسمى الوظيفي بطرق مختلفة
                let jobTitleName = 'غير محدد';
                if (employee.job_title && employee.job_title.name) {
                    jobTitleName = employee.job_title.name;
                } else if (employee.job_Title && employee.job_Title.name) {
                    jobTitleName = employee.job_Title.name;
                } else if (employee.job_title && employee.job_title.ar_name) {
                    jobTitleName = employee.job_title.ar_name;
                } else if (employee.job_Title && employee.job_Title.ar_name) {
                    jobTitleName = employee.job_Title.ar_name;
                } else if (employee.job_title_name) {
                    jobTitleName = employee.job_title_name;
                } else if (employee.position) {
                    jobTitleName = employee.position;
                }

                // محاولة الحصول على اسم الفرع بطرق مختلفة
                let branchName = 'غير محدد';
                if (employee.branch && employee.branch.name) {
                    branchName = employee.branch.name;
                } else if (employee.branch && employee.branch.ar_name) {
                    branchName = employee.branch.ar_name;
                } else if (employee.branch_name) {
                    branchName = employee.branch_name;
                }

                const leaveTypesCount = employee.leave_types_count || 0;
                const assignmentType = employee.assignment_type || 'غير محدد';

                html += `
                    <tr class="employee-row"
                        data-name="${fullName.toLowerCase()}"
                        data-department="${employee.department_id || ''}">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="employee-avatar mr-2">
                                    ${fullName.substr(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <h6 class="mb-0">${fullName}</h6>
                                    <small class="stk-help">رقم الموظف: ${employeeId}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="stk-tag">${departmentName}</span>
                        </td>
                        <td>
                            <span class="stk-tag">${jobTitleName}</span>
                        </td>
                        <td>
                            <span class="badge badge-info">${branchName}</span>
                        </td>
                        <td>
                            <span class="badge badge-success">${leaveTypesCount} نوع</span>
                        </td>
                        <td>
                            <span class="badge badge-${assignmentType === 'مباشر' ? 'primary' : 'secondary'}">${assignmentType}</span>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="dropdown" data-bs-display="static"
                                    aria-expanded="false" aria-label="خيارات">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <button type="button" class="dropdown-item"
                                            onclick="viewEmployeeLeaveTypes(${employee.id})">
                                        <i class="fa fa-list me-2 text-primary"></i>عرض أنواع إجازات الموظف
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <button type="button" class="dropdown-item text-danger"
                                            onclick="removeEmployeeFromPolicy(${employee.id}, '${fullName.replace(/'/g, "\\'")}')">
                                        <i class="fa fa-times me-2"></i>إزالة من السياسة
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $('#employees_table').html(html);
            console.log('تم عرض الموظفين بنجاح');
        }

        // إظهار رسالة عدم وجود موظفين
        function showNoEmployeesMessage() {
            $('#employees_table').html(`
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="p-4">
                            <i class="feather icon-users fa-3x text-muted mb-3"></i>
                            <h5>لا توجد موظفين مخصصين</h5>
                            <p class="text-muted">لم يتم تخصيص أي موظفين لهذه السياسة حتى الآن</p>
                            @permission('leave_policies.edit')
                                <a href="{{ route('leave_policy.leave_policy_employees', $leave_policy->id) }}"
                                   class="btn btn-primary">
                                    <i class="feather icon-plus"></i> إضافة موظفين
                                </a>
                            @endpermission
                        </div>
                    </td>
                </tr>
            `);

            // تحديث إحصائيات الموظفين
            $('#employeeStatsRow').html(`
                <div class="col-md-12">
                    <div class="alert alert-info text-center">
                        <i class="feather icon-info"></i>
                        لا توجد موظفين مخصصين لعرض الإحصائيات
                    </div>
                </div>
            `);
        }

        // تحديث إحصائيات الموظفين
        function updateEmployeeStatistics(employees) {
            if (!employees || employees.length === 0) {
                showNoEmployeesMessage();
                return;
            }

            const totalEmployees = employees.length;
            const departments = [...new Set(employees.map(emp =>
                emp.department && emp.department.name ? emp.department.name : null
            ).filter(Boolean))].length;
            const branches = [...new Set(employees.map(emp =>
                emp.branch && emp.branch.name ? emp.branch.name : null
            ).filter(Boolean))].length;
            const totalLeaveTypesAssigned = employees.reduce((sum, emp) =>
                sum + (emp.leave_types_count || 0), 0);

            $('#employeeStatsRow').html(`
                <div class="col-md-3">
                    <div class="card bg-primary text-white stats-card">
                        <div class="card-body text-center">
                            <h3 class="mb-1">${totalEmployees}</h3>
                            <p class="mb-0">إجمالي الموظفين</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white stats-card">
                        <div class="card-body text-center">
                            <h3 class="mb-1">${departments}</h3>
                            <p class="mb-0">عدد الأقسام</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white stats-card">
                        <div class="card-body text-center">
                            <h3 class="mb-1">${branches}</h3>
                            <p class="mb-0">عدد الفروع</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white stats-card">
                        <div class="card-body text-center">
                            <h3 class="mb-1">${totalLeaveTypesAssigned}</h3>
                            <p class="mb-0">إجمالي الأنواع المخصصة</p>
                        </div>
                    </div>
                </div>
            `);
        }

        // ملء فلتر الأقسام
        function populateDepartmentFilter(departments) {
            let options = '<option value="">جميع الأقسام</option>';
            if (departments && departments.length > 0) {
                departments.forEach(function(dept) {
                    if (dept && dept.id && dept.name) {
                        options += `<option value="${dept.id}">${dept.name}</option>`;
                    }
                });
            }
            $('#department_filter').html(options);
        }

        // فلترة الموظفين
        function filterEmployees() {
            const searchTerm = $('#employee_search').val().toLowerCase();
            const selectedDept = $('#department_filter').val();

            $('.employee-row').each(function() {
                const employeeName = $(this).data('name');
                const employeeDept = $(this).data('department');

                const matchesSearch = !searchTerm || employeeName.includes(searchTerm);
                const matchesDept = !selectedDept || employeeDept == selectedDept;

                $(this).toggle(matchesSearch && matchesDept);
            });
        }

        // إزالة موظف من السياسة
        function removeEmployeeFromPolicy(employeeId, employeeName) {
            Swal.fire({
                title: 'إزالة الموظف من السياسة',
                text: `هل أنت متأكد من إزالة "${employeeName}" من هذه السياسة؟`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، أزل',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route("leave_policy.removeEmployee", $leave_policy->id) }}',
                        method: 'POST',
                        data: {
                            employee_id: employeeId,
                            // المسار DELETE — بدونه يُرجع 405 ولا يُزال الموظف
                            _method: 'DELETE',
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'تمت الإزالة!',
                                    text: response.message,
                                    icon: 'success',
                                    timer: 2000,
                                    timerProgressBar: true
                                });

                                // إزالة الصف وإعادة تحميل البيانات
                                $(`.employee-row[data-name*="${employeeName.toLowerCase()}"]`).fadeOut(300, function() {
                                    $(this).remove();
                                    loadEmployeesCount();
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: 'خطأ!',
                                text: 'حدث خطأ أثناء إزالة الموظف',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        }

        // عرض أنواع إجازات الموظف
        function viewEmployeeLeaveTypes(employeeId) {
            Swal.fire({
                title: 'أنواع إجازات الموظف',
                text: 'سيتم إضافة هذه الميزة قريباً',
                icon: 'info'
            });
        }

        // تعديل نوع الإجازة
        function editLeaveType(leaveTypeId) {
            Swal.fire({
                title: 'تعديل نوع الإجازة',
                text: 'سيتم إضافة هذه الميزة قريباً',
                icon: 'info'
            });
        }

        // عرض تفاصيل نوع الإجازة
        function viewLeaveTypeDetails(leaveTypeId) {
            Swal.fire({
                title: 'تفاصيل نوع الإجازة',
                text: 'سيتم إضافة هذه الميزة قريباً',
                icon: 'info'
            });
        }

        // تحسينات إضافية
        $(document).on('mouseenter', '.table-hover tbody tr', function() {
            $(this).addClass('table-active');
        }).on('mouseleave', '.table-hover tbody tr', function() {
            $(this).removeClass('table-active');
        });

        // Tooltips للأزرار
        $('[title]').tooltip();

        // معالجة الأخطاء العامة
        $(document).ajaxError(function(event, xhr, settings) {
            if (xhr.status === 403) {
                Swal.fire({
                    title: 'غير مصرح!',
                    text: 'ليس لديك صلاحية للقيام بهذا الإجراء',
                    icon: 'error'
                });
            } else if (xhr.status === 404) {
                Swal.fire({
                    title: 'غير موجود!',
                    text: 'البيانات المطلوبة غير موجودة',
                    icon: 'error'
                });
            }
        });
    </script>
@endsection