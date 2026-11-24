@extends('manufacturing::master')

@section('title', 'تعديل ' . $plan->name)

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل ' . $plan->name,
            'icon' => 'calendar',
            'sub' => '#' . $plan->code . ' — ' . $plan->status->label(),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'خطط الإنتاج' => route('manufacturing.production-plans.index'),
                $plan->name => route('manufacturing.production-plans.show', $plan->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.production-plans.show', $plan->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="planForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @include('manufacturing::production-plans.partials.form', [
            'action' => route('manufacturing.production-plans.update', $plan->id),
            'method' => 'PUT',
        ])
    </div>
@endsection
