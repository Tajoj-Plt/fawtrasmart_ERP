@extends('hr::master')

@section('title', 'تعديل مكان التسجيل')

@section('content')
<div class="hr-hints">
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل: '.$geofence->name,
        'icon' => 'map-pin',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أماكن التسجيل' => route('attendance.settings.geofences.index'),
            $geofence->name => route('attendance.settings.geofences.show', $geofence->id),
            'تعديل' => null,
        ],
        'actions' => '<a href="'.route('attendance.settings.geofences.show', $geofence->id).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="geofence_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')

    <form id="geofence_form" action="{{ route('attendance.settings.geofences.update', $geofence->id) }}" method="POST">
        @csrf
        @method('PUT')

        @include('hr::attendance.settings.geofences._form', ['geofence' => $geofence])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('attendance.settings.geofences.show', $geofence->id) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ التعديلات
                </button>
            </div>
        </div>
    </form>
</div>
</div>
@include('components.hint-tips')
@endsection
