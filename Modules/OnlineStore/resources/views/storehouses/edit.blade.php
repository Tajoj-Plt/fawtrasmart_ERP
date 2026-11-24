@extends('onlinestore::master')

@section('title', 'تعديل المخزن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('stock.warehouses.show'))
                <a href="{{ route('store_storehouses.show', $storehouse->id) }}" class="btn btn-outline-info">
                    <i class="feather icon-eye"></i> عرض
                </a>
            @endif
            <a href="{{ route('store_storehouses.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('stock.warehouses.edit'))
                <button type="submit" form="storehouse_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التعديلات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل المخزن',
            'icon' => 'package',
            'sub' => $storehouse->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'المخازن' => route('store_storehouses.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="storehouse_form" action="{{ route('store_storehouses.update', $storehouse->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات المخزن</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="name" class="stk-req">اسم المخزن</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $storehouse->name) }}"
                                   placeholder="أدخل اسم المخزن" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group col-md-6">
                            <label for="status" class="stk-req">الحالة</label>
                            <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                                <option value="active" @selected(old('status', $storehouse->status) == 'active')>نشط</option>
                                <option value="inactive" @selected(old('status', $storehouse->status) == 'inactive')>غير نشط</option>
                                <option value="stopped" @selected(old('status', $storehouse->status) == 'stopped')>متوقف</option>
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
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $storehouse->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
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
                            'help' => 'الموظفون الذين يرون هذا المخزن وأذونه (ومعهم من يديرهم). لا تستطيع نزع إسنادٍ لموظفٍ خارج فريقك، وغيرُ المسنَد لا يراه إلا من يرى كل مخازن المتجر.',
                        ])
                        <div class="form-group col-12">
                            <label for="shipping_address">عنوان الشحن</label>
                            <textarea class="form-control @error('shipping_address') is-invalid @enderror"
                                      id="shipping_address" name="shipping_address" rows="3"
                                      placeholder="أدخل عنوان الشحن">{{ old('shipping_address', $storehouse->shipping_address) }}</textarea>
                            @error('shipping_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @if ($storehouse->account)
                        <dl class="stk-def stk-def--inline mb-0">
                            <div class="stk-def__row">
                                <dt>كود الحساب</dt>
                                <dd class="stk-num">{{ $storehouse->account->code }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الرصيد الحالي</dt>
                                <dd class="stk-num">{{ \App\Support\CurrencySettings::format($storehouse->account->balance) }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>نوع الحساب</dt>
                                <dd>مدين (أصول)</dd>
                            </div>
                        </dl>
                    @endif
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
            title: 'تأكيد التعديل',
            text: 'هل أنت متأكد من حفظ التعديلات؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احفظ',
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
