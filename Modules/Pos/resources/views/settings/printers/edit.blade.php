{{--
    تعديل طابعة — الحقولُ في [_form.blade.php] المشترك مع الإضافة.
--}}
@extends('pos::master')

@section('title', 'تعديل الطابعة — '.$printer->name)

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.printers.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="printerForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التعديلات
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => $printer->name,
        'icon' => 'printer',
        'sub' => 'تعديلُ نوع الطابعة واتصالِها وعرضِ ورقها وربطِها بجهاز',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الطابعات' => route('pos.settings.printers.index'),
            'تعديل' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.printers.update', $printer->id) }}" method="POST" id="printerForm">
                @csrf
                @method('PUT')
                @include('pos::settings.printers._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.printers.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="printerForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ التعديلات
        </button>
    </div>
</div>

@endsection
