@extends('sales::master')

{{-- سلوك إرسال الفاتورة الإلكترونية.

     كان النموذج `<form action="">` أي يُرسِل إلى الصفحة نفسها بـGET،
     ومربّع الاختيار يقرأ من `$settings` التي لا تصل القالبَ أصلاً. --}}

@section('title', __('sales_settings.electronic.behavior'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="content-header row">
        <div class="content-header-left col-md-8 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">{{ __('sales_settings.electronic.behavior') }}</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('sittingInvoice.index') }}">{{ __('sales_settings.hub.title') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('settings.electronic_invoice') }}">{{ __('sales_settings.electronic.title') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('sales_settings.electronic.behavior') }}</li>
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
            'key' => 'sales-electronic-invoice.behavior',
            'open' => false,
            'title' => __('sales_settings.guide.electronic_behavior.title'),
            'lead' => __('sales_settings.guide.electronic_behavior.lead'),
            'blocks' => [
                [
                    __('sales_settings.guide.electronic_behavior.a'),
                    __('sales_settings.guide.electronic_behavior.b'),
                    __('sales_settings.guide.electronic_behavior.c'),
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

        <form method="POST" action="{{ route('electronic_invoice.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="behavior">

            <div class="row">
                <div class="col-lg-7 col-12">
                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-send" style="color:#2b6cb0"></i>
                            <h4>{{ __('sales_settings.electronic.behavior') }}</h4>
                        </div>
                        <div class="body">
                            @foreach (['auto_send_after_create', 'send_on_payment', 'attach_qr_to_print'] as $flag)
                                <div class="st-toggle-row">
                                    <div class="txt">
                                        <b>{{ __('sales_settings.electronic.fields.'.$flag) }}</b>
                                        <small>{{ __('sales_settings.electronic.fields.'.$flag.'_hint') }}</small>
                                    </div>
                                    {{-- الحقل المخفي يسبق المربّع: غير المؤشَّر لا يُرسَل،
                                         فبلا الحقل يبقى الإعداد مفعَّلاً مهما عُطِّل --}}
                                    <label class="st-switch">
                                        <input type="hidden" name="{{ $flag }}" value="0">
                                        <input type="checkbox" name="{{ $flag }}" value="1"
                                            @checked(old($flag, $settings->{$flag}))>
                                        <span class="track"></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-12">
                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-globe" style="color:#2b6cb0"></i>
                            <h4>{{ __('sales_settings.electronic.fields.environment') }}</h4>
                        </div>
                        <div class="body">
                            @foreach (\App\Models\ElectronicInvoiceSetting::ENVIRONMENTS as $env)
                                <div class="custom-control custom-radio {{ $loop->last ? '' : 'mb-1' }}">
                                    <input type="radio" class="custom-control-input" id="env-{{ $env }}"
                                        name="environment" value="{{ $env }}"
                                        @checked(old('environment', $settings->environment) === $env)>
                                    <label class="custom-control-label" for="env-{{ $env }}">
                                        <strong>{{ __('sales_settings.electronic.env.'.$env) }}</strong>
                                        <div style="font-size:.8rem;color:#8a93a0">
                                            {{ __('sales_settings.electronic.env_desc.'.$env) }}
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                            @error('environment')<div class="text-danger" style="font-size:.8rem">{{ $message }}</div>@enderror
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
