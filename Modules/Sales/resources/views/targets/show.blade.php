@extends('sales::master')

{{-- تفاصيل هدف المبيعات — قالبٌ كان مفقوداً بالكامل رغم وجود مساره ومتحكّمه.

     القالب مشترك بين «أهداف المبيعات» و«أهداف التحصيلات»: كانت الشاشتان
     ملفّين متطابقين إلّا في أسماء المسارات. ما يفترقان فيه يصل في
     `$screen` من `Modules\Sales\Support\TargetScreen`. --}}

@php
    // الوصف يُبنى هنا لا في المتحكّم: القالب هو من يعرف أيّ نوعٍ يعرض،
    // والمتحكّم يمرّر البيانات وحدها
    $screen = \Modules\Sales\Support\TargetScreen::config('sales');
@endphp

@section('title', $target->name)

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @include('sales::targets.partials.show')
@endsection
