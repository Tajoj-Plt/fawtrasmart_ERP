@extends('hr::master')

@section('title', 'ورديات متخصصة')

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة وردية متخصصة',
        'icon' => 'user-check',
        'crumbs' => ['الرئيسية' => url('/'), 'الورديات المتخصصة' => route('custom_shifts.index'), 'إضافة' => null],
        'actions' => (hasPermission('shifts.index') ? '<a href="'.route('custom_shifts.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>' : '')
            .'<button type="submit" form="addAttendanceForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>',
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @permission('shifts.create')
        <form action="{{ route('custom_shifts.store') }}" method="POST" id="addAttendanceForm">
            @csrf

            @include('hr::attendance.custom_shifts._form', ['customShift' => null])

            <div class="card">
                <div class="stk-form-foot">
                    @permission('shifts.index')
                        <a href="{{ route('custom_shifts.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ
                    </button>
                </div>
            </div>
        </form>
    @endpermission
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const radios = document.querySelectorAll('input[name="use_rules"]');
        const employeeSection = document.getElementById('employee');
        const rulesSection = document.getElementById('rules');
        const employeeInputs = employeeSection.querySelectorAll('input, select, textarea');
        const rulesInputs = rulesSection.querySelectorAll('input, select, textarea');

        function toggleInputs(section, inputs, isEnabled) {
            inputs.forEach(input => {
                if (isEnabled) {
                    input.removeAttribute('disabled');
                } else {
                    input.setAttribute('disabled', 'disabled');
                }
            });
        }

        radios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.id === 'customRadio1') {
                    employeeSection.style.display = 'none';
                    rulesSection.style.display = 'block';

                    toggleInputs(employeeSection, employeeInputs, false);
                    toggleInputs(rulesSection, rulesInputs, true);
                } else if (this.id === 'customRadio2') {
                    employeeSection.style.display = 'block';
                    rulesSection.style.display = 'none';

                    toggleInputs(employeeSection, employeeInputs, true);
                    toggleInputs(rulesSection, rulesInputs, false);
                }

                $('#employee-select-employees').select2({
                    allowClear: true
                });

                $('#employee-select-rules').select2({
                    allowClear: true
                });
            });
        });

        // عند تحميل الصفحة، تحقق من الحالة الحالية
        if (document.getElementById('customRadio1').checked) {
            toggleInputs(employeeSection, employeeInputs, false);
            toggleInputs(rulesSection, rulesInputs, true);
        } else if (document.getElementById('customRadio2').checked) {
            toggleInputs(employeeSection, employeeInputs, true);
            toggleInputs(rulesSection, rulesInputs, false);
        }

        // إضافة SweetAlert2 عند تقديم النموذج
        document.getElementById('addAttendanceForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // التحقق من صحة النموذج
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: 'يرجى ملء جميع الحقول الإلزامية',
                    confirmButtonText: 'حسناً'
                });
                return;
            }

            // إذا كان النموذج صالحاً، تقديمه
            Swal.fire({
                title: 'جاري الحفظ',
                text: 'يرجى الانتظار...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    // تقديم النموذج بعد تأكيد الحفظ
                    e.target.submit();
                }
            });
        });
    });
</script>
@endsection
