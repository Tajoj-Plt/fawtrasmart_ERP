@php
    $minOrderAmount = $settings->where('key', 'min_order_amount')->first();
    $maxOrderAmount = $settings->where('key', 'max_order_amount')->first();
    $taxRate = $settings->where('key', 'tax_rate')->first();
    $enableGuestCheckout = $settings->where('key', 'enable_guest_checkout')->first();
    $requirePhoneVerification = $settings->where('key', 'require_phone_verification')->first();
    $requireEmailVerification = $settings->where('key', 'require_email_verification')->first();
    $cartSessionLifetime = $settings->where('key', 'cart_session_lifetime')->first();
    $defaultStorehouse = $settings->where('key', 'default_storehouse_id')->first();
    $allowNegativeStock = $settings->where('key', 'allow_negative_stock')->first();
    $defaultTreasury = $settings->where('key', 'default_treasury_id')->first();
    
    // إعدادات طرق الدفع
    $defaultPaymentMethod = $settings->where('key', 'default_payment_method_id')->first();
    $enabledPaymentMethods = $settings->where('key', 'enabled_payment_methods')->first();
    $enabledPaymentMethodsArray = [];
    if ($enabledPaymentMethods && $enabledPaymentMethods->value) {
        $decoded = json_decode($enabledPaymentMethods->value, true);
        $enabledPaymentMethodsArray = is_array($decoded) ? $decoded : [];
    }
    
    // جلب طرق الدفع المتاحة
    $paymentMethods = \App\Models\PaymentMethod::where('is_active', true)->orderBy('sort_order')->get();
    
    // جلب مخازن المتجر الإلكتروني فقط — لا مخازن الفروع الأخرى ولا عهدة الموظفين
    $storehouses = \App\Models\StoreHouse::onlineStore()->where('status', 'active')->get();

    // جلب خزائن وحسابات المتجر الإلكتروني فقط — لا خزائن الموظفين الشخصية
    $treasuries = \App\Models\Account::whereIn('parent_id', [
        \App\Constants\SystemAccountIds::CASH_TREASURIES_PARENT,
        \App\Constants\SystemAccountIds::BANK_ACCOUNTS_PARENT
    ])->onlineStore()->where('is_active', 1)->get();
@endphp

<h5 class="mb-3"><i class="feather icon-dollar-sign"></i> حدود الطلبات</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="min_order_amount">الحد الأدنى للطلب ({{ \App\Support\CurrencySettings::symbol() }})</label>
            <input type="number" name="min_order_amount" id="min_order_amount" 
                   class="form-control" step="0.01" min="0"
                   value="{{ $minOrderAmount->value ?? '50.00' }}">
            <small class="text-muted">أقل قيمة مسموح بها للطلب</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="max_order_amount">الحد الأقصى للطلب ({{ \App\Support\CurrencySettings::symbol() }})</label>
            <input type="number" name="max_order_amount" id="max_order_amount" 
                   class="form-control" step="0.01" min="0"
                   value="{{ $maxOrderAmount->value ?? '10000.00' }}">
            <small class="text-muted">أعلى قيمة مسموح بها للطلب</small>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-percent"></i> الضرائب</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="tax_rate">نسبة الضريبة (%)</label>
            <div class="input-group">
                <input type="number" name="tax_rate" id="tax_rate" 
                       class="form-control" step="0.01" min="0" max="100"
                       value="{{ $taxRate->value ?? '15.00' }}">
                <div class="input-group-append">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <small class="text-muted">ضريبة القيمة المضافة</small>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-shopping-cart"></i> إعدادات السلة</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="cart_session_lifetime">مدة بقاء السلة (بالدقائق)</label>
            <input type="number" name="cart_session_lifetime" id="cart_session_lifetime" 
                   class="form-control" min="60"
                   value="{{ $cartSessionLifetime->value ?? '1440' }}">
            <small class="text-muted">الفترة قبل انتهاء صلاحية السلة (1440 = 24 ساعة)</small>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-user"></i> إعدادات العملاء</h5>

<div class="row">
    <div class="col-md-6">
        <label class="stk-switch" for="enable_guest_checkout">
            <input type="checkbox" id="enable_guest_checkout" name="enable_guest_checkout" value="1"
                   {{ ($enableGuestCheckout && in_array($enableGuestCheckout->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">السماح للزوار بالشراء</span>
                <span class="stk-switch__hint">السماح للزوار غير المسجلين بإتمام الشراء</span>
            </span>
        </label>
    </div>
    <div class="col-md-6">
        <label class="stk-switch" for="require_phone_verification">
            <input type="checkbox" id="require_phone_verification" name="require_phone_verification" value="1"
                   {{ ($requirePhoneVerification && in_array($requirePhoneVerification->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">التحقق من رقم الهاتف</span>
                <span class="stk-switch__hint">مطالبة العميل بتأكيد رقم الهاتف</span>
            </span>
        </label>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <label class="stk-switch" for="require_email_verification">
            <input type="checkbox" id="require_email_verification" name="require_email_verification" value="1"
                   {{ ($requireEmailVerification && in_array($requireEmailVerification->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">التحقق من البريد الإلكتروني</span>
                <span class="stk-switch__hint">مطالبة العميل بتأكيد البريد الإلكتروني قبل إتمام الطلب</span>
            </span>
        </label>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-credit-card"></i> طرق الدفع</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="default_payment_method_id">طريقة الدفع الافتراضية</label>
            <select name="default_payment_method_id" id="default_payment_method_id" class="form-control select2">
                <option value="">-- اختر طريقة الدفع الافتراضية --</option>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method->id }}" {{ ($defaultPaymentMethod && $defaultPaymentMethod->value == $method->id) ? 'selected' : '' }}>
                        {{ $method->name }}
                        @if($method->is_online) (أونلاين) @endif
                    </option>
                @endforeach
            </select>
            <small class="text-muted">طريقة الدفع المحددة مسبقاً عند إتمام الطلب</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>طرق الدفع المتاحة في المتجر</label>
            <div class="payment-methods-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;">
                @forelse($paymentMethods as $method)
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" 
                               class="custom-control-input" 
                               id="payment_method_{{ $method->id }}" 
                               name="enabled_payment_methods[]" 
                               value="{{ $method->id }}"
                               {{ in_array($method->id, $enabledPaymentMethodsArray) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="payment_method_{{ $method->id }}">
                            <i class="feather icon-{{ $method->icon ?? 'credit-card' }} mr-50"></i>
                            {{ $method->name }}
                            @if($method->is_online)
                                <span class="badge badge-light-primary badge-sm">أونلاين</span>
                            @endif
                            @if($method->fee_amount > 0)
                                <span class="badge badge-light-warning badge-sm">
                                    رسوم: {{ $method->fee_type == 'percentage' ? $method->fee_amount.'%' : $method->fee_amount.' '.\App\Support\CurrencySettings::symbol() }}
                                </span>
                            @endif
                        </label>
                    </div>
                @empty
                    <div class="alert alert-warning mb-0">
                        <i class="feather icon-alert-triangle mr-50"></i>
                        لا توجد طرق دفع مُعرّفة. يرجى إضافة طرق الدفع أولاً.
                    </div>
                @endforelse
            </div>
            <small class="text-muted">اختر طرق الدفع التي ستظهر للعملاء في المتجر</small>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-box"></i> إعدادات المخزون</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="default_storehouse_id">المخزون الافتراضي للمتجر</label>
            <select name="default_storehouse_id" id="default_storehouse_id" class="form-control select2">
                <option value="">-- اختر المخزون الافتراضي --</option>
                @foreach($storehouses as $storehouse)
                    <option value="{{ $storehouse->id }}" {{ ($defaultStorehouse && $defaultStorehouse->value == $storehouse->id) ? 'selected' : '' }}>
                        {{ $storehouse->name }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">المخزون الذي سيتم خصم المنتجات منه عند إتمام الطلبات</small>
        </div>
    </div>
    <div class="col-md-6">
        <label class="stk-switch" for="allow_negative_stock">
            <input type="checkbox" id="allow_negative_stock" name="allow_negative_stock" value="1"
                   {{ ($allowNegativeStock && in_array($allowNegativeStock->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">البيع بالسالب (دون مخزون)</span>
                <span class="stk-switch__hint">السماح بالبيع حتى لو كان المخزون صفر أو سالب</span>
            </span>
        </label>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-credit-card"></i> إعدادات الخزينة</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="default_treasury_id">الخزينة / البنك الافتراضي</label>
            <select name="default_treasury_id" id="default_treasury_id" class="form-control select2">
                <option value="">-- اختر الخزينة أو البنك الافتراضي --</option>
                @if($treasuries->count() > 0)
                    <optgroup label="الخزائن والحسابات البنكية">
                        @foreach($treasuries as $treasury)
                            <option value="{{ $treasury->id }}" {{ ($defaultTreasury && $defaultTreasury->value == $treasury->id) ? 'selected' : '' }}>
                                {{ $treasury->name }} 
                                @if($treasury->parent_id == \App\Constants\SystemAccountIds::CASH_TREASURIES_PARENT)
                                    (خزينة)
                                @else
                                    (حساب بنكي)
                                @endif
                            </option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <small class="text-muted">الخزينة أو الحساب البنكي الذي سيتم إيداع المبالغ فيه عند إتمام الطلبات</small>
        </div>
    </div>
</div>
