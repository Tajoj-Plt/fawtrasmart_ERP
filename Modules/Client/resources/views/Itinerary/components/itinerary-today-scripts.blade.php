@php
    $todayMapClients = collect($clients)->values()->map(fn ($client, $index) => [
        'number' => $index + 1,
        'name' => $client['name'],
        'code' => $client['code'],
        'lat' => $client['lat'],
        'lng' => $client['lng'],
        'state' => $client['state'],
    ])->all();
    $todayConfig = [
        'employeeId' => $employee?->id,
        'date' => $date->toDateString(),
        'isToday' => $date->isToday(),
        'clients' => $todayMapClients,
        'mapDataUrl' => route('itinerary.map-data'),
        'trackingEnabled' => $trackingEnabled,
        'mapsConfigured' => $mapsKey !== '',
    ];
@endphp
<script>
    window.ITINERARY_TODAY = @json($todayConfig);
</script>
<script>
    (function () {
        'use strict';

        const cfg = window.ITINERARY_TODAY;
        const $ = (id) => document.getElementById(id);
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const STATE_COLOR = { completed: '#16a34a', started: '#d97706', missed: '#e11d48', cancelled: '#94a3b8', planned: '#6366f1' };
        const STATE_LABEL = { completed: 'مكتملة', started: 'جارية', missed: 'فائتة', cancelled: 'ملغاة', planned: 'مخطَّطة' };

        // النموذج: الحقول الفارغة لا تُرسل
        const form = $('rb-today-form');
        if (form) {
            form.addEventListener('submit', () => {
                form.querySelectorAll('input, select').forEach((field) => {
                    if (!field.name || !field.value) field.disabled = true;
                });
            });
        }

        if (!cfg.employeeId) return;

        // إخفاء/إظهار ملاحظات عميل
        document.querySelectorAll('#route-builder [data-toggle-notes]').forEach((button) => button.addEventListener('click', () => {
            const box = $(`rb-notes-${button.dataset.toggleNotes}`);
            if (!box) return;
            box.hidden = !box.hidden;
            button.textContent = box.hidden ? 'إظهار' : 'إخفاء';
        }));

        $('rb-print')?.addEventListener('click', () => window.print());

        // ═══════════════ الخريطة والتتبّع ═══════════════

        const mapState = { ready: false, gmap: null, clientOverlays: [], liveOverlays: [], drawn: false, timer: null };
        const located = (client) => client.lat !== null && client.lat !== undefined && client.lng !== null && client.lng !== undefined;

        function mapNotice(html) {
            mapState.ready = false;
            $('rb-map').hidden = true;
            $('rb-map-notice').hidden = false;
            $('rb-map-notice').innerHTML = html;
        }

        function initMap() {
            if (mapState.gmap || $('rb-map').hidden) return;
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

        function drawClients() {
            const points = cfg.clients.filter(located);
            const link = $('rb-open-gmaps');
            if (points.length) {
                const ll = (point) => `${point.lat},${point.lng}`;
                const params = new URLSearchParams({ api: '1', travelmode: 'driving', destination: ll(points[points.length - 1]) });
                if (points.length > 1) params.set('origin', ll(points[0]));
                const middle = points.slice(1, -1).slice(0, 9);
                if (middle.length) params.set('waypoints', middle.map(ll).join('|'));
                link.href = `https://www.google.com/maps/dir/?${params}`;
                link.classList.remove('is-disabled');
            }

            if (!mapState.ready || mapState.drawn) return;
            mapState.drawn = true;

            const bounds = new google.maps.LatLngBounds();
            points.forEach((client) => {
                const position = { lat: Number(client.lat), lng: Number(client.lng) };
                bounds.extend(position);
                const marker = new google.maps.Marker({
                    map: mapState.gmap,
                    position,
                    title: `${client.name} — ${STATE_LABEL[client.state] || client.state}`,
                    label: { text: String(client.number), color: '#fff', fontWeight: '700', fontSize: '12px' },
                    icon: { path: google.maps.SymbolPath.CIRCLE, scale: 13, fillColor: STATE_COLOR[client.state] || '#6366f1', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
                });
                const info = new google.maps.InfoWindow({ content: `<div dir="rtl" style="font-family:inherit"><strong>${esc(client.name)}</strong><br><small>${esc(client.code || '')} · ${esc(STATE_LABEL[client.state] || '')}</small></div>` });
                marker.addListener('click', () => info.open(mapState.gmap, marker));
                mapState.clientOverlays.push(marker);
            });

            if (points.length > 1) mapState.gmap.fitBounds(bounds, 48);
            else if (points.length === 1) { mapState.gmap.setCenter(bounds.getCenter()); mapState.gmap.setZoom(14); }
        }

        /** آخرُ موقعٍ للمندوب ومسارُه في اليوم — من `itinerary.map-data` (يحرسه نطاقُ الدور وإعدادُ التتبّع) */
        async function refreshLive() {
            if (!cfg.trackingEnabled) return;
            const live = $('rb-live');

            try {
                const url = new URL(cfg.mapDataUrl, window.location.origin);
                url.searchParams.set('employee_id', cfg.employeeId);
                url.searchParams.set('date', cfg.date);
                url.searchParams.set('live_only', '1');
                const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const body = await response.json().catch(() => ({}));
                if (!response.ok || !body.success) throw new Error(body.message || `HTTP ${response.status}`);

                const location = body.employee_location;
                if (live) {
                    live.classList.toggle('is-live', !!location?.is_live);
                    live.classList.toggle('is-stale', !!location && !location.is_live);
                    live.querySelector('span').textContent = location
                        ? `${location.is_live ? 'متصل الآن' : 'آخر موقع'} — ${location.updated_at_formatted}`
                        : 'لا موقع مسجَّل للمندوب بعد.';
                }
                $('rb-map-updated').textContent = `آخر تحديث: ${new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;

                if (!mapState.ready) return;
                mapState.liveOverlays.forEach((overlay) => overlay.setMap(null));
                mapState.liveOverlays = [];

                const path = (body.path_coords || []).map((point) => ({ lat: Number(point.lat ?? point.latitude), lng: Number(point.lng ?? point.longitude) }));
                if (path.length > 1) {
                    mapState.liveOverlays.push(new google.maps.Polyline({
                        map: mapState.gmap,
                        path,
                        strokeColor: '#1d4ed8',
                        strokeWeight: 4,
                        strokeOpacity: 0.75,
                        icons: [{ icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 2, strokeColor: '#1d4ed8' }, offset: '100%', repeat: '80px' }],
                    }));
                }
                if (location) {
                    mapState.liveOverlays.push(new google.maps.Marker({
                        map: mapState.gmap,
                        position: { lat: Number(location.latitude), lng: Number(location.longitude) },
                        title: `${location.employee_name} — ${location.updated_at_formatted}`,
                        zIndex: 999,
                        icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 7, fillColor: '#e11d48', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
                    }));
                }
            } catch (error) {
                if (live) live.querySelector('span').textContent = `تعذّر جلب الموقع: ${error.message}`;
            }
        }

        document.querySelectorAll('#route-builder .rb-tab').forEach((tab) => tab.addEventListener('click', () => {
            document.querySelectorAll('#route-builder .rb-tab').forEach((item) => item.classList.toggle('is-active', item === tab));
            $('rb-today-body').dataset.view = tab.dataset.view;
            if (tab.dataset.view === 'map') {
                initMap();
                drawClients();
                refreshLive();
            }
        }));

        $('rb-map-refresh')?.addEventListener('click', refreshLive);

        drawClients();
        refreshLive();

        // تحديثُ الموقع كلَّ دقيقة ما دام اليومُ اليومَ والصفحةُ ظاهرة
        if (cfg.trackingEnabled && cfg.isToday) {
            mapState.timer = setInterval(() => { if (!document.hidden) refreshLive(); }, 60000);
        }
    })();
</script>
