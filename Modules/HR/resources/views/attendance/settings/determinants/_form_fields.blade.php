{{--
    حقولُ نموذج محدِّد الحضور — مشتركةٌ بين «إضافة» و«تعديل».
    يستقبل: $det (null عند الإضافة). المعرّفات (name / enable_ip_verification /
    enable_location_verification / ip_addresses_section / radius_section /
    radius_type_section / map_section / map / latitude / longitude) يقرؤها سكربت
    الصفحتين (إظهار الأقسام وخريطة Google)، فلا تُغيَّر.
--}}
@php
    $det = $det ?? null;
    // عند الإضافة تبدأ الأقسام التابعة مخفيّة، وعند التعديل ظاهرة — ثم يضبطها
    // `toggleSections()` بحسب مربّعات التفعيل فور التحميل.
    $hiddenUntilToggled = $det ? '' : 'display: none;';
    $detOld = fn (string $field, $default = null) => old($field, $det ? $det->{$field} : $default);
@endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم يُحفظ المحدِّد — راجع الحقول المعلَّمة:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Basic Information -->
<div class="card">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="name" class="form-label stk-req">الاسم</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                       value="{{ $detOld('name') }}" required>
                @error('name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 col-xl-3 mb-2">
                <label for="status" class="form-label stk-req">الحالة</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="0" {{ $detOld('status') == 0 ? 'selected' : '' }}>نشط</option>
                    <option value="1" {{ $detOld('status') == 1 ? 'selected' : '' }}>غير نشط</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Employee Image Section -->
    <div class="col-xl-6 col-12">
        <div class="card">
            <div class="card-header"><h4 class="card-title"><i class="feather icon-camera"></i> التقاط صورة الموظف</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mt-1 mb-2">
                        <fieldset>
                            <div class="vs-checkbox-con vs-checkbox-primary">
                                <input type="checkbox" value="1" name="capture_employee_image" id="capture_employee_image"
                                       {{ $detOld('capture_employee_image') == 1 ? 'checked' : '' }}>
                                <span class="vs-checkbox">
                                    <span class="vs-checkbox--check">
                                        <i class="vs-icon feather icon-check"></i>
                                    </span>
                                </span>
                                <span class="">تفعيل التقاط صورة الموظف</span>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label for="image_investigation" class="form-label">التحقق من الصورة</label>
                        <select name="image_investigation" id="image_investigation" class="form-control">
                            <option value="1" {{ $detOld('image_investigation') == 1 ? 'selected' : '' }}>مطلوب</option>
                            <option value="2" {{ $detOld('image_investigation') == 2 ? 'selected' : '' }}>اختياري</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- IP Verification Section -->
    <div class="col-xl-6 col-12">
        <div class="card">
            <div class="card-header"><h4 class="card-title"><i class="feather icon-wifi"></i> التحقق من عنوان IP</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mt-1 mb-2">
                        <fieldset>
                            <div class="vs-checkbox-con vs-checkbox-primary">
                                <input type="checkbox" value="1" name="enable_ip_verification" id="enable_ip_verification"
                                       {{ $detOld('enable_ip_verification') == 1 ? 'checked' : '' }}>
                                <span class="vs-checkbox">
                                    <span class="vs-checkbox--check">
                                        <i class="vs-icon feather icon-check"></i>
                                    </span>
                                </span>
                                <span class="">تفعيل التحقق من عنوان IP</span>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label for="ip_investigation" class="form-label">التحقق من IP</label>
                        <select name="ip_investigation" id="ip_investigation" class="form-control">
                            <option value="1" {{ $detOld('ip_investigation') == 1 ? 'selected' : '' }}>مطلوب</option>
                            <option value="2" {{ $detOld('ip_investigation') == 2 ? 'selected' : '' }}>اختياري</option>
                        </select>
                    </div>

                    <div class="col-md-12 mt-1" id="ip_addresses_section" style="{{ $hiddenUntilToggled }}">
                        <label for="allowed_ips" class="form-label">عناوين IP المسموحة</label>
                        <textarea name="allowed_ips" id="allowed_ips" class="form-control" rows="3"
                                  placeholder="أدخل عناوين IP مفصولة بفواصل أو أسطر جديدة">{{ old('allowed_ips', $det ? (is_array($det->allowed_ips) ? implode(', ', $det->allowed_ips) : $det->allowed_ips) : null) }}</textarea>
                        <small class="stk-help">مثال: 192.168.1.1, 192.168.1.2</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Location Verification Section -->
<div class="card">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-map-pin"></i> التحقق من الموقع الجغرافي</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 col-xl-3 mt-1 mb-2">
                <fieldset>
                    <div class="vs-checkbox-con vs-checkbox-primary">
                        <input type="checkbox" value="1" name="enable_location_verification" id="enable_location_verification"
                               {{ $detOld('enable_location_verification') == 1 ? 'checked' : '' }}>
                        <span class="vs-checkbox">
                            <span class="vs-checkbox--check">
                                <i class="vs-icon feather icon-check"></i>
                            </span>
                        </span>
                        <span class="">تفعيل التحقق من الموقع</span>
                    </div>
                </fieldset>
            </div>

            <div class="col-md-6 col-xl-3 mb-2">
                <label for="location_investigation" class="form-label">التحقق من الموقع</label>
                <select name="location_investigation" id="location_investigation" class="form-control">
                    <option value="1" {{ $detOld('location_investigation') == 1 ? 'selected' : '' }}>مطلوب</option>
                    <option value="2" {{ $detOld('location_investigation') == 2 ? 'selected' : '' }}>اختياري</option>
                </select>
            </div>

            <div class="col-md-6 col-xl-3 mb-2" id="radius_section" style="{{ $hiddenUntilToggled }}">
                <label for="radius" class="form-label">نطاق الموقع</label>
                <input type="number" class="form-control" id="radius" name="radius"
                       min="1" step="0.01" value="{{ $detOld('radius', 100) }}">
            </div>

            <div class="col-md-6 col-xl-3 mb-2" id="radius_type_section" style="{{ $hiddenUntilToggled }}">
                <label for="radius_type" class="form-label">وحدة القياس</label>
                <select name="radius_type" id="radius_type" class="form-control">
                    <option value="1" {{ $detOld('radius_type') == 1 ? 'selected' : '' }}>متر</option>
                    <option value="2" {{ $detOld('radius_type') == 2 ? 'selected' : '' }}>كيلومتر</option>
                </select>
            </div>

            <div class="col-md-12 mt-1" id="map_section" style="{{ $hiddenUntilToggled }}">
                <label class="form-label">تحديد الموقع على الخريطة</label>
                <div id="map" style="width: 100%; height: 400px; border-radius: 8px;"></div>
                <input type="hidden" name="latitude" id="latitude" value="{{ $detOld('latitude') }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ $detOld('longitude') }}">
                <small class="stk-help">اضغط على الخريطة لتحديد الموقع أو اسحب المؤشر</small>
            </div>
        </div>
    </div>
</div>
