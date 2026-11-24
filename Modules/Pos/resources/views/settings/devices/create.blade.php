{{--
    إضافة جهاز كاشير — الحقولُ في [_form.blade.php] المشترك مع التعديل.
--}}
@extends('pos::master')

@section('title', 'إضافة جهاز كاشير')

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.devices.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="deviceForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ الجهاز
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => 'إضافة جهاز كاشير',
        'icon' => 'monitor',
        'sub' => 'الفرعُ والمستودعُ والمسؤولُ وحدُّ النقد في الدرج',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الأجهزة' => route('pos.settings.devices.index'),
            'إضافة' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.devices.store') }}" method="POST"
                  enctype="multipart/form-data" id="deviceForm">
                @csrf
                @include('pos::settings.devices._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.devices.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="deviceForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ الجهاز
        </button>
    </div>
</div>

@endsection
