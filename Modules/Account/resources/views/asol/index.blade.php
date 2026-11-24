@extends('account::master')

@section('title', 'الأصول الثابتة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @can('account.assets.create')
                <a href="{{ route('Assets.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> أصل جديد
                </a>
            @endcan
        @endsection

        @include('account::components.page-head', [
            'title' => 'الأصول الثابتة',
            'icon' => 'box',
            'sub' => 'الأصول المسجَّلة وقيمها الدفترية وطرق إهلاكها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الحسابات' => null,
                'الأصول الثابتة' => null,
            ],
        ])


        @php
            $rows = $assets ?? collect();
            $totalPurchase = $rows->sum('purchase_value');
            $totalBook = $rows->sum(fn ($a) => $a->depreciation?->book_value ?? $a->purchase_value);
            $inService = $rows->filter(fn ($a) => (int) $a->status === 1)->count();
        @endphp

        @if ($rows->count())
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-box"></i></span>
                    <span class="stk-metric__label">أصول معروضة</span>
                    <span class="stk-metric__value stk-num">{{ $rows->count() }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__label">في الخدمة</span>
                    <span class="stk-metric__value stk-num">{{ $inService }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-shopping-bag"></i></span>
                    <span class="stk-metric__label">قيمة الشراء</span>
                    <span class="stk-metric__value">@money($totalPurchase)</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-trending-down"></i></span>
                    <span class="stk-metric__label">القيمة الدفترية</span>
                    <span class="stk-metric__value">@money($totalBook)</span>
                </div>
            </div>
        @endif

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['name', 'code', 'employee_id', 'status', 'from_date_2', 'to_date_2']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form method="GET" action="{{ route('Assets.index') }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name">اسم الأصل</label>
                                <input type="text" id="name" name="name" class="form-control"
                                    value="{{ request('name') }}" placeholder="اكتب للبحث…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="code">كود الأصل</label>
                                <input type="text" id="code" name="code" class="form-control"
                                    value="{{ request('code') }}" placeholder="مثال: ORG-00012">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="employee_id">الموظف المسؤول</label>
                                <select name="employee_id" id="employee_id" class="form-control">
                                    <option value="">جميع الموظفين</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>
                                            {{ $employee->full_name ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">حالة الأصل</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="1" @selected(request('status') == '1')>في الخدمة</option>
                                    <option value="2" @selected(request('status') == '2')>تم البيع</option>
                                    <option value="3" @selected(request('status') == '3')>مهلك</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="from_date_2">من تاريخ</label>
                                <input type="date" id="from_date_2" name="from_date_2" class="form-control"
                                    value="{{ request('from_date_2') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="to_date_2">إلى تاريخ</label>
                                <input type="date" id="to_date_2" name="to_date_2" class="form-control"
                                    value="{{ request('to_date_2') }}">
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('Assets.index') }}" class="btn btn-outline-secondary">
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
                @include('account::asol.partials.table', ['assets' => $rows])
            </div>
        </div>
    </div>
@endsection
