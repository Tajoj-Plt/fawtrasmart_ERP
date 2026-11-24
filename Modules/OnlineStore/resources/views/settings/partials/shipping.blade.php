@php
    $packagingFee = $settings->where('key', 'packaging_fee')->first();
    $shippingRevenuePercentage = $settings->where('key', 'shipping_revenue_percentage')->first();
@endphp

<h5 class="mb-3"><i class="feather icon-plus-circle"></i> رسوم إضافية</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="packaging_fee">رسوم التغليف ({{ \App\Support\CurrencySettings::symbol() }})</label>
            <input type="number" name="packaging_fee" id="packaging_fee" 
                   class="form-control" step="0.01" min="0"
                   value="{{ $packagingFee->value ?? '5.00' }}">
            <small class="text-muted">رسوم تغليف المنتجات (اختياري)</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="shipping_revenue_percentage">نسبة إيرادات الشحن (%)</label>
            <input type="number" name="shipping_revenue_percentage" id="shipping_revenue_percentage" 
                   class="form-control" step="0.01" min="0" max="100"
                   value="{{ $shippingRevenuePercentage->value ?? '0.00' }}">
            <small class="text-muted">عمولة الموقع من رسوم الشحن (تسجل كإيرادات)</small>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="feather icon-info"></i>
    <strong>ملاحظة:</strong> لإدارة طرق الشحن بشكل تفصيلي، يمكنك زيارة 
    <a href="{{ route('shipping_methods.index') }}">طرق الشحن</a>.
</div>
