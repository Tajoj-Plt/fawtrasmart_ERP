@extends('hr::master')

@section('title', 'تعديل سياسة الإجازات')

@section('content')
<div class="content-body">
    {{-- زرّا الترويسة يُحيلان إلى زرَّي أسفل النموذج (#cancelBtn / #updateBtn)
         فيمرّ الحفظ بنفس فحص التغييرات والتحقّق ونافذة التأكيد --}}
    @include('stock::components.page-head', [
        'title' => 'تعديل سياسة الإجازات: '.$leave_policy->name,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'سياسات الإجازات' => route('leave_policy.index'),
            $leave_policy->name => hasPermission('leave_policies.show') ? route('leave_policy.show', $leave_policy->id) : null,
            'تعديل' => null,
        ],
        'actions' => '<button type="button" class="btn btn-outline-secondary" onclick="document.getElementById(\'cancelBtn\').click()"><i class="feather icon-x"></i> إلغاء</button>'
            .'<button type="button" class="btn btn-primary" onclick="document.getElementById(\'updateBtn\').click()"><i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')

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

    @permission('leave_policies.edit')
        <form id="editHolidayForm" method="POST" action="{{ route('leave_policy.update', $leave_policy->id) }}">
            @csrf
            {{-- مسار التحديث POST (`leave_policy.update`) — كان PUT هنا يُرجع 405 فلا يُحفظ أي تعديل --}}

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="name" class="form-label stk-req">الاسم</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $leave_policy->name) }}" required>
                            <div class="invalid-feedback">يرجى إدخال اسم سياسة الإجازات</div>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="status" class="form-label stk-req">الحالة</label>
                            <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                                <option value="0" {{ old('status', $leave_policy->status) == 0 ? 'selected' : '' }}>نشط</option>
                                <option value="1" {{ old('status', $leave_policy->status) == 1 ? 'selected' : '' }}>غير نشط</option>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار حالة السياسة</div>
                        </div>

                        <div class="col-12 mb-0">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="اكتب وصف للسياسة...">{{ old('description', $leave_policy->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-log-out"></i> الإجازات
                        <span class="stk-count">عدد الإجازات المختارة: <strong id="holidayCount">{{ $leave_policy->leaveType->count() }}</strong></span>
                    </h6>
                    <button type="button" id="addRow" class="btn btn-sm btn-outline-primary"><i class="feather icon-plus"></i> إضافة</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width: 10%">الرقم المتسلسل</th>
                                <th>نوع الإجازة</th>
                                <th style="width: 10%"></th>
                            </tr>
                        </thead>
                        <tbody id="holidayTable">
                            @foreach ($leave_policy->leaveType as $index => $leave_type)
                                <tr>
                                    <td style="width: 10%">{{ $index + 1 }}</td>
                                    <td>
                                        <select name="leave_type_id[]" class="form-control leave-type-select" required>
                                            <option value="" disabled>-- اختر نوع الإجازة --</option>
                                            @foreach ($leave_types as $type)
                                                <option value="{{ $type->id }}" {{ $leave_type->id == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback">يرجى اختيار نوع الإجازة</div>
                                    </td>
                                    <td style="width: 10%">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-row">إزالة <i class="feather icon-minus-circle"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    @endpermission

    <div class="card">
        <div class="stk-form-foot">
            <button type="button" id="cancelBtn" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </button>
            <button type="button" id="updateBtn" class="btn btn-primary">
                <i class="feather icon-check"></i> حفظ التعديلات
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const holidayTable = document.getElementById('holidayTable');
        const holidayCount = document.getElementById('holidayCount');
        const form = document.getElementById('editHolidayForm');
        let rowCount = holidayTable.rows.length;

        // Store original form data for comparison
        const originalData = new FormData(form);

        // Leave types for dynamic rows
        const leaveTypesOptions = `
            <option value="" disabled selected>-- اختر نوع الإجازة --</option>
            @foreach ($leave_types as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        `;

        // Function to check if form has changes
        function hasChanges() {
            const currentData = new FormData(form);
            const originalEntries = [...originalData.entries()].sort();
            const currentEntries = [...currentData.entries()].sort();

            if (originalEntries.length !== currentEntries.length) {
                return true;
            }

            for (let i = 0; i < originalEntries.length; i++) {
                if (originalEntries[i][0] !== currentEntries[i][0] ||
                    originalEntries[i][1] !== currentEntries[i][1]) {
                    return true;
                }
            }
            return false;
        }

        // Function to update holiday count
        function updateHolidayCount() {
            holidayCount.textContent = holidayTable.rows.length;
            // Update serial numbers
            const rows = holidayTable.querySelectorAll('tr');
            rows.forEach((row, index) => {
                row.children[0].textContent = index + 1;
            });
        }

        // Function to check for duplicate leave types
        function checkDuplicateLeaveTypes() {
            const selects = document.querySelectorAll('.leave-type-select');
            const selectedValues = [];
            let hasDuplicates = false;

            selects.forEach(select => {
                if (select.value) {
                    if (selectedValues.includes(select.value)) {
                        hasDuplicates = true;
                    }
                    selectedValues.push(select.value);
                }
            });

            return hasDuplicates;
        }

        // Add Row Functionality
        document.getElementById('addRow').addEventListener('click', function () {
            rowCount++;
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td style="width: 10%">${rowCount}</td>
                <td>
                    <select name="leave_type_id[]" class="form-control leave-type-select" required>
                        ${leaveTypesOptions}
                    </select>
                    <div class="invalid-feedback">يرجى اختيار نوع الإجازة</div>
                </td>
                <td style="width: 10%">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-row">إزالة <i class="feather icon-minus-circle"></i></button>
                </td>
            `;
            holidayTable.appendChild(newRow);
            updateHolidayCount();
        });

        // Remove Row Functionality
        holidayTable.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-row') || e.target.closest('.remove-row')) {
                if (holidayTable.rows.length === 1) {
                    Swal.fire({
                        title: 'تنبيه!',
                        text: 'لا يمكن حذف آخر إجازة. يجب أن تحتوي السياسة على نوع إجازة واحد على الأقل.',
                        icon: 'warning',
                        confirmButtonText: 'موافق',
                    });
                    return;
                }

                const row = e.target.closest('tr');
                Swal.fire({
                    title: 'تأكيد الحذف',
                    text: 'هل تريد حذف هذا النوع من الإجازة؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        row.remove();
                        updateHolidayCount();
                    }
                });
            }
        });

        // Cancel Button Functionality
        document.getElementById('cancelBtn').addEventListener('click', function () {
            if (hasChanges()) {
                Swal.fire({
                    title: 'هل أنت متأكد؟',
                    text: 'سيتم إلغاء جميع التغييرات التي قمت بها!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، إلغاء التغييرات',
                    cancelButtonText: 'لا، استمر في التعديل'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "{{ route('leave_policy.index') }}";
                    }
                });
            } else {
                window.location.href = "{{ route('leave_policy.index') }}";
            }
        });

        // Update Button Functionality
        document.getElementById('updateBtn').addEventListener('click', function () {
            // Validate form
            if (!validateForm()) {
                return;
            }

            if (!hasChanges()) {
                Swal.fire({
                    title: 'لا توجد تغييرات!',
                    text: 'لم يتم إجراء أي تعديلات على البيانات',
                    icon: 'info',
                    confirmButtonText: 'موافق',
                });
                return;
            }

            Swal.fire({
                title: 'تأكيد التحديث',
                text: 'هل تريد حفظ التغييرات؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، احفظ التغييرات',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'جاري التحديث...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Submit form
                    form.submit();
                }
            });
        });

        // Form validation function
        function validateForm() {
            let isValid = true;
            const name = document.getElementById('name');
            const status = document.getElementById('status');
            const leaveTypeSelects = document.querySelectorAll('.leave-type-select');

            // Clear previous validation states
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Validate name
            if (!name.value.trim()) {
                name.classList.add('is-invalid');
                isValid = false;
            }

            // Validate status
            if (!status.value && status.value !== '0') {
                status.classList.add('is-invalid');
                isValid = false;
            }

            // Validate leave types
            leaveTypeSelects.forEach((select, index) => {
                if (!select.value) {
                    select.classList.add('is-invalid');
                    isValid = false;
                }
            });

            // Check for duplicate leave types
            if (checkDuplicateLeaveTypes()) {
                isValid = false;
                Swal.fire({
                    title: 'أنواع إجازات مكررة!',
                    text: 'يوجد أنواع إجازات مكررة في السياسة. يرجى اختيار أنواع مختلفة.',
                    icon: 'error',
                    confirmButtonText: 'موافق',
                });
                return false;
            }

            if (!isValid) {
                Swal.fire({
                    title: 'خطأ في البيانات!',
                    text: 'يرجى ملء جميع الحقول المطلوبة',
                    icon: 'error',
                    confirmButtonText: 'موافق',
                });
            }

            return isValid;
        }

        // Handle form submission response
        @if(session('success'))
            Swal.fire({
                title: 'تم التحديث بنجاح!',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonText: 'موافق',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('leave_policy.index') }}";
                }
            });
        @endif

        @if(session('error'))
            Swal.fire({
                title: 'حدث خطأ!',
                text: '{{ session('error') }}',
                icon: 'error',
                confirmButtonText: 'موافق',
            });
        @endif

        @if ($errors->any())
            let errorMessages = '';
            @foreach ($errors->all() as $error)
                errorMessages += '• {{ $error }}\n';
            @endforeach

            Swal.fire({
                title: 'أخطاء في البيانات!',
                text: errorMessages,
                icon: 'error',
                confirmButtonText: 'موافق',
            });
        @endif

        // Warn user before leaving page if there are unsaved changes
        window.addEventListener('beforeunload', function (e) {
            if (hasChanges()) {
                e.preventDefault();
                e.returnValue = 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟';
            }
        });
    });
</script>
@endsection