@extends('sales::master')

{{-- إعدادات الفوترة الإلكترونية للمنشأة.

     بيانات المنشأة تُعرَض ولا تُدخَل: مصدرها شاشة «إعدادات الحساب» التي
     تحفظها في سجلّ المنشأة. كانت هذه الشاشة تطلبها من جديد، فيُدخِلها
     المستخدم مرّتين وتتباعد النسختان — يُصحَّح الرقم الضريبي في مكانه
     الصحيح فتُرسَل الفاتورة الإلكترونية بالرقم القديم.

     والقالب السابق كان مبتوراً: `@section('content')` مكرّرة مرّتين، وثلاثة
     عناصر `div` غير مغلقة، والملفّ ينتهي بـ`@endsection` داخل حقل العنوان،
     ونموذجه `action=""`. --}}

@php
    $value = fn (string $field, $fallback = null) => old($field, $settings->{$field} ?? $fallback);

    // العمود في `clients` ← مفتاح الترجمة
    $fromEstablishment = [
        'trade_name' => 'trade_name',
        'tax_number' => 'tax_number',
        'commercial_registration' => 'commercial_register',
        'country' => 'country',
        'street1' => 'street',
        'city' => 'city',
        'postal_code' => 'postal_code',
    ];
@endphp

@section('title', __('sales_settings.electronic.company'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">{{ __('sales_settings.electronic.company') }}</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('sittingInvoice.index') }}">{{ __('sales_settings.hub.title') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('settings.electronic_invoice') }}">{{ __('sales_settings.electronic.title') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('sales_settings.electronic.company') }}</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-header-right col-md-4 col-12 mb-2 text-right">
            <a href="{{ route('settings.electronic_invoice') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
            </a>
        </div>
    </div>

    <div class="content-body">
        {{-- من أجل `.sl-guide` وحدها؛ كلُّ قواعدها `.sl-`ـيّة فلا تمسّ
             أنماطَ هذه الشاشة --}}
        @include('sales::partials.settings-theme')
        @include('components.hint-tips')

        @include('sales::components.guide', [
            'key' => 'sales-electronic-invoice.company',
            'open' => false,
            'title' => __('sales_settings.guide.electronic_company.title'),
            'lead' => __('sales_settings.guide.electronic_company.lead'),
            'blocks' => [
                [
                    __('sales_settings.guide.electronic_company.a'),
                    __('sales_settings.guide.electronic_company.b'),
                    __('sales_settings.guide.electronic_company.c'),
                ],
            ],
        ])

        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pr-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($missing['establishment'])
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                {{ __('sales_settings.electronic.missing_in_establishment', [
                    'fields' => implode(__('settings.common.list_separator'), $missing['establishment']),
                ]) }}
                @if (Route::has('SittingAccount.index'))
                    <a href="{{ route('SittingAccount.index') }}" class="alert-link">
                        {{ __('sales_settings.electronic.open_account_settings') }}
                    </a>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('electronic_invoice.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="company">

            <div class="row">
                <div class="col-lg-7 col-12">
                    {{-- تُعرَض ولا تُدخَل — مصدرها إعدادات الحساب --}}
                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-briefcase" style="color:#2b6cb0"></i>
                            <h4>{{ __('sales_settings.electronic.establishment_data') }}</h4>
                            <span class="count">{{ __('sales_settings.electronic.read_only') }}</span>
                            <span class="spacer"></span>
                            @if (Route::has('SittingAccount.index'))
                                <a href="{{ route('SittingAccount.index') }}" class="btn btn-sm btn-outline-primary">
                                    <i class="feather icon-edit-2"></i> {{ __('settings.common.edit') }}
                                </a>
                            @endif
                        </div>
                        <div class="body">
                            <p class="text-muted" style="font-size:.85rem">
                                {{ __('sales_settings.electronic.establishment_hint') }}
                            </p>
                        </div>
                        <div class="body flush">
                            <table class="st-table">
                                <tbody>
                                    @foreach ($fromEstablishment as $column => $key)
                                        <tr>
                                            <td style="width:40%;color:#6b7280">
                                                {{ __('sales_settings.electronic.fields.'.$key) }}
                                            </td>
                                            <td>
                                                @if ($settings->fromEstablishment($column))
                                                    {{ $settings->fromEstablishment($column) }}
                                                @else
                                                    <span style="color:#b7bec8">—</span>
                                                    <span class="st-badge warn">{{ __('sales_settings.electronic.missing_short') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td style="color:#6b7280">{{ __('sales_settings.electronic.fields.address') }}</td>
                                        <td>
                                            @if ($settings->establishmentAddress())
                                                {{ $settings->establishmentAddress() }}
                                            @else
                                                <span style="color:#b7bec8">—</span>
                                                <span class="st-badge warn">{{ __('sales_settings.electronic.missing_short') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ما يخصّ الفوترة الإلكترونية وحدها --}}
                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-file-plus" style="color:#2b6cb0"></i>
                            <h4>{{ __('sales_settings.electronic.platform_data') }}</h4>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('sales_settings.electronic.fields.tax_group_name') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="tax_group_name" maxlength="255" required
                                        class="form-control @error('tax_group_name') is-invalid @enderror"
                                        value="{{ $value('tax_group_name') }}">
                                    @error('tax_group_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <small class="text-muted">{{ __('sales_settings.electronic.fields.tax_group_name_hint') }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('sales_settings.electronic.fields.business_category') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="business_category" maxlength="255" required
                                        class="form-control @error('business_category') is-invalid @enderror"
                                        value="{{ $value('business_category') }}">
                                    @error('business_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                {{-- العنوان الوطني: الشارع والمدينة والرمز البريدي من
                                     إعدادات الحساب، وهذان ما لا تحمله تلك الشاشة --}}
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('zatca.fields.building_number') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="building_number" inputmode="numeric" maxlength="4" required
                                        class="form-control @error('building_number') is-invalid @enderror"
                                        style="direction:ltr;text-align:left"
                                        value="{{ $value('building_number') }}">
                                    @error('building_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <small class="text-muted">{{ __('zatca.fields.building_number_hint') }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('zatca.fields.district') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="district" maxlength="255" required
                                        class="form-control @error('district') is-invalid @enderror"
                                        value="{{ $value('district') }}">
                                    @error('district')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12 form-group mb-0">
                                    <label>{{ __('sales_settings.electronic.fields.common_name') }}</label>
                                    <input type="text" name="common_name" maxlength="255"
                                        class="form-control @error('common_name') is-invalid @enderror"
                                        value="{{ $value('common_name') }}"
                                        placeholder="{{ $settings->fromEstablishment('trade_name') }}">
                                    @error('common_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <small class="text-muted">{{ __('sales_settings.electronic.fields.common_name_hint') }}</small>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-12">
                    {{-- كان هنا «عنوان الخدمة» و«معرّف العميل» و«المفتاح السرّي» —
                         نموذجُ ربطٍ لا تعرفه الهيئة. الربط شهادةٌ رقمية تُنشأ
                         برمز OTP من لوحة الفاتورة الإلكترونية، لا بياناتٌ تُكتَب. --}}
                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-file-text" style="color:#2b6cb0"></i>
                            <h4>{{ __('zatca.fields.invoice_types') }}</h4>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <select name="invoice_types" class="form-control @error('invoice_types') is-invalid @enderror">
                                    @foreach (['1100' => 'both', '1000' => 'standard', '0100' => 'simplified'] as $code => $label)
                                        <option value="{{ $code }}" @selected($value('invoice_types', '1100') === $code)>
                                            {{ __('zatca.fields.invoice_types_'.$label) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('invoice_types')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <small class="text-muted">{{ __('zatca.fields.invoice_types_hint') }}</small>
                                @enderror
                            </div>

                            <ul class="pr-2 mb-0" style="font-size:.82rem;line-height:1.8;color:#6b7280">
                                <li><strong>{{ __('zatca.profile.standard') }}:</strong> {{ __('zatca.profile_hint.standard') }}</li>
                                <li><strong>{{ __('zatca.profile.simplified') }}:</strong> {{ __('zatca.profile_hint.simplified') }}</li>
                            </ul>
                        </div>
                    </div>

                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-percent" style="color:#2b6cb0"></i>
                            <h4>{{ __('zatca.fields.zero_tax') }}</h4>
                            <span class="count">{{ __('settings.common.optional') }}</span>
                        </div>
                        <div class="body">
                            <p class="text-muted" style="font-size:.85rem">{{ __('zatca.fields.zero_tax_hint') }}</p>

                            <div class="form-group">
                                <label>{{ __('zatca.fields.zero_tax_category') }}</label>
                                <select name="zero_tax_category" class="form-control @error('zero_tax_category') is-invalid @enderror">
                                    <option value="">{{ __('zatca.fields.zero_tax_none') }}</option>
                                    @foreach (\App\Models\ElectronicInvoiceSetting::ZERO_TAX_CATEGORIES as $category)
                                        <option value="{{ $category }}" @selected($value('zero_tax_category') === $category)>
                                            {{ __('zatca.fields.zero_tax_'.$category) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('zero_tax_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group">
                                <label>{{ __('zatca.fields.zero_tax_reason_code') }}</label>
                                <input type="text" name="zero_tax_reason_code" maxlength="40"
                                    class="form-control st-mono @error('zero_tax_reason_code') is-invalid @enderror"
                                    style="direction:ltr;text-align:left"
                                    value="{{ $value('zero_tax_reason_code') }}">
                                @error('zero_tax_reason_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <small class="text-muted">{{ __('zatca.fields.zero_tax_reason_code_hint') }}</small>
                                @enderror
                            </div>

                            <div class="form-group mb-0">
                                <label>{{ __('zatca.fields.zero_tax_reason') }}</label>
                                <input type="text" name="zero_tax_reason" maxlength="255"
                                    class="form-control @error('zero_tax_reason') is-invalid @enderror"
                                    value="{{ $value('zero_tax_reason') }}">
                                @error('zero_tax_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="foot text-center">
                            @permission('sales.settings.update')
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="feather icon-save"></i> {{ __('settings.common.save_changes') }}
                                </button>
                            @endpermission
                            <a href="{{ route('settings.electronic_invoice') }}" class="btn btn-outline-secondary btn-block mt-1">
                                {{ __('settings.common.cancel') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
