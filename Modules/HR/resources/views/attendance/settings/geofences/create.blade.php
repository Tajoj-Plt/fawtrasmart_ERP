@extends('hr::master')

@section('title', 'إضافة مكان تسجيل')

@section('content')
<div class="hr-hints">
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة مكان تسجيل',
        'icon' => 'map-pin',
        'crumbs' => ['الرئيسية' => url('/'), 'أماكن التسجيل' => route('attendance.settings.geofences.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('attendance.settings.geofences.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="geofence_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ المكان</button>',
    ])

    @include('layouts.alerts.error')

    <form id="geofence_form" action="{{ route('attendance.settings.geofences.store') }}" method="POST">
        @csrf

        @include('hr::attendance.settings.geofences._form', ['geofence' => null])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('attendance.settings.geofences.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ المكان
                </button>
            </div>
        </div>
    </form>
</div>
</div>
@include('components.hint-tips')
@endsection
