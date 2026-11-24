@php
    /**
     * جدول مفاتيح الترجمة — يُستعمل في شاشة التفاصيل وفي نموذجي الإضافة والتعديل.
     *
     * @var array $groups        مجموعات الترجمة (اسم الملف + عدد المفاتيح)
     * @var bool  $standalone    هل يملك نموذجه وزر حفظه؟ (شاشة التفاصيل)
     * @var \App\Models\SystemLanguage|null $language
     */
    $groups = $groups ?? [];
    $language = $language ?? null;
    $standalone = $standalone ?? false;
    $canEditTranslations = canLanguages($language ? 'edit' : 'create');

    $editorConfig = [
        'canEdit' => $canEditTranslations,
        'standalone' => $standalone,
        'fetchUrl' => $language ? route('languages.translations.group', $language->id) : null,
        'pageSize' => 300,
        'groups' => array_map(fn ($group) => [
            'name' => $group['name'],
            'label' => $group['label'],
            'type' => $group['type'],
            'relative' => $group['relative'],
            'count' => count($group['entries']),
            'error' => $group['error'],
        ], $groups),
        'labels' => [
            'key' => __('languages.translations.key'),
            'value' => __('languages.translations.value'),
            'search' => __('languages.translations.search'),
            'addRow' => __('languages.translations.add_row'),
            'remove' => __('languages.delete'),
            'loading' => __('languages.translations.loading'),
            'empty' => __('languages.translations.empty'),
            'noGroups' => __('languages.translations.no_groups'),
            'showing' => __('languages.translations.showing'),
            'showMore' => __('languages.translations.show_more'),
            'unreadable' => __('languages.translations.unreadable'),
            'loadFailed' => __('languages.translations.load_failed'),
            'duplicate' => __('languages.translations.duplicate'),
            'edited' => __('languages.translations.edited'),
            'keyPlaceholder' => __('languages.translations.key_placeholder'),
            'confirmDeleteGroup' => __('languages.translations.confirm_delete_group'),
            'groupExists' => __('languages.translations.group_exists'),
            'jsonExists' => __('languages.translations.json_exists'),
        ],
    ];
@endphp

@once
    <style>
        .tr-editor .tr-tabs { gap: .35rem; flex-wrap: wrap; }
        .tr-editor .tr-tabs .nav-link { padding: .35rem .8rem; font-size: .82rem; cursor: pointer; }
        .tr-editor .tr-tabs .nav-link.tr-dirty::after { content: '•'; margin-inline-start: .35rem; color: #ff9f43; font-size: 1.1rem; line-height: 0; }
        .tr-editor .tr-table td { padding: .35rem .5rem; vertical-align: middle; }
        .tr-editor .tr-table th { padding: .5rem; font-size: .78rem; }
        .tr-editor .tr-key { font-family: monospace; direction: ltr; text-align: left; font-size: .78rem; }
        .tr-editor .tr-value { min-height: 2.1rem; resize: vertical; font-size: .82rem; }
        .tr-editor .tr-scroll { max-height: 60vh; overflow: auto; }
        .tr-editor .tr-empty { padding: 2rem 1rem; text-align: center; color: #b9b9c3; }
    </style>
@endonce

<div class="tr-editor" data-tr-editor>
    <script type="application/json" data-tr-config>@json($editorConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)</script>

    <input type="hidden" name="translations_payload" data-tr-payload value="">

    <div class="d-flex flex-wrap align-items-center mb-1" style="gap:.5rem;">
        <div class="position-relative" style="flex:1 1 220px; max-width:320px;">
            <input type="search" class="form-control form-control-sm" data-tr-search
                   placeholder="{{ __('languages.translations.search') }}" autocomplete="off">
        </div>
        <span class="badge badge-light-secondary" data-tr-count>—</span>

        @if ($canEditTranslations)
            <div class="d-flex ml-auto" style="gap:.35rem;">
                <input type="text" class="form-control form-control-sm" data-tr-new-group
                       placeholder="{{ __('languages.translations.new_group_placeholder') }}" maxlength="50" style="max-width:190px;">
                <button type="button" class="btn btn-sm btn-outline-primary" data-tr-add-group>
                    <i class="feather icon-folder-plus"></i> {{ __('languages.translations.add_group') }}
                </button>
            </div>
        @endif
    </div>

    <ul class="nav nav-pills tr-tabs mb-1" data-tr-tabs></ul>

    <div data-tr-panel></div>

    <noscript>
        <div class="alert alert-warning py-1 px-2 mb-0">{{ __('languages.translations.needs_js') }}</div>
    </noscript>

    @if ($canEditTranslations)
        <small class="text-muted d-block mt-1">
            <i class="fa fa-info-circle mr-1"></i> {{ __('languages.translations.hint') }}
        </small>
    @endif
</div>

@once
    @verbatim
        <script>
            (function () {
                document.querySelectorAll('[data-tr-editor]').forEach(function (root) {
                    if (root.dataset.trBooted) return;
                    root.dataset.trBooted = '1';

                    var config = JSON.parse(root.querySelector('[data-tr-config]').textContent);
                    var L = config.labels;
                    var tabsEl = root.querySelector('[data-tr-tabs]');
                    var panelEl = root.querySelector('[data-tr-panel]');
                    var searchEl = root.querySelector('[data-tr-search]');
                    var countEl = root.querySelector('[data-tr-count]');
                    var payloadEl = root.querySelector('[data-tr-payload]');
                    var form = root.closest('form');

                    // النموذج في الذاكرة هو المرجع، والجدول لا يعرض إلا شريحة منه:
                    // ملف بآلاف المفاتيح يشلّ المتصفح لو رُسم كاملاً دفعة واحدة.
                    var groups = config.groups.map(function (g) {
                        return {
                            name: g.name, label: g.label, type: g.type, relative: g.relative,
                            count: g.count, error: g.error,
                            loaded: false, dirty: false, rows: null, seq: 0, limit: config.pageSize
                        };
                    });
                    var active = groups.length ? 0 : -1;

                    function esc(value) {
                        return String(value === null || value === undefined ? '' : value)
                            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;');
                    }

                    function renderTabs() {
                        tabsEl.innerHTML = '';
                        groups.forEach(function (group, index) {
                            var li = document.createElement('li');
                            li.className = 'nav-item';
                            var a = document.createElement('a');
                            a.className = 'nav-link' + (index === active ? ' active' : '') + (group.dirty ? ' tr-dirty' : '');
                            a.innerHTML = esc(group.label) +
                                ' <span class="badge badge-light-primary">' + (group.rows ? group.rows.length : group.count) + '</span>';
                            a.addEventListener('click', function () {
                                syncPanel();
                                active = index;
                                renderTabs();
                                renderPanel();
                            });
                            li.appendChild(a);
                            tabsEl.appendChild(li);
                        });
                    }

                    function currentGroup() {
                        return active >= 0 ? groups[active] : null;
                    }

                    function matches(row, term) {
                        if (!term) return true;
                        return (row.key + ' ' + row.value).toLowerCase().indexOf(term) !== -1;
                    }

                    function renderPanel() {
                        var group = currentGroup();

                        if (!group) {
                            panelEl.innerHTML = '<div class="tr-empty">' + esc(L.noGroups) + '</div>';
                            countEl.textContent = '—';
                            return;
                        }

                        if (group.error) {
                            panelEl.innerHTML = '<div class="alert alert-danger py-1 px-2 mb-0">' +
                                '<strong>' + esc(L.unreadable) + '</strong><br><small>' + esc(group.error) + '</small></div>';
                            countEl.textContent = '—';
                            return;
                        }

                        if (!group.loaded) {
                            panelEl.innerHTML = '<div class="tr-empty">' + esc(L.loading) + '</div>';
                            load(group).then(renderPanel).catch(function (e) {
                                panelEl.innerHTML = '<div class="alert alert-danger py-1 px-2 mb-0">' +
                                    esc(L.loadFailed) + ' ' + esc(e && e.message ? e.message : '') + '</div>';
                            });
                            return;
                        }

                        var term = (searchEl.value || '').trim().toLowerCase();
                        var visible = group.rows.filter(function (row) { return matches(row, term); });
                        var shown = visible.slice(0, group.limit);

                        var html = '<div class="card border mb-0"><div class="card-body p-0">' +
                            '<div class="tr-scroll"><table class="table table-sm table-hover tr-table mb-0">' +
                            '<thead class="thead-light"><tr>' +
                            '<th style="width:38%">' + esc(L.key) + '</th>' +
                            '<th>' + esc(L.value) + '</th>' +
                            (config.canEdit ? '<th style="width:46px"></th>' : '') +
                            '</tr></thead><tbody>';

                        if (!shown.length) {
                            html += '<tr><td colspan="3" class="tr-empty">' + esc(L.empty) + '</td></tr>';
                        }

                        shown.forEach(function (row) {
                            html += '<tr data-id="' + row.id + '">' +
                                '<td><input type="text" class="form-control form-control-sm tr-key" data-field="key"' +
                                ' value="' + esc(row.key) + '" placeholder="' + esc(L.keyPlaceholder) + '"' +
                                (config.canEdit ? '' : ' readonly') + '></td>' +
                                '<td><textarea rows="1" class="form-control form-control-sm tr-value" data-field="value"' +
                                (config.canEdit ? '' : ' readonly') + '>' + esc(row.value) + '</textarea></td>' +
                                (config.canEdit ? '<td class="text-center"><button type="button" class="btn btn-sm btn-flat-danger"' +
                                    ' data-tr-remove title="' + esc(L.remove) + '">&times;</button></td>' : '') +
                                '</tr>';
                        });

                        html += '</tbody></table></div>';

                        if (visible.length > shown.length) {
                            html += '<div class="p-1 text-center border-top">' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary" data-tr-more>' +
                                esc(L.showMore) + '</button></div>';
                        }

                        html += '</div>';

                        if (config.canEdit) {
                            html += '<div class="d-flex flex-wrap p-1" style="gap:.5rem;">' +
                                '<button type="button" class="btn btn-sm btn-outline-primary" data-tr-add-row>' +
                                '<i class="feather icon-plus"></i> ' + esc(L.addRow) + '</button>';

                            if (config.standalone && group.relative) {
                                html += '<button type="button" class="btn btn-sm btn-flat-danger ml-auto" data-tr-delete-group>' +
                                    '<i class="feather icon-trash-2"></i> ' + esc(group.label) + '</button>';
                            }

                            html += '</div>';
                        }

                        panelEl.innerHTML = html;
                        countEl.textContent = L.showing
                            .replace(':shown', shown.length)
                            .replace(':total', group.rows.length);

                        bindPanel(group);
                    }

                    function bindPanel(group) {
                        panelEl.querySelectorAll('[data-field]').forEach(function (input) {
                            input.addEventListener('input', function () {
                                markDirty(group);
                            });
                        });

                        panelEl.querySelectorAll('[data-tr-remove]').forEach(function (button) {
                            button.addEventListener('click', function () {
                                var tr = button.closest('tr');
                                var id = Number(tr.getAttribute('data-id'));
                                syncPanel();
                                group.rows = group.rows.filter(function (row) { return row.id !== id; });
                                markDirty(group);
                                renderTabs();
                                renderPanel();
                            });
                        });

                        var more = panelEl.querySelector('[data-tr-more]');
                        if (more) {
                            more.addEventListener('click', function () {
                                syncPanel();
                                group.limit += config.pageSize;
                                renderPanel();
                            });
                        }

                        var addRow = panelEl.querySelector('[data-tr-add-row]');
                        if (addRow) {
                            addRow.addEventListener('click', function () {
                                syncPanel();
                                group.rows.unshift({ id: ++group.seq, key: '', value: '' });
                                markDirty(group);
                                searchEl.value = '';
                                renderTabs();
                                renderPanel();
                                var first = panelEl.querySelector('.tr-key');
                                if (first) first.focus();
                            });
                        }

                        var deleteGroup = panelEl.querySelector('[data-tr-delete-group]');
                        if (deleteGroup) {
                            deleteGroup.addEventListener('click', function () {
                                var target = document.querySelector('[data-tr-delete-form]');
                                if (!target || !confirm(L.confirmDeleteGroup.replace(':file', group.label))) return;
                                target.querySelector('[name="group"]').value = group.name;
                                target.querySelector('[name="type"]').value = group.type;
                                target.submit();
                            });
                        }
                    }

                    function markDirty(group) {
                        if (!group.dirty) {
                            group.dirty = true;
                            renderTabs();
                        }
                    }

                    /** نسخ ما في الجدول إلى النموذج قبل أي إعادة رسم حتى لا تضيع الكتابة */
                    function syncPanel() {
                        var group = currentGroup();
                        if (!group || !group.loaded || !group.rows) return;

                        var index = {};
                        group.rows.forEach(function (row) { index[row.id] = row; });

                        panelEl.querySelectorAll('tr[data-id]').forEach(function (tr) {
                            var row = index[Number(tr.getAttribute('data-id'))];
                            if (!row) return;
                            var key = tr.querySelector('[data-field="key"]');
                            var value = tr.querySelector('[data-field="value"]');
                            if (key) row.key = key.value;
                            if (value) row.value = value.value;
                        });
                    }

                    function load(group) {
                        if (group.loaded) return Promise.resolve(group);

                        // مجموعة جديدة لم تُكتب بعد: لا شيء يُجلب من الخادم
                        if (!config.fetchUrl || group.isNew) {
                            group.rows = group.rows || [];
                            group.loaded = true;
                            return Promise.resolve(group);
                        }

                        var url = config.fetchUrl + (config.fetchUrl.indexOf('?') === -1 ? '?' : '&') +
                            'group=' + encodeURIComponent(group.name) + '&type=' + encodeURIComponent(group.type);

                        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                            .then(function (response) {
                                if (!response.ok) throw new Error('HTTP ' + response.status);
                                return response.json();
                            })
                            .then(function (data) {
                                group.error = data.error || null;
                                group.rows = Object.keys(data.entries || {}).map(function (key) {
                                    return { id: ++group.seq, key: key, value: data.entries[key] };
                                });
                                group.count = group.rows.length;
                                group.loaded = true;
                                return group;
                            });
                    }

                    /** الحمولة نصّ JSON واحد: مئات الحقول المنفصلة تتجاوز max_input_vars فتصل ناقصة */
                    function serialize() {
                        syncPanel();

                        var payload = { groups: [] };

                        groups.forEach(function (group) {
                            if (!group.dirty || group.error || !group.rows) return;

                            var entries = {};
                            group.rows.forEach(function (row) {
                                var key = (row.key || '').trim();
                                if (key === '') return;
                                entries[key] = row.value;
                            });

                            payload.groups.push({ name: group.name, type: group.type, entries: entries });
                        });

                        payloadEl.value = payload.groups.length ? JSON.stringify(payload) : '';

                        return payload.groups.length;
                    }

                    var addGroupButton = root.querySelector('[data-tr-add-group]');
                    if (addGroupButton) {
                        addGroupButton.addEventListener('click', function () {
                            var input = root.querySelector('[data-tr-new-group]');
                            var raw = (input.value || '').trim().toLowerCase();
                            var name = raw.replace(/[^a-z0-9_-]/g, '');

                            if (name === '') return input.focus();

                            var exists = groups.some(function (group) { return group.name === name; });
                            if (exists) {
                                alert(L.groupExists.replace(':name', name));
                                return;
                            }

                            syncPanel();
                            groups.push({
                                name: name, label: name + '.php', type: 'php', relative: null,
                                count: 0, error: null, loaded: true, dirty: false, isNew: true,
                                rows: [{ id: 1, key: '', value: '' }], seq: 1, limit: config.pageSize
                            });
                            input.value = '';
                            active = groups.length - 1;
                            markDirty(groups[active]);
                            renderTabs();
                            renderPanel();
                        });
                    }

                    searchEl.addEventListener('input', function () {
                        syncPanel();
                        var group = currentGroup();
                        if (group) group.limit = config.pageSize;
                        renderPanel();
                    });

                    if (form) {
                        form.addEventListener('submit', function () {
                            serialize();
                        });
                    }

                    renderTabs();
                    renderPanel();
                });
            })();
        </script>
    @endverbatim
@endonce
