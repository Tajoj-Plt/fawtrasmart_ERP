@extends('stock::master')

@section('title', 'تحركات المخزون للموظف')

@section('content')
    <div class="content-body">

        {{--
            كان عنوانها «تنبيه» بالأحمر — تُقرأ عطلاً، وهي حالة طبيعية:
            الموظف ببساطة لا مستودع مُسنَد إليه.
        --}}
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon">
                                <i class="feather icon-home"></i>
                            </div>
                            <h5 class="stk-empty__title">لا يوجد مستودع مُسنَد إليك</h5>
                            <p class="stk-empty__hint">{{ $message }}</p>
                            <a href="{{ route('stock.employee_movements') }}" class="btn btn-primary">
                                <i class="feather icon-arrow-right"></i> العودة
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
