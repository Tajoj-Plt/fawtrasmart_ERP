@extends('hr::master')

@section('title')
ورديات المخصصة
@stop

@section('page-actions')
    @permission('shifts.edit')
        <a href="{{ route('custom_shifts.edit', $custom_shift->id) }}" class="btn btn-primary">
            <i class="fa fa-edit"></i> تعديل
        </a>
    @endpermission
    @permission('shifts.destroy')
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $custom_shift->id }}">
                        <i class="fa fa-trash me-2"></i>حذف
                    </a>
                </li>
            </ul>
        </div>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $custom_shift->name,
        'icon' => 'user-check',
        'crumbs' => ['الرئيسية' => url('/'), 'الورديات المتخصصة' => route('custom_shifts.index'), $custom_shift->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-user-check"></i></span>
                    <h4 class="mb-25">{{ $custom_shift->name }}</h4>
                    <p class="stk-help mt-0 mb-1">وردية متخصصة رقم #{{ $custom_shift->id }}</p>
                    <span class="stk-state stk-state--on">{{ $custom_shift->shift->name ?? '—' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">الموظفون</span>
                        <span class="stk-metric__value">{{ $custom_shift->employees->count() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">النشاطات</span>
                        <span class="stk-metric__value">{{ $logs->flatten()->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>تاريخ البدء</dt>
                            <dd class="stk-num">{{ $custom_shift->from_date }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الانتهاء</dt>
                            <dd class="stk-num">{{ $custom_shift->to_date }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الوردية المعينه</dt>
                            <dd>{{ $custom_shift->shift->name ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab" aria-controls="details" aria-selected="true">
                                <i class="feather icon-users"></i> الموظفون
                                <span class="stk-count">{{ $custom_shift->employees->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activity-log-tab" data-toggle="tab" href="#activity-log" role="tab" aria-controls="activity-log" aria-selected="false">
                                <i class="feather icon-activity"></i> سجل النشاطات
                                <span class="stk-count">{{ $logs->flatten()->count() }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        @if ($custom_shift->employees->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:44px">#</th>
                                            <th>اسم الموظف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($custom_shift->employees as $i => $employee)
                                            <tr>
                                                <td class="stk-num text-muted">{{ $i + 1 }}</td>
                                                <td>
                                                    @permission('employees.show')
                                                        <a href="{{ route('employee.show', $employee->id) }}" target="_blank" class="font-weight-bold">
                                                            {{ $employee->full_name }} #{{ $employee->id }}
                                                        </a>
                                                    @endpermission
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body">
                                <div class="stk-empty">
                                    <div class="stk-empty__icon"><i class="feather icon-users"></i></div>
                                    <h5 class="stk-empty__title">لا موظفين</h5>
                                    <p class="stk-empty__hint">لم يُسنَد موظفٌ من نطاقك إلى هذه الوردية بالاسم.</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="tab-pane" id="activity-log" role="tabpanel" aria-labelledby="activity-log-tab">
                        <div class="card-body">
                            @include('hr::organizational_structure.partials._activity', ['logs' => $logs])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @permission('shifts.destroy')
        <div class="modal fade" id="modal_DELETE{{ $custom_shift->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">حذف {{ $custom_shift->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">هل انت متاكد من انك تريد الحذف ؟</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <form method="POST" action="{{ route('custom_shifts.delete', $custom_shift->id) }}" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> تأكيد الحذف</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endpermission
</div>
@endsection
