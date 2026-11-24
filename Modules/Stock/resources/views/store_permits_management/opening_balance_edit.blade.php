@extends('stock::master')

@section('title', 'تعديل الرصيد الافتتاحي')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل الرصيد الافتتاحي',
            'icon' => 'edit-2',
            'sub' => 'تعديل بيانات الرصيد الافتتاحي وبنوده',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('store_permits_management.index'),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('store_permits_management.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="permitForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- النموذج كلّه في جزئية واحدة تخدم شاشات الإذن الثماني --}}
        @include('stock::store_permits_management.partials.form', [
            'type' => 4,
            'typeLabel' => 'رصيد افتتاحي',
            'stores' => 'single',
            'action' => route('store_permits_management.update', $permit->id),
            'permit' => $permit,
            'items' => App\Models\WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->get(),
        ])
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection
