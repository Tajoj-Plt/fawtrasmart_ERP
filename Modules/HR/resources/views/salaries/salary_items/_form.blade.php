{{--
    نموذجُ بند الراتب — مشتركٌ بين «إضافة» و«تعديل».

    يستقبل: $salaryItem (null عند الإضافة)، $accounts، $usage (اختياري).
    كلُّ حقلٍ يقرأ `old()` أوّلاً فلا يضيع شيءٌ عند رفض التحقّق، ويُعلّمه
    `hr::components.form-ux` باسمه.
--}}
@php
    $item = $salaryItem ?? null;
    $valueMode = (string) old('salary_item_value', $item->salary_item_value ?? \App\Models\SalaryItem::VALUE_TYPE_AMOUNT);
    $typeValue = (string) old('type', $item->type ?? '');
    $statusValue = (string) old('status', $item->status ?? \App\Models\SalaryItem::STATUS_ACTIVE);
    // صندوقُ الاختيار غير المؤشَّر لا يُرسَل، فوجودُ أيّ مدخلٍ قديم يعني أن النموذج أُرسل
    $submitted = session()->hasOldInput();
    $insurable = $submitted ? (bool) old('is_insurable') : (bool) ($item->is_insurable ?? false);
    $inEos = $submitted ? (bool) old('in_eos_wage') : (bool) ($item->in_eos_wage ?? false);
    $referenceOnly = $submitted ? (bool) old('reference_value') : (bool) ($item->reference_value ?? false);
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم يُحفظ البند — راجع الحقول المعلَّمة:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    {{-- ══════════ العمود الرئيسي ══════════ --}}
    <div class="col-lg-8 col-12">
        <div class="card">
            <div class="card-body">
                <div class="stk-section">
                    <div class="stk-section__head">
                        <i class="feather icon-info"></i>
                        <h6 class="stk-section__title">البيانات الأساسية</h6>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">اسم البند</label>
                                <input type="text" id="name" name="name" required maxlength="255"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $item->name ?? '') }}" placeholder="مثال: بدل سكن">
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="salary_item_type">
                                    النوع
                                    <i class="feather icon-help-circle stk-tip" title="المستحق يزيد صافي الراتب، والمستقطع ينقصه. البند (كالبدل) يتكرّر في كل راتب؛ أمّا مكافآت «كتالوج المكافآت» فتُضاف تلقائياً لمرّةٍ واحدة عند اعتمادها — لا تعرّفها هنا."></i>
                                </label>
                                <select name="type" id="salary_item_type" required
                                        class="form-control hr-s2 @error('type') is-invalid @enderror">
                                    <option value="">اختر النوع</option>
                                    <option value="1" {{ $typeValue === '1' ? 'selected' : '' }}>مستحق</option>
                                    <option value="2" {{ $typeValue === '2' ? 'selected' : '' }}>مستقطع</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" required
                                        class="form-control hr-s2 @error('status') is-invalid @enderror">
                                    <option value="1" {{ $statusValue === '1' ? 'selected' : '' }}>نشط</option>
                                    <option value="2" {{ $statusValue === '2' ? 'selected' : '' }}>غير نشط</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="description">الوصف</label>
                                <textarea name="description" id="description" rows="2" maxlength="2000"
                                          class="form-control @error('description') is-invalid @enderror"
                                          placeholder="ما هذا البند ومتى يُصرف أو يُستقطع">{{ old('description', $item->description ?? '') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-section">
                    <div class="stk-section__head">
                        <i class="feather icon-hash"></i>
                        <h6 class="stk-section__title">القيمة</h6>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-radio mb-50">
                                    <input id="amount_radio" name="salary_item_value" value="1" type="radio"
                                           class="custom-control-input" onchange="toggleFields()"
                                           {{ $valueMode === '1' ? 'checked' : '' }}>
                                    <label for="amount_radio" class="custom-control-label">مبلغ ثابت</label>
                                </div>
                                <label for="amount" class="sr-only">المبلغ</label>
                                <input type="number" step="0.01" min="0" id="amount" name="amount"
                                       class="form-control @error('amount') is-invalid @enderror"
                                       value="{{ old('amount', $item->amount ?? '') }}" placeholder="0.00">
                                <small class="stk-help">المبلغ الافتراضي؛ يمكن تغييره لكل موظف في عقده.</small>
                                @error('amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-radio mb-50">
                                    <input id="formula_radio" name="salary_item_value" value="2" type="radio"
                                           class="custom-control-input" onchange="toggleFields()"
                                           {{ $valueMode === '2' ? 'checked' : '' }}>
                                    <label for="formula_radio" class="custom-control-label">
                                        صيغة حسابية
                                        <i class="feather icon-help-circle stk-tip" title="اكتب @ لإظهار المتغيرات المتاحة: basic، total_allowances، absence_days، day_rate…"></i>
                                    </label>
                                </div>
                                <label for="calculation_formula" class="sr-only">الصيغة الحسابية</label>
                                <input type="text" id="calculation_formula" name="calculation_formula" dir="ltr" maxlength="255"
                                       class="form-control formula-input @error('calculation_formula') is-invalid @enderror"
                                       value="{{ old('calculation_formula', $item->calculation_formula ?? '') }}"
                                       placeholder="basic * 0.25">
                                @error('calculation_formula')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-md-0">
                                <label for="condition">
                                    الشرط
                                    <i class="feather icon-help-circle stk-tip" title="وصفٌ نصّي لشرط استحقاق البند — يظهر للمراجعة ولا يُحتسب آلياً"></i>
                                </label>
                                <input type="text" id="condition" name="condition" maxlength="1000"
                                       class="form-control @error('condition') is-invalid @enderror"
                                       value="{{ old('condition', $item->condition ?? '') }}" placeholder="مثال: بعد إتمام فترة التجربة">
                                @error('condition')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <label class="stk-switch stk-switch--sm mb-0">
                                <input type="checkbox" name="reference_value" value="1" {{ $referenceOnly ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">قيمة مرجعية فقط</span>
                                    <span class="stk-switch__hint">تُعرض للمقارنة ولا تُفرض على العقد</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ العمود الجانبي ══════════ --}}
    <div class="col-lg-4 col-12">
        <div class="card">
            <div class="card-body">
                <div class="stk-section">
                    <div class="stk-section__head">
                        <i class="feather icon-shield"></i>
                        <h6 class="stk-section__title">الخضوع والاحتساب</h6>
                    </div>

                    <div id="addition-flags">
                        <label class="stk-switch mb-2">
                            <input type="checkbox" name="is_insurable" value="1" {{ $insurable ? 'checked' : '' }}>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">خاضع للتأمينات الاجتماعية</span>
                                <span class="stk-switch__hint">يدخل مبلغُه الأجرَ الخاضعَ للاشتراك (مع الأساسي، وحتى السقف)</span>
                            </span>
                        </label>

                        <label class="stk-switch mb-0">
                            <input type="checkbox" name="in_eos_wage" value="1" {{ $inEos ? 'checked' : '' }}>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">يدخل أجر مكافأة نهاية الخدمة</span>
                                <span class="stk-switch__hint">حين يُضبط احتساب المكافأة على «الأساسي + البدلات المعتادة»</span>
                            </span>
                        </label>
                    </div>
                    <p class="stk-help mb-0 d-none" id="deduction-flags-note">
                        الاستقطاعُ لا يدخل الأجرَ الخاضع للتأمينات ولا أجرَ نهاية الخدمة.
                    </p>
                </div>

                <div class="stk-section">
                    <div class="stk-section__head">
                        <i class="feather icon-briefcase"></i>
                        <h6 class="stk-section__title">الحساب المحاسبي</h6>
                    </div>
                    <div class="form-group mb-0">
                        <label for="account_id">
                            الحساب
                            <i class="feather icon-help-circle stk-tip" title="للبند المستقطَع: يُقيَّد الخصمُ لحساب هذا البند عند الصرف (فارغٌ = حساب الخصميات العام). أمّا بنودُ الاستحقاق (البدلات) فتُحمَّل كلُّها على «مصروف رواتب الموظفين» المربوط في إعدادات الحسابات المحاسبية — ولا يُستعمل هذا الحقل لها؛ والمكافآتُ المستبدَلة على «حساب مصروف المكافآت»."></i>
                        </label>
                        <select name="account_id" id="account_id" class="form-control select2 @error('account_id') is-invalid @enderror">
                            <option value="">الحساب الافتراضي</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('account_id', $item->account_id ?? '') === (string) $account->id ? 'selected' : '' }}>
                                    {{ $account->code }} - {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('account_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <x-create-first
                            :count="count($accounts ?? [])"
                            route="accounts_chart.view_root"
                            permission="account.chart.view"
                            :plan="\App\Constants\planFeatures\AccountPlanFeatures::CHART_OF_ACCOUNTS"
                            label="حساباً"
                            hint="الحساب يُنشأ من دليل الحسابات، ولا حساب متاح بعد." />
                    </div>
                </div>

                @if (! empty($usage) && ($usage['contracts'] || $usage['slips']))
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-link"></i>
                            <h6 class="stk-section__title">أين يُستعمل</h6>
                        </div>
                        <dl class="stk-def stk-def--compact">
                            <div class="stk-def__row"><dt>عقود</dt><dd class="stk-num">{{ $usage['contracts'] }}</dd></div>
                            <div class="stk-def__row"><dt>قسائم</dt><dd class="stk-num">{{ $usage['slips'] }}</dd></div>
                        </dl>
                        <p class="stk-help mb-0">التعديلُ يسري على النسخ الجديدة؛ النسخُ القائمة في العقود والقسائم لا تتغيّر.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
