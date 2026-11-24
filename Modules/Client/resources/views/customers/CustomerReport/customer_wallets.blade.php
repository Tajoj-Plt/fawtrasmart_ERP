{{-- تقرير محافظ العملاء — المحتوى في `reports.wallets.content`، مشتركاً مع تقرير محافظ الموردين --}}
@extends('client::master')

@section('title')
    تقرير محافظ العملاء
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@php
    $wrShared = [
        'wrParty' => 'client',
        'wrNoun' => 'العميل',
        'wrNounPlural' => 'العملاء',
        'wrTitle' => 'تقرير محافظ العملاء',
        'wrAction' => route('ClientReport.customerWallets'),
        'wrFields' => 'client::customers.CustomerReport.partials.wallet-fields',
        'wrCrumbLabel' => 'تقارير العملاء',
        'wrCrumbUrl' => route('ClientReport.index'),
    ];
@endphp

@section('content')
    @include('reports.wallets.content', $wrShared)
@endsection

@section('scripts')
    @include('reports.wallets.scripts', $wrShared)
@endsection
