@extends('onlinestore::master')

@section('title', 'سجل استخدام الكوبون')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('store_coupons.show', $storeCoupon) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع للكوبون
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'سجل استخدام الكوبون',
            'icon' => 'list',
            'sub' => $storeCoupon->code,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'كوبونات الخصم' => route('store_coupons.index'),
                $storeCoupon->code => route('store_coupons.show', $storeCoupon),
                'سجل الاستخدام' => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="coupon-code-display">
                            <code>{{ $storeCoupon->code }}</code>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <strong>نوع الخصم:</strong>
                        @if ($storeCoupon->type->value == 'percentage')
                            <span class="badge badge-light-primary">{{ $storeCoupon->value }}%</span>
                        @elseif ($storeCoupon->type->value == 'fixed')
                            <span class="badge badge-light-success">{{ number_format($storeCoupon->value, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</span>
                        @else
                            <span class="badge badge-light-info">شحن مجاني</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <strong>الاستخدام:</strong>
                        <span class="badge badge-light-primary">
                            {{ $storeCoupon->used_count ?? 0 }} / {{ $storeCoupon->usage_limit ?: '∞' }}
                        </span>
                    </div>
                    <div class="col-md-3">
                        <strong>الحالة:</strong>
                        @if ($storeCoupon->is_active && (!$storeCoupon->end_date || $storeCoupon->end_date > now()))
                            <span class="stk-state stk-state--on">نشط</span>
                        @else
                            <span class="stk-state stk-state--off">غير نشط</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @php
            $totalDiscount = $usages->sum('discount_amount');
            $totalOrders = $usages->count();
        @endphp

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-users"></i></span>
                <span class="stk-metric__label">إجمالي الاستخدامات</span>
                <span class="stk-metric__value">{{ $usages->total() ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الخصومات</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($totalDiscount ?? 0) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">عدد الطلبات</span>
                <span class="stk-metric__value">{{ $totalOrders ?? 0 }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">متوسط الخصم</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::format($totalOrders > 0 ? $totalDiscount / $totalOrders : 0) }}</span>
            </div>
        </div>

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> سجل الاستخدامات</h6>
                <span class="badge badge-light-primary">{{ $usages->total() }} استخدام</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th class="text-center">العميل</th>
                            <th class="text-center">رقم الفاتورة</th>
                            <th class="text-center">قيمة الخصم</th>
                            <th class="text-center">التاريخ</th>
                            <th class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usages as $index => $usage)
                            <tr>
                                <td class="text-center stk-num">{{ $usages->firstItem() + $index }}</td>
                                <td class="text-center">
                                    <strong>{{ $usage->client->trade_name ?? ($usage->client->first_name ?? 'غير معروف') }}</strong>
                                </td>
                                <td class="text-center stk-num">
                                    <a href="#" class="text-primary">#{{ $usage->invoice_id }}</a>
                                </td>
                                <td class="text-center">
                                    <strong class="text-success">{{ number_format($usage->discount_amount ?? 0, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</strong>
                                </td>
                                <td class="text-center stk-num">
                                    {{ $usage->created_at->format('Y-m-d') }}
                                    <br>
                                    <small class="text-muted">{{ $usage->created_at->format('H:i') }}</small>
                                </td>
                                <td class="text-center">
                                    <a href="#" class="btn btn-sm btn-icon" title="عرض الفاتورة">
                                        <i class="feather icon-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                        <h6 class="stk-empty__title">لم يتم استخدام هذا الكوبون بعد</h6>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($usages->count() > 0)
                        <tfoot>
                            <tr class="stk-total-row">
                                <td colspan="3" class="text-center">الإجمالي</td>
                                <td class="text-center text-success">{{ number_format($totalDiscount ?? 0, 2) }} {{ \App\Support\CurrencySettings::symbol() }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @include('onlinestore::partials._smart-pagination', ['paginator' => $usages, 'label' => 'نتيجة', 'appends' => request()->query()])
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // تهيئة Select2
    if ($.fn.select2) {
        $('.select2').select2({
            dir: 'rtl',
            language: {
                noResults: function() {
                    return "لا توجد نتائج";
                }
            },
            allowClear: true,
            width: '100%'
        });
    }
});
</script>
<style>
.coupon-code-display {
    background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 1.5rem;
    letter-spacing: 3px;
    display: inline-block;
    box-shadow: 0 4px 15px rgba(115, 103, 240, 0.3);
}
.coupon-code-display code {
    color: #fff;
    font-size: inherit;
}
</style>
@endsection
