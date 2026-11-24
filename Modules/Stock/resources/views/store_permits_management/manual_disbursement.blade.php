@extends('stock::master')

@section('title', 'إذن صرف مخزني')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إذن صرف مخزني',
            'icon' => 'minus-circle',
            'sub' => 'إخراج بضاعة من المستودع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('store_permits_management.index'),
                'إذن صرف' => null,
            ],
            'actions' => '<a href="' . route('store_permits_management.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="permitForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الإذن</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- النموذج كلّه في جزئية واحدة تخدم شاشات الإذن الثماني --}}
        @include('stock::store_permits_management.partials.form', [
            'type' => 2,
            'typeLabel' => 'إذن صرف',
            {{-- الصرف يُخرج البضاعة من المستودع، فلا وجهة له: كانت الشاشة
                 تطلب «إلى مستودع» إلزامياً فيضطرّ المستخدم لاختيار مستودعٍ
                 لا معنى له، ولا يُستعمل في القيد ولا في حركة المخزون --}}
            'stores' => 'single',
            'action' => route('store_permits_management.store'),
        ])
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection
