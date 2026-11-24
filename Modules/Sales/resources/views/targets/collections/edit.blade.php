@extends('sales::master')

{{-- تعديل هدف تحصيل.

     القالب مشترك بين «أهداف المبيعات» و«أهداف التحصيلات»: كانت الشاشتان
     ملفّين متطابقين إلّا في أسماء المسارات. ما يفترقان فيه يصل في
     `$screen` من `Modules\Sales\Support\TargetScreen`. --}}

@php
    // الوصف يُبنى هنا لا في المتحكّم: القالب هو من يعرف أيّ نوعٍ يعرض،
    // والمتحكّم يمرّر البيانات وحدها
    $screen = \Modules\Sales\Support\TargetScreen::config('collections');
@endphp

@section('title', $screen['edit_title'])

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @include('sales::targets.partials.form')
@endsection
