@extends('stock::master')

@section('title', 'إذن إضافة مخزني')

@section('content')
    {{--
        لا سنةَ مالية ⇒ المستندُ يُحفَظ بلا نسبة. يُقال ذلك قبل الإنشاء لا بعده.
    --}}
    @include('partials.no-fiscal-year-notice')

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إذن إضافة مخزني',
            'icon' => 'plus-circle',
            'sub' => 'إدخال بضاعة إلى المستودع بأسعار تكلفتها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('store_permits_management.index'),
                'إذن إضافة' => null,
            ],
            'actions' => '<a href="' . route('store_permits_management.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="permitForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الإذن</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{--
            كانت هذه الشاشة تحمل نسخةً ثانية من النموذج (نحو 250 سطراً) بينما
            بقيّة شاشات الإذن تستعمل الجزئية المشتركة — فبقيت وحدها خارج كل
            إصلاح: `required` على قوائم select2 المخفيّة يمنع الإرسال بصمت،
            ولا مكان تظهر فيه أخطاء التحقّق.
        --}}
        @include('stock::store_permits_management.partials.form', [
            'type' => 1,
            'typeLabel' => 'إذن إضافة',
            'stores' => 'single',
            'action' => route('store_permits_management.store'),
        ])
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('app-assets/vendors/js/extensions/sweetalert2.all.min.js') }}"></script>
@endsection
