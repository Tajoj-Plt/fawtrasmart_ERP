@extends('ecosystem::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('ecosystem.name') !!}</p>
@endsection
