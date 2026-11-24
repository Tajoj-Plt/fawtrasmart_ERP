@extends('manufacturing::master')

@section('title', 'مسار إنتاج جديد')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'مسار إنتاج جديد',
            'icon' => 'git-merge',
            'sub' => 'اسم المسار ومراحله بالترتيب',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'مسارات الإنتاج' => route('manufacturing.paths.index'),
                'جديد' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.paths.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="pathForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ المسار</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        {{-- النموذج مشترك بين الإنشاء والتعديل --}}
        @include('manufacturing::settings.Paths.form', [
            'action' => route('manufacturing.paths.store'),
            'submitLabel' => 'حفظ المسار',
        ])
    </div>
@endsection
