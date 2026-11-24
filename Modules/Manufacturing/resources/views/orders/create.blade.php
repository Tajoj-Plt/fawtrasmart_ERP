@extends('manufacturing::master')

@section('title', 'أمر تصنيع جديد')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'أمر تصنيع جديد',
            'icon' => 'tool',
            'sub' => 'المنتج والكميّة ومكوّنات التصنيع من مواد ومصروفات وعمليات وهوالك',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'أوامر التصنيع' => route('manufacturing.orders.index'),
                'جديد' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.orders.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="manufacturingOrderForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الأمر</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @include('manufacturing::orders.partials.form', [
            'action' => route('manufacturing.orders.store'),
            'submitLabel' => 'حفظ الأمر',
            'order' => null,
        ])
    </div>
@endsection
