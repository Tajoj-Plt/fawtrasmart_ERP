@extends('onlinestore::master')

@section('title', 'تفاصيل المدينة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.geo.edit'))
                <a href="{{ route('cities.edit', $city) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('cities.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل المدينة',
            'icon' => 'map-pin',
            'sub' => $city->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المدن' => route('cities.index'),
                $city->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-map-pin"></i></span>
                <span class="stk-metric__label">اسم المدينة</span>
                <span class="stk-metric__value">{{ $city->name }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-globe"></i></span>
                <span class="stk-metric__label">الدولة</span>
                <span class="stk-metric__value">{{ $city->country->name }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $city->status ? 'check-circle' : 'x-circle' }}"></i></span>
                <span class="stk-metric__label">الحالة</span>
                <span class="stk-metric__value">{{ $city->status ? 'نشطة' : 'غير نشطة' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-calendar"></i></span>
                <span class="stk-metric__label">تاريخ الإضافة</span>
                <span class="stk-metric__value stk-num">{{ $city->created_at?->format('Y-m-d') }}</span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات المدينة</h6>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>اسم المدينة</dt>
                                <dd><strong>{{ $city->name }}</strong></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الدولة</dt>
                                <dd>
                                    @if (hasPermission('online_store.geo.view'))
                                        <a href="{{ route('countries.show', $city->country) }}" class="badge badge-light-primary">
                                            <i class="feather icon-globe"></i> {{ $city->country->name }}
                                        </a>
                                    @else
                                        <span class="badge badge-light-primary">
                                            <i class="feather icon-globe"></i> {{ $city->country->name }}
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>كود الدولة</dt>
                                <dd><span class="badge badge-light-info">{{ $city->country->code }}</span></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd>
                                    <span class="stk-state {{ $city->status ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $city->status ? 'نشطة' : 'غير نشطة' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإضافة</dt>
                                <dd class="stk-num">{{ $city->created_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ $city->updated_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات إضافية</h6>
            </div>

            <div class="card-body">
                <dl class="stk-def stk-def--inline mb-3">
                    <div class="stk-def__row">
                        <dt>اسم الدولة</dt>
                        <dd>{{ $city->country->name }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>كود الدولة</dt>
                        <dd>{{ $city->country->code }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>كود الهاتف</dt>
                        <dd class="stk-num">+{{ $city->country->phone_code }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>حالة الدولة</dt>
                        <dd>
                            <span class="stk-state {{ $city->country->status ? 'stk-state--on' : 'stk-state--off' }}">
                                {{ $city->country->status ? 'نشطة' : 'غير نشطة' }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div>
                    @if (hasPermission('online_store.geo.view'))
                        <a href="{{ route('countries.show', $city->country) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="feather icon-eye"></i> عرض تفاصيل الدولة
                        </a>
                        <a href="{{ route('cities.index', ['country_id' => $city->country->id]) }}"
                            class="btn btn-sm btn-outline-primary">
                            <i class="feather icon-list"></i> عرض جميع مدن {{ $city->country->name }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
