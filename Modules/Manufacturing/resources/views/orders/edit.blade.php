@extends('manufacturing::master')

@section('title', 'تعديل أمر التصنيع — ' . $order->name)

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل أمر التصنيع',
            'icon' => 'edit-2',
            'sub' => $order->name . ' — #' . $order->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'أوامر التصنيع' => route('manufacturing.orders.index'),
                $order->name => route('manufacturing.orders.show', $order->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.orders.show', $order->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="manufacturingOrderForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @include('manufacturing::orders.partials.form', [
            'action' => route('manufacturing.orders.update', $order->id),
            'submitLabel' => 'حفظ التعديلات',
        ])
    </div>
@endsection
