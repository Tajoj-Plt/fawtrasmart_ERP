@extends('onlinestore::master')

@section('title', 'إضافة مخزن جديد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_storehouses.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('stock.warehouses.create'))
                <button type="submit" form="storehouse_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة مخزن جديد',
            'icon' => 'package',
            'sub' => 'مخزن جديد تابع للمتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المخازن' => route('store_storehouses.index'),
                'إضافة مخزن جديد' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="storehouse_form" action="{{ route('store_storehouses.store') }}" method="POST">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات المخزن الجديد</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="name" class="stk-req">اسم المخزن</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}"
                                   placeholder="أدخل اسم المخزن" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group col-md-6">
                            <label for="status" class="stk-req">الحالة</label>
                            <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                                <option value="active" @selected(old('status') == 'active')>نشط</option>
                                <option value="inactive" @selected(old('status') == 'inactive')>غير نشط</option>
                                <option value="stopped" @selected(old('status') == 'stopped')>متوقف</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{--
                            فرع المخزن: كان يُكتب «الفرع الرئيسي» دائماً بلا حقل اختيار،
                            فمخزنُ فرعٍ آخر يُحفظ على الرئيسي ولا يظهر إلّا لمن يقف عليه.
                            المنسدلةُ فروعُ المستخدم وحدَها، والافتراضيُّ فرعُ الشريط.
                        --}}
                        <div class="form-group col-md-6">
                            <label for="branch_id" class="stk-req">الفرع</label>
                            <select class="form-control select2 @error('branch_id') is-invalid @enderror" id="branch_id" name="branch_id" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $defaultBranchId) === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <span class="stk-help">يظهر المخزن وأذونه لمن يقف على هذا الفرع أو يشمله نطاق بياناته.</span>
                            @error('branch_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        @include('onlinestore::components.assignee-field', [
                            'name' => 'assigned_employee_ids',
                            'employees' => $employees,
                            'selected' => $selectedAssignees,
                            'multiple' => true,
                            'label' => 'المسند إليهم',
                            'help' => 'الموظفون الذين يرون هذا المخزن وأذونه (ومعهم من يديرهم). يُسنَد إليك تلقائياً إن لم تختر أحداً من فريقك، وغيرُ المسنَد لا يراه إلا من يرى كل مخازن المتجر.',
                        ])
                        <div class="form-group col-12">
                            <label for="shipping_address">عنوان الشحن</label>
                            <textarea class="form-control @error('shipping_address') is-invalid @enderror"
                                      id="shipping_address" name="shipping_address" rows="3"
                                      placeholder="أدخل عنوان الشحن">{{ old('shipping_address') }}</textarea>
                            @error('shipping_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#branch_id').select2({ dir: 'rtl', width: '100%' });
    }

    // تأكيد الإرسال
    document.getElementById('storehouse_form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'تأكيد الإضافة',
            text: 'هل أنت متأكد من إضافة المخزن الجديد؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، أضف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
</script>
@endsection
