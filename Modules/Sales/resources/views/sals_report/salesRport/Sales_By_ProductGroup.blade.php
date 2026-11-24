@extends('sales::master')

@section('title')
    تقرير المبيعات حسب مجموعة المنتج والتصنيف والوحدات
@stop

{{--
    القسم هنا `css` لا `styles`: `sales::master` يكدّس `styles` بـ`@stack`
    ولا يلتقط الأقسام — فكانت ورقةُ `account-reports.css` وأنماطُ الفلاتر
    تسقطان صامتتين، وتُعرَض الشاشة بلا نمطٍ واحد من أنماطها. آخرُ شاشةٍ
    في الوحدة بقيت على الاسم الخطأ.
--}}
@section('css')
@include('sales::sals_report.report_partials.styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
@include('sales::sals_report.partials.report_filters_balance_like_styles')
@endsection

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير المبيعات حسب مجموعة المنتج والتصنيف</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('salesReports.index') }}">تقارير المبيعات</a></li>
                            <li class="breadcrumb-item active">تقرير المبيعات حسب المجموعة</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body rpt">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <!-- قسم الفلاتر -->
        <div class="card filter-card no-print">
            <div class="card-header">
                <h4 class="card-title"><i class="fa fa-filter"></i> خيارات البحث</h4>
                <a class="heading-elements-toggle"><i class="la la-ellipsis-v font-medium-3"></i></a>
                <div class="heading-elements">
                    <ul class="list-inline mb-0">
                        <li><a data-action="collapse"><i class="ft-minus"></i></a></li>
                    </ul>
                </div>
            </div>
            <div class="card-content collapse show">
                <div class="card-body">
                    <form method="GET" action="{{ route('salesReports.byProductGroup') }}" id="filterForm">
                        <!-- أزرار الفترات الزمنية -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="period-tabs">
                                    <button type="button" class="btn period-btn {{ request('period') == '1' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="1">
                                        <i class="fa fa-calendar-day"></i> يوم
                                    </button>
                                    <button type="button" class="btn period-btn {{ request('period') == '7' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="7">
                                        <i class="fa fa-calendar-week"></i> أسبوع
                                    </button>
                                    <button type="button" class="btn period-btn {{ (request('period') == '30' || !request('period')) ? 'btn-primary' : 'btn-outline-primary' }}" data-period="30">
                                        <i class="fa fa-calendar-alt"></i> شهر
                                    </button>
                                    <button type="button" class="btn period-btn {{ request('period') == '90' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="90">
                                        <i class="fa fa-calendar"></i> 3 أشهر
                                    </button>
                                    <button type="button" class="btn period-btn {{ request('period') == '180' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="180">
                                        <i class="fa fa-calendar"></i> 6 أشهر
                                    </button>
                                    <button type="button" class="btn period-btn {{ request('period') == '365' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="365">
                                        <i class="fa fa-calendar"></i> سنة
                                    </button>
                                    <button type="button" class="btn period-btn {{ request('period') == 'custom' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="custom">
                                        <i class="fa fa-calendar-plus"></i> مخصص
                                    </button>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="period" id="periodInput" value="{{ request('period') ?? '30' }}">

                        {{-- البحث الهرمي بالشجرة — أول فلتر بالصفحة --}}
                        <input type="hidden" name="cascade_node" id="cascade_node" value="{{ request('cascade_node') }}">
                        <div class="row mt-2" id="cascadeContainer">
                            {{-- Cascade selects تُبنى ديناميكياً عبر JS --}}
                        </div>



                        <div class="row mt-2">
                            <div class="col-md-12">
                                <div class="group-by-tabs">
                                    <a href="{{ route('salesReports.byProductGroup', array_merge(request()->all(), ['group_by' => 'product_group'])) }}"
                                       class="btn {{ (request('group_by') == 'product_group' || !request('group_by')) ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fa fa-boxes"></i> مجموعة المنتج
                                    </a>
                                    <a href="{{ route('salesReports.byProductGroup', array_merge(request()->all(), ['group_by' => 'category'])) }}"
                                       class="btn {{ request('group_by') == 'category' ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fa fa-sitemap"></i> التصنيف
                                    </a>
                                    <a href="{{ route('salesReports.byProductGroup', array_merge(request()->all(), ['group_by' => 'unit'])) }}"
                                       class="btn {{ request('group_by') == 'unit' ? 'btn-primary' : 'btn-outline-primary' }}">
                                        <i class="fa fa-balance-scale"></i> الوحدة
                                    </a>
                                </div>
                                <input type="hidden" name="group_by" value="{{ request('group_by') ?? 'product_group' }}">
                            </div>
                        </div>

                        <div class="row mt-2">
                            <!-- Date From -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>من تاريخ</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from', $fromDate->format('Y-m-d')) }}">
                            </div>

                            <!-- Date To -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>إلى تاريخ</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to', $toDate->format('Y-m-d')) }}">
                            </div>

                            <!-- Branch Filter -->
                            @if($isMainBranch)
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الفرع</label>
                                <select name="branch_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            <!-- Product Group Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>مجموعة المنتج</label>
                                <select name="product_group_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($productGroups as $group)
                                        <option value="{{ $group->id }}" {{ request('product_group_id') == $group->id ? 'selected' : '' }}>
                                            {{ $group->name }}
                                        </option>
                                        @foreach($group->children as $child)
                                            <option value="{{ $child->id }}" {{ request('product_group_id') == $child->id ? 'selected' : '' }}>
                                                &nbsp;&nbsp;↳ {{ $child->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Category Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>التصنيف</label>
                                <select name="category_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                        @foreach($category->children as $child)
                                            <option value="{{ $child->id }}" {{ request('category_id') == $child->id ? 'selected' : '' }}>
                                                &nbsp;&nbsp;↳ {{ $child->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>

                            <!-- Unit Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الوحدة</label>
                                <select name="unit_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}" {{ request('unit_id') == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Product Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>المنتج</label>
                                <select name="product_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Client Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>العميل</label>
                                <select name="client_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                            {{ $client->trade_name ?? $client->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Employee Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الموظف</label>
                                <select name="employee_id" class="form-control select2">
                                    <option value="">الكل</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->first_name }} {{ $employee->middle_name ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Invoice Type Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>نوع الفاتورة</label>
                                <select name="invoice_type" class="form-control select2">
                                    @foreach($invoiceTypes as $key => $label)
                                        <option value="{{ $key }}" {{ ($selectedInvoiceType ?? 'all') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- View Type -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>نوع العرض</label>
                                <div class="btn-group d-block" role="group">
                                    <button type="submit" name="view_type" value="summary" class="btn {{ $isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                                        <i class="fa fa-list"></i> الملخص
                                    </button>
                                    <button type="submit" name="view_type" value="details" class="btn {{ !$isSummary ? 'btn-primary' : 'btn-outline-primary' }} btn-view-toggle">
                                        <i class="fa fa-th-list"></i> التفاصيل
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <!-- Search Button -->
                            <div class="col-md-6 col-sm-6 mb-1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-search"></i> بحث
                                </button>
                                <a href="{{ route('salesReports.byProductGroup') }}" class="btn btn-outline-secondary">
                                    <i class="fa fa-refresh"></i> إلغاء البحث
                                </a>
                            </div>

                            <!-- Export Buttons -->
                            <div class="col-md-6 col-sm-6 mb-1 text-right">
                                <button type="button" class="btn btn-success" id="printBtn" onclick="printReport()">
                                    <i class="fa fa-print"></i> طباعة
                                </button>
                                <button type="button" class="btn btn-info" id="exportExcel" onclick="exportToExcel()">
                                    <i class="fa fa-file-excel-o"></i> Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card income-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-file-text text-success font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1">
                            {{ $totals['sales_count'] ?? 0 }}
                        </h2>
                        <p class="mb-0">عدد عمليات البيع</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card expense-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-trending-up text-success font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1">
                            {{ number_format($totals['sales_amount'] ?? 0, 2) }}
                        </h2>
                        <p class="mb-0">إجمالي المبيعات (ريال)</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card account-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-warning p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-percent text-warning font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1">
                            {{ number_format($totals['returns_amount'] ?? 0, 2) }}
                        </h2>
                        <p class="mb-0">المرتجعات ({{ $totals['returns_count'] ?? 0 }})</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card treasury-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-dollar-sign text-primary font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1">
                            {{ number_format($totals['net_amount'] ?? 0, 2) }}
                        </h2>
                        <p class="mb-0">صافي المبيعات (ريال)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-primary mr-1">
                            @switch(request('group_by', 'product_group'))
                                @case('product_group') مجموعة المنتج @break
                                @case('category') التصنيف @break
                                @case('unit') الوحدة @break
                            @endswitch
                        </span>
                        <span class="text-muted">الفترة: {{ $fromDate->format('d/m/Y') }} إلى {{ $toDate->format('d/m/Y') }}</span>
                    </div>
                    <small class="text-muted">آخر تحديث: {{ now()->format('Y-m-d H:i') }}</small>
                </div>
            </div>
        </div>

        <!-- Chart -->
        @if(count($reportData) > 0)
        <div class="card mt-2">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fa fa-chart-bar"></i> الرسم البياني للمبيعات
                </h4>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge badge-light-success mr-2">
                        <i class="fa fa-circle"></i> المبيعات
                    </span>
                    <span class="badge badge-light-danger mr-2">
                        <i class="fa fa-circle"></i> المرتجعات
                    </span>
                    <span class="badge badge-light-primary">
                        <i class="fa fa-circle"></i> الصافي
                    </span>
                </div>
            </div>
        </div>
        @endif

        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fa fa-table"></i>
                    {{ $isSummary ? 'ملخص المبيعات' : 'تفاصيل المبيعات' }} حسب
                    @switch(request('group_by', 'product_group'))
                        @case('product_group') مجموعة المنتج @break
                        @case('category') التصنيف @break
                        @case('unit') الوحدة @break
                    @endswitch
                </h4>
            </div>
            <div class="card-content">
                <div class="card-body">
                    @if($isSummary)
                        {{-- جدول الملخص --}}
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 5%">#</th>
                                        <th class="text-center" style="width: 20%">
                                            @switch(request('group_by', 'product_group'))
                                                @case('product_group') المجموعة @break
                                                @case('category') التصنيف @break
                                                @case('unit') الوحدة @break
                                            @endswitch
                                        </th>
                                        <th class="text-center" style="width: 10%">عمليات البيع</th>
                                        <th class="text-center" style="width: 13%">الكمية المباعة</th>
                                        <th class="text-center" style="width: 13%">إجمالي المبيعات</th>
                                        <th class="text-center" style="width: 10%">المرتجعات</th>
                                        <th class="text-center" style="width: 13%">مبلغ المرتجع</th>
                                        <th class="text-center" style="width: 16%">صافي المبيعات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rowNum = 0; @endphp
                                    @forelse($reportData as $key => $data)
                                        @php $rowNum++; @endphp
                                        <tr>
                                            <td class="text-center">{{ $rowNum }}</td>
                                            <td>
                                                @if($data['parent_name'])
                                                    <span class="badge-parent">
                                                        <i class="fa fa-level-up-alt fa-rotate-90"></i>
                                                        {{ $data['parent_name'] }}
                                                    </span>
                                                @endif
                                                <strong>
                                                    <i class="fa fa-{{ $data['type'] == 'product_group' ? 'boxes' : ($data['type'] == 'category' ? 'sitemap' : 'balance-scale') }} text-info mr-1"></i>
                                                    {{ $data['name'] }}
                                                </strong>
                                                @if(isset($data['hierarchy']['full_path']) && $data['hierarchy']['full_path'])
                                                    <br>
                                                    <small class="text-muted">{{ $data['hierarchy']['full_path'] }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-light-success">{{ $data['sales_count'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="unit-info">
                                                    <span class="quantity">{{ number_format($data['sales_quantity'], 2) }}</span>
                                                    <small class="conversion d-block">({{ number_format($data['sales_quantity_base'], 2) }} أساسية)</small>
                                                </div>
                                            </td>
                                            <td class="text-center text-success font-weight-bold">
                                                {{ number_format($data['sales_amount'], 2) }}
                                            </td>
                                            <td class="text-center">
                                                @if($data['returns_count'] > 0)
                                                    <span class="badge badge-light-danger">{{ $data['returns_count'] }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center text-danger">
                                                @if($data['returns_amount'] > 0)
                                                    ({{ number_format($data['returns_amount'], 2) }})
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center text-primary font-weight-bold">
                                                {{ number_format($data['net_amount'], 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <i class="fa fa-info-circle text-muted fa-3x mb-3 d-block"></i>
                                                <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                                                <p class="text-muted">يرجى تحديد معايير البحث والضغط على "بحث" لعرض التقرير</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(count($reportData) > 0)
                                <tfoot class="table-warning">
                                    <tr>
                                        <td colspan="2" class="font-weight-bold">الإجمالي العام</td>
                                        <td class="text-center font-weight-bold">{{ $totals['sales_count'] ?? 0 }}</td>
                                        <td class="text-center font-weight-bold">{{ number_format($totals['sales_quantity_base'] ?? 0, 2) }}</td>
                                        <td class="text-center text-success font-weight-bold">{{ number_format($totals['sales_amount'] ?? 0, 2) }} ر.س</td>
                                        <td class="text-center font-weight-bold">{{ $totals['returns_count'] ?? 0 }}</td>
                                        <td class="text-center text-danger font-weight-bold">{{ number_format($totals['returns_amount'] ?? 0, 2) }} ر.س</td>
                                        <td class="text-center text-primary font-weight-bold">{{ number_format($totals['net_amount'] ?? 0, 2) }} ر.س</td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @else
                        {{-- جدول التفاصيل --}}
                        <div class="table-responsive">
                            <table class="table table-bordered" id="reportTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">رقم الفاتورة</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">العميل</th>
                                        <th class="text-center">المنتج</th>
                                        <th class="text-center">الوحدة</th>
                                        <th class="text-center">الكمية</th>
                                        <th class="text-center">السعر</th>
                                        <th class="text-center">الخصم</th>
                                        <th class="text-center">الإجمالي</th>
                                        <th class="text-center">النوع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $globalRowNum = 0; @endphp
                                    @forelse($reportData as $key => $data)
                                        <!-- رأس المجموعة -->
                                        <tr class="group-header-row">
                                            <td colspan="11">
                                                <strong>
                                                    <i class="fa fa-{{ $data['type'] == 'product_group' ? 'boxes' : ($data['type'] == 'category' ? 'sitemap' : 'balance-scale') }} text-info mr-1"></i>
                                                    {{ $data['name'] }}
                                                    @if($data['parent_name'])
                                                        <span class="badge-parent">{{ $data['parent_name'] }}</span>
                                                    @endif
                                                    <span class="badge badge-info mr-2">{{ $data['sales_count'] }} عملية</span>
                                                    <span class="badge badge-success">{{ number_format($data['net_amount'], 2) }} ر.س</span>
                                                </strong>
                                            </td>
                                        </tr>

                                        @foreach($data['items'] as $item)
                                            @php $globalRowNum++; @endphp
                                            <tr>
                                                <td class="text-center">{{ $globalRowNum }}</td>
                                                <td class="text-center">
                                                    <a href="#" class="text-primary">{{ $item['invoice_code'] }}</a>
                                                </td>
                                                <td class="text-center">{{ $item['invoice_date'] }}</td>
                                                <td>{{ $item['client_name'] }}</td>
                                                <td>{{ $item['product_name'] }}</td>
                                                <td class="text-center">
                                                    <span class="badge-unit">{{ $item['unit_name'] }}</span>
                                                </td>
                                                <td class="text-center">
                                                    {{ number_format($item['quantity'], 2) }}
                                                    @if($item['conversion_factor'] > 1)
                                                        <small class="text-muted d-block">({{ number_format($item['quantity_base'], 2) }} أساسية)</small>
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ number_format($item['unit_price'], 2) }}</td>
                                                <td class="text-center text-warning">{{ number_format($item['discount'], 2) }}</td>
                                                <td class="text-center font-weight-bold {{ $item['type'] == 'return' ? 'text-danger' : 'text-success' }}">
                                                    {{ $item['type'] == 'return' ? '-' : '' }}{{ number_format($item['total'], 2) }}
                                                </td>
                                                <td class="text-center">
                                                    @if($item['type'] == 'sale')
                                                        <span class="badge badge-success">بيع</span>
                                                    @else
                                                        <span class="badge badge-danger">مرتجع</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach

                                        <!-- صف المجموع الفرعي -->
                                        <tr class="subtotal-row">
                                            <td colspan="6" class="text-left"><strong>إجمالي {{ $data['name'] }}</strong></td>
                                            <td class="text-center"><strong>{{ number_format($data['sales_quantity'], 2) }}</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-center text-primary"><strong>{{ number_format($data['net_amount'], 2) }}</strong></td>
                                            <td></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center py-5">
                                                <i class="fa fa-info-circle text-muted fa-3x mb-3 d-block"></i>
                                                <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                                                <p class="text-muted">يرجى تحديد معايير البحث والضغط على "بحث" لعرض التقرير</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(count($reportData) > 0)
                                <tfoot class="table-warning">
                                    <tr>
                                        <td colspan="6" class="text-left"><strong>الإجمالي العام</strong></td>
                                        <td class="text-center"><strong>{{ number_format($totals['sales_quantity_base'] ?? 0, 2) }}</strong></td>
                                        <td></td>
                                        <td></td>
                                        <td class="text-center text-primary"><strong>{{ number_format($totals['net_amount'] ?? 0, 2) }} ر.س</strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        let salesChart;

        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function changePeriod(period) {
            period = String(period);
            $('#periodInput').val(period);
            $('.period-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $(`.period-btn[data-period="${period}"]`).removeClass('btn-outline-primary').addClass('btn-primary');

            if (period !== 'custom') {
                // الخادم يحسب المدى من `period` بنفس دالة الفهرس؛ يُفرَّغ
                // الحقلان كي لا يُقدَّما عليه فتنزلق الفترةُ عمّا أُضيء زرُّه.
                $('#date_from').val('');
                $('#date_to').val('');
                $('#filterForm').submit();
            } else {
                $('#date_from').focus();
            }
        }

        $(document).ready(function() {
            // تهيئة Select2
            if ($.fn.select2) {
                $('.select2').each(function() {
                    var $el = $(this);
                    if ($el.data('select2')) {
                        $el.select2('destroy');
                    }
                });
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

            $('.period-btn').on('click', function() {
                changePeriod($(this).data('period'));
            });

            // تهيئة الرسم البياني
            initChart();
        });

        function initChart() {
            const ctx = document.getElementById('salesChart');
            if (!ctx) return;

            if (salesChart) salesChart.destroy();

            const chartData = @json($chartData);

            salesChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'المبيعات',
                            data: chartData.sales,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1,
                            order: 2
                        },
                        {
                            label: 'المرتجعات',
                            data: chartData.returns,
                            backgroundColor: 'rgba(234, 84, 85, 0.7)',
                            borderColor: 'rgba(234, 84, 85, 1)',
                            borderWidth: 1,
                            order: 3
                        },
                        {
                            label: 'الصافي',
                            data: chartData.net,
                            type: 'line',
                            fill: false,
                            borderColor: 'rgba(0, 123, 255, 1)',
                            backgroundColor: 'rgba(0, 123, 255, 0.2)',
                            borderWidth: 3,
                            tension: 0.3,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 12 },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatNumber(context.raw) + ' ر.س';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11 },
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatNumber(value) + ' ر.س';
                                }
                            }
                        }
                    }
                }
            });
        }

        function formatNumber(n, decimals = 2) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

        // =====================================
        // تصدير Excel
        // =====================================
        function exportToExcel() {
            const isSummary = {{ $isSummary ? 'true' : 'false' }};

            var totals = {
                sales_count: {{ $totals['sales_count'] ?? 0 }},
                sales_quantity_base: {{ $totals['sales_quantity_base'] ?? 0 }},
                sales_amount: {{ $totals['sales_amount'] ?? 0 }},
                returns_count: {{ $totals['returns_count'] ?? 0 }},
                returns_amount: {{ $totals['returns_amount'] ?? 0 }},
                net_amount: {{ $totals['net_amount'] ?? 0 }}
            };

            const groupByLabel = '{{ request("group_by", "product_group") == "product_group" ? "مجموعة المنتج" : (request("group_by") == "category" ? "التصنيف" : "الوحدة") }}';

            var tableData = [];
            var rowNumber = 1;

            if (isSummary) {
                // تصدير الملخص
                @if(!empty($reportData))
                @foreach($reportData as $key => $data)
                tableData.push({
                    values: [
                        rowNumber++,
                        '{{ addslashes($data["name"]) }}',
                        {{ $data['sales_count'] }},
                        formatNumber({{ $data['sales_quantity_base'] }}),
                        formatNumber({{ $data['sales_amount'] }}),
                        {{ $data['returns_count'] }},
                        formatNumber({{ $data['returns_amount'] }}),
                        formatNumber({{ $data['net_amount'] }})
                    ],
                    cellStyles: Array(8).fill({ alignment: { horizontal: 'center' } })
                });
                @endforeach
                @endif

                const config = {
                    fileName: 'تقرير_المبيعات_حسب_' + groupByLabel,
                    mainTitle: 'تقرير المبيعات حسب ' + groupByLabel,
                    subTitle: 'نظام المبيعات المتكامل',

                    reportInfo: {
                        'التجميع حسب': groupByLabel,
                        'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                        'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                    },

                    statisticsCards: [
                        { title: 'عدد عمليات البيع', value: formatNumber(totals.sales_count, 0), unit: ' عملية', color: 'success', borderColor: 'success' },
                        { title: 'إجمالي المبيعات', value: formatNumber(totals.sales_amount), unit: ' ' + window.AppCurrency.symbol, color: 'success', borderColor: 'success' },
                        { title: 'المرتجعات', value: formatNumber(totals.returns_amount), unit: ' ' + window.AppCurrency.symbol, color: 'danger', borderColor: 'danger' },
                        { title: 'صافي المبيعات', value: formatNumber(totals.net_amount), unit: ' ' + window.AppCurrency.symbol, color: 'primary', borderColor: 'primary' }
                    ],

                    tableTitle: 'ملخص المبيعات حسب ' + groupByLabel,
                    tableHeaders: ['#', groupByLabel, 'عمليات البيع', 'الكمية', 'المبيعات', 'المرتجعات', 'مبلغ المرتجع', 'الصافي'],
                    columnWidths: [6, 30, 12, 12, 15, 12, 15, 15],
                    tableData: tableData,
                    summaryRows: [{
                        label: 'الإجمالي',
                        values: ['الإجمالي', '', totals.sales_count, formatNumber(totals.sales_quantity_base), formatNumber(totals.sales_amount), totals.returns_count, formatNumber(totals.returns_amount), formatNumber(totals.net_amount)],
                        mergeFrom: 1, mergeTo: 2, color: 'primary', alignment: 'center'
                    }],
                    buttonSelector: '#exportExcel',
                    successMessage: 'تم تصدير التقرير بنجاح!'
                };

                if (typeof exportToExcelProfessional === 'function') {
                    exportToExcelProfessional(config);
                } else {
                    alert('جاري تحميل مكتبة التصدير...');
                }
            } else {
                // تصدير التفاصيل - قراءة البيانات من الجدول
                $('#reportTable tbody tr').each(function() {
                    var cells = $(this).find('td');
                    // تخطي صفوف رؤوس المجموعات والصفوف الفارغة
                    if (cells.length < 10 || $(this).hasClass('group-header-row') || $(this).hasClass('subtotal-row')) return;

                    tableData.push({
                        values: [
                            rowNumber++,
                            $(cells[1]).text().trim(),
                            $(cells[2]).text().trim(),
                            $(cells[3]).text().trim(),
                            $(cells[4]).text().trim(),
                            $(cells[5]).text().trim(),
                            $(cells[6]).text().trim(),
                            $(cells[7]).text().trim(),
                            $(cells[8]).text().trim(),
                            $(cells[9]).text().trim(),
                            $(cells[10]).text().trim()
                        ],
                        cellStyles: Array(11).fill({ alignment: { horizontal: 'center' } })
                    });
                });

                const config = {
                    fileName: 'تقرير_المبيعات_التفصيلي_حسب_' + groupByLabel,
                    mainTitle: 'تقرير المبيعات التفصيلي حسب ' + groupByLabel,
                    subTitle: 'نظام المبيعات المتكامل',

                    reportInfo: {
                        'التجميع حسب': groupByLabel,
                        'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}',
                        'تاريخ التصدير': new Date().toLocaleDateString('ar-SA')
                    },

                    statisticsCards: [
                        { title: 'عدد عمليات البيع', value: formatNumber(totals.sales_count, 0), unit: ' عملية', color: 'success', borderColor: 'success' },
                        { title: 'إجمالي المبيعات', value: formatNumber(totals.sales_amount), unit: ' ' + window.AppCurrency.symbol, color: 'success', borderColor: 'success' },
                        { title: 'المرتجعات', value: formatNumber(totals.returns_amount), unit: ' ' + window.AppCurrency.symbol, color: 'danger', borderColor: 'danger' },
                        { title: 'صافي المبيعات', value: formatNumber(totals.net_amount), unit: ' ' + window.AppCurrency.symbol, color: 'primary', borderColor: 'primary' }
                    ],

                    tableTitle: 'تفاصيل المبيعات حسب ' + groupByLabel,
                    tableHeaders: ['#', 'رقم الفاتورة', 'التاريخ', 'العميل', 'المنتج', 'الوحدة', 'الكمية', 'السعر', 'الخصم', 'الإجمالي', 'النوع'],
                    columnWidths: [5, 12, 10, 15, 15, 10, 10, 10, 8, 12, 8],
                    tableData: tableData,
                    summaryRows: [{
                        label: 'الإجمالي',
                        values: ['الإجمالي', '', '', '', '', '', '', '', '', formatNumber(totals.net_amount), ''],
                        mergeFrom: 1, mergeTo: 9, color: 'primary', alignment: 'center'
                    }],
                    buttonSelector: '#exportExcel',
                    successMessage: 'تم تصدير التقرير بنجاح!'
                };

                if (typeof exportToExcelProfessional === 'function') {
                    exportToExcelProfessional(config);
                } else {
                    alert('جاري تحميل مكتبة التصدير...');
                }
            }
        }

        // =====================================
        // الطباعة
        // =====================================
        function printReport() {
            const isSummary = {{ $isSummary ? 'true' : 'false' }};

            const totals = {
                sales_count: {{ $totals['sales_count'] ?? 0 }},
                sales_amount: {{ $totals['sales_amount'] ?? 0 }},
                returns_amount: {{ $totals['returns_amount'] ?? 0 }},
                net_amount: {{ $totals['net_amount'] ?? 0 }}
            };

            const groupByLabel = '{{ request("group_by", "product_group") == "product_group" ? "مجموعة المنتج" : (request("group_by") == "category" ? "التصنيف" : "الوحدة") }}';

            if (typeof printTable === 'function') {
                if (isSummary) {
                    // طباعة الملخص
                    printTable({
                        tableSelector: '#reportTable tbody tr',
                        tfootSelector: '#reportTable tfoot',
                        title: 'تقرير المبيعات حسب ' + groupByLabel + ' - ملخص',
                        infoCards: [
                            { label: 'عمليات البيع', value: totals.sales_count + ' عملية' },
                            { label: 'إجمالي المبيعات', value: formatNumber(totals.sales_amount) + ' ' + window.AppCurrency.symbol, class: 'text-success' },
                            { label: 'المرتجعات', value: formatNumber(totals.returns_amount) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                            { label: 'صافي المبيعات', value: formatNumber(totals.net_amount) + ' ' + window.AppCurrency.symbol, class: 'text-primary' }
                        ],
                        filterInfo: {
                            'التجميع حسب': groupByLabel,
                            'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}'
                        },
                        tableHeaders: ['#', groupByLabel, 'عمليات البيع', 'الكمية', 'المبيعات', 'المرتجعات', 'مبلغ المرتجع', 'الصافي'],
                        columnWidths: ['5%', '25%', '10%', '12%', '12%', '10%', '12%', '14%'],
                        windowWidth: 1200,
                        windowHeight: 800
                    });
                } else {
                    // طباعة التفاصيل
                    printTable({
                        tableSelector: '#reportTable tbody tr',
                        tfootSelector: '#reportTable tfoot',
                        title: 'تقرير المبيعات حسب ' + groupByLabel + ' - تفصيلي',
                        infoCards: [
                            { label: 'عمليات البيع', value: totals.sales_count + ' عملية' },
                            { label: 'إجمالي المبيعات', value: formatNumber(totals.sales_amount) + ' ' + window.AppCurrency.symbol, class: 'text-success' },
                            { label: 'المرتجعات', value: formatNumber(totals.returns_amount) + ' ' + window.AppCurrency.symbol, class: 'text-danger' },
                            { label: 'صافي المبيعات', value: formatNumber(totals.net_amount) + ' ' + window.AppCurrency.symbol, class: 'text-primary' }
                        ],
                        filterInfo: {
                            'التجميع حسب': groupByLabel,
                            'الفترة': 'من {{ $fromDate->format("Y-m-d") }} إلى {{ $toDate->format("Y-m-d") }}'
                        },
                        tableHeaders: ['#', 'رقم الفاتورة', 'التاريخ', 'العميل', 'المنتج', 'الوحدة', 'الكمية', 'السعر', 'الخصم', 'الإجمالي', 'النوع'],
                        columnWidths: ['4%', '10%', '8%', '12%', '14%', '8%', '8%', '8%', '8%', '10%', '8%'],
                        windowWidth: 1400,
                        windowHeight: 900
                    });
                }
            } else {
                window.print();
            }
        }

        // ============================================================
        // البحث الهرمي بالشجرة (Cascade Selects) — بنفس تقرير الأرصدة
        // ============================================================
        const CASCADE_BRANCHES = @json($cascadeBranches ?? []);
        // فرع الهيدر (users.branch_id): جذر الشجرة الذي اختاره المستخدم من الأعلى
        const CASCADE_HEADER_BRANCH = @json($cascadeHeaderBranchId ?? (auth()->user()->branch_id ?? null));

        (function() {
            function parseValue(val) {
                if (!val) return null;
                if (val.indexOf('b_') === 0) return { id: parseInt(val.substring(2)) };
                return null;
            }
            function getRootBranches() {
                return CASCADE_BRANCHES.filter(function(b) { return !b.parent_id; });
            }
            function getChildren(id) {
                return CASCADE_BRANCHES.filter(function(b) { return b.parent_id == id; });
            }
            function hasChildren(id) { return getChildren(id).length > 0; }
            function branchExists(id) { return CASCADE_BRANCHES.some(function (b) { return b.id == id; }); }
            function getAncestorPath(id) {
                var path = [], current = { id: parseInt(id) }, safety = 20;
                while (current && safety-- > 0) {
                    path.unshift(current);
                    var b = CASCADE_BRANCHES.find(function(x) { return x.id == current.id; });
                    current = (b && b.parent_id) ? { id: b.parent_id } : null;
                }
                return path;
            }
            function addLevel(level, parentId, selectedValue) {
                var options = (level === 0) ? getRootBranches() : getChildren(parentId);
                if (options.length === 0) return false;
                var labelText = (level === 0) ? 'الفرع الرئيسي (شجرة)' : ('المستوى ' + (level + 1));
                var $col = $('<div class="col-md-3 col-sm-6 mb-1 cascade-col"></div>');
                $col.append($('<label></label>').text(labelText));
                var $select = $('<select class="form-control select2 cascade-select" data-level="' + level + '"></select>');
                $select.append('<option value="">-- اختر --</option>');
                options.forEach(function(b) {
                    var label = b.name;
                    if (level > 0) { label += (b.is_territory == 1) ? ' [مجموعة]' : ' [فرع]'; }
                    var sel = (selectedValue === ('b_' + b.id)) ? ' selected' : '';
                    $select.append('<option value="b_' + b.id + '"' + sel + '>' + label + '</option>');
                });
                $col.append($select);
                $('#cascadeContainer').append($col);
                if ($.fn.select2) {
                    $select.select2({
                        dir: 'rtl',
                        language: { noResults: function() { return "لا توجد نتائج"; } },
                        allowClear: true,
                        width: '100%'
                    });
                }
                $select.on('change', function() {
                    var val = $(this).val();
                    $('#cascadeContainer .cascade-col').each(function() {
                        var $wrap = $(this);
                        var lvl = parseInt($wrap.find('select').attr('data-level'), 10);
                        if (lvl > level) {
                            var $s = $wrap.find('select');
                            if ($.fn.select2 && $s.length && $s.data('select2')) {
                                $s.select2('destroy');
                            }
                            $wrap.remove();
                        }
                    });
                    if (val) {
                        $('#cascade_node').val(val);
                        var $rgClear = $('#region_group_id');
                        if ($rgClear.length) {
                            $rgClear.val('').trigger('change');
                        }
                        var parsed = parseValue(val);
                        if (parsed && hasChildren(parsed.id)) {
                            addLevel(level + 1, parsed.id, null);
                        }
                    } else {
                        var $prev = $('#cascadeContainer select[data-level="' + (level - 1) + '"]');
                        $('#cascade_node').val($prev.length ? ($prev.val() || '') : '');
                    }
                });
                return true;
            }
            function initCascade() {
                $('#cascadeContainer .cascade-col select').each(function() {
                    var $s = $(this);
                    if ($.fn.select2 && $s.data('select2')) {
                        $s.select2('destroy');
                    }
                });
                $('#cascadeContainer').empty();
                var initialValue = $('#cascade_node').val();
                var path = [];
                if (initialValue) {
                    var parsed = parseValue(initialValue);
                    if (parsed) path = getAncestorPath(parsed.id);
                } else if (CASCADE_HEADER_BRANCH && branchExists(CASCADE_HEADER_BRANCH)) {
                    // لم يُرسل الطلب أي عقدة: نبدأ من فرع الهيدر منتقى في المستوى
                    // الأول (التقرير مقيَّد به أصلاً) فلا يبقى إلا اختيار ما تحته.
                    path = getAncestorPath(CASCADE_HEADER_BRANCH);
                }
                var firstSel = path[0] ? ('b_' + path[0].id) : null;
                addLevel(0, null, firstSel);
                for (var i = 0; i < path.length; i++) {
                    var next = path[i + 1] || null;
                    var selVal = next ? ('b_' + next.id) : null;
                    addLevel(i + 1, path[i].id, selVal);
                }
            }
            $(document).ready(function() {
                initCascade();
                var $rg = $('#region_group_id');
                if ($rg.length) {
                    $rg.on('change', function() {
                        if ($(this).val()) {
                            $('#cascade_node').val('');
                            initCascade();
                        }
                    });
                }
            });
        })();
    </script>
@endsection
