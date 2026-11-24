@extends('account::master')

@section('title', 'الخزائن والحسابات البنكية')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('treasury.create_account_bank') }}" class="btn btn-outline-secondary">
                <i class="feather icon-credit-card"></i> حساب بنكي
            </a>
            <a href="{{ route('treasury.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> خزينة جديدة
            </a>
        @endsection

        @include('account::components.page-head', [
            'title' => 'الخزائن والحسابات البنكية',
            'icon' => 'briefcase',
            'sub' => 'أرصدة الخزائن والبنوك وحركاتها والتحويلات بينها',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المالية' => null,
                'الخزائن' => null,
            ],
        ])

        @php
            $rows = $treasuries ?? collect();
            $activeCount = $rows->filter(fn($t) => $t->is_active == 1)->count();
            $bankCount = $rows->filter(fn($t) => $t->type_accont != 0)->count();
            $totalBalance = $rows->sum('balance');
        @endphp

        @if ($rows->count())
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-briefcase"></i></span>
                    <span class="stk-metric__label">خزائن معروضة</span>
                    <span class="stk-metric__value stk-num">{{ $rows->count() }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                    <span class="stk-metric__label">نشطة</span>
                    <span class="stk-metric__value stk-num">{{ $activeCount }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-credit-card"></i></span>
                    <span class="stk-metric__label">حسابات بنكية</span>
                    <span class="stk-metric__value stk-num">{{ $bankCount }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                    <span class="stk-metric__label">مجموع الأرصدة</span>
                    <span class="stk-metric__value">@money($totalBalance)</span>
                </div>
            </div>
        @endif

        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['keywords', 'category', 'status', 'currency', 'branch']))
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form class="form" method="GET" action="{{ route('treasury.index') }}">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="keywords">بحث</label>
                                <input type="text" class="form-control" id="keywords" name="keywords"
                                    value="{{ request('keywords') }}" placeholder="الاسم أو الكود…">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="category">النوع</label>
                                <select name="category" class="form-control" id="category">
                                    <option value="">كل الأنواع</option>
                                    <option value="1" @selected(request('category') == '1')>حساب بنكي</option>
                                    <option value="2" @selected(request('category') == '2')>خزينة</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select class="form-control" name="status" id="status">
                                    <option value="">كل الحالات</option>
                                    <option value="1" @selected(request('status') == '1')>نشط</option>
                                    <option value="0" @selected(request('status') === '0')>غير نشط</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                @include('account::components.currency-select', [
                                    'name' => 'currency',
                                    'label' => 'العملة',
                                    'placeholder' => 'كل العملات',
                                    'selected' => request('currency', \App\Support\CurrencySettings::code()),
                                ])
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="branch">الفرع</label>
                                <select class="form-control" name="branch" id="branch">
                                    <option value="">جميع الفروع</option>
                                    @foreach ($branches ?? [] as $branch)
                                        <option value="{{ $branch->id }}" @selected(request('branch') == $branch->id)>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="stk-filters__foot">
                        <a href="{{ route('treasury.index') }}" class="btn btn-outline-secondary">
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
                @include('account::finance.treasury.partials.table', ['treasuries' => $rows])
            </div>
        </div>
    </div>
@endsection
