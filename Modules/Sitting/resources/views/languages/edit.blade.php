@extends('sitting::master')

@section('title', __('languages.edit'))

@section('content')
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-globe"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">{{ __('languages.edit_language', ['name' => $language->name]) }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('languages.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.language') }}">{{ __('languages.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('languages.edit') }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            <a href="{{ route('languages.show', $language->id) }}" class="btn btn-outline-info mr-1">
                <i class="feather icon-eye"></i> {{ __('languages.show') }}
            </a>
            <a href="{{ route('SittingAccount.language') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-left"></i> {{ __('languages.back') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.error')

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <h6 class="alert-heading"><i class="fa fa-exclamation-circle mr-1"></i> {{ __('languages.messages.validation_errors') }}</h6>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($language->is_default)
            <div class="alert alert-warning py-1 px-2" role="alert">
                <i class="fa fa-star mr-1"></i>
                {{ __('languages.is_the_default') }} — {{ __('languages.default_language_note') }}
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="feather icon-edit"></i>
                    {{ __('languages.edit_language', ['name' => $language->name]) }}
                </h4>
            </div>
            <div class="card-body">
                <form action="{{ route('languages.update', $language->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    @include('sitting::languages._form', ['language' => $language])

                    <div class="d-flex justify-content-end pt-2 mt-2 border-top">
                        <a href="{{ route('SittingAccount.language') }}" class="btn btn-outline-secondary mr-1">
                            <i class="feather icon-x"></i> {{ __('languages.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-check"></i> {{ __('languages.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
