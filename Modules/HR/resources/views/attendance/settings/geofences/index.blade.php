@extends('hr::master')

@section('title', 'أماكن التسجيل')

@section('page-actions')
    @permission('attendance_places.create')
        <a href="{{ route('attendance.settings.geofences.create') }}" class="btn btn-primary">
            <i class="feather icon-plus"></i>
            <span class="d-none d-md-inline">إضافة مكان</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أماكن التسجيل',
        'icon' => 'map-pin',
        'sub' => 'مكان كل فرع على الخريطة — لا يُقبل تسجيل الحضور من الجوال إلا من داخل دائرته.',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'أماكن التسجيل' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'devices'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'كل الأماكن', 'value' => $stats['total'], 'icon' => 'map-pin', 'tone' => 'primary'],
        ['label' => 'أماكن مفعّلة', 'value' => $stats['active'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'أجهزة التسجيل', 'value' => $stats['kiosks'], 'icon' => 'tablet', 'tone' => 'info'],
        ['label' => 'تسجيلات اليوم في الأماكن', 'value' => $stats['today'], 'icon' => 'log-in', 'tone' => 'muted'],
    ]])

    @php
        $hasFilters = collect(request()->only(['name', 'status']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
        $canEdit = hasPermission('attendance_places.edit');
        $canDestroy = hasPermission('attendance_places.destroy');
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form action="{{ route('attendance.settings.geofences.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="name">المكان</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ request('name') }}" placeholder="ابحث باسم المكان…">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" class="form-control hr-s2">
                                <option value="">الكل</option>
                                <option value="active" @selected(request('status') === 'active')>مفعّل</option>
                                <option value="inactive" @selected(request('status') === 'inactive')>موقوف</option>
                            </select>
                        </div>
                    </div>
                </div>
                @if (request()->filled('per_page'))
                    <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                @endif

                <div class="stk-filters__foot">
                    <a href="{{ route('attendance.settings.geofences.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card stk-table-card">
        @if ($geofences->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة الأماكن
                    <span class="stk-count">{{ $geofences->total() }}</span>
                </h6>
                @include('stock::components.per-page', [
                    'id' => 'geofencesPerPage',
                    'current' => request('per_page', 10),
                    'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
                ])
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>المكان</th>
                            <th>الفرع</th>
                            <th class="text-center">نصف القطر</th>
                            <th class="text-center">الشرط</th>
                            <th class="text-center">الأجهزة</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($geofences as $index => $geofence)
                            <tr>
                                <td class="stk-num text-muted">{{ $geofences->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-map-pin"></i></span>
                                        <span class="stk-media__body">
                                            <a href="{{ route('attendance.settings.geofences.show', $geofence->id) }}" class="stk-media__title">{{ $geofence->name }}</a>
                                            <span class="stk-media__sub">
                                                {{ $geofence->hasCircle() ? 'محدّد على الخريطة' : 'لم يُحدَّد على الخريطة' }}
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $geofence->branch->name ?? '—' }}</td>
                                <td class="text-center stk-num">{{ (int) $geofence->radius_meters }} م</td>
                                <td class="text-center">
                                    @if ($geofence->require_geo && $geofence->require_network)
                                        داخل الدائرة + شبكة المكتب
                                    @elseif ($geofence->require_geo)
                                        داخل الدائرة
                                    @elseif ($geofence->require_network)
                                        شبكة المكتب
                                    @else
                                        <span class="text-muted">بلا شرط</span>
                                    @endif
                                </td>
                                <td class="text-center stk-num">{{ (int) $geofence->kiosks_count }}</td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $geofence->is_active ? 'on' : 'off' }}">{{ $geofence->is_active ? 'مفعّل' : 'موقوف' }}</span>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" data-bs-display="static"
                                            aria-expanded="false" aria-label="خيارات">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('attendance.settings.geofences.show', $geofence->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                            @if ($canEdit)
                                                <a class="dropdown-item" href="{{ route('attendance.settings.geofences.edit', $geofence->id) }}">
                                                    <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                </a>
                                            @endif
                                            @if ($canDestroy)
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $geofence->id }}">
                                                    <i class="fa fa-trash me-2"></i>حذف
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($geofences->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $geofences->firstItem() }}–{{ $geofences->lastItem() }} من {{ $geofences->total() }}</p>
                    {{ $geofences->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-map-pin"></i></div>
                    <h5 class="stk-empty__title">لا توجد أماكن</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أي مكان — جرّب معايير أوسع
                        @else
                            حدّد مكان الفرع على الخريطة ليُقبل التسجيل من داخله فقط. بلا مكان، يُقبل التسجيل من أي مكان.
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('attendance.settings.geofences.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('attendance_places.create'))
                        <a href="{{ route('attendance.settings.geofences.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة مكان</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($canDestroy)
        @foreach ($geofences as $geofence)
            @include('hr::organizational_structure.partials._delete_modal', [
                'modalId' => 'modal_DELETE'.$geofence->id,
                'heading' => 'حذف المكان',
                'action' => route('attendance.settings.geofences.delete', $geofence->id),
                'itemName' => $geofence->name,
                'subject' => 'المكان',
                'usage' => $geofence->kiosks_count > 0 ? ['أجهزة في هذا المكان' => $geofence->kiosks_count] : [],
            ])
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
