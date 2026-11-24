@extends('onlinestore::master')

@section('title', 'تعديل إذن الصرف')

@section('content')
    <div class="content-body">

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل إذن الصرف',
            'icon' => 'edit-2',
            'sub' => 'تعديل بيانات الإذن وبنوده',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأذون المخزنية' => route('online_store.permits.index'),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('online_store.permits.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="permitForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('onlinestore::store_permits.partials.form', [
            'type' => 2,
            'typeLabel' => 'إذن صرف',
            'stores' => 'single',
            'action' => route('online_store.permits.update', $permit->id),
            'permit' => $permit,
            'items' => App\Models\WarehousePermitsProducts::where('warehouse_permits_id', $permit->id)->get(),
        ])
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection
