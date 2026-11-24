@php
    $productsPerPage = $settings->where('key', 'products_per_page')->first();
    $enableWishlist = $settings->where('key', 'enable_wishlist')->first();
    $enableReviews = $settings->where('key', 'enable_reviews')->first();
    $enableQuestions = $settings->where('key', 'enable_questions')->first();
@endphp

<h5 class="mb-3"><i class="feather icon-grid"></i> عرض المنتجات</h5>

<div class="form-group">
    <label for="products_per_page">عدد المنتجات في الصفحة</label>
    <select name="products_per_page" id="products_per_page" class="form-control">
        <option value="8" {{ ($productsPerPage->value ?? '') == '8' ? 'selected' : '' }}>8 منتجات</option>
        <option value="12" {{ ($productsPerPage->value ?? '12') == '12' ? 'selected' : '' }}>12 منتج</option>
        <option value="16" {{ ($productsPerPage->value ?? '') == '16' ? 'selected' : '' }}>16 منتج</option>
        <option value="20" {{ ($productsPerPage->value ?? '') == '20' ? 'selected' : '' }}>20 منتج</option>
        <option value="24" {{ ($productsPerPage->value ?? '') == '24' ? 'selected' : '' }}>24 منتج</option>
    </select>
</div>

<hr>
<h5 class="mb-3"><i class="feather icon-toggle-right"></i> ميزات المتجر</h5>

<div class="row">
    <div class="col-md-6">
        <label class="stk-switch" for="enable_wishlist">
            <input type="checkbox" id="enable_wishlist" name="enable_wishlist" value="1"
                   {{ ($enableWishlist && in_array($enableWishlist->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">قائمة المفضلة</span>
                <span class="stk-switch__hint">السماح للعملاء بإضافة منتجات للمفضلة</span>
            </span>
        </label>
    </div>
    <div class="col-md-6">
        <label class="stk-switch" for="enable_reviews">
            <input type="checkbox" id="enable_reviews" name="enable_reviews" value="1"
                   {{ ($enableReviews && in_array($enableReviews->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">تقييمات المنتجات</span>
                <span class="stk-switch__hint">السماح للعملاء بتقييم المنتجات</span>
            </span>
        </label>
    </div>
</div>

<div class="row mt-2">
    <div class="col-md-6">
        <label class="stk-switch" for="enable_questions">
            <input type="checkbox" id="enable_questions" name="enable_questions" value="1"
                   {{ ($enableQuestions && in_array($enableQuestions->value, ['true', '1', 1], true)) ? 'checked' : '' }}>
            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
            <span class="stk-switch__label">
                <span class="stk-switch__title">أسئلة المنتجات</span>
                <span class="stk-switch__hint">السماح للعملاء بطرح أسئلة على المنتجات والحصول على إجابات</span>
            </span>
        </label>
    </div>
</div>
