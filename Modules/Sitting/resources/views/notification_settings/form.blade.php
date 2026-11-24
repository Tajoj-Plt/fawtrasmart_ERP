@extends('sitting::master')

@section('title', __('settings.notify.edit_title', ['name' => $setting->label]))

@section('content')
    <link rel="stylesheet" type="text/css" href="{{ asset('css/online-store.css') }}">

    <style>
        .ns-switch { display: flex; align-items: center; gap: .6rem; cursor: pointer; margin: 0; }
        .ns-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
        .ns-switch .track {
            position: relative; flex: 0 0 auto; width: 46px; height: 24px;
            background: #d9dee5; border-radius: 999px; transition: background .18s;
        }
        .ns-switch .track::after {
            content: ''; position: absolute; top: 3px; right: 3px;
            width: 18px; height: 18px; background: #fff; border-radius: 50%;
            transition: transform .18s; box-shadow: 0 1px 3px rgba(0,0,0,.25);
        }
        .ns-switch input:checked + .track { background: #28c76f; }
        .ns-switch input:checked + .track::after { transform: translateX(-22px); }
        .ns-switch .txt { display: flex; flex-direction: column; line-height: 1.35; }
        .ns-switch .txt small { color: #6b7280; font-size: .75rem; }

        /* بطاقة اختيار: المربع والنص في سطر واحد — لا يهبط النص تحت المربع */
        .ns-pick {
            display: flex; align-items: flex-start; gap: .65rem; margin: 0 0 .6rem;
            padding: .7rem .85rem; border: 1px solid #e6e9ef; border-radius: 9px;
            cursor: pointer; transition: border-color .15s, background .15s;
        }
        .ns-pick:hover { border-color: #b9c6d8; background: #fafbfc; }
        .ns-pick input { margin: .2rem 0 0; flex: 0 0 auto; width: 16px; height: 16px; }
        .ns-pick.on { border-color: #7367f0; background: #f6f5ff; }
        .ns-pick .lbl { font-weight: 600; font-size: .9rem; }
        .ns-pick .hint { display: block; color: #6b7280; font-size: .76rem; margin-top: .1rem; }
        .ns-pick.locked { opacity: .75; cursor: not-allowed; background: #f7f8fa; }

        .ns-stat { text-align: center; padding: .7rem .4rem; border-inline-end: 1px solid #eef0f4; }
        .ns-stat:last-child { border-inline-end: 0; }
        .ns-stat .v { font-size: 1.35rem; font-weight: 700; }
        .ns-stat .l { font-size: .75rem; color: #6b7280; }
        .ns-warn { font-size: .8rem; color: #8a5b00; background: #fff8e6;
            border: 1px solid #ffe0a3; border-radius: 8px; padding: .5rem .7rem; margin-top: .5rem; }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-bell"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ $setting->label }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('NotificationSettings.index') }}">{{ __('settings.notify.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.common.edit') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('NotificationSettings.index') }}" class="btn btn-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.notify.back_to_list') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form method="POST" action="{{ route('NotificationSettings.update', $setting->id) }}">
            @csrf @method('PUT')

            <div class="row">
                {{-- ============ العمود الأيمن: التعريف والقنوات ============ --}}
                <div class="col-lg-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-info"></i> {{ __('settings.notify.definition') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7 col-12 mb-1">
                                    <label>{{ __('settings.notify.name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                                        value="{{ old('label', $setting->label) }}" required>
                                    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-5 col-12 mb-1">
                                    <label>{{ __('settings.notify.category') }}</label>
                                    <select name="category" class="form-control">
                                        @foreach ($categories as $key => $meta)
                                            <option value="{{ $key }}"
                                                {{ old('category', $setting->category) === $key ? 'selected' : '' }}>
                                                {{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12 mb-1">
                                    <label>{{ __('settings.notify.description') }}</label>
                                    <input type="text" name="description" class="form-control"
                                        value="{{ old('description', $setting->description) }}"
                                        placeholder="{{ __('settings.notify.description_placeholder') }}">
                                </div>

                                <div class="col-12">
                                    <label>{{ __('settings.notify.code_key') }}</label>
                                    <input type="text" class="form-control" value="{{ $setting->key }}" readonly
                                        style="direction:ltr;background:#f7f8fa">
                                    <small class="text-muted">
                                        {!! __('settings.notify.code_key_hint', ['count' => number_format($stats['total'])]) !!}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-send"></i> {{ __('settings.notify.send_channels') }}</h4>
                        </div>
                        <div class="card-body">
                            @php $active = old('channels', $setting->channels ?: ['database']); @endphp

                            @foreach ($channels as $key => $meta)
                                @php
                                    $isDb = $key === 'database';
                                    $on = $isDb || in_array($key, (array) $active, true);
                                    $ready = $key === 'mail' ? $mailReady : ($key === 'sms' ? $smsReady : true);
                                @endphp
                                <label class="ns-pick {{ $on ? 'on' : '' }} {{ $isDb ? 'locked' : '' }}">
                                    {{-- القناة الداخلية مقفلة: `disabled` يمنع إرسالها، والمتحكّم
                                         يضيفها دائماً — فالقفل بصري وفعلي معاً --}}
                                    <input type="checkbox" name="channels[]" value="{{ $key }}"
                                        {{ $on ? 'checked' : '' }} {{ $isDb ? 'disabled' : '' }}>
                                    <span>
                                        <span class="lbl">
                                            <i class="feather icon-{{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                                            @if ($isDb)
                                                <span class="badge badge-light-secondary" style="font-size:.65rem">{{ __('settings.notify.permanent') }}</span>
                                            @elseif (! $ready)
                                                <span class="badge badge-light-warning" style="font-size:.65rem">{{ __('settings.notify.not_configured') }}</span>
                                            @endif
                                        </span>
                                        <span class="hint">{{ $meta['hint'] }}</span>
                                    </span>
                                </label>
                            @endforeach

                            @unless ($mailReady && $smsReady)
                                <div class="ns-warn">
                                    <i class="fas fa-info-circle"></i>
                                    {{ __('settings.notify.channel_unready') }}
                                    @unless ($mailReady) <a href="{{ route('SMPT.index') }}">{{ __('settings.notify.smtp_settings') }}</a> @endunless
                                    @if (! $mailReady && ! $smsReady) — @endif
                                    @unless ($smsReady) <a href="{{ route('Sms.index') }}">{{ __('settings.notify.sms_settings') }}</a> @endunless
                                </div>
                            @endunless

                            <div class="mt-2">
                                <label>{{ __('settings.notify.sms_template') }} <span class="text-muted">{{ __('settings.notify.optional') }}</span></label>
                                <select name="sms_template_code" class="form-control">
                                    <option value="">{{ __('settings.notify.no_template') }}</option>
                                    @foreach ($smsTemplates as $tpl)
                                        <option value="{{ $tpl->code }}"
                                            {{ old('sms_template_code', $setting->sms_template_code) === $tpl->code ? 'selected' : '' }}>
                                            {{ $tpl->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    {!! __('settings.notify.templates_managed', ['link' => '<a href="'.route('SmsTemplates.index').'">'.__('settings.notify.sms_templates_link').'</a>']) !!}
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-users"></i> {{ __('settings.notify.recipients_title') }}</h4>
                        </div>
                        <div class="card-body">
                            @php $picked = old('recipients', $setting->recipients ?: ['receiver']); @endphp

                            @foreach ($recipients as $key => $meta)
                                @php $on = in_array($key, (array) $picked, true); @endphp
                                <label class="ns-pick {{ $on ? 'on' : '' }}">
                                    <input type="checkbox" name="recipients[]" value="{{ $key }}" {{ $on ? 'checked' : '' }}>
                                    <span>
                                        <span class="lbl">{{ $meta['label'] }}</span>
                                        <span class="hint">{{ $meta['hint'] }}</span>
                                    </span>
                                </label>
                            @endforeach

                            <div class="ns-warn">
                                <i class="fas fa-info-circle"></i>
                                {{ __('settings.notify.recipients_hint') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ العمود الأيسر: التشغيل والإحصاء والفحص ============ --}}
                <div class="col-lg-4 col-12">
                    <div class="card">
                        <div class="card-body">
                            <label class="ns-switch">
                                <input type="checkbox" name="is_enabled" value="1"
                                    {{ old('is_enabled', $setting->is_enabled) ? 'checked' : '' }}>
                                <span class="track"></span>
                                <span class="txt">
                                    <strong>{{ __('settings.notify.enable_this') }}</strong>
                                    <small>{{ __('settings.notify.enable_this_hint') }}</small>
                                </span>
                            </label>

                            <hr>

                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="feather icon-save"></i> {{ __('settings.notify.save') }}
                            </button>
                            <button type="button" class="btn btn-outline-info btn-block mt-1" id="btnProbe">
                                <i class="feather icon-send"></i> {{ __('settings.notify.send_test') }}
                            </button>
                            <div id="probeOut" class="mt-1" style="display:none;font-size:.82rem;white-space:pre-line"></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0"><i class="feather icon-bar-chart-2"></i> {{ __('settings.notify.usage') }}</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="d-flex">
                                <div class="ns-stat flex-fill">
                                    <div class="v">{{ number_format($stats['total']) }}</div>
                                    <div class="l">{{ __('settings.notify.total') }}</div>
                                </div>
                                <div class="ns-stat flex-fill">
                                    <div class="v">{{ number_format($stats['last30']) }}</div>
                                    <div class="l">{{ __('settings.notify.last_30') }}</div>
                                </div>
                                <div class="ns-stat flex-fill">
                                    <div class="v">{{ number_format($stats['unread']) }}</div>
                                    <div class="l">{{ __('settings.notify.unread') }}</div>
                                </div>
                            </div>

                            @if ($stats['latest'])
                                <div style="border-top:1px solid #eef0f4;padding:.75rem 1rem">
                                    <div class="text-muted" style="font-size:.72rem">{{ __('settings.notify.last_of_type') }}</div>
                                    <div style="font-size:.85rem;font-weight:600">{{ $stats['latest']->title }}</div>
                                    <div class="text-muted" style="font-size:.78rem">
                                        {{ \Illuminate\Support\Str::limit($stats['latest']->description, 90) }}
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;margin-top:.25rem">
                                        {{ \Carbon\Carbon::parse($stats['latest']->created_at)->diffForHumans() }}
                                    </div>
                                </div>
                            @else
                                <div style="border-top:1px solid #eef0f4;padding:.9rem 1rem;font-size:.82rem"
                                    class="text-muted">
                                    {{ __('settings.notify.none_recorded') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($stats['total'] > 10000)
                        <div class="ns-warn">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ __('settings.notify.heavy_type', ['count' => number_format($stats['total'])]) }}
                        </div>
                    @endif
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
            (function () {
                // إبراز البطاقة المختارة بصرياً: الحدّ واللون يتبعان المربع
                document.querySelectorAll('.ns-pick input[type=checkbox]').forEach(function (box) {
                    box.addEventListener('change', function () {
                        box.closest('.ns-pick').classList.toggle('on', box.checked);
                    });
                });

                var btn = document.getElementById('btnProbe');
                var out = document.getElementById('probeOut');

                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="feather icon-loader"></i> ' + @json(__('settings.notify.sending'));
                    out.style.display = 'none';

                    fetch('{{ route('NotificationSettings.test', $setting->id) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            out.style.display = 'block';
                            out.className = 'mt-1 alert ' + (d.success ? 'alert-success' : 'alert-warning');
                            out.textContent = d.message;
                        })
                        .catch(function (e) {
                            out.style.display = 'block';
                            out.className = 'mt-1 alert alert-danger';
                            out.textContent = @json(__('settings.notify.check_failed')).replace(':error', e.message);
                        })
                        .finally(function () {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="feather icon-send"></i> {{ __('settings.notify.send_test') }}';
                        });
                });
            })();
    </script>
@endsection
