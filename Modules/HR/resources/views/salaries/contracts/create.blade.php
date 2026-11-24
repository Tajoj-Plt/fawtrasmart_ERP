@extends('hr::master')

@section('title', 'إضافة عقد')

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة عقد',
        'icon' => 'file-plus',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'العقود' => route('Contracts.index'),
            'إضافة' => null,
        ],
        'actions' => '<a href="'.route('Contracts.index').'" class="btn btn-outline-secondary">'
            .'<i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="contractForm" class="btn btn-primary">'
            .'<i class="feather icon-check"></i> حفظ العقد</button>',
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="mb-1"><strong>تعذّر الحفظ — راجِع الحقول المعلَّمة:</strong></p>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form id="contractForm" class="form" action="{{ route('Contracts.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('hr::salaries.contracts.partials.form')
    </form>
</div>
@endsection
