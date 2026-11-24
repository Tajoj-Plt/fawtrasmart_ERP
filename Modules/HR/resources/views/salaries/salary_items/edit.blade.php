@extends('hr::master')

@section('title')
    تعديل بند راتب
@stop

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/formula-autocomplete.css') }}">
@endpush

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'تعديل بند الراتب: '.$salaryItem->name,
            'icon' => 'edit-2',
            'sub' => 'التعديلُ يسري على ما يُنسخ ويُحتسب بعده؛ العقودُ والقسائمُ القائمة تحتفظ بنسختها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'بنود الرواتب' => route('SalaryItems.index'),
                $salaryItem->name => route('SalaryItems.show', $salaryItem->id),
                'تعديل' => null,
            ],
            'actions' => '<a href="'.route('SalaryItems.show', $salaryItem->id).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
                .'<button type="submit" form="salary_item_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>',
        ])

        @include('layouts.alerts.error')

        <form id="salary_item_form" action="{{ route('SalaryItems.update', $salaryItem->id) }}" method="POST">
            @csrf
            @method('PUT')

            @include('hr::salaries.salary_items._form', ['salaryItem' => $salaryItem, 'usage' => $usage ?? null])

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('SalaryItems.show', $salaryItem->id) }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ التعديلات
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @include('hr::salaries.salary_items._form_script')
@endpush
