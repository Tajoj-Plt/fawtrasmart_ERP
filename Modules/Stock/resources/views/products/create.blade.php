@extends('stock::master')

@section('title', 'إضافة منتج')

@section('content')
    @php
        $multiUnits = isset($role) && $role;
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إضافة منتج',
            'icon' => 'package',
            'sub' => 'منتج مخزَّن — يُتتبَّع بالكمية ويُسعَّر بالوحدة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                'منتج جديد' => null,
            ],
            'actions' => '<a href="' . route('products.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="button" id="save-product-btn" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ المنتج</button>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form id="products_form" action="{{ route('products.store') }}"
              method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="products">

            <div class="row">
                {{-- ══════════ بيانات المنتج ══════════ --}}
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info"></i> بيانات المنتج</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="product-name" class="stk-req">الاسم</label>
                                        <input type="text" id="product-name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name') }}">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_material_type" class="stk-req">نوع المادة</label>
                                        <select id="product_material_type" name="material_type" required
                                                class="form-control @error('material_type') is-invalid @enderror">
                                            <option value="">اختر…</option>
                                            @foreach (['raw' => 'خام (مادة أولية)', 'semi_finished' => 'نصف مصنَّع', 'finished' => 'منتج نهائي'] as $value => $label)
                                                <option value="{{ $value }}" {{ old('material_type') === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('material_type')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{-- اختياريّ بقرار صاحب النظام — لكل الأنواع --}}
                                        <label for="category-input">التصنيف <span class="text-muted">(اختياري)</span></label>
                                        <select id="category-input" name="category_id"
                                                class="form-control select2 @error('category_id') is-invalid @enderror">
                                            <option value="">بدون تصنيف</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}"
                                                    {{ old('category_id') == $category->id ? 'selected' : '' }}>
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
                                        <span class="stk-help">وحدات الصنف تُحمَّل تلقائياً بعد الاختيار</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="serial-number">الرقم التسلسلي</label>
                                        {{-- حقلٌ تلقائي: الخادم يُصدره ويتجاهل ما يصل من النموذج، فالحقل هنا للعرض فقط --}}
                                        <input type="text" id="serial-number" name="serial_number" readonly tabindex="-1"
                                               class="form-control text-center stk-num stk-locked"
                                               value="{{ $serial_number ?? '' }}">
                                        <span class="stk-help stk-help--locked">
                                            <i class="feather icon-lock"></i>
                                            يُصدره النظام عند الحفظ من إعدادات الترقيم
                                        </span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="barcode">الباركود</label>
                                        <input type="text" id="barcode" name="barcode"
                                               class="form-control stk-num pricing-field"
                                               value="{{ old('barcode') }}" placeholder="يُولَّد تلقائياً إن تُرك فارغاً">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier-id">المورّد</label>
                                        {{--
                                            كان `<input type="number">` — أي أن على المستخدم
                                            أن يعرف **رقم** المورّد ويكتبه بيده. ولا أحد يحفظ
                                            أرقام مورّديه، فبقي الحقل فارغاً في كل منتج.
                                        --}}
                                        <select id="supplier-id" name="supplier_id" class="form-control select2">
                                            <option value="">بلا مورّد</option>
                                            @foreach ($suppliers ?? [] as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                    @selected(old('supplier_id', null) == $supplier->id)>
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
                                                  rows="3">{{ old('description') }}</textarea>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="product-images">الصورة</label>
                                        <label for="product-images" class="stk-upload">
                                            <img src="" alt="" class="stk-upload__preview d-none" id="images_preview">
                                            <span class="stk-upload__placeholder" id="images_placeholder">
                                                <i class="feather icon-upload-cloud"></i>
                                                <span class="stk-upload__title">اسحب الصورة أو اضغط للاختيار</span>
                                                <span class="stk-upload__hint">PNG أو JPG</span>
                                            </span>
                                        </label>
                                        <input type="file" id="product-images" name="images" accept="image/*" class="d-none">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="stk-switch mb-0">
                                        <input type="checkbox" name="available_pos" id="available_pos" value="1"
                                               {{ old('available_pos') ? 'checked' : '' }}>
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">متاح في نقاط البيع</span>
                                            <span class="stk-switch__hint">يظهر المنتج على شاشة الكاشير</span>
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
                                       {{ old('available_online') ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">متاح أونلاين</span>
                                    <span class="stk-switch__hint">يظهر المنتج في المتجر</span>
                                </span>
                            </label>

                            <label class="stk-switch mt-2 mb-2">
                                <input type="checkbox" name="featured_product" id="featured_product" value="1"
                                       {{ old('featured_product') ? 'checked' : '' }}
                                       {{ old('available_online') ? '' : 'disabled' }}>
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
                                                    {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
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

                                <div class="col-md-3 col-6">
                                    <div class="form-group">
                                        <label for="weight">الوزن (كجم)</label>
                                        <input type="number" id="weight" name="weight" step="0.001" min="0"
                                               class="form-control stk-num" value="{{ old('weight') }}">
                                    </div>
                                </div>

                                @foreach (['length' => 'الطول', 'width' => 'العرض', 'height' => 'الارتفاع'] as $dim => $label)
                                    <div class="col-md-3 col-6">
                                        <div class="form-group">
                                            <label for="{{ $dim }}">{{ $label }} (سم)</label>
                                            <input type="number" id="{{ $dim }}" name="dimensions[{{ $dim }}]"
                                                   step="0.01" min="0" class="form-control stk-num"
                                                   value="{{ old('dimensions.' . $dim) }}">
                                        </div>
                                    </div>
                                @endforeach

                                <div class="col-md-6">
                                    <label class="stk-switch">
                                        <input type="checkbox" name="is_returnable" id="is_returnable" value="1"
                                               {{ old('is_returnable', '1') ? 'checked' : '' }}>
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
                                               class="form-control stk-num" value="{{ old('return_days', 14) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ══════════ الوحدات والتسعير ══════════ --}}
                <div class="col-lg-6 col-12">
                    <div class="card" id="product-units-card" hidden>
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-box"></i> وحدات المنتج وأسعارها</h4>
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

                            <div id="product-units-container"></div>
                            <div class="stk-empty" id="no-units-message" hidden>
                                <div class="stk-empty__icon"><i class="feather icon-box"></i></div>
                                <h5 class="stk-empty__title">لا وحدات لهذا الصنف</h5>
                                <p class="stk-empty__hint">أضف وحدات للصنف أولاً لتظهر هنا</p>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-dollar-sign"></i> التسعير</h4>
                            <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info" id="pricing-units-notice" hidden>
                                <i class="feather icon-info"></i>
                                الأسعار مرتبطة بالوحدة الأساسية — عدّلها من «وحدات المنتج وأسعارها».
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purchase-price">سعر الشراء</label>
                                        @if ($multiUnits)
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" id="purchase-price"
                                                       name="purchase_price" class="form-control stk-num pricing-field"
                                                       value="{{ old('purchase_price') }}">
                                                <select id="purchase-type" name="purchase_unit_id"
                                                        class="form-control" style="max-width:45%"></select>
                                            </div>
                                        @else
                                            <input type="number" step="0.01" min="0" id="purchase-price"
                                                   name="purchase_price" class="form-control stk-num pricing-field"
                                                   value="{{ old('purchase_price') }}">
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sale-price">سعر البيع</label>
                                        @if ($multiUnits)
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" id="sale-price"
                                                       name="sale_price" class="form-control stk-num pricing-field"
                                                       value="{{ old('sale_price') }}">
                                                <select id="sale-type" name="sales_unit_id"
                                                        class="form-control" style="max-width:45%"></select>
                                            </div>
                                        @else
                                            <input type="number" step="0.01" min="0" id="sale-price"
                                                   name="sale_price" class="form-control stk-num pricing-field"
                                                   value="{{ old('sale_price') }}">
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tax1">الضريبة الأولى</label>
                                        <select id="tax1" name="tax1" class="form-control">
                                            <option value="">بلا ضريبة</option>
                                            @foreach ($taxOptions ?? [] as $taxOption)
                                                <option value="{{ $taxOption->id }}"
                                                    @selected(old('tax1', null) == $taxOption->id)>
                                                    {{ $taxOption->name ?? 'ضريبة' }} ({{ $taxOption->tax }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                        @if (empty($taxOptions) || ! count($taxOptions))
                                            <small class="stk-help text-danger">
                                                <i class="feather icon-alert-triangle"></i>
                                                لم تُعرَّف ضرائب بعد — عرّفها من «الإعدادات ← الضرائب».
                                            </small>
                                        @else
                                            <small class="stk-help">من شاشة «الضرائب» في الإعدادات.</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tax2">الضريبة الثانية</label>
                                        <select id="tax2" name="tax2" class="form-control">
                                            <option value="">بلا ضريبة</option>
                                            @foreach ($taxOptions ?? [] as $taxOption)
                                                <option value="{{ $taxOption->id }}"
                                                    @selected(old('tax2', null) == $taxOption->id)>
                                                    {{ $taxOption->name ?? 'ضريبة' }} ({{ $taxOption->tax }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                        @if (empty($taxOptions) || ! count($taxOptions))
                                            <small class="stk-help text-danger">
                                                <i class="feather icon-alert-triangle"></i>
                                                لم تُعرَّف ضرائب بعد — عرّفها من «الإعدادات ← الضرائب».
                                            </small>
                                        @else
                                            <small class="stk-help">من شاشة «الضرائب» في الإعدادات.</small>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="min-sale-price">أدنى سعر بيع</label>
                                        <input type="number" step="0.01" min="0" id="min-sale-price"
                                               name="min_sale_price" class="form-control stk-num pricing-field"
                                               value="{{ old('min_sale_price') }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="profit-margin">هامش الربح %</label>
                                        <input type="number" step="0.01" id="profit-margin" name="profit_margin"
                                               class="form-control stk-num" value="{{ old('profit_margin') }}">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="discount">الخصم</label>
                                        <input type="number" step="0.01" min="0" id="discount" name="discount"
                                               class="form-control stk-num" value="{{ old('discount') }}">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="discount-type">نوعه</label>
                                        <select id="discount-type" name="discount_type" class="form-control">
                                            <option value="1" {{ old('discount_type') == 1 ? 'selected' : '' }}>نسبة %</option>
                                            <option value="2" {{ old('discount_type') == 2 ? 'selected' : '' }}>مبلغ ثابت</option>
                                        </select>
                                    </div>
                                </div>

                                {{-- حسابا المنتج: تجاوزٌ اختياري لِما تحدّده إعدادات الحسابات --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sales-account">حساب المبيعات</label>
                                        <select id="sales-account" name="sales_account" class="form-control select2">
                                            <option value="">من «الحسابات المحاسبية» في الإعدادات</option>
                                            @foreach ($salesAccounts ?? [] as $postingAccount)
                                                <option value="{{ $postingAccount->id }}"
                                                    @selected(old('sales_account', null) == $postingAccount->id)>
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
                                    <div class="form-group">
                                        <label for="sales-cost-account">حساب تكلفة المبيعات</label>
                                        <select id="sales-cost-account" name="sales_cost_account" class="form-control select2">
                                            <option value="">من «الحسابات المحاسبية» في الإعدادات</option>
                                            @foreach ($costAccounts ?? [] as $postingAccount)
                                                <option value="{{ $postingAccount->id }}"
                                                    @selected(old('sales_cost_account', null) == $postingAccount->id)>
                                                    {{ $postingAccount->code }} — {{ $postingAccount->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="stk-help">
                                            تجاوزٌ اختياري لهذا المنتج — يُترك فارغاً ليتبع الإعدادات.
                                        </small>
                                    </div>
                                </div>

                                @isset($price_lists)
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label for="price-list-select">قائمة الأسعار</label>
                                            <select id="price-list-select" name="price_list_id" class="form-control">
                                                <option value="">بلا قائمة</option>
                                                @foreach ($price_lists as $price_list)
                                                    <option value="{{ $price_list->id }}"
                                                            data-price="{{ $price_list->default_price }}"
                                                            {{ old('price_list_id') == $price_list->id ? 'selected' : '' }}>
                                                        {{ $price_list->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-create-first
                                                :count="count($price_lists ?? [])"
                                                route="price_list.create"
                                                permission="stock.price_lists.create"
                                                :plan="\App\Constants\planFeatures\StockPlanFeatures::PRICE_LISTS"
                                                label="قائمة أسعار"
                                                hint="لا قوائم أسعار مسجَّلة بعد." />
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label for="default-price-input">السعر الافتراضي</label>
                                            <input type="text" id="default-price-input" name="price_list"
                                                   class="form-control stk-num" disabled>
                                        </div>
                                    </div>
                                @endisset
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ إدارة المخزون ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-crosshair"></i> إدارة المخزون</h4>
                </div>
                <div class="card-body">
                    {{--
                        **ما كان**

                        المفتاح بلا `name` — أي لا يُرسَل أصلاً — وحقل «نوع التتبع»
                        `disabled` حتى يُفعَّل. والمتصفّح **لا يرسل الحقول
                        المعطَّلة**، فمن اختار نوعاً ولم يلمس المفتاح حُفظ منتجه
                        بلا نوع تتبّع. ولهذا كل منتجات القاعدة على القيمة صفر.

                        و`inventory_type` يكتبه الكنترولر من الطلب ولا حقل له في
                        النموذج إطلاقاً — فكان `null` في كل منتج منذ اليوم الأول.

                        **ما صار**

                        المفتاح حقلٌ باسمٍ يُرسَل، والحقول تبقى مفعَّلة لكن
                        مخفيّة حين يُطفأ — فتصل قيمها ولا تضيع.
                    --}}
                    <label class="stk-switch">
                        <input type="checkbox" id="ProductTrackStock" name="track_stock" value="1" >
                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                        <span class="stk-switch__label">
                            <span class="stk-switch__title">تتبّع مخزون هذا المنتج</span>
                            <span class="stk-switch__hint">يفعّل نوع التتبع وحدّ التنبيه</span>
                        </span>
                    </label>

                    <div class="row mt-2" id="tracking-fields">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group mb-0">
                                <label for="tracking-select">نوع التتبع</label>
                                <select id="tracking-select" name="track_inventory" class="form-control">
                                    @foreach ([0 => 'الرقم التسلسلي', 1 => 'رقم الشحنة', 2 => 'تاريخ الانتهاء', 3 => 'رقم الشحنة وتاريخ الانتهاء', 4 => 'الكمية فقط'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('track_inventory', null) == $value)>
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
                                        <option value="{{ $value }}" @selected(old('inventory_type', null) == $value)>
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
                                       class="form-control stk-num" value="{{ old('low_stock_alert', 0) }}">
                            </div>
                        </div>

                        {{-- حقلا الصلاحية لا يظهران إلا حين يكون التتبع بتاريخ الانتهاء --}}
                        <div class="col-md-3 col-sm-6" id="expiry-date-field" hidden>
                            <div class="form-group mb-0">
                                <label for="expiry_date">تاريخ الانتهاء</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control"
                                       value="{{ old('expiry_date') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6" id="notify-before-expiry-field" hidden>
                            <div class="form-group mb-0">
                                <label for="notify_before_days">نبّهني قبل الانتهاء (أيام)</label>
                                <input type="number" id="notify_before_days" name="notify_before_days" min="0"
                                       class="form-control stk-num" value="{{ old('notify_before_days') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                                          rows="3">{{ old('Internal_notes') }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label for="tags">وسوم</label>
                                <input type="text" id="tags" name="tags" class="form-control"
                                       value="{{ old('tags') }}" placeholder="افصل بينها بفاصلة">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>نشط</option>
                                    <option value="unactive" {{ old('status') === 'unactive' ? 'selected' : '' }}>غير نشط</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="button" id="save-product-btn-bottom" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ المنتج
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        /*
            لا jQuery ولا Select2 من CDN هنا — القالب يحمّلهما، وإعادة تحميل
            jQuery فوق نفسه كانت تُفقد كل الإضافات المسجَّلة عليه.
        */
        $(function () {
            if ($.fn.select2) {
                $('#products_form .select2').select2({ placeholder: 'اختر…', allowClear: true, dir: 'rtl', width: '100%' });
            }

            const form = $('#products_form');

            /* ══════════ مفاتيح التبديل ══════════ */

            /*
                الحقول تُخفى ولا تُعطَّل.

                كان المفتاح يضع `disabled` على الحقول — والمتصفّح لا يرسل
                الحقول المعطَّلة، فتضيع قيمها عند الحفظ. الإخفاء يُبقيها
                تُرسَل، وقيمة «الكمية فقط» هي ما يعنيه إطفاء المفتاح.
            */
            function syncTrackingFields() {
                var on = document.getElementById('ProductTrackStock').checked;
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

            /* حقول الصلاحية تخصّ نوع التتبع «تاريخ الانتهاء» وحده.
               كان هذا المستمع يُربط بـ addEventListener على كائن jQuery،
               فيرمي استثناءً يُسقط بقيّة السكربت معه. */
            $('#tracking-select').on('change', function () {
                const showExpiry = this.value === '2' || this.value === '3';
                document.getElementById('expiry-date-field').hidden = !showExpiry;
                document.getElementById('notify-before-expiry-field').hidden = !showExpiry;
            }).trigger('change');

            $('#price-list-select').on('change', function () {
                const input = document.getElementById('default-price-input');
                const price = this.options[this.selectedIndex].getAttribute('data-price');
                input.disabled = !this.value;
                input.value = this.value ? (price || '') : '';
            });

            /* معاينة الصورة */
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
                const factor = unit.conversion_factor;
                const symbol = unit.unit_symbol ? ' (' + escapeHtml(unit.unit_symbol) + ')' : '';

                return ''
                    + '<div class="stk-unit-card" data-is-base="' + (isBase ? '1' : '0') + '"'
                    + '     data-factor="' + escapeHtml(factor) + '">'
                    + '  <div class="stk-unit-card__head">'
                    + '    <span class="stk-unit-card__name">' + escapeHtml(unit.unit_name) + symbol + '</span>'
                    + (isBase ? '<span class="badge badge-light-success">أساسية</span>' : '')
                    + '    <span class="stk-unit-card__factor stk-num">معامل ' + escapeHtml(factor) + '</span>'
                    + '  </div>'
                    + '  <input type="hidden" name="units[' + index + '][category_unit_id]" value="' + escapeHtml(unit.id) + '">'
                    + '  <input type="hidden" name="units[' + index + '][is_base_unit]" value="' + (isBase ? '1' : '0') + '">'
                    + '  <div class="row">'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>الباركود</label>'
                    + '      <input type="text" class="form-control stk-num unit-barcode" data-is-base="' + (isBase ? '1' : '0') + '"'
                    + '             name="units[' + index + '][barcode]"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>سعر الشراء</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num unit-purchase-price" data-is-base="' + (isBase ? '1' : '0') + '"'
                    + '             name="units[' + index + '][purchase_price]" placeholder="0.00"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>سعر البيع</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num unit-sale-price" data-is-base="' + (isBase ? '1' : '0') + '"'
                    + '             name="units[' + index + '][sale_price]" placeholder="0.00"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-1"><label>أدنى سعر بيع</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num unit-min-sale-price" data-is-base="' + (isBase ? '1' : '0') + '"'
                    + '             name="units[' + index + '][min_sale_price]" placeholder="0.00"></div></div>'
                    + '    <div class="col-6"><div class="form-group mb-0"><label>سعر الجملة</label>'
                    + '      <input type="number" step="0.01" class="form-control stk-num unit-wholesale-price" data-is-base="' + (isBase ? '1' : '0') + '"'
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
                const card = document.getElementById('product-units-card');
                const container = $('#product-units-container');
                const empty = document.getElementById('no-units-message');

                if (!categoryId) {
                    card.hidden = true;
                    container.empty();
                    return;
                }

                card.hidden = false;
                container.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>');

                $.getJSON('{{ route('products.getCategoryUnits') }}', { category_id: categoryId })
                    .done(function (response) {
                        container.empty();

                        const units = (response && response.success && response.units) ? response.units : [];
                        empty.hidden = units.length > 0;

                        // الأسعار تُقرأ من الوحدة الأساسية حين تُعرَّف وحدات
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

            /* حقول الوحدة الأساسية تغذّي حقول التسعير العامّة */
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

            $('#category-input').on('change', function () { loadCategoryUnits(this.value); });
            if ($('#category-input').val()) loadCategoryUnits($('#category-input').val());

            /* ══════════ الحفظ ══════════ */

            function save() {
                const name = $('#product-name').val().trim();
                if (!name) {
                    Swal.fire({ icon: 'error', title: 'بيانات ناقصة', text: 'أدخل اسم المنتج' });
                    $('#product-name').addClass('is-invalid').focus();
                    return;
                }

                Swal.fire({
                    title: 'حفظ المنتج؟',
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
                    type: 'POST',
                    data: new FormData(form[0]),
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .done(function (response) {
                        if (!response || !response.success) {
                            Swal.fire({ icon: 'error', title: 'تعذّر الحفظ', text: (response && response.message) || '' });
                            return;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحفظ',
                            text: response.message,
                            showCancelButton: true,
                            confirmButtonText: 'عرض المنتجات',
                            cancelButtonText: 'إضافة منتج آخر',
                        }).then(result => {
                            if (result.isConfirmed) {
                                window.location.href = response.redirect_url;
                                return;
                            }
                            form[0].reset();
                            if ($.fn.select2) $('#products_form .select2').val('').trigger('change');
                            $('#featured_product').prop('disabled', true);
                            $('.ProductTrackingInput').prop('disabled', true);
                            $('#ProductTrackStock').prop('checked', false);
                            $('#product-units-card').prop('hidden', true);
                            window.scrollTo(0, 0);
                        });
                    })
                    .fail(function (xhr) {
                        let message = 'حدث خطأ أثناء الحفظ. حاول مرة أخرى.';
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors) {
                                message = Object.values(xhr.responseJSON.errors).flat().join('\n');
                            } else if (xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }
                        }
                        Swal.fire({ icon: 'error', title: 'تعذّر الحفظ', text: message });
                    });
            }

            $('#save-product-btn, #save-product-btn-bottom').on('click', save);
            form.on('submit', function (e) { e.preventDefault(); save(); });
        });
    </script>

    @include('stock::products.partials.unit-price-sync')
@endsection
