@extends('hr::master')

@section('title', 'تعديل أذونات إجازة')

@section('page-actions')
    @permission('leave_permissions.index')
        <a href="{{ route('leave_permissions.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
    @endpermission
    @permission('leave_permissions.edit')
        <button type="submit" form="editLeavePermissionForm" class="btn btn-primary" id="updateBtn">
            <i class="feather icon-check"></i> حفظ التعديلات
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل إذن إجازة #'.$leavePermission->id,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أذونات الإجازة' => hasPermission('leave_permissions.index') ? route('leave_permissions.index') : null,
            'إذن #'.$leavePermission->id => hasPermission('leave_permissions.show') ? route('leave_permissions.show', $leavePermission->id) : null,
            'تعديل' => null,
        ],
    ])

    @include('layouts.alerts.error')

    @permission('leave_permissions.edit')
        <form id="editLeavePermissionForm" method="POST" action="{{ route('leave_permissions.update', $leavePermission->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="stk-form-errors alert alert-danger" role="alert">
                    <div class="stk-form-errors__head">
                        <i class="feather icon-alert-circle"></i> لم تُحفظ التعديلات — راجع الحقول المعلَّمة:
                    </div>
                    <ul class="stk-form-errors__list mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ══════════ البيانات الأساسية ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-clock"></i> البيانات الأساسية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Employee Selection -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="employee" class="form-label stk-req">موظف</label>
                            <select class="form-control select2 @error('employee_id') is-invalid @enderror"
                                    id="employee" name="employee_id" required>
                                <option value="" disabled>اختر موظف</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                            {{ old('employee_id', $leavePermission->employee_id) == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }} - {{ $employee->employee_number ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-create-first
                                :count="count($employees ?? [])"
                                route="employee.create"
                                permission="employees.create"
                                :plan="\App\Constants\planFeatures\HrPlanFeatures::EMPLOYEES"
                                label="موظفاً"
                                hint="العملية تقع على موظف، ولا موظف مسجَّل بعد." />
                            @error('employee_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- نوع الإذن (type) - قيم رقمية -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="type" class="form-label stk-req">نوع الإذن</label>
                            <select class="form-control @error('type') is-invalid @enderror"
                                    id="type" name="leave_type" required>
                                <option value="" disabled>اختر نوع الإذن</option>
                                <option value="1" {{ old('leave_type', $leavePermission->leave_type) == '1' ? 'selected' : '' }}>الوصول المتأخر</option>
                                <option value="2" {{ old('leave_type', $leavePermission->leave_type) == '2' ? 'selected' : '' }}>الانصراف المبكر</option>
                            </select>
                            @error('leave_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- نوع الإجازة (leave_type) - قيم نصية -->
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="leave-type" class="form-label stk-req">نوع الإجازة</label>
                            <select class="form-control @error('leave_type') is-invalid @enderror"
                                    id="leave-type" name="type" required>
                                <option value="" disabled>اختر نوع الإجازة</option>
                                <option value="1" {{ old('type', $leavePermission->type) == '1' ? 'selected' : '' }}>إجازة اعتيادية</option>
                                <option value="2" {{ old('type', $leavePermission->type) == '2' ? 'selected' : '' }}>إجازة عرضية</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="start-date" class="form-label stk-req">التاريخ من</label>
                            <input type="date"
                                   class="form-control @error('start_date') is-invalid @enderror"
                                   id="start-date"
                                   name="start_date"
                                   value="{{ old('start_date', optional($leavePermission->start_date)->format('Y-m-d')) }}"
                                   required>
                            @error('start_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- End Date -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="end-date" class="form-label stk-req">التاريخ إلى</label>
                            <input type="date"
                                   class="form-control @error('end_date') is-invalid @enderror"
                                   id="end-date"
                                   name="end_date"
                                   value="{{ old('end_date', optional($leavePermission->end_date)->format('Y-m-d')) }}"
                                   required>
                            @error('end_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Submission Date -->
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="application-date" class="form-label">تاريخ التقديم</label>
                            <input type="date"
                                   class="form-control @error('submission_date') is-invalid @enderror"
                                   id="application-date"
                                   name="submission_date"
                                   value="{{ old('submission_date', optional($leavePermission->submission_date)->format('Y-m-d')) }}">
                            @error('submission_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ بيانات إضافية ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-file-text"></i> بيانات إضافية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Notes -->
                        <div class="col-xl-6 col-12 mb-3">
                            <label for="notes" class="form-label">ملاحظة</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes"
                                      rows="3"
                                      placeholder="أدخل ملاحظاتك"
                                      name="notes"
                                      maxlength="1000">{{ old('notes', $leavePermission->notes) }}</textarea>
                            <small class="stk-help">
                                <span id="notes-count">{{ strlen(old('notes', $leavePermission->notes)) }}</span>/1000 حرف
                            </small>
                            @error('notes')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Attachments -->
                        <div class="col-xl-6 col-12 mb-3">
                            <label for="attachments" class="form-label">
                                @if($leavePermission->attachments)
                                    تحديث المرفقات (اختياري)
                                @else
                                    المرفقات
                                @endif
                            </label>
                            @if($leavePermission->attachments)
                                <div class="d-flex align-items-center flex-wrap mb-50" style="gap:.5rem">
                                    <a href="{{ asset('storage/' . $leavePermission->attachments) }}" target="_blank" class="btn btn-outline-info btn-sm">
                                        <i class="feather icon-eye"></i> عرض المرفق الحالي
                                    </a>
                                    <small class="stk-help mt-0">{{ basename($leavePermission->attachments) }}</small>
                                </div>
                            @endif
                            <div class="custom-file">
                                <input type="file"
                                       name="attachments"
                                       id="attachments"
                                       class="custom-file-input @error('attachments') is-invalid @enderror"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <label class="custom-file-label" for="attachments">اختر ملف جديد...</label>
                            </div>
                            <small class="stk-help">
                                @if($leavePermission->attachments)
                                    ترك هذا الحقل فارغ سيحتفظ بالمرفق الحالي.
                                @endif
                                الحد الأقصى لحجم الملف: 5 ميجابايت. الأنواع المدعومة: PDF, DOC, DOCX, JPG, PNG
                            </small>
                            @error('attachments')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    @permission('leave_permissions.index')
                        <a href="{{ route('leave_permissions.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ التعديلات
                    </button>
                </div>
            </div>
        </form>
    @endpermission
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: "اختر موظف",
        allowClear: true,
        dir: "rtl"
    });

    // File input label update
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'اختر ملف جديد...');
    });

    // Notes character counter
    $('#notes').on('input', function() {
        let length = $(this).val().length;
        $('#notes-count').text(length);

        if (length > 1000) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    // Date validation
    $('#start-date').on('change', function() {
        let startDate = $(this).val();
        $('#end-date').attr('min', startDate);

        // Clear end date if it's before start date
        let endDate = $('#end-date').val();
        if (endDate && endDate < startDate) {
            $('#end-date').val('');
            Swal.fire({
                title: 'تنبيه!',
                text: 'تم مسح تاريخ النهاية لأنه كان أقل من تاريخ البداية',
                icon: 'info',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    });

    // Form submission with SweetAlert confirmation
    $('#editLeavePermissionForm').on('submit', function(e) {
        e.preventDefault();

        let isValid = true;
        let errorMessage = '';

        if (!$('#employee').val()) {
            isValid = false;
            errorMessage = 'يرجى اختيار موظف';
        }
        else if (!$('#start-date').val()) {
            isValid = false;
            errorMessage = 'يرجى إدخال تاريخ البداية';
        }
        else if (!$('#end-date').val()) {
            isValid = false;
            errorMessage = 'يرجى إدخال تاريخ النهاية';
        }
        else if (!$('#type').val()) {
            isValid = false;
            errorMessage = 'يرجى اختيار نوع الإذن';
        }
        else if (!$('#leave-type').val()) {
            isValid = false;
            errorMessage = 'يرجى اختيار نوع الإجازة';
        }

        if (!isValid) {
            Swal.fire({
                title: 'بيانات ناقصة!',
                text: errorMessage,
                icon: 'warning',
                confirmButtonText: 'موافق',
            });
            return;
        }

        Swal.fire({
            title: 'تأكيد التحديث',
            text: 'هل أنت متأكد من تحديث بيانات إذن الإجازة؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="feather icon-edit-2"></i> نعم، حدث',
            cancelButtonText: '<i class="feather icon-x"></i> إلغاء',
            reverseButtons: true,
            customClass: {
                confirmButton: 'btn btn-info mx-2',
                cancelButton: 'btn btn-danger mx-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $('#updateBtn').html('<i class="fa fa-spinner fa-spin"></i> جاري التحديث...').prop('disabled', true);

                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                Toast.fire({
                    icon: 'info',
                    title: 'جاري تحديث البيانات...'
                });

                this.submit();
            }
        });
    });

    @if(session('success'))
        Swal.fire({
            title: 'تم التحديث بنجاح!',
            text: '{{ session('success') }}',
            icon: 'success',
            confirmButtonText: 'موافق',
            timer: 3000,
            timerProgressBar: true
        });
    @endif

    @if(session('error'))
        Swal.fire({
            title: 'خطأ في التحديث!',
            text: '{{ session('error') }}',
            icon: 'error',
            confirmButtonText: 'موافق',
        });
    @endif
});
</script>
@endpush