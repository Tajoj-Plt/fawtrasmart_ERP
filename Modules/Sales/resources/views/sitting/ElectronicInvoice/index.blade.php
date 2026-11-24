@extends('sales::master')

{{-- لوحة الفاتورة الإلكترونية.

     كانت بطاقتا «تفعيل» زرّاهما `href=""` — أي تعيد تحميل الصفحة ولا
     تفعّل شيئاً، ولا شيء في الشاشة يدلّ على البيئة المفعَّلة ولا على
     اكتمال البيانات. الآن الحالة معروضة والتفعيل نموذج POST حقيقي. --}}

@section('title', __('sales_settings.electronic.title'))

@php
    $settings = $settings ?? null;
    $tone = $settings->isReady() ? 'success' : ($settings->is_enabled ? 'warning' : 'danger');
@endphp

@section('content')
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">
    @include('sitting::components._settings_styles')

    <style>
        .ei-env {
            border: 1px solid #e6e9ef; border-radius: 14px; background: #fff; padding: 1.4rem;
            height: 100%; display: flex; flex-direction: column; text-align: center;
            box-shadow: 0 1px 2px rgba(20,26,35,.04), 0 6px 16px rgba(20,26,35,.04);
        }
        .ei-env.on { border-color: #28c76f; box-shadow: 0 0 0 3px rgba(40,199,111,.12); }
        .ei-env .ic {
            width: 56px; height: 56px; border-radius: 15px; margin: 0 auto .8rem;
            display: grid; place-items: center; font-size: 1.5rem;
            background: #eaf1fb; color: #2b6cb0;
        }
        .ei-env.on .ic { background: #e7f6ee; color: #1b7a4b; }
        .ei-env h4 { font-size: 1.05rem; font-weight: 700; color: #1b2430; margin: 0 0 .4rem; }
        .ei-env p { font-size: .84rem; color: #6b7280; line-height: 1.7; flex: 1; }
        .ei-env .act { margin-top: 1rem; }
        .ei-env .needs { font-size: .78rem; color: #8a93a0; flex: 0; margin-bottom: 0; }
    </style>

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">{{ __('sales_settings.electronic.title') }}</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('sittingInvoice.index') }}">{{ __('sales_settings.hub.title') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('sales_settings.electronic.title') }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right text-md-right col-md-4 col-12">
            @if ($settings->is_enabled)
                @permission('sales.settings.update')
                    <form action="{{ route('electronic_invoice.deactivate') }}" method="POST" class="d-inline"
                        onsubmit="return confirm(@js(__('sales_settings.electronic.deactivate_confirm')))">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="feather icon-power"></i> {{ __('sales_settings.electronic.deactivate') }}
                        </button>
                    </form>
                @endpermission
            @endif
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- ══════════ إرشادُ الشاشة ══════════
             `settings-theme` من أجل أنماط `.sl-guide` وحدها؛ كلُّ قواعده
             مبدوءةٌ بـ`.sl-` فلا تمسّ أنماطَ `.ei-*` أعلاه. --}}
        @include('sales::partials.settings-theme')
        @include('components.hint-tips')

        @include('sales::components.guide', [
            'key' => 'sales-electronic-invoice.index',
            'open' => false,
            'title' => __('sales_settings.guide.electronic.title'),
            'lead' => __('sales_settings.guide.electronic.lead'),
            'blocks' => [
                __('sales_settings.guide.electronic.what_is.title') => [
                    __('sales_settings.guide.electronic.what_is.a'),
                    __('sales_settings.guide.electronic.what_is.b'),
                ],
                __('sales_settings.guide.electronic.effects.title') => [
                    __('sales_settings.guide.electronic.effects.a'),
                    __('sales_settings.guide.electronic.effects.b'),
                ],
            ],
        ])

        {{-- ══════════ المرحلة ══════════
             قبل كلّ شيء: بها يتحدّد ما يُطلَب من المنشأة وما يظهر أدناه. منشأةٌ
             في المرحلة الأولى لا تُسأل عن شهادةٍ ولا رمز OTP. --}}
        <div class="st-card">
            <div class="head">
                <i class="feather icon-layers" style="color:#2b6cb0"></i>
                <h4>{{ __('zatca.phase.title') }}</h4>
                <span class="st-badge info">{{ __('zatca.phase.'.$settings->phase.'.title') }}</span>
            </div>
            <div class="body">
                <p class="text-muted" style="font-size:.88rem;line-height:1.8">{{ __('zatca.phase.lead') }}</p>

                <div class="row">
                    @foreach (\App\Models\ElectronicInvoiceSetting::PHASES as $phase)
                        @php $isCurrent = $settings->phase === $phase; @endphp
                        <div class="col-md-6 col-12 mb-1">
                            <div class="ei-env {{ $isCurrent ? 'on' : '' }}">
                                <div class="ic">
                                    <i class="feather icon-{{ $phase === 'phase1' ? 'printer' : 'send' }}"></i>
                                </div>
                                <h4>{{ __('zatca.phase.'.$phase.'.title') }}</h4>
                                <p>{{ __('zatca.phase.'.$phase.'.desc') }}</p>
                                <p class="needs">{{ __('zatca.phase.'.$phase.'.needs') }}</p>
                                <div class="act">
                                    @if ($isCurrent)
                                        <span class="badge-modern active">
                                            <i class="feather icon-check"></i> {{ __('zatca.phase.current') }}
                                        </span>
                                    @else
                                        @permission('sales.settings.update')
                                            <form action="{{ route('electronic_invoice.phase') }}" method="POST"
                                                onsubmit="return confirm(@js(__('zatca.phase.switch_confirm')))">
                                                @csrf
                                                <input type="hidden" name="phase" value="{{ $phase }}">
                                                <button type="submit" class="btn btn-outline-primary">
                                                    {{ __('zatca.phase.choose') }}
                                                </button>
                                            </form>
                                        @endpermission
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- النقص مفصول بمصدره: ما ينقص في المنشأة يُصلَح في «إعدادات الحساب»
             لا هنا، فإرسال المستخدم إلى الشاشة الخطأ يضيّع وقته --}}
        @if ($missing['establishment'])
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                {{ __('sales_settings.electronic.missing_in_establishment', [
                    'fields' => implode(__('settings.common.list_separator'), $missing['establishment']),
                ]) }}
                @if (Route::has('SittingAccount.index'))
                    <a href="{{ route('SittingAccount.index') }}" class="alert-link">
                        {{ __('sales_settings.electronic.open_account_settings') }}
                    </a>
                @endif
            </div>
        @endif

        @if ($missing['local'])
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                {{ __('sales_settings.electronic.missing_note', [
                    'fields' => implode(__('settings.common.list_separator'), $missing['local']),
                ]) }}
                <a href="{{ route('electronic_invoice.company') }}" class="alert-link">
                    {{ __('sales_settings.electronic.open_company') }}
                </a>
            </div>
        @endif

        <div class="stats-row">
            <div class="stat-card-modern {{ $tone }}">
                <div class="stat-icon"><i class="feather icon-zap"></i></div>
                <div class="stat-label">{{ __('sales_settings.electronic.status') }}</div>
                <div class="stat-value" style="font-size:1.25rem">
                    @if (! $settings->is_enabled)
                        {{ __('sales_settings.electronic.disabled') }}
                    @elseif ($settings->isComplete())
                        {{ __('sales_settings.electronic.ready') }}
                    @else
                        {{ __('sales_settings.electronic.incomplete') }}
                    @endif
                </div>
            </div>

            <div class="stat-card-modern primary">
                <div class="stat-icon"><i class="feather icon-globe"></i></div>
                <div class="stat-label">{{ __('sales_settings.electronic.active_env') }}</div>
                <div class="stat-value" style="font-size:1.25rem">
                    {{-- البيئة شأنُ المرحلة الثانية؛ الأولى لا تتّصل بشيء --}}
                    @if (! $settings->is_enabled)
                        —
                    @elseif ($settings->isPhaseTwo())
                        {{ __('sales_settings.electronic.env.'.$settings->environment) }}
                    @else
                        {{ __('zatca.phase.phase1.title') }}
                    @endif
                </div>
            </div>

            <div class="stat-card-modern {{ $settings->isComplete() ? 'success' : 'warning' }}">
                <div class="stat-icon"><i class="feather icon-check-square"></i></div>
                <div class="stat-label">{{ __('sales_settings.electronic.data_state') }}</div>
                <div class="stat-value" style="font-size:1.25rem">
                    {{ $settings->isComplete()
                        ? __('sales_settings.electronic.complete')
                        : __('sales_settings.electronic.missing_count', ['count' => count($missing['all'])]) }}
                </div>
            </div>
        </div>

        @if ($settings->isPhaseTwo())
        @php
            $daysLeft = $settings->hasCredentials() && $settings->certificate_expires_at
                ? (int) floor(now()->diffInDays($settings->certificate_expires_at, false))
                : null;
        @endphp

        @if ($daysLeft !== null && $daysLeft < 0)
            <div class="alert alert-danger">
                <i class="feather icon-alert-octagon"></i> {{ __('zatca.onboarding.expired_notice') }}
            </div>
        @elseif ($daysLeft !== null && $daysLeft <= 30)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i> {{ __('zatca.onboarding.expiring', ['days' => $daysLeft]) }}
            </div>
        @endif

        @if ($settings->canSubmit() && ! $settings->auto_send_after_create)
            <div class="alert alert-info">
                <i class="feather icon-info"></i> {{ __('zatca.onboarding.auto_off') }}
                <a href="{{ route('electronic_invoice_edit') }}" class="alert-link">{{ __('sales_settings.electronic.open_settings') }}</a>
            </div>
        @endif

        <div class="row">
            @foreach (\App\Models\ElectronicInvoiceSetting::ENVIRONMENTS as $env)
                @php $isOn = $settings->is_enabled && $settings->environment === $env; @endphp
                <div class="col-lg-3 col-md-6 col-12 mb-2">
                    <div class="ei-env {{ $isOn ? 'on' : '' }}">
                        <div class="ic">
                            <i class="feather icon-{{ ['sandbox' => 'shield', 'simulation' => 'repeat'][$env] ?? 'send' }}"></i>
                        </div>
                        <h4>{{ __('sales_settings.electronic.env.'.$env) }}</h4>
                        <p>{{ __('sales_settings.electronic.env_desc.'.$env) }}</p>
                        <div class="act">
                            @if ($isOn)
                                <span class="badge-modern active">
                                    <i class="feather icon-check"></i> {{ __('sales_settings.electronic.active_now') }}
                                </span>
                            @else
                                {{-- التفعيل يكتب في القاعدة، فهو POST لا رابط --}}
                                @permission('sales.settings.update')
                                    <form action="{{ route('electronic_invoice.activate') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="environment" value="{{ $env }}">
                                        <button type="submit" class="btn btn-success">
                                            <i class="feather icon-check"></i>
                                            {{ $settings->is_enabled ? __('sales_settings.electronic.switch_to') : __('sales_settings.electronic.activate') }}
                                        </button>
                                    </form>
                                @endpermission
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="col-lg-3 col-md-6 col-12 mb-2">
                <div class="ei-env">
                    <div class="ic"><i class="feather icon-sliders"></i></div>
                    <h4>{{ __('sales_settings.electronic.behavior') }}</h4>
                    <p>{{ __('sales_settings.electronic.behavior_hint') }}</p>
                    <div class="act">
                        <a href="{{ route('electronic_invoice_edit') }}" class="btn btn-outline-primary">
                            <i class="feather icon-settings"></i> {{ __('sales_settings.electronic.open_settings') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ التسجيل لدى الهيئة ══════════
             الشهادة تخصّ بيئتها: المحفوظة لبيئةٍ غير المفعَّلة لا تُرسِل،
             فتُعرَض حالتها صريحةً بدل «مسجَّل» يكذب. --}}
        @php
            $registered = $settings->hasCredentials();
            $elsewhere = ! $registered && filled($settings->credentials_environment);
            $expired = $registered && $settings->certificateExpired();
        @endphp

        <div class="st-card">
            <div class="head">
                <i class="feather icon-shield" style="color:#2b6cb0"></i>
                <h4>{{ __('zatca.onboarding.title') }}</h4>
                @if ($expired)
                    <span class="st-badge dang">{{ __('zatca.onboarding.expired') }}</span>
                @elseif ($registered)
                    <span class="st-badge on"><i class="feather icon-check"></i> {{ __('zatca.onboarding.registered') }}</span>
                @elseif ($elsewhere)
                    <span class="st-badge warn">{{ __('zatca.onboarding.other_environment') }}</span>
                @else
                    <span class="st-badge off">{{ __('zatca.onboarding.not_registered') }}</span>
                @endif
                <span class="spacer"></span>
                <a href="{{ route('electronic_invoice.documents') }}" class="btn btn-sm btn-outline-primary">
                    <i class="feather icon-list"></i> {{ __('zatca.documents.open') }}
                </a>
            </div>
            <div class="body">
                <div class="row">
                    <div class="col-lg-7 col-12">
                        <p class="text-muted" style="font-size:.88rem;line-height:1.8">{{ __('zatca.onboarding.lead') }}</p>

                        @if ($elsewhere)
                            <div class="alert alert-warning mb-1">
                                <i class="feather icon-alert-triangle"></i>
                                {{ __('zatca.onboarding.switch_warning', [
                                    'env' => __('sales_settings.electronic.env.'.$settings->credentials_environment),
                                ]) }}
                            </div>
                        @endif

                        @if ($settings->last_check_status === 'failed' && filled($settings->last_check_message))
                            <div class="alert alert-danger mb-1" style="font-size:.85rem">
                                <strong>{{ __('zatca.onboarding.last_attempt') }}
                                    ({{ optional($settings->last_checked_at)->format('Y-m-d H:i') }}):</strong>
                                {{ $settings->last_check_message }}
                            </div>
                        @endif

                        @if (filled($settings->credentials_environment))
                            <table class="st-table">
                                <tbody>
                                    <tr>
                                        <td style="width:40%;color:#6b7280">{{ __('zatca.onboarding.environment') }}</td>
                                        <td>{{ __('sales_settings.electronic.env.'.$settings->credentials_environment) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color:#6b7280">{{ __('zatca.onboarding.registered_at') }}</td>
                                        <td>{{ optional($settings->onboarded_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color:#6b7280">{{ __('zatca.onboarding.expires_at') }}</td>
                                        <td>{{ optional($settings->certificate_expires_at)->format('Y-m-d') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color:#6b7280">{{ __('zatca.onboarding.serial') }}</td>
                                        <td class="st-mono" style="direction:ltr;text-align:right">{{ $settings->egs_serial ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        @else
                            <strong style="font-size:.88rem">{{ __('zatca.onboarding.steps_title') }}</strong>
                            <ol class="pr-2 mt-50" style="font-size:.86rem;line-height:1.9;color:#4b5563">
                                <li>{{ __('zatca.onboarding.step_1') }}</li>
                                <li>{{ __('zatca.onboarding.step_2') }}</li>
                                <li>{{ __('zatca.onboarding.step_3') }}</li>
                            </ol>
                        @endif
                    </div>

                    <div class="col-lg-5 col-12">
                        @permission('sales.settings.update')
                            @if (! $settings->isComplete())
                                <div class="alert alert-warning mb-0">
                                    <i class="feather icon-alert-triangle"></i>
                                    {{ __('zatca.onboarding.complete_first') }}
                                </div>
                            @else
                                <form action="{{ route('electronic_invoice.onboard') }}" method="POST" id="zatca-onboard-form"
                                    @if ($registered) data-confirm="{{ __('zatca.onboarding.renew_confirm') }}" @endif>
                                    @csrf
                                    <div class="alert alert-{{ $settings->isProduction() ? 'danger' : 'info' }}" style="font-size:.84rem">
                                        <i class="feather icon-info"></i>
                                        <strong>{{ __('sales_settings.electronic.env.'.$settings->environment) }}:</strong>
                                        {{ __('zatca.onboarding.'.$settings->environment.'_note') }}
                                    </div>

                                    <div class="form-group">
                                        <label for="zatca-otp">{{ __('zatca.onboarding.otp') }}</label>
                                        <input type="text" name="otp" id="zatca-otp" inputmode="numeric" maxlength="6"
                                            autocomplete="off" required
                                            class="form-control st-mono @error('otp') is-invalid @enderror"
                                            style="direction:ltr;text-align:center;letter-spacing:.4em;font-size:1.15rem"
                                            placeholder="{{ __('zatca.onboarding.otp_placeholder') }}">
                                        @error('otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-block" id="zatca-onboard-btn"
                                        data-working="{{ __('zatca.onboarding.working') }}">
                                        <i class="feather icon-shield"></i>
                                        {{ $registered ? __('zatca.onboarding.renew') : __('zatca.onboarding.submit') }}
                                    </button>
                                </form>
                            @endif
                        @endpermission
                    </div>
                </div>
            </div>
        </div>

        {{-- التسجيل نداءاتٌ متتابعة على الهيئة تستغرق ثوانيَ: ضغطةٌ ثانية أثناءها
             تستهلك رمز OTP نفسه مرّتين فتفشل الثانية وتُربك الأولى --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var form = document.getElementById('zatca-onboard-form');

                if (! form) {
                    return;
                }

                form.addEventListener('submit', function (event) {
                    if (form.dataset.confirm && ! window.confirm(form.dataset.confirm)) {
                        event.preventDefault();

                        return;
                    }

                    var button = document.getElementById('zatca-onboard-btn');

                    button.disabled = true;
                    button.textContent = button.dataset.working;
                });
            });
        </script>
        @else
            {{-- المرحلة الأولى: لا بيئة ولا تسجيل — تفعيلٌ فحسب --}}
            <div class="st-card">
                <div class="head">
                    <i class="feather icon-printer" style="color:#2b6cb0"></i>
                    <h4>{{ __('zatca.phase.phase1.title') }}</h4>
                    @if ($settings->isReady())
                        <span class="st-badge on"><i class="feather icon-check"></i> {{ __('sales_settings.electronic.ready') }}</span>
                    @endif
                </div>
                <div class="body">
                    <p class="text-muted mb-1" style="font-size:.88rem;line-height:1.8">
                        {{ $settings->isReady() ? __('zatca.phase.ready') : __('zatca.phase.phase1.desc') }}
                    </p>

                    @if (! $settings->is_enabled)
                        @permission('sales.settings.update')
                            <form action="{{ route('electronic_invoice.activate') }}" method="POST">
                                @csrf
                                <input type="hidden" name="environment" value="{{ $settings->environment }}">
                                <button type="submit" class="btn btn-success">
                                    <i class="feather icon-check"></i> {{ __('zatca.phase.activate') }}
                                </button>
                            </form>
                        @endpermission
                    @endif
                </div>
            </div>
        @endif

        <div class="st-card">
            <div class="head">
                <i class="feather icon-briefcase" style="color:#2b6cb0"></i>
                <h4>{{ __('sales_settings.electronic.company') }}</h4>
                @if ($settings->isComplete())
                    <span class="st-badge on">{{ __('sales_settings.electronic.complete') }}</span>
                @else
                    <span class="st-badge warn">{{ __('sales_settings.electronic.incomplete') }}</span>
                @endif
                <span class="spacer"></span>
                <a href="{{ route('electronic_invoice.company') }}" class="btn btn-sm btn-outline-primary">
                    <i class="feather icon-edit-2"></i> {{ __('settings.common.edit') }}
                </a>
            </div>
            <div class="body flush">
                <table class="st-table">
                    <tbody>
                        {{-- من سجلّ المنشأة: تُعرَض بمصدرها كي يعرف المستخدم
                             أين يُصحّحها --}}
                        @foreach ([
                            'trade_name' => 'trade_name',
                            'tax_number' => 'tax_number',
                            'commercial_registration' => 'commercial_register',
                            'country' => 'country',
                            'postal_code' => 'postal_code',
                        ] as $column => $key)
                            <tr>
                                <td style="width:38%;color:#6b7280">
                                    {{ __('sales_settings.electronic.fields.'.$key) }}
                                    <span class="st-badge off">{{ __('sales_settings.electronic.from_account') }}</span>
                                </td>
                                <td>
                                    {{ $settings->fromEstablishment($column) ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td style="color:#6b7280">
                                {{ __('sales_settings.electronic.fields.address') }}
                                <span class="st-badge off">{{ __('sales_settings.electronic.from_account') }}</span>
                            </td>
                            <td>{{ $settings->establishmentAddress() ?? '—' }}</td>
                        </tr>

                        {{-- خاصّة بالفوترة الإلكترونية --}}
                        @foreach (['tax_group_name', 'business_category', 'building_number', 'district', 'common_name'] as $field)
                            <tr>
                                <td style="color:#6b7280">{{ __('sales_settings.electronic.fields.'.$field) }}</td>
                                <td>
                                    @if (filled($settings->{$field}))
                                        {{ $settings->{$field} }}
                                    @else
                                        <span style="color:#b7bec8">—</span>
                                        @if (in_array($field, \App\Models\ElectronicInvoiceSetting::REQUIRED_LOCAL, true))
                                            <span class="st-badge warn">{{ __('settings.common.required_mark') }}</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td style="color:#6b7280">{{ __('zatca.fields.invoice_types') }}</td>
                            <td>
                                {{ __('zatca.fields.invoice_types_'.(['1000' => 'standard', '0100' => 'simplified'][$settings->invoice_types] ?? 'both')) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
