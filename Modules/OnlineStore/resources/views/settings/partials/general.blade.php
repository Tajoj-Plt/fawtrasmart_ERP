@php
    $storeName = $settings->where('key', 'store_name')->first();
    $storeNameEn = $settings->where('key', 'store_name_en')->first();
    $storeLogo = $settings->where('key', 'store_logo')->first();
    $storeFavicon = $settings->where('key', 'store_favicon')->first();
    $storeDescription = $settings->where('key', 'store_description')->first();
    $storeEmail = $settings->where('key', 'store_email')->first();
    $storePhone = $settings->where('key', 'store_phone')->first();
    $storeAddress = $settings->where('key', 'store_address')->first();
    $storeTimezone = $settings->where('key', 'store_timezone')->first();
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="store_name" class="stk-req">اسم المتجر (عربي)</label>
            <input type="text" name="store_name" id="store_name"
                   class="form-control"
                   value="{{ $storeName->value ?? '' }}"
                   required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="store_name_en">اسم المتجر (إنجليزي)</label>
            <input type="text" name="store_name_en" id="store_name_en" 
                   class="form-control" 
                   value="{{ $storeNameEn->value ?? '' }}">
        </div>
    </div>
</div>

<div class="form-group">
    <label for="store_description">وصف المتجر</label>
    <textarea name="store_description" id="store_description" 
              class="form-control" rows="3">{{ $storeDescription->value ?? '' }}</textarea>
</div>

<!-- كارد عرض الصور المحفوظة حالياً -->
<div class="card mb-3">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-image"></i> الصور المحفوظة</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <label class="d-block mb-2 small text-muted">لوجو المتجر</label>
                @if($storeLogo && !empty($storeLogo->value))
                    @php
                        $logoPath = trim($storeLogo->value);
                        $logoUrl = asset(ltrim($logoPath, '/'));
                    @endphp
                    <div class="d-flex align-items-center">
                        <img src="{{ $logoUrl }}" alt="Logo"
                             style="height: 40px; width: auto; object-fit: contain; margin-left: 10px;">
                        @if (hasPermission('online_store.settings.manage'))
                            <button type="button" class="btn btn-sm btn-danger delete-image" data-key="store_logo">
                                <i class="feather icon-trash-2"></i>
                            </button>
                        @endif
                    </div>
                @else
                    <p class="text-muted mb-0 small">لا يوجد</p>
                @endif
            </div>
            <div class="col-md-6">
                <label class="d-block mb-2 small text-muted">أيقونة المتجر</label>
                @if($storeFavicon && !empty($storeFavicon->value))
                    @php
                        $faviconPath = trim($storeFavicon->value);
                        $faviconUrl = asset(ltrim($faviconPath, '/'));
                    @endphp
                    <div class="d-flex align-items-center">
                        <img src="{{ $faviconUrl }}" alt="Favicon"
                             style="height: 32px; width: auto; object-fit: contain; margin-left: 10px;">
                        @if (hasPermission('online_store.settings.manage'))
                            <button type="button" class="btn btn-sm btn-danger delete-image" data-key="store_favicon">
                                <i class="feather icon-trash-2"></i>
                            </button>
                        @endif
                    </div>
                @else
                    <p class="text-muted mb-0 small">لا يوجد</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="store_logo">رفع لوجو جديد</label>
            <div class="custom-file">
                <input type="file" name="store_logo" id="store_logo" 
                       class="custom-file-input" accept="image/*">
                <label class="custom-file-label" for="store_logo">اختر صورة</label>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="store_favicon">رفع أيقونة جديدة</label>
            <div class="custom-file">
                <input type="file" name="store_favicon" id="store_favicon" 
                       class="custom-file-input" accept="image/*">
                <label class="custom-file-label" for="store_favicon">اختر صورة</label>
            </div>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-phone"></i> معلومات الاتصال</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="store_email">البريد الإلكتروني</label>
            <input type="email" name="store_email" id="store_email" 
                   class="form-control" 
                   value="{{ $storeEmail->value ?? '' }}">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="store_phone">رقم الهاتف</label>
            <input type="tel" name="store_phone" id="store_phone" 
                   class="form-control" 
                   value="{{ $storePhone->value ?? '' }}">
        </div>
    </div>
</div>

<div class="form-group">
    <label for="store_address">العنوان</label>
    <textarea name="store_address" id="store_address" 
              class="form-control" rows="2">{{ $storeAddress->value ?? '' }}</textarea>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-globe"></i> إعدادات إقليمية</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label>العملة</label>
            <input type="text" class="form-control" value="{{ \App\Support\CurrencySettings::name() }} ({{ \App\Support\CurrencySettings::code() }})" disabled>
            <small class="text-muted">
                عملة المتجر تتبع عملة النظام الافتراضية تلقائياً — لا تُخصَّص هنا. لتغييرها اذهب إلى
                <a href="{{ route('SittingAccount.currency') }}">إعدادات العملات</a>.
            </small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="store_timezone">المنطقة الزمنية</label>
            <select name="store_timezone" id="store_timezone" class="form-control select2">
                <option value="Asia/Riyadh" {{ ($storeTimezone->value ?? '') == 'Asia/Riyadh' ? 'selected' : '' }}>الرياض (UTC+3)</option>
                <option value="Asia/Dubai" {{ ($storeTimezone->value ?? '') == 'Asia/Dubai' ? 'selected' : '' }}>دبي (UTC+4)</option>
                <option value="Asia/Kuwait" {{ ($storeTimezone->value ?? '') == 'Asia/Kuwait' ? 'selected' : '' }}>الكويت (UTC+3)</option>
                <option value="Africa/Cairo" {{ ($storeTimezone->value ?? '') == 'Africa/Cairo' ? 'selected' : '' }}>القاهرة (UTC+2)</option>
                <option value="UTC" {{ ($storeTimezone->value ?? '') == 'UTC' ? 'selected' : '' }}>UTC</option>
            </select>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-map-pin"></i> الخرائط</h5>

@php
    $googleMapsKey = $settings->where('key', 'google_maps_api_key')->first();
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="google_maps_api_key">مفتاح خرائط Google</label>
            <input type="text" name="google_maps_api_key" id="google_maps_api_key"
                   class="form-control" dir="ltr" autocomplete="off" maxlength="255"
                   value="{{ $googleMapsKey->value ?? '' }}"
                   placeholder="{{ config('services.google_maps.key') ? 'فارغ = مفتاح النظام الافتراضي' : 'AIza...' }}">
            <small class="text-muted">يُستعمل في واجهة المتجر لتعبئة العناوين من الخريطة. اتركه فارغاً لاستعمال مفتاح النظام.</small>
        </div>
    </div>
</div>
