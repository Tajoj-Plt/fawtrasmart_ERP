@extends('stock::master')

@php
    /*
        الشاشة تخدم كل نوعٍ بلا رصيد — الخدمة والمنتج الرقمي — فحقولهما
        واحدة: بلا مستودع ولا وزن ولا أبعاد ولا حدّ إعادة طلب. كانت
        العناوين تقول «خدمة» نصّاً، فلو فُتحت لنوعٍ آخر كذبت على المستخدم.
    */
    $type = $productType ?? \App\Enums\ProductType::SERVICES;
    $noun = $type === \App\Enums\ProductType::SERVICES ? 'الخدمة' : 'المنتج';
@endphp

@section('title', 'إضافة ' . $type->label())

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إضافة ' . $type->label(),
            'icon' => 'briefcase',
            'sub' => 'الخدمات لا تُخزَّن ولا تُتتبَّع كمّيّاً — تُسعَّر وتُباع فقط',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                $type->label() => null,
            ],
            'actions' => '<a href="' . route('products.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="products_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ ' . $noun . '</button>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <form id="products_form" action="{{ route('products.store_services') }}"
              method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" value="{{ $type->value }}">
            {{-- «خدمات الحجز ← خدمة جديدة» يعود بعد الحفظ إلى ملفّ حجزها --}}
            @if (request('return_to') === 'reservations' || old('return_to') === 'reservations')
                <input type="hidden" name="return_to" value="reservations">
            @endif

            <div class="row">
                {{-- ══════════ البيانات الأساسية ══════════ --}}
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-{{ $type->icon() }}"></i> بيانات {{ $type->label() }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="name" class="stk-req">الاسم</label>
                                        <input type="text" id="name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name') }}" placeholder="مثال: صيانة دورية">
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
                                        {{-- اختياريّ للخدمات والرقميّات: التصنيفات مبنيّة
                                             للأصناف المخزنية ولا تنطبق بالضرورة هنا --}}
                                        <label for="category_id">التصنيف <span class="text-muted">(اختياري)</span></label>
                                        <select id="category_id" name="category_id"
                                                class="form-control select2 @error('category_id') is-invalid @enderror">
                                            <option value="">بدون تصنيف</option>
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
                                        {{--
                                            كان `<input type="number">` — أي على المستخدم أن
                                            يعرف **رقم** المورّد ويكتبه بيده. وهو العيب نفسه
                                            الذي أُصلح في شاشة المنتج، وبقي هنا.
                                        --}}
                                        <select id="supplier_id" name="supplier_id" class="form-control select2">
                                            <option value="">بلا مورّد</option>
                                            @foreach ($suppliers ?? [] as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                    @selected(old('supplier_id') == $supplier->id)>
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

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status">الحالة</label>
                                        <select id="status" name="status" class="form-control">
                                            {{-- `stopped` ليست في enum العمود — تُرفض عند الحفظ --}}
                                            @foreach (\App\Enums\ProductStatus::toArray() as $value => $label)
                                                <option value="{{ $value }}" {{ old('status') === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
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
                                        <label for="purchase_price">سعر التكلفة</label>
                                        <input type="number" step="0.01" min="0" id="purchase_price"
                                               name="purchase_price" class="form-control stk-num"
                                               value="{{ old('purchase_price') }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sale_price">سعر البيع</label>
                                        <input type="number" step="0.01" min="0" id="sale_price"
                                               name="sale_price" class="form-control stk-num"
                                               value="{{ old('sale_price') }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        {{-- من جدول الضرائب لا من معرّفاتٍ مكتوبة: المعرّف 1 هنا «صفرية» لا «القيمة المضافة» --}}
                                        <label for="tax1">الضريبة الأولى</label>
                                        <select id="tax1" name="tax1" class="form-control">
                                            <option value="">بلا ضريبة</option>
                                            @foreach ($taxOptions ?? [] as $taxOption)
                                                <option value="{{ $taxOption->id }}" @selected(old('tax1') == $taxOption->id)>
                                                    {{ $taxOption->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format((float) $taxOption->tax, 2, '.', ''), '0'), '.') }}%)
                                                </option>
                                            @endforeach
                                        </select>
                                        @if (empty($taxOptions) || ! count($taxOptions))
                                            <small class="stk-help text-danger">
                                                <i class="feather icon-alert-triangle"></i>
                                                لم تُعرَّف ضرائب بعد — عرّفها من «الإعدادات ← الضرائب».
                                            </small>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="tax2">الضريبة الثانية</label>
                                        <select id="tax2" name="tax2" class="form-control">
                                            <option value="">بلا ضريبة</option>
                                            @foreach ($taxOptions ?? [] as $taxOption)
                                                <option value="{{ $taxOption->id }}" @selected(old('tax2') == $taxOption->id)>
                                                    {{ $taxOption->name ?? 'ضريبة' }} ({{ rtrim(rtrim(number_format((float) $taxOption->tax, 2, '.', ''), '0'), '.') }}%)
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
                                        <span class="stk-help">لا يُسمح بالبيع تحته</span>
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
                                    <span class="stk-switch__hint">يظهر في المتجر</span>
                                </span>
                            </label>

                            <label class="stk-switch mt-2">
                                <input type="checkbox" name="featured_product" id="featured_product" value="1"
                                       {{ old('featured_product') ? 'checked' : '' }}
                                       {{ old('available_online') ? '' : 'disabled' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">مميَّز</span>
                                    <span class="stk-switch__hint">تُبرز في واجهة المتجر — تتطلّب الإتاحة أونلاين</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ ملاحظات ووسوم ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-file-text"></i> ملاحظات ووسوم</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="Internal_notes">ملاحظات داخلية</label>
                                <textarea id="Internal_notes" name="Internal_notes" class="form-control"
                                          rows="3">{{ old('Internal_notes') }}</textarea>
                                <span class="stk-help">لا تظهر للعميل</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="tags">وسوم</label>
                                <input type="text" id="tags" name="tags" class="form-control"
                                       value="{{ old('tags') }}" placeholder="افصل بينها بفاصلة">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ الخدمة
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ placeholder: 'اختر…', allowClear: true, dir: 'rtl', width: '100%' });
            }

            /* «مميّزة» لا معنى لها ما لم تكن الخدمة معروضة أونلاين */
            const online = document.getElementById('available_online');
            const featured = document.getElementById('featured_product');
            online.addEventListener('change', function () {
                featured.disabled = !this.checked;
                if (!this.checked) featured.checked = false;
            });

            /* معاينة الصورة: كان الحقل عارياً فلا تعرف ما اخترت */
            const file = document.getElementById('images');
            file.addEventListener('change', function () {
                const preview = document.getElementById('images_preview');
                const placeholder = document.getElementById('images_placeholder');
                const picked = this.files && this.files[0];
                if (picked) preview.src = URL.createObjectURL(picked);
                preview.classList.toggle('d-none', !picked);
                placeholder.classList.toggle('d-none', !!picked);
            });
        });
    </script>
@endsection
