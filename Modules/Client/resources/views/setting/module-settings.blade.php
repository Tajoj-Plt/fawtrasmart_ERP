@extends('client::master')

{{--
    شاشة إعدادات وحدات العملاء الموحّدة.

    ستّة تبويبات في نموذجٍ واحد — العملاء، الزيارات، خطوط السير، المتابعة،
    الموظفون، الفواتير والديون — ابتلعت شاشةَ «عام» القديمة بحقولها
    الإضافية ونوعِ العميل.

    **لماذا نموذجٌ واحد لا نموذجٌ لكل تبويب؟** التبويبات هنا تصنيفٌ بصري
    لإعداداتٍ تُحفظ معاً في معاملة واحدة؛ ونموذجٌ لكلٍّ يعني أن من غيّر في
    تبويبين ثم حفظ يفقد أحدهما. والتبويب النشط يُحمل في `active_tab` فيعود
    المستخدم بعد الحفظ إلى حيث كان.

    القيم كلّها تصل في `$values` مفتاحاً بمفتاح — الخريطة نفسها التي يقرأ
    بها المتحكّم `FIELDS`، فلا يمكن أن يفترق اسمُ حقلٍ هنا عن اسمه هناك.
--}}

@section('title', __('client_settings.module_settings.title'))

@section('content')
    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    <style>
        /* التبويب زرٌّ لا رابط: التبويبات هنا أجزاءُ نموذجٍ واحد لا صفحات */
        .cl-scope button.cl-tab {
            background: none;
            border: 0;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-family: inherit;
        }

        .cl-scope .cl-tabpanel[hidden] { display: none; }

        .cl-scope .cl-tab__badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; padding: 0 .3rem;
            border-radius: 999px; font-size: .68rem; font-weight: 700;
            background: var(--app-primary-soft, rgba(115, 103, 240, .12));
            color: var(--app-primary, #7367f0);
        }

    </style>

    <div class="content-body cl-scope">

        @php
            $activeTab = in_array(request('tab'), \Modules\Client\Http\Controllers\ClientModuleSettingsController::TABS, true)
                ? request('tab')
                : 'clients';

            // أسماءٌ قصيرة عمداً: `@include` يمرّر متغيّرات هذا القالب كلَّها
            // إلى `components.switch`، و`$value`/`$hint`/`$title` أسماءُ
            // وسائطه — فمُغلَقٌ باسم `$value` هنا يُسرَّب إليه ويُسقط الشاشة.
            $val = fn (string $k, $d = null) => old($k, $values[$k] ?? $d);
            $sw = fn (string $k) => (bool) old($k, $values[$k] ?? false);
        @endphp

        @section('page-actions')
            @permission('clients_module_settings_update')
                <button type="submit" form="clientModuleSettings" class="btn btn-primary">
                    <i class="feather icon-save"></i> {{ __('client_settings.ui.save') }}
                </button>
            @endpermission

            <a href="{{ route('clients.setting') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('client_settings.ui.cancel') }}
            </a>
        @endsection

        @include('client::components.page-head', [
            'title' => __('client_settings.module_settings.title'),
            'icon' => 'toggle-right',
            'sub' => __('client_settings.module_settings.subtitle'),
            'crumbs' => [
                __('client_settings.ui.home') => route('dashboard'),
                __('client_settings.hub.title') => route('clients.setting'),
                __('client_settings.module_settings.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('client::components.form-errors')

        {{-- إعدادات النظام التي تعمل بها شاشات العملاء ولا تُضبط هنا --}}
        @include('client::components.system-context')

        @include('client::components.guide', [
            'key' => 'client-module-settings.index',
            'open' => false,
            'title' => __('client_settings.module_settings.guide.title'),
            'lead' => __('client_settings.module_settings.guide.lead'),
            'blocks' => [
                [
                    __('client_settings.module_settings.guide.a'),
                    __('client_settings.module_settings.guide.b'),
                    __('client_settings.module_settings.guide.c'),
                    __('client_settings.module_settings.guide.d'),
                ],
            ],
            'facts' => [
                __('client_settings.module_settings.guide.fact1'),
                __('client_settings.module_settings.guide.fact2'),
            ],
        ])

        <form id="clientModuleSettings" method="POST" action="{{ route('clients.module_settings.update') }}">
            @csrf
            <input type="hidden" name="active_tab" id="activeTabInput" value="{{ $activeTab }}">

            {{-- ══════════ تفعيل الأقسام — فوق التبويبات لأنه يحكمها كلَّها ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-toggle-right"></i>
                        {{ __('client_settings.module_settings.sections.features') }}
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach ([
                            'visits_enabled' => 'icon-map-pin',
                            // خطوط السير، موقع العميل، CRM، تحويل الفواتير، أهداف العملاء — أُزيلت (2026-09-15): الصلاحية تكفي
                            'notes_enabled' => 'icon-file-text',
                            'doubtful_debts_enabled' => 'icon-alert-triangle',
                            'exempt_location_enabled' => 'icon-map-pin',
                            // 'exempt_exchange_enabled' أُزيلت: راجع تعليقها في FEATURES بـ ClientModuleSettingsController
                        ] as $feature => $featureIcon)
                            <div class="col-lg-4 col-md-6 col-12">
                                <div class="cl-switch-row">
                                    @include('client::components.switch', [
                                        'name' => $feature,
                                        'checked' => (bool) old($feature, $moduleSettings->{$feature}),
                                        'title' => __('client_settings.module_settings.fields.'.$feature),
                                    ])
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ══════════ التبويبات ══════════ --}}
            <div class="cl-tabs" role="tablist">
                @foreach ([
                    'clients' => 'icon-user',
                    'visits' => 'icon-map-pin',
                    'itinerary' => 'icon-map',
                    'follow_up' => 'icon-message-square',
                    'employees' => 'icon-users',
                    'invoices' => 'icon-file-text',
                ] as $tabKey => $tabIcon)
                    <button type="button" role="tab" data-cl-tab="{{ $tabKey }}"
                        id="cltab-{{ $tabKey }}" aria-controls="clpanel-{{ $tabKey }}"
                        aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
                        class="cl-tab {{ $activeTab === $tabKey ? 'is-active' : '' }}">
                        <i class="feather {{ $tabIcon }}"></i>
                        {{ __('client_settings.module_settings.tabs.'.$tabKey) }}
                    </button>
                @endforeach
            </div>

            {{-- ═══════════════════════ تبويب: العملاء ═══════════════════════ --}}
            <div class="cl-tabpanel" id="clpanel-clients" role="tabpanel" aria-labelledby="cltab-clients"
                @if ($activeTab !== 'clients') hidden @endif>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-sliders"></i>
                            {{ __('client_settings.module_settings.sections.client_defaults') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <label for="default_new_client_status_id">{{ __('client_settings.module_settings.fields.default_new_client_status_id') }}</label>
                                    <select name="default_new_client_status_id" id="default_new_client_status_id" class="form-control">
                                        <option value="">{{ __('client_settings.ui.none') }}</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}"
                                                @selected((string) $val('default_new_client_status_id') === (string) $status->id)>
                                                {{ $status->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <label for="default_registration_stage_id">{{ __('client_settings.module_settings.fields.default_registration_stage_id') }}</label>
                                    <select name="default_registration_stage_id" id="default_registration_stage_id" class="form-control">
                                        <option value="">{{ __('client_settings.ui.none') }}</option>
                                        @foreach ($registrationStages as $stage)
                                            <option value="{{ $stage->id }}"
                                                @selected((string) $val('default_registration_stage_id') === (string) $stage->id)>
                                                {{ $stage->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="cl-help">{{ __('client_settings.module_settings.fields.default_registration_stage_id_hint') }}</span>

                                    <div class="form-check mt-1">
                                        <input type="checkbox" class="form-check-input" id="apply_default_stage_to_existing" name="apply_default_stage_to_existing" value="1">
                                        <label class="form-check-label" for="apply_default_stage_to_existing">
                                            تطبيق هذا الوضع أيضاً على العملاء المسجلين حالياً الذين لا يملكون وضعاً بعد
                                        </label>
                                        <span class="cl-help d-block">لا يغيّر وضع أيّ عميل مضبوطاً مسبقاً — يملأ الفارغ فقط.</span>
                                    </div>

                                    {{-- أيُّ العملاء بلا وضع يُطبَّق عليهم: حسب حالتهم (نشط / موقوف) أو الجميع --}}
                                    <div class="mt-50" id="apply_default_stage_status_wrap">
                                        <label for="apply_default_stage_status_id" class="small mb-25">العملاء بلا وضع الذين حالتهم</label>
                                        <select name="apply_default_stage_status_id" id="apply_default_stage_status_id" class="form-control form-control-sm">
                                            <option value="">كل الحالات</option>
                                            @foreach ($statuses as $status)
                                                <option value="{{ $status->id }}" @selected((string) old('apply_default_stage_status_id') === (string) $status->id)>{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="cl-help d-block">مثال: اختر «نشط» ليأخذ الوضعَ العملاءُ النشطون بلا وضع وحدَهم، ويبقى الموقوفون بلا وضع.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <label for="client_type_scope">{{ __('client_settings.module_settings.fields.client_type_scope') }}</label>
                                    <select name="client_type_scope" id="client_type_scope" class="form-control">
                                        @foreach (['Both' => 'client_type_both', 'individual' => 'client_type_individual', 'commercial' => 'client_type_commercial'] as $scopeValue => $scopeLabel)
                                            <option value="{{ $scopeValue }}" @selected($val('client_type_scope', 'Both') === $scopeValue)>
                                                {{ __('client_settings.module_settings.fields.'.$scopeLabel) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="cl-help">{{ __('client_settings.module_settings.fields.client_type_scope_hint') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            @foreach (['client_require_phone', 'client_require_mobile', 'client_unique_phone', 'client_require_tax_number', 'client_require_email'] as $clientRule)
                                <div class="col-lg-6 col-12">
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => $clientRule,
                                            'checked' => $sw($clientRule),
                                            'title' => __('client_settings.module_settings.fields.'.$clientRule),
                                            'hint' => $clientRule === 'client_unique_phone'
                                                ? __('client_settings.module_settings.fields.client_unique_phone_hint')
                                                : null,
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="row">
                            @foreach (['client_require_trade_name', 'client_require_commercial_registration', 'client_require_category', 'client_require_registration_stage'] as $clientRequiredField)
                                <div class="col-lg-6 col-12">
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => $clientRequiredField,
                                            'checked' => $sw($clientRequiredField),
                                            'title' => __('client_settings.module_settings.fields.'.$clientRequiredField),
                                            'hint' => $clientRequiredField === 'client_require_registration_stage'
                                                ? __('client_settings.module_settings.fields.client_require_registration_stage_hint')
                                                : null,
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ route('clients.status') }}" class="btn btn-outline-primary btn-sm mt-1">
                            <i class="feather icon-list"></i>
                            {{ __('client_settings.module_settings.fields.statuses_link') }}
                        </a>
                        <a href="{{ route('clients.registrationStages') }}" class="btn btn-outline-primary btn-sm mt-1">
                            <i class="feather icon-clock"></i>
                            {{ __('client_settings.module_settings.fields.registration_stages_link') }}
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-check-circle"></i>
                            {{ __('client_settings.module_settings.sections.client_status_change') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="cl-switch-row">
                            @include('client::components.switch', [
                                'name' => 'client_status_change_requires_approval',
                                'checked' => $sw('client_status_change_requires_approval'),
                                'title' => __('client_settings.module_settings.fields.client_status_change_requires_approval'),
                                'hint' => __('client_settings.module_settings.fields.client_status_change_requires_approval_hint'),
                            ])
                        </div>
                    </div>
                </div>

                {{-- الحقول الإضافية — ما كانت تحفظه شاشة «عام» --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-check-square"></i>
                            {{ __('client_settings.module_settings.sections.client_fields') }}
                        </h4>
                        <span class="cl-tab__badge">
                            {{ $clientFields->where('is_active', true)->count() }} / {{ $clientFields->count() }}
                        </span>
                    </div>
                    <div class="card-body">
                        {{-- بهذا الحقل يعرف المتحكّم أن قائمة الحقول أُرسلت فعلاً،
                             فلا يُطفئها كلَّها حين يصل حفظٌ لا يحملها --}}
                        <input type="hidden" name="client_fields_submitted" value="1">

                        <p class="cl-help mb-2">{{ __('client_settings.module_settings.fields.client_fields_hint') }}</p>

                        @if ($clientFields->isEmpty())
                            <p class="cl-help mb-0" data-hint-keep>{{ __('client_settings.module_settings.fields.client_fields_empty') }}</p>
                        @else
                            @php
                                // المقارنة غير صارمة عمداً: `old()` تعيد نصوصاً
                                // والمعرّفات أعداد
                                $checkedFieldIds = (array) old(
                                    'settings',
                                    $clientFields->where('is_active', true)->pluck('id')->all()
                                );

                                /* حقولٌ لها أثرٌ فعليّ في نموذج العميل (إضافة/تعديل)،
                                   وحقولٌ ليس لها — إمّا لأنّ النموذج لا يعرضها بعد، أو
                                   لأنّ العمود نفسه غيرُ موجود في جدول `clients`. التفعيلُ
                                   هنا لا يفعل شيئاً في الحالة الثانية، فالتنبيهُ أصدقُ
                                   من صمتٍ يوحي بأثرٍ غير موجود. */
                                $clientFieldHints = [
                                    'image' => __('client_settings.module_settings.fields.client_field_hint_image'),
                                    'location' => __('client_settings.module_settings.fields.client_field_hint_location'),
                                    'credit_duration' => __('client_settings.module_settings.fields.client_field_hint_credit_duration'),
                                ];
                                $clientFieldHintUnwired = __('client_settings.module_settings.fields.client_field_hint_unwired');
                            @endphp
                            <div class="row">
                                @foreach ($clientFields as $clientField)
                                    <div class="col-lg-4 col-md-6 col-12">
                                        <div class="cl-switch-row">
                                            <label class="cl-switch" for="clfield_{{ $clientField->id }}">
                                                <input type="checkbox" id="clfield_{{ $clientField->id }}" name="settings[]"
                                                    value="{{ $clientField->id }}"
                                                    @checked(in_array($clientField->id, $checkedFieldIds))>
                                                <span class="cl-switch__track" aria-hidden="true">
                                                    <span class="cl-switch__thumb"></span>
                                                </span>
                                                <span class="cl-switch__label">
                                                    <span class="cl-switch__title">{{ $clientField->name }}</span>
                                                    <span class="cl-switch__hint">
                                                        {{ $clientFieldHints[$clientField->key] ?? $clientFieldHintUnwired }}
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="feather icon-map-pin"></i>
                                    {{ __('client_settings.module_settings.sections.location_group') }}
                                </h4>
                            </div>
                            <div class="card-body">
                                @foreach (['location_required', 'group_required'] as $locationRule)
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => $locationRule,
                                            'checked' => $sw($locationRule),
                                            'title' => __('client_settings.module_settings.fields.'.$locationRule),
                                        ])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="feather icon-paperclip"></i>
                                    {{ __('client_settings.module_settings.sections.upload_limits') }}
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="max_upload_size_kb">{{ __('client_settings.module_settings.fields.max_upload_size_kb') }}</label>
                                    <input type="number" min="64" max="512000" class="form-control"
                                        id="max_upload_size_kb" name="max_upload_size_kb" value="{{ $val('max_upload_size_kb', 102400) }}">
                                    <span class="cl-help">{{ __('client_settings.module_settings.fields.max_upload_size_kb_hint') }}</span>
                                </div>

                                {{-- ضغطُ الصور يقع في متصفّح المندوب قبل الرفع —
                                     المكسب في الشبكة الضعيفة أن تُرفع الصورة مضغوطةً
                                     أصلاً لا أن تُضغط بعد وصولها. --}}
                                <div class="row">
                                    <div class="col-md-6 col-12">
                                        <div class="form-group mb-0">
                                            <label for="image_quality_percent">{{ __('client_settings.module_settings.fields.image_quality_percent') }}</label>
                                            <input type="number" min="10" max="100" class="form-control"
                                                id="image_quality_percent" name="image_quality_percent" value="{{ $val('image_quality_percent', 60) }}">
                                            <span class="cl-help">كلّما قلّت الجودة صغُر الحجم وأسرع الرفع. 60% تكفي لقراءة لافتةٍ أو رفّ.</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="form-group mb-0">
                                            <label for="image_max_dimension_px">{{ __('client_settings.module_settings.fields.image_max_dimension_px') }}</label>
                                            <input type="number" min="320" max="4096" class="form-control"
                                                id="image_max_dimension_px" name="image_max_dimension_px" value="{{ $val('image_max_dimension_px', 1280) }}">
                                            <span class="cl-help">أطولُ ضلعٍ للصورة بعد التصغير. 1280 بكسل تكفي عادةً؛ اخفضها للشبكات الضعيفة جداً.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-eye-off"></i>
                            {{ __('client_settings.module_settings.sections.map') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label for="map_hide_duration_hours">{{ __('client_settings.module_settings.fields.map_hide_duration_hours') }}</label>
                            <input type="number" min="1" max="720" class="form-control"
                                id="map_hide_duration_hours" name="map_hide_duration_hours" value="{{ $val('map_hide_duration_hours', 24) }}">
                            <span class="cl-help">{{ __('client_settings.module_settings.fields.map_hide_duration_hours_hint') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════ تبويب: الزيارات ═══════════════════════ --}}
            <div class="cl-tabpanel" id="clpanel-visits" role="tabpanel" aria-labelledby="cltab-visits"
                @if ($activeTab !== 'visits') hidden @endif>

                <div class="row">
                    <div class="col-lg-6 col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="feather icon-user-check"></i>
                                    {{ __('client_settings.module_settings.sections.employee_visit') }}
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="cl-switch-row">
                                    @include('client::components.switch', [
                                        'name' => 'visit_note_required',
                                        'checked' => $sw('visit_note_required'),
                                        'title' => __('client_settings.module_settings.fields.visit_note_required'),
                                        'hint' => __('client_settings.module_settings.fields.visit_note_required_hint'),
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="feather icon-crosshair"></i>
                                    {{ __('client_settings.module_settings.sections.visit_proximity') }}
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-0">
                                    <label for="visit_proximity_radius_km">{{ __('client_settings.module_settings.fields.visit_proximity_radius_km') }}</label>
                                    <input type="number" step="0.01" min="0.01" max="50" class="form-control"
                                        id="visit_proximity_radius_km" name="visit_proximity_radius_km"
                                        value="{{ $val('visit_proximity_radius_km', 0.3) }}">
                                    <small class="form-text text-muted">{{ __('client_settings.module_settings.fields.visit_proximity_radius_km_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-layout"></i>
                            {{ __('client_settings.module_settings.sections.visit_workspace_sections') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">{{ __('client_settings.module_settings.fields.visit_workspace_sections_hint') }}</p>
                        <div class="row">
                            @foreach ([
                                'show_location_card' => 'icon-map-pin',
                                'show_quick_actions_card' => 'icon-zap',
                                'show_notes_card' => 'icon-file-text',
                                'show_contact_card' => 'icon-user',
                                'show_details_card' => 'icon-info',
                                'show_notes_tab' => 'icon-file-text',
                                'show_competitors_tab' => 'icon-eye',
                                'show_competitor_capture' => 'icon-target',
                                'note_deposit_count' => 'icon-package',
                                'note_photo' => 'icon-camera',
                                'show_docs_tab' => 'icon-file',
                                'show_attachments_tab' => 'icon-paperclip',
                                'show_signature_section' => 'icon-edit-3',
                            ] as $sectionKey => $sectionIcon)
                                <div class="col-lg-4 col-md-6 col-12">
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => $sectionKey,
                                            'checked' => $sw($sectionKey),
                                            'title' => __('client_settings.module_settings.fields.'.$sectionKey),
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- عددٌ لا مفتاح: كم صورةً يجب إرفاقها بالملاحظة.
                             يسري على ملاحظة الزيارة والمختصرة والرئيسية معاً،
                             ولا أثر له متى أُطفئ «حقل الصورة في ملاحظة الزيارة». --}}
                        <div class="row mt-2">
                            <div class="col-lg-4 col-md-6 col-12">
                                <div class="form-group">
                                    <label for="note_min_photos">{{ __('client_settings.module_settings.fields.note_min_photos') }}</label>
                                    <input type="number" min="1" max="10" class="form-control"
                                        id="note_min_photos" name="note_min_photos" value="{{ $val('note_min_photos', 1) }}">
                                    <small class="text-muted">{{ __('client_settings.module_settings.fields.note_min_photos_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('client::setting.partials.employee-proximity-overrides')

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-eye-off"></i>
                            {{ __('client_settings.module_settings.sections.finished_visit_clients') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="cl-switch-row">
                            @include('client::components.switch', [
                                'name' => 'finished_visit_hide_clients',
                                'checked' => $sw('finished_visit_hide_clients'),
                                'title' => __('client_settings.module_settings.fields.finished_visit_hide_clients'),
                                'hint' => __('client_settings.module_settings.fields.finished_visit_hide_clients_hint'),
                            ])
                        </div>
                        <div class="cl-switch-row">
                            @include('client::components.switch', [
                                'name' => 'finished_visit_allow_return',
                                'checked' => $sw('finished_visit_allow_return'),
                                'title' => __('client_settings.module_settings.fields.finished_visit_allow_return'),
                                'hint' => __('client_settings.module_settings.fields.finished_visit_allow_return_hint'),
                            ])
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════ تبويب: خطوط السير ═══════════════════════ --}}
            <div class="cl-tabpanel" id="clpanel-itinerary" role="tabpanel" aria-labelledby="cltab-itinerary"
                @if ($activeTab !== 'itinerary') hidden @endif>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-list"></i>
                            {{ __('client_settings.module_settings.sections.itinerary_order') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <label for="itinerary_order_strategy">{{ __('client_settings.module_settings.fields.itinerary_order_strategy') }}</label>
                                    <select name="itinerary_order_strategy" id="itinerary_order_strategy" class="form-control">
                                        @foreach (['manual', 'nearest', 'last_visit'] as $strategy)
                                            <option value="{{ $strategy }}" @selected($val('itinerary_order_strategy', 'manual') === $strategy)>
                                                {{ __('client_settings.module_settings.fields.itinerary_order_'.$strategy) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <label for="itinerary_max_clients_per_day">{{ __('client_settings.module_settings.fields.itinerary_max_clients_per_day') }}</label>
                                    <input type="number" min="1" max="500" class="form-control"
                                        id="itinerary_max_clients_per_day" name="itinerary_max_clients_per_day"
                                        value="{{ $val('itinerary_max_clients_per_day') }}"
                                        placeholder="{{ __('client_settings.ui.unlimited') }}">
                                    <span class="cl-help">{{ __('client_settings.ui.unlimited_hint') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            @foreach (['itinerary_lock_after_start', 'itinerary_allow_reorder_by_employee', 'itinerary_restrict_to_assigned_clients'] as $itineraryRule)
                                <div class="col-lg-6 col-12">
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => $itineraryRule,
                                            'checked' => $sw($itineraryRule),
                                            'title' => __('client_settings.module_settings.fields.'.$itineraryRule),
                                            'hint' => $itineraryRule === 'itinerary_restrict_to_assigned_clients'
                                                ? __('client_settings.module_settings.fields.itinerary_restrict_to_assigned_clients_hint')
                                                : null,
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-users"></i>
                            {{ __('client_settings.module_settings.sections.create_screen_client_scope') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="cl-switch-row">
                            @include('client::components.switch', [
                                'name' => 'create_screens_restrict_to_itinerary',
                                'checked' => $sw('create_screens_restrict_to_itinerary'),
                                'title' => __('client_settings.module_settings.fields.create_screens_restrict_to_itinerary'),
                                'hint' => __('client_settings.module_settings.fields.create_screens_restrict_to_itinerary_hint'),
                            ])
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-map"></i>
                            {{ __('client_settings.module_settings.sections.route_tracking') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="cl-switch-row">
                            @include('client::components.switch', [
                                'name' => 'enable_route_tracking',
                                'checked' => $sw('enable_route_tracking'),
                                'title' => __('client_settings.module_settings.fields.enable_route_tracking'),
                                'hint' => __('client_settings.module_settings.fields.enable_route_tracking_hint'),
                            ])
                        </div>
                    </div>
                </div>

            </div>

            {{-- ═══════════════════════ تبويب: المتابعة ═══════════════════════ --}}
            <div class="cl-tabpanel" id="clpanel-follow_up" role="tabpanel" aria-labelledby="cltab-follow_up"
                @if ($activeTab !== 'follow_up') hidden @endif>

                {{-- «المواعيد» انتقلت إلى «إعدادات الحجوزات ← المواعيد» (2026-09-13). --}}

                {{-- «ملاحظات العملاء» و«علاقات العملاء (CRM)» أُزيلتا (2026-09-13). --}}

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <i class="feather icon-bar-chart-2"></i>
                                    {{ __('client_settings.module_settings.sections.client_dashboard') }}
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="dashboard_needs_visit_days">{{ __('client_settings.module_settings.fields.dashboard_needs_visit_days') }}</label>
                                            <input type="number" min="1" max="365" class="form-control"
                                                id="dashboard_needs_visit_days" name="dashboard_needs_visit_days" value="{{ $val('dashboard_needs_visit_days', 30) }}">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="dashboard_overdue_days">{{ __('client_settings.module_settings.fields.dashboard_overdue_days') }}</label>
                                            <input type="number" min="0" max="365" class="form-control"
                                                id="dashboard_overdue_days" name="dashboard_overdue_days" value="{{ $val('dashboard_overdue_days', 60) }}">
                                        </div>
                                    </div>
                                </div>
                                <span class="cl-help d-block mb-2">{{ __('client_settings.module_settings.fields.dashboard_settings_hint') }}</span>

                                {{-- مطويٌّ دائماً وبلا `key`: لا يتذكّر الفتح، فالصفحة تُفتح والشرح مقفل --}}
                                @include('client::components.guide', [
                                    'open' => false,
                                    'title' => __('client_settings.module_settings.fields.dashboard_segments.guide.title'),
                                    'lead' => __('client_settings.module_settings.fields.dashboard_segments.guide.lead'),
                                    'blocks' => __('client_settings.module_settings.fields.dashboard_segments.guide.lines'),
                                ])
                                @include('client::setting.partials.dashboard-segments', [
                                    'name' => 'dashboard_lifecycle_segments',
                                    'kind' => 'lifecycle',
                                    'rows' => $val('dashboard_lifecycle_segments'),
                                    'defaults' => \App\Support\ClientDashboardSegments::DEFAULT_LIFECYCLE,
                                ])
                                @include('client::setting.partials.dashboard-segments', [
                                    'name' => 'dashboard_aging_segments',
                                    'kind' => 'aging',
                                    'rows' => $val('dashboard_aging_segments'),
                                    'defaults' => \App\Support\ClientDashboardSegments::DEFAULT_AGING,
                                ])
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════ تبويب: الموظفون ═══════════════════════ --}}
            <div class="cl-tabpanel" id="clpanel-employees" role="tabpanel" aria-labelledby="cltab-employees"
                @if ($activeTab !== 'employees') hidden @endif>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-user-plus"></i>
                            {{ __('client_settings.module_settings.sections.client_employees') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="client_employees_max">{{ __('client_settings.module_settings.fields.client_employees_max') }}</label>
                            <input type="number" min="1" max="100" class="form-control"
                                id="client_employees_max" name="client_employees_max"
                                value="{{ $val('client_employees_max') }}"
                                placeholder="{{ __('client_settings.ui.unlimited') }}">
                            <span class="cl-help">{{ __('client_settings.ui.unlimited_hint') }}</span>
                        </div>

                        {{-- من أيّ قسمٍ في الهيكل التنظيمي يُعيَّن موظّفو العملاء.

                             الحقل المخفيّ الفارغ ليس زينة: `saveSettingGroups()`
                             تتخطّى ما لم يُرسَل أصلاً («الغائب لا يُمسّ»)،
                             ومتعدّدُ الاختيار بلا اختيارٍ لا يرسل شيئاً — فبلا
                             هذا الحقل لا يستطيع المستخدم إفراغ القائمة أبداً
                             بعد أن ملأها مرّة. القيمة الفارغة تُصفَّى في
                             `castValue('int[]')` فتعود `[]` = كل الأقسام. --}}
                        <div class="form-group">
                            <label for="client_employees_departments">أقسام الموظفين المسموح تعيينهم للعملاء</label>
                            <input type="hidden" name="client_employees_departments[]" value="">
                            <select name="client_employees_departments[]" id="client_employees_departments"
                                class="form-control select2" multiple data-placeholder="كل الأقسام"
                                data-width="100%" style="width: 100%">
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}"
                                        @selected(in_array((int) $department->id, (array) ($val('client_employees_departments') ?: []), true))>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="cl-help">
                                يُقصَر اختيار «الموظفين المسؤولين» في شاشتَي إضافة وتعديل العميل على موظّفي
                                هذه الأقسام. اتركه فارغاً لتظهر كلّ الأقسام.
                                @if ($departments->isEmpty())
                                    <strong>لا قسم مسجَّل بعد في الهيكل التنظيمي.</strong>
                                @endif
                            </span>
                        </div>

                        <div class="row">
                            @foreach ([
                                'client_employees_allow_multiple',
                                'notify_employee_on_assignment_change',
                            ] as $employeeRule)
                                <div class="col-lg-6 col-12">
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => $employeeRule,
                                            'checked' => $sw($employeeRule),
                                            'title' => __('client_settings.module_settings.fields.'.$employeeRule),
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════ تبويب: الفواتير والديون ═══════════════════════ --}}
            <div class="cl-tabpanel" id="clpanel-invoices" role="tabpanel" aria-labelledby="cltab-invoices"
                @if ($activeTab !== 'invoices') hidden @endif>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-repeat"></i>
                            {{ __('client_settings.module_settings.sections.invoice_transfer') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <label for="invoice_transfer_max_open">{{ __('client_settings.module_settings.fields.invoice_transfer_max_open') }}</label>
                                    <input type="number" min="1" max="1000" class="form-control"
                                        id="invoice_transfer_max_open" name="invoice_transfer_max_open"
                                        value="{{ $val('invoice_transfer_max_open') }}"
                                        placeholder="{{ __('client_settings.ui.unlimited') }}">
                                    <span class="cl-help">{{ __('client_settings.ui.unlimited_hint') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            @foreach ([
                                'invoice_transfer_require_approval',
                                'invoice_transfer_reason_required',
                                'invoice_transfer_notify',
                                'invoice_transfer_allow_paid',
                            ] as $transferRule)
                                <div class="col-lg-6 col-12">
                                    <div class="cl-switch-row">
                                        @include('client::components.switch', [
                                            'name' => $transferRule,
                                            'checked' => $sw($transferRule),
                                            'title' => __('client_settings.module_settings.fields.'.$transferRule),
                                            'hint' => $transferRule === 'invoice_transfer_require_approval'
                                                ? __('client_settings.module_settings.fields.invoice_transfer_require_approval_hint')
                                                : null,
                                        ])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-file-minus"></i>
                            {{ __('client_settings.module_settings.sections.exchange_transaction') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="cl-switch-row">
                            @include('client::components.switch', [
                                'name' => 'exchange_require_transaction',
                                'checked' => $sw('exchange_require_transaction'),
                                'title' => __('client_settings.module_settings.fields.exchange_require_transaction'),
                                'hint' => __('client_settings.module_settings.fields.exchange_require_transaction_hint'),
                            ])
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="feather icon-shield"></i>
                            {{ __('client_settings.module_settings.sections.debt_writeoff') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label for="bad_debt_writeoff_approval_threshold">{{ __('client_settings.module_settings.fields.bad_debt_writeoff_approval_threshold') }}</label>
                            <input type="number" step="0.01" min="0" class="form-control"
                                id="bad_debt_writeoff_approval_threshold" name="bad_debt_writeoff_approval_threshold"
                                value="{{ $val('bad_debt_writeoff_approval_threshold') }}"
                                placeholder="{{ __('client_settings.ui.unlimited') }}">
                            <span class="cl-help">{{ __('client_settings.module_settings.fields.bad_debt_writeoff_approval_threshold_hint') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{--
            نماذج نطاق القرب المخصَّص للموظفين — منفصلة عمداً عن النموذج
            الكبير أعلاه (لا يصحّ تداخل <form> داخل <form>). حقولها الفعلية
            داخل بطاقة تبويب «الزيارات» أعلاه، مربوطةٌ بهذين النموذجين عبر
            السمة `form="..."`.
        --}}
        <form id="addProximityOverrideForm" method="POST"
            action="{{ route('clients.module_settings.employee_proximity_overrides.store') }}">
            @csrf
            <input type="hidden" name="active_tab" value="visits">
        </form>

        @foreach ($employeesWithProximityOverride as $emp)
            <form id="removeProximityOverride{{ $emp->id }}" method="POST"
                action="{{ route('clients.module_settings.employee_proximity_overrides.destroy', $emp->id) }}">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </div>
@endsection

@section('scripts')
    <script>
        // التبويبات محليّة بالكامل: الحقول كلّها في نموذجٍ واحد يُرسَل معاً،
        // فالتبديل إظهارٌ وإخفاء لا انتقالُ صفحة. و`active_tab` يُحدَّث ليعود
        // المستخدمُ بعد الحفظ إلى التبويب الذي كان فيه.
        (function () {
            var tabs = document.querySelectorAll('[data-cl-tab]');
            var input = document.getElementById('activeTabInput');

            if (!tabs.length) {
                return;
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var key = tab.getAttribute('data-cl-tab');

                    tabs.forEach(function (other) {
                        var isTarget = other === tab;
                        other.classList.toggle('is-active', isTarget);
                        other.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                    });

                    document.querySelectorAll('.cl-tabpanel').forEach(function (panel) {
                        panel.hidden = panel.id !== 'clpanel-' + key;
                    });

                    if (input) {
                        input.value = key;
                    }

                    // العنوان يتبع التبويب فيُحفَظ في المفضّلة ويعود إليه زرّ الرجوع
                    if (window.history && window.history.replaceState) {
                        var url = new URL(window.location.href);
                        url.searchParams.set('tab', key);
                        window.history.replaceState({}, '', url);
                    }
                });
            });
        })();

        // محرّرات شرائح لوحة العملاء — انظر setting/partials/dashboard-segments
        (function () {
            document.querySelectorAll('[data-seg-editor]').forEach(function (editor) {
                var name = editor.getAttribute('data-name');
                var maxRows = parseInt(editor.getAttribute('data-max-rows'), 10) || 12;
                var body = editor.querySelector('[data-seg-body]');
                var template = editor.querySelector('template[data-seg-template]');
                var addButton = editor.querySelector('[data-seg-add]');

                var readJson = function (selector) {
                    try { return JSON.parse(editor.querySelector(selector).textContent) || []; } catch (e) { return []; }
                };

                // جملةٌ حيّة تحت اسم كل صف — تترجم الأرقام إلى «من يدخله»، بقاعدة ClientDashboardSegments نفسها
                var kind = editor.getAttribute('data-kind');
                var texts = (function () {
                    try { return JSON.parse(editor.querySelector('[data-seg-texts]').textContent) || {}; } catch (e) { return {}; }
                })();
                var say = function (key, values) {
                    var text = texts[key] || '';
                    Object.keys(values || {}).forEach(function (k) { text = text.split(':' + k).join(String(values[k])); });
                    return text;
                };
                var numberOf = function (tr, field) {
                    var input = tr.querySelector('[data-f="' + field + '"]');
                    if (!input || input.value === '') { return null; }
                    var n = parseInt(input.value, 10);
                    return isNaN(n) ? null : Math.max(0, n);
                };
                var lifecycleSentence = function (tr, i) {
                    var from = numberOf(tr, 'from') || 0;
                    var to = numberOf(tr, 'to');
                    var minInvoices = numberOf(tr, 'min_invoices');
                    var gap = numberOf(tr, 'min_gap');
                    if (to !== null && to < from) { var swap = from; from = to; to = swap; }
                    var range = to === null
                        ? (from === 0 ? say('range_any') : say('range_older', { from: from }))
                        : (from === 0 ? say('range_within', { to: to }) : say('range_between', { from: from, to: to }));
                    var parts = [(gap !== null ? say('last_invoice') : say('last_activity')) + ' ' + range];
                    if (minInvoices) { parts.push(say('min_invoices', { n: minInvoices })); }
                    if (gap !== null) { parts.push(say('min_gap', { n: gap })); }
                    return say('enters') + ' ' + parts.join(say('joiner')) + (i > 0 ? ' ' + say('unless_above') : '');
                };
                var agingSentence = function (tr, i, previousBound, isLast) {
                    var to = numberOf(tr, 'to');
                    if (!isLast && to === null) { return say('aging_open_not_last'); }
                    if (isLast) { to = null; }
                    var from = i === 0 ? 0 : (previousBound === '' || previousBound === null ? null : parseInt(previousBound, 10) + 1);
                    if (from === null || (to !== null && to < from)) { return say('aging_unsorted'); }
                    if (from === 0 && to === null) { return say('aging_all'); }
                    if (to === null) { return say('aging_last', { days: from - 1 }); }
                    return from === 0 ? say('aging_first', { to: to }) : say('aging_mid', { from: from, to: to });
                };

                var reindex = function () {
                    var previousTo = null;
                    Array.prototype.forEach.call(body.children, function (tr, i) {
                        tr.querySelector('[data-seg-index]').textContent = i + 1;
                        tr.querySelectorAll('[data-f]').forEach(function (input) {
                            input.name = name + '[' + i + '][' + input.getAttribute('data-f') + ']';
                        });

                        // التقادم: «من» محسوبةٌ من حدّ الشريحة السابقة
                        var from = tr.querySelector('[data-seg-from]');
                        var previousBound = previousTo;
                        if (from) {
                            from.value = i === 0 ? '0' : (previousTo === '' || previousTo === null ? '—' : String(parseInt(previousTo, 10) + 1));
                            previousTo = tr.querySelector('[data-f="to"]').value;
                        }

                        var sentence = tr.querySelector('[data-seg-sentence]');
                        if (sentence) {
                            sentence.textContent = kind === 'aging'
                                ? agingSentence(tr, i, previousBound, i === body.children.length - 1)
                                : lifecycleSentence(tr, i);
                        }

                    });
                    addButton.disabled = body.children.length >= maxRows;
                };

                var addRow = function (data) {
                    var tr = template.content.firstElementChild.cloneNode(true);
                    tr.querySelectorAll('[data-f]').forEach(function (input) {
                        var value = data[input.getAttribute('data-f')];
                        if (value === null || value === undefined || value === '') {
                            if (input.type === 'color') { input.value = '#7367f0'; }
                            else if (input.tagName === 'SELECT') { input.value = 'any'; }
                            return;
                        }
                        input.value = value;
                    });
                    body.appendChild(tr);
                    return tr;
                };

                var fill = function (rows) {
                    body.innerHTML = '';
                    (rows.length ? rows : [{}]).forEach(addRow);
                    reindex();
                };

                fill(readJson('[data-seg-rows]'));

                editor.addEventListener('click', function (e) {
                    var button = e.target.closest('button');
                    if (!button || !editor.contains(button)) { return; }
                    var tr = button.closest('tr');

                    if (button.hasAttribute('data-seg-add')) {
                        if (body.children.length >= maxRows) { return; }
                        addRow({}).querySelector('[data-f="label"]').focus();
                    } else if (button.hasAttribute('data-seg-reset')) {
                        if (window.confirm(editor.getAttribute('data-reset-confirm'))) { fill(readJson('[data-seg-defaults]')); }
                        return;
                    } else if (button.hasAttribute('data-seg-remove')) {
                        if (body.children.length <= 1) { window.alert(editor.getAttribute('data-last-row')); return; }
                        tr.remove();
                    } else if (button.hasAttribute('data-seg-up') && tr.previousElementSibling) {
                        body.insertBefore(tr, tr.previousElementSibling);
                    } else if (button.hasAttribute('data-seg-down') && tr.nextElementSibling) {
                        body.insertBefore(tr.nextElementSibling, tr);
                    } else {
                        return;
                    }
                    reindex();
                });

                editor.addEventListener('input', reindex);
                editor.addEventListener('change', reindex);
            });
        })();
    </script>
@endsection
