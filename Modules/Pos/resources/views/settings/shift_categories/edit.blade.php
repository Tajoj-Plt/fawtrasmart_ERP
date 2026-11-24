{{--
    تعديل تصنيف ورديات — نفسُ [_form.blade.php] المستعمل في الإضافة.
--}}
@extends('pos::master')

@section('title', 'تعديل تصنيف ورديات')

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.shift_categories.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="shiftCategoryForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التعديلات
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => 'تعديل: '.$category->name,
        'icon' => 'tag',
        'sub' => $category->shifts_count ?? null
            ? 'عليه '.$category->shifts_count.' وردية'
            : 'اسمٌ ولونٌ وترتيبُ عرضٍ وفرع',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'تصنيفات الورديات' => route('pos.settings.shift_categories.index'),
            'تعديل' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.shift_categories.update', $category->id) }}" method="POST"
                  id="shiftCategoryForm">
                @csrf
                @method('PUT')
                @include('pos::settings.shift_categories._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.shift_categories.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="shiftCategoryForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التعديلات
        </button>
    </div>
</div>

@endsection
