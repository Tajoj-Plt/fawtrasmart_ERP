@extends('client::reservations.master')

@section('title', __('reservations.menu.create'))

@section('content')
    @section('page-actions')
        <a href="{{ route('Reservations.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> {{ __('reservations.form.back_to_list') }}
        </a>
    @endsection

    @include('client::reservations.components.page-head', [
            'title' => __('reservations.menu.create'),
            'icon' => 'plus-circle',
            'sub' => __('reservations.form.create_sub'),
            'crumbs' => [
                __('reservations.menu.home') => route('dashboard'),
                __('reservations.menu.reservations') => route('Reservations.index'),
                __('reservations.menu.create') => null,
            ],
        ])

    @include('client::reservations.partials.alerts')
    @include('client::reservations.partials.readiness-banner')

    {{-- الحفظُ في بطاقة الملخّص داخل النموذج — لا زرَّ ثانياً في الأسفل --}}
    <form method="POST" action="{{ route('Reservations.store') }}" id="rsv-form" class="mb-4">
        @csrf
        @include('client::reservations.partials.form')
    </form>
@endsection

@section('scripts')
    @include('client::reservations.partials.form_scripts')
@endsection
