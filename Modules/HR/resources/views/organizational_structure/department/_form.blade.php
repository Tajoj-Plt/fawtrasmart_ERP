{{--
    نموذجُ القسم — مشتركٌ بين «إضافة» و«تعديل».

    يستقبل: $department (null عند الإضافة)، $employees (منتقي المديرين من نطاق
    المستخدم — المتحكّم يقيّده)، $selectedEmployees (عند التعديل).
    كلُّ حقلٍ يقرأ `old()` أوّلاً فلا يضيع شيءٌ عند رفض التحقّق.
--}}
@php
    $dept = $department ?? null;
    /* إلزاميّةُ الاختصار والوصف والمدير، والحالةُ المختارةُ سلفاً — من شاشة
       «إعدادات الموظفين والهيكل التنظيمي». والنجمةُ والسمةُ `required` تتبعان
       القاعدةَ نفسَها التي يفحصها `DepartmentController::rules()`، فلا يُلزَم
       حقلٌ في الخادم بلا علامةٍ عليه هنا. */
    $orgReq = fn (string $key) => \App\Support\HrSettings::flag('org_department_rules', $key);
    $defaultStatus = \App\Support\HrSettings::flag('org_defaults', 'new_node_active', true) ? 0 : 1;
    $statusValue = (string) old('status', $dept->status ?? $defaultStatus);
    $managerIds = array_map('intval', (array) old('employee_id', $selectedEmployees ?? []));
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم يُحفظ القسم — راجع الحقول المعلَّمة:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="stk-section">
            <div class="stk-section__head">
                <i class="feather icon-info"></i>
                <h6 class="stk-section__title">بيانات القسم</h6>
            </div>

            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="name" class="stk-req">اسم القسم</label>
                        <input type="text" id="name" name="name" required maxlength="255"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $dept->name ?? '') }}" placeholder="مثال: المبيعات">
                        @error('name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="short_name" class="{{ $orgReq('require_short_name') ? 'stk-req' : '' }}">
                            الاختصار
                            <i class="feather icon-help-circle stk-tip" title="رمزٌ قصير يظهر تحت اسم القسم في القوائم ويُصدَّر معه — مثل «MKT» أو «مبيعات»"></i>
                        </label>
                        <input type="text" id="short_name" name="short_name" maxlength="50"
                               @required($orgReq('require_short_name'))
                               class="form-control @error('short_name') is-invalid @enderror"
                               value="{{ old('short_name', $dept->short_name ?? '') }}"
                               placeholder="{{ $orgReq('require_short_name') ? 'مطلوب' : 'اختياري' }}">
                        @error('short_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="status" class="stk-req">
                            الحالة
                            <i class="feather icon-help-circle stk-tip" title="القسم غير النشط يبقى بموظفيه وسجلّاته، ويُعلَّم «غير نشط» في القوائم. التعطيلُ هو البديلُ الآمن للحذف."></i>
                        </label>
                        <select id="status" name="status" required class="form-control hr-s2 @error('status') is-invalid @enderror">
                            <option value="0" {{ $statusValue === '0' ? 'selected' : '' }}>نشط</option>
                            <option value="1" {{ $statusValue === '1' ? 'selected' : '' }}>غير نشط</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group mb-0">
                        <label for="description" class="{{ $orgReq('require_description') ? 'stk-req' : '' }}">الوصف</label>
                        <textarea id="description" name="description" rows="3"
                                  @required($orgReq('require_description'))
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="مهامّ القسم ونطاق عمله">{{ old('description', $dept->description ?? '') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="stk-section">
            <div class="stk-section__head">
                <i class="feather icon-user-check"></i>
                <h6 class="stk-section__title">إدارة القسم</h6>
            </div>

            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="form-group mb-0">
                        <label for="employee_id" class="{{ $orgReq('require_manager') ? 'stk-req' : '' }}">
                            المديرون المعنيّون
                            <i class="feather icon-help-circle stk-tip" title="من يُسنَد إليهم القسم إدارياً. قائمةٌ مستقلّة عن موظفي القسم: الموظفُ ينتسب إلى القسم من ملفّه (حقل «القسم»)، لا من هنا."></i>
                        </label>
                        <select id="employee_id" name="employee_id[]" multiple
                                class="form-control hr-s2 @error('employee_id') is-invalid @enderror"
                                data-placeholder="اختر مديراً أو أكثر">
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ in_array((int) $employee->id, $managerIds, true) ? 'selected' : '' }}>
                                    {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="stk-help">القائمة محصورةٌ في موظفي نطاقك. ومن كان مديراً خارج نطاقك يبقى كما هو.</span>
                        @error('employee_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
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
    </div>
</div>
