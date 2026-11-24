{{-- ملف: resources/views/hr/attendance/settings/holiday/table-content.blade.php --}}

@if(isset($holiday_lists) && !@empty($holiday_lists) && $holiday_lists->count() > 0)
    <!-- اختيار عدد السجلات في الأعلى -->
    <div class="d-flex justify-content-start align-items-center mb-2 gap-3">
        <label style="margin-bottom: 0;">
            عرض
            <select name="per_page" class="form-select form-select-sm d-inline-block w-auto" onchange="updatePerPage(this.value)">
                <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ request('per_page', 10) == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ request('per_page', 10) == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ request('per_page', 10) == 100 ? 'selected' : '' }}>100</option>
            </select>
            سجل
        </label>
    </div>

    <!-- الجدول -->
    
        <table class="table table-hover zero-configuration dataTable" id="holiday-lists-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>إجمالي الأيام</th>
                    <th style="width: 10%">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($holiday_lists as $holiday_list)
                    <tr id="holiday-list-{{ $holiday_list->id }}">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar bg-light-primary mr-1">
                                    <div class="avatar-content">
                                        <i class="feather icon-calendar text-primary font-medium-3"></i>
                                    </div>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="font-weight-bolder">{{ $holiday_list->name }}</span>
                                    <small class="stk-help">#{{ $holiday_list->id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="font-weight-bolder text-primary">
                                    {{ $holiday_list->holidays()->count() }}
                                </span>
                                <small class="stk-help">يوم إجازة</small>
                            </div>
                        </td>
                        <td>
                            @if (canAny(['holidays.show', 'holidays.edit', 'holidays.destroy']))
                            <div class="btn-group">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="dropdownMenuButton{{ $holiday_list->id }}"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $holiday_list->id }}">
                                        @permission('holidays.show')
                                            <a class="dropdown-item" href="{{ route('holiday_lists.show', $holiday_list->id) }}">
                                                <i class="fa fa-eye me-2 text-primary"></i>عرض
                                            </a>
                                        @endpermission
                                        @permission('holidays.edit')
                                            <a class="dropdown-item" href="{{ route('holiday_lists.edit', $holiday_list->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endpermission
                                        @permission('holidays.destroy')
                                        @if (canAny(['holidays.show', 'holidays.edit']))
                                        <div class="dropdown-divider"></div>
                                        @endif
                                        <a class="dropdown-item text-danger delete-holiday-list-btn"
                                            href="#"
                                            data-id="{{ $holiday_list->id }}"
                                            data-name="{{ $holiday_list->name }}"
                                            data-days-count="{{ $holiday_list->holidays()->count() }}">
                                            <i class="fa fa-trash me-2"></i>حذف
                                        </a>
                                        @endpermission
                                    </div>
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
    

    <!-- الباجينيشن ومعلومات الصفحة -->
    <div class="d-flex justify-content-between align-items-center mt-3 gap-3">
   

        <!-- معلومات الصفحة في أقصى اليمين -->
        <div class="pagination-info">
            صفحة {{ $holiday_lists->currentPage() }} من {{ $holiday_lists->lastPage() }}
            (عرض {{ $holiday_lists->firstItem() }} إلى {{ $holiday_lists->lastItem() }} من {{ $holiday_lists->total() }} سجل)
        </div>
        
             <!-- الباجينيشن في أقصى اليسار -->
        <nav aria-label="Page navigation">
            <ul class="pagination mb-0">
                @if ($holiday_lists->onFirstPage())
                    <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $holiday_lists->previousPageUrl() }}">&laquo;</a></li>
                @endif
                
                @for ($i = 1; $i <= $holiday_lists->lastPage(); $i++)
                    <li class="page-item {{ $holiday_lists->currentPage() == $i ? 'active' : '' }}">
                        <a class="page-link" href="{{ $holiday_lists->url($i) }}">{{ $i }}</a>
                    </li>
                @endfor
                
                @if ($holiday_lists->hasMorePages())
                    <li class="page-item"><a class="page-link" href="{{ $holiday_lists->nextPageUrl() }}">&raquo;</a></li>
                @else
                    <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                @endif
            </ul>
        </nav>
    </div>
@else
    <div class="alert alert-info text-center">
        <i class="feather icon-info me-2"></i>
        @if(request()->hasAny(['keywords']))
            لم يتم العثور على أي نتائج تطابق معايير البحث المحددة.
            <br>
            جرب تغيير معايير البحث أو إعادة تعيين الفلاتر.
        @else
            لا توجد قوائم عطل مضافة حتى الآن!
        @endif
        <div class="mt-3">
            @permission('holidays.create')
                <a href="{{ route('holiday_lists.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus me-2"></i>إضافة قائمة عطل جديدة
                </a>
            @endpermission
        </div>
    </div>
@endif

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ربط أحداث الحذف
$(document).ready(function() {
    bindDeleteEvents();
});

function bindDeleteEvents() {
    $('.delete-holiday-list-btn').off('click').on('click', function(e) {
        e.preventDefault();
        
        const listId = $(this).data('id');
        const listName = $(this).data('name');
        const daysCount = $(this).data('days-count');
        
        deleteHolidayList(listId, listName, daysCount);
    });
}

// دالة حذف قائمة العطل
function deleteHolidayList(listId, listName, daysCount) {
    Swal.fire({
        title: 'حذف قائمة العطل',
        html: `
            <strong>هل أنت متأكد من أنك تريد حذف قائمة العطل:</strong>
            <p class="text-danger mt-2 mb-2">"${listName}"</p>
            <div class="text-start mt-3">
                <p class="mb-1"><strong>عدد أيام العطل المرتبطة:</strong> ${daysCount}</p>
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

            // استخدام route باسم Laravel
            const url = "{{ route('holiday_lists.delete', ':id') }}".replace(':id', listId);
            
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
                            text: response.message || 'تم حذف قائمة العطل بنجاح',
                            icon: 'success',
                            confirmButtonText: 'موافق',
                            timer: 3000,
                            timerProgressBar: true
                        });

                        // إزالة الصف من الجدول
                        $(`#holiday-list-${listId}`).fadeOut(300, function() {
                            $(this).remove();
                            
                            // إعادة تحميل البيانات إذا لم يتبقى سجلات
                            if ($('#holiday-lists-table tbody tr').length === 0) {
                                performSearch();
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
                        errorMessage = 'لم يتم العثور على قائمة العطل المطلوبة';
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
                }
            });
        }
    });
}

// تحديث عدد السجلات المعروضة
function updatePerPage(perPage) {
    const formData = $('#searchForm').serialize() + '&per_page=' + perPage;
    performSearch(formData);
}

// إعادة البحث
function performSearch(customData = null) {
    const formData = customData || $('#searchForm').serialize();
    
    $.ajax({
        url: "{{ route('holiday_lists.search') }}",
        method: 'GET',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#resultsContainer').html(response.html);
                bindDeleteEvents();
            } else {
                showAlert('حدث خطأ في البحث', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('خطأ في البحث:', error);
            showAlert('حدث خطأ في البحث', 'danger');
        }
    });
}

// إظهار التنبيهات
function showAlert(message, type = 'info') {
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

// جعل الدوال متاحة globally
window.bindDeleteEvents = bindDeleteEvents;
window.deleteHolidayList = deleteHolidayList;
window.updatePerPage = updatePerPage;
window.performSearch = performSearch;
</script>