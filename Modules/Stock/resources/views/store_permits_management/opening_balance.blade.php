@extends('stock::master')

@section('title', 'رصيد افتتاحي')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'رصيد افتتاحي',
            'icon' => 'star',
            'sub' => 'إثبات أرصدة المستودع الافتتاحية',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('store_permits_management.index'),
                'رصيد افتتاحي' => null,
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
            'type' => 4,
            'typeLabel' => 'رصيد افتتاحي',
            'stores' => 'single',
            'action' => route('store_permits_management.store'),
        ])
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection
