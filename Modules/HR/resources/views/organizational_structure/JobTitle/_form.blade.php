{{--
    نموذجُ المسمّى الوظيفي — مشتركٌ بين «إضافة» و«تعديل».

    يستقبل: $jobTitle (null عند الإضافة)، $departments.
    (اسمُ المتغيّر ليس `$title`: `hr::components.guide` وغيرُها تقرأ `$title` نصّاً.)
--}}
@php
    $jt = $jobTitle ?? null;
    /* إلزاميّةُ القسم والوصف، والحالةُ المختارةُ سلفاً — من شاشة «إعدادات
       الموظفين والهيكل التنظيمي»، والقاعدةُ نفسُها في `JobTitleController`. */
    $orgReq = fn (string $key) => \App\Support\HrSettings::flag('org_job_title_rules', $key);
    $defaultStatus = \App\Support\HrSettings::flag('org_defaults', 'new_node_active', true) ? 0 : 1;
    $statusValue = (string) old('status', $jt->status ?? $defaultStatus);
    $departmentValue = (string) old('department_id', $jt->department_id ?? '');
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم يُحفظ المسمى الوظيفي — راجع الحقول المعلَّمة:
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
                <h6 class="stk-section__title">بيانات المسمى الوظيفي</h6>
            </div>

            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="name" class="stk-req">اسم المسمى</label>
                        <input type="text" id="name" name="name" required maxlength="255"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $jt->name ?? '') }}" placeholder="مثال: محاسب أول">
                        @error('name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="department_id" class="{{ $orgReq('require_department') ? 'stk-req' : '' }}">
                            القسم
                            <i class="feather icon-help-circle stk-tip" title="كلُّ مسمّى يتبع قسماً واحداً. تغييرُه ينقل حاملي المسمّى في التقارير المبنيّة على الأقسام — ولا يغيّر حقلَ «القسم» في ملفّاتهم."></i>
                        </label>
                        <select id="department_id" name="department_id" @required($orgReq('require_department'))
                                class="form-control hr-s2 @error('department_id') is-invalid @enderror">
                            <option value="">اختر القسم</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" {{ $departmentValue === (string) $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}{{ isset($department->status) && (int) $department->status !== 0 ? ' (غير نشط)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <x-create-first
                            :count="count($departments ?? [])"
                            route="department.create"
                            permission="departments.create"
                            :plan="\App\Constants\planFeatures\HrPlanFeatures::DEPARTMENTS"
                            label="قسماً"
                            hint="الأقسام جزءٌ من الهيكل التنظيمي، ولا قسم مسجَّل بعد." />
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="status" class="stk-req">
                            الحالة
                            <i class="feather icon-help-circle stk-tip" title="المسمّى غير النشط يبقى على من يحملونه ويُعلَّم «غير نشط». التعطيلُ هو البديلُ الآمن للحذف."></i>
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
                        <textarea id="description" name="description" rows="3" maxlength="255"
                                  @required($orgReq('require_description'))
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="مهامّ المسمى ومسؤولياته">{{ old('description', $jt->description ?? '') }}</textarea>
                        <span class="stk-help">حتى 255 حرفاً.</span>
                        @error('description')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
