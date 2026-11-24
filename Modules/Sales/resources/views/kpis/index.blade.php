@extends('sales::master')

@section('disable-dashboard-ecommerce', true)

@section('title')
مؤشرات الأداء
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/performance-indicators.css') }}">
<link rel="stylesheet" href="{{ asset('app-assets/vendors/css/forms/select/select2.min.css') }}">
<style>
    /* تحسينات التحصيلات */
    .bg-gradient-collections {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        position: relative;
        overflow: hidden;
    }

    .bg-gradient-collections::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        background: rgba(13, 204, 225, 0.1);
        border-radius: 50%;
    }

    .bg-gradient-collections .card-header {
        position: relative;
        z-index: 1;
    }

    .bg-gradient-collections .card-footer {
        background: rgba(255, 255, 255, 0.95);
        position: relative;
        z-index: 1;
    }

    .card-footer {
        padding: 1rem;
        font-size: 0.875rem;
    }

    .card-footer .text-success {
        color: #28c76f !important;
    }

    .card-footer .text-warning {
        color: #ff9f43 !important;
    }

    /* تحسينات عامة للبطاقات */
    .card {
        transition: all 0.3s ease;
        border: 1px solid #e8e8e8;
    }

    .card:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    /* بطاقة الملخص الإحصائي */
    .stats-summary {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .summary-item {
        text-align: center;
        padding: 15px 10px;
        border-right: 1px solid #e9ecef;
        transition: all 0.3s ease;
    }

    .summary-item:hover {
        background-color: #f8f9fa;
        border-radius: 8px;
    }

    .summary-item:last-child {
        border-right: none;
    }

    .summary-label {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .summary-value {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2c3e50;
        word-break: break-word;
    }

    .text-info {
        color: #0dcce1 !important;
    }

    .text-success {
        color: #28c76f !important;
    }

    .text-warning {
        color: #ff9f43 !important;
    }

    .text-primary {
        color: #7367f0 !important;
    }

    @media (max-width: 768px) {
        .summary-item {
            border-right: none;
            border-bottom: 1px solid #e9ecef;
            padding: 12px;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-value {
            font-size: 1rem;
        }
    }
</style>
<style>
    /* أزرار فلتر الفترة الزمنية */
    .period-filter-card {
        background: linear-gradient(118deg, rgba(115, 103, 240, 1), rgba(115, 103, 240, 0.7));
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 0 10px 1px rgba(115, 103, 240, 0.7);
    }

    .period-filter-title {
        color: #fff;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .period-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .period-btn {
        border-radius: 25px;
        padding: 8px 18px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    .period-btn:hover {
        background: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.5);
        color: #fff;
        transform: translateY(-2px);
    }

    .period-btn.active {
        background: #fff;
        color: #667eea;
        border-color: #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .period-btn i {
        margin-left: 5px;
    }

    .custom-date-inputs {
        display: none;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .custom-date-inputs.show {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: end;
    }

    .custom-date-inputs .form-group {
        margin-bottom: 0;
    }

    .custom-date-inputs label {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.8rem;
        margin-bottom: 5px;
        display: block;
    }

    .custom-date-inputs input {
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        padding: 8px 12px;
    }

    .custom-date-inputs input::placeholder {
        color: rgba(255, 255, 255, 0.6);
    }

    .custom-date-inputs input:focus {
        background: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.5);
        color: #fff;
        outline: none;
    }

    .apply-filter-btn {
        background: #fff;
        color: #667eea;
        border: none;
        border-radius: 8px;
        padding: 8px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .apply-filter-btn:hover {
        background: #f0f0f0;
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .period-tabs {
            justify-content: center;
        }

        .period-btn {
            padding: 6px 12px;
            font-size: 0.75rem;
        }

        .custom-date-inputs {
            flex-direction: column;
            align-items: stretch;
        }
    }

    /* بطاقات مخططات KPI — مقارنة الفروع والمجموعات */
    .kpi-chart-card {
        border: 0;
        box-shadow: 0 2px 14px rgba(34, 41, 47, 0.07);
        border-radius: 14px;
        overflow: hidden;
    }
    .kpi-chart-card .card-header {
        background: linear-gradient(118deg, rgba(115, 103, 240, 0.08), rgba(115, 103, 240, 0.02));
        border-bottom: 1px solid #eceef5;
        padding: 1rem 1.25rem;
    }
    .kpi-chart-card .card-title {
        font-weight: 700;
        color: #2c3349;
    }
    .kpi-chart-card .card-subtitle {
        font-size: 0.8125rem;
        color: #6e6b7b;
        line-height: 1.45;
        max-width: 48rem;
    }
    .kpi-chart-surface {
        background: linear-gradient(180deg, #fafbfd 0%, #fff 40%);
        border-radius: 12px;
        border: 1px solid #eef0f6;
        padding: 0.35rem 0.25rem 0.15rem;
    }
    .groups-detail-table {
        font-size: 0.8125rem;
        border-collapse: separate;
        border-spacing: 0;
    }
    .groups-detail-table thead th {
        background: #f6f4ff;
        color: #5e5873;
        font-weight: 600;
        border-bottom: 2px solid #e8e3fc;
        white-space: nowrap;
        padding: 0.65rem 0.5rem;
    }
    .groups-detail-table tbody td {
        padding: 0.5rem 0.5rem;
        vertical-align: middle;
    }
    .groups-detail-table tbody tr:hover {
        background: #fbfcff;
    }
    details.groups-details-wrap > summary::-webkit-details-marker {
        display: none;
    }
    details.groups-details-wrap > summary::marker {
        display: none;
        content: '';
    }

    .branch-hierarchy-filter-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e8e8e8;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
    }

    .branch-hierarchy-filter-card .filter-title {
        font-weight: 600;
        color: #2c3349;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #branchHierarchyFilter {
        width: 100%;
        max-width: 520px;
    }

    .branch-hierarchy-filter-card .select2-container {
        width: 100% !important;
        max-width: 520px;
    }

    .branch-filter-active-badge {
        font-size: 0.8125rem;
        font-weight: 500;
    }

    .period-filter-card .branch-filter-row {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.25);
    }

    .period-filter-card .branch-filter-row label {
        color: #fff;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
    }

    .period-filter-card #branchHierarchyFilter,
    .period-filter-card .select2-container {
        width: 100% !important;
        max-width: 100%;
    }

    .period-filter-card .select2-container--default .select2-selection--single {
        min-height: 42px;
        border-radius: 8px;
        border: 1px solid #ddd;
        background: #fff;
    }

    .period-filter-card .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
        padding-right: 12px;
    }

    .group-filter #branchHierarchyFilter,
    .group-filter .select2-container {
        width: 100% !important;
        min-width: 280px;
    }
</style>
@endpush

@section('content')
<div class="content-body">
    <section id="dashboard-analytics">

        <!-- فلتر الفترة الزمنية -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="period-filter-card">
                    <div class="period-filter-title">
                        <i class="feather icon-calendar"></i>
                        فلترة حسب الفترة الزمنية
                    </div>
                    <div class="period-tabs">
                        <button type="button" class="btn period-btn {{ request('period', '365') == '1' ? 'active' : '' }}" data-period="1">
                            <i class="fa fa-calendar-day"></i> يوم
                        </button>
                        <button type="button" class="btn period-btn {{ request('period', '365') == '7' ? 'active' : '' }}" data-period="7">
                            <i class="fa fa-calendar-week"></i> أسبوع
                        </button>
                        <button type="button" class="btn period-btn {{ request('period', '365') == '30' ? 'active' : '' }}" data-period="30">
                            <i class="fa fa-calendar-alt"></i> شهر
                        </button>
                        <button type="button" class="btn period-btn {{ request('period', '365') == '90' ? 'active' : '' }}" data-period="90">
                            <i class="fa fa-calendar"></i> 3 أشهر
                        </button>
                        <button type="button" class="btn period-btn {{ request('period', '365') == '180' ? 'active' : '' }}" data-period="180">
                            <i class="fa fa-calendar"></i> 6 أشهر
                        </button>
                        <button type="button" class="btn period-btn {{ request('period', '365') == '365' ? 'active' : '' }}" data-period="365">
                            <i class="fa fa-calendar"></i> سنة
                        </button>
                        <button type="button" class="btn period-btn {{ request('period') == 'custom' ? 'active' : '' }}" data-period="custom">
                            <i class="fa fa-calendar-plus"></i> مخصص
                        </button>
                    </div>

                    <div class="custom-date-inputs {{ request('period') == 'custom' ? 'show' : '' }}" id="customDateInputs">
                        <div class="form-group">
                            <label>من تاريخ</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" value="{{ request('start_date') }}">
                        </div>
                        <div class="form-group">
                            <label>إلى تاريخ</label>
                            <input type="date" class="form-control" id="endDate" name="end_date" value="{{ request('end_date') }}">
                        </div>
                        <button type="button" class="btn apply-filter-btn" id="applyCustomFilter">
                            <i class="fa fa-search"></i> تطبيق
                        </button>
                    </div>

                    @if($showBranchHierarchyFilter ?? false)
                    <div class="branch-filter-row">
                        <label for="branchHierarchyFilterTop">
                            <i class="feather icon-git-branch"></i>
                            الفرع / المجموعة (شجرة كاملة)
                        </label>
                        <select id="branchHierarchyFilterTop" class="form-control branch-hierarchy-filter">
                            <option value="">كل الفروع والمجموعات</option>
                            @foreach($branchHierarchyOptions ?? [] as $option)
                                <option value="{{ $option['id'] }}" {{ (string)($selected_branch_filter_id ?? '') === (string)$option['id'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- فلتر الشجرة في بطاقة الفترة أعلاه + قسم المجموعات --}}
        @if(false)
        <div class="row mb-2 d-none">
            <div class="col-12">
                <div class="branch-hierarchy-filter-card d-none">
                    <div class="filter-title">
                        <i class="feather icon-filter"></i>
                        فلترة حسب الفرع / المجموعة (الشجرة كاملة)
                    </div>
                    <select id="branchHierarchyFilter" class="form-control branch-hierarchy-filter">
                        <option value="">كل الفروع والمجموعات</option>
                        @foreach($branchHierarchyOptions ?? [] as $option)
                            <option value="{{ $option['id'] }}" {{ (string)($selected_branch_filter_id ?? '') === (string)$option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @if(!empty($selectedBranchFilterLabel))
                        <div class="mt-1">
                            <span class="badge badge-light-primary branch-filter-active-badge">
                                العرض الحالي: {{ $selectedBranchFilterLabel }} (يشمل كل التابعين)
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- الصف الأول -->
        <div class="row">
            <div class="col-lg-6 col-md-12 col-sm-12">
                <div class="card bg-analytics text-white">
                    <div class="card-body text-center">
                        <div class="avatar avatar-xl bg-primary shadow mt-0" style="margin: 0 auto 1rem;">
                            <i class="feather icon-award white font-large-1"></i>
                        </div>
                        <h1 class="mb-2 text-white">مرحباً بك في لوحة المبيعات</h1>
                        <p class="m-auto w-75">تابع مؤشرات أداء مبيعاتك وإحصائياتها المهمة</p>
                    </div>
                </div>
            </div>

            <!-- بطاقة الفواتير -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-primary">
                            <i class="feather icon-file-text text-primary font-medium-5"></i>
                        </div>
                        <h2 class="text-bold-700 mt-1 mb-25">{{ $invoiceCount ?? 0 }}</h2>
                        <p class="mb-0">الفواتير</p>
                    </div>
                    <div class="card-content">
                        <div id="subscribe-gain-chart" style="min-height: 100px;"></div>
                    </div>
                </div>
            </div>


            <!-- بطاقة المبيعات -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-warning">
                            <i class="feather icon-shopping-cart text-warning font-medium-5"></i>
                        </div>
                        <h2 class="text-bold-700 mt-1 mb-25">{{ number_format($totalSales ?? 0, 2) }}</h2>
                        <p class="mb-0">المبيعات</p>
                    </div>
                    <div class="card-content">
                        <div id="orders-received-chart" style="min-height: 100px;"></div>
                    </div>
                </div>
            </div>

            <!-- بطاقة التحصيلات -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-info">
                            <i class="feather icon-credit-card text-info font-medium-5"></i>
                        </div>
                        <h2 class="text-bold-700 mt-1 mb-25">{{ number_format($totalCollections ?? 0, 2) }}</h2>
                        <p class="mb-0">التحصيلات</p>
                    </div>
                    <div class="card-content">
                        <div id="collections-chart" style="min-height: 100px;"></div>
                    </div>
                </div>
            </div>

            <!-- بطاقة المرتجعات -->
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-danger">
                            <i class="feather icon-arrow-left text-danger font-medium-5"></i>
                        </div>
                        <h2 class="text-bold-700 mt-1 mb-25">{{ number_format($totalReturns ?? 0, 2) }}</h2>
                        <p class="mb-0">المرتجعات</p>
                    </div>
                    <div class="card-content">
                        <div id="returns-chart" style="min-height: 100px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الصف الثاني -->
        <div class="row">
            <!-- متوسط المبيعات -->
            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-content">
                        <div class="card-body">
                            <div class="row pb-50">
                                <div class="col-lg-6 col-12 d-flex justify-content-between flex-column">
                                    <div>
                                        <h2 class="text-bold-700 mb-25">{{ number_format($averageSales ?? 0, 2) }}</h2>
                                        <p class="text-bold-500 mb-25">متوسط المبيعات</p>
                                        <!--<h5 class="font-medium-2">-->
                                        <!--    @php $growthPositive = ($salesGrowthPercent ?? 0) >= 0; @endphp-->
                                        <!--    <span class="{{ $growthPositive ? 'text-success' : 'text-danger' }}">-->
                                        <!--        {{ number_format(abs($salesGrowthPercent ?? 0), 2) }}% {{ $growthPositive ? '↑' : '↓' }}-->
                                        <!--    </span>-->
                                        <!--    <span>مقارنة بالفترة السابقة</span>-->
                                        <!--</h5>-->
                                    </div>
                                    <a href="#" class="btn btn-primary mt-2">عرض التفاصيل <i class="feather icon-chevrons-left"></i></a>
                                </div>
                                <div class="col-lg-6 col-12">
                                    <div id="avg-session-chart" style="min-height: 220px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إجمالي المبيعات والمدفوعات -->
            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between pb-0">
                        <h4 class="card-title">ملخص المبيعات</h4>
                    </div>
                    <div class="card-content">
                        <!-- Remove top padding to align chart nicely inside card -->
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 d-flex justify-content-center">
                                    <!-- Multi-series radial bar for sales, payments and receipts -->
                                    <div id="support-tracker-chart" style="min-height: 280px;"></div>
                                </div>
                            </div>
                            <div class="chart-info d-flex justify-content-between mt-2">
                                <div class="text-center">
                                    <p class="mb-2">المبيعات الكلية</p>
                                    <span class="font-large-1 text-success">{{ number_format($totalSales ?? 0, 2) }}</span>
                                </div>
                                <div class="text-center">
                                    <p class="mb-2">التحصيلات</p>
                                    <span class="font-large-1 text-primary">{{ number_format($totalPayments ?? 0, 2) }}</span>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<!-- ضع هذا الكود في المكان المناسب بعد الصف الثاني -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="card-title">  المبيعات والتحصيلات  </h4>
                    @if(!($showBranchHierarchyFilter ?? false) && !$isMainBranch)
                    <div class="current-branch">
                        <span class="badge badge-primary">{{ auth()->user()->branch->name ?? 'الفرع الحالي' }}</span>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div id="payments-receipts-chart" style="min-height: 400px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

            <!-- كارد المؤشر العام -->
         <!-- كارد المؤشر العام -->
<!-- كارد المؤشر العام -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center pb-0">
                <h4 class="card-title mb-0">المؤشر العام</h4>

            </div>
            <div class="card-content">
                <div class="card-body py-0">
                    <div id="indicator-chart" style="min-height: 300px;"></div>
                </div>
                <div class="card-footer border-top-0 pt-0">
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="border-right">
                                <p class="mb-1 text-muted">المبيعات</p>
                                <h5 class="font-weight-bolder text-primary mb-0">{{ number_format($totalSales, 2) }} ر.س</h5>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border-right">
                                <p class="mb-1 text-muted">التحصيلات</p>
                                <h5 class="font-weight-bolder text-success mb-0">{{ number_format($totalPayments, 2) }} ر.س</h5>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border-right">
                                <p class="mb-1 text-muted">المرتجعات</p>
                                <h5 class="font-weight-bolder text-danger mb-0">{{ number_format($totalReturns, 2) }} ر.س</h5>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
            <!-- الصف الثالث - نمو المبيعات -->
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">نمو المبيعات</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div id="line-chart" style="min-height: 365px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الإشعارات الدائنة -->
            <!--<div class="col-lg-4 col-md-12">-->
            <!--    <div class="card">-->
            <!--        <div class="card-header">-->
            <!--            <h4 class="card-title">الإشعارات الدائنة</h4>-->
            <!--        </div>-->
            <!--        <div class="card-content">-->
            <!--            <div class="card-body">-->
            <!--                <div id="product-order-chart" style="min-height: 300px;"></div>-->
            <!--            </div>-->
            <!--        </div>-->
            <!--    </div>-->
            <!--</div>-->
        </div>

        <!-- الصف الرابع - الرسوم البيانية الإضافية -->
        <div class="row">
            <!-- مخطط Pie -->
            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">توزيع المبيعات</h4>
                    </div>
                    <div class="card-content">
                        <div class="card-body">
                            <div id="pie-chart" style="min-height: 300px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- مقارنة أداء الفروع الرئيسية (جذور الشجرة فقط، الأرقام تشمل التابعين) -->
          <div class="col-md-6 col-12">
    <div class="card kpi-chart-card">
        <div class="card-header">
            <h4 class="card-title mb-50">مقارنة أداء الفروع</h4>
            <p class="card-subtitle mb-0">تُعرض <strong>الفروع الرئيسية</strong> التي لا يوجد لها أب في الشجرة فقط؛ المبالغ هي مجموع كل فرع مع جميع الفروع والمجموعات التابعة له ضمن نطاق الفلتر.</p>
        </div>
        <div class="card-content">
            <div class="card-body pt-0">
                <div class="kpi-chart-surface">
                    <div id="radar-chart" style="min-height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
<!-- يمكنك إضافته مؤقتاً للتحقق -->

        <!-- الصف الخامس — المجموعات -->
        <div class="row">

      <div class="col-md-12 col-12">
    <div class="card kpi-chart-card">
        <div class="card-header d-flex justify-content-between align-items-start flex-wrap">
            <div class="mb-1 mb-md-0 pr-md-1">
                <h4 class="card-title mb-50">مبيعات وتحصيلات ومرتجعات المجموعات</h4>
                <p class="card-subtitle mb-0">ترتيب شجري للمجموعات ضمن نطاق الفلتر العام أعلاه. الأعمدة للقيم <strong>المباشرة</strong>؛ للتفاصيل والإجمالي مع الأبناء استخدم الجدول أسفل المخطط.</p>
            </div>
            @if($showBranchHierarchyFilter ?? false)
            <div class="group-filter flex-shrink-0">
                <label for="branchHierarchyFilterGroups" class="d-block small text-muted mb-25">الفرع / المجموعة</label>
                <select id="branchHierarchyFilterGroups" class="form-control form-control-sm branch-hierarchy-filter">
                    <option value="">كل الفروع والمجموعات</option>
                    @foreach($branchHierarchyOptions ?? [] as $option)
                        <option value="{{ $option['id'] }}" {{ (string)($selected_branch_filter_id ?? '') === (string)$option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            @elseif(!empty($selectedBranchFilterLabel))
            <div class="flex-shrink-0">
                <span class="badge badge-light-secondary branch-filter-active-badge">{{ $selectedBranchFilterLabel }}</span>
            </div>
            @endif
        </div>
        <div class="card-content">
            <div class="card-body pt-0">
                <div class="kpi-chart-surface mb-1">
                    <div id="groups-chart" style="min-height: 360px;"></div>
                </div>
                @if(isset($groupChartData) && $groupChartData->count())
                <details class="mt-1 groups-details-wrap">
                    <summary class="btn btn-sm btn-outline-primary mb-1" style="cursor: pointer; list-style: none;">عرض جدول التفاصيل (مباشر / شامل الأبناء)</summary>
                    <div class="table-responsive border rounded" style="border-color: #eef0f6 !important;">
                    <table class="table groups-detail-table mb-0">
                        <thead>
                            <tr>
                                <th>المسار</th>
                                <th class="text-right">مبيعات مباشرة</th>
                                <th class="text-right">مبيعات شاملة</th>
                                <th class="text-right">تحصيلات مباشرة</th>
                                <th class="text-right">تحصيلات شاملة</th>
                                <th class="text-right">مرتجعات مباشرة</th>
                                <th class="text-right">مرتجعات شاملة</th>
                                <th class="text-right">فواتير</th>
                                <th class="text-right">فواتير شاملة</th>
                            </tr>
                        </thead>
                        <tbody id="groups-stats-tbody">
                            @foreach($groupChartData as $row)
                            <tr data-region-id="{{ $row['region_id'] }}">
                                <td class="text-nowrap">{{ $row['path_label'] ?? $row['region'] }}</td>
                                <td class="text-right">{{ number_format($row['sales'] ?? 0, 2) }}</td>
                                <td class="text-right font-weight-bold text-primary">{{ number_format($row['rolled_sales'] ?? 0, 2) }}</td>
                                <td class="text-right">{{ number_format($row['collections'] ?? 0, 2) }}</td>
                                <td class="text-right font-weight-bold text-success">{{ number_format($row['rolled_collections'] ?? 0, 2) }}</td>
                                <td class="text-right">{{ number_format($row['returns'] ?? 0, 2) }}</td>
                                <td class="text-right font-weight-bold text-danger">{{ number_format($row['rolled_returns'] ?? 0, 2) }}</td>
                                <td class="text-right">{{ number_format($row['invoice_count'] ?? 0) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($row['rolled_invoice_count'] ?? 0) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </details>
                @endif
            </div>
        </div>
    </div>
</div>

        </div>

    </section>
</div>
@endsection

@push('scripts')
<script>
/**
 * تنسيق أرقام المخططات بأرقام إنجليزية 0-9 حتى مع locale عربي (يمنع ١٢٣ على المحاور).
 */
function kpiFormatLatinNumber(val, maxFrac, minFrac) {
    var n = Number(val);
    if (isNaN(n)) {
        return String(val);
    }
    var opts = {
        maximumFractionDigits: maxFrac == null ? 0 : maxFrac,
        minimumFractionDigits: minFrac == null ? 0 : minFrac
    };
    try {
        opts.numberingSystem = 'latn';
    } catch (e0) { /* قديم */ }
    try {
        return n.toLocaleString('en-US', opts);
    } catch (e1) {
        return n.toLocaleString('en-US', { maximumFractionDigits: maxFrac == null ? 2 : maxFrac });
    }
}

// الانتظار حتى تحميل المكتبة والصفحة بالكامل
function initializeAllCharts() {
    'use strict';

    if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts غير محملة!');
        setTimeout(initializeAllCharts, 100);
        return;
    }

    console.log('بدء تحميل الرسوم البيانية...');
    console.log('عدد العناصر المتاحة:', {
        'subscribe-gain-chart': !!document.querySelector("#subscribe-gain-chart"),
        'orders-received-chart': !!document.querySelector("#orders-received-chart"),
        'collections-chart': !!document.querySelector("#collections-chart"),
        'returns-chart': !!document.querySelector("#returns-chart"),
        'avg-session-chart': !!document.querySelector("#avg-session-chart"),
        'support-tracker-chart': !!document.querySelector("#support-tracker-chart"),
        'payments-receipts-chart': !!document.querySelector("#payments-receipts-chart"),
        'line-chart': !!document.querySelector("#line-chart"),
        'pie-chart': !!document.querySelector("#pie-chart"),
        'indicator-chart': !!document.querySelector("#indicator-chart"),
        'radar-chart': !!document.querySelector("#radar-chart"),
        'groups-chart': !!document.querySelector("#groups-chart")
    });

    const colors = {
        primary: '#7367f0',
        warning: '#ff9f43',
        danger: '#ea5455',
        success: '#28c76f',
        info: '#0dcce1'
    };

    // بيانات الرسوم البيانية الأساسية
    const monthlyInvoiceCountData = @json($monthlyInvoiceCountData ?? array_fill(0, 12, 0));
    const monthlySalesData = @json($monthlySalesData ?? array_fill(0, 12, 0));
    const monthlyReturnsData = @json($monthlyReturnsData ?? array_fill(0, 12, 0));
    const monthlyTargetData = @json($monthlyTargetData ?? array_fill(0, 12, 0));
    const monthlyAvgSalesData = @json($monthlyAvgSalesData ?? array_fill(0, 12, 0));
    const topProductLabels = @json($topProductLabels ?? []);
    const topProductData = @json($topProductData ?? []);

    // بيانات المدفوعات وسندات القبض
    const monthlyPaymentsData = @json($monthlyPaymentsData ?? array_fill(0, 12, 0));
    const monthlyReceiptsData = @json($monthlyReceiptsData ?? array_fill(0, 12, 0));
    const monthlyCollectionsData = monthlyPaymentsData.map((payment, index) => payment + (monthlyReceiptsData[index] || 0));

    console.log('بيانات المبيعات الشهرية:', monthlySalesData);
    console.log('بيانات التحصيلات :', monthlyPaymentsData);

    console.log('بيانات التحصيلات المحسوبة:', monthlyCollectionsData);
    console.log('بيانات المرتجعات:', monthlyReturnsData);

    // التحقق من صحة البيانات
    if (!Array.isArray(monthlySalesData) || monthlySalesData.length === 0) {
        console.warn('تحذير: بيانات المبيعات فارغة أو غير صحيحة');
    }
    if (!Array.isArray(monthlyCollectionsData) || monthlyCollectionsData.length === 0) {
        console.warn('تحذير: بيانات التحصيلات فارغة أو غير صحيحة');
    }
    if (!Array.isArray(monthlyReturnsData) || monthlyReturnsData.length === 0) {
        console.warn('تحذير: بيانات المرتجعات فارغة أو غير صحيحة');
    }

    const monthsArabic = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

    try {
        // 1. مخطط الفواتير (Sparkline)
        if (document.querySelector("#subscribe-gain-chart")) {
            new ApexCharts(document.querySelector("#subscribe-gain-chart"), {
                chart: {
                    type: 'area',
                    height: 100,
                    sparkline: { enabled: true },
                    animations: { enabled: true }
                },
                series: [{ data: monthlyInvoiceCountData }],
                colors: [colors.primary],
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0.9, opacityFrom: 0.7, opacityTo: 0.5, stops: [0, 80, 100] } },
                tooltip: { x: { show: false } }
            }).render();
        }

        // 2. مخطط المبيعات (Sparkline)
        if (document.querySelector("#orders-received-chart")) {
            new ApexCharts(document.querySelector("#orders-received-chart"), {
                chart: {
                    type: 'area',
                    height: 100,
                    sparkline: { enabled: true },
                    animations: { enabled: true }
                },
                series: [{ data: monthlySalesData }],
                colors: [colors.warning],
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0.9, opacityFrom: 0.7, opacityTo: 0.5, stops: [0, 80, 100] } },
                tooltip: { x: { show: false } }
            }).render();
        }

        // 2.5 مخطط التحصيلات (Sparkline)
        if (document.querySelector("#collections-chart")) {
            new ApexCharts(document.querySelector("#collections-chart"), {
                chart: {
                    type: 'area',
                    height: 100,
                    sparkline: { enabled: true },
                    animations: { enabled: true }
                },
                series: [{ data: monthlyCollectionsData || monthlyPaymentsData.map((p, i) => p + monthlyReceiptsData[i]) }],
                colors: [colors.info],
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0.9, opacityFrom: 0.7, opacityTo: 0.5, stops: [0, 80, 100] } },
                tooltip: { x: { show: false } }
            }).render();
        }

        // 2.6 مخطط المرتجعات (Sparkline)
        if (document.querySelector("#returns-chart")) {
            new ApexCharts(document.querySelector("#returns-chart"), {
                chart: {
                    type: 'area',
                    height: 100,
                    sparkline: { enabled: true },
                    animations: { enabled: true }
                },
                series: [{ data: monthlyReturnsData }],
                colors: [colors.danger],
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0.9, opacityFrom: 0.7, opacityTo: 0.5, stops: [0, 80, 100] } },
                tooltip: { x: { show: false } }
            }).render();
        }

        // 3. مخطط متوسط المبيعات
        if (document.querySelector("#avg-session-chart")) {
            new ApexCharts(document.querySelector("#avg-session-chart"), {
                chart: {
                    type: 'bar',
                    height: 220,
                    animations: { enabled: true }
                },
                series: [{ name: 'متوسط المبيعات', data: monthlyAvgSalesData }],
                colors: [colors.primary],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '25%',
                        borderRadius: 5
                    }
                },
                xaxis: { categories: monthsArabic },
                dataLabels: { enabled: false }
            }).render();
        }
// 5. مخطط ملخص المبيعات (Pie Chart بديل)
if (document.querySelector("#support-tracker-chart")) {
    const totalSalesVal = {{ $totalSales ?? 0 }};
    const totalPaymentsVal = {{ $totalPayments ?? 0 }};
    const totalReceiptsVal = {{ $totalReceipts ?? 0 }};
    const totalCollectionsVal = {{ $totalCollections ?? 0 }};

    console.log('بيانات الملخص:', {
        sales: totalSalesVal,
        payments: totalPaymentsVal,
        receipts: totalReceiptsVal,
        collections: totalCollectionsVal
    });

    // استخدام المبيعات والتحصيلات فقط
    const seriesData = [totalSalesVal, totalCollectionsVal];
    const labelsData = ['المبيعات الكلية', 'التحصيلات'];

    new ApexCharts(document.querySelector("#support-tracker-chart"), {
        chart: {
            type: 'donut',
            height: 280,
            animations: { enabled: true }
        },
        series: seriesData,
        labels: labelsData,
        colors: ['#28C76F', '#7367F0'],
        plotOptions: {
            pie: {
                donut: {
                    size: '60%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '14px',
                            fontWeight: 600,
                            color: '#373d3f'
                        },
                        value: {
                            show: true,
                            fontSize: '16px',
                            fontWeight: 700,
                            color: '#373d3f',
                            formatter: function (val) {
                                return val.toLocaleString('ar-EG') + ' ر.س';
                            }
                        },
                        total: {
                            show: true,
                            label: 'الإجمالي',
                            fontSize: '16px',
                            fontWeight: 700,
                            color: '#373d3f',
                            formatter: function (w) {
                                const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                return total.toLocaleString('ar-EG') + ' ر.س';
                            }
                        }
                    }
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '12px',
            labels: {
                colors: '#373d3f'
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val.toLocaleString('ar-EG') + ' ر.س';
                }
            }
        }
    }).render();

    console.log('تم تحميل مخطط ملخص المبيعات بنجاح');
}
        // 4. مخطط المبيعات والتحصيلات والمرتجعات (الشهري)
if (document.querySelector("#payments-receipts-chart")) {
    const chartElement = document.querySelector("#payments-receipts-chart");

    if (!chartElement) {
        console.warn('عنصر الرسم البياني غير موجود');
    } else {
        try {
            const options = {
                series: [
                    {
                        name: 'المبيعات',
                        data: monthlySalesData
                    },
                    {
                        name: 'التحصيلات',
                        data: monthlyCollectionsData
                    },
                    {
                        name: 'المرتجعات',
                        data: monthlyReturnsData
                    }
                ],
                chart: {
                    type: 'bar',
                    height: 400,
                    toolbar: {
                        show: true,
                        tools: {
                            download: true,
                            selection: true,
                            zoom: true,
                            zoomin: true,
                            zoomout: true,
                            pan: true,
                            reset: true
                        }
                    }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        endingShape: 'rounded',
                        borderRadius: 5
                    }
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: monthsArabic,
                    labels: {
                        style: {
                            colors: '#6b6b6b',
                            fontSize: '12px'
                        }
                    }
                },
                yaxis: {
                    title: {
                        text: 'المبلغ (ر.س)',
                        style: {
                            fontSize: '14px',
                            fontWeight: 'bold',
                            color: '#373d3f'
                        }
                    },
                    labels: {
                        formatter: function (value) {
                            return value.toLocaleString('ar-EG');
                        },
                        style: {
                            colors: '#6b6b6b',
                            fontSize: '12px'
                        }
                    }
                },
                fill: {
                    opacity: 1
                },
                colors: ['#7367F0', '#28C76F', '#EA5455'], // مبيعات بنفسجي، تحصيلات أخضر، مرتجعات أحمر
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val.toLocaleString('ar-EG') + " ر.س";
                        }
                    },
                    theme: 'light'
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'center',
                    offsetY: 0,
                    fontSize: '14px',
                    labels: {
                        colors: '#373d3f'
                    }
                },
                grid: {
                    borderColor: '#e0e0e0',
                    strokeDashArray: 4
                }
            };

            const chart = new ApexCharts(chartElement, options);
            chart.render();
            console.log('تم تحميل مخطط المبيعات والتحصيلات والمرتجعات بنجاح');
        } catch (error) {
            console.error('خطأ في تحميل مخطط المبيعات والتحصيلات والمرتجعات:', error);
        }
    }
}

        // 5. مخطط نمو المبيعات والمدفوعات وسندات القبض
        if (document.querySelector("#line-chart")) {
            const monthlyPaymentsData = @json($monthlyPaymentsData ?? array_fill(0, 12, 0));
            const monthlyReceiptsData = @json($monthlyReceiptsData ?? array_fill(0, 12, 0));

            new ApexCharts(document.querySelector("#line-chart"), {
                chart: {
                    type: 'line',
                    height: 365,
                    animations: { enabled: true }
                },
                series: [
                    { name: 'المبيعات', data: monthlySalesData },
                    { name: 'تحصيلات', data: monthlyPaymentsData },

                ],
                colors: ['#7367F0', '#28C76F', '#FFA500'],
                stroke: { width: 2.5, curve: 'smooth' },
                markers: { size: 5 },
                xaxis: { categories: monthsArabic }
            }).render();
        }


        // 7. مخطط Pie
        if (document.querySelector("#pie-chart") && topProductData.length > 0) {
            new ApexCharts(document.querySelector("#pie-chart"), {
                chart: {
                    type: 'pie',
                    height: 300,
                    animations: { enabled: true }
                },
                series: topProductData,
                labels: topProductLabels,
                colors: [colors.primary, colors.warning, colors.success, colors.info, colors.danger]
            }).render();
        }
// 10. مخطط المؤشر العام (Pie Chart)
// 10. مخطط المؤشر العام (Pie Chart)
if (document.querySelector("#indicator-chart")) {
    const totalSalesVal = {{ $totalSales ?? 0 }};
    const totalPaymentsVal = {{ $totalPayments ?? 0 }};
    const totalReturnsVal = {{ $totalReturns ?? 0 }};
    const totalCreditsVal = {{ $totalCredits ?? 0 }};

    console.log('بيانات المؤشر العام:', {
        sales: totalSalesVal,
        payments: totalPaymentsVal,
        returns: totalReturnsVal,
        credits: totalCreditsVal
    });

    // حساب النسب المئوية
    const total = totalSalesVal + totalPaymentsVal + totalReturnsVal + totalCreditsVal;
    const salesPercent = total > 0 ? (totalSalesVal / total) * 100 : 0;
    const paymentsPercent = total > 0 ? (totalPaymentsVal / total) * 100 : 0;
    const returnsPercent = total > 0 ? (totalReturnsVal / total) * 100 : 0;
    const creditsPercent = total > 0 ? (totalCreditsVal / total) * 100 : 0;

    new ApexCharts(document.querySelector("#indicator-chart"), {
        chart: {
            type: 'donut',
            height: 300,
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800
            }
        },
        series: [totalSalesVal, totalPaymentsVal, totalReturnsVal, totalCreditsVal],
        labels: [
            `المبيعات (${salesPercent.toFixed(1)}%)`,
            `التحصيلات (${paymentsPercent.toFixed(1)}%)`,
            `المرتجعات (${returnsPercent.toFixed(1)}%)`,

        ],
        colors: ['#7367F0', '#28C76F', '#EA5455', ],
        plotOptions: {
            pie: {
                donut: {
                    size: '60%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '13px',
                            fontWeight: 600,
                            color: '#6b6b6b'
                        },
                        value: {
                            show: true,
                            fontSize: '18px',
                            fontWeight: 700,
                            color: '#373d3f',
                            formatter: function (val) {
                                return val.toLocaleString('ar-SA');
                            }
                        },
                        total: {
                            show: true,
                            label: 'الإجمالي',
                            fontSize: '16px',
                            fontWeight: 700,
                            color: '#373d3f',
                            formatter: function (w) {
                                const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                return total.toLocaleString('ar-SA') + ' ر.س';
                            }
                        }
                    }
                }
            }
        },
        dataLabels: {
            enabled: true,
            style: {
                fontSize: '11px',
                fontWeight: 'bold'
            },
            formatter: function(val, opts) {
                return opts.w.config.labels[opts.seriesIndex];
            },
            dropShadow: {
                enabled: false
            }
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '12px',
            labels: {
                colors: '#6b6b6b',
                useSeriesColors: false
            },
            itemMargin: {
                horizontal: 8,
                vertical: 4
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val.toLocaleString('ar-SA') + ' ر.س';
                }
            }
        },
        responsive: [{
            breakpoint: 1200,
            options: {
                chart: {
                    height: 280
                },
                legend: {
                    position: 'bottom',
                    fontSize: '11px'
                }
            }
        }, {
            breakpoint: 768,
            options: {
                chart: {
                    height: 250
                },
                legend: {
                    position: 'bottom',
                    fontSize: '10px'
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '55%'
                        }
                    }
                }
            }
        }, {
            breakpoint: 576,
            options: {
                chart: {
                    height: 220
                },
                legend: {
                    position: 'bottom',
                    fontSize: '9px',
                    itemMargin: {
                        horizontal: 5,
                        vertical: 2
                    }
                },
                dataLabels: {
                    style: {
                        fontSize: '9px'
                    }
                }
            }
        }]
    }).render();

    console.log('تم تحميل مخطط المؤشر العام بنجاح');
}
        // 8. مخطط إحصائيات المبيعات (Radar)
       // 8. مخطط إحصائيات المبيعات حسب الفرع (Bar Chart)
// 8. مخطط إحصائيات المبيعات والمدفوعات والمرتجعات حسب الفرع (Bar Chart)
// 8. مخطط الرادار لمقارنة أداء الفروع (بدون تطبيع)
if (document.querySelector("#radar-chart")) {
    const branchSalesLabels = @json($branchSalesLabels ?? []);
    const branchSalesData = @json($branchSalesData ?? []);
    const branchReturnsData = @json($branchReturnsData ?? []);
    const branchPaymentsData = @json($branchPaymentsData ?? []);

    console.log('بيانات الرادار للفروع:', {
        labels: branchSalesLabels,
        sales: branchSalesData,
        payments: branchPaymentsData,
        returns: branchReturnsData
    });

    // إذا لم تكن هناك بيانات، عرض رسالة
    if (branchSalesLabels.length === 0) {
        document.querySelector("#radar-chart").innerHTML = `
            <div class="text-center p-4">
                <div class="text-muted">
                    <i class="feather icon-bar-chart-2" style="font-size: 48px;"></i>
                    <p class="mt-2">لا توجد بيانات متاحة</p>
                </div>
            </div>
        `;
    } else {
        const branchChartHeight = Math.min(720, Math.max(380, branchSalesLabels.length * 56));
        new ApexCharts(document.querySelector("#radar-chart"), {
            chart: {
                type: 'bar',
                height: branchChartHeight,
                fontFamily: 'inherit',
                foreColor: '#6e6b7b',
                rtl: false,
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: true
                    }
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 650
                }
            },
            series: [
                { name: 'المبيعات', data: branchSalesData },
                { name: 'التحصيلات', data: branchPaymentsData },
                { name: 'المرتجعات', data: branchReturnsData }
            ],
            colors: ['#7367F0', '#28C76F', '#EA5455'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '58%',
                    borderRadius: 6,
                    dataLabels: { position: 'top' }
                }
            },
            dataLabels: { enabled: false },
            stroke: {
                show: true,
                width: 1,
                colors: ['transparent']
            },
            grid: {
                borderColor: '#eceef5',
                strokeDashArray: 4,
                padding: { top: 8, right: 12, bottom: 4, left: 12 }
            },
            xaxis: {
                categories: branchSalesLabels,
                labels: {
                    rotate: -35,
                    rotateAlways: false,
                    hideOverlappingLabels: true,
                    trim: true,
                    maxHeight: 120,
                    style: {
                        fontSize: '11px',
                        fontWeight: 500,
                        colors: '#5e5873'
                    },
                    formatter: function(value) {
                        if (typeof value !== 'string') {
                            return value;
                        }
                        return value.length > 22 ? value.substring(0, 22) + '…' : value;
                    }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                min: 0,
                title: {
                    text: 'المبلغ (ر.س)',
                    style: { fontSize: '12px', fontWeight: 600, color: '#a5a3ae' }
                },
                labels: {
                    formatter: function(val) {
                        return kpiFormatLatinNumber(val, 0, 0);
                    },
                    style: { fontSize: '11px', colors: '#8e8b9a' }
                }
            },
            tooltip: {
                shared: true,
                intersect: false,
                theme: 'light',
                style: { fontSize: '13px' },
                y: {
                    formatter: function(val) {
                        return kpiFormatLatinNumber(val, 2, 0) + ' ر.س';
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                offsetY: 4,
                fontSize: '13px',
                fontWeight: 500,
                markers: { width: 10, height: 10, radius: 3 },
                itemMargin: { horizontal: 14, vertical: 4 },
                labels: { colors: '#5e5873' }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.12,
                    opacityFrom: 0.92,
                    opacityTo: 0.78,
                    stops: [0, 90, 100]
                }
            }
        }).render();

        console.log('تم تحميل مخطط مقارنة الفروع الرئيسية بنجاح');
    }
}

        // 9. مخطط المنتجات الأكثر مبيعاً
        if (document.querySelector("#horizontal-bar-chart") && topProductData.length > 0) {
            new ApexCharts(document.querySelector("#horizontal-bar-chart"), {
                chart: {
                    type: 'bar',
                    height: 300,
                    animations: { enabled: true }
                },
                series: [{ name: 'الكمية المباعة', data: topProductData }],
                xaxis: { categories: topProductLabels },
                colors: [colors.primary],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        columnWidth: '25%',
                        borderRadius: 5
                    }
                },
                dataLabels: { enabled: true }
            }).render();
        }

        console.log('تم تحميل جميع الرسوم البيانية بنجاح');

    } catch (error) {
        console.error('خطأ في تحميل الرسوم البيانية:', error);
        console.error('اسم الخطأ:', error.name);
        console.error('رسالة الخطأ:', error.message);
        console.error('Stack:', error.stack);
    }
}

// 11. مخطط المجموعات (Bar Chart) — ترتيب شجري، فلتر منفصل عن فلتر الصفحة الرئيسي
function initializeGroupsChart() {
    if (document.querySelector("#groups-chart")) {
        const groupChartData = @json($groupChartData ?? []);
        let tooltipSourceRows = Array.isArray(groupChartData) ? groupChartData.slice() : [];

        console.log('بيانات المجموعات:', groupChartData);

        // التحقق من صحة البيانات
        groupChartData.forEach(group => {
            const calculatedCollections = group.payments + group.receipts;
            const difference = Math.abs(group.collections - calculatedCollections);

            if (difference > 0.01) {
                console.warn(`تفاوت في حساب التحصيلات للمجموعة ${group.region}: ${group.collections} vs ${calculatedCollections}`);
            }
        });

        // إبناء المجموعة + كل الأحفاد (للفلتر المحلي على المخطط)
        function collectDescendantRegionIds(allRows, rootId) {
            const byParent = {};
            allRows.forEach(r => {
                const p = r.parent_id;
                if (p == null || p === undefined) {
                    return;
                }
                if (!byParent[p]) {
                    byParent[p] = [];
                }
                byParent[p].push(r.region_id);
            });
            const ids = new Set([rootId]);
            const frontier = [rootId];
            while (frontier.length) {
                const id = frontier.pop();
                (byParent[id] || []).forEach(cid => {
                    if (!ids.has(cid)) {
                        ids.add(cid);
                        frontier.push(cid);
                    }
                });
            }
            return ids;
        }

        // إذا لم تكن هناك بيانات، عرض رسالة
        if (groupChartData.length === 0) {
            document.querySelector("#groups-chart").innerHTML = `
                <div class="text-center p-4">
                    <div class="text-muted">
                        <i class="feather icon-bar-chart-2" style="font-size: 48px;"></i>
                        <p class="mt-2">لا توجد بيانات متاحة للمجموعات</p>
                    </div>
                </div>
            `;
            return;
        }

        groupChartData.sort(function(a, b) {
            var ca = parseFloat(a.collections) || 0;
            var cb = parseFloat(b.collections) || 0;
            if (cb !== ca) {
                return cb - ca;
            }
            return String(a.path_label || a.region || '').localeCompare(String(b.path_label || b.region || ''));
        });

        // تحضير البيانات للرسم البياني (بعد ترتيب تنازلي حسب التحصيلات المباشرة)
        const prepareChartData = (filteredData = groupChartData) => {
            const labels = filteredData.map(function(item) {
                const raw = item.chart_label || item.region || '';
                return typeof raw === 'string' && raw.length > 26 ? raw.substring(0, 26) + '…' : raw;
            });
            const salesData = filteredData.map(item => item.sales);
            const returnsData = filteredData.map(item => item.returns);
            const collectionsData = filteredData.map(item => item.collections);
            const paymentsData = filteredData.map(item => item.payments);
            const receiptsData = filteredData.map(item => item.receipts);

            return {
                labels,
                salesData,
                returnsData,
                collectionsData,
                paymentsData,
                receiptsData
            };
        };

        const initialData = prepareChartData();
        tooltipSourceRows = groupChartData.slice();

        const groupsChartHeight = Math.min(780, Math.max(400, groupChartData.length * 48));

        const chartOptions = {
            chart: {
                type: 'bar',
                height: groupsChartHeight,
                fontFamily: 'inherit',
                foreColor: '#6e6b7b',
                rtl: false,
                animations: { enabled: true, easing: 'easeinout', speed: 600 },
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: true
                    }
                }
            },
            series: [
                { name: 'المبيعات', data: initialData.salesData },
                { name: 'التحصيلات', data: initialData.collectionsData },
                { name: 'المرتجعات', data: initialData.returnsData }
            ],
            colors: ['#7367F0', '#28C76F', '#EA5455'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 6,
                    dataLabels: { position: 'top' }
                }
            },
            dataLabels: { enabled: false },
            stroke: {
                show: true,
                width: 1,
                colors: ['transparent']
            },
            grid: {
                borderColor: '#eceef5',
                strokeDashArray: 4,
                padding: { top: 8, right: 10, bottom: 8, left: 10 }
            },
            xaxis: {
                categories: initialData.labels,
                labels: {
                    rotate: -35,
                    hideOverlappingLabels: true,
                    trim: true,
                    maxHeight: 110,
                    style: {
                        colors: '#5e5873',
                        fontSize: '10px',
                        fontWeight: 500
                    }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                min: 0,
                title: {
                    text: 'المبلغ (ر.س)',
                    style: { fontSize: '12px', fontWeight: 600, color: '#a5a3ae' }
                },
                labels: {
                    formatter: function(value) {
                        return kpiFormatLatinNumber(value, 0, 0);
                    },
                    style: { colors: '#8e8b9a', fontSize: '11px' }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.1,
                    opacityFrom: 0.9,
                    opacityTo: 0.75,
                    stops: [0, 92, 100]
                }
            },
            tooltip: {
                shared: true,
                intersect: false,
                theme: 'light',
                style: { fontSize: '13px' },
                y: {
                    formatter: function(val) {
                        return kpiFormatLatinNumber(val, 2, 0) + ' ر.س';
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                offsetY: 4,
                fontSize: '13px',
                fontWeight: 500,
                markers: { width: 10, height: 10, radius: 3 },
                itemMargin: { horizontal: 12, vertical: 4 },
                labels: { colors: '#5e5873' }
            },
            responsive: [
                {
                    breakpoint: 1200,
                    options: {
                        chart: { height: Math.min(720, Math.max(380, groupChartData.length * 44)) },
                        plotOptions: { bar: { columnWidth: '52%' } }
                    }
                },
                {
                    breakpoint: 768,
                    options: {
                        chart: { height: Math.min(680, Math.max(360, groupChartData.length * 42)) },
                        legend: { fontSize: '11px' },
                        xaxis: { labels: { rotate: -45, style: { fontSize: '9px' } } }
                    }
                },
                {
                    breakpoint: 576,
                    options: {
                        chart: { height: Math.min(640, Math.max(340, groupChartData.length * 40)) },
                        legend: { fontSize: '10px' },
                        plotOptions: { bar: { columnWidth: '48%' } }
                    }
                }
            ]
        };

        const chart = new ApexCharts(document.querySelector("#groups-chart"), chartOptions);
        chart.render();

        console.log('تم تحميل مخطط المجموعات بنجاح');
    }
}

// استدعاء الدالة في التهيئة
initializeGroupsChart();

// =================== فلتر الفترة الزمنية ===================
function setupPeriodFilter() {
    const periodBtns = document.querySelectorAll('.period-btn');
    const customDateInputs = document.getElementById('customDateInputs');
    const applyCustomFilter = document.getElementById('applyCustomFilter');

    periodBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const period = this.dataset.period;

            // إزالة active من جميع الأزرار
            periodBtns.forEach(b => b.classList.remove('active'));
            // إضافة active للزر المحدد
            this.classList.add('active');

            if (period === 'custom') {
                // إظهار حقول التاريخ المخصص
                customDateInputs.classList.add('show');
            } else {
                // إخفاء حقول التاريخ المخصص
                customDateInputs.classList.remove('show');
                // تطبيق الفلتر مباشرة
                applyPeriodFilter(period);
            }
        });
    });

    // تطبيق الفلتر المخصص
    if (applyCustomFilter) {
        applyCustomFilter.addEventListener('click', function() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            if (!startDate || !endDate) {
                alert('يرجى تحديد تاريخ البداية والنهاية');
                return;
            }

            applyPeriodFilter('custom', startDate, endDate);
        });
    }
}

function applyPeriodFilter(period, startDate = null, endDate = null) {
    // الحصول على المعاملات الحالية
    const urlParams = new URLSearchParams(window.location.search);

    // تحديث أو إضافة معامل الفترة
    urlParams.set('period', period);

    if (period === 'custom' && startDate && endDate) {
        urlParams.set('start_date', startDate);
        urlParams.set('end_date', endDate);
    } else {
        urlParams.delete('start_date');
        urlParams.delete('end_date');
    }

    // إعادة التوجيه مع المعاملات الجديدة
    window.location.href = window.location.pathname + '?' + urlParams.toString();
}

// فلتر الشجرة الموحّد — إعادة تحميل الصفحة كاملة
function setupBranchHierarchyFilter() {
    const $filters = $('.branch-hierarchy-filter');
    if (!$filters.length) {
        return;
    }

    $filters.each(function() {
        const $filter = $(this);
        if ($filter.data('branchHierarchyBound')) {
            return;
        }

        $filter.select2({
            dir: 'rtl',
            width: '100%',
            placeholder: 'كل الفروع والمجموعات',
            allowClear: true
        });

        $filter.on('change', function() {
            const branchFilterId = $(this).val();
            const urlParams = new URLSearchParams(window.location.search);

            urlParams.delete('branch_id');
            urlParams.delete('region_group_id');

            if (branchFilterId) {
                urlParams.set('branch_filter_id', branchFilterId);
            } else {
                urlParams.delete('branch_filter_id');
            }

            window.location.href = window.location.pathname + '?' + urlParams.toString();
        });

        $filter.data('branchHierarchyBound', true);
    });
}

// بدء التحميل عندما تكون الصفحة جاهزة (بعد jQuery و Select2 من master)
$(function() {
    setupBranchHierarchyFilter();
    setupPeriodFilter();
    initializeAllCharts();
});

// إعادة التحميل عند تغيير حجم النافذة
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        // إعادة تحميل الرسوم البيانية للتأكد من التكيف مع الحجم الجديد
        initializeAllCharts();
    }, 250);
});
</script>
@endpush
