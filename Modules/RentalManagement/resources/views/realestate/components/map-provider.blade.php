{{--
    مزوّد الخرائط: خرائط Google إن حُمّلت وقُبل مفتاحها، وإلا OpenStreetMap (Leaflet) بلا مفتاح.

    `reMap.init(function (api) {…})` — تُستدعى الدالّة بالمزوّد المتاح، وتُستدعى **مرّةً ثانية**
    إن رفضت Google المفتاح بعد التحميل (الرفض يصل متأخراً)، فعليها أن تبني من الصفر:
    `api.map()` يفرّغ العنصر، وأحداث jQuery تُربط بنطاق `.remap` بعد `off`.

    الواجهة موحّدة والنقاط دائماً `{lat, lng}`:
      api.map(el, {center, zoom}) → { marker, onClick, setView, panTo, fit, closePopup, resize }
      map.marker(point|null, {draggable, title}) → { setPosition, onClick, onDragEnd, popup }
      api.geocode(point, cb({country, city, district, address}))
      api.search(inputEl, cb(point))
--}}
<script>
    window.reMap = (function () {
        const LEAFLET = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/';
        const inits = [];
        let provider = null;

        const plain = p => ({ lat: typeof p.lat === 'function' ? p.lat() : p.lat, lng: typeof p.lng === 'function' ? p.lng() : p.lng });

        function googleApi() {
            return {
                name: 'google',
                map: function (el, o) {
                    el.innerHTML = '';
                    const map = new google.maps.Map(el, { center: o.center, zoom: o.zoom, streetViewControl: false, mapTypeControl: true });
                    const info = new google.maps.InfoWindow();

                    return {
                        marker: function (point, mo) {
                            mo = mo || {};
                            const mk = new google.maps.Marker({ map: map, position: point, visible: !!point, draggable: !!mo.draggable, title: mo.title });
                            return {
                                setPosition: p => { mk.setPosition(p); mk.setVisible(true); },
                                onClick: cb => mk.addListener('click', () => cb()),
                                onDragEnd: cb => mk.addListener('dragend', e => cb(plain(e.latLng))),
                                popup: node => { info.setContent(node); info.open(map, mk); },
                            };
                        },
                        onClick: cb => map.addListener('click', e => cb(plain(e.latLng))),
                        setView: (p, zoom) => { map.setCenter(p); map.setZoom(zoom); },
                        panTo: (p, minZoom) => { map.panTo(p); if (map.getZoom() < minZoom) map.setZoom(minZoom); },
                        fit: function (points) {
                            const bounds = new google.maps.LatLngBounds();
                            points.forEach(p => bounds.extend(p));
                            map.fitBounds(bounds);
                        },
                        closePopup: () => info.close(),
                        resize: () => google.maps.event.trigger(map, 'resize'),
                    };
                },
                geocode: function (point, cb) {
                    new google.maps.Geocoder().geocode({ location: point, language: 'ar' }, function (results, status) {
                        if (status !== 'OK' || !results[0]) return;
                        const part = type => (results[0].address_components.find(c => c.types.includes(type)) || {}).long_name;
                        cb({ country: part('country'), city: part('locality') || part('administrative_area_level_2'),
                            district: part('sublocality') || part('neighborhood'), address: results[0].formatted_address });
                    });
                },
                search: function (input, cb) {
                    if (!google.maps.places) return;
                    const auto = new google.maps.places.Autocomplete(input, { fields: ['geometry'] });
                    auto.addListener('place_changed', function () {
                        const found = auto.getPlace();
                        if (found.geometry && found.geometry.location) cb(plain(found.geometry.location));
                    });
                },
            };
        }

        function leafletApi() {
            // fetch لا ‎$.ajax: ترويسة CSRF العامة في ‎$.ajaxSetup تُفشل طلباً عابراً للنطاق
            const nominatim = (path, params) => fetch('https://nominatim.openstreetmap.org/' + path + '?'
                + new URLSearchParams(Object.assign({ format: 'jsonv2', 'accept-language': 'ar' }, params))).then(r => r.json());

            return {
                name: 'leaflet',
                map: function (el, o) {
                    if (el._reLeaflet) el._reLeaflet.remove();
                    el.innerHTML = '';
                    const map = el._reLeaflet = L.map(el).setView([o.center.lat, o.center.lng], o.zoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);

                    return {
                        marker: function (point, mo) {
                            mo = mo || {};
                            const mk = L.marker(point ? [point.lat, point.lng] : [0, 0], { draggable: !!mo.draggable, title: mo.title || '' });
                            if (point) mk.addTo(map);
                            return {
                                setPosition: p => { mk.setLatLng([p.lat, p.lng]); if (!map.hasLayer(mk)) mk.addTo(map); },
                                onClick: cb => mk.on('click', () => cb()),
                                onDragEnd: cb => mk.on('dragend', () => cb(plain(mk.getLatLng()))),
                                popup: node => mk.unbindPopup().bindPopup(node).openPopup(),
                            };
                        },
                        onClick: cb => map.on('click', e => cb(plain(e.latlng))),
                        setView: (p, zoom) => map.setView([p.lat, p.lng], zoom),
                        panTo: (p, minZoom) => map.setView([p.lat, p.lng], Math.max(map.getZoom(), minZoom)),
                        fit: points => map.fitBounds(points.map(p => [p.lat, p.lng]), { padding: [30, 30] }),
                        closePopup: () => map.closePopup(),
                        resize: () => map.invalidateSize(),
                    };
                },
                geocode: function (point, cb) {
                    nominatim('reverse', { lat: point.lat, lon: point.lng }).then(function (r) {
                        const a = (r && r.address) || {};
                        cb({ country: a.country, city: a.city || a.town || a.village || a.county,
                            district: a.suburb || a.neighbourhood || a.quarter, address: r && r.display_name });
                    }).catch(() => {});
                },
                // بلا إكمالٍ تلقائي: Enter يبحث ويذهب إلى أول نتيجة
                search: function (input, cb) {
                    // Google تعطّل الحقل وتلوّنه عند رفض المفتاح: يُستبدل بنسخةٍ نظيفة
                    if (input.classList.contains('gm-err-autocomplete') || input.disabled) {
                        const fresh = input.cloneNode(false);
                        fresh.classList.remove('gm-err-autocomplete', 'pac-target-input');
                        fresh.disabled = false;
                        fresh.style.backgroundImage = '';
                        fresh.value = '';
                        input.replaceWith(fresh);
                        input = fresh;
                    }

                    $(input).attr('placeholder', 'اكتب اسم الحيّ أو الشارع ثم اضغط Enter…').off('keydown.remap').on('keydown.remap', function (e) {
                        if (e.key !== 'Enter' || !this.value.trim()) return;
                        nominatim('search', { q: this.value.trim(), limit: 1 }).then(function (rows) {
                            if (rows && rows[0]) cb({ lat: parseFloat(rows[0].lat), lng: parseFloat(rows[0].lon) });
                        }).catch(() => {});
                    });
                },
            };
        }

        function run(fn) {
            fn.ok(provider === 'google' ? googleApi() : leafletApi());
        }

        function useLeaflet() {
            provider = 'leaflet';
            if (window.L && L.map) return inits.forEach(run);

            $('<link rel="stylesheet">').attr('href', LEAFLET + 'leaflet.min.css').appendTo('head');
            // بلاطات Leaflet تُحسب يساراً-يميناً: اتجاه الصفحة RTL يترك فواصل بينها
            $('<style>.leaflet-container{direction:ltr}.leaflet-container img{max-width:none!important}</style>').appendTo('head');
            const script = document.createElement('script');
            script.src = LEAFLET + 'leaflet.min.js';
            script.onload = () => inits.forEach(run);
            script.onerror = () => inits.forEach(fn => fn.fail && fn.fail());
            document.head.appendChild(script);
        }

        // مفتاحٌ مرفوض (نطاق غير مصرّح به، فوترة موقوفة…): تستدعي Google هذه الدالّة بعد التحميل
        window.gm_authFailure = function () {
            window.google = null;
            if (provider !== 'leaflet') useLeaflet();
        };

        return {
            init: function (ok, fail) {
                const fn = { ok: ok, fail: fail };
                inits.push(fn);

                $(function () {
                    if (provider === 'leaflet') return window.L && L.map ? run(fn) : null; // قيد التحميل: سيُشغَّل عند اكتماله
                    if (window.google && google.maps) { provider = 'google'; return run(fn); }
                    useLeaflet();
                });
            },
        };
    })();
</script>
