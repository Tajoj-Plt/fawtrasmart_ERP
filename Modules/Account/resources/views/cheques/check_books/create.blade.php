@extends('account::master')

@section('title', 'دفتر شيكات جديد')

@section('content')
    <div class="content-body">

        {{--
            كانت الشاشتان — الإنشاء والتعديل — نسختين متطابقتين تقريباً بكل
            حقولهما وسكربتهما. صارتا تستدعيان جزئية واحدة.
        --}}
        @include('account::components.page-head', [
            'title' => 'دفتر شيكات جديد',
            'icon' => 'book',
            'sub' => 'سجّل مدى الأرقام المصرفية للدفتر — منه تُصرَف الشيكات وتُرقَّم',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'دفاتر الشيكات' => route('check_books.index'),
                'دفتر جديد' => null,
            ],
        ])

        @include('account::cheques.check_books.partials.form', [
            'action' => route('check_books.store'),
        ])
    </div>
@endsection
