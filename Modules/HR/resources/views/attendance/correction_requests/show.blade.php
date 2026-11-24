@extends('hr::master')

@section('title', 'طلب تصحيح حضور')

@php
    $tone = ['pending' => 'warning', 'approved' => 'success'][$correction->status] ?? 'danger';
    $dateLabel = \Carbon\Carbon::parse($correction->attendance_date)->locale('ar')->translatedFormat('l, d/m/Y');
    $canCancel = $correction->status === 'pending' && (int) $correction->requested_by === (int) auth()->id() && hasPermission('attendance_corrections.create');
@endphp

@section('page-actions')
    @if ($canCancel)
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <form action="{{ route('hr.attendance-corrections.cancel', $correction->id) }}" method="POST"
                          onsubmit="return confirm('هل تريد إلغاء طلب التصحيح هذا؟');">
                        @csrf
                        @method('PUT')
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fa fa-times me-2"></i>إلغاء الطلب
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $correction->employee->full_name ?? '—',
        'icon' => 'edit-3',
        'crumbs' => ['الرئيسية' => url('/'), 'طلبات تصحيح الحضور' => route('hr.attendance-corrections.index'), 'طلب #'.$correction->id => null],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-edit-3"></i></span>
                    <h4 class="mb-25">{{ $correction->employee->full_name ?? '—' }}</h4>
                    <p class="stk-help mt-0 mb-1">{{ $dateLabel }}</p>
                    <span class="badge badge-{{ $tone }}">{{ $correction->statusLabel() }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">الحالة المطلوبة</span>
                        <span class="stk-metric__value">{{ $correction->requestedStatusLabel() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">الحالة الحالية</span>
                        <span class="stk-metric__value">{{ $correction->currentStatusLabel() ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>سبب الطلب</dt>
                            <dd>{{ $correction->reason }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>مقدِّم الطلب</dt>
                            <dd>{{ $correction->requestedBy->name ?? '—' }}</dd>
                        </div>
                        @if ($correction->status !== 'pending')
                            <div class="stk-def__row">
                                <dt>{{ $correction->status === 'approved' ? 'اعتمده' : 'رفضه' }}</dt>
                                <dd>{{ $correction->approvedBy->name ?? '—' }} — <span class="stk-num">{{ optional($correction->approved_at)->format('Y-m-d H:i') }}</span></dd>
                            </div>
                            @if ($correction->status === 'rejected')
                                <div class="stk-def__row">
                                    <dt>سبب الرفض</dt>
                                    <dd>{{ $correction->rejection_reason }}</dd>
                                </div>
                            @endif
                            @if ($correction->admin_notes)
                                <div class="stk-def__row">
                                    <dt>ملاحظات المعتمِد</dt>
                                    <dd>{{ $correction->admin_notes }}</dd>
                                </div>
                            @endif
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-repeat"></i> قبل / بعد</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th></th>
                                <th>الحالي</th>
                                <th>المطلوب</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- تسمياتٌ عربيّة لا مفاتيحَ خام: المعتمِدُ يقرّر على ما يقرأ --}}
                            <tr>
                                <td>الحالة</td>
                                <td>{{ $correction->currentStatusLabel() ?? '—' }}</td>
                                <td><strong>{{ $correction->requestedStatusLabel() }}</strong></td>
                            </tr>
                            <tr>
                                <td>بداية الوردية</td>
                                <td class="stk-num">{{ $correction->current_start_shift ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_start_shift ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>نهاية الوردية</td>
                                <td class="stk-num">{{ $correction->current_end_shift ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_end_shift ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>وقت الحضور</td>
                                <td class="stk-num">{{ $correction->current_check_in_time ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_check_in_time ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>وقت الانصراف</td>
                                <td class="stk-num">{{ $correction->current_check_out_time ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_check_out_time ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>نوع الإجازة</td>
                                <td>{{ \App\Models\AttendanceCorrectionRequest::absenceTypeLabel($correction->current_absence_type) ?? '—' }}</td>
                                <td><strong>{{ \App\Models\AttendanceCorrectionRequest::absenceTypeLabel($correction->requested_absence_type) ?? '—' }}</strong></td>
                            </tr>
                            <tr>
                                <td>رصيد الإجازة</td>
                                <td class="stk-num">{{ $correction->current_absence_balance ?? '—' }}</td>
                                <td class="stk-num"><strong>{{ $correction->requested_absence_balance ?? '—' }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
