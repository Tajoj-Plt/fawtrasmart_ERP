@extends('hr::master')

@section('title', 'محددات الحضور')

@section('page-actions')
    @permission('attendance_rules.create')
        <a href="{{ route('attendance_determinants.create') }}" class="btn btn-primary">
            <i class="feather icon-plus"></i>
            <span class="d-none d-md-inline">أضف محدد حضور</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'محددات الحضور',
        'icon' => 'user-check',
        'sub' => 'شروط التحقق من الحضور (IP / الموقع الجغرافي / الصور)',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'محددات الحضور' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-attendance-determinants.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه محدِّداتُ الحضور: قيودٌ تضبط أين ومتى وكيف يُقبَل تسجيلُ الحضور — لا كم يُحسَم منه.',
        'blocks' => [
            'ما يُحدَّد' => [
                'المحدِّدُ يقرّر شروطَ قبول البصمة: موضعُها، ووقتُها، ووسيلتُها. فما خالف الشرطَ لا يُسجَّل حضوراً.',
                'ويقبل التخصيصَ بفرعٍ أو قسمٍ أو مسمّىً — فقيدٌ يُكتب لفرعٍ لا يقيّد غيرَه.',
            ],
            'ما يترتّب على التشديد' => [
                'تشديدُ محدِّدٍ يمنع تسجيلاتٍ كانت تُقبَل، فيظهر موظفون غائبين وهم على رأس العمل — والغيابُ يُحسَم.',
                'فراجعْ من ينطبق عليه المحدِّدُ قبل تفعيله، لا بعد أن يظهر أثرُه في كشف الرواتب.',
            ],
        ],
        'facts' => [
            'المحدِّدُ يقبل البصمةَ أو يردّها',
            'تشديدُه قد يقلب حاضرين غائبين',
        ],
    ])

    @php
        $hasFilters = collect(request()->only(['keywords', 'status']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
        $canShow = hasPermission('attendance_rules.show');
        $canEdit = hasPermission('attendance_rules.edit');
        $canDestroy = hasPermission('attendance_rules.destroy');
    @endphp

    <div class="card stk-filters">
        <div class="card-body">
            <div class="stk-filters__head">
                <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                @if ($hasFilters)
                    <span class="stk-count">فلاتر مفعّلة</span>
                @endif
            </div>

            <form id="searchForm" action="{{ route('attendance_determinants.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="keywords">المحدد</label>
                            <input type="text" class="form-control" id="keywords" name="keywords"
                                   value="{{ request('keywords') }}" placeholder="ابحث باسم محدد الحضور…">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">الكل</option>
                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>نشط</option>
                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>غير نشط</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="stk-filters__foot">
                    <a href="{{ route('attendance_determinants.index') }}" id="resetFilter" class="btn btn-outline-secondary">
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
        @if ($attendance_determinants->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة المحددات
                    <span class="stk-count">{{ $attendance_determinants->total() }}</span>
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>المحدد</th>
                            <th class="text-center">التحقق من الموقع</th>
                            <th class="text-center">التحقق من IP</th>
                            <th class="text-center">التقاط الصورة</th>
                            <th class="text-center">الحالة</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attendance_determinants as $index => $determinant)
                            @php $isActive = (int) $determinant->status === 0; @endphp
                            <tr>
                                <td class="stk-num text-muted">{{ $attendance_determinants->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-user-check"></i></span>
                                        <span class="stk-media__body">
                                            @if ($canShow)
                                                <a href="{{ route('attendance_determinants.show', $determinant->id) }}" class="stk-media__title">{{ $determinant->name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $determinant->name }}</span>
                                            @endif
                                            <span class="stk-media__sub">{{ $determinant->created_at?->format('Y-m-d') }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $determinant->enable_location_verification ? 'on' : 'off' }}">{{ $determinant->enable_location_verification ? 'مفعل' : 'معطل' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $determinant->enable_ip_verification ? 'on' : 'off' }}">{{ $determinant->enable_ip_verification ? 'مفعل' : 'معطل' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $determinant->capture_employee_image ? 'on' : 'off' }}">{{ $determinant->capture_employee_image ? 'مفعل' : 'معطل' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">{{ $isActive ? 'نشط' : 'غير نشط' }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($canShow || $canEdit || $canDestroy)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" data-bs-display="static"
                                                aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                @if ($canShow)
                                                    <a class="dropdown-item" href="{{ route('attendance_determinants.show', $determinant->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                @endif
                                                @if ($canEdit)
                                                    <a class="dropdown-item" href="{{ route('attendance_determinants.edit', $determinant->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                    </a>
                                                @endif
                                                @if ($canDestroy)
                                                    @if ($canShow || $canEdit)
                                                        <div class="dropdown-divider"></div>
                                                    @endif
                                                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $determinant->id }}">
                                                        <i class="fa fa-trash me-2"></i>حذف
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($attendance_determinants->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $attendance_determinants->firstItem() }}–{{ $attendance_determinants->lastItem() }} من {{ $attendance_determinants->total() }}</p>
                    {{ $attendance_determinants->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-user-check"></i></div>
                    <h5 class="stk-empty__title">لا توجد محددات حضور</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أي محدد — جرّب معايير أوسع
                        @else
                            المحدِّد يقيّد تسجيل الحضور بموقعٍ أو شبكةٍ أو صورة.
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('attendance_determinants.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('attendance_rules.create'))
                        <a href="{{ route('attendance_determinants.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> أضف محدد حضور</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($canDestroy)
        @foreach ($attendance_determinants as $determinant)
            @include('hr::organizational_structure.partials._delete_modal', [
                'modalId' => 'modal_DELETE'.$determinant->id,
                'heading' => 'حذف محدد الحضور',
                'action' => route('attendance_determinants.destroy', $determinant->id),
                'itemName' => $determinant->name,
                'subject' => 'محدد الحضور',
                'usage' => [],
            ])
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
