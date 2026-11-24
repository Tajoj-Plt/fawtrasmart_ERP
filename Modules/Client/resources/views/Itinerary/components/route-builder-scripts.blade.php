<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    window.ROUTE_BUILDER = {
        mode: @json($mode),
        saveUrl: @json($mode === 'edit' ? route('itinerary.update', $employee->id) : route('itinerary.store')),
        saveMethod: @json($mode === 'edit' ? 'PUT' : 'POST'),
        listUrl: @json(route('itinerary.list')),
        clientsUrl: @json(route('itinerary.route-clients')),
        weekUrl: @json(route('itinerary.week-itinerary', ['employeeId' => '__EMP__'])),
        showUrl: @json(route('clients.show', ['id' => '__ID__'])),
        csrf: @json(csrf_token()),
        nodes: @json($nodes),
        days: @json($days),
        currentWeek: @json($currentWeek),
        selectedDay: @json($selectedDay),
        orderStrategy: @json($orderStrategy),
        maxPerDay: @json($maxPerDay),
        canReorder: @json($canReorder),
        mapsConfigured: @json($mapsKey !== ''),
    };
</script>
<script>
    (function () {
        'use strict';

        const cfg = window.ROUTE_BUILDER;
        const $ = (id) => document.getElementById(id);
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const LEVELS = ['region', 'branch', 'group', 'outlet'];
        const DAYS = Object.keys(cfg.days);
        const dayName = (day) => (cfg.days[day] && cfg.days[day].name) || day;
        const STATUS_LABEL = { started: 'بدأت', completed: 'مكتملة', missed: 'فائتة', cancelled: 'ملغاة' };
        const nodeById = new Map(cfg.nodes.map((node) => [Number(node.id), node]));
        const emptyWeek = () => Object.fromEntries(DAYS.map((day) => [day, []]));

        const state = {
            days: emptyWeek(),
            currentDay: DAYS.includes(cfg.selectedDay) ? cfg.selectedDay : DAYS[0],
            lockedDays: new Set(),
            clients: new Map(),
            listed: [],
            assignedIds: new Set(),
            assignedLoaded: false,
            dirty: false,
        };

        const dayStops = () => state.days[state.currentDay];
        const isDayLocked = (day = state.currentDay) => state.lockedDays.has(day);
        const located = (client) => !!client && client.lat !== null && client.lat !== undefined && client.lng !== null && client.lng !== undefined;

        // ═══════════════ أدوات ═══════════════

        function haversineKm(a, b) {
            const rad = (deg) => Number(deg) * Math.PI / 180;
            const dLat = rad(b.lat) - rad(a.lat);
            const dLng = rad(b.lng) - rad(a.lng);
            const x = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
            return 12742 * Math.asin(Math.sqrt(x));
        }

        /** شارةُ حالة العميل بلونها — اللونُ من «حالات العملاء»، ويُقبل بصيغة hex وحدها */
        function statusBadge(status) {
            if (!status || !status.name) return '<span class="rb-muted">—</span>';
            const color = /^#[0-9a-f]{3,8}$/i.test(String(status.color || '')) ? status.color : '';
            return `<span class="rb-status"${color ? ` style="--rb-status:${color}"` : ''}>${esc(status.name)}</span>`;
        }

        /** «أضافه المندوب» / «أضافه: فلان» — صفوفُ الخطّة الأسبوعية بلا شارة */
        function sourceBadge(source) {
            if (!source || !source.label) return '';
            return ` <span class="rb-src rb-src--${source.tone === 'self' ? 'self' : 'other'}" title="أُضيف من «خط سيري اليومي»"><i class="fas fa-user-plus"></i> ${esc(source.label)}</span>`;
        }

        function notify(icon, text) {
            if (window.Swal) return Swal.fire({ icon, text, confirmButtonText: 'حسناً' });
            alert(text);
            return Promise.resolve();
        }

        async function confirmAsk(text) {
            if (!window.Swal) return window.confirm(text);
            const answer = await Swal.fire({ icon: 'question', text, showCancelButton: true, confirmButtonText: 'متابعة', cancelButtonText: 'إلغاء' });
            return answer.isConfirmed;
        }

        async function getJson(url, params) {
            const target = new URL(url, window.location.origin);
            Object.entries(params || {}).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') target.searchParams.set(key, value);
            });
            const response = await fetch(target, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.message || `HTTP ${response.status}`);
            return body;
        }

        const employeeSelect = $('rb-employee');
        const yearSelect = $('rb-year');
        const weekSelect = $('rb-week');
        const currentEmployee = () => Number(employeeSelect.value) || null;
        const weekUrl = (employeeId) => cfg.weekUrl.replace('__EMP__', employeeId);

        // ═══════════════ الترتيب — «ترتيب الزيارات في خط السير» (نظيرُ ItineraryWeekService::orderStops) ═══════════════

        function orderDay(day) {
            const stops = state.days[day];
            const client = (stop) => state.clients.get(stop.client_id) || {};

            if (cfg.orderStrategy === 'last_visit') {
                // الأقدمُ زيارةً أولاً، ومن لم يُزَر قطّ قبل الجميع
                stops.sort((a, b) => String(client(a).last_visit || '').localeCompare(String(client(b).last_visit || '')));
            } else if (cfg.orderStrategy === 'nearest') {
                const withCoords = stops.filter((stop) => located(client(stop)));
                const without = stops.filter((stop) => !located(client(stop)));
                if (withCoords.length > 1) {
                    const ordered = [withCoords.shift()];
                    while (withCoords.length) {
                        const current = client(ordered[ordered.length - 1]);
                        let nearest = 0;
                        withCoords.forEach((stop, index) => {
                            if (haversineKm(current, client(stop)) < haversineKm(current, client(withCoords[nearest]))) nearest = index;
                        });
                        ordered.push(withCoords.splice(nearest, 1)[0]);
                    }
                    state.days[day] = [...ordered, ...without];
                    return;
                }
                state.days[day] = [...withCoords, ...without];
            }
        }

        // ═══════════════ الأسابيع (تبدأ السبت — نظيرُ حساب الخادم) ═══════════════

        function weekStart(year, week) {
            const offset = (new Date(year, 0, 1).getDay() + 1) % 7;
            return new Date(year, 0, 1 - offset + (week - 1) * 7);
        }

        function weeksInYear(year) {
            let week = 1;
            while (weekStart(year, week + 1) <= new Date(year, 11, 31)) week++;
            return week;
        }

        const fmtDate = (date) => `${date.getDate()}/${date.getMonth() + 1}`;
        const addDays = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

        function fillWeeks(selected) {
            const year = Number(yearSelect.value);
            const count = weeksInYear(year);
            const chosen = Math.min(Math.max(1, Number(selected) || 1), count);
            weekSelect.innerHTML = Array.from({ length: count }, (_, i) => {
                const week = i + 1;
                const start = weekStart(year, week);
                return `<option value="${week}" ${week === chosen ? 'selected' : ''}>الأسبوع ${week} (${fmtDate(start)} - ${fmtDate(addDays(start, 6))})</option>`;
            }).join('');
            weekSelect.dataset.prev = weekSelect.value;
            syncWeekRange();
        }

        function syncWeekRange() {
            const start = weekStart(Number(yearSelect.value), Number(weekSelect.value));
            $('rb-week-range').textContent = `${fmtDate(start)} — ${fmtDate(addDays(start, 6))}`;
        }

        const dayDate = (day) => addDays(weekStart(Number(yearSelect.value), Number(weekSelect.value)), DAYS.indexOf(day));

        // ═══════════════ المنطقة ← الفرع ← المجموعة ← المنفذ ═══════════════

        const levelSelects = LEVELS.map((level) => document.querySelector(`#route-builder .rb-level[data-level="${level}"]`));

        function isUnder(node, ancestorId) {
            let cursor = node;
            for (let guard = 0; cursor && guard < 30; guard++) {
                if (Number(cursor.parent_id) === ancestorId) return true;
                cursor = nodeById.get(Number(cursor.parent_id));
            }
            return false;
        }

        function fillLevel(index) {
            const select = levelSelects[index];
            const allLabel = select.options[0].textContent;
            let ancestor = null;
            for (let i = index - 1; i >= 0; i--) {
                if (levelSelects[i].value) { ancestor = Number(levelSelects[i].value); break; }
            }
            const options = cfg.nodes.filter((node) => node.display_type === LEVELS[index] && (ancestor === null || isUnder(node, ancestor)));
            select.innerHTML = `<option value="">${esc(allLabel)}</option>`
                + options.map((node) => `<option value="${node.id}" title="${esc(node.tree_label)}">${esc(node.name)}</option>`).join('');
            select.disabled = options.length === 0;
        }

        function effectiveNode() {
            for (let i = levelSelects.length - 1; i >= 0; i--) {
                if (levelSelects[i].value) return Number(levelSelects[i].value);
            }
            return null;
        }

        levelSelects.forEach((select, index) => select.addEventListener('change', () => {
            for (let i = index + 1; i < levelSelects.length; i++) fillLevel(i);
            loadClients();
        }));

        // ═══════════════ قائمة العملاء ═══════════════

        let listTimer = null;
        let listSeq = 0;

        function loadClients() {
            clearTimeout(listTimer);
            listTimer = setTimeout(fetchClients, 250);
        }

        function listMessage(text) {
            state.listed = [];
            $('rb-client-list').innerHTML = `<p class="rb-empty">${text}</p>`;
            $('rb-list-note').hidden = true;
            syncCheckAll();
        }

        async function fetchClients() {
            const seq = ++listSeq;
            const assigned = $('rb-assigned').value;
            const employeeId = currentEmployee();

            if (assigned === 'route' && !employeeId) return listMessage('اختر المندوب أولاً لعرض عملائه المُسنَدين.');
            if (assigned !== 'route' && !effectiveNode()) return listMessage('اختر المنطقة أو الفرع لعرض عملائه، أو اختر «المُسنَدون للمندوب».');

            $('rb-client-list').innerHTML = '<p class="rb-empty"><i class="fas fa-spinner fa-spin"></i> جارٍ التحميل…</p>';

            try {
                const body = await getJson(cfg.clientsUrl, { node_id: effectiveNode(), employee_id: employeeId, assigned, q: $('rb-search').value.trim() });
                if (seq !== listSeq) return;
                if (body.needs_employee) return listMessage('اختر المندوب أولاً — إعدادات خطوط السير تقصر الخط على عملائه المُسنَدين.');
                if (body.needs_node) return listMessage('اختر المنطقة أو الفرع لعرض عملائه.');

                state.listed = body.clients || [];
                state.listed.forEach((client) => state.clients.set(Number(client.id), client));

                const note = $('rb-list-note');
                note.hidden = !(body.total > state.listed.length);
                note.textContent = `يُعرض ${state.listed.length} من ${body.total} عميل — ضيّق الاختيار أو ابحث بالاسم.`;
                renderList();
            } catch (error) {
                if (seq === listSeq) listMessage(`تعذّر جلب العملاء: ${esc(error.message)}`);
            }
        }

        function renderList() {
            if (!state.listed.length) return listMessage('لا عملاء في هذه الجهة.');

            const inDay = new Set(dayStops().map((stop) => stop.client_id));
            $('rb-client-list').innerHTML = state.listed.map((client) => {
                const id = Number(client.id);
                const added = inDay.has(id);
                const otherDays = DAYS.filter((day) => day !== state.currentDay && state.days[day].some((stop) => stop.client_id === id)).map(dayName);
                return `<label class="rb-client${added ? ' is-added' : ''}">
                    <input type="checkbox" value="${id}" ${added ? 'checked disabled' : ''}>
                    <span class="rb-client__body">
                        <span class="rb-client__name">${esc(client.name)} ${client.status ? statusBadge(client.status) : ''}</span>
                        <span class="rb-client__code">${esc(client.code || '—')}${added ? ' · <span class="rb-badge">مضاف</span>' : ''}${otherDays.length ? ` · <span class="rb-badge rb-badge--muted">${esc(otherDays.join('، '))}</span>` : ''}${located(client) ? '' : ' · <span class="rb-badge rb-badge--warn">بلا موقع</span>'}</span>
                    </span>
                    <i class="fas fa-store rb-client__icon"></i>
                </label>`;
            }).join('');
            syncCheckAll();
        }

        const listBoxes = () => [...$('rb-client-list').querySelectorAll('input[type="checkbox"]:not(:disabled)')];

        function syncCheckAll() {
            const boxes = listBoxes();
            const all = $('rb-check-all');
            all.checked = boxes.length > 0 && boxes.every((box) => box.checked);
            all.disabled = boxes.length === 0;
        }

        $('rb-client-list').addEventListener('change', syncCheckAll);
        $('rb-check-all').addEventListener('change', (event) => listBoxes().forEach((box) => { box.checked = event.target.checked; }));
        $('rb-search').addEventListener('input', loadClients);
        $('rb-assigned').addEventListener('change', loadClients);

        function addToDay(day, ids) {
            const stops = state.days[day];
            let toAdd = ids.filter((id) => !stops.some((stop) => stop.client_id === id));
            let capped = 0;
            if (cfg.maxPerDay !== null) {
                const room = Math.max(0, cfg.maxPerDay - stops.length);
                if (toAdd.length > room) { capped = toAdd.length - room; toAdd = toAdd.slice(0, room); }
            }
            toAdd.forEach((id) => stops.push({ client_id: id, visit_status: null }));
            if (toAdd.length) {
                state.dirty = true;
                orderDay(day);
            }
            return { added: toAdd.length, capped };
        }

        $('rb-add').addEventListener('click', () => {
            if (!currentEmployee()) return notify('warning', 'اختر المندوب أولاً.');
            if (isDayLocked()) return notify('warning', `يوم ${dayName(state.currentDay)} مقفل — بدأت إحدى زياراته.`);
            const ids = listBoxes().filter((box) => box.checked).map((box) => Number(box.value));
            if (!ids.length) return notify('info', 'حدّد عميلاً واحداً على الأقل من القائمة.');

            const result = addToDay(state.currentDay, ids);
            if (result.capped) notify('warning', `لم يُضف ${result.capped} عميل — الحدّ الأعلى لعملاء اليوم ${cfg.maxPerDay} (إعدادات خطوط السير).`);
            $('rb-check-all').checked = false;
            refresh(true);
        });

        // ═══════════════ الأيام والجدول ═══════════════

        function renderDays() {
            $('rb-days').innerHTML = DAYS.map((day) => {
                const count = state.days[day].length;
                const classes = ['rb-day', day === state.currentDay ? 'is-active' : '', count ? 'has-clients' : ''].filter(Boolean).join(' ');
                return `<button type="button" class="${classes}" data-day="${day}" title="${fmtDate(dayDate(day))}">
                    ${isDayLocked(day) ? '<i class="fas fa-lock"></i>' : ''}${esc(dayName(day))}<span class="rb-day__count">${count}</span>
                </button>`;
            }).join('');

            const locked = isDayLocked();
            $('rb-add-label').textContent = `إضافة المحدد إلى يوم ${dayName(state.currentDay)}`;
            $('rb-locked').hidden = !locked;
            $('rb-locked-text').textContent = `يوم ${dayName(state.currentDay)} مقفل: بدأت إحدى زياراته، وإعدادات خطوط السير تمنع التعديل بعد البدء.`;
            $('rb-add').disabled = locked;
            $('rb-clear-day').disabled = locked;
        }

        function renderStops() {
            const stops = dayStops();
            const locked = isDayLocked();
            const empty = $('rb-stops-empty');
            empty.hidden = stops.length > 0;
            empty.textContent = currentEmployee() ? `لا عملاء في يوم ${dayName(state.currentDay)} — حدّدهم من «اختيار العملاء» ثم أضفهم.` : 'اختر المندوب لعرض خط سيره.';

            $('rb-stops').innerHTML = stops.map((stop, index) => {
                const client = state.clients.get(stop.client_id) || { name: `#${stop.client_id}` };
                const visit = STATUS_LABEL[stop.visit_status] ? ` <span class="rb-badge">${STATUS_LABEL[stop.visit_status]}</span>` : '';
                return `<tr data-id="${stop.client_id}">
                    <td><span class="rb-order-cell"><span class="rb-grip${cfg.canReorder && !locked ? '' : ' is-off'}" title="${cfg.canReorder ? 'اسحب لتغيير الترتيب' : 'الترتيب من إعدادات خطوط السير'}"><i class="fas fa-grip-vertical"></i></span><span class="rb-order">${index + 1}</span></span></td>
                    <td class="rb-client-cell"><strong><a class="rb-client-link" href="${cfg.showUrl.replace('__ID__', stop.client_id)}" target="_blank" rel="noopener">${esc(client.name)}</a>${visit}${sourceBadge(stop.source)}</strong><small>${esc(client.code || '')}${located(client) ? '' : ' <span class="rb-badge rb-badge--warn">بلا موقع</span>'}</small></td>
                    <td>${statusBadge(client.status)}</td>
                    <td>${esc(client.region || '—')}</td>
                    <td>${esc(client.branch || '—')}${client.unit ? `<small class="rb-muted" style="display:block">${esc(client.unit)}</small>` : ''}</td>
                    <td><button type="button" class="rb-icon rb-icon--delete" data-remove="${stop.client_id}" title="حذف من اليوم" ${locked ? 'disabled' : ''}><i class="far fa-trash-alt"></i></button></td>
                </tr>`;
            }).join('');

            $('rb-foot-count').textContent = stops.length;
        }

        function renderStats() {
            const weekIds = new Set(DAYS.flatMap((day) => state.days[day].map((stop) => stop.client_id)));
            $('rb-stat-selected').textContent = weekIds.size;

            const known = !!currentEmployee() && state.assignedLoaded;
            $('rb-stat-assigned').textContent = known ? state.assignedIds.size : '—';
            $('rb-stat-missing').textContent = known ? [...state.assignedIds].filter((id) => !weekIds.has(id)).length : '—';

            $('rb-stat-day').textContent = dayStops().length;
            $('rb-stat-day-name').textContent = `يوم ${dayName(state.currentDay)}`;
        }

        function refresh(routeChanged) {
            renderDays();
            renderStops();
            renderList();
            renderStats();
            if (routeChanged) scheduleRoute();
        }

        $('rb-days').addEventListener('click', (event) => {
            const button = event.target.closest('[data-day]');
            if (!button || button.dataset.day === state.currentDay) return;
            state.currentDay = button.dataset.day;
            refresh(true);
        });

        $('rb-stops').addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove]');
            if (!remove || isDayLocked()) return;
            const id = Number(remove.dataset.remove);
            state.days[state.currentDay] = dayStops().filter((stop) => stop.client_id !== id);
            state.dirty = true;
            refresh(true);
        });

        if (window.Sortable) {
            Sortable.create($('rb-stops'), {
                handle: '.rb-grip',
                animation: 150,
                disabled: !cfg.canReorder,
                onEnd() {
                    if (isDayLocked()) return refresh(false);
                    const order = [...$('rb-stops').querySelectorAll('tr[data-id]')].map((row) => Number(row.dataset.id));
                    dayStops().sort((a, b) => order.indexOf(a.client_id) - order.indexOf(b.client_id));
                    state.dirty = true;
                    refresh(true);
                },
            });
        }

        $('rb-auto').addEventListener('click', async () => {
            if (!currentEmployee()) return notify('warning', 'اختر المندوب أولاً.');
            if (!state.listed.length) return notify('info', 'اعرض عملاء جهةٍ في «اختيار العملاء» أولاً — يُوزَّع ما في القائمة.');

            const open = DAYS.filter((day) => !isDayLocked(day));
            let exclude = '';

            if (window.Swal) {
                const answer = await Swal.fire({
                    title: 'التوزيع التلقائي',
                    html: `<p>سيُوزَّع ${state.listed.length} عميل على أيام الأسبوع، ويُستبدل ما في الأيام غير المقفلة.</p>
                        <label style="display:block;margin-top:.75rem">استثناء يوم:</label>
                        <select id="rb-exclude" class="swal2-select" style="display:inline-block;width:auto">
                            <option value="">لا يوجد</option>${open.map((day) => `<option value="${day}">${esc(dayName(day))}</option>`).join('')}
                        </select>`,
                    showCancelButton: true,
                    confirmButtonText: 'ابدأ التوزيع',
                    cancelButtonText: 'إلغاء',
                    preConfirm: () => document.getElementById('rb-exclude').value,
                });
                if (!answer.isConfirmed) return;
                exclude = answer.value;
            } else if (!window.confirm('سيُستبدل خط الأيام غير المقفلة بتوزيعٍ تلقائي. متابعة؟')) {
                return;
            }

            const target = open.filter((day) => day !== exclude);
            if (!target.length) return notify('warning', 'لا أيام متاحة للتوزيع.');

            target.forEach((day) => { state.days[day] = []; });
            let added = 0;
            state.listed.forEach((client, index) => { added += addToDay(target[index % target.length], [Number(client.id)]).added; });
            state.dirty = true;
            refresh(true);

            const skipped = state.listed.length - added;
            notify('success', `وُزِّع ${added} عميل${skipped ? ` — ولم يتّسع لـ ${skipped} بسبب الحدّ الأعلى لعملاء اليوم` : ''}.`);
        });

        $('rb-clear-day').addEventListener('click', async () => {
            if (isDayLocked() || !dayStops().length) return;
            if (!(await confirmAsk(`مسح ${dayStops().length} عميل من يوم ${dayName(state.currentDay)}؟`))) return;
            state.days[state.currentDay] = [];
            state.dirty = true;
            refresh(true);
        });

        $('rb-clear-all').addEventListener('click', async () => {
            const total = DAYS.filter((day) => !isDayLocked(day)).reduce((sum, day) => sum + state.days[day].length, 0);
            if (!total) return notify('info', 'لا عملاء لمسحهم.');
            if (!(await confirmAsk(`مسح جميع العملاء (${total}) من كل أيام الأسبوع غير المقفلة؟`))) return;
            DAYS.forEach((day) => { if (!isDayLocked(day)) state.days[day] = []; });
            state.dirty = true;
            refresh(true);
        });

        // ═══════════════ النافذة ═══════════════

        const openModal = (id) => { $(id).hidden = false; };
        const closeModal = (id) => { $(id).hidden = true; };

        document.querySelectorAll('#route-builder .rb-modal').forEach((modal) => modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('[data-close]')) modal.hidden = true;
        }));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') document.querySelectorAll('#route-builder .rb-modal').forEach((modal) => { modal.hidden = true; });
        });

        // ═══════════════ الخريطة ═══════════════

        const mapState = { ready: false, gmap: null, overlays: [], seq: 0 };
        let routeTimer = null;

        function mapNotice(html) {
            mapState.ready = false;
            $('rb-map').hidden = true;
            const notice = $('rb-map-notice');
            notice.hidden = false;
            notice.innerHTML = html;
        }

        function initMap() {
            if (!cfg.mapsConfigured) {
                return mapNotice('<div><p><i class="fas fa-map-marked-alt fa-2x"></i></p><p><strong>الخريطة غير مفعّلة</strong></p><p>أضف مفتاح Google Maps في ملف <code>.env</code> على السيرفر: <code>GOOGLE_MAPS_API_KEY</code> ثم نفّذ <code>php artisan config:clear</code>. المسافة تُقدَّر مؤقتاً بخطٍّ مستقيم.</p></div>');
            }
            if (!(window.google && google.maps && google.maps.Map)) {
                return mapNotice('<div><p><strong>تعذّر تحميل خرائط Google.</strong></p><p>تحقّق من الاتصال بالإنترنت ومن صلاحية المفتاح.</p></div>');
            }
            mapState.gmap = new google.maps.Map($('rb-map'), {
                center: { lat: 24.7136, lng: 46.6753 },
                zoom: 11,
                streetViewControl: false,
                mapTypeControl: true,
                fullscreenControl: true,
            });
            mapState.ready = true;
        }

        window.gm_authFailure = () => {
            mapNotice('<div><p><strong>مفتاح Google Maps مرفوض.</strong></p><p>فعّل Maps JavaScript API وDirections API لهذا المفتاح في Google Cloud.</p></div>');
            scheduleRoute();
        };

        function scheduleRoute() {
            clearTimeout(routeTimer);
            routeTimer = setTimeout(drawRoute, 450);
        }

        function clearOverlays() {
            mapState.overlays.forEach((overlay) => overlay.setMap(null));
            mapState.overlays = [];
        }

        function showDistance(meters) {
            $('rb-distance').textContent = meters === null ? '—' : `${(meters / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })} كم`;
        }

        function updateGmapsLink(points) {
            const link = $('rb-open-gmaps');
            if (!points.length) {
                link.classList.add('is-disabled');
                link.removeAttribute('href');
                return;
            }
            const ll = (point) => `${point.c.lat},${point.c.lng}`;
            const params = new URLSearchParams({ api: '1', travelmode: 'driving', destination: ll(points[points.length - 1]) });
            if (points.length > 1) params.set('origin', ll(points[0]));
            const middle = points.slice(1, -1).slice(0, 9);
            if (middle.length) params.set('waypoints', middle.map(ll).join('|'));
            link.href = `https://www.google.com/maps/dir/?${params}`;
            link.title = points.length > 11 ? 'رابط خرائط جوجل يقبل 9 نقاط وسيطة — يُفتح أوّل المسار' : '';
            link.classList.remove('is-disabled');
        }

        function straightDistance(points) {
            let meters = 0;
            for (let i = 1; i < points.length; i++) meters += haversineKm(points[i - 1].c, points[i].c) * 1.3 * 1000;
            showDistance(meters);
        }

        async function drawRoute() {
            const seq = ++mapState.seq;
            const points = dayStops()
                .map((stop) => ({ s: stop, c: state.clients.get(stop.client_id) }))
                .filter((point) => located(point.c));

            updateGmapsLink(points);

            if (!points.length) {
                if (mapState.ready) clearOverlays();
                showDistance(null);
                return;
            }

            if (!mapState.ready) return straightDistance(points);

            clearOverlays();
            const latLng = (point) => ({ lat: Number(point.c.lat), lng: Number(point.c.lng) });
            const bounds = new google.maps.LatLngBounds();

            points.forEach((point, index) => {
                bounds.extend(latLng(point));
                const color = index === 0 ? '#16a34a' : (index === points.length - 1 ? '#e11d48' : '#2563eb');
                mapState.overlays.push(new google.maps.Marker({
                    map: mapState.gmap,
                    position: latLng(point),
                    title: point.c.name,
                    zIndex: 10 + index,
                    label: { text: String(dayStops().indexOf(point.s) + 1), color: '#fff', fontWeight: '700', fontSize: '12px' },
                    icon: { path: google.maps.SymbolPath.CIRCLE, scale: 13, fillColor: color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
                }));
            });

            if (points.length === 1) {
                mapState.gmap.setCenter(bounds.getCenter());
                mapState.gmap.setZoom(14);
                showDistance(0);
                return;
            }

            mapState.gmap.fitBounds(bounds, 48);

            const service = new google.maps.DirectionsService();
            let meters = 0;

            try {
                // الطلبُ الواحد يقبل 25 نقطة — المسارُ الأطول يُقسَم بتداخل نقطة
                for (let start = 0; start < points.length - 1; start += 24) {
                    const chunk = points.slice(start, start + 25);
                    const result = await service.route({
                        origin: latLng(chunk[0]),
                        destination: latLng(chunk[chunk.length - 1]),
                        waypoints: chunk.slice(1, -1).map((point) => ({ location: latLng(point), stopover: true })),
                        travelMode: google.maps.TravelMode.DRIVING,
                    });
                    if (seq !== mapState.seq) return;

                    mapState.overlays.push(new google.maps.DirectionsRenderer({
                        map: mapState.gmap,
                        directions: result,
                        suppressMarkers: true,
                        preserveViewport: true,
                        polylineOptions: { strokeColor: '#2563eb', strokeWeight: 5, strokeOpacity: 0.85 },
                    }));
                    result.routes[0].legs.forEach((leg) => { meters += leg.distance.value; });
                }
            } catch (error) {
                if (seq !== mapState.seq) return;
                mapState.overlays.push(new google.maps.Polyline({ map: mapState.gmap, path: points.map(latLng), strokeColor: '#2563eb', strokeWeight: 4, strokeOpacity: 0.7 }));
                return straightDistance(points);
            }

            showDistance(meters);
        }

        document.querySelectorAll('#route-builder .rb-tab').forEach((tab) => tab.addEventListener('click', () => {
            document.querySelectorAll('#route-builder .rb-tab').forEach((item) => item.classList.toggle('is-active', item === tab));
            $('rb-work').dataset.view = tab.dataset.view;
            if (mapState.ready) scheduleRoute();
        }));

        // ═══════════════ تحميل الأسبوع ═══════════════

        function toStops(list, keepStatus) {
            let stops = list || [];
            if (!keepStatus && cfg.maxPerDay !== null) stops = stops.slice(0, cfg.maxPerDay);
            return stops.map((stop) => {
                const id = Number(stop.client_id);
                state.clients.set(id, { ...stop, id });
                // الشارةُ لليوم المحفوظ وحده — المنسوخُ من أسبوعٍ سابق إضافةٌ جديدة
                return { client_id: id, visit_status: keepStatus ? (stop.visit_status || null) : null, source: keepStatus ? (stop.source || null) : null };
            });
        }

        let weekSeq = 0;
        async function loadWeek() {
            const seq = ++weekSeq;
            const employeeId = currentEmployee();
            state.days = emptyWeek();
            state.lockedDays = new Set();

            if (employeeId) {
                $('rb-stops-empty').textContent = 'جارٍ تحميل خط الأسبوع…';
                try {
                    const body = await getJson(weekUrl(employeeId), { year: yearSelect.value, week: weekSelect.value });
                    if (seq !== weekSeq) return;
                    DAYS.forEach((day) => { state.days[day] = toStops(body.days?.[day] || [], true); });
                    state.lockedDays = new Set(body.locked_days || []);
                } catch (error) {
                    if (seq === weekSeq) notify('error', `تعذّر تحميل خط الأسبوع: ${error.message}`);
                }
            }

            if (seq !== weekSeq) return;
            state.dirty = false;
            refresh(true);
        }

        async function fetchAssigned() {
            const employeeId = currentEmployee();
            state.assignedIds = new Set();
            state.assignedLoaded = false;
            if (!employeeId) return renderStats();
            try {
                const body = await getJson(cfg.clientsUrl, { employee_id: employeeId, summary: 1 });
                if (employeeId !== currentEmployee()) return;
                state.assignedIds = new Set((body.assigned_ids || []).map(Number));
                state.assignedLoaded = true;
            } catch (error) {
                // تبقى البطاقتان «—»
            }
            renderStats();
        }

        function guardedChange(select, apply) {
            select.dataset.prev = select.value;
            select.addEventListener('change', async () => {
                if (state.dirty && !(await confirmAsk('لديك تعديلات لم تُحفظ على هذا الأسبوع. تجاهلها والمتابعة؟'))) {
                    select.value = select.dataset.prev;
                    return;
                }
                select.dataset.prev = select.value;
                apply();
            });
        }

        guardedChange(employeeSelect, () => { fetchAssigned(); loadWeek(); loadClients(); });
        guardedChange(yearSelect, () => { fillWeeks(weekSelect.value); loadWeek(); });
        guardedChange(weekSelect, () => { syncWeekRange(); loadWeek(); });

        window.addEventListener('beforeunload', (event) => {
            if (state.dirty) { event.preventDefault(); event.returnValue = ''; }
        });

        // ═══════════════ نسخ من خط سابق ═══════════════

        $('rb-copy').addEventListener('click', () => {
            if (!currentEmployee()) return notify('info', 'اختر المندوب أولاً.');
            let year = Number(yearSelect.value);
            let week = Number(weekSelect.value);
            const items = [];
            for (let i = 0; i < 8; i++) {
                week--;
                if (week < 1) { year--; week = weeksInYear(year); }
                const start = weekStart(year, week);
                items.push(`<button type="button" class="rb-source" data-year="${year}" data-week="${week}">
                    <span><strong>الأسبوع ${week} — ${year}</strong><br><small>${fmtDate(start)} - ${fmtDate(addDays(start, 6))}</small></span>
                    <i class="fas fa-chevron-left"></i></button>`);
            }
            $('rb-copy-list').innerHTML = items.join('');
            openModal('rb-copy-modal');
        });

        $('rb-copy-list').addEventListener('click', async (event) => {
            const button = event.target.closest('.rb-source');
            if (!button) return;
            try {
                const body = await getJson(weekUrl(currentEmployee()), { year: button.dataset.year, week: button.dataset.week });
                const total = DAYS.reduce((sum, day) => sum + (body.days?.[day]?.length || 0), 0);
                if (!total) return notify('info', 'لا زيارات في ذلك الأسبوع.');

                const current = DAYS.reduce((sum, day) => sum + state.days[day].length, 0);
                if (current && !(await confirmAsk(`سيُستبدل خط هذا الأسبوع (${current} زيارة) بخط الأسبوع ${button.dataset.week} (${total} زيارة) — عدا الأيام المقفلة. متابعة؟`))) return;

                DAYS.forEach((day) => {
                    if (isDayLocked(day)) return;
                    state.days[day] = toStops(body.days?.[day] || [], false);
                    orderDay(day);
                });
                state.dirty = true;
                closeModal('rb-copy-modal');
                refresh(true);
            } catch (error) {
                notify('error', error.message);
            }
        });

        // ═══════════════ الحفظ ═══════════════

        $('rb-save').addEventListener('click', async () => {
            const button = $('rb-save');
            const employeeId = currentEmployee();
            if (!employeeId) return notify('warning', 'اختر المندوب.');

            const total = DAYS.reduce((sum, day) => sum + state.days[day].length, 0);
            if (!total && !(await confirmAsk('لا عملاء في أيّ يومٍ من هذا الأسبوع — سيُرفع خط الأسبوع المحفوظ (عدا الزيارات التي بدأت). متابعة؟'))) return;

            const payload = {
                employee_id: employeeId,
                year: Number(yearSelect.value),
                week_number: Number(weekSelect.value),
                visits: Object.fromEntries(DAYS.map((day) => [day, state.days[day].map((stop) => ({ client_id: stop.client_id }))])),
            };

            const label = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ…';

            try {
                const response = await fetch(cfg.saveUrl, {
                    method: cfg.saveMethod,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload),
                });
                const body = await response.json().catch(() => ({}));

                if (response.ok && body.success) {
                    state.dirty = false;
                    if (window.Swal) await Swal.fire({ icon: 'success', text: body.message, timer: 1800, showConfirmButton: false });
                    window.location.href = body.redirect || cfg.listUrl;
                    return;
                }

                const errors = body.errors ? [...new Set(Object.values(body.errors).flat())] : [];
                const html = `${esc(body.message || 'تعذّر الحفظ.')}${errors.length ? `<ul style="text-align:right;margin-top:.5rem">${errors.map((e) => `<li>${esc(e)}</li>`).join('')}</ul>` : ''}`;
                if (window.Swal) Swal.fire({ icon: 'error', title: 'تعذّر الحفظ', html });
                else alert(body.message || 'تعذّر الحفظ.');
            } catch (error) {
                notify('error', `تعذّر الاتصال بالخادم: ${error.message}`);
            } finally {
                button.innerHTML = label;
                button.disabled = false;
            }
        });

        // ═══════════════ البداية ═══════════════

        LEVELS.forEach((_, index) => fillLevel(index));
        fillWeeks(cfg.currentWeek);
        initMap();
        refresh(false);
        loadClients();
        if (currentEmployee()) {
            fetchAssigned();
            loadWeek();
        }
    })();
</script>
