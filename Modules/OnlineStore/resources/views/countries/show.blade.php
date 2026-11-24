@extends('onlinestore::master')

@section('title', 'تفاصيل الدولة')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.geo.edit'))
                <a href="{{ route('countries.edit', $country) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('countries.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل الدولة',
            'icon' => 'globe',
            'sub' => $country->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الدول' => route('countries.index'),
                $country->name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-map-pin"></i></span>
                <span class="stk-metric__label">إجمالي المدن</span>
                <span class="stk-metric__value">{{ $stats['total_cities'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__label">مدن نشطة</span>
                <span class="stk-metric__value">{{ $stats['active_cities'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                <span class="stk-metric__label">مدن غير نشطة</span>
                <span class="stk-metric__value">{{ $stats['inactive_cities'] }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $country->status ? 'check-circle' : 'x-circle' }}"></i></span>
                <span class="stk-metric__label">حالة الدولة</span>
                <span class="stk-metric__value">{{ $country->status ? 'نشطة' : 'غير نشطة' }}</span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات الدولة</h6>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>اسم الدولة</dt>
                                <dd><strong>{{ $country->name }}</strong></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>كود الدولة</dt>
                                <dd><span class="badge badge-light-info">{{ $country->code }}</span></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>كود الهاتف</dt>
                                <dd class="stk-num">+{{ $country->phone_code }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd>
                                    <span class="stk-state {{ $country->status ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $country->status ? 'نشطة' : 'غير نشطة' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>دولة افتراضية</dt>
                                <dd>
                                    @if ($country->is_default)
                                        <span class="stk-state stk-state--on"><i class="feather icon-star"></i> افتراضية</span>
                                    @else
                                        <span class="stk-state stk-state--off"><i class="feather icon-minus"></i> غير افتراضية</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإضافة</dt>
                                <dd class="stk-num">{{ $country->created_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ $country->updated_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> المدن</h6>
                @if (hasPermission('online_store.geo.create'))
                    <a href="{{ route('cities.create', ['country_id' => $country->id]) }}" class="btn btn-sm btn-primary">
                        <i class="feather icon-plus"></i> إضافة مدينة
                    </a>
                @endif
            </div>

            <div class="card-body">
                @if ($country->cities && $country->cities->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 60px;">#</th>
                                    <th>اسم المدينة</th>
                                    <th class="text-center">الحالة</th>
                                    @if (hasPermission(['online_store.geo.view', 'online_store.geo.edit']))
                                        <th class="text-center" style="width: 80px;">الإجراءات</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($country->cities as $city)
                                    <tr>
                                        <td class="text-center stk-num">{{ $loop->iteration }}</td>
                                        <td>{{ $city->name }}</td>
                                        <td class="text-center">
                                            <span class="stk-state {{ $city->status ? 'stk-state--on' : 'stk-state--off' }}">
                                                {{ $city->status ? 'نشطة' : 'غير نشطة' }}
                                            </span>
                                        </td>
                                        @if (hasPermission(['online_store.geo.view', 'online_store.geo.edit']))
                                            <td class="text-center">
                                                @if (hasPermission('online_store.geo.view'))
                                                    <a href="{{ route('cities.show', $city) }}" class="btn btn-sm btn-icon"
                                                        data-bs-toggle="tooltip" title="عرض">
                                                        <i class="feather icon-eye"></i>
                                                    </a>
                                                @endif
                                                @if (hasPermission('online_store.geo.edit'))
                                                    <a href="{{ route('cities.edit', $city) }}" class="btn btn-sm btn-icon"
                                                        data-bs-toggle="tooltip" title="تعديل">
                                                        <i class="feather icon-edit-2"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="stk-empty">
                        <span class="stk-empty__icon"><i class="feather icon-map-pin"></i></span>
                        <h6 class="stk-empty__title">لا توجد مدن</h6>
                        <p class="stk-empty__hint">لا توجد مدن مسجلة لهذه الدولة بعد.</p>
                        @if (hasPermission('online_store.geo.create'))
                            <a href="{{ route('cities.create', ['country_id' => $country->id]) }}" class="btn btn-primary btn-sm">
                                <i class="feather icon-plus"></i> إضافة مدينة
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
