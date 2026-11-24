{{--
    اختيارُ مكانٍ على الخريطة — بدل كتابة خطّ العرض والطول بالأرقام.

    كان نموذجُ «أماكن التسجيل» يطلب من المستخدم أن يفتح خرائط جوجل ويضغط
    مطوّلاً وينسخ رقمين بسبع خانات عشرية. هنا يختار المكانَ بلمسة، أو يبحث
    باسمه، أو يأخذ موقعَه الحالي، ويرى دائرةَ النطاق تتغيّر مع نصف القطر.

    الخريطة Leaflet + OpenStreetMap: لا تحتاج مفتاحاً — مفتاحُ جوجل غيرُ
    مضبوطٍ في كل نسخة، والخريطةُ بلا مفتاحٍ تظهر رمادية.

    @include('hr::attendance._partials.map-picker', [
        'id' => 'gf_map',                 // معرّفٌ فريد للصفحة
        'lat' => $geofence->latitude ?? null,
        'lng' => $geofence->longitude ?? null,
        'latName' => 'latitude',          // أسماء الحقول المرسَلة
        'lngName' => 'longitude',
        'radiusInput' => '#gf_radius',    // (اختياري) حقلُ نصف القطر بالأمتار
    ])
--}}
@php
    $id = $id ?? 'map_'.uniqid();
    $latName = $latName ?? 'latitude';
    $lngName = $lngName ?? 'longitude';
    $lat = old($latName, $lat ?? null);
    $lng = old($lngName, $lng ?? null);
@endphp

@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .mp { border: 1px solid var(--app-border, #e5e7eb); border-radius: var(--app-radius, 8px); overflow: hidden; }
        .mp__bar { display: flex; flex-wrap: wrap; gap: .5rem; padding: .6rem; background: var(--app-body-bg, #f9fafb); border-bottom: 1px solid var(--app-border, #e5e7eb); }
        .mp__bar input[type=search] { flex: 1 1 220px; min-width: 0; }
        /* Leaflet يرسم البلاطات بإزاحاتٍ من اليسار — في صفحةٍ RTL تنزاح خارج الإطار فتظهر الخريطة رمادية */
        .mp__map { height: 320px; z-index: 0; direction: ltr; }
        .mp__foot { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .5rem .75rem; font-size: .8rem; color: var(--app-muted, #6b7280); }
        .mp__coords { direction: ltr; font-family: monospace; color: var(--app-text, #1f2937); }
        .mp__results { list-style: none; margin: 0; padding: 0; max-height: 180px; overflow: auto; border-top: 1px solid var(--app-border, #e5e7eb); }
        .mp__results li { padding: .45rem .75rem; cursor: pointer; font-size: .82rem; border-bottom: 1px solid var(--app-border, #f0f0f0); }
        .mp__results li:hover { background: var(--app-primary-soft, #eef2ff); }
    </style>
@endonce

<div class="mp" id="{{ $id }}">
    <div class="mp__bar">
        <input type="search" class="form-control form-control-sm mp__q" placeholder="ابحث باسم المكان أو الحي أو المدينة…">
        <button type="button" class="btn btn-sm btn-outline-primary mp__search"><i class="feather icon-search"></i> بحث</button>
        <button type="button" class="btn btn-sm btn-outline-secondary mp__me"><i class="feather icon-crosshair"></i> موقعي الحالي</button>
    </div>
    <ul class="mp__results" hidden></ul>
    <div class="mp__map"></div>
    <div class="mp__foot">
        <span><i class="feather icon-info"></i> اضغط على الخريطة لتحديد المكان، أو اسحب الدبوس.</span>
        <span>الإحداثيات: <span class="mp__coords">{{ $lat && $lng ? round((float) $lat, 6).', '.round((float) $lng, 6) : 'لم يُحدَّد' }}</span></span>
        <button type="button" class="btn btn-link btn-sm p-0 mp__clear" @if (! ($lat && $lng)) hidden @endif>مسح المكان</button>
    </div>
    <input type="hidden" name="{{ $latName }}" class="mp__lat" value="{{ $lat }}">
    <input type="hidden" name="{{ $lngName }}" class="mp__lng" value="{{ $lng }}">
</div>

<script>
(function () {
    const root = document.getElementById(@json($id));
    if (!root || !window.L) { return; }

    const latInput = root.querySelector('.mp__lat');
    const lngInput = root.querySelector('.mp__lng');
    const coords = root.querySelector('.mp__coords');
    const clearBtn = root.querySelector('.mp__clear');
    const results = root.querySelector('.mp__results');
    const radiusInput = @json($radiusInput ?? null) ? document.querySelector(@json($radiusInput ?? null)) : null;

    const hasPoint = latInput.value !== '' && lngInput.value !== '';
    const start = hasPoint ? [parseFloat(latInput.value), parseFloat(lngInput.value)] : [24.7136, 46.6753]; // الرياض

    const map = L.map(root.querySelector('.mp__map')).setView(start, hasPoint ? 16 : 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    let marker = null;
    let circle = null;

    const radius = () => Math.max(0, parseInt(radiusInput ? radiusInput.value : 0, 10) || 0);

    function drawCircle() {
        if (!marker || !radius()) {
            if (circle) { map.removeLayer(circle); circle = null; }
            return;
        }
        if (!circle) {
            circle = L.circle(marker.getLatLng(), { radius: radius(), color: 'var(--app-primary, #6366f1)', weight: 2, fillOpacity: .12 }).addTo(map);
        } else {
            circle.setLatLng(marker.getLatLng()).setRadius(radius());
        }
    }

    function place(latlng, zoom) {
        const lat = Math.round(latlng.lat * 1e7) / 1e7;
        const lng = Math.round(latlng.lng * 1e7) / 1e7;
        latInput.value = lat;
        lngInput.value = lng;
        coords.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        clearBtn.hidden = false;

        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => place(marker.getLatLng()));
        } else {
            marker.setLatLng([lat, lng]);
        }
        drawCircle();
        if (zoom) { map.setView([lat, lng], zoom); }
    }

    if (hasPoint) { place({ lat: start[0], lng: start[1] }); }

    map.on('click', (e) => place(e.latlng));
    if (radiusInput) { radiusInput.addEventListener('input', drawCircle); }

    clearBtn.addEventListener('click', () => {
        latInput.value = ''; lngInput.value = '';
        coords.textContent = 'لم يُحدَّد';
        clearBtn.hidden = true;
        if (marker) { map.removeLayer(marker); marker = null; }
        drawCircle();
    });

    root.querySelector('.mp__me').addEventListener('click', () => {
        if (!navigator.geolocation) { alert('المتصفح لا يدعم تحديد الموقع.'); return; }
        navigator.geolocation.getCurrentPosition(
            (pos) => place({ lat: pos.coords.latitude, lng: pos.coords.longitude }, 17),
            () => alert('تعذّر الوصول إلى موقعك — اسمح للمتصفح بالموقع، أو اختر المكان من الخريطة.'),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    const search = () => {
        const q = root.querySelector('.mp__q').value.trim();
        if (!q) { return; }
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=6&accept-language=ar&q=' + encodeURIComponent(q))
            .then((r) => r.json())
            .then((items) => {
                results.innerHTML = '';
                if (!items.length) {
                    results.innerHTML = '<li>لا نتائج — جرّب اسماً آخر أو اختر من الخريطة.</li>';
                }
                items.forEach((it) => {
                    const li = document.createElement('li');
                    li.textContent = it.display_name;
                    li.addEventListener('click', () => {
                        place({ lat: parseFloat(it.lat), lng: parseFloat(it.lon) }, 17);
                        results.hidden = true;
                    });
                    results.appendChild(li);
                });
                results.hidden = false;
            })
            .catch(() => { results.innerHTML = '<li>تعذّر البحث — اختر المكان من الخريطة.</li>'; results.hidden = false; });
    };
    root.querySelector('.mp__search').addEventListener('click', search);
    root.querySelector('.mp__q').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); search(); } });

    // خريطةٌ داخل عنصرٍ كان مخفياً (تبويب، نافذة) تحتاج إعادة قياس عند ظهوره
    new ResizeObserver(() => map.invalidateSize()).observe(root);
})();
</script>
