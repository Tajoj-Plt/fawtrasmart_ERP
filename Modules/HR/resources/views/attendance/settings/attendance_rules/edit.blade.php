@extends('hr::master')

@section('title', 'تعديل قواعد الحضور')

@section('content')
    @include('hr::attendance._partials.styles')

@php
    $canSave = hasPermission('attendance_rules.edit');
    $cancelLink = hasPermission('attendance_rules.index')
        ? '<a href="'.route('attendance-rules.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
        : '';
@endphp

<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل قاعدة الحضور: '.$attendanceRule->name,
        'icon' => 'edit-2',
        'crumbs' => ['الرئيسية' => url('/'), 'قواعد الحضور' => route('attendance-rules.index'), $attendanceRule->name => route('attendance-rules.show', $attendanceRule->id), 'تعديل' => null],
        'actions' => $cancelLink
            .($canSave ? '<button type="submit" form="editForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>' : ''),
    ])

    @include('layouts.alerts.error')

    @permission('attendance_rules.edit')
        <form id="editForm" action="{{ route('attendance-rules.update', $attendanceRule->id) }}" method="POST">
            @csrf
            @method('PUT')

            @include('hr::attendance.settings.attendance_rules._form_fields', ['rule' => $attendanceRule])

            <div class="card">
                <div class="stk-form-foot">
                    @permission('attendance_rules.index')
                        <a href="{{ route('attendance-rules.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ التعديلات
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
    $('#editForm').on('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم تحديث بيانات قاعدة الحضور',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، حفظ التعديلات',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'جاري التحديث...',
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