@extends('account::master')

@section('title', 'دفاتر الشيكات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @can('account.cheque_books.create')
                <a href="{{ route('check_books.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> دفتر جديد
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => 'دفاتر الشيكات',
            'icon' => 'book',
            'sub' => 'الدفاتر المسجَّلة ومدياتها المصرفية وحالتها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الشيكات' => null,
                'دفاتر الشيكات' => null,
            ],
        ])


        @php
            $books = $check_books ?? collect();
            $activeBooks = $books->filter(fn ($b) => (int) $b->status === 1)->count();
            $totalLeaves = $books->sum(fn ($b) => max(0, ($b->end_serial_number - $b->start_serial_number) + 1));
        @endphp

        @if ($books->count())
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-book"></i></span>
                    <span class="stk-metric__label">دفاتر معروضة</span>
                    <span class="stk-metric__value stk-num">{{ $books->count() }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__label">نشطة</span>
                    <span class="stk-metric__value stk-num">{{ $activeBooks }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-layers"></i></span>
                    <span class="stk-metric__label">إجمالي الأوراق</span>
                    <span class="stk-metric__value stk-num">{{ number_format($totalLeaves) }}</span>
                </div>
            </div>
        @endif

        {{--
            كان نموذج الفلترة `<form action="#">` بحقولٍ لا يقرؤها المتحكّم —
            «القسم» و«الفرع» بمعرّف `searchDepartment` مكرَّر مرّتين، وبلا
            `name` أصلاً في بعضها. أي أنّ الضغط على «بحث» يُعيد الصفحة كما هي.
            الحقول هنا كلّها مقروءة في `index()` فعلاً.
        --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['keywords', 'status', 'currency', 'from_date', 'to_date']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('check_books.index') }}">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="form-group">
                                <label for="keywords">رقم الدفتر أو البنك</label>
                                <input type="text" id="keywords" name="keywords" class="form-control"
                                    value="{{ request('keywords') }}" placeholder="اكتب للبحث…">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">الكل</option>
                                    <option value="1" @selected(request('status') === '1')>نشط</option>
                                    <option value="0" @selected(request('status') === '0')>غير نشط</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                @include('account::components.currency-select', [
                                    'name' => 'currency',
                                    'label' => 'العملة',
                                    'placeholder' => 'كل العملات',
                                    'selected' => request('currency'),
                                ])
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="from_date">من تاريخ</label>
                                <input type="date" id="from_date" name="from_date" class="form-control"
                                    value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="to_date">إلى تاريخ</label>
                                <input type="date" id="to_date" name="to_date" class="form-control"
                                    value="{{ request('to_date') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('check_books.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stk-table-card">
            @include('account::cheques.check_books.partials.table', ['check_books' => $books])
        </div>
    </div>
@endsection
