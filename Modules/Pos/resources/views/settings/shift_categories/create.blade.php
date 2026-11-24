{{--
    إضافة تصنيف ورديات — الحقولُ في [_form.blade.php] المشترك مع التعديل.
--}}
@extends('pos::master')

@section('title', 'إضافة تصنيف ورديات')

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.shift_categories.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="shiftCategoryForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التصنيف
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => 'إضافة تصنيف',
        'icon' => 'tag',
        'sub' => 'اسمٌ ولونٌ وترتيبُ عرضٍ وفرع',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'تصنيفات الورديات' => route('pos.settings.shift_categories.index'),
            'إضافة' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.shift_categories.store') }}" method="POST"
                  id="shiftCategoryForm">
                @csrf
                @include('pos::settings.shift_categories._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.shift_categories.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="shiftCategoryForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التصنيف
        </button>
    </div>
</div>

@endsection
