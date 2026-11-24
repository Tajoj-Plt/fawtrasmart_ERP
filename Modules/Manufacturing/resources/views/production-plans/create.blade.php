@extends('manufacturing::master')

@section('title', 'خطة إنتاج جديدة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'خطة إنتاج جديدة',
            'icon' => 'calendar',
            'sub' => 'بنودُ الخطّة تصير أوامرَ تصنيع بعد اعتمادها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'خطط الإنتاج' => route('manufacturing.production-plans.index'),
                'جديدة' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.production-plans.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="planForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الخطّة</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @include('manufacturing::production-plans.partials.form', [
            'plan' => null,
            'action' => route('manufacturing.production-plans.store'),
            'method' => 'POST',
        ])
    </div>
@endsection
