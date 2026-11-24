{{--
    عرض خطوط السير — بتصميم شاشة التخطيط وترتيبها: لوحةُ التصفية مكان «اختيار
    العملاء»، والبطاقات، وشريطُ الأسابيع مكان شريط الأيام، وجدولُ المندوبين
    بأيامهم، ولوحةُ اليوم بزياراته وخريطته.
    المُتحكِّم `ItineraryController::listAll()` و`dayVisits()`.
--}}
@extends('client::master')

@section('title', 'خطوط السير')

@section('content')
    @include('client::Itinerary.components.route-builder-styles')
    @include('components.hint-tips')
    @include('client::Itinerary.components.itinerary-index-styles')
    @include('client::Itinerary.components.itinerary-index')
@endsection

@section('scripts')
    @include('client::Itinerary.components.itinerary-index-scripts')
@endsection
