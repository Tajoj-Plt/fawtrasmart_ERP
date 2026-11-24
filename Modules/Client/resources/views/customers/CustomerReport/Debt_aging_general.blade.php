@extends('client::master')

@section('title')
    تقرير أعمار ديون الأستاذ العام
@stop

@section('styles')
<link rel="stylesheet" href="{{ asset('css/account-reports.css') }}">
<style>
    .period-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }

    .period-tabs .btn {
        flex: 1;
        border-radius: 6px;
        padding: 10px 15px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .stat-card {
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .filter-card {
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .chart-container {
        position: relative;
        height: 400px;
    }

    .btn-view-toggle {
        padding: 8px 20px;
        border-radius: 6px;
    }

    .aging-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        display: inline-block;
        font-weight: 600;
        font-size: 0.8rem;
        color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }

    .client-link {
        color: #667eea;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .client-link:hover {
        color: #764ba2;
        text-decoration: underline;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .stat-card {
            break-inside: avoid;
        }
    }
</style>
@endsection

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير أعمار ديون الأستاذ العام</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="#">التقارير</a></li>
                            <li class="breadcrumb-item active">تقرير أعمار ديون الأستاذ العام</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
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
                    <form method="GET" id="filterForm">
                        <!-- Tabs للفترات الزمنية -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="period-tabs">
                                    <button type="button" class="btn period-btn {{ request('period') == '1' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="1">
                                        <i class="fa fa-calendar-day"></i> يوم
                                    </button>
                                    <button type="button" class="btn period-btn {{ request('period') == '7' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="7">
                                        <i class="fa fa-calendar-week"></i> أسبوع
                                    </button>
                                    <button type="button" class="btn period-btn {{ request('period') == '30' ? 'btn-primary' : 'btn-outline-primary' }}" data-period="30">
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
                        <input type="hidden" name="aging_filter" id="agingFilterInput" value="">

                        <div class="row mt-2">
                            <!-- Date From -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>من تاريخ</label>
                                <input type="date" name="from_date" id="from_date" class="form-control" value="{{ request('from_date') ?? now()->startOfMonth()->format('Y-m-d') }}">
                            </div>

                            <!-- Date To -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>إلى تاريخ</label>
                                <input type="date" name="to_date" id="to_date" class="form-control" value="{{ request('to_date') ?? now()->format('Y-m-d') }}">
                            </div>

                            <!-- Customer Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>العميل</label>
                                <select name="customer" id="customer" class="form-control select2">
                                    <option value="">جميع العملاء</option>
                                    @if(isset($customers))
                                        @foreach($customers as $client)
                                            <option value="{{ $client->id }}" {{ request('customer') == $client->id ? 'selected' : '' }}>
                                                {{ $client->trade_name ?? ($client->first_name . ' ' . $client->last_name) }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- Branch Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>الفرع</label>
                                <select name="branch" id="branch" class="form-control select2">
                                    <option value="">جميع الفروع</option>
                                    @if(isset($branches))
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Group Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>المجموعة</label>
                                <select name="region_group" id="region_group" class="form-control select2">
                                    <option value="">جميع المجموعات</option>
                                    @if(isset($regionGroups))
                                        @foreach($regionGroups as $group)
                                            <option value="{{ $group->id }}" {{ request('region_group') == $group->id ? 'selected' : '' }}>
                                                {{ $group->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- Sales Manager Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>مسؤول المبيعات</label>
                                <select name="sales_manager" id="sales_manager" class="form-control select2">
                                    <option value="">جميع مسؤولي المبيعات</option>
                                    @if(isset($salesManagers))
                                        @foreach($salesManagers as $manager)
                                            <option value="{{ $manager->id }}" {{ request('sales_manager') == $manager->id ? 'selected' : '' }}>
                                                {{ $manager->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- Added By Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>تمت الإضافة بواسطة</label>
                                <select name="added_by" id="added_by" class="form-control select2">
                                    <option value="">الكل</option>
                                    @if(isset($salesManagers))
                                        @foreach($salesManagers as $user)
                                            <option value="{{ $user->id }}" {{ request('added_by') == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <!-- Status Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>حالة العميل</label>
                                <select name="status" id="status" class="form-control select2">
                                    <option value="">جميع الحالات</option>
                                    @if(isset($statuses))
                                        @foreach($statuses as $status)
                                            <option value="{{ $status->id }}" {{ request('status') == $status->id ? 'selected' : '' }}>
                                                {{ $status->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Customer Type Filter -->
                            <div class="col-md-3 col-sm-6 mb-1">
                                <label>تصنيف العميل</label>
                                <select name="category_client" id="category_client" class="form-control select2">
                                    <option value="">جميع التصنيفات</option>
                                    @if(isset($categories))
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ request('category_client') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <!-- Search Button -->
                            <div class="col-md-6 col-sm-6 mb-1">
                                <button type="button" id="filterBtn" class="btn btn-primary">
                                    <i class="fa fa-search"></i> بحث
                                </button>
                                <button type="button" id="resetBtn" class="btn btn-secondary">
                                    <i class="fa fa-refresh"></i> إعادة تعيين
                                </button>
                            </div>

                            <!-- Export Buttons -->
                            <div class="col-md-6 col-sm-6 mb-1 text-right">
                                <button type="button" class="btn btn-outline-success" id="exportExcel">
                                    <i class="feather icon-file-text"></i> تصدير Excel
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="printBtn">
                                    <i class="feather icon-printer"></i> طباعة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards - Aging Periods -->
        <div class="row">
            <div class="col-lg-2 col-md-4 col-6">
                <div class="card stat-card" data-filter="today" style="cursor: pointer;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-calendar text-success font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="todayAmount">0.00</h2>
                        <p class="mb-0">اليوم</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <div class="card stat-card" data-filter="1-30" style="cursor: pointer;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-info p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-clock text-info font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="days1to30">0.00</h2>
                        <p class="mb-0">1-30 يوم</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <div class="card stat-card" data-filter="31-60" style="cursor: pointer;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-warning p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-alert-circle text-warning font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="days31to60">0.00</h2>
                        <p class="mb-0">31-60 يوم</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <div class="card stat-card" data-filter="61-90" style="cursor: pointer;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-danger p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-alert-triangle text-danger font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="days61to90">0.00</h2>
                        <p class="mb-0">61-90 يوم</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <div class="card stat-card" data-filter="91-120" style="cursor: pointer;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-x-circle text-primary font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="days91to120">0.00</h2>
                        <p class="mb-0">91-120 يوم</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-6">
                <div class="card stat-card" data-filter="120+" style="cursor: pointer;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-dark p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-slash text-dark font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="daysOver120">0.00</h2>
                        <p class="mb-0">+120 يوم</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Totals Summary -->
        <div class="row">
            <div class="col-lg-4 col-md-6 col-12">
                <div class="card stat-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-users text-success font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="totalClients">0</h2>
                        <p class="mb-0">إجمالي العملاء</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-12">
                <div class="card stat-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-info p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-file-text text-info font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="totalRecords">0</h2>
                        <p class="mb-0">إجمالي السجلات</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-12">
                <div class="card stat-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-danger p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-trending-up text-danger font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="totalDue">0.00</h2>
                        <p class="mb-0">إجمالي المستحق (ريال)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card mt-2">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-primary mr-1">الفترة المحددة</span>
                        <span class="text-muted">من <span id="displayFromDate">-</span> إلى <span id="displayToDate">-</span></span>
                    </div>
                    <small class="text-muted">آخر تحديث: {{ now()->format('Y-m-d H:i') }}</small>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card mt-2" id="chartCard" style="display: none;">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fa fa-chart-bar"></i> الرسم البياني لأعمار الديون
                </h4>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="agingChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card mt-2" id="reportCard">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="fa fa-table"></i> تفاصيل أعمار ديون الأستاذ العام
                </h4>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="agingTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th class="text-center">كود العميل</th>
                                    <th class="text-center">اسم العميل</th>
                                    <th class="text-center">الفرع</th>
                                    <th class="text-center">المجموعة</th>
                                    <th class="text-center">الحي</th>
                                    <th class="text-center">الحالة</th>
                                    <th class="text-center">اليوم</th>
                                    <th class="text-center">1-30</th>
                                    <th class="text-center">31-60</th>
                                    <th class="text-center">61-90</th>
                                    <th class="text-center">91-120</th>
                                    <th class="text-center">+120</th>
                                    <th class="text-center">الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody id="reportTableBody">
                                <tr>
                                    <td colspan="14" class="text-center py-4">
                                        <i class="fa fa-spinner fa-spin fa-2x mb-2 d-block"></i>
                                        <p class="text-muted mb-0">جاري تحميل البيانات...</p>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="reportTableFooter" style="display: none;">
                                <tr class="bg-success text-white font-weight-bold">
                                    <td colspan="7" class="text-center">الإجمالي الكلي</td>
                                    <td class="text-center" id="footerToday">0.00</td>
                                    <td class="text-center" id="footer1to30">0.00</td>
                                    <td class="text-center" id="footer31to60">0.00</td>
                                    <td class="text-center" id="footer61to90">0.00</td>
                                    <td class="text-center" id="footer91to120">0.00</td>
                                    <td class="text-center" id="footerOver120">0.00</td>
                                    <td class="text-center" id="footerTotal">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="{{ asset('js/print-table.js') }}"></script>
    <script src="{{ asset('js/excel-export-helper.js') }}"></script>

    <script>
        let agingChart;
        let branchesData = [];
        let regionsData = [];
        let currentReportData = null;

        $(document).ready(function() {
            // تهيئة Select2 (تجنب التهيئة المزدوجة مع form-select2 في الـ layout)
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

            // تحميل بيانات الفروع والمجموعات
            loadBranchesAndRegions();

            // تحميل البيانات الأولية
            loadReportData();

            // أحداث الفلاتر
            $('#filterBtn').click(function() {
                loadReportData();
            });

            $('#resetBtn').click(resetFilters);
            $('#exportExcel').click(exportDebtAgingToExcel);
            $('#printBtn').click(printDebtAgingReport);

            // أحداث أزرار الفترات
            $('.period-btn').click(function() {
                const period = $(this).data('period');
                changePeriod(period);
            });

            // فلترة المجموعات عند اختيار الفرع
            $('#branch').change(function() {
                filterRegionsByBranch();
                loadReportData();
            });

            // إعادة تحميل عند تغيير أي فلتر
            $('#customer, #sales_manager, #from_date, #to_date, #status, #region_group, #category_client, #added_by').change(function() {
                loadReportData();
            });

            // أحداث النقر على كروت الإحصائيات
            $('.stat-card[data-filter]').click(function() {
                var filter = $(this).data('filter');
                $('.stat-card').removeClass('border-primary');
                $(this).addClass('border-primary');
                $('#agingFilterInput').val(filter);
                loadReportData();
            });
        });

        function filterRegionsByBranch() {
            const selectedBranchId = $('#branch').val();
            const $regionSelect = $('#region_group');

            // البحث عن بيانات الفرع المختار لمعرفة ما إذا كان رئيسياً
            const selectedBranch = branchesData.find(b => b.id == selectedBranchId);
            const isMainBranch = selectedBranch ? (selectedBranch.is_main == 1) : false;

            $regionSelect.empty();
            $regionSelect.append('<option value="">جميع المجموعات</option>');

            // إذا لم يتم اختيار فرع، أو كان الفرع المختار رئيسياً، نعرض جميع المجموعات
            if (!selectedBranchId || isMainBranch) {
                regionsData.forEach(function(region) {
                    $regionSelect.append(`<option value="${region.id}">${region.name}</option>`);
                });
            } else {
                // تصفية المجموعات حسب الفرع المختار (للفرع الذي ليس رئيسياً)
                const filteredRegions = regionsData.filter(function(region) {
                    return parseInt(region.branch_id) === parseInt(selectedBranchId);
                });

                if (filteredRegions.length > 0) {
                    filteredRegions.forEach(function(region) {
                        $regionSelect.append(`<option value="${region.id}">${region.name}</option>`);
                    });
                } else {
                    $regionSelect.append('<option disabled>لا توجد مجموعات لهذا الفرع</option>');
                }
            }

            if ($.fn.select2) {
                $regionSelect.trigger('change.select2');
            }
        }

        function loadBranchesAndRegions() {
            $.ajax({
                url: '{{ route('ClientReport.getBranchesRegionData') }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        branchesData = response.branches || [];
                        regionsData = response.regions || [];

                        const currentBranch = $('#branch').val();
                        $('#branch').empty();
                        $('#branch').append('<option value="">جميع الفروع</option>');

                        branchesData.forEach(function(branch) {
                            const selected = branch.id == currentBranch ? 'selected' : '';
                            $('#branch').append(`<option value="${branch.id}" ${selected}>${branch.name}</option>`);
                        });

                        if ($.fn.select2) {
                            $('#branch').trigger('change.select2');
                        }

                        if (currentBranch) {
                            filterRegionsByBranch();
                        }
                    }
                },
                error: function(error) {
                    console.error('خطأ في تحميل بيانات الفروع:', error);
                }
            });
        }

        function changePeriod(period) {
            $('#periodInput').val(period);
            $('#agingFilterInput').val('');
            $('.stat-card').removeClass('border-primary');

            $('.period-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $(`.period-btn[data-period="${period}"]`).removeClass('btn-outline-primary').addClass('btn-primary');

            if (period !== 'custom') {
                const today = new Date();
                const startDate = new Date(today);

                if (period !== 'custom') {
                    startDate.setDate(startDate.getDate() - parseInt(period));
                    $('#from_date').val(formatDateForInput(startDate));
                    $('#to_date').val(formatDateForInput(today));
                }
            }

            loadReportData();
        }

        function loadReportData() {
            showTableLoading();
            $('#filterBtn').prop('disabled', true);

            const formData = {
                customer: $('#customer').val(),
                branch: $('#branch').val(),
                region_group: $('#region_group').val(),
                sales_manager: $('#sales_manager').val(),
                added_by: $('#added_by').val(),
                category_client: $('#category_client').val(),
                status: $('#status').val(),
                aging_filter: $('#agingFilterInput').val(),
                from_date: $('#from_date').val(),
                to_date: $('#to_date').val()
            };

            $.ajax({
                url: '{{ route('ClientReport.debtAgingGeneralLedgerAjax') }}',
                method: 'GET',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        currentReportData = response;
                        updateReportDisplay(response);
                    } else {
                        showNoDataMessage();
                    }
                },
                error: function(error) {
                    console.error('خطأ في تحميل البيانات:', error);
                    showNoDataMessage();
                },
                complete: function() {
                    $('#filterBtn').prop('disabled', false);
                }
            });
        }

        function showTableLoading() {
            const loadingHtml = `
                <tr>
                    <td colspan="14" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">جاري التحميل...</span>
                        </div>
                        <p class="mt-2 text-muted">جاري تحميل البيانات...</p>
                    </td>
                </tr>
            `;
            $('#reportTableBody').html(loadingHtml);
        }

        function updateReportDisplay(data) {
            // تحديث إحصائيات الأعمار
            $('#todayAmount').text(formatNumber(data.totals.today || 0));
            $('#days1to30').text(formatNumber(data.totals.days1to30 || 0));
            $('#days31to60').text(formatNumber(data.totals.days31to60 || 0));
            $('#days61to90').text(formatNumber(data.totals.days61to90 || 0));
            $('#days91to120').text(formatNumber(data.totals.days91to120 || 0));
            $('#daysOver120').text(formatNumber(data.totals.daysOver120 || 0));

            // تحديث الإجماليات
            $('#totalClients').text(data.customers_count || 0);
            $('#totalRecords').text(data.records_count || 0);
            $('#totalDue').text(formatNumber(data.totals.total_due || 0));

            // تحديث التواريخ المعروضة
            if (data.from_date) {
                $('#displayFromDate').text(formatDateDisplay(data.from_date));
            }
            if (data.to_date) {
                $('#displayToDate').text(formatDateDisplay(data.to_date));
            }

            // تحديث الجدول
            updateTableBody(data.data || []);

            // تحديث الرسم البياني
            if (data.chart_data && data.chart_data.aging_periods) {
                updateChart(data.chart_data);
            } else {
                $('#chartCard').hide();
                if (agingChart) {
                    agingChart.destroy();
                    agingChart = null;
                }
            }

            // تحديث الفوتر
            $('#footerToday').text(formatNumber(data.totals.today || 0));
            $('#footer1to30').text(formatNumber(data.totals.days1to30 || 0));
            $('#footer31to60').text(formatNumber(data.totals.days31to60 || 0));
            $('#footer61to90').text(formatNumber(data.totals.days61to90 || 0));
            $('#footer91to120').text(formatNumber(data.totals.days91to120 || 0));
            $('#footerOver120').text(formatNumber(data.totals.daysOver120 || 0));
            $('#footerTotal').text(formatNumber(data.totals.total_due || 0));
            $('#reportTableFooter').show();
        }

        function updateTableBody(data) {
            let tableHtml = '';
            let index = 0;

            if (!data || data.length === 0) {
                showNoDataMessage();
                return;
            }

            data.forEach(item => {
                index++;

                let statusStyle = item.status_color ?
                    'background-color: ' + item.status_color + '; color: white;' :
                    'background-color: #6c757d; color: white;';

                tableHtml += `
                    <tr>
                        <td class="text-center">${index}</td>
                        <td class="text-center"><span class="badge badge-info">${item.client_code || '-'}</span></td>
                        <td>
                            ${item.client_id ?
                                `<a href="/clients/${item.client_id}" class="client-link" target="_blank">${item.client_name || '-'}</a>` :
                                `<span class="text-muted">${item.client_name || '-'}</span>`
                            }
                        </td>
                        <td><span class="badge badge-secondary">${item.branch || '-'}</span></td>
                        <td>${item.group || '-'}</td>
                        <td>${item.neighborhood || '-'}</td>
                        <td class="text-center">
                            <span class="badge" style="${statusStyle}">${item.status || '-'}</span>
                        </td>
                        <td class="text-center">${formatNumber(item.today || 0)}</td>
                        <td class="text-center">${formatNumber(item.days1to30 || 0)}</td>
                        <td class="text-center">${formatNumber(item.days31to60 || 0)}</td>
                        <td class="text-center">${formatNumber(item.days61to90 || 0)}</td>
                        <td class="text-center">${formatNumber(item.days91to120 || 0)}</td>
                        <td class="text-center">${formatNumber(item.daysOver120 || 0)}</td>
                        <td class="text-center font-weight-bold text-primary">${formatNumber(item.total_due || 0)}</td>
                    </tr>
                `;
            });

            $('#reportTableBody').html(tableHtml);
        }

        function showNoDataMessage() {
            const tableHtml = `
                <tr>
                    <td colspan="14" class="text-center py-5">
                        <i class="fa fa-info-circle text-muted fa-3x mb-3 d-block"></i>
                        <h5 class="text-muted">لا توجد بيانات للعرض</h5>
                        <p class="text-muted">يرجى تحديد معايير البحث والضغط على "بحث" لعرض التقرير</p>
                    </td>
                </tr>
            `;
            $('#reportTableBody').html(tableHtml);
            $('#reportTableFooter').hide();
        }

        function updateChart(chartData) {
            const ctx = document.getElementById('agingChart');
            if (!ctx) return;

            $('#chartCard').show();

            if (agingChart) {
                agingChart.destroy();
            }

            const labels = chartData.aging_periods || ['اليوم', '1-30 يوم', '31-60 يوم', '61-90 يوم', '91-120 يوم', '+120 يوم'];
            const values = chartData.aging_values || [0, 0, 0, 0, 0, 0];

            agingChart = new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'أعمار الديون',
                            data: values,
                            backgroundColor: [
                                'rgba(40, 167, 69, 0.7)',
                                'rgba(23, 162, 184, 0.7)',
                                'rgba(255, 193, 7, 0.7)',
                                'rgba(220, 53, 69, 0.7)',
                                'rgba(102, 126, 234, 0.7)',
                                'rgba(52, 58, 64, 0.7)'
                            ],
                            borderColor: [
                                'rgba(40, 167, 69, 1)',
                                'rgba(23, 162, 184, 1)',
                                'rgba(255, 193, 7, 1)',
                                'rgba(220, 53, 69, 1)',
                                'rgba(102, 126, 234, 1)',
                                'rgba(52, 58, 64, 1)'
                            ],
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
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
                            grid: { display: false }
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

        function formatNumber(n) {
            return parseFloat(n || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDateDisplay(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('ar-SA');
        }

        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function resetFilters() {
            $('#customer').val(null).trigger('change');
            $('#group').val(null).trigger('change');
            $('#branch').val(null).trigger('change');
            $('#sales_manager').val(null).trigger('change');
            $('#customer_type').val(null).trigger('change');
            $('#status').val(null).trigger('change');
            $('#periodInput').val('30');
            $('#agingFilterInput').val('');

            $('.stat-card').removeClass('border-primary');
            $('.period-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $('.period-btn[data-period="30"]').removeClass('btn-outline-primary').addClass('btn-primary');

            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            $('#from_date').val(formatDateForInput(firstDay));
            $('#to_date').val(formatDateForInput(today));

            currentReportData = null;
            loadReportData();
        }

        // دالة الطباعة المحسنة
        function printDebtAgingReport() {
            // تحقق من وجود بيانات للطباعة فقط
            const tableRows = document.querySelectorAll('#agingTable tbody tr');
            if (tableRows.length === 0 || (tableRows.length === 1 && tableRows[0].querySelector('td[colspan]'))) {
                alert('لا توجد بيانات للطباعة. يرجى تحميل البيانات أولاً.');
                return;
            }

            // اجمع بيانات الفلاتر
            const dateFrom = $('#from_date').val() || 'غير محدد';
            const dateTo = $('#to_date').val() || 'غير محدد';
            const branchName = $('#branch option:selected').text().trim() || 'جميع الفروع';
            const customerName = $('#customer option:selected').text().trim() || 'جميع العملاء';
            const groupName = $('#region_group option:selected').text().trim() || 'جميع المجموعات';

            // اطبع بنفس منطق الحركات المالية
            printTable({
                tableSelector: '#agingTable tbody tr',
                tfootSelector: '#agingTable tfoot',
                title: 'تقرير أعمار ديون الأستاذ العام',
                infoCards: [
                    { label: 'اليوم', value: $('#todayAmount').text() + ' ر.س' },
                    { label: '1-30 يوم', value: $('#days1to30').text() + ' ر.س' },
                    { label: '31-60 يوم', value: $('#days31to60').text() + ' ر.س' },
                    { label: '61-90 يوم', value: $('#days61to90').text() + ' ر.س' },
                    { label: '91-120 يوم', value: $('#days91to120').text() + ' ر.س' },
                    { label: '+120 يوم', value: $('#daysOver120').text() + ' ر.س' },
                    { label: 'إجمالي المستحق', value: $('#totalDue').text() + ' ر.س', class: 'text-danger fw-bold' }
                ],
                filterInfo: {
                    'الفترة': 'من ' + dateFrom + ' إلى ' + dateTo,
                    'الفرع': branchName,
                    'العميل': customerName,
                    'المجموعة': groupName
                },
                tableHeaders: ['#', 'كود العميل', 'اسم العميل', 'الفرع', 'المجموعة', 'الحي', 'الحالة', 'اليوم', '1-30', '31-60', '61-90', '91-120', '+120', 'الإجمالي'],
                columnWidths: ['3%', '7%', '12%', '8%', '8%', '8%', '6%', '7%', '7%', '7%', '7%', '7%', '7%', '9%'],
                windowWidth: 1200,
                windowHeight: 800
            });
        }

        // دالة تصدير Excel المحسنة
        function exportDebtAgingToExcel() {
            if (!currentReportData || !currentReportData.data) {
                alert('لا توجد بيانات للتصدير');
                return;
            }

            const dateFrom = $('#from_date').val() || 'غير محدد';
            const dateTo = $('#to_date').val() || 'غير محدد';
            const branchName = $('#branch option:selected').text() || 'جميع الفروع';
            const customerName = $('#customer option:selected').text() || 'جميع العملاء';
            const groupName = $('#region_group option:selected').text() || 'جميع المجموعات';

            const totals = currentReportData.totals || {};
            const data = currentReportData.data || [];

            // تجميع بيانات الجدول
            const tableData = [];
            let rowNumber = 1;

            data.forEach(function(item) {
                tableData.push({
                    values: [
                        rowNumber++,
                        item.client_code || '-',
                        item.client_name || '-',
                        item.branch || '-',
                        item.group || '-',
                        item.neighborhood || '-',
                        item.status || '-',
                        formatNumber(item.today || 0),
                        formatNumber(item.days1to30 || 0),
                        formatNumber(item.days31to60 || 0),
                        formatNumber(item.days61to90 || 0),
                        formatNumber(item.days91to120 || 0),
                        formatNumber(item.daysOver120 || 0),
                        formatNumber(item.total_due || 0)
                    ],
                    alignments: ['center', 'center', 'right', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center'],
                    cellStyles: [
                        null, null, null, null, null, null, null,
                        { color: 'success' },
                        { color: 'info' },
                        { color: 'warning' },
                        { color: 'danger' },
                        { color: 'primary' },
                        { color: 'gray' },
                        { color: 'primary', bold: true }
                    ]
                });
            });

            // إعداد التقرير
            const config = {
                fileName: 'تقرير_أعمار_ديون_الأستاذ_العام',
                mainTitle: 'تقرير أعمار ديون الأستاذ العام',
                subTitle: 'نظام إدارة العملاء',

                reportInfo: {
                    'الفترة': `من ${dateFrom} إلى ${dateTo}`,
                    'تاريخ التصدير': new Date().toLocaleDateString('ar-SA'),
                    'الفرع': branchName,
                    'العميل': customerName,
                    'المجموعة': groupName
                },

                statisticsCards: [
                    {
                        title: 'اليوم',
                        value: formatNumber(totals.today || 0),
                        unit: ' ر.س',
                        titleColor: 'success',
                        valueColor: 'success',
                        backgroundColor: 'successLight',
                        borderColor: 'success'
                    },
                    {
                        title: '1-30 يوم',
                        value: formatNumber(totals.days1to30 || 0),
                        unit: ' ر.س',
                        titleColor: 'info',
                        valueColor: 'info',
                        backgroundColor: 'grayLight',
                        borderColor: 'info'
                    },
                    {
                        title: '31-60 يوم',
                        value: formatNumber(totals.days31to60 || 0),
                        unit: ' ر.س',
                        titleColor: 'warning',
                        valueColor: 'warning',
                        backgroundColor: 'warningLight',
                        borderColor: 'warning'
                    },
                    {
                        title: '61-90 يوم',
                        value: formatNumber(totals.days61to90 || 0),
                        unit: ' ر.س',
                        titleColor: 'danger',
                        valueColor: 'danger',
                        backgroundColor: 'dangerLight',
                        borderColor: 'danger'
                    },
                    {
                        title: '91-120 يوم',
                        value: formatNumber(totals.days91to120 || 0),
                        unit: ' ر.س',
                        titleColor: 'primary',
                        valueColor: 'primary',
                        backgroundColor: 'purpleLight',
                        borderColor: 'primary'
                    },
                    {
                        title: '+120 يوم',
                        value: formatNumber(totals.daysOver120 || 0),
                        unit: ' ر.س',
                        titleColor: 'gray',
                        valueColor: 'gray',
                        backgroundColor: 'grayBg',
                        borderColor: 'gray'
                    }
                ],

                tableTitle: 'تفاصيل أعمار ديون العملاء',
                tableHeaders: ['#', 'كود العميل', 'اسم العميل', 'الفرع', 'المجموعة', 'الحي', 'الحالة', 'اليوم', '1-30', '31-60', '61-90', '91-120', '+120', 'الإجمالي'],
                columnWidths: [5, 10, 18, 10, 10, 10, 8, 10, 10, 10, 10, 10, 10, 12],

                tableData: tableData,

                summaryRows: [
                    {
                        values: ['', '', '', '', '', '', 'الإجمالي الكلي',
                            formatNumber(totals.today || 0),
                            formatNumber(totals.days1to30 || 0),
                            formatNumber(totals.days31to60 || 0),
                            formatNumber(totals.days61to90 || 0),
                            formatNumber(totals.days91to120 || 0),
                            formatNumber(totals.daysOver120 || 0),
                            formatNumber(totals.total_due || 0)
                        ],
                        mergeFrom: 1,
                        mergeTo: 7,
                        background: 'successBg',
                        color: 'success',
                        borderColor: 'success'
                    }
                ],

                summarySheet: {
                    sheetName: 'الملخص',
                    title: 'ملخص أعمار الديون',
                    tables: [
                        {
                            title: 'إحصائيات عامة',
                            headers: ['البيان', 'القيمة'],
                            rows: [
                                ['إجمالي العملاء', currentReportData.customers_count || 0],
                                ['إجمالي السجلات', currentReportData.records_count || 0],
                                ['إجمالي المستحق', formatNumber(totals.total_due || 0) + ' ر.س']
                            ]
                        },
                        {
                            title: 'أعمار الديون',
                            headers: ['الفترة', 'المبلغ'],
                            rows: [
                                ['اليوم', formatNumber(totals.today || 0) + ' ر.س'],
                                ['1-30 يوم', formatNumber(totals.days1to30 || 0) + ' ر.س'],
                                ['31-60 يوم', formatNumber(totals.days31to60 || 0) + ' ر.س'],
                                ['61-90 يوم', formatNumber(totals.days61to90 || 0) + ' ر.س'],
                                ['91-120 يوم', formatNumber(totals.days91to120 || 0) + ' ر.س'],
                                ['+120 يوم', formatNumber(totals.daysOver120 || 0) + ' ر.س']
                            ]
                        }
                    ]
                },

                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير تقرير أعمار الديون بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            };

            // استدعاء دالة التصدير
            exportToExcelProfessional(config);
        }
    </script>
@endsection
