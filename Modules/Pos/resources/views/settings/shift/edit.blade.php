{{--
    تعديل وردية — الحقولُ في [_form.blade.php] المشترك مع الإضافة.
--}}
@extends('pos::master')

@section('title', 'تعديل الوردية — '.$shift->name)

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.shift.show', $shift->id) }}" class="btn btn-outline-secondary">
            <i class="feather icon-eye"></i> عرض
        </a>
        <button type="submit" form="shiftForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التغييرات
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => $shift->name,
        'icon' => 'clock',
        'sub' => 'تعديلُ وقت الدوام وأيّامه ومهلةِ التأخّر والفرع',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الورديات' => route('pos.settings.shift.index'),
            'تعديل' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.shift.update', $shift->id) }}" method="POST"
                  enctype="multipart/form-data" id="shiftForm">
                @csrf
                @method('PUT')
                @include('pos::settings.shift._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.shift.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="shiftForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التغييرات
        </button>
    </div>
</div>

@endsection
