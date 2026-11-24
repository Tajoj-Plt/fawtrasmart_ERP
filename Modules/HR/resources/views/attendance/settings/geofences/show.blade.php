@extends('hr::master')

@section('title', $geofence->name)

@section('page-actions')
    @permission('attendance_places.edit')
        <a href="{{ route('attendance.settings.geofences.edit', $geofence->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل</span>
        </a>
    @endpermission
    @permission('attendance_places.destroy')
        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modal_DELETE{{ $geofence->id }}">
            <i class="feather icon-trash-2"></i> <span class="d-none d-md-inline">حذف</span>
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $geofence->name,
        'icon' => 'map-pin',
        'sub' => 'مكان تسجيل الحضور',
        'crumbs' => ['الرئيسية' => url('/'), 'أماكن التسجيل' => route('attendance.settings.geofences.index'), $geofence->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-map-pin"></i></span>
                    <h4 class="mb-25">{{ $geofence->name }}</h4>
                    <p class="stk-help mt-0 mb-1">{{ $geofence->branch->name ?? 'بلا فرع' }}</p>
                    <span class="stk-state stk-state--{{ $geofence->is_active ? 'on' : 'off' }}">{{ $geofence->is_active ? 'مفعّل' : 'موقوف' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">مقبول اليوم</span>
                        <span class="stk-metric__value">{{ $today['accepted'] }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">مرفوض اليوم</span>
                        <span class="stk-metric__value">{{ $today['rejected'] }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>نصف القطر</dt>
                            <dd class="stk-num">{{ (int) $geofence->radius_meters }} متر</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أقل دقة مقبولة للجوال</dt>
                            <dd class="stk-num">{{ (int) $geofence->max_accuracy_meters }} متر</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>داخل الدائرة</dt>
                            <dd>{{ $geofence->require_geo ? 'مشروط' : 'غير مشروط' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>شبكة المكتب</dt>
                            <dd>
                                {{ $geofence->require_network ? 'مشروطة' : 'غير مشروطة' }}
                                @if ($geofence->allowed_ip_ranges || $geofence->allowed_wifi_ssids)
                                    <div class="stk-help" style="direction:ltr;text-align:right">
                                        {{ implode('، ', array_merge((array) $geofence->allowed_ip_ranges, (array) $geofence->allowed_wifi_ssids)) }}
                                    </div>
                                @endif
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>ملاحظات</dt>
                            <dd>{{ $geofence->notes ?: '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أُنشئ / آخر تعديل</dt>
                            <dd class="stk-num">{{ optional($geofence->created_at)->format('Y-m-d H:i') ?? '—' }} / {{ optional($geofence->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-map"></i> على الخريطة</h4></div>
                @if ($geofence->hasCircle())
                    <div id="gf_show_map" style="height:340px;direction:ltr;z-index:0"></div>
                @else
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-map"></i></div>
                            <h5 class="stk-empty__title">لم يُحدَّد المكان على الخريطة</h5>
                            <p class="stk-empty__hint">بلا موقعٍ على الخريطة لا يُفحص مكان الجوال — حدّده من «تعديل».</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-tablet"></i> أجهزة التسجيل في هذا المكان
                        <span class="stk-count">{{ $geofence->kiosks->count() }}</span>
                    </h6>
                    @permission('attendance_places.create')
                        <a href="{{ route('attendance.settings.kiosks.create', ['geofence_id' => $geofence->id]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="feather icon-plus"></i> إضافة جهاز
                        </a>
                    @endpermission
                </div>
                @if ($geofence->kiosks->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>الجهاز</th>
                                    <th>آخر استخدام</th>
                                    <th class="text-center">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($geofence->kiosks as $kiosk)
                                    <tr>
                                        <td><a href="{{ route('attendance.settings.kiosks.show', $kiosk->id) }}">{{ $kiosk->name }}</a></td>
                                        <td>{{ $kiosk->last_seen_at ? $kiosk->last_seen_at->diffForHumans() : 'لم يُربط بعد' }}</td>
                                        <td class="text-center"><span class="stk-state stk-state--{{ $kiosk->is_active ? 'on' : 'off' }}">{{ $kiosk->is_active ? 'مفعّل' : 'موقوف' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body"><p class="stk-help mb-0">لا توجد أجهزة في هذا المكان. الموظفون يسجّلون فيه من جوالاتهم فقط.</p></div>
                @endif
            </div>

            @include('hr::attendance.settings._partials.recent-punches', ['punches' => $punches, 'showKiosk' => true])
        </div>
    </div>

    @permission('attendance_places.destroy')
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$geofence->id,
            'heading' => 'حذف المكان',
            'action' => route('attendance.settings.geofences.delete', $geofence->id),
            'itemName' => $geofence->name,
            'subject' => 'المكان',
            'usage' => $geofence->kiosks->count() > 0 ? ['أجهزة في هذا المكان' => $geofence->kiosks->count()] : [],
        ])
    @endpermission
</div>
@endsection

@if ($geofence->hasCircle())
@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const el = document.getElementById('gf_show_map');
    if (!el || !window.L) { return; }
    const center = [{{ (float) $geofence->latitude }}, {{ (float) $geofence->longitude }}];
    const map = L.map(el, { scrollWheelZoom: false }).setView(center, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    L.marker(center).addTo(map);
    const circle = L.circle(center, { radius: {{ (int) $geofence->radius_meters }}, weight: 2, fillOpacity: .12 }).addTo(map);
    map.fitBounds(circle.getBounds(), { maxZoom: 17 });
    new ResizeObserver(() => map.invalidateSize()).observe(el);
})();
</script>
@endpush
@endif
