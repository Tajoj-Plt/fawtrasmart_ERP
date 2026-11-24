@extends('hr::master')

@section('title', 'الماكينات')

@section('content')
    <!-- Header Section -->
    {{-- ترويسةُ نظام التصميم — كانت مكتوبةً يدوياً بستّة مستويات
         من `div` وفتاتِ تنقّلٍ رابطُه الأوّل فارغ. --}}
    @include('stock::components.page-head', [
        'title' => 'الماكينات',
        'crumbs' => ['الرئيسية' => null, 'إضافة' => null],
    ])
    @endsection