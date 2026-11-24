@extends('sales::master')

@section('title')
    تقرير الأرباح الشامل للموظفين
@stop

@section('css')
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #e9ecef;
            --gray: #6c757d;
            --shadow: rgba(0, 0, 0, 0.08);
        }

        * {
            font-family: 'Cairo', sans-serif;
        }

        /* العنوان بدون خلفية */
        .page-header-simple {
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border);
        }

        .page-header-simple h1 {
            font-weight: 700;
            margin: 0;
            font-size: 2rem;
            color: var(--secondary);
        }

        .breadcrumb-custom .breadcrumb {
            background: transparent;
            margin: 0;
            padding: 0;
        }

        .breadcrumb-custom .breadcrumb-item a {
            color: var(--gray);
            text-decoration: none;
        }

        .breadcrumb-custom .breadcrumb-item.active {
            color: var(--primary);
        }

        /* الكروت الأساسية */
        .card-simple {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .card-simple:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header-simple {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .card-header-simple h5 {
            color: var(--secondary);
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .card-body-simple {
            padding: 1.5rem;
        }

        /* الأزرار - ألوان معدلة */
        .btn-simple {
            padding: 0.6rem 1.2rem;
            border: 2px solid;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }

        /* زر التصفية - رمادي */
        .btn-simple.filter {
            background: var(--gray);
            color: white;
            border-color: var(--gray);
        }

        .btn-simple.filter:hover {
            background: #5a6268;
            border-color: #5a6268;
            transform: translateY(-2px);
        }

        /* زر التصدير - أخضر */
        .btn-simple.export {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        .btn-simple.export:hover {
            background: #219653;
            border-color: #219653;
            transform: translateY(-2px);
        }

        /* زر الطباعة - برتقالي */
        .btn-simple.print {
            background: var(--warning);
            color: white;
            border-color: var(--warning);
        }

        .btn-simple.print:hover {
            background: #e67e22;
            border-color: #e67e22;
            transform: translateY(-2px);
        }

        /* زر إعادة التعيين - رمادي فاتح */
        .btn-simple.reset {
            background: transparent;
            color: var(--gray);
            border-color: var(--gray);
        }

        .btn-simple.reset:hover {
            background: var(--gray);
            color: white;
            transform: translateY(-2px);
        }

        /* إحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
        .stat-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
        .stat-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
        .stat-icon.danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* الرسم البياني */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        /* الجداول */
        .table-simple {
            width: 100%;
            border-collapse: collapse;
        }

        .table-simple thead {
            background: var(--light);
        }

        .table-simple th {
            padding: 1rem;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid var(--border);
            text-align: right;
        }

        .table-simple td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            text-align: right;
        }

        .table-simple tbody tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }

        /* الفلاتر - تصميم مضغوط */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .form-control-simple {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: white;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            height: 42px;
        }

        .form-control-simple:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        /* التحميل */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            z-index: 10;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* التبويب */
        .view-tabs {
            display: flex;
            gap: 0.5rem;
        }

        .view-tab {
            padding: 0.6rem 1.2rem;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .view-tab.active {
            background: var(--primary);
            color: white;
        }

        /* التجاوب */
        @media (max-width: 768px) {
            .page-header-simple h1 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-simple {
                width: 100%;
                justify-content: center;
            }
            
            .table-simple {
                font-size: 0.9rem;
            }

            .form-control-simple {
                font-size: 0.85rem;
            }

            .chart-container {
                height: 300px;
            }
        }

        /* تحسينات Select2 - حجم مضغوط */
        .select2-container--bootstrap-5 .select2-selection--single {
            border: 2px solid var(--border);
            border-radius: 8px;
            height: 42px;
            padding: 0.4rem 0.8rem;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding: 0;
            line-height: 1.5;
            font-size: 0.9rem;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 42px;
        }

        .select2-container--bootstrap-5 .select2-selection--single:focus {
            border-color: var(--primary);
        }

        /* تحسينات للشبكة */
        .row.g-2 {
            --bs-gutter-x: 0.5rem;
            --bs-gutter-y: 0.5rem;
        }

        .row.g-3 {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1rem;
        }

        /* أقسام الجدول */
        .table-section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
            color: white;
        }

        .table-section-header td {
            padding: 1rem;
            font-weight: 700;
            font-size: 1rem;
            border: none;
        }

        .table-grand-total {
            background: linear-gradient(135deg, var(--success) 0%, #219653 100%) !important;
            color: white;
        }

        .table-grand-total td {
            padding: 1.2rem 1rem;
            font-weight: 700;
            font-size: 1rem;
            border: none;
        }

        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .badge.bg-primary { background: var(--primary) !important; }
        .badge.bg-info { background: #17a2b8 !important; }
        .badge.bg-warning { background: var(--warning) !important; color: white; }
    </style>
@endsection

@section('content')

    {{-- من أجل `.sl-guide` وحدها؛ شاشاتُ التقارير على ورقة `rpt-*` الخاصّة
         بها ولا تتقاطع معها في محدِّد --}}
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    {{-- ══════════ إرشادُ الشاشة ══════════
         مطويّةٌ افتراضياً: من فتح تقريراً جاء لأرقامه --}}
    <div class="no-print">
        @include('sales::components.guide', [
            'key' => 'sales-reports.employee-profits',
            'title' => __('sales_reports.guide.hub.title'),
            'lead' => __('sales_reports.guide.hub.lead'),
            'open' => false,
            'blocks' => [
                [
                    __('sales_reports.guide.hub.a'),
                    __('sales_reports.guide.hub.b'),
                    __('sales_reports.guide.hub.c'),
                    __('sales_reports.guide.hub.d'),
                ],
            ],
        ])
    </div>
    <!-- رأس الصفحة بدون خلفية -->
    <div class="page-header-simple">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-chart-line me-3"></i>
                        تقرير الأرباح الشامل للموظفين
                    </h1>
                    <nav class="breadcrumb-custom">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="#"><i class="fas fa-home me-2"></i>الرئيسية</a>
                            </li>
                            <li class="breadcrumb-item active">تقرير الأرباح الشامل للموظفين</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- فلاتر التقرير -->
        <div class="card-simple">
            <div class="card-header-simple">
                <h5><i class="fas fa-filter me-2"></i>فلاتر البحث</h5>
            </div>
            <div class="card-body-simple">
                <form id="reportForm">
                    <div class="row g-2">
                        <!-- الصف الأول -->
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الموظف</label>
                            <select name="employee" id="employee" class="form-control-simple select2">
                                <option value="">جميع الموظفين</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">العميل</label>
                            <select name="client" id="client" class="form-control-simple select2">
                                <option value="">جميع العملاء</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->trade_name }}-{{ $client->code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الفرع</label>
                            <select name="branch" id="branch" class="form-control-simple select2">
                                <option value="">جميع الفروع</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">المنتج</label>
                            <select name="product" id="product" class="form-control-simple select2">
                                <option value="">جميع المنتجات</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- الصف الثاني -->
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">التصنيف</label>
                            <select name="category" id="category" class="form-control-simple select2">
                                <option value="">جميع التصنيفات</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">الماركة</label>
                            <select name="brand" id="brand" class="form-control-simple select2">
                                <option value="">جميع الماركات</option>
                                {{-- معرّف الماركة ← اسمها: الفلتر على products.brand_id --}}
                                @foreach ($brands as $brandId => $brandName)
                                    <option value="{{ $brandId }}">{{ $brandName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">نوع التقرير</label>
                            <select name="report_type" id="report_type" class="form-control-simple select2">
                                <option value="comprehensive">التقرير الشامل</option>
                                <option value="employee_profits">أرباح الموظفين</option>
                                <option value="client_profits">أرباح العملاء</option>
                                <option value="product_profits">أرباح المنتجات</option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">نوع التاريخ</label>
                            <select name="date_type" id="date_type" class="form-control-simple select2">
                                <option value="custom">مخصص</option>
                                <option value="today">اليوم</option>
                                <option value="yesterday">أمس</option>
                                <option value="this_week">هذا الأسبوع</option>
                                <option value="last_week">الأسبوع الماضي</option>
                                <option value="this_month">هذا الشهر</option>
                                <option value="last_month">الشهر الماضي</option>
                                <option value="this_year">هذا العام</option>
                                <option value="last_year">العام الماضي</option>
                            </select>
                        </div>

                        <!-- التواريخ -->
                        <div class="col-lg-3 col-md-6" id="custom_dates">
                            <label class="form-label">من تاريخ</label>
                            <input type="date" name="from_date" id="from_date" class="form-control-simple"
                                value="{{ $fromDate->format('Y-m-d') }}">
                        </div>

                        <div class="col-lg-3 col-md-6" id="custom_to_date">
                            <label class="form-label">إلى تاريخ</label>
                            <input type="date" name="to_date" id="to_date" class="form-control-simple"
                                value="{{ $toDate->format('Y-m-d') }}">
                        </div>

                        <!-- الأزرار -->
                        <div class="col-lg-6 col-md-12 d-flex align-items-end gap-2">
                            <button type="button" class="btn-simple filter" id="filterBtn">
                                <i class="fas fa-search"></i>تصفية
                            </button>
                            <button type="button" class="btn-simple reset" id="resetBtn">
                                <i class="fas fa-refresh"></i>إعادة تعيين
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value" id="totalRevenue">{{ number_format($totals['total_revenue'] ?? 0, 2) }}</div>
                <div class="stat-label">إجمالي الإيرادات (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-minus-circle"></i>
                </div>
                <div class="stat-value" id="totalCost">{{ number_format($totals['total_cost'] ?? 0, 2) }}</div>
                <div class="stat-label">إجمالي التكلفة (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value" id="totalProfit">{{ number_format($totals['total_profit'] ?? 0, 2) }}</div>
                <div class="stat-label">صافي الربح (ريال)</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-value" id="profitMargin">{{ number_format($totals['total_profit_margin'] ?? 0, 2) }}%</div>
                <div class="stat-label">هامش الربح</div>
            </div>
        </div>

        <!-- أدوات التقرير -->
        <div class="card-simple">
            <div class="card-body-simple">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn-simple export" id="exportExcel">
                            <i class="fas fa-file-excel"></i>تصدير إكسل
                        </button>
                        <button class="btn-simple print" id="printBtn">
                            <i class="fas fa-print"></i>طباعة
                        </button>
                    </div>

                    <div class="view-tabs">
                        <div class="view-tab active" id="summaryViewBtn">
                            <i class="fas fa-chart-pie"></i>ملخص
                        </div>
                        <div class="view-tab" id="detailViewBtn">
                            <i class="fas fa-list"></i>تفاصيل
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- قسم الرسم البياني -->
        <div class="card-simple fade-in mb-4" id="chartSection">
            <div class="card-header-simple">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    الرسم البياني للأرباح والإيرادات
                </h5>
            </div>
            <div class="card-body-simple">
                <div class="chart-container">
                    <canvas id="profitsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- جدول التقرير -->
        <div class="card-simple" id="reportContainer" style="position: relative;">
            <div class="loading-overlay">
                <div class="spinner"></div>
            </div>

            <div class="card-header-simple">
                <h5 id="reportTitle">
                    <i class="fas fa-table me-2"></i>
                    تقرير الأرباح الشامل من {{ $fromDate->format('d/m/Y') }} إلى {{ $toDate->format('d/m/Y') }}
                </h5>
            </div>
            <div class="card-body-simple p-0">
                <div class="table-responsive">
                    <table class="table-simple">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag me-2"></i>#</th>
                                <th><i class="fas fa-user-tie me-2"></i>الموظف</th>
                                <th><i class="fas fa-calendar me-2"></i>التاريخ</th>
                                <th><i class="fas fa-user me-2"></i>العميل</th>
                                <th><i class="fas fa-box me-2"></i>المنتج</th>
                                <th><i class="fas fa-sort-numeric-up me-2"></i>الكمية</th>
                                <th><i class="fas fa-dollar-sign me-2"></i>الإيراد (ريال)</th>
                                <th><i class="fas fa-minus-circle me-2"></i>التكلفة (ريال)</th>
                                <th><i class="fas fa-chart-line me-2"></i>الربح (ريال)</th>
                                <th><i class="fas fa-percentage me-2"></i>هامش الربح</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                            <!-- البيانات تأتي من AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

    <script>
        let profitsChart;

        $(document).ready(function() {
            // تهيئة Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dir: 'rtl',
                minimumResultsForSearch: 0,
                width: '100%'
            });

            // تحميل البيانات الأولية
            loadReportData();

            // أحداث الفلاتر
            $('#filterBtn').click(loadReportData);
            $('#resetBtn').click(resetFilters);
            $('.select2, input[type="date"]').change(function() {
                if ($('#date_type').val() === 'custom' || $(this).attr('id') !== 'date_type') {
                    loadReportData();
                }
            });

            // أحداث الأزرار
            $('#exportExcel').click(exportToExcel);
            $('#printBtn').click(() => window.print());

            // أحداث التبويب
            $('.view-tab').click(function() {
                $('.view-tab').removeClass('active');
                $(this).addClass('active');
                
                if ($(this).attr('id') === 'summaryViewBtn') {
                    $('#chartSection').show();
                    $('#reportContainer').hide();
                } else {
                    $('#chartSection').hide();
                    $('#reportContainer').show();
                }
            });

            // التعامل مع تغيير نوع التاريخ
            $('#date_type').change(function() {
                const dateType = $(this).val();
                if (dateType === 'custom') {
                    $('#custom_dates, #custom_to_date').fadeIn();
                } else {
                    $('#custom_dates, #custom_to_date').fadeOut();
                    setDateRange(dateType);
                }
                loadReportData();
            });

            function loadReportData() {
                $('.loading-overlay').fadeIn();
                
                $.ajax({
                    url: '{{ route('salesReports.employeeComprehensiveProfitsReportAjax') }}',
                    method: 'GET',
                    data: $('#reportForm').serialize(),
                    success: function(response) {
                        updateReportDisplay(response);
                        $('.loading-overlay').fadeOut();
                    },
                    error: function() {
                        alert('حدث خطأ في تحميل البيانات');
                        $('.loading-overlay').fadeOut();
                    }
                });
            }

            function resetFilters() {
                $('#reportForm')[0].reset();
                $('.select2').val(null).trigger('change');
                $('#date_type').val('custom').trigger('change');
                $('#from_date').val('{{ $fromDate->format('Y-m-d') }}');
                $('#to_date').val('{{ $toDate->format('Y-m-d') }}');
                loadReportData();
            }

            function setDateRange(dateType) {
                const today = new Date();
                let fromDate, toDate;

                switch (dateType) {
                    case 'today':
                        fromDate = toDate = today;
                        break;
                    case 'yesterday':
                        fromDate = toDate = new Date(today.getTime() - 24 * 60 * 60 * 1000);
                        break;
                    case 'this_week':
                        const startOfWeek = new Date(today);
                        startOfWeek.setDate(today.getDate() - today.getDay());
                        fromDate = startOfWeek;
                        toDate = today;
                        break;
                    case 'last_week':
                        const lastWeekEnd = new Date(today);
                        lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
                        const lastWeekStart = new Date(lastWeekEnd);
                        lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
                        fromDate = lastWeekStart;
                        toDate = lastWeekEnd;
                        break;
                    case 'this_month':
                        fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        toDate = today;
                        break;
                    case 'last_month':
                        fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                        break;
                    case 'this_year':
                        fromDate = new Date(today.getFullYear(), 0, 1);
                        toDate = today;
                        break;
                    case 'last_year':
                        fromDate = new Date(today.getFullYear() - 1, 0, 1);
                        toDate = new Date(today.getFullYear() - 1, 11, 31);
                        break;
                    default:
                        return;
                }

                $('#from_date').val(formatDateForInput(fromDate));
                $('#to_date').val(formatDateForInput(toDate));
            }

            function formatDateForInput(date) {
                return date.toISOString().split('T')[0];
            }

            function updateReportDisplay(data) {
                // تحديث الإحصائيات
                $('#totalRevenue').text(formatNumber(data.totals.total_revenue));
                $('#totalCost').text(formatNumber(data.totals.total_cost));
                $('#totalProfit').text(formatNumber(data.totals.total_profit));
                $('#profitMargin').text(formatNumber(data.totals.total_profit_margin) + '%');

                // تحديث عنوان التقرير
                $('#reportTitle').html(`
                    <i class="fas fa-table me-2"></i>
                    تقرير الأرباح الشامل من ${data.from_date} إلى ${data.to_date}
                `);

                // تحديث الرسم البياني
                updateChart(data.chart_data);

                // تحديث جدول البيانات
                updateTableBody(data.grouped_data, data.totals, data.report_type);
            }

            // دالة تحديث الرسم البياني
            function updateChart(chartData) {
                const ctx = document.getElementById('profitsChart').getContext('2d');

                if (profitsChart) {
                    profitsChart.destroy();
                }

                profitsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                                label: 'الإيرادات (ريال)',
                                data: chartData.revenues,
                                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                                borderColor: 'rgba(52, 152, 219, 1)',
                                borderWidth: 2,
                                borderRadius: 8,
                            },
                            {
                                label: 'التكلفة (ريال)',
                                data: chartData.costs,
                                backgroundColor: 'rgba(231, 76, 60, 0.7)',
                                borderColor: 'rgba(231, 76, 60, 1)',
                                borderWidth: 2,
                                borderRadius: 8,
                            },
                            {
                                label: 'الأرباح (ريال)',
                                data: chartData.profits,
                                backgroundColor: 'rgba(39, 174, 96, 0.7)',
                                borderColor: 'rgba(39, 174, 96, 1)',
                                borderWidth: 2,
                                borderRadius: 8,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                rtl: true,
                                labels: {
                                    font: {
                                        family: 'Cairo'
                                    }
                                }
                            },
                            tooltip: {
                                rtl: true,
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + formatNumber(context.parsed.y) + ' ' + window.AppCurrency.symbol;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatNumber(value) + ' ' + window.AppCurrency.symbol;
                                    },
                                    font: {
                                        family: 'Cairo'
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Cairo'
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function updateTableBody(groupedData, totals, reportType) {
                let tableHtml = '';
                let counter = 1;

                if (reportType === 'comprehensive') {
                    // عرض البيانات الشاملة مقسمة حسب الأقسام
                    if (groupedData.employees && groupedData.employees.length > 0) {
                        tableHtml += `
                            <tr class="table-section-header">
                                <td colspan="10">
                                    <i class="fas fa-users me-2"></i>
                                    <strong>أرباح الموظفين</strong>
                                </td>
                            </tr>
                        `;

                        groupedData.employees.forEach(employee => {
                            tableHtml += createEmployeeRow(employee, counter++);
                        });
                    }

                    if (groupedData.clients && groupedData.clients.length > 0) {
                        tableHtml += `
                            <tr class="table-section-header">
                                <td colspan="10">
                                    <i class="fas fa-user-friends me-2"></i>
                                    <strong>أرباح العملاء</strong>
                                </td>
                            </tr>
                        `;

                        groupedData.clients.forEach(client => {
                            tableHtml += createClientRow(client, counter++);
                        });
                    }

                    if (groupedData.products && groupedData.products.length > 0) {
                        tableHtml += `
                            <tr class="table-section-header">
                                <td colspan="10">
                                    <i class="fas fa-boxes me-2"></i>
                                    <strong>أرباح المنتجات</strong>
                                </td>
                            </tr>
                        `;

                        groupedData.products.forEach(product => {
                            tableHtml += createProductRow(product, counter++);
                        });
                    }
                } else {
                    // عرض البيانات حسب نوع التقرير المحدد
                    if (Array.isArray(groupedData)) {
                        groupedData.forEach(item => {
                            if (reportType === 'employee_profits') {
                                tableHtml += createEmployeeRow(item, counter++);
                            } else if (reportType === 'client_profits') {
                                tableHtml += createClientRow(item, counter++);
                            } else if (reportType === 'product_profits') {
                                tableHtml += createProductRow(item, counter++);
                            }
                        });
                    }
                }

                // صف الإجمالي العام
                tableHtml += `
                    <tr class="table-grand-total">
                        <td colspan="6">
                            <i class="fas fa-chart-bar me-2"></i>
                            <strong>المجموع الكلي</strong>
                        </td>
                        <td class="fw-bold">${formatNumber(totals.total_revenue)}</td>
                        <td class="fw-bold">${formatNumber(totals.total_cost)}</td>
                        <td class="fw-bold">${formatNumber(totals.total_profit)}</td>
                        <td class="fw-bold">${formatNumber(totals.total_profit_margin)}%</td>
                    </tr>
                `;

                $('#reportTableBody').html(tableHtml);
            }

            // دالة إنشاء صف الموظف
            function createEmployeeRow(employee, counter) {
                const profitClass = employee.total_profit >= 0 ? 'text-success' : 'text-danger';
                const profitMarginClass = employee.profit_margin >= 0 ? 'text-success' : 'text-danger';

                return `
                    <tr class="employee-row">
                        <td>${counter}</td>
                        <td>
                            <span class="badge bg-primary">
                                <i class="fas fa-user-tie me-1"></i>${employee.name}
                            </span>
                        </td>
                        <td>-</td>
                        <td>متعدد</td>
                        <td>متعدد</td>
                        <td>${formatNumber(employee.total_quantity)}</td>
                        <td class="text-primary fw-bold">${formatNumber(employee.total_revenue)}</td>
                        <td class="text-danger fw-bold">${formatNumber(employee.total_cost)}</td>
                        <td class="${profitClass} fw-bold">${formatNumber(employee.total_profit)}</td>
                        <td class="${profitMarginClass} fw-bold">${formatNumber(employee.profit_margin)}%</td>
                    </tr>
                `;
            }

            // دالة إنشاء صف العميل
            function createClientRow(client, counter) {
                const profitClass = client.total_profit >= 0 ? 'text-success' : 'text-danger';
                const profitMarginClass = client.profit_margin >= 0 ? 'text-success' : 'text-danger';

                return `
                    <tr class="client-row">
                        <td>${counter}</td>
                        <td>متعدد</td>
                        <td>-</td>
                        <td>
                            <span class="badge bg-info">
                                <i class="fas fa-user me-1"></i>${client.name}
                            </span>
                        </td>
                        <td>متعدد</td>
                        <td>${formatNumber(client.total_quantity)}</td>
                        <td class="text-primary fw-bold">${formatNumber(client.total_revenue)}</td>
                        <td class="text-danger fw-bold">${formatNumber(client.total_cost)}</td>
                        <td class="${profitClass} fw-bold">${formatNumber(client.total_profit)}</td>
                        <td class="${profitMarginClass} fw-bold">${formatNumber(client.profit_margin)}%</td>
                    </tr>
                `;
            }

            // دالة إنشاء صف المنتج
            function createProductRow(product, counter) {
                const profitClass = product.total_profit >= 0 ? 'text-success' : 'text-danger';
                const profitMarginClass = product.profit_margin >= 0 ? 'text-success' : 'text-danger';

                return `
                    <tr class="product-row">
                        <td>${counter}</td>
                        <td>متعدد</td>
                        <td>-</td>
                        <td>متعدد</td>
                        <td>
                            <span class="badge bg-warning">
                                <i class="fas fa-box me-1"></i>${product.name}
                            </span>
                            <br><small class="text-muted">${product.category} | ${product.brand}</small>
                        </td>
                        <td>${formatNumber(product.total_quantity)}</td>
                        <td class="text-primary fw-bold">${formatNumber(product.total_revenue)}</td>
                        <td class="text-danger fw-bold">${formatNumber(product.total_cost)}</td>
                        <td class="${profitClass} fw-bold">${formatNumber(product.total_profit)}</td>
                        <td class="${profitMarginClass} fw-bold">${formatNumber(product.profit_margin)}%</td>
                    </tr>
                `;
            }

            // دالة تنسيق الأرقام
            function formatNumber(number) {
                return parseFloat(number).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // دالة تصدير إكسل
            function exportToExcel() {
                const table = document.querySelector('#reportContainer table');
                const wb = XLSX.utils.table_to_book(table, {
                    raw: false,
                    cellDates: true
                });

                const today = new Date();
                const fileName = `تقرير_الأرباح_الشامل_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;

                XLSX.writeFile(wb, fileName);
            }
        });
    </script>
@endsection