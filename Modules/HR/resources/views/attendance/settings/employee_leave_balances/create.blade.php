@extends('hr::master')

@section('title', 'إضافة رصيد إجازة')

@section('content')
<div class="hr-hints">
<div class="content-body">
    {{-- زرّا الترويسة يُحيلان إلى زرَّي أسفل النموذج (#cancelBtn / #saveBtn)
         فيمرّ الحفظ بنفس التحقّق ونافذة التأكيد --}}
    @include('stock::components.page-head', [
        'title' => 'إضافة رصيد إجازة',
        'icon' => 'pie-chart',
        'crumbs' => ['الرئيسية' => url('/'), 'أرصدة الإجازات' => route('employee_leave_balances.index'), 'إضافة' => null],
        'actions' => '<button type="button" class="btn btn-outline-secondary" onclick="document.getElementById(\'cancelBtn\').click()"><i class="feather icon-x"></i> إلغاء</button>'
            .'<button type="button" class="btn btn-primary" onclick="document.getElementById(\'saveBtn\').click()"><i class="feather icon-check"></i> حفظ الرصيد</button>',
    ])

    @include('layouts.alerts.error')

    @if ($errors->any())
        <div class="stk-form-errors alert alert-danger" role="alert">
            <div class="stk-form-errors__head">
                <i class="feather icon-alert-circle"></i> لم يُحفظ الرصيد — راجع الحقول المعلَّمة:
            </div>
            <ul class="stk-form-errors__list mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @permission('leave_balances.create')
        <form id="addLeaveBalanceForm" method="POST" action="{{ route('employee_leave_balances.store') }}">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="employee_id" class="form-label stk-req">الموظف</label>
                            <select class="form-control select2 @error('employee_id') is-invalid @enderror" id="employee_id" name="employee_id" required>
                                <option value="">اختر الموظف</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }} - {{ $employee->employee_code ?? $employee->id }}
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
                            <div class="invalid-feedback">يرجى اختيار الموظف</div>
                        </div>

                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="leave_type_id" class="form-label stk-req">نوع الإجازة</label>
                            <select class="form-control select2 @error('leave_type_id') is-invalid @enderror" id="leave_type_id" name="leave_type_id" required>
                                <option value="">اختر نوع الإجازة</option>
                                @foreach($leaveTypes as $leaveType)
                                    <option value="{{ $leaveType->id }}" {{ old('leave_type_id') == $leaveType->id ? 'selected' : '' }}
                                            data-max-days="{{ $leaveType->max_days_per_year }}">
                                        {{ $leaveType->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">يرجى اختيار نوع الإجازة</div>
                        </div>

                        <div class="col-md-6 col-xl-2 mb-3">
                            <label for="year" class="form-label stk-req">السنة</label>
                            <select class="form-control @error('year') is-invalid @enderror" id="year" name="year" required>
                                @for($i = date('Y') - 2; $i <= date('Y') + 1; $i++)
                                    <option value="{{ $i }}" {{ old('year', date('Y')) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            <div class="invalid-feedback">يرجى اختيار السنة</div>
                        </div>

                        <div class="col-md-6 col-xl-2 mb-3">
                            <label for="initial_balance" class="form-label stk-req">الرصيد المبدئي</label>
                            <input type="number" class="form-control @error('initial_balance') is-invalid @enderror" id="initial_balance" name="initial_balance"
                                   value="{{ old('initial_balance') }}" min="0" required>
                            <small class="stk-help" id="suggestedBalance"></small>
                            <div class="invalid-feedback">يرجى إدخال الرصيد المبدئي</div>
                        </div>
                    </div>

                    {{-- تحقق من وجود رصيد سابق — يُظهره السكربت --}}
                    <div id="existingBalanceAlert" class="alert alert-warning mb-0" style="display: none;">
                        <i class="feather icon-alert-triangle"></i>
                        <strong>تنبيه:</strong> يوجد رصيد سابق لهذا الموظف في نفس السنة ونوع الإجازة.
                        <div id="existingBalanceDetails"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-layers"></i> الرصيد</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="carried_forward" class="form-label">المرحل من السنة السابقة</label>
                            <input type="number" class="form-control @error('carried_forward') is-invalid @enderror" id="carried_forward" name="carried_forward"
                                   value="{{ old('carried_forward', 0) }}" min="0">
                            <small class="stk-help">الأيام المرحلة من العام السابق</small>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="additional_balance" class="form-label">الرصيد الإضافي</label>
                            <input type="number" class="form-control @error('additional_balance') is-invalid @enderror" id="additional_balance" name="additional_balance"
                                   value="{{ old('additional_balance', 0) }}" min="0">
                            <small class="stk-help">أيام إضافية (مكافآت، تعويضات، إلخ)</small>
                        </div>

                        <div class="col-md-12 col-xl-6 mb-3">
                            <label class="form-label">الرصيد الإجمالي المتاح</label>
                            <div class="alert alert-info mb-0" id="totalBalance">
                                <strong>الإجمالي: <span id="totalBalanceValue">0</span> يوم</strong>
                            </div>
                        </div>

                        <div class="col-12 mb-0">
                            <label for="notes" class="form-label">الملاحظات</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3"
                                      placeholder="أدخل أي ملاحظات إضافية...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endpermission

    <div class="card">
        <div class="stk-form-foot">
            <button type="button" id="cancelBtn" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </button>
            <button type="button" id="saveBtn" class="btn btn-primary">
                <i class="feather icon-check"></i> حفظ الرصيد
            </button>
        </div>
    </div>
</div>
</div>
@include('components.hint-tips')
@endsection
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('addLeaveBalanceForm');
        const employeeSelect = document.getElementById('employee_id');
        const leaveTypeSelect = document.getElementById('leave_type_id');
        const yearSelect = document.getElementById('year');
        const initialBalanceInput = document.getElementById('initial_balance');
        const carriedForwardInput = document.getElementById('carried_forward');
        const additionalBalanceInput = document.getElementById('additional_balance');

        // Initialize Select2
        $('.select2').select2({
            placeholder: 'اختر...',
            allowClear: true
        });

        // Update suggested balance when leave type changes
        leaveTypeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const maxDays = selectedOption.getAttribute('data-max-days');

            if (maxDays) {
                document.getElementById('suggestedBalance').textContent = `الحد الأقصى المقترح: ${maxDays} يوم`;
                if (!initialBalanceInput.value) {
                    initialBalanceInput.value = maxDays;
                    updateTotalBalance();
                }
            } else {
                document.getElementById('suggestedBalance').textContent = '';
            }
        });

        // Update total balance calculation
        function updateTotalBalance() {
            const initial = parseInt(initialBalanceInput.value) || 0;
            const carried = parseInt(carriedForwardInput.value) || 0;
            const additional = parseInt(additionalBalanceInput.value) || 0;
            const total = initial + carried + additional;

            document.getElementById('totalBalanceValue').textContent = total;
        }

        // Event listeners for balance calculation
        [initialBalanceInput, carriedForwardInput, additionalBalanceInput].forEach(input => {
            input.addEventListener('input', updateTotalBalance);
        });

        // Check for existing balance
        function checkExistingBalance() {
            const employeeId = employeeSelect.value;
            const leaveTypeId = leaveTypeSelect.value;
            const year = yearSelect.value;

            if (employeeId && leaveTypeId && year) {
                {{-- نفس علّة /recalculate في index.blade.php: "admin/" ليست بادئة هذا المسار --}}
                fetch(`{{ route('employee_leave_balances.check_existing') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        leave_type_id: leaveTypeId,
                        year: year
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const alertDiv = document.getElementById('existingBalanceAlert');
                    const detailsDiv = document.getElementById('existingBalanceDetails');

                    if (data.exists) {
                        detailsDiv.innerHTML = `
                            <hr>
                            <strong>الرصيد الحالي:</strong> ${data.balance.initial_balance} يوم<br>
                            <strong>المستخدم:</strong> ${data.balance.used_balance} يوم<br>
                            <strong>المتبقي:</strong> ${data.balance.remaining_balance} يوم
                        `;
                        alertDiv.style.display = 'block';
                    } else {
                        alertDiv.style.display = 'none';
                    }
                });
            }
        }

        // Event listeners for checking existing balance
        [employeeSelect, leaveTypeSelect, yearSelect].forEach(select => {
            select.addEventListener('change', checkExistingBalance);
        });

        /*
            select2 يُطلق تغييرَه عبر jQuery لا حدثاً أصليّاً — فلا تسمعه
            `addEventListener`، ولا يُفحص الرصيد القائم ولا يُقترح الرصيد عند
            الاختيار منه. يُعاد إطلاقه حدثاً أصليّاً.
        */
        if (window.jQuery) {
            [employeeSelect, leaveTypeSelect, yearSelect].forEach(select => {
                jQuery(select).on('select2:select select2:clear', () => select.dispatchEvent(new Event('change')));
            });
        }

        // Cancel Button
        document.getElementById('cancelBtn').addEventListener('click', function () {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'سيتم إلغاء جميع التغييرات التي قمت بها!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، إلغاء',
                cancelButtonText: 'لا، استمر'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('employee_leave_balances.index') }}";
                }
            });
        });

        // Save Button
        document.getElementById('saveBtn').addEventListener('click', function () {
            if (!validateForm()) {
                return;
            }

            Swal.fire({
                title: 'تأكيد الحفظ',
                text: 'هل تريد حفظ رصيد الإجازة؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، احفظ',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'جاري الحفظ...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    form.submit();
                }
            });
        });

        // Form validation
        function validateForm() {
            let isValid = true;
            const requiredFields = ['employee_id', 'leave_type_id', 'year', 'initial_balance'];

            // Clear previous validation states
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value || (fieldName === 'initial_balance' && parseInt(field.value) < 0)) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            });

            // Validate numeric fields are not negative
            const numericFields = ['carried_forward', 'additional_balance'];
            numericFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field.value && parseInt(field.value) < 0) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            });

            if (!isValid) {
                Swal.fire({
                    title: 'خطأ في البيانات!',
                    text: 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح',
                    icon: 'error',
                    confirmButtonText: 'موافق',
                });
            }

            return isValid;
        }

        // Handle form responses
        @if(session('success'))
            Swal.fire({
                title: 'تم بنجاح!',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonText: 'موافق',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('employee_leave_balances.index') }}";
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

        // Initialize total balance calculation
        updateTotalBalance();
    });
</script>
@endsection
