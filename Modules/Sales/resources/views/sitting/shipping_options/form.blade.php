@extends('sales::master')

{{-- نموذج خيار الشحن — إضافةً وتعديلاً، على لغة تصميم «الأذون المخزنية»:
     بطاقات `card` بترويسة `card-title` بأيقونة، وأزرار الحفظ والإلغاء في
     ترويسة الصفحة (`form="shippingForm"` يربط الزرّ الخارج بالنموذج)،
     وقاعُ نموذجٍ `sl-form-foot` يكرّرهما لمن بلغ آخر الصفحة فلا يعود
     إلى أعلاها.

     كان القالبان — الإضافة والتعديل — نسختين متباعدتين: أحدهما زرّ إلغائه
     `href=""` يعيد تحميل الصفحة، وكلاهما بلا `old()` على أكثر الحقول فيضيع
     ما كُتب عند أي خطأ تحقّق، وحقل الحالة يقرأ من `request()` لا من القيمة
     المحفوظة فيعود فارغاً في التعديل. وحّدتهما جلسةٌ سابقة، وهذه تنقل
     تصميمهما. --}}

@php
    $editing = $shippingOption->exists;
    $cur = fn (string $field, $fallback = null) => old($field, $shippingOption->{$field} ?? $fallback);
@endphp

@section('title', $editing ? __('sales_settings.shipping.edit_title') : __('sales_settings.shipping.create_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('shippingOptions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> {{ __('sales_settings.ui.cancel') }}
            </a>

            <button type="submit" form="shippingForm" class="btn btn-primary">
                <i class="feather icon-check"></i> {{ __('sales_settings.ui.save') }}
            </button>
        @endsection

        @include('sales::components.page-head', [
            'title' => $editing ? __('sales_settings.shipping.edit_title') : __('sales_settings.shipping.create_title'),
            'icon' => 'truck',
            'sub' => __('sales_settings.shipping.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.shipping.title') => route('shippingOptions.index'),
                ($editing ? $shippingOption->name : __('sales_settings.ui.add')) => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-shipping.form',
            'open' => false,
            'title' => __('sales_settings.guide.shipping.title'),
            'lead' => __('sales_settings.guide.shipping.lead'),
            'blocks' => [
                __('sales_settings.guide.shipping.fields.title') => [
                    __('sales_settings.guide.shipping.fields.a'),
                    __('sales_settings.guide.shipping.fields.b'),
                    __('sales_settings.guide.shipping.fields.c'),
                ],
                __('sales_settings.guide.shipping.effects.title') => [
                    __('sales_settings.guide.shipping.effects.a'),
                    __('sales_settings.guide.shipping.effects.b'),
                    __('sales_settings.guide.shipping.effects.c'),
                ],
            ],
        ])

        <form id="shippingForm" method="POST"
              action="{{ $editing ? route('shippingOptions.update', $shippingOption->id) : route('shippingOptions.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="row">
                <div class="col-lg-7 col-12">
                    {{-- ══════════ بيانات الخيار ══════════ --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-truck"></i> {{ __('sales_settings.shipping.details') }}
                            </h4>
                        </div>

                        <div class="card-body">
                            <div class="form-group">
                                <label for="name" class="sl-req">{{ __('sales_settings.ui.name') }}</label>
                                <input type="text" id="name" name="name" maxlength="255" required
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ $cur('name') }}">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <small class="sl-help">{{ __('sales_settings.shipping.name_hint') }}</small>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="cost" class="sl-req">{{ __('sales_settings.shipping.cost') }}</label>

                                        {{-- رمز العملة إلى جانب الحقل: المبلغ يُدخَل بعملة
                                             النظام لا بعملة يختارها المستخدم، وقول ذلك عند
                                             الحقل أوضح من قوله في سطر شارح تحته --}}
                                        <div class="input-group">
                                            <input type="number" id="cost" name="cost" step="0.01" min="0" required
                                                   class="form-control @error('cost') is-invalid @enderror"
                                                   value="{{ $cur('cost', 0) }}">
                                            <span class="input-group-text">{{ \App\Support\CurrencySettings::symbol() }}</span>
                                        </div>

                                        @error('cost')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @else
                                            <small class="sl-help">{{ __('sales_settings.shipping.cost_hint') }}</small>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="form-group">
                                        <label for="display_order">{{ __('sales_settings.shipping.display_order') }}</label>
                                        <input type="number" id="display_order" name="display_order" min="0"
                                               class="form-control @error('display_order') is-invalid @enderror"
                                               value="{{ $cur('display_order') }}"
                                               placeholder="{{ __('sales_settings.shipping.order_auto') }}">
                                        @error('display_order')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @else
                                            <small class="sl-help">{{ __('sales_settings.shipping.display_order_hint') }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label for="description">{{ __('sales_settings.ui.description') }}</label>
                                <textarea id="description" name="description" rows="3" maxlength="1000"
                                          class="form-control @error('description') is-invalid @enderror">{{ $cur('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- ══════════ الربط المحاسبي ══════════ --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-git-branch"></i> {{ __('sales_settings.shipping.accounting') }}
                            </h4>
                            <span class="sl-count">{{ __('sales_settings.ui.none') }}</span>
                        </div>

                        <div class="card-body">
                            <div class="form-group mb-0">
                                <label for="default_account_id">{{ __('sales_settings.shipping.default_account') }}</label>

                                {{-- `select2`: شجرة الحسابات تتجاوز ثلاثة آلاف حساب تفصيلي،
                                     وقائمة منسدلة بهذا الطول لا تُتصفَّح بالعين. القالب
                                     يُهيّئ كل `.select2` تلقائياً --}}
                                <select id="default_account_id" name="default_account_id"
                                        class="form-control select2 @error('default_account_id') is-invalid @enderror">
                                    <option value="">{{ __('sales_settings.shipping.no_account') }}</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}"
                                            @selected((string) $cur('default_account_id') === (string) $account->id)>
                                            {{ $account->code }} — {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('default_account_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @else
                                    <small class="sl-help">{{ __('sales_settings.shipping.account_hint') }}</small>
                                @enderror

                                {{-- الحساب البديل يُقرأ من شاشة «الحسابات المحاسبية».
                                     كان النصّ يعِد بـ«حساب الإيرادات العام» ولا يشير
                                     إلى حسابٍ بعينه، فلا يعرف المحاسب أين ذهب المبلغ --}}
                                <div class="mt-1">
                                    @if (! empty($fallbackAccount))
                                        <span class="sl-state sl-state--neutral">
                                            {{ __('sales_settings.shipping.fallback_account') }}:
                                            {{ $fallbackAccount->code }} — {{ $fallbackAccount->name }}
                                        </span>
                                    @else
                                        <span class="sl-state sl-state--off">
                                            {{ __('sales_settings.shipping.fallback_account_none') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-12">
                    {{-- ══════════ الضريبة والحالة ══════════ --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-sliders"></i> {{ __('sales_settings.shipping.tax_and_status') }}
                            </h4>
                        </div>

                        <div class="card-body">
                            <div class="form-group">
                                <label class="sl-req">{{ __('sales_settings.shipping.tax_type') }}</label>

                                {{-- رمز معاملة لا مبلغ: القيم 1/2 كما يوثّقها الترحيل --}}
                                @foreach (__('sales_settings.shipping.tax_types') as $code => $label)
                                    <div class="custom-control custom-radio {{ $loop->last ? '' : 'mb-1' }}">
                                        <input type="radio" class="custom-control-input" id="tax-{{ $code }}"
                                               name="tax" value="{{ $code }}"
                                               @checked((int) $cur('tax', \App\Models\ShippingOption::TAX_VAT) === (int) $code)>
                                        <label class="custom-control-label" for="tax-{{ $code }}">{{ $label }}</label>
                                    </div>
                                @endforeach

                                @error('tax')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @else
                                    <small class="sl-help">{{ __('sales_settings.shipping.tax_hint') }}</small>
                                @enderror
                            </div>

                            <div class="sl-switch-row" style="border-top:1px solid var(--sl-line-soft);padding-top:1rem">
                                {{-- الحقل المخفي يسبق المربّع: مربّع غير مؤشَّر لا يُرسَل،
                                     فبلا الحقل يصير التعطيل «لم يُذكَر» ويسقط في `required`.
                                     `sales::components.switch` يفترض 0/1، وهذا الحقل 1/2،
                                     فهو مكتوبٌ هنا بيده --}}
                                <label class="sl-switch" for="status">
                                    <input type="hidden" name="status" value="{{ \App\Models\ShippingOption::STATUS_INACTIVE }}">
                                    <input type="checkbox" id="status" name="status"
                                           value="{{ \App\Models\ShippingOption::STATUS_ACTIVE }}"
                                           @checked((int) $cur('status', \App\Models\ShippingOption::STATUS_ACTIVE) === \App\Models\ShippingOption::STATUS_ACTIVE)>
                                    <span class="sl-switch__track" aria-hidden="true">
                                        <span class="sl-switch__thumb"></span>
                                    </span>
                                    <span class="sl-switch__label">
                                        <span class="sl-switch__title">{{ __('sales_settings.ui.active') }}</span>
                                        <span class="sl-switch__hint">{{ __('sales_settings.shipping.status_hint') }}</span>
                                    </span>
                                </label>
                            </div>

                            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="sl-form-foot">
                            <a href="{{ route('shippingOptions.index') }}" class="btn btn-outline-secondary">
                                {{ __('sales_settings.ui.cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
