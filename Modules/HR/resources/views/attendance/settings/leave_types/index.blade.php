@extends('hr::master')

@section('title', 'أنواع الإجازات')

@section('page-actions')
    @permission('leave_types.create')
        <a href="{{ route('leave_types.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span class="d-none d-md-inline">إضافة نوع إجازة</span>
        </a>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'أنواع الإجازات',
        'icon' => 'log-out',
        'sub' => 'تعريف أنواع الإجازات المتاحة في النظام (سنوية، مرضية، طارئة...)',
        'crumbs' => ['الرئيسية' => url('/'), 'الحضور' => route('hr.attendance.hub'), 'أنواع الإجازات' => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    {{-- ══════════ إرشادُ الشاشة ══════════ --}}
    @include('hr::components.guide', [
        'key' => 'hr-leave-types.index',
        'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
        'lead' => 'هذه أنواعُ الإجازات. والنوعُ يقرّر أمرين: أرصيدٌ له أم لا، وأمدفوعةٌ هو أم يُنقص الراتب. فهو مرجعُ كلّ طلب إجازة.',
        'blocks' => [
            'ما يترتّب على النوع' => [
                'كلُّ طلب إجازةٍ يُنسَب إلى نوع، ومن النوع يُعرَف من أيّ رصيدٍ يُخصَم.',
                'وكونُه مدفوعاً أو غيرَ مدفوع هو ما يفرّق بين إجازةٍ لا أثرَ لها في الراتب وأخرى تُنقصه.',
                'وسياساتُ الإجازات وأرصدةُ الموظفين تُبنى على الأنواع — فالنوعُ أصلٌ لهما لا العكس.',
            ],
            'التعديلُ والحذف' => [
                'تغييرُ خصائص النوع يسري على ما يُحتسَب بعده؛ والإجازاتُ المعتمَدةُ سلفاً تبقى على ما كانت.',
                'وحذفُ نوعٍ مستعمَلٍ يترك طلباتٍ وأرصدةً تشير إلى نوعٍ معدوم — فلا تحذفه ما دام في الطلبات أثرٌ منه.',
            ],
        ],
        'facts' => [
            'النوعُ يقرّر الرصيدَ وأثرَ الإجازة في الراتب',
            'حذفُ نوعٍ مستعمَلٍ يقطع مرجعَ طلباتٍ قائمة',
        ],
    ])

    @php
        $hasFilters = request()->filled('keywords');
        $canShow = hasPermission('leave_types.show');
        $canEdit = hasPermission('leave_types.edit');
        $canDestroy = hasPermission('leave_types.destroy');
    @endphp

    @permission('leave_types.index')
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if ($hasFilters)
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('leave_types.index') }}">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="keywords">نوع الإجازة</label>
                                <input type="text" id="keywords" name="keywords" class="form-control" placeholder="ابحث بالاسم أو الرقم…" value="{{ request('keywords') }}">
                            </div>
                        </div>
                    </div>
                    @if (request()->filled('per_page'))
                        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                    @endif

                    <div class="stk-filters__foot">
                        <a href="{{ route('leave_types.index') }}" class="btn btn-outline-secondary">
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
        @if ($leave_types->count() > 0)
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-list"></i> الأنواع المعرّفة
                    <span class="stk-count">{{ $leave_types->total() }}</span>
                </h6>
                @include('stock::components.per-page', [
                    'id' => 'leaveTypesPerPage',
                    'current' => request('per_page', 10),
                    'onchange' => "window.location.href='".url()->current().'?'.http_build_query(request()->except(['per_page', 'page']))."&per_page='+this.value",
                ])
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>نوع الإجازة</th>
                            <th class="text-center">الحد السنوي</th>
                            <th class="text-center">الأيام المتتالية القصوى</th>
                            <th class="text-center">الاعتماد</th>
                            <th class="text-center" style="width:70px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leave_types as $index => $type)
                            @php $color = $type->color ?: 'var(--app-primary)'; @endphp
                            <tr id="leave-type-{{ $type->id }}">
                                <td class="stk-num text-muted">{{ $leave_types->firstItem() + $index }}</td>
                                <td>
                                    <div class="stk-media">
                                        <span class="stk-media__thumb" style="background: color-mix(in srgb, {{ $color }} 14%, transparent); color: {{ $color }};"><i class="feather icon-calendar"></i></span>
                                        <span class="stk-media__body">
                                            @if ($canShow)
                                                <a href="{{ route('leave_types.show', $type->id) }}" class="stk-media__title">{{ $type->name }}</a>
                                            @else
                                                <span class="stk-media__title">{{ $type->name }}</span>
                                            @endif
                                            <span class="stk-media__sub">رقم {{ $type->id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center stk-num">{{ $type->max_days_per_year !== null ? $type->max_days_per_year.' يوم' : 'بلا حد' }}</td>
                                <td class="text-center stk-num">{{ $type->max_consecutive_days ?? '—' }}</td>
                                <td class="text-center">
                                    @if ($type->requires_approval ?? false)
                                        <span class="stk-state stk-state--on">يحتاج اعتماد</span>
                                    @else
                                        <span class="stk-state stk-state--off">تلقائي</span>
                                    @endif
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
                                                    <a class="dropdown-item" href="{{ route('leave_types.show', $type->id) }}">
                                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                                    </a>
                                                @endif
                                                @if ($canEdit)
                                                    <a class="dropdown-item" href="{{ route('leave_types.edit', $type->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                    </a>
                                                @endif
                                                @if ($canDestroy)
                                                    @if ($canShow || $canEdit)
                                                        <div class="dropdown-divider"></div>
                                                    @endif
                                                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $type->id }}">
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

            @if ($leave_types->hasPages())
                <div class="stk-table-foot">
                    <p class="stk-table-foot__count">عرض {{ $leave_types->firstItem() }}–{{ $leave_types->lastItem() }} من {{ $leave_types->total() }}</p>
                    {{ $leave_types->links() }}
                </div>
            @endif
        @else
            <div class="card-body">
                <div class="stk-empty">
                    <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                    <h5 class="stk-empty__title">لا توجد أنواع إجازات</h5>
                    <p class="stk-empty__hint">
                        @if ($hasFilters)
                            لم يطابق البحث أي نوع — جرّب كلمة أخرى
                        @else
                            ابدأ بإضافة الأنواع المتاحة في منشأتك (سنوية، مرضية، ...)
                        @endif
                    </p>
                    @if ($hasFilters)
                        <a href="{{ route('leave_types.index') }}" class="btn btn-outline-secondary"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</a>
                    @elseif (hasPermission('leave_types.create'))
                        <a href="{{ route('leave_types.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة نوع إجازة</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($canDestroy)
        {{-- نوافذ الحذف خارج الجدول: وضعُها داخل <tbody> ينتهك بنيته --}}
        @foreach ($leave_types as $type)
            <div class="modal fade" id="modal_DELETE{{ $type->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content text-right">
                        <div class="modal-header">
                            <h5 class="modal-title">حذف نوع الإجازة</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-1">سيُحذف نوع الإجازة <strong>{{ $type->name }}</strong>.</p>
                            <p class="stk-empty__hint mb-0">لا تحذف نوعاً له طلبات أو أرصدة قائمة؛ ستبقى تشير إلى نوعٍ غير موجود.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" data-bs-dismiss="modal">إلغاء</button>
                            <form method="POST" action="{{ route('leave_types.delete', $type->id) }}" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> تأكيد الحذف</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
@include('hr::components.table-menu')
@endpush
