@php
    $metaTitle = $settings->where('key', 'meta_title')->first();
    $metaDescription = $settings->where('key', 'meta_description')->first();
    $metaKeywords = $settings->where('key', 'meta_keywords')->first();
    $googleAnalyticsId = $settings->where('key', 'google_analytics_id')->first();
    $facebookPixelId = $settings->where('key', 'facebook_pixel_id')->first();
@endphp

<h5 class="mb-3"><i class="feather icon-file-text"></i> معلومات الميتا</h5>

<div class="form-group">
    <label for="meta_title">عنوان الصفحة الرئيسية (Meta Title)</label>
    <input type="text" name="meta_title" id="meta_title" 
           class="form-control" maxlength="70"
           value="{{ $metaTitle->value ?? '' }}">
    <small class="text-muted">يُفضل أن يكون بين 50-60 حرفاً. 
        <span id="meta_title_count">{{ strlen($metaTitle->value ?? '') }}/70</span>
    </small>
</div>

<div class="form-group">
    <label for="meta_description">وصف الصفحة الرئيسية (Meta Description)</label>
    <textarea name="meta_description" id="meta_description" 
              class="form-control" rows="3" maxlength="160">{{ $metaDescription->value ?? '' }}</textarea>
    <small class="text-muted">يُفضل أن يكون بين 150-160 حرفاً. 
        <span id="meta_description_count">{{ strlen($metaDescription->value ?? '') }}/160</span>
    </small>
</div>

<div class="form-group">
    <label for="meta_keywords">الكلمات المفتاحية (Keywords)</label>
    <input type="text" name="meta_keywords" id="meta_keywords" 
           class="form-control"
           value="{{ $metaKeywords->value ?? '' }}"
           placeholder="كلمة1، كلمة2، كلمة3">
    <small class="text-muted">افصل بين الكلمات بفاصلة</small>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-bar-chart-2"></i> أدوات التتبع والتحليل</h5>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="google_analytics_id">
                <img src="https://www.google.com/favicon.ico" alt="Google" style="width: 16px; height: 16px;"> 
                Google Analytics ID
            </label>
            <input type="text" name="google_analytics_id" id="google_analytics_id" 
                   class="form-control"
                   value="{{ $googleAnalyticsId->value ?? '' }}"
                   placeholder="G-XXXXXXXXXX أو UA-XXXXXXXXX-X">
            <small class="text-muted">معرف Google Analytics لتتبع الزوار</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="facebook_pixel_id">
                <img src="https://www.facebook.com/favicon.ico" alt="Facebook" style="width: 16px; height: 16px;"> 
                Facebook Pixel ID
            </label>
            <input type="text" name="facebook_pixel_id" id="facebook_pixel_id" 
                   class="form-control"
                   value="{{ $facebookPixelId->value ?? '' }}"
                   placeholder="XXXXXXXXXXXXXXXX">
            <small class="text-muted">معرف Facebook Pixel للإعلانات</small>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-eye"></i> معاينة في نتائج البحث</h5>

<div class="card bg-light">
    <div class="card-body">
        <div class="search-preview">
            <div class="preview-title text-primary" id="preview-title">
                {{ $metaTitle->value ?? 'عنوان المتجر' }}
            </div>
            <div class="preview-url text-success" style="font-size: 12px;">
                {{ url('/') }}
            </div>
            <div class="preview-description text-muted" style="font-size: 13px;" id="preview-description">
                {{ $metaDescription->value ?? 'وصف المتجر يظهر هنا...' }}
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // عداد الأحرف
    document.getElementById('meta_title').addEventListener('input', function() {
        document.getElementById('meta_title_count').textContent = this.value.length + '/70';
        document.getElementById('preview-title').textContent = this.value || 'عنوان المتجر';
    });
    
    document.getElementById('meta_description').addEventListener('input', function() {
        document.getElementById('meta_description_count').textContent = this.value.length + '/160';
        document.getElementById('preview-description').textContent = this.value || 'وصف المتجر يظهر هنا...';
    });
});
</script>
