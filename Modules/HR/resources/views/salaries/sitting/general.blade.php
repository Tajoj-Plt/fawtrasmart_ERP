@extends('hr::master')

@section('title', 'الإعدادات العامة للرواتب')

@section('content')
    @php
        /** أيقونةُ شرحٍ تُقرأ عند التمرير (`stk-tip`) — ما يفعله الحقل وأين يسري. */
        $tip = fn (string $text) => '<i class="feather icon-help-circle stk-tip" title="'.e($text).'"></i>';

        /*
            قيمةُ المفتاح بعد فشل التحقّق: الخانةُ المطفأة لا تُرسَل أصلاً، فـ
            `old($name, $default)` كانت تعيدها مفعّلةً بالقيمة المحفوظة.
        */
        $submitted = session()->hasOldInput();
        $on = fn (string $name, bool $current) => $submitted ? (bool) old($name) : $current;
        $s = $payrollSettings;
    @endphp

    @section('page-actions')
        <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> الإعدادات
        </a>
        <button type="submit" form="payrollGeneralForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الإعدادات
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'الإعدادات العامة للرواتب',
        'icon' => 'settings',
        'crumbs' => ['الإعدادات' => route('SalarySittings.index'), 'الإعدادات العامة' => null],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        @include('hr::components.guide', [
            'key' => 'hr-payroll-settings.general',
            'title' => 'متى يُولَّد الراتب، ومتى يُصرف، ومن يصرّح به؟',
            'lead' => 'القسيمةُ المعتمدة تولّد كلَّ فترةٍ «صرفةً» معلّقة، والصرفةُ تُصرف في يوم الصرف فيُكتب سندُها وقيدُها. هذه الشاشةُ تحكم الخطوتين وضوابطَهما.',
            'blocks' => [
                'يوم الصرف' => [
                    'يُقرأ بالترتيب: يومُ الصرف في القسيمة نفسِها، فإن كان فارغاً فاليومُ الافتراضي هنا.',
                    'ويحدّد تاريخَ استحقاق كلّ صرفةٍ تُولَّد بعد الحفظ — الصرفاتُ المولَّدة قبله لا تتغيّر.',
                ],
                'الأتمتة' => [
                    'مهمّةُ «معالجة قسائم الرواتب» تعمل يومياً: تولّد ما حان من صرفات، ثمّ تصرف المستحقّ منها — وكلُّ مرحلةٍ لها مفتاحُها.',
                    'إطفاءُ التوليد يوقف أيضاً التوليدَ عند فتح صفحة القسيمة؛ وإطفاءُ الصرف يُبقي الصرفاتِ معلّقةً حتى تُصرف يدوياً.',
                ],
                'الاعتماد' => [
                    'اعتمادُ مسير الرواتب هو الاعتمادُ الوحيد قبل خروج النقد — لا اعتمادَ لدفعات الصرف.',
                ],
            ],
            'facts' => [
                'الأثرُ على ما يُولَّد ويُصرف بعد الحفظ',
                'العملة: عملةُ النظام من شاشة «العملات»',
            ],
        ])

        <form id="payrollGeneralForm" action="{{ route('SalarySittings.general.update') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-body">

                    {{-- ═══════════ الصرف والجدولة ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-calendar"></i>
                            <h6 class="stk-section__title">الصرف والجدولة</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="default_payment_day" class="stk-req">
                                        يوم الصرف الافتراضي
                                        {!! $tip('اليومُ من الشهر الذي تستحقّ فيه الصرفة (1 إلى 28). يُستعمل لكلّ قسيمةٍ تُرك فيها «يوم الصرف» فارغاً. يسري على الصرفات التي تُولَّد بعد الحفظ.') !!}
                                    </label>
                                    <input type="number" min="1" max="28" step="1" required
                                           id="default_payment_day" name="default_payment_day"
                                           class="form-control @error('default_payment_day') is-invalid @enderror"
                                           value="{{ old('default_payment_day', $s['default_payment_day'] ?? 1) }}">
                                    <span class="stk-help">حتى 28 كي يوجد اليومُ في كلّ شهر، ومنه فبراير.</span>
                                    @error('default_payment_day')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="auto_generate_payroll_runs">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            توليد الصرفات تلقائياً
                                            {!! $tip('تقرؤه مهمّةُ «معالجة قسائم الرواتب» اليومية، وفتحُ صفحة القسيمة. مطفأً: لا تُنشأ صرفةٌ جديدة إلا يدوياً من صفحة القسيمة.') !!}
                                        </span>
                                        <span class="stk-switch__hint">ينشئ لكلّ قسيمةٍ نشطة صرفةَ الفترة التي حان موعدُها.</span>
                                    </span>
                                    <input type="checkbox" id="auto_generate_payroll_runs" name="auto_generate_payroll_runs" value="1"
                                           @checked($on('auto_generate_payroll_runs', (bool) ($s['auto_generate_payroll_runs'] ?? true)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="auto_disburse_payroll_runs">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            صرف الرواتب تلقائياً
                                            {!! $tip('تقرؤه مهمّةُ «معالجة قسائم الرواتب» اليومية: تصرف الصرفاتِ التي بلغت يومَ صرفها واستوفت الاعتماد، بسند صرفٍ وقيد. مطفأً: تبقى معلّقةً حتى زرّ «صرف».') !!}
                                        </span>
                                        <span class="stk-switch__hint">سندُ صرفٍ وقيدٌ محاسبيّ لكلّ صرفةٍ حلّ يومُها.</span>
                                    </span>
                                    <input type="checkbox" id="auto_disburse_payroll_runs" name="auto_disburse_payroll_runs" value="1"
                                           @checked($on('auto_disburse_payroll_runs', (bool) ($s['auto_disburse_payroll_runs'] ?? true)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ الضوابط ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-shield"></i>
                            <h6 class="stk-section__title">الضوابط والاعتماد</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="require_disbursement_approval">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            اشتراط اعتماد مسير الرواتب قبل الصرف
                                            {!! $tip('يُفحص عند كلّ صرف — يدوياً أو بالمهمّة اليومية أو جماعياً: لا تُصرف صرفةٌ إلا وقسيمتُها ضمن مسير رواتب معتمد. مطفأً: يكفي اعتمادُ القسيمة.') !!}
                                        </span>
                                        <span class="stk-switch__hint">اعتمادُ المسير هو الاعتمادُ الوحيد — وإطفاؤه يُسقط توثيقَ من صرّح بخروج النقد.</span>
                                    </span>
                                    <input type="checkbox" id="require_disbursement_approval" name="require_disbursement_approval" value="1"
                                           @checked($on('require_disbursement_approval', (bool) ($s['require_disbursement_approval'] ?? true)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="require_slip_maker_checker">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            فصل المهام على القسائم
                                            {!! $tip('يُفحص عند زرّ «اعتماد» في صفحة القسيمة: من أنشأ القسيمة لا يعتمدها. اتركه مطفأً إن كان في المنشأة مسؤولٌ واحد.') !!}
                                        </span>
                                        <span class="stk-switch__hint">من حدّد الأجر غيرُ من صادق عليه.</span>
                                    </span>
                                    <input type="checkbox" id="require_slip_maker_checker" name="require_slip_maker_checker" value="1"
                                           @checked($on('require_slip_maker_checker', (bool) ($s['require_slip_maker_checker'] ?? false)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ إشعار الموظف ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-mail"></i>
                            <h6 class="stk-section__title">إشعار الموظف</h6>
                        </div>

                        <label class="stk-switch" for="send_salary_slips">
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">
                                    إرسال قسيمة الراتب بالبريد عند الصرف
                                    {!! $tip('يُنفَّذ بعد نجاح صرف كلّ صرفة (يدوياً أو بالمهمّة): رسالةٌ إلى بريد الموظف في بطاقته بالفترة والإجمالي والخصومات والصافي. عبر إعدادات البريد (SMTP) في إعدادات النظام.') !!}
                                </span>
                                <span class="stk-switch__hint">الموظفُ بلا بريدٍ في بطاقته لا يُرسل له شيء، والإشعارُ داخل النظام يصله على أيّ حال.</span>
                            </span>
                            <input type="checkbox" id="send_salary_slips" name="send_salary_slips" value="1"
                                   @checked($on('send_salary_slips', (bool) ($s['send_salary_slips'] ?? false)))>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                        </label>
                    </div>

                    {{-- ═══════════ قوالب الطباعة ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-printer"></i>
                            <h6 class="stk-section__title">قوالب طباعة الرواتب</h6>
                            {!! $tip('القسيمة، وكشف المسير، والعقد، وسند السلفة، وسند العمولة — قالبُ كلٍّ منها هو الافتراضيُّ في «قوالب المستندات»، وأيُّ تعديلٍ هناك يسري على الطباعة فوراً.') !!}
                        </div>
                        @if (hasPermission('settings_edit_general_settings'))
                            <a href="{{ route('document-templates.index', ['group' => 'hr']) }}" class="btn btn-outline-primary">
                                <i class="feather icon-external-link"></i> فتح قوالب المستندات
                            </a>
                        @else
                            <span class="stk-help mt-0">تُدار من «قوالب المستندات» وتحتاج صلاحية «تعديل الإعدادات العامة».</span>
                        @endif
                    </div>

                    @include('hr::salaries.sitting.partials.cron-state', [
                        'cronKeys' => [
                            'payroll_process' => 'توليد الصرفات وصرفُها التلقائي',
                        ],
                    ])
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ الإعدادات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
