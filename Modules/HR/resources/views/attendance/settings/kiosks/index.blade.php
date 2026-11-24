@extends('hr::master')

@section('title', 'أجهزة التسجيل')

@section('page-actions')
    @permission('attendance_places.create')
        <a href="{{ route('attendance.settings.kiosks.create') }}" class="btn btn-primary">
            <i class="feather icon-plus"></i>
            <span class="d-none d-md-inline">إضافة جهاز</span>
        </a>
    @endpermission
    @if (hasPermission('attendance_registration.kiosk'))
        <a href="{{ route('barcode.scan') }}" target="_blank" class="btn btn-outline-secondary">
            <i class="feather icon-external-link"></i>
            <span class="d-none d-md-inline">فتح شاشة التسجيل</span>
        </a>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أجهزة التسجيل',
        'icon' => 'tablet',
        'sub' => 'تابلت أو حاسوب ثابت في الفرع، يمسح عليه الموظفون بطاقاتهم للحضور والانصراف.',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'أجهزة التسجيل' => null],
    ])

    @include('hr::attendance._partials.section-tabs', ['group' => 'devices'])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @include('hr::organizational_structure.partials._stats', ['statItems' => [
        ['label' => 'كل الأجهزة', 'value' => $stats['total'], 'icon' => 'tablet', 'tone' => 'primary'],
        ['label' => 'أجهزة مفعّلة', 'value' => $stats['active'], 'icon' => 'check-circle', 'tone' => 'success'],
        ['label' => 'أجهزة مربوطة', 'value' => $stats['linked'], 'icon' => 'link', 'tone' => 'info'],
        ['label' => 'لم تعمل آخر 3 ساعات', 'value' => $stats['silent'], 'icon' => 'alert-circle', 'tone' => 'muted'],
    ]])

    @php
        $hasFilters = collect(request()->only(['name', 'geofence_id', 'status']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
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

            <form action="{{ route('attendance.settings.kiosks.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="name">الجهاز</label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ request('name') }}" placeholder="ابحث باسم الجهاز…">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="geofence_id">المكان</label>
                            <select id="geofence_id" name="geofence_id" class="form-control hr-s2">
                                <option value="">الكل</option>
                                @foreach ($geofences as $geofence)
                                    <option value="{{ $geofence->id }}" @selected((string) request('geofence_id') === (string) $geofence->id)>{{ $geofence->name }}</option>
                                @endforeach
                            </select>
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
                    <a href="{{ route('attendance.settings.kiosks.index') }}" class="btn btn-outline-secondary">
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
        @if ($kiosks->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة الأجهزة
                    <span class="stk-count">{{ $kiosks->total() }}</span>
                </h6>
                @include('stock::components.per-page', [
                    'id' => 'kiosksPerPage',
                    'current' => request('per_page', 10),
                    'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
                ])
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>الجهاز</th>
                            <th>المكان</th>
                            <th class="text-center">رمز الربط</th>
                            <th>آخر استخدام</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kiosks as $index => $kiosk)
                            <tr>
                                <td class="stk-num text-muted">{{ $kiosks->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-tablet"></i></span>
                                        <span class="stk-media__body">
                                            <a href="{{ route('attendance.settings.kiosks.show', $kiosk->id) }}" class="stk-media__title">{{ $kiosk->name }}</a>
                                            <span class="stk-media__sub">{{ $kiosk->branch->name ?? 'بلا فرع' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $kiosk->geofence->name ?? '—' }}</td>
                                <td class="text-center"><code class="stk-num" style="letter-spacing:2px;font-weight:700">{{ $kiosk->pairing_code }}</code></td>
                                <td>
                                    @if ($kiosk->last_seen_at)
                                        <span title="{{ $kiosk->last_seen_at->format('Y-m-d H:i') }}">{{ $kiosk->last_seen_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-muted">لم يُربط بعد</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $kiosk->is_active ? 'on' : 'off' }}">{{ $kiosk->is_active ? 'مفعّل' : 'موقوف' }}</span>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" data-bs-display="static"
                                            aria-expanded="false" aria-label="خيارات">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{ route('attendance.settings.kiosks.show', $kiosk->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                            @if ($canEdit)
                                                <a class="dropdown-item" href="{{ route('attendance.settings.kiosks.edit', $kiosk->id) }}">
                                                    <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                </a>
                                            @endif
                                            @if ($canDestroy)
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $kiosk->id }}">
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

            @if ($kiosks->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $kiosks->firstItem() }}–{{ $kiosks->lastItem() }} من {{ $kiosks->total() }}</p>
                    {{ $kiosks->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-tablet"></i></div>
                    <h5 class="stk-empty__title">لا توجد أجهزة</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أي جهاز — جرّب معايير أوسع
                        @else
                            أضف جهاز الفرع، ثم أدخل رمز الربط عليه مرة واحدة ليبدأ الموظفون التسجيل ببطاقاتهم.
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('attendance.settings.kiosks.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('attendance_places.create'))
                        <a href="{{ route('attendance.settings.kiosks.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة جهاز</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($canDestroy)
        @foreach ($kiosks as $kiosk)
            @include('hr::organizational_structure.partials._delete_modal', [
                'modalId' => 'modal_DELETE'.$kiosk->id,
                'heading' => 'حذف الجهاز',
                'action' => route('attendance.settings.kiosks.delete', $kiosk->id),
                'itemName' => $kiosk->name,
                'subject' => 'الجهاز',
                'usage' => [],
            ])
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
