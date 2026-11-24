@php
    // `@json` لا يقبل مصفوفةً على أكثر من سطر — تُبنى هنا ثم تُمرَّر متغيّراً
    $indexFilters = [
        'node_id' => $filters['node_id'],
        'date_from' => $filters['date_from']?->toDateString(),
        'date_to' => $filters['date_to']?->toDateString(),
    ];
    $indexWeek = $selectedWeek ? ['year' => $selectedWeek['year'], 'week' => $selectedWeek['week']] : null;
@endphp
<script>
    window.ITINERARY_INDEX = {
        dayVisitsUrl: @json(route('itinerary.day-visits')),
        bulkDeleteUrl: @json(route('itinerary.visits.bulk-destroy')),
        moveUrl: @json(route('itinerary.move-incomplete')),
        editUrl: @json(route('itinerary.edit', ['id' => '__EMP__'])),
        showUrl: @json(route('clients.show', ['id' => '__ID__'])),
        tones: @json(\App\Services\Itinerary\DailyItineraryService::VISIT_TONES),
        csrf: @json(csrf_token()),
        nodes: @json($nodes),
        days: @json($days),
        filters: @json($indexFilters),
        week: @json($indexWeek),
        currentWeek: @json($currentWeek),
        canEdit: @json($canEdit),
        mapsConfigured: @json($mapsKey !== ''),
    };
</script>
<script>
    (function () {
        'use strict';

        const cfg = window.ITINERARY_INDEX;
        const $ = (id) => document.getElementById(id);
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const LEVELS = ['region', 'branch', 'group', 'outlet'];
        const DAYS = Object.keys(cfg.days);
        const dayName = (day) => (cfg.days[day] && cfg.days[day].name) || day;
        const VISIT_STATE = {
            planned: 'مخطَّطة',
            started: 'بدأت',
            completed: 'مكتملة',
            missed: 'فائتة',
            cancelled: 'ملغاة',
        };
        const nodeById = new Map(cfg.nodes.map((node) => [Number(node.id), node]));
        const located = (item) => !!item && item.lat !== null && item.lat !== undefined && item.lng !== null && item.lng !== undefined;

        // ═══════════════ أدوات ═══════════════

        function notify(icon, text) {
            if (window.Swal) return Swal.fire({ icon, text, confirmButtonText: 'حسناً' });
            alert(text);
            return Promise.resolve();
        }

        async function confirmAsk(text) {
            if (!window.Swal) return window.confirm(text);
            const answer = await Swal.fire({ icon: 'warning', text, showCancelButton: true, confirmButtonText: 'متابعة', cancelButtonText: 'إلغاء' });
            return answer.isConfirmed;
        }

        async function request(url, method, params, body) {
            const target = new URL(url, window.location.origin);
            Object.entries(params || {}).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') target.searchParams.set(key, value);
            });
            const response = await fetch(target, {
                method,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: body ? JSON.stringify(body) : undefined,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = data.errors ? Object.values(data.errors).flat().join(' — ') : '';
                throw new Error(errors || data.message || `HTTP ${response.status}`);
            }
            return data;
        }

        function statusBadge(status) {
            if (!status || !status.name) return '<span class="rb-muted">—</span>';
            const color = /^#[0-9a-f]{3,8}$/i.test(String(status.color || '')) ? status.color : '';
            return `<span class="rb-status"${color ? ` style="--rb-status:${color}"` : ''}>${esc(status.name)}</span>`;
        }

        /** «أضافه المندوب» / «أضافه: فلان» — صفوفُ الخطّة الأسبوعية بلا شارة */
        function sourceBadge(source) {
            if (!source || !source.label) return '';
            return `<span class="rb-src rb-src--${source.tone === 'self' ? 'self' : 'other'}" title="أُضيف من «خط سيري اليومي»"><i class="fas fa-user-plus"></i> ${esc(source.label)}</span>`;
        }

        /** لونُ العميل في يومه (مُضاف/من الخطة × زيارة) — DailyItineraryService::VISIT_TONES */
        const toneOf = (key) => cfg.tones[key] || cfg.tones.not_visited;
        const toneChip = (key) => `<span class="rb-tone" style="--tone:${toneOf(key).color}" title="${esc(toneOf(key).hint)}">${esc(toneOf(key).label)}</span>`;

        function renderToneLegend(visits) {
            const counts = visits.reduce((all, visit) => ({ ...all, [visit.tone]: (all[visit.tone] || 0) + 1 }), {});
            $('rb-day-legend').innerHTML = Object.entries(cfg.tones).map(([key, tone]) => `<div class="rb-tone-legend__item" style="--tone:${tone.color}">
                <div><strong>${esc(tone.label)}</strong><small>${esc(tone.hint)}</small></div>
                <span class="rb-tone-legend__count">${counts[key] || 0}</span>
            </div>`).join('');
        }

        function haversineKm(a, b) {
            const rad = (deg) => Number(deg) * Math.PI / 180;
            const dLat = rad(b.lat) - rad(a.lat);
            const dLng = rad(b.lng) - rad(a.lng);
            const x = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
            return 12742 * Math.asin(Math.sqrt(x));
        }

        const openModal = (id) => { $(id).hidden = false; };
        const closeModal = (id) => { $(id).hidden = true; };
        document.querySelectorAll('#route-builder .rb-modal').forEach((modal) => modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('[data-close]')) modal.hidden = true;
        }));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') document.querySelectorAll('#route-builder .rb-modal').forEach((modal) => { modal.hidden = true; });
        });

        // ═══════════════ التصفية: المنطقة ← الفرع ← المجموعة ← المنفذ ═══════════════

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

        /** إعادةُ سلسلة القوائم لجهةٍ محفوظة في الرابط — كلُّ مستوى يأخذ سلفَها من نوعه */
        function restoreNode(nodeId) {
            const chain = [];
            let cursor = nodeById.get(Number(nodeId));
            for (let guard = 0; cursor && guard < 30; guard++) {
                chain.unshift(cursor);
                cursor = nodeById.get(Number(cursor.parent_id));
            }
            LEVELS.forEach((level, index) => {
                fillLevel(index);
                const match = chain.find((node) => node.display_type === level);
                if (match && [...levelSelects[index].options].some((option) => Number(option.value) === Number(match.id))) {
                    levelSelects[index].value = String(match.id);
                }
            });
        }

        levelSelects.forEach((select, index) => select.addEventListener('change', () => {
            for (let i = index + 1; i < levelSelects.length; i++) fillLevel(i);
            $('rb-node').value = effectiveNode() || '';
        }));

        if (cfg.filters.node_id) restoreNode(cfg.filters.node_id);
        else LEVELS.forEach((_, index) => fillLevel(index));

        // الحقول الفارغة لا تُرسل — رابطٌ نظيف يُحفظ ويُشارك
        $('rb-filters').addEventListener('submit', () => {
            $('rb-filters').querySelectorAll('input, select').forEach((field) => {
                if (!field.name || !field.value) field.disabled = true;
            });
        });

        // ═══════════════ لوحة اليوم ═══════════════

        const panel = { employeeId: null, day: null, data: null, seq: 0 };
        const colspan = cfg.canEdit ? 8 : 6;

        document.querySelectorAll('#route-builder .rb-cell[data-employee]').forEach((cell) => cell.addEventListener('click', () => {
            openDay(Number(cell.dataset.employee), cell.dataset.day, true);
        }));

        $('rb-day-close').addEventListener('click', () => {
            $('rb-day-panel').hidden = true;
            document.querySelectorAll('#route-builder .rb-cell.is-active').forEach((cell) => cell.classList.remove('is-active'));
            history.replaceState(null, '', window.location.pathname + window.location.search);
            panel.employeeId = null;
        });

        async function openDay(employeeId, day, scroll) {
            if (!cfg.week) return;
            const seq = ++panel.seq;
            document.querySelectorAll('#route-builder .rb-cell.is-active').forEach((cell) => cell.classList.remove('is-active'));
            document.querySelector(`#route-builder .rb-cell[data-employee="${employeeId}"][data-day="${day}"]`)?.classList.add('is-active');

            panel.employeeId = employeeId;
            panel.day = day;
            history.replaceState(null, '', `${window.location.pathname}${window.location.search}#d=${employeeId}-${day}`);

            $('rb-day-panel').hidden = false;
            $('rb-visits').innerHTML = `<tr><td colspan="${colspan}" class="rb-empty"><i class="fas fa-spinner fa-spin"></i> جارٍ التحميل…</td></tr>`;
            if (scroll) $('rb-day-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });

            try {
                const body = await request(cfg.dayVisitsUrl, 'GET', { employee_id: employeeId, day, year: cfg.week.year, week: cfg.week.week, ...cfg.filters });
                if (seq !== panel.seq) return;
                panel.data = body;
                renderDay();
            } catch (error) {
                if (seq !== panel.seq) return;
                panel.data = null;
                $('rb-visits').innerHTML = `<tr><td colspan="${colspan}" class="rb-empty">تعذّر جلب الزيارات: ${esc(error.message)}</td></tr>`;
            }
        }

        function renderDay() {
            const { employee, date, locked, visits } = panel.data;
            const editable = cfg.canEdit && !locked;

            $('rb-day-title').textContent = `زيارات ${employee || ''} — يوم ${dayName(panel.day)}`;
            $('rb-day-date').textContent = date;
            $('rb-day-locked').hidden = !locked;
            if ($('rb-day-edit')) $('rb-day-edit').href = `${cfg.editUrl.replace('__EMP__', panel.employeeId)}?date=${date}`;

            $('rb-visits').innerHTML = visits.length ? visits.map((visit, index) => {
                const state = visit.completed ? 'completed' : (visit.visit_status || 'planned');
                return `<tr class="rb-tone-row" style="--tone:${toneOf(visit.tone).color}">
                    ${cfg.canEdit ? `<td><input type="checkbox" class="rb-check-input rb-visit-check" value="${index}" ${editable ? '' : 'disabled'}></td>` : ''}
                    <td><span class="rb-order">${index + 1}</span></td>
                    <td class="rb-client-cell"><strong><a class="rb-client-link" href="${cfg.showUrl.replace('__ID__', visit.id)}" target="_blank" rel="noopener">${esc(visit.name)}</a> ${toneChip(visit.tone)} ${sourceBadge(visit.source)}</strong><small>${esc(visit.code || '')}${located(visit) ? '' : ' <span class="rb-badge rb-badge--warn">بلا موقع</span>'}</small></td>
                    <td>${statusBadge(visit.status)}</td>
                    <td><span class="rb-vstate rb-vstate--${esc(state)}">${esc(VISIT_STATE[state] || state)}</span>${visit.moved_from ? ` <span class="rb-badge rb-badge--muted" title="نُقلت من يوم ${esc(visit.moved_from)}">منقولة</span>` : ''}</td>
                    <td>${esc(visit.region || '—')}</td>
                    <td>${esc(visit.branch || '—')}${visit.unit ? `<small class="rb-muted" style="display:block">${esc(visit.unit)}</small>` : ''}</td>
                    ${cfg.canEdit ? `<td><button type="button" class="rb-icon rb-icon--delete" data-delete="${index}" ${editable ? '' : 'disabled'} title="حذف الزيارة"><i class="far fa-trash-alt"></i></button></td>` : ''}
                </tr>`;
            }).join('') : `<tr><td colspan="${colspan}" class="rb-empty">لا زيارات في هذا اليوم ضمن التصفية الحالية.</td></tr>`;

            $('rb-day-count').textContent = visits.length;
            $('rb-day-done').textContent = visits.filter((visit) => visit.completed).length;
            renderToneLegend(visits);
            syncSelection();
            scheduleMap();
        }

        const visitChecks = () => [...document.querySelectorAll('#rb-visits .rb-visit-check:not(:disabled)')];
        const selectedVisits = () => visitChecks().filter((check) => check.checked).map((check) => panel.data.visits[Number(check.value)]);

        function syncSelection() {
            if (!cfg.canEdit) return;
            const checks = visitChecks();
            const count = checks.filter((check) => check.checked).length;
            const locked = !!panel.data?.locked;
            const hasVisits = !!panel.data?.visits.length;

            $('rb-check-all').checked = checks.length > 0 && count === checks.length;
            $('rb-check-all').disabled = checks.length === 0;
            $('rb-delete-selected').disabled = count === 0;
            $('rb-delete-selected').querySelector('span').textContent = count ? `حذف المحدد (${count})` : 'حذف المحدد';
            $('rb-delete-day').disabled = locked || !hasVisits;
            $('rb-move').disabled = locked || !hasVisits;
        }

        if (cfg.canEdit) {
            $('rb-visits').addEventListener('change', syncSelection);
            $('rb-check-all').addEventListener('change', (event) => {
                visitChecks().forEach((check) => { check.checked = event.target.checked; });
                syncSelection();
            });

            $('rb-visits').addEventListener('click', async (event) => {
                const button = event.target.closest('[data-delete]');
                if (!button) return;
                const visit = panel.data.visits[Number(button.dataset.delete)];
                if (await confirmAsk(`حذف زيارة «${visit.name}» من يوم ${dayName(panel.day)}؟`)) deleteVisits([visit]);
            });

            $('rb-delete-selected').addEventListener('click', async () => {
                const visits = selectedVisits();
                if (visits.length && await confirmAsk(`حذف ${visits.length} زيارة من يوم ${dayName(panel.day)}؟`)) deleteVisits(visits);
            });

            $('rb-delete-day').addEventListener('click', async () => {
                const visits = panel.data?.visits || [];
                if (visits.length && await confirmAsk(`حذف جميع زيارات يوم ${dayName(panel.day)} (${visits.length})؟ لا يمكن التراجع.`)) deleteVisits(visits);
            });
        }

        /** حذفٌ بطلبٍ واحد — الخادم يتخطّى الأيام المقفلة وما خارج نطاق المستخدم ويذكرهما */
        async function deleteVisits(visits) {
            try {
                const body = await request(cfg.bulkDeleteUrl, 'POST', null, { visit_ids: visits.flatMap((visit) => visit.visit_ids) });
                await notify(body.success ? 'success' : 'error', body.message || 'تم الحذف.');
                window.location.reload();
            } catch (error) {
                notify('error', error.message);
            }
        }

        // ═══════════════ نقل غير المكتمل ═══════════════

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

        function fillMoveWeeks(selected) {
            const year = Number($('rb-move-year').value);
            const count = weeksInYear(year);
            const chosen = Math.min(Math.max(1, Number(selected) || 1), count);
            $('rb-move-week').innerHTML = Array.from({ length: count }, (_, i) => {
                const week = i + 1;
                const start = weekStart(year, week);
                const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
                return `<option value="${week}" ${week === chosen ? 'selected' : ''}>الأسبوع ${week} (${fmtDate(start)} - ${fmtDate(end)})</option>`;
            }).join('');
        }

        if (cfg.canEdit) {
            $('rb-move-year').addEventListener('change', () => fillMoveWeeks($('rb-move-week').value));

            $('rb-move').addEventListener('click', () => {
                const incomplete = (panel.data?.visits || [])
                    .map((visit, index) => ({ visit, index }))
                    .filter(({ visit }) => !visit.completed && visit.visit_status !== 'cancelled');
                if (!incomplete.length) return notify('info', 'لا زيارات غير مكتملة في هذا اليوم.');

                const picked = new Set(selectedVisits().map((visit) => visit.id));
                $('rb-move-list').innerHTML = incomplete.map(({ visit, index }) => `<label class="rb-client">
                    <input type="checkbox" class="rb-move-check" value="${index}" ${picked.size === 0 || picked.has(visit.id) ? 'checked' : ''}>
                    <span class="rb-client__body"><span class="rb-client__name">${esc(visit.name)}</span><span class="rb-client__code">${esc(visit.code || '')}</span></span>
                    ${statusBadge(visit.status)}
                </label>`).join('');

                $('rb-move-year').value = String(cfg.currentWeek.year);
                fillMoveWeeks(cfg.currentWeek.week);
                $('rb-move-day').value = panel.day;
                openModal('rb-move-modal');
            });

            $('rb-move-submit').addEventListener('click', async () => {
                const visits = [...document.querySelectorAll('#rb-move-list .rb-move-check:checked')].map((check) => panel.data.visits[Number(check.value)]);
                if (!visits.length) return notify('warning', 'حدّد زيارة واحدة على الأقل.');

                const payload = {
                    employee_id: panel.employeeId,
                    source_date: panel.data.date,
                    target_year: Number($('rb-move-year').value),
                    target_week: Number($('rb-move-week').value),
                    target_day: $('rb-move-day').value,
                    visit_ids: visits.flatMap((visit) => visit.visit_ids),
                };
                if (payload.target_year === cfg.week.year && payload.target_week === cfg.week.week && payload.target_day === panel.day) {
                    return notify('warning', 'اختر يوماً غير اليوم نفسه.');
                }

                try {
                    const body = await request(cfg.moveUrl, 'POST', null, payload);
                    closeModal('rb-move-modal');
                    await notify(body.success ? 'success' : 'warning', body.message);
                    if (body.success) window.location.reload();
                } catch (error) {
                    notify('error', error.message);
                }
            });
        }

        // ═══════════════ الخريطة ═══════════════

        const mapState = { ready: false, gmap: null, overlays: [] };
        let mapTimer = null;

        function mapNotice(html) {
            mapState.ready = false;
            $('rb-map').hidden = true;
            $('rb-map-notice').hidden = false;
            $('rb-map-notice').innerHTML = html;
        }

        function initMap() {
            if (!cfg.mapsConfigured) {
                return mapNotice('<div><p><i class="fas fa-map-marked-alt fa-2x"></i></p><p><strong>الخريطة غير مفعّلة</strong></p><p>أضف مفتاح Google Maps في ملف <code>.env</code> على السيرفر: <code>GOOGLE_MAPS_API_KEY</code> ثم نفّذ <code>php artisan config:clear</code>.</p></div>');
            }
            if (!(window.google && google.maps && google.maps.Map)) {
                return mapNotice('<div><p><strong>تعذّر تحميل خرائط Google.</strong></p><p>تحقّق من الاتصال بالإنترنت ومن صلاحية المفتاح.</p></div>');
            }
            mapState.gmap = new google.maps.Map($('rb-map'), { center: { lat: 24.7136, lng: 46.6753 }, zoom: 11, streetViewControl: false, fullscreenControl: true });
            mapState.ready = true;
        }

        window.gm_authFailure = () => mapNotice('<div><p><strong>مفتاح Google Maps مرفوض.</strong></p><p>فعّل Maps JavaScript API لهذا المفتاح في Google Cloud.</p></div>');

        function scheduleMap() {
            clearTimeout(mapTimer);
            mapTimer = setTimeout(drawMap, 250);
        }

        function drawMap() {
            const visits = panel.data?.visits || [];
            const points = visits.map((visit, index) => ({ visit, index })).filter(({ visit }) => located(visit));

            let meters = 0;
            for (let i = 1; i < points.length; i++) meters += haversineKm(points[i - 1].visit, points[i].visit) * 1.3 * 1000;
            $('rb-distance').textContent = points.length ? `${(meters / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })} كم` : '—';

            const link = $('rb-open-gmaps');
            if (points.length) {
                const ll = ({ visit }) => `${visit.lat},${visit.lng}`;
                const params = new URLSearchParams({ api: '1', travelmode: 'driving', destination: ll(points[points.length - 1]) });
                if (points.length > 1) params.set('origin', ll(points[0]));
                const middle = points.slice(1, -1).slice(0, 9);
                if (middle.length) params.set('waypoints', middle.map(ll).join('|'));
                link.href = `https://www.google.com/maps/dir/?${params}`;
                link.classList.remove('is-disabled');
            } else {
                link.removeAttribute('href');
                link.classList.add('is-disabled');
            }

            if (!mapState.ready) return;
            mapState.overlays.forEach((overlay) => overlay.setMap(null));
            mapState.overlays = [];
            if (!points.length) return;

            const bounds = new google.maps.LatLngBounds();
            const path = [];

            points.forEach(({ visit, index }) => {
                const position = { lat: Number(visit.lat), lng: Number(visit.lng) };
                const state = visit.completed ? 'completed' : (visit.visit_status || 'planned');
                bounds.extend(position);
                path.push(position);
                mapState.overlays.push(new google.maps.Marker({
                    map: mapState.gmap,
                    position,
                    title: `${visit.name} — ${toneOf(visit.tone).label} (${VISIT_STATE[state] || state})`,
                    label: { text: String(index + 1), color: '#fff', fontWeight: '700', fontSize: '12px' },
                    icon: { path: google.maps.SymbolPath.CIRCLE, scale: 13, fillColor: toneOf(visit.tone).color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
                }));
            });

            if (path.length > 1) {
                mapState.overlays.push(new google.maps.Polyline({ map: mapState.gmap, path, strokeColor: '#2563eb', strokeWeight: 3, strokeOpacity: 0.6 }));
                mapState.gmap.fitBounds(bounds, 48);
            } else {
                mapState.gmap.setCenter(bounds.getCenter());
                mapState.gmap.setZoom(14);
            }
        }

        document.querySelectorAll('#rb-day-panel .rb-tab').forEach((tab) => tab.addEventListener('click', () => {
            document.querySelectorAll('#rb-day-panel .rb-tab').forEach((item) => item.classList.toggle('is-active', item === tab));
            $('rb-work').dataset.view = tab.dataset.view;
            scheduleMap();
        }));

        // ═══════════════ البداية ═══════════════

        initMap();

        // يومٌ مفتوحٌ قبل إعادة التحميل (بعد حذفٍ أو نقل) يُعاد فتحُه
        const opened = /^#d=(\d+)-([a-z]+)$/.exec(window.location.hash);
        if (opened && DAYS.includes(opened[2]) && document.querySelector(`#route-builder .rb-cell[data-employee="${opened[1]}"][data-day="${opened[2]}"]:not(:disabled)`)) {
            openDay(Number(opened[1]), opened[2], false);
        }
    })();
</script>
