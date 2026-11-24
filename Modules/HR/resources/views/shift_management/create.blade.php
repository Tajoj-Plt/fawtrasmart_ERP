@extends('hr::master')

@section('title')
    الورديات
@stop

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة وردية',
        'icon' => 'clock',
        'crumbs' => ['الرئيسية' => url('/'), 'الورديات' => route('shift_management.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('shift_management.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="shift_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>',
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @permission('shifts.create')
        <form id="shift_form" class="form" action="{{ route('shift_management.store') }}" method="POST">
            @csrf

            @include('hr::shift_management._form', ['shift' => null])

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('shift_management.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // العناصر
        const typeSelect = document.querySelector('select[name="type"]');
        const basicSection = document.getElementById('basic');
        const advancedSection = document.getElementById('advanced');

        // تحديث العرض وتمكين/تعطيل المدخلات بناءً على الاختيار
        function toggleSections() {
            if (typeSelect.value === 'basic') {
                basicSection.style.display = 'block';
                advancedSection.style.display = 'none';

                // تمكين المدخلات داخل القسم الأساسي
                enableInputs(basicSection, true);
                // تعطيل المدخلات داخل القسم المتقدم
                enableInputs(advancedSection, false);

            } else if (typeSelect.value === 'advanced') {
                advancedSection.style.display = 'block';
                basicSection.style.display = 'none';

                // تعطيل المدخلات داخل القسم الأساسي
                enableInputs(basicSection, false);
                // تمكين المدخلات داخل القسم المتقدم
                enableInputs(advancedSection, true);

                // تحديث حالة الحقول للأيام المتقدمة
                @php
                    $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                @endphp
                @foreach ($days as $dayKey)
                    toggleDayFields('{{ $dayKey }}');
                @endforeach
            }
        }

        function enableInputs(section, enabled) {
            const inputs = section.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.disabled = !enabled;
            });
        }

        // إضافة الحدث عند تغيير الخيار
        typeSelect.addEventListener('change', toggleSections);

        // استدعاء الوظيفة عند التحميل
        toggleSections();

        // التعامل مع إرسال النموذج
        const form = document.getElementById('shift_form');
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'سيتم حفظ بيانات الوردية الجديدة',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، احفظ',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    function toggleDayFields(dayKey) {
        const isChecked = document.getElementById(`${dayKey}_enabled`).checked;
        const fieldsContainer = document.getElementById(`${dayKey}_fields`);

        if (fieldsContainer) {
            fieldsContainer.style.display = isChecked ? 'flex' : 'none';
            const inputs = fieldsContainer.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.disabled = !isChecked;
                // إضافة أو إزالة required للحقول المطلوبة
                if (input.type === 'time' || input.name.includes('grace_period') || input.name.includes('delay_calculation')) {
                    if (isChecked) {
                        input.setAttribute('required', 'required');
                    } else {
                        input.removeAttribute('required');
                    }
                }
            });
        }
    }

    // معالجة رسائل النجاح والخطأ
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'تمت العملية بنجاح',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'حدث خطأ',
            text: '{{ session('error') }}',
            timer: 3000,
            showConfirmButton: false
        });
    @endif
</script>
@endsection
