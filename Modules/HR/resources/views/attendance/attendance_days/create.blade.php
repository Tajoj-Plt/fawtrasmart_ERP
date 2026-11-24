@extends('hr::master')

@section('title', 'أيام الحضور - إضافة')

{{--
    زرّا الترويسة يُطلقان زرَّي التذييل: `#saveButton` هو من يحمل تأكيد
    SweetAlert، فلا يُكرَّر مستمعُه هنا.
--}}
@section('page-actions')
    @permission('attendance_days.index')
        <a href="{{ route('attendanceDays.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
    @endpermission
    @permission('attendance_days.edit')
        <button type="button" class="btn btn-primary" onclick="document.getElementById('saveButton').click()">
            <i class="feather icon-check"></i> حفظ
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة يوم حضور',
        'icon' => 'calendar',
        'crumbs' => ['الرئيسية' => url('/'), 'أيام الحضور' => route('attendanceDays.index'), 'إضافة' => null],
    ])

    @include('layouts.alerts.error')

    @permission('attendance_days.edit')
        <form id="addAttendanceForm" action="{{ route('attendanceDays.store') }}" method="POST">
            @csrf

            @if ($errors->any())
                <div class="stk-form-errors alert alert-danger" role="alert">
                    <div class="stk-form-errors__head">
                        <i class="feather icon-alert-circle"></i> لم يُحفظ يوم الحضور — راجع ما يلي:
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
                اليوم المُدخَل هنا يُرسَل <strong>طلب تصحيح</strong> ويظهر في الحضور اليومي بعد اعتماده. لا يُقبل لتاريخ لم يأتِ، ولا ليوم عطلة أو إجازة.
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-2">
                            <label for="employee-select" class="form-label stk-req">الموظف</label>
                            <select id="employee-select" name="employee_id" class="form-control select2" required>
                                <option value="" selected>اختر الموظف</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
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
                            <input type="date" id="attendance-date" name="attendance_date" class="form-control" placeholder="ادخل تاريخ الحضور" value="{{ old('attendance_date' ) }}" required>
                            @error('attendance_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-12 col-xl-5 mb-2">
                            <label class="form-label stk-req">الحالة</label>
                            <div class="d-flex flex-wrap" style="gap: 1.5rem; padding-top: .5rem">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="status" id="customRadio1" {{ old('status') == 'present' ? 'checked' : '' }} value="present" required>
                                    <label class="custom-control-label" for="customRadio1">حاضر</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="status" id="customRadio2" {{ old('status') == 'absent' ? 'checked' : '' }} value="absent" required>
                                    <label class="custom-control-label" for="customRadio2">إجازة</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="status" id="customRadio3" {{ old('status') == 'late' ? 'checked' : '' }} value="late" required>
                                    <label class="custom-control-label" for="customRadio3">غائب</label>
                                </div>
                            </div>
                            <small class="stk-help">تُفعَّل الحالة بعد اختيار الموظف والتاريخ.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-clock"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <div id="attendance-rules" style="display: none">
                        <div class="row">
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="start-shift" class="form-label stk-req">بداية الوردية</label>
                                <input type="time" id="start-shift" name="start_shift" class="form-control" value="{{ old('start_shift') }}">
                                @error('start_shift')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="end-shift" class="form-label stk-req">نهاية الوردية</label>
                                <input type="time" id="end-shift" name="end_shift" class="form-control" value="{{ old('end_shift') }}">
                                @error('end_shift')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="login-time" class="form-label stk-req">تسجيل الدخول</label>
                                <input type="time" id="login-time" name="check_in_time" class="form-control" value="{{ old('check_in_time') }}">
                                @error('check_in_time')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="logout-time" class="form-label stk-req">تسجيل الخروج</label>
                                <input type="time" id="logout-time" name="check_out_time" class="form-control" value="{{ old('check_out_time') }}">
                                @error('check_out_time')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div id="vacation-rules" style="display: none">
                        <div class="row">
                            <div class="col-md-6 col-xl-4 mb-2">
                                <label for="absence-type" class="form-label stk-req">نوع الاجازة</label>
                                <select id="absence-type" name="absence_type" class="form-control">
                                    <option value="" disabled selected>اختر نوع الاجازة</option>
                                    <option value="1" {{ old('absence_type') == 1 ? 'selected' : '' }}>اجازة اعتيادية</option>
                                    <option value="2" {{ old('absence_type') == 2 ? 'selected' : '' }}>اجازة عرضية</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-xl-3 mb-2">
                                <label for="absence-balance" class="form-label stk-req">رصيد الاجازة</label>
                                <input type="number" id="absence-balance" name="absence_balance" class="form-control" value="{{ old('absence_balance', 1) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <label for="notes" class="form-label">الملاحظات</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="أدخل الملاحظات هنا">{{ old('notes') }}</textarea>
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
                    <button type="button" id="saveButton" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ
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
        document.addEventListener('DOMContentLoaded', function () {
            const employeeSelect = document.getElementById('employee-select');
            const attendanceDate = document.querySelector('input[name="attendance_date"]');
            const radios = document.querySelectorAll('input[name="status"]');
            const attendanceRules = document.getElementById('attendance-rules');
            const vacationRules = document.getElementById('vacation-rules');
            const saveButton = document.getElementById('saveButton');
            const form = document.getElementById('addAttendanceForm');

            // تعطيل الحالة عند تحميل الصفحة
            radios.forEach(radio => radio.disabled = true);

            // تفعيل الحالة إذا تم تحديد الموظف والتاريخ
            function toggleRadios() {
                const isEmployeeSelected = employeeSelect.value !== '';
                const isDateSelected = attendanceDate.value !== '';
                radios.forEach(radio => radio.disabled = !(isEmployeeSelected && isDateSelected));
            }

            employeeSelect.addEventListener('change', toggleRadios);
            attendanceDate.addEventListener('input', toggleRadios);

            // عرض الأقسام بناءً على اختيار الحالة
            radios.forEach(radio => {
                radio.addEventListener('change', function () {
                    if (this.id === 'customRadio1') { // حاضر
                        attendanceRules.style.display = 'block';
                        vacationRules.style.display = 'none';
                    } else if (this.id === 'customRadio2') { // إجازة
                        attendanceRules.style.display = 'none';
                        vacationRules.style.display = 'block';
                    } else { // غائب أو حالة أخرى
                        attendanceRules.style.display = 'none';
                        vacationRules.style.display = 'none';
                    }
                });
            });

            // SweetAlert2 للتأكيد قبل الحفظ
            saveButton.addEventListener('click', function(e) {
                e.preventDefault();

                // التحقق من صحة النموذج
                if (!form.checkValidity()) {
                    // عرض رسالة خطأ إذا كانت الحقول المطلوبة فارغة
                    Swal.fire({
                        icon: 'warning',
                        title: 'تحذير!',
                        text: 'يرجى ملء جميع الحقول المطلوبة',
                        confirmButtonText: 'موافق',
                    });
                    form.reportValidity();
                    return;
                }

                // عرض تأكيد الحفظ
                Swal.fire({
                    title: 'تأكيد الحفظ',
                    text: 'هل أنت متأكد من حفظ بيانات الحضور؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // عرض loading أثناء الحفظ
                        Swal.fire({
                            title: 'جاري الحفظ...',
                            text: 'يرجى الانتظار',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // إرسال النموذج
                        form.submit();
                    }
                });
            });

            // التحقق من وجود رسالة نجاح من الخادم
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'تم الحفظ بنجاح!',
                    text: '{{ session('success') }}',
                    confirmButtonText: 'موافق',
                });
            @endif

            // التحقق من وجود رسالة خطأ من الخادم
            @if(session('error'))
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
