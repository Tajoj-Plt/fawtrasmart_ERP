{{--
    لوحة تحكّم خط السير — بتصميم شاشات خط السير وترتيبها.
    المُتحكِّم `ItineraryController::dashboard()`.
--}}
@extends('client::master')

@section('title', 'لوحة تحكم خط السير')

@section('content')
    @include('client::Itinerary.components.route-builder-styles')
    @include('components.hint-tips')
    @include('client::Itinerary.components.itinerary-index-styles')
    @include('client::Itinerary.components.itinerary-pages-styles')
    @include('client::Itinerary.components.itinerary-dashboard')
@endsection

@section('scripts')
    @include('client::Itinerary.components.itinerary-dashboard-scripts')
@endsection
