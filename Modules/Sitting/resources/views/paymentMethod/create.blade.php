@extends('sitting::master')

@section('title', __('settings.payment.new'))

@section('content')
    @include('sitting::components._settings_styles')

    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-credit-card"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.payment.new') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('PaymentMethods.index') }}">{{ __('settings.payment.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.tax.is_new') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('PaymentMethods.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> {{ __('settings.common.back') }}
            </a>

        </div>
    </div>

    <div class="content-body">
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

        <form method="POST" action="{{ route('PaymentMethods.store') }}">
            @csrf

            <div class="row">
                <div class="col-lg-7 col-12">
                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-credit-card" style="color:#2b6cb0"></i>
                            <h4>{{ __('settings.payment.basic') }}</h4>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label>{{ __('settings.payment.name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" placeholder="{{ __('settings.payment.name_placeholder') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('settings.payment.name_en') }}</label>
                                    <input type="text" name="name_en" class="form-control"
                                        value="{{ old('name_en') }}" placeholder="Cash">
                                </div>
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('settings.common.code') }}</label>
                                    <input type="text" name="code" class="form-control st-mono"
                                        style="direction:ltr;text-align:left;width:100%"
                                        value="{{ old('code') }}" placeholder="{{ $suggestedCode ?? 'CASH' }}">
                                    <small class="text-muted">{{ __('settings.payment.code_hint') }}</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>{{ __('settings.common.description') }}</label>
                                <textarea name="description" class="form-control" rows="3"
                                    placeholder="{{ __('settings.payment.description_placeholder') }}">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-percent" style="color:#2b6cb0"></i>
                            <h4>{{ __('settings.payment.limits') }}</h4>
                            <span class="count">{{ __('settings.common.optional') }}</span>
                        </div>
                        <div class="body">
                            <div class="row">
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('settings.payment.min_amount') }}</label>
                                    <input type="number" name="min_amount" class="form-control" step="0.01" min="0"
                                        value="{{ old('min_amount') }}" placeholder="{{ __('settings.common.no_limit') }}">
                                </div>
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('settings.payment.max_amount') }}</label>
                                    <input type="number" name="max_amount" class="form-control" step="0.01" min="0"
                                        value="{{ old('max_amount') }}" placeholder="{{ __('settings.common.no_limit') }}">
                                </div>
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('settings.payment.fee_type') }}</label>
                                    <select name="fee_type" class="form-control">
                                        <option value="fixed" {{ old('fee_type') === 'fixed' ? 'selected' : '' }}>{{ __('settings.payment.fee_fixed') }}</option>
                                        <option value="percentage" {{ old('fee_type') === 'percentage' ? 'selected' : '' }}>{{ __('settings.payment.fee_percentage') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6 col-12 form-group">
                                    <label>{{ __('settings.payment.fee_amount') }}</label>
                                    <input type="number" name="fee_amount" class="form-control" step="0.01" min="0"
                                        value="{{ old('fee_amount', 0) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('sitting::paymentMethod._gateway_fields')
                </div>

                <div class="col-lg-5 col-12">
                    <div class="st-card">
                        <div class="head">
                            <i class="feather icon-sliders" style="color:#2b6cb0"></i>
                            <h4>{{ __('settings.payment.type_and_status') }}</h4>
                        </div>
                        <div class="body">
                            <div class="form-group">
                                <label>{{ __('settings.common.type') }} <span class="text-danger">*</span></label>
                                <div class="custom-control custom-radio mb-1">
                                    <input type="radio" class="custom-control-input" id="type-normal" name="type"
                                        value="normal" {{ old('type', 'normal') === 'normal' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="type-normal">
                                        <strong>{{ __('settings.payment.type_normal') }}</strong>
                                        <div style="font-size:.8rem;color:#8a93a0">{{ __('settings.payment.type_normal_hint') }}</div>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" class="custom-control-input" id="type-electronic" name="type"
                                        value="electronic" {{ old('type') === 'electronic' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="type-electronic">
                                        <strong>{{ __('settings.payment.type_electronic') }}</strong>
                                        <div style="font-size:.8rem;color:#8a93a0">{{ __('settings.payment.type_electronic_hint') }}</div>
                                    </label>
                                </div>
                            </div>

                            <hr>

                            <div class="st-toggle-row">
                                <div class="txt">
                                    <b>{{ __('settings.payment.is_enabled') }}</b>
                                    <small>{{ __('settings.payment.is_enabled_hint') }}</small>
                                </div>
                                <label class="st-switch">
                                    <input type="hidden" name="status" value="inactive">
                                    <input type="checkbox" name="status" value="active"
                                        {{ old('status', 'active') === 'active' ? 'checked' : '' }}>
                                    <span class="track"></span>
                                </label>
                            </div>

                            <div class="st-toggle-row">
                                <div class="txt">
                                    <b>{{ __('settings.payment.is_online') }}</b>
                                    <small>{{ __('settings.payment.is_online_hint') }}</small>
                                </div>
                                <label class="st-switch">
                                    <input type="hidden" name="is_online" value="0">
                                    <input type="checkbox" name="is_online" value="1"
                                        {{ old('is_online') ? 'checked' : '' }}>
                                    <span class="track"></span>
                                </label>
                            </div>
                        </div>
                        <div class="foot text-center">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="feather icon-save"></i> {{ __('settings.payment.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
