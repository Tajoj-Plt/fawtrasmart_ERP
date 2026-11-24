@extends('hr::master')

@section('title', 'طلب تصحيح يوم حضور')

{{--
    زرّا الترويسة يُطلقان زرَّي التذييل: `#updateButton` هو من يحمل تأكيد
    SweetAlert، فلا يُكرَّر مستمعُه هنا.
--}}
@section('page-actions')
    @permission('attendance_days.index')
        <a href="{{ route('attendanceDays.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
    @endpermission
    @permission('attendance_corrections.create')
        <button type="button" class="btn btn-primary" onclick="document.getElementById('updateButton').click()">
            <i class="feather icon-check"></i> تقديم طلب التصحيح
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'طلب تصحيح يوم حضور',
        'icon' => 'calendar',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أيام الحضور' => route('attendanceDays.index'),
            '#'.$attendanceDay->id => hasPermission('attendance_days.show') ? route('attendanceDays.show', $attendanceDay->id) : null,
            'طلب تصحيح' => null,
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @permission('attendance_corrections.create')
        <form id="updateAttendanceForm" action="{{ route('attendanceDays.update', $attendanceDay->id) }}"
            method="POST">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="stk-form-errors alert alert-danger" role="alert">
                    <div class="stk-form-errors__head">
                        <i class="feather icon-alert-circle"></i> لم يُقدَّم الطلب — راجع ما يلي:
                    </div>
                    <ul class="stk-form-errors__list mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="alert alert-info">
                <i class="feather icon-info"></i>
                هذا النموذج يُقدِّم طلباً بانتظار الاعتماد — لا يُعدِّل السجل مباشرةً.
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-2">
                            <label for="employee-select" class="form-label stk-req">الموظف</label>
                            <select id="employee-select" name="employee_id" class="form-control select2" required>
                                <option value="" disabled>اختر الموظف</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                        {{ $attendanceDay->employee_id == $employee->id ? 'selected' : '' }}>
                                        {{ $employee->full_name }}</option>
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
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-6 col-xl-3 mb-2">
                            <label for="attendance-date" class="form-label stk-req">تاريخ الحضور</label>
                            <input type="date" id="attendance-date" name="attendance_date" class="form-control"
                                placeholder="ادخل تاريخ الحضور"
                                value="{{ old('attendance_date', $attendanceDay->attendance_date) }}" required>
                            @error('attendance_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-12 col-xl-5 mb-2">
                            <label class="form-label stk-req">الحالة المطلوبة</label>
                            <div class="d-flex flex-wrap" style="gap: 1.5rem; padding-top: .5rem">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="requested_status"
                                        id="customRadio1"
                                        {{ old('requested_status', $attendanceDay->status) == 'present' ? 'checked' : '' }}
                                        value="present" required>
                                    <label class="custom-control-label" for="customRadio1">حاضر</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="requested_status"
                                        id="customRadio2"
                                        {{ old('requested_status', $attendanceDay->status) == 'absent' ? 'checked' : '' }}
                                        value="absent" required>
                                    <label class="custom-control-label" for="customRadio2">إجازة</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="requested_status"
                                        id="customRadio3"
                                        {{ old('requested_status', $attendanceDay->status) == 'late' ? 'checked' : '' }}
                                        value="late" required>
                                    <label class="custom-control-label" for="customRadio3">غائب</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mb-2">
                            <label for="reason" class="form-label stk-req">سبب طلب التصحيح</label>
                            <textarea name="reason" id="reason" class="form-control" rows="2" placeholder="مثال: نسي الموظف تسجيل الحضور بالباركود" required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-clock"></i> التفاصيل المطلوبة</h4></div>
                <div class="card-body">
                    <div id="attendance-rules" style="display: none">
                        <div class="row">
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="start-shift" class="form-label stk-req">بداية الوردية</label>
                                <input type="time" id="start-shift" name="requested_start_shift" class="form-control"
                                    value="{{ old('requested_start_shift', $attendanceDay->start_shift) }}">
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="end-shift" class="form-label stk-req">نهاية الوردية</label>
                                <input type="time" id="end-shift" name="requested_end_shift" class="form-control"
                                    value="{{ old('requested_end_shift', $attendanceDay->end_shift) }}">
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="login-time" class="form-label stk-req">تسجيل الدخول</label>
                                <input type="time" id="login-time" name="requested_check_in_time" class="form-control"
                                    value="{{ old('requested_check_in_time', $attendanceDay->check_in_time) }}">
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="logout-time" class="form-label stk-req">تسجيل الخروج</label>
                                <input type="time" id="logout-time" name="requested_check_out_time" class="form-control"
                                    value="{{ old('requested_check_out_time', $attendanceDay->check_out_time) }}">
                            </div>
                        </div>
                    </div>

                    <div id="vacation-rules" style="display: none">
                        <div class="row">
                            <div class="col-md-6 col-xl-4 mb-2">
                                <label for="absence-type" class="form-label stk-req">نوع الاجازة</label>
                                <select id="absence-type" name="requested_absence_type" class="form-control">
                                    <option value="" disabled>اختر نوع الاجازة</option>
                                    <option value="1"
                                        {{ old('requested_absence_type', $attendanceDay->absence_type) == 1 ? 'selected' : '' }}>
                                        اجازة اعتيادية</option>
                                    <option value="2"
                                        {{ old('requested_absence_type', $attendanceDay->absence_type) == 2 ? 'selected' : '' }}>
                                        اجازة عرضية</option>
                                </select>
                                @error('requested_absence_type')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="absence-balance" class="form-label stk-req">رصيد الاجازة</label>
                                <input type="number" id="absence-balance" name="requested_absence_balance" class="form-control"
                                    value="{{ old('requested_absence_balance', $attendanceDay->absence_balance) }}">
                                @error('requested_absence_balance')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <label for="requested_notes" class="form-label">الملاحظات</label>
                            <textarea id="requested_notes" name="requested_notes" class="form-control" rows="3" placeholder="أدخل الملاحظات هنا">{{ old('requested_notes', $attendanceDay->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    @permission('attendance_days.index')
                        <a href="{{ route('attendanceDays.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="button" id="updateButton" class="btn btn-primary">
                        <i class="feather icon-check"></i> تقديم طلب التصحيح
                    </button>
                </div>
            </div>
        </form>
    @endpermission
</div>
@endsection

@section('scripts')
    <!-- SweetAlert2 Library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const employeeSelect = document.getElementById('employee-select');
            const attendanceDate = document.querySelector('input[name="attendance_date"]');
            const radios = document.querySelectorAll('input[name="requested_status"]');
            const attendanceRules = document.getElementById('attendance-rules');
            const vacationRules = document.getElementById('vacation-rules');
            const updateButton = document.getElementById('updateButton');
            const form = document.getElementById('updateAttendanceForm');

            function toggleRadios() {
                const isEmployeeSelected = employeeSelect.value !== '';
                const isDateSelected = attendanceDate.value !== '';
                radios.forEach(radio => {
                    radio.disabled = !(isEmployeeSelected && isDateSelected);
                });

                const selectedStatus = document.querySelector('input[name="requested_status"]:checked');
                if (selectedStatus) {
                    toggleSections(selectedStatus.value);
                }
            }

            function toggleSections(status) {
                // الحصول على العناصر
                const startShiftInput = document.querySelector('input[name="requested_start_shift"]');
                const endShiftInput = document.querySelector('input[name="requested_end_shift"]');
                const loginTimeInput = document.querySelector('input[name="requested_check_in_time"]');
                const logoutTimeInput = document.querySelector('input[name="requested_check_out_time"]');
                const absenceTypeSelect = document.querySelector('select[name="requested_absence_type"]');
                const absenceBalanceInput = document.querySelector('input[name="requested_absence_balance"]');

                if (status === 'present') { // حاضر
                    attendanceRules.style.display = 'block';
                    vacationRules.style.display = 'none';

                    // جعل حقول الحضور مطلوبة
                    if (startShiftInput) startShiftInput.required = true;
                    if (endShiftInput) endShiftInput.required = true;
                    if (loginTimeInput) loginTimeInput.required = true;
                    if (logoutTimeInput) logoutTimeInput.required = true;

                    // إزالة required من حقول الإجازة
                    if (absenceTypeSelect) absenceTypeSelect.required = false;
                    if (absenceBalanceInput) absenceBalanceInput.required = false;

                } else if (status === 'absent') { // إجازة
                    attendanceRules.style.display = 'none';
                    vacationRules.style.display = 'block';

                    // إزالة required من حقول الحضور
                    if (startShiftInput) startShiftInput.required = false;
                    if (endShiftInput) endShiftInput.required = false;
                    if (loginTimeInput) loginTimeInput.required = false;
                    if (logoutTimeInput) logoutTimeInput.required = false;

                    // جعل حقول الإجازة مطلوبة
                    if (absenceTypeSelect) absenceTypeSelect.required = true;
                    if (absenceBalanceInput) absenceBalanceInput.required = true;

                } else { // غائب أو حالة أخرى
                    attendanceRules.style.display = 'none';
                    vacationRules.style.display = 'none';

                    // إزالة required من جميع الحقول الاختيارية
                    if (startShiftInput) startShiftInput.required = false;
                    if (endShiftInput) endShiftInput.required = false;
                    if (loginTimeInput) loginTimeInput.required = false;
                    if (logoutTimeInput) logoutTimeInput.required = false;
                    if (absenceTypeSelect) absenceTypeSelect.required = false;
                    if (absenceBalanceInput) absenceBalanceInput.required = false;
                }
            }

            // إضافة مستمعين للأحداث
            employeeSelect.addEventListener('change', toggleRadios);
            attendanceDate.addEventListener('input', toggleRadios);

            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    toggleSections(this.value);
                });
            });

            // SweetAlert2 للتأكيد قبل الإرسال
            updateButton.addEventListener('click', function(e) {
                e.preventDefault();

                // التحقق من صحة النموذج باستخدام HTML5 validation
                if (!form.checkValidity()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تحذير!',
                        text: 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح',
                        confirmButtonText: 'موافق',
                    });
                    form.reportValidity();
                    return;
                }

                // عرض تأكيد الإرسال
                Swal.fire({
                    title: 'تأكيد تقديم الطلب',
                    text: 'سيُرسَل هذا كطلبِ تصحيحٍ بانتظار الاعتماد — ولن يُطبَّق تلقائياً على السجل.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، أرسل الطلب',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'جاري الإرسال...',
                            text: 'يرجى الانتظار',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        form.submit();
                    }
                });
            });

            // تشغيل التحديث الأولي
            toggleRadios();

            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'تم الإرسال بنجاح!',
                    text: '{{ session('success') }}',
                    confirmButtonText: 'موافق',
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: '{{ session('error') }}',
                    confirmButtonText: 'موافق',
                });
            @endif
        });
    </script>
@endsection
