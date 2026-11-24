@extends('hr::master')

@section('title', 'تعديل نوع بند')

@section('content')
    @include('stock::components.page-head', [
        'title' => 'تعديل: '.$type->name,
        'icon' => 'edit',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الكتالوج' => route('SalaryComponentTypes.index'),
            'تعديل' => null,
        ],
    ])

    <div class="content-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('SalaryComponentTypes.update', $type->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('hr::salaries.component_types._form', ['type' => $type])
        </form>
    </div>
@endsection
