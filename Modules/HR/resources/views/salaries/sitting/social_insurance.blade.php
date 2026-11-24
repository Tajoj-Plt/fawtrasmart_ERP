@extends('hr::master')

@section('title', 'إعدادات التأمينات الاجتماعية')

@section('content')
    @php
        $submitted = session()->hasOldInput();
        $enabled = $submitted ? (bool) old('enabled') : (bool) ($gosiSettings['enabled'] ?? false);
        $gosiUnlinked = collect($accountRows)->where('state', '!=', 'mapped')->isNotEmpty();
    @endphp

    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'إعدادات التأمينات الاجتماعية',
            'icon' => 'shield',
            'sub' => 'كم ينخصم من الموظف، وكم تدفع الشركة، على أي راتب تنحسب',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الرواتب' => route('SalarySittings.index'),
                'التأمينات الاجتماعية' => null,
            ],
            'actions' => (canAny(['insurance.index', 'insurance.show'])
                    ? '<a href="'.route('hr.gosi.remittances').'" class="btn btn-outline-primary"><i class="feather icon-send"></i> سداد التأمينات</a>'
                    : '')
                .'<button type="submit" form="gosi_settings_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ الإعدادات</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        @if ($gosiUnlinked && $enabled)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                التأمينات شغّالة لكن فيه حساب مو مربوط في {{ \App\Support\PayrollAccounts::SCREEN }} — صرف الرواتب بيتوقف لين تربطه.
                @if (\App\Support\PayrollAccounts::canEdit())
                    <a href="{{ \App\Support\PayrollAccounts::editUrl(\App\Support\PayrollAccounts::GOSI_EMPLOYEE_PAYABLE) }}" class="alert-link">ربطها الآن</a>
                @endif
            </div>
        @endif

        @include('hr::components.guide', [
            'key' => 'hr-gosi.settings',
            'title' => 'وش هي التأمينات الاجتماعية وكيف تنحسب؟',
            'lead' => 'كل ما تصرف راتب، النظام يحسب التأمينات: جزء ينخصم من راتب الموظف، وجزء تدفعه الشركة من عندها. والمبلغين يتجمعون عليك لين تسددهم للتأمينات من شاشة «سداد التأمينات».',
            'blocks' => [
                'مين ينحسب عليه؟' => [
                    'السعودي (المواطن): ينخصم منه 9.75% والشركة تدفع 11.75%.',
                    'غير السعودي (المقيم): ما ينخصم منه شيء، والشركة تدفع 2% بس.',
                    'الزائر: ما عليه تأمينات.',
                    'وتقدر تعفي أي موظف بعينه من بطاقته (خانة «الخضوع للتأمينات»).',
                ],
                'على أي راتب تنحسب؟ (مثال)' => [
                    'موظف سعودي: أساسي 4,000 + بدل سكن 1,000 + بدل مواصلات 500.',
                    'السكن معلّم عليه «خاضع للتأمينات» والمواصلات لا ← تنحسب على 5,000 بس.',
                    'ينخصم من الموظف 9.75% = 487.50، والشركة تدفع 11.75% = 587.50.',
                ],
                'وش يصير لو غيّرت هنا؟' => [
                    'التغيير يمشي على الرواتب اللي تنصرف بعده بس، والقديمة ما تتغير.',
                    'ما تقدر تشغّل التأمينات إلا إذا كانت حساباتها مربوطة في «إعدادات الحسابات المحاسبية».',
                ],
            ],
            'facts' => [
                'حصة الموظف تنقص راتبه',
                'حصة الشركة ما تنقص راتب الموظف',
                'التغيير على الرواتب الجاية بس',
            ],
        ])

        <form id="gosi_settings_form" action="{{ route('hr.gosi.settings.update') }}" method="POST">
            @csrf

            @if ($errors->any())
                <div class="stk-form-errors alert alert-danger" role="alert">
                    <div class="stk-form-errors__head"><i class="feather icon-alert-circle"></i> لم تُحفظ الإعدادات — راجع الحقول المعلَّمة:</div>
                    <ul class="stk-form-errors__list mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="stk-section">
                                <div class="stk-section__head">
                                    <i class="feather icon-percent"></i>
                                    <h6 class="stk-section__title">الموظف السعودي</h6>
                                <span class="hr-hover-panel">
                                    <button type="button" class="hr-hover-panel__btn" aria-label="جرّب بنفسك" title="جرّب بنفسك: مرّر المؤشر هنا واكتب راتب موظف">!</button>
                                    <div class="hr-hover-panel__pop">
                            {{-- مثالٌ حيّ: يُحسب من النسب المكتوبة أعلاه لحظةَ تغييرها (للشرح فقط — لا يُرسَل) --}}
                            <div class="gosi-example" id="gosiExample">
                                <div class="gosi-example__head">
                                    <i class="feather icon-play-circle"></i>
                                    <strong>جرّب بنفسك</strong>
                                    <span class="stk-help mb-0">اكتب راتب موظف ويطلع لك كم ينخصم منه وكم تدفع الشركة.</span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-3 col-6">
                                        <label for="gxBasic" class="mb-25">الراتب الأساسي</label>
                                        <input type="number" min="0" step="1" id="gxBasic" class="form-control" value="4000">
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <label for="gxIns" class="mb-25">بدلات تدخل (مثل السكن)</label>
                                        <input type="number" min="0" step="1" id="gxIns" class="form-control" value="1000">
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <label for="gxOther" class="mb-25">بدلات ما تدخل (مثل المواصلات)</label>
                                        <input type="number" min="0" step="1" id="gxOther" class="form-control" value="500">
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <label for="gxNat" class="mb-25">الجنسية</label>
                                        <select id="gxNat" class="form-control hr-s2">
                                            <option value="citizen">سعودي</option>
                                            <option value="resident">مقيم</option>
                                            <option value="visitor">زائر</option>
                                        </select>
                                    </div>
                                </div>
                                <ol class="gosi-example__steps" id="gxSteps"></ol>
                                <div class="gosi-example__totals">
                                    <div class="gosi-example__total">
                                        <span>ينخصم من راتب الموظف</span>
                                        <b id="gxEmp">—</b>
                                        <small id="gxNet"></small>
                                    </div>
                                    <div class="gosi-example__total">
                                        <span>تدفعه الشركة من عندها</span>
                                        <b id="gxEr">—</b>
                                    </div>
                                    <div class="gosi-example__total">
                                        <span>المجموع اللي ينسدد للتأمينات</span>
                                        <b id="gxTotal">—</b>
                                    </div>
                                </div>
                            </div>
                                    </div>
                                </span>
                                </div>

                                <label class="stk-switch mb-2" for="gosi_enabled">
                                    <input type="checkbox" id="gosi_enabled" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            احسب التأمينات لما أصرف الراتب
                                            <i class="feather icon-help-circle stk-tip" title="إذا كان شغّال: كل ما تصرف راتب، ينخصم من الموظف نصيبه، وتنحسب على الشركة نصيبها. إذا مطفي: ما تنحسب تأمينات أبداً. لازم تكون حساباتها مربوطة عشان تشغّله."></i>
                                        </span>
                                        <span class="stk-switch__hint">يجي مطفي عشان ما ينخصم شي من الرواتب إلا لما تقرر أنت</span>
                                    </span>
                                </label>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="employee_rate">
                                                كم ينخصم من الموظف؟ (%)
                                                <i class="feather icon-help-circle stk-tip" title="هذي النسبة تنخصم من راتب الموظف السعودي. المعتاد: 9.75%."></i>
                                            </label>
                                            <input type="number" step="0.001" min="0" max="100" id="employee_rate" name="employee_rate" required
                                                   class="form-control @error('employee_rate') is-invalid @enderror"
                                                   value="{{ old('employee_rate', $gosiSettings['employee_rate'] ?? 9.75) }}">
                                            @error('employee_rate')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="employer_rate">
                                                كم تدفع الشركة؟ (%)
                                                <i class="feather icon-help-circle stk-tip" title="هذي تدفعها الشركة من عندها عن الموظف السعودي، وما تنخصم من راتبه. المعتاد: 11.75%."></i>
                                            </label>
                                            <input type="number" step="0.001" min="0" max="100" id="employer_rate" name="employer_rate" required
                                                   class="form-control @error('employer_rate') is-invalid @enderror"
                                                   value="{{ old('employer_rate', $gosiSettings['employer_rate'] ?? 11.75) }}">
                                            @error('employer_rate')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="wage_cap">
                                                أعلى راتب تنحسب عليه
                                                <i class="feather icon-help-circle stk-tip" title="لو راتب الموظف أعلى من هذا الرقم، التأمينات تنحسب على هذا الرقم بس. المعتاد: 45,000. خلّه فاضي أو صفر لو ما تبي حد."></i>
                                            </label>
                                            <input type="number" step="0.01" min="0" id="wage_cap" name="wage_cap"
                                                   class="form-control @error('wage_cap') is-invalid @enderror"
                                                   value="{{ old('wage_cap', $gosiSettings['wage_cap'] ?? 45000) }}">
                                            @error('wage_cap')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <p class="stk-help mt-0">هذي النسب للموظف <strong>السعودي</strong> — وللي ما انكتبت جنسيته في بطاقته.</p>
                            </div>

                            <div class="stk-section">
                                <div class="stk-section__head">
                                    <i class="feather icon-users"></i>
                                    <h6 class="stk-section__title">الموظف غير السعودي</h6>
                                    <i class="feather icon-help-circle stk-tip" title="النظام يعرف جنسية الموظف من بطاقته: سعودي ← النسب اللي فوق، مقيم ← النسب هنا، زائر ← ما عليه تأمينات. وتقدر تعفي أي موظف من بطاقته."></i>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="resident_employee_rate">
                                                كم ينخصم من الموظف؟ (%)
                                                <i class="feather icon-help-circle stk-tip" title="المعتاد صفر: ما ينخصم من راتب غير السعودي شيء."></i>
                                            </label>
                                            <input type="number" step="0.001" min="0" max="100" id="resident_employee_rate" name="resident_employee_rate"
                                                   class="form-control @error('resident_employee_rate') is-invalid @enderror"
                                                   value="{{ old('resident_employee_rate', $gosiSettings['resident_employee_rate'] ?? 0) }}">
                                            @error('resident_employee_rate')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="resident_employer_rate">
                                                كم تدفع الشركة؟ (%)
                                                <i class="feather icon-help-circle stk-tip" title="المعتاد 2% (تأمين الأخطار المهنية). تدفعها الشركة وما تنخصم من راتبه."></i>
                                            </label>
                                            <input type="number" step="0.001" min="0" max="100" id="resident_employer_rate" name="resident_employer_rate"
                                                   class="form-control @error('resident_employer_rate') is-invalid @enderror"
                                                   value="{{ old('resident_employer_rate', $gosiSettings['resident_employer_rate'] ?? 2) }}">
                                            @error('resident_employer_rate')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <p class="stk-help mb-0">الزائر ما عليه تأمينات. واللي معفى من بطاقته ما ينحسب عليه شيء، ويطلع السبب في قسيمته.</p>
                            </div>

                            {{-- الحساباتُ للقراءة — تُربط من «إعدادات الحسابات المحاسبية». ويشترط تشغيلُ الاستقطاع ربطَ الثلاثة. --}}
                            @include('hr::salaries.sitting.partials.linked-accounts', [
                                'rows' => $accountRows,
                                'title' => 'الحسابات',
                                'focus' => \App\Support\PayrollAccounts::GOSI_EMPLOYEE_PAYABLE,
                                'hints' => [
                                    \App\Support\PayrollAccounts::GOSI_EMPLOYEE_PAYABLE => 'يتجمع فيه اللي انخصم من الموظفين لين تسدده للتأمينات',
                                    \App\Support\PayrollAccounts::GOSI_EMPLOYER_PAYABLE => 'يتجمع فيه نصيب الشركة لين تسدده للتأمينات',
                                    \App\Support\PayrollAccounts::GOSI_EXPENSE => 'هنا ينسجل نصيب الشركة كمصروف عليها',
                                ],
                            ])
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-list"></i> البدلات اللي تدخل في التأمينات</h4>
                        </div>
                        <div class="card-body">
                            <p class="stk-help mt-0">
                                التأمينات تنحسب على الراتب الأساسي + هذي البدلات بس (معلّم عليها «خاضع للتأمينات» في بنود الرواتب):
                            </p>
                            @if ($insurableItems->isNotEmpty())
                                <ul class="list-unstyled mb-1">
                                    @foreach ($insurableItems as $insurable)
                                        <li class="mb-50">
                                            <i class="feather icon-check-circle text-success"></i>
                                            @if (hasPermission('salary_items.show'))
                                                <a href="{{ route('SalaryItems.show', $insurable->id) }}">{{ $insurable->name }}</a>
                                            @else
                                                {{ $insurable->name }}
                                            @endif
                                            @if ((int) $insurable->status !== \App\Models\SalaryItem::STATUS_ACTIVE)
                                                <span class="stk-state stk-state--off">غير نشط</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="stk-empty py-1">
                                    <p class="stk-empty__hint mb-0">ما فيه ولا بدل معلّم — التأمينات تنحسب على الأساسي بس.</p>
                                </div>
                            @endif
                            @if (hasPermission('salary_items.index') && hasPermission('salary_items.edit'))
                                <a href="{{ route('SalaryItems.index', ['type' => 1]) }}" class="btn btn-outline-primary btn-sm btn-block mt-1">
                                    <i class="feather icon-edit-2"></i> حدد البدلات اللي تدخل
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-file-minus"></i> كيف يطلع في القسيمة؟</h4>
                        </div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>يطلع باسم</dt>
                                    <dd>{{ $deductionName }}</dd>
                                </div>
                            </dl>
                            <p class="stk-help mb-0">النظام يضيفه للقسيمة لحاله وقت الصرف — ما تضيفه ولا تحذفه بيدك.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('SalarySittings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ الإعدادات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('styles')
    <style>
        .gosi-example {
            margin: 1rem 0 1.25rem;
            padding: .9rem 1rem;
            border: 1px dashed var(--app-border, #d8d6de);
            border-radius: var(--app-radius, .357rem);
            background: color-mix(in srgb, var(--app-primary, #7367f0) 4%, transparent);
        }
        .gosi-example__head { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem .6rem; margin-bottom: .6rem; }
        .gosi-example__head i { color: var(--app-primary, #7367f0); }
        .gosi-example__steps { margin: .9rem 0 .7rem; padding-inline-start: 1.25rem; line-height: 2; }
        .gosi-example__totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .6rem; }
        .gosi-example__total {
            display: flex; flex-direction: column; gap: .15rem;
            padding: .6rem .8rem; border-radius: var(--app-radius, .357rem);
            background: var(--app-surface, #fff); border: 1px solid var(--app-border, #ebe9f1);
        }
        .gosi-example__total span { font-size: .85rem; color: var(--app-muted, #6e6b7b); }
        .gosi-example__total b { font-size: 1.25rem; color: var(--app-primary, #7367f0); }
        .gosi-example__total small { color: var(--app-muted, #6e6b7b); }
    </style>
@endpush

@push('scripts')
    @include('hr::components.hover-panel')
    <script>
        /* المثال الحيّ: نفسُ منطق SocialInsuranceService — للشرح فقط، لا يُحفظ شيء */
        (function () {
            var $ = function (id) { return document.getElementById(id); };
            var num = function (id) { var el = $(id); return el ? (parseFloat(el.value) || 0) : 0; };
            var fmt = function (n) { n = Math.round(n * 100) / 100; var d = (n % 1 === 0) ? 0 : 2; return n.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: 2 }); };

            function calc() {
                var basic = num('gxBasic'), ins = num('gxIns'), other = num('gxOther');
                var nat = $('gxNat').value;
                var cap = num('wage_cap');
                var on = $('gosi_enabled') ? $('gosi_enabled').checked : true;

                var empRate = nat === 'citizen' ? num('employee_rate') : (nat === 'resident' ? num('resident_employee_rate') : 0);
                var erRate = nat === 'citizen' ? num('employer_rate') : (nat === 'resident' ? num('resident_employer_rate') : 0);

                var wage = basic + ins;
                var capped = cap > 0 && wage > cap;
                var base = capped ? cap : wage;
                var emp = base * empRate / 100;
                var er = base * erRate / 100;

                var steps = [];
                if (!on) {
                    steps.push('<b>التأمينات مطفية</b> فوق — ما بينحسب شيء. شغّلها عشان تنحسب.');
                    emp = 0; er = 0;
                } else if (nat === 'visitor') {
                    steps.push('الزائر <b>ما عليه تأمينات</b>.');
                    emp = 0; er = 0;
                } else {
                    steps.push('تنحسب على الأساسي + البدلات اللي تدخل: ' + fmt(basic) + ' + ' + fmt(ins) + ' = <b>' + fmt(wage) + '</b>'
                        + (other > 0 ? ' (بدلات ' + fmt(other) + ' ما تدخل)' : ''));
                    if (capped) {
                        steps.push('الراتب أعلى من الحد (' + fmt(cap) + ') ← تنحسب على <b>' + fmt(cap) + '</b> بس');
                    }
                    steps.push('ينخصم من الموظف ' + fmt(empRate) + '% من ' + fmt(base) + ' = <b>' + fmt(emp) + '</b>');
                    steps.push('الشركة تدفع ' + fmt(erRate) + '% من ' + fmt(base) + ' = <b>' + fmt(er) + '</b>');
                }
                $('gxSteps').innerHTML = steps.map(function (t) { return '<li>' + t + '</li>'; }).join('');

                var gross = basic + ins + other;
                $('gxEmp').textContent = fmt(emp);
                $('gxNet').textContent = 'راتبه ' + fmt(gross) + ' ← يستلم ' + fmt(gross - emp);
                $('gxEr').textContent = fmt(er);
                $('gxTotal').textContent = fmt(emp + er);
            }

            ['gxBasic', 'gxIns', 'gxOther', 'gxNat', 'employee_rate', 'employer_rate', 'wage_cap', 'resident_employee_rate', 'resident_employer_rate', 'gosi_enabled'].forEach(function (id) {
                var el = $(id);
                if (el) { el.addEventListener('input', calc); el.addEventListener('change', calc); }
            });
            if (window.jQuery) { window.jQuery('#gxNat').on('change', calc); }
            calc();
        })();
    </script>
@endpush
