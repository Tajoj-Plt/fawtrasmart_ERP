@extends('manufacturing::master')

@section('title', 'تكلفة غير مباشرة جديدة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تكلفة غير مباشرة جديدة',
            'icon' => 'pie-chart',
            'sub' => 'اختر حساب المصروف والفترة — النظام يجلب القيود ويحسب نصيب الوحدة ويوزّع على أوامر الفترة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التكاليف غير المباشرة' => route('manufacturing.indirectcosts.index'),
                'جديدة' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.indirectcosts.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="indirectCostForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @include('manufacturing::indirectCosts.form', [
            'action' => route('manufacturing.indirectcosts.store'),
            'submitLabel' => 'حفظ',
            'indirectCost' => null,
        ])
    </div>
@endsection
