@extends('client::reservations.master')

@section('title', __('reservations.services.profile_title', ['name' => $service->displayName()]))

@section('content')
    @section('page-actions')
        <a href="{{ route('Reservations.services.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> {{ __('reservations.actions.back') }}
        </a>
    @endsection

    @include('client::reservations.components.page-head', [
        'title' => __('reservations.services.profile_title', ['name' => $service->displayName()]),
        'icon' => 'sliders',
        'sub' => __('reservations.services.product_info'),
        'crumbs' => [
            __('reservations.menu.home') => route('dashboard'),
            __('reservations.services.title') => route('Reservations.services.index'),
            $service->displayName() => null,
        ],
    ])

    @include('client::reservations.partials.alerts')

    @if ($product)
        <form method="POST" action="{{ route('Reservations.services.update', $service->id) }}">
            @csrf
            @method('PUT')
            @include('client::reservations.services._form')
            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-primary"><i class="feather icon-save"></i> {{ __('reservations.services.save') }}</button>
                <a href="{{ route('Reservations.services.index') }}" class="btn btn-outline-secondary">{{ __('reservations.actions.back') }}</a>
            </div>
        </form>
    @else
        {{-- ملفٌّ قديم بلا منتج: يبقى لحجوزه، ولا يُعدَّل إلى كتالوجٍ ثانٍ --}}
        <div class="alert alert-warning">{{ __('reservations.services.legacy_profile') }}</div>
    @endif
@endsection
