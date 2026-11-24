@extends('hr::master')

@section('title', 'دفاتر الحضور')

{{--
    زرّا الترويسة يُطلقان زرَّي التذييل: `#cancelBtn` و`#saveBtn` هما من
    يحملان تأكيد SweetAlert والتحقّق، فلا تُكرَّر مستمعاتهما هنا.
--}}
@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('cancelBtn').click()">
        <i class="feather icon-x"></i> إلغاء
    </button>
    @permission('attendance_books.create')
        <button type="button" class="btn btn-primary" onclick="document.getElementById('saveBtn').click()">
            <i class="feather icon-check"></i> حفظ
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة دفتر حضور',
        'icon' => 'book-open',
        'crumbs' => ['الرئيسية' => url('/'), 'دفاتر الحضور' => route('attendance_sheets.index'), 'إضافة' => null],
    ])

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

    @permission('attendance_books.create')
        <form action="{{ route('attendance_sheets.store') }}" method="POST" id="addAttendanceForm">
            @csrf

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-3 mb-2">
                            <label for="from-date" class="form-label stk-req">من</label>
                            <input type="date" id="from-date" name="from_date" class="form-control" value="{{ old('from_date') }}">
                            @error('from_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-6 col-xl-3 mb-2">
                            <label for="to-date" class="form-label stk-req">الي</label>
                            <input type="date" id="to-date" name="to_date" class="form-control" value="{{ old('to_date') }}">
                            @error('to_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-12 col-xl-6 mb-2">
                            <label class="form-label stk-req">إختيار المعايير</label>
                            <div class="d-flex flex-wrap" style="gap: 1.5rem; padding-top: .5rem">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio1" {{ old('use_rules') == 'rules' ? 'checked' : '' }} value="rules">
                                    <label class="custom-control-label" for="customRadio1">إختيار القواعد</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="use_rules" id="customRadio2" {{ old('use_rules') == 'employees' ? 'checked' : '' }} value="employees">
                                    <label class="custom-control-label" for="customRadio2">إختيار الموظفين</label>
                                </div>
                            </div>
                            <small class="stk-help">القواعد تُنشئ الدفتر لكل من يطابقها؛ واختيار الموظفين يُنشئ دفتراً لكل موظف محدد.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="employee" style="display: {{ old('use_rules') == 'employees' ? 'block' : 'none' }}">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-users"></i> اختيار الموظفين</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 col-xl-8 mb-2">
                            <label for="employee-select-employees" class="form-label stk-req">اختر الموظفين</label>
                            <select id="employee-select-employees" name="employee_id[]" class="form-control select2" multiple="multiple">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ in_array($employee->id, old('employee_id', [])) ? 'selected' : '' }}>
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

            <div class="card" id="rules" style="display: {{ old('use_rules') == 'rules' ? 'block' : 'none' }}">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-sliders"></i> اختيار القواعد</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-2">
                            @include('reports.partials.affiliation-tree', [
                                'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes(),
                                'name' => 'branch_id',
                                'fieldId' => 'branch',
                                'value' => old('branch_id'),
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
                                    <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
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
                                    <option value="{{ $job_title->id }}" {{ old('job_title_id') == $job_title->id ? 'selected' : '' }}>{{ $job_title->name }}</option>
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
                                    <option value="{{ $shift->id }}" {{ old('shifts_id') == $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
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
                            <select id="employee-select-rules" name="excluded_employee_id[]" class="form-control select2" multiple="multiple">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ in_array($employee->id, old('excluded_employee_id', [])) ? 'selected' : '' }}>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="stk-help">من تختاره هنا لن يُنشأ له دفتر، ولو طابق القواعد أعلاه.</small>
                            @error('excluded_employee_id')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                    <small class="stk-help mb-0">اختر قاعدة واحدة على الأقل (فرع أو قسم أو مسمى وظيفي أو وردية).</small>
                </div>
            </div>
        </form>
    @endpermission

    <div class="card">
        <div class="stk-form-foot">
            <button type="button" id="cancelBtn" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </button>
            <button type="button" id="saveBtn" class="btn btn-primary">
                <i class="feather icon-check"></i> حفظ
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
    const form = document.getElementById('addAttendanceForm');
    const saveBtn = document.getElementById('saveBtn');
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

    // Validation function
    function validateForm() {
        const fromDate = document.querySelector('input[name="from_date"]').value;
        const toDate = document.querySelector('input[name="to_date"]').value;
        const useRules = document.querySelector('input[name="use_rules"]:checked')?.value;

        if (!fromDate) {
            Swal.fire({
                title: 'خطأ في البيانات',
                text: 'يرجى تحديد تاريخ البداية',
                icon: 'error',
                confirmButtonText: 'حسناً',
                ...swalConfig
            });
            return false;
        }

        if (!toDate) {
            Swal.fire({
                title: 'خطأ في البيانات',
                text: 'يرجى تحديد تاريخ النهاية',
                icon: 'error',
                confirmButtonText: 'حسناً',
                ...swalConfig
            });
            return false;
        }

        if (new Date(fromDate) > new Date(toDate)) {
            Swal.fire({
                title: 'خطأ في التواريخ',
                text: 'تاريخ البداية يجب أن يكون أقل من أو يساوي تاريخ النهاية',
                icon: 'error',
                confirmButtonText: 'حسناً',
                ...swalConfig
            });
            return false;
        }

        if (!useRules) {
            Swal.fire({
                title: 'خطأ في البيانات',
                text: 'يرجى اختيار طريقة إضافة الموظفين (قواعد أو موظفين محددين)',
                icon: 'error',
                confirmButtonText: 'حسناً',
                ...swalConfig
            });
            return false;
        }

        if (useRules === 'employees') {
            const selectedEmployees = document.querySelector('#employee-select-employees').value;
            if (!selectedEmployees || selectedEmployees.length === 0) {
                Swal.fire({
                    title: 'خطأ في البيانات',
                    text: 'يرجى اختيار موظف واحد على الأقل',
                    icon: 'error',
                    confirmButtonText: 'حسناً',
                    ...swalConfig
                });
                return false;
            }
        }

        // وضعُ القواعد بلا قاعدةٍ واحدة يعني كلَّ الموظفين — والخادمُ يرفضه،
        // فيُنبَّه هنا قبل الإرسال.
        if (useRules === 'rules') {
            const hasRule = ['branch', 'department', 'job_title', 'shifts_id']
                .some(id => document.getElementById(id)?.value);

            if (!hasRule) {
                Swal.fire({
                    title: 'خطأ في البيانات',
                    text: 'يرجى اختيار قاعدة واحدة على الأقل (فرع أو قسم أو مسمى وظيفي أو وردية)',
                    icon: 'error',
                    confirmButtonText: 'حسناً',
                    ...swalConfig
                });
                return false;
            }
        }

        return true;
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
                allowClear: true,
                placeholder: 'اختر الموظفين'
            });

            $('#employee-select-rules').select2({
                allowClear: true,
                placeholder: 'اختر الموظفين المستبعدين'
            });
        });
    });

    // Handle Save Button Click with SweetAlert2 Confirmation and Validation
    saveBtn.addEventListener('click', function() {
        // Validate form first
        if (!validateForm()) {
            return;
        }

        const useRules = document.querySelector('input[name="use_rules"]:checked').value;
        let confirmationText = 'هل أنت متأكد من حفظ بيانات دفتر الحضور؟';

        if (useRules === 'employees') {
            const selectedEmployees = $('#employee-select-employees').select2('data');
            const employeeCount = selectedEmployees.length;
            confirmationText = `سيتم إنشاء ${employeeCount} دفتر حضور منفصل للموظفين المحددين. هل أنت متأكد؟`;
        }

        Swal.fire({
            title: 'تأكيد الحفظ',
            text: confirmationText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، احفظ',
            cancelButtonText: 'إلغاء',
            ...swalConfig
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'جاري الحفظ...',
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
            text: 'هل أنت متأكد من إلغاء العملية؟ سيتم فقدان جميع البيانات المدخلة.',
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

    // عند تحميل الصفحة، تحقق من الحالة الحالية
    if (document.getElementById('customRadio1').checked) {
        toggleInputs(employeeSection, employeeInputs, false);
        toggleInputs(rulesSection, rulesInputs, true);
    } else if (document.getElementById('customRadio2').checked) {
        toggleInputs(employeeSection, employeeInputs, true);
        toggleInputs(rulesSection, rulesInputs, false);
    }

    // Handle Server Messages with Enhanced SweetAlert2
    @if ($errors->any())
        let errorMessages = '';
        @foreach ($errors->all() as $error)
            errorMessages += '• {{ $error }}\n';
        @endforeach

        Swal.fire({
            title: 'خطأ في البيانات',
            html: errorMessages.replace(/\n/g, '<br>'),
            icon: 'error',
            confirmButtonText: 'حسناً',
            width: '500px',
            ...swalConfig
        });
    @endif

    @if (session('success'))
        Swal.fire({
            title: 'تم بنجاح!',
            text: '{{ session('success') }}',
            icon: 'success',
            confirmButtonText: 'ممتاز',
            timer: 5000,
            timerProgressBar: true,
            ...swalConfig
        });
    @endif

    @if (session('info'))
        Swal.fire({
            title: 'معلومة',
            text: '{{ session('info') }}',
            icon: 'info',
            confirmButtonText: 'حسناً',
            ...swalConfig
        });
    @endif

    @if (session('warning'))
        Swal.fire({
            title: 'تنبيه',
            html: '{{ session('warning') }}'.replace(/\n/g, '<br>'),
            icon: 'warning',
            confirmButtonText: 'حسناً',
            width: '600px',
            ...swalConfig
        });
    @endif

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
