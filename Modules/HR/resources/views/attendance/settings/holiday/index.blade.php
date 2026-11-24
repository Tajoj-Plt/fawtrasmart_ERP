@extends('hr::master')

@section('title', 'قوائم العطلات')

@section('page-actions')
    @permission('holidays.create')
        <a href="{{ route('holiday_lists.create') }}" class="btn btn-primary">
            <i class="feather icon-plus"></i>
            <span class="d-none d-md-inline">إضافة قائمة جديدة</span>
        </a>
    @endpermission
@endsection

@push('styles')
<style>
    /* شاراتُ أنواع التكرار — أربع حالات، فلا تكفيها stk-state */
    .hl-type-pill { display:inline-block; padding:3px 10px; border-radius:10px; font-size:.7rem; font-weight:600; white-space:nowrap; }
    .hl-type-pill.once    { background: var(--app-info-soft, #dbeafe); color: var(--app-info, #1e3a8a); }
    .hl-type-pill.weekly  { background: var(--app-success-soft, #d1fae5); color: var(--app-success, #065f46); }
    .hl-type-pill.monthly { background: var(--app-warning-soft, #fef3c7); color: var(--app-warning, #92400e); }
    .hl-type-pill.yearly  { background: var(--app-danger-soft, #fecaca); color: var(--app-danger, #991b1b); }
</style>
@endpush

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'قوائم العطلات والأعياد',
        'icon' => 'calendar',
        'sub' => 'إدارة العطل بكل أنواعها: مرة واحدة / أسبوعي / شهري / سنوي',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'قوائم العطلات' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-holidays.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه العُطَلُ الرسميّة. واليومُ المعطَّلُ لا يُطلَب فيه حضورٌ ولا يُحسَب غياباً — فهو يمنع حسماً كان سيقع.',
        'blocks' => [
            'ما يترتّب على العطلة' => [
                'اليومُ المدرَجُ عطلةً يخرج من حساب الغياب والتأخير، فلا يُحسَم على أحد.',
                'وتقبل العُطَلُ التخصيصَ بفرعٍ أو قسمٍ أو مسمّىً — فعطلةٌ تخصّ فرعاً لا تُعطّل غيره.',
            ],
            'ما يترتّب على الحذف أو الإغفال' => [
                'يومٌ عطلةٌ لم يُدرَج هنا يُعامَل يومَ دوامٍ عاديّاً: من لم يبصم فيه يظهر غائباً ويُحسَم عليه.',
                'وحذفُ عطلةٍ بعد احتساب أيامها لا يُعيد الحسابَ وحدَه — الأثرُ على ما يُحتسَب بعده.',
            ],
        ],
        'facts' => [
            'العطلةُ تمنع احتسابَ الغياب والتأخير',
            'عطلةٌ غيرُ مُدرَجةٍ تُحسَب غياباً ويُحسَم عليها',
        ],
    ])

    @php
        $hasFilters = request()->filled('keywords');
        $canShow = hasPermission('holidays.show');
        $canEdit = hasPermission('holidays.edit');
        $canDestroy = hasPermission('holidays.destroy');
    @endphp

    @permission('holidays.index')
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($hasFilters)
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('holiday_lists.index') }}">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="keywords">القائمة</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" placeholder="ابحث باسم القائمة…" value="{{ request('keywords') }}">
                            </div>
                        </div>
                    </div>
                    @if (request()->filled('per_page'))
                        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                    @endif

                    <div class="stk-filters__foot">
                        <a href="{{ route('holiday_lists.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endpermission

    <div class="card stk-table-card">
        @if ($holiday_lists->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> قائمة قوائم العطلات
                    <span class="stk-count">{{ $holiday_lists->total() }}</span>
                </h6>
                @include('stock::components.per-page', [
                    'id' => 'holidayListsPerPage',
                    'current' => request('per_page', 10),
                    'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
                ])
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>القائمة</th>
                            <th class="text-center">عدد العطل</th>
                            <th>أنواع العطل</th>
                            <th>تاريخ الإنشاء</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($holiday_lists as $index => $list)
                            @php
                                $holidays = $list->holidays ?? collect();
                                $typeCounts = [
                                    'once'    => $holidays->where('recurrence_type', 'once')->count(),
                                    'weekly'  => $holidays->where('recurrence_type', 'weekly')->count(),
                                    'monthly' => $holidays->where('recurrence_type', 'monthly')->count(),
                                    'yearly'  => $holidays->where('recurrence_type', 'yearly')->count(),
                                ];
                            @endphp
                            <tr>
                                <td class="stk-num text-muted">{{ $holiday_lists->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb"><i class="feather icon-calendar"></i></span>
                                        <span class="stk-media__body">
                                            @if ($canShow)
                                                <a href="{{ route('holiday_lists.show', $list->id) }}" class="stk-media__title">{{ $list->name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $list->name }}</span>
                                            @endif
                                            <span class="stk-media__sub">رقم القائمة #{{ $list->id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center stk-num">{{ $holidays->count() }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @if ($typeCounts['once'] > 0)
                                            <span class="hl-type-pill once"><i class="feather icon-flag"></i> ثابت ({{ $typeCounts['once'] }})</span>
                                        @endif
                                        @if ($typeCounts['weekly'] > 0)
                                            <span class="hl-type-pill weekly"><i class="feather icon-refresh-cw"></i> أسبوعي ({{ $typeCounts['weekly'] }})</span>
                                        @endif
                                        @if ($typeCounts['monthly'] > 0)
                                            <span class="hl-type-pill monthly"><i class="feather icon-calendar"></i> شهري ({{ $typeCounts['monthly'] }})</span>
                                        @endif
                                        @if ($typeCounts['yearly'] > 0)
                                            <span class="hl-type-pill yearly"><i class="feather icon-star"></i> سنوي ({{ $typeCounts['yearly'] }})</span>
                                        @endif
                                        @if ($holidays->count() === 0)
                                            <span class="text-muted small">لا توجد عطل بعد</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="stk-num">{{ $list->created_at?->format('Y-m-d') ?? '—' }}</td>
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
                                                    <a class="dropdown-item" href="{{ route('holiday_lists.show', $list->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                @endif
                                                @if ($canEdit)
                                                    <a class="dropdown-item" href="{{ route('holiday_lists.edit', $list->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                    </a>
                                                @endif
                                                @if ($canDestroy)
                                                    @if ($canShow || $canEdit)
                                                        <div class="dropdown-divider"></div>
                                                    @endif
                                                    <form method="POST" action="{{ route('holiday_lists.delete', $list->id) }}"
                                                        onsubmit="return confirm('حذف قائمة {{ addslashes($list->name) }} وكل عطلاتها؟');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fa fa-trash me-2"></i>حذف
                                                        </button>
                                                    </form>
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

            @if ($holiday_lists->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $holiday_lists->firstItem() }}–{{ $holiday_lists->lastItem() }} من {{ $holiday_lists->total() }}</p>
                    {{ $holiday_lists->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                    <h5 class="stk-empty__title">لا توجد قوائم عطلات</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أي قائمة — جرّب معايير أوسع
                        @else
                            ابدأ بإنشاء قائمة عطلات تغطّي أعياد السنة والعطل الأسبوعية والشهرية
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('holiday_lists.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('holidays.create'))
                        <a href="{{ route('holiday_lists.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إنشاء قائمة جديدة</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
