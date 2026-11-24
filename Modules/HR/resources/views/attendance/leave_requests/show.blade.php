@extends('hr::master')

@section('title')
    عرض طلب الإجازة
@stop

@php
    $lrCanApprove = hasPermission('leave_requests.approve');
    $lrCanEdit = hasPermission('leave_requests.edit');
    $lrCanDelete = hasPermission('leave_requests.destroy');
    $lrIsPending = $leaveRequest->status == 'pending';
    $lrStatusBadge = $lrIsPending
        ? ['warning', 'تحت المراجعة']
        : ($leaveRequest->status == 'approved' ? ['success', 'موافق عليه'] : ['danger', 'مرفوض']);
    $lrRequestType = $leaveRequest->request_type == 'leave'
        ? 'إجازة عادية'
        : ($leaveRequest->request_type == 'emergency' ? 'إجازة طارئة' : 'إجازة مرضية');
    $lrAttachments = $leaveRequest->attachments ? json_decode($leaveRequest->attachments, true) : null;
@endphp

@section('page-actions')
    @if ($lrIsPending)
        @if ($lrCanApprove)
            <button type="button" class="btn btn-success" onclick="approveRequest({{ $leaveRequest->id }})">
                <i class="feather icon-check"></i> <span class="d-none d-md-inline">موافقة</span>
            </button>
            <button type="button" class="btn btn-outline-danger" onclick="rejectRequest({{ $leaveRequest->id }})">
                <i class="feather icon-x"></i> <span class="d-none d-md-inline">رفض</span>
            </button>
        @endif
        @if ($lrCanEdit)
            <a href="{{ route('attendance.leave_requests.edit', $leaveRequest->id) }}" class="btn btn-primary">
                <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل</span>
            </a>
        @endif
        @if ($lrCanDelete)
            <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modal_DELETE{{ $leaveRequest->id }}">
                <i class="feather icon-trash-2"></i> <span class="d-none d-md-inline">حذف</span>
            </button>
        @endif
    @endif
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'طلب إجازة #'.$leaveRequest->id,
        'icon' => 'send',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'طلبات الإجازة' => hasPermission('leave_requests.index') ? route('attendance.leave_requests.index') : null,
            'طلب #'.$leaveRequest->id => null,
        ],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    <div class="row">
        {{-- ══════════ الهوية والتفاصيل ══════════ --}}
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-send"></i></span>
                    <h4 class="mb-25">{{ $leaveRequest->employee->full_name }}</h4>
                    <p class="stk-help mt-0 mb-1">طلب إجازة #{{ $leaveRequest->id }} · {{ $leaveRequest->leaveType->name ?? 'غير محدد' }}</p>
                    <span class="badge bg-{{ $lrStatusBadge[0] }}">{{ $lrStatusBadge[1] }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">عدد الأيام</span>
                        <span class="stk-metric__value">{{ $leaveRequest->days }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">تاريخ البدء</span>
                        <span class="stk-metric__value stk-num" style="font-size:1rem">{{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>الموظف</dt>
                            <dd>
                                {{-- الاسمُ يظهر دائماً؛ الرابطُ لمن يملك فتحَ ملف الموظف --}}
                                @if (hasPermission('employees.show'))
                                    <a href="{{ route('employee.show', $leaveRequest->employee->id) }}" target="_blank">
                                        {{ $leaveRequest->employee->full_name }}
                                        #{{ $leaveRequest->employee->id }}
                                    </a>
                                @else
                                    {{ $leaveRequest->employee->full_name }}
                                    #{{ $leaveRequest->employee->id }}
                                @endif
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>القسم</dt>
                            <dd>{{ $leaveRequest->employee->department->name ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الإنشاء</dt>
                            <dd class="stk-num">{{ $leaveRequest->created_at->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>آخر تحديث</dt>
                            <dd class="stk-num">{{ $leaveRequest->updated_at->format('Y-m-d H:i') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- ══════════ الطلب والسجل ══════════ --}}
        <div class="col-lg-8 col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-calendar"></i> معلومات طلب الإجازة</h4></div>
                <div class="card-body">
                    <dl class="stk-def stk-def--compact">
                        <div class="stk-def__row">
                            <dt>تاريخ البدء</dt>
                            <dd class="stk-num">{{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('Y-m-d') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>تاريخ الانتهاء</dt>
                            <dd class="stk-num">{{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('Y-m-d') }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>عدد الأيام</dt>
                            <dd>{{ $leaveRequest->days }} يوم</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>نوع الطلب</dt>
                            <dd>{{ $lrRequestType }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>نوع الإجازة</dt>
                            <dd>{{ $leaveRequest->leaveType->name ?? 'غير محدد' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الحالة</dt>
                            <dd><span class="badge bg-{{ $lrStatusBadge[0] }}">{{ $lrStatusBadge[1] }}</span></dd>
                        </div>
                        @if (! $lrIsPending)
                            <div class="stk-def__row">
                                <dt>تمت الموافقة/الرفض بواسطة</dt>
                                <dd>{{ $leaveRequest->approver->name ?? 'غير معروف' }}</dd>
                            </div>
                        @endif
                        @if ($leaveRequest->status == 'rejected' && $leaveRequest->rejection_reason)
                            <div class="stk-def__row">
                                <dt>سبب الرفض</dt>
                                <dd>{{ $leaveRequest->rejection_reason }}</dd>
                            </div>
                        @endif
                        <div class="stk-def__row">
                            <dt>الوصف</dt>
                            <dd>{{ $leaveRequest->description ?? 'لا يوجد وصف' }}</dd>
                        </div>
                        @if ($leaveRequest->attachments)
                            <div class="stk-def__row">
                                <dt>المرفقات</dt>
                                <dd>
                                    @if (is_array($lrAttachments))
                                        @foreach ($lrAttachments as $attachment)
                                            <a href="{{ asset('storage/' . $attachment) }}" target="_blank" class="btn btn-sm btn-outline-primary mb-25 mr-25">
                                                <i class="feather icon-download"></i> تحميل
                                            </a>
                                        @endforeach
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-activity"></i> سجل النشاطات</h4>
                </div>
                <div class="card-body">
                    @if ($logs->isNotEmpty())
                        <ul class="stk-timeline">
                            @foreach ($logs as $date => $logItems)
                                <li class="stk-timeline__day">
                                    <span>{{ \Illuminate\Support\Carbon::parse($date)->format('Y-m-d') }}</span>
                                </li>
                                @foreach ($logItems as $log)
                                    @php
                                        $lrDot = $log->type_log == 'approved' ? ['in', 'check'] : ($log->type_log == 'rejected' ? ['out', 'x'] : ['log', 'plus']);
                                    @endphp
                                    <li class="stk-timeline__item">
                                        <span class="stk-timeline__dot stk-timeline__dot--{{ $lrDot[0] }}">
                                            <i class="feather icon-{{ $lrDot[1] }}"></i>
                                        </span>
                                        <div class="stk-timeline__body">
                                            <p class="stk-timeline__head">
                                                <strong>{{ $log->user->name ?? 'النظام' }}</strong>
                                                <span class="stk-timeline__time stk-num">
                                                    <i class="feather icon-clock"></i> {{ $log->created_at->format('H:i:s') }}
                                                </span>
                                            </p>
                                            <div class="stk-timeline__text">{{ $log->description }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    @else
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                            <h5 class="stk-empty__title">لا توجد سجلات نشاط</h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Modal delete -->
    <div class="modal fade" id="modal_DELETE{{ $leaveRequest->id }}" tabindex="-1" role="dialog"
        aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel1">حذف طلب الإجازة</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">هل أنت متأكد من أنك تريد حذف طلب الإجازة هذا؟</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    @permission('leave_requests.destroy')
                        <form action="{{ route('attendance.leave_requests.destroy', $leaveRequest->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> تأكيد الحذف</button>
                        </form>
                    @endpermission
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Approve Request -->
    <div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">الموافقة على طلب الإجازة</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>هل تريد الموافقة على طلب الإجازة لـ <strong>{{ $leaveRequest->employee->full_name }}</strong>؟</p>
                    <p class="text-muted mb-0">المدة: <strong>{{ $leaveRequest->days }} أيام</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-success" onclick="confirmApprove()">موافقة</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Reject Request -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header">
                    <h5 class="modal-title">رفض طلب الإجازة</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>هل تريد رفض طلب الإجازة لـ <strong>{{ $leaveRequest->employee->full_name }}</strong>؟</p>
                    <div class="form-group mb-0">
                        <label for="rejectionReason" class="form-label stk-req">سبب الرفض</label>
                        <textarea class="form-control" id="rejectionReason" rows="4"
                                  placeholder="أدخل سبب الرفض..."></textarea>
                        <small class="stk-help">يجب إدخال سبب الرفض</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-danger" onclick="confirmReject()">رفض</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
        let pendingRequestId = null;

        function approveRequest(requestId) {
            pendingRequestId = requestId;
            
            Swal.fire({
                title: 'الموافقة على طلب الإجازة',
                html: `
                    <p>هل تريد الموافقة على طلب الإجازة لـ <strong>{{ $leaveRequest->employee->full_name }}</strong>؟</p>
                    <p class="text-muted">المدة: <strong>{{ $leaveRequest->days }} أيام</strong></p>
                    <p class="text-muted">الفترة: من <strong>{{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('d/m/Y') }}</strong> إلى <strong>{{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('d/m/Y') }}</strong></p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="feather icon-check"></i> نعم، الموافقة',
                cancelButtonText: '<i class="feather icon-x"></i> إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    confirmApprove();
                }
            });
        }

        function rejectRequest(requestId) {
            pendingRequestId = requestId;
            
            Swal.fire({
                title: 'رفض طلب الإجازة',
                html: `
                    <p>هل تريد رفض طلب الإجازة لـ <strong>{{ $leaveRequest->employee->full_name }}</strong>؟</p>
                    <div class="form-group text-start">
                        <label for="rejectionReason" class="form-label stk-req">سبب الرفض</label>
                        <textarea class="form-control" id="rejectionReason" rows="4" 
                                  placeholder="أدخل سبب الرفض..."></textarea>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="feather icon-x"></i> نعم، الرفض',
                cancelButtonText: '<i class="feather icon-rotate-ccw"></i> إلغاء',
                reverseButtons: true,
                preConfirm: () => {
                    const reason = document.getElementById('rejectionReason').value.trim();
                    if (!reason) {
                        Swal.showValidationMessage('يجب إدخال سبب الرفض');
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    confirmReject(result.value);
                }
            });
        }

        function confirmApprove() {
            if (!pendingRequestId) return;

            // إظهار تحميل
            Swal.fire({
                title: 'جاري المعالجة...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // استخدام route باسم Laravel
            const url = "{{ route('attendance.leave_requests.approve', ':id') }}".replace(':id', pendingRequestId);
            
            $.ajax({
                url: url,
                type: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        Swal.fire({
                            title: 'تمت الموافقة!',
                            text: response.message || 'تم الموافقة على الطلب بنجاح',
                            icon: 'success',
                            confirmButtonText: 'موافق',
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'خطأ!',
                            text: response.message || 'حدث خطأ أثناء المعالجة',
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    
                    let errorMsg = 'حدث خطأ في الخادم';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMsg = 'طلب الإجازة غير موجود';
                    } else if (xhr.status === 403) {
                        errorMsg = 'غير مسموح لك بتنفيذ هذا الإجراء';
                    }
                    
                    Swal.fire({
                        title: 'خطأ!',
                        text: errorMsg,
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                }
            });
        }

        function confirmReject(rejectionReason) {
            if (!pendingRequestId) return;

            // إظهار تحميل
            Swal.fire({
                title: 'جاري المعالجة...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // استخدام route باسم Laravel
            const url = "{{ route('attendance.leave_requests.reject', ':id') }}".replace(':id', pendingRequestId);
            
            $.ajax({
                url: url,
                type: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                data: { 
                    rejection_reason: rejectionReason 
                },
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        Swal.fire({
                            title: 'تم الرفض!',
                            text: response.message || 'تم رفض الطلب بنجاح',
                            icon: 'success',
                            confirmButtonText: 'موافق',
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'خطأ!',
                            text: response.message || 'حدث خطأ أثناء المعالجة',
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    
                    let errorMsg = 'حدث خطأ في الخادم';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMsg = 'طلب الإجازة غير موجود';
                    } else if (xhr.status === 403) {
                        errorMsg = 'غير مسموح لك بتنفيذ هذا الإجراء';
                    }
                    
                    Swal.fire({
                        title: 'خطأ!',
                        text: errorMsg,
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                }
            });
        }

        // إظهار التنبيهات
        function showNotification(message, type = 'info') {
            const alertClass = `alert-${type}`;
            const iconClass = type === 'success' ? 'fa-check-circle' : 
                            type === 'danger' ? 'fa-exclamation-circle' : 
                            type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                     style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
                    <i class="fas ${iconClass} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('body').append(alertHtml);

            setTimeout(() => {
                $('.alert').fadeOut(500, function() { $(this).remove(); });
            }, 5000);
        }
    </script>
@endsection
