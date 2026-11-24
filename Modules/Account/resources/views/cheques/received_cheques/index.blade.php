@extends('account::master')

@section('title', 'الشيكات المستلمة')

@section('content')
    @php
        $cheques = $received_cheques ?? collect();
        $total = $cheques->sum('amount');
        $dueSoon = $cheques->filter(fn ($c) => $c->due_date && $c->due_date->isBetween(today(), today()->addDays(7)));
        $overdue = $cheques->filter(fn ($c) => $c->due_date && $c->due_date->isBefore(today()));
    @endphp

    <div class="content-body">

        @section('page-actions')
            @can('account.received_cheques.create')
                <a href="{{ route('received_cheques.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> استلام شيك
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => 'الشيكات المستلمة',
            'icon' => 'download',
            'sub' => 'أوراق القبض: ما استُلم من العملاء وحالته في دورة التحصيل',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الشيكات' => null,
                'المستلمة' => null,
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
            كان النموذج `<form action="#">` بحقول لا يقرؤها المتحكّم — «البحث
            بواسطة الموظف» و«القسم» و«الفرع» بمعرّف `searchDepartment` مكرَّر —
            بينما `index()` يقرأ فعلاً `status` و`from_date` و`to_date`. أي أنّ
            الفلاتر المعروضة ليست الفلاتر العاملة.
        --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['status', 'from_date', 'to_date']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('received_cheques.index') }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
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

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="from_date">استحقاق من</label>
                                <input type="date" id="from_date" name="from_date" class="form-control"
                                    value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="to_date">استحقاق إلى</label>
                                <input type="date" id="to_date" name="to_date" class="form-control"
                                    value="{{ request('to_date') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('received_cheques.index') }}" class="btn btn-outline-secondary">
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
                @include('account::cheques.received_cheques.partials.table', ['received_cheques' => $cheques])
            </div>
        </div>
    </div>
@endsection
