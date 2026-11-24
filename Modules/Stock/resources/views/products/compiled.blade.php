@extends('stock::master')

@section('title', 'منتج تجميعي جديد')

@section('content')
    @php
        /* الكنترولر لا يمرّر $TemplateUnit — الحقل يُخفى بدل أن يرمي
           «متغيّر غير معرَّف» حين يكون نظام الوحدات المتعدّدة مفعَّلاً. */
        $templateUnits = collect($TemplateUnit ?? []);
        $multiUnits = $role && $templateUnits->isNotEmpty();
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'منتج تجميعي جديد',
            'icon' => 'layers',
            'sub' => 'منتج يُركَّب من منتجات أخرى بكمّيّات محدّدة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                'منتج تجميعي' => null,
            ],
            'actions' => '<a href="' . route('products.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="products_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ المنتج</button>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form id="products_form" action="{{ route('products.compiled_store') }}"
              method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="compiled">

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
                                        <label for="name" class="stk-req">الاسم</label>
                                        <input type="text" id="name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name') }}">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="serial_number">الرقم التسلسلي</label>
                                        {{-- حقلٌ تلقائي: الخادم يُصدره ويتجاهل ما يصل من النموذج، فالحقل هنا للعرض فقط --}}
                                        <input type="text" id="serial_number" name="serial_number" readonly tabindex="-1"
                                               class="form-control text-center stk-num stk-locked"
                                               value="{{ $serial_number }}">
                                        <span class="stk-help stk-help--locked">
                                            <i class="feather icon-lock"></i>
                                            يُصدره النظام عند الحفظ من إعدادات الترقيم
                                        </span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category_id" class="stk-req">التصنيف</label>
                                        <select id="category_id" name="category_id" required
                                                class="form-control select2 @error('category_id') is-invalid @enderror">
                                            <option value="">اختر التصنيف…</option>
                                            @foreach ($categories ?? [] as $category)
                                                <option value="{{ $category->id }}"
                                                    {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('category_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="barcode">الباركود</label>
                                        <input type="text" id="barcode" name="barcode" class="form-control stk-num"
                                               value="{{ old('barcode') }}" placeholder="يُولَّد تلقائياً إن تُرك فارغاً">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier_id">المورّد</label>
                                        <input type="number" id="supplier_id" name="supplier_id"
                                               class="form-control stk-num" value="{{ old('supplier_id') }}">
                                    </div>
                                </div>

                                @if ($multiUnits)
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="template-unit">قالب الوحدة</label>
                                            <select id="template-unit" name="sub_unit_id" class="form-control">
                                                @foreach ($templateUnits as $key => $template)
                                                    <option value="{{ $template->id }}" {{ $key === 0 ? 'selected' : '' }}>
                                                        {{ $template->template ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif

                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="description">الوصف</label>
                                        <textarea id="description" name="description" class="form-control"
                                                  rows="3">{{ old('description') }}</textarea>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="images">الصورة</label>
                                        <label for="images" class="stk-upload">
                                            <img src="" alt="" class="stk-upload__preview d-none" id="images_preview">
                                            <span class="stk-upload__placeholder" id="images_placeholder">
                                                <i class="feather icon-upload-cloud"></i>
                                                <span class="stk-upload__title">اسحب الصورة أو اضغط للاختيار</span>
                                                <span class="stk-upload__hint">PNG أو JPG</span>
                                            </span>
                                        </label>
                                        <input type="file" id="images" name="images" accept="image/*" class="d-none">
                                    </div>
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

                            <label class="stk-switch mt-2">
                                <input type="checkbox" name="featured_product" id="featured_product" value="1"
                                       {{ old('featured_product') ? 'checked' : '' }}
                                       {{ old('available_online') ? '' : 'disabled' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">منتج مميّز</span>
                                    <span class="stk-switch__hint">يتطلّب الإتاحة أونلاين</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- ══════════ التسعير ══════════ --}}
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-dollar-sign"></i> التسعير</h4>
                            <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purchase-price">سعر الشراء</label>
                                        @if ($multiUnits)
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" id="purchase-price"
                                                       name="purchase_price" class="form-control stk-num"
                                                       value="{{ old('purchase_price') }}">
                                                <select id="purchase-type" name="purchase_unit_id"
                                                        class="form-control" style="max-width:45%"></select>
                                            </div>
                                        @else
                                            <input type="number" step="0.01" min="0" id="purchase-price"
                                                   name="purchase_price" class="form-control stk-num"
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
                                                       name="sale_price" class="form-control stk-num"
                                                       value="{{ old('sale_price') }}">
                                                <select id="sale-type" name="sales_unit_id"
                                                        class="form-control" style="max-width:45%"></select>
                                            </div>
                                        @else
                                            <input type="number" step="0.01" min="0" id="sale-price"
                                                   name="sale_price" class="form-control stk-num"
                                                   value="{{ old('sale_price') }}">
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tax1">الضريبة الأولى</label>
                                        <select id="tax1" name="tax1" class="form-control">
                                            <option value="">بلا ضريبة</option>
                                            @foreach ([1 => 'القيمة المضافة', 2 => 'صفرية', 3 => 'قيمة مضافة'] as $value => $label)
                                                <option value="{{ $value }}" {{ old('tax1') == $value ? 'selected' : '' }}>
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
                                            @foreach ([1 => 'القيمة المضافة', 2 => 'صفرية', 3 => 'قيمة مضافة'] as $value => $label)
                                                <option value="{{ $value }}" {{ old('tax2') == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="min_sale_price">أدنى سعر بيع</label>
                                        <input type="number" step="0.01" min="0" id="min_sale_price"
                                               name="min_sale_price" class="form-control stk-num"
                                               value="{{ old('min_sale_price') }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="profit_margin">هامش الربح %</label>
                                        <input type="number" step="0.01" id="profit_margin" name="profit_margin"
                                               class="form-control stk-num" value="{{ old('profit_margin') }}">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="form-group mb-0">
                                        <label for="discount">الخصم</label>
                                        <input type="number" step="0.01" min="0" id="discount" name="discount"
                                               class="form-control stk-num" value="{{ old('discount') }}">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label for="discount_type">نوعه</label>
                                        <select id="discount_type" name="discount_type" class="form-control">
                                            <option value="1" {{ old('discount_type') == 1 ? 'selected' : '' }}>نسبة %</option>
                                            <option value="2" {{ old('discount_type') == 2 ? 'selected' : '' }}>مبلغ ثابت</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ══════════ تتبع المخزون ══════════ --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-crosshair"></i> تتبع المخزون</h4>
                        </div>
                        <div class="card-body">
                            <label class="stk-switch">
                                <input type="checkbox" id="ProductTrackStock">
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">تتبّع مخزون هذا المنتج</span>
                                    <span class="stk-switch__hint">يفعّل نوع التتبع وحدّ التنبيه</span>
                                </span>
                            </label>

                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="track_inventory">نوع التتبع</label>
                                        <select id="track_inventory" name="track_inventory" disabled
                                                class="form-control ProductTrackingInput">
                                            @foreach ([0 => 'الرقم التسلسلي', 1 => 'رقم الشحنة', 2 => 'تاريخ الانتهاء', 3 => 'رقم الشحنة وتاريخ الانتهاء', 4 => 'الكمية فقط'] as $value => $label)
                                                <option value="{{ $value }}" {{ old('track_inventory', 4) == $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="low_stock_alert">نبّهني عند النزول تحت</label>
                                        <input type="number" id="low_stock_alert" name="low_stock_alert" disabled
                                               class="form-control stk-num ProductTrackingInput"
                                               value="{{ old('low_stock_alert', 0) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ مكوّنات التجميعة ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-layers"></i> مكوّنات التجميعة</h4>
                    <button type="button" id="add-product" class="btn btn-sm btn-primary">
                        <i class="feather icon-plus"></i> إضافة مكوّن
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="storehouse_id" class="stk-req">المخزن</label>
                                <select id="storehouse_id" name="storehouse_id" class="form-control select2" required>
                                    <option value="">اختر مخزناً…</option>
                                    @foreach ($storehouses as $storehouse)
                                        <option value="{{ $storehouse->id }}"
                                            {{ old('storehouse_id') == $storehouse->id ? 'selected' : '' }}>
                                            {{ $storehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="compile_type">نوع التجميعة</label>
                                <select id="compile_type" name="compile_type" class="form-control">
                                    <option value="Instant" {{ old('compile_type') === 'Instant' ? 'selected' : '' }}>فوري</option>
                                    <option value="Pre-made" {{ old('compile_type') === 'Pre-made' ? 'selected' : '' }}>معدّ مسبقاً</option>
                                </select>
                                <span class="stk-help">الفوري يُركَّب عند البيع، والمعدّ مسبقاً يُنتَج ويُخزَّن</span>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="quantity" class="stk-req">الكمية الإجمالية</label>
                                <input type="number" id="quantity" name="quantity" min="0" required
                                       class="form-control stk-num" value="{{ old('quantity', 0) }}">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" id="products-table" hidden>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:60%">المكوّن</th>
                                    <th style="width:30%">الكمية</th>
                                    <th class="text-center" style="width:10%">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="product-fields"></tbody>
                        </table>
                    </div>

                    <div class="stk-empty" id="products-empty">
                        <div class="stk-empty__icon"><i class="feather icon-layers"></i></div>
                        <h5 class="stk-empty__title">لا مكوّنات بعد</h5>
                        <p class="stk-empty__hint">أضف المنتجات التي يتركّب منها هذا المنتج التجميعي</p>
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
                                <label for="Internal_notes">ملاحظات داخلية</label>
                                <textarea id="Internal_notes" name="Internal_notes" class="form-control"
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
                                    {{-- `stopped` و`not_active` ليستا في enum العمود — تُرفضان عند الحفظ --}}
                                    @foreach (\App\Enums\ProductStatus::toArray() as $value => $label)
                                        <option value="{{ $value }}" {{ old('status') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ المنتج
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    {{--
        لا jQuery ولا Bootstrap ولا Select2 من CDN هنا: القالب يحمّلها جميعاً،
        وإعادة تحميل jQuery وبوتستراب 4 فوقهما كانت تكسر الأنماط والإضافات معاً.
    --}}
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#products_form .select2').select2({ placeholder: 'اختر…', allowClear: true, dir: 'rtl', width: '100%' });
            }

            /* تتبّع المخزون: الحقول معطَّلة حتى يُفعَّل التتبّع */
            const trackToggle = document.getElementById('ProductTrackStock');
            trackToggle.addEventListener('change', function () {
                document.querySelectorAll('.ProductTrackingInput').forEach(el => {
                    el.disabled = !trackToggle.checked;
                });
            });

            /* «مميّز» تابعٌ لـ«متاح أونلاين» */
            const online = document.getElementById('available_online');
            const featured = document.getElementById('featured_product');
            online.addEventListener('change', function () {
                featured.disabled = !this.checked;
                if (!this.checked) featured.checked = false;
            });

            /* معاينة الصورة */
            document.getElementById('images').addEventListener('change', function () {
                const preview = document.getElementById('images_preview');
                const placeholder = document.getElementById('images_placeholder');
                const picked = this.files && this.files[0];
                if (picked) preview.src = URL.createObjectURL(picked);
                preview.classList.toggle('d-none', !picked);
                placeholder.classList.toggle('d-none', !!picked);
            });

            /* ══════════ مكوّنات التجميعة ══════════ */
            const componentOptions = @json(
                collect($products)->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()
            );

            let rowIndex = 0;

            function syncEmptyState() {
                const any = $('#product-fields tr').length > 0;
                document.getElementById('products-table').hidden = !any;
                document.getElementById('products-empty').hidden = any;
            }

            $('#add-product').on('click', function () {
                const options = componentOptions
                    .map(p => '<option value="' + p.id + '">' + $('<div>').text(p.name).html() + '</option>')
                    .join('');

                const row = $(
                    '<tr class="product-row">'
                    + '<td><select class="form-control product-select" name="products[' + rowIndex + '][product_id]">'
                    + options + '</select></td>'
                    + '<td><input type="number" min="1" value="1" class="form-control stk-num" '
                    + 'name="products[' + rowIndex + '][quantity]"></td>'
                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-product" '
                    + 'aria-label="حذف المكوّن"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                $('#product-fields').append(row);
                if ($.fn.select2) {
                    row.find('.product-select').select2({ placeholder: 'ابحث عن منتج…', dir: 'rtl', width: '100%' });
                }

                rowIndex++;
                syncEmptyState();
            });

            /* التفويض بدل onclick: الصفوف تُنشأ بعد تحميل الصفحة.
               كانت `removeProductRow` معرَّفةً ثلاث مرّات بأجسامٍ مختلفة. */
            $('#product-fields').on('click', '.remove-product', function () {
                $(this).closest('tr').remove();
                syncEmptyState();
            });

            syncEmptyState();

            @if ($multiUnits)
                /* الوحدات الفرعية تتبع قالب الوحدة المختار */
                function fetchSubUnits(templateUnitId) {
                    const targets = $('#purchase-type, #sale-type');
                    if (!templateUnitId) {
                        targets.empty().append('<option value="">لا توجد وحدات فرعية</option>');
                        return;
                    }

                    $.getJSON('{{ route('products.getSubUnits') }}', { template_unit_id: templateUnitId })
                        .done(function (response) {
                            targets.empty();
                            if (!response || !response.length) {
                                targets.append('<option value="">لا توجد وحدات فرعية</option>');
                                return;
                            }
                            $.each(response, function (i, unit) {
                                targets.append($('<option>').val(unit.id).text(unit.sub_discrimination));
                            });
                            targets.val(response[0].id);
                        })
                        .fail(function () {
                            targets.empty().append('<option value="">تعذّر جلب الوحدات</option>');
                        });
                }

                fetchSubUnits($('#template-unit').val());
                $('#template-unit').on('change', function () { fetchSubUnits(this.value); });
            @endif
        });
    </script>
@endsection
