{{-- إعدادات الفلاتر والعرض - Filters & Display Settings --}}
@php
    $filterMaxCategories = $settings->where('key', 'filter_max_categories')->first();
    $filterMaxBrands = $settings->where('key', 'filter_max_brands')->first();
    $maxFeaturedProducts = $settings->where('key', 'max_featured_products')->first();
    $maxNewArrivals = $settings->where('key', 'max_new_arrivals')->first();
    $maxTopRatedProducts = $settings->where('key', 'max_top_rated_products')->first();
    $maxBestSellingProducts = $settings->where('key', 'max_best_selling_products')->first();
    $maxRecentlyViewed = $settings->where('key', 'max_recently_viewed')->first();
    $priceRanges = $settings->where('key', 'price_ranges')->first();
@endphp

<div class="row">
    {{-- إعدادات حدود العرض --}}
    <div class="col-12">
        <h5 class="mb-3 text-primary">
            <i class="feather icon-sliders"></i> حدود العرض
        </h5>
    </div>

    {{-- الحد الأقصى للأصناف --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="filter_max_categories">
                <i class="feather icon-folder text-info"></i> الحد الأقصى للأصناف
            </label>
            <input type="number" 
                   name="filter_max_categories" 
                   id="filter_max_categories"
                   class="form-control"
                   value="{{ $filterMaxCategories->value ?? 10 }}"
                   min="1" max="50">
            <small class="text-muted">عدد الأصناف المعروضة في الصفحة الرئيسية والفلاتر</small>
        </div>
    </div>

    {{-- الحد الأقصى للبراندات --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="filter_max_brands">
                <i class="feather icon-tag text-warning"></i> الحد الأقصى للبراندات
            </label>
            <input type="number" 
                   name="filter_max_brands" 
                   id="filter_max_brands"
                   class="form-control"
                   value="{{ $filterMaxBrands->value ?? 10 }}"
                   min="1" max="50">
            <small class="text-muted">عدد البراندات المعروضة في الفلاتر</small>
        </div>
    </div>

    {{-- الحد الأقصى للمنتجات المميزة --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="max_featured_products">
                <i class="feather icon-star text-warning"></i> المنتجات المميزة
            </label>
            <input type="number" 
                   name="max_featured_products" 
                   id="max_featured_products"
                   class="form-control"
                   value="{{ $maxFeaturedProducts->value ?? 8 }}"
                   min="1" max="24">
            <small class="text-muted">عدد المنتجات المميزة في الصفحة الرئيسية</small>
        </div>
    </div>

    {{-- الحد الأقصى للمنتجات الجديدة --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="max_new_arrivals">
                <i class="feather icon-clock text-success"></i> المنتجات الجديدة
            </label>
            <input type="number" 
                   name="max_new_arrivals" 
                   id="max_new_arrivals"
                   class="form-control"
                   value="{{ $maxNewArrivals->value ?? 8 }}"
                   min="1" max="24">
            <small class="text-muted">عدد أحدث المنتجات المعروضة</small>
        </div>
    </div>

    {{-- الحد الأقصى للمنتجات الأعلى تقييماً --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="max_top_rated_products">
                <i class="feather icon-award text-danger"></i> الأعلى تقييماً
            </label>
            <input type="number" 
                   name="max_top_rated_products" 
                   id="max_top_rated_products"
                   class="form-control"
                   value="{{ $maxTopRatedProducts->value ?? 5 }}"
                   min="1" max="20">
            <small class="text-muted">عدد المنتجات الأعلى تقييماً</small>
        </div>
    </div>

    {{-- الحد الأقصى للمنتجات الأكثر مبيعاً --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="max_best_selling_products">
                <i class="feather icon-trending-up text-primary"></i> الأكثر مبيعاً
            </label>
            <input type="number" 
                   name="max_best_selling_products" 
                   id="max_best_selling_products"
                   class="form-control"
                   value="{{ $maxBestSellingProducts->value ?? 8 }}"
                   min="1" max="24">
            <small class="text-muted">عدد المنتجات الأكثر مبيعاً</small>
        </div>
    </div>

    {{-- الحد الأقصى للمنتجات المشاهدة مؤخراً --}}
    <div class="col-md-6">
        <div class="form-group">
            <label for="max_recently_viewed">
                <i class="feather icon-eye text-info"></i> المشاهدة مؤخراً
            </label>
            <input type="number" 
                   name="max_recently_viewed" 
                   id="max_recently_viewed"
                   class="form-control"
                   value="{{ $maxRecentlyViewed->value ?? 10 }}"
                   min="1" max="30">
            <small class="text-muted">عدد المنتجات المشاهدة مؤخراً للعميل</small>
        </div>
    </div>

    <div class="col-12"><hr></div>

    {{-- نطاقات الأسعار --}}
    <div class="col-12">
        <h5 class="mb-3 text-primary">
            <i class="feather icon-dollar-sign"></i> نطاقات الأسعار للفلترة
        </h5>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="price_ranges">
                <i class="feather icon-sliders text-info"></i> نطاقات الأسعار (JSON)
            </label>
            <textarea name="price_ranges" 
                      id="price_ranges"
                      class="form-control"
                      rows="6"
                      dir="ltr"
                      style="font-family: monospace;">{{ $priceRanges->value ?? '[{"key":"all","min":null,"max":null},{"key":"range_1","min":0,"max":100},{"key":"range_2","min":100,"max":500},{"key":"range_3","min":500,"max":1000},{"key":"range_4","min":1000,"max":null}]' }}</textarea>
            <small class="text-muted">
                تنسيق JSON لنطاقات الأسعار. كل نطاق يحتوي على: key (المفتاح)، min (الحد الأدنى)، max (الحد الأقصى).
                <br>استخدم null للحد المفتوح.
            </small>
        </div>
    </div>

    {{-- معاينة النطاقات --}}
    <div class="col-12">
        <div class="card bg-light">
            <div class="card-body py-2">
                <h6 class="mb-2"><i class="feather icon-eye"></i> معاينة النطاقات:</h6>
                <div id="price-ranges-preview" class="d-flex flex-wrap gap-2">
                    {{-- يتم ملؤها بـ JavaScript --}}
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // معاينة نطاقات الأسعار
    function updatePriceRangesPreview() {
        try {
            var ranges = JSON.parse($('#price_ranges').val());
            var html = '';
            ranges.forEach(function(range) {
                var label = '';
                if (range.min === null && range.max === null) {
                    label = 'الكل';
                } else if (range.min === null) {
                    label = range.max + ' أو أقل';
                } else if (range.max === null) {
                    label = range.min + ' أو أكثر';
                } else {
                    label = range.min + ' - ' + range.max;
                }
                html += '<span class="badge badge-primary mr-1 mb-1">' + label + '</span>';
            });
            $('#price-ranges-preview').html(html);
        } catch (e) {
            $('#price-ranges-preview').html('<span class="text-danger">صيغة JSON غير صحيحة</span>');
        }
    }

    // تحديث عند تغيير القيمة
    $('#price_ranges').on('input', updatePriceRangesPreview);
    
    // تحديث عند التحميل
    updatePriceRangesPreview();
});
</script>
@endpush
