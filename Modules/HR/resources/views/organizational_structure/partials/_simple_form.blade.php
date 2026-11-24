{{--
    نموذجُ السجلّ البسيط (الاسم، الحالة، الوصف) — للمستويات وأنواع الوظائف،
    إضافةً وتعديلاً.

      record           السجل أو null عند الإضافة
      sectionTitle     عنوان القسم في النموذج
      subject          «المستوى الوظيفي» / «نوع الوظيفة» — لرسالة الأخطاء
      namePlaceholder  مثالٌ في حقل الاسم
      nameTip          شرحٌ على حقل الاسم (اختياري)
      settingsGroup    مجموعةُ الإعدادات في `HrSettings` (`org_job_level_rules` /
                       `org_job_type_rules`) — منها إلزاميّةُ الوصف
--}}
@php
    $rec = $record ?? null;
    /* إلزاميّةُ الوصف والحالةُ المختارةُ سلفاً من شاشة «إعدادات الموظفين
       والهيكل التنظيمي» — والقاعدةُ نفسُها في متحكّم كلّ شاشة.
       بلا `settingsGroup` يبقى الوصفُ اختياريّاً كما كان. */
    $descRequired = isset($settingsGroup) && is_string($settingsGroup)
        && \App\Support\HrSettings::flag($settingsGroup, 'require_description');
    $defaultStatus = \App\Support\HrSettings::flag('org_defaults', 'new_node_active', true) ? 0 : 1;
    $statusValue = (string) old('status', $rec->status ?? $defaultStatus);
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم يُحفظ {{ $subject }} — راجع الحقول المعلَّمة:
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
                <h6 class="stk-section__title">{{ $sectionTitle }}</h6>
            </div>

            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="name" class="stk-req">
                            الاسم
                            @if (! empty($nameTip))
                                <i class="feather icon-help-circle stk-tip" title="{{ $nameTip }}"></i>
                            @endif
                        </label>
                        <input type="text" id="name" name="name" required maxlength="255"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $rec->name ?? '') }}" placeholder="{{ $namePlaceholder }}">
                        @error('name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label for="status" class="stk-req">
                            الحالة
                            <i class="feather icon-help-circle stk-tip" title="غيرُ النشط يبقى على من يحملونه ويُعلَّم «غير نشط». التعطيلُ هو البديلُ الآمن للحذف."></i>
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
                        <label for="description" class="{{ $descRequired ? 'stk-req' : '' }}">الوصف</label>
                        <textarea id="description" name="description" rows="3" maxlength="255"
                                  @required($descRequired)
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="متى يُستعمَل ولمن">{{ old('description', $rec->description ?? '') }}</textarea>
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
