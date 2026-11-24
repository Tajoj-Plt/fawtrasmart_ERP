@extends('hr::master')

@section('title', 'إضافة ماكينة جديدة')

@section('content')
    @include('hr::attendance._partials.styles')

@php
    $canSave = hasPermission('attendance_devices.create');
@endphp

<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة ماكينة جديدة',
        'icon' => 'monitor',
        'crumbs' => ['الرئيسية' => url('/'), 'الماكينات' => route('machines.index'), 'إضافة' => null],
        'actions' => '<button type="button" id="cancelBtn" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</button>'
            .($canSave ? '<button type="submit" id="saveBtn" form="machine-form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>' : ''),
    ])

    @include('layouts.alerts.error')

    @permission('attendance_devices.create')
        <form id="machine-form" action="{{ route('machines.store') }}" method="POST">
            @csrf

            @include('hr::attendance.settings.machines._form_fields', ['machine' => null])

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('machines.index') }}" class="btn btn-outline-secondary">
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
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('machine-form');
    const cancelBtn = document.getElementById('cancelBtn');

    // معالج زر الإلغاء
    cancelBtn.addEventListener('click', function() {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم فقدان جميع البيانات المدخلة',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، إلغاء',
            cancelButtonText: 'البقاء'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '{{ route("machines.index") }}';
            }
        });
    });

    // معالج إرسال الفورم
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // التحقق من صحة البيانات
        const name = document.getElementById('name').value.trim();
        const machineType = document.getElementById('machine_type').value;
        const hostName = document.getElementById('host_name').value.trim();
        const portNumber = document.getElementById('port_number').value;

        if (!name || !machineType || !hostName || !portNumber) {
            Swal.fire({
                title: 'خطأ!',
                text: 'يرجى ملء جميع الحقول المطلوبة',
                icon: 'error',
                confirmButtonText: 'موافق'
            });
            return;
        }

        // إظهار رسالة التأكيد
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حفظ بيانات الماكينة الجديدة',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، احفظ',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                // إظهار رسالة التحميل
                Swal.fire({
                    title: 'جاري الحفظ...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // إرسال النموذج
                this.submit();
            }
        });
    });

    // إظهار رسالة النجاح إذا كانت موجودة في الجلسة
    @if(session('success'))
        Swal.fire({
            title: 'تم بنجاح!',
            text: '{{ session('success') }}',
            icon: 'success',
            confirmButtonText: 'موافق'
        });
    @endif

    // إظهار رسالة الخطأ إذا كانت موجودة في الجلسة
    @if(session('error'))
        Swal.fire({
            title: 'خطأ!',
            text: '{{ session('error') }}',
            icon: 'error',
            confirmButtonText: 'موافق'
        });
    @endif
});
</script>
@endsection