@extends('onlinestore::master')

@section('title', 'إضافة كوبون خصم')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_coupons.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.coupons.create'))
                <button type="submit" form="coupon_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة كوبون خصم',
            'icon' => 'percent',
            'sub' => 'كوبون خصم جديد لعملاء المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'كوبونات الخصم' => route('store_coupons.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="coupon_form" action="{{ route('store_coupons.store') }}" method="POST">
            @csrf

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات الكوبون</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="code" class="stk-req">كود الكوبون</label>
                            <div class="input-group">
                                <input type="text" name="code" id="code" class="form-control"
                                    value="{{ old('code') }}" style="text-transform: uppercase;" dir="ltr" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="generateCode">
                                        <i class="feather icon-refresh-cw"></i>
                                    </button>
                                </div>
                            </div>
                            @error('code')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم الكوبون</label>
                            <input type="text" name="name" id="name" class="form-control"
                                value="{{ old('name') }}" placeholder="مثال: خصم الصيف" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="discount_type" class="stk-req">نوع الخصم</label>
                            <select name="type" id="discount_type" class="form-control" required>
                                <option value="percentage" @selected(old('type') == 'percentage')>نسبة مئوية (%)</option>
                                <option value="fixed" @selected(old('type') == 'fixed')>مبلغ ثابت</option>
                                <option value="free_shipping" @selected(old('type') == 'free_shipping')>شحن مجاني</option>
                            </select>
                            @error('type')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4" id="discountValueGroup">
                            <label for="discount_value" class="stk-req">قيمة الخصم</label>
                            <div class="input-group">
                                <input type="number" name="value" id="discount_value" class="form-control"
                                    value="{{ old('value') }}" min="0" step="0.01">
                                <div class="input-group-append">
                                    <span class="input-group-text" id="discountSymbol">%</span>
                                </div>
                            </div>
                            @error('value')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4" id="maxDiscountGroup">
                            <label for="max_discount_amount">الحد الأقصى للخصم</label>
                            <input type="number" name="max_discount" id="max_discount_amount" class="form-control"
                                value="{{ old('max_discount') }}" min="0" step="0.01" placeholder="للنسبة المئوية فقط">
                            @error('max_discount')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="min_order_amount">الحد الأدنى للطلب</label>
                            <input type="number" name="min_order_amount" id="min_order_amount" class="form-control"
                                value="{{ old('min_order_amount') }}" min="0" step="0.01" placeholder="0 = بدون حد">
                            @error('min_order_amount')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="usage_limit">الحد الأقصى للاستخدام الكلي</label>
                            <input type="number" name="usage_limit" id="usage_limit" class="form-control"
                                value="{{ old('usage_limit') }}" min="0" placeholder="فارغ = غير محدود">
                            @error('usage_limit')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="usage_limit_per_user">الحد الأقصى لكل مستخدم</label>
                            <input type="number" name="usage_limit_per_user" id="usage_limit_per_user" class="form-control"
                                value="{{ old('usage_limit_per_user', 1) }}" min="1">
                            @error('usage_limit_per_user')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="starts_at">تاريخ البداية</label>
                            <input type="datetime-local" name="start_date" id="starts_at" class="form-control"
                                value="{{ old('start_date') }}">
                            @error('start_date')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="expires_at">تاريخ الانتهاء</label>
                            <input type="datetime-local" name="end_date" id="expires_at" class="form-control"
                                value="{{ old('end_date') }}">
                            @error('end_date')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="is_active">حالة التفعيل</label>
                            <select name="is_active" id="is_active" class="form-control">
                                <option value="1" @selected(old('is_active', 1) == 1)>نشط</option>
                                <option value="0" @selected(old('is_active') == '0')>غير نشط</option>
                            </select>
                        </div>

                        <div class="form-group col-md-9">
                            <label for="description">وصف الكوبون</label>
                            <input type="text" name="description" id="description" class="form-control"
                                value="{{ old('description') }}" placeholder="مثال: خصم 20% على جميع المنتجات">
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
    // توليد كود عشوائي
    $('#generateCode').click(function() {
        var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var code = '';
        for (var i = 0; i < 8; i++) {
            code += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        $('#code').val(code);
    });

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
