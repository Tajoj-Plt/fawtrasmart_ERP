@extends('sales::master')

{{-- نموذج عرض الحسم — إضافةً وتعديلاً في قالب واحد.

     كان قالبان: `create.blade.php` و`edit.blade.php`، متقاربان في البنية
     ومتباعدان في التفاصيل. وفي الأول خطأ يمنع الشاشة من العمل أصلاً:

         <form action="{{ route('gift-offers.store') }}">

     أي أن «إضافة عرض حسم» كانت تُرسِل حقولَها إلى متحكّم *عروض الهدايا*،
     وهو يطلب `min_quantity` و`gift_quantity` و`is_for_all_clients` — فيردّ
     التحقّقُ النموذجَ أبداً. لم تكن هذه الشاشة تحفظ عرضاً قطّ.

     وكان القالب يحمّل jQuery 3.6 وSelect2 من شبكة توزيع في رأسه — خارج
     أيّ `@section`، فتُحقن الوسوم قبل `<!doctype>` — و`sales::master`
     يحمّل الاثنين أصلاً. نسختان من jQuery في الصفحة تُفقِد الأولى
     مُلحقاتِها.

     الآن: قالب واحد، ومسارٌ صحيح، وSelect2 من أصول التطبيق. --}}

@php
    /* شاشة الإضافة لا تُمرِّر `$offer`، وثلاثةُ مُغلَقاتٍ أدناه تلتقطه —
       و`use ($offer)` على متغيّرٍ غير معرَّف تحذيرٌ يحوّله مُعالِج أخطاء
       لارافيل إلى `ErrorException`، أي صفحة 500 قبل أن يُعرَض شيء.
       التعريف هنا يجعل الالتقاط `null` صريحاً، و`$editing` هو الحارس. */
    $offer = $offer ?? null;

    $editing = $offer && $offer->exists;

    $cur = fn (string $field, $fallback = null) => old($field, $editing ? $offer->{$field} : $fallback);

    // المنتقى المتعدّد: `old()` أوّلاً ليبقى اختيار المستخدم بعد خطأ تحقّق،
    // ثم المحفوظ في العلاقة
    $picked = function (string $field, string $relation) use ($editing, $offer) {
        $old = old($field);

        if (is_array($old)) {
            return array_map('strval', $old);
        }

        return $editing ? $offer->{$relation}->pluck('id')->map('strval')->all() : [];
    };

    $pickedClients = $picked('client_id', 'clients');
    $pickedProducts = $picked('product_id', 'products');
    $pickedCategories = $picked('category_id', 'categories');

    $title = $editing
        ? __('sales_settings.offers.edit_title')
        : __('sales_settings.offers.create_title');
@endphp

@section('title', $title)

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('offers.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $title,
            'icon' => 'percent',
            'sub' => __('sales_settings.offers.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.offers.title') => route('offers.index'),
                ($editing ? ($offer->name ?: '#'.$offer->id) : __('sales_settings.ui.add')) => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-offers.form',
            'open' => false,
            'title' => __('sales_settings.guide.offers.title'),
            'lead' => __('sales_settings.guide.offers.lead'),
            'blocks' => [
                __('sales_settings.guide.offers.fields.title') => [
                    __('sales_settings.guide.offers.fields.a'),
                    __('sales_settings.guide.offers.fields.b'),
                    __('sales_settings.guide.offers.fields.c'),
                    __('sales_settings.guide.offers.fields.d'),
                ],
                __('sales_settings.guide.offers.effects.title') => [
                    __('sales_settings.guide.offers.effects.a'),
                    __('sales_settings.guide.offers.effects.b'),
                    __('sales_settings.guide.offers.effects.c'),
                ],
            ],
        ])

        {{-- العملة تخصّ هذه الشاشة: «قيمة الخصم» مبلغٌ حين يكون الخصم
             حقيقياً، ونسبةٌ حين يكون نسبياً --}}
        @include('sales::components.system-context', ['only' => ['currency', 'language']])

        <form method="POST"
              action="{{ $editing ? route('offers.update', $offer->id) : route('offers.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            {{-- ══════════ تفاصيل العرض ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-percent"></i> {{ __('sales_settings.offers.details') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="name" class="sl-req">{{ __('sales_settings.ui.name') }}</label>
                                <input type="text" id="name" name="name" maxlength="255" required
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ $cur('name') }}"
                                       placeholder="{{ __('sales_settings.offers.name_placeholder') }}">
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- التاريخان كانا بلا `value` في شاشة الإضافة، فيضيع
                             ما أدخله المستخدم عند أوّل خطأ تحقّق --}}
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="valid_from">{{ __('sales_settings.offers.valid_from') }}</label>
                                <input type="date" id="valid_from" name="valid_from"
                                       class="form-control @error('valid_from') is-invalid @enderror"
                                       value="{{ $cur('valid_from') instanceof \Carbon\CarbonInterface
                                            ? $cur('valid_from')->toDateString()
                                            : $cur('valid_from') }}">
                                @error('valid_from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="valid_to">{{ __('sales_settings.offers.valid_to') }}</label>
                                <input type="date" id="valid_to" name="valid_to"
                                       class="form-control @error('valid_to') is-invalid @enderror"
                                       value="{{ $cur('valid_to') instanceof \Carbon\CarbonInterface
                                            ? $cur('valid_to')->toDateString()
                                            : $cur('valid_to') }}">
                                @error('valid_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="type" class="sl-req">{{ __('sales_settings.offers.type') }}</label>
                                <select id="type" name="type" class="form-control @error('type') is-invalid @enderror">
                                    @foreach ([1, 2] as $type)
                                        <option value="{{ $type }}" @selected((int) $cur('type', 1) === $type)>
                                            {{ __('sales_settings.offers.types.'.$type) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="quantity" class="sl-req">{{ __('sales_settings.offers.quantity') }}</label>
                                <input type="number" id="quantity" name="quantity" min="1" step="1"
                                       class="form-control @error('quantity') is-invalid @enderror"
                                       value="{{ $cur('quantity', 1) }}">
                                @error('quantity')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @else
                                    <span class="sl-help">{{ __('sales_settings.offers.quantity_hint') }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="discount_type" class="sl-req">{{ __('sales_settings.offers.discount_type') }}</label>
                                <select id="discount_type" name="discount_type"
                                        class="form-control @error('discount_type') is-invalid @enderror">
                                    @foreach ([1, 2] as $type)
                                        <option value="{{ $type }}" @selected((int) $cur('discount_type', 1) === $type)>
                                            {{ __('sales_settings.offers.discount_types.'.$type) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('discount_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="discount_value" class="sl-req">{{ __('sales_settings.offers.discount_value') }}</label>

                                <div class="input-group">
                                    <input type="number" id="discount_value" name="discount_value" min="0" step="0.01"
                                           class="form-control @error('discount_value') is-invalid @enderror"
                                           value="{{ $cur('discount_value') }}">
                                    {{-- الوحدة تتبع نوع الخصم: رمز العملة للحقيقي و`%` للنسبي --}}
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="discountUnit"
                                              data-money="{{ \App\Support\CurrencySettings::symbol() }}">%</span>
                                    </div>
                                </div>

                                @error('discount_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="client_id">{{ __('sales_settings.offers.clients') }}</label>
                                <select id="client_id" name="client_id[]" class="form-control sl-select2" multiple
                                        data-placeholder="{{ __('sales_settings.offers.clients_placeholder') }}">
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}"
                                            @selected(in_array((string) $client->id, $pickedClients, true))>
                                            {{ $client->trade_name ?: $client->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="sl-help">{{ __('sales_settings.offers.clients_hint') }}</span>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12 d-flex align-items-center">
                            <div class="sl-switch-row w-100" style="border:0">
                                @include('sales::components.switch', [
                                    'name' => 'is_active',
                                    'checked' => (bool) $cur('is_active', true),
                                    'title' => __('sales_settings.ui.active'),
                                    'hint' => __('sales_settings.offers.is_active_hint'),
                                ])
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ نطاق العرض ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-package"></i> {{ __('sales_settings.offers.scope') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="unit_type" class="sl-req">{{ __('sales_settings.offers.unit_type') }}</label>
                                <select id="unit_type" name="unit_type"
                                        class="form-control @error('unit_type') is-invalid @enderror">
                                    @foreach ([1, 2, 3] as $unit)
                                        <option value="{{ $unit }}" @selected((int) $cur('unit_type', 1) === $unit)>
                                            {{ __('sales_settings.offers.unit_types.'.$unit) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('unit_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12" data-scope="3">
                            <div class="form-group">
                                <label for="product_id">{{ __('sales_settings.offers.products') }}</label>
                                <select id="product_id" name="product_id[]" class="form-control sl-select2" multiple
                                        data-placeholder="{{ __('sales_settings.offers.products_placeholder') }}">
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            @selected(in_array((string) $product->id, $pickedProducts, true))>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12" data-scope="2">
                            <div class="form-group">
                                <label for="category_id">{{ __('sales_settings.offers.categories') }}</label>
                                <select id="category_id" name="category_id[]" class="form-control sl-select2" multiple
                                        data-placeholder="{{ __('sales_settings.offers.categories_placeholder') }}">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            @selected(in_array((string) $category->id, $pickedCategories, true))>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sl-form-foot">
                    <a href="{{ route('offers.index') }}" class="btn btn-outline-secondary">
                        {{ __('sales_settings.ui.cancel') }}
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            // Select2 من أصول التطبيق: `sales::master` يحمّلها قبل هذا القسم.
            // كان القالب يجلب نسخةً ثانيةً منها ومن jQuery من شبكة توزيع
            if ($.fn.select2) {
                $('.sl-select2').each(function () {
                    $(this).select2({
                        width: '100%',
                        placeholder: $(this).data('placeholder'),
                        dir: @json(($languageIsRtl ?? true) ? 'rtl' : 'ltr'),
                        allowClear: true,
                        closeOnSelect: false
                    });
                });
            }

            // نطاق العرض: «الكل» لا يحتاج منتقىً، و«التصنيف» و«المنتجات»
            // كلٌّ يُظهر منتقاه وحده
            function syncScope() {
                var unit = $('#unit_type').val();

                $('[data-scope]').each(function () {
                    $(this).toggle($(this).data('scope') === Number(unit));
                });
            }

            $('#unit_type').on('change', syncScope);
            syncScope();

            // وحدة قيمة الخصم تتبع نوعه: رمز العملة أو النسبة المئوية
            function syncDiscountUnit() {
                var unit = $('#discountUnit');
                unit.text($('#discount_type').val() === '1' ? unit.data('money') : '%');
            }

            $('#discount_type').on('change', syncDiscountUnit);
            syncDiscountUnit();

            // «خصم على البند» يُطبَّق على وحدة واحدة، فالكمية فيه ثابتة
            function syncQuantity() {
                var isPerItem = $('#type').val() === '1';
                var quantity = $('#quantity');

                if (isPerItem) {
                    quantity.val(1).prop('readonly', true);
                } else {
                    quantity.prop('readonly', false);
                }
            }

            $('#type').on('change', syncQuantity);
            syncQuantity();
        });
    </script>
@endsection
