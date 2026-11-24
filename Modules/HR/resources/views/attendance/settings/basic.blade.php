@extends('hr::master')

@section('title', 'الأعدادات الأساسية')

@section('content')
    <!-- Header Section -->
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'الأعدادات الأساسية',
        'crumbs' => ['الرئيسية' => null, 'إضافة' => null],
    ])
    @endsection