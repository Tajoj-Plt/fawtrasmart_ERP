@extends('account::master')

@section('title', 'الشيكات الصادرة')

@section('content')
    @php
        $cheques = $payable_cheques ?? collect();
        $total = $cheques->sum('amount');
        $dueSoon = $cheques->filter(fn ($c) => $c->due_date && $c->due_date->isBetween(today(), today()->addDays(7)));
        $overdue = $cheques->filter(fn ($c) => $c->due_date && $c->due_date->isBefore(today()));
    @endphp

    <div class="content-body">

        @section('page-actions')
            {{--
                زرّ «إصدار شيك» كان خلف `check_cycle_manage_payable_checks` —
                صلاحية **غير معرَّفة في القاعدة**. و`@can` بصلاحية لا وجود
                لها تُرجع `false` دائماً، فلم يظهر الزرّ لأحدٍ قطّ ولم تكن
                هناك طريقة لإصدار شيكٍ من هذه الشاشة.

                وزرّ «دفاتر الشيكات» لم يكن موجوداً أصلاً — والشاشة قائمة
                ومساراتها معرَّفة (`check_books.index`)، لكن لا رابط إليها
                من دورة الشيكات.
            --}}
            @can('account.cheque_books.view')
                <a href="{{ route('check_books.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-book"></i> دفاتر الشيكات
                </a>
            @endcan

            @can('account.payable_cheques.create')
                <a href="{{ route('payable_cheques.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> إصدار شيك
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => 'الشيكات الصادرة',
            'icon' => 'upload',
            'sub' => 'أوراق الدفع: ما حُرِّر للموردين وحالته في دورة الصرف',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الشيكات' => null,
                'الصادرة' => null,
            ],
        ])


        @if ($cheques->count())
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-file-text"></i></span>
                    <span class="stk-metric__label">شيكات معروضة</span>
                    <span class="stk-metric__value stk-num">{{ $cheques->count() }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                    <span class="stk-metric__label">إجمالي القيمة</span>
                    <span class="stk-metric__value">@money($total)</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-clock"></i></span>
                    <span class="stk-metric__label">تستحقّ خلال أسبوع</span>
                    <span class="stk-metric__value stk-num">{{ $dueSoon->count() }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-alert-triangle"></i></span>
                    <span class="stk-metric__label">تجاوزت الاستحقاق</span>
                    <span class="stk-metric__value stk-num">{{ $overdue->count() }}</span>
                </div>
            </div>
        @endif

        {{--
            الفلاتر هنا هي التي يقرؤها `index()` فعلاً: الحالة والمدى الزمني
            والبنك. كان المعروض قبلها حقولاً أخرى تُرسَل إلى `action="#"`.
        --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['status', 'from_date', 'to_date', 'bank_id']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('payable_cheques.index') }}">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">كل الحالات</option>
                                    @foreach ($statuses ?? [] as $status)
                                        <option value="{{ $status->value }}" @selected(request('status') == $status->value)>
                                            {{ $status->getName() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="bank_id">البنك</label>
                                <select name="bank_id" id="bank_id" class="form-control">
                                    <option value="">كل البنوك</option>
                                    @foreach ($banks ?? [] as $bank)
                                        <option value="{{ $bank->id }}" @selected(request('bank_id') == $bank->id)>
                                            {{ $bank->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="from_date">استحقاق من</label>
                                <input type="date" id="from_date" name="from_date" class="form-control"
                                    value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="to_date">استحقاق إلى</label>
                                <input type="date" id="to_date" name="to_date" class="form-control"
                                    value="{{ request('to_date') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('payable_cheques.index') }}" class="btn btn-outline-secondary">
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
            <div id="results-container">
                @include('account::cheques.payable_cheques.partials.table', ['payable_cheques' => $cheques])
            </div>
        </div>
    </div>
@endsection
