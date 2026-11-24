@extends('hr::master')

@section('title')
نوع الإجازة
@stop

@section('page-actions')
    @permission('leave_types.edit')
        <a href="{{ route('leave_types.edit', $leaveType->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل</span>
        </a>
    @endpermission
    @permission('leave_types.destroy')
        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modal_DELETE{{ $leaveType->id }}">
            <i class="feather icon-trash-2"></i> <span class="d-none d-md-inline">حذف</span>
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $leaveType->name,
        'icon' => 'log-out',
        'crumbs' => ['الرئيسية' => url('/'), 'أنواع الإجازات' => route('leave_types.index'), $leaveType->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true" style="background: {{ $leaveType->color }}; color: #fff">
                        <i class="feather icon-log-out"></i>
                    </span>
                    <h4 class="mb-25">{{ $leaveType->name }}</h4>
                    <p class="stk-help mt-0 mb-1">نوع إجازة رقم #{{ $leaveType->id }}</p>
                    <span class="stk-state stk-state--{{ $leaveType->requires_approval == 1 ? 'on' : 'off' }}">{{ $leaveType->requires_approval == 1 ? 'يحتاج إذن' : 'لا يحتاج إذن' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">الحد الأقصى خلال العام</span>
                        <span class="stk-metric__value">{{ $leaveType->max_days_per_year }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">الأيام المتوالية</span>
                        <span class="stk-metric__value">{{ $leaveType->max_consecutive_days ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════ التفاصيل والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab"
                               aria-controls="details" aria-selected="true">
                                <i class="feather icon-file-text"></i> التفاصيل
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activity-log-tab" data-toggle="tab" href="#activity-log" role="tab"
                               aria-controls="activity-log" aria-selected="false">
                                <i class="feather icon-activity"></i> سجل النشاطات
                                <span class="stk-count">{{ $logs ? $logs->flatten()->count() : 0 }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>الحد الأقصى للأيام خلال العام</dt>
                                    <dd class="stk-num">{{ $leaveType->max_days_per_year }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الحد الأقصى للأيام المتوالية القابلة للتطبيق</dt>
                                    <dd class="stk-num">{{ $leaveType->max_consecutive_days ?? '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>قابلة للتطبيق بعد</dt>
                                    <dd class="stk-num">{{ $leaveType->applicable_after ?? '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>اللون</dt>
                                    <dd>
                                        <span style="background-color: {{ $leaveType->color }}; padding: 2px 8px; border-radius: 3px; display: inline-block; color: white; direction: ltr">
                                            {{ $leaveType->color }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>يحتاج إذن</dt>
                                    <dd>
                                        <span class="stk-state stk-state--{{ $leaveType->requires_approval == 1 ? 'on' : 'off' }}">{{ $leaveType->requires_approval == 1 ? 'نعم' : 'لا' }}</span>
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>استبدال أيام عطلة نهاية الأسبوع بالإجازة</dt>
                                    <dd>
                                        <span class="stk-state stk-state--{{ $leaveType->replace_weekends == 1 ? 'on' : 'off' }}">{{ $leaveType->replace_weekends == 1 ? 'نعم' : 'لا' }}</span>
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الوصف</dt>
                                    <dd>{{ $leaveType->description ?: '—' }}</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>أُنشئ / آخر تعديل</dt>
                                    <dd class="stk-num">{{ optional($leaveType->created_at)->format('Y-m-d H:i') ?? '—' }} / {{ optional($leaveType->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="activity-log" role="tabpanel" aria-labelledby="activity-log-tab">
                        <div class="card-body">
                            @include('hr::organizational_structure.partials._activity', ['logs' => $logs ?? collect()])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @permission('leave_types.destroy')
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$leaveType->id,
            'heading' => 'حذف '.$leaveType->name,
            'action' => route('leave_types.delete', $leaveType->id),
            'itemName' => $leaveType->name,
            'subject' => 'نوع الإجازة',
        ])
    @endpermission
</div>
@endsection
