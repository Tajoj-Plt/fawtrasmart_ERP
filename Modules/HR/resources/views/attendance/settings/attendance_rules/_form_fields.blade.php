{{--
    حقولُ نموذج قاعدة الحضور — مشتركةٌ بين «إضافة» و«تعديل».
    يستقبل: $rule (null عند الإضافة) و$shifts. المعرّفات (name / status /
    shift / color / colorDisplay) يقرؤها سكربت الصفحتين، فلا تُغيَّر.
--}}
@php
    $rule = $rule ?? null;
    $ruleColor = old('color', $rule ? $rule->color : '#4e5381');
    $ruleStatus = old('status', $rule ? $rule->status : 'active');

    /*
        القيمُ تُقرأ من الخدمة نفسِها التي يقرأ بها المحرّك، فما يُعرض هو ما
        يسري — لا حقلَ نصٍّ حرٍّ يُحفظ ويُهمَل.
    */
    $overrides = app(\App\Services\Attendance\AttendanceRuleOverrides::class);
    $ruleFields = \App\Services\Attendance\AttendanceRuleOverrides::fields();
    $ruleValues = $rule ? $overrides->valuesOf($rule->formula, $rule->condition) : [];
    $ruleLeftover = $rule ? $overrides->leftover($rule->formula, $rule->condition) : '';
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم تُحفظ القاعدة — راجع الحقول المعلَّمة:
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
            <!-- الاسم -->
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="name" class="form-label stk-req">الاسم</label>
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name', $rule->name ?? '') }}"
                       placeholder="أدخل الاسم"
                       required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- الوردية -->
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="shift" class="form-label">الوردية</label>
                <select class="form-control select2 @error('shift_id') is-invalid @enderror"
                        id="shift"
                        name="shift_id">
                    {{-- فارغةٌ = تسري على كلّ الورديات، وهي الوحيدةُ التي تصل موظفاً بلا ورديةٍ مُسنَدة --}}
                    <option value="">كل الورديات (وبلا وردية)</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ $shift->id }}"
                                {{ old('shift_id', $rule->shift_id ?? null) == $shift->id ? 'selected' : '' }}>
                            {{ $shift->name }}
                        </option>
                    @endforeach
                </select>
                <x-create-first
                    :count="count($shifts ?? [])"
                    route="custom_shifts.create"
                    permission="shifts.create"
                    :plan="\App\Constants\planFeatures\HrPlanFeatures::SHIFTS"
                    label="وردية"
                    hint="الورديات تُعرَّف في إعدادات الحضور، ولا وردية بعد." />
                @error('shift_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- الحالة -->
            <div class="col-md-6 col-xl-2 mb-2">
                <label for="status" class="form-label stk-req">الحالة</label>
                <select class="form-control select2 @error('status') is-invalid @enderror"
                        id="status"
                        name="status"
                        required>
                    <option value="active" {{ $ruleStatus == 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="inactive" {{ $ruleStatus == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- اللون -->
            <div class="col-md-6 col-xl-2 mb-2">
                <label for="color" class="form-label">اللون</label>
                <div class="d-flex align-items-center">
                    <input type="color"
                           class="form-control form-control-color @error('color') is-invalid @enderror"
                           id="color"
                           name="color"
                           value="{{ $ruleColor }}"
                           title="اختر اللون"
                           style="max-width: 50px; margin-left: 10px;">
                    <input type="text"
                           class="form-control"
                           id="colorDisplay"
                           value="{{ $ruleColor }}"
                           readonly>
                </div>
                @error('color')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- الوصف -->
            <div class="col-12">
                <label for="description" class="form-label">الوصف</label>
                <textarea class="form-control @error('description') is-invalid @enderror"
                          id="description"
                          name="description"
                          rows="2"
                          placeholder="أدخل الوصف">{{ old('description', $rule->description ?? '') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

{{--
    ما تضبطه القاعدة.

    كان هنا حقلا نصٍّ حرَّان («الصيغة الحسابية» و«الشرط») بزرَّي 🔍 لا
    يفعلان شيئاً، والمحرّكُ لا يقبل منهما إلّا `مفتاح=قيمة` من خمسةٍ بعينها.
    فمن كتب قاعدةً بالعربية حُفظت بنجاحٍ وأُهملت بصمت. والحقولُ الآن مولّدةٌ
    من `AttendanceRuleOverrides::fields()` — لا يُكتب إلّا ما يُقرأ.
--}}
<div class="card">
    <div class="card-header">
        <h4 class="card-title"><i class="feather icon-sliders"></i> ما تضبطه القاعدة</h4>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2" style="font-size:.82rem; line-height:1.9;">
            اترك الحقلَ فارغاً ليبقى على إعداد الحضور العام، واملأه لتَجُبّ هذه القاعدةُ ذلك الإعدادَ
            لمن تسري عليهم.
        </p>

        <div class="row">
            @foreach ($ruleFields as $key => $meta)
                <div class="col-md-6 col-xl-4 mb-2">
                    <label for="{{ $key }}" class="form-label">{{ $meta['label'] }}</label>
                    <div class="input-group">
                        <input type="number"
                               class="form-control @error($key) is-invalid @enderror"
                               id="{{ $key }}"
                               name="{{ $key }}"
                               value="{{ old($key, $ruleValues[$key] ?? '') }}"
                               min="{{ $meta['min'] }}"
                               max="{{ $meta['max'] }}"
                               step="{{ $meta['step'] }}"
                               placeholder="بلا تغيير">
                        <span class="input-group-text">{{ $meta['unit'] }}</span>
                        @error($key)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <small class="stk-help">{{ $meta['hint'] }}</small>
                </div>
            @endforeach
        </div>

        @if ($ruleLeftover)
            {{-- نصٌّ قديمٌ لا يفهمه المحرّك: يُقال صراحةً أنّه لم يكن يعمل، ولا يُحفظ بعد اليوم --}}
            <div class="alert alert-warning mb-0" role="alert">
                <strong><i class="feather icon-alert-triangle"></i> نصٌّ محفوظٌ لم يكن له أثر:</strong>
                <div class="mt-25" style="font-size:.82rem;">{{ $ruleLeftover }}</div>
                <div class="mt-25" style="font-size:.78rem;">
                    كُتب في «الصيغة» أو «الشرط» قديماً، والمحرّكُ لا يقرأ إلّا الحقولَ أعلاه.
                    اضبط ما تريده فيها، وسيُحذف هذا النصُّ عند الحفظ.
                </div>
            </div>
        @endif
    </div>
</div>
