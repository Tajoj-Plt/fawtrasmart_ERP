@php
    $enableCache = $settings->where('key', 'enable_cache')->first();
    $maintenanceMode = $settings->where('key', 'maintenance_mode')->first();
    $autoApproveReviews = $settings->where('key', 'auto_approve_reviews')->first();
@endphp

<h5 class="mb-3"><i class="feather icon-alert-triangle text-warning"></i> وضع الصيانة</h5>

<div class="card border-warning mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">وضع الصيانة</h5>
                <p class="text-muted mb-0">عند التفعيل، سيتم إيقاف المتجر للزوار مع عرض صفحة صيانة</p>
            </div>
            <label class="stk-switch" for="maintenance_mode">
                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1"
                       {{ ($maintenanceMode && in_array($maintenanceMode->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            </label>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-zap"></i> الأداء</h5>

<label class="stk-switch" for="enable_cache">
    <input type="checkbox" id="enable_cache" name="enable_cache" value="1"
           {{ ($enableCache && in_array($enableCache->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
    <span class="stk-switch__label">
        <span class="stk-switch__title">تفعيل التخزين المؤقت</span>
        <span class="stk-switch__hint">تسريع تحميل الصفحات عبر التخزين المؤقت</span>
    </span>
</label>

@if (hasPermission('online_store.settings.clear_cache'))
    <div class="card bg-light mt-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">مسح ذاكرة التخزين المؤقت</h6>
                    <small class="text-muted">مسح جميع البيانات المخزنة مؤقتاً</small>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" id="clearCacheBtn">
                    <i class="feather icon-trash-2"></i> مسح الكاش
                </button>
            </div>
        </div>
    </div>
@endif

<hr>
<h5 class="mb-3"><i class="feather icon-star"></i> إدارة التقييمات</h5>

<label class="stk-switch" for="auto_approve_reviews">
    <input type="checkbox" id="auto_approve_reviews" name="auto_approve_reviews" value="1"
           {{ ($autoApproveReviews && in_array($autoApproveReviews->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
    <span class="stk-switch__label">
        <span class="stk-switch__title">الموافقة التلقائية على التقييمات</span>
        <span class="stk-switch__hint">نشر التقييمات تلقائياً بدون مراجعة (غير مستحسن)</span>
    </span>
</label>

<hr>
<h5 class="mb-3"><i class="feather icon-database"></i> النسخ الاحتياطي</h5>

<div class="row">
    @if (hasPermission('online_store.settings.import_export'))
        <div class="col-md-6">
            <div class="card border">
                <div class="card-body">
                    <h6><i class="feather icon-download"></i> تصدير الإعدادات</h6>
                    <p class="text-muted">تحميل نسخة من جميع إعدادات المتجر</p>
                    <a href="{{ route('store_settings.export') }}" class="btn btn-outline-primary btn-sm">
                        <i class="feather icon-download"></i> تصدير JSON
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border">
                <div class="card-body">
                    <h6><i class="feather icon-upload"></i> استيراد الإعدادات</h6>
                    <p class="text-muted">استعادة الإعدادات من ملف JSON</p>
                    <button type="button" class="btn btn-outline-success btn-sm" data-os-modal="#importModal">
                        <i class="feather icon-upload"></i> استيراد
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
document.getElementById('clearCacheBtn')?.addEventListener('click', function() {
    if (confirm('هل أنت متأكد من مسح ذاكرة التخزين المؤقت؟')) {
        fetch('{{ route("store_settings.clear-cache") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message || 'تم مسح الكاش بنجاح');
            } else {
                toastr.error(data.message || 'حدث خطأ');
            }
        })
        .catch(error => {
            toastr.error('حدث خطأ في الاتصال');
        });
    }
});
</script>
