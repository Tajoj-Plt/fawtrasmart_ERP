@extends('hr::master')

@section('title')
    عرض رصيد الإجازة
@stop

@php
    $percentage = $balance->getTotalAvailableBalance() > 0
        ? round(($balance->used_balance / $balance->getTotalAvailableBalance()) * 100, 1)
        : 0;
    if ($percentage >= 90) {
        $usageBadge = ['badge-danger', 'مكتمل تقريباً'];
    } elseif ($percentage >= 75) {
        $usageBadge = ['badge-warning', 'مرتفع الاستخدام'];
    } elseif ($percentage >= 50) {
        $usageBadge = ['badge-info', 'متوسط الاستخدام'];
    } else {
        $usageBadge = ['badge-success', 'استخدام منخفض'];
    }
@endphp

@section('page-actions')
    @permission('leave_balances.edit')
        <a href="{{ route('employee_leave_balances.edit', $balance->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل الرصيد</span>
        </a>
    @endpermission
    @if (canAny(['leave_balances.edit', 'leave_balances.index', 'leave_balances.destroy']))
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i> المزيد
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @permission('leave_balances.edit')
                    <li>
                        <button type="button" id="recalculateBtn" class="dropdown-item"
                                data-employee-id="{{ $balance->employee_id }}" data-year="{{ $balance->year }}">
                            <i class="fa fa-calculator me-2 text-warning"></i>إعادة حساب الرصيد
                        </button>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modal_ADD_BALANCE{{ $balance->id }}">
                            <i class="fa fa-plus-circle me-2 text-info"></i>إضافة رصيد إضافي
                        </a>
                    </li>
                @endpermission
                @permission('leave_balances.index')
                    <li>
                        <a class="dropdown-item" href="{{ route('employee_leave_balances.index') }}">
                            <i class="fa fa-list me-2 text-primary"></i>العودة للقائمة
                        </a>
                    </li>
                @endpermission
                @if ($balance->used_balance == 0 && hasPermission('leave_balances.destroy'))
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $balance->id }}">
                            <i class="fa fa-trash me-2"></i>حذف الرصيد
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('hr::components.table-menu')

    @include('stock::components.page-head', [
        'title' => 'رصيد إجازة #'.$balance->id,
        'icon' => 'pie-chart',
        'crumbs' => ['الرئيسية' => url('/'), 'أرصدة الإجازات' => route('employee_leave_balances.index'), 'رصيد #'.$balance->id => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true">
                        <i class="feather icon-pie-chart"></i>
                    </span>
                    <h4 class="mb-25">{{ $balance->employee->full_name ?? 'موظف محذوف' }}</h4>
                    <p class="stk-help mt-0 mb-1">
                        #رصيد {{ $balance->id }} · {{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }} · {{ $balance->year }}
                    </p>
                    <span class="badge {{ $usageBadge[0] }}">{{ $usageBadge[1] }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">المتبقي</span>
                        <span class="stk-metric__value {{ $balance->getActualRemainingBalance() <= 5 ? 'text-danger' : 'text-success' }}">{{ $balance->getActualRemainingBalance() }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">المستخدم</span>
                        <span class="stk-metric__value">{{ $balance->used_balance }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">الإجمالي</span>
                        <span class="stk-metric__value">{{ $balance->getTotalAvailableBalance() }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4>
                </div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>الموظف</dt>
                            <dd>
                                <a href="#" class="text-primary text-decoration-none">
                                    <i class="feather icon-user"></i>
                                    {{ $balance->employee->full_name ?? 'موظف محذوف' }}
                                </a>
                                <span class="stk-tag stk-num">#{{ $balance->employee->id ?? $balance->employee_id }}</span>
                                <div class="stk-help">
                                    <i class="fas fa-building"></i> {{ $balance->employee->department->name ?? 'غير محدد' }} |
                                    <i class="feather icon-map-pin"></i> {{ $balance->employee->branch->name ?? 'غير محدد' }}
                                </div>
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>المنصب</dt>
                            <dd>{{ $balance->employee->position->name ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>نوع الإجازة</dt>
                            <dd>
                                <span class="badge" style="background-color: {{ $balance->leaveType->color ?? '#6c757d' }}; color: white;">
                                    {{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}
                                </span>
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>وصف النوع</dt>
                            <dd>{{ $balance->leaveType->description ?? 'لا يوجد وصف' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>السنة</dt>
                            <dd class="stk-num">{{ $balance->year }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الإنشاء</dt>
                            <dd class="stk-num">{{ optional($balance->created_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>آخر تحديث</dt>
                            <dd class="stk-num">{{ optional($balance->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ══════════ الرصيد والطلبات والإحصائيات والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card stk-table-card">
                <div class="card-body pb-0">
                    <ul class="nav nav-tabs stk-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab"
                               aria-controls="details" aria-selected="true">
                                <i class="feather icon-layers"></i> تفاصيل الرصيد
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="requests-log-tab" data-toggle="tab" href="#requests-log" role="tab"
                               aria-controls="requests-log" aria-selected="false">
                                <i class="feather icon-calendar"></i> سجل الطلبات
                                <span class="stk-count">{{ $leaveRequests->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="statistics-tab" data-toggle="tab" href="#statistics" role="tab"
                               aria-controls="statistics" aria-selected="false">
                                <i class="feather icon-bar-chart-2"></i> الإحصائيات
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
                    {{-- تفاصيل الرصيد --}}
                    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>الرصيد المبدئي</dt>
                                    <dd class="stk-num">{{ $balance->initial_balance }} يوم</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>المرحل من العام السابق</dt>
                                    <dd class="stk-num">{{ $balance->carried_forward }} يوم</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>الرصيد الإضافي</dt>
                                    <dd class="stk-num">{{ $balance->additional_balance }} يوم</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>إجمالي الرصيد المتاح</dt>
                                    <dd class="stk-num text-info font-weight-bold">{{ $balance->getTotalAvailableBalance() }} يوم</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>المستخدم</dt>
                                    <dd class="stk-num text-danger font-weight-bold">{{ $balance->used_balance }} يوم</dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>المتبقي</dt>
                                    <dd class="stk-num font-weight-bold {{ $balance->getActualRemainingBalance() <= 5 ? 'text-danger' : 'text-success' }}">
                                        {{ $balance->getActualRemainingBalance() }} يوم
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>نسبة الاستخدام</dt>
                                    <dd>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar
                                                {{ $percentage >= 90 ? 'bg-danger' : ($percentage >= 75 ? 'bg-warning' : 'bg-success') }}"
                                                role="progressbar"
                                                style="width: {{ $percentage }}%"
                                                aria-valuenow="{{ $percentage }}"
                                                aria-valuemin="0"
                                                aria-valuemax="100">
                                                {{ $percentage }}%
                                            </div>
                                        </div>
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>السنة</dt>
                                    <dd class="stk-num">{{ $balance->year }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- سجل الطلبات --}}
                    <div class="tab-pane fade" id="requests-log" role="tabpanel" aria-labelledby="requests-log-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">طلبات الإجازة المعتمدة لعام {{ $balance->year }}</h6>
                        </div>

                        @if($leaveRequests->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>تاريخ البداية</th>
                                            <th>تاريخ النهاية</th>
                                            <th>عدد الأيام</th>
                                            <th>السبب</th>
                                            <th>تاريخ الطلب</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($leaveRequests as $request)
                                            <tr>
                                                <td class="stk-num text-muted">{{ $loop->iteration }}</td>
                                                <td class="stk-num">{{ \Carbon\Carbon::parse($request->start_date)->format('Y-m-d') }}</td>
                                                <td class="stk-num">{{ \Carbon\Carbon::parse($request->end_date)->format('Y-m-d') }}</td>
                                                <td>
                                                    <span class="badge badge-info">{{ $request->total_days }} أيام</span>
                                                </td>
                                                <td>
                                                    <span class="text-truncate" style="max-width: 200px; display: inline-block;"
                                                          title="{{ $request->reason }}">
                                                        {{ Str::limit($request->reason, 50) }}
                                                    </span>
                                                </td>
                                                <td class="stk-num">{{ $request->created_at->format('Y-m-d') }}</td>
                                                <td>
                                                    <span class="stk-state stk-state--on">معتمد</span>
                                                </td>
                                                <td>
                                                    @permission('leave_requests.show')
                                                        <a href="{{ route('attendance.leave_requests.show', $request->id) }}"
                                                           class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                                            <i class="feather icon-eye"></i>
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
                                    <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
                                    <h5 class="stk-empty__title">لا توجد طلبات إجازات معتمدة</h5>
                                    <p class="stk-empty__hint">لم يتم تقديم أي طلبات إجازة معتمدة لهذا النوع في عام {{ $balance->year }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- الإحصائيات --}}
                    <div class="tab-pane fade" id="statistics" role="tabpanel" aria-labelledby="statistics-tab">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title">إحصائيات الموظف لعام {{ $balance->year }} — توزيع الرصيد</h6>
                        </div>
                        <div class="stk-metrics" style="margin: 0; border-width: 0; border-radius: 0; box-shadow: none">
                            <div class="stk-metric">
                                <span class="stk-metric__label">المتبقي</span>
                                <span class="stk-metric__value text-success">{{ $balance->getActualRemainingBalance() }}</span>
                            </div>
                            <div class="stk-metric">
                                <span class="stk-metric__label">المستخدم</span>
                                <span class="stk-metric__value text-danger">{{ $balance->used_balance }}</span>
                            </div>
                            <div class="stk-metric">
                                <span class="stk-metric__label">الإجمالي</span>
                                <span class="stk-metric__value text-info">{{ $balance->getTotalAvailableBalance() }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- سجل النشاطات --}}
                    <div class="tab-pane fade" id="activity-log" role="tabpanel" aria-labelledby="activity-log-tab">
                        <div class="card-body">
                            @if ($logs && count($logs) > 0)
                                <ul class="stk-timeline">
                                    @foreach ($logs as $date => $dayLogs)
                                        <li class="stk-timeline__day">
                                            <span>{{ \Carbon\Carbon::parse($date)->locale('ar')->translatedFormat('l، d F Y') }}</span>
                                        </li>
                                        @foreach ($dayLogs as $log)
                                            <li class="stk-timeline__item">
                                                <span class="stk-timeline__dot stk-timeline__dot--log">
                                                    <i class="feather icon-edit-3"></i>
                                                </span>
                                                <div class="stk-timeline__body">
                                                    <p class="stk-timeline__head">
                                                        <strong>{{ $log->user->name ?? 'النظام' }}</strong>
                                                        <span class="stk-timeline__time stk-num">
                                                            <i class="feather icon-clock"></i> {{ $log->created_at->format('H:i:s') }}
                                                        </span>
                                                    </p>
                                                    <div class="stk-timeline__text">{{ $log->description ?? 'نشاط غير معرف' }}</div>
                                                    @if(isset($log->details))
                                                        <p class="stk-timeline__meta">{{ $log->details }}</p>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @else
                                <p class="stk-help mb-2">لا توجد سجلات نشاط على الرصيد حتى الآن!</p>

                                {{-- عرض إنشاء الرصيد كأول نشاط إذا لا توجد سجلات --}}
                                <ul class="stk-timeline">
                                    <li class="stk-timeline__day">
                                        <span>{{ $balance->created_at->locale('ar')->translatedFormat('l، d F Y') }}</span>
                                    </li>
                                    <li class="stk-timeline__item">
                                        <span class="stk-timeline__dot stk-timeline__dot--log">
                                            <i class="feather icon-plus"></i>
                                        </span>
                                        <div class="stk-timeline__body">
                                            <p class="stk-timeline__head">
                                                <strong>النظام</strong>
                                                <span class="stk-timeline__time stk-num">
                                                    <i class="feather icon-clock"></i> {{ $balance->created_at->format('H:i:s') }}
                                                </span>
                                            </p>
                                            <div class="stk-timeline__text">تم إنشاء رصيد {{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}</div>
                                            <p class="stk-timeline__meta">
                                                للموظف {{ $balance->employee->full_name ?? 'موظف محذوف' }} - الإجمالي {{ $balance->getTotalAvailableBalance() }} يوم لعام {{ $balance->year }}
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- نافذة الحذف — الحذف عبر السكربت (.delete-confirm) --}}
    @if($balance->used_balance == 0)
        <div class="modal fade" id="modal_DELETE{{ $balance->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">حذف رصيد الإجازة</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            هل أنت متأكد من أنك تريد حذف رصيد إجازة <strong>"{{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}"</strong>
                            للموظف <strong>"{{ $balance->employee->full_name ?? 'موظف محذوف' }}"</strong>؟
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <button type="button" class="btn btn-danger delete-confirm" data-id="{{ $balance->id }}">
                            <i class="feather icon-trash-2"></i> تأكيد
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- نافذة إضافة رصيد إضافي --}}
    <div class="modal fade" id="modal_ADD_BALANCE{{ $balance->id }}" tabindex="-1" role="dialog"
         aria-labelledby="addBalanceLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBalanceLabel">إضافة رصيد إضافي</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="addBalanceForm">
                        <div class="form-group">
                            <label for="additional_days" class="form-label stk-req">عدد الأيام الإضافية</label>
                            <input type="number" class="form-control" id="additional_days" min="1" required>
                        </div>
                        <div class="form-group mb-0">
                            <label for="reason" class="form-label">السبب</label>
                            <textarea class="form-control" id="reason" rows="3" placeholder="أدخل سبب إضافة الرصيد الإضافي"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" id="confirmAddBalance"
                            data-id="{{ $balance->id }}">إضافة الرصيد</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Delete functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-confirm')) {
                const btn = e.target.closest('.delete-confirm');
                const id = btn.getAttribute('data-id');

                fetch(`{{ route('employee_leave_balances.index') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    $(`#modal_DELETE${id}`).modal('hide');

                    if (data.success) {
                        Swal.fire({
                            title: 'تم الحذف!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonText: 'موافق'
                        }).then(() => {
                            window.location.href = '{{ route("employee_leave_balances.index") }}';
                        });
                    } else {
                        Swal.fire({
                            title: 'خطأ!',
                            text: data.message,
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    }
                })
                .catch(error => {
                    $(`#modal_DELETE${id}`).modal('hide');
                    Swal.fire({
                        title: 'خطأ!',
                        text: 'حدث خطأ أثناء الحذف',
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                });
            }
        });

        // Recalculate balance
        document.getElementById('recalculateBtn')?.addEventListener('click', function() {
            const employeeId = this.getAttribute('data-employee-id');
            const year = this.getAttribute('data-year');

            Swal.fire({
                title: 'إعادة حساب الرصيد',
                text: 'سيتم إعادة حساب الرصيد بناءً على الطلبات المعتمدة. هل تريد المتابعة؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، احسب',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'جاري إعادة الحساب...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('{{ route("employee_leave_balances.recalculate") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            year: year,
                            employee_id: employeeId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'تم بنجاح!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'موافق'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'خطأ!',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'موافق'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'خطأ!',
                            text: 'حدث خطأ أثناء إعادة الحساب',
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    });
                }
            });
        });

        // Add additional balance
        document.getElementById('confirmAddBalance').addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const additionalDays = document.getElementById('additional_days').value;
            const reason = document.getElementById('reason').value;

            if (!additionalDays || additionalDays <= 0) {
                Swal.fire({
                    title: 'خطأ!',
                    text: 'يجب إدخال عدد أيام صحيح',
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
                return;
            }

            // هنا يمكنك إضافة الطلب لإضافة الرصيد الإضافي
            $('#modal_ADD_BALANCE' + id).modal('hide');

            Swal.fire({
                title: 'قريباً',
                text: 'ميزة إضافة الرصيد الإضافي ستكون متاحة قريباً',
                icon: 'info',
                confirmButtonText: 'موافق'
            });
        });
    });
</script>
@endsection
