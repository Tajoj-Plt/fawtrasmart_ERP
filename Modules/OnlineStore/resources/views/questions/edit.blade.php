@extends('onlinestore::master')

@section('title', 'تعديل السؤال #' . $productQuestion->id)

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('product_questions.show', $productQuestion) }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="question_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ التعديلات
            </button>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تعديل السؤال #' . $productQuestion->id,
            'icon' => 'edit',
            'sub' => 'تعديل المنتج والسائل ونص السؤال وإجابته وحالة نشره',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'أسئلة المنتجات' => route('product_questions.index'),
                'تعديل' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="question_form" action="{{ route('product_questions.update', $productQuestion) }}" method="POST">
            @csrf
            @method('PUT')
            @include('onlinestore::questions._form')
        </form>
    </div>
@endsection
