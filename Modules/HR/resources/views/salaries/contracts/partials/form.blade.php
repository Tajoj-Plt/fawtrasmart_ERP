{{--
    نموذج العقد — مشتركٌ بين الإنشاء والتعديل.

    المتغيّرات: $contract (جديد أو قائم) · $employees · $jopTitle · $functionalLevels
    · $additionItems · $deductionItems · $currencyCode
    · $parentContracts · $contractAdditions · $contractDeductions · $nextNumber (إنشاء)

    **لا يضيع شيءٌ عند الإخفاق**: كلُّ حقلٍ يقرأ `old('x', $contract->x)`،
    وصفوفُ البنود تُعاد من `old()` صفّاً صفّاً إن وُجدت، وإلا من بنود العقد.
--}}
@php
    $isEdit = $contract->exists;
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';

    // عقودٌ قديمة بلا مدّةٍ محفوظة (نوعها فارغ أو «مدة» بلا رقم) تُعرض «تاريخ انتهاء» كي لا يُمحى تاريخُها
    $storedType = $contract->exists && ! $contract->duration ? \App\Models\Contract::TYPE_END_DATE : ($contract->type_contract ?: \App\Models\Contract::TYPE_PERIOD);
    $typeContract = (int) old('type_contract', $storedType);
    $employeeId = old('employee_id', $contract->employee_id);

    /*
        صفوفُ البنود: من `old()` بعد إخفاق التحقّق، وإلا من العقد.
        بنودُ العقد نُسخٌ تحمل اسمَ البند لا معرّفَه، فتُطابَق مع الكتالوج
        بالاسم — وما لا يطابق يبقى بمعرّف نسخته (يقبله الخادم لهذا العقد).
    */
    $buildRows = function (string $kind, $catalog, $existing) {
        if (is_array(old($kind.'_type'))) {
            $ids = old($kind.'_type', []);
            $names = \App\Models\SalaryItem::whereIn('id', array_filter($ids, 'is_numeric'))->pluck('name', 'id');
            $rows = [];
            foreach ($ids as $i => $id) {
                $rows[] = [
                    'selected' => $id,
                    'selectedName' => $names[$id] ?? null,
                    'formula' => old($kind.'_calculation_formula.'.$i),
                    'amount' => old($kind.'_amount.'.$i),
                ];
            }

            return $rows;
        }

        return collect($existing)->map(function ($item) use ($catalog) {
            $match = $catalog->firstWhere('name', $item->name);

            return [
                'selected' => $match->id ?? $item->id,
                'selectedName' => $item->name,
                'formula' => $item->calculation_formula,
                'amount' => $item->amount !== null ? number_format((float) $item->amount, 2, '.', '') : null,
            ];
        })->all();
    };

    $additionRows = $buildRows('addition', $additionItems, $contractAdditions ?? collect());
    $deductionRows = $buildRows('deduction', $deductionItems, $contractDeductions ?? collect());

    $basicValue = old('amount', $contract->amount !== null ? number_format((float) $contract->amount, 2, '.', '') : '');
@endphp

@include('hr::components.guide', [
    'key' => 'hr-contracts.form',
    'title' => 'كيف يُحسب العقد، وما الذي يُملأ تلقائياً؟',
    'lead' => 'اختر الموظف أولاً: يُملأ المسمّى الوظيفي والمستوى وتاريخ الالتحاق من ملفّه، وتظهر عقودُه السابقة في «العقد الأساسي». ثم حدّد المدّة فيُحسب تاريخ الانتهاء، ثم أدخل الراتب.',
    'blocks' => [
        'مبلغ العقد' => [
            'مبلغ العقد = الراتب الأساسي + مجموع بنود المستحقات. يكفي أحدُهما: من أدخل «راتب أساسي» بنداً في المستحقات يترك حقل الأساسي فارغاً.',
            'صافي الراتب = مبلغ العقد − مجموع المستقطعات.',
            'الصيغة الحسابية تُحسب على الراتب الأساسي، مثل basic * 0.25 لبدل سكنٍ ربعَ الأساسي.',
        ],
        'ما يترتّب على الحفظ' => [
            'يُصرَف للعقد رقمٌ من «ترقيم المستندات».',
            'ومسيرُ الرواتب يقرأ الأساسيَّ وبنودَ العقد لكل قسيمة تُصدَر للموظف ما دام العقد سارياً.',
        ],
    ],
    'facts' => [
        'العملة من إعدادات النظام: '.$currencyCode,
        'تاريخ الانتهاء يُحسب من البداية والمدة',
    ],
])

<div class="row">
    <div class="col-12">

        {{-- ══════════ بيانات العقد ══════════ --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-file-text"></i> بيانات العقد</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="employee_id" class="stk-req">الموظف</label>
                            <i class="feather icon-help-circle stk-tip" title="الطرف الثاني في العقد. عند اختياره يُملأ المسمّى الوظيفي والمستوى وتاريخ الالتحاق من ملفّه، وتظهر عقودُه السابقة في «العقد الأساسي». القائمة محصورةٌ في الموظفين الذين يحقّ لك إدارتهم."></i>
                            <select name="employee_id" id="employee_id" class="form-control ct-select2" required data-placeholder="اختر الموظف">
                                <option value="">اختر الموظف</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ (string) $employeeId === (string) $employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }}{{ $employee->employee_code ? ' — '.$employee->employee_code : '' }}
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
                            <small class="stk-help" id="ct-employee-info" aria-live="polite"></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="feedback2">رقم العقد</label>
                            <i class="feather icon-help-circle stk-tip" title="يُصرَف تلقائياً من «ترقيم المستندات ← العقود» عند الحفظ، ولا يُكتب يدوياً."></i>
                            <input type="text" id="feedback2" class="form-control stk-locked" value="{{ $isEdit ? $contract->code : ($nextNumber ?? '') }}" readonly disabled>
                            <small class="stk-help stk-help--locked"><i class="feather icon-lock"></i>{{ $isEdit ? 'رقم العقد لا يتغيّر' : 'رقم تسلسلي تلقائي — يُحجز عند الحفظ' }}</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="job_title_id" class="stk-req">المسمى الوظيفي</label>
                            <i class="feather icon-help-circle stk-tip" title="يُملأ من ملف الموظف عند اختياره، ويمكن تغييره إن كان العقد لوظيفةٍ مختلفة."></i>
                            <select name="job_title_id" id="job_title_id" class="form-control ct-select2" required data-placeholder="اختر المسمى الوظيفي">
                                <option value="">اختر المسمى الوظيفي</option>
                                @foreach ($jopTitle as $jop)
                                    <option value="{{ $jop->id }}" {{ (string) old('job_title_id', $contract->job_title_id) === (string) $jop->id ? 'selected' : '' }}>{{ $jop->name }}</option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($jopTitle ?? [])"
                                route="JobTitles.create"
                                permission="job_titles.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::JOB_TITLES"
                                label="مسمّى وظيفياً"
                                hint="المسمّيات تُعرَّف في الهيكل التنظيمي، ولا مسمّى بعد." />
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="job_level_id" class="stk-req">المستوى الوظيفي</label>
                            <i class="feather icon-help-circle stk-tip" title="الدرجة أو المستوى الوظيفي للموظف — يُملأ من ملفه عند اختياره."></i>
                            <select name="job_level_id" id="job_level_id" class="form-control ct-select2" required data-placeholder="اختر المستوى الوظيفي">
                                <option value="">اختر المستوى الوظيفي</option>
                                @foreach ($functionalLevels as $functionalLevel)
                                    <option value="{{ $functionalLevel->id }}" {{ (string) old('job_level_id', $contract->job_level_id) === (string) $functionalLevel->id ? 'selected' : '' }}>{{ $functionalLevel->name }}</option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($functionalLevels ?? [])"
                                route="ManagingFunctionalLevels.create"
                                permission="job_levels.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::FUNCTIONAL_LEVELS"
                                label="مستوى وظيفياً"
                                hint="المستويات تُعرَّف في الهيكل التنظيمي، ولا مستوى بعد." />
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="parent_contract_id">العقد الأساسي</label>
                            <i class="feather icon-help-circle stk-tip" title="اختياري: إن كان هذا العقد تجديداً أو ملحقاً لعقدٍ سابقٍ للموظف نفسه فاختره هنا. تظهر عقود الموظف المختار وحده."></i>
                            @php $parentId = old('parent_contract_id', $contract->parent_contract_id); @endphp
                            <select name="parent_contract_id" id="parent_contract_id" class="form-control ct-select2" data-placeholder="بدون — عقد مستقل" data-selected="{{ $parentId }}">
                                <option value="">بدون — عقد مستقل</option>
                                @foreach ($parentContracts as $pc)
                                    <option value="{{ $pc->id }}" {{ (string) $parentId === (string) $pc->id ? 'selected' : '' }}>
                                        {{ ($pc->code ?: '#'.$pc->id).' — '.optional($pc->start_date)->format('Y-m-d') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <i class="feather icon-help-circle stk-tip" title="اختياري: ملاحظةٌ أو شرطٌ خاصّ بالعقد — يُطبع ضمن بنود العقد في المطبوعة."></i>
                            <textarea name="description" id="description" rows="2" maxlength="255" class="form-control" placeholder="شروط خاصة أو ملاحظات">{{ old('description', $contract->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ المدة والتواريخ ══════════ --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-calendar"></i> المدة والتواريخ</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="start_date" class="stk-req">تاريخ بداية العقد</label>
                            <i class="feather icon-help-circle stk-tip" title="اليوم الأول لسريان العقد. منه تُحسب نهاية العقد عند تحديد المدة."></i>
                            <input type="date" name="start_date" id="start_date" class="form-control" required value="{{ old('start_date', $d($contract->start_date)) }}">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="form-group mb-1">
                            <label class="stk-req d-block">نوع المدة</label>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input id="duration_radio" name="type_contract" class="custom-control-input" type="radio" value="1" {{ $typeContract === 1 ? 'checked' : '' }}>
                                <label for="duration_radio" class="custom-control-label">مدة محددة</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input id="enddate_radio" name="type_contract" class="custom-control-input" type="radio" value="2" {{ $typeContract === 2 ? 'checked' : '' }}>
                                <label for="enddate_radio" class="custom-control-label">تاريخ انتهاء محدد</label>
                            </div>
                            <i class="feather icon-help-circle stk-tip" title="«مدة محددة»: أدخل عدد الأيام أو الأشهر أو السنوات، ويُحسب تاريخ الانتهاء تلقائياً. «تاريخ انتهاء محدد»: اكتب تاريخ الانتهاء بنفسك، أو اتركه فارغاً لعقدٍ غير محدد المدة."></i>
                        </div>

                        <div class="row">
                            <div class="col-md-4" id="duration-inputs">
                                <div class="form-group">
                                    <label for="duration">المدة</label>
                                    <input class="form-control" id="duration" type="number" min="1" step="1" name="duration" value="{{ old('duration', $contract->duration) }}" placeholder="مثال: 1">
                                </div>
                            </div>
                            <div class="col-md-4" id="duration-unit-wrap">
                                <div class="form-group">
                                    <label for="duration_unit">وحدة المدة</label>
                                    @php $unit = (string) old('duration_unit', $contract->duration_unit); @endphp
                                    <select class="form-control hr-s2" id="duration_unit" name="duration_unit">
                                        <option value="1" {{ $unit === '1' ? 'selected' : '' }}>يوم</option>
                                        <option value="2" {{ $unit === '2' ? 'selected' : '' }}>شهر</option>
                                        <option value="3" {{ $unit === '3' ? 'selected' : '' }}>سنة</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="end_date">تاريخ الانتهاء</label>
                                    <i class="feather icon-help-circle stk-tip" title="في «مدة محددة» يُحسب من تاريخ البداية والمدة (شاملاً يوم البداية) فراجِعه قبل الحفظ. وفي «تاريخ انتهاء محدد» يُكتب يدوياً."></i>
                                    <input type="date" id="end_date" class="form-control" name="end_date" value="{{ old('end_date', $d($contract->end_date)) }}">
                                </div>
                            </div>
                        </div>
                        <small class="stk-help" id="ct-end-preview" aria-live="polite"></small>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="join_date" class="stk-req">تاريخ الالتحاق</label>
                            <i class="feather icon-help-circle stk-tip" title="تاريخ مباشرة الموظف للعمل — يُملأ من «تاريخ التعيين» في ملف الموظف. عليه تُبنى مدة الخدمة ومكافأة نهاية الخدمة."></i>
                            <input type="date" id="join_date" class="form-control" name="join_date" required value="{{ old('join_date', $d($contract->join_date)) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="probation_end_date" class="stk-req">نهاية فترة التجربة</label>
                            <i class="feather icon-help-circle stk-tip" title="آخر يوم في فترة التجربة. إن تُرك فارغاً يُقترح 90 يوماً من تاريخ الالتحاق (نظام العمل)."></i>
                            <input type="date" id="probation_end_date" class="form-control" name="probation_end_date" required value="{{ old('probation_end_date', $d($contract->probation_end_date)) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="contract_date" class="stk-req">تاريخ توقيع العقد</label>
                            <i class="feather icon-help-circle stk-tip" title="تاريخ توقيع الطرفين على العقد — يُطبع في المطبوعة."></i>
                            <input type="date" id="contract_date" class="form-control" name="contract_date" required value="{{ old('contract_date', $d($contract->contract_date) ?: ($isEdit ? '' : now()->format('Y-m-d'))) }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ بيانات الراتب ══════════ --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-dollar-sign"></i> بيانات الراتب</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="receiving_cycle">دورة القبض</label>
                            <i class="feather icon-help-circle stk-tip" title="كم مرة يُصرف الراتب: شهري هو المعتاد."></i>
                            @php $cycle = (string) old('receiving_cycle', $contract->receiving_cycle ?: 1); @endphp
                            <select name="receiving_cycle" id="receiving_cycle" class="form-control hr-s2">
                                <option value="1" {{ $cycle === '1' ? 'selected' : '' }}>شهري</option>
                                <option value="2" {{ $cycle === '2' ? 'selected' : '' }}>أسبوعي</option>
                                <option value="5" {{ $cycle === '5' ? 'selected' : '' }}>مرة كل أسبوعين</option>
                                <option value="4" {{ $cycle === '4' ? 'selected' : '' }}>ربع سنوي</option>
                                <option value="3" {{ $cycle === '3' ? 'selected' : '' }}>سنوي</option>
                            </select>
                        </div>
                    </div>

                    {{-- «قالب الرواتب» أُلغي (2026-09-24): البنودُ تُضاف مباشرةً في جدولَي المستحقات والمستقطعات --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="ct-currency">العملة</label>
                            <i class="feather icon-help-circle stk-tip" title="عملة العقد هي عملة النظام المضبوطة في الإعدادات ← العملات، ولا تُختار لكل عقد."></i>
                            <input type="text" id="ct-currency" class="form-control stk-locked" value="{{ $currencyCode }} — {{ \App\Support\CurrencySettings::name() }}" readonly disabled>
                            <small class="stk-help stk-help--locked"><i class="feather icon-lock"></i>من إعدادات النظام</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="basic_amount">الراتب الأساسي</label>
                            <i class="feather icon-help-circle stk-tip" title="الأجر الأساسي الشهري (basic). عليه تُحسب الصيغ مثل basic * 0.25. اتركه فارغاً إن أدخلت «راتب أساسي» بنداً في المستحقات — المهمّ أن يكون مبلغ العقد أكبر من صفر."></i>
                            <input type="text" id="basic_amount" name="amount" class="form-control" dir="ltr" inputmode="decimal" value="{{ $basicValue }}" placeholder="0.00">
                        </div>
                    </div>
                </div>

                {{-- مستحق --}}
                <div class="stk-section">
                    <div class="stk-section__head">
                        <i class="feather icon-plus-circle"></i>
                        <h6 class="stk-section__title">المستحقات (البدلات)</h6>
                        <i class="feather icon-help-circle stk-tip" title="بنود تُضاف إلى الراتب: بدل سكن، بدل نقل… اختر البند فيُملأ مبلغه أو صيغته من إعداد البند، ثم عدّل المبلغ إن لزم. مجموعها يدخل في مبلغ العقد."></i>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-2" id="additions-table">
                            <thead>
                                <tr>
                                    <th style="width:40px"></th>
                                    <th>بند الراتب</th>
                                    <th style="width:28%">الصيغة الحسابية</th>
                                    <th style="width:20%">المبلغ</th>
                                    <th style="width:56px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($additionRows as $row)
                                    @include('hr::salaries.contracts.partials.item-row', ['kind' => 'addition', 'items' => $additionItems] + $row)
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <template id="tpl-addition-row">
                        @include('hr::salaries.contracts.partials.item-row', ['kind' => 'addition', 'items' => $additionItems, 'selected' => null, 'selectedName' => null, 'formula' => null, 'amount' => null])
                    </template>
                    <button type="button" class="btn btn-outline-primary btn-sm add-row-button-addition"><i class="feather icon-plus"></i> إضافة بند مستحق</button>
                    <x-create-first
                        :count="count($additionItems ?? [])"
                        route="SalaryItems.create"
                        permission="salary_items.create"
                        :plan="\App\Constants\planFeatures\HrPlanFeatures::SALARY_ITEMS"
                        label="بند استحقاق"
                        hint="بنود الراتب تُعرَّف في إعدادات الرواتب، ولا بند بعد." />
                </div>

                {{-- مستقطع --}}
                <div class="stk-section">
                    <div class="stk-section__head">
                        <i class="feather icon-minus-circle"></i>
                        <h6 class="stk-section__title">المستقطعات</h6>
                        <i class="feather icon-help-circle stk-tip" title="بنود تُخصم من الراتب بموجب العقد (مثل حصة الموظف في التأمينات إن كانت ثابتة). تُطرح من مبلغ العقد لحساب الصافي."></i>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-2" id="deductions-table">
                            <thead>
                                <tr>
                                    <th style="width:40px"></th>
                                    <th>بند الراتب</th>
                                    <th style="width:28%">الصيغة الحسابية</th>
                                    <th style="width:20%">المبلغ</th>
                                    <th style="width:56px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($deductionRows as $row)
                                    @include('hr::salaries.contracts.partials.item-row', ['kind' => 'deduction', 'items' => $deductionItems] + $row)
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <template id="tpl-deduction-row">
                        @include('hr::salaries.contracts.partials.item-row', ['kind' => 'deduction', 'items' => $deductionItems, 'selected' => null, 'selectedName' => null, 'formula' => null, 'amount' => null])
                    </template>
                    <button type="button" class="btn btn-outline-primary btn-sm add-row-button-deduction"><i class="feather icon-plus"></i> إضافة بند مستقطع</button>
                    <x-create-first
                        :count="count($deductionItems ?? [])"
                        route="SalaryItems.create"
                        permission="salary_items.create"
                        :plan="\App\Constants\planFeatures\HrPlanFeatures::SALARY_ITEMS"
                        label="بند استقطاع"
                        hint="بنود الراتب تُعرَّف في إعدادات الرواتب، ولا بند بعد." />
                </div>

                {{-- الإجماليات — تُحسب في الصفحة وتُعاد على الخادم عند الحفظ --}}
                <div class="stk-section mb-0">
                    <div class="stk-section__head">
                        <i class="feather icon-bar-chart-2"></i>
                        <h6 class="stk-section__title">ملخص الراتب</h6>
                        <i class="feather icon-help-circle stk-tip" title="يُحسب تلقائياً: مبلغ العقد = الأساسي + المستحقات، والصافي = مبلغ العقد − المستقطعات. يُعاد حسابه على الخادم عند الحفظ."></i>
                    </div>
                    <dl class="stk-def stk-def--compact" id="ct-totals">
                        <div class="stk-def__row"><dt>الراتب الأساسي</dt><dd dir="ltr" class="text-right" id="ct-total-basic">0.00</dd></div>
                        <div class="stk-def__row"><dt>إجمالي المستحقات</dt><dd dir="ltr" class="text-right" id="ct-total-additions">0.00</dd></div>
                        <div class="stk-def__row"><dt><strong>مبلغ العقد (إجمالي الراتب)</strong></dt><dd dir="ltr" class="text-right"><strong id="ct-total-gross">0.00</strong> <small>{{ $currencyCode }}</small></dd></div>
                        <div class="stk-def__row"><dt>إجمالي المستقطعات</dt><dd dir="ltr" class="text-right" id="ct-total-deductions">0.00</dd></div>
                        <div class="stk-def__row"><dt><strong>صافي الراتب</strong></dt><dd dir="ltr" class="text-right"><strong id="ct-total-net">0.00</strong> <small>{{ $currencyCode }}</small></dd></div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ══════════ المرفقات ══════════ --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-paperclip"></i> المرفقات</h4>
            </div>
            <div class="card-body">
                <label for="attachments" class="stk-upload mb-0">
                    <span class="stk-upload__placeholder">
                        <i class="feather icon-upload-cloud"></i>
                        <span class="stk-upload__title" id="ct-attachment-name">اضغط لاختيار ملف العقد الموقَّع</span>
                        <span class="stk-upload__hint">
                            PDF أو صورة — بحدّ أقصى 10 ميغابايت
                            @if ($isEdit && $contract->attachments)
                                · الحالي: {{ $contract->attachments }} (اختيار ملفٍ جديد يستبدله)
                            @endif
                        </span>
                    </span>
                </label>
                <input type="file" name="attachments" id="attachments" class="d-none">
            </div>
            <div class="stk-form-foot">
                <a href="{{ $isEdit ? route('Contracts.show', $contract->id) : route('Contracts.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> {{ $isEdit ? 'حفظ التعديلات' : 'حفظ العقد' }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('css/formula-autocomplete.css') }}">
<style>
    /* أيقونةُ الشرح «؟» بجوار عنوان الحقل لا تحته — العنوانُ في هذا القالب كتلةٌ كاملة العرض */
    #contractForm .form-group > label { display: inline-block; }
    #contractForm #ct-totals dd { font-variant-numeric: tabular-nums; }
</style>
@endpush

@push('scripts')
{{-- إكمال متغيّرات الصيغة (basic…) عند كتابة @ في حقل الصيغة --}}
<script src="{{ asset('js/formula-autocomplete.js') }}"></script>
<script>
(function ($) {
    'use strict';

    var EMPLOYEE_URL = @json(route('Contracts.employeeData', ['employee' => '__ID__']));
    var EXCEPT_ID = @json($isEdit ? $contract->id : null);
    var PROBATION_DAYS = {{ \Modules\HR\Services\ContractService::DEFAULT_PROBATION_DAYS }};
    var $form = $('#contractForm');

    // ─── أرقام ───
    function num(v) {
        if (v === null || v === undefined) return 0;
        var s = String(v).replace(/[,\s٬]/g, '').replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace('٫', '.');
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }
    function money(n) {
        return (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function basic() { return num($('#basic_amount').val()); }

    // الصيغة على الأساسي — أرقامٌ وعمليات فقط، كما في `CalculationFormulaTrait`
    function evalFormula(formula) {
        var expr = String(formula || '').replace(/\b(basic_amount|basic_salary|basic)\b/gi, String(basic()));
        if (!/^[0-9+\-*\/().\s]+$/.test(expr)) return null;
        try {
            var v = Function('"use strict"; return (' + expr + ');')();
            return (typeof v === 'number' && isFinite(v)) ? v : null;
        } catch (e) { return null; }
    }

    // ─── select2 ───
    function enhance($scope) {
        if (!$.fn.select2) return;
        $scope.find('select.ct-select2, select.item-select').each(function () {
            var $s = $(this);
            if ($s.hasClass('select2-hidden-accessible')) return;
            $s.select2({ width: '100%', dir: 'rtl', placeholder: $s.data('placeholder') || 'اختر', allowClear: !$s.prop('required') && !$s.hasClass('item-select') });
        });
    }

    // ─── الإجماليات ───
    function updateTotals() {
        var b = basic(), add = 0, ded = 0;
        $('#additions-table tbody tr').each(function () {
            if ($(this).find('.item-select').val()) add += num($(this).find('.amount-input').val());
        });
        $('#deductions-table tbody tr').each(function () {
            if ($(this).find('.item-select').val()) ded += num($(this).find('.amount-input').val());
        });
        var gross = b + add;
        $('#ct-total-basic').text(money(b));
        $('#ct-total-additions').text(money(add));
        $('#ct-total-gross').text(money(gross));
        $('#ct-total-deductions').text(money(ded));
        $('#ct-total-net').text(money(gross - ded)).toggleClass('text-danger', gross - ded < 0);
        return { gross: gross, net: gross - ded };
    }

    function applyFormula($row) {
        var f = $.trim($row.find('.calculation-input').val());
        if (!f) return;
        var v = evalFormula(f);
        if (v !== null) $row.find('.amount-input').val((Math.round(v * 100) / 100).toFixed(2));
    }

    // ─── الصفوف ───
    function addRow(kind, data) {
        var tpl = document.getElementById('tpl-' + kind + '-row');
        var $row = $(tpl.innerHTML.trim());
        var $tbody = $('#' + kind + 's-table tbody');
        if (data) {
            var $sel = $row.find('.item-select');
            // البندُ يُطابَق مع الكتالوج بالاسم؛ وما لا يطابق يُضاف خياراً بمعرّفه
            var $match = $sel.find('option').filter(function () { return $.trim($(this).text()) === $.trim(data.name); }).first();
            if ($match.length) {
                $sel.val($match.val());
            } else {
                $sel.append($('<option>', { value: data.id, text: data.name, selected: true }));
            }
            $row.find('.calculation-input').val(data.formula || '');
            $row.find('.amount-input').val(data.amount !== null && data.amount !== undefined && data.amount !== '' ? num(data.amount).toFixed(2) : '');
            applyFormula($row);
        }
        $tbody.append($row);
        enhance($row);
        return $row;
    }

    $(document).on('click', '.add-row-button-addition', function (e) { e.preventDefault(); addRow('addition'); });
    $(document).on('click', '.add-row-button-deduction', function (e) { e.preventDefault(); addRow('deduction'); });
    $(document).on('click', '#contractForm .remove-row', function () { $(this).closest('tr').remove(); updateTotals(); });

    $(document).on('change', '#contractForm .item-select', function () {
        var $row = $(this).closest('tr');
        var $opt = $(this).find('option:selected');
        if (!$(this).val()) { updateTotals(); return; }
        var formula = $opt.data('calculation') || '';
        var amount = $opt.data('amount');
        $row.find('.calculation-input').val(formula);
        $row.find('.amount-input').val(amount !== undefined && amount !== '' ? num(amount).toFixed(2) : '');
        applyFormula($row);
        updateTotals();
    });
    $(document).on('input', '#contractForm .calculation-input', function () { applyFormula($(this).closest('tr')); updateTotals(); });
    $(document).on('input change', '#contractForm .amount-input', updateTotals);
    $('#basic_amount').on('input change', function () {
        $('#contractForm tbody tr').each(function () { applyFormula($(this)); });
        updateTotals();
    });

    // ─── المدة وتاريخ الانتهاء ───
    function parseDate(v) {
        if (!v) return null;
        var p = v.split('-');
        return p.length === 3 ? new Date(Date.UTC(+p[0], +p[1] - 1, +p[2])) : null;
    }
    function fmtDate(d) { return d.toISOString().slice(0, 10); }
    function addMonthsNoOverflow(d, m) {
        var y = d.getUTCFullYear(), mo = d.getUTCMonth() + m, day = d.getUTCDate();
        var last = new Date(Date.UTC(y, mo + 1, 0)).getUTCDate();
        return new Date(Date.UTC(y, mo, Math.min(day, last)));
    }
    // مطابقٌ لـ`ContractService::computeEndDate()`: شاملاً يوم البداية
    function computeEnd() {
        var start = parseDate($('#start_date').val() || $('#join_date').val());
        var n = parseInt($('#duration').val(), 10);
        var unit = $('#duration_unit').val();
        if (!start || !n || n < 1) return null;
        var end = unit === '1' ? new Date(start.getTime() + n * 86400000)
            : addMonthsNoOverflow(start, unit === '2' ? n : n * 12);
        return new Date(end.getTime() - 86400000);
    }
    function isPeriod() { return $('#duration_radio').is(':checked'); }
    function syncDuration() {
        var period = isPeriod();
        $('#duration, #duration_unit').prop('disabled', !period).prop('required', period);
        $('#duration-inputs, #duration-unit-wrap').toggleClass('text-muted', !period);
        $('#end_date').prop('readonly', period).toggleClass('stk-locked', period);
        var $prev = $('#ct-end-preview');
        if (period) {
            var end = computeEnd();
            $('#end_date').val(end ? fmtDate(end) : '');
            $prev.html(end
                ? '<i class="feather icon-info"></i> ينتهي العقد في <strong dir="ltr">' + fmtDate(end) + '</strong> — راجِعه قبل الحفظ.'
                : 'أدخل تاريخ البداية والمدة ليُحسب تاريخ الانتهاء.');
        } else {
            $prev.text($('#end_date').val() ? '' : 'بلا تاريخ انتهاء: عقدٌ غير محدد المدة.');
        }
    }
    $('#duration_radio, #enddate_radio').on('change', syncDuration);
    $('#start_date, #duration, #duration_unit').on('input change', syncDuration);
    $('#end_date').on('input change', function () { if (!isPeriod()) syncDuration(); });

    // نهايةُ التجربة المقترحة: 90 يوماً من الالتحاق — ما لم يكتبها المستخدم
    var probationAuto = !$('#probation_end_date').val();
    $('#probation_end_date').on('input change', function () { probationAuto = !$(this).val(); });
    function suggestProbation() {
        var j = parseDate($('#join_date').val());
        if (!probationAuto || !j) return;
        $('#probation_end_date').val(fmtDate(new Date(j.getTime() + (PROBATION_DAYS - 1) * 86400000)));
    }
    $('#join_date').on('input change', function () { suggestProbation(); if (!$('#start_date').val()) syncDuration(); });

    // ─── الموظف: ملءٌ تلقائي من ملفه ───
    function loadEmployee(id, fill) {
        var $info = $('#ct-employee-info');
        if (!id) { $info.empty(); return; }
        $info.text('جارٍ جلب بيانات الموظف…');
        var url = EMPLOYEE_URL.replace('__ID__', id) + (EXCEPT_ID ? '?except=' + EXCEPT_ID : '');
        $.getJSON(url).done(function (res) {
            if (!res || !res.success) { $info.text((res && res.message) || 'تعذّر جلب بيانات الموظف.'); return; }
            var e = res.employee;
            var bits = [];
            if (e.code) bits.push('رقم الموظف: ' + e.code);
            if (e.branch) bits.push('الفرع: ' + e.branch);
            if (e.department) bits.push('القسم: ' + e.department);
            if (e.hire_date) bits.push('تاريخ التعيين: ' + e.hire_date);
            $info.text(bits.join(' · ') || 'لا بيانات وظيفية محفوظة في ملف الموظف.');

            if (fill) {
                if (e.job_title_id) $('#job_title_id').val(String(e.job_title_id)).trigger('change');
                if (e.job_level_id) $('#job_level_id').val(String(e.job_level_id)).trigger('change');
                if (e.hire_date) {
                    if (!$('#join_date').val()) $('#join_date').val(e.hire_date).trigger('change');
                    if (!$('#start_date').val()) $('#start_date').val(e.hire_date).trigger('change');
                }
            }

            // العقد الأساسي: عقودُ هذا الموظف وحده
            var $parent = $('#parent_contract_id');
            var keep = $parent.val() || String($parent.data('selected') || '');
            $parent.find('option').not('[value=""]').remove();
            (e.contracts || []).forEach(function (c) {
                $parent.append($('<option>', { value: c.id, text: c.label, selected: String(c.id) === keep }));
            });
            $parent.trigger('change.select2');
        }).fail(function () {
            $info.text('تعذّر جلب بيانات الموظف — أكمل الحقول يدوياً.');
        });
    }
    $('#employee_id').on('change', function () { loadEmployee($(this).val(), true); });

    // ─── المرفق ───
    $('#attachments').on('change', function () {
        var f = this.files && this.files[0];
        $('#ct-attachment-name').text(f ? f.name : 'اضغط لاختيار ملف العقد الموقَّع');
    });

    // ─── الحفظ: مبلغ العقد لا يكون صفراً ───
    $form.on('submit', function (e) {
        syncDuration();
        var t = updateTotals();
        if (t.gross <= 0) {
            e.preventDefault();
            var el = document.getElementById('basic_amount');
            var msg = 'مبلغ العقد صفر: أدخل الراتب الأساسي، أو أضف بنداً مستحقاً بمبلغ.';
            if (window.HrFormUx) { window.HrFormUx.mark(el, msg); window.HrFormUx.focus(el); } else { alert(msg); el.focus(); }
            return false;
        }
        if (t.net < 0) {
            e.preventDefault();
            var first = $('#deductions-table .amount-input').get(0) || document.getElementById('basic_amount');
            var m2 = 'مجموع المستقطعات يتجاوز إجمالي الراتب.';
            if (window.HrFormUx) { window.HrFormUx.mark(first, m2); window.HrFormUx.focus(first); } else { alert(m2); }
            return false;
        }
    });

    $(function () {
        enhance($form);
        syncDuration();
        updateTotals();
        // بعد إخفاق التحقّق أو في التعديل: تُعرض بياناتُ الموظف دون الكتابة فوق المُدخَل
        if ($('#employee_id').val()) loadEmployee($('#employee_id').val(), false);
    });
})(jQuery);
</script>
@endpush
