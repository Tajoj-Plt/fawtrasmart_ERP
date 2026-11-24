{{--
    صفوفُ جدول طلبات الإجازة — يُعيدها مسارُ البحث (attendance.leave_requests.search)
    نصّاً في `table` فتضعه الشاشةُ في #tableContainer داخل بطاقة الجدول.
    عددُ السجلات في الصفحة ومجموعُ الطلبات في رأس البطاقة نفسها (index).
--}}
@if(isset($leaveRequests) && $leaveRequests->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="leave-requests-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الموظف</th>
                    <th>تاريخ الطلب</th>
                    <th>نوع الإجازة</th>
                    <th class="text-center">المدة</th>
                    <th>الفترة</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaveRequests as $index => $request)
                    @php
                        $lrCanShow = hasPermission('leave_requests.show');
                        $lrCanEdit = $request->status == 'pending' && hasPermission('leave_requests.edit');
                        /* أزرارُ البتّ لمن يحقّ له البتُّ في هذا الطلب بعينه — لا لكلّ من يملك الصلاحية:
                           كانت تظهر ثم يردّ الخادمُ «غير مسموح» (قنوات الاعتماد، وطلبُ المرء لنفسه). */
                        $lrMayDecide = hasPermission('leave_requests.approve')
                            && app(\App\Services\Attendance\LeaveApprovalAuthorizer::class)->canDecide(auth()->user(), $request);
                        $lrCanApprove = $request->status == 'pending' && $lrMayDecide;
                        $lrCanReject = $lrCanApprove;
                        $lrCanUnapprove = $request->status == 'approved' && $lrMayDecide;
                        $lrCanReopen = $request->status == 'rejected' && $lrMayDecide;
                        $lrCanDelete = in_array($request->status, ['pending', 'rejected'])
                            && hasPermission('leave_requests.destroy');
                    @endphp
                    <tr id="leave-request-{{ $request->id }}">
                        <td class="stk-num text-muted">{{ $leaveRequests->firstItem() + $index }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                <span class="stk-media__body">
                                    @if ($lrCanShow)
                                        <a href="{{ route('attendance.leave_requests.show', $request->id) }}" class="stk-media__title">{{ $request->employee->full_name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $request->employee->full_name }}</span>
                                    @endif
                                    <span class="stk-media__sub">#{{ $request->employee->id }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="stk-num">{{ \Carbon\Carbon::parse($request->created_at)->format('d/m/Y') }}</td>
                        <td>{{ $request->leaveType->name ?? 'نوع محذوف' }}</td>
                        <td class="text-center stk-num">{{ $request->days }} يوم</td>
                        <td class="stk-num">
                            {{ \Carbon\Carbon::parse($request->start_date)->format('d/m/Y') }}
                            <span class="text-muted">←</span>
                            {{ \Carbon\Carbon::parse($request->end_date)->format('d/m/Y') }}
                        </td>
                        <td class="text-center">
                            @if($request->status == 'approved')
                                <span class="badge badge-light-success">موافق عليه</span>
                            @elseif($request->status == 'pending')
                                <span class="badge badge-light-warning">تحت المراجعة</span>
                            @elseif($request->status == 'rejected')
                                <span class="badge badge-light-danger">مرفوض</span>
                            @else
                                <span class="badge badge-light-secondary">غير محدد</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($lrCanShow || $lrCanEdit || $lrCanApprove || $lrCanDelete || $lrCanUnapprove || $lrCanReopen)
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                    id="dropdownMenuButton{{ $request->id }}" data-bs-toggle="dropdown" data-bs-display="static"
                                    aria-expanded="false" aria-label="خيارات">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $request->id }}">
                                    @if ($lrCanShow)
                                        <a class="dropdown-item" href="{{ route('attendance.leave_requests.show', $request->id) }}">
                                            <i class="fa fa-eye me-2 text-primary"></i>عرض
                                        </a>
                                    @endif

                                    @if ($request->status == 'pending')
                                        @if ($lrCanEdit)
                                            <a class="dropdown-item" href="{{ route('attendance.leave_requests.edit', $request->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endif

                                        @if ($lrCanApprove)
                                            <a class="dropdown-item approve-request-btn" href="#"
                                                data-id="{{ $request->id }}"
                                                data-name="{{ $request->employee->full_name }}">
                                                <i class="fa fa-check me-2 text-success"></i>موافقة
                                            </a>
                                        @endif
                                    @endif

                                    @if ($lrCanUnapprove)
                                        <a class="dropdown-item text-warning unapprove-request-btn" href="#"
                                            data-id="{{ $request->id }}"
                                            data-name="{{ $request->employee->full_name }}">
                                            <i class="fa fa-undo me-2"></i>إلغاء الموافقة
                                        </a>
                                    @endif
                                    @if ($lrCanReopen)
                                        <a class="dropdown-item reopen-request-btn" href="#"
                                            data-id="{{ $request->id }}"
                                            data-name="{{ $request->employee->full_name }}">
                                            <i class="fa fa-reply me-2"></i>إرجاع للمراجعة
                                        </a>
                                    @endif

                                    @if (($lrCanReject || $lrCanDelete) && ($lrCanShow || $lrCanEdit || $lrCanApprove))
                                        <div class="dropdown-divider"></div>
                                    @endif
                                    @if ($lrCanReject)
                                        <a class="dropdown-item text-danger reject-request-btn" href="#"
                                            data-id="{{ $request->id }}"
                                            data-name="{{ $request->employee->full_name }}">
                                            <i class="fa fa-times me-2"></i>رفض
                                        </a>
                                    @endif
                                    @if ($lrCanDelete)
                                        <a class="dropdown-item text-danger delete-request-btn" href="#"
                                            data-id="{{ $request->id }}"
                                            data-name="{{ $request->employee->full_name }}"
                                            data-type="{{ $request->leaveType->name ?? 'نوع محذوف' }}"
                                            data-duration="{{ $request->days }} يوم"
                                            data-period="{{ \Carbon\Carbon::parse($request->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($request->end_date)->format('d/m/Y') }}">
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

    @if ($leaveRequests->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">عرض {{ $leaveRequests->firstItem() }}–{{ $leaveRequests->lastItem() }} من {{ $leaveRequests->total() }}</p>
            {{ $leaveRequests->links() }}
        </div>
    @endif
@else
    @php
        $lrHasFilters = collect(request()->only(['employee_search', 'from_date', 'to_date', 'created_date', 'leave_type', 'department', 'branch', 'status']))
            ->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    @endphp
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-send"></i></div>
            <h5 class="stk-empty__title">{{ $lrHasFilters ? 'لا توجد نتائج' : 'لا توجد طلبات إجازة' }}</h5>
            <p class="stk-empty__hint">
                @if ($lrHasFilters)
                    لم يطابق البحث أيّ طلب — جرّب معايير أوسع
                @else
                    أضف أوّل طلب إجازة ليظهر هنا
                @endif
            </p>
            @if ($lrHasFilters)
                <button type="button" class="btn btn-outline-secondary js-lr-reset"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</button>
            @elseif (hasPermission('leave_requests.create'))
                <a href="{{ route('attendance.leave_requests.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة طلب إجازة</a>
            @endif
        </div>
    </div>
@endif

<script>
function updatePerPage(perPage) {
    const formData = $('#searchForm').serialize() + '&per_page=' + perPage;
    searchLeaveRequests(formData);
}

// ربط أحداث الحذف والموافقة والرفض
$(document).ready(function() {
    bindLeaveRequestEvents();
});

function bindLeaveRequestEvents() {
    // حذف طلب الإجازة
    $('.delete-request-btn').off('click').on('click', function(e) {
        e.preventDefault();
        
        const requestId = $(this).data('id');
        const employeeName = $(this).data('name');
        const leaveType = $(this).data('type');
        const duration = $(this).data('duration');
        const period = $(this).data('period');
        
        deleteLeaveRequest(requestId, employeeName, leaveType, duration, period);
    });

    // إلغاء الموافقة / إرجاع المرفوض للمراجعة — كانا مسارين بلا زرّ
    $('.unapprove-request-btn, .reopen-request-btn').off('click').on('click', function(e) {
        e.preventDefault();

        const unapprove = $(this).hasClass('unapprove-request-btn');
        const requestId = $(this).data('id');
        const url = (unapprove
            ? "{{ route('attendance.leave_requests.cancelApproval', ':id') }}"
            : "{{ route('attendance.leave_requests.return_from_rejection', ':id') }}").replace(':id', requestId);

        Swal.fire({
            title: unapprove ? 'إلغاء الموافقة على الإجازة' : 'إرجاع الطلب للمراجعة',
            html: (unapprove
                ? 'ستعود الأيام إلى رصيد <b>' + $(this).data('name') + '</b>، ويُعاد احتساب حضوره في أيام الإجازة التي مضت.'
                : 'يعود طلب <b>' + $(this).data('name') + '</b> إلى «تحت المراجعة» ليُبتّ فيه من جديد.'),
            input: 'textarea',
            inputPlaceholder: 'السبب',
            inputValidator: (value) => !value ? 'اكتب السبب' : undefined,
            showCancelButton: true,
            confirmButtonText: unapprove ? 'نعم، ألغِ الموافقة' : 'نعم، أرجعه',
            cancelButtonText: 'إلغاء',
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: url,
                method: 'POST',
                data: { note: result.value, _token: '{{ csrf_token() }}' },
                headers: { 'Accept': 'application/json' },
            }).done(function (res) {
                Swal.fire({ icon: 'success', title: res.message || 'تم', timer: 2500, showConfirmButton: false });
                if (typeof searchLeaveRequests === 'function') { searchLeaveRequests(); } else { location.reload(); }
            }).fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'تعذّر التنفيذ', text: (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ' });
            });
        });
    });

    // الموافقة على طلب الإجازة
    $('.approve-request-btn').off('click').on('click', function(e) {
        e.preventDefault();
        
        const requestId = $(this).data('id');
        const employeeName = $(this).data('name');
        
        updateRequestStatus(requestId, 'approved', employeeName);
    });

    // رفض طلب الإجازة
    $('.reject-request-btn').off('click').on('click', function(e) {
        e.preventDefault();
        
        const requestId = $(this).data('id');
        const employeeName = $(this).data('name');
        
        updateRequestStatus(requestId, 'rejected', employeeName);
    });
}

// دالة حذف طلب الإجازة
function deleteLeaveRequest(requestId, employeeName, leaveType, duration, period) {
    Swal.fire({
        title: 'حذف طلب الإجازة',
        html: `
            <strong>هل أنت متأكد من أنك تريد حذف طلب الإجازة للموظف:</strong>
            <p class="text-danger mt-2 mb-2">"${employeeName}"</p>
            <div class="text-start mt-3">
                <p class="mb-1"><strong>نوع الإجازة:</strong> ${leaveType}</p>
                <p class="mb-1"><strong>المدة:</strong> ${duration}</p>
                <p class="mb-1"><strong>الفترة:</strong> ${period}</p>
            </div>
            <div class="alert alert-warning mt-3">
                <i class="feather icon-alert-triangle"></i>
                <strong>تحذير:</strong> هذا الإجراء لا يمكن التراجع عنه!
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="feather icon-trash-2"></i> نعم، احذف',
        cancelButtonText: '<i class="feather icon-x"></i> إلغاء',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger mx-2',
            cancelButton: 'btn btn-secondary mx-2'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            // إظهار حالة التحميل
            Swal.fire({
                title: 'جاري الحذف...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال طلب الحذف
            $.ajax({
                url: `{{ route('attendance.leave_requests.destroy', '') }}/${requestId}`,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        // إظهار رسالة النجاح
                        Swal.fire({
                            title: 'تم الحذف!',
                            text: response.message || 'تم حذف طلب الإجازة بنجاح',
                            icon: 'success',
                            confirmButtonText: 'موافق',
                            timer: 3000,
                            timerProgressBar: true
                        });

                        // إزالة الصف من الجدول
                        $(`#leave-request-${requestId}`).fadeOut(300, function() {
                            $(this).remove();
                            
                            // إعادة تحميل البيانات إذا لم يتبقى سجلات
                            if ($('#leave-requests-table tbody tr').length === 0) {
                                searchLeaveRequests();
                            }
                        });

                        // إظهار رسالة النجاح في الأعلى
                        showAlert('success', response.message || 'تم حذف طلب الإجازة بنجاح');
                        
                    } else {
                        Swal.fire({
                            title: 'خطأ!',
                            text: response.message || 'حدث خطأ أثناء الحذف',
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    
                    let errorMessage = 'حدث خطأ أثناء الحذف. يرجى المحاولة مرة أخرى.';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMessage = 'لم يتم العثور على طلب الإجازة المطلوب';
                    } else if (xhr.status === 403) {
                        errorMessage = 'غير مسموح لك بحذف هذا السجل';
                    }
                    
                    Swal.fire({
                        title: 'خطأ!',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                    
                    console.error('خطأ في الحذف:', error);
                }
            });
        }
    });
}

// دالة تحديث حالة طلب الإجازة
function updateRequestStatus(requestId, status, employeeName) {
    const statusText = status === 'approved' ? 'الموافقة على' : 'الرفض';
    const statusIcon = status === 'approved' ? 'fa-check' : 'fa-times';
    const statusColor = status === 'approved' ? 'success' : 'danger';
    
    Swal.fire({
        title: `${statusText} طلب الإجازة`,
        html: `
            <strong>هل أنت متأكد من ${statusText} طلب الإجازة للموظف:</strong>
            <p class="text-${statusColor} mt-2 mb-2">"${employeeName}"</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'approved' ? '#28a745' : '#d33',
        confirmButtonText: `<i class="fa ${statusIcon}"></i> نعم، ${statusText}`,
        cancelButtonText: '<i class="feather icon-x"></i> إلغاء',
        reverseButtons: true,
        customClass: {
            confirmButton: `btn btn-${statusColor} mx-2`,
            cancelButton: 'btn btn-secondary mx-2'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            // إظهار حالة التحميل
            Swal.fire({
                title: `جاري ${statusText}...`,
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال طلب التحديث
            $.ajax({
                url: `{{ route('attendance.leave_requests.update_status', '') }}/${requestId}`,
                method: 'PATCH',
                data: {
                    status: status
                },
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        // إظهار رسالة النجاح
                        Swal.fire({
                            title: `تم ${statusText}!`,
                            text: response.message || `تم ${statusText} طلب الإجازة بنجاح`,
                            icon: 'success',
                            confirmButtonText: 'موافق',
                            timer: 3000,
                            timerProgressBar: true
                        });

                        // إعادة تحميل البيانات
                        searchLeaveRequests();

                        // إظهار رسالة النجاح في الأعلى
                        showAlert('success', response.message || `تم ${statusText} طلب الإجازة بنجاح`);
                        
                    } else {
                        Swal.fire({
                            title: 'خطأ!',
                            text: response.message || `حدث خطأ أثناء ${statusText}`,
                            icon: 'error',
                            confirmButtonText: 'موافق'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    
                    let errorMessage = `حدث خطأ أثناء ${statusText}. يرجى المحاولة مرة أخرى.`;
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMessage = 'لم يتم العثور على طلب الإجازة المطلوب';
                    } else if (xhr.status === 403) {
                        errorMessage = 'غير مسموح لك بتحديث حالة هذا السجل';
                    }
                    
                    Swal.fire({
                        title: 'خطأ!',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                    
                    console.error(`خطأ في ${statusText}:`, error);
                }
            });
        }
    });
}

// جعل الدوال متاحة globally
window.bindLeaveRequestEvents = bindLeaveRequestEvents;
window.deleteLeaveRequest = deleteLeaveRequest;
window.updateRequestStatus = updateRequestStatus;

// ربط اختيار عدد السجلات بالبحث
$(document).ready(function() {
    // عند تغيير عدد السجلات
    $(document).off('change', '#perPageSelect').on('change', '#perPageSelect', function() {
        if (typeof window.updatePerPageAndSearch === 'function') {
            window.updatePerPageAndSearch($(this).val());
        } else if (typeof searchLeaveRequests === 'function') {
            // إعادة تعيين الصفحة الحالية إلى 1
            currentPage = 1;
            // إضافة per_page إلى الفلاتر
            currentFilters.per_page = $(this).val();
            // تحديث البحث
            searchLeaveRequests();
        }
    });
});
</script>