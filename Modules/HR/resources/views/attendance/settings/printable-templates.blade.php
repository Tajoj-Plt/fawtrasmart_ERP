@extends('hr::master')

@section('title', 'قوالب   الطباعة')

@section('content')
    <!-- Header Section -->
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'قوالب الطباعة',
        'crumbs' => ['الرئيسية' => null, 'إضافة' => null],
    ])
    @endsection