@extends('sitting::master')

@section('title', __('settings.sms.title'))

@section('css')
    <style>
        .sm { --b:#e3e7ef; --muted:#79809a; --soft:#f7f9fc; --accent: var(--app-primary,#7367F0); --ink:#2f3349; }
        .sm-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:.9rem; }
        .sm-head h2 { font-size:1.2rem; font-weight:700; margin:0 0 .25rem; color:var(--ink); }
        .sm-head p { font-size:.82rem; color:var(--muted); margin:0; }

        .sm-card { background:#fff; border:1px solid var(--b); border-radius:11px;
            box-shadow:0 1px 3px rgba(20,25,45,.05); margin-bottom:.85rem; overflow:hidden; }
        .sm-card-head { padding:.75rem 1rem; border-bottom:1px solid var(--b); background:var(--soft);
            display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
        .sm-card-head h3 { font-size:.92rem; font-weight:700; margin:0; color:var(--ink); display:flex; align-items:center; gap:.45rem; }
        .sm-card-head p { font-size:.75rem; color:var(--muted); margin:.15rem 0 0; font-weight:400; }
        .sm-card-body { padding:1rem; }

        .sm-label { font-size:.78rem; font-weight:600; color:#444a60; margin-bottom:.25rem; display:block; }
        .sm-hint { font-size:.72rem; color:var(--muted); line-height:1.7; margin-top:.25rem; }
        .sm .form-control { font-size:.84rem; }
        .sm textarea.form-control { font-family:ui-monospace,monospace; font-size:.78rem; direction:ltr; text-align:left; }

        /* ---------- الإحصاء ---------- */
        .sm-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(112px,1fr)); gap:.5rem; margin-bottom:.85rem; }
        .sm-stat { border:1px solid var(--b); border-radius:10px; padding:.65rem .5rem; text-align:center; background:#fff; }
        .sm-stat b { display:block; font-size:1.2rem; color:var(--ink); line-height:1.3; }
        .sm-stat span { font-size:.71rem; color:var(--muted); }
        .sm-stat.ok b { color:#1c6b40; } .sm-stat.no b { color:#a13333; }
        .sm-stat.wait b { color:#8a5b00; } .sm-stat.hold b { color:#5a4b9c; }

        /* ---------- التبويبات ---------- */
        .sm-layout { display:grid; grid-template-columns:230px minmax(0,1fr); gap:.85rem; align-items:start; }
        @media (max-width:992px) { .sm-layout { grid-template-columns:minmax(0,1fr); } }

        .sm-tabs { background:#fff; border:1px solid var(--b); border-radius:11px; padding:.4rem; position:sticky; top:1rem; }
        @media (max-width:992px) { .sm-tabs { position:static; display:flex; overflow-x:auto; gap:.25rem; } }
        .sm-tab { display:flex; align-items:center; gap:.5rem; width:100%; text-align:start; border:0; background:transparent;
            padding:.55rem .6rem; border-radius:8px; font-size:.82rem; font-weight:600; color:#4a5069; cursor:pointer;
            white-space:nowrap; transition:background .12s,color .12s; }
        .sm-tab:hover { background:var(--soft); }
        .sm-tab.active { background:var(--accent); color:#fff; }
        .sm-tab i { font-size:.9rem; }
        .sm-tab .dot { width:7px; height:7px; border-radius:50%; background:#f0ad4e; margin-inline-start:auto; flex:0 0 auto; }
        .sm-tab.active .dot { background:#fff; }

        .sm-panel { display:none; }
        .sm-panel.active { display:block; }

        /* ---------- شبكة الحقول ---------- */
        .sm-fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:.85rem; }
        .sm-fields .wide { grid-column:1/-1; }

        /* ---------- مفتاح التبديل ---------- */
        .sm-switch { display:flex; align-items:flex-start; gap:.55rem; cursor:pointer; margin:0;
            font-size:.8rem; font-weight:600; color:#444a60; }
        .sm-switch input { position:absolute; opacity:0; width:0; height:0; }
        .sm-switch-track { display:flex; align-items:center; flex:0 0 auto; width:36px; height:20px; margin-top:1px;
            border-radius:20px; background:#d3d8e4; padding:2px; transition:background .15s; }
        .sm-switch-dot { width:16px; height:16px; border-radius:50%; background:#fff;
            box-shadow:0 1px 2px rgba(20,25,45,.3); margin-inline-start:0; transition:margin-inline-start .15s; }
        .sm-switch input:checked + .sm-switch-track { background:var(--accent); }
        .sm-switch input:checked + .sm-switch-track .sm-switch-dot { margin-inline-start:16px; }
        .sm-switch input:focus-visible + .sm-switch-track { outline:2px solid var(--accent); outline-offset:2px; }
        .sm-switch-text small { display:block; font-weight:400; font-size:.72rem; color:var(--muted); line-height:1.6; margin-top:.1rem; }

        .sm-switch-row { border:1px solid var(--b); border-radius:9px; padding:.6rem .7rem; background:#fff; }
        .sm-switch-row + .sm-switch-row { margin-top:.5rem; }

        /* ---------- أيام الأسبوع ---------- */
        .sm-days { display:flex; flex-wrap:wrap; gap:.35rem; }
        .sm-day { position:relative; }
        .sm-day input { position:absolute; opacity:0; width:0; height:0; }
        .sm-day span { display:inline-block; padding:.3rem .7rem; border:1px solid var(--b); border-radius:20px;
            font-size:.76rem; cursor:pointer; background:#fff; color:#4a5069; transition:.12s; }
        .sm-day input:checked + span { background:var(--accent); border-color:var(--accent); color:#fff; }

        /* ---------- سجل مصغّر ---------- */
        .sm-log { display:flex; align-items:center; justify-content:space-between; gap:.5rem;
            padding:.5rem .2rem; border-bottom:1px dashed var(--b); font-size:.78rem; }
        .sm-log:last-child { border-bottom:0; }
        .sm-log .num { font-family:ui-monospace,monospace; color:#4a4f61; direction:ltr; }
        .sm-pill { font-size:.7rem; border-radius:20px; padding:.1rem .55rem; border:1px solid var(--b); white-space:nowrap; }
        .sm-pill.ok { background:#eaf8f0; color:#1c6b40; border-color:#c7e9d5; }
        .sm-pill.no { background:#fdeeee; color:#a13333; border-color:#f5cccc; }
        .sm-pill.wait { background:#fff8e6; color:#8a5b00; border-color:#ffe0a3; }
        .sm-pill.hold { background:#f1eefc; color:#5a4b9c; border-color:#ddd6f5; }

        /* ---------- شريط الحفظ ---------- */
        .sm-save { position:sticky; bottom:0; z-index:5; background:#fff; border:1px solid var(--b);
            border-radius:11px; padding:.65rem .9rem; display:flex; align-items:center; justify-content:space-between;
            gap:.6rem; flex-wrap:wrap; box-shadow:0 -2px 10px rgba(20,25,45,.06); margin-top:.85rem; }
        .sm-save .note { font-size:.75rem; color:var(--muted); }
    </style>
@endsection

@section('content')
<div class="sm" dir="rtl">

    <div class="sm-head">
        <div>
            <h2><i class="feather icon-message-square" style="color:var(--accent)"></i> {{ __('settings.sms.title') }}</h2>
            <p>{{ __('settings.sms.subtitle') }}</p>
        </div>
        <div>
            <a href="{{ route('SmsTemplates.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather icon-file-text"></i> {{ __('settings.sms.templates_link', ['count' => $templateCount]) }}
            </a>
            <a href="{{ route('SmsLogs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather icon-list"></i> {{ __('settings.sms.log_link') }}
            </a>
        </div>
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>{{ __('settings.sms.errors.heading') }}</strong>
            <ul class="mb-0 pr-3 mt-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- حالة البوابة صراحةً: الشاشة كانت تحفظ ولا تقول أتعمل أم لا --}}
    @if ($configured && ! $settings->test_mode)
        <div class="alert d-flex align-items-start"
            style="background:#eaf8f0;border:1px solid #c7e9d5;color:#1c6b40;border-radius:10px">
            <i class="fas fa-check-circle mt-1 ml-2"></i>
            <div>
                <strong>{{ __('settings.sms.ready_title') }}</strong>
                <span style="font-size:.88rem">
                    {!! __('settings.sms.ready_hint', ['sender' => '<code>'.e($settings->sender_name).'</code>']) !!}
                </span>
            </div>
        </div>
    @elseif ($configured && $settings->test_mode)
        <div class="alert d-flex align-items-start"
            style="background:#f1eefc;border:1px solid #ddd6f5;color:#4a3d8f;border-radius:10px">
            <i class="fas fa-flask mt-1 ml-2"></i>
            <div>
                <strong>{{ __('settings.sms.test_mode_on_title') }}</strong>
                <div style="font-size:.88rem;margin-top:2px">{{ __('settings.sms.test_mode_on_hint') }}</div>
            </div>
        </div>
    @else
        <div class="alert d-flex align-items-start"
            style="background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00;border-radius:10px">
            <i class="fas fa-info-circle mt-1 ml-2"></i>
            <div>
                <strong>{{ __('settings.sms.unready_title') }}</strong>
                <div style="font-size:.88rem;margin-top:2px">
                    {{ __('settings.sms.unready_hint') }}
                    <a href="https://www.msegat.com" target="_blank" rel="noopener">msegat.com</a>.
                </div>
            </div>
        </div>
    @endif

    {{-- ==================== الإحصاء ==================== --}}
    <div class="sm-stats">
        <div class="sm-stat"><b>{{ number_format($stats['total']) }}</b><span>{{ __('settings.sms.total') }}</span></div>
        <div class="sm-stat ok"><b>{{ number_format($stats['sent']) }}</b><span>{{ __('settings.sms.succeeded') }}</span></div>
        <div class="sm-stat no"><b>{{ number_format($stats['failed']) }}</b><span>{{ __('settings.sms.failed') }}</span></div>
        <div class="sm-stat wait"><b>{{ number_format($stats['pending']) }}</b><span>{{ __('settings.sms.pending') }}</span></div>
        <div class="sm-stat hold"><b>{{ number_format($stats['withheld']) }}</b><span>{{ __('settings.sms.withheld') }}</span></div>
        <div class="sm-stat"><b>{{ number_format($stats['today']) }}</b><span>{{ __('settings.sms.today') }}</span></div>
        <div class="sm-stat"><b>{{ number_format($stats['month_cost'], 2) }}</b><span>{{ __('settings.sms.month_cost', ['currency' => $settings->cost_currency]) }}</span></div>
    </div>

    <form action="{{ route('Sms.store') }}" method="POST" id="smsForm">
        @csrf
        {{-- يعيد فتح القسم نفسه بعد الحفظ بدل القفز إلى الأول --}}
        <input type="hidden" name="active_section" id="activeSection" value="{{ old('active_section', 'gateway') }}">

        <div class="sm-layout">

            {{-- ==================== قائمة الأقسام ==================== --}}
            <div class="sm-tabs" role="tablist">
                @foreach ($sections as $key => [$label, $icon, $hint])
                    <button type="button" class="sm-tab {{ $loop->first ? 'active' : '' }}"
                        data-panel="{{ $key }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                        <i class="feather icon-{{ $icon }}"></i>
                        <span>{{ $label }}</span>
                        @if ($key === 'safety' && $settings->test_mode)
                            <span class="dot" title="{{ __('settings.sms.test_mode_on_title') }}"></span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div>
                {{-- ==================== 1. البوابة ==================== --}}
                <div class="sm-panel active" data-panel="gateway">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-server" style="color:var(--accent)"></i> {{ $sections['gateway'][0] }}</h3>
                                <p>{{ $sections['gateway'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-switch-row mb-3">
                                <label class="sm-switch">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $settings->is_active) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.enable') }}
                                        <small>{{ __('settings.sms.enable_hint') }}</small></span>
                                </label>
                            </div>

                            <div class="sm-fields">
                                <div>
                                    <label class="sm-label" for="provider">{{ __('settings.sms.provider') }}</label>
                                    <select class="form-control" name="provider" id="provider">
                                        @foreach ($providers as $key => $provider)
                                            <option value="{{ $key }}" {{ old('provider', $settings->provider) === $key ? 'selected' : '' }}>
                                                {{ $provider['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="sm-hint">{{ __('settings.sms.provider_hint') }}</div>
                                </div>

                                <div>
                                    <label class="sm-label" for="timeout_seconds">{{ __('settings.sms.timeout') }}</label>
                                    <input type="number" class="form-control" name="timeout_seconds" id="timeout_seconds"
                                        min="5" max="120" value="{{ old('timeout_seconds', $settings->timeout_seconds) }}">
                                    <div class="sm-hint">{{ __('settings.sms.timeout_hint') }}</div>
                                </div>

                                <div>
                                    <label class="sm-label" for="username">{{ __('settings.sms.username') }}</label>
                                    <input type="text" class="form-control" name="username" id="username" required dir="ltr"
                                        value="{{ old('username', $settings->username) }}">
                                    <div class="sm-hint">{{ __('settings.sms.username_hint') }}</div>
                                </div>

                                <div>
                                    <label class="sm-label" for="api_key">{{ __('settings.sms.api_key') }}</label>
                                    {{-- المفتاحُ لا يُطبع في الصفحة: كان يظهر نصّاً صريحاً في الحقل
                                         لكل من يفتح الشاشة أو يقف خلف من فتحها. الحقلُ الفارغ يُبقي
                                         المحفوظ، والكتابةُ فيه تستبدله. --}}
                                    <input type="password" class="form-control" name="api_key" id="api_key" dir="ltr"
                                        autocomplete="new-password" value=""
                                        @if (filled($settings->api_key)) placeholder="{{ __('settings.sms.api_key_saved') }}" @else required @endif>
                                    <div class="sm-hint">{{ __('settings.sms.api_key_hint') }}</div>
                                </div>

                                <div>
                                    <label class="sm-label" for="sender_name">{{ __('settings.sms.sender_name') }}</label>
                                    <input type="text" class="form-control" name="sender_name" id="sender_name" required dir="ltr"
                                        value="{{ old('sender_name', $settings->sender_name) }}">
                                    <div class="sm-hint">{!! __('settings.sms.sender_name_hint') !!}</div>
                                </div>

                                <div>
                                    <label class="sm-label" for="fallback_sender_name">{{ __('settings.sms.fallback_sender') }}</label>
                                    <input type="text" class="form-control" name="fallback_sender_name" id="fallback_sender_name" dir="ltr"
                                        value="{{ old('fallback_sender_name', $settings->fallback_sender_name) }}">
                                    <div class="sm-hint">{{ __('settings.sms.fallback_sender_hint') }}</div>
                                </div>

                                <div class="wide">
                                    <label class="sm-label" for="api_url">{{ __('settings.sms.api_url') }}</label>
                                    <input type="url" class="form-control" name="api_url" id="api_url" dir="ltr"
                                        placeholder="{{ $settings->sendUrl() ?: 'https://…' }}"
                                        value="{{ old('api_url', $settings->api_url) }}">
                                    <div class="sm-hint">{{ __('settings.sms.api_url_hint') }}</div>
                                </div>
                            </div>

                            <hr>

                            <div class="sm-fields">
                                <div>
                                    <label class="sm-label" for="testPhone">{{ __('settings.sms.phone') }}</label>
                                    <input type="text" class="form-control" id="testPhone" dir="ltr" placeholder="05xxxxxxxx">
                                    <div class="sm-hint">{!! __('settings.sms.phone_hint') !!}</div>
                                </div>
                                <div class="d-flex align-items-end" style="gap:.4rem">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnBalance">
                                        <i class="feather icon-activity"></i> {{ __('settings.sms.check_balance') }}
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" id="btnTest">
                                        <i class="feather icon-send"></i> {{ __('settings.sms.send_test') }}
                                    </button>
                                </div>
                                <div class="wide"><div id="testResult"></div></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== 2. صياغة الرسالة ==================== --}}
                <div class="sm-panel" data-panel="message">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-edit-3" style="color:var(--accent)"></i> {{ $sections['message'][0] }}</h3>
                                <p>{{ $sections['message'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-fields mb-3">
                                <div>
                                    <label class="sm-label" for="default_country_code">{{ __('settings.sms.country_code') }}</label>
                                    <input type="text" class="form-control" name="default_country_code" id="default_country_code" dir="ltr"
                                        value="{{ old('default_country_code', $settings->default_country_code) }}">
                                    <div class="sm-hint">{{ __('settings.sms.country_code_hint') }}</div>
                                </div>

                                <div>
                                    <label class="sm-label" for="encoding_mode">{{ __('settings.sms.encoding') }}</label>
                                    <select class="form-control" name="encoding_mode" id="encoding_mode">
                                        @foreach (\App\Models\SmsSetting::ENCODING_MODES as $mode)
                                            <option value="{{ $mode }}" {{ old('encoding_mode', $settings->encoding_mode) === $mode ? 'selected' : '' }}>
                                                {{ __('settings.sms.encoding_'.$mode) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="sm-hint">{{ __('settings.sms.encoding_hint') }}</div>
                                </div>

                                <div>
                                    <label class="sm-label" for="max_segments">{{ __('settings.sms.max_segments') }}</label>
                                    <input type="number" class="form-control" name="max_segments" id="max_segments"
                                        min="1" max="10" value="{{ old('max_segments', $settings->max_segments) }}">
                                    <div class="sm-hint">{{ __('settings.sms.max_segments_hint') }}</div>
                                </div>

                                <div class="wide">
                                    <label class="sm-label" for="allowed_country_codes">{{ __('settings.sms.allowed_countries') }}</label>
                                    <input type="text" class="form-control" name="allowed_country_codes" id="allowed_country_codes" dir="ltr"
                                        placeholder="966, 971, 965"
                                        value="{{ old('allowed_country_codes', implode(', ', (array) $settings->allowed_country_codes)) }}">
                                    <div class="sm-hint">{{ __('settings.sms.allowed_countries_hint') }}</div>
                                </div>
                            </div>

                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="allow_international" value="1" {{ old('allow_international', $settings->allow_international) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.allow_international') }}
                                        <small>{{ __('settings.sms.allow_international_hint') }}</small></span>
                                </label>
                            </div>
                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="strip_emoji" value="1" {{ old('strip_emoji', $settings->strip_emoji) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.strip_emoji') }}
                                        <small>{{ __('settings.sms.strip_emoji_hint') }}</small></span>
                                </label>
                            </div>
                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="collapse_whitespace" value="1" {{ old('collapse_whitespace', $settings->collapse_whitespace) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.collapse_whitespace') }}
                                        <small>{{ __('settings.sms.collapse_whitespace_hint') }}</small></span>
                                </label>
                            </div>

                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="append_signature" value="1" {{ old('append_signature', $settings->append_signature) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.append_signature') }}
                                        <small>{{ __('settings.sms.append_signature_hint') }}</small></span>
                                </label>
                                <input type="text" class="form-control mt-2" name="signature_text" maxlength="120"
                                    placeholder="{{ __('settings.sms.signature_placeholder') }}"
                                    value="{{ old('signature_text', $settings->signature_text) }}">
                            </div>

                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="append_opt_out" value="1" {{ old('append_opt_out', $settings->append_opt_out) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.append_opt_out') }}
                                        <small>{{ __('settings.sms.append_opt_out_hint') }}</small></span>
                                </label>
                                <input type="text" class="form-control mt-2" name="opt_out_text" maxlength="120"
                                    placeholder="{{ __('settings.sms.opt_out_placeholder') }}"
                                    value="{{ old('opt_out_text', $settings->opt_out_text) }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== 3. أوقات الإرسال ==================== --}}
                <div class="sm-panel" data-panel="timing">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-clock" style="color:var(--accent)"></i> {{ $sections['timing'][0] }}</h3>
                                <p>{{ $sections['timing'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="quiet_hours_enabled" value="1" {{ old('quiet_hours_enabled', $settings->quiet_hours_enabled) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.quiet_hours') }}
                                        <small>{{ __('settings.sms.quiet_hours_hint') }}</small></span>
                                </label>
                            </div>

                            <div class="sm-fields mt-3">
                                <div>
                                    <label class="sm-label" for="quiet_hours_start">{{ __('settings.sms.quiet_start') }}</label>
                                    <input type="time" class="form-control" name="quiet_hours_start" id="quiet_hours_start" dir="ltr"
                                        value="{{ old('quiet_hours_start', \Illuminate\Support\Str::substr((string) $settings->quiet_hours_start, 0, 5)) }}">
                                </div>
                                <div>
                                    <label class="sm-label" for="quiet_hours_end">{{ __('settings.sms.quiet_end') }}</label>
                                    <input type="time" class="form-control" name="quiet_hours_end" id="quiet_hours_end" dir="ltr"
                                        value="{{ old('quiet_hours_end', \Illuminate\Support\Str::substr((string) $settings->quiet_hours_end, 0, 5)) }}">
                                    <div class="sm-hint">{{ __('settings.sms.quiet_overnight_hint') }}</div>
                                </div>
                                <div>
                                    <label class="sm-label" for="quiet_hours_action">{{ __('settings.sms.quiet_action') }}</label>
                                    <select class="form-control" name="quiet_hours_action" id="quiet_hours_action">
                                        @foreach (\App\Models\SmsSetting::QUIET_ACTIONS as $action)
                                            <option value="{{ $action }}" {{ old('quiet_hours_action', $settings->quiet_hours_action) === $action ? 'selected' : '' }}>
                                                {{ __('settings.sms.quiet_action_'.$action) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="sm-hint">{{ __('settings.sms.quiet_action_hint') }}</div>
                                </div>
                                <div>
                                    <label class="sm-label" for="timezone">{{ __('settings.sms.timezone') }}</label>
                                    <select class="form-control" name="timezone" id="timezone" dir="ltr">
                                        <option value="">{{ __('settings.sms.timezone_default', ['zone' => config('app.timezone')]) }}</option>
                                        @foreach ($timezones as $zone)
                                            <option value="{{ $zone }}" {{ old('timezone', $settings->timezone) === $zone ? 'selected' : '' }}>{{ $zone }}</option>
                                        @endforeach
                                    </select>
                                    <div class="sm-hint">{{ __('settings.sms.timezone_hint') }}</div>
                                </div>
                            </div>

                            <hr>

                            <div class="sm-switch-row mb-3">
                                <label class="sm-switch">
                                    <input type="checkbox" name="respect_working_days" value="1" {{ old('respect_working_days', $settings->respect_working_days) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.working_days') }}
                                        <small>{{ __('settings.sms.working_days_hint') }}</small></span>
                                </label>
                            </div>

                            @php $chosenDays = array_map('intval', (array) old('working_days', $settings->working_days ?? [])); @endphp
                            <div class="sm-days">
                                @foreach ($weekDays as $index => $day)
                                    <label class="sm-day">
                                        <input type="checkbox" name="working_days[]" value="{{ $index }}"
                                            {{ in_array($index, $chosenDays, true) ? 'checked' : '' }}>
                                        <span>{{ $day }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== 4. الحدود والتكرار ==================== --}}
                <div class="sm-panel" data-panel="limits">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-sliders" style="color:var(--accent)"></i> {{ $sections['limits'][0] }}</h3>
                                <p>{{ $sections['limits'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="alert py-2 px-3" style="background:var(--soft);border:1px solid var(--b);color:#4a5069;font-size:.78rem;border-radius:9px">
                                {{ __('settings.sms.zero_means_unlimited') }}
                            </div>

                            <div class="sm-fields">
                                <div>
                                    <label class="sm-label" for="rate_limit_per_minute">{{ __('settings.sms.rate_limit') }}</label>
                                    <input type="number" class="form-control" name="rate_limit_per_minute" id="rate_limit_per_minute"
                                        min="0" max="10000" value="{{ old('rate_limit_per_minute', $settings->rate_limit_per_minute) }}">
                                    <div class="sm-hint">{{ __('settings.sms.rate_limit_hint') }}</div>
                                </div>
                                <div>
                                    <label class="sm-label" for="daily_cap">{{ __('settings.sms.daily_cap') }}</label>
                                    <input type="number" class="form-control" name="daily_cap" id="daily_cap"
                                        min="0" max="1000000" value="{{ old('daily_cap', $settings->daily_cap) }}">
                                    <div class="sm-hint">{{ __('settings.sms.daily_cap_hint') }}</div>
                                </div>
                                <div>
                                    <label class="sm-label" for="monthly_cap">{{ __('settings.sms.monthly_cap') }}</label>
                                    <input type="number" class="form-control" name="monthly_cap" id="monthly_cap"
                                        min="0" max="10000000" value="{{ old('monthly_cap', $settings->monthly_cap) }}">
                                    <div class="sm-hint">{{ __('settings.sms.monthly_cap_hint') }}</div>
                                </div>
                                <div>
                                    <label class="sm-label" for="per_recipient_daily_cap">{{ __('settings.sms.per_recipient_cap') }}</label>
                                    <input type="number" class="form-control" name="per_recipient_daily_cap" id="per_recipient_daily_cap"
                                        min="0" max="1000" value="{{ old('per_recipient_daily_cap', $settings->per_recipient_daily_cap) }}">
                                    <div class="sm-hint">{{ __('settings.sms.per_recipient_cap_hint') }}</div>
                                </div>
                                <div>
                                    <label class="sm-label" for="duplicate_window_minutes">{{ __('settings.sms.duplicate_window') }}</label>
                                    <input type="number" class="form-control" name="duplicate_window_minutes" id="duplicate_window_minutes"
                                        min="0" max="1440" value="{{ old('duplicate_window_minutes', $settings->duplicate_window_minutes) }}">
                                    <div class="sm-hint">{{ __('settings.sms.duplicate_window_hint') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== 5. إعادة المحاولة ==================== --}}
                <div class="sm-panel" data-panel="retry">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-refresh-cw" style="color:var(--accent)"></i> {{ $sections['retry'][0] }}</h3>
                                <p>{{ $sections['retry'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-switch-row mb-3">
                                <label class="sm-switch">
                                    <input type="checkbox" name="retry_enabled" value="1" {{ old('retry_enabled', $settings->retry_enabled) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.retry_enable') }}
                                        <small>{{ __('settings.sms.retry_enable_hint') }}</small></span>
                                </label>
                            </div>

                            <div class="sm-fields">
                                <div>
                                    <label class="sm-label" for="retry_attempts">{{ __('settings.sms.retry_attempts') }}</label>
                                    <input type="number" class="form-control" name="retry_attempts" id="retry_attempts"
                                        min="0" max="10" value="{{ old('retry_attempts', $settings->retry_attempts) }}">
                                </div>
                                <div>
                                    <label class="sm-label" for="retry_delay_seconds">{{ __('settings.sms.retry_delay') }}</label>
                                    <input type="number" class="form-control" name="retry_delay_seconds" id="retry_delay_seconds"
                                        min="5" max="3600" value="{{ old('retry_delay_seconds', $settings->retry_delay_seconds) }}">
                                </div>
                                <div>
                                    <label class="sm-label" for="retry_backoff">{{ __('settings.sms.retry_backoff') }}</label>
                                    <select class="form-control" name="retry_backoff" id="retry_backoff">
                                        @foreach (\App\Models\SmsSetting::BACKOFF_MODES as $mode)
                                            <option value="{{ $mode }}" {{ old('retry_backoff', $settings->retry_backoff) === $mode ? 'selected' : '' }}>
                                                {{ __('settings.sms.retry_backoff_'.$mode) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="sm-hint">{{ __('settings.sms.retry_backoff_hint') }}</div>
                                </div>
                            </div>

                            <div class="sm-hint mt-3">{!! __('settings.sms.retry_cron_note') !!}</div>
                        </div>
                    </div>
                </div>

                {{-- ==================== 6. الاختبار والحماية ==================== --}}
                <div class="sm-panel" data-panel="safety">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-shield" style="color:var(--accent)"></i> {{ $sections['safety'][0] }}</h3>
                                <p>{{ $sections['safety'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="test_mode" value="1" {{ old('test_mode', $settings->test_mode) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.test_mode') }}
                                        <small>{{ __('settings.sms.test_mode_hint') }}</small></span>
                                </label>
                                <input type="text" class="form-control mt-2" name="test_redirect_number" dir="ltr" maxlength="20"
                                    placeholder="{{ __('settings.sms.test_redirect_placeholder') }}"
                                    value="{{ old('test_redirect_number', $settings->test_redirect_number) }}">
                                <div class="sm-hint">{{ __('settings.sms.test_redirect_hint') }}</div>
                            </div>

                            <div class="sm-fields mt-3">
                                <div class="wide">
                                    <label class="sm-label" for="blocked_numbers">{{ __('settings.sms.blocked_numbers') }}</label>
                                    <textarea class="form-control" name="blocked_numbers" id="blocked_numbers" rows="5"
                                        placeholder="0501234567&#10;0559876543">{{ old('blocked_numbers', $settings->blocked_numbers) }}</textarea>
                                    <div class="sm-hint">{{ __('settings.sms.blocked_numbers_hint') }}</div>
                                </div>
                            </div>

                            <div class="sm-switch-row mt-3">
                                <label class="sm-switch">
                                    <input type="checkbox" name="whitelist_enabled" value="1" {{ old('whitelist_enabled', $settings->whitelist_enabled) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.whitelist') }}
                                        <small>{{ __('settings.sms.whitelist_hint') }}</small></span>
                                </label>
                                <textarea class="form-control mt-2" name="allowed_numbers" rows="4"
                                    placeholder="0501234567">{{ old('allowed_numbers', $settings->allowed_numbers) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== 7. الرصيد والكلفة ==================== --}}
                <div class="sm-panel" data-panel="cost">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-dollar-sign" style="color:var(--accent)"></i> {{ $sections['cost'][0] }}</h3>
                                <p>{{ $sections['cost'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-fields">
                                <div>
                                    <label class="sm-label" for="cost_per_segment">{{ __('settings.sms.cost_per_segment') }}</label>
                                    <input type="number" step="0.0001" min="0" max="100" class="form-control"
                                        name="cost_per_segment" id="cost_per_segment"
                                        value="{{ old('cost_per_segment', $settings->cost_per_segment) }}">
                                    <div class="sm-hint">{{ __('settings.sms.cost_per_segment_hint') }}</div>
                                </div>
                                <div>
                                    <label class="sm-label" for="cost_currency">{{ __('settings.sms.currency') }}</label>
                                    <input type="text" class="form-control" name="cost_currency" id="cost_currency" maxlength="10"
                                        value="{{ old('cost_currency', $settings->cost_currency) }}">
                                </div>
                                <div>
                                    <label class="sm-label" for="low_balance_threshold">{{ __('settings.sms.low_balance_threshold') }}</label>
                                    <input type="number" class="form-control" name="low_balance_threshold" id="low_balance_threshold"
                                        min="0" max="1000000" value="{{ old('low_balance_threshold', $settings->low_balance_threshold) }}">
                                    <div class="sm-hint">{{ __('settings.sms.low_balance_threshold_hint') }}</div>
                                </div>
                            </div>

                            <div class="sm-switch-row mt-3">
                                <label class="sm-switch">
                                    <input type="checkbox" name="low_balance_alert" value="1" {{ old('low_balance_alert', $settings->low_balance_alert) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.low_balance_alert') }}
                                        <small>{{ __('settings.sms.low_balance_alert_hint') }}</small></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== 8. السجل والخصوصية ==================== --}}
                <div class="sm-panel" data-panel="logging">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-database" style="color:var(--accent)"></i> {{ $sections['logging'][0] }}</h3>
                                <p>{{ $sections['logging'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="log_enabled" value="1" {{ old('log_enabled', $settings->log_enabled) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.log_enabled') }}
                                        <small>{{ __('settings.sms.log_enabled_hint') }}</small></span>
                                </label>
                            </div>
                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="log_body" value="1" {{ old('log_body', $settings->log_body) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.log_body') }}
                                        <small>{{ __('settings.sms.log_body_hint') }}</small></span>
                                </label>
                            </div>
                            <div class="sm-switch-row">
                                <label class="sm-switch">
                                    <input type="checkbox" name="mask_recipient" value="1" {{ old('mask_recipient', $settings->mask_recipient) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.mask_recipient') }}
                                        <small>{{ __('settings.sms.mask_recipient_hint') }}</small></span>
                                </label>
                            </div>

                            <div class="sm-fields mt-3">
                                <div>
                                    <label class="sm-label" for="log_retention_days">{{ __('settings.sms.retention') }}</label>
                                    <input type="number" class="form-control" name="log_retention_days" id="log_retention_days"
                                        min="0" max="3650" value="{{ old('log_retention_days', $settings->log_retention_days) }}">
                                    <div class="sm-hint">{{ __('settings.sms.retention_hint') }}</div>
                                </div>
                            </div>

                            <hr>

                            <h6 style="font-size:.82rem;font-weight:700;color:var(--ink)">{{ __('settings.sms.recent') }}</h6>
                            @forelse ($recentLogs as $log)
                                <div class="sm-log">
                                    <span class="num">{{ $log->recipient }}</span>
                                    <span style="color:var(--muted);font-size:.72rem">{{ $log->created_at?->diffForHumans() }}</span>
                                    <span class="sm-pill {{ match ($log->status) {
                                        'sent' => 'ok',
                                        'failed' => 'no',
                                        'deferred', 'retry', 'processing' => 'wait',
                                        default => 'hold',
                                    } }}">{{ $log->statusLabel() }}</span>
                                </div>
                            @empty
                                <p class="sm-hint mb-0">{{ __('settings.sms.no_messages') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- ==================== 9. تنبيه المسؤولين ==================== --}}
                <div class="sm-panel" data-panel="alerts">
                    <div class="sm-card">
                        <div class="sm-card-head">
                            <div>
                                <h3><i class="feather icon-bell" style="color:var(--accent)"></i> {{ $sections['alerts'][0] }}</h3>
                                <p>{{ $sections['alerts'][2] }}</p>
                            </div>
                        </div>
                        <div class="sm-card-body">
                            <div class="sm-switch-row mb-3">
                                <label class="sm-switch">
                                    <input type="checkbox" name="notify_on_failure" value="1" {{ old('notify_on_failure', $settings->notify_on_failure) ? 'checked' : '' }}>
                                    <span class="sm-switch-track"><span class="sm-switch-dot"></span></span>
                                    <span class="sm-switch-text">{{ __('settings.sms.notify_on_failure') }}
                                        <small>{{ __('settings.sms.notify_on_failure_hint') }}</small></span>
                                </label>
                            </div>

                            <div class="sm-fields">
                                <div>
                                    <label class="sm-label" for="failure_threshold">{{ __('settings.sms.failure_threshold') }}</label>
                                    <input type="number" class="form-control" name="failure_threshold" id="failure_threshold"
                                        min="1" max="100" value="{{ old('failure_threshold', $settings->failure_threshold) }}">
                                    <div class="sm-hint">{{ __('settings.sms.failure_threshold_hint') }}</div>
                                </div>
                                <div class="wide">
                                    <label class="sm-label" for="failure_notify_emails">{{ __('settings.sms.failure_emails') }}</label>
                                    <input type="text" class="form-control" name="failure_notify_emails" id="failure_notify_emails" dir="ltr"
                                        value="{{ old('failure_notify_emails', $settings->failure_notify_emails) }}">
                                    <div class="sm-hint">{{ __('settings.sms.failure_emails_hint') }}</div>
                                </div>
                            </div>

                            <div class="sm-hint mt-3">
                                {!! __('settings.sms.notify_channel_note', ['link' => '<a href="'.route('NotificationSettings.index').'">'.__('settings.sms.notification_settings').'</a>']) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sm-save">
            <span class="note"><i class="feather icon-info"></i> {{ __('settings.sms.save_note') }}</span>
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-save"></i> {{ __('settings.sms.save') }}
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    // ---------- التبويبات ----------
    var tabs = document.querySelectorAll('.sm-tab');
    var panels = document.querySelectorAll('.sm-panel');
    var activeField = document.getElementById('activeSection');

    function show(name) {
        tabs.forEach(function (tab) {
            var on = tab.dataset.panel === name;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.panel === name);
        });
        if (activeField) activeField.value = name;
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { show(tab.dataset.panel); });
    });

    // القسم المطلوب من العنوان — يُعاد فتحه بعد الحفظ وعند مشاركة الرابط
    var wanted = (location.hash || '').replace('#tab-', '');
    if (wanted && document.querySelector('.sm-panel[data-panel="' + wanted + '"]')) {
        show(wanted);
    }

    // حقلٌ خاطئ في قسم مطويّ يجعل المتصفّح يرفض الإرسال بلا سبب ظاهر —
    // يُفتح قسمه قبل أن يشتكي
    var form = document.getElementById('smsForm');
    if (form) {
        form.addEventListener('invalid', function (event) {
            var panel = event.target.closest('.sm-panel');
            if (panel && !panel.classList.contains('active')) show(panel.dataset.panel);
        }, true);
    }

    // ---------- الفحص ----------
    var result = document.getElementById('testResult');
    var token = document.querySelector('input[name="_token"]').value;

    function note(kind, text) {
        var colors = {
            ok: 'background:#eaf8f0;border:1px solid #c7e9d5;color:#1c6b40',
            no: 'background:#fdeeee;border:1px solid #f5cccc;color:#a13333',
            wait: 'background:#fff8e6;border:1px solid #ffe0a3;color:#8a5b00'
        };
        result.innerHTML = '<div class="alert py-2 px-3 mb-0" style="border-radius:9px;font-size:.82rem;' + colors[kind] + '">' + text + '</div>';
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify(body || {})
        }).then(function (response) { return response.json(); });
    }

    var balanceBtn = document.getElementById('btnBalance');
    if (balanceBtn) {
        balanceBtn.addEventListener('click', function () {
            note('wait', @json(__('settings.sms.checking')));
            post(@json(route('Sms.balance')))
                .then(function (data) {
                    var text = data.message || '';
                    if (data.balance) text += ' ' + @json(__('settings.sms.balance_available')).replace(':balance', data.balance);
                    note(data.success ? 'ok' : 'no', text);
                })
                .catch(function () { note('no', @json(__('settings.sms.connection_failed'))); });
        });
    }

    var testBtn = document.getElementById('btnTest');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var phone = document.getElementById('testPhone').value.trim();
            if (!phone) { note('no', @json(__('settings.sms.enter_phone'))); return; }

            note('wait', @json(__('settings.sms.sending')));
            post(@json(route('Sms.test_sms')), { phone: phone })
                .then(function (data) {
                    // «مؤجَّلة» و«وضع الاختبار» ليستا فشلاً ولا نجاحاً كاملاً
                    var kind = data.success ? (data.status === 'sent' ? 'ok' : 'wait') : 'no';
                    note(kind, data.message || data.error || '');
                })
                .catch(function () { note('no', @json(__('settings.sms.connection_failed'))); });
        });
    }
})();
</script>
@endsection
