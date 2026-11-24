{{--
    نموذج الهدف — إنشاءً وتعديلاً، للمبيعات وللتحصيلات: قالبٌ واحد بدل
    أربعة (`create` و`edit` مضروبةً في النوعين، ٩٥ ألف بايت مكرّرة).

    **ما أُصلح هنا، لا نُقل فحسب:**

    1. *مستويات العمولة كانت تُنشأ ولا تُعدَّل.* شاشة التعديل القديمة لم
       تكن تعرض المستويات أصلاً، و`update()` لم يمسّها — فمن أخطأ في
       نسبةٍ عند الإنشاء لا سبيل له إلّا حذف الهدف وإعادته. صارت هنا
       بنفس مُكرِّر الإنشاء، والمتحكّم يزامنها.

    2. *`old()` لم تكن مقروءة.* فشلُ التحقّق كان يُعيد الشاشة فارغةً من
       المستويات والموظفين المختارين، فيُعاد الإدخال من أوّله.

    3. *الأرقام بلا عملة.* «قيمة الهدف» كانت `number` عارياً بلا رمز ولا
       تلميحٍ بأيّ عملة تُدخَل. صار رمز العملة ملصقاً في الحقل نفسه من
       `CurrencySettings`.

    المتغيّرات:
      $screen             وصف النوع — TargetScreen::config()
      $target             الهدف عند التعديل، أو null عند الإنشاء
      $employees          الموظفون المؤهَّلون (مصفوفات)
      $selectedEmployees  معرّفات المستخدمين المُسنَد إليهم
--}}
@php
    $r = $screen['routes'];
    $isEdit = ! empty($target);

    $action = $isEdit ? route($r['update'], $target->id) : route($r['store']);

    // ترتيب الأولوية في كل حقل: ما أعاده التحقّق، ثمّ المحفوظ، ثمّ الفراغ
    $selected = collect(old('employees', $selectedEmployees ?? []))->map(fn ($v) => (int) $v)->all();

    $tiers = old('commission_tiers');
    if ($tiers === null) {
        $tiers = $isEdit
            ? $target->commissionTiers
                ->map(fn ($t) => [
                    'achievement_percentage' => $t->achievement_percentage,
                    'commission_percentage' => $t->commission_percentage,
                ])->values()->all()
            : [];
    }

    $period = old('commission_period', $isEdit ? $target->commission_period : 'monthly');
    $autoWeekly = (bool) old('auto_calculate_weekly', $isEdit ? $target->auto_calculate_weekly : false);
    $currencySymbol = \App\Support\CurrencySettings::symbol();

    // مدّةُ الهدف وطريقةُ صرف عمولته
    $startDate = old('start_date', $isEdit ? optional($target->start_date)->format('Y-m-d') : '');
    $endDate = old('end_date', $isEdit ? optional($target->end_date)->format('Y-m-d') : '');
    $settlementMode = old('settlement_mode', ($isEdit ? $target->settlement_mode : null) ?: 'period_end');

    // قالبُ الصفّ الجديد يُصاغ هنا لا داخل `@json(view(...))`: مُحلِّل
    // وسائط Blade يقطع الوسيط عند أوّل فاصلةٍ يجدها في مصفوفةٍ متعدّدة
    // الأسطر، فيخرج `json_encode(view('…', ['index' => '__I__',
    // 'achievement' => null)` — قوسٌ لا يُغلق، وصفحةُ خطأٍ في التصريف.
    $tierTemplate = view('sales::targets.partials.tier-row', [
        'index' => '__I__',
        'achievement' => null,
        'commission' => null,
    ])->render();
@endphp

<div class="content-body sl-scope">

    @section('page-actions')
        <button type="submit" form="targetForm" class="btn btn-primary">
            <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
        </button>

        <a href="{{ route($r['index']) }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
            {{ __('sales_targets.actions.back_to_list') }}
        </a>
    @endsection

    @include('sales::components.page-head', [
        'title' => $isEdit ? $screen['edit_title'] : $screen['new'],
        'icon' => $screen['icon'],
        'sub' => $screen['achievement_source'],
        'crumbs' => [
            __('sales_settings.ui.home') => route('dashboard'),
            $screen['title'] => route($r['index']),
            ($isEdit ? $screen['edit_title'] : $screen['new']) => null,
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')
    @include('sales::components.form-errors')

    {{-- النوعُ الموقوف يردّ الحفظ — يُقال قبل ملء النموذج لا بعده --}}
    @unless (\App\Models\TargetSetting::for($screen['settings_type'])->enabled)
        <div class="alert alert-warning">
            <i class="feather icon-pause-circle me-1"></i>
            {{ __('sales_targets.messages.type_disabled') }}
            <a href="{{ $screen['settings_url'] }}" class="alert-link">
                {{ __('sales_targets.actions.settings') }}
            </a>
        </div>
    @endunless

    @include('sales::components.system-context', [
        'only' => ['currency', 'accounts', 'period'],
    ])

    <form id="targetForm" method="POST" action="{{ $action }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="row">
            {{-- ══════════ بيانات الهدف ══════════ --}}
            <div class="col-lg-8 col-md-12">
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-file-text"></i> {{ __('sales_settings.targets.general') }}
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">
                                {{ __('sales_targets.fields.name') }} <span class="sl-req">*</span>
                            </label>
                            <input type="text" id="name" name="name" required maxlength="255"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $isEdit ? $target->name : '') }}"
                                   placeholder="{{ __('sales_targets.fields.name_placeholder') }}">
                            @error('name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label for="target_value">
                                {{ __('sales_targets.fields.target_value') }} <span class="sl-req">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" id="target_value" name="target_value" required
                                       step="0.01" min="0" max="9999999999999.99"
                                       class="form-control @error('target_value') is-invalid @enderror"
                                       value="{{ old('target_value', $isEdit ? $target->target_value : '') }}">
                                {{-- رمز العملة من إعدادات النظام: كان الحقل رقماً
                                     عارياً لا يقول بأيّ عملة يُدخَل المبلغ --}}
                                <div class="input-group-append">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                </div>
                            </div>
                            <small class="sl-help">{{ __('sales_targets.fields.target_value_hint') }}</small>
                            @error('target_value')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label for="description">{{ __('sales_targets.fields.description') }}</label>
                            <textarea id="description" name="description" rows="3"
                                      class="form-control @error('description') is-invalid @enderror"
                                      placeholder="{{ __('sales_targets.fields.description_placeholder') }}">{{ old('description', $isEdit ? $target->description : '') }}</textarea>
                            @error('description')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                {{-- ══════════ مدّة الهدف وصرف العمولة ══════════
                     الهدفُ كان بلا مدّة: تُصرف عمولتُه عن دورةِ «إعدادات
                     الأهداف» مهما كانت — شهرٍ قد يسبق بدايةَ الهدف نفسِه.
                     المدّةُ هنا تحدّ ما يُقاس وما يُصرف عنه. --}}
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-calendar"></i> {{ __('sales_targets.duration.title') }}
                        </h6>
                    </div>

                    <div class="card-body">
                        <p class="sl-help mb-2">
                            <i class="feather icon-info"></i> {{ __('sales_targets.duration.hint') }}
                        </p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">{{ __('sales_targets.duration.start') }}</label>
                                    <input type="date" id="start_date" name="start_date"
                                           class="form-control @error('start_date') is-invalid @enderror"
                                           value="{{ $startDate }}">
                                    @error('start_date')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">{{ __('sales_targets.duration.end') }}</label>
                                    <input type="date" id="end_date" name="end_date"
                                           class="form-control @error('end_date') is-invalid @enderror"
                                           value="{{ $endDate }}">
                                    @error('end_date')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- المدّةُ تُحسب من «تاريخ البداية»؛ وإن كان فارغاً فمن أوّل الشهر الجاري --}}
                        <div class="sl-duration__quick mb-3">
                            <span class="sl-help">{{ __('sales_targets.duration.quick') }}</span>
                            @foreach ([1, 3, 6, 12] as $months)
                                <button type="button" class="btn btn-sm btn-outline-primary" data-duration-months="{{ $months }}">
                                    {{ __('sales_targets.duration.months.'.$months) }}
                                </button>
                            @endforeach
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-duration-clear>
                                {{ __('sales_targets.duration.clear') }}
                            </button>
                        </div>

                        <div class="form-group mb-0" id="settlementModeRow">
                            <label for="settlement_mode">{{ __('sales_targets.duration.mode') }}</label>
                            <select id="settlement_mode" name="settlement_mode" class="form-control">
                                @foreach (['period_end', 'cycle'] as $mode)
                                    <option value="{{ $mode }}" @selected($settlementMode === $mode)>
                                        {{ __('sales_targets.duration.modes.'.$mode) }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="sl-help" data-hint-keep data-mode-hint="period_end">{{ __('sales_targets.duration.modes_hint.period_end') }}</small>
                            <small class="sl-help" data-hint-keep data-mode-hint="cycle">{{ __('sales_targets.duration.modes_hint.cycle') }}</small>
                        </div>

                        <p class="sl-help mb-0 mt-2 d-none" data-hint-keep id="durationSummary"></p>
                    </div>
                </div>

                {{-- ══════════ مستويات العمولة ══════════ --}}
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-layers"></i> {{ __('sales_targets.tiers.title') }}
                        </h6>

                        <button type="button" class="btn btn-sm btn-outline-primary" id="addTier">
                            <i class="feather icon-plus"></i> {{ __('sales_targets.tiers.add') }}
                        </button>
                    </div>

                    <div class="card-body">
                        <p class="sl-help mb-3">
                            <i class="feather icon-info"></i> {{ __('sales_targets.tiers.hint') }}
                        </p>

                        <div id="tiersBody">
                            @foreach ($tiers as $i => $tier)
                                @include('sales::targets.partials.tier-row', [
                                    'index' => $i,
                                    'achievement' => $tier['achievement_percentage'] ?? null,
                                    'commission' => $tier['commission_percentage'] ?? null,
                                ])
                            @endforeach
                        </div>

                        <div id="tiersEmpty" class="{{ count($tiers) ? 'd-none' : '' }}">
                            @include('sales::components.empty-state', [
                                'icon' => 'layers',
                                'title' => __('sales_targets.tiers.empty'),
                                'hint' => __('sales_targets.tiers.empty_hint'),
                            ])
                        </div>

                        @error('commission_tiers')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            {{-- ══════════ الضبط ══════════ --}}
            <div class="col-lg-4 col-md-12">
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-settings"></i> {{ __('sales_settings.ui.options') }}
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="form-group">
                            <label for="status">{{ __('sales_targets.fields.status') }}</label>
                            <select id="status" name="status" class="form-control">
                                @foreach (['active', 'inactive'] as $s)
                                    <option value="{{ $s }}"
                                        @selected(old('status', $isEdit ? $target->status : 'active') === $s)>
                                        {{ __('sales_settings.ui.'.$s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="commission_period">{{ __('sales_targets.fields.period') }}</label>
                            <select id="commission_period" name="commission_period" class="form-control">
                                @foreach (['monthly', 'weekly'] as $p)
                                    <option value="{{ $p }}" @selected($period === $p)>
                                        {{ __('sales_targets.fields.periods.'.$p) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- يظهر مع الدورية الأسبوعية وحدها --}}
                        <div id="autoWeeklyRow" class="{{ $period === 'weekly' ? '' : 'd-none' }}">
                            @include('sales::components.switch', [
                                'name' => 'auto_calculate_weekly',
                                'title' => __('sales_targets.fields.auto_weekly'),
                                'hint' => __('sales_targets.fields.auto_weekly_hint'),
                                'checked' => $autoWeekly,
                            ])
                        </div>
                    </div>
                </div>

                {{-- ══════════ الموظفون ══════════ --}}
                <div class="card sl-section">
                    <div class="sl-section__head">
                        <h6 class="sl-section__title">
                            <i class="feather icon-users"></i> {{ __('sales_targets.fields.employees') }}
                            <span class="sl-count" id="pickedCount">0</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <p class="sl-help mb-2">{{ __('sales_targets.fields.employees_hint') }}</p>

                        {{-- الفرعُ من الترويسة لا من هنا: القائمة أصلاً مقصورة على
                             فرع الشريط العلويّ وما تحته (`eligibleEmployees`) --}}
                        <div class="form-group">
                            <input type="text" id="employeeSearch" class="form-control"
                                   placeholder="{{ __('sales_settings.ui.search_placeholder') }}">
                        </div>

                        <div class="mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="pickAll">
                                <i class="feather icon-check-square"></i> {{ __('sales_settings.ui.yes') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="pickNone">
                                <i class="feather icon-square"></i> {{ __('sales_settings.ui.none') }}
                            </button>
                        </div>

                        <div class="sl-picker" id="employeeList">
                            @forelse ($employees as $employee)
                                <label class="sl-picker__row"
                                       data-name="{{ Str::lower($employee['name'].' '.($employee['job_title'] ?? '')) }}">
                                    <input type="checkbox" name="employees[]" value="{{ $employee['id'] }}"
                                           class="sl-picker__box"
                                           @checked(in_array((int) $employee['id'], $selected, true))>

                                    <span class="sl-picker__body">
                                        <span class="sl-picker__title">
                                            {{ $employee['name'] }}
                                            @if (! empty($employee['inactive']))
                                                <span class="sl-state sl-state--off">{{ __('sales_settings.ui.inactive') }}</span>
                                            @endif
                                        </span>
                                        <span class="sl-picker__sub">
                                            {{ $employee['job_title'] ?: __('sales_settings.ui.none') }}
                                            · {{ $employee['treasury_name'] }}
                                        </span>
                                    </span>
                                </label>
                            @empty
                                @include('sales::components.empty-state', [
                                    'icon' => 'users',
                                    'title' => __('sales_targets.messages.no_employees'),
                                    'hint' => __('sales_targets.fields.employees_hint'),
                                ])
                            @endforelse
                        </div>

                        @error('employees')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="sl-form-foot">
            <a href="{{ route($r['index']) }}" class="btn btn-outline-secondary">
                {{ __('sales_settings.ui.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
            </button>
        </div>
    </form>
</div>

@once
    <style>
        /* منتقي الموظفين — قائمةٌ مؤطَّرة تُمرَّر، لا شبكةُ بطاقاتٍ تُطيل
           الصفحة كلّما زاد عدد الموظفين */
        .sl-picker {
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid var(--app-border, rgba(0, 0, 0, .09));
            border-radius: var(--sl-r, 8px);
        }
        .sl-picker__row {
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            margin: 0;
            padding: .625rem .75rem;
            cursor: pointer;
            border-block-end: 1px solid var(--app-border, rgba(0, 0, 0, .06));
        }
        .sl-picker__row:last-child { border-block-end: 0; }
        .sl-picker__row:hover { background: var(--app-primary-soft, rgba(75, 123, 236, .07)); }
        .sl-picker__box { margin-block-start: .2rem; flex: none; }
        .sl-picker__body { display: flex; flex-direction: column; min-width: 0; }
        .sl-picker__title { font-weight: 600; color: var(--app-text, #1f2937); }
        .sl-picker__sub { font-size: .75rem; color: var(--app-muted, #6b7280); }

        /* أزرارُ المدّة السريعة */
        .sl-duration__quick { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; }

        /* صفّ مستوى العمولة */
        .sl-tier {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: .625rem;
            align-items: end;
            padding: .75rem;
            margin-block-end: .625rem;
            border: 1px solid var(--app-border, rgba(0, 0, 0, .09));
            border-radius: var(--sl-r, 8px);
            background: var(--app-surface, #fff);
        }
        .sl-tier__rule {
            grid-column: 1 / -1;
            font-size: .75rem;
            color: var(--app-muted, #6b7280);
            margin: 0;
        }
    </style>
@endonce

@section('scripts')
    <script>
        (function () {
            'use strict';

            // ═══ مستويات العمولة ═══
            var body = document.getElementById('tiersBody');
            var empty = document.getElementById('tiersEmpty');
            // القالب يأتي من الخادم فيبقى الترميز والنصوص في مكانٍ واحد
            var tpl = @json($tierTemplate);

            function refresh() {
                var rows = body.querySelectorAll('.sl-tier');
                empty.classList.toggle('d-none', rows.length > 0);

                // إعادة ترقيم الأسماء بعد كل حذف: ثغرةٌ في الفهارس تجعل
                // لارافيل يقرأ المصفوفة خريطةً لا قائمة
                rows.forEach(function (row, i) {
                    row.querySelectorAll('[name^="commission_tiers"]').forEach(function (input) {
                        input.name = input.name.replace(/commission_tiers\[[^\]]*\]/, 'commission_tiers[' + i + ']');
                    });
                });
            }

            document.getElementById('addTier').addEventListener('click', function () {
                var wrap = document.createElement('div');
                wrap.innerHTML = tpl.replace(/__I__/g, body.querySelectorAll('.sl-tier').length);
                body.appendChild(wrap.firstElementChild);
                refresh();
            });

            body.addEventListener('click', function (e) {
                var btn = e.target.closest('.sl-tier__remove');
                if (!btn) return;
                btn.closest('.sl-tier').remove();
                refresh();
            });

            refresh();

            // ═══ مدّة الهدف ═══
            var start = document.getElementById('start_date');
            var end = document.getElementById('end_date');
            var mode = document.getElementById('settlement_mode');
            var modeRow = document.getElementById('settlementModeRow');
            var summary = document.getElementById('durationSummary');
            var durationText = {
                days: @json(__('sales_targets.duration.summary')),
                none: @json(__('sales_targets.duration.none')),
            };

            function iso(date) {
                // بالتوقيت المحلّي: `toISOString()` تُرجع UTC فتُنقص يوماً شرقَ غرينتش
                return date.getFullYear() + '-'
                    + String(date.getMonth() + 1).padStart(2, '0') + '-'
                    + String(date.getDate()).padStart(2, '0');
            }

            function refreshDuration() {
                var has = !!start.value && !!end.value;

                // طريقةُ الصرف لا معنى لها بلا مدّة
                modeRow.classList.toggle('d-none', !has);
                modeRow.querySelectorAll('[data-mode-hint]').forEach(function (hint) {
                    hint.classList.toggle('d-none', hint.dataset.modeHint !== mode.value);
                });

                end.min = start.value || '';
                end.setCustomValidity(has && end.value < start.value ? @json(__('sales_targets.duration.end_before_start')) : '');

                summary.classList.remove('d-none');
                if (!has || end.value < start.value) {
                    summary.textContent = has ? '' : durationText.none;
                    return;
                }

                var days = Math.round((new Date(end.value) - new Date(start.value)) / 86400000) + 1;
                summary.textContent = durationText.days.replace(':days', days);
            }

            document.querySelectorAll('[data-duration-months]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var from = start.value ? new Date(start.value + 'T00:00:00') : new Date();
                    if (!start.value) {
                        from.setDate(1);
                        start.value = iso(from);
                    }

                    // نهايةُ المدّة اليومُ السابق لمرور الأشهر: من ١ يناير لستّة
                    // أشهر تنتهي ٣٠ يونيو لا ١ يوليو
                    var to = new Date(from.getFullYear(), from.getMonth() + parseInt(button.dataset.durationMonths, 10), from.getDate() - 1);
                    end.value = iso(to);
                    refreshDuration();
                });
            });

            document.querySelector('[data-duration-clear]').addEventListener('click', function () {
                start.value = '';
                end.value = '';
                refreshDuration();
            });

            [start, end, mode].forEach(function (field) {
                field.addEventListener('change', refreshDuration);
            });

            refreshDuration();

            // ═══ الدورية الأسبوعية ═══
            var period = document.getElementById('commission_period');
            var autoRow = document.getElementById('autoWeeklyRow');

            period.addEventListener('change', function () {
                autoRow.classList.toggle('d-none', period.value !== 'weekly');
            });

            // ═══ منتقي الموظفين ═══
            var list = document.getElementById('employeeList');
            var counter = document.getElementById('pickedCount');
            var search = document.getElementById('employeeSearch');

            function applyFilters() {
                var q = search.value.trim().toLowerCase();
                list.querySelectorAll('.sl-picker__row').forEach(function (row) {
                    row.classList.toggle('d-none', q !== '' && row.dataset.name.indexOf(q) === -1);
                });
            }

            function boxes() {
                return list ? list.querySelectorAll('.sl-picker__box') : [];
            }

            function countPicked() {
                var n = 0;
                boxes().forEach(function (b) { if (b.checked) n++; });
                counter.textContent = n;
            }

            if (list) {
                list.addEventListener('change', countPicked);

                document.getElementById('pickAll').addEventListener('click', function () {
                    // «الكل» يعني المرئي بعد التصفية، لا المخفيّ أيضاً —
                    // وإلّا اختار البحثُ ما لم يره المستخدم
                    list.querySelectorAll('.sl-picker__row:not(.d-none) .sl-picker__box')
                        .forEach(function (b) { b.checked = true; });
                    countPicked();
                });

                document.getElementById('pickNone').addEventListener('click', function () {
                    boxes().forEach(function (b) { b.checked = false; });
                    countPicked();
                });

                search.addEventListener('input', applyFilters);

                countPicked();
            }
        })();
    </script>
@endsection
