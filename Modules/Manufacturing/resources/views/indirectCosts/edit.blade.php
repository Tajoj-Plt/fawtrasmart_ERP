@extends('manufacturing::master')

@section('title', 'تعديل تكلفة غير مباشرة')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل التكلفة غير المباشرة',
            'icon' => 'edit-2',
            'sub' => (optional($indirectCost->account)->name ?? 'حساب غير محدد') . ' — ' . ($indirectCost->code ?: '#' . $indirectCost->id),
            'crumbs' => [
                'الرئيسية' => url('/'),
                'التكاليف غير المباشرة' => route('manufacturing.indirectcosts.index'),
                ($indirectCost->code ?: '#' . $indirectCost->id) => route('manufacturing.indirectcosts.show', $indirectCost->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.indirectcosts.show', $indirectCost->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="indirectCostForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @include('manufacturing::indirectCosts.form', [
            'action' => route('manufacturing.indirectcosts.update', $indirectCost->id),
            'submitLabel' => 'حفظ التعديلات',
            'indirectCost' => $indirectCost,
        ])
    </div>
@endsection
