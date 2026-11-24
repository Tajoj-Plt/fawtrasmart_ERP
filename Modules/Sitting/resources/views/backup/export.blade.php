@extends('sitting::master')

@section('title', __('settings.export.title'))

@section('content')

    {{-- أنماط أدوات التحديد — القائمة صارت تعرض جداول القاعدة كلّها --}}
    <style>
        .ex-tools { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap;
            padding:.45rem .55rem; border-bottom:1px solid #e3e7ef; background:#fbfcfe; }
        .ex-tool { border:1px solid #dfe3ec; background:#fff; border-radius:7px; cursor:pointer;
            font-size:.74rem; font-weight:600; color:#4a5069; padding:.25rem .55rem; }
        .ex-tool:hover { background:#f2f4f8; }
        .ex-total { margin-inline-start:auto; font-size:.71rem; color:#79809a; }

        .ex-group { display:flex; align-items:center; gap:.4rem; }
        .ex-group-btn { margin-inline-start:auto; border:0; background:transparent; cursor:pointer;
            color:#9aa1b5; padding:0 .2rem; font-size:.75rem; }
        .ex-group-btn:hover { color:var(--app-primary,#7367F0); }

        .ex-sensitive { font-style:normal; font-size:.64rem; background:#fdeeee; color:#a13333;
            border:1px solid #f5cccc; border-radius:20px; padding:0 .4rem; margin-inline-start:.35rem; }

        .ex-warn { display:flex; align-items:flex-start; gap:.4rem; margin-top:.5rem;
            font-size:.72rem; line-height:1.7; color:#8a5b00; background:#fff8e6;
            border:1px solid #ffe0a3; border-radius:8px; padding:.45rem .6rem; }
        .ex-warn i { margin-top:.15rem; flex:0 0 auto; }
    </style>

    @include('sitting::cron_jobs._styles')

    <style>
        .ex-panel { border: 1px solid #e0e3e9; border-radius: 8px; margin-bottom: .75rem; }
        .ex-panel-head {
            display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
            padding: .6rem .8rem; background: #f8f9fb; border-bottom: 1px solid #e6e9ef;
        }
        .ex-panel-head .ex-title { font-weight: 600; }
        .ex-panel-head .ex-drop { margin-right: auto; }
        .ex-panel-body { padding: .8rem; }
        .ex-cols {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: .2rem .8rem; max-height: 240px; overflow-y: auto;
            border: 1px solid #eef0f4; border-radius: 6px; padding: .5rem;
        }
        .ex-col { display: flex; align-items: center; gap: .4rem; margin: 0; padding: .15rem 0; cursor: pointer; }
        .ex-col input { margin: 0; flex: 0 0 auto; }
        .ex-col .ex-type { font-size: .7rem; color: #9aa2ae; margin-right: auto; }
        .ex-filter { display: flex; gap: .4rem; align-items: center; margin-bottom: .4rem; flex-wrap: wrap; }
        .ex-filter select, .ex-filter input { flex: 1 1 140px; min-width: 110px; }
        .ex-filter .btn { flex: 0 0 auto; }
        .ex-group {
            padding: .45rem .7rem .2rem; font-size: .74rem; font-weight: 700;
            color: #8a94a0; letter-spacing: .04em; background: #fbfcfd;
            border-bottom: 1px solid #f1f3f6; position: sticky; top: 0;
        }
    </style>

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-database"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.export.title') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.dashboard') }}">{{ __('settings.common.settings') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.export.title') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('Backup.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-database"></i> {{ __('settings.backup.title') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- الفرق بين التصدير والنسخة الاحتياطية يجب أن يُقال صراحةً:
             من يظنّ ملفّ Excel نسخةً احتياطية يكتشف عند العطل أنه لا يملك
             ما يعيد به النظام --}}
        <div class="cj-health unknown">
            <span class="dot"></span>
            <div>
                <strong>{{ __('settings.export.not_backup_title') }}</strong>
                <div style="font-size:.85rem;margin-top:.25rem">
                    {!! __('settings.export.not_backup_body', ['link' => '<a href="'.route('Backup.index').'" style="color:inherit;text-decoration:underline">'.__('settings.backup.title').'</a>']) !!}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('DataExport.run') }}" id="exportForm">
            @csrf

            <div class="row">
                <div class="col-md-4 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.export.step_records') }}</h4>
                        </div>
                        <div class="card-body">
                            {{-- قائمة مسمّاة بالعربية ومجموعة بالأقسام، لا أسماء
                                 جداول القاعدة. ما ليس هنا لا يُصدَّر أصلاً --}}
            @php
                $tableCount = collect($groups)->flatten(1)->count();
            @endphp

                            <div class="bk-picker" id="picker-export">
                                <div class="bk-picker-bar">
                                    <input type="text" class="form-control form-control-sm bk-picker-search"
                                        placeholder="{{ __('settings.export.search') }}" autocomplete="off">
                                    <span class="bk-picker-count cj-sub"></span>
                                </div>

                                {{-- الجداول تجاوزت الثلاثمئة، فتحديدها واحداً واحداً
                                     غير عملي. «تحديد الكل» يتخطّى الحسّاسة عمداً --}}
                                <div class="ex-tools">
                                    <button type="button" class="ex-tool" data-act="all">
                                        <i class="feather icon-check-square"></i> {{ __('settings.export.select_all') }}
                                    </button>
                                    <button type="button" class="ex-tool" data-act="none">
                                        <i class="feather icon-square"></i> {{ __('settings.export.clear_all') }}
                                    </button>
                                    <span class="ex-total">{{ __('settings.export.tables_count', [
                                        'count' => $tableCount, 'groups' => count($groups)]) }}</span>
                                </div>

                                <div class="bk-picker-list">
                                    @foreach ($groups as $group => $entities)
                                        <div class="ex-group">
                                            <span>{{ $group }}</span>
                                            <button type="button" class="ex-group-btn" data-act="group"
                                                title="{{ __('settings.export.select_group') }}">
                                                <i class="feather icon-check"></i>
                                            </button>
                                        </div>
                                        @foreach ($entities as $entity)
                                            <label class="bk-picker-item" data-name="{{ $entity['label'] }}"
                                                @if ($entity['hint']) title="{{ $entity['hint'] }}" @endif>
                                                <input type="checkbox" value="{{ $entity['key'] }}"
                                                    @if ($entity['sensitive'] ?? false) data-sensitive="1" @endif>
                                                <span>{{ $entity['label'] }}</span>
                                                @if ($entity['sensitive'] ?? false)
                                                    <em class="ex-sensitive" title="{{ __('settings.export.sensitive_title') }}">
                                                        {{ __('settings.export.sensitive_badge') }}</em>
                                                @endif
                                            </label>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>

                            <div class="ex-warn">
                                <i class="feather icon-shield"></i>
                                <span>{{ __('settings.export.sensitive_note') }}</span>
                            </div>
                            <small class="text-muted">
                                {{ __('settings.export.records_hint') }}
                            </small>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.export.step_format') }}</h4>
                        </div>
                        <div class="card-body">
                            @foreach ($formats as $key => $meta)
                                <div class="custom-control custom-radio mb-1">
                                    <input type="radio" class="custom-control-input" id="fmt-{{ $key }}"
                                        name="format" value="{{ $key }}"
                                        {{ old('format', 'csv') === $key ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="fmt-{{ $key }}">
                                        <i class="feather icon-{{ $meta['icon'] }}"></i>
                                        <strong>{{ $meta['label'] }}</strong>
                                        <div class="cj-sub">{{ $meta['hint'] }}</div>
                                    </label>
                                </div>
                            @endforeach

                            <div class="alert mb-0 mt-1"
                                style="background:#f4f5f7;border:1px solid #e0e3e9;color:#4b5563;border-radius:8px;font-size:.82rem">
                                <i class="feather icon-info"></i>
                                {{ __('settings.export.csv_note') }}
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body text-center">
                            <button type="submit" class="btn btn-primary btn-block" id="exportSubmit" disabled>
                                <i class="feather icon-download"></i> {{ __('settings.export.submit') }}
                            </button>
                            <div class="cj-sub mt-1" id="exportSummary">{{ __('settings.export.none_selected') }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ __('settings.export.step_fields') }}</h4>
                            <span class="cj-sub">{{ __('settings.export.max_rows', ['count' => number_format($maxRows)]) }}</span>
                        </div>
                        <div class="card-body" id="exportPanels">
                            <div class="text-center py-4" id="exportEmpty">
                                <i class="feather icon-filter" style="font-size:2rem;color:#c8ccd4"></i>
                                <div class="mt-1">{{ __('settings.export.pick_record') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- قالب لوحة الجدول — يُستنسَخ في JS عند اختيار جدول --}}
    <template id="exPanelTemplate">
        <div class="ex-panel" data-table="">
            <div class="ex-panel-head">
                <i class="feather icon-database"></i>
                <span class="ex-title"></span>
                <span class="cj-sub ex-meta"></span>
                <button type="button" class="btn btn-sm btn-outline-danger ex-drop">
                    <i class="feather icon-x"></i> {{ __('settings.export.remove') }}
                </button>
            </div>
            <div class="ex-panel-body">
                <div class="d-flex align-items-center mb-1" style="gap:.5rem;flex-wrap:wrap">
                    <strong style="font-size:.88rem">{{ __('settings.export.fields') }}</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary ex-all">{{ __('settings.export.all') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ex-none">{{ __('settings.export.none') }}</button>
                    <span class="cj-sub ex-count"></span>
                    <div class="mr-auto d-flex align-items-center" style="gap:.4rem">
                        <label class="mb-0 cj-sub">{{ __('settings.export.row_limit') }}</label>
                        <input type="number" class="form-control form-control-sm ex-limit"
                            style="width:110px" min="1" value="{{ $maxRows }}">
                    </div>
                </div>

                <div class="ex-cols"></div>

                <div class="mt-1">
                    <strong style="font-size:.88rem">{{ __('settings.export.conditions') }}</strong>
                    <span class="cj-sub">{{ __('settings.export.conditions_hint') }}</span>
                    <div class="ex-filters mt-1"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary ex-add-filter">
                        <i class="feather icon-plus"></i> {{ __('settings.export.add_condition') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
@endsection

@section('scripts')
    <script>
        (function () {
            var panels = document.getElementById('exportPanels');
            var empty = document.getElementById('exportEmpty');
            var picker = document.getElementById('picker-export');
            var template = document.getElementById('exPanelTemplate');
            var submit = document.getElementById('exportSubmit');
            var summary = document.getElementById('exportSummary');
            var columnsUrl = @json(route('DataExport.columns', ['key' => '__KEY__']));
            var operators = @json($operators);

            var index = 0;               // فهرس ثابت لكل لوحة — الإزالة لا تعيد ترقيم الباقي
            var open = Object.create(null);

            function refreshSummary() {
                var count = panels.querySelectorAll('.ex-panel').length;

                empty.style.display = count ? 'none' : '';
                submit.disabled = count === 0;
                summary.textContent = count === 0
                    ? @json(__('settings.export.none_selected'))
                    : (count === 1 ? @json(__('settings.export.will_export_one'))
                                  : @json(__('settings.export.will_export')).replace(':count', count));
            }

            function buildFilterRow(panel, columns, position) {
                var row = document.createElement('div');
                row.className = 'ex-filter';

                var prefix = 'selections[' + panel.dataset.index + '][filters][' + position + ']';

                var columnSelect = '<select class="form-control form-control-sm" name="' + prefix + '[column]">'
                    + columns.map(function (column) {
                        return '<option value="' + column.name + '">' + column.label + '</option>';
                    }).join('') + '</select>';

                var operatorSelect = '<select class="form-control form-control-sm ex-op" name="' + prefix + '[operator]">'
                    + Object.keys(operators).map(function (key) {
                        return '<option value="' + key + '">' + operators[key].label + '</option>';
                    }).join('') + '</select>';

                row.innerHTML = columnSelect + operatorSelect
                    + '<input type="text" class="form-control form-control-sm ex-val" name="' + prefix + '[value]" placeholder="' + @json(__('settings.export.value')) + '">'
                    + '<button type="button" class="btn btn-sm btn-outline-danger ex-del"><i class="feather icon-trash-2"></i></button>';

                // المعاملات «فارغ» و«غير فارغ» بلا قيمة — إبقاء الحقل يوهم
                // بأن كتابة شيء فيه تؤثّر
                var operatorField = row.querySelector('.ex-op');
                var valueField = row.querySelector('.ex-val');

                function syncValue() {
                    var needsValue = operators[operatorField.value] && operators[operatorField.value].value;
                    valueField.style.display = needsValue ? '' : 'none';
                    if (!needsValue) valueField.value = '';
                }

                operatorField.addEventListener('change', syncValue);
                row.querySelector('.ex-del').addEventListener('click', function () { row.remove(); });
                syncValue();

                return row;
            }

            function addPanel(table) {
                if (open[table]) return;

                open[table] = true;

                fetch(columnsUrl.replace('__KEY__', encodeURIComponent(table)), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error(@json(__('settings.export.columns_failed')));
                        return response.json();
                    })
                    .then(function (data) {
                        var panel = template.content.firstElementChild.cloneNode(true);
                        var myIndex = index++;

                        panel.dataset.table = table;
                        panel.dataset.index = myIndex;
                        panel.querySelector('.ex-title').textContent = data.label;
                        panel.querySelector('.ex-meta').textContent = @json(__('settings.export.meta')).replace(':rows', Number(data.rows).toLocaleString('en')).replace(':columns', data.columns.length);

                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'selections[' + myIndex + '][key]';
                        hidden.value = table;
                        panel.appendChild(hidden);

                        panel.querySelector('.ex-limit').name = 'selections[' + myIndex + '][limit]';

                        var box = panel.querySelector('.ex-cols');

                        data.columns.forEach(function (column) {
                            var label = document.createElement('label');
                            label.className = 'ex-col';
                            label.title = column.label;
                            label.innerHTML = '<input type="checkbox" checked name="selections[' + myIndex + '][columns][]" value="'
                                + column.name + '"><span>' + column.label + '</span>';
                            box.appendChild(label);
                        });

                        function refreshCount() {
                            var checked = box.querySelectorAll('input:checked').length;
                            panel.querySelector('.ex-count').textContent = @json(__('settings.export.selected_count')).replace(':selected', checked).replace(':total', data.columns.length);
                        }

                        box.addEventListener('change', refreshCount);
                        panel.querySelector('.ex-all').addEventListener('click', function () {
                            box.querySelectorAll('input').forEach(function (i) { i.checked = true; });
                            refreshCount();
                        });
                        panel.querySelector('.ex-none').addEventListener('click', function () {
                            box.querySelectorAll('input').forEach(function (i) { i.checked = false; });
                            refreshCount();
                        });

                        var filters = panel.querySelector('.ex-filters');
                        var filterCount = 0;

                        panel.querySelector('.ex-add-filter').addEventListener('click', function () {
                            filters.appendChild(buildFilterRow(panel, data.columns, filterCount++));
                        });

                        panel.querySelector('.ex-drop').addEventListener('click', function () {
                            panel.remove();
                            delete open[table];

                            // إلغاء تأشير الجدول في القائمة كي يبقى الجانبان متّفقين
                            var box = picker.querySelector('.bk-picker-item[data-name="' + table + '"] input');
                            if (box) box.checked = false;

                            refreshSummary();
                        });

                        refreshCount();
                        panels.appendChild(panel);
                        refreshSummary();
                    })
                    .catch(function (error) {
                        delete open[table];
                        alert(error.message);
                    });
            }

            picker.addEventListener('change', function (event) {
                if (event.target.type !== 'checkbox') return;

                var table = event.target.value;

                if (event.target.checked) {
                    addPanel(table);
                } else {
                    var panel = panels.querySelector('.ex-panel[data-table="' + table + '"]');
                    if (panel) panel.remove();
                    delete open[table];
                    refreshSummary();
                }
            });

            // مربّعات اختيار المنتقي تحمل الاسم `__tables[]` وهي للواجهة فقط —
            // تُعطَّل عند الإرسال كي لا تصل إلى الخادم بلا داعٍ
            document.getElementById('exportForm').addEventListener('submit', function () {
                picker.querySelectorAll('input[type=checkbox]').forEach(function (box) { box.disabled = true; });
                submit.disabled = true;
                submit.innerHTML = '<i class="feather icon-loader"></i> ' + @json(__('settings.export.exporting'));

                // التنزيل لا يعيد تحميل الصفحة، فيبقى الزرّ معطَّلاً للأبد
                setTimeout(function () {
                    submit.disabled = false;
                    submit.innerHTML = '<i class="feather icon-download"></i> ' + @json(__('settings.export.submit'));
                    picker.querySelectorAll('input[type=checkbox]').forEach(function (box) { box.disabled = false; });
                }, 4000);
            });

            refreshSummary();
        })();
        // ---------- تحديد الكل / مجموعة / لا شيء ----------
    (function () {
        'use strict';

        var picker = document.getElementById('picker-export');
        if (!picker) return;

        function fire(box) {
            // الحدث يدوياً: بقية الشاشة تبني بطاقة الجدول عند التغيير،
            // وضبط `checked` برمجياً لا يُطلقه
            box.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function setAll(boxes, checked) {
            boxes.forEach(function (box) {
                if (box.disabled || box.checked === checked) return;
                box.checked = checked;
                fire(box);
            });
        }

        picker.addEventListener('click', function (event) {
            var button = event.target.closest('[data-act]');
            if (!button) return;

            event.preventDefault();
            var act = button.dataset.act;

            if (act === 'all') {
                // الحسّاسة تُترك: تصديرها قرار يُتَّخذ لا يقع بالخطأ
                setAll(Array.prototype.slice.call(
                    picker.querySelectorAll('.bk-picker-item input[type=checkbox]:not([data-sensitive])')
                ), true);
                return;
            }

            if (act === 'none') {
                setAll(Array.prototype.slice.call(
                    picker.querySelectorAll('.bk-picker-item input[type=checkbox]')
                ), false);
                return;
            }

            if (act === 'group') {
                var boxes = [];
                var node = button.closest('.ex-group').nextElementSibling;

                while (node && !node.classList.contains('ex-group')) {
                    var box = node.querySelector('input[type=checkbox]');
                    if (box) boxes.push(box);
                    node = node.nextElementSibling;
                }

                // زرّ المجموعة يعمل عكسياً: إن كانت كلّها محدّدة أُلغيت
                var allOn = boxes.length > 0 && boxes.every(function (b) { return b.checked; });
                setAll(boxes, !allOn);
            }
        });
    })();
</script>
@endsection
