@extends('sales::master')

{{-- إعدادات نظام الأهداف — مبيعاتٍ وتحصيلات.

     **العطب الأصلي:** كانت أنماط الشاشة كلّها — مئتا سطر: `pro-card`،
     `pretty-switch`، `switch-row`، `type-tabs`، `page-header-strip` —
     داخل `@section('css')`، و`sales::master` لا يعرض هذا القسم أصلاً:
     فيه `@stack('styles')` وحدها ولا `@yield('css')`. فلم يكن شيءٌ من
     ذلك يصل إلى الصفحة: البطاقات بلا حدود، والتبويبات روابطُ عارية،
     ومفاتيح التبديل مربّعاتٌ خام بجانب `span` فارغ. وهذا ما يبدو
     «شريطاً جانبياً معطوباً» في البند 4 من تقرير الإصلاح.

     الآن: `sales::partials.settings-theme` — يحقن نمطه داخل المحتوى
     بـ`@once`، فلا يعتمد على قسمٍ لا يعرضه القالب الأب.

     وكلّ نصٍّ في الشاشة كان مكتوباً عربيّاً في الوسم؛ صار من
     `sales_settings.targets`. --}}

@section('title', __('sales_settings.targets.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        $current = $targetSettings[$activeType];
        $isLastDay = (int) old('settlement_day', $current->settlement_day ?? 1) === 0;
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @permission('sales.target_settings.update')
                <button type="submit" form="targetSettings" class="btn btn-primary">
                    <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                </button>
            @endpermission

            <a href="{{ route('sales-targets.index') }}" class="btn btn-outline-primary">
                <i class="feather icon-target"></i> {{ __('sales_settings.targets.back_to_targets') }}
            </a>

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.targets.title'),
            'icon' => 'target',
            'sub' => __('sales_settings.targets.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.targets.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-targets.settings',
            'open' => false,
            'title' => __('sales_targets.guide.settings.title'),
            'lead' => __('sales_targets.guide.settings.lead'),
            'blocks' => [
                [
                    __('sales_targets.guide.settings.a'),
                    __('sales_targets.guide.settings.b'),
                    __('sales_targets.guide.settings.c'),
                ],
            ],
        ])

        {{-- العمولة تُصرَف بمبلغ من خزينة إلى حساب، فالعملة والحسابات
             والفترة المحاسبية كلّها تخصّ هذه الشاشة --}}
        @include('sales::components.system-context', [
            'only' => ['currency', 'accounts', 'period', 'notifications'],
        ])

        {{-- ══════════ تبويبات الأنواع ══════════ --}}
        <div class="sl-group">
            <div class="sl-tabs">
                @foreach (\App\Models\TargetSetting::supportedTypes() as $key => $label)
                    <a href="{{ route('sales-targets.settings', ['type' => $key]) }}"
                       class="sl-tab {{ $activeType === $key ? 'is-active' : '' }}"
                       @if ($activeType === $key) aria-current="page" @endif>
                        <i class="feather icon-{{ $key === 'collections' ? 'dollar-sign' : 'trending-up' }}"></i>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <form id="targetSettings" method="POST"
              action="{{ route('sales-targets.settings.update', ['type' => $activeType]) }}">
            @csrf

            {{-- ══════════ عام ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-power"></i>
                        {{ __('sales_settings.targets.general', ['type' => $current->type_label]) }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="sl-switch-row">
                        @include('sales::components.switch', [
                            'name' => 'enabled',
                            'checked' => (bool) old('enabled', $current->enabled),
                            'title' => __('sales_settings.targets.enabled', ['type' => $current->type_label]),
                            'hint' => __('sales_settings.targets.enabled_hint'),
                        ])
                    </div>

                    <div class="row mt-1">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group mb-0">
                                <label for="calculation_method">{{ __('sales_settings.targets.calculation_method') }}</label>

                                <select id="calculation_method" name="calculation_method" class="form-control">
                                    @foreach ($calcMethods as $method)
                                        <option value="{{ $method }}"
                                            @selected(old('calculation_method', $current->calculation_method) === $method)>
                                            {{ __('sales_settings.targets.methods.'.$method) }}
                                        </option>
                                    @endforeach
                                </select>

                                <span class="sl-help">{{ __('sales_settings.targets.calculation_method_hint') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ العمولة ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-dollar-sign"></i> {{ __('sales_settings.targets.commission') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="commission_account_id">{{ __('sales_settings.targets.commission_account') }}</label>

                                <select id="commission_account_id" name="commission_account_id" class="form-control sl-select2"
                                        data-placeholder="{{ __('sales_settings.targets.account_placeholder') }}">
                                    <option value="">{{ __('sales_settings.ui.none') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}"
                                            @selected((string) old('commission_account_id', $current->commission_account_id) === (string) $account->id)>
                                            {{ $account->code ? $account->code.' — ' : '' }}{{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <span class="sl-help">{{ __('sales_settings.targets.commission_account_hint') }}</span>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="default_commission_treasury_id">{{ __('sales_settings.targets.default_treasury') }}</label>

                                <select id="default_commission_treasury_id" name="default_commission_treasury_id"
                                        class="form-control sl-select2"
                                        data-placeholder="{{ __('sales_settings.targets.account_placeholder') }}">
                                    <option value="">{{ __('sales_settings.ui.none') }}</option>
                                    @foreach ($treasuries as $treasury)
                                        <option value="{{ $treasury->id }}"
                                            @selected((string) old('default_commission_treasury_id', $current->default_commission_treasury_id) === (string) $treasury->id)>
                                            {{ $treasury->code ? $treasury->code.' — ' : '' }}{{ $treasury->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <span class="sl-help">{{ __('sales_settings.targets.default_treasury_hint') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="sl-switch-row" style="border-bottom:0">
                        @include('sales::components.switch', [
                            'name' => 'pay_from_employee_treasury',
                            'checked' => (bool) old('pay_from_employee_treasury', $current->pay_from_employee_treasury),
                            'title' => __('sales_settings.targets.pay_from_employee_treasury'),
                            'hint' => __('sales_settings.targets.pay_from_employee_treasury_hint'),
                        ])
                    </div>
                </div>
            </div>

            {{-- ══════════ التسوية المجدولة ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-clock"></i> {{ __('sales_settings.targets.settlement') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="sl-switch-row">
                        @include('sales::components.switch', [
                            'name' => 'auto_settlement_enabled',
                            'checked' => (bool) old('auto_settlement_enabled', $current->auto_settlement_enabled),
                            'title' => __('sales_settings.targets.auto_settlement'),
                            'hint' => __('sales_settings.targets.auto_settlement_hint'),
                        ])
                    </div>

                    <div class="row mt-1">
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="settlement_frequency">{{ __('sales_settings.targets.frequency') }}</label>

                                <select id="settlement_frequency" name="settlement_frequency" class="form-control">
                                    <option value="">{{ __('sales_settings.ui.none') }}</option>
                                    @foreach ($frequencies as $frequency)
                                        <option value="{{ $frequency }}"
                                            @selected(old('settlement_frequency', $current->settlement_frequency) === $frequency)>
                                            {{ __('sales_settings.targets.frequencies.'.$frequency) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="settlement_day">{{ __('sales_settings.targets.day') }}</label>

                                <input type="number" id="settlement_day" name="settlement_day" min="0" max="31"
                                       class="form-control"
                                       value="{{ old('settlement_day', $current->settlement_day) }}"
                                       {{ $isLastDay ? 'disabled' : '' }}
                                       placeholder="{{ __('sales_settings.targets.day_placeholder') }}">

                                <label class="d-flex align-items-center mt-1" for="use_last_day" style="gap:.4rem;font-weight:600">
                                    <input type="checkbox" id="use_last_day" {{ $isLastDay ? 'checked' : '' }}>
                                    {{ __('sales_settings.targets.last_day') }}
                                </label>

                                <span class="sl-help">{{ __('sales_settings.targets.last_day_hint') }}</span>

                                {{-- معنى «اليوم» يتبع التكرار المختار — يُحدَّث من
                                     السكربت أدناه، ويُطبع هنا محسوباً للحالة المحفوظة
                                     حتى يظهر صحيحاً قبل أي تفاعل (وبلا جافاسكربت) --}}
                                <span class="sl-help" id="settlement_day_meaning">
                                    {{ __('sales_settings.targets.day_meaning.'.($current->settlement_frequency ?: 'monthly')) }}
                                </span>

                                <span class="sl-help" id="settlement_last_day_note" style="{{ $isLastDay ? '' : 'display:none' }}">
                                    {{ __('sales_settings.targets.last_day_period') }}
                                </span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="settlement_time">{{ __('sales_settings.targets.time') }}</label>

                                {{-- `Carbon::parse(null)` تعطي «الآن»، فالحقل كان يعرض
                                     ساعة فتح الصفحة حين لا وقت محفوظ --}}
                                <input type="time" id="settlement_time" name="settlement_time" class="form-control"
                                       value="{{ old('settlement_time', $current->settlement_time
                                            ? \Carbon\Carbon::parse($current->settlement_time)->format('H:i')
                                            : '') }}">
                            </div>
                        </div>

                        {{-- ما تُسوّيه الدورةُ بيومٍ رقميّ: المنقضية (الافتراضي) أو
                             الجارية حتى يوم التنفيذ. لا معنى له في الأسبوعيّ ولا مع
                             «آخر يوم»، فيُخفى حينها (السكربت أدناه) --}}
                        <div class="col-md-8 col-sm-12" id="settlement_scope_wrap">
                            <div class="form-group">
                                <label for="settlement_scope">{{ __('sales_settings.targets.scope') }}</label>
                                <select id="settlement_scope" name="settlement_scope" class="form-control">
                                    @foreach ([\App\Models\TargetSetting::SCOPE_PREVIOUS, \App\Models\TargetSetting::SCOPE_CURRENT] as $scope)
                                        <option value="{{ $scope }}" @selected(old('settlement_scope', $current->settlementScope()) === $scope)>
                                            {{ __('sales_settings.targets.scopes.'.$scope) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="sl-help">{{ __('sales_settings.targets.scope_hint') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- ما سيقع فعلاً حسب المحفوظ: الموعدُ القادم والفترةُ
                         التي ستُسوَّى فيه. «اليوم ٢٦» يُسوّي الشهرَ المنقضي،
                         فمن أنشأ هدفه في الشهر نفسه يرى هنا — قبل أن ينتظر
                         شهراً — أنّ أوّل صرفٍ في الموعد التالي --}}
                    @php
                        $next = $nextRuns[$activeType] ?? ['at' => null, 'period' => null, 'reason' => 'auto_off'];
                        // التسوية مفعّلة لكنّ شيئاً يمنعها: تحذيرٌ أحمر لا صندوقٌ رماديّ
                        // يُقرأ كأنّه ملاحظة — مرّ موعدُ صرفٍ كاملٌ ولم يُنتبَه له
                        $nextBlocked = ! $next['at']
                            && in_array($next['reason'], ['no_frequency', 'no_account', 'cron_job_missing', 'cron_job_disabled'], true);
                    @endphp

                    <div class="alert {{ $next['at'] ? 'alert-info' : ($nextBlocked ? 'alert-danger' : 'alert-secondary') }} mb-1" id="settlement_next_run">
                        <i class="feather icon-calendar"></i>
                        @if ($next['at'])
                            <strong>{{ __('sales_settings.targets.next_run') }}:</strong>
                            {{ $next['at']->format('Y-m-d H:i') }}
                            — {{ __('sales_settings.targets.next_run_period', ['from' => $next['period'][0], 'to' => $next['period'][1]]) }}
                            <br>
                            <small>{{ __('sales_settings.targets.next_run_note') }}</small>
                        @else
                            {{ __('sales_settings.targets.next_run_none.'.($next['reason'] ?: 'auto_off')) }}
                            @if (in_array($next['reason'], ['cron_job_disabled', 'cron_job_missing'], true))
                                <a href="{{ route('CronJobs.index') }}" class="alert-link">{{ __('sales_settings.targets.cron_job_link') }}</a>
                            @endif
                        @endif
                    </div>

                    {{-- نبضُ المجدول: تسويةٌ مجدولة على خادمٍ بلا مجدول لا تقع أبداً --}}
                    {{-- سطرُ «تحتاج مجدول لارافيل… schedule:run» أُزيل (2026-09-27) بطلب
                         صاحب النظام: المجدولُ الداخليّ يُغني عنه، وحالُ المجدول
                         مذكورةٌ في الصندوق نفسِه. --}}
                    <div class="alert alert-{{ $schedulerHealth['color'] ?? 'secondary' }} mb-0" id="settlement_scheduler_health">
                        <i class="feather icon-activity"></i>
                        <strong>{{ __('sales_settings.targets.scheduler_status') }}:</strong>
                        {{ $schedulerHealth['label'] ?? '' }} — {{ $schedulerHealth['message'] ?? '' }}
                    </div>
                </div>
            </div>

            {{-- ══════════ الإشعارات والاعتماد ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-bell"></i> {{ __('sales_settings.targets.notifications') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="sl-switch-row">
                        @include('sales::components.switch', [
                            'name' => 'notify_on_settlement',
                            'checked' => (bool) old('notify_on_settlement', $current->notify_on_settlement),
                            'title' => __('sales_settings.targets.notify_on_settlement'),
                            'hint' => __('sales_settings.targets.notify_on_settlement_hint'),
                        ])
                    </div>

                    <div class="sl-switch-row">
                        @include('sales::components.switch', [
                            'name' => 'require_approval',
                            'checked' => (bool) old('require_approval', $current->require_approval),
                            'title' => __('sales_settings.targets.require_approval'),
                            'hint' => __('sales_settings.targets.require_approval_hint'),
                        ])
                    </div>
                </div>

                <div class="sl-form-foot">
                    <span class="sl-help" style="margin:0">
                        @if ($current->updated_at && $current->updated_by)
                            <i class="feather icon-clock"></i>
                            {{ __('sales_settings.ui.updated_at') }}
                            {{ \App\Support\LanguageSettings::formatDate($current->updated_at, true) }}
                            @if ($current->updatedBy)
                                — {{ $current->updatedBy->name }}
                            @endif
                        @endif
                    </span>

                    <div class="d-flex" style="gap:.5rem">
                        <a href="{{ route('sales-targets.settings', ['type' => $activeType]) }}"
                           class="btn btn-outline-secondary">
                            {{ __('sales_settings.ui.cancel') }}
                        </a>

                        @permission('sales.target_settings.update')
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i>
                                {{ __('sales_settings.targets.save', ['type' => $current->type_label]) }}
                            </button>
                        @endpermission
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            // Select2 من أصول التطبيق: `sales::master` يحمّلها قبل هذا القسم.
            // كان القالب يجلب نسخةً ثانيةً منها من شبكة توزيع
            if ($.fn.select2) {
                $('.sl-select2').each(function () {
                    $(this).select2({
                        width: '100%',
                        placeholder: $(this).data('placeholder'),
                        dir: @json(($languageIsRtl ?? true) ? 'rtl' : 'ltr'),
                        allowClear: true
                    });
                });
            }

            var lastDay = document.getElementById('use_last_day');
            var dayInput = document.getElementById('settlement_day');
            var form = document.getElementById('targetSettings');

            // معنى «يوم التنفيذ» يختلف باختلاف التكرار — يُعرَض بدل أن يُترك للتخمين
            var meanings = @json(__('sales_settings.targets.day_meaning'));
            var freqSelect = document.getElementById('settlement_frequency');
            var meaningBox = document.getElementById('settlement_day_meaning');
            var lastDayNote = document.getElementById('settlement_last_day_note');

            var scopeWrap = document.getElementById('settlement_scope_wrap');

            function refreshMeaning() {
                var freq = (freqSelect && freqSelect.value) || 'monthly';
                if (meaningBox) {
                    meaningBox.textContent = meanings[freq] || '';
                }
                if (lastDayNote && lastDay) {
                    lastDayNote.style.display = lastDay.checked ? '' : 'none';
                }
                // النطاق يخصّ اليومَ الرقميّ في الشهريّ/الربعيّ/السنويّ وحده
                if (scopeWrap) {
                    scopeWrap.style.display = (freq === 'weekly' || (lastDay && lastDay.checked)) ? 'none' : '';
                }
            }

            if (freqSelect) {
                freqSelect.addEventListener('change', refreshMeaning);
            }

            refreshMeaning();

            if (lastDay && dayInput) {
                lastDay.addEventListener('change', refreshMeaning);

                lastDay.addEventListener('change', function () {
                    if (lastDay.checked) {
                        dayInput.dataset.prev = dayInput.value;
                        dayInput.value = 0;
                        dayInput.disabled = true;
                    } else {
                        dayInput.disabled = false;
                        dayInput.value = dayInput.dataset.prev || '1';
                    }
                });

                // الحقل المعطَّل لا يُرسَل، فيُفكّ تعطيله لحظةَ الإرسال حتى
                // تصل القيمة 0 التي تعني «آخر يوم»
                form.addEventListener('submit', function () {
                    if (lastDay.checked) {
                        dayInput.disabled = false;
                        dayInput.value = 0;
                    }
                });
            }
        });
    </script>
@endsection
