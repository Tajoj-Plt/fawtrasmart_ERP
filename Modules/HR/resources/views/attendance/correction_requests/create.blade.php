@extends('hr::master')

@section('title', 'طلب تصحيح حضور')

@section('content')
<div class="content-body">
    {{-- زرُّ التقديم يبقى `button#saveButton` لا `submit`: السكربت يتحقّق ثمّ يطلب التأكيد قبل الإرسال --}}
    @include('stock::components.page-head', [
        'title' => 'طلب تصحيح حضور',
        'icon' => 'edit-3',
        'crumbs' => ['الرئيسية' => url('/'), 'طلبات تصحيح الحضور' => route('hr.attendance-corrections.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('hr.attendance-corrections.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="button" id="saveButton" class="btn btn-primary"><i class="feather icon-check"></i> تقديم الطلب</button>',
    ])

    @include('layouts.alerts.error')

    <p class="stk-help mb-1">الطلب لا يُطبَّق تلقائياً — ينتظر اعتماد صاحب صلاحية الاعتماد.</p>

    <form id="correctionRequestForm" action="{{ route('hr.attendance-corrections.store') }}" method="POST">
        @csrf

        @if ($errors->any())
            <div class="stk-form-errors alert alert-danger" role="alert">
                <div class="stk-form-errors__head">
                    <i class="feather icon-alert-circle"></i> لم يُقدَّم الطلب — راجع الحقول المعلَّمة:
                </div>
                <ul class="stk-form-errors__list mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h4 class="card-title"><i class="feather icon-calendar"></i> اليوم المطلوب تصحيحه</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 col-xl-4 mb-3">
                        <label for="employee-select" class="form-label stk-req">الموظف</label>
                        <select id="employee-select" name="employee_id" class="form-control select2" required>
                            <option value="" selected>اختر الموظف</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" {{ old('employee_id') == $employee->id ? 'selected' : '' }}>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6 col-xl-3 mb-3">
                        <label for="attendance-date" class="form-label stk-req">تاريخ الحضور</label>
                        <input type="date" id="attendance-date" name="attendance_date" class="form-control" value="{{ old('attendance_date') }}" max="{{ now()->toDateString() }}" required>
                        @error('attendance_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-12 col-xl-5 mb-3">
                        <label class="form-label stk-req d-block">الحالة المطلوبة</label>
                        <ul class="list-unstyled mb-0 pt-50">
                            <li class="d-inline-block mr-2">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="requested_status" id="reqStatusPresent" {{ old('requested_status') == 'present' ? 'checked' : '' }} value="present" required>
                                    <label class="custom-control-label" for="reqStatusPresent">حاضر</label>
                                </div>
                            </li>
                            <li class="d-inline-block mr-2">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="requested_status" id="reqStatusAbsent" {{ old('requested_status') == 'absent' ? 'checked' : '' }} value="absent" required>
                                    <label class="custom-control-label" for="reqStatusAbsent">إجازة</label>
                                </div>
                            </li>
                            <li class="d-inline-block mr-2">
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" name="requested_status" id="reqStatusLate" {{ old('requested_status') == 'late' ? 'checked' : '' }} value="late" required>
                                    <label class="custom-control-label" for="reqStatusLate">متأخر</label>
                                </div>
                            </li>
                        </ul>
                        @error('requested_status')
                            <small class="text-danger d-block mt-1">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-12 mb-0">
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
            <div class="card-header"><h4 class="card-title"><i class="feather icon-clock"></i> القيم المطلوبة</h4></div>
            <div class="card-body">
                <div id="present-fields" style="display: none">
                    <div class="row">
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label class="form-label">بداية الوردية</label>
                            <input type="time" name="requested_start_shift" class="form-control" value="{{ old('requested_start_shift') }}">
                            @error('requested_start_shift')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label class="form-label">نهاية الوردية</label>
                            <input type="time" name="requested_end_shift" class="form-control" value="{{ old('requested_end_shift') }}">
                            @error('requested_end_shift')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label class="form-label stk-req">وقت الحضور المطلوب</label>
                            <input type="time" name="requested_check_in_time" class="form-control" value="{{ old('requested_check_in_time') }}">
                            @error('requested_check_in_time')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label class="form-label stk-req">وقت الانصراف المطلوب</label>
                            <input type="time" name="requested_check_out_time" class="form-control" value="{{ old('requested_check_out_time') }}">
                            @error('requested_check_out_time')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>

                <div id="absent-fields" style="display: none">
                    <div class="row">
                        <div class="col-md-6 col-xl-4 mb-3">
                            <label class="form-label stk-req">نوع الإجازة</label>
                            <select name="requested_absence_type" class="form-control">
                                <option value="" disabled selected>اختر نوع الإجازة</option>
                                <option value="1" {{ old('requested_absence_type') == 1 ? 'selected' : '' }}>إجازة اعتيادية</option>
                                <option value="2" {{ old('requested_absence_type') == 2 ? 'selected' : '' }}>إجازة عرضية</option>
                            </select>
                            @error('requested_absence_type')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-6 col-xl-3 mb-3">
                            <label class="form-label stk-req">رصيد الإجازة</label>
                            <input type="number" name="requested_absence_balance" class="form-control" value="{{ old('requested_absence_balance', 1) }}">
                            @error('requested_absence_balance')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <label for="requested_notes" class="form-label">ملاحظات على السجل</label>
                        <textarea name="requested_notes" id="requested_notes" class="form-control" rows="2">{{ old('requested_notes') }}</textarea>
                        <small class="stk-help">حقول الأوقات تظهر عند اختيار «حاضر»، وحقول الإجازة عند اختيار «إجازة».</small>
                        @error('requested_notes')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('hr.attendance-corrections.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="button" id="saveButtonFoot" class="btn btn-primary">
                    <i class="feather icon-check"></i> تقديم الطلب
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('input[name="requested_status"]');
            const presentFields = document.getElementById('present-fields');
            const absentFields = document.getElementById('absent-fields');
            const saveButton = document.getElementById('saveButton');
            const saveButtonFoot = document.getElementById('saveButtonFoot');
            const form = document.getElementById('correctionRequestForm');

            function toggleSections(status) {
                presentFields.style.display = status === 'present' ? 'block' : 'none';
                absentFields.style.display = status === 'absent' ? 'block' : 'none';
            }

            radios.forEach(radio => {
                radio.addEventListener('change', () => toggleSections(radio.value));
                if (radio.checked) toggleSections(radio.value);
            });

            function submitWithConfirm(e) {
                e.preventDefault();

                if (!form.checkValidity()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تحذير!',
                        text: 'يرجى ملء جميع الحقول المطلوبة',
                        confirmButtonText: 'موافق',
                    });
                    form.reportValidity();
                    return;
                }

                Swal.fire({
                    title: 'تأكيد تقديم الطلب',
                    text: 'سيُرسَل هذا كطلبٍ بانتظار الاعتماد — ولن يُطبَّق تلقائياً.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، أرسل الطلب',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }

            saveButton.addEventListener('click', submitWithConfirm);
            saveButtonFoot.addEventListener('click', submitWithConfirm);
        });
    </script>
@endsection
