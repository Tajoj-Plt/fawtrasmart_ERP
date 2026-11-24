@extends('hr::master')

@section('title', 'سياسة الأجازات')

@section('content')
    <!-- Header Section -->
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'سياسة الأجازات',
        'crumbs' => ['الرئيسية' => null, 'إضافة' => null],
    ])
    @endsection