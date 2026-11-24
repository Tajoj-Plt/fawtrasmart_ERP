@extends('hr::master')

@section('title', 'تعديل العقد')

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل العقد '.($contract->code ?: '#'.$contract->id),
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'العقود' => route('Contracts.index'),
            ($contract->code ?: '#'.$contract->id) => route('Contracts.show', $contract->id),
            'تعديل' => null,
        ],
        'actions' => '<a href="'.route('Contracts.show', $contract->id).'" class="btn btn-outline-secondary">'
            .'<i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="contractForm" class="btn btn-primary">'
            .'<i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="mb-1"><strong>تعذّر الحفظ — راجِع الحقول المعلَّمة:</strong></p>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form id="contractForm" class="form" action="{{ route('Contracts.update', $contract->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('hr::salaries.contracts.partials.form')
    </form>
</div>
@endsection
