@extends('sales::master')

{{-- اختيار فترة تقرير هدف التحصيل.

     القالب مشترك بين «أهداف المبيعات» و«أهداف التحصيلات»: كانت الشاشتان
     ملفّين متطابقين إلّا في أسماء المسارات وأعمدة التفصيل. ما يفترقان فيه
     يصل في `$screen` من `Modules\Sales\Support\TargetScreen`. --}}

@php
    // الوصف يُبنى هنا لا في المتحكّم: القالب هو من يعرف أيّ نوعٍ يعرض،
    // والمتحكّم يمرّر البيانات وحدها
    $screen = \Modules\Sales\Support\TargetScreen::config('collections');
@endphp

@section('title', __('sales_targets.report.page_title', ['name' => $target->name]))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @include('sales::targets.partials.report')
@endsection
