@extends('onlinestore::master')

@section('title', 'تعديل البانر: ' . $storeBanner->title)

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_banners.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.banners.edit'))
                <button type="submit" form="bannerForm" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ التعديلات
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل البانر',
            'icon' => 'image',
            'sub' => $storeBanner->title,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'البانرات' => route('store_banners.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form action="{{ route('store_banners.update', $storeBanner) }}" method="POST" enctype="multipart/form-data" id="bannerForm">
            @csrf
            @method('PUT')

            <div class="row">
                <!-- العمود الأيمن - المعلومات الأساسية -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-info"></i> المعلومات الأساسية</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 form-group">
                                    <label class="stk-req" for="title">العنوان</label>
                                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                                        value="{{ old('title', $storeBanner->title) }}" placeholder="عنوان البانر" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 form-group">
                                    <label for="description">الوصف</label>
                                    <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror"
                                        rows="3" placeholder="وصف مختصر للبانر">{{ old('description', $storeBanner->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- صورة البانر -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-image"></i> صورة البانر</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>صورة سطح المكتب</label>

                                    @if ($storeBanner->image)
                                        <div class="current-image-container mb-2" id="currentDesktopImage">
                                            <img src="{{ asset($storeBanner->image) }}" class="img-fluid rounded" style="max-height: 150px;">
                                            <button type="button" class="remove-image" onclick="showUploadZone('desktop')">
                                                <i class="feather icon-x"></i>
                                            </button>
                                        </div>
                                    @endif

                                    <div class="image-upload-zone" id="desktopUploadZone" style="{{ $storeBanner->image ? 'display: none;' : '' }}">
                                        <i class="feather icon-upload-cloud upload-icon"></i>
                                        <p class="upload-text">اسحب الصورة هنا أو انقر للاختيار</p>
                                        <p class="upload-hint">الأبعاد الموصى بها: 1200x400 بكسل</p>
                                        <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;">
                                    </div>
                                    <div class="image-preview mt-2" id="desktopPreview" style="display: none;">
                                        <img src="" alt="معاينة">
                                        <button type="button" class="remove-image" onclick="removeImage('desktop')">
                                            <i class="feather icon-x"></i>
                                        </button>
                                    </div>
                                    @error('image')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 form-group">
                                    <label>صورة الجوال (اختياري)</label>

                                    @if ($storeBanner->mobile_image)
                                        <div class="current-image-container mb-2" id="currentMobileImage">
                                            <img src="{{ asset($storeBanner->mobile_image) }}" class="img-fluid rounded" style="max-height: 150px;">
                                            @if (hasPermission('online_store.banners.edit'))
                                                <button type="button" class="remove-image" onclick="removeMobileImage()">
                                                    <i class="feather icon-x"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="image-upload-zone" id="mobileUploadZone" style="{{ $storeBanner->mobile_image ? 'display: none;' : '' }}">
                                        <i class="feather icon-smartphone upload-icon"></i>
                                        <p class="upload-text">صورة مخصصة للجوال</p>
                                        <p class="upload-hint">الأبعاد الموصى بها: 600x400 بكسل</p>
                                        <input type="file" name="mobile_image" id="mobileImageInput" accept="image/*" style="display: none;">
                                    </div>
                                    <div class="image-preview mt-2" id="mobilePreview" style="display: none;">
                                        <img src="" alt="معاينة">
                                        <button type="button" class="remove-image" onclick="removeImage('mobile')">
                                            <i class="feather icon-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الرابط -->
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-link"></i> إعدادات الرابط</h6>
                        </div>
                        <div class="card-body">
                            <div class="link-type-options">
                                @foreach ($linkTypes as $type)
                                    <div class="link-type-option">
                                        <input type="radio" name="link_type" id="link_type_{{ $type->value }}"
                                            value="{{ $type->value }}" {{ old('link_type', $storeBanner->link_type?->value ?? 'url') == $type->value ? 'checked' : '' }}>
                                        <label for="link_type_{{ $type->value }}">
                                            @php
                                                $icons = [
                                                    'url' => 'icon-link-2',
                                                    'product' => 'icon-box',
                                                    'category' => 'icon-folder',
                                                    'page' => 'icon-file-text',
                                                    'offer' => 'icon-tag'
                                                ];
                                            @endphp
                                            <i class="feather {{ $icons[$type->value] ?? 'icon-link' }}"></i>
                                            {{ $type->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <!-- حقل link_id المخفي -->
                            <input type="hidden" name="link_id" id="linkIdInput" value="{{ old('link_id', $storeBanner->link_id) }}">

                            <!-- حقل الرابط URL -->
                            <div class="link-field" id="urlField">
                                <label class="form-label">رابط URL</label>
                                <input type="url" name="link" id="linkInput" class="form-control @error('link') is-invalid @enderror"
                                    value="{{ old('link', $storeBanner->link) }}" placeholder="https://example.com">
                                @error('link')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- حقل اختيار المنتج -->
                            <div class="link-field" id="productField" style="display: none;">
                                <label class="form-label">اختر المنتج</label>
                                <select id="productSelect" class="form-control select2 link-select" data-link-type="product">
                                    <option value="">-- اختر منتج --</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" {{ $storeBanner->link_type?->value === 'product' && $storeBanner->link_id == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- حقل اختيار التصنيف -->
                            <div class="link-field" id="categoryField" style="display: none;">
                                <label class="form-label">اختر التصنيف</label>
                                <select id="categorySelect" class="form-control select2 link-select" data-link-type="category">
                                    <option value="">-- اختر تصنيف --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ $storeBanner->link_type?->value === 'category' && $storeBanner->link_id == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- حقل اختيار البراند -->
                            <div class="link-field" id="brandField" style="display: none;">
                                <label class="form-label">اختر العلامة التجارية</label>
                                <select id="brandSelect" class="form-control select2 link-select" data-link-type="brand">
                                    <option value="">-- اختر علامة تجارية --</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ $storeBanner->link_type?->value === 'brand' && $storeBanner->link_id == $brand->id ? 'selected' : '' }}>
                                            {{ $brand->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- العمود الأيسر - الإعدادات -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-layout"></i> موقع العرض</h6>
                        </div>
                        <div class="card-body">
                            <div class="position-visual">
                                @foreach ($positions as $position)
                                    <div class="position-item">
                                        <input type="radio" name="position" id="position_{{ $position->value }}"
                                            value="{{ $position->value }}" {{ old('position', $storeBanner->position?->value) == $position->value ? 'checked' : '' }}>
                                        <label for="position_{{ $position->value }}">
                                            @php
                                                $posIcons = [
                                                    'home_slider' => 'icon-monitor',
                                                    'sidebar' => 'icon-sidebar',
                                                    'footer' => 'icon-minus-square',
                                                    'popup' => 'icon-maximize-2',
                                                    'category' => 'icon-grid'
                                                ];
                                            @endphp
                                            <i class="feather {{ $posIcons[$position->value] ?? 'icon-square' }}"></i>
                                            <span>{{ $position->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('position')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-calendar"></i> فترة العرض</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="startDate">تاريخ البداية</label>
                                <input type="text" name="start_date" id="startDate"
                                    class="form-control flatpickr @error('start_date') is-invalid @enderror"
                                    value="{{ old('start_date', $storeBanner->start_date?->format('Y-m-d H:i')) }}" placeholder="اختياري">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="endDate">تاريخ النهاية</label>
                                <input type="text" name="end_date" id="endDate"
                                    class="form-control flatpickr @error('end_date') is-invalid @enderror"
                                    value="{{ old('end_date', $storeBanner->end_date?->format('Y-m-d H:i')) }}" placeholder="اختياري">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <label class="stk-switch" for="isActive">
                                <input type="checkbox" id="isActive" name="is_active" value="1"
                                    {{ old('is_active', $storeBanner->is_active) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">نشط</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-sliders"></i> خيارات إضافية</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="sort_order">ترتيب العرض</label>
                                <input type="number" name="sort_order" id="sort_order" class="form-control @error('sort_order') is-invalid @enderror"
                                    value="{{ old('sort_order', $storeBanner->sort_order) }}" min="0">
                                <small class="stk-help">الأرقام الأقل تظهر أولاً.</small>
                                @error('sort_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
<script>
$(document).ready(function() {
    // Flatpickr للتواريخ
    $(".flatpickr").flatpickr({
        locale: "ar",
        dateFormat: "Y-m-d H:i",
        enableTime: true,
        time_24hr: true
    });

    // رفع صورة سطح المكتب
    $('#desktopUploadZone').on('click', function() {
        $('#imageInput').click();
    });

    $('#imageInput').on('change', function() {
        previewImage(this, 'desktopPreview', 'desktopUploadZone');
    });

    // رفع صورة الجوال
    $('#mobileUploadZone').on('click', function() {
        $('#mobileImageInput').click();
    });

    $('#mobileImageInput').on('change', function() {
        previewImage(this, 'mobilePreview', 'mobileUploadZone');
    });

    // Drag and Drop
    ['desktopUploadZone', 'mobileUploadZone'].forEach(function(zoneId) {
        var zone = document.getElementById(zoneId);
        if (!zone) return;

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            var inputId = zoneId === 'desktopUploadZone' ? 'imageInput' : 'mobileImageInput';
            var previewId = zoneId === 'desktopUploadZone' ? 'desktopPreview' : 'mobilePreview';
            var input = document.getElementById(inputId);
            input.files = e.dataTransfer.files;
            previewImage(input, previewId, zoneId);
        });
    });

    // تبديل حقول الرابط
    $('input[name="link_type"]').on('change', function() {
        $('.link-field').hide();
        var type = $(this).val();

        // لا نمسح القيمة إلا إذا كان التغيير يدوي
        if (this.dataset.manualChange) {
            $('#linkIdInput').val('');
        }

        if (type === 'url' || type === 'page' || type === 'offer') {
            $('#urlField').show();
        } else if (type === 'product') {
            $('#productField').show();
            // تعيين القيمة من select المنتج إذا كان محدد
            var productVal = $('#productSelect').val();
            if (productVal) $('#linkIdInput').val(productVal);
        } else if (type === 'category') {
            $('#categoryField').show();
            // تعيين القيمة من select التصنيف إذا كان محدد
            var categoryVal = $('#categorySelect').val();
            if (categoryVal) $('#linkIdInput').val(categoryVal);
        } else if (type === 'brand') {
            $('#brandField').show();
            // تعيين القيمة من select البراند إذا كان محدد
            var brandVal = $('#brandSelect').val();
            if (brandVal) $('#linkIdInput').val(brandVal);
        }
    });

    // ربط حدث التغيير اليدوي
    $('input[name="link_type"]').on('click', function() {
        this.dataset.manualChange = 'true';
    });

    // تحديث link_id عند تغيير أي select
    $('.link-select').on('change', function() {
        var selectedValue = $(this).val();
        $('#linkIdInput').val(selectedValue);
    });

    // تفعيل الحالة الأولية
    $('input[name="link_type"]:checked').trigger('change');

    // Select2
    if ($.fn.select2) {
        $('.select2').select2({ dir: 'rtl', width: '100%' });

        // ربط حدث change لـ select2
        $('.link-select').on('select2:select', function() {
            var selectedValue = $(this).val();
            $('#linkIdInput').val(selectedValue);
        });
    }
});

function previewImage(input, previewId, zoneId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#' + previewId + ' img').attr('src', e.target.result);
            $('#' + previewId).show();
            $('#' + zoneId).hide();
            // Hide current image if exists
            if (previewId === 'desktopPreview') {
                $('#currentDesktopImage').hide();
            } else {
                $('#currentMobileImage').hide();
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage(type) {
    if (type === 'desktop') {
        $('#imageInput').val('');
        $('#desktopPreview').hide();
        $('#desktopUploadZone').show();
    } else {
        $('#mobileImageInput').val('');
        $('#mobilePreview').hide();
        $('#mobileUploadZone').show();
    }
}

function showUploadZone(type) {
    if (type === 'desktop') {
        $('#currentDesktopImage').hide();
        $('#desktopUploadZone').show();
    } else {
        $('#currentMobileImage').hide();
        $('#mobileUploadZone').show();
    }
}

function removeMobileImage() {
    if (confirm('هل تريد حذف صورة الجوال؟')) {
        $.ajax({
            url: '{{ route("store_banners.remove-mobile-image", $storeBanner) }}',
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    $('#currentMobileImage').hide();
                    $('#mobileUploadZone').show();
                    toastr.success(response.message);
                }
            }
        });
    }
}
</script>
@endsection
