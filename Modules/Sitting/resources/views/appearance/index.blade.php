@extends('sitting::master')

@section('title')
    {{ __('settings.appearance.title') }}
@stop

@section('css')
    <style>
        .ap {
            --b: #e6e9f0;
            --muted: #7b8194;
            --soft: #f7f8fc;
            --accent: var(--app-primary, #7367F0);
        }

        /* ==================== الترويسة ==================== */
        .ap-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .ap-head h2 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0 0 .3rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .ap-head p {
            color: var(--muted);
            font-size: .875rem;
            margin: 0;
            max-width: 62ch;
        }

        /* ==================== التخطيط ==================== */
        .ap-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 430px;
            gap: 1.25rem;
            align-items: start;
        }

        @media (max-width: 1299px) {
            .ap-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .ap-card {
            background: #fff;
            border: 1px solid var(--b);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(20, 25, 45, .05);
            overflow: hidden;
        }

        /* ==================== التبويبات ==================== */
        .ap-tabs {
            display: flex;
            border-bottom: 1px solid var(--b);
            background: var(--soft);
            overflow-x: auto;
        }

        .ap-tab {
            flex: 1;
            min-width: 118px;
            border: 0;
            background: none;
            padding: .875rem .75rem;
            font-size: .84rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            transition: color .15s, background .15s;
            white-space: nowrap;
        }

        .ap-tab:hover {
            color: var(--accent);
            background: rgba(0, 0, 0, .02);
        }

        .ap-tab.is-active {
            color: var(--accent);
            border-bottom-color: var(--accent);
            background: #fff;
        }

        .ap-pane {
            display: none;
            padding: 1.5rem;
        }

        .ap-pane.is-active {
            display: block;
        }

        /* ==================== اللوحات الجاهزة ==================== */
        .ap-preset-group + .ap-preset-group {
            margin-top: 1.25rem;
        }

        .ap-preset-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .05em;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: .625rem;
        }

        .ap-presets {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(158px, 1fr));
            gap: .75rem;
        }

        .ap-preset {
            border: 2px solid var(--b);
            border-radius: 10px;
            padding: 0;
            cursor: pointer;
            background: #fff;
            text-align: start;
            overflow: hidden;
            transition: border-color .15s, transform .12s, box-shadow .15s;
        }

        .ap-preset:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(20, 25, 45, .1);
        }

        .ap-preset.is-active {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(115, 103, 240, .16);
        }

        .ap-preset-mini {
            display: flex;
            height: 54px;
        }

        .ap-preset-mini-side {
            width: 32%;
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 6px 5px;
        }

        .ap-preset-mini-side i {
            height: 5px;
            border-radius: 2px;
            opacity: .5;
        }

        .ap-preset-mini-side i:first-child {
            opacity: 1;
        }

        .ap-preset-mini-main {
            flex: 1;
            padding: 7px 6px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .ap-preset-mini-bar {
            height: 6px;
            border-radius: 3px;
        }

        .ap-preset-name {
            font-size: .76rem;
            font-weight: 600;
            color: #3b4054;
            padding: .5rem .625rem;
            border-top: 1px solid var(--b);
        }

        /* ==================== حقول الألوان ==================== */
        .ap-color {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 0;
            border-bottom: 1px dashed var(--b);
        }

        .ap-color:last-of-type {
            border-bottom: 0;
        }

        .ap-swatch {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            border: 1px solid var(--b);
            padding: 0;
            cursor: pointer;
            flex: none;
            background: none;
        }

        .ap-color-info {
            flex: 1;
            min-width: 0;
        }

        .ap-color-info strong {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: #3b4054;
        }

        .ap-color-info small {
            color: var(--muted);
            font-size: .74rem;
        }

        .ap-hex {
            width: 100px;
            border: 1px solid var(--b);
            border-radius: 7px;
            padding: .4rem .5rem;
            font-family: ui-monospace, monospace;
            font-size: .78rem;
            text-transform: uppercase;
            text-align: center;
            flex: none;
        }

        .ap-hex.is-invalid {
            border-color: #EA5455;
            background: #fdf2f2;
        }

        /* ---------- شارة التباين ---------- */
        .ap-contrast {
            margin-top: .875rem;
            border-radius: 9px;
            padding: .75rem .875rem;
            display: flex;
            align-items: center;
            gap: .625rem;
            font-size: .8rem;
            line-height: 1.5;
        }

        .ap-contrast.is-good {
            background: #eaf8f0;
            color: #1c6b40;
        }

        .ap-contrast.is-warn {
            background: #fff6e5;
            color: #8a5b00;
        }

        .ap-contrast.is-bad {
            background: #fdeaea;
            color: #9b2020;
        }

        .ap-contrast i {
            font-size: 1.1rem;
            flex: none;
        }

        .ap-contrast b {
            font-family: ui-monospace, monospace;
        }

        /* ==================== حقول عامة ==================== */
        .ap-field {
            margin-bottom: 1.25rem;
        }

        .ap-field > label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: #3b4054;
            margin-bottom: .4rem;
        }

        .ap-field .form-control {
            border: 1px solid var(--b);
            border-radius: 7px;
            padding: .5rem .75rem;
            font-size: .875rem;
            height: auto;
        }

        .ap-hint {
            display: block;
            font-size: .75rem;
            color: var(--muted);
            margin-top: .35rem;
        }

        .ap-section-title {
            display: flex;
            align-items: center;
            gap: .625rem;
            font-size: .76rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin: 0 0 1rem;
        }

        .ap-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--b);
        }

        .ap-section-title:not(:first-child) {
            margin-top: 1.75rem;
        }

        .ap-range-row {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .ap-range-row input[type="range"] {
            flex: 1;
            accent-color: var(--accent);
        }

        .ap-range-value {
            min-width: 58px;
            text-align: center;
            font-family: ui-monospace, monospace;
            font-size: .82rem;
            font-weight: 700;
            background: var(--soft);
            border-radius: 7px;
            padding: .35rem .4rem;
        }

        /* ---------- بطاقات الاختيار ---------- */
        .ap-choices {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: .75rem;
        }

        .ap-choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .ap-choice span {
            display: block;
            border: 2px solid var(--b);
            border-radius: 10px;
            padding: .875rem .75rem;
            text-align: center;
            font-size: .82rem;
            font-weight: 600;
            color: #3b4054;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }

        .ap-choice span small {
            display: block;
            font-weight: 400;
            font-size: .72rem;
            color: var(--muted);
            margin-top: .2rem;
        }

        .ap-choice input:checked + span {
            border-color: var(--accent);
            background: rgba(115, 103, 240, .06);
        }

        .ap-choice-chip {
            display: block;
            height: 30px;
            border-radius: 6px;
            margin-bottom: .5rem;
            border: 1px solid var(--b);
        }

        /* ---------- معاينة الخط ---------- */
        .ap-font-sample {
            border: 1px solid var(--b);
            border-radius: 9px;
            padding: 1rem 1.125rem;
            background: var(--soft);
            margin-top: .875rem;
        }

        .ap-font-sample .sample-h {
            font-size: 1.15rem;
            margin: 0 0 .35rem;
        }

        .ap-font-sample .sample-p {
            margin: 0 0 .5rem;
            color: #4b4b4b;
        }

        .ap-font-sample .sample-n {
            font-family: ui-monospace, monospace;
            color: var(--muted);
            font-size: .8rem;
            margin: 0;
        }

        /* ==================== المعاينة ==================== */
        .ap-preview {
            position: sticky;
            top: 88px;
        }

        .ap-preview-head {
            padding: .875rem 1.125rem;
            border-bottom: 1px solid var(--b);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .ap-preview-head h3 {
            font-size: .92rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: .45rem;
        }

        .ap-live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #28C76F;
            animation: ap-pulse 2s ease-in-out infinite;
        }

        @keyframes ap-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .35; }
        }

        @media (prefers-reduced-motion: reduce) {
            .ap-live-dot { animation: none; }
        }

        /* ---------- نموذج التطبيق المصغّر ---------- */
        .ap-mock {
            margin: 1.125rem;
            border: 1px solid var(--b);
            border-radius: 10px;
            overflow: hidden;
            font-size: 11px;
            line-height: 1.5;
        }

        .mock-top {
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 .75rem;
            border-bottom: 1px solid rgba(128, 128, 128, .18);
        }

        .mock-search {
            width: 46%;
            height: 20px;
            border-radius: 5px;
            border: 1px solid rgba(128, 128, 128, .22);
        }

        .mock-avatar {
            width: 22px;
            height: 22px;
            border-radius: 50%;
        }

        .mock-body {
            display: flex;
            min-height: 320px;
        }

        .mock-side {
            width: 33%;
            padding: .625rem .5rem;
        }

        .mock-brand {
            font-weight: 700;
            font-size: 12px;
            padding: 0 .4rem .625rem;
            margin-bottom: .375rem;
            border-bottom: 1px solid rgba(128, 128, 128, .16);
        }

        .mock-nav {
            padding: .35rem .5rem;
            margin-bottom: .2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mock-main {
            flex: 1;
            padding: .75rem;
            min-width: 0;
        }

        .mock-title {
            font-size: 12px;
            margin: 0 0 .5rem;
        }

        .mock-stats {
            display: flex;
            gap: .375rem;
            margin-bottom: .5rem;
        }

        .mock-stat {
            flex: 1;
            border: 1px solid;
            padding: .4rem .45rem;
        }

        .mock-stat span {
            display: block;
            font-size: 9px;
            opacity: .7;
        }

        .mock-stat strong {
            font-size: 12px;
        }

        .mock-card {
            border: 1px solid;
            padding: .5rem .625rem;
            margin-bottom: .5rem;
        }

        .mock-input {
            display: block;
            width: 100%;
            height: 24px;
            border: 1px solid;
            margin-top: .3rem;
        }

        .mock-btns {
            display: flex;
            gap: .3rem;
            flex-wrap: wrap;
            margin-bottom: .5rem;
        }

        .mock-btn {
            font-size: 10px;
            padding: .3rem .7rem;
            border: 1px solid transparent;
            font-weight: 600;
        }

        .mock-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .mock-table th,
        .mock-table td {
            padding: .3rem .35rem;
            text-align: start;
            border-bottom: 1px solid;
        }

        .mock-badge {
            display: inline-block;
            font-size: 9px;
            padding: .1rem .4rem;
            font-weight: 600;
        }

        .ap-preview-foot {
            padding: 0 1.125rem 1.125rem;
            font-size: .74rem;
            color: var(--muted);
            text-align: center;
        }

        /* ==================== شريط الحفظ ==================== */
        .ap-actions {
            position: sticky;
            bottom: 0;
            background: #fff;
            border: 1px solid var(--b);
            border-radius: 12px;
            padding: .875rem 1.25rem;
            margin-top: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            box-shadow: 0 -3px 14px rgba(20, 25, 45, .07);
            z-index: 5;
        }

        .ap-dirty {
            display: none;
            align-items: center;
            gap: .4rem;
            font-size: .8rem;
            color: #8a5b00;
            background: #fff6e5;
            border-radius: 20px;
            padding: .3rem .75rem;
        }

        .ap-dirty.is-on {
            display: inline-flex;
        }

        .ap-thumb {
            max-height: 46px;
            max-width: 130px;
            border: 1px solid var(--b);
            border-radius: 7px;
            padding: .25rem;
            background: #fff;
        }
        /* زر مسح اللون الاختياري — يعيد الحقل إلى «اتبع القالب» */
        .ap-clear {
            border: 1px solid var(--b);
            background: #fff;
            color: var(--muted);
            border-radius: 6px;
            width: 30px;
            height: 30px;
            line-height: 1;
            flex: 0 0 auto;
            cursor: pointer;
            transition: all .15s ease;
        }

        .ap-clear:hover {
            border-color: #e05c5c;
            color: #e05c5c;
        }

    </style>
@endsection

@section('content')
    @php
        // القيمة المعروضة لكل حقل: مُدخل سابق مرفوض، وإلا المحفوظ، وإلا الافتراضي
        $field = fn (string $key) => old($key, $theme->valueOr($key));

        $colorFields = [
            // المفتاح اسم الحقل، والقيمة: هل يتبع اللون الأساسي حين لا يُضبط؟
            // التسميات والتلميحات من ملفّ الترجمة تحت المفتاح نفسه
            'primary_color' => false,
            'sidebar_color' => false,
            'sidebar_text_color' => false,
            'topbar_color' => false,
            'color' => false,
            'success_color' => false,
            'danger_color' => false,
            'info_color' => false,
            'warning_color' => false,
            'link_color' => true,
        ];
    @endphp

    <div class="ap">

        <div class="ap-head">
            <div>
                <h2><i class="feather icon-droplet" style="color:var(--accent)"></i> {{ __('settings.appearance.title') }}</h2>
                <p>{{ __('settings.appearance.subtitle') }}</p>
            </div>
            <form action="{{ route('Appearance.reset') }}" method="POST"
                onsubmit="return confirm('{{ __('settings.appearance.confirm_reset') }}');">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="feather icon-rotate-ccw"></i> {{ __('settings.appearance.reset') }}
                </button>
            </form>
        </div>

        @include('layouts.alerts.success')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('Appearance.update') }}" method="POST" enctype="multipart/form-data" id="apForm">
            @csrf

            <div class="ap-grid">

                {{-- ==================== الإعدادات ==================== --}}
                <div class="ap-card">

                    <div class="ap-tabs" role="tablist">
                        <button type="button" class="ap-tab is-active" data-pane="presets" role="tab">
                            <i class="feather icon-grid"></i> {{ __('settings.appearance.tab_presets') }}
                        </button>
                        <button type="button" class="ap-tab" data-pane="colors" role="tab">
                            <i class="feather icon-droplet"></i> {{ __('settings.appearance.tab_colors') }}
                        </button>
                        <button type="button" class="ap-tab" data-pane="type" role="tab">
                            <i class="feather icon-type"></i> {{ __('settings.appearance.tab_font') }}
                        </button>
                        <button type="button" class="ap-tab" data-pane="layout" role="tab">
                            <i class="feather icon-layout"></i> {{ __('settings.appearance.tab_layout') }}
                        </button>
                        <button type="button" class="ap-tab" data-pane="brand" role="tab">
                            <i class="feather icon-award"></i> {{ __('settings.appearance.tab_brand') }}
                        </button>
                        <button type="button" class="ap-tab" data-pane="components" role="tab">
                            <i class="feather icon-grid"></i> {{ __('settings.appearance.tab_components') }}
                        </button>
                        <button type="button" class="ap-tab" data-pane="advanced" role="tab">
                            <i class="feather icon-sliders"></i> {{ __('settings.appearance.tab_advanced') }}
                        </button>
                    </div>

                    {{-- ---------- لوحات جاهزة ---------- --}}
                    <div class="ap-pane is-active" data-pane="presets">
                        @foreach (collect($presets)->groupBy('group') as $groupName => $groupPresets)
                            <div class="ap-preset-group">
                                <div class="ap-preset-label">{{ \App\Models\ColorSitting::presetGroupLabel($groupName) }}</div>
                                <div class="ap-presets">
                                    @foreach ($groupPresets as $key => $preset)
                                        <button type="button" class="ap-preset" data-preset="{{ $key }}"
                                            data-values='@json(collect($preset)->except(['label', 'group']))'>
                                            <div class="ap-preset-mini" style="background: {{ $preset['color'] }}">
                                                <div class="ap-preset-mini-side" style="background: {{ $preset['sidebar_color'] }}">
                                                    <i style="background: {{ $preset['primary_color'] }}"></i>
                                                    <i style="background: {{ $preset['sidebar_text_color'] }}"></i>
                                                    <i style="background: {{ $preset['sidebar_text_color'] }}"></i>
                                                </div>
                                                <div class="ap-preset-mini-main">
                                                    <div class="ap-preset-mini-bar"
                                                        style="background: {{ $preset['topbar_color'] }};width:100%"></div>
                                                    <div class="ap-preset-mini-bar"
                                                        style="background: {{ $preset['primary_color'] }};width:55%"></div>
                                                    <div class="ap-preset-mini-bar"
                                                        style="background: {{ $preset['success_color'] }};width:35%"></div>
                                                </div>
                                            </div>
                                            <div class="ap-preset-name">{{ \App\Models\ColorSitting::presetLabel($key, $preset['label']) }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <p class="ap-hint mt-3 mb-0">
                            <i class="feather icon-info"></i>
                            {{ __('settings.appearance.presets_hint') }}
                        </p>
                    </div>

                    {{-- ---------- الألوان ---------- --}}
                    <div class="ap-pane" data-pane="colors">
                        @foreach ($colorFields as $name => $followsPrimary)
                            @include('sitting::appearance.partials.color-row', [
                                'name' => $name,
                                'label' => __('settings.appearance.colors.'.$name),
                                'hint' => __('settings.appearance.colors.'.$name.'_hint'),
                                // التابع يُترك فارغاً ليظل تابعاً، ومنتقيه يُظهر الأساسي
                                'value' => $followsPrimary ? $field($name) : ($field($name) ?: '#000000'),
                                'preview' => $field($name) ?: $field('primary_color'),
                                'optional' => $followsPrimary,
                            ])
                        @endforeach

                        {{-- فاحص التباين: نص القائمة فوق خلفيتها هو أكثر ما يُخطئ فيه --}}
                        <div class="ap-contrast" id="contrastBox">
                            <i class="feather icon-check-circle"></i>
                            <div>
                                <span id="contrastText">{{ __('settings.appearance.calculating') }}</span>
                                <div class="ap-hint mt-1 mb-0" style="color:inherit;opacity:.75">
                                    {{ __('settings.appearance.wcag_hint') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ---------- الخط ---------- --}}
                    <div class="ap-pane" data-pane="type">
                        <div class="row">
                            <div class="col-md-7 ap-field">
                                <label for="font_family">{{ __('settings.appearance.font_family') }}</label>
                                <select class="form-control" name="font_family" id="font_family">
                                    @foreach ($fonts as $key => $font)
                                        <option value="{{ $key }}"
                                            {{ $field('font_family') === $key ? 'selected' : '' }}>
                                            {{ $font['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="ap-hint">{{ __('settings.appearance.font_family_hint') }}</small>
                            </div>

                            <div class="col-md-5 ap-field">
                                <label for="heading_font_weight">{{ __('settings.appearance.heading_weight') }}</label>
                                <select class="form-control" name="heading_font_weight" id="heading_font_weight">
                                    @foreach (__('settings.appearance.weights') as $weight => $label)
                                        <option value="{{ $weight }}"
                                            {{ $field('heading_font_weight') === $weight ? 'selected' : '' }}>
                                            {{ $label }} ({{ $weight }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="ap-field">
                            <label for="font_size">{{ __('settings.appearance.font_size') }}</label>
                            <div class="ap-range-row">
                                <input type="range" name="font_size" id="font_size" min="11" max="20" step="1"
                                    value="{{ old('font_size', (int) $theme->valueOr('font_size')) }}">
                                <span class="ap-range-value" id="fontSizeValue"></span>
                            </div>
                            <small class="ap-hint">{{ __('settings.appearance.font_size_hint') }}</small>
                        </div>

                        <div class="ap-font-sample" id="fontSample">
                            <p class="sample-h" id="sampleHeading">{{ __('settings.appearance.sample_heading') }}</p>
                            <p class="sample-p">
                                {{ __('settings.appearance.sample_body') }}
                                The quick brown fox jumps over the lazy dog.
                            </p>
                            <p class="sample-n">{{ __('settings.appearance.sample_numbers') }}</p>
                        </div>
                    </div>

                    {{-- ---------- التخطيط ---------- --}}
                    <div class="ap-pane" data-pane="layout">
                        <div class="ap-section-title">{{ __('settings.appearance.display_mode') }}</div>
                        <div class="ap-choices mb-2">
                            @foreach ([
        'light' => ['#ffffff', '#f0f1f5'],
        'dark' => ['#161d31', '#283046'],
        'semi-dark' => ['#10163A', '#ffffff'],
    ] as $mode => [$c1, $c2])
                                @php
                                    $label = __('settings.appearance.modes.'.$mode);
                                    $hint = __('settings.appearance.modes.'.$mode.'_hint');
                                @endphp
                                <label class="ap-choice">
                                    <input type="radio" name="layout_mode" value="{{ $mode }}"
                                        {{ $field('layout_mode') === $mode ? 'checked' : '' }}>
                                    <span>
                                        <em class="ap-choice-chip"
                                            style="background:linear-gradient(135deg, {{ $c1 }} 50%, {{ $c2 }} 50%)"></em>
                                        {{ $label }}
                                        <small>{{ $hint }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="ap-hint">
                            <i class="feather icon-alert-triangle"></i>
                            {{ __('settings.appearance.dark_note') }}
                        </p>

                        <div class="ap-section-title">{{ __('settings.appearance.shape') }}</div>
                        <div class="ap-field">
                            <label for="border_radius">{{ __('settings.appearance.border_radius') }}</label>
                            <div class="ap-range-row">
                                <input type="range" name="border_radius" id="border_radius" min="0" max="24" step="1"
                                    value="{{ old('border_radius', (int) $theme->valueOr('border_radius')) }}">
                                <span class="ap-range-value" id="radiusValue"></span>
                            </div>
                            <small class="ap-hint">{{ __('settings.appearance.border_radius_hint') }}</small>
                        </div>

                        <div class="ap-section-title">{{ __('settings.appearance.behavior') }}</div>
                        <div class="row">
                            <div class="col-md-6 ap-field">
                                <label for="sidebar_position">{{ __('settings.appearance.sidebar_position') }}</label>
                                <select class="form-control" name="sidebar_position" id="sidebar_position">
                                    <option value="fixed"
                                        {{ $field('sidebar_position') === 'fixed' ? 'selected' : '' }}>
                                        {{ __('settings.appearance.sidebar_fixed') }}
                                    </option>
                                    <option value="static"
                                        {{ $field('sidebar_position') === 'static' ? 'selected' : '' }}>
                                        {{ __('settings.appearance.sidebar_scroll') }}
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6 ap-field">
                                <label>{{ __('settings.appearance.density') }}</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="compact_mode"
                                        name="compact_mode" value="1"
                                        {{ old('compact_mode', $theme->compact_mode) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="compact_mode">
                                        {{ __('settings.appearance.compact') }}
                                    </label>
                                </div>
                                <small class="ap-hint">{{ __('settings.appearance.compact_hint') }}</small>
                            </div>
                        </div>
                    </div>

                    {{-- ---------- الهوية ---------- --}}
                    <div class="ap-pane" data-pane="brand">
                        <div class="ap-field">
                            <label for="app_title">{{ __('settings.appearance.app_title') }}</label>
                            <input type="text" class="form-control" name="app_title" id="app_title" maxlength="100"
                                value="{{ old('app_title', $theme->app_title) }}" placeholder="{{ __('settings.appearance.app_title_placeholder') }}">
                            <small class="ap-hint">{{ __('settings.appearance.app_title_hint') }}</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 ap-field">
                                <label for="login_logo">{{ __('settings.appearance.login_logo') }}</label>
                                <input type="file" class="form-control-file" name="login_logo" id="login_logo"
                                    accept="image/*">
                                <small class="ap-hint">{{ __('settings.appearance.login_logo_hint') }}</small>
                                @if ($theme->login_logo_url)
                                    <img src="{{ $theme->login_logo_url }}" alt="{{ __('settings.appearance.current_logo') }}" class="ap-thumb mt-2">
                                @endif
                            </div>

                            <div class="col-md-6 ap-field">
                                <label for="favicon">{{ __('settings.appearance.favicon') }}</label>
                                <input type="file" class="form-control-file" name="favicon" id="favicon"
                                    accept="image/png,image/svg+xml,.ico">
                                <small class="ap-hint">{{ __('settings.appearance.favicon_hint') }}</small>
                                @if ($theme->favicon_url)
                                    <img src="{{ $theme->favicon_url }}" alt="{{ __('settings.appearance.current_favicon') }}" class="ap-thumb mt-2">
                                @endif
                            </div>
                        </div>

                        {{-- خلفية صفحة الدخول: فارغة تعني «اتبع اللون الأساسي» --}}
                        @include('sitting::appearance.partials.color-row', [
                            'name' => 'login_bg_color',
                            'label' => __('settings.appearance.login_bg'),
                            'hint' => __('settings.appearance.login_bg_hint'),
                            'value' => $field('login_bg_color'),
                            'preview' => $field('login_bg_color') ?: $field('primary_color'),
                            'optional' => true,
                        ])
                    </div>

                    {{-- ---------- الجداول والبطاقات وعناصر التحكّم ---------- --}}
                    <div class="ap-pane" data-pane="components">

                        {{-- ===== الجداول ===== --}}
                        <div class="ap-section-title">{{ __('settings.appearance.tables') }}</div>

                        @include('sitting::appearance.partials.color-row', [
                            'name' => 'table_header_bg',
                            'label' => __('settings.appearance.table_header_bg'),
                            'hint' => __('settings.appearance.table_header_bg_hint'),
                            'value' => $field('table_header_bg'),
                            'preview' => $field('table_header_bg') ?: '#F7F8FC',
                            'optional' => true,
                        ])

                        @include('sitting::appearance.partials.color-row', [
                            'name' => 'table_header_text',
                            'label' => __('settings.appearance.table_header_text'),
                            'hint' => __('settings.appearance.table_header_text_hint'),
                            'value' => $field('table_header_text'),
                            'preview' => $field('table_header_text') ?: '#4B4B4B',
                            'optional' => true,
                        ])

                        <div class="row">
                            <div class="col-md-6 ap-field">
                                <label for="table_border_style">{{ __('settings.appearance.table_border_style') }}</label>
                                <select class="form-control" name="table_border_style" id="table_border_style">
                                    @foreach (array_keys($tableBorders) as $borderKey)
                                        <option value="{{ $borderKey }}"
                                            {{ $field('table_border_style') === $borderKey ? 'selected' : '' }}>
                                            {{ __('settings.appearance.table_borders.'.$borderKey) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 ap-field">
                                <label>{{ __('settings.appearance.table_rows') }}</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="table_striped"
                                        name="table_striped" value="1"
                                        {{ old('table_striped', $theme->table_striped) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="table_striped">
                                        {{ __('settings.appearance.table_striped') }}
                                    </label>
                                </div>
                                <div class="custom-control custom-switch mt-1">
                                    <input type="checkbox" class="custom-control-input" id="table_hover"
                                        name="table_hover" value="1"
                                        {{ old('table_hover', $theme->table_hover) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="table_hover">
                                        {{ __('settings.appearance.table_hover') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- ===== البطاقات ===== --}}
                        <div class="ap-section-title">{{ __('settings.appearance.cards') }}</div>

                        <div class="row">
                            <div class="col-md-6 ap-field">
                                <label for="card_shadow">{{ __('settings.appearance.card_shadow') }}</label>
                                <select class="form-control" name="card_shadow" id="card_shadow">
                                    @foreach (array_keys($shadows) as $shadowKey)
                                        <option value="{{ $shadowKey }}"
                                            {{ $field('card_shadow') === $shadowKey ? 'selected' : '' }}>
                                            {{ __('settings.appearance.shadows.'.$shadowKey) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="ap-hint">{{ __('settings.appearance.card_shadow_hint') }}</small>
                            </div>

                            <div class="col-md-6 ap-field">
                                <label>{{ __('settings.appearance.card_frame') }}</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="card_border"
                                        name="card_border" value="1"
                                        {{ old('card_border', $theme->card_border) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="card_border">
                                        {{ __('settings.appearance.card_border') }}
                                    </label>
                                </div>
                                <small class="ap-hint">{{ __('settings.appearance.card_border_hint') }}</small>
                            </div>
                        </div>

                        @include('sitting::appearance.partials.color-row', [
                            'name' => 'card_header_bg',
                            'label' => __('settings.appearance.card_header_bg'),
                            'hint' => __('settings.appearance.card_header_bg_hint'),
                            'value' => $field('card_header_bg'),
                            'preview' => $field('card_header_bg') ?: '#FFFFFF',
                            'optional' => true,
                        ])

                        <div class="ap-field">
                            <label for="card_padding">{{ __('settings.appearance.card_padding') }}</label>
                            <div class="ap-range-row">
                                <input type="range" name="card_padding" id="card_padding" min="8" max="40" step="2"
                                    value="{{ old('card_padding', (int) ($theme->card_padding ?: 20)) }}">
                                <span class="ap-range-value" id="cardPaddingValue"></span>
                            </div>
                            <small class="ap-hint">{{ __('settings.appearance.card_padding_hint') }}</small>
                        </div>

                        {{-- ===== الأزرار ===== --}}
                        <div class="ap-section-title">{{ __('settings.appearance.buttons') }}</div>

                        <div class="row">
                            <div class="col-md-6 ap-field">
                                <label for="button_size">{{ __('settings.appearance.button_size') }}</label>
                                <select class="form-control" name="button_size" id="button_size">
                                    @foreach (array_keys($buttonSizes) as $sizeKey)
                                        <option value="{{ $sizeKey }}"
                                            {{ $field('button_size') === $sizeKey ? 'selected' : '' }}>
                                            {{ __('settings.appearance.button_sizes.'.$sizeKey) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="ap-hint">{{ __('settings.appearance.button_size_hint') }}</small>
                            </div>

                            <div class="col-md-6 ap-field">
                                <label for="button_radius">{{ __('settings.appearance.button_radius') }}</label>
                                <div class="ap-range-row">
                                    <input type="range" name="button_radius" id="button_radius" min="0" max="30"
                                        step="1"
                                        value="{{ old('button_radius', (int) ($theme->button_radius ?: $theme->valueOr('border_radius'))) }}">
                                    <span class="ap-range-value" id="buttonRadiusValue"></span>
                                </div>
                                <small class="ap-hint">{{ __('settings.appearance.button_radius_hint') }}</small>
                            </div>
                        </div>

                        {{-- ===== حقول الإدخال ===== --}}
                        <div class="ap-section-title">{{ __('settings.appearance.inputs') }}</div>

                        @include('sitting::appearance.partials.color-row', [
                            'name' => 'input_bg',
                            'label' => __('settings.appearance.input_bg'),
                            'hint' => __('settings.appearance.input_bg_hint'),
                            'value' => $field('input_bg'),
                            'preview' => $field('input_bg') ?: '#FFFFFF',
                            'optional' => true,
                        ])

                        <div class="ap-field">
                            <label for="input_height">{{ __('settings.appearance.input_height') }}</label>
                            <div class="ap-range-row">
                                <input type="range" name="input_height" id="input_height" min="30" max="60" step="2"
                                    value="{{ old('input_height', (int) ($theme->input_height ?: 38)) }}">
                                <span class="ap-range-value" id="inputHeightValue"></span>
                            </div>
                            <small class="ap-hint">{{ __('settings.appearance.input_height_hint') }}</small>
                        </div>
                    </div>

                    {{-- ---------- متقدّم ---------- --}}
                    <div class="ap-pane" data-pane="advanced">
                        <div class="ap-section-title">{{ __('settings.appearance.frame') }}</div>

                        <div class="ap-field">
                            <label for="sidebar_width">{{ __('settings.appearance.sidebar_width') }}</label>
                            <div class="ap-range-row">
                                <input type="range" name="sidebar_width" id="sidebar_width" min="200" max="400"
                                    step="10" value="{{ old('sidebar_width', (int) $theme->valueOr('sidebar_width')) }}">
                                <span class="ap-range-value" id="sidebarWidthValue"></span>
                            </div>
                            <small class="ap-hint">{{ __('settings.appearance.sidebar_width_hint') }}</small>
                        </div>

                        <div class="ap-field">
                            <label for="topbar_height">{{ __('settings.appearance.topbar_height') }}</label>
                            <div class="ap-range-row">
                                <input type="range" name="topbar_height" id="topbar_height" min="48" max="100"
                                    step="2" value="{{ old('topbar_height', (int) ($theme->topbar_height ?: 62)) }}">
                                <span class="ap-range-value" id="topbarHeightValue"></span>
                            </div>
                            <small class="ap-hint">{{ __('settings.appearance.topbar_height_hint') }}</small>
                        </div>

                        <div class="ap-field">
                            <label for="content_width">{{ __('settings.appearance.content_width') }}</label>
                            <select class="form-control" name="content_width" id="content_width">
                                @foreach ($contentWidths as $widthKey)
                                    <option value="{{ $widthKey }}"
                                        {{ $field('content_width') === $widthKey ? 'selected' : '' }}>
                                        {{ __('settings.appearance.content_widths.'.$widthKey) }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="ap-hint">{{ __('settings.appearance.content_width_hint') }}</small>
                        </div>

                        <div class="ap-section-title">{{ __('settings.appearance.custom_css') }}</div>
                        <div class="ap-field">
                            <label for="custom_css">{{ __('settings.appearance.custom_css') }}</label>
                            <textarea class="form-control" name="custom_css" id="custom_css" rows="10"
                                spellcheck="false" dir="ltr" style="font-family:monospace;font-size:.82rem"
                                placeholder=".card { border: 1px solid #eee; }">{{ old('custom_css', $theme->custom_css) }}</textarea>
                            <small class="ap-hint">{{ __('settings.appearance.custom_css_hint') }}</small>
                        </div>

                        <div class="ap-contrast is-warn">
                            <i class="feather icon-alert-triangle"></i>
                            <div>{{ __('settings.appearance.custom_css_warning') }}</div>
                        </div>
                    </div>

                </div>

                {{-- ==================== المعاينة الحية ==================== --}}
                <div class="ap-preview">
                    <div class="ap-card">
                        <div class="ap-preview-head">
                            <h3><span class="ap-live-dot"></span> {{ __('settings.appearance.live_preview') }}</h3>
                            <span class="ap-hint m-0" id="previewMode"></span>
                        </div>

                        <div class="ap-mock" id="mock">
                            <div class="mock-top" id="mockTop">
                                <span class="mock-search" id="mockSearch"></span>
                                <span class="mock-avatar" id="mockAvatar"></span>
                            </div>
                            <div class="mock-body">
                                <div class="mock-side" id="mockSide">
                                    <div class="mock-brand" id="mockBrand">{{ __('settings.appearance.mock_brand') }}</div>
                                    <div class="mock-nav js-nav-active" id="mockNavActive">{{ __('settings.appearance.mock_dashboard') }}</div>
                                    <div class="mock-nav js-nav">{{ __('settings.appearance.mock_invoices') }}</div>
                                    <div class="mock-nav js-nav">{{ __('settings.appearance.mock_clients') }}</div>
                                    <div class="mock-nav js-nav">{{ __('settings.appearance.mock_stock') }}</div>
                                    <div class="mock-nav js-nav">{{ __('settings.appearance.mock_reports') }}</div>
                                </div>

                                <div class="mock-main" id="mockMain">
                                    <p class="mock-title js-heading" id="mockTitle">{{ __('settings.appearance.mock_dashboard') }}</p>

                                    <div class="mock-stats">
                                        <div class="mock-stat js-surface js-radius">
                                            <span>{{ __('settings.appearance.mock_sales') }}</span><strong class="js-primary-text">42,500</strong>
                                        </div>
                                        <div class="mock-stat js-surface js-radius">
                                            <span>{{ __('settings.appearance.mock_invoices') }}</span><strong>128</strong>
                                        </div>
                                    </div>

                                    <div class="mock-card js-surface js-radius">
                                        <span class="js-heading">{{ __('settings.appearance.mock_new_invoice') }}</span>
                                        <span class="mock-input js-input js-radius"></span>
                                    </div>

                                    <div class="mock-btns">
                                        <button type="button" class="mock-btn js-radius" id="btnPrimary">{{ __('settings.appearance.mock_save') }}</button>
                                        <button type="button" class="mock-btn js-radius" id="btnSuccess">{{ __('settings.appearance.mock_approve') }}</button>
                                        <button type="button" class="mock-btn js-radius" id="btnDanger">{{ __('settings.appearance.mock_delete') }}</button>
                                        <button type="button" class="mock-btn js-radius" id="btnOutline">{{ __('settings.appearance.mock_cancel') }}</button>
                                    </div>

                                    <table class="mock-table js-surface">
                                        <thead>
                                            <tr>
                                                <th class="js-border js-muted">{{ __('settings.appearance.mock_item') }}</th>
                                                <th class="js-border js-muted">{{ __('settings.appearance.mock_status') }}</th>
                                                <th class="js-border js-muted">{{ __('settings.appearance.mock_total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="js-border">{{ __('settings.appearance.mock_product_1') }}</td>
                                                <td class="js-border"><span class="mock-badge js-radius" id="badgeOk">{{ __('settings.appearance.mock_paid') }}</span></td>
                                                <td class="js-border">150.00</td>
                                            </tr>
                                            <tr>
                                                <td class="js-border">{{ __('settings.appearance.mock_product_2') }}</td>
                                                <td class="js-border"><span class="mock-badge js-radius" id="badgeBad">{{ __('settings.appearance.mock_overdue') }}</span></td>
                                                <td class="js-border">75.00</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <p class="ap-preview-foot">{{ __('settings.appearance.preview_note') }}</p>
                    </div>
                </div>

            </div>

            <div class="ap-actions">
                <div class="d-flex align-items-center flex-wrap" style="gap:.75rem">
                    <span class="ap-dirty" id="dirtyBadge">
                        <i class="feather icon-alert-circle"></i> {{ __('settings.appearance.unsaved') }}
                    </span>
                    <span class="text-muted" style="font-size:.8rem">
                        <i class="feather icon-users"></i> {{ __('settings.appearance.applies_to_all') }}
                    </span>
                </div>
                <div>
                    <a href="{{ route('SittingAccount.dashboard') }}" class="btn btn-outline-secondary">{{ __('settings.common.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> {{ __('settings.appearance.save') }}
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

            var FONT_STACKS = @json(collect($fonts)->map->stack);
            var SHADOWS = @json($shadows);

            var el = function (id) { return document.getElementById(id); };
            var val = function (id) { var node = el(id); return node ? node.value : ''; };

            // ==================== التبويبات ====================
            document.querySelectorAll('.ap-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    document.querySelectorAll('.ap-tab').forEach(function (other) {
                        other.classList.toggle('is-active', other === tab);
                    });
                    document.querySelectorAll('.ap-pane').forEach(function (pane) {
                        pane.classList.toggle('is-active', pane.dataset.pane === tab.dataset.pane);
                    });
                });
            });

            // ==================== أدوات اللون ====================
            function toRgb(hex) {
                var match = /^#?([0-9a-f]{6})$/i.exec((hex || '').trim());
                if (!match) { return null; }
                var int = parseInt(match[1], 16);
                return [(int >> 16) & 255, (int >> 8) & 255, int & 255];
            }

            function luminance(hex) {
                var rgb = toRgb(hex);
                if (!rgb) { return null; }
                var channels = rgb.map(function (channel) {
                    var value = channel / 255;
                    return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
                });
                return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
            }

            function contrast(a, b) {
                var first = luminance(a), second = luminance(b);
                if (first === null || second === null) { return null; }
                return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
            }

            function readableOn(hex) {
                var lum = luminance(hex);
                return (lum !== null && lum > 0.45) ? '#1a1c24' : '#ffffff';
            }

            function mix(hex, target, amount) {
                var rgb = toRgb(hex);
                if (!rgb) { return hex; }
                return '#' + rgb.map(function (channel) {
                    return Math.round(channel + (target - channel) * amount)
                        .toString(16).padStart(2, '0');
                }).join('');
            }

            function rgba(hex, alpha) {
                var rgb = toRgb(hex);
                return rgb ? 'rgba(' + rgb.join(',') + ',' + alpha + ')' : 'transparent';
            }

            // ==================== ربط المنتقي بحقل الكود ====================
            document.querySelectorAll('.js-picker').forEach(function (picker) {
                var hex = el(picker.dataset.target);

                picker.addEventListener('input', function () {
                    hex.value = picker.value.toUpperCase();
                    hex.classList.remove('is-invalid');
                    render();
                });

                hex.addEventListener('input', function () {
                    var valid = /^#[0-9a-fA-F]{6}$/.test(hex.value);
                    hex.classList.toggle('is-invalid', !valid && hex.value !== '');
                    if (valid) { picker.value = hex.value; render(); }
                });
            });

            // ==================== مسح اللون الاختياري ====================
            // الحقل الفارغ يعني «اترك لون القالب» — الزر يفرّغه ويعيد
            // المنتقي إلى اللون الأساسي ليبقى معبّراً عمّا سيظهر فعلاً.
            document.querySelectorAll('.js-clear').forEach(function (button) {
                button.addEventListener('click', function () {
                    var hex = el(button.dataset.target);
                    var picker = document.querySelector('.js-picker[data-target="' + button.dataset.target + '"]');
                    if (!hex) { return; }

                    hex.value = '';
                    hex.classList.remove('is-invalid');
                    if (picker) { picker.value = val('primary_color') || '#7367F0'; }
                    render();
                });
            });

            // ==================== اللوحات الجاهزة ====================
            document.querySelectorAll('.ap-preset').forEach(function (button) {
                button.addEventListener('click', function () {
                    var values = JSON.parse(button.dataset.values);

                    Object.keys(values).forEach(function (name) {
                        if (name === 'layout_mode') {
                            var radio = document.querySelector('input[name="layout_mode"][value="' + values[name] + '"]');
                            if (radio) { radio.checked = true; }
                            return;
                        }

                        var hex = el(name);
                        var picker = document.querySelector('.js-picker[data-target="' + name + '"]');
                        if (!hex || !picker) { return; }
                        hex.value = values[name].toUpperCase();
                        hex.classList.remove('is-invalid');
                        picker.value = values[name];
                    });

                    document.querySelectorAll('.ap-preset').forEach(function (other) {
                        other.classList.toggle('is-active', other === button);
                    });

                    render();
                });
            });

            // ==================== فاحص التباين ====================
            function renderContrast() {
                var box = el('contrastBox');
                var text = el('contrastText');
                if (!box) { return; }

                var ratio = contrast(val('sidebar_text_color'), val('sidebar_color'));

                if (ratio === null) {
                    box.className = 'ap-contrast is-warn';
                    text.textContent = @json(__('settings.appearance.contrast_invalid'));
                    return;
                }

                var rounded = ratio.toFixed(2);
                var icon = box.querySelector('i');

                if (ratio >= 4.5) {
                    box.className = 'ap-contrast is-good';
                    icon.className = 'feather icon-check-circle';
                    text.innerHTML = @json(__('settings.appearance.contrast_good')).replace(':ratio', rounded);
                } else if (ratio >= 3) {
                    box.className = 'ap-contrast is-warn';
                    icon.className = 'feather icon-alert-triangle';
                    text.innerHTML = @json(__('settings.appearance.contrast_fair')).replace(':ratio', rounded);
                } else {
                    box.className = 'ap-contrast is-bad';
                    icon.className = 'feather icon-x-circle';
                    text.innerHTML = @json(__('settings.appearance.contrast_poor')).replace(':ratio', rounded);
                }
            }

            // ==================== المعاينة ====================
            var mock = el('mock');

            function render() {
                var mode = (document.querySelector('input[name="layout_mode"]:checked') || {}).value || 'light';
                var dark = mode === 'dark';
                var semiDark = mode === 'semi-dark';

                var primary = val('primary_color');
                var radius = el('border_radius').value + 'px';
                var size = parseInt(el('font_size').value, 10);
                var stack = FONT_STACKS[val('font_family')] || 'inherit';

                // نفس اشتقاق الألوان في App\Support\ThemeSettings::palette()
                var sidebarBg = semiDark ? '#10163A' : val('sidebar_color');
                var sidebarText = semiDark ? '#d0d2d6' : val('sidebar_text_color');
                var bodyBg = dark ? '#161d31' : val('color');
                var surface = dark ? '#283046' : '#ffffff';
                var textColor = dark ? '#d0d2d6' : '#4b4b4b';
                var muted = dark ? '#8a8f9c' : '#7b8194';
                var border = dark ? '#3b4253' : '#e6e9f0';
                var topbar = dark ? surface : val('topbar_color');

                el('fontSizeValue').textContent = size + 'px';
                el('radiusValue').textContent = radius;
                el('previewMode').textContent = @json(__('settings.appearance.preview_modes'))[mode];

                // ---------- قيَم المزالق الجديدة ----------
                var ranges = {
                    sidebar_width: 'sidebarWidthValue',
                    topbar_height: 'topbarHeightValue',
                    card_padding: 'cardPaddingValue',
                    button_radius: 'buttonRadiusValue',
                    input_height: 'inputHeightValue'
                };
                Object.keys(ranges).forEach(function (name) {
                    var input = el(name), label = el(ranges[name]);
                    if (input && label) { label.textContent = input.value + 'px'; }
                });

                // ظل البطاقات وعرض القائمة يظهران في المعاينة مباشرةً
                var shadow = SHADOWS[val('card_shadow')] || 'none';
                var sideWidth = el('sidebar_width');
                if (sideWidth) {
                    // 200–400px تُسقَط على 26–44% من عرض المعاينة
                    el('mockSide').style.width = (26 + (sideWidth.value - 200) * 0.09).toFixed(1) + '%';
                }

                // ---------- الخط ----------
                el('fontSample').style.fontFamily = stack;
                el('fontSample').style.fontSize = size + 'px';
                el('sampleHeading').style.fontWeight = val('heading_font_weight');

                // ---------- الإطار العام ----------
                mock.style.fontFamily = stack;
                mock.style.borderRadius = radius;
                mock.style.borderColor = border;
                mock.style.color = textColor;

                el('mockTop').style.background = topbar;
                el('mockSearch').style.background = dark ? bodyBg : 'rgba(0,0,0,.03)';
                el('mockAvatar').style.background = rgba(primary, 0.75);
                el('mockSide').style.background = sidebarBg;
                el('mockMain').style.background = bodyBg;
                el('mockBrand').style.color = sidebarText;
                el('mockTitle').style.color = textColor;

                // ---------- القائمة ----------
                document.querySelectorAll('.js-nav').forEach(function (item) {
                    item.style.color = sidebarText;
                    item.style.borderRadius = radius;
                    item.style.background = 'transparent';
                });

                var active = el('mockNavActive');
                active.style.background = primary;
                active.style.color = readableOn(primary);
                active.style.borderRadius = radius;
                active.style.boxShadow = '0 0 10px 1px ' + rgba(primary, 0.28);

                // ---------- السطوح ----------
                document.querySelectorAll('.js-surface').forEach(function (node) {
                    node.style.background = surface;
                    node.style.borderColor = border;
                    node.style.color = textColor;
                    node.style.boxShadow = shadow;
                    node.style.borderWidth = el('card_border') && el('card_border').checked ? '1px' : '';
                    node.style.borderStyle = el('card_border') && el('card_border').checked ? 'solid' : '';
                });

                // ---------- رأس الجدول ----------
                document.querySelectorAll('.mock-table th').forEach(function (cell) {
                    cell.style.background = val('table_header_bg') || '';
                    cell.style.color = val('table_header_text') || '';
                });
                document.querySelectorAll('.js-border').forEach(function (node) {
                    node.style.borderColor = border;
                });
                document.querySelectorAll('.js-muted').forEach(function (node) {
                    node.style.color = muted;
                });
                document.querySelectorAll('.js-heading').forEach(function (node) {
                    node.style.fontWeight = val('heading_font_weight');
                    node.style.color = textColor;
                });
                document.querySelectorAll('.js-radius').forEach(function (node) {
                    node.style.borderRadius = radius;
                });
                document.querySelectorAll('.js-primary-text').forEach(function (node) {
                    node.style.color = primary;
                });
                document.querySelectorAll('.js-input').forEach(function (node) {
                    node.style.background = dark ? bodyBg : '#fff';
                    node.style.borderColor = border;
                });

                // ---------- الأزرار والشارات ----------
                var onPrimary = readableOn(primary);
                var success = val('success_color');
                var danger = val('danger_color');

                el('btnPrimary').style.cssText += ';background:' + primary + ';color:' + onPrimary;
                el('btnSuccess').style.cssText += ';background:' + success + ';color:' + readableOn(success);
                el('btnDanger').style.cssText += ';background:' + danger + ';color:' + readableOn(danger);
                el('btnOutline').style.cssText += ';background:transparent;color:' + primary + ';border-color:' + primary;

                el('badgeOk').style.cssText += ';background:' + rgba(success, 0.16) + ';color:' + success;
                el('badgeBad').style.cssText += ';background:' + rgba(danger, 0.16) + ';color:' + danger;

                var buttonRadius = el('button_radius') ? el('button_radius').value + 'px' : radius;
                ['btnPrimary', 'btnSuccess', 'btnDanger', 'btnOutline'].forEach(function (id) {
                    if (el(id)) { el(id).style.borderRadius = buttonRadius; }
                });

                renderContrast();
                markDirty();
            }

            // ==================== تنبيه التغييرات غير المحفوظة ====================
            var form = el('apForm');
            var baseline = new FormData(form).toString ? null : null;
            var initial = serialize();
            var saving = false;

            function serialize() {
                return Array.prototype.map.call(
                    form.querySelectorAll('input:not([type=file]), select, textarea'),
                    function (node) {
                        return node.type === 'checkbox' || node.type === 'radio'
                            ? node.name + '=' + node.checked
                            : node.name + '=' + node.value;
                    }
                ).join('&');
            }

            function markDirty() {
                el('dirtyBadge').classList.toggle('is-on', serialize() !== initial);
            }

            form.addEventListener('submit', function () { saving = true; });

            window.addEventListener('beforeunload', function (event) {
                if (!saving && serialize() !== initial) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            // ==================== الربط ====================
            form.addEventListener('input', render);
            form.addEventListener('change', render);

            render();
            initial = serialize();
            markDirty();
        })();
    </script>
@endsection
