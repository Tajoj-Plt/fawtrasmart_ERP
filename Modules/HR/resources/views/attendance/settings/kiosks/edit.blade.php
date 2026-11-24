@extends('hr::master')

@section('title', 'تعديل جهاز التسجيل')

@section('content')
<div class="hr-hints">
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'تعديل: '.$kiosk->name,
        'icon' => 'tablet',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'أجهزة التسجيل' => route('attendance.settings.kiosks.index'),
            $kiosk->name => route('attendance.settings.kiosks.show', $kiosk->id),
            'تعديل' => null,
        ],
        'actions' => '<a href="'.route('attendance.settings.kiosks.show', $kiosk->id).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="kiosk_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ التعديلات</button>',
    ])

    @include('layouts.alerts.error')

    <form id="kiosk_form" action="{{ route('attendance.settings.kiosks.update', $kiosk->id) }}" method="POST">
        @csrf
        @method('PUT')

        @include('hr::attendance.settings.kiosks._form', ['kiosk' => $kiosk])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('attendance.settings.kiosks.show', $kiosk->id) }}" class="btn btn-outline-secondary">
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
