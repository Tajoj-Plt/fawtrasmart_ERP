@extends('hr::master')

@section('title', 'تعديل الماكينة')

@section('content')
    @include('hr::attendance._partials.styles')

@php
    $canSave = hasPermission('attendance_devices.edit');
@endphp

<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل ماكينة: '.$machine->name,
        'icon' => 'edit-2',
        'crumbs' => ['الرئيسية' => url('/'), 'الماكينات' => route('machines.index'), $machine->name => route('machines.show', $machine->id), 'تعديل' => null],
        'actions' => '<button type="button" id="cancelBtn" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</button>'
            .($canSave ? '<button type="submit" form="machine-edit-form" class="btn btn-primary"><i class="feather icon-check"></i> تحديث</button>' : ''),
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @permission('attendance_devices.edit')
        <form id="machine-edit-form" action="{{ route('machines.update', $machine->id) }}" method="POST" class="form">
            @csrf
            @method('PUT')

            @include('hr::attendance.settings.machines._form_fields', ['machine' => $machine])

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('machines.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> تحديث
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
    const form = document.getElementById('machine-edit-form');
    const cancelBtn = document.getElementById('cancelBtn');

    // معالج زر الإلغاء
    cancelBtn.addEventListener('click', function() {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم فقدان جميع التغييرات غير المحفوظة',
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
            text: 'سيتم تحديث بيانات الماكينة',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، حدث',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                // إظهار رسالة التحميل
                Swal.fire({
                    title: 'جاري التحديث...',
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