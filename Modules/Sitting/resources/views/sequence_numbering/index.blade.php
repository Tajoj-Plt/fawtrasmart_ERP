@extends('sitting::master')

@section('title')
    {{ __('settings.sequence.title') }}
@stop

@section('css')
    <style>
        .seq-wrap {
            --seq-border: #e6e9f0;
            --seq-muted: #7b8194;
            --seq-bg: #f7f8fc;
            --seq-accent: var(--app-accent-color, #7367F0);
        }

        /* ==================== الترويسة ==================== */
        .seq-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .seq-head h2 {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0 0 .25rem;
        }

        .seq-head p {
            color: var(--seq-muted);
            font-size: .875rem;
            margin: 0;
        }

        /* ==================== التخطيط ==================== */
        .seq-grid {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            gap: 1.25rem;
            align-items: start;
        }

        @media (max-width: 991px) {
            .seq-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .seq-panel {
            background: #fff;
            border: 1px solid var(--seq-border);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(20, 25, 45, .05);
        }

        /* ==================== قائمة الأقسام ==================== */
        .seq-aside {
            position: sticky;
            top: 90px;
            max-height: calc(100vh - 120px);
            display: flex;
            flex-direction: column;
        }

        .seq-search {
            padding: .875rem;
            border-bottom: 1px solid var(--seq-border);
        }

        .seq-search input {
            width: 100%;
            border: 1px solid var(--seq-border);
            border-radius: 7px;
            padding: .5rem .75rem;
            font-size: .85rem;
            outline: none;
            transition: border-color .15s;
        }

        .seq-search input:focus {
            border-color: var(--seq-accent);
        }

        .seq-list {
            overflow-y: auto;
            padding: .5rem 0 .75rem;
            flex: 1;
        }

        .seq-group-title {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: var(--seq-muted);
            text-transform: uppercase;
            padding: .875rem 1rem .375rem;
        }

        .seq-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .5rem 1rem;
            font-size: .85rem;
            color: #4a4f61;
            text-decoration: none;
            border-right: 3px solid transparent;
            transition: background .12s, color .12s;
        }

        .seq-item:hover {
            background: var(--seq-bg);
            color: var(--seq-accent);
            text-decoration: none;
        }

        .seq-item.is-active {
            background: color-mix(in srgb, var(--seq-accent) 9%, #fff);
            color: var(--seq-accent);
            font-weight: 600;
            border-right-color: var(--seq-accent);
        }

        .seq-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #d3d7e3;
            flex: none;
        }

        .seq-dot.is-set {
            background: #28C76F;
        }

        /* ==================== المحتوى ==================== */
        .seq-body {
            padding: 1.5rem;
        }

        .seq-section-title {
            display: flex;
            align-items: center;
            gap: .625rem;
            font-size: .8rem;
            font-weight: 700;
            color: var(--seq-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin: 0 0 1rem;
        }

        .seq-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--seq-border);
        }

        .seq-field {
            margin-bottom: 1.125rem;
        }

        .seq-field label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: #3b4054;
            margin-bottom: .375rem;
        }

        .seq-field .form-control,
        .seq-field select {
            border: 1px solid var(--seq-border);
            border-radius: 7px;
            padding: .5rem .75rem;
            font-size: .875rem;
            width: 100%;
            height: auto;
            transition: border-color .15s, box-shadow .15s;
        }

        .seq-field .form-control:focus,
        .seq-field select:focus {
            border-color: var(--seq-accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--seq-accent) 15%, transparent);
            outline: none;
        }

        .seq-hint {
            display: block;
            font-size: .75rem;
            color: var(--seq-muted);
            margin-top: .3rem;
        }

        /* ==================== المعاينة ==================== */
        .seq-preview {
            background: linear-gradient(135deg, #1e2235, #2c3150);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            color: #fff;
            position: sticky;
            top: 90px;
        }

        .seq-preview-label {
            font-size: .72rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            opacity: .6;
            margin-bottom: .625rem;
        }

        .seq-preview-value {
            font-family: 'Courier New', ui-monospace, monospace;
            font-size: 1.9rem;
            font-weight: 700;
            letter-spacing: .04em;
            word-break: break-all;
            line-height: 1.25;
            min-height: 2.4rem;
        }

        .seq-preview-next {
            font-family: 'Courier New', ui-monospace, monospace;
            font-size: .9rem;
            opacity: .45;
            margin-top: .5rem;
        }

        .seq-preview-meta {
            border-top: 1px solid rgba(255, 255, 255, .12);
            margin-top: 1.125rem;
            padding-top: .875rem;
            display: flex;
            justify-content: space-around;
            font-size: .75rem;
        }

        .seq-preview-meta span {
            display: block;
            opacity: .55;
            margin-bottom: .15rem;
        }

        .seq-preview-meta strong {
            font-size: .95rem;
            font-weight: 700;
        }

        /* ==================== متفرقات ==================== */
        .seq-token {
            display: inline-block;
            background: var(--seq-bg);
            border: 1px solid var(--seq-border);
            border-radius: 5px;
            padding: .1rem .4rem;
            font-family: ui-monospace, monospace;
            font-size: .72rem;
            color: var(--seq-accent);
            cursor: pointer;
            margin-inline-end: .25rem;
            transition: background .12s;
        }

        .seq-token:hover {
            background: color-mix(in srgb, var(--seq-accent) 12%, #fff);
        }

        .seq-footer {
            border-top: 1px solid var(--seq-border);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            background: #fcfcfe;
            border-radius: 0 0 10px 10px;
        }

        .seq-switch {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .85rem;
            color: #3b4054;
            cursor: pointer;
            margin: 0;
        }
    </style>
@endsection

@section('content')
    <div class="seq-wrap">

        <div class="seq-head">
            <div>
                <h2>{{ __('settings.sequence.title') }}</h2>
                <p>{{ __('settings.sequence.subtitle') }}</p>
            </div>
            <div class="d-flex align-items-center" style="gap:.5rem">
                <span class="badge badge-light-secondary" style="font-size:.75rem">
                    {{ __('settings.sequence.configured_of', ['done' => count($configured), 'total' => count(\App\Support\DocumentSections::keys())]) }}
                </span>
            </div>
        </div>

        @include('layouts.alerts.success')

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="seq-grid">

            {{-- ==================== قائمة الأقسام ==================== --}}
            <div class="seq-panel seq-aside">
                <div class="seq-search">
                    <input type="search" id="seqFilter" placeholder="{{ __('settings.sequence.search') }}" autocomplete="off">
                </div>
                <nav class="seq-list" id="seqList">
                    @foreach ($groups as $groupKey => $group)
                        @continue(empty($group['sections']))
                        <div class="seq-group" data-group="{{ $groupKey }}">
                            <div class="seq-group-title">{{ $group['label'] }}</div>
                            @foreach ($group['sections'] as $key => $meta)
                                <a href="{{ route('SequenceNumbering.index', ['section' => $key]) }}"
                                    class="seq-item {{ $key === $section ? 'is-active' : '' }}"
                                    data-label="{{ $meta['label'] }}">
                                    <span>{{ $meta['label'] }}</span>
                                    <span class="seq-dot {{ array_key_exists($key, $configured) ? 'is-set' : '' }}"
                                        title="{{ array_key_exists($key, $configured) ? __('settings.sequence.is_set') : __('settings.sequence.not_set') }}"></span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                    <p class="text-center text-muted d-none py-3 mb-0" id="seqEmpty" style="font-size:.82rem">
                        {{ __('settings.sequence.no_section') }}
                    </p>
                </nav>
            </div>

            {{-- ==================== نموذج الإعدادات ==================== --}}
            <form action="{{ route('SequenceNumbering.store') }}" method="POST" id="seqForm">
                @csrf
                <input type="hidden" name="section" value="{{ $section }}">

                <div class="seq-panel">
                    <div class="seq-body">

                        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4" style="gap:.75rem">
                            <div>
                                <h4 class="mb-1" style="font-size:1.1rem;font-weight:700">
                                    {{ \App\Support\DocumentSections::label($section) }}
                                </h4>
                                <code style="font-size:.75rem;color:#7b8194">{{ $section }}</code>
                            </div>
                            @unless (array_key_exists($section, $configured))
                                <span class="badge badge-light-warning">{{ __('settings.sequence.not_set_badge') }}</span>
                            @endunless
                        </div>

                        <div class="row">
                            {{-- ---------- الإعدادات ---------- --}}
                            <div class="col-lg-7">

                                <div class="seq-section-title">{{ __('settings.sequence.format') }}</div>

                                <div class="row">
                                    <div class="col-sm-5 seq-field">
                                        <label for="prefix">{{ __('settings.sequence.prefix') }}</label>
                                        <input type="text" class="form-control" id="prefix" name="prefix" maxlength="20"
                                            value="{{ old('prefix', $setting->prefix) }}" placeholder="INV">
                                        <small class="seq-hint">
                                            {{ __('settings.sequence.date_tokens') }}
                                            <span class="seq-token" data-token="{YYYY}">&#123;YYYY&#125;</span>
                                            <span class="seq-token" data-token="{YY}">&#123;YY&#125;</span>
                                            <span class="seq-token" data-token="{MM}">&#123;MM&#125;</span>
                                            <span class="seq-token" data-token="{DD}">&#123;DD&#125;</span>
                                        </small>
                                    </div>

                                    <div class="col-sm-3 seq-field">
                                        <label for="separator">{{ __('settings.sequence.separator') }}</label>
                                        <input type="text" class="form-control" id="separator" name="separator"
                                            maxlength="5" value="{{ old('separator', $setting->separator) }}"
                                            placeholder="-">
                                        <small class="seq-hint">{{ __('settings.sequence.separator_hint') }}</small>
                                    </div>

                                    <div class="col-sm-4 seq-field">
                                        <label for="suffix">{{ __('settings.sequence.suffix') }}</label>
                                        <input type="text" class="form-control" id="suffix" name="suffix" maxlength="20"
                                            value="{{ old('suffix', $setting->suffix) }}" placeholder="{{ __('settings.sequence.suffix_placeholder') }}">
                                        <small class="seq-hint">{{ __('settings.sequence.suffix_hint') }}</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-sm-6 seq-field">
                                        <label for="number_of_digits">{{ __('settings.sequence.digits') }} <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="number_of_digits"
                                            name="number_of_digits" min="1" max="12" required
                                            value="{{ old('number_of_digits', $setting->number_of_digits ?: 6) }}">
                                        <small class="seq-hint">{{ __('settings.sequence.digits_hint') }}</small>
                                    </div>

                                    <div class="col-sm-6 seq-field">
                                        <label for="mode">{{ __('settings.sequence.mode') }} <span class="text-danger">*</span></label>
                                        <select class="form-control" id="mode" name="mode" required>
                                            @foreach (__('settings.sequence.modes') as $value => $label)
                                                <option value="{{ $value }}"
                                                    {{ (int) old('mode', $setting->mode) === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="seq-section-title">{{ __('settings.sequence.counter') }}</div>

                                <div class="row">
                                    <div class="col-sm-6 seq-field">
                                        <label for="current_number">{{ __('settings.sequence.current_number') }} <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="current_number" name="current_number"
                                            min="0" max="99999999" required
                                            value="{{ old('current_number', $setting->current_number ?? 0) }}">
                                        <small class="seq-hint">
                                            {{ __('settings.sequence.current_number_hint') }}
                                            @if ($lastFromTable > 0)
                                                {!! __('settings.sequence.highest_id', ['id' => $lastFromTable]) !!}
                                            @endif
                                        </small>
                                    </div>

                                    <div class="col-sm-6 seq-field">
                                        <label for="reset_period">{{ __('settings.sequence.reset_period') }}</label>
                                        <select class="form-control" id="reset_period" name="reset_period">
                                            @foreach (__('settings.sequence.reset_periods') as $value => $label)
                                                <option value="{{ $value }}"
                                                    {{ old('reset_period', $setting->effective_reset_period) === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="seq-hint">
                                            {!! __('settings.sequence.reset_hint') !!}
                                        </small>
                                    </div>
                                </div>

                            </div>

                            {{-- ---------- المعاينة ---------- --}}
                            <div class="col-lg-5">
                                <div class="seq-preview">
                                    <div class="seq-preview-label">{{ __('settings.sequence.next_number') }}</div>
                                    <div class="seq-preview-value" id="previewNext">{{ $previewNext }}</div>
                                    <div class="seq-preview-next">{{ __('settings.sequence.then') }} <span id="previewAfter">…</span></div>

                                    <div class="seq-preview-meta">
                                        <div>
                                            <span>{{ __('settings.sequence.digits_meta') }}</span>
                                            <strong id="metaDigits">{{ $setting->number_of_digits ?: 6 }}</strong>
                                        </div>
                                        <div>
                                            <span>{{ __('settings.sequence.reset_meta') }}</span>
                                            <strong id="metaReset">
                                                {{ __('settings.sequence.reset_short.'.$setting->effective_reset_period) }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="seq-footer">
                        <label class="seq-switch">
                            <input type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $setting->is_active ?? true) ? 'checked' : '' }}>
                            <span>{{ __('settings.sequence.auto_enabled') }}</span>
                        </label>
                        <div>
                            <a href="{{ route('SittingAccount.dashboard') }}" class="btn btn-outline-secondary">{{ __('settings.common.cancel') }}</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> {{ __('settings.sequence.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            'use strict';

            // ---------- تصفية قائمة الأقسام ----------
            var filter = document.getElementById('seqFilter');
            var groups = Array.prototype.slice.call(document.querySelectorAll('#seqList .seq-group'));
            var empty = document.getElementById('seqEmpty');

            if (filter) {
                filter.addEventListener('input', function () {
                    var term = filter.value.trim();
                    var anyVisible = false;

                    groups.forEach(function (group) {
                        var visibleInGroup = 0;

                        group.querySelectorAll('.seq-item').forEach(function (item) {
                            var match = !term || item.dataset.label.indexOf(term) !== -1;
                            item.style.display = match ? '' : 'none';
                            if (match) { visibleInGroup++; }
                        });

                        group.style.display = visibleInGroup ? '' : 'none';
                        if (visibleInGroup) { anyVisible = true; }
                    });

                    empty.classList.toggle('d-none', anyVisible);
                });
            }

            // ---------- إدراج رمز تاريخ في البادئة ----------
            var prefixInput = document.getElementById('prefix');

            document.querySelectorAll('.seq-token').forEach(function (token) {
                token.addEventListener('click', function () {
                    prefixInput.value += token.dataset.token;
                    prefixInput.dispatchEvent(new Event('input'));
                    prefixInput.focus();
                });
            });

            // ---------- المعاينة الحية ----------
            // تُحسب على الخادم بنفس دالة التوليد، فلا ينحرف المعروض عن الناتج الحقيقي.
            var fields = ['prefix', 'separator', 'suffix', 'number_of_digits', 'mode', 'current_number']
                .map(function (id) { return document.getElementById(id); })
                .filter(Boolean);

            var previewNext = document.getElementById('previewNext');
            var previewAfter = document.getElementById('previewAfter');
            var metaDigits = document.getElementById('metaDigits');
            var metaReset = document.getElementById('metaReset');
            var resetSelect = document.getElementById('reset_period');
            var resetLabels = @json(__('settings.sequence.reset_short'));
            var timer = null;
            var inFlight = null;

            function refreshPreview() {
                if (inFlight) { inFlight.abort(); }

                var controller = new AbortController();
                inFlight = controller;

                var payload = new FormData();
                payload.append('_token', document.querySelector('input[name="_token"]').value);
                fields.forEach(function (field) { payload.append(field.name, field.value); });

                fetch('{{ route('SequenceNumbering.preview') }}', {
                    method: 'POST',
                    body: payload,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) {
                        if (!data) { return; }
                        previewNext.textContent = data.next;
                        previewAfter.textContent = data.after;
                    })
                    .catch(function () { /* طلب أُلغي أو فشل: تبقى المعاينة السابقة */ });
            }

            fields.forEach(function (field) {
                field.addEventListener('input', function () {
                    metaDigits.textContent = document.getElementById('number_of_digits').value || '6';
                    clearTimeout(timer);
                    timer = setTimeout(refreshPreview, 250);
                });
                field.addEventListener('change', refreshPreview);
            });

            if (resetSelect) {
                resetSelect.addEventListener('change', function () {
                    metaReset.textContent = resetLabels[resetSelect.value] || resetLabels.never;
                });
            }

            refreshPreview();
        })();
    </script>
@endsection
