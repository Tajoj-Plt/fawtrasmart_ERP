@extends('sales::master')

{{-- نموذج عرض الهدية — إضافةً وتعديلاً في قالب واحد (وهو ما كان: المتحكّم
     يعرضه للحالتين).

     كان يمدّد `master` (قالب الجذر) لا `sales::master`، فيخرج بلا شريط
     المبيعات الجانبي. وكان نصّه كلّه عربيّاً مكتوباً في الوسم، ويستعمل
     أصناف Bootstrap 5 (`mb-3`, `text-end`) في قالبٍ على Bootstrap 4 —
     فلا تُطبَّق. و`$giftOffer->excludedClients` تُقرأ فيه بينما `edit()`
     لم تكن تُحمِّلها، فيصير كل استدعاءٍ لها استعلاماً كسولاً في الحلقة.

     الآن: `sl-*`، ومفاتيح `sales_settings`، والعلاقات الثلاث مُحمَّلة من
     المتحكّم. --}}

@php
    /* شاشة الإضافة لا تُمرِّر `$giftOffer`، والمُغلَق أدناه يلتقطه —
       و`use ($giftOffer)` على متغيّرٍ غير معرَّف تحذيرٌ يحوّله مُعالِج
       أخطاء لارافيل إلى `ErrorException`، أي صفحة 500 قبل أن يُعرَض شيء. */
    $giftOffer = $giftOffer ?? null;

    $editing = $giftOffer && $giftOffer->exists;

    $cur = fn (string $field, $fallback = null) => old($field, $editing ? $giftOffer->{$field} : $fallback);

    $picked = function (string $field, string $relation) use ($editing, $giftOffer) {
        $old = old($field);

        if (is_array($old)) {
            return array_map('strval', $old);
        }

        return $editing ? $giftOffer->{$relation}->pluck('id')->map('strval')->all() : [];
    };

    $pickedClients = $picked('clients', 'clients');
    $pickedEmployees = $picked('employees', 'users');
    $pickedExcluded = $picked('excluded_clients', 'excludedClients');

    $allClients = (int) $cur('is_for_all_clients', 1) === 1;
    $allEmployees = (int) $cur('is_for_all_employees', 1) === 1;

    $title = $editing
        ? __('sales_settings.gift_offers.edit_title')
        : __('sales_settings.gift_offers.create_title');
@endphp

@section('title', $title)

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('gift-offers.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $title,
            'icon' => 'gift',
            'sub' => __('sales_settings.gift_offers.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.gift_offers.title') => route('gift-offers.index'),
                ($editing ? ($giftOffer->name ?: '#'.$giftOffer->id) : __('sales_settings.ui.add')) => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════
             مفتاحٌ واحد للإنشاء والتعديل لا يصحّ: من طوى الشرحَ وهو يُنشئ
             قد يحتاجه وهو يعدّل عرضاً كتبه غيرُه --}}
        @include('sales::components.guide', [
            'open' => false,
            'key' => $editing ? 'sales-gift-offers.edit' : 'sales-gift-offers.create',
            'title' => __('sales_settings.guide.gift_offers_form.title'),
            'lead' => __('sales_settings.guide.gift_offers_form.lead'),
            'blocks' => [
                __('sales_settings.guide.gift_offers_form.fields.title') => [
                    __('sales_settings.guide.gift_offers_form.fields.a'),
                    __('sales_settings.guide.gift_offers_form.fields.b'),
                    __('sales_settings.guide.gift_offers_form.fields.c'),
                    __('sales_settings.guide.gift_offers_form.fields.d'),
                    __('sales_settings.guide.gift_offers_form.fields.e'),
                ],
                __('sales_settings.guide.gift_offers_form.effects.title') => [
                    __('sales_settings.guide.gift_offers_form.effects.a'),
                    __('sales_settings.guide.gift_offers_form.effects.b'),
                    __('sales_settings.guide.gift_offers_form.effects.c'),
                ],
            ],
        ])

        <form method="POST"
              action="{{ $editing ? route('gift-offers.update', $giftOffer->id) : route('gift-offers.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            {{-- ══════════ شرط العرض وهديّته ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-gift"></i> {{ __('sales_settings.gift_offers.details') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="name">{{ __('sales_settings.ui.name') }}</label>
                                <input type="text" id="name" name="name" maxlength="255"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ $cur('name') }}"
                                       placeholder="{{ __('sales_settings.gift_offers.name_placeholder') }}">
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @else
                                    <span class="sl-help">{{ __('sales_settings.gift_offers.name_hint') }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="target_product_id">{{ __('sales_settings.gift_offers.buy') }}</label>
                                <select id="target_product_id" name="target_product_id"
                                        class="form-control sl-select2 @error('target_product_id') is-invalid @enderror"
                                        data-placeholder="{{ __('sales_settings.offers.products_placeholder') }}">
                                    <option value="">{{ __('sales_settings.ui.none') }}</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            @selected((string) $cur('target_product_id') === (string) $product->id)>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('target_product_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="min_quantity" class="sl-req">{{ __('sales_settings.gift_offers.min_quantity') }}</label>
                                <input type="number" id="min_quantity" name="min_quantity" min="1" step="1" required
                                       class="form-control @error('min_quantity') is-invalid @enderror"
                                       value="{{ $cur('min_quantity', 1) }}">
                                @error('min_quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="gift_product_id">{{ __('sales_settings.gift_offers.get') }}</label>
                                <select id="gift_product_id" name="gift_product_id"
                                        class="form-control sl-select2 @error('gift_product_id') is-invalid @enderror"
                                        data-placeholder="{{ __('sales_settings.offers.products_placeholder') }}">
                                    <option value="">{{ __('sales_settings.ui.none') }}</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            @selected((string) $cur('gift_product_id') === (string) $product->id)>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('gift_product_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="gift_quantity" class="sl-req">{{ __('sales_settings.gift_offers.gift_quantity') }}</label>
                                <input type="number" id="gift_quantity" name="gift_quantity" min="1" step="1" required
                                       class="form-control @error('gift_quantity') is-invalid @enderror"
                                       value="{{ $cur('gift_quantity', 1) }}">
                                @error('gift_quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="start_date">{{ __('sales_settings.gift_offers.start_date') }}</label>
                                <input type="date" id="start_date" name="start_date"
                                       class="form-control @error('start_date') is-invalid @enderror"
                                       value="{{ $cur('start_date') }}">
                                @error('start_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="end_date">{{ __('sales_settings.gift_offers.end_date') }}</label>
                                <input type="date" id="end_date" name="end_date"
                                       class="form-control @error('end_date') is-invalid @enderror"
                                       value="{{ $cur('end_date') }}">
                                @error('end_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @else
                                    <span class="sl-help">{{ __('sales_settings.gift_offers.period_hint') }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ على مَن يُطبَّق ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="feather icon-users"></i> {{ __('sales_settings.gift_offers.audience') }}
                    </h4>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            {{-- زرّا الاختيار صارا مفتاحاً واحداً: «لكل العملاء»
                                 نعم أو لا سؤالٌ ثنائي، وزرّان دائريّان له
                                 يشغلان ضعف المساحة ويحتاجان قراءةً --}}
                            <div class="sl-switch-row" style="border:0">
                                @include('sales::components.switch', [
                                    'name' => 'is_for_all_clients',
                                    'checked' => $allClients,
                                    'title' => __('sales_settings.gift_offers.all_clients'),
                                    'hint' => __('sales_settings.gift_offers.all_clients_hint'),
                                    'id' => 'isForAllClients',
                                ])
                            </div>

                            <div class="form-group" id="clientsPicker" style="{{ $allClients ? 'display:none' : '' }}">
                                <label for="clients">{{ __('sales_settings.gift_offers.pick_clients') }}</label>
                                <select id="clients" name="clients[]" class="form-control sl-select2" multiple
                                        data-placeholder="{{ __('sales_settings.offers.clients_placeholder') }}">
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}"
                                            @selected(in_array((string) $client->id, $pickedClients, true))>
                                            {{ $client->trade_name ?: trim($client->first_name.' '.$client->last_name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-12">
                            <div class="sl-switch-row" style="border:0">
                                @include('sales::components.switch', [
                                    'name' => 'is_for_all_employees',
                                    'checked' => $allEmployees,
                                    'title' => __('sales_settings.gift_offers.all_employees'),
                                    'hint' => __('sales_settings.gift_offers.all_employees_hint'),
                                    'id' => 'isForAllEmployees',
                                ])
                            </div>

                            <div class="form-group" id="employeesPicker" style="{{ $allEmployees ? 'display:none' : '' }}">
                                <label for="employees">{{ __('sales_settings.gift_offers.pick_employees') }}</label>
                                <select id="employees" name="employees[]" class="form-control sl-select2" multiple
                                        data-placeholder="{{ __('sales_settings.gift_offers.employees_placeholder') }}">
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}"
                                            @selected(in_array((string) $user->id, $pickedEmployees, true))>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="excluded_clients">{{ __('sales_settings.gift_offers.excluded_clients') }}</label>
                                <select id="excluded_clients" name="excluded_clients[]" class="form-control sl-select2" multiple
                                        data-placeholder="{{ __('sales_settings.offers.clients_placeholder') }}">
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}"
                                            @selected(in_array((string) $client->id, $pickedExcluded, true))>
                                            {{ $client->trade_name ?: trim($client->first_name.' '.$client->last_name) }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="sl-help">{{ __('sales_settings.gift_offers.excluded_clients_hint') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sl-form-foot">
                    <a href="{{ route('gift-offers.index') }}" class="btn btn-outline-secondary">
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
            // Select2 من أصول التطبيق: `sales::master` يحمّلها قبل هذا القسم
            if ($.fn.select2) {
                $('.sl-select2').each(function () {
                    $(this).select2({
                        width: '100%',
                        placeholder: $(this).data('placeholder'),
                        dir: @json(($languageIsRtl ?? true) ? 'rtl' : 'ltr'),
                        allowClear: true
                    });
                });
            }

            /**
             * المنتقى يظهر حين يُطفأ «لكل …». ولا تُمحى القائمة عند الإخفاء:
             * كان الإخفاء يمسحها (`val(null)`), فمن أطفأ المفتاح ثم أعاده
             * سهواً فقد اختياره كلَّه. المتحكّم يتجاهل القائمة تحت العَلَم
             * العامّ على كل حال.
             */
            function bindPicker(switchId, pickerId) {
                var input = document.getElementById(switchId);
                var picker = document.getElementById(pickerId);
                if (!input || !picker) return;

                input.addEventListener('change', function () {
                    picker.style.display = input.checked ? 'none' : '';
                });
            }

            bindPicker('isForAllClients', 'clientsPicker');
            bindPicker('isForAllEmployees', 'employeesPicker');
        });
    </script>
@endsection
