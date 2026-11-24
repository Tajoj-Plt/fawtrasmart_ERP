@extends('onlinestore::master')

@section('title', 'إحصائيات الطلبات')

@section('content')
    <div class="content-body">

        @section('page-actions')
            <a href="{{ route('online_orders.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة للطلبات
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'إحصائيات الطلبات',
            'icon' => 'bar-chart-2',
            'sub' => 'مؤشرات أداء الطلبات والمبيعات في المتجر الإلكتروني',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الطلبات' => route('online_orders.index'),
                'الإحصائيات' => null,
            ],
        ])

        <div class="card stk-filters">
            <div class="card-body">
                <form method="GET" action="{{ route('online_orders.statistics') }}" class="d-flex align-items-center flex-wrap gap-2">
                    <label class="mb-0 me-2">الفترة الزمنية:</label>
                    <div class="btn-group">
                        <a href="{{ route('online_orders.statistics', ['period' => 'today']) }}"
                            class="btn {{ $period == 'today' ? 'btn-primary' : 'btn-outline-primary' }}">
                            اليوم
                        </a>
                        <a href="{{ route('online_orders.statistics', ['period' => 'week']) }}"
                            class="btn {{ $period == 'week' ? 'btn-primary' : 'btn-outline-primary' }}">
                            هذا الأسبوع
                        </a>
                        <a href="{{ route('online_orders.statistics', ['period' => 'month']) }}"
                            class="btn {{ $period == 'month' ? 'btn-primary' : 'btn-outline-primary' }}">
                            هذا الشهر
                        </a>
                        <a href="{{ route('online_orders.statistics', ['period' => 'year']) }}"
                            class="btn {{ $period == 'year' ? 'btn-primary' : 'btn-outline-primary' }}">
                            هذه السنة
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-shopping-cart"></i></span>
                <span class="stk-metric__label">إجمالي الطلبات</span>
                <span class="stk-metric__value">{{ number_format($stats['total_orders']) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">إجمالي الإيرادات ({{ \App\Support\CurrencySettings::symbol() }})</span>
                <span class="stk-metric__value">{{ number_format($stats['total_revenue'], 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-trending-up"></i></span>
                <span class="stk-metric__label">متوسط قيمة الطلب ({{ \App\Support\CurrencySettings::symbol() }})</span>
                <span class="stk-metric__value">{{ number_format($stats['average_order_value'] ?? 0, 2) }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-percent"></i></span>
                <span class="stk-metric__label">نسبة الإكمال</span>
                <span class="stk-metric__value">{{ $stats['completion_rate'] }}%</span>
            </div>
        </div>

        <div class="row">
            <!-- توزيع الطلبات حسب الحالة -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-pie-chart"></i> الطلبات حسب الحالة</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $statusLabels = [
                                'pending' => ['label' => 'قيد الانتظار', 'color' => 'warning'],
                                'confirmed' => ['label' => 'مؤكد', 'color' => 'info'],
                                'processing' => ['label' => 'جاري التجهيز', 'color' => 'primary'],
                                'shipped' => ['label' => 'تم الشحن', 'color' => 'info'],
                                'out_for_delivery' => ['label' => 'في الطريق', 'color' => 'info'],
                                'delivered' => ['label' => 'تم التسليم', 'color' => 'success'],
                                'cancelled' => ['label' => 'ملغي', 'color' => 'danger'],
                                'returned' => ['label' => 'مرتجع', 'color' => 'secondary'],
                                'refunded' => ['label' => 'مسترد', 'color' => 'dark'],
                            ];
                            $totalOrders = $stats['orders_by_status']->sum();
                        @endphp

                        @forelse ($stats['orders_by_status'] as $status => $count)
                            @php
                                $statusInfo = $statusLabels[$status] ?? ['label' => $status, 'color' => 'secondary'];
                                $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge badge-light-{{ $statusInfo['color'] }}">{{ $statusInfo['label'] }}</span>
                                    <span class="font-weight-bold stk-num">{{ $count }} ({{ $percentage }}%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-{{ $statusInfo['color'] }}" role="progressbar" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                <h6 class="stk-empty__title">لا توجد بيانات</h6>
                                <p class="stk-empty__hint">لا توجد طلبات في هذه الفترة.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- توزيع الطلبات حسب طريقة الدفع -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-credit-card"></i> الطلبات حسب طريقة الدفع</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $paymentLabels = [
                                'cod' => ['label' => 'الدفع عند الاستلام', 'color' => 'warning', 'icon' => 'dollar-sign'],
                                'card' => ['label' => 'بطاقة ائتمان', 'color' => 'primary', 'icon' => 'credit-card'],
                                'wallet' => ['label' => 'المحفظة', 'color' => 'success', 'icon' => 'briefcase'],
                                'bank_transfer' => ['label' => 'تحويل بنكي', 'color' => 'info', 'icon' => 'home'],
                            ];
                            $totalPayments = $stats['orders_by_payment']->sum();
                        @endphp

                        @forelse ($stats['orders_by_payment'] as $method => $count)
                            @php
                                $paymentInfo = $paymentLabels[$method] ?? ['label' => $method, 'color' => 'secondary', 'icon' => 'credit-card'];
                                $percentage = $totalPayments > 0 ? round(($count / $totalPayments) * 100, 1) : 0;
                            @endphp
                            <div class="mb-3 d-flex align-items-center">
                                <span class="badge badge-light-{{ $paymentInfo['color'] }} me-2">
                                    <i class="feather icon-{{ $paymentInfo['icon'] }}"></i>
                                </span>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <span>{{ $paymentInfo['label'] }}</span>
                                        <span class="font-weight-bold stk-num">{{ $count }}</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 6px;">
                                        <div class="progress-bar bg-{{ $paymentInfo['color'] }}" role="progressbar" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-inbox"></i></span>
                                <h6 class="stk-empty__title">لا توجد بيانات</h6>
                                <p class="stk-empty__hint">لا توجد طلبات في هذه الفترة.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- نسب الأداء -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="circular-progress success" data-value="{{ $stats['completion_rate'] }}">
                            <div class="progress-value">
                                <h2>{{ $stats['completion_rate'] }}%</h2>
                            </div>
                        </div>
                        <h5 class="mt-2">نسبة إكمال الطلبات</h5>
                        <p class="text-muted">الطلبات التي تم تسليمها بنجاح</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="circular-progress danger" data-value="{{ $stats['cancellation_rate'] }}">
                            <div class="progress-value">
                                <h2>{{ $stats['cancellation_rate'] }}%</h2>
                            </div>
                        </div>
                        <h5 class="mt-2">نسبة الإلغاء</h5>
                        <p class="text-muted">الطلبات التي تم إلغاؤها</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('styles')
<style>
    .circular-progress {
        position: relative;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .circular-progress.success {
        background: conic-gradient(#28c76f calc(var(--value) * 1%), #e9ecef 0);
    }
    .circular-progress.danger {
        background: conic-gradient(#ea5455 calc(var(--value) * 1%), #e9ecef 0);
    }
    .circular-progress::before {
        content: '';
        position: absolute;
        width: 120px;
        height: 120px;
        background: var(--app-surface, #fff);
        border-radius: 50%;
    }
    .progress-value {
        position: relative;
        z-index: 1;
    }
    .progress-value h2 {
        margin: 0;
        font-size: 1.5rem;
    }
</style>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Set CSS variable for circular progress
    $('.circular-progress').each(function() {
        var value = $(this).data('value') || 0;
        $(this).css('--value', value);
    });
});
</script>
@endsection
