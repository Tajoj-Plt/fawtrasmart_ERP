@extends('hr::master')

@section('title', 'إضافة نوع بند')

@section('content')
    @include('stock::components.page-head', [
        'title' => 'إضافة نوع بند',
        'icon' => 'plus',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الكتالوج' => route('SalaryComponentTypes.index'),
            'إضافة' => null,
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

        <form action="{{ route('SalaryComponentTypes.store') }}" method="POST">
            @csrf
            @include('hr::salaries.component_types._form', ['type' => null])
        </form>
    </div>
@endsection
