{{--
    تعديل جهاز كاشير — الحقولُ في [_form.blade.php] المشترك مع الإضافة.

    ومسارُ الحفظ من `route()` لا من `url('POS/Devices/update/'.$id)`: العنوانُ
    المكتوب يدوياً يكسره تغييرُ بادئةٍ في التوجيه بلا أن ينبّه أحدٌ إليه.
--}}
@extends('pos::master')

@section('title', 'تعديل الجهاز — '.$device->device_name)

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.devices.show', $device->id) }}" class="btn btn-outline-secondary">
            <i class="feather icon-eye"></i> عرض
        </a>
        <button type="submit" form="deviceForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التغييرات
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => $device->device_name,
        'icon' => 'monitor',
        'sub' => 'تعديلُ الفرع والمستودع والمسؤول وحدِّ النقد في الدرج',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الأجهزة' => route('pos.settings.devices.index'),
            'تعديل' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.devices.update', $device->id) }}" method="POST"
                  enctype="multipart/form-data" id="deviceForm">
                @csrf
                @method('PUT')
                @include('pos::settings.devices._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.devices.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="deviceForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التغييرات
        </button>
    </div>
</div>

@endsection
