@extends('client::master')

@section('title', 'تعديل خط السير')

@section('content')
    @include('client::Itinerary.components.route-builder-styles')
    @include('components.hint-tips')
    @include('client::Itinerary.components.route-builder')
@endsection

@section('scripts')
    @include('client::Itinerary.components.route-builder-scripts')
@endsection
