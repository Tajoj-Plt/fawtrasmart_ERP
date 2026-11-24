@extends('hr::master')

@section('title')
    إضافة بند راتب
@stop

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/formula-autocomplete.css') }}">
@endpush

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'إضافة بند راتب',
            'icon' => 'plus-circle',
            'sub' => 'استحقاقٌ أو استقطاعٌ يُختار بعد حفظه في العقود والقسائم',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'بنود الرواتب' => route('SalaryItems.index'),
                'إضافة' => null,
            ],
            'actions' => '<a href="'.route('SalaryItems.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="salary_item_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ البند</button>',
        ])

        @include('layouts.alerts.error')

        <form id="salary_item_form" action="{{ route('SalaryItems.store') }}" method="POST">
            @csrf

            @include('hr::salaries.salary_items._form', ['salaryItem' => null])

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('SalaryItems.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ البند
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @include('hr::salaries.salary_items._form_script')
@endpush
