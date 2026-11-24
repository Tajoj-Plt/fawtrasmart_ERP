@extends('onlinestore::master')

@section('title', 'تفاصيل الكوبون')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.coupons.edit'))
                <a href="{{ route('store_coupons.edit', $storeCoupon) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('store_coupons.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل الكوبون',
            'icon' => 'percent',
            'sub' => $storeCoupon->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'كوبونات الخصم' => route('store_coupons.index'),
                $storeCoupon->code => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        @php
            $isExpired = $storeCoupon->end_date && $storeCoupon->end_date < now();
            $isLimitReached = $storeCoupon->usage_limit && $storeCoupon->used_count >= $storeCoupon->usage_limit;

            [$stateClass, $stateLabel] = match(true) {
                $isExpired => ['stk-state--off', 'منتهي'],
                $isLimitReached => ['stk-state--off', 'منتهي الاستخدام'],
                !$storeCoupon->is_active => ['stk-state--off', 'غير نشط'],
                default => ['stk-state--on', 'نشط'],
            };
        @endphp

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">مرات الاستخدام</span>
                <span class="stk-metric__value">{{ $storeCoupon->used_count ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الخصومات</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($stats['total_discount'] ?? 0) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-user-check"></i></span>
                <span class="stk-metric__label">مستخدمون فريدون</span>
                <span class="stk-metric__value">{{ $stats['unique_users'] ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-activity"></i></span>
                <span class="stk-metric__label">حالة الكوبون</span>
                <span class="stk-metric__value"><span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span></span>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="coupon-code-display">
                        <code>{{ $storeCoupon->code }}</code>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2 copy-code" data-code="{{ $storeCoupon->code }}">
                        <i class="feather icon-copy"></i> نسخ الكود
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الاسم</dt>
                                <dd>{{ $storeCoupon->name ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الوصف</dt>
                                <dd>{{ $storeCoupon->description ?: '—' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>نوع الخصم</dt>
                                <dd>
                                    @if ($storeCoupon->type->value == 'percentage')
                                        <span class="badge badge-light-primary">نسبة مئوية</span>
                                    @elseif ($storeCoupon->type->value == 'fixed')
                                        <span class="badge badge-light-success">مبلغ ثابت</span>
                                    @else
                                        <span class="badge badge-light-info">شحن مجاني</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>قيمة الخصم</dt>
                                <dd>
                                    @if ($storeCoupon->type->value == 'percentage')
                                        <strong class="text-success">{{ $storeCoupon->value }}%</strong>
                                    @elseif ($storeCoupon->type->value == 'fixed')
                                        <strong class="text-success">{{ number_format($storeCoupon->value, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                                    @else
                                        <span class="text-info"><i class="feather icon-truck"></i> شحن مجاني</span>
                                    @endif
                                </dd>
                            </div>
                            @if ($storeCoupon->type->value == 'percentage' && $storeCoupon->max_discount)
                                <div class="stk-def__row">
                                    <dt>الحد الأقصى للخصم</dt>
                                    <dd>{{ number_format($storeCoupon->max_discount, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الحد الأدنى للطلب</dt>
                                <dd>
                                    @if ($storeCoupon->min_order_amount)
                                        {{ number_format($storeCoupon->min_order_amount, 2) }} {{ \App\Support\CurrencySettings::symbol() }}
                                    @else
                                        <span class="text-muted">بدون حد أدنى</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>حد الاستخدام الكلي</dt>
                                <dd>
                                    @if ($storeCoupon->usage_limit)
                                        <span class="badge badge-light-{{ $isLimitReached ? 'danger' : 'primary' }}">
                                            {{ $storeCoupon->used_count ?? 0 }} / {{ $storeCoupon->usage_limit }}
                                        </span>
                                    @else
                                        <span class="text-success">غير محدود</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>حد الاستخدام لكل مستخدم</dt>
                                <dd>{{ $storeCoupon->usage_limit_per_user ?? 1 }} مرة</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ البداية</dt>
                                <dd class="stk-num">
                                    @if ($storeCoupon->start_date)
                                        {{ $storeCoupon->start_date->format('Y-m-d H:i') }}
                                    @else
                                        <span class="text-muted">فوري</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الانتهاء</dt>
                                <dd class="stk-num">
                                    @if ($storeCoupon->end_date)
                                        <span class="{{ $isExpired ? 'text-danger' : '' }}">
                                            {{ $storeCoupon->end_date->format('Y-m-d H:i') }}
                                        </span>
                                        @if ($isExpired)
                                            <span class="badge badge-danger">منتهي</span>
                                        @else
                                            <br><small class="text-muted">{{ $storeCoupon->end_date->diffForHumans() }}</small>
                                        @endif
                                    @else
                                        <span class="text-success">دائم</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإنشاء</dt>
                                <dd class="stk-num">{{ $storeCoupon->created_at->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> آخر الاستخدامات</h6>
                @if (($storeCoupon->used_count ?? 0) > 0)
                    <a href="{{ route('store_coupons.usages', $storeCoupon) }}" class="btn btn-sm btn-primary">
                        عرض الكل <i class="feather icon-arrow-left"></i>
                    </a>
                @endif
            </div>

            @if ($storeCoupon->usages && $storeCoupon->usages->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">العميل</th>
                                <th class="text-center">رقم الفاتورة</th>
                                <th class="text-center">قيمة الخصم</th>
                                <th class="text-center">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($storeCoupon->usages->take(5) as $usage)
                                <tr>
                                    <td class="text-center stk-num">{{ $usage->id }}</td>
                                    <td class="text-center">{{ $usage->client->trade_name ?? ($usage->client->first_name ?? 'غير معروف') }}</td>
                                    <td class="text-center stk-num">#{{ $usage->invoice_id }}</td>
                                    <td class="text-center text-success"><strong>{{ number_format($usage->discount_amount ?? 0, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong></td>
                                    <td class="text-center stk-num">{{ $usage->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                    <h6 class="stk-empty__title">لم يتم استخدام هذا الكوبون بعد</h6>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // نسخ الكود
    $('.copy-code').click(function() {
        var code = $(this).data('code');
        navigator.clipboard.writeText(code).then(function() {
            toastr.success('تم نسخ الكود: ' + code);
        });
    });
});
</script>
<style>
.coupon-code-display {
    background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
    color: #fff;
    padding: 15px 30px;
    border-radius: 10px;
    font-size: 2rem;
    letter-spacing: 5px;
    display: inline-block;
    box-shadow: 0 4px 15px rgba(115, 103, 240, 0.3);
}
.coupon-code-display code {
    color: #fff;
    font-size: inherit;
}
</style>
@endsection
