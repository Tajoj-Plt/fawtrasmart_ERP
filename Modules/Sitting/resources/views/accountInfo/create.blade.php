@extends('master')

@section('title')
    {{ __('settings.account_info.create_title') }} 
@stop

@section('content')
    <div style="font-size: 1.1rem;">
        <div class="sl-page-head">
            <div class="sl-page-head__titles">
                <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-briefcase"></i></span>
                <div style="min-width:0">
                    <h2 class="sl-page-head__title">{{ __('settings.account_info.add_membership') }}</h2>
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="">{{ __('settings.common.home') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('settings.currency.view') }}</li>

                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <form id="clientForm" action="{{ route('AccountInfo.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="content-body">
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                            <div>
                                <label>{!! __('settings.background.required_note') !!}</label>
                            </div>
                            <div>
                                <a href="" class="btn btn-outline-danger">
                                    <i class="fa fa-ban"></i> {{ __('settings.background.cancel') }}
                                </a>
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fa fa-save"></i> {{ __('settings.common.save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="max-width: 90%; margin: 0 auto;">
                    <div class="card-header">
                        <h1>{{ __('settings.account_info.business_data') }}</h1>
                    </div>
                    <div class="card-body">
                        <div class="form-body row mb-5 align-items-center">
                            <div class="form-group col-md-4 mb-3">
                                <label for="business_name" class="">{{ __('settings.account_info.trade_name') }} <span class="text-danger">*</span></label>
                                <input type="name" class="form-control @error('business_name') is-invalid @enderror" 
                                       name="business_name" value="{{ old('business_name') }}">
                                @error('business_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-4 mb-3">
                                <label for="first_name" class="">{{ __('settings.account_info.first_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('first_name') is-invalid @enderror" 
                                       name="first_name" value="{{ old('first_name') }}">
                                @error('first_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-4 mb-3">
                                <label for="last_name" class="">{{ __('settings.account_info.last_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('last_name') is-invalid @enderror" 
                                       name="last_name" value="{{ old('last_name') }}">
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="form-body row mb-5 align-items-center">
                            <div class="form-group col-md-6 mb-3">
                                <label for="phone" class="">{{ __('settings.account_info.phone') }} <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control @error('phone') is-invalid @enderror" 
                                       name="phone" value="{{ old('phone') }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <label for="mobile" class="">{{ __('settings.account_info.mobile') }} <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control @error('mobile') is-invalid @enderror" 
                                       name="mobile" value="{{ old('mobile') }}">
                                @error('mobile')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="form-body row mb-5 align-items-center">
                            <div class="form-group col-md-6 mb-3">
                                <label for="address1" class="">{{ __('settings.account_info.street1') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('address1') is-invalid @enderror" 
                                       name="address1" value="{{ old('address1') }}">
                                @error('address1')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <label for="address2" class="">{{ __('settings.account_info.street2') }}</label>
                                <input type="text" class="form-control @error('address2') is-invalid @enderror" 
                                       name="address2" value="{{ old('address2') }}">
                                @error('address2')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="form-body row mb-5 align-items-center">
                            <div class="form-group col-md-6 mb-3">
                                <label for="city" class="">{{ __('settings.account_info.city') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('city') is-invalid @enderror" 
                                       name="city" value="{{ old('city') }}">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <label for="postal_code" class="">{{ __('settings.account_info.postal_code') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('postal_code') is-invalid @enderror" 
                                       name="postal_code" value="{{ old('postal_code') }}">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="form-group col-md-6 mb-3">
                            <label for="country" class="">{{ __('settings.account_info.country') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('country') is-invalid @enderror" 
                                   name="country" value="{{ old('country') }}">
                            @error('country')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                    </div>
                </div>

           

            <div>
            </br>
        </hr>
            </div>
           
            <div class="card" style="max-width: 90%; margin: 0 auto;">
                <div class="card-header">
                    <h1>{{ __('settings.account_info.account_settings') }}</h1>
                </div>
                <div class="card-body">
                    <div class="form-body row mb-5 align-items-center">
                        <div class="form-group col-md-6 mb-3">
                            <label for="currency" class="">{{ __('settings.account_info.currency') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('currency') is-invalid @enderror" 
                                   name="currency" value="{{ old('currency') }}">
                            @error('currency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group col-md-6 mb-3">
                            <label for="timezone" class="">{{ __('settings.account_info.timezone') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('timezone') is-invalid @enderror" 
                                   name="timezone" value="{{ old('timezone') }}">
                            @error('timezone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
            
                    <div class="form-group col-md-6 mb-3">
                        <label for="business_type" class="">{{ __('settings.account_info.business_type') }} <span class="text-danger">*</span></label>
                        <select name="business_type" class="form-control @error('business_type') is-invalid @enderror">
                            <option value="products" {{ old('business_type') == 'products' ? 'selected' : '' }}>{{ __('settings.account_info.type_products') }}</option>
                            <option value="services" {{ old('business_type') == 'services' ? 'selected' : '' }}>{{ __('settings.account_info.type_services') }}</option>
                            <option value="products_services" {{ old('business_type') == 'products_services' ? 'selected' : '' }}>{{ __('settings.account_info.type_both') }}</option>
                        </select>
                        @error('business_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            
        </form>
    </div>
@endsection
