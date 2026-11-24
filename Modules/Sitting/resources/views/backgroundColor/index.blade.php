@extends('sitting::master')

@section('title')
  {{ __('settings.background.title') }}
@stop

@section('content')
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-droplet"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('settings.background.heading') }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="">{{ __('settings.common.home') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('settings.currency.view') }} </li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    @include('layouts.alerts.success')
        <!-- عرض الأخطاء -->
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    
                    <div>
                        <label>{!! __('settings.background.required_note') !!}</label>
                    </div>
                    <form  action="{{ route('AccountInfo.updateColor') }}" method="POST">
                        @csrf
                    <div>
                        <a href="" class="btn btn-outline-danger">
                            <i class="fa fa-ban"></i>{{ __('settings.background.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i>  {{ __('settings.background.update') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="color" class="form-label">{{ __('settings.background.color_code') }} <span style="color: red">*</span></label>
                        <input type="text" id="color" name="color" class="form-control" placeholder="{{ __('settings.background.color_placeholder') }}"
                            value="{{ old('color', $backgroundColor->color ?? '#ffffff') }}" required>
                        
                        <!-- حقل اختيار اللون -->
                        <input type="color" id="colorPicker" class="form-control mt-2"
                            value="{{ old('color', $backgroundColor->color ?? '#ffffff') }}"
                            onchange="document.getElementById('color').value = this.value;">
        
                        @error('color')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
        
    </form>
</div>
@endsection


