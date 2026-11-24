@extends('hr::master')

@section('title', 'حساب أيام الحضور')

@section('content')
    @include('hr::attendance._partials.styles')


    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="fa fa-calculator"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">حساب أيام الحضور</h2>
                <div class="stk-page-head__sub">معالجة بيانات الحضور والغياب</div>
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
                        @permission('attendance_days.index')
                            <a href="{{ route('attendanceDays.index') }}" class="btn btn-outline-danger">
                                <i class="feather icon-slash"></i> إلغاء
                            </a>
                        @endpermission
                        <button type="button" id="calculateButton" class="btn btn-primary">
                            <i class="fa fa-calculator"></i> حساب
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
                @permission('attendance_days.calculate')
                    <form action="{{ route('attendanceDays.runCalculation') }}" method="POST" id="addAttendanceForm">
                        @csrf
                        <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>تفاصيل حساب أيام الحضور</strong></p>
                        <p class="text-muted small mb-3">يُشغَّل معالج الحضور الحقيقي (أختام، ورديات، إجازات، عطلات) — لا يُنشئ أيام غياب فارغة. التواريخ المستقبلية تُتجاهل.</p>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="attendance-date" class="form-label">من <span style="color: red">*</span></label>
                                <input type="date" name="from_date" class="form-control" value="{{ old('from_date') }}" required>
                                @error('from_date')
                                <small class="text-danger" id="basic-default-name-error" class="error">
                                    {{ $message }}
                                </small>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="attendance-date" class="form-label">الي <span style="color: red">*</span></label>
                                <input type="date" name="to_date" class="form-control" value="{{ old('to_date') }}" required>
                                @error('to_date')
                                <small class="text-danger" id="basic-default-name-error" class="error">
                                    {{ $message }}
                                </small>
                                @enderror
                            </div>
                        </div>
    
                        <hr>
    
                        <!-- Attendance Status -->
                        <div class="mb-3">
                            <p class="mb-2"><strong>إختيار المعايير</strong></p>
                            <div class="row g-3">
                                <!-- Present -->
                                <div class="col-12">
                                    <ul class="list-unstyled mb-0">
                                        <li class="d-inline-block mr-5">
                                            <fieldset>
                                                <div class="custom-control custom-radio">
                                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio1" {{ old('use_rules') == 'rules' ? 'checked' : '' }} value="rules" required>
                                                    <label class="custom-control-label" for="customRadio1">إختيار القواعد <span style="color: red">*</span></label>
                                                </div>
                                            </fieldset>
                                        </li>
                                        <li class="d-inline-block mr-5">
                                            <fieldset>
                                                <div class="custom-control custom-radio">
                                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio2" {{ old('use_rules') == 'employees' ? 'checked' : '' }} value="employees" required>
                                                    <label class="custom-control-label" for="customRadio2">إختيار الموظفين <span style="color: red">*</span></label>
                                                </div>
                                            </fieldset>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
    
                        <div id="employee" style="display: {{ old('use_rules') == 'employees' ? 'block' : 'none' }}">
                            <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>اختيار الموظفين</strong></p>
    
                            <div class="row g-3 mb-3">
    
                                <div class="col-md-6">
                                    <label for="" selected>اختر الموظفين <span style="color: red">*</span></label>
                                    <select id="employee-select-employees" name="employee_id[]" class="form-control select2" multiple="multiple" required>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ in_array($employee->id, old('employee_id', [])) ? 'selected' : '' }}>
                                                {{ $employee->full_name }}
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
    
                        <div id="rules"style="display: {{ old('use_rules') == 'rules' ? 'block' : 'none' }}">
                            <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>اختيار القواعد</strong></p>
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
                                    <label for="">القسم</label>
                                    <select id="department" name="department_id" class="form-control select2">
                                        <option value="" disabled selected>اختر قسم</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="">المسمى الوظيفي</label>
                                    <select id="job_title" name="job_title_id" class="form-control select2">
                                        <option value="" disabled selected>اختر المسمى الوظيفي</option>
                                        @foreach ($job_titles as $job_title)
                                            <option value="{{ $job_title->id }}" {{ old('job_title_id') == $job_title->id ? 'selected' : '' }}>{{ $job_title->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="">الوردية</label>
                                    <select name="shifts_id" id="shifts_id" class="form-control select2">
                                        <option value="" disabled selected>اختر الوردية</option>
                                        @foreach ($shifts as $shift)
                                            <option value="{{ $shift->id }}" {{ old('shifts_id') == $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="employee-select-rules">اختر الموظفين المستبعدين</label>
                                    <select id="employee-select-rules" name="excluded_employee_id[]" class="form-control select2" multiple="multiple">
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" {{ in_array($employee->id, old('excluded_employee_id', [])) ? 'selected' : '' }}>
                                                {{ $employee->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">من تختاره هنا لن يُحسب، ولو طابق القواعد أعلاه.</small>
                                    @error('excluded_employee_id')
                                    <small class="text-danger d-block" class="error">
                                        {{ $message }}
                                    </small>
                                    @enderror
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
    <!-- SweetAlert2 Library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('input[name="use_rules"]');
            const employeeSection = document.getElementById('employee');
            const rulesSection = document.getElementById('rules');
            const employeeInputs = employeeSection.querySelectorAll('input, select, textarea');
            const rulesInputs = rulesSection.querySelectorAll('input, select, textarea');
            const calculateButton = document.getElementById('calculateButton');
            const form = document.getElementById('addAttendanceForm');

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

            // عند تحميل الصفحة، تحقق من الحالة الحالية
            if (document.getElementById('customRadio1').checked) {
                toggleInputs(employeeSection, employeeInputs, false);
                toggleInputs(rulesSection, rulesInputs, true);
            } else if (document.getElementById('customRadio2').checked) {
                toggleInputs(employeeSection, employeeInputs, true);
                toggleInputs(rulesSection, rulesInputs, false);
            }

            // SweetAlert2 للتأكيد قبل الحساب
            calculateButton.addEventListener('click', function(e) {
                e.preventDefault();

                // التحقق من صحة النموذج
                if (!form.checkValidity()) {
                    // عرض رسالة خطأ إذا كانت الحقول المطلوبة فارغة
                    Swal.fire({
                        icon: 'warning',
                        title: 'تحذير!',
                        text: 'يرجى ملء جميع الحقول المطلوبة',
                        confirmButtonText: 'موافق',
                    });
                    form.reportValidity();
                    return;
                }

                // التحقق من اختيار المعايير المناسبة
                const selectedCriteria = document.querySelector('input[name="use_rules"]:checked');
                if (selectedCriteria && selectedCriteria.value === 'employees') {
                    const selectedEmployees = document.getElementById('employee-select-employees');
                    if (!selectedEmployees.value || selectedEmployees.value.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تحذير!',
                            text: 'يرجى اختيار الموظفين المطلوبين',
                            confirmButtonText: 'موافق',
                        });
                        return;
                    }
                }

                // وضعُ القواعد بلا قاعدةٍ واحدة يعني كلَّ الموظفين — والخادمُ
                // يرفضه، فيُنبَّه هنا قبل الإرسال.
                if (selectedCriteria && selectedCriteria.value === 'rules') {
                    const ruleFields = ['branch', 'department', 'job_title', 'shifts_id'];
                    const hasRule = ruleFields.some(id => {
                        const field = document.getElementById(id);
                        return field && field.value;
                    });

                    if (!hasRule) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تحذير!',
                            text: 'يرجى اختيار قاعدة واحدة على الأقل (فرع أو قسم أو مسمى وظيفي أو وردية)',
                            confirmButtonText: 'موافق',
                        });
                        return;
                    }
                }

                // عرض تأكيد الحساب
                Swal.fire({
                    title: 'تأكيد عملية الحساب',
                    text: 'هل أنت متأكد من حساب أيام الحضور للفترة المحددة؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احسب',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // عرض loading أثناء الحساب
                        Swal.fire({
                            title: 'جاري الحساب...',
                            text: 'يرجى الانتظار، جاري معالجة البيانات',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // إرسال النموذج
                        form.submit();
                    }
                });
            });

            // التحقق من وجود رسالة نجاح من الخادم
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'تم الحساب بنجاح!',
                    text: '{{ session('success') }}',
                    confirmButtonText: 'موافق',
                });
            @endif

            // التحقق من وجود رسالة خطأ من الخادم
            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في العملية!',
                    text: '{{ session('error') }}',
                    confirmButtonText: 'موافق',
                });
            @endif
        });
    </script>
@endsection
