{{--
    صفوفُ الورديات المخصّصة — يُرسم في الصفحة أوّل مرّة، ويعود من
    `custom_shifts.filter` نصّاً يُوضع في `#resultsContainer`.
    ترويسةُ الجدول وتذييلُه هنا لا في الصفحة، ليتجدّدا مع كل تصفية.
--}}
@php
    $csHasFilters = collect(request()->only(['keywords', 'from_date', 'to_date']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $csDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '—';
@endphp

@if (isset($custom_shifts) && $custom_shifts->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> قائمة الورديات المخصصة
            <span class="stk-count">{{ $custom_shifts->total() }}</span>
        </h6>
        @include('stock::components.per-page', [
            'id' => 'customShiftsPerPage',
            'current' => request('per_page', 10),
            'onchange' => 'updatePerPage(this.value)',
        ])
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="custom-shifts-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الاسم</th>
                    <th>الوردية</th>
                    <th>تاريخ البدء</th>
                    <th>تاريخ الانتهاء</th>
                    <th class="text-center">عدد الموظفين</th>
                    <th class="text-center" style="width:70px">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($custom_shifts as $index => $custom_shift)
                    <tr id="custom-shift-{{ $custom_shift->id }}">
                        <td class="stk-num text-muted">{{ $custom_shifts->firstItem() + $index }}</td>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-calendar"></i></span>
                                <span class="stk-media__body">
                                    @if (hasPermission('shifts.show'))
                                        <a href="{{ route('custom_shifts.show', $custom_shift->id) }}" class="stk-media__title">{{ $custom_shift->name }}</a>
                                    @else
                                        <span class="stk-media__title">{{ $custom_shift->name }}</span>
                                    @endif
                                    <span class="stk-media__sub">#{{ $custom_shift->id }}</span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info">
                                <i class="feather icon-clock me-1"></i>
                                {{ $custom_shift->shift->name ?? 'غير محدد' }}
                            </span>
                        </td>
                        {{-- عمودا الجدول `from_date`/`to_date`؛ و`start_date`/`end_date` لا وجود لهما،
                             فكان `Carbon::parse(null)` يُظهر تاريخ اليوم في كل صفّ --}}
                        <td class="stk-num">{{ $csDate($custom_shift->from_date ?? $custom_shift->start_date) }}</td>
                        <td class="stk-num">{{ $csDate($custom_shift->to_date ?? $custom_shift->end_date) }}</td>
                        <td class="text-center stk-num">{{ $custom_shift->employees->count() }}</td>
                        <td class="text-center">
                            @if (canAny(['shifts.show', 'shifts.edit', 'shifts.destroy']))
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="dropdownMenuButton{{ $custom_shift->id }}"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $custom_shift->id }}">
                                        @permission('shifts.show')
                                            <a class="dropdown-item" href="{{ route('custom_shifts.show', $custom_shift->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endpermission
                                        @permission('shifts.edit')
                                            <a class="dropdown-item" href="{{ route('custom_shifts.edit', $custom_shift->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endpermission
                                        @permission('shifts.destroy')
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger delete-custom-shift-btn"
                                                href="#"
                                                data-id="{{ $custom_shift->id }}"
                                                data-name="{{ $custom_shift->name }}"
                                                data-employees-count="{{ $custom_shift->employees->count() }}">
                                                <i class="fa fa-trash me-2"></i>حذف
                                            </a>
                                        @endpermission
                                    </div>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($custom_shifts->hasPages())
        <div class="stk-table-foot">
            <p class="stk-table-foot__count">عرض {{ $custom_shifts->firstItem() }}–{{ $custom_shifts->lastItem() }} من {{ $custom_shifts->total() }}</p>
            {{ $custom_shifts->links() }}
        </div>
    @endif
@else
    <div class="card-body">
        <div class="stk-empty">
            <div class="stk-empty__icon"><i class="feather icon-calendar"></i></div>
            <h5 class="stk-empty__title">لا توجد ورديات مخصصة</h5>
            <p class="stk-empty__hint">
                @if ($csHasFilters)
                    لم يطابق البحث أي وردية — جرّب معايير أوسع
                @else
                    أضف وردية مخصصة لتعيين أوقات دوام مختلفة لموظفين معيّنين في فترة محددة
                @endif
            </p>
            @if ($csHasFilters)
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('resetFilter').click()"><i class="feather icon-rotate-ccw"></i> إزالة الفلاتر</button>
            @else
                @permission('shifts.create')
                    <a href="{{ route('custom_shifts.create') }}" class="btn btn-primary"><i class="feather icon-plus"></i> إضافة وردية مخصصة</a>
                @endpermission
            @endif
        </div>
    </div>
@endif

<script>
// ربط أحداث الحذف
$(document).ready(function() {
    bindDeleteEvents();
});

function bindDeleteEvents() {
    $('.delete-custom-shift-btn').off('click').on('click', function(e) {
        e.preventDefault();
        
        const shiftId = $(this).data('id');
        const shiftName = $(this).data('name');
        const employeesCount = $(this).data('employees-count');
        
        deleteCustomShift(shiftId, shiftName, employeesCount);
    });
}

// دالة حذف الوردية المخصصة
// دالة حذف الوردية المخصصة - النسخة المصححة
// دالة حذف الوردية المخصصة - النسخة المصححة
function deleteCustomShift(shiftId, shiftName, employeesCount) {
    Swal.fire({
        title: 'حذف الوردية المخصصة',
        html: `
            <strong>هل أنت متأكد من أنك تريد حذف الوردية المخصصة:</strong>
            <p class="text-danger mt-2 mb-2">"${shiftName}"</p>
            <div class="text-start mt-3">
                <p class="mb-1"><strong>عدد الموظفين المرتبطين:</strong> ${employeesCount}</p>
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

            // استخدام route باسم Laravel - التصحيح هنا
            const url = "{{ route('custom_shifts.delete', ':id') }}".replace(':id', shiftId);
            
            // إرسال طلب الحذف
            $.ajax({
                url: url,
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
                            text: response.message || 'تم حذف الوردية المخصصة بنجاح',
                            icon: 'success',
                            confirmButtonText: 'موافق',
                            timer: 3000,
                            timerProgressBar: true
                        });

                        // إزالة الصف من الجدول
                        $(`#custom-shift-${shiftId}`).fadeOut(300, function() {
                            $(this).remove();
                            
                            // إعادة تحميل البيانات إذا لم يتبقى سجلات
                            if ($('#custom-shifts-table tbody tr').length === 0) {
                                filterData();
                            }
                        });
                        
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
                        errorMessage = 'لم يتم العثور على الوردية المطلوبة';
                    } else if (xhr.status === 403) {
                        errorMessage = 'غير مسموح لك بحذف هذا السجل';
                    } else if (xhr.status === 405) {
                        errorMessage = 'طريقة الحذف غير مسموحة. تأكد من أن الـ route مسجل بشكل صحيح.';
                    }
                    
                    Swal.fire({
                        title: 'خطأ!',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                    
                    console.error('خطأ في الحذف:', error);
                    console.error('تفاصيل الخطأ:', xhr.responseText);
                }
            });
        }
    });
}

// جعل الدوال متاحة globally
window.bindDeleteEvents = bindDeleteEvents;
window.deleteCustomShift = deleteCustomShift;
</script>