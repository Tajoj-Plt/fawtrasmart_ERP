@extends('account::master')

@section('title', 'تعديل دفتر الشيكات')

@section('content')
    <div class="content-body">

        @include('account::components.page-head', [
            'title' => 'تعديل دفتر الشيكات',
            'icon' => 'edit-2',
            'sub' => 'الدفتر رقم ' . $checkbook->cheque_book_number,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'دفاتر الشيكات' => route('check_books.index'),
                'تعديل' => null,
            ],
        ])

        @include('account::cheques.check_books.partials.form', [
            'action' => route('check_books.update', $checkbook->id),
            'book' => $checkbook,
            'cheque_book_number' => $checkbook->cheque_book_number,
        ])
    </div>
@endsection
