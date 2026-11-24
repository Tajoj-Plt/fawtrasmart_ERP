@extends('account::master')
@section('title')
    سجل التعديلات
@stop

@section('content')
    @include('account::components.page-head', [
        'title' => 'سجل التعديلات',
        'crumbs' => [
            'الرئيسيه' => null,
            'عرض' => null,
        ],
    ])

@endsection
