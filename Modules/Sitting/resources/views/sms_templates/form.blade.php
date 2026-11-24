@extends('sitting::master')

@section('title', $template->exists
    ? __('settings.sms_templates.edit_title', ['name' => $template->name])
    : __('settings.sms_templates.new_title'))

@section('css')
    <style>
        .st {
            --b: #e2e6ef; --b2: #eef1f6; --muted: #7b8299; --soft: #f7f9fc;
            --desk: #e8ebf2; --accent: var(--app-primary, #7367F0); --ink: #2f3349;
            --stage-h: calc(100vh - 268px);
        }

        /* ==================== الترويسة ==================== */
        .st-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:.7rem; }
        .st-head h2 { font-size:1.18rem; font-weight:700; margin:0 0 .25rem; color:var(--ink); }
        .st-tags { display:flex; gap:.3rem; flex-wrap:wrap; font-size:.73rem; color:var(--muted); }
        .st-tags span { background:var(--soft); border:1px solid var(--b); border-radius:20px; padding:.1rem .6rem; }

        /* ==================== شريط الإرشاد ==================== */
        .st-guide { background:linear-gradient(90deg,#f4f2ff,#fcfbff); border:1px solid #ded9ff; border-radius:9px;
            padding:.6rem .85rem; margin-bottom:.7rem; display:flex; align-items:center; gap:1.1rem;
            flex-wrap:wrap; font-size:.79rem; color:#4b4a63; }
        .st-guide b { color:var(--ink); }
        .st-legend { display:flex; gap:.85rem; flex-wrap:wrap; align-items:center; }
        .st-legend i { display:inline-block; width:12px; height:12px; border-radius:3px; vertical-align:-2px; margin-inline-end:.28rem; }
        .lg-var { background:#ede9fe; border:1px solid #c4b5fd; }
        .lg-text { background:#fff; border:1px solid #cfd5e2; }
        .lg-long { background:#fff7ed; border:1px dashed #f59e0b; }
        .st-guide-close { border:0; background:none; color:var(--muted); margin-inline-start:auto; cursor:pointer; font-size:.77rem; }

        /* ==================== الإطار ==================== */
        .st-shell { display:grid; grid-template-columns:272px minmax(0,1fr); gap:.7rem; align-items:stretch; }
        .st-shell.dock-closed { grid-template-columns:minmax(0,1fr); }
        .st-shell.dock-closed .st-dock { display:none; }
        @media (max-width:1100px){ .st-shell, .st-shell.dock-closed { grid-template-columns:minmax(0,1fr); } }

        .st-card { background:#fff; border:1px solid var(--b); border-radius:10px;
            box-shadow:0 1px 3px rgba(20,25,45,.05); overflow:hidden; }

        /* ==================== الرصيف ==================== */
        .st-dock { display:flex; flex-direction:column; height:var(--stage-h); }
        .st-dock-tabs { display:flex; border-bottom:1px solid var(--b); background:var(--soft); flex-shrink:0; }
        .st-dock-tabs button { flex:1; border:0; background:none; padding:.6rem .2rem; font-size:.78rem;
            font-weight:600; color:var(--muted); cursor:pointer; border-bottom:2px solid transparent; }
        .st-dock-tabs button.is-active { color:var(--accent); border-bottom-color:var(--accent); background:#fff; }

        .st-dock-pane { display:none; flex-direction:column; flex:1; min-height:0; }
        .st-dock-pane.is-active { display:flex; }

        .st-hint { padding:.5rem .8rem; font-size:.72rem; color:var(--muted);
            border-bottom:1px solid var(--b2); background:#fcfdff; line-height:1.6; }
        .st-scroll { overflow-y:auto; flex:1; min-height:0; }

        .st-group-title { font-size:.67rem; font-weight:700; color:var(--muted);
            padding:.7rem .85rem .2rem; display:flex; align-items:center; gap:.35rem; }

        .st-chip { display:flex; align-items:center; justify-content:space-between; gap:.4rem; width:100%;
            border:0; background:none; padding:.34rem .85rem; font-size:.78rem; color:#4a4f61;
            cursor:pointer; text-align:start; transition:background .12s, color .12s; }
        .st-chip:hover { background:#f1eeff; color:var(--accent); }
        .st-chip code { font-size:.64rem; color:#a5abbe; background:none; padding:0; white-space:nowrap; }

        .st-block { display:block; width:calc(100% - 1.3rem); margin:.3rem .65rem; border:1px solid var(--b);
            border-radius:8px; background:#fff; padding:.5rem .65rem; text-align:start; cursor:pointer;
            transition:border-color .12s, box-shadow .12s; }
        .st-block:hover { border-color:var(--accent); box-shadow:0 2px 8px rgba(115,103,240,.14); }
        .st-block strong { display:block; font-size:.8rem; font-weight:600; color:var(--ink); }
        .st-block small { display:block; font-size:.7rem; color:var(--muted); line-height:1.5; }

        .st-settings { padding:.8rem .85rem 1.2rem; }
        .st-settings label { font-size:.76rem; font-weight:600; color:#444a60; margin-bottom:.2rem; }
        .st-settings .form-control { font-size:.8rem; height:auto; padding:.32rem .55rem; }
        .st-sec { font-size:.68rem; font-weight:700; color:var(--muted); display:flex; align-items:center;
            gap:.5rem; margin:1.1rem 0 .6rem; }
        .st-sec:first-child { margin-top:0; }
        .st-sec::after { content:''; flex:1; height:1px; background:var(--b); }

        /* ==================== المسرح ==================== */
        .st-main { display:flex; flex-direction:column; height:var(--stage-h); }
        .st-bar { display:flex; align-items:center; gap:.5rem; padding:.4rem .6rem; border-bottom:1px solid var(--b);
            background:var(--soft); flex-shrink:0; flex-wrap:wrap; }

        .st-seg { display:inline-flex; border:1px solid var(--b); border-radius:7px; overflow:hidden; background:#fff; }
        .st-seg button { border:0; background:none; padding:.34rem .72rem; font-size:.78rem; font-weight:600;
            color:var(--muted); cursor:pointer; display:flex; align-items:center; gap:.3rem; white-space:nowrap; }
        .st-seg button + button { border-inline-start:1px solid var(--b); }
        .st-seg button.is-active { background:var(--accent); color:#fff; }
        .st-bar-gap { flex:1; }

        .st-icon { border:1px solid var(--b); background:#fff; border-radius:6px; width:28px; height:28px;
            font-size:.75rem; color:var(--muted); cursor:pointer; line-height:1; }
        .st-icon:hover { color:var(--accent); border-color:var(--accent); }
        .st-icon.is-off { background:var(--soft); color:#bcc1d2; }

        .st-stage { display:flex; flex:1; min-height:0; }
        .st-pane { min-width:0; min-height:0; display:flex; flex-direction:column; flex:1; }
        .st-pane-view { border-inline-start:1px solid var(--b); }
        .st-stage.view-edit .st-pane-view { display:none; }
        .st-stage.view-preview .st-pane-edit { display:none; }

        .st-pane-title { font-size:.7rem; font-weight:700; color:var(--muted); padding:.3rem .7rem;
            background:#fbfcfe; border-bottom:1px solid var(--b2); display:flex; align-items:center;
            justify-content:space-between; flex-shrink:0; }
        .st-stage.view-edit .st-pane-title, .st-stage.view-preview .st-pane-title { display:none; }

        /* ---------- ورقة التحرير ---------- */
        .st-desk { flex:1; min-height:0; overflow:auto; background:var(--desk); padding:18px 14px 28px;
            display:flex; justify-content:center; align-items:flex-start; }
        .st-sheet { background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(15,20,40,.14);
            width:100%; max-width:560px; padding:1.1rem; }
        .st-sheet-label { font-size:.72rem; font-weight:700; color:var(--muted); margin-bottom:.4rem; display:block; }

        .st-body { width:100%; border:1px solid var(--b); border-radius:9px; padding:.8rem;
            font-size:.95rem; line-height:2.1; min-height:190px; resize:vertical; color:#1f2430; outline:none; }
        .st-body:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(115,103,240,.12); }

        .st-meter { margin-top:.7rem; }
        .st-meter-bar { height:6px; border-radius:6px; background:#eef1f6; overflow:hidden; }
        .st-meter-fill { height:100%; width:0; background:var(--accent); transition:width .15s, background .15s; }
        .st-meter-fill.warn { background:#f59e0b; }
        .st-meter-text { font-size:.75rem; color:var(--muted); margin-top:.35rem; line-height:1.7; }
        .st-meter-text b { color:var(--ink); }

        /* ---------- المعاينة ---------- */
        .st-phone-desk { flex:1; min-height:0; overflow:auto; background:var(--desk);
            padding:18px 14px 28px; display:flex; justify-content:center; align-items:flex-start; }
        .st-phone { width:100%; max-width:310px; background:#263043; border-radius:24px; padding:10px 9px 12px;
            box-shadow:0 6px 24px rgba(15,20,40,.24); }
        .st-phone-notch { width:76px; height:4px; border-radius:4px; background:#141b28; margin:0 auto 9px; }
        /* الشاشة تكبر بمحتواها: الارتفاع الثابت كان يترك فراغاً أسود واسعاً
           تحت الرسالة فيبدو الإطار فارغاً */
        .st-phone-screen { background:#eceff5; border-radius:14px; padding:.8rem .65rem; min-height:150px; }
        .st-sender { font-size:.72rem; color:#6b7290; font-weight:700; text-align:center; margin-bottom:.7rem; }
        .st-bubble { background:#fff; border-radius:16px 16px 16px 5px; padding:.75rem .9rem;
            box-shadow:0 2px 8px rgba(15,20,40,.1); font-size:.88rem; line-height:2;
            color:#1f2430; white-space:pre-wrap; word-break:break-word; }
        .st-bubble.empty { color:#9aa0b5; font-style:italic; }
        .st-bubble + .st-bubble { margin-top:.4rem; border-radius:16px 5px 16px 5px; }
        .st-part-label { font-size:.66rem; color:#8a90a6; margin:.5rem 0 .15rem; text-align:center; }
        .st-phone-foot { font-size:.68rem; color:#93a0b8; text-align:center; margin-top:.6rem; }

        /* ---------- مفتاح ---------- */
        .st-switch { display:flex; align-items:center; gap:.55rem; cursor:pointer; margin:0 0 .6rem;
            font-size:.78rem; font-weight:500; color:#444a60; }
        .st-switch input { position:absolute; opacity:0; width:0; height:0; }
        .st-switch-track { display:flex; align-items:center; flex:0 0 auto; width:34px; height:19px;
            border-radius:19px; background:#d3d8e4; padding:2px; transition:background .15s; }
        .st-switch-dot { width:15px; height:15px; border-radius:50%; background:#fff;
            box-shadow:0 1px 2px rgba(20,25,45,.3); margin-inline-start:0; transition:margin-inline-start .15s; }
        .st-switch input:checked + .st-switch-track { background:var(--accent); }
        .st-switch input:checked + .st-switch-track .st-switch-dot { margin-inline-start:15px; }

        /* ---------- شريط الحفظ ---------- */
        .st-actions { background:#fff; border:1px solid var(--b); border-radius:10px; padding:.6rem 1rem;
            margin-top:.7rem; display:flex; justify-content:space-between; align-items:center;
            gap:1rem; flex-wrap:wrap; }
        .st-status { font-size:.79rem; color:var(--muted); display:flex; align-items:center; gap:.35rem; }
        .st-status.is-dirty { color:#b45309; }
        .st-status.is-ok { color:#15803d; }
        .st-status.is-error { color:#b91c1c; }
    </style>
@endsection

@section('content')
<div class="st" dir="rtl">

    <div class="st-head">
        <div>
            <h2>{{ $template->exists ? $template->name : __('settings.sms_templates.new_title') }}</h2>
            <div class="st-tags">
                <span>{{ $events[$template->event] ?? __('settings.sms_templates.no_event') }}</span>
                @if ($template->exists)
                    <span><code style="font-size:.68rem">{{ $template->code }}</code></span>
                    <span style="{{ $template->is_active ? 'background:#eaf8f0;color:#1c6b40;border-color:#c7e9d5' : 'background:#fdeeee;color:#a13333;border-color:#f5cccc' }}">
                        {{ $template->is_active ? __('settings.common.enabled') : __('settings.common.disabled') }}
                    </span>
                @endif
            </div>
        </div>
        <div>
            <a href="{{ route('SmsTemplates.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather icon-arrow-right"></i> {{ __('settings.sms_templates.templates') }}
            </a>
            <button type="submit" form="stForm" class="btn btn-primary btn-sm">
                <i class="feather icon-save"></i> {{ __('settings.sms_templates.save') }}
            </button>
        </div>
    </div>

    <div class="st-guide" id="stGuide">
        <span>{!! __('settings.sms_templates.guide') !!}</span>
        <span class="st-legend">
            <span><i class="lg-var"></i> {{ __('settings.sms_templates.legend_var') }}</span>
            <span><i class="lg-text"></i> {{ __('settings.sms_templates.legend_text') }}</span>
            <span><i class="lg-long"></i> {{ __('settings.sms_templates.legend_long') }}</span>
        </span>
        <button type="button" class="st-guide-close" id="stGuideClose">{{ __('settings.sms_templates.hide') }}</button>
    </div>

    @include('layouts.alerts.error')

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 pr-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" id="stForm"
        action="{{ $template->exists ? route('SmsTemplates.update', $template->id) : route('SmsTemplates.store') }}">
        @csrf
        @if ($template->exists) @method('PUT') @endif

        <div class="st-shell" id="stShell">

            {{-- ==================== الرصيف ==================== --}}
            <aside class="st-card st-dock">
                <div class="st-dock-tabs">
                    <button type="button" class="is-active" data-dock="vars">{{ __('settings.sms_templates.dock_vars') }}</button>
                    <button type="button" data-dock="presets">{{ __('settings.sms_templates.dock_presets') }}</button>
                    <button type="button" data-dock="settings">{{ __('settings.sms_templates.dock_settings') }}</button>
                </div>

                {{-- ---------- المتغيّرات ---------- --}}
                <div class="st-dock-pane is-active" data-dock="vars">
                    <div class="st-hint">{{ __('settings.sms_templates.vars_hint') }}</div>
                    <div class="st-scroll">
                        @foreach ($variableGroups as $group)
                            <div class="st-group-title">
                                <i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['label'] }}
                            </div>
                            @foreach ($group['fields'] as $key => [$label, $sample])
                                <button type="button" class="st-chip js-var" data-token="{{ '{' . $key . '}' }}"
                                    title="{{ __('settings.sms_templates.var_example', ['value' => $sample]) }}">
                                    <span>{{ $label }}</span>
                                    <code>{{ $sample }}</code>
                                </button>
                            @endforeach
                        @endforeach
                        <p class="st-hint mb-0" style="border:0;background:none">
                            {{ __('settings.sms_templates.empty_var_note') }}
                        </p>
                    </div>
                </div>

                {{-- ---------- الصياغات الجاهزة ---------- --}}
                <div class="st-dock-pane" data-dock="presets">
                    <div class="st-hint">{{ __('settings.sms_templates.presets_hint') }}</div>
                    <div class="st-scroll">
                        @foreach ($presets as $key => $preset)
                            <button type="button" class="st-block js-preset" data-body="{{ $preset['body'] }}">
                                <strong>
                                    <i class="feather icon-{{ $preset['icon'] }}" style="color:var(--accent)"></i>
                                    {{ $preset['label'] }}
                                </strong>
                                <small>{{ $preset['hint'] }}</small>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- ---------- الإعدادات ---------- --}}
                <div class="st-dock-pane" data-dock="settings">
                    <div class="st-hint">{{ __('settings.sms_templates.settings_hint') }}</div>
                    <div class="st-scroll">
                        <div class="st-settings">
                            <div class="st-sec">{{ __('settings.sms_templates.template_data') }}</div>

                            <div class="form-group mb-2">
                                <label for="name">{{ __('settings.sms_templates.name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control js-dirty" name="name" id="name" required
                                    value="{{ old('name', $template->name) }}" placeholder="{{ __('settings.sms_templates.name_placeholder') }}">
                            </div>

                            <div class="form-group mb-2">
                                <label for="code">{{ __('settings.sms_templates.code_label') }} <span class="text-danger">*</span></label>
                                {{-- الرمز مقفل على القالب النظامي: الكود يستدعيه به،
                                     وتغييره يُعطّل الميزة بلا رسالة تدلّ على السبب --}}
                                <input type="text" class="form-control js-dirty" name="code" id="code" required dir="ltr"
                                    value="{{ old('code', $template->code) }}" placeholder="due_reminder"
                                    {{ $template->is_system ? 'readonly' : '' }}>
                                <small class="text-muted" style="font-size:.68rem;line-height:1.6;display:block">
                                    {{ $template->is_system ? __('settings.sms_templates.system_hint') : __('settings.sms_templates.code_hint') }}
                                </small>
                            </div>

                            <div class="form-group mb-2">
                                <label for="description">{{ __('settings.sms_templates.description') }}</label>
                                <textarea class="form-control js-dirty" name="description" id="description" rows="2"
                                    placeholder="{{ __('settings.sms_templates.description_placeholder') }}">{{ old('description', $template->description) }}</textarea>
                                <small class="text-muted" style="font-size:.68rem;line-height:1.6;display:block">
                                    {{ __('settings.sms_templates.description_hint') }}
                                </small>
                            </div>

                            <div class="st-sec">{{ __('settings.sms_templates.runtime') }}</div>

                            <div class="form-group mb-2">
                                <label for="event">{{ __('settings.sms_templates.sent_on') }}</label>
                                {{-- الأحداث تجاوزت الستّين، فقائمة مسطّحة لا تُقرأ --}}
                                <select name="event" id="event" class="form-control js-dirty">
                                    <option value="">{{ __('settings.sms_templates.no_event_option') }}</option>
                                    @foreach ($eventGroups as $group)
                                        <optgroup label="{{ $group['label'] }}">
                                            @foreach ($group['events'] as $key => $label)
                                                <option value="{{ $key }}"
                                                    {{ old('event', $template->event) === $key ? 'selected' : '' }}>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group mb-2">
                                <label for="category">{{ __('settings.sms_templates.category') }}</label>
                                <select name="category" id="category" class="form-control js-dirty">
                                    @foreach ($categories as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ old('category', $template->category ?: 'general') === $key ? 'selected' : '' }}>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <label class="st-switch">
                                <input type="checkbox" name="is_active" value="1" class="js-dirty"
                                    {{ old('is_active', $template->exists ? $template->is_active : true) ? 'checked' : '' }}>
                                <span class="st-switch-track"><span class="st-switch-dot"></span></span>
                                <span>{{ __('settings.sms_templates.is_active') }}</span>
                            </label>

                            @if ($template->exists)
                                <div class="st-sec">{{ __('settings.sms_templates.test_send') }}</div>

                                @unless ($configured)
                                    <div style="font-size:.7rem;color:#8a5b00;background:#fff8e6;
                                        border:1px solid #ffe0a3;border-radius:8px;padding:.5rem;line-height:1.7">
                                        {!! __('settings.sms_templates.gateway_unset', ['link' => '<a href="'.route('Sms.index').'">'.__('settings.sms_templates.sms_settings').'</a>']) !!}
                                    </div>
                                @endunless

                                <div class="form-group mb-2 mt-2">
                                    <label for="testPhone">{{ __('settings.sms_templates.phone') }}</label>
                                    <input type="text" class="form-control" id="testPhone"
                                        placeholder="05xxxxxxxx" dir="ltr">
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btnTest">
                                    <i class="feather icon-send"></i> {{ __('settings.sms_templates.send_this') }}
                                </button>
                                <div id="testResult" class="mt-2" style="font-size:.78rem;line-height:1.7"></div>
                                <small class="text-muted" style="font-size:.68rem;line-height:1.6;display:block;margin-top:.4rem">
                                    {!! __('settings.sms_templates.send_saved_note') !!}
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            </aside>

            {{-- ==================== المسرح ==================== --}}
            <section class="st-card st-main">

                <div class="st-bar">
                    <button type="button" class="st-icon" id="dockToggle" title="{{ __('settings.sms_templates.toggle_dock') }}">
                        <i class="feather icon-sidebar"></i>
                    </button>

                    <span class="st-bar-gap"></span>

                    <div class="st-seg" id="segView">
                        <button type="button" data-view="edit" title="{{ __('settings.sms_templates.view_edit_title') }}">
                            <i class="feather icon-edit-3"></i> {{ __('settings.sms_templates.view_edit') }}
                        </button>
                        <button type="button" class="is-active" data-view="split" title="{{ __('settings.sms_templates.view_split_title') }}">
                            <i class="feather icon-columns"></i> {{ __('settings.sms_templates.view_split') }}
                        </button>
                        <button type="button" data-view="preview" title="{{ __('settings.sms_templates.view_preview_title') }}">
                            <i class="feather icon-eye"></i> {{ __('settings.sms_templates.view_preview') }}
                        </button>
                    </div>
                </div>

                <div class="st-stage view-split" id="stStage">

                    {{-- ---------- التحرير ---------- --}}
                    <div class="st-pane st-pane-edit">
                        <div class="st-pane-title">
                            <span><i class="feather icon-edit-3"></i> {{ __('settings.sms_templates.editing') }}</span>
                            <span style="font-weight:400">{{ __('settings.sms_templates.vars_shown') }}</span>
                        </div>
                        <div class="st-desk">
                            <div class="st-sheet">
                                <label class="st-sheet-label" for="body">{{ __('settings.sms_templates.message_body') }}</label>
                                <textarea name="body" id="body" required
                                    class="st-body js-dirty">{{ old('body', $template->body) }}</textarea>

                                <div class="st-meter">
                                    <div class="st-meter-bar"><div class="st-meter-fill" id="meterFill"></div></div>
                                    <div class="st-meter-text" id="meterText"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ---------- المعاينة ---------- --}}
                    <div class="st-pane st-pane-view">
                        <div class="st-pane-title">
                            <span><i class="feather icon-eye"></i> {{ __('settings.sms_templates.preview') }}</span>
                            <span style="font-weight:400">{{ __('settings.sms_templates.preview_hint') }}</span>
                        </div>
                        <div class="st-phone-desk">
                            <div class="st-phone">
                                <div class="st-phone-notch"></div>
                                <div class="st-phone-screen">
                                    <div class="st-sender">{{ $senderName ?: __('settings.sms_templates.sender_placeholder') }}</div>
                                    <div id="preview"></div>
                                </div>
                                <div class="st-phone-foot" id="previewFoot"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="st-actions">
            <span class="st-status" id="status">
                <i class="feather icon-check-circle"></i> {{ __('settings.sms_templates.ready') }}
            </span>
            <div>
                <a href="{{ route('SmsTemplates.index') }}" class="btn btn-outline-secondary">{{ __('settings.common.cancel') }}</a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-save"></i>
                    {{ $template->exists ? __('settings.sms_templates.save_edits') : __('settings.sms_templates.create') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        'use strict';

        var CSRF = @json(csrf_token());
        var SAMPLES = @json($samples);
        var TEST_URL = @json($template->exists ? route('SmsTemplates.test', $template->id) : null);

        var el = function (id) { return document.getElementById(id); };
        var body = el('body');
        var preview = el('preview');
        var fill = el('meterFill');
        var meter = el('meterText');
        var foot = el('previewFoot');
        var statusEl = el('status');
        var stage = el('stStage');
        var shell = el('stShell');
        var dirty = false;

        function setStatus(icon, text, state) {
            statusEl.className = 'st-status' + (state ? ' is-' + state : '');
            statusEl.innerHTML = '<i class="feather icon-' + icon + '"></i> ' + text;
        }

        function markDirty() {
            dirty = true;
            setStatus('edit-2', @json(__('settings.sms_templates.unsaved')), 'dirty');
        }

        /**
         * العربية تُرسل بترميز Unicode فحدّ الرسالة 70 حرفاً لا 160،
         * والرسالة المجزّأة تُحاسب على كل جزء — الفرق مال حقيقي.
         */
        function measure(value) {
            var unicode = /[^\u0000-\u007F]/.test(value);
            var limit = unicode ? 70 : 160;
            var part = unicode ? 67 : 153;
            var count = value.length === 0 ? 0 : (value.length <= limit ? 1 : Math.ceil(value.length / part));

            return { unicode: unicode, limit: limit, part: part, count: count };
        }

        /** يقسّم النصّ كما تقسّمه البوابة فعلاً */
        function split(value, info) {
            if (info.count <= 1) { return [value]; }

            var parts = [];
            for (var i = 0; i < value.length; i += info.part) {
                parts.push(value.slice(i, i + info.part));
            }
            return parts;
        }

        function render() {
            var raw = body.value;
            var filled = raw;

            Object.keys(SAMPLES).forEach(function (key) {
                filled = filled.split('{' + key + '}').join(SAMPLES[key]);
            });

            // المتغيّر بلا قيمة يُحذف: وصول `{customer}` للعميل أسوأ من نقص كلمة
            filled = filled.replace(/\{[a-zA-Z_][a-zA-Z0-9_]*\}/g, '').trim();

            var info = measure(raw);
            preview.innerHTML = '';

            if (!filled) {
                var empty = document.createElement('div');
                empty.className = 'st-bubble empty';
                empty.textContent = @json(__('settings.sms_templates.preview_empty'));
                preview.appendChild(empty);
            } else {
                var sentInfo = measure(filled);
                split(filled, sentInfo).forEach(function (chunk, index, all) {
                    if (all.length > 1) {
                        var label = document.createElement('div');
                        label.className = 'st-part-label';
                        label.textContent = @json(__('settings.sms_templates.part_of')).replace(':index', index + 1).replace(':total', all.length);
                        preview.appendChild(label);
                    }
                    var bubble = document.createElement('div');
                    bubble.className = 'st-bubble';
                    bubble.textContent = chunk;
                    preview.appendChild(bubble);
                });

                foot.textContent = @json(__('settings.sms_templates.sent_info'))
                    .replace(':chars', filled.length).replace(':count', sentInfo.count);
            }

            if (!filled) { foot.textContent = ''; }

            fill.style.width = info.count === 0 ? '0%'
                : Math.min(100, (raw.length / (info.count * info.limit)) * 100) + '%';
            fill.classList.toggle('warn', info.count > 3);

            meter.innerHTML = @json(__('settings.sms_templates.meter'))
                        .replace(':chars', raw.length).replace(':count', info.count)
                    + (info.unicode ? @json(__('settings.sms_templates.meter_unicode')).replace(':limit', info.limit) : '')
                    + (info.count > 3 ? @json(__('settings.sms_templates.meter_long')) : '');
        }

        body.addEventListener('input', function () { render(); markDirty(); });
        render();

        // ---------- إدراج متغيّر ----------
        document.querySelectorAll('.js-var').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var token = chip.dataset.token;
                var start = body.selectionStart || body.value.length;
                var end = body.selectionEnd || start;

                body.value = body.value.slice(0, start) + token + body.value.slice(end);
                body.focus();
                body.selectionStart = body.selectionEnd = start + token.length;
                render();
                markDirty();
            });
        });

        // ---------- صياغة جاهزة ----------
        document.querySelectorAll('.js-preset').forEach(function (button) {
            button.addEventListener('click', function () {
                if (body.value.trim() && !window.confirm(@json(__('settings.sms_templates.confirm_preset')))) { return; }

                body.value = button.dataset.body;
                body.focus();
                render();
                markDirty();
            });
        });

        document.querySelectorAll('.js-dirty').forEach(function (input) {
            input.addEventListener('change', markDirty);
        });

        // ---------- تبويبات الرصيف ----------
        document.querySelectorAll('.st-dock-tabs button').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('.st-dock-tabs button').forEach(function (other) {
                    other.classList.toggle('is-active', other === button);
                });
                document.querySelectorAll('.st-dock-pane').forEach(function (pane) {
                    pane.classList.toggle('is-active', pane.dataset.dock === button.dataset.dock);
                });
            });
        });

        // ---------- أوضاع العرض ----------
        function setViewMode(mode) {
            stage.className = 'st-stage view-' + mode;

            document.querySelectorAll('#segView button').forEach(function (button) {
                button.classList.toggle('is-active', button.dataset.view === mode);
            });

            localStorage.setItem('sms.view', mode);
        }

        document.querySelectorAll('#segView button').forEach(function (button) {
            button.addEventListener('click', function () { setViewMode(button.dataset.view); });
        });

        setViewMode(localStorage.getItem('sms.view') || 'split');

        el('dockToggle').addEventListener('click', function () {
            var closed = shell.classList.toggle('dock-closed');
            this.classList.toggle('is-off', closed);
            localStorage.setItem('sms.dock', closed ? 'closed' : 'open');
        });

        if (localStorage.getItem('sms.dock') === 'closed') {
            shell.classList.add('dock-closed');
            el('dockToggle').classList.add('is-off');
        }

        // ---------- شريط الإرشاد ----------
        if (localStorage.getItem('sms.guide') === 'off') { el('stGuide').style.display = 'none'; }
        el('stGuideClose').addEventListener('click', function () {
            el('stGuide').style.display = 'none';
            localStorage.setItem('sms.guide', 'off');
        });

        // ---------- الإرسال التجريبي ----------
        var testButton = el('btnTest');

        if (testButton && TEST_URL) {
            testButton.addEventListener('click', function () {
                var phone = el('testPhone').value.trim();
                var result = el('testResult');

                if (!phone) {
                    result.innerHTML = '<span style="color:#b45309">' + @json(__('settings.sms_templates.enter_phone')) + '</span>';
                    return;
                }

                testButton.disabled = true;
                result.innerHTML = '<span style="color:#79809a">' + @json(__('settings.sms_templates.sending')) + '</span>';

                var payload = new FormData();
                payload.append('_token', CSRF);
                payload.append('phone', phone);

                fetch(TEST_URL, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: payload
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        testButton.disabled = false;
                        result.innerHTML = '<span style="color:' + (data.success ? '#15803d' : '#b91c1c') + '">'
                            + (data.message || '') + '</span>';
                    })
                    .catch(function () {
                        testButton.disabled = false;
                        result.innerHTML = '<span style="color:#b91c1c">' + @json(__('settings.common.connection_failed')) + '</span>';
                    });
            });
        }

        // ---------- الحفظ ----------
        el('stForm').addEventListener('submit', function () { dirty = false; });

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                el('stForm').requestSubmit();
            }
        });

        window.addEventListener('beforeunload', function (event) {
            if (!dirty) { return; }
            event.preventDefault();
            event.returnValue = '';
        });
    })();
</script>
@endsection
