@extends('hr::master')

@section('title', 'محددات الحضور - تخصيص الموظفين')

@section('content')
    @include('hr::attendance._partials.styles')


    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="feather icon-users"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">{{ $attendance_determinant->name }}</h2>
                <div class="stk-page-head__sub">تخصيص الموظفين لمحدد الحضور</div>
            </div>
        </div>
    </div>

    <div class="content-body">


        <!-- Form Actions -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <label>الحقول التي عليها علامة <span style="color: red">*</span> الزامية</label>
                    </div>
                    <div>
                        @permission('attendance_rules.show')
                            <a href="{{ route('attendance_determinants.show', $attendance_determinant->id) }}" class="btn btn-outline-secondary">
                                <i class="feather icon-arrow-left"></i> رجوع
                            </a>
                        @endpermission
                        @permission('attendance_rules.index')
                            <a href="{{ route('attendance_determinants.index') }}" class="btn btn-outline-danger">
                                <i class="feather icon-slash"></i> إلغاء
                            </a>
                        @endpermission
                        <button type="submit" form="addAttendanceForm" class="btn btn-outline-primary">
                            <i class="feather icon-save"></i> حفظ
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form Section -->
        <div class="card">
            <div class="card-body">
                @permission('attendance_rules.edit')
                    <form action="{{ route('attendance_determinants.add_employees', $attendance_determinant->id) }}" method="POST" id="addAttendanceForm">
                        @csrf
                        <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>قم بتخصيص الموظفين لمحدد الحضور</strong></p>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="determinant-name" class="form-label">اسم محدد الحضور <span style="color: red">*</span></label>
                                <input type="text" name="name" class="form-control" disabled value="{{ old('name', $attendance_determinant->name) }}">
                            </div>
                            <div class="col-md-6">
                                <label for="determinant-status" class="form-label">حالة المحدد</label>
                                <input type="text" class="form-control" disabled value="{{ $attendance_determinant->status == 0 ? 'نشط' : 'غير نشط' }}">
                            </div>
                        </div>
    
                        <hr>
    
                        <!-- Selection Criteria -->
                        <div class="mb-3">
                            <p class="mb-2"><strong>إختيار المعايير</strong></p>
                            <div class="row g-3">
                                <!-- Rules or Direct -->
                                <div class="col-12">
                                    <ul class="list-unstyled mb-0">
                                        <li class="d-inline-block mr-5">
                                            <fieldset>
                                                <div class="custom-control custom-radio">
                                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio1" {{ old('use_rules') == 'rules' ? 'checked' : '' }} value="rules">
                                                    <label class="custom-control-label" for="customRadio1">إختيار القواعد <span style="color: red">*</span></label>
                                                </div>
                                            </fieldset>
                                        </li>
                                        <li class="d-inline-block mr-5">
                                            <fieldset>
                                                <div class="custom-control custom-radio">
                                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio2" {{ old('use_rules') == 'employees' ? 'checked' : '' }} value="employees">
                                                    <label class="custom-control-label" for="customRadio2">إختيار الموظفين <span style="color: red">*</span></label>
                                                </div>
                                            </fieldset>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
    
                        <!-- Direct Employee Selection -->
                        <div id="employee" style="display: {{ old('use_rules') == 'employees' ? 'block' : 'none' }}">
                            <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>اختيار الموظفين مباشرة</strong></p>
    
                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label for="employee-select-employees">اختر الموظفين لتطبيق محدد الحضور عليهم</label>
                                    <select id="employee-select-employees" name="employee_id[]" class="form-control select2" multiple="multiple">
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ in_array($employee->id, old('employee_id', [])) ? 'selected' : '' }}>
                                                {{ $employee->full_name }} - {{ $employee->department->name ?? 'غير محدد' }} - {{ $employee->branch->name ?? 'غير محدد' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                    <small class="text-danger" id="basic-default-name-error" class="error">
                                        {{ $message }}
                                    </small>
                                    @enderror
                                </div>
                            </div>
                        </div>
    
                        <!-- Rule-Based Selection -->
                        <div id="rules" style="display: {{ old('use_rules') == 'rules' ? 'block' : 'none' }}">
                            <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>اختيار القواعد لتطبيق محدد الحضور</strong></p>
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    @include('reports.partials.affiliation-tree', [
                                        'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                        'name' => 'branch_id',
                                        'fieldId' => 'branch',
                                        'value' => old('branch_id'),
                                        'showExact' => false,
                                        'label' => 'تابع لـ',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    <label for="department">اختر القسم</label>
                                    <select id="department" name="department_id" class="form-control select2">
                                        <option value="">جميع الأقسام</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="job_title">اختر المسمى الوظيفي</label>
                                    <select id="job_title" name="job_title_id" class="form-control select2">
                                        <option value="">جميع المناصب</option>
                                        @foreach ($job_titles as $job_title)
                                            <option value="{{ $job_title->id }}" {{ old('job_title_id') == $job_title->id ? 'selected' : '' }}>{{ $job_title->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="employee-select-rules">اختر الموظفين المستبعدين (اختياري)</label>
                                    <select id="employee-select-rules" name="excluded_employee_id[]" class="form-control select2" multiple="multiple">
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ in_array($employee->id, old('excluded_employee_id', [])) ? 'selected' : '' }}>
                                                {{ $employee->full_name }} - {{ $employee->department->name ?? 'غير محدد' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('excluded_employee_id')
                                    <small class="text-danger" class="error">
                                        {{ $message }}
                                    </small>
                                    @enderror
                                </div>
                            </div>
    
                            <!-- Preview Section for Rule-Based -->
                            <div class="mt-3">
                                <div class="card">
                                    <div class="stk-table-head">
                                        <h6 class="stk-table-head__title mb-0">معاينة الموظفين المتأثرين بالقواعد</h6>
                                        <button type="button" id="previewRules" class="btn btn-info btn-sm">
                                            <i class="feather icon-eye"></i> معاينة
                                        </button>
                                    </div>
                                    <div class="card-body" id="previewResults" style="display: none;">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>اسم الموظف</th>
                                                        <th>القسم</th>
                                                        <th>الفرع</th>
                                                        <th>المسمى الوظيفي</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="previewTableBody">
                                                    <!-- سيتم ملؤها بـ JavaScript -->
                                                </tbody>
                                            </table>
                                        </div>
                                        <div id="previewCount" class="alert alert-info mt-2" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                        <!-- Settings Section -->
                        <hr>
                        <div class="mb-3">
                            <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>إعدادات إضافية</strong></p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="apply_immediately" name="apply_immediately" {{ old('apply_immediately') ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="apply_immediately">تطبيق المحدد على الفور</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="send_notification" name="send_notification" {{ old('send_notification') ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="send_notification">إرسال إشعار للموظفين</label>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                    </form>
                @endpermission
            </div>
        </div>

    </div>

@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('input[name="use_rules"]');
            const employeeSection = document.getElementById('employee');
            const rulesSection = document.getElementById('rules');
            const employeeInputs = employeeSection.querySelectorAll('input, select, textarea');
            const rulesInputs = rulesSection.querySelectorAll('input, select, textarea');

            function toggleInputs(section, inputs, isEnabled) {
                inputs.forEach(input => {
                    if (isEnabled) {
                        input.removeAttribute('disabled');
                    } else {
                        input.setAttribute('disabled', 'disabled');
                    }
                });
            }

            radios.forEach(radio => {
                radio.addEventListener('change', function () {
                    if (this.id === 'customRadio1') {
                        employeeSection.style.display = 'none';
                        rulesSection.style.display = 'block';

                        toggleInputs(employeeSection, employeeInputs, false);
                        toggleInputs(rulesSection, rulesInputs, true);
                    } else if (this.id === 'customRadio2') {
                        employeeSection.style.display = 'block';
                        rulesSection.style.display = 'none';

                        toggleInputs(employeeSection, employeeInputs, true);
                        toggleInputs(rulesSection, rulesInputs, false);
                    }

                    // إعادة تهيئة select2
                    $('#employee-select-employees').select2({
                        allowClear: true,
                        placeholder: "اختر الموظفين..."
                    });

                    $('#employee-select-rules').select2({
                        allowClear: true,
                        placeholder: "اختر الموظفين المستبعدين..."
                    });
                });
            });

            // عند تحميل الصفحة، تحقق من الحالة الحالية
            if (document.getElementById('customRadio1').checked) {
                toggleInputs(employeeSection, employeeInputs, false);
                toggleInputs(rulesSection, rulesInputs, true);
            } else if (document.getElementById('customRadio2').checked) {
                toggleInputs(employeeSection, employeeInputs, true);
                toggleInputs(rulesSection, rulesInputs, false);
            }

            // تهيئة select2 للجميع
            $('.select2').select2({
                allowClear: true
            });

            // معاينة القواعد
            $('#previewRules').on('click', function() {
                const branchId = $('#branch').val();
                const departmentId = $('#department').val();
                const jobTitleId = $('#job_title').val();
                const excludedEmployees = $('#employee-select-rules').val() || [];

                if (!branchId && !departmentId && !jobTitleId) {
                    Swal.fire({
                        title: 'تنبيه',
                        text: 'يرجى اختيار معيار واحد على الأقل لمعاينة النتائج',
                        icon: 'warning'
                    });
                    return;
                }

                $.ajax({
                    url: '{{ route("attendance_determinants.preview_rules") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        branch_id: branchId,
                        department_id: departmentId,
                        job_title_id: jobTitleId,
                        excluded_employee_id: excludedEmployees
                    },
                    beforeSend: function() {
                        $('#previewRules').html('<i class="fa fa-spinner fa-spin"></i> جاري التحميل...');
                        $('#previewRules').prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            displayPreviewResults(response.employees);
                            $('#previewResults').show();
                            $('#previewCount').text(`عدد الموظفين المتأثرين: ${response.employees.length}`).show();
                        } else {
                            Swal.fire({
                                title: 'خطأ',
                                text: response.message || 'حدث خطأ أثناء المعاينة',
                                icon: 'error'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'خطأ',
                            text: 'حدث خطأ في الاتصال بالخادم',
                            icon: 'error'
                        });
                    },
                    complete: function() {
                        $('#previewRules').html('<i class="feather icon-eye"></i> معاينة');
                        $('#previewRules').prop('disabled', false);
                    }
                });
            });

            function displayPreviewResults(employees) {
                let html = '';
                if (employees.length > 0) {
                    employees.forEach(function(employee) {
                        html += `
                            <tr>
                                <td>${employee.full_name || 'غير محدد'}</td>
                                <td>${employee.department ? employee.department.name : 'غير محدد'}</td>
                                <td>${employee.branch ? employee.branch.name : 'غير محدد'}</td>
                                <td>${employee.job_title ? employee.job_title.name : 'غير محدد'}</td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="4" class="text-center">لا يوجد موظفين يطابقون المعايير المحددة</td></tr>';
                }
                $('#previewTableBody').html(html);
            }

            // إضافة SweetAlert2 إذا لم يكن موجوداً
            if (typeof Swal === 'undefined') {
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                document.head.appendChild(script);
            }
        });
    </script>
@endsection
