@extends('hr::master')

@section('title', 'إضافة قواعد الحضور')

@section('content')
    @include('hr::attendance._partials.styles')

@php
    $canSave = hasPermission('attendance_rules.create');
    $cancelLink = hasPermission('attendance_rules.index')
        ? '<a href="'.route('attendance-rules.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
        : '';
@endphp

<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة قاعدة حضور',
        'icon' => 'clipboard',
        'crumbs' => ['الرئيسية' => url('/'), 'قواعد الحضور' => route('attendance-rules.index'), 'إضافة' => null],
        'actions' => $cancelLink
            .($canSave ? '<button type="submit" form="createForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>' : ''),
    ])

    @include('layouts.alerts.error')

    @permission('attendance_rules.create')
        <form id="createForm" action="{{ route('attendance-rules.store') }}" method="POST">
            @csrf

            @include('hr::attendance.settings.attendance_rules._form_fields', ['rule' => null])

            <div class="card">
                <div class="stk-form-foot">
                    @permission('attendance_rules.index')
                        <a href="{{ route('attendance-rules.index') }}" class="btn btn-outline-secondary">
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        dir: 'rtl',
        language: 'ar'
    });

    // Color picker functionality
    $('#color').on('change', function() {
        $('#colorDisplay').val($(this).val());
    });

    // Form submission with SweetAlert
    $('#createForm').on('submit', function(e) {
        e.preventDefault();

        // Validate required fields
        let isValid = true;
        const requiredFields = ['name', 'status', 'shift_id'];

        requiredFields.forEach(function(field) {
            const fieldElement = document.getElementById(field === 'shift_id' ? 'shift' : field);
            if (!fieldElement.value.trim()) {
                isValid = false;
                fieldElement.classList.add('is-invalid');
            } else {
                fieldElement.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            Swal.fire({
                icon: 'error',
                title: 'خطأ!',
                text: 'يرجى ملء جميع الحقول المطلوبة',
                confirmButtonText: 'موافق'
            });
            return;
        }

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم إضافة قاعدة حضور جديدة',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، احفظ',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'جاري الحفظ...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Submit form
                this.submit();
            }
        });
    });

    // Display success message if exists
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'تم بنجاح!',
            text: '{{ session('success') }}',
            confirmButtonText: 'موافق'
        });
    @endif

    // Display error message if exists
    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'خطأ!',
            text: '{{ session('error') }}',
            confirmButtonText: 'موافق'
        });
    @endif

    // Display validation errors
    @if($errors->any())
        let errorMessages = '';
        @foreach($errors->all() as $error)
            errorMessages += '• {{ $error }}\n';
        @endforeach

        Swal.fire({
            icon: 'error',
            title: 'خطأ في البيانات المدخلة',
            text: errorMessages,
            confirmButtonText: 'موافق'
        });
    @endif
});
</script>
@endpush