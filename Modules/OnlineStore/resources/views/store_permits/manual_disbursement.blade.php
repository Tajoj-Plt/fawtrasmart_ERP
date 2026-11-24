@extends('onlinestore::master')

@section('title', 'إذن صرف مخزني')

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'إذن صرف مخزني',
            'icon' => 'minus-circle',
            'sub' => 'إخراج بضاعة من المستودع',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('online_store.permits.index'),
                'صرف يدوي' => null,
            ],
            'actions' => '<a href="' . route('online_store.permits.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="permitForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الإذن</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('onlinestore::store_permits.partials.form', [
            'type' => 2,
            'typeLabel' => 'إذن صرف',
            'stores' => 'single',
            'action' => route('online_store.permits.store'),
        ])
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection
