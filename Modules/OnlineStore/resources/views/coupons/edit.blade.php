@extends('onlinestore::master')

@section('title', 'تعديل كوبون الخصم')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_coupons.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.coupons.edit'))
                <button type="submit" form="coupon_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التغييرات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل كوبون الخصم',
            'icon' => 'percent',
            'sub' => $storeCoupon->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'كوبونات الخصم' => route('store_coupons.index'),
                $storeCoupon->code => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="coupon_form" action="{{ route('store_coupons.update', $storeCoupon) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات الكوبون</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="code" class="stk-req">كود الكوبون</label>
                            <input type="text" name="code" id="code" class="form-control"
                                value="{{ old('code', $storeCoupon->code) }}" style="text-transform: uppercase;" dir="ltr" required>
                            @error('code')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم الكوبون</label>
                            <input type="text" name="name" id="name" class="form-control"
                                value="{{ old('name', $storeCoupon->name) }}" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="discount_type" class="stk-req">نوع الخصم</label>
                            <select name="type" id="discount_type" class="form-control" required>
                                @foreach ($couponTypes as $type)
                                    <option value="{{ $type->value }}" @selected(old('type', $storeCoupon->type?->value) == $type->value)>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4" id="discountValueGroup">
                            <label for="discount_value" class="stk-req">قيمة الخصم</label>
                            <div class="input-group">
                                <input type="number" name="value" id="discount_value" class="form-control"
                                    value="{{ old('value', $storeCoupon->value) }}" min="0" step="0.01" required>
                                <div class="input-group-append">
                                    <span class="input-group-text" id="discountSymbol">%</span>
                                </div>
                            </div>
                            @error('value')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4" id="maxDiscountGroup">
                            <label for="max_discount">الحد الأقصى للخصم</label>
                            <input type="number" name="max_discount" id="max_discount" class="form-control"
                                value="{{ old('max_discount', $storeCoupon->max_discount) }}" min="0" step="0.01"
                                placeholder="للنسبة المئوية فقط">
                            @error('max_discount')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="min_order_amount">الحد الأدنى للطلب</label>
                            <input type="number" name="min_order_amount" id="min_order_amount" class="form-control"
                                value="{{ old('min_order_amount', $storeCoupon->min_order_amount) }}" min="0" step="0.01"
                                placeholder="0 = بدون حد">
                            @error('min_order_amount')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="usage_limit">الحد الأقصى للاستخدام الكلي</label>
                            <input type="number" name="usage_limit" id="usage_limit" class="form-control"
                                value="{{ old('usage_limit', $storeCoupon->usage_limit) }}" min="0"
                                placeholder="فارغ = غير محدود">
                            @error('usage_limit')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="usage_limit_per_user">الحد الأقصى لكل مستخدم</label>
                            <input type="number" name="usage_limit_per_user" id="usage_limit_per_user" class="form-control"
                                value="{{ old('usage_limit_per_user', $storeCoupon->usage_limit_per_user ?? 1) }}" min="1">
                            @error('usage_limit_per_user')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="is_active">حالة التفعيل</label>
                            <select name="is_active" id="is_active" class="form-control">
                                <option value="1" @selected(old('is_active', $storeCoupon->is_active) == 1)>نشط</option>
                                <option value="0" @selected(old('is_active', $storeCoupon->is_active) == 0)>غير نشط</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="start_date">تاريخ البداية</label>
                            <input type="datetime-local" name="start_date" id="start_date" class="form-control"
                                value="{{ old('start_date', $storeCoupon->start_date ? $storeCoupon->start_date->format('Y-m-d\TH:i') : '') }}">
                            @error('start_date')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="end_date">تاريخ الانتهاء</label>
                            <input type="datetime-local" name="end_date" id="end_date" class="form-control"
                                value="{{ old('end_date', $storeCoupon->end_date ? $storeCoupon->end_date->format('Y-m-d\TH:i') : '') }}">
                            @error('end_date')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="is_first_order_only">للطلب الأول فقط</label>
                            <select name="is_first_order_only" id="is_first_order_only" class="form-control">
                                <option value="0" @selected(old('is_first_order_only', $storeCoupon->is_first_order_only) == 0)>لا</option>
                                <option value="1" @selected(old('is_first_order_only', $storeCoupon->is_first_order_only) == 1)>نعم</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-12">
                            <label for="description">وصف الكوبون</label>
                            <textarea name="description" id="description" class="form-control" rows="2"
                                placeholder="مثال: خصم 20% على جميع المنتجات">{{ old('description', $storeCoupon->description) }}</textarea>
                            @error('description')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تغيير نوع الخصم
    $('#discount_type').change(function() {
        var type = $(this).val();
        if (type === 'free_shipping') {
            $('#discountValueGroup').hide();
            $('#maxDiscountGroup').hide();
            $('#discount_value').removeAttr('required');
        } else if (type === 'percentage') {
            $('#discountValueGroup').show();
            $('#maxDiscountGroup').show();
            $('#discountSymbol').text('%');
            $('#discount_value').attr('max', '100').attr('required', 'required');
        } else {
            $('#discountValueGroup').show();
            $('#maxDiscountGroup').hide();
            $('#discountSymbol').text('{{ \App\Support\CurrencySettings::symbol() }}');
            $('#discount_value').removeAttr('max').attr('required', 'required');
        }
    }).trigger('change');

    // تحويل الكود لأحرف كبيرة
    $('#code').on('input', function() {
        $(this).val($(this).val().toUpperCase().replace(/[^A-Z0-9]/g, ''));
    });
});
</script>
@endsection
