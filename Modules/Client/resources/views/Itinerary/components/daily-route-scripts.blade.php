<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
    window.DAILY_ROUTE = {
        pageUrl: @json(route('itinerary.daily')),
        dataUrl: @json(route('itinerary.daily.data')),
        clientsUrl: @json(route('itinerary.daily.clients')),
        storeUrl: @json(route('itinerary.daily.store')),
        showUrl: @json(route('clients.show', ['id' => '__ID__'])),
        openUrl: @json(route('clients.openVisitWorkspace', ['client' => '__ID__'])),
        csrf: @json(csrf_token()),
        authId: @json((int) auth()->id()),
        employeeId: @json($employeeId),
        date: @json($date),
    };
</script>
<script>
    (function () {
        'use strict';

        const cfg = window.DAILY_ROUTE;
        const $ = (id) => document.getElementById(id);
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const withId = (url, id) => url.replace('__ID__', id);

        const STATES = {
            done: 'تمت الزيارة',
            active: 'جارية الآن',
            next: 'الزيارة التالية',
            planned: 'قادمة',
            late: 'متأخرة',
            missed: 'فائتة',
            cancelled: 'ملغاة',
        };
        // الفائتة تُلوَّن كالمتأخرة — كلتاهما زيارةٌ فاتت
        const tone = (stateName) => (stateName === 'missed' ? 'late' : stateName);

        const state = {
            employeeId: Number(cfg.employeeId),
            date: cfg.date,
            day: null,
            selectedId: null,
            view: 'map',
            map: null,
            layer: null,
            me: null,
            markers: new Map(),
            galleryStart: 0,
            picked: new Set(),
        };

        // ═══════════════ أدوات ═══════════════

        function notify(icon, text) {
            if (window.Swal) return Swal.fire({ icon, text, confirmButtonText: 'حسناً' });
            alert(text);
            return Promise.resolve();
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

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload || {}),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok || body.success === false) {
                const firstError = body.errors ? Object.values(body.errors).flat()[0] : null;
                const error = new Error(firstError || body.message || `HTTP ${response.status}`);
                error.body = body;
                throw error;
            }
            return body;
        }

        const fmtKm = (km) => `${km >= 10 ? Math.round(km) : Number(km).toFixed(1).replace(/\.0$/, '')} كم`;

        function fmtMinutes(total) {
            total = Math.max(0, Math.round(total || 0));
            const h = Math.floor(total / 60);
            const m = total % 60;
            if (h === 0) return `${m} د`;
            return m === 0 ? `${h} س` : `${h} س ${m} د`;
        }

        const located = (stop) => stop && stop.lat !== null && stop.lng !== null;
        const stopNumber = (stop) => state.day.stops.indexOf(stop) + 1;
        const selectedStop = () => (state.day ? state.day.stops.find((stop) => stop.id === state.selectedId) : null) || null;

        /** «أضافه: …» من منظور من يفتح الشاشة */
        function byLabel(by) {
            if (!by) return '';
            if (by.self) return state.day.is_self ? 'أضفتَه أنت' : 'أضافه المندوب';
            if (by.id === cfg.authId) return 'أضفتَه أنت لخطّ المندوب';
            return `أضافه: ${by.name}${by.role ? ` · ${by.role}` : ''}`;
        }

        const byTone = (by) => (by && by.self ? 'self' : 'other');

        // ═══════════════ التحميل ═══════════════

        async function load(keepSelection) {
            $('dr-alert').hidden = true;

            try {
                state.day = await getJson(cfg.dataUrl, { employee_id: state.employeeId, date: state.date });
            } catch (error) {
                $('dr-alert').textContent = `تعذّر تحميل خط السير: ${error.message}`;
                $('dr-alert').hidden = false;
                return;
            }

            // الرابط يحفظ المندوب واليوم عند التحديث — وتعذُّرُه لا يوقف العرض
            try {
                const url = new URL(cfg.pageUrl, window.location.origin);
                if (state.employeeId !== cfg.authId) url.searchParams.set('employee_id', state.employeeId);
                url.searchParams.set('date', state.date);
                window.history.replaceState(null, '', url);
            } catch (error) {
                // أصلٌ مختلف (نسخة معاينة) — لا شيء
            }

            if (!keepSelection || !selectedStop()) state.selectedId = defaultSelection();
            render();
        }

        function defaultSelection() {
            const stops = state.day.stops;
            const pick = stops.find((s) => s.state === 'active')
                || stops.find((s) => s.state === 'next')
                || stops.find((s) => s.state === 'late')
                || stops.find((s) => s.state === 'planned')
                || stops[stops.length - 1];
            return pick ? pick.id : null;
        }

        // ═══════════════ العرض ═══════════════

        function render() {
            const day = state.day;

            $('dr-title').textContent = day.is_self ? 'خط سيري اليومي' : `خط سير ${day.employee || ''}`;
            $('dr-date-label').textContent = day.date_label;
            $('dr-stat-done').textContent = `${day.stats.done} من ${day.stats.total}`;
            $('dr-stat-distance').textContent = fmtKm(day.stats.km);
            $('dr-stat-time').textContent = fmtMinutes(day.stats.minutes);
            $('dr-stat-percent').textContent = `${day.stats.percent}%`;
            $('dr-ring').style.setProperty('--p', day.stats.percent);
            $('dr-list-count').textContent = day.stops.length;
            $('dr-add-open').hidden = !day.can.add;

            renderList();
            renderTable();
            renderMap();
            renderNext();
        }

        function renderList() {
            const stops = state.day.stops;
            $('dr-stops-empty').hidden = stops.length > 0;

            $('dr-stops').innerHTML = stops.map((stop, index) => `
                <li class="dr-stop dr-stop--${tone(stop.state)} ${stop.id === state.selectedId ? 'is-selected' : ''}" data-id="${stop.id}">
                    <span class="dr-stop__num">${stop.state === 'done' ? '<i class="fas fa-check"></i>' : index + 1}</span>
                    <div class="dr-stop__body">
                        <span class="dr-stop__name" title="${esc(stop.name)}">${esc(stop.name)}</span>
                        <span class="dr-stop__line"><i class="far fa-clock"></i> <bdi dir="ltr">${esc(stop.start)} - ${esc(stop.end)}</bdi></span>
                        ${stop.place ? `<span class="dr-stop__line"><i class="fas fa-map-marker-alt"></i> ${esc(stop.place)}</span>` : ''}
                        ${stop.added_by ? `<span class="dr-by dr-by--${byTone(stop.added_by)}" title="${esc(byLabel(stop.added_by))}"><i class="fas fa-user-plus"></i> ${esc(byLabel(stop.added_by))}</span>` : ''}
                    </div>
                    <div class="dr-stop__aside">
                        <span class="dr-state dr-state--${tone(stop.state)}">${STATES[stop.state] || ''}</span>
                    </div>
                </li>
            `).join('');
        }

        function renderTable() {
            $('dr-table-body').innerHTML = state.day.stops.map((stop, index) => `
                <tr data-id="${stop.id}" class="${stop.id === state.selectedId ? 'is-selected' : ''}">
                    <td>${index + 1}</td>
                    <td><strong>${esc(stop.name)}</strong><small>${esc(stop.code)}</small></td>
                    <td><bdi dir="ltr">${esc(stop.start)} - ${esc(stop.end)}</bdi></td>
                    <td>${esc(stop.place || '—')}</td>
                    <td><span class="dr-state dr-state--${tone(stop.state)}">${STATES[stop.state] || ''}</span></td>
                    <td>${stop.added_by ? `<span class="dr-by dr-by--${byTone(stop.added_by)}">${esc(byLabel(stop.added_by))}</span>` : '<small>الخطّة الأسبوعية</small>'}</td>
                </tr>
            `).join('') || '<tr><td colspan="6" class="dr-empty">لا زيارات في خط سير هذا اليوم.</td></tr>';
        }

        // ═══════════════ الخريطة ═══════════════

        function ensureMap() {
            if (state.map) return true;

            if (!window.L) {
                $('dr-map-notice').textContent = 'تعذّر تحميل الخريطة — تحقّق من الاتصال بالإنترنت.';
                $('dr-map-notice').hidden = false;
                return false;
            }

            state.map = L.map('dr-map', { zoomControl: false }).setView([24.7136, 46.6753], 11);
            L.control.zoom({ position: 'topleft' }).addTo(state.map);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(state.map);
            state.layer = L.layerGroup().addTo(state.map);
            return true;
        }

        function pinIcon(stop, number) {
            const by = stop.added_by ? `<i class="dr-pin__by dr-pin__by--${byTone(stop.added_by)}"></i>` : '';
            const label = stop.state === 'done' ? '<i class="fas fa-check"></i>' : number;
            return L.divIcon({
                className: '',
                html: `<span class="dr-pin dr-pin--${tone(stop.state)} ${stop.id === state.selectedId ? 'is-selected' : ''}">${label}${by}</span>`,
                iconSize: [32, 32],
                iconAnchor: [16, 16],
            });
        }

        function renderMap() {
            if (!ensureMap()) return;

            const stops = state.day.stops;
            const onMap = stops.filter((stop) => located(stop) && stop.state !== 'cancelled');
            const unlocated = stops.filter((stop) => !located(stop)).length;

            state.layer.clearLayers();
            state.markers.clear();
            $('dr-map-notice').hidden = true;
            $('dr-unlocated').hidden = unlocated === 0;
            $('dr-unlocated').textContent = `${unlocated} ${unlocated === 1 ? 'عميل' : 'عملاء'} بلا موقع على الخريطة`;

            if (onMap.length > 1) {
                const points = onMap.map((stop) => [stop.lat, stop.lng]);
                const pivot = onMap.findIndex((stop) => stop.state === 'active' || stop.state === 'next');
                const cut = pivot === -1 ? (onMap.every((s) => s.state === 'done') ? points.length : 1) : pivot + 1;

                if (cut > 1) L.polyline(points.slice(0, cut), { color: '#2563eb', weight: 5, opacity: .9 }).addTo(state.layer);
                if (points.length - cut + 1 > 1) L.polyline(points.slice(Math.max(cut - 1, 0)), { color: '#2563eb', weight: 4, opacity: .5, dashArray: '8 8' }).addTo(state.layer);
            }

            stops.forEach((stop) => {
                if (!located(stop)) return;

                const marker = L.marker([stop.lat, stop.lng], { icon: pinIcon(stop, stopNumber(stop)), zIndexOffset: stop.id === state.selectedId ? 1000 : 0 })
                    .bindTooltip(esc(stop.name), { permanent: true, direction: 'right', offset: [16, 0], className: 'dr-pin-label' })
                    .on('click', () => select(stop.id, false))
                    .addTo(state.layer);

                state.markers.set(stop.id, marker);
            });

            if (onMap.length === 0) {
                if (stops.length > 0) {
                    $('dr-map-notice').textContent = 'لا يوجد موقع مسجَّل لأيٍّ من عملاء هذا اليوم.';
                    $('dr-map-notice').hidden = false;
                }
                return;
            }

            state.map.fitBounds(L.latLngBounds(onMap.map((stop) => [stop.lat, stop.lng])), { padding: [60, 60], maxZoom: 15 });
        }

        function refreshMarkers() {
            state.markers.forEach((marker, id) => {
                const stop = state.day.stops.find((s) => s.id === id);
                if (stop) {
                    marker.setIcon(pinIcon(stop, stopNumber(stop)));
                    marker.setZIndexOffset(id === state.selectedId ? 1000 : 0);
                }
            });
        }

        // ═══════════════ العميل المختار ═══════════════

        function renderNext() {
            const stop = selectedStop();
            const can = state.day.can;

            $('dr-next-body').hidden = !stop;
            $('dr-next-empty').hidden = !!stop;
            $('dr-next-seq').hidden = !stop;

            if (!stop) {
                $('dr-next-title').textContent = 'العميل التالي';
                return;
            }

            $('dr-next-title').textContent = stop.state === 'next' ? 'العميل التالي' : (stop.state === 'active' ? 'الزيارة الجارية' : 'تفاصيل الزيارة');
            $('dr-next-seq').textContent = stopNumber(stop);
            $('dr-next-name').textContent = stop.name;
            $('dr-next-code').textContent = stop.code || '';
            $('dr-next-code').hidden = !stop.code;

            const status = stop.client_status;
            $('dr-next-status').hidden = !status;
            if (status) {
                $('dr-next-status').textContent = status.name;
                $('dr-next-status').style.color = status.color || '';
            }

            $('dr-next-place').hidden = !stop.place && !stop.address;
            $('dr-next-place').querySelector('span').textContent = stop.place || stop.address;
            $('dr-next-time').querySelector('bdi').textContent = `${stop.start} - ${stop.end}`;
            $('dr-next-leg').hidden = stop.leg_km === null;
            $('dr-next-leg').querySelector('span').textContent = stop.leg_km === null ? '' : `${fmtKm(stop.leg_km)} (${fmtMinutes(stop.leg_minutes)}) من الزيارة السابقة`;

            // صورةُ العميل من ملفه أوّلاً، ثمّ آخرُ صورةِ زيارة، ثمّ أيقونة المتجر
            const photo = stop.client_photo || stop.photos[0];
            $('dr-next-photo').innerHTML = photo
                ? `<a href="${esc(photo)}" target="_blank" rel="noopener"><img src="${esc(photo)}" alt="${esc(stop.name)}"></a>`
                : '<i class="fas fa-store"></i>';

            $('dr-next-by').hidden = !stop.added_by;
            if (stop.added_by) {
                $('dr-next-by').className = `dr-by dr-by--${byTone(stop.added_by)}`;
                $('dr-next-by').innerHTML = `<i class="fas fa-user-plus"></i> ${esc(byLabel(stop.added_by))}`;
            }

            $('dr-act-details').href = withId(cfg.showUrl, stop.client_id);

            const directions = $('dr-act-directions');
            directions.classList.toggle('is-disabled', !located(stop));
            directions.href = located(stop) ? `https://www.google.com/maps/dir/?api=1&destination=${stop.lat},${stop.lng}` : '#';

            const call = $('dr-act-call');
            call.classList.toggle('is-disabled', !stop.phone);
            call.href = stop.phone ? `tel:${stop.phone}` : '#';

            const open = ['next', 'planned', 'late', 'active'].includes(stop.state);
            $('dr-act-start').hidden = !(can.start && open);
            $('dr-act-start').href = withId(cfg.openUrl, stop.client_id);
            $('dr-act-start').querySelector('span').textContent = stop.state === 'active' ? 'متابعة الزيارة' : 'بدء الزيارة';

            $('dr-next-note').innerHTML = stop.note
                ? `${esc(stop.note.text)}<small>${esc(stop.note.date || '')}</small>`
                : '<span class="dr-help">لا ملاحظات مسجَّلة لهذا العميل.</span>';

            state.galleryStart = 0;
            renderGallery();
        }

        function renderGallery() {
            const stop = selectedStop();
            const photos = stop ? stop.photos : [];
            const start = Math.min(state.galleryStart, Math.max(0, photos.length - 3));

            $('dr-gallery').innerHTML = photos.length
                ? photos.slice(start, start + 3).map((src) => `<a href="${esc(src)}" target="_blank" rel="noopener"><img src="${esc(src)}" alt="" loading="lazy"></a>`).join('')
                : '<span class="dr-gallery__empty">لا صور سابقة لهذا العميل.</span>';

            $('dr-gallery-prev').disabled = start === 0;
            $('dr-gallery-next').disabled = start + 3 >= photos.length;
        }

        function select(id, pan) {
            state.selectedId = id;
            document.querySelectorAll('#dr-stops .dr-stop, #dr-table-body tr').forEach((el) => el.classList.toggle('is-selected', Number(el.dataset.id) === id));
            refreshMarkers();
            renderNext();

            const stop = selectedStop();
            if (pan && state.map && located(stop)) state.map.panTo([stop.lat, stop.lng]);

            const item = document.querySelector(`#dr-stops .dr-stop[data-id="${id}"]`);
            if (item) item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        // قائمة «⋮» على بطاقة الزيارة حُذفت بطلب المستخدم (2026-09-15): «تفاصيل العميل»
        // و«الخريطة» باقيان في لوحة العميل المختار، والنقر على البطاقة يختاره على الخريطة.

        // ═══════════════ إضافة عميل للمسار ═══════════════

        let searchTimer = null;
        let pickerRequest = 0;

        function openAdd() {
            state.picked.clear();
            $('dr-add-q').value = '';
            $('dr-add-target').innerHTML = state.day.is_self
                ? `يُضاف العميل إلى آخر خطّ سيرك ليوم <strong>${esc(state.day.date_label)}</strong>.`
                : `يُضاف العميل إلى آخر خطّ سير <strong>${esc(state.day.employee)}</strong> ليوم <strong>${esc(state.day.date_label)}</strong>، ويظهر عنده أنّك من أضافه.`;
            updatePicked();
            $('dr-add-modal').hidden = false;
            loadPicker();
            setTimeout(() => $('dr-add-q').focus(), 50);
        }

        function closeAdd() {
            $('dr-add-modal').hidden = true;
        }

        async function loadPicker() {
            const requestId = ++pickerRequest;
            const list = $('dr-add-list');
            list.innerHTML = '<div class="dr-empty"><i class="fas fa-spinner fa-spin"></i><p>جارٍ التحميل…</p></div>';
            $('dr-add-note').hidden = true;

            try {
                const data = await getJson(cfg.clientsUrl, {
                    employee_id: state.employeeId,
                    date: state.date,
                    node_id: $('dr-add-node').value,
                    q: $('dr-add-q').value.trim(),
                });

                if (requestId !== pickerRequest) return;

                list.innerHTML = data.clients.length
                    ? data.clients.map((client) => `
                        <label class="dr-pick ${client.in_route ? 'is-in-route' : ''}">
                            <input type="checkbox" value="${client.id}" ${client.in_route ? 'disabled' : ''} ${state.picked.has(client.id) ? 'checked' : ''}>
                            <span class="dr-pick__body">
                                <span class="dr-pick__name">${esc(client.name)}</span>
                                <span class="dr-pick__meta">${esc([client.code, client.place].filter(Boolean).join(' · '))}</span>
                            </span>
                            ${client.in_route ? '<span class="dr-state dr-state--next">في المسار</span>' : ''}
                        </label>
                    `).join('')
                    : '<div class="dr-empty"><i class="fas fa-user-slash"></i><p>لا عملاء مطابقون فيما يتبعك.</p></div>';

                if (data.total > data.limit) {
                    $('dr-add-note').textContent = `يُعرض أوّل ${data.limit} من ${data.total} عميل — ضيّق بالبحث أو باختيار «تابع لـ».`;
                    $('dr-add-note').hidden = false;
                }
            } catch (error) {
                if (requestId === pickerRequest) list.innerHTML = `<div class="dr-empty"><p>${esc(error.message)}</p></div>`;
            }
        }

        function updatePicked() {
            $('dr-add-count').textContent = state.picked.size;
            $('dr-add-submit').disabled = state.picked.size === 0;
        }

        async function submitAdd() {
            const button = $('dr-add-submit');
            button.disabled = true;

            try {
                const result = await postJson(cfg.storeUrl, {
                    employee_id: state.employeeId,
                    date: state.date,
                    client_ids: Array.from(state.picked),
                });
                closeAdd();
                notify('success', result.message);
                load(true);
            } catch (error) {
                const nothingAdded = error.body && 'added' in error.body;
                notify(nothingAdded ? 'warning' : 'error', error.message);
                if (nothingAdded) loadPicker();
            } finally {
                updatePicked();
            }
        }

        // ═══════════════ الأحداث ═══════════════

        $('dr-stops').addEventListener('click', (event) => {
            const item = event.target.closest('.dr-stop');
            if (!item) return;

            select(Number(item.dataset.id), true);
        });

        $('dr-table-body').addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-id]');
            if (row) select(Number(row.dataset.id), true);
        });

        function switchView(view) {
            state.view = view;
            document.querySelectorAll('#daily-route .dr-tab').forEach((tab) => tab.classList.toggle('is-active', tab.dataset.view === view));
            $('dr-view-map').hidden = view !== 'map';
            $('dr-view-table').hidden = view !== 'table';
            if (view === 'map' && state.map) setTimeout(() => state.map.invalidateSize(), 50);
        }

        document.querySelectorAll('#daily-route .dr-tab').forEach((tab) => tab.addEventListener('click', () => switchView(tab.dataset.view)));

        $('dr-fullscreen').addEventListener('click', () => {
            const target = $('dr-view-map');
            if (document.fullscreenElement) document.exitFullscreen();
            else if (target.requestFullscreen) target.requestFullscreen();
        });
        document.addEventListener('fullscreenchange', () => state.map && setTimeout(() => state.map.invalidateSize(), 100));

        $('dr-locate').addEventListener('click', () => {
            if (!navigator.geolocation || !ensureMap()) return notify('info', 'تحديد الموقع غير متاح في هذا المتصفح.');

            navigator.geolocation.getCurrentPosition((position) => {
                const point = [position.coords.latitude, position.coords.longitude];
                if (state.me) state.me.setLatLng(point);
                else state.me = L.marker(point, { icon: L.divIcon({ className: '', html: '<span class="dr-me"></span>', iconSize: [16, 16], iconAnchor: [8, 8] }) }).bindTooltip('موقعي').addTo(state.map);
                state.map.setView(point, Math.max(state.map.getZoom(), 14));
            }, () => notify('warning', 'تعذّر تحديد موقعك — اسمح للمتصفح بالوصول إلى الموقع.'), { enableHighAccuracy: true, timeout: 10000 });
        });

        $('dr-gallery-prev').addEventListener('click', () => { state.galleryStart = Math.max(0, state.galleryStart - 3); renderGallery(); });
        $('dr-gallery-next').addEventListener('click', () => { state.galleryStart += 3; renderGallery(); });

        const employeeSelect = $('dr-employee');
        if (employeeSelect) {
            employeeSelect.addEventListener('change', () => {
                state.employeeId = Number(employeeSelect.value);
                load(false);
            });
        }

        $('dr-date').addEventListener('change', () => {
            if (!$('dr-date').value) return;
            state.date = $('dr-date').value;
            load(false);
        });

        $('dr-add-open').addEventListener('click', openAdd);
        $('dr-add-modal').addEventListener('click', (event) => {
            if (event.target === $('dr-add-modal') || event.target.closest('[data-close]')) closeAdd();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !$('dr-add-modal').hidden) closeAdd();
        });
        $('dr-add-node').addEventListener('change', loadPicker);
        $('dr-add-q').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadPicker, 300);
        });
        $('dr-add-list').addEventListener('change', (event) => {
            const box = event.target.closest('input[type="checkbox"]');
            if (!box) return;
            const id = Number(box.value);
            if (box.checked) state.picked.add(id);
            else state.picked.delete(id);
            updatePicked();
        });
        $('dr-add-submit').addEventListener('click', submitAdd);

        load(false);
    })();
</script>
