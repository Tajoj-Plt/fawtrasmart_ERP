{{--
    ملاحظات اليوم لخط السير — بتصميم شاشات خط السير وترتيبها.
    المُتحكِّم `ItineraryController::getTodayNotes()`.
--}}
@extends('client::master')

@section('title', 'ملاحظات اليوم')

@section('content')
    @include('client::Itinerary.components.route-builder-styles')
    @include('components.hint-tips')
    @include('client::Itinerary.components.itinerary-index-styles')
    @include('client::Itinerary.components.itinerary-pages-styles')
    @include('client::Itinerary.components.itinerary-today')
@endsection

@section('scripts')
    @include('client::Itinerary.components.itinerary-today-scripts')
@endsection
