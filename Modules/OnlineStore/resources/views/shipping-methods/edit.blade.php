@extends('onlinestore::master')

@section('title', 'تعديل طريقة الشحن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('shipping_methods.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.shipping_methods.edit'))
                <button type="submit" form="shipping_method_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> تحديث
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل طريقة الشحن',
            'icon' => 'truck',
            'sub' => $shippingMethod->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'طرق الشحن' => route('shipping_methods.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="shipping_method_form" action="{{ route('shipping_methods.update', $shippingMethod) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> بيانات طريقة الشحن</h6>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="name" class="stk-req">اسم طريقة الشحن</label>
                            <input type="text" name="name" id="name" class="form-control"
                                value="{{ old('name', $shippingMethod->name) }}" required>
                            @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="code">الكود</label>
                            <input type="text" name="code" id="code" class="form-control"
                                value="{{ old('code', $shippingMethod->code) }}" dir="ltr">
                            @error('code')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        <div class="form-group col-md-4">
                            <label for="shipping_type" class="stk-req">نوع الشحن</label>
                            <select name="type" id="shipping_type" class="form-control" required>
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}"
                                        @selected(old('type', $shippingMethod->type->value) == $type->value)>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="cost" class="stk-req">تكلفة الشحن</label>
                            <input type="number" name="cost" id="cost" class="form-control"
                                value="{{ old('cost', $shippingMethod->cost) }}" step="0.01" min="0" required>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="min_order_amount">الحد الأدنى للطلب</label>
                            <input type="number" name="min_order_amount" id="min_order_amount" class="form-control"
                                value="{{ old('min_order_amount', $shippingMethod->min_order_amount) }}" step="0.01" min="0">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="free_shipping_threshold">حد الشحن المجاني</label>
                            <input type="number" name="free_shipping_threshold" id="free_shipping_threshold" class="form-control"
                                value="{{ old('free_shipping_threshold', $shippingMethod->free_shipping_threshold) }}" step="0.01" min="0">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="sort_order">الترتيب</label>
                            <input type="number" name="sort_order" id="sort_order" class="form-control"
                                value="{{ old('sort_order', $shippingMethod->sort_order) }}" min="0">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="estimated_days_min">الوقت الأدنى (أيام)</label>
                            <input type="number" name="estimated_days_min" id="estimated_days_min" class="form-control"
                                value="{{ old('estimated_days_min', $shippingMethod->estimated_days_min) }}" min="0">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="estimated_days_max">الوقت الأقصى (أيام)</label>
                            <input type="number" name="estimated_days_max" id="estimated_days_max" class="form-control"
                                value="{{ old('estimated_days_max', $shippingMethod->estimated_days_max) }}" min="0">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="logo">
                                الشعار
                                @if ($shippingMethod->logo)
                                    <span class="badge badge-light-success">موجود</span>
                                @endif
                            </label>
                            <input type="file" name="logo" id="logo" class="form-control" accept="image/*">
                        </div>

                        <div class="form-group col-md-3">
                            <label for="is_active">حالة التفعيل</label>
                            <div class="switch-form-group mt-1">
                                <div class="switch-row">
                                    <span class="switch-label">مفعل</span>
                                    <label class="ios-switch">
                                        <input type="checkbox" id="is_active" name="is_active" value="1"
                                            {{ old('is_active', $shippingMethod->is_active) ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="supports_express">يدعم الشحن السريع</label>
                            <div class="switch-form-group mt-1">
                                <div class="switch-row">
                                    <span class="switch-label">يدعم الشحن السريع</span>
                                    <label class="ios-switch">
                                        <input type="checkbox" id="supports_express" name="supports_express" value="1"
                                            {{ old('supports_express', $shippingMethod->supports_express ?? true) ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted">حدد ما إذا كانت طريقة الشحن هذه تدعم خيار الشحن السريع</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-12">
                            <label for="description">الوصف</label>
                            <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $shippingMethod->description) }}</textarea>
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
    $('#shipping_type').change(function() {
        var type = $(this).val();
        if (type === 'free') {
            $('#cost').val(0).prop('readonly', true);
        } else {
            $('#cost').prop('readonly', false);
        }
    }).trigger('change');
});
</script>
@endsection
