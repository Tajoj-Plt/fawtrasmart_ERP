@extends('hr::master')

@section('title', 'إضافة جهاز تسجيل')

@section('content')
<div class="hr-hints">
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => 'إضافة جهاز تسجيل',
        'icon' => 'tablet',
        'crumbs' => ['الرئيسية' => url('/'), 'أجهزة التسجيل' => route('attendance.settings.kiosks.index'), 'إضافة' => null],
        'actions' => '<a href="'.route('attendance.settings.kiosks.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
            .'<button type="submit" form="kiosk_form" class="btn btn-primary"><i class="feather icon-check"></i> حفظ الجهاز</button>',
    ])

    @include('layouts.alerts.error')

    <div class="alert alert-info">
        <i class="feather icon-info"></i>
        جهاز التسجيل تابلت أو حاسوب ثابت عند باب الفرع، يمسح عليه الموظفون بطاقاتهم.
        بعد الحفظ يظهر لك <strong>رمز الربط</strong> — تدخله مرة واحدة على الجهاز نفسه.
    </div>

    <form id="kiosk_form" action="{{ route('attendance.settings.kiosks.store') }}" method="POST">
        @csrf

        @include('hr::attendance.settings.kiosks._form', ['kiosk' => null])

        <div class="card">
            <div class="stk-form-foot">
                <a href="{{ route('attendance.settings.kiosks.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> حفظ الجهاز
                </button>
            </div>
        </div>
    </form>
</div>
</div>
@include('components.hint-tips')
@endsection
