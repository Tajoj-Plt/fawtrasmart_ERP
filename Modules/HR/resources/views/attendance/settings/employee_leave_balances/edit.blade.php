@extends('hr::master')

@section('title', 'تعديل رصيد إجازة')

@section('content')
<div class="hr-hints">
<div class="content-body">
    {{-- زرّا الترويسة يُحيلان إلى زرَّي أسفل النموذج (#cancelBtn / #updateBtn)
         فيمرّ الحفظ بنفس التحقّق ونافذة التأكيد --}}
    @include('stock::components.page-head', [
        'title' => 'تعديل رصيد إجازة',
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أرصدة الإجازات' => route('employee_leave_balances.index'),
            ($balance->employee->full_name ?? 'رصيد #'.$balance->id) => hasPermission('leave_balances.show') ? route('employee_leave_balances.show', $balance->id) : null,
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

    {{-- معلومات الرصيد الحالي --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-pie-chart"></i> معلومات الرصيد الحالي</h4>
        </div>
        <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
            <div class="stk-metric">
                <span class="stk-metric__label">الموظف</span>
                <span class="stk-metric__value" style="font-size: 1rem">{{ $balance->employee->full_name ?? 'موظف محذوف' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__label">نوع الإجازة</span>
                <span class="stk-metric__value" style="font-size: 1rem">{{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__label">الرصيد المستخدم</span>
                <span class="stk-metric__value">{{ $balance->used_balance }} يوم</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__label">الرصيد المتبقي</span>
                <span class="stk-metric__value">{{ $balance->getActualRemainingBalance() }} يوم</span>
            </div>
        </div>
    </div>

    @permission('leave_balances.edit')
        <form id="editLeaveBalanceForm" method="POST" action="{{ route('employee_leave_balances.update', $balance->id) }}">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="employee_display" class="form-label">الموظف</label>
                            <input type="text" class="form-control" id="employee_display"
                                   value="{{ $balance->employee->full_name ?? 'موظف محذوف' }} - {{ $balance->employee->employee_code ?? $balance->employee->id ?? $balance->employee_id }}"
                                   disabled>
                        </div>

                        <div class="col-md-6 col-xl-4 mb-3">
                            <label for="leave_type_display" class="form-label">نوع الإجازة</label>
                            <input type="text" class="form-control" id="leave_type_display"
                                   value="{{ $balance->leaveType->name ?? 'نوع إجازة محذوف' }}" disabled>
                        </div>

                        <div class="col-md-6 col-xl-2 mb-3">
                            <label for="year" class="form-label stk-req">السنة</label>
                            <select class="form-control @error('year') is-invalid @enderror" id="year" name="year" required>
                                @for($i = date('Y') - 2; $i <= date('Y') + 1; $i++)
                                    <option value="{{ $i }}" {{ old('year', $balance->year) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                            <div class="invalid-feedback">يرجى اختيار السنة</div>
                        </div>

                        <div class="col-md-6 col-xl-2 mb-3">
                            <label for="initial_balance" class="form-label stk-req">الرصيد المبدئي</label>
                            <input type="number" class="form-control @error('initial_balance') is-invalid @enderror" id="initial_balance" name="initial_balance"
                                   value="{{ old('initial_balance', $balance->initial_balance) }}" min="0" required>
                            <div class="invalid-feedback">يرجى إدخال الرصيد المبدئي</div>
                        </div>
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
                                   value="{{ old('carried_forward', $balance->carried_forward) }}" min="0">
                            <small class="stk-help">الأيام المرحلة من العام السابق</small>
                        </div>

                        <div class="col-md-6 col-xl-3 mb-3">
                            <label for="additional_balance" class="form-label">الرصيد الإضافي</label>
                            <input type="number" class="form-control @error('additional_balance') is-invalid @enderror" id="additional_balance" name="additional_balance"
                                   value="{{ old('additional_balance', $balance->additional_balance) }}" min="0">
                            <small class="stk-help">أيام إضافية (مكافآت، تعويضات، إلخ)</small>
                        </div>

                        {{-- عرض الرصيد المحدث — يحدّثه السكربت --}}
                        <div class="col-md-12 col-xl-6 mb-3">
                            <label class="form-label">بعد التعديل</label>
                            <div class="row">
                                <div class="col-md-4 mb-1">
                                    <div class="alert alert-info mb-0">
                                        <strong>الإجمالي الجديد: <span id="newTotalBalance">{{ $balance->getTotalAvailableBalance() }}</span> يوم</strong>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-1">
                                    <div class="alert alert-secondary mb-0">
                                        <strong>المستخدم: {{ $balance->used_balance }} يوم</strong>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-1">
                                    <div class="alert alert-success mb-0" id="newRemainingAlert">
                                        <strong>المتبقي الجديد: <span id="newRemainingBalance">{{ $balance->getActualRemainingBalance() }}</span> يوم</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- تحذير إذا كان الرصيد الجديد أقل من المستخدم --}}
                        <div id="insufficientBalanceWarning" class="col-md-12 mb-3" style="display: none;">
                            <div class="alert alert-danger mb-0">
                                <i class="feather icon-alert-triangle"></i>
                                <strong>تحذير:</strong> الرصيد الإجمالي الجديد أقل من الرصيد المستخدم حالياً.
                                هذا قد يؤثر على الطلبات المعتمدة للموظف.
                            </div>
                        </div>

                        <div class="col-12 mb-0">
                            <label for="notes" class="form-label">الملاحظات</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3"
                                      placeholder="أدخل سبب التعديل أو أي ملاحظات إضافية...">{{ old('notes', $balance->notes) }}</textarea>
                            <small class="stk-help">يُنصح بذكر سبب التعديل في الملاحظات</small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endpermission

    {{-- معلومات الاستخدام إذا كان هناك طلبات معتمدة --}}
    @if($balance->used_balance > 0)
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-alert-triangle"></i> معلومات الاستخدام</h4>
            </div>
            <div class="card-body">
                <dl class="stk-def">
                    <div class="stk-def__row">
                        <dt>الطلبات المعتمدة</dt>
                        <dd class="stk-num">{{ $approvedRequestsCount ?? 0 }} طلب</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>إجمالي الأيام المستخدمة</dt>
                        <dd class="stk-num">{{ $balance->used_balance }} يوم</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>آخر استخدام</dt>
                        <dd class="stk-num">{{ $lastUsageDate ?? 'غير متوفر' }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>معدل الاستخدام</dt>
                        <dd class="stk-num">{{ $usagePercentage ?? 0 }}%</dd>
                    </div>
                </dl>
                <small class="text-warning">
                    <i class="feather icon-alert-triangle"></i>
                    تنبيه: هذا الرصيد مُستخدم حالياً. التعديلات قد تؤثر على حسابات الإجازات الحالية.
                </small>
            </div>
        </div>
    @endif

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
</div>
@include('components.hint-tips')
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('editLeaveBalanceForm');
        const initialBalanceInput = document.getElementById('initial_balance');
        const carriedForwardInput = document.getElementById('carried_forward');
        const additionalBalanceInput = document.getElementById('additional_balance');
        const usedBalance = {{ $balance->used_balance }};

        const originalValues = {
            year: {{ $balance->year }},
            initial_balance: {{ $balance->initial_balance }},
            carried_forward: {{ $balance->carried_forward }},
            additional_balance: {{ $balance->additional_balance }},
            notes: '{{ $balance->notes ?? '' }}'
        };

        // Update balance calculations
        function updateBalanceCalculation() {
            const initial = parseInt(initialBalanceInput.value) || 0;
            const carried = parseInt(carriedForwardInput.value) || 0;
            const additional = parseInt(additionalBalanceInput.value) || 0;
            const newTotal = initial + carried + additional;
            const newRemaining = Math.max(0, newTotal - usedBalance);

            document.getElementById('newTotalBalance').textContent = newTotal;
            document.getElementById('newRemainingBalance').textContent = newRemaining;

            // Show warning if insufficient balance
            const warningDiv = document.getElementById('insufficientBalanceWarning');
            const remainingAlert = document.getElementById('newRemainingAlert');

            if (newTotal < usedBalance) {
                warningDiv.style.display = 'block';
                remainingAlert.className = 'alert alert-danger mb-0';
            } else {
                warningDiv.style.display = 'none';
                remainingAlert.className = 'alert alert-success mb-0';
            }
        }

        // Event listeners for balance calculation
        [initialBalanceInput, carriedForwardInput, additionalBalanceInput].forEach(input => {
            input.addEventListener('input', updateBalanceCalculation);
        });

        // Check if form has changes
        function hasChanges() {
            const currentValues = {
                year: parseInt(document.getElementById('year').value),
                initial_balance: parseInt(initialBalanceInput.value) || 0,
                carried_forward: parseInt(carriedForwardInput.value) || 0,
                additional_balance: parseInt(additionalBalanceInput.value) || 0,
                notes: document.getElementById('notes').value
            };

            return JSON.stringify(currentValues) !== JSON.stringify(originalValues);
        }

        // Cancel Button
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
                        window.location.href = "{{ route('employee_leave_balances.index') }}";
                    }
                });
            } else {
                window.location.href = "{{ route('employee_leave_balances.index') }}";
            }
        });

        // Update Button
        document.getElementById('updateBtn').addEventListener('click', function () {
            if (!hasChanges()) {
                Swal.fire({
                    title: 'لا توجد تغييرات!',
                    text: 'لم تقم بإجراء أي تغييرات على البيانات',
                    icon: 'info',
                    confirmButtonText: 'موافق',
                });
                return;
            }

            if (!validateForm()) {
                return;
            }

            let confirmText = 'هل تريد حفظ التغييرات على رصيد الإجازة؟';
            @if($balance->used_balance > 0)
                confirmText = 'هذا الرصيد مُستخدم حالياً. هل تريد المتابعة مع التحديث؟';
            @endif

            Swal.fire({
                title: 'تأكيد التحديث',
                text: confirmText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، احفظ التغييرات',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Additional warning for insufficient balance
                    const newTotal = (parseInt(initialBalanceInput.value) || 0) +
                                   (parseInt(carriedForwardInput.value) || 0) +
                                   (parseInt(additionalBalanceInput.value) || 0);

                    if (newTotal < usedBalance) {
                        Swal.fire({
                            title: 'تحذير: رصيد غير كافي!',
                            text: `الرصيد الجديد (${newTotal}) أقل من المستخدم (${usedBalance}). هذا قد يؤثر على الطلبات المعتمدة. هل تريد المتابعة؟`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'نعم، احفظ رغم التحذير',
                            cancelButtonText: 'إلغاء'
                        }).then((confirmResult) => {
                            if (confirmResult.isConfirmed) {
                                Swal.fire({
                                    title: 'جاري التحديث...',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                                form.submit();
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'جاري التحديث...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        form.submit();
                    }
                }
            });
        });

        // Form validation
        function validateForm() {
            let isValid = true;
            const year = document.getElementById('year');

            // Clear previous validation states
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Validate year
            if (!year.value) {
                year.classList.add('is-invalid');
                isValid = false;
            }

            // Validate initial balance
            if (!initialBalanceInput.value || parseInt(initialBalanceInput.value) < 0) {
                initialBalanceInput.classList.add('is-invalid');
                isValid = false;
            }

            // Validate numeric fields are not negative
            const numericFields = [carriedForwardInput, additionalBalanceInput];
            numericFields.forEach(field => {
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
                title: 'تم التحديث بنجاح!',
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

        // Warn before leaving page with unsaved changes
        window.addEventListener('beforeunload', function (e) {
            if (hasChanges()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Initialize balance calculation
        updateBalanceCalculation();
    });
</script>
@endsection
