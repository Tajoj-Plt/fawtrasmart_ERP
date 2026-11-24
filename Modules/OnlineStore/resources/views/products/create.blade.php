@extends('onlinestore::master')

@section('title', 'إضافة منتج جديد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_products.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            @if (hasPermission('online_store.products.create'))
                <button type="submit" form="product_form" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ
                </button>
            @endif
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة منتج جديد',
            'icon' => 'package',
            'sub' => 'منتج جديد يُعرض في كتالوج المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('store_products.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="product_form" action="{{ route('store_products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <!-- معلومات المنتج الأساسية -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-info"></i> المعلومات الأساسية</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="stk-req">اسم المنتج</label>
                                <input type="text" name="name" class="form-control"
                                       value="{{ old('name') }}" required>
                                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="stk-req">التصنيف</label>
                                <select name="category_id" class="form-control select2" required>
                                    <option value="">اختر التصنيف</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')<small class="text-danger">{{ $message }}</small>@enderror
                                <x-create-first
                                    :count="count($categories)"
                                    route="store_categories.create"
                                    permission="online_store.categories.create"
                                    :plan="\App\Constants\planFeatures\StockPlanFeatures::LIMIT_CATEGORIES"
                                    label="تصنيف"
                                    hint="المنتج يتبع تصنيفاً، ولا تصنيفات مسجَّلة بعد." />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>العلامة التجارية</label>
                                <select name="brand_id" class="form-control select2">
                                    <option value="">بدون علامة</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                                            {{ $brand->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('brand_id')<small class="text-danger">{{ $message }}</small>@enderror
                                <x-create-first
                                    :count="count($brands)"
                                    route="brands.create"
                                    permission="online_store.brands.create"
                                    :plan="\App\Constants\planFeatures\StockPlanFeatures::LIMIT_BRANDS"
                                    label="علامة تجارية"
                                    hint="بإمكانك ربط المنتج بعلامة تجارية، ولا علامات مسجَّلة بعد." />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>الباركود</label>
                                <input type="text" name="barcode" class="form-control"
                                       value="{{ old('barcode') }}" dir="ltr">
                                @error('barcode')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>وصف المنتج</label>
                                <textarea name="description" class="form-control" rows="4"
                                          placeholder="أدخل وصف تفصيلي للمنتج...">{{ old('description') }}</textarea>
                                @error('description')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الأسعار -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-dollar-sign"></i> الأسعار والخصومات</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="stk-req">سعر الشراء</label>
                                <div class="input-group">
                                    <input type="number" name="purchase_price" class="form-control"
                                           value="{{ old('purchase_price', 0) }}" min="0" step="0.01" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">{{ \App\Support\CurrencySettings::symbol() }}</span>
                                    </div>
                                </div>
                                @error('purchase_price')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="stk-req">سعر البيع</label>
                                <div class="input-group">
                                    <input type="number" name="sale_price" class="form-control"
                                           value="{{ old('sale_price', 0) }}" min="0" step="0.01" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">{{ \App\Support\CurrencySettings::symbol() }}</span>
                                    </div>
                                </div>
                                @error('sale_price')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>أقل سعر بيع</label>
                                <div class="input-group">
                                    <input type="number" name="min_sale_price" class="form-control"
                                           value="{{ old('min_sale_price') }}" min="0" step="0.01">
                                    <div class="input-group-append">
                                        <span class="input-group-text">{{ \App\Support\CurrencySettings::symbol() }}</span>
                                    </div>
                                </div>
                                @error('min_sale_price')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="stk-req">الحالة</label>
                                <select name="status" class="form-control" required>
                                    @foreach($productStatuses as $status)
                                        <option value="{{ $status->value }}" {{ old('status', 'active') == $status->value ? 'selected' : '' }}>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>نوع الخصم</label>
                                <select name="discount_type" class="form-control" id="discount_type">
                                    <option value="">بدون خصم</option>
                                    <option value="percentage" {{ old('discount_type') == 'percentage' ? 'selected' : '' }}>نسبة مئوية</option>
                                    <option value="fixed" {{ old('discount_type') == 'fixed' ? 'selected' : '' }}>مبلغ ثابت</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3" id="discountGroup" style="{{ old('discount_type') ? '' : 'display:none;' }}">
                            <div class="form-group">
                                <label>قيمة الخصم</label>
                                <div class="input-group">
                                    <input type="number" name="discount" id="discount_value" class="form-control"
                                           value="{{ old('discount') }}" min="0" step="0.01">
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="discountSymbol">%</span>
                                    </div>
                                </div>
                                @error('discount')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tax1">الضريبة الأولى</label>
                                <select name="tax1" id="tax1" class="form-control">
                                    <option value="">بلا ضريبة</option>
                                    @foreach ($taxOptions ?? [] as $taxOption)
                                        <option value="{{ $taxOption->id }}" @selected((string) old('tax1', null) === (string) $taxOption->id)>
                                            {{ $taxOption->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format((float) $taxOption->tax, 2), '0'), '.') }}%)
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">من شاشة «الضرائب» في الإعدادات.</small>
                                @error('tax1')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="tax2">الضريبة الثانية</label>
                                <select name="tax2" id="tax2" class="form-control">
                                    <option value="">بلا ضريبة</option>
                                    @foreach ($taxOptions ?? [] as $taxOption)
                                        <option value="{{ $taxOption->id }}" @selected((string) old('tax2', null) === (string) $taxOption->id)>
                                            {{ $taxOption->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format((float) $taxOption->tax, 2), '0'), '.') }}%)
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">من شاشة «الضرائب» في الإعدادات.</small>
                                @error('tax2')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- المواصفات الفيزيائية -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-box"></i> المواصفات الفيزيائية</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>الوزن (كجم)</label>
                                <input type="number" name="weight" class="form-control"
                                       value="{{ old('weight') }}" min="0" step="0.01">
                                @error('weight')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>الأبعاد (الطول × العرض × الارتفاع)</label>
                                <input type="text" name="dimensions" class="form-control"
                                       value="{{ old('dimensions') }}" placeholder="مثال: 20×15×10 سم">
                                <small class="text-muted">الصيغة: 10x15x20 أو {"length":"10","width":"15","height":"20"}</small>
                                @error('dimensions')<small class="text-danger d-block">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>قابل للإرجاع</label>
                                <select name="is_returnable" class="form-control" id="is_returnable">
                                    <option value="1" {{ old('is_returnable', 1) == 1 ? 'selected' : '' }}>نعم</option>
                                    <option value="0" {{ old('is_returnable') == '0' ? 'selected' : '' }}>لا</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3" id="returnDaysGroup">
                            <div class="form-group">
                                <label>مدة الإرجاع (بالأيام)</label>
                                <input type="number" name="return_days" class="form-control"
                                       value="{{ old('return_days', 14) }}" min="0">
                                @error('return_days')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إعدادات المخزون -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-layers"></i> إعدادات المخزون</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="track_inventory"
                                           id="track_inventory" value="1" {{ old('track_inventory', 1) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="track_inventory">تتبع المخزون</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="low_stock_alert"
                                           id="low_stock_alert" value="1" {{ old('low_stock_alert') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="low_stock_alert">تنبيه انخفاض المخزون</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="lowStockGroup" style="{{ old('low_stock_alert') ? '' : 'display:none;' }}">
                            <div class="form-group">
                                <label>حد التنبيه</label>
                                <input type="number" name="low_stock_thershold" class="form-control"
                                       value="{{ old('low_stock_thershold', 5) }}" min="0">
                                @error('low_stock_thershold')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إعدادات المتجر الإلكتروني -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-globe"></i> إعدادات المتجر الإلكتروني</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="available_online"
                                           id="available_online" value="1" {{ old('available_online', 1) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="available_online">متاح في المتجر الإلكتروني</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="available_pos"
                                           id="available_pos" value="1" {{ old('available_pos', 1) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="available_pos">متاح في نقطة البيع</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="featured_product"
                                           id="featured_product" value="1" {{ old('featured_product') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="featured_product">منتج مميز</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الخيارات والمتغيرات -->
            @include('onlinestore::products.partials.options_variants', ['optionTemplates' => $optionTemplates])

            <!-- الصور -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-image"></i> صور المنتج</h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>اختر الصور</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="images[]" id="images"
                                   multiple accept="image/*">
                            <label class="custom-file-label" for="images">اختر الصور...</label>
                        </div>
                        <small class="text-muted">يمكنك اختيار أكثر من صورة. الحجم الأقصى: 2MB لكل صورة</small>
                        @error('images')<small class="text-danger d-block">{{ $message }}</small>@enderror
                        @error('images.*')<small class="text-danger d-block">{{ $message }}</small>@enderror
                    </div>
                    <div id="imagePreview" class="row mt-2"></div>
                </div>
            </div>

            <!-- ملاحظات داخلية -->
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> ملاحظات داخلية</h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <textarea name="Internal_notes" class="form-control" rows="3"
                                  placeholder="ملاحظات داخلية لا تظهر للعملاء...">{{ old('Internal_notes') }}</textarea>
                        @error('Internal_notes')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize select2
    $('.select2').select2();

    // نوع الخصم
    $('#discount_type').change(function() {
        var type = $(this).val();
        if (type) {
            $('#discountGroup').show();
            if (type === 'percentage') {
                $('#discountSymbol').text('%');
                $('#discount_value').attr('max', '100');
            } else {
                $('#discountSymbol').text('{{ \App\Support\CurrencySettings::symbol() }}');
                $('#discount_value').removeAttr('max');
            }
        } else {
            $('#discountGroup').hide();
            $('#discount_value').val('');
        }
    });

    // قابل للإرجاع
    $('#is_returnable').change(function() {
        if ($(this).val() == '1') {
            $('#returnDaysGroup').show();
        } else {
            $('#returnDaysGroup').hide();
        }
    });

    // تنبيه انخفاض المخزون
    $('#low_stock_alert').change(function() {
        if ($(this).is(':checked')) {
            $('#lowStockGroup').show();
        } else {
            $('#lowStockGroup').hide();
        }
    });

    // معاينة الصور
    $('#images').change(function() {
        var files = this.files;
        var preview = $('#imagePreview');
        preview.html('');

        if (files.length > 0) {
            $('.custom-file-label').text(files.length + ' صورة محددة');
        } else {
            $('.custom-file-label').text('اختر الصور...');
        }

        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var reader = new FileReader();

            reader.onload = function(e) {
                preview.append(
                    '<div class="col-md-2 col-sm-3 mb-2">' +
                        '<img src="' + e.target.result + '" class="img-thumbnail" style="height: 100px; object-fit: cover;">' +
                    '</div>'
                );
            };

            reader.readAsDataURL(file);
        }
    });

    // تنسيق حقل الأبعاد تلقائياً
    $('input[name="dimensions"]').on('blur', function() {
        var value = $(this).val().trim();
        if (!value) return;

        try {
            // محاولة تحليل JSON
            var json = JSON.parse(value);
            if (json.length && json.width && json.height) {
                var formatted = json.length + 'x' + json.width + 'x' + json.height;
                $(this).val(formatted);
                toastr.success('تم تنسيق الأبعاد إلى: ' + formatted + ' سم');
            }
        } catch(e) {
            // ليس JSON، تحقق من الصيغة LxWxH
            var cleaned = value.replace(/\s+/g, '').replace(/[xX×]/g, 'x');
            if (/^[0-9]+(\.[0-9]+)?x[0-9]+(\.[0-9]+)?x[0-9]+(\.[0-9]+)?$/.test(cleaned)) {
                if (cleaned !== value) {
                    $(this).val(cleaned);
                    toastr.info('تم تنظيف الأبعاد: ' + cleaned + ' سم');
                }
            }
        }
    });

});
</script>
@endsection
