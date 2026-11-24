@extends('hr::master')

@section('title', 'سياسة الإجازات - تخصيص الموظفين')

@section('content')
    @include('hr::attendance._partials.styles')


    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="feather icon-users"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">{{ $leave_policy->name }}</h2>
                <div class="stk-page-head__sub">تخصيص الموظفين لسياسة الإجازات</div>
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
                        @permission('leave_policies.index')
                            <a href="{{ route('leave_policy.index') }}" class="btn btn-outline-danger">
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
                @permission('leave_policies.edit')
                    <form action="{{ route('leave_policy.add_leave_policy_employees', $leave_policy->id) }}" method="POST" id="addAttendanceForm">
                        @csrf
                        <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>قم بالتخصيص للموظفين</strong></p>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="attendance-date" class="form-label">الاسم <span style="color: red">*</span></label>
                                <input type="text" name="name" class="form-control" disabled value="{{ old('name', $leave_policy->name) }}">
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
    
                        <div id="employee" style="display: {{ old('use_rules') == 'employees' ? 'block' : 'none' }}">
                            <p style="background-color: var(--app-body-bg, #f8f8f8); padding: 10px"><strong>اختيار الموظفين</strong></p>
    
                            <div class="row g-3 mb-3">
    
                                <div class="col-md-6">
                                    <label for="" selected>اختر الموظفين</label>
                                    <select id="employee-select-employees" name="employee_id[]" class="form-control select2" multiple="multiple">
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
                                    <label for=""></label>
                                    <select id="department" name="department_id" class="form-control select2">
                                        <option value="" disabled selected>اختر قسم</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for=""></label>
                                    <select id="job_title" name="job_title_id" class="form-control select2">
                                        <option value="" disabled selected>اختر المسمى الوظيفي</option>
                                        @foreach ($job_titles as $job_title)
                                            <option value="{{ $job_title->id }}" {{ old('job_title_id') == $job_title->id ? 'selected' : '' }}>{{ $job_title->name }}</option>
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
                                    <small class="text-muted">من تختاره هنا لا تشمله السياسة، ولو طابق القواعد أعلاه.</small>
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
        });
    </script>
@endsection
