@extends('hr::master')

@section('title')
أذونات إجازة
@stop

@php
    $lpTypeText = $leavePermission->type == 1 ? 'إجازة اعتيادية' : 'إجازة عرضية';
    $lpDate = fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : ($d ?: '—');
@endphp

@section('page-actions')
    @if (hasPermission('leave_permissions.edit'))
        <a href="{{ route('leave_permissions.edit', $leavePermission->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل</span>
        </a>
    @endif
    @if (hasPermission('leave_permissions.destroy'))
        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modal_DELETE{{ $leavePermission->id }}">
            <i class="feather icon-trash-2"></i> <span class="d-none d-md-inline">حذف</span>
        </button>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إذن إجازة #'.$leavePermission->id,
        'icon' => 'clock',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أذونات الإجازة' => hasPermission('leave_permissions.index') ? route('leave_permissions.index') : null,
            'إذن #'.$leavePermission->id => null,
        ],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-clock"></i></span>
                    <h4 class="mb-25">{{ $leavePermission->employee->full_name }}</h4>
                    <p class="stk-help mt-0 mb-1">إذن إجازة #{{ $leavePermission->id }}</p>
                    <span class="stk-state stk-state--neutral">{{ $lpTypeText }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">التاريخ من</span>
                        <span class="stk-metric__value stk-num" style="font-size:1rem">{{ $lpDate($leavePermission->start_date) }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">التاريخ إلى</span>
                        <span class="stk-metric__value stk-num" style="font-size:1rem">{{ $lpDate($leavePermission->end_date) }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>اسم الموظف</dt>
                            <dd>
                                <a href="{{ route('employee.show', $leavePermission->employee->id) }}" target="_blank">
                                    {{ $leavePermission->employee->full_name }} #{{ $leavePermission->employee->id }}
                                </a>
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>النوع</dt>
                            <dd>{{ $lpTypeText }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>التاريخ</dt>
                            <dd class="stk-num">{{ $lpDate($leavePermission->start_date) }} - {{ $lpDate($leavePermission->end_date) }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ التقديم</dt>
                            <dd class="stk-num">{{ $lpDate($leavePermission->submission_date) }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>ملاحظة</dt>
                            <dd>{{ $leavePermission->notes ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ══════════ سجل النشاطات ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-activity"></i> سجل النشاطات</h4>
                </div>
                <div class="card-body">
                    @include('hr::organizational_structure.partials._activity', [
                        'logs' => collect($logs ?? []),
                        'emptyHint' => 'لا توجد سجلات نشاط حتى الآن!',
                    ])
                </div>
            </div>
        </div>
    </div>

    <!-- Modal delete -->
    <div class="modal fade" id="modal_DELETE{{ $leavePermission->id }}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel1">حذف اذن اجازة</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">هل انت متاكد من انك تريد الحذف ؟</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">الغاء</button>
                    @permission('leave_permissions.destroy')
                        <form method="POST" action="{{ route('leave_permissions.destroy', $leavePermission->id) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> تأكيد الحذف</button>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>
    </div>
    <!--end delete-->
</div>
@endsection
