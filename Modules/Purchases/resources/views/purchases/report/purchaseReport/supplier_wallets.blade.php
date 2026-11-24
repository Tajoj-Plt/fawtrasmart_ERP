{{-- تقرير محافظ الموردين — المحتوى في `reports.wallets.content`، مشتركاً مع تقرير محافظ العملاء --}}
@extends('purchases::master')

@section('title')
    تقرير محافظ الموردين
@stop

@section('css')
    @include('client::customers.CustomerReport.partials.report-theme')
@endsection

@php
    $wrShared = [
        'wrParty' => 'supplier',
        'wrNoun' => 'المورد',
        'wrNounPlural' => 'الموردين',
        'wrTitle' => 'تقرير محافظ الموردين',
        'wrAction' => route('ReportsPurchases.supplierWallets'),
        'wrFields' => 'purchases::purchases.report.purchaseReport.partials.wallet-fields',
        'wrCrumbLabel' => 'تقارير المشتريات',
        'wrCrumbUrl' => route('ReportsPurchases.index'),
    ];
@endphp

@section('content')
    @include('reports.wallets.content', $wrShared)
@endsection

@section('scripts')
    @include('reports.wallets.scripts', $wrShared)
@endsection
