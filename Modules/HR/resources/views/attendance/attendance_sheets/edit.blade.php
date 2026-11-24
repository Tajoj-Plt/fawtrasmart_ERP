@extends('hr::master')

@section('title', 'تعديل دفتر الحضور')

{{--
    زرّا الترويسة يُطلقان زرَّي التذييل: `#cancelBtn` و`#updateBtn` هما من
    يحملان تأكيد SweetAlert، فلا تُكرَّر مستمعاتهما هنا.
--}}
@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('cancelBtn').click()">
        <i class="feather icon-x"></i> إلغاء
    </button>
    @permission('attendance_books.edit')
        <button type="button" class="btn btn-primary" onclick="document.getElementById('updateBtn').click()">
            <i class="feather icon-check"></i> حفظ التعديلات
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل دفتر الحضور #'.$attendanceSheet->id,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'دفاتر الحضور' => route('attendance_sheets.index'),
            '#'.$attendanceSheet->id => hasPermission('attendance_books.show') ? route('attendance_sheets.show', $attendanceSheet->id) : null,
            'تعديل' => null,
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @if ($errors->any())
        <div class="stk-form-errors alert alert-danger" role="alert" id="errorAlert">
            <div class="stk-form-errors__head">
                <i class="feather icon-alert-circle"></i> لم يُحفظ الدفتر — راجع ما يلي:
            </div>
            <ul class="stk-form-errors__list mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @permission('attendance_books.edit')
        <form action="{{ route('attendance_sheets.update', $attendanceSheet->id) }}" method="POST" id="editAttendanceForm">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-3 mb-2">
                            <label for="from-date" class="form-label stk-req">من</label>
                            <input type="date" id="from-date" name="from_date" class="form-control" value="{{ old('from_date', $attendanceSheet->from_date) }}">
                            @error('from_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-6 col-xl-3 mb-2">
                            <label for="to-date" class="form-label stk-req">إلى</label>
                            <input type="date" id="to-date" name="to_date" class="form-control" value="{{ old('to_date', $attendanceSheet->to_date) }}">
                            @error('to_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-12 col-xl-6 mb-2">
                            <label class="form-label stk-req">إختيار المعايير</label>
                            <div class="d-flex flex-wrap" style="gap: 1.5rem; padding-top: .5rem">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio1" {{ old('use_rules', $attendanceSheet->use_rules) == 1 ? 'checked' : '' }} value="rules">
                                    <label class="custom-control-label" for="customRadio1">إختيار القواعد</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio2" {{ old('use_rules', $attendanceSheet->use_rules) == 2 ? 'checked' : '' }} value="employees">
                                    <label class="custom-control-label" for="customRadio2">إختيار الموظفين</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="employee" style="display: {{ old('use_rules', $attendanceSheet->use_rules) == 2 ? 'block' : 'none' }}">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-users"></i> اختيار الموظفين</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 col-xl-8 mb-2">
                            <label for="employee-select-employees" class="form-label stk-req">اختر الموظفين</label>
                            @php
                                $selectedEmployees = old('employee_id', $attachedEmployeeIds ?? $attendanceSheet->employees->pluck('id')->toArray());
                            @endphp
                            <select id="employee-select-employees" name="employee_id[]" class="form-control select2" multiple="multiple">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ in_array($employee->id, $selectedEmployees) ? 'selected' : '' }}>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($employees ?? [])"
                                route="employee.create"
                                permission="employees.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::EMPLOYEES"
                                label="موظفاً"
                                hint="العملية تقع على موظف، ولا موظف مسجَّل بعد." />
                            @error('employee_id')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="rules" style="display: {{ old('use_rules', $attendanceSheet->use_rules) == 1 ? 'block' : 'none' }}">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-sliders"></i> اختيار القواعد</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-2">
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes([$attendanceSheet->branch_id]),
                                'name' => 'branch_id',
                                'fieldId' => 'branch',
                                'value' => old('branch_id', $attendanceSheet->branch_id),
                                'showExact' => false,
                                'label' => 'تابع لـ',
                            ])
                            <x-create-first
                                :count="count($branches ?? [])"
                                route="branches.create"
                                label="فرعاً"
                                hint="الفروع تُدار في وحدة الفروع، ولا فرع مسجَّل بعد." />
                        </div>
                        <div class="col-md-6 col-xl-4 mb-2">
                            <label for="department" class="form-label">القسم</label>
                            <select id="department" name="department_id" class="form-control select2">
                                <option value="" disabled selected>اختر قسم</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" {{ old('department_id', $attendanceSheet->department_id) == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($departments ?? [])"
                                route="department.create"
                                permission="departments.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::DEPARTMENTS"
                                label="قسماً"
                                hint="الأقسام جزءٌ من الهيكل التنظيمي، ولا قسم مسجَّل بعد." />
                        </div>
                        <div class="col-md-6 col-xl-4 mb-2">
                            <label for="job_title" class="form-label">المسمى الوظيفي</label>
                            <select id="job_title" name="job_title_id" class="form-control select2">
                                <option value="" disabled selected>اختر المسمى الوظيفي</option>
                                @foreach ($job_titles as $job_title)
                                    <option value="{{ $job_title->id }}" {{ old('job_title_id', $attendanceSheet->job_title_id) == $job_title->id ? 'selected' : '' }}>{{ $job_title->name }}</option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($job_titles ?? [])"
                                route="JobTitles.create"
                                permission="job_titles.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::JOB_TITLES"
                                label="مسمّى وظيفياً"
                                hint="المسمّيات تُعرَّف في الهيكل التنظيمي، ولا مسمّى بعد." />
                        </div>
                        <div class="col-md-6 col-xl-4 mb-2">
                            <label for="shifts_id" class="form-label">الوردية</label>
                            <select name="shifts_id" id="shifts_id" class="form-control select2">
                                <option value="" disabled selected>اختر الوردية</option>
                                @foreach ($shifts as $shift)
                                    <option value="{{ $shift->id }}" {{ old('shifts_id', $attendanceSheet->shifts_id) == $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($shifts ?? [])"
                                route="custom_shifts.create"
                                permission="shifts.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::SHIFTS"
                                label="وردية"
                                hint="الورديات تُعرَّف في إعدادات الحضور، ولا وردية بعد." />
                        </div>
                        <div class="col-md-12 col-xl-8 mb-2">
                            <label for="employee-select-rules" class="form-label">اختر الموظفين المستبعدين</label>
                            @php
                                // ما استُبعد سابقاً يُستنتج في المتحكم: مطابقو القواعد
                                // ناقصَ من ارتبط بالدفتر فعلاً.
                                $selectedEmployeesExcluded = old('excluded_employee_id', $excludedEmployeeIds ?? []);
                            @endphp
                            <select id="employee-select-rules" name="excluded_employee_id[]" class="form-control select2" multiple="multiple">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ in_array($employee->id, $selectedEmployeesExcluded) ? 'selected' : '' }}>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="stk-help">من تختاره هنا يُحذف من الدفتر، ولو طابق القواعد أعلاه.</small>
                            @error('excluded_employee_id')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endpermission

    <div class="card">
        <div class="stk-form-foot">
            <button type="button" id="cancelBtn" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </button>
            <button type="button" id="updateBtn" class="btn btn-primary">
                <i class="feather icon-check"></i> حفظ التعديلات
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('input[name="use_rules"]');
            const employeeSection = document.getElementById('employee');
            const rulesSection = document.getElementById('rules');
            const employeeInputs = employeeSection.querySelectorAll('input, select, textarea');
            const rulesInputs = rulesSection.querySelectorAll('input, select, textarea');
            const form = document.getElementById('editAttendanceForm');
            const updateBtn = document.getElementById('updateBtn');
            const cancelBtn = document.getElementById('cancelBtn');

            // SweetAlert2 Configuration in Arabic
            const swalConfig = {
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-success mx-1',
                    cancelButton: 'btn btn-danger mx-1'
                },
                buttonsStyling: false
            };

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

                    $('#employee-select-employees').select2({
                        allowClear: true
                    });

                    $('#employee-select-rules').select2({
                        allowClear: true
                    });
                });
            });

            // Handle Update Button Click with SweetAlert2 Confirmation
            updateBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'تأكيد التحديث',
                    text: 'هل أنت متأكد من تحديث بيانات دفتر الحضور؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، حديث',
                    cancelButtonText: 'إلغاء',
                    ...swalConfig
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'جاري التحديث...',
                            text: 'يرجى الانتظار',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Submit the form
                        form.submit();
                    }
                });
            });

            // Handle Cancel Button Click with SweetAlert2 Confirmation
            cancelBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'تأكيد الإلغاء',
                    text: 'هل أنت متأكد من إلغاء التعديل؟ سيتم فقدان جميع التغييرات غير المحفوظة.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، ألغي',
                    cancelButtonText: 'لا، تراجع',
                    ...swalConfig
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "{{ route('attendance_sheets.index') }}";
                    }
                });
            });

            // Initialize Select2 on page load
            $('#employee-select-employees').select2({
                allowClear: true,
                placeholder: 'اختر الموظفين...'
            });

            $('#employee-select-rules').select2({
                allowClear: true,
                placeholder: 'اختر الموظفين المستبعدين...'
            });

            // Initialize other Select2 dropdowns
            $('.select2').select2({
                allowClear: true
            });

            // عند تحميل الصفحة، تحقق من الحالة الحالية
            if (document.getElementById('customRadio1').checked) {
                toggleInputs(employeeSection, employeeInputs, false);
                toggleInputs(rulesSection, rulesInputs, true);
            } else if (document.getElementById('customRadio2').checked) {
                toggleInputs(employeeSection, employeeInputs, true);
                toggleInputs(rulesSection, rulesInputs, false);
            }

            // Handle Server Errors Display with SweetAlert2
            @if ($errors->any())
                let errorMessages = '';
                @foreach ($errors->all() as $error)
                    errorMessages += '• {{ $error }}\n';
                @endforeach

                Swal.fire({
                    title: 'خطأ في البيانات',
                    text: errorMessages,
                    icon: 'error',
                    confirmButtonText: 'حسناً',
                    ...swalConfig
                });
            @endif

            // Handle Success Messages
            @if (session('success'))
                Swal.fire({
                    title: 'تم التحديث بنجاح!',
                    text: '{{ session('success') }}',
                    icon: 'success',
                    confirmButtonText: 'ممتاز',
                    timer: 3000,
                    timerProgressBar: true,
                    ...swalConfig
                });
            @endif

            // Handle Info Messages
            @if (session('info'))
                Swal.fire({
                    title: 'معلومة',
                    text: '{{ session('info') }}',
                    icon: 'info',
                    confirmButtonText: 'حسناً',
                    ...swalConfig
                });
            @endif

            // Handle Warning Messages
            @if (session('warning'))
                Swal.fire({
                    title: 'تنبيه',
                    text: '{{ session('warning') }}',
                    icon: 'warning',
                    confirmButtonText: 'حسناً',
                    ...swalConfig
                });
            @endif

            // Handle Error Messages
            @if (session('error'))
                Swal.fire({
                    title: 'خطأ',
                    text: '{{ session('error') }}',
                    icon: 'error',
                    confirmButtonText: 'حسناً',
                    ...swalConfig
                });
            @endif
        });
    </script>
@endsection
