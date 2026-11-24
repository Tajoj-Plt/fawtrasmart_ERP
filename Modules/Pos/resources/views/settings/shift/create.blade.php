{{--
    إضافة وردية — الحقولُ في [_form.blade.php] المشترك مع التعديل.
--}}
@extends('pos::master')

@section('title', 'إضافة وردية')

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.shift.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="shiftForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ الوردية
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => 'إضافة وردية',
        'icon' => 'clock',
        'sub' => 'وقتُ الدوام وأيّامُه ومهلةُ التأخّر والفرع',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الورديات' => route('pos.settings.shift.index'),
            'إضافة' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.shift.store') }}" method="POST"
                  enctype="multipart/form-data" id="shiftForm">
                @csrf
                @include('pos::settings.shift._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.shift.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="shiftForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ الوردية
        </button>
    </div>
</div>

@endsection
