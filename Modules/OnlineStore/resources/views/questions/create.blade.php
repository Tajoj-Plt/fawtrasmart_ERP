@extends('onlinestore::master')

@section('title', 'إضافة سؤال منتج')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('product_questions.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" form="question_form" class="btn btn-primary">
                <i class="feather icon-save"></i> حفظ
            </button>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إضافة سؤال منتج',
            'icon' => 'help-circle',
            'sub' => 'سجّل سؤالاً وصلك من عميل أو زائر (هاتف، واتساب، في الفرع) وأجب عليه ليظهر تحت المنتج في المتجر',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'أسئلة المنتجات' => route('product_questions.index'),
                'إضافة' => null,
            ],
        ])

        @include('layouts.alerts.error')

        <form id="question_form" action="{{ route('product_questions.store') }}" method="POST">
            @csrf
            @include('onlinestore::questions._form')
        </form>
    </div>
@endsection
