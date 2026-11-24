@extends('stock::master')

@section('title', 'تعديل ' . $product->name)

@section('content')
    @php
        /*
            تكييف الحقول حسب النوع.

            كان الشرط `$product->type === 'services'` — مقارنةَ نصٍّ واحد،
            تحرس بطاقة «إدارة المخزون» وحدها. فيُسأل عن **وزن الخدمة
            وطولها وعرضها وارتفاعها** ونوع مادّتها ووحداتها، وهي أسئلة بلا
            معنى تُملأ عشوائياً أو تُترك فارغة فتشوّش البيانات.

            وأيّ نوعٍ جديد بلا رصيد (كالمنتج الرقمي) كان يفلت من الحراسة
            كلّها، إذ الشرط يسمّي «الخدمات» بالاسم لا بالصفة.

            `ProductType` يجيب بالقدرة: هل له رصيد؟ هل يُشحن؟ هل يُرجَع؟
            وهي الإجابة نفسها التي يعتمدها حارس الأذون — فلا تفترق الشاشة
            عن الحفظ.
        */
        $productType = \App\Enums\ProductType::from_value($product->type);
        $tracksStock = $productType->tracksStock();
        $isShippable = $productType->isShippable();
        $canReturn = $productType->isReturnable();

        $isService = ! $tracksStock;   // يبقى للعناوين والأيقونات
        $units = $product->productUnits ?? collect();
        $hasUnits = $units->count() > 0;

        // الوحدة الأساسية تقود أسعار المنتج، فتُقفَل الحقول العامّة حين توجد وحدات
        $baseUnit = $units->firstWhere('categoryUnit.is_base_unit', true) ?? $units->first();

        $taxOptions = [1 => 'القيمة المضافة', 2 => 'صفرية', 3 => 'قيمة مضافة'];
        $dimensions = is_array($product->dimensions)
            ? $product->dimensions
            : (array) json_decode((string) $product->dimensions, true);
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل ' . $productType->label(),
            'icon' => $productType->icon(),
            'sub' => $product->name . ' — ' . $product->serial_number,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                $product->name => route('products.show', $product->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('products.show', $product->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="button" id="update-product-btn" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form id="products_form" action="{{ route('products.update', $product->id) }}"
              method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="type" value="{{ $product->type }}">

            <div class="row">
                {{-- ══════════ البيانات ══════════ --}}
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-info"></i>
                                بيانات {{ $productType->label() }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="{{ $isService ? 'col-12' : 'col-md-8' }}">
                                    <div class="form-group">
                                        <label for="product-name" class="stk-req">الاسم</label>
                                        <input type="text" id="product-name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name', $product->name) }}">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                @unless ($isService)
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="product_material_type" class="stk-req">نوع المادة</label>
                                            <select id="product_material_type" name="material_type" required
                                                    class="form-control @error('material_type') is-invalid @enderror">
                                                <option value="">اختر…</option>
                                                @foreach (['raw' => 'خام (مادة أولية)', 'semi_finished' => 'نصف مصنَّع', 'finished' => 'منتج نهائي'] as $value => $label)
                                                    <option value="{{ $value }}"
                                                        {{ old('material_type', $product->material_type) === $value ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('material_type')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endunless

                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{-- اختياريّ بقرار صاحب النظام — لكل الأنواع --}}
                                        <label for="category-input">التصنيف <span class="text-muted">(اختياري)</span></label>
                                        <select id="category-input" name="category_id"
                                                class="form-control select2 @error('category_id') is-invalid @enderror">
                                            <option value="">بدون تصنيف</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}"
                                                    {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-create-first
                                            :count="count($categories ?? [])"
                                            route="category.create"
                                            permission="stock.categories.create"
                                            :plan="\App\Constants\planFeatures\StockPlanFeatures::CATEGORIES"
                                            label="تصنيفاً"
                                            hint="المنتج يتبع تصنيفاً، ولا تصنيفات مسجَّلة بعد." />
                                        @error('category_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="serial-number">الرقم التسلسلي</label>
                                        {{-- حقلٌ تلقائي: الخادم يُصدره ويتجاهل ما يصل من النموذج، فالحقل هنا للعرض فقط --}}
                                        <input type="text" id="serial-number" name="serial_number" readonly tabindex="-1"
                                               class="form-control text-center stk-num stk-locked"
                                               value="{{ $product->serial_number }}">
                                        <span class="stk-help stk-help--locked">
                                            <i class="feather icon-lock"></i>
                                            الرقم التسلسلي لا يُعدَّل بعد إصداره
                                        </span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="barcode">الباركود</label>
                                        <input type="text" id="barcode" name="barcode"
                                               class="form-control stk-num pricing-field"
                                               value="{{ old('barcode', $product->barcode) }}"
                                               {{ $hasUnits ? 'readonly' : '' }}>
                                        @if ($hasUnits)
                                            <span class="stk-help">يُقرأ من باركود الوحدة الأساسية</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier-id">المورّد</label>
                                        {{-- كان `<input type="number">` يطلب رقم المورّد بيد المستخدم --}}
                                        <select id="supplier-id" name="supplier_id" class="form-control select2">
                                            <option value="">بلا مورّد</option>
                                            @foreach ($suppliers ?? [] as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                    @selected(old('supplier_id', $product->supplier_id) == $supplier->id)>
                                                    {{ $supplier->label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-create-first
                                            :count="count($suppliers ?? [])"
                                            route="purchases.supplier_settings.create"
                                            label="مورّداً"
                                            hint="الموردون يُدارون في وحدة المشتريات، ولا مورّد مسجَّل بعد." />
                                        @if (empty($suppliers) || ! count($suppliers))
                                            <small class="stk-help">لا مورّدين معرَّفين بعد.</small>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="description">الوصف</label>
                                        <textarea id="description" name="description" class="form-control"
                                                  rows="3">{{ old('description', $product->description) }}</textarea>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="product-images">الصورة</label>
                                        <label for="product-images" class="stk-upload">
                                            {{-- الصورة الحالية معروضة: كان الحقل عارياً فلا تعرف ما المحفوظ --}}
                                            <img src="{{ $product->images ? asset($product->images) : '' }}"
                                                 alt="{{ $product->name }}"
                                                 class="stk-upload__preview {{ $product->images ? '' : 'd-none' }}"
                                                 id="images_preview">
                                            <span class="stk-upload__placeholder {{ $product->images ? 'd-none' : '' }}"
                                                  id="images_placeholder">
                                                <i class="feather icon-upload-cloud"></i>
                                                <span class="stk-upload__title">اسحب صورة أو اضغط للاختيار</span>
                                                <span class="stk-upload__hint">PNG أو JPG</span>
                                            </span>
                                        </label>
                                        <input type="file" id="product-images" name="images" accept="image/*" class="d-none">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="stk-switch mb-0">
                                        <input type="checkbox" name="available_pos" id="available_pos" value="1"
                                               {{ old('available_pos', $product->available_pos) ? 'checked' : '' }}>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">متاح في نقاط البيع</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ══════════ المتجر الإلكتروني ══════════ --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-shopping-bag"></i> المتجر الإلكتروني</h4>
                        </div>
                        <div class="card-body">
                            <label class="stk-switch">
                                <input type="checkbox" name="available_online" id="available_online" value="1"
                                       {{ old('available_online', $product->available_online) ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">متاح أونلاين</span>
                                    <span class="stk-switch__hint">يظهر في المتجر</span>
                                </span>
                            </label>

                            <label class="stk-switch mt-2 mb-2">
                                <input type="checkbox" name="featured_product" id="featured_product" value="1"
                                       {{ old('featured_product', $product->featured_product) ? 'checked' : '' }}
                                       {{ $product->available_online ? '' : 'disabled' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">منتج مميّز</span>
                                    <span class="stk-switch__hint">يتطلّب الإتاحة أونلاين</span>
                                </span>
                            </label>

                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="brand_id">العلامة التجارية</label>
                                        <select id="brand_id" name="brand_id" class="form-control select2">
                                            <option value="">بلا علامة</option>
                                            @foreach ($brands as $brand)
                                                <option value="{{ $brand->id }}"
                                                    {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>
                                                    {{ $brand->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-create-first
                                            :count="count($brands ?? [])"
                                            route="stock.brands.create"
                                            permission="stock.brands.create"
                                            :plan="\App\Constants\planFeatures\StockPlanFeatures::BRANDS"
                                            label="علامة تجارية"
                                            hint="لا علامات تجارية مسجَّلة بعد — وهي اختيارية للمنتج." />
                                    </div>
                                </div>

                                {{-- الوزن والأبعاد لِما يُشحن مادّياً؛ لا معنى لطول خدمة --}}
                                @if ($isShippable)
                                    <div class="col-md-3 col-6">
                                        <div class="form-group">
                                            <label for="weight">الوزن (كجم)</label>
                                            <input type="number" id="weight" name="weight" step="0.001" min="0"
                                                   class="form-control stk-num"
                                                   value="{{ old('weight', $product->weight) }}">
                                        </div>
                                    </div>

                                    @foreach (['length' => 'الطول', 'width' => 'العرض', 'height' => 'الارتفاع'] as $dim => $label)
                                        <div class="col-md-3 col-6">
                                            <div class="form-group">
                                                <label for="{{ $dim }}">{{ $label }} (سم)</label>
                                                <input type="number" id="{{ $dim }}" name="dimensions[{{ $dim }}]"
                                                       step="0.01" min="0" class="form-control stk-num"
                                                       value="{{ old('dimensions.' . $dim, $dimensions[$dim] ?? '') }}">
                                            </div>
                                        </div>
                                    @endforeach
                                @endif

                                {{-- الخدمة تُؤدّى ولا تُردّ، فلا سؤال عن مدّة إرجاعها --}}
                                @if ($canReturn)
                                <div class="col-md-6">
                                    <label class="stk-switch">
                                        <input type="checkbox" name="is_returnable" id="is_returnable" value="1"
                                               {{ old('is_returnable', $product->is_returnable ?? true) ? 'checked' : '' }}>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">قابل للإرجاع</span>
                                        </span>
                                    </label>
                                </div>

                                <div class="col-md-6" id="return_days_field">
                                    <div class="form-group mb-0">
                                        <label for="return_days">مدة الإرجاع (أيام)</label>
                                        <input type="number" id="return_days" name="return_days" min="1" max="365"
                                               class="form-control stk-num"
                                               value="{{ old('return_days', $product->return_days ?? 14) }}">
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ══════════ الوحدات والتسعير ══════════ --}}
                <div class="col-lg-6 col-12">
                    {{-- وحدات القياس والتحويل بينها معنىً مخزنيّ: الخدمة تُباع بالمرّة --}}
                    <div class="card @unless ($tracksStock) d-none @endunless" id="product-units-card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-box"></i> وحدات المنتج وأسعارها</h4>
                            @if ($hasUnits)
                                <span class="stk-count">{{ $units->count() }}</span>
                            @endif
                        </div>
                        <div class="card-body">
                            {{--
                                الأسعار تُشتقّ من الوحدة الأساسية بمعامل التحويل،
                                وتبقى قابلة للتعديل — انظر `partials.unit-price-sync`.
                            --}}
                            <div class="stk-help d-flex align-items-center justify-content-between mb-2">
                                <span>
                                    <i class="feather icon-info"></i>
                                    سعرُ الوحدة الأساسية يملأ أسعار الوحدات المرتبطة بمعامل التحويل — وأيّ قيمة تُحرَّر يدوياً تبقى كما هي.
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="rederive-unit-prices">
                                    <i class="feather icon-refresh-cw"></i> إعادة الاشتقاق
                                </button>
                            </div>

                            <div id="product-units-container">
                                @foreach ($units as $index => $productUnit)
                                    @php
                                        $isBase = ($productUnit->categoryUnit && $productUnit->categoryUnit->is_base_unit)
                                            || ($baseUnit && $productUnit->id === $baseUnit->id);
                                        $unit = optional($productUnit->categoryUnit)->unit;
                                    @endphp

                                    <div class="stk-unit-card" data-is-base="{{ $isBase ? '1' : '0' }}"
                                         data-factor="{{ optional($productUnit->categoryUnit)->conversion_factor ?: 1 }}">
                                        <div class="stk-unit-card__head">
                                            <span class="stk-unit-card__name">
                                                {{ $unit->name ?? '—' }}{{ $unit && $unit->symbol ? ' (' . $unit->symbol . ')' : '' }}
                                            </span>
                                            @if ($isBase)
                                                <span class="badge badge-light-success">أساسية</span>
                                            @endif
                                            <span class="stk-unit-card__factor stk-num">
                                                معامل {{ optional($productUnit->categoryUnit)->conversion_factor }}
                                            </span>
                                        </div>

                                        <input type="hidden" name="units[{{ $index }}][id]" value="{{ $productUnit->id }}">
                                        <input type="hidden" name="units[{{ $index }}][category_unit_id]"
                                               value="{{ $productUnit->category_unit_id }}">
                                        <input type="hidden" name="units[{{ $index }}][is_base_unit]"
                                               value="{{ $isBase ? '1' : '0' }}">

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label>الباركود</label>
                                                    <input type="text" class="form-control stk-num unit-barcode"
                                                           data-is-base="{{ $isBase ? '1' : '0' }}"
                                                           name="units[{{ $index }}][barcode]"
                                                           value="{{ $productUnit->barcode }}">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label>سعر الشراء</label>
                                                    <input type="number" step="0.01" class="form-control stk-num unit-purchase-price"
                                                           data-is-base="{{ $isBase ? '1' : '0' }}"
                                                           name="units[{{ $index }}][purchase_price]"
                                                           value="{{ $productUnit->purchase_price }}" placeholder="0.00">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label>سعر البيع</label>
                                                    <input type="number" step="0.01" class="form-control stk-num unit-sale-price"
                                                           data-is-base="{{ $isBase ? '1' : '0' }}"
                                                           name="units[{{ $index }}][sale_price]"
                                                           value="{{ $productUnit->sale_price }}" placeholder="0.00">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-1">
                                                    <label>أدنى سعر بيع</label>
                                                    <input type="number" step="0.01" class="form-control stk-num unit-min-sale-price"
                                                           data-is-base="{{ $isBase ? '1' : '0' }}"
                                                           name="units[{{ $index }}][min_sale_price]"
                                                           value="{{ $productUnit->min_sale_price }}" placeholder="0.00">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-group mb-0">
                                                    <label>سعر الجملة</label>
                                                    <input type="number" step="0.01" class="form-control stk-num unit-wholesale-price"
                                                           data-is-base="{{ $isBase ? '1' : '0' }}"
                                                           name="units[{{ $index }}][wholesale_price]"
                                                           value="{{ $productUnit->wholesale_price }}" placeholder="0.00">
                                                </div>
                                            </div>
                                            <div class="col-6 d-flex align-items-end">
                                                <label class="stk-switch stk-switch--sm mb-1">
                                                    <input type="checkbox" name="units[{{ $index }}][is_default]" value="1"
                                                           {{ $productUnit->is_default ? 'checked' : '' }}>
                                                    <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                                    <span class="stk-switch__label">
                                                        <span class="stk-switch__title">وحدة افتراضية</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="stk-empty" id="no-units-message" {{ $hasUnits ? 'hidden' : '' }}>
                                <div class="stk-empty__icon"><i class="feather icon-box"></i></div>
                                <h5 class="stk-empty__title">لا وحدات مرتبطة</h5>
                                <p class="stk-empty__hint">اختر صنفاً له وحدات لتظهر هنا</p>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-dollar-sign"></i> التسعير</h4>
                            <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info" id="pricing-units-notice" {{ $hasUnits ? '' : 'hidden' }}>
                                <i class="feather icon-info"></i>
                                الأسعار مرتبطة بالوحدة الأساسية — عدّلها من «وحدات المنتج وأسعارها».
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purchase-price">سعر الشراء</label>
                                        <input type="number" step="0.01" min="0" id="purchase-price" name="purchase_price"
                                               class="form-control stk-num pricing-field"
                                               value="{{ old('purchase_price', $product->purchase_price) }}"
                                               {{ $hasUnits ? 'readonly' : '' }}>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sale-price">سعر البيع</label>
                                        <input type="number" step="0.01" min="0" id="sale-price" name="sale_price"
                                               class="form-control stk-num pricing-field"
                                               value="{{ old('sale_price', $product->sale_price) }}"
                                               {{ $hasUnits ? 'readonly' : '' }}>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tax1">الضريبة الأولى</label>
                                        <select id="tax1" name="tax1" class="form-control">
                                            <option value="">بلا ضريبة</option>
                                            @foreach ($taxOptions as $value => $label)
                                                <option value="{{ $value }}"
                                                    {{ old('tax1', $product->tax1) == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tax2">الضريبة الثانية</label>
                                        <select id="tax2" name="tax2" class="form-control">
                                            <option value="">بلا ضريبة</option>
                                            @foreach ($taxOptions as $value => $label)
                                                <option value="{{ $value }}"
                                                    {{ old('tax2', $product->tax2) == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="min-sale-price">أدنى سعر بيع</label>
                                        <input type="number" step="0.01" min="0" id="min-sale-price" name="min_sale_price"
                                               class="form-control stk-num pricing-field"
                                               value="{{ old('min_sale_price', $product->min_sale_price) }}"
                                               {{ $hasUnits ? 'readonly' : '' }}>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="profit-margin">هامش الربح %</label>
                                        <input type="number" step="0.01" id="profit-margin" name="profit_margin"
                                               class="form-control stk-num"
                                               value="{{ old('profit_margin', $product->profit_margin) }}">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="discount">الخصم</label>
                                        <input type="number" step="0.01" min="0" id="discount" name="discount"
                                               class="form-control stk-num"
                                               value="{{ old('discount', $product->discount) }}">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="discount-type">نوعه</label>
                                        <select id="discount-type" name="discount_type" class="form-control">
                                            <option value="1" {{ old('discount_type', $product->discount_type) == 1 ? 'selected' : '' }}>نسبة %</option>
                                            <option value="2" {{ old('discount_type', $product->discount_type) == 2 ? 'selected' : '' }}>مبلغ ثابت</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="sales-account">حساب المبيعات</label>
                                        <select id="sales-account" name="sales_account" class="form-control select2">
                                            <option value="">من «الحسابات المحاسبية» في الإعدادات</option>
                                            @foreach ($salesAccounts ?? [] as $postingAccount)
                                                <option value="{{ $postingAccount->id }}"
                                                    @selected(old('sales_account', $product->sales_account) == $postingAccount->id)>
                                                    {{ $postingAccount->code }} — {{ $postingAccount->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="stk-help">
                                            تجاوزٌ اختياري لهذا المنتج — يُترك فارغاً ليتبع الإعدادات.
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="sales-cost-account">حساب تكلفة المبيعات</label>
                                        <select id="sales-cost-account" name="sales_cost_account" class="form-control select2">
                                            <option value="">من «الحسابات المحاسبية» في الإعدادات</option>
                                            @foreach ($costAccounts ?? [] as $postingAccount)
                                                <option value="{{ $postingAccount->id }}"
                                                    @selected(old('sales_cost_account', $product->sales_cost_account) == $postingAccount->id)>
                                                    {{ $postingAccount->code }} — {{ $postingAccount->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="stk-help">
                                            تجاوزٌ اختياري لهذا المنتج — يُترك فارغاً ليتبع الإعدادات.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ إدارة المخزون ══════════ --}}
            @unless ($isService)
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-crosshair"></i> إدارة المخزون</h4>
                    </div>
                    <div class="card-body">
                        {{--
                            **ما كان**

                            المفتاح بلا `name` — أي لا يُرسَل — والحقول `disabled`
                            حتى يُفعَّل. والمتصفّح **لا يرسل الحقول المعطَّلة**، فمن
                            فتح منتجاً بلا تتبّع وعدّله حُفظ بلا نوع تتبّع مهما اختار.

                            و`inventory_type` يكتبه الكنترولر من الطلب ولا حقل له في
                            النموذج إطلاقاً — فكان `null` في كل منتج منذ اليوم الأول.

                            **ما صار**

                            المفتاح حقلٌ باسمٍ يُرسَل، والحقول تبقى مفعَّلة لكن تُخفى
                            حين يُطفأ — فتصل قيمها ولا تضيع.
                        --}}
                        <label class="stk-switch">
                            <input type="checkbox" id="ProductTrackStock" name="track_stock" value="1"
                                   @checked(($product->track_inventory ?? 4) != 4)>
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">تتبّع مخزون هذا المنتج</span>
                            </span>
                        </label>

                        <div class="row mt-2" id="tracking-fields">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group mb-0">
                                    <label for="tracking-select">نوع التتبع</label>
                                    <select id="tracking-select" name="track_inventory" class="form-control">
                                        @foreach ([0 => 'الرقم التسلسلي', 1 => 'رقم الشحنة', 2 => 'تاريخ الانتهاء', 3 => 'رقم الشحنة وتاريخ الانتهاء', 4 => 'الكمية فقط'] as $value => $label)
                                            <option value="{{ $value }}"
                                                @selected(old('track_inventory', $product->track_inventory) == $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group mb-0">
                                    <label for="inventory_type">طبيعة الصنف</label>
                                    <select id="inventory_type" name="inventory_type" class="form-control">
                                        <option value="">غير محدَّد</option>
                                        @foreach (['stored' => 'مخزَّن', 'consumable' => 'مستهلك', 'service' => 'خدمة', 'raw' => 'مادة خام'] as $value => $label)
                                            <option value="{{ $value }}"
                                                @selected(old('inventory_type', $product->inventory_type) == $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group mb-0">
                                    <label for="low_stock_alert">نبّهني عند النزول تحت</label>
                                    <input type="number" id="low_stock_alert" name="low_stock_alert"
                                           class="form-control stk-num"
                                           value="{{ old('low_stock_alert', $product->low_stock_alert) }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6" id="expiry-date-field" hidden>
                                <div class="form-group mb-0">
                                    <label for="expiry_date">تاريخ الانتهاء</label>
                                    <input type="date" id="expiry_date" name="expiry_date" class="form-control"
                                           value="{{ old('expiry_date', optional($product->expiry_date)->format('Y-m-d') ?? $product->expiry_date) }}">
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6" id="notify-before-expiry-field" hidden>
                                <div class="form-group mb-0">
                                    <label for="notify_before_days">نبّهني قبل الانتهاء (أيام)</label>
                                    <input type="number" id="notify_before_days" name="notify_before_days" min="0"
                                           class="form-control stk-num"
                                           value="{{ old('notify_before_days', $product->notify_before_days) }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endunless

            {{-- ══════════ ملاحظات وحالة ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-file-text"></i> ملاحظات وحالة</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label for="internal-notes">ملاحظات داخلية</label>
                                <textarea id="internal-notes" name="Internal_notes" class="form-control"
                                          rows="3">{{ old('Internal_notes', $product->Internal_notes) }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label for="tags">وسوم</label>
                                <input type="text" id="tags" name="tags" class="form-control"
                                       value="{{ old('tags', $product->tags) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    @php $current = old('status', $product->status->value); @endphp
                                    <option value="active" {{ $current === 'active' ? 'selected' : '' }}>نشط</option>
                                    <option value="unactive" {{ $current === 'unactive' ? 'selected' : '' }}>غير نشط</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <a href="{{ route('products.show', $product->id) }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="button" id="update-product-btn-bottom" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ التعديلات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        /* jQuery وSelect2 يأتيان من القالب — إعادة تحميلهما هنا كانت تُفقد
           كل الإضافات المسجَّلة عليهما. */
        $(function () {
            if ($.fn.select2) {
                $('#products_form .select2').select2({ placeholder: 'اختر…', allowClear: true, dir: 'rtl', width: '100%' });
            }

            const form = $('#products_form');

            /*
                الحقول تُخفى ولا تُعطَّل.

                كان المفتاح يضع `disabled` على الحقول — والمتصفّح لا يرسل
                الحقول المعطَّلة، فتضيع قيمها عند الحفظ. الإخفاء يُبقيها
                تُرسَل، وقيمة «الكمية فقط» هي ما يعنيه إطفاء المفتاح.
            */
            function syncTrackingFields() {
                var trackToggle = document.getElementById('ProductTrackStock');
                if (! trackToggle) return; // بلا هذا المفتاح للخدمات، لا يُطبَع في الصفحة أصلاً

                var on = trackToggle.checked;
                document.getElementById('tracking-fields').hidden = ! on;

                if (! on) {
                    $('#tracking-select').val('4');
                }
            }

            $('#ProductTrackStock').on('change', syncTrackingFields);
            syncTrackingFields();

            /*
                هامش الربح يُحسب من سعرَي الشراء والبيع.

                كان حقلاً يُكتب بيدٍ ولا يرتبط بشيء — فبقي فارغاً في كل
                منتج. وهو نسبةٌ مشتقّة لا مُدخَل:  (بيع − شراء) ÷ شراء × 100.
                ويبقى قابلاً للتحرير: تحريره يدوياً يُثبّته.
            */
            function syncProfitMargin() {
                var margin = document.getElementById('profit-margin');

                if (! margin || margin.dataset.manual === '1') {
                    return;
                }

                var purchase = parseFloat($('#purchase-price').val()) || 0;
                var sale = parseFloat($('#sale-price').val()) || 0;

                margin.value = purchase > 0 ? (((sale - purchase) / purchase) * 100).toFixed(2) : '';
            }

            $(document).on('input', '#purchase-price, #sale-price', syncProfitMargin);
            $(document).on('input', '#profit-margin', function () {
                this.dataset.manual = '1';
            });
            syncProfitMargin();

            $('#available_online').on('change', function () {
                const featured = document.getElementById('featured_product');
                featured.disabled = !this.checked;
                if (!this.checked) featured.checked = false;
            });

            $('#is_returnable').on('change', function () {
                document.getElementById('return_days_field').hidden = !this.checked;
            }).trigger('change');

            $('#tracking-select').on('change', function () {
                const showExpiry = this.value === '2' || this.value === '3';
                document.getElementById('expiry-date-field').hidden = !showExpiry;
                document.getElementById('notify-before-expiry-field').hidden = !showExpiry;
            }).trigger('change');

            $('#product-images').on('change', function () {
                const preview = document.getElementById('images_preview');
                const placeholder = document.getElementById('images_placeholder');
                const picked = this.files && this.files[0];
                if (picked) preview.src = URL.createObjectURL(picked);
                preview.classList.toggle('d-none', !picked);
                placeholder.classList.toggle('d-none', !!picked);
            });

            $('#products_form').on('focus', 'input, select, textarea', function () {
                $(this).removeClass('is-invalid');
            });

            /* ══════════ وحدات الصنف ══════════ */

            function escapeHtml(value) {
                return $('<div>').text(value == null ? '' : value).html();
            }

            function unitRow(unit, index, isBase) {
                const symbol = unit.unit_symbol ? ' (' + escapeHtml(unit.unit_symbol) + ')' : '';
                const base = isBase ? '1' : '0';

                return ''
                    + '<div class="stk-unit-card" data-is-base="' + base + '"'
                    + '     data-factor="' + escapeHtml(unit.conversion_factor) + '">'
                    + '  <div class="stk-unit-card__head">'
                    + '    <span class="stk-unit-card__name">' + escapeHtml(unit.unit_name) + symbol + '</span>'
                    + (isBase ? '<span class="badge badge-light-success">أساسية</span>' : '')
                    + '    <span class="stk-unit-card__factor stk-num">معامل ' + escapeHtml(unit.conversion_factor) + '</span>'
                    + '  </div>'
                    + '  <input type="hidden" name="units[' + index + '][category_unit_id]" value="' + escapeHtml(unit.id) + '">'
                    + '  <input type="hidden" name="units[' + index + '][is_base_unit]" value="' + base + '">'
                    + '  <div class="row">'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>الباركود</label>'
                    + '      <input type="text" class="form-control stk-num unit-barcode" data-is-base="' + base + '"'
                    + '             name="units[' + index + '][barcode]"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>سعر الشراء</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num unit-purchase-price" data-is-base="' + base + '"'
                    + '             name="units[' + index + '][purchase_price]" placeholder="0.00"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>سعر البيع</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num unit-sale-price" data-is-base="' + base + '"'
                    + '             name="units[' + index + '][sale_price]" placeholder="0.00"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>أدنى سعر بيع</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num unit-min-sale-price" data-is-base="' + base + '"'
                    + '             name="units[' + index + '][min_sale_price]" placeholder="0.00"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-0"><label>سعر الجملة</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num"'
                    + '             name="units[' + index + '][wholesale_price]" placeholder="0.00"></div></div>'
                    + '    <div class="col-6 d-flex align-items-end">'
                    + '      <label class="stk-switch stk-switch--sm mb-1">'
                    + '        <input type="checkbox" name="units[' + index + '][is_default]" value="1"' + (isBase ? ' checked' : '') + '>'
                    + '        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>'
                    + '        <span class="stk-switch__label"><span class="stk-switch__title">وحدة افتراضية</span></span>'
                    + '      </label>'
                    + '    </div>'
                    + '  </div>'
                    + '</div>';
            }

            function loadCategoryUnits(categoryId) {
                if (!categoryId) return;

                const container = $('#product-units-container');
                const empty = document.getElementById('no-units-message');

                container.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>');

                $.getJSON('{{ route('products.getCategoryUnits') }}', { category_id: categoryId })
                    .done(function (response) {
                        container.empty();

                        const units = (response && response.success && response.units) ? response.units : [];
                        empty.hidden = units.length > 0;
                        document.getElementById('pricing-units-notice').hidden = units.length === 0;
                        $('.pricing-field').prop('readonly', units.length > 0);

                        if (!units.length) return;

                        let baseIndex = units.findIndex(u => u.is_base_unit);
                        if (baseIndex === -1) baseIndex = 0;

                        units.forEach(function (unit, index) {
                            container.append(unitRow(unit, index, unit.is_base_unit || index === baseIndex));
                        });

                        // البطاقات بُنيت الآن: تُشتقّ أسعارها إن كان للأساسية سعر
                        if (window.deriveUnitPrices) window.deriveUnitPrices(false);
                    })
                    .fail(function () {
                        container.html('<div class="alert alert-danger mb-0">تعذّر تحميل وحدات الصنف</div>');
                    });
            }

            const mirror = {
                '.unit-barcode': '#barcode',
                '.unit-purchase-price': '#purchase-price',
                '.unit-sale-price': '#sale-price',
                '.unit-min-sale-price': '#min-sale-price',
            };

            $.each(mirror, function (source, target) {
                $('#product-units-container').on('input', source + '[data-is-base="1"]', function () {
                    $(target).val(this.value);
                });
            });

            /* تغيير الصنف يستبدل الوحدات — لا يُستدعى عند التحميل كي لا
               تُمحى وحدات المنتج المحفوظة وقيمها. */
            $('#category-input').on('change', function () { loadCategoryUnits(this.value); });

            /* ══════════ الحفظ ══════════ */

            function save() {
                const name = $('#product-name').val().trim();
                if (!name) {
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text: 'أدخل اسم المنتج' });
                    $('#product-name').addClass('is-invalid').focus();
                    return;
                }

                Swal.fire({
                    title: 'حفظ التعديلات؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (result.isConfirmed) submit();
                });
            }

            function submit() {
                Swal.fire({
                    title: 'جارٍ الحفظ…',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                });

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',   // مع _method=PUT في النموذج
                    data: new FormData(form[0]),
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .done(function (response) {
                        // الكنترولر قد يعيد JSON أو إعادة توجيه HTML
                        if (typeof response !== 'object') {
                            window.location.href = '{{ route('products.show', $product->id) }}';
                            return;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحفظ',
                            text: response.message || 'حُدِّثت بيانات المنتج',
                            showCancelButton: true,
                            confirmButtonText: 'عرض المنتج',
                            cancelButtonText: 'متابعة التعديل',
                        }).then(result => {
                            if (result.isConfirmed) {
                                window.location.href = response.redirect || '{{ route('products.show', $product->id) }}';
                            }
                        });
                    })
                    .fail(function (xhr) {
                        const messages = {
                            419: 'انتهت صلاحية الجلسة — أعد تحميل الصفحة',
                            404: 'المنتج غير موجود أو حُذف',
                            0: 'تعذّر الاتصال بالشبكة',
                        };

                        let message = messages[xhr.status] || 'حدث خطأ أثناء الحفظ';
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            message = Object.values(xhr.responseJSON.errors).flat().join('\n');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        Swal.fire({ icon: 'error', title: 'تعذّر الحفظ', text: message });
                    });
            }

            $('#update-product-btn, #update-product-btn-bottom').on('click', save);
            form.on('submit', function (e) { e.preventDefault(); save(); });
        });
    </script>

    @include('stock::products.partials.unit-price-sync')
@endsection
