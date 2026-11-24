@extends('stock::master')

@section('title')
لوحة تحكم المخزون
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/stock-dashboard.css') }}">
<style>
    .stat-card { transition: transform 0.3s; }
    .stat-card:hover { transform: translateY(-5px); }
    .small-table { font-size: 0.85rem; }
    .badge-icon { font-size: 0.75rem; margin-right: 3px; }
</style>
@endpush

@section('content')
<div class="content-body">
    <section id="stock-dashboard">
        
        {{-- الصف الأول: بطاقة الترحيب --}}
        <div class="row">
            <div class="col-lg-8 col-md-12">
                <div class="card bg-analytics text-white">
                    <div class="card-body text-center">
                        <div class="avatar avatar-xl bg-primary shadow mt-0" style="margin: 0 auto 1rem;">
                            <i class="feather icon-package white font-large-1"></i>
                        </div>
                        <h1 class="mb-2 text-white">مرحباً بك في لوحة المخزون</h1>
                        <p class="m-auto w-75">تابع مؤشرات أداء مخزونك وإحصائياتها المهمة</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="feather icon-trending-up text-success"></i> نمو المخزون</h5>
                        <div class="mb-2">
                            <span class="text-muted">قيمة المخزون:</span>
                            <strong class="float-right text-success">{{ number_format($currentStockValue, 2) }} ر.س</strong>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">نسبة النمو:</span>
                            <strong class="float-right {{ $stockValueGrowth >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($stockValueGrowth, 2) }}%
                            </strong>
                        </div>
                        <div>
                            <span class="text-muted">معدل الدوران:</span>
                            <strong class="float-right text-info">{{ number_format($inventoryTurnoverRate, 2) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الثاني: إحصائيات المنتجات التفصيلية --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-box text-primary"></i> إحصائيات المنتجات</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="text-center stat-card">
                                    <div class="avatar bg-rgba-primary mx-auto mb-2">
                                        <i class="feather icon-box text-primary font-large-1"></i>
                                    </div>
                                    <h3 class="font-weight-bolder">{{ $totalProducts }}</h3>
                                    <p class="text-muted mb-0">إجمالي المنتجات</p>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="text-center stat-card">
                                    <div class="avatar bg-rgba-success mx-auto mb-2">
                                        <i class="feather icon-check-circle text-success font-large-1"></i>
                                    </div>
                                    <h3 class="font-weight-bolder text-success">{{ $activeProducts }}</h3>
                                    <p class="text-muted mb-0">منتجات نشطة</p>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="text-center stat-card">
                                    <div class="avatar bg-rgba-danger mx-auto mb-2">
                                        <i class="feather icon-x-circle text-danger font-large-1"></i>
                                    </div>
                                    <h3 class="font-weight-bolder text-danger">{{ $outOfStockProducts }}</h3>
                                    <p class="text-muted mb-0">منتجات نفذت</p>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="text-center stat-card">
                                    <div class="avatar bg-rgba-warning mx-auto mb-2">
                                        <i class="feather icon-alert-triangle text-warning font-large-1"></i>
                                    </div>
                                    <h3 class="font-weight-bolder text-warning">{{ $lowStockProducts }}</h3>
                                    <p class="text-muted mb-0">تنبيهات مخزون</p>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="text-center stat-card">
                                    <div class="avatar bg-rgba-info mx-auto mb-2">
                                        <i class="feather icon-plus-circle text-info font-large-1"></i>
                                    </div>
                                    <h3 class="font-weight-bolder text-info">{{ $newProductsThisMonth }}</h3>
                                    <p class="text-muted mb-0">منتجات جديدة</p>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 col-6">
                                <div class="text-center stat-card">
                                    <div class="avatar bg-rgba-secondary mx-auto mb-2">
                                        <i class="feather icon-percent text-secondary font-large-1"></i>
                                    </div>
                                    <h3 class="font-weight-bolder text-secondary">{{ number_format($stockoutRate, 1) }}%</h3>
                                    <p class="text-muted mb-0">نسبة النفاذ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الثالث: إحصائيات المشتريات والمرتجعات --}}
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">إجمالي المشتريات</h6>
                                <h3 class="font-weight-bolder mb-0">{{ number_format($totalPurchases, 2) }}</h3>
                                <small class="text-muted">عدد الفواتير: {{ $totalPurchaseInvoices }}</small>
                            </div>
                            <div class="avatar bg-rgba-success">
                                <i class="feather icon-shopping-bag text-success font-large-1"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="badge badge-{{ $purchaseGrowth >= 0 ? 'success' : 'danger' }}">
                                <i class="feather icon-trending-{{ $purchaseGrowth >= 0 ? 'up' : 'down' }}"></i>
                                {{ number_format($purchaseGrowth, 1) }}%
                            </span>
                            <small class="text-muted"> مقارنة بالفترة السابقة</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">إجمالي المرتجعات</h6>
                                <h3 class="font-weight-bolder mb-0">{{ number_format($totalReturns, 2) }}</h3>
                                <small class="text-muted">نسبة المرتجعات: {{ number_format($returnRate, 1) }}%</small>
                            </div>
                            <div class="avatar bg-rgba-warning">
                                <i class="feather icon-rotate-ccw text-warning font-large-1"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="badge badge-{{ $returnGrowth >= 0 ? 'danger' : 'success' }}">
                                <i class="feather icon-trending-{{ $returnGrowth >= 0 ? 'up' : 'down' }}"></i>
                                {{ number_format($returnGrowth, 1) }}%
                            </span>
                            <small class="text-muted"> مقارنة بالفترة السابقة</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">إجمالي المدفوعات</h6>
                                <h3 class="font-weight-bolder mb-0">{{ number_format($totalSupplierPayments, 2) }}</h3>
                                <small class="text-muted">نسبة السداد: {{ number_format($paymentRate, 1) }}%</small>
                            </div>
                            <div class="avatar bg-rgba-info">
                                <i class="feather icon-credit-card text-info font-large-1"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">هذا الشهر: </small>
                            <strong>{{ number_format($paymentsThisMonth, 2) }} ر.س</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">المدفوعات المعلقة</h6>
                                <h3 class="font-weight-bolder mb-0 text-danger">{{ number_format($pendingPayments, 2) }}</h3>
                                <small class="text-muted">متوسط الفاتورة: {{ number_format($averagePurchaseValue, 2) }}</small>
                            </div>
                            <div class="avatar bg-rgba-danger">
                                <i class="feather icon-alert-circle text-danger font-large-1"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">أكبر فاتورة: </small>
                            <strong>{{ number_format($largestPurchase, 2) }} ر.س</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الرابع: أذونات المستودعات --}}
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="avatar bg-rgba-success mx-auto mb-2">
                            <i class="feather icon-arrow-down-circle text-success font-large-1"></i>
                        </div>
                        <h4 class="font-weight-bolder">{{ $incomingPermits->count }}</h4>
                        <p class="mb-1">أذونات إدخال</p>
                        <h5 class="text-success">{{ number_format($incomingPermits->total, 2) }} ر.س</h5>
                        <small class="text-muted">هذا الشهر: {{ $incomingPermitsThisMonth }}</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="avatar bg-rgba-danger mx-auto mb-2">
                            <i class="feather icon-arrow-up-circle text-danger font-large-1"></i>
                        </div>
                        <h4 class="font-weight-bolder">{{ $outgoingPermits->count }}</h4>
                        <p class="mb-1">أذونات إخراج</p>
                        <h5 class="text-danger">{{ number_format($outgoingPermits->total, 2) }} ر.س</h5>
                        <small class="text-muted">هذا الشهر: {{ $outgoingPermitsThisMonth }}</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="avatar bg-rgba-info mx-auto mb-2">
                            <i class="feather icon-shuffle text-info font-large-1"></i>
                        </div>
                        <h4 class="font-weight-bolder">{{ $transferPermits->count }}</h4>
                        <p class="mb-1">أذونات نقل</p>
                        <h5 class="text-info">{{ number_format($transferPermits->total, 2) }} ر.س</h5>
                        <small class="text-muted">هذا الشهر: {{ $transferPermitsThisMonth }}</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="avatar bg-rgba-warning mx-auto mb-2">
                            <i class="feather icon-edit-3 text-warning font-large-1"></i>
                        </div>
                        <h4 class="font-weight-bolder">{{ $adjustmentPermits->count }}</h4>
                        <p class="mb-1">أذونات تسوية</p>
                        <h5 class="text-warning">{{ number_format($adjustmentPermits->total, 2) }} ر.س</h5>
                        <small class="text-muted">للفترة المحددة</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الخامس: الرسومات البيانية الرئيسية --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> حركة المخزون الشهرية</h4>
                    </div>
                    <div class="card-body">
                        <div id="monthly-movements-chart" style="min-height: 400px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف السادس: المستودعات والفئات --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-home"></i> توزيع المخزون حسب المستودعات</h4>
                    </div>
                    <div class="card-body">
                        <div id="warehouse-stock-chart" style="min-height: 350px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-grid"></i> توزيع المخزون حسب الفئات</h4>
                    </div>
                    <div class="card-body">
                        <div id="category-stock-chart" style="min-height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف السابع: أفضل المنتجات والموردين --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-trending-up"></i> أكثر المنتجات حركة</h4>
                    </div>
                    <div class="card-body">
                        <div id="top-products-chart" style="min-height: 350px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-users"></i> أفضل الموردين</h4>
                    </div>
                    <div class="card-body">
                        <div id="top-suppliers-chart" style="min-height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الثامن: آخر أذونات المستودعات --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-file-text"></i> آخر أذونات المستودعات</h4>
                        <span class="badge badge-primary badge-pill">{{ count($recentWarehousePermits) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>رقم الإذن</th>
                                        <th>النوع</th>
                                        <th>المستودع</th>
                                        <th>القيمة</th>
                                        <th>عدد الأصناف</th>
                                        <th>التاريخ</th>
                                        <th>المستخدم</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentWarehousePermits as $permit)
                                    <tr>
                                        <td><strong>{{ $permit['number'] }}</strong></td>
                                        <td>
                                            <span class="badge badge-{{ $permit['color'] }}">
                                                <i class="feather icon-{{ $permit['icon'] }} badge-icon"></i>
                                                {{ $permit['type_name'] }}
                                            </span>
                                        </td>
                                        <td>{{ $permit['storehouse'] }}</td>
                                        <td><strong>{{ number_format($permit['grand_total'], 2) }}</strong></td>
                                        <td><span class="badge badge-light">{{ $permit['items_count'] }}</span></td>
                                        <td><small>{{ \Carbon\Carbon::parse($permit['date'])->format('Y-m-d') }}</small></td>
                                        <td><small>{{ $permit['created_by'] }}</small></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">لا توجد أذونات حديثة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف التاسع: آخر فواتير الشراء --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-shopping-cart"></i> آخر فواتير الشراء</h4>
                        <span class="badge badge-success badge-pill">{{ count($recentPurchaseInvoices) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>رقم الفاتورة</th>
                                        <th>النوع</th>
                                        <th>المورد</th>
                                        <th>القيمة</th>
                                        <th>الأصناف</th>
                                        <th>حالة الدفع</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentPurchaseInvoices as $invoice)
                                    <tr>
                                        <td><strong>{{ $invoice['code'] }}</strong></td>
                                        <td>
                                            <span class="badge badge-{{ $invoice['type'] == 'purchase' ? 'success' : 'warning' }}">
                                                {{ $invoice['type_name'] }}
                                            </span>
                                        </td>
                                        <td>{{ $invoice['supplier'] }}</td>
                                        <td><strong>{{ number_format($invoice['grand_total'], 2) }}</strong></td>
                                        <td><span class="badge badge-light">{{ $invoice['items_count'] }}</span></td>
                                        <td>
                                            <span class="badge badge-{{ $invoice['payment_status'] == 'paid' ? 'success' : 'warning' }}">
                                                {{ $invoice['payment_status'] == 'paid' ? 'مدفوعة' : 'معلقة' }}
                                            </span>
                                        </td>
                                        <td><small>{{ \Carbon\Carbon::parse($invoice['date'])->format('Y-m-d') }}</small></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">لا توجد فواتير حديثة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف العاشر: حركات المنتجات وأوامر التصنيع --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-activity"></i> آخر حركات المنتجات</h4>
                        <span class="badge badge-info badge-pill">{{ count($recentProductMovements) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0 small-table">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>المستودع</th>
                                        <th>الكمية</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentProductMovements as $movement)
                                    <tr>
                                        <td>{{ Str::limit($movement['product'], 20) }}</td>
                                        <td><small>{{ Str::limit($movement['storehouse'], 15) }}</small></td>
                                        <td>
                                            <span class="badge badge-{{ $movement['quantity'] > 0 ? 'success' : 'danger' }}">
                                                {{ $movement['quantity'] > 0 ? '+' : '' }}{{ $movement['quantity'] }}
                                            </span>
                                        </td>
                                        <td><small>{{ \Carbon\Carbon::parse($movement['created_at'])->diffForHumans() }}</small></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">لا توجد حركات حديثة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-settings"></i> آخر أوامر التصنيع</h4>
                        <span class="badge badge-secondary badge-pill">{{ count($recentManufacturingOrders) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0 small-table">
                                <thead>
                                    <tr>
                                        <th>الكود</th>
                                        <th>المنتج</th>
                                        <th>الكمية</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentManufacturingOrders as $order)
                                    <tr>
                                        <td><strong>{{ $order['code'] }}</strong></td>
                                        <td><small>{{ Str::limit($order['product'], 20) }}</small></td>
                                        <td>{{ $order['quantity'] }}</td>
                                        <td>
                                            <span class="badge badge-{{ $order['status'] == 'completed' ? 'success' : ($order['status'] == 'in_progress' ? 'warning' : 'secondary') }}">
                                                {{ $order['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">لا توجد أوامر حديثة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الحادي عشر: آخر المدفوعات --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-credit-card"></i> آخر المدفوعات للموردين</h4>
                        <span class="badge badge-success badge-pill">{{ count($recentPayments) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>المورد</th>
                                        <th>المبلغ</th>
                                        <th>طريقة الدفع</th>
                                        <th>الخزينة</th>
                                        <th>التاريخ</th>
                                        <th>الموظف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentPayments as $payment)
                                    <tr>
                                        <td><strong>{{ $payment['supplier'] }}</strong></td>
                                        <td><strong class="text-success">{{ number_format($payment['amount'], 2) }}</strong></td>
                                        <td><span class="badge badge-info">{{ $payment['payment_method'] }}</span></td>
                                        <td><small>{{ $payment['treasury'] }}</small></td>
                                        <td><small>{{ \Carbon\Carbon::parse($payment['payment_date'])->format('Y-m-d') }}</small></td>
                                        <td><small>{{ $payment['employee'] }}</small></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">لا توجد مدفوعات حديثة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الثاني عشر: آخر المنتجات والموردين --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-box"></i> آخر المنتجات المضافة</h4>
                        <span class="badge badge-primary badge-pill">{{ count($recentProducts) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0 small-table">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>الفئة</th>
                                        <th>السعر</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentProducts as $product)
                                    <tr>
                                        <td><strong>{{ Str::limit($product['name'], 25) }}</strong></td>
                                        <td><small>{{ $product['category'] }}</small></td>
                                        <td>{{ number_format($product['sale_price'], 2) }}</td>
                                        <td><small>{{ \Carbon\Carbon::parse($product['created_at'])->diffForHumans() }}</small></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">لا توجد منتجات حديثة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><i class="feather icon-users"></i> آخر الموردين المضافين</h4>
                        <span class="badge badge-success badge-pill">{{ count($recentSuppliers) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0 small-table">
                                <thead>
                                    <tr>
                                        <th>الاسم</th>
                                        <th>الهاتف</th>
                                        <th>الرصيد</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentSuppliers as $supplier)
                                    <tr>
                                        <td><strong>{{ Str::limit($supplier['name'], 25) }}</strong></td>
                                        <td><small>{{ $supplier['mobile'] ?? $supplier['phone'] }}</small></td>
                                        <td>{{ number_format($supplier['opening_balance'], 2) }}</td>
                                        <td><small>{{ \Carbon\Carbon::parse($supplier['created_at'])->diffForHumans() }}</small></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">لا يوجد موردين حديثين</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الثالث عشر: التحليل الأسبوعي واليومي --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-calendar"></i> التحليل الأسبوعي (آخر 4 أسابيع)</h4>
                    </div>
                    <div class="card-body">
                        <div id="weekly-analysis-chart" style="min-height: 300px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-clock"></i> التحليل اليومي (آخر 30 يوم)</h4>
                    </div>
                    <div class="card-body">
                        <div id="daily-analysis-chart" style="min-height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الرابع عشر: أداء الفئات والمنتجات البطيئة --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-pie-chart"></i> أداء فئات المنتجات</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 small-table">
                                <thead>
                                    <tr>
                                        <th>الفئة</th>
                                        <th>المعاملات</th>
                                        <th>القيمة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($categoryPerformance->take(8) as $category)
                                    <tr>
                                        <td><strong>{{ $category->name }}</strong></td>
                                        <td><span class="badge badge-primary">{{ $category->transactions_count }}</span></td>
                                        <td><strong>{{ number_format($category->total_value, 2) }}</strong></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">لا توجد بيانات</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-alert-triangle"></i> المنتجات بطيئة الحركة</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 small-table">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>الحركات</th>
                                        <th>التوصية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($slowMovingProducts as $product)
                                    <tr>
                                        <td><strong>{{ Str::limit($product->name, 30) }}</strong></td>
                                        <td>
                                            <span class="badge badge-{{ $product->movement_count == 0 ? 'danger' : 'warning' }}">
                                                {{ $product->movement_count }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($product->movement_count == 0)
                                            <small class="text-danger"><i class="feather icon-alert-circle"></i> يحتاج مراجعة</small>
                                            @else
                                            <small class="text-warning"><i class="feather icon-info"></i> حركة ضعيفة</small>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">جميع المنتجات نشطة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصف الخامس عشر: منتجات منتهية الصلاحية ومؤشرات الأداء --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-alert-octagon"></i> تنبيهات انتهاء الصلاحية</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 small-table">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>تاريخ الانتهاء</th>
                                        <th>الكمية</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($expiringProducts->take(8) as $product)
                                    <tr>
                                        <td>{{ Str::limit($product['product_name'], 25) }}</td>
                                        <td><small>{{ \Carbon\Carbon::parse($product['expiry_date'])->format('Y-m-d') }}</small></td>
                                        <td>{{ $product['quantity'] }}</td>
                                        <td>
                                            <span class="badge badge-{{ $product['status'] === 'expired' ? 'danger' : 'warning' }}">
                                                {{ $product['status'] === 'expired' ? 'منتهي' : 'قريب' }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">لا توجد منتجات منتهية أو قريبة</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-bar-chart"></i> مؤشرات الأداء الرئيسية</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>معدل دوران المخزون</span>
                                <strong class="text-primary">{{ number_format($inventoryTurnoverRate, 2) }}</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: {{ min($inventoryTurnoverRate * 10, 100) }}%"></div>
                            </div>
                            <small class="text-muted">المعدل الأمثل: 4-6 مرات سنوياً</small>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>متوسط أيام البيع</span>
                                <strong class="text-info">{{ number_format($averageDaysToSell, 0) }} يوم</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-info" style="width: {{ min($averageDaysToSell / 3, 100) }}%"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>معدل إنجاز الطلبات</span>
                                <strong class="text-success">{{ number_format($orderFulfillmentRate, 1) }}%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: {{ $orderFulfillmentRate }}%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>نسبة نفاذ المخزون</span>
                                <strong class="text-{{ $stockoutRate > 10 ? 'danger' : 'warning' }}">{{ number_format($stockoutRate, 1) }}%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-{{ $stockoutRate > 10 ? 'danger' : 'warning' }}" style="width: {{ $stockoutRate }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/apex-charts/3.44.0/apexcharts.min.js"></script>

<script>
function initializeStockCharts() {
    'use strict';
    
    if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts غير محملة!');
        setTimeout(initializeStockCharts, 100);
        return;
    }

    const colors = {
        primary: '#7367f0',
        warning: '#ff9f43',
        danger: '#ea5455',
        success: '#28c76f',
        info: '#0dcce1',
        secondary: '#82868b'
    };

    const monthlyData = @json($monthlyData);
    const stockByWarehouse = @json($stockByWarehouse);
    const stockByCategory = @json($stockByCategory);
    const topMovingProducts = @json($topMovingProducts);
    const topSuppliers = @json($topSuppliers);
    const weeklyAnalysis = @json($weeklyAnalysis);
    const dailyAnalysis = @json($dailyAnalysis);

    try {
        // 1. مخطط حركة المخزون الشهرية
        if (document.querySelector("#monthly-movements-chart")) {
            new ApexCharts(document.querySelector("#monthly-movements-chart"), {
                chart: {
                    type: 'line',
                    height: 400,
                    toolbar: { show: true }
                },
                series: [
                    { name: 'المشتريات', data: monthlyData.purchases },
                    { name: 'المرتجعات', data: monthlyData.returns },
                    { name: 'أذونات إدخال', data: monthlyData.incoming_permits },
                    { name: 'أذونات إخراج', data: monthlyData.outgoing_permits }
                ],
                colors: [colors.success, colors.danger, colors.primary, colors.warning],
                stroke: { width: 3, curve: 'smooth' },
                markers: { size: 5 },
                xaxis: { categories: monthlyData.labels },
                legend: { position: 'top' },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return val.toLocaleString('ar-SA') + ' ر.س';
                        }
                    }
                }
            }).render();
        }

        // 2. مخطط توزيع المخزون حسب المستودعات
        if (document.querySelector("#warehouse-stock-chart") && stockByWarehouse.length > 0) {
            new ApexCharts(document.querySelector("#warehouse-stock-chart"), {
                chart: {
                    type: 'donut',
                    height: 350
                },
                series: stockByWarehouse.map(w => w.value),
                labels: stockByWarehouse.map(w => w.warehouse_name),
                colors: [colors.primary, colors.success, colors.info, colors.warning, colors.danger],
                legend: { position: 'bottom' },
                plotOptions: {
                    pie: {
                        donut: {
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'الإجمالي',
                                    formatter: function(w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('ar-SA');
                                    }
                                }
                            }
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return val.toLocaleString('ar-SA') + ' ر.س';
                        }
                    }
                }
            }).render();
        }

        // 3. مخطط توزيع المخزون حسب الفئات
        if (document.querySelector("#category-stock-chart") && stockByCategory.length > 0) {
            new ApexCharts(document.querySelector("#category-stock-chart"), {
                chart: {
                    type: 'pie',
                    height: 350
                },
                series: stockByCategory.map(c => c.total_value),
                labels: stockByCategory.map(c => c.category_name),
                colors: [colors.primary, colors.success, colors.warning, colors.danger, colors.info, colors.secondary],
                legend: { position: 'bottom' },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return val.toLocaleString('ar-SA') + ' ر.س';
                        }
                    }
                }
            }).render();
        }

        // 4. مخطط أكثر المنتجات حركة
        if (document.querySelector("#top-products-chart") && topMovingProducts.length > 0) {
            new ApexCharts(document.querySelector("#top-products-chart"), {
                chart: {
                    type: 'bar',
                    height: 350
                },
                series: [{ 
                    name: 'الكمية', 
                    data: topMovingProducts.map(p => p.quantity) 
                }],
                xaxis: { 
                    categories: topMovingProducts.map(p => p.product_name),
                    labels: {
                        formatter: function(val) {
                            return val.length > 15 ? val.substring(0, 15) + '...' : val;
                        }
                    }
                },
                colors: [colors.primary],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4
                    }
                },
                dataLabels: { enabled: true }
            }).render();
        }

        // 5. مخطط أفضل الموردين
        if (document.querySelector("#top-suppliers-chart") && topSuppliers.length > 0) {
            new ApexCharts(document.querySelector("#top-suppliers-chart"), {
                chart: {
                    type: 'bar',
                    height: 350
                },
                series: [{ 
                    name: 'المشتريات', 
                    data: topSuppliers.map(s => s.total_purchases) 
                }],
                xaxis: { 
                    categories: topSuppliers.map(s => s.supplier_name),
                    labels: {
                        formatter: function(val) {
                            return val.length > 15 ? val.substring(0, 15) + '...' : val;
                        }
                    }
                },
                colors: [colors.success],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4
                    }
                },
                dataLabels: { enabled: false },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return val.toLocaleString('ar-SA') + ' ر.س';
                        }
                    }
                }
            }).render();
        }

        // 6. مخطط التحليل الأسبوعي
        if (document.querySelector("#weekly-analysis-chart")) {
            new ApexCharts(document.querySelector("#weekly-analysis-chart"), {
                chart: {
                    type: 'bar',
                    height: 300
                },
                series: [
                    {
                        name: 'المشتريات',
                        data: weeklyAnalysis.data.map(d => d.purchases)
                    },
                    {
                        name: 'الأذونات',
                        data: weeklyAnalysis.data.map(d => d.permits)
                    }
                ],
                xaxis: {
                    categories: weeklyAnalysis.labels
                },
                colors: [colors.success, colors.primary],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4
                    }
                },
                legend: { position: 'top' },
                dataLabels: { enabled: false }
            }).render();
        }

        // 7. مخطط التحليل اليومي
        if (document.querySelector("#daily-analysis-chart")) {
            new ApexCharts(document.querySelector("#daily-analysis-chart"), {
                chart: {
                    type: 'area',
                    height: 300,
                    zoom: { enabled: true }
                },
                series: [
                    {
                        name: 'المشتريات',
                        data: dailyAnalysis.map(d => d.purchases)
                    },
                    {
                        name: 'الأذونات',
                        data: dailyAnalysis.map(d => d.permits)
                    }
                ],
                xaxis: {
                    categories: dailyAnalysis.map(d => d.date_formatted)
                },
                colors: [colors.success, colors.primary],
                stroke: {
                    curve: 'smooth',
                    width: 2
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.7,
                        opacityTo: 0.3
                    }
                },
                legend: { position: 'top' }
            }).render();
        }

        console.log('تم تحميل جميع الرسوم البيانية بنجاح');

    } catch (error) {
        console.error('خطأ في تحميل الرسوم البيانية:', error);
    }
}

// بدء التحميل
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeStockCharts);
} else {
    initializeStockCharts();
}
</script>
@endpush