@extends('onlinestore::master')

@section('title', 'تفاصيل طريقة الشحن')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.shipping_methods.edit'))
                <a href="{{ route('shipping_methods.edit', $shippingMethod) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('shipping_methods.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => $shippingMethod->name,
            'icon' => 'truck',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'طرق الشحن' => route('shipping_methods.index'),
                $shippingMethod->name => null,
            ],
        ])

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-truck"></i> معلومات طريقة الشحن</h6>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        @if ($shippingMethod->logo)
                            <img src="{{ asset($shippingMethod->logo) }}" alt="{{ $shippingMethod->name }}"
                                class="img-fluid rounded" style="max-height: 150px;">
                        @else
                            <div class="avatar bg-light-primary mx-auto" style="width: 100px; height: 100px;">
                                <span class="avatar-content" style="font-size: 40px;"><i class="feather icon-truck"></i></span>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>الاسم</dt>
                                        <dd>{{ $shippingMethod->name }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الكود</dt>
                                        <dd class="stk-num">{{ $shippingMethod->code ?: '-' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>النوع</dt>
                                        <dd><span class="badge badge-light-primary">{{ $shippingMethod->type->label() }}</span></dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>التكلفة</dt>
                                        <dd class="stk-num text-success">{{ \App\Support\CurrencySettings::format($shippingMethod->cost) }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الحد الأدنى للطلب</dt>
                                        <dd>{{ $shippingMethod->min_order_amount ? \App\Support\CurrencySettings::format($shippingMethod->min_order_amount) : '-' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="col-md-6">
                                <dl class="stk-def">
                                    <div class="stk-def__row">
                                        <dt>حد الشحن المجاني</dt>
                                        <dd>{{ $shippingMethod->free_shipping_threshold ? \App\Support\CurrencySettings::format($shippingMethod->free_shipping_threshold) : '-' }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>يدعم الشحن السريع</dt>
                                        <dd>
                                            @if ($shippingMethod->supports_express ?? true)
                                                <span class="badge badge-light-success"><i class="feather icon-zap"></i> يدعم الشحن السريع</span>
                                            @else
                                                <span class="badge badge-light-secondary">لا يدعم الشحن السريع</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الوقت المتوقع</dt>
                                        <dd>{{ $shippingMethod->estimated_delivery_text }}</dd>
                                    </div>
                                    <div class="stk-def__row">
                                        <dt>الحالة</dt>
                                        <dd><span class="stk-state {{ $shippingMethod->is_active ? 'stk-state--on' : 'stk-state--off' }}">{{ $shippingMethod->is_active ? 'نشط' : 'غير نشط' }}</span></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الحساب المرتبط</dt>
                                <dd>
                                    @if ($shippingMethod->account)
                                        <span class="badge badge-light-primary">
                                            <i class="feather icon-credit-card"></i>
                                            {{ $shippingMethod->account->code }} - {{ $shippingMethod->account->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>رصيد الحساب</dt>
                                <dd>
                                    @if ($shippingMethod->account)
                                        <span class="{{ $accountBalance > 0 ? 'text-danger' : ($accountBalance < 0 ? 'text-success' : 'text-muted') }} font-weight-bold stk-num">
                                            {{ number_format(abs($accountBalance), 2) }} {{ \App\Support\CurrencySettings::symbol() }}
                                            @if ($accountBalance > 0)
                                                <small class="d-block text-muted">(مستحق للشركة)</small>
                                            @elseif ($accountBalance < 0)
                                                <small class="d-block text-muted">(مدفوع مقدماً)</small>
                                            @else
                                                <small class="d-block text-muted">(متوازن)</small>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </dd>
                            </div>
                            @if ($shippingMethod->description)
                                <div class="stk-def__row">
                                    <dt>الوصف</dt>
                                    <dd>{{ $shippingMethod->description }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
