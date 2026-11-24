@extends('manufacturing::master')

@section('title', 'تعديل ' . $path->name)

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'تعديل مسار الإنتاج',
            'icon' => 'edit-2',
            'sub' => $path->name . ' — #' . $path->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'مسارات الإنتاج' => route('manufacturing.paths.index'),
                $path->name => route('manufacturing.paths.show', $path->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.paths.show', $path->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="pathForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        @include('manufacturing::settings.Paths.form', [
            'action' => route('manufacturing.paths.update', $path->id),
            'path' => $path,
            'stages' => $path->stages ?? collect(),
            'submitLabel' => 'حفظ التعديلات',
        ])
    </div>
@endsection
