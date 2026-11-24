@extends('hr::master')

@section('title', 'الإعدادات الأساسية')

@section('css')
    <style>
        .employee-select { display: none; }
    </style>
@endsection

@section('content')
    @include('hr::attendance._partials.styles')


        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-attendance-basic.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه الإعداداتُ الأساسية للحضور: ما يسري افتراضاً على الجميع ما لم تُخصَّص قاعدةٌ أضيقُ منه.',
            'blocks' => [
                'موضعُها من بقيّة الإعدادات' => [
                    'ما هنا هو الأساسُ العامّ. وقواعدُ الحضور والورديّاتُ والمحدِّداتُ المخصَّصةُ تتقدّم عليه لمن تنطبق عليه.',
                    'فتغييرُ إعدادٍ أساسيٍّ يسري على كلّ من لا قاعدةَ أخصَّ له — وهم الأكثرون عادةً.',
                ],
                'ما يترتّب' => [
                    'الأثرُ على ما يُحتسَب بعد التغيير؛ والأيامُ المحسوبةُ سلفاً تبقى على ما حُسبت به.',
                ],
            ],
            'facts' => [
                'الأساسُ يسري على من لا قاعدةَ أخصَّ له',
                'الأثرُ على ما يُحتسَب بعده',
            ],
        ])


    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="feather icon-settings"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">الإعدادات الأساسية</h2>
                <div class="stk-page-head__sub">بداية السنة المالية + نمط الموافقات + إعدادات عامة</div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <label>الحقول التي عليها علامة <span style="color: red">*</span> الزامية</label>
                </div>
                <div>
                    @permission('attendance_settings.manage')
                        <a href="{{ route('attendance.settings.index') }}" class="btn btn-outline-danger">
                            <i class="feather icon-slash"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="submit" form="SettingsBasic" class="btn btn-outline-primary">
                        <i class="feather icon-save"></i> حفظ
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mt-5">
        <div class="card-body">
            <h4 class="mb-2 p-1" style="background: var(--app-body-bg, #f8f8f8)">إعدادات الحضور</h4>
            @permission('attendance_settings.manage')
                <form id="SettingsBasic" method="POST" action="{{ route('settings_basic.update') }}">
                    @csrf
                    <div class="mb-2 row">
                        <div class="col-sm-6">
                            <label for="monthStart" class="form-label stk-req">شهر بداية السنة المالية</label>
                            <select id="monthStart" class="form-control" name="start_month">
                                @foreach (range(1, 12) as $month)
                                    <option value="{{ $month }}" {{ isset($attendanceSettings) && $month == $attendanceSettings->start_month ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create()->month($month)->locale('ar')->monthName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="dayStart" class="form-label stk-req">يوم بداية السنة المالية</label>
                            <select id="dayStart" class="form-control" name="start_day" {{ old('start_day', $attendanceSettings->start_day) ? 'value="' . old('start_day') . '"' : '' }}></select>
                        </div>
                    </div>
    
                    <p><small>الوردية الثانية :</small></p>
                    <div class="mb-2">
                        <label class="stk-switch mb-0">
                            <input type="checkbox" id="enableShiftTwo" name="allow_second_shift" {{ old('allow_second_shift', $attendanceSettings->allow_second_shift == 1) ? 'checked' : '' }}>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">إتاحة الوردية الثانية للموظفين</span>
                            </span>
                        </label>
                    </div>
    
                    <h4 class="mb-2 p-1" style="background: var(--app-body-bg, #f8f8f8)">إعدادات طلب الإجازة</h4>
    
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="mb-2">
                                <label class="stk-switch mb-0">
                                    <input type="checkbox" id="allowBackdatedRequests" name="allow_backdated_requests" {{ old('allow_backdated_requests', $attendanceSettings->allow_backdated_requests == 1) ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">السماح بإدخال طلبات الإجازة لتواريخ سابقة</span>
                                    </span>
                                </label>
                            </div>
                        </div>
    
                        <div class="col-sm-12">
                            <p><small>الموافقة او رفض طلبات الأجازة بأي من :</small></p>
                        </div>
    
                        <div class="col-sm-6">
                            <div class="mb-2">
                                <label class="stk-switch mb-0">
                                    <input type="checkbox" id="directManagersApproval" name="direct_manager_approval" {{ old('direct_manager_approval', $attendanceSettings->direct_manager_approval == 1) ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">المديرين المباشرين</span>
                                    </span>
                                </label>
                            </div>
                        </div>
    
                        <div class="col-sm-6">
                            <div class="mb-2">
                                <label class="stk-switch mb-0">
                                    <input type="checkbox" id="departmentManagersApproval" name="department_manager_approval" {{ old('department_manager_approval', $attendanceSettings->department_manager_approval == 1) ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">مديري <a href="{{ route('department.index') }}" target="_blank">الأقسام <i class="feather icon-link"></i></a></span>
                                    </span>
                                </label>
                            </div>
                        </div>
    
                        <div class="col-sm-6">
                            <div class="mb-2">
                                <label class="stk-switch mb-0">
                                    <input type="checkbox" id="employeesApproval" name="employees_approval" {{ old('employees_approval', $attendanceSettings->employees_approval == 1) ? 'checked' : '' }}>
                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                    <span class="stk-switch__label">
                                        <span class="stk-switch__title">الموظفين</span>
                                    </span>
                                </label>
                            </div>
                        </div>
    
                        <div class="col-sm-6"></div>
    
                        @php
                            $selectedEmployees = $attendanceSettings->employees->pluck('id')->toArray();
                        @endphp
                        <div class="col-md-6 employee-select">
                            <label for="employee_id">اختر الموظفين:</label>
                            <select name="employee_id[]" id="employee_id" class="form-control select2" multiple="multiple">
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" {{ in_array($employee->id, $selectedEmployees) ? 'selected' : '' }}>
                                        {{ $employee->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
    
                    </div>
    
                </form>
            @endpermission
        </div>
    </div>

@endsection

@section('scripts')

    <script>
        // JavaScript لإعادة ضبط الأيام بناءً على الشهر والسنة
        document.addEventListener('DOMContentLoaded', function () {
            const monthSelect = document.getElementById('monthStart');
            const daySelect = document.getElementById('dayStart');

            // دالة لتحديد عدد الأيام في الشهر
            function getDaysInMonth(month, year) {
                return new Date(year, month, 0).getDate(); // تعيد عدد الأيام في الشهر
            }

            // عند تحميل الصفحة، تحديد اليوم المختار مسبقًا إذا كان موجودًا
            function updateDays() {
                const year = new Date().getFullYear();
                const month = parseInt(monthSelect.value);
                const days = getDaysInMonth(month, year);

                daySelect.innerHTML = '';

                for (let day = 1; day <= days; day++) {
                    const option = document.createElement('option');
                    option.value = day;
                    option.textContent = day;

                    // تحديد اليوم المختار مسبقًا
                    if (day === {{ $attendanceSettings->start_day ?? 'null' }}) {
                        option.selected = true;
                    }

                    daySelect.appendChild(option);
                }
            }

            // استدعاء الدالة عند تغيير الشهر
            monthSelect.addEventListener('change', updateDays);

            // تهيئة الأيام عند تحميل الصفحة
            updateDays();
        });

        //-------------------------------------------------

        $(document).ready(function () {
            // عرض الحقل إذا كان "الموظفين" محددًا
            if ($("#employeesApproval").is(":checked")) {
                $(".employee-select").show();
            }

            // إظهار/إخفاء الحقل عند التبديل
            $("#employeesApproval").on("change", function () {
                $(".employee-select").toggle($(this).is(":checked"));
            });
        });

    </script>

@endsection
