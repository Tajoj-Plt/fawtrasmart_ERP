{{--
    سكربتُ اللوحة المشترك: «من أين تأتي هذه البيانات؟»، الفلترة، الشجرة،
    واللوحةُ الجانبية. يعرّف `window.salesDashboard.openSegment` لتستعمله
    الرسومُ في اللوحة. يقرأ $period ليحمل مفتاحَ الفترة للاستعلام حتى لو
    لم يُرسَل في الرابط (افتراضيُّ الشاشة قد يخالف افتراضيَّ الاستعلام).
--}}
@php
    // مفتاحُ الفترة دائماً: افتراضيُّ هذه الشاشة قد يخالف افتراضيَّ `segment()`
    $drillFilters = array_merge(['period' => $period->key], request()->except(['segment']));
@endphp
<script>
        (function () {
            'use strict';

            // ==================== «من أين تأتي هذه البيانات؟» ====================
            document.querySelectorAll('#sales-dashboard [data-sd-info-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var panel = btn.closest('.sd-card').querySelector('.sd-info-panel');
                    if (panel) { panel.hidden = !panel.hidden; }
                });
            });

            // ==================== الفلترة ====================
            var form = document.getElementById('sdFilterForm');
            var periodInput = document.getElementById('sdPeriod');
            var fromInput = document.getElementById('sdFrom');
            var toInput = document.getElementById('sdTo');
            var customFields = document.querySelectorAll('#sales-dashboard [data-sd-custom]');

            document.querySelectorAll('#sales-dashboard .sd-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var period = tab.getAttribute('data-period');
                    var isCustom = period === 'custom';

                    periodInput.value = period;
                    customFields.forEach(function (el) { el.style.display = isCustom ? '' : 'none'; });

                    if (isCustom) {
                        document.querySelectorAll('#sales-dashboard .sd-tab').forEach(function (t) { t.classList.toggle('active', t === tab); });
                        fromInput.focus();

                        return;
                    }

                    // زرُّ الفترة يلغي التاريخين، وإلا تقدّما عليه في الخادم
                    fromInput.value = '';
                    toInput.value = '';
                    form.submit();
                });
            });

            ['sdBranch', 'sdIncludeChildren', 'sdSort'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.addEventListener('change', function () { form.submit(); }); }
            });

            // ==================== الشجرة: فتح وطيّ الأبناء ====================
            var tree = document.getElementById('sdTree');
            if (tree) {
                var childRows = function (id) { return tree.querySelectorAll('tr[data-tree-parent="' + id + '"]'); };

                // طيُّ عقدةٍ يُخفي نسلَها كلَّه؛ وفتحُها يُظهر أبناءها وحدهم
                var setOpen = function (row, open) {
                    var caret = row.querySelector('[data-sd-tree-toggle]');
                    if (!caret) { return; }
                    caret.setAttribute('aria-expanded', open ? 'true' : 'false');
                    childRows(row.getAttribute('data-tree-id')).forEach(function (child) {
                        child.hidden = !open;
                        if (!open) { setOpen(child, false); }
                    });
                };

                tree.addEventListener('click', function (e) {
                    var caret = e.target.closest('[data-sd-tree-toggle]');
                    if (!caret) { return; }
                    setOpen(caret.closest('tr'), caret.getAttribute('aria-expanded') !== 'true');
                });

                document.querySelectorAll('#sales-dashboard [data-sd-tree-all]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var open = btn.getAttribute('data-sd-tree-all') === '1';
                        tree.querySelectorAll('tr[data-tree-parent=""]').forEach(function (root) {
                            if (!open) { setOpen(root, false); return; }
                            (function openAll(row) {
                                setOpen(row, true);
                                childRows(row.getAttribute('data-tree-id')).forEach(openAll);
                            })(root);
                        });
                    });
                });
            }

            // ==================== «ماذا وراء هذا الرقم؟» ====================
            // كل عنصرٍ يحمل `data-segment` يطلب `sales.dashboard.segment` بفلاتر اللوحة
            var dashboardRoot = document.getElementById('sales-dashboard');
            var segmentUrl = @json(route('sales.dashboard.segment'));
            var dashboardFilters = @json($drillFilters);
            var drillText = @json(__('sales_dashboard.drill'));
            var drillDecimals = @json(\App\Support\CurrencySettings::decimals());
            var drawer = document.getElementById('sdDrawer');
            var backdrop = document.getElementById('sdDrawerBackdrop');
            var drawerBody = document.getElementById('sdDrawerBody');
            var drawerSearch = document.getElementById('sdDrawerSearch');
            var drawerCount = document.getElementById('sdDrawerCount');
            var drawerTitle = document.getElementById('sdDrawerTitle');
            var drawerHint = document.getElementById('sdDrawerHint');
            var PAGE = 200;
            var drill = { data: null, filtered: [], shown: PAGE, request: 0, trigger: null };

            var tr = function (key, replace) {
                var text = String(drillText[key] || key);
                Object.keys(replace || {}).forEach(function (k) { text = text.split(':' + k).join(replace[k]); });
                return text;
            };
            var esc = function (value) {
                return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            };
            var drillMoney = function (v) {
                return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: drillDecimals, maximumFractionDigits: drillDecimals });
            };
            var tones = { 'sale': '--sd-primary', 'return': '--sd-danger', 'credit_note': '--sd-info', 'collection': '--sd-success', 'cancellation': '--sd-warning', 'cancelled_collection': '--sd-muted', 'refund': '--sd-danger', 'adjustment': '--sd-muted' };

            var cell = function (col, row) {
                var v = row[col.key];
                var linked = function (url) { return url ? '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(v) + '</a>' : esc(v || '—'); };
                switch (col.type) {
                    case 'doc': return linked(row.url);
                    case 'client': return linked(row.client_url);
                    case 'kind':
                        var tone = 'var(' + (tones[row.kind || row.tone] || '--sd-muted') + ')';
                        return '<span class="sd-badge" style="background: color-mix(in srgb, ' + tone + ' 14%, transparent); color: ' + tone + ';">' + esc(v) + '</span>';
                    // شارةُ حالة العميل ووضعِه — لونُ كلٍّ في `<المفتاح>_color`، كلوحة العملاء
                    case 'status':
                        var hue = esc(row[col.key + '_color'] || '#999999');
                        return '<span class="sd-badge" style="background: color-mix(in srgb, ' + hue + ' 16%, transparent); color: ' + hue + ';">' + esc(v || '—') + '</span>';
                    case 'visit':
                        return (v === null || v === undefined)
                            ? '<span style="color: var(--sd-danger); font-weight: 600;">' + esc(tr('never_visited')) + '</span>'
                            : '<span class="sd-num">' + esc(row.last_visit) + '</span> <small class="text-muted">(' + v + ' ' + esc(tr('day')) + ')</small>';
                    case 'money': return '<span class="sd-num">' + drillMoney(v) + '</span>';
                    case 'number': return '<span class="sd-num">' + Number(v || 0).toLocaleString('en-US') + '</span>';
                    case 'date': return v ? '<span class="sd-num">' + esc(v) + '</span>' : '—';
                    case 'longtext': return v ? '<div class="sd-long">' + esc(v) + '</div>' : '—';
                    default: return (v === null || v === undefined || v === '') ? '—' : esc(v);
                }
            };

            var renderRows = function () {
                var data = drill.data;
                if (!data) { return; }
                // الصفُّ عميلٌ في كل القوائم إلّا حركةَ عميلٍ بعينه ومصروفاتِ موظّف
                var unit = data.unit === 'clients' ? 'rows_clients' : 'rows';
                drawerCount.textContent = drill.filtered.length === data.rows.length
                    ? tr(unit, { count: data.rows.length.toLocaleString('en-US') })
                    : tr(unit + '_of', { shown: drill.filtered.length.toLocaleString('en-US'), total: data.rows.length.toLocaleString('en-US') });

                if (!drill.filtered.length) {
                    drawerBody.innerHTML = '<div class="sd-empty"><i class="feather icon-inbox"></i>' + esc(data.rows.length ? tr('no_results') : tr('empty')) + '</div>';
                    return;
                }

                var head = '<tr><th>#</th>' + data.columns.map(function (c) { return '<th>' + esc(c.label) + '</th>'; }).join('') + '</tr>';
                var body = drill.filtered.slice(0, drill.shown).map(function (row, i) {
                    return '<tr><td class="sd-num">' + (i + 1) + '</td>' + data.columns.map(function (c) { return '<td>' + cell(c, row) + '</td>'; }).join('') + '</tr>';
                }).join('');

                // المجموعُ على كل ما طابق البحث لا على الصفحة الظاهرة — فيطابق الرقمَ المنقور
                var foot = '';
                if (data.columns.some(function (c) { return c.sum; })) {
                    foot = '<tfoot><tr><td></td>' + data.columns.map(function (c, i) {
                        if (!c.sum) { return '<td>' + (i === 0 ? esc(tr('total')) : '') + '</td>'; }
                        var total = drill.filtered.reduce(function (a, r) { return a + (Number(r[c.key]) || 0); }, 0);
                        return '<td><span class="sd-num">' + (c.type === 'money' ? drillMoney(total) : total.toLocaleString('en-US', { maximumFractionDigits: 2 })) + '</span></td>';
                    }).join('') + '</tr></tfoot>';
                }

                var more = drill.filtered.length > drill.shown
                    ? '<button type="button" class="sd-reset sd-drawer-more" id="sdDrawerMore">' + esc(tr('more', { count: (drill.filtered.length - drill.shown).toLocaleString('en-US') })) + '</button>'
                    : '';

                drawerBody.innerHTML = '<div class="table-responsive"><table class="sd-table"><thead>' + head + '</thead><tbody>' + body + '</tbody>' + foot + '</table></div>' + more;
            };

            var applySearch = function () {
                var q = drawerSearch.value.trim().toLowerCase();
                drill.shown = PAGE;
                drill.filtered = !q ? drill.data.rows.slice() : drill.data.rows.filter(function (r) {
                    return [r.code, r.client, r.name, r.phone, r.employee, r.description, r.kind_label, r.state, r.status, r.stage].some(function (x) {
                        return String(x || '').toLowerCase().indexOf(q) !== -1;
                    });
                });
                renderRows();
            };

            var closeDrawer = function () {
                drawer.hidden = true;
                backdrop.hidden = true;
                document.body.style.overflow = '';
                drill.request++;
                if (drill.trigger && document.contains(drill.trigger)) { drill.trigger.focus(); }
            };

            var openSegment = function (segment, trigger, extra) {
                drill.trigger = trigger || null;
                drill.data = null;
                var requestId = ++drill.request;

                drawerTitle.textContent = tr('loading');
                drawerHint.textContent = '';
                drawerCount.textContent = '';
                drawerSearch.value = '';
                drawerBody.innerHTML = '<div class="sd-empty"><i class="feather icon-loader"></i>' + esc(tr('fetching')) + '</div>';
                drawer.hidden = false;
                backdrop.hidden = false;
                document.body.style.overflow = 'hidden';

                var params = new URLSearchParams();
                Object.keys(dashboardFilters).forEach(function (k) {
                    var v = dashboardFilters[k];
                    if (Array.isArray(v)) { v.forEach(function (x) { params.append(k + '[]', x); }); }
                    else if (v !== null && v !== '') { params.append(k, v); }
                });
                params.set('segment', segment);
                if (trigger && trigger.getAttribute('data-segment-params')) {
                    try { extra = Object.assign({}, JSON.parse(trigger.getAttribute('data-segment-params')), extra || {}); }
                    catch (e) { /* معاملاتٌ تالفة لا تمنع القائمة */ }
                }
                Object.keys(extra || {}).forEach(function (k) { params.set(k, extra[k]); });

                fetch(segmentUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) { throw new Error(r.status === 403 ? tr('forbidden') : tr('failed_status', { status: r.status })); }
                        return r.json();
                    })
                    .then(function (data) {
                        if (requestId !== drill.request) { return; }
                        drill.data = data;
                        drawerTitle.textContent = data.title;
                        drawerHint.textContent = data.hint || '';
                        drawerSearch.placeholder = tr(data.unit === 'clients' ? 'search_clients' : 'search');
                        drill.filtered = data.rows.slice();
                        drill.shown = PAGE;
                        renderRows();
                        drawerSearch.focus();
                    })
                    .catch(function (err) {
                        if (requestId !== drill.request) { return; }
                        drawerTitle.textContent = tr('failed');
                        drawerBody.innerHTML = '<div class="sd-empty"><i class="feather icon-alert-triangle"></i>' + esc(err.message) + '</div>';
                    });
            };

            dashboardRoot.addEventListener('click', function (e) {
                if (e.target.closest('[data-sd-drawer-close]')) { closeDrawer(); return; }
                if (e.target.closest('#sdDrawerMore')) { drill.shown += PAGE; renderRows(); return; }
                if (e.target.closest('#sdDrawer')) { return; }

                var el = e.target.closest('[data-segment]');
                var control = e.target.closest('a[href], button');
                // رابطُ الاسم في الشجرة وأزرارُها تعمل كما هي
                if (!el || (control && el.contains(control))) { return; }
                e.preventDefault();
                openSegment(el.getAttribute('data-segment'), el);
            });

            dashboardRoot.addEventListener('keydown', function (e) {
                if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-segment]')) {
                    e.preventDefault();
                    openSegment(e.target.getAttribute('data-segment'), e.target);
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !drawer.hidden) { closeDrawer(); }
            });

            drawerSearch.addEventListener('input', function () { if (drill.data) { applySearch(); } });

            document.getElementById('sdDrawerCsv').addEventListener('click', function () {
                if (!drill.data || !drill.filtered.length) { return; }
                var cols = drill.data.columns;
                var quote = function (v) { return '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"'; };
                var lines = [cols.map(function (c) { return quote(c.label); }).join(',')];
                drill.filtered.forEach(function (row) {
                    lines.push(cols.map(function (c) { return quote(row[c.key]); }).join(','));
                });
                var blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (drill.data.title || 'sales').replace(/[\\/:*?"<>|]/g, '-') + '.csv';
                document.body.appendChild(a);
                a.click();
                setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
            });

            // الرسومُ في اللوحة تفتح القوائم من خارج هذا النطاق
            window.salesDashboard = { openSegment: openSegment };
        })();
    </script>
