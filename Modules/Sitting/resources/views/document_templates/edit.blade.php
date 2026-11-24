@extends('sitting::master')

@php
    $isThermal = str_starts_with($template->paper_size, 'Thermal');
    $isLandscape = $template->orientation === 'landscape';

    // مقاس الورقة بالمليمتر — يحدّد عرض ورقة التحرير وورقة المعاينة معاً
    $paperWidth = match ($template->paper_size) {
        'Thermal_80mm' => 80,
        'Thermal_58mm' => 58,
        'A4' => $isLandscape ? 297 : 210,
        default => (float) ($template->width ?: 210),
    };
    $paperHeight = $isThermal ? 170 : ($isLandscape ? 210 : 297);
    $paperPadding = $isThermal ? 4 : 14;

    $hasItemsLoop = str_contains((string) $template->content, '{{#items}}');

    // مسارات المحرّر المحتملة: المشروع يُخدَم من منفذين بمسارين مختلفين،
    // فالرابط الذي يبنيه الخادم قد لا يطابق ما يتصفّح منه المستخدم
    $tinymceBases = array_values(array_unique([
        asset('vendor/tinymce'),
        url('vendor/tinymce'),
        '/vendor/tinymce',
    ]));
@endphp

@section('title')
    {{ __('settings.doc_templates.edit_title', ['name' => $template->name]) }}
@stop

@section('css')
    <style>
        .dt {
            --b: #e2e6ef;
            --b2: #eef1f6;
            --muted: #7b8299;
            --soft: #f7f9fc;
            --desk: #e8ebf2;
            --accent: var(--app-primary, #7367F0);
            --ink: #2f3349;
        }

        /* ==================== الترويسة ==================== */
        .dt-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: .7rem;
        }

        .dt-head h2 {
            font-size: 1.18rem;
            font-weight: 700;
            margin: 0 0 .25rem;
            color: var(--ink);
        }

        .dt-tags {
            display: flex;
            gap: .3rem;
            flex-wrap: wrap;
            font-size: .73rem;
            color: var(--muted);
        }

        .dt-tags span {
            background: var(--soft);
            border: 1px solid var(--b);
            border-radius: 20px;
            padding: .1rem .6rem;
        }

        /* ==================== شريط الإرشاد ==================== */
        .dt-guide {
            background: linear-gradient(90deg, #f4f2ff, #fcfbff);
            border: 1px solid #ded9ff;
            border-radius: 9px;
            padding: .6rem .85rem;
            margin-bottom: .7rem;
            display: flex;
            align-items: center;
            gap: 1.1rem;
            flex-wrap: wrap;
            font-size: .79rem;
            color: #4b4a63;
        }

        .dt-guide b {
            color: var(--ink);
        }

        .dt-legend {
            display: flex;
            gap: .85rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .dt-legend i {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            vertical-align: -2px;
            margin-inline-end: .28rem;
        }

        .lg-field { background: #ede9fe; border: 1px solid #c4b5fd; }
        .lg-item { background: #dcfce7; border: 1px solid #86efac; }
        .lg-repeat { background: #fff; border: 2px dashed #16a34a; }
        .lg-if { background: #fff7ed; border: 1px dashed #f59e0b; }

        .dt-guide-close {
            border: 0;
            background: none;
            color: var(--muted);
            margin-inline-start: auto;
            cursor: pointer;
            font-size: .77rem;
        }

        /* ==================== الإطار العام ==================== */
        .dt-shell {
            display: grid;
            grid-template-columns: 272px minmax(0, 1fr);
            gap: .7rem;
            align-items: stretch;
        }

        .dt-shell.dock-closed {
            grid-template-columns: minmax(0, 1fr);
        }

        .dt-shell.dock-closed .dt-dock {
            display: none;
        }

        @media (max-width: 1100px) {
            .dt-shell,
            .dt-shell.dock-closed {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .dt-card {
            background: #fff;
            border: 1px solid var(--b);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(20, 25, 45, .05);
            overflow: hidden;
        }

        /* ==================== الرصيف الجانبي ==================== */
        .dt-dock {
            display: flex;
            flex-direction: column;
            height: var(--stage-h);
        }

        .dt-dock-tabs {
            display: flex;
            border-bottom: 1px solid var(--b);
            background: var(--soft);
            flex-shrink: 0;
        }

        .dt-dock-tabs button {
            flex: 1;
            border: 0;
            background: none;
            padding: .6rem .2rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .dt-dock-tabs button.is-active {
            color: var(--accent);
            border-bottom-color: var(--accent);
            background: #fff;
        }

        .dt-dock-pane {
            display: none;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }

        .dt-dock-pane.is-active {
            display: flex;
        }

        .dt-hint {
            padding: .5rem .8rem;
            font-size: .72rem;
            color: var(--muted);
            border-bottom: 1px solid var(--b2);
            background: #fcfdff;
            line-height: 1.6;
        }

        .dt-search {
            padding: .5rem .65rem;
            border-bottom: 1px solid var(--b2);
            flex-shrink: 0;
        }

        .dt-search input {
            width: 100%;
            border: 1px solid var(--b);
            border-radius: 7px;
            padding: .38rem .6rem;
            font-size: .78rem;
            outline: none;
        }

        .dt-search input:focus {
            border-color: var(--accent);
        }

        .dt-scroll {
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }

        .dt-group-title {
            font-size: .67rem;
            font-weight: 700;
            color: var(--muted);
            padding: .7rem .85rem .2rem;
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .dt-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .4rem;
            width: 100%;
            border: 0;
            background: none;
            padding: .34rem .85rem;
            font-size: .78rem;
            color: #4a4f61;
            cursor: grab;
            text-align: start;
            transition: background .12s, color .12s;
        }

        .dt-chip:hover {
            background: #f1eeff;
            color: var(--accent);
        }

        .dt-chip:active { cursor: grabbing; }

        .dt-chip code {
            font-size: .64rem;
            color: #a5abbe;
            background: none;
            padding: 0;
            white-space: nowrap;
            max-width: 46%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dt-block {
            display: block;
            width: calc(100% - 1.3rem);
            margin: .3rem .65rem;
            border: 1px solid var(--b);
            border-radius: 8px;
            background: #fff;
            padding: .5rem .65rem;
            text-align: start;
            cursor: pointer;
            transition: border-color .12s, box-shadow .12s;
        }

        .dt-block:hover {
            border-color: var(--accent);
            box-shadow: 0 2px 8px rgba(115, 103, 240, .14);
        }

        .dt-block strong {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--ink);
        }

        .dt-block small {
            display: block;
            font-size: .7rem;
            color: var(--muted);
            line-height: 1.5;
        }

        /* ---------- الإعدادات داخل الرصيف ---------- */
        .dt-settings {
            padding: .8rem .85rem 1.2rem;
        }

        .dt-settings label {
            font-size: .76rem;
            font-weight: 600;
            color: #444a60;
            margin-bottom: .2rem;
        }

        .dt-settings .form-control {
            font-size: .8rem;
            height: auto;
            padding: .32rem .55rem;
        }

        .dt-settings input[type="color"] {
            padding: 2px;
            height: 32px;
        }

        .dt-sec {
            font-size: .68rem;
            font-weight: 700;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: 1.1rem 0 .6rem;
        }

        .dt-sec:first-child { margin-top: 0; }

        /* الخط الفاصل يسبق زر الإعادة، وإلا التصق العنوان بالزر وبقي فراغ بعده */
        .dt-sec::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--b);
            order: 2;
        }

        .dt-sec button { order: 3; }

        /* ---------- شبكة خيارات التصميم ---------- */
        .dt-grid2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 .5rem;
            align-items: end;
        }

        .dt-grid2 .form-group {
            margin-bottom: .55rem;
            min-width: 0;
        }

        .dt-grid2 label {
            font-size: .72rem;
            line-height: 1.35;
            display: block;
            margin-bottom: .2rem;
        }

        .dt-full { grid-column: 1 / -1; }

        .dt-settings input[type="color"] {
            padding: 2px;
            height: 30px;
            cursor: pointer;
        }

        /* ---------- مفتاح تبديل ----------
           مفاتيح Bootstrap تبني الدائرة بعنصر مطلق الموضع وحشو من جهة واحدة،
           فينزل النص تحت المفتاح في الاتجاه من اليمين لليسار. هذا المفتاح
           صفّ مرن: الدائرة والنص جنباً إلى جنب مهما كان الاتجاه. */
        .dt-switch {
            display: flex;
            align-items: center;
            gap: .55rem;
            cursor: pointer;
            margin: 0 0 .6rem;
            font-size: .77rem;
            font-weight: 500;
            color: #444a60;
            line-height: 1.35;
        }

        .dt-switch input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .dt-switch-track {
            display: flex;
            align-items: center;
            flex: 0 0 auto;
            width: 34px;
            height: 19px;
            border-radius: 19px;
            background: #d3d8e4;
            padding: 2px;
            transition: background .15s;
        }

        .dt-switch-dot {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 2px rgba(20, 25, 45, .3);
            margin-inline-start: 0;
            transition: margin-inline-start .15s;
        }

        .dt-switch input:checked + .dt-switch-track {
            background: var(--accent);
        }

        .dt-switch input:checked + .dt-switch-track .dt-switch-dot {
            margin-inline-start: 15px;
        }

        .dt-switch input:focus-visible + .dt-switch-track {
            box-shadow: 0 0 0 3px rgba(115, 103, 240, .28);
        }

        .dt-switch-text {
            min-width: 0;
        }

        /* ---------- هدف الإفلات ---------- */
        .dt-pane-edit.is-dropping .dt-editor-host {
            outline: 2px dashed var(--accent);
            outline-offset: -3px;
        }

        .dt-chip.is-dragging {
            opacity: .45;
        }

        .dt-group-hint {
            font-size: .68rem;
            color: #9aa0b5;
            margin: -.35rem 0 .55rem;
            line-height: 1.5;
        }

        /* ==================== المسرح ==================== */
        .dt-main {
            display: flex;
            flex-direction: column;
            height: var(--stage-h);
        }

        .dt-bar {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .4rem .6rem;
            border-bottom: 1px solid var(--b);
            background: var(--soft);
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        /* مجموعة أزرار متلاصقة — نمط مفاتيح العرض في الوورد */
        .dt-seg {
            display: inline-flex;
            border: 1px solid var(--b);
            border-radius: 7px;
            overflow: hidden;
            background: #fff;
        }

        .dt-seg button {
            border: 0;
            background: none;
            padding: .34rem .72rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: .3rem;
            white-space: nowrap;
        }

        .dt-seg button + button {
            border-inline-start: 1px solid var(--b);
        }

        .dt-seg button.is-active {
            background: var(--accent);
            color: #fff;
        }

        .dt-bar-gap { flex: 1; }

        .dt-icon {
            border: 1px solid var(--b);
            background: #fff;
            border-radius: 6px;
            width: 28px;
            height: 28px;
            font-size: .75rem;
            color: var(--muted);
            cursor: pointer;
            line-height: 1;
        }

        .dt-icon:hover {
            color: var(--accent);
            border-color: var(--accent);
        }

        .dt-icon.is-off {
            background: var(--soft);
            color: #bcc1d2;
        }

        .dt-zoom {
            font-size: .76rem;
            color: #4a4f61;
            border: 1px solid var(--b);
            border-radius: 6px;
            padding: .28rem .35rem;
            background: #fff;
        }

        /* ---------- اللوحان ---------- */
        .dt-stage {
            display: flex;
            flex: 1;
            min-height: 0;
        }

        /* `min-height:0` على كل حلقة في السلسلة: بدونها يتمدّد العنصر بمحتواه
           بدل أن ينكمش داخل المسرح، فيقصر محرّر الكود ولا يبلغ طول المعاينة */
        .dt-pane {
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .dt-pane-edit { flex: 1; }

        .dt-pane-view {
            flex: 1;
            border-inline-start: 1px solid var(--b);
        }

        .dt-stage.view-edit .dt-pane-view { display: none; }
        .dt-stage.view-preview .dt-pane-edit { display: none; }

        .dt-pane-title {
            font-size: .7rem;
            font-weight: 700;
            color: var(--muted);
            padding: .3rem .7rem;
            background: #fbfcfe;
            border-bottom: 1px solid var(--b2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .dt-stage.view-edit .dt-pane-title,
        .dt-stage.view-preview .dt-pane-title { display: none; }

        /* ---------- المكتب والورقة ---------- */
        .dt-desk {
            flex: 1;
            min-height: 0;
            overflow: auto;
            background: var(--desk);
            padding: 16px 12px 28px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .dt-sheet {
            background: #fff;
            box-shadow: 0 4px 20px rgba(15, 20, 40, .18);
            flex-shrink: 0;
            position: relative;
        }

        .dt-sheet iframe {
            border: 0;
            display: block;
            transform-origin: top right;
            background: #fff;
        }

        .dt-printer {
            background: #263043;
            border-radius: 12px 12px 6px 6px;
            padding: 10px 10px 0;
            box-shadow: 0 4px 18px rgba(15, 20, 40, .22);
            flex-shrink: 0;
        }

        .dt-printer .slot {
            background: #17202f;
            height: 8px;
            border-radius: 4px;
            margin-bottom: 9px;
        }

        .dt-printer .tear {
            height: 8px;
            background-image: linear-gradient(135deg, transparent 50%, #263043 50%),
                linear-gradient(225deg, transparent 50%, #263043 50%);
            background-size: 8px 8px;
            background-repeat: repeat-x;
        }

        /* ---------- المحرّر ---------- */
        .dt-editor-host {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .dt-editor-host .tox-tinymce {
            border: 0 !important;
            border-radius: 0 !important;
            flex: 1;
        }

        .tox .tox-toolbar__primary,
        .tox .tox-toolbar-overlord {
            background: #fbfcfe !important;
        }

        .dt-code {
            font-family: ui-monospace, 'Courier New', monospace;
            font-size: .8rem;
            line-height: 1.65;
            direction: ltr;
            text-align: left;
            border: 0;
            resize: none;
            width: 100%;
            flex: 1 1 auto;
            min-height: 0;
            padding: 1rem;
            background: #1e2235;
            color: #d6d9e3;
        }

        .dt-code:focus {
            outline: none;
            background: #1a1e30;
        }

        .dt-note {
            padding: .5rem .9rem;
            background: #fff6e5;
            color: #8a5b00;
            font-size: .76rem;
            border-bottom: 1px solid var(--b2);
            flex-shrink: 0;
            line-height: 1.6;
        }

        .dt-sub {
            display: none;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }

        .dt-sub.is-active { display: flex; }

        /* ==================== شريط الحفظ ==================== */
        .dt-actions {
            background: #fff;
            border: 1px solid var(--b);
            border-radius: 10px;
            padding: .6rem 1rem;
            margin-top: .7rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .dt-status {
            font-size: .79rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .dt-status.is-dirty { color: #b45309; }
        .dt-status.is-error { color: #b91c1c; }
        .dt-status.is-ok { color: #15803d; }
    </style>
@endsection

@section('content')
    <div class="dt" style="--stage-h: calc(100vh - 268px)">
        {{-- رسائلُ الحفظ وأخطاءُ التحقّق: كانت الشاشةُ لا تعرض شيئاً منها، فحفظٌ
             فاشلٌ يعيد المحرّرَ كما كان بلا كلمة --}}
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


        <div class="dt-head">
            <div>
                <h2>{{ $template->name }}</h2>
                <div class="dt-tags">
                    <span>{{ $documentTypes[$template->document_type] ?? $template->document_type }}</span>
                    <span>{{ $paperSizes[$template->paper_size] ?? $template->paper_size }}</span>
                    <span>{{ $isLandscape ? __('settings.doc_templates.landscape_short') : __('settings.doc_templates.portrait_short') }}</span>
                    @if ($template->is_default)
                        <span style="background:#eaf8f0;color:#1c6b40;border-color:#c7e9d5">{{ __('settings.doc_templates.default_template') }}</span>
                    @endif
                </div>
            </div>
            <div>
                <a href="{{ route('document-templates.preview', $template->id) }}" target="_blank"
                    class="btn btn-outline-secondary btn-sm">
                    <i class="feather icon-external-link"></i> {{ __('settings.doc_templates.open_standalone') }}
                </a>
                <a href="{{ route('document-templates.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather icon-arrow-right"></i> {{ __('settings.doc_templates.templates') }}
                </a>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveTop">
                    <i class="feather icon-save"></i> {{ __('settings.doc_templates.save') }}
                </button>
            </div>
        </div>

        <div class="dt-guide" id="dtGuide">
            <span>{!! __('settings.doc_templates.guide') !!}</span>
            <span class="dt-legend">
                <span><i class="lg-field"></i> {{ __('settings.doc_templates.legend_field') }}</span>
                <span><i class="lg-item"></i> {{ __('settings.doc_templates.legend_item') }}</span>
                <span><i class="lg-repeat"></i> {{ __('settings.doc_templates.legend_repeat') }}</span>
                <span><i class="lg-if"></i> {{ __('settings.doc_templates.legend_if') }}</span>
            </span>
            <button type="button" class="dt-guide-close" id="dtGuideClose">{{ __('settings.doc_templates.hide') }}</button>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('document-templates.update', $template->id) }}" method="POST" id="dtForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="editor_mode" id="editor_mode" value="visual">
            {{-- تُخبر الخادم أن لوحة التصميم شاركت في هذا الطلب — بغيابها
                 تُحفظ خيارات التصميم السابقة كما هي بدل محوها --}}
            <input type="hidden" name="style_panel" value="1">

            <div class="dt-shell" id="dtShell">

                {{-- ==================== الرصيف ==================== --}}
                <aside class="dt-card dt-dock" id="dtDock">
                    <div class="dt-dock-tabs">
                        <button type="button" class="is-active" data-dock="fields">{{ __('settings.doc_templates.dock_fields') }}</button>
                        <button type="button" data-dock="blocks">{{ __('settings.doc_templates.dock_blocks') }}</button>
                        <button type="button" data-dock="design">{{ __('settings.doc_templates.dock_design') }}</button>
                        <button type="button" data-dock="settings">{{ __('settings.doc_templates.dock_settings') }}</button>
                    </div>

                    {{-- ---------- الحقول ---------- --}}
                    <div class="dt-dock-pane is-active" data-dock="fields">
                        <div class="dt-hint">{{ __('settings.doc_templates.fields_hint') }}</div>

                        <div class="dt-search">
                            <input type="search" id="fieldSearch" placeholder="{{ __('settings.doc_templates.search_field') }}" autocomplete="off">
                        </div>

                        <div class="dt-scroll" id="fieldList">
                            @foreach ($fieldGroups as $group)
                                <div class="dt-field-group">
                                    <div class="dt-group-title">
                                        <i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['label'] }}
                                    </div>
                                    @foreach ($group['fields'] as $fieldKey => [$label, $sample])
                                        <button type="button" class="dt-chip" draggable="true"
                                            data-field="{{ $fieldKey }}" data-label="{{ $label }}"
                                            title="{{ __('settings.doc_templates.field_example', ['value' => $sample]) }}">
                                            <span>{{ $label }}</span>
                                            <code>{{ $sample }}</code>
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach

                            <div class="dt-field-group">
                                <div class="dt-group-title">
                                    <i class="feather icon-list"></i> {{ __('settings.doc_templates.item_line') }}
                                </div>
                                <div style="font-size:.69rem;color:#9aa0b5;line-height:1.6;padding:0 .85rem .2rem">
                                    {{ __('settings.doc_templates.item_line_hint') }}
                                </div>
                                @foreach ($itemFields as $fieldKey => [$label, $sample])
                                    <button type="button" class="dt-chip" draggable="true"
                                        data-field="{{ $fieldKey }}" data-label="{{ $label }}" data-item="1"
                                        title="{{ __('settings.doc_templates.field_example', ['value' => $sample]) }}">
                                        <span>{{ $label }}</span>
                                        <code>{{ $sample }}</code>
                                    </button>
                                @endforeach
                            </div>

                            <p class="text-center text-muted d-none py-3 mb-0" id="fieldEmpty" style="font-size:.78rem">
                                {{ __('settings.doc_templates.no_field') }}
                            </p>
                        </div>
                    </div>

                    {{-- ---------- الكتل ---------- --}}
                    <div class="dt-dock-pane" data-dock="blocks">
                        <div class="dt-hint">{{ __('settings.doc_templates.blocks_hint') }}</div>
                        <div class="dt-scroll">
                            @foreach ($blocks as $key => $block)
                                <button type="button" class="dt-block" data-block="{{ $key }}">
                                    <strong>
                                        <i class="feather icon-{{ $block['icon'] }}" style="color:var(--accent)"></i>
                                        {{ $block['label'] }}
                                    </strong>
                                    <small>{{ $block['hint'] }}</small>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- ---------- التصميم ---------- --}}
                    <div class="dt-dock-pane" data-dock="design">
                        <div class="dt-hint">
                            {{ __('settings.doc_templates.design_hint') }}
                        </div>
                        <div class="dt-scroll">
                            <div class="dt-settings">
                                @foreach ($styleGroups as $groupKey => $group)
                                    <div class="dt-sec">
                                        <i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['label'] }}
                                        <button type="button" class="js-style-reset"
                                            data-group="{{ $groupKey }}"
                                            title="{{ __('settings.doc_templates.reset_group_title') }}"
                                            style="border:0;background:none;color:#a5abbe;cursor:pointer;font-size:.68rem;font-weight:400">
                                            {{ __('settings.doc_templates.reset_group') }}
                                        </button>
                                    </div>

                                    <p class="dt-group-hint">{{ $group['hint'] }}</p>

                                    <div class="dt-grid2">
                                        @foreach ($group['options'] as $key => $option)
                                            @php
                                                $isSet = array_key_exists($key, $styleValues);
                                                $value = $isSet ? $styleValues[$key] : $option['default'];
                                                // القوائم والمفاتيح تأخذ السطر كاملاً، والباقي عمودان
                                                $wide = in_array($option['type'], ['select', 'switch'], true);
                                            @endphp

                                            @if ($option['type'] === 'switch')
                                                <label class="dt-switch dt-full">
                                                    <input type="checkbox" class="js-style"
                                                        id="style_{{ $key }}" data-style="{{ $key }}"
                                                        data-group="{{ $groupKey }}"
                                                        data-default="{{ $option['default'] ? '1' : '0' }}"
                                                        @if ($isSet) data-touched="1" @endif
                                                        {{ $value ? 'checked' : '' }}>
                                                    <span class="dt-switch-track"><span class="dt-switch-dot"></span></span>
                                                    <span class="dt-switch-text">{{ $option['label'] }}</span>
                                                </label>
                                            @else
                                                <div class="form-group {{ $wide ? 'dt-full' : '' }}">
                                                    <label for="style_{{ $key }}">
                                                        {{ $option['label'] }}
                                                        @if (!empty($option['unit']))
                                                            <span style="color:#a5abbe;font-weight:400">({{ $option['unit'] }})</span>
                                                        @endif
                                                    </label>

                                                    @if ($option['type'] === 'color')
                                                        <input type="color" class="form-control js-style"
                                                            id="style_{{ $key }}" data-style="{{ $key }}"
                                                            data-group="{{ $groupKey }}"
                                                            data-default="{{ $option['default'] }}"
                                                            @if ($isSet) data-touched="1" @endif
                                                            value="{{ $value }}">
                                                    @elseif ($option['type'] === 'select')
                                                        <select class="form-control js-style" id="style_{{ $key }}"
                                                            data-style="{{ $key }}" data-group="{{ $groupKey }}"
                                                            data-default="{{ $option['default'] }}"
                                                            @if ($isSet) data-touched="1" @endif>
                                                            @foreach ($option['choices'] as $choice => $choiceLabel)
                                                                <option value="{{ $choice }}"
                                                                    {{ (string) $value === (string) $choice ? 'selected' : '' }}>
                                                                    {{ $choiceLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input type="number" class="form-control js-style"
                                                            id="style_{{ $key }}" data-style="{{ $key }}"
                                                            data-group="{{ $groupKey }}"
                                                            data-default="{{ $option['default'] }}"
                                                            @if ($isSet) data-touched="1" @endif
                                                            min="{{ $option['min'] }}" max="{{ $option['max'] }}"
                                                            step="{{ $option['step'] }}" value="{{ $value }}">
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach

                                <p style="font-size:.68rem;color:#9aa0b5;line-height:1.6;margin-top:1rem">
                                    {{ __('settings.doc_templates.untouched_note') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- ---------- الإعدادات ---------- --}}
                    <div class="dt-dock-pane" data-dock="settings">
                        <div class="dt-hint">{{ __('settings.doc_templates.settings_hint') }}</div>
                        <div class="dt-scroll">
                            <div class="dt-settings">

                                <div class="dt-sec">{{ __('settings.doc_templates.template_data') }}</div>
                                <div class="form-group mb-2">
                                    <label for="name">{{ __('settings.doc_templates.name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" id="name" required
                                        value="{{ old('name', $template->name) }}">
                                </div>
                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label for="orientation">{{ __('settings.doc_templates.direction') }}</label>
                                        <select class="form-control" name="orientation" id="orientation">
                                            <option value="portrait" {{ !$isLandscape ? 'selected' : '' }}>{{ __('settings.doc_templates.portrait_short') }}</option>
                                            <option value="landscape" {{ $isLandscape ? 'selected' : '' }}>{{ __('settings.doc_templates.landscape_short') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label for="width">{{ __('settings.doc_templates.width_label') }}</label>
                                        <input type="number" class="form-control" name="width" id="width" step="0.1"
                                            value="{{ old('width', $template->width) }}">
                                    </div>
                                </div>
                                {{-- الارتفاع: كان الحقل غائباً عن الشاشة والمتحكّم يقبله
                                     أصلاً (`$request->input('height')`). ويلزم الشريطَ
                                     الحراري تحديداً: `@page` لا تقبل ارتفاعاً `auto`، فمن
                                     أراد لفّةً أقصر من الافتراضي لا سبيل له إلّا هنا. --}}
                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label for="height">{{ __('settings.doc_templates.height_label') }}</label>
                                        <input type="number" class="form-control" name="height" id="height" step="0.1"
                                            value="{{ old('height', $template->height) }}"
                                            placeholder="{{ $isThermal ? '297' : '' }}">
                                    </div>
                                </div>
                                @if ($isThermal)
                                    <small class="text-muted" style="font-size:.69rem;line-height:1.5;display:block">
                                        {{ __('settings.doc_templates.thermal_height_hint') }}
                                    </small>
                                @endif
                                <small class="text-muted" style="font-size:.69rem;line-height:1.5;display:block">
                                    {{ __('settings.doc_templates.reopen_note') }}
                                </small>

                                <div class="dt-sec">{{ __('settings.doc_templates.visible_elements') }}</div>
                                @foreach (__('settings.doc_templates.toggles') as $key => $label)
                                    <label class="dt-switch">
                                        <input type="checkbox" class="js-setting" id="{{ $key }}"
                                            name="{{ $key }}" value="1"
                                            {{ ($template->settings[$key] ?? true) ? 'checked' : '' }}>
                                        <span class="dt-switch-track"><span class="dt-switch-dot"></span></span>
                                        <span class="dt-switch-text">{{ $label }}</span>
                                    </label>
                                @endforeach
                                <small class="text-muted" style="font-size:.69rem;line-height:1.5;display:block">
                                    {{ __('settings.doc_templates.hide_note') }}
                                </small>

                                <div class="dt-sec">{{ __('settings.doc_templates.font_and_colors') }}</div>
                                <div class="form-group mb-2">
                                    <label for="font_family">{{ __('settings.doc_templates.font') }}</label>
                                    <select class="form-control js-setting" name="font_family" id="font_family">
                                        @foreach (['Tajawal', 'Cairo', 'Almarai', 'Amiri'] as $font)
                                            <option value="{{ $font }}"
                                                {{ ($template->settings['font_family'] ?? 'Tajawal') === $font ? 'selected' : '' }}>
                                                {{ $font }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group mb-2">
                                    <label for="font_size">{{ __('settings.doc_templates.font_size') }}</label>
                                    <input type="number" class="form-control js-setting" name="font_size" id="font_size"
                                        min="8" max="24" value="{{ $template->settings['font_size'] ?? 13 }}">
                                </div>
                                <div class="row">
                                    <div class="col-6 form-group mb-2">
                                        <label for="primary_color">{{ __('settings.doc_templates.primary_color') }}</label>
                                        <input type="color" class="form-control js-setting" name="primary_color"
                                            id="primary_color"
                                            value="{{ $template->settings['primary_color'] ?? '#7367F0' }}">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label for="secondary_color">{{ __('settings.doc_templates.secondary_color') }}</label>
                                        <input type="color" class="form-control js-setting" name="secondary_color"
                                            id="secondary_color"
                                            value="{{ $template->settings['secondary_color'] ?? '#82868b' }}">
                                    </div>
                                </div>

                                <input type="hidden" name="direction"
                                    value="{{ $template->settings['direction'] ?? 'rtl' }}">
                            </div>
                        </div>
                    </div>
                </aside>

                {{-- ==================== المسرح ==================== --}}
                <section class="dt-card dt-main">

                    <div class="dt-bar">
                        <button type="button" class="dt-icon" id="dockToggle" title="{{ __('settings.doc_templates.toggle_dock') }}">
                            <i class="feather icon-sidebar"></i>
                        </button>

                        <div class="dt-seg" id="segSub">
                            <button type="button" class="is-active" data-sub="visual">
                                <i class="feather icon-edit-3"></i> {{ __('settings.doc_templates.edit') }}
                            </button>
                            {{-- محرّرا الكود مخفيان افتراضياً: التصميم كله يُضبط من اللوحة --}}
                            <button type="button" data-sub="source" class="js-advanced" style="display:none">HTML</button>
                            <button type="button" data-sub="styles" class="js-advanced" style="display:none">CSS</button>
                        </div>

                        <span class="dt-bar-gap"></span>

                        <button type="button" class="dt-icon is-off" id="advancedToggle"
                            title="{{ __('settings.doc_templates.show_code_title') }}">
                            <i class="feather icon-code"></i>
                        </button>

                        <select class="dt-zoom" id="zoomSelect" title="{{ __('settings.doc_templates.zoom_title') }}">
                            <option value="0.75">75%</option>
                            <option value="0.9">90%</option>
                            <option value="1" selected>100%</option>
                            <option value="1.25">125%</option>
                            <option value="1.5">150%</option>
                        </select>

                        <div class="dt-seg" id="segView">
                            <button type="button" data-view="edit" title="{{ __('settings.doc_templates.view_edit_title') }}">
                                <i class="feather icon-file-text"></i> {{ __('settings.doc_templates.edit') }}
                            </button>
                            <button type="button" class="is-active" data-view="split" title="{{ __('settings.doc_templates.view_split_title') }}">
                                <i class="feather icon-columns"></i> {{ __('settings.doc_templates.view_split') }}
                            </button>
                            <button type="button" data-view="preview" title="{{ __('settings.doc_templates.view_preview_title') }}">
                                <i class="feather icon-eye"></i> {{ __('settings.doc_templates.view_preview') }}
                            </button>
                        </div>
                    </div>

                    <div class="dt-stage view-split" id="dtStage">

                        {{-- ---------- لوح التحرير ---------- --}}
                        <div class="dt-pane dt-pane-edit">
                            <div class="dt-pane-title">
                                <span><i class="feather icon-edit-3"></i> {{ __('settings.doc_templates.editing') }}</span>
                                <span style="font-weight:400">{{ __('settings.doc_templates.values_by_name') }}</span>
                            </div>

                            {{-- خارج ألواح التحرير عمداً: التنبيه يجب أن يُرى أياً كان
                                 اللوح النشط، وكان داخل اللوح المرئي فيُخفى معه --}}
                            <div class="dt-note d-none" id="editorFallback">
                                <i class="feather icon-alert-triangle"></i>
                                <b>{{ __('settings.doc_templates.editor_failed_title') }}</b>
                                {{ __('settings.doc_templates.editor_failed_body') }}
                                <span id="fallbackReason" style="display:block;margin-top:.25rem;opacity:.85"></span>
                            </div>

                            <div class="dt-sub is-active" data-sub="visual">
                                <div class="dt-editor-host">
                                    <textarea id="visualEditor">{!! $editorContent !!}</textarea>
                                </div>
                            </div>

                            <div class="dt-sub" data-sub="source">
                                <div class="dt-note">
                                    <i class="feather icon-alert-triangle"></i>
                                    {{ __('settings.doc_templates.html_hint') }}
                                </div>
                                <textarea id="sourceEditor" class="dt-code" spellcheck="false">{{ $template->content }}</textarea>
                            </div>

                            <div class="dt-sub" data-sub="styles">
                                <div class="dt-note">
                                    <i class="feather icon-info"></i>
                                    {{ __('settings.doc_templates.css_hint') }}
                                </div>
                                <textarea name="styles" id="stylesEditor" class="dt-code" spellcheck="false">{{ $manualStyles }}</textarea>
                            </div>
                        </div>

                        {{-- ---------- لوح المعاينة ---------- --}}
                        <div class="dt-pane dt-pane-view">
                            <div class="dt-pane-title">
                                <span><i class="feather icon-eye"></i> {{ __('settings.doc_templates.preview') }}</span>
                                <span style="font-weight:400">{{ __('settings.doc_templates.preview_sample_hint') }}</span>
                            </div>

                            <div class="dt-desk" id="previewDesk">
                                @if ($isThermal)
                                    <div class="dt-printer">
                                        <div class="slot"></div>
                                        <div class="dt-sheet" id="previewSheet">
                                            <iframe id="previewFrame" title="{{ __('settings.doc_templates.preview_frame_title') }}"></iframe>
                                        </div>
                                        <div class="tear"></div>
                                    </div>
                                @else
                                    <div class="dt-sheet" id="previewSheet">
                                        <iframe id="previewFrame" title="{{ __('settings.doc_templates.preview_frame_title') }}"></iframe>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- المحتوى المرسَل فعلياً — يُملأ عند الحفظ حسب المحرّر النشط --}}
            <textarea name="content" id="contentField" class="d-none"></textarea>

            <div class="dt-actions">
                <span class="dt-status" id="status">
                    <i class="feather icon-loader"></i> {{ __('settings.doc_templates.loading_editor') }}
                </span>
                <div>
                    <a href="{{ route('document-templates.index') }}" class="btn btn-outline-secondary">{{ __('settings.common.cancel') }}</a>
                    <button type="submit" class="btn btn-primary" id="btnSave">
                        <i class="feather icon-save"></i> {{ __('settings.doc_templates.save_template') }}
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

            // ==================== ثوابت الصفحة ====================
            /**
             * المحرّر مستضاف داخل المشروع، لكن الرابط الذي يبنيه الخادم قد لا
             * يطابق العنوان الذي يتصفّح منه المستخدم: المشروع يُخدَم هنا من
             * منفذين بمسارين مختلفين. لذا تُجرَّب المسارات بالترتيب، وأول
             * واحد ينجح هو الذي يُعتمد — وإن فشلت كلها ظهرت أسماؤها للمستخدم.
             */
            var TINYMCE_BASES = @json($tinymceBases);
            var TINYMCE_BASE = TINYMCE_BASES[0];
            var PREVIEW_URL = @json(route('document-templates.preview-html'));
            var SAVE_URL = @json(route('document-templates.update', $template->id));
            var CSRF = @json(csrf_token());
            var DOC_TYPE = @json($template->document_type);
            var PAPER = @json($template->paper_size);
            var BLOCKS = @json(collect($blocks)->map(fn($b) => ['label' => $b['label'], 'html' => $b['html']]));
            var CONDITIONS = @json($conditions);
            var PAPER_W = {{ $paperWidth }};
            var PAPER_H = {{ $paperHeight }};
            var PAPER_PAD = {{ $paperPadding }};
            var IS_THERMAL = {{ $isThermal ? 'true' : 'false' }};
            var HAD_ITEMS_LOOP = {{ $hasItemsLoop ? 'true' : 'false' }};
            var MM = 96 / 25.4; // مليمتر ← بكسل عند 96dpi

            var el = function (id) { return document.getElementById(id); };
            var statusEl = el('status');
            var stage = el('dtStage');
            var shell = el('dtShell');
            var editor = null;
            var dirty = false;
            var viewMode = 'split';
            var subMode = 'visual';
            /** الحقل المسحوب حالياً — تُقرأ منه الحمولة أثناء `dragover` */
            var dragged = null;
            var initWatchdog = null;

            function setStatus(icon, text, state) {
                statusEl.className = 'dt-status' + (state ? ' is-' + state : '');
                statusEl.innerHTML = '<i class="feather icon-' + icon + '"></i> ' + text;
            }

            function markDirty() {
                dirty = true;
                setStatus('edit-2', @json(__('settings.doc_templates.unsaved')), 'dirty');
            }

            // ==================== تشغيل المحرّر ====================
            // يُستدعى في آخر النص لا هنا: لو فشل التشغيل وجب أن تكون بقية
            // أزرار الصفحة مربوطة أصلاً، وإلا بقي المستخدم أمام صفحة جامدة.
            function initEditor() {

            // حزمة اللغة تُحمَّل من خادم Tiny بشكل منفصل؛ تعريفها هنا يجعل
            // الواجهة عربية دون طلب خارجي إضافي قد يفشل.
            tinymce.addI18n(@json(app()->getLocale()), @json(__('settings.doc_templates.editor_i18n')));

            // ==================== ورقة التحرير ====================
            // الجسم بعرض الورقة الحقيقي بالمليمتر فوق خلفية «مكتب» رمادية —
            // فيرى المستخدم حدود الصفحة كما في الوورد لا مربع نص بلا حدود.
            var CONTENT_STYLE = [
                "@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&family=Cairo:wght@400;600;700&display=swap');",
                "html{background:#e8ebf2;padding:16px 0 28px}",
                "body{width:" + PAPER_W + "mm;min-height:" + PAPER_H + "mm;margin:0 auto;",
                "background:#fff;box-shadow:0 4px 20px rgba(15,20,40,.18);padding:" + PAPER_PAD + "mm;",
                "font-family:'Tajawal','Cairo',sans-serif;direction:rtl;box-sizing:border-box}",
                ".doc-field{background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;border-radius:4px;",
                "padding:0 5px;font-size:.92em;white-space:nowrap;display:inline-block}",
                ".doc-field-item{background:#dcfce7;color:#166534;border-color:#86efac}",
                ".doc-raw{background:#fef3c7;color:#92400e;border:1px dashed #fbbf24;border-radius:4px;",
                "padding:0 5px;font-family:monospace;font-size:.85em}",
                "[data-repeat]{outline:2px dashed #16a34a;outline-offset:1px}",
                "[data-if]{outline:1px dashed #f59e0b;outline-offset:1px;background:#fffbf3}",
                "table{border-collapse:collapse;width:100%}",
                "td,th{border:1px solid #d5d8e0;padding:5px}",
                "th{background:#f4f5fa}",
                "img{max-width:100%}"
            ].join('');

            tinymce.init({
                selector: '#visualEditor',
                // المسار صريح حتى لا يعتمد الاستنتاج على وسم النص البرمجي
                base_url: TINYMCE_BASE,
                suffix: '.min',
                license_key: 'gpl',
                height: '100%',
                menubar: false,
                statusbar: false,
                directionality: 'rtl',
                language: 'ar',
                branding: false,
                promotion: false,
                resize: false,
                // `sliding` يدفع أزرار الفائض خلف زر «المزيد» — وكانت أزرارنا
                // الأربعة (إدراج حقل، كتلة جاهزة، صف متكرر، إظهار بشرط) هي
                // المخفية. `wrap` يوزّعها على سطرين وتبقى كلها ظاهرة.
                toolbar_mode: 'wrap',
                plugins: 'lists advlist table link image charmap searchreplace code visualblocks pagebreak',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline forecolor backcolor'
                    + ' | alignright aligncenter alignleft alignjustify | bullist numlist outdent indent'
                    + ' | insertfield insertblock repeatrow showif | table link image hr pagebreak'
                    + ' | searchreplace charmap removeformat code',
                font_family_formats: 'Tajawal=Tajawal;Cairo=Cairo;Almarai=Almarai;Amiri=Amiri;'
                    + 'Arial=arial,helvetica,sans-serif;Times=times new roman,times',
                font_size_formats: '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 24pt 36pt',
                content_style: CONTENT_STYLE,
                // الشرائح والعلامات جزء من بنية القالب — يجب ألا ينظّفها المحرر.
                // `@[…]` قاعدة عامة تسري على كل العناصر، فالشرط يصحّ على `th`
                // و`td` و`table` أيضاً لا على `div` وحده.
                extended_valid_elements: '@[data-if|data-repeat|data-repeat-wrapper|data-field|data-raw|contenteditable],'
                    + 'span[class|style|data-field|data-raw|contenteditable]',
                valid_children: '+body[style]',
                verify_html: false,
                convert_urls: false,

                setup: function (ed) {
                    editor = ed;

                    // ---------- إدراج حقل ----------
                    ed.ui.registry.addMenuButton('insertfield', {
                        text: @json(__('settings.doc_templates.insert_field')),
                        tooltip: @json(__('settings.doc_templates.insert_field_tip')),
                        fetch: function (callback) {
                            var groups = [];
                            document.querySelectorAll('#fieldList .dt-field-group').forEach(function (group) {
                                var title = group.querySelector('.dt-group-title').textContent.trim();
                                var items = [];
                                group.querySelectorAll('.dt-chip').forEach(function (chip) {
                                    items.push({
                                        type: 'menuitem',
                                        text: chip.dataset.label,
                                        onAction: function () {
                                            insertField(chip.dataset.field, chip.dataset.label, chip.dataset.item);
                                        }
                                    });
                                });
                                groups.push({
                                    type: 'nestedmenuitem',
                                    text: title,
                                    getSubmenuItems: function () { return items; }
                                });
                            });
                            callback(groups);
                        }
                    });

                    // ---------- إدراج كتلة جاهزة ----------
                    ed.ui.registry.addMenuButton('insertblock', {
                        text: @json(__('settings.doc_templates.insert_block')),
                        tooltip: @json(__('settings.doc_templates.insert_block_tip')),
                        fetch: function (callback) {
                            callback(Object.keys(BLOCKS).map(function (key) {
                                return {
                                    type: 'menuitem',
                                    text: BLOCKS[key].label,
                                    onAction: function () { insertBlock(key); }
                                };
                            }));
                        }
                    });

                    // ---------- الصف المتكرر ----------
                    ed.ui.registry.addToggleButton('repeatrow', {
                        text: @json(__('settings.doc_templates.repeat_row')),
                        tooltip: @json(__('settings.doc_templates.repeat_row_tip')),
                        onAction: function () {
                            var row = ed.dom.getParent(ed.selection.getNode(), 'tr');

                            if (!row) {
                                ed.notificationManager.open({
                                    text: @json(__('settings.doc_templates.cursor_in_row')), type: 'warning', timeout: 3500
                                });
                                return;
                            }

                            if (row.hasAttribute('data-repeat')) {
                                ed.dom.setAttrib(row, 'data-repeat', null);
                            } else {
                                ed.dom.setAttrib(row, 'data-repeat', 'items');
                            }
                            ed.fire('change');
                        },
                        onSetup: function (api) {
                            var update = function () {
                                var row = ed.dom.getParent(ed.selection.getNode(), 'tr');
                                api.setActive(!!(row && row.hasAttribute('data-repeat')));
                            };
                            ed.on('NodeChange', update);
                            return function () { ed.off('NodeChange', update); };
                        }
                    });

                    // ---------- إظهار بشرط ----------
                    ed.ui.registry.addMenuButton('showif', {
                        text: @json(__('settings.doc_templates.show_if')),
                        tooltip: @json(__('settings.doc_templates.show_if_tip')),
                        fetch: function (callback) {
                            var items = Object.keys(CONDITIONS).map(function (key) {
                                return {
                                    type: 'menuitem',
                                    text: CONDITIONS[key],
                                    onAction: function () { applyCondition(key); }
                                };
                            });
                            items.push({ type: 'separator' });
                            items.push({
                                type: 'menuitem',
                                text: @json(__('settings.doc_templates.remove_condition')),
                                onAction: function () { applyCondition(null); }
                            });
                            callback(items);
                        }
                    });

                    // ---------- الإفلات من لوحة الحقول ----------
                    // بلا منع السلوك الافتراضي في `dragover` يرفض المتصفح
                    // الإفلات أصلاً فلا يصل الحدث `drop` إطلاقاً. ولا يمكن
                    // قراءة `dataTransfer` أثناء السحب لأسباب أمنية، لذا
                    // يُحتفظ بالحمولة في متغيّر منذ بدء السحب.
                    ed.on('dragover', function (event) {
                        if (!dragged) { return; }
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'copy';
                        moveCaretTo(event);
                    });

                    ed.on('dragenter', function () {
                        if (dragged) { document.querySelector('.dt-pane-edit').classList.add('is-dropping'); }
                    });

                    ed.on('dragleave', function () {
                        document.querySelector('.dt-pane-edit').classList.remove('is-dropping');
                    });

                    ed.on('drop', function (event) {
                        if (!dragged) { return; }
                        event.preventDefault();

                        var data = dragged;
                        dragged = null;
                        document.querySelector('.dt-pane-edit').classList.remove('is-dropping');

                        moveCaretTo(event);
                        insertField(data.field, data.label, data.item);
                    });

                    ed.on('change keyup SetContent ExecCommand', function () {
                        markDirty();
                        schedulePreview();
                    });

                    ed.addShortcut('meta+s', @json(__('settings.doc_templates.save_template')), save);
                },

                init_instance_callback: function () {
                    clearTimeout(initWatchdog);
                    setStatus('check-circle', @json(__('settings.doc_templates.ready_to_edit')), 'ok');
                    applyZoom();
                    refreshPreview();
                }
            }).catch(function (error) {
                editorFailed(error && error.message);
            });

            // ملف قالب أو تنسيق ناقص يجعل TinyMCE ينتظر بلا وعد مرفوض،
            // فتبقى الصفحة على «جارٍ التحميل» إلى الأبد. الحارس يكسر الانتظار.
            initWatchdog = setTimeout(function () {
                if (!editor || !editor.initialized) {
                    editorFailed(@json(__('settings.doc_templates.editor_timeout')));
                }
            }, 20000);

            } // نهاية initEditor

            /**
             * ينقل مؤشر الكتابة إلى النقطة التي يقف عندها الفأر داخل الورقة.
             *
             * الإحداثيات نسبةً إلى إطار المحرّر، وهو الإطار نفسه الذي تُستعلَم
             * فيه النقطة، فلا حاجة لأي إزاحة.
             */
            function moveCaretTo(event) {
                if (!editor) { return; }

                var doc = editor.getDoc();

                if (doc.caretRangeFromPoint) {
                    var range = doc.caretRangeFromPoint(event.clientX, event.clientY);
                    if (range) { editor.selection.setRng(range); }
                    return;
                }

                if (doc.caretPositionFromPoint) {
                    var position = doc.caretPositionFromPoint(event.clientX, event.clientY);
                    if (position) {
                        editor.selection.setCursorLocation(position.offsetNode, position.offset);
                    }
                }
            }

            /** يدرج شريحة حقل عند موضع المؤشر */
            function insertField(field, label, isItem) {
                if (!editor) { return; }

                editor.insertContent(
                    '<span class="doc-field' + (isItem ? ' doc-field-item' : '') + '"'
                    + ' data-field="' + field + '" contenteditable="false">' + label + '</span>&nbsp;'
                );
                editor.focus();
            }

            /** يدرج كتلة جاهزة كاملة */
            function insertBlock(key) {
                if (!editor || !BLOCKS[key]) { return; }

                editor.insertContent(BLOCKS[key].html);
                editor.focus();
                markDirty();
                schedulePreview();
            }

            /** يعلّق ظهور العنصر الحالي على مفتاح إعداد، أو يزيل التعليق */
            function applyCondition(key) {
                if (!editor) { return; }

                var node = editor.selection.getNode();
                // أقرب عنصر كامل يصلح للتعليق — الشريحة نفسها ليست عنصراً مستقلاً
                var target = editor.dom.getParent(node, 'tr,td,th,div,table,p,h1,h2,h3,section');

                if (!target || target === editor.getBody()) {
                    editor.notificationManager.open({
                        text: @json(__('settings.doc_templates.cursor_in_element')), type: 'warning', timeout: 3500
                    });
                    return;
                }

                editor.dom.setAttrib(target, 'data-if', key);
                editor.fire('change');

                editor.notificationManager.open({
                    text: key ? @json(__('settings.doc_templates.condition_set')).replace(':condition', CONDITIONS[key]) : @json(__('settings.doc_templates.condition_removed')),
                    type: 'success',
                    timeout: 3000
                });
            }

            // ==================== الرصيف ====================
            document.querySelectorAll('.dt-chip').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    insertField(chip.dataset.field, chip.dataset.label, chip.dataset.item);
                });

                chip.addEventListener('dragstart', function (event) {
                    dragged = {
                        field: chip.dataset.field,
                        label: chip.dataset.label,
                        item: chip.dataset.item
                    };

                    // فايرفوكس لا يبدأ السحب ما لم تُسجَّل بيانات ما
                    event.dataTransfer.setData('text/doc-field', JSON.stringify(dragged));
                    event.dataTransfer.effectAllowed = 'copy';
                    chip.classList.add('is-dragging');

                    // السحب إلى وضع لا تظهر فيه الورقة بلا معنى
                    if (subMode !== 'visual') { setSubMode('visual'); }
                    if (viewMode === 'preview') { setViewMode('split'); }
                });

                chip.addEventListener('dragend', function () {
                    dragged = null;
                    chip.classList.remove('is-dragging');
                    document.querySelector('.dt-pane-edit').classList.remove('is-dropping');
                });
            });

            document.querySelectorAll('.dt-block').forEach(function (button) {
                button.addEventListener('click', function () { insertBlock(button.dataset.block); });
            });

            document.querySelectorAll('.dt-dock-tabs button').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.querySelectorAll('.dt-dock-tabs button').forEach(function (other) {
                        other.classList.toggle('is-active', other === button);
                    });
                    document.querySelectorAll('.dt-dock-pane').forEach(function (pane) {
                        pane.classList.toggle('is-active', pane.dataset.dock === button.dataset.dock);
                    });
                });
            });

            var search = el('fieldSearch');
            search.addEventListener('input', function () {
                var term = search.value.trim();
                var anyVisible = false;

                document.querySelectorAll('#fieldList .dt-field-group').forEach(function (group) {
                    var visible = 0;
                    group.querySelectorAll('.dt-chip').forEach(function (chip) {
                        var match = !term
                            || chip.dataset.label.indexOf(term) !== -1
                            || chip.dataset.field.indexOf(term.toLowerCase()) !== -1;
                        chip.style.display = match ? '' : 'none';
                        if (match) { visible++; }
                    });
                    group.style.display = visible ? '' : 'none';
                    if (visible) { anyVisible = true; }
                });

                el('fieldEmpty').classList.toggle('d-none', anyVisible);
            });

            el('dockToggle').addEventListener('click', function () {
                var closed = shell.classList.toggle('dock-closed');
                this.classList.toggle('is-off', closed);
                localStorage.setItem('dt.dock', closed ? 'closed' : 'open');
                scalePreview();
            });

            if (localStorage.getItem('dt.dock') === 'closed') {
                shell.classList.add('dock-closed');
                el('dockToggle').classList.add('is-off');
            }

            // ---------- شريط الإرشاد ----------
            if (localStorage.getItem('dt.guide') === 'off') {
                el('dtGuide').style.display = 'none';
            }
            el('dtGuideClose').addEventListener('click', function () {
                el('dtGuide').style.display = 'none';
                localStorage.setItem('dt.guide', 'off');
            });

            // ==================== أوضاع العرض ====================

            /** تحرير / تحرير + معاينة / معاينة */
            function setViewMode(mode) {
                viewMode = mode;
                stage.className = 'dt-stage view-' + mode;

                document.querySelectorAll('#segView button').forEach(function (button) {
                    button.classList.toggle('is-active', button.dataset.view === mode);
                });

                localStorage.setItem('dt.view', mode);

                if (mode === 'edit') { return; }

                // المعاينة كانت مخفية فمحتواها قديم — تُحدَّث عند إظهارها
                refreshPreview();
            }

            /** تحرير مرئي / HTML / CSS داخل لوح التحرير */
            function setSubMode(mode) {
                // الخروج من HTML: تُعاد بناء الورقة من النص المكتوب
                if (subMode === 'source' && mode !== 'source' && editor) {
                    syncSourceToVisual();
                }

                subMode = mode;

                document.querySelectorAll('#segSub button').forEach(function (button) {
                    button.classList.toggle('is-active', button.dataset.sub === mode);
                });
                document.querySelectorAll('.dt-sub').forEach(function (pane) {
                    pane.classList.toggle('is-active', pane.dataset.sub === mode);
                });

                // الوضع المرسَل مع النموذج يتبع آخر محرّر استُعمل للمحتوى
                if (mode === 'source' || mode === 'visual') {
                    el('editor_mode').value = mode;
                }

                // CSS لا يغيّر المحتوى، فوضع الحفظ يبقى كما كان
                el('zoomSelect').disabled = mode !== 'visual';
            }

            document.querySelectorAll('#segView button').forEach(function (button) {
                button.addEventListener('click', function () { setViewMode(button.dataset.view); });
            });

            document.querySelectorAll('#segSub button').forEach(function (button) {
                button.addEventListener('click', function () { setSubMode(button.dataset.sub); });
            });

            // ---------- الوضع المتقدّم ----------
            function setAdvanced(on) {
                document.querySelectorAll('.js-advanced').forEach(function (button) {
                    button.style.display = on ? '' : 'none';
                });
                el('advancedToggle').classList.toggle('is-off', !on);
                localStorage.setItem('dt.advanced', on ? '1' : '0');
            }

            el('advancedToggle').addEventListener('click', function () {
                var on = this.classList.contains('is-off');
                setAdvanced(on);

                // إخفاء المحرّر النشط يترك اللوح فارغاً — نعود إلى التحرير
                if (!on && subMode !== 'visual') { setSubMode('visual'); }
            });

            setAdvanced(localStorage.getItem('dt.advanced') === '1');

            // ---------- لوحة التصميم ----------
            /**
             * الخيارات التي لمسها المستخدم فقط.
             *
             * الخيار غير الملموس لا يُرسَل، فلا تُكتب له قاعدة CSS ويبقى تنسيق
             * القالب الأصلي كما هو — 356 قالباً جاهزاً لا يجوز أن تتغيّر بمجرّد
             * فتح الصفحة وحفظها.
             */
            function appendStyleOptions(payload) {
                document.querySelectorAll('.js-style[data-touched="1"]').forEach(function (input) {
                    var value = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value;
                    payload.append('style_options[' + input.dataset.style + ']', value);
                });
            }

            document.querySelectorAll('.js-style').forEach(function (input) {
                var apply = function () {
                    input.dataset.touched = '1';
                    markDirty();
                    schedulePreview();
                };

                input.addEventListener('change', apply);
                // السحب داخل منتقي اللون أو حقل الرقم يُظهر الأثر أثناء السحب
                if (input.type === 'color' || input.type === 'number') {
                    input.addEventListener('input', apply);
                }
            });

            document.querySelectorAll('.js-style-reset').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.querySelectorAll('.js-style[data-group="' + button.dataset.group + '"]')
                        .forEach(function (input) {
                            input.removeAttribute('data-touched');
                            delete input.dataset.touched;

                            if (input.type === 'checkbox') {
                                input.checked = input.dataset.default === '1';
                            } else {
                                input.value = input.dataset.default;
                            }
                        });

                    markDirty();
                    refreshPreview();
                });
            });

            // ---------- التكبير ----------
            var zoom = el('zoomSelect');
            zoom.addEventListener('change', applyZoom);

            function applyZoom() {
                if (!editor || !editor.getBody()) { return; }
                editor.getBody().style.zoom = zoom.value;
            }

            // ==================== تحويل المصدر ====================
            function syncSourceToVisual() {
                setStatus('loader', @json(__('settings.doc_templates.building_page')));

                fetch(PREVIEW_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                    body: buildPayload('source', true)
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data && data.editor_html !== undefined) {
                            editor.setContent(data.editor_html);
                            applyZoom();
                        }
                        markDirty();
                    })
                    .catch(function () {
                        setStatus('alert-triangle', @json(__('settings.doc_templates.html_parse_failed')), 'error');
                    });
            }

            // ==================== المعاينة ====================
            var previewTimer = null;
            var previewInFlight = null;

            /** محتوى القالب من المحرّر النشط — يصحّ أيضاً بلا محرّر مرئي */
            function currentContent(mode) {
                if (mode === 'source' || !editor) { return el('sourceEditor').value; }

                return editor.getContent();
            }

            function buildPayload(mode, wantEditorHtml) {
                var payload = new FormData();
                payload.append('_token', CSRF);
                payload.append('editor_mode', mode);
                payload.append('content', currentContent(mode));
                payload.append('styles', el('stylesEditor').value);
                payload.append('document_type', DOC_TYPE);
                payload.append('paper_size', PAPER);
                payload.append('font_size', el('font_size').value);
                payload.append('font_family', el('font_family').value);
                payload.append('primary_color', el('primary_color').value);
                payload.append('secondary_color', el('secondary_color').value);

                ['show_logo', 'show_qr', 'show_barcode', 'show_customer', 'show_tax', 'show_discount']
                    .forEach(function (key) { payload.append(key, el(key).checked ? 'true' : 'false'); });

                appendStyleOptions(payload);

                if (wantEditorHtml) { payload.append('want_editor_html', '1'); }

                return payload;
            }

            function refreshPreview() {
                if (viewMode === 'edit') { return; }
                if (previewInFlight) { previewInFlight.abort(); }

                var controller = new AbortController();
                previewInFlight = controller;

                fetch(PREVIEW_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                    // بلا محرّر مرئي يكون المصدر هو الحقيقة الوحيدة
                    body: buildPayload(subMode === 'source' || !editor ? 'source' : 'visual'),
                    signal: controller.signal
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        var frame = el('previewFrame');
                        var doc = frame.contentDocument || frame.contentWindow.document;
                        doc.open();
                        doc.write('<!doctype html><html dir="rtl"><head><meta charset="utf-8">'
                            + '<style>html,body{margin:0;padding:0}</style></head><body>'
                            + (data.html || '') + '</body></html>');
                        doc.close();
                        scalePreview();
                    })
                    .catch(function (error) {
                        if (error.name !== 'AbortError') {
                            setStatus('alert-triangle', @json(__('settings.doc_templates.preview_failed')), 'error');
                        }
                    });
            }

            function schedulePreview() {
                clearTimeout(previewTimer);
                previewTimer = setTimeout(refreshPreview, 450);
            }

            /**
             * ورقة المعاينة تُصغَّر بـ transform لتظهر الصفحة كاملة.
             * بلا هذا يعرض اللوح ركناً من الصفحة فقط، وهو أسوأ من لا شيء.
             */
            function scalePreview() {
                var desk = el('previewDesk');
                var sheet = el('previewSheet');

                if (!desk || !sheet || viewMode === 'edit') { return; }

                var frame = el('previewFrame');
                var naturalW = Math.round(PAPER_W * MM);
                var naturalH = Math.round(PAPER_H * MM);

                // حشو المكتب 12px على الجانبين، وإطار الطابعة 10px إضافية
                var available = desk.clientWidth - 24 - (IS_THERMAL ? 20 : 0);
                var scale = Math.min(1, Math.max(0.1, available / naturalW));

                frame.style.width = naturalW + 'px';
                frame.style.height = naturalH + 'px';
                frame.style.transform = 'scale(' + scale + ')';

                sheet.style.width = Math.round(naturalW * scale) + 'px';
                sheet.style.height = Math.round(naturalH * scale) + 'px';
            }

            var resizeTimer = null;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(scalePreview, 120);
            });

            document.querySelectorAll('.js-setting').forEach(function (input) {
                input.addEventListener('change', function () {
                    markDirty();
                    refreshPreview();
                });
            });

            el('stylesEditor').addEventListener('input', function () {
                markDirty();
                schedulePreview();
            });

            el('sourceEditor').addEventListener('input', function () {
                markDirty();
                schedulePreview();
            });

            el('name').addEventListener('input', markDirty);

            // ==================== الحفظ ====================
            function save() {
                var form = el('dtForm');
                var content = currentContent(el('editor_mode').value);

                // حذف حلقة الأصناف يعني فاتورة بلا بنود — يُسأل المستخدم قبل الحفظ
                if (HAD_ITEMS_LOOP
                    && content.indexOf('data-repeat') === -1
                    && content.indexOf('@{{#items}}') === -1
                    && !window.confirm(@json(__('settings.doc_templates.no_repeat_row')))) {
                    return;
                }

                el('contentField').value = content;

                var button = el('btnSave');
                button.disabled = true;
                setStatus('loader', @json(__('settings.doc_templates.saving')));

                // خيارات التصميم ليست حقولاً مسمّاة في النموذج — تُضاف يدوياً
                var payload = new FormData(form);
                appendStyleOptions(payload);

                fetch(SAVE_URL, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: payload
                })
                    .then(function (response) {
                        return response.json().then(function (body) {
                            return { ok: response.ok, body: body };
                        });
                    })
                    .then(function (result) {
                        button.disabled = false;

                        if (!result.ok) {
                            var messages = result.body && result.body.errors
                                ? Object.keys(result.body.errors).map(function (key) {
                                    return result.body.errors[key][0];
                                }).join(' — ')
                                : (result.body && result.body.message) || @json(__('settings.doc_templates.save_failed'));

                            setStatus('alert-triangle', messages, 'error');
                            if (typeof toastr !== 'undefined') { toastr.error(messages); }
                            return;
                        }

                        dirty = false;
                        setStatus('check-circle', @json(__('settings.doc_templates.saved')), 'ok');
                        if (typeof toastr !== 'undefined') {
                            toastr.success(result.body.message || @json(__('settings.doc_templates.saved_ok')));
                        }
                    })
                    .catch(function () {
                        button.disabled = false;
                        setStatus('alert-triangle', @json(__('settings.doc_templates.connection_failed')), 'error');
                    });
            }

            el('dtForm').addEventListener('submit', function (event) {
                event.preventDefault();
                save();
            });

            el('btnSaveTop').addEventListener('click', save);

            document.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    save();
                }
            });

            window.addEventListener('beforeunload', function (event) {
                if (!dirty) { return; }
                event.preventDefault();
                event.returnValue = '';
            });

            // ==================== الإقلاع ====================

            /** المحرّر المرئي غير متاح: الصفحة تعمل من تبويب HTML بلا نقص */
            function editorFailed(reason) {
                clearTimeout(initWatchdog);
                editor = null;

                el('editorFallback').classList.remove('d-none');
                el('fallbackReason').textContent = reason || '';

                // مربع النص الخام لا يجوز أن يظهر: يبدو حقلاً صغيراً بلا وظيفة
                el('visualEditor').style.display = 'none';
                document.querySelector('.dt-sub[data-sub="visual"]').classList.remove('is-active');
                document.querySelector('#segSub button[data-sub="visual"]').style.display = 'none';

                // محرّرا الكود مخفيان افتراضياً — بلا محرّر مرئي لا بدّ من إظهارهما
                setAdvanced(true);
                setSubMode('source');
                setStatus('alert-triangle', @json(__('settings.doc_templates.editor_unavailable')), 'error');
                refreshPreview();

                if (reason) { console.error('TinyMCE: ' + reason); }
            }

            /** يحمّل نصاً برمجياً ويَعِد بنجاحه أو فشله */
            function loadScript(url) {
                return new Promise(function (resolve, reject) {
                    var tag = document.createElement('script');
                    tag.src = url;
                    tag.onload = resolve;
                    tag.onerror = function () { reject(new Error(url)); };
                    document.head.appendChild(tag);
                });
            }

            /** يجرّب مسارات المحرّر بالترتيب حتى ينجح أحدها */
            function loadTinymce() {
                // نسخة محمّلة سلفاً في الصفحة (من قالب رئيسي مثلاً): تُستعمل
                // بمسارها هي. فرض مسارنا عليها يجعلها تطلب ملفات لا تطابق
                // بنيتها فتتعلّق التهيئة بلا خطأ.
                if (typeof tinymce !== 'undefined') {
                    TINYMCE_BASE = tinymce.baseURL || TINYMCE_BASE;

                    return Promise.resolve();
                }

                var tried = [];

                var chain = TINYMCE_BASES.reduce(function (previous, base) {
                    return previous.catch(function () {
                        tried.push(base);

                        return loadScript(base + '/tinymce.min.js').then(function () {
                            if (typeof tinymce === 'undefined') {
                                throw new Error(@json(__('settings.doc_templates.tinymce_missing')));
                            }
                            TINYMCE_BASE = base;
                        });
                    });
                }, Promise.reject());

                return chain.catch(function () {
                    throw new Error(@json(__('settings.doc_templates.tinymce_load_failed')).replace(':paths', tried.join('  |  ')));
                });
            }

            // الوضع المحفوظ من الجلسة السابقة، وإلا «تحرير + معاينة»
            setViewMode(localStorage.getItem('dt.view') || 'split');
            setStatus('loader', @json(__('settings.doc_templates.loading_editor')));

            loadTinymce()
                .then(function () {
                    try {
                        initEditor();
                    } catch (error) {
                        editorFailed(@json(__('settings.doc_templates.editor_start_failed')).replace(':error', error.message));
                    }
                })
                .catch(function (error) { editorFailed(error.message); });
        })();
    </script>
@endsection
