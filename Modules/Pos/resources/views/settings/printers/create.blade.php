{{--
    إضافة طابعة — ترويسةٌ وذيلُ نموذجٍ على نظام التصميم، والحقولُ في
    [_form.blade.php] المشترك مع التعديل.
--}}
@extends('pos::master')

@section('title', 'إضافة طابعة')

@section('content')

<div class="content-body">

    @section('page-actions')
        <a href="{{ route('pos.settings.printers.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="printerForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ الطابعة
        </button>
    @endsection

    @include('pos::components.page-head', [
        'title' => 'إضافة طابعة',
        'icon' => 'printer',
        'sub' => 'نوعُ الطابعة واتصالُها وعرضُ ورقها وربطُها بجهاز',
        'crumbs' => [
            'الرئيسية' => url('/'),
            'الإعدادات' => route('pos.settings.index'),
            'الطابعات' => route('pos.settings.printers.index'),
            'إضافة' => null,
        ],
    ])

    <div class="card">
        <div class="card-body">
            <form action="{{ route('pos.settings.printers.store') }}" method="POST" id="printerForm">
                @csrf
                @include('pos::settings.printers._form')
            </form>
        </div>
    </div>

    <div class="stk-form-foot">
        <a href="{{ route('pos.settings.printers.index') }}" class="btn btn-outline-secondary">إلغاء</a>
        <button type="submit" form="printerForm" class="btn btn-primary">
            <i class="feather icon-check"></i> حفظ الطابعة
        </button>
    </div>
</div>

@endsection
