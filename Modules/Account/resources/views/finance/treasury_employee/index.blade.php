@extends('account::master')

@section('title', 'خزائن الموظفين')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('treasury_employee.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> تعيين خزينة لموظف
            </a>
        @endsection

        @include('account::components.page-head', [
            'title' => 'خزائن الموظفين',
            'icon' => 'users',
            'sub' => 'الخزينة الافتراضية لكل موظف — منها يُصرَف وإليها يُقبَض حين لا يختار غيرها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الإعدادات المالية' => route('finance_settings.index'),
                'خزائن الموظفين' => null,
            ],
        ])

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['employee_id', 'treasury_id']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form class="form" method="GET" action="{{ route('finance_settings.treasury_employee') }}">
                    <div class="row">
                        <div class="col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="employee_id">الموظف</label>
                                <select class="form-control select2" name="employee_id" id="employee_id">
                                    <option value="">جميع الموظفين</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>
                                            {{ $employee->full_name ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="treasury_id">الخزينة الافتراضية</label>
                                <select class="form-control select2" name="treasury_id" id="treasury_id">
                                    <option value="">جميع الخزائن</option>
                                    @foreach ($treasuries as $treasury)
                                        <option value="{{ $treasury->id }}" @selected(request('treasury_id') == $treasury->id)>
                                            {{ $treasury->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('finance_settings.treasury_employee') }}" class="btn btn-outline-secondary">
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
                @if (!empty($treasury_employees))
                    @include('account::finance.treasury_employee.partials.table', [
                        'treasury_employees' => $treasury_employees,
                    ])
                @else
                    <div class="stk-empty">
                        <span class="stk-empty__icon"><i class="feather icon-users"></i></span>
                        <h6 class="stk-empty__title">لا بيانات</h6>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
