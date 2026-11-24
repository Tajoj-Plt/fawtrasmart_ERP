@extends('client::master')

@section('title', 'خط سيري اليومي')

@section('content')
    @include('client::Itinerary.components.daily-route-styles')
    @include('client::Itinerary.components.daily-route')
@endsection

@section('scripts')
    @include('client::Itinerary.components.daily-route-scripts')
@endsection
