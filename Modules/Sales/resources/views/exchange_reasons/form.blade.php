@extends('sales::master')

{{-- نموذج سبب الاستبدال — إضافةً وتعديلاً في قالب واحد.

     قالبان متطابقان تقريباً يتباعدان مع أول تعديل، فوُحِّدا. وكانت أنماطه
     مستعارة من وحدة الإعدادات (`sitting::components._settings_styles`
     والبادئة `st-`) بألوانٍ مثبَّتة (`#2b6cb0`) لا تتبع لون المظهر — الآن
     `sl-*` وألوانها من `--app-*`. --}}

@php
    $editing = $reason->exists;
    $cur = fn (string $field, $fallback = null) => old($field, $reason->{$field} ?? $fallback);
    $title = $editing
        ? __('sales_settings.exchange_reasons.edit_title')
        : __('sales_settings.exchange_reasons.create_title');
@endphp

@section('title', $title)

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('exchange-reasons.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => $title,
            'icon' => 'repeat',
            'sub' => __('sales_settings.exchange_reasons.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.exchange_reasons.title') => route('exchange-reasons.index'),
                ($editing ? $reason->name : __('sales_settings.ui.add')) => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'open' => false,
            'key' => $editing ? 'sales-exchange-reasons.edit' : 'sales-exchange-reasons.create',
            'title' => __('sales_settings.guide.exchange_reasons_form.title'),
            'lead' => __('sales_settings.guide.exchange_reasons_form.lead'),
            'blocks' => [
                __('sales_settings.guide.exchange_reasons_form.fields.title') => [
                    __('sales_settings.guide.exchange_reasons_form.fields.a'),
                    __('sales_settings.guide.exchange_reasons_form.fields.b'),
                    __('sales_settings.guide.exchange_reasons_form.fields.c'),
                    __('sales_settings.guide.exchange_reasons_form.fields.d'),
                ],
                __('sales_settings.guide.exchange_reasons_form.effects.title') => [
                    __('sales_settings.guide.exchange_reasons_form.effects.a'),
                    __('sales_settings.guide.exchange_reasons_form.effects.b'),
                    __('sales_settings.guide.exchange_reasons_form.effects.c'),
                ],
            ],
        ])

        <form method="POST"
              action="{{ $editing ? route('exchange-reasons.update', $reason->id) : route('exchange-reasons.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="row">
                <div class="col-lg-7 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-repeat"></i>
                                {{ __('sales_settings.exchange_reasons.details') }}
                            </h4>
                        </div>

                        <div class="card-body">
                            <div class="form-group">
                                <label for="name" class="sl-req">{{ __('sales_settings.ui.name') }}</label>

                                <input type="text" id="name" name="name" maxlength="255" required
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ $cur('name') }}"
                                       placeholder="{{ __('sales_settings.exchange_reasons.name_placeholder') }}">

                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @else
                                    <span class="sl-help">{{ __('sales_settings.exchange_reasons.name_hint') }}</span>
                                @enderror
                            </div>

                            <div class="form-group mb-0">
                                <label for="description">
                                    {{ __('sales_settings.ui.description') }}
                                    <span class="sl-help d-inline">({{ __('sales_settings.ui.optional') }})</span>
                                </label>

                                <textarea id="description" name="description" rows="3" maxlength="500"
                                          class="form-control @error('description') is-invalid @enderror"
                                          placeholder="{{ __('sales_settings.exchange_reasons.description_placeholder') }}">{{ $cur('description') }}</textarea>

                                @error('description')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-sliders"></i> {{ __('sales_settings.ui.options') }}
                            </h4>
                        </div>

                        <div class="card-body">
                            <div class="form-group">
                                <label for="sort_order">{{ __('sales_settings.exchange_reasons.sort_order') }}</label>

                                <input type="number" id="sort_order" name="sort_order" min="0"
                                       class="form-control @error('sort_order') is-invalid @enderror"
                                       value="{{ $cur('sort_order') }}"
                                       placeholder="{{ __('sales_settings.exchange_reasons.order_auto') }}">

                                @error('sort_order')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @else
                                    <span class="sl-help">{{ __('sales_settings.exchange_reasons.sort_hint') }}</span>
                                @enderror
                            </div>

                            <div class="sl-switch-row" style="border-top:1px solid var(--sl-line-soft);border-bottom:0">
                                @include('sales::components.switch', [
                                    'name' => 'is_active',
                                    'checked' => (bool) $cur('is_active', true),
                                    'title' => __('sales_settings.ui.active'),
                                    'hint' => __('sales_settings.exchange_reasons.is_active_hint'),
                                ])
                            </div>
                        </div>

                        <div class="sl-form-foot">
                            <a href="{{ route('exchange-reasons.index') }}" class="btn btn-outline-secondary">
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
