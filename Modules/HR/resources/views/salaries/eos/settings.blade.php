@extends('hr::master')

@section('title', 'إعدادات مكافأة نهاية الخدمة')

@section('content')
    @php
        /** أيقونةُ شرحٍ تُقرأ عند التمرير (`stk-tip`) — ما يفعله الحقل وأين يسري. */
        $tip = fn (string $text) => '<i class="feather icon-help-circle stk-tip" title="'.e($text).'"></i>';

        // الخانةُ المطفأة لا تُرسَل — فبعد فشل التحقّق يُقرأ ما أُرسل لا المحفوظ
        $submitted = session()->hasOldInput();
        $on = fn (string $name, bool $current) => $submitted ? (bool) old($name) : $current;
        $eosUnlinked = collect($accountRows)->where('state', '!=', 'mapped')->isNotEmpty();

        $fraction = function ($f) {
            $f = (float) $f;
            // أزواجٌ لا مفاتيح: مفتاحُ المصفوفة العشريّ يُقصّ إلى عددٍ صحيح في PHP، فكان «0» يُطبع «الثلثان»
            foreach ([[0.0, 'ما ياخذ شيء'], [1 / 3, 'ثلث المكافأة'], [0.5, 'نص المكافأة'], [2 / 3, 'ثلثين المكافأة'], [1.0, 'المكافأة كاملة']] as [$v, $label]) {
                if (abs($f - (float) $v) < 0.001) {
                    return $label;
                }
            }

            return rtrim(rtrim(number_format($f * 100, 1), '0'), '.').'%';
        };
    @endphp

    @section('page-actions')
        @permission('eos.index')
            <a href="{{ route('hr.eos.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> نهاية الخدمة
            </a>
        @endpermission
        <button type="submit" form="eosSettingsForm" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ الإعدادات
        </button>
    @endsection

    @include('stock::components.page-head', [
        'title' => 'إعدادات مكافأة نهاية الخدمة',
        'icon' => 'award',
        'crumbs' => array_filter([
            'إعدادات الرواتب' => route('SalarySittings.index'),
            'نهاية الخدمة' => hasPermission('eos.index') ? route('hr.eos.index') : null,
            'الإعدادات' => null,
        ], fn ($v, $k) => $v !== null || $k === 'الإعدادات', ARRAY_FILTER_USE_BOTH),
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('layouts.alerts.warning')

        {{-- الحسابان غير مربوطين: لا يُمنع الحفظ، لكن يُنبَّه — الاعتمادُ سيُرفض --}}
        @if ($eosUnlinked)
            <div class="alert alert-warning" id="eosUnlinkedWarning">
                <i class="feather icon-alert-triangle"></i>
                حسابات نهاية الخدمة (أو واحد منها) مو مربوطة في {{ \App\Support\PayrollAccounts::SCREEN }}.
                النظام يحسب المكافأة عادي، لكن ما يقدر يعتمدها لين تربط الحسابات.
                @if (\App\Support\PayrollAccounts::canEdit())
                    <a href="{{ \App\Support\PayrollAccounts::editUrl(\App\Support\PayrollAccounts::EOS_EXPENSE) }}" class="alert-link">ربطهما الآن</a>
                @endif
            </div>
        @endif
        @include('stock::components.form-errors')

        @include('hr::components.guide', [
            'key' => 'hr-eos.settings',
            'title' => 'وش هي مكافأة نهاية الخدمة وكيف تنحسب؟',
            'lead' => 'لما موظف يطلع من الشركة (استقالة أو إنهاء عقد)، النظام يحسب له مكافأة على حسب سنين خدمته وراتبه. هذي الصفحة تحدد طريقة الحساب.',
            'blocks' => [
                'كيف تنحسب؟ (مثال)' => [
                    'موظف خدم 7 سنوات وراتبه 5,000:',
                    'أول 5 سنوات × نص راتب = 2.5 راتب، والسنتين الباقية × راتب كامل = 2 راتب.',
                    'المجموع 4.5 راتب = 22,500.',
                    'لو هو اللي استقال: ياخذ جزء منها على حسب مدته (الجدول تحت). ولو الشركة أنهت عقده: ياخذها كاملة.',
                ],
                'الراتب اللي تنحسب عليه' => [
                    'الراتب الأساسي في آخر قسيمة له.',
                    'وإذا شغّلت «احسبها على الراتب مع البدلات»: ينضاف معه البدلات اللي معلّم عليها «تدخل في نهاية الخدمة» في بنود الرواتب (مثل السكن).',
                ],
                'وش يصير بعد الحساب؟' => [
                    'تنحسب المكافأة كمسودة ← تعتمدها ← تسويها على حساب الموظف ← تصرفها له من الخزينة.',
                    'الحسابات المحاسبية تنربط من «إعدادات الحسابات المحاسبية»، وهنا تشوفها بس.',
                ],
            ],
            'facts' => [
                'التعديل هنا يأثر على المكافآت الجديدة بس',
                'المكافآت المعتمدة ما تتغير',
            ],
        ])

        <form id="eosSettingsForm" action="{{ route('hr.eos.settings.update') }}" method="POST">
            @csrf

            <div class="card">
                <div class="card-body">

                    {{-- ═══════════ الاحتساب ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-power"></i>
                            <h6 class="stk-section__title">الاحتساب التلقائي والأجر</h6>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="eos_enabled">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            احسب المكافأة تلقائي لما تنهي خدمة موظف
                                            {!! $tip('إذا كان شغّال: أول ما تسجّل إنهاء خدمة موظف من بطاقته، النظام يحسب مكافأته ويحطها مسودة. إذا مطفي: تحسبها أنت من شاشة «نهاية الخدمة».') !!}
                                        </span>
                                        <span class="stk-switch__hint">وتقدر تحسبها يدوي من شاشة نهاية الخدمة في كل الأحوال.</span>
                                    </span>
                                    <input type="checkbox" id="eos_enabled" name="eos_enabled" value="1"
                                           @checked($on('eos_enabled', (bool) ($eosSettings['enabled'] ?? true)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="stk-switch" for="eos_include_default_allowances">
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">
                                            احسبها على الراتب مع البدلات
                                            {!! $tip('إذا كان شغّال: المكافأة تنحسب على الراتب الأساسي + البدلات اللي معلّم عليها «تدخل في نهاية الخدمة» في شاشة بنود الرواتب. إذا مطفي: على الأساسي بس.') !!}
                                        </span>
                                        <span class="stk-switch__hint">مثل بدل السكن — وأي بدل يدخل تحدده من «بنود الرواتب».</span>
                                    </span>
                                    <input type="checkbox" id="eos_include_default_allowances" name="eos_include_default_allowances" value="1"
                                           @checked($on('eos_include_default_allowances', (bool) ($eosSettings['include_default_allowances'] ?? true)))>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════════ الشرائح ═══════════ --}}
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-layers"></i>
                            <h6 class="stk-section__title">كم ياخذ الموظف عن كل سنة خدمة؟</h6>
                                <span class="hr-hover-panel">
                                    <button type="button" class="hr-hover-panel__btn" aria-label="جرّب بنفسك" title="جرّب بنفسك: مرّر المؤشر هنا واكتب راتب ومدة خدمة">!</button>
                                    <div class="hr-hover-panel__pop">
                        {{-- مثالٌ حيّ: يُحسب من القيم المكتوبة في الحقول أعلاه لحظةَ تغييرها --}}
                        <div class="eos-example" id="eosExample">
                            <div class="eos-example__head">
                                <i class="feather icon-play-circle"></i>
                                <strong>جرّب بنفسك</strong>
                                <span class="stk-help mb-0">اكتب راتب ومدة خدمة، ويطلع لك كم يستحق بالأرقام اللي فوق.</span>
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4 col-6">
                                    <label for="exSalary" class="mb-25">راتبه الشهري</label>
                                    <input type="number" min="0" step="1" id="exSalary" class="form-control" value="5000" data-no-save>
                                </div>
                                <div class="col-md-4 col-6">
                                    <label for="exYears" class="mb-25">كم سنة خدم؟</label>
                                    <input type="number" min="0" step="0.5" id="exYears" class="form-control" value="7" data-no-save>
                                </div>
                            </div>
                            <ol class="eos-example__steps" id="exSteps"></ol>
                            <div class="eos-example__totals">
                                <div class="eos-example__total">
                                    <span>لو الشركة أنهت عقده ياخذ</span>
                                    <b id="exFull">—</b>
                                </div>
                                <div class="eos-example__total">
                                    <span>لو هو استقال بنفسه ياخذ</span>
                                    <b id="exResign">—</b>
                                    <small id="exResignWhy"></small>
                                </div>
                            </div>
                        </div>
                                    </div>
                                </span>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="eos_first_bracket_years" class="stk-req">
                                    عدد السنوات الأولى
                                    {!! $tip('أول كم سنة خدمة لها حساب خاص (الحقل اللي جنبه). مثلاً 5 = أول 5 سنوات. نظام العمل السعودي: 5.') !!}
                                </label>
                                <input type="number" step="0.01" min="0" max="50" required
                                       id="eos_first_bracket_years" name="eos_first_bracket_years"
                                       class="form-control @error('eos_first_bracket_years') is-invalid @enderror"
                                       value="{{ old('eos_first_bracket_years', $eosSettings['rules']['first_bracket_years'] ?? 5) }}">
                                @error('eos_first_bracket_years')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label for="eos_first_months_per_year" class="stk-req">
                                    كم راتب عن كل سنة منها؟
                                    {!! $tip('عن كل سنة من السنوات الأولى ياخذ كم راتب؟ 0.5 = نص راتب عن كل سنة. نظام العمل السعودي: 0.5.') !!}
                                </label>
                                <input type="number" step="0.01" min="0" max="12" required
                                       id="eos_first_months_per_year" name="eos_first_months_per_year"
                                       class="form-control @error('eos_first_months_per_year') is-invalid @enderror"
                                       value="{{ old('eos_first_months_per_year', $eosSettings['rules']['first_months_per_year'] ?? 0.5) }}">
                                @error('eos_first_months_per_year')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label for="eos_later_months_per_year" class="stk-req">
                                    كم راتب عن كل سنة بعدها؟
                                    {!! $tip('عن كل سنة بعد السنوات الأولى ياخذ كم راتب؟ 1 = راتب كامل عن كل سنة. نظام العمل السعودي: 1.') !!}
                                </label>
                                <input type="number" step="0.01" min="0" max="12" required
                                       id="eos_later_months_per_year" name="eos_later_months_per_year"
                                       class="form-control @error('eos_later_months_per_year') is-invalid @enderror"
                                       value="{{ old('eos_later_months_per_year', $eosSettings['rules']['later_months_per_year'] ?? 1) }}">
                                @error('eos_later_months_per_year')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex align-items-center flex-wrap mb-50" style="gap: .4rem">
                            <strong class="me-1">لو الموظف استقال بنفسه ياخذ:</strong>
                            @php
                                $prev = 0;
                                // «سنة / سنتين / 3 سنين» — العدد بكلامٍ يُقرأ
                                $yrs = function ($n) {
                                    $n = (float) $n;
                                    $t = rtrim(rtrim(number_format($n, 1), '0'), '.');
                                    return $n == 1 ? 'سنة' : ($n == 2 ? 'سنتين' : $t.' سنين');
                                };
                            @endphp
                            @foreach ($eosSettings['bands'] ?? [] as $band)
                                <span class="stk-state stk-state--neutral">
                                    @if ($band['max_years'] === null)
                                        خدم {{ $yrs($prev) }} أو أكثر
                                    @elseif ($prev <= 0)
                                        خدم أقل من {{ $yrs($band['max_years']) }}
                                    @else
                                        خدم من {{ $yrs($prev) }} لأقل من {{ $yrs($band['max_years']) }}
                                    @endif
                                    ← {{ $fraction($band['fraction'] ?? 0) }}
                                </span>
                                @php $prev = (float) ($band['max_years'] ?? $prev); @endphp
                            @endforeach
                            {!! $tip('هذا بس إذا الموظف هو اللي استقال (المادة 85 من نظام العمل). إذا الشركة هي اللي أنهت عقده ياخذ المكافأة كاملة. هذي النسب ثابتة بالنظام وما تتعدل من هنا.') !!}
                        </div>

                    </div>

                    {{-- ═══════════ الحسابات (للقراءة) ═══════════ --}}
                    @include('hr::salaries.sitting.partials.linked-accounts', [
                        'rows' => $accountRows,
                        'title' => 'الحسابات',
                        'focus' => \App\Support\PayrollAccounts::EOS_EXPENSE,
                        'hints' => [
                            \App\Support\PayrollAccounts::EOS_EXPENSE => 'هنا تنسجل المكافأة كمصروف على الشركة لما تعتمدها.',
                            \App\Support\PayrollAccounts::EOS_PROVISION => 'مبلغ محجوز للموظف لين تصرفه له: ينزل فيه وقت الاعتماد، ويطلع منه وقت التسوية.',
                        ],
                    ])
                </div>

                <div class="stk-form-foot">
                    <a href="{{ hasPermission('eos.index') ? route('hr.eos.index') : route('SalarySittings.index') }}" class="btn btn-outline-secondary">
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

@push('styles')
    <style>
        .eos-example {
            margin-top: 1rem;
            padding: .9rem 1rem;
            border: 1px dashed var(--app-border, #d8d6de);
            border-radius: var(--app-radius, .357rem);
            background: color-mix(in srgb, var(--app-primary, #7367f0) 4%, transparent);
        }
        .eos-example__head { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem .6rem; margin-bottom: .6rem; }
        .eos-example__head i { color: var(--app-primary, #7367f0); }
        .eos-example__steps { margin: .9rem 0 .7rem; padding-inline-start: 1.25rem; line-height: 2; }
        .eos-example__steps b { color: var(--app-text, #4b4b4b); }
        .eos-example__totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .6rem; }
        .eos-example__total {
            display: flex; flex-direction: column; gap: .15rem;
            padding: .6rem .8rem; border-radius: var(--app-radius, .357rem);
            background: var(--app-surface, #fff); border: 1px solid var(--app-border, #ebe9f1);
        }
        .eos-example__total span { font-size: .85rem; color: var(--app-muted, #6e6b7b); }
        .eos-example__total b { font-size: 1.25rem; color: var(--app-primary, #7367f0); }
        .eos-example__total small { color: var(--app-muted, #6e6b7b); }
    </style>
@endpush

@push('scripts')
    @include('hr::components.hover-panel')
    <script>
        /* المثال الحيّ: نفسُ معادلة الخدمة (EndOfServiceCalculator) — للشرح فقط، لا يُحفظ شيء */
        (function () {
            var BANDS = @json(array_values($eosSettings['bands'] ?? []));
            var $ = function (id) { return document.getElementById(id); };
            var fmt = function (n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', { maximumFractionDigits: 2 }); };

            function fractionFor(years) {
                for (var i = 0; i < BANDS.length; i++) {
                    var b = BANDS[i];
                    if (b.max_years === null || years < parseFloat(b.max_years)) { return parseFloat(b.fraction || 0); }
                }
                return 1;
            }

            function yrs(n) {
                n = Math.round(n * 100) / 100;
                return n === 1 ? 'سنة' : (n === 2 ? 'سنتين' : fmt(n) + ' سنين');
            }
            function share(f) {
                if (f <= 0) return 'ما ياخذ شيء';
                if (f >= 1) return 'ياخذها كاملة';
                if (Math.abs(f - 1 / 3) < 0.001) return 'ياخذ ثلثها';
                if (Math.abs(f - 2 / 3) < 0.001) return 'ياخذ ثلثينها';
                if (Math.abs(f - 0.5) < 0.001) return 'ياخذ نصها';
                return 'ياخذ ' + fmt(f * 100) + '% منها';
            }
            function rateText(m) {
                if (m === 0.5) return 'نص راتب';
                if (m === 1) return 'راتب كامل';
                return fmt(m) + ' من الراتب';
            }

            function calc() {
                var salary = parseFloat($('exSalary').value) || 0;
                var years = parseFloat($('exYears').value) || 0;
                var firstYears = parseFloat($('eos_first_bracket_years').value) || 0;
                var firstMonths = parseFloat($('eos_first_months_per_year').value) || 0;
                var laterMonths = parseFloat($('eos_later_months_per_year').value) || 0;

                var y1 = Math.min(years, firstYears);
                var y2 = Math.max(0, years - firstYears);
                var part1 = y1 * firstMonths * salary;
                var part2 = y2 * laterMonths * salary;
                var full = part1 + part2;
                var frac = fractionFor(years);

                var steps = [];
                if (y1 > 0) {
                    steps.push('أول ' + yrs(y1) + ': عن كل سنة ' + rateText(firstMonths) + ' (' + fmt(firstMonths * salary) + ') ← '
                        + fmt(firstMonths * salary) + ' × ' + fmt(y1) + ' = <b>' + fmt(part1) + '</b>');
                }
                if (y2 > 0) {
                    var y2Label = yrs(y2);
                    steps.push((/^[0-9]/.test(y2Label) ? 'الـ' : 'ال') + y2Label + ' اللي بعدها: عن كل سنة ' + rateText(laterMonths) + ' (' + fmt(laterMonths * salary) + ') ← '
                        + fmt(laterMonths * salary) + ' × ' + fmt(y2) + ' = <b>' + fmt(part2) + '</b>');
                }
                if (steps.length > 1) {
                    steps.push('المجموع: ' + fmt(part1) + ' + ' + fmt(part2) + ' = <b>' + fmt(full) + '</b>');
                }
                if (!steps.length) {
                    steps.push('اكتب الراتب وعدد سنين الخدمة.');
                }
                $('exSteps').innerHTML = steps.map(function (t) { return '<li>' + t + '</li>'; }).join('');

                $('exFull').textContent = fmt(full);
                $('exResign').textContent = fmt(full * frac);
                $('exResignWhy').textContent = 'خدم ' + yrs(years) + ' ← ' + share(frac) + ' (حسب جدول الاستقالة فوق)';
            }

            ['exSalary', 'exYears', 'eos_first_bracket_years', 'eos_first_months_per_year', 'eos_later_months_per_year'].forEach(function (id) {
                var el = $(id);
                if (el) { el.addEventListener('input', calc); }
            });
            calc();
        })();
    </script>
    <script>
        /* تفعيلُ الاحتساب التلقائي والحسابان غير مربوطين: تنبيهٌ قبل الحفظ لا منع */
        (function () {
            var toggle = document.getElementById('eos_enabled');
            var form = document.getElementById('eosSettingsForm');
            var unlinked = @json($eosUnlinked);
            if (!toggle || !form || !unlinked) return;
            form.addEventListener('submit', function (e) {
                if (toggle.checked && !form.dataset.confirmed) {
                    if (!window.confirm('حسابات نهاية الخدمة مو مربوطة في إعدادات الحسابات المحاسبية — المكافأة تنحسب لكن ما تنعتمد لين تربطها. تكمل الحفظ؟')) {
                        e.preventDefault();
                        return;
                    }
                    form.dataset.confirmed = '1';
                }
            });
        })();
    </script>
@endpush
