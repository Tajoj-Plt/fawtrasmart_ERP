{{--
    نموذجُ الوردية المتخصصة — مشتركٌ بين «إضافة» و«تعديل».

    يستقبل: $customShift (null عند الإضافة)، $shifts، $employees، $branches،
    $departments، $job_titles، و$excludedEmployeeIds عند التعديل.
    المعرّفات (`#employee`, `#rules`, `#customRadio1/2`, `#employee-select-*`)
    يقرؤها سكربت الصفحة لإظهار أحد القسمين وتعطيل مدخلات الآخر.
--}}
@php
    $cs = $customShift ?? null;
    $useRules = old('use_rules', $cs->use_rules ?? null);
    $selectedEmployees = old('employee_id', $cs ? ($cs->employees?->pluck('id')->toArray() ?? []) : []);
    // لا علاقة `excludedEmployees` على النموذج — كان الشرطُ يُقيَّم دوماً
    // بمصفوفة فارغة. تُستنتج في المتحكم.
    $selectedExcluded = old('excluded_employee_id', $cs ? ($excludedEmployeeIds ?? []) : []);
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم تُحفظ الوردية — راجع الحقول المعلَّمة:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 col-xl-4 mb-3">
                <label for="cs_name" class="form-label stk-req">اسم الوردية</label>
                <input type="text" id="cs_name" name="name" class="form-control" value="{{ old('name', $cs->name ?? '') }}">
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <label for="is_main" class="form-label stk-req">نوع الوردية</label>
                <select name="is_main" id="is_main" class="form-control select2" required>
                    <option value="" disabled {{ $cs ? '' : 'selected' }}>اختر نوع الوردية</option>
                    <option value="1" {{ old('is_main', $cs->is_main ?? null) == '1' ? 'selected' : '' }}>رئيسي</option>
                    <option value="0" {{ ($cs ? old('is_main', $cs->is_main) : old('is_main')) == '0' ? 'selected' : '' }}>فرعي</option>
                </select>
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <label for="shift_id" class="form-label">الوردية</label>
                <select name="shift_id" id="shift_id" class="form-control select2">
                    <option value="" disabled selected>اختر الوردية</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" {{ old('shift_id', $cs->shift_id ?? null) == $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
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
            <div class="col-md-6 col-xl-3 mb-3">
                <label for="cs_from_date" class="form-label stk-req">تاريخ البدء</label>
                <input type="date" id="cs_from_date" name="from_date" class="form-control" value="{{ old('from_date', $cs->from_date ?? '') }}">
            </div>
            <div class="col-md-6 col-xl-3 mb-3">
                <label for="cs_to_date" class="form-label stk-req">تاريخ الإنتهاء</label>
                <input type="date" id="cs_to_date" name="to_date" class="form-control" value="{{ old('to_date', $cs->to_date ?? '') }}">
            </div>
            <div class="col-md-12 col-xl-6 mb-3">
                <label class="form-label stk-req d-block">إختيار المعايير</label>
                <ul class="list-unstyled mb-0 pt-50">
                    <li class="d-inline-block mr-2">
                        <fieldset>
                            <div class="custom-control custom-radio">
                                <input type="radio" class="custom-control-input" name="use_rules" id="customRadio1" {{ $useRules == 'rules' ? 'checked' : '' }} value="rules">
                                <label class="custom-control-label" for="customRadio1">إختيار القواعد</label>
                            </div>
                        </fieldset>
                    </li>
                    <li class="d-inline-block mr-2">
                        <fieldset>
                            <div class="custom-control custom-radio">
                                <input type="radio" class="custom-control-input" name="use_rules" id="customRadio2" {{ $useRules == 'employees' ? 'checked' : '' }} value="employees">
                                <label class="custom-control-label" for="customRadio2">إختيار الموظفين</label>
                            </div>
                        </fieldset>
                    </li>
                </ul>
                <small class="stk-help">حدّد من تشملهم الوردية: بقواعد (فرع، قسم، مسمّى…) أو بأسماء الموظفين.</small>
            </div>
        </div>
    </div>
</div>

<div class="card" id="employee" style="display: {{ $useRules == 'employees' ? 'block' : 'none' }}">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-users"></i> اختيار الموظفين</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 col-xl-8">
                <label for="employee-select-employees" class="form-label">اختر الموظفين</label>
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
            </div>
        </div>
    </div>
</div>

<div class="card" id="rules" style="display: {{ $useRules == 'rules' ? 'block' : 'none' }}">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-filter"></i> اختيار القواعد</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 col-xl-4 mb-3">
                @include('reports.partials.affiliation-tree', [
                    'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes($cs ? [$cs->branch_id] : []),
                    'name' => 'branch_id',
                    'fieldId' => 'branch',
                    'value' => old('branch_id', $cs->branch_id ?? null),
                    'showExact' => false,
                    'label' => 'تابع لـ',
                ])
                <x-create-first
                    :count="count($branches ?? [])"
                    route="branches.create"
                    label="فرعاً"
                    hint="الفروع تُدار في وحدة الفروع، ولا فرع مسجَّل بعد." />
            </div>
            <div class="col-md-6 col-xl-4 mb-3">
                <label for="department" class="form-label">القسم</label>
                <select id="department" name="department_id" class="form-control select2">
                    <option value="" disabled selected>اختر قسم</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ old('department_id', $cs->department_id ?? null) == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
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
            <div class="col-md-6 col-xl-4 mb-3">
                <label for="job_title" class="form-label">المسمى الوظيفي</label>
                <select id="job_title" name="job_title_id" class="form-control select2">
                    <option value="" disabled selected>اختر المسمى الوظيفي</option>
                    @foreach ($job_titles as $job_title)
                        <option value="{{ $job_title->id }}" {{ old('job_title_id', $cs->job_title_id ?? null) == $job_title->id ? 'selected' : '' }}>{{ $job_title->name }}</option>
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
            <div class="col-md-6 col-xl-4 mb-3">
                <label for="shift_rule_id" class="form-label">الوردية</label>
                <select name="shift_rule_id" id="shift_rule_id" class="form-control select2">
                    <option value="" disabled selected>اختر الوردية</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}" {{ old('shift_rule_id', $cs->shift_rule_id ?? null) == $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12 col-xl-8 mb-3">
                <label for="employee-select-rules" class="form-label">اختر الموظفين المستبعدين</label>
                <select id="employee-select-rules" name="excluded_employee_id[]" class="form-control select2" multiple="multiple">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, $selectedExcluded) ? 'selected' : '' }}>
                            {{ $employee->full_name }}
                        </option>
                    @endforeach
                </select>
                <small class="stk-help">من تختاره هنا لا تشمله الوردية، ولو طابق القواعد أعلاه.</small>
                @error('excluded_employee_id')
                    <small class="text-danger d-block">{{ $message }}</small>
                @enderror
            </div>
        </div>
    </div>
</div>
